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
		'archetypes' => [ 'hero', 'process', 'pricing', 'faq', 'benefits', 'services', 'team', 'testimonials', 'cta' ],
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

function wpae_design_plan_grouped_items( array $brief, string $prefix, array $role_map, array $required_fields, int $minimum, int $maximum ): array {
	$items = [];
	$errors = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$group_id = sanitize_key( (string) ( $item['group_id'] ?? '' ) );
		$role = sanitize_key( (string) ( $item['role'] ?? '' ) );
		$field = $role_map[ $role ] ?? '';
		if ( $field === '' || ! str_starts_with( $group_id, $prefix . '_' ) ) {
			continue;
		}
		if ( ! isset( $items[ $group_id ] ) ) {
			$items[ $group_id ] = [ 'group_id' => $group_id, 'source_order' => (int) ( $item['source_span'][0] ?? 0 ), 'provenance' => [ 'source' => 'brief', 'item_id' => $group_id ] ];
		}
		if ( isset( $items[ $group_id ][ $field ] ) ) {
			$errors[] = $group_id . '_duplicate_' . $field;
			continue;
		}
		$items[ $group_id ][ $field ] = sanitize_key( (string) ( $item['id'] ?? '' ) );
		$items[ $group_id ]['provenance']['source_spans'][ $field ] = (array) ( $item['source_span'] ?? [] );
	}
	foreach ( (array) ( $brief['media_references'] ?? [] ) as $media ) {
		if ( ! is_array( $media ) ) {
			continue;
		}
		$group_id = sanitize_key( (string) ( $media['group_id'] ?? '' ) );
		if ( $group_id === '' || ! str_starts_with( $group_id, $prefix . '_' ) ) {
			continue;
		}
		if ( ! isset( $items[ $group_id ] ) ) {
			$items[ $group_id ] = [ 'group_id' => $group_id, 'source_order' => (int) ( $media['provenance']['source_span'][0] ?? 0 ), 'provenance' => [ 'source' => 'brief', 'item_id' => $group_id ] ];
		}
		if ( isset( $items[ $group_id ]['media_ref'] ) ) {
			$errors[] = $group_id . '_multiple_media_assets';
		} else {
			$items[ $group_id ]['media_ref'] = sanitize_key( (string) ( $media['asset_id'] ?? '' ) );
		}
	}
	$items = array_values( $items );
	usort( $items, static fn( array $a, array $b ): int => (int) ( $a['source_order'] ?? 0 ) <=> (int) ( $b['source_order'] ?? 0 ) );
	foreach ( $items as $index => $item ) {
		foreach ( $required_fields as $field ) {
			if ( trim( (string) ( $item[ $field ] ?? '' ) ) === '' ) {
				$errors[] = sanitize_key( (string) ( $item['group_id'] ?? 'item_' . ( $index + 1 ) ) ) . '_missing_' . sanitize_key( $field );
			}
		}
		unset( $items[ $index ]['source_order'] );
	}
	if ( count( $items ) < $minimum || count( $items ) > $maximum ) {
		$errors[] = $prefix . '_item_count_out_of_range';
	}
	return [ 'items' => $items, 'errors' => array_values( array_unique( $errors ) ) ];
}

function wpae_design_plan_group_refs( array $items, array $fields ): array {
	$refs = [];
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		foreach ( $fields as $field ) {
			$ref = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
			if ( $ref !== '' ) {
				$refs[] = $ref;
			}
		}
	}
	return array_values( array_unique( $refs ) );
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

function wpae_design_plan_default_service_media(): array {
	return [
		[ 'asset_id' => 'wpae_service_photo_1', 'group_id' => 'service_1', 'source_url' => 'https://images.unsplash.com/photo-1772442198689-af331f8f9617?auto=format&fit=crop&fm=jpg&h=675&ixlib=rb-4.1.0&q=80&w=1200', 'alt' => 'Архитектор изучает чертежи у современного здания.' ],
		[ 'asset_id' => 'wpae_service_photo_2', 'group_id' => 'service_2', 'source_url' => 'https://images.unsplash.com/photo-1766230976347-c5badd3f76c9?auto=format&fit=crop&fm=jpg&h=675&ixlib=rb-4.1.0&q=80&w=1200', 'alt' => 'Современный архитектурный интерьер.' ],
		[ 'asset_id' => 'wpae_service_photo_3', 'group_id' => 'service_3', 'source_url' => 'https://images.unsplash.com/photo-1778074762022-c33cc42f79ae?auto=format&fit=crop&fm=jpg&h=675&ixlib=rb-4.1.0&q=80&w=1200', 'alt' => 'Специалисты обсуждают проектные чертежи.' ],
	];
}

function wpae_design_plan_services_lead_ref( array $brief, array $context = [] ): string {
	$context_ref = is_scalar( $context['services_lead_service_ref'] ?? null ) ? trim( (string) $context['services_lead_service_ref'] ) : '';
	if ( preg_match( '/^service_\d+$/', $context_ref ) ) {
		return sanitize_key( $context_ref );
	}
	$source = (string) ( $brief['source_text'] ?? '' );
	if ( preg_match( '/(?<![a-z0-9_])services_lead_service_ref\s*[:=]\s*(service_\d+)(?![a-z0-9_])/iu', $source, $matches ) ) {
		return sanitize_key( (string) ( $matches[1] ?? '' ) );
	}
	return '';
}

function wpae_design_plan_services_recipe_catalog_media( array $brief, string $recipe_id, string $lead_ref, array $existing_media ): array {
	if ( ! empty( $existing_media ) ) {
		return [];
	}
	$source = (string) ( $brief['source_text'] ?? '' );
	if ( ! preg_match( '/services\s+(?:media\s+)?catalog/iu', $source ) ) {
		return [];
	}
	$media_intent = sanitize_key( (string) wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' ) );
	if ( in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) ) {
		return [];
	}
	$brief_groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
	$group_ids = [];
	foreach ( $brief_groups as $group ) {
		$group_id = sanitize_key( (string) ( $group['group_id'] ?? '' ) );
		if ( $group_id !== '' ) {
			$group_ids[] = $group_id;
		}
	}
	if ( empty( $group_ids ) && function_exists( 'wpae_brief_ir_service_groups' ) ) {
		foreach ( wpae_brief_ir_service_groups( (array) ( $brief['content'] ?? [] ) ) as $group ) {
			$group_id = sanitize_key( (string) ( $group['group_id'] ?? '' ) );
			if ( $group_id !== '' ) {
				$group_ids[] = $group_id;
			}
		}
	}
	if ( $recipe_id === 'services.photo_cards' ) {
		$selected_groups = $group_ids;
	} elseif ( $recipe_id === 'services.split_editorial' && $lead_ref !== '' && in_array( $lead_ref, $group_ids, true ) ) {
		$selected_groups = [ $lead_ref ];
	} else {
		return [];
	}
	$references = [];
	foreach ( wpae_design_plan_default_service_media() as $asset ) {
		if ( ! in_array( (string) $asset['group_id'], $selected_groups, true ) ) {
			continue;
		}
		$asset['role'] = 'card_image';
		$asset['attachment_id'] = null;
		$asset['focal_point'] = null;
		$asset['crop'] = '4:3';
		$asset['object_fit'] = 'cover';
		$asset['license'] = 'Unsplash License';
		$asset['attribution'] = '';
		$asset['allowed_reuse'] = true;
		$asset['provenance'] = [ 'source' => 'plugin_default', 'catalog' => 'wpae-service-media-v1' ];
		$references[] = $asset;
	}
	return $references;
}

function wpae_design_plan_default_hero_media( array $brief ): array {
	if ( wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' ) !== 'unspecified' || ! empty( $brief['media_references'] ) ) {
		return [];
	}
	$source = (string) ( $brief['source_text'] ?? '' );
	if ( ! preg_match( '/архитектур\w*|архитектор\w*|интерьер\w*|строительств\w*|здан\w*|ландшафт\w*|\barchitecture\b|\barchitect\w*|\binterior\w*|\bconstruction\b|\bbuilding\b/iu', $source ) ) {
		return [];
	}
	$media = wpae_design_plan_default_service_media()[1];
	$media['asset_id'] = 'wpae_hero_architecture_interior';
	$media['group_id'] = 'hero_visual';
	$media['role'] = 'hero';
	$media['attachment_id'] = null;
	$media['crop'] = '16:9';
	$media['object_fit'] = 'cover';
	$media['license'] = 'Unsplash License';
	$media['attribution'] = '';
	$media['allowed_reuse'] = true;
	$media['provenance'] = [ 'source' => 'plugin_default', 'catalog' => 'wpae-hero-media-v1' ];
	return $media;
}

