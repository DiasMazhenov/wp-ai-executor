<?php

defined( 'ABSPATH' ) || exit;

const WPAE_BLOCK_LIBRARY_POST_TYPE = 'wpae_block';
const WPAE_BLOCK_LIBRARY_SCHEMA = 'wpae-elementor-block-v1';
const WPAE_BLOCK_LIBRARY_SCHEMA_VERSION = '1.0.0';
const WPAE_BLOCK_LIBRARY_MAX_BYTES = 1048576;
const WPAE_BLOCK_LIBRARY_META_KEY = '_wpae_block_payload';

add_action( 'init', function (): void {
    register_post_type( WPAE_BLOCK_LIBRARY_POST_TYPE, [
        'labels' => [
            'name' => 'WPAE Elementor Blocks',
            'singular_name' => 'WPAE Elementor Block',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'supports' => [ 'title', 'editor', 'author', 'revisions' ],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ] );

    register_post_meta( WPAE_BLOCK_LIBRARY_POST_TYPE, WPAE_BLOCK_LIBRARY_META_KEY, [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'revisions_enabled' => true,
        'sanitize_callback' => static function ( $value ): string {
            return is_string( $value ) ? $value : '';
        },
        'auth_callback' => static function (): bool {
            return current_user_can( 'edit_posts' );
        },
    ] );
}, 5 );

function wpae_block_library_is_list( array $value ): bool {
    $index = 0;
    foreach ( array_keys( $value ) as $key ) {
        if ( $key !== $index ) {
            return false;
        }
        $index++;
    }
    return true;
}

function wpae_block_library_decode_input( $payload ) {
    if ( is_string( $payload ) ) {
        if ( strlen( $payload ) > WPAE_BLOCK_LIBRARY_MAX_BYTES ) {
            return new WP_Error( 'wpae_block_too_large', 'Elementor block JSON exceeds the 1 MB limit.', [ 'status' => 413 ] );
        }

        $payload = json_decode( $payload, true );
        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'wpae_invalid_block_json', 'Elementor block must be valid JSON.', [ 'status' => 400, 'json_error' => json_last_error_msg() ] );
        }
    }

    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'wpae_missing_block_json', 'A native Elementor JSON block or WPAE library block is required.', [ 'status' => 400 ] );
    }

    $encoded = wp_json_encode( $payload );
    if ( ! is_string( $encoded ) || strlen( $encoded ) > WPAE_BLOCK_LIBRARY_MAX_BYTES ) {
        return new WP_Error( 'wpae_block_too_large', 'Elementor block JSON exceeds the 1 MB limit.', [ 'status' => 413 ] );
    }

    return $payload;
}

function wpae_block_library_extract_elements( $payload ) {
    $payload = wpae_block_library_decode_input( $payload );
    if ( is_wp_error( $payload ) ) {
        return $payload;
    }

    $source_mode = (string) ( $payload['schema'] ?? '' ) === WPAE_BLOCK_LIBRARY_SCHEMA
        ? 'wpae_library_block'
        : 'native_elementor_json';

    if ( $source_mode === 'wpae_library_block' ) {
        $elements = $payload['elementor_data'] ?? null;
    } elseif ( isset( $payload['content'] ) && is_array( $payload['content'] ) ) {
        $elements = $payload['content'];
    } elseif ( isset( $payload['elementor_data'] ) && is_array( $payload['elementor_data'] ) ) {
        $elements = $payload['elementor_data'];
    } elseif ( isset( $payload['elType'] ) ) {
        $elements = [ $payload ];
    } elseif ( wpae_block_library_is_list( $payload ) ) {
        $elements = $payload;
    } else {
        return new WP_Error(
            'wpae_unsupported_block_shape',
            'Expected a native Elementor element, element list, template content, or wpae-elementor-block-v1 payload.',
            [ 'status' => 422 ]
        );
    }

    if ( ! is_array( $elements ) || empty( $elements ) ) {
        return new WP_Error( 'wpae_empty_block', 'Elementor block does not contain any elements.', [ 'status' => 422 ] );
    }

    return [
        'source_mode' => $source_mode,
        'source_payload' => $payload,
        'elementor_data' => array_values( $elements ),
    ];
}

