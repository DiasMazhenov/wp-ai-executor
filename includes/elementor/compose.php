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



/** Preview accepts canonical typed Brief only. It never parses free text or writes. */
function wpae_elementor_compose_typed( WP_REST_Request $request ): WP_REST_Response {
	foreach ( [ 'recipe_id', 'id', 'variant', 'slots' ] as $legacy_key ) {
		if ( $request->get_param( $legacy_key ) !== null ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'mixed_legacy_typed_contract' ], 'write_count' => 0 ], 400 ); }
	}
	$brief = $request->get_param( 'canonical_brief' );
	if ( ! is_array( $brief ) || ! is_string( $request->get_param( 'composition_record' ) ) || $request->get_param( 'composition_record' ) === '' ) {
		return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'canonical_brief_and_record_required' ], 'write_count' => 0 ], 400 );
	}
	$brief['canonical_create'] = true;
	$validation = wpae_brief_ir_validate( $brief );
	if ( empty( $validation['ok'] ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => $validation['errors'], 'write_count' => 0 ], 422 ); }
	$context = [ 'canonical_create' => true ];
	foreach ( [ 'composition_record', 'composition_version', 'visual_profile', 'page_tokens', 'page_tokens_confirmed', 'reference_tokens', 'reference_tokens_confirmed' ] as $key ) {
		$value = $request->get_param( $key ); if ( $value !== null ) { $context[ $key ] = $value; }
	}
	$plan = wpae_design_plan_from_brief( $brief, $context );
	$validation = wpae_design_plan_validate( $plan, $brief );
	$diagnostics = [ 'brief_hash' => wpae_brief_ir_hash( $brief ), 'plan_hash' => wpae_design_plan_hash( $plan ), 'composition_decision' => $plan['composition_decision'] ?? [], 'resolved_visual' => $plan['resolved_visual'] ?? [], 'validation' => $validation ];
	if ( empty( $validation['ok'] ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => $validation['errors'], 'diagnostics' => $diagnostics, 'write_count' => 0 ], 422 ); }
	$layout = wpae_layout_report_for_plan( $plan, [ 'tokens' => $plan['resolved_visual']['values'] ?? [] ] );
	$layout_validation = wpae_layout_report_validate( $layout );
	$diagnostics['layout'] = [ 'report' => $layout, 'validation' => $layout_validation ];
	if ( empty( $layout['ok'] ) || empty( $layout_validation['ok'] ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'layout_report_rejected' ], 'diagnostics' => $diagnostics, 'write_count' => 0 ], 422 ); }
	$ir = wpae_elementor_ir_from_design_plan( $plan, $brief );
	$compiled = wpae_native_elementor_compile( $ir, $brief, [], [ 'resolved_visual' => $plan['resolved_visual'], 'id_seed' => 'typed-composer-v1' ] );
	$data = (array) ( $compiled['elementor_data'] ?? [] );
	$instance = $request->get_param( 'instance_id' );
	if ( $instance !== null && ( ! is_string( $instance ) || sanitize_key( $instance ) === '' ) ) { return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'instance_id_invalid' ], 'write_count' => 0 ], 400 ); }
	$instance = $instance !== null ? sanitize_key( $instance ) : substr( hash( 'sha256', $diagnostics['plan_hash'] ), 0, 24 );
	$data = wpae_rekey_elementor_ids_recursive( $data, $instance );
	$before = wpae_llm_decision_signature( $data );
	$normalized = wpae_elementor_normalize_data( $data );
	$errors = array_merge( (array) ( $compiled['errors'] ?? [] ), wpae_validate_elementor_data_array( $normalized['data'] ) );
	if ( $before !== wpae_llm_decision_signature( $normalized['data'] ) ) { $errors[] = 'frozen_decisions_mutated'; }
	$ok = ! empty( $compiled['ok'] ) && empty( $errors );
	$stats = wpae_default_elementor_audit_stats();
	wpae_collect_elementor_audit_stats( $normalized['data'], $stats );
	wpae_collect_elementor_design_quality_stats( $normalized['data'], $stats );
	wpae_finalize_elementor_audit_stats( $stats );
	return new WP_REST_Response( [ 'ok' => $ok, 'recipe_id' => $context['composition_record'], 'variant' => $context['composition_record'], 'instance_id' => $instance,
		'requested_variant' => $context['composition_record'], 'effective_variant' => $plan['composition_decision']['record_id'],
		'variant_kind' => wpae_composition_records()[ $context['composition_record'] ]['variant_kind'], 'distinct' => wpae_composition_records()[ $context['composition_record'] ]['distinct'], 'implementation_status' => 'implemented_source',
		'missing_required_slots' => [], 'slots_used' => $plan['composition_decision']['slot_bindings'], 'normalization' => [ 'change_counts' => $normalized['report']['counts'], 'changes' => $normalized['report']['changes'] ],
		'stats' => $stats, 'next_steps' => [ 'POST /elementor/validate', 'POST /elementor/page' ], 'errors' => $errors, 'elementor_data' => $ok ? $normalized['data'] : [], 'diagnostics' => $diagnostics, 'write_count' => 0, 'provider_calls' => 0, 'render_status' => 'NOT_RUN' ], $ok ? 200 : 422 );
}
