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
function sanitize_textarea_field( $value ) { return trim( preg_replace( '/\r\n?/', "\n", strip_tags( (string) $value ) ) ); }
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
function get_post( $id, $output = null ) {
    $autosave = $GLOBALS['test_autosave'] ?? null;
    if ( $autosave && (int) ( $autosave->ID ?? 0 ) === (int) $id ) {
        return $autosave;
    }
    return [ 'ID' => $id, 'post_title' => $GLOBALS['post_title'] ?? 'Existing page' ];
}
function wp_get_post_autosave( $id, $owner_id = 0 ) {
    $autosave = $GLOBALS['test_autosave'] ?? null;
    if ( ! $autosave || (int) ( $autosave->post_parent ?? $id ) !== (int) $id ) {
        return null;
    }
    if ( $owner_id > 0 && (int) ( $autosave->post_author ?? 0 ) !== (int) $owner_id ) {
        return null;
    }
    return $autosave;
}
function wp_delete_post( $id, $force_delete = false ) {
    $GLOBALS['deleted_autosaves'][] = [ 'id' => (int) $id, 'force' => (bool) $force_delete ];
    if ( isset( $GLOBALS['test_autosave']->ID ) && (int) $GLOBALS['test_autosave']->ID === (int) $id ) {
        $GLOBALS['test_autosave'] = null;
    }
    return $GLOBALS['delete_autosave_result'] ?? (object) [ 'ID' => (int) $id ];
}
function get_post_meta( $id, $key = '', $single = false ) {
    $meta = [ '_elementor_data' => [ wp_json_encode( $GLOBALS['page_data'] ) ], '_elementor_css' => [ $GLOBALS['css_cache'] ?? '' ] ];
    return $key === '' ? $meta : ( $single ? ( $meta[ $key ][0] ?? '' ) : ( $meta[ $key ] ?? [] ) );
}

require __DIR__ . '/../includes/llm/llm.php';
require __DIR__ . '/../includes/elementor/normalize.php';
require __DIR__ . '/../includes/elementor/design-contract.php';
require __DIR__ . '/../includes/elementor/validation.php';
require __DIR__ . '/../includes/rollback/rollback.php';
require __DIR__ . '/../includes/elementor/transactions.php';

function check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    $GLOBALS['checks'] = ( $GLOBALS['checks'] ?? 0 ) + 1;
}

$GLOBALS['test_autosave'] = (object) [
    'ID' => 4975,
    'post_parent' => 4556,
    'post_author' => 10,
    'post_modified' => '2026-09-13 10:00:00',
    'post_content' => '{"elements":[]}',
];
$GLOBALS['deleted_autosaves'] = [];
$GLOBALS['delete_autosave_result'] = (object) [ 'ID' => 4975 ];
$autosave_report = wpae_clear_current_elementor_autosave( 4556 );
check( empty( $autosave_report['cleared'] ) && $autosave_report['status'] === 'owner_unknown', 'Autosave cleanup queried or deleted a draft without an explicit owner' );
check( count( $GLOBALS['deleted_autosaves'] ) === 0, 'Owner-unknown autosave cleanup performed a destructive delete' );
$autosave_snapshot = wpae_capture_elementor_autosave( 4556, 10 );
$autosave_report = wpae_clear_current_elementor_autosave( 4556, 10, $autosave_snapshot );
check( ! empty( $autosave_report['cleared'] ) && $autosave_report['status'] === 'cleared', 'Explicitly owned Elementor autosave was not cleared after a confirmed save' );
check( $autosave_report['autosave_id'] === 4975 && count( $GLOBALS['deleted_autosaves'] ) === 1, 'Autosave cleanup did not target the captured autosave exactly once' );
$GLOBALS['test_autosave'] = null;
$autosave_none_report = wpae_clear_current_elementor_autosave( 4556, 10 );
check( ! empty( $autosave_none_report['cleared'] ) && $autosave_none_report['status'] === 'none_at_start', 'Autosave cleanup did not accept a page without an autosave' );
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
$explicit_fallback_action = wpae_llm_build_fallback_action( $explicit_cta_message, 42 );
$explicit_fallback_changed = 0;
wpae_llm_remove_unrequested_buttons( $explicit_fallback_action['elements'], $explicit_cta_message, $explicit_fallback_changed );
$explicit_fallback_action['elements'] = wpae_llm_normalize_requested_cta( $explicit_fallback_action['elements'], $explicit_cta_message, $explicit_fallback_changed );
$explicit_fallback_json = (string) wp_json_encode( $explicit_fallback_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $explicit_fallback_json, '"widgetType":"button"' ) === 1, 'Single labeled CTA fallback lost its requested native button before semantic validation' );
check( strpos( $explicit_fallback_json, '"text":"Обсудить проект"' ) !== false && strpos( $explicit_fallback_json, '"url":"#contact"' ) !== false, 'Primary labeled CTA fallback lost its paired URL' );
$two_cta_fallback_message = 'Создай новый hero. Заголовок: «Пространство для вашей жизни». Текст: «Проектируем спокойные, светлые интерьеры». Основная кнопка: «Обсудить проект», ссылка #contact. Вторая кнопка: «Смотреть проекты», ссылка #projects. Надпись: «Архитектура повседневности».';
$two_cta_fallback_action = wpae_llm_build_fallback_action( $two_cta_fallback_message, 42 );
$two_cta_fallback_changed = 0;
wpae_llm_remove_unrequested_buttons( $two_cta_fallback_action['elements'], $two_cta_fallback_message, $two_cta_fallback_changed );
$two_cta_fallback_action['elements'] = wpae_llm_normalize_requested_cta( $two_cta_fallback_action['elements'], $two_cta_fallback_message, $two_cta_fallback_changed );
$two_cta_fallback_json = (string) wp_json_encode( $two_cta_fallback_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $two_cta_fallback_json, '"widgetType":"button"' ) === 2, 'Two labeled CTA fallback lost requested native buttons before semantic validation' );
check( strpos( $two_cta_fallback_json, '"text":"Смотреть проекты"' ) !== false && strpos( $two_cta_fallback_json, '"url":"#projects"' ) !== false, 'Secondary labeled CTA fallback lost its paired URL' );
$two_cta_hero_copy = wpae_llm_extract_hero_copy( $two_cta_fallback_message );
check( $two_cta_hero_copy['brand'] === '' && $two_cta_hero_copy['title'] === 'Пространство для вашей жизни' && $two_cta_hero_copy['body'] === 'Проектируем спокойные, светлые интерьеры', 'Hero copy extractor did not separate explicit title and body labels: ' . wp_json_encode( $two_cta_hero_copy, JSON_UNESCAPED_UNICODE ) );
check( $two_cta_hero_copy['visual'] === 'Архитектура повседневности', 'Hero copy extractor did not isolate the visual-panel label: ' . wp_json_encode( $two_cta_hero_copy, JSON_UNESCAPED_UNICODE ) );
$exact_hero_copy = wpae_llm_extract_hero_copy( $explicit_cta_message );
check( $exact_hero_copy['brand'] === 'Тихая форма' && $exact_hero_copy['title'] === 'Пространство для вашей жизни' && $exact_hero_copy['body'] === 'Проектируем спокойные, светлые интерьеры с вниманием к каждой детали', 'Full hero brief lost its brand, title, or body semantic fields' );
check( $exact_hero_copy['visual'] === 'Архитектура повседневности', 'Full hero brief lost its visual-panel copy' );
$two_cta_hero_changed = 0;
$two_cta_hero_elements = wpae_llm_normalize_hero_composition( $two_cta_fallback_action['elements'], $two_cta_hero_changed, $two_cta_fallback_message, true );
$two_cta_hero_buttons = [];
$collect_two_cta_hero_buttons = static function ( array $nodes ) use ( &$collect_two_cta_hero_buttons, &$two_cta_hero_buttons ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'button' ) {
			$two_cta_hero_buttons[] = $node;
		}
		if ( is_array( $node['elements'] ?? null ) ) {
			$collect_two_cta_hero_buttons( $node['elements'] );
		}
	}
};
$collect_two_cta_hero_buttons( $two_cta_hero_elements );
check( count( $two_cta_hero_buttons ) === 2, 'Trusted hero normalization collapsed two requested CTAs into one button' );
check( ( $two_cta_hero_buttons[0]['settings']['link']['url'] ?? '' ) === '#contact' && ( $two_cta_hero_buttons[1]['settings']['link']['url'] ?? '' ) === '#projects', 'Trusted hero normalization lost distinct CTA URLs' );
check( ( $two_cta_hero_buttons[0]['settings']['text'] ?? '' ) === 'Обсудить проект' && ( $two_cta_hero_buttons[1]['settings']['text'] ?? '' ) === 'Смотреть проекты', 'Trusted hero normalization lost distinct CTA labels' );
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

