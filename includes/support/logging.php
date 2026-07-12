<?php

defined( 'ABSPATH' ) || exit;

function wpae_get_operation_logs_store(): array {
    $logs = get_option( 'wp_ai_executor_operation_logs', [] );
    return is_array( $logs ) ? $logs : [];
}

function wpae_update_operation_logs_store( array $logs ): void {
    update_option( 'wp_ai_executor_operation_logs', array_slice( array_values( $logs ), 0, WPAE_OPERATION_LOG_MAX_ENTRIES ), false );
}

function wpae_should_log_operation( WP_REST_Request $request ): bool {
    $route = (string) $request->get_route();
    $method = strtoupper( (string) $request->get_method() );

    if ( strpos( $route, '/ai-executor/v1/' ) !== 0 ) {
        return false;
    }

    if ( in_array( $route, [ '/ai-executor/v1/logs', '/ai-executor/v1/key' ], true ) ) {
        return false;
    }

    if ( in_array( $method, [ 'POST', 'DELETE' ], true ) ) {
        return true;
    }

    return in_array( $route, [ '/ai-executor/v1/audit' ], true );
}

function wpae_get_response_data_for_log( $response ): array {
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        return is_array( $data ) ? $data : [];
    }

    if ( $response instanceof WP_HTTP_Response ) {
        $data = $response->get_data();
        return is_array( $data ) ? $data : [];
    }

    if ( is_wp_error( $response ) ) {
        return [
            'error' => $response->get_error_message(),
            'code' => $response->get_error_code(),
        ];
    }

    return [];
}

function wpae_get_response_status_for_log( $response ): int {
    if ( $response instanceof WP_REST_Response || $response instanceof WP_HTTP_Response ) {
        return (int) $response->get_status();
    }

    if ( is_wp_error( $response ) ) {
        $data = $response->get_error_data();
        return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
    }

    return 200;
}

function wpae_collect_log_target_ids( WP_REST_Request $request, array $response_data ): array {
    $targets = [];
    $post_id = absint( $request->get_param( 'post_id' ) ?: ( $response_data['post_id'] ?? 0 ) );
    $attachment_id = absint( $response_data['attachment_id'] ?? 0 );
    $skill_id = (string) ( $request->get_param( 'id' ) ?: ( $response_data['deleted'] ?? '' ) );

    if ( $skill_id === '' && ! empty( $response_data['skill'] ) && is_array( $response_data['skill'] ) ) {
        $skill_id = (string) ( $response_data['skill']['id'] ?? '' );
    }

    if ( $post_id > 0 ) {
        $targets['post_id'] = $post_id;
    }

    if ( $attachment_id > 0 ) {
        $targets['attachment_id'] = $attachment_id;
    }

    if ( $skill_id !== '' ) {
        $targets['skill_id'] = wpae_normalize_skill_id( $skill_id );
    }

    if ( ! empty( $response_data['imported'] ) && is_array( $response_data['imported'] ) ) {
        $skill_ids = [];
        foreach ( $response_data['imported'] as $skill ) {
            if ( is_array( $skill ) && ! empty( $skill['id'] ) ) {
                $skill_ids[] = wpae_normalize_skill_id( (string) $skill['id'] );
            }
        }
        if ( ! empty( $skill_ids ) ) {
            $targets['skill_ids'] = array_values( array_unique( $skill_ids ) );
        }
    }

    if ( ! empty( $response_data['filename'] ) ) {
        $targets['filename'] = sanitize_file_name( (string) $response_data['filename'] );
    }

    return $targets;
}

function wpae_summarize_response_for_log( array $response_data, int $status ): array {
    $summary = [
        'ok' => $status >= 200 && $status < 300 && empty( $response_data['error'] ),
    ];

    foreach ( [ 'error', 'code', 'dry_run', 'same_file', 'validation_ok', 'imported_count', 'total_count', 'mode' ] as $key ) {
        if ( array_key_exists( $key, $response_data ) && ! is_array( $response_data[ $key ] ) ) {
            $summary[ $key ] = $response_data[ $key ];
        }
    }

    if ( isset( $response_data['ok'] ) && is_bool( $response_data['ok'] ) ) {
        $summary['ok'] = $response_data['ok'];
    }

    if ( isset( $response_data['findings'] ) && is_array( $response_data['findings'] ) ) {
        $summary['findings_count'] = count( $response_data['findings'] );
    }

    if ( isset( $response_data['errors'] ) && is_array( $response_data['errors'] ) ) {
        $summary['errors_count'] = count( $response_data['errors'] );
    }

    if ( isset( $response_data['after_errors'] ) && is_array( $response_data['after_errors'] ) ) {
        $summary['after_errors_count'] = count( $response_data['after_errors'] );
    }

    if ( isset( $response_data['changes'] ) && is_array( $response_data['changes'] ) ) {
        $summary['changes_count'] = count( $response_data['changes'] );
    }

    if ( isset( $response_data['agent_conformance'] ) && is_array( $response_data['agent_conformance'] ) ) {
        $summary['agent_conformance_score'] = (int) ( $response_data['agent_conformance']['score'] ?? 0 );
        $summary['agent_conformance_level'] = (string) ( $response_data['agent_conformance']['level'] ?? '' );
    }

    return $summary;
}

