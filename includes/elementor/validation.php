<?php

defined( 'ABSPATH' ) || exit;

function wpae_capture_elementor_data_snapshot(): array {
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id ASC",
            '_elementor_data'
        ),
        ARRAY_A
    );

    $snapshot = [];
    foreach ( $rows as $row ) {
        $snapshot[ (int) $row['post_id'] ] = (string) $row['meta_value'];
    }

    return $snapshot;
}

function wpae_restore_elementor_data_from_snapshot( int $post_id, array $before ): void {
    if ( array_key_exists( $post_id, $before ) ) {
        update_post_meta( $post_id, '_elementor_data', wp_slash( $before[ $post_id ] ) );
        return;
    }

    delete_post_meta( $post_id, '_elementor_data' );
}

function wpae_validate_changed_elementor_data( array $before, ?WP_REST_Request $request = null ): array {
    $after = wpae_capture_elementor_data_snapshot();
    $errors = [];
    $rolled_back_post_ids = [];
    $elementor_writes_enabled = wpae_capability_enabled( 'elementor_writes' );

    foreach ( $after as $post_id => $raw_data ) {
        if ( array_key_exists( $post_id, $before ) && $before[ $post_id ] === $raw_data ) {
            continue;
        }

        if ( ! $elementor_writes_enabled ) {
            $errors[] = [
                'post_id' => $post_id,
                'errors' => [ 'Elementor writes are disabled by the site owner.' ],
            ];

            wpae_restore_elementor_data_from_snapshot( $post_id, $before );
            $rolled_back_post_ids[] = $post_id;
            continue;
        }

        $post_errors = wpae_validate_elementor_data_string( $raw_data );
        if ( ! empty( $post_errors ) ) {
            $errors[] = [
                'post_id' => $post_id,
                'errors' => $post_errors,
            ];

            wpae_restore_elementor_data_from_snapshot( $post_id, $before );
            $rolled_back_post_ids[] = $post_id;
            continue;
        }

        $elementor_data = json_decode( $raw_data, true );
        if ( ! is_array( $elementor_data ) ) {
            $errors[] = [
                'post_id' => $post_id,
                'errors' => [ 'Elementor _elementor_data must decode to a JSON array before design-system validation.' ],
            ];

            wpae_restore_elementor_data_from_snapshot( $post_id, $before );
            $rolled_back_post_ids[] = $post_id;
            continue;
        }

        $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
        if ( ! $design_system_contract['ok'] ) {
            $errors[] = [
                'post_id' => $post_id,
                'errors' => array_merge(
                    [
                        'Direct _elementor_data writes through /run cannot bypass the design-system contract. Use /elementor/design-system, /elementor/normalize, and /elementor/update dry_run before saving.',
                    ],
                    (array) ( $design_system_contract['errors'] ?? [] )
                ),
                'design_system_contract' => $design_system_contract,
            ];

            wpae_restore_elementor_data_from_snapshot( $post_id, $before );
            $rolled_back_post_ids[] = $post_id;
            continue;
        }

        if ( $request instanceof WP_REST_Request ) {
            $template = (string) get_post_meta( $post_id, '_wp_page_template', true );
            $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
                'post_id' => $post_id,
                'operation' => 'run_elementor_data_write',
                'template' => $template !== '' ? $template : 'elementor_canvas',
            ] );

            if ( ! $preflight['ok'] ) {
                $errors[] = [
                    'post_id' => $post_id,
                    'errors' => [
                        'Direct _elementor_data writes through /run failed Elementor preflight. Use /elementor/update dry_run and fix blocking checks instead of bypassing structured endpoints.',
                    ],
                    'preflight' => $preflight,
                ];

                wpae_restore_elementor_data_from_snapshot( $post_id, $before );
                $rolled_back_post_ids[] = $post_id;
            }
        }
    }

    return [
        'ok' => empty( $errors ),
        'errors' => $errors,
        'rolled_back_post_ids' => $rolled_back_post_ids,
    ];
}

function wpae_validate_elementor_data_string( string $raw_data ): array {
    $data = json_decode( $raw_data, true );
    if ( ! is_array( $data ) ) {
        return [ 'Elementor _elementor_data must be valid JSON array data.' ];
    }

    $errors = [];
    wpae_validate_elementor_elements_recursive( $data, 'root', $errors, wpae_get_enforceable_skill_rules() );
    return $errors;
}

function wpae_html_has_blocking_heading_typography_override( string $html ): bool {
    return (bool) preg_match(
        '/\.elementor-heading-title[^{}]*\{[^{}]*\b(?:font-family|font-size|font-weight|font-style|line-height|letter-spacing|word-spacing|text-transform|text-decoration)\s*:[^;{}]*!important/i',
        $html
    );
}

function wpae_native_css_property_names(): array {
    return [
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'line-height',
        'letter-spacing',
        'word-spacing',
        'text-transform',
        'text-decoration',
        'color',
        'background',
        'background-color',
        'border',
        'border-color',
        'border-radius',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'margin',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'min-height',
        'width',
        'max-width',
        'display',
        'flex-direction',
        'justify-content',
        'align-items',
        'gap',
        'row-gap',
        'column-gap',
        'flex-wrap',
        'z-index',
        'position',
    ];
}

function wpae_html_script_injects_native_css( string $html ): array {
    if ( $html === '' || stripos( $html, '<script' ) === false ) {
        return [];
    }

    $has_style_injection = preg_match( '/createElement\s*\(\s*[\'"]style[\'"]\s*\)/i', $html )
        || preg_match( '/insertAdjacentHTML\s*\([^)]*<style/i', $html )
        || preg_match( '/\bappend(?:Child)?\s*\([^)]*<style/i', $html );

    if ( ! $has_style_injection ) {
        return [];
    }

    // Inspect static CSS assigned to a style node, never the full JavaScript source.
    preg_match_all( '/(?:textContent|innerHTML|innerText)\s*=\s*(`(?:\\\\.|[^`])*`|\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*")/s', $html, $matches );
    $sources = (array) ( $matches[1] ?? [] );
    if ( empty( $sources ) ) {
        return [];
    }

    $blocked = [];
    foreach ( $sources as $source ) {
        $source = (string) $source;
        if ( strlen( $source ) < 2 ) {
            continue;
        }
        $css = substr( $source, 1, -1 );
        if ( $source[0] !== '`' ) {
            $css = stripcslashes( $css );
        }

        $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
        $css = preg_replace( '/@import\s+[^;]+;/i', '', $css );
        $css = preg_replace( '/:root\s*\{[^{}]*\}/si', '', $css );
        $css = preg_replace( '/@(media|supports|container|keyframes)\b[^{}]*\{(?:[^{}]|\{[^{}]*\})*\}/si', '', $css );

        foreach ( wpae_native_css_property_names() as $property ) {
            $pattern = '/(?:^|[;{])\s*' . preg_quote( $property, '/' ) . '\s*:/i';
            if ( preg_match( $pattern, $css ) ) {
                $blocked[] = $property;
            }
        }
    }

    return array_values( array_unique( $blocked ) );
}

