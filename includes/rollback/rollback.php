<?php

defined( 'ABSPATH' ) || exit;

function wpae_get_rollback_snapshots(): array {
    $snapshots = get_option( 'wp_ai_executor_rollback_snapshots', [] );
    return is_array( $snapshots ) ? $snapshots : [];
}

function wpae_update_rollback_snapshots( array $snapshots ): void {
    update_option( 'wp_ai_executor_rollback_snapshots', $snapshots, false );
}

function wpae_prune_rollback_snapshots( array $snapshots ): array {
    $now = time();
    foreach ( $snapshots as $id => $snapshot ) {
        if ( (int) ( $snapshot['expires_at_unix'] ?? 0 ) < $now ) {
            unset( $snapshots[ $id ] );
        }
    }

    uasort( $snapshots, function ( array $a, array $b ): int {
        return (int) ( $b['created_at_unix'] ?? 0 ) <=> (int) ( $a['created_at_unix'] ?? 0 );
    } );

    return array_slice( $snapshots, 0, WPAE_ROLLBACK_MAX_SNAPSHOTS, true );
}

function wpae_sanitize_rollback_post_ids( $post_ids ): array {
    if ( ! is_array( $post_ids ) ) {
        return [];
    }

    $post_ids = array_map( 'absint', $post_ids );
    $post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

    return array_slice( $post_ids, 0, 50 );
}

function wpae_sanitize_rollback_option_names( $option_names ): array {
    if ( ! is_array( $option_names ) ) {
        return [];
    }

    $clean = [];
    foreach ( $option_names as $name ) {
        $name = sanitize_key( (string) $name );
        if ( $name !== '' ) {
            $clean[] = $name;
        }
    }

    return array_slice( array_values( array_unique( $clean ) ), 0, 50 );
}

function wpae_capture_post_snapshot( int $post_id ): array {
    $post = get_post( $post_id, ARRAY_A );
    if ( ! is_array( $post ) ) {
        return [ 'exists' => false ];
    }

    return [
        'exists' => true,
        'post' => $post,
        'meta' => wpae_filter_managed_post_meta_snapshot( get_post_meta( $post_id ) ),
    ];
}

function wpae_capture_option_snapshot( string $option_name ): array {
    $sentinel = '__wpae_missing_option__';
    $value = get_option( $option_name, $sentinel );

    if ( $value === $sentinel ) {
        return [ 'exists' => false ];
    }

    return [
        'exists' => true,
        'value' => $value,
    ];
}

function wpae_managed_rollback_post_meta_keys(): array {
    return [
        '_elementor_data',
        '_elementor_edit_mode',
        '_elementor_template_type',
        '_elementor_version',
        '_elementor_css',
        '_wp_page_template',
        '_wpae_design_system_id',
        '_wpae_design_system_hash',
        '_wpae_blueprint',
        '_wpae_quality_summary',
    ];
}

function wpae_filter_managed_post_meta_snapshot( array $meta ): array {
    $allowed = array_fill_keys( wpae_managed_rollback_post_meta_keys(), true );
    return array_intersect_key( $meta, $allowed );
}

function wpae_prepare_restored_post_meta_value( string $meta_key, $value ) {
    if ( $meta_key === '_elementor_data' && is_string( $value ) ) {
        return wp_slash( $value );
    }

    return $value;
}

function wpae_validate_snapshot_elementor_meta( array $meta ): ?array {
    if ( ! array_key_exists( '_elementor_data', $meta ) ) {
        return null;
    }

    $values = is_array( $meta['_elementor_data'] ) ? $meta['_elementor_data'] : [ $meta['_elementor_data'] ];
    $raw_data = (string) ( $values[0] ?? '' );
    if ( trim( $raw_data ) === '' ) {
        return [
            'code' => 'empty_snapshot_elementor_data',
            'message' => 'Rollback snapshot contains an empty _elementor_data value.',
        ];
    }

    $decoded = json_decode( $raw_data, true );
    if ( is_array( $decoded ) ) {
        return null;
    }

    return [
        'code' => 'invalid_snapshot_elementor_data',
        'message' => 'Rollback snapshot _elementor_data is not valid JSON array data.',
        'json_error' => json_last_error_msg(),
    ];
}

