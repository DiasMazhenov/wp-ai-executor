<?php

/** Typed DesignPlan v1. Provider output stops at this boundary. */

defined( 'ABSPATH' ) || exit;

const WPAE_DESIGN_PLAN_SCHEMA = 'wpae-design-plan-v1';

function wpae_design_pipeline_mode(): string {
	$settings = function_exists( 'wpae_llm_get_settings' ) ? wpae_llm_get_settings() : [];
	$mode = sanitize_key( (string) ( $settings['design_pipeline_mode'] ?? 'off' ) );
	if ( function_exists( 'apply_filters' ) ) {
		$mode = sanitize_key( (string) apply_filters( 'wpae_design_pipeline_mode', $mode ) );
	}
	return in_array( $mode, [ 'off', 'shadow', 'active' ], true ) ? $mode : 'off';
}

function wpae_design_plan_schema(): array {
	return [
		'schema' => WPAE_DESIGN_PLAN_SCHEMA,
		'archetypes' => [ 'hero', 'process', 'pricing', 'faq', 'benefits' ],
		'compositions' => [ 'split_60_40', 'split_50_50', 'split_40_60', 'stacked_left', 'linear', 'three_cards' ],
		'responsive' => [ 'split_60_40', 'split_50_50', 'split_40_60', 'copy_first_stack', 'stack' ],
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
	];
}

function wpae_design_plan_constraint_value( array $brief, string $kind, $default = null ) {
	foreach ( (array) ( $brief['layout_constraints'] ?? [] ) as $constraint ) {
		if ( is_array( $constraint ) && ( $constraint['kind'] ?? '' ) === $kind ) {
			return $constraint['value'] ?? $default;
		}
	}
	return $default;
}

function wpae_design_plan_content_refs( array $brief, array $roles = [] ): array {
	$refs = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) || trim( (string) ( $item['exact_text'] ?? '' ) ) === '' ) {
			continue;
		}
		if ( empty( $roles ) || in_array( (string) ( $item['role'] ?? '' ), $roles, true ) ) {
			$refs[] = sanitize_key( (string) ( $item['id'] ?? '' ) );
		}
	}
	return array_values( array_filter( array_unique( $refs ) ) );
}

