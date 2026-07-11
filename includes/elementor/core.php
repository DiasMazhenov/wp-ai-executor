<?php

defined( 'ABSPATH' ) || exit;

function wpae_get_elementor_data_from_request( WP_REST_Request $request ) {
    $data = $request->get_param( 'elementor_data' );
    if ( $data === null ) {
        $data = $request->get_param( 'data' );
    }

    if ( is_string( $data ) ) {
        $decoded = json_decode( $data, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'wpae_invalid_elementor_json', 'elementor_data must be valid JSON array data.' );
        }
        return $decoded;
    }

    if ( ! is_array( $data ) ) {
        return new WP_Error( 'wpae_missing_elementor_data', 'elementor_data array is required.' );
    }

    return $data;
}

function wpae_get_elementor_data_for_post( int $post_id ) {
    $raw_data = get_post_meta( $post_id, '_elementor_data', true );
    if ( ! is_string( $raw_data ) || trim( $raw_data ) === '' ) {
        return new WP_Error( 'wpae_missing_saved_elementor_data', 'Target post does not have saved _elementor_data.' );
    }

    $decoded = json_decode( $raw_data, true );
    if ( ! is_array( $decoded ) ) {
        return new WP_Error( 'wpae_invalid_saved_elementor_data', 'Saved _elementor_data is not valid JSON array data.', [ 'json_error' => json_last_error_msg() ] );
    }

    return $decoded;
}

function wpae_elementor_protected_zone_kind( array $element ): string {
    $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
    $classes = strtolower( (string) ( $settings['_css_classes'] ?? $settings['css_classes'] ?? '' ) );
    $html = strtolower( (string) ( $settings['html'] ?? '' ) );
    $marker = strpos( $classes, 'wpae-protected-zone' ) !== false || strpos( $html, 'data-wpae-protected' ) !== false;
    $enhancement = (string) ( $element['widgetType'] ?? '' ) === 'html'
        && (bool) preg_match( '/\b(webgl|three(?:\.js)?|gsap|canvas|shader|babylon)\b/i', $html );

    if ( ! $marker && ! $enhancement ) {
        return '';
    }

    return $enhancement ? 'enhancement' : 'marked';
}

function wpae_collect_elementor_protected_zones( array $elements, array &$zones = [], string $path = 'root' ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_path = $path . '.' . $index;
        $element_id = (string) ( $element['id'] ?? '' );
        $kind = wpae_elementor_protected_zone_kind( $element );
        if ( $kind !== '' && $element_id !== '' ) {
            $zones[ $element_id ] = [
                'id' => $element_id,
                'kind' => $kind,
                'path' => $element_path,
                'hash' => hash( 'sha256', (string) wp_json_encode( $element ) ),
            ];
        }

        if ( is_array( $element['elements'] ?? null ) ) {
            wpae_collect_elementor_protected_zones( $element['elements'], $zones, $element_path . '.elements' );
        }
    }

    return $zones;
}

function wpae_validate_elementor_protected_zones( array $existing_data, array $next_data, WP_REST_Request $request ): array {
    $before = wpae_collect_elementor_protected_zones( $existing_data );
    if ( empty( $before ) ) {
        return [ 'ok' => true, 'protected_zones' => [] ];
    }

    $after = wpae_collect_elementor_protected_zones( $next_data );
    $changes = [];
    foreach ( $before as $id => $zone ) {
        if ( ! isset( $after[ $id ] ) ) {
            $changes[] = [ 'element_id' => $id, 'kind' => $zone['kind'], 'change' => 'removed' ];
        } elseif ( ! hash_equals( $zone['hash'], $after[ $id ]['hash'] ) ) {
            $changes[] = [ 'element_id' => $id, 'kind' => $zone['kind'], 'change' => 'modified' ];
        }
    }

    if ( empty( $changes ) ) {
        return [ 'ok' => true, 'protected_zones' => array_values( $before ) ];
    }

    $reason = trim( sanitize_textarea_field( (string) $request->get_param( 'protected_zone_reason' ) ) );
    if ( (bool) $request->get_param( 'allow_protected_zone_changes' ) && strlen( $reason ) >= 10 ) {
        return [
            'ok' => true,
            'override_used' => true,
            'reason' => $reason,
            'changes' => $changes,
            'protected_zones' => array_values( $before ),
        ];
    }

    return [
        'ok' => false,
        'error_code' => 'wpae_protected_zone_change_blocked',
        'message' => 'Protected WebGL/Three.js/GSAP/canvas enhancement zone would be modified or removed.',
        'changes' => $changes,
        'protected_zones' => array_values( $before ),
        'required_override' => [
            'allow_protected_zone_changes' => true,
            'protected_zone_reason' => 'A concrete reason of at least 10 characters.',
        ],
    ];
}

function wpae_is_allowed_elementor_patch_path( string $path ): bool {
    if ( $path === '' || strlen( $path ) > 160 ) {
        return false;
    }

    if ( strpos( $path, '..' ) !== false || preg_match( '/[^A-Za-z0-9_.-]/', $path ) ) {
        return false;
    }

    if ( preg_match( '/(^|\\.)(__|constructor|prototype|GLOBALS|_REQUEST|_POST|_GET|_SERVER)(\\.|$)/i', $path ) ) {
        return false;
    }

    return (bool) preg_match( '/^(settings|elements\\.[0-9]+\\.settings)\\.[A-Za-z0-9_.-]+$/', $path );
}

function wpae_set_array_path_value( array &$target, array $segments, $value ): void {
    $cursor =& $target;
    $last_index = count( $segments ) - 1;
    foreach ( $segments as $index => $segment ) {
        $key = ctype_digit( (string) $segment ) ? (int) $segment : (string) $segment;
        if ( $index === $last_index ) {
            $cursor[ $key ] = $value;
            return;
        }
        if ( ! isset( $cursor[ $key ] ) || ! is_array( $cursor[ $key ] ) ) {
            $cursor[ $key ] = [];
        }
        $cursor =& $cursor[ $key ];
    }
}

function wpae_delete_array_path_value( array &$target, array $segments ): bool {
    $cursor =& $target;
    $last_index = count( $segments ) - 1;
    foreach ( $segments as $index => $segment ) {
        $key = ctype_digit( (string) $segment ) ? (int) $segment : (string) $segment;
        if ( $index === $last_index ) {
            if ( array_key_exists( $key, $cursor ) ) {
                unset( $cursor[ $key ] );
                return true;
            }
            return false;
        }
        if ( ! isset( $cursor[ $key ] ) || ! is_array( $cursor[ $key ] ) ) {
            return false;
        }
        $cursor =& $cursor[ $key ];
    }

    return false;
}

function wpae_apply_elementor_patch_to_element( array &$elements, string $element_id, array $patch, array &$report, string $path = 'root' ): bool {
    foreach ( $elements as $index => &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $current_path = $path . '.' . $index;
        if ( (string) ( $element['id'] ?? '' ) === $element_id ) {
            $property_path = trim( (string) ( $patch['path'] ?? '' ) );
            $op = sanitize_key( (string) ( $patch['op'] ?? 'set' ) );
            if ( ! in_array( $op, [ 'set', 'delete' ], true ) ) {
                $op = 'set';
            }

            if ( ! wpae_is_allowed_elementor_patch_path( $property_path ) ) {
                $report['errors'][] = [
                    'element_id' => $element_id,
                    'path' => $property_path,
                    'message' => 'Patch path is not allowed. Use native Elementor settings paths such as settings.typography_font_size.',
                ];
                return true;
            }

            $segments = explode( '.', $property_path );
            if ( $op === 'delete' ) {
                $changed = wpae_delete_array_path_value( $element, $segments );
            } else {
                wpae_set_array_path_value( $element, $segments, $patch['value'] ?? null );
                $changed = true;
            }

            $report['changes'][] = [
                'element_id' => $element_id,
                'path' => $property_path,
                'op' => $op,
                'changed' => $changed,
                'element_path' => $current_path,
            ];
            return true;
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            if ( wpae_apply_elementor_patch_to_element( $element['elements'], $element_id, $patch, $report, $current_path . '.elements' ) ) {
                return true;
            }
        }
    }
    unset( $element );

    return false;
}

function wpae_apply_elementor_patches( array $elementor_data, array $patches ): array {
    $report = [
        'changes' => [],
        'errors' => [],
        'missing_element_ids' => [],
    ];

    foreach ( array_slice( $patches, 0, 50 ) as $patch ) {
        if ( ! is_array( $patch ) ) {
            $report['errors'][] = [ 'message' => 'Each patch must be an object.' ];
            continue;
        }

        $element_id = sanitize_text_field( (string) ( $patch['element_id'] ?? $patch['id'] ?? '' ) );
        if ( $element_id === '' ) {
            $report['errors'][] = [ 'message' => 'Patch is missing element_id.' ];
            continue;
        }

        $found = wpae_apply_elementor_patch_to_element( $elementor_data, $element_id, $patch, $report );
        if ( ! $found ) {
            $report['missing_element_ids'][] = $element_id;
        }
    }

    return [
        'data' => $elementor_data,
        'report' => $report,
    ];
}

function wpae_validate_elementor_data_array( array $elementor_data ): array {
    return wpae_validate_elementor_data_string( (string) wp_json_encode( $elementor_data ) );
}

function wpae_append_css_classes( $existing, array $classes ): string {
    $current = preg_split( '/\s+/', trim( is_scalar( $existing ) ? (string) $existing : '' ) );
    $current = is_array( $current ) ? array_filter( $current ) : [];

    foreach ( $classes as $class ) {
        $class = sanitize_html_class( (string) $class );
        if ( $class !== '' && ! in_array( $class, $current, true ) ) {
            $current[] = $class;
        }
    }

    return trim( implode( ' ', array_values( array_unique( $current ) ) ) );
}

function wpae_migrate_design_system_css_classes( $existing, array $required_classes ): array {
    $current = preg_split( '/\s+/', trim( is_scalar( $existing ) ? (string) $existing : '' ) );
    $current = is_array( $current ) ? array_values( array_filter( $current ) ) : [];
    $removed = [];
    $kept = [];

    foreach ( $current as $class ) {
        $class = sanitize_html_class( (string) $class );
        if ( $class === '' ) {
            continue;
        }

        if ( strpos( $class, 'wpae-system-' ) === 0 && ! in_array( $class, $required_classes, true ) ) {
            $removed[] = $class;
            continue;
        }

        $kept[] = $class;
    }

    $added = [];
    foreach ( $required_classes as $required_class ) {
        $required_class = sanitize_html_class( (string) $required_class );
        if ( $required_class !== '' && ! in_array( $required_class, $kept, true ) ) {
            $kept[] = $required_class;
            $added[] = $required_class;
        }
    }

    return [
        'classes' => trim( implode( ' ', array_values( array_unique( $kept ) ) ) ),
        'added' => array_values( array_unique( $added ) ),
        'removed' => array_values( array_unique( $removed ) ),
    ];
}

function wpae_elementor_default_id( string $path, array $element ): string {
    return substr( md5( $path . '|' . wp_json_encode( $element ) ), 0, 7 );
}

function wpae_elementor_normalize_add_change( array &$report, string $type, string $path, string $message, array $details = [] ): void {
    if ( ! isset( $report['counts'][ $type ] ) ) {
        $report['counts'][ $type ] = 0;
    }

    $report['counts'][ $type ]++;

    if ( count( $report['changes'] ) >= 200 ) {
        return;
    }

    $report['changes'][] = [
        'type' => $type,
        'path' => $path,
        'message' => $message,
        'details' => $details,
    ];
}

