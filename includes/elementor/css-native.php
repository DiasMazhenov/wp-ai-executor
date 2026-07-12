<?php

defined( 'ABSPATH' ) || exit;

function wpae_css_native_control_value( string $value ) {
    $value = trim( $value );
    if ( preg_match( '/^(-?\d+(?:\.\d+)?)(px|rem|em|%|vh|svh|vw)$/i', $value, $match ) ) {
        return [
            'unit' => strtolower( $match[2] ),
            'size' => (float) $match[1],
            'sizes' => [],
        ];
    }

    if ( preg_match( '/^(-?\d+(?:\.\d+)?)$/', $value, $match ) ) {
        return [
            'unit' => 'em',
            'size' => (float) $match[1],
            'sizes' => [],
        ];
    }

    return null;
}

function wpae_css_native_dimension_value( string $value ) {
    $parts = preg_split( '/\s+/', trim( $value ) );
    if ( ! is_array( $parts ) || empty( $parts ) || count( $parts ) > 4 ) {
        return null;
    }

    $parsed = [];
    $unit = null;
    foreach ( $parts as $part ) {
        if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)(px|rem|em|%)$/i', $part, $match ) ) {
            return null;
        }
        $unit = $unit ?: strtolower( $match[2] );
        if ( $unit !== strtolower( $match[2] ) ) {
            return null;
        }
        $parsed[] = (string) (float) $match[1];
    }

    if ( count( $parsed ) === 1 ) {
        $top = $right = $bottom = $left = $parsed[0];
    } elseif ( count( $parsed ) === 2 ) {
        [ $top, $right ] = $parsed;
        $bottom = $top;
        $left = $right;
    } elseif ( count( $parsed ) === 3 ) {
        [ $top, $right, $bottom ] = $parsed;
        $left = $right;
    } else {
        [ $top, $right, $bottom, $left ] = $parsed;
    }

    return [
        'unit' => $unit ?: 'px',
        'top' => $top,
        'right' => $right,
        'bottom' => $bottom,
        'left' => $left,
        'isLinked' => count( array_unique( [ $top, $right, $bottom, $left ] ) ) === 1,
    ];
}

function wpae_css_native_color_value( string $value ): string {
    $value = trim( preg_replace( '/\s*!important\s*$/i', '', $value ) );
    if ( preg_match( '/^(#[a-f0-9]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|[a-z]+)$/i', $value ) ) {
        return $value;
    }

    return '';
}

function wpae_css_native_font_family_value( string $value ): string {
    $first = trim( explode( ',', $value )[0] ?? '' );
    $first = trim( $first, " \t\n\r\0\x0B'\"" );
    return sanitize_text_field( $first );
}