function wpae_design_plan_process_content( array $brief ): array {
	$items = array_values( array_filter( (array) ( $brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && trim( (string) ( $item['id'] ?? '' ) ) !== '' && trim( (string) ( $item['exact_text'] ?? '' ) ) !== '' ) );
	$source = (string) ( $brief['source_text'] ?? '' );
	$steps = [];
	$unpaired = [];
	for ( $index = 0, $count = count( $items ); $index < $count; ) {
		$current = $items[ $index ];
		$next = $items[ $index + 1 ] ?? null;
		$current_span = (array) ( $current['source_span'] ?? [] );
		$next_span = is_array( $next ) ? (array) ( $next['source_span'] ?? [] ) : [];
		$gap = is_array( $next ) && isset( $current_span[1], $next_span[0] )
			? substr( $source, (int) $current_span[1], max( 0, (int) $next_span[0] - (int) $current_span[1] ) )
			: '';
		if ( is_array( $next ) && preg_match( '/^\s*(?:[—–]|->|→)\s*$/u', $gap ) ) {
			$steps[] = [
				'label_ref' => sanitize_key( (string) $current['id'] ),
				'text_ref' => sanitize_key( (string) $next['id'] ),
				'provenance' => [ 'source' => 'brief', 'source_spans' => [ $current_span, $next_span ] ],
			];
			$index += 2;
			continue;
		}
		$unpaired[] = $current;
		$index++;
	}
	$badge_ref = '';
	if ( ! empty( $steps ) ) {
		foreach ( $unpaired as $unpaired_index => $item ) {
			$span = (array) ( $item['source_span'] ?? [] );
			$prefix = isset( $span[0] ) ? substr( $source, 0, (int) $span[0] ) : '';
			if ( preg_match( '/(?:блок\s+процесс\w*|process\s+block)\s*$/iu', $prefix ) ) {
				$badge_ref = sanitize_key( (string) $item['id'] );
				unset( $unpaired[ $unpaired_index ] );
				break;
			}
		}
	}
	foreach ( $unpaired as $item ) {
		$span = (array) ( $item['source_span'] ?? [] );
		$steps[] = [
			'label_ref' => sanitize_key( (string) $item['id'] ),
			'text_ref' => '',
			'provenance' => [ 'source' => 'brief', 'source_spans' => [ $span ] ],
		];
	}
	$starts = [];
	foreach ( $items as $item ) {
		$starts[ sanitize_key( (string) ( $item['id'] ?? '' ) ) ] = (int) ( $item['source_span'][0] ?? 0 );
	}
	usort( $steps, static function ( array $left, array $right ) use ( $starts ): int {
		return ( $starts[ $left['label_ref'] ] ?? 0 ) <=> ( $starts[ $right['label_ref'] ] ?? 0 );
	} );
	return [ 'badge_content_ref' => $badge_ref, 'steps' => array_values( $steps ) ];
}

function wpae_design_plan_pairs( array $brief, string $first_role, string $second_role, string $first_key, string $second_key ): array {
	$items = [];
	$pending = null;
	$errors = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$role = sanitize_key( (string) ( $item['role'] ?? '' ) );
		if ( $role === $first_role ) {
			if ( $pending !== null ) {
				$errors[] = 'unpaired_' . $first_role;
			}
			$pending = sanitize_key( (string) ( $item['id'] ?? '' ) );
		} elseif ( $role === $second_role ) {
			if ( $pending === null ) {
				$errors[] = 'unpaired_' . $second_role;
				continue;
			}
			$items[] = [ $first_key => $pending, $second_key => sanitize_key( (string) ( $item['id'] ?? '' ) ) ];
			$pending = null;
		}
	}
	if ( $pending !== null ) {
		$errors[] = 'unpaired_' . $first_role;
	}
	return [ 'items' => $items, 'errors' => array_values( array_unique( $errors ) ) ];
}

function wpae_design_plan_media_reference_valid( array $media ): bool {
	if ( absint( $media['attachment_id'] ?? 0 ) > 0 ) {
		return true;
	}
	$url = trim( (string) ( $media['source_url'] ?? '' ) );
	$parts = parse_url( $url );
	if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) || trim( (string) ( $parts['host'] ?? '' ) ) === '' ) {
		return false;
	}
	return ! function_exists( 'wp_http_validate_url' ) || (bool) wp_http_validate_url( $url );
}