function wpae_validate_elementor_elements_recursive( array $elements, string $path, array &$errors, array $skill_rules = [] ): void {
    $allowed_widget_types = [];
    foreach ( $skill_rules as $rule ) {
        if ( ( $rule['type'] ?? '' ) === 'allow_widget_type' && ! empty( $rule['value'] ) ) {
            $allowed_widget_types[] = (string) $rule['value'];
        }
    }
    $allowed_widget_types = array_values( array_unique( $allowed_widget_types ) );

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            $errors[] = "{$path}.{$index}: element must be an object/array.";
            continue;
        }

        $element_path = $path . '.' . ( $element['id'] ?? $index );
        $el_type = $element['elType'] ?? null;

        if ( $el_type === 'section' || $el_type === 'column' ) {
            $errors[] = "{$element_path}: legacy Elementor elType={$el_type} is forbidden; use elType=container.";
        }

        if ( array_key_exists( 'widget_type', $element ) ) {
            $errors[] = "{$element_path}: widget_type is forbidden; use camelCase widgetType.";
        }

        if ( $el_type === 'widget' && empty( $element['widgetType'] ) ) {
            $errors[] = "{$element_path}: widget element must have non-empty camelCase widgetType.";
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        if ( (string) ( $element['widgetType'] ?? '' ) === 'html' ) {
            $html = (string) ( $settings['html'] ?? $settings['content'] ?? $settings['code'] ?? '' );
            if ( $html !== '' && wpae_html_has_blocking_heading_typography_override( $html ) ) {
                $errors[] = "{$element_path}: HTML widget typography !important overrides native heading controls; use /elementor/resolve-typography-overrides.";
            }
            $script_injected_native_css = wpae_html_script_injects_native_css( $html );
            if ( ! empty( $script_injected_native_css ) ) {
                $errors[] = "{$element_path}: html_widget_script_injected_css blocks Elementor editability for native properties (" . implode( ', ', array_slice( $script_injected_native_css, 0, 10 ) ) . '). Move mapped properties into native widget/container settings and keep script-injected CSS only for non-native enhancements.';
            }
        }

        foreach ( $skill_rules as $rule ) {
            $rule_type = $rule['type'] ?? '';
            $rule_value = $rule['value'] ?? '';
            $rule_target = $rule['target'] ?? '';
            $skill_id = $rule['skill_id'] ?? 'unknown';
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

            if ( $rule_type === 'forbid_elementor_eltype' && $el_type === $rule_value ) {
                $errors[] = "{$element_path}: elType={$el_type} is forbidden by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_widget_key' && $el_type === 'widget' && array_key_exists( $rule_value, $element ) ) {
                $errors[] = "{$element_path}: widget key {$rule_value} is forbidden by skill {$skill_id}.";
            }

            if ( $rule_type === 'require_widget_key' && $el_type === 'widget' && empty( $element[ $rule_value ] ) ) {
                $errors[] = "{$element_path}: widget key {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_widget_type' && $el_type === 'widget' && $widget_type === $rule_value ) {
                $errors[] = "{$element_path}: widgetType={$widget_type} is forbidden by skill {$skill_id}.";
            }

            if (
                $rule_type === 'require_widget_setting' &&
                $el_type === 'widget' &&
                ( $rule_target === '' || $rule_target === $widget_type ) &&
                empty( $settings[ $rule_value ] )
            ) {
                $errors[] = "{$element_path}: widget setting {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'require_container_setting' && $el_type === 'container' && empty( $settings[ $rule_value ] ) ) {
                $errors[] = "{$element_path}: container setting {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_html_pattern' && $el_type === 'widget' && $widget_type === 'html' ) {
                $html = (string) ( $settings['html'] ?? $settings['editor'] ?? '' );

                if ( stripos( $html, $rule_value ) !== false ) {
                    $errors[] = "{$element_path}: HTML widget content matches forbidden pattern from skill {$skill_id}.";
                }
            }
        }

        if ( $el_type === 'widget' && ! empty( $allowed_widget_types ) && ! in_array( (string) ( $element['widgetType'] ?? '' ), $allowed_widget_types, true ) ) {
            $errors[] = "{$element_path}: widgetType=" . (string) ( $element['widgetType'] ?? '' ) . ' is not in the enabled skills allowlist.';
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_validate_elementor_elements_recursive( $element['elements'], $element_path, $errors, $skill_rules );
        }
    }
}

function wpae_audit_add_finding( array &$findings, string $code, string $status, string $message, array $details = [] ): void {
    $findings[] = [
        'code' => $code,
        'status' => $status,
        'message' => $message,
        'details' => $details,
    ];
}

function wpae_collect_elementor_audit_stats( array $elements, array &$stats ): void {
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $el_type = (string) ( $element['elType'] ?? '' );
        if ( $el_type === 'container' ) {
            $stats['containers']++;
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $has_background = ! empty( $settings['background_color'] )
                || ! empty( $settings['background_background'] )
                || ! empty( $settings['_background_color'] )
                || ! empty( $settings['_background_background'] );

            if ( $has_background ) {
                $stats['containers_with_native_background']++;
            }
        }

        if ( $el_type === 'widget' ) {
            $stats['widgets']++;
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

            if ( $widget_type === 'html' ) {
                $stats['html_widgets']++;
                $html = (string) ( $settings['html'] ?? $settings['editor'] ?? '' );
                if ( preg_match( '/<(main|section|article|header|footer|nav)\b/i', $html ) || stripos( $html, 'elementor-section' ) !== false ) {
                    $stats['html_widget_layout_risks']++;
                }
            }

            if ( $widget_type === 'heading' && trim( (string) ( $settings['title'] ?? '' ) ) === '' ) {
                $stats['empty_heading_widgets']++;
            }

            if ( $widget_type === 'text-editor' && trim( wp_strip_all_tags( (string) ( $settings['editor'] ?? '' ) ) ) === '' ) {
                $stats['empty_text_widgets']++;
            }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_collect_elementor_audit_stats( $element['elements'], $stats );
        }
    }
}

