<?php

/** Typed DesignPlan v1. Provider output stops at this boundary. */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/elementor/recipes.php';

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
		'archetypes' => [ 'about', 'hero', 'process', 'pricing', 'faq', 'benefits', 'services', 'team', 'testimonials', 'cta' ],
		'migrated_create' => [ 'hero', 'about', 'benefits', 'pricing', 'faq', 'services', 'team', 'testimonials', 'process' ],
		'family_compositions' => wpae_composition_family_compositions(),
		'compositions' => [ 'editorial_list', 'photo_cards', 'split_editorial', 'split_60_40', 'split_50_50', 'split_40_60', 'stacked_left', 'linear', 'three_cards', 'icon_cards', 'ordered_timeline' ],
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

/** Restrict frozen geometry to simple, bounded native lengths. */
function wpae_design_plan_layout_measure_valid( $value, bool $allow_percent = false ): bool {
	if ( ! is_string( $value ) && ! is_numeric( $value ) ) { return false; }
	$value = trim( (string) $value );
	if ( ! preg_match( '/^(?:0|[1-9]\d*(?:\.\d+)?|0\.\d+)(px|rem|em|%)$/', $value, $matches ) ) { return false; }
	if ( $matches[1] === '%' && ! $allow_percent ) { return false; }
	$number = (float) substr( $value, 0, -strlen( $matches[1] ) );
	return $number >= 0 && $number <= ( $matches[1] === '%' ? 100 : 1600 );
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
	$step_items = [];
	$badge_ref = '';
	foreach ( $items as $item ) {
		$role = sanitize_key( (string) ( $item['role'] ?? '' ) );
		if ( $role === 'eyebrow' && $badge_ref === '' ) {
			$badge_ref = sanitize_key( (string) $item['id'] );
			continue;
		}
		if ( in_array( $role, [ 'label', 'text' ], true ) ) {
			$step_items[] = $item;
		}
	}
	for ( $index = 0, $count = count( $step_items ); $index < $count; ) {
		$current = $step_items[ $index ];
		$next = $step_items[ $index + 1 ] ?? null;
		$current_span = (array) ( $current['source_span'] ?? [] );
		$next_span = is_array( $next ) ? (array) ( $next['source_span'] ?? [] ) : [];
		$gap = is_array( $next ) && isset( $current_span[1], $next_span[0] )
			? substr( $source, (int) $current_span[1], max( 0, (int) $next_span[0] - (int) $current_span[1] ) )
			: '';
		$current_group = sanitize_key( (string) ( $current['group_id'] ?? '' ) );
		$next_group = is_array( $next ) ? sanitize_key( (string) ( $next['group_id'] ?? '' ) ) : '';
		$typed_pair = is_array( $next ) && ( $current['role'] ?? '' ) === 'label' && ( $next['role'] ?? '' ) === 'text' && $current_group !== '' && $current_group === $next_group;
		$source_pair = is_array( $next ) && preg_match( '/^\s*(?::|[—–]|->|→)\s*$/u', $gap );
		if ( is_array( $next ) && ( $typed_pair || $source_pair ) ) {
			$steps[] = [
				'group_id' => $current_group !== '' ? $current_group : $next_group,
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
	if ( ! empty( $steps ) ) {
		foreach ( $unpaired as $unpaired_index => $item ) {
			$span = (array) ( $item['source_span'] ?? [] );
			$prefix = isset( $span[0] ) ? substr( $source, 0, (int) $span[0] ) : '';
			if ( $badge_ref === '' && preg_match( '/(?:блок\s+процесс\w*|process\s+block)\s*[«"“]?\s*$/iu', $prefix ) ) {
				$badge_ref = sanitize_key( (string) $item['id'] );
				unset( $unpaired[ $unpaired_index ] );
				break;
			}
		}
	}
	foreach ( $unpaired as $item ) {
		$span = (array) ( $item['source_span'] ?? [] );
		$steps[] = [
			'group_id' => sanitize_key( (string) ( $item['group_id'] ?? '' ) ),
			'label_ref' => sanitize_key( (string) $item['id'] ),
			'text_ref' => '',
			'provenance' => [ 'source' => 'brief', 'source_spans' => [ $span ] ],
		];
	}
	$starts = [];
	foreach ( $step_items as $item ) {
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
			$first_item = array_column( (array) ( $brief['content'] ?? [] ), null, 'id' )[ $pending ] ?? [];
			$items[] = [ 'group_id' => (string) ( $first_item['group_id'] ?? '' ), $first_key => $pending, $second_key => sanitize_key( (string) ( $item['id'] ?? '' ) ) ];
			$pending = null;
		}
	}
	if ( $pending !== null ) {
		$errors[] = 'unpaired_' . $first_role;
	}
	return [ 'items' => $items, 'errors' => array_values( array_unique( $errors ) ) ];
}

/** Select an available collection presentation from canonical copy, before the Plan is frozen. */
function wpae_design_plan_grouped_items( array $brief, string $prefix, array $role_map, array $required_fields, int $minimum, int $maximum ): array {
	if ( ! empty( $brief['canonical_create'] ) && in_array( $prefix, [ 'team', 'testimonial' ], true ) ) {
		$items = (array) ( $brief['groups'] ?? [] ); $errors = [];
		foreach ( $items as $item ) { foreach ( (array) ( $item['errors'] ?? [] ) as $error ) { $errors[] = $item['group_id'] . '_' . $error; } foreach ( $required_fields as $field ) { if ( empty( $item[$field] ) ) { $errors[] = $item['group_id'] . '_missing_' . $field; } } }
		if ( count( $items ) < $minimum || count( $items ) > $maximum ) { $errors[] = $prefix . '_item_count_out_of_range'; }
		return [ 'items' => $items, 'errors' => array_values( array_unique( $errors ) ) ];
	}
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
	return [ 'services.photo_cards', 'services.split_editorial', 'services.text_icon_list', 'services.icon_cards' ];
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
		'sha256' => '52668f62027ad9b6085421b976a8bd1fb680e8718a1a502dacc3e7cf501a9405',
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
	$selected_composition_record = trim( (string) ( $context['composition_record'] ?? '' ) );
	foreach ( array_unique( array_filter( [ $explicit_context_recipe, str_starts_with( $selected_composition_record, 'services.' ) ? $selected_composition_record : '' ] ) ) as $context_recipe_id ) {
		$recognized[] = [ 'recipe_id' => $context_recipe_id, 'source' => 'explicit_context' ];
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
	if ( $explicit_context_recipe !== '' || str_starts_with( $selected_composition_record, 'services.' ) ) {
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
	$selection = (array) ( $context['composition_decision'] ?? [] );
	$catalog_media_pool = $media;
	if ( $recipe_id === '' && empty( $selection ) ) {
		$selection_context = $context;
		if ( $lead_ref !== '' ) { $selection_context['services_lead_service_ref'] = $lead_ref; }
		$selection_context['candidate_media_by_record'] = (array) ( $selection_context['candidate_media_by_record'] ?? [] );
		foreach ( [ 'services.photo_cards', 'services.split_editorial' ] as $candidate_id ) {
			$candidate_media = wpae_design_plan_services_recipe_catalog_media( $brief, $candidate_id, $lead_ref, $catalog_media_pool );
			if ( $candidate_media ) { $selection_context['candidate_media_by_record'][$candidate_id] = $candidate_media; }
		}
		if ( $explicit_recipe_id !== '' ) { $selection_context['composition_record'] = $explicit_recipe_id; }
		$selection = wpae_composition_decide( $brief, $selection_context );
		if ( ! empty( $selection['errors'] ) ) {
			return [ 'ok' => false, 'recipe_id' => '', 'source' => $source, 'library_required' => $library_policy === 'required', 'fallback_policy' => $fallback_policy, 'media_status' => 'unresolved', 'reason' => implode( ',', array_map( 'sanitize_key', (array) $selection['errors'] ) ), 'composition_decision' => $selection ];
		}
		$recipe_id = (string) $selection['record']['id'];
		$source = (string) $selection['source'];
		$selection['candidate_media_by_record'] = $selection_context['candidate_media_by_record'];
	} elseif ( empty( $selection ) ) {
		$selection_context = array_merge( $context, [ 'composition_record' => $recipe_id ] );
		if ( $lead_ref !== '' ) { $selection_context['services_lead_service_ref'] = $lead_ref; }
		$selection_context['candidate_media_by_record'] = (array) ( $selection_context['candidate_media_by_record'] ?? [] );
		$candidate_media = wpae_design_plan_services_recipe_catalog_media( $brief, $recipe_id, $lead_ref, $catalog_media_pool );
		if ( $candidate_media ) { $selection_context['candidate_media_by_record'][$recipe_id] = $candidate_media; }
		$selection = wpae_composition_decide( $brief, $selection_context );
		if ( ! empty( $selection['errors'] ) ) {
			$missing_split_lead = $recipe_id === 'services.split_editorial' && $lead_ref === '';
			return [ 'ok' => false, 'recipe_id' => $recipe_id, 'source' => $source, 'library_required' => $library_policy === 'required', 'fallback_policy' => $fallback_policy, 'media_status' => 'unresolved', 'reason' => $missing_split_lead ? 'services_split_editorial_lead_service_ref_required' : implode( ',', array_map( 'sanitize_key', (array) $selection['errors'] ) ), 'composition_decision' => $selection ];
		}
	}
	$decision_context = $selection ? wpae_composition_decision_for_record( $brief, $selection, $recipe_id ) : [];
	if ( ! empty( $decision_context['errors'] ) ) {
		return [ 'ok' => false, 'recipe_id' => $recipe_id, 'source' => $source, 'library_required' => $library_policy === 'required', 'fallback_policy' => $fallback_policy, 'media_status' => 'unresolved', 'reason' => implode( ',', array_map( 'sanitize_key', (array) $decision_context['errors'] ) ), 'composition_decision' => $decision_context ];
	}
	$catalog_media = (array) ( $selection['candidate_media_by_record'][$recipe_id] ?? [] );
	if ( ! $catalog_media ) { $catalog_media = wpae_design_plan_services_recipe_catalog_media( $brief, $recipe_id, $lead_ref, $media ); }
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
	$icon_recipe_media_invalid = $recipe_id === 'services.icon_cards' && $media_intent === 'required';
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
	if ( $icon_recipe_media_invalid ) {
		$media_status = 'unresolved';
	}
	if ( $library_policy === 'required' ) {
		return [ 'ok' => $lead_valid, 'recipe_id' => $recipe_id, 'source' => $source, 'lead_service_ref' => $lead_ref, 'media_references' => $media, 'library_required' => true, 'library_template_id' => (string) $map['template_id'], 'library_applied' => false, 'library_mapping' => $map, 'fallback_policy' => $fallback_policy, 'media_status' => $media_status, 'media_missing_service_ids' => $missing, 'reason' => $lead_valid ? '' : 'services_split_editorial_lead_service_ref_required' ];
	}
	return [
		'ok' => in_array( $recipe_id, wpae_design_plan_services_recipe_ids(), true ) && $lead_valid && ! $icon_recipe_media_invalid,
		'recipe_id' => $recipe_id,
		'source' => $source,
		'composition_decision' => $decision_context ?? [],
		'lead_service_ref' => $lead_ref,
		'media_references' => $media,
		'library_required' => false,
		'library_template_id' => '',
		'library_applied' => false,
		'library_mapping' => [],
		'fallback_policy' => $fallback_policy,
		'media_status' => $media_status,
		'media_missing_service_ids' => $missing,
		'reason' => ! $lead_valid ? 'services_split_editorial_lead_service_ref_required' : ( $icon_recipe_media_invalid ? 'services_icon_cards_media_incompatible' : ( $recipe_id === '' ? 'services_recipe_unsupported' : '' ) ),
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
		'services.icon_cards' => 'services_icon_cards',
	][ $recipe_id ] ?? 'unknown_services_recipe';
	$recipe_items = [];
	foreach ( $groups as $group ) {
		$item = [ 'group_id' => (string) $group['group_id'], 'title_ref' => $group['title_ref'], 'body_ref' => $group['body_ref'], 'cta_ref' => $group['cta_ref'] ];
		if ( $recipe_id === 'services.photo_cards' || ( $recipe_id === 'services.split_editorial' && $group['group_id'] === $lead_group_id ) ) {
			$item['media_ref'] = (string) ( $group['media_ref'] ?? '' );
		}
		$recipe_items[] = $item;
	}
	$section = [ 'composition' => 'linear', 'provenance' => [ 'source' => 'brief' ] ];
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
		$children[] = [ 'role' => 'copy_group', 'allowed_widgets' => $intro_allowed_widgets, 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.section_title', 'type.body' ], 'layout_constraints' => $intro_layout_constraints, 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ], 'provenance' => [ 'source' => 'brief', 'roles' => [ 'eyebrow', 'title', 'body' ] ] ];
	}
	$children[] = [
		'role' => $recipe_role,
		'allowed_widgets' => $recipe_id === 'services.photo_cards' ? [ 'container', 'image', 'heading', 'text-editor', 'button' ] : ( $recipe_id === 'services.split_editorial' ? [ 'container', 'image', 'heading', 'text-editor', 'button', 'divider' ] : [ 'container', 'icon', 'heading', 'text-editor', 'button', 'divider' ] ),
		'content_refs' => wpae_design_plan_group_refs( $recipe_items, [ 'title_ref', 'body_ref', 'cta_ref' ] ),
		'items' => $recipe_items,
		'item_errors' => array_values( array_unique( $errors ) ),
		'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.border', 'color.hover', 'color.focus', 'radius.card', 'space.component', 'type.display', 'type.body' ],
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
	$record = wpae_composition_records()[ $recipe_id ] ?? [];
	$selected_recipe_media = [];
	foreach ( $recipe_items as $recipe_item ) {
		$asset_id = trim( (string) ( $recipe_item['media_ref'] ?? '' ) );
		if ( $asset_id !== '' && isset( $media_by_id[ $asset_id ] ) ) { $selected_recipe_media[] = $media_by_id[ $asset_id ]; }
	}
	$record_context = array_merge( $context, [ 'composition_record' => $recipe_id, 'media_references' => $selected_recipe_media ] );
	$accepted_decision = (array) ( $context['composition_decision'] ?? [] );
	$record_selection = $record
		? ( ! empty( $accepted_decision ) ? wpae_composition_decision_for_record( $brief, $accepted_decision, $recipe_id ) : wpae_composition_decide( $brief, $record_context ) )
		: [ 'errors' => [ 'composition_record_missing' ] ];
	if ( empty( $record_selection['errors'] ) && empty( $record_selection['record'] ) ) { $record_selection['record'] = $record; }
	$record_policy = (array) ( $record['policy'] ?? [] );
	$composition = (string) ( $record['composition'] ?? 'linear' );
	$responsive = array_intersect_key( $record_policy, array_flip( [ 'desktop', 'tablet', 'mobile' ] ) );
	if ( empty( $responsive ) ) { $responsive = [ 'desktop' => $composition, 'tablet' => 'stack', 'mobile' => 'stack' ]; }
	$section['composition'] = $composition;
	if ( $children ) {
		$children[ count( $children ) - 1 ]['responsive_policy'] = (string) ( $responsive['tablet'] ?? 'stack' );
	}
	return [
		'schema' => WPAE_DESIGN_PLAN_SCHEMA,
		'archetype' => 'services',
		'recipe_id' => $recipe_id,
		'composition_decision' => array_merge( [ 'identity' => 'services.' . $composition, 'slot_bindings' => $children ], $record_selection, [ 'request_selection' => (array) ( $record_selection['request_selection'] ?? [] ), 'record_id' => $record['id'] ?? '', 'record_version' => $record['version'] ?? 0, 'record_hash' => $record['hash'] ?? '', 'brief_hash' => (string) ( $record_selection['brief_hash'] ?? '' ), 'selection_policy' => (string) ( $record_selection['policy_version'] ?? '' ), 'catalog_id' => (string) ( $record_selection['catalog_id'] ?? 'wpae-compositions-v1' ), 'catalog_identity' => (string) ( $record_selection['catalog_identity'] ?? '' ), 'selection_metrics' => (array) ( $record_selection['metrics'] ?? [] ), 'selection_reasons' => (array) ( $record_selection['selection_reasons'] ?? [] ), 'rejected_candidates' => (array) ( $record_selection['rejected'] ?? [] ), 'alternatives' => (array) ( $record_selection['alternatives'] ?? [] ), 'variant_kind' => $record['variant_kind'] ?? '', 'distinct' => $record['distinct'] ?? false, 'policy' => $record_policy, 'source' => (string) ( $context['services_recipe_selection_source'] ?? $record_selection['source'] ?? 'documented_default' ), 'visual_profile' => (string) ( $record_selection['visual_profile'] ?? '' ), 'errors' => array_values( (array) ( $record_selection['errors'] ?? [] ) ) ] ),
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
		'sections' => [ array_merge( $section, [ 'id' => 'services', 'role' => 'services', 'composition' => $composition, 'surface_token' => 'color.page_bg', 'spacing_token' => 'space.component', 'children' => $children, 'provenance' => [ 'source' => 'brief' ] ] ) ],
		'responsive' => $responsive,
		'tokens' => [ 'color.page_bg' => 'color.page_bg', 'color.surface' => 'color.surface', 'color.text' => 'color.text', 'color.muted' => 'color.muted', 'color.primary' => 'color.primary', 'color.border' => 'color.border', 'color.focus' => 'color.focus', 'color.hover' => 'color.hover', 'type.display' => 'type.display', 'type.section_title' => 'type.section_title', 'type.body' => 'type.body', 'space.section' => 'space.section', 'space.component' => 'space.component', 'radius.card' => 'radius.card' ],
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
		'media_references' => $media,
		'provenance' => [ 'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '', 'planner' => 'wpae-design-plan-v1', 'source' => 'brief-ir' ],
		'warnings' => [],
		'media_intent' => $media_intent,
		'media_asset_count' => count( $media ),
	];
}

/** Resolve role geometry once, before the accepted Plan is hashed. Historical Plans lack this field. */
function wpae_design_plan_visual_policy( array $brief, array $record, array $visual, string $composition, array $responsive, int $item_count = 0, string $services_recipe_id = '' ): array {
	$values = array_replace( wpae_design_token_defaults(), (array) ( $visual['values'] ?? [] ) );
	$record_policy = (array) ( $record['policy'] ?? [] );
	$field_sources = [];
	$pick = static function ( string $key, $fallback ) use ( $brief, $record_policy, $values, &$field_sources ) {
		$explicit = wpae_design_plan_constraint_value( $brief, $key );
		if ( $explicit !== null ) { $field_sources[$key] = 'explicit_brief'; return $explicit; }
		if ( array_key_exists( $key, $record_policy ) ) { $field_sources[$key] = 'composition_record'; return $record_policy[$key]; }
		if ( array_key_exists( 'layout.' . $key, $values ) ) { $field_sources[$key] = 'resolved_visual'; return $values['layout.' . $key]; }
		$field_sources[$key] = 'documented_default';
		return $fallback;
	};
	$split = in_array( $composition, [ 'split_60_40', 'split_50_50', 'split_40_60' ], true );
	$family = (string) ( $brief['intent']['archetype'] ?? '' );
	$explicit_intro_token = '';
	foreach ( (array) ( $brief['layout_constraints'] ?? [] ) as $constraint ) {
		if ( is_array( $constraint ) && ( $constraint['kind'] ?? '' ) === 'visual_token' && in_array( (string) ( $constraint['token'] ?? '' ), [ 'type.display', 'type.section_title' ], true ) ) { $explicit_intro_token = (string) $constraint['token']; }
	}
	$record_intro_token = trim( (string) ( $record_policy['intro_title_token'] ?? '' ) );
	$profile_intro_token = trim( (string) ( $values['layout.intro_title_token'] ?? '' ) );
	if ( $explicit_intro_token !== '' ) {
		$intro_title_token = $explicit_intro_token;
		$field_sources['intro_title_token'] = 'explicit_brief';
	} elseif ( in_array( $record_intro_token, [ 'type.display', 'type.section_title' ], true ) ) {
		$intro_title_token = $record_intro_token;
		$field_sources['intro_title_token'] = 'composition_record';
	} elseif ( in_array( $profile_intro_token, [ 'type.display', 'type.section_title' ], true ) ) {
		$intro_title_token = $profile_intro_token;
		$field_sources['intro_title_token'] = 'resolved_visual';
	} else {
		$intro_title_token = $family === 'hero' ? 'type.display' : 'type.section_title';
		$field_sources['intro_title_token'] = 'documented_default';
	}
	$body = $values['type.body'];
	$item = $values['type.feature'] ?? array_replace( $body, [ 'desktop' => '1.125rem', 'tablet' => '1.125rem', 'mobile' => '1.125rem', 'weight' => '600', 'line_height' => '1.3' ] );
	$eyebrow = array_replace( $body, [ 'desktop' => '0.75rem', 'tablet' => '0.75rem', 'mobile' => '0.75rem', 'weight' => '600', 'line_height' => '1.2' ] );
	$entity_variant = (string) ( $record_policy['entity_layout'] ?? '' );
	$is_list = $composition === 'editorial_list' || $services_recipe_id === 'services.text_icon_list' || $entity_variant === 'editorial_rows';
	$item_count = $item_count ?: ( $family === 'process'
		? count( (array) ( wpae_design_plan_process_content( $brief )['steps'] ?? [] ) )
		: count( (array) ( $brief['groups'] ?? [] ) ) );
	$columns = min( 3, max( 1, $item_count ) );
	if ( in_array( $item_count, [ 4, 6 ], true ) ) { $columns = 2; }
	$record_columns = (array) ( $record_policy['collection_columns'] ?? [] );
	$desktop_column_limit = max( 1, (int) ( $record_columns['desktop_max'] ?? $columns ) );
	$tablet_column_default = max( 1, (int) ( $record_columns['tablet'] ?? min( 2, $columns ) ) );
	$collection_columns = [
		'desktop' => min( max( 1, $item_count ), max( 1, (int) $pick( 'columns', min( $desktop_column_limit, max( 1, $item_count ) ) ) ) ),
		'tablet' => $is_list || ( $responsive['tablet'] ?? 'stack' ) === 'stack' ? 1 : min( max( 1, $item_count ), $tablet_column_default ),
		'mobile' => 1,
	];
	$collection_width = [
		// Collection width describes the available native track. Keep text measure
		// in list_row/entity_layout instead of turning a semantic list alias into
		// a max-width on the entire collection (Team/Testimonials also use the
		// historical `editorial_list` composition label).
		'desktop' => $pick( 'collection_width_desktop', '100%' ),
		'tablet' => $pick( 'collection_width_tablet', '100%' ),
		'mobile' => $pick( 'collection_width_mobile', '100%' ),
	];
	$collection_gap = [
		'desktop' => $pick( 'collection_gap_desktop', $is_list ? '1.25rem' : $values['space.component'] ),
		'tablet' => $pick( 'collection_gap_tablet', $is_list ? '1.125rem' : ( $values['space.component_tablet'] ?? $values['space.component'] ) ),
		'mobile' => $pick( 'collection_gap_mobile', $is_list ? '1rem' : ( $values['space.component_mobile'] ?? '1rem' ) ),
	];
	$has_authored_actions = false;
	foreach ( (array) ( $brief['groups'] ?? [] ) as $group ) {
		if ( is_array( $group ) && ( ! empty( $group['action_ref'] ) || ! empty( $group['cta_ref'] ) ) ) { $has_authored_actions = true; break; }
	}
	if ( ! $has_authored_actions ) {
		foreach ( (array) ( $brief['content'] ?? [] ) as $content_slot ) {
			if ( is_array( $content_slot ) && preg_match( '/(?:action|cta)/i', (string) ( $content_slot['role'] ?? '' ) ) && trim( (string) ( $content_slot['id'] ?? $content_slot['exact_text'] ?? '' ) ) !== '' ) { $has_authored_actions = true; break; }
		}
	}
	if ( ! $has_authored_actions ) {
		foreach ( (array) ( $brief['recipe_items'] ?? [] ) as $recipe_item ) {
			if ( is_array( $recipe_item ) && ( ! empty( $recipe_item['action_ref'] ) || ! empty( $recipe_item['cta_ref'] ) ) ) { $has_authored_actions = true; break; }
		}
	}
	$item_height_mode = (string) $pick( 'collection_item_height', $has_authored_actions ? 'equal_row' : 'content' );
	$collection_alignment = (string) $pick( 'collection_alignment', 'start' );
	$entity_tracks = null;
	if ( $entity_variant === 'editorial_rows' ) {
		$identity_default = $family === 'team' ? 34 : 24;
		$entity_tracks = [
			'identity_percent' => (int) $pick( 'entity_identity_percent', $identity_default ),
			'copy_percent' => (int) $pick( 'entity_copy_percent', 100 - $identity_default ),
			'copy_measure' => $pick( 'entity_copy_measure', $family === 'team' ? '48rem' : '54rem' ),
			'gap' => [
				'desktop' => $pick( 'entity_gap_desktop', '2rem' ),
				'tablet' => $pick( 'entity_gap_tablet', '1.5rem' ),
				'mobile' => $pick( 'entity_gap_mobile', '1rem' ),
			],
			'direction' => [ 'desktop' => 'row', 'tablet' => 'column', 'mobile' => 'column' ],
			'mobile_order' => [ 'identity', 'copy' ],
		];
	}
	$surface_override = strtolower( trim( (string) wpae_design_plan_constraint_value( $brief, 'surface_color', '' ) ) );
	if ( ! preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface_override ) ) { $surface_override = ''; }
	$explicit_surface_token = '';
	foreach ( (array) ( $brief['layout_constraints'] ?? [] ) as $constraint ) {
		if ( is_array( $constraint ) && ( $constraint['kind'] ?? '' ) === 'visual_token' && in_array( (string) ( $constraint['token'] ?? '' ), [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) ) {
			$explicit_surface_token = (string) $constraint['token'];
		}
	}
	$record_surface_token = (string) ( $record_policy['section_surface_token'] ?? '' );
	$profile_surface_token = (string) ( $values['layout.section_surface_token'] ?? '' );
	$legacy_surface_token = $family === 'pricing' ? 'color.surface' : 'color.page_bg';
	if ( $surface_override !== '' ) {
		$section_surface = [ 'token' => 'surface_override', 'source' => 'explicit_brief', 'value' => $surface_override ];
	} elseif ( $explicit_surface_token !== '' ) {
		$section_surface = [ 'token' => $explicit_surface_token, 'source' => 'explicit_brief' ];
	} elseif ( in_array( $record_surface_token, [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) ) {
		$section_surface = [ 'token' => $record_surface_token, 'source' => 'composition_record' ];
	} elseif ( in_array( $profile_surface_token, [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) ) {
		$section_surface = [ 'token' => $profile_surface_token, 'source' => 'visual_profile' ];
	} elseif ( isset( $visual['values']['color.section_bg'] ) ) {
		$section_surface = [ 'token' => 'color.section_bg', 'source' => 'documented_default', 'palette_role' => 'accent', 'opacity' => 0.2, 'transparency' => 0.8, 'accent_source' => (string) ( $visual['palette']['accent_reference']['source'] ?? '' ), 'accent_confirmed' => (bool) ( $visual['palette']['accent_reference']['confirmed'] ?? false ) ];
	} else {
		$section_surface = [ 'token' => $legacy_surface_token, 'source' => 'documented_default_no_palette_accent' ];
	}
	$field_sources['section_surface_token'] = $section_surface['source'];
	// New accepted repeat groups compile to native Flex containers. The historical
	// native_grid compiler path remains available only to frozen older Plans.
	$collection_implementation_default = 'native_flex_equal';
	$collection_implementation = (string) $pick( 'collection_implementation', $collection_implementation_default );
	if ( $collection_implementation !== 'native_flex_equal' ) {
		$collection_implementation = $collection_implementation_default;
	}
	$item_surface_contract = wpae_design_plan_item_surface_contract( $record, $family, $values );
	$policy = [
		'version' => 1,
		'precedence' => [ 'explicit_brief', 'composition_record', 'visual_profile', 'documented_default' ],
		'intro' => [ 'placement' => $pick( 'intro_placement', $split ? 'split_copy' : 'above_collection' ), 'text_align' => $pick( 'intro_text_align', 'left' ), 'container_align' => $pick( 'intro_container_align', 'start' ), 'reading_measure' => $pick( 'reading_measure', $values['layout.copy_width'] ?? '38rem' ), 'heading_level' => $family === 'hero' ? 'h1' : 'h2', 'title_token' => $intro_title_token, 'eyebrow_presentation' => $pick( 'eyebrow_presentation', wpae_elementor_recipe_eyebrow_presentation() ) ],
		'typography' => [ 'intro_title' => $values[$intro_title_token], 'item_title' => $item, 'body' => $body, 'eyebrow' => $eyebrow, 'price' => array_replace( $body, [ 'desktop' => '2rem', 'tablet' => '1.8rem', 'mobile' => '1.6rem', 'weight' => '800', 'line_height' => '1.2' ] ) ],
		'spacing' => [ 'eyebrow_title' => $pick( 'eyebrow_title_gap', '0.75rem' ), 'title_description' => $pick( 'title_description_gap', '1rem' ), 'description_cta' => $pick( 'description_cta_gap', '1.5rem' ), 'intro_collection' => $pick( 'intro_collection_gap', '2rem' ), 'item_copy' => $pick( 'item_copy_gap', '0.75rem' ), 'item_cta' => $pick( 'item_cta_gap', '1.25rem' ), 'section' => [ 'desktop' => $values['space.section'], 'tablet' => $values['space.section_tablet'] ?? $values['space.section'], 'mobile' => $values['space.section_mobile'] ?? '2rem' ] ],
		'eyebrow_colors' => [ 'plain' => $values['color.primary'], 'pill' => $family === 'services' ? $values['color.text'] : $values['color.surface'], 'pill_background' => $family === 'services' ? $values['color.surface'] : $values['color.primary'], 'pill_border' => $family === 'services' ? $values['color.muted'] : $values['color.primary'] ],
		'item_surface' => $item_surface_contract ?? [ 'padding' => $values['space.card'] ?? '1.5rem', 'radius' => $values['radius.card'], 'background' => $values['color.surface'] ],
		'section_surface' => $section_surface,
		// Inline values (price + period) keep their shared start axis when space narrows.
		'entity_layout' => array_filter( [ 'variant' => $record_policy['entity_layout'] ?? 'grid', 'columns' => [ 'desktop' => $entity_variant === 'editorial_rows' ? 2 : 1, 'tablet' => 1, 'mobile' => 1 ], 'gap' => $pick( 'item_copy_gap', '0.75rem' ), 'tracks' => $entity_tracks ], static fn( $value ): bool => $value !== null ),
		'inline_value' => [ 'direction' => [ 'desktop' => 'row', 'tablet' => 'row', 'mobile' => 'row' ], 'wrap' => 'wrap', 'main_align' => 'flex-start', 'cross_align' => 'center', 'gap' => '0.25rem' ],
		'collection' => [ 'implementation' => $collection_implementation, 'axis' => $is_list ? 'list' : 'grid', 'alignment' => $collection_alignment, 'width' => $collection_width, 'item_height' => $item_height_mode, 'item_count' => $item_count, 'columns' => $is_list ? [ 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ] : $collection_columns, 'gap' => $collection_gap ],
		'list_row' => $is_list && ( $composition === 'editorial_list' || $services_recipe_id === 'services.text_icon_list' ) ? [ 'icon_width' => $pick( 'list_icon_width', '44px' ), 'gap' => [ 'desktop' => $pick( 'list_item_gap_desktop', '1rem' ), 'tablet' => $pick( 'list_item_gap_tablet', '1rem' ), 'mobile' => $pick( 'list_item_gap_mobile', '0.75rem' ) ], 'copy_measure' => $pick( 'list_copy_measure', '48rem' ), 'direction' => [ 'desktop' => 'row', 'tablet' => 'row', 'mobile' => 'row' ] ] : null,
		'cards' => [ 'direction' => 'column', 'row_alignment' => 'stretch', 'body_actions_distribution' => 'space_between', 'body_copy_gap' => $pick( 'item_copy_gap', '0.75rem' ), 'media_copy_gap' => $pick( 'item_media_gap', $values['space.component'] ), 'body_actions_gap' => $pick( 'item_cta_gap', '1.25rem' ), 'mobile_height' => 'content', 'footer' => 'when_actions_exist' ],
		'split' => [ 'gap' => [ 'desktop' => $values['space.component'], 'tablet' => $values['space.component_tablet'] ?? $values['space.component'], 'mobile' => $values['space.component_mobile'] ?? '1rem' ], 'ratio' => [ 'split_60_40' => [ 60, 40 ], 'split_50_50' => [ 50, 50 ], 'split_40_60' => [ 40, 60 ] ][ $composition ] ?? [], 'media_side' => $record_policy['media_side'] ?? wpae_design_plan_constraint_value( $brief, 'media_side', 'right' ), 'mobile_direction' => $split && ( $record_policy['media_side'] ?? wpae_design_plan_constraint_value( $brief, 'media_side', 'right' ) ) === 'left' ? 'column-reverse' : 'column', 'responsive' => $responsive ],
	];
	$field_sources['inline_value'] = 'documented_default';
	$policy = array_filter( $policy, static fn( $value ): bool => $value !== null );
	$policy['provenance'] = [ 'record_id' => $record['id'] ?? '', 'profile' => $visual['profile'] ?? '', 'field_sources' => $field_sources, 'token_sources' => $visual['sources'] ?? [] ];
	return $policy;
}

/** Apply only the accepted section-surface token; the compiler never chooses it. */
function wpae_design_plan_apply_section_surface_policy( array &$plan ): void {
	$surface = (array) ( $plan['visual_policy']['section_surface'] ?? [] );
	$token = (string) ( $surface['token'] ?? '' );
	if ( ! in_array( $token, [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) || empty( $plan['sections'][0] ) || ! is_array( $plan['sections'][0] ) ) {
		return;
	}
	$plan['sections'][0]['surface_token'] = $token;
	$plan['sections'][0]['provenance']['surface_token'] = [ 'source' => 'accepted_visual_policy', 'decision_source' => (string) ( $surface['source'] ?? 'unknown' ), 'token' => $token ];
	if ( $token === 'color.section_bg' ) {
		$plan['tokens']['color.section_bg'] = 'color.section_bg';
	}
}

function wpae_design_plan_from_brief( array $brief, array $context = [] ): array {
	$archetype = sanitize_key( (string) ( $brief['intent']['archetype'] ?? 'unknown' ) );
	if ( array_key_exists( 'services_recipe_id', $context ) ) {
		$raw_recipe_id = is_scalar( $context['services_recipe_id'] ) ? trim( (string) $context['services_recipe_id'] ) : '';
		$recipe_plan = wpae_design_plan_services_recipe_plan( $brief, $context, $raw_recipe_id );
		if ( $archetype !== 'services' ) {
			$recipe_plan['archetype'] = $archetype;
		}
		if ( ! empty( $context['canonical_create'] ) ) {
			$recipe_plan['resolved_visual'] = wpae_design_plan_resolve_visual( $brief, $context );
			$recipe_plan['visual_policy'] = wpae_design_plan_visual_policy( $brief, (array) ( $recipe_plan['composition_decision']['record_id'] ? ( wpae_composition_records()[ $recipe_plan['composition_decision']['record_id'] ] ?? [] ) : [] ), $recipe_plan['resolved_visual'], (string) ( $recipe_plan['sections'][0]['composition'] ?? 'linear' ), [ 'tablet' => $recipe_plan['responsive']['tablet'] ?? 'stack', 'mobile' => $recipe_plan['responsive']['mobile'] ?? 'stack' ], count( (array) ( $recipe_plan['slot_bindings']['services'] ?? [] ) ), (string) ( $recipe_plan['recipe_id'] ?? '' ) );
			wpae_design_plan_apply_section_surface_policy( $recipe_plan );
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
	$composition = (string) ( $explicit_composition ?? ( in_array( $archetype, [ 'hero', 'about' ], true ) ? 'split_60_40' : ( $archetype === 'process' && ! empty( $context['canonical_create'] ) ? 'ordered_timeline' : ( $archetype === 'pricing' || ( in_array( $archetype, [ 'benefits', 'team', 'testimonials' ], true ) && ! empty( $context['canonical_create'] ) ) ? 'three_cards' : 'linear' ) ) ) );
	$automatic_composition = [];
	$media_references = array_values( array_filter( (array) ( $brief['media_references'] ?? [] ), static fn( $media ): bool => is_array( $media ) && wpae_design_plan_media_reference_valid( $media ) && ( ! in_array( $archetype, [ 'hero', 'about' ], true ) || ( $media['role'] ?? '' ) === $archetype ) ) );
	if ( empty( $context['canonical_create'] ) && in_array( $archetype, [ 'hero', 'about' ], true ) && empty( $media_references ) ) {
		$default_hero_media = wpae_design_plan_default_hero_media( $brief );
		if ( ! empty( $default_hero_media ) ) {
			$media_references[] = $default_hero_media;
		}
	}
	$hero_has_media = in_array( $archetype, [ 'hero', 'about' ], true ) && ! empty( $media_references ) && $media_intent !== 'forbidden' && $media_intent !== 'conflict';
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
	$eyebrow_presentation = (string) wpae_design_plan_constraint_value( $brief, 'eyebrow_presentation', wpae_elementor_recipe_eyebrow_presentation() );
	$media_side = (string) wpae_design_plan_constraint_value( $brief, 'media_side', 'right' );
	if ( ! in_array( $media_side, [ 'left', 'right' ], true ) ) {
		$media_side = 'right';
	}
	$record_selection = [];
	if ( ! empty( $context['canonical_create'] ) && in_array( $archetype, [ 'hero', 'about', 'benefits', 'pricing', 'faq', 'team', 'testimonials', 'process' ], true ) ) {
		$accepted_decision = (array) ( $context['composition_decision'] ?? [] );
		$record_selection = ! empty( $accepted_decision )
			? wpae_composition_decision_for_record( $brief, $accepted_decision, (string) ( $accepted_decision['record']['id'] ?? $accepted_decision['record_id'] ?? '' ) )
			: wpae_composition_decide( $brief, $context );
		if ( empty( $record_selection['errors'] ) ) {
			$composition = $record_selection['record']['composition'];
			$media_side = $record_selection['record']['policy']['media_side'] ?? $media_side;
			$context['visual_profile'] = (string) ( $record_selection['visual_profile'] ?? '' );
		}
	}
	$cta_refs = wpae_design_plan_content_refs( $brief, [ 'cta', 'cta_2', 'cta_3' ] );
	$tokens = [
		'color.page_bg' => 'color.page_bg',
		'color.surface' => 'color.surface',
		'color.text' => 'color.text',
		'color.muted' => 'color.muted',
		'color.primary' => 'color.primary',
		'color.border' => 'color.border',
		'color.focus' => 'color.focus',
		'color.hover' => 'color.hover',
		'type.display' => 'type.display',
		'type.section_title' => 'type.section_title',
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
	if ( in_array( $archetype, [ 'hero', 'about' ], true ) && $eyebrow_presentation === 'pill' ) {
		$eyebrow_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow' ] );
		$section['badge_content_ref'] = $eyebrow_refs[0] ?? '';
	}
	if ( in_array( $archetype, [ 'hero', 'about' ], true ) ) {
		$section['media_intent'] = $media_intent;
		$section['media_side'] = $media_side;
		$copy_group = [
			[
				'role' => 'copy_group',
				'allowed_widgets' => [ 'heading', 'text-editor', 'button' ],
				'content_refs' => array_values( array_unique( array_merge( wpae_design_plan_content_refs( $brief, [ 'brand', 'eyebrow', 'title', 'body' ] ), $cta_refs ) ) ),
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.section_title', 'type.body', 'space.component' ],
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
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'body' ] );
		if ( $intro_refs ) {
			$intro_constraints = [ 'min_width' => 0, 'max_width' => 100 ];
			if ( (string) $process_content['badge_content_ref'] !== '' ) {
				$intro_constraints['eyebrow_presentation'] = (string) wpae_design_plan_constraint_value( $brief, 'eyebrow_presentation', 'pill' );
			}
			$section['children'][] = [
				'role' => 'copy_group',
				'allowed_widgets' => [ 'container', 'heading', 'text-editor' ],
				'content_refs' => $intro_refs,
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'color.surface', 'type.section_title', 'type.body', 'space.component' ],
				'layout_constraints' => $intro_constraints,
				'responsive_policy' => 'stack',
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'eyebrow', 'title', 'body' ] ],
			];
		}
		$section['children'][] = [
				'role' => 'process_steps',
				'allowed_widgets' => [ 'heading', 'text-editor', 'divider' ],
				'content_refs' => array_values( array_unique( $process_refs ) ),
				'steps' => $process_content['steps'],
				'token_refs' => [ 'color.text', 'color.muted', 'color.border', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'connector' => true ],
				'responsive_policy' => 'two_columns',
				'media_refs' => [],
				'editable_fields' => [ 'text' ],
				'provenance' => [ 'source' => 'brief', 'roles' => [ 'label', 'text' ] ],
		];
	} elseif ( $archetype === 'pricing' ) {
		$pricing_refs = [];
		foreach ( (array) ( $brief['pricing_items'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$pricing_refs[] = array_intersect_key( $item, array_flip( [ 'group_id', 'feature_refs', 'label_ref', 'price_ref', 'period_ref', 'description_ref', 'cta_ref', 'price_text', 'provenance' ] ) );
		}
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'body' ] );
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
				'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.section_title', 'type.body' ],
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
			'layout_constraints' => array_merge( [ 'min_width' => 0, 'max_width' => 100, 'border_radius' => $radius ], $surface_override !== '' ? [ 'surface_override' => $surface_override ] : [] ),
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
			$section['children'][] = [ 'role' => 'copy_group', 'allowed_widgets' => [ 'heading' ], 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.section_title', 'type.body' ], 'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ], 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ] ];
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
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'body', 'cta', 'cta_2', 'cta_3', 'cta_4' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [ 'role' => 'copy_group', 'allowed_widgets' => [ 'heading', 'text-editor', 'button' ], 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.section_title', 'type.body' ], 'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ], 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ] ];
		}
		$section['children'][] = [
				'role' => 'team_cards',
				'allowed_widgets' => $team_widgets,
				'content_refs' => wpae_design_plan_group_refs( $grouped['items'], [ 'name_ref', 'position_ref', 'bio_ref', 'action_ref' ] ),
				'items' => $grouped['items'],
				'item_errors' => $grouped['errors'],
				'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.border', 'radius.card', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'entity_layout' => $record_selection['record']['policy']['entity_layout'] ?? 'grid' ],
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
		$intro_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow', 'title', 'body', 'cta', 'cta_2', 'cta_3', 'cta_4' ] );
		$section['children'] = [];
		if ( ! empty( $intro_refs ) ) {
			$section['children'][] = [ 'role' => 'copy_group', 'allowed_widgets' => [ 'heading', 'text-editor', 'button' ], 'content_refs' => $intro_refs, 'token_refs' => [ 'color.text', 'color.primary', 'type.display', 'type.section_title', 'type.body' ], 'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100 ], 'responsive_policy' => 'stack', 'editable_fields' => [ 'text' ] ];
		}
		$section['children'][] = [
				'role' => 'testimonial_cards',
				'allowed_widgets' => $testimonial_widgets,
				'content_refs' => wpae_design_plan_group_refs( $grouped['items'], [ 'quote_ref', 'author_ref', 'meta_ref', 'rating_ref', 'action_ref' ] ),
				'items' => $grouped['items'],
				'item_errors' => $grouped['errors'],
				'token_refs' => [ 'color.surface', 'color.text', 'color.muted', 'color.border', 'radius.card', 'space.component' ],
				'layout_constraints' => [ 'min_width' => 0, 'max_width' => 100, 'entity_layout' => $record_selection['record']['policy']['entity_layout'] ?? 'grid' ],
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
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'color.surface', 'type.display', 'type.section_title', 'type.body', 'space.component' ],
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
				'token_refs' => [ 'color.text', 'color.muted', 'color.primary', 'type.display', 'type.section_title', 'type.body', 'space.component' ],
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
			'role' => $composition === 'editorial_list' ? 'feature_list' : 'feature_cards',
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
	// Freeze the recipe presentation in the Plan; repair continues to use its accepted Plan.
	$eyebrow_refs = wpae_design_plan_content_refs( $brief, [ 'eyebrow' ] );
	foreach ( $section['children'] as &$intro_child ) {
		if ( in_array( $intro_child['role'] ?? '', [ 'copy_group', 'cta_copy_group' ], true ) && $eyebrow_presentation === 'pill' && array_intersect( $eyebrow_refs, (array) ( $intro_child['content_refs'] ?? [] ) ) ) {
			$intro_child['layout_constraints']['eyebrow_presentation'] = 'pill';
			$intro_child['allowed_widgets'] = array_values( array_unique( array_merge( $intro_child['allowed_widgets'], [ 'container' ] ) ) );
		}
	}
	unset( $intro_child );
	$resolved_visual = ! empty( $context['canonical_create'] ) ? wpae_design_plan_resolve_visual( $brief, $context ) : [];
	$visual_policy = ! empty( $context['canonical_create'] ) ? wpae_design_plan_visual_policy( $brief, (array) ( $record_selection['record'] ?? [] ), $resolved_visual, $composition, [ 'tablet' => $record_selection['record']['policy']['tablet'] ?? ( $hero_has_media ? 'split_50_50' : 'stack' ), 'mobile' => $record_selection['record']['policy']['mobile'] ?? 'stack' ], max( array_merge( [ 0 ], array_map( static fn( array $child ): int => count( (array) ( $child['items'] ?? [] ) ), $section['children'] ) ) ) ) : null;
	$plan = [
		'composition_decision' => array_merge( [ 'identity' => $archetype . '.' . $composition, 'source' => $explicit_composition !== null ? 'explicit_brief' : 'documented_default', 'slot_bindings' => $section['children'] ], empty( $record_selection ) ? [] : [
		'record_id' => $record_selection['record']['id'] ?? '', 'record_version' => $record_selection['record']['version'] ?? 0, 'record_hash' => $record_selection['record']['hash'] ?? '', 'brief_hash' => (string) ( $record_selection['brief_hash'] ?? '' ),
			'request_selection' => (array) ( $record_selection['request_selection'] ?? [] ), 'variant_kind' => $record_selection['record']['variant_kind'] ?? '', 'distinct' => $record_selection['record']['distinct'] ?? false, 'source' => $record_selection['source'] ?? 'unresolved', 'policy' => $record_selection['record']['policy'] ?? [], 'visual_profile' => $record_selection['visual_profile'] ?? '', 'errors' => $record_selection['errors'],
			'selection_policy' => (string) ( $record_selection['policy_version'] ?? '' ), 'catalog_id' => (string) ( $record_selection['catalog_id'] ?? 'wpae-compositions-v1' ), 'catalog_identity' => (string) ( $record_selection['catalog_identity'] ?? '' ), 'selection_metrics' => (array) ( $record_selection['metrics'] ?? [] ), 'selection_reasons' => (array) ( $record_selection['selection_reasons'] ?? [] ), 'rejected_candidates' => (array) ( $record_selection['rejected'] ?? [] ), 'alternatives' => (array) ( $record_selection['alternatives'] ?? [] ),
		] ),
		'resolved_visual' => $resolved_visual,
		'visual_policy' => $visual_policy,
		'schema' => WPAE_DESIGN_PLAN_SCHEMA,
		'archetype' => $archetype,
		'sections' => [ $section ],
		'responsive' => ! empty( $record_selection['record']['policy'] ) ? array_intersect_key( (array) $record_selection['record']['policy'], array_flip( [ 'desktop', 'tablet', 'mobile' ] ) ) : [
			'desktop' => $composition,
			'tablet' => in_array( $archetype, [ 'hero', 'about' ], true ) && $hero_has_media ? 'split_50_50' : 'stack',
			'mobile' => ( in_array( $archetype, [ 'hero', 'about' ], true ) && $hero_has_media ) || ( $archetype === 'cta' && $cta_has_media ) ? 'copy_first_stack' : 'stack',
		],
		'tokens' => $tokens,
		'quality_gates' => [ 'brief_fidelity', 'capabilities', 'layout_report', 'readback', 'render_review' ],
		'media_references' => array_values( $media_references ),
		'provenance' => [
			'brief_hash' => function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '',
			'planner' => 'wpae-design-plan-v1',
			'source' => 'brief-ir',
		],
		'warnings' => array_values( array_unique( array_merge( empty( $brief['ambiguities'] ) ? [] : [ 'brief_has_ambiguities' ], in_array( $archetype, [ 'hero', 'about' ], true ) && $media_intent === 'unspecified' && ! $hero_has_media ? [ 'media_unspecified_no_asset_text_only' ] : [] ) ) ),
		'explicit_badge' => in_array( $archetype, [ 'hero', 'about' ], true ) && $eyebrow_presentation === 'pill' && ( ! empty( $eyebrow_refs ) || wpae_design_plan_constraint_value( $brief, 'eyebrow_presentation', '' ) === 'pill' ),
		'media_intent' => $media_intent,
		'media_asset_count' => count( $media_references ),
	];
	wpae_design_plan_apply_section_surface_policy( $plan );
	return $plan;
}

function wpae_design_plan_services_recipe_validate( array $plan, array $brief ): array {
	$errors = [];
	$recipe_id = (string) ( $plan['recipe_id'] ?? '' );
	$recipe_roles = [ 'services.photo_cards' => 'services_photo_grid', 'services.split_editorial' => 'services_split_editorial', 'services.text_icon_list' => 'services_text_icon_list', 'services.icon_cards' => 'services_icon_cards' ];
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
	if ( in_array( $recipe_id, [ 'services.text_icon_list', 'services.icon_cards' ], true ) && ( $media_intent === 'required' || ! empty( $consumed ) ) ) {
		$errors[] = 'text_icon_list_media_incompatible';
	}
	if ( in_array( $recipe_id, [ 'services.text_icon_list', 'services.icon_cards' ], true ) && $media_intent === 'required' ) {
		$errors[] = 'text_icon_list_incompatible_with_required_media';
	}
	if ( $media_intent === 'required' && ! empty( $unconsumed ) ) {
		$errors[] = 'required_media_assets_unconsumed';
	}
	return array_values( array_unique( $errors ) );
}

/**
 * Freeze which repeated-item container owns its native surface. Existing
 * composition records remain byte-for-byte stable; this companion mapping is
 * versioned independently and is copied into each newly accepted Plan.
 */
function wpae_design_plan_item_surface_contract( array $record, string $family, array $values ): ?array {
	// Composition IDs deliberately contain a dot; sanitize_key would erase it.
	$record_id = trim( (string) ( $record['id'] ?? '' ) );
	$declared = (array) ( $record['policy']['item_surface'] ?? [] );
	$owners = [
		'benefits.grid' => [ 'card', 'feature_card' ],
		'benefits.linear' => [ 'card', 'feature_card' ],
		'benefits.editorial_list' => [ 'transparent_divider', 'feature_row' ],
		'pricing.tiers' => [ 'card', 'pricing_card' ],
		'team.grid' => [ 'card', 'team_card' ],
		'team.editorial_rows' => [ 'card', 'team_card' ],
		'testimonials.grid' => [ 'card', 'testimonial_card' ],
		'testimonials.editorial_rows' => [ 'card', 'testimonial_card' ],
		'services.photo_cards' => [ 'card', 'services_photo_card' ],
		'services.split_editorial' => [ 'card', 'services_split_lead' ],
		'services.text_icon_list' => [ 'transparent_divider', 'services_text_icon_row' ],
		'services.icon_cards' => [ 'card', 'services_icon_card' ],
		'process.ordered_steps' => [ 'card', 'process_card' ],
	];
	$mapped = $owners[ $record_id ] ?? null;
	if ( ! $mapped && (int) ( $declared['contract_version'] ?? 0 ) < 2 ) {
		return null;
	}
	$mode = sanitize_key( (string) ( $declared['mode'] ?? $mapped[0] ?? '' ) );
	$owner_role = sanitize_key( (string) ( $declared['owner_role'] ?? $mapped[1] ?? '' ) );
	if ( ! in_array( $mode, [ 'card', 'transparent_divider' ], true ) || $owner_role === '' ) {
		return null;
	}
	$card_surface = $mode === 'card';
	return [
		'contract_version' => 2,
		'mode' => $mode,
		'owner_role' => $owner_role,
		'background' => $card_surface ? (string) ( $values['color.surface'] ?? '#ffffff' ) : 'transparent',
		'border_style' => $card_surface ? 'solid' : 'none',
		'border_color' => $card_surface ? (string) ( $values['color.border'] ?? '#d1d5db' ) : 'transparent',
		'border_width' => $card_surface ? '1px' : '0px',
		'radius' => $card_surface ? (string) ( $values['radius.card'] ?? '0.75rem' ) : '0px',
		'padding' => $card_surface ? (string) ( $values['space.card'] ?? '1.5rem' ) : '0rem',
		'padding_tablet' => $card_surface ? (string) ( $values['space.component_tablet'] ?? $values['space.card'] ?? '1.25rem' ) : '0rem',
		'padding_mobile' => $card_surface ? (string) ( $values['space.component_mobile'] ?? '1rem' ) : '0rem',
		'interior' => 'transparent_by_default',
		'provenance' => [ 'source' => 'composition_record', 'record_id' => $record_id, 'record_hash' => (string) ( $record['hash'] ?? '' ), 'family' => $family ],
	];
}

function wpae_design_plan_validate( array $plan, array $brief = [] ): array {
	$errors = array_merge( (array) ( $plan['composition_decision']['errors'] ?? [] ), (array) ( $plan['resolved_visual']['errors'] ?? [] ) );
	$decision = $plan['composition_decision'] ?? [];
	if ( ! empty( $decision['record_id'] ) ) {
		$record = wpae_composition_records()[ $decision['record_id'] ] ?? [];
		if ( ! $record || ( $decision['record_version'] ?? null ) !== $record['version'] || ( $decision['record_hash'] ?? '' ) !== $record['hash'] ) { $errors[] = 'composition_record_integrity'; }
		if ( ! empty( $decision['selection_policy'] ) ) {
			$catalog_records = wpae_composition_records(); $catalog_ids = array_keys( $catalog_records ); sort( $catalog_ids );
			$catalog_identity = hash( 'sha256', wp_json_encode( array_map( static fn( $id ): array => [ $id, $catalog_records[$id]['version'], $catalog_records[$id]['hash'] ], $catalog_ids ) ) );
			if ( ( $decision['selection_policy'] ?? '' ) !== 'wpae-composition-selection-v2' || ( $decision['catalog_id'] ?? '' ) !== 'wpae-compositions-v1' || ( $decision['catalog_identity'] ?? '' ) !== $catalog_identity ) { $errors[] = 'composition_selection_provenance_invalid'; }
			if ( $brief && ( $decision['brief_hash'] ?? '' ) !== ( function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '' ) ) { $errors[] = 'composition_brief_hash_mismatch'; }
			if ( $record && ! in_array( (string) ( $decision['visual_profile'] ?? '' ), (array) ( $record['visual_profiles'] ?? [] ), true ) && ! empty( $record['visual_profiles'] ) ) { $errors[] = 'composition_visual_profile_integrity'; }
		}
		if ( $record && ( ( $decision['policy'] ?? [] ) !== $record['policy'] || ( $plan['sections'][0]['composition'] ?? '' ) !== $record['composition'] || ( isset( $record['policy']['media_side'] ) && $record['policy']['media_side'] !== ( $plan['sections'][0]['media_side'] ?? null ) ) || ( $plan['responsive'] ?? [] ) !== array_intersect_key( $record['policy'], array_flip( [ 'desktop', 'tablet', 'mobile' ] ) ) ) ) { $errors[] = 'composition_policy_mutated'; }
	}
	$schema = wpae_design_plan_schema();
	$content_by_id = array_column( (array) ( $brief['content'] ?? [] ), null, 'id' );
	if ( ! empty( $brief['canonical_create'] ) ) {
		$allowed_compositions = $schema['family_compositions'][ $plan['archetype'] ?? '' ] ?? [];
		if ( ! in_array( $plan['sections'][0]['composition'] ?? '', $allowed_compositions, true ) ) { $errors[] = 'family_composition_unsupported'; }
		$explicit_copy_refs = wpae_design_plan_content_refs( $brief, [ 'title', 'pricing_label', 'feature_title', 'faq_question', 'team_name', 'testimonial_quote' ] );
		$process_has_explicit_labels = ( $plan['archetype'] ?? '' ) === 'process' && ! empty( wpae_design_plan_process_content( $brief )['steps'] );
		if ( empty( $explicit_copy_refs ) && ! $process_has_explicit_labels ) { $errors[] = 'explicit_copy_required'; }
		$media_intent = wpae_design_plan_constraint_value( $brief, 'media_intent', 'unspecified' );
		if ( $media_intent === 'conflict' ) { $errors[] = 'media_policy_conflict'; }
		if ( ! in_array( $plan['archetype'] ?? '', [ 'hero', 'about', 'team', 'testimonials' ], true ) && ( $media_intent === 'required' || ! empty( $brief['media_references'] ) ) ) { $errors[] = 'unsupported_media_slots'; }
		$reference_validation = wpae_reference_set_validate( (array) ( $brief['media_references'] ?? [] ) );
		$errors = array_merge( $errors, $reference_validation['errors'] );
		foreach ( (array) ( $brief['media_references'] ?? [] ) as $media ) {
			if ( ! is_array( $media ) || ! wpae_design_plan_media_reference_valid( $media ) || empty( $media['allowed_reuse'] ) || trim( (string) ( $media['alt'] ?? '' ) ) === '' ) { $errors[] = 'media_asset_unresolved'; }
			if ( in_array( $plan['archetype'] ?? '', [ 'hero', 'about' ], true ) && ! empty( $media['group_id'] ) && $media['group_id'] !== $plan['archetype'] ) { $errors[] = 'cross_group_media_binding'; }
		}
		if ( in_array( $media_intent, [ 'forbidden', 'conflict' ], true ) && ! empty( $brief['media_references'] ) ) { $errors[] = 'media_policy_conflict'; }
		if ( count( (array) ( $brief['media_references'] ?? [] ) ) !== count( (array) ( $plan['media_references'] ?? [] ) ) ) { $errors[] = 'unconsumed_media_reference'; }
		if ( in_array( $plan['archetype'] ?? '', [ 'hero', 'about' ], true ) && count( (array) ( $plan['media_references'] ?? [] ) ) > 1 ) { $errors[] = 'split_requires_one_media_asset'; }
		if ( in_array( $plan['archetype'] ?? '', [ 'team', 'testimonials' ], true ) ) {
			$family = $plan['archetype'];
			$fields = wpae_brief_ir_entity_fields( $family );
			$groups = (array) ( $brief['groups'] ?? [] );
			$expected_role = array_flip( $fields );
			$media_by_id = array_column( (array) ( $brief['media_references'] ?? [] ), null, 'asset_id' );
			$entity_policy = $plan['visual_policy']['entity_layout'] ?? [];
			$variant = $plan['composition_decision']['policy']['entity_layout'] ?? '';
			if ( ( $entity_policy['variant'] ?? '' ) !== $variant || ( $entity_policy['columns'] ?? [] ) !== [ 'desktop' => $variant === 'editorial_rows' ? 2 : 1, 'tablet' => 1, 'mobile' => 1 ] || ! preg_match( '/^(?:0|[1-9]\d*(?:\.\d+)?|0\.\d+)(?:px|rem|em)$/', (string) ( $entity_policy['gap'] ?? '' ) ) ) { $errors[] = 'entity_layout_policy_invalid'; }
			$consumed = []; $expected_items = [];
			foreach ( $groups as $group ) {
				$expected_items[] = $group;
				foreach ( $expected_role as $field => $role ) {
					if ( empty( $group[$field] ) ) { continue; }
					$slot = $content_by_id[$group[$field]] ?? [];
					if ( ( $slot['role'] ?? '' ) !== $role || ( $slot['group_id'] ?? '' ) !== ( $group['group_id'] ?? '' ) ) { $errors[] = 'entity_slot_owner_or_role:' . $field; }
					if ( $field === 'action_ref' && ( empty( $slot['url_requested'] ) || empty( $slot['url'] ) ) ) { $errors[] = 'entity_action_url_required'; }
				}
				if ( ! empty( $group['media_ref'] ) ) {
					$asset = $media_by_id[$group['media_ref']] ?? [];
					if ( ( $asset['group_id'] ?? '' ) !== $group['group_id'] || ( $asset['role'] ?? '' ) !== 'portrait' ) { $errors[] = 'entity_media_owner_or_role'; }
					$consumed[] = $group['media_ref'];
				} elseif ( $media_intent === 'required' ) { $errors[] = 'entity_required_portrait_missing:' . $group['group_id']; }
			}
			if ( count( $consumed ) !== count( $media_by_id ) || count( array_unique( $consumed ) ) !== count( $consumed ) ) { $errors[] = 'entity_media_unconsumed_or_reused'; }
			$collection = array_values( array_filter( (array) ( $plan['sections'][0]['children'] ?? [] ), static fn( array $child ): bool => in_array( $child['role'] ?? '', [ 'team_cards', 'testimonial_cards' ], true ) ) );
			if ( ( $collection[0]['items'] ?? [] ) !== $expected_items ) { $errors[] = 'entity_frozen_group_bindings_changed'; }
		}
		$bound_refs = [];
		foreach ( (array) ( $plan['sections'] ?? [] ) as $section ) {
			foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
				$bound_refs = array_merge( $bound_refs, (array) ( $child['content_refs'] ?? [] ) );
				foreach ( (array) ( $child['items'] ?? [] ) as $item ) {
					foreach ( $item as $key => $value ) { if ( $key === 'feature_refs' ) { $bound_refs = array_merge( $bound_refs, (array) $value ); } elseif ( substr( (string) $key, -4 ) === '_ref' ) { $bound_refs[] = $value; } }
				}
			}
		}
		foreach ( (array) ( $brief['content'] ?? [] ) as $item ) { if ( ! in_array( $item['id'], $bound_refs, true ) ) { $errors[] = 'unbound_explicit_content:' . $item['id']; } }
		foreach ( (array) ( $brief['ambiguities'] ?? [] ) as $ambiguity ) { if ( ( $ambiguity['kind'] ?? '' ) === 'conflicting_compositions' ) { $errors[] = 'conflicting_compositions'; } }
	}

	foreach ( (array) ( $plan['sections'] ?? [] ) as $section ) {
		foreach ( (array) ( $section['children'] ?? [] ) as $child ) {
			foreach ( (array) ( $child['content_refs'] ?? [] ) as $ref ) { if ( $brief && ! isset( $content_by_id[ $ref ] ) ) { $errors[] = 'unknown_content_ref:' . $ref; } }
			foreach ( (array) ( $child['items'] ?? [] ) as $item ) {
				$owners = [];
				foreach ( $item as $key => $ref ) {
					$refs = $key === 'feature_refs' ? (array) $ref : ( substr( (string) $key, -4 ) === '_ref' ? [ $ref ] : [] );
					foreach ( $refs as $id ) {
						if ( $id === '' ) { continue; }
						if ( $brief && ! isset( $content_by_id[ $id ] ) && $key !== 'media_ref' ) { $errors[] = 'unknown_content_ref:' . $id; }
						$expected_role = [ 'pricing' => [ 'label_ref' => 'pricing_label', 'price_ref' => 'pricing_price', 'period_ref' => 'pricing_period', 'description_ref' => 'pricing_description', 'feature_refs' => 'pricing_feature', 'cta_ref' => 'pricing_cta' ], 'faq' => [ 'question_ref' => 'faq_question', 'answer_ref' => 'faq_answer' ], 'benefits' => [ 'title_ref' => 'feature_title', 'body_ref' => 'feature_body' ] ][ $plan['archetype'] ?? '' ][ $key ] ?? '';
						if ( $brief && $expected_role !== '' && ( $content_by_id[ $id ]['role'] ?? '' ) !== $expected_role ) { $errors[] = 'content_role_binding:' . $key; }
						$owner = (string) ( $content_by_id[ $id ]['group_id'] ?? '' );
						if ( $owner !== '' ) { $owners[] = $owner; }
					}
				}
				if ( count( array_unique( $owners ) ) > 1 || ( ! empty( $item['group_id'] ) && $owners && array_diff( $owners, [ $item['group_id'] ] ) ) ) { $errors[] = 'cross_group_binding'; }
			}
		}
	}
	if ( ! empty( $plan['resolved_visual'] ) && empty( $plan['resolved_visual']['contrast']['ok'] ) ) { $errors[] = 'resolved_visual_contrast'; }
	if ( ( $plan['schema'] ?? '' ) !== WPAE_DESIGN_PLAN_SCHEMA ) {
		$errors[] = 'schema';
	}
	if ( ! in_array( $plan['archetype'] ?? '', $schema['archetypes'], true ) ) {
		$errors[] = 'archetype';
	}
	if ( empty( $plan['sections'] ) || ! is_array( $plan['sections'] ) ) {
		$errors[] = 'sections';
	}
	if ( in_array( $plan['archetype'] ?? '', [ 'hero', 'about' ], true ) ) {
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
		if ( $has_media_node && ! $split ) { $errors[] = 'media_requires_split_composition'; }
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
			if ( ( $plan['composition_decision']['record_id'] ?? '' ) === 'process.ordered_steps' && ( count( $steps ) < 3 || count( $steps ) > 6 ) ) { $errors[] = 'typed_process_step_count_out_of_range'; }
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
			if ( is_array( $child ) && in_array( $child['role'] ?? '', [ 'feature_cards', 'feature_list' ], true ) ) {
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
	if ( isset( $plan['visual_policy'] ) ) {
		$visual_policy = $plan['visual_policy'];
		if ( ( $visual_policy['version'] ?? null ) !== 1 || ! in_array( $visual_policy['intro']['text_align'] ?? '', [ 'left', 'center', 'right' ], true ) || ! in_array( $visual_policy['intro']['container_align'] ?? '', [ 'start', 'center', 'end' ], true ) || ! in_array( $visual_policy['intro']['eyebrow_presentation'] ?? '', [ 'plain', 'pill' ], true ) ) { $errors[] = 'visual_policy_invalid'; }
		if ( isset( $visual_policy['intro']['title_token'] ) && ! in_array( $visual_policy['intro']['title_token'], [ 'type.display', 'type.section_title' ], true ) ) { $errors[] = 'visual_policy_intro_title_token_invalid'; }
		if ( isset( $visual_policy['section_surface'] ) ) {
			$section_surface = (array) $visual_policy['section_surface'];
			$surface_token = (string) ( $section_surface['token'] ?? '' );
			if ( ! in_array( $surface_token, [ 'surface_override', 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) || ! in_array( $section_surface['source'] ?? '', [ 'explicit_brief', 'composition_record', 'visual_profile', 'documented_default', 'documented_default_no_palette_accent' ], true ) ) {
				$errors[] = 'visual_policy_section_surface_invalid';
			} elseif ( $surface_token === 'surface_override' ) {
				if ( strtolower( (string) ( $plan['sections'][0]['surface_override'] ?? '' ) ) !== strtolower( (string) ( $section_surface['value'] ?? '' ) ) || ( $section_surface['source'] ?? '' ) !== 'explicit_brief' ) { $errors[] = 'visual_policy_section_surface_override_mismatch'; }
			} else {
				if ( ( $plan['sections'][0]['surface_token'] ?? '' ) !== $surface_token ) { $errors[] = 'visual_policy_section_surface_token_mismatch'; }
				if ( $surface_token === 'color.section_bg' && ( ( $section_surface['palette_role'] ?? '' ) !== 'accent' || (float) ( $section_surface['opacity'] ?? -1 ) !== 0.2 || (float) ( $section_surface['transparency'] ?? -1 ) !== 0.8 || trim( (string) ( $section_surface['accent_source'] ?? '' ) ) === '' || empty( $plan['tokens']['color.section_bg'] ) ) ) { $errors[] = 'visual_policy_section_accent_tint_invalid'; }
			}
		}
		if ( ! preg_match( '/^(?:[1-9]\d*(?:\.\d+)?|0\.[1-9]\d*)(?:px|rem|em)$/', (string) ( $visual_policy['intro']['reading_measure'] ?? '' ) ) ) { $errors[] = 'visual_policy_reading_measure_invalid'; }
		$surface = (array) ( $visual_policy['item_surface'] ?? [] );
		if ( (int) ( $surface['contract_version'] ?? 0 ) === 2 ) {
			$record_id = (string) ( $plan['composition_decision']['record_id'] ?? '' );
			$record = wpae_composition_records()[ $record_id ] ?? [];
			$expected_surface = $record ? wpae_design_plan_item_surface_contract( $record, (string) ( $plan['archetype'] ?? '' ), (array) ( $plan['resolved_visual']['values'] ?? [] ) ) : null;
			$surface_provenance = (array) ( $surface['provenance'] ?? [] );
			if ( ! is_array( $expected_surface ) || ! in_array( $surface['mode'] ?? '', [ 'card', 'transparent_divider' ], true ) || ( $surface['owner_role'] ?? '' ) !== $expected_surface['owner_role'] || ( $surface['mode'] ?? '' ) !== $expected_surface['mode'] || ( $surface['interior'] ?? '' ) !== 'transparent_by_default' || ( $surface_provenance['record_id'] ?? '' ) !== $record_id || ( $surface_provenance['record_hash'] ?? '' ) !== ( $plan['composition_decision']['record_hash'] ?? '' ) ) {
				$errors[] = 'visual_policy_item_surface_owner_invalid';
			}
			foreach ( [ 'radius', 'padding', 'padding_tablet', 'padding_mobile', 'border_width' ] as $measure_key ) {
				if ( ! wpae_design_plan_layout_measure_valid( $surface[ $measure_key ] ?? null ) ) { $errors[] = 'visual_policy_item_surface_measure_invalid:' . $measure_key; }
			}
			foreach ( [ 'background', 'border_color' ] as $color_key ) {
				$color = strtolower( trim( (string) ( $surface[ $color_key ] ?? '' ) ) );
				if ( $color !== 'transparent' && ! preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/', $color ) ) { $errors[] = 'visual_policy_item_surface_color_invalid:' . $color_key; }
			}
			if ( ! in_array( $surface['border_style'] ?? '', [ 'solid', 'none' ], true ) ) { $errors[] = 'visual_policy_item_surface_border_invalid'; }
		} elseif ( ! empty( $surface['contract_version'] ) && (int) $surface['contract_version'] !== 2 ) {
			$errors[] = 'visual_policy_item_surface_version_unsupported';
		}
		$placement = in_array( $plan['sections'][0]['composition'] ?? '', [ 'split_60_40', 'split_50_50', 'split_40_60' ], true ) ? 'split_copy' : 'above_collection';
		if ( ( $visual_policy['intro']['placement'] ?? '' ) !== $placement ) { $errors[] = 'visual_policy_placement_composition_conflict'; }
		if ( ( $plan['responsive']['tablet'] ?? 'stack' ) === 'stack' && ( $visual_policy['collection']['columns']['tablet'] ?? 0 ) !== 1 ) { $errors[] = 'visual_policy_responsive_conflict'; }
		foreach ( (array) ( $visual_policy['collection']['columns'] ?? [] ) as $columns ) { if ( ! is_int( $columns ) || $columns < 1 || $columns > 6 ) { $errors[] = 'visual_policy_columns_invalid'; } }
		if ( isset( $visual_policy['collection']['item_count'] ) && ( ! is_int( $visual_policy['collection']['item_count'] ) || $visual_policy['collection']['item_count'] < 0 || $visual_policy['collection']['item_count'] > 8 ) ) { $errors[] = 'visual_policy_item_count_invalid'; }
		$actual_collection_count = max( array_merge( [ 0 ], array_map( static fn( $child ): int => is_array( $child ) ? count( (array) ( $child['items'] ?? [] ) ) : 0, (array) ( $plan['sections'][0]['children'] ?? [] ) ) ) );
		if ( $actual_collection_count > 0 && isset( $visual_policy['collection']['item_count'] ) && $visual_policy['collection']['item_count'] !== $actual_collection_count ) { $errors[] = 'visual_policy_item_count_mismatch'; }
		if ( isset( $visual_policy['cards'] ) ) {
			$cards = $visual_policy['cards'];
			if ( ! is_array( $cards ) || ( $cards['direction'] ?? '' ) !== 'column' || ( $cards['row_alignment'] ?? '' ) !== 'stretch' || ( $cards['body_actions_distribution'] ?? '' ) !== 'space_between' || ( $cards['mobile_height'] ?? '' ) !== 'content' || ( $cards['footer'] ?? '' ) !== 'when_actions_exist' ) { $errors[] = 'visual_policy_cards_invalid'; }
			foreach ( [ 'body_copy_gap', 'media_copy_gap', 'body_actions_gap' ] as $gap_key ) { if ( ! preg_match( '/^(?:0|[1-9]\d*(?:\.\d+)?|0\.\d+)(?:px|rem|em)$/', (string) ( $cards[$gap_key] ?? '' ) ) ) { $errors[] = 'visual_policy_cards_spacing_invalid'; } }
		}
		$collection = (array) ( $visual_policy['collection'] ?? [] );
		if ( isset( $collection['axis'] ) ) {
			$expected_axis = ( ( $plan['sections'][0]['composition'] ?? '' ) === 'editorial_list' || ( $plan['recipe_id'] ?? '' ) === 'services.text_icon_list' || ( $visual_policy['entity_layout']['variant'] ?? '' ) === 'editorial_rows' ) ? 'list' : 'grid';
			if ( ! in_array( $collection['axis'], [ 'grid', 'list' ], true ) || $collection['axis'] !== $expected_axis ) { $errors[] = 'visual_policy_collection_axis_invalid'; }
			if ( ! in_array( $collection['alignment'] ?? '', [ 'start', 'center', 'end' ], true ) ) { $errors[] = 'visual_policy_collection_alignment_invalid'; }
			foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) {
				if ( ! wpae_design_plan_layout_measure_valid( $collection['width'][$device] ?? null, true ) ) { $errors[] = 'visual_policy_collection_width_invalid:' . $device; }
				if ( ! wpae_design_plan_layout_measure_valid( $collection['gap'][$device] ?? null ) ) { $errors[] = 'visual_policy_collection_gap_invalid:' . $device; }
			}
			if ( ! in_array( $collection['item_height'] ?? '', [ 'equal_row', 'content' ], true ) ) { $errors[] = 'visual_policy_collection_item_height_invalid'; }
			if ( $collection['axis'] === 'list' && array_filter( (array) ( $collection['columns'] ?? [] ), static fn( $count ): bool => $count !== 1 ) ) { $errors[] = 'visual_policy_list_columns_invalid'; }
			$contains_action = static function ( $value ) use ( &$contains_action ): bool {
				if ( ! is_array( $value ) ) { return false; }
				foreach ( $value as $key => $child ) {
					if ( is_string( $key ) && preg_match( '/(?:action|cta)_ref$/', $key ) && is_scalar( $child ) && trim( (string) $child ) !== '' ) { return true; }
					if ( $contains_action( $child ) ) { return true; }
				}
				return false;
			};
			if ( ( $collection['item_height'] ?? '' ) === 'content' && $contains_action( [ $brief['groups'] ?? [], $brief['content'] ?? [], $plan['sections'] ?? [], $plan['slot_bindings'] ?? [] ] ) ) { $errors[] = 'visual_policy_content_height_with_actions'; }
		}
		if ( isset( $visual_policy['list_row'] ) ) {
			$row = (array) $visual_policy['list_row'];
			if ( ( $collection['axis'] ?? '' ) !== 'list' || ! in_array( $row['direction']['desktop'] ?? '', [ 'row' ], true ) || ! in_array( $row['direction']['tablet'] ?? '', [ 'row' ], true ) || ! in_array( $row['direction']['mobile'] ?? '', [ 'row' ], true ) ) { $errors[] = 'visual_policy_list_row_invalid'; }
			if ( ! wpae_design_plan_layout_measure_valid( $row['icon_width'] ?? null ) || ! wpae_design_plan_layout_measure_valid( $row['copy_measure'] ?? null ) ) { $errors[] = 'visual_policy_list_row_measure_invalid'; }
			foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) { if ( ! wpae_design_plan_layout_measure_valid( $row['gap'][$device] ?? null ) ) { $errors[] = 'visual_policy_list_row_gap_invalid:' . $device; } }
		}
		$entity_tracks = (array) ( $visual_policy['entity_layout']['tracks'] ?? [] );
		if ( $entity_tracks ) {
			$identity_percent = $entity_tracks['identity_percent'] ?? null;
			$copy_percent = $entity_tracks['copy_percent'] ?? null;
			if ( ( $visual_policy['entity_layout']['variant'] ?? '' ) !== 'editorial_rows' || ! is_int( $identity_percent ) || ! is_int( $copy_percent ) || $identity_percent < 20 || $identity_percent > 45 || $copy_percent < 55 || $copy_percent > 80 || $identity_percent + $copy_percent !== 100 ) { $errors[] = 'visual_policy_entity_tracks_invalid'; }
			if ( ! wpae_design_plan_layout_measure_valid( $entity_tracks['copy_measure'] ?? null ) ) { $errors[] = 'visual_policy_entity_copy_measure_invalid'; }
			if ( ( $entity_tracks['direction'] ?? [] ) !== [ 'desktop' => 'row', 'tablet' => 'column', 'mobile' => 'column' ] || ( $entity_tracks['mobile_order'] ?? [] ) !== [ 'identity', 'copy' ] ) { $errors[] = 'visual_policy_entity_responsive_invalid'; }
			foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) { if ( ! wpae_design_plan_layout_measure_valid( $entity_tracks['gap'][$device] ?? null ) ) { $errors[] = 'visual_policy_entity_gap_invalid:' . $device; } }
		}
		if ( isset( $visual_policy['inline_value'] ) ) {
			$inline = $visual_policy['inline_value'];
			foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) { if ( ! in_array( $inline['direction'][$device] ?? '', [ 'row', 'column' ], true ) ) { $errors[] = 'visual_policy_inline_direction_invalid'; } }
			if ( ! in_array( $inline['wrap'] ?? '', [ 'wrap', 'nowrap' ], true ) || ! in_array( $inline['main_align'] ?? '', [ 'flex-start', 'center', 'flex-end' ], true ) || ! in_array( $inline['cross_align'] ?? '', [ 'flex-start', 'center', 'flex-end', 'baseline' ], true ) || ! preg_match( '/^(?:0|[1-9]\d*(?:\.\d+)?|0\.\d+)(?:px|rem|em)$/', (string) ( $inline['gap'] ?? '' ) ) ) { $errors[] = 'visual_policy_inline_invalid'; }
		}
		foreach ( (array) ( $brief['ambiguities'] ?? [] ) as $ambiguity ) { if ( ( $ambiguity['kind'] ?? '' ) === 'conflicting_eyebrow_presentation' ) { $errors[] = 'conflicting_eyebrow_presentation'; } }
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
	$requested_widgets = (array) ( $record['capabilities'] ?? [] );
	foreach ( (array) ( $plan['sections'] ?? [] ) as $section_index => $section ) {
		if ( ! is_array( $section ) || trim( (string) ( $section['id'] ?? '' ) ) === '' ) {
			$errors[] = 'section_' . (int) $section_index;
			continue;
		}
		if ( ! in_array( $section['composition'] ?? '', $schema['compositions'], true ) ) { $errors[] = 'unknown_composition'; }
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
	$selection_policy = (string) ( $plan['composition_decision']['selection_policy'] ?? '' );
	$is_current_canonical_plan = $selection_policy === 'wpae-composition-selection-v2' && ! empty( $plan['resolved_visual'] );
	$token_values = (array) ( $plan['tokens'] ?? [] );
	if ( $is_current_canonical_plan ) {
		$resolved_values = (array) ( $plan['resolved_visual']['values'] ?? [] );
		$resolved_sources = (array) ( $plan['resolved_visual']['sources'] ?? [] );
		$token_values = array_replace( $token_values, $resolved_values );
		foreach ( array_keys( (array) ( $plan['tokens'] ?? [] ) ) as $token_ref ) {
			if ( ! str_starts_with( (string) $token_ref, 'color.' ) ) { continue; }
			$source = (string) ( $resolved_sources[$token_ref] ?? '' );
			if ( ! array_key_exists( $token_ref, $resolved_values ) || $source === '' || $source === 'unconfirmed' ) {
				$errors[] = 'unresolved_palette_provenance:' . sanitize_key( str_replace( '.', '_', (string) $token_ref ) );
			}
		}
	}
	$token_report = function_exists( 'wpae_design_token_validate_refs' ) ? wpae_design_token_validate_refs( array_keys( (array) ( $plan['tokens'] ?? [] ) ), $token_values ) : [ 'ok' => true, 'missing' => [] ];
	if ( ! empty( $token_report['missing'] ) ) {
		$errors[] = 'missing_tokens:' . implode( ',', $token_report['missing'] );
	}
	$contrast_tokens = $is_current_canonical_plan ? (array) ( $plan['resolved_visual']['values'] ?? [] ) : [];
	$contrast_context = $is_current_canonical_plan ? (array) ( $plan['resolved_visual']['contrast_context'] ?? [] ) : [];
	$contrast = function_exists( 'wpae_design_token_validate_contrast' ) ? wpae_design_token_validate_contrast( $contrast_tokens, $contrast_context ) : [ 'ok' => true, 'errors' => [] ];
	if ( empty( $contrast['ok'] ) ) {
		$errors[] = 'contrast:' . implode( ',', (array) ( $contrast['errors'] ?? [] ) );
	}
	return [ 'ok' => empty( $errors ), 'errors' => array_values( $errors ), 'capabilities' => $capabilities, 'tokens' => $token_report, 'contrast' => $contrast ];
}

function wpae_design_plan_hash( array $plan ): string {
	return hash( 'sha256', (string) wp_json_encode( $plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION ) );
}

/** Resolve semantic values once, before compilation. Context layers require confirmation. */
function wpae_design_plan_resolve_visual( array $brief, array $context ): array {
	$defaults = wpae_design_token_defaults();
	$color_refs = array_filter( array_keys( $defaults ), static fn( $ref ): bool => str_starts_with( $ref, 'color.' ) );
	$non_color_defaults = array_diff_key( $defaults, array_fill_keys( $color_refs, true ) );
	$palette = function_exists( 'wpae_design_palette_runtime_sources' ) ? wpae_design_palette_runtime_sources() : [ 'values' => [], 'sources' => [], 'missing' => $color_refs, 'unconfirmed' => [] ];
	$project = (array) ( $palette['values'] ?? [] );
	$project_report = [ 'resolved' => [], 'fallbacks' => [], 'missing' => (array) ( $palette['missing'] ?? [] ) ];
	$page_input = ! empty( $context['page_tokens_confirmed'] ) && is_array( $context['page_tokens'] ?? null ) ? $context['page_tokens'] : [];
	$page_source = sanitize_key( (string) ( $context['page_tokens_source'] ?? '' ) );
	$uses_preview_inheritance = $page_source === 'elementor_preview_computed_body';
	$preview_page_bg_underlay = $uses_preview_inheritance && preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $page_input['color.page_bg'] ?? '' ) )
		? strtolower( (string) $page_input['color.page_bg'] )
		: '';
	$preview_page = [];
	$page = array_intersect_key( $page_input, array_fill_keys( $color_refs, true ) );
	foreach ( $page as $token => $value ) {
		$transparent_allowed = $value === 'transparent' && in_array( $token, [ 'color.surface', 'color.border' ], true );
		if ( ! is_string( $value ) || ( ! $transparent_allowed && ! preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $value ) ) ) { unset( $page[$token] ); }
	}
	if ( $uses_preview_inheritance ) {
		// The current Elementor preview may supply only its measured body context.
		// Do not let inherited CSS replace an explicitly configured WPAE/Elementor
		// palette role; it only fills roles that have no confirmed palette owner.
		$preview_page = array_diff_key( array_intersect_key( $page, array_flip( [ 'color.page_bg', 'color.text' ] ) ), $project );
		$page = [];
	}
	// Reference colors describe an example, not the owner's palette. They can
	// guide non-color styling but never replace confirmed site color roles.
	$profile_id = is_string( $context['visual_profile'] ?? '' ) ? ( $context['visual_profile'] ?? '' ) : '';
	$profile = array_filter( (array) ( wpae_composition_visual_profiles()[ $profile_id ] ?? [] ), static fn( $value, $key ): bool => ! str_starts_with( (string) $key, 'color.' ), ARRAY_FILTER_USE_BOTH );
	$page_bg_underlay = '';
	foreach ( [ $preview_page_bg_underlay, $page['color.page_bg'] ?? '', $project['color.page_bg'] ?? '' ] as $candidate_underlay ) {
		if ( is_string( $candidate_underlay ) && preg_match( '/^#[0-9a-f]{6}$/i', $candidate_underlay ) ) { $page_bg_underlay = strtolower( $candidate_underlay ); break; }
	}
	$explicit = [];
	$surface = wpae_design_plan_constraint_value( $brief, 'surface_color', '' );
	if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', (string) $surface ) ) { $explicit['color.page_bg'] = strtolower( (string) $surface ); }
	$radius = wpae_design_plan_constraint_value( $brief, 'border_radius', '' );
	if ( preg_match( '/^\d+(?:\.\d+)?(?:px|rem|em)$/', (string) $radius ) ) { $explicit['radius.card'] = $radius; }
	foreach ( (array) ( $brief['layout_constraints'] ?? [] ) as $constraint ) { if ( ( $constraint['kind'] ?? '' ) === 'visual_token' && array_key_exists( $constraint['token'] ?? '', array_replace( $defaults, $profile ) ) ) { $explicit[ $constraint['token'] ] = $constraint['value']; } }
	$values = array_replace( $non_color_defaults, $project, $preview_page, $page, $profile, $explicit );
	if ( ! isset( $values['color.surface'] ) && isset( $values['color.page_bg'] ) ) {
		if ( strlen( ltrim( (string) $values['color.page_bg'], '#' ) ) === 8 && $page_bg_underlay !== '' ) {
			$values['color.surface'] = $page_bg_underlay;
			$palette['sources']['color.surface'] = 'semantic_alias:opaque_page_background_underlay';
		} else {
			$values['color.surface'] = $values['color.page_bg'];
			$palette['sources']['color.surface'] = 'semantic_alias:confirmed_color.page_bg';
		}
	}
	if ( ! isset( $values['color.muted'] ) && isset( $values['color.text'] ) ) {
		$values['color.muted'] = $values['color.text'];
		$palette['sources']['color.muted'] = 'semantic_alias:confirmed_color.text';
	}
	if ( ! isset( $values['color.border'] ) && isset( $values['color.page_bg'] ) ) {
		$values['color.border'] = 'transparent';
		$palette['sources']['color.border'] = 'no_confirmed_border_role_transparent';
	}
	$accent_reference = is_array( $palette['accent_reference'] ?? null ) ? $palette['accent_reference'] : [];
	$accent_value = strtolower( trim( (string) ( $accent_reference['value'] ?? '' ) ) );
	$section_bg_underlay = preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $values['color.page_bg'] ?? '' ) )
		? strtolower( (string) $values['color.page_bg'] )
		: $page_bg_underlay;
	if ( preg_match( '/^#[0-9a-f]{6}$/i', $accent_value ) && $section_bg_underlay !== '' ) {
		// 20% opacity is 80% transparency. Keep this separate from primary so a
		// palette Accent can tint section surfaces without changing buttons/text.
		$values['color.section_bg'] = $accent_value . '33';
		$palette['sources']['color.section_bg'] = 'derived_palette_accent_20pct_opacity:' . (string) ( $accent_reference['source'] ?? 'unknown_accent_source' );
	}
	$value_errors = [];
	foreach ( (array) ( $palette['missing'] ?? [] ) as $missing_role ) {
		if ( ! array_key_exists( $missing_role, $values ) || ( $values[$missing_role] === 'transparent' && $missing_role !== 'color.border' && empty( $values['color.page_bg'] ) ) ) { $value_errors[] = 'confirmed_palette_role_missing:' . str_replace( '.', '_', (string) $missing_role ); }
	}
	if ( ( $values['color.surface'] ?? '' ) === 'transparent' && empty( $values['color.page_bg'] ) ) { $value_errors[] = 'transparent_surface_without_confirmed_context'; }
	if ( strlen( ltrim( (string) ( $values['color.page_bg'] ?? '' ), '#' ) ) === 8 && $page_bg_underlay === '' ) { $value_errors[] = 'alpha_page_background_requires_confirmed_opaque_underlay'; }
	foreach ( $values as $key => $value ) {
		if ( ! str_starts_with( (string) $key, 'color.' ) ) { continue; }
		$transparent_allowed = in_array( $key, [ 'color.surface', 'color.border' ], true ) && $value === 'transparent';
		$alpha_allowed = in_array( $key, [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true );
		$color_pattern = $alpha_allowed ? '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i' : '/^#[0-9a-f]{6}$/i';
		if ( ! $transparent_allowed && ( ! is_string( $value ) || ! preg_match( $color_pattern, $value ) ) ) {
			$value_errors[] = 'visual_color_invalid_or_unverifiable:' . sanitize_key( str_replace( '.', '_', (string) $key ) );
		}
	}
	$required_palette_roles = [ 'color.page_bg', 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.focus', 'color.hover' ];
	$palette_complete = ! array_diff( $required_palette_roles, array_keys( $values ) );
	$contrast_context = $page_bg_underlay !== '' ? [ 'background_underlay' => $page_bg_underlay, 'color.page_bg_underlay' => $page_bg_underlay, 'color.surface_underlay' => $page_bg_underlay ] : [];
	if ( isset( $values['color.section_bg'] ) && $section_bg_underlay !== '' ) { $contrast_context['color.section_bg_underlay'] = $section_bg_underlay; }
	$contrast = $palette_complete ? wpae_design_token_validate_contrast( $values, $contrast_context ) : [ 'ok' => false, 'pairs' => [], 'errors' => [ 'confirmed_palette_incomplete' ] ];
	if ( $profile_id !== '' ) {
		if ( isset( $values['color.surface'], $values['color.text'], $values['color.muted'], $values['color.primary'], $values['color.focus'], $values['color.hover'] ) ) {
			$surface_contrast = wpae_design_token_validate_contrast( array_replace( $values, [ 'color.page_bg' => $values['color.surface'] ] ), $contrast_context );
			foreach ( $surface_contrast['errors'] as $error ) { $value_errors[] = 'visual_surface_contrast:' . $error; }
		}
	}
	if ( $profile_id !== '' ) {
		foreach ( $values as $key => $value ) {
			$profile_color_pattern = in_array( $key, [ 'color.page_bg', 'color.surface', 'color.section_bg' ], true ) ? '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i' : '/^#[0-9a-f]{6}$/i';
			if ( str_starts_with( $key, 'color.' ) && ( ! is_string( $value ) || ( $key === 'color.border' ? ( $value !== 'transparent' && ! preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) : ! preg_match( $profile_color_pattern, $value ) ) ) ) { $value_errors[] = 'visual_color_invalid:' . $key; }
			if ( str_starts_with( $key, 'space.' ) || str_starts_with( $key, 'radius.' ) || $key === 'layout.copy_width' ) {
				if ( ! is_string( $value ) || ! preg_match( '/^\d+(?:\.\d+)?(?:px|rem|em)$/', $value ) ) { $value_errors[] = 'visual_dimension_invalid:' . $key; }
			}
			if ( str_starts_with( $key, 'type.' ) ) {
				if ( ! is_array( $value ) ) { $value_errors[] = 'visual_type_invalid:' . $key; continue; }
				if ( ! is_scalar( $value['weight'] ?? null ) || ! preg_match( '/^[1-9]00$/', (string) $value['weight'] ) || ! isset( $value['line_height'] ) || ! is_string( $value['font_family'] ?? null ) || $value['font_family'] === '' ) { $value_errors[] = 'visual_type_hierarchy_invalid:' . $key; }
				foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) { if ( ! is_string( $value[ $device ] ?? null ) || ! preg_match( '/^(?:[1-9]\d*(?:\.\d+)?|0\.\d+)(?:px|rem|em)$/', $value[ $device ] ) || (float) $value[ $device ] <= 0 ) { $value_errors[] = 'visual_type_size_invalid:' . $key; } }
				foreach ( [ 'line_height', 'line_height_tablet', 'line_height_mobile' ] as $field ) { if ( isset( $value[ $field ] ) && ( ! is_numeric( $value[ $field ] ) || (float) $value[ $field ] < 1 || (float) $value[ $field ] > 3 ) ) { $value_errors[] = 'visual_type_line_invalid:' . $key; } }
			}
		}
	}
	$adjustments = [];
	if ( $profile_id === '' && in_array( 'color.muted_on_color.page_bg', (array) ( $contrast['errors'] ?? [] ), true ) && ! isset( $explicit['color.muted'] ) ) { $value_errors[] = 'confirmed_muted_color_fails_contrast'; }
	$viewport = is_array( $context['page_tokens_viewport'] ?? null ) ? $context['page_tokens_viewport'] : [];
	$page_context = $uses_preview_inheritance && $preview_page ? [ 'source' => 'elementor_preview_computed_body', 'post_id' => absint( $context['post_id'] ?? 0 ), 'viewport' => [ 'width' => absint( $viewport['width'] ?? 0 ), 'height' => absint( $viewport['height'] ?? 0 ) ], 'roles' => array_keys( $preview_page ) ] : null;
	$sources = [];
	foreach ( $values as $ref => $value ) { $sources[ $ref ] = isset( $explicit[ $ref ] ) ? 'explicit_brief' : ( isset( $profile[ $ref ] ) ? 'visual_profile:' . $profile_id : ( isset( $preview_page[ $ref ] ) ? 'confirmed_elementor_preview_body' : ( isset( $page[ $ref ] ) ? 'confirmed_page' : ( $palette['sources'][$ref] ?? ( str_starts_with( $ref, 'color.' ) ? 'unconfirmed' : 'safe_default' ) ) ) ) ); }
	if ( $adjustments ) { $sources['color.muted'] = 'plan_contrast_adjustment'; }
	return [ 'errors' => array_values( array_unique( $value_errors ) ), 'profile' => $profile_id, 'values' => $values, 'sources' => $sources, 'palette' => [ 'sources' => $palette['sources'] ?? [], 'accent_reference' => $accent_reference, 'missing' => $palette['missing'] ?? [], 'unconfirmed_project_defaults' => $palette['unconfirmed'] ?? [], 'elementor_global_color_ids' => $palette['global_color_ids'] ?? [] ], 'page_context' => $page_context, 'contrast_context' => $contrast_context, 'precedence' => [ 'safe_non_color_default', 'confirmed_wpae_project_option_or_elementor_global_color', 'elementor_preview_inherited_context_missing_roles_only', 'confirmed_page', 'selected_visual_profile_non_color', 'explicit_brief' ], 'adjustments' => $adjustments, 'contrast' => $contrast ];
}
