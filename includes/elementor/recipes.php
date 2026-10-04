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
            'widget.heading',
            'widget.copy',
            'widget.cta-row',
            'widget.metric',
            'widget.feature-item',
            'widget.html-enhancement',
        ],
        'recipes' => $recipes,
        'typed_records' => array_values( wpae_composition_records() ),
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
	foreach ( $records as &$record ) {
		$record['visual_profiles'] = in_array( $record['family'], [ 'hero', 'about', 'benefits' ], true ) ? [ 'editorial_light', 'soft_cards_light' ] : [];
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

/** Resolve once. A conflicting explicit selection is a refusal, never a downgrade. */
function wpae_composition_resolve( array $brief, array $context, string $composition, string $side ): array {
	$records = wpae_composition_records();
	$family = (string) ( $brief['intent']['archetype'] ?? '' );
	$id = $context['composition_record'] ?? '';
	$explicit = $id !== '';
	$errors = [];
	if ( ! is_string( $id ) ) { return [ 'errors' => [ 'composition_record_invalid' ] ]; }
	if ( ! $explicit ) {
		foreach ( $records as $candidate ) {
			if ( $candidate['family'] === $family && $candidate['composition'] === $composition && ( $candidate['policy']['media_side'] ?? $side ) === $side ) { $id = $candidate['id']; break; }
		}
	}
	$record = $records[ $id ] ?? [];
	if ( ( $brief['policy']['library']['source'] ?? '' ) === 'required' ) { $errors[] = 'composition_library_slot_map_unavailable'; }
	if ( ! $record ) { return [ 'errors' => [ 'composition_record_unknown' ] ]; }
	if ( isset( $context['composition_version'] ) && $context['composition_version'] !== $record['version'] ) { $errors[] = 'composition_version_unknown'; }
	if ( $record['family'] !== $family ) { $errors[] = 'composition_cross_family'; }
	if ( ! in_array( $brief['intent']['scope'] ?? 'page', $record['scope'], true ) ) { $errors[] = 'composition_scope_unsupported'; }
	foreach ( [ 'composition', 'media_side' ] as $kind ) {
		$value = wpae_design_plan_constraint_value( $brief, $kind );
		if ( $value !== null && $value !== ( $record['policy'][ $kind ] ?? null ) ) { $errors[] = 'composition_brief_conflict:' . $kind; }
	}
	$count = count( (array) ( $brief['media_references'] ?? [] ) );
	if ( $count < $record['media']['min'] || $count > $record['media']['max'] ) { $errors[] = 'composition_media_cardinality'; }
	$groups = count( (array) ( $brief['groups'] ?? [] ) );
	if ( $groups < $record['groups']['min'] || ( $record['groups']['max'] !== null && $groups > $record['groups']['max'] ) ) { $errors[] = 'composition_group_cardinality'; }
	$profile = $context['visual_profile'] ?? '';
	if ( ! is_string( $profile ) || ( $profile !== '' && ! in_array( $profile, $record['visual_profiles'], true ) ) ) { $errors[] = 'composition_visual_profile_unsupported'; }
	return [ 'record' => $record, 'source' => $explicit ? 'explicit_record' : ( wpae_design_plan_constraint_value( $brief, 'composition' ) !== null || wpae_design_plan_constraint_value( $brief, 'media_side' ) !== null ? 'explicit_brief' : 'documented_default' ), 'visual_profile' => is_string( $profile ) ? $profile : '', 'errors' => $errors ];
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
		'editorial_light' => [ 'color.page_bg' => '#ffffff', 'color.surface' => '#ffffff', 'color.text' => '#17202a', 'color.muted' => '#475569', 'color.border' => '#cbd5e1',
			'type.display' => $type( '3.25rem', '2.5rem', '2rem', '700', '1.1' ), 'type.body' => $type( '1.0625rem', '1rem', '1rem', '400', '1.65' ), 'type.feature' => $type( '1.25rem', '1.1875rem', '1.125rem', '600', '1.25' ),
			'space.section' => '5rem', 'space.section_tablet' => '3.5rem', 'space.section_mobile' => '2.5rem', 'space.component' => '1.25rem', 'space.component_tablet' => '1rem', 'space.component_mobile' => '0.875rem', 'radius.card' => '0.25rem', 'layout.copy_width' => '38rem', 'space.card' => '1.25rem' ],
		'soft_cards_light' => [ 'color.page_bg' => '#f1f5f9', 'color.surface' => '#ffffff', 'color.text' => '#0f172a', 'color.muted' => '#475569', 'color.border' => '#b8c4d2',
			'type.display' => $type( '2.75rem', '2.25rem', '1.875rem', '600', '1.2' ), 'type.body' => $type( '1rem', '1rem', '0.9375rem', '400', '1.6' ), 'type.feature' => $type( '1.1875rem', '1.125rem', '1.0625rem', '600', '1.35' ),
			'space.section' => '4rem', 'space.section_tablet' => '3rem', 'space.section_mobile' => '2rem', 'space.component' => '1.75rem', 'space.component_tablet' => '1.25rem', 'space.component_mobile' => '1rem', 'radius.card' => '1.25rem', 'layout.copy_width' => '32rem', 'space.card' => '1.75rem' ],
	];
}