function wpae_should_score_conformance( WP_REST_Request $request ): bool {
    $route = (string) $request->get_route();
    $method = strtoupper( (string) $request->get_method() );

    if ( strpos( $route, '/ai-executor/v1/' ) !== 0 ) {
        return false;
    }

    if ( ! in_array( $method, [ 'POST', 'DELETE' ], true ) ) {
        return false;
    }

    return ! in_array( $route, [
        '/ai-executor/v1/key',
        '/ai-executor/v1/guide',
        '/ai-executor/v1/guide/session',
        '/ai-executor/v1/guide/ack',
        '/ai-executor/v1/capabilities',
        '/ai-executor/v1/logs',
        '/ai-executor/v1/skills/export',
    ], true );
}

function wpae_route_requires_guide_token_for_conformance( string $route ): bool {
    return in_array( $route, [
        '/ai-executor/v1/run',
        '/ai-executor/v1/rollback',
        '/ai-executor/v1/elementor/page',
        '/ai-executor/v1/elementor/update',
        '/ai-executor/v1/elementor/patch',
        '/ai-executor/v1/elementor/typography-unlock',
        '/ai-executor/v1/elementor/restore-revision',
        '/ai-executor/v1/skills',
        '/ai-executor/v1/skills/import',
        '/ai-executor/v1/skills/import-url',
        '/ai-executor/v1/media/upload',
        '/ai-executor/v1/exports/create',
        '/ai-executor/v1/exports/prune',
        '/ai-executor/v1/self-update',
        '/ai-executor/v1/self-update-package',
    ], true ) || preg_match( '#^/ai-executor/v1/skills/[a-z0-9_-]+$#', $route );
}

function wpae_conformance_add_criterion( array &$criteria, string $key, string $status, int $points, int $max, string $message, array $evidence = [] ): void {
    $criteria[ $key ] = [
        'status' => $status,
        'points' => max( 0, min( $points, $max ) ),
        'max' => max( 0, $max ),
        'message' => $message,
        'evidence' => $evidence,
    ];
}

function wpae_get_request_elementor_data_for_conformance( WP_REST_Request $request ) {
    $route = (string) $request->get_route();

    if ( ! in_array( $route, [
        '/ai-executor/v1/elementor/validate',
        '/ai-executor/v1/elementor/normalize',
        '/ai-executor/v1/elementor/visual-audit',
        '/ai-executor/v1/elementor/editability-audit',
        '/ai-executor/v1/elementor/page',
        '/ai-executor/v1/elementor/update',
        '/ai-executor/v1/elementor/patch',
    ], true ) ) {
        return null;
    }

    $data = wpae_get_elementor_data_from_request( $request );
    return is_wp_error( $data ) ? null : $data;
}

function wpae_count_elementor_validation_errors_by_type( array $errors ): array {
    $counts = [
        'legacy_layout' => 0,
        'widget_type' => 0,
        'other' => 0,
    ];

    foreach ( $errors as $error ) {
        $error = (string) $error;
        if ( stripos( $error, 'elType=section' ) !== false || stripos( $error, 'elType=column' ) !== false ) {
            $counts['legacy_layout']++;
        } elseif ( stripos( $error, 'widgetType' ) !== false || stripos( $error, 'widget_type' ) !== false ) {
            $counts['widget_type']++;
        } else {
            $counts['other']++;
        }
    }

    return $counts;
}

function wpae_default_elementor_audit_stats(): array {
    return [
        'containers' => 0,
        'containers_with_native_background' => 0,
        'containers_with_native_text_color' => 0,
        'containers_with_native_border' => 0,
        'containers_with_padding' => 0,
        'containers_with_gap' => 0,
        'widgets' => 0,
        'widgets_with_native_text_color' => 0,
        'html_widgets' => 0,
        'html_widget_layout_risks' => 0,
        'empty_heading_widgets' => 0,
        'empty_text_widgets' => 0,
        'heading_widgets' => 0,
        'h1_headings' => 0,
        'h2_h3_headings' => 0,
        'button_widgets' => 0,
        'button_widgets_with_native_style' => 0,
        'text_widgets' => 0,
        'widgets_with_local_typography' => 0,
        'heading_widgets_with_local_typography' => 0,
        'html_widgets_with_script_injected_native_css' => 0,
        'html_widgets_with_heading_typography_important' => 0,
        'elements_with_responsive_settings' => 0,
        'unique_color_count' => 0,
        'color_values' => [],
        'px_unit_count' => 0,
        'relative_unit_count' => 0,
        'viewport_unit_count' => 0,
        'percent_unit_count' => 0,
        'fixed_width_risk_count' => 0,
        'fixed_height_risk_count' => 0,
    ];
}

