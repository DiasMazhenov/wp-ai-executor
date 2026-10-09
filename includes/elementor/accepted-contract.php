<?php
/** Bounded server-owned typed decisions. No browser state is authoritative. */
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__ ) . '/vision/report-store.php';
require_once __DIR__ . '/native-roundtrip.php';
const WPAE_ACCEPTED_CONTRACT_OPTION = 'wp_ai_executor_accepted_contracts';
const WPAE_ACCEPTED_CONTRACT_SCHEMA = 'wpae-accepted-contract-v1';
const WPAE_ACCEPTED_CONTRACT_TTL = 7200;
const WPAE_ACCEPTED_CONTRACT_LIMIT = 20;
const WPAE_ACCEPTED_CONTRACT_BYTES = 262144;

function wpae_accepted_owned_roots( array $data, array $ids ): array {
    return array_values( array_filter( $data, static fn( $root ): bool => is_array( $root ) && in_array( (string) ( $root['id'] ?? '' ), $ids, true ) ) );
}

function wpae_accepted_owned_fingerprint( array $roots ): string {
    return hash( 'sha256', wp_json_encode( [ array_column( $roots, 'id' ), wpae_llm_decision_signature( $roots ) ] ) );
}

/** Live models materialize Elementor defaults. Only server-verified defaults may be omitted. */
function wpae_accepted_native_defaults( array $node ): array {
    if ( ! class_exists( '\\Elementor\\Plugin' ) ) { return []; }
    $minimal = array_intersect_key( $node, array_flip( [ 'id', 'elType', 'widgetType', 'isInner' ] ) );
    $minimal['settings'] = []; $minimal['elements'] = [];
    $manager = \Elementor\Plugin::instance()->elements_manager ?? null;
    if ( ! is_object( $manager ) || ! method_exists( $manager, 'create_element_instance' ) ) { return []; }
    $instance = $manager->create_element_instance( $minimal );
    if ( ! $instance ) { return []; }
    $controls = method_exists( $instance, 'get_controls' ) ? (array) $instance->get_controls() : [];
    return wpae_accepted_expand_responsive_defaults( (array) $instance->get_settings(), $controls );
}
/** Settings serialization omits declared device defaults; recover only registered values. */
function wpae_accepted_expand_responsive_defaults( array $defaults, array $controls ): array {
    foreach ( $controls as $key => $control ) {
        if ( ! is_array( $control ) || empty( $control['is_responsive'] ) ) { continue; }
        foreach ( [ 'tablet', 'mobile' ] as $device ) {
            $device_key = $key . '_' . $device;
            if ( ! array_key_exists( $device_key, $defaults ) && array_key_exists( $device . '_default', $control ) ) {
                $defaults[$device_key] = $control[$device . '_default'];
                // Native slider device defaults omit the unused multi-value slot.
                if ( ( $control['type'] ?? '' ) === 'slider' && is_array( $defaults[$device_key] ) && ! array_key_exists( 'sizes', $defaults[$device_key] ) && ( $control['default']['sizes'] ?? null ) === [] ) {
                    $defaults[$device_key]['sizes'] = [];
                }
            }
        }
    }
    return $defaults;
}
function wpae_accepted_control_equal( string $key, $a, $b, string $widget_type = '', ?array &$projected_value = null, ?array &$mismatch = null, ?array &$roundtrip_changes = null, ?string $adapter_version = null ): bool {
	if ( $widget_type === 'accordion' && $key === 'tabs' ) {
		$projected_value = wpae_native_roundtrip_project_accordion_tabs( $a, $b, $mismatch, $roundtrip_changes, $adapter_version );
		return $projected_value !== null;
	}
	if ( $widget_type === 'text-editor' && $key === 'editor' ) {
		$projection_changes = [];
		$projected_value = wpae_native_roundtrip_project_text_editor( $a, $b, $projection_changes, $adapter_version );
		if ( $projected_value === null ) {
			if ( $a !== $b ) { $mismatch = [ 'control' => 'editor', 'reason' => 'authored_control_changed' ]; }
			return false;
		}
		if ( $roundtrip_changes === null ) { $roundtrip_changes = []; }
		array_push( $roundtrip_changes, ...$projection_changes );
		return true;
	}
	$projected_value = $b;
	return hash_equals( wpae_llm_decision_signature( [ [ 'settings' => [ $key => $a ] ] ] ), wpae_llm_decision_signature( [ [ 'settings' => [ $key => $b ] ] ] ) );
}
function wpae_accepted_project_owned_model( array $expected, array $current, ?callable $defaults_provider = null, ?array &$mismatch = null, array $roundtrip_context = [], ?array &$roundtrip_changes = null ): ?array {
	if ( $roundtrip_changes === null ) { $roundtrip_changes = []; }
	$adapter_version = $roundtrip_context['version'] ?? null;
    if ( count( $expected ) !== count( $current ) ) { $mismatch = [ 'reason' => 'node_count' ]; return null; }
    $projected = [];
    foreach ( $expected as $index => $node ) {
        $actual = $current[$index] ?? [];
        foreach ( [ 'id', 'elType', 'widgetType' ] as $key ) { if ( ( $node[$key] ?? '' ) !== ( $actual[$key] ?? '' ) ) { $mismatch = [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'node_identity' ]; return null; } }
        $defaults = $defaults_provider ? $defaults_provider( $node ) : wpae_accepted_native_defaults( $node );
        $authored = (array) ( $node['settings'] ?? [] ); $settings = (array) ( $actual['settings'] ?? [] );
        foreach ( $settings as $key => $value ) {
            if ( array_key_exists( $key, $authored ) ) { continue; }
			if ( $key === '__globals__' ) {
				$global_changes = [];
				if ( wpae_native_roundtrip_project_plan_background_global( $authored, $settings, $roundtrip_context, $global_changes, (string) ( $node['elType'] ?? '' ) ) ) {
					unset( $settings['__globals__'] );
					foreach ( $global_changes as $change ) { $roundtrip_changes[] = array_merge( [ 'node_id' => (string) ( $node['id'] ?? '' ) ], $change ); }
					continue;
				}
			}
			$normalized_default = null; $control_mismatch = null; $control_changes = null;
			if ( ! array_key_exists( $key, $defaults ) || ! wpae_accepted_control_equal( $key, $value, $defaults[$key], (string) ( $node['widgetType'] ?? '' ), $normalized_default, $control_mismatch, $control_changes, $adapter_version ) ) { $mismatch = $control_mismatch ?: [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'extra_nondefault_control' ]; return null; }
			if ( $control_changes ) { foreach ( $control_changes as $change ) { $roundtrip_changes[] = array_merge( [ 'node_id' => (string) ( $node['id'] ?? '' ) ], $change ); } }
			unset( $settings[$key] );
        }
        foreach ( $authored as $key => $value ) {
			if ( array_key_exists( $key, $settings ) ) {
				$normalized_value = null; $control_mismatch = null; $control_changes = null;
				if ( ! wpae_accepted_control_equal( $key, $value, $settings[$key], (string) ( $node['widgetType'] ?? '' ), $normalized_value, $control_mismatch, $control_changes, $adapter_version ) ) { $mismatch = $control_mismatch ?: [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'authored_control_changed' ]; return null; }
				if ( ( $key === 'tabs' && ( $node['widgetType'] ?? '' ) === 'accordion' ) || ( $key === 'editor' && ( $node['widgetType'] ?? '' ) === 'text-editor' ) ) { $settings[$key] = $normalized_value; }
				if ( $control_changes ) { foreach ( $control_changes as $change ) { $roundtrip_changes[] = array_merge( [ 'node_id' => (string) ( $node['id'] ?? '' ) ], $change ); } }
				continue;
			}
			$normalized_default = null; $control_mismatch = null; $control_changes = null;
			if ( ! array_key_exists( $key, $defaults ) || ! wpae_accepted_control_equal( $key, $value, $defaults[$key], (string) ( $node['widgetType'] ?? '' ), $normalized_default, $control_mismatch, $control_changes, $adapter_version ) ) { $mismatch = $control_mismatch ?: [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'authored_control_missing' ]; return null; }
			if ( $key === 'tabs' && ( $node['widgetType'] ?? '' ) === 'accordion' ) { $settings[$key] = $normalized_default; }
			if ( $control_changes ) { foreach ( $control_changes as $change ) { $roundtrip_changes[] = array_merge( [ 'node_id' => (string) ( $node['id'] ?? '' ) ], $change ); } }
			$settings[$key] = $value;
		}
		$children = wpae_accepted_project_owned_model( (array) ( $node['elements'] ?? [] ), (array) ( $actual['elements'] ?? [] ), $defaults_provider, $mismatch, $roundtrip_context, $roundtrip_changes );
        if ( $children === null ) { return null; }
        $actual['settings'] = $settings; $actual['elements'] = $children; $projected[] = $actual;
    }
    return $projected;
}
function wpae_accepted_owned_matches( array $expected, array $current, array $roundtrip_context = [] ): bool {
	$changes = []; $mismatch = null;
	$projected = wpae_accepted_project_owned_model( $expected, $current, null, $mismatch, $roundtrip_context, $changes );
	return $projected !== null && hash_equals( wpae_accepted_owned_fingerprint( $expected ), wpae_accepted_owned_fingerprint( $projected ) );
}