$content_only_hero_message = "Тихая форма\nПространство для вашей жизни\nПроектируем спокойные, светлые интерьеры с вниманием к каждой детали\nОбсудить проект — #contact\nСмотреть проекты — #projects\nАрхитектура повседневности";
check( wpae_llm_detect_block_archetype( $content_only_hero_message ) === 'hero', 'Content-only hero with two anchor CTAs was misclassified' );
$content_only_ctas = wpae_llm_extract_requested_ctas( $content_only_hero_message );
check( count( $content_only_ctas ) === 2 && $content_only_ctas[0]['text'] === 'Обсудить проект' && $content_only_ctas[0]['url'] === '#contact', 'Content-only CTA parser did not split primary label and URL' );
check( $content_only_ctas[1]['text'] === 'Смотреть проекты' && $content_only_ctas[1]['url'] === '#projects', 'Content-only CTA parser did not preserve secondary label and URL' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( 'not JSON' ), provider_reply( 'still not JSON' ), provider_reply( 'still not JSON' ) ];
$content_only_request = new WP_REST_Request();
$content_only_request->set_param( 'message', $content_only_hero_message );
$content_only_request->set_param( 'context', [ 'post_id' => 42 ] );
$content_only_response = wpae_llm_chat_request( $content_only_request );
check( $content_only_response instanceof WP_REST_Response && ! empty( $content_only_response->get_data()['ok'] ), 'Content-only hero fallback did not save' );
$content_only_saved = $GLOBALS['page_data'][2] ?? [];
$content_only_json = (string) wp_json_encode( $content_only_saved, JSON_UNESCAPED_UNICODE );
foreach ( [ 'Тихая форма', 'Пространство для вашей жизни', 'Проектируем спокойные, светлые интерьеры с вниманием к каждой детали', 'Архитектура повседневности', 'Обсудить проект', 'Смотреть проекты' ] as $required_copy ) {
	check( strpos( $content_only_json, $required_copy ) !== false, 'Content-only hero lost requested copy: ' . $required_copy );
}
check( strpos( $content_only_json, 'Обсудить проект — #contact' ) === false && strpos( $content_only_json, 'Смотреть проекты — #projects' ) === false, 'CTA URL leaked into visible content-only hero copy' );
check( strpos( $content_only_json, 'wpae-hero-visual-panel' ) !== false && strpos( $content_only_json, 'background_color":"#e7c7b7' ) !== false, 'Content-only hero did not create the separate visual panel' );
check( strpos( $content_only_json, 'wpae-generated-root' ) !== false, 'Generated hero root did not receive the operation ownership marker' );
$content_only_children = (array) ( $content_only_saved['elements'] ?? [] );
$content_only_badge_classes = preg_split( '/\s+/', trim( (string) ( $content_only_children[0]['settings']['_css_classes'] ?? '' ) ) );
$content_only_shell_classes = preg_split( '/\s+/', trim( (string) ( $content_only_children[1]['settings']['_css_classes'] ?? '' ) ) );
check( in_array( 'wpae-generated-badge', $content_only_badge_classes, true ) && in_array( 'wpae-hero-content-shell', $content_only_shell_classes, true ), 'Hero badge was left inside the horizontal content row instead of remaining a root-level control' );
check( ( $content_only_children[1]['settings']['flex_direction'] ?? '' ) === 'row' && ( $content_only_children[1]['settings']['flex_direction_mobile'] ?? '' ) === 'column', 'Hero content shell lost desktop row/mobile stack contract' );
check( count( array_filter( (array) ( $content_only_children[1]['elements'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) ) === 2, 'Hero content shell does not contain exactly two balanced visual zones' );
check( strpos( $content_only_json, '"background_color":"#a84c36' ) !== false, 'Content-only hero lost its authored terracotta CTA palette at the write boundary' );
check( strpos( $content_only_json, 'Создай новый hero' ) === false && strpos( $content_only_json, 'native Flexbox-контейнеров' ) === false, 'Hero fallback wrote technical prompt instructions into visible Elementor copy' );
check( substr_count( $content_only_json, '"widgetType":"button"' ) === 2 && strpos( $content_only_json, '"url":"#projects"' ) !== false, 'Content-only hero fallback did not save both native CTA buttons and URLs' );
check( strpos( $content_only_json, '"button_background_color"' ) === false, 'Content-only hero retained a non-native Button background key' );
check( strpos( $content_only_json, '"background_color":"#61ce70' ) === false, 'Content-only hero was remapped to the generic green design-system accent' );
$content_only_mobile_stack = false;
$find_content_only_mobile_stack = static function ( array $nodes ) use ( &$find_content_only_mobile_stack, &$content_only_mobile_stack ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$classes = preg_split( '/\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) );
		if ( is_array( $classes ) && in_array( 'wpae-hero-content-shell', $classes, true ) && ( $node['settings']['flex_direction_mobile'] ?? '' ) === 'column' ) {
			$content_only_mobile_stack = true;
		}
		if ( is_array( $node['elements'] ?? null ) ) {
			$find_content_only_mobile_stack( $node['elements'] );
		}
	}
};
$find_content_only_mobile_stack( [ $content_only_saved ] );
check( $content_only_mobile_stack, 'Content-only hero is not stacked on mobile' );
$owned_hero = $content_only_saved;
$owned_hero['id'] = 'owned-hero';
$GLOBALS['page_data'] = [ $owned_hero ];
$GLOBALS['writes'] = [];
$replace_execution = wpae_llm_execute_action( wpae_llm_build_fallback_action( $content_only_hero_message, 42 ), 42, 'hero', -1, $content_only_hero_message, false, [ 'replace_root_ids' => [ 'owned-hero' ] ] );
check( ! empty( $replace_execution['ok'] ) && count( $GLOBALS['page_data'] ) === 1 && ( $GLOBALS['page_data'][0]['id'] ?? '' ) === 'owned-hero', 'Vision-owned hero retry did not replace exactly one marked root' );
check( ( $replace_execution['editor_sync']['mode'] ?? '' ) === 'replace' && ( $replace_execution['editor_sync']['replace_element_id'] ?? '' ) === 'owned-hero' && ! empty( $replace_execution['diff']['changed'] ), 'Owned-root replacement did not expose replace sync or a changed read-back diff' );

$transport_benefits_message = "Почему нас выбирают\nТочная работа с пространством — Планируем каждый метр и сохраняем ощущение воздуха\nСвет и воздух — Работаем с естественным светом и спокойными материалами\nПорядок в деталях — Продумываем хранение, маршруты и ежедневные привычки";
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 58417 milliseconds' ) ];
$transport_fallback_request = new WP_REST_Request();
$transport_fallback_request->set_param( 'message', $transport_benefits_message );
$transport_fallback_request->set_param( 'context', [ 'post_id' => 42 ] );
$transport_fallback_response = wpae_llm_chat_request( $transport_fallback_request );
check( $transport_fallback_response instanceof WP_REST_Response && ! empty( $transport_fallback_response->get_data()['ok'] ), 'Provider transport failure did not reach deterministic fallback' );
$transport_fallback_data = $transport_fallback_response->get_data();
check( ( $transport_fallback_data['diagnostics']['action_path'] ?? '' ) === 'fallback', 'Transport fallback did not report fallback action path' );
check( strpos( (string) ( $transport_fallback_data['diagnostics']['command']['provider_transport_error'] ?? '' ), 'cURL error 28' ) !== false, 'Transport fallback lost sanitized provider error diagnostics' );
check( count( $GLOBALS['http_calls'] ) === 1 && count( $GLOBALS['writes'] ) === 1, 'Transport fallback made duplicate provider calls or writes' );
$transport_fallback_json = (string) wp_json_encode( $GLOBALS['page_data'][2] ?? [], JSON_UNESCAPED_UNICODE );
foreach ( [ 'Почему нас выбирают', 'Точная работа с пространством', 'Свет и воздух', 'Порядок в деталях' ] as $required_benefit_copy ) {
	check( strpos( $transport_fallback_json, $required_benefit_copy ) !== false, 'Transport fallback lost benefits content: ' . $required_benefit_copy );
}
foreach ( [ 'Планируем каждый метр и сохраняем ощущение воздуха', 'Работаем с естественным светом и спокойными материалами', 'Продумываем хранение, маршруты и ежедневные привычки' ] as $required_benefit_description ) {
	check( strpos( $transport_fallback_json, $required_benefit_description ) !== false, 'Transport fallback lost benefits description: ' . $required_benefit_description );
}
check( strpos( $transport_fallback_json, 'Точная работа с пространством — Планируем каждый метр и сохраняем ощущение воздуха' ) === false, 'Transport fallback wrote an unsplit benefits pair into a single widget' );

$provider_shorthand = [
    container_node( 'provider-shorthand', [
        'container_type' => 'flex',
        'layout' => [
            'flex_direction' => 'row',
            'align_items' => 'center',
            'gap' => [ 'unit' => 'rem', 'size' => 1.25 ],
        ],
        'background_color' => [ 'value' => '#f4eee4' ],
    ], [
        widget( 'shorthand-heading', 'heading', [
            'title' => 'Заголовок',
            'typography' => [
                'font_size' => [
                    'desktop' => [ 'unit' => 'rem', 'size' => 3.2 ],
                    'tablet' => [ 'unit' => 'rem', 'size' => 2.6 ],
                    'mobile' => [ 'unit' => 'rem', 'size' => 2.1 ],
                ],
                'font_weight' => '700',
                'line_height' => 1.1,
                'color' => [ 'value' => '#111827' ],
            ],
        ] ),
        widget( 'shorthand-button', 'button', [
            'text' => 'Открыть',
            'background_color' => [ 'value' => '#a84c36' ],
            'border' => [
                'width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
                'color' => [ 'value' => '#a84c36' ],
            ],
        ] ),
    ] ),
];
$provider_shorthand_normalized = wpae_elementor_normalize_data( $provider_shorthand )['data'];
$provider_shorthand_root = $provider_shorthand_normalized[0];
$provider_shorthand_heading = $provider_shorthand_root['elements'][0]['settings'];
$provider_shorthand_button = $provider_shorthand_root['elements'][1]['settings'];
check( $provider_shorthand_root['settings']['background_color'] === '#f4eee4', 'Provider background wrapper was not converted to a native scalar color' );
check( $provider_shorthand_root['settings']['flex_align_items'] === 'center' && $provider_shorthand_root['settings']['flex_gap']['size'] === '1.25', 'Provider layout shorthand was not flattened to native Flexbox controls' );
check( $provider_shorthand_heading['typography_font_size_mobile']['size'] === 2.1 && $provider_shorthand_heading['title_color'] === '#111827', 'Provider typography shorthand was not flattened to responsive native controls' );
check( $provider_shorthand_button['background_color'] === '#a84c36' && $provider_shorthand_button['border_color'] === '#a84c36' && $provider_shorthand_button['border_border'] === 'solid', 'Provider button visual shorthand was not converted to native Elementor controls' );
check( ! array_key_exists( 'typography', $provider_shorthand_heading ) && ! array_key_exists( 'border', $provider_shorthand_button ), 'Non-native provider visual wrappers leaked into normalized settings' );

$provider_responsive = [
    container_node( 'provider-responsive', [
        'container_type' => 'flex',
        'flex_direction' => [ 'desktop' => 'row', 'tablet' => 'row', 'mobile' => 'column' ],
        'flex_wrap' => [ 'desktop' => 'nowrap', 'mobile' => 'wrap' ],
    ], [ widget( 'responsive-copy', 'text-editor', [ 'editor' => 'Контент' ] ) ] ),
];
$provider_responsive_normalized = wpae_elementor_normalize_data( $provider_responsive )['data'][0]['settings'];
check( $provider_responsive_normalized['flex_direction'] === 'row' && $provider_responsive_normalized['flex_direction_tablet'] === 'row' && $provider_responsive_normalized['flex_direction_mobile'] === 'column', 'Responsive provider Flex direction object was not converted to native Elementor keys' );
check( $provider_responsive_normalized['flex_wrap'] === 'nowrap' && $provider_responsive_normalized['flex_wrap_mobile'] === 'wrap', 'Responsive provider Flex wrap object was not converted to native Elementor keys' );

$pricing_provider_without_shell = [
    container_node( 'pricing-provider-without-shell', [ 'container_type' => 'flex' ], [
        container_node( 'pricing-provider-card-1', [], [ widget( 'pricing-provider-title-1', 'heading', [ 'title' => 'Старт' ] ) ] ),
        container_node( 'pricing-provider-card-2', [], [ widget( 'pricing-provider-title-2', 'heading', [ 'title' => 'Проект' ] ) ] ),
        container_node( 'pricing-provider-card-3', [], [ widget( 'pricing-provider-title-3', 'heading', [ 'title' => 'Полное сопровождение' ] ) ] ),
    ] ),
];
$pricing_provider_quality = wpae_llm_provider_composition_quality( "Тарифы\nСтарт — 30 000 ₸\nПроект — 150 000 ₸\nПолное сопровождение — 300 000 ₸", $pricing_provider_without_shell, 'pricing' );
check( empty( $pricing_provider_quality['ok'] ) && in_array( 'pricing provider lacks a native repeatable card grid and section heading', (array) $pricing_provider_quality['failures'], true ), 'Sparse pricing provider tree passed without a native grid/section shell' );
$pricing_visual_changed = 0;
$pricing_visual = wpae_llm_normalize_native_visual_contract( [ container_node( 'pricing-visual-root', [ 'container_type' => 'flex' ], [ widget( 'pricing-visual-button', 'button', [ 'text' => 'Выбрать', 'background_background' => 'gradient', 'background_color_b' => '#f2295b', 'button_background_hover_color' => '#3348B8' ] ) ] ) ], 'Тарифы. Используй терракотовые акценты.', 'pricing', $pricing_visual_changed );
$pricing_visual_button = $pricing_visual[0]['elements'][0]['settings'];
check( $pricing_visual_button['background_background'] === 'classic' && $pricing_visual_button['background_color'] === '#a84c36' && $pricing_visual_button['button_background_hover_color'] === '#8f3e2c', 'Requested terracotta direction did not reach native button controls' );
check( $pricing_visual_button['button_text_color'] === '#ffffff' && $pricing_visual_changed > 0, 'Provider button native visual contract did not fill readable text color' );

$poor_content_only_provider = [
    container_node( 'poor-provider', [ 'container_type' => 'flex' ], [
        widget( 'poor-heading', 'heading', [ 'title' => 'Тихая форма' ] ),
        widget( 'poor-copy', 'text-editor', [ 'editor' => '<p>Пространство для вашей жизни</p><p>Проектируем спокойные, светлые интерьеры с вниманием к каждой детали</p><p>Архитектура повседневности</p>' ] ),
        widget( 'poor-cta-1', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
        widget( 'poor-cta-2', 'button', [ 'text' => 'Смотреть проекты', 'link' => [ 'url' => '#projects' ] ] ),
    ] ),
];
$poor_quality = wpae_llm_provider_composition_quality( $content_only_hero_message, $poor_content_only_provider, 'hero' );
check( empty( $poor_quality['ok'] ), 'Sparse content-only provider tree passed the composition quality gate' );
check( ! in_array( 'required generated badge is missing', (array) $poor_quality['failures'], true ) && in_array( 'hero brand/title/visual hierarchy is incomplete', (array) $poor_quality['failures'], true ), 'Composition quality diagnostics incorrectly required a badge instead of reporting the sparse hero hierarchy' );
$fallback_quality = wpae_llm_provider_composition_quality( $content_only_hero_message, (array) ( wpae_llm_build_fallback_action( $content_only_hero_message, 42 )['elements'] ?? [] ), 'hero' );
check( ! empty( $fallback_quality['ok'] ), 'Deterministic content-complete hero fallback failed its own composition quality gate' );

$cta_provider_without_badge = [
    widget( 'cta-title', 'heading', [ 'title' => 'Обсудим ваш проект' ] ),
    widget( 'cta-copy', 'text-editor', [ 'editor' => 'Опишите задачу, а мы предложим следующий шаг.' ] ),
    widget( 'cta-button', 'button', [ 'text' => 'Записаться на консультацию', 'link' => [ 'url' => '#booking' ] ] ),
];
$cta_quality = wpae_llm_provider_composition_quality( "Обсудим ваш проект\nОпишите задачу, а мы предложим следующий шаг.\nЗаписаться на консультацию — #booking", $cta_provider_without_badge, 'cta' );
check( ! empty( $cta_quality['ok'] ) && empty( $cta_quality['counts']['has_badge'] ), 'A semantically complete CTA provider composition without a badge was rejected' );

$benefits_message = "Почему нас выбирают\nТочная работа с пространством — Планируем каждый метр и сохраняем ощущение воздуха\nСвет и воздух — Работаем с естественным светом и спокойными материалами\nПорядок в деталях — Продумываем хранение, маршруты и ежедневные привычки";
$benefits_action = wpae_llm_build_fallback_action( $benefits_message, 42 );
$benefits_cards = [];
$collect_benefits_cards = static function ( array $nodes ) use ( &$collect_benefits_cards, &$benefits_cards ): void {
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        if ( ( $node['elType'] ?? '' ) === 'container' && strpos( (string) ( $node['id'] ?? '' ), 'llm-benefit-' ) === 0 && preg_match( '/^llm-benefit-[1-9][0-9]*$/', (string) $node['id'] ) ) {
            $headings = [];
            $copy = [];
            foreach ( (array) ( $node['elements'] ?? [] ) as $child ) {
                if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' ) {
                    continue;
                }
                if ( ( $child['widgetType'] ?? '' ) === 'heading' ) {
                    $headings[] = (string) ( $child['settings']['title'] ?? '' );
                } elseif ( ( $child['widgetType'] ?? '' ) === 'text-editor' ) {
                    $copy[] = (string) ( $child['settings']['editor'] ?? '' );
                }
            }
            $benefits_cards[] = [ 'headings' => $headings, 'copy' => $copy ];
        }
        if ( is_array( $node['elements'] ?? null ) ) {
            $collect_benefits_cards( $node['elements'] );
        }
    }
};
$collect_benefits_cards( (array) ( $benefits_action['elements'] ?? [] ) );
check( count( $benefits_cards ) === 3, 'Benefits fallback did not create one native card per labeled content pair' );
check( ( $benefits_cards[0]['headings'][0] ?? '' ) === 'Точная работа с пространством' && ( $benefits_cards[1]['headings'][0] ?? '' ) === 'Свет и воздух' && ( $benefits_cards[2]['headings'][0] ?? '' ) === 'Порядок в деталях', 'Benefits fallback merged the dash-separated title and description' );
check( ( $benefits_cards[0]['copy'][0] ?? '' ) === 'Планируем каждый метр и сохраняем ощущение воздуха' && ( $benefits_cards[1]['copy'][0] ?? '' ) === 'Работаем с естественным светом и спокойными материалами' && ( $benefits_cards[2]['copy'][0] ?? '' ) === 'Продумываем хранение, маршруты и ежедневные привычки', 'Benefits fallback shifted or duplicated card descriptions' );
$benefits_fidelity = wpae_llm_content_fidelity( $benefits_message, (array) ( $benefits_action['elements'] ?? [] ) );
check( ! empty( $benefits_fidelity['ok'] ), 'Benefits content fidelity treated already-split label/description pairs as missing full-line copy' );
$benefits_requested = wpae_llm_extract_requested_content( $benefits_message );
check( ! in_array( 'Точная работа с пространством — Планируем каждый метр и сохраняем ощущение воздуха', $benefits_requested, true ), 'Benefits requested-content extraction retained a redundant unsplit pair line' );

$faq_message = 'Создай FAQ «Частые вопросы». «Как начать?» — «Оставьте заявку, и мы согласуем встречу». «Можно работать дистанционно?» — «Да, обсуждения и согласования проводим онлайн». «Что входит в проект?» — «Планировка, концепция и согласованный комплект материалов». Сохрани точные вопросы, ответы и порядок. Адаптируй блок для телефона.';
$faq_pairs = wpae_llm_extract_faq_content( $faq_message );
check( count( $faq_pairs ) === 3, 'FAQ parser did not extract three quoted question/answer pairs' );
check( ( $faq_pairs[0]['label'] ?? '' ) === 'Как начать' && ( $faq_pairs[0]['content'] ?? '' ) === 'Оставьте заявку, и мы согласуем встречу', 'FAQ parser did not strip punctuation while preserving the first pair' );
check( ( $faq_pairs[2]['label'] ?? '' ) === 'Что входит в проект' && ( $faq_pairs[2]['content'] ?? '' ) === 'Планировка, концепция и согласованный комплект материалов', 'FAQ parser changed the last pair or its order' );
$faq_requested = wpae_llm_extract_requested_content( $faq_message );
check( in_array( 'Частые вопросы', $faq_requested, true ) && in_array( 'Как начать', $faq_requested, true ) && in_array( 'Оставьте заявку, и мы согласуем встречу', $faq_requested, true ), 'FAQ requested-content extraction lost the title or first pair' );
check( ! in_array( 'Сохрани точные вопросы, ответы и порядок', $faq_requested, true ) && ! in_array( '«Как начать?»', $faq_requested, true ), 'FAQ requested-content extraction retained instruction or quoted duplicate content' );
$faq_plan = wpae_llm_content_plan( $faq_message, 'faq' );
check( count( $faq_plan['content_pairs'] ?? [] ) === 3 && ( $faq_plan['content_pairs'][1]['label'] ?? '' ) === 'Можно работать дистанционно', 'FAQ semantic plan did not use the question/answer parser' );
check( empty( $faq_plan['explicit_cta'] ?? [] ) && empty( $faq_plan['cta_required'] ), 'FAQ semantic plan inferred a CTA from ordinary answer copy' );
$faq_action = wpae_llm_build_fallback_action( $faq_message, 42 );
$faq_ctas = wpae_llm_extract_requested_ctas( $faq_message );
check( empty( $faq_ctas ), 'FAQ answer text was incorrectly inferred as an explicit CTA requirement' );
$faq_cleanup_changed = 0;
wpae_llm_remove_unrequested_buttons( $faq_action['elements'], $faq_message, $faq_cleanup_changed );
$faq_json = (string) wp_json_encode( $faq_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $faq_json, '"widgetType":"button"' ) === 0, 'FAQ fallback retained an unrequested CTA button from ordinary answer copy' );
check( strpos( $faq_json, 'Как начать' ) !== false && strpos( $faq_json, 'Оставьте заявку, и мы согласуем встречу' ) !== false, 'FAQ fallback lost the first exact question or answer' );
check( strpos( $faq_json, 'Можно работать дистанционно' ) !== false && strpos( $faq_json, 'Да, обсуждения и согласования проводим онлайн' ) !== false, 'FAQ fallback lost the second exact question or answer' );
check( strpos( $faq_json, 'Что входит в проект' ) !== false && strpos( $faq_json, 'Планировка, концепция и согласованный комплект материалов' ) !== false, 'FAQ fallback lost the third exact question or answer' );
check( empty( wpae_llm_content_fidelity( $faq_message, $faq_action['elements'] )['missing'] ), 'FAQ fallback failed final content fidelity' );