function wpae_block_library_collect_media_references( $value, array &$references, string $path = 'settings' ): void {
    if ( is_array( $value ) ) {
        $id = absint( $value['id'] ?? 0 );
        $url = esc_url_raw( (string) ( $value['url'] ?? '' ) );
        $looks_like_media = $id > 0
            || strpos( $url, '/wp-content/uploads/' ) !== false
            || preg_match( '/\.(?:avif|gif|jpe?g|mp3|mp4|ogg|png|svg|wav|webm|webp)(?:[?#]|$)/i', $url );
        if ( $looks_like_media ) {
            $key = $id > 0 ? 'id:' . $id : 'url:' . hash( 'sha256', $url );
            $references[ $key ] = [
                'attachment_id' => $id ?: null,
                'url' => $url !== '' ? $url : null,
                'path' => $path,
            ];
        }
        foreach ( $value as $key => $child ) {
            wpae_block_library_collect_media_references( $child, $references, $path . '.' . sanitize_key( (string) $key ) );
        }
    }
}

function wpae_block_library_collect_stats_walk( array $elements, array &$stats ): void {
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $stats['elements']++;
        $el_type = (string) ( $element['elType'] ?? '' );
        if ( $el_type === 'widget' ) {
            $stats['widgets']++;
            $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? $element['widget_type'] ?? '' ) );
            if ( $widget_type !== '' ) {
                $stats['widget_types'][ $widget_type ] = true;
            }
        } else {
            $stats['containers']++;
        }

        if ( $el_type === 'section' || $el_type === 'column' ) {
            $stats['legacy_elements']++;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $system_id = sanitize_key( (string) ( $settings['_wpae_design_system_id'] ?? '' ) );
        if ( $system_id === '' ) {
            $classes = (string) ( $settings['_css_classes'] ?? '' );
            if ( preg_match( '/(?:^|\s)wpae-system-([a-z0-9-]+)/', $classes, $matches ) ) {
                $system_id = sanitize_key( (string) $matches[1] );
            }
        }
        if ( $system_id !== '' ) {
            $stats['design_system_ids'][ $system_id ] = true;
        }
        wpae_block_library_collect_media_references( $settings, $stats['media_references'], 'settings.' . (string) ( $element['id'] ?? 'unknown' ) );

        if ( is_array( $element['elements'] ?? null ) ) {
            wpae_block_library_collect_stats_walk( $element['elements'], $stats );
        }
    }
}

function wpae_block_library_collect_stats( array $elements ): array {
    $stats = [
        'elements' => 0,
        'containers' => 0,
        'widgets' => 0,
        'widget_types' => [],
        'legacy_elements' => 0,
        'design_system_ids' => [],
        'media_references' => [],
    ];
    wpae_block_library_collect_stats_walk( $elements, $stats );
    $stats['widget_types'] = array_values( array_keys( $stats['widget_types'] ) );
    $stats['design_system_ids'] = array_values( array_keys( $stats['design_system_ids'] ) );
    $stats['media_references'] = array_values( $stats['media_references'] );
    sort( $stats['widget_types'] );
    sort( $stats['design_system_ids'] );

    return $stats;
}

