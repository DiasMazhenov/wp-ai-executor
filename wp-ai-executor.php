<?php
/**
 * Plugin Name: WP AI Executor
 * Description: Secure REST endpoint for AI automation (Claude, GPT, Gemini, Qwen, etc.). Execute PHP in WordPress context via any AI agent.
 * Version:     v02.08.36
 * Author:      DIAS
 * License:     MIT
 */

defined( 'ABSPATH' ) || exit;

const WPAE_VERSION = 'v02.08.36';
const WPAE_ROLLBACK_TTL_SECONDS = 7200;
const WPAE_ROLLBACK_MAX_SNAPSHOTS = 20;
const WPAE_OPERATION_LOG_MAX_ENTRIES = 100;
const WPAE_EXPORT_TTL_SECONDS = 3600;
const WPAE_EXPORT_MAX_ENTRIES = 20;

require_once __DIR__ . '/includes/updates/package-updater.php';
require_once __DIR__ . '/includes/security/capabilities.php';
require_once __DIR__ . '/includes/skills/skills.php';
require_once __DIR__ . '/includes/guide/session.php';
require_once __DIR__ . '/includes/rest/routes.php';

function wpae_project_design_token_defaults(): array {
    return [
        'palette' => [
            'ink' => '#111827',
            'paper' => '#f6f0e6',
            'surface' => '#ffffff',
            'accent' => '#c75b3b',
            'support' => '#2563eb',
            'muted' => '#6b7280',
        ],
        'typography_roles' => [
            'display' => 'Large high-contrast H1/H2 headings; avoid generic template scale.',
            'body' => 'Readable native text-editor copy with 1.5-1.7 line-height.',
            'utility' => 'Small uppercase eyebrow/labels used only where they carry meaning.',
        ],
        'spacing_scale' => [
            'section_padding_desktop' => '4.5rem top/bottom',
            'section_padding_mobile' => '2rem top/bottom',
            'container_gap_desktop' => '1.5rem-2.5rem',
            'container_gap_mobile' => '1rem-1.25rem',
        ],
        'unit_policy' => [
            'spacing_and_type' => 'Prefer rem/em for spacing, typography, gap, padding, margin, border radius, icon sizing, and component dimensions when practical.',
            'height' => 'Prefer vh/svh/min-height for viewport-height sections; avoid fixed px heights unless the element is a small control or icon.',
            'width' => 'Prefer percentages, max-width, flex basis, and responsive constraints for width; avoid fixed px widths for main layout containers.',
            'allowed_px_exceptions' => 'Use px only for hairline borders, very small icons, shadows, precise editor controls, or when Elementor control compatibility requires px.',
        ],
        'radii' => [
            'cards' => '0.5rem or less unless the site design system says otherwise',
            'buttons' => 'match site style; avoid oversized pill defaults unless intentional',
        ],
        'button_style' => 'Native Elementor button widget with clear label, high contrast, and no oversized pill default unless intentional.',
        'tone_of_voice' => 'Concrete, confident, and useful; avoid filler marketing language.',
        'design_prohibitions' => [
            'No generic template gradients as the main visual idea.',
            'No legacy Elementor sections or columns.',
            'No HTML widget as main layout or content container.',
            'No CSS-only critical backgrounds, contrast, spacing, or borders.',
        ],
    ];
}

function wpae_sanitize_design_token_text( $value, int $max_length = 240 ): string {
    $value = trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
}

function wpae_sanitize_design_tokens( array $input ): array {
    $defaults = wpae_project_design_token_defaults();
    $tokens = $defaults;

    foreach ( [ 'palette', 'typography_roles', 'spacing_scale', 'radii' ] as $group ) {
        $incoming = is_array( $input[ $group ] ?? null ) ? $input[ $group ] : [];
        foreach ( $defaults[ $group ] as $key => $default ) {
            $value = $incoming[ $key ] ?? $default;
            $tokens[ $group ][ $key ] = wpae_sanitize_design_token_text( $value, $group === 'palette' ? 32 : 180 );
        }
    }

    $tokens['button_style'] = wpae_sanitize_design_token_text( $input['button_style'] ?? $defaults['button_style'], 240 );
    $tokens['tone_of_voice'] = wpae_sanitize_design_token_text( $input['tone_of_voice'] ?? $defaults['tone_of_voice'], 240 );

    $prohibitions = $input['design_prohibitions'] ?? $defaults['design_prohibitions'];
    if ( is_string( $prohibitions ) ) {
        $prohibitions = preg_split( '/\r\n|\r|\n/', $prohibitions );
    }
    if ( ! is_array( $prohibitions ) ) {
        $prohibitions = $defaults['design_prohibitions'];
    }

    $tokens['design_prohibitions'] = [];
    foreach ( $prohibitions as $item ) {
        $item = wpae_sanitize_design_token_text( $item, 180 );
        if ( $item !== '' ) {
            $tokens['design_prohibitions'][] = $item;
        }
    }
    if ( empty( $tokens['design_prohibitions'] ) ) {
        $tokens['design_prohibitions'] = $defaults['design_prohibitions'];
    }

    return $tokens;
}

function wpae_get_project_design_tokens(): array {
    $stored = get_option( 'wp_ai_executor_design_tokens', [] );
    return wpae_sanitize_design_tokens( is_array( $stored ) ? $stored : [] );
}

function wpae_update_project_design_tokens( array $input ): void {
    update_option( 'wp_ai_executor_design_tokens', wpae_sanitize_design_tokens( $input ), false );
}

function wpae_get_design_system_id( array $tokens = [] ): string {
    if ( empty( $tokens ) ) {
        $tokens = wpae_get_project_design_tokens();
    }

    return 'ds-' . substr( md5( wp_json_encode( $tokens ) ), 0, 8 );
}

function wpae_get_design_system_required_classes( array $tokens = [] ): array {
    return [ 'wpae-ds', 'wpae-system-' . wpae_get_design_system_id( $tokens ) ];
}

function wpae_build_project_design_system( array $input = [] ): array {
    $tokens = wpae_get_project_design_tokens();
    $system_id = wpae_get_design_system_id( $tokens );

    return [
        'design_system_version' => 'v01.00.00',
        'system_id' => $system_id,
        'source' => 'wp_ai_executor_design_tokens',
        'tokens' => $tokens,
        'required_root_classes' => wpae_get_design_system_required_classes( $tokens ),
        'mandatory_workflow' => [
            '1. Before creating a page or adding a page block, call /elementor/design-system.',
            '2. Treat returned tokens as the single visual source of truth.',
            '3. Design mobile first: plan mobile stacking, readable type, tap targets, CTA visibility, and responsive Elementor settings before tablet/desktop.',
            '4. Call /elementor/blueprint after the design system and keep the same system_id.',
            '5. Every top-level page or block container must include required_root_classes in settings._css_classes.',
            '6. Reuse the same palette, typography roles, spacing scale, radii, button style, and tone across all sections.',
            '7. Run /elementor/visual-audit before and after writing; fix weak/blocked style consistency results.',
        ],
        'style_contract' => [
            'single_system' => 'All new pages and new blocks must use one shared design system per site/project.',
            'no_one_off_blocks' => 'Do not invent a new palette, heading style, button style, spacing rhythm, radius, or tone for a later block unless the user explicitly changes the design system.',
            'native_settings_first' => 'Apply token colors, spacing, radii, button style, and element styling through native Elementor settings/style controls first.',
            'block_consistency' => 'A new block must look like it belongs to the existing page: same type scale, same button treatment, same card/background grammar, same spacing rhythm.',
            'allowed_variation' => 'Variation is allowed only through token roles, e.g. paper/surface/accent/support, not through unrelated colors or shapes.',
        ],
        'elementor_contract' => [
            'root_marker' => 'settings._css_classes must include all required_root_classes on each top-level page/block container.',
            'layout' => 'Flexbox Containers only.',
            'content' => 'Native editable widgets first.',
            'html_widget' => 'Enhancement-only CSS/JS; never main layout or content.',
        ],
    ];
}

function wpae_get_jezweb_claude_skills_pack(): array {
    return [
        'source' => 'https://github.com/jezweb/claude-skills',
        'source_summary' => 'Production workflow skills for Claude Code; distilled here into portable WP AI Executor rules.',
        'version' => 'distilled-2026-07-07',
        'philosophy' => [
            'Every skill must produce a tangible output, not a knowledge dump.',
            'Teach patterns and workflows; adapt implementation to the current environment.',
            'Use trigger-driven workflow selection: WordPress/Elementor, landing page, design review, palette, and responsiveness checks.',
        ],
        'relevant_skills' => [
            'wordpress-elementor' => [
                'trigger' => 'Elementor page editing, template work, content changes, widget styling, or page structure changes.',
                'adaptation' => 'In WP AI Executor, prefer structured Elementor endpoints over browser automation or WP-CLI.',
                'rules' => [
                    'Identify target page and Elementor metadata before editing.',
                    'For text-only changes, update native widget settings rather than opaque HTML.',
                    'For structural changes, use native Elementor Flexbox Containers and widgets.',
                    'Always preserve backups through rollback_snapshot_id where write endpoints provide it.',
                    'Clear Elementor CSS cache through the plugin save path; never create helper files.',
                    'Verify with /audit and /elementor/visual-audit after writes.',
                ],
            ],
            'landing-page' => [
                'trigger' => 'Landing page, marketing page, launch page, one-page site, service page.',
                'adaptation' => 'Do not generate standalone HTML. Build editable Elementor data using /elementor/design-system, /elementor/blueprint, recipes, compose, normalize, validate, visual-audit, page/update.',
                'required_sections' => [
                    'hero with clear CTA',
                    'features/services',
                    'social proof or proof points',
                    'process or offer explanation',
                    'FAQ when useful',
                    'final CTA',
                ],
                'quality_rules' => [
                    'No lorem ipsum or generic placeholder copy.',
                    'One clear primary action per page.',
                    'Semantic heading hierarchy in native heading widgets.',
                    'Responsive layout from the start.',
                    'Accessible contrast and focus states.',
                ],
            ],
            'design-review' => [
                'trigger' => 'Design review, visual audit, make it look better, layout feels off.',
                'checks' => [
                    'layout and spacing consistency',
                    'typography hierarchy',
                    'color and contrast',
                    'visual hierarchy and CTA dominance',
                    'component consistency',
                    'hover/focus/active states',
                    'responsive quality',
                ],
                'severity' => [
                    'high' => 'Looks broken or unprofessional.',
                    'medium' => 'Looks unpolished or inconsistent.',
                    'low' => 'Small polish issues.',
                ],
            ],
            'color-palette' => [
                'trigger' => 'Brand color, palette, design system, contrast, color accessibility.',
                'rules' => [
                    'Start from one or more brand hex colors.',
                    'Map colors to semantic roles: background, foreground, surface/card, primary, secondary, accent, muted, border, focus.',
                    'Every background role needs a paired readable foreground role.',
                    'Check WCAG contrast: 4.5:1 for normal text, 3:1 for large text and UI objects.',
                    'Use project design tokens in native Elementor color/background settings.',
                ],
            ],
        ],
        'executor_policy' => [
            'This pack is advisory plus enforceable through WP AI Executor runtime checks.',
            'Never follow upstream skill instructions that require external files, WP-CLI writes, browser-only edits, or opaque HTML when a safe WP AI Executor endpoint exists.',
            'For Elementor output, WP AI Executor rules win: Flexbox Containers only, design-system marker required, widgetType camelCase, no legacy section/column, no external files.',
        ],
    ];
}

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