function wpae_design_plan_services_recipe_ids(): array {
	return [ 'services.photo_cards', 'services.split_editorial', 'services.text_icon_list' ];
}

/**
 * Verify the one bundled Services reference whose repeating card slots are
 * explicitly mapped to the canonical photo-card recipe. This only authorizes
 * a source/template identity; source Elementor IDs and controls are never
 * copied into the generated tree.
 */
function wpae_design_plan_services_photo_template_slot_map(): array {
	$map = [
		'template_id' => 'template-services-photo-cards-v1',
		'file' => 'services-photo-cards.json',
		'sha256' => '9f6d8e2a5cc3ddf0f865656a540f36de86ef3fb3f2d81be48b49ad60d11a52c4',
		'recipe_id' => 'services.photo_cards',
		'adaptation' => 'verified_reference_slots_recompiled_by_native_services_recipe',
		'slots' => [ 'section.eyebrow', 'section.title', 'service[].image', 'service[].title', 'service[].body' ],
	];
	$base = dirname( __DIR__ ) . '/elementor/imported-templates/';
	$template_path = $base . $map['file'];
	$manifest_path = $base . 'manifest.json';
	if ( ! is_file( $template_path ) || ! is_file( $manifest_path ) || hash_file( 'sha256', $template_path ) !== $map['sha256'] ) {
		return [ 'ok' => false, 'reason' => 'services_reference_integrity_mismatch', 'template_id' => $map['template_id'] ];
	}
	$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
	$record = null;
	foreach ( (array) ( $manifest['files'] ?? [] ) as $candidate ) {
		if ( is_array( $candidate ) && ( $candidate['id'] ?? '' ) === $map['template_id'] && ( $candidate['file'] ?? '' ) === $map['file'] ) {
			$record = $candidate;
			break;
		}
	}
	if ( ! is_array( $record ) || ( $record['sha256'] ?? '' ) !== $map['sha256'] || ( $record['category'] ?? '' ) !== 'services' ) {
		return [ 'ok' => false, 'reason' => 'services_reference_manifest_mismatch', 'template_id' => $map['template_id'] ];
	}
	$document = json_decode( (string) file_get_contents( $template_path ), true );
	$root = is_array( $document['content'][0] ?? null ) ? $document['content'][0] : [];
	$root_children = array_values( (array) ( $root['elements'] ?? [] ) );
	$find_class = static function ( array $nodes, string $class ) use ( &$find_class ): array {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$classes = preg_split( '/\\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) ) ?: [];
			if ( in_array( $class, $classes, true ) ) {
				return $node;
			}
			$nested = $find_class( (array) ( $node['elements'] ?? [] ), $class );
			if ( ! empty( $nested ) ) {
				return $nested;
			}
		}
		return [];
	};
	$find_widget_types = static function ( array $nodes, string $widget_type ) use ( &$find_widget_types ): array {
		$found = [];
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === $widget_type ) {
				$found[] = $node;
			}
			$found = array_merge( $found, $find_widget_types( (array) ( $node['elements'] ?? [] ), $widget_type ) );
		}
		return $found;
	};
	$grid = $find_class( [ $root ], 'wpae-bento-grid' );
	$cards = array_values( (array) ( $grid['elements'] ?? [] ) );
	$slot_shape_ok = count( $root_children ) === 3
		&& ( $root_children[0]['elType'] ?? '' ) === 'container'
		&& ! empty( $find_widget_types( [ $root_children[0] ], 'heading' ) )
		&& ( $root_children[1]['widgetType'] ?? '' ) === 'heading'
		&& ( $grid['elType'] ?? '' ) === 'container'
		&& count( $cards ) === 3;
	foreach ( $cards as $card ) {
		$card_children = array_values( (array) ( $card['elements'] ?? [] ) );
		$slot_shape_ok = $slot_shape_ok
			&& ( $card['elType'] ?? '' ) === 'container'
			&& ( $card_children[0]['widgetType'] ?? '' ) === 'image'
			&& ( $card_children[1]['elType'] ?? '' ) === 'container'
			&& ! empty( $find_widget_types( [ $card_children[1] ], 'heading' ) )
			&& ( $card_children[2]['widgetType'] ?? '' ) === 'text-editor';
	}
	if ( ! $slot_shape_ok ) {
		return [ 'ok' => false, 'reason' => 'services_reference_slot_shape_mismatch', 'template_id' => $map['template_id'] ];
	}
	return $map + [ 'ok' => true, 'title' => sanitize_text_field( (string) ( $record['title'] ?? '' ) ) ];
}

