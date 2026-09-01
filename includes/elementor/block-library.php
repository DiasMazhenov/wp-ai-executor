<?php

defined( 'ABSPATH' ) || exit;

const WPAE_BLOCK_LIBRARY_POST_TYPE = 'wpae_block';
const WPAE_BLOCK_LIBRARY_SCHEMA = 'wpae-elementor-block-v1';
const WPAE_BLOCK_LIBRARY_SCHEMA_VERSION = '1.1.0';
const WPAE_BLOCK_LIBRARY_MANIFEST_SCHEMA = 'wpae-elementor-block-manifest-v1';
const WPAE_BLOCK_LIBRARY_MAX_BYTES = 4194304;
const WPAE_BLOCK_LIBRARY_MAX_METADATA_BYTES = 32768;
const WPAE_BLOCK_LIBRARY_META_KEY = '_wpae_block_payload';
const WPAE_BLOCK_LIBRARY_FIXTURE_OPTION = 'wpae_block_library_fixture_state';

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
            return new WP_Error( 'wpae_block_too_large', 'Elementor block JSON exceeds the 4 MB limit.', [ 'status' => 413 ] );
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
        return new WP_Error( 'wpae_block_too_large', 'Elementor block JSON exceeds the 4 MB limit.', [ 'status' => 413 ] );
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
    } elseif ( isset( $payload['elements'] ) && is_array( $payload['elements'] ) ) {
        $source_mode = 'elementor_export';
        $elements = $payload['elements'];
    } elseif ( isset( $payload['elType'] ) ) {
        $elements = [ $payload ];
    } elseif ( wpae_block_library_is_list( $payload ) ) {
        $elements = $payload;
    } else {
        return new WP_Error(
            'wpae_unsupported_block_shape',
            'Expected a native Elementor element, element list, Elementor export with an elements array, template content, or wpae-elementor-block-v1 payload.',
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

function wpae_block_library_filter_compatible_roots( array $elementor_data ): array {
    $compatible = [];
    foreach ( $elementor_data as $element ) {
        if ( ! is_array( $element ) || (string) ( $element['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        $candidate = $element;
        if ( function_exists( 'wpae_elementor_normalize_data' ) ) {
            $normalized = wpae_elementor_normalize_data( [ $element ] );
            $candidate = is_array( $normalized['data'][0] ?? null ) ? $normalized['data'][0] : $element;
        }
        $report = wpae_block_library_compatibility_report( [ $candidate ] );
        if ( ! empty( $report['raw_valid'] ) && ! empty( $report['normalizable'] ) && empty( $report['unavailable_widget_types'] ) ) {
            $compatible[] = $candidate;
        }
    }
    return $compatible;
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

function wpae_block_library_statuses(): array {
    return [ 'draft', 'approved', 'published' ];
}

function wpae_block_library_status( array $record, string $fallback = 'published' ): string {
    $status = sanitize_key( (string) ( $record['status'] ?? $record['manifest']['status'] ?? $fallback ) );
    return in_array( $status, wpae_block_library_statuses(), true ) ? $status : $fallback;
}

function wpae_block_library_sanitize_source_skill( $value ): array {
    if ( is_string( $value ) ) {
        return [ 'id' => sanitize_key( $value ) ];
    }
    if ( ! is_array( $value ) ) {
        return [];
    }
    return array_filter( [
        'id' => sanitize_key( (string) ( $value['id'] ?? '' ) ),
        'version' => sanitize_text_field( (string) ( $value['version'] ?? '' ) ),
        'source' => esc_url_raw( (string) ( $value['source'] ?? '' ) ),
    ] );
}

function wpae_block_library_sanitize_provenance( $value, array $fallback = [] ): array {
    $value = is_array( $value ) ? $value : [];
    $source_url = esc_url_raw( (string) ( $value['source_url'] ?? $fallback['source_url'] ?? '' ) );
    $license = sanitize_text_field( (string) ( $value['license'] ?? $fallback['license'] ?? '' ) );
    $attribution = sanitize_textarea_field( (string) ( $value['attribution'] ?? $fallback['attribution'] ?? '' ) );
    return array_filter( [
        'source' => sanitize_key( (string) ( $value['source'] ?? $fallback['source'] ?? 'foreign' ) ),
        'source_post_id' => absint( $value['source_post_id'] ?? $fallback['source_post_id'] ?? 0 ) ?: null,
        'source_url' => $source_url,
        'license' => $license,
        'attribution' => $attribution,
    ] );
}

function wpae_block_library_normalize_record_manifest( array $record ): array {
    $manifest = is_array( $record['manifest'] ?? null ) ? $record['manifest'] : [];
    $status = wpae_block_library_status( $record );
    if ( ( $manifest['schema'] ?? '' ) !== WPAE_BLOCK_LIBRARY_MANIFEST_SCHEMA ) {
        $manifest = [
            'schema' => WPAE_BLOCK_LIBRARY_MANIFEST_SCHEMA,
            'version' => '1.0.0',
            'status' => $status,
            'source_skill' => [],
            'design_system' => [ 'id' => $record['design_system_id'] ?? null, 'version' => null ],
            'provenance' => wpae_block_library_sanitize_provenance( [
                'source' => $record['source'] ?? 'foreign',
                'source_post_id' => $record['source_post_id'] ?? 0,
            ] ),
            'parent_revision' => null,
            'quality' => [ 'score' => null, 'review_state' => $status === 'published' ? 'approved' : $status ],
            'media_dependencies' => (array) ( $record['compatibility']['stats']['media_references'] ?? [] ),
        ];
    }
    $manifest['status'] = $status;
    $manifest['schema'] = WPAE_BLOCK_LIBRARY_MANIFEST_SCHEMA;
    $manifest['version'] = sanitize_text_field( (string) ( $manifest['version'] ?? '1.0.0' ) );
    return $manifest;
}

function wpae_block_library_manifest_metadata_size_ok( array $manifest ): bool {
    $encoded = wp_json_encode( $manifest );
    return is_string( $encoded ) && strlen( $encoded ) <= WPAE_BLOCK_LIBRARY_MAX_METADATA_BYTES;
}

function wpae_block_library_build_record( array $parsed, array $input, array $existing = [] ): array {
    $raw_elements = $parsed['elementor_data'];
    $normalized = wpae_elementor_normalize_data( $raw_elements );
    $elements = $normalized['data'];
    $source_payload = $parsed['source_payload'];
    $source_metadata = $parsed['source_mode'] === 'wpae_library_block' && is_array( $source_payload )
        ? $source_payload
        : [];
    $compatibility = wpae_block_library_compatibility_report( $elements );
    $stats = $compatibility['stats'];
    $source_manifest = is_array( $source_metadata['manifest'] ?? null ) ? $source_metadata['manifest'] : [];
    $now = gmdate( 'c' );
    $source = sanitize_key( (string) ( $input['source'] ?? $source_metadata['source'] ?? $existing['source'] ?? 'foreign' ) );
    if ( ! in_array( $source, [ 'local', 'foreign', 'agent', 'import', 'copyelement' ], true ) ) {
        $source = 'foreign';
    }

    $title = sanitize_text_field( (string) ( $input['title'] ?? $input['name'] ?? $source_metadata['title'] ?? $existing['title'] ?? '' ) );
    if ( $title === '' ) {
        $title = 'Elementor block ' . gmdate( 'Y-m-d H:i' );
    }

    $detected_design_system = count( $stats['design_system_ids'] ) === 1 ? $stats['design_system_ids'][0] : null;
    $design_system_id = sanitize_key( (string) ( $input['design_system_id'] ?? $source_metadata['design_system_id'] ?? $source_manifest['design_system']['id'] ?? $existing['design_system_id'] ?? $detected_design_system ?? '' ) );
    $native_payload = $parsed['source_mode'] === 'wpae_library_block'
        ? ( $source_metadata['native_payload'] ?? $elements )
        : $source_payload;
    $preview_url = esc_url_raw( (string) ( $input['preview_url'] ?? $source_metadata['preview_url'] ?? $existing['preview_url'] ?? '' ) );

    $status = empty( $existing ) ? 'draft' : 'draft';
    $source_skill = wpae_block_library_sanitize_source_skill( $input['source_skill'] ?? $source_metadata['source_skill'] ?? $source_manifest['source_skill'] ?? $existing['manifest']['source_skill'] ?? [] );
    $provenance = wpae_block_library_sanitize_provenance(
        $input['provenance'] ?? $source_metadata['provenance'] ?? $source_manifest['provenance'] ?? $existing['manifest']['provenance'] ?? [],
        [
            'source' => $source,
            'source_post_id' => $input['source_post_id'] ?? $source_metadata['source_post_id'] ?? $existing['source_post_id'] ?? 0,
            'source_url' => $input['source_url'] ?? '',
            'license' => $input['license'] ?? '',
        ]
    );
    $manifest = [
        'schema' => WPAE_BLOCK_LIBRARY_MANIFEST_SCHEMA,
        'version' => '1.0.0',
        'status' => $status,
        'source_skill' => $source_skill,
        'design_system' => [
            'id' => $design_system_id !== '' ? $design_system_id : null,
            'version' => sanitize_text_field( (string) ( $input['design_system_version'] ?? $source_metadata['design_system_version'] ?? $source_manifest['design_system']['version'] ?? $existing['manifest']['design_system']['version'] ?? '' ) ) ?: null,
        ],
        'provenance' => $provenance,
        'parent_revision' => absint( $input['parent_revision'] ?? $source_metadata['parent_revision'] ?? $existing['manifest']['parent_revision'] ?? 0 ) ?: null,
        'quality' => [ 'score' => null, 'review_state' => 'draft' ],
        'media_dependencies' => $stats['media_references'],
    ];

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
        'preview_url' => $preview_url !== '' ? $preview_url : null,
        'elementor_version' => sanitize_text_field( (string) ( $input['elementor_version'] ?? $source_metadata['elementor_version'] ?? $existing['elementor_version'] ?? '' ) ),
        'design_system_id' => $design_system_id !== '' ? $design_system_id : null,
        'native_payload' => $native_payload,
        'elementor_data' => $elements,
        'compatibility' => $compatibility,
        'content_hash' => hash( 'sha256', (string) wp_json_encode( $elements ) ),
        'status' => $status,
        'manifest' => $manifest,
        'created_at' => (string) ( $existing['created_at'] ?? $now ),
        'updated_at' => $now,
    ];
}

function wpae_block_library_bundled_fixtures(): array {
    static $fixtures;
    if ( is_array( $fixtures ) ) {
        return $fixtures;
    }

    $manifest_path = __DIR__ . '/copyelement/manifest.php';
    if ( ! is_readable( $manifest_path ) ) {
        return $fixtures = [];
    }

    $loaded = include $manifest_path;
    return $fixtures = is_array( $loaded ) ? $loaded : [];
}

function wpae_block_library_bundled_preview_url( string $preview_file ): string {
    $preview_file = ltrim( $preview_file, '/' );
    if ( ! preg_match( '#^assets/[a-z0-9][a-z0-9_./-]*\.(?:png|jpe?g)$#i', $preview_file ) || ! function_exists( 'plugins_url' ) ) {
        return '';
    }

    return esc_url_raw( (string) plugins_url( $preview_file, dirname( __DIR__, 2 ) . '/wp-ai-executor.php' ) );
}

function wpae_block_library_is_trusted_bundled_fixture( array $record ): bool {
    if ( (string) ( $record['source'] ?? '' ) !== 'copyelement' ) {
        return false;
    }

    $fixture_id = sanitize_key( (string) ( $record['bundled_fixture_id'] ?? '' ) );
    $fixture_hash = strtolower( (string) ( $record['bundled_fixture_sha256'] ?? '' ) );
    $content_hash = strtolower( (string) ( $record['bundled_fixture_content_hash'] ?? '' ) );
    $elementor_data = (array) ( $record['elementor_data'] ?? [] );
    if (
        $fixture_id === ''
        || ! preg_match( '/^[a-f0-9]{64}$/', $fixture_hash )
        || ! preg_match( '/^[a-f0-9]{64}$/', $content_hash )
        || ! hash_equals( $content_hash, strtolower( (string) ( $record['content_hash'] ?? '' ) ) )
        || ! hash_equals( $content_hash, hash( 'sha256', (string) wp_json_encode( $elementor_data ) ) )
    ) {
        return false;
    }

    foreach ( wpae_block_library_bundled_fixtures() as $fixture ) {
        if (
            is_array( $fixture )
            && $fixture_id === sanitize_key( (string) ( $fixture['id'] ?? '' ) )
            && hash_equals( $fixture_hash, strtolower( (string) ( $fixture['sha256'] ?? '' ) ) )
        ) {
            return true;
        }
    }

    return false;
}

function wpae_block_library_seed_bundled_templates(): array {
    static $result;
    if ( is_array( $result ) ) {
        return $result;
    }

    $result = [ 'imported' => [], 'migrated' => [], 'skipped' => [] ];
    $state = get_option( WPAE_BLOCK_LIBRARY_FIXTURE_OPTION, [] );
    $state = is_array( $state ) ? $state : [];

    foreach ( wpae_block_library_bundled_fixtures() as $fixture ) {
        if ( ! is_array( $fixture ) ) {
            continue;
        }

        $fixture_id = sanitize_key( (string) ( $fixture['id'] ?? '' ) );
        $filename = basename( (string) ( $fixture['file'] ?? '' ) );
        $expected_hash = strtolower( (string) ( $fixture['sha256'] ?? '' ) );
        if ( $fixture_id === '' || $filename === '' || ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) ) {
            $result['skipped'][] = $fixture_id ?: 'invalid';
            continue;
        }

        $path = __DIR__ . '/copyelement/' . $filename;
        $parsed = null;
        $fixture_content_hash = '';
        if ( is_readable( $path ) && hash_equals( $expected_hash, (string) hash_file( 'sha256', $path ) ) ) {
            $raw = file_get_contents( $path );
            $parsed = wpae_block_library_extract_elements( is_string( $raw ) ? $raw : '' );
            if ( ! is_wp_error( $parsed ) ) {
                $fixture_content_hash = hash( 'sha256', (string) wp_json_encode( wpae_elementor_normalize_data( $parsed['elementor_data'] )['data'] ) );
            }
        }

        $source_url = esc_url_raw( (string) ( $fixture['source_url'] ?? '' ) );
        $input = [
            'title' => (string) ( $fixture['title'] ?? $fixture_id ),
            'description' => (string) ( $fixture['description'] ?? '' ),
            'category' => (string) ( $fixture['category'] ?? 'custom' ),
            'tags' => (array) ( $fixture['tags'] ?? [] ),
            'source' => 'copyelement',
            'preview_url' => (string) ( $fixture['preview_url'] ?? '' ) !== ''
                ? (string) $fixture['preview_url']
                : wpae_block_library_bundled_preview_url( (string) ( $fixture['preview_file'] ?? '' ) ),
            'source_url' => $source_url,
            'provenance' => [
                'source' => 'copyelement',
                'source_url' => $source_url,
            ],
        ];

        $known = is_array( $state[ $fixture_id ] ?? null ) ? $state[ $fixture_id ] : [];
        if ( hash_equals( $expected_hash, strtolower( (string) ( $known['sha256'] ?? '' ) ) ) ) {
            $known_post = wpae_block_library_get_post( absint( $known['post_id'] ?? 0 ) );
            if ( ! is_wp_error( $known_post ) ) {
                $known_record = wpae_block_library_decode_post( $known_post );
                if (
                    ! is_wp_error( $known_record )
                    && (string) ( $known_record['source'] ?? '' ) === 'copyelement'
                ) {
                    if (
                        $fixture_content_hash !== ''
                        && hash_equals( $fixture_content_hash, strtolower( (string) ( $known_record['content_hash'] ?? '' ) ) )
                        && ! wpae_block_library_is_trusted_bundled_fixture( $known_record )
                    ) {
                        $known_record['bundled_fixture_id'] = $fixture_id;
                        $known_record['bundled_fixture_sha256'] = $expected_hash;
                        $known_record['bundled_fixture_content_hash'] = $fixture_content_hash;
                        wpae_block_library_store_record( (int) $known_post->ID, $known_record );
                    } elseif (
                        is_array( $parsed )
                        && $fixture_content_hash !== ''
                        && ! wpae_block_library_is_trusted_bundled_fixture( $known_record )
                    ) {
                        $record = wpae_block_library_build_record( $parsed, $input, $known_record );
                        $record['bundled_fixture_id'] = $fixture_id;
                        $record['bundled_fixture_sha256'] = $expected_hash;
                        $record['bundled_fixture_content_hash'] = $fixture_content_hash;
                        if ( ! is_wp_error( wpae_block_library_store_record( (int) $known_post->ID, $record ) ) ) {
                            $result['migrated'][] = [ 'id' => $fixture_id, 'post_id' => (int) $known_post->ID, 'title' => $record['title'] ];
                        } else {
                            $result['skipped'][] = $fixture_id;
                        }
                    }
                }
                continue;
            }
        }

        if ( ! is_array( $parsed ) || $fixture_content_hash === '' ) {
            $result['skipped'][] = $fixture_id;
            continue;
        }

        $record = wpae_block_library_build_record( $parsed, $input );
        $record['bundled_fixture_id'] = $fixture_id;
        $record['bundled_fixture_sha256'] = $expected_hash;
        $record['bundled_fixture_content_hash'] = $fixture_content_hash;
        $post_id = wp_insert_post( [
            'post_type' => WPAE_BLOCK_LIBRARY_POST_TYPE,
            'post_status' => 'private',
            'post_title' => $record['title'],
            'post_content' => '',
            'post_author' => get_current_user_id(),
        ], true );
        if ( is_wp_error( $post_id ) || is_wp_error( wpae_block_library_store_record( (int) $post_id, $record ) ) ) {
            if ( ! is_wp_error( $post_id ) ) {
                wp_delete_post( $post_id, true );
            }
            $result['skipped'][] = $fixture_id;
            continue;
        }

        $state[ $fixture_id ] = [
            'sha256' => $expected_hash,
            'post_id' => (int) $post_id,
        ];
        $result['imported'][] = [ 'id' => $fixture_id, 'post_id' => (int) $post_id, 'title' => $record['title'] ];
    }

    if ( ! empty( $result['imported'] ) || ! empty( $result['migrated'] ) ) {
        update_option( WPAE_BLOCK_LIBRARY_FIXTURE_OPTION, $state, false );
    }

    return $result;
}

function wpae_block_library_decode_post( WP_Post $post ) {
    $stored_json = get_post_meta( $post->ID, WPAE_BLOCK_LIBRARY_META_KEY, true );
    $record = json_decode( (string) $stored_json, true );
    if ( ! is_array( $record ) || (string) ( $record['schema'] ?? '' ) !== WPAE_BLOCK_LIBRARY_SCHEMA ) {
        return new WP_Error( 'wpae_invalid_stored_block', 'Stored Elementor block has an invalid schema.', [ 'status' => 500, 'id' => $post->ID ] );
    }
    $record['manifest'] = wpae_block_library_normalize_record_manifest( $record );
    $record['status'] = wpae_block_library_status( $record );
    $record['id'] = $post->ID;
    $record['title'] = get_the_title( $post );
    return $record;
}

function wpae_block_library_retrieval_tokens( $value ): array {
    $value = wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    $value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    $tokens = preg_split( '/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY );
    $stop_words = [
        'блок', 'блока', 'добавь', 'добавить', 'сделай', 'создай', 'создать', 'для', 'это', 'как',
        'and', 'the', 'with', 'from', 'make', 'create', 'block', 'section',
    ];
    $result = [];
    foreach ( (array) $tokens as $token ) {
        $token = trim( (string) $token );
        if ( $token === '' || strlen( $token ) < 3 || in_array( $token, $stop_words, true ) ) {
            continue;
        }
        $result[ $token ] = true;
    }
    return array_keys( $result );
}

function wpae_block_library_retrieval_aliases( string $archetype ): array {
    $aliases = [
        'mega_menu' => [ 'mega-menu', 'mega menu', 'мега меню', 'меню', 'навигац', 'header', 'шапк' ],
        'carousel' => [ 'carousel', 'slider', 'карусел', 'слайдер', 'логотип', 'логотипы', 'партнер', 'партнёр', 'бренд' ],
        'hero' => [ 'hero', 'хиро', 'первый', 'экран', 'обложка', 'cover', 'home', 'главн', 'image', 'image-box' ],
        'benefits' => [ 'benefit', 'benefits', 'feature', 'features', 'course', 'courses', 'курс', 'курсы', 'обучен', 'заняти', 'программ', 'преимуществ', 'выгод' ],
        'pricing' => [ 'pricing', 'price', 'тариф', 'цена', 'стоимость', 'пакет' ],
        'testimonials' => [ 'testimonial', 'testimonials', 'отзыв', 'рекомендац', 'клиент' ],
        'team' => [ 'team', 'команд', 'сотрудник', 'специалист', 'коллег', 'about' ],
        'about' => [ 'about', 'company', 'компан', 'о нас', 'о компании' ],
        'faq' => [ 'faq', 'вопрос', 'ответ', 'accordion', 'аккордеон' ],
        'process' => [ 'process', 'step', 'steps', 'event', 'events', 'событи', 'мероприяти', 'мастер-класс', 'процесс', 'этап', 'шаг' ],
        'cta' => [ 'cta', 'contact', 'footer', '404', 'form', 'подвал', 'не найд', 'контакт', 'заявк', 'связ' ],
        'portfolio' => [ 'portfolio', 'case', 'cases', 'project', 'blog', 'article', 'стать', 'блог', 'проекты', 'кейс', 'работ', 'image', 'image-box' ],
    ];
    return $aliases[ $archetype ] ?? [];
}

function wpae_block_library_retrieve_for_prompt( string $message, string $archetype = '' ): array {
    $result = [
        'status' => 'no_match',
        'reason' => 'No approved or trusted bundled library block matched the request.',
        'available_count' => 0,
        'candidate_count' => 0,
        'candidates' => [],
        'selected' => null,
    ];
    if ( function_exists( 'wpae_block_library_seed_bundled_templates' ) ) {
        wpae_block_library_seed_bundled_templates();
    }

    $prompt_tokens = wpae_block_library_retrieval_tokens( $message );
    $aliases = wpae_block_library_retrieval_aliases( sanitize_key( $archetype ) );
    $posts = get_posts( [
        'post_type' => WPAE_BLOCK_LIBRARY_POST_TYPE,
        'post_status' => 'private',
        'posts_per_page' => 100,
        'orderby' => 'modified',
        'order' => 'DESC',
    ] );
    $ranked = [];

    foreach ( $posts as $post ) {
        $record = wpae_block_library_decode_post( $post );
        if ( is_wp_error( $record ) ) {
            continue;
        }
        $record_status = wpae_block_library_status( $record );
        $trusted_bundled = wpae_block_library_is_trusted_bundled_fixture( $record );
        if ( ! $trusted_bundled && ! in_array( $record_status, [ 'approved', 'published' ], true ) ) {
            continue;
        }
        $result['available_count']++;
        $compatibility = is_array( $record['compatibility'] ?? null ) ? $record['compatibility'] : [];
        if ( empty( $compatibility['raw_valid'] ) || empty( $compatibility['normalizable'] ) ) {
            continue;
        }
        $elementor_data = (array) ( $record['elementor_data'] ?? [] );
        if ( function_exists( 'wpae_elementor_normalize_data' ) ) {
            $elementor_data = wpae_elementor_normalize_data( $elementor_data )['data'];
        }
        if ( ! empty( $compatibility['unavailable_widget_types'] ) ) {
            $elementor_data = wpae_block_library_filter_compatible_roots( $elementor_data );
            if ( empty( $elementor_data ) ) {
                continue;
            }
        }

        $category = sanitize_key( (string) ( $record['category'] ?? '' ) );
        $tags = array_map( 'sanitize_key', (array) ( $record['tags'] ?? [] ) );
        $candidate_text = implode( ' ', [
            $category,
            implode( ' ', $tags ),
            (string) ( $record['title'] ?? '' ),
            (string) ( $record['description'] ?? '' ),
        ] );
        $candidate_tokens = wpae_block_library_retrieval_tokens( $candidate_text );
        $score = 0;
        $matched_terms = [];
        foreach ( $aliases as $alias ) {
            $alias_key = sanitize_key( $alias );
            $alias_tokens = wpae_block_library_retrieval_tokens( $alias );
            if ( $alias_key === '' && empty( $alias_tokens ) ) {
                continue;
            }
            if ( ( $alias_key !== '' && ( $category === $alias_key || in_array( $alias_key, $tags, true ) ) ) ) {
                $score += 8;
                $matched_terms[] = $alias_key;
                continue;
            }
            foreach ( $alias_tokens as $alias_token ) {
                if ( in_array( $alias_token, $candidate_tokens, true ) ) {
                    $score += 3;
                    $matched_terms[] = $alias_token;
                    break;
                }
            }
        }
        foreach ( $prompt_tokens as $token ) {
            if ( in_array( $token, $candidate_tokens, true ) ) {
                $score += 2;
                $matched_terms[] = $token;
            }
        }
        if ( in_array( 'bento', $tags, true ) && in_array( sanitize_key( $archetype ), [ 'hero', 'portfolio' ], true ) ) {
            $score++;
            $matched_terms[] = 'bento';
        }
        $matched_terms = array_values( array_unique( $matched_terms ) );
        if ( $score < 5 ) {
            continue;
        }

        $summary = wpae_block_library_summary( $record );
        $ranked[] = [
            'score' => $score,
            'matched_terms' => $matched_terms,
            'summary' => $summary,
            'trusted_bundled' => $trusted_bundled,
            'elementor_data' => $elementor_data,
        ];
    }

    usort( $ranked, static function ( array $left, array $right ): int {
        return (int) $right['score'] <=> (int) $left['score'];
    } );
    $result['candidate_count'] = count( $ranked );
    foreach ( array_slice( $ranked, 0, 3 ) as $candidate ) {
        $result['candidates'][] = [
            'id' => (int) ( $candidate['summary']['id'] ?? 0 ),
            'title' => (string) ( $candidate['summary']['title'] ?? '' ),
            'category' => (string) ( $candidate['summary']['category'] ?? '' ),
            'score' => (int) $candidate['score'],
            'matched_terms' => $candidate['matched_terms'],
            'status' => (string) ( $candidate['summary']['status'] ?? '' ),
        ];
    }
    if ( empty( $ranked ) ) {
        $result['reason'] = $result['available_count'] > 0
            ? 'Approved or trusted bundled library blocks exist, but none match the request or current compatibility contract.'
            : 'The private library has no approved, published, or trusted bundled blocks for retrieval.';
        return $result;
    }

    $selected = $ranked[0];
    $result['status'] = 'matched';
    $result['reason'] = ! empty( $selected['trusted_bundled'] )
        ? 'A trusted bundled library block matched the request.'
        : 'An approved library block matched the request.';
    $result['selected'] = [
        'id' => (int) ( $selected['summary']['id'] ?? 0 ),
        'title' => (string) ( $selected['summary']['title'] ?? '' ),
        'category' => (string) ( $selected['summary']['category'] ?? '' ),
        'source' => (string) ( $selected['summary']['source'] ?? '' ),
        'status' => (string) ( $selected['summary']['status'] ?? '' ),
        'trusted_bundled' => ! empty( $selected['trusted_bundled'] ),
        'score' => (int) $selected['score'],
        'matched_terms' => $selected['matched_terms'],
        'elementor_data' => $selected['elementor_data'],
    ];
    return $result;
}

function wpae_block_library_summary( array $record ): array {
    $manifest = wpae_block_library_normalize_record_manifest( $record );
    return [
        'id' => (int) ( $record['id'] ?? 0 ),
        'title' => (string) ( $record['title'] ?? '' ),
        'description' => (string) ( $record['description'] ?? '' ),
        'category' => (string) ( $record['category'] ?? 'custom' ),
        'tags' => array_values( (array) ( $record['tags'] ?? [] ) ),
        'source_mode' => (string) ( $record['source_mode'] ?? 'native_elementor_json' ),
        'source' => (string) ( $record['source'] ?? 'foreign' ),
        'preview_url' => $record['preview_url'] ?? null,
        'elementor_version' => (string) ( $record['elementor_version'] ?? '' ),
        'design_system_id' => $record['design_system_id'] ?? null,
        'status' => wpae_block_library_status( $record ),
        'manifest' => [
            'schema' => $manifest['schema'],
            'version' => $manifest['version'],
            'source_skill' => $manifest['source_skill'],
            'design_system' => $manifest['design_system'],
            'provenance' => $manifest['provenance'],
            'parent_revision' => $manifest['parent_revision'],
            'quality' => $manifest['quality'],
            'media_dependency_count' => count( (array) $manifest['media_dependencies'] ),
        ],
        'quality_score' => $manifest['quality']['score'] ?? null,
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
            || isset( $json['elements'] )
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
    $bundled = wpae_block_library_seed_bundled_templates();
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
        'bundled' => $bundled,
        'supported_input_formats' => [ 'native_elementor_json', 'elementor_export', WPAE_BLOCK_LIBRARY_SCHEMA ],
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

function wpae_block_library_store_record( int $post_id, array $record ) {
    $record['manifest'] = wpae_block_library_normalize_record_manifest( $record );
    if ( ! wpae_block_library_manifest_metadata_size_ok( $record['manifest'] ) ) {
        return new WP_Error( 'wpae_block_metadata_too_large', 'Elementor block manifest metadata exceeds the 32 KB limit.', [ 'status' => 413 ] );
    }
    $record_json = wp_json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $record_json ) || strlen( $record_json ) > WPAE_BLOCK_LIBRARY_MAX_BYTES ) {
        return new WP_Error( 'wpae_block_too_large', 'Elementor block JSON exceeds the 4 MB limit.', [ 'status' => 413 ] );
    }
    $updated = update_post_meta( $post_id, WPAE_BLOCK_LIBRARY_META_KEY, wp_slash( $record_json ) );
    if ( $updated === false && ! metadata_exists( 'post', $post_id, WPAE_BLOCK_LIBRARY_META_KEY ) ) {
        return new WP_Error( 'wpae_block_store_failed', 'Failed to save Elementor block payload.' );
    }
    return true;
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
    if ( is_wp_error( wpae_block_library_store_record( (int) $post_id, $record ) ) ) {
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

function wpae_block_library_update( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_block_library_get_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $post->get_error_message(), 'code' => $post->get_error_code() ], 404 );
    }
    $record = wpae_block_library_decode_post( $post );
    if ( is_wp_error( $record ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $record->get_error_message(), 'code' => $record->get_error_code() ], 500 );
    }
    $input = $request->get_json_params();
    $input = is_array( $input ) ? $input : $request->get_params();
    $payload = wpae_block_library_request_payload( $request );
    if ( $payload !== null ) {
        $parsed = wpae_block_library_extract_elements( $payload );
        if ( is_wp_error( $parsed ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => $parsed->get_error_message(), 'code' => $parsed->get_error_code(), 'details' => $parsed->get_error_data() ], 422 );
        }
        $record = wpae_block_library_build_record( $parsed, $input, $record );
    } else {
        foreach ( [ 'title', 'description' ] as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $record[ $field ] = $field === 'title'
                    ? sanitize_text_field( (string) $input[ $field ] )
                    : sanitize_textarea_field( (string) $input[ $field ] );
            }
        }
        if ( array_key_exists( 'category', $input ) ) {
            $record['category'] = sanitize_key( (string) $input['category'] ) ?: 'custom';
        }
        if ( array_key_exists( 'tags', $input ) ) {
            $record['tags'] = wpae_block_library_sanitize_tags( $input['tags'] );
        }
        if ( array_key_exists( 'preview_url', $input ) ) {
            $record['preview_url'] = esc_url_raw( (string) $input['preview_url'] ) ?: null;
        }
        $manifest = wpae_block_library_normalize_record_manifest( $record );
        if ( array_key_exists( 'source_skill', $input ) ) {
            $manifest['source_skill'] = wpae_block_library_sanitize_source_skill( $input['source_skill'] );
        }
        if ( array_key_exists( 'provenance', $input ) ) {
            $manifest['provenance'] = wpae_block_library_sanitize_provenance( $input['provenance'], $manifest['provenance'] );
        }
        if ( array_key_exists( 'design_system_version', $input ) ) {
            $manifest['design_system']['version'] = sanitize_text_field( (string) $input['design_system_version'] ) ?: null;
        }
        $record['manifest'] = $manifest;
        $record['updated_at'] = gmdate( 'c' );
    }
    $stored = wpae_block_library_store_record( (int) $post->ID, $record );
    if ( is_wp_error( $stored ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $stored->get_error_message(), 'code' => $stored->get_error_code(), 'details' => $stored->get_error_data() ], 422 );
    }
    wp_update_post( [ 'ID' => $post->ID, 'post_title' => $record['title'] ] );
    $record['id'] = (int) $post->ID;
    return new WP_REST_Response( [ 'ok' => true, 'updated' => true, 'block' => wpae_block_library_summary( $record ) ], 200 );
}