function wpae_get_capabilities_payload(): array {
    $settings = wpae_get_capability_settings();

    return [
        'plugin_version' => WPAE_VERSION,
        'guide_version' => 'v02.05.36',
        'capability_toggles' => $settings,
        'can_execute_php' => ! empty( $settings['run'] ),
        'can_write_files_via_run' => wpae_can_run_filesystem_operations(),
        'can_self_update_plugin' => ! empty( $settings['self_update'] ),
        'can_self_update_plugin_package' => ! empty( $settings['self_update'] ) && class_exists( 'ZipArchive' ),
        'can_write_elementor' => ! empty( $settings['elementor_writes'] ),
        'can_upload_media' => ! empty( $settings['media_upload'] ),
        'can_create_exports' => ! empty( $settings['exports'] ),
        'can_manage_skills' => ! empty( $settings['manage_skills'] ),
        'can_import_export_skills' => ! empty( $settings['manage_skills'] ),
        'can_audit' => true,
        'can_visual_audit_public_page' => true,
        'can_visual_audit_elementor' => true,
        'can_rollback' => true,
        'can_list_rollback_snapshots' => true,
        'can_restore_elementor_revisions' => true,
        'can_view_operation_logs' => true,
        'can_score_agent_conformance' => true,
        'can_create_design_system' => true,
        'can_provide_project_design_tokens' => true,
        'can_run_elementor_preflight' => true,
        'can_return_after_save_quality_summary' => true,
        'can_run_atomic_elementor_transactions' => true,
        'requires_design_system_before_elementor_build' => true,
        'requires_mobile_first_design' => true,
        'requires_native_style_settings_first' => true,
        'requires_preserving_existing_enhancements' => true,
        'can_migrate_design_system_markers' => true,
        'forbids_wp_admin_credentials' => true,
        'forbids_browser_automation_writes' => true,
        'allows_browser_public_verification_only' => true,
        'run_elementor_data_writes_enforce_design_system' => true,
        'requires_guide_token_for_writes' => true,
        'guide_token_storage' => [
            'session_storage' => 'wp_options_per_session_with_legacy_index',
            'token_storage' => 'wp_options_per_token_hash',
            'ack_body_formats' => [ 'application/json', 'raw_json_body_fallback', 'form_fields' ],
        ],
        'project_design_tokens' => wpae_get_project_design_tokens(),
        'project_design_system' => wpae_build_project_design_system(),
        'embedded_jezweb_claude_skills' => wpae_get_jezweb_claude_skills_pack(),
        'visual_audit' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/visual-audit',
            'scope' => 'Read-only public HTML audit for same-site post_id or url.',
            'checks' => [
                'public fetch status',
                'viewport/title/visible copy',
                'fixed width/min-width overflow risks',
                'invisible text patterns',
                'empty Elementor/WPAE blocks',
                'CTA presence',
                'mobile-first CSS signals',
            ],
            'limitations' => [
                'No server-side screenshots.',
                'No true rendered overflow measurement.',
                'No full computed CSS contrast cascade.',
            ],
            'browser_followup_required_for' => [
                'desktop/mobile screenshots',
                'real click/tap checks',
                'rendered contrast',
                'animation behavior',
            ],
        ],
        'elementor' => [
            'enabled_for_writes' => ! empty( $settings['elementor_writes'] ),
            'safe_endpoints' => [
                'validate' => 'POST /wp-json/ai-executor/v1/elementor/validate',
                'normalize' => 'POST /wp-json/ai-executor/v1/elementor/normalize',
                'blueprint' => 'POST /wp-json/ai-executor/v1/elementor/blueprint',
                'design_system' => 'POST /wp-json/ai-executor/v1/elementor/design-system',
                'recipes' => 'GET /wp-json/ai-executor/v1/elementor/recipes',
                'recipe' => 'GET /wp-json/ai-executor/v1/elementor/recipes/{id}',
                'compose' => 'POST /wp-json/ai-executor/v1/elementor/compose',
                'visual_audit' => 'POST /wp-json/ai-executor/v1/elementor/visual-audit',
                'public_visual_audit' => 'POST /wp-json/ai-executor/v1/visual-audit',
                'revisions' => 'GET /wp-json/ai-executor/v1/elementor/revisions',
                'restore_revision' => 'POST /wp-json/ai-executor/v1/elementor/restore-revision',
                'page' => 'POST /wp-json/ai-executor/v1/elementor/page',
                'update' => 'POST /wp-json/ai-executor/v1/elementor/update',
                'patch' => 'POST /wp-json/ai-executor/v1/elementor/patch',
            ],
            'can_normalize' => true,
            'can_blueprint' => true,
            'can_use_recipes' => true,
            'can_compose_sections' => true,
            'must_use_flex_containers' => true,
            'forbidden_eltypes' => [ 'section', 'column' ],
            'required_widget_key' => 'widgetType',
            'forbidden_widget_keys' => [ 'widget_type' ],
            'runtime_validation' => true,
            'static_visual_audit' => true,
            'preflight_before_writes' => true,
            'transaction_write_mode' => [
                'mode' => 'atomic',
                'default_for' => [ '/elementor/page', '/elementor/update', '/elementor/patch' ],
                'auto_rollback_on' => [
                    'metadata_save_error',
                    'invalid_saved_elementor_json',
                    'post_save_contract_failure',
                    'cache_clear_failure',
                    'public_verification_failure_when_requested',
                    'strict_quality_failure_when_requested',
                ],
                'optional_request_flags' => [
                    'transaction_verify_public' => 'When true, fetch and audit the public permalink after save; failure triggers auto-rollback.',
                    'transaction_strict_quality' => 'When true, weak/blocked static quality after save triggers auto-rollback.',
                ],
                'response_fields' => [
                    'transaction',
                    'transaction.checks',
                    'transaction.failed_checks',
                    'transaction.auto_rollback',
                    'rollback_snapshot_id',
                    'rollback_expires_at',
                    'quality_summary',
                ],
            ],
            'protected_enhancement_zones' => [
                'enabled' => true,
                'marker' => 'wpae-protected-zone CSS class or data-wpae-protected attribute.',
                'automatic_detection' => 'HTML widgets containing WebGL, Three.js, GSAP, canvas, shader, or Babylon enhancement code.',
                'guarded_endpoints' => [ '/elementor/page', '/elementor/update', '/elementor/patch' ],
                'default' => 'Block removal or modification of an existing protected zone.',
                'explicit_override' => [
                    'allow_protected_zone_changes' => true,
                    'protected_zone_reason' => 'Concrete reason of at least 10 characters.',
                ],
            ],
            'after_save_quality_summary' => true,
            'design_system_contract_enforced_on_writes' => true,
            'design_system_marker_migration' => true,
            'mobile_first_design_required' => true,
            'native_style_settings_required' => true,
            'preserve_existing_enhancements_required' => true,
            'requires_elementor_editor_editable_design' => true,
            'native_style_policy' => [
                'required' => true,
                'rule' => 'Change element styling through native Elementor settings/style controls first; CSS is allowed only for exceptional enhancements that Elementor cannot express.',
                'principle' => 'Every design property that Elementor supports natively must be represented in widget/container settings so the site owner can edit it in Elementor. Native settings are the editable source of truth; do not remove them unless the user explicitly asks for global inheritance/design-token control.',
                'editor_editability_contract' => [
                    'Editable design means the property is stored in Elementor element settings/controls and saved in _elementor_data, not hidden only in Custom CSS, an HTML widget, an external file, or injected markup.',
                    'Do not strip native settings to make a property editable. Native widget/container settings are what makes the property editable in the Elementor panel.',
                    'Only remove local native overrides when the user explicitly asks that a property inherit from global Elementor styles/design tokens.',
                    'If a property from css_to_native_map appears in <script>-injected CSS, it is not editable in Elementor. Migrate it to native settings and remove the injected CSS declaration.',
                    'For existing pages, migrate only the requested properties; preserve unrelated working styles, classes, IDs, JS, WebGL, Three.js, GSAP, canvas, and animation code.',
                ],
                'css_to_native_map_contract' => 'Properties in css_to_native_map must be set through native Elementor settings. They must not be the design source in Custom CSS, HTML widget CSS, external CSS files, or <script>-injected CSS.',
                'css_to_native_map' => [
                    'typography' => [
                        'font-family' => 'typography_font_family',
                        'font-size' => 'typography_font_size plus typography_font_size_mobile and typography_font_size_tablet when needed',
                        'font-weight' => 'typography_font_weight',
                        'line-height' => 'typography_line_height; use unitless ratio for multi-line headings/text',
                        'letter-spacing' => 'typography_letter_spacing',
                        'text-transform' => 'typography_text_transform',
                    ],
                    'colors' => [
                        'color' => 'title_color or text_color',
                        'background-color' => 'background_background plus background_color',
                        'linear/radial gradient' => 'background_background=gradient plus background_color, background_color_b, background_gradient_type, background_gradient_angle, and background_gradient_position where supported',
                        'background overlay gradient' => 'background_overlay_background=gradient plus background_overlay_color, background_overlay_color_b, and related overlay gradient settings where supported',
                        'border-color' => 'border_border plus border_color',
                    ],
                    'advanced_positioning' => [
                        'z-index' => '_z_index or z_index control/settings when Elementor exposes it for the element',
                        'position fixed/sticky' => 'Elementor Advanced/Positioning or Motion Effects sticky settings such as _position, sticky, sticky_on, sticky_offset, sticky_effects_offset, and sticky_parent when available',
                    ],
                    'spacing_and_size' => [
                        'padding' => 'padding including responsive breakpoints',
                        'margin' => 'margin',
                        'border-radius' => 'border_radius',
                        'min-height' => 'min_height',
                    ],
                    'flex_grid' => [
                        'display:flex' => 'flex_direction, justify_content, align_items',
                        'gap' => 'gap',
                        'flex-wrap' => 'flex_wrap',
                    ],
                ],
                'native_first_settings' => [
                    'background_background',
                    'background_color',
                    'background_color_b',
                    'background_gradient_type',
                    'background_gradient_angle',
                    'background_gradient_position',
                    'background_overlay_background',
                    'background_overlay_color',
                    'background_overlay_color_b',
                    '_background_background',
                    '_background_color',
                    '_z_index',
                    'z_index',
                    '_position',
                    'sticky',
                    'sticky_on',
                    'sticky_offset',
                    'sticky_effects_offset',
                    'sticky_parent',
                    'title_color',
                    'text_color',
                    'button_text_color',
                    'button_background_color',
                    'border_border',
                    'border_color',
                    'border_width',
                    'border_radius',
                    'padding',
                    'margin',
                    'gap',
                    'width',
                    'min_height',
                    'align_items',
                    'justify_content',
                    'flex_direction',
                    'flex_wrap',
                    'typography_font_family',
                    'typography_font_size',
                    'typography_font_weight',
                    'typography_line_height',
                    'typography_letter_spacing',
                    'typography_text_transform',
                    'responsive mobile/tablet variants',
                ],
                'css_exception_rule' => 'Use scoped CSS only for animations, pseudo-elements, WebGL/canvas styling, gradients/patterns that Elementor Group_Control_Background cannot express, fixed/sticky global overlays or off-canvas UI when native positioning controls are insufficient, custom systemic z-index layers when native z-index controls are insufficient, Google Fonts @import, media queries that responsive controls cannot express, complex responsive behavior, hover/focus refinements, browser fixes, or theme specificity conflicts after native settings are set. The script-injected CSS validator inspects only static CSS declarations assigned to an injected style node; it excludes JavaScript object properties, @import, :root custom properties, and documented @media/@keyframes/@supports/@container exceptions.',
                'cache_rule' => 'After changing native Elementor settings, clear Elementor CSS cache: delete_post_meta(post_id, "_elementor_css"), delete_option("_elementor_global_css"), Elementor files cache when available, and rocket_clean_domain() when WP Rocket exists.',
                'forbidden' => [
                    'CSS-only backgrounds, contrast, spacing, borders, typography, or layout when native Elementor settings can express them.',
                    'Full page or block styling hidden inside an HTML widget instead of editable Elementor controls.',
                    'Unscoped global CSS for page-specific styling.',
                    'Injecting CSS via <script>createElement("style") for any property that has a mapping in css_to_native_map.',
                ],
                'validation_checks' => [
                    [
                        'code' => 'html_widget_script_injected_css',
                        'severity' => 'blocking',
                        'check' => 'Detect <script> tags in HTML widgets that inject <style> targeting properties listed in css_to_native_map.',
                        'message' => 'HTML widget contains static <script>-injected CSS declarations for native properties. Migrate only those declarations to widget/container native settings.',
                    ],
                ],
            ],
            'mobile_first_policy' => [
                'required' => true,
                'rule' => 'Design and validate the mobile layout first, then enhance tablet and desktop.',
                'native_settings' => [
                    'Set mobile flex direction, wrapping, order, width, min-height, padding, gap, and alignment explicitly where the section can collapse.',
                    'Keep primary CTA visible without horizontal scrolling.',
                    'Use readable mobile type sizes and comfortable tap targets.',
                    'Avoid desktop-first split/grid compositions that are only patched after saving.',
                ],
                'verification' => 'Run visual audit with mobile readiness in mind before writing and verify the public page at mobile width before desktop polish.',
            ],
            'wp_admin_editing_forbidden' => true,
            'playwright_editing_forbidden' => true,
            'browser_verification_scope' => 'Browser automation may inspect public pages after writes, but must not log in to WP Admin, open Elementor editor for edits, request admin credentials, or rely on WP cookies/nonces.',
            'run_bypass_blocked' => true,
            'run_elementor_data_policy' => 'Direct _elementor_data writes through /run are not a bypass; changed Elementor data is validated with the same design-system and preflight contract and rolled back on failure.',
            'unit_policy' => [
                'spacing_and_type' => 'Prefer rem/em.',
                'height' => 'Prefer vh/svh/min-height for viewport sections.',
                'width' => 'Prefer %, flex basis, max-width, and responsive constraints.',
                'px_exceptions' => 'Hairline borders, tiny controls/icons, shadows, and Elementor compatibility exceptions.',
            ],
            'supports_dry_run' => [ '/elementor/page', '/elementor/update', '/elementor/patch' ],
        ],
        'rollback' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/rollback',
            'requires_guide_token' => true,
            'ttl_seconds' => WPAE_ROLLBACK_TTL_SECONDS,
            'max_snapshots' => WPAE_ROLLBACK_MAX_SNAPSHOTS,
            'storage' => 'wp_options',
            'run_snapshot_request' => [
                'rollback_targets' => [
                    'post_ids' => [ 123 ],
                    'option_names' => [ 'some_option_name' ],
                ],
            ],
        ],
        'skills' => [
            'storage' => 'wp_options',
            'safe_endpoints' => [
                'list' => 'GET /wp-json/ai-executor/v1/skills',
                'save_one' => 'POST /wp-json/ai-executor/v1/skills',
                'delete_one' => 'DELETE /wp-json/ai-executor/v1/skills/{id}',
                'export_bundle' => 'GET /wp-json/ai-executor/v1/skills/export',
                'import_bundle' => 'POST /wp-json/ai-executor/v1/skills/import',
                'import_from_github_url' => 'POST /wp-json/ai-executor/v1/skills/import-url',
            ],
            'import_modes' => [ 'merge', 'replace' ],
            'github_import' => [
                'storage' => 'Downloaded skills are normalized and stored in wp_options; no server files are created.',
                'accepted_urls' => [
                    'https://github.com/{owner}/{repo}/blob/{ref}/path/SKILL.md',
                    'https://github.com/{owner}/{repo}/tree/{ref}/path/to/skill-folder',
                    'https://raw.githubusercontent.com/{owner}/{repo}/{ref}/path/SKILL.md',
                    'https://raw.githubusercontent.com/{owner}/{repo}/{ref}/bundle.json',
                ],
            ],
            'bundle_schema' => 'wp-ai-executor.skill-bundle',
            'max_skills_per_bundle' => 100,
            'max_content_bytes_per_skill' => 120000,
            'enforce_rule_types' => [
                'forbid_elementor_eltype',
                'require_widget_key',
                'forbid_widget_key',
                'allow_widget_type',
                'forbid_widget_type',
                'require_widget_setting',
                'require_container_setting',
                'forbid_html_pattern',
            ],
        ],
        'exports' => [
            'storage' => 'wp_options',
            'ttl_seconds' => WPAE_EXPORT_TTL_SECONDS,
            'max_entries' => WPAE_EXPORT_MAX_ENTRIES,
            'endpoints' => [
                'summary' => 'GET /wp-json/ai-executor/v1/exports',
                'create' => 'POST /wp-json/ai-executor/v1/exports/create',
                'fetch_one' => 'GET /wp-json/ai-executor/v1/exports/{id}',
                'prune_expired' => 'POST /wp-json/ai-executor/v1/exports/prune',
            ],
            'file_policy' => 'Exports are never public uploads files; prune removes expired wp_options entries only.',
        ],
        'operation_logs' => [
            'endpoint' => 'GET /wp-json/ai-executor/v1/logs',
            'storage' => 'wp_options',
            'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
            'logged_fields' => [
                'time',
                'method',
                'endpoint',
                'status',
                'actor',
                'ip_hash',
                'guide_hash',
                'target_ids',
                'rollback_snapshot_id',
                'validation_result',
            ],
            'redaction' => [
                'api_keys',
                'guide_tokens',
                'raw_request_bodies',
                'raw_page_payloads',
                'raw_response_payloads',
                'secrets',
            ],
        ],
        'agent_conformance' => [
            'enabled' => true,
            'returned_in_responses' => true,
            'included_in_logs' => true,
            'levels' => [ 'strong', 'acceptable', 'weak', 'blocked' ],
            'criteria' => [
                'guide_token_flow',
                'file_policy',
                'elementor_policy',
                'native_flex_containers',
                'widget_type_integrity',
                'native_visual_settings',
                'static_visual_audit',
                'design_system_consistency',
                'typography_hierarchy',
                'spacing_consistency',
                'cta_visibility',
                'mobile_readiness',
                'palette_quality',
                'content_completeness',
                'responsive_unit_policy',
                'verification_signal',
            ],
            'design_quality_gates' => [
                'typography_hierarchy' => 'Use enough native heading widgets to show page structure.',
                'spacing_consistency' => 'Put padding and gap into native container settings, not only CSS.',
                'cta_visibility' => 'Use a native button widget for the primary action.',
                'mobile_readiness' => 'Design mobile first and include responsive Elementor settings for complex layouts.',
                'palette_quality' => 'Use a deliberate palette with detectable color variety.',
                'content_completeness' => 'Avoid empty native heading/text widgets and sparse placeholder content.',
                'responsive_unit_policy' => 'Prefer rem/em for spacing/type, vh/svh for height, and percentages/flex constraints for width.',
            ],
            'score_meaning' => [
                '90_100' => 'strong',
                '75_89' => 'acceptable',
                '50_74' => 'weak',
                '0_49' => 'blocked',
            ],
        ],
        'repeated_agent_error_audit' => [
            'enabled' => true,
            'returned_on' => [ '/audit', '/elementor/visual-audit' ],
            'checks' => [
                'legacy_sections_columns',
                'snake_case_widget_type',
                'html_widget_layout_or_content',
                'script_injected_native_css',
                'heading_typography_important_override',
                'excessive_local_typography_overrides',
                'design_system_marker_drift',
                'fixed_px_layout_risk',
            ],
            'purpose' => 'Surfaces repeated external-agent mistakes as concrete machine-readable checks with safe next fixes.',
        ],
        'file_write_policy' => [
            'forbidden_in_run' => [ 'php_files', 'mu_plugins', 'tmp_files', 'arbitrary_paths', 'shell_commands' ],
            'filesystem_override_enabled' => wpae_can_run_filesystem_operations(),
            'allowed_endpoints' => [
                '/self-update' => ! empty( $settings['self_update'] ),
                '/self-update-package' => ! empty( $settings['self_update'] ) && class_exists( 'ZipArchive' ),
                '/media/upload' => ! empty( $settings['media_upload'] ),
                '/exports' => true,
                '/exports/create' => ! empty( $settings['exports'] ),
                '/exports/prune' => ! empty( $settings['exports'] ),
                '/elementor/validate' => true,
                '/elementor/normalize' => true,
                '/elementor/blueprint' => true,
                '/elementor/design-system' => true,
                '/elementor/recipes' => true,
                '/elementor/compose' => true,
                '/elementor/visual-audit' => true,
                '/elementor/typography-unlock' => ! empty( $settings['elementor_writes'] ),
                '/elementor/resolve-typography-overrides' => ! empty( $settings['elementor_writes'] ),
                '/elementor/revisions' => true,
                '/elementor/restore-revision' => ! empty( $settings['elementor_writes'] ),
                '/visual-audit' => true,
                '/elementor/page' => ! empty( $settings['elementor_writes'] ),
                '/elementor/update' => ! empty( $settings['elementor_writes'] ),
                '/elementor/patch' => ! empty( $settings['elementor_writes'] ),
                '/audit' => true,
                '/logs' => true,
                '/rollback' => true,
                '/skills' => ! empty( $settings['manage_skills'] ),
                '/skills/export' => true,
                '/skills/import' => ! empty( $settings['manage_skills'] ),
                '/skills/import-url' => ! empty( $settings['manage_skills'] ),
            ],
        ],
        'guide_token_protocol' => [
            'session_endpoint' => 'POST /wp-json/ai-executor/v1/guide/session',
            'ack_endpoint' => 'POST /wp-json/ai-executor/v1/guide/ack',
            'required_headers_for_writes' => [ 'X-WPAE-Guide-Token', 'X-WPAE-Guide-Hash' ],
            'ttl_minutes' => 15,
            'storage' => 'Guide sessions are stored as individual wp_options plus a legacy index; guide tokens are also stored by token hash for write validation.',
            'ack_body_formats' => [
                'JSON body with guide_session_id and ack object.',
                'Raw JSON body fallback even if Content-Type is missing or wrong.',
                'Form fields: guide_session_id plus required ack fields.',
            ],
        ],
    ];
}

function wpae_get_capabilities(): WP_REST_Response {
    return new WP_REST_Response( wpae_get_capabilities_payload(), 200 );
}

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

function wpae_upload_media( WP_REST_Request $request ) {
    $filename = sanitize_file_name( (string) $request->get_param( 'filename' ) );
    $mime_type = sanitize_text_field( (string) $request->get_param( 'mime_type' ) );
    $content_base64 = (string) $request->get_param( 'content_base64' );
    $post_parent = absint( $request->get_param( 'post_parent' ) );

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    if ( $filename === '' || $content_base64 === '' ) {
        return new WP_REST_Response( [ 'error' => 'filename and content_base64 are required.' ], 400 );
    }

    if ( ! isset( $allowed_mimes[ $mime_type ] ) ) {
        return new WP_REST_Response( [ 'error' => 'mime_type is not allowed.', 'allowed' => array_keys( $allowed_mimes ) ], 400 );
    }

    $bytes = base64_decode( $content_base64, true );
    if ( $bytes === false ) {
        return new WP_REST_Response( [ 'error' => 'Invalid base64 content.' ], 400 );
    }

    if ( strlen( $bytes ) > 8 * 1024 * 1024 ) {
        return new WP_REST_Response( [ 'error' => 'Media file exceeds 8 MB limit.' ], 413 );
    }

    $detected_mime = wpae_detect_media_mime_from_bytes( $bytes );
    if ( $detected_mime !== $mime_type ) {
        return new WP_REST_Response( [
            'error' => 'Media binary signature does not match mime_type.',
            'mime_type' => $mime_type,
            'detected_mime_type' => $detected_mime,
        ], 400 );
    }

    $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    if ( $extension !== $allowed_mimes[ $mime_type ] ) {
        $filename .= '.' . $allowed_mimes[ $mime_type ];
    }

    $upload = wp_upload_bits( $filename, null, $bytes );
    if ( ! empty( $upload['error'] ) ) {
        return new WP_REST_Response( [ 'error' => $upload['error'] ], 500 );
    }

    $attachment_id = wp_insert_attachment( [
        'post_mime_type' => $mime_type,
        'post_title' => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
        'post_content' => '',
        'post_status' => 'inherit',
    ], $upload['file'], $post_parent );

    if ( is_wp_error( $attachment_id ) ) {
        return new WP_REST_Response( [ 'error' => $attachment_id->get_error_message() ], 500 );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    if ( is_array( $metadata ) ) {
        wp_update_attachment_metadata( $attachment_id, $metadata );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'id' => $attachment_id,
        'url' => wp_get_attachment_url( $attachment_id ),
        'file' => basename( $upload['file'] ),
        'mime_type' => $mime_type,
    ], 200 );
}

function wpae_detect_media_mime_from_bytes( string $bytes ): ?string {
    if ( str_starts_with( $bytes, "\xFF\xD8\xFF" ) ) {
        return 'image/jpeg';
    }

    if ( str_starts_with( $bytes, "\x89PNG\r\n\x1A\n" ) ) {
        return 'image/png';
    }

    if ( str_starts_with( $bytes, 'GIF87a' ) || str_starts_with( $bytes, 'GIF89a' ) ) {
        return 'image/gif';
    }

    if ( strlen( $bytes ) >= 12 && substr( $bytes, 0, 4 ) === 'RIFF' && substr( $bytes, 8, 4 ) === 'WEBP' ) {
        return 'image/webp';
    }

    if ( str_starts_with( $bytes, '%PDF-' ) ) {
        return 'application/pdf';
    }

    return null;
}

function wpae_get_export_store(): array {
    $exports = get_option( 'wp_ai_executor_exports', [] );
    return is_array( $exports ) ? $exports : [];
}

function wpae_update_export_store( array $exports ): void {
    update_option( 'wp_ai_executor_exports', $exports, false );
}

function wpae_prune_export_store( array $exports ): array {
    $now = time();
    foreach ( $exports as $id => $export ) {
        if ( (int) ( $export['expires_at_unix'] ?? 0 ) < $now ) {
            unset( $exports[ $id ] );
        }
    }

    uasort( $exports, function ( array $a, array $b ): int {
        return (int) ( $b['created_at_unix'] ?? 0 ) <=> (int) ( $a['created_at_unix'] ?? 0 );
    } );

    return array_slice( $exports, 0, WPAE_EXPORT_MAX_ENTRIES, true );
}