function wpae_elementor_infer_widget_type( array $element, array $settings ): string {
    if ( ! empty( $element['widgetType'] ) ) {
        return sanitize_key( (string) $element['widgetType'] );
    }

    if ( ! empty( $element['widget_type'] ) ) {
        return sanitize_key( (string) $element['widget_type'] );
    }

    if ( isset( $settings['html'] ) ) {
        return 'html';
    }

    if ( isset( $settings['title'] ) ) {
        return 'heading';
    }

    if ( isset( $settings['text'] ) || isset( $settings['link'] ) ) {
        return 'button';
    }

    return 'text-editor';
}

function wpae_elementor_normalize_elements( array $elements, array &$report, string $path = 'root' ): array {
    $normalized = [];

    foreach ( $elements as $index => $element ) {
        $element_path = $path . '.' . $index;

        if ( ! is_array( $element ) ) {
            wpae_elementor_normalize_add_change( $report, 'removed_non_object_element', $element_path, 'Removed non-object Elementor element.' );
            continue;
        }

        if ( empty( $element['id'] ) || ! is_string( $element['id'] ) ) {
            $element['id'] = wpae_elementor_default_id( $element_path, $element );
            wpae_elementor_normalize_add_change( $report, 'filled_missing_id', $element_path, 'Filled missing Elementor element id.', [ 'id' => $element['id'] ] );
        }

        $element_path = $path . '.' . $element['id'];
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
            $element['settings'] = [];
            $settings = [];
            wpae_elementor_normalize_add_change( $report, 'filled_settings', $element_path, 'Filled missing settings array.' );
        }

        $el_type = (string) ( $element['elType'] ?? '' );
        if ( $el_type === 'section' || $el_type === 'column' ) {
            $legacy_el_type = $el_type;
            $element['elType'] = 'container';
            $el_type = 'container';
            wpae_elementor_normalize_add_change( $report, 'converted_legacy_eltype', $element_path, 'Converted legacy Elementor layout node to Flexbox Container.', [ 'from' => $legacy_el_type, 'to' => 'container' ] );
        }

        if ( $el_type === '' ) {
            $el_type = ( isset( $element['widgetType'] ) || isset( $element['widget_type'] ) ) ? 'widget' : 'container';
            $element['elType'] = $el_type;
            wpae_elementor_normalize_add_change( $report, 'inferred_eltype', $element_path, 'Inferred missing elType.', [ 'elType' => $el_type ] );
        }

        if ( $el_type === 'widget' ) {
            if ( array_key_exists( 'widget_type', $element ) ) {
                if ( empty( $element['widgetType'] ) ) {
                    $element['widgetType'] = sanitize_key( (string) $element['widget_type'] );
                    wpae_elementor_normalize_add_change( $report, 'converted_widget_type_key', $element_path, 'Converted widget_type to camelCase widgetType.', [ 'widgetType' => $element['widgetType'] ] );
                }

                unset( $element['widget_type'] );
                wpae_elementor_normalize_add_change( $report, 'removed_widget_type_key', $element_path, 'Removed forbidden snake-case widget_type key.' );
            }

            if ( empty( $element['widgetType'] ) ) {
                $element['widgetType'] = wpae_elementor_infer_widget_type( $element, $settings );
                wpae_elementor_normalize_add_change( $report, 'inferred_widget_type', $element_path, 'Filled missing widgetType with best-effort native widget type.', [ 'widgetType' => $element['widgetType'] ] );
            }

            if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
                $element['elements'] = [];
                wpae_elementor_normalize_add_change( $report, 'filled_elements', $element_path, 'Filled missing widget elements array.' );
            }
        } else {
            $element['elType'] = 'container';

            if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
                $element['elements'] = [];
                wpae_elementor_normalize_add_change( $report, 'filled_elements', $element_path, 'Filled missing container elements array.' );
            }

            if ( $path === 'root' ) {
                $required_classes = array_merge( wpae_get_design_system_required_classes(), [ 'wpae-block' ] );
                $before_classes = (string) ( $element['settings']['_css_classes'] ?? '' );
                $class_migration = wpae_migrate_design_system_css_classes( $before_classes, $required_classes );
                $element['settings']['_css_classes'] = $class_migration['classes'];
                $element['settings']['_wpae_design_system_id'] = wpae_get_design_system_id();

                if ( $element['settings']['_css_classes'] !== $before_classes ) {
                    $change_type = ! empty( $class_migration['removed'] ) ? 'migrated_design_system_marker' : 'filled_design_system_marker';
                    wpae_elementor_normalize_add_change(
                        $report,
                        $change_type,
                        $element_path,
                        ! empty( $class_migration['removed'] )
                            ? 'Migrated top-level container to the current design-system marker classes.'
                            : 'Added required design-system marker classes to top-level container.',
                        [
                            'required_classes' => $required_classes,
                            'added' => $class_migration['added'],
                            'removed' => $class_migration['removed'],
                        ]
                    );
                }
            }

            foreach ( [
                'content_width' => 'boxed',
                'flex_direction' => 'column',
                'background_background' => 'classic',
                'background_color' => '#ffffff',
            ] as $setting_key => $setting_value ) {
                if ( empty( $element['settings'][ $setting_key ] ) ) {
                    $element['settings'][ $setting_key ] = $setting_value;
                    wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container setting.', [ 'setting' => $setting_key, 'value' => $setting_value ] );
                }
            }

            if ( ! isset( $element['settings']['gap'] ) ) {
                $element['settings']['gap'] = [
                    'unit' => 'rem',
                    'size' => 1.5,
                    'sizes' => [],
                ];
                wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container gap.', [ 'setting' => 'gap' ] );
            }

            if ( ! isset( $element['settings']['padding'] ) ) {
                $element['settings']['padding'] = [
                    'unit' => 'rem',
                    'top' => '1.5',
                    'right' => '1.5',
                    'bottom' => '1.5',
                    'left' => '1.5',
                    'isLinked' => true,
                ];
                wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container padding.', [ 'setting' => 'padding' ] );
            }

            $element['elements'] = wpae_elementor_normalize_elements( $element['elements'], $report, $element_path );
        }

        $normalized[] = $element;
    }

    return $normalized;
}

function wpae_elementor_normalize_data( array $elementor_data ): array {
    $report = [
        'counts' => [],
        'changes' => [],
    ];

    $normalized = wpae_elementor_normalize_elements( $elementor_data, $report );
    ksort( $report['counts'] );

    return [
        'data' => $normalized,
        'report' => $report,
    ];
}

function wpae_clear_elementor_cache( int $post_id ): array {
    $report = [
        'post_id' => $post_id,
        'post_css_meta_deleted' => false,
        'global_css_option_deleted' => false,
        'elementor_files_cache_cleared' => null,
        'wp_rocket_domain_cleaned' => null,
        'errors' => [],
    ];

    delete_post_meta( $post_id, '_elementor_css' );
    $report['post_css_meta_deleted'] = get_post_meta( $post_id, '_elementor_css', true ) === '';

    delete_option( '_elementor_global_css' );
    $report['global_css_option_deleted'] = get_option( '_elementor_global_css', null ) === null;

    if ( class_exists( '\Elementor\Plugin' ) ) {
        try {
            $elementor = \Elementor\Plugin::$instance;
            if ( isset( $elementor->files_manager ) && method_exists( $elementor->files_manager, 'clear_cache' ) ) {
                $elementor->files_manager->clear_cache();
                $report['elementor_files_cache_cleared'] = true;
            } else {
                $report['elementor_files_cache_cleared'] = false;
            }
        } catch ( Throwable $e ) {
            $report['elementor_files_cache_cleared'] = false;
            $report['errors'][] = [
                'scope' => 'elementor_files_cache',
                'message' => $e->getMessage(),
            ];
        }
    }

    if ( function_exists( 'rocket_clean_domain' ) ) {
        try {
            rocket_clean_domain();
            $report['wp_rocket_domain_cleaned'] = true;
        } catch ( Throwable $e ) {
            $report['wp_rocket_domain_cleaned'] = false;
            $report['errors'][] = [
                'scope' => 'wp_rocket',
                'message' => $e->getMessage(),
            ];
        }
    }

    $report['ok'] = $report['post_css_meta_deleted'] && empty( $report['errors'] );

    return $report;
}

function wpae_save_elementor_page_data( int $post_id, array $elementor_data, string $template = 'elementor_canvas' ) {
    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_Error( 'wpae_invalid_post_id', 'A valid post_id is required.' );
    }

    $errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $errors ) ) {
        return new WP_Error( 'wpae_invalid_elementor_data', 'Elementor data failed validation.', [ 'errors' => $errors ] );
    }

    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
    update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
    update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
    update_post_meta( $post_id, '_wp_page_template', $template ?: 'elementor_canvas' );
    $cache = wpae_clear_elementor_cache( $post_id );
    if ( empty( $cache['ok'] ) ) {
        return new WP_Error( 'wpae_elementor_cache_clear_failed', 'Elementor cache clearing failed after metadata write.', [ 'cache' => $cache ] );
    }

    return true;
}

function wpae_build_elementor_transaction_status( string $operation, int $post_id, ?array $rollback_snapshot, array $checks = [] ): array {
    return [
        'mode' => 'atomic',
        'operation' => $operation,
        'post_id' => $post_id,
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'checks' => $checks,
        'auto_rollback_on' => [
            'metadata_save_error',
            'invalid_saved_elementor_json',
            'post_save_contract_failure',
            'cache_clear_failure',
            'public_verification_failure_when_requested',
            'strict_quality_failure_when_requested',
        ],
    ];
}

function wpae_verify_saved_elementor_transaction( int $post_id, array $expected_elementor_data, array $preflight, WP_REST_Request $request ): array {
    $checks = [];
    $raw_data = get_post_meta( $post_id, '_elementor_data', true );
    $decoded = is_string( $raw_data ) ? json_decode( $raw_data, true ) : null;
    $saved_valid = is_array( $decoded ) && empty( wpae_validate_elementor_data_array( $decoded ) );

    $checks['saved_elementor_data'] = [
        'ok' => $saved_valid,
        'message' => $saved_valid ? 'Saved _elementor_data decodes as valid Elementor array.' : 'Saved _elementor_data is missing, invalid JSON, or fails Elementor validation.',
        'json_error' => is_string( $raw_data ) && ! is_array( $decoded ) ? json_last_error_msg() : null,
    ];

    $design_contract = is_array( $decoded ) ? wpae_validate_design_system_contract( $decoded ) : [ 'ok' => false ];
    $checks['design_system_contract'] = [
        'ok' => ! empty( $design_contract['ok'] ),
        'message' => ! empty( $design_contract['ok'] ) ? 'Saved data keeps the active design-system contract.' : 'Saved data violates the active design-system contract.',
        'details' => $design_contract,
    ];

    $cache = wpae_clear_elementor_cache( $post_id );
    $checks['cache_refresh'] = [
        'ok' => ! empty( $cache['ok'] ),
        'message' => ! empty( $cache['ok'] ) ? 'Elementor cache refresh completed.' : 'Elementor cache refresh failed.',
        'details' => $cache,
    ];

    $quality_summary = wpae_build_after_save_quality_summary( $post_id, $expected_elementor_data, $preflight );
    $strict_quality = (bool) $request->get_param( 'transaction_strict_quality' );
    $quality_level = (string) ( $quality_summary['visual_audit']['level'] ?? '' );
    $quality_ok = ! $strict_quality || in_array( $quality_level, [ 'strong', 'acceptable' ], true );
    $checks['quality_gate'] = [
        'ok' => $quality_ok,
        'strict' => $strict_quality,
        'message' => $quality_ok ? 'Static quality gate passed for the requested transaction mode.' : 'Strict quality gate failed.',
        'details' => [
            'level' => $quality_level,
            'score' => (int) ( $quality_summary['visual_audit']['score'] ?? 0 ),
        ],
    ];

    $public_verification = (bool) $request->get_param( 'transaction_verify_public' );
    if ( $public_verification ) {
        $public_target = get_permalink( $post_id );
        $public_ok = false;
        $public_details = [ 'url' => $public_target ];
        if ( is_string( $public_target ) && $public_target !== '' && wpae_is_safe_visual_audit_url( $public_target ) ) {
            $response = wp_remote_get( $public_target, [
                'timeout' => 10,
                'redirection' => 3,
                'user-agent' => 'WP AI Executor Transaction Verify/' . WPAE_VERSION,
            ] );
            if ( is_wp_error( $response ) ) {
                $public_details['error'] = $response->get_error_message();
            } else {
                $code = (int) wp_remote_retrieve_response_code( $response );
                $html = (string) wp_remote_retrieve_body( $response );
                $public_audit = wpae_build_public_html_visual_audit( $html, [
                    'source' => 'transaction_verify_public',
                    'url' => $public_target,
                    'post_id' => $post_id,
                ] );
                $public_ok = $code >= 200 && $code < 400 && ! empty( $public_audit['ok'] );
                $public_details['http_status'] = $code;
                $public_details['audit'] = [
                    'ok' => (bool) ( $public_audit['ok'] ?? false ),
                    'score' => (int) ( $public_audit['score'] ?? 0 ),
                    'level' => (string) ( $public_audit['level'] ?? '' ),
                    'recommendations' => (array) ( $public_audit['recommendations'] ?? [] ),
                ];
            }
        } else {
            $public_details['error'] = 'Unsafe or empty permalink for public verification.';
        }

        $checks['public_verification'] = [
            'ok' => $public_ok,
            'requested' => true,
            'message' => $public_ok ? 'Public verification passed.' : 'Public verification failed.',
            'details' => $public_details,
        ];
    }

    $failed = [];
    foreach ( $checks as $code => $check ) {
        if ( empty( $check['ok'] ) ) {
            $failed[] = $code;
        }
    }

    return [
        'ok' => empty( $failed ),
        'checks' => $checks,
        'failed_checks' => $failed,
        'quality_summary' => $quality_summary,
    ];
}