function wpae_block_library_set_status( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_block_library_get_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $post->get_error_message(), 'code' => $post->get_error_code() ], 404 );
    }
    $record = wpae_block_library_decode_post( $post );
    if ( is_wp_error( $record ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $record->get_error_message(), 'code' => $record->get_error_code() ], 500 );
    }
    $input = $request->get_json_params();
    $input = is_array( $input ) ? $input : $request->get_params();
    $target = sanitize_key( (string) ( $input['status'] ?? $request->get_param( 'status' ) ?? '' ) );
    $current = wpae_block_library_status( $record );
    if ( ! in_array( $target, wpae_block_library_statuses(), true ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Unsupported block status.', 'available_statuses' => wpae_block_library_statuses() ], 400 );
    }
    if ( $target === 'published' && $current !== 'approved' && $current !== 'published' ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Only an approved block can be published.', 'code' => 'wpae_publish_requires_approval', 'current_status' => $current ], 409 );
    }
    $review = null;
    $manifest = wpae_block_library_normalize_record_manifest( $record );
    if ( $target === 'approved' && $current !== 'approved' ) {
        $review = wpae_build_elementor_design_review( (array) $record['elementor_data'], [ 'source' => 'block_library_publish', 'post_id' => $post->ID ] );
        if ( ( $review['state'] ?? '' ) !== 'approved' ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Block failed the Design Review Gate.', 'code' => 'wpae_block_review_required', 'review' => $review ], 422 );
        }
        $manifest['quality'] = [
            'score' => min( (int) ( $review['evidence']['visual_audit']['score'] ?? 0 ), (int) ( $review['evidence']['editability_audit']['score'] ?? 0 ) ),
            'review_state' => 'approved',
        ];
    }
    $manifest['status'] = $target;
    if ( $target === 'draft' ) {
        $manifest['quality']['review_state'] = 'draft';
        $manifest['quality']['score'] = null;
    }
    $record['status'] = $target;
    $record['manifest'] = $manifest;
    $record['updated_at'] = gmdate( 'c' );
    $stored = wpae_block_library_store_record( (int) $post->ID, $record );
    if ( is_wp_error( $stored ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $stored->get_error_message(), 'code' => $stored->get_error_code(), 'details' => $stored->get_error_data() ], 422 );
    }
    $record['id'] = (int) $post->ID;
    return new WP_REST_Response( [ 'ok' => true, 'status' => $target, 'review' => $review, 'block' => wpae_block_library_summary( $record ) ], 200 );
}

