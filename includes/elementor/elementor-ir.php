<?php

/**
 * ElementorIR v2: the only layer allowed to choose widgetType and IDs for the
 * new pipeline. Legacy provider trees remain outside this compiler boundary.
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/design/token-resolution.php';
require_once dirname( __DIR__ ) . '/llm/design-plan.php';
require_once __DIR__ . '/native-roundtrip.php';

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

function wpae_elementor_ir_card_nodes( string $role, string $node_id, array $items, array $tokens ): array {
	$cards = [];
	$roles = [
		'service_cards' => [ 'title_ref' => [ 'service_title', 'heading' ], 'body_ref' => [ 'service_body', 'text-editor' ], 'cta_ref' => [ 'service_cta', 'button' ] ],
		'team_cards' => [ 'name_ref' => [ 'team_name', 'heading' ], 'position_ref' => [ 'team_position', 'text-editor' ], 'bio_ref' => [ 'team_bio', 'text-editor' ], 'action_ref' => [ 'team_action', 'button' ] ],
		'testimonial_cards' => [ 'quote_ref' => [ 'testimonial_quote', 'text-editor' ], 'author_ref' => [ 'testimonial_author', 'heading' ], 'meta_ref' => [ 'testimonial_meta', 'text-editor' ], 'rating_ref' => [ 'testimonial_rating', 'text-editor' ], 'action_ref' => [ 'testimonial_action', 'button' ] ],
	];
	$field_map = $roles[ $role ] ?? [];
	foreach ( array_values( $items ) as $index => $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$group_id = sanitize_key( (string) ( $item['group_id'] ?? $role . '_' . ( $index + 1 ) ) );
		$body_children = [];
		$content_children = [];
		$action_children = [];
		$card_style = [];
		foreach ( [ 'border_radius', 'border_radius_mobile' ] as $style_key ) {
			$style_value = $item['layout_constraints'][ $style_key ] ?? null;
			if ( is_scalar( $style_value ) && preg_match( '/^\d+(?:\.\d+)?\s*(?:px|rem|em)?$/i', trim( (string) $style_value ) ) ) {
				$card_style[ $style_key ] = trim( (string) $style_value );
			}
		}
		if ( ! empty( $item['media_ref'] ) ) {
			$image_role = $role === 'service_cards' ? 'service_image' : ( $role === 'testimonial_cards' ? 'testimonial_photo' : 'team_photo' );
			$image_constraints = [ 'min_width' => 0, 'max_width' => 100 ];
			if ( $role === 'service_cards' && ( ! empty( $card_style['border_radius'] ) || ! empty( $card_style['border_radius_mobile'] ) ) ) {
				$image_constraints['card_border_radius'] = $card_style['border_radius'] ?? '16px';
				$image_constraints['card_border_radius_mobile'] = $card_style['border_radius_mobile'] ?? ( $card_style['border_radius'] ?? '16px' );
			}
			$body_children[] = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-photo', $image_role, 'image', [], [ 'radius.card' ], [], $image_constraints, [ 'strategy' => 'stack', 'media_refs' => [ sanitize_key( (string) $item['media_ref'] ) ], 'editable_fields' => [ 'media', 'alt' ] ] );
		}
		foreach ( $field_map as $field => [ $field_role, $widget_type ] ) {
			$ref = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
			if ( $ref === '' ) {
				continue;
			}
			$field_node = wpae_elementor_ir_node(
				$node_id . '-' . $group_id . '-' . $field_role,
				$field_role,
				$widget_type,
				[ $ref ],
				$tokens,
				[],
				[ 'min_width' => 0, 'max_width' => 100 ],
				[ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ]
			);
			if ( in_array( $field_role, [ 'team_action', 'testimonial_action', 'service_cta' ], true ) ) {
				$action_children[] = $field_node;
			} elseif ( $role === 'service_cards' ) {
				$content_children[] = $field_node;
			} else {
				$body_children[] = $field_node;
			}
		}
		if ( $role === 'service_cards' ) {
			$body_children[] = wpae_elementor_ir_node(
				$node_id . '-' . $group_id . '-content',
				'service_content',
				'container',
				[],
				[ 'color.surface', 'color.text', 'color.muted', 'space.component' ],
				$content_children,
				[ 'min_width' => 0, 'max_width' => 100 ],
				[ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ]
			);
		}
		$card_children = [ wpae_elementor_ir_node( $node_id . '-' . $group_id . '-body', 'card_body', 'container', [], [ 'space.component' ], $body_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] ) ];
		if ( $action_children ) {
			$card_children[] = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-actions', 'card_actions', 'container', [], [ 'space.component' ], $action_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
		}
		$card_constraints = array_merge( [ 'min_width' => 0, 'max_width' => 100, 'item_id' => $group_id ], $card_style );
		$cards[] = wpae_elementor_ir_node(
			$node_id . '-' . $group_id . '-card',
			substr( $role, 0, -1 ),
			'container',
			[],
			[ 'color.surface', 'color.border', 'radius.card', 'space.component' ],
			$card_children,
			$card_constraints,
			[ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ]
		);
	}
	return $cards;
}

function wpae_elementor_ir_services_recipe_nodes( string $role, string $node_id, array $items, array $tokens, string $lead_group_id = '' ): array {
	$make_copy = static function ( string $id, array $item, string $copy_role ) use ( $tokens ): array {
		$body_children = [];
		$action_children = [];
		foreach ( [ 'title_ref' => [ 'services_recipe_title', 'heading' ], 'body_ref' => [ 'service_body', 'text-editor' ], 'cta_ref' => [ 'service_cta', 'button' ] ] as $field => [ $field_role, $widget ] ) {
			$ref = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
			if ( $ref === '' ) {
				continue;
			}
			$field_node = wpae_elementor_ir_node( $id . '-' . $field_role, $field_role, $widget, [ $ref ], $tokens, [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			$field_node['provenance']['item_id'] = sanitize_key( (string) ( $item['group_id'] ?? '' ) );
			if ( $field_role === 'service_cta' ) { $action_children[] = $field_node; } else { $body_children[] = $field_node; }
		}
		$body = wpae_elementor_ir_node( $id . '-body', 'card_body', 'container', [], $tokens, $body_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
		$body['provenance']['item_id'] = sanitize_key( (string) ( $item['group_id'] ?? '' ) );
		$body['provenance']['composition_role'] = sanitize_key( $copy_role );
		$actions = $action_children ? wpae_elementor_ir_node( $id . '-actions', 'card_actions', 'container', [], $tokens, $action_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] ) : null;
		if ( is_array( $actions ) ) { $actions['provenance']['item_id'] = sanitize_key( (string) ( $item['group_id'] ?? '' ) ); }
		return [ 'body' => $body, 'actions' => $actions ];
	};
	$make_image = static function ( string $id, array $item, string $image_role ): array {
		$media_ref = sanitize_key( (string) ( $item['media_ref'] ?? '' ) );
		return wpae_elementor_ir_node( $id, $image_role, 'image', [], [ 'radius.card' ], [], [ 'min_width' => 0, 'max_width' => 100, 'aspect_ratio' => '4:3' ], [ 'strategy' => 'stack', 'media_refs' => $media_ref !== '' ? [ $media_ref ] : [], 'editable_fields' => [ 'media', 'alt' ] ] );
	};
	if ( $role === 'services_photo_grid' ) {
		$cards = [];
		foreach ( array_values( $items ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$group_id = sanitize_key( (string) ( $item['group_id'] ?? 'service_' . ( $index + 1 ) ) );
			$card_id = $node_id . '-' . $group_id . '-photo-card';
			$copy = $make_copy( $card_id . '-panel', $item, 'services_photo_panel' );
			$panel = $copy['body']; $panel['role'] = 'services_photo_panel';
			$body = wpae_elementor_ir_node( $card_id . '-body', 'card_body', 'container', [], $tokens, [ $make_image( $card_id . '-image', $item, 'services_photo_image' ), $panel ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] );
			$card_children = [ $body ];
			if ( is_array( $copy['actions'] ) ) { $card_children[] = $copy['actions']; }
			$cards[] = wpae_elementor_ir_node( $card_id, 'services_photo_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_children, [ 'min_width' => 0, 'max_width' => 100, 'item_id' => $group_id, 'border_radius' => '16px', 'border_radius_mobile' => '16px' ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] );
		}
		return [ wpae_elementor_ir_node( $node_id, 'services_photo_grid', 'container', [], $tokens, $cards, [ 'min_width' => 0, 'max_width' => 100, 'grid_gap_desktop' => 24, 'grid_gap_tablet' => 20, 'grid_gap_mobile' => 16 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] ) ];
	}
	if ( $role === 'services_split_editorial' ) {
		$lead = null;
		$secondary = [];
		foreach ( array_values( $items ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( (string) ( $item['group_id'] ?? '' ) === $lead_group_id ) {
				$lead = $item;
			} else {
				$secondary[] = $item;
			}
		}
		$nodes = [];
		if ( is_array( $lead ) ) {
			$lead_id = $node_id . '-' . sanitize_key( $lead_group_id ) . '-lead';
			$copy_parts = $make_copy( $lead_id . '-copy', $lead, 'services_split_copy' );
			$copy_children = [ $copy_parts['body'] ];
			if ( is_array( $copy_parts['actions'] ) ) { $copy_children[] = $copy_parts['actions']; }
			$copy = wpae_elementor_ir_node( $lead_id . '-copy', 'services_split_copy', 'container', [], $tokens, $copy_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			$copy['layout_constraints']['desktop_width'] = 52;
			$image = $make_image( $lead_id . '-image', $lead, 'services_split_image' );
			$image_panel = wpae_elementor_ir_node( $lead_id . '-image-panel', 'services_split_image_panel', 'container', [], [ 'color.surface', 'radius.card' ], [ $image ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'media', 'alt' ] ] );
			$nodes[] = wpae_elementor_ir_node( $lead_id, 'services_split_lead', 'container', [], $tokens, [ $copy, $image_panel ], [ 'min_width' => 0, 'max_width' => 100, 'composition' => 'split_60_40', 'copy_width' => 52, 'media_width' => 44, 'media_side' => 'right', 'item_id' => $lead_group_id ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] );
		}
		if ( ! empty( $secondary ) ) {
			$rows = [];
			foreach ( $secondary as $index => $item ) {
				$group_id = sanitize_key( (string) ( $item['group_id'] ?? 'service_' . ( $index + 1 ) ) );
				$copy_parts = $make_copy( $node_id . '-' . $group_id . '-editorial-copy', $item, 'services_editorial_copy' );
				$copy_children = [ $copy_parts['body'] ];
				if ( is_array( $copy_parts['actions'] ) ) { $copy_children[] = $copy_parts['actions']; }
				$editorial_copy = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-editorial-copy', 'services_editorial_copy', 'container', [], $tokens, $copy_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
				$rows[] = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-editorial-row', 'services_editorial_row', 'container', [], $tokens, [ $editorial_copy ], [ 'min_width' => 0, 'max_width' => 100, 'item_id' => $group_id ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
				if ( $index < count( $secondary ) - 1 ) {
					$rows[] = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-editorial-divider', 'services_editorial_divider', 'divider', [], [ 'color.border' ], [], [ 'reference' => 'services-editorial-row-v1' ], [ 'strategy' => 'stack' ] );
				}
			}
			$nodes[] = wpae_elementor_ir_node( $node_id . '-secondary-editorial-rows', 'services_editorial_rows', 'container', [], $tokens, $rows, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
		}
		return [ wpae_elementor_ir_node( $node_id, 'services_split_editorial', 'container', [], $tokens, $nodes, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] ) ];
	}
	if ( $role === 'services_text_icon_list' ) {
		$rows = [];
		foreach ( array_values( $items ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$group_id = sanitize_key( (string) ( $item['group_id'] ?? 'service_' . ( $index + 1 ) ) );
			$copy_parts = $make_copy( $node_id . '-' . $group_id . '-text-copy', $item, 'services_text_icon_copy' );
			$copy_children = [ $copy_parts['body'] ];
			if ( is_array( $copy_parts['actions'] ) ) { $copy_children[] = $copy_parts['actions']; }
			$copy = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-text-copy', 'services_text_icon_copy', 'container', [], $tokens, $copy_children, [ 'min_width' => 0, 'max_width' => 100, 'list_copy_width' => 90 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			$row = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-text-icon-row', 'services_text_icon_row', 'container', [], $tokens, [ wpae_elementor_ir_node( $node_id . '-' . $group_id . '-icon', 'services_list_icon', 'icon', [], [ 'color.primary', 'color.surface' ], [], [ 'icon_name' => 'check-circle', 'fixed_width' => 44 ], [ 'strategy' => 'stack', 'editable_fields' => [] ] ), $copy ], [ 'min_width' => 0, 'max_width' => 100, 'item_id' => $group_id ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			$rows[] = $row;
			if ( $index < count( $items ) - 1 ) {
				$rows[] = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-text-divider', 'services_text_icon_divider', 'divider', [], [ 'color.border' ], [], [ 'reference' => 'services-text-icon-list-v1' ], [ 'strategy' => 'stack' ] );
			}
		}
		return [ wpae_elementor_ir_node( $node_id, 'services_text_icon_list', 'container', [], $tokens, $rows, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] ) ];
	}
	if ( $role === 'services_icon_cards' ) {
		$cards = [];
		foreach ( array_values( $items ) as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$group_id = sanitize_key( (string) ( $item['group_id'] ?? 'service_' . ( $index + 1 ) ) );
			$card_id = $node_id . '-' . $group_id . '-icon-card';
			$copy = $make_copy( $card_id . '-copy', $item, 'services_icon_copy' );
			$icon = wpae_elementor_ir_node( $card_id . '-icon', 'services_list_icon', 'icon', [], [ 'color.primary', 'color.surface' ], [], [ 'icon_name' => 'check-circle', 'fixed_width' => 44 ], [ 'strategy' => 'stack', 'editable_fields' => [] ] );
			$body_children = array_merge( [ $icon ], (array) ( $copy['body']['children'] ?? [] ) );
			$body = wpae_elementor_ir_node( $card_id . '-body', 'card_body', 'container', [], $tokens, $body_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			$body['provenance']['item_id'] = $group_id;
			$body['provenance']['composition_role'] = 'services_icon_copy';
			$card_children = [ $body ];
			if ( is_array( $copy['actions'] ) ) { $card_children[] = $copy['actions']; }
			$card = wpae_elementor_ir_node( $card_id, 'services_icon_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_children, [ 'min_width' => 0, 'max_width' => 100, 'item_id' => $group_id ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			$cards[] = $card;
		}
		return [ wpae_elementor_ir_node( $node_id, 'services_icon_cards', 'container', [], $tokens, $cards, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] ) ];
	}
	return [];
}

/** Build semantically owned project rows/cards; layout is selected by the frozen record. */
function wpae_elementor_ir_portfolio_project_nodes( string $layout, string $node_id, array $items, array $content_map ): array {
	$nodes = [];
	$layout = $layout === 'editorial_rows' ? 'editorial_rows' : 'project_cards';
	foreach ( array_values( $items ) as $index => $item ) {
		if ( ! is_array( $item ) ) { continue; }
		$group_id = sanitize_key( (string) ( $item['group_id'] ?? '' ) );
		if ( $group_id === '' ) { continue; }
		$owner = static function ( array $node ) use ( $group_id, $item ): array {
			$node['layout_constraints']['item_id'] = $group_id;
			$node['provenance'] = array_merge( (array) ( $node['provenance'] ?? [] ), [ 'source' => 'brief', 'group_id' => $group_id, 'item_id' => $group_id, 'source_spans' => (array) ( $item['provenance']['source_spans'] ?? [] ) ] );
			return $node;
		};
		$field = static function ( string $field, string $role, string $widget, array $tokens ) use ( $item, $content_map, $node_id, $group_id, $index, $owner ): ?array {
			$ref = sanitize_key( (string) ( $item[$field] ?? '' ) );
			if ( $ref === '' || ! isset( $content_map[$ref] ) ) { return null; }
			$node = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-' . $role . '-' . $index, $role, $widget, [ $ref ], $tokens, [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			$node['provenance']['content_ref'] = $ref;
			return $owner( $node );
		};
		$title = $field( 'title_ref', 'portfolio_project_title', 'heading', [ 'color.text', 'type.body' ] );
		$category = $field( 'category_ref', 'portfolio_project_category', 'text-editor', [ 'color.primary', 'type.body' ] );
		$description = $field( 'description_ref', 'portfolio_project_description', 'text-editor', [ 'color.muted', 'type.body' ] );
		$copy_children = array_values( array_filter( [ $title, $category, $description ] ) );
		$copy = $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-copy', 'portfolio_project_content', 'container', [], [ 'space.component' ], $copy_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ) );
		$asset_id = sanitize_key( (string) ( $item['media_ref'] ?? '' ) );
		$image = null;
		if ( $asset_id !== '' ) {
			$image = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-image', 'portfolio_project_image', 'image', [], [ 'radius.card' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'media_refs' => [ $asset_id ], 'editable_fields' => [ 'media', 'alt' ] ] );
			$image['provenance'] = array_merge( (array) $image['provenance'], [ 'source' => 'brief', 'group_id' => $group_id, 'item_id' => $group_id, 'asset_id' => $asset_id ] );
			$image = $owner( $image );
		}
		$action = $field( 'action_label_ref', 'portfolio_project_action', 'button', [ 'color.primary', 'color.surface', 'color.text', 'color.hover', 'color.focus' ] );
		$actions = $action ? $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-actions', 'card_actions', 'container', [], [ 'space.component' ], [ $action ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] ) ) : null;
		if ( $layout === 'editorial_rows' ) {
			$identity_children = $image ? [ $image ] : [];
			$identity = $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-identity', 'entity_identity', 'container', [], [], $identity_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'media', 'alt' ] ] ) );
			$copy_children = $copy['children'];
			if ( $actions ) { $copy_children[] = $actions; }
			$copy['children'] = $copy_children;
			$entity_copy = $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-entity-copy', 'entity_copy', 'container', [], [ 'space.component' ], [ $copy ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] ) );
			$nodes[] = $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-row', 'portfolio_project_row', 'container', [], [ 'color.border', 'space.component' ], [ $identity, $entity_copy ], [ 'min_width' => 0, 'max_width' => 100, 'entity_layout' => 'editorial_rows' ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] ) );
			continue;
		}
		$body_children = array_values( array_filter( [ $image, $copy ] ) );
		$body = $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-body', 'card_body', 'container', [], [ 'space.component' ], $body_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'media', 'alt' ] ] ) );
		$card_children = [ $body ];
		if ( $actions ) { $card_children[] = $actions; }
		$nodes[] = $owner( wpae_elementor_ir_node( $node_id . '-' . $group_id . '-card', 'portfolio_project_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] ) );
	}
	return $nodes;
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
		$section_header = is_array( $section['section_header'] ?? null ) ? $section['section_header'] : [];
		$badge_ref = sanitize_key( (string) ( $section['badge_content_ref'] ?? '' ) );
		$badge_is_in_intro = (bool) array_filter( (array) ( $section['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && in_array( (string) ( $child['role'] ?? '' ), [ 'copy_group', 'cta_copy_group' ], true ) && in_array( $badge_ref, array_map( 'sanitize_key', (array) ( $child['content_refs'] ?? [] ) ), true ) );
		if ( sanitize_key( (string) ( $section['role'] ?? '' ) ) === 'process' && $badge_ref !== '' && ! $badge_is_in_intro && isset( $content_map[ $badge_ref ] ) ) {
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
			if ( in_array( $role, [ 'copy_group', 'cta_copy_group' ], true ) ) {
				$copy_container_alignment = sanitize_key( (string) ( $child['layout_constraints']['container_align'] ?? ( $plan['visual_policy']['intro']['container_align'] ?? ( $child['layout_constraints']['text_align'] ?? 'start' ) ) ) );
				if ( ! in_array( $copy_container_alignment, [ 'start', 'center', 'end', 'left', 'right' ], true ) ) { $copy_container_alignment = 'start'; }
				$copy_container_alignment = [ 'left' => 'start', 'right' => 'end' ][ $copy_container_alignment ] ?? $copy_container_alignment;
				$widgets = [];
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					$item_role = sanitize_key( (string) ( $item['role'] ?? '' ) );
					if ( $item_role === 'brand' ) {
						$widgets['brand'] = wpae_elementor_ir_node( $child_id . '-brand', 'brand', 'heading', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'title' ) {
						$title_role = $role === 'cta_copy_group' ? 'cta_section_title' : ( str_starts_with( (string) ( $plan['recipe_id'] ?? '' ), 'services.' ) ? 'services_section_title' : 'title' );
						$widgets['title'] = wpae_elementor_ir_node( $child_id . '-title', $title_role, 'heading', [ $content_ref ], [ 'color.text', 'type.display', 'type.section_title', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100, 'heading_level' => ( $section['role'] ?? '' ) === 'hero' ? 'h1' : 'h2' ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'eyebrow' ) {
						if ( ( $child['layout_constraints']['eyebrow_presentation'] ?? '' ) === 'pill' ) {
							$is_services_recipe = str_starts_with( (string) ( $plan['recipe_id'] ?? '' ), 'services.' );
							$badge_label_role = $is_services_recipe ? 'services_badge_label' : 'eyebrow_badge_label';
							$badge_role = $is_services_recipe ? 'services_badge' : 'hero_badge';
							$badge_label = wpae_elementor_ir_node( $child_id . '-eyebrow-badge-label', $badge_label_role, 'heading', [ $content_ref ], [ 'color.text', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow-badge', $badge_role, 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100, 'container_align' => $copy_container_alignment ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						} else {
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow', 'eyebrow', 'heading', [ $content_ref ], [ 'color.primary', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						}
					} elseif ( $item_role === 'label' ) {
						if ( ( $child['layout_constraints']['eyebrow_presentation'] ?? '' ) === 'pill' ) {
							$is_services_recipe = str_starts_with( (string) ( $plan['recipe_id'] ?? '' ), 'services.' );
							$badge_label_role = $is_services_recipe ? 'services_badge_label' : 'eyebrow_badge_label';
							$badge_role = $is_services_recipe ? 'services_badge' : ( ( $section['role'] ?? '' ) === 'pricing' ? 'pricing_badge' : 'hero_badge' );
							$badge_label = wpae_elementor_ir_node( $child_id . '-label-badge-label', $badge_label_role, 'heading', [ $content_ref ], [ 'color.text', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-label-badge', $badge_role, 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100, 'container_align' => $copy_container_alignment ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						} else {
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-label', 'eyebrow', 'heading', [ $content_ref ], [ 'color.primary', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						}
					} elseif ( $item_role === 'body' || $item_role === 'text' ) {
						$body_role = $role === 'cta_copy_group' ? 'cta_description' : 'body';
						$widgets['body'] = wpae_elementor_ir_node( $child_id . '-body', $body_role, 'text-editor', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( str_starts_with( $item_role, 'cta' ) || $item_role === 'button' ) {
						$cta_count = count( array_filter( array_keys( $widgets ), static fn( string $key ): bool => str_starts_with( $key, 'cta_' ) ) );
						$button_key = 'cta_' . $cta_count;
						$button_role = $cta_count === 0 ? 'cta_primary' : 'cta_secondary';
						$widgets[ $button_key ] = wpae_elementor_ir_node( $child_id . '-' . $button_key, $button_role, 'button', [ $content_ref ], [ 'color.primary', 'color.text', 'color.surface', 'color.hover', 'color.focus' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
					}
				}
				if ( (int) ( $section_header['version'] ?? 0 ) === 1 && (int) ( $child['layout_constraints']['section_header_contract_version'] ?? 0 ) === 1 ) {
					$header_eyebrow = (array) ( $section_header['eyebrow'] ?? [] );
					if ( empty( $widgets['eyebrow'] ) && trim( (string) ( $header_eyebrow['text'] ?? '' ) ) !== '' ) {
						$is_services_recipe = str_starts_with( (string) ( $plan['recipe_id'] ?? '' ), 'services.' );
						$badge_label_role = $is_services_recipe ? 'services_badge_label' : 'eyebrow_badge_label';
						$badge_role = $is_services_recipe ? 'services_badge' : ( ( $section['role'] ?? '' ) === 'pricing' ? 'pricing_badge' : 'hero_badge' );
						$badge_label = wpae_elementor_ir_node( $child_id . '-default-eyebrow-label', $badge_label_role, 'heading', [], [ 'color.text', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100, 'literal_text' => (string) $header_eyebrow['text'] ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [] ] );
						$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-default-eyebrow-badge', $badge_role, 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100, 'container_align' => $copy_container_alignment ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [] ] );
					}
					$header_title = (array) ( $section_header['title'] ?? [] );
					if ( empty( $widgets['title'] ) && trim( (string) ( $header_title['text'] ?? '' ) ) !== '' ) {
						$title_role = $role === 'cta_copy_group' ? 'cta_section_title' : ( str_starts_with( (string) ( $plan['recipe_id'] ?? '' ), 'services.' ) ? 'services_section_title' : 'title' );
						$widgets['title'] = wpae_elementor_ir_node( $child_id . '-default-title', $title_role, 'heading', [], [ 'color.text', 'type.display', 'type.section_title', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100, 'literal_text' => (string) $header_title['text'], 'heading_level' => (string) ( $section_header['heading_level'] ?? ( ( $section['role'] ?? '' ) === 'hero' ? 'h1' : 'h2' ) ) ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [] ] );
					}
				}
					$ordered_widgets = [];
					foreach ( [ 'brand', 'eyebrow', 'title', 'body' ] as $widget_key ) {
						if ( isset( $widgets[ $widget_key ] ) ) {
							$ordered_widgets[] = $widgets[ $widget_key ];
						}
					}
					$cta_widgets = [];
					foreach ( $widgets as $widget_key => $widget ) {
						if ( str_starts_with( (string) $widget_key, 'cta_' ) ) {
							$cta_widgets[] = $widget;
						}
					}
					if ( $role === 'cta_copy_group' && ! empty( $cta_widgets ) ) {
						$actions_layout = [ 'min_width' => 0, 'max_width' => 100, 'actions_direction' => (array) ( $child['layout_constraints']['cta_actions_direction'] ?? [] ), 'actions_align' => (string) ( $child['layout_constraints']['cta_actions_align'] ?? 'start' ), 'actions_gap' => (array) ( $child['layout_constraints']['cta_actions_gap'] ?? [] ), 'copy_actions_gap' => (array) ( $child['layout_constraints']['cta_copy_actions_gap'] ?? [] ) ];
						$ordered_widgets[] = wpae_elementor_ir_node( $child_id . '-actions', 'cta_actions', 'container', [], [ 'space.component', 'color.primary', 'color.surface', 'color.text', 'color.hover', 'color.focus' ], $cta_widgets, $actions_layout, [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
					} else {
						$ordered_widgets = array_merge( $ordered_widgets, $cta_widgets );
					}
					$text_alignment = sanitize_key( (string) ( $child['layout_constraints']['text_align'] ?? '' ) );
					if ( in_array( $text_alignment, [ 'left', 'center', 'right' ], true ) ) {
						foreach ( $ordered_widgets as &$ordered_widget ) {
							if ( is_array( $ordered_widget ) && in_array( (string) ( $ordered_widget['widget_type'] ?? '' ), [ 'heading', 'text-editor' ], true ) ) {
								$ordered_widget['layout_constraints']['text_align'] = $text_alignment;
							}
						}
						unset( $ordered_widget );
					}
					$copy_constraints = array_merge( [ 'min_width' => 0, 'max_width' => 100 ], (array) ( $child['layout_constraints'] ?? [] ) );
					$copy_constraints['container_align'] = $copy_container_alignment;
				$copy_constraints['reading_measure'] = true;
				$section_children[] = wpae_elementor_ir_node( $child_id, $role, 'container', [], $token_refs, $ordered_widgets, $copy_constraints, [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
				if ( empty( $widgets ) ) {
					$warnings[] = $child_id . ':no_content_widgets';
				}
			} elseif ( $role === 'cta_actions' ) {
				$button_widgets = [];
				$button_index = 0;
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					if ( ! in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2', 'cta_3', 'cta_4' ], true ) ) { continue; }
					$button_role = $button_index === 0 ? 'cta_primary' : 'cta_secondary';
					$button_widgets[] = wpae_elementor_ir_node( $child_id . '-' . $button_role, $button_role, 'button', [ $content_ref ], [ 'color.primary', 'color.text', 'color.surface', 'color.hover', 'color.focus' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
					$button_index++;
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'cta_actions', 'container', [], $token_refs, $button_widgets, (array) ( $child['layout_constraints'] ?? [] ), [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			} elseif ( $role === 'media' ) {
				$media_refs = array_values( array_filter( array_map( 'sanitize_key', (array) ( $child['media_refs'] ?? [] ) ) ) );
				if ( empty( $media_refs ) ) {
					$warnings[] = $child_id . ':missing_media_asset_omitted';
					continue;
				}
				$media_type = in_array( 'image', $allowed, true ) ? 'image' : ( $allowed[0] ?? 'image' );
				$image = wpae_elementor_ir_node( $child_id . '-asset', 'media_image', $media_type, [], $token_refs, [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'media_refs' => $media_refs, 'editable_fields' => [ 'media', 'alt' ] ] );
				$section_children[] = wpae_elementor_ir_node( $child_id, 'media_group', 'container', [], $token_refs, [ $image ], (array) ( $child['layout_constraints'] ?? [ 'min_width' => 0, 'max_width' => 100 ] ), [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'media', 'alt' ] ] );
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
					$step_children[] = wpae_elementor_ir_node( $child_id . '-step-' . $step_number . '-card', 'process_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_children, [ 'item_id' => (string) $step_number ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'process_steps', 'container', [], $token_refs, $step_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			} elseif ( $role === 'portfolio_projects' ) {
				$layout = sanitize_key( (string) ( $child['layout_constraints']['entity_layout'] ?? 'project_cards' ) );
				$projects = wpae_elementor_ir_portfolio_project_nodes( $layout, $child_id, (array) ( $child['items'] ?? [] ), $content_map );
				$collection = wpae_elementor_ir_node( $child_id, 'portfolio_projects', 'container', [], $token_refs, $projects, (array) ( $child['layout_constraints'] ?? [] ), [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] );
				$collection['provenance'] = array_merge( (array) $collection['provenance'], [ 'source' => 'design-plan', 'record_id' => (string) ( $plan['composition_decision']['record_id'] ?? '' ), 'item_groups' => array_values( array_map( static fn( array $item ): string => sanitize_key( (string) ( $item['group_id'] ?? '' ) ), (array) ( $child['items'] ?? [] ) ) ) ] );
				$section_children[] = $collection;
			} elseif ( in_array( $role, [ 'services_photo_grid', 'services_split_editorial', 'services_text_icon_list', 'services_icon_cards' ], true ) ) {
				$recipe_nodes = wpae_elementor_ir_services_recipe_nodes( $role, $child_id, (array) ( $child['items'] ?? [] ), $token_refs, (string) ( $plan['recipe_selection']['lead_service_ref'] ?? '' ) );
				foreach ( $recipe_nodes as $recipe_node ) {
					$section_children[] = $recipe_node;
				}
			} elseif ( $role === 'pricing_cards' ) {
				$intro_widgets = [];
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					$item_role = sanitize_key( (string) ( $item['role'] ?? '' ) );
					if ( $item_role === 'eyebrow' ) {
						$badge_label = wpae_elementor_ir_node( $child_id . '-intro-eyebrow-label', 'eyebrow', 'heading', [ $content_ref ], [ 'color.surface', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
						if ( ( $plan['visual_policy']['intro']['eyebrow_presentation'] ?? 'pill' ) === 'plain' ) { $intro_widgets[] = $badge_label; } else {
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-eyebrow', 'pricing_badge', 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
						}

					} elseif ( $item_role === 'body' ) {
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-body', 'body', 'text-editor', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'title' ) {
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-title', 'title', 'heading', [ $content_ref ], [ 'color.text', 'type.display', 'type.section_title' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					}
				}
				if ( ! empty( $intro_widgets ) ) {
					$section_children[] = wpae_elementor_ir_node( $child_id . '-intro', 'pricing_intro', 'container', [], [ 'color.text', 'color.muted', 'space.component' ], $intro_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
				}
				$card_children = [];
				foreach ( array_values( (array) ( $child['items'] ?? [] ) ) as $card_index => $tier ) {
					if ( ! is_array( $tier ) ) {
						continue;
					}
					$details_widgets = [];
					$price_widgets = [];
					$card_number = $card_index + 1;
					$add_tier_widget = static function ( array &$widgets, string $field, string $node_role, string $widget_type, array $tokens ) use ( $tier, $content_map, $child_id, $card_number ): void {
						$ref = sanitize_key( (string) ( $tier[ $field ] ?? '' ) );
						if ( $ref === '' || empty( $content_map[ $ref ]['exact_text'] ) ) {
							return;
						}
						$widgets[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-' . str_replace( '_ref', '', $field ), $node_role, $widget_type, [ $ref ], $tokens, [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
					};
					$add_tier_widget( $details_widgets, 'label_ref', 'pricing_label', 'heading', [ 'color.text', 'type.body' ] );
					$add_tier_widget( $price_widgets, 'price_ref', 'pricing_price', 'heading', [ 'color.text', 'type.display' ] );
					$add_tier_widget( $price_widgets, 'period_ref', 'pricing_period', 'text-editor', [ 'color.muted', 'type.body' ] );
					$price_group = wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-price-group', 'pricing_price_group', 'container', [], [ 'space.component' ], $price_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					$details_widgets[] = $price_group;
					$add_tier_widget( $details_widgets, 'description_ref', 'pricing_description', 'text-editor', [ 'color.muted', 'type.body' ] );
					foreach ( (array) ( $tier['feature_refs'] ?? [] ) as $feature_index => $feature_ref ) {
						$details_widgets[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-feature-' . $feature_index, 'pricing_feature', 'text-editor', [ $feature_ref ], [ 'color.text', 'type.body' ], [], [], [ 'strategy' => 'stack' ] );
					}
					$details = wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-details', 'pricing_details', 'container', [], [ 'space.component' ], $details_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					$card_widgets = [ wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-body', 'card_body', 'container', [], [ 'space.component' ], [ $details ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ) ];
					$cta_widgets = [];
					$add_tier_widget( $cta_widgets, 'cta_ref', 'cta_primary', 'button', [ 'color.primary', 'color.text', 'color.surface', 'color.hover', 'color.focus' ] );
					if ( $cta_widgets ) { $card_widgets[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-actions', 'card_actions', 'container', [], [ 'space.component' ], $cta_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] ); }
					if ( count( $details_widgets ) + count( $cta_widgets ) < 3 ) {
						$warnings[] = $child_id . ':pricing_tier_' . $card_number . '_incomplete';
					}
					$card_children[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_index, 'pricing_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'pricing_cards', 'container', [], $token_refs, $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			} elseif ( $role === 'faq_surface' ) {
				$accordion = wpae_elementor_ir_node( $child_id . '-accordion', 'faq_accordion', 'accordion', $content_refs, array_merge( $token_refs, [ 'color.focus' ] ), [], [ 'items' => array_values( (array) ( $child['items'] ?? [] ) ) ], [ 'strategy' => 'stack', 'editable_fields' => [ 'question', 'answer' ] ] );
				$section_children[] = wpae_elementor_ir_node( $child_id, 'faq_surface', 'container', [], $token_refs, [ $accordion ], (array) ( $child['layout_constraints'] ?? [] ), [ 'strategy' => 'stack', 'editable_fields' => [ 'question', 'answer' ] ] );
			} elseif ( in_array( $role, [ 'feature_cards', 'feature_list' ], true ) ) {
				$cards = [];
				foreach ( array_values( (array) ( $child['items'] ?? [] ) ) as $card_index => $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$card_number = $card_index + 1;
					$card_children = [
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-icon', $role === 'feature_list' ? 'feature_list_icon' : 'feature_icon', 'icon', [], [ 'color.primary', 'color.surface' ], [], [ 'icon_name' => 'check-circle' ], [ 'strategy' => 'stack', 'editable_fields' => [] ] ),
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-title', 'feature_title', 'heading', [ sanitize_key( (string) ( $item['title_ref'] ?? '' ) ) ], [ 'color.text', 'type.body' ], [], [], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ),
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-body', 'feature_body', 'text-editor', [ sanitize_key( (string) ( $item['body_ref'] ?? '' ) ) ], [ 'color.muted', 'type.body' ], [], [], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ),
					];
					if ( $role === 'feature_list' ) {
						$copy = wpae_elementor_ir_node( $child_id . '-copy-' . $card_number, 'copy_group', 'container', [], [ 'space.component' ], array_slice( $card_children, 1 ), [], [ 'strategy' => 'stack' ] );
						$card_children = [ $card_children[0], $copy ];
					}
					$cards[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_number, $role === 'feature_list' ? 'feature_row' : 'feature_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, $role, 'container', [], $token_refs, $cards, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			} elseif ( in_array( $role, [ 'service_cards', 'team_cards', 'testimonial_cards' ], true ) ) {
				$cards = wpae_elementor_ir_card_nodes( $role, $child_id, (array) ( $child['items'] ?? [] ), [ 'color.text', 'color.muted', 'type.body' ] );
			if ( ( $child['layout_constraints']['entity_layout'] ?? '' ) === 'editorial_rows' ) {
				foreach ( $cards as &$card ) {
					$body = $card['children'][0] ?? [];
					if ( ( $body['role'] ?? '' ) === 'card_body' ) {
						$identity = []; $copy = [];
						foreach ( (array) ( $body['children'] ?? [] ) as $field ) { if ( in_array( $field['role'] ?? '', [ 'team_photo', 'team_name', 'team_position', 'testimonial_photo', 'testimonial_author', 'testimonial_meta' ], true ) ) { $identity[] = $field; } else { $copy[] = $field; } }
						$body['children'] = [];
						if ( $identity ) { $body['children'][] = wpae_elementor_ir_node( $card['node_id'] . '-identity', 'entity_identity', 'container', [], [], $identity ); }
						if ( $copy ) { $body['children'][] = wpae_elementor_ir_node( $card['node_id'] . '-copy', 'entity_copy', 'container', [], [], $copy ); }
						$body['layout_constraints']['entity_layout'] = 'editorial_rows';
						$card['children'][0] = $body;
					}
					$card['layout_constraints']['entity_layout'] = 'editorial_rows';
				}
					unset( $card );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, $role, 'container', [], $token_refs, $cards, (array) ( $child['layout_constraints'] ?? [] ), [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] );
			}
		}
		$section_composition = (string) ( $section['composition'] ?? 'stacked_left' );
		if ( sanitize_key( (string) ( $section['role'] ?? '' ) ) === 'pricing' || ( isset( $plan['visual_policy'] ) && in_array( $section['role'] ?? '', [ 'team', 'testimonials' ], true ) ) ) {
			$section_composition = 'stacked_left';
		}
		$section_layout = [ 'composition' => $section_composition, 'min_width' => 0, 'max_width' => 100, 'media_side' => sanitize_key( (string) ( $section['media_side'] ?? 'right' ) ) ];
		$surface_override = strtolower( trim( (string) ( $section['surface_override'] ?? '' ) ) );
		if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
			$section_layout['surface_override'] = $surface_override;
		}
		$section_surface_token = (string) ( $plan['visual_policy']['section_surface']['token'] ?? '' );
		if ( $surface_override === '' && in_array( $section_surface_token, [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) ) {
			$section_layout['section_surface_token'] = $section_surface_token;
			$section_layout['section_surface_source'] = (string) ( $plan['visual_policy']['section_surface']['source'] ?? 'accepted_visual_policy' );
		}
		$nodes[] = wpae_elementor_ir_node( sanitize_key( (string) ( $section['id'] ?? 'section-' . $section_index ) ), sanitize_key( (string) ( $section['role'] ?? 'section' ) ), 'container', [], [ (string) ( $section['surface_token'] ?? 'color.page_bg' ), (string) ( $section['spacing_token'] ?? 'space.section' ) ], $section_children, $section_layout, [ 'strategy' => $plan['responsive']['mobile'] ?? 'stack' ] );
	}
	if ( isset( $plan['visual_policy']['version'] ) ) {
		foreach ( $nodes as &$policy_node ) { wpae_elementor_ir_bind_visual_policy( $policy_node, $plan['visual_policy'] ); }
		unset( $policy_node );
	}
	return [
		'schema' => WPAE_ELEMENTOR_IR_SCHEMA,
		'archetype' => sanitize_key( (string) ( $plan['archetype'] ?? 'unknown' ) ),
		'recipe_id' => (string) ( $plan['recipe_id'] ?? '' ),
		'composition_decision' => (array) ( $plan['composition_decision'] ?? [] ),
		'recipe_selection' => (array) ( $plan['recipe_selection'] ?? [] ),
		'slot_bindings' => (array) ( $plan['slot_bindings'] ?? [] ),
		'media_compatibility' => (array) ( $plan['media_compatibility'] ?? [] ),
		'media_intent' => sanitize_key( (string) ( $plan['media_intent'] ?? 'unspecified' ) ),
		'media_references' => array_values( (array) ( $plan['media_references'] ?? $brief['media_references'] ?? [] ) ),
		'nodes' => $nodes,
		'provenance' => [ 'plan_hash' => function_exists( 'wpae_design_plan_hash' ) ? wpae_design_plan_hash( $plan ) : '', 'source' => 'design-plan' ],
		'warnings' => array_values( array_unique( $warnings ) ),
	];
}

/** Bind accepted role decisions to IR nodes without selecting another composition/profile. */
function wpae_elementor_ir_bind_visual_policy( array &$node, array $policy, bool $intro = false, bool $badge_label = false, bool $list_row_copy = false, bool $inside_surface_owner = false ): void {
	$role = (string) ( $node['role'] ?? '' );
	$surface = (array) ( $policy['item_surface'] ?? [] );
	$surface_v2 = (int) ( $surface['contract_version'] ?? 0 ) >= 2;
	$surface_owner_role = (string) ( $surface['owner_role'] ?? '' );
	$is_surface_owner = $surface_v2 && $surface_owner_role !== '' && $role === $surface_owner_role;
	$intro = $intro || ( in_array( $role, [ 'copy_group', 'cta_copy_group' ], true ) && ! empty( $node['layout_constraints']['reading_measure'] ) ) || $role === 'pricing_intro';
	$node['visual_policy'] = [ 'version' => $policy['version'], 'intro' => $intro, 'text_align' => $intro ? ( $policy['intro']['text_align'] ?? 'left' ) : 'left', 'container_align' => $intro ? ( $policy['intro']['container_align'] ?? ( $policy['intro']['text_align'] ?? 'start' ) ) : 'start', 'spacing' => $policy['spacing'], 'item_surface' => $policy['item_surface'], 'split' => $policy['split'], 'cta' => $policy['cta'] ?? [], 'badge_label' => $badge_label, 'eyebrow_colors' => $policy['eyebrow_colors'] ];
	if ( $surface_v2 ) {
		$node['visual_policy']['item_surface_scope'] = $is_surface_owner ? 'owner' : ( $inside_surface_owner ? 'interior' : 'outside' );
	}
	if ( ( ( $role === 'card_body' && ( $node['layout_constraints']['entity_layout'] ?? '' ) === 'editorial_rows' ) || $role === 'portfolio_project_row' ) && isset( $policy['entity_layout'] ) ) { $node['visual_policy']['entity_layout'] = $policy['entity_layout']; }
	if ( in_array( $role, [ 'feature_card', 'pricing_card', 'service_card', 'team_card', 'testimonial_card', 'portfolio_project_card', 'services_photo_card', 'services_icon_card', 'process_card' ], true ) && isset( $policy['cards'] ) ) { $node['visual_policy']['cards'] = $policy['cards']; }
	if ( in_array( $role, [ 'feature_card', 'pricing_card', 'service_card', 'team_card', 'testimonial_card', 'portfolio_project_card', 'services_photo_card', 'services_icon_card', 'process_card' ], true ) && ( $policy['collection']['axis'] ?? '' ) === 'grid' && isset( $policy['collection']['surface_alignment'] ) ) {
		$node['visual_policy']['collection_item_alignment'] = $policy['collection']['surface_alignment'];
	}
	if ( $role === 'pricing_price_group' && isset( $policy['inline_value'] ) ) { $node['visual_policy']['inline_value'] = $policy['inline_value']; }
	if ( $intro && $node['widget_type'] === 'container' && in_array( $role, [ 'copy_group', 'cta_copy_group', 'pricing_intro' ], true ) ) { $node['visual_policy']['reading_measure'] = $policy['intro']['reading_measure']; }
	if ( in_array( $role, [ 'feature_cards', 'feature_list', 'pricing_cards', 'service_cards', 'team_cards', 'testimonial_cards', 'portfolio_projects', 'services_photo_grid', 'services_text_icon_list', 'services_icon_cards', 'process_steps' ], true ) ) { $node['visual_policy']['collection'] = $policy['collection']; }
	if ( in_array( $role, [ 'feature_row', 'services_text_icon_row' ], true ) && isset( $policy['list_row'] ) ) { $node['visual_policy']['list_row'] = $policy['list_row']; }
	if ( $list_row_copy && in_array( $role, [ 'copy_group', 'services_text_icon_copy' ], true ) && isset( $policy['list_row']['copy_measure'] ) ) {
		$node['visual_policy']['reading_measure'] = $policy['list_row']['copy_measure'];
		$node['visual_policy']['text_align'] = 'left';
		$node['visual_policy']['container_align'] = 'start';
	}
	if ( $role === 'entity_copy' && isset( $policy['entity_layout']['tracks']['copy_measure'] ) ) {
		$node['visual_policy']['reading_measure'] = $policy['entity_layout']['tracks']['copy_measure'];
		$node['visual_policy']['text_align'] = 'left';
		$node['visual_policy']['container_align'] = 'start';
	}
	if ( $node['widget_type'] === 'heading' ) {
		$key = $intro && in_array( $role, [ 'title', 'cta_section_title', 'services_section_title' ], true ) ? 'intro_title' : ( in_array( $role, [ 'eyebrow', 'eyebrow_badge_label', 'services_badge_label', 'process_badge_label', 'brand' ], true ) ? 'eyebrow' : ( $role === 'pricing_price' ? 'price' : 'item_title' ) );
		$node['visual_policy']['typography'] = $policy['typography'][ $key ];
		$node['visual_policy']['heading_level'] = $key === 'intro_title' ? $policy['intro']['heading_level'] : ( $key === 'eyebrow' ? 'h6' : 'h3' );
	} elseif ( $node['widget_type'] === 'text-editor' ) { $node['visual_policy']['typography'] = $policy['typography']['body']; }
	$child_inside_surface = $inside_surface_owner || $is_surface_owner;
	foreach ( $node['children'] as &$child ) { wpae_elementor_ir_bind_visual_policy( $child, $policy, $intro, in_array( $role, [ 'hero_badge', 'pricing_badge', 'services_badge', 'process_badge' ], true ), in_array( $role, [ 'feature_row', 'services_text_icon_row' ], true ), $child_inside_surface ); }
	unset( $child );
}

/** Translation of frozen Plan values only. The compiler has no prompt or profile decision here. */
function wpae_elementor_ir_visual_controls( array $node, array $settings ): array {
	$policy = $node['visual_policy'];
	$role = (string) $node['role'];
	if ( isset( $policy['typography'] ) ) { $settings = array_merge( $settings, wpae_elementor_ir_type_settings( $policy['typography'] ) ); }
	if ( isset( $policy['heading_level'] ) ) { $settings['header_size'] = $policy['heading_level']; }
	if ( in_array( $node['widget_type'], [ 'heading', 'text-editor', 'button' ], true ) ) {
		$settings['align'] = $policy['text_align'];
		$settings['align_tablet'] = $settings['align_mobile'] = $policy['text_align'];
	}
	if ( in_array( $role, [ 'eyebrow', 'eyebrow_badge_label', 'services_badge_label' ], true ) ) {
		$settings['title_color'] = $policy['eyebrow_colors'][ $policy['badge_label'] ? 'pill' : 'plain' ];
		foreach ( [ 'background_background', 'background_color', 'border_border', 'border_color', 'border_width', 'border_radius', '_padding' ] as $box_key ) { unset( $settings[ $box_key ] ); }
	}
	if ( $node['widget_type'] !== 'container' ) { return $settings; }
	if ( $role === 'services_badge' && isset( $policy['eyebrow_colors']['pill_background'], $policy['eyebrow_colors']['pill_border'] ) ) {
		$settings['background_background'] = 'classic';
		$settings['background_color'] = $policy['eyebrow_colors']['pill_background'];
		$settings['border_color'] = $policy['eyebrow_colors']['pill_border'];
	}
	if ( isset( $policy['inline_value'] ) ) {
		$inline = $policy['inline_value'];
		$gap = wpae_elementor_ir_dimension_control( $inline['gap'], 'rem', 0.25 );
		foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
			$settings['flex_direction' . $suffix] = $inline['direction'][$device];
			$settings['flex_wrap' . $suffix] = $inline['wrap'];
			$settings['flex_justify_content' . $suffix] = $inline['main_align'];
			$settings['flex_align_items' . $suffix] = $inline['cross_align'];
			$settings['flex_gap' . $suffix] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
		}
	}
	$settings['content_width'] = in_array( $role, wpae_design_plan_schema()['archetypes'], true ) ? $settings['content_width'] : 'full';
	if ( in_array( $role, wpae_design_plan_schema()['archetypes'], true ) ) {
		foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
			$pad = wpae_elementor_ir_dimension_control( $policy['spacing']['section'][ $device ], 'rem', 4.5, false );
			unset( $pad['size'] );
			// Horizontal padding is a separate native axis, never the vertical rhythm.
			$pad['right'] = $pad['left'] = $pad['unit'] === 'rem' ? ( $device === 'mobile' ? '1' : '2' ) : ( $device === 'mobile' ? '16' : '32' );
			$settings[ 'padding' . $suffix ] = $pad;
		}
		$tablet = $policy['split']['responsive']['tablet'] ?? 'stack';
		$settings['flex_direction_tablet'] = str_starts_with( $tablet, 'split_' ) ? 'row' : 'column';
		$settings['flex_direction_mobile'] = $policy['split']['mobile_direction'];
		foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
			$gap = wpae_elementor_ir_dimension_control( $policy['split']['gap'][ $device ], 'rem', 1.5 );
			$settings[ 'flex_gap' . $suffix ] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'row' => (string) $gap['size'], 'column' => (string) $gap['size'], 'isLinked' => true ];
		}
	}
	if ( in_array( $role, [ 'feature_card', 'pricing_card', 'service_card', 'team_card', 'testimonial_card', 'portfolio_project_card', 'services_photo_card', 'services_icon_card', 'process_card' ], true ) ) {
		$pad = wpae_elementor_ir_dimension_control( $policy['item_surface']['padding'], 'rem', 1.5, false );
		unset( $pad['size'] );
		$settings['padding'] = $settings['padding_tablet'] = $settings['padding_mobile'] = $pad;
		$radius = wpae_elementor_ir_dimension_control( $policy['item_surface']['radius'], 'rem', 0.5 );
		unset( $radius['size'] );
		$settings['border_radius'] = $radius;
		$settings['background_color'] = $policy['item_surface']['background'];
	}
	if ( isset( $policy['collection_item_alignment'] ) ) {
		$align_self = [ 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'stretch' => 'stretch' ][ (string) $policy['collection_item_alignment'] ] ?? 'flex-start';
		$settings['align_self'] = $settings['align_self_tablet'] = $settings['align_self_mobile'] = $align_self;
	}
	if ( ( in_array( $role, [ 'feature_card', 'pricing_card', 'pricing_details', 'service_card', 'team_card', 'testimonial_card', 'portfolio_project_card', 'portfolio_project_content', 'entity_identity', 'entity_copy', 'services_photo_content', 'services_text_icon_copy', 'card_body', 'services_photo_panel' ], true ) && ! ( $role === 'card_body' && isset( $policy['entity_layout']['tracks'] ) ) ) || ( $role === 'copy_group' && empty( $policy['intro'] ) ) ) {
		$gap = wpae_elementor_ir_dimension_control( $policy['spacing']['item_copy'], 'rem', 0.75 );
		$settings['flex_gap'] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'row' => (string) $gap['size'], 'column' => (string) $gap['size'], 'isLinked' => true ];
		$settings['flex_gap_tablet'] = $settings['flex_gap_mobile'] = $settings['flex_gap'];
	}
	$measured_copy = ! empty( $policy['reading_measure'] );
	$intro_copy = $measured_copy && ! empty( $policy['intro'] );
	if ( $measured_copy ) {
		foreach ( [ '', '_tablet', '_mobile' ] as $suffix ) { unset( $settings[ 'boxed_width' . $suffix ] ); }
		$container_alignment = sanitize_key( (string) ( $policy['container_align'] ?? ( $policy['text_align'] ?? 'start' ) ) );
		$container_alignment = [ 'left' => 'start', 'right' => 'end' ][ $container_alignment ] ?? $container_alignment;
		$container_alignment = in_array( $container_alignment, [ 'start', 'center', 'end' ], true ) ? $container_alignment : 'start';
		$text_alignment = sanitize_key( (string) ( $policy['text_align'] ?? 'left' ) );
		$settings['align_self'] = [ 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' ][ $container_alignment ];
		$settings['flex_align_items'] = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $text_alignment ] ?? 'flex-start';
		$settings['flex_align_items_tablet'] = $settings['flex_align_items_mobile'] = $settings['flex_align_items'];
		if ( $intro_copy ) {
			// The full-width intro column preserves the selected axis; its reading wrapper alone owns the measure.
			$settings['flex_gap'] = [ 'unit' => 'px', 'column' => '0', 'row' => '0', 'isLinked' => true ];
			$settings['flex_gap_tablet'] = $settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
	}
	if ( in_array( $role, wpae_design_plan_schema()['archetypes'], true ) && ! in_array( (string) ( $node['layout_constraints']['composition'] ?? '' ), [ 'split_60_40', 'split_50_50', 'split_40_60' ], true ) ) {
		$gap = wpae_elementor_ir_dimension_control( $policy['spacing']['intro_collection'], 'rem', 2 );
		$settings['flex_gap'] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
		$settings['flex_gap_tablet'] = $settings['flex_gap_mobile'] = $settings['flex_gap'];
	}
	if ( ( $policy['entity_layout']['variant'] ?? '' ) === 'editorial_rows' ) {
		$settings['_wpae_visual_policy_version'] = 1;
		if ( isset( $policy['entity_layout']['tracks'] ) ) {
			$settings['container_type'] = 'flex';
			foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
				$gap = wpae_elementor_ir_dimension_control( $policy['entity_layout']['tracks']['gap'][$device], 'rem', 1 );
				$settings['flex_direction' . $suffix] = $policy['entity_layout']['tracks']['direction'][$device];
				$settings['flex_wrap' . $suffix] = 'nowrap';
				$settings['flex_align_items' . $suffix] = 'flex-start';
				$settings['flex_gap' . $suffix] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
			}
		} else {
			// Historical frozen Plans retain their original equal native Grid tracks.
			$settings['container_type'] = 'grid';
			foreach ( [ '' => 'desktop', '_tablet' => 'tablet', '_mobile' => 'mobile' ] as $suffix => $device ) {
				$columns = $policy['entity_layout']['columns'][$device];
				$settings['grid_columns_grid' . $suffix] = [ 'unit' => 'fr', 'size' => $columns, 'sizes' => [] ];
				$settings['grid_rows_grid' . $suffix] = [ 'unit' => 'custom', 'size' => 'auto', 'sizes' => [] ];
				$settings['grid_auto_flow' . $suffix] = 'row';
				$settings['grid_align_items' . $suffix] = 'start';
				$gap = wpae_elementor_ir_dimension_control( $policy['entity_layout']['gap'], 'rem', 1 );
				$settings['grid_gaps' . $suffix] = [ 'unit' => $gap['unit'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
			}
		}
	}
	if ( isset( $policy['collection'] ) ) {
		$collection = (array) $policy['collection'];
		$implementation = (string) ( $collection['implementation'] ?? 'native_grid' );
		$axis = (string) ( $collection['axis'] ?? 'grid' );
		$settings['_wpae_visual_policy_version'] = 1;
		foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
			$width = (string) ( $collection['width'][$device] ?? '100%' );
			$dimension = str_ends_with( $width, '%' )
				? [ 'unit' => '%', 'size' => (float) rtrim( $width, '%' ), 'sizes' => [] ]
				: [ 'unit' => 'custom', 'size' => 'min(100%, ' . $width . ')', 'sizes' => [] ];
			$settings[ 'width' . $suffix ] = $settings[ '_element_custom_width' . $suffix ] = $dimension;
			$collection_alignment = (string) ( $collection['alignment'] ?? 'start' );
			$settings[ 'align_self' . $suffix ] = [ 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' ][ $collection_alignment ] ?? 'flex-start';
			$settings[ '_element_width' . $suffix ] = 'initial';
			$settings[ '_flex_size' . $suffix ] = 'custom';
			$settings[ '_flex_grow' . $suffix ] = $settings[ 'flex_grow' . $suffix ] = 0;
			$settings[ '_flex_shrink' . $suffix ] = $settings[ 'flex_shrink' . $suffix ] = 1;
		}
		foreach ( [ '', '_tablet', '_mobile' ] as $suffix ) {
			unset( $settings[ 'grid_columns_grid' . $suffix ], $settings[ 'grid_rows_grid' . $suffix ], $settings[ 'grid_auto_flow' . $suffix ], $settings[ 'grid_align_items' . $suffix ], $settings[ 'grid_justify_items' . $suffix ], $settings[ 'grid_gaps' . $suffix ] );
			unset( $settings[ 'flex_direction' . $suffix ], $settings[ 'flex_wrap' . $suffix ], $settings[ 'flex_align_items' . $suffix ], $settings[ 'flex_gap' . $suffix ] );
		}
		foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
			$gap = wpae_elementor_ir_dimension_control( (string) ( $collection['gap'][ $device ] ?? '1.5rem' ), 'rem', 1.5 );
			$gap_control = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
			if ( $axis === 'list' ) {
				$settings['container_type'] = 'flex';
				$settings[ 'flex_direction' . $suffix ] = 'column';
				$settings[ 'flex_wrap' . $suffix ] = 'nowrap';
				$settings[ 'flex_align_items' . $suffix ] = 'stretch';
				$settings[ 'flex_gap' . $suffix ] = $gap_control;
			} elseif ( $implementation === 'native_flex_equal' ) {
				$settings['container_type'] = 'flex';
				$item_count = max( 1, (int) ( $collection['item_count'] ?? 1 ) );
				$columns = min( $item_count, max( 1, (int) ( $collection['columns'][ $device ] ?? 1 ) ) );
				$settings[ 'flex_direction' . $suffix ] = $columns > 1 ? 'row' : 'column';
				$settings[ 'flex_wrap' . $suffix ] = $columns > 1 ? 'wrap' : 'nowrap';
				$settings[ 'flex_align_items' . $suffix ] = ( $collection['item_height'] ?? 'equal_row' ) === 'content' ? 'flex-start' : 'stretch';
				$settings[ 'flex_gap' . $suffix ] = $gap_control;
			} else {
				$settings['container_type'] = 'grid';
				$settings[ 'grid_columns_grid' . $suffix ] = [ 'unit' => 'fr', 'size' => max( 1, (int) ( $collection['columns'][ $device ] ?? 1 ) ), 'sizes' => [] ];
				$settings[ 'grid_rows_grid' . $suffix ] = [ 'unit' => 'custom', 'size' => 'auto', 'sizes' => [] ];
				$settings[ 'grid_gaps' . $suffix ] = $gap_control;
				$settings[ 'grid_auto_flow' . $suffix ] = 'row';
				$settings[ 'grid_align_items' . $suffix ] = ( $collection['item_height'] ?? 'equal_row' ) === 'content' ? 'start' : 'stretch';
				$settings[ 'grid_justify_items' . $suffix ] = 'stretch';
			}
		}
	}
	if ( isset( $policy['list_row'] ) ) {
		$list_row = $policy['list_row'];
		$settings['_wpae_visual_policy_version'] = 1;
		$settings['container_type'] = 'flex';
		foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
			$gap = wpae_elementor_ir_dimension_control( $list_row['gap'][$device], 'rem', 1 );
			$settings[ 'flex_direction' . $suffix ] = $list_row['direction'][$device];
			$settings[ 'flex_wrap' . $suffix ] = 'nowrap';
			$settings[ 'flex_align_items' . $suffix ] = 'flex-start';
			$settings[ 'flex_gap' . $suffix ] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
		}
	}
	if ( isset( $policy['cards'] ) && in_array( $role, [ 'feature_card', 'pricing_card', 'service_card', 'team_card', 'testimonial_card', 'portfolio_project_card', 'services_photo_card', 'services_icon_card', 'process_card' ], true ) ) {
		$card = (array) $policy['cards'];
		$has_actions = (bool) array_filter( (array) ( $node['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'card_actions' );
		$body_has_media = (bool) array_filter( (array) ( $node['children'][0]['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['widget_type'] ?? '' ) === 'image' );
		$copy_gap = wpae_elementor_ir_dimension_control( $body_has_media ? $card['media_copy_gap'] : $card['body_copy_gap'], 'rem', 0.75 );
		$actions_gap = wpae_elementor_ir_dimension_control( $card['body_actions_gap'], 'rem', 1.25 );
		$settings['container_type'] = 'flex';
		$settings['flex_direction'] = $settings['flex_direction_tablet'] = $settings['flex_direction_mobile'] = $card['direction'];
		$settings['flex_wrap'] = $settings['flex_wrap_tablet'] = $settings['flex_wrap_mobile'] = 'nowrap';
		$settings['flex_align_items'] = $settings['flex_align_items_tablet'] = $settings['flex_align_items_mobile'] = $card['row_alignment'];
		$main_distribution = $has_actions ? ( [ 'space_between' => 'space-between', 'flex_start' => 'flex-start' ][ (string) ( $card['body_actions_distribution'] ?? '' ) ] ?? 'flex-start' ) : 'flex-start';
		$settings['flex_justify_content'] = $settings['flex_justify_content_tablet'] = $main_distribution;
		$settings['flex_justify_content_mobile'] = 'flex-start';
		$gap = $has_actions ? $actions_gap : $copy_gap;
		$settings['flex_gap'] = $settings['flex_gap_tablet'] = $settings['flex_gap_mobile'] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
	}
	// Editorial entity rows own the spacing between identity and copy through
	// their accepted tracks. Do not let the generic card-body fallback replace
	// those responsive native controls after visual-policy translation.
	if ( in_array( $role, [ 'card_body', 'services_photo_panel' ], true ) && ! ( $role === 'card_body' && isset( $policy['entity_layout']['tracks'] ) ) ) {
		$card = (array) ( $policy['cards'] ?? [] );
		$gap_value = ( $role === 'card_body' && array_filter( (array) ( $node['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['widget_type'] ?? '' ) === 'image' ) ) ? ( $card['media_copy_gap'] ?? $policy['spacing']['item_copy'] ) : ( $card['body_copy_gap'] ?? $policy['spacing']['item_copy'] );
		$gap = wpae_elementor_ir_dimension_control( $gap_value, 'rem', 0.75 );
		$settings['flex_gap'] = $settings['flex_gap_tablet'] = $settings['flex_gap_mobile'] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
	}
	if ( $role === 'card_actions' ) {
		$gap = wpae_elementor_ir_dimension_control( $policy['cards']['body_actions_gap'] ?? $policy['spacing']['item_cta'], 'rem', 1.25 );
		$settings['flex_gap'] = $settings['flex_gap_tablet'] = $settings['flex_gap_mobile'] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
	}
	if ( (int) ( $policy['item_surface']['contract_version'] ?? 0 ) >= 2 ) {
		$scope = (string) ( $policy['item_surface_scope'] ?? 'outside' );
		$surface = (array) $policy['item_surface'];
		$dimension = static function ( $value, string $fallback_unit, float $fallback_size, bool $linked = false ): array {
			$control = wpae_elementor_ir_dimension_control( $value, $fallback_unit, $fallback_size, $linked );
			unset( $control['size'], $control['sizes'] );
			return $control;
		};
		$zero_box = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
		if ( $scope === 'owner' && ( $surface['mode'] ?? '' ) === 'card' ) {
			$settings['background_background'] = 'classic';
			$settings['background_color'] = (string) ( $surface['background'] ?? '#ffffff' );
			$explicit_surface = strtolower( trim( (string) ( $node['layout_constraints']['surface_override'] ?? '' ) ) );
			if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $explicit_surface ) ) { $settings['background_color'] = $explicit_surface; }
			$settings['border_border'] = (string) ( $surface['border_style'] ?? 'solid' );
			$settings['border_color'] = (string) ( $surface['border_color'] ?? '#d1d5db' );
			$settings['border_width'] = $dimension( $surface['border_width'] ?? '1px', 'px', 1, true );
			$settings['border_radius'] = $dimension( $surface['radius'] ?? '0px', 'px', 0, true );
			$settings['padding'] = $dimension( $surface['padding'] ?? '0px', 'px', 0 );
			$settings['padding_tablet'] = $dimension( $surface['padding_tablet'] ?? $surface['padding'] ?? '0px', 'px', 0 );
			$settings['padding_mobile'] = $dimension( $surface['padding_mobile'] ?? $surface['padding'] ?? '0px', 'px', 0 );
			if ( isset( $surface['shadow'] ) && is_array( $surface['shadow'] ) ) { $settings['box_shadow'] = $surface['shadow']; }
		} elseif ( $scope === 'owner' && ( $surface['mode'] ?? '' ) === 'transparent_divider' ) {
			$settings['background_background'] = 'classic';
			$settings['background_color'] = 'transparent';
			$settings['border_border'] = 'none';
			$settings['border_color'] = 'transparent';
			$settings['border_width'] = $zero_box;
			$settings['border_radius'] = $zero_box;
			$settings['padding'] = $dimension( $surface['padding'] ?? '0px', 'px', 0 );
			$settings['padding_tablet'] = $dimension( $surface['padding_tablet'] ?? $surface['padding'] ?? '0px', 'px', 0 );
			$settings['padding_mobile'] = $dimension( $surface['padding_mobile'] ?? $surface['padding'] ?? '0px', 'px', 0 );
		} elseif ( $scope === 'interior' && ! in_array( $role, [ 'process_marker', 'services_badge', 'process_badge', 'pricing_badge', 'hero_badge' ], true ) ) {
			// A color token is available to the Plan, but never paints every nested wrapper.
			$settings['background_background'] = 'classic';
			$settings['background_color'] = 'transparent';
			$settings['border_border'] = 'none';
			$settings['border_color'] = 'transparent';
			$settings['border_width'] = $zero_box;
			$settings['border_radius'] = $zero_box;
			$settings['padding'] = $settings['padding_tablet'] = $settings['padding_mobile'] = $zero_box;
			unset( $settings['box_shadow'] );
		}
	}
	return $settings;
}

function wpae_elementor_ir_validate( array $ir, array $brief = [] ): array {
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
	if ( in_array( $ir['archetype'] ?? '', [ 'hero', 'about' ], true ) ) {
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
	if ( ( $ir['archetype'] ?? '' ) === 'cta' ) {
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
	$recipe_id = (string) ( $ir['recipe_id'] ?? '' );
	if ( $recipe_id !== '' ) {
		$known_recipes = [ 'services.photo_cards', 'services.split_editorial', 'services.text_icon_list', 'services.icon_cards' ];
		if ( ! in_array( $recipe_id, $known_recipes, true ) || ( $ir['archetype'] ?? '' ) !== 'services' ) {
			$errors[] = 'services_recipe_unknown_or_archetype_mismatch';
		} else {
			$flat = [];
			$collect = static function ( array $nodes ) use ( &$collect, &$flat ): void {
				foreach ( $nodes as $node ) {
					if ( ! is_array( $node ) ) {
						continue;
					}
					$flat[ (string) ( $node['role'] ?? '' ) ][] = $node;
					$collect( (array) ( $node['children'] ?? [] ) );
				}
			};
			$collect( (array) ( $ir['nodes'] ?? [] ) );
			$recipe_roles = [ 'services.photo_cards' => 'services_photo_grid', 'services.split_editorial' => 'services_split_editorial', 'services.text_icon_list' => 'services_text_icon_list', 'services.icon_cards' => 'services_icon_cards' ];
			$expected_role = $recipe_roles[ $recipe_id ] ?? '';
			$recipe_items = array_values( array_filter( (array) ( $ir['slot_bindings']['services'] ?? [] ), 'is_array' ) );
			$expected_group_ids = array_values( array_map( static fn( array $item ): string => sanitize_key( (string) ( $item['group_id'] ?? '' ) ), $recipe_items ) );
			if ( count( $expected_group_ids ) < 2 || count( $expected_group_ids ) > 6 || count( $expected_group_ids ) !== count( array_unique( $expected_group_ids ) ) ) {
				$errors[] = 'services_recipe_slot_bindings_invalid';
			}
			if ( count( (array) ( $flat[ $expected_role ] ?? [] ) ) !== 1 || ! empty( $flat['service_cards'] ) ) {
				$errors[] = 'services_recipe_topology_invalid';
			}
			foreach ( $recipe_items as $item ) {
				$group_id = sanitize_key( (string) ( $item['group_id'] ?? '' ) );
				foreach ( [ 'services_recipe_title' => 'title_ref', 'service_body' => 'body_ref', 'service_cta' => 'cta_ref' ] as $node_role => $field ) {
					$matches = array_values( array_filter( (array) ( $flat[ $node_role ] ?? [] ), static fn( array $node ): bool => ( $node['provenance']['item_id'] ?? '' ) === $group_id ) );
					$expected_ref = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
					if ( ( $expected_ref === '' && ! empty( $matches ) ) || ( $expected_ref !== '' && ( count( $matches ) !== 1 || (array) ( $matches[0]['content_refs'] ?? [] ) !== [ $expected_ref ] ) ) ) {
						$errors[] = $group_id . '_recipe_' . $field . '_ir_binding_invalid';
					}
				}
			}
			if ( $recipe_id === 'services.photo_cards' ) {
				$cards = (array) ( $flat['services_photo_card'] ?? [] );
				$card_group_ids = array_values( array_map( static fn( array $node ): string => sanitize_key( (string) ( $node['layout_constraints']['item_id'] ?? '' ) ), $cards ) );
				if ( count( $cards ) < 2 || count( $cards ) > 6 || $card_group_ids !== $expected_group_ids || count( $flat['services_photo_image'] ?? [] ) !== count( $cards ) || count( $flat['services_photo_panel'] ?? [] ) !== count( $cards ) ) {
					$errors[] = 'services_photo_card_structure_invalid';
				}
				foreach ( $cards as $card ) {
					$direct = array_values( (array) ( $card['children'] ?? [] ) );
					$group_id = sanitize_key( (string) ( $card['layout_constraints']['item_id'] ?? '' ) );
					$slot = array_values( array_filter( $recipe_items, static fn( array $item ): bool => ( $item['group_id'] ?? '' ) === $group_id ) )[0] ?? [];
					$body_children = array_values( (array) ( $direct[0]['children'] ?? [] ) );
					$expected_action_ref = sanitize_key( (string) ( $slot['cta_ref'] ?? '' ) );
					$actions = $direct[1] ?? [];
					$action_children = (array) ( $actions['children'] ?? [] );
					if ( ( $direct[0]['role'] ?? '' ) !== 'card_body' || ( $body_children[0]['role'] ?? '' ) !== 'services_photo_image' || ( $body_children[1]['role'] ?? '' ) !== 'services_photo_panel' || (array) ( $body_children[0]['media_refs'] ?? [] ) !== [ sanitize_key( (string) ( $slot['media_ref'] ?? '' ) ) ] || ( $expected_action_ref === '' && count( $direct ) !== 1 ) || ( $expected_action_ref !== '' && ( count( $direct ) !== 2 || ( $actions['role'] ?? '' ) !== 'card_actions' || count( $action_children ) !== 1 || ( $action_children[0]['content_refs'] ?? [] ) !== [ $expected_action_ref ] ) ) ) {
						$errors[] = 'services_photo_card_body_actions_invalid';
					}
				}
			}
			if ( $recipe_id === 'services.split_editorial' ) {
				$lead_nodes = (array) ( $flat['services_split_lead'] ?? [] );
				$lead_id = sanitize_key( (string) ( $ir['recipe_selection']['lead_service_ref'] ?? '' ) );
				$lead_slot = array_values( array_filter( $recipe_items, static fn( array $item ): bool => ( $item['group_id'] ?? '' ) === $lead_id ) )[0] ?? [];
				$secondary_ids = array_values( array_filter( $expected_group_ids, static fn( string $group_id ): bool => $group_id !== $lead_id ) );
				$rows_node = (array) ( $flat['services_editorial_rows'][0] ?? [] );
				$row_ids = array_values( array_map( static fn( array $node ): string => sanitize_key( (string) ( $node['layout_constraints']['item_id'] ?? '' ) ), array_filter( (array) ( $rows_node['children'] ?? [] ), static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_editorial_row' ) ) );
				$lead_images = array_values( array_filter( (array) ( $flat['services_split_image'] ?? [] ), static fn( array $node ): bool => ( $node['media_refs'][0] ?? '' ) === ( $lead_slot['media_ref'] ?? '' ) ) );
				if ( count( $lead_nodes ) !== 1 || ( $lead_nodes[0]['layout_constraints']['item_id'] ?? '' ) !== $lead_id || count( $lead_images ) !== 1 || count( $flat['services_editorial_rows'] ?? [] ) !== 1 || $row_ids !== $secondary_ids ) {
					$errors[] = 'services_split_editorial_structure_invalid';
				}
			}
			if ( $recipe_id === 'services.text_icon_list' ) {
				$rows = (array) ( $flat['services_text_icon_row'] ?? [] );
				$row_ids = array_values( array_map( static fn( array $node ): string => sanitize_key( (string) ( $node['layout_constraints']['item_id'] ?? '' ) ), $rows ) );
				if ( count( $rows ) < 2 || count( $rows ) > 6 || $row_ids !== $expected_group_ids || count( $flat['services_list_icon'] ?? [] ) !== count( $rows ) || ! empty( $flat['services_photo_image'] ) || ! empty( $flat['services_split_image'] ) ) {
					$errors[] = 'services_text_icon_list_structure_invalid';
				}
			}
			if ( $recipe_id === 'services.icon_cards' ) {
				$cards = (array) ( $flat['services_icon_card'] ?? [] );
				$card_group_ids = array_values( array_map( static fn( array $node ): string => sanitize_key( (string) ( $node['layout_constraints']['item_id'] ?? '' ) ), $cards ) );
				if ( count( $cards ) < 2 || count( $cards ) > 6 || $card_group_ids !== $expected_group_ids || count( $flat['services_list_icon'] ?? [] ) !== count( $cards ) || ! empty( $flat['services_photo_image'] ) || ! empty( $flat['services_split_image'] ) ) {
					$errors[] = 'services_icon_cards_structure_invalid';
				}
				foreach ( $cards as $card ) {
					$direct = array_values( (array) ( $card['children'] ?? [] ) );
					$group_id = sanitize_key( (string) ( $card['layout_constraints']['item_id'] ?? '' ) );
					$body_children = array_values( (array) ( $direct[0]['children'] ?? [] ) );
					$matching_slot = array_values( array_filter( $recipe_items, static fn( array $item ): bool => ( $item['group_id'] ?? '' ) === $group_id ) )[0] ?? [];
					$expected_action_ref = sanitize_key( (string) ( $matching_slot['cta_ref'] ?? '' ) );
					$actions = $direct[1] ?? [];
					$action_children = (array) ( $actions['children'] ?? [] );
					if ( ( $direct[0]['role'] ?? '' ) !== 'card_body' || ( $body_children[0]['role'] ?? '' ) !== 'services_list_icon' || ( $direct[0]['provenance']['item_id'] ?? '' ) !== $group_id || ( $expected_action_ref === '' && count( $direct ) !== 1 ) || ( $expected_action_ref !== '' && ( count( $direct ) !== 2 || ( $actions['role'] ?? '' ) !== 'card_actions' || count( $action_children ) !== 1 || ( $action_children[0]['content_refs'] ?? [] ) !== [ $expected_action_ref ] ) ) ) {
						$errors[] = 'services_icon_card_body_actions_invalid';
					}
				}
			}
			if ( $brief ) {
				$content_map = wpae_elementor_ir_content_map( $brief );
				$expected_by_group = [];
				foreach ( (array) ( $brief['groups'] ?? [] ) as $group ) {
					if ( is_array( $group ) ) {
						$expected_by_group[ sanitize_key( (string) ( $group['group_id'] ?? '' ) ) ] = $group;
					}
				}
				foreach ( [ 'services_recipe_title' => [ 'title_ref', 'service_title' ], 'service_body' => [ 'body_ref', 'service_body' ], 'service_cta' => [ 'cta_ref', 'service_cta' ] ] as $role => [ $field, $brief_role ] ) {
					foreach ( (array) ( $flat[ $role ] ?? [] ) as $node ) {
						$ref = sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) );
						$group_id = sanitize_key( (string) ( $node['provenance']['item_id'] ?? '' ) );
						$belongs = false;
						foreach ( (array) ( $node['content_refs'] ?? [] ) as $candidate_ref ) {
							$content = $content_map[ sanitize_key( (string) $candidate_ref ) ] ?? [];
							if ( ! empty( $content ) && ( $content['group_id'] ?? '' ) === $group_id && ( $content['role'] ?? '' ) === $brief_role ) {
								$belongs = true;
								break;
							}
						}
						if ( ! $belongs || ( $ref !== '' && empty( $content_map[ $ref ] ) ) ) {
							$errors[] = 'services_recipe_content_group_mismatch';
						}
					}
				}
			}
		}
	}
	if ( ( $ir['archetype'] ?? '' ) === 'process' && ( $ir['composition_decision']['record_id'] ?? '' ) === 'process.ordered_steps' ) {
		$flat = [];
		$collect_process = static function ( array $nodes ) use ( &$collect_process, &$flat ): void {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) { continue; }
				$flat[ (string) ( $node['role'] ?? '' ) ][] = $node;
				$collect_process( (array) ( $node['children'] ?? [] ) );
			}
		};
		$collect_process( (array) ( $ir['nodes'] ?? [] ) );
		$collections = (array) ( $flat['process_steps'] ?? [] );
		$steps = [];
		foreach ( $collections as $collection ) { $steps = array_merge( $steps, array_values( (array) ( $collection['children'] ?? [] ) ) ); }
		if ( count( $collections ) !== 1 || count( $steps ) < 3 || count( $steps ) > 6 || array_values( array_map( static fn( array $step ): string => (string) ( $step['layout_constraints']['item_id'] ?? '' ), $steps ) ) !== array_map( 'strval', range( 1, count( $steps ) ) ) || count( $flat['process_card_title'] ?? [] ) !== count( $steps ) ) {
			$errors[] = 'typed_process_ordered_steps_structure_invalid';
		}
	}
	if ( ( $ir['archetype'] ?? '' ) === 'portfolio' ) {
		$portfolio_flat = [];
		$collect_portfolio = static function ( array $nodes ) use ( &$collect_portfolio, &$portfolio_flat ): void {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) { continue; }
				$portfolio_flat[ (string) ( $node['role'] ?? '' ) ][] = $node;
				$collect_portfolio( (array) ( $node['children'] ?? [] ) );
			}
		};
		$collect_portfolio( (array) ( $ir['nodes'] ?? [] ) );
		// Composition IDs are dotted catalog keys; sanitize_key would erase the
		// family boundary and make an accepted Portfolio record unverifiable.
		$record_id = trim( (string) ( $ir['composition_decision']['record_id'] ?? '' ) );
		$record_layouts = [ 'portfolio.project_cards' => 'project_cards', 'portfolio.editorial_rows' => 'editorial_rows' ];
		$expected_layout = $record_layouts[$record_id] ?? '';
		$collections = array_values( (array) ( $portfolio_flat['portfolio_projects'] ?? [] ) );
		$collection = $collections[0] ?? [];
		$layout = sanitize_key( (string) ( $collection['layout_constraints']['entity_layout'] ?? '' ) );
		$projects = array_values( (array) ( $collection['children'] ?? [] ) );
		$group_ids = array_values( array_map( 'sanitize_key', (array) ( $collection['provenance']['item_groups'] ?? [] ) ) );
		if ( $record_id === '' || $expected_layout === '' || count( $collections ) !== 1 || ( $collection['provenance']['record_id'] ?? '' ) !== $record_id || $layout !== $expected_layout || count( $projects ) < 2 || count( $projects ) > 6 || count( $group_ids ) !== count( $projects ) || count( array_unique( $group_ids ) ) !== count( $group_ids ) ) {
			$errors[] = 'portfolio_ir_collection_or_record_invalid';
		}
		$brief_groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
		if ( $brief_groups ) {
			$brief_group_ids = array_values( array_map( static fn( array $item ): string => sanitize_key( (string) ( $item['group_id'] ?? '' ) ), $brief_groups ) );
			if ( $group_ids !== $brief_group_ids ) { $errors[] = 'portfolio_ir_group_order_changed'; }
		}
		$media_by_id = [];
		foreach ( (array) ( $ir['media_references'] ?? [] ) as $asset ) {
			if ( is_array( $asset ) && trim( (string) ( $asset['asset_id'] ?? '' ) ) !== '' ) {
				$media_by_id[ sanitize_key( (string) $asset['asset_id'] ) ] = $asset;
			}
		}
		$content_map = $brief ? wpae_elementor_ir_content_map( $brief ) : [];
		$consumed_assets = [];
		foreach ( $projects as $index => $project ) {
			if ( ! is_array( $project ) ) { $errors[] = 'portfolio_ir_project_node_invalid'; continue; }
			$group_id = $group_ids[$index] ?? '';
			$expected_role = $expected_layout === 'project_cards' ? 'portfolio_project_card' : 'portfolio_project_row';
			if ( $group_id === '' || ( $project['role'] ?? '' ) !== $expected_role || sanitize_key( (string) ( $project['provenance']['group_id'] ?? '' ) ) !== $group_id || sanitize_key( (string) ( $project['layout_constraints']['item_id'] ?? '' ) ) !== $group_id ) {
				$errors[] = 'portfolio_ir_project_owner_or_topology_invalid';
				continue;
			}
			$find_descendants = static function ( array $node, string $role ) use ( &$find_descendants ): array {
				$found = [];
				foreach ( (array) ( $node['children'] ?? [] ) as $child ) {
					if ( ! is_array( $child ) ) { continue; }
					if ( ( $child['role'] ?? '' ) === $role ) { $found[] = $child; }
					$found = array_merge( $found, $find_descendants( $child, $role ) );
				}
				return $found;
			};
			$direct = array_values( (array) ( $project['children'] ?? [] ) );
			if ( $expected_layout === 'project_cards' ) {
				$body = $direct[0] ?? [];
				$actions = $direct[1] ?? [];
				$body_children = array_values( (array) ( $body['children'] ?? [] ) );
				if ( ( $body['role'] ?? '' ) !== 'card_body' || ( $body['provenance']['group_id'] ?? '' ) !== $group_id || ! in_array( count( $direct ), [ 1, 2 ], true ) || ( count( $direct ) === 2 && ( $actions['role'] ?? '' ) !== 'card_actions' ) || ( $body_children[0]['role'] ?? '' ) !== 'portfolio_project_image' || ( $body_children[1]['role'] ?? '' ) !== 'portfolio_project_content' || count( $body_children ) !== 2 ) {
					$errors[] = 'portfolio_project_cards_native_topology_invalid:' . $group_id;
				}
			} else {
				$identity = $direct[0] ?? [];
				$copy = $direct[1] ?? [];
				$copy_children = array_values( (array) ( $copy['children'] ?? [] ) );
				if ( count( $direct ) !== 2 || ( $identity['role'] ?? '' ) !== 'entity_identity' || ( $copy['role'] ?? '' ) !== 'entity_copy' || count( $copy_children ) !== 1 || ( $copy_children[0]['role'] ?? '' ) !== 'portfolio_project_content' || count( (array) ( $identity['children'] ?? [] ) ) !== 1 || ( $identity['children'][0]['role'] ?? '' ) !== 'portfolio_project_image' ) {
					$errors[] = 'portfolio_editorial_rows_native_topology_invalid:' . $group_id;
				}
			}
			foreach ( [ 'portfolio_project_image' => 'media', 'portfolio_project_title' => 'portfolio_project_title', 'portfolio_project_description' => 'portfolio_project_description', 'portfolio_project_category' => 'portfolio_project_category', 'portfolio_project_action' => 'portfolio_project_action_label' ] as $role => $content_role ) {
				$owned = array_values( array_filter( $find_descendants( $project, $role ), static fn( array $node ): bool => sanitize_key( (string) ( $node['provenance']['group_id'] ?? '' ) ) === $group_id ) );
				if ( $role === 'portfolio_project_image' ) { continue; }
				if ( count( $owned ) > 1 || ( in_array( $role, [ 'portfolio_project_title', 'portfolio_project_description' ], true ) && count( $owned ) !== 1 ) ) { $errors[] = 'portfolio_project_content_owner_invalid:' . $group_id . ':' . $role; continue; }
				if ( $owned ) {
					$ref = sanitize_key( (string) ( $owned[0]['content_refs'][0] ?? '' ) );
					$slot = $content_map[$ref] ?? [];
					if ( count( (array) ( $owned[0]['content_refs'] ?? [] ) ) !== 1 || ( $slot['role'] ?? '' ) !== $content_role || ( $slot['group_id'] ?? '' ) !== $group_id ) { $errors[] = 'portfolio_project_content_binding_invalid:' . $group_id . ':' . $role; }
					if ( $role === 'portfolio_project_action' && ( empty( $slot['url_requested'] ) || trim( (string) ( $slot['url'] ?? '' ) ) === '' ) ) { $errors[] = 'portfolio_project_action_url_invalid:' . $group_id; }
				}
			}
			$images = array_values( array_filter( $find_descendants( $project, 'portfolio_project_image' ), static fn( array $node ): bool => sanitize_key( (string) ( $node['provenance']['group_id'] ?? '' ) ) === $group_id ) );
			$asset_id = sanitize_key( (string) ( $images[0]['media_refs'][0] ?? '' ) );
			$asset = $media_by_id[$asset_id] ?? [];
			if ( count( $images ) !== 1 || count( (array) ( $images[0]['media_refs'] ?? [] ) ) !== 1 || $asset_id === '' || sanitize_key( (string) ( $images[0]['provenance']['asset_id'] ?? '' ) ) !== $asset_id || ( $asset['group_id'] ?? '' ) !== $group_id || ( $asset['role'] ?? '' ) !== 'project_image' || empty( $asset['allowed_reuse'] ) || trim( (string) ( $asset['alt'] ?? '' ) ) === '' || absint( $asset['asset_facts']['width'] ?? 0 ) < 16 || absint( $asset['asset_facts']['height'] ?? 0 ) < 16 || ( empty( $asset['attachment_id'] ) && trim( (string) ( $asset['source_url'] ?? '' ) ) === '' ) || ! is_array( $asset['render'] ?? null ) || ! in_array( $asset['render']['object_fit'] ?? '', [ 'cover', 'contain' ], true ) ) {
				$errors[] = 'portfolio_project_media_identity_or_render_policy_invalid:' . $group_id;
			}
			$consumed_assets[] = $asset_id;
		}
		if ( $media_by_id && ( count( array_unique( $consumed_assets ) ) !== count( $consumed_assets ) || array_diff( array_keys( $media_by_id ), $consumed_assets ) ) ) { $errors[] = 'portfolio_ir_media_unowned_or_reused'; }
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

/**
 * Preserve explicit paragraph boundaries in plain text-editor copy.
 * Elementor's text-editor collapses raw newlines in normal HTML whitespace,
 * so authored paragraphs must be emitted as native rich-text paragraphs.
 */
function wpae_elementor_ir_text_editor_content( string $text ): string {
	$normalized = str_replace( [ "\r\n", "\r" ], "\n", $text );
	if ( preg_match( '/<\s*\/?\s*[a-z][^>]*>/i', $normalized ) ) {
		return $text;
	}
	$paragraphs = preg_split( '/\n[ \t]*\n+/u', trim( $normalized ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $paragraphs ) || count( $paragraphs ) < 2 ) {
		return $text;
	}
	$rendered = [];
	foreach ( $paragraphs as $paragraph ) {
		$paragraph = trim( (string) $paragraph );
		if ( $paragraph === '' ) {
			continue;
		}
		$escaped = htmlspecialchars( $paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$rendered[] = '<p>' . nl2br( $escaped, false ) . '</p>';
	}
	return count( $rendered ) > 1 ? implode( "\n", $rendered ) : $text;
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

/** Return an Elementor custom width whose flex basis already accounts for every gap. */
function wpae_elementor_ir_flex_equal_track_dimension( $gap_value, int $columns ): array {
	$columns = max( 1, $columns );
	if ( $columns === 1 ) {
		return [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
	}
	$gap = wpae_elementor_ir_dimension_control( $gap_value, 'rem', 1.5 );
	$number = rtrim( rtrim( number_format( (float) $gap['size'], 4, '.', '' ), '0' ), '.' );
	$term = ( $number === '' ? '0' : $number ) . (string) $gap['unit'];
	$subtractions = implode( ' - ', array_fill( 0, $columns - 1, $term ) );
	return [ 'unit' => 'custom', 'size' => 'calc((100% - ' . $subtractions . ') / ' . $columns . ')', 'sizes' => [] ];
}

/** Native image dimension controls use unit/size, while asset facts stay intrinsic. */
function wpae_elementor_ir_media_dimension( $value ): array {
	$raw = trim( (string) $value );
	if ( $raw === 'auto' ) { return []; }
	if ( ! preg_match( '/^(0|[1-9]\d*(?:\.\d+)?)(px|%|em|rem|vh|vw)$/', $raw, $match ) ) { return []; }
	return [ 'unit' => strtolower( $match[2] ), 'size' => (float) $match[1], 'sizes' => [] ];
}

/**
 * Keep WordPress's attachment renderer only when its stored alt is the accepted alt.
 * Elementor's ID-backed renderer derives alt from attachment metadata and ignores
 * the Image widget's custom alt field; a URL-backed widget honors that field.
 */
function wpae_elementor_ir_image_attachment_id_for_alt( array $media ): int {
	$attachment_id = absint( $media['attachment_id'] ?? 0 );
	$accepted_alt = trim( (string) ( $media['alt'] ?? '' ) );
	if ( $attachment_id < 1 || $accepted_alt === '' ) { return $attachment_id; }
	if ( ! function_exists( 'get_post_meta' ) ) { return 0; }
	$attachment_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	return $attachment_alt === $accepted_alt ? $attachment_id : 0;
}

function wpae_elementor_ir_focal_position( $focal ): string {
	if ( ! is_array( $focal ) || ! is_numeric( $focal['x'] ?? null ) || ! is_numeric( $focal['y'] ?? null ) ) { return ''; }
	// Elementor's Image widget exposes a nine-position select, not arbitrary
	// percentages. Keep the exact normalized focal point in Brief/Plan, but lower
	// it to the closest supported native keyword pair instead of writing a value
	// the control cannot represent. This is intentionally a coarse 3x3 mapping.
	$axis = static function ( float $value, string $start, string $middle, string $end ): string {
		$value = max( 0.0, min( 1.0, $value ) );
		if ( $value < ( 1 / 3 ) ) { return $start; }
		if ( $value > ( 2 / 3 ) ) { return $end; }
		return $middle;
	};
	$horizontal = $axis( (float) $focal['x'], 'left', 'center', 'right' );
	$vertical = $axis( (float) $focal['y'], 'top', 'center', 'bottom' );
	return $vertical . ' ' . $horizontal;
}

function wpae_elementor_ir_service_image_radius( string $card_radius ): array {
	$radius = wpae_elementor_ir_dimension_control( $card_radius, 'px', 16 );
	return [
		'unit' => $radius['unit'],
		'top' => (string) $radius['top'],
		'right' => (string) $radius['right'],
		'bottom' => '0',
		'left' => (string) $radius['left'],
		'isLinked' => false,
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
	foreach ( [ 'tablet', 'mobile' ] as $device ) {
		if ( isset( $type[ 'line_height_' . $device ] ) ) { $settings[ 'typography_line_height_' . $device ] = $dimension( $type[ 'line_height_' . $device ], 'em' ); }
	}
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
	$cta_has_media = $role === 'cta' && (bool) array_filter( (array) ( $node['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'media_group' );
	$settings['background_background'] = 'classic';
	if ( isset( $token_values['color.page_bg'] ) && is_string( $token_values['color.page_bg'] ) ) {
		$settings['background_color'] = $token_values['color.page_bg'];
	}
	if ( $widget_type === 'container' ) {
		$settings['container_type'] = 'flex';
		$settings['content_width'] = in_array( $role, wpae_design_plan_schema()['archetypes'], true ) ? 'boxed' : 'full';
		$settings['padding'] = [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
		$settings['background_color'] = $settings['background_color'] ?? 'transparent';
		$settings['flex_direction'] = in_array( (string) ( $node['layout_constraints']['composition'] ?? '' ), [ 'split_60_40', 'split_50_50', 'split_40_60', 'cta_split_actions' ], true ) ? 'row' : 'column';
		$settings['flex_direction_mobile'] = 'column';
		$component_gap = $token_values['space.component'] ?? ( $tokens['native_tokens']['spacing']['gap'] ?? '1.5rem' );
		$gap_control = wpae_elementor_ir_dimension_control( $component_gap, 'rem', 1.5 );
		$settings['flex_gap'] = [ 'unit' => $gap_control['unit'], 'size' => $gap_control['size'], 'column' => (string) $gap_control['size'], 'row' => (string) $gap_control['size'], 'isLinked' => true ];
		$settings['flex_gap_mobile'] = $settings['flex_gap'];
		if ( in_array( $role, [ 'about', 'hero', 'process', 'pricing', 'faq', 'benefits', 'services', 'team', 'testimonials', 'cta' ], true ) ) {
			$section_spacing = $token_values['space.section'] ?? $token_values['space.component'] ?? ( $tokens['native_tokens']['spacing']['section_desktop'] ?? '4.5rem' );
			$mobile_spacing = isset( $token_values['space.section'] ) ? ( $tokens['native_tokens']['spacing']['section_mobile'] ?? '2rem' ) : $section_spacing;
			$desktop_padding = wpae_elementor_ir_dimension_control( $section_spacing, 'rem', 4.5, false );
			$mobile_padding = wpae_elementor_ir_dimension_control( $mobile_spacing, 'rem', 2, false );
			$desktop_padding['left'] = $desktop_padding['right'] = '2';
			$mobile_padding['left'] = $mobile_padding['right'] = '1';
			// The side values now differ from the uniform slider, so keep the
			// native dimensions control unambiguous and let its sides drive CSS.
			unset( $desktop_padding['size'], $mobile_padding['size'] );
			$settings['padding'] = $desktop_padding;
			$settings['padding_tablet'] = $desktop_padding;
			$settings['padding_mobile'] = $mobile_padding;
		}
		if ( $role === 'services' ) {
			$settings['content_width'] = 'boxed';
			$settings['boxed_width'] = [ 'unit' => 'px', 'size' => 1200, 'sizes' => [] ];
		}
		$surface_contract_version = (int) ( $node['visual_policy']['item_surface']['contract_version'] ?? 0 );
		$surface_is_explicit_section = in_array( $role, [ 'pricing', 'faq', 'faq_surface' ], true ) || ! empty( $node['layout_constraints']['surface_override'] );
		if ( isset( $token_values['color.surface'] ) && ( $surface_contract_version < 2 || $surface_is_explicit_section ) ) {
			$settings['background_color'] = $token_values['color.surface'];
		}
		$surface_override = strtolower( trim( (string) ( $node['layout_constraints']['surface_override'] ?? '' ) ) );
		if ( $role === 'media_group' && ( $node['layout_constraints']['media_surface_mode'] ?? '' ) === 'transparent' ) {
			$settings['background_color'] = 'transparent';
			$settings['background_background'] = 'classic';
		}
		if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
			$settings['background_color'] = $surface_override;
			$report['tokens']['resolved'][] = [ 'token' => 'explicit.surface', 'value' => $surface_override, 'source' => 'prompt' ];
		}
		if ( in_array( $role, [ 'copy_group', 'service_cards', 'team_cards', 'testimonial_cards', 'cta_copy_group', 'cta_actions', 'services_photo_grid', 'services_editorial_rows', 'services_text_icon_list' ], true ) ) {
			$settings['background_color'] = 'transparent';
			$settings['background_background'] = 'classic';
		}
		if ( $role === 'faq_surface' ) {
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( (string) ( $node['layout_constraints']['border_radius'] ?? '12px' ), 'px', 12 );
			$settings['border_border'] = 'solid';
			$settings['border_width'] = wpae_elementor_ir_dimension_control( '1px', 'px', 1 );
			$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
			$settings['padding'] = wpae_elementor_ir_dimension_control( $token_values['space.component'] ?? '1rem', 'rem', 1, false );
			$settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '0.75', 'right' => '0.5', 'bottom' => '0.75', 'left' => '0.5', 'isLinked' => false, 'sizes' => [] ];
			if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
				$settings['background_color'] = $surface_override;
			}
		}
		if ( $role === 'feature_cards' ) {
			$settings['background_color'] = 'transparent';
			$settings['background_background'] = 'classic';
		}
		if ( $role === 'pricing_cards' && empty( $node['visual_policy'] ) ) {
			// Pricing cards are a row on wide viewports and a stack on mobile.
			// The child width contract below then supplies equal desktop columns.
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'row';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_wrap_tablet'] = 'wrap';
			$settings['flex_wrap_mobile'] = 'wrap';
		}
		if ( $role === 'feature_cards' ) {
			$settings['_css_classes'] = 'wpae-feature-cards';
			if ( empty( $policy['collection'] ) ) {
				$settings['flex_direction'] = 'row';
				$settings['flex_direction_tablet'] = 'column';
				$settings['flex_direction_mobile'] = 'column';
				$settings['flex_wrap'] = 'wrap';
				$settings['flex_wrap_tablet'] = 'nowrap';
				$settings['flex_wrap_mobile'] = 'nowrap';
			}
		}
		if ( in_array( $role, [ 'service_cards', 'team_cards', 'testimonial_cards' ], true ) ) {
			$settings['_css_classes'] = 'wpae-' . $role;
			if ( empty( $policy['collection'] ) ) {
				$settings['flex_direction'] = 'row';
				$settings['flex_direction_tablet'] = 'column';
				$settings['flex_direction_mobile'] = 'column';
				$settings['flex_wrap'] = 'wrap';
				$settings['flex_wrap_tablet'] = 'nowrap';
				$settings['flex_wrap_mobile'] = 'nowrap';
				$settings['flex_align_items'] = 'stretch';
			}
		}
		if ( $role === 'service_cards' && empty( $policy['collection'] ) ) {
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 24, 'column' => '24', 'row' => '24', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = [ 'unit' => 'px', 'size' => 20, 'column' => '20', 'row' => '20', 'isLinked' => true ];
		}
		if ( $role === 'services_photo_grid' ) {
			$settings['_css_classes'] = 'wpae-services-photo-grid';
			if ( empty( $policy['collection'] ) ) {
				// Preserve the pre-policy contract for historical typed operations.
				$settings['flex_direction'] = 'row';
				$settings['flex_direction_tablet'] = 'row';
				$settings['flex_direction_mobile'] = 'column';
				$settings['flex_wrap'] = 'wrap';
				$settings['flex_wrap_tablet'] = 'wrap';
				$settings['flex_wrap_mobile'] = 'nowrap';
				$settings['flex_align_items'] = 'stretch';
				$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 24, 'column' => '24', 'row' => '24', 'isLinked' => true ];
				$settings['flex_gap_tablet'] = [ 'unit' => 'px', 'size' => 20, 'column' => '20', 'row' => '20', 'isLinked' => true ];
				$settings['flex_gap_mobile'] = [ 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16', 'isLinked' => true ];
			}
		}
		if ( $role === 'services_icon_cards' ) {
			$settings['_css_classes'] = 'wpae-services-icon-cards';
		}
		if ( $role === 'services_split_lead' ) {
			$settings['_css_classes'] = 'wpae-services-split-lead';
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'column';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_wrap_tablet'] = 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			$settings['flex_align_items'] = 'center';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 32, 'column' => '32', 'row' => '32', 'isLinked' => true ];
			$settings['flex_gap_tablet'] = [ 'unit' => 'px', 'size' => 24, 'column' => '24', 'row' => '24', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = [ 'unit' => 'px', 'size' => 20, 'column' => '20', 'row' => '20', 'isLinked' => true ];
		}
		if ( $role === 'services_editorial_rows' || $role === 'services_text_icon_list' ) {
			$settings['_css_classes'] = $role === 'services_editorial_rows' ? 'wpae-services-editorial-rows' : 'wpae-services-text-icon-list';
			$settings['flex_direction'] = 'column';
			$settings['flex_direction_tablet'] = 'column';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_wrap_tablet'] = 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			// Child dividers and row padding own editorial spacing; a second
			// container gap would double the vertical rhythm around each rule.
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( $role === 'services_editorial_row' ) {
			$settings['_css_classes'] = 'wpae-services-editorial-row';
			$settings['flex_direction'] = 'column';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12', 'isLinked' => true ];
			$settings['background_background'] = 'classic';
			if ( $surface_override !== '' ) {
				$settings['background_color'] = $surface_override;
				$settings['padding'] = [ 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => false ];
				$settings['border_radius'] = wpae_elementor_ir_dimension_control( '12px', 'px', 12 );
			} else {
				// Default editorial rows stay on the page surface and use the
				// adjacent native divider instead of an unpadded white bar.
				$settings['background_color'] = 'transparent';
				$settings['padding'] = [ 'unit' => 'px', 'top' => '16', 'right' => '0', 'bottom' => '16', 'left' => '0', 'isLinked' => false ];
			}
		}
		if ( in_array( $role, [ 'services_text_icon_row', 'feature_row' ], true ) ) {
			$settings['_css_classes'] = $role === 'feature_row' ? 'wpae-benefits-list-row' : 'wpae-services-text-icon-row';
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'row';
			$settings['flex_direction_mobile'] = 'row';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			$settings['flex_align_items'] = 'flex-start';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = [ 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12', 'isLinked' => true ];
			if ( $surface_contract_version < 2 ) {
				$settings['background_background'] = 'classic';
				if ( $surface_override !== '' ) {
					$settings['background_color'] = $surface_override;
					$settings['padding'] = [ 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => false ];
					$settings['border_radius'] = wpae_elementor_ir_dimension_control( '12px', 'px', 12 );
				} else {
					// Frozen legacy rows keep their pre-v2 transparent divider box.
					$settings['background_color'] = 'transparent';
					$settings['padding'] = [ 'unit' => 'px', 'top' => '16', 'right' => '0', 'bottom' => '16', 'left' => '0', 'isLinked' => false ];
				}
			}
		}
		if ( in_array( $role, [ 'services_split_copy', 'services_editorial_copy', 'services_text_icon_copy' ], true ) ) {
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 8, 'column' => '8', 'row' => '8', 'isLinked' => true ];
			$settings['flex_gap_tablet'] = $settings['flex_gap'];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( $role === 'services_split_lead' ) {
			if ( $surface_contract_version < 2 ) {
				$settings['background_background'] = 'classic';
				if ( $surface_override === '' ) {
					$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
				}
				$settings['border_radius'] = wpae_elementor_ir_dimension_control( (string) ( $node['layout_constraints']['border_radius'] ?? '16px' ), 'px', 16 );
				$settings['padding'] = [ 'unit' => 'px', 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'isLinked' => false ];
				$settings['padding_tablet'] = $settings['padding'];
				$settings['padding_mobile'] = [ 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => false ];
			}
		}
		if ( $role === 'cta' ) {
			$settings['_css_classes'] = 'wpae-cta-section';
			$settings['flex_align_items'] = 'center';
			$cta_layout = (array) ( $node['visual_policy']['cta'] ?? [] );
			if ( ! empty( $cta_layout['direction'] ) ) {
				$settings['flex_direction'] = (string) $cta_layout['direction']['desktop'];
				$settings['flex_direction_tablet'] = (string) $cta_layout['direction']['tablet'];
				$settings['flex_direction_mobile'] = (string) $cta_layout['direction']['mobile'];
				$settings['flex_wrap'] = 'nowrap';
				$settings['flex_wrap_tablet'] = 'nowrap'; $settings['flex_wrap_mobile'] = 'nowrap';
				foreach ( [ '' => 'desktop', '_tablet' => 'tablet', '_mobile' => 'mobile' ] as $suffix => $device ) {
					$gap = wpae_elementor_ir_dimension_control( $cta_layout['copy_actions_gap'][$device] ?? '1rem', 'rem', 1 );
					$settings[ 'flex_gap' . $suffix ] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
				}
			} else {
				$settings['flex_direction'] = $cta_has_media ? 'row' : 'column';
				if ( $cta_has_media ) { $settings['flex_direction_tablet'] = 'column'; $settings['flex_direction_mobile'] = 'column'; $settings['flex_wrap'] = 'nowrap'; $settings['flex_wrap_tablet'] = 'nowrap'; $settings['flex_wrap_mobile'] = 'nowrap'; }
			}
		}
		if ( $role === 'cta_actions' ) {
			$settings['_css_classes'] = 'wpae-cta-actions';
			$actions_direction = (array) ( $node['layout_constraints']['actions_direction'] ?? [] );
			$settings['flex_direction'] = (string) ( $actions_direction['desktop'] ?? 'row' );
			$settings['flex_direction_tablet'] = (string) ( $actions_direction['tablet'] ?? 'row' );
			$settings['flex_direction_mobile'] = (string) ( $actions_direction['mobile'] ?? 'column' );
			$settings['flex_wrap'] = $settings['flex_direction'] === 'row' ? 'wrap' : 'nowrap';
			$settings['flex_wrap_tablet'] = $settings['flex_direction_tablet'] === 'row' ? 'wrap' : 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			$actions_align = sanitize_key( (string) ( $node['layout_constraints']['actions_align'] ?? 'start' ) );
			$actions_align = in_array( $actions_align, [ 'start', 'center', 'end' ], true ) ? $actions_align : 'start';
			$actions_alignment_control = [ 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' ][ $actions_align ];
			$actions_direction_by_device = [ 'desktop' => $settings['flex_direction'], 'tablet' => $settings['flex_direction_tablet'], 'mobile' => $settings['flex_direction_mobile'] ];
			foreach ( [ '' => 'desktop', '_tablet' => 'tablet', '_mobile' => 'mobile' ] as $suffix => $device ) {
				if ( $actions_direction_by_device[ $device ] === 'row' ) {
					// Alignment is along the action group's main axis; for a horizontal
					// row that means justify-content, while buttons stay vertically centered.
					$settings[ 'flex_justify_content' . $suffix ] = $actions_alignment_control;
					$settings[ 'flex_align_items' . $suffix ] = 'center';
				} else {
					$settings[ 'flex_align_items' . $suffix ] = $actions_alignment_control;
				}
			}
			$actions_gap = (array) ( $node['layout_constraints']['actions_gap'] ?? [] );
			foreach ( [ '' => [ 'device' => 'desktop', 'fallback' => '0.875rem' ], '_tablet' => [ 'device' => 'tablet', 'fallback' => '0.875rem' ], '_mobile' => [ 'device' => 'mobile', 'fallback' => '0.75rem' ] ] as $suffix => $device_config ) {
				$gap = wpae_elementor_ir_dimension_control( $actions_gap[$device_config['device']] ?? $device_config['fallback'], 'rem', 0.75 );
				$settings[ 'flex_gap' . $suffix ] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
			}
		}
		if ( in_array( $role, [ 'hero', 'about' ], true ) && ( $node['layout_constraints']['media_side'] ?? 'right' ) === 'left' && array_filter( (array) ( $node['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'media_group' ) ) {
			$settings['flex_direction_mobile'] = 'column-reverse';
		}
		if ( $role === 'cta' && $cta_has_media && ( $node['layout_constraints']['media_side'] ?? 'right' ) === 'left' ) {
			$settings['flex_direction_mobile'] = 'column-reverse';
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
			if ( $surface_contract_version < 2 ) {
				// Preserve pre-existing frozen legacy Process layouts. Canonical
				// Process contracts always delegate the complete box to the owner
				// surface translator after structural role compilation.
				$settings['background_color'] = '#ffffff';
				$settings['border_border'] = 'solid';
				$settings['border_color'] = '#dbe3f0';
				$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
				$settings['border_radius'] = [ 'unit' => 'px', 'size' => 0.75, 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => true ];
				$settings['padding'] = [ 'unit' => 'rem', 'top' => '1', 'right' => '0.75', 'bottom' => '1.25', 'left' => '0.75', 'isLinked' => true ];
				$settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '1', 'right' => '0.75', 'bottom' => '1', 'left' => '0.75', 'isLinked' => true ];
			}
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
		if ( $role === 'services_badge' ) {
			// Match the user-corrected Services reference: a compact white outlined pill.
			$settings['_css_classes'] = 'wpae-generated-badge';
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_justify_content'] = 'center';
			$settings['flex_align_items'] = 'center';
			$settings['background_background'] = 'classic';
			$settings['background_color'] = '#ffffff';
			$settings['border_border'] = 'solid';
			$settings['border_color'] = '#6b7280';
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ];
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( '999px', 'px', 999 );
			$settings['padding'] = [ 'unit' => 'rem', 'top' => '0.5', 'right' => '1.75', 'bottom' => '0.5', 'left' => '1.75', 'isLinked' => false, 'sizes' => [] ];
			$settings['align_self'] = 'flex-start';
			$settings['align_self_tablet'] = 'flex-start';
			$settings['align_self_mobile'] = 'flex-start';
			$settings['_element_width'] = 'initial';
			$settings['_element_width_tablet'] = 'initial';
			$settings['_element_width_mobile'] = 'initial';
			$settings['_flex_grow'] = 0;
			$settings['_flex_grow_tablet'] = 0;
			$settings['_flex_grow_mobile'] = 0;
			$settings['_flex_shrink'] = 0;
			$settings['_flex_shrink_tablet'] = 0;
			$settings['_flex_shrink_mobile'] = 0;
			$settings['custom_css'] = 'selector { width: fit-content; max-width: 100%; align-self: flex-start; flex: 0 0 auto; }';
		}
		if ( in_array( $role, [ 'pricing_card', 'feature_card', 'service_card', 'team_card', 'testimonial_card', 'services_photo_card', 'services_icon_card' ], true ) ) {
			$settings['border_border'] = 'solid';
			$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( $token_values['radius.card'] ?? '0.5rem', 'rem', 0.5 );
			$settings['padding'] = wpae_elementor_ir_dimension_control( $token_values['space.component'] ?? '1.5rem', 'rem', 1.5, false );
			$settings['padding_tablet'] = $settings['padding'];
			$settings['padding_mobile'] = wpae_elementor_ir_dimension_control( $token_values['space.component'] ?? '1.25rem', 'rem', 1.25, false );
		}
		if ( $role === 'pricing_card' ) {
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_justify_content'] = 'space-between';
			$settings['flex_justify_content_mobile'] = 'flex-start';
		}
		if ( $role === 'feature_card' ) {
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
		}
		if ( in_array( $role, [ 'service_card', 'team_card', 'testimonial_card' ], true ) ) {
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_wrap'] = 'nowrap';
			$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.5, 'column' => '0.5', 'row' => '0.5', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( $role === 'service_card' ) {
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$card_radius = $node['layout_constraints']['border_radius'] ?? '16px';
			$mobile_radius = $node['layout_constraints']['border_radius_mobile'] ?? $card_radius;
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( $card_radius, 'px', 16 );
			$settings['border_radius_mobile'] = wpae_elementor_ir_dimension_control( $mobile_radius, 'px', 16 );
			$settings['padding'] = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
			$settings['padding_tablet'] = $settings['padding'];
			$settings['padding_mobile'] = $settings['padding'];
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
			$settings['overflow'] = 'hidden';
		}
		if ( $role === 'services_photo_card' ) {
			$settings['_css_classes'] = 'wpae-services-photo-card';
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( (string) ( $node['layout_constraints']['border_radius'] ?? '16px' ), 'px', 16 );
			$settings['border_radius_mobile'] = wpae_elementor_ir_dimension_control( (string) ( $node['layout_constraints']['border_radius_mobile'] ?? '16px' ), 'px', 16 );
			$settings['padding'] = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
			$settings['padding_tablet'] = $settings['padding'];
			$settings['padding_mobile'] = $settings['padding'];
			$settings['overflow'] = 'hidden';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( $role === 'services_icon_card' ) {
			$settings['_css_classes'] = 'wpae-services-icon-card';
		}
		if ( $role === 'services_photo_panel' ) {
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['padding'] = [ 'unit' => 'px', 'top' => '24', 'right' => '0', 'bottom' => '24', 'left' => '0', 'isLinked' => false ];
			$settings['padding_tablet'] = $settings['padding'];
			$settings['padding_mobile'] = [ 'unit' => 'px', 'top' => '20', 'right' => '0', 'bottom' => '20', 'left' => '0', 'isLinked' => false ];
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 10, 'column' => '10', 'row' => '10', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( $role === 'services_split_image_panel' ) {
			$settings['_css_classes'] = 'wpae-services-split-image-panel';
			$settings['background_color'] = 'transparent';
			$settings['border_radius'] = wpae_elementor_ir_dimension_control( '16px', 'px', 16 );
			$settings['overflow'] = 'hidden';
			$settings['padding'] = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
			$settings['padding_mobile'] = $settings['padding'];
		}
		if ( $role === 'service_content' ) {
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['padding'] = [ 'unit' => 'px', 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'isLinked' => true ];
			$settings['padding_tablet'] = $settings['padding'];
			$settings['padding_mobile'] = [ 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => true ];
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 8, 'column' => '8', 'row' => '8', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( $role === 'testimonial_card' ) {
			$settings['flex_justify_content'] = 'space-between';
			$settings['flex_justify_content_mobile'] = 'flex-start';
		}
		if ( in_array( $role, [ 'copy_group', 'cta_copy_group' ], true ) ) {
			$text_alignment = sanitize_key( (string) ( $node['layout_constraints']['text_align'] ?? 'left' ) );
			$container_alignment = sanitize_key( (string) ( $node['layout_constraints']['container_align'] ?? $text_alignment ) );
			$container_alignment = [ 'left' => 'start', 'right' => 'end' ][ $container_alignment ] ?? $container_alignment;
			$container_alignment = in_array( $container_alignment, [ 'start', 'center', 'end' ], true ) ? $container_alignment : 'start';
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = [ 'center' => 'center', 'end' => 'flex-end' ][ $container_alignment ] ?? 'flex-start';
			$settings['text_align'] = in_array( $text_alignment, [ 'left', 'center', 'right' ], true ) ? $text_alignment : 'left';
			if ( $role === 'cta_copy_group' ) {
				$settings['_css_classes'] = 'wpae-cta-copy';
				$settings['flex_gap'] = [ 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ];
				$settings['flex_gap_tablet'] = $settings['flex_gap']; $settings['flex_gap_mobile'] = $settings['flex_gap'];
			}
		}
		if ( $role === 'pricing_details' ) {
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.75, 'column' => '0.75', 'row' => '0.75', 'isLinked' => true ];
		}
		if ( $role === 'pricing_price_group' && empty( $node['visual_policy']['inline_value'] ) ) {
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_align_items'] = 'center';
			$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.25, 'column' => '0.25', 'row' => '0.25', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
		}
		if ( in_array( $role, [ 'pricing_badge', 'hero_badge' ], true ) ) {
			$badge_container_alignment = sanitize_key( (string) ( $node['layout_constraints']['container_align'] ?? 'start' ) );
			$badge_container_alignment = [ 'left' => 'start', 'right' => 'end' ][ $badge_container_alignment ] ?? $badge_container_alignment;
			$badge_align_self = [ 'center' => 'center', 'end' => 'flex-end' ][ $badge_container_alignment ] ?? 'flex-start';
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
			$settings['align_self'] = $badge_align_self;
			$settings['align_self_tablet'] = $badge_align_self;
			$settings['align_self_mobile'] = $badge_align_self;
			$settings['_element_width'] = 'initial';
			$settings['_element_width_tablet'] = 'initial';
			$settings['_element_width_mobile'] = 'initial';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
			$settings['custom_css'] = 'selector { width: fit-content; max-width: 100%; align-self: ' . $badge_align_self . '; flex: 0 0 auto; }';
			$settings['_css_classes'] = 'wpae-generated-badge';
		}
	} elseif ( $widget_type === 'heading' ) {
		$item = $content_map[ sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) ) ] ?? [];
		$settings['title'] = (string) ( $item['exact_text'] ?? $node['layout_constraints']['literal_text'] ?? '' );
		$heading_alignment = sanitize_key( (string) ( $node['layout_constraints']['text_align'] ?? '' ) );
		if ( in_array( $heading_alignment, [ 'left', 'center', 'right' ], true ) ) {
			$settings['align'] = $heading_alignment;
		}
		$settings['header_size'] = [ 'process_number' => 'h6', 'process_badge_label' => 'h6', 'services_badge_label' => 'h6', 'eyebrow_badge_label' => 'h6', 'brand' => 'h6', 'eyebrow' => 'h6', 'pricing_label' => 'h4', 'pricing_price' => 'h2', 'cta_section_title' => 'h2', 'service_title' => 'h3', 'team_name' => 'h3', 'testimonial_author' => 'h3' ][ $role ] ?? ( $role === 'title' ? ( str_contains( (string) ( $node['node_id'] ?? '' ), '-card-' ) ? 'h3' : 'h1' ) : 'h3' );
		$semantic_heading = (string) ( $node['layout_constraints']['heading_level'] ?? '' );
		if ( in_array( $semantic_heading, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
			$settings['header_size'] = $semantic_heading;
		}
		$settings['title_color'] = in_array( $role, [ 'process_number', 'process_badge_label' ], true ) ? (string) ( $token_values['color.surface'] ?? '#ffffff' ) : (string) ( $token_values[ $role === 'brand' ? 'color.muted' : 'color.text' ] ?? '#111827' );
		$type_token = is_array( $token_values['type.display'] ?? null ) && ! in_array( $role, [ 'eyebrow', 'brand', 'feature_title', 'pricing_label', 'process_number', 'process_badge_label', 'cta_section_title', 'service_title', 'team_name', 'testimonial_author' ], true ) ? $token_values['type.display'] : ( $token_values['type.body'] ?? [] );
		if ( empty( $node['visual_policy'] ) && is_array( $type_token ) ) {
			$settings = array_merge( $settings, wpae_elementor_ir_type_settings( $type_token ) );
		}
		if ( empty( $node['visual_policy'] ) ) {
		if ( $role === 'services_recipe_title' ) {
			// A Services item title must read as a heading even when the site's
			// global typography gives h3 and body copy the same default size.
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.125, 'sizes' => [] ];
			$settings['typography_font_size_tablet'] = $settings['typography_font_size'];
			$settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 1.0625, 'sizes' => [] ];
			$settings['typography_font_weight'] = '600';
			$settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.3, 'sizes' => [] ];
		} elseif ( $role === 'feature_title' ) {
			$settings['align'] = 'left';
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.125, 'sizes' => [] ];
			$settings['typography_font_size_tablet'] = $settings['typography_font_size'];
			$settings['typography_font_size_mobile'] = $settings['typography_font_size'];
			$settings['typography_font_weight'] = '600';
		} elseif ( $role === 'cta_section_title' ) {
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.75, 'sizes' => [] ];
			$settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1.625, 'sizes' => [] ];
			$settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 1.5, 'sizes' => [] ];
			$settings['typography_font_weight'] = '700';
			$settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.2, 'sizes' => [] ];
		} elseif ( in_array( $role, [ 'service_title', 'team_name' ], true ) ) {
			$settings['align'] = 'left';
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.125, 'sizes' => [] ];
			$settings['typography_font_size_tablet'] = $settings['typography_font_size'];
			$settings['typography_font_size_mobile'] = $settings['typography_font_size'];
			$settings['typography_font_weight'] = '600';
		} elseif ( $role === 'testimonial_author' ) {
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ];
			$settings['typography_font_size_tablet'] = $settings['typography_font_size'];
			$settings['typography_font_size_mobile'] = $settings['typography_font_size'];
			$settings['typography_font_weight'] = '700';
		} elseif ( $role === 'pricing_label' ) {
			$settings['typography_font_weight'] = '700';
		} elseif ( $role === 'pricing_price' ) {
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 2, 'sizes' => [] ];
			$settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1.8, 'sizes' => [] ];
			$settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 1.6, 'sizes' => [] ];
			$settings['typography_font_weight'] = '800';
		}
		}
		if ( $role === 'process_number' ) {
			$settings['_css_classes'] = 'wpae-process-marker-label';
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 0.875, 'sizes' => [] ];
			$settings['typography_font_weight'] = '600';
			$settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1, 'sizes' => [] ];
		} elseif ( in_array( $role, [ 'process_badge_label', 'services_badge_label' ], true ) ) {
			$settings['_css_classes'] = 'wpae-generated-badge-label';
			$settings['typography_typography'] = 'custom';
			$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 0.75, 'sizes' => [] ];
			$settings['typography_font_weight'] = '600';
			$settings['typography_text_transform'] = 'uppercase';
			$settings['typography_letter_spacing'] = [ 'unit' => 'em', 'size' => 0.08, 'sizes' => [] ];
			$settings['align_self'] = 'center';
			$settings['_element_width'] = 'initial';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
			$settings['margin'] = [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
		} elseif ( $role === 'eyebrow_badge_label' || ( $role === 'eyebrow' && str_contains( (string) ( $node['node_id'] ?? '' ), 'pricing' ) ) ) {
			// The native badge container owns fill, border, radius, and padding.
			// Keep only the label foreground and typography here.
			$settings['title_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
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
			$semantic_heading = sanitize_key( (string) ( $node['layout_constraints']['heading_level'] ?? '' ) );
			$tag = in_array( $semantic_heading, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true )
				? $semantic_heading
				: ( [ 'brand' => 'h6', 'eyebrow' => 'h6', 'eyebrow_badge_label' => 'h6', 'services_badge_label' => 'h6', 'process_badge_label' => 'h6', 'pricing_badge_label' => 'h6', 'services_section_title' => 'h2', 'services_recipe_title' => 'h3', 'cta_section_title' => 'h2', 'team_name' => 'h3', 'testimonial_author' => 'h3', 'feature_title' => 'h3', 'pricing_label' => 'h4' ][ $role ] ?? 'h3' );
			$settings['editor'] = '<' . $tag . '>' . nl2br( htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ), false ) . '</' . $tag . '>';
			$heading_color = $role === 'brand' ? 'color.muted' : ( $role === 'eyebrow' ? 'color.primary' : 'color.text' );
			$settings['text_color'] = (string) ( $token_values[ $heading_color ] ?? '#111827' );
		} else {
			$settings['editor'] = wpae_elementor_ir_text_editor_content( $text );
			$settings['text_color'] = (string) ( $token_values[ $role === 'testimonial_quote' ? 'color.text' : 'color.muted' ] ?? '#6b7280' );
			$text_alignment = sanitize_key( (string) ( $node['layout_constraints']['text_align'] ?? '' ) );
			if ( in_array( $text_alignment, [ 'left', 'center', 'right' ], true ) ) {
				$settings['align'] = $text_alignment;
			}
			if ( in_array( $role, [ 'feature_body', 'pricing_description' ], true ) ) {
				$settings['align'] = 'left';
			}
			if ( ! empty( $node['visual_policy'] ) ) {
				// Accepted role typography is translated once by visual_controls below.
			} elseif ( is_array( $token_values['type.body'] ?? null ) ) {
				$settings = array_merge( $settings, wpae_elementor_ir_type_settings( $token_values['type.body'] ) );
			} elseif ( $role === 'service_body' ) {
				$settings['typography_typography'] = 'custom';
				$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ];
				$settings['typography_font_size_tablet'] = $settings['typography_font_size'];
				$settings['typography_font_size_mobile'] = $settings['typography_font_size'];
				$settings['typography_font_weight'] = '400';
				$settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.6, 'sizes' => [] ];
			}
		}
	} elseif ( $widget_type === 'button' ) {
		$item = $content_map[ sanitize_key( (string) ( $node['content_refs'][0] ?? '' ) ) ] ?? [];
		$settings['text'] = (string) ( $item['exact_text'] ?? '' );
		$settings['link'] = [ 'url' => (string) ( $item['url'] ?? ( $node['layout_constraints']['fallback_url'] ?? '' ) ), 'is_external' => '', 'nofollow' => '' ];
		if ( $role === 'cta_secondary' ) {
			$settings['background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['button_text_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['button_background_hover_color'] = (string) ( $token_values['color.hover'] ?? $token_values['color.primary'] ?? '#4460EC' );
			$settings['hover_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['border_border'] = 'solid';
			$settings['border_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['button_hover_border_color'] = (string) ( $token_values['color.hover'] ?? $token_values['color.primary'] ?? '#4460EC' );
			$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
		} else {
			$settings['background_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
			$settings['button_text_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['button_background_hover_color'] = (string) ( $token_values['color.hover'] ?? $token_values['color.primary'] ?? '#4460EC' );
			$settings['hover_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
			$settings['button_hover_border_color'] = (string) ( $token_values['color.hover'] ?? $token_values['color.primary'] ?? '#4460EC' );
		}
	} elseif ( $widget_type === 'image' ) {
		$media = $media_map[ sanitize_key( (string) ( $node['media_refs'][0] ?? '' ) ) ] ?? [];
		$render = is_array( $media['render'] ?? null ) ? $media['render'] : [];
		$has_render_policy = ! empty( $render['width'] ) || ! empty( $render['height'] ) || in_array( $render['object_fit'] ?? '', [ 'cover', 'contain' ], true ) || is_array( $render['focal_point'] ?? null ) || in_array( $render['shape'] ?? '', [ 'rounded', 'circle' ], true ) || ! empty( $render['radius_token'] );
		$source_url = trim( (string) ( $media['source_url'] ?? '' ) );
		$source_parts = parse_url( $source_url );
		$valid_source = absint( $media['attachment_id'] ?? 0 ) > 0 || ( is_array( $source_parts ) && in_array( strtolower( (string) ( $source_parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) && trim( (string) ( $source_parts['host'] ?? '' ) ) !== '' );
		if ( ! $valid_source ) {
			$report['errors'][] = [ 'node_id' => sanitize_key( (string) ( $node['node_id'] ?? '' ) ), 'widget_type' => 'image', 'reason' => 'media_asset_missing_or_invalid' ];
			return [];
		}
		if ( in_array( $role, [ 'service_image', 'services_photo_image', 'services_split_image' ], true ) && is_array( $source_parts ) && strtolower( (string) ( $source_parts['host'] ?? '' ) ) === 'images.unsplash.com' ) {
			$query = [];
			parse_str( (string) ( $source_parts['query'] ?? '' ), $query );
			$query['fit'] = 'crop';
			$query['w'] = 1200;
			$query['h'] = 900;
			$source_url = ( (string) ( $source_parts['scheme'] ?? 'https' ) ) . '://' . (string) $source_parts['host'] . (string) ( $source_parts['path'] ?? '' ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}
		$settings['image'] = [ 'url' => $source_url, 'id' => wpae_elementor_ir_image_attachment_id_for_alt( $media ), 'alt' => (string) ( $media['alt'] ?? '' ) ];
		$settings['image_size'] = 'full';
		$settings['width'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
		$settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
		if ( $has_render_policy ) {
			foreach ( [ 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ] as $device => $suffix ) {
				foreach ( [ 'width', 'height' ] as $dimension_name ) {
					$dimension_value = trim( (string) ( $render[$dimension_name][$device] ?? '' ) );
					$dimension = wpae_elementor_ir_media_dimension( $dimension_value );
					if ( $dimension ) { $settings[ $dimension_name . $suffix ] = $dimension; }
				}
				// Elementor applies Image widget height to the <img>, but a 100%
				// height cannot resolve while the widget remains an auto-sized flex
				// item. When the accepted Plan says the image fills its stretched
				// media track, make that native widget grow on the column axis so the
				// image's existing height/object-fit controls have a definite frame.
				if ( ( $render['height'][$device] ?? '' ) === '100%' ) {
					$settings[ '_flex_size' . $suffix ] = 'grow';
					$settings[ '_flex_grow' . $suffix ] = 1;
					$settings[ '_flex_shrink' . $suffix ] = 1;
				}
			}
			if ( in_array( $render['object_fit'] ?? '', [ 'cover', 'contain' ], true ) ) { $settings['object-fit'] = (string) $render['object_fit']; }
			$focal_position = wpae_elementor_ir_focal_position( $render['focal_point'] ?? null );
			if ( $focal_position !== '' ) { $settings['object-position'] = $focal_position; }
			$shape = sanitize_key( (string) ( $render['shape'] ?? 'natural' ) );
			if ( $shape === 'circle' ) {
				$settings['image_border_radius'] = [ 'unit' => '%', 'top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'isLinked' => true ];
				$settings['image_border_radius_mobile'] = $settings['image_border_radius'];
			} elseif ( $shape === 'rounded' ) {
				$radius_token = function_exists( 'wpae_design_token_ref' ) ? wpae_design_token_ref( (string) ( $render['radius_token'] ?? '' ) ) : strtolower( trim( (string) ( $render['radius_token'] ?? '' ) ) );
				$radius_value = $radius_token !== '' ? (string) ( $token_values[$radius_token] ?? '' ) : '';
				if ( $radius_value !== '' ) {
					$settings['image_border_radius'] = wpae_elementor_ir_dimension_control( $radius_value, 'rem', 0.75 );
					$settings['image_border_radius_mobile'] = $settings['image_border_radius'];
				}
			} else {
				unset( $settings['image_border_radius'], $settings['image_border_radius_mobile'] );
			}
		}
		if ( in_array( $role, [ 'services_photo_image', 'services_split_image' ], true ) ) {
			$settings['height'] = [ 'unit' => 'px', 'size' => 260, 'sizes' => [] ];
			$settings['height_tablet'] = [ 'unit' => 'px', 'size' => 240, 'sizes' => [] ];
			$settings['height_mobile'] = [ 'unit' => 'px', 'size' => 220, 'sizes' => [] ];
			$settings['object-fit'] = 'cover';
			$focal_position = wpae_elementor_ir_focal_position( $media['focal_point'] ?? null );
			if ( $focal_position !== '' ) { $settings['object-position'] = $focal_position; }
		}
		if ( ! $has_render_policy ) {
			$settings['image_border_radius'] = $role === 'service_image'
				? wpae_elementor_ir_service_image_radius( (string) ( $node['layout_constraints']['card_border_radius'] ?? '16px' ) )
				: wpae_elementor_ir_dimension_control( $token_values['radius.card'] ?? '0.5rem', 'rem', 0.5 );
			if ( in_array( $role, [ 'service_image', 'services_photo_image' ], true ) ) {
				$settings['image_border_radius_mobile'] = wpae_elementor_ir_service_image_radius( (string) ( $node['layout_constraints']['card_border_radius_mobile'] ?? $node['layout_constraints']['card_border_radius'] ?? '16px' ) );
			}
		}
		if ( ! $has_render_policy && in_array( (string) ( $media['object_fit'] ?? '' ), [ 'cover', 'contain', 'fill' ], true ) ) {
			$settings['object-fit'] = (string) $media['object_fit'];
		} elseif ( ! $has_render_policy && $role === 'service_image' ) {
			$settings['object-fit'] = 'cover';
		}
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
		} elseif ( in_array( ( $node['layout_constraints']['reference'] ?? '' ), [ 'services-editorial-row-v1', 'services-text-icon-list-v1' ], true ) ) {
			$settings['style'] = 'solid';
			$settings['weight'] = [ 'unit' => 'px', 'size' => 1, 'sizes' => [] ];
			$settings['width'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
			$settings['align'] = 'left';
			$settings['color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
			$settings['gap'] = [ 'unit' => 'px', 'size' => 0, 'sizes' => [] ];
		} else {
			$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
		}
	} elseif ( $widget_type === 'accordion' ) {
		$settings['tabs'] = [];
		foreach ( (array) ( $node['layout_constraints']['items'] ?? [] ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = $content_map[ sanitize_key( (string) ( $item['question_ref'] ?? '' ) ) ] ?? [];
			$answer = $content_map[ sanitize_key( (string) ( $item['answer_ref'] ?? '' ) ) ] ?? [];
			if ( trim( (string) ( $question['exact_text'] ?? '' ) ) === '' || trim( (string) ( $answer['exact_text'] ?? '' ) ) === '' ) {
				continue;
			}
			$settings['tabs'][] = [
				'tab_title' => (string) $question['exact_text'],
				'tab_content' => (string) $answer['exact_text'],
				'_id' => wpae_elementor_ir_id( (string) $node['node_id'] . '-tab-' . ( (int) $index + 1 ), $seed ),
			];
		}
		$settings['selected_icon'] = [ 'value' => 'fas fa-angle-down', 'library' => 'fa-solid' ];
		$settings['selected_active_icon'] = [ 'value' => 'fas fa-angle-up', 'library' => 'fa-solid' ];
		$settings['title_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
		$settings['title_active_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
		$settings['title_hover_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
		$settings['content_color'] = (string) ( $token_values['color.muted'] ?? '#4b5563' );
		$settings['icon_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
		$settings['icon_active_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
		$settings['icon_hover_color'] = (string) ( $token_values['color.text'] ?? '#111827' );
		$settings['border_color'] = (string) ( $token_values['color.border'] ?? '#d1d5db' );
		$settings['border_width'] = [ 'unit' => 'px', 'size' => 0, 'sizes' => [] ];
		$settings['title_background'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
		$settings['content_background_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
		$settings['title_padding'] = [ 'unit' => 'rem', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => false, 'sizes' => [] ];
		$settings['title_padding_mobile'] = [ 'unit' => 'rem', 'top' => '0.875', 'right' => '0.75', 'bottom' => '0.875', 'left' => '0.75', 'isLinked' => false, 'sizes' => [] ];
		$settings['content_padding'] = [ 'unit' => 'rem', 'top' => '0.75', 'right' => '0.75', 'bottom' => '1', 'left' => '0.75', 'isLinked' => false, 'sizes' => [] ];
		$settings['content_padding_mobile'] = [ 'unit' => 'rem', 'top' => '0.5', 'right' => '0.25', 'bottom' => '0.75', 'left' => '0.25', 'isLinked' => false, 'sizes' => [] ];
		$border_color = (string) ( $token_values['color.border'] ?? '#d1d5db' );
		$text_color = (string) ( $token_values['color.text'] ?? '#111827' );
		$focus_color = (string) ( $token_values['color.focus'] ?? '#2563eb' );
		$settings['_css_classes'] = 'wpae-faq-accordion';
		$settings['custom_css'] = "selector .elementor-accordion-item { border: 0; border-bottom: 1px solid {$border_color}; }\nselector .elementor-accordion-item:last-child { border-bottom: 0; }\nselector .elementor-tab-content { border-top: 0; }\nselector .elementor-tab-content > * { max-width: 70ch; }\nselector .elementor-tab-title:focus-visible, selector .elementor-tab-title .elementor-accordion-title:focus-visible { outline: 2px solid {$focus_color}; outline-offset: 2px; border-radius: 2px; }";
	} elseif ( $widget_type === 'icon' ) {
		$settings['selected_icon'] = [ 'value' => 'fas fa-' . sanitize_key( (string) ( $node['layout_constraints']['icon_name'] ?? 'check-circle' ) ), 'library' => 'fa-solid' ];
		$settings['view'] = 'stacked';
		$settings['shape'] = 'circle';
		$settings['size'] = [ 'unit' => 'px', 'size' => 28, 'sizes' => [] ];
		$settings['primary_color'] = (string) ( $token_values['color.primary'] ?? '#4460EC' );
		$settings['secondary_color'] = (string) ( $token_values['color.surface'] ?? '#ffffff' );
		if ( $role === 'feature_icon' ) {
			$settings['align'] = 'left';
		}
		if ( in_array( $role, [ 'services_list_icon', 'feature_list_icon' ], true ) ) {
			$settings['align'] = 'left';
			$settings['size'] = [ 'unit' => 'px', 'size' => 22, 'sizes' => [] ];
			// Elementor's stacked icon uses half-em padding, so 22px produces a
			// 44px circle without relying on a non-native padding control.
			$settings['width'] = [ 'unit' => 'px', 'size' => 44, 'sizes' => [] ];
			$settings['_element_width'] = 'initial';
			$settings['_element_custom_width'] = [ 'unit' => 'px', 'size' => 44, 'sizes' => [] ];
			$settings['_flex_size'] = 'custom';
			$settings['_flex_grow'] = 0;
			$settings['_flex_shrink'] = 0;
		}
	}
	// Extra resolved profile tokens are compiled as native controls, never chosen here.
	if ( empty( $node['visual_policy'] ) && ! empty( $tokens['_wpae_visual_profile'] ) ) {
		if ( $widget_type === 'container' ) {
			foreach ( [ '' => 'space.component', '_tablet' => 'space.component_tablet', '_mobile' => 'space.component_mobile' ] as $suffix => $key ) {
				if ( isset( $tokens[ $key ] ) && ! in_array( $role, [ 'feature_list', 'feature_row', 'feature_list_copy' ], true ) ) {
					$gap = wpae_elementor_ir_dimension_control( $tokens[ $key ], 'rem', 1 );
					$settings[ 'flex_gap' . $suffix ] = [ 'unit' => $gap['unit'], 'size' => $gap['size'], 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true ];
				}
			}
			if ( in_array( $role, [ 'hero', 'about', 'benefits' ], true ) ) {
				foreach ( [ '_tablet' => 'space.section_tablet', '_mobile' => 'space.section_mobile' ] as $suffix => $key ) {
					$padding = wpae_elementor_ir_dimension_control( $tokens[ $key ], 'rem', 2, false );
					$padding['left'] = $padding['right'] = $suffix === '_mobile' ? '1' : '2'; unset( $padding['size'] );
					$settings[ 'padding' . $suffix ] = $padding;
				}
			}
			if ( $role === 'copy_group' && ! empty( $node['layout_constraints']['reading_measure'] ) ) { $settings['content_width'] = 'boxed'; $settings['boxed_width'] = wpae_elementor_ir_dimension_control( $tokens['layout.copy_width'], 'rem', 38 ); $settings['boxed_width_tablet'] = $settings['boxed_width']; $settings['boxed_width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ]; }
			if ( in_array( $role, [ 'feature_card', 'feature_row' ], true ) ) {
				$settings['border_radius'] = wpae_elementor_ir_dimension_control( $tokens['radius.card'], 'rem', 1 );
				$settings['border_border'] = 'solid'; $settings['border_width'] = wpae_elementor_ir_dimension_control( '1px', 'px', 1 ); $settings['border_color'] = $tokens['color.border'];
				$settings['background_color'] = $tokens['color.surface']; $settings['padding'] = wpae_elementor_ir_dimension_control( $tokens['space.card'], 'rem', 1, false );
				$settings['padding_tablet'] = wpae_elementor_ir_dimension_control( $tokens['space.component_tablet'], 'rem', 1, false );
				$settings['padding_mobile'] = wpae_elementor_ir_dimension_control( $tokens['space.component_mobile'], 'rem', 1, false );
			}
		}
		if ( $widget_type === 'heading' && $role === 'feature_title' ) { $settings = array_merge( $settings, wpae_elementor_ir_type_settings( $tokens['type.feature'] ) ); }
	}
	$section_surface_token = (string) ( $node['layout_constraints']['section_surface_token'] ?? '' );
	if ( $widget_type === 'container' && $section_surface_token !== '' && empty( $node['layout_constraints']['surface_override'] ) && array_key_exists( $section_surface_token, $token_values ) ) {
		$section_surface_value = $token_values[$section_surface_token];
		if ( is_string( $section_surface_value ) && preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $section_surface_value ) ) {
			$settings['background_background'] = 'classic';
			$settings['background_color'] = strtolower( $section_surface_value );
			$report['tokens']['resolved'][] = [ 'token' => $section_surface_token, 'value' => strtolower( $section_surface_value ), 'source' => sanitize_key( (string) ( $node['layout_constraints']['section_surface_source'] ?? 'accepted_visual_policy' ) ), 'role' => 'section_surface' ];
		}
	}
	$compiled_children = [];
	foreach ( (array) ( $node['children'] ?? [] ) as $child ) {
		if ( is_array( $child ) ) {
			$compiled_children[] = wpae_elementor_ir_compile_node( $child, $content_map, $media_map, $tokens, $seed, $report );
		}
	}
	if ( $widget_type === 'container' && empty( $node['visual_policy']['collection'] ) && empty( $node['visual_policy']['list_row'] ) && empty( $node['visual_policy']['entity_layout']['tracks'] ) ) {
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
		if ( $role === 'pricing_cards' && count( $compiled_children ) === 2 ) {
			// Reserve room for the native gap; two 50% columns wrap instead of sharing a row.
			$composition_basis = [ 48, 48 ];
		} elseif ( $role === 'pricing_cards' && count( $compiled_children ) === 3 ) {
			// Three percentage columns plus two native gaps otherwise wrap at common desktop widths.
			$composition_basis = [ 31.5, 31.5, 31.5 ];
		}
		if ( $role === 'services_photo_grid' ) {
			$desktop_basis = count( $compiled_children ) === 3 ? 31.5 : 48;
			$composition_basis = array_fill( 0, count( $compiled_children ), $desktop_basis );
		}
		if ( $role === 'services_split_lead' && count( $compiled_children ) === 2 ) {
			$composition_basis = [ 52, 44 ];
		}
		if ( in_array( $role, [ 'service_cards', 'team_cards', 'testimonial_cards' ], true ) ) {
			if ( count( $compiled_children ) === 2 ) {
				$composition_basis = [ 48, 48 ];
			} elseif ( count( $compiled_children ) === 3 ) {
				$composition_basis = [ 31.5, 31.5, 31.5 ];
			} elseif ( count( $compiled_children ) >= 4 ) {
				$composition_basis = array_fill( 0, count( $compiled_children ), 48 );
			}
		}
		if ( $role === 'feature_cards' && count( $compiled_children ) === 2 ) {
			// Two 50% widths plus the native gap exceed the content width and wrap into a column.
			$composition_basis = [ 48, 48 ];
		} elseif ( $role === 'feature_cards' && count( $compiled_children ) === 3 ) {
			$composition_basis = [ 31.5, 31.5, 31.5 ];
		} elseif ( $role === 'feature_cards' && count( $compiled_children ) >= 4 ) {
			$composition_basis = array_fill( 0, count( $compiled_children ), 48 );
		}
		if ( in_array( $role, [ 'about', 'hero', 'cta' ], true ) && ( $node['layout_constraints']['media_side'] ?? 'right' ) === 'left' && count( $composition_basis ) === 2 ) {
			$composition_basis = array_reverse( $composition_basis );
		}
		$composition_matches_children = ! empty( $composition_basis ) && count( $composition_basis ) === count( $compiled_children );
		$default_child_basis = ( $settings['flex_direction'] ?? 'column' ) === 'column' ? 100 : ( count( $compiled_children ) > 0 ? 100 / count( $compiled_children ) : 100 );
		foreach ( $compiled_children as $child_index => &$compiled_child ) {
			if ( ! is_array( $compiled_child ) || ( $compiled_child['elType'] ?? '' ) !== 'container' || ! is_array( $compiled_child['settings'] ?? null ) ) {
				continue;
			}
			$child_role = (string) ( $node['children'][ $child_index ]['role'] ?? '' );
			// A child collection owns its width. Translate its accepted device
			// values here instead of replacing them with the enclosing section's
			// generic composition basis.
			$child_collection = (array) ( $node['children'][ $child_index ]['visual_policy']['collection'] ?? [] );
			if ( $child_collection ) {
				foreach ( [ '' => 'desktop', '_tablet' => 'tablet', '_mobile' => 'mobile' ] as $suffix => $device ) {
					$width = (string) ( $child_collection['width'][$device] ?? '100%' );
					$dimension = str_ends_with( $width, '%' )
						? [ 'unit' => '%', 'size' => (float) rtrim( $width, '%' ), 'sizes' => [] ]
						: [ 'unit' => 'custom', 'size' => 'min(100%, ' . $width . ')', 'sizes' => [] ];
					$child_settings[ 'width' . $suffix ] = $child_settings[ '_element_custom_width' . $suffix ] = $dimension;
					$child_settings[ '_element_width' . $suffix ] = 'initial';
					$child_settings[ '_flex_size' . $suffix ] = 'custom';
					$child_settings[ '_flex_grow' . $suffix ] = $child_settings[ 'flex_grow' . $suffix ] = 0;
					$child_settings[ '_flex_shrink' . $suffix ] = $child_settings[ 'flex_shrink' . $suffix ] = 1;
				}
				continue;
			}
			if ( in_array( $child_role, [ 'pricing_badge', 'hero_badge', 'process_badge', 'services_badge' ], true ) || $role === 'process_marker_row' ) {
				continue;
			}
			$basis = (float) ( $composition_matches_children ? $composition_basis[ $child_index ] : $default_child_basis );
			// When Elementor has no tablet override, its tablet axis inherits the
			// desktop direction. Treat an inherited column as a full-width child;
			// assuming row here incorrectly halves stacked Services wrappers.
			$tablet_direction = (string) ( $settings['flex_direction_tablet'] ?? $settings['flex_direction'] ?? 'column' );
			$tablet_is_stack = $tablet_direction === 'column';
			$tablet_basis = $tablet_is_stack ? 100 : ( $role === 'services_photo_grid' ? 48 : ( count( $compiled_children ) > 0 ? 100 / count( $compiled_children ) : 100 ) );
			if ( $role === 'pricing_cards' && ! $tablet_is_stack && $composition_matches_children ) {
				$tablet_basis = $basis;
			}
			if ( $role === 'services_photo_grid' && $child_index < count( $compiled_children ) ) {
				$tablet_basis = 48;
			}
			if ( ! isset( $node['visual_policy']['list_row'] ) && ( ( $role === 'services_text_icon_row' && $child_role === 'services_text_icon_copy' ) || ( $role === 'feature_row' && $child_role === 'copy_group' ) ) ) {
				$basis = 90;
				$tablet_basis = 90;
			}
			if ( $role === 'cta' && in_array( $child_role, [ 'cta_copy_group', 'cta_actions' ], true ) && ! empty( $node['visual_policy']['cta']['tracks'] ) ) {
				$track_key = sanitize_key( (string) ( $node['children'][ $child_index ]['layout_constraints']['track_key'] ?? ( $child_role === 'cta_actions' ? 'actions' : 'copy' ) ) );
				$tracks = (array) $node['visual_policy']['cta']['tracks'];
				$basis = max( 1, min( 100, (float) ( $tracks[$track_key] ?? 100 ) ) );
				$tablet_basis = ( $settings['flex_direction_tablet'] ?? $settings['flex_direction'] ?? 'column' ) === 'row' ? $basis : 100;
			} elseif ( $role === 'cta' && ! $cta_has_media && empty( $node['visual_policy']['cta']['tracks'] ) && $child_role === 'cta_copy_group' ) {
				// Historical unfrozen CTA plans predate composition-owned tracks.
				// Preserve their established reading width while new canonical records
				// use the accepted policy above.
				$basis = 72;
				$tablet_basis = 84;
			}
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
	if ( $widget_type === 'container' && isset( $node['visual_policy']['list_row'] ) ) {
		foreach ( $compiled_children as $index => &$compiled_child ) {
			$child_role = (string) ( $node['children'][$index]['role'] ?? '' );
			if ( ! in_array( $child_role, [ 'copy_group', 'services_text_icon_copy' ], true ) || ( $compiled_child['elType'] ?? '' ) !== 'container' ) { continue; }
			$copy_settings = &$compiled_child['settings'];
			foreach ( [ '' => 100, '_tablet' => 100, '_mobile' => 100 ] as $suffix => $width ) {
				$dimension = [ 'unit' => '%', 'size' => $width, 'sizes' => [] ];
				$copy_settings[ 'width' . $suffix ] = $copy_settings[ '_element_custom_width' . $suffix ] = $dimension;
				$copy_settings[ '_element_width' . $suffix ] = 'initial';
				$copy_settings[ '_flex_size' . $suffix ] = 'custom';
				$copy_settings[ '_flex_grow' . $suffix ] = $copy_settings[ 'flex_grow' . $suffix ] = 0;
				$copy_settings[ '_flex_shrink' . $suffix ] = $copy_settings[ 'flex_shrink' . $suffix ] = 1;
			}
			unset( $copy_settings );
		}
		unset( $compiled_child );
	}
	if ( $widget_type === 'container' && isset( $node['visual_policy']['entity_layout']['tracks'] ) ) {
		$tracks = $node['visual_policy']['entity_layout']['tracks'];
		foreach ( $compiled_children as $index => &$compiled_child ) {
			$track_role = (string) ( $node['children'][$index]['role'] ?? '' );
			if ( ! in_array( $track_role, [ 'entity_identity', 'entity_copy' ], true ) || ( $compiled_child['elType'] ?? '' ) !== 'container' ) { continue; }
			$percent = $track_role === 'entity_identity' ? (int) $tracks['identity_percent'] : (int) $tracks['copy_percent'];
			$track_settings = &$compiled_child['settings'];
			foreach ( [ '' => $percent, '_tablet' => 100, '_mobile' => 100 ] as $suffix => $width ) {
				$dimension = [ 'unit' => '%', 'size' => $width, 'sizes' => [] ];
				$track_settings[ 'width' . $suffix ] = $track_settings[ '_element_custom_width' . $suffix ] = $dimension;
				$track_settings[ '_element_width' . $suffix ] = 'initial';
				$track_settings[ '_flex_size' . $suffix ] = 'custom';
				$track_settings[ '_flex_grow' . $suffix ] = $track_settings[ 'flex_grow' . $suffix ] = 0;
				$track_settings[ '_flex_shrink' . $suffix ] = $track_settings[ 'flex_shrink' . $suffix ] = 1;
			}
			unset( $track_settings );
		}
		unset( $compiled_child );
	}
	// Elementor emits native percentage --width only for full-width containers.
	// Keep the composition column full and put the profile reading measure in
	// a separate boxed native child; boxed copy columns otherwise render 100%.
	if ( empty( $node['visual_policy'] ) && $widget_type === 'container' && $role === 'copy_group' && ! empty( $tokens['_wpae_visual_profile'] ) && ! empty( $node['layout_constraints']['reading_measure'] ) ) {
		$measure_settings = $settings;
		foreach ( [ '', '_tablet', '_mobile' ] as $suffix ) {
			unset( $settings[ 'boxed_width' . $suffix ] );
		}
		$settings['content_width'] = 'full';
		$compiled_children = [ [
			'id' => wpae_elementor_ir_id( (string) ( $node['node_id'] ?? 'node' ) . '-reading-measure', $seed ),
			'elType' => 'container',
			'settings' => $measure_settings,
			'elements' => $compiled_children,
		] ];
	}
	if ( ! empty( $node['visual_policy'] ) ) {
		$settings = wpae_elementor_ir_visual_controls( $node, $settings );
		$policy = $node['visual_policy'];
		foreach ( $compiled_children as $index => &$compiled_child ) {
			$ir_child = $node['children'][ $index ] ?? [];
			if ( isset( $policy['collection'] ) && is_array( $compiled_child['settings'] ?? null ) ) {
				$collection = (array) $policy['collection'];
				$axis = (string) ( $collection['axis'] ?? 'grid' );
				$implementation = (string) ( $collection['implementation'] ?? 'native_grid' );
				$item_count = max( 1, (int) ( $collection['item_count'] ?? count( $compiled_children ) ) );
				foreach ( [ '' => 'desktop', '_tablet' => 'tablet', '_mobile' => 'mobile' ] as $suffix => $device ) {
					$columns = $axis === 'list' ? 1 : min( $item_count, max( 1, (int) ( $collection['columns'][ $device ] ?? 1 ) ) );
					$dimension = $implementation === 'native_flex_equal' && $axis !== 'list'
						? wpae_elementor_ir_flex_equal_track_dimension( $collection['gap'][ $device ] ?? '1.5rem', $columns )
						: [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
					$item_settings = &$compiled_child['settings'];
					$item_settings[ 'width' . $suffix ] = $item_settings[ '_element_custom_width' . $suffix ] = $dimension;
					$item_settings[ '_element_width' . $suffix ] = 'initial';
					$item_settings[ '_flex_size' . $suffix ] = 'custom';
					$item_settings[ '_flex_grow' . $suffix ] = $item_settings[ 'flex_grow' . $suffix ] = 0;
					$item_settings[ '_flex_shrink' . $suffix ] = $item_settings[ 'flex_shrink' . $suffix ] = $implementation === 'native_flex_equal' && $axis !== 'list' && $columns > 1 ? 0 : 1;
					unset( $item_settings );
				}
			}
			if ( empty( $policy['intro'] ) && ! in_array( $role, [ 'card_actions', 'cta_actions' ], true ) && ( $ir_child['widget_type'] ?? '' ) === 'button' && $index > 0 ) {
				$cta_gap = wpae_elementor_ir_dimension_control( $policy['spacing']['item_cta'], 'rem', 1.25 );
				$copy_gap = wpae_elementor_ir_dimension_control( $policy['spacing']['item_copy'], 'rem', 0.75 );
				$extra = $cta_gap['unit'] === $copy_gap['unit'] ? max( 0, $cta_gap['size'] - $copy_gap['size'] ) : $cta_gap['size'];
				$compiled_child['settings']['_margin'] = [ 'unit' => $cta_gap['unit'], 'top' => (string) $extra, 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ];
			}
			if ( ! empty( $policy['reading_measure'] ) && $index > 0 ) {
				$child_role = (string) ( $ir_child['role'] ?? '' );
				$key = in_array( $child_role, [ 'cta_primary', 'cta_secondary' ], true ) ? 'description_cta' : ( $child_role === 'body' ? 'title_description' : 'eyebrow_title' );
				$gap = wpae_elementor_ir_dimension_control( $policy['spacing'][ $key ], 'rem', 1 );
				$compiled_child['settings']['_margin'] = [ 'unit' => $gap['unit'], 'top' => (string) $gap['size'], 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ];
			}
		}
		unset( $compiled_child );
		if ( ! empty( $policy['reading_measure'] ) ) {
			$measure = wpae_elementor_ir_dimension_control( $policy['reading_measure'], 'rem', 38 );
			// Reading measure is a ceiling inside the selected column, never a minimum width.
			// Elementor's native custom slider unit emits the expression without a unit suffix.
			$measure = [ 'unit' => 'custom', 'size' => 'min(100%, ' . $measure['size'] . $measure['unit'] . ')', 'sizes' => [] ];
			$measure_gap = ! empty( $policy['intro'] ) ? [ 'unit' => 'px', 'size' => 0 ] : wpae_elementor_ir_dimension_control( $policy['spacing']['item_copy'] ?? '0.75rem', 'rem', 0.75 );
			$measure_container_alignment = sanitize_key( (string) ( $policy['container_align'] ?? ( $policy['text_align'] ?? 'start' ) ) );
			$measure_container_alignment = [ 'left' => 'start', 'right' => 'end' ][ $measure_container_alignment ] ?? $measure_container_alignment;
			$measure_container_alignment = in_array( $measure_container_alignment, [ 'start', 'center', 'end' ], true ) ? $measure_container_alignment : 'start';
			$measure_text_alignment = sanitize_key( (string) ( $policy['text_align'] ?? 'left' ) );
			$measure_settings = [ 'background_background' => 'classic', 'background_color' => 'transparent', 'container_type' => 'flex', 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => [ 'unit' => $measure_gap['unit'], 'size' => $measure_gap['size'], 'column' => (string) $measure_gap['size'], 'row' => (string) $measure_gap['size'], 'isLinked' => true ], 'padding' => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ], 'align_self' => [ 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' ][ $measure_container_alignment ], 'flex_align_items' => [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $measure_text_alignment ] ?? 'flex-start', 'width' => $measure, 'width_tablet' => $measure, 'width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ] ];
			$compiled_children = [ [ 'id' => wpae_elementor_ir_id( (string) $node['node_id'] . '-reading-measure', $seed ), 'elType' => 'container', 'settings' => $measure_settings, 'elements' => $compiled_children ] ];
		}
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
	if ( ! empty( $options['resolved_visual']['values'] ) ) { $tokens = $options['resolved_visual']['values']; }
	unset( $tokens['_wpae_visual_profile'] );
	if ( ! empty( $options['resolved_visual']['profile'] ) ) { $tokens['_wpae_visual_profile'] = $options['resolved_visual']['profile']; }
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( is_array( $item ) && ! empty( $item['url_requested'] ) && trim( (string) ( $item['url'] ?? '' ) ) === '' ) {
			return [ 'ok' => false, 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'errors' => [ 'explicit_cta_url_invalid:' . sanitize_key( (string) ( $item['id'] ?? 'cta' ) ) ] ];
		}
	}
	$validation = wpae_elementor_ir_validate( $ir, $brief );
	if ( empty( $validation['ok'] ) ) {
		return [ 'ok' => false, 'errors' => $validation['errors'], 'validation' => $validation ];
	}
	$content_map = wpae_elementor_ir_content_map( $brief );
	$media_map = [];
	foreach ( array_merge( (array) ( $brief['media_references'] ?? [] ), (array) ( $ir['media_references'] ?? [] ) ) as $media ) {
		if ( is_array( $media ) ) {
			$media_map[ sanitize_key( (string) ( $media['asset_id'] ?? '' ) ) ] = $media;
		}
	}
	$report = [ 'schema' => 'wpae-elementor-compile-report-v1', 'downgrades' => [], 'errors' => [], 'warnings' => (array) ( $ir['warnings'] ?? [] ), 'tokens' => [ 'resolved' => [], 'missing' => [], 'fallbacks' => [], 'collisions' => [] ], 'node_count' => 0 ];
	$contrast_context = is_array( $options['resolved_visual']['contrast_context'] ?? null ) ? $options['resolved_visual']['contrast_context'] : [];
	$report['contrast'] = function_exists( 'wpae_design_token_validate_contrast' ) ? wpae_design_token_validate_contrast( $tokens, $contrast_context ) : [ 'ok' => true, 'errors' => [] ];
	if ( empty( $options['resolved_visual'] ) && empty( $report['contrast']['ok'] ) && in_array( 'color.muted_on_color.page_bg', (array) ( $report['contrast']['errors'] ?? [] ), true ) ) {
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
			$compiled_root = wpae_elementor_ir_compile_node( $node, $content_map, $media_map, $tokens, $seed, $report );
            if ( ( ! empty( $brief['canonical_create'] ) || ! empty( $node['visual_policy'] ) ) && ( $compiled_root['elType'] ?? '' ) === 'container' && function_exists( 'wpae_get_design_system_required_classes' ) ) {
                // Resolve mandatory technical markers before the accepted signature.
                // Normalization remains unable to change author classes or controls.
                $markers = array_merge( wpae_get_design_system_required_classes(), [ 'wpae-block' ] );
                $compiled_root['settings']['_css_classes'] = wpae_migrate_design_system_css_classes( $compiled_root['settings']['_css_classes'] ?? '', $markers )['classes'];
                $compiled_root['settings']['_wpae_design_system_id'] = wpae_get_design_system_id();
            }
            $data[] = $compiled_root;
		}
	}
	$native_roundtrip = wpae_native_roundtrip_compile_tree( $data );
	$data = $native_roundtrip['elements'];
	$report['native_roundtrip'] = $native_roundtrip['diagnostics'];
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