function wpae_finalize_elementor_transaction( string $operation, int $post_id, ?array $rollback_snapshot, array $elementor_data, array $preflight, WP_REST_Request $request ) {
    $transaction = wpae_build_elementor_transaction_status( $operation, $post_id, $rollback_snapshot );
    $verification = wpae_verify_saved_elementor_transaction( $post_id, $elementor_data, $preflight, $request );
    $transaction['checks'] = $verification['checks'];
    $transaction['failed_checks'] = $verification['failed_checks'];
    $transaction['ok'] = ! empty( $verification['ok'] );

    if ( ! $transaction['ok'] ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : [ 'ok' => false, 'error' => 'No rollback snapshot was available.' ];
        $transaction['auto_rollback'] = $rollback;

        return new WP_Error(
            'wpae_elementor_transaction_failed',
            'Elementor transaction failed after write and was rolled back when possible.',
            [
                'transaction' => $transaction,
                'quality_summary' => $verification['quality_summary'],
            ]
        );
    }

    return [
        'transaction' => $transaction,
        'quality_summary' => $verification['quality_summary'],
    ];
}

function wpae_elementor_validate( WP_REST_Request $request ): WP_REST_Response {
    $elementor_data = wpae_get_elementor_data_from_request( $request );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    $errors = wpae_validate_elementor_data_array( $elementor_data );

    return new WP_REST_Response( [
        'ok' => empty( $errors ),
        'errors' => $errors,
    ], empty( $errors ) ? 200 : 422 );
}

function wpae_elementor_normalize( WP_REST_Request $request ): WP_REST_Response {
    $elementor_data = wpae_get_elementor_data_from_request( $request );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    $before_errors = wpae_validate_elementor_data_array( $elementor_data );
    $normalized = wpae_elementor_normalize_data( $elementor_data );
    $normalized_data = $normalized['data'];
    $after_errors = wpae_validate_elementor_data_array( $normalized_data );
    $stats = wpae_default_elementor_audit_stats();
    wpae_collect_elementor_audit_stats( $normalized_data, $stats );
    wpae_collect_elementor_design_quality_stats( $normalized_data, $stats );
    wpae_finalize_elementor_audit_stats( $stats );

    return new WP_REST_Response( [
        'ok' => empty( $after_errors ),
        'changed' => ! empty( $normalized['report']['changes'] ),
        'change_counts' => $normalized['report']['counts'],
        'changes' => $normalized['report']['changes'],
        'before_errors' => $before_errors,
        'after_errors' => $after_errors,
        'stats' => $stats,
        'normalized_elementor_data' => $normalized_data,
    ], empty( $after_errors ) ? 200 : 422 );
}

function wpae_get_typography_unlock_keys(): array {
    return [
        'typography_typography',
        'typography_font_family',
        'typography_font_size',
        'typography_font_size_tablet',
        'typography_font_size_mobile',
        'typography_font_weight',
        'typography_text_transform',
        'typography_font_style',
        'typography_text_decoration',
        'typography_line_height',
        'typography_line_height_tablet',
        'typography_line_height_mobile',
        'typography_letter_spacing',
        'typography_letter_spacing_tablet',
        'typography_letter_spacing_mobile',
        'typography_word_spacing',
        'typography_word_spacing_tablet',
        'typography_word_spacing_mobile',
    ];
}

function wpae_clean_typography_globals( array &$settings, array $keys ): array {
    $removed = [];
    if ( empty( $settings['__globals__'] ) || ! is_array( $settings['__globals__'] ) ) {
        return $removed;
    }

    foreach ( $keys as $key ) {
        if ( array_key_exists( $key, $settings['__globals__'] ) ) {
            $removed[] = '__globals__.' . $key;
            unset( $settings['__globals__'][ $key ] );
        }
    }

    if ( empty( $settings['__globals__'] ) ) {
        unset( $settings['__globals__'] );
    }

    return $removed;
}

function wpae_elementor_typography_unlock_elements( array $elements, array $options, array &$report, string $path = 'root' ): array {
    $keys = (array) ( $options['keys'] ?? wpae_get_typography_unlock_keys() );
    $widget_types = (array) ( $options['widget_types'] ?? [ 'heading', 'text-editor', 'button', 'icon-list' ] );
    $preserve_ids = array_fill_keys( (array) ( $options['preserve_ids'] ?? [] ), true );
    $preserve_classes = (array) ( $options['preserve_classes'] ?? [] );

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_id = (string) ( $element['id'] ?? $index );
        $element_path = $path . '.' . $element_id;
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $is_target_widget = ( (string) ( $element['elType'] ?? '' ) === 'widget' && in_array( $widget_type, $widget_types, true ) );
        $is_preserved = isset( $preserve_ids[ $element_id ] );

        if ( ! $is_preserved && ! empty( $preserve_classes ) ) {
            $class_string = (string) ( $settings['_css_classes'] ?? '' );
            foreach ( $preserve_classes as $preserve_class ) {
                if ( $preserve_class !== '' && preg_match( '/(^|\s)' . preg_quote( (string) $preserve_class, '/' ) . '(\s|$)/', $class_string ) ) {
                    $is_preserved = true;
                    break;
                }
            }
        }

        if ( $is_target_widget && ! $is_preserved ) {
            $removed_keys = [];
            foreach ( $keys as $key ) {
                if ( array_key_exists( $key, $settings ) ) {
                    unset( $settings[ $key ] );
                    $removed_keys[] = $key;
                }
            }
            $removed_keys = array_merge( $removed_keys, wpae_clean_typography_globals( $settings, $keys ) );

            if ( ! empty( $removed_keys ) ) {
                $report['changed'] = true;
                $report['widgets_changed']++;
                foreach ( $removed_keys as $removed_key ) {
                    $report['removed_settings'][ $removed_key ] = ( $report['removed_settings'][ $removed_key ] ?? 0 ) + 1;
                }
                if ( count( $report['changes'] ) < 200 ) {
                    $report['changes'][] = [
                        'path' => $element_path,
                        'id' => $element_id,
                        'widgetType' => $widget_type,
                        'removed' => $removed_keys,
                    ];
                }
            }
        }

        $element['settings'] = $settings;
        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_elementor_typography_unlock_elements( $element['elements'], $options, $report, $element_path );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_typography_unlock( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
    $elementor_data = json_decode( $raw_data, true );
    if ( ! is_array( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is not valid JSON array data.', 'post_id' => $post_id ], 422 );
    }

    $preserve_ids = $request->get_param( 'preserve_ids' );
    $preserve_classes = $request->get_param( 'preserve_classes' );
    $widget_types = $request->get_param( 'widget_types' );
    $mode = sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'typography' ) );
    $keys = $mode === 'font_family'
        ? [ 'typography_font_family' ]
        : wpae_get_typography_unlock_keys();

    $report = [
        'changed' => false,
        'widgets_changed' => 0,
        'removed_settings' => [],
        'changes' => [],
        'mode' => $mode,
    ];
    $updated_data = wpae_elementor_typography_unlock_elements( $elementor_data, [
        'keys' => $keys,
        'preserve_ids' => is_array( $preserve_ids ) ? array_map( 'sanitize_key', $preserve_ids ) : [],
        'preserve_classes' => is_array( $preserve_classes ) ? array_map( 'sanitize_html_class', $preserve_classes ) : [],
        'widget_types' => is_array( $widget_types ) ? array_map( 'sanitize_key', $widget_types ) : [ 'heading', 'text-editor', 'button', 'icon-list' ],
    ], $report );

    $validation_errors = wpae_validate_elementor_data_array( $updated_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Typography unlock produced invalid Elementor data.',
            'details' => [ 'errors' => $validation_errors ],
            'report' => $report,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'report' => $report,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_typography_unlock:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $updated_data, $template );
    if ( is_wp_error( $saved ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'report' => $report,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'report' => $report,
        'quality_summary' => wpae_build_after_save_quality_summary( $post_id, $updated_data, [] ),
    ], 200 );
}

function wpae_sanitize_typography_override_patches( $raw_patches ): array {
    if ( ! is_array( $raw_patches ) ) {
        return [];
    }

    $allowed_keys = [
        'typography_typography',
        'typography_font_family',
        'typography_font_weight',
        'typography_font_style',
        'typography_text_transform',
        'typography_text_decoration',
        'typography_letter_spacing',
        'typography_word_spacing',
        'typography_line_height',
        'typography_font_size',
        'typography_font_size_tablet',
        'typography_font_size_mobile',
    ];
    $patches = [];

    foreach ( $raw_patches as $element_id => $settings ) {
        $element_id = sanitize_key( (string) $element_id );
        if ( $element_id === '' || ! is_array( $settings ) ) {
            continue;
        }

        foreach ( $settings as $key => $value ) {
            if ( ! in_array( $key, $allowed_keys, true ) || ! is_scalar( $value ) ) {
                continue;
            }

            $patches[ $element_id ][ $key ] = sanitize_text_field( (string) $value );
        }
    }

    return $patches;
}

function wpae_relax_heading_typography_important( string $content, array &$report ): string {
    $result = preg_replace_callback( '/([^{}]+)\{([^{}]*)\}/s', function ( array $match ) use ( &$report ): string {
        $selector = $match[1];
        if ( stripos( $selector, '.elementor-heading-title' ) === false ) {
            return $match[0];
        }

        $changed = false;
        $declarations = preg_replace_callback(
            '/\b(font-family|font-size|font-weight|font-style|line-height|letter-spacing|word-spacing|text-transform|text-decoration)\s*:\s*([^;{}]*?)\s*!important(\s*;?)/i',
            function ( array $declaration ) use ( &$report, &$changed ): string {
                $changed = true;
                $report['html_typography_important_removed']++;
                return $declaration[1] . ': ' . trim( $declaration[2] ) . $declaration[3];
            },
            $match[2]
        );

        if ( $changed ) {
            $report['html_rules_relaxed']++;
        }

        return $selector . '{' . $declarations . '}';
    }, $content );

    return is_string( $result ) ? $result : $content;
}

