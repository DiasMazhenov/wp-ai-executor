<?php
/** Local audit probe. Real transaction/validation code, in-memory WP boundary; no site access. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WPAE_ROLLBACK_MAX_SNAPSHOTS', 20 );
define( 'WPAE_ROLLBACK_TTL_SECONDS', 7200 );
class WP_Error {
    public function __construct( public $code, public $message, public $data = null ) {}
}
class WP_REST_Request {
    public function get_param( $key ) { return null; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_slash( $value ) { return addslashes( $value ); }
function get_current_user_id() { return 10; }
function get_post( $id, $output = null ) {
    if ( isset( $GLOBALS['autosaves'][ $id ] ) ) {
        return $GLOBALS['autosaves'][ $id ];
    }
    return $output === ARRAY_A ? [ 'ID' => $id, 'post_title' => 'Probe page' ] : (object) [ 'ID' => $id ];
}
function get_post_meta( $id, $key = '', $single = false ) {
    if ( $key === '' ) { return $GLOBALS['meta'] ?? []; }
    return $GLOBALS['meta'][ $key ] ?? '';
}
function update_post_meta( $id, $key, $value ) {
    if ( $key === '_elementor_data' && ! empty( $GLOBALS['fail_data_write'] ) ) { return false; }
    if ( ( $GLOBALS['fail_meta_key'] ?? '' ) === $key ) { return false; }
    if ( ( $GLOBALS['no_op_meta_key'] ?? '' ) === $key ) { return false; }
    $GLOBALS['meta'][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value;
    return true;
}
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $key ] ); return true; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function do_action( ...$args ) {}
function wpae_get_enforceable_skill_rules() { return []; }
function wpae_get_project_design_tokens() { return [ 'palette' => [ 'ink' => '#111827' ] ]; }
function wpae_get_design_system_required_classes( ...$args ) { return [ 'wpae-system-test' ]; }
function wpae_build_project_design_system() { return []; }
function wpae_get_design_system_id() { return 'test'; }
function wpae_get_design_system_source_hash() { return 'test-hash'; }
// Quality/Vision are outside these probes; transaction quality flags stay off.
function wpae_build_after_save_quality_summary( $id, $data, $preflight ) {
    return [ 'visual_audit' => [ 'level' => 'acceptable', 'score' => 90 ], 'audited_title' => $data[0]['elements'][0]['settings']['title'] ?? null ];
}
function wpae_build_elementor_design_review( $data, $context ) { return [ 'state' => 'approved' ]; }
// Mirrors WP's documented default: user_id=0 applies no author filter, latest first.
function wp_get_post_autosave( $post_id, $user_id = 0 ) {
    $candidates = array_values( array_filter( $GLOBALS['autosaves'], static fn( $a ) => $a->post_parent === $post_id && ( ! $user_id || $a->post_author === $user_id ) ) );
    usort( $candidates, static fn( $a, $b ) => ( $b->post_modified ?? '' ) <=> ( $a->post_modified ?? '' ) );
    return $candidates[0] ?? false;
}
function wp_delete_post( $id, $force = false ) {
    if ( ! empty( $GLOBALS['delete_failure'] ) ) {
        return false;
    }
    $deleted = $GLOBALS['autosaves'][ $id ] ?? null;
    $GLOBALS['deleted'][] = $deleted;
    unset( $GLOBALS['autosaves'][ $id ] );
    return $deleted;
}

$root = dirname( __DIR__, 3 );
require $root . '/includes/elementor/data.php';
require $root . '/includes/elementor/validation-rules.php';
require $root . '/includes/elementor/design-contract.php';
require $root . '/includes/rollback/rollback.php';
require $root . '/includes/elementor/transactions.php';

function audit_tree( string $title, bool $marked = true, string $id = 'aaaa111' ): array {
    return [ [
        'id' => $id, 'elType' => 'container',
        'settings' => [ 'container_type' => 'flex', 'background_color' => '#111827', '_css_classes' => $marked ? 'wpae-system-test' : '' ],
        'elements' => [ [ 'id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => $title ], 'elements' => [] ] ],
    ] ];
}
function emit_probe( string $name, array $result ): void {
    echo wp_json_encode( [ 'probe' => $name ] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
}
function assert_probe( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

$GLOBALS['autosaves'] = [
    1001 => (object) [ 'ID' => 1001, 'post_parent' => 42, 'post_author' => 10, 'post_modified' => '2026-09-13 10:01:00' ],
    2002 => (object) [ 'ID' => 2002, 'post_parent' => 42, 'post_author' => 20, 'post_modified' => '2026-09-13 10:02:00' ],
];
$GLOBALS['deleted'] = [];
$cleanup = wpae_clear_current_elementor_autosave( 42 );
$owner_unknown = $cleanup;
assert_probe( $cleanup['status'] === 'owner_unknown' && empty( $GLOBALS['deleted'] ), 'A01: an owner-less cleanup must not query or delete another user\'s autosave.' );
$captured = wpae_capture_elementor_autosave( 42, 10 );
$cleanup = wpae_clear_current_elementor_autosave( 42, 10, $captured );
assert_probe( $cleanup['status'] === 'cleared' && count( $GLOBALS['deleted'] ) === 1 && ( $GLOBALS['deleted'][0]->post_author ?? null ) === 10, 'A01: explicit owner cleanup did not delete only the captured own autosave.' );
assert_probe( isset( $GLOBALS['autosaves'][2002] ), 'A01: another user\'s autosave was deleted.' );
emit_probe( 'A01_autosave_owner', [ 'current_user' => get_current_user_id(), 'owner_unknown_report' => $owner_unknown, 'captured_report' => $captured, 'report' => $cleanup, 'deleted_owner' => $GLOBALS['deleted'][0]->post_author ?? null, 'remaining_ids' => array_keys( $GLOBALS['autosaves'] ) ] );

$GLOBALS['autosaves'] = [ 3003 => (object) [ 'ID' => 3003, 'post_parent' => 42, 'post_author' => 10, 'post_modified' => '2026-09-13 10:03:00' ] ];
$captured = wpae_capture_elementor_autosave( 42, 10 );
$GLOBALS['autosaves'][3003]->post_modified = '2026-09-13 10:04:00';
$changed_cleanup = wpae_cleanup_elementor_autosave( $captured );
assert_probe( $changed_cleanup['status'] === 'changed_during_operation' && isset( $GLOBALS['autosaves'][3003] ), 'A01: an edited own autosave must be preserved.' );
$GLOBALS['autosaves'] = [
    4004 => (object) [ 'ID' => 4004, 'post_parent' => 42, 'post_author' => 10, 'post_modified' => '2026-09-13 10:04:00' ],
];
$captured = wpae_capture_elementor_autosave( 42, 10 );
$GLOBALS['autosaves'][4005] = (object) [ 'ID' => 4005, 'post_parent' => 42, 'post_author' => 10, 'post_modified' => '2026-09-13 10:05:00' ];
$replaced_cleanup = wpae_cleanup_elementor_autosave( $captured );
assert_probe( $replaced_cleanup['status'] === 'replaced_during_operation' && count( $GLOBALS['autosaves'] ) === 2, 'A01: a newer own autosave must be preserved.' );
$GLOBALS['autosaves'] = [ 5005 => (object) [ 'ID' => 5005, 'post_parent' => 42, 'post_author' => 10, 'post_modified' => '2026-09-13 10:06:00' ] ];
$captured = wpae_capture_elementor_autosave( 42, 10 );
$GLOBALS['delete_failure'] = true;
$delete_failure_cleanup = wpae_cleanup_elementor_autosave( $captured );
$GLOBALS['delete_failure'] = false;
assert_probe( $delete_failure_cleanup['status'] === 'delete_failed' && ! $delete_failure_cleanup['ok'] && isset( $GLOBALS['autosaves'][5005] ), 'A01: a failed autosave delete must be reported and preserved.' );
emit_probe( 'A01_autosave_lifecycle', [ 'changed_during_operation' => $changed_cleanup, 'replaced_during_operation' => $replaced_cleanup, 'delete_failure' => $delete_failure_cleanup ] );

$legacy = audit_tree( 'Existing user block', false );
$combined = array_merge( $legacy, audit_tree( 'New generated block', true, 'bbbb222' ) );
$context = [ 'allow_unchanged_legacy_top_level' => $legacy ];
$pre_contract = wpae_validate_design_system_contract( $combined, $context );
$GLOBALS['meta'] = [ '_elementor_data' => wp_json_encode( [] ) ];
$write_context = $context + [ 'expected_before_elementor_data' => [] ];
$saved = wpae_save_elementor_page_data( 42, $combined, 'elementor_canvas', $write_context );
$verified = wpae_verify_saved_elementor_transaction( 42, $combined, [ 'ok' => true ] + $context, new WP_REST_Request(), null, $write_context );
assert_probe( $pre_contract['ok'], 'A03: the preflight contract rejected an unchanged legacy Flex root.' );
assert_probe( $saved === true && $verified['ok'], 'A03: the same legacy exception was not preserved through write/read-back/finalize verification.' );
assert_probe( empty( $verified['checks']['design_system_contract']['details']['errors'] ), 'A03: after-save contract lost the trusted before-state context.' );
emit_probe( 'A03_legacy_after_save', [ 'input_validation_errors' => wpae_validate_elementor_data_array( $combined ), 'pre_contract_ok' => $pre_contract['ok'], 'save_returned_success' => $saved === true, 'after_save_ok' => $verified['ok'], 'failed_checks' => $verified['failed_checks'], 'contract_errors' => $verified['checks']['design_system_contract']['details']['errors'] ] );

$GLOBALS['no_op_meta_key'] = '_elementor_edit_mode';
$no_op_saved = wpae_save_elementor_page_data( 42, $combined, 'elementor_canvas', [ 'expected_before_elementor_data' => $combined, 'allow_unchanged_legacy_top_level' => $legacy ] );
$GLOBALS['no_op_meta_key'] = '';
assert_probe( $no_op_saved === true, 'A04: update_post_meta=false for an unchanged required value was treated as a write failure.' );
$newer_tree = audit_tree( 'Newer concurrent content' );
$GLOBALS['meta']['_elementor_data'] = wp_json_encode( $newer_tree );
$conflict = wpae_save_elementor_page_data( 42, $combined, 'elementor_canvas', [ 'expected_before_elementor_data' => $combined ] );
assert_probe( $conflict instanceof WP_Error && $conflict->code === 'wpae_elementor_write_conflict', 'A04: a concurrent before-state change was not rejected.' );
assert_probe( json_decode( $GLOBALS['meta']['_elementor_data'], true )[0]['elements'][0]['settings']['title'] === 'Newer concurrent content', 'A04: write conflict changed newer saved content.' );
$GLOBALS['meta']['_elementor_data'] = wp_json_encode( $combined );
unset( $GLOBALS['meta']['_wpae_design_system_hash'] );
$GLOBALS['fail_meta_key'] = '_wpae_design_system_hash';
$required_meta_failure = wpae_save_elementor_page_data( 42, $combined, 'elementor_canvas', [ 'expected_before_elementor_data' => $combined ] );
$GLOBALS['fail_meta_key'] = '';
assert_probe( $required_meta_failure instanceof WP_Error && $required_meta_failure->code === 'wpae_elementor_metadata_write_failed', 'A04: a missing required metadata value was not reported.' );
emit_probe( 'A04_write_conflicts_and_noop', [ 'no_op_saved' => $no_op_saved === true, 'conflict_error' => $conflict instanceof WP_Error ? $conflict->code : null, 'required_meta_error' => $required_meta_failure instanceof WP_Error ? $required_meta_failure->code : null ] );

$GLOBALS['meta']['_elementor_data'] = wp_json_encode( $combined );
$before_fingerprint = wpae_rollback_post_fingerprint( 42 );
$GLOBALS['options']['wp_ai_executor_rollback_snapshots'] = [
    'probe-snapshot' => [
        'id' => 'probe-snapshot',
        'created_at_unix' => time(),
        'expires_at_unix' => time() + 7200,
        'posts' => [ '42' => [ 'exists' => true ] ],
        'options' => [],
    ],
];
$GLOBALS['meta']['_elementor_data'] = wp_json_encode( $newer_tree );
$rollback_guard = wpae_restore_rollback_snapshot_by_id( 'probe-snapshot', false, $before_fingerprint );
assert_probe( empty( $rollback_guard['ok'] ) && (int) $rollback_guard['status'] === 409 && ! empty( $rollback_guard['conflict'] ), 'A04: rollback did not reject a newer concurrent page state.' );
assert_probe( json_decode( $GLOBALS['meta']['_elementor_data'], true )[0]['elements'][0]['settings']['title'] === 'Newer concurrent content', 'A04: guarded rollback overwrote newer concurrent content.' );
assert_probe( isset( $GLOBALS['options']['wp_ai_executor_rollback_snapshots']['probe-snapshot'] ), 'A04: a conflicting rollback snapshot was consumed.' );
emit_probe( 'A04_concurrent_rollback_guard', [ 'before_fingerprint' => $before_fingerprint, 'result' => $rollback_guard, 'newer_title' => json_decode( $GLOBALS['meta']['_elementor_data'], true )[0]['elements'][0]['settings']['title'] ] );

$old = audit_tree( 'OLD saved content' );
$expected = audit_tree( 'NEW requested content' );
$GLOBALS['meta'] = [ '_elementor_data' => wp_json_encode( $old ) ];
$GLOBALS['fail_data_write'] = true;
$GLOBALS['autosaves'] = [];
$saved = wpae_save_elementor_page_data( 42, $expected, 'elementor_canvas', [ 'expected_before_elementor_data' => $old ] );
$verified = wpae_verify_saved_elementor_transaction( 42, $expected, [ 'ok' => true ], new WP_REST_Request(), null, [ 'expected_before_elementor_data' => $old ] );
$actual = json_decode( $GLOBALS['meta']['_elementor_data'], true );
assert_probe( $saved instanceof WP_Error && $saved->code === 'wpae_elementor_metadata_write_failed', 'A04: a failed _elementor_data write was reported as success.' );
assert_probe( ! $verified['ok'] && in_array( 'saved_elementor_data', $verified['failed_checks'], true ), 'A04: after-save verification accepted content that was not written.' );
assert_probe( $actual[0]['elements'][0]['settings']['title'] === 'OLD saved content', 'A04: failed write overwrote the old content in the probe boundary.' );
assert_probe( $verified['quality_summary']['audited_title'] === 'OLD saved content', 'A04: quality summary was built from the requested tree instead of confirmed read-back.' );
emit_probe( 'A04_failed_write_reported_success', [ 'save_returned_success' => $saved === true, 'save_error' => $saved instanceof WP_Error ? $saved->code : null, 'after_save_ok' => $verified['ok'], 'failed_checks' => $verified['failed_checks'], 'actual_title' => $actual[0]['elements'][0]['settings']['title'], 'expected_title' => $expected[0]['elements'][0]['settings']['title'], 'quality_audited_title' => $verified['quality_summary']['audited_title'] ] );

$GLOBALS['fail_data_write'] = false;
$GLOBALS['autosaves'] = [ 1001 => (object) [ 'ID' => 1001, 'post_parent' => 42, 'post_author' => 10, 'post_modified' => '2026-09-13 10:07:00' ] ];
$GLOBALS['meta'] = [ '_elementor_data' => wp_json_encode( $old ) ];
$autosave_context = [ 'expected_before_elementor_data' => $old, 'allow_unchanged_legacy_top_level' => $legacy, 'autosave_snapshot' => wpae_capture_elementor_autosave( 42, 10 ) ];
$saved = wpae_save_elementor_page_data( 42, $combined, 'elementor_canvas', $autosave_context );
$GLOBALS['meta']['_elementor_data'] = '{invalid-json';
$verified = wpae_verify_saved_elementor_transaction( 42, $combined, [ 'ok' => true ] + $context, new WP_REST_Request(), null, $autosave_context );
assert_probe( $saved === true && ! $verified['ok'], 'A01/A04: the probe did not reach a failed after-save verification.' );
assert_probe( count( $GLOBALS['autosaves'] ) === 1, 'A01: autosave was deleted before a failed after-save verification completed.' );
$failed_verification_autosaves = count( $GLOBALS['autosaves'] );
$GLOBALS['meta']['_elementor_data'] = wp_json_encode( $combined );
$verified_after_repair = wpae_verify_saved_elementor_transaction( 42, $combined, [ 'ok' => true ] + $context, new WP_REST_Request(), null, $autosave_context );
$cleanup_after_success = wpae_cleanup_elementor_autosave( $autosave_context['autosave_snapshot'] );
assert_probe( $verified_after_repair['ok'] && $cleanup_after_success['status'] === 'cleared' && empty( $GLOBALS['autosaves'] ), 'A01: the captured autosave was not cleaned after successful read-back verification.' );
emit_probe( 'A01_autosave_after_failed_verification', [ 'save_returned_success' => $saved === true, 'after_save_ok' => $verified['ok'], 'failed_verification_remaining_autosaves' => $failed_verification_autosaves, 'failed_checks' => $verified['failed_checks'], 'after_repair_ok' => $verified_after_repair['ok'], 'cleanup_report' => $cleanup_after_success ] );

// v259: the same server-attested empty inverse policy must reach saved readback.
$GLOBALS['meta'] = [ '_elementor_data' => wp_json_encode([]) ];
$verified_empty = wpae_verify_saved_elementor_transaction(42, [], ['ok'=>true], new WP_REST_Request(), null, ['verified_empty_inverse'=>true]);
assert_probe($verified_empty['ok'] && $verified_empty['checks']['design_system_contract']['ok'], 'Verified empty inverse was rejected after real transaction readback.');
$ordinary_empty = wpae_verify_saved_elementor_transaction(42, [], ['ok'=>true], new WP_REST_Request());
assert_probe(!$ordinary_empty['ok'] && !$ordinary_empty['checks']['design_system_contract']['ok'], 'Ordinary empty write incorrectly inherits inverse permission.');
$GLOBALS['meta']['_elementor_data'] = wp_json_encode($old);
$mismatched_empty = wpae_verify_saved_elementor_transaction(42, [], ['ok'=>true], new WP_REST_Request(), null, ['verified_empty_inverse'=>true]);
assert_probe(!$mismatched_empty['ok'] && !$mismatched_empty['checks']['saved_elementor_data']['ok'], 'Empty inverse incorrectly accepts mismatched readback.');
emit_probe('typed_empty_inverse_readback', ['verified'=>$verified_empty['ok'], 'ordinary_empty_refused'=>!$ordinary_empty['ok'], 'mismatch_refused'=>!$mismatched_empty['ok']]);