function wpae_finalize_elementor_audit_stats( array &$stats ): void {
    $stats['unique_color_count'] = count( (array) ( $stats['color_values'] ?? [] ) );
    unset( $stats['color_values'] );
}

function wpae_collect_elementor_setting_colors( array $settings, array &$stats ): void {
    foreach ( $settings as $key => $value ) {
        if ( ! is_string( $value ) || ! preg_match( '/color|background/i', (string) $key ) ) {
            continue;
        }

        if ( preg_match_all( '/#[0-9a-f]{3,8}\b/i', $value, $matches ) ) {
            foreach ( $matches[0] as $color ) {
                $stats['color_values'][ strtolower( $color ) ] = true;
            }
        }
    }
}

function wpae_collect_elementor_setting_units( array $settings, array &$stats ): void {
    foreach ( $settings as $key => $value ) {
        $key = (string) $key;

        if ( is_array( $value ) ) {
            $unit = strtolower( (string) ( $value['unit'] ?? '' ) );
            if ( $unit !== '' ) {
                if ( $unit === 'px' ) {
                    $stats['px_unit_count']++;
                    if ( preg_match( '/width|basis|size/i', $key ) ) {
                        $stats['fixed_width_risk_count']++;
                    }
                    if ( preg_match( '/height|min_height|max_height/i', $key ) ) {
                        $stats['fixed_height_risk_count']++;
                    }
                } elseif ( in_array( $unit, [ 'rem', 'em' ], true ) ) {
                    $stats['relative_unit_count']++;
                } elseif ( in_array( $unit, [ '%', 'vw', 'dvw', 'lvw' ], true ) ) {
                    $stats['percent_unit_count']++;
                } elseif ( in_array( $unit, [ 'vh', 'svh', 'dvh', 'lvh' ], true ) ) {
                    $stats['viewport_unit_count']++;
                }
            }

            wpae_collect_elementor_setting_units( $value, $stats );
            continue;
        }

        if ( ! is_string( $value ) ) {
            continue;
        }

        if ( preg_match_all( '/-?\d*\.?\d+(px|rem|em|%|vh|svh|dvh|lvh|vw|dvw|lvw)\b/i', $value, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $unit = strtolower( (string) ( $match[1] ?? '' ) );
                if ( $unit === 'px' ) {
                    $stats['px_unit_count']++;
                    if ( preg_match( '/width|basis|size/i', $key ) ) {
                        $stats['fixed_width_risk_count']++;
                    }
                    if ( preg_match( '/height|min_height|max_height/i', $key ) ) {
                        $stats['fixed_height_risk_count']++;
                    }
                } elseif ( in_array( $unit, [ 'rem', 'em' ], true ) ) {
                    $stats['relative_unit_count']++;
                } elseif ( in_array( $unit, [ '%', 'vw', 'dvw', 'lvw' ], true ) ) {
                    $stats['percent_unit_count']++;
                } elseif ( in_array( $unit, [ 'vh', 'svh', 'dvh', 'lvh' ], true ) ) {
                    $stats['viewport_unit_count']++;
                }
            }
        }
    }
}

function wpae_setting_has_spacing_value( $value ): bool {
    if ( is_array( $value ) ) {
        foreach ( [ 'top', 'right', 'bottom', 'left', 'size', 'column', 'row' ] as $key ) {
            if ( isset( $value[ $key ] ) && (string) $value[ $key ] !== '' && (float) $value[ $key ] !== 0.0 ) {
                return true;
            }
        }

        return false;
    }

    return (string) $value !== '' && (float) $value !== 0.0;
}

function wpae_setting_has_color_value( $value ): bool {
    if ( is_array( $value ) ) {
        foreach ( $value as $child_value ) {
            if ( wpae_setting_has_color_value( $child_value ) ) {
                return true;
            }
        }

        return false;
    }

    $value = trim( (string) $value );
    return $value !== '' && ( preg_match( '/#[0-9a-f]{3,8}\b/i', $value ) || preg_match( '/rgba?\(/i', $value ) );
}