/** Resolve Services composition once from explicit context, canonical Brief constraints, or documented defaults. */
function wpae_design_plan_services_recipe_decision( array $brief, array $context = [] ): array {
	$policy = (array) ( $brief['policy'] ?? [] );
	$library_policy = sanitize_key( (string) ( $policy['library']['source'] ?? 'unspecified' ) );
	$fallback_policy = sanitize_key( (string) ( $policy['fallback']['source'] ?? 'unspecified' ) );
	$constraints = array_values( array_filter( (array) ( $brief['layout_constraints'] ?? [] ), 'is_array' ) );
	$recognized = [];
	$explicit_context_recipe = trim( (string) ( $context['services_recipe_id'] ?? '' ) );
	if ( $explicit_context_recipe !== '' ) {
		$recognized[] = [ 'recipe_id' => $explicit_context_recipe, 'source' => 'explicit_context' ];
	}
	foreach ( $constraints as $constraint ) {
		if ( ( $constraint['kind'] ?? '' ) === 'services_recipe' ) {
			$recognized[] = [ 'recipe_id' => trim( (string) ( $constraint['value'] ?? '' ) ), 'source' => 'explicit_request' ];
		}
		if ( ( $constraint['kind'] ?? '' ) === 'composition' && str_starts_with( trim( (string) ( $constraint['value'] ?? '' ) ), 'split_' ) ) {
			$recognized[] = [ 'recipe_id' => 'services.split_editorial', 'source' => 'explicit_request' ];
		}
	}
	$recognized_ids = array_values( array_unique( array_column( $recognized, 'recipe_id' ) ) );
	if ( count( $recognized_ids ) > 1 ) {
		return [ 'ok' => false, 'recipe_id' => '', 'source' => 'explicit_request', 'library_required' => $library_policy === 'required', 'fallback_policy' => $fallback_policy, 'media_status' => 'unresolved', 'reason' => 'services_recipe_selection_conflict' ];
	}
	$explicit_recipe_id = (string) ( $recognized_ids[0] ?? '' );
	$source = $explicit_recipe_id !== '' ? (string) ( $recognized[0]['source'] ?? 'explicit_request' ) : 'documented_default';
	if ( $explicit_context_recipe !== '' ) {
		$source = 'explicit_context';
	} elseif ( $explicit_recipe_id !== '' ) {
		$source = 'explicit_request';
	}
	$groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
	if ( empty( $groups ) && function_exists( 'wpae_brief_ir_service_groups' ) ) {
		$groups = wpae_brief_ir_service_groups( (array) ( $brief['content'] ?? [] ), (array) ( $brief['media_references'] ?? [] ) );
	}
	$media = array_merge( (array) ( $brief['media_references'] ?? [] ), (array) ( $context['media_references'] ?? [] ) );
	$valid_media = [];
	$invalid_media = false;
	foreach ( $media as $asset ) {
		if ( ! is_array( $asset ) || trim( (string) ( $asset['asset_id'] ?? '' ) ) === '' || ! wpae_design_plan_media_reference_valid( $asset ) ) {
			$invalid_media = true;
			continue;
		}
		$asset_id = sanitize_key( (string) $asset['asset_id'] );
		if ( isset( $valid_media[ $asset_id ] ) ) {
			$invalid_media = true;
			continue;
		}
		$valid_media[ $asset_id ] = $asset;
	}
	$unassigned = array_values( array_filter( $valid_media, static fn( array $asset ): bool => trim( (string) ( $asset['group_id'] ?? '' ) ) === '' ) );
	$resolved_group_media = [];
	foreach ( $groups as $index => $group ) {
		$group_id = sanitize_key( (string) ( $group['group_id'] ?? 'service_' . ( $index + 1 ) ) );
		$media_ref = sanitize_key( (string) ( $group['media_ref'] ?? '' ) );
		if ( $media_ref === '' ) {
			foreach ( $valid_media as $asset_id => $asset ) {
				if ( sanitize_key( (string) ( $asset['group_id'] ?? '' ) ) === $group_id ) {
					$media_ref = $asset_id;
					break;
				}
			}
		}
		$asset = $valid_media[ $media_ref ] ?? [];
		$asset_group_id = sanitize_key( (string) ( $asset['group_id'] ?? '' ) );
		if ( ! empty( $asset ) && $asset_group_id !== '' && $asset_group_id !== $group_id ) {
			$invalid_media = true;
			$asset = [];
		}
		if ( ! empty( $asset ) && trim( (string) ( $asset['alt'] ?? '' ) ) !== '' ) {
			$resolved_group_media[ $group_id ] = $asset;
		}
	}
	$media_intent = sanitize_key( (string) wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' ) );
	$lead_ref = wpae_design_plan_services_lead_ref( $brief, $context );
	$recipe_id = $explicit_recipe_id;
	if ( $library_policy === 'required' ) {
		$map = wpae_design_plan_services_photo_template_slot_map();
		if ( empty( $map['ok'] ) ) {
			return [ 'ok' => false, 'recipe_id' => $recipe_id, 'source' => $source, 'library_required' => true, 'library_template_id' => '', 'library_applied' => false, 'library_mapping' => $map, 'fallback_policy' => $fallback_policy, 'media_status' => 'unresolved', 'reason' => 'services_library_only_no_compatible_template_slot_map' ];
		}
		if ( $recipe_id !== '' && $recipe_id !== (string) $map['recipe_id'] ) {
			return [ 'ok' => false, 'recipe_id' => $recipe_id, 'source' => $source, 'library_required' => true, 'library_template_id' => '', 'library_applied' => false, 'library_mapping' => $map, 'fallback_policy' => $fallback_policy, 'media_status' => 'unresolved', 'reason' => 'services_library_only_recipe_has_no_compatible_template_map' ];
		}
		$recipe_id = (string) $map['recipe_id'];
		$source = $explicit_recipe_id !== '' ? $source : 'explicit_request';
	}
	if ( $recipe_id === '' ) {
		$all_services_have_media = ! empty( $groups );
		foreach ( $groups as $index => $group ) {
			$group_id = sanitize_key( (string) ( $group['group_id'] ?? 'service_' . ( $index + 1 ) ) );
			if ( ! isset( $resolved_group_media[ $group_id ] ) ) {
				$all_services_have_media = false;
				break;
			}
		}
		$recipe_id = $media_intent === 'required' || $all_services_have_media ? 'services.photo_cards' : 'services.text_icon_list';
	}
	$catalog_media = wpae_design_plan_services_recipe_catalog_media( $brief, $recipe_id, $lead_ref, $media );
	foreach ( $catalog_media as $asset ) {
		$asset_id = sanitize_key( (string) ( $asset['asset_id'] ?? '' ) );
		$group_id = sanitize_key( (string) ( $asset['group_id'] ?? '' ) );
		if ( $asset_id === '' || $group_id === '' ) {
			continue;
		}
		$media[] = $asset;
		$valid_media[ $asset_id ] = $asset;
		$resolved_group_media[ $group_id ] = $asset;
	}
	$group_ids = [];
	foreach ( $groups as $index => $group ) {
		$group_ids[] = sanitize_key( (string) ( $group['group_id'] ?? 'service_' . ( $index + 1 ) ) );
	}
	$lead_valid = $recipe_id !== 'services.split_editorial' || ( $lead_ref !== '' && in_array( $lead_ref, $group_ids, true ) );
	if ( $recipe_id === 'services.split_editorial' && $lead_valid && ! isset( $resolved_group_media[ $lead_ref ] ) && count( $unassigned ) === 1 ) {
		$resolved_group_media[ $lead_ref ] = $unassigned[0];
	}
	$required_media_groups = $recipe_id === 'services.photo_cards'
		? $group_ids
		: ( $recipe_id === 'services.split_editorial' ? [ $lead_valid ? $lead_ref : '' ] : [] );
	$missing = array_values( array_filter( $required_media_groups, static fn( string $group_id ): bool => ! isset( $resolved_group_media[ $group_id ] ) ) );
	$media_status = $recipe_id === 'services.text_icon_list'
		? ( $media_intent === 'required' ? 'unresolved' : ( empty( $media ) ? 'none' : 'unconsumed' ) )
		: ( ! $lead_valid || $invalid_media || ! empty( $missing ) ? 'unresolved' : ( empty( $required_media_groups ) && empty( $media ) ? 'none' : 'resolved_per_service' ) );
	if ( $library_policy === 'required' ) {
		return [ 'ok' => $lead_valid, 'recipe_id' => $recipe_id, 'source' => $source, 'lead_service_ref' => $lead_ref, 'media_references' => $media, 'library_required' => true, 'library_template_id' => (string) $map['template_id'], 'library_applied' => false, 'library_mapping' => $map, 'fallback_policy' => $fallback_policy, 'media_status' => $media_status, 'media_missing_service_ids' => $missing, 'reason' => $lead_valid ? '' : 'services_split_editorial_lead_service_ref_required' ];
	}
	return [
		'ok' => in_array( $recipe_id, wpae_design_plan_services_recipe_ids(), true ) && $lead_valid,
		'recipe_id' => $recipe_id,
		'source' => $source,
		'lead_service_ref' => $lead_ref,
		'media_references' => $media,
		'library_required' => false,
		'library_template_id' => '',
		'library_applied' => false,
		'library_mapping' => [],
		'fallback_policy' => $fallback_policy,
		'media_status' => $media_status,
		'media_missing_service_ids' => $missing,
		'reason' => ! $lead_valid ? 'services_split_editorial_lead_service_ref_required' : ( $recipe_id === '' ? 'services_recipe_unsupported' : '' ),
	];
}

function wpae_design_plan_services_recipe_plan( array $brief, array $context, string $recipe_id ): array {
	$media_intent = (string) wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' );
	$brief_media = array_values( (array) ( $brief['media_references'] ?? [] ) );
	$context_media = array_values( (array) ( $context['media_references'] ?? [] ) );
	$requested_lead_group_id = wpae_design_plan_services_lead_ref( $brief, $context );
	$media = [];
	$media_by_id = [];
	$errors = [];
	if ( ! in_array( $recipe_id, wpae_design_plan_services_recipe_ids(), true ) ) {
		$errors[] = 'services_recipe_unknown';
	}
	foreach ( array_merge( $brief_media, $context_media ) as $index => $asset ) {
		if ( ! is_array( $asset ) ) {
			$errors[] = 'media_reference_' . ( $index + 1 ) . '_invalid';
			continue;
		}
		$id = trim( (string) ( $asset['asset_id'] ?? '' ) );
		if ( $id === '' || sanitize_key( $id ) !== $id ) {
			$errors[] = 'media_reference_' . ( $index + 1 ) . '_invalid_id';
			continue;
		}
		if ( isset( $media_by_id[ $id ] ) ) {
			$errors[] = 'duplicate_media_asset_' . $id;
			continue;
		}
		if ( ! wpae_design_plan_media_reference_valid( $asset ) ) {
			$errors[] = 'media_asset_invalid_' . $id;
		}
		$asset['group_id'] = trim( (string) ( $asset['group_id'] ?? '' ) );
		$media_by_id[ $id ] = $asset;
		$media[] = $asset;
	}
	$groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
	if ( empty( $groups ) ) {
		$grouped = wpae_design_plan_grouped_items( $brief, 'service', [ 'service_title' => 'title_ref', 'service_body' => 'body_ref', 'service_cta' => 'cta_ref' ], [ 'title_ref', 'body_ref' ], 2, 6 );
		$groups = $grouped['items'];
		$errors = array_merge( $errors, (array) $grouped['errors'] );
	}
	foreach ( $groups as $group_index => &$group ) {
		$group['group_id'] = sanitize_key( (string) ( $group['group_id'] ?? '' ) );
		$group['source_order'] = (int) ( $group['source_span'][0] ?? $group['source_order'] ?? $group_index );
		$group['media_ref'] = trim( (string) ( $group['media_ref'] ?? '' ) );
		if ( ! preg_match( '/^service_\d+$/', $group['group_id'] ) ) {
			$errors[] = 'service_group_' . ( $group_index + 1 ) . '_invalid_id';
		}
	}
	unset( $group );
	usort( $groups, static fn( array $left, array $right ): int => (int) $left['source_order'] <=> (int) $right['source_order'] );
	if ( count( $groups ) < 2 || count( $groups ) > 6 ) {
		$errors[] = 'services_items_out_of_range';
	}
	foreach ( wpae_design_plan_services_recipe_catalog_media( $brief, $recipe_id, $requested_lead_group_id, array_merge( $brief_media, $context_media ) ) as $asset ) {
		$asset_id = sanitize_key( (string) ( $asset['asset_id'] ?? '' ) );
		$group_id = sanitize_key( (string) ( $asset['group_id'] ?? '' ) );
		if ( $asset_id === '' || $group_id === '' || isset( $media_by_id[ $asset_id ] ) ) {
			continue;
		}
		$asset['group_id'] = $group_id;
		$media_by_id[ $asset_id ] = $asset;
		$media[] = $asset;
		foreach ( $groups as &$group ) {
			if ( $group['group_id'] === $group_id && $group['media_ref'] === '' ) {
				$group['media_ref'] = $asset_id;
				break;
			}
		}
		unset( $group );
	}
	$group_ids = [];
	foreach ( $groups as $group ) {
		$id = (string) ( $group['group_id'] ?? '' );
		if ( $id === '' || isset( $group_ids[ $id ] ) ) {
			$errors[] = 'services_duplicate_group_' . ( $id !== '' ? $id : 'unknown' );
		}
		$group_ids[ $id ] = true;
		foreach ( [ 'title_ref', 'body_ref' ] as $field ) {
			if ( trim( (string) ( $group[ $field ] ?? '' ) ) === '' ) {
				$errors[] = $id . '_missing_' . $field;
			}
		}
		if ( ! empty( $group['errors'] ) ) {
			$errors[] = $id . '_ambiguous_content';
		}
		$group_asset = $media_by_id[ (string) ( $group['media_ref'] ?? '' ) ] ?? [];
		$asset_group_id = sanitize_key( (string) ( $group_asset['group_id'] ?? '' ) );
		if ( ! empty( $group_asset ) && $asset_group_id !== '' && $asset_group_id !== $id ) {
			$errors[] = $id . '_media_asset_group_mismatch';
		}
	}
	$unassigned_media = [];
	foreach ( $media as $asset ) {
		$group_id = (string) ( $asset['group_id'] ?? '' );
		if ( $group_id !== '' && ! isset( $group_ids[ $group_id ] ) ) {
			$errors[] = 'media_asset_group_unknown_' . sanitize_key( $group_id );
			continue;
		}
		if ( $group_id === '' ) {
			$unassigned_media[] = $asset;
			continue;
		}
		foreach ( $groups as &$group ) {
			if ( $group['group_id'] !== $group_id ) {
				continue;
			}
			if ( $group['media_ref'] !== '' && $group['media_ref'] !== (string) $asset['asset_id'] ) {
				$errors[] = $group_id . '_multiple_media_assets';
			} else {
				$group['media_ref'] = (string) $asset['asset_id'];
			}
			break;
		}
		unset( $group );
	}
	if ( $recipe_id === 'services.split_editorial' && count( $unassigned_media ) === 1 && $requested_lead_group_id !== '' ) {
		foreach ( $groups as &$lead_group ) {
			if ( $lead_group['group_id'] === $requested_lead_group_id && $lead_group['media_ref'] === '' ) {
				$lead_group['media_ref'] = (string) $unassigned_media[0]['asset_id'];
				break;
			}
		}
		unset( $lead_group );
	}
	$service_content = [];
	foreach ( (array) ( $brief['content'] ?? [] ) as $item ) {
		if ( is_array( $item ) && trim( (string) ( $item['id'] ?? '' ) ) !== '' ) {
			$service_content[ sanitize_key( (string) $item['id'] ) ] = $item;
		}
	}
	foreach ( $groups as &$group ) {
		$group['title_ref'] = sanitize_key( (string) ( $group['title_ref'] ?? '' ) );
		$group['body_ref'] = sanitize_key( (string) ( $group['body_ref'] ?? '' ) );
		$group['cta_ref'] = sanitize_key( (string) ( $group['cta_ref'] ?? '' ) );
		foreach ( [ 'title_ref' => 'service_title', 'body_ref' => 'service_body', 'cta_ref' => 'service_cta' ] as $field => $expected_role ) {
			$ref = (string) $group[ $field ];
			if ( $ref === '' && $field === 'cta_ref' ) {
				continue;
			}
			if ( empty( $service_content[ $ref ] ) || ( $service_content[ $ref ]['role'] ?? '' ) !== $expected_role || ( $service_content[ $ref ]['group_id'] ?? '' ) !== $group['group_id'] ) {
				$errors[] = $group['group_id'] . '_invalid_' . $field;
			}
		}
		if ( $group['cta_ref'] !== '' ) {
			$cta_item = $service_content[ $group['cta_ref'] ] ?? [];
			if ( empty( $cta_item['url_requested'] ) || trim( (string) ( $cta_item['url'] ?? '' ) ) === '' ) {
				$errors[] = $group['group_id'] . '_cta_url_required';
			}
		}
	}
	unset( $group );
	$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'body' ] );
	$intro_eyebrow_ref = '';
	foreach ( (array) ( $brief['content'] ?? [] ) as $intro_item ) {
		if ( is_array( $intro_item ) && ( $intro_item['role'] ?? '' ) === 'eyebrow' ) {
			$intro_eyebrow_ref = sanitize_key( (string) ( $intro_item['id'] ?? '' ) );
			break;
		}
	}
	$provided = array_values( array_map( static fn( array $asset ): string => (string) $asset['asset_id'], $media ) );
	$consumed = [];
	$lead_group_id = '';
	if ( $recipe_id === 'services.photo_cards' ) {
		if ( in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) ) {
			$errors[] = 'photo_cards_media_forbidden_or_conflicted';
		}
		foreach ( $groups as $group ) {
			$ref = (string) ( $group['media_ref'] ?? '' );
			$asset = $media_by_id[ $ref ] ?? [];
			if ( $ref === '' || empty( $asset ) ) {
				$errors[] = $group['group_id'] . '_photo_asset_required';
				continue;
			}
			if ( trim( (string) ( $asset['alt'] ?? '' ) ) === '' ) {
				$errors[] = $group['group_id'] . '_photo_alt_required';
			}
			if ( preg_match( '#^https?://images\.unsplash\.com/#i', (string) ( $asset['source_url'] ?? '' ) ) && empty( $asset['allowed_reuse'] ) ) {
				$errors[] = 'media_unsplash_license_unconfirmed_' . $ref;
			}
			$consumed[] = $ref;
		}
	} elseif ( $recipe_id === 'services.split_editorial' ) {
		if ( in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) ) {
			$errors[] = 'split_editorial_media_forbidden_or_conflicted';
		}
		$lead_group_id = $requested_lead_group_id;
		if ( $lead_group_id === '' ) {
			$errors[] = 'split_editorial_lead_service_ref_required';
		} elseif ( ! isset( $group_ids[ $lead_group_id ] ) ) {
			$errors[] = 'split_editorial_lead_service_ref_invalid';
		}
		foreach ( $groups as $group ) {
			if ( $group['group_id'] === $lead_group_id ) {
				$lead_asset = $media_by_id[ (string) ( $group['media_ref'] ?? '' ) ] ?? [];
				if ( empty( $lead_asset ) ) {
					$asset = $unassigned_media[0] ?? [];
					if ( ! empty( $asset['asset_id'] ) ) {
						$lead_asset = $asset;
						foreach ( $groups as &$lead_candidate ) {
							if ( $lead_candidate['group_id'] === $lead_group_id ) {
							$lead_candidate['media_ref'] = (string) $asset['asset_id'];
							}
						}
						unset( $lead_candidate );
					}
				}
				if ( empty( $lead_asset ) ) {
					$errors[] = 'split_editorial_lead_photo_required';
				} else {
					$lead_ref = (string) $lead_asset['asset_id'];
					if ( trim( (string) ( $lead_asset['alt'] ?? '' ) ) === '' ) {
						$errors[] = 'split_editorial_lead_photo_alt_required';
					}
					if ( preg_match( '#^https?://images\.unsplash\.com/#i', (string) ( $lead_asset['source_url'] ?? '' ) ) && empty( $lead_asset['allowed_reuse'] ) ) {
						$errors[] = 'media_unsplash_license_unconfirmed_' . $lead_ref;
					}
					$consumed[] = $lead_ref;
				}
				break;
			}
		}
	} elseif ( $recipe_id === 'services.text_icon_list' ) {
		if ( $media_intent === 'required' ) {
			$errors[] = 'text_icon_list_incompatible_with_required_media';
		}
		if ( in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) && ! empty( $media ) ) {
			$errors[] = 'text_icon_list_media_intent_conflict';
		}
	}
	$unconsumed = array_values( array_diff( $provided, array_unique( $consumed ) ) );
	if ( $media_intent === 'required' && ! empty( $unconsumed ) ) {
		$errors[] = 'required_media_assets_unconsumed';
	}
	$recipe_role = [
		'services.photo_cards' => 'services_photo_grid',
		'services.split_editorial' => 'services_split_editorial',
		'services.text_icon_list' => 'services_text_icon_list',
	][ $recipe_id ] ?? 'unknown_services_recipe';
	$recipe_items = [];
	foreach ( $groups as $group ) {
		$item = [ 'group_id' => (string) $group['group_id'], 'title_ref' => $group['title_ref'], 'body_ref' => $group['body_ref'], 'cta_ref' => $group['cta_ref'] ];
		if ( $recipe_id === 'services.photo_cards' || ( $recipe_id === 'services.split_editorial' && $group['group_id'] === $lead_group_id ) ) {
			$item['media_ref'] = (string) ( $group['media_ref'] ?? '' );
		}
		$recipe_items[] = $item;
	}
	$children = [];
	if ( ! empty( $intro_refs ) ) {
		$intro_layout_constraints = [ 'min_width' => 0, 'max_width' => 100 ];
		$intro_allowed_widgets = [ 'heading', 'text-editor' ];
		if ( $intro_eyebrow_ref !== '' ) {
			// Typed Services recipes use the user-corrected native section pill as
			// their canonical eyebrow presentation when the prompt has no override.
			$intro_layout_constraints['eyebrow_presentation'] = (string) wpae_design_plan_constraint_value( $brief, 'eyebrow_presentation', 'pill' );
			if ( $intro_layout_constraints['eyebrow_presentation'] === 'pill' ) {
				$intro_allowed_widgets[] = 'container';
			}
		}
		$children[] = [ 'role' => 'copy_group', 'allowed_widgets' => $intro_allowed_widgets, 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.body' ], 'layout_constraints' => $intro_layout_constraints, 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ], 'provenance' => [ 'source' => 'brief', 'roles' => [ 'eyebrow', 'title', 'body' ] ] ];
	}
	$children[] = [
		'role' => $recipe_role,
		'allowed_widgets' => $recipe_id === 'services.photo_cards' ? [ 'container', 'image', 'heading', 'text-editor', 'button' ] : ( $recipe_id === 'services.split_editorial' ? [ 'container', 'image', 'heading', 'text-editor', 'button', 'divider' ] : [ 'container', 'icon', 'heading', 'text-editor', 'button', 'divider' ] ),
		'content_refs' => wpae_design_plan_group_refs( $recipe_items, [ 'title_ref', 'body_ref', 'cta_ref' ] ),
		'items' => $recipe_items,
		'item_errors' => array_values( array_unique( $errors ) ),
		'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.border', 'radius.card', 'space.component' ],
		'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
		'responsive_policy' => 'stack',
		'media_refs' => [],
		'editable_fields' => [ 'text', 'url', 'media', 'alt' ],
		'provenance' => [ 'source' => 'brief', 'roles' => [ 'service_title', 'service_body', 'service_cta' ] ],
	];
	$intro_refs_meta = [ 'eyebrow_ref' => '', 'title_ref' => '', 'description_ref' => '' ];
	foreach ( (array) ( $brief['content'] ?? [] ) as $content_item ) {
		if ( ! is_array( $content_item ) ) {
			continue;
		}
		$role = (string) ( $content_item['role'] ?? '' );
		$key = [ 'eyebrow' => 'eyebrow_ref', 'title' => 'title_ref', 'body' => 'description_ref' ][ $role ] ?? '';
		if ( $key !== '' && $intro_refs_meta[ $key ] === '' ) {
			$intro_refs_meta[ $key ] = sanitize_key( (string) ( $content_item['id'] ?? '' ) );
		}
	}
	return [
		'schema' => WPAE_DESIGN_PLAN_SCHEMA,
		'archetype' => 'services',
		'recipe_id' => $recipe_id,
		'recipe_selection' => [
			'source' => sanitize_key( (string) ( $context['services_recipe_selection_source'] ?? 'planning_context' ) ),
			'field' => 'services_recipe_id',
			'lead_service_ref' => $lead_group_id,
			'library_required' => ! empty( $context['services_library_required'] ),
			'library_template_id' => sanitize_key( (string) ( $context['services_library_template_id'] ?? '' ) ),
			'library_adaptation' => sanitize_key( (string) ( $context['services_library_adaptation'] ?? '' ) ),
		],
		'slot_bindings' => [ 'section' => $intro_refs_meta, 'services' => $recipe_items ],
		'media_compatibility' => [ 'status' => empty( $errors ) ? 'compatible' : 'incompatible', 'provided_asset_refs' => $provided, 'consumed_asset_refs' => array_values( array_unique( $consumed ) ), 'unconsumed_asset_refs' => $unconsumed ],
		'sections' => [ [ 'id' => 'services', 'role' => 'services', 'composition' => 'linear', 'surface_token' => 'color.page_bg', 'spacing_token' => 'space.component', 'children' => $children, 'provenance' => [ 'source' => 'brief' ] ] ],
		'responsive' => [ 'desktop' => 'linear', 'tablet' => 'stack', 'mobile' => 'stack' ],
		'tokens' => [ 'color.page_bg' => 'color.page_bg', 'color.surface' => 'color.surface', 'color.text' => 'color.text', 'color.muted' => 'color.muted', 'color.primary' => 'color.primary', 'color.border' => 'color.border', 'type.display' => 'type.display', 'type.body' => 'type.body', 'space.section' => 'space.section', 'space.component' => 'space.component', 'radius.card' => 'radius.card' ],
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
		'media_references' => $media,
		'provenance' => [ 'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '', 'planner' => 'wpae-design-plan-v1', 'source' => 'brief-ir' ],
		'warnings' => [],
		'media_intent' => $media_intent,
		'media_asset_count' => count( $media ),
	];
}