$portfolio_message = 'Создай блок «Наши проекты» с тремя работами: «Квартира у парка» — «Светлый интерьер для семьи»; «Дом у озера» — «Природные материалы и открытые пространства»; «Городская студия» — «Компактная планировка для одного человека». Сохрани три работы, точные описания и порядок. Используй редактируемые элементы Elementor. Не выдумывай фотографии выполненных проектов. Адаптируй для телефона.';
$portfolio_pairs = wpae_llm_extract_labeled_content( $portfolio_message );
check( count( $portfolio_pairs ) === 3, 'Portfolio parser did not extract three quoted title/description pairs' );
check( ( $portfolio_pairs[0]['label'] ?? '' ) === 'Квартира у парка' && ( $portfolio_pairs[0]['content'] ?? '' ) === 'Светлый интерьер для семьи', 'Portfolio parser changed the first exact pair' );
check( ( $portfolio_pairs[2]['label'] ?? '' ) === 'Городская студия' && ( $portfolio_pairs[2]['content'] ?? '' ) === 'Компактная планировка для одного человека', 'Portfolio parser retained instruction text in the last description' );
$portfolio_requested = wpae_llm_extract_requested_content( $portfolio_message );
check( $portfolio_requested === [ 'Наши проекты', 'Квартира у парка', 'Светлый интерьер для семьи', 'Дом у озера', 'Природные материалы и открытые пространства', 'Городская студия', 'Компактная планировка для одного человека' ], 'Portfolio requested-content extraction did not return the title and six clean pair fields in order' );
check( ! in_array( 'Сохрани три работы, точные описания и порядок', $portfolio_requested, true ) && ! in_array( 'Используй редактируемые элементы Elementor', $portfolio_requested, true ), 'Portfolio requested-content extraction retained a technical instruction' );
$portfolio_action = wpae_llm_build_fallback_action( $portfolio_message, 42 );
$portfolio_built_json = (string) wp_json_encode( $portfolio_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $portfolio_built_json, '"title":"Наши проекты"' ) !== false, 'Fallback builder did not return the explicit Portfolio section title' );
check( strpos( $portfolio_built_json, '"widgetType":"button"' ) === false, 'Fallback builder leaked an unrequested Portfolio CTA' );
check( strpos( $portfolio_built_json, 'Сохрани три работы' ) === false && strpos( $portfolio_built_json, 'Используй редактируемые элементы Elementor' ) === false, 'Fallback builder leaked Portfolio instructions' );
$portfolio_built_fidelity = wpae_llm_content_fidelity( $portfolio_message, $portfolio_action['elements'] );
check( ! empty( $portfolio_built_fidelity['ok'] ), 'Fallback builder did not return a content-complete Portfolio tree' );
$portfolio_repair_changed = 0;
$portfolio_repaired = wpae_llm_repair_unbalanced_repeatable_layout( $portfolio_action['elements'], $portfolio_message, 'portfolio', $portfolio_repair_changed );
$portfolio_repaired_json = (string) wp_json_encode( $portfolio_repaired, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $portfolio_repaired_json, 'Создай блок «Наши проекты»' ) === false, 'Repeatable-layout repair promoted the full Portfolio instruction to a heading' );
check( strpos( $portfolio_repaired_json, '"title":"Наши проекты"' ) !== false, 'Repeatable-layout repair did not preserve the explicit Portfolio heading' );
$portfolio_changed = 0;
wpae_llm_apply_fallback_archetype_content( $portfolio_action['elements'], $portfolio_message, 'portfolio', $portfolio_changed );
$portfolio_fidelity = wpae_llm_content_fidelity( $portfolio_message, $portfolio_action['elements'] );
$portfolio_missing = (array) ( $portfolio_fidelity['missing'] ?? [] );
if ( ! empty( $portfolio_missing ) ) {
	wpae_llm_apply_fallback_content( $portfolio_action['elements'], $portfolio_missing, 'portfolio', $portfolio_changed );
}
$portfolio_fidelity = wpae_llm_content_fidelity( $portfolio_message, $portfolio_action['elements'] );
check( ! empty( $portfolio_fidelity['ok'] ), 'Portfolio fallback failed final content fidelity after pair normalization' );
$portfolio_json = (string) wp_json_encode( $portfolio_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $portfolio_json, '"title":"Наши проекты"' ) !== false, 'Portfolio fallback did not place the explicit section title in a native Heading' );
check( strpos( $portfolio_json, 'Сохрани три работы' ) === false && strpos( $portfolio_json, 'Используй редактируемые элементы Elementor' ) === false, 'Portfolio fallback leaked prompt instructions into native widgets' );
check( strpos( $portfolio_json, 'Квартира у парка' ) !== false && strpos( $portfolio_json, 'Компактная планировка для одного человека' ) !== false, 'Portfolio fallback lost exact project content' );
$portfolio_layout_changed = 0;
$portfolio_layout = wpae_llm_normalize_generated_typography( $portfolio_action['elements'], 'portfolio', 0, $portfolio_layout_changed );
$portfolio_bento_changed = 0;
$portfolio_layout = wpae_llm_apply_bento_layout( $portfolio_layout, 'portfolio', $portfolio_bento_changed );
$portfolio_repair_layout_changed = 0;
$portfolio_layout = wpae_llm_repair_unbalanced_repeatable_layout( $portfolio_layout, $portfolio_message, 'portfolio', $portfolio_repair_layout_changed );
$portfolio_grammar_changed = 0;
$portfolio_layout = wpae_llm_apply_generation_visual_grammar( $portfolio_layout, 'portfolio', $portfolio_grammar_changed );
$portfolio_nested_card_grids = 0;
$collect_portfolio_card_grids = static function ( array $nodes, bool $inside_grid = false ) use ( &$collect_portfolio_card_grids, &$portfolio_nested_card_grids ): void {
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
        $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
        $is_grid = is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true );
        $child_containers = array_filter( (array) ( $node['elements'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' );
        if ( $inside_grid && $is_grid && count( $child_containers ) > 0 ) {
            $portfolio_nested_card_grids++;
        }
        if ( is_array( $node['elements'] ?? null ) ) {
            $collect_portfolio_card_grids( $node['elements'], $inside_grid || $is_grid );
        }
    }
};
$collect_portfolio_card_grids( $portfolio_layout );
check( $portfolio_nested_card_grids === 0, 'Repeatable card layout nested icon/heading/copy into extra bento containers' );

$wrong_provider_action = $action;
$wrong_provider_action['elements'][0]['elements'][0]['elements'][0]['settings']['title'] = 'Нерелевантный заголовок';
$wrong_provider_action['elements'][0]['elements'][0]['elements'][1]['settings']['editor'] = 'Нерелевантное описание';
$wrong_provider_action['elements'][0]['elements'][1]['elements'][0]['settings']['title'] = 'Нерелевантная визуальная подпись';
$wrong_provider_action['elements'][0]['elements'][2]['settings']['text'] = 'Нерелевантная кнопка';
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $wrong_provider_action ) ), provider_reply( wp_json_encode( $wrong_provider_action ) ), provider_reply( wp_json_encode( $wrong_provider_action ) ) ];
$wrong_provider_request = new WP_REST_Request();
$wrong_provider_request->set_param( 'message', $content_only_hero_message );
$wrong_provider_request->set_param( 'context', [ 'post_id' => 42 ] );
$wrong_provider_response = wpae_llm_chat_request( $wrong_provider_request );
check( $wrong_provider_response instanceof WP_REST_Response && ! empty( $wrong_provider_response->get_data()['ok'] ), 'Content-mismatched provider tree was not recovered safely' );
$wrong_provider_data = $wrong_provider_response->get_data();
check( ( $wrong_provider_data['diagnostics']['action_path'] ?? '' ) === 'fallback', 'Content-mismatched provider tree did not report fallback path' );
$wrong_provider_json = (string) wp_json_encode( $GLOBALS['page_data'][2] ?? [], JSON_UNESCAPED_UNICODE );
foreach ( [ 'Тихая форма', 'Пространство для вашей жизни', 'Проектируем спокойные, светлые интерьеры с вниманием к каждой детали', 'Архитектура повседневности', 'Обсудить проект', 'Смотреть проекты' ] as $required_copy ) {
	check( strpos( $wrong_provider_json, $required_copy ) !== false, 'Content-mismatched provider recovery lost requested copy: ' . $required_copy );
}
check( strpos( $wrong_provider_json, 'Нерелевантный заголовок' ) === false, 'Content-mismatched provider copy leaked into the saved fallback' );