function wpae_elementor_has_any_setting( array $settings, array $patterns ): bool {
    foreach ( $settings as $setting_key => $setting_value ) {
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, (string) $setting_key ) ) {
                if ( is_array( $setting_value ) ) {
                    if ( wpae_setting_has_spacing_value( $setting_value ) || wpae_setting_has_color_value( $setting_value ) ) {
                        return true;
                    }
                    continue;
                }

                if ( trim( (string) $setting_value ) !== '' ) {
                    return true;
                }
            }
        }
    }

    return false;
}

function wpae_collect_elementor_design_quality_stats( array $elements, array &$stats ): void {
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $el_type = (string) ( $element['elType'] ?? '' );
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        wpae_collect_elementor_setting_colors( $settings, $stats );
        wpae_collect_elementor_setting_units( $settings, $stats );

        foreach ( array_keys( $settings ) as $setting_key ) {
            if ( preg_match( '/(_mobile|_tablet|mobile_|tablet_|mobile|tablet)/i', (string) $setting_key ) ) {
                $stats['elements_with_responsive_settings']++;
                break;
            }
        }

        if ( $el_type === 'container' ) {
            $has_padding = false;
            $has_gap = false;
            $has_text_color = false;
            $has_border = false;

            foreach ( $settings as $setting_key => $setting_value ) {
                if ( stripos( (string) $setting_key, 'padding' ) !== false && wpae_setting_has_spacing_value( $setting_value ) ) {
                    $has_padding = true;
                }

                if ( preg_match( '/gap|space_between|widgets_spacing/i', (string) $setting_key ) && wpae_setting_has_spacing_value( $setting_value ) ) {
                    $has_gap = true;
                }

                if ( preg_match( '/(^|_)color|text_color|typography_typography/i', (string) $setting_key ) && wpae_setting_has_color_value( $setting_value ) ) {
                    $has_text_color = true;
                }

                if ( preg_match( '/border|radius|box_shadow/i', (string) $setting_key ) && trim( is_array( $setting_value ) ? wp_json_encode( $setting_value ) : (string) $setting_value ) !== '' ) {
                    $has_border = true;
                }
            }

            if ( $has_padding ) {
                $stats['containers_with_padding']++;
            }

            if ( $has_gap ) {
                $stats['containers_with_gap']++;
            }

            if ( $has_text_color ) {
                $stats['containers_with_native_text_color']++;
            }

            if ( $has_border ) {
                $stats['containers_with_native_border']++;
            }
        }

        if ( $el_type === 'widget' ) {
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            $has_local_typography = wpae_elementor_has_any_setting( $settings, [
                '/^typography_(typography|font_family|font_size|font_weight|line_height|letter_spacing|text_transform|font_style|text_decoration|word_spacing)/i',
            ] );
            if ( $has_local_typography ) {
                $stats['widgets_with_local_typography']++;
            }
            if ( wpae_elementor_has_any_setting( $settings, [ '/(^|_)color|text_color|title_color|button_text_color/i' ] ) ) {
                $stats['widgets_with_native_text_color']++;
            }

            if ( $widget_type === 'heading' ) {
                $stats['heading_widgets']++;
                if ( $has_local_typography ) {
                    $stats['heading_widgets_with_local_typography']++;
                }
                $header_size = strtolower( (string) ( $settings['header_size'] ?? $settings['size'] ?? '' ) );
                if ( $header_size === 'h1' ) {
                    $stats['h1_headings']++;
                } elseif ( in_array( $header_size, [ 'h2', 'h3' ], true ) ) {
                    $stats['h2_h3_headings']++;
                }
            } elseif ( $widget_type === 'button' ) {
                $stats['button_widgets']++;
                if ( wpae_elementor_has_any_setting( $settings, [ '/background|button_background|text_color|border|radius|typography/i' ] ) ) {
                    $stats['button_widgets_with_native_style']++;
                }
            } elseif ( $widget_type === 'text-editor' ) {
                $stats['text_widgets']++;
            } elseif ( $widget_type === 'html' ) {
                $html = (string) ( $settings['html'] ?? $settings['content'] ?? $settings['code'] ?? '' );
                if ( ! empty( wpae_html_script_injects_native_css( $html ) ) ) {
                    $stats['html_widgets_with_script_injected_native_css']++;
                }
                if ( wpae_html_has_blocking_heading_typography_override( $html ) ) {
                    $stats['html_widgets_with_heading_typography_important']++;
                }
            }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_collect_elementor_design_quality_stats( $element['elements'], $stats );
        }
    }
}