function wpae_block_library_compatibility_report( array $elementor_data ): array {
    $raw_errors = wpae_validate_elementor_data_array( $elementor_data );
    $normalized = wpae_elementor_normalize_data( $elementor_data );
    $normalized_errors = wpae_validate_elementor_data_array( $normalized['data'] );
    $stats = wpae_block_library_collect_stats( $elementor_data );
    $protected_zones = array_values( wpae_collect_elementor_protected_zones( $elementor_data ) );
    $unavailable_widgets = [];
    if (
        class_exists( '\Elementor\Plugin' ) &&
        isset( \Elementor\Plugin::$instance->widgets_manager ) &&
        is_object( \Elementor\Plugin::$instance->widgets_manager )
    ) {
        $available_widgets = array_keys( (array) \Elementor\Plugin::$instance->widgets_manager->get_widget_types() );
        $unavailable_widgets = array_values( array_diff( $stats['widget_types'], $available_widgets ) );
        sort( $unavailable_widgets );
    }

    return [
        'raw_valid' => empty( $raw_errors ),
        'raw_errors' => array_values( $raw_errors ),
        'normalizable' => empty( $normalized_errors ),
        'normalized_errors' => array_values( $normalized_errors ),
        'normalization_counts' => $normalized['report']['counts'],
        'stats' => $stats,
        'unavailable_widget_types' => $unavailable_widgets,
        'protected_enhancement_zones' => $protected_zones,
        'current_design_system_id' => wpae_get_design_system_id(),
        'foreign_design_system' => ! empty( $stats['design_system_ids'] )
            && ! in_array( wpae_get_design_system_id(), $stats['design_system_ids'], true ),
    ];
}

function wpae_block_library_sanitize_tags( $tags ): array {
    if ( is_string( $tags ) ) {
        $tags = preg_split( '/[,;\n]+/', $tags );
    }
    $tags = is_array( $tags ) ? $tags : [];
    $sanitized = [];
    foreach ( array_slice( $tags, 0, 20 ) as $tag ) {
        $tag = sanitize_text_field( (string) $tag );
        if ( $tag !== '' ) {
            $sanitized[] = $tag;
        }
    }
    return array_values( array_unique( $sanitized ) );
}

function wpae_block_library_build_record( array $parsed, array $input, array $existing = [] ): array {
    $elements = $parsed['elementor_data'];
    $source_payload = $parsed['source_payload'];
    $source_metadata = $parsed['source_mode'] === 'wpae_library_block' && is_array( $source_payload )
        ? $source_payload
        : [];
    $compatibility = wpae_block_library_compatibility_report( $elements );
    $stats = $compatibility['stats'];
    $now = gmdate( 'c' );
    $source = sanitize_key( (string) ( $input['source'] ?? $source_metadata['source'] ?? $existing['source'] ?? 'foreign' ) );
    if ( ! in_array( $source, [ 'local', 'foreign', 'agent', 'import' ], true ) ) {
        $source = 'foreign';
    }

    $title = sanitize_text_field( (string) ( $input['title'] ?? $input['name'] ?? $source_metadata['title'] ?? $existing['title'] ?? '' ) );
    if ( $title === '' ) {
        $title = 'Elementor block ' . gmdate( 'Y-m-d H:i' );
    }

    $detected_design_system = count( $stats['design_system_ids'] ) === 1 ? $stats['design_system_ids'][0] : null;
    $design_system_id = sanitize_key( (string) ( $input['design_system_id'] ?? $source_metadata['design_system_id'] ?? $existing['design_system_id'] ?? $detected_design_system ?? '' ) );
    $native_payload = $parsed['source_mode'] === 'wpae_library_block'
        ? ( $source_metadata['native_payload'] ?? $elements )
        : $source_payload;

    return [
        'schema' => WPAE_BLOCK_LIBRARY_SCHEMA,
        'schema_version' => WPAE_BLOCK_LIBRARY_SCHEMA_VERSION,
        'title' => $title,
        'description' => sanitize_textarea_field( (string) ( $input['description'] ?? $source_metadata['description'] ?? $existing['description'] ?? '' ) ),
        'category' => sanitize_key( (string) ( $input['category'] ?? $source_metadata['category'] ?? $existing['category'] ?? 'custom' ) ) ?: 'custom',
        'tags' => wpae_block_library_sanitize_tags( $input['tags'] ?? $source_metadata['tags'] ?? $existing['tags'] ?? [] ),
        'source_mode' => $parsed['source_mode'],
        'source' => $source,
        'source_post_id' => absint( $input['source_post_id'] ?? $source_metadata['source_post_id'] ?? $existing['source_post_id'] ?? 0 ),
        'elementor_version' => sanitize_text_field( (string) ( $input['elementor_version'] ?? $source_metadata['elementor_version'] ?? $existing['elementor_version'] ?? '' ) ),
        'design_system_id' => $design_system_id !== '' ? $design_system_id : null,
        'native_payload' => $native_payload,
        'elementor_data' => $elements,
        'compatibility' => $compatibility,
        'content_hash' => hash( 'sha256', (string) wp_json_encode( $elements ) ),
        'created_at' => (string) ( $existing['created_at'] ?? $now ),
        'updated_at' => $now,
    ];
}