$pricing_content_only_message = "Тарифы\nСтарт — 30 000 ₸ — Одна консультация для ясного первого шага\nПроект — 150 000 ₸ — Планировка и концепция для вашего пространства\nПолное сопровождение — 300 000 ₸ — Проект и авторский надзор до результата\nВыбрать Старт — #contact\nВыбрать Проект — #contact\nВыбрать Полное сопровождение — #contact";
$pricing_content_only_action = wpae_llm_build_fallback_action( $pricing_content_only_message, 42 );
$pricing_fallback_changed = 0;
wpae_llm_apply_fallback_archetype_content( $pricing_content_only_action['elements'], $pricing_content_only_message, 'pricing', $pricing_fallback_changed );
$pricing_visual_changed = 0;
$pricing_content_only_action['elements'] = wpae_llm_apply_generation_visual_grammar( $pricing_content_only_action['elements'], 'pricing', $pricing_visual_changed );
$pricing_cta_changed = 0;
$pricing_content_only_action['elements'] = wpae_llm_normalize_requested_cta( $pricing_content_only_action['elements'], $pricing_content_only_message, $pricing_cta_changed );
$pricing_contract_changed = 0;
$pricing_layout = wpae_llm_build_pricing_pair_layout( $pricing_content_only_action['elements'], wpae_llm_extract_pricing_content( $pricing_content_only_message ), $pricing_contract_changed, wpae_llm_extract_requested_ctas( $pricing_content_only_message ) );
$pricing_content_only_action['elements'] = $pricing_layout;
// The final pricing contract rebuild must not discard CTAs normalized before it.
$pricing_cta_after_contract_changed = 0;
$pricing_content_only_action['elements'] = wpae_llm_normalize_requested_cta( $pricing_content_only_action['elements'], $pricing_content_only_message, $pricing_cta_after_contract_changed );
$pricing_content_only_json = (string) wp_json_encode( $pricing_content_only_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $pricing_content_only_json, '"widgetType":"button"' ) === 3, 'Pricing contract rebuild discarded content-only CTA buttons' );
check( strpos( $pricing_content_only_json, '"text":"Выбрать Полное сопровождение"' ) !== false && strpos( $pricing_content_only_json, '"url":"#contact"' ) !== false, 'Pricing content-only CTA text or URL was lost after the final contract' );
check( strpos( $pricing_content_only_json, '"title":"30 000 ₸"' ) !== false && strpos( $pricing_content_only_json, '"editor":"Одна консультация для ясного первого шага"' ) !== false, 'Pricing parser did not split em-dash price content into native price and description fields' );
check( strpos( $pricing_content_only_json, '"background_color":"#4460EC"' ) !== false && strpos( $pricing_content_only_json, '"background_color":"#61ce70"' ) === false, 'Generated pricing buttons inherited the unrelated global green instead of a native palette' );
check( ! empty( wpae_llm_content_fidelity( $pricing_content_only_message, $pricing_content_only_action['elements'] )['ok'] ), 'Pricing content-only fallback failed final content fidelity after CTA reapplication' );
$pricing_card_buttons = 0;
$pricing_walk = static function ( array $nodes ) use ( &$pricing_walk, &$pricing_card_buttons ): void {
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        $classes = preg_split( '/\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) );
        if ( ( $node['elType'] ?? '' ) === 'container' && is_array( $classes ) && in_array( 'wpae-pricing-card', $classes, true ) ) {
            foreach ( (array) ( $node['elements'] ?? [] ) as $child ) {
                if ( is_array( $child ) && ( $child['widgetType'] ?? '' ) === 'button' ) {
                    $pricing_card_buttons++;
                }
            }
        }
        $pricing_walk( (array) ( $node['elements'] ?? [] ) );
    }
};
$pricing_walk( $pricing_content_only_action['elements'] );
check( $pricing_card_buttons === 3, 'Pricing CTA buttons are not nested inside their native pricing cards' );