function wpae_block_library_duplicate( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_block_library_get_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $post->get_error_message(), 'code' => $post->get_error_code() ], 404 );
    }
    $record = wpae_block_library_decode_post( $post );
    if ( is_wp_error( $record ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $record->get_error_message(), 'code' => $record->get_error_code() ], 500 );
    }
    $input = $request->get_json_params();
    $input = is_array( $input ) ? $input : $request->get_params();
    $record['title'] = sanitize_text_field( (string) ( $input['title'] ?? ( $record['title'] . ' copy' ) ) );
    $record['status'] = 'draft';
    $manifest = wpae_block_library_normalize_record_manifest( $record );
    $manifest['status'] = 'draft';
    $manifest['parent_revision'] = (int) $post->ID;
    $manifest['quality'] = [ 'score' => null, 'review_state' => 'draft' ];
    $record['manifest'] = $manifest;
    $record['created_at'] = gmdate( 'c' );
    $record['updated_at'] = $record['created_at'];
    $new_id = wp_insert_post( [
        'post_type' => WPAE_BLOCK_LIBRARY_POST_TYPE,
        'post_status' => 'private',
        'post_title' => $record['title'],
        'post_content' => '',
        'post_author' => get_current_user_id(),
    ], true );
    if ( is_wp_error( $new_id ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $new_id->get_error_message(), 'code' => $new_id->get_error_code() ], 500 );
    }
    $stored = wpae_block_library_store_record( (int) $new_id, $record );
    if ( is_wp_error( $stored ) ) {
        wp_delete_post( $new_id, true );
        return new WP_REST_Response( [ 'ok' => false, 'error' => $stored->get_error_message(), 'code' => $stored->get_error_code() ], 422 );
    }
    $record['id'] = (int) $new_id;
    return new WP_REST_Response( [ 'ok' => true, 'created' => true, 'revision_of' => (int) $post->ID, 'block' => wpae_block_library_summary( $record ) ], 201 );
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

    $status = wpae_block_library_status( $record );
    if ( $status === 'draft' ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Draft Elementor blocks cannot be instantiated.',
            'code' => 'wpae_block_requires_approval',
            'status' => $status,
            'next_step' => 'Run POST /elementor/blocks/{id}/publish with status=approved after the Design Review Gate passes.',
        ], 409 );
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
        'status' => $status,
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
            'status' => 'Статус',
            'draft' => 'Черновик',
            'approved' => 'Одобрен',
            'published' => 'Опубликован',
            'approve' => 'Проверить и одобрить',
            'publish' => 'Опубликовать',
            'duplicate' => 'Создать ревизию',
            'duplicated' => 'Ревизия создана как черновик.',
            'statusChanged' => 'Статус блока обновлён.',
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