function wpae_collect_design_system_stats( array $elements, array &$stats, int $depth = 0 ): void {
    $tokens = wpae_get_project_design_tokens();
    $palette = array_map( 'strtolower', array_values( (array) ( $tokens['palette'] ?? [] ) ) );
    $required_classes = wpae_get_design_system_required_classes( $tokens );

    if ( ! isset( $stats['design_system_marked_top_level_containers'] ) ) {
        $stats['design_system_marked_top_level_containers'] = 0;
        $stats['mismatched_design_system_top_level_containers'] = 0;
        $stats['mismatched_design_system_classes'] = [];
        $stats['top_level_containers'] = 0;
        $stats['token_color_hits'] = 0;
        $stats['off_palette_color_count'] = 0;
        $stats['off_palette_colors'] = [];
    }

    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $el_type = (string) ( $element['elType'] ?? '' );

        if ( $depth === 0 && $el_type === 'container' ) {
            $stats['top_level_containers']++;
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $classes = is_array( $classes ) ? $classes : [];
            $has_all = true;
            foreach ( $required_classes as $required_class ) {
                if ( ! in_array( $required_class, $classes, true ) ) {
                    $has_all = false;
                    break;
                }
            }
            if ( $has_all ) {
                $stats['design_system_marked_top_level_containers']++;
            }

            foreach ( $classes as $class ) {
                if ( strpos( (string) $class, 'wpae-system-' ) === 0 && ! in_array( $class, $required_classes, true ) ) {
                    $stats['mismatched_design_system_top_level_containers']++;
                    $stats['mismatched_design_system_classes'][ (string) $class ] = true;
                }
            }
        }

        foreach ( $settings as $setting_value ) {
            if ( ! is_string( $setting_value ) ) {
                continue;
            }
            if ( preg_match_all( '/#[0-9a-f]{3,8}\b/i', $setting_value, $matches ) ) {
                foreach ( $matches[0] as $color ) {
                    $color = strtolower( $color );
                    if ( in_array( $color, $palette, true ) ) {
                        $stats['token_color_hits']++;
                    } else {
                        $stats['off_palette_colors'][ $color ] = true;
                    }
                }
            }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_collect_design_system_stats( $element['elements'], $stats, $depth + 1 );
        }
    }

    $stats['off_palette_color_count'] = count( (array) ( $stats['off_palette_colors'] ?? [] ) );
}

function wpae_finalize_design_system_stats( array &$stats ): void {
    if ( isset( $stats['off_palette_colors'] ) && is_array( $stats['off_palette_colors'] ) ) {
        $stats['off_palette_colors'] = array_keys( $stats['off_palette_colors'] );
    }

    if ( isset( $stats['mismatched_design_system_classes'] ) && is_array( $stats['mismatched_design_system_classes'] ) ) {
        $stats['mismatched_design_system_classes'] = array_keys( $stats['mismatched_design_system_classes'] );
    }
}

function wpae_validate_design_system_contract( array $elementor_data ): array {
    $stats = [];
    wpae_collect_design_system_stats( $elementor_data, $stats );
    wpae_finalize_design_system_stats( $stats );

    $errors = [];
    $top_level = (int) ( $stats['top_level_containers'] ?? 0 );
    $marked = (int) ( $stats['design_system_marked_top_level_containers'] ?? 0 );

    if ( $top_level <= 0 ) {
        $errors[] = 'Design system contract requires at least one top-level Flexbox Container.';
    } elseif ( $marked < $top_level ) {
        $required = implode( ' ', wpae_get_design_system_required_classes() );
        $mismatched = (array) ( $stats['mismatched_design_system_classes'] ?? [] );
        if ( ! empty( $mismatched ) ) {
            $errors[] = 'This page uses old design-system marker classes (' . implode( ', ', $mismatched ) . '). Run /elementor/normalize to migrate top-level containers to the current required_root_classes before /elementor/update dry_run: ' . $required . '.';
        } else {
            $errors[] = 'Every new page/block top-level container must include design-system classes in settings._css_classes: ' . $required . '. Run /elementor/normalize to add them before /elementor/update dry_run.';
        }
    }

    if ( (int) ( $stats['token_color_hits'] ?? 0 ) <= 0 ) {
        $errors[] = 'Design system contract requires using project palette tokens in native Elementor color/background settings.';
    }

    return [
        'ok' => empty( $errors ),
        'errors' => $errors,
        'stats' => $stats,
        'design_system' => wpae_build_project_design_system(),
    ];
}

function wpae_ratio( int $part, int $total ): float {
    if ( $total <= 0 ) {
        return 0.0;
    }

    return round( $part / $total, 3 );
}

function wpae_visual_audit_add_check( array &$checks, string $code, string $status, int $points, int $max, string $message, array $details = [], string $recommendation = '' ): void {
    $check = [
        'code' => $code,
        'status' => $status,
        'points' => $points,
        'max' => $max,
        'message' => $message,
        'details' => $details,
    ];

    if ( $recommendation !== '' ) {
        $check['recommendation'] = $recommendation;
    }

    $checks[] = $check;
}

function wpae_repeated_agent_error_add_check( array &$checks, string $code, string $status, string $message, array $details = [], string $fix = '' ): void {
    $check = [
        'code' => $code,
        'status' => $status,
        'message' => $message,
        'details' => $details,
    ];

    if ( $fix !== '' ) {
        $check['fix'] = $fix;
    }

    $checks[] = $check;
}

