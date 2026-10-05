<?php
/** Local audit probe. Real transaction/validation code, in-memory WP boundary; no site access. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WPAE_ROLLBACK_MAX_SNAPSHOTS', 20 );
define( 'WPAE_ROLLBACK_TTL_SECONDS', 7200 );
class WP_Error {
    public function __construct( public $code, public $message, public $data = null ) {}
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
    public function get_param( $key ) { return null; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_slash( $value ) { return is_array($value) ? array_map('wp_slash',$value) : (is_string($value) ? addslashes($value) : $value); }
function get_current_user_id() { return 10; }
function get_post( $id, $output = null ) {
    if ( isset( $GLOBALS['autosaves'][ $id ] ) ) {
        return $GLOBALS['autosaves'][ $id ];
    }
    return $output === ARRAY_A ? (['ID'=>$id,'post_title'=>'Probe page'] + ($GLOBALS['post_record']??[])) : (object) (['ID'=>$id] + ($GLOBALS['post_record']??[]));
}
function get_post_meta( $id, $key = '', $single = false ) {
    if ( $key === '' ) { return array_map(static fn($value)=>[$value],$GLOBALS['meta']??[]); }
    return $GLOBALS['meta'][ $key ] ?? '';
}
function update_post_meta( $id, $key, $value ) {
    if ( $key === '_elementor_data' && ! empty( $GLOBALS['fail_data_write'] ) ) { return false; }
    if ( ( $GLOBALS['fail_meta_key'] ?? '' ) === $key ) { return false; }
    if ( ( $GLOBALS['no_op_meta_key'] ?? '' ) === $key ) { return false; }
    $GLOBALS['meta'][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value;
    if ( $key === '_elementor_data' && ! empty( $GLOBALS['concurrent_meta_json'] ) ) { $GLOBALS['meta'][$key]=$GLOBALS['concurrent_meta_json']; }
    if ( $key === '_wp_page_template' && isset( $GLOBALS['concurrent_meta_html'] ) ) { $GLOBALS['post_record']['post_content']=$GLOBALS['concurrent_meta_html']; }
    return true;
}
function add_post_meta($id,$key,$value) { $GLOBALS['meta'][$key]=is_string($value)?stripslashes($value):$value; return true; }
function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/','',strtolower($s)); }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $key ] ); return true; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function do_action( ...$args ) {}
function wp_update_post($post,$error=false) { if(!empty($GLOBALS['fail_projection'])) { return new WP_Error('write_failed','Projection failure'); } foreach($post as $k=>$v) { $GLOBALS['post_record'][$k]=is_string($v)?stripslashes($v):$v; } return $post['ID']; }
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


// Native boundary fixture mirrors Elementor 4.1.1 conversion semantics, including
// nonempty HTML conversion. It deliberately reproduces the old failure, not a
// hydration stub that always returns []. Projection API accepts explicit arrays.
class AuditProjectionDB {
    public function switch_to_post($id) {}
    public function restore_current_post() {}
    public function get_plain_text_from_data($elements) {
        $html='';
        foreach($elements as $node) {
            if(($node['widgetType']??'')==='heading') { $html.='<h2>'.$node['settings']['title'].'</h2>'; }
            if(($node['widgetType']??'')==='text-editor') { $html.=$node['settings']['editor']; }
            $html.=$this->get_plain_text_from_data($node['elements']??[]);
        }
        return $html;
    }
}
class AuditElementorPlugin {
    public static $instance;
    public $db;
    public $files_manager;
    public function __construct() { $this->db=new AuditProjectionDB(); $this->files_manager=new class { public function clear_cache() {} }; }
    public static function instance() { return self::$instance??=new self(); }
}
class_alias(AuditElementorPlugin::class, 'Elementor\\Plugin');
function audit_hydrate() {
    $elements=json_decode($GLOBALS['meta']['_elementor_data']??'null',true);
    if(!is_array($elements)) { $elements=[]; }
    if(!$elements && !empty($GLOBALS['post_record']['post_content'])) {
        return [['id'=>'converted-unowned','elType'=>'container','settings'=>[], 'elements'=>[['id'=>'converted-copy','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>$GLOBALS['post_record']['post_content']],'elements'=>[]]]]];
    }
    return $elements;
}
$projection_checks=0;
$GLOBALS['meta']=['_elementor_data'=>'[]']; $GLOBALS['post_record']=['post_content'=>''];
for($cycle=1;$cycle<=2;$cycle++) {
    assert_probe(audit_hydrate()===[] && $GLOBALS['post_record']['post_content']==='','Second create did not start from the preceding verified inverse.');
    $tree=audit_tree('Cycle '.$cycle);
    $ctx=['sync_document_projection'=>true,'expected_before_elementor_data'=>[], 'expected_before_html_hash'=>hash('sha256','')];
    assert_probe(wpae_save_elementor_page_data(42,$tree,'elementor_canvas',$ctx)===true,'Explicit create projection failed.');
    // Native Save publishes its HTML. Native projection contains actual text.
    assert_probe(audit_hydrate()===$tree && str_contains($GLOBALS['post_record']['post_content'],'Cycle '.$cycle),'Native Save/hydration loses content.');
    $native_html=$GLOBALS['post_record']['post_content'];
    // Reproduce old JSON-only Undo and prove hydration converts retained HTML.
    $GLOBALS['meta']['_elementor_data']='[]';
    assert_probe(audit_hydrate()!==[],'Fixture failed to detect old stale HTML hydration.');
    $GLOBALS['meta']['_elementor_data']=wp_json_encode($tree);
    $ctx=['sync_document_projection'=>true,'verified_empty_inverse'=>true,'expected_before_elementor_data'=>$tree,'expected_before_html_hash'=>hash('sha256',$native_html)];
    assert_probe(wpae_save_elementor_page_data(42,[],'elementor_canvas',$ctx)===true,'Verified inverse projection failed.');
    assert_probe(audit_hydrate()===[] && audit_hydrate()===[] && $GLOBALS['post_record']['post_content']==='','Reload reintroduced removed HTML.');
    $projection_checks+=5;
}
// Existing HTML is never erased on the strength of JSON=[] alone.
$GLOBALS['meta']=['_elementor_data'=>'[]'];$GLOBALS['post_record']=['post_content'=>'<p>User legacy HTML</p>'];
$ctx=['sync_document_projection'=>true,'expected_before_elementor_data'=>[], 'expected_before_html_hash'=>hash('sha256',$GLOBALS['post_record']['post_content'])];
assert_probe(is_wp_error(wpae_save_elementor_page_data(42,audit_tree('New'),'elementor_canvas',$ctx)) && $GLOBALS['post_record']['post_content']==='<p>User legacy HTML</p>','Unscoped legacy HTML was overwritten.');
assert_probe(audit_hydrate()[0]['id']==='converted-unowned','Ordinary legacy HTML import was globally disabled.');
// CAS refusals occur before any JSON/HTML mutation.
$GLOBALS['meta']=['_elementor_data'=>'[]'];$GLOBALS['post_record']=['post_content'=>'Concurrent HTML'];
$ctx['expected_before_html_hash']=hash('sha256','');
assert_probe(is_wp_error(wpae_save_elementor_page_data(42,audit_tree('New'),'elementor_canvas',$ctx)) && $GLOBALS['meta']['_elementor_data']==='[]' && $GLOBALS['post_record']['post_content']==='Concurrent HTML','Concurrent HTML was lost.');
$GLOBALS['meta']['_elementor_data']=wp_json_encode(audit_tree('Concurrent JSON'));
assert_probe(is_wp_error(wpae_save_elementor_page_data(42,audit_tree('New'),'elementor_canvas',$ctx)) && str_contains($GLOBALS['meta']['_elementor_data'],'Concurrent JSON'),'Concurrent JSON was lost.');
$projection_checks+=4;
emit_probe('explicit_projection_and_hydration',['checks'=>$projection_checks,'two_cycles'=>true,'old_bug_reproduced'=>true,'legacy_conversion_preserved'=>true]);


// Actual rollback restores both metadata and post HTML after projection failure.
$GLOBALS['meta']=['_elementor_data'=>wp_json_encode($tree)];
$GLOBALS['post_record']=['post_content'=>$native_html];
$snapshot=wpae_create_rollback_snapshot('projection failure',[42]);
$GLOBALS['fail_projection']=true;
$failed=wpae_save_elementor_page_data(42,[],'elementor_canvas',['sync_document_projection'=>true,'expected_before_elementor_data'=>$tree,'expected_before_html_hash'=>hash('sha256',$native_html)]);
assert_probe(is_wp_error($failed),'Projection refusal was not reported.');
$GLOBALS['fail_projection']=false;
$restore=wpae_restore_rollback_snapshot_by_id($snapshot['id'],false,wpae_rollback_post_fingerprint(42));
assert_probe($restore['ok'] && json_decode($GLOBALS['meta']['_elementor_data'],true)===$tree && $GLOBALS['post_record']['post_content']===$native_html,'Actual rollback failed to restore JSON and HTML together.');
$before_fingerprint=wpae_rollback_post_fingerprint(42);
$GLOBALS['post_record']['post_content']='Concurrent newer HTML';
$conflict=wpae_restore_rollback_snapshot_by_id($snapshot['id'],false,$before_fingerprint);
assert_probe(!$conflict['ok'] && $GLOBALS['post_record']['post_content']==='Concurrent newer HTML','Stale rollback erased concurrent HTML.');
emit_probe('projection_failure_rollback',['json_and_html_restored'=>true,'concurrent_rollback_refused'=>true]);

// Changes made by metadata hooks must survive the route's scoped rollback.
$GLOBALS['meta']=['_elementor_data'=>wp_json_encode($tree)];$GLOBALS['post_record']=['post_content'=>$native_html];
$snapshot=wpae_create_rollback_snapshot('concurrent projection',[42]);
$GLOBALS['concurrent_meta_html']='Newer user HTML';
$failed=wpae_save_elementor_page_data(42,[],'elementor_canvas',['sync_document_projection'=>true,'expected_before_elementor_data'=>$tree,'expected_before_html_hash'=>hash('sha256',$native_html)]);
unset($GLOBALS['concurrent_meta_html']);
assert_probe(is_wp_error($failed) && !empty($failed->get_error_data()['preserve_current_post_content']),'HTML hook race was not detected.');
$restored=wpae_restore_rollback_snapshot_by_id($snapshot['id'],false,wpae_rollback_post_fingerprint(42),['post_content'=>true]);
assert_probe($restored['ok'] && $GLOBALS['post_record']['post_content']==='Newer user HTML','Rollback overwrote newer HTML.');
$GLOBALS['post_record']['post_content']=$native_html;
$GLOBALS['concurrent_meta_json']=wp_json_encode(audit_tree('Newer user JSON'));
$failed=wpae_save_elementor_page_data(42,[],'elementor_canvas',['sync_document_projection'=>true,'expected_before_elementor_data'=>$tree,'expected_before_html_hash'=>hash('sha256',$native_html)]);
unset($GLOBALS['concurrent_meta_json']);
assert_probe(is_wp_error($failed) && !empty($failed->get_error_data()['preserve_current_elementor_data']),'JSON hook race was not detected.');
$restored=wpae_restore_rollback_snapshot_by_id($snapshot['id'],false,wpae_rollback_post_fingerprint(42),['_elementor_data'=>true]);
assert_probe($restored['ok'] && str_contains($GLOBALS['meta']['_elementor_data'],'Newer user JSON'),'Rollback overwrote newer JSON.');
emit_probe('metadata_hook_concurrency',['newer_html_preserved'=>true,'newer_json_preserved'=>true]);
