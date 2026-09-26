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

function wpae_elementor_ir_card_nodes( string $role, string $node_id, array $items, array $tokens ): array {
	$cards = [];
	$roles = [
		'service_cards' => [ 'title_ref' => [ 'service_title', 'heading' ], 'body_ref' => [ 'service_body', 'text-editor' ], 'cta_ref' => [ 'service_cta', 'button' ] ],
		'team_cards' => [ 'name_ref' => [ 'team_name', 'heading' ], 'position_ref' => [ 'team_position', 'text-editor' ], 'bio_ref' => [ 'team_bio', 'text-editor' ] ],
		'testimonial_cards' => [ 'quote_ref' => [ 'testimonial_quote', 'text-editor' ], 'author_ref' => [ 'testimonial_author', 'heading' ], 'meta_ref' => [ 'testimonial_meta', 'text-editor' ], 'rating_ref' => [ 'testimonial_rating', 'text-editor' ] ],
	];
	$field_map = $roles[ $role ] ?? [];
	foreach ( array_values( $items ) as $index => $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$group_id = sanitize_key( (string) ( $item['group_id'] ?? $role . '_' . ( $index + 1 ) ) );
		$card_children = [];
		if ( ! empty( $item['media_ref'] ) ) {
			$image_role = $role === 'service_cards' ? 'service_image' : ( $role === 'testimonial_cards' ? 'testimonial_photo' : 'team_photo' );
			$card_children[] = wpae_elementor_ir_node( $node_id . '-' . $group_id . '-photo', $image_role, 'image', [], [ 'radius.card' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'media_refs' => [ sanitize_key( (string) $item['media_ref'] ) ], 'editable_fields' => [ 'media', 'alt' ] ] );
		}
		foreach ( $field_map as $field => [ $field_role, $widget_type ] ) {
			$ref = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
			if ( $ref === '' ) {
				continue;
			}
			$card_children[] = wpae_elementor_ir_node(
				$node_id . '-' . $group_id . '-' . $field_role,
				$field_role,
				$widget_type,
				[ $ref ],
				$tokens,
				[],
				[ 'min_width' => 0, 'max_width' => 100 ],
				[ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ]
			);
		}
		$cards[] = wpae_elementor_ir_node(
			$node_id . '-' . $group_id . '-card',
			substr( $role, 0, -1 ),
			'container',
			[],
			[ 'color.surface', 'color.border', 'radius.card', 'space.component' ],
			$card_children,
			[ 'min_width' => 0, 'max_width' => 100, 'item_id' => $group_id ],
			[ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ]
		);
	}
	return $cards;
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
			if ( in_array( $role, [ 'copy_group', 'cta_copy_group' ], true ) ) {
				$widgets = [];
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					$item_role = sanitize_key( (string) ( $item['role'] ?? '' ) );
					if ( $item_role === 'brand' ) {
						$widgets['brand'] = wpae_elementor_ir_node( $child_id . '-brand', 'brand', 'heading', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'title' ) {
						$title_role = $role === 'cta_copy_group' ? 'cta_section_title' : 'title';
						$widgets['title'] = wpae_elementor_ir_node( $child_id . '-title', $title_role, 'heading', [ $content_ref ], [ 'color.text', 'type.display', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'eyebrow' ) {
						if ( ( $child['layout_constraints']['eyebrow_presentation'] ?? '' ) === 'pill' ) {
							$badge_label = wpae_elementor_ir_node( $child_id . '-eyebrow-badge-label', 'eyebrow_badge_label', 'heading', [ $content_ref ], [ 'color.surface', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow-badge', 'hero_badge', 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						} else {
							$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-eyebrow', 'eyebrow', 'heading', [ $content_ref ], [ 'color.primary', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
						}
					} elseif ( $item_role === 'label' ) {
						$widgets['eyebrow'] = wpae_elementor_ir_node( $child_id . '-label', 'eyebrow', 'heading', [ $content_ref ], [ 'color.primary', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'body' || $item_role === 'text' ) {
						$body_role = $role === 'cta_copy_group' ? 'cta_description' : 'body';
						$widgets['body'] = wpae_elementor_ir_node( $child_id . '-body', $body_role, 'text-editor', [ $content_ref ], [ 'color.muted', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text' ] ] );
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
					$cta_widgets = [];
					foreach ( $widgets as $widget_key => $widget ) {
						if ( str_starts_with( (string) $widget_key, 'cta_' ) ) {
							$cta_widgets[] = $widget;
						}
					}
					if ( $role === 'cta_copy_group' && ! empty( $cta_widgets ) ) {
						$ordered_widgets[] = wpae_elementor_ir_node( $child_id . '-actions', 'cta_actions', 'container', [], [ 'space.component', 'color.primary', 'color.surface', 'color.text' ], $cta_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
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
				$section_children[] = wpae_elementor_ir_node( $child_id, $role, 'container', [], $token_refs, $ordered_widgets, $copy_constraints, [ 'strategy' => 'copy_first_stack', 'editable_fields' => [ 'text', 'url' ] ] );
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
				foreach ( $content_refs as $content_ref ) {
					$item = $content_map[ $content_ref ] ?? [];
					$item_role = sanitize_key( (string) ( $item['role'] ?? '' ) );
					if ( $item_role === 'eyebrow' ) {
						$badge_label = wpae_elementor_ir_node( $child_id . '-intro-eyebrow-label', 'eyebrow', 'heading', [ $content_ref ], [ 'color.surface', 'type.body' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-eyebrow', 'pricing_badge', 'container', [], [ 'color.primary', 'color.surface' ], [ $badge_label ], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
					} elseif ( $item_role === 'title' ) {
						$intro_widgets[] = wpae_elementor_ir_node( $child_id . '-intro-title', 'title', 'heading', [ $content_ref ], [ 'color.text', 'type.display' ], [], [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
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
					$card_widgets = [
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-details', 'pricing_details', 'container', [], [ 'space.component' ], $details_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ),
					];
					$cta_widgets = [];
					$add_tier_widget( $cta_widgets, 'cta_ref', 'cta_primary', 'button', [ 'color.primary', 'color.text', 'color.surface' ] );
					$card_widgets = array_merge( $card_widgets, $cta_widgets );
					if ( count( $details_widgets ) + count( $cta_widgets ) < 3 ) {
						$warnings[] = $child_id . ':pricing_tier_' . $card_number . '_incomplete';
					}
					$card_children[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_index, 'pricing_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_widgets, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'pricing_cards', 'container', [], $token_refs, $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url' ] ] );
			} elseif ( $role === 'faq_surface' ) {
				$accordion = wpae_elementor_ir_node( $child_id . '-accordion', 'faq_accordion', 'accordion', $content_refs, array_merge( $token_refs, [ 'color.focus' ] ), [], [ 'items' => array_values( (array) ( $child['items'] ?? [] ) ) ], [ 'strategy' => 'stack', 'editable_fields' => [ 'question', 'answer' ] ] );
				$section_children[] = wpae_elementor_ir_node( $child_id, 'faq_surface', 'container', [], $token_refs, [ $accordion ], (array) ( $child['layout_constraints'] ?? [] ), [ 'strategy' => 'stack', 'editable_fields' => [ 'question', 'answer' ] ] );
			} elseif ( $role === 'feature_cards' ) {
				$cards = [];
				foreach ( array_values( (array) ( $child['items'] ?? [] ) ) as $card_index => $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$card_number = $card_index + 1;
					$card_children = [
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-icon', 'feature_icon', 'icon', [], [ 'color.primary', 'color.surface' ], [], [ 'icon_name' => 'check-circle' ], [ 'strategy' => 'stack', 'editable_fields' => [] ] ),
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-title', 'feature_title', 'heading', [ sanitize_key( (string) ( $item['title_ref'] ?? '' ) ) ], [ 'color.text', 'type.body' ], [], [], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ),
						wpae_elementor_ir_node( $child_id . '-card-' . $card_number . '-body', 'feature_body', 'text-editor', [ sanitize_key( (string) ( $item['body_ref'] ?? '' ) ) ], [ 'color.muted', 'type.body' ], [], [], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] ),
					];
					$cards[] = wpae_elementor_ir_node( $child_id . '-card-' . $card_number, 'feature_card', 'container', [], [ 'color.surface', 'color.border', 'radius.card', 'space.component' ], $card_children, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
				}
				$section_children[] = wpae_elementor_ir_node( $child_id, 'feature_cards', 'container', [], $token_refs, $cards, [ 'min_width' => 0, 'max_width' => 100 ], [ 'strategy' => 'stack', 'editable_fields' => [ 'text' ] ] );
			} elseif ( in_array( $role, [ 'service_cards', 'team_cards', 'testimonial_cards' ], true ) ) {
				$cards = wpae_elementor_ir_card_nodes( $role, $child_id, (array) ( $child['items'] ?? [] ), [ 'color.text', 'color.muted', 'type.body' ] );
				$section_children[] = wpae_elementor_ir_node( $child_id, $role, 'container', [], $token_refs, $cards, (array) ( $child['layout_constraints'] ?? [] ), [ 'strategy' => 'stack', 'editable_fields' => [ 'text', 'url', 'media', 'alt' ] ] );
			}
		}
		$section_composition = (string) ( $section['composition'] ?? 'stacked_left' );
		if ( sanitize_key( (string) ( $section['role'] ?? '' ) ) === 'pricing' ) {
			$section_composition = 'stacked_left';
		}
		$section_layout = [ 'composition' => $section_composition, 'min_width' => 0, 'max_width' => 100, 'media_side' => sanitize_key( (string) ( $section['media_side'] ?? 'right' ) ) ];
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
		if ( in_array( $role, [ 'hero', 'process', 'pricing', 'faq', 'benefits', 'services', 'team', 'testimonials', 'cta' ], true ) ) {
			$section_spacing = $token_values['space.section'] ?? $token_values['space.component'] ?? ( $tokens['native_tokens']['spacing']['section_desktop'] ?? '4.5rem' );
			$mobile_spacing = isset( $token_values['space.section'] ) ? ( $tokens['native_tokens']['spacing']['section_mobile'] ?? '2rem' ) : $section_spacing;
			$desktop_padding = wpae_elementor_ir_dimension_control( $section_spacing, 'rem', 4.5, false );
			$mobile_padding = wpae_elementor_ir_dimension_control( $mobile_spacing, 'rem', 2, false );
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
		if ( in_array( $role, [ 'copy_group', 'service_cards', 'team_cards', 'testimonial_cards', 'cta_copy_group', 'cta_actions' ], true ) ) {
			unset( $settings['background_color'], $settings['background_background'] );
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
			unset( $settings['background_color'], $settings['background_background'] );
		}
		if ( $role === 'pricing_cards' ) {
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
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'column';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_wrap_tablet'] = 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
		}
		if ( in_array( $role, [ 'service_cards', 'team_cards', 'testimonial_cards' ], true ) ) {
			$settings['_css_classes'] = 'wpae-' . $role;
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'column';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_wrap_tablet'] = 'nowrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			$settings['flex_align_items'] = 'stretch';
		}
		if ( $role === 'cta' ) {
			$settings['_css_classes'] = 'wpae-cta-section';
			$settings['flex_align_items'] = 'center';
			$settings['flex_direction'] = 'column';
		}
		if ( $role === 'cta_actions' ) {
			$settings['_css_classes'] = 'wpae-cta-actions';
			$settings['flex_direction'] = 'row';
			$settings['flex_direction_tablet'] = 'row';
			$settings['flex_direction_mobile'] = 'column';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_wrap_tablet'] = 'wrap';
			$settings['flex_wrap_mobile'] = 'nowrap';
			$settings['flex_align_items'] = 'flex-start';
			$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.875, 'column' => '0.875', 'row' => '0.875', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = [ 'unit' => 'rem', 'size' => 0.75, 'column' => '0.75', 'row' => '0.75', 'isLinked' => true ];
		}
		if ( $role === 'hero' && ( $node['layout_constraints']['media_side'] ?? 'right' ) === 'left' && array_filter( (array) ( $node['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'media_group' ) ) {
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
		if ( in_array( $role, [ 'pricing_card', 'feature_card', 'service_card', 'team_card', 'testimonial_card' ], true ) ) {
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
		if ( $role === 'testimonial_card' ) {
			$settings['flex_justify_content'] = 'space-between';
			$settings['flex_justify_content_mobile'] = 'flex-start';
		}
		if ( in_array( $role, [ 'copy_group', 'cta_copy_group' ], true ) ) {
			$alignment = sanitize_key( (string) ( $node['layout_constraints']['text_align'] ?? 'left' ) );
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = [ 'center' => 'center', 'right' => 'flex-end' ][ $alignment ] ?? 'flex-start';
			$settings['text_align'] = in_array( $alignment, [ 'left', 'center', 'right' ], true ) ? $alignment : 'left';
			if ( $role === 'cta_copy_group' ) {
				$settings['_css_classes'] = 'wpae-cta-copy';
				$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.5, 'column' => '0.5', 'row' => '0.5', 'isLinked' => true ];
				$settings['flex_gap_mobile'] = $settings['flex_gap'];
			}
		}
		if ( $role === 'pricing_details' ) {
			$settings['flex_direction'] = 'column';
			$settings['flex_align_items'] = 'stretch';
			$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.75, 'column' => '0.75', 'row' => '0.75', 'isLinked' => true ];
		}
		if ( $role === 'pricing_price_group' ) {
			$settings['flex_direction'] = 'row';
			$settings['flex_wrap'] = 'wrap';
			$settings['flex_align_items'] = 'center';
			$settings['flex_gap'] = [ 'unit' => 'rem', 'size' => 0.25, 'column' => '0.25', 'row' => '0.25', 'isLinked' => true ];
			$settings['flex_gap_mobile'] = $settings['flex_gap'];
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
		$heading_alignment = sanitize_key( (string) ( $node['layout_constraints']['text_align'] ?? '' ) );
		if ( in_array( $heading_alignment, [ 'left', 'center', 'right' ], true ) ) {
			$settings['align'] = $heading_alignment;
		}
		$settings['header_size'] = [ 'process_number' => 'h6', 'process_badge_label' => 'h6', 'brand' => 'h6', 'eyebrow' => 'h6', 'pricing_label' => 'h4', 'pricing_price' => 'h2', 'cta_section_title' => 'h2', 'service_title' => 'h3', 'team_name' => 'h3', 'testimonial_author' => 'h3' ][ $role ] ?? ( $role === 'title' ? ( str_contains( (string) ( $node['node_id'] ?? '' ), '-card-' ) ? 'h3' : 'h1' ) : 'h3' );
		$settings['title_color'] = in_array( $role, [ 'process_number', 'process_badge_label' ], true ) ? (string) ( $token_values['color.surface'] ?? '#ffffff' ) : (string) ( $token_values[ $role === 'brand' ? 'color.muted' : 'color.text' ] ?? '#111827' );
		$type_token = is_array( $token_values['type.display'] ?? null ) && ! in_array( $role, [ 'eyebrow', 'brand', 'feature_title', 'pricing_label', 'process_number', 'process_badge_label', 'cta_section_title', 'service_title', 'team_name', 'testimonial_author' ], true ) ? $token_values['type.display'] : ( $token_values['type.body'] ?? [] );
		if ( is_array( $type_token ) ) {
			$settings = array_merge( $settings, wpae_elementor_ir_type_settings( $type_token ) );
		}
		if ( $role === 'feature_title' ) {
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
			$settings['text_color'] = (string) ( $token_values[ $role === 'testimonial_quote' ? 'color.text' : 'color.muted' ] ?? '#6b7280' );
			$text_alignment = sanitize_key( (string) ( $node['layout_constraints']['text_align'] ?? '' ) );
			if ( in_array( $text_alignment, [ 'left', 'center', 'right' ], true ) ) {
				$settings['align'] = $text_alignment;
			}
			if ( in_array( $role, [ 'feature_body', 'pricing_description' ], true ) ) {
				$settings['align'] = 'left';
			}
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
		$settings['width'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
		$settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
		$settings['image_border_radius'] = wpae_elementor_ir_dimension_control( $token_values['radius.card'] ?? '0.5rem', 'rem', 0.5 );
		if ( in_array( (string) ( $media['object_fit'] ?? '' ), [ 'cover', 'contain', 'fill' ], true ) ) {
			$settings['object-fit'] = (string) $media['object_fit'];
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
		if ( $role === 'hero' && ( $node['layout_constraints']['media_side'] ?? 'right' ) === 'left' && count( $composition_basis ) === 2 ) {
			$composition_basis = array_reverse( $composition_basis );
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
			if ( $role === 'cta' && $child_role === 'cta_copy_group' ) {
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