function wpae_build_repeated_agent_error_audit( array $elementor_data, array $validation_errors = [], array $stats = [] ): array {
    if ( empty( $stats ) ) {
        $stats = wpae_default_elementor_audit_stats();
        wpae_collect_elementor_audit_stats( $elementor_data, $stats );
        wpae_collect_elementor_design_quality_stats( $elementor_data, $stats );
        wpae_finalize_elementor_audit_stats( $stats );
    }

    if ( empty( $validation_errors ) ) {
        $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    }

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    $error_counts = wpae_count_elementor_validation_errors_by_type( $validation_errors );
    $checks = [];

    wpae_repeated_agent_error_add_check(
        $checks,
        'legacy_sections_columns',
        (int) ( $error_counts['legacy_layout'] ?? 0 ) === 0 ? 'pass' : 'fail',
        (int) ( $error_counts['legacy_layout'] ?? 0 ) === 0 ? 'No legacy section/column elements detected.' : 'Legacy Elementor section/column elements detected.',
        [ 'count' => (int) ( $error_counts['legacy_layout'] ?? 0 ) ],
        'Run /elementor/normalize and rebuild layout as native Flexbox Containers only.'
    );

    wpae_repeated_agent_error_add_check(
        $checks,
        'snake_case_widget_type',
        (int) ( $error_counts['widget_type'] ?? 0 ) === 0 ? 'pass' : 'fail',
        (int) ( $error_counts['widget_type'] ?? 0 ) === 0 ? 'Widget metadata uses camelCase widgetType.' : 'Some widgets have widgetType/widget_type metadata errors.',
        [ 'count' => (int) ( $error_counts['widget_type'] ?? 0 ) ],
        'Use widgetType only; never use widget_type.'
    );

    wpae_repeated_agent_error_add_check(
        $checks,
        'html_widget_layout_or_content',
        (int) ( $stats['html_widget_layout_risks'] ?? 0 ) === 0 ? 'pass' : 'warn',
        (int) ( $stats['html_widget_layout_risks'] ?? 0 ) === 0 ? 'HTML widgets are not acting as main layout/content.' : 'HTML widgets may contain main layout or content markup.',
        [
            'html_widgets' => (int) ( $stats['html_widgets'] ?? 0 ),
            'layout_risks' => (int) ( $stats['html_widget_layout_risks'] ?? 0 ),
        ],
        'Move content into native heading/text/button/list widgets and containers; keep HTML widgets enhancement-only.'
    );

    wpae_repeated_agent_error_add_check(
        $checks,
        'script_injected_native_css',
        (int) ( $stats['html_widgets_with_script_injected_native_css'] ?? 0 ) === 0 ? 'pass' : 'fail',
        (int) ( $stats['html_widgets_with_script_injected_native_css'] ?? 0 ) === 0 ? 'No script-injected CSS for native properties detected.' : 'HTML widgets inject CSS for native Elementor properties.',
        [ 'html_widgets_with_script_injected_native_css' => (int) ( $stats['html_widgets_with_script_injected_native_css'] ?? 0 ) ],
        'Migrate mapped CSS properties to native Elementor settings and remove script-injected style declarations.'
    );

    wpae_repeated_agent_error_add_check(
        $checks,
        'heading_typography_important_override',
        (int) ( $stats['html_widgets_with_heading_typography_important'] ?? 0 ) === 0 ? 'pass' : 'fail',
        (int) ( $stats['html_widgets_with_heading_typography_important'] ?? 0 ) === 0 ? 'No HTML widget !important heading typography override detected.' : 'HTML widget CSS overrides native heading typography with !important.',
        [ 'html_widgets_with_heading_typography_important' => (int) ( $stats['html_widgets_with_heading_typography_important'] ?? 0 ) ],
        'Use /elementor/resolve-typography-overrides with dry_run=true and explicit native_typography_patches.'
    );

    $heading_count = max( 1, (int) ( $stats['heading_widgets'] ?? 0 ) );
    $local_heading_typography_ratio = wpae_ratio( (int) ( $stats['heading_widgets_with_local_typography'] ?? 0 ), $heading_count );
    wpae_repeated_agent_error_add_check(
        $checks,
        'excessive_local_typography_overrides',
        $local_heading_typography_ratio <= 0.6 ? 'pass' : 'warn',
        $local_heading_typography_ratio <= 0.6 ? 'Local heading typography overrides are within a reasonable range.' : 'Most heading widgets have local typography overrides; global Elementor typography may be hard to edit.',
        [
            'heading_widgets' => (int) ( $stats['heading_widgets'] ?? 0 ),
            'heading_widgets_with_local_typography' => (int) ( $stats['heading_widgets_with_local_typography'] ?? 0 ),
            'ratio' => $local_heading_typography_ratio,
        ],
        'If the owner needs global typography control, run /elementor/typography-unlock dry_run=true and preserve intentional exceptions.'
    );

    wpae_repeated_agent_error_add_check(
        $checks,
        'design_system_marker_drift',
        ! empty( $design_system_contract['ok'] ) ? 'pass' : 'fail',
        ! empty( $design_system_contract['ok'] ) ? 'Design-system markers and palette usage are current.' : 'Design-system marker or palette contract is failing.',
        [
            'errors' => $design_system_contract['errors'] ?? [],
            'stats' => $design_system_contract['stats'] ?? [],
        ],
        'Run /elementor/normalize to migrate old wpae-system-* markers, then /elementor/update dry_run.'
    );

    $unit_risks = (int) ( $stats['fixed_width_risk_count'] ?? 0 ) + (int) ( $stats['fixed_height_risk_count'] ?? 0 );
    wpae_repeated_agent_error_add_check(
        $checks,
        'fixed_px_layout_risk',
        $unit_risks === 0 ? 'pass' : 'warn',
        $unit_risks === 0 ? 'No obvious fixed px width/height layout risks in Elementor settings.' : 'Fixed px width/height settings may harm mobile responsiveness.',
        [
            'fixed_width_risk_count' => (int) ( $stats['fixed_width_risk_count'] ?? 0 ),
            'fixed_height_risk_count' => (int) ( $stats['fixed_height_risk_count'] ?? 0 ),
        ],
        'Prefer rem/em for spacing/type, vh/svh for viewport sections, and %/flex/max-width for widths.'
    );

    $failures = array_values( array_filter( $checks, fn( array $check ): bool => ( $check['status'] ?? '' ) === 'fail' ) );
    $warnings = array_values( array_filter( $checks, fn( array $check ): bool => ( $check['status'] ?? '' ) === 'warn' ) );

    return [
        'ok' => empty( $failures ),
        'level' => ! empty( $failures ) ? 'blocked' : ( ! empty( $warnings ) ? 'needs_attention' : 'clean' ),
        'failure_count' => count( $failures ),
        'warning_count' => count( $warnings ),
        'checks' => $checks,
        'next_fixes' => array_values( array_filter( array_map( fn( array $check ): string => (string) ( $check['status'] ?? '' ) !== 'pass' ? (string) ( $check['fix'] ?? '' ) : '', $checks ) ) ),
    ];
}