function wpae_replace_managed_post_meta( int $post_id, array $meta ): void {
    $meta = wpae_filter_managed_post_meta_snapshot( $meta );
    $managed_keys = array_unique( array_merge( wpae_managed_rollback_post_meta_keys(), array_keys( $meta ) ) );

    foreach ( $managed_keys as $meta_key ) {
        delete_post_meta( $post_id, $meta_key );
    }

    foreach ( $meta as $meta_key => $values ) {
        if ( ! is_array( $values ) ) {
            $values = [ $values ];
        }

        foreach ( $values as $value ) {
            add_post_meta( $post_id, $meta_key, wpae_prepare_restored_post_meta_value( (string) $meta_key, $value ) );
        }
    }
}

function wpae_validate_restored_elementor_meta( int $post_id ): ?array {
    $raw_data = get_post_meta( $post_id, '_elementor_data', true );
    if ( ! is_string( $raw_data ) || trim( $raw_data ) === '' ) {
        return null;
    }

    $decoded = json_decode( $raw_data, true );
    if ( is_array( $decoded ) ) {
        return null;
    }

    return [
        'code' => 'invalid_restored_elementor_data',
        'message' => '_elementor_data was restored but does not decode as a JSON array.',
        'json_error' => json_last_error_msg(),
    ];
}

function wpae_create_rollback_snapshot( string $label, array $post_ids = [], array $option_names = [], array $created_post_ids = [] ): ?array {
    $post_ids = wpae_sanitize_rollback_post_ids( $post_ids );
    $option_names = wpae_sanitize_rollback_option_names( $option_names );
    $created_post_ids = wpae_sanitize_rollback_post_ids( $created_post_ids );
    $all_post_ids = array_values( array_unique( array_merge( $post_ids, $created_post_ids ) ) );

    if ( empty( $all_post_ids ) && empty( $option_names ) ) {
        return null;
    }

    $now = time();
    $snapshot_id = bin2hex( random_bytes( 12 ) );
    $snapshot = [
        'id' => $snapshot_id,
        'label' => sanitize_text_field( $label ),
        'created_at' => gmdate( 'c', $now ),
        'created_at_unix' => $now,
        'expires_at' => gmdate( 'c', $now + WPAE_ROLLBACK_TTL_SECONDS ),
        'expires_at_unix' => $now + WPAE_ROLLBACK_TTL_SECONDS,
        'posts' => [],
        'options' => [],
    ];

    foreach ( $all_post_ids as $post_id ) {
        $snapshot['posts'][ (string) $post_id ] = in_array( $post_id, $created_post_ids, true )
            ? [ 'exists' => false ]
            : wpae_capture_post_snapshot( $post_id );
    }

    foreach ( $option_names as $option_name ) {
        $snapshot['options'][ $option_name ] = wpae_capture_option_snapshot( $option_name );
    }

    $snapshots = wpae_prune_rollback_snapshots( wpae_get_rollback_snapshots() );
    $snapshots[ $snapshot_id ] = $snapshot;
    wpae_update_rollback_snapshots( wpae_prune_rollback_snapshots( $snapshots ) );

    return [
        'id' => $snapshot_id,
        'expires_at' => $snapshot['expires_at'],
    ];
}