/** Build runtime-only serialization evidence from the immutable accepted Plan and current Elementor kit. */
function wpae_accepted_native_roundtrip_context( array $contract ): array {
	$resolved = is_array( $contract['resolved_visual'] ?? null ) ? $contract['resolved_visual'] : (array) ( $contract['plan']['resolved_visual'] ?? [] );
	return [
		'version' => $contract['native_roundtrip']['version'] ?? null,
		'page_background' => (string) ( $resolved['values']['color.page_bg'] ?? '' ),
		'global_colors' => function_exists( 'wpae_native_roundtrip_elementor_global_colors' ) ? wpae_native_roundtrip_elementor_global_colors() : [],
	];
}

function wpae_accepted_contract_store(): array {
    $store = get_option( WPAE_ACCEPTED_CONTRACT_OPTION, [] );
    return is_array( $store ) ? $store : [];
}

function wpae_accepted_contract_get( array $operation ): array {
    $id = (string) ( $operation['accepted_contract_id'] ?? '' );
    if ( $id === '' ) { return [ 'ok' => false, 'reason' => 'historical_contract_unavailable' ]; }
    $stored = wpae_accepted_contract_store()[ $id ] ?? null;
    if ( ! is_array( $stored ) ) { return [ 'ok' => false, 'reason' => 'contract_missing' ]; }
    if ( (int) ( $stored['expires_at'] ?? 0 ) <= time() ) { return [ 'ok' => false, 'reason' => 'contract_expired' ]; }
    $payload = $stored['payload'] ?? [];
    $hash = hash( 'sha256', wp_json_encode( $payload ) );
    if ( ! hash_equals( (string) ( $operation['accepted_contract_hash'] ?? '' ), $hash ) || (string) ( $payload['operation_id'] ?? '' ) !== (string) ( $operation['operation_id'] ?? '' ) || (int) ( $payload['post_id'] ?? 0 ) !== (int) ( $operation['post_id'] ?? 0 ) || ( isset( $payload['operation_identity'] ) && (string) $payload['operation_identity'] !== (string) ( $operation['operation_identity'] ?? '' ) ) ) {
        return [ 'ok' => false, 'reason' => 'contract_binding_mismatch' ];
    }
    return [ 'ok' => true, 'contract' => $payload, 'hash' => $hash, 'expires_at' => $stored['expires_at'] ];
}

/** Compact evidence derived from the one frozen DesignPlan, never from compiler inference. */
function wpae_accepted_contract_composition_evidence_from_plan( array $brief, array $plan, array $generation = [] ): array {
	$decision = (array) ( $plan['composition_decision'] ?? [] );
	$request = is_array( $decision['request_selection'] ?? null ) ? $decision['request_selection'] : null;
	$visual = (array) ( $plan['resolved_visual'] ?? [] );
	$visual_policy = (array) ( $plan['visual_policy'] ?? [] );
	$surface = (array) ( $visual_policy['section_surface'] ?? [] );
	$surface_token = (string) ( $surface['token'] ?? '' );
	$surface_value = $surface_token === 'surface_override' ? (string) ( $surface['value'] ?? '' ) : (string) ( ( $visual['values'] ?? [] )[$surface_token] ?? '' );
	$accent = (array) ( ( $visual['palette'] ?? [] )['accent_reference'] ?? [] );
	$underlay = (string) ( ( $visual['contrast_context'] ?? [] )['color.section_bg_underlay'] ?? ( $visual['contrast_context'] ?? [] )['background_underlay'] ?? '' );
	$brief_hash = function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( $brief ) : '';
	$plan_hash = function_exists( 'wpae_design_plan_hash' ) ? wpae_design_plan_hash( $plan ) : '';
	return [
		'schema' => 'wpae-composition-evidence-v1',
		'status' => $request === null ? 'historical_request_unknown' : 'complete',
		'request' => $request ?? [ 'mode' => 'unknown', 'reason' => 'not_present_in_frozen_plan' ],
		'accepted' => [
			'record_id' => (string) ( $decision['record_id'] ?? '' ),
			'record_version' => (int) ( $decision['record_version'] ?? 0 ),
			'record_hash' => (string) ( $decision['record_hash'] ?? '' ),
			'source' => (string) ( $decision['source'] ?? '' ),
			'visual_profile' => (string) ( $decision['visual_profile'] ?? $visual['profile'] ?? '' ),
			'selection_reasons' => array_values( (array) ( $decision['selection_reasons'] ?? [] ) ),
			'selection_policy' => (string) ( $decision['selection_policy'] ?? '' ),
			'catalog_id' => (string) ( $decision['catalog_id'] ?? '' ),
			'catalog_identity' => (string) ( $decision['catalog_identity'] ?? '' ),
		],
		'hashes' => [ 'brief_sha256' => $brief_hash, 'plan_sha256' => $plan_hash ],
		'surface' => [
			'profile' => (string) ( $visual['profile'] ?? '' ),
			'section_surface' => $surface,
			'resolved_value' => $surface_value,
			'value_source' => (string) ( ( $visual['sources'] ?? [] )[$surface_token] ?? $surface['source'] ?? '' ),
			'accent_reference' => array_intersect_key( $accent, array_flip( [ 'value', 'source', 'confirmed', 'reference_id' ] ) ),
			'opaque_underlay' => $underlay,
		],
		'generation' => [
			'route' => sanitize_key( (string) ( $generation['route'] ?? '' ) ),
			'provider_calls' => max( 0, (int) ( $generation['provider_calls'] ?? 0 ) ),
			'provider_call_count_source' => (string) ( $generation['provider_call_count_source'] ?? 'design_pipeline_preflight' ),
		],
	];
}