function wpae_build_elementor_visual_audit( array $elementor_data, array $context = [] ): array {
    $stats = wpae_default_elementor_audit_stats();
    wpae_collect_elementor_audit_stats( $elementor_data, $stats );
    wpae_collect_elementor_design_quality_stats( $elementor_data, $stats );
    wpae_finalize_elementor_audit_stats( $stats );

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    $error_counts = wpae_count_elementor_validation_errors_by_type( $validation_errors );
    $repeated_agent_error_audit = wpae_build_repeated_agent_error_audit( $elementor_data, $validation_errors, $stats );
    $checks = [];
    $container_count = (int) ( $stats['containers'] ?? 0 );
    $widget_count = (int) ( $stats['widgets'] ?? 0 );
    $background_ratio = wpae_ratio( (int) ( $stats['containers_with_native_background'] ?? 0 ), max( 1, $container_count ) );
    $spacing_ratio = min(
        wpae_ratio( (int) ( $stats['containers_with_padding'] ?? 0 ), max( 1, $container_count ) ),
        wpae_ratio( (int) ( $stats['containers_with_gap'] ?? 0 ), max( 1, $container_count ) )
    );
    $text_color_sources = (int) ( $stats['containers_with_native_text_color'] ?? 0 ) + (int) ( $stats['widgets_with_native_text_color'] ?? 0 );
    $text_color_ratio = wpae_ratio( $text_color_sources, max( 1, $widget_count + $container_count ) );

    wpae_visual_audit_add_check(
        $checks,
        'runtime_elementor_contract',
        empty( $validation_errors ) ? 'pass' : 'fail',
        empty( $validation_errors ) ? 20 : 0,
        20,
        empty( $validation_errors ) ? 'Elementor data matches the required Flexbox Container contract.' : 'Elementor data violates the required Flexbox Container contract.',
        [ 'errors' => $validation_errors, 'error_counts' => $error_counts ],
        'Run /elementor/normalize, then /elementor/validate before writing.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'design_system_contract',
        $design_system_contract['ok'] ? 'pass' : 'fail',
        $design_system_contract['ok'] ? 18 : 0,
        18,
        $design_system_contract['ok'] ? 'Elementor data follows the project design-system contract.' : 'Elementor data is missing required design-system markers or token usage.',
        [
            'errors' => $design_system_contract['errors'],
            'stats' => $design_system_contract['stats'],
            'required_root_classes' => $design_system_contract['design_system']['required_root_classes'] ?? [],
        ],
        'Call /elementor/design-system first, then add required_root_classes to every top-level page/block container and use project palette tokens.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'repeated_agent_errors',
        ! empty( $repeated_agent_error_audit['ok'] ) ? ( (int) ( $repeated_agent_error_audit['warning_count'] ?? 0 ) > 0 ? 'warn' : 'pass' ) : 'fail',
        ! empty( $repeated_agent_error_audit['ok'] ) ? ( (int) ( $repeated_agent_error_audit['warning_count'] ?? 0 ) > 0 ? 6 : 10 ) : 0,
        10,
        ! empty( $repeated_agent_error_audit['ok'] ) ? 'Repeated external-agent error checks passed or only need attention.' : 'Repeated external-agent error checks found blocking issues.',
        [
            'level' => $repeated_agent_error_audit['level'] ?? 'unknown',
            'failure_count' => (int) ( $repeated_agent_error_audit['failure_count'] ?? 0 ),
            'warning_count' => (int) ( $repeated_agent_error_audit['warning_count'] ?? 0 ),
        ],
        'Read repeated_agent_error_audit.next_fixes and correct every failed check before saving or claiming completion.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'native_background_coverage',
        $background_ratio >= 0.35 ? 'pass' : ( $background_ratio > 0 ? 'warn' : 'warn' ),
        $background_ratio >= 0.35 ? 12 : ( $background_ratio > 0 ? 7 : 3 ),
        12,
        $background_ratio >= 0.35 ? 'Native container backgrounds are detectable.' : 'Native container background coverage is sparse.',
        [
            'containers' => $container_count,
            'containers_with_native_background' => (int) ( $stats['containers_with_native_background'] ?? 0 ),
            'coverage' => $background_ratio,
        ],
        'Put section/card backgrounds into native Elementor container settings first; CSS may reinforce them.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'native_text_color_coverage',
        $text_color_ratio >= 0.15 ? 'pass' : 'warn',
        $text_color_ratio >= 0.15 ? 10 : 5,
        10,
        $text_color_ratio >= 0.15 ? 'Native text color settings are detectable.' : 'Text color settings look too dependent on inherited theme/CSS state.',
        [
            'containers_with_native_text_color' => (int) ( $stats['containers_with_native_text_color'] ?? 0 ),
            'widgets_with_native_text_color' => (int) ( $stats['widgets_with_native_text_color'] ?? 0 ),
            'coverage' => $text_color_ratio,
        ],
        'Set readable title/text/button colors in native Elementor settings for dark or colored areas.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'native_spacing_coverage',
        $spacing_ratio >= 0.45 ? 'pass' : ( $spacing_ratio >= 0.2 ? 'warn' : 'warn' ),
        $spacing_ratio >= 0.45 ? 12 : ( $spacing_ratio >= 0.2 ? 7 : 3 ),
        12,
        $spacing_ratio >= 0.45 ? 'Native padding and gap settings are used consistently.' : 'Native padding/gap coverage is weak.',
        [
            'containers_with_padding' => (int) ( $stats['containers_with_padding'] ?? 0 ),
            'containers_with_gap' => (int) ( $stats['containers_with_gap'] ?? 0 ),
            'coverage' => $spacing_ratio,
        ],
        'Use container padding/gap settings for section rhythm instead of CSS-only spacing.'
    );

    $has_hierarchy = (int) ( $stats['heading_widgets'] ?? 0 ) >= 2
        && ( (int) ( $stats['h1_headings'] ?? 0 ) > 0 || (int) ( $stats['h2_h3_headings'] ?? 0 ) > 0 );
    wpae_visual_audit_add_check(
        $checks,
        'typography_hierarchy',
        $has_hierarchy ? 'pass' : 'warn',
        $has_hierarchy ? 10 : 5,
        10,
        $has_hierarchy ? 'Native heading hierarchy is visible in Elementor data.' : 'Heading hierarchy is weak.',
        [
            'heading_widgets' => (int) ( $stats['heading_widgets'] ?? 0 ),
            'h1_headings' => (int) ( $stats['h1_headings'] ?? 0 ),
            'h2_h3_headings' => (int) ( $stats['h2_h3_headings'] ?? 0 ),
        ],
        'Use native heading widgets with explicit H1/H2/H3 roles.'
    );

    $has_cta = (int) ( $stats['button_widgets'] ?? 0 ) > 0;
    wpae_visual_audit_add_check(
        $checks,
        'native_cta',
        $has_cta ? 'pass' : 'warn',
        $has_cta ? 8 : 3,
        8,
        $has_cta ? 'Native Elementor button CTA is present.' : 'No native Elementor button CTA detected.',
        [
            'button_widgets' => (int) ( $stats['button_widgets'] ?? 0 ),
            'button_widgets_with_native_style' => (int) ( $stats['button_widgets_with_native_style'] ?? 0 ),
        ],
        'Use a native button widget for the primary action and style it in button settings.'
    );

    $responsive_count = (int) ( $stats['elements_with_responsive_settings'] ?? 0 );
    wpae_visual_audit_add_check(
        $checks,
        'responsive_settings',
        $responsive_count > 0 ? 'pass' : 'warn',
        $responsive_count > 0 ? 8 : 4,
        8,
        $responsive_count > 0 ? 'Responsive Elementor settings are present.' : 'No responsive Elementor settings detected.',
        [ 'elements_with_responsive_settings' => $responsive_count ],
        'Add mobile/tablet Elementor settings for complex split, grid, and hero layouts.'
    );

    $relative_units = (int) ( $stats['relative_unit_count'] ?? 0 ) + (int) ( $stats['percent_unit_count'] ?? 0 ) + (int) ( $stats['viewport_unit_count'] ?? 0 );
    $px_units = (int) ( $stats['px_unit_count'] ?? 0 );
    $unit_ok = $relative_units > 0 && ( (int) ( $stats['fixed_width_risk_count'] ?? 0 ) + (int) ( $stats['fixed_height_risk_count'] ?? 0 ) ) === 0;
    wpae_visual_audit_add_check(
        $checks,
        'responsive_unit_policy',
        $unit_ok ? 'pass' : 'warn',
        $unit_ok ? 8 : 4,
        8,
        $unit_ok ? 'Responsive-friendly units are used in Elementor settings.' : 'Elementor settings rely too much on fixed px units.',
        [
            'px_unit_count' => $px_units,
            'relative_unit_count' => (int) ( $stats['relative_unit_count'] ?? 0 ),
            'percent_unit_count' => (int) ( $stats['percent_unit_count'] ?? 0 ),
            'viewport_unit_count' => (int) ( $stats['viewport_unit_count'] ?? 0 ),
            'fixed_width_risk_count' => (int) ( $stats['fixed_width_risk_count'] ?? 0 ),
            'fixed_height_risk_count' => (int) ( $stats['fixed_height_risk_count'] ?? 0 ),
        ],
        'Prefer rem/em for spacing and type, vh/svh for viewport-height sections, and percentages/flex constraints for widths; keep px for tiny controls, borders, and compatibility exceptions.'
    );

    $html_layout_risks = (int) ( $stats['html_widget_layout_risks'] ?? 0 );
    wpae_visual_audit_add_check(
        $checks,
        'html_widget_scope',
        $html_layout_risks === 0 ? 'pass' : 'warn',
        $html_layout_risks === 0 ? 8 : 3,
        8,
        $html_layout_risks === 0 ? 'HTML widgets look enhancement-only.' : 'HTML widgets may contain page layout/content markup.',
        [
            'html_widgets' => (int) ( $stats['html_widgets'] ?? 0 ),
            'html_widget_layout_risks' => $html_layout_risks,
        ],
        'Keep HTML widgets limited to scoped CSS/JS enhancements, not content or layout.'
    );

    $empty_content = (int) ( $stats['empty_heading_widgets'] ?? 0 ) + (int) ( $stats['empty_text_widgets'] ?? 0 );
    wpae_visual_audit_add_check(
        $checks,
        'native_content_complete',
        $empty_content === 0 ? 'pass' : 'warn',
        $empty_content === 0 ? 8 : 3,
        8,
        $empty_content === 0 ? 'No empty native heading/text widgets detected.' : 'Some native heading/text widgets are empty.',
        [
            'empty_heading_widgets' => (int) ( $stats['empty_heading_widgets'] ?? 0 ),
            'empty_text_widgets' => (int) ( $stats['empty_text_widgets'] ?? 0 ),
        ],
        'Move real copy into heading/text-editor/button settings and remove empty placeholders.'
    );

    $color_count = (int) ( $stats['unique_color_count'] ?? 0 );
    $palette_ok = $color_count >= 3 && $color_count <= 12;
    wpae_visual_audit_add_check(
        $checks,
        'palette_variety',
        $palette_ok ? 'pass' : 'warn',
        $palette_ok ? 7 : 3,
        7,
        $palette_ok ? 'Palette variety is detectable.' : 'Palette looks too sparse or too noisy from native settings.',
        [ 'unique_color_count' => $color_count ],
        'Use the project design tokens and put key colors into native settings.'
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
        'visual_audit_version' => 'v01.02.00',
        'score' => $score,
        'level' => $level,
        'points' => $points,
        'max_points' => $max,
        'context' => $context,
        'stats' => $stats,
        'design_system' => $design_system_contract['design_system'],
        'repeated_agent_error_audit' => $repeated_agent_error_audit,
        'checks' => $checks,
        'recommendations' => array_values( array_unique( $recommendations ) ),
        'contract' => [
            'layout' => 'Only native Elementor Flexbox Containers are allowed.',
            'critical_visuals' => 'Backgrounds, readable text colors, borders, spacing, dimensions, and CTA styling should be present in native Elementor settings first.',
            'html_widget' => 'Allowed only for scoped CSS/JS enhancement, not as main layout or content.',
        ],
    ];
}

