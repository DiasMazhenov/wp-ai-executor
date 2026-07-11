<?php

defined( 'ABSPATH' ) || exit;

function wpae_is_allowed_package_update_url( string $source_url ): bool {
    $parts = wp_parse_url( $source_url );
    if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || ( $parts['host'] ?? '' ) !== 'github.com' ) {
        return false;
    }

    return (bool) preg_match( '#^/DiasMazhenov/wp-ai-executor/archive/[a-f0-9]{40}\.zip$#', (string) ( $parts['path'] ?? '' ) );
}

function wpae_is_safe_package_relative_path( string $path ): bool {
    if ( $path === '' || strlen( $path ) > 180 || strpos( $path, '..' ) !== false || strpos( $path, '\\' ) !== false ) {
        return false;
    }

    if ( ! preg_match( '#^(?:wp-ai-executor\.php|includes/[a-z0-9][a-z0-9_./-]*\.(?:php|json))$#i', $path ) ) {
        return false;
    }

    return basename( $path ) !== '';
}

function wpae_read_package_manifest( ZipArchive $archive ): array {
    $manifest_index = false;
    for ( $index = 0; $index < $archive->numFiles; $index++ ) {
        $name = (string) $archive->getNameIndex( $index );
        if ( preg_match( '#^[^/]+/wpae-package\.json$#', $name ) ) {
            $manifest_index = $index;
            break;
        }
    }

    if ( $manifest_index === false ) {
        return [ 'ok' => false, 'error' => 'Package manifest wpae-package.json is missing.' ];
    }

    $manifest_path = (string) $archive->getNameIndex( $manifest_index );
    $prefix = dirname( $manifest_path ) . '/';
    $decoded = json_decode( (string) $archive->getFromIndex( $manifest_index ), true );
    if ( ! is_array( $decoded ) || ( $decoded['format'] ?? '' ) !== 'wpae-plugin-package-v1' || ( $decoded['entrypoint'] ?? '' ) !== 'wp-ai-executor.php' || ! is_array( $decoded['files'] ?? null ) ) {
        return [ 'ok' => false, 'error' => 'Package manifest is invalid.' ];
    }

    $files = $decoded['files'];
    if ( count( $files ) === 0 || count( $files ) > 80 || ! isset( $files['wp-ai-executor.php'] ) ) {
        return [ 'ok' => false, 'error' => 'Package manifest has an invalid file set.' ];
    }

    $validated = [];
    foreach ( $files as $relative_path => $expected_hash ) {
        $relative_path = (string) $relative_path;
        $expected_hash = strtolower( (string) $expected_hash );
        if ( ! wpae_is_safe_package_relative_path( $relative_path ) || ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) ) {
            return [ 'ok' => false, 'error' => 'Package manifest contains an unsafe path or hash.' ];
        }

        $content = $archive->getFromName( $prefix . $relative_path );
        if ( $content === false || ! hash_equals( $expected_hash, hash( 'sha256', (string) $content ) ) ) {
            return [ 'ok' => false, 'error' => 'Package file hash does not match manifest.', 'path' => $relative_path ];
        }
        $validated[ $relative_path ] = (string) $content;
    }

    $entry_errors = wpae_validate_self_update_file( $validated['wp-ai-executor.php'] );
    if ( ! empty( $entry_errors ) ) {
        return [ 'ok' => false, 'error' => 'Package entrypoint failed validation.', 'details' => $entry_errors ];
    }

    return [
        'ok' => true,
        'files' => $validated,
        'manifest' => $decoded,
        'package_prefix' => $prefix,
    ];
}

function wpae_self_update_package( WP_REST_Request $request ): WP_REST_Response {
    $source_url = trim( (string) $request->get_param( 'package_url' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    if ( ! wpae_is_allowed_package_update_url( $source_url ) ) {
        return new WP_REST_Response( [
            'error' => 'Package update requires an immutable GitHub commit ZIP URL.',
            'allowed' => 'https://github.com/DiasMazhenov/wp-ai-executor/archive/<40-char-commit-sha>.zip',
        ], 400 );
    }
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_REST_Response( [ 'error' => 'ZipArchive is unavailable on this server.' ], 501 );
    }

    $response = wp_remote_get( $source_url, [ 'timeout' => 30, 'redirection' => 3, 'limit_response_size' => 20 * 1024 * 1024 ] );
    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return new WP_REST_Response( [
            'error' => 'Failed to download package update.',
            'message' => is_wp_error( $response ) ? $response->get_error_message() : null,
            'status' => is_wp_error( $response ) ? null : (int) wp_remote_retrieve_response_code( $response ),
        ], 502 );
    }

    $zip_path = wp_tempnam( 'wpae-package.zip' );
    if ( ! $zip_path || file_put_contents( $zip_path, (string) wp_remote_retrieve_body( $response ), LOCK_EX ) === false ) {
        return new WP_REST_Response( [ 'error' => 'Failed to stage package download.' ], 500 );
    }

    $archive = new ZipArchive();
    $opened = $archive->open( $zip_path );
    if ( $opened !== true ) {
        @unlink( $zip_path );
        return new WP_REST_Response( [ 'error' => 'Downloaded package is not a valid ZIP archive.' ], 422 );
    }
    $package = wpae_read_package_manifest( $archive );
    $archive->close();
    @unlink( $zip_path );
    if ( ! $package['ok'] ) {
        return new WP_REST_Response( $package, 422 );
    }

    $target_dir = dirname( __DIR__, 2 );
    $paths = array_keys( $package['files'] );
    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'source_url' => $source_url,
            'file_count' => count( $paths ),
            'paths' => $paths,
            'entrypoint_replaced_last' => true,
        ], 200 );
    }

    // Write every module first; the bootstrap switches to them only after all writes succeed.
    usort( $paths, static fn( string $a, string $b ): int => $a === 'wp-ai-executor.php' ? 1 : ( $b === 'wp-ai-executor.php' ? -1 : strcmp( $a, $b ) ) );
    $written = [];
    foreach ( $paths as $relative_path ) {
        $target = $target_dir . '/' . $relative_path;
        $directory = dirname( $target );
        if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
            return new WP_REST_Response( [ 'error' => 'Failed to create package directory.', 'path' => $relative_path, 'written' => $written ], 500 );
        }
        $temp = tempnam( $directory, '.wpae-package-' );
        if ( $temp === false || file_put_contents( $temp, $package['files'][ $relative_path ], LOCK_EX ) === false || ! @rename( $temp, $target ) ) {
            if ( is_string( $temp ) ) {
                @unlink( $temp );
            }
            return new WP_REST_Response( [ 'error' => 'Failed to replace package file.', 'path' => $relative_path, 'written' => $written ], 500 );
        }
        $written[] = $relative_path;
    }

    return new WP_REST_Response( [
        'ok' => true,
        'source_url' => $source_url,
        'file_count' => count( $written ),
        'entrypoint_replaced_last' => true,
    ], 200 );
}