function wpae_block_library_decode_post( WP_Post $post ) {
    $stored_json = get_post_meta( $post->ID, WPAE_BLOCK_LIBRARY_META_KEY, true );
    $record = json_decode( (string) $stored_json, true );
    if ( ! is_array( $record ) || (string) ( $record['schema'] ?? '' ) !== WPAE_BLOCK_LIBRARY_SCHEMA ) {
        return new WP_Error( 'wpae_invalid_stored_block', 'Stored Elementor block has an invalid schema.', [ 'status' => 500, 'id' => $post->ID ] );
    }
    $record['id'] = $post->ID;
    $record['title'] = get_the_title( $post );
    return $record;
}

function wpae_block_library_summary( array $record ): array {
    return [
        'id' => (int) ( $record['id'] ?? 0 ),
        'title' => (string) ( $record['title'] ?? '' ),
        'description' => (string) ( $record['description'] ?? '' ),
        'category' => (string) ( $record['category'] ?? 'custom' ),
        'tags' => array_values( (array) ( $record['tags'] ?? [] ) ),
        'source_mode' => (string) ( $record['source_mode'] ?? 'native_elementor_json' ),
        'source' => (string) ( $record['source'] ?? 'foreign' ),
        'elementor_version' => (string) ( $record['elementor_version'] ?? '' ),
        'design_system_id' => $record['design_system_id'] ?? null,
        'content_hash' => (string) ( $record['content_hash'] ?? '' ),
        'compatibility' => [
            'raw_valid' => (bool) ( $record['compatibility']['raw_valid'] ?? false ),
            'normalizable' => (bool) ( $record['compatibility']['normalizable'] ?? false ),
            'foreign_design_system' => (bool) ( $record['compatibility']['foreign_design_system'] ?? false ),
            'stats' => $record['compatibility']['stats'] ?? [],
        ],
        'created_at' => $record['created_at'] ?? null,
        'updated_at' => $record['updated_at'] ?? null,
    ];
}

function wpae_block_library_dashboard_summary( int $limit = 5 ): array {
    $posts = get_posts( [
        'post_type' => WPAE_BLOCK_LIBRARY_POST_TYPE,
        'post_status' => 'private',
        'posts_per_page' => max( 1, min( 20, $limit ) ),
        'orderby' => 'modified',
        'order' => 'DESC',
    ] );
    $items = [];
    foreach ( $posts as $post ) {
        $record = wpae_block_library_decode_post( $post );
        if ( ! is_wp_error( $record ) ) {
            $items[] = wpae_block_library_summary( $record );
        }
    }
    $counts = wp_count_posts( WPAE_BLOCK_LIBRARY_POST_TYPE );
    return [
        'count' => isset( $counts->private ) ? (int) $counts->private : count( $items ),
        'items' => $items,
    ];
}

function wpae_block_library_get_post( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! ( $post instanceof WP_Post ) || $post->post_type !== WPAE_BLOCK_LIBRARY_POST_TYPE || $post->post_status === 'trash' ) {
        return new WP_Error( 'wpae_block_not_found', 'Elementor library block was not found.', [ 'status' => 404 ] );
    }
    return $post;
}

function wpae_block_library_request_payload( WP_REST_Request $request ) {
    $json = $request->get_json_params();
    if (
        is_array( $json )
        && (
            isset( $json['schema'] )
            || isset( $json['content'] )
            || isset( $json['elType'] )
            || wpae_block_library_is_list( $json )
        )
    ) {
        return $json;
    }

    foreach ( [ 'block', 'data', 'elementor_data' ] as $key ) {
        $value = $request->get_param( $key );
        if ( $value !== null ) {
            return $value;
        }
    }

    return null;
}

