<?php

/** Deterministic width/overflow report for generated layout decisions. */

defined( 'ABSPATH' ) || exit;

const WPAE_LAYOUT_REPORT_SCHEMA = 'wpae-layout-report-v1';

function wpae_layout_report_breakpoints(): array {
	return [
		[ 'id' => 'desktop', 'width' => 1440 ],
		[ 'id' => 'laptop', 'width' => 1024 ],
		[ 'id' => 'tablet', 'width' => 768 ],
		[ 'id' => 'mobile', 'width' => 390 ],
	];
}

function wpae_layout_report_composition_basis( string $composition ): array {
	return [
		'split_60_40' => [ 60, 40 ],
		'split_50_50' => [ 50, 50 ],
		'split_40_60' => [ 40, 60 ],
		'three_cards' => [ 33.333, 33.333, 33.333 ],
		'linear' => [ 100 ],
		'stacked_left' => [ 100, 100 ],
	][ $composition ] ?? [ 100 ];
}

function wpae_layout_report_length_px( $value, int $viewport, float $fallback ): float {
	if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)\s*(px|rem|em|vw|%)?$/i', trim( (string) $value ), $matches ) ) {
		return $fallback;
	}
	$size = (float) $matches[1];
	$unit = strtolower( (string) ( $matches[2] ?? 'px' ) );
	return $unit === 'rem' || $unit === 'em' ? $size * 16 : ( $unit === 'vw' || $unit === '%' ? $viewport * $size / 100 : $size );
}