function wpae_is_landing_page_request( WP_REST_Request $request, string $template = '' ): bool {
    foreach ( [ 'page_kind', 'page_type', 'intent', 'goal', 'style', 'title', 'slug' ] as $param ) {
        $value = strtolower( (string) $request->get_param( $param ) );
        if ( preg_match( '/landing|лендинг|service page|страница услуги|sales|продаж/i', $value ) ) {
            return true;
        }
    }

    return $template === 'elementor_canvas' && (string) $request->get_route() === '/ai-executor/v1/elementor/page';
}

function wpae_preflight_add_check( array &$checks, string $code, string $status, string $message, array $details = [], string $fix = '' ): void {
    $check = [
        'code' => $code,
        'status' => $status,
        'message' => $message,
        'details' => $details,
    ];

    if ( $fix !== '' ) {
        $check['fix'] = $fix;
    }

    $checks[] = $check;
}

function wpae_build_elementor_preflight( array $elementor_data, WP_REST_Request $request, array $context = [] ): array {
    $template_value = $context['template'] ?? $request->get_param( 'template' );
    if ( $template_value === null || $template_value === '' ) {
        $template_value = 'elementor_canvas';
    }
    $template = sanitize_key( (string) $template_value );
    $is_landing = wpae_is_landing_page_request( $request, $template );
    $visual_audit = wpae_build_elementor_visual_audit( $elementor_data, array_merge( $context, [
        'source' => 'preflight',
        'landing_page_required' => $is_landing,
    ] ) );
    $stats = (array) ( $visual_audit['stats'] ?? [] );
    $checks = [];

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    wpae_preflight_add_check(
        $checks,
        'elementor_contract',
        empty( $validation_errors ) ? 'pass' : 'fail',
        empty( $validation_errors ) ? 'Elementor contract is valid.' : 'Elementor contract has blocking errors.',
        [ 'errors' => $validation_errors ],
        'Run /elementor/normalize and /elementor/validate before writing.'
    );

    $design_system = wpae_validate_design_system_contract( $elementor_data );
    wpae_preflight_add_check(
        $checks,
        'design_system_contract',
        $design_system['ok'] ? 'pass' : 'fail',
        $design_system['ok'] ? 'Design-system markers and token usage are present.' : 'Design-system contract is missing required markers or token usage.',
        $design_system,
        'Call /elementor/design-system first, add required_root_classes to top-level containers, and use palette tokens in native settings.'
    );

    $empty_content = (int) ( $stats['empty_heading_widgets'] ?? 0 ) + (int) ( $stats['empty_text_widgets'] ?? 0 );
    wpae_preflight_add_check(
        $checks,
        'native_content_complete',
        $empty_content === 0 ? 'pass' : 'fail',
        $empty_content === 0 ? 'Native heading/text widgets are populated.' : 'Native heading/text widgets include empty content.',
        [
            'empty_heading_widgets' => (int) ( $stats['empty_heading_widgets'] ?? 0 ),
            'empty_text_widgets' => (int) ( $stats['empty_text_widgets'] ?? 0 ),
        ],
        'Fill all heading settings.title and text-editor settings.editor values before writing.'
    );

    $html_layout_risks = (int) ( $stats['html_widget_layout_risks'] ?? 0 );
    wpae_preflight_add_check(
        $checks,
        'html_widget_scope',
        $html_layout_risks === 0 ? 'pass' : 'fail',
        $html_layout_risks === 0 ? 'HTML widgets are enhancement-only.' : 'HTML widgets appear to contain layout/content markup.',
        [
            'html_widgets' => (int) ( $stats['html_widgets'] ?? 0 ),
            'html_widget_layout_risks' => $html_layout_risks,
        ],
        'Move content/layout into native Elementor containers and widgets; keep HTML widgets only for scoped CSS/JS.'
    );

    $has_cta = (int) ( $stats['button_widgets'] ?? 0 ) > 0;
    wpae_preflight_add_check(
        $checks,
        'landing_page_cta',
        ( ! $is_landing || $has_cta ) ? 'pass' : 'fail',
        ( ! $is_landing || $has_cta ) ? 'CTA requirement is satisfied.' : 'Landing pages require at least one native Elementor button CTA.',
        [
            'landing_page_required' => $is_landing,
            'button_widgets' => (int) ( $stats['button_widgets'] ?? 0 ),
        ],
        'Add a native button widget with clear text and link settings.'
    );

    $has_native_visuals = (int) ( $stats['containers_with_native_background'] ?? 0 ) > 0
        && ( (int) ( $stats['containers_with_native_text_color'] ?? 0 ) + (int) ( $stats['widgets_with_native_text_color'] ?? 0 ) ) > 0
        && (int) ( $stats['containers_with_padding'] ?? 0 ) > 0
        && (int) ( $stats['containers_with_gap'] ?? 0 ) > 0;
    wpae_preflight_add_check(
        $checks,
        'native_critical_visual_settings',
        ( ! $is_landing || $has_native_visuals ) ? 'pass' : 'fail',
        ( ! $is_landing || $has_native_visuals ) ? 'Native critical visual settings are sufficient.' : 'Landing pages require native backgrounds, readable text colors, padding, and gaps before write.',
        [
            'landing_page_required' => $is_landing,
            'containers_with_native_background' => (int) ( $stats['containers_with_native_background'] ?? 0 ),
            'containers_with_native_text_color' => (int) ( $stats['containers_with_native_text_color'] ?? 0 ),
            'widgets_with_native_text_color' => (int) ( $stats['widgets_with_native_text_color'] ?? 0 ),
            'containers_with_padding' => (int) ( $stats['containers_with_padding'] ?? 0 ),
            'containers_with_gap' => (int) ( $stats['containers_with_gap'] ?? 0 ),
        ],
        'Set background, color, padding, and gap in native Elementor settings first; CSS may only reinforce them.'
    );

    $unit_risks = (int) ( $stats['fixed_width_risk_count'] ?? 0 ) + (int) ( $stats['fixed_height_risk_count'] ?? 0 );
    wpae_preflight_add_check(
        $checks,
        'responsive_unit_policy',
        $unit_risks === 0 ? 'pass' : 'warn',
        $unit_risks === 0 ? 'No risky fixed px width/height settings were detected.' : 'Fixed px width/height settings may harm responsiveness.',
        [
            'px_unit_count' => (int) ( $stats['px_unit_count'] ?? 0 ),
            'relative_unit_count' => (int) ( $stats['relative_unit_count'] ?? 0 ),
            'percent_unit_count' => (int) ( $stats['percent_unit_count'] ?? 0 ),
            'viewport_unit_count' => (int) ( $stats['viewport_unit_count'] ?? 0 ),
            'fixed_width_risk_count' => (int) ( $stats['fixed_width_risk_count'] ?? 0 ),
            'fixed_height_risk_count' => (int) ( $stats['fixed_height_risk_count'] ?? 0 ),
        ],
        'Use rem/em where practical, vh/svh for viewport-height sections, and %/flex/max-width for widths. Keep px only for small exceptions.'
    );

    $blocking = [];
    $warnings = [];
    $fixes = [];
    foreach ( $checks as $check ) {
        if ( ( $check['status'] ?? '' ) === 'fail' ) {
            $blocking[] = $check['message'];
        } elseif ( ( $check['status'] ?? '' ) === 'warn' ) {
            $warnings[] = $check['message'];
        }
        if ( ( $check['status'] ?? '' ) !== 'pass' && ! empty( $check['fix'] ) ) {
            $fixes[] = $check['fix'];
        }
    }

    return [
        'ok' => empty( $blocking ),
        'preflight_version' => 'v01.00.00',
        'landing_page_required' => $is_landing,
        'blocking_errors' => array_values( array_unique( $blocking ) ),
        'warnings' => array_values( array_unique( $warnings ) ),
        'fixes' => array_values( array_unique( $fixes ) ),
        'checks' => $checks,
        'visual_audit_summary' => [
            'score' => (int) ( $visual_audit['score'] ?? 0 ),
            'level' => (string) ( $visual_audit['level'] ?? '' ),
            'recommendations' => (array) ( $visual_audit['recommendations'] ?? [] ),
        ],
    ];
}

