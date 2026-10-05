<?php
/** Bounded server-owned typed decisions. No browser state is authoritative. */
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__ ) . '/vision/report-store.php';
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
    return $instance ? (array) $instance->get_settings() : [];
}
function wpae_accepted_control_equal( string $key, $a, $b ): bool {
    return hash_equals( wpae_llm_decision_signature( [ [ 'settings' => [ $key => $a ] ] ] ), wpae_llm_decision_signature( [ [ 'settings' => [ $key => $b ] ] ] ) );
}
function wpae_accepted_project_owned_model( array $expected, array $current, ?callable $defaults_provider = null, ?array &$mismatch = null ): ?array {
    if ( count( $expected ) !== count( $current ) ) { $mismatch = [ 'reason' => 'node_count' ]; return null; }
    $projected = [];
    foreach ( $expected as $index => $node ) {
        $actual = $current[$index] ?? [];
        foreach ( [ 'id', 'elType', 'widgetType' ] as $key ) { if ( ( $node[$key] ?? '' ) !== ( $actual[$key] ?? '' ) ) { $mismatch = [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'node_identity' ]; return null; } }
        $defaults = $defaults_provider ? $defaults_provider( $node ) : wpae_accepted_native_defaults( $node );
        $authored = (array) ( $node['settings'] ?? [] ); $settings = (array) ( $actual['settings'] ?? [] );
        foreach ( $settings as $key => $value ) {
            if ( array_key_exists( $key, $authored ) ) { continue; }
            if ( ! array_key_exists( $key, $defaults ) || ! wpae_accepted_control_equal( $key, $value, $defaults[$key] ) ) { $mismatch = [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'extra_nondefault_control' ]; return null; }
            unset( $settings[$key] );
        }
        foreach ( $authored as $key => $value ) {
            if ( array_key_exists( $key, $settings ) ) { if ( ! wpae_accepted_control_equal( $key, $value, $settings[$key] ) ) { $mismatch = [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'authored_control_changed' ]; return null; } continue; }
            if ( ! array_key_exists( $key, $defaults ) || ! wpae_accepted_control_equal( $key, $value, $defaults[$key] ) ) { $mismatch = [ 'node_id' => $node['id'] ?? '', 'control' => $key, 'reason' => 'authored_control_missing' ]; return null; }
            $settings[$key] = $value;
        }
        $children = wpae_accepted_project_owned_model( (array) ( $node['elements'] ?? [] ), (array) ( $actual['elements'] ?? [] ), $defaults_provider, $mismatch );
        if ( $children === null ) { return null; }
        $actual['settings'] = $settings; $actual['elements'] = $children; $projected[] = $actual;
    }
    return $projected;
}
function wpae_accepted_owned_matches( array $expected, array $current ): bool {
    $projected = wpae_accepted_project_owned_model( $expected, $current );
    return $projected !== null && hash_equals( wpae_accepted_owned_fingerprint( $expected ), wpae_accepted_owned_fingerprint( $projected ) );
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
    if ( ! hash_equals( (string) ( $operation['accepted_contract_hash'] ?? '' ), $hash ) || (string) ( $payload['operation_id'] ?? '' ) !== (string) ( $operation['operation_id'] ?? '' ) || (int) ( $payload['post_id'] ?? 0 ) !== (int) ( $operation['post_id'] ?? 0 ) ) {
        return [ 'ok' => false, 'reason' => 'contract_binding_mismatch' ];
    }
    return [ 'ok' => true, 'contract' => $payload, 'hash' => $hash, 'expires_at' => $stored['expires_at'] ];
}

/** Select known IR fields, never HTTP context/history, credentials or foreign roots. */
function wpae_accepted_contract_prepare( array $brief, array $plan, array $compiled, array $before_owned = [], string $parent_id = '' ): array {
    $brief = array_intersect_key( $brief, array_flip( [ 'source_text', 'locale', 'parser_version', 'pricing_items', 'style_references', 'explicit_constraints', 'ambiguities', 'warnings', 'schema', 'version', 'canonical_create', 'archetype', 'intent', 'scope', 'content', 'content_items', 'groups', 'media_references', 'layout_constraints', 'style_constraints', 'policy', 'behavior', 'exact_text', 'constraints', 'required_widgets', 'copy_policy', 'provenance', 'hash' ] ) );
    // Brief and Plan are already server-built typed data, not raw request context.
    $prepared = [ 'schema' => WPAE_ACCEPTED_CONTRACT_SCHEMA, 'brief' => $brief, 'plan' => $plan, 'composition' => $plan['composition_decision'] ?? [], 'profile' => $plan['resolved_visual']['profile'] ?? '', 'resolved_visual' => $plan['resolved_visual'] ?? [], 'compiler_schema' => 'elementor-ir-v2/native-compiler-v1', 'compiled_signature' => wpae_llm_decision_signature( $compiled ), 'before_owned' => $before_owned, 'compiled_owned' => $compiled, 'parent_operation_id' => $parent_id ];
    if ( strlen( wp_json_encode( $prepared ) ) + strlen( wp_json_encode( $compiled ) ) + 4096 > WPAE_ACCEPTED_CONTRACT_BYTES ) { return [ 'ok' => false, 'reason' => 'contract_too_large' ]; }
    return [ 'ok' => true, 'prepared' => $prepared ];
}