function wpae_design_plan_from_brief( array $brief, array $context = [] ): array {
	$archetype = sanitize_key( (string) ( $brief['intent']['archetype'] ?? 'unknown' ) );
	if ( array_key_exists( 'services_recipe_id', $context ) ) {
		$raw_recipe_id = is_scalar( $context['services_recipe_id'] ) ? trim( (string) $context['services_recipe_id'] ) : '';
		$recipe_plan = wpae_design_plan_services_recipe_plan( $brief, $context, $raw_recipe_id );
		if ( $archetype !== 'services' ) {
			$recipe_plan['archetype'] = $archetype;
		}
		return $recipe_plan;
	}
	if ( ! in_array( $archetype, wpae_design_plan_schema()['archetypes'], true ) ) {
		return [
			'schema' => WPAE_DESIGN_PLAN_SCHEMA,
			'archetype' => 'unknown',
			'sections' => [],
			'responsive' => [],
			'tokens' => [],
			'media_references' => [],
			'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
			'provenance' => [ 'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '', 'planner' => 'wpae-design-plan-v1' ],
			'warnings' => [ 'unsupported_archetype' ],
		];
	}
	$media_intent = (string) wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' );
	$explicit_composition = wpae_design_plan_constraint_value( $brief, 'composition' );
	$composition = (string) ( $explicit_composition ?? ( $archetype === 'hero' ? 'split_60_40' : ( $archetype === 'pricing' ? 'three_cards' : 'linear' ) ) );
	$media_references = array_values( array_filter( (array) ( $brief['media_references'] ?? [] ), static fn( $media ): bool => is_array( $media ) && wpae_design_plan_media_reference_valid( $media ) && ( $archetype !== 'hero' || ( $media['role'] ?? '' ) === 'hero' ) ) );
	if ( $archetype === 'hero' && empty( $media_references ) ) {
		$default_hero_media = wpae_design_plan_default_hero_media( $brief );
		if ( ! empty( $default_hero_media ) ) {
			$media_references[] = $default_hero_media;
		}
	}
	$hero_has_media = $archetype === 'hero' && ! empty( $media_references ) && $media_intent !== 'forbidden' && $media_intent !== 'conflict';
	$cta_has_media = $archetype === 'cta' && ! empty( $media_references ) && $media_intent !== 'forbidden' && $media_intent !== 'conflict';
	if ( $archetype === 'hero' && ! $hero_has_media && $explicit_composition === null && $media_intent !== 'conflict' ) {
		$composition = 'stacked_left';
	}
	if ( $cta_has_media && $explicit_composition === null ) {
		$composition = 'split_60_40';
	}
	$surface_override = strtolower( trim( (string) wpae_design_plan_constraint_value( $brief, 'surface_color', '' ) ) );
	if ( ! preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) {
		$surface_override = '';
	}
	$eyebrow_presentation = (string) wpae_design_plan_constraint_value( $brief, 'eyebrow_presentation', '' );
	$media_side = (string) wpae_design_plan_constraint_value( $brief, 'media_side', 'right' );
	if ( ! in_array( $media_side, [ 'left', 'right' ], true ) ) {
		$media_side = 'right';
	}
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
		'surface_token' => in_array( $archetype, [ 'pricing' ], true ) ? 'color.surface' : 'color.page_bg',
		'spacing_token' => in_array( $archetype, [ 'services', 'team', 'testimonials', 'cta' ], true ) ? 'space.component' : 'space.section',
		'children' => [],
		'provenance' => [ 'source' => 'brief' ],
	];
	if ( $surface_override !== '' && $archetype !== 'faq' ) {
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
		$section['media_side'] = $media_side;
		$copy_group = [
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
		][0];
		$section['children'] = [ $copy_group ];
		if ( $hero_has_media ) {
			$media_child = [
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
			if ( $media_side === 'left' ) {
				array_unshift( $section['children'], $media_child );
			} else {
				$section['children'][] = $media_child;
			}
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
		$pricing_refs = [];
		foreach ( (array) ( $brief['pricing_items'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$pricing_refs[] = array_intersect_key( $item, array_flip( [ 'label_ref', 'price_ref', 'period_ref', 'description_ref', 'cta_ref', 'price_text', 'provenance' ] ) );
		}
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title' ] );
		$section['children'] = [
			[
				'role' => 'pricing_cards',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => $intro_refs,
				'items' => $pricing_refs,
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
		$radius = (string) wpae_design_plan_constraint_value( $brief, 'border_radius', '12px' );
		$section['children'][] = [
			'role' => 'faq_surface',
			'allowed_widgets' => [ 'accordion' ],
			'content_refs' => array_values( array_reduce( $qa['items'], static function ( array $refs, array $item ): array {
				return array_merge( $refs, [ $item['question_ref'], $item['answer_ref'] ] );
			}, [] ) ),
			'items' => $qa['items'],
			'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.border', 'radius.card', 'space.component' ],
			'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'surface_override' => $surface_override !== '' ? $surface_override : '#ffffff', 'border_radius' => $radius ],
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
	} elseif ( $archetype === 'services' ) {
		$grouped = wpae_design_plan_grouped_items( $brief, 'service', [ 'service_title' => 'title_ref', 'service_body' => 'body_ref', 'service_cta' => 'cta_ref' ], [ 'title_ref', 'body_ref' ], 2, 6 );
		if ( ! in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) ) {
			$service_defaults = array_column( wpae_design_plan_default_service_media(), null, 'group_id' );
			foreach ( $grouped['items'] as &$service_item ) {
				if ( ! empty( $service_item['media_ref'] ) ) {
					continue;
				}
				$group_id = sanitize_key( (string) ( $service_item['group_id'] ?? '' ) );
				$default = $service_defaults[ $group_id ] ?? null;
				if ( ! is_array( $default ) ) {
					continue;
				}
				$default['role'] = 'card_image';
				$default['attachment_id'] = null;
				$default['focal_point'] = null;
				$default['crop'] = '4:3';
				$default['object_fit'] = 'cover';
				$default['license'] = 'Unsplash License';
				$default['attribution'] = '';
				$default['allowed_reuse'] = true;
				$default['provenance'] = [ 'source' => 'plugin_default', 'catalog' => 'wpae-service-media-v1' ];
				$media_references[] = $default;
				$service_item['media_ref'] = $default['asset_id'];
			}
			unset( $service_item );
		}
		$service_widgets = [ 'container', 'heading', 'text-editor', 'button' ];
		if ( (bool) array_filter( $grouped['items'], static fn( array $item ): bool => ! empty( $item['media_ref'] ) ) ) {
			$service_widgets[] = 'image';
		}
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [ 'role' => 'copy_group', 'allowed_widgets' => [ 'heading' ], 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.body' ], 'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ], 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ] ];
		}
		$section['children'][] = [
				'role' => 'service_cards',
				'allowed_widgets' => $service_widgets,
				'content_refs' => wpae_design_plan_group_refs( $grouped['items'], [ 'title_ref', 'body_ref', 'cta_ref' ] ),
				'items' => $grouped['items'],
				'item_errors' => $grouped['errors'],
				'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.border', 'radius.card', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text', 'url' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'service_title', 'service_body', 'service_cta' ] ],
			];
	} elseif ( $archetype === 'team' ) {
		$grouped = wpae_design_plan_grouped_items( $brief, 'team', [ 'team_name' => 'name_ref', 'team_position' => 'position_ref', 'team_bio' => 'bio_ref' ], [ 'name_ref', 'position_ref' ], 1, 8 );
		$team_widgets = [ 'container', 'heading', 'text-editor' ];
		if ( (bool) array_filter( $grouped['items'], static fn( array $item ): bool => ! empty( $item['media_ref'] ) ) ) {
			$team_widgets[] = 'image';
		}
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [ 'role' => 'copy_group', 'allowed_widgets' => [ 'heading' ], 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.body' ], 'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ], 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ] ];
		}
		$section['children'][] = [
				'role' => 'team_cards',
				'allowed_widgets' => $team_widgets,
				'content_refs' => wpae_design_plan_group_refs( $grouped['items'], [ 'name_ref', 'position_ref', 'bio_ref' ] ),
				'items' => $grouped['items'],
				'item_errors' => $grouped['errors'],
				'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.border', 'radius.card', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text', 'media', 'alt' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'team_name', 'team_position', 'team_bio' ] ],
			];
	} elseif ( $archetype === 'testimonials' ) {
		$grouped = wpae_design_plan_grouped_items( $brief, 'testimonial', [ 'testimonial_quote' => 'quote_ref', 'testimonial_author' => 'author_ref', 'testimonial_meta' => 'meta_ref', 'testimonial_rating' => 'rating_ref' ], [ 'quote_ref', 'author_ref' ], 1, 6 );
		$testimonial_widgets = [ 'container', 'heading', 'text-editor' ];
		if ( (bool) array_filter( $grouped['items'], static fn( array $item ): bool => ! empty( $item['media_ref'] ) ) ) {
			$testimonial_widgets[] = 'image';
		}
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [ 'role' => 'copy_group', 'allowed_widgets' => [ 'heading' ], 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.body' ], 'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ], 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ] ];
		}
		$section['children'][] = [
				'role' => 'testimonial_cards',
				'allowed_widgets' => $testimonial_widgets,
				'content_refs' => wpae_design_plan_group_refs( $grouped['items'], [ 'quote_ref', 'author_ref', 'meta_ref' ] ),
				'items' => $grouped['items'],
				'item_errors' => $grouped['errors'],
				'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.border', 'radius.card', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'testimonial_quote', 'testimonial_author', 'testimonial_meta' ] ],
			];
	} elseif ( $archetype === 'cta' ) {
		$cta_content = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'body', 'cta', 'cta_2' ] );
		$button_items = array_values( array_filter( (array) ( $brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2' ], true ) ) );
		$alignment = (string) wpae_design_plan_constraint_value( $brief, 'cta_alignment', 'left' );
		$section['media_intent'] = $media_intent;
		$section['media_side'] = $media_side;
		$section['children'] = [
			[
				'role' => 'cta_copy_group',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => $cta_content,
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'color.surface', 'type.display', 'type.body', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'text_align' => $alignment ],
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text', 'url' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'title', 'body', 'cta', 'cta_2' ] ],
			],
		];
		if ( $cta_has_media ) {
			$media_ids = array_values( array_map( static fn( $item ): string => sanitize_key( (string) ( $item['asset_id'] ?? '' ) ), $media_references ) );
			if ( count( $media_ids ) !== 1 ) {
				$section['cta_errors'] = [ 'requires_exactly_one_media_asset' ];
			}
			$media_child = [
				'role' => 'media',
				'allowed_widgets' => [ 'image' ],
				'content_refs' => [],
				'token_refs' => [ 'color.surface', 'color.border', 'radius.card' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ],
				'responsive_policy' => 'copy_first_stack',
				'media_refs' => array_slice( $media_ids, 0, 1 ),
				'editable_fields' => [ 'media', 'alt' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'media' ] ],
			];
			if ( $media_side === 'left' ) {
				array_unshift( $section['children'], $media_child );
			} else {
				$section['children'][] = $media_child;
			}
		}
		if ( count( $button_items ) > 2 ) {
			$section['cta_errors'][] = 'more_than_two_buttons';
		}
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
			'mobile' => ( $archetype === 'hero' && $hero_has_media ) || ( $archetype === 'cta' && $cta_has_media ) ? 'copy_first_stack' : 'stack',
		],
		'tokens' => $tokens,
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
		'media_references' => array_values( $media_references ),
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