function wpae_build_exports_summary( array $exports ): array {
    $now = time();
    $active = [];
    $expired_count = 0;
    $total_bytes = 0;

    foreach ( $exports as $id => $export ) {
        $expires_at_unix = (int) ( $export['expires_at_unix'] ?? 0 );
        if ( $expires_at_unix > 0 && $expires_at_unix < $now ) {
            $expired_count++;
            continue;
        }

        $bytes = (int) ( $export['bytes'] ?? 0 );
        $total_bytes += $bytes;
        $active[] = [
            'id' => (string) $id,
            'filename' => (string) ( $export['filename'] ?? 'wp-ai-export.json' ),
            'bytes' => $bytes,
            'created_at' => $export['created_at'] ?? null,
            'expires_at' => $export['expires_at'] ?? null,
            'endpoint' => get_rest_url( null, 'ai-executor/v1/exports/' . (string) $id ),
        ];
    }

    return [
        'active_count' => count( $active ),
        'expired_count' => $expired_count,
        'max_entries' => WPAE_EXPORT_MAX_ENTRIES,
        'ttl_seconds' => WPAE_EXPORT_TTL_SECONDS,
        'total_active_bytes' => $total_bytes,
        'storage' => 'wp_options',
        'exports' => $active,
    ];
}

function wpae_get_exports_summary(): WP_REST_Response {
    $exports = wpae_get_export_store();
    return new WP_REST_Response( array_merge( [ 'ok' => true ], wpae_build_exports_summary( $exports ) ), 200 );
}

function wpae_prune_exports(): WP_REST_Response {
    $before = wpae_get_export_store();
    $after = wpae_prune_export_store( $before );
    wpae_update_export_store( $after );

    return new WP_REST_Response( array_merge( [
        'ok' => true,
        'removed_count' => max( 0, count( $before ) - count( $after ) ),
        'before_count' => count( $before ),
        'after_count' => count( $after ),
    ], wpae_build_exports_summary( $after ) ), 200 );
}

function wpae_create_export( WP_REST_Request $request ) {
    $filename = sanitize_file_name( (string) ( $request->get_param( 'filename' ) ?: 'wp-ai-export-' . gmdate( 'Ymd-His' ) . '.json' ) );
    $payload = $request->get_param( 'data' );

    if ( $payload === null ) {
        return new WP_REST_Response( [ 'error' => 'data is required.' ], 400 );
    }

    if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'json' ) {
        $filename .= '.json';
    }

    $json = is_string( $payload )
        ? $payload
        : wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

    if ( ! is_string( $json ) || strlen( $json ) > 1024 * 1024 ) {
        return new WP_REST_Response( [ 'error' => 'Export JSON exceeds 1 MB limit or could not be encoded.' ], 413 );
    }

    $decoded = json_decode( $json, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_REST_Response( [ 'error' => 'Export data must be valid JSON.' ], 400 );
    }

    $now = time();
    $export_id = bin2hex( random_bytes( 12 ) );
    $exports = wpae_prune_export_store( wpae_get_export_store() );
    $exports[ $export_id ] = [
        'id' => $export_id,
        'filename' => $filename,
        'json' => $json,
        'bytes' => strlen( $json ),
        'created_at' => gmdate( 'c', $now ),
        'created_at_unix' => $now,
        'expires_at' => gmdate( 'c', $now + WPAE_EXPORT_TTL_SECONDS ),
        'expires_at_unix' => $now + WPAE_EXPORT_TTL_SECONDS,
    ];
    wpae_update_export_store( wpae_prune_export_store( $exports ) );

    return new WP_REST_Response( [
        'ok' => true,
        'id' => $export_id,
        'filename' => $filename,
        'bytes' => strlen( $json ),
        'expires_at' => $exports[ $export_id ]['expires_at'],
        'endpoint' => get_rest_url( null, 'ai-executor/v1/exports/' . $export_id ),
        'storage' => 'wp_options',
        'public_url' => null,
    ], 200 );
}

function wpae_get_export( WP_REST_Request $request ): WP_REST_Response {
    $export_id = sanitize_text_field( (string) $request->get_param( 'id' ) );
    $exports = wpae_prune_export_store( wpae_get_export_store() );

    if ( $export_id === '' || ! isset( $exports[ $export_id ] ) ) {
        wpae_update_export_store( $exports );
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid or expired export.' ], 404 );
    }

    $export = $exports[ $export_id ];
    $response = new WP_REST_Response( [
        'ok' => true,
        'id' => $export_id,
        'filename' => $export['filename'] ?? 'wp-ai-export.json',
        'data' => json_decode( (string) ( $export['json'] ?? '{}' ), true ),
        'bytes' => (int) ( $export['bytes'] ?? 0 ),
        'expires_at' => $export['expires_at'] ?? null,
    ], 200 );
    $response->header( 'Cache-Control', 'no-store, private' );

    return $response;
}

function wpae_self_update( WP_REST_Request $request ) {
    $source_url = trim( (string) $request->get_param( 'source_url' ) );
    $dry_run     = (bool) $request->get_param( 'dry_run' );

    if ( $source_url === '' || ! wpae_is_allowed_self_update_url( $source_url ) ) {
        return new WP_REST_Response( [
            'error' => 'Self-update requires an immutable Git commit URL.',
            'allowed' => 'https://raw.githubusercontent.com/DiasMazhenov/wp-ai-executor/<40-char-commit-sha>/wp-ai-executor.php',
        ], 400 );
    }

    $response = wp_remote_get( $source_url, [
        'timeout' => 20,
        'redirection' => 3,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [
            'error' => 'Failed to download update.',
            'message' => $response->get_error_message(),
        ], 502 );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = (string) wp_remote_retrieve_body( $response );

    if ( $status !== 200 ) {
        return new WP_REST_Response( [
            'error' => 'Update download returned non-200 status.',
            'status' => $status,
        ], 502 );
    }

    $validation_errors = wpae_validate_self_update_file( $body );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'error' => 'Downloaded plugin file failed validation.',
            'details' => $validation_errors,
        ], 422 );
    }

    $target = __FILE__;
    $current_hash = hash_file( 'sha256', $target );
    $new_hash = hash( 'sha256', $body );

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'target' => $target,
            'source_url' => $source_url,
            'current_sha256' => $current_hash,
            'new_sha256' => $new_hash,
            'same_file' => hash_equals( $current_hash, $new_hash ),
        ], 200 );
    }

    $temp = tempnam( dirname( $target ), '.wpae-update-' );
    if ( $temp === false ) {
        return new WP_REST_Response( [
            'error' => 'Failed to create atomic update temp file.',
            'target' => $target,
        ], 500 );
    }

    $written = file_put_contents( $temp, $body, LOCK_EX );
    if ( $written === false ) {
        @unlink( $temp );
        return new WP_REST_Response( [
            'error' => 'Failed to write plugin update temp file.',
            'target' => $target,
        ], 500 );
    }

    $target_mode = @fileperms( $target );
    if ( $target_mode !== false ) {
        @chmod( $temp, $target_mode & 0777 );
    }

    if ( ! @rename( $temp, $target ) ) {
        @unlink( $temp );
        return new WP_REST_Response( [
            'error' => 'Failed to atomically replace plugin file.',
            'target' => $target,
        ], 500 );
    }

    clearstatcache( true, $target );

    return new WP_REST_Response( [
        'ok' => true,
        'target' => $target,
        'source_url' => $source_url,
        'bytes' => $written,
        'previous_sha256' => $current_hash,
        'new_sha256' => hash_file( 'sha256', $target ),
    ], 200 );
}

function wpae_is_allowed_self_update_url( string $source_url ): bool {
    $parts = wp_parse_url( $source_url );
    if ( ! is_array( $parts ) ) {
        return false;
    }

    if ( ( $parts['scheme'] ?? '' ) !== 'https' ) {
        return false;
    }

    if ( ( $parts['host'] ?? '' ) !== 'raw.githubusercontent.com' ) {
        return false;
    }

    $path = $parts['path'] ?? '';
    return (bool) preg_match( '#^/DiasMazhenov/wp-ai-executor/[a-f0-9]{40}/wp-ai-executor\.php$#', $path );
}

function wpae_validate_self_update_file( string $contents ): array {
    $errors = [];

    if ( strlen( $contents ) < 5000 ) {
        $errors[] = 'File is unexpectedly small.';
    }

    if ( strlen( $contents ) > 500000 ) {
        $errors[] = 'File is unexpectedly large.';
    }

    if ( strncmp( ltrim( $contents ), '<?php', 5 ) !== 0 ) {
        $errors[] = 'File must start with <?php.';
    }

    $required_markers = [
        'Plugin Name: WP AI Executor',
        'function wpae_run',
        'function wpae_self_update',
        "register_rest_route( 'ai-executor/v1'",
        'Filesystem writes are disabled by WP AI Executor policy.',
    ];

    foreach ( $required_markers as $marker ) {
        if ( strpos( $contents, $marker ) === false ) {
            $errors[] = 'Missing marker: ' . $marker;
        }
    }

    return $errors;
}

function wpae_run( WP_REST_Request $request ) {
    $code = trim( (string) $request->get_param( 'code' ) );
    if ( $code === '' ) {
        return new WP_REST_Response( [ 'error' => 'No code provided' ], 400 );
    }

    if ( (bool) $request->get_param( 'dry_run' ) ) {
        return new WP_REST_Response( [
            'error' => 'dry_run is not supported for arbitrary /run PHP.',
            'help' => 'Use /elementor/validate, /elementor/page dry_run, /elementor/update dry_run, or pass rollback_targets for a real rollback snapshot.',
        ], 400 );
    }

    $forbidden_file_operation = wpae_detect_forbidden_file_operation( $code );
    if (
        $forbidden_file_operation &&
        ! wpae_can_run_filesystem_operations()
    ) {
        return new WP_REST_Response( [
            'error' => 'Filesystem writes are disabled by WP AI Executor policy.',
            'blocked_operation' => $forbidden_file_operation,
            'help' => 'Use WordPress APIs for posts, post meta, options, Elementor data, and cache clearing. Do not create temporary loaders, mu-plugins, PHP/JS/CSS/JSON files, or files in /tmp.',
        ], 403 );
    }

    $rollback_snapshot = null;
    $rollback_targets = $request->get_param( 'rollback_targets' );
    if ( is_array( $rollback_targets ) ) {
        $rollback_snapshot = wpae_create_rollback_snapshot(
            'run',
            is_array( $rollback_targets['post_ids'] ?? null ) ? $rollback_targets['post_ids'] : [],
            is_array( $rollback_targets['option_names'] ?? null ) ? $rollback_targets['option_names'] : []
        );
    }

    $elementor_before = wpae_capture_elementor_data_snapshot();

    ob_start();
    $result = null;
    try {
        $fn     = eval( 'return function() { ' . $code . ' };' );
        $result = $fn();
    } catch ( Throwable $e ) {
        ob_end_clean();
        return new WP_REST_Response( [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        ], 500 );
    }
    $output = ob_get_clean();

    $elementor_validation = wpae_validate_changed_elementor_data( $elementor_before, $request );
    if ( ! $elementor_validation['ok'] ) {
        return new WP_REST_Response( [
            'error' => 'Invalid Elementor data blocked by WP AI Executor policy.',
            'details' => $elementor_validation['errors'],
            'rolled_back_post_ids' => $elementor_validation['rolled_back_post_ids'],
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
            'output' => $output,
        ], 422 );
    }

    return new WP_REST_Response( [
        'return_value' => $result,
        'output' => $output,
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
    ], 200 );
}

function wpae_detect_forbidden_file_operation( string $code ): ?string {
    $patterns = [
        'file_put_contents',
        'fopen',
        'fwrite',
        'fputs',
        'mkdir',
        'rmdir',
        'unlink',
        'rename',
        'copy',
        'touch',
        'chmod',
        'chown',
        'chgrp',
        'symlink',
        'link',
        'move_uploaded_file',
        'ZipArchive',
        'Phar',
        'WP_Filesystem',
        'wp_mkdir_p',
        'wp_delete_file',
        'wp_tempnam',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
        'popen',
    ];

    foreach ( $patterns as $pattern ) {
        if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $code ) ) {
            return $pattern;
        }
    }

    $path_patterns = [
        '/tmp/',
        'wp-content/mu-plugins',
        'wp-content/plugins',
        'wp-content/themes',
        'landing_data.b64',
        'elem-loader.php',
    ];

    foreach ( $path_patterns as $pattern ) {
        if ( stripos( $code, $pattern ) !== false ) {
            return $pattern;
        }
    }

    return null;
}

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
        'findings' => $findings,
    ], $has_failures ? 422 : 200 );
}

function wpae_get_guide(): WP_REST_Response {
    return new WP_REST_Response( wpae_agent_guide(), 200 );
}