function wpae_get_elementor_conformance_context( WP_REST_Request $request, array $response_data ): array {
    $route = (string) $request->get_route();
    $data = wpae_get_request_elementor_data_for_conformance( $request );
    if ( $route === '/ai-executor/v1/elementor/normalize' && isset( $response_data['normalized_elementor_data'] ) && is_array( $response_data['normalized_elementor_data'] ) ) {
        $data = $response_data['normalized_elementor_data'];
    } elseif ( isset( $response_data['elementor_data'] ) && is_array( $response_data['elementor_data'] ) ) {
        $data = $response_data['elementor_data'];
    }
    $stats = wpae_default_elementor_audit_stats();
    $validation_errors = [];

    if ( is_array( $data ) ) {
        $validation_errors = wpae_validate_elementor_data_array( $data );
        wpae_collect_elementor_audit_stats( $data, $stats );
        wpae_collect_elementor_design_quality_stats( $data, $stats );
    }

    wpae_finalize_elementor_audit_stats( $stats );

    if ( isset( $response_data['stats'] ) && is_array( $response_data['stats'] ) ) {
        $stats = array_merge( $stats, array_intersect_key( $response_data['stats'], $stats ) );
    }

    if ( isset( $response_data['errors'] ) && is_array( $response_data['errors'] ) ) {
        $validation_errors = array_merge( $validation_errors, $response_data['errors'] );
    }

    if ( isset( $response_data['after_errors'] ) && is_array( $response_data['after_errors'] ) ) {
        $validation_errors = array_merge( $validation_errors, $response_data['after_errors'] );
    }

    if ( isset( $response_data['details']['errors'] ) && is_array( $response_data['details']['errors'] ) ) {
        $validation_errors = array_merge( $validation_errors, $response_data['details']['errors'] );
    }

    $is_elementor_route = in_array( $route, [
        '/ai-executor/v1/elementor/validate',
        '/ai-executor/v1/elementor/normalize',
        '/ai-executor/v1/elementor/compose',
        '/ai-executor/v1/elementor/visual-audit',
        '/ai-executor/v1/elementor/editability-audit',
        '/ai-executor/v1/elementor/page',
        '/ai-executor/v1/elementor/update',
        '/ai-executor/v1/elementor/patch',
        '/ai-executor/v1/audit',
    ], true );

    return [
        'applicable' => $is_elementor_route || is_array( $data ) || isset( $response_data['stats'] ),
        'has_request_data' => is_array( $data ),
        'stats' => $stats,
        'validation_errors' => array_values( array_unique( array_map( 'strval', $validation_errors ) ) ),
        'error_counts' => wpae_count_elementor_validation_errors_by_type( $validation_errors ),
    ];
}

