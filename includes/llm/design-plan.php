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
		'archetypes' => [ 'hero', 'process', 'pricing' ],
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

function wpae_design_plan_from_brief( array $brief, array $context = [] ): array {
	$archetype = sanitize_key( (string) ( $brief['intent']['archetype'] ?? 'unknown' ) );
	if ( ! in_array( $archetype, [ 'hero', 'process', 'pricing' ], true ) ) {
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
	$composition = (string) wpae_design_plan_constraint_value( $brief, 'composition', $archetype === 'hero' ? 'split_60_40' : ( $archetype === 'pricing' ? 'three_cards' : 'linear' ) );
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
		'surface_token' => 'color.page_bg',
		'spacing_token' => 'space.section',
		'children' => [],
	];
	if ( $archetype === 'hero' ) {
		$section['children'] = [
			[
				'role' => 'copy_group',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => array_values( array_unique( array_merge( wpae_design_plan_content_refs( $brief, [ 'brand', 'eyebrow', 'title', 'body' ] ), $cta_refs ) ) ),
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.body', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'copy_first_stack',
				'media_refs' => [],
				'editable_fields' => [ 'text', 'url' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'brand', 'eyebrow', 'title', 'body', 'cta' ] ],
			],
			[
				'role' => 'media',
				'allowed_widgets' => [ 'image' ],
				'content_refs' => [],
				'token_refs' => [ 'color.surface', 'color.border' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'copy_first_stack',
				'media_refs' => array_values( array_map( static fn( $item ): string => sanitize_key( (string) ( $item['asset_id'] ?? '' ) ), (array) ( $brief['media_references'] ?? [] ) ) ),
				'editable_fields' => [ 'media', 'alt' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'media' ] ],
			],
		];
	} elseif ( $archetype === 'process' ) {
		$section['children'] = [
			[
				'role' => 'process_steps',
				'allowed_widgets' => [ 'heading', 'icon-list', 'divider' ],
				'content_refs' => wpae_design_plan_content_refs( $brief ),
				'token_refs' => [ 'color.text', 'color.muted', 'color.border', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'connector' => true ],
				'responsive_policy' => 'stack',
				'media_refs' => [],
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'label', 'text' ] ],
			],
		];
	} else {
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
	}
	return [
		'schema' => WPAE_DESIGN_PLAN_SCHEMA,
		'archetype' => $archetype,
		'sections' => [ $section ],
		'responsive' => [
			'desktop' => $composition,
			'tablet' => $archetype === 'hero' ? 'split_50_50' : 'stack',
			'mobile' => $archetype === 'hero' ? 'copy_first_stack' : 'stack',
		],
		'tokens' => $tokens,
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
		'provenance' => [
			'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '',
			'planner' => 'wpae-design-plan-v1',
			'source' => 'brief-ir',
		],
		'warnings' => empty( $brief['ambiguities'] ) ? [] : [ 'brief_has_ambiguities' ],
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
	if ( ! empty( $capabilities['unavailable'] ) ) {
		$errors[] = 'unavailable_widgets:' . implode( ',', $capabilities['unavailable'] );
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
