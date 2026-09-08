<?php
/** Runtime regressions: real generation/normalizer/transport with an in-memory WP boundary. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WPAE_VERSION', 'test' );
define( 'WPAE_GUIDE_VERSION', 'test' );
define( 'WPAE_ROLLBACK_MAX_SNAPSHOTS', 20 );
define( 'WPAE_ROLLBACK_TTL_SECONDS', 7200 );

class WP_Error {
    public function __construct( private string $code, private string $message, private $data = [] ) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
    private array $params = [];
    public function __construct( $method = '', $route = '' ) {}
    public function set_param( $key, $value ) { $this->params[ $key ] = $value; }
    public function get_param( $key ) { return $this->params[ $key ] ?? null; }
    public function get_json_params() { return $this->params; }
}
class WP_REST_Response {
    public function __construct( private $data, private int $status = 200 ) {}
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}
function add_action( ...$args ) {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function esc_url_raw( $value ) { return (string) $value; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_bloginfo( $key ) { return 'Test site'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['options'][ $key ] = $value; return true; }
function is_user_logged_in() { return true; }
function current_user_can( ...$args ) { return $args[0] !== 'edit_post' || ( $args[1] ?? 0 ) !== 99; }
function wpae_get_request_api_key( $request ) { return ''; }
function wp_generate_uuid4() { return 'abcd1234-abcd-1234-abcd-123456789012'; }
function wp_generate_password( ...$args ) { return 'test-operation'; }
function wpae_vision_decrypt_api_key( $value ) { return 'test-key'; }
function wpae_capability_enabled( $value ) { return true; }
function wpae_build_project_design_system() { return []; }
function wpae_get_project_design_tokens() { return [ 'palette' => [ 'ink' => '#111827' ] ]; }
function wpae_get_design_system_required_classes() { return [ 'wpae-system-test' ]; }
function wpae_get_design_system_id() { return 'test'; }
function wpae_get_elementor_data_for_post( $id ) { return $GLOBALS['page_data']; }
function wpae_block_library_retrieve_for_prompt( ...$args ) { return $GLOBALS['library']; }
function wpae_count_elementor_validation_errors_by_type( array $errors ) { return []; }
function wpae_elementor_update( $request ) {
    $contract = wpae_validate_design_system_contract( $request->get_param( 'elementor_data' ), [ 'allow_unchanged_legacy_top_level' => (array) ( $GLOBALS['page_data'] ?? [] ) ] );
    if ( ! $contract['ok'] ) {
        throw new RuntimeException( 'Real write contract failed: ' . wp_json_encode( $contract['errors'] ) );
    }
    if ( ! $request->get_param( 'dry_run' ) ) {
        $GLOBALS['writes'][] = $request->get_param( 'elementor_data' );
        $GLOBALS['page_data'] = $request->get_param( 'elementor_data' );
    }
    return new WP_REST_Response( [ 'ok' => true, 'rollback_snapshot_id' => 'snapshot' ] );
}
function wp_safe_remote_post( $url, $args ) {
    $GLOBALS['http_calls'][] = [ 'url' => $url, 'timeout' => $args['timeout'], 'body' => json_decode( $args['body'], true ) ];
    if ( empty( $GLOBALS['responses'] ) ) {
        throw new RuntimeException( 'Unexpected provider call' );
    }
    return array_shift( $GLOBALS['responses'] );
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_retrieve_header( $response, $name ) { return ''; }
function get_post( $id, $output = null ) { return [ 'ID' => $id, 'post_title' => $GLOBALS['post_title'] ?? 'Existing page' ]; }
function get_post_meta( $id, $key = '', $single = false ) {
    $meta = [ '_elementor_data' => [ wp_json_encode( $GLOBALS['page_data'] ) ], '_elementor_css' => [ $GLOBALS['css_cache'] ?? '' ] ];
    return $key === '' ? $meta : ( $single ? ( $meta[ $key ][0] ?? '' ) : ( $meta[ $key ] ?? [] ) );
}

require __DIR__ . '/../includes/llm/llm.php';
require __DIR__ . '/../includes/elementor/normalize.php';
require __DIR__ . '/../includes/elementor/design-contract.php';
require __DIR__ . '/../includes/elementor/validation.php';
require __DIR__ . '/../includes/rollback/rollback.php';

function check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    $GLOBALS['checks'] = ( $GLOBALS['checks'] ?? 0 ) + 1;
}
function widget( $id, $type, $settings ) { return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ]; }
function container_node( $id, $settings, $children ) { return [ 'id' => $id, 'elType' => 'container', 'settings' => $settings, 'elements' => $children ]; }
function provider_reply( $reply ) { return [ 'response' => [ 'code' => 200 ], 'body' => wp_json_encode( [ 'choices' => [ [ 'finish_reason' => 'stop', 'message' => [ 'content' => $reply ] ] ] ] ) ]; }

$hero = container_node( 'provider-hero', [ 'container_type' => 'flex', 'flex_direction' => 'row', 'background_color' => '#f4eee4', 'min_height' => [ 'unit' => 'vh', 'size' => 78 ], 'flex_gap' => [ 'unit' => 'rem', 'size' => 2 ] ], [
    container_node( 'copy-zone', [ 'width' => [ 'unit' => '%', 'size' => 54 ] ], [
        widget( 'title', 'heading', [ 'title' => 'Пространство для жизни', 'header_size' => 'h1', 'typography_font_size' => [ 'unit' => 'rem', 'size' => 5.5 ], 'title_color' => '#28251f' ] ),
        widget( 'copy', 'text-editor', [ 'editor' => '<p>Светлые интерьеры</p>', 'text_color' => '#514b42' ] ),
    ] ),
    container_node( 'visual-zone', [ 'width' => [ 'unit' => '%', 'size' => 40 ], 'background_color' => '#a84c36', 'border_radius' => [ 'unit' => 'rem', 'top' => '2', 'isLinked' => true ] ], [
        widget( 'visual-label', 'heading', [ 'title' => 'Архитектура повседневности', 'title_color' => '#ffffff' ] ),
    ] ),
] );
$message = 'Создай hero. Заголовок: «Пространство для жизни». Текст: «Светлые интерьеры». Надпись: «Архитектура повседневности».';
$explicit_cta_message = 'Создай новый hero для архитектурной студии «Тихая форма». Заголовок: «Пространство для вашей жизни». Текст: «Проектируем спокойные, светлые интерьеры с вниманием к каждой детали». Кнопка: «Обсудить проект», ссылка #contact. Выразительная асимметричная композиция из native Flexbox-контейнеров, крупная типографика, тёплый светлый фон и терракотовый акцент. Справа отдельный визуальный блок с надписью «Архитектура повседневности». Адаптируй для телефона.';
$explicit_cta_plan = wpae_llm_content_plan( $explicit_cta_message, 'hero' );
check( ! empty( $explicit_cta_plan['cta_required'] ) && in_array( 'Обсудить проект', (array) ( $explicit_cta_plan['explicit_cta'] ?? [] ), true ), 'Labeled quoted CTA was not added to the semantic content plan' );
$failure_diagnostics = wpae_llm_execution_failure_diagnostics( [
    'status' => 422,
    'update_error' => 'Elementor data failed design-system contract.',
    'details' => [
        'error' => 'Elementor data failed design-system contract.',
        'status' => 422,
        'details' => [
            'ok' => false,
            'errors' => [ 'Every new page/block top-level container must include design-system classes.' ],
            'warnings' => [ 'The composition uses a custom native palette.' ],
            'stats' => [ 'top_level_containers' => 2, 'design_system_marked_top_level_containers' => 1, 'native_color_hits' => 0, 'token_color_hits' => 0, 'mismatched_design_system_classes' => [ 'wpae-system-old' ] ],
        ],
    ],
] );
check( $failure_diagnostics['contract']['errors'][0] === 'Every new page/block top-level container must include design-system classes.', 'Design-system contract errors were not preserved in failure diagnostics' );
check( (int) ( $failure_diagnostics['contract']['stats']['top_level_containers'] ?? 0 ) === 2, 'Design-system contract stats were not preserved in failure diagnostics' );
$action = [ 'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'elements' => [ $hero ] ];
$existing = [ container_node( 'existing', [ 'container_type' => 'flex', '_css_classes' => 'wpae-system-test', 'padding' => [ 'unit' => 'px', 'top' => '7' ] ], [ widget( 'old-title', 'heading', [ 'title' => 'Existing content' ] ) ] ) ];
$GLOBALS['options'] = [ WPAE_LLM_SETTINGS_OPTION => [ 'provider' => 'openrouter', 'model' => 'openrouter/free' ] ];
$GLOBALS['library'] = [ 'status' => 'matched', 'selected' => [ 'title' => 'Unrelated stock template', 'elementor_data' => [ container_node( 'library', [], [ widget( 'stock', 'heading', [ 'title' => 'Stock copy' ] ) ] ) ] ] ];

foreach ( [ 'primary', 'repaired' ] as $scenario ) {
    $GLOBALS['page_data'] = $existing;
    $GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
    $GLOBALS['responses'] = $scenario === 'repaired' ? [ provider_reply( 'invalid JSON' ), provider_reply( wp_json_encode( $action ) ) ] : [ provider_reply( wp_json_encode( $action ) ) ];
    $request = new WP_REST_Request();
    $request->set_param( 'message', $message );
    $request->set_param( 'context', [ 'post_id' => 42 ] );
    $response = wpae_llm_chat_request( $request );
    check( $response instanceof WP_REST_Response, $scenario . ': ' . ( is_wp_error( $response ) ? $response->get_error_message() . ' ' . wp_json_encode( $response->get_error_data() ) : 'no response' ) );
    $data = $response->get_data();
    check( ! empty( $data['ok'] ), $scenario . ': write failed' );
    check( count( $GLOBALS['http_calls'] ) === ( $scenario === 'primary' ? 1 : 2 ), $scenario . ': wrong provider attempt count' );
    check( count( $GLOBALS['writes'] ) === 1, $scenario . ': duplicate or missing write' );
    check( $GLOBALS['page_data'][0] === $existing[0], $scenario . ': existing page mutated by append' );
    $saved = $GLOBALS['page_data'][1];
    check( $saved['settings']['background_color'] === '#f4eee4' && $saved['settings']['flex_direction'] === 'row', $scenario . ': provider palette/composition lost' );
    check( $saved['settings']['min_height']['size'] === 78, $scenario . ': hero geometry lost' );
    check( $saved['elements'][0]['elements'][0]['settings']['typography_font_size']['size'] === 5.5, $scenario . ': display typography overwritten' );
    check( $saved['elements'][0]['elements'][0]['settings']['typography_typography'] === 'custom', $scenario . ': native typography group remains disabled' );
    check( $saved['elements'][0]['elements'][0]['settings']['header_size'] === 'h1', $scenario . ': semantic heading overwritten' );
    check( $saved['elements'][0]['settings']['width']['size'] === 54 && $saved['elements'][1]['settings']['width']['size'] === 40, $scenario . ': asymmetry overwritten' );
    check( $saved['elements'][0]['settings']['width_mobile']['size'] === 100, $scenario . ': stacked column remains narrow' );
    check( $saved['elements'][0]['settings']['content_width'] === 'full', $scenario . ': nested container boxed' );
    check( $saved['elements'][0]['settings']['padding']['left'] === '0', $scenario . ': nested whitespace accumulated' );
    check( $saved['elements'][1]['settings']['border_radius']['right'] === '2', $scenario . ': linked dimension incomplete' );
    check( $saved['settings']['flex_gap']['column'] === '2', $scenario . ': native gap missing' );
    check( $data['library']['status'] !== 'applied', $scenario . ': library replaced accepted provider' );
    check( count( $saved['elements'] ) === 2, $scenario . ': structural wrappers inserted' );
    check( strpos( $GLOBALS['http_calls'][0]['body']['messages'][0]['content'], '3–5' ) === false, 'Prompt still caps composition to 3-5 widgets' );
}

$legacy_badge = container_node( 'legacy-badge', [
    '_css_classes' => 'wpae-generated-badge',
    'background_background' => 'classic',
    'background_color' => '#ffffff',
    'border_border' => 'solid',
    'border_color' => '#1f2937',
], [ widget( 'legacy-badge-label', 'heading', [ 'title' => 'ПРОЦЕСС' ] ) ] );
$legacy_page = [ $existing[0], $legacy_badge ];
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['library'] = [ 'status' => 'skipped', 'selected' => [] ];
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $action ) ) ];
$legacy_request = new WP_REST_Request();
$legacy_request->set_param( 'message', $message );
$legacy_request->set_param( 'context', [ 'post_id' => 42 ] );
$legacy_response = wpae_llm_chat_request( $legacy_request );
check( $legacy_response instanceof WP_REST_Response && ! empty( $legacy_response->get_data()['ok'] ), 'Unchanged legacy root blocked append generation' );
check( count( $GLOBALS['writes'] ) === 1, 'Legacy-root append did not write exactly once' );
check( $GLOBALS['page_data'][0] === $legacy_page[0] && $GLOBALS['page_data'][1] === $legacy_page[1], 'Unchanged legacy roots were rewritten' );
$legacy_saved = $GLOBALS['page_data'][2];
check( strpos( (string) ( $legacy_saved['settings']['_css_classes'] ?? '' ), 'wpae-system-test' ) !== false, 'New append root missed current design-system marker' );
$legacy_contract = wpae_validate_design_system_contract( array_merge( $legacy_page, [ $legacy_saved ] ), [ 'allow_unchanged_legacy_top_level' => $legacy_page ] );
check( $legacy_contract['ok'] && (int) $legacy_contract['stats']['unchanged_legacy_top_level_containers'] === 1, 'Unchanged legacy root allowance was not reported' );
$legacy_audit = wpae_build_repeated_agent_error_audit( array_merge( $legacy_page, [ $legacy_saved ] ), [ 'validated' ], [ 'html_widget_layout_risks' => 0 ], [ 'allow_unchanged_legacy_top_level' => $legacy_page ] );
check( $legacy_audit['ok'], 'Repeated preflight audit rejected the unchanged legacy root' );
$strict_legacy_audit = wpae_build_repeated_agent_error_audit( array_merge( $legacy_page, [ $legacy_saved ] ), [ 'validated' ], [ 'html_widget_layout_risks' => 0 ] );
check( ! $strict_legacy_audit['ok'], 'Strict audit accepted the unmarked legacy root without append context' );
$changed_legacy = $legacy_page;
$changed_legacy[1]['settings']['padding'] = [ 'unit' => 'rem', 'top' => '3' ];
check( ! wpae_validate_design_system_contract( array_merge( $changed_legacy, [ $legacy_saved ] ), [ 'allow_unchanged_legacy_top_level' => $legacy_page ] )['ok'], 'Changed unmarked legacy root bypassed contract' );

$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( 'not JSON' ), provider_reply( 'still not JSON' ), provider_reply( 'still not JSON' ) ];
$fallback_request = new WP_REST_Request();
$fallback_request->set_param( 'message', 'Создай hero. Заголовок: «Пространство для жизни». Текст: «Светлые интерьеры». Надпись: «Архитектура повседневности».' );
$fallback_request->set_param( 'context', [ 'post_id' => 42 ] );
$fallback_response = wpae_llm_chat_request( $fallback_request );
check( $fallback_response instanceof WP_REST_Response && ! empty( $fallback_response->get_data()['ok'] ), 'Deterministic fallback was blocked by the append contract' );
$fallback_data = $fallback_response->get_data();
check( ( $fallback_data['diagnostics']['action_path'] ?? '' ) === 'fallback', 'Fallback diagnostics lost action path' );
check( ! empty( $fallback_data['diagnostics']['initial_validation']['failed_checks'] ), 'Fallback diagnostics lost initial validation failures' );
check( count( (array) ( $fallback_data['diagnostics']['repair_attempts'] ?? [] ) ) === 2, 'Fallback diagnostics lost both bounded repair attempts' );
check( count( $GLOBALS['http_calls'] ) === 3, 'Fallback test did not use one primary plus two repair calls' );
$fallback_widget_types = [];
$collect_fallback_widget_types = static function ( array $nodes ) use ( &$collect_fallback_widget_types, &$fallback_widget_types ): void {
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        if ( ( $node['elType'] ?? '' ) === 'widget' ) {
            $type = (string) ( $node['widgetType'] ?? '' );
            $fallback_widget_types[ $type ] = (int) ( $fallback_widget_types[ $type ] ?? 0 ) + 1;
        }
        if ( is_array( $node['elements'] ?? null ) ) {
            $collect_fallback_widget_types( $node['elements'] );
        }
    }
};
$collect_fallback_widget_types( [ $GLOBALS['page_data'][2] ?? [] ] );
check( empty( $fallback_widget_types['divider'] ), 'Hero fallback inherited an unrelated process Divider' );
check( strpos( (string) wp_json_encode( $GLOBALS['page_data'][2] ?? [] ), 'wpae-process-' ) === false, 'Hero fallback inherited unrelated process semantics' );

$with_overrides = $hero;
check( WPAE_LLM_Design::is_complete( [ $hero ] ), 'Populated native composition rejected' );
check( ! WPAE_LLM_Design::is_complete( [ container_node( 'empty', [], [] ) ] ), 'Empty container accepted' );
check( ! WPAE_LLM_Design::is_complete( [ widget( 'empty-title', 'heading', [ 'title' => '<p> </p>' ] ) ] ), 'Empty heading accepted' );
check( ! WPAE_LLM_Design::is_complete( array_fill( 0, 81, widget( 'too-many', 'divider', [] ) ) ), 'Element budget not enforced' );
$with_overrides['settings']['flex_direction_mobile'] = 'row';
$with_overrides['settings']['gap_tablet'] = [ 'unit' => 'rem', 'size' => 0.75 ];
$with_overrides['elements'][0]['settings']['width_mobile'] = [ 'unit' => '%', 'size' => 60 ];
$with_overrides['elements'][0]['elements'][0]['settings']['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 3 ];
$changed = 0;
$normalized = WPAE_LLM_Design::normalize( [ $with_overrides ], $changed );
check( $normalized[0]['settings']['flex_direction_mobile'] === 'row', 'Explicit mobile layout lost' );
check( $normalized[0]['elements'][0]['settings']['width_mobile']['size'] === 60, 'Explicit mobile width lost' );
check( $normalized[0]['elements'][0]['elements'][0]['settings']['typography_font_size_mobile']['size'] === 3, 'Explicit mobile type lost' );
check( $normalized[0]['settings']['flex_gap_tablet']['column'] === '0.75', 'Tablet gap was not migrated' );
$again = WPAE_LLM_Design::normalize( $normalized, $changed );
check( $again === $normalized, 'Normalization is not idempotent' );
$custom_contract = wpae_validate_design_system_contract( $normalized );
check( $custom_contract['ok'] && $custom_contract['stats']['token_color_hits'] === 0, 'Explicit custom palette blocked at real write boundary' );
check( ! wpae_validate_design_system_contract( $existing )['ok'], 'Unstyled tree bypassed write contract' );
$missing_marker = $normalized;
unset( $missing_marker[0]['settings']['_css_classes'] );
check( ! wpae_validate_design_system_contract( $missing_marker )['ok'], 'Design marker enforcement lost' );

$GLOBALS['http_calls'] = [];
$budget_result = wpae_llm_provider_request( 'https://example.test', [ 'timeout' => 45 ], [ 'messages' => [] ], true, 'openrouter', microtime( true ) - 1 );
check( is_wp_error( $budget_result ) && $budget_result->get_error_code() === 'wpae_llm_provider_budget_exhausted', 'Expired budget accepted' );
check( count( $GLOBALS['http_calls'] ) === 0, 'Expired request still reached provider' );
$GLOBALS['responses'] = [ [ 'response' => [ 'code' => 400 ], 'body' => wp_json_encode( [ 'error' => [ 'message' => 'No endpoints found' ] ] ) ], provider_reply( '{}' ) ];
wpae_llm_provider_request( 'https://example.test', [ 'timeout' => 45 ], [ 'messages' => [], 'response_format' => [ 'type' => 'json_object' ] ], true, 'openrouter', microtime( true ) + 2 );
check( count( $GLOBALS['http_calls'] ) === 2 && max( array_column( $GLOBALS['http_calls'], 'timeout' ) ) <= 2, 'Schema retry exceeded shared deadline' );

$GLOBALS['options']['wp_ai_executor_rollback_snapshots'] = [ 'undo' => [ 'posts' => [ 42 => [ 'exists' => true ] ], 'expires_at_unix' => time() + 3600 ] ];
wpae_seal_rollback_snapshot( 'undo', 42 );
$hash = $GLOBALS['options']['wp_ai_executor_rollback_snapshots']['undo']['after_hashes'][42];
$GLOBALS['css_cache'] = 'rebuilt';
check( $hash === wpae_rollback_post_fingerprint( 42 ), 'Render-only cache refresh blocks undo' );
$GLOBALS['page_data'][] = container_node( 'later-user-change', [], [] );
$undo = new WP_REST_Request();
$undo->set_param( 'post_id', 42 );
$undo->set_param( 'rollback_snapshot_id', 'undo' );
$undo_response = wpae_llm_undo( $undo );
check( $undo_response->get_status() === 409 && $undo_response->get_data()['code'] === 'wpae_undo_conflict', 'Undo overwrote a later edit' );
check( isset( $GLOBALS['options']['wp_ai_executor_rollback_snapshots']['undo'] ), 'Conflict consumed rollback evidence' );

$permission = new WP_REST_Request();
$permission->set_param( 'post_id', 42 );
$permission->set_param( 'context', [ 'post_id' => 99 ] );
check( is_wp_error( wpae_llm_chat_permission( $permission ) ), 'Nested editor context bypassed post authorization' );
$permission->set_param( 'context', [ 'post_id' => 42 ] );
check( wpae_llm_chat_permission( $permission ) === true, 'Authorized editor blocked' );

echo 'flex generation runtime: ' . $GLOBALS['checks'] . " checks OK\n";