function wpae_restore_post_snapshot( int $post_id, array $snapshot ): array {
    if ( empty( $snapshot['exists'] ) ) {
        if ( get_post( $post_id ) !== null ) {
            wp_delete_post( $post_id, true );
        }
        return [ 'post_id' => $post_id, 'action' => 'deleted_created_post' ];
    }

    $meta = is_array( $snapshot['meta'] ?? null ) ? $snapshot['meta'] : [];
    $meta = wpae_filter_managed_post_meta_snapshot( $meta );
    $snapshot_validation_error = wpae_validate_snapshot_elementor_meta( $meta );
    if ( $snapshot_validation_error !== null ) {
        return [
            'post_id' => $post_id,
            'action' => 'failed_preflight',
            'error' => $snapshot_validation_error,
        ];
    }

    $before = wpae_capture_post_snapshot( $post_id );
    $post = is_array( $snapshot['post'] ?? null ) ? $snapshot['post'] : [];
    if ( empty( $post['ID'] ) ) {
        $post['ID'] = $post_id;
    }

    $result = wp_update_post( $post, true );
    if ( is_wp_error( $result ) ) {
        return [ 'post_id' => $post_id, 'action' => 'failed', 'error' => $result->get_error_message() ];
    }

    wpae_replace_managed_post_meta( $post_id, $meta );

    wpae_clear_elementor_cache( $post_id );

    $elementor_validation_error = wpae_validate_restored_elementor_meta( $post_id );
    if ( $elementor_validation_error !== null ) {
        $before_meta = is_array( $before['meta'] ?? null ) ? $before['meta'] : [];
        $before_meta = wpae_filter_managed_post_meta_snapshot( $before_meta );
        $before_validation_error = wpae_validate_snapshot_elementor_meta( $before_meta );

        if ( empty( $before['exists'] ) || $before_validation_error !== null ) {
            return [
                'post_id' => $post_id,
                'action' => 'failed_after_write',
                'error' => $elementor_validation_error,
                'recovery' => 'unavailable',
            ];
        }

        wpae_replace_managed_post_meta( $post_id, $before_meta );
        wp_update_post( (array) ( $before['post'] ?? [] ) );
        wpae_clear_elementor_cache( $post_id );

        return [
            'post_id' => $post_id,
            'action' => 'failed_after_write_recovered',
            'error' => $elementor_validation_error,
            'recovery' => 'previous_post_and_meta_restored',
        ];
    }

    return [ 'post_id' => $post_id, 'action' => 'restored' ];
}

function wpae_restore_option_snapshot( string $option_name, array $snapshot ): array {
    if ( empty( $snapshot['exists'] ) ) {
        delete_option( $option_name );
        return [ 'option' => $option_name, 'action' => 'deleted_created_option' ];
    }

    update_option( $option_name, $snapshot['value'] ?? null, false );
    return [ 'option' => $option_name, 'action' => 'restored' ];
}

function wpae_restore_rollback_snapshot_by_id( string $snapshot_id, bool $consume = true ): array {
    $snapshots = wpae_prune_rollback_snapshots( wpae_get_rollback_snapshots() );

    if ( $snapshot_id === '' || ! isset( $snapshots[ $snapshot_id ] ) ) {
        wpae_update_rollback_snapshots( $snapshots );
        return [
            'ok' => false,
            'status' => 404,
            'snapshot_id' => $snapshot_id,
            'error' => 'Invalid or expired rollback snapshot.',
        ];
    }

    $snapshot = $snapshots[ $snapshot_id ];
    $restored_posts = [];
    $restored_options = [];

    foreach ( (array) ( $snapshot['posts'] ?? [] ) as $post_id => $post_snapshot ) {
        $restored_posts[] = wpae_restore_post_snapshot( absint( $post_id ), (array) $post_snapshot );
    }

    foreach ( (array) ( $snapshot['options'] ?? [] ) as $option_name => $option_snapshot ) {
        $restored_options[] = wpae_restore_option_snapshot( sanitize_key( (string) $option_name ), (array) $option_snapshot );
    }

    $failed_posts = array_values( array_filter( $restored_posts, function ( array $result ): bool {
        return strpos( (string) ( $result['action'] ?? '' ), 'failed' ) === 0;
    } ) );

    if ( ! empty( $failed_posts ) ) {
        wpae_update_rollback_snapshots( $snapshots );
        return [
            'ok' => false,
            'status' => 422,
            'snapshot_id' => $snapshot_id,
            'label' => $snapshot['label'] ?? '',
            'error' => 'Rollback did not complete. The snapshot has been retained for recovery.',
            'restored_posts' => $restored_posts,
            'restored_options' => $restored_options,
        ];
    }

    if ( $consume ) {
        unset( $snapshots[ $snapshot_id ] );
    }
    wpae_update_rollback_snapshots( $snapshots );

    return [
        'ok' => true,
        'status' => 200,
        'snapshot_id' => $snapshot_id,
        'label' => $snapshot['label'] ?? '',
        'restored_posts' => $restored_posts,
        'restored_options' => $restored_options,
    ];
}

