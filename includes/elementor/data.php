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