function wpae_build_agent_conformance( WP_REST_Request $request, array $response_data, int $status ): array {
    $route = (string) $request->get_route();
    $criteria = [];
    $blocking_errors = [];
    $guide_token = (string) ( $request->get_header( 'X-WPAE-Guide-Token' ) ?: $request->get_param( 'guide_token' ) );
    $guide_hash = (string) ( $request->get_header( 'X-WPAE-Guide-Hash' ) ?: $request->get_param( 'guide_hash' ) );

    if ( wpae_route_requires_guide_token_for_conformance( $route ) ) {
        $guide_ok = $guide_token !== '' && $guide_hash !== '' && $status !== 403;
        wpae_conformance_add_criterion(
            $criteria,
            'guide_token_flow',
            $guide_ok ? 'pass' : 'fail',
            $guide_ok ? 15 : 0,
            15,
            $guide_ok ? 'Write request used guide-token headers.' : 'Write request did not complete with a valid guide-token flow.',
            [ 'has_guide_hash' => $guide_hash !== '' ]
        );
    }

    $file_error = (string) ( $response_data['blocked_operation'] ?? '' );
    $file_ok = $file_error === '' && stripos( (string) ( $response_data['error'] ?? '' ), 'Filesystem writes are disabled' ) === false;
    wpae_conformance_add_criterion(
        $criteria,
        'file_policy',
        $file_ok ? 'pass' : 'fail',
        $file_ok ? 15 : 0,
        15,
        $file_ok ? 'No forbidden filesystem operation was detected.' : 'Forbidden filesystem operation was blocked.',
        $file_error !== '' ? [ 'blocked_operation' => $file_error ] : []
    );

    $elementor = wpae_get_elementor_conformance_context( $request, $response_data );
    if ( $elementor['applicable'] ) {
        $errors = $elementor['validation_errors'];
        $error_counts = $elementor['error_counts'];
        $stats = $elementor['stats'];
        $design_system_data = null;
        if ( isset( $response_data['normalized_elementor_data'] ) && is_array( $response_data['normalized_elementor_data'] ) ) {
            $design_system_data = $response_data['normalized_elementor_data'];
        }
        if ( ! is_array( $design_system_data ) && isset( $response_data['elementor_data'] ) && is_array( $response_data['elementor_data'] ) ) {
            $design_system_data = $response_data['elementor_data'];
        }
        if ( ! is_array( $design_system_data ) ) {
            $design_system_data = wpae_get_request_elementor_data_for_conformance( $request );
        }
        $design_system_contract = is_array( $design_system_data )
            ? wpae_validate_design_system_contract( $design_system_data )
            : [ 'ok' => true, 'errors' => [], 'stats' => [] ];
        $elementor_ok = empty( $errors ) && $status < 400;

        wpae_conformance_add_criterion(
            $criteria,
            'elementor_policy',
            $elementor_ok ? 'pass' : 'fail',
            $elementor_ok ? 20 : 0,
            20,
            $elementor_ok ? 'Elementor data passed runtime validation.' : 'Elementor data failed runtime validation.',
            [ 'errors_count' => count( $errors ) ]
        );

        $has_containers = (int) ( $stats['containers'] ?? 0 ) > 0;
        $legacy_clean = (int) ( $error_counts['legacy_layout'] ?? 0 ) === 0;
        wpae_conformance_add_criterion(
            $criteria,
            'native_flex_containers',
            ( $has_containers && $legacy_clean ) ? 'pass' : 'warn',
            ( $has_containers && $legacy_clean ) ? 15 : 7,
            15,
            $has_containers ? 'Elementor layout uses native containers.' : 'No native Elementor containers were detected.',
            [ 'containers' => (int) ( $stats['containers'] ?? 0 ), 'legacy_layout_errors' => (int) ( $error_counts['legacy_layout'] ?? 0 ) ]
        );

        $widget_type_ok = (int) ( $error_counts['widget_type'] ?? 0 ) === 0;
        wpae_conformance_add_criterion(
            $criteria,
            'widget_type_integrity',
            $widget_type_ok ? 'pass' : 'fail',
            $widget_type_ok ? 15 : 0,
            15,
            $widget_type_ok ? 'Widgets use valid camelCase widgetType metadata.' : 'Widget metadata has widgetType/widget_type problems.',
            [ 'widget_type_errors' => (int) ( $error_counts['widget_type'] ?? 0 ) ]
        );

        $native_visual_ok = (int) ( $stats['containers_with_native_background'] ?? 0 ) > 0 || (int) ( $stats['containers'] ?? 0 ) === 0;
        wpae_conformance_add_criterion(
            $criteria,
            'native_visual_settings',
            $native_visual_ok ? 'pass' : 'warn',
            $native_visual_ok ? 10 : 4,
            10,
            $native_visual_ok ? 'Native Elementor visual settings are present where detectable.' : 'No native container background settings were detected.',
            [
                'containers_with_native_background' => (int) ( $stats['containers_with_native_background'] ?? 0 ),
                'html_widget_layout_risks' => (int) ( $stats['html_widget_layout_risks'] ?? 0 ),
            ]
        );

        wpae_conformance_add_criterion(
            $criteria,
            'design_system_consistency',
            ! empty( $design_system_contract['ok'] ) ? 'pass' : 'fail',
            ! empty( $design_system_contract['ok'] ) ? 15 : 0,
            15,
            ! empty( $design_system_contract['ok'] ) ? 'Elementor data follows the required project design system.' : 'Elementor data violates the required project design system.',
            [
                'errors' => $design_system_contract['errors'] ?? [],
                'stats' => $design_system_contract['stats'] ?? [],
            ]
        );

        $heading_count = (int) ( $stats['heading_widgets'] ?? 0 );
        $has_hierarchy = $heading_count >= 2 && ( (int) ( $stats['h1_headings'] ?? 0 ) > 0 || (int) ( $stats['h2_h3_headings'] ?? 0 ) > 0 );
        wpae_conformance_add_criterion(
            $criteria,
            'typography_hierarchy',
            $has_hierarchy ? 'pass' : ( $heading_count > 0 ? 'warn' : 'fail' ),
            $has_hierarchy ? 10 : ( $heading_count > 0 ? 5 : 0 ),
            10,
            $has_hierarchy ? 'Elementor content has a detectable heading hierarchy.' : 'Heading hierarchy is weak or missing.',
            [
                'heading_widgets' => $heading_count,
                'h1_headings' => (int) ( $stats['h1_headings'] ?? 0 ),
                'h2_h3_headings' => (int) ( $stats['h2_h3_headings'] ?? 0 ),
            ]
        );

        $container_count = max( 1, (int) ( $stats['containers'] ?? 0 ) );
        $spacing_ratio = min(
            (int) ( $stats['containers_with_padding'] ?? 0 ) / $container_count,
            (int) ( $stats['containers_with_gap'] ?? 0 ) / $container_count
        );
        wpae_conformance_add_criterion(
            $criteria,
            'spacing_consistency',
            $spacing_ratio >= 0.45 ? 'pass' : ( $spacing_ratio >= 0.2 ? 'warn' : 'fail' ),
            $spacing_ratio >= 0.45 ? 10 : ( $spacing_ratio >= 0.2 ? 5 : 0 ),
            10,
            $spacing_ratio >= 0.45 ? 'Containers include native spacing settings.' : 'Native padding/gap settings are sparse.',
            [
                'containers' => (int) ( $stats['containers'] ?? 0 ),
                'containers_with_padding' => (int) ( $stats['containers_with_padding'] ?? 0 ),
                'containers_with_gap' => (int) ( $stats['containers_with_gap'] ?? 0 ),
            ]
        );

        $has_cta = (int) ( $stats['button_widgets'] ?? 0 ) > 0;
        wpae_conformance_add_criterion(
            $criteria,
            'cta_visibility',
            $has_cta ? 'pass' : 'warn',
            $has_cta ? 8 : 3,
            8,
            $has_cta ? 'At least one native Elementor button CTA is present.' : 'No native button CTA was detected.',
            [ 'button_widgets' => (int) ( $stats['button_widgets'] ?? 0 ) ]
        );

        $responsive_count = (int) ( $stats['elements_with_responsive_settings'] ?? 0 );
        wpae_conformance_add_criterion(
            $criteria,
            'mobile_readiness',
            $responsive_count > 0 ? 'pass' : 'warn',
            $responsive_count > 0 ? 8 : 4,
            8,
            $responsive_count > 0 ? 'Responsive Elementor settings are present.' : 'No responsive Elementor settings were detected.',
            [ 'elements_with_responsive_settings' => $responsive_count ]
        );

        $unit_risks = (int) ( $stats['fixed_width_risk_count'] ?? 0 ) + (int) ( $stats['fixed_height_risk_count'] ?? 0 );
        $relative_unit_count = (int) ( $stats['relative_unit_count'] ?? 0 ) + (int) ( $stats['percent_unit_count'] ?? 0 ) + (int) ( $stats['viewport_unit_count'] ?? 0 );
        wpae_conformance_add_criterion(
            $criteria,
            'responsive_unit_policy',
            $unit_risks === 0 && $relative_unit_count > 0 ? 'pass' : 'warn',
            $unit_risks === 0 && $relative_unit_count > 0 ? 8 : 4,
            8,
            $unit_risks === 0 && $relative_unit_count > 0 ? 'Responsive-friendly units are present.' : 'Prefer rem/em, vh, and percentages over fixed px units where practical.',
            [
                'px_unit_count' => (int) ( $stats['px_unit_count'] ?? 0 ),
                'relative_unit_count' => (int) ( $stats['relative_unit_count'] ?? 0 ),
                'percent_unit_count' => (int) ( $stats['percent_unit_count'] ?? 0 ),
                'viewport_unit_count' => (int) ( $stats['viewport_unit_count'] ?? 0 ),
                'fixed_width_risk_count' => (int) ( $stats['fixed_width_risk_count'] ?? 0 ),
                'fixed_height_risk_count' => (int) ( $stats['fixed_height_risk_count'] ?? 0 ),
            ]
        );

        $color_count = (int) ( $stats['unique_color_count'] ?? 0 );
        $palette_ok = $color_count >= 3 && $color_count <= 10;
        wpae_conformance_add_criterion(
            $criteria,
            'palette_quality',
            $palette_ok ? 'pass' : 'warn',
            $palette_ok ? 8 : 4,
            8,
            $palette_ok ? 'Detected palette has enough variety without becoming noisy.' : 'Detected palette looks too sparse or too noisy.',
            [ 'unique_color_count' => $color_count ]
        );

        $empty_content = (int) ( $stats['empty_heading_widgets'] ?? 0 ) + (int) ( $stats['empty_text_widgets'] ?? 0 );
        $content_count = $heading_count + (int) ( $stats['text_widgets'] ?? 0 );
        $content_ok = $empty_content === 0 && $content_count >= 3;
        wpae_conformance_add_criterion(
            $criteria,
            'content_completeness',
            $content_ok ? 'pass' : ( $empty_content === 0 ? 'warn' : 'fail' ),
            $content_ok ? 10 : ( $empty_content === 0 ? 5 : 0 ),
            10,
            $content_ok ? 'Native text content is populated.' : 'Content appears sparse or has empty native text widgets.',
            [
                'empty_heading_widgets' => (int) ( $stats['empty_heading_widgets'] ?? 0 ),
                'empty_text_widgets' => (int) ( $stats['empty_text_widgets'] ?? 0 ),
                'heading_widgets' => $heading_count,
                'text_widgets' => (int) ( $stats['text_widgets'] ?? 0 ),
            ]
        );
    }

    $verified = $route === '/ai-executor/v1/audit'
        || (string) $request->get_header( 'X-WPAE-Verified' ) === '1'
        || (bool) $request->get_param( 'verified' );
    wpae_conformance_add_criterion(
        $criteria,
        'verification_signal',
        $verified ? 'pass' : 'warn',
        $verified ? 10 : 5,
        10,
        $verified ? 'Verification signal is present.' : 'No explicit post-write verification signal was provided.',
        [ 'audit_endpoint' => $route === '/ai-executor/v1/audit' ]
    );

    $points = 0;
    $max = 0;
    foreach ( $criteria as $criterion ) {
        $points += (int) ( $criterion['points'] ?? 0 );
        $max += (int) ( $criterion['max'] ?? 0 );
        if ( ( $criterion['status'] ?? '' ) === 'fail' ) {
            $blocking_errors[] = $criterion['message'] ?? 'Conformance criterion failed.';
        }
    }

    $score = $max > 0 ? (int) round( ( $points / $max ) * 100 ) : 100;
    $level = $score >= 90 ? 'strong' : ( $score >= 75 ? 'acceptable' : ( $score >= 50 ? 'weak' : 'blocked' ) );

    return [
        'score' => $score,
        'level' => $level,
        'points' => $points,
        'max_points' => $max,
        'blocking_errors' => $blocking_errors,
        'criteria' => $criteria,
    ];
}