function wpae_rollback( WP_REST_Request $request ): WP_REST_Response {
    $snapshot_id = sanitize_text_field( (string) $request->get_param( 'snapshot_id' ) );
    $rollback = wpae_restore_rollback_snapshot_by_id( $snapshot_id, true );

    return new WP_REST_Response( $rollback, (int) ( $rollback['status'] ?? ( ! empty( $rollback['ok'] ) ? 200 : 422 ) ) );
}

function wpae_list_rollback_snapshots( WP_REST_Request $request ): WP_REST_Response {
    $snapshots = wpae_prune_rollback_snapshots( wpae_get_rollback_snapshots() );
    wpae_update_rollback_snapshots( $snapshots );

    $items = [];
    foreach ( $snapshots as $snapshot_id => $snapshot ) {
        $posts = [];
        foreach ( (array) ( $snapshot['posts'] ?? [] ) as $post_id => $post_snapshot ) {
            $meta = is_array( $post_snapshot['meta'] ?? null ) ? $post_snapshot['meta'] : [];
            $elementor_data_values = $meta['_elementor_data'] ?? [];
            $elementor_data = is_array( $elementor_data_values ) ? (string) ( $elementor_data_values[0] ?? '' ) : (string) $elementor_data_values;
            $posts[] = [
                'post_id' => absint( $post_id ),
                'exists' => ! empty( $post_snapshot['exists'] ),
                'post_title' => (string) ( $post_snapshot['post']['post_title'] ?? '' ),
                'post_modified' => (string) ( $post_snapshot['post']['post_modified'] ?? '' ),
                'elementor_data_bytes' => strlen( $elementor_data ),
                'managed_meta_keys' => array_values( array_keys( $meta ) ),
            ];
        }

        $items[] = [
            'id' => (string) $snapshot_id,
            'label' => (string) ( $snapshot['label'] ?? '' ),
            'created_at' => (string) ( $snapshot['created_at'] ?? '' ),
            'expires_at' => (string) ( $snapshot['expires_at'] ?? '' ),
            'posts' => $posts,
            'option_names' => array_values( array_keys( (array) ( $snapshot['options'] ?? [] ) ) ),
        ];
    }

    usort( $items, fn( $a, $b ) => strcmp( (string) ( $b['created_at'] ?? '' ), (string) ( $a['created_at'] ?? '' ) ) );

    return new WP_REST_Response( [
        'ok' => true,
        'count' => count( $items ),
        'snapshots' => $items,
    ], 200 );
}

function wpae_try_decode_elementor_json( string $value ): array {
    $value = trim( wp_unslash( $value ) );
    if ( $value === '' ) {
        return [ 'ok' => false, 'data' => null, 'count' => 0 ];
    }

    $decoded = json_decode( $value, true );
    if ( ! is_array( $decoded ) ) {
        return [ 'ok' => false, 'data' => null, 'count' => 0 ];
    }

    return [
        'ok' => true,
        'data' => $decoded,
        'count' => count( $decoded ),
    ];
}

function wpae_revision_elementor_data_source( int $revision_id ): array {
    $meta_value = get_post_meta( $revision_id, '_elementor_data', true );
    if ( is_string( $meta_value ) && $meta_value !== '' ) {
        $decoded = wpae_try_decode_elementor_json( $meta_value );
        if ( $decoded['ok'] ) {
            return [
                'source' => 'revision_meta',
                'raw' => $meta_value,
                'decoded' => $decoded['data'],
                'element_count' => $decoded['count'],
            ];
        }
    }

    $revision = get_post( $revision_id );
    $content = $revision ? (string) $revision->post_content : '';
    $decoded = wpae_try_decode_elementor_json( $content );
    if ( $decoded['ok'] ) {
        return [
            'source' => 'revision_post_content_json',
            'raw' => $content,
            'decoded' => $decoded['data'],
            'element_count' => $decoded['count'],
        ];
    }

    return [
        'source' => 'none',
        'raw' => '',
        'decoded' => null,
        'element_count' => 0,
    ];
}

