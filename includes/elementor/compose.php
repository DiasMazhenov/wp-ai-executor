<?php

defined( 'ABSPATH' ) || exit;

function wpae_replace_placeholders_recursive( $value, array $slots ) {
    if ( is_string( $value ) ) {
        foreach ( $slots as $slot => $slot_value ) {
            $value = str_replace( '{{' . $slot . '}}', (string) $slot_value, $value );
        }
        return $value;
    }

    if ( is_array( $value ) ) {
        foreach ( $value as $key => $child ) {
            $value[ $key ] = wpae_replace_placeholders_recursive( $child, $slots );
        }
    }

    return $value;
}

function wpae_rekey_elementor_ids_recursive( array $elements, string $instance_id ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $old_id = (string) ( $element['id'] ?? $index );
        $element['id'] = substr( md5( $instance_id . '|' . $old_id . '|' . $index ), 0, 7 );

        if ( ( $element['widgetType'] ?? '' ) === 'accordion' ) {
            foreach ( (array) ( $element['settings']['tabs'] ?? [] ) as $tab_index => $tab ) { $element['settings']['tabs'][ $tab_index ]['_id'] = substr( md5( $instance_id . '|tab|' . ( $tab['_id'] ?? $tab_index ) ), 0, 7 ); }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_rekey_elementor_ids_recursive( $element['elements'], $instance_id . '|' . $old_id );
        }

        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_compose( WP_REST_Request $request ): WP_REST_Response {
    if ( $request->get_param( 'composition_record' ) !== null || $request->get_param( 'canonical_brief' ) !== null ) { return wpae_elementor_compose_typed( $request ); }
    $recipe_id = wpae_sanitize_elementor_recipe_id( (string) ( $request->get_param( 'recipe_id' ) ?: $request->get_param( 'id' ) ) );
    $variant = sanitize_key( (string) $request->get_param( 'variant' ) );
    $input_slots = $request->get_param( 'slots' );
    $input_slots = is_array( $input_slots ) ? $input_slots : [];
    $recipes = wpae_elementor_recipe_definitions();

    if ( ! isset( $recipes[ $recipe_id ] ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Recipe not found.', 'available' => array_keys( $recipes ) ], 404 );
    }

    $recipe = $recipes[ $recipe_id ];
    if ( $variant === '' ) {
        $variant = (string) $recipe['default_variant'];
    }

    if ( ! in_array( $variant, $recipe['variants'], true ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Variant is not available for this recipe.', 'available_variants' => $recipe['variants'] ], 400 );
    }

    $slots = [];
    $missing_required = [];
    foreach ( $recipe['slots'] as $slot => $schema ) {
        if ( array_key_exists( $slot, $input_slots ) && (string) $input_slots[ $slot ] !== '' ) {
            $slots[ $slot ] = is_scalar( $input_slots[ $slot ] ) ? sanitize_text_field( (string) $input_slots[ $slot ] ) : wp_json_encode( $input_slots[ $slot ] );
        } else {
            if ( ! empty( $schema['required'] ) ) {
                $missing_required[] = $slot;
            }
            $slots[ $slot ] = (string) ( $schema['default'] ?? '' );
        }
    }

    $elementor_data = wpae_replace_placeholders_recursive( $recipe['elementor_data'], $slots );
    $instance_id = sanitize_key( (string) ( $request->get_param( 'instance_id' ) ?: $recipe_id . '-' . $variant . '-' . substr( md5( wp_json_encode( $slots ) ), 0, 8 ) ) );
    $elementor_data = wpae_rekey_elementor_ids_recursive( $elementor_data, $instance_id );
    $normalized = wpae_elementor_normalize_data( $elementor_data );
    $elementor_data = $normalized['data'];
    $errors = wpae_validate_elementor_data_array( $elementor_data );
    $stats = wpae_default_elementor_audit_stats();
    wpae_collect_elementor_audit_stats( $elementor_data, $stats );
    wpae_collect_elementor_design_quality_stats( $elementor_data, $stats );
    wpae_finalize_elementor_audit_stats( $stats );

    $ok = empty( $errors ) && empty( $missing_required );

    return new WP_REST_Response( [
        'ok' => $ok,
        'recipe_id' => $recipe_id,
        'variant' => $variant,
        'requested_variant' => $variant,
        'effective_variant' => $recipe['default_variant'],
        'variant_kind' => wpae_elementor_recipe_variants( $recipe )[ $variant ]['variant_kind'],
        'distinct' => wpae_elementor_recipe_variants( $recipe )[ $variant ]['distinct'],
        'implementation_status' => wpae_elementor_recipe_variants( $recipe )[ $variant ]['implementation_status'],
        'write_count' => 0,
        'instance_id' => $instance_id,
        'missing_required_slots' => $missing_required,
        'slots_used' => $slots,
        'normalization' => [
            'change_counts' => $normalized['report']['counts'],
            'changes' => $normalized['report']['changes'],
        ],
        'errors' => $errors,
        'stats' => $stats,
        'elementor_data' => $elementor_data,
        'next_steps' => [ 'POST /elementor/normalize', 'POST /elementor/validate', 'POST /elementor/page' ],
    ], $ok ? 200 : 422 );
}



/** Structural signature uses native widget/container layout, excluding IDs and authored values. */
function wpae_composition_native_layout_signature( array $nodes ): string {
	$project = static function ( array $items ) use ( &$project ): array {
		$out = [];
		foreach ( $items as $node ) {
			if ( ! is_array( $node ) ) { continue; }
			$settings = (array) ( $node['settings'] ?? [] );
			$layout = [];
			foreach ( $settings as $key => $value ) {
				if ( preg_match( '/(?:flex|width|gap|align|justify|direction|wrap|order|position|columns|size|container_type)/i', (string) $key ) && ! preg_match( '/(?:color|background|text|title|editor|link|image)/i', (string) $key ) ) { $layout[$key] = $value; }
			}
			$out[] = [ 'elType' => (string) ( $node['elType'] ?? '' ), 'widgetType' => (string) ( $node['widgetType'] ?? '' ), 'layout' => $layout, 'children' => $project( (array) ( $node['elements'] ?? [] ) ) ];
		}
		return $out;
	};
	return hash( 'sha256', wp_json_encode( $project( $nodes ) ) );
}

/** Preview canonical Brief candidates through the accepted Plan/compiler; never parses free text or writes. */
function wpae_elementor_compose_typed( WP_REST_Request $request ): WP_REST_Response {
	foreach ( [ 'recipe_id', 'id', 'variant', 'slots' ] as $legacy_key ) { if ( $request->get_param( $legacy_key ) !== null ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'mixed_legacy_typed_contract' ], 'write_count' => 0 ], 400 ); } }
	$brief = $request->get_param( 'canonical_brief' );
	$preview_alternatives = filter_var( $request->get_param( 'preview_alternatives' ), FILTER_VALIDATE_BOOLEAN );
	$requested_record = $request->get_param( 'composition_record' );
	if ( ! is_array( $brief ) || ( ! is_string( $requested_record ) || $requested_record === '' ) && ! $preview_alternatives ) {
		return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'canonical_brief_and_record_required' ], 'write_count' => 0 ], 400 );
	}
	$brief['canonical_create'] = true;
	$validation = wpae_brief_ir_validate( $brief );
	if ( empty( $validation['ok'] ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => $validation['errors'], 'write_count' => 0 ], 422 ); }
	$context = [ 'canonical_create' => true ];
	foreach ( [ 'composition_record', 'composition_version', 'visual_profile', 'page_tokens', 'page_tokens_confirmed', 'reference_tokens', 'reference_tokens_confirmed', 'media_references', 'services_lead_service_ref' ] as $key ) { $value = $request->get_param( $key ); if ( $value !== null ) { $context[$key] = $value; } }
	$decision = wpae_composition_decide( $brief, $context );
	if ( ! empty( $decision['errors'] ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => $decision['errors'], 'diagnostics' => [ 'brief_hash' => wpae_brief_ir_hash( $brief ), 'composition_decision' => $decision ], 'write_count' => 0, 'provider_calls' => 0 ], 422 ); }
	$record_ids = [ (string) $decision['record']['id'] ];
	if ( $preview_alternatives && empty( $requested_record ) ) { foreach ( (array) ( $decision['alternatives'] ?? [] ) as $alternative ) { if ( is_array( $alternative ) && ! empty( $alternative['record_id'] ) ) { $record_ids[] = (string) $alternative['record_id']; } } }
	$instance = $request->get_param( 'instance_id' );
	if ( $instance !== null && ( ! is_string( $instance ) || sanitize_key( $instance ) === '' ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'instance_id_invalid' ], 'write_count' => 0 ], 400 ); }
	$candidates = []; $rejected = []; $signatures = [];
	foreach ( array_slice( array_values( array_unique( $record_ids ) ), 0, 3 ) as $record_id ) {
		$accepted = wpae_composition_decision_for_record( $brief, $decision, $record_id );
		if ( ! empty( $accepted['errors'] ) ) { $rejected[$record_id] = $accepted['errors']; continue; }
		$candidate_context = array_merge( $context, [ 'composition_decision' => $accepted, 'visual_profile' => (string) ( $accepted['visual_profile'] ?? '' ) ] );
		if ( ( $brief['intent']['archetype'] ?? '' ) === 'services' ) { $candidate_context['services_recipe_id'] = $record_id; $candidate_context['services_recipe_selection_source'] = (string) ( $accepted['source'] ?? 'content_ranked_catalog' ); }
		$plan = wpae_design_plan_from_brief( $brief, $candidate_context );
		$plan_validation = wpae_design_plan_validate( $plan, $brief );
		$diagnostics = [ 'brief_hash' => wpae_brief_ir_hash( $brief ), 'plan_hash' => wpae_design_plan_hash( $plan ), 'composition_decision' => $plan['composition_decision'] ?? [], 'resolved_visual' => $plan['resolved_visual'] ?? [], 'validation' => $plan_validation ];
		if ( empty( $plan_validation['ok'] ) ) { $rejected[$record_id] = $plan_validation['errors']; continue; }
		$layout = wpae_layout_report_for_plan( $plan, [ 'tokens' => $plan['resolved_visual']['values'] ?? [] ] );
		$layout_validation = wpae_layout_report_validate( $layout );
		$diagnostics['layout'] = [ 'report' => $layout, 'validation' => $layout_validation ];
		if ( empty( $layout['ok'] ) || empty( $layout_validation['ok'] ) ) { $rejected[$record_id] = [ 'layout_report_rejected' ]; continue; }
		$ir = wpae_elementor_ir_from_design_plan( $plan, $brief );
		$compiled = wpae_native_elementor_compile( $ir, $brief, [], [ 'resolved_visual' => $plan['resolved_visual'], 'id_seed' => 'typed-composer-v2' ] );
		$data = (array) ( $compiled['elementor_data'] ?? [] );
		$candidate_instance = $instance !== null ? sanitize_key( $instance ) : substr( hash( 'sha256', $diagnostics['plan_hash'] ), 0, 24 );
		$data = wpae_rekey_elementor_ids_recursive( $data, $candidate_instance );
		$before = wpae_llm_decision_signature( $data );
		$normalized = wpae_elementor_normalize_data( $data );
		$errors = array_merge( (array) ( $compiled['errors'] ?? [] ), wpae_validate_elementor_data_array( $normalized['data'] ) );
		if ( $before !== wpae_llm_decision_signature( $normalized['data'] ) ) { $errors[] = 'frozen_decisions_mutated'; }
		if ( ! empty( $errors ) || empty( $compiled['ok'] ) ) { $rejected[$record_id] = array_values( array_unique( $errors ?: [ 'native_compile_rejected' ] ) ); continue; }
		$native_signature = wpae_composition_native_layout_signature( $normalized['data'] );
		$topology_signature = wpae_composition_topology_signature( $accepted['record'] );
		if ( isset( $signatures[$native_signature] ) ) { $rejected[$record_id] = [ 'compiled_native_layout_not_distinct_from:' . $signatures[$native_signature] ]; continue; }
		$signatures[$native_signature] = $record_id;
		$stats = wpae_default_elementor_audit_stats();
		wpae_collect_elementor_audit_stats( $normalized['data'], $stats ); wpae_collect_elementor_design_quality_stats( $normalized['data'], $stats ); wpae_finalize_elementor_audit_stats( $stats );
		$candidates[] = [ 'record_id' => $record_id, 'record_version' => (int) $accepted['record']['version'], 'record_hash' => (string) $accepted['record']['hash'], 'selection_reason' => (array) ( $accepted['selection_reasons'] ?? [] ), 'profile' => (string) ( $accepted['visual_profile'] ?? '' ), 'topology_signature' => $topology_signature, 'native_layout_signature' => $native_signature, 'plan_hash' => $diagnostics['plan_hash'], 'brief_hash' => $diagnostics['brief_hash'], 'plan' => $diagnostics, 'stats' => $stats, 'elementor_data' => $normalized['data'], 'instance_id' => $candidate_instance ];
	}
	if ( ! $candidates ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'all_composition_candidates_rejected' ], 'rejected_candidates' => $rejected, 'write_count' => 0, 'provider_calls' => 0 ], 422 ); }
	$selected = array_shift( $candidates );
	if ( $selected['record_id'] !== (string) $decision['record']['id'] ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'selected_composition_failed_validation' ], 'selected_record_id' => $decision['record']['id'], 'rejected_candidates' => $rejected, 'write_count' => 0 ], 422 ); }
	return new WP_REST_Response( [ 'ok' => true, 'recipe_id' => $selected['record_id'], 'variant' => $selected['record_id'], 'instance_id' => $selected['instance_id'], 'requested_variant' => $selected['record_id'], 'effective_variant' => $selected['record_id'], 'variant_kind' => wpae_composition_records()[$selected['record_id']]['variant_kind'], 'distinct' => wpae_composition_records()[$selected['record_id']]['distinct'], 'implementation_status' => 'implemented_source', 'missing_required_slots' => [], 'slots_used' => $selected['plan']['composition_decision']['slot_bindings'] ?? [], 'stats' => $selected['stats'], 'errors' => [], 'elementor_data' => $selected['elementor_data'], 'diagnostics' => $selected['plan'], 'selection' => [ 'source' => $decision['source'], 'policy_version' => $decision['policy_version'], 'catalog_id' => $decision['catalog_id'], 'catalog_identity' => $decision['catalog_identity'], 'brief_hash' => $decision['brief_hash'], 'metrics' => $decision['metrics'], 'reasons' => $selected['selection_reason'] ], 'alternatives' => $candidates, 'rejected_candidates' => $rejected, 'write_count' => 0, 'provider_calls' => 0, 'render_status' => 'NOT_RUN' ], 200 );
}
