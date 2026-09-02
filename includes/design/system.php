<?php

defined( 'ABSPATH' ) || exit;

function wpae_project_design_token_defaults(): array {
    return [
        'palette' => [
            'ink' => '#111827',
            'paper' => '#f6f0e6',
            'surface' => '#ffffff',
            'accent' => '#4460EC',
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
        'native_tokens' => [
            'typography' => [
                'display' => [ 'font_family' => 'inherit', 'desktop' => '3.5rem', 'tablet' => '2.75rem', 'mobile' => '2.25rem', 'weight' => '700', 'line_height' => '1.05' ],
                'subheading' => [ 'font_family' => 'inherit', 'desktop' => '2rem', 'tablet' => '1.75rem', 'mobile' => '1.5rem', 'weight' => '700', 'line_height' => '1.2' ],
                'body' => [ 'font_family' => 'inherit', 'desktop' => '1rem', 'tablet' => '1rem', 'mobile' => '1rem', 'weight' => '400', 'line_height' => '1.6' ],
                'utility' => [ 'font_family' => 'inherit', 'desktop' => '0.75rem', 'tablet' => '0.75rem', 'mobile' => '0.75rem', 'weight' => '600', 'line_height' => '1.2' ],
            ],
            'spacing' => [
                'section_desktop' => '4.5rem',
                'section_mobile' => '2rem',
                'component' => '1.5rem',
                'gap' => '1.5rem',
            ],
            'radii' => [
                'card' => '0.5rem',
                'button' => '0.35rem',
            ],
        ],
        'button_style' => 'Native Elementor button widget with clear label, high contrast, and no oversized pill default unless intentional.',
        'tone_of_voice' => 'Concrete, confident, and useful; avoid filler marketing language.',
        'design_prohibitions' => [
            'No generic template gradients as the main visual idea.',
            'No legacy Elementor sections or columns.',
            'No HTML widget as main layout or content container.',
            'No CSS-only critical backgrounds, contrast, spacing, or borders.',
            'No dirty terracotta, brown, or muddy accent colors such as #C75B3B; use a clean project accent token instead.',
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
    $native_input = is_array( $input['native_tokens'] ?? null ) ? $input['native_tokens'] : [];

    foreach ( [ 'palette', 'typography_roles', 'spacing_scale', 'radii' ] as $group ) {
        $incoming = is_array( $input[ $group ] ?? null ) ? $input[ $group ] : [];
        foreach ( $defaults[ $group ] as $key => $default ) {
            $value = $incoming[ $key ] ?? $default;
            if ( $group === 'palette' && $key === 'accent' && strtolower( trim( (string) $value ) ) === '#c75b3b' ) {
                $value = $defaults[ $group ][ $key ];
            }
            $tokens[ $group ][ $key ] = wpae_sanitize_design_token_text( $value, $group === 'palette' ? 32 : 180 );
        }
    }

    $tokens['button_style'] = wpae_sanitize_design_token_text( $input['button_style'] ?? $defaults['button_style'], 240 );
    $tokens['tone_of_voice'] = wpae_sanitize_design_token_text( $input['tone_of_voice'] ?? $defaults['tone_of_voice'], 240 );

    foreach ( $defaults['native_tokens'] as $group => $roles ) {
        $native_group = is_array( $native_input[ $group ] ?? null ) ? $native_input[ $group ] : [];
        foreach ( $roles as $role => $role_defaults ) {
            if ( is_array( $role_defaults ) ) {
                $native_role = is_array( $native_group[ $role ] ?? null ) ? $native_group[ $role ] : [];
                foreach ( $role_defaults as $key => $default ) {
                    $tokens['native_tokens'][ $group ][ $role ][ $key ] = wpae_sanitize_design_token_text(
                        $native_role[ $key ] ?? $default,
                        80
                    );
                }
            } else {
                $tokens['native_tokens'][ $group ][ $role ] = wpae_sanitize_design_token_text(
                    $native_group[ $role ] ?? $role_defaults,
                    80
                );
            }
        }
    }

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

function wpae_get_design_system_manifest(): array {
    $stored = get_option( 'wp_ai_executor_design_system_manifest', [] );
    $stored = is_array( $stored ) ? $stored : [];

    return [
        'format' => 'wpae-design-system-v2',
        'version' => wpae_sanitize_design_token_text( $stored['version'] ?? 'v02.00.00', 24 ),
        'name' => wpae_sanitize_design_token_text( $stored['name'] ?? get_bloginfo( 'name' ) . ' Design System', 120 ),
        'provenance' => wpae_sanitize_design_token_text( $stored['provenance'] ?? 'site-owner', 80 ),
        'source_url' => esc_url_raw( (string) ( $stored['source_url'] ?? home_url( '/' ) ) ),
        'license' => wpae_sanitize_design_token_text( $stored['license'] ?? 'site-owner-provided', 80 ),
        'storage' => 'wp_options',
    ];
}

function wpae_sanitize_design_system_manifest( array $input ): array {
    $current = wpae_get_design_system_manifest();

    return [
        'format' => 'wpae-design-system-v2',
        'version' => wpae_sanitize_design_token_text( $input['version'] ?? $current['version'], 24 ),
        'name' => wpae_sanitize_design_token_text( $input['name'] ?? $current['name'], 120 ),
        'provenance' => wpae_sanitize_design_token_text( $input['provenance'] ?? $current['provenance'], 80 ),
        'source_url' => esc_url_raw( (string) ( $input['source_url'] ?? $current['source_url'] ) ),
        'license' => wpae_sanitize_design_token_text( $input['license'] ?? $current['license'], 80 ),
        'storage' => 'wp_options',
    ];
}

function wpae_validate_design_system_package( array $manifest, array $tokens ): array {
    $errors = [];
    foreach ( [ 'version', 'name', 'provenance', 'source_url', 'license' ] as $field ) {
        if ( trim( (string) ( $manifest[ $field ] ?? '' ) ) === '' ) {
            $errors[] = 'manifest.' . $field . ' is required.';
        }
    }

    if ( ! empty( $manifest['source_url'] ) && ! wp_http_validate_url( (string) $manifest['source_url'] ) ) {
        $errors[] = 'manifest.source_url must be a valid HTTP(S) URL.';
    }

    foreach ( (array) ( $tokens['palette'] ?? [] ) as $role => $color ) {
        if ( ! preg_match( '/^#[0-9a-f]{6}([0-9a-f]{2})?$/i', (string) $color ) ) {
            $errors[] = 'tokens.palette.' . sanitize_key( (string) $role ) . ' must be a 6 or 8 digit hex color.';
        }
    }

    foreach ( (array) ( $tokens['native_tokens']['typography'] ?? [] ) as $role => $type ) {
        foreach ( [ 'desktop', 'tablet', 'mobile', 'line_height' ] as $field ) {
            if ( ! preg_match( '/^\d+(?:\.\d+)?(?:rem|em|%|vh|svh|vw)?$/', (string) ( $type[ $field ] ?? '' ) ) ) {
                $errors[] = 'tokens.native_tokens.typography.' . sanitize_key( (string) $role ) . '.' . $field . ' has an invalid responsive value.';
            }
        }
        if ( ! preg_match( '/^(?:normal|bold|[1-9]00)$/', (string) ( $type['weight'] ?? '' ) ) ) {
            $errors[] = 'tokens.native_tokens.typography.' . sanitize_key( (string) $role ) . '.weight is invalid.';
        }
    }

    foreach ( [ 'spacing', 'radii' ] as $group ) {
        foreach ( (array) ( $tokens['native_tokens'][ $group ] ?? [] ) as $role => $value ) {
            if ( ! preg_match( '/^\d+(?:\.\d+)?(?:rem|em|%|vh|svh|vw)$/', (string) $value ) ) {
                $errors[] = 'tokens.native_tokens.' . $group . '.' . sanitize_key( (string) $role ) . ' must include a supported unit.';
            }
        }
    }

    return [ 'ok' => empty( $errors ), 'errors' => $errors ];
}

function wpae_update_design_system_manifest( array $manifest ): void {
    $manifest = wpae_sanitize_design_system_manifest( $manifest );
    unset( $manifest['storage'] );
    update_option( 'wp_ai_executor_design_system_manifest', $manifest, false );
}

function wpae_update_project_design_tokens( array $input ): void {
    update_option( 'wp_ai_executor_design_tokens', wpae_sanitize_design_tokens( $input ), false );
}

function wpae_get_design_system_id( array $tokens = [] ): string {
    if ( empty( $tokens ) ) {
        $tokens = wpae_get_project_design_tokens();
    }

    // Keep existing marker IDs stable while v2-native token detail evolves.
    unset( $tokens['native_tokens'] );
    return 'ds-' . substr( md5( wp_json_encode( $tokens ) ), 0, 8 );
}

function wpae_get_design_system_source_hash( array $tokens = [] ): string {
    if ( empty( $tokens ) ) {
        $tokens = wpae_get_project_design_tokens();
    }

    return hash( 'sha256', (string) wp_json_encode( $tokens ) );
}

function wpae_build_design_system_document( array $manifest, array $tokens ): string {
    return implode( "\n", [
        '# ' . $manifest['name'],
        '',
        '- ID: `' . $manifest['id'] . '`',
        '- Version: `' . $manifest['version'] . '`',
        '- License: `' . $manifest['license'] . '`',
        '',
        'Use the semantic token roles below through native Elementor controls.',
        'Build mobile first with Flexbox Containers. Preserve protected HTML/WebGL zones.',
        'Prefer rem/em for type and spacing, vh/svh for viewport height, and percentages for layout width.',
        '',
        '## Semantic tokens',
        '',
        '```json',
        (string) wp_json_encode( $tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
        '```',
    ] );
}

function wpae_get_page_design_system_state( int $post_id, string $system_id, string $source_hash ): array {
    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return [ 'post_id' => null, 'active' => null, 'stored_system_id' => null, 'matches_current' => null ];
    }

    $stored_id = sanitize_key( (string) get_post_meta( $post_id, '_wpae_design_system_id', true ) );
    $stored_hash = sanitize_text_field( (string) get_post_meta( $post_id, '_wpae_design_system_hash', true ) );

    return [
        'post_id' => $post_id,
        'active' => $stored_id !== '',
        'stored_system_id' => $stored_id ?: null,
        'matches_current' => $stored_id !== '' && hash_equals( $system_id, $stored_id ) && hash_equals( $source_hash, $stored_hash ),
    ];
}

function wpae_get_design_system_required_classes( array $tokens = [] ): array {
    return [ 'wpae-ds', 'wpae-system-' . wpae_get_design_system_id( $tokens ) ];
}

function wpae_build_project_design_system( array $input = [] ): array {
    $tokens = wpae_get_project_design_tokens();
    return wpae_build_project_design_system_from_values( wpae_get_design_system_manifest(), $tokens, $input );
}

function wpae_build_project_design_system_from_values( array $manifest, array $tokens, array $input = [] ): array {
    $system_id = wpae_get_design_system_id( $tokens );
    $source_hash = wpae_get_design_system_source_hash( $tokens );
    $manifest = wpae_sanitize_design_system_manifest( $manifest );
    $manifest['id'] = $system_id;
    $manifest['source_hash'] = $source_hash;
    $manifest['active_page'] = wpae_get_page_design_system_state( absint( $input['post_id'] ?? 0 ), $system_id, $source_hash );

    return [
        'design_system_version' => 'v02.00.00',
        'system_id' => $system_id,
        'source' => 'wp_ai_executor_design_tokens',
        'tokens' => $tokens,
        'package' => [
            'manifest' => $manifest,
            'agent_document' => [
                'format' => 'DESIGN.md',
                'content' => wpae_build_design_system_document( $manifest, $tokens ),
            ],
            'semantic_tokens' => $tokens,
        ],
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