function wpae_layout_report_for_plan( array $plan, array $options = [] ): array {
	$reports = [];
	$violations = [];
	$basis_overrides = is_array( $options['basis_overrides'] ?? null ) ? $options['basis_overrides'] : [];
	$gap_override = isset( $options['gap'] ) && is_numeric( $options['gap'] ) ? max( 0, (float) $options['gap'] ) : null;
	$tokens = is_array( $options['tokens'] ?? null ) ? $options['tokens'] : [];
	$gap_token = function_exists( 'wpae_design_token_value' ) ? wpae_design_token_value( 'space.component', $tokens ) : '1.5rem';
	$archetype = sanitize_key( (string) ( $plan['archetype'] ?? 'unknown' ) );
	$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
	$composition = sanitize_key( (string) ( $section['composition'] ?? 'stacked_left' ) );
	$children = (array) ( $section['children'] ?? [] );
	$has_media_child = (bool) array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'media' );
	foreach ( wpae_layout_report_breakpoints() as $breakpoint ) {
		$viewport = (int) $breakpoint['width'];
		$is_mobile = $viewport <= 767;
		$horizontal_padding = $is_mobile ? 16 : 32;
		$container_width = max( 0, $viewport - ( $horizontal_padding * 2 ) );
		$breakpoint_composition = $breakpoint['id'] === 'mobile'
			? sanitize_key( (string) ( $plan['responsive']['mobile'] ?? 'stack' ) )
			: ( in_array( $breakpoint['id'], [ 'laptop', 'tablet' ], true ) ? sanitize_key( (string) ( $plan['responsive']['tablet'] ?? $composition ) ) : $composition );
		$is_split_composition = in_array( $breakpoint_composition, [ 'split_60_40', 'split_50_50', 'split_40_60' ], true );
		$is_stack = ! $is_split_composition || ( $is_mobile && ( $breakpoint_composition === 'copy_first_stack' || ( $plan['responsive']['mobile'] ?? '' ) === 'copy_first_stack' ) );
		$basis_percentages = $is_split_composition ? wpae_layout_report_composition_basis( $breakpoint_composition ) : [];
		$gap = $gap_override ?? wpae_layout_report_length_px( $gap_token, $viewport, 24 );
		$available_width = max( 0, $container_width - ( $is_stack ? 0 : $gap * max( 0, count( $children ) - 1 ) ) );
		$basis = [];
		$basis_percentages_for_report = [];
		$min_width = [];
		$max_width = [];
		$zero_width_nodes = [];
		$overflow_nodes = [];
		$fixed_text_heights = [];
		$suggested_patches = [];
		$total = 0.0;
		foreach ( array_values( $children ) as $index => $child ) {
			$node_id = sanitize_key( (string) ( $child['role'] ?? 'child_' . $index ) );
			$percentage = (float) ( $basis_percentages[ $index ] ?? ( count( $children ) > 0 ? 100 / count( $children ) : 100 ) );
			if ( array_key_exists( $node_id, $basis_overrides ) && is_numeric( $basis_overrides[ $node_id ] ) ) {
				$percentage = (float) $basis_overrides[ $node_id ];
			}
			// A stacked column consumes the available width on the cross axis.
			// Its desktop composition percentages describe the row only and must
			// not be applied to the mobile column width.
			$basis_percent = $is_stack && ! array_key_exists( $node_id, $basis_overrides ) ? 100.0 : $percentage;
			$child_basis = $available_width * ( $basis_percent / 100 );
			$basis[ $node_id ] = round( $child_basis, 2 );
			$basis_percentages_for_report[ $node_id ] = $basis_percent;
			$min_width[ $node_id ] = (float) ( $child['layout_constraints']['min_width'] ?? 0 );
			$max_width[ $node_id ] = (float) ( $child['layout_constraints']['max_width'] ?? 100 );
			$total += $child_basis;
			if ( $basis_percent < $min_width[ $node_id ] ) {
				$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'basis_below_min_width', 'node_id' => $node_id, 'basis_percent' => $basis_percent, 'min_width' => $min_width[ $node_id ] ];
			}
			if ( $basis_percent > $max_width[ $node_id ] ) {
				$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'basis_above_max_width', 'node_id' => $node_id, 'basis_percent' => $basis_percent, 'max_width' => $max_width[ $node_id ] ];
			}
			if ( $child_basis <= 0 ) {
				$zero_width_nodes[] = $node_id;
			}
			if ( ! empty( $child['layout_constraints']['fixed_height'] ) && in_array( $child['role'] ?? '', [ 'copy_group', 'title', 'body', 'text' ], true ) ) {
				$fixed_text_heights[] = $node_id;
			}
			if ( in_array( $child['role'] ?? '', [ 'copy_group', 'body', 'title', 'cta' ], true ) && ! empty( $child['content_refs'] ) ) {
				$suggested_patches[] = [ 'node_id' => $node_id, 'patch' => 'allow_intrinsic_text_height' ];
			}
		}
		$used_width = $is_stack ? ( $children ? max( $basis ?: [ 0 ] ) : 0 ) : $total;
		$required_width = $is_stack ? $used_width : $used_width + $gap * max( 0, count( $children ) - 1 );
		if ( ! $is_stack && $required_width > $container_width + 0.01 ) {
			$overflow_nodes[] = 'section';
			$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'width_overflow', 'required' => round( $required_width, 2 ), 'available' => $container_width ];
		}
		if ( $is_stack && $used_width > $container_width + 0.01 ) {
			$overflow_nodes[] = 'mobile_stack';
			$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'mobile_overflow', 'required' => round( $used_width, 2 ), 'available' => $container_width ];
		}
		foreach ( $zero_width_nodes as $node_id ) {
			$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'zero_width_child', 'node_id' => $node_id ];
		}
		foreach ( $fixed_text_heights as $node_id ) {
			$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'fixed_text_height', 'node_id' => $node_id ];
		}
		$reports[] = [
			'breakpoint' => $breakpoint['id'],
			'composition' => $breakpoint_composition,
			'viewport_width' => $viewport,
			'container_width' => $container_width,
			'used_width' => round( $used_width, 2 ),
			'available_width' => $available_width,
			'layout_axis' => $is_stack ? 'column' : 'row',
			'gaps' => $is_stack ? [ 'axis' => 'column', 'size' => $gap ] : [ 'axis' => 'row', 'size' => $gap, 'count' => max( 0, count( $children ) - 1 ) ],
			'basis' => $basis,
			'basis_percent' => $basis_percentages_for_report,
			'min_width' => $min_width,
			'max_width' => $max_width,
			'zero_width_nodes' => $zero_width_nodes,
			'overflow_nodes' => $overflow_nodes,
			'fixed_text_heights' => $fixed_text_heights,
			'mobile_stack_result' => $is_stack ? ( $archetype === 'hero' ? ( $has_media_child ? 'copy_first_stack' : 'text_only_single_column' ) : 'stack' ) : 'not_applicable',
			'violations' => array_values( array_filter( $violations, static fn( array $violation ): bool => ( $violation['breakpoint'] ?? '' ) === $breakpoint['id'] ) ),
			'suggested_patches' => $suggested_patches,
		];
	}
	return [
		'schema' => WPAE_LAYOUT_REPORT_SCHEMA,
		'archetype' => $archetype,
		'evidence' => 'static_plan',
		'visual_render_verified' => false,
		'breakpoint_source' => 'static_assumptions; runtime Elementor breakpoints must be checked in browser',
		'breakpoints' => $reports,
		'violations' => $violations,
		'ok' => empty( $violations ),
	];
}

function wpae_layout_report_validate( array $report ): array {
	$errors = [];
	if ( ( $report['schema'] ?? '' ) !== WPAE_LAYOUT_REPORT_SCHEMA ) {
		$errors[] = 'schema';
	}
	$seen = [];
	foreach ( (array) ( $report['breakpoints'] ?? [] ) as $breakpoint ) {
		$name = (string) ( $breakpoint['breakpoint'] ?? '' );
		if ( $name === '' || isset( $seen[ $name ] ) ) {
			$errors[] = 'breakpoint_identity';
		}
		$seen[ $name ] = true;
		if ( (float) ( $breakpoint['used_width'] ?? 0 ) < 0 || (float) ( $breakpoint['used_width'] ?? 0 ) > (float) ( $breakpoint['container_width'] ?? 0 ) + 0.01 ) {
			$errors[] = $name . ':used_width';
		}
	}
	return [ 'ok' => empty( $errors ), 'errors' => array_values( $errors ), 'violations' => (array) ( $report['violations'] ?? [] ) ];
}