/** Sealed once, only after verified readback. Binding cannot be overwritten. */
function wpae_accepted_contract_seal( array $prepared, array $operation, array $saved_owned ): array {
    if ( ! hash_equals( (string) $prepared['compiled_signature'], wpae_llm_decision_signature( $saved_owned ) ) ) { return [ 'ok' => false, 'reason' => 'contract_readback_mismatch' ]; }
    $payload = array_merge( $prepared, [ 'operation_id' => $operation['operation_id'], 'post_id' => $operation['post_id'], 'saved_revision' => $operation['revision'], 'saved_hash' => $operation['saved_hash'], 'owned_root_ids' => array_column( $saved_owned, 'id' ), 'after_owned' => $saved_owned, 'owned_fingerprint' => wpae_accepted_owned_fingerprint( $saved_owned ) ] );
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

function wpae_accepted_contract_eligibility( array $operation, array $data ): array {
    if ( ! empty( $operation['typed_undone'] ) ) { return [ 'status' => 'already_undone' ]; }
    $loaded = wpae_accepted_contract_get( $operation );
    if ( empty( $loaded['ok'] ) ) { return [ 'status' => 'unavailable', 'reason' => $loaded['reason'] ]; }
    $contract = $loaded['contract'];
    if ( count( (array) $contract['owned_root_ids'] ) !== 1 ) { return [ 'status' => 'ambiguous', 'reason' => 'single_owned_root_required' ]; }
    $owned = wpae_accepted_owned_roots( $data, $contract['owned_root_ids'] );
    if ( count( $owned ) !== 1 || ! wpae_accepted_owned_matches( $contract['after_owned'], $owned ) ) { return [ 'status' => 'changed_target' ]; }
    foreach ( wpae_design_operation_store() as $candidate ) {
        if ( ( $candidate['parent_operation_id'] ?? '' ) === $operation['operation_id'] && empty( $candidate['typed_undone'] ) && in_array( $candidate['current_state'] ?? '', [ 'written', 'rendered', 'reviewed', 'completed' ], true ) ) { return [ 'status' => 'ambiguous', 'reason' => 'active_child_operation' ]; }
    }
    return [ 'status' => 'available', 'action' => empty( $contract['parent_operation_id'] ) ? 'undo_creation' : 'undo_repair', 'expires_at' => $loaded['expires_at'] ];
}

function wpae_accepted_contract_descriptors( int $post_id, array $data ): array {
    $descriptors = [];
    foreach ( array_reverse( wpae_design_operation_store() ) as $operation ) {
        if ( (int) ( $operation['post_id'] ?? 0 ) !== $post_id || empty( $operation['root_ids'] ) ) { continue; }
        $eligibility = wpae_accepted_contract_eligibility( $operation, $data );
        $descriptors[] = array_merge( $eligibility, array_intersect_key( $operation, array_flip( [ 'operation_id', 'operation_identity', 'revision', 'root_ids', 'accepted_contract_id' ] ) ) );
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
    if ( wpae_accepted_contract_eligibility( $operation, $current )['status'] !== 'available' ) { return false; }
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
        if ( ! $operation || (int) $operation['post_id'] !== $post_id || (int) $request->get_param( 'revision' ) !== (int) $operation['revision'] || (string) $request->get_param( 'operation_identity' ) !== (string) $operation['operation_identity'] ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_scope_or_revision' ], 409 ); }
        $data = wpae_get_elementor_data_for_post( $post_id );
        if ( ! is_array( $data ) ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_readback_unavailable' ], 409 ); }
        $eligibility = wpae_accepted_contract_eligibility( $operation, $data );
        if ( $eligibility['status'] !== 'available' ) { return new WP_REST_Response( [ 'ok' => false, 'code' => 'typed_undo_' . $eligibility['status'], 'eligibility' => $eligibility ], 409 ); }
        $contract = wpae_accepted_contract_get( $operation )['contract'];
        $next = [];
        foreach ( $data as $root ) {
            if ( in_array( $root['id'] ?? '', $contract['owned_root_ids'], true ) ) { $next = array_merge( $next, $contract['before_owned'] ); }
            else { $next[] = $root; }
        }
        $update = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/update' );
        foreach ( [ 'post_id' => $post_id, 'elementor_data' => $next, 'expected_before_elementor_data' => $data, 'template' => get_post_meta( $post_id, '_wp_page_template', true ), 'operation_id' => $id . '-undo', 'operation_root_ids' => $contract['owned_root_ids'] ] as $key => $value ) { $update->set_param( $key, $value ); }
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
    // Native Save payload is already authored; generation defaults must not alter it.
    $document_model = wpae_get_elementor_data_for_post( $post_id );
    if ( ! is_array( $document_model ) || ! wpae_accepted_owned_matches( $document_model, $data['elements'] ) ) { throw new RuntimeException( 'Typed save blocked: whole native document differs from fresh server data. Local edits preserved.' ); }
    $owned = wpae_accepted_owned_roots( $data['elements'], $contract['owned_root_ids'] );
    if ( ! wpae_accepted_owned_matches( $contract['after_owned'], $owned ) ) { throw new RuntimeException( 'Typed save blocked: editor owned tree differs from accepted server decisions. Local edits preserved; resync required.' ); }
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

function wpae_accepted_lifecycle_request( array $context ) {
    $post_id = absint( $context['post_id'] ?? 0 );
    $operation = wpae_design_operation_find_by_id( sanitize_key( (string) ( $context['accepted_operation_id'] ?? '' ) ) );
    if ( ! current_user_can( 'edit_post', $post_id ) || ! $operation || (int) $operation['post_id'] !== $post_id || (int) ( $context['accepted_revision'] ?? 0 ) !== (int) $operation['revision'] || (string) ( $context['accepted_identity'] ?? '' ) !== (string) $operation['operation_identity'] ) { return new WP_Error( 'wpae_typed_scope_conflict', 'Точная операция/revision не подтверждены.', [ 'status' => 409, 'write_count' => 0 ] ); }
    $loaded = wpae_accepted_contract_get( $operation );
    if ( empty( $loaded['ok'] ) ) { return new WP_Error( 'wpae_typed_contract_unavailable', $loaded['reason'], [ 'status' => 409, 'write_count' => 0 ] ); }
    $data = wpae_get_elementor_data_for_post( $post_id );
    if ( ! is_array( $data ) || wpae_accepted_contract_eligibility( $operation, $data )['status'] !== 'available' ) { return new WP_Error( 'wpae_typed_target_changed', 'Owned fingerprint или lineage изменились.', [ 'status' => 409, 'write_count' => 0 ] ); }
    $contract = $loaded['contract'];
    $owned = wpae_accepted_owned_roots( $data, $contract['owned_root_ids'] );
    $action = $context['lifecycle_action'] ?? '';
    if ( $action === 'check_document_model' ) {
        // Read-only fresh native comparison protects foreign/local edits before Undo reload.
        $model = $context['editor_document_model'] ?? null;
        $mismatch = null;
        $projected = is_array( $model ) ? wpae_accepted_project_owned_model( $data, $model, null, $mismatch ) : null;
        $matches = $projected !== null && hash_equals( wpae_accepted_owned_fingerprint( $data ), wpae_accepted_owned_fingerprint( $projected ) );
        return new WP_REST_Response( [ 'mismatch' => $mismatch, 'ok' => $matches, 'code' => $matches ? 'typed_document_model_matches' : 'typed_document_model_mismatch', 'operation_id' => $operation['operation_id'], 'root_ids' => array_column( $data, 'id' ), 'write_count' => 0 ], $matches ? 200 : 409 );
    }
    if ( $action === 'resync' ) {
        $model = $context['editor_owned_model'] ?? null;
        if ( ! is_array( $model ) ) { return new WP_Error( 'wpae_typed_resync_model_missing', 'Нужно текущее owned model.', [ 'status' => 409 ] ); }
        $current_model = $model;
        $matches_before = wpae_accepted_owned_matches( $contract['before_owned'], $current_model );
        $matches_after = wpae_accepted_owned_matches( $contract['after_owned'], $current_model );
        if ( ! $matches_before && ! $matches_after ) { return new WP_Error( 'wpae_typed_resync_local_conflict', 'Owned local changes не перезаписаны.', [ 'status' => 409 ] ); }
        return new WP_REST_Response( [ 'ok' => true, 'editor_sync' => [ 'elements' => $owned, 'mode' => $model ? 'replace' : 'insert', 'replace_element_id' => $contract['owned_root_ids'][0], 'operation_owned_root_ids' => $contract['owned_root_ids'], 'after_top_level_ids' => array_column( $data, 'id' ) ], 'write_count' => 0 ], 200 );
    }
    if ( $action === 'check_model' ) {
        $model = $context['editor_owned_model'] ?? null;
        $mismatch = null;
        $projected = is_array( $model ) ? wpae_accepted_project_owned_model( $contract['after_owned'], $model, null, $mismatch ) : null;
        $matches = $projected !== null && hash_equals( wpae_accepted_owned_fingerprint( $contract['after_owned'] ), wpae_accepted_owned_fingerprint( $projected ) );
        return new WP_REST_Response( [ 'ok' => $matches, 'code' => $matches ? 'typed_model_matches' : 'typed_editor_model_mismatch', 'operation_id' => $operation['operation_id'], 'contract_hash' => $loaded['hash'], 'mismatch' => $mismatch, 'write_count' => 0 ], $matches ? 200 : 409 );
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
    $prepared = wpae_accepted_contract_prepare( $brief, $plan, $compiled['elementor_data'], $owned, $operation['operation_id'] );
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
