<?php

defined( 'ABSPATH' ) || exit;

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

