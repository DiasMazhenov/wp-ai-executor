<?php

defined( 'ABSPATH' ) || exit;

function wpae_editability_add_issue( array &$issues, string $code, string $severity, string $path, string $message, array $details = [] ): void {
    $issues[] = [
        'code' => $code,
        'severity' => $severity,
        'path' => $path,
        'message' => $message,
        'details' => $details,
    ];
}

function wpae_collect_elementor_editability_stats( array $elements, array &$stats, array &$issues, string $path = 'root' ): void {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_path = $path . '[' . $index . ']';
        $el_type = (string) ( $element['elType'] ?? '' );
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $stats['elements']++;

        if ( $el_type === 'container' ) {
            $stats['containers']++;
            if ( wpae_elementor_has_any_setting( $settings, [ '/^(background_background|background_color|background_color_b|background_gradient_)/' ] ) ) {
                $stats['native_background_elements']++;
            }
            if ( wpae_elementor_has_any_setting( $settings, [ '/^(padding|margin|gap|border_radius|min_height|width|content_width)/' ] ) ) {
                $stats['native_spacing_size_elements']++;
            }
            if ( wpae_elementor_has_any_setting( $settings, [ '/^(flex_direction|justify_content|align_items|flex_wrap|gap)/' ] ) ) {
                $stats['native_flex_elements']++;
            }
            if ( wpae_elementor_has_any_setting( $settings, [ '/(^|_)z_index$|^_position$|^sticky$|^sticky_/' ] ) ) {
                $stats['native_position_elements']++;
            }
        }

        if ( $el_type === 'widget' ) {
            $stats['widgets']++;
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            if ( in_array( $widget_type, [ 'heading', 'text-editor', 'button' ], true ) ) {
                $stats['editable_text_widgets']++;
                if ( wpae_elementor_has_any_setting( $settings, [ '/^typography_(typography|font_family|font_size|font_weight|line_height|letter_spacing|text_transform|font_style|text_decoration|word_spacing)/i' ] ) ) {
                    $stats['native_typography_widgets']++;
                }
                if ( wpae_elementor_has_any_setting( $settings, [ '/^(title_color|text_color|button_text_color|background_color|border_color)$/i' ] ) ) {
                    $stats['native_color_widgets']++;
                }
            }

            if ( $widget_type === 'html' ) {
                $stats['html_widgets']++;
                $html = (string) ( $settings['html'] ?? $settings['content'] ?? $settings['code'] ?? '' );
                $script_injected = wpae_html_script_injects_native_css( $html );
                if ( ! empty( $script_injected ) ) {
                    $stats['script_injected_native_css_widgets']++;
                    wpae_editability_add_issue(
                        $issues,
                        'script_injected_native_css',
                        'blocking',
                        $element_path,
                        'HTML widget injects CSS for properties that must be editable through native Elementor settings.',
                        [ 'properties' => array_values( $script_injected ) ]
                    );
                }
                if ( wpae_html_has_blocking_heading_typography_override( $html ) ) {
                    $stats['heading_typography_important_widgets']++;
                    wpae_editability_add_issue(
                        $issues,
                        'heading_typography_important_override',
                        'blocking',
                        $element_path,
                        'HTML widget CSS contains heading typography !important overrides that can beat Elementor controls.',
                        []
                    );
                }
                if ( preg_match( '/(?:font-family|font-size|font-weight|line-height|letter-spacing|text-transform|background-color|border-color|padding|margin|border-radius|min-height|display\s*:\s*flex|gap|z-index|position\s*:\s*(?:fixed|sticky))/i', $html ) ) {
                    $stats['html_native_property_css_widgets']++;
                    wpae_editability_add_issue(
                        $issues,
                        'html_widget_native_property_css',
                        'warning',
                        $element_path,
                        'HTML widget contains CSS-like native design properties. Verify they are enhancements only and not the design source of truth.',
                        []
                    );
                }
            }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_collect_elementor_editability_stats( $element['elements'], $stats, $issues, $element_path . '.elements' );
        }
    }
}