function wpae_block_library_list( WP_REST_Request $request ): WP_REST_Response {
    $limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
    $query = sanitize_text_field( (string) $request->get_param( 'q' ) );
    $category = sanitize_key( (string) $request->get_param( 'category' ) );
    $tag = sanitize_text_field( (string) $request->get_param( 'tag' ) );
    $posts = get_posts( [
        'post_type' => WPAE_BLOCK_LIBRARY_POST_TYPE,
        'post_status' => 'private',
        'posts_per_page' => 200,
        'orderby' => 'modified',
        'order' => 'DESC',
        's' => $query,
    ] );

    $items = [];
    foreach ( $posts as $post ) {
        $record = wpae_block_library_decode_post( $post );
        if ( is_wp_error( $record ) ) {
            continue;
        }
        if ( $category !== '' && (string) $record['category'] !== $category ) {
            continue;
        }
        if ( $tag !== '' && ! in_array( $tag, (array) $record['tags'], true ) ) {
            continue;
        }
        $items[] = wpae_block_library_summary( $record );
        if ( count( $items ) >= $limit ) {
            break;
        }
    }

    return new WP_REST_Response( [
        'ok' => true,
        'schema' => WPAE_BLOCK_LIBRARY_SCHEMA,
        'count' => count( $items ),
        'items' => $items,
        'supported_input_formats' => [ 'native_elementor_json', WPAE_BLOCK_LIBRARY_SCHEMA ],
        'instantiate_modes' => [ 'preserve', 'compatibility', 'adapt' ],
    ], 200 );
}

function wpae_block_library_get( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_block_library_get_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $post->get_error_message(), 'code' => $post->get_error_code() ], 404 );
    }
    $record = wpae_block_library_decode_post( $post );
    if ( is_wp_error( $record ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $record->get_error_message(), 'code' => $record->get_error_code() ], 500 );
    }
    return new WP_REST_Response( [ 'ok' => true, 'block' => $record ], 200 );
}

function wpae_block_library_save( WP_REST_Request $request ): WP_REST_Response {
    $input = $request->get_json_params();
    $input = is_array( $input ) ? $input : $request->get_params();
    $parsed = wpae_block_library_extract_elements( wpae_block_library_request_payload( $request ) );
    if ( is_wp_error( $parsed ) ) {
        $error_data = $parsed->get_error_data();
        $status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 400 ) : 400;
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $parsed->get_error_message(),
            'code' => $parsed->get_error_code(),
            'details' => $error_data,
        ], $status );
    }

    $record = wpae_block_library_build_record( $parsed, $input );
    $record_json = (string) wp_json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $post_id = wp_insert_post( [
        'post_type' => WPAE_BLOCK_LIBRARY_POST_TYPE,
        'post_status' => 'private',
        'post_title' => $record['title'],
        'post_content' => '',
        'post_author' => get_current_user_id(),
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Failed to save Elementor block.',
            'code' => $post_id->get_error_code(),
            'details' => $post_id->get_error_message(),
        ], 500 );
    }
    if ( update_post_meta( $post_id, WPAE_BLOCK_LIBRARY_META_KEY, wp_slash( $record_json ) ) === false ) {
        wp_delete_post( $post_id, true );
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Failed to save Elementor block payload.',
            'cleanup' => [ 'created_library_post_deleted' => true ],
        ], 500 );
    }

    $record['id'] = (int) $post_id;
    return new WP_REST_Response( [
        'ok' => true,
        'created' => true,
        'block' => wpae_block_library_summary( $record ),
        'endpoints' => [
            'get' => get_rest_url( null, 'ai-executor/v1/elementor/blocks/' . $post_id ),
            'instantiate' => get_rest_url( null, 'ai-executor/v1/elementor/blocks/' . $post_id . '/instantiate' ),
        ],
    ], 201 );
}