function wpae_resolve_heading_typography_overrides( array $elements, array $patches, array &$report, string $path = 'root' ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_id = sanitize_key( (string) ( $element['id'] ?? $index ) );
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );

        if ( $widget_type === 'heading' && isset( $patches[ $element_id ] ) ) {
            $changed_keys = [];
            foreach ( $patches[ $element_id ] as $key => $value ) {
                if ( ! array_key_exists( $key, $settings ) || $settings[ $key ] !== $value ) {
                    $settings[ $key ] = $value;
                    $changed_keys[] = $key;
                }
            }

            if ( isset( $settings['typography_font_family'] ) && empty( $settings['typography_typography'] ) ) {
                $settings['typography_typography'] = 'custom';
                $changed_keys[] = 'typography_typography';
            }

            $report['patched_ids'][ $element_id ] = true;
            if ( ! empty( $changed_keys ) ) {
                $report['native_widgets_changed']++;
                $report['native_changes'][] = [
                    'path' => $path . '.' . $element_id,
                    'id' => $element_id,
                    'changed' => $changed_keys,
                ];
            }
        }

        if ( $widget_type === 'html' ) {
            foreach ( [ 'html', 'content', 'code' ] as $content_key ) {
                if ( ! is_string( $settings[ $content_key ] ?? null ) || $settings[ $content_key ] === '' ) {
                    continue;
                }

                $before = $settings[ $content_key ];
                $settings[ $content_key ] = wpae_relax_heading_typography_important( $before, $report );
                if ( $settings[ $content_key ] !== $before ) {
                    $report['html_widgets_changed']++;
                    $report['html_widget_ids'][] = $element_id;
                }
                break;
            }
        }

        $element['settings'] = $settings;
        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_resolve_heading_typography_overrides( $element['elements'], $patches, $report, $path . '.' . $element_id );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_resolve_typography_overrides( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $patches = wpae_sanitize_typography_override_patches( $request->get_param( 'native_typography_patches' ) );

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
    $elementor_data = json_decode( $raw_data, true );
    if ( ! is_array( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is not valid JSON array data.', 'post_id' => $post_id ], 422 );
    }

    $report = [
        'native_widgets_changed' => 0,
        'native_changes' => [],
        'patched_ids' => [],
        'html_widgets_changed' => 0,
        'html_widget_ids' => [],
        'html_rules_relaxed' => 0,
        'html_typography_important_removed' => 0,
    ];
    $updated_data = wpae_resolve_heading_typography_overrides( $elementor_data, $patches, $report );
    $report['patched_ids'] = array_values( array_keys( $report['patched_ids'] ) );
    $report['html_widget_ids'] = array_values( array_unique( $report['html_widget_ids'] ) );
    $report['missing_patch_ids'] = array_values( array_diff( array_keys( $patches ), $report['patched_ids'] ) );
    $report['changed'] = $report['native_widgets_changed'] > 0 || $report['html_widgets_changed'] > 0;

    $validation_errors = wpae_validate_elementor_data_array( $updated_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Typography override migration produced invalid Elementor data.',
            'details' => [ 'errors' => $validation_errors ],
            'report' => $report,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'report' => $report,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_resolve_typography_overrides:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $updated_data, $template );
    if ( is_wp_error( $saved ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'report' => $report,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'report' => $report,
        'quality_summary' => wpae_build_after_save_quality_summary( $post_id, $updated_data, [] ),
    ], 200 );
}

function wpae_el_spacing( int $top, int $right, int $bottom, int $left ): array {
    return [
        'unit' => 'px',
        'top' => (string) $top,
        'right' => (string) $right,
        'bottom' => (string) $bottom,
        'left' => (string) $left,
        'isLinked' => false,
    ];
}

function wpae_el_gap( int $size ): array {
    return [
        'unit' => 'px',
        'size' => $size,
        'sizes' => [],
    ];
}

function wpae_el_container( string $id, array $settings = [], array $elements = [] ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => array_merge( [
            'content_width' => 'boxed',
            'flex_direction' => 'column',
            'background_background' => 'classic',
            'background_color' => '#ffffff',
            'gap' => wpae_el_gap( 24 ),
            'gap_mobile' => wpae_el_gap( 16 ),
            'padding' => wpae_el_spacing( 48, 32, 48, 32 ),
            'padding_mobile' => wpae_el_spacing( 32, 18, 32, 18 ),
            'flex_direction_mobile' => 'column',
        ], $settings ),
        'elements' => $elements,
    ];
}

function wpae_el_widget( string $id, string $widget_type, array $settings = [] ): array {
    return [
        'id' => $id,
        'elType' => 'widget',
        'widgetType' => $widget_type,
        'settings' => $settings,
        'elements' => [],
    ];
}