function wpae_agent_guide(): array {
    return [
        'name' => 'WP AI Executor Agent Guide',
        'version' => 'v02.05.36',
        'plugin_version' => WPAE_VERSION,
        'purpose' => 'Use this guide before automating WordPress and Elementor through WP AI Executor.',
        'embedded_skill_packs' => [
            'frontend_design' => 'Distilled frontend-design rules for distinctive visual direction, typography, layout, motion, and copy.',
            'wordpress_elementor_dev' => 'Distilled WordPress/Elementor development rules for native Elementor data, REST execution, security, and verification.',
            'jezweb_claude_skills' => 'Distilled jezweb/claude-skills workflows for WordPress Elementor, landing pages, design review, and color palettes.',
        ],
        'custom_skills' => wpae_get_enabled_skills_for_guide(),
        'project_design_tokens' => wpae_get_project_design_tokens(),
        'project_design_system' => wpae_build_project_design_system(),
        'jezweb_claude_skills' => wpae_get_jezweb_claude_skills_pack(),
        'capabilities' => wpae_get_capabilities_payload(),
        'repeated_agent_error_audit_policy' => [
            'returned_in' => [ 'POST /elementor/visual-audit', 'POST /audit' ],
            'rule' => 'Agents must read repeated_agent_error_audit and fix every fail check before claiming success; warn checks must be either fixed or explicitly explained.',
            'checks' => [
                'legacy_sections_columns',
                'snake_case_widget_type',
                'html_widget_layout_or_content',
                'script_injected_native_css',
                'heading_typography_important_override',
                'excessive_local_typography_overrides',
                'design_system_marker_drift',
                'fixed_px_layout_risk',
            ],
        ],
        'guide_token_protocol' => [
            'required_for_write_endpoints' => true,
            'session_endpoint' => 'POST /wp-json/ai-executor/v1/guide/session',
            'ack_endpoint' => 'POST /wp-json/ai-executor/v1/guide/ack',
            'required_write_headers' => [
                'X-WPAE-Guide-Token',
                'X-WPAE-Guide-Hash',
            ],
            'ttl_minutes' => 15,
            'storage' => 'Guide sessions are stored as individual wp_options plus a legacy index; guide tokens are stored by token hash so write validation does not depend only on one shared session array.',
            'ack_body_formats' => [
                'application/json body: {"guide_session_id":"...","ack":{...}}',
                'raw JSON body fallback even when Content-Type is missing/wrong',
                'form fields: guide_session_id plus read_agent_prompt/read_custom_skills/read_capabilities/will_follow_skills/will_follow_runtime_rules',
            ],
            'rule' => 'Before any write endpoint, create a guide session, read /guide and /capabilities, acknowledge all required fields, then send the returned guide token and hash with the write request.',
        ],
        'v02_08_16_behavior_notes' => [
            'rule' => 'Agents must account for current WP AI Executor runtime behavior instead of assuming older file-based or broad-rollback behavior.',
            'rollback_scope' => [
                'behavior' => 'Rollback restores the post record plus plugin-managed Elementor/WPAE meta only.',
                'managed_post_meta' => wpae_managed_rollback_post_meta_keys(),
                'agent_instruction' => 'Do not expect /rollback to delete or restore unrelated third-party post meta such as SEO, CRM, analytics, theme, or custom plugin fields.',
            ],
            'elementor_page_orphan_cleanup' => [
                'behavior' => 'If /elementor/page creates a new page but Elementor metadata saving fails, the plugin attempts to delete that newly created page immediately.',
                'response_signal' => 'Failure responses may include cleanup.created_post_deleted and cleanup.reason.',
                'agent_instruction' => 'On /elementor/page failure, inspect cleanup before retrying; do not manually hunt for orphan pages unless cleanup failed.',
            ],
            'media_upload_signature_check' => [
                'behavior' => '/media/upload validates binary file signatures for JPEG, PNG, WebP, GIF, and PDF before writing to the media library.',
                'agent_instruction' => 'The declared mime_type must match the actual base64 content. If rejected, fix the source bytes or mime_type; do not retry with a fake extension.',
                'error' => 'Media binary signature does not match mime_type.',
            ],
            'exports_storage' => [
                'behavior' => '/exports/create stores short-lived JSON exports in wp_options and returns an authenticated /exports/{id} endpoint.',
                'summary_endpoint' => 'GET /wp-json/ai-executor/v1/exports returns metadata only, never raw export JSON.',
                'prune_endpoint' => 'POST /wp-json/ai-executor/v1/exports/prune removes expired wp_options export records and requires the exports capability plus guide token.',
                'agent_instruction' => 'Never expect public uploads export files or a public URL from /exports/create.',
            ],
        ],
        'agent_prompt' => wpae_agent_prompt(),
        'workflow' => [
            '1. Inspect WordPress, PHP, theme, and Elementor status with a small read-only PHP request.',
            '2. Before any page or page-block work, call /elementor/design-system and treat it as the single style source.',
            '3. For page work, call /elementor/blueprint after /elementor/design-system before composing or writing Elementor data.',
            '3a. Design mobile first: define mobile stacking, type scale, spacing, CTA visibility, tap targets, and responsive Elementor settings before expanding tablet and desktop layouts.',
            '4. Prefer /elementor/design-system, /elementor/blueprint, /elementor/recipes, /elementor/compose, /elementor/normalize, /elementor/validate, /elementor/visual-audit, /elementor/typography-unlock, /elementor/resolve-typography-overrides, /elementor/patch, /elementor/page, and /elementor/update over raw PHP through /run.',
            '4a. Never use /run to bypass /elementor/update, design-system marker classes, dry_run, or Elementor preflight; direct _elementor_data changes through /run are validated by the same contract and rolled back on failure.',
            '4b. If an existing page has old or different wpae-system-* classes, migrate it to the current main design system with /elementor/normalize, then run /elementor/update dry_run; preserve non-WPAE custom classes.',
            '4c. Never ask for WP Admin login/password, admin cookies, nonces, or browser sessions. Do not use Playwright/WP Admin/Elementor editor for writes; browser automation is allowed only to inspect public pages after API writes.',
            '5. Before building a new page, call /elementor/blueprint with subject, audience, goal, offer, language, style, proof points, and CTA labels. For complex or non-standard sections, call /elementor/recipes, choose a recipe/variant, then call /elementor/compose with slots.',
            '6. Use /elementor/normalize when Elementor JSON contains legacy sections/columns, widget_type, missing widgetType, missing settings, missing elements arrays, incomplete container defaults, or missing design-system marker classes.',
            '7. Use /elementor/validate, /elementor/visual-audit, or dry_run=true on /elementor/patch, /elementor/page, and /elementor/update before a real write when building complex pages; structured writes also run strict preflight checks.',
            '8. Use native Elementor Flexbox Containers only for layout: elType=container plus native widgets. Never use legacy elType=section or elType=column.',
            '9. Never create external files. Use WordPress APIs and database metadata only; no temp files, loaders, mu-plugins, PHP/JS/CSS/JSON files, or filesystem writes.',
            '10. Design the page before building: define subject, audience, job, palette, type roles, layout, and one signature element inside the returned design system.',
            '11. Prefer rem/em for spacing and typography, vh/svh for viewport-height sections, and %/flex/max-width constraints for widths; use px only for small exceptions or Elementor compatibility.',
            '12. Change element styles through native Elementor settings/style controls first; use scoped CSS only for exceptional enhancements that cannot reasonably be expressed with native settings.',
            '13. On existing pages, preserve unrelated working CSS/JS/WebGL/Three.js/GSAP/canvas/shader/animation HTML widgets and scoped CSS; do not perform a page-wide restyle or cleanup unless explicitly requested.',
            '13a. Protected enhancement zones are enforced on structured updates. Do not modify or remove them unless the task explicitly requires it; then send allow_protected_zone_changes=true with a concrete protected_zone_reason and verify the public animation afterwards.',
            '14. Save the returned rollback_snapshot_id after writes and use /rollback if the result is wrong.',
            '14a. If /rollback returns ok=false, stop. Report its status/error/restored_posts details, keep the retained snapshot, and do not attempt a manual metadata rewrite, /run workaround, WP Admin edit, or browser-based write.',
            '15. Verify with /audit, /elementor/visual-audit, /visual-audit, HTTP status, permalink, post status, _elementor_edit_mode, _elementor_data, visible HTML text, and inspect any html widgets if present.',
            '16. After any page write, rollback, typography migration, or revision restore, perform real browser screenshot verification on the public URL before claiming success; HTML/CSS audits alone are not enough.',
            '17. Use /logs for recent operation metadata when debugging, but never expect raw payloads or secrets there.',
            '18. Read preflight and agent_conformance in write responses and fix weak/blocked criteria, including design quality gates, before considering the task complete.',
            '19. If any endpoint or verification step fails, report concrete error details: endpoint/action, HTTP status or exception, plugin error code/message, details/preflight/blocking_errors, and the next safe fix.',
        ],
        'frontend_design' => [
            'principles' => [
                'Avoid generic template aesthetics; ground the visual direction in the subject matter.',
                'Open with a hero that states a clear design thesis.',
                'Choose a compact color system, intentional typography roles, and one memorable signature element.',
                'Use structure as information, not decoration. Numbering should mean sequence.',
                'Keep copy specific, active, and useful from the visitor side of the screen.',
                'Spend boldness in one place; keep the rest disciplined and responsive.',
            ],
            'anti_generic_defaults' => [
                'Do not default to a generic hero, generic gradient cards, generic dark SaaS page, or one-note palette.',
                'Do not use decorative numbering unless the content is actually sequential.',
                'Do not use stock-like filler sections; every section must move the visitor toward the page job.',
                'Do not stop at a technically valid layout; evaluate whether the page looks intentionally designed.',
            ],
            'planning_template' => [
                'subject' => 'What is being sold or explained?',
                'audience' => 'Who must understand and act?',
                'single_job' => 'What should the page make the visitor do?',
                'palette' => '4-6 named hex colors.',
                'type_roles' => 'Display, body, and utility/caption roles.',
                'layout' => 'Short section map or wireframe.',
                'signature' => 'One distinctive element justified by the brief.',
            ],
            'design_quality_bar' => [
                'Hero must be the design thesis and should contain the page signature.',
                'Typography must use deliberate roles: display, body, utility/caption.',
                'Mobile-first is mandatory: mobile layout, readable type, tap targets, CTA visibility, and section order come before desktop polish.',
                'Layout must be stable at desktop, tablet, and mobile widths.',
                'Use responsive units deliberately: rem/em for spacing and type, vh/svh for viewport-height sections, and percentages/flex constraints for width.',
                'Motion must support comprehension: reveal, hover, progress, or focused ambient animation.',
                'Copy must be concrete and action-oriented.',
                'Native Elementor output must pass design quality gates: heading hierarchy, native spacing, visible CTA, responsive settings, deliberate palette, and populated content.',
            ],
            'project_tokens_rule' => 'Use project_design_tokens from this guide as the site visual system. They override generic defaults unless the user explicitly asks for a different direction.',
            'design_system_first_rule' => 'Before creating a page or adding a page block, call /elementor/design-system. All later blocks must reuse the same system_id, palette, type roles, spacing, radii, button style, and tone.',
            'native_style_first_rule' => 'When changing any element style, write it to native Elementor settings/style controls first. CSS is only an exception for complex behavior, animations, hover/focus refinements, browser fixes, or specificity conflicts after native settings exist.',
            'elementor_editor_editability_rule' => 'All design properties that Elementor can edit must remain editable through native Elementor controls/settings. Native settings are the editable source of truth, not something to delete; only remove local overrides when the user explicitly asks for global inheritance/design-token control.',
            'global_typography_editability_rule' => 'For typography that the user should control globally in Elementor, do not hardcode typography_* overrides on every widget. Use global typography roles, inherited/container typography, or leave widget typography unset; local typography_* settings are allowed only for deliberate exceptions such as a hero display wordmark.',
        ],
        'jezweb_claude_skills' => [
            'source' => 'https://github.com/jezweb/claude-skills',
            'rule' => 'Apply the distilled jezweb workflows when they overlap with WordPress, Elementor, landing pages, design review, color palettes, responsiveness, and production verification.',
            'executor_adaptation' => [
                'Replace upstream standalone HTML output with editable Elementor native containers/widgets.',
                'Replace WP-CLI/browser-only structural edits with WP AI Executor safe endpoints whenever possible.',
                'Use /elementor/design-system before landing page or block work.',
                'Use /elementor/visual-audit as the design-review gate.',
                'Use project design tokens as the color-palette output target.',
                'Do not create external files even if an upstream skill would normally write artifacts.',
            ],
        ],
        'wordpress_elementor' => [
            'stack' => [
                'Remote WordPress site',
                'Elementor only',
                'WP AI Executor as the automation bridge',
                'No Oxygen',
                'No Novamira',
                'HTML widget only for small JS snippets or complex CSS that cannot reasonably be expressed through Elementor settings',
                'No WP Admin or Elementor editor writes through Playwright/browser automation',
            ],
            'browser_admin_policy' => [
                'rule' => 'Do not ask the user for WP Admin login/password, admin cookies, browser sessions, or REST nonces. Do not use WP Admin or Elementor editor for edits.',
                'allowed_browser_use' => 'Playwright/browser automation is allowed only for public-page inspection after REST API writes, such as screenshots, click checks, layout checks, and visible content verification.',
                'required_edit_path' => 'All WordPress/Elementor changes must go through WP AI Executor REST endpoints with X-AI-Key and guide-token headers for writes.',
                'auth_debugging' => 'If a request returns 401/403, verify the header name is X-AI-Key and report endpoint/status/body; do not assume key rotation and do not request credentials.',
            ],
            'filesystem_policy' => [
                'required' => true,
                'rule' => 'Do not create, modify, rename, copy, chmod, or delete files on the WordPress server through WP AI Executor.',
                'forbidden' => [
                    'Temporary loaders such as elem-loader.php.',
                    'Files in /tmp, wp-content/mu-plugins, wp-content/plugins, wp-content/themes, or uploads created as implementation scratch space.',
                    'External PHP, JS, CSS, JSON, base64, cache, or helper files.',
                    'Direct filesystem or shell/process calls such as file_put_contents, fopen, fwrite, mkdir, unlink, rename, copy, chmod, ZipArchive, Phar, WP_Filesystem, wp_mkdir_p, exec, shell_exec, system, passthru, proc_open, and popen.',
                ],
                'allowed_instead' => [
                    'Use wp_insert_post, wp_update_post, update_post_meta, update_option, delete_option, and Elementor metadata.',
                    'Use Elementor cache APIs to clear/regenerate generated CSS; do not write CSS files manually.',
                    'Return data directly from /run instead of writing temporary files.',
                    'Use /media/upload for validated media library files.',
                    'Use /exports/create for short-lived JSON exports stored in wp_options; fetch them through the authenticated /exports/{id} endpoint and never expect public uploads URLs.',
                ],
                'runtime_enforcement' => 'By default, /run rejects common filesystem write/delete operations unless the site owner enables the filesystem_writes capability or WP_AI_EXECUTOR_ALLOW_FILE_WRITES is explicitly defined.',
                'self_update_policy' => [
                    'endpoint' => 'POST /wp-json/ai-executor/v1/self-update',
                    'rule' => 'Plugin self-update is allowed only through the dedicated self-update endpoint, never through arbitrary /run filesystem writes.',
                    'source' => 'Only allowlisted raw GitHub URLs from DiasMazhenov/wp-ai-executor ending in wp-ai-executor.php are accepted.',
                    'target' => 'The endpoint writes only to the current plugin file (__FILE__) after validating required plugin markers.',
                    'dry_run' => 'Pass dry_run=true to compare hashes without writing.',
                ],
                'package_update_policy' => [
                    'endpoint' => 'POST /wp-json/ai-executor/v1/self-update-package',
                    'source' => 'Only an immutable https://github.com/DiasMazhenov/wp-ai-executor/archive/<40-char-commit-sha>.zip URL is accepted.',
                    'validation' => 'ZIP must include wpae-package.json with SHA-256 for every file; only wp-ai-executor.php and includes/*.php or includes/*.json paths are accepted.',
                    'rollout' => 'Modules are written before the bootstrap entrypoint, which is replaced last. Use dry_run=true before a live package rollout.',
                ],
            ],
            'custom_skills_policy' => [
                'endpoint' => 'GET/POST/DELETE /wp-json/ai-executor/v1/skills plus GET /skills/export, POST /skills/import, and POST /skills/import-url',
                'storage' => 'Skills are stored in wp_options as text/JSON, not as files.',
                'rule' => 'Agents must read every enabled custom_skills item in this guide before any WordPress/Elementor work and apply them as mandatory project rules by priority. If a custom skill conflicts with WP AI Executor policy, WP AI Executor policy wins.',
                'github_import' => 'GitHub skills are downloaded by the plugin, normalized, stored in wp_options, included in guide_hash, and exposed through /guide so agents do not need remote internet access to read them.',
                'limits' => 'Each skill content is limited to 120 KB and is never executed as code.',
                'bundle_import_export' => 'Skill bundles are JSON objects with schema=wp-ai-executor.skill-bundle and a skills array. Import mode can be merge or replace. Bundles are stored in the database only.',
                'enforceable_rules' => [
                    'forbid_elementor_eltype',
                    'require_widget_key',
                    'forbid_widget_key',
                    'allow_widget_type',
                    'forbid_widget_type',
                    'require_widget_setting',
                    'require_container_setting',
                    'forbid_html_pattern',
                ],
            ],
        'design_system_policy' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/design-system',
            'required' => true,
                'rule' => 'Before creating a page or adding any new page block, create/read the design system and reuse it as the single visual source of truth.',
                'write_enforcement' => 'POST /elementor/page and POST /elementor/update reject Elementor data whose top-level containers miss required_root_classes or do not use project palette tokens.',
                'required_marker' => 'Every top-level page/block container must include required_root_classes in settings._css_classes.',
                'block_rule' => 'A later block must inherit the same system_id, palette, typography roles, spacing, radii, button style, tone, and component grammar as the existing page.',
            'normalize_support' => 'POST /elementor/normalize adds the required design-system marker classes to top-level containers when missing.',
            'migration_rule' => 'If an existing page has old or different wpae-system-* marker classes, /elementor/normalize removes the old system marker, adds the current required_root_classes, preserves non-WPAE custom classes, and returns migrated_design_system_marker changes.',
            'agent_rule' => 'On design-system mismatch, migrate markers with /elementor/normalize and then use /elementor/update dry_run; do not bypass through /run.',
        ],
        'responsive_unit_policy' => [
            'required' => true,
            'rule' => 'Use responsive units wherever practical: rem/em for spacing, typography, gaps, padding, margins, radii, and component dimensions; vh/svh/min-height for viewport-height sections; percentages, flex basis, max-width, and responsive constraints for widths.',
            'px_exceptions' => [
                '1px borders and hairlines.',
                'Tiny icons and editor controls.',
                'Box shadows.',
                'Elementor controls that do not accept relative units reliably.',
            ],
            'preflight' => 'Structured Elementor writes warn when fixed px width/height settings are detected.',
        ],
        'native_style_settings_policy' => [
            'required' => true,
            'rule' => 'For every element style change, use native Elementor settings/style controls first. CSS is an exception, not the default.',
            'scope_boundary' => 'This rule applies to new styles and explicitly requested style changes. It is not permission to rewrite the whole page or remove working enhancement code.',
            'principle' => 'All styles Elementor supports natively must be set through widget/container native settings instead of external CSS.',
            'elementor_editor_editability_contract' => [
                'The owner must be able to edit design properties in Elementor after the agent finishes.',
                'If Elementor exposes a native control for a property, store that property in the element/container/widget settings in _elementor_data.',
                'Do not make CSS, an HTML widget, injected markup, an external file, or Custom CSS the only source of an editable design property.',
                'If a property from css_to_native_map appears in <script>-injected CSS, it is not editable in Elementor. The agent must migrate it to native settings and remove the injected CSS declaration.',
                'Do not remove native settings just because they generate page-specific CSS. Elementor-generated CSS from native settings is expected and editable.',
                'Only remove local native overrides when the user explicitly requests global Elementor style/design-token inheritance.',
                'When inheriting from global styles, document which properties were intentionally left unset and verify the global style is actually controlling them.',
            ],
            'css_to_native_map_contract' => 'Properties in this map must be set through native Elementor settings. They must not appear as the authoritative design source in Custom CSS, HTML widget CSS, external CSS files, or <script>-injected CSS.',
            'css_to_native_map' => [
                'typography' => [
                    'font-family' => 'typography_font_family',
                    'font-size' => 'typography_font_size plus _mobile and _tablet responsive variants',
                    'font-weight' => 'typography_font_weight',
                    'line-height' => 'typography_line_height; use unitless ratio for multi-line headings/text',
                    'letter-spacing' => 'typography_letter_spacing',
                    'text-transform' => 'typography_text_transform',
                ],
                'colors' => [
                    'color' => 'title_color or text_color',
                    'background-color' => 'background_background plus background_color',
                    'linear/radial gradient' => 'background_background=gradient plus background_color, background_color_b, background_gradient_type, background_gradient_angle, and background_gradient_position where supported',
                    'background overlay gradient' => 'background_overlay_background=gradient plus background_overlay_color, background_overlay_color_b, and related overlay gradient settings where supported',
                    'border-color' => 'border_border plus border_color',
                ],
                'advanced_positioning' => [
                    'z-index' => '_z_index or z_index control/settings when Elementor exposes it for the element',
                    'position fixed/sticky' => 'Elementor Advanced/Positioning or Motion Effects sticky settings such as _position, sticky, sticky_on, sticky_offset, sticky_effects_offset, and sticky_parent when available',
                ],
                'spacing_and_size' => [
                    'padding' => 'padding for all needed breakpoints',
                    'margin' => 'margin',
                    'border-radius' => 'border_radius',
                    'min-height' => 'min_height',
                ],
                'flex_grid' => [
                    'display:flex' => 'flex_direction, justify_content, align_items',
                    'gap' => 'gap',
                    'flex-wrap' => 'flex_wrap',
                ],
            ],
            'native_first' => [
                'Backgrounds, text colors, button colors, borders, radii, spacing, width, min-height, flex layout, alignment, and responsive variants must be stored in element/container/widget settings when Elementor supports them.',
                'Normal and hover gradients supported by Elementor Group_Control_Background are native settings, not CSS exceptions.',
                'z-index and fixed/sticky positioning must use Elementor advanced/positioning/motion settings first when available.',
                'Typography for heading, text-editor, and button widgets must use typography_* settings, including responsive font-size variants when needed.',
                'Use unitless line-height for multi-line headings/text instead of px/em line-height.',
                'CSS may refine or reinforce a style, but it must not be the only source of essential background, contrast, spacing, border, typography, or layout.',
                'When CSS is used, scope it under page/project classes and keep the affected content editable through native widgets.',
            ],
            'preserve_existing_enhancements' => [
                'Do not delete, rewrite, simplify, or move existing scoped CSS, JavaScript, WebGL, Three.js, GSAP, canvas, shader, animation, or HTML widget enhancement code unless the user explicitly asks for that exact change.',
                'If an existing enhancement is working, preserve its widget, classes, IDs, script order, and dependency assumptions.',
                'Native style migration must be targeted to the requested elements only; do not perform a page-wide restyle or cleanup pass.',
                'Before changing an existing page, compare before/after Elementor data and keep all unrelated HTML widget content byte-for-byte where possible.',
            ],
            'css_allowed_only_for' => [
                'Animations, transitions, hover/focus refinements, and progressive enhancement.',
                'Pseudo-elements such as ::before and ::after for decoration.',
                'WebGL/canvas styling.',
                'Gradients, masks, or patterns Elementor Group_Control_Background cannot express.',
                'position: fixed/sticky for global overlays or off-canvas UI when Elementor native positioning/sticky controls are insufficient.',
                'Custom systemic z-index layers when Elementor native z-index controls are insufficient.',
                '@import for Google Fonts.',
                'Media queries that Elementor responsive controls cannot express.',
                'Small JavaScript support styles.',
                'Complex responsive behavior that Elementor controls cannot express cleanly.',
                'Browser quirks or theme specificity conflicts after native settings are already present.',
                'Tiny visual details such as pseudo-elements, masks, advanced gradients, or decorative accents.',
            ],
            'script_injected_css_rule' => [
                'forbidden' => 'Never use <script>-injected <style> elements for properties in css_to_native_map.',
                'permitted_scope' => 'Script-injected CSS is allowed only for @import fonts, WebGL/canvas support, @keyframes, browser fixes, media queries, and properties without native Elementor equivalents.',
                'blocking_validation' => 'html_widget_script_injected_css blocks HTML widgets that inject style tags containing mapped native CSS properties.',
            ],
            'cache_rule' => [
                'After changing native settings, Elementor CSS cache must be cleared.',
                'Required cleanup: delete_post_meta(post_id, "_elementor_css") and delete_option("_elementor_global_css").',
                'If available, clear Elementor files cache and call rocket_clean_domain() for WP Rocket.',
                'If native settings do not appear publicly, suspect stale Elementor CSS cache before changing the settings again.',
            ],
            'css_forbidden_for' => [
                'Main page layout or content structure.',
                'Styles that Elementor has native controls for but the agent skipped.',
                'Global unscoped page-specific styles.',
                'Hiding missing native backgrounds, spacing, borders, typography, or contrast behind an HTML widget.',
                'Any property from css_to_native_map inside <script>-injected CSS.',
            ],
        ],
        'mobile_first_policy' => [
            'required' => true,
            'rule' => 'Start every page or new block from the mobile composition first, then progressively enhance tablet and desktop.',
            'elementor_requirements' => [
                'Set responsive Elementor controls for mobile stacking, wrapping, width, order, min-height, padding, gap, and alignment when a section is split, grid-like, or visually complex.',
                'Keep the main CTA visible and tappable on mobile without horizontal overflow.',
                'Use readable mobile typography and spacing from the design system before increasing desktop scale.',
                'Do not rely on desktop-only composition with mobile fixes added after the fact.',
            ],
            'verification' => [
                'Run /elementor/visual-audit before writing and treat weak mobile_readiness as a required fix.',
                'After writing, inspect the public page at mobile width first, then tablet/desktop.',
            ],
        ],
        'public_visual_audit_policy' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/visual-audit',
            'required_after_public_writes' => true,
            'scope' => 'Read-only public HTML checks for same-site post_id or url.',
            'checks' => [
                'public fetch status',
                'viewport/title/visible copy',
                'horizontal overflow risk from large fixed width/min-width styles',
                'invisible text patterns',
                'empty Elementor/WPAE blocks',
                'CTA presence',
                'mobile-first CSS signals',
            ],
            'limitations' => 'This endpoint intentionally avoids server-side screenshots and cannot compute true rendered overflow or full CSS cascade contrast.',
            'agent_rule' => 'Use /visual-audit after writes as a quick public-page sanity check, then use browser automation only on public pages for screenshots, clicks, rendered overflow, and contrast verification.',
        ],
        'elementor_preflight_policy' => [
            'applies_to' => [ 'POST /elementor/page', 'POST /elementor/update', 'changed _elementor_data after POST /run' ],
            'returned_as' => 'preflight',
            'blocks' => [
                'Invalid Elementor contract or design-system contract.',
                'Empty native heading/text widgets.',
                'HTML widget used as main layout/content.',
                'Landing page without a native button CTA.',
                'Landing page without native background, text color, padding, and gap settings.',
            ],
            'warns' => [
                'Fixed px width/height risks instead of responsive units.',
            ],
        ],
        'run_elementor_bypass_policy' => [
            'required' => true,
            'rule' => 'Agents must not use /run to bypass /elementor/update, design-system validation, dry_run, or Elementor preflight.',
            'enforcement' => 'Any _elementor_data changed after /run is validated with the same design-system and preflight contract. Failed writes are rolled back.',
            'allowed_run_use' => 'Use /run for small WordPress API tasks, cache clearing, diagnostics, or controlled metadata work that does not bypass structured Elementor endpoints.',
            'forbidden_run_use' => 'Do not directly update _elementor_data through /run to skip design-system marker classes, quality gates, dry_run, or structured endpoint validation.',
        ],
        'browser_admin_policy' => [
            'required' => true,
            'forbidden' => [
                'Requesting WP Admin login or password.',
                'Requesting admin cookies, WordPress REST nonces, browser sessions, or manual admin authentication.',
                'Using Playwright, browser automation, or WP Admin/Elementor editor to make content, style, meta, or settings changes.',
                'Treating 401/403 from WP Admin/browser nonce flows as a reason to ask for credentials.',
            ],
            'allowed' => [
                'Use WP AI Executor REST API with X-AI-Key for read endpoints.',
                'Use guide-token flow for write endpoints.',
                'Use Elementor structured endpoints for Elementor writes.',
                'Use browser automation only to inspect public, non-admin pages after API writes.',
            ],
            'rule' => 'If API auth fails, report the exact endpoint, headers used by name, HTTP status, and response body. Do not switch to WP Admin or request credentials.',
        ],
        'error_reporting_policy' => [
            'required' => true,
            'rule' => 'If any endpoint, validation, preflight, write, cache clear, or verification step fails, the agent must include concrete error details in its response.',
            'must_include' => [
                'Endpoint or action name.',
                'HTTP status or exception type when available.',
                'Plugin error code/message and details/preflight/blocking_errors when returned.',
                'The attempted fix or next safe step.',
            ],
            'forbidden' => 'Do not hide failures behind vague summaries such as "there was an issue" or claim completion while errors remain unresolved.',
        ],
        'after_save_quality_summary_policy' => [
            'applies_to' => [ 'POST /elementor/page', 'POST /elementor/update' ],
            'returned_as' => 'quality_summary',
            'contains' => [
                'permalink and post status.',
                'visual audit score and level.',
                'preflight warnings and blocking status.',
                'concrete fixes the agent should apply before claiming completion.',
            ],
            'rule' => 'If quality_summary.visual_audit.level is weak/blocked or fixes are present, the agent must continue correcting the page before reporting completion.',
        ],
        'transaction_write_policy' => [
            'applies_to' => [ 'POST /elementor/page', 'POST /elementor/update', 'POST /elementor/patch' ],
            'mode' => 'atomic',
            'returned_as' => 'transaction',
            'rule' => 'Structured Elementor writes create a rollback snapshot before writing, verify saved metadata and cache refresh after writing, and automatically roll back on transaction failure.',
            'auto_rollback_on' => [
                'metadata save error.',
                'saved _elementor_data is missing, invalid JSON, or fails Elementor validation.',
                'saved data violates the active design-system contract.',
                'Elementor CSS cache clear reports an error.',
                'public verification fails when transaction_verify_public=true.',
                'static quality is weak/blocked when transaction_strict_quality=true.',
            ],
            'optional_request_flags' => [
                'transaction_verify_public=true fetches the public permalink after save and runs /visual-audit style checks; failure triggers rollback.',
                'transaction_strict_quality=true treats weak/blocked static visual audit as a transaction failure.',
            ],
            'failure_rule' => 'If transaction.ok=false, read transaction.failed_checks and transaction.auto_rollback. Do not retry through /run, WP Admin, browser writes, or direct metadata edits. Fix the payload and repeat dry_run first.',
        ],
        'elementor_patch_policy' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/patch',
            'preferred_for' => 'Small targeted native Elementor settings changes by element_id, such as typography, colors, spacing, gap, alignment, min-height, border, radius, links, and widget content.',
            'rule' => 'Use /elementor/patch instead of rebuilding the whole page when only specific element settings need to change.',
            'payload_shape' => [
                'post_id' => 123,
                'dry_run' => true,
                'patches' => [
                    [
                        'element_id' => 'hero_title',
                        'path' => 'settings.typography_font_size',
                        'op' => 'set',
                        'value' => [ 'unit' => 'rem', 'size' => 4.5 ],
                    ],
                    [
                        'element_id' => 'hero_title',
                        'path' => 'settings.title_color',
                        'op' => 'set',
                        'value' => '#111827',
                    ],
                ],
            ],
            'allowed_paths' => [
                'settings.*',
                'elements.{index}.settings.* for tightly scoped nested settings only',
            ],
            'blocked_paths' => [
                'elType',
                'widgetType',
                'id',
                'arbitrary structural rewrites',
            ],
            'validation' => 'Patch output runs the same Elementor validation, design-system contract, preflight, atomic transaction, cache refresh, and rollback path as /elementor/update.',
        ],
        'protected_enhancement_zones_policy' => [
            'required' => true,
            'purpose' => 'Prevent native-style migrations and targeted edits from breaking working WebGL, Three.js, GSAP, canvas, shader, or HTML enhancement blocks.',
            'how_to_mark' => 'Add the wpae-protected-zone class to the owning Elementor element or data-wpae-protected to its HTML. HTML widgets with enhancement keywords are also protected automatically.',
            'enforcement' => 'On existing pages, /elementor/page updates, /elementor/update, and /elementor/patch block removal or byte-level modification of protected zones by default.',
            'override' => 'Only change a protected zone when the task explicitly requires it. Send allow_protected_zone_changes=true and a concrete protected_zone_reason of at least 10 characters, then verify the public animation after write.',
            'agent_rule' => 'Do not use an override for unrelated typography, spacing, or layout work. Preserve the protected widget, its IDs/classes, script order, and dependencies byte-for-byte.',
        ],
        'repeated_agent_error_audit_policy' => [
            'returned_in' => [ 'POST /elementor/visual-audit', 'POST /audit' ],
            'rule' => 'Agents must read repeated_agent_error_audit and fix every fail check before claiming success; warn checks must be either fixed or explicitly explained.',
            'checks' => [
                'legacy_sections_columns',
                'snake_case_widget_type',
                'html_widget_layout_or_content',
                'script_injected_native_css',
                'heading_typography_important_override',
                'excessive_local_typography_overrides',
                'design_system_marker_drift',
                'fixed_px_layout_risk',
            ],
        ],
        'runtime_elementor_validation' => [
            'required' => true,
                'rule' => 'The executor validates changed _elementor_data after each /run call and rejects invalid Elementor JSON, missing design-system markers, and failed preflight even if the agent ignored the guide.',
                'normalize_endpoint' => [
                    'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/normalize',
                    'writes' => false,
                    'rule' => 'Use this before saving when JSON has legacy section/column layout, widget_type, missing widgetType, missing settings arrays, missing elements arrays, or incomplete container defaults.',
                    'returns' => [
                        'normalized_elementor_data',
                        'change_counts',
                        'changes',
                        'before_errors',
                        'after_errors',
                        'stats',
                    ],
                ],
                'blocked' => [
                    'Legacy elType=section.',
                    'Legacy elType=column.',
                    'Snake-case widget_type.',
                    'Any elType=widget element with missing or empty widgetType.',
                ],
                'rollback' => 'If invalid _elementor_data is detected, the changed _elementor_data meta is rolled back to its pre-run value when possible.',
            ],
            'blueprint_policy' => [
                'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/blueprint',
                'writes' => false,
                'rule' => 'Before building a new Elementor page, call /elementor/blueprint with subject, audience, goal, offer, language, style, proof_points, and CTA labels.',
                'returns' => [
                    'design_tokens',
                    'section plan',
                    'recipe IDs and variants',
                    'slot suggestions',
                    'native widget requirements',
                    'HTML enhancement zones',
                    'design quality gates',
                    'agent workflow',
                ],
                'usage' => [
                    'Treat the blueprint as the page contract.',
                    'Use recipes when recipe_available=true.',
                    'For custom sections, use native Flexbox Container primitives and then normalize/validate.',
                    'Do not write a page until the intended sections map back to the blueprint.',
                ],
            ],
            'recipes_policy' => [
                'list_endpoint' => 'GET /wp-json/ai-executor/v1/elementor/recipes',
                'recipe_endpoint' => 'GET /wp-json/ai-executor/v1/elementor/recipes/{id}',
                'compose_endpoint' => 'POST /wp-json/ai-executor/v1/elementor/compose',
                'writes' => false,
                'rule' => 'Recipes are composition patterns, not rigid templates. Use variants and slots to build complex native Elementor sections while preserving editor usability.',
                'available_sections' => [
                    'hero.editorial',
                    'feature.grid',
                    'process.steps',
                    'pricing.comparison',
                    'faq.accordion',
                    'cta.band',
                    'proof.timeline',
                    'contact.block',
                ],
                'available_primitives' => [
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
                'workflow' => [
                    'Call /elementor/recipes to inspect available patterns.',
                    'Call /elementor/recipes/{id} to inspect slots and variants.',
                    'Call /elementor/compose with recipe_id, variant, and slots.',
                    'Run /elementor/normalize and /elementor/validate on the returned elementor_data before saving.',
                ],
                'constraints' => [
                    'Layout must remain native Flexbox Containers.',
                    'Main content must remain native editable widgets.',
                    'HTML widgets are allowed only as scoped enhancement zones, not main layout.',
                    'All required slots should be filled with project-specific content.',
                ],
            ],
            'rollback_policy' => [
                'endpoint' => 'POST /wp-json/ai-executor/v1/rollback',
                'storage' => 'Short-lived snapshots are stored in wp_options, never in files.',
                'ttl_seconds' => WPAE_ROLLBACK_TTL_SECONDS,
                'rule' => 'For structured Elementor writes, read rollback_snapshot_id and rollback_expires_at from the response. To revert, call /rollback with the snapshot_id and valid guide-token headers. Rollback validates _elementor_data before replacing metadata; if it fails, the snapshot is retained and the agent must stop, report the endpoint/status/error details, and never bypass recovery through /run, WP Admin, or browser automation.',
                'dry_run' => [
                    '/elementor/page' => 'Pass dry_run=true to validate the requested create/update without writing.',
                    '/elementor/update' => 'Pass dry_run=true to validate the requested metadata update without writing.',
                    '/elementor/patch' => 'Pass dry_run=true to validate targeted element_id/property changes without writing.',
                    '/run' => 'Arbitrary PHP dry_run is not supported because the plugin cannot reliably simulate unknown mutations. Use rollback_targets instead.',
                ],
                'run_rollback_targets' => [
                    'body' => [
                        'code' => 'return update_option("example_option", "new-value");',
                        'rollback_targets' => [
                            'post_ids' => [ 123 ],
                            'option_names' => [ 'example_option' ],
                        ],
                    ],
                    'rule' => 'Before risky /run mutations, pass known post_ids and option_names so the plugin captures a rollback snapshot first.',
                ],
            ],
            'operation_logs_policy' => [
                'endpoint' => 'GET /wp-json/ai-executor/v1/logs',
                'storage' => 'Recent operation metadata is stored in wp_options with a capped entry count.',
                'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
                'logged' => [
                    'endpoint',
                    'method',
                    'status',
                    'actor hint',
                    'guide hash',
                    'target IDs',
                    'rollback snapshot ID',
                    'validation summary',
                ],
                'redacted' => [
                    'API keys',
                    'guide tokens',
                    'raw request bodies',
                    'raw page payloads',
                    'raw response payloads',
                    'secrets',
                ],
            ],
            'elementor_visual_audit' => [
                'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/visual-audit',
                'input' => 'Pass post_id for a saved page or elementor_data to audit before writing.',
                'rule' => 'After composing or writing a page, run visual-audit and fix weak/blocked results before claiming completion.',
                'checks' => [
                    'runtime_elementor_contract' => 'Flexbox Container contract, widgetType, and validation errors.',
                    'native_background_coverage' => 'Important backgrounds are present in native Elementor settings.',
                    'native_text_color_coverage' => 'Readable text colors are not only inherited or CSS-only.',
                    'native_spacing_coverage' => 'Container padding/gap are present as native settings.',
                    'typography_hierarchy' => 'Native heading hierarchy exists.',
                    'native_cta' => 'A native button CTA exists and is styled.',
                    'responsive_settings' => 'Mobile/tablet settings exist for complex layouts.',
                    'html_widget_scope' => 'HTML widgets are enhancement-only.',
                    'native_content_complete' => 'No empty heading/text widgets.',
                    'palette_variety' => 'Palette variety is detectable from native settings.',
                ],
            ],
            'public_visual_audit' => [
                'endpoint' => 'POST /wp-json/ai-executor/v1/visual-audit',
                'input' => 'Pass post_id or same-site url after a page is public.',
                'rule' => 'Run the read-only public HTML audit after writes and report/fix weak or blocked results before claiming completion.',
                'checks' => [
                    'public_fetch' => 'The permalink is reachable without WP Admin authentication.',
                    'html_structure' => 'Viewport meta, title, and visible copy are present.',
                    'horizontal_overflow_risk' => 'Large fixed width/min-width styles are flagged.',
                    'invisible_text_risk' => 'Hidden or transparent text patterns are flagged.',
                    'empty_block_risk' => 'Suspicious empty Elementor/WPAE blocks are flagged.',
                    'cta_presence' => 'A public link/button CTA is detectable.',
                    'mobile_first_css_signal' => 'Mobile responsive CSS signals are visible.',
                ],
                'limitations' => 'No screenshots, true rendered overflow, computed contrast cascade, or animation timing.',
            ],
            'agent_conformance_policy' => [
                'returned_in_responses' => true,
                'included_in_logs' => true,
                'rule' => 'Every scored endpoint returns agent_conformance with score, level, criteria, and blocking_errors. A weak or blocked level means the agent should correct the operation or run verification before claiming success.',
                'criteria' => [
                    'guide_token_flow' => 'Write endpoints should use the required guide token and guide hash.',
                    'file_policy' => 'No forbidden filesystem operations or scratch files.',
                    'elementor_policy' => 'Elementor data passes runtime validation.',
                    'native_flex_containers' => 'Elementor layout uses Flexbox Containers and no legacy section/column layout.',
                    'widget_type_integrity' => 'Widgets use camelCase widgetType and never widget_type.',
                    'native_visual_settings' => 'Critical visual state is present in native Elementor settings where detectable.',
                    'typography_hierarchy' => 'Native heading widgets create a real visual/content hierarchy.',
                    'spacing_consistency' => 'Native container padding and gaps are used consistently.',
                    'cta_visibility' => 'A native Elementor button exists for the primary action.',
                    'mobile_readiness' => 'Responsive Elementor settings are present for complex sections.',
                    'palette_quality' => 'The palette has enough variety without becoming noisy.',
                    'content_completeness' => 'Native heading/text widgets are populated and not placeholder-empty.',
                    'verification_signal' => 'Use /audit or X-WPAE-Verified: 1 after writes.',
                ],
                'design_quality_bar' => [
                    'Treat weak typography hierarchy, missing CTA, sparse native spacing, missing responsive settings, or empty content as unfinished work.',
                    'Prefer fixing native Elementor settings first; scoped CSS may reinforce but should not hide missing editor settings.',
                    'If a complex section cannot fit an existing recipe, compose from native container/widget primitives and validate with /elementor/normalize and /elementor/validate.',
                ],
            ],
            'native_elementor_first' => [
                'required' => true,
                'rule' => 'Build page structure and content from native Elementor Flexbox Containers and widgets so the user can edit content and styling in the Elementor editor panel. Use elType=container for all layout nodes.',
                'layout_system' => [
                    'required' => 'Elementor Flexbox Containers only.',
                    'allowed_layout_eltypes' => [
                        'container',
                    ],
                    'forbidden_legacy_eltypes' => [
                        'section',
                        'column',
                    ],
                    'rule' => 'Do not create, import, or preserve legacy Section/Column layouts. Convert every layout wrapper to nested containers with flex_direction, content_width, width, gap, padding, and responsive settings.',
                ],
                'allowed_widget_types' => [
                    'heading',
                    'text-editor',
                    'button',
                    'icon-list',
                    'image',
                    'image-box',
                    'icon-box',
                    'divider',
                    'spacer',
                    'counter',
                    'progress',
                    'testimonial',
                    'tabs',
                    'accordion',
                    'toggle',
                ],
                'forbidden_widget_types' => [
                    'shortcode',
                ],
                'conditional_widget_types' => [
                    'html' => 'Allowed only for small JavaScript snippets or complex CSS enhancements that cannot reasonably be expressed with native Elementor settings. Never use it as the main page markup/content/layout container.',
                ],
                'native_style_settings' => [
                    'required' => true,
                    'rule' => 'Element style changes must be made in native Elementor settings/style controls first.',
                    'typography_editability_rule' => 'Native typography does not mean every widget must receive explicit typography_* values. Repeated local typography_font_family, typography_font_size, typography_font_weight, typography_line_height, typography_letter_spacing, typography_text_transform, typography_font_style, typography_text_decoration, or typography_word_spacing settings block global typography edits. Use local typography only where the design intentionally needs a one-off exception.',
                    'unlock_endpoint' => 'If a page already has excessive local typography overrides, use POST /wp-json/ai-executor/v1/elementor/typography-unlock with dry_run=true first, then apply it with preserve_ids/preserve_classes for intentional exceptions.',
                    'override_resolution_endpoint' => 'If an HTML widget CSS rule uses !important to override native heading typography, use POST /wp-json/ai-executor/v1/elementor/resolve-typography-overrides with dry_run=true and explicit native_typography_patches. It only relaxes typography !important tokens on heading selectors and preserves the rest of the HTML widget.',
                    'examples' => [
                        'Use background_color/background_background for section and card backgrounds.',
                        'Use title_color/text_color/button color controls for readable text and CTA contrast.',
                        'Use border, radius, padding, margin, gap, width, min-height, flex direction, wrap, justify, align, and responsive controls for layout and spacing.',
                        'Use _css_classes only to attach scoped enhancements after native settings are present.',
                    ],
                    'css_exception' => 'Use CSS only when a native setting cannot express the requested behavior or when refining an already native-styled element.',
                ],
                'content_placement' => [
                    'Headlines must live in heading widget settings.title.',
                    'Body copy must live in text-editor widget settings.editor.',
                    'Calls to action must live in button widget settings.text and settings.link.',
                    'Lists must live in icon-list repeater settings when practical.',
                    'Cards, columns, grids, hero panels, and sections must be Flexbox Containers with settings and child widgets.',
                    'Critical visual state required for readability must live in native Elementor settings first: background_color, text color, border, border_radius, padding, margin, width, min-height, gap, and alignment.',
                ],
                'forbidden_patterns' => [
                    'Do not create temporary loader files, mu-plugins, helper PHP files, external CSS/JS files, JSON/base64 payload files, or scratch files anywhere on the server.',
                    'Do not use legacy Elementor sections or columns: elType=section and elType=column are forbidden.',
                    'Do not put full page markup into an Elementor HTML widget.',
                    'Do not use inline CSS/JS blobs to fake the main page structure.',
                    'Do not replace editable Elementor controls with opaque HTML.',
                    'Do not use CSS as the default way to change element styling when Elementor has native style controls.',
                    'Do not use CSS-only critical backgrounds, contrast, spacing, borders, or typography when native settings can express them.',
                    'Do not rely on enhancement CSS as the only source for essential backgrounds, contrast, spacing, or card borders.',
                ],
                'verification' => [
                    'Confirm the solution did not create or require any external files.',
                    'Traverse _elementor_data recursively.',
                    'Confirm all layout elements are elType=container and all content elements are allowed native widgets.',
                    'Confirm there are zero elements with elType=section or elType=column.',
                    'If html widgets exist, confirm each one is limited to JS or complex CSS enhancements, not page content/layout.',
                    'Confirm important text lives in heading/text-editor/button/icon-list settings.',
                    'Confirm each changed element style exists in native Elementor settings first, with CSS only as an explained exception.',
                    'Confirm unrelated existing CSS/JS/WebGL/Three.js/GSAP/animation enhancements were preserved.',
                    'Confirm any visually critical card, panel, hero, or dark section has native Elementor background and border settings before relying on CSS.',
                ],
            ],
            'html_enhancement_policy' => [
                'allowed' => true,
                'rule' => 'HTML widget CSS/JS/WebGL is exceptional enhancement only. If Elementor has a native control for the style, set the native control first, but preserve existing working enhancements unless the user explicitly asks to change them.',
                'allowed_for' => [
                    'Google Fonts or other font loading when the site has no better typography pipeline.',
                    'Scoped CSS polish for classed Elementor containers/widgets.',
                    'Small JavaScript interactions such as scroll reveal, hover helpers, tabs state, counters, or progressive enhancement.',
                    'Complex responsive CSS that Elementor settings cannot express cleanly.',
                    'WebGL, Three.js, GSAP, canvas, shader, or animation enhancements that cannot be represented as native Elementor settings.',
                ],
                'requirements' => [
                    'Scope CSS under project-specific classes, e.g. .wpae-* or page-specific .mz-*.',
                    'Do not target .elementor-widget-container as the primary selector.',
                    'Never inject <style> with JavaScript for properties that Elementor can edit natively; this is blocked by html_widget_script_injected_css validation.',
                    'Use !important only as an enhancement fallback for scoped selectors when Elementor/theme CSS wins specificity.',
                    'Do not use !important to compensate for missing native Elementor settings on critical backgrounds, colors, borders, spacing, or layout.',
                    'When CSS is used, state why native Elementor settings were insufficient.',
                    'When an existing HTML widget contains CSS/JS/WebGL/Three.js/GSAP/canvas code unrelated to the requested change, preserve it unchanged.',
                    'Respect prefers-reduced-motion for animation.',
                    'Use vanilla JavaScript in an IIFE; avoid jQuery unless WordPress/Elementor dependency forces it.',
                    'HTML widget must be enhancement-only and removable without losing the page content.',
                ],
            ],
            'page_meta' => [
                '_elementor_edit_mode' => 'builder',
                '_elementor_template_type' => 'wp-page',
                '_elementor_version' => 'Use ELEMENTOR_VERSION when defined.',
                '_elementor_data' => 'JSON-encoded Elementor element array, stored with wp_slash().',
                '_wp_page_template' => 'elementor_canvas for full landing pages when appropriate.',
            ],
            'element_shape' => [
                'container' => [
                    'id' => 'unique 7-8 character string',
                    'elType' => 'container',
                    'isInner' => false,
                    'settings' => 'Container/Flexbox settings.',
                    'elements' => 'Nested widgets or containers.',
                ],
                'widget' => [
                    'id' => 'unique 7-8 character string',
                    'elType' => 'widget',
                    'widgetType' => 'Required camelCase key. Native editable Elementor widget, e.g. heading, text-editor, button, icon-list, image, divider, spacer. HTML widget is allowed only for JS or complex CSS enhancements, never for main layout/content.',
                    'isInner' => false,
                    'settings' => 'Widget control values.',
                    'elements' => [],
                ],
            ],
            'elementor_data_rules' => [
                'Use recursive arrays of containers and widgets.',
                'Every element needs id, elType, isInner, settings, and elements.',
                'Every widget element must use the exact camelCase key widgetType. Never use widget_type, widget_type_name, type, or name as substitutes.',
                'If elType is widget and widgetType is missing or empty, Elementor will render an empty/broken widget; treat this as a blocker.',
                'Never emit legacy elType=section or elType=column. If source data contains them, convert to nested elType=container before saving.',
                'Use Flexbox Container settings for layout: flex_direction, content_width, width, min_height, gap, padding, margin, justify_content, align_items, flex_wrap, and responsive variants.',
                'Use rem/em where practical for gap, padding, margin, typography, radii, and component dimensions; use vh/svh/min-height for viewport sections and percentages/flex/max-width for widths.',
                'Use deterministic short ids when possible so future updates can target stable elements.',
                'Use _css_classes in settings to attach scoped enhancement styles.',
                'For readable sections and cards, duplicate essential styling in settings before adding CSS: background_background, background_color, title/text colors, border_border, border_color, border_width, border_radius, padding, margin, gap, width, min-height, and alignment.',
                'Clear/regenerate Elementor CSS cache after writing page data when Elementor classes are available.',
            ],
            'verification_checklist' => [
                'HTTP status for permalink is 200.',
                'Post status is publish unless the user requested draft.',
                '_wp_page_template is elementor_canvas for full landing pages when appropriate.',
                '_elementor_edit_mode is builder.',
                '_elementor_data decodes as JSON array.',
                '_elementor_data contains no legacy section or column elements.',
                'Every elType=widget element has non-empty widgetType and no widget_type key.',
                'Core text is stored in native widget settings, not opaque HTML.',
                'Any html widget is enhancement-only.',
                'Critical backgrounds, borders, spacing, and contrast are present in native Elementor settings, with CSS only refining or reinforcing them.',
                'Changed element styles are stored in native Elementor settings first; CSS usage is scoped and justified as an exception.',
                '/visual-audit passes or returns only explained warnings.',
                'No external files, temporary loaders, mu-plugins, scratch files, or filesystem writes were created or required.',
                'Desktop and mobile layout should not have obvious overlap or horizontal overflow.',
            ],
            'php_snippet' => wpae_elementor_page_snippet(),
        ],
        'security' => [
            'Treat X-AI-Key as sensitive WordPress automation access, restricted by site-owner capability toggles and guide-token flow.',
            'Never ask for or use WP Admin login/password, admin cookies, REST nonces, or browser sessions.',
            'Never commit, log, or expose real keys in frontend code.',
            'Never create temporary loaders, mu-plugins, PHP/JS/CSS/JSON/base64 files, or scratch files on the server.',
            'Never use Playwright, WP Admin, or the Elementor editor to make writes; use browser automation only for public-page verification after REST API writes.',
            'Filesystem write/delete operations are blocked by default in /run; do not ask agents to bypass this.',
            'Prefer server/firewall IP restrictions for production.',
            'Run read-only checks before writes and verify after writes.',
        ],
    ];
}