/** Add operation and saved-native facts only after the contract is bound and verified. */
function wpae_accepted_contract_composition_evidence( array $operation ): array {
	$loaded = wpae_accepted_contract_get( $operation );
	if ( empty( $loaded['ok'] ) ) { return [ 'schema' => 'wpae-composition-evidence-v1', 'status' => 'unavailable', 'reason' => (string) ( $loaded['reason'] ?? 'contract_unavailable' ) ]; }
	$contract = (array) $loaded['contract'];
	$operation_roots = array_values( array_map( 'strval', (array) ( $operation['root_ids'] ?? [] ) ) );
	$contract_roots = array_values( array_map( 'strval', (array) ( $contract['owned_root_ids'] ?? [] ) ) );
	if ( $operation_roots !== $contract_roots ) { return [ 'schema' => 'wpae-composition-evidence-v1', 'status' => 'unavailable', 'reason' => 'operation_root_scope_mismatch' ]; }
	$evidence = is_array( $contract['composition_evidence'] ?? null ) ? $contract['composition_evidence'] : wpae_accepted_contract_composition_evidence_from_plan( (array) ( $contract['brief'] ?? [] ), (array) ( $contract['plan'] ?? [] ), (array) ( $contract['generation'] ?? [] ) );
	$plan_hash = function_exists( 'wpae_design_plan_hash' ) ? wpae_design_plan_hash( (array) ( $contract['plan'] ?? [] ) ) : '';
	$brief_hash = function_exists( 'wpae_brief_ir_hash' ) ? wpae_brief_ir_hash( (array) ( $contract['brief'] ?? [] ) ) : '';
	if ( ( (string) ( $operation['plan_hash'] ?? '' ) !== '' && ! hash_equals( (string) $operation['plan_hash'], $plan_hash ) ) || ( (string) ( $operation['brief_hash'] ?? '' ) !== '' && ! hash_equals( (string) $operation['brief_hash'], $brief_hash ) ) ) {
		return [ 'schema' => 'wpae-composition-evidence-v1', 'status' => 'unavailable', 'reason' => 'operation_plan_or_brief_hash_mismatch' ];
	}
	$evidence['operation'] = [
		'post_id' => (int) ( $operation['post_id'] ?? 0 ),
		'operation_id' => (string) ( $operation['operation_id'] ?? '' ),
		'operation_identity' => (string) ( $operation['operation_identity'] ?? '' ),
		'root_ids' => $operation_roots,
		'revision' => (int) ( $operation['revision'] ?? 0 ),
		'accepted_contract_id' => (string) ( $operation['accepted_contract_id'] ?? '' ),
		'accepted_contract_sha256' => (string) ( $loaded['hash'] ?? '' ),
		'saved_document_sha256' => (string) ( $operation['saved_hash'] ?? $contract['saved_hash'] ?? '' ),
	];
	$evidence['native'] = [ 'saved_native_fingerprint' => (string) ( $contract['owned_fingerprint'] ?? '' ), 'saved_root_ids' => $contract_roots ];
	$evidence['generation']['transaction_write_count'] = max( 0, (int) ( ( $contract['generation'] ?? [] )['transaction_write_count'] ?? 0 ) );
	$evidence['generation']['transaction_write_count_source'] = (string) ( ( $contract['generation'] ?? [] )['transaction_write_count_source'] ?? 'unavailable' );
	return $evidence;
}

/** Select known IR fields, never HTTP context/history, credentials or foreign roots. */
function wpae_accepted_contract_prepare( array $brief, array $plan, array $compiled, array $before_owned = [], string $parent_id = '', array $native_roundtrip = [], array $generation = [] ): array {
    $brief = array_intersect_key( $brief, array_flip( [ 'source_text', 'locale', 'parser_version', 'pricing_items', 'style_references', 'explicit_constraints', 'ambiguities', 'warnings', 'schema', 'version', 'canonical_create', 'archetype', 'intent', 'scope', 'content', 'content_items', 'groups', 'media_references', 'layout_constraints', 'style_constraints', 'policy', 'behavior', 'exact_text', 'constraints', 'required_widgets', 'copy_policy', 'approved_facts', 'intake', 'provenance', 'hash' ] ) );
    // Brief and Plan are already server-built typed data, not raw request context.
	$roundtrip = array_intersect_key( $native_roundtrip, array_flip( [ 'version', 'control_aware', 'repeater_order_and_count', 'repeater_ids', 'allowed_legacy_serialization', 'decisions' ] ) );
	$generation_evidence = array_intersect_key( $generation, array_flip( [ 'route', 'provider_calls', 'provider_call_count_source' ] ) );
	$prepared = [ 'schema' => WPAE_ACCEPTED_CONTRACT_SCHEMA, 'brief' => $brief, 'plan' => $plan, 'composition' => $plan['composition_decision'] ?? [], 'profile' => $plan['resolved_visual']['profile'] ?? '', 'resolved_visual' => $plan['resolved_visual'] ?? [], 'composition_evidence' => wpae_accepted_contract_composition_evidence_from_plan( $brief, $plan, $generation_evidence ), 'generation' => array_merge( $generation_evidence, [ 'transaction_write_count' => 0, 'transaction_write_count_source' => 'not_written_at_prepare' ] ), 'compiler_schema' => 'elementor-ir-v2/native-compiler-v1', 'compiled_signature' => wpae_llm_decision_signature( $compiled ), 'before_owned' => $before_owned, 'compiled_owned' => $compiled, 'parent_operation_id' => $parent_id ];
	if ( $roundtrip ) { $prepared['native_roundtrip'] = $roundtrip; }
    if ( strlen( wp_json_encode( $prepared ) ) + strlen( wp_json_encode( $compiled ) ) + 4096 > WPAE_ACCEPTED_CONTRACT_BYTES ) { return [ 'ok' => false, 'reason' => 'contract_too_large' ]; }
    return [ 'ok' => true, 'prepared' => $prepared ];
}

/** Sealed once, only after verified readback. Binding cannot be overwritten. */
function wpae_accepted_contract_seal( array $prepared, array $operation, array $saved_owned, array $generation = [] ): array {
    if ( ! hash_equals( (string) $prepared['compiled_signature'], wpae_llm_decision_signature( $saved_owned ) ) ) { return [ 'ok' => false, 'reason' => 'contract_readback_mismatch' ]; }
	$generation_write_count = max( 0, (int) ( $generation['write_count'] ?? 1 ) );
	$prepared['generation'] = array_merge( (array) ( $prepared['generation'] ?? [] ), [ 'transaction_write_count' => $generation_write_count, 'transaction_write_count_source' => $generation_write_count === 1 ? 'successful_transaction_and_readback' : 'explicit_generation_evidence' ] );
	$payload = array_merge( $prepared, [ 'operation_id' => $operation['operation_id'], 'operation_identity' => $operation['operation_identity'] ?? '', 'post_id' => $operation['post_id'], 'saved_revision' => $operation['revision'], 'saved_hash' => $operation['saved_hash'], 'owned_root_ids' => array_column( $saved_owned, 'id' ), 'after_owned' => $saved_owned, 'owned_fingerprint' => wpae_accepted_owned_fingerprint( $saved_owned ) ] );
    if ( strlen( wp_json_encode( $payload ) ) > WPAE_ACCEPTED_CONTRACT_BYTES ) { return [ 'ok' => false, 'reason' => 'contract_too_large' ]; }
    $hash = hash( 'sha256', wp_json_encode( $payload ) );
    $id = 'contract-' . substr( $hash, 0, 24 );
    $result = wpae_design_operation_with_lock( static function () use ( $id, $hash, $payload, $operation ): array {
        $operations = wpae_design_operation_store();
        foreach ( $operations as &$entry ) {
            if ( ( $entry['operation_id'] ?? '' ) !== $operation['operation_id'] ) { continue; }
            if ( ! empty( $entry['accepted_contract_id'] ) || (int) $entry['revision'] !== (int) $operation['revision'] ) { return [ 'ok' => false, 'reason' => 'contract_already_bound_or_stale' ]; }
            $store = array_filter( wpae_accepted_contract_store(), static fn( $c ): bool => is_array( $c ) && (int) ( $c['expires_at'] ?? 0 ) > time() );
            $store[$id] = [ 'payload' => $payload, 'expires_at' => time() + WPAE_ACCEPTED_CONTRACT_TTL ];
            update_option( WPAE_ACCEPTED_CONTRACT_OPTION, array_slice( $store, -WPAE_ACCEPTED_CONTRACT_LIMIT, null, true ), false );
            $entry['accepted_contract_id'] = $id;
            $entry['accepted_contract_hash'] = $hash;
            $entry['parent_operation_id'] = $payload['parent_operation_id'];
            wpae_design_operation_save( $operations );
            return [ 'ok' => true, 'operation' => $entry, 'contract_id' => $id ];
        }
        return [ 'ok' => false, 'reason' => 'operation_missing' ];
    } );
    return is_array( $result ) ? $result : [ 'ok' => false, 'reason' => 'contract_lock_busy' ];
}