$quoted_pricing_message = "Создай блок «Тарифы» с тремя карточками:\n«Старт» — «Одна консультация» — «30 000 ₸»;\n«Проект» — «Планировка и концепция» — «150 000 ₸»;\n«Полное сопровождение» — «Проект и авторский надзор» — «300 000 ₸».\nВ каждой карточке отдельная кнопка:\n«Выбрать Старт» → #start,\n«Выбрать Проект» → #project,\n«Выбрать сопровождение» → #support.\nИспользуй светлый фон и терракотовые акценты. На телефоне расположи карточки вертикально.";
$quoted_pricing_pairs = wpae_llm_extract_pricing_content( $quoted_pricing_message );
check( count( $quoted_pricing_pairs ) === 3 && $quoted_pricing_pairs[0]['label'] === 'Старт' && $quoted_pricing_pairs[0]['content'] === '30 000 ₸ — Одна консультация', 'Quoted pricing content-only parser did not preserve label, price, and description order' );
check( wpae_llm_detect_block_archetype( $quoted_pricing_message ) === 'pricing', 'Quoted pricing request was misclassified as a hero after CTA extraction' );
$quoted_pricing_ctas = wpae_llm_extract_requested_ctas( $quoted_pricing_message );
check( count( $quoted_pricing_ctas ) === 3 && $quoted_pricing_ctas[0]['text'] === 'Выбрать Старт' && $quoted_pricing_ctas[0]['url'] === '#start' && $quoted_pricing_ctas[2]['url'] === '#support', 'Arrow CTA parser did not preserve all pricing labels and targets' );
$quoted_pricing_action = wpae_llm_build_fallback_action( $quoted_pricing_message, 42 );
check( ! empty( wpae_llm_content_fidelity( $quoted_pricing_message, $quoted_pricing_action['elements'] )['ok'] ), 'Quoted pricing fallback failed content fidelity' );
$quoted_pricing_json = (string) wp_json_encode( $quoted_pricing_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $quoted_pricing_json, '"widgetType":"button"' ) === 3 && strpos( $quoted_pricing_json, '"url":"#start"' ) !== false && strpos( $quoted_pricing_json, '"url":"#support"' ) !== false, 'Quoted pricing fallback did not build three native CTA buttons with exact targets' );