function wpae_build_after_save_quality_summary( int $post_id, array $elementor_data, array $preflight = [] ): array {
    $visual_audit = wpae_build_elementor_visual_audit( $elementor_data, [
        'source' => 'after_save',
        'post_id' => $post_id,
        'post_status' => get_post_status( $post_id ),
        'url' => get_permalink( $post_id ),
    ] );

    $fixes = array_values( array_unique( array_merge(
        (array) ( $preflight['fixes'] ?? [] ),
        (array) ( $visual_audit['recommendations'] ?? [] )
    ) ) );

    return [
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'status' => get_post_status( $post_id ),
        'visual_audit' => [
            'score' => (int) ( $visual_audit['score'] ?? 0 ),
            'level' => (string) ( $visual_audit['level'] ?? '' ),
            'warnings' => array_values( array_filter( array_map(
                static function ( $check ) {
                    return is_array( $check ) && ( $check['status'] ?? '' ) !== 'pass'
                        ? (string) ( $check['message'] ?? '' )
                        : '';
                },
                (array) ( $visual_audit['checks'] ?? [] )
            ) ) ),
        ],
        'preflight' => [
            'ok' => (bool) ( $preflight['ok'] ?? false ),
            'warnings' => (array) ( $preflight['warnings'] ?? [] ),
            'blocking_errors' => (array) ( $preflight['blocking_errors'] ?? [] ),
        ],
        'fixes' => $fixes,
        'completion_rule' => 'If visual_audit.level is weak/blocked or fixes are present, the agent must correct them before claiming the page is finished.',
    ];
}