/** Bind a narrowly accepted native serialization projection to the exact read-only snapshot. */
function wpae_accepted_native_roundtrip_proof( array $operation, array $loaded, array $data, array $owned, array $changes ): array {
	$proof = [
		'post_id' => (int) ( $operation['post_id'] ?? 0 ),
		'operation_id' => (string) ( $operation['operation_id'] ?? '' ),
		'operation_identity' => (string) ( $operation['operation_identity'] ?? '' ),
		'accepted_contract_id' => (string) ( $operation['accepted_contract_id'] ?? '' ),
		'accepted_contract_hash' => (string) ( $loaded['hash'] ?? '' ),
		'root_ids' => array_values( array_map( 'strval', (array) ( $operation['root_ids'] ?? [] ) ) ),
		'revision' => (int) ( $operation['revision'] ?? 0 ),
		'adapter_version' => WPAE_NATIVE_ROUNDTRIP_VERSION,
		'owned_payload_sha256' => hash( 'sha256', wp_json_encode( $owned ) ),
		'document_payload_sha256' => hash( 'sha256', wp_json_encode( $data ) ),
		'transformations' => $changes,
	];
	$proof['proof_sha256'] = hash( 'sha256', wp_json_encode( $proof ) );
	return $proof;
}

function wpae_accepted_native_roundtrip_proof_matches( $candidate, array $expected ): bool {
	if ( ! is_array( $candidate ) ) { return false; }
	$expected_json = wp_json_encode( $expected );
	$candidate_json = wp_json_encode( $candidate );
	return is_string( $expected_json ) && is_string( $candidate_json ) && hash_equals( hash( 'sha256', $expected_json ), hash( 'sha256', $candidate_json ) );
}

function wpae_accepted_contract_eligibility( array $operation, array $data ): array {
    if ( ! empty( $operation['typed_undone'] ) ) { return [ 'status' => 'already_undone' ]; }
    $loaded = wpae_accepted_contract_get( $operation );
    if ( empty( $loaded['ok'] ) ) { return [ 'status' => 'unavailable', 'reason' => $loaded['reason'] ]; }
	$contract = $loaded['contract'];
    $operation_roots = array_values( array_map( 'strval', (array) ( $operation['root_ids'] ?? [] ) ) );
    $contract_roots = array_values( array_map( 'strval', (array) ( $contract['owned_root_ids'] ?? [] ) ) );
    if ( $operation_roots !== $contract_roots ) { return [ 'status' => 'unavailable', 'reason' => 'operation_root_scope_mismatch' ]; }
    if ( count( (array) $contract['owned_root_ids'] ) !== 1 ) { return [ 'status' => 'ambiguous', 'reason' => 'single_owned_root_required' ]; }
    $owned = wpae_accepted_owned_roots( $data, $contract['owned_root_ids'] );
    if ( count( $owned ) !== 1 ) { return [ 'status' => 'changed_target', 'reason' => 'owned_root_missing_or_duplicated' ]; }
	$roundtrip_context = wpae_accepted_native_roundtrip_context( $contract );
	$roundtrip_version = $roundtrip_context['version'] ?? null;
	if ( isset( $contract['native_roundtrip'] ) && ! wpae_native_roundtrip_supported_version( $roundtrip_version ) ) { return [ 'status' => 'unavailable', 'reason' => 'unknown_native_roundtrip_version' ]; }
	$mismatch = [];
	$roundtrip_changes = [];
	$projected = wpae_accepted_project_owned_model( (array) $contract['after_owned'], $owned, null, $mismatch, $roundtrip_context, $roundtrip_changes );
    if ( $projected === null ) {
        return [ 'status' => 'changed_target', 'reason' => 'owned_fingerprint_changed', 'mismatch' => array_intersect_key( $mismatch, array_flip( [ 'node_id', 'control', 'reason' ] ) ) ];
    }
    if ( ! hash_equals( wpae_accepted_owned_fingerprint( (array) $contract['after_owned'] ), wpae_accepted_owned_fingerprint( $projected ) ) ) {
        return [ 'status' => 'changed_target', 'reason' => 'owned_fingerprint_changed', 'mismatch' => [ 'reason' => 'projected_fingerprint_mismatch' ] ];
    }
    foreach ( wpae_design_operation_store() as $candidate ) {
        if ( ( $candidate['parent_operation_id'] ?? '' ) === $operation['operation_id'] && empty( $candidate['typed_undone'] ) && in_array( $candidate['current_state'] ?? '', [ 'written', 'rendered', 'reviewed', 'completed' ], true ) ) { return [ 'status' => 'ambiguous', 'reason' => 'active_child_operation' ]; }
    }
	$result = [ 'status' => 'available', 'action' => empty( $contract['parent_operation_id'] ) ? 'undo_creation' : 'undo_repair', 'expires_at' => $loaded['expires_at'] ];
	if ( $roundtrip_changes ) { $result['native_roundtrip_recovery'] = wpae_accepted_native_roundtrip_proof( $operation, $loaded, $data, $owned, $roundtrip_changes ); }
	return $result;
}

function wpae_accepted_contract_descriptors( int $post_id, array $data ): array {
    $descriptors = [];
    foreach ( array_reverse( wpae_design_operation_store() ) as $operation ) {
        if ( (int) ( $operation['post_id'] ?? 0 ) !== $post_id || empty( $operation['root_ids'] ) ) { continue; }
        $eligibility = wpae_accepted_contract_eligibility( $operation, $data );
        $descriptor = array_merge( $eligibility, array_intersect_key( $operation, array_flip( [ 'post_id', 'operation_id', 'operation_identity', 'revision', 'root_ids', 'accepted_contract_id', 'accepted_contract_hash', 'brief_hash', 'plan_hash', 'saved_hash' ] ) ) );
		$descriptor['composition_evidence'] = wpae_accepted_contract_composition_evidence( $operation );
		$descriptors[] = $descriptor;
        if ( count( $descriptors ) === 20 ) { break; }
    }
    return $descriptors;
}