function wpae_css_native_build_patches( string $element_id, string $property, string $value, string $selector, array &$report ): array {
    $property = strtolower( trim( $property ) );
    $value = trim( preg_replace( '/\s*!important\s*$/i', '', $value ) );
    $patches = [];

    $add = static function ( string $path, $native_value ) use ( &$patches, $element_id ): void {
        $patches[] = [
            'element_id' => $element_id,
            'path' => $path,
            'value' => $native_value,
        ];
    };

    if ( $property === 'font-family' ) {
        $family = wpae_css_native_font_family_value( $value );
        if ( $family === '' ) {
            return [];
        }
        $add( 'settings.typography_typography', 'custom' );
        $add( 'settings.typography_font_family', $family );
    } elseif ( $property === 'font-size' ) {
        $control = wpae_css_native_control_value( $value );
        if ( $control === null ) {
            return [];
        }
        $add( 'settings.typography_typography', 'custom' );
        $add( 'settings.typography_font_size', $control );
    } elseif ( $property === 'font-weight' ) {
        if ( ! preg_match( '/^(normal|bold|[1-9]00)$/i', $value ) ) {
            return [];
        }
        $add( 'settings.typography_typography', 'custom' );
        $add( 'settings.typography_font_weight', strtolower( $value ) );
    } elseif ( $property === 'line-height' ) {
        $control = wpae_css_native_control_value( $value );
        if ( $control === null ) {
            return [];
        }
        $add( 'settings.typography_typography', 'custom' );
        $add( 'settings.typography_line_height', $control );
    } elseif ( $property === 'letter-spacing' ) {
        $control = wpae_css_native_control_value( $value );
        if ( $control === null ) {
            return [];
        }
        $add( 'settings.typography_typography', 'custom' );
        $add( 'settings.typography_letter_spacing', $control );
    } elseif ( $property === 'text-transform' ) {
        if ( ! in_array( strtolower( $value ), [ 'none', 'uppercase', 'lowercase', 'capitalize' ], true ) ) {
            return [];
        }
        $add( 'settings.typography_typography', 'custom' );
        $add( 'settings.typography_text_transform', strtolower( $value ) );
    } elseif ( $property === 'color' ) {
        $color = wpae_css_native_color_value( $value );
        if ( $color === '' ) {
            return [];
        }
        $add( 'settings.title_color', $color );
        $add( 'settings.text_color', $color );
        $add( 'settings.button_text_color', $color );
    } elseif ( $property === 'background-color' ) {
        $color = wpae_css_native_color_value( $value );
        if ( $color === '' ) {
            return [];
        }
        $add( 'settings.background_background', 'classic' );
        $add( 'settings.background_color', $color );
    } elseif ( $property === 'border-color' ) {
        $color = wpae_css_native_color_value( $value );
        if ( $color === '' ) {
            return [];
        }
        $add( 'settings.border_border', 'solid' );
        $add( 'settings.border_color', $color );
    } elseif ( in_array( $property, [ 'padding', 'margin', 'border-radius' ], true ) ) {
        $dimension = wpae_css_native_dimension_value( $value );
        if ( $dimension === null ) {
            return [];
        }
        $add( 'settings.' . ( $property === 'border-radius' ? 'border_radius' : $property ), $dimension );
    } elseif ( $property === 'min-height' ) {
        $control = wpae_css_native_control_value( $value );
        if ( $control === null ) {
            return [];
        }
        $add( 'settings.min_height', $control );
    } elseif ( $property === 'gap' ) {
        $control = wpae_css_native_control_value( $value );
        if ( $control === null ) {
            return [];
        }
        $add( 'settings.gap', $control );
    } elseif ( $property === 'flex-direction' ) {
        if ( ! in_array( strtolower( $value ), [ 'row', 'column', 'row-reverse', 'column-reverse' ], true ) ) {
            return [];
        }
        $add( 'settings.flex_direction', strtolower( $value ) );
    } elseif ( $property === 'justify-content' ) {
        $add( 'settings.justify_content', sanitize_key( $value ) );
    } elseif ( $property === 'align-items' ) {
        $add( 'settings.align_items', sanitize_key( $value ) );
    } elseif ( $property === 'flex-wrap' ) {
        if ( ! in_array( strtolower( $value ), [ 'nowrap', 'wrap', 'wrap-reverse' ], true ) ) {
            return [];
        }
        $add( 'settings.flex_wrap', strtolower( $value ) );
    } elseif ( $property === 'z-index' ) {
        if ( ! preg_match( '/^-?\d+$/', $value ) ) {
            return [];
        }
        $add( 'settings._z_index', (int) $value );
    } else {
        return [];
    }

    $report['migrated_declarations'][] = [
        'element_id' => $element_id,
        'selector' => $selector,
        'property' => $property,
        'value' => $value,
        'patch_count' => count( $patches ),
    ];
    $report['migrated_property_counts'][ $property ] = ( $report['migrated_property_counts'][ $property ] ?? 0 ) + 1;

    return $patches;
}

function wpae_css_native_selector_element_id( string $selector ): string {
    if ( preg_match( '/data-id\s*=\s*["\']?([A-Za-z0-9_-]{3,32})["\']?/i', $selector, $match ) ) {
        return sanitize_key( $match[1] );
    }

    if ( preg_match( '/elementor-element-([A-Za-z0-9_-]{3,32})/i', $selector, $match ) ) {
        return sanitize_key( $match[1] );
    }

    return '';
}

function wpae_css_native_migrate_css_text( string $css, array &$report ): array {
    $patches = [];
    $changed = false;
    $next_css = preg_replace_callback( '/([^{}]+)\{([^{}]*)\}/s', function ( array $match ) use ( &$patches, &$changed, &$report ): string {
        $selector = trim( $match[1] );
        $element_id = wpae_css_native_selector_element_id( $selector );
        if ( $element_id === '' ) {
            return $match[0];
        }

        $kept = [];
        $declarations = explode( ';', $match[2] );
        foreach ( $declarations as $declaration ) {
            $declaration = trim( $declaration );
            if ( $declaration === '' || strpos( $declaration, ':' ) === false ) {
                continue;
            }
            [ $property, $value ] = array_map( 'trim', explode( ':', $declaration, 2 ) );
            $native_patches = wpae_css_native_build_patches( $element_id, $property, $value, $selector, $report );
            if ( ! empty( $native_patches ) ) {
                $patches = array_merge( $patches, $native_patches );
                $changed = true;
                continue;
            }
            $kept[] = $property . ': ' . $value;
        }

        if ( empty( $kept ) ) {
            return '';
        }

        return $selector . ' { ' . implode( '; ', $kept ) . '; }';
    }, $css );

    return [
        'css' => is_string( $next_css ) ? $next_css : $css,
        'patches' => $patches,
        'changed' => $changed,
    ];
}