function wpae_audit( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $findings = [];

    if ( $post_id <= 0 ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'post_id is required.' ], 400 );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Post not found.' ], 404 );
    }

    wpae_audit_add_finding(
        $findings,
        'post_exists',
        'pass',
        'Post exists.',
        [ 'post_id' => $post_id, 'post_type' => $post->post_type, 'post_status' => $post->post_status ]
    );

    $edit_mode = (string) get_post_meta( $post_id, '_elementor_edit_mode', true );
    wpae_audit_add_finding(
        $findings,
        'elementor_edit_mode',
        $edit_mode === 'builder' ? 'pass' : 'fail',
        $edit_mode === 'builder' ? 'Elementor edit mode is builder.' : 'Elementor edit mode is not builder.',
        [ 'value' => $edit_mode ]
    );

    $template = (string) get_post_meta( $post_id, '_wp_page_template', true );
    wpae_audit_add_finding(
        $findings,
        'page_template',
        $template !== '' ? 'pass' : 'warn',
        $template !== '' ? 'Page template is set.' : 'Page template is empty.',
        [ 'value' => $template ]
    );

    $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
    if ( $raw_data === '' ) {
        wpae_audit_add_finding( $findings, 'elementor_data_present', 'fail', '_elementor_data is empty.' );
        return new WP_REST_Response( [
            'ok' => false,
            'post_id' => $post_id,
            'findings' => $findings,
        ], 422 );
    }

    $elementor_data = json_decode( $raw_data, true );
    if ( ! is_array( $elementor_data ) ) {
        wpae_audit_add_finding( $findings, 'elementor_data_json', 'fail', '_elementor_data is not valid JSON array data.' );
        return new WP_REST_Response( [
            'ok' => false,
            'post_id' => $post_id,
            'findings' => $findings,
        ], 422 );
    }

    wpae_audit_add_finding( $findings, 'elementor_data_json', 'pass', '_elementor_data decodes as JSON array data.' );

    $validation_errors = wpae_validate_elementor_data_string( $raw_data );
    wpae_audit_add_finding(
        $findings,
        'elementor_policy_validation',
        empty( $validation_errors ) ? 'pass' : 'fail',
        empty( $validation_errors ) ? 'Elementor data passes runtime policy validation.' : 'Elementor data failed runtime policy validation.',
        [ 'errors' => $validation_errors ]
    );

    $stats = wpae_default_elementor_audit_stats();
    wpae_collect_elementor_audit_stats( $elementor_data, $stats );
    wpae_collect_elementor_design_quality_stats( $elementor_data, $stats );
    wpae_finalize_elementor_audit_stats( $stats );
    $visual_audit = wpae_build_elementor_visual_audit( $elementor_data, [
        'source' => 'post_meta',
        'post_id' => $post_id,
        'post_status' => get_post_status( $post_id ),
        'url' => get_permalink( $post_id ),
    ] );
    $repeated_agent_error_audit = wpae_build_repeated_agent_error_audit( $elementor_data, $validation_errors, $stats );
    $editability_audit = wpae_build_elementor_editability_audit( $elementor_data, [
        'source' => 'post_meta',
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
    ] );

    wpae_audit_add_finding(
        $findings,
        'native_containers',
        $stats['containers'] > 0 ? 'pass' : 'fail',
        $stats['containers'] > 0 ? 'Elementor layout uses containers.' : 'No Elementor containers found.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'native_backgrounds',
        $stats['containers_with_native_background'] > 0 ? 'pass' : 'warn',
        $stats['containers_with_native_background'] > 0 ? 'At least one container has native background settings.' : 'No native container background settings found; verify contrast is not CSS-only.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'html_widget_scope',
        $stats['html_widget_layout_risks'] === 0 ? 'pass' : 'warn',
        $stats['html_widget_layout_risks'] === 0 ? 'No HTML widget layout risks detected.' : 'Some HTML widgets may contain layout/content markup instead of enhancement-only CSS/JS.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'native_widget_content',
        ( $stats['empty_heading_widgets'] === 0 && $stats['empty_text_widgets'] === 0 ) ? 'pass' : 'warn',
        ( $stats['empty_heading_widgets'] === 0 && $stats['empty_text_widgets'] === 0 ) ? 'No empty heading/text widgets detected.' : 'Some native content widgets appear empty.',
        $stats
    );

    $design_quality_ok = $stats['heading_widgets'] >= 2
        && $stats['button_widgets'] > 0
        && $stats['containers_with_padding'] > 0
        && $stats['containers_with_gap'] > 0
        && $stats['unique_color_count'] >= 3
        && $stats['empty_heading_widgets'] === 0
        && $stats['empty_text_widgets'] === 0;
    wpae_audit_add_finding(
        $findings,
        'design_quality_gates',
        $design_quality_ok ? 'pass' : 'warn',
        $design_quality_ok ? 'Design quality gates passed for native Elementor structure.' : 'Design quality gates need attention: check headings, CTA, native spacing, palette, and empty content.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'elementor_visual_audit',
        in_array( $visual_audit['level'], [ 'strong', 'acceptable' ], true ) ? 'pass' : 'warn',
        'Static Elementor visual audit level: ' . $visual_audit['level'] . '.',
        [
            'score' => $visual_audit['score'],
            'level' => $visual_audit['level'],
            'recommendations' => $visual_audit['recommendations'],
        ]
    );

    wpae_audit_add_finding(
        $findings,
        'repeated_agent_error_audit',
        ! empty( $repeated_agent_error_audit['ok'] ) ? ( ( $repeated_agent_error_audit['warning_count'] ?? 0 ) > 0 ? 'warn' : 'pass' ) : 'fail',
        ! empty( $repeated_agent_error_audit['ok'] ) ? 'Repeated external-agent error checks passed or only need attention.' : 'Repeated external-agent error checks found blocking issues.',
        $repeated_agent_error_audit
    );

    wpae_audit_add_finding(
        $findings,
        'elementor_editability_audit',
        ! empty( $editability_audit['ok'] ) ? ( ( $editability_audit['warning_count'] ?? 0 ) > 0 ? 'warn' : 'pass' ) : 'fail',
        ! empty( $editability_audit['ok'] ) ? 'Elementor-supported design properties are not blocked by detected CSS/script overrides.' : 'Detected CSS/script overrides block Elementor editability for native properties.',
        [
            'score' => (int) ( $editability_audit['score'] ?? 0 ),
            'level' => (string) ( $editability_audit['level'] ?? '' ),
            'blocking_count' => (int) ( $editability_audit['blocking_count'] ?? 0 ),
            'warning_count' => (int) ( $editability_audit['warning_count'] ?? 0 ),
            'next_fixes' => (array) ( $editability_audit['next_fixes'] ?? [] ),
        ]
    );

    $has_failures = false;
    foreach ( $findings as $finding ) {
        if ( ( $finding['status'] ?? '' ) === 'fail' ) {
            $has_failures = true;
            break;
        }
    }

    return new WP_REST_Response( [
        'ok' => ! $has_failures,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'stats' => $stats,
        'visual_audit' => $visual_audit,
        'repeated_agent_error_audit' => $repeated_agent_error_audit,
        'editability_audit' => $editability_audit,
        'findings' => $findings,
    ], $has_failures ? 422 : 200 );
}
