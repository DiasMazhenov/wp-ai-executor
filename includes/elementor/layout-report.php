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

function wpae_layout_report_for_plan( array $plan, array $options = [] ): array {
	$reports = [];
	$violations = [];
	$basis_overrides = is_array( $options['basis_overrides'] ?? null ) ? $options['basis_overrides'] : [];
	$gap_override = isset( $options['gap'] ) && is_numeric( $options['gap'] ) ? max( 0, (float) $options['gap'] ) : null;
	$archetype = sanitize_key( (string) ( $plan['archetype'] ?? 'unknown' ) );
	$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
	$composition = sanitize_key( (string) ( $section['composition'] ?? 'stacked_left' ) );
	$basis_percentages = wpae_layout_report_composition_basis( $composition );
	$children = (array) ( $section['children'] ?? [] );
	foreach ( wpae_layout_report_breakpoints() as $breakpoint ) {
		$viewport = (int) $breakpoint['width'];
		$container_width = max( 0, min( 1200, $viewport - 64 ) );
		$is_mobile = $viewport <= 390;
		$is_stack = $is_mobile || ( $archetype !== 'hero' && $viewport <= 768 ) || ( $archetype === 'hero' && ( $plan['responsive']['mobile'] ?? '' ) === 'copy_first_stack' && $is_mobile );
		$gap = $gap_override ?? ( $is_stack ? 16 : 32 );
		$available_width = max( 0, $container_width - ( $is_stack ? 0 : $gap * max( 0, count( $children ) - 1 ) ) );
		$basis = [];
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
			$child_basis = $is_stack ? $container_width * ( $percentage / 100 ) : $available_width * ( $percentage / 100 );
			$basis[ $node_id ] = round( $child_basis, 2 );
			$min_width[ $node_id ] = (float) ( $child['layout_constraints']['min_width'] ?? 0 );
			$max_width[ $node_id ] = (float) ( $child['layout_constraints']['max_width'] ?? 100 );
			$total += $child_basis;
			if ( $percentage < $min_width[ $node_id ] ) {
				$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'basis_below_min_width', 'node_id' => $node_id, 'basis_percent' => $percentage, 'min_width' => $min_width[ $node_id ] ];
			}
			if ( $percentage > $max_width[ $node_id ] ) {
				$violations[] = [ 'breakpoint' => $breakpoint['id'], 'kind' => 'basis_above_max_width', 'node_id' => $node_id, 'basis_percent' => $percentage, 'max_width' => $max_width[ $node_id ] ];
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
			'viewport_width' => $viewport,
			'container_width' => $container_width,
			'used_width' => round( $used_width, 2 ),
			'available_width' => $available_width,
			'gaps' => $is_stack ? [ 'axis' => 'column', 'size' => $gap ] : [ 'axis' => 'row', 'size' => $gap, 'count' => max( 0, count( $children ) - 1 ) ],
			'basis' => $basis,
			'min_width' => $min_width,
			'max_width' => $max_width,
			'zero_width_nodes' => $zero_width_nodes,
			'overflow_nodes' => $overflow_nodes,
			'fixed_text_heights' => $fixed_text_heights,
			'mobile_stack_result' => $is_stack ? ( $archetype === 'hero' ? 'copy_first_stack' : 'stack' ) : 'not_applicable',
			'violations' => array_values( array_filter( $violations, static fn( array $violation ): bool => ( $violation['breakpoint'] ?? '' ) === $breakpoint['id'] ) ),
			'suggested_patches' => $suggested_patches,
		];
	}
	return [
		'schema' => WPAE_LAYOUT_REPORT_SCHEMA,
		'archetype' => $archetype,
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
