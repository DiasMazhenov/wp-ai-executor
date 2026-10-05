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

$old = audit_tree('OLD saved content');
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