$reference_timeline = wpae_llm_build_process_timeline(
    [
        [ 'label' => 'Замысел', 'content' => 'Этап 1: Замысел.' ],
        [ 'label' => 'Съёмка', 'content' => 'Этап 2: Съёмка.' ],
        [ 'label' => 'Монтаж', 'content' => 'Этап 3: Монтаж.' ],
        [ 'label' => 'Публикация', 'content' => 'Этап 4: Публикация.' ],
    ],
    'reference-process',
    'horizontal'
);
$reference_settings = $reference_timeline['settings'];
check( $reference_settings['flex_direction'] === 'column' && $reference_settings['flex_direction_mobile'] === 'column', 'Horizontal reference timeline header shell is not responsive column layout' );
check( $reference_settings['flex_wrap'] === 'nowrap' && $reference_settings['flex_wrap_mobile'] === 'nowrap', 'Horizontal reference timeline can wrap unexpectedly' );
check( $reference_settings['padding']['unit'] === 'rem' && $reference_settings['padding']['top'] === '1.25' && $reference_settings['padding']['right'] === '1.25' && $reference_settings['padding']['bottom'] === '1.5' && $reference_settings['padding']['left'] === '1.25', 'Horizontal main container lost the reference inner padding' );
check( $reference_settings['padding_mobile']['unit'] === 'rem' && $reference_settings['padding_mobile']['top'] === '1' && $reference_settings['padding_mobile']['right'] === '0.875' && $reference_settings['padding_mobile']['bottom'] === '1.25' && $reference_settings['padding_mobile']['left'] === '0.875', 'Horizontal main container lost responsive mobile inner padding' );
$reference_badge = $reference_timeline['elements'][0] ?? [];
$reference_heading = $reference_timeline['elements'][1] ?? [];
$reference_items = $reference_timeline['elements'][2] ?? [];
check( ( $reference_badge['settings']['_css_classes'] ?? '' ) === 'wpae-generated-badge' && ( $reference_badge['elements'][0]['settings']['title'] ?? '' ) === 'ПРОЦЕСС', 'Horizontal reference timeline is missing the process badge' );
check( ( $reference_heading['widgetType'] ?? '' ) === 'heading' && ( $reference_heading['settings']['_css_classes'] ?? '' ) === 'wpae-process-heading' && ( $reference_heading['settings']['title'] ?? '' ) === 'Как мы работаем', 'Horizontal reference timeline is missing its section heading' );
check( ( $reference_items['settings']['_css_classes'] ?? '' ) === 'wpae-process-items' && $reference_items['settings']['flex_direction'] === 'row' && $reference_items['settings']['flex_direction_mobile'] === 'column', 'Horizontal reference timeline items row is not responsive' );
check( count( $reference_items['elements'] ?? [] ) === 4, 'Horizontal reference timeline does not have four direct cards in its items row' );
check( strpos( (string) wp_json_encode( $reference_timeline ), 'wpae-process-track' ) === false, 'Horizontal reference timeline retained the shared track wrapper' );
check( strpos( (string) wp_json_encode( $reference_timeline ), 'wpae-process-rail' ) === false, 'Horizontal reference timeline retained the shared rail wrapper' );
check( strpos( (string) wp_json_encode( $reference_timeline ), 'wpae-process-cards' ) === false, 'Horizontal reference timeline retained the shared cards wrapper' );
$reference_dividers = 0;
foreach ( $reference_items['elements'] as $reference_index => $reference_card ) {
    $card_settings = $reference_card['settings'];
    $card_classes = preg_split( '/\s+/', trim( (string) ( $card_settings['_css_classes'] ?? '' ) ) );
    check( in_array( 'wpae-process-content', $card_classes, true ), 'Reference timeline child is not a process-content card' );
    check( $card_settings['border_radius']['unit'] === 'px' && $card_settings['border_radius']['top'] === '20' && $card_settings['border_radius']['right'] === '20' && $card_settings['border_radius']['bottom'] === '20' && $card_settings['border_radius']['left'] === '20', 'Reference card radius does not match the supplied JSON' );
    check( (float) $card_settings['width']['size'] === 22.0 && (float) $card_settings['width_mobile']['size'] === 100.0, 'Reference card width is not 22% desktop / 100% mobile' );
    check( count( $reference_card['elements'] ) === 3, 'Reference card child order/count does not match marker-row, heading, copy' );
    $marker_row = $reference_card['elements'][0];
    check( $marker_row['elType'] === 'container' && $marker_row['settings']['flex_direction'] === 'row' && ( $marker_row['settings']['padding']['top'] ?? null ) === '0', 'Reference marker row is not a native zero-padding Flex row' );
    check( ( $reference_card['elements'][1]['widgetType'] ?? '' ) === 'heading' && ( $reference_card['elements'][2]['widgetType'] ?? '' ) === 'text-editor', 'Reference card heading/text-editor order changed' );
    if ( $reference_index < 3 ) {
        $divider = $marker_row['elements'][1] ?? [];
        check( ( $divider['widgetType'] ?? '' ) === 'divider', 'Reference connector is not a native Divider widget' );
        check( ( $divider['settings']['weight']['size'] ?? null ) === 1 && ( $divider['settings']['gap']['size'] ?? null ) === 15, 'Reference Divider geometry does not match the supplied JSON' );
        check( ( $divider['settings']['color'] ?? '' ) === '#000' && ( $divider['settings']['width']['size'] ?? null ) === 100, 'Reference Divider styling/width does not match the supplied JSON' );
        $reference_dividers++;
    }
}
check( $reference_dividers === 3, 'Horizontal reference timeline must have three native connectors' );
$legacy_horizontal_root = container_node( 'legacy-horizontal-root', [ 'container_type' => 'flex' ], [
    container_node( 'legacy-horizontal-track', [ '_css_classes' => 'wpae-process-track' ], [
        container_node( 'legacy-horizontal-rail', [ '_css_classes' => 'wpae-process-rail' ], [] ),
        container_node( 'legacy-horizontal-cards', [ '_css_classes' => 'wpae-process-cards' ], [] ),
    ] ),
] );
check( wpae_llm_is_process_timeline_root( $legacy_horizontal_root ), 'Legacy horizontal root without its marker class was not recognised' );
$legacy_direct_root = $reference_timeline;
$legacy_direct_root['id'] = 'legacy-direct-root';
$legacy_direct_root['settings']['_css_classes'] = '';
$legacy_direct_root['settings']['flex_direction'] = 'row';
$legacy_direct_root['settings']['flex_direction_mobile'] = 'column';
$legacy_direct_root['elements'] = (array) ( $reference_timeline['elements'][2]['elements'] ?? [] );
check( wpae_llm_is_process_timeline_root( $legacy_direct_root ), 'Legacy horizontal root with direct process-content cards was not recognised' );
$detailed_process_message = 'Обнови выбранный горизонтальный таймлайн строго по эталонной карточке: повтори структуру для «Замысел», «Съёмка», «Монтаж», «Публикация».';
$detailed_steps = wpae_llm_process_timeline_steps( $detailed_process_message );
check( array_column( $detailed_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Quoted labels in a detailed reference prompt were not preserved' );
$content_only_process_message = 'Создай горизонтальный блок «Как мы работаем» с бейджем «ПРОЦЕСС». Четыре этапа строго в таком порядке: «Замысел», «Съёмка», «Монтаж», «Публикация». Между этапами используй native Divider, на телефоне расположи карточки вертикально.';
$content_only_process_steps = wpae_llm_process_timeline_steps( $content_only_process_message );
check( array_column( $content_only_process_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Quoted process shell labels were incorrectly promoted to timeline cards' );
$content_only_process_timeline = wpae_llm_build_process_timeline( $content_only_process_steps, 'content-only-process', 'horizontal', 'Как мы работаем' );
$content_only_process_json = wp_json_encode( $content_only_process_timeline );
check( substr_count( (string) $content_only_process_json, '"wpae-process-content"' ) === 4 && substr_count( (string) $content_only_process_json, '"widgetType":"divider"' ) === 3, 'Content-only process prompt did not produce four cards and three native dividers' );
check( wpae_llm_is_targeted_edit_request( 'Обнови выбранный горизонтальный таймлайн: добавь стандартный нативный бейдж «ПРОЦЕСС» и секционный заголовок.' ), 'Selected process shell addition was misclassified as a new block' );
check( ! wpae_llm_is_targeted_edit_request( 'Создай новый горизонтальный таймлайн: Замысел, Съёмка, Монтаж, Публикация.' ), 'New process generation was incorrectly classified as a targeted edit' );
$rebuilt_steps = wpae_llm_process_timeline_steps_from_elements( [ $reference_timeline ] );
check( array_column( $rebuilt_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Process step reader did not ignore the horizontal badge/heading shell' );
$contract_changed = 0;
$contract_timeline = wpae_llm_enforce_process_timeline_contract( [ $reference_timeline ], $detailed_process_message, $contract_changed )[0] ?? [];
$contract_json = wp_json_encode( $contract_timeline );
check( substr_count( (string) $contract_json, '"wpae-generated-badge"' ) === 1 && substr_count( (string) $contract_json, '"wpae-process-heading"' ) === 1, 'Process contract duplicated or dropped the horizontal badge/heading shell' );
$repair_root = $reference_timeline;
$repair_root['id'] = 'repair-root';
$repair_root['settings']['_css_classes'] = 'wpae-system-test';
$GLOBALS['page_data'] = [ $repair_root ];
$GLOBALS['writes'] = [];
$repair_result = wpae_llm_execute_process_timeline_repair( $GLOBALS['page_data'], 42, [ 'repair-root' ], $detailed_process_message, 'repair-operation' );
check( ! empty( $repair_result['ok'] ), 'Process timeline repair did not complete for a direct horizontal root' );
check( ( $repair_result['editor_sync']['after_top_level_ids'] ?? [] ) === [ 'repair-root' ], 'Process repair did not expose the saved top-level IDs for editor reconciliation' );
check( substr_count( (string) wp_json_encode( $repair_result['editor_sync']['elements'][0] ?? [] ), '"wpae-generated-badge"' ) === 1, 'Process repair editor sync lost the native badge' );
check( substr_count( (string) wp_json_encode( $repair_result['editor_sync']['elements'][0] ?? [] ), '"wpae-process-heading"' ) === 1, 'Process repair editor sync lost the section heading' );
$vertical_timeline = wpae_llm_build_process_timeline(
    [ [ 'label' => 'Вертикальный', 'content' => 'Не менять.' ], [ 'label' => 'Шаг', 'content' => 'Сохраняем.' ] ],
    'vertical-process',
    'left'
);
$vertical_first_classes = preg_split( '/\s+/', trim( (string) ( $vertical_timeline['elements'][0]['elements'][0]['settings']['_css_classes'] ?? '' ) ) );
check( in_array( 'wpae-process-marker-column', $vertical_first_classes, true ), 'Vertical timeline marker-column variant was changed by horizontal reference work' );
check( in_array( 'wpae-process-content', preg_split( '/\s+/', trim( (string) ( $vertical_timeline['elements'][0]['elements'][1]['settings']['_css_classes'] ?? '' ) ) ), true ), 'Vertical timeline content card was changed by horizontal reference work' );

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
