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
	$tokens = is_array( $plan['resolved_visual']['values'] ?? null ) ? $plan['resolved_visual']['values'] : ( is_array( $options['tokens'] ?? null ) ? $options['tokens'] : [] );
	$gap_token = function_exists( 'wpae_design_token_value' ) ? wpae_design_token_value( 'space.component', $tokens ) : '1.5rem';
	$archetype = sanitize_key( (string) ( $plan['archetype'] ?? 'unknown' ) );
	$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
	$composition = sanitize_key( (string) ( $section['composition'] ?? 'stacked_left' ) );
	$children = (array) ( $section['children'] ?? [] );
	$recipe_layout = [];
	$recipe_id = (string) ( $plan['recipe_id'] ?? '' );
	if ( $recipe_id !== '' ) {
		$recipe_child = [];
		foreach ( $children as $child ) {
			if ( is_array( $child ) && str_starts_with( (string) ( $child['role'] ?? '' ), 'services_' ) ) {
				$recipe_child = $child;
				break;
			}
		}
		$item_count = count( (array) ( $recipe_child['items'] ?? [] ) );
		$recipe_layout = [
			'recipe_id' => $recipe_id,
			'evidence' => 'static_plan',
			'visual_render_verified' => false,
			'item_count' => $item_count,
			'card_or_row_padding_px' => $recipe_id === 'services.photo_cards' ? [ 'desktop' => 24, 'tablet' => 22, 'mobile' => 20 ] : [ 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ],
			'geometry_assumptions' => [ 'section_padding_px' => [ 'desktop' => 32, 'tablet' => 32, 'mobile' => 16 ], 'container_width_samples_px' => array_values( (array) ( $options['container_widths'] ?? [ 320, 390, 480, 768, 1024, 1200 ] ) ), 'basis_measurement' => 'native_Elementor_container_width_percent_with_flex_gap', 'elementor_thresholds_source' => 'static_desktop_tablet_mobile_labels; site runtime breakpoints are not queried by this source-only report' ],
			'native_breakpoint_controls' => [],
			'geometry_samples' => [],
			'breakpoints' => [],
		];
		$recipe_layout['native_breakpoint_controls'] = $recipe_id === 'services.photo_cards'
			? [ 'grid' => [ 'desktop' => [ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'column_basis_percent' => $item_count === 3 ? 31.5 : 48, 'gap_px' => 24 ], 'tablet' => [ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'column_basis_percent' => 48, 'gap_px' => 20 ], 'mobile' => [ 'flex_direction' => 'column', 'flex_wrap' => 'nowrap', 'column_basis_percent' => 100, 'gap_px' => 16 ] ], 'card_padding_px' => [ 'desktop' => 24, 'tablet' => 22, 'mobile' => 20 ] ]
			: ( $recipe_id === 'services.split_editorial'
				? [ 'lead' => [ 'desktop' => [ 'flex_direction' => 'row', 'flex_wrap' => 'nowrap', 'copy_basis_percent' => 52, 'image_basis_percent' => 44, 'gap_px' => 32, 'flex_shrink' => 1 ], 'tablet' => [ 'flex_direction' => 'column', 'child_basis_percent' => 100, 'gap_px' => 24 ], 'mobile' => [ 'flex_direction' => 'column', 'child_basis_percent' => 100, 'gap_px' => 20 ] ] ]
				: [ 'list' => [ 'desktop' => [ 'flex_direction' => 'column', 'gap_px' => 24 ], 'tablet' => [ 'flex_direction' => 'column', 'gap_px' => 24 ], 'mobile' => [ 'flex_direction' => 'column', 'gap_px' => 20 ] ], 'row' => [ 'flex_direction' => 'row', 'icon_width_px' => 36, 'copy_basis_percent' => 90, 'copy_min_width_px' => 0, 'gap_px' => 24 ] ] );
		$container_width_samples = array_map( 'intval', (array) ( $options['container_widths'] ?? [ 320, 390, 480, 768, 1024, 1200 ] ) );
		$container_width_samples = array_values( array_unique( array_filter( $container_width_samples, static fn( int $width ): bool => $width > 0 ) ) );
		foreach ( $container_width_samples as $container_sample ) {
			$device = $container_sample <= 767 ? 'mobile' : ( $container_sample <= 1024 ? 'tablet' : 'desktop' );
			$outer_padding = $device === 'mobile' ? 16 : 32;
			$available = max( 0.0, $container_sample - ( 2 * $outer_padding ) );
			$overflow = false;
			$sample = [ 'container_width_px' => $container_sample, 'device_assumption' => $device, 'outer_horizontal_padding_px' => $outer_padding, 'available_content_width_px' => round( $available, 2 ), 'recipe_id' => $recipe_id ];
			if ( $recipe_id === 'services.photo_cards' ) {
				$gap = $device === 'mobile' ? 16 : ( $device === 'tablet' ? 20 : 24 );
				$basis = $device === 'mobile' ? 100.0 : ( $device === 'tablet' ? 48.0 : ( $item_count === 3 ? 31.5 : 48.0 ) );
				$card_basis_width = $available * $basis / 100;
				$columns = $device === 'mobile' ? 1 : max( 1, (int) floor( ( $available + $gap ) / max( 1, $card_basis_width + $gap ) ) );
				$columns = min( max( 1, $item_count ), $columns );
				$row_width = $columns * $card_basis_width + $gap * max( 0, $columns - 1 );
				$overflow = $row_width > $available + 0.01;
				$sample += [ 'gap_px' => $gap, 'card_basis_percent' => $basis, 'card_basis_width_px' => round( $card_basis_width, 2 ), 'items_per_row_after_native_wrap' => $columns, 'row_count' => $item_count > 0 ? (int) ceil( $item_count / $columns ) : 0, 'row_required_width_px' => round( $row_width, 2 ), 'overflow' => $overflow ];
			} elseif ( $recipe_id === 'services.split_editorial' ) {
				$stack = $device !== 'desktop';
				$gap = $stack ? ( $device === 'mobile' ? 20 : 24 ) : 32;
				$copy_basis = $stack ? $available : $available * 0.52;
				$image_basis = $stack ? $available : $available * 0.44;
				$raw_required = $stack ? $available : $copy_basis + $image_basis + $gap;
				$shrink = $stack ? 0.0 : max( 0.0, $raw_required - $available );
				$copy_effective = $stack ? $available : max( 0.0, $copy_basis - ( $shrink > 0 && $raw_required > $gap ? $shrink * $copy_basis / ( $copy_basis + $image_basis ) : 0 ) );
				$image_effective = $stack ? $available : max( 0.0, $image_basis - ( $shrink > 0 && $raw_required > $gap ? $shrink * $image_basis / ( $copy_basis + $image_basis ) : 0 ) );
				$required_width = $stack ? max( $copy_effective, $image_effective ) : $copy_effective + $image_effective + $gap;
				$overflow = $required_width > $available + 0.01;
				$sample += [ 'axis' => $stack ? 'column' : 'row', 'gap_px' => $gap, 'raw_copy_basis_width_px' => round( $copy_basis, 2 ), 'raw_image_basis_width_px' => round( $image_basis, 2 ), 'native_flex_shrink_adjustment_px' => round( $shrink, 2 ), 'effective_copy_width_px' => round( $copy_effective, 2 ), 'effective_image_width_px' => round( $image_effective, 2 ), 'required_width_after_native_shrink_px' => round( $required_width, 2 ), 'overflow' => $overflow ];
			} else {
				$gap = $device === 'mobile' ? 20 : 24;
				$row_gap = 24;
				$icon_width = 36.0;
				$copy_basis = $available * 0.9;
				$copy_effective = max( 0.0, min( $copy_basis, $available - $icon_width - $gap ) );
				$sample += [ 'list_gap_px' => $row_gap, 'item_axis' => 'row', 'item_gap_px' => $gap, 'icon_width_px' => $icon_width, 'copy_basis_percent' => 90, 'copy_effective_width_px' => round( $copy_effective, 2 ), 'overflow' => ( $icon_width + $gap + $copy_effective ) > $available + 0.01 ];
			}
			$sample['card_or_row_inner_padding_px'] = $recipe_layout['card_or_row_padding_px'][ $device ] ?? 0;
			$recipe_layout['geometry_samples'][] = $sample;
			if ( $overflow ) {
				$violations[] = [ 'breakpoint' => $device, 'kind' => 'services_recipe_container_overflow', 'container_width_px' => $container_sample, 'recipe_id' => $recipe_id, 'required_width_px' => $sample['row_required_width_px'] ?? $sample['required_width_after_native_shrink_px'] ?? 0, 'available_width_px' => $available ];
			}
		}
		foreach ( wpae_layout_report_breakpoints() as $breakpoint ) {
			$viewport = (int) $breakpoint['width'];
			$mobile = $breakpoint['id'] === 'mobile';
			$tablet = in_array( $breakpoint['id'], [ 'tablet', 'laptop' ], true );
			$outer = min( $viewport, 1200 );
			$content_width = max( 0, $outer - ( $mobile ? 32 : 64 ) );
			$columns = $recipe_id === 'services.photo_cards' ? ( $mobile ? 1 : ( $tablet ? 2 : ( $item_count === 3 ? 3 : 2 ) ) ) : 1;
			$gap = $recipe_id === 'services.photo_cards' ? ( $mobile ? 16 : ( $tablet ? 20 : 24 ) ) : ( $mobile ? 20 : 24 );
			$column_width = $columns > 0 ? max( 0, ( $content_width - ( $gap * max( 0, $columns - 1 ) ) ) / $columns ) : 0;
			$recipe_layout['breakpoints'][] = [
				'breakpoint' => $breakpoint['id'],
				'viewport_width' => $viewport,
				'container_width' => $content_width,
				'columns' => $columns,
				'column_gap_px' => $gap,
				'column_basis_percent' => $recipe_id === 'services.photo_cards'
					? ( $columns === 3 ? 31.5 : 48 )
					: ( $recipe_id === 'services.split_editorial'
						? ( ! $tablet && ! $mobile ? [ 'copy' => 52, 'image' => 44 ] : [ 'copy' => 100 ] )
						: [ 'icon' => 10, 'copy' => 90 ] ),
				'estimated_column_width_px' => round( $column_width, 2 ),
				'axis' => $recipe_id === 'services.photo_cards'
					? ( $columns > 1 ? 'row_wrap' : 'vertical_stack' )
					: ( $recipe_id === 'services.split_editorial'
						? ( ! $tablet && ! $mobile ? 'lead_row_then_editorial_stack' : 'vertical_stack' )
						: 'vertical_list_with_horizontal_item_rows' ),
			];
		}
	}
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
		$device = $is_mobile ? 'mobile' : ( in_array( $breakpoint['id'], [ 'tablet', 'laptop' ], true ) ? 'tablet' : 'desktop' );
        $gap_ref = 'space.component' . ( $device === 'desktop' ? '' : '_' . $device );
        $gap = $gap_override ?? wpae_layout_report_length_px( $tokens[ $gap_ref ] ?? $gap_token, $viewport, 24 );
        if ( ! empty( $plan['visual_policy'] ) && ! in_array( $composition, [ 'split_60_40', 'split_50_50', 'split_40_60' ], true ) ) { $gap = wpae_layout_report_length_px( $plan['visual_policy']['spacing']['intro_collection'], $viewport, 32 ); }
        $copy_widths = [];
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
            if ( ( $child['role'] ?? '' ) === 'copy_group' && ! empty( $plan['resolved_visual']['profile'] ) && isset( $tokens['layout.copy_width'] ) ) {
                $copy_widths[ $node_id ] = round( $is_mobile ? $child_basis : min( $child_basis, wpae_layout_report_length_px( $tokens['layout.copy_width'], $viewport, $child_basis ) ), 2 );
            }
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
            'boxed_copy_content_width_px' => $copy_widths,
            'gap_token' => $gap_ref,
            'device_assumption' => $device,
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
	$collections = [];
	if ( ! empty( $plan['visual_policy'] ) ) {
		foreach ( $children as $child ) {
			if ( ! in_array( $child['role'] ?? '', [ 'feature_cards', 'pricing_cards', 'service_cards', 'team_cards', 'testimonial_cards', 'services_photo_grid' ], true ) ) { continue; }
			$collection = $plan['visual_policy']['collection'];
			$samples = [];
			foreach ( $reports as $row ) {
				$device = $row['device_assumption'];
				$columns = $collection['columns'][ $device ];
				$gap = wpae_layout_report_length_px( $collection['gap'][ $device ], $row['viewport_width'], 24 );
				$width = $row['container_width'];
				$cell = max( 0, ( $width - max( 0, $columns - 1 ) * $gap ) / $columns );
				$samples[] = [ 'breakpoint' => $row['breakpoint'], 'container_width' => $width, 'columns' => $columns, 'gap_px' => $gap, 'cell_width_px' => $cell, 'used_width' => $cell * $columns + max( 0, $columns - 1 ) * $gap, 'formula' => '(container_width - (columns - 1) * gap) / columns', 'evidence' => 'static_plan', 'visual_render_verified' => false ];
			}
			$collections[] = [ 'role' => $child['role'], 'item_count' => count( (array) ( $child['items'] ?? [] ) ), 'native_controls' => $collection, 'samples' => $samples ];
		}
		if ( $recipe_id !== '' ) { $recipe_layout = [ 'recipe_id' => $recipe_id, 'evidence' => 'static_plan', 'visual_render_verified' => false, 'collections' => $collections ]; }
	}
	return [
		'schema' => WPAE_LAYOUT_REPORT_SCHEMA,
		'archetype' => $archetype,
		'recipe_layout' => $recipe_layout,
		'collections' => $collections,
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