function wpae_design_plan_from_brief( array $brief, array $context = [] ): array {
	$archetype = sanitize_key( (string) ( $brief['intent']['archetype'] ?? 'unknown' ) );
	if ( ! in_array( $archetype, [ 'hero', 'process', 'pricing', 'faq', 'benefits' ], true ) ) {
		return [
			'schema' => WPAE_DESIGN_PLAN_SCHEMA,
			'archetype' => 'unknown',
			'sections' => [],
			'responsive' => [],
			'tokens' => [],
			'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
			'provenance' => [ 'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '', 'planner' => 'wpae-design-plan-v1' ],
			'warnings' => [ 'unsupported_archetype' ],
		];
	}
	$media_intent = (string) wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' );
	$explicit_composition = wpae_design_plan_constraint_value( $brief, 'composition' );
	$composition = (string) ( $explicit_composition ?? ( $archetype === 'hero' ? 'split_60_40' : ( $archetype === 'pricing' ? 'three_cards' : 'linear' ) ) );
	$media_references = array_values( array_filter( (array) ( $brief['media_references'] ?? [] ), static fn( $media ): bool => is_array( $media ) && wpae_design_plan_media_reference_valid( $media ) ) );
	$hero_has_media = $archetype === 'hero' && ! empty( $media_references ) && $media_intent !== 'forbidden' && $media_intent !== 'conflict';
	if ( $archetype === 'hero' && ! $hero_has_media && $explicit_composition === null && $media_intent !== 'conflict' ) {
		$composition = 'stacked_left';
	}
	$surface_override = strtolower( trim( (string) wpae_design_plan_constraint_value( $brief, 'surface_color', '' ) ) );
	if ( ! preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
		$surface_override = '';
	}
	$eyebrow_presentation = (string) wpae_design_plan_constraint_value( $brief, 'eyebrow_presentation', '' );
	$cta_refs = wpae_design_plan_content_refs( $brief, [ 'cta', 'cta_2', 'cta_3' ] );
	$tokens = [
		'color.page_bg' => 'color.page_bg',
		'color.surface' => 'color.surface',
		'color.text' => 'color.text',
		'color.muted' => 'color.muted',
		'color.primary' => 'color.primary',
		'color.border' => 'color.border',
		'type.display' => 'type.display',
		'type.body' => 'type.body',
		'space.section' => 'space.section',
		'space.component' => 'space.component',
		'radius.card' => 'radius.card',
	];
	$section = [
		'id' => $archetype,
		'role' => $archetype,
		'composition' => $composition,
		// Pricing owns a white surface so the card borders remain visible and the
		// section does not inherit the site's warm page background.
		'surface_token' => $archetype === 'pricing' ? 'color.surface' : 'color.page_bg',
		'spacing_token' => 'space.section',
		'children' => [],
		'provenance' => [ 'source' => 'brief' ],
	];
	if ( $surface_override !== '' ) {
		// Explicit prompt colour wins at the section boundary; the compiler still
		// records the semantic token for the default/reference path.
		$section['surface_override'] = $surface_override;
		$section['provenance']['surface_override'] = [ 'source' => 'prompt', 'constraint' => 'surface_color' ];
	}
	if ( $archetype === 'hero' && $eyebrow_presentation === 'pill' ) {
		$eyebrow_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow' ] );
		$section['badge_content_ref'] = $eyebrow_refs[0] ?? '';
	}
	if ( $archetype === 'hero' ) {
		$section['media_intent'] = $media_intent;
		$section['children'] = [
			[
				'role' => 'copy_group',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => array_values( array_unique( array_merge( wpae_design_plan_content_refs( $brief, [ 'brand', 'eyebrow', 'title', 'body' ] ), $cta_refs ) ) ),
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.body', 'space.component' ],
				'layout_constraints' => array_merge( [ 'min_width' => 0, 'max_width' => 100 ], $eyebrow_presentation === 'pill' ? [ 'eyebrow_presentation' => 'pill' ] : [] ),
				'responsive_policy' => 'copy_first_stack',
				'media_refs' => [],
				'editable_fields' => [ 'text', 'url' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'brand', 'eyebrow', 'title', 'body', 'cta' ] ],
			],
		];
		if ( $hero_has_media ) {
			$section['children'][] = [
				'role' => 'media',
				'allowed_widgets' => [ 'image' ],
				'content_refs' => [],
				'token_refs' => [ 'color.surface', 'color.border' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'copy_first_stack',
				'media_refs' => array_values( array_map( static fn( $item ): string => sanitize_key( (string) ( $item['asset_id'] ?? '' ) ), $media_references ) ),
				'editable_fields' => [ 'media', 'alt' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'media' ] ],
			];
		}
	} elseif ( $archetype === 'process' ) {
		$process_content = wpae_design_plan_process_content( $brief );
		$process_refs = [];
		foreach ( (array) $process_content['steps'] as $step ) {
			$process_refs = array_merge( $process_refs, array_filter( [ $step['label_ref'] ?? '', $step['text_ref'] ?? '' ] ) );
		}
		if ( (string) $process_content['badge_content_ref'] !== '' ) {
			$section['badge_content_ref'] = $process_content['badge_content_ref'];
		}
		$section['children'] = [
			[
				'role' => 'process_steps',
				'allowed_widgets' => [ 'heading', 'text-editor', 'divider' ],
				'content_refs' => array_values( array_unique( $process_refs ) ),
				'steps' => $process_content['steps'],
				'token_refs' => [ 'color.text', 'color.muted', 'color.border', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'connector' => true ],
				'responsive_policy' => 'stack',
				'media_refs' => [],
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'label', 'text' ] ],
			],
		];
	} elseif ( $archetype === 'pricing' ) {
		$section['children'] = [
			[
				'role' => 'pricing_cards',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => wpae_design_plan_content_refs( $brief ),
				'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.border', 'radius.card', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'media_refs' => [],
				'editable_fields' => [ 'text', 'url' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'title', 'body', 'label', 'cta' ] ],
			],
		];
	} elseif ( $archetype === 'faq' ) {
		$qa = wpae_design_plan_pairs( $brief, 'faq_question', 'faq_answer', 'question_ref', 'answer_ref' );
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'label' ] );
		$cta_refs = wpae_design_plan_content_refs( $brief, [ 'cta', 'cta_2', 'cta_3', 'cta_4' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [
				'role' => 'copy_group',
				'allowed_widgets' => [ 'heading' ],
				'content_refs' => $intro_refs,
				'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.body' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'eyebrow', 'title' ] ],
			];
		}
		$section['children'][] = [
			'role' => 'faq_accordion',
			'allowed_widgets' => [ 'accordion' ],
			'content_refs' => array_values( array_reduce( $qa['items'], static function ( array $refs, array $item ): array {
				return array_merge( $refs, [ $item['question_ref'], $item['answer_ref'] ] );
			}, [] ) ),
			'items' => $qa['items'],
			'token_refs' => [ 'color.text', 'color.muted', 'color.border', 'space.component' ],
			'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
			'responsive_policy' => 'stack',
			'editable_fields' => [ 'question', 'answer' ],
			'provenance' => [ 'source' => 'brief', 'roles' => [ 'faq_question', 'faq_answer' ] ],
		];
		if ( ! empty( $cta_refs ) ) {
			$section['children'][] = [
				'role' => 'copy_group',
				'allowed_widgets' => [ 'button' ],
				'content_refs' => $cta_refs,
				'token_refs' => [ 'color.primary', 'color.text', 'color.surface' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text', 'url' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'cta', 'cta_2', 'cta_3', 'cta_4' ] ],
			];
		}
		$section['qa_errors'] = $qa['errors'];
	} else {
		$features = wpae_design_plan_pairs( $brief, 'feature_title', 'feature_body', 'title_ref', 'body_ref' );
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'label', 'body', 'cta', 'cta_2', 'cta_3', 'cta_4' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [
				'role' => 'copy_group',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => $intro_refs,
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.body', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'eyebrow', 'title', 'body' ] ],
			];
		}
		$feature_refs = [];
		foreach ( $features['items'] as $item ) {
			$feature_refs = array_merge( $feature_refs, [ $item['title_ref'], $item['body_ref'] ] );
		}
		$section['children'][] = [
			'role' => 'feature_cards',
			'allowed_widgets' => [ 'container', 'icon', 'heading', 'text-editor' ],
			'content_refs' => array_values( $feature_refs ),
			'items' => $features['items'],
			'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.border', 'radius.card', 'space.component' ],
			'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
			'responsive_policy' => 'stack',
			'editable_fields' => [ 'text' ],
			'provenance' => [ 'source' => 'brief', 'roles' => [ 'feature_title', 'feature_body' ] ],
		];
		$section['feature_errors'] = $features['errors'];
	}
	return [
		'schema' => WPAE_DESIGN_PLAN_SCHEMA,
		'archetype' => $archetype,
		'sections' => [ $section ],
		'responsive' => [
			'desktop' => $composition,
			'tablet' => $archetype === 'hero' && $hero_has_media ? 'split_50_50' : 'stack',
			'mobile' => $archetype === 'hero' && $hero_has_media ? 'copy_first_stack' : 'stack',
		],
		'tokens' => $tokens,
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
		'provenance' => [
			'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '',
			'planner' => 'wpae-design-plan-v1',
			'source' => 'brief-ir',
		],
		'warnings' => array_values( array_unique( array_merge( empty( $brief['ambiguities'] ) ? [] : [ 'brief_has_ambiguities' ], $archetype === 'hero' && $media_intent === 'unspecified' && ! $hero_has_media ? [ 'media_unspecified_no_asset_text_only' ] : [] ) ) ),
		'explicit_badge' => $archetype === 'hero' && $eyebrow_presentation === 'pill',
		'media_intent' => $media_intent,
		'media_asset_count' => count( $media_references ),
	];
}