function wpae_elementor_revisions( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Valid post_id is required.' ], 400 );
    }

    $limit = max( 1, min( 80, absint( $request->get_param( 'limit' ) ?: 40 ) ) );
    $revisions = wp_get_post_revisions( $post_id, [
        'posts_per_page' => $limit,
        'order' => 'DESC',
        'orderby' => 'date',
    ] );

    $items = [];
    foreach ( $revisions as $revision ) {
        $revision_id = (int) $revision->ID;
        $source = wpae_revision_elementor_data_source( $revision_id );
        $meta_keys = array_keys( get_post_meta( $revision_id ) );
        $items[] = [
            'revision_id' => $revision_id,
            'post_date' => (string) $revision->post_date,
            'post_modified' => (string) $revision->post_modified,
            'post_title' => (string) $revision->post_title,
            'post_author' => (int) $revision->post_author,
            'post_content_bytes' => strlen( (string) $revision->post_content ),
            'elementor_data_source' => $source['source'],
            'elementor_data_bytes' => strlen( (string) $source['raw'] ),
            'element_count' => (int) $source['element_count'],
            'managed_meta_keys' => array_values( array_intersect( $meta_keys, wpae_managed_rollback_post_meta_keys() ) ),
        ];
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'count' => count( $items ),
        'revisions' => $items,
    ], 200 );
}

function wpae_elementor_restore_revision( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $revision_id = absint( $request->get_param( 'revision_id' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );

    $post = get_post( $post_id );
    $revision = get_post( $revision_id );
    if ( ! $post || ! $revision || (int) wp_is_post_revision( $revision_id ) !== $post_id ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Valid post_id and matching revision_id are required.' ], 400 );
    }

    $source = wpae_revision_elementor_data_source( $revision_id );
    if ( $source['source'] === 'none' || ! is_array( $source['decoded'] ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Revision does not contain restorable Elementor data.',
            'revision_id' => $revision_id,
        ], 422 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $source['decoded'] );
    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'revision_id' => $revision_id,
            'elementor_data_source' => $source['source'],
            'element_count' => $source['element_count'],
            'validation_errors' => $validation_errors,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_restore_revision:' . $post_id, [ $post_id ] );

    wp_update_post( [
        'ID' => $post_id,
        'post_title' => $revision->post_title ?: $post->post_title,
        'post_excerpt' => $revision->post_excerpt,
    ] );

    update_post_meta( $post_id, '_elementor_edit_mode', get_post_meta( $revision_id, '_elementor_edit_mode', true ) ?: 'builder' );
    update_post_meta( $post_id, '_elementor_template_type', get_post_meta( $revision_id, '_elementor_template_type', true ) ?: 'wp-page' );
    update_post_meta( $post_id, '_elementor_version', get_post_meta( $revision_id, '_elementor_version', true ) ?: ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' ) );
    update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $source['decoded'] ) ) );
    update_post_meta( $post_id, '_wp_page_template', get_post_meta( $revision_id, '_wp_page_template', true ) ?: get_post_meta( $post_id, '_wp_page_template', true ) ?: 'elementor_canvas' );

    foreach ( [ '_wpae_design_system_id', '_wpae_design_system_hash', '_wpae_blueprint', '_wpae_quality_summary' ] as $meta_key ) {
        $value = get_post_meta( $revision_id, $meta_key, true );
        if ( $value !== '' ) {
            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    delete_post_meta( $post_id, '_elementor_css' );
    wpae_clear_elementor_cache( $post_id );

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'revision_id' => $revision_id,
        'elementor_data_source' => $source['source'],
        'element_count' => $source['element_count'],
        'validation_errors' => $validation_errors,
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
    ], 200 );
}