function wpae_design_plan_services_recipe_validate( array $plan, array $brief ): array {
	$errors = [];
	$recipe_id = (string) ( $plan['recipe_id'] ?? '' );
	$recipe_roles = [ 'services.photo_cards' => 'services_photo_grid', 'services.split_editorial' => 'services_split_editorial', 'services.text_icon_list' => 'services_text_icon_list' ];
	if ( ! isset( $recipe_roles[ $recipe_id ] ) ) {
		$errors[] = 'services_recipe_unknown';
		return $errors;
	}
	if ( ( $plan['archetype'] ?? '' ) !== 'services' ) {
		$errors[] = 'services_recipe_archetype_mismatch';
	}
	$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
	$children = array_values( array_filter( (array) ( $section['children'] ?? [] ), 'is_array' ) );
	$recipe_children = array_values( array_filter( $children, static fn( array $child ): bool => ( $child['role'] ?? '' ) === $recipe_roles[ $recipe_id ] ) );
	if ( count( $recipe_children ) !== 1 || (bool) array_filter( $children, static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'service_cards' ) ) {
		$errors[] = 'services_recipe_topology_invalid';
	}
	$recipe = $recipe_children[0] ?? [];
	$expected_groups = array_values( array_filter( (array) ( $brief['groups'] ?? [] ), 'is_array' ) );
	if ( empty( $expected_groups ) ) {
		$expected_groups = wpae_design_plan_grouped_items( $brief, 'service', [ 'service_title' => 'title_ref', 'service_body' => 'body_ref', 'service_cta' => 'cta_ref' ], [ 'title_ref', 'body_ref' ], 2, 6 )['items'];
	}
	$expected_by_id = [];
	foreach ( $expected_groups as $group ) {
		$id = sanitize_key( (string) ( $group['group_id'] ?? '' ) );
		if ( $id === '' || isset( $expected_by_id[ $id ] ) ) {
			$errors[] = 'services_brief_group_duplicate_or_invalid';
			continue;
		}
		$expected_by_id[ $id ] = $group;
	}
	$items = array_values( (array) ( $recipe['items'] ?? [] ) );
	if ( count( $items ) < 2 || count( $items ) > 6 || count( $items ) !== count( $expected_by_id ) ) {
		$errors[] = 'services_recipe_items_out_of_range_or_incomplete';
	}
	$seen = [];
	$media_by_id = [];
	foreach ( (array) ( $plan['media_references'] ?? [] ) as $media ) {
		if ( ! is_array( $media ) ) {
			$errors[] = 'services_recipe_media_reference_invalid';
			continue;
		}
		$id = trim( (string) ( $media['asset_id'] ?? '' ) );
		if ( $id === '' || isset( $media_by_id[ $id ] ) || ! wpae_design_plan_media_reference_valid( $media ) ) {
			$errors[] = 'services_recipe_media_reference_invalid';
			continue;
		}
		$media_by_id[ $id ] = $media;
	}
	$consumed = [];
	$lead_ref = trim( (string) ( $plan['recipe_selection']['lead_service_ref'] ?? '' ) );
	foreach ( $items as $index => $item ) {
		if ( ! is_array( $item ) ) {
			$errors[] = 'services_recipe_item_' . ( $index + 1 ) . '_invalid';
			continue;
		}
		$group_id = sanitize_key( (string) ( $item['group_id'] ?? '' ) );
		if ( $group_id === '' || isset( $seen[ $group_id ] ) || ! isset( $expected_by_id[ $group_id ] ) ) {
			$errors[] = 'services_recipe_item_' . ( $index + 1 ) . '_group_invalid';
			continue;
		}
		$seen[ $group_id ] = true;
		$expected = $expected_by_id[ $group_id ];
		foreach ( [ 'title_ref', 'body_ref', 'cta_ref' ] as $field ) {
			$ref = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
			$expected_ref = sanitize_key( (string) ( $expected[ $field ] ?? '' ) );
			if ( $ref !== $expected_ref ) {
				$errors[] = $group_id . '_recipe_' . $field . '_mismatch';
			}
		}
		if ( $recipe_id === 'services.photo_cards' || ( $recipe_id === 'services.split_editorial' && $group_id === $lead_ref ) ) {
			$media_ref = trim( (string) ( $item['media_ref'] ?? '' ) );
			$media = $media_by_id[ $media_ref ] ?? [];
			if ( $media_ref === '' || empty( $media ) || trim( (string) ( $media['alt'] ?? '' ) ) === '' ) {
				$errors[] = $group_id . '_recipe_photo_missing_or_invalid';
			} else {
				$consumed[] = $media_ref;
			}
		} elseif ( array_key_exists( 'media_ref', $item ) && trim( (string) $item['media_ref'] ) !== '' ) {
			$errors[] = $group_id . '_recipe_unexpected_media_ref';
		}
	}
	if ( count( $seen ) !== count( $expected_by_id ) ) {
		$errors[] = 'services_recipe_source_items_not_preserved';
	}
	foreach ( (array) ( $recipe['item_errors'] ?? [] ) as $error ) {
		$errors[] = 'services_recipe_' . sanitize_key( (string) $error );
	}
	$compatibility = is_array( $plan['media_compatibility'] ?? null ) ? $plan['media_compatibility'] : [];
	$provided = array_values( array_map( 'strval', (array) ( $compatibility['provided_asset_refs'] ?? [] ) ) );
	$declared_consumed = array_values( array_map( 'strval', (array) ( $compatibility['consumed_asset_refs'] ?? [] ) ) );
	$unconsumed = array_values( array_map( 'strval', (array) ( $compatibility['unconsumed_asset_refs'] ?? [] ) ) );
	if ( count( $provided ) !== count( array_unique( $provided ) ) || count( $declared_consumed ) !== count( array_unique( $declared_consumed ) ) || count( $unconsumed ) !== count( array_unique( $unconsumed ) ) || array_diff( $provided, array_keys( $media_by_id ) ) || array_diff( array_keys( $media_by_id ), $provided ) ) {
		$errors[] = 'services_recipe_media_accounting_invalid';
	}
	if ( array_diff( $consumed, $declared_consumed ) || array_diff( $declared_consumed, $consumed ) || array_diff( array_merge( $declared_consumed, $unconsumed ), $provided ) || array_diff( $provided, array_merge( $declared_consumed, $unconsumed ) ) ) {
		$errors[] = 'services_recipe_media_accounting_mismatch';
	}
	$media_intent = (string) ( $plan['media_intent'] ?? 'unspecified' );
	if ( $recipe_id === 'services.photo_cards' && in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) ) {
		$errors[] = 'photo_cards_media_intent_incompatible';
	}
	if ( $recipe_id === 'services.split_editorial' ) {
		if ( ! isset( $expected_by_id[ $lead_ref ] ) || count( $consumed ) !== 1 ) {
			$errors[] = 'split_editorial_lead_binding_invalid';
		}
	}
	if ( $recipe_id === 'services.text_icon_list' && ( $media_intent === 'required' || ! empty( $consumed ) ) ) {
		$errors[] = 'text_icon_list_media_incompatible';
	}
	if ( $recipe_id === 'services.text_icon_list' && $media_intent === 'required' ) {
		$errors[] = 'text_icon_list_incompatible_with_required_media';
	}
	if ( $media_intent === 'required' && ! empty( $unconsumed ) ) {
		$errors[] = 'required_media_assets_unconsumed';
	}
	return array_values( array_unique( $errors ) );
}