function wpae_block_library_delete( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_block_library_get_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $post->get_error_message(), 'code' => $post->get_error_code() ], 404 );
    }
    $deleted = wp_delete_post( $post->ID, true );
    if ( ! ( $deleted instanceof WP_Post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to delete Elementor library block.' ], 500 );
    }
    return new WP_REST_Response( [ 'ok' => true, 'deleted' => true, 'id' => $post->ID ], 200 );
}

function wpae_block_library_instantiate( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_block_library_get_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $post->get_error_message(), 'code' => $post->get_error_code() ], 404 );
    }
    $record = wpae_block_library_decode_post( $post );
    if ( is_wp_error( $record ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $record->get_error_message(), 'code' => $record->get_error_code() ], 500 );
    }

    $mode = sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'preserve' ) );
    if ( ! in_array( $mode, [ 'preserve', 'compatibility', 'adapt' ], true ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Unsupported instantiate mode.',
            'available_modes' => [ 'preserve', 'compatibility', 'adapt' ],
        ], 400 );
    }

    $instance_id = sanitize_key( (string) ( $request->get_param( 'instance_id' ) ?: 'block-' . $post->ID . '-' . wp_generate_password( 8, false, false ) ) );
    $elementor_data = (array) $record['elementor_data'];
    $normalization = [ 'counts' => [], 'changes' => [] ];
    if ( $mode !== 'preserve' ) {
        $normalized = wpae_elementor_normalize_data( $elementor_data );
        $elementor_data = $normalized['data'];
        $normalization = $normalized['report'];
    }
    $elementor_data = wpae_rekey_elementor_ids_recursive( $elementor_data, $instance_id );
    $token_map = null;
    if ( $mode === 'adapt' ) {
        $adapted = wpae_apply_design_token_map( $elementor_data );
        $elementor_data = $adapted['data'];
        $token_map = $adapted['report'];
    }
    $errors = wpae_validate_elementor_data_array( $elementor_data );
    $protected_zones = array_values( wpae_collect_elementor_protected_zones( $elementor_data ) );
    $warnings = [];
    if ( $mode === 'preserve' && ! empty( $protected_zones ) ) {
        $warnings[] = 'Elementor IDs were rekeyed. Verify that protected HTML/JS/WebGL code does not target the old data-id values.';
    }
    if ( ! empty( $record['compatibility']['unavailable_widget_types'] ) ) {
        $warnings[] = 'The target site is missing one or more widget types used by this block.';
    }

    $structured_write_ready = empty( $errors );
    $ok = $mode === 'preserve' || $structured_write_ready;

    return new WP_REST_Response( [
        'ok' => $ok,
        'block_id' => $post->ID,
        'mode' => $mode,
        'instance_id' => $instance_id,
        'preserves_original_design' => $mode === 'preserve',
        'structured_write_ready' => $structured_write_ready,
        'adaptation_scope' => $mode === 'adapt' ? 'Deterministic semantic token mapping through native Elementor settings.' : null,
        'token_map' => $token_map,
        'normalization' => $normalization,
        'errors' => array_values( $errors ),
        'warnings' => $warnings,
        'elementor_data' => $elementor_data,
        'protected_enhancement_zones' => $protected_zones,
        'next_steps' => [
            'Merge the returned elementor_data into the target page structure.',
            'Run /elementor/validate and the target write endpoint with dry_run=true.',
            'Use /elementor/update or /elementor/patch only after dry-run passes.',
        ],
    ], $ok ? 200 : 422 );
}

function wpae_block_library_has_api_key( WP_REST_Request $request ): bool {
    return wpae_get_request_api_key( $request ) !== '';
}

function wpae_block_library_read_permission( WP_REST_Request $request ) {
    if ( wpae_block_library_has_api_key( $request ) ) {
        return wpae_auth( $request );
    }
    return current_user_can( 'edit_posts' );
}

