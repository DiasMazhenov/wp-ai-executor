<?php

defined( 'ABSPATH' ) || exit;

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