function wpae_design_plan_validate( array $plan, array $brief = [] ): array {
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
	if ( ( $plan['archetype'] ?? '' ) === 'cta' ) {
		$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
		$media_nodes = array_values( array_filter( (array) ( $section['children'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'media' ) );
		$has_media = ! empty( $media_nodes );
		$split = in_array( (string) ( $section['composition'] ?? '' ), [ 'split_60_40', 'split_50_50', 'split_40_60' ], true );
		$media_intent = sanitize_key( (string) ( $section['media_intent'] ?? 'unspecified' ) );
		if ( $media_intent === 'conflict' ) {
			$errors[] = 'media_intent_conflict';
		}
		if ( $media_intent === 'forbidden' && $has_media ) {
			$errors[] = 'forbidden_media_in_plan';
		}
		if ( $media_intent === 'required' && ! $has_media ) {
			$errors[] = 'required_media_asset_missing';
		}
		if ( $has_media && ! $split ) {
			$errors[] = 'cta_media_requires_split_composition';
		}
		if ( count( $media_nodes ) > 1 || in_array( 'requires_exactly_one_media_asset', (array) ( $section['cta_errors'] ?? [] ), true ) ) {
			$errors[] = 'cta_requires_exactly_one_media_asset';
		}
	}
	if ( array_key_exists( 'recipe_id', $plan ) && ( $plan['archetype'] ?? '' ) !== 'services' ) {
		$errors[] = in_array( (string) ( $plan['recipe_id'] ?? '' ), wpae_design_plan_services_recipe_ids(), true ) ? 'services_recipe_archetype_mismatch' : 'services_recipe_unknown';
	}
	if ( $brief ) {
		$used_media_ids = [];
		foreach ( (array) ( $plan['sections'] ?? [] ) as $section ) {
			foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
				$used_media_ids = array_merge( $used_media_ids, (array) ( $child['media_refs'] ?? [] ) );
				foreach ( (array) ( $child['items'] ?? [] ) as $item ) {
					if ( ! empty( $item['media_ref'] ) ) {
						$used_media_ids[] = (string) $item['media_ref'];
					}
				}
			}
		}
		$media_by_id = [];
		foreach ( array_merge( (array) ( $brief['media_references'] ?? [] ), (array) ( $plan['media_references'] ?? [] ) ) as $media ) {
			if ( is_array( $media ) ) {
				$media_by_id[ sanitize_key( (string) ( $media['asset_id'] ?? '' ) ) ] = $media;
			}
		}
		foreach ( array_unique( array_map( 'sanitize_key', $used_media_ids ) ) as $asset_id ) {
			$media = $media_by_id[ $asset_id ] ?? [];
			if ( empty( $media ) ) {
				continue;
			}
			if ( preg_match( '#^https?://images\.unsplash\.com/#i', (string) ( $media['source_url'] ?? '' ) ) && empty( $media['allowed_reuse'] ) ) {
				$errors[] = 'media_unsplash_license_unconfirmed_' . $asset_id;
			}
			if ( in_array( (string) ( $media['role'] ?? '' ), [ 'hero', 'portrait' ], true ) && trim( (string) ( $media['alt'] ?? '' ) ) === '' ) {
				$errors[] = 'media_alt_required_' . $asset_id;
			}
			if ( ( $plan['archetype'] ?? '' ) === 'cta' && trim( (string) ( $media['alt'] ?? '' ) ) === '' ) {
				$errors[] = 'cta_media_alt_required_' . $asset_id;
			}
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
	if ( ( $plan['archetype'] ?? '' ) === 'pricing' ) {
		$items = [];
		foreach ( (array) ( $plan['sections'][0]['children'] ?? [] ) as $child ) {
			if ( is_array( $child ) && ( $child['role'] ?? '' ) === 'pricing_cards' ) {
				$items = array_values( (array) ( $child['items'] ?? [] ) );
			}
		}
		if ( count( $items ) < 2 ) {
			$errors[] = 'pricing_tiers_required';
		}
		foreach ( $items as $index => $item ) {
			foreach ( [ 'label_ref', 'price_ref' ] as $field ) {
				if ( trim( (string) ( $item[ $field ] ?? '' ) ) === '' ) {
					$errors[] = 'pricing_tier_' . ( $index + 1 ) . '_missing_' . $field;
				}
			}
		}
	}
	if ( ( $plan['archetype'] ?? '' ) === 'faq' ) {
		$section = is_array( $plan['sections'][0] ?? null ) ? $plan['sections'][0] : [];
		$accordion = null;
		foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
			if ( is_array( $child ) && ( $child['role'] ?? '' ) === 'faq_surface' ) {
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
	$grouped_requirements = [
		'services' => [ 'role' => 'service_cards', 'minimum' => 2, 'maximum' => 6, 'required' => [ 'title_ref', 'body_ref' ], 'prefix' => 'services' ],
		'team' => [ 'role' => 'team_cards', 'minimum' => 1, 'maximum' => 8, 'required' => [ 'name_ref', 'position_ref' ], 'prefix' => 'team' ],
		'testimonials' => [ 'role' => 'testimonial_cards', 'minimum' => 1, 'maximum' => 6, 'required' => [ 'quote_ref', 'author_ref' ], 'prefix' => 'testimonials' ],
	];
	if ( isset( $grouped_requirements[ $plan['archetype'] ?? '' ] ) ) {
		$requirement = $grouped_requirements[ $plan['archetype'] ];
	if ( ( $plan['archetype'] ?? '' ) === 'services' ) {
		if ( array_key_exists( 'recipe_id', $plan ) ) {
			$errors = array_merge( $errors, wpae_design_plan_services_recipe_validate( $plan, $brief ) );
		}
		foreach ( (array) ( $brief['ambiguities'] ?? [] ) as $ambiguity ) {
				if ( ! is_array( $ambiguity ) || ! in_array( (string) ( $ambiguity['kind'] ?? '' ), [ 'incomplete_service_pair', 'duplicate_service_index' ], true ) ) {
					continue;
				}
				$errors[] = 'services_ambiguous_input';
				break;
			}
		}
		if ( array_key_exists( 'recipe_id', $plan ) ) {
			$cards = null;
			$items = [];
		} else {
		$cards = null;
		foreach ( (array) ( $plan['sections'][0]['children'] ?? [] ) as $child ) {
			if ( is_array( $child ) && ( $child['role'] ?? '' ) === $requirement['role'] ) {
				$cards = $child;
				break;
			}
		}
		$items = array_values( (array) ( $cards['items'] ?? [] ) );
		if ( count( $items ) < $requirement['minimum'] || count( $items ) > $requirement['maximum'] ) {
			$errors[] = $requirement['prefix'] . '_items_out_of_range';
		}
		foreach ( (array) ( $cards['item_errors'] ?? [] ) as $item_error ) {
			$errors[] = $requirement['prefix'] . '_' . sanitize_key( (string) $item_error );
		}
		foreach ( $items as $index => $item ) {
			foreach ( $requirement['required'] as $field ) {
				$reference = sanitize_key( (string) ( $item[ $field ] ?? '' ) );
				if ( $reference === '' ) {
					$errors[] = $requirement['prefix'] . '_item_' . ( $index + 1 ) . '_missing_' . sanitize_key( $field );
				}
			}
			if ( ( $plan['archetype'] ?? '' ) === 'services' && ! empty( $item['cta_ref'] ) ) {
				$cta = [];
				foreach ( (array) ( $brief['content'] ?? [] ) as $content_item ) {
					if ( is_array( $content_item ) && ( $content_item['id'] ?? '' ) === $item['cta_ref'] ) {
						$cta = $content_item;
						break;
					}
				}
				if ( empty( $cta['url_requested'] ) || trim( (string) ( $cta['url'] ?? '' ) ) === '' ) {
					$errors[] = 'services_item_' . ( $index + 1 ) . '_cta_url_required';
				}
			}
		}
		}
	}
	if ( ( $plan['archetype'] ?? '' ) === 'cta' ) {
		$content = (array) ( $brief['content'] ?? [] );
		$has_title = (bool) array_filter( $content, static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === 'title' && trim( (string) ( $item['exact_text'] ?? '' ) ) !== '' );
		$buttons = array_values( array_filter( $content, static fn( $item ): bool => is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2' ], true ) ) );
		if ( ! $has_title ) {
			$errors[] = 'cta_heading_required';
		}
		if ( count( $buttons ) < 1 || count( $buttons ) > 2 ) {
			$errors[] = 'cta_requires_one_or_two_buttons';
		}
		foreach ( $buttons as $index => $button ) {
			if ( empty( $button['url_requested'] ) || trim( (string) ( $button['url'] ?? '' ) ) === '' ) {
				$errors[] = 'cta_button_' . ( $index + 1 ) . '_explicit_url_required';
			}
		}
		foreach ( (array) ( $plan['sections'][0]['cta_errors'] ?? [] ) as $cta_error ) {
			$errors[] = 'cta_' . sanitize_key( (string) $cta_error );
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