function wpae_css_native_migrate_html_content( string $html, array &$report ): array {
    $patches = [];
    $changed = false;
    $next_html = preg_replace_callback( '/<style\b[^>]*>(.*?)<\/style>/is', function ( array $match ) use ( &$patches, &$changed, &$report ): string {
        $result = wpae_css_native_migrate_css_text( (string) $match[1], $report );
        if ( ! empty( $result['patches'] ) ) {
            $patches = array_merge( $patches, $result['patches'] );
        }
        if ( ! empty( $result['changed'] ) ) {
            $changed = true;
            $css = trim( (string) $result['css'] );
            return $css === '' ? '' : '<style>' . $css . '</style>';
        }
        return $match[0];
    }, $html );

    return [
        'html' => is_string( $next_html ) ? $next_html : $html,
        'patches' => $patches,
        'changed' => $changed,
    ];
}

function wpae_css_native_prepare_elements( array $elements, array &$report, array &$patches, string $path = 'root' ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_id = sanitize_key( (string) ( $element['id'] ?? $index ) );
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $element_path = $path . '.' . $element_id;

        if ( $widget_type === 'html' ) {
            if ( wpae_elementor_protected_zone_kind( $element ) !== '' ) {
                $report['skipped_protected_html_widgets'][] = [
                    'id' => $element_id,
                    'path' => $element_path,
                ];
            } else {
                foreach ( [ 'html', 'content', 'code' ] as $content_key ) {
                    if ( ! is_string( $settings[ $content_key ] ?? null ) || stripos( $settings[ $content_key ], '<style' ) === false ) {
                        continue;
                    }
                    $result = wpae_css_native_migrate_html_content( $settings[ $content_key ], $report );
                    if ( ! empty( $result['patches'] ) ) {
                        $patches = array_merge( $patches, $result['patches'] );
                    }
                    if ( ! empty( $result['changed'] ) ) {
                        $settings[ $content_key ] = $result['html'];
                        $report['html_widgets_changed'][] = [
                            'id' => $element_id,
                            'path' => $element_path,
                            'content_key' => $content_key,
                        ];
                    }
                    break;
                }
            }
        }

        $element['settings'] = $settings;
        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_css_native_prepare_elements( $element['elements'], $report, $patches, $element_path );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_css_to_native( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $source = 'request';

    if ( $post_id > 0 ) {
        $elementor_data = wpae_get_elementor_data_for_post( $post_id );
        $source = 'post_meta';
    } else {
        $elementor_data = wpae_get_elementor_data_from_request( $request );
    }

    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $elementor_data->get_error_message(),
            'details' => $elementor_data->get_error_data(),
        ], $post_id > 0 ? 422 : 400 );
    }

    $report = [
        'source' => $source,
        'changed' => false,
        'html_widgets_changed' => [],
        'skipped_protected_html_widgets' => [],
        'migrated_declarations' => [],
        'migrated_property_counts' => [],
        'patch_report' => null,
    ];
    $patches = [];
    $prepared_data = wpae_css_native_prepare_elements( $elementor_data, $report, $patches );
    $patch_result = ! empty( $patches ) ? wpae_apply_elementor_patches( $prepared_data, $patches ) : [ 'data' => $prepared_data, 'report' => [ 'changes' => [], 'errors' => [], 'missing_element_ids' => [] ] ];
    $updated_data = $patch_result['data'];
    $report['patch_report'] = $patch_result['report'];
    $report['changed'] = ! empty( $report['html_widgets_changed'] ) || ! empty( $patch_result['report']['changes'] );

    if ( ! empty( $patch_result['report']['errors'] ) || ! empty( $patch_result['report']['missing_element_ids'] ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'CSS-to-native migration could not apply all generated native patches.',
            'report' => $report,
        ], 422 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $updated_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'CSS-to-native migration produced invalid Elementor data.',
            'details' => [ 'errors' => $validation_errors ],
            'report' => $report,
        ], 422 );
    }

    if ( $dry_run || $post_id <= 0 ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => $dry_run,
            'post_id' => $post_id ?: null,
            'report' => $report,
            'elementor_data' => $post_id > 0 ? null : $updated_data,
            'editability_audit' => wpae_build_elementor_editability_audit( $updated_data, [ 'source' => 'css_to_native_dry_run' ] ),
        ], 200 );
    }

    $existing_data = wpae_get_elementor_data_for_post( $post_id );
    if ( is_wp_error( $existing_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $existing_data->get_error_message() ], 422 );
    }
    $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $updated_data, $request );
    if ( ! $protected_zone_guard['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $protected_zone_guard['message'],
            'code' => $protected_zone_guard['error_code'],
            'protected_zone_guard' => $protected_zone_guard,
            'report' => $report,
        ], 422 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_css_to_native:' . $post_id, [ $post_id ] );
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
        'protected_zone_guard' => $protected_zone_guard,
        'report' => $report,
        'editability_audit' => wpae_build_elementor_editability_audit( $updated_data, [ 'source' => 'css_to_native_after_save', 'post_id' => $post_id ] ),
        'quality_summary' => wpae_build_after_save_quality_summary( $post_id, $updated_data, [] ),
    ], 200 );
}