function wpae_agent_prompt(): string {
    return <<<'PROMPT'
You are operating a remote WordPress site through WP AI Executor.
Current runtime notes: rollback restores only plugin-managed Elementor/WPAE meta plus the post record and does not remove unrelated third-party post meta; /elementor/page attempts to delete a newly created page if Elementor metadata saving fails and reports cleanup.created_post_deleted; /media/upload rejects files whose binary signature does not match mime_type; /exports/create stores short-lived JSON in wp_options and returns an authenticated /exports/{id} endpoint, never a public uploads file; use GET /exports for metadata and POST /exports/prune to remove expired export records.
Typography editability note: if the user should control typography globally or through Elementor design roles, do not hardcode typography_* settings on every widget; repeated local typography overrides block global edits. Use /elementor/typography-unlock with dry_run=true to remove excessive local typography overrides while preserving intentional exceptions. If an HTML widget CSS selector with !important is blocking a native heading control, do not delete or rewrite the HTML widget: call /elementor/resolve-typography-overrides with dry_run=true and explicit native_typography_patches, then verify the returned report before saving.
Elementor editor editability note: design properties must stay editable through native Elementor controls/settings. Do not move editable properties into CSS/HTML, and do not remove native settings unless the user explicitly asks for global inheritance from Elementor styles/design tokens.
Before writing, fetch and follow this guide as the source of truth. Inspect the environment first. Read /capabilities and respect site-owner capability toggles; a disabled capability is a hard stop even with a valid key. Read every enabled custom_skills item in this guide before any WordPress/Elementor work and apply those skills as mandatory project rules by priority; if a skill conflicts with WP AI Executor policy, WP AI Executor policy wins. Apply embedded jezweb_claude_skills where relevant for WordPress/Elementor, landing pages, design review, color palettes, responsiveness, and production verification, but WP AI Executor rules override upstream instructions whenever they conflict. Before creating a page or adding a new page block, call /elementor/design-system and treat its system_id, required_root_classes, palette, typography roles, spacing, radii, button style, and tone as the single style source for all current and future blocks. Design mobile first: plan the mobile stack, type scale, spacing, CTA visibility, tap targets, section order, and responsive Elementor settings before expanding tablet and desktop layouts. All top-level page/block containers must include the returned required_root_classes in settings._css_classes; /elementor/page and /elementor/update reject writes that miss this contract. Read project_design_tokens from the guide and use them as the site visual system. Write endpoints require a guide token: call /guide/session, read /guide and /capabilities, call /guide/ack, then send X-WPAE-Guide-Token and X-WPAE-Guide-Hash with every write request. Never request or use WP Admin login/password, admin cookies, WordPress REST nonces, or browser sessions; if auth fails, report endpoint/status/body and verify X-AI-Key instead of asking for credentials. Never use Playwright, WP Admin, or Elementor editor to write changes; browser automation is allowed only to inspect public pages after REST API writes. Never create external files on the WordPress server: no temporary loaders, mu-plugins, helper PHP files, CSS/JS/JSON/base64 payload files, scratch files, or files in /tmp. Use WordPress APIs and Elementor metadata only; /run blocks common filesystem write/delete operations by default. Prefer /elementor/design-system, /elementor/blueprint, /elementor/recipes, /elementor/compose, /elementor/normalize, /elementor/validate, /elementor/visual-audit, /elementor/typography-unlock, /visual-audit, /elementor/page, and /elementor/update over raw PHP for Elementor pages. Never use /run to bypass /elementor/update, design-system marker classes, dry_run, or Elementor preflight; direct _elementor_data changes through /run are validated by the same design-system/preflight contract and rolled back on failure. If an existing page has old or different wpae-system-* marker classes, migrate it to the current main design system with /elementor/normalize, preserve non-WPAE custom classes, then run /elementor/update dry_run. Before building a new page, call /elementor/blueprint with subject, audience, goal, offer, language, style, proof points, and CTA labels. For complex or non-standard sections, call /elementor/recipes, choose a recipe/variant, then call /elementor/compose with project-specific slots. Use /elementor/normalize before saving when JSON has legacy section/column layout, widget_type, missing widgetType, missing settings, missing elements arrays, incomplete container defaults, or missing design-system marker classes. Use /elementor/visual-audit on composed elementor_data before writing and on post_id after writing; use /visual-audit on the public page after writing; fix weak mobile_readiness, public HTML warnings, or other blocked audit results before claiming completion. Use dry_run=true on /elementor/page or /elementor/update before complex writes; structured writes return preflight and block invalid contracts, empty native content, HTML-widget layout misuse, missing landing-page CTA, and missing native critical visual settings. Successful structured writes return quality_summary with permalink, visual audit score/level, warnings, and fixes; keep correcting until quality_summary has no required fixes and the audit is acceptable. Arbitrary /run dry_run is not supported, so pass rollback_targets.post_ids and rollback_targets.option_names before risky /run mutations. Save rollback_snapshot_id from write responses and call /rollback with snapshot_id if the result must be reverted. For Elementor pages, design first: define subject, audience, single page job, palette, type roles, mobile-first layout, and one distinctive signature element inside the design system. Apply the embedded frontend_design pack to avoid generic pages, apply the wordpress_elementor_dev pack to build editable Elementor output, and apply the jezweb landing-page/design-review/color-palette workflow to produce tangible, polished results. Use only native Elementor Flexbox Containers for layout: elType=container plus editable native widgets. Never use legacy Elementor Sections or Columns; elType=section and elType=column are forbidden and must be converted to containers before saving. Every widget must use the exact camelCase widgetType key; widget_type is forbidden and causes empty widgets. Put all Elementor-supported styles into native settings/style controls: typography_font_family, typography_font_size plus mobile/tablet variants, typography_font_weight, typography_line_height with unitless ratio for multi-line text, typography_letter_spacing, typography_text_transform, title_color/text_color, background_background/background_color/background_color_b/background_gradient_type/background_gradient_angle/background_gradient_position, background_overlay_background/background_overlay_color/background_overlay_color_b, border_border/border_color, padding, margin, border_radius, min_height, flex_direction, justify_content, align_items, gap, flex_wrap, native z-index controls (_z_index/z_index), and native Elementor positioning/sticky controls (_position/sticky/sticky_on/sticky_offset/sticky_effects_offset/sticky_parent) when available. Elementor-generated CSS from native settings is expected and editable in the Elementor panel; do not treat it as forbidden external CSS. Properties listed in css_to_native_map must not be authored through Custom CSS, HTML widget CSS, external files, or <script>-injected <style>; if they appear there, migrate the value to native Elementor settings and remove the CSS declaration. CSS is an exception only for animations, pseudo-elements, WebGL/canvas, gradients/patterns that Elementor Group_Control_Background cannot express, fixed/sticky global overlays or off-canvas UI when native positioning controls are insufficient, custom systemic z-index layers when native z-index controls are insufficient, Google Fonts @import, media queries Elementor cannot express, hover/focus polish, browser fixes, or specificity conflicts after native settings are present; scoped CSS, including selective !important, may reinforce or refine styles but must not be the default styling method or the only source of essential contrast or layout. Native style migration must be targeted to requested elements only: do not rewrite the whole page, do not remove working scoped CSS/JS/WebGL/Three.js/GSAP/canvas/shader/animation code, and preserve unrelated HTML widget content, classes, IDs, script order, and dependencies unless the user explicitly asks to change them. Prefer rem/em for spacing, typography, gap, padding, margin, radii, and component dimensions; use vh/svh/min-height for viewport-height sections; use percentages, flex basis, max-width, and responsive constraints for widths. Use px only for hairline borders, tiny icons/controls, shadows, or Elementor compatibility exceptions. After changing native settings, clear Elementor CSS cache: delete_post_meta(post_id, "_elementor_css"), delete_option("_elementor_global_css"), Elementor files cache when available, and rocket_clean_domain() when WP Rocket exists. The Elementor HTML widget is allowed only for small JavaScript snippets, WebGL/canvas/animation code, or complex CSS enhancements when native settings are not enough; never use it as the main page markup/content/layout container. Never inject <style> via JavaScript for native Elementor properties; html_widget_script_injected_css is a blocking validation error. Do not use shortcode widgets, Oxygen, or Novamira for page layout/content. After writing, rollback, typography migration, or revision restore, run /audit, /elementor/visual-audit, /visual-audit, and real browser screenshot verification of the public URL; HTML/CSS audits alone are not enough. Then complete the verification checklist: published URL, Elementor meta, decoded _elementor_data, public HTML audit, zero section/column elements, no external files, native widget content placement, native critical visual settings, native style settings before CSS, preserved unrelated enhancements, mobile-first responsive settings, design-system markers, and html widgets enhancement-only. Read preflight, quality_summary, visual audits, repeated_agent_error_audit, and agent_conformance in responses and fix every failed repeated-agent check plus weak or blocked criteria before claiming completion; design quality gates require native heading hierarchy, native spacing, visible CTA, mobile-first responsive settings, deliberate palette, consistent design system, responsive unit policy, and populated native content. If any endpoint or verification step fails, include concrete error details in the response: endpoint/action, HTTP status or exception, plugin error code/message, details/preflight/blocking_errors, and the next safe fix. Use /logs for recent operation metadata when debugging; logs never include API keys, guide tokens, raw request bodies, raw page payloads, or secrets. Do not expose API keys.
PROMPT;
}