function wpae_attach_agent_conformance( WP_REST_Request $request, $response ) {
    if ( ! wpae_should_score_conformance( $request ) || ! ( $response instanceof WP_HTTP_Response ) ) {
        return $response;
    }

    $data = $response->get_data();
    if ( ! is_array( $data ) ) {
        return $response;
    }

    if ( isset( $data['agent_conformance'] ) ) {
        return $response;
    }

    $data['agent_conformance'] = wpae_build_agent_conformance( $request, $data, (int) $response->get_status() );
    $response->set_data( $data );

    return $response;
}

function wpae_record_operation_log( WP_REST_Request $request, $response ): void {
    if ( ! wpae_should_log_operation( $request ) || ! wpae_auth( $request ) ) {
        return;
    }

    $status = wpae_get_response_status_for_log( $response );
    $response_data = wpae_get_response_data_for_log( $response );
    $route = (string) $request->get_route();
    $logs = wpae_get_operation_logs_store();
    $guide_hash = (string) ( $request->get_header( 'X-WPAE-Guide-Hash' ) ?: $request->get_param( 'guide_hash' ) );

    array_unshift( $logs, [
        'id' => bin2hex( random_bytes( 8 ) ),
        'time' => gmdate( 'c' ),
        'method' => strtoupper( (string) $request->get_method() ),
        'endpoint' => preg_replace( '#^/ai-executor/v1#', '', $route ),
        'status' => $status,
        'actor' => sanitize_text_field( (string) ( $request->get_header( 'X-WPAE-Actor' ) ?: $request->get_header( 'X-AI-Agent' ) ?: 'agent' ) ),
        'ip_hash' => hash( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        'guide_hash' => $guide_hash !== '' ? $guide_hash : null,
        'target_ids' => wpae_collect_log_target_ids( $request, $response_data ),
        'rollback_snapshot_id' => sanitize_text_field( (string) ( $response_data['rollback_snapshot_id'] ?? $response_data['snapshot_id'] ?? '' ) ) ?: null,
        'validation_result' => wpae_summarize_response_for_log( $response_data, $status ),
    ] );

    wpae_update_operation_logs_store( $logs );
}

add_filter( 'rest_post_dispatch', function ( $response, WP_REST_Server $server, WP_REST_Request $request ) {
    $response = wpae_attach_agent_conformance( $request, $response );
    wpae_record_operation_log( $request, $response );
    return $response;
}, 10, 3 );

function wpae_get_logs( WP_REST_Request $request ): WP_REST_Response {
    $limit = max( 1, min( WPAE_OPERATION_LOG_MAX_ENTRIES, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
    $logs = array_slice( wpae_get_operation_logs_store(), 0, $limit );

    return new WP_REST_Response( [
        'logs' => $logs,
        'count' => count( $logs ),
        'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
        'storage' => 'wp_options',
        'redaction' => 'API keys, guide tokens, raw request bodies, and raw page payloads are not logged.',
    ], 200 );
}