function wpae_design_plan_validate( array $plan ): array {
	$errors = [];
	$schema = wpae_design_plan_schema();
	if ( ( $plan['schema'] ?? '' ) !== WPAE_DESIGN_PLAN_SCHEMA ) {
		$errors[] = 'schema';
	}
	if ( ! in_array( $plan['archetype'] ?? '', $schema['archetypes'], true ) ) {
		$errors[] = 'archetype';
	}
	if ( empty( $plan['sections'] ) || ! is_array( $plan['sections'] ) ) {
		$errors[] = 'sections';
	}
	if ( ( $plan['archetype'] ?? '' ) === 'hero' ) {
		$media_intent = sanitize_key( (string) ( $plan['media_intent'] ?? 'unspecified' ) );
		$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
		$has_media_node = (bool) array_filter( (array) ( $section['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'media' );
		$split = in_array( (string) ( $section['composition'] ?? '' ), [ 'split_60_40', 'split_50_50', 'split_40_60' ], true );
		if ( ! empty( $plan['explicit_badge'] ) && trim( (string) ( $section['badge_content_ref'] ?? '' ) ) === '' ) {
			$errors[] = 'explicit_pill_badge_missing_eyebrow';
		}
		if ( $media_intent === 'conflict' ) {
			$errors[] = 'media_intent_conflict';
		}
		if ( $media_intent === 'forbidden' && $has_media_node ) {
			$errors[] = 'forbidden_media_in_plan';
		}
		if ( $media_intent === 'forbidden' && $split ) {
			$errors[] = 'media_forbidden_split_conflict';
		}
		if ( $media_intent === 'required' && ! $has_media_node ) {
			$errors[] = 'required_media_asset_missing';
		}
		if ( $split && ! $has_media_node && $media_intent !== 'forbidden' ) {
			$errors[] = 'split_composition_media_asset_missing';
		}
	}
	if ( ( $plan['archetype'] ?? '' ) === 'process' ) {
		$process_children = [];
		foreach ( (array) ( $plan['sections'] ?? [] ) as $section ) {
			if ( ! is_array( $section ) || ( $section['role'] ?? '' ) !== 'process' ) {
				continue;
			}
			foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
				if ( is_array( $child ) && ( $child['role'] ?? '' ) === 'process_steps' ) {
					$process_children[] = $child;
				}
			}
		}
		$steps = [];
		$content_refs = [];
		foreach ( $process_children as $child ) {
			$steps = array_merge( $steps, array_values( (array) ( $child['steps'] ?? [] ) ) );
			$content_refs = array_merge( $content_refs, (array) ( $child['content_refs'] ?? [] ) );
		}
		$content_refs = array_fill_keys( array_map( 'sanitize_key', $content_refs ), true );
		if ( empty( $steps ) ) {
			$errors[] = 'process_steps_missing_source_content';
		} else {
			foreach ( $steps as $index => $step ) {
				$label_ref = sanitize_key( (string) ( $step['label_ref'] ?? '' ) );
				$text_ref = sanitize_key( (string) ( $step['text_ref'] ?? '' ) );
				if ( $label_ref === '' || ! isset( $content_refs[ $label_ref ] ) || ( $text_ref !== '' && ! isset( $content_refs[ $text_ref ] ) ) ) {
					$errors[] = 'process_step_content_ref_invalid_' . ( (int) $index + 1 );
				}
			}
		}
	}
	if ( ( $plan['archetype'] ?? '' ) === 'faq' ) {
		$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
		$accordion = null;
		foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
			if ( is_array( $child ) && ( $child['role'] ?? '' ) === 'faq_accordion' ) {
				$accordion = $child;
				break;
			}
		}
		$items = (array) ( $accordion['items'] ?? [] );
		if ( empty( $items ) ) {
			$errors[] = 'faq_questions_and_answers_required';
		}
		foreach ( (array) ( $section['qa_errors'] ?? [] ) as $pair_error ) {
			$errors[] = 'faq_' . sanitize_key( (string) $pair_error );
		}
	}
	if ( ( $plan['archetype'] ?? '' ) === 'benefits' ) {
		$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
		$cards = null;
		foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
			if ( is_array( $child ) && ( $child['role'] ?? '' ) === 'feature_cards' ) {
				$cards = $child;
				break;
			}
		}
		$count = count( (array) ( $cards['items'] ?? [] ) );
		if ( $count < 2 || $count > 6 ) {
			$errors[] = 'benefits_require_two_to_six_complete_items';
		}
		foreach ( (array) ( $section['feature_errors'] ?? [] ) as $pair_error ) {
			$errors[] = 'benefits_' . sanitize_key( (string) $pair_error );
		}
	}
	$requested_widgets = [];
	foreach ( (array) ( $plan['sections'] ?? [] ) as $section_index => $section ) {
		if ( ! is_array( $section ) || trim( (string) ( $section['id'] ?? '' ) ) === '' ) {
			$errors[] = 'section_' . (int) $section_index;
			continue;
		}
		foreach ( (array) ( $section['children'] ?? [] ) as $child_index => $child ) {
			if ( ! is_array( $child ) || empty( $child['role'] ) ) {
				$errors[] = 'child_' . (int) $section_index . '_' . (int) $child_index;
				continue;
			}
			foreach ( (array) ( $child['allowed_widgets'] ?? [] ) as $widget_type ) {
				$requested_widgets[] = sanitize_key( (string) $widget_type );
			}
		}
	}
	$capabilities = function_exists( 'wpae_widget_capability_report' ) ? wpae_widget_capability_report( $requested_widgets ) : [ 'ok' => true, 'unavailable' => [], 'downgrades' => [] ];
	if ( empty( $capabilities['ok'] ) ) {
		foreach ( (array) ( $capabilities['failures'] ?? [] ) as $failure ) {
			$errors[] = 'widget_capability:' . sanitize_key( (string) ( $failure['from'] ?? 'unknown' ) ) . ':' . sanitize_key( (string) ( $failure['reason'] ?? 'unavailable' ) );
		}
	}
	$token_report = function_exists( 'wpae_design_token_validate_refs' ) ? wpae_design_token_validate_refs( array_keys( (array) ( $plan['tokens'] ?? [] ) ) ) : [ 'ok' => true, 'missing' => [] ];
	if ( ! empty( $token_report['missing'] ) ) {
		$errors[] = 'missing_tokens:' . implode( ',', $token_report['missing'] );
	}
	$contrast = function_exists( 'wpae_design_token_validate_contrast' ) ? wpae_design_token_validate_contrast() : [ 'ok' => true, 'errors' => [] ];
	if ( empty( $contrast['ok'] ) ) {
		$errors[] = 'contrast:' . implode( ',', (array) ( $contrast['errors'] ?? [] ) );
	}
	return [ 'ok' => empty( $errors ), 'errors' => array_values( $errors ), 'capabilities' => $capabilities, 'tokens' => $token_report, 'contrast' => $contrast ];
}

function wpae_design_plan_hash( array $plan ): string {
	return hash( 'sha256', (string) wp_json_encode( $plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION ) );
}