/** Attest the complete inverse, including unchanged foreign roots, from the ledger. */
function wpae_accepted_document_inverse( WP_REST_Request $request, array $current, array $next ): bool {
    if ( $current === [] ) { return false; }
    $id = (string) $request->get_param( 'operation_id' );
    if ( ! str_ends_with( $id, '-undo' ) ) { return false; }
    $operation = wpae_design_operation_find_by_id( substr( $id, 0, -5 ) );
    if ( ! $operation || (int) $operation['post_id'] !== (int) $request->get_param( 'post_id' ) || (int) $operation['revision'] !== (int) $request->get_param( 'accepted_undo_revision' ) || $operation['operation_identity'] !== $request->get_param( 'accepted_undo_identity' ) ) { return false; }
	$eligibility = wpae_accepted_contract_eligibility( $operation, $current );
	if ( $eligibility['status'] !== 'available' ) { return false; }
	$proof = $eligibility['native_roundtrip_recovery'] ?? null;
	$submitted_proof = $request->get_param( 'accepted_native_roundtrip_proof' );
	if ( ( $proof !== null && ! wpae_accepted_native_roundtrip_proof_matches( $submitted_proof, $proof ) ) || ( $proof === null && $submitted_proof !== null ) ) { return false; }
    $contract = wpae_accepted_contract_get( $operation )['contract'];
    $inverse = [];
    foreach ( $current as $root ) {
        if ( in_array( $root['id'] ?? '', $contract['owned_root_ids'], true ) ) { $inverse = array_merge( $inverse, $contract['before_owned'] ); }
        else { $inverse[] = $root; }
    }
    return wpae_elementor_data_matches( $inverse, $next );
}

/** Empty documents are valid only as the verified inverse of a current owned creation. */
function wpae_accepted_empty_inverse( WP_REST_Request $request, array $current, array $next ): bool {
    return $next === [] && wpae_accepted_document_inverse( $request, $current, $next );
}