function wpae_elementor_page_snippet(): string {
    return <<<'PHP'
$page_id = wp_insert_post([
    'post_title'  => 'Landing Page',
    'post_name'   => 'landing-page',
    'post_status' => 'publish',
    'post_type'   => 'page',
], true);

if ( is_wp_error( $page_id ) ) {
    return [ 'ok' => false, 'error' => $page_id->get_error_message() ];
}

$elementor_data = [
    [
        'id'       => 'hero01',
        'elType'   => 'container',
        'isInner'  => false,
        'settings' => [
            'content_width'  => 'boxed',
            'flex_direction' => 'column',
        ],
        'elements' => [
            [
                'id'         => 'title01',
                'elType'     => 'widget',
                'widgetType' => 'heading',
                'isInner'    => false,
                'settings'   => [
                    'title'       => 'Landing headline',
                    'header_size' => 'h1',
                ],
                'elements'   => [],
            ],
        ],
    ],
];

update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );

return [ 'ok' => true, 'id' => $page_id, 'url' => get_permalink( $page_id ) ];
PHP;
}

// ── Страница настроек ──────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_options_page(
        'WP AI Executor',
        'AI Executor',
        'manage_options',
        'wp-ai-executor',
        'wpae_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'wpae_settings', 'wp_ai_executor_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    // Обработка регенерации ключа.
    if (
        isset( $_POST['wpae_regenerate'] ) &&
        check_admin_referer( 'wpae_regenerate_key' )
    ) {
        update_option( 'wp_ai_executor_key', bin2hex( random_bytes( 32 ) ) );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&regenerated=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_capabilities'] ) &&
        check_admin_referer( 'wpae_save_capabilities' )
    ) {
        $input = isset( $_POST['wpae_capabilities'] ) && is_array( $_POST['wpae_capabilities'] )
            ? wp_unslash( $_POST['wpae_capabilities'] )
            : [];

        wpae_update_capability_settings( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&capabilities_saved=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_apply_capability_preset'] ) &&
        check_admin_referer( 'wpae_apply_capability_preset' )
    ) {
        $preset_id = sanitize_key( (string) wp_unslash( $_POST['wpae_capability_preset'] ?? '' ) );
        $result = wpae_apply_capability_preset( $preset_id ) ? 'capability_preset_saved' : 'capability_preset_error';
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . $result . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_design_tokens'] ) &&
        check_admin_referer( 'wpae_save_design_tokens' )
    ) {
        $input = isset( $_POST['wpae_design_tokens'] ) && is_array( $_POST['wpae_design_tokens'] )
            ? wp_unslash( $_POST['wpae_design_tokens'] )
            : [];

        wpae_update_project_design_tokens( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&design_tokens_saved=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_skill_ui'] ) &&
        check_admin_referer( 'wpae_save_skill_ui' )
    ) {
        $raw_enforce = isset( $_POST['wpae_skill_enforce'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_enforce'] ) )
            : '';
        $enforce = [];

        if ( $raw_enforce !== '' ) {
            $decoded = json_decode( $raw_enforce, true );
            if ( is_array( $decoded ) ) {
                $enforce = $decoded;
            } else {
                wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_error=1' ) );
                exit;
            }
        }

        $skill = wpae_upsert_skill( [
            'id' => isset( $_POST['wpae_skill_id'] ) ? wp_unslash( $_POST['wpae_skill_id'] ) : '',
            'name' => isset( $_POST['wpae_skill_name'] ) ? wp_unslash( $_POST['wpae_skill_name'] ) : '',
            'description' => isset( $_POST['wpae_skill_description'] ) ? wp_unslash( $_POST['wpae_skill_description'] ) : '',
            'content' => isset( $_POST['wpae_skill_content'] ) ? wp_unslash( $_POST['wpae_skill_content'] ) : '',
            'enforce' => $enforce,
            'enabled' => ! empty( $_POST['wpae_skill_enabled'] ),
            'priority' => isset( $_POST['wpae_skill_priority'] ) ? wp_unslash( $_POST['wpae_skill_priority'] ) : 0,
        ] );

        $result = is_wp_error( $skill ) ? 'skill_error' : 'skill_saved';
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . $result . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_delete_skill_ui'] ) &&
        check_admin_referer( 'wpae_delete_skill_ui' )
    ) {
        $id = wpae_normalize_skill_id( isset( $_POST['wpae_delete_skill_id'] ) ? (string) wp_unslash( $_POST['wpae_delete_skill_id'] ) : '' );
        $skills = wpae_get_skill_store();

        if ( isset( $skills[ $id ] ) ) {
            unset( $skills[ $id ] );
            wpae_update_skill_store( $skills );
        }

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_deleted=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_import_skills_ui'] ) &&
        check_admin_referer( 'wpae_import_skills_ui' )
    ) {
        $bundle = isset( $_POST['wpae_skill_bundle_json'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_bundle_json'] ) )
            : '';
        $mode = isset( $_POST['wpae_skill_import_mode'] )
            ? sanitize_key( (string) wp_unslash( $_POST['wpae_skill_import_mode'] ) )
            : 'merge';
        $items = wpae_extract_skill_import_items( $bundle );
        $result = is_wp_error( $items ) ? $items : wpae_import_skill_items( $items, $mode );

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'skill_import_error' : 'skill_imported' ) . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_import_skill_url_ui'] ) &&
        check_admin_referer( 'wpae_import_skill_url_ui' )
    ) {
        $source_url = isset( $_POST['wpae_skill_source_url'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_source_url'] ) )
            : '';
        $raw_url = wpae_normalize_github_skill_url( $source_url );
        $result = $raw_url;

        if ( ! is_wp_error( $raw_url ) ) {
            $response = wp_remote_get( $raw_url, [
                'timeout' => 20,
                'redirection' => 3,
                'limit_response_size' => 150000,
            ] );

            if ( is_wp_error( $response ) ) {
                $result = $response;
            } elseif ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
                $result = new WP_Error( 'wpae_skill_download_failed', 'Skill download returned non-200 status.' );
            } else {
                $content = (string) wp_remote_retrieve_body( $response );
                $request_data = [
                    'id' => isset( $_POST['wpae_skill_url_id'] ) ? wp_unslash( $_POST['wpae_skill_url_id'] ) : '',
                    'name' => isset( $_POST['wpae_skill_url_name'] ) ? wp_unslash( $_POST['wpae_skill_url_name'] ) : '',
                    'description' => '',
                    'enabled' => ! empty( $_POST['wpae_skill_url_enabled'] ),
                    'priority' => isset( $_POST['wpae_skill_url_priority'] ) ? wp_unslash( $_POST['wpae_skill_url_priority'] ) : 10,
                    'enforce' => [],
                ];
                $trimmed = ltrim( $content );

                if ( $trimmed !== '' && ( $trimmed[0] === '{' || $trimmed[0] === '[' ) ) {
                    $decoded = json_decode( $content, true );
                    $items = wpae_extract_skill_import_items( $decoded );
                    if ( is_wp_error( $items ) ) {
                        $result = $items;
                    } else {
                        foreach ( $items as &$item ) {
                            if ( is_array( $item ) ) {
                                $item['source_url'] = $raw_url;
                                $item['source_type'] = 'github_bundle';
                                $item['source_sha256'] = hash( 'sha256', $content );
                                $item['imported_at'] = gmdate( 'c' );
                            }
                        }
                        unset( $item );
                        $result = wpae_import_skill_items( $items, 'merge' );
                    }
                } else {
                    $result = wpae_upsert_skill( wpae_build_skill_from_markdown( $content, $raw_url, $request_data ) );
                }
            }
        }

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'skill_url_error' : 'skill_url_imported' ) . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_prune_exports_ui'] ) &&
        check_admin_referer( 'wpae_prune_exports_ui' )
    ) {
        $before = wpae_get_export_store();
        $after = wpae_prune_export_store( $before );
        wpae_update_export_store( $after );

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&exports_pruned=' . max( 0, count( $before ) - count( $after ) ) ) );
        exit;
    }
} );

function wpae_settings_page() {
    $key                = wpae_get_key();
    $site_url           = get_rest_url( null, 'ai-executor/v1/run' );
    $guide_url          = get_rest_url( null, 'ai-executor/v1/guide' );
    $capabilities_url   = get_rest_url( null, 'ai-executor/v1/capabilities' );
    $logs_url           = get_rest_url( null, 'ai-executor/v1/logs' );
    $regen              = isset( $_GET['regenerated'] );
    $capabilities_saved = isset( $_GET['capabilities_saved'] );
    $capability_preset_saved = isset( $_GET['capability_preset_saved'] );
    $capability_preset_error = isset( $_GET['capability_preset_error'] );
    $design_tokens_saved = isset( $_GET['design_tokens_saved'] );
    $skill_saved        = isset( $_GET['skill_saved'] );
    $skill_deleted      = isset( $_GET['skill_deleted'] );
    $skill_error        = isset( $_GET['skill_error'] );
    $skill_imported     = isset( $_GET['skill_imported'] );
    $skill_import_error = isset( $_GET['skill_import_error'] );
    $skill_url_imported = isset( $_GET['skill_url_imported'] );
    $skill_url_error    = isset( $_GET['skill_url_error'] );
    $exports_pruned     = isset( $_GET['exports_pruned'] ) ? absint( $_GET['exports_pruned'] ) : null;
    $capabilities       = wpae_get_capability_settings();
    $capability_labels  = wpae_capability_labels();
    $capability_presets = wpae_capability_presets();
    $design_tokens      = wpae_get_project_design_tokens();
    $skills             = wpae_sort_skills( wpae_get_skill_store() );
    $operation_logs     = array_slice( wpae_get_operation_logs_store(), 0, 8 );
    $exports_summary    = wpae_build_exports_summary( wpae_get_export_store() );
    $skill_bundle_json  = wp_json_encode( wpae_build_skill_bundle(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    $enabled_count      = count( array_filter( $capabilities ) );
    $total_count        = count( $capabilities );
    $filesystem_locked  = ! wpae_can_run_filesystem_operations();
    ?>
    <style>
        .wpae-dashboard {
            --wpae-bg: #f6f7f9;
            --wpae-panel: #ffffff;
            --wpae-panel-soft: #f8fafc;
            --wpae-text: #111827;
            --wpae-muted: #64748b;
            --wpae-border: #d9e0ea;
            --wpae-accent: #16a34a;
            --wpae-accent-dark: #15803d;
            --wpae-danger: #b91c1c;
            --wpae-code: #0f172a;
            --wpae-code-text: #dbeafe;
            max-width: 1180px;
            color: var(--wpae-text);
        }
        .wpae-dashboard * { box-sizing: border-box; }
        .wpae-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
            gap: 16px;
            align-items: stretch;
            margin: 18px 0;
        }
        .wpae-hero-main,
        .wpae-card {
            background: var(--wpae-panel);
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .wpae-hero-main {
            padding: 24px;
            border-left: 4px solid var(--wpae-accent);
        }
        .wpae-kicker {
            margin: 0 0 8px;
            color: var(--wpae-accent-dark);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .wpae-title {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 0;
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: 0;
        }
        .wpae-version {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #e8f5ee;
            color: #166534;
            font-size: 13px;
            font-weight: 700;
        }
        .wpae-lead {
            max-width: 760px;
            margin: 10px 0 0;
            color: var(--wpae-muted);
            font-size: 14px;
            line-height: 1.55;
        }
        .wpae-status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            padding: 16px;
        }
        .wpae-stat {
            min-height: 78px;
            padding: 14px;
            background: var(--wpae-panel-soft);
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
        }
        .wpae-stat-label {
            margin: 0 0 7px;
            color: var(--wpae-muted);
            font-size: 12px;
            font-weight: 600;
        }
        .wpae-stat-value {
            margin: 0;
            font-size: 22px;
            line-height: 1.1;
            font-weight: 800;
        }
        .wpae-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .wpae-card {
            padding: 18px;
        }
        .wpae-card-wide {
            grid-column: 1 / -1;
        }
        .wpae-card h2 {
            margin: 0 0 6px;
            font-size: 18px;
            line-height: 1.25;
        }
        .wpae-card h3 {
            margin: 18px 0 8px;
            font-size: 14px;
        }
        .wpae-card p {
            margin: 0 0 12px;
            color: var(--wpae-muted);
            line-height: 1.5;
        }
        .wpae-field-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .wpae-input {
            width: 100%;
            min-height: 38px;
            padding: 8px 11px;
            border: 1px solid var(--wpae-border);
            border-radius: 7px;
            background: #fff;
            color: var(--wpae-text);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
        }
        .wpae-button {
            min-height: 38px;
            padding: 7px 12px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 700;
        }
        .wpae-button:focus-visible,
        .wpae-input:focus-visible,
        .wpae-toggle input:focus-visible {
            outline: 2px solid var(--wpae-accent);
            outline-offset: 2px;
        }
        .wpae-danger-button {
            color: var(--wpae-danger) !important;
            border-color: var(--wpae-danger) !important;
        }
        .wpae-code {
            margin: 0;
            padding: 14px;
            overflow-x: auto;
            border-radius: 8px;
            background: var(--wpae-code);
            color: var(--wpae-code-text);
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .wpae-code-light {
            background: #f8fafc;
            color: #1f2937;
            border: 1px solid var(--wpae-border);
        }
        .wpae-textarea {
            width: 100%;
            min-height: 180px;
            padding: 11px;
            border: 1px solid var(--wpae-border);
            border-radius: 7px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: vertical;
        }
        .wpae-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .wpae-form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 700;
        }
        .wpae-section-note {
            margin: 8px 0 0;
            padding: 10px 12px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 12px;
            line-height: 1.45;
        }
        .wpae-color-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .wpae-color-field {
            padding: 12px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }
        .wpae-color-control {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
        }
        .wpae-color-control input[type="color"] {
            width: 44px;
            height: 38px;
            padding: 2px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
        }
        .wpae-color-token {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }
        .wpae-token-pill {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .wpae-skill-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .wpae-skill-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-skill-item h3 {
            margin: 0 0 4px;
            font-size: 14px;
        }
        .wpae-skill-meta {
            color: var(--wpae-muted);
            font-size: 12px;
        }
        .wpae-cap-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .wpae-preset-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 14px 0 16px;
        }
        .wpae-preset {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            min-height: 154px;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-preset strong {
            display: block;
            margin-bottom: 5px;
            color: var(--wpae-text);
        }
        .wpae-preset span {
            display: block;
            color: var(--wpae-muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .wpae-toggle {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            min-height: 92px;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-toggle input {
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }
        .wpae-toggle strong {
            display: block;
            margin-bottom: 4px;
            color: var(--wpae-text);
        }
        .wpae-toggle span {
            display: block;
            color: var(--wpae-muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .wpae-alert {
            margin: 12px 0;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            font-weight: 600;
        }
        .wpae-security {
            border-color: #fde68a;
            background: #fffbeb;
        }
        .wpae-security strong {
            display: block;
            margin-bottom: 8px;
        }
        .wpae-security ul {
            margin: 0 0 0 18px;
            color: #713f12;
        }
        @media (max-width: 960px) {
            .wpae-hero,
            .wpae-grid,
            .wpae-cap-list {
                grid-template-columns: 1fr;
            }
            .wpae-field-row {
                flex-direction: column;
            }
            .wpae-form-grid,
            .wpae-color-grid,
            .wpae-preset-list,
            .wpae-skill-item {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="wrap wpae-dashboard">
        <section class="wpae-hero" aria-labelledby="wpae-title">
            <div class="wpae-hero-main">
                <p class="wpae-kicker">Панель управления агентами</p>
                <h1 id="wpae-title" class="wpae-title">
                    WP AI Executor
                    <span class="wpae-version"><?php echo esc_html( WPAE_VERSION ); ?></span>
                </h1>
                <p class="wpae-lead">
                    REST-мост для Codex, Claude, GPT, Gemini, Qwen и других агентов.
                    Управляйте доступом, проверяйте Elementor-структуру и держите опасные операции под контролем.
                </p>
            </div>

            <div class="wpae-card">
                <div class="wpae-status-grid">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Разрешения</p>
                        <p class="wpae-stat-value"><?php echo esc_html( $enabled_count . '/' . $total_count ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Файловая запись</p>
                        <p class="wpae-stat-value"><?php echo $filesystem_locked ? 'Выкл.' : 'Вкл.'; ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Guide-токен</p>
                        <p class="wpae-stat-value">15 мин</p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Elementor</p>
                        <p class="wpae-stat-value"><?php echo ! empty( $capabilities['elementor_writes'] ) ? 'Вкл.' : 'Выкл.'; ?></p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ( $regen ) : ?>
            <div class="wpae-alert" role="status">Секретный ключ успешно сгенерирован заново.</div>
        <?php endif; ?>

        <?php if ( $capabilities_saved ) : ?>
            <div class="wpae-alert" role="status">Настройки разрешений сохранены.</div>
        <?php endif; ?>

        <?php if ( $capability_preset_saved ) : ?>
            <div class="wpae-alert" role="status">Профиль разрешений применен.</div>
        <?php endif; ?>

        <?php if ( $capability_preset_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось применить профиль разрешений.</div>
        <?php endif; ?>

        <?php if ( $design_tokens_saved ) : ?>
            <div class="wpae-alert" role="status">Дизайн-токены проекта сохранены.</div>
        <?php endif; ?>

        <?php if ( $skill_saved ) : ?>
            <div class="wpae-alert" role="status">Custom skill сохранен.</div>
        <?php endif; ?>

        <?php if ( $skill_deleted ) : ?>
            <div class="wpae-alert" role="status">Custom skill удален.</div>
        <?php endif; ?>

        <?php if ( $skill_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось сохранить skill: проверьте название, содержимое и JSON enforce.</div>
        <?php endif; ?>

        <?php if ( $skill_imported ) : ?>
            <div class="wpae-alert" role="status">Пакет skills импортирован.</div>
        <?php endif; ?>

        <?php if ( $skill_import_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось импортировать пакет: проверьте JSON, поле skills и содержимое каждого skill.</div>
        <?php endif; ?>

        <?php if ( $skill_url_imported ) : ?>
            <div class="wpae-alert" role="status">Skill из GitHub URL импортирован.</div>
        <?php endif; ?>

        <?php if ( $skill_url_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось импортировать skill из GitHub URL: проверьте ссылку, доступность файла и размер.</div>
        <?php endif; ?>

        <?php if ( $exports_pruned !== null ) : ?>
            <div class="wpae-alert" role="status">Очистка exports завершена. Удалено записей: <?php echo esc_html( (string) $exports_pruned ); ?>.</div>
        <?php endif; ?>

        <div class="wpae-grid">
            <div class="wpae-card">
                <h2>REST endpoint</h2>
                <p>Основной адрес для выполнения PHP через защищенный REST API.</p>
                <label for="wpae-rest-url">REST URL</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-rest-url" type="text" value="<?php echo esc_attr( $site_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $site_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Секретный ключ</h2>
                <p>Передавайте этот ключ в заголовке <code>X-AI-Key</code>. Не публикуйте его в frontend-коде.</p>
                <label for="wpae-key">X-AI-Key</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" type="text" id="wpae-key" value="<?php echo esc_attr( $key ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $key ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>

                <form method="post" style="margin-top:12px" onsubmit="return confirm('Сгенерировать новый секретный ключ? Агентам со старым ключом потребуется обновление.')">
                    <?php wp_nonce_field( 'wpae_regenerate_key' ); ?>
                    <input type="hidden" name="wpae_regenerate" value="1" />
                    <button type="submit" class="button wpae-button wpae-danger-button">Сгенерировать новый ключ</button>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Короткоживущие exports</h2>
                <p>
                    JSON-экспорты хранятся в <code>wp_options</code>, не создают публичных файлов и автоматически ограничены по TTL и количеству.
                </p>
                <div class="wpae-status-grid" style="padding:0;margin-top:12px">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Активные exports</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( $exports_summary['active_count'] ?? 0 ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Просроченные</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( $exports_summary['expired_count'] ?? 0 ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Активный размер</p>
                        <p class="wpae-stat-value"><?php echo esc_html( size_format( (int) ( $exports_summary['total_active_bytes'] ?? 0 ) ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">TTL</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( (int) WPAE_EXPORT_TTL_SECONDS / 60 ) ); ?> мин</p>
                    </div>
                </div>
                <form method="post" style="margin-top:12px">
                    <?php wp_nonce_field( 'wpae_prune_exports_ui' ); ?>
                    <input type="hidden" name="wpae_prune_exports_ui" value="1" />
                    <button type="submit" class="button wpae-button">Очистить просроченные exports</button>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Разрешения агента</h2>
                <p>
                    Ключ остается один, но владелец сайта управляет тем, что агенту разрешено делать.
                    Все write endpoints дополнительно требуют свежий guide token.
                </p>

                <div class="wpae-preset-list">
                    <?php foreach ( $capability_presets as $preset_id => $preset ) : ?>
                        <form class="wpae-preset" method="post">
                            <?php wp_nonce_field( 'wpae_apply_capability_preset' ); ?>
                            <input type="hidden" name="wpae_apply_capability_preset" value="1" />
                            <input type="hidden" name="wpae_capability_preset" value="<?php echo esc_attr( $preset_id ); ?>" />
                            <span>
                                <strong><?php echo esc_html( $preset['label'] ); ?></strong>
                                <span><?php echo esc_html( $preset['description'] ); ?></span>
                            </span>
                            <button type="submit" class="button wpae-button">Применить</button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_capabilities' ); ?>
                    <input type="hidden" name="wpae_save_capabilities" value="1" />

                    <div class="wpae-cap-list">
                    <?php foreach ( $capability_labels as $capability => $meta ) : ?>
                        <label class="wpae-toggle">
                            <input type="checkbox"
                                name="wpae_capabilities[<?php echo esc_attr( $capability ); ?>]"
                                value="1"
                                <?php checked( ! empty( $capabilities[ $capability ] ) ); ?> />
                            <span>
                                <strong><?php echo esc_html( $meta['label'] ); ?></strong>
                                <span><?php echo esc_html( $meta['description'] ); ?></span>
                                <?php if ( $capability === 'filesystem_writes' && defined( 'WP_AI_EXECUTOR_ALLOW_FILE_WRITES' ) && WP_AI_EXECUTOR_ALLOW_FILE_WRITES ) : ?>
                                    <span><strong>Переопределение в wp-config.php сейчас включено.</strong></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить разрешения</button>
                    </p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Дизайн-токены проекта</h2>
                <p>
                    Эти настройки попадают в <code>/guide</code>, <code>/capabilities</code>, <code>/elementor/design-system</code> и <code>/elementor/blueprint</code>.
                    Агент обязан использовать их как единую дизайн-систему для новых страниц и новых блоков.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_design_tokens' ); ?>
                    <input type="hidden" name="wpae_save_design_tokens" value="1" />

                    <h3>Палитра</h3>
                    <p class="wpae-section-note">
                        Выберите цвета через picker или введите HEX вручную. Эти значения становятся обязательной дизайн-системой для Elementor-страниц и блоков.
                    </p>
                    <div class="wpae-color-grid">
                        <?php foreach ( (array) ( $design_tokens['palette'] ?? [] ) as $token_key => $token_value ) : ?>
                            <?php
                            $color_value = (string) $token_value;
                            $picker_value = preg_match( '/^#[0-9a-fA-F]{6}$/', $color_value ) ? $color_value : '#111827';
                            ?>
                            <div class="wpae-color-field">
                                <div class="wpae-color-token">
                                    <label for="wpae-token-palette-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                    <span class="wpae-token-pill"><?php echo esc_html( $color_value ); ?></span>
                                </div>
                                <div class="wpae-color-control">
                                    <input type="color"
                                        aria-label="<?php echo esc_attr( $token_key ); ?> color picker"
                                        value="<?php echo esc_attr( $picker_value ); ?>"
                                        data-wpae-color-target="wpae-token-palette-<?php echo esc_attr( $token_key ); ?>" />
                                    <input class="wpae-input"
                                        id="wpae-token-palette-<?php echo esc_attr( $token_key ); ?>"
                                        name="wpae_design_tokens[palette][<?php echo esc_attr( $token_key ); ?>]"
                                        type="text"
                                        pattern="#[0-9a-fA-F]{6,8}"
                                        value="<?php echo esc_attr( $color_value ); ?>" />
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3>Типографика</h3>
                    <div class="wpae-form-grid">
                        <?php foreach ( (array) ( $design_tokens['typography_roles'] ?? [] ) as $token_key => $token_value ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-token-type-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                <input class="wpae-input" id="wpae-token-type-<?php echo esc_attr( $token_key ); ?>" name="wpae_design_tokens[typography_roles][<?php echo esc_attr( $token_key ); ?>]" type="text" value="<?php echo esc_attr( (string) $token_value ); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3>Spacing и radii</h3>
                    <div class="wpae-form-grid">
                        <?php foreach ( (array) ( $design_tokens['spacing_scale'] ?? [] ) as $token_key => $token_value ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-token-spacing-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                <input class="wpae-input" id="wpae-token-spacing-<?php echo esc_attr( $token_key ); ?>" name="wpae_design_tokens[spacing_scale][<?php echo esc_attr( $token_key ); ?>]" type="text" value="<?php echo esc_attr( (string) $token_value ); ?>" />
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ( (array) ( $design_tokens['radii'] ?? [] ) as $token_key => $token_value ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-token-radii-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                <input class="wpae-input" id="wpae-token-radii-<?php echo esc_attr( $token_key ); ?>" name="wpae_design_tokens[radii][<?php echo esc_attr( $token_key ); ?>]" type="text" value="<?php echo esc_attr( (string) $token_value ); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-token-button-style">Стиль кнопок</label>
                            <input class="wpae-input" id="wpae-token-button-style" name="wpae_design_tokens[button_style]" type="text" value="<?php echo esc_attr( (string) ( $design_tokens['button_style'] ?? '' ) ); ?>" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-token-tone">Тон коммуникации</label>
                            <input class="wpae-input" id="wpae-token-tone" name="wpae_design_tokens[tone_of_voice]" type="text" value="<?php echo esc_attr( (string) ( $design_tokens['tone_of_voice'] ?? '' ) ); ?>" />
                        </div>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-token-prohibitions">Дизайн-запреты</label>
                        <textarea class="wpae-textarea" id="wpae-token-prohibitions" name="wpae_design_tokens[design_prohibitions]" style="min-height:120px"><?php echo esc_textarea( implode( "\n", (array) ( $design_tokens['design_prohibitions'] ?? [] ) ) ); ?></textarea>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить дизайн-токены</button>
                    </p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Пользовательские skills</h2>
                <p>
                    Загружайте собственные инструкции в формате <code>SKILL.md</code>. Они хранятся в базе WordPress,
                    попадают в <code>/guide</code> и не создают файлов на сервере.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_skill_ui' ); ?>
                    <input type="hidden" name="wpae_save_skill_ui" value="1" />

                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-skill-name">Название</label>
                            <input class="wpae-input" id="wpae-skill-name" name="wpae_skill_name" type="text" placeholder="frontend-design" required />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-id">ID</label>
                            <input class="wpae-input" id="wpae-skill-id" name="wpae_skill_id" type="text" placeholder="frontend-design" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-priority">Приоритет</label>
                            <input class="wpae-input" id="wpae-skill-priority" name="wpae_skill_priority" type="number" min="-100" max="100" value="10" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-enabled">Статус</label>
                            <label class="wpae-toggle" style="min-height:38px;padding:9px;margin:0">
                                <input id="wpae-skill-enabled" name="wpae_skill_enabled" type="checkbox" value="1" checked />
                                <span><strong>Включить skill</strong></span>
                            </label>
                        </div>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-description">Описание</label>
                        <input class="wpae-input" id="wpae-skill-description" name="wpae_skill_description" type="text" placeholder="Правила дизайна, Elementor или проекта" />
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-content">Содержимое SKILL.md</label>
                        <textarea class="wpae-textarea" id="wpae-skill-content" name="wpae_skill_content" placeholder="# Skill instructions..." required></textarea>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-enforce">Enforce JSON</label>
                        <textarea class="wpae-textarea" id="wpae-skill-enforce" name="wpae_skill_enforce" style="min-height:92px" placeholder='[{"type":"forbid_elementor_eltype","value":"section"},{"type":"require_widget_key","value":"widgetType"}]'></textarea>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить skill</button>
                    </p>
                </form>

                <div class="wpae-grid wpae-grid-two" style="margin-top:18px">
                    <form method="post" style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <?php wp_nonce_field( 'wpae_import_skill_url_ui' ); ?>
                        <input type="hidden" name="wpae_import_skill_url_ui" value="1" />
                        <h3 style="margin-top:0">Импорт из GitHub URL</h3>
                        <p>Вставьте ссылку на <code>SKILL.md</code>, папку skill на GitHub или JSON bundle. Плагин скачает содержимое и сохранит его в базе, без файлов на сервере.</p>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-source-url">GitHub URL</label>
                            <input class="wpae-input" id="wpae-skill-source-url" name="wpae_skill_source_url" type="url" placeholder="https://github.com/owner/repo/blob/main/path/SKILL.md" required />
                        </div>
                        <div class="wpae-form-grid">
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-name">Название, если нужно</label>
                                <input class="wpae-input" id="wpae-skill-url-name" name="wpae_skill_url_name" type="text" placeholder="frontend-design" />
                            </div>
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-id">ID, если нужно</label>
                                <input class="wpae-input" id="wpae-skill-url-id" name="wpae_skill_url_id" type="text" placeholder="frontend-design" />
                            </div>
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-priority">Приоритет</label>
                                <input class="wpae-input" id="wpae-skill-url-priority" name="wpae_skill_url_priority" type="number" min="-100" max="100" value="10" />
                            </div>
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-enabled">Статус</label>
                                <label class="wpae-toggle" style="min-height:38px;padding:9px;margin:0">
                                    <input id="wpae-skill-url-enabled" name="wpae_skill_url_enabled" type="checkbox" value="1" checked />
                                    <span><strong>Включить skill</strong></span>
                                </label>
                            </div>
                        </div>
                        <p class="wpae-section-note">Поддерживаются только HTTPS-ссылки <code>github.com</code> и <code>raw.githubusercontent.com</code>. При импорте меняется <code>guide_hash</code>, поэтому агенту нужно заново пройти guide ack.</p>
                        <p style="margin-top:14px">
                            <button type="submit" class="button button-primary wpae-button">Импортировать из GitHub</button>
                        </p>
                    </form>

                    <form method="post" style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <?php wp_nonce_field( 'wpae_import_skills_ui' ); ?>
                        <input type="hidden" name="wpae_import_skills_ui" value="1" />
                        <h3 style="margin-top:0">Импорт пакета</h3>
                        <p>Вставьте JSON bundle. Режим merge обновит совпадающие ID, replace полностью заменит текущие skills.</p>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-import-mode">Режим</label>
                            <select class="wpae-input" id="wpae-skill-import-mode" name="wpae_skill_import_mode">
                                <option value="merge">Merge: добавить и обновить</option>
                                <option value="replace">Replace: заменить все</option>
                            </select>
                        </div>
                        <div class="wpae-form-field" style="margin-top:12px">
                            <label for="wpae-skill-bundle-json">JSON bundle</label>
                            <textarea class="wpae-textarea" id="wpae-skill-bundle-json" name="wpae_skill_bundle_json" style="min-height:180px" placeholder='{"schema":"wp-ai-executor.skill-bundle","skills":[]}' required></textarea>
                        </div>
                        <p style="margin-top:14px">
                            <button type="submit" class="button button-primary wpae-button">Импортировать</button>
                        </p>
                    </form>

                    <div style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <h3 style="margin-top:0">Экспорт пакета</h3>
                        <p>Этот JSON можно перенести на другой WordPress сайт с WP AI Executor. Файлы на сервере не создаются.</p>
                        <textarea class="wpae-textarea" readonly style="min-height:265px" onclick="this.select()"><?php echo esc_textarea( (string) $skill_bundle_json ); ?></textarea>
                    </div>
                </div>

                <div class="wpae-skill-list" aria-label="Установленные custom skills">
                    <?php if ( empty( $skills ) ) : ?>
                        <div class="wpae-skill-item">
                            <div>
                                <h3>Skills пока не загружены</h3>
                                <div class="wpae-skill-meta">Добавьте SKILL.md через форму выше.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $skills as $skill ) : ?>
                            <div class="wpae-skill-item">
                                <div>
                                    <h3><?php echo esc_html( (string) ( $skill['name'] ?? $skill['id'] ?? 'skill' ) ); ?></h3>
                                    <div class="wpae-skill-meta">
                                        ID: <code><?php echo esc_html( (string) ( $skill['id'] ?? '' ) ); ?></code>
                                        · приоритет: <?php echo esc_html( (string) ( $skill['priority'] ?? 0 ) ); ?>
                                        · <?php echo ! empty( $skill['enabled'] ) ? 'включен' : 'выключен'; ?>
                                        · enforce: <?php echo esc_html( (string) count( is_array( $skill['enforce'] ?? null ) ? $skill['enforce'] : [] ) ); ?>
                                    </div>
                                    <?php if ( ! empty( $skill['description'] ) ) : ?>
                                        <div class="wpae-skill-meta"><?php echo esc_html( (string) $skill['description'] ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $skill['source_url'] ) ) : ?>
                                        <div class="wpae-skill-meta">
                                            source: <code><?php echo esc_html( (string) $skill['source_type'] ); ?></code>
                                            · hash: <code><?php echo esc_html( substr( (string) ( $skill['source_sha256'] ?? '' ), 0, 12 ) ); ?></code>
                                            · <a href="<?php echo esc_url( (string) $skill['source_url'] ); ?>" target="_blank" rel="noreferrer">открыть источник</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <form method="post" onsubmit="return confirm('Удалить custom skill?')">
                                    <?php wp_nonce_field( 'wpae_delete_skill_ui' ); ?>
                                    <input type="hidden" name="wpae_delete_skill_ui" value="1" />
                                    <input type="hidden" name="wpae_delete_skill_id" value="<?php echo esc_attr( (string) ( $skill['id'] ?? '' ) ); ?>" />
                                    <button type="submit" class="button wpae-button wpae-danger-button">Удалить</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Guide и разрешения</h2>
                <p>Агент должен читать эти endpoints перед записью и следовать возвращенным правилам.</p>
                <label for="wpae-guide-url">URL guide</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-guide-url" type="text" value="<?php echo esc_attr( $guide_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $guide_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
                <label for="wpae-capabilities-url" style="display:block;margin-top:12px">URL разрешений</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-capabilities-url" type="text" value="<?php echo esc_attr( $capabilities_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $capabilities_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Журнал операций</h2>
                <p>Последние действия агентов без ключей, токенов и raw payload.</p>
                <label for="wpae-logs-url">URL журнала</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-logs-url" type="text" value="<?php echo esc_attr( $logs_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $logs_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>

                <div class="wpae-skill-list" style="margin-top:14px">
                    <?php if ( empty( $operation_logs ) ) : ?>
                        <div class="wpae-skill-item">
                            <div>
                                <h3>Записей пока нет</h3>
                                <div class="wpae-skill-meta">Журнал появится после write/audit запросов.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $operation_logs as $entry ) : ?>
                            <div class="wpae-skill-item">
                                <div>
                                    <h3><?php echo esc_html( (string) ( $entry['method'] ?? '' ) . ' ' . ( $entry['endpoint'] ?? '' ) ); ?></h3>
                                    <div class="wpae-skill-meta">
                                        <?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?>
                                        · status <?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?>
                                        · actor <?php echo esc_html( (string) ( $entry['actor'] ?? 'agent' ) ); ?>
                                    </div>
                                    <?php if ( ! empty( $entry['target_ids'] ) ) : ?>
                                        <div class="wpae-skill-meta">targets: <code><?php echo esc_html( (string) wp_json_encode( $entry['target_ids'] ) ); ?></code></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $entry['rollback_snapshot_id'] ) ) : ?>
                                        <div class="wpae-skill-meta">rollback: <code><?php echo esc_html( (string) $entry['rollback_snapshot_id'] ); ?></code></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Пример curl</h2>
                <p>Минимальный запрос к `/run`. Для write endpoints также нужен guide token.</p>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s -X POST "' . $site_url . '" \\
  -H "Content-Type: application/json" \\
  -H "X-AI-Key: ' . $key . '" \\
  -d \'{"code": "return get_bloginfo(\'name\');"}\''
); ?></pre>
            </div>

            <div class="wpae-card">
                <h2>JavaScript</h2>
                <p>Для локальной разработки или agent runtime с fetch.</p>
                <pre class="wpae-code"><?php echo esc_html(
'const AI_KEY = "' . $key . '";

window.aiPHP = async (code) => {
    const res = await fetch("/wp-json/ai-executor/v1/run", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-AI-Key": AI_KEY },
        body: JSON.stringify({ code })
    });
    const d = await res.json();
    return d.return_value ?? d.error;
};

// Пример:
await aiPHP(`return get_bloginfo("name") . " | PHP " . PHP_VERSION;`);'
); ?></pre>
            </div>

            <div class="wpae-card">
                <h2>Python</h2>
                <p>Пример для любого агента, который умеет делать HTTP-запросы.</p>
                <pre class="wpae-code"><?php echo esc_html(
'import requests

def wp_php(code: str) -> dict:
    return requests.post(
        "' . $site_url . '",
        headers={"X-AI-Key": "' . $key . '"},
        json={"code": code}
    ).json()

result = wp_php("return get_bloginfo(\'name\');")
print(result["return_value"])'
); ?></pre>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Рекомендуемая инструкция для агента</h2>
                <p>Эту инструкцию можно дать Codex, Claude Desktop или другому агенту перед работой с сайтом.</p>
                <h3>Получить guide</h3>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s "' . get_rest_url( null, 'ai-executor/v1/guide' ) . '" \\
  -H "X-AI-Key: ' . $key . '"'
); ?></pre>
                <h3>Инструкция агента</h3>
                <pre class="wpae-code wpae-code-light"><?php echo esc_html( wpae_agent_prompt() ); ?></pre>
            </div>

            <div class="wpae-card wpae-card-wide wpae-security">
                <strong>Безопасность</strong>
                <ul>
                    <li>Плагин может выполнять PHP, поэтому держите ключ в секрете.</li>
                    <li>Для production лучше задать ключ в <code>wp-config.php</code>: <code>define('WP_AI_EXECUTOR_KEY', 'your-key');</code></li>
                    <li>Дополнительно ограничьте доступ по IP на уровне сервера или firewall.</li>
                </ul>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.querySelectorAll('[data-wpae-color-target]').forEach(function (picker) {
            var input = document.getElementById(picker.getAttribute('data-wpae-color-target'));
            var pill = picker.closest('.wpae-color-field') ? picker.closest('.wpae-color-field').querySelector('.wpae-token-pill') : null;
            if (!input) return;
            picker.addEventListener('input', function () {
                input.value = picker.value.toUpperCase();
                if (pill) pill.textContent = input.value;
            });
            input.addEventListener('input', function () {
                if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
                    picker.value = input.value;
                }
                if (pill) pill.textContent = input.value;
            });
        });
    })();
    </script>
    <?php
}