function wpae_build_elementor_editability_audit( array $elementor_data, array $context = [] ): array {
    $stats = [
        'elements' => 0,
        'containers' => 0,
        'widgets' => 0,
        'editable_text_widgets' => 0,
        'native_typography_widgets' => 0,
        'native_color_widgets' => 0,
        'native_background_elements' => 0,
        'native_spacing_size_elements' => 0,
        'native_flex_elements' => 0,
        'native_position_elements' => 0,
        'html_widgets' => 0,
        'script_injected_native_css_widgets' => 0,
        'heading_typography_important_widgets' => 0,
        'html_native_property_css_widgets' => 0,
    ];
    $issues = [];
    wpae_collect_elementor_editability_stats( $elementor_data, $stats, $issues );

    $editable_text = max( 1, (int) $stats['editable_text_widgets'] );
    $containers = max( 1, (int) $stats['containers'] );
    $coverage = [
        'typography_native_ratio' => wpae_ratio( (int) $stats['native_typography_widgets'], $editable_text ),
        'color_native_ratio' => wpae_ratio( (int) $stats['native_color_widgets'], $editable_text ),
        'background_native_ratio' => wpae_ratio( (int) $stats['native_background_elements'], $containers ),
        'spacing_size_native_ratio' => wpae_ratio( (int) $stats['native_spacing_size_elements'], $containers ),
        'flex_native_ratio' => wpae_ratio( (int) $stats['native_flex_elements'], $containers ),
    ];

    if ( $stats['editable_text_widgets'] > 0 && $coverage['typography_native_ratio'] < 0.35 ) {
        wpae_editability_add_issue( $issues, 'low_native_typography_coverage', 'warning', 'root', 'Few editable text widgets expose typography through native Elementor settings.', $coverage );
    }
    if ( $stats['editable_text_widgets'] > 0 && $coverage['color_native_ratio'] < 0.35 ) {
        wpae_editability_add_issue( $issues, 'low_native_color_coverage', 'warning', 'root', 'Few editable text widgets expose colors through native Elementor settings.', $coverage );
    }
    if ( $stats['containers'] > 0 && $coverage['spacing_size_native_ratio'] < 0.45 ) {
        wpae_editability_add_issue( $issues, 'low_native_spacing_coverage', 'warning', 'root', 'Container spacing/size editability looks weak.', $coverage );
    }

    $blocking = array_values( array_filter( $issues, static fn( $issue ) => ( $issue['severity'] ?? '' ) === 'blocking' ) );
    $warnings = array_values( array_filter( $issues, static fn( $issue ) => ( $issue['severity'] ?? '' ) === 'warning' ) );
    $score = 100;
    $score -= count( $blocking ) * 30;
    $score -= count( $warnings ) * 8;
    $score = max( 0, min( 100, $score ) );
    $level = ! empty( $blocking ) ? 'blocked' : ( $score >= 90 ? 'strong' : ( $score >= 75 ? 'acceptable' : ( $score >= 50 ? 'weak' : 'blocked' ) ) );

    return [
        'ok' => empty( $blocking ),
        'editability_audit_version' => 'v01.00.00',
        'score' => $score,
        'level' => $level,
        'context' => $context,
        'stats' => $stats,
        'coverage' => $coverage,
        'issues' => $issues,
        'blocking_count' => count( $blocking ),
        'warning_count' => count( $warnings ),
        'next_fixes' => array_values( array_unique( array_map(
            static fn( $issue ) => (string) ( $issue['message'] ?? '' ),
            $issues
        ) ) ),
        'contract' => [
            'native_source_of_truth' => 'Elementor-supported design properties must be stored in native widget/container settings.',
            'css_exception' => 'CSS/HTML widget code may enhance unsupported behavior, but must not be the only source for editable typography, colors, layout, spacing, or backgrounds.',
        ],
    ];
}

function wpae_elementor_editability_audit( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    if ( $post_id > 0 ) {
        $elementor_data = wpae_get_elementor_data_for_post( $post_id );
        if ( is_wp_error( $elementor_data ) ) {
            return new WP_REST_Response( [
                'ok' => false,
                'error' => $elementor_data->get_error_message(),
                'details' => $elementor_data->get_error_data(),
            ], 422 );
        }
        $context = [
            'source' => 'post_meta',
            'post_id' => $post_id,
            'url' => get_permalink( $post_id ),
        ];
    } else {
        $elementor_data = wpae_get_elementor_data_from_request( $request );
        if ( is_wp_error( $elementor_data ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
        }
        $context = [ 'source' => 'request' ];
    }

    $audit = wpae_build_elementor_editability_audit( $elementor_data, $context );
    return new WP_REST_Response( $audit, ! empty( $audit['ok'] ) ? 200 : 422 );
}
