<?php

/**
 * ElementorIR v2: the only layer allowed to choose widgetType and IDs for the
 * new pipeline. Legacy provider trees remain outside this compiler boundary.
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/design/token-resolution.php';

const WPAE_ELEMENTOR_IR_SCHEMA = 'wpae-elementor-ir-v2';

function wpae_elementor_ir_content_map( array $brief ): array {
	$map = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( is_array( $item ) && trim( (string) ( $item['id'] ?? '' ) ) !== '' ) {
			$map[ sanitize_key( (string) $item['id'] ) ] = $item;
		}
	}
	return $map;
}

function wpae_elementor_ir_node( string $node_id, string $role, string $widget_type, array $content_refs = [], array $token_refs = [], array $children = [], array $layout = [], array $responsive = [] ): array {
	return [
		'node_id' => sanitize_key( $node_id ),
		'role' => sanitize_key( $role ),
		'widget_type' => sanitize_key( $widget_type ),
		'content_refs' => array_values( array_filter( array_map( 'sanitize_key', $content_refs ) ) ),
		'token_refs' => array_values( array_filter( array_map( 'wpae_design_token_ref', $token_refs ) ) ),
		'layout_constraints' => $layout,
		'responsive_policy' => sanitize_key( $responsive['strategy'] ?? 'inherit' ),
		'media_refs' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $responsive['media_refs'] ?? [] ) ) ) ),
		'editable_fields' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $responsive['editable_fields'] ?? [] ) ) ) ),
		'children' => $children,
		'provenance' => [ 'source' => 'design-plan', 'plan_node' => sanitize_key( $node_id ) ],
	];
}

function wpae_elementor_ir_from_design_plan( array $plan, array $brief, array $context = [] ): array {
	$content_map = wpae_elementor_ir_content_map( $brief );
	$nodes = [];
	$warnings = [];
	foreach ( (array) ( $plan['sections'] ?? [] ) as $section_index => $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}
		$section_children = [];
		$badge_ref = sanitize_key( (string) ( $section['badge_content_ref'] ?? '' ) );
		if ( sanitize_key( (string) ( $section['role'] ?? '' ) ) === 'process' && $badge_ref !== '' && isset( $content_map[ $badge_ref ] ) ) {
			$badge_label = wpae_elementor_ir_node( sanitize_key( (string) ( $section['id'] ?? 'process' ) ) . '-badge-label', 'process_badge_label', 'heading', [ $badge_ref ], [ 'color.surface', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			$section_children[] = wpae_elementor_ir_node( sanitize_key( (string) ( $section['id'] ?? 'process' ) ) . '-badge', 'process_badge', 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
		}
		foreach ( (array) ( $section['children'] ?? [] ) as $child_index => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$role = sanitize_key( (string) ( $child['role'] ?? 'content' ) );
			$child_id = sanitize_key( (string) ( $section['id'] ?? 'section' ) . '-' . $role . '-' . (int) $child_index );
			$content_refs = array_values( array_filter( array_map( 'sanitize_key', (array) ( $child['content_refs'] ?? [] ) ) ) );
			$allowed = array_values( array_filter( array_map( 'sanitize_key', (array) ( $child['allowed_widgets'] ?? [] ) ) ) );
			$token_refs = array_values( array_filter( array_map( 'wpae_design_token_ref', (array) ( $child['token_refs'] ?? [] ) ) ) );
			if ( $role === 'copy_group' ) {
				$widgets = [];
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					$item_role = sanitize_key( (string) ( $item['role'] ?? '' ) );
					if ( $item_role === 'brand' ) {
						$widgets['brand'] = wpae_elementor_ir_node( $child_id . '-brand', 'brand', 'heading', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'title' ) {
						$widgets['title'] = wpae_elementor_ir_node( $child_id . '-title', 'title', 'heading', [ $content_ref ], [ 'color.text', 'type.display' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'eyebrow' ) {
						if ( ( $child['layout_constraints']['eyebrow_presentation'] ?? '' ) === 'pill' ) {
							$badge_label = wpae_elementor_ir_node( $child_id . '-eyebrow-badge-label', 'eyebrow_badge_label', 'heading', [ $content_ref ], [ 'color.surface', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow-badge', 'hero_badge', 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						} else {
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow', 'eyebrow', 'heading', [ $content_ref ], [ 'color.primary', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						}
					} elseif ( $item_role === 'body' || $item_role === 'text' ) {
						$widgets['body'] = wpae_elementor_ir_node( $child_id . '-body', 'body', 'text-editor', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( str_starts_with( $item_role, 'cta' ) || $item_role === 'button' ) {
						$cta_count = count( array_filter( array_keys( $widgets ), static fn( string $key ): bool => str_starts_with( $key, 'cta_' ) ) );
						$button_key = 'cta_' . $cta_count;
						$button_role = $cta_count === 0 ? 'cta_primary' : 'cta_secondary';
						$widgets[ $button_key ] = wpae_elementor_ir_node( $child_id . '-' . $button_key, $button_role, 'button', [ $content_ref ], [ 'color.primary', 'color.text', 'color.surface' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
					}
				}
					$ordered_widgets = [];
					foreach ( [ 'brand', 'eyebrow', 'title', 'body' ] as $widget_key ) {
						if ( isset( $widgets[ $widget_key ] ) ) {
							$ordered_widgets[] = $widgets[ $widget_key ];
						}
					}
					foreach ( $widgets as $widget_key => $widget ) {
						if ( str_starts_with( (string) $widget_key, 'cta_' ) ) {
							$ordered_widgets[] = $widget;
						}
					}
					$section_children[] = wpae_elementor_ir_node( $child_id, 'copy_group', 'container', [], $token_refs, $ordered_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
				if ( empty( $widgets ) ) {
					$warnings[] = $child_id . ':no_content_widgets';
				}
			} elseif ( $role === 'media' ) {
				$media_refs = array_values( array_filter( array_map( 'sanitize_key', (array) ( $child['media_refs'] ?? [] ) ) ) );
				if ( empty( $media_refs ) ) {
					$warnings[] = $child_id . ':missing_media_asset_omitted';
					continue;
				}
				$media_type = in_array( 'image', $allowed, true ) ? 'image' : ( $allowed[0] ?? 'image' );
				$image = wpae_elementor_ir_node( $child_id . '-asset', 'media_image', $media_type, [], $token_refs, [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'media_refs' => $media_refs, 'editable_fields' => [ 'media', 'alt' ] ] );
				$section_children[] = wpae_elementor_ir_node( $child_id, 'media_group', 'container', [], $token_refs, [ $image ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'media', 'alt' ] ] );
			} elseif ( $role === 'process_steps' ) {
				$step_children = [];
				$steps = array_values( array_filter( (array) ( $child['steps'] ?? [] ), static fn( $step ): bool => is_array( $step ) && sanitize_key( (string) ( $step['label_ref'] ?? '' ) ) !== '' ) );
				foreach ( $steps as $step_index => $step ) {
					$step_number = $step_index + 1;
					$marker_number = wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-number', 'process_number', 'heading', [], [ 'color.surface', 'type.body' ], [], [ 'literal_text' => sprintf( '%02d', $step_number ) ], [ 'strategy' => 'stack', 'editable_fields' => [] ] );
					$marker = wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-marker', 'process_marker', 'container', [], [ 'color.primary' ], [ $marker_number ], [ 'width_rem' => 3, 'width_mobile_rem' => 2.5 ], [ 'strategy' => 'stack', 'editable_fields' => [] ] );
					$marker_row_children = [ $marker ];
					if ( $step_index < count( $steps ) - 1 ) {
						$marker_row_children[] = wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-connector', 'connector', 'divider', [], [ 'color.border' ], [], [ 'reference' => 'process-card-v1' ], [ 'strategy' => 'stack' ] );
					}
					$card_children = [
						wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-marker-row', 'process_marker_row', 'container', [], [], $marker_row_children, [], [ 'strategy' => 'stack', 'editable_fields' => [] ] ),
						wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-title', 'process_card_title', 'heading', [ sanitize_key( (string) $step['label_ref'] ) ], [ 'color.text', 'type.display' ], [], [], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ),
					];
					$text_ref = sanitize_key( (string) ( $step['text_ref'] ?? '' ) );
					if ( $text_ref !== '' ) {
						$card_children[] = wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-copy', 'process_card_copy', 'text-editor', [ $text_ref ], [ 'color.muted', 'type.body' ], [], [], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					}
					$step_children[] = wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-card', 'process_card', 'container', [], [ 'color.surface', 'color.border', 'space.component' ], $card_children, [ 'reference' => 'process-card-v1' ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'process_steps', 'container', [], $token_refs, $step_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			} elseif ( $role === 'pricing_cards' ) {
				$intro_widgets = [];
				$card_refs = [];
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					$item_role = sanitize_key( (string) ( $item['role'] ?? '' ) );
					if ( $item_role === 'eyebrow' ) {
						$badge_label = wpae_elementor_ir_node( $child_id . '-intro-eyebrow-label', 'eyebrow', 'heading', [ $content_ref ], [ 'color.surface', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-eyebrow', 'pricing_badge', 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'title' ) {
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-title', 'title', 'heading', [ $content_ref ], [ 'color.text', 'type.display' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					} else {
						$card_refs[] = $content_ref;
					}
				}
				if ( ! empty( $intro_widgets ) ) {
					$section_children[] = wpae_elementor_ir_node( $child_id . '-intro', 'pricing_intro', 'container', [], [ 'color.text', 'color.muted', 'space.component' ], $intro_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
				}
				$card_children = [];
				foreach ( array_chunk( $card_refs, 4 ) as $card_index => $card_refs ) {
					$card_widgets = [];
					foreach ( $card_refs as $card_ref ) {
						$item = $content_map[ $card_ref ] ?? [];
						$item_role = sanitize_key( (string) ( $item['role'] ?? 'text' ) );
						$widget_type = str_starts_with( $item_role, 'cta' ) ? 'button' : ( in_array( $item_role, [ 'title', 'label' ], true ) ? 'heading' : 'text-editor' );
						$layout = [ 'min_width' => 0, 'max_width' => 100 ];
						if ( $widget_type === 'button' && empty( $item['url'] ) ) {
							$fallback_url = '';
							foreach ( $card_refs as $candidate_ref ) {
								$candidate_url = trim( (string) ( $content_map[ $candidate_ref ]['url'] ?? '' ) );
								if ( $candidate_url !== '' ) {
									$fallback_url = $candidate_url;
									break;
								}
							}
							if ( $fallback_url !== '' ) {
								$layout['fallback_url'] = $fallback_url;
							}
						}
						$card_widgets[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_index . '-' . count( $card_widgets ), $item_role, $widget_type, [ $card_ref ], [ $widget_type === 'button' ? 'color.primary' : 'color.text' ], [], $layout, [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
					}
					$card_children[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_index, 'pricing_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'pricing_cards', 'container', [], $token_refs, $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			}
		}
		$section_composition = (string) ( $section['composition'] ?? 'stacked_left' );
		if ( sanitize_key( (string) ( $section['role'] ?? '' ) ) === 'pricing' ) {
			$section_composition = 'stacked_left';
		}
		$section_layout = [ 'composition' => $section_composition, 'min_width' => 0, 'max_width' => 100 ];
		$surface_override = strtolower( trim( (string) ( $section['surface_override'] ?? '' ) ) );
		if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
			$section_layout['surface_override'] = $surface_override;
		}
		$nodes[] = wpae_elementor_ir_node( sanitize_key( (string) ( $section['id'] ?? 'section-' . $section_index ) ), sanitize_key( (string) ( $section['role'] ?? 'section' ) ), 'container', [], [ (string) ( $section['surface_token'] ?? 'color.page_bg' ), (string) ( $section['spacing_token'] ?? 'space.section' ) ], $section_children, $section_layout, [ 'strategy' => $plan['responsive']['mobile'] ?? 'stack' ] );
	}
	return [
		'schema' => WPAE_ELEMENTOR_IR_SCHEMA,
		'archetype' => sanitize_key( (string) ( $plan['archetype'] ?? 'unknown' ) ),
		'media_intent' => sanitize_key( (string) ( $plan['media_intent'] ?? 'unspecified' ) ),
		'nodes' => $nodes,
		'provenance' => [ 'plan_hash' => function_exists( 'wpae_design_plan_hash' ) ? wpae_design_plan_hash( $plan ) : '', 'source' => 'design-plan' ],
		'warnings' => array_values( array_unique( $warnings ) ),
	];
}

function wpae_elementor_ir_validate( array $ir ): array {
	$errors = [];
	$widgets = [];
	$walk = static function ( array $nodes ) use ( &$walk, &$errors, &$widgets ): void {
		foreach ( $nodes as $index => $node ) {
			if ( ! is_array( $node ) || trim( (string) ( $node['node_id'] ?? '' ) ) === '' || trim( (string) ( $node['role'] ?? '' ) ) === '' || trim( (string) ( $node['widget_type'] ?? '' ) ) === '' ) {
				$errors[] = 'node_' . (int) $index . '_shape';
				continue;
			}
			$widgets[] = sanitize_key( (string) $node['widget_type'] );
			if ( ( $node['role'] ?? '' ) === 'process_steps' && empty( $node['children'] ) ) {
				$errors[] = 'process_steps_empty';
			}
			$walk( (array) ( $node['children'] ?? [] ) );
		}
	};
	if ( ( $ir['schema'] ?? '' ) !== WPAE_ELEMENTOR_IR_SCHEMA ) {
		$errors[] = 'schema';
	}
	$walk( (array) ( $ir['nodes'] ?? [] ) );
	if ( ( $ir['archetype'] ?? '' ) === 'hero' ) {
		$media_nodes = [];
		$find_media = static function ( array $nodes ) use ( &$find_media, &$media_nodes ): void {
			foreach ( $nodes as $node ) {
				if ( is_array( $node ) && str_starts_with( (string) ( $node['role'] ?? '' ), 'media' ) && ( $node['widget_type'] ?? '' ) === 'image' ) {
					$media_nodes[] = $node;
				}
				if ( is_array( $node ) ) {
					$find_media( (array) ( $node['children'] ?? [] ) );
				}
			}
		};
		$find_media( (array) ( $ir['nodes'] ?? [] ) );
		if ( ( $ir['media_intent'] ?? 'unspecified' ) === 'forbidden' && ! empty( $media_nodes ) ) {
			$errors[] = 'forbidden_media_in_ir';
		}
		if ( ( $ir['media_intent'] ?? 'unspecified' ) === 'required' && empty( $media_nodes ) ) {
			$errors[] = 'required_media_asset_missing';
		}
	}
	$capabilities = function_exists( 'wpae_widget_capability_report' ) ? wpae_widget_capability_report( $widgets ) : [ 'ok' => true, 'unavailable' => [], 'downgrades' => [] ];
	if ( empty( $capabilities['ok'] ) ) {
		foreach ( (array) ( $capabilities['failures'] ?? [] ) as $failure ) {
			$errors[] = 'widget_capability:' . sanitize_key( (string) ( $failure['from'] ?? 'unknown' ) ) . ':' . sanitize_key( (string) ( $failure['reason'] ?? 'unavailable' ) );
		}
	}
	return [ 'ok' => empty( $errors ), 'errors' => array_values( $errors ), 'widgets' => array_values( array_unique( $widgets ) ), 'capabilities' => $capabilities ];
}

function wpae_elementor_ir_id( string $node_id, string $seed = 'wpae-ir-v2' ): string {
	return substr( hash( 'sha256', $seed . '|' . $node_id ), 0, 7 );
}

function wpae_elementor_ir_setting_value( string $token, array $tokens, array &$token_report ) {
	return function_exists( 'wpae_design_token_value' ) ? wpae_design_token_value( $token, $tokens, $token_report ) : null;
}

function wpae_elementor_ir_missing_cta_urls( array $elements, array $brief ): array {
	$expected = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) || ! str_starts_with( (string) ( $item['role'] ?? '' ), 'cta' ) ) {
			continue;
		}
		$url = trim( (string) ( $item['url'] ?? '' ) );
		if ( empty( $item['url_requested'] ) && $url === '' ) {
			continue;
		}
		$expected[] = [ 'text' => (string) ( $item['exact_text'] ?? '' ), 'url' => $url ];
	}
	$actual = [];
	$walk = static function ( array $nodes ) use ( &$walk, &$actual ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( ( $node['widgetType'] ?? '' ) === 'button' ) {
				$actual[] = [ 'text' => (string) ( $node['settings']['text'] ?? '' ), 'url' => (string) ( $node['settings']['link']['url'] ?? '' ) ];
			}
			$walk( (array) ( $node['elements'] ?? [] ) );
		}
	};
	$walk( $elements );
	$missing = [];
	foreach ( $expected as $item ) {
		$matched = false;
		foreach ( $actual as $index => $candidate ) {
			if ( $candidate === $item ) {
				unset( $actual[ $index ] );
				$matched = true;
				break;
			}
		}
		if ( ! $matched ) {
			$missing[] = [ 'reason' => 'explicit_cta_url_lost', 'text' => $item['text'], 'expected_url' => $item['url'] ];
		}
	}
	return array_values( $missing );
}

function wpae_elementor_ir_dimension_control( $value, string $fallback_unit, float $fallback_size, bool $linked = true ): array {
	$raw = trim( (string) $value );
	$unit = $fallback_unit;
	$size = $fallback_size;
	if ( preg_match( '/^(-?\d+(?:\.\d+)?)\s*(px|%|em|rem|vh|vw)?$/i', $raw, $matches ) ) {
		$size = max( 0, (float) $matches[1] );
		if ( ! empty( $matches[2] ) ) {
			$unit = strtolower( $matches[2] );
		}
	}
	return [
		'unit' => $unit,
		'size' => $size,
		'top' => (string) $size,
		'right' => (string) $size,
		'bottom' => (string) $size,
		'left' => (string) $size,
		'isLinked' => $linked,
		'sizes' => [],
	];
}

function wpae_elementor_ir_type_settings( array $type ): array {
	$dimension = static function ( $value, string $fallback_unit ): array {
		if ( preg_match( '/^(-?\d+(?:\.\d+)?)\s*(px|%|em|rem|vh|vw)?$/i', trim( (string) $value ), $matches ) ) {
			return [ 'unit' => strtolower( (string) ( ( $matches[2] ?? '' ) ?: $fallback_unit ) ), 'size' => (float) $matches[1], 'sizes' => [] ];
		}
		return [ 'unit' => $fallback_unit, 'size' => 1, 'sizes' => [] ];
	};
	$settings = [
		'typography_typography' => 'custom',
		'typography_font_family' => sanitize_text_field( (string) ( $type['font_family'] ?? 'inherit' ) ),
		'typography_font_size' => $dimension( $type['desktop'] ?? '1rem', 'rem' ),
		'typography_font_size_tablet' => $dimension( $type['tablet'] ?? $type['desktop'] ?? '1rem', 'rem' ),
		'typography_font_size_mobile' => $dimension( $type['mobile'] ?? $type['tablet'] ?? $type['desktop'] ?? '1rem', 'rem' ),
		'typography_font_weight' => (string) ( $type['weight'] ?? '400' ),
		'typography_line_height' => $dimension( $type['line_height'] ?? '1.5', 'em' ),
	];
	$settings['typography_line_height']['unit'] = 'em';
	return $settings;
}

function wpae_elementor_ir_compile_node( array $node, array $content_map, array $media_map, array $tokens, string $seed, array &$report ): array {
	$requested_widget_type = sanitize_key( (string) ( $node['widget_type'] ?? 'container' ) );
	$resolved = function_exists( 'wpae_widget_capability_resolve' ) ? wpae_widget_capability_resolve( $requested_widget_type, $node ) : [ 'ok' => false, 'widget_type' => null, 'from' => $requested_widget_type, 'downgraded' => false, 'reason' => 'capability_registry_unavailable' ];
	if ( empty( $resolved['ok'] ) ) {
		$report['errors'][] = [ 'node_id' => sanitize_key( (string) ( $node['node_id'] ?? '' ) ), 'widget_type' => $requested_widget_type, 'reason' => sanitize_key( (string) ( $resolved['reason'] ?? 'capability_unavailable' ) ), 'runtime_result' => $resolved['trace'][0]['runtime_result'] ?? 'unknown', 'trace' => $resolved['trace'] ?? [] ];
		return [];
	}
	$widget_type = sanitize_key( (string) ( $resolved['widget_type'] ?? 'container' ) );
	if ( ! empty( $resolved['downgraded'] ) ) {
		$report['downgrades'][] = $resolved;
	}
	$settings = [];
	$token_report = &$report['tokens'];
	$token_values = [];
	foreach ( (array) ( $node['token_refs'] ?? [] ) as $token_ref ) {
		$token_values[ $token_ref ] = wpae_elementor_ir_setting_value( (string) $token_ref, $tokens, $token_report );
	}
	$role = sanitize_key( (string) ( $node['role'] ?? '' ) );
	$settings['background_background'] = 'classic';
	if ( isset( $token_values['color.page_bg'] ) && is_string( $token_values['color.page_bg'] ) ) {
		$settings['background_color'] = $token_values['color.page_bg'];
	}
	if ( $widget_type === 'container' ) {
		$settings['container_type'] = 'flex';
		$settings['flex_direction'] = in_array( (string) ( $node['layout_constraints']['composition'] ?? '' ), [ 'split_60_40', 'split_50_50', 'split_40_60' ], true ) ? 'row' : 'column';
		$settings['flex_direction_mobile'] = 'column';
		$component_gap = $token_values['space.component'] ?? ( $tokens['native_tokens']['spacing']['gap'] ?? '1.5rem' );
		$gap_control = wpae_elementor_ir_dimension_control( $component_gap, 'rem', 1.5 );
		$settings['flex_gap'] = [ 'unit' => $gap_control['unit'], 'size' => $gap_control['size'], 'column' => (string) $gap_control['size'], 'row' => (string) $gap_control['size'], 'isLinked' => true ];
		$settings['flex_gap_mobile'] = $settings['flex_gap'];
		if ( in_array( $role, [ 'hero', 'process', 'pricing' ], true ) ) {
			$desktop_padding = wpae_elementor_ir_dimension_control( $token_values['space.section'] ?? ( $tokens['native_tokens']['spacing']['section_desktop'] ?? '4.5rem' ), 'rem', 4.5, false );
			$mobile_padding = wpae_elementor_ir_dimension_control( $tokens['native_tokens']['spacing']['section_mobile'] ?? '2rem', 'rem', 2, false );
			$desktop_padding['left'] = $desktop_padding['right'] = '2';
			$mobile_padding['left'] = $mobile_padding['right'] = '1';
			$settings['padding'] = $desktop_padding;
			$settings['padding_tablet'] = $desktop_padding;
			$settings['padding_mobile'] = $mobile_padding;
		}
		if ( isset( $token_values['color.surface'] ) ) {
			$settings['background_color'] = $token_values['color.surface'];
		}
		$surface_override = strtolower( trim( (string) ( $node['layout_constraints']['surface_override'] ?? '' ) ) );
		if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
			$settings['background_color'] = $surface_override;
			$report['tokens']['resolved'][] = [ 'token' => 'explicit.surface', 'value' => $surface_override, 'source' => 'prompt' ];
		}
		if ( $role === 'pricing_cards' ) {
			// Pricing cards are a row on wide viewports and a stack on mobile.
			// The child width contract below then supplies equal desktop columns.
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'row';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_wrap_tablet'] = 'wrap';
			$settings['flex_wrap_mobile'] = 'wrap';
		}
		if ( $role === 'process' ) {
			$settings['_css_classes'] = 'wpae-process-timeline wpae-process-timeline-horizontal';
			$settings['flex_direction'] = 'column';
			$settings['flex_direction_tablet'] = 'column';
			$settings['flex_direction_mobile'] = 'column';
		}
		if ( $role === 'process_steps' ) {
			$settings['_css_classes'] = 'wpae-process-items';
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'column';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_wrap_tablet'] = 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			$settings['flex_gap'] = [ 'column' => '0.5', 'row' => '0.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.5' ];
			$settings['flex_gap_tablet'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
			$settings['flex_gap_mobile'] = $settings['flex_gap_tablet'];
		}
		if ( $role === 'process_card' ) {
			$settings['_css_classes'] = 'wpae-process-content';
			$settings['flex_direction'] = 'column';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_gap'] = [ 'column' => '0.5', 'row' => '0.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.5' ];
			$settings['flex_gap_mobile'] = [ 'column' => '0.4', 'row' => '0.4', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.4' ];
			$settings['background_color'] = '#ffffff';
			$settings['border_border'] = 'solid';
			$settings['border_color'] = '#dbe3f0';
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
			$settings['border_radius'] = [ 'unit' => 'px', 'size' => 0.75, 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => true ];
			$settings['padding'] = [ 'unit' => 'rem', 'top' => '1', 'right' => '0.75', 'bottom' => '1.25', 'left' => '0.75', 'isLinked' => true ];
			$settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '1', 'right' => '0.75', 'bottom' => '1', 'left' => '0.75', 'isLinked' => true ];
		}
		if ( $role === 'process_marker_row' ) {
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_align_items'] = 'center';
			$settings['flex_justify_content'] = 'flex-start';
			$settings['flex_gap'] = [ 'column' => '0.5', 'row' => '0.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.5' ];
			$settings['flex_gap_mobile'] = [ 'column' => '0.4', 'row' => '0.4', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.4' ];
		}
		if ( $role === 'process_marker' ) {
			$settings['_css_classes'] = 'wpae-process-marker';
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_justify_content'] = 'center';
			$settings['flex_align_items'] = 'center';
			$settings['background_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['border_radius'] = [ 'unit' => 'px', 'size' => 999, 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'isLinked' => true ];
			$marker_width = (float) ( $node['layout_constraints']['width_rem'] ?? 3 );
			$marker_mobile_width = (float) ( $node['layout_constraints']['width_mobile_rem'] ?? 2.5 );
			$settings['width'] = [ 'unit' => 'rem', 'size' => $marker_width, 'sizes' => [] ];
			$settings['width_mobile'] = [ 'unit' => 'rem', 'size' => $marker_mobile_width, 'sizes' => [] ];
			$settings['min_height'] = [ 'unit' => 'rem', 'size' => $marker_width ];
			$settings['min_height_mobile'] = [ 'unit' => 'rem', 'size' => $marker_mobile_width ];
			$settings['_element_width'] = 'initial';
			$settings['_element_custom_width'] = $settings['width'];
			$settings['_element_custom_width_mobile'] = $settings['width_mobile'];
			$settings['_flex_size'] = 'custom';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
		}
		if ( $role === 'process_badge' ) {
			$settings['_css_classes'] = 'wpae-generated-badge';
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_align_items'] = 'center';
			$settings['background_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['border_border'] = 'solid';
			$settings['border_color'] = $settings['background_color'];
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( '999px', 'px', 999 );
			$settings['padding'] = [ 'unit' => 'rem', 'top' => '0.35', 'right' => '0.75', 'bottom' => '0.35', 'left' => '0.75', 'isLinked' => false, 'sizes' => [] ];
			$settings['align_self'] = 'flex-start';
			$settings['_element_width'] = 'initial';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
			$settings['custom_css'] = 'selector { width: fit-content; max-width: 100%; align-self: flex-start; flex: 0 0 auto; }';
		}
		if ( $role === 'pricing_card' ) {
			$settings['border_border'] = 'solid';
			$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( $token_values['radius.card'] ?? '0.5rem', 'rem', 0.5 );
			$settings['padding'] = wpae_elementor_ir_dimension_control( $token_values['space.component'] ?? '1.5rem', 'rem', 1.5, false );
			$settings['padding_tablet'] = $settings['padding'];
			$settings['padding_mobile'] = wpae_elementor_ir_dimension_control( $token_values['space.component'] ?? '1.25rem', 'rem', 1.25, false );
		}
		if ( in_array( $role, [ 'pricing_badge', 'hero_badge' ], true ) ) {
			$accent = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_align_items'] = 'center';
			$settings['background_color'] = $accent;
			$settings['border_border'] = 'solid';
			$settings['border_color'] = $accent;
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( '999px', 'px', 999 );
			$settings['padding'] = [ 'unit' => 'rem', 'top' => '0.35', 'right' => '0.75', 'bottom' => '0.35', 'left' => '0.75', 'isLinked' => false, 'sizes' => [] ];
			$settings['align_self'] = 'flex-start';
			$settings['align_self_tablet'] = 'flex-start';
			$settings['align_self_mobile'] = 'flex-start';
			$settings['_element_width'] = 'initial';
			$settings['_element_width_tablet'] = 'initial';
			$settings['_element_width_mobile'] = 'initial';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
			$settings['custom_css'] = 'selector { width: fit-content; max-width: 100%; align-self: flex-start; flex: 0 0 auto; }';
			$settings['_css_classes'] = 'wpae-generated-badge';
		}
	} elseif ( $widget_type === 'heading' ) {
		$item = $content_map[ sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) ) ] ?? [];
		$settings['title'] = (string) ( $item['exact_text'] ?? $node['layout_constraints']['literal_text'] ?? '' );
		$settings['header_size'] = in_array( $role, [ 'process_number', 'process_badge_label', 'brand', 'eyebrow' ], true ) ? 'h6' : ( $role === 'title' ? ( str_contains( (string) ( $node['node_id'] ?? '' ), '-card-' ) ? 'h3' : 'h1' ) : 'h3' );
		$settings['title_color'] = in_array( $role, [ 'process_number', 'process_badge_label' ], true ) ? (string) ( $token_values['color.surface'] ?? '#ffffff' ) : (string) ( $token_values[ $role === 'brand' ? 'color.muted' : 'color.text' ] ?? '#111827' );
		$type_token = is_array( $token_values['type.display'] ?? null ) && ! in_array( $role, [ 'eyebrow', 'brand', 'process_number', 'process_badge_label' ], true ) ? $token_values['type.display'] : ( $token_values['type.body'] ?? [] );
		if ( is_array( $type_token ) ) {
			$settings = array_merge( $settings, wpae_elementor_ir_type_settings( $type_token ) );
		}
		if ( $role === 'process_number' ) {
			$settings['_css_classes'] = 'wpae-process-marker-label';
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 0.875, 'sizes' => [] ];
			$settings['typography_font_weight'] = '600';
			$settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1, 'sizes' => [] ];
		} elseif ( $role === 'process_badge_label' ) {
			$settings['_css_classes'] = 'wpae-generated-badge-label';
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 0.75, 'sizes' => [] ];
			$settings['typography_font_weight'] = '600';
			$settings['typography_text_transform'] = 'uppercase';
			$settings['typography_letter_spacing'] = [ 'unit' => 'em', 'size' => 0.08, 'sizes' => [] ];
		} elseif ( $role === 'eyebrow_badge_label' || ( $role === 'eyebrow' && str_contains( (string) ( $node['node_id'] ?? '' ), 'pricing' ) ) ) {
			$accent = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['background_background'] = 'classic';
			$settings['background_color'] = $accent;
			$settings['title_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['border_border'] = 'solid';
			$settings['border_color'] = $accent;
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( '999px', 'px', 999 );
			$settings['_padding'] = [ 'unit' => 'rem', 'top' => '0.35', 'right' => '0.75', 'bottom' => '0.35', 'left' => '0.75', 'isLinked' => false, 'sizes' => [] ];
			$settings['align_self'] = 'flex-start';
			$settings['align_self_tablet'] = 'flex-start';
			$settings['align_self_mobile'] = 'flex-start';
			$settings['_element_width'] = 'initial';
			$settings['_element_width_tablet'] = 'initial';
			$settings['_element_width_mobile'] = 'initial';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 0.75, 'sizes' => [] ];
			$settings['typography_font_weight'] = '600';
			$settings['typography_text_transform'] = 'uppercase';
			$settings['typography_letter_spacing'] = [ 'unit' => 'em', 'size' => 0.08, 'sizes' => [] ];
			if ( $role === 'eyebrow_badge_label' ) {
				$settings['_css_classes'] = 'wpae-generated-badge-label';
			}
		}
	} elseif ( $widget_type === 'text-editor' ) {
		$values = [];
		foreach ( (array) ( $node['content_refs'] ?? [] ) as $content_ref ) {
			if ( isset( $content_map[ sanitize_key( (string) $content_ref ) ]['exact_text'] ) ) {
				$values[] = (string) $content_map[ sanitize_key( (string) $content_ref ) ]['exact_text'];
			}
		}
		$text = implode( "\n", $values );
		if ( $requested_widget_type === 'heading' && $text !== '' ) {
			$tag = in_array( $role, [ 'brand', 'eyebrow' ], true ) ? 'h6' : 'h1';
			$settings['editor'] = '<' . $tag . '>' . nl2br( htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ), false ) . '</' . $tag . '>';
			$heading_color = $role === 'brand' ? 'color.muted' : ( $role === 'eyebrow' ? 'color.primary' : 'color.text' );
			$settings['text_color'] = (string) ( $token_values[ $heading_color ] ?? '#111827' );
		} else {
			$settings['editor'] = $text;
			$settings['text_color'] = (string) ( $token_values['color.muted'] ?? '#6b7280' );
			if ( is_array( $token_values['type.body'] ?? null ) ) {
				$settings = array_merge( $settings, wpae_elementor_ir_type_settings( $token_values['type.body'] ) );
			}
		}
	} elseif ( $widget_type === 'button' ) {
		$item = $content_map[ sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) ) ] ?? [];
		$settings['text'] = (string) ( $item['exact_text'] ?? '' );
		$settings['link'] = [ 'url' => (string) ( $item['url'] ?? ( $node['layout_constraints']['fallback_url'] ?? '' ) ), 'is_external' => '', 'nofollow' => '' ];
		if ( $role === 'cta_secondary' ) {
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['button_text_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['border_border'] = 'solid';
			$settings['border_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
		} else {
			$settings['background_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['button_text_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
		}
	} elseif ( $widget_type === 'image' ) {
		$media = $media_map[ sanitize_key( (string) ( $node['media_refs'][0] ?? '' ) ) ] ?? [];
		$source_url = trim( (string) ( $media['source_url'] ?? '' ) );
		$source_parts = parse_url( $source_url );
		$valid_source = absint( $media['attachment_id'] ?? 0 ) > 0 || ( is_array( $source_parts ) && in_array( strtolower( (string) ( $source_parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) && trim( (string) ( $source_parts['host'] ?? '' ) ) !== '' );
		if ( ! $valid_source ) {
			$report['errors'][] = [ 'node_id' => sanitize_key( (string) ( $node['node_id'] ?? '' ) ), 'widget_type' => 'image', 'reason' => 'media_asset_missing_or_invalid' ];
			return [];
		}
		$settings['image'] = [ 'url' => $source_url, 'id' => absint( $media['attachment_id'] ?? 0 ), 'alt' => (string) ( $media['alt'] ?? '' ) ];
		$settings['image_size'] = 'full';
	} elseif ( $widget_type === 'icon-list' ) {
		$settings['icon_list'] = [];
		foreach ( (array) ( $node['content_refs'] ?? [] ) as $content_ref ) {
			$item = $content_map[ sanitize_key( (string) $content_ref ) ] ?? [];
			if ( trim( (string) ( $item['exact_text'] ?? '' ) ) !== '' ) {
				$settings['icon_list'][] = [ 'text' => (string) $item['exact_text'] ];
			}
		}
		$settings['text_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
	} elseif ( $widget_type === 'divider' ) {
		if ( ( $node['layout_constraints']['reference'] ?? '' ) === 'process-card-v1' ) {
			$settings['style'] = 'solid';
			$settings['weight'] = [ 'unit' => 'px', 'size' => 1, 'sizes' => [] ];
			$settings['width'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
			$settings['align'] = 'left';
			$settings['color'] = '#000000';
			$settings['gap'] = [ 'unit' => 'px', 'size' => 15, 'sizes' => [] ];
		} else {
			$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
		}
	}
	$compiled_children = [];
	foreach ( (array) ( $node['children'] ?? [] ) as $child ) {
		if ( is_array( $child ) ) {
			$compiled_children[] = wpae_elementor_ir_compile_node( $child, $content_map, $media_map, $tokens, $seed, $report );
		}
	}
	if ( $widget_type === 'container' ) {
		$composition_basis = [
			'split_60_40' => [ 60, 40 ],
			'split_50_50' => [ 50, 50 ],
			'split_40_60' => [ 40, 60 ],
			'three_cards' => [ 33.333, 33.333, 33.333 ],
		][ (string) ( $node['layout_constraints']['composition'] ?? '' ) ] ?? [];
		if ( $role === 'process_steps' && count( $compiled_children ) > 0 ) {
			$card_basis = count( $compiled_children ) === 4 ? 22 : min( 32, 92 / count( $compiled_children ) );
			$composition_basis = array_fill( 0, count( $compiled_children ), round( $card_basis, 3 ) );
		}
		if ( $role === 'pricing_cards' && count( $compiled_children ) === 3 ) {
			// Three percentage columns plus two native gaps otherwise wrap at common desktop widths.
			$composition_basis = [ 31.5, 31.5, 31.5 ];
		}
		$composition_matches_children = ! empty( $composition_basis ) && count( $composition_basis ) === count( $compiled_children );
		$default_child_basis = ( $settings['flex_direction'] ?? 'column' ) === 'column' ? 100 : ( count( $compiled_children ) > 0 ? 100 / count( $compiled_children ) : 100 );
		foreach ( $compiled_children as $child_index => &$compiled_child ) {
			if ( ! is_array( $compiled_child ) || ( $compiled_child['elType'] ?? '' ) !== 'container' || ! is_array( $compiled_child['settings'] ?? null ) ) {
				continue;
			}
			$child_role = (string) ( $node['children'][ $child_index ]['role'] ?? '' );
			if ( in_array( $child_role, [ 'pricing_badge', 'hero_badge', 'process_badge' ], true ) || $role === 'process_marker_row' ) {
				continue;
			}
			$basis = (float) ( $composition_matches_children ? $composition_basis[ $child_index ] : $default_child_basis );
			$tablet_is_stack = ( $settings['flex_direction_tablet'] ?? '' ) === 'column';
			$tablet_basis = $tablet_is_stack ? 100 : ( count( $compiled_children ) > 0 ? 100 / count( $compiled_children ) : 100 );
			$child_settings = &$compiled_child['settings'];
			// Elementor's native container controls use _element_custom_width and
			// _flex_*; generic flex_basis keys are persisted but ignored by the
			// rendered CSS. Keep one width contract per main axis and let native
			// flex-shrink account for the inter-column gap.
			foreach ( [ 'flex_basis', 'flex_basis_tablet', 'flex_basis_mobile' ] as $key ) {
				unset( $child_settings[ $key ] );
			}
			foreach ( [
				'width' => $basis,
				'width_tablet' => $tablet_basis,
				'width_mobile' => 100,
				'_element_custom_width' => $basis,
				'_element_custom_width_tablet' => $tablet_basis,
				'_element_custom_width_mobile' => 100,
			] as $key => $size ) {
				$child_settings[ $key ] = [ 'unit' => '%', 'size' => $size, 'sizes' => [] ];
			}
			$child_settings['_element_width'] = 'initial';
			$child_settings['_element_width_tablet'] = 'initial';
			$child_settings['_element_width_mobile'] = 'initial';
			$child_settings['_flex_size'] = 'custom';
			$child_settings['_flex_size_tablet'] = 'custom';
			$child_settings['_flex_size_mobile'] = 'custom';
			$child_settings['_flex_grow'] = 0;
			$child_settings['_flex_grow_tablet'] = 0;
			$child_settings['_flex_grow_mobile'] = 0;
			$child_settings['_flex_shrink'] = 1;
			$child_settings['_flex_shrink_tablet'] = 1;
			$child_settings['_flex_shrink_mobile'] = 1;
			$child_settings['flex_grow'] = 0;
			$child_settings['flex_grow_tablet'] = 0;
			$child_settings['flex_grow_mobile'] = 0;
			$child_settings['flex_shrink'] = 1;
			$child_settings['flex_shrink_tablet'] = 1;
			$child_settings['flex_shrink_mobile'] = 1;
			unset( $child_settings );
		}
		unset( $compiled_child );
	}
	$element = [
		'id' => wpae_elementor_ir_id( (string) ( $node['node_id'] ?? 'node' ), $seed ),
		'elType' => $widget_type === 'container' ? 'container' : 'widget',
		'settings' => $settings,
		'elements' => $compiled_children,
	];
	if ( $widget_type !== 'container' ) {
		$element['widgetType'] = $widget_type;
	}
	return $element;
}

function wpae_elementor_ir_compile( array $ir, array $brief, array $tokens = [], array $options = [] ): array {
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( is_array( $item ) && ! empty( $item['url_requested'] ) && trim( (string) ( $item['url'] ?? '' ) ) === '' ) {
			return [ 'ok' => false, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'errors' => [ 'explicit_cta_url_invalid:' . sanitize_key( (string) ( $item['id'] ?? 'cta' ) ) ] ];
		}
	}
	$validation = wpae_elementor_ir_validate( $ir );
	if ( empty( $validation['ok'] ) ) {
		return [ 'ok' => false, 'errors' => $validation['errors'], 'validation' => $validation ];
	}
	$content_map = wpae_elementor_ir_content_map( $brief );
	$media_map = [];
	foreach ( (array) ( $brief['media_references'] ?? [] ) as $media ) {
		if ( is_array( $media ) ) {
			$media_map[ sanitize_key( (string) ( $media['asset_id'] ?? '' ) ) ] = $media;
		}
	}
	$report = [ 'schema' => 'wpae-elementor-compile-report-v1', 'downgrades' => [], 'errors' => [], 'warnings' => (array) ( $ir['warnings'] ?? [] ), 'tokens' => [ 'resolved' => [], 'missing' => [], 'fallbacks' => [], 'collisions' => [] ], 'node_count' => 0 ];
	$report['contrast'] = function_exists( 'wpae_design_token_validate_contrast' ) ? wpae_design_token_validate_contrast( $tokens ) : [ 'ok' => true, 'errors' => [] ];
	if ( empty( $report['contrast']['ok'] ) && in_array( 'color.muted_on_color.page_bg', (array) ( $report['contrast']['errors'] ?? [] ), true ) ) {
		// Preserve the site's palette globally, but keep generated small text
		// readable when an inherited muted token is below the normal-text gate.
		if ( ! isset( $tokens['palette'] ) || ! is_array( $tokens['palette'] ) ) {
			$tokens['palette'] = [];
		}
		$tokens['palette']['muted'] = '#4b5563';
		$report['warnings'][] = 'color.muted:contrast_safe_fallback';
		$report['tokens']['fallbacks'][] = [ 'token' => 'color.muted', 'value' => '#4b5563', 'reason' => 'small_text_contrast' ];
		$report['contrast'] = wpae_design_token_validate_contrast( $tokens );
	}
	$seed = sanitize_key( (string) ( $options['id_seed'] ?? 'wpae-ir-v2' ) );
	$data = [];
	foreach ( (array) ( $ir['nodes'] ?? [] ) as $node ) {
		if ( is_array( $node ) ) {
			$data[] = wpae_elementor_ir_compile_node( $node, $content_map, $media_map, $tokens, $seed, $report );
		}
	}
	$counter = static function ( array $nodes ) use ( &$counter ): int {
		$count = count( $nodes );
		foreach ( $nodes as $node ) {
			$count += $counter( (array) ( $node['elements'] ?? [] ) );
		}
		return $count;
	};
	$report['node_count'] = $counter( $data );
	$missing_cta_urls = wpae_elementor_ir_missing_cta_urls( $data, $brief );
	if ( ! empty( $missing_cta_urls ) ) {
		$report['errors'] = array_merge( $report['errors'], $missing_cta_urls );
	}
	if ( ! empty( $report['errors'] ) ) {
		return [ 'ok' => false, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'errors' => $report['errors'], 'report' => $report, 'validation' => $validation ];
	}
	if ( empty( $report['contrast']['ok'] ) ) {
		return [ 'ok' => false, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'errors' => [ 'contrast:' . implode( ',', (array) ( $report['contrast']['errors'] ?? [] ) ) ], 'report' => $report, 'validation' => $validation ];
	}
	return [ 'ok' => true, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'elementor_data' => $data, 'report' => $report, 'validation' => $validation ];
}