function wpae_block_library_write_permission( WP_REST_Request $request ) {
    if ( wpae_block_library_has_api_key( $request ) ) {
        return wpae_auth_with_capability( $request, 'elementor_writes' );
    }
    if ( strtoupper( (string) $request->get_method() ) === 'DELETE' ) {
        return current_user_can( 'delete_post', absint( $request['id'] ) );
    }
    return current_user_can( 'edit_posts' );
}

function wpae_block_library_asset_source( string $relative_path ): string {
    $path = dirname( __DIR__, 2 ) . '/' . ltrim( $relative_path, '/' );
    if ( ! is_readable( $path ) ) {
        return '';
    }
    $source = file_get_contents( $path );
    return is_string( $source ) ? $source : '';
}

function wpae_enqueue_elementor_block_library_editor_style(): void {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }
    $style_source = wpae_block_library_asset_source( 'assets/css/elementor-block-library.css' );
    if ( $style_source !== '' ) {
        wp_add_inline_style( 'elementor-editor', $style_source );
    }
}
add_action( 'elementor/editor/after_enqueue_styles', 'wpae_enqueue_elementor_block_library_editor_style' );

function wpae_enqueue_elementor_block_library_editor(): void {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $menu_script_source = wpae_block_library_asset_source( 'assets/js/elementor-block-library.js' );
    $ui_script_source = wpae_block_library_asset_source( 'assets/js/elementor-block-library-ui.js' );
    if ( $menu_script_source === '' || $ui_script_source === '' ) {
        return;
    }

    $config = wp_json_encode( [
        'endpoint' => get_rest_url( null, 'ai-executor/v1/elementor/blocks' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'strings' => [
            'copy' => 'Копировать как JSON',
            'save' => 'Сохранить в библиотеку WP AI Executor',
            'openLibrary' => 'Открыть библиотеку блоков',
            'titlePrompt' => 'Название блока',
            'categoryPrompt' => 'Категория блока',
            'copied' => 'Elementor JSON скопирован.',
            'saved' => 'Блок сохранён в библиотеку WP AI Executor.',
            'failed' => 'Не удалось выполнить действие с блоком.',
            'selection' => 'Выбранные элементы',
            'noSelection' => 'Не удалось определить выбранный элемент Elementor.',
            'library' => 'WP AI Executor',
            'libraryTitle' => 'Библиотека блоков',
            'close' => 'Закрыть',
            'search' => 'Поиск по названию, категории или тегу',
            'allCategories' => 'Все категории',
            'insertMode' => 'Режим вставки',
            'preserve' => 'Оригинал',
            'compatibility' => 'Совместимость',
            'adapt' => 'Адаптация',
            'refresh' => 'Обновить',
            'blocks' => 'Блоки',
            'blockDetails' => 'Состав блока',
            'selectBlock' => 'Выберите блок, чтобы посмотреть состав и совместимость.',
            'category' => 'Категория',
            'elements' => 'Элементы',
            'containers' => 'Контейнеры',
            'widgets' => 'Виджеты',
            'elementorVersion' => 'Elementor',
            'valid' => 'Готов к вставке',
            'needsNormalization' => 'Нужна нормализация',
            'foreignDesign' => 'Чужая дизайн-система',
            'insert' => 'Вставить',
            'inserting' => 'Вставка…',
            'inserted' => 'Блок вставлен в Elementor.',
            'insertTargetMissing' => 'Выберите контейнер или элемент, рядом с которым нужно вставить блок.',
            'loading' => 'Загрузка библиотеки…',
            'noBlocks' => 'Блоки не найдены.',
            'units' => 'элем.',
            'shortContainers' => 'конт.',
            'shortWidgets' => 'видж.',
        ],
    ] );
    if ( is_string( $config ) ) {
        wp_add_inline_script(
            'elementor-editor',
            'window.WPAEBlockLibrary = ' . $config . ';'
                . "\n" . $ui_script_source
                . "\n" . $menu_script_source,
            'before'
        );
    }
}
add_action( 'elementor/editor/after_enqueue_scripts', 'wpae_enqueue_elementor_block_library_editor' );