function wpae_elementor_recipe_definitions(): array {
    return [
        'hero.editorial' => [
            'id' => 'hero.editorial',
            'type' => 'section',
            'title' => 'Editorial service hero',
            'description' => 'A strong first screen with offer, proof line, CTA row, and a metric rail.',
            'variants' => [ 'minimal', 'split-proof', 'metric-led' ],
            'default_variant' => 'split-proof',
            'slots' => [
                'eyebrow' => [ 'required' => false, 'default' => 'Website build lab', 'max_chars' => 42 ],
                'headline' => [ 'required' => true, 'default' => 'A page that explains the offer before visitors leave', 'max_chars' => 110 ],
                'subheadline' => [ 'required' => true, 'default' => 'Editable Elementor structure with clear offer, proof, process, and action.', 'max_chars' => 180 ],
                'cta_primary' => [ 'required' => true, 'default' => 'Discuss the page', 'max_chars' => 32 ],
                'cta_secondary' => [ 'required' => false, 'default' => 'View process', 'max_chars' => 32 ],
                'metric_1' => [ 'required' => false, 'default' => '7-14 days' ],
                'metric_1_label' => [ 'required' => false, 'default' => 'typical launch window' ],
                'metric_2' => [ 'required' => false, 'default' => '0 files' ],
                'metric_2_label' => [ 'required' => false, 'default' => 'external scratch files' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'heroa01', [
                    'background_color' => '#f6f0e6',
                    'gap' => wpae_el_gap( 32 ),
                    'padding' => wpae_el_spacing( 72, 40, 72, 40 ),
                ], [
                    wpae_el_widget( 'heroa02', 'heading', [ 'title' => '{{eyebrow}}', 'header_size' => 'h6', 'title_color' => '#c75b3b' ] ),
                    wpae_el_container( 'heroa03', [
                        'flex_direction' => 'row',
                        'gap' => wpae_el_gap( 40 ),
                        'background_color' => '#f6f0e6',
                        'padding' => wpae_el_spacing( 0, 0, 0, 0 ),
                    ], [
                        wpae_el_container( 'heroa04', [ 'background_color' => '#f6f0e6', 'padding' => wpae_el_spacing( 0, 0, 0, 0 ) ], [
                            wpae_el_widget( 'heroa05', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h1', 'title_color' => '#111827' ] ),
                            wpae_el_widget( 'heroa06', 'text-editor', [ 'editor' => '{{subheadline}}', 'text_color' => '#374151' ] ),
                            wpae_el_container( 'heroa07', [ 'flex_direction' => 'row', 'background_color' => '#f6f0e6', 'padding' => wpae_el_spacing( 0, 0, 0, 0 ) ], [
                                wpae_el_widget( 'heroa08', 'button', [ 'text' => '{{cta_primary}}', 'button_type' => 'primary', 'background_color' => '#111827', 'text_color' => '#ffffff' ] ),
                                wpae_el_widget( 'heroa09', 'button', [ 'text' => '{{cta_secondary}}', 'button_type' => 'secondary', 'background_color' => '#ffffff', 'text_color' => '#111827' ] ),
                            ] ),
                        ] ),
                        wpae_el_container( 'heroa10', [ 'background_color' => '#111827', 'padding' => wpae_el_spacing( 32, 32, 32, 32 ) ], [
                            wpae_el_widget( 'heroa11', 'heading', [ 'title' => '{{metric_1}}', 'header_size' => 'h2', 'title_color' => '#ffffff' ] ),
                            wpae_el_widget( 'heroa12', 'text-editor', [ 'editor' => '{{metric_1_label}}', 'text_color' => '#d1d5db' ] ),
                            wpae_el_widget( 'heroa13', 'divider', [] ),
                            wpae_el_widget( 'heroa14', 'heading', [ 'title' => '{{metric_2}}', 'header_size' => 'h2', 'title_color' => '#ffffff' ] ),
                            wpae_el_widget( 'heroa15', 'text-editor', [ 'editor' => '{{metric_2_label}}', 'text_color' => '#d1d5db' ] ),
                        ] ),
                    ] ),
                ] ),
            ],
        ],
        'feature.grid' => [
            'id' => 'feature.grid',
            'type' => 'section',
            'title' => 'Feature grid',
            'description' => 'Three native editable feature cards with a compact intro.',
            'variants' => [ 'three-cards', 'dense', 'proof-led' ],
            'default_variant' => 'three-cards',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'What the page must make obvious' ],
                'intro' => [ 'required' => false, 'default' => 'Each block has a job: explain, prove, reduce friction, or move to action.' ],
                'item_1_title' => [ 'required' => true, 'default' => 'Clear offer' ],
                'item_1_text' => [ 'required' => true, 'default' => 'Visitors understand who it is for and what result they get.' ],
                'item_2_title' => [ 'required' => true, 'default' => 'Trust structure' ],
                'item_2_text' => [ 'required' => true, 'default' => 'Proof, process, and specifics replace vague promises.' ],
                'item_3_title' => [ 'required' => true, 'default' => 'Editable system' ],
                'item_3_text' => [ 'required' => true, 'default' => 'Built from native Elementor containers and widgets.' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'feat001', [ 'background_color' => '#ffffff' ], [
                    wpae_el_widget( 'feat002', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2', 'title_color' => '#111827' ] ),
                    wpae_el_widget( 'feat003', 'text-editor', [ 'editor' => '{{intro}}', 'text_color' => '#4b5563' ] ),
                    wpae_el_container( 'feat004', [ 'flex_direction' => 'row', 'background_color' => '#ffffff', 'padding' => wpae_el_spacing( 0, 0, 0, 0 ) ], [
                        wpae_el_container( 'feat005', [ 'background_color' => '#f3f4f6' ], [ wpae_el_widget( 'feat006', 'heading', [ 'title' => '{{item_1_title}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'feat007', 'text-editor', [ 'editor' => '{{item_1_text}}' ] ) ] ),
                        wpae_el_container( 'feat008', [ 'background_color' => '#eef2ff' ], [ wpae_el_widget( 'feat009', 'heading', [ 'title' => '{{item_2_title}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'feat010', 'text-editor', [ 'editor' => '{{item_2_text}}' ] ) ] ),
                        wpae_el_container( 'feat011', [ 'background_color' => '#ecfdf5' ], [ wpae_el_widget( 'feat012', 'heading', [ 'title' => '{{item_3_title}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'feat013', 'text-editor', [ 'editor' => '{{item_3_text}}' ] ) ] ),
                    ] ),
                ] ),
            ],
        ],
        'process.steps' => [
            'id' => 'process.steps',
            'type' => 'section',
            'title' => 'Process steps',
            'description' => 'Numbered process with four editable steps.',
            'variants' => [ 'linear', 'split', 'timeline' ],
            'default_variant' => 'linear',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'How the work moves' ],
                'step_1' => [ 'required' => true, 'default' => 'Brief and page job' ],
                'step_2' => [ 'required' => true, 'default' => 'Structure and copy' ],
                'step_3' => [ 'required' => true, 'default' => 'Elementor build' ],
                'step_4' => [ 'required' => true, 'default' => 'Audit and launch' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'proc001', [ 'background_color' => '#111827' ], [
                    wpae_el_widget( 'proc002', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2', 'title_color' => '#ffffff' ] ),
                    wpae_el_widget( 'proc003', 'icon-list', [
                        'icon_list' => [
                            [ 'text' => '01 / {{step_1}}' ],
                            [ 'text' => '02 / {{step_2}}' ],
                            [ 'text' => '03 / {{step_3}}' ],
                            [ 'text' => '04 / {{step_4}}' ],
                        ],
                        'text_color' => '#ffffff',
                    ] ),
                ] ),
            ],
        ],
        'pricing.comparison' => [
            'id' => 'pricing.comparison',
            'type' => 'section',
            'title' => 'Pricing comparison',
            'description' => 'Two or three package cards with native buttons.',
            'variants' => [ 'two-packages', 'three-packages' ],
            'default_variant' => 'two-packages',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'Choose the right build depth' ],
                'package_1' => [ 'required' => true, 'default' => 'Landing page' ],
                'package_1_price' => [ 'required' => false, 'default' => 'from 350k KZT' ],
                'package_2' => [ 'required' => true, 'default' => 'Service page system' ],
                'package_2_price' => [ 'required' => false, 'default' => 'from 650k KZT' ],
                'cta' => [ 'required' => true, 'default' => 'Request estimate' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'price01', [ 'background_color' => '#f9fafb' ], [
                    wpae_el_widget( 'price02', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2' ] ),
                    wpae_el_container( 'price03', [ 'flex_direction' => 'row', 'background_color' => '#f9fafb', 'padding' => wpae_el_spacing( 0, 0, 0, 0 ) ], [
                        wpae_el_container( 'price04', [ 'background_color' => '#ffffff' ], [ wpae_el_widget( 'price05', 'heading', [ 'title' => '{{package_1}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'price06', 'heading', [ 'title' => '{{package_1_price}}', 'header_size' => 'h2' ] ), wpae_el_widget( 'price07', 'button', [ 'text' => '{{cta}}' ] ) ] ),
                        wpae_el_container( 'price08', [ 'background_color' => '#111827' ], [ wpae_el_widget( 'price09', 'heading', [ 'title' => '{{package_2}}', 'header_size' => 'h3', 'title_color' => '#ffffff' ] ), wpae_el_widget( 'price10', 'heading', [ 'title' => '{{package_2_price}}', 'header_size' => 'h2', 'title_color' => '#ffffff' ] ), wpae_el_widget( 'price11', 'button', [ 'text' => '{{cta}}', 'background_color' => '#ffffff', 'text_color' => '#111827' ] ) ] ),
                    ] ),
                ] ),
            ],
        ],
        'faq.accordion' => [
            'id' => 'faq.accordion',
            'type' => 'section',
            'title' => 'FAQ accordion',
            'description' => 'Native Elementor accordion with editable questions.',
            'variants' => [ 'simple', 'compact' ],
            'default_variant' => 'simple',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'Questions before the start' ],
                'q1' => [ 'required' => true, 'default' => 'Can I edit the page later?' ],
                'a1' => [ 'required' => true, 'default' => 'Yes. Content is placed in native Elementor widgets.' ],
                'q2' => [ 'required' => true, 'default' => 'Will it use external files?' ],
                'a2' => [ 'required' => true, 'default' => 'No. The build uses WordPress metadata and native Elementor settings.' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'faq0001', [ 'background_color' => '#ffffff' ], [
                    wpae_el_widget( 'faq0002', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2' ] ),
                    wpae_el_widget( 'faq0003', 'accordion', [
                        'tabs' => [
                            [ 'tab_title' => '{{q1}}', 'tab_content' => '{{a1}}' ],
                            [ 'tab_title' => '{{q2}}', 'tab_content' => '{{a2}}' ],
                        ],
                    ] ),
                ] ),
            ],
        ],
        'cta.band' => [
            'id' => 'cta.band',
            'type' => 'section',
            'title' => 'CTA band',
            'description' => 'Focused action band with heading, copy, and button.',
            'variants' => [ 'dark', 'light', 'accent' ],
            'default_variant' => 'dark',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'Ready to make the page clear?' ],
                'text' => [ 'required' => true, 'default' => 'Send the brief and get a practical structure before development starts.' ],
                'cta' => [ 'required' => true, 'default' => 'Start with a brief' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'cta0001', [ 'background_color' => '#111827' ], [
                    wpae_el_widget( 'cta0002', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2', 'title_color' => '#ffffff' ] ),
                    wpae_el_widget( 'cta0003', 'text-editor', [ 'editor' => '{{text}}', 'text_color' => '#d1d5db' ] ),
                    wpae_el_widget( 'cta0004', 'button', [ 'text' => '{{cta}}', 'background_color' => '#ffffff', 'text_color' => '#111827' ] ),
                ] ),
            ],
        ],
        'proof.timeline' => [
            'id' => 'proof.timeline',
            'type' => 'section',
            'title' => 'Proof timeline',
            'description' => 'Trust-building sequence for proof, examples, and result.',
            'variants' => [ 'three-points', 'case-led' ],
            'default_variant' => 'three-points',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'Proof before promises' ],
                'proof_1' => [ 'required' => true, 'default' => 'Process is visible before design starts.' ],
                'proof_2' => [ 'required' => true, 'default' => 'Copy explains the offer, not just the brand.' ],
                'proof_3' => [ 'required' => true, 'default' => 'The final page remains editable in Elementor.' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'proof01', [ 'background_color' => '#f6f0e6' ], [
                    wpae_el_widget( 'proof02', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2' ] ),
                    wpae_el_widget( 'proof03', 'icon-list', [
                        'icon_list' => [
                            [ 'text' => '{{proof_1}}' ],
                            [ 'text' => '{{proof_2}}' ],
                            [ 'text' => '{{proof_3}}' ],
                        ],
                    ] ),
                ] ),
            ],
        ],
        'contact.block' => [
            'id' => 'contact.block',
            'type' => 'section',
            'title' => 'Contact block',
            'description' => 'Simple contact section with direct action and context.',
            'variants' => [ 'direct', 'split' ],
            'default_variant' => 'direct',
            'slots' => [
                'headline' => [ 'required' => true, 'default' => 'Tell me what page you need' ],
                'text' => [ 'required' => true, 'default' => 'Send the service, audience, and desired action. I will turn it into a page plan.' ],
                'cta' => [ 'required' => true, 'default' => 'Send request' ],
            ],
            'elementor_data' => [
                wpae_el_container( 'cont001', [ 'background_color' => '#ffffff' ], [
                    wpae_el_widget( 'cont002', 'heading', [ 'title' => '{{headline}}', 'header_size' => 'h2' ] ),
                    wpae_el_widget( 'cont003', 'text-editor', [ 'editor' => '{{text}}' ] ),
                    wpae_el_widget( 'cont004', 'button', [ 'text' => '{{cta}}' ] ),
                ] ),
            ],
        ],
    ];
}

function wpae_elementor_recipe_summary( array $recipe ): array {
    return [
        'id' => $recipe['id'],
        'type' => $recipe['type'],
        'title' => $recipe['title'],
        'description' => $recipe['description'],
        'variants' => $recipe['variants'],
        'default_variant' => $recipe['default_variant'],
        'slots' => $recipe['slots'],
    ];
}

function wpae_sanitize_elementor_recipe_id( string $id ): string {
    $id = strtolower( trim( $id ) );
    $id = str_replace( '_', '.', $id );
    return preg_replace( '/[^a-z0-9.-]/', '', $id );
}

function wpae_elementor_recipes(): WP_REST_Response {
    $recipes = array_map( 'wpae_elementor_recipe_summary', array_values( wpae_elementor_recipe_definitions() ) );

    return new WP_REST_Response( [
        'ok' => true,
        'usage' => [
            'blueprint' => 'POST /wp-json/ai-executor/v1/elementor/blueprint',
            'list' => 'GET /wp-json/ai-executor/v1/elementor/recipes',
            'get_one' => 'GET /wp-json/ai-executor/v1/elementor/recipes/{id}',
            'compose' => 'POST /wp-json/ai-executor/v1/elementor/compose',
            'next_steps' => [ '/elementor/normalize', '/elementor/validate', '/elementor/page' ],
        ],
        'composition_policy' => [
            'layout' => 'Native Elementor Flexbox Containers only.',
            'content' => 'Native editable widgets only.',
            'html_widget' => 'Enhancement-only CSS/JS zone; never main layout.',
        ],
        'primitives' => [
            'container.stack',
            'container.grid',
            'container.split',
            'widget.heading',
            'widget.copy',
            'widget.cta-row',
            'widget.metric',
            'widget.feature-item',
            'widget.html-enhancement',
        ],
        'recipes' => $recipes,
    ], 200 );
}

function wpae_elementor_recipe( WP_REST_Request $request ): WP_REST_Response {
    $id = wpae_sanitize_elementor_recipe_id( (string) $request['id'] );
    $recipes = wpae_elementor_recipe_definitions();

    if ( ! isset( $recipes[ $id ] ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Recipe not found.', 'available' => array_keys( $recipes ) ], 404 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'recipe' => $recipes[ $id ],
        'next_steps' => [ 'POST /elementor/compose', 'POST /elementor/normalize', 'POST /elementor/validate' ],
    ], 200 );
}

function wpae_blueprint_text_param( WP_REST_Request $request, string $key, string $default = '', int $max_length = 180 ): string {
    $value = trim( sanitize_text_field( (string) $request->get_param( $key ) ) );
    if ( $value === '' ) {
        $value = $default;
    }

    return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
}

function wpae_blueprint_list_param( WP_REST_Request $request, string $key, array $fallback = [] ): array {
    $value = $request->get_param( $key );
    if ( is_string( $value ) ) {
        $value = preg_split( '/[,;\n]+/', $value );
    }

    if ( ! is_array( $value ) ) {
        return $fallback;
    }

    $items = [];
    foreach ( $value as $item ) {
        if ( ! is_scalar( $item ) ) {
            continue;
        }

        $item = trim( sanitize_text_field( (string) $item ) );
        if ( $item !== '' ) {
            $items[] = function_exists( 'mb_substr' ) ? mb_substr( $item, 0, 90 ) : substr( $item, 0, 90 );
        }
    }

    return ! empty( $items ) ? array_values( array_unique( $items ) ) : $fallback;
}

function wpae_blueprint_palette_for_style( string $style ): array {
    $style = strtolower( $style );
    if ( preg_match( '/premium|editorial|lux|minimal|clean|эксперт|преми/i', $style ) ) {
        return [
            'ink' => '#111827',
            'paper' => '#f6f0e6',
            'surface' => '#ffffff',
            'accent' => '#c75b3b',
            'muted' => '#6b7280',
            'line' => '#d8cfc2',
        ];
    }

    if ( preg_match( '/tech|saas|software|digital|тех|софт|айти/i', $style ) ) {
        return [
            'ink' => '#101828',
            'paper' => '#f8fafc',
            'surface' => '#ffffff',
            'accent' => '#2563eb',
            'support' => '#14b8a6',
            'muted' => '#667085',
        ];
    }

    return [
        'ink' => '#111827',
        'paper' => '#f7f2ea',
        'surface' => '#ffffff',
        'accent' => '#d97706',
        'support' => '#2563eb',
        'muted' => '#4b5563',
    ];
}

function wpae_elementor_design_system( WP_REST_Request $request ): WP_REST_Response {
    $subject = wpae_blueprint_text_param( $request, 'subject', 'страница сайта', 140 );
    $style = wpae_blueprint_text_param( $request, 'style', 'project default', 120 );
    $language = wpae_blueprint_text_param( $request, 'language', 'ru', 24 );
    $design_system = wpae_build_project_design_system( [
        'subject' => $subject,
        'style' => $style,
        'language' => $language,
    ] );

    $design_system['input'] = [
        'subject' => $subject,
        'style' => $style,
        'language' => $language,
    ];
    $design_system['next_steps'] = [
        'POST /elementor/blueprint with the same subject/style and this system_id.',
        'Build every page/block from native Flexbox Containers and widgets.',
        'Put required_root_classes into settings._css_classes on every top-level container.',
        'Run /elementor/normalize if a container is missing the marker.',
        'Run /elementor/visual-audit before /elementor/page or /elementor/update.',
    ];

    return new WP_REST_Response( [
        'ok' => true,
        'writes' => false,
        'design_system' => $design_system,
    ], 200 );
}

function wpae_elementor_blueprint( WP_REST_Request $request ): WP_REST_Response {
    $subject = wpae_blueprint_text_param( $request, 'subject', 'страница услуги', 140 );
    $audience = wpae_blueprint_text_param( $request, 'audience', 'клиенты, которым нужно быстро понять предложение', 180 );
    $goal = wpae_blueprint_text_param( $request, 'goal', 'получить заявку', 140 );
    $offer = wpae_blueprint_text_param( $request, 'offer', $subject, 180 );
    $language = wpae_blueprint_text_param( $request, 'language', 'ru', 24 );
    $style = wpae_blueprint_text_param( $request, 'style', 'editorial premium service page', 120 );
    $tone = wpae_blueprint_text_param( $request, 'tone', 'конкретный, уверенный, без лишней рекламности', 140 );
    $proof_points = wpae_blueprint_list_param( $request, 'proof_points', [ 'сроки', 'понятный процесс', 'редактируемая Elementor-структура' ] );
    $constraints = wpae_blueprint_list_param( $request, 'constraints', [ 'native Elementor Flexbox Containers only', 'no external files', 'HTML widget only for scoped CSS/JS enhancements' ] );
    $project_tokens = wpae_get_project_design_tokens();
    $design_system = wpae_build_project_design_system( [
        'subject' => $subject,
        'style' => $style,
        'language' => $language,
    ] );
    $palette = ! empty( $project_tokens['palette'] ) ? $project_tokens['palette'] : wpae_blueprint_palette_for_style( $style );
    $primary_cta = wpae_blueprint_text_param( $request, 'primary_cta', 'Обсудить проект', 80 );
    $secondary_cta = wpae_blueprint_text_param( $request, 'secondary_cta', 'Посмотреть процесс', 80 );
    $recipes = wpae_elementor_recipe_definitions();

    $sections = [
        [
            'id' => 'hero',
            'recipe_id' => 'hero.editorial',
            'variant' => 'split-proof',
            'job' => 'Immediately state the offer, audience fit, proof line, and primary action.',
            'native_widgets' => [ 'heading', 'text-editor', 'button' ],
            'slots' => [
                'eyebrow' => $subject,
                'headline' => $offer,
                'subheadline' => 'Для ' . $audience . ': ' . $goal . '.',
                'cta_primary' => $primary_cta,
                'cta_secondary' => $secondary_cta,
                'metric_1' => '7-14',
                'metric_1_label' => 'дней до запуска типовой страницы',
                'metric_2' => '0',
                'metric_2_label' => 'внешних файлов на сервере',
            ],
        ],
        [
            'id' => 'trust',
            'recipe_id' => 'feature.grid',
            'variant' => 'three-cards',
            'job' => 'Turn proof points into concrete reasons to trust the offer.',
            'native_widgets' => [ 'heading', 'text-editor', 'icon-list' ],
            'content_roles' => $proof_points,
        ],
        [
            'id' => 'process',
            'recipe_id' => 'process.steps',
            'variant' => 'linear',
            'job' => 'Show the path from request to launch without hiding complexity.',
            'native_widgets' => [ 'heading', 'text-editor' ],
            'steps' => [ 'бриф и структура', 'Elementor-сборка', 'проверка и правки', 'публикация' ],
        ],
        [
            'id' => 'offer',
            'recipe_id' => 'pricing.comparison',
            'variant' => 'two-packages',
            'job' => 'Explain what is included, what is not included, and the expected result.',
            'native_widgets' => [ 'heading', 'text-editor', 'button' ],
        ],
        [
            'id' => 'faq',
            'recipe_id' => 'faq.accordion',
            'variant' => 'compact',
            'job' => 'Remove objections before the final CTA.',
            'native_widgets' => [ 'heading', 'accordion' ],
        ],
        [
            'id' => 'final_cta',
            'recipe_id' => 'cta.band',
            'variant' => 'dark',
            'job' => 'Give one clear next step.',
            'native_widgets' => [ 'heading', 'text-editor', 'button' ],
            'slots' => [
                'headline' => 'Готовы обсудить страницу?',
                'subheadline' => 'Коротко разберем задачу, аудиторию и нужный результат.',
                'cta_primary' => $primary_cta,
            ],
        ],
    ];

    $available_recipes = array_keys( $recipes );
    foreach ( $sections as &$section ) {
        $section['recipe_available'] = in_array( $section['recipe_id'], $available_recipes, true );
        $section['fallback_if_recipe_not_enough'] = 'Build from native container primitives and widgets, then run /elementor/normalize and /elementor/validate.';
    }
    unset( $section );

    return new WP_REST_Response( [
        'ok' => true,
        'writes' => false,
        'blueprint_version' => 'v01.01.00',
        'design_system_required' => true,
        'design_system' => $design_system,
        'input' => [
            'subject' => $subject,
            'audience' => $audience,
            'goal' => $goal,
            'offer' => $offer,
            'language' => $language,
            'style' => $style,
            'tone' => $tone,
            'proof_points' => $proof_points,
            'constraints' => $constraints,
        ],
        'design_tokens' => [
            'palette' => $palette,
            'typography_roles' => $project_tokens['typography_roles'] ?? [],
            'spacing_scale' => $project_tokens['spacing_scale'] ?? [],
            'radii' => $project_tokens['radii'] ?? [],
            'button_style' => $project_tokens['button_style'] ?? '',
            'tone_of_voice' => $project_tokens['tone_of_voice'] ?? '',
            'design_prohibitions' => $project_tokens['design_prohibitions'] ?? [],
        ],
        'sections' => $sections,
        'html_enhancement_zones' => [
            [
                'zone' => 'global_page_enhancement',
                'allowed' => [ 'Google Fonts loader', 'scoped CSS polish', 'small vanilla JS reveal/counter interactions' ],
                'forbidden' => [ 'main page markup', 'layout wrappers', 'content hidden inside HTML widget', 'external files' ],
            ],
        ],
        'elementor_contract' => [
            'design_system' => 'Call /elementor/design-system before building. Every top-level page/block container must include required_root_classes in settings._css_classes.',
            'required_root_classes' => $design_system['required_root_classes'],
            'layout' => 'Use elType=container only; never section/column.',
            'widgets' => 'Every widget must use camelCase widgetType.',
            'native_settings_first' => [ 'background_color', 'text colors', 'padding', 'gap', 'border', 'width', 'responsive settings' ],
            'next_endpoints' => [ 'GET /elementor/recipes', 'POST /elementor/compose', 'POST /elementor/normalize', 'POST /elementor/validate', 'POST /elementor/page' ],
        ],
        'design_quality_gates' => [
            'typography_hierarchy' => 'At least H1 plus meaningful H2/H3 sections.',
            'spacing_consistency' => 'Native padding and gap on containers.',
            'cta_visibility' => 'Native button CTA in hero and final CTA.',
            'mobile_readiness' => 'Responsive Elementor settings for split/grid sections.',
            'palette_quality' => '3-8 intentional colors from the design tokens.',
            'content_completeness' => 'No empty heading/text widgets or placeholder filler.',
        ],
        'agent_workflow' => [
            '1. Call /elementor/design-system first and keep its system_id.',
            '2. Use this blueprint as the page/block contract.',
            '3. Compose available recipes with project-specific slots.',
            '4. For custom sections, build native container/widget primitives using the same design system.',
            '5. Put required_root_classes on every top-level page/block container.',
            '6. Normalize, validate, and visual-audit before writing.',
            '7. Save with /elementor/page or /elementor/update using guide-token headers.',
            '8. Verify with /audit and fix weak/blocked agent_conformance criteria.',
        ],
    ], 200 );
}

function wpae_replace_placeholders_recursive( $value, array $slots ) {
    if ( is_string( $value ) ) {
        foreach ( $slots as $slot => $slot_value ) {
            $value = str_replace( '{{' . $slot . '}}', (string) $slot_value, $value );
        }
        return $value;
    }

    if ( is_array( $value ) ) {
        foreach ( $value as $key => $child ) {
            $value[ $key ] = wpae_replace_placeholders_recursive( $child, $slots );
        }
    }

    return $value;
}

function wpae_rekey_elementor_ids_recursive( array $elements, string $instance_id ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $old_id = (string) ( $element['id'] ?? $index );
        $element['id'] = substr( md5( $instance_id . '|' . $old_id . '|' . $index ), 0, 7 );

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_rekey_elementor_ids_recursive( $element['elements'], $instance_id . '|' . $old_id );
        }

        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_compose( WP_REST_Request $request ): WP_REST_Response {
    $recipe_id = wpae_sanitize_elementor_recipe_id( (string) ( $request->get_param( 'recipe_id' ) ?: $request->get_param( 'id' ) ) );
    $variant = sanitize_key( (string) $request->get_param( 'variant' ) );
    $input_slots = $request->get_param( 'slots' );
    $input_slots = is_array( $input_slots ) ? $input_slots : [];
    $recipes = wpae_elementor_recipe_definitions();

    if ( ! isset( $recipes[ $recipe_id ] ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Recipe not found.', 'available' => array_keys( $recipes ) ], 404 );
    }

    $recipe = $recipes[ $recipe_id ];
    if ( $variant === '' ) {
        $variant = (string) $recipe['default_variant'];
    }

    if ( ! in_array( $variant, $recipe['variants'], true ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Variant is not available for this recipe.', 'available_variants' => $recipe['variants'] ], 400 );
    }

    $slots = [];
    $missing_required = [];
    foreach ( $recipe['slots'] as $slot => $schema ) {
        if ( array_key_exists( $slot, $input_slots ) && (string) $input_slots[ $slot ] !== '' ) {
            $slots[ $slot ] = is_scalar( $input_slots[ $slot ] ) ? sanitize_text_field( (string) $input_slots[ $slot ] ) : wp_json_encode( $input_slots[ $slot ] );
        } else {
            if ( ! empty( $schema['required'] ) ) {
                $missing_required[] = $slot;
            }
            $slots[ $slot ] = (string) ( $schema['default'] ?? '' );
        }
    }

    $elementor_data = wpae_replace_placeholders_recursive( $recipe['elementor_data'], $slots );
    $instance_id = sanitize_key( (string) ( $request->get_param( 'instance_id' ) ?: $recipe_id . '-' . $variant . '-' . substr( md5( wp_json_encode( $slots ) ), 0, 8 ) ) );
    $elementor_data = wpae_rekey_elementor_ids_recursive( $elementor_data, $instance_id );
    $normalized = wpae_elementor_normalize_data( $elementor_data );
    $elementor_data = $normalized['data'];
    $errors = wpae_validate_elementor_data_array( $elementor_data );
    $stats = wpae_default_elementor_audit_stats();
    wpae_collect_elementor_audit_stats( $elementor_data, $stats );
    wpae_collect_elementor_design_quality_stats( $elementor_data, $stats );
    wpae_finalize_elementor_audit_stats( $stats );

    $ok = empty( $errors ) && empty( $missing_required );

    return new WP_REST_Response( [
        'ok' => $ok,
        'recipe_id' => $recipe_id,
        'variant' => $variant,
        'instance_id' => $instance_id,
        'missing_required_slots' => $missing_required,
        'slots_used' => $slots,
        'normalization' => [
            'change_counts' => $normalized['report']['counts'],
            'changes' => $normalized['report']['changes'],
        ],
        'errors' => $errors,
        'stats' => $stats,
        'elementor_data' => $elementor_data,
        'next_steps' => [ 'POST /elementor/normalize', 'POST /elementor/validate', 'POST /elementor/page' ],
    ], $ok ? 200 : 422 );
}

function wpae_elementor_visual_audit( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $has_payload = $request->get_param( 'elementor_data' ) !== null;
    $context = [
        'source' => $has_payload ? 'request.elementor_data' : 'post_meta',
    ];

    if ( $has_payload ) {
        $elementor_data = wpae_get_elementor_data_from_request( $request );
        if ( is_wp_error( $elementor_data ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
        }
    } else {
        if ( $post_id <= 0 ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'post_id or elementor_data is required.' ], 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Post not found.' ], 404 );
        }

        $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
        if ( $raw_data === '' ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is empty.', 'post_id' => $post_id ], 422 );
        }

        $elementor_data = json_decode( $raw_data, true );
        if ( ! is_array( $elementor_data ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is not valid JSON array data.', 'post_id' => $post_id ], 422 );
        }

        $context['post_id'] = $post_id;
        $context['post_status'] = get_post_status( $post_id );
        $context['url'] = get_permalink( $post_id );
    }

    $audit = wpae_build_elementor_visual_audit( $elementor_data, $context );
    $status = $audit['level'] === 'blocked' ? 422 : 200;

    return new WP_REST_Response( $audit, $status );
}

function wpae_visual_audit_page( WP_REST_Request $request ): WP_REST_Response {
    $target = wpae_resolve_public_visual_audit_target( $request );
    if ( is_wp_error( $target ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $target->get_error_message(),
            'code' => $target->get_error_code(),
        ], 400 );
    }

    $response = wp_safe_remote_get( $target['url'], [
        'timeout' => 15,
        'redirection' => 3,
        'limit_response_size' => 4 * 1024 * 1024,
        'reject_unsafe_urls' => true,
        'user-agent' => 'WP AI Executor Visual Audit/' . WPAE_VERSION,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Failed to fetch public page.',
            'message' => $response->get_error_message(),
            'target' => $target,
        ], 502 );
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $html = (string) wp_remote_retrieve_body( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    if ( is_array( $content_type ) ) {
        $content_type = implode( ', ', $content_type );
    }

    $audit = wpae_build_public_html_visual_audit( $html, [
        'source' => 'public_html',
        'status_code' => $status_code,
        'target' => $target,
        'content_type' => (string) $content_type,
    ] );

    $response_status = $status_code >= 200 && $status_code < 400
        ? ( $audit['level'] === 'blocked' ? 422 : 200 )
        : 502;

    return new WP_REST_Response( $audit, $response_status );
}

function wpae_resolve_public_visual_audit_target( WP_REST_Request $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $url = trim( (string) $request->get_param( 'url' ) );

    if ( $post_id > 0 ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', 'Post not found.' );
        }

        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return new WP_Error( 'missing_permalink', 'Post permalink is unavailable.' );
        }

        if ( ! wpae_is_safe_visual_audit_url( $permalink ) ) {
            return new WP_Error( 'unsafe_target', 'The page URL resolves to a private, reserved, or unsafe network address.' );
        }

        return [
            'type' => 'post',
            'post_id' => $post_id,
            'post_status' => get_post_status( $post_id ),
            'url' => $permalink,
        ];
    }

    if ( $url === '' ) {
        return new WP_Error( 'missing_target', 'post_id or url is required.' );
    }

    if ( strpos( $url, '/' ) === 0 ) {
        $url = home_url( $url );
    }

    $url = esc_url_raw( $url );
    $parts = wp_parse_url( $url );
    $home_parts = wp_parse_url( home_url() );
    if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true ) ) {
        return new WP_Error( 'invalid_url', 'URL must be a valid http or https URL.' );
    }

    if ( ! is_array( $home_parts ) || strtolower( (string) ( $parts['host'] ?? '' ) ) !== strtolower( (string) ( $home_parts['host'] ?? '' ) ) ) {
        return new WP_Error( 'external_url_forbidden', 'Only same-site URLs can be audited.' );
    }

    if ( ! wpae_is_safe_visual_audit_url( $url ) ) {
        return new WP_Error( 'unsafe_target', 'The page URL resolves to a private, reserved, or unsafe network address.' );
    }

    return [
        'type' => 'url',
        'url' => $url,
    ];
}

function wpae_is_safe_visual_audit_url( string $url ): bool {
    $parts = wp_parse_url( $url );
    $home_parts = wp_parse_url( home_url() );

    if ( ! is_array( $parts ) || ! is_array( $home_parts ) ) {
        return false;
    }

    if ( ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) ) {
        return false;
    }

    if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['port'] ) ) {
        return false;
    }

    $host = strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) );
    $home_host = strtolower( rtrim( (string) ( $home_parts['host'] ?? '' ), '.' ) );
    if ( $host === '' || $host !== $home_host ) {
        return false;
    }

    if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
        return false;
    }

    $ips = [];
    if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
        $ips[] = $host;
    } else {
        $ips = array_merge( $ips, (array) gethostbynamel( $host ) );
        if ( function_exists( 'dns_get_record' ) ) {
            $dns_types = 0;
            if ( defined( 'DNS_A' ) ) {
                $dns_types |= DNS_A;
            }
            if ( defined( 'DNS_AAAA' ) ) {
                $dns_types |= DNS_AAAA;
            }

            if ( $dns_types !== 0 ) {
                $records = dns_get_record( $host, $dns_types );
                foreach ( (array) $records as $record ) {
                    if ( ! empty( $record['ip'] ) ) {
                        $ips[] = $record['ip'];
                    }
                    if ( ! empty( $record['ipv6'] ) ) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }
    }

    $ips = array_values( array_unique( array_filter( array_map( 'strval', $ips ) ) ) );
    if ( empty( $ips ) ) {
        return false;
    }

    foreach ( $ips as $ip ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
            return false;
        }
    }

    return true;
}

function wpae_count_regex_matches( string $pattern, string $subject ): int {
    $matches = [];
    return preg_match_all( $pattern, $subject, $matches ) ?: 0;
}

function wpae_public_html_text_length( string $html ): int {
    $html = preg_replace( '#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $html ) ) );
    return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
}

function wpae_count_wide_fixed_width_risks( string $html ): int {
    $matches = [];
    preg_match_all( '/(?:^|[;"\s])(width|min-width)\s*:\s*(\d{3,5})px/i', $html, $matches, PREG_SET_ORDER );
    $risks = 0;
    foreach ( $matches as $match ) {
        if ( (int) ( $match[2] ?? 0 ) > 430 ) {
            $risks++;
        }
    }
    return $risks;
}

function wpae_build_public_html_visual_audit( string $html, array $context = [] ): array {
    $checks = [];
    $status_code = (int) ( $context['status_code'] ?? 0 );
    $html_bytes = strlen( $html );
    $text_length = wpae_public_html_text_length( $html );
    $has_viewport = (bool) preg_match( '/<meta[^>]+name=["\']viewport["\']/i', $html );
    $has_title = (bool) preg_match( '/<title\b[^>]*>[^<]{3,}<\/title>/i', $html );
    $has_elementor = stripos( $html, 'elementor' ) !== false;
    $has_cta = (bool) preg_match( '/<(a|button)\b[^>]*(?:href=|type=|class=)[^>]*>[^<]{2,}<\/\1>/i', $html );
    $wide_width_risks = wpae_count_wide_fixed_width_risks( $html );
    $overflow_hidden_count = wpae_count_regex_matches( '/overflow-x\s*:\s*hidden/i', $html );
    $invisible_text_risks = wpae_count_regex_matches( '/(?:color\s*:\s*transparent|rgba\([^)]*,\s*0\s*\)|opacity\s*:\s*0\b|visibility\s*:\s*hidden\b)/i', $html );
    $empty_block_risks = wpae_count_regex_matches( '/<(section|div|article|main|header|footer)\b[^>]*(?:elementor|wpae)[^>]*>\s*<\/\1>/i', $html );
    $desktop_only_media = wpae_count_regex_matches( '/@media\s*\([^)]*min-width\s*:\s*(?:7\d\d|8\d\d|9\d\d|1\d{3,})px/i', $html );
    $mobile_media = wpae_count_regex_matches( '/@media\s*\([^)]*max-width\s*:\s*(?:7\d\d|6\d\d|5\d\d|4\d\d|3\d\d)px/i', $html );

    wpae_visual_audit_add_check(
        $checks,
        'public_fetch',
        $status_code >= 200 && $status_code < 400 && $html_bytes > 0 ? 'pass' : 'fail',
        $status_code >= 200 && $status_code < 400 && $html_bytes > 0 ? 14 : 0,
        14,
        $status_code >= 200 && $status_code < 400 && $html_bytes > 0 ? 'Public page HTML was fetched successfully.' : 'Public page HTML could not be fetched successfully.',
        [ 'status_code' => $status_code, 'html_bytes' => $html_bytes ],
        'Check that the page is published and reachable without WP Admin authentication.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'html_structure',
        $has_viewport && $has_title && $text_length >= 300 ? 'pass' : 'warn',
        $has_viewport && $has_title && $text_length >= 300 ? 14 : 7,
        14,
        $has_viewport && $has_title && $text_length >= 300 ? 'Basic public HTML structure is present.' : 'Public HTML structure or visible copy looks weak.',
        [
            'has_viewport_meta' => $has_viewport,
            'has_title' => $has_title,
            'visible_text_length' => $text_length,
            'contains_elementor_markup' => $has_elementor,
        ],
        'Ensure the public page has viewport meta, a title, and enough visible page copy.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'horizontal_overflow_risk',
        $wide_width_risks === 0 ? 'pass' : 'warn',
        $wide_width_risks === 0 ? 14 : 6,
        14,
        $wide_width_risks === 0 ? 'No obvious fixed-width mobile overflow risks were found in public HTML/CSS.' : 'Fixed width/min-width styles may cause mobile horizontal overflow.',
        [
            'wide_fixed_width_risks' => $wide_width_risks,
            'overflow_x_hidden_count' => $overflow_hidden_count,
        ],
        'Replace large fixed width/min-width values with %, max-width, flex-basis, rem, or responsive Elementor settings.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'invisible_text_risk',
        $invisible_text_risks <= 8 ? 'pass' : 'warn',
        $invisible_text_risks <= 8 ? 12 : 5,
        12,
        $invisible_text_risks <= 8 ? 'No excessive invisible-text patterns were detected.' : 'Public HTML contains many invisible-text patterns.',
        [ 'invisible_text_pattern_count' => $invisible_text_risks ],
        'Inspect hidden/transparent text and ensure important headings, body copy, and CTAs are visible.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'empty_block_risk',
        $empty_block_risks === 0 ? 'pass' : 'warn',
        $empty_block_risks === 0 ? 10 : 4,
        10,
        $empty_block_risks === 0 ? 'No obvious empty Elementor/WPAE blocks were found in public HTML.' : 'Public HTML contains suspicious empty Elementor/WPAE blocks.',
        [ 'empty_block_risk_count' => $empty_block_risks ],
        'Remove empty blocks or populate them with native Elementor content/settings.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'cta_presence',
        $has_cta ? 'pass' : 'warn',
        $has_cta ? 10 : 4,
        10,
        $has_cta ? 'A public link/button CTA is detectable.' : 'No clear public link/button CTA was detected.',
        [ 'has_cta' => $has_cta ],
        'Add a visible native button/link CTA and verify it is tappable on mobile.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'mobile_first_css_signal',
        $mobile_media >= $desktop_only_media || $mobile_media > 0 ? 'pass' : 'warn',
        $mobile_media >= $desktop_only_media || $mobile_media > 0 ? 10 : 5,
        10,
        $mobile_media >= $desktop_only_media || $mobile_media > 0 ? 'Mobile responsive CSS signals are present.' : 'Mobile responsive CSS signals are weak in public HTML.',
        [
            'max_width_media_queries' => $mobile_media,
            'large_min_width_media_queries' => $desktop_only_media,
        ],
        'Design mobile first and add explicit mobile Elementor responsive settings before desktop polish.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'server_side_limitations',
        'warn',
        0,
        0,
        'Server-side audit does not create screenshots or compute full CSS cascade/contrast.',
        [
            'unsupported' => [
                'desktop/mobile screenshot metrics',
                'true rendered overflow',
                'computed contrast after CSS cascade',
                'animation timing',
            ],
        ],
        'Use browser/public-page verification after REST writes for screenshots, clickable state, rendered contrast, and real overflow.'
    );

    $points = 0;
    $max = 0;
    $has_failures = false;
    $recommendations = [];
    foreach ( $checks as $check ) {
        $points += (int) ( $check['points'] ?? 0 );
        $max += (int) ( $check['max'] ?? 0 );
        if ( ( $check['status'] ?? '' ) === 'fail' ) {
            $has_failures = true;
        }
        if ( ( $check['status'] ?? '' ) !== 'pass' && ! empty( $check['recommendation'] ) ) {
            $recommendations[] = $check['recommendation'];
        }
    }

    $score = $max > 0 ? (int) round( ( $points / $max ) * 100 ) : 100;
    $level = $has_failures ? 'blocked' : ( $score >= 90 ? 'strong' : ( $score >= 75 ? 'acceptable' : ( $score >= 50 ? 'weak' : 'blocked' ) ) );

    return [
        'ok' => ! $has_failures,
        'visual_audit_version' => 'v01.03.00',
        'audit_type' => 'public_html',
        'score' => $score,
        'level' => $level,
        'points' => $points,
        'max_points' => $max,
        'context' => $context,
        'stats' => [
            'html_bytes' => $html_bytes,
            'visible_text_length' => $text_length,
            'has_viewport_meta' => $has_viewport,
            'has_title' => $has_title,
            'contains_elementor_markup' => $has_elementor,
            'wide_fixed_width_risks' => $wide_width_risks,
            'invisible_text_risks' => $invisible_text_risks,
            'empty_block_risks' => $empty_block_risks,
            'has_cta' => $has_cta,
            'max_width_media_queries' => $mobile_media,
            'large_min_width_media_queries' => $desktop_only_media,
        ],
        'checks' => $checks,
        'recommendations' => array_values( array_unique( $recommendations ) ),
        'contract' => [
            'scope' => 'Read-only public HTML audit for same-site pages.',
            'use_after' => 'Use after /elementor/page or /elementor/update writes, alongside /elementor/visual-audit and /audit.',
            'no_browser_claim' => 'This endpoint intentionally avoids server-side screenshots because typical WordPress hosting is not a reliable browser-rendering environment.',
        ],
    ];
}

function wpae_elementor_update( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $elementor_data = wpae_get_elementor_data_from_request( $request );

    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    $existing_data = wpae_get_elementor_data_for_post( $post_id );
    if ( is_wp_error( $existing_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $existing_data->get_error_message(),
            'details' => $existing_data->get_error_data(),
        ], 422 );
    }

    $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $elementor_data, $request );
    if ( ! $protected_zone_guard['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $protected_zone_guard['message'],
            'code' => $protected_zone_guard['error_code'],
            'protected_zone_guard' => $protected_zone_guard,
        ], 422 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
        ], 422 );
    }

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    if ( ! $design_system_contract['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed design-system contract.',
            'details' => $design_system_contract,
        ], 422 );
    }

    $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
        'post_id' => $post_id,
        'template' => $template,
        'operation' => 'update',
    ] );
    if ( ! $preflight['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor preflight failed.',
            'preflight' => $preflight,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'would_write' => [
                '_elementor_data',
                '_elementor_edit_mode',
                '_elementor_template_type',
                '_elementor_version',
                '_wp_page_template',
            ],
            'preflight' => $preflight,
            'protected_zone_guard' => $protected_zone_guard,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_update:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : null;
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'transaction' => wpae_build_elementor_transaction_status( 'elementor_update', $post_id, $rollback_snapshot, [
                'metadata_save' => [
                    'ok' => false,
                    'message' => $saved->get_error_message(),
                    'details' => $saved->get_error_data(),
                ],
            ] ),
            'auto_rollback' => $rollback,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    $finalized = wpae_finalize_elementor_transaction( 'elementor_update', $post_id, $rollback_snapshot, $elementor_data, $preflight, $request );
    if ( is_wp_error( $finalized ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $finalized->get_error_message(),
            'details' => $finalized->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'preflight' => $preflight,
        'protected_zone_guard' => $protected_zone_guard,
        'transaction' => $finalized['transaction'],
        'quality_summary' => $finalized['quality_summary'],
    ], 200 );
}

function wpae_elementor_patch( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $patches = $request->get_param( 'patches' );
    if ( is_array( $patches ) && ( isset( $patches['element_id'] ) || isset( $patches['id'] ) ) ) {
        $patches = [ $patches ];
    }
    if ( ! is_array( $patches ) ) {
        $single_patch = $request->get_param( 'patch' );
        $patches = is_array( $single_patch ) ? [ $single_patch ] : [];
    }

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    if ( empty( $patches ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'patches array is required.' ], 400 );
    }

    $existing_data = wpae_get_elementor_data_for_post( $post_id );
    if ( is_wp_error( $existing_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $existing_data->get_error_message(),
            'details' => $existing_data->get_error_data(),
        ], 422 );
    }

    $patched = wpae_apply_elementor_patches( $existing_data, $patches );
    $elementor_data = $patched['data'];
    $patch_report = $patched['report'];

    if ( ! empty( $patch_report['errors'] ) || ! empty( $patch_report['missing_element_ids'] ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor patch failed before validation.',
            'patch_report' => $patch_report,
        ], 422 );
    }

    $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $elementor_data, $request );
    if ( ! $protected_zone_guard['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $protected_zone_guard['message'],
            'code' => $protected_zone_guard['error_code'],
            'protected_zone_guard' => $protected_zone_guard,
            'patch_report' => $patch_report,
        ], 422 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Patched Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
            'patch_report' => $patch_report,
        ], 422 );
    }

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    if ( ! $design_system_contract['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Patched Elementor data failed design-system contract.',
            'details' => $design_system_contract,
            'patch_report' => $patch_report,
        ], 422 );
    }

    $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
        'post_id' => $post_id,
        'template' => $template,
        'operation' => 'patch',
        'patch_count' => count( $patch_report['changes'] ),
    ] );
    if ( ! $preflight['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Patched Elementor data failed preflight.',
            'preflight' => $preflight,
            'patch_report' => $patch_report,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'patch_report' => $patch_report,
            'preflight' => $preflight,
            'protected_zone_guard' => $protected_zone_guard,
            'elementor_data' => $elementor_data,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_patch:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : null;
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'post_id' => $post_id,
            'patch_report' => $patch_report,
            'transaction' => wpae_build_elementor_transaction_status( 'elementor_patch', $post_id, $rollback_snapshot, [
                'metadata_save' => [
                    'ok' => false,
                    'message' => $saved->get_error_message(),
                    'details' => $saved->get_error_data(),
                ],
            ] ),
            'auto_rollback' => $rollback,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    $finalized = wpae_finalize_elementor_transaction( 'elementor_patch', $post_id, $rollback_snapshot, $elementor_data, $preflight, $request );
    if ( is_wp_error( $finalized ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $finalized->get_error_message(),
            'details' => $finalized->get_error_data(),
            'patch_report' => $patch_report,
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'patch_report' => $patch_report,
        'preflight' => $preflight,
        'protected_zone_guard' => $protected_zone_guard,
        'transaction' => $finalized['transaction'],
        'quality_summary' => $finalized['quality_summary'],
    ], 200 );
}

function wpae_elementor_page( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: 'Elementor Page' ) );
    $slug = sanitize_title( (string) $request->get_param( 'slug' ) );
    $status = sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'publish' ) );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $allowed_statuses = [ 'publish', 'draft', 'private', 'pending' ];
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $elementor_data = wpae_get_elementor_data_from_request( $request );

    if ( ! in_array( $status, $allowed_statuses, true ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid page status.' ], 400 );
    }

    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
        ], 422 );
    }

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    if ( ! $design_system_contract['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed design-system contract.',
            'details' => $design_system_contract,
        ], 422 );
    }

    $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
        'post_id' => $post_id ?: null,
        'template' => $template,
        'operation' => $post_id > 0 ? 'page_update' : 'page_create',
        'title' => $title,
        'slug' => $slug,
    ] );
    if ( ! $preflight['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor preflight failed.',
            'preflight' => $preflight,
        ], 422 );
    }

    if ( $post_id > 0 && get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Target post_id does not exist.' ], 404 );
    }

    $protected_zone_guard = [ 'ok' => true, 'protected_zones' => [] ];
    if ( $post_id > 0 ) {
        $existing_data = wpae_get_elementor_data_for_post( $post_id );
        if ( is_wp_error( $existing_data ) ) {
            return new WP_REST_Response( [
                'ok' => false,
                'error' => $existing_data->get_error_message(),
                'details' => $existing_data->get_error_data(),
            ], 422 );
        }
        $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $elementor_data, $request );
        if ( ! $protected_zone_guard['ok'] ) {
            return new WP_REST_Response( [
                'ok' => false,
                'error' => $protected_zone_guard['message'],
                'code' => $protected_zone_guard['error_code'],
                'protected_zone_guard' => $protected_zone_guard,
            ], 422 );
        }
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id ?: null,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'template' => $template,
            'preflight' => $preflight,
            'protected_zone_guard' => $protected_zone_guard,
        ], 200 );
    }

    $post_args = [
        'post_title' => $title,
        'post_status' => $status,
        'post_type' => 'page',
    ];

    if ( $slug !== '' ) {
        $post_args['post_name'] = $slug;
    }

    $rollback_snapshot = null;
    $is_new_post = $post_id <= 0;

    if ( $post_id > 0 ) {
        $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_page:update:' . $post_id, [ $post_id ] );
        $post_args['ID'] = $post_id;
        $result = wp_update_post( $post_args, true );
    } else {
        $result = wp_insert_post( $post_args, true );
    }

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
    }

    $post_id = (int) $result;
    if ( $is_new_post ) {
        $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_page:create:' . $post_id, [], [], [ $post_id ] );
    }

    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : null;

        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'post_id' => $post_id,
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
            'transaction' => wpae_build_elementor_transaction_status( $is_new_post ? 'elementor_page_create' : 'elementor_page_update', $post_id, $rollback_snapshot, [
                'metadata_save' => [
                    'ok' => false,
                    'message' => $saved->get_error_message(),
                    'details' => $saved->get_error_data(),
                ],
            ] ),
            'auto_rollback' => $rollback,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    $finalized = wpae_finalize_elementor_transaction( $is_new_post ? 'elementor_page_create' : 'elementor_page_update', $post_id, $rollback_snapshot, $elementor_data, $preflight, $request );
    if ( is_wp_error( $finalized ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $finalized->get_error_message(),
            'details' => $finalized->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'status' => get_post_status( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'preflight' => $preflight,
        'protected_zone_guard' => $protected_zone_guard,
        'transaction' => $finalized['transaction'],
        'quality_summary' => $finalized['quality_summary'],
    ], 200 );
}

