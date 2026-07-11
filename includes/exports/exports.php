<?php

defined( 'ABSPATH' ) || exit;

function wpae_get_export_store(): array {
    $exports = get_option( 'wp_ai_executor_exports', [] );
    return is_array( $exports ) ? $exports : [];
}

function wpae_update_export_store( array $exports ): void {
    update_option( 'wp_ai_executor_exports', $exports, false );
}

function wpae_prune_export_store( array $exports ): array {
    $now = time();
    foreach ( $exports as $id => $export ) {
        if ( (int) ( $export['expires_at_unix'] ?? 0 ) < $now ) {
            unset( $exports[ $id ] );
        }
    }

    uasort( $exports, function ( array $a, array $b ): int {
        return (int) ( $b['created_at_unix'] ?? 0 ) <=> (int) ( $a['created_at_unix'] ?? 0 );
    } );

    return array_slice( $exports, 0, WPAE_EXPORT_MAX_ENTRIES, true );
}

function wpae_build_exports_summary( array $exports ): array {
    $now = time();
    $active = [];
    $expired_count = 0;
    $total_bytes = 0;

    foreach ( $exports as $id => $export ) {
        $expires_at_unix = (int) ( $export['expires_at_unix'] ?? 0 );
        if ( $expires_at_unix > 0 && $expires_at_unix < $now ) {
            $expired_count++;
            continue;
        }

        $bytes = (int) ( $export['bytes'] ?? 0 );
        $total_bytes += $bytes;
        $active[] = [
            'id' => (string) $id,
            'filename' => (string) ( $export['filename'] ?? 'wp-ai-export.json' ),
            'bytes' => $bytes,
            'created_at' => $export['created_at'] ?? null,
            'expires_at' => $export['expires_at'] ?? null,
            'endpoint' => get_rest_url( null, 'ai-executor/v1/exports/' . (string) $id ),
        ];
    }

    return [
        'active_count' => count( $active ),
        'expired_count' => $expired_count,
        'max_entries' => WPAE_EXPORT_MAX_ENTRIES,
        'ttl_seconds' => WPAE_EXPORT_TTL_SECONDS,
        'total_active_bytes' => $total_bytes,
        'storage' => 'wp_options',
        'exports' => $active,
    ];
}

function wpae_get_exports_summary(): WP_REST_Response {
    $exports = wpae_get_export_store();
    return new WP_REST_Response( array_merge( [ 'ok' => true ], wpae_build_exports_summary( $exports ) ), 200 );
}

function wpae_prune_exports(): WP_REST_Response {
    $before = wpae_get_export_store();
    $after = wpae_prune_export_store( $before );
    wpae_update_export_store( $after );

    return new WP_REST_Response( array_merge( [
        'ok' => true,
        'removed_count' => max( 0, count( $before ) - count( $after ) ),
        'before_count' => count( $before ),
        'after_count' => count( $after ),
    ], wpae_build_exports_summary( $after ) ), 200 );
}

function wpae_create_export( WP_REST_Request $request ) {
    $filename = sanitize_file_name( (string) ( $request->get_param( 'filename' ) ?: 'wp-ai-export-' . gmdate( 'Ymd-His' ) . '.json' ) );
    $payload = $request->get_param( 'data' );

    if ( $payload === null ) {
        return new WP_REST_Response( [ 'error' => 'data is required.' ], 400 );
    }

    if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'json' ) {
        $filename .= '.json';
    }

    $json = is_string( $payload )
        ? $payload
        : wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

    if ( ! is_string( $json ) || strlen( $json ) > 1024 * 1024 ) {
        return new WP_REST_Response( [ 'error' => 'Export JSON exceeds 1 MB limit or could not be encoded.' ], 413 );
    }

    $decoded = json_decode( $json, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_REST_Response( [ 'error' => 'Export data must be valid JSON.' ], 400 );
    }

    $now = time();
    $export_id = bin2hex( random_bytes( 12 ) );
    $exports = wpae_prune_export_store( wpae_get_export_store() );
    $exports[ $export_id ] = [
        'id' => $export_id,
        'filename' => $filename,
        'json' => $json,
        'bytes' => strlen( $json ),
        'created_at' => gmdate( 'c', $now ),
        'created_at_unix' => $now,
        'expires_at' => gmdate( 'c', $now + WPAE_EXPORT_TTL_SECONDS ),
        'expires_at_unix' => $now + WPAE_EXPORT_TTL_SECONDS,
    ];
    wpae_update_export_store( wpae_prune_export_store( $exports ) );

    return new WP_REST_Response( [
        'ok' => true,
        'id' => $export_id,
        'filename' => $filename,
        'bytes' => strlen( $json ),
        'expires_at' => $exports[ $export_id ]['expires_at'],
        'endpoint' => get_rest_url( null, 'ai-executor/v1/exports/' . $export_id ),
        'storage' => 'wp_options',
        'public_url' => null,
    ], 200 );
}

function wpae_get_export( WP_REST_Request $request ): WP_REST_Response {
    $export_id = sanitize_text_field( (string) $request->get_param( 'id' ) );
    $exports = wpae_prune_export_store( wpae_get_export_store() );

    if ( $export_id === '' || ! isset( $exports[ $export_id ] ) ) {
        wpae_update_export_store( $exports );
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid or expired export.' ], 404 );
    }

    $export = $exports[ $export_id ];
    $response = new WP_REST_Response( [
        'ok' => true,
        'id' => $export_id,
        'filename' => $export['filename'] ?? 'wp-ai-export.json',
        'data' => json_decode( (string) ( $export['json'] ?? '{}' ), true ),
        'bytes' => (int) ( $export['bytes'] ?? 0 ),
        'expires_at' => $export['expires_at'] ?? null,
    ], 200 );
    $response->header( 'Cache-Control', 'no-store, private' );

    return $response;
}