/** Owned inverse change uses fresh document and existing preview/transaction authority. */
function wpae_accepted_contract_undo( WP_REST_Request $request ): WP_REST_Response {
    $id = sanitize_key( (string) $request->get_param( 'operation_id' ) );
    $post_id = absint( $request->get_param( 'post_id' ) );
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_forbidden' ], 403 ); }
    $token = wpae_design_operation_acquire_lock();
    if ( ! $token ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_busy' ], 409 ); }
    try {
        $operation = wpae_design_operation_find_by_id( $id );
        if ( ! $operation || (int) $operation['post_id'] !== $post_id || (int) $request->get_param( 'revision' ) !== (int) $operation['revision'] || (string) $request->get_param( 'operation_identity' ) !== (string) $operation['operation_identity'] || (string) $request->get_param( 'accepted_contract_id' ) !== (string) ( $operation['accepted_contract_id'] ?? '' ) || array_values( array_map( 'strval', (array) $request->get_param( 'accepted_root_ids' ) ) ) !== array_values( array_map( 'strval', (array) ( $operation['root_ids'] ?? [] ) ) ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_scope_or_revision' ], 409 ); }
        $data = wpae_get_elementor_data_for_post( $post_id );
        if ( ! is_array( $data ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_readback_unavailable' ], 409 ); }
        $eligibility = wpae_accepted_contract_eligibility( $operation, $data );
		if ( $eligibility['status'] !== 'available' ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_' . $eligibility['status'], 'eligibility' => $eligibility ], 409 ); }
		$roundtrip_proof = $eligibility['native_roundtrip_recovery'] ?? null;
		$submitted_proof = $request->get_param( 'accepted_native_roundtrip_proof' );
		if ( ( $roundtrip_proof !== null && ! wpae_accepted_native_roundtrip_proof_matches( $submitted_proof, $roundtrip_proof ) ) || ( $roundtrip_proof === null && $submitted_proof !== null ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_native_roundtrip_proof_mismatch', 'eligibility' => $eligibility, 'write_count' => 0 ], 409 ); }
        $contract = wpae_accepted_contract_get( $operation )['contract'];
        $next = [];
        foreach ( $data as $root ) {
            if ( in_array( $root['id'] ?? '', $contract['owned_root_ids'], true ) ) { $next = array_merge( $next, $contract['before_owned'] ); }
            else { $next[] = $root; }
        }
        $update = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/update' );
		foreach ( [ 'post_id' => $post_id, 'elementor_data' => $next, 'expected_before_elementor_data' => $data, 'template' => get_post_meta( $post_id, '_wp_page_template', true ), 'operation_id' => $id . '-undo', 'operation_root_ids' => $contract['owned_root_ids'] ] as $key => $value ) { $update->set_param( $key, $value ); }
		if ( $roundtrip_proof !== null ) { $update->set_param( 'accepted_native_roundtrip_proof', $roundtrip_proof ); }
        $update->set_param( 'accepted_undo_revision', $operation['revision'] );
        $update->set_param( 'accepted_undo_identity', $operation['operation_identity'] );
        $update->set_param( 'dry_run', true );
        $preview = wpae_elementor_update( $update );
        if ( is_wp_error( $preview ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => $preview->get_error_code(), 'write_count' => 0 ], 409 ); }
        if ( empty( $preview->get_data()['ok'] ) ) { return $preview; }
        $update->set_param( 'dry_run', false );
        $result = wpae_elementor_update( $update );
        if ( is_wp_error( $result ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => $result->get_error_code(), 'write_count' => 0 ], 409 ); }
        if ( empty( $result->get_data()['ok'] ) ) { return $result; }
        $readback = wpae_get_elementor_data_for_post( $post_id );
        if ( ! is_array( $readback ) || ! wpae_elementor_data_matches( $next, $readback ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_readback_failed', 'write_count' => 1 ], 409 ); }
        $operations = wpae_design_operation_store();
        foreach ( $operations as &$entry ) { if ( ( $entry['operation_id'] ?? '' ) === $id ) { $entry['typed_undone'] = true; $entry['revision']++; $entry['rollback_event'] = $eligibility['action']; } }
        unset( $entry );
        wpae_design_operation_save( $operations );
        wpae_accepted_save_guard_clear( $post_id, $id );
        return new WP_REST_Response( [ 'ok' => true, 'operation_id' => $id, 'undo_action' => $eligibility['action'], 'write_count' => 1, 'root_ids' => array_column( $readback, 'id' ) ], 200 );
    } finally { wpae_design_operation_release_lock( $token ); }
}

function wpae_accepted_save_guard_clear( int $post_id, string $operation_id ): void {
    $guards = get_option( 'wp_ai_executor_typed_save_guards', [] );
    if ( ( $guards[$post_id]['operation_id'] ?? '' ) === $operation_id ) { unset( $guards[$post_id] ); update_option( 'wp_ai_executor_typed_save_guards', $guards, false ); }
}

function wpae_accepted_save_guard_set( array $operation ): void {
    $guards = get_option( 'wp_ai_executor_typed_save_guards', [] );
    $guards = is_array( $guards ) ? $guards : [];
    $guards[$operation['post_id']] = [ 'operation_id' => $operation['operation_id'], 'expires_at' => time() + WPAE_ACCEPTED_CONTRACT_TTL ];
    update_option( 'wp_ai_executor_typed_save_guards', array_slice( $guards, -20, null, true ), false );
}

/** Documented Elementor 4.1.1 filter runs before settings or element writes. */
function wpae_accepted_elementor_save_guard( array $data, $document ): array {
    if ( ! isset( $data['elements'] ) || ! is_array( $data['elements'] ) ) { return $data; }
    $post_id = (int) $document->get_main_id();
    $guard = get_option( 'wp_ai_executor_typed_save_guards', [] )[$post_id] ?? null;
    if ( ! is_array( $guard ) ) { return $data; }
    if ( (int) $guard['expires_at'] <= time() ) { throw new RuntimeException( 'Typed save blocked: pending contract expired; server resync required.' ); }
    $operation = wpae_design_operation_find_by_id( $guard['operation_id'] );
    $loaded = $operation ? wpae_accepted_contract_get( $operation ) : [ 'ok' => false ];
    if ( empty( $loaded['ok'] ) ) { throw new RuntimeException( 'Typed save blocked: accepted contract unavailable.' ); }
    $contract = $loaded['contract'];
	$roundtrip_context = wpae_accepted_native_roundtrip_context( $contract );
    // Native Save payload is already authored; generation defaults must not alter it.
    $document_model = wpae_get_elementor_data_for_post( $post_id );
	if ( ! is_array( $document_model ) || ! wpae_accepted_owned_matches( $document_model, $data['elements'], $roundtrip_context ) ) { throw new RuntimeException( 'Typed save blocked: whole native document differs from fresh server data. Local edits preserved.' ); }
	$owned = wpae_accepted_owned_roots( $data['elements'], $contract['owned_root_ids'] );
	if ( ! wpae_accepted_owned_matches( $contract['after_owned'], $owned, $roundtrip_context ) ) { throw new RuntimeException( 'Typed save blocked: editor owned tree differs from accepted server decisions. Local edits preserved; resync required.' ); }
    return $data;
}

function wpae_accepted_elementor_after_save( $document ): void {
    $post_id = (int) $document->get_main_id();
    $guard = get_option( 'wp_ai_executor_typed_save_guards', [] )[$post_id] ?? null;
    if ( is_array( $guard ) ) {
        $operation = wpae_design_operation_find_by_id( $guard['operation_id'] );
        $saved = wpae_get_elementor_data_for_post( $post_id );
        if ( ! $operation || ! is_array( $saved ) || wpae_accepted_contract_eligibility( $operation, $saved )['status'] !== 'available' ) { throw new RuntimeException( 'Typed save readback differs from accepted decisions; guard remains pending.' ); }
        wpae_design_operation_update( $operation['operation_id'], [ 'saved_hash' => hash( 'sha256', wp_json_encode( $saved ) ), 'target_fingerprint' => wpae_rollback_post_fingerprint( $post_id ) ] );
        wpae_accepted_save_guard_clear( $post_id, $guard['operation_id'] );
    }
}
if ( function_exists( 'add_filter' ) ) { add_filter( 'elementor/document/save/data', 'wpae_accepted_elementor_save_guard', 10, 2 ); }
if ( function_exists( 'add_action' ) ) { add_action( 'elementor/document/after_save', 'wpae_accepted_elementor_after_save', 10, 1 ); }

/** First slice: measured/explained excessive spacing only, no topology/media/copy changes. */
function wpae_accepted_layout_delta( array $plan, array $report, string $correction ): array {
    if ( $correction !== 'compact_spacing' ) { return [ 'ok' => false, 'reason' => 'unsupported_layout_correction' ]; }
    $supported = false;
    foreach ( (array) ( $report['findings'] ?? [] ) as $finding ) {
        if ( preg_match( '/spacing|layout|отступ|ритм/iu', (string) ( $finding['category'] ?? '' ) ) && preg_match( '/excess|too\s+(?:large|wide|much)|sparse|empty|reduce|слишком|уменьш|пуст/iu', (string) ( $finding['message'] ?? '' ) . ' ' . (string) ( $finding['fix'] ?? '' ) ) ) { $supported = true; }
    }
    if ( ! $supported ) { return [ 'ok' => false, 'reason' => 'finding_does_not_justify_delta' ]; }
    // New accepted policies repair a specific relationship; historical contracts keep their old delta.
    if ( ! empty( $plan['visual_policy'] ) ) {
        $changes = [];
        foreach ( $plan['visual_policy']['spacing']['section'] as $device => $old ) {
            if ( ! preg_match( '/^([0-9.]+)(rem|px)$/', $old, $m ) ) { continue; }
            $new = max( $m[2] === 'rem' ? 1 : 16, (float) $m[1] - ( $m[2] === 'rem' ? 0.5 : 8 ) ) . $m[2];
            if ( $old === $new ) { continue; }
            $plan['visual_policy']['spacing']['section'][$device] = $new;
            $changes['visual_policy.spacing.section.' . $device] = [ 'before' => $old, 'after' => $new ];
        }
        if ( ! $changes ) { return [ 'ok' => false, 'reason' => 'no_supported_spacing_values' ]; }
        return [ 'ok' => true, 'plan' => $plan, 'delta' => $changes ];
    }
    $changes = [];
    foreach ( [ 'space.section', 'space.section_tablet', 'space.section_mobile', 'space.component', 'space.component_tablet', 'space.component_mobile' ] as $key ) {
        $value = $plan['resolved_visual']['values'][$key] ?? null;
        if ( ! is_string( $value ) || ! preg_match( '/^([0-9.]+)(rem|px)$/', $value, $m ) ) { continue; }
        $new = round( max( $m[2] === 'rem' ? 0.5 : 8, (float) $m[1] * 0.8 ), 3 ) . $m[2];
        if ( $new === $value ) { continue; }
        $plan['resolved_visual']['values'][$key] = $new;
        $plan['resolved_visual']['sources'][$key] = 'operation_bound_layout_delta';
        $changes[$key] = [ 'before' => $value, 'after' => $new ];
    }
    if ( ! $changes ) { return [ 'ok' => false, 'reason' => 'no_supported_spacing_values' ]; }
    $plan['resolved_visual']['adjustments'][] = [ 'kind' => 'scoped_repair', 'correction' => $correction, 'changes' => $changes ];
    return [ 'ok' => true, 'plan' => $plan, 'delta' => $changes ];
}

function wpae_accepted_lifecycle_scope_matches( array $operation, array $context ): bool {
    $operation_roots = array_values( array_map( 'strval', (array) ( $operation['root_ids'] ?? [] ) ) );
    $requested_roots = array_values( array_map( 'strval', (array) ( $context['accepted_root_ids'] ?? [] ) ) );
    return (string) ( $context['accepted_contract_id'] ?? '' ) !== ''
        && hash_equals( (string) ( $operation['accepted_contract_id'] ?? '' ), (string) $context['accepted_contract_id'] )
        && $operation_roots !== []
        && $requested_roots === $operation_roots;
}

function wpae_accepted_lifecycle_request( array $context ) {
    $post_id = absint( $context['post_id'] ?? 0 );
    $operation = wpae_design_operation_find_by_id( sanitize_key( (string) ( $context['accepted_operation_id'] ?? '' ) ) );
    if ( ( $context['lifecycle_action'] ?? '' ) === 'describe_operation' ) {
        // Read-only recovery of a stale UI acknowledgement; mutations still require revision.
        if ( ! current_user_can( 'edit_post', $post_id ) || ! $operation || (int) $operation['post_id'] !== $post_id || (string) ( $context['accepted_identity'] ?? '' ) !== (string) $operation['operation_identity'] || ! wpae_accepted_lifecycle_scope_matches( $operation, $context ) ) { return new WP_Error( 'wpae_typed_scope_conflict', 'Точная операция и её root/contract scope не подтверждены.', [ 'status' => 409, 'write_count' => 0 ] ); }
        $data = wpae_get_elementor_data_for_post( $post_id );
        if ( ! is_array( $data ) ) { return new WP_Error( 'wpae_typed_readback_unavailable', 'Текущая native-модель недоступна.', [ 'status' => 409, 'write_count' => 0 ] ); }
        $eligibility = wpae_accepted_contract_eligibility( $operation, $data );
        // Describe is strictly read-only: return the current server descriptor even
        // when the target is ineligible, so the UI can report the actual revision
        // and reason. The client must not treat an ineligible descriptor as approval.
        $descriptor = array_intersect_key( $operation, array_flip( [ 'post_id', 'operation_id', 'operation_identity', 'revision', 'root_ids', 'accepted_contract_id', 'accepted_contract_hash', 'brief_hash', 'plan_hash', 'saved_hash' ] ) );
        $descriptor['eligibility'] = $eligibility;
		$descriptor['composition_evidence'] = wpae_accepted_contract_composition_evidence( $operation );
        return new WP_REST_Response( [ 'ok' => true, 'write_count' => 0, 'operation' => $descriptor ], 200 );
    }
    if ( ! current_user_can( 'edit_post', $post_id ) || ! $operation || (int) $operation['post_id'] !== $post_id || (int) ( $context['accepted_revision'] ?? 0 ) !== (int) $operation['revision'] || (string) ( $context['accepted_identity'] ?? '' ) !== (string) $operation['operation_identity'] || ! wpae_accepted_lifecycle_scope_matches( $operation, $context ) ) { return new WP_Error( 'wpae_typed_scope_conflict', 'Точная операция/revision и её root/contract scope не подтверждены.', [ 'status' => 409, 'write_count' => 0 ] ); }
	$loaded = wpae_accepted_contract_get( $operation );
	if ( empty( $loaded['ok'] ) ) { return new WP_Error( 'wpae_typed_contract_unavailable', $loaded['reason'], [ 'status' => 409, 'write_count' => 0 ] ); }
	$data = wpae_get_elementor_data_for_post( $post_id );
	if ( ! is_array( $data ) ) { return new WP_Error( 'wpae_typed_readback_unavailable', 'Текущая native-модель недоступна.', [ 'status' => 409, 'write_count' => 0 ] ); }
	$eligibility = wpae_accepted_contract_eligibility( $operation, $data );
	$contract = $loaded['contract'];
	$owned = wpae_accepted_owned_roots( $data, $contract['owned_root_ids'] );
	$action = $context['lifecycle_action'] ?? '';
	if ( $action === 'check_document_model' ) {
		// Read-only full-document comparison remains available on an ineligible target;
		// exact post/revision/identity/contract/root scope was already checked above.
		$model = $context['editor_document_model'] ?? null;
		$mismatch = null;
		$projection_changes = [];
		$roundtrip_context = wpae_accepted_native_roundtrip_context( $contract );
		$projected = is_array( $model ) ? wpae_accepted_project_owned_model( $data, $model, null, $mismatch, $roundtrip_context, $projection_changes ) : null;
		$matches = $projected !== null && hash_equals( wpae_accepted_owned_fingerprint( $data ), wpae_accepted_owned_fingerprint( $projected ) );
		$required_proof = $eligibility['native_roundtrip_recovery'] ?? null;
		$submitted_proof = $context['accepted_native_roundtrip_proof'] ?? null;
		$proof_matches = ( $required_proof === null && $submitted_proof === null ) || ( $required_proof !== null && wpae_accepted_native_roundtrip_proof_matches( $submitted_proof, $required_proof ) );
		$matches = $matches && $proof_matches;
		if ( ! $proof_matches && $mismatch === null ) { $mismatch = [ 'reason' => 'native_roundtrip_proof_mismatch' ]; }
		return new WP_REST_Response( [ 'mismatch' => $mismatch, 'eligibility' => array_intersect_key( $eligibility, array_flip( [ 'status', 'reason', 'mismatch' ] ) ), 'native_roundtrip_recovery' => $required_proof, 'ok' => $matches, 'code' => $matches ? 'typed_document_model_matches' : 'typed_document_model_mismatch', 'operation_id' => $operation['operation_id'], 'root_ids' => array_column( $data, 'id' ), 'write_count' => 0 ], $matches ? 200 : 409 );
	}
	if ( $action === 'check_model' ) {
		// Compare the browser's owned root to the frozen accepted contract even when
		// saved read-back is ineligible; diagnostics never authorize Undo or Save.
		$model = $context['editor_owned_model'] ?? null;
		$mismatch = null;
		$projected = is_array( $model ) ? wpae_accepted_project_owned_model( $contract['after_owned'], $model, null, $mismatch, wpae_accepted_native_roundtrip_context( $contract ) ) : null;
		$matches = $projected !== null && hash_equals( wpae_accepted_owned_fingerprint( $contract['after_owned'] ), wpae_accepted_owned_fingerprint( $projected ) );
		return new WP_REST_Response( [ 'ok' => $matches, 'code' => $matches ? 'typed_model_matches' : 'typed_editor_model_mismatch', 'operation_id' => $operation['operation_id'], 'contract_hash' => $loaded['hash'], 'eligibility' => array_intersect_key( $eligibility, array_flip( [ 'status', 'reason', 'mismatch' ] ) ), 'mismatch' => $mismatch, 'write_count' => 0 ], $matches ? 200 : 409 );
	}
    if ( $eligibility['status'] !== 'available' ) { return new WP_Error( 'wpae_typed_target_changed', 'Owned-модель операции недоступна для lifecycle-действия.', [ 'status' => 409, 'write_count' => 0, 'eligibility' => $eligibility ] ); }
	if ( $action === 'resync' ) {
        $model = $context['editor_owned_model'] ?? null;
        if ( ! is_array( $model ) ) { return new WP_Error( 'wpae_typed_resync_model_missing', 'Нужно текущее owned model.', [ 'status' => 409 ] ); }
        $current_model = $model;
		$roundtrip_context = wpae_accepted_native_roundtrip_context( $contract );
		$matches_before = wpae_accepted_owned_matches( $contract['before_owned'], $current_model, $roundtrip_context );
		$matches_after = wpae_accepted_owned_matches( $contract['after_owned'], $current_model, $roundtrip_context );
        if ( ! $matches_before && ! $matches_after ) { return new WP_Error( 'wpae_typed_resync_local_conflict', 'Owned local changes не перезаписаны.', [ 'status' => 409 ] ); }
        return new WP_REST_Response( [ 'ok' => true, 'editor_sync' => [ 'elements' => $owned, 'mode' => $model ? 'replace' : 'insert', 'replace_element_id' => $contract['owned_root_ids'][0], 'operation_owned_root_ids' => $contract['owned_root_ids'], 'after_top_level_ids' => array_column( $data, 'id' ) ], 'write_count' => 0 ], 200 );
    }
    if ( $action !== 'repair' ) { return new WP_Error( 'wpae_typed_action_unsupported', 'Неизвестное lifecycle действие.', [ 'status' => 422, 'write_count' => 0 ] ); }
    $report = function_exists( 'wpae_get_vision_report' ) ? wpae_get_vision_report( sanitize_text_field( (string) ( $context['accepted_vision_report_id'] ?? '' ) ) ) : null;
    if ( ! is_array( $report ) || ( $report['source'] ?? '' ) !== 'provider' || ! wpae_design_operation_report_scope_matches( $operation, $report, $post_id, (int) ( $report['render_context']['operation_revision'] ?? 0 ), $contract['owned_root_ids'] ) || (int) ( $report['render_context']['operation_revision'] ?? 0 ) < (int) $contract['saved_revision'] || (int) ( $report['render_context']['operation_revision'] ?? 0 ) > (int) $operation['revision'] ) { return new WP_Error( 'wpae_typed_report_unverified', 'Report не привязан к точной операции и saved result.', [ 'status' => 409, 'write_count' => 0 ] ); }
    // Also compare current document binding: a stale review is not reused after save.
    if ( ! hash_equals( (string) $operation['saved_hash'], hash( 'sha256', wp_json_encode( $data ) ) ) || ! hash_equals( (string) $operation['target_fingerprint'], wpae_rollback_post_fingerprint( $post_id ) ) ) { return new WP_Error( 'wpae_typed_review_stale', 'После review сохранённый результат изменился; нужен новый report.', [ 'status' => 409, 'write_count' => 0 ] ); }
    $attempts = 0;
    foreach ( wpae_design_operation_store() as $candidate ) {
        if ( ( $candidate['repair_parent_id'] ?? $candidate['parent_operation_id'] ?? '' ) === $operation['operation_id'] ) { $attempts++; }
    }
    if ( $attempts >= 2 ) { return new WP_Error( 'wpae_typed_repair_limit', 'Лимит двух repair attempts достигнут.', [ 'status' => 409, 'write_count' => 0 ] ); }
    $depth = 0; $ancestor = $operation;
    while ( ! empty( $ancestor['parent_operation_id'] ) ) { $depth++; $ancestor = wpae_design_operation_find_by_id( $ancestor['parent_operation_id'] ); if ( ! $ancestor || $depth >= 2 ) { return new WP_Error( 'wpae_typed_repair_limit', 'Лимит repair lineage достигнут.', [ 'status' => 409, 'write_count' => 0 ] ); } }
    $delta = wpae_accepted_layout_delta( $contract['plan'], $report, (string) ( $context['layout_correction'] ?? '' ) );
    if ( empty( $delta['ok'] ) ) { return new WP_Error( 'wpae_typed_repair_advisory', $delta['reason'], [ 'status' => 422, 'write_count' => 0 ] ); }
    $brief = $contract['brief']; $plan = $delta['plan'];
    $validation = wpae_design_plan_validate( $plan, $brief );
    if ( empty( $validation['ok'] ) ) { return new WP_Error( 'wpae_typed_plan_invalid', 'Delta Plan не прошёл validation.', [ 'status' => 422, 'write_count' => 0 ] ); }
    $ir = wpae_elementor_ir_from_design_plan( $plan, $brief );
    $compiled = wpae_native_elementor_compile( $ir, $brief, [], [ 'resolved_visual' => $plan['resolved_visual'] ] );
    if ( empty( $compiled['ok'] ) || count( $compiled['elementor_data'] ?? [] ) !== 1 ) { return new WP_Error( 'wpae_typed_compile_failed', 'Repair compiler отказал.', [ 'status' => 422, 'write_count' => 0 ] ); }
	$prepared = wpae_accepted_contract_prepare( $brief, $plan, $compiled['elementor_data'], $owned, $operation['operation_id'], (array) ( $compiled['report']['native_roundtrip'] ?? [] ) );
    if ( empty( $prepared['ok'] ) ) { return new WP_Error( 'wpae_typed_contract_size', $prepared['reason'], [ 'status' => 422, 'write_count' => 0 ] ); }
    $identity = sanitize_text_field( (string) ( $context['operation_identity'] ?? '' ) );
    if ( $identity === '' ) { return new WP_Error( 'wpae_typed_identity_required', 'Нужна новая identity repair.', [ 'status' => 400, 'write_count' => 0 ] ); }
    $child = wpae_design_operation_create( [ 'operation_identity' => $identity, 'operation_type' => 'design_repair', 'post_id' => $post_id, 'brief_hash' => wpae_brief_ir_hash( $brief ), 'plan_hash' => wpae_design_plan_hash( $plan ), 'current_state' => 'generated', 'idempotency_key' => wpae_design_operation_idempotency_key( $post_id, $loaded['hash'], $operation['operation_id'], 'design_repair', $identity ) ] );
    if ( ! empty( $child['reconciled'] ) ) { return new WP_Error( 'wpae_typed_repair_pending', 'Repair уже существует; повторной записи нет.', [ 'status' => 409, 'write_count' => 0 ] ); }
    wpae_design_operation_with_lock( static function () use ( $child, $operation ): void {
        $entries = wpae_design_operation_store();
        foreach ( $entries as &$entry ) { if ( $entry['operation_id'] === $child['operation_id'] ) { $entry['repair_parent_id'] = $operation['operation_id']; } }
        unset( $entry ); wpae_design_operation_save( $entries );
    } );
    wpae_design_operation_update( $child['operation_id'], [ 'current_state' => 'normalized' ] );
    wpae_design_operation_update( $child['operation_id'], [ 'current_state' => 'validated' ] );
    $execution = wpae_llm_execute_action( [ 'action' => 'insert_elements', 'post_id' => $post_id, 'position' => 'replace', 'elements' => $compiled['elementor_data'] ], $post_id, $plan['archetype'], -1, $brief['source_text'], true, [ 'frozen_decisions' => true, 'accepted_signature' => $prepared['prepared']['compiled_signature'], 'deterministic_ids' => true, 'operation_id' => $child['operation_id'], 'operation_identity' => $identity, 'replace_root_ids' => $contract['owned_root_ids'], 'typed_parent_operation_id' => $operation['operation_id'] ] );
    $saved = ! empty( $execution['ok'] ) ? wpae_get_elementor_data_for_post( $post_id ) : null;
    $saved_owned = is_array( $saved ) ? wpae_accepted_owned_roots( $saved, $contract['owned_root_ids'] ) : [];
    $matches = $saved_owned && hash_equals( $prepared['prepared']['compiled_signature'], wpae_llm_decision_signature( $saved_owned ) );
    $child = wpae_design_operation_update( $child['operation_id'], [ 'current_state' => ! empty( $execution['ok'] ) && $matches ? 'written' : 'failed', 'root_ids' => $contract['owned_root_ids'], 'saved_hash' => is_array( $saved ) ? hash( 'sha256', wp_json_encode( $saved ) ) : '', 'target_fingerprint' => wpae_rollback_post_fingerprint( $post_id ), 'rollback_snapshot_id' => $execution['rollback_snapshot_id'] ?? '' ] );
    if ( empty( $execution['ok'] ) || ! $matches ) { return new WP_Error( 'wpae_typed_repair_failed', 'Repair transaction/readback отказал; append/fallback не запускались.', [ 'status' => 409, 'write_count' => ! empty( $execution['ok'] ) ? 1 : 0, 'write' => $execution ] ); }
    $sealed = wpae_accepted_contract_seal( $prepared['prepared'], $child, $saved_owned );
    if ( empty( $sealed['ok'] ) ) { return new WP_Error( 'wpae_typed_repair_seal_failed', $sealed['reason'], [ 'status' => 409, 'write_count' => 1 ] ); }
    $child = $sealed['operation']; wpae_accepted_save_guard_set( $child );
    wpae_design_operation_update( $operation['operation_id'], [ 'current_state' => 'revised' ] );
    return new WP_REST_Response( [ 'ok' => true, 'message' => 'Scoped spacing repair сохранён; требуется проверка editor/save/readback.', 'operation_id' => $child['operation_id'], 'write' => $execution, 'diagnostics' => [ 'operation_ledger' => $child, 'write_count' => 1, 'provider_calls' => 0, 'accepted_delta' => $delta['delta'], 'accepted_contract_hash' => $child['accepted_contract_hash'] ] ], 200 );
}
