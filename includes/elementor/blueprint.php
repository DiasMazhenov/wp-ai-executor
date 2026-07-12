<?php

defined( 'ABSPATH' ) || exit;

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

