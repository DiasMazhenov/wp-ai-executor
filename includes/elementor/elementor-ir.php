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
						$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow', 'eyebrow', 'heading', [ $content_ref ], [ 'color.primary', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'body' || $item_role === 'text' ) {
						$widgets['body'] = wpae_elementor_ir_node( $child_id . '-body', 'body', 'text-editor', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( str_starts_with( $item_role, 'cta' ) || $item_role === 'button' ) {
						$button_key = 'cta_' . count( array_filter( array_keys( $widgets ), static fn( string $key ): bool => str_starts_with( $key, 'cta_' ) ) );
						$widgets[ $button_key ] = wpae_elementor_ir_node( $child_id . '-' . $button_key, 'cta', 'button', [ $content_ref ], [ 'color.primary', 'color.text' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
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
				$media_type = empty( $media_refs ) ? 'container' : ( in_array( 'image', $allowed, true ) ? 'image' : ( $allowed[0] ?? 'container' ) );
				if ( empty( $media_refs ) ) {
					$warnings[] = $child_id . ':missing_media_explicit_fallback';
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, empty( $media_refs ) ? 'media_fallback' : 'media', $media_type, [], $token_refs, [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'media_refs' => $media_refs, 'editable_fields' => [ 'media', 'alt' ] ] );
			} elseif ( $role === 'process_steps' ) {
				$step_children = [];
				foreach ( $content_refs as $content_ref_index => $content_ref ) {
					$step_children[] = wpae_elementor_ir_node( $child_id . '-step-' . count( $step_children ), 'step', 'text-editor', [ $content_ref ], [ 'color.text', 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					if ( $content_ref_index < count( $content_refs ) - 1 ) {
						$step_children[] = wpae_elementor_ir_node( $child_id . '-divider-' . count( $step_children ), 'connector', 'divider', [], [ 'color.border' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack' ] );
					}
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'process_steps', 'container', [], $token_refs, $step_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			} elseif ( $role === 'pricing_cards' ) {
				$card_children = [];
				foreach ( array_chunk( $content_refs, 4 ) as $card_index => $card_refs ) {
					$card_widgets = [];
					foreach ( $card_refs as $card_ref ) {
						$item = $content_map[ $card_ref ] ?? [];
						$item_role = sanitize_key( (string) ( $item['role'] ?? 'text' ) );
						$widget_type = str_starts_with( $item_role, 'cta' ) ? 'button' : ( in_array( $item_role, [ 'title', 'label' ], true ) ? 'heading' : 'text-editor' );
						$card_widgets[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_index . '-' . count( $card_widgets ), $item_role, $widget_type, [ $card_ref ], [ $widget_type === 'button' ? 'color.primary' : 'color.text' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
					}
					$card_children[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_index, 'pricing_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'pricing_cards', 'container', [], $token_refs, $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			}
		}
		$nodes[] = wpae_elementor_ir_node( sanitize_key( (string) ( $section['id'] ?? 'section-' . $section_index ) ), sanitize_key( (string) ( $section['role'] ?? 'section' ) ), 'container', [], [ (string) ( $section['surface_token'] ?? 'color.page_bg' ), (string) ( $section['spacing_token'] ?? 'space.section' ) ], $section_children, [ 'composition' => $section['composition'] ?? 'stacked_left', 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => $plan['responsive']['mobile'] ?? 'stack' ] );
	}
	return [
		'schema' => WPAE_ELEMENTOR_IR_SCHEMA,
		'archetype' => sanitize_key( (string) ( $plan['archetype'] ?? 'unknown' ) ),
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
			$walk( (array) ( $node['children'] ?? [] ) );
		}
	};
	if ( ( $ir['schema'] ?? '' ) !== WPAE_ELEMENTOR_IR_SCHEMA ) {
		$errors[] = 'schema';
	}
	$walk( (array) ( $ir['nodes'] ?? [] ) );
	$capabilities = function_exists( 'wpae_widget_capability_report' ) ? wpae_widget_capability_report( $widgets ) : [ 'ok' => true, 'unavailable' => [], 'downgrades' => [] ];
	if ( ! empty( $capabilities['unavailable'] ) ) {
		$errors[] = 'unavailable_widgets:' . implode( ',', $capabilities['unavailable'] );
	}
	return [ 'ok' => empty( $errors ), 'errors' => array_values( $errors ), 'widgets' => array_values( array_unique( $widgets ) ), 'capabilities' => $capabilities ];
}

function wpae_elementor_ir_id( string $node_id, string $seed = 'wpae-ir-v2' ): string {
	return substr( hash( 'sha256', $seed . '|' . $node_id ), 0, 7 );
}

function wpae_elementor_ir_setting_value( string $token, array $tokens, array &$token_report ) {
	return function_exists( 'wpae_design_token_value' ) ? wpae_design_token_value( $token, $tokens, $token_report ) : null;
}

function wpae_elementor_ir_compile_node( array $node, array $content_map, array $media_map, array $tokens, string $seed, array &$report ): array {
	$resolved = function_exists( 'wpae_widget_capability_resolve' ) ? wpae_widget_capability_resolve( (string) ( $node['widget_type'] ?? 'container' ) ) : [ 'widget_type' => sanitize_key( (string) ( $node['widget_type'] ?? 'container' ) ), 'downgraded' => false ];
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
		$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 1.5, 'column' => '1.5', 'row' => '1.5', 'isLinked' => true ];
		$settings['flex_gap_mobile'] = [ 'unit' => 'rem', 'size' => 1, 'column' => '1', 'row' => '1', 'isLinked' => true ];
		if ( isset( $token_values['color.surface'] ) ) {
			$settings['background_color'] = $token_values['color.surface'];
		}
		if ( $role === 'media_fallback' ) {
			$settings['_css_classes'] = 'wpae-ir-media-fallback';
			$settings['border_border'] = 'solid';
			$settings['border_color'] = $token_values['color.border'] ?? '#d1d5db';
			$settings['min_height'] = [ 'unit' => 'rem', 'size' => 12 ];
			$report['warnings'][] = (string) ( $node['node_id'] ?? '' ) . ':media_fallback';
		}
	} elseif ( $widget_type === 'heading' ) {
		$item = $content_map[ sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) ) ] ?? [];
		$settings['title'] = (string) ( $item['exact_text'] ?? '' );
		$settings['header_size'] = in_array( $role, [ 'brand', 'eyebrow' ], true ) ? 'h6' : 'h1';
		$settings['title_color'] = (string) ( $token_values[ $role === 'brand' ? 'color.muted' : 'color.text' ] ?? '#111827' );
	} elseif ( $widget_type === 'text-editor' ) {
		$values = [];
		foreach ( (array) ( $node['content_refs'] ?? [] ) as $content_ref ) {
			if ( isset( $content_map[ sanitize_key( (string) $content_ref ) ]['exact_text'] ) ) {
				$values[] = (string) $content_map[ sanitize_key( (string) $content_ref ) ]['exact_text'];
			}
		}
		$settings['editor'] = implode( "\n", $values );
		$settings['text_color'] = (string) ( $token_values['color.muted'] ?? '#6b7280' );
	} elseif ( $widget_type === 'button' ) {
		$item = $content_map[ sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) ) ] ?? [];
		$settings['text'] = (string) ( $item['exact_text'] ?? '' );
		$settings['link'] = [ 'url' => (string) ( $item['url'] ?? '' ), 'is_external' => '', 'nofollow' => '' ];
		$settings['background_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
		$settings['button_text_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
	} elseif ( $widget_type === 'image' ) {
		$media = $media_map[ sanitize_key( (string) ( $node['media_refs'][0] ?? '' ) ) ] ?? [];
		$settings['image'] = [ 'url' => (string) ( $media['source_url'] ?? '' ), 'id' => absint( $media['attachment_id'] ?? 0 ), 'alt' => (string) ( $media['alt'] ?? '' ) ];
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
		$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
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
		foreach ( $compiled_children as $child_index => &$compiled_child ) {
			if ( ! is_array( $compiled_child ) || ! is_array( $compiled_child['settings'] ?? null ) ) {
				continue;
			}
			$basis = (float) ( $composition_basis[ $child_index ] ?? ( count( $compiled_children ) > 0 ? 100 / count( $compiled_children ) : 100 ) );
			$compiled_child['settings']['flex_basis'] = [ 'unit' => '%', 'size' => $basis ];
			$compiled_child['settings']['flex_grow'] = 0;
			$compiled_child['settings']['flex_shrink'] = 1;
			$compiled_child['settings']['flex_basis_tablet'] = [ 'unit' => '%', 'size' => count( $compiled_children ) > 0 ? 100 / count( $compiled_children ) : 100 ];
			$compiled_child['settings']['flex_basis_mobile'] = [ 'unit' => '%', 'size' => 100 ];
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
	$report = [ 'schema' => 'wpae-elementor-compile-report-v1', 'downgrades' => [], 'warnings' => (array) ( $ir['warnings'] ?? [] ), 'tokens' => [ 'resolved' => [], 'missing' => [], 'fallbacks' => [], 'collisions' => [] ], 'node_count' => 0 ];
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
	if ( empty( $report['contrast']['ok'] ) ) {
		return [ 'ok' => false, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'errors' => [ 'contrast:' . implode( ',', (array) ( $report['contrast']['errors'] ?? [] ) ) ], 'report' => $report, 'validation' => $validation ];
	}
	return [ 'ok' => true, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'elementor_data' => $data, 'report' => $report, 'validation' => $validation ];
}
