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

/** Native Flex child basis with all row gaps removed before division. */
function wpae_el_flex_track_dimension( int $columns, int $gap_px ): array {
    $columns = max( 1, $columns );
    if ( $columns === 1 ) {
        return [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
    }
    $gap_total = max( 0, $columns - 1 ) * max( 0, $gap_px );
    return [ 'unit' => 'custom', 'size' => 'calc((100% - ' . $gap_total . 'px) / ' . $columns . ')', 'sizes' => [] ];
}

/** Responsive equal-width Flex item controls for stored repeated recipe groups. */
function wpae_el_flex_equal_item_settings( int $desktop_columns, int $tablet_columns, int $mobile_columns = 1, int $gap_px = 24 ): array {
    $settings = [];
    foreach ( [ '' => $desktop_columns, '_tablet' => $tablet_columns, '_mobile' => $mobile_columns ] as $suffix => $columns ) {
        $dimension = wpae_el_flex_track_dimension( $columns, $gap_px );
        $settings[ 'width' . $suffix ] = $settings[ '_element_custom_width' . $suffix ] = $dimension;
        $settings[ '_element_width' . $suffix ] = 'initial';
        $settings[ '_flex_size' . $suffix ] = 'custom';
        $settings[ '_flex_grow' . $suffix ] = $settings[ 'flex_grow' . $suffix ] = 0;
        $settings[ '_flex_shrink' . $suffix ] = $settings[ 'flex_shrink' . $suffix ] = $columns > 1 ? 0 : 1;
    }
    return $settings;
}

function wpae_el_container( string $id, array $settings = [], array $elements = [] ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => array_merge( [
            'content_width' => 'boxed',
            'container_type' => 'flex',
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

/** Shared typed recipe default; only supplied eyebrow content receives a badge. */
function wpae_elementor_recipe_eyebrow_presentation(): string {
    return 'pill';
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
                    wpae_el_widget( 'heroa02', 'heading', [ 'title' => '{{eyebrow}}', 'header_size' => 'h6', 'title_color' => '#4460EC' ] ),
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
                    wpae_el_container( 'feat004', [ 'flex_direction' => 'row', 'flex_direction_tablet' => 'row', 'flex_direction_mobile' => 'column', 'flex_wrap' => 'wrap', 'flex_wrap_tablet' => 'wrap', 'flex_wrap_mobile' => 'nowrap', 'background_color' => '#ffffff', 'padding' => wpae_el_spacing( 0, 0, 0, 0 ) ], [
                        wpae_el_container( 'feat005', array_merge( [ 'background_color' => '#f3f4f6' ], wpae_el_flex_equal_item_settings( 3, 2 ) ), [ wpae_el_widget( 'feat006', 'heading', [ 'title' => '{{item_1_title}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'feat007', 'text-editor', [ 'editor' => '{{item_1_text}}' ] ) ] ),
                        wpae_el_container( 'feat008', array_merge( [ 'background_color' => '#eef2ff' ], wpae_el_flex_equal_item_settings( 3, 2 ) ), [ wpae_el_widget( 'feat009', 'heading', [ 'title' => '{{item_2_title}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'feat010', 'text-editor', [ 'editor' => '{{item_2_text}}' ] ) ] ),
                        wpae_el_container( 'feat011', array_merge( [ 'background_color' => '#ecfdf5' ], wpae_el_flex_equal_item_settings( 3, 2 ) ), [ wpae_el_widget( 'feat012', 'heading', [ 'title' => '{{item_3_title}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'feat013', 'text-editor', [ 'editor' => '{{item_3_text}}' ] ) ] ),
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
                    wpae_el_container( 'price03', [ 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_direction_mobile' => 'column', 'flex_wrap' => 'wrap', 'flex_wrap_tablet' => 'nowrap', 'flex_wrap_mobile' => 'nowrap', 'background_color' => '#f9fafb', 'padding' => wpae_el_spacing( 0, 0, 0, 0 ) ], [
                        wpae_el_container( 'price04', array_merge( [ 'background_color' => '#ffffff' ], wpae_el_flex_equal_item_settings( 2, 1 ) ), [ wpae_el_widget( 'price05', 'heading', [ 'title' => '{{package_1}}', 'header_size' => 'h3' ] ), wpae_el_widget( 'price06', 'heading', [ 'title' => '{{package_1_price}}', 'header_size' => 'h2' ] ), wpae_el_widget( 'price07', 'button', [ 'text' => '{{cta}}' ] ) ] ),
                        wpae_el_container( 'price08', array_merge( [ 'background_color' => '#111827' ], wpae_el_flex_equal_item_settings( 2, 1 ) ), [ wpae_el_widget( 'price09', 'heading', [ 'title' => '{{package_2}}', 'header_size' => 'h3', 'title_color' => '#ffffff' ] ), wpae_el_widget( 'price10', 'heading', [ 'title' => '{{package_2_price}}', 'header_size' => 'h2', 'title_color' => '#ffffff' ] ), wpae_el_widget( 'price11', 'button', [ 'text' => '{{cta}}', 'background_color' => '#ffffff', 'text_color' => '#111827' ] ) ] ),
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
        'variant_records' => wpae_elementor_recipe_variants( $recipe ),
        'alternatives' => [],
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
            'container.badge-pill',
            'widget.heading',
            'widget.copy',
            'widget.cta-row',
            'widget.metric',
            'widget.feature-item',
            'widget.html-enhancement',
        ],
        'recipes' => $recipes,
        'typed_records' => array_values( wpae_composition_records() ),
        'typed_recipe_defaults' => [ 'eyebrow_presentation' => wpae_elementor_recipe_eyebrow_presentation(), 'eyebrow_content' => 'supplied_only' ],
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
        'recipe' => array_merge( $recipes[ $id ], [ 'variant_records' => wpae_elementor_recipe_variants( $recipes[ $id ] ), 'alternatives' => [] ] ),
        'next_steps' => [ 'POST /elementor/compose', 'POST /elementor/normalize', 'POST /elementor/validate' ],
    ], 200 );
}


/** Versioned typed records describe existing Plan decisions; no executable templates. */
function wpae_composition_records(): array {
	$records = [];
	foreach ( [ 'hero', 'about' ] as $family ) {
		foreach ( [ 'split_60_40', 'split_50_50', 'split_40_60' ] as $composition ) {
			foreach ( [ 'right', 'left' ] as $side ) {
				$id = $family . '.' . $composition . '.' . $side;
				$records[ $id ] = [ 'id' => $id, 'version' => 1, 'family' => $family, 'scope' => [ 'page' ], 'composition' => $composition,
					'slots' => [ 'copy' => 'exact_role_refs', 'cta' => 'all_explicit_links', 'media' => 'one_authorized_family_asset' ],
					'media' => [ 'min' => 1, 'max' => 1 ], 'groups' => [ 'min' => 1, 'max' => 1 ],
					'policy' => [ 'composition' => $composition, 'desktop' => $composition, 'tablet' => 'split_50_50', 'media_side' => $side, 'mobile' => 'copy_first_stack' ],
					'variant_kind' => 'layout_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'heading', 'text-editor', 'button', 'image' ] ];
			}
		}
	}
	foreach ( [ [ 'hero.text_only', 'hero', 'stacked_left', 0, 'structural_alternative' ], [ 'benefits.grid', 'benefits', 'three_cards', 0, 'structural_alternative' ], [ 'benefits.editorial_list', 'benefits', 'editorial_list', 0, 'structural_alternative' ], [ 'benefits.linear', 'benefits', 'linear', 0, 'legacy_alias' ], [ 'pricing.tiers', 'pricing', 'three_cards', 0, 'default' ], [ 'faq.native', 'faq', 'linear', 0, 'default' ] ] as [ $id, $family, $composition, $media, $kind ] ) {
		$records[ $id ] = [ 'id' => $id, 'version' => 1, 'family' => $family, 'scope' => [ 'page' ], 'composition' => $composition,
			'slots' => [ 'copy' => 'all_exact_refs', 'groups' => 'ordered_owned_role_refs', 'cta' => 'all_explicit_links' ],
			'media' => [ 'min' => $media, 'max' => $media ], 'groups' => [ 'min' => in_array( $family, [ 'benefits', 'pricing' ], true ) ? 2 : 1, 'max' => $family === 'hero' ? 1 : ( $family === 'benefits' ? 6 : null ) ],
			'policy' => [ 'composition' => $composition, 'desktop' => $composition, 'tablet' => 'stack', 'mobile' => 'stack' ], 'variant_kind' => $kind, 'distinct' => $kind !== 'legacy_alias',
			'capabilities' => $family === 'faq' ? [ 'container', 'accordion' ] : array_merge( [ 'container', 'heading', 'text-editor' ], $family === 'benefits' ? [ 'icon' ] : [ 'button' ] ) ];
	}
	foreach ( [ 'team', 'testimonials' ] as $family ) {
		foreach ( [ 'grid' => 'three_cards', 'editorial_rows' => 'editorial_list' ] as $variant => $composition ) {
			$id = $family . '.' . $variant;
			$maximum = $family === 'team' ? 8 : 6;
			$records[$id] = [ 'id' => $id, 'version' => 1, 'family' => $family, 'scope' => [ 'page' ], 'composition' => $composition,
				'slots' => [ 'intro' => 'all_exact_section_refs', 'groups' => 'ordered_owned_role_refs', 'media' => 'owned_portrait', 'cta' => 'all_explicit_links' ],
				'media' => [ 'min' => 0, 'max' => $maximum ], 'groups' => [ 'min' => 1, 'max' => $maximum ],
				'policy' => [ 'composition' => $composition, 'desktop' => $composition, 'tablet' => 'stack', 'mobile' => 'stack', 'entity_layout' => $variant ],
				'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'heading', 'text-editor', 'button', 'image' ] ];
		}
	}
	foreach ( [
		'portfolio.project_cards' => [ 'composition' => 'project_cards', 'layout' => 'project_cards', 'desktop' => 'three_cards', 'tablet' => 'two_cards', 'mobile' => 'stack', 'columns' => [ 'desktop_max' => 3, 'tablet' => 2, 'mobile' => 1 ] ],
		'portfolio.editorial_rows' => [ 'composition' => 'editorial_list', 'layout' => 'editorial_rows', 'desktop' => 'editorial_list', 'tablet' => 'stack', 'mobile' => 'stack', 'columns' => [ 'desktop_max' => 1, 'tablet' => 1, 'mobile' => 1 ] ],
	] as $id => $spec ) {
		$records[$id] = [ 'id' => $id, 'version' => 1, 'family' => 'portfolio', 'scope' => [ 'page' ], 'composition' => $spec['composition'],
			'slots' => [ 'intro' => 'all_exact_section_refs', 'groups' => 'ordered_project_entity_refs', 'media' => 'one_allowed_project_image_per_entity', 'links' => 'exact_project_links' ],
			'media' => [ 'min' => 2, 'max' => 6 ], 'groups' => [ 'min' => 2, 'max' => 6 ],
			'policy' => [ 'composition' => $spec['composition'], 'desktop' => $spec['desktop'], 'tablet' => $spec['tablet'], 'mobile' => $spec['mobile'], 'collection_columns' => $spec['columns'], 'entity_layout' => $spec['layout'], 'project_media' => [ 'height' => [ 'desktop' => '16rem', 'tablet' => '14rem', 'mobile' => '13rem' ], 'fit' => 'cover', 'surface_token' => 'color.surface', 'radius_token' => 'radius.card', 'mobile_height' => 'content' ] ],
			'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'heading', 'text-editor', 'button', 'image' ],
		];
	}
	// CTA records are native Flex compositions. Their policies own the section
	// topology and responsive axes before the accepted Plan is frozen.
	$records['cta.centered'] = [
		'id' => 'cta.centered', 'version' => 1, 'family' => 'cta', 'scope' => [ 'page' ], 'composition' => 'cta_centered',
		'slots' => [ 'intro' => 'eyebrow_title_body', 'cta' => 'one_or_two_explicit_links', 'media' => 'none' ],
		'media' => [ 'min' => 0, 'max' => 0 ], 'groups' => [ 'min' => 0, 'max' => 0 ],
		'policy' => [ 'composition' => 'cta_centered', 'desktop' => 'column', 'tablet' => 'column', 'mobile' => 'column', 'topology' => 'copy_contains_actions', 'intro_placement' => 'above_collection', 'intro_text_align' => 'center', 'intro_container_align' => 'center', 'reading_measure' => '42rem', 'cta_tracks' => [ 'copy' => 100, 'actions' => 100 ], 'cta_actions_direction' => [ 'desktop' => 'row', 'tablet' => 'column', 'mobile' => 'column' ], 'cta_actions_align' => 'center', 'cta_actions_gap' => [ 'desktop' => '0.875rem', 'tablet' => '0.75rem', 'mobile' => '0.75rem' ], 'cta_copy_actions_gap' => [ 'desktop' => '1.5rem', 'tablet' => '1.25rem', 'mobile' => '1rem' ] ],
		'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'heading', 'text-editor', 'button' ],
	];
	$records['cta.split_actions'] = [
		'id' => 'cta.split_actions', 'version' => 1, 'family' => 'cta', 'scope' => [ 'page' ], 'composition' => 'cta_split_actions',
		'slots' => [ 'intro' => 'eyebrow_title_body', 'cta' => 'separate_one_or_two_explicit_links', 'media' => 'none' ],
		'media' => [ 'min' => 0, 'max' => 0 ], 'groups' => [ 'min' => 0, 'max' => 0 ],
		'policy' => [ 'composition' => 'cta_split_actions', 'desktop' => 'row', 'tablet' => 'column', 'mobile' => 'column', 'topology' => 'copy_actions_siblings', 'intro_placement' => 'split_copy', 'intro_text_align' => 'left', 'intro_container_align' => 'start', 'reading_measure' => '42rem', 'cta_tracks' => [ 'copy' => 62, 'actions' => 32 ], 'cta_actions_direction' => [ 'desktop' => 'column', 'tablet' => 'column', 'mobile' => 'column' ], 'cta_actions_align' => 'start', 'cta_actions_gap' => [ 'desktop' => '1rem', 'tablet' => '0.75rem', 'mobile' => '0.75rem' ], 'cta_copy_actions_gap' => [ 'desktop' => '2rem', 'tablet' => '1.25rem', 'mobile' => '1rem' ] ],
		'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'heading', 'text-editor', 'button' ],
	];
	// New records declare a semantic repeat-surface owner without changing the
	// hashes of already frozen v1 records. The accepted DesignPlan copies this
	// owner into its versioned visual policy; the compiler only translates it.
	$records['services.icon_cards'] = [
		'id' => 'services.icon_cards', 'version' => 1, 'family' => 'services', 'scope' => [ 'page' ], 'composition' => 'icon_cards',
		'slots' => [ 'intro' => 'all_exact_section_refs', 'groups' => 'ordered_owned_role_refs', 'icon' => 'composition_default_check_circle', 'cta' => 'all_explicit_links' ],
		'media' => [ 'min' => 0, 'max' => 0 ], 'groups' => [ 'min' => 2, 'max' => 6 ],
		'policy' => [ 'composition' => 'icon_cards', 'desktop' => 'three_cards', 'tablet' => 'two_cards', 'mobile' => 'stack', 'collection_columns' => [ 'desktop_max' => 3, 'tablet' => 2, 'mobile' => 1 ], 'item_surface' => [ 'contract_version' => 2, 'mode' => 'card', 'owner_role' => 'services_icon_card' ] ],
		'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'icon', 'heading', 'text-editor', 'button' ],
	];
	$records['process.ordered_steps'] = [
		'id' => 'process.ordered_steps', 'version' => 1, 'family' => 'process', 'scope' => [ 'page' ], 'composition' => 'ordered_timeline',
		'slots' => [ 'intro' => 'exact_section_refs', 'steps' => 'ordered_label_body_pairs', 'cta' => 'none_unless_explicit' ],
		'media' => [ 'min' => 0, 'max' => 0 ], 'groups' => [ 'min' => 3, 'max' => 6 ],
		'policy' => [ 'composition' => 'ordered_timeline', 'desktop' => 'ordered_timeline', 'tablet' => 'two_columns', 'mobile' => 'stack', 'collection_columns' => [ 'desktop_max' => 4, 'tablet' => 2, 'mobile' => 1 ], 'item_surface' => [ 'contract_version' => 2, 'mode' => 'card', 'owner_role' => 'process_card' ], 'legacy_recipe' => 'process.steps' ],
		'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'heading', 'text-editor', 'divider' ],
	];
	foreach ( [
		'services.photo_cards' => [ 'composition' => 'photo_cards', 'media' => [ 'min' => 2, 'max' => 6 ], 'owner' => 'services_photo_card', 'mode' => 'card' ],
		'services.split_editorial' => [ 'composition' => 'split_editorial', 'media' => [ 'min' => 1, 'max' => 1 ], 'owner' => 'services_split_lead', 'mode' => 'card' ],
		'services.text_icon_list' => [ 'composition' => 'editorial_list', 'media' => [ 'min' => 0, 'max' => 0 ], 'owner' => 'services_text_icon_row', 'mode' => 'transparent_divider' ],
	] as $id => $spec ) {
		$records[$id] = [
			'id' => $id, 'version' => 1, 'family' => 'services', 'scope' => [ 'page' ], 'composition' => $spec['composition'],
			'slots' => [ 'intro' => 'all_exact_section_refs', 'groups' => 'ordered_owned_role_refs', 'media' => $spec['media']['max'] > 0 ? 'owned_media' : 'none', 'cta' => 'all_explicit_links' ],
			'media' => $spec['media'], 'groups' => [ 'min' => 2, 'max' => 6 ],
			'policy' => [ 'composition' => $spec['composition'], 'desktop' => $spec['composition'], 'tablet' => $spec['composition'], 'mobile' => 'stack', 'collection_columns' => [ 'desktop_max' => 3, 'tablet' => 2, 'mobile' => 1 ], 'item_surface' => [ 'contract_version' => 2, 'mode' => $spec['mode'], 'owner_role' => $spec['owner'] ] ],
			'variant_kind' => 'structural_alternative', 'distinct' => true, 'capabilities' => [ 'container', 'icon', 'heading', 'text-editor', 'button', 'image', 'divider' ],
		];
	}
	foreach ( $records as &$record ) {
		$record['visual_profiles'] = in_array( $record['family'], [ 'hero', 'about', 'benefits', 'team', 'testimonials', 'portfolio', 'services', 'process', 'cta' ], true ) ? [ 'editorial_light', 'soft_cards_light' ] : [];
		if ( $record['id'] === 'benefits.linear' ) { $record['alias_of'] = 'benefits.grid'; }
		$record['implementation_status'] = 'implemented_source';
		$record['provenance'] = [ 'source' => 'typed_plan', 'catalog' => 'wpae-compositions-v1' ];
		$record['hash'] = hash( 'sha256', wp_json_encode( $record ) );
	}
	unset( $record );
	return $records;
}

function wpae_composition_family_compositions(): array {
	$families = [];
	foreach ( wpae_composition_records() as $record ) { $families[ $record['family'] ][] = $record['composition']; }
	foreach ( $families as &$compositions ) { $compositions = array_values( array_unique( $compositions ) ); }
	return $families;
}

/** Content-length hints guide composition choice but are never render measurements. */
function wpae_composition_content_metrics( array $brief ): array {
	$texts = [];
	$content = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) ) { continue; }
		$id = (string) ( $item['id'] ?? '' );
		$text = trim( (string) ( $item['exact_text'] ?? '' ) );
		if ( $id !== '' ) { $content[ $id ] = $item; }
		if ( $text !== '' ) { $texts[] = [ 'id' => $id, 'role' => (string) ( $item['role'] ?? '' ), 'group_id' => (string) ( $item['group_id'] ?? '' ), 'chars' => function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ]; }
	}
	$groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
	if ( ! $groups && ( $brief['intent']['archetype'] ?? '' ) === 'services' && function_exists( 'wpae_brief_ir_service_groups' ) ) {
		$groups = wpae_brief_ir_service_groups( (array) ( $brief['content'] ?? [] ), (array) ( $brief['media_references'] ?? [] ) );
	}
	$group_lengths = [];
	foreach ( $groups as $group ) {
		$ids = [];
		foreach ( (array) ( $group['role_refs'] ?? [] ) as $refs ) { foreach ( (array) $refs as $ref ) { $ids[] = (string) $ref; } }
		foreach ( [ 'title_ref', 'body_ref', 'name_ref', 'position_ref', 'bio_ref', 'quote_ref', 'author_ref', 'meta_ref', 'label_ref', 'text_ref', 'description_ref' ] as $key ) { if ( ! empty( $group[$key] ) ) { $ids[] = (string) $group[$key]; } }
		$sum = 0;
		foreach ( array_unique( $ids ) as $id ) { $item = $content[$id] ?? []; $text = trim( (string) ( $item['exact_text'] ?? '' ) ); $sum += function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ); }
		$group_lengths[] = $sum;
	}
	if ( ! $group_lengths && $texts ) { $group_lengths = array_column( $texts, 'chars' ); }
	$lengths = array_values( array_filter( $group_lengths, static fn( $value ): bool => $value > 0 ) );
	$role_lengths = [];
	foreach ( $texts as $item ) { $role_lengths[$item['role']][] = $item['chars']; }
	$max_roles = static function ( array $roles ) use ( $role_lengths ): int {
		$values = [];
		foreach ( $roles as $role ) { $values = array_merge( $values, (array) ( $role_lengths[$role] ?? [] ) ); }
		return max( $values ?: [ 0 ] );
	};
	$cta_refs = [];
	foreach ( $texts as $item ) {
		if ( preg_match( '/(?:^|_)cta(?:$|_)/', $item['role'] ) === 1 && $item['id'] !== '' ) { $cta_refs[] = $item['id']; }
	}
	foreach ( $groups as $group ) {
		foreach ( [ 'cta_ref', 'action_ref' ] as $ref_key ) { if ( ! empty( $group[$ref_key] ) ) { $cta_refs[] = (string) $group[$ref_key]; } }
	}
	$cta_refs = array_values( array_unique( $cta_refs ) );
	$cta_items = array_values( array_filter( $texts, static fn( $item ): bool => in_array( $item['id'], $cta_refs, true ) ) );
	$pricing = (array) ( $brief['pricing_items'] ?? [] );
	$faq_pairs = 0;
	foreach ( $texts as $item ) { if ( $item['role'] === 'faq_question' ) { $faq_pairs++; } }
	if ( ( $brief['intent']['archetype'] ?? '' ) === 'pricing' && $pricing ) { $groups = $pricing; $lengths = []; }
	if ( ( $brief['intent']['archetype'] ?? '' ) === 'process' && function_exists( 'wpae_design_plan_process_content' ) ) { $groups = (array) ( wpae_design_plan_process_content( $brief )['steps'] ?? [] ); }
	return [
		'item_count' => count( $groups ),
		'max_title_chars' => $max_roles( [ 'title', 'service_title', 'feature_title', 'team_name', 'testimonial_author', 'pricing_label', 'faq_question', 'process_step_title' ] ),
		'max_body_chars' => $max_roles( [ 'body', 'service_body', 'feature_body', 'team_bio', 'testimonial_quote', 'faq_answer', 'process_step_body', 'text' ] ),
		'max_entity_chars' => max( $lengths ?: [ 0 ] ),
		'min_entity_chars' => min( $lengths ?: [ 0 ] ),
		'entity_length_spread' => $lengths ? max( $lengths ) - min( $lengths ) : 0,
		'cta_count' => count( $cta_refs ),
		'max_cta_chars' => max( array_column( $cta_items, 'chars' ) ?: [ 0 ] ),
		'faq_pair_count' => $faq_pairs,
	];
}

function wpae_composition_candidate_compatibility( array $brief, array $context, array $record ): array {
	$errors = [];
	$family = sanitize_key( (string) ( $brief['intent']['archetype'] ?? '' ) );
	$id = (string) ( $record['id'] ?? '' );
	if ( ( $record['family'] ?? '' ) !== $family ) { $errors[] = 'composition_cross_family'; }
	if ( ! in_array( (string) ( $brief['intent']['scope'] ?? 'page' ), (array) ( $record['scope'] ?? [] ), true ) ) { $errors[] = 'composition_scope_unsupported'; }
	if ( isset( $context['composition_record'] ) && (string) $context['composition_record'] !== $id ) { $errors[] = 'explicit_record_not_selected'; }
	if ( isset( $context['composition_version'] ) && (int) $context['composition_version'] !== (int) ( $record['version'] ?? 0 ) ) { $errors[] = 'composition_version_unknown'; }
	foreach ( [ 'composition', 'media_side' ] as $kind ) {
		$value = function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, $kind ) : null;
		$record_value = ( $record['policy'][$kind] ?? ( $kind === 'composition' ? ( $record['composition'] ?? null ) : null ) );
		if ( $value !== null && $value !== $record_value ) { $errors[] = 'composition_brief_conflict:' . $kind; }
	}
	$services_recipe = '';
	foreach ( (array) ( $brief['layout_constraints'] ?? [] ) as $constraint ) { if ( is_array( $constraint ) && ( $constraint['kind'] ?? '' ) === 'services_recipe' ) { $services_recipe = trim( (string) ( $constraint['value'] ?? '' ) ); } }
	if ( $services_recipe !== '' && $services_recipe !== $id ) { $errors[] = 'composition_brief_conflict:services_recipe'; }
	if ( ( $brief['policy']['library']['source'] ?? '' ) === 'required' ) {
		$mapped = $family === 'services' && $id === 'services.photo_cards' && function_exists( 'wpae_design_plan_services_photo_template_slot_map' ) && ! empty( wpae_design_plan_services_photo_template_slot_map()['ok'] );
		if ( ! $mapped ) { $errors[] = 'composition_library_slot_map_unavailable'; }
	}
	$groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
	if ( ! $groups && $family === 'services' && function_exists( 'wpae_brief_ir_service_groups' ) ) { $groups = wpae_brief_ir_service_groups( (array) ( $brief['content'] ?? [] ), (array) ( $brief['media_references'] ?? [] ) ); }
	if ( $family === 'pricing' ) { $groups = array_values( array_filter( (array) ( $brief['pricing_items'] ?? [] ), 'is_array' ) ); }
	if ( $family === 'process' && function_exists( 'wpae_design_plan_process_content' ) ) { $groups = (array) ( wpae_design_plan_process_content( $brief )['steps'] ?? [] ); }
	$minimum = (int) ( $record['groups']['min'] ?? 0 ); $maximum = $record['groups']['max'] ?? null;
	if ( count( $groups ) < $minimum || ( $maximum !== null && count( $groups ) > (int) $maximum ) ) { $errors[] = 'composition_group_cardinality'; }
	if ( $family === 'cta' ) {
		$media_refs = array_merge( (array) ( $brief['media_references'] ?? [] ), (array) ( $context['media_references'] ?? [] ), (array) ( $context['candidate_media_by_record'][$id] ?? [] ) );
		$cta_media_intent = sanitize_key( (string) ( function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' ) : 'unspecified' ) );
		if ( in_array( $cta_media_intent, [ 'required', 'conflict' ], true ) || ! empty( $media_refs ) ) { $errors[] = 'composition_cta_media_unsupported'; }
		$content = array_values( array_filter( (array) ( $brief['content'] ?? [] ), 'is_array' ) );
		$titles = array_values( array_filter( $content, static fn( $item ): bool => ( $item['role'] ?? '' ) === 'title' && trim( (string) ( $item['exact_text'] ?? '' ) ) !== '' ) );
		$buttons = array_values( array_filter( $content, static fn( $item ): bool => in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2', 'cta_3', 'cta_4' ], true ) ) );
		if ( count( $titles ) !== 1 ) { $errors[] = 'composition_cta_title_required'; }
		if ( count( $buttons ) < 1 || count( $buttons ) > 2 ) { $errors[] = 'composition_cta_action_count_invalid'; }
		foreach ( $buttons as $index => $button ) {
			if ( empty( $button['url_requested'] ) || trim( (string) ( $button['url'] ?? '' ) ) === '' ) { $errors[] = 'composition_cta_action_url_required:' . ( $index + 1 ); }
			if ( ( $button['provenance']['source'] ?? '' ) !== 'prompt' || ( $button['copy_status'] ?? '' ) !== 'explicit' ) { $errors[] = 'composition_cta_action_not_explicit:' . ( $index + 1 ); }
		}
	}
	$seen_groups = [];
	foreach ( $groups as $group ) {
		$group_id = sanitize_key( (string) ( $group['group_id'] ?? '' ) );
		if ( $group_id !== '' && isset( $seen_groups[$group_id] ) ) { $errors[] = 'composition_duplicate_entity:' . $group_id; }
		if ( $group_id !== '' ) { $seen_groups[$group_id] = true; }
		if ( ! empty( $group['errors'] ) ) { $errors[] = 'composition_entity_ambiguous:' . ( $group_id ?: 'unknown' ); }
	}
	$media_intent = sanitize_key( (string) ( function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' ) : 'unspecified' ) );
	$group_id_values = array_map(
		static fn( $group ): string => sanitize_key( (string) ( $group['group_id'] ?? '' ) ),
		$groups
	);
	$group_ids = array_fill_keys( array_values( array_filter( $group_id_values ) ), true );
	$content_by_id = array_column( array_values( array_filter( (array) ( $brief['content'] ?? [] ), 'is_array' ) ), null, 'id' );
	$required_roles = [
		'benefits' => [ 'feature_title', 'feature_body' ],
		'services' => [ 'service_title', 'service_body' ],
		'team' => [ 'team_name', 'team_position' ],
		'testimonials' => [ 'testimonial_quote', 'testimonial_author' ],
		'pricing' => [ 'pricing_label', 'pricing_price' ],
		'faq' => [ 'faq_question', 'faq_answer' ],
		'process' => [ 'label', 'text' ],
	];
	if ( in_array( $family, [ 'hero', 'about' ], true ) ) { $required_roles[$family] = [ 'title', 'body' ]; }
	foreach ( (array) ( $required_roles[$family] ?? [] ) as $role ) {
		$owned_refs = [];
		foreach ( $groups as $group ) {
			$refs = (array) ( $group['role_refs'] ?? [] );
			$role_refs = (array) ( $refs[$role] ?? [] );
			$field_map = [ 'service_title' => 'title_ref', 'service_body' => 'body_ref', 'feature_title' => 'title_ref', 'feature_body' => 'body_ref', 'team_name' => 'name_ref', 'team_position' => 'position_ref', 'team_bio' => 'bio_ref', 'testimonial_quote' => 'quote_ref', 'testimonial_author' => 'author_ref', 'testimonial_meta' => 'meta_ref', 'pricing_label' => 'label_ref', 'pricing_price' => 'price_ref', 'faq_question' => 'question_ref', 'faq_answer' => 'answer_ref', 'label' => 'label_ref', 'text' => 'text_ref', 'title' => 'title_ref', 'body' => 'body_ref' ];
			if ( isset( $field_map[$role], $group[$field_map[$role]] ) ) { $role_refs[] = (string) $group[$field_map[$role]]; }
			foreach ( $role_refs as $ref ) {
				$item = $content_by_id[(string) $ref] ?? [];
				if ( ! $item || ( $item['role'] ?? '' ) !== $role || ( ! empty( $item['group_id'] ) && sanitize_key( (string) $item['group_id'] ) !== sanitize_key( (string) ( $group['group_id'] ?? '' ) ) ) ) {
					$errors[] = 'composition_content_owner_mismatch:' . sanitize_key( $role );
					continue;
				}
				$owned_refs[] = (string) $ref;
			}
		}
		if ( in_array( $family, [ 'hero', 'about' ], true ) ) {
			$owned_refs = array_merge( $owned_refs, array_column( array_values( array_filter( (array) ( $brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === $role ) ), 'id' ) );
		}
		$all_role_refs = array_column( array_values( array_filter( (array) ( $brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === $role ) ), 'id' );
		if ( ! $all_role_refs || array_diff( $all_role_refs, $owned_refs ) ) { $errors[] = 'composition_required_content_unbound:' . sanitize_key( $role ); }
	}
	foreach ( $groups as $group ) {
		$refs = (array) ( $group['role_refs'] ?? [] );
		$flat_refs = [];
		foreach ( $refs as $role_refs ) { foreach ( (array) $role_refs as $ref ) { $flat_refs[] = (string) $ref; } }
		foreach ( [ 'title_ref', 'text_ref', 'body_ref', 'name_ref', 'position_ref', 'bio_ref', 'quote_ref', 'author_ref', 'meta_ref', 'label_ref', 'price_ref', 'period_ref', 'description_ref', 'question_ref', 'answer_ref', 'cta_ref', 'action_ref' ] as $field ) { if ( ! empty( $group[$field] ) ) { $flat_refs[] = (string) $group[$field]; } }
		foreach ( array_unique( $flat_refs ) as $ref ) {
			$item = $content_by_id[$ref] ?? [];
			if ( ! $item || ( ! empty( $item['group_id'] ) && sanitize_key( (string) $item['group_id'] ) !== sanitize_key( (string) ( $group['group_id'] ?? '' ) ) ) ) { $errors[] = 'composition_content_reference_unowned'; continue; }
			if ( in_array( (string) ( $item['role'] ?? '' ), [ 'service_cta', 'team_action', 'testimonial_action', 'pricing_cta' ], true ) && ( empty( $item['url_requested'] ) || trim( (string) ( $item['url'] ?? '' ) ) === '' ) ) { $errors[] = 'composition_link_reference_incomplete'; }
		}
	}
	$owned_media = [];
	foreach ( array_merge( (array) ( $brief['media_references'] ?? [] ), (array) ( $context['media_references'] ?? [] ) ) as $asset ) {
		if ( ! is_array( $asset ) || trim( (string) ( $asset['asset_id'] ?? '' ) ) === '' ) { continue; }
		$role = sanitize_key( (string) ( $asset['role'] ?? '' ) );
		$group_id = sanitize_key( (string) ( $asset['group_id'] ?? '' ) );
		$role_owned = [ 'hero' => [ 'hero' ], 'about' => [ 'about' ], 'services' => [ 'card_image', 'service_image', 'photo' ], 'team' => [ 'portrait' ], 'testimonials' => [ 'portrait', 'avatar', 'testimonial_image' ], 'portfolio' => [ 'project_image' ] ][$family] ?? [];
		$owned_group = $group_id !== '' && isset( $group_ids[$group_id] );
		$section_asset = in_array( $family, [ 'hero', 'about' ], true ) && in_array( $group_id, [ $family, 'hero_visual', 'about_visual' ], true );
		$group_scoped_service_media = $family === 'services' && $role === '' && $owned_group;
		if ( ( in_array( $role, $role_owned, true ) || $group_scoped_service_media ) && ( $owned_group || $section_asset ) ) { $owned_media[] = $asset; }
	}
	$family_media_roles = [ 'hero' => [ 'hero' ], 'about' => [ 'about' ], 'services' => [ 'card_image', 'service_image', 'photo' ], 'team' => [ 'portrait' ], 'testimonials' => [ 'portrait', 'avatar', 'testimonial_image' ], 'portfolio' => [ 'project_image' ] ][$family] ?? [];
	foreach ( (array) ( $context['candidate_media_by_record'][$id] ?? [] ) as $asset ) {
		if ( ! is_array( $asset ) || trim( (string) ( $asset['asset_id'] ?? '' ) ) === '' ) { $errors[] = 'composition_candidate_media_invalid'; continue; }
		if ( ! function_exists( 'wpae_design_plan_media_reference_valid' ) || ! wpae_design_plan_media_reference_valid( $asset ) || empty( $asset['allowed_reuse'] ) || trim( (string) ( $asset['alt'] ?? '' ) ) === '' ) { $errors[] = 'composition_candidate_media_unverified:' . sanitize_key( (string) $asset['asset_id'] ); continue; }
		$asset_role = sanitize_key( (string) ( $asset['role'] ?? '' ) ); $asset_group = sanitize_key( (string) ( $asset['group_id'] ?? '' ) );
		$owned_group = $asset_group !== '' && isset( $group_ids[$asset_group] );
		$section_asset = in_array( $family, [ 'hero', 'about' ], true ) && in_array( $asset_group, [ $family, 'hero_visual', 'about_visual' ], true );
		$group_scoped_service_media = $family === 'services' && $asset_role === '' && $owned_group;
		if ( ( ! in_array( $asset_role, $family_media_roles, true ) && ! $group_scoped_service_media ) || ( ! $owned_group && ! $section_asset ) ) { $errors[] = 'composition_candidate_media_owner_or_role_mismatch:' . sanitize_key( (string) $asset['asset_id'] ); continue; }
		$owned_media[] = $asset;
	}
	$unique_media = [];
	foreach ( $owned_media as $asset ) { $asset_id = sanitize_key( (string) ( $asset['asset_id'] ?? '' ) ); if ( $asset_id !== '' ) { $unique_media[$asset_id] = $asset; } }
	$owned_media = array_values( $unique_media );
	$media_min = (int) ( $record['media']['min'] ?? 0 ); $media_max = $record['media']['max'] ?? null;
	if ( $media_intent === 'forbidden' && $media_min > 0 ) { $errors[] = 'composition_media_forbidden'; }
	if ( $media_min > count( $owned_media ) ) { $errors[] = 'composition_required_media_missing'; }
	if ( $media_max !== null && count( $owned_media ) > (int) $media_max && ( $media_min > 0 || $media_intent === 'required' ) ) { $errors[] = 'composition_media_cardinality'; }
	if ( $media_intent === 'required' && ( $media_max === null || (int) $media_max === 0 || count( $owned_media ) === 0 ) ) { $errors[] = 'composition_required_media_unsupported'; }
	if ( $family === 'services' && $media_min > 0 ) {
		$assets_by_group = [];
		foreach ( $owned_media as $asset ) { $group_id = sanitize_key( (string) ( $asset['group_id'] ?? '' ) ); if ( $group_id !== '' ) { $assets_by_group[$group_id] = true; } }
		if ( $id === 'services.photo_cards' ) { foreach ( array_keys( $group_ids ) as $group_id ) { if ( ! isset( $assets_by_group[$group_id] ) ) { $errors[] = 'composition_required_media_owner_missing:' . $group_id; } } }
		if ( $id === 'services.split_editorial' ) {
			$lead = sanitize_key( (string) ( $context['services_lead_service_ref'] ?? '' ) );
			if ( $lead === '' || ! isset( $group_ids[$lead] ) || ! isset( $assets_by_group[$lead] ) ) { $errors[] = 'composition_split_lead_media_unresolved'; }
		}
	}
	$profile = $context['visual_profile'] ?? '';
	if ( ! is_string( $profile ) || ( $profile !== '' && ! in_array( $profile, (array) ( $record['visual_profiles'] ?? [] ), true ) ) ) { $errors[] = 'composition_visual_profile_unsupported'; }
	$required_capabilities = array_values( array_unique( array_map( 'sanitize_key', (array) ( $record['capabilities'] ?? [] ) ) ) );
	if ( function_exists( 'wpae_widget_capability_report' ) ) {
		$capabilities = wpae_widget_capability_report( $required_capabilities );
		if ( empty( $capabilities['ok'] ) ) { $errors[] = 'composition_runtime_capability_unavailable'; }
	}
	return [ 'compatible' => empty( $errors ), 'reasons' => array_values( array_unique( $errors ) ), 'group_count' => count( $groups ), 'owned_media_count' => count( $owned_media ) ];
}

/** One deterministic decision stage shared by canonical create and the typed composer. */
function wpae_composition_decide( array $brief, array $context = [], ?array $candidate_ids = null ): array {
	$records = wpae_composition_records();
	$family = sanitize_key( (string) ( $brief['intent']['archetype'] ?? '' ) );
	$metrics = wpae_composition_content_metrics( $brief );
	$brief_profile = function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, 'visual_profile' ) : null;
	$context_profile = is_string( $context['visual_profile'] ?? null ) ? trim( (string) $context['visual_profile'] ) : '';
	$explicit_record = isset( $context['composition_record'] ) && is_string( $context['composition_record'] ) && $context['composition_record'] !== '';
	$explicit_composition = function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, 'composition' ) : null;
	$explicit_side = function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, 'media_side' ) : null;
	$requested_profile = is_string( $brief_profile ) && $brief_profile !== '' ? $brief_profile : $context_profile;
	$requested_selection = [
		'mode' => $explicit_record ? 'explicit_record' : ( $explicit_composition !== null || $explicit_side !== null ? 'explicit_brief' : 'automatic' ),
		'record_id' => $explicit_record && preg_match( '/^[a-z0-9][a-z0-9_.-]{0,127}$/i', trim( (string) $context['composition_record'] ) ) ? trim( (string) $context['composition_record'] ) : null,
		'record_version' => $explicit_record && isset( $context['composition_version'] ) && is_numeric( $context['composition_version'] ) ? (int) $context['composition_version'] : null,
		'visual_profile' => $requested_profile !== '' ? sanitize_key( $requested_profile ) : null,
		'visual_profile_source' => is_string( $brief_profile ) && $brief_profile !== '' ? 'explicit_brief' : ( $context_profile !== '' ? 'ui_selector' : 'not_requested' ),
		'brief_composition' => is_string( $explicit_composition ) && $explicit_composition !== '' ? sanitize_key( $explicit_composition ) : null,
		'brief_media_side' => is_string( $explicit_side ) && $explicit_side !== '' ? sanitize_key( $explicit_side ) : null,
	];
	if ( is_string( $brief_profile ) && $brief_profile !== '' && $context_profile !== '' && $brief_profile !== $context_profile ) {
		return [ 'errors' => [ 'composition_visual_profile_conflict' ], 'source' => 'explicit_conflict', 'request_selection' => $requested_selection, 'policy_version' => 'wpae-composition-selection-v2', 'metrics' => $metrics, 'rejected' => [] ];
	}
	if ( is_string( $brief_profile ) && $brief_profile !== '' ) { $context['visual_profile'] = $brief_profile; }
	$source = $explicit_record ? 'explicit_record' : ( $explicit_composition !== null || $explicit_side !== null ? 'explicit_brief' : 'content_ranked_catalog' );
	if ( $explicit_record && ! isset( $records[$context['composition_record']] ) ) { return [ 'errors' => [ 'composition_record_unknown' ], 'request_selection' => $requested_selection, 'policy_version' => 'wpae-composition-selection-v2', 'metrics' => $metrics, 'rejected' => [ (string) $context['composition_record'] => [ 'composition_record_unknown' ] ] ]; }
	if ( $explicit_record && $requested_selection['record_version'] !== null && (int) $records[$context['composition_record']]['version'] !== $requested_selection['record_version'] ) {
		return [ 'errors' => [ 'composition_record_version_conflict' ], 'source' => 'explicit_conflict', 'request_selection' => $requested_selection, 'policy_version' => 'wpae-composition-selection-v2', 'metrics' => $metrics, 'rejected' => [ (string) $context['composition_record'] => [ 'composition_record_version_conflict' ] ] ];
	}
	$catalog_ids = array_keys( $records ); sort( $catalog_ids );
	$catalog_identity = hash( 'sha256', wp_json_encode( array_map( static fn( $id ): array => [ $id, $records[$id]['version'], $records[$id]['hash'] ], $catalog_ids ) ) );
	$ranked = []; $rejected = [];
	$allowed_ids = $candidate_ids !== null ? array_fill_keys( $candidate_ids, true ) : null;
	foreach ( $records as $id => $record ) {
		if ( $allowed_ids !== null && ! isset( $allowed_ids[$id] ) ) { continue; }
		$compatibility = wpae_composition_candidate_compatibility( $brief, $context, $record );
		if ( empty( $compatibility['compatible'] ) ) { $rejected[$id] = $compatibility['reasons']; continue; }
		$score = 0; $reasons = [];
		if ( $explicit_record ) { $score += 10000; $reasons[] = 'honored_explicit_record'; }
		elseif ( $explicit_composition !== null || $explicit_side !== null ) { $score += 5000; $reasons[] = 'honored_explicit_brief_constraint'; }
		else {
			$spread = (int) $metrics['entity_length_spread']; $body = (int) $metrics['max_body_chars']; $count = (int) $metrics['item_count'];
			switch ( $family ) {
				case 'benefits':
					// The grid supports two through six items. Keep compact copy in the
					// card topology at every supported count; an editorial list is for
					// genuinely long/uneven descriptions, not merely for count > 2.
					$compact = $count >= 2 && $count <= 6 && (int) $metrics['max_title_chars'] <= 48 && $body <= 84 && (int) $metrics['max_entity_chars'] <= 120;
					$want = $compact ? 'benefits.grid' : 'benefits.editorial_list';
					if ( $id === $want ) { $score += 300; $reasons[] = $compact ? 'compact_copy_favors_cards' : 'long_or_uneven_copy_favors_editorial_list'; }
					break;
				case 'services':
					$want = ( (int) $metrics['max_body_chars'] >= 210 || $spread >= 170 ) ? 'services.text_icon_list' : 'services.icon_cards';
					if ( $media_intent = sanitize_key( (string) ( function_exists( 'wpae_design_plan_constraint_value' ) ? wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' ) : 'unspecified' ) ) ) { if ( $media_intent === 'required' ) { $want = 'services.photo_cards'; } }
					if ( $id === $want ) { $score += 300; $reasons[] = $want === 'services.text_icon_list' ? 'long_or_uneven_copy_favors_list' : ( $want === 'services.photo_cards' ? 'required_media_favors_photo_cards' : 'balanced_copy_favors_icon_cards' ); }
					break;
				case 'testimonials':
					$want = $body >= 240 || $spread >= 180 ? 'testimonials.editorial_rows' : 'testimonials.grid';
					if ( $id === $want ) { $score += 300; $reasons[] = $want === 'testimonials.editorial_rows' ? 'long_or_uneven_quotes_favor_editorial_rows' : 'compact_quotes_favor_cards'; }
					break;
				case 'team':
					$want = $body >= 180 || $spread >= 140 ? 'team.editorial_rows' : 'team.grid';
					if ( $id === $want ) { $score += 300; $reasons[] = $want === 'team.editorial_rows' ? 'long_or_uneven_biographies_favor_rows' : 'compact_biographies_favor_cards'; }
					break;
				case 'cta':
					$button_count = count( array_filter( (array) ( $brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2' ], true ) ) );
					$want = $button_count > 1 ? 'cta.split_actions' : 'cta.centered';
					if ( $id === $want ) { $score += 300; $reasons[] = $button_count > 1 ? 'separate_actions_favor_split_actions' : 'single_action_favors_centered'; }
					break;
				case 'hero': case 'about':
					$has_media = (int) $compatibility['owned_media_count'] > 0;
					$desired = $family . '.' . ( $has_media ? ( $body > 280 ? 'split_50_50' : 'split_60_40' ) : 'text_only' );
					if ( $id === $desired . '.right' || $id === $desired ) { $score += 200; $reasons[] = $has_media ? 'copy_and_media_balance' : 'no_owned_media'; }
					if ( $has_media && str_ends_with( $id, '.right' ) ) { $score += 20; $reasons[] = 'documented_media_right_default'; }
					break;
				default: break;
			}
		}
		$profile = is_string( $context['visual_profile'] ?? null ) ? (string) $context['visual_profile'] : '';
		if ( $profile === '' && in_array( 'editorial_light', (array) ( $record['visual_profiles'] ?? [] ), true ) ) { $profile = 'editorial_light'; }
		$ranked[] = [ 'record' => $record, 'compatibility' => $compatibility, 'score' => $score, 'reasons' => $reasons ?: [ 'only_compatible_record' ], 'visual_profile' => $profile ];
	}
	usort( $ranked, static function ( array $left, array $right ): int { return ( $right['score'] <=> $left['score'] ) ?: strcmp( (string) $left['record']['id'], (string) $right['record']['id'] ); } );
	if ( ! $ranked ) { return [ 'errors' => [ 'composition_no_compatible_candidate' ], 'source' => $source, 'request_selection' => $requested_selection, 'policy_version' => 'wpae-composition-selection-v2', 'catalog_id' => 'wpae-compositions-v1', 'catalog_identity' => $catalog_identity, 'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '', 'metrics' => $metrics, 'rejected' => $rejected, 'candidates' => [] ]; }
	$selected = $ranked[0];
	$alternatives = [];
	if ( ! $explicit_record ) {
		$selected_signature = wpae_composition_topology_signature( $selected['record'] );
		foreach ( array_slice( $ranked, 1 ) as $candidate ) {
			if ( empty( $candidate['record']['distinct'] ) || wpae_composition_topology_signature( $candidate['record'] ) === $selected_signature ) { continue; }
			$alternatives[] = [ 'record_id' => $candidate['record']['id'], 'record_version' => $candidate['record']['version'], 'record_hash' => $candidate['record']['hash'], 'variant_kind' => $candidate['record']['variant_kind'], 'visual_profile' => $candidate['visual_profile'], 'reasons' => $candidate['reasons'], 'topology_signature' => wpae_composition_topology_signature( $candidate['record'] ) ];
			if ( count( $alternatives ) === 2 ) { break; }
		}
	}
	return [ 'record' => $selected['record'], 'source' => $source, 'request_selection' => $requested_selection, 'visual_profile' => $selected['visual_profile'], 'errors' => [], 'policy_version' => 'wpae-composition-selection-v2', 'catalog_id' => 'wpae-compositions-v1', 'catalog_identity' => $catalog_identity, 'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '', 'metrics' => $metrics, 'selection_reasons' => $selected['reasons'], 'alternatives' => $alternatives, 'rejected' => $rejected, 'candidates' => array_map( static fn( $candidate ): array => [ 'record_id' => $candidate['record']['id'], 'record_version' => $candidate['record']['version'], 'record_hash' => $candidate['record']['hash'], 'score' => $candidate['score'], 'reasons' => $candidate['reasons'], 'visual_profile' => $candidate['visual_profile'], 'topology_signature' => wpae_composition_topology_signature( $candidate['record'] ) ], $ranked ) ];
}

/** Materialize one already-ranked candidate without invoking selection again. */
function wpae_composition_decision_for_record( array $brief, array $decision, string $record_id ): array {
	$records = wpae_composition_records();
	$record = $records[$record_id] ?? [];
	$catalog_ids = array_keys( $records ); sort( $catalog_ids );
	$catalog_identity = hash( 'sha256', wp_json_encode( array_map( static fn( $id ): array => [ $id, $records[$id]['version'], $records[$id]['hash'] ], $catalog_ids ) ) );
	if ( ! $record || ! empty( $decision['errors'] ) || ( $decision['policy_version'] ?? '' ) !== 'wpae-composition-selection-v2' || ( $decision['catalog_id'] ?? '' ) !== 'wpae-compositions-v1' || ( $decision['catalog_identity'] ?? '' ) !== $catalog_identity || ( $decision['brief_hash'] ?? '' ) !== ( function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '' ) ) {
		return [ 'errors' => [ 'composition_decision_stale_or_untrusted' ] ];
	}
	$candidate = null;
	foreach ( (array) ( $decision['candidates'] ?? [] ) as $item ) { if ( is_array( $item ) && ( $item['record_id'] ?? '' ) === $record_id ) { $candidate = $item; break; } }
	if ( ! is_array( $candidate ) || (int) ( $candidate['record_version'] ?? 0 ) !== (int) $record['version'] || ( $candidate['record_hash'] ?? '' ) !== (string) $record['hash'] ) {
		return [ 'errors' => [ 'composition_candidate_not_in_accepted_catalog' ] ];
	}
	$alternatives = array_values( array_filter( (array) ( $decision['alternatives'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['record_id'] ?? '' ) !== $record_id ) );
	return array_merge( $decision, [ 'record' => $record, 'visual_profile' => (string) ( $candidate['visual_profile'] ?? '' ), 'selection_reasons' => (array) ( $candidate['reasons'] ?? [] ), 'alternatives' => $alternatives, 'selected_candidate' => $candidate, 'errors' => [] ] );
}

function wpae_composition_topology_signature( array $record ): string {
	$policy = (array) ( $record['policy'] ?? [] );
	$shape = [ 'family' => $record['family'] ?? '', 'composition' => $record['composition'] ?? '', 'topology' => $policy['topology'] ?? '', 'desktop' => $policy['desktop'] ?? '', 'tablet' => $policy['tablet'] ?? '', 'mobile' => $policy['mobile'] ?? '', 'media_side' => $policy['media_side'] ?? '', 'entity_layout' => $policy['entity_layout'] ?? '', 'capabilities' => array_values( (array) ( $record['capabilities'] ?? [] ) ) ];
	return hash( 'sha256', wp_json_encode( $shape ) );
}

/** Compatibility facade for old callers: it now delegates to the common chooser. */
function wpae_composition_resolve( array $brief, array $context, string $composition, string $side ): array {
	$ids = array_keys( array_filter( wpae_composition_records(), static fn( $record ): bool => ( $record['family'] ?? '' ) === ( $brief['intent']['archetype'] ?? '' ) && ( $record['composition'] ?? '' ) === $composition && ( $record['policy']['media_side'] ?? $side ) === $side ) );
	if ( empty( $context['composition_record'] ) && ! $ids ) { return [ 'errors' => [ 'composition_record_missing' ] ]; }
	if ( empty( $context['composition_record'] ) ) { $context['composition_candidate_ids'] = $ids; }
	$decision = wpae_composition_decide( $brief, $context, empty( $context['composition_record'] ) ? $ids : null );
	if ( ! empty( $decision['errors'] ) ) { return $decision; }
	return array_merge( $decision, [ 'source' => $decision['source'], 'record' => $decision['record'] ] );
}

/** Old REST recipes have one actual tree each; every other label is an alias. */
function wpae_elementor_recipe_variants( array $recipe ): array {
	$variants = [];
	foreach ( $recipe['variants'] as $label ) {
		$alias = $label !== $recipe['default_variant'];
		$variants[ $label ] = [ 'requested_variant' => $label, 'effective_variant' => $recipe['default_variant'], 'variant_kind' => $alias ? 'legacy_alias' : 'default', 'distinct' => ! $alias, 'implementation_status' => $alias ? 'compatibility_alias' : 'implemented_legacy' ];
	}
	return $variants;
}


function wpae_composition_visual_profiles(): array {
	$type = static fn( string $desktop, string $tablet, string $mobile, string $weight, string $line ): array => [ 'font_family' => 'inherit', 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile, 'weight' => $weight, 'line_height' => $line, 'line_height_tablet' => $line, 'line_height_mobile' => $line ];
	return [
		// Visual profiles own layout, rhythm, typography and shape. Brand colors
		// are resolved from confirmed project/site sources before Plan freeze.
		'editorial_light' => [
			'type.display' => $type( '3.25rem', '2.5rem', '2rem', '700', '1.1' ), 'type.section_title' => $type( '2.5rem', '2rem', '1.75rem', '700', '1.15' ), 'type.body' => $type( '1.0625rem', '1rem', '1rem', '400', '1.65' ), 'type.feature' => $type( '1.25rem', '1.1875rem', '1.125rem', '600', '1.25' ),
			'space.section' => '5rem', 'space.section_tablet' => '3.5rem', 'space.section_mobile' => '2.5rem', 'space.component' => '1.25rem', 'space.component_tablet' => '1rem', 'space.component_mobile' => '0.875rem', 'radius.card' => '0.25rem', 'layout.copy_width' => '38rem', 'space.card' => '1.25rem' ],
		'soft_cards_light' => [
			'type.display' => $type( '2.75rem', '2.25rem', '1.875rem', '600', '1.2' ), 'type.section_title' => $type( '2.125rem', '1.875rem', '1.625rem', '700', '1.2' ), 'type.body' => $type( '1rem', '1rem', '0.9375rem', '400', '1.6' ), 'type.feature' => $type( '1.1875rem', '1.125rem', '1.0625rem', '600', '1.35' ),
			'space.section' => '4rem', 'space.section_tablet' => '3rem', 'space.section_mobile' => '2rem', 'space.component' => '1.75rem', 'space.component_tablet' => '1.25rem', 'space.component_mobile' => '1rem', 'radius.card' => '1.25rem', 'layout.copy_width' => '32rem', 'space.card' => '1.75rem' ],
	];
}

/** Safe editor projection: no trees, tokens, credentials or legacy aliases. */
function wpae_composition_editor_catalog(): array {
	$families = [ 'hero' => 'Первый экран', 'about' => 'О нас', 'benefits' => 'Преимущества', 'pricing' => 'Тарифы', 'faq' => 'Вопросы и ответы', 'team' => 'Команда', 'testimonials' => 'Отзывы', 'portfolio' => 'Портфолио', 'services' => 'Услуги', 'process' => 'Процесс', 'cta' => 'Призыв к действию' ];
	$labels = [ 'hero.text_only' => 'Только текст', 'benefits.grid' => 'Сетка карточек', 'benefits.editorial_list' => 'Список с иконками', 'pricing.tiers' => 'Карточки тарифов', 'faq.native' => 'Аккордеон', 'team.grid' => 'Карточки участников', 'team.editorial_rows' => 'Редакционные строки', 'testimonials.grid' => 'Карточки отзывов', 'testimonials.editorial_rows' => 'Редакционные строки', 'portfolio.project_cards' => 'Карточки проектов', 'portfolio.editorial_rows' => 'Редакционные строки проектов', 'services.icon_cards' => 'Карточки с иконками', 'services.photo_cards' => 'Карточки с фото', 'services.split_editorial' => 'Текст и фото', 'services.text_icon_list' => 'Список услуг', 'process.ordered_steps' => 'Упорядоченные этапы', 'cta.centered' => 'Центрированный блок', 'cta.split_actions' => 'Текст и отдельные действия' ];
 $records = [];
 foreach ( wpae_composition_records() as $record ) {
  if ( empty( $record['distinct'] ) || $record['implementation_status'] !== 'implemented_source' || ! in_array( 'page', $record['scope'], true ) ) { continue; }
  $label = $labels[ $record['id'] ] ?? '';
  if ( $label === '' ) {
   $ratio = str_replace( [ 'split_', '_' ], [ '', '/' ], $record['composition'] );
   $label = 'Фото ' . ( ( $record['policy']['media_side'] ?? 'right' ) === 'left' ? 'слева' : 'справа' ) . ' · текст/фото ' . $ratio;
  }
  $records[] = [ 'id' => $record['id'], 'version' => $record['version'], 'family' => $record['family'], 'family_label' => $families[ $record['family'] ] ?? $record['family'], 'label' => $label, 'profiles' => $record['visual_profiles'] ];
 }
 return [ 'records' => $records, 'profiles' => [ [ 'id' => 'editorial_light', 'label' => 'Светлое редакционное' ], [ 'id' => 'soft_cards_light', 'label' => 'Светлое с мягкими карточками' ] ] ];
}
