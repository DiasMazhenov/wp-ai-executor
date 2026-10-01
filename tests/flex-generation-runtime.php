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
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function delete_post_meta( $id, $key ) {
    if ( $key === '_elementor_data' ) { $GLOBALS['page_data'] = []; }
    if ( $key === '_elementor_css' ) { unset( $GLOBALS['css_cache'] ); }
    return true;
}
function add_post_meta( $id, $key, $value ) {
    if ( $key === '_elementor_data' ) {
        $decoded = json_decode( (string) $value, true );
        if ( is_array( $decoded ) ) { $GLOBALS['page_data'] = $decoded; }
    }
    return true;
}
function update_post_meta( $id, $key, $value ) {
    if ( $key === '_elementor_data' ) {
        $decoded = json_decode( (string) $value, true );
        if ( is_array( $decoded ) ) { $GLOBALS['page_data'] = $decoded; }
    }
    return true;
}
function wp_slash( $value ) { return $value; }
function wp_update_post( $post, $wp_error = false ) { return (int) ( $post['ID'] ?? 42 ); }
function do_action( ...$args ) {}
function is_user_logged_in() { return true; }
function current_user_can( ...$args ) { return $args[0] !== 'edit_post' || ( $args[1] ?? 0 ) !== 99; }
function wpae_get_request_api_key( $request ) { return ''; }
function wp_generate_uuid4() { return 'abcd1234-abcd-1234-abcd-123456789012'; }
function wp_generate_password( ...$args ) { return 'test-operation'; }
function wpae_vision_decrypt_api_key( $value ) { return 'test-key'; }
function wpae_capability_enabled( $value ) { return true; }
function wpae_build_project_design_system() { return []; }
function wpae_get_project_design_tokens() {
    $tokens = [ 'palette' => [ 'ink' => '#111827' ] ];
    if ( ! empty( $GLOBALS['test_project_typography_tokens'] ) ) {
        $tokens['native_tokens']['typography'] = [
            'display' => [ 'font_family' => 'inherit', 'desktop' => '3.5rem', 'tablet' => '2.75rem', 'mobile' => '2.25rem', 'weight' => '700', 'line_height' => '1.05' ],
            'subheading' => [ 'font_family' => 'inherit', 'desktop' => '2rem', 'tablet' => '1.75rem', 'mobile' => '1.5rem', 'weight' => '600', 'line_height' => '1.2' ],
            'body' => [ 'font_family' => 'inherit', 'desktop' => '1rem', 'tablet' => '1rem', 'mobile' => '1rem', 'weight' => '400', 'line_height' => '1.5' ],
            'utility' => [ 'font_family' => 'inherit', 'desktop' => '0.875rem', 'tablet' => '0.875rem', 'mobile' => '0.875rem', 'weight' => '500', 'line_height' => '1.4' ],
        ];
    }
    return $tokens;
}
function wpae_get_design_system_required_classes() { return [ 'wpae-system-test' ]; }
function wpae_get_design_system_id() { return 'test'; }
function wpae_block_library_retrieve_for_prompt( ...$args ) {
	$GLOBALS['library_retrieval_calls'][] = $args;
	$library = $GLOBALS['library'];
	if ( empty( $args[2] ) ) {
		unset( $library['preflight_candidates'] );
	}
	return $library;
}
function wpae_count_elementor_validation_errors_by_type( array $errors ) { return []; }
function wpae_elementor_update( $request ) {
    $contract = wpae_validate_design_system_contract( $request->get_param( 'elementor_data' ), [ 'allow_unchanged_legacy_top_level' => (array) ( $GLOBALS['page_data'] ?? [] ) ] );
    if ( ! $contract['ok'] ) {
        throw new RuntimeException( 'Real write contract failed: ' . wp_json_encode( $contract['errors'] ) );
    }
    if ( ! empty( $GLOBALS['fail_write_boundary'] ) ) {
        $GLOBALS['fail_write_boundary'] = false;
        return new WP_Error( 'wpae_test_write_failed', 'simulated write boundary failure' );
    }
    if ( ! $request->get_param( 'dry_run' ) ) {
        $GLOBALS['writes'][] = $request->get_param( 'elementor_data' );
        $GLOBALS['page_data'] = $request->get_param( 'elementor_data' );
    }
    return new WP_REST_Response( [ 'ok' => true, 'rollback_snapshot_id' => 'snapshot' ] );
}
function wpae_elementor_patch( $request ) {
    $existing = $GLOBALS['page_data'];
    $expected_before_hash = (string) $request->get_param( 'expected_before_hash' );
    if ( $expected_before_hash !== '' && ! hash_equals( $expected_before_hash, hash( 'sha256', (string) wp_json_encode( $existing ) ) ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'code' => 'wpae_patch_before_hash_mismatch' ], 409 );
    }
    $patched = wpae_apply_elementor_patches( $existing, (array) $request->get_param( 'patches' ) );
    if ( ! empty( $patched['report']['errors'] ) || ! empty( $patched['report']['missing_element_ids'] ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'patch_report' => $patched['report'] ], 422 );
    }
    if ( $request->get_param( 'dry_run' ) ) {
        return new WP_REST_Response( [ 'ok' => true, 'dry_run' => true, 'elementor_data' => $patched['data'], 'patch_report' => $patched['report'] ], 200 );
    }
    $operation_id = sanitize_key( (string) $request->get_param( 'operation_id' ) );
    $snapshot = wpae_create_rollback_snapshot( 'elementor_patch:' . (int) $request->get_param( 'post_id' ), [ (int) $request->get_param( 'post_id' ) ], [], [], [
        'operation_id' => $operation_id,
        'operation_identity' => (string) $request->get_param( 'operation_identity' ),
        'root_ids' => (array) $request->get_param( 'operation_root_ids' ),
    ] );
    if ( empty( $snapshot['id'] ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'rollback_snapshot_failed' ], 500 );
    }
    $GLOBALS['page_data'] = $patched['data'];
    $GLOBALS['writes'][] = $patched['data'];
    wpae_seal_rollback_snapshot( (string) $snapshot['id'], (int) $request->get_param( 'post_id' ) );
    return new WP_REST_Response( [ 'ok' => true, 'rollback_snapshot_id' => $snapshot['id'], 'rollback_expires_at' => $snapshot['expires_at'], 'patch_report' => $patched['report'] ], 200 );
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
require __DIR__ . '/../includes/elementor/validation-rules.php';
require __DIR__ . '/../includes/elementor/data.php';
require __DIR__ . '/../includes/elementor/normalize.php';
require __DIR__ . '/../includes/elementor/design-contract.php';
require __DIR__ . '/../includes/elementor/validation.php';
require __DIR__ . '/../includes/rollback/rollback.php';
require __DIR__ . '/../includes/elementor/transactions.php';

function check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    $GLOBALS['checks'] = ( $GLOBALS['checks'] ?? 0 ) + 1;
}

$native_controls = container_node( 'native-control-regression', [
	'container_type' => 'flex',
	'background_overlay_opacity' => 0.55,
	'border_radius' => [ 'unit' => 'px', 'size' => 16, 'sizes' => [] ],
	'image_border_radius' => [ 'unit' => 'px', 'size' => 12, 'sizes' => [] ],
	'border_radius_mobile' => [ 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => true ],
], [] );
$native_controls_normalized = wpae_elementor_normalize_data( [ $native_controls ] )['data'][0]['settings'];
check( ( $native_controls_normalized['background_overlay_opacity']['size'] ?? null ) === 0.55 && ( $native_controls_normalized['border_radius']['right'] ?? '' ) === '16' && ( $native_controls_normalized['image_border_radius']['bottom'] ?? '' ) === '12' && ( $native_controls_normalized['border_radius_mobile']['top'] ?? '' ) === '20', 'native opacity/dimensions normalize by control schema and preserve an explicit responsive override' );
check( wpae_elementor_native_control_error( 'background_overlay_opacity', [ 'unit' => 'px', 'size' => '', 'sizes' => [] ] ) === '', 'an empty native opacity slider remains an unset control instead of rejecting an otherwise valid imported template' );
$native_controls_patch = wpae_apply_elementor_patches( [ $native_controls ], [
	[ 'element_id' => 'native-control-regression', 'path' => 'settings.background_overlay_opacity', 'op' => 'set', 'value' => 0.55 ],
	[ 'element_id' => 'native-control-regression', 'path' => 'settings.border_radius', 'op' => 'set', 'value' => 24 ],
	[ 'element_id' => 'native-control-regression', 'path' => 'settings.image_border_radius', 'op' => 'set', 'value' => [ 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '0', 'left' => '0', 'isLinked' => false ] ],
] );
check( empty( $native_controls_patch['report']['errors'] ) && ( $native_controls_patch['data'][0]['settings']['background_overlay_opacity']['size'] ?? null ) === 0.55 && ( $native_controls_patch['data'][0]['settings']['border_radius']['left'] ?? '' ) === '24' && ( $native_controls_patch['data'][0]['settings']['image_border_radius']['bottom'] ?? '' ) === '0', 'targeted patches accept native opacity and both uniform and per-side radius controls' );
$invalid_opacity_patch = wpae_apply_elementor_patches( [ $native_controls ], [ [ 'element_id' => 'native-control-regression', 'path' => 'settings.background_overlay_opacity', 'op' => 'set', 'value' => 55 ] ] );
$invalid_radius_patch = wpae_apply_elementor_patches( [ $native_controls ], [ [ 'element_id' => 'native-control-regression', 'path' => 'settings.border_radius', 'op' => 'set', 'value' => [ 'unit' => 'px', 'top' => [], 'right' => '1', 'bottom' => '1', 'left' => '1' ] ] ] );
check( ! empty( $invalid_opacity_patch['report']['errors'] ) && ( $invalid_opacity_patch['data'][0]['settings']['background_overlay_opacity'] ?? null ) === 0.55 && ! empty( $invalid_radius_patch['report']['errors'] ) && ( $invalid_radius_patch['data'][0]['settings']['border_radius']['top'] ?? '' ) === '', 'ambiguous native opacity and malformed radius are rejected without Arraypx coercion or partial mutation' );
$accordion_patch_source = widget( 'accordion-patch-regression', 'accordion', [ 'tabs' => [ [ 'tab_title' => 'Old question', 'tab_content' => 'Old answer' ], [ 'tab_title' => 'Keep this question', 'tab_content' => 'Keep this answer' ] ] ] );
$accordion_patch = wpae_apply_elementor_patches( [ $accordion_patch_source ], [ [ 'element_id' => 'accordion-patch-regression', 'path' => 'settings.tabs[0].tab_title', 'op' => 'set', 'value' => 'New question' ], [ 'element_id' => 'accordion-patch-regression', 'path' => 'settings.tabs[0].tab_content', 'op' => 'set', 'value' => 'New answer' ] ] );
check( empty( $accordion_patch['report']['errors'] ) && ( $accordion_patch['data'][0]['settings']['tabs'][0]['tab_title'] ?? '' ) === 'New question' && ( $accordion_patch['data'][0]['settings']['tabs'][0]['tab_content'] ?? '' ) === 'New answer' && ( $accordion_patch['data'][0]['settings']['tabs'][1]['tab_title'] ?? '' ) === 'Keep this question', 'targeted Accordion patches update only explicit native tab title/content paths' );
$unsafe_accordion_patches = wpae_apply_elementor_patches( [ $accordion_patch_source ], [ [ 'element_id' => 'accordion-patch-regression', 'path' => 'settings.tabs[2].tab_title', 'op' => 'set', 'value' => 'No new tab' ], [ 'element_id' => 'accordion-patch-regression', 'path' => 'settings.tabs[0].tab_title', 'op' => 'delete' ] ] );
check( count( $unsafe_accordion_patches['report']['errors'] ) === 2 && ( $unsafe_accordion_patches['data'][0]['settings']['tabs'][0]['tab_title'] ?? '' ) === 'Old question', 'Accordion patch rejects missing tabs and delete operations without changing the source' );

$library_selection_fixture = [
	'selection_candidates' => [
		[ 'choice_key' => 'candidate_1', 'title' => 'Шаблон A', 'description' => 'Карточки команды с портретом и должностью.', 'structure' => [ [ 'container' => 'row', 'children' => [ 'image', 'heading', 'text-editor' ] ] ], 'elementor_data' => [ [ 'id' => 'tree-a' ] ] ],
		[ 'choice_key' => 'candidate_2', 'title' => 'Шаблон B', 'description' => 'Список FAQ в аккордеоне.', 'structure' => [ [ 'container' => 'column', 'children' => [ 'heading', 'accordion' ] ] ], 'elementor_data' => [ [ 'id' => 'tree-b' ] ] ],
	],
];
$library_choice = wpae_llm_resolve_library_choice( $library_selection_fixture, 'candidate_2' );
check( ! empty( $library_choice['ok'] ) && ( $library_choice['selected']['title'] ?? '' ) === 'Шаблон B' && ( $library_choice['selected']['elementor_data'][0]['id'] ?? '' ) === 'tree-b', 'Agent library choice did not resolve to the matching allowlisted template tree' );
$library_invalid_choice = wpae_llm_resolve_library_choice( $library_selection_fixture, 'template-999' );
check( empty( $library_invalid_choice['ok'] ) && ( $library_invalid_choice['source'] ?? '' ) === 'invalid_model_choice' && empty( $library_invalid_choice['selected'] ), 'Agent library choice accepted an unlisted template key' );
$library_choice_prompt = wpae_llm_library_decision_prompt( $library_selection_fixture );
check( strpos( $library_choice_prompt, 'candidate_2' ) !== false && strpos( $library_choice_prompt, 'tree-b' ) === false, 'Library decision prompt failed to expose bounded choices without raw Elementor JSON' );
check( strpos( $library_choice_prompt, 'Карточки команды' ) !== false && strpos( $library_choice_prompt, '"container":"row"' ) !== false && strpos( $library_choice_prompt, 'Выбери ровно один лучший подходящий choice_key' ) !== false, 'Library choice prompt provides compact candidate semantics and layout structure so the model can compare templates instead of guessing from titles alone' );
check( strpos( $library_choice_prompt, 'elements: []' ) !== false && strpos( $library_choice_prompt, 'явно откажись от записи' ) !== false && strpos( $library_choice_prompt, 'Не заменяй отказ собственной native-композицией' ) !== false, 'Library decision prompt requires a no-write refusal instead of native fallback when offered templates do not fit' );
$library_only_envelope = wpae_llm_validate_library_choice_action( [ 'action' => 'insert_elements', 'post_id' => 42, 'library_choice' => 'candidate_2', 'elements' => [] ], 42, $library_selection_fixture );
$library_wrong_target_envelope = wpae_llm_validate_library_choice_action( [ 'action' => 'insert_elements', 'post_id' => 99, 'library_choice' => 'candidate_2', 'elements' => [] ], 42, $library_selection_fixture );
check( ! empty( $library_only_envelope['ok'] ) && empty( $library_wrong_target_envelope['ok'] ), 'Library-only choice accepts an allowlisted candidate but still enforces the target post' );
$services_library_prompt = wpae_llm_library_decision_prompt( [
	'selection_candidates' => [
		[ 'choice_key' => 'candidate_1', 'category' => 'services', 'template_type' => 'section-services', 'media_reference_count' => 0 ],
	],
] );
check( strpos( $services_library_prompt, 'native Image widgets' ) !== false && strpos( $services_library_prompt, 'Unsplash' ) !== false && strpos( $services_library_prompt, 'не отклоняй иначе подходящую структуру карточек' ) !== false, 'Services library decisions know the production compiler can add licensed native Unsplash images when the source template has none' );
$decoded_library_choice = wpae_llm_decode_action( wp_json_encode( [ 'action' => 'insert_elements', 'library_choice' => 'candidate_2', 'elements' => [] ] ), 42 );
check( ( $decoded_library_choice['library_choice'] ?? '' ) === 'candidate_2', 'Action decoder dropped the bounded agent library decision' );

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

$unsolicited_team_photo = 'https://images.unsplash.com/photo-random-person?auto=format&fit=crop&w=800&q=80';
$team_image_changes = 0;
$team_without_requested_photo = wpae_llm_normalize_native_visual_contract( [ container_node( 'team-root', [], [
	widget( 'team-face', 'image', [ 'image' => [ 'url' => $unsolicited_team_photo, 'id' => 0, 'source' => 'url' ] ] ),
	widget( 'team-name', 'heading', [ 'title' => 'Алия Садыкова' ] ),
] ) ], 'Блок команды: Алия Садыкова.', 'team', $team_image_changes );
check( count( $team_without_requested_photo[0]['elements'] ?? [] ) === 1 && ( $team_without_requested_photo[0]['elements'][0]['settings']['title'] ?? '' ) === 'Алия Садыкова' && $team_image_changes > 0, 'Team generation removes an unrequested stock portrait while preserving the person copy' );
$requested_team_photo = 'https://example.test/media/aliya.png';
$team_explicit_image_changes = 0;
$team_with_requested_photo = wpae_llm_normalize_native_visual_contract( [ container_node( 'team-explicit-root', [], [ widget( 'team-face-explicit', 'image', [ 'image' => [ 'url' => $requested_team_photo, 'id' => 0, 'source' => 'url' ] ] ) ] ) ], 'Команда. Участник 1 — фото: ' . $requested_team_photo, 'team', $team_explicit_image_changes );
check( ( $team_with_requested_photo[0]['elements'][0]['settings']['image']['url'] ?? '' ) === $requested_team_photo, 'Team generation keeps an image explicitly supplied for the actual named person' );
$process_background_changes = 0;
$process_without_stock_background = wpae_llm_normalize_native_visual_contract( [ container_node( 'process-root', [ 'background_image' => [ 'url' => $unsolicited_team_photo, 'id' => 0 ] ], [ widget( 'process-step', 'heading', [ 'title' => 'Заявка' ] ) ] ) ], 'Процесс: этапы работы.', 'process', $process_background_changes );
check( ! isset( $process_without_stock_background[0]['settings']['background_image'] ) && $process_background_changes > 0, 'Process generation strips an unrequested decorative stock photo from its background' );
$services_catalog_image = wpae_design_plan_default_service_media()[0]['source_url'];
$service_image_changes = 0;
$service_with_catalog_image = wpae_llm_normalize_native_visual_contract( [ container_node( 'service-root', [], [ widget( 'service-photo', 'image', [ 'image' => [ 'url' => $services_catalog_image, 'id' => 0, 'source' => 'url' ] ] ) ] ) ], 'Блок услуг.', 'services', $service_image_changes );
check( ( $service_with_catalog_image[0]['elements'][0]['settings']['image']['url'] ?? '' ) === $services_catalog_image, 'Services may use the plugin curated Unsplash image catalog without a URL in the prompt' );
$hero_default_brief = wpae_brief_ir_parse( 'Hero архитектурной студии. Заголовок: «Пространство для идей».' );
$hero_default_image = wpae_design_plan_default_hero_media( $hero_default_brief );
$hero_default_image_changes = 0;
$hero_with_default_image = wpae_llm_normalize_native_visual_contract( [ container_node( 'architecture-hero-root', [], [ widget( 'architecture-hero-photo', 'image', [ 'image' => [ 'url' => $hero_default_image['source_url'], 'id' => 0, 'source' => 'url' ] ] ) ] ) ], 'Hero архитектурной студии. Заголовок: «Пространство для идей».', 'hero', $hero_default_image_changes );
check( ( $hero_with_default_image[0]['elements'][0]['settings']['image']['url'] ?? '' ) === $hero_default_image['source_url'], 'Contextual architecture Hero keeps its plugin selected Unsplash image' );
$hero_no_photo_changes = 0;
$hero_no_photo = wpae_llm_normalize_native_visual_contract( [ container_node( 'architecture-hero-no-photo-root', [], [ widget( 'architecture-hero-no-photo', 'image', [ 'image' => [ 'url' => $hero_default_image['source_url'], 'id' => 0, 'source' => 'url' ] ] ) ] ) ], 'Hero архитектурной студии без фото. Заголовок: «Пространство для идей».', 'hero', $hero_no_photo_changes );
check( empty( $hero_no_photo[0]['elements'] ) && $hero_no_photo_changes > 0, 'Explicit no-photo instruction overrides contextual Hero imagery' );

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
$process_without_media_plan = wpae_llm_content_plan( 'Создай процесс из этапов «Заявка» и «Старт». Не добавляй изображения, фото или медиа.', 'process' );
check( empty( $process_without_media_plan['requires_media'] ), 'A negated media mention must not become a required-image fidelity gate' );
$required_media_content_plan = wpae_llm_content_plan( 'Create a hero with an image required. Title: "Room for ideas".', 'hero' );
check( ! empty( $required_media_content_plan['requires_media'] ), 'An explicit required image remains enforced by content fidelity' );
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
$natural_hero_message = 'Создай на пустой странице hero-блок для архитектурной студии «Тихая форма». Используй эти точные тексты: надзаголовок «АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ», заголовок «Пространство для вашей жизни», описание «Проектируем спокойные, светлые интерьеры с вниманием к каждой детали». Основная кнопка «Обсудить проект» со ссылкой #contact; вторичная «Смотреть проекты» со ссылкой #projects. Сделай композицию 40/60, выравнивание по центру и адаптацию для телефона.';
$natural_ctas = wpae_llm_extract_requested_ctas( $natural_hero_message );
check( count( $natural_ctas ) === 2 && ( $natural_ctas[0]['text'] ?? '' ) === 'Обсудить проект' && ( $natural_ctas[0]['url'] ?? '' ) === '#contact' && ( $natural_ctas[1]['text'] ?? '' ) === 'Смотреть проекты' && ( $natural_ctas[1]['url'] ?? '' ) === '#projects', 'Natural-language hero CTA parser lost a role, label, or URL' );
$standalone_cta_message = "CTA\nЗаголовок секции: «Обсудим проект»\nОписание секции: «Опишите задачу и выберите следующий шаг»\nОсновная кнопка: «Связаться» -> #contact\nВторичная кнопка: «Смотреть проекты» -> #projects";
$standalone_ctas = wpae_llm_extract_requested_ctas( $standalone_cta_message );
check( wpae_llm_detect_block_archetype( $standalone_cta_message ) === 'cta' && wpae_brief_ir_archetype( $standalone_cta_message ) === 'cta', 'Standalone CTA with two links was routed into the hero archetype' );
check( wpae_llm_extract_section_title( $standalone_cta_message ) === 'Обсудим проект' && array_column( $standalone_ctas, 'url' ) === [ '#contact', '#projects' ], 'CTA field labels or arrow targets were lost during prompt parsing' );
$standalone_cta_action = wpae_llm_build_fallback_action( $standalone_cta_message, 42 );
$standalone_cta_copy = wpae_llm_collect_action_content( (array) ( $standalone_cta_action['elements'] ?? [] ) );
$standalone_cta_root = (array) ( $standalone_cta_action['elements'][0] ?? [] );
$standalone_cta_group = [];
foreach ( (array) ( $standalone_cta_root['elements'] ?? [] ) as $cta_child ) {
	if ( is_array( $cta_child ) && str_contains( (string) ( $cta_child['settings']['_css_classes'] ?? '' ), 'wpae-cta-actions' ) ) { $standalone_cta_group = $cta_child; }
}
$standalone_cta_buttons = (array) ( $standalone_cta_group['elements'] ?? [] );
check( str_contains( $standalone_cta_copy, 'Обсудим проект' ) && str_contains( $standalone_cta_copy, 'Опишите задачу и выберите следующий шаг' ) && ! str_contains( $standalone_cta_copy, 'Заголовок секции:' ) && ! str_contains( $standalone_cta_copy, 'Описание секции:' ), 'Standalone CTA published field labels or lost the exact requested copy' );
check( count( $standalone_cta_buttons ) === 2 && ( $standalone_cta_group['settings']['flex_direction'] ?? '' ) === 'row' && ( $standalone_cta_group['settings']['flex_direction_mobile'] ?? '' ) === 'column', 'Standalone CTA did not compile both native buttons into a responsive Flex group' );
check( ( $standalone_cta_buttons[0]['settings']['text'] ?? '' ) === 'Связаться' && ( $standalone_cta_buttons[0]['settings']['link']['url'] ?? '' ) === '#contact' && ( $standalone_cta_buttons[1]['settings']['text'] ?? '' ) === 'Смотреть проекты' && ( $standalone_cta_buttons[1]['settings']['link']['url'] ?? '' ) === '#projects' && ( $standalone_cta_buttons[0]['settings']['background_color'] ?? '' ) !== 'transparent' && ( $standalone_cta_buttons[1]['settings']['background_color'] ?? '' ) === 'transparent', 'Standalone CTA lost button order, URL, or primary/secondary styling' );
$natural_labeled_cta = 'CTA: «Обсудим проект». Опишите задачу и выберите следующий шаг. Кнопка «Связаться» → #contact; вторичная кнопка «Посмотреть проекты» → #projects.';
$natural_labeled_cta_brief = wpae_brief_ir_parse( $natural_labeled_cta );
$natural_labeled_cta_content = (array) ( $natural_labeled_cta_brief['content'] ?? [] );
$natural_labeled_cta_plan = wpae_design_plan_from_brief( $natural_labeled_cta_brief );
$natural_labeled_cta_errors = array_values( array_filter( (array) ( wpae_design_plan_validate( $natural_labeled_cta_plan, $natural_labeled_cta_brief )['errors'] ?? [] ), static fn( string $error ): bool => str_starts_with( $error, 'cta_' ) ) );
$natural_labeled_cta_roles = array_column( $natural_labeled_cta_content, 'role' );
$natural_labeled_cta_buttons = array_values( array_filter( $natural_labeled_cta_content, static fn( $item ): bool => is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2' ], true ) ) );
check( ( $natural_labeled_cta_brief['intent']['archetype'] ?? '' ) === 'cta' && in_array( 'title', $natural_labeled_cta_roles, true ) && in_array( 'body', $natural_labeled_cta_roles, true ), 'Natural CTA: heading and unquoted body were not separated from the section label' );
check( count( $natural_labeled_cta_buttons ) === 2 && array_column( $natural_labeled_cta_buttons, 'url' ) === [ '#contact', '#projects' ] && ! $natural_labeled_cta_errors, 'Natural CTA: primary/secondary links did not satisfy the production design-plan validator: ' . wp_json_encode( [ 'content' => $natural_labeled_cta_content, 'errors' => $natural_labeled_cta_errors ], JSON_UNESCAPED_UNICODE ) );
$leaked_cta_provider = [ container_node( 'leaked-cta-provider', [ 'container_type' => 'flex' ], [
	widget( 'leaked-cta-heading', 'heading', [ 'title' => 'Заголовок секции: «Обсудим проект»' ] ),
	widget( 'leaked-cta-copy', 'text-editor', [ 'editor' => 'Описание секции: «Опишите задачу и выберите следующий шаг»' ] ),
	widget( 'leaked-cta-primary', 'button', [ 'text' => 'Основная кнопка: «Связаться»', 'link' => [ 'url' => '#contact' ] ] ),
	widget( 'leaked-cta-secondary', 'button', [ 'text' => 'Вторичная кнопка: «Смотреть проекты»', 'link' => [ 'url' => '#projects' ] ] ),
] ) ];
$leaked_cta_quality = wpae_llm_provider_composition_quality( $standalone_cta_message, $leaked_cta_provider, 'cta' );
check( empty( $leaked_cta_quality['ok'] ) && in_array( 'CTA field labels were published as visible copy', (array) $leaked_cta_quality['failures'], true ), 'CTA provider tree with field labels passed composition quality' );
$natural_cta_message = 'Секция призыва к действию: «Обсудим проект». Текст: «Опишите задачу». Кнопки: «Связаться» → #contact и «Проекты» → #projects.';
$natural_ctas = wpae_llm_extract_requested_ctas( $natural_cta_message );
check( count( $natural_ctas ) === 2 && ( $natural_ctas[0]['text'] ?? '' ) === 'Связаться' && ( $natural_ctas[0]['url'] ?? '' ) === '#contact' && ( $natural_ctas[1]['text'] ?? '' ) === 'Проекты' && ( $natural_ctas[1]['url'] ?? '' ) === '#projects', 'CTA extractor ignored the grouped CTA roles already parsed by BriefIR' );
$natural_cta_fallback = wpae_llm_build_fallback_action( $natural_cta_message, 42 );
$natural_cta_fallback_fidelity = wpae_llm_content_fidelity( $natural_cta_message, (array) ( $natural_cta_fallback['elements'] ?? [] ) );
check( ! empty( $natural_cta_fallback_fidelity['ok'] ), 'CTA deterministic fallback failed to preserve all BriefIR roles and URLs: ' . wp_json_encode( $natural_cta_fallback_fidelity, JSON_UNESCAPED_UNICODE ) );
$natural_cta_provider = container_node( 'natural-cta-root', [ 'container_type' => 'flex' ], [
	widget( 'natural-cta-heading', 'heading', [ 'title' => 'Обсудим проект' ] ),
	widget( 'natural-cta-body', 'text-editor', [ 'editor' => 'Опишите задачу' ] ),
	widget( 'natural-cta-primary', 'button', [ 'text' => 'Связаться', 'link' => [ 'url' => '#contact' ] ] ),
	widget( 'natural-cta-secondary', 'button', [ 'text' => 'Проекты', 'link' => [ 'url' => '#projects' ] ] ),
] );
$natural_cta_quality = wpae_llm_provider_composition_quality( $natural_cta_message, [ $natural_cta_provider ], 'cta' );
check( ! empty( $natural_cta_quality['ok'] ) && (int) ( $natural_cta_quality['expected_copy_slots'] ?? 0 ) === 4, 'CTA quality gate counted instruction fragments instead of its four parsed copy roles: ' . wp_json_encode( [ $natural_cta_quality, wpae_brief_ir_parse( $natural_cta_message )['content'] ?? [] ], JSON_UNESCAPED_UNICODE ) );
$natural_cta_plan = wpae_llm_content_plan( $natural_cta_message, 'cta' );
$natural_cta_audit = wpae_llm_content_plan_audit( $natural_cta_plan, (array) ( $natural_cta_fallback['elements'] ?? [] ) );
check( ! empty( $natural_cta_audit['ok'] ), 'CTA plan audit treated generic next-step copy as an unrelated process section: ' . wp_json_encode( $natural_cta_audit['failures'] ?? [], JSON_UNESCAPED_UNICODE ) );
$short_cta_live_prompt = 'Отдельная CTA-секция: «Обсудим ваш проект». Опишите задачу, чтобы выбрать следующий шаг. Кнопка «Связаться» → #contact.';
$short_cta_live_brief = wpae_brief_ir_parse( $short_cta_live_prompt );
$short_cta_live_plan = wpae_design_plan_from_brief( $short_cta_live_brief );
$short_cta_live_errors = (array) ( wpae_design_plan_validate( $short_cta_live_plan, $short_cta_live_brief )['errors'] ?? [] );
check( count( array_filter( (array) ( $short_cta_live_brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === 'title' && ( $item['exact_text'] ?? '' ) === 'Обсудим ваш проект' ) ) === 1 && ! in_array( 'cta_heading_required', $short_cta_live_errors, true ), 'Natural standalone CTA heading was lost from the typed design plan' );
$punctuated_cta_prompt = 'CTA. Заголовок: «Обсудим проект». Описание: «Опишите задачу и выберите следующий шаг». Основная кнопка: «Связаться», ссылка: #contact. Вторая кнопка: «Посмотреть проекты», ссылка: #projects.';
$punctuated_cta_brief = wpae_brief_ir_parse( $punctuated_cta_prompt );
$punctuated_cta_fallback = wpae_llm_build_fallback_action( $punctuated_cta_prompt, 42 );
$punctuated_cta_json = (string) wp_json_encode( $punctuated_cta_fallback['elements'] ?? [], JSON_UNESCAPED_UNICODE );
check( wpae_llm_detect_block_archetype( $punctuated_cta_prompt ) === 'cta' && ( $punctuated_cta_brief['intent']['archetype'] ?? '' ) === 'cta', 'Punctuated CTA heading fell through to the generic hero classifier' );
check( strpos( $punctuated_cta_json, 'wpae-hero-visual-panel' ) === false && strpos( $punctuated_cta_json, 'wpae-generated-cta-row' ) !== false, 'Punctuated CTA fallback compiled a hero visual panel instead of the CTA action row' );
$leaked_cta_fallback = wpae_llm_build_fallback_action( $standalone_cta_message, 42 );
$leaked_cta_fallback_copy = wpae_llm_collect_action_content( (array) ( $leaked_cta_fallback['elements'] ?? [] ) );
check( str_contains( $leaked_cta_fallback_copy, 'Обсудим проект' ) && str_contains( $leaked_cta_fallback_copy, 'Опишите задачу и выберите следующий шаг' ) && ! preg_match( '/(?:Заголовок|Описание) секции:|(?:Основная|Вторичная) кнопка:/u', $leaked_cta_fallback_copy ), 'CTA recovery fallback leaked field labels instead of publishing the semantic content' );
$natural_hero_copy = wpae_llm_extract_hero_copy( $natural_hero_message );
check( ( $natural_hero_copy['title'] ?? '' ) === 'Пространство для вашей жизни' && ( $natural_hero_copy['body'] ?? '' ) === 'Проектируем спокойные, светлые интерьеры с вниманием к каждой детали' && ( $natural_hero_copy['visual'] ?? '' ) === 'АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ', 'Natural-language hero copy parser did not preserve labeled eyebrow, title, and body' );
$natural_hero_action = wpae_llm_build_fallback_action( $natural_hero_message, 42 );
$natural_hero_fidelity = wpae_llm_content_fidelity( $natural_hero_message, (array) ( $natural_hero_action['elements'] ?? [] ) );
check( ! empty( $natural_hero_fidelity['ok'] ), 'Natural-language hero fallback failed final content fidelity' );
$natural_hero_json = (string) wp_json_encode( $natural_hero_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $natural_hero_json, '"id":"llm-hero-badge-label"' ) !== false && strpos( $natural_hero_json, '"title":"АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ"' ) !== false, 'Natural-language hero fallback did not promote the requested eyebrow into the native badge' );
$second_hero_message = 'Создай второй hero-блок в конце текущей страницы, не удаляй и не изменяй существующие hero и pricing. Надзаголовок «АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ», заголовок «Пространство для вашей жизни», описание «Проектируем спокойные, светлые интерьеры с вниманием к каждой детали». Основная кнопка «Обсудить проект» со ссылкой #contact; вторичная «Смотреть проекты» со ссылкой #projects. Композиция 60/40: текст слева, визуальная зона справа. Компактные отступы, нейтральный размер заголовка, outlined surface, фон #F6F0E6, на мобильном сначала текст, затем визуальная зона. Существующие hero и pricing сохрани.';
$second_hero_copy = wpae_llm_extract_hero_copy( $second_hero_message );
check( ( $second_hero_copy['brand'] ?? '' ) === '' && ( $second_hero_copy['title'] ?? '' ) === 'Пространство для вашей жизни' && ( $second_hero_copy['body'] ?? '' ) === 'Проектируем спокойные, светлые интерьеры с вниманием к каждой детали' && ( $second_hero_copy['visual'] ?? '' ) === 'АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ', 'Signed second-hero fields were polluted by the generic brand heuristic' );
$second_hero_action = wpae_llm_build_fallback_action( $second_hero_message, 42 );
$second_hero_json = (string) wp_json_encode( $second_hero_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $second_hero_json, '"id":"llm-hero-visual-panel"' ) !== false && strpos( $second_hero_json, '"title":"Надзаголовок «АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ"' ) === false, 'Signed second-hero fallback leaked the full labeled sentence into a native heading' );
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
check( substr_count( $content_only_json, 'Архитектура повседневности' ) === 1 && strpos( $content_only_json, '"widgetType":"image"' ) !== false && strpos( $content_only_json, 'images.unsplash.com' ) !== false, 'Architecture hero fallback should use one contextual Unsplash image instead of a generic icon placeholder' );
check( strpos( $content_only_json, 'Обсудить проект — #contact' ) === false && strpos( $content_only_json, 'Смотреть проекты — #projects' ) === false, 'CTA URL leaked into visible content-only hero copy' );
check( strpos( $content_only_json, 'wpae-hero-visual-panel' ) !== false && strpos( $content_only_json, 'background_color":"transparent' ) !== false, 'Photo-backed hero visual panel should expose the native image without a colored placeholder surface' );
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
$template_hero_root = container_node( 'template-hero', [ 'background_image' => [ 'url' => 'https://templatekit.example.invalid/hero.jpg', 'id' => 42, 'source' => 'url' ] ], [ widget( 'template-title', 'heading', [ 'title' => 'Архитектурная студия' ] ), widget( 'template-copy', 'text-editor', [ 'editor' => 'Современные интерьеры.' ] ) ] );
$template_hero_changed = 0;
$template_hero_prompt = "Hero\nАрхитектурная студия\nСовременный интерьер\nПроектируем дома";
check( count( wpae_llm_content_units( $template_hero_prompt ) ) >= 2, 'trusted hero image replacement test includes distinct copy units' );
$template_hero_normalized = wpae_llm_normalize_hero_composition( [ $template_hero_root ], $template_hero_changed, $template_hero_prompt, true, 0 );
$template_hero_json = (string) wp_json_encode( $template_hero_normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $template_hero_json, 'https://images.unsplash.com/' ) !== false && strpos( $template_hero_json, 'templatekit.example.invalid' ) === false, 'Trusted imported hero image replacement should use the existing Unsplash catalog, never the source kit host' );
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
$benefits_live_message = 'Создай блок преимуществ. Преимущество 1: «Точный расчёт сроков». Описание преимущества 1: «Планируем этапы до начала работ». Преимущество 2: «Единая команда». Описание преимущества 2: «Архитекторы и инженеры работают вместе». Преимущество 3: «Прозрачный контроль». Описание преимущества 3: «Показываем ход проекта на каждом этапе».';
$benefits_live_plan = wpae_llm_content_plan( $benefits_live_message, 'benefits' );
$benefits_live_fallback = wpae_llm_build_fallback_action( $benefits_live_message, 42 );
$benefits_live_audit = wpae_llm_content_plan_audit( $benefits_live_plan, (array) ( $benefits_live_fallback['elements'] ?? [] ) );
check( ( $benefits_live_plan['repeatable_units'] ?? 0 ) === 3, 'Benefits semantic audit counted each title and description as a separate card' );
check( ! in_array( 'repeatable content units are not separated into distinct containers', (array) ( $benefits_live_audit['failures'] ?? [] ), true ), 'Benefits fallback was rejected despite one complete native card per requested pair' );

$faq_message = 'Создай FAQ «Частые вопросы». «Как начать?» — «Оставьте заявку, и мы согласуем встречу». «Можно работать дистанционно?» — «Да, обсуждения и согласования проводим онлайн». «Что входит в проект?» — «Планировка, концепция и согласованный комплект материалов». Сохрани точные вопросы, ответы и порядок. Адаптируй блок для телефона.';
$faq_natural_brief = wpae_brief_ir_parse( 'FAQ: два вопроса — «Как проходит работа?» — «Сначала обсуждаем задачу, затем согласуем проект и сроки»; «Можно ли внести правки?» — «Да, изменения согласуем до финальной версии».' );
$benefits_natural_brief = wpae_brief_ir_parse( 'Блок преимуществ: «Понятный процесс» — «Этапы и сроки согласованы заранее»; «Продуманные решения» — «Каждое решение связано с задачей проекта»; «Сопровождение» — «Проверяем результат на каждом этапе».' );
$benefits_natural_roles = array_column( (array) ( $benefits_natural_brief['content'] ?? [] ), 'role' );
check( $benefits_natural_roles === [ 'feature_title', 'feature_body', 'feature_title', 'feature_body', 'feature_title', 'feature_body' ], 'Benefits parser did not pair natural quoted title/description values separated by dashes' );
$benefits_natural_plan = wpae_design_plan_from_brief( $benefits_natural_brief );
$benefits_natural_items = (array) ( $benefits_natural_plan['sections'][0]['children'][0]['items'] ?? [] );
check( count( $benefits_natural_items ) === 3 && $benefits_natural_items[0]['title_ref'] === 'feature_title' && $benefits_natural_items[0]['body_ref'] === 'feature_body' && $benefits_natural_items[2]['title_ref'] === 'feature_title_3' && $benefits_natural_items[2]['body_ref'] === 'feature_body_3', 'Benefits natural inline pairs were not assembled into three ordered feature cards' );
$benefits_unquoted_prompt = 'Создай блок преимуществ из трёх пунктов: точный расчёт сроков — планируем этапы до начала работ; единая команда — архитекторы и инженеры работают вместе; прозрачный контроль — показываем ход проекта на каждом этапе.';
$benefits_unquoted_brief = wpae_brief_ir_parse( $benefits_unquoted_prompt );
$benefits_unquoted_plan = wpae_design_plan_from_brief( $benefits_unquoted_brief );
$benefits_unquoted_items = (array) ( $benefits_unquoted_plan['sections'][0]['children'][0]['items'] ?? [] );
check( count( $benefits_unquoted_items ) === 3 && ( $benefits_unquoted_brief['content'][0]['exact_text'] ?? '' ) === 'точный расчёт сроков' && ( $benefits_unquoted_brief['content'][1]['exact_text'] ?? '' ) === 'планируем этапы до начала работ', 'Benefits BriefIR did not split natural unquoted title/description pairs into exact fields' );
check( count( $benefits_unquoted_items ) === 3 && ! in_array( 'benefits_require_two_to_six_complete_items', (array) ( wpae_design_plan_validate( $benefits_unquoted_plan )['errors'] ?? [] ), true ), 'Benefits DesignPlan rejected three complete unquoted feature pairs' );
$faq_natural_roles = array_column( (array) ( $faq_natural_brief['content'] ?? [] ), 'role' );
check( $faq_natural_roles === [ 'faq_question', 'faq_answer', 'faq_question', 'faq_answer' ], 'FAQ parser did not pair short natural question/answer copy separated by an em dash' );
$faq_natural_plan = wpae_design_plan_from_brief( $faq_natural_brief );
$faq_natural_items = (array) ( $faq_natural_plan['sections'][0]['children'][0]['items'] ?? [] );
check( count( $faq_natural_items ) === 2 && $faq_natural_items[0]['question_ref'] === 'faq_question' && $faq_natural_items[0]['answer_ref'] === 'faq_answer' && $faq_natural_items[1]['question_ref'] === 'faq_question_2' && $faq_natural_items[1]['answer_ref'] === 'faq_answer_2', 'FAQ natural inline pairs were not assembled into two ordered Accordion items' );
$faq_pairs = wpae_llm_extract_faq_content( $faq_message );
check( count( $faq_pairs ) === 3, 'FAQ parser did not extract three quoted question/answer pairs' );
check( ( $faq_pairs[0]['label'] ?? '' ) === 'Как начать' && ( $faq_pairs[0]['content'] ?? '' ) === 'Оставьте заявку, и мы согласуем встречу', 'FAQ parser did not strip punctuation while preserving the first pair' );
check( ( $faq_pairs[2]['label'] ?? '' ) === 'Что входит в проект' && ( $faq_pairs[2]['content'] ?? '' ) === 'Планировка, концепция и согласованный комплект материалов', 'FAQ parser changed the last pair or its order' );
$faq_requested = wpae_llm_extract_requested_content( $faq_message );
check( in_array( 'Частые вопросы', $faq_requested, true ) && in_array( 'Как начать', $faq_requested, true ) && in_array( 'Оставьте заявку, и мы согласуем встречу', $faq_requested, true ), 'FAQ requested-content extraction lost the title or first pair' );
check( ! in_array( 'Сохрани точные вопросы, ответы и порядок', $faq_requested, true ) && ! in_array( '«Как начать?»', $faq_requested, true ), 'FAQ requested-content extraction retained instruction or quoted duplicate content' );

$faq_answer_labeled_message = 'Создай блок FAQ: вопрос «Как начать проект?» — ответ «Оставьте заявку, и мы обсудим задачу». Вопрос «Сколько стоит работа?» — ответ «Стоимость зависит от объёма и сроков».';
$faq_answer_labeled_pairs = wpae_llm_extract_faq_content( $faq_answer_labeled_message );
check( $faq_answer_labeled_pairs === [
	[ 'label' => 'Как начать проект', 'content' => 'Оставьте заявку, и мы обсудим задачу' ],
	[ 'label' => 'Сколько стоит работа', 'content' => 'Стоимость зависит от объёма и сроков' ],
], 'FAQ parser did not retain natural inline pairs with an explicit answer label' );
$faq_answer_labeled_action = wpae_llm_build_fallback_action( $faq_answer_labeled_message, 42 );
$faq_answer_labeled_tabs = (array) ( $faq_answer_labeled_action['elements'][0]['elements'][1]['settings']['tabs'] ?? [] );
check( count( $faq_answer_labeled_tabs ) === 2 && ( $faq_answer_labeled_tabs[0]['tab_title'] ?? '' ) === 'Как начать проект?' && ( $faq_answer_labeled_tabs[0]['tab_content'] ?? '' ) === 'Оставьте заявку, и мы обсудим задачу' && ( $faq_answer_labeled_tabs[1]['tab_title'] ?? '' ) === 'Сколько стоит работа?' && ( $faq_answer_labeled_tabs[1]['tab_content'] ?? '' ) === 'Стоимость зависит от объёма и сроков', 'FAQ production fallback lost natural answer-labeled question/answer copy' );
$faq_answer_labeled_fidelity = wpae_llm_content_fidelity( $faq_answer_labeled_message, $faq_answer_labeled_action['elements'] );
check( ! empty( $faq_answer_labeled_fidelity['ok'] ), 'FAQ content-fidelity rejected the supported answer-labeled pair format' );
$faq_plan = wpae_llm_content_plan( $faq_message, 'faq' );
check( count( $faq_plan['content_pairs'] ?? [] ) === 3 && ( $faq_plan['content_pairs'][1]['label'] ?? '' ) === 'Можно работать дистанционно', 'FAQ semantic plan did not use the question/answer parser' );
check( empty( $faq_plan['explicit_cta'] ?? [] ) && empty( $faq_plan['cta_required'] ), 'FAQ semantic plan inferred a CTA from ordinary answer copy' );
$faq_action = wpae_llm_build_fallback_action( $faq_message, 42 );
$faq_ctas = wpae_llm_extract_requested_ctas( $faq_message );
check( empty( $faq_ctas ), 'FAQ answer text was incorrectly inferred as an explicit CTA requirement' );
$faq_widget = (array) ( $faq_action['elements'][0]['elements'][1] ?? [] );
$faq_tabs = (array) ( $faq_widget['settings']['tabs'] ?? [] );
check( ( $faq_widget['widgetType'] ?? '' ) === 'accordion' && count( $faq_tabs ) === 3, 'FAQ fallback did not use one native Accordion with three items' );
check( ( $faq_tabs[0]['tab_title'] ?? '' ) === 'Как начать?' && ( $faq_tabs[0]['tab_content'] ?? '' ) === 'Оставьте заявку, и мы согласуем встречу', 'FAQ Accordion changed the first question or answer' );
check( ( $faq_tabs[2]['tab_title'] ?? '' ) === 'Что входит в проект?' && ( $faq_tabs[2]['tab_content'] ?? '' ) === 'Планировка, концепция и согласованный комплект материалов', 'FAQ Accordion changed the last question, answer, or order' );
check( ! empty( wpae_llm_provider_composition_quality( $faq_message, $faq_action['elements'], 'faq' )['ok'] ), 'Native FAQ Accordion failed the provider composition-quality gate' );
$faq_cleanup_changed = 0;
wpae_llm_remove_unrequested_buttons( $faq_action['elements'], $faq_message, $faq_cleanup_changed );
$faq_json = (string) wp_json_encode( $faq_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $faq_json, '"widgetType":"button"' ) === 0, 'FAQ fallback retained an unrequested CTA button from ordinary answer copy' );
check( strpos( $faq_json, 'Как начать' ) !== false && strpos( $faq_json, 'Оставьте заявку, и мы согласуем встречу' ) !== false, 'FAQ fallback lost the first exact question or answer' );
check( strpos( $faq_json, 'Можно работать дистанционно' ) !== false && strpos( $faq_json, 'Да, обсуждения и согласования проводим онлайн' ) !== false, 'FAQ fallback lost the second exact question or answer' );
check( strpos( $faq_json, 'Что входит в проект' ) !== false && strpos( $faq_json, 'Планировка, концепция и согласованный комплект материалов' ) !== false, 'FAQ fallback lost the third exact question or answer' );
check( empty( wpae_llm_content_fidelity( $faq_message, $faq_action['elements'] )['missing'] ), 'FAQ fallback failed final content fidelity' );
$faq_without_accordion_items = $faq_action['elements'];
$faq_without_accordion_items[0]['elements'][1]['settings']['tabs'] = [];
$faq_without_accordion_audit = wpae_llm_content_plan_audit( $faq_plan, $faq_without_accordion_items );
check( empty( $faq_without_accordion_audit['ok'] ) && in_array( 'FAQ questions and answers are not represented by native Accordion items', (array) ( $faq_without_accordion_audit['failures'] ?? [] ), true ), 'FAQ semantic audit accepted copy outside the required native Accordion items' );

$faq_live_qa_message = 'Создай FAQ «FAQ · QA». «Это настоящие данные компании?» — «Нет, это синтетический QA-текст только для проверки виджета Accordion». «Как проверить раскрытие?» — «Нажмите на вопрос: ответ должен открываться и закрываться». «Что нужно увидеть после перезагрузки?» — «Те же три вопроса в том же порядке внутри одного native Accordion». Дизайн только для этого нового корня: чистая белая поверхность, тонкая светло-серая обводка и скругление 12px; не задавай глобальные цвета сайта. Существующие элементы страницы не меняй.';
$faq_live_qa_requested = wpae_llm_extract_requested_content( $faq_live_qa_message );
check( in_array( 'FAQ · QA', $faq_live_qa_requested, true ) && in_array( 'Это настоящие данные компании', $faq_live_qa_requested, true ) && in_array( 'Те же три вопроса в том же порядке внутри одного native Accordion', $faq_live_qa_requested, true ), 'FAQ content-fidelity fixture lost the title or explicit question/answer copy' );
check( ! in_array( 'Дизайн только для этого нового корня: чистая белая поверхность, тонкая светло-серая обводка и скругление 12px; не задавай глобальные цвета сайта', $faq_live_qa_requested, true ) && ! in_array( 'Существующие элементы страницы не меняй', $faq_live_qa_requested, true ), 'FAQ content-fidelity fixture treated styling or preservation instructions as visible content' );
$faq_live_qa_action = wpae_llm_build_fallback_action( $faq_live_qa_message, 42 );
$faq_live_qa_widget = (array) ( $faq_live_qa_action['elements'][0]['elements'][1] ?? [] );
check( ( $faq_live_qa_widget['widgetType'] ?? '' ) === 'accordion' && count( (array) ( $faq_live_qa_widget['settings']['tabs'] ?? [] ) ) === 3 && empty( wpae_llm_content_fidelity( $faq_live_qa_message, $faq_live_qa_action['elements'] )['missing'] ), 'FAQ live QA fallback did not pass content fidelity with one native three-item Accordion' );
$faq_patch_message = 'Измени текст: «Как заказать проект?» — «Оставьте заявку, и мы свяжемся с вами»; «Сколько длится работа?» — «Срок зависит от состава и объёма проекта».';
$faq_generic_patch_tree = [ widget( 'faq-patch-accordion', 'accordion', [ 'tabs' => [ [ '_id' => 'q1', 'tab_title' => 'Аккордеон #1', 'tab_content' => 'Kafka placeholder' ], [ '_id' => 'q2', 'tab_title' => 'Аккордеон #2', 'tab_content' => 'More placeholder' ] ] ] ) ];
$faq_patch_fidelity = wpae_llm_content_fidelity( $faq_patch_message, $faq_generic_patch_tree );
check( empty( $faq_patch_fidelity['ok'] ) && in_array( 'Как заказать проект?', $faq_patch_fidelity['missing'], true ) && in_array( 'Сколько длится работа?', $faq_patch_fidelity['missing'], true ), 'targeted Accordion content with generic questions fails exact requested-content fidelity' );
$faq_target_root = container_node( 'faq-target-root', [ '_css_classes' => 'wpae-generated-root' ], [ $faq_generic_patch_tree[0] ] );
$faq_copy_neighbor = container_node( 'faq-copy-neighbor', [], [ widget( 'faq-copy-neighbor-accordion', 'accordion', [ 'tabs' => [ [ '_id' => 'n1', 'tab_title' => 'Как заказать проект?', 'tab_content' => 'Оставьте заявку, и мы свяжемся с вами' ], [ '_id' => 'n2', 'tab_title' => 'Сколько длится работа?', 'tab_content' => 'Срок зависит от состава и объёма проекта' ] ] ] ) ] );
check( empty( wpae_llm_content_fidelity( $faq_patch_message, [ $faq_target_root, $faq_copy_neighbor ] )['missing'] ) && ! empty( wpae_llm_content_fidelity_for_roots( $faq_patch_message, [ $faq_target_root, $faq_copy_neighbor ], [ 'faq-target-root' ] )['missing'] ), 'FAQ exact copy in a neighboring root cannot satisfy selected-root content fidelity' );
$GLOBALS['page_data'] = [ $faq_target_root, $faq_copy_neighbor ];
$GLOBALS['options'][ WPAE_DESIGN_OPERATION_OPTION ] = [];
$GLOBALS['options']['wp_ai_executor_rollback_snapshots'] = [];
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$faq_partial_target_patch = wpae_llm_execute_patch_action(
	[ 'action' => 'patch_elements', 'post_id' => 42, 'patches' => [ [ 'element_id' => 'faq-patch-accordion', 'path' => 'settings.tabs[0].tab_title', 'op' => 'set', 'value' => 'Как заказать проект?' ] ] ],
	42,
	[ 'faq-target-root' ],
	$faq_patch_message,
	[ 'operation_identity' => 'faq-scope-fidelity-regression' ]
);
check( empty( $faq_partial_target_patch['ok'] ) && empty( $GLOBALS['writes'] ) && ( $GLOBALS['page_data'][0]['elements'][0]['settings']['tabs'][0]['tab_title'] ?? '' ) === 'Аккордеон #1', 'targeted FAQ patch with matching copy only in a sibling root is rejected before the write boundary' );
$faq_library_source = [ container_node( 'faq-library-root', [], [
	widget( 'faq-library-heading', 'heading', [ 'title' => 'Template questions' ] ),
	widget( 'faq-library-accordion', 'accordion', [ 'tabs' => [ [ '_id' => 'oldtab1', 'tab_title' => 'Old question', 'tab_content' => 'Old answer' ], [ '_id' => 'oldtab2', 'tab_title' => 'Another old question', 'tab_content' => 'Another old answer' ] ] ] ),
] ) ];
$faq_library_changes = 0;
$faq_library_adapted = wpae_llm_apply_library_template( $faq_library_source, $faq_answer_labeled_message, 'faq', $faq_library_changes );
$faq_library_accordion = $faq_library_adapted[0]['elements'][1] ?? [];
$faq_library_tabs = (array) ( $faq_library_accordion['settings']['tabs'] ?? [] );
$faq_library_audit = wpae_llm_content_plan_audit( wpae_llm_content_plan( $faq_answer_labeled_message, 'faq' ), $faq_library_adapted );
$faq_library_tab_ids = array_column( $faq_library_tabs, '_id' );
check( count( $faq_library_adapted ) === 1 && ( $faq_library_accordion['widgetType'] ?? '' ) === 'accordion' && count( $faq_library_tabs ) === 2, 'Selected FAQ library template was rejected instead of adapting its native Accordion' );
check( ( $faq_library_tabs[0]['tab_title'] ?? '' ) === 'Как начать проект?' && ( $faq_library_tabs[0]['tab_content'] ?? '' ) === 'Оставьте заявку, и мы обсудим задачу' && ( $faq_library_tabs[1]['tab_title'] ?? '' ) === 'Сколько стоит работа?' && ( $faq_library_tabs[1]['tab_content'] ?? '' ) === 'Стоимость зависит от объёма и сроков', 'FAQ library adaptation changed exact copy or question/answer order' );
check( count( array_unique( $faq_library_tab_ids ) ) === 2 && $faq_library_tab_ids[0] !== 'oldtab1' && $faq_library_tab_ids[1] !== 'oldtab2' && ! empty( $faq_library_audit['ok'] ), 'FAQ library adaptation reused template tab IDs or failed the production semantic audit: ' . wp_json_encode( [ 'ids' => $faq_library_tab_ids, 'audit' => $faq_library_audit ], JSON_UNESCAPED_UNICODE ) );
$faq_explicit_labels = 'Блок FAQ. Вопрос: «Как начать работу?» Ответ: «Оставьте заявку, и мы обсудим задачу». Вопрос: «Можно ли менять сайт после запуска?» Ответ: «Да, контент редактируется в Elementor».';
$faq_explicit_pairs = wpae_llm_extract_faq_content( $faq_explicit_labels );
$faq_explicit_changes = 0;
$faq_explicit_adapted = wpae_llm_apply_library_template( $faq_library_source, $faq_explicit_labels, 'faq', $faq_explicit_changes );
$faq_explicit_tabs = (array) ( $faq_explicit_adapted[0]['elements'][1]['settings']['tabs'] ?? [] );
$faq_explicit_audit = wpae_llm_content_plan_audit( wpae_llm_content_plan( $faq_explicit_labels, 'faq' ), $faq_explicit_adapted );
check( count( $faq_explicit_pairs ) === 2 && ( $faq_explicit_pairs[0]['label'] ?? '' ) === 'Как начать работу' && ( $faq_explicit_pairs[1]['content'] ?? '' ) === 'Да, контент редактируется в Elementor', 'FAQ parser rejected adjacent labeled question/answer pairs from the live prompt' );
check( count( $faq_explicit_tabs ) === 2 && ( $faq_explicit_tabs[0]['tab_title'] ?? '' ) === 'Как начать работу?' && ( $faq_explicit_tabs[1]['tab_content'] ?? '' ) === 'Да, контент редактируется в Elementor', 'FAQ library adapter did not transfer labeled pairs into native Accordion items' );
check( ! empty( $faq_explicit_audit['ok'] ), 'FAQ content plan audit rejected the exact explicit-label prompt: ' . wp_json_encode( $faq_explicit_audit, JSON_UNESCAPED_UNICODE ) );

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

$testimonial_shell = [
	[
		'id' => 'testimonial-root',
		'elType' => 'container',
		'settings' => [ 'flex_direction' => 'column' ],
		'elements' => [
			[ 'id' => 'badge-shell', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'badge', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ '_css_classes' => 'wpae-generated-badge', 'title' => 'ОТЗЫВЫ' ], 'elements' => [] ] ] ],
			[ 'id' => 'content-shell', 'elType' => 'container', 'settings' => [], 'elements' => [
				[ 'id' => 'section-heading', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Что говорят клиенты' ], 'elements' => [] ],
				[ 'id' => 'quote-card-1', 'elType' => 'container', 'settings' => [], 'elements' => [] ],
				[ 'id' => 'quote-card-2', 'elType' => 'container', 'settings' => [], 'elements' => [] ],
			] ],
		],
	],
];
$testimonial_bento_changed = 0;
$testimonial_shell = wpae_llm_apply_bento_layout( $testimonial_shell, 'testimonials', $testimonial_bento_changed );
$testimonial_root = $testimonial_shell[0];
$testimonial_root_classes = preg_split( '/\s+/', trim( (string) ( $testimonial_root['settings']['_css_classes'] ?? '' ) ) );
$testimonial_content = $testimonial_root['elements'][1] ?? [];
$testimonial_grid = $testimonial_content['elements'][1] ?? [];
$testimonial_grid_classes = preg_split( '/\s+/', trim( (string) ( $testimonial_grid['settings']['_css_classes'] ?? '' ) ) );
check( ( $testimonial_root['settings']['flex_direction'] ?? '' ) === 'column' && ! in_array( 'wpae-bento-grid', $testimonial_root_classes, true ), 'Testimonial badge/content shells were incorrectly converted into peer cards' );
check( in_array( 'wpae-bento-grid', $testimonial_grid_classes, true ) && count( (array) ( $testimonial_grid['elements'] ?? [] ) ) === 2, 'Testimonial quote cards were not laid out as a grid inside the content shell' );

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
$pricing_layout = wpae_llm_build_pricing_pair_layout( $pricing_content_only_action['elements'], wpae_llm_extract_pricing_content( $pricing_content_only_message ), $pricing_contract_changed );
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
$pricing_dimension_errors = [];
$check_pricing_dimensions = static function ( array $nodes, string $path = 'root' ) use ( &$check_pricing_dimensions, &$pricing_dimension_errors ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$node_path = $path . '.' . (string) ( $node['id'] ?? 'unknown' );
		foreach ( (array) ( $node['settings'] ?? [] ) as $key => $value ) {
			if ( wpae_elementor_native_control_kind( (string) $key ) !== 'dimensions' ) { continue; }
			$error = wpae_elementor_native_control_error( (string) $key, $value );
			if ( $error !== '' ) { $pricing_dimension_errors[] = $node_path . '.' . $key . ': ' . $error; }
		}
		$check_pricing_dimensions( (array) ( $node['elements'] ?? [] ), $node_path );
	}
};
$check_pricing_dimensions( $pricing_content_only_action['elements'] );
check( empty( $pricing_dimension_errors ), 'Pricing fallback contains invalid native dimension controls: ' . implode( '; ', $pricing_dimension_errors ) );

$quoted_pricing_message = "Создай блок «Тарифы» с тремя карточками:\n«Старт» — «Одна консультация» — «30 000 ₸»;\n«Проект» — «Планировка и концепция» — «150 000 ₸»;\n«Полное сопровождение» — «Проект и авторский надзор» — «300 000 ₸».\nВ каждой карточке отдельная кнопка:\n«Выбрать Старт» → #start,\n«Выбрать Проект» → #project,\n«Выбрать сопровождение» → #support.\nИспользуй светлый фон и терракотовые акценты. На телефоне расположи карточки вертикально.";
$quoted_pricing_contract = wpae_llm_extract_pricing_content( $quoted_pricing_message );
$quoted_pricing_items = $quoted_pricing_contract['items'];
check( count( $quoted_pricing_items ) === 3 && $quoted_pricing_items[0]['label'] === 'Старт' && $quoted_pricing_items[0]['price_text'] === '30 000 ₸' && $quoted_pricing_items[0]['description'] === 'Одна консультация', 'Quoted pricing contract did not preserve label, price, and description order' );
check( wpae_llm_detect_block_archetype( $quoted_pricing_message ) === 'pricing', 'Quoted pricing request was misclassified as a hero after CTA extraction' );
$quoted_pricing_ctas = wpae_llm_extract_requested_ctas( $quoted_pricing_message );
check( count( $quoted_pricing_ctas ) === 3 && $quoted_pricing_ctas[0]['text'] === 'Выбрать Старт' && $quoted_pricing_ctas[0]['url'] === '#start' && $quoted_pricing_ctas[2]['url'] === '#support', 'Arrow CTA parser did not preserve all pricing labels and targets' );
$quoted_pricing_action = wpae_llm_build_fallback_action( $quoted_pricing_message, 42 );
check( ! empty( wpae_llm_content_fidelity( $quoted_pricing_message, $quoted_pricing_action['elements'] )['ok'] ), 'Quoted pricing fallback failed content fidelity' );
$quoted_pricing_json = (string) wp_json_encode( $quoted_pricing_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( substr_count( $quoted_pricing_json, '"widgetType":"button"' ) === 3 && strpos( $quoted_pricing_json, '"url":"#start"' ) !== false && strpos( $quoted_pricing_json, '"url":"#support"' ) !== false, 'Quoted pricing fallback did not build three native CTA buttons with exact targets' );
$inline_pricing_message = 'Создай блок «Выберите формат работы» с бейджем «ТАРИФЫ». Три предложения: «Старт» — «Для небольшой задачи с понятным объёмом» — «от 50 000 ₸» — кнопка «Выбрать Старт», ссылка #start. «Проект» — «Для комплексной работы от идеи до результата» — «от 150 000 ₸» — кнопка «Обсудить проект», ссылка #project. «Поддержка» — «Для регулярных задач и развития проекта» — «от 80 000 ₸/мес» — кнопка «Подключить поддержку», ссылка #support.';
$inline_pricing_contract = wpae_llm_extract_pricing_content( $inline_pricing_message );
$inline_pricing_items = $inline_pricing_contract['items'];
check( count( $inline_pricing_items ) === 3 && $inline_pricing_items[0]['price_text'] === 'от 50 000 ₸' && $inline_pricing_items[2]['price_text'] === 'от 80 000 ₸/мес' && $inline_pricing_contract['heading'] === 'Выберите формат работы' && $inline_pricing_contract['badge'] === 'ТАРИФЫ', 'Inline pricing contract did not preserve amounts, heading, and badge' );
$inline_pricing_action = wpae_llm_build_fallback_action( $inline_pricing_message, 42 );
$inline_pricing_fidelity = wpae_llm_content_fidelity( $inline_pricing_message, $inline_pricing_action['elements'] );
check( ! empty( $inline_pricing_fidelity['ok'] ), 'Inline quoted pricing fallback failed exact content fidelity' );
$inline_pricing_json = (string) wp_json_encode( $inline_pricing_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$inline_direct_changed = 0;
$inline_direct_layout = wpae_llm_build_pricing_pair_layout( [], $inline_pricing_contract, $inline_direct_changed );
check( ! empty( wpae_llm_content_fidelity( $inline_pricing_message, $inline_direct_layout )['ok'] ), 'Pricing amount plus separate monthly period did not satisfy exact content fidelity' );
check( substr_count( $inline_pricing_json, '"widgetType":"button"' ) === 3, 'Inline quoted pricing fallback lost native CTA widgets' );
check( strpos( $inline_pricing_json, '"url":"#start"' ) !== false, 'Inline quoted pricing fallback lost the #start CTA URL' );
check( strpos( $inline_pricing_json, '"title":"от 150 000 ₸"' ) !== false, 'Inline quoted pricing fallback lost the quoted amount field' );
check( strpos( $inline_pricing_json, '"title":"Выберите формат работы"' ) !== false, 'Inline quoted pricing fallback lost the requested section title' );
check( strpos( $inline_pricing_json, '"title":"ТАРИФЫ"' ) !== false, 'Inline quoted pricing fallback lost the requested badge label' );
$inline_monthly_group = null;
$find_inline_monthly_group = static function ( array $nodes ) use ( &$find_inline_monthly_group, &$inline_monthly_group ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'container' && str_contains( (string) ( $node['settings']['_css_classes'] ?? '' ), 'wpae-pricing-price-group' ) ) { $inline_monthly_group = $node; return; }
		$find_inline_monthly_group( (array) ( $node['elements'] ?? [] ) );
		if ( is_array( $inline_monthly_group ) ) { return; }
	}
};
$find_inline_monthly_group( $inline_pricing_action['elements'] );
$inline_monthly_widgets = (array) ( $inline_monthly_group['elements'] ?? [] );
check( count( $inline_monthly_widgets ) === 2 && ( $inline_monthly_widgets[0]['settings']['title'] ?? '' ) === 'от 80 000 ₸' && ( $inline_monthly_widgets[1]['settings']['title'] ?? '' ) === '/мес', 'Monthly price is not represented as a compact amount plus period pair' );
check( ( $inline_monthly_group['settings']['flex_direction'] ?? '' ) === 'row' && ( $inline_monthly_group['settings']['flex_direction_mobile'] ?? '' ) === 'row' && ( $inline_monthly_group['settings']['flex_wrap'] ?? '' ) === 'nowrap' && ( $inline_monthly_group['settings']['flex_align_items'] ?? '' ) === 'baseline' && ( $inline_monthly_widgets[0]['settings']['_flex_grow'] ?? -1 ) === 0, 'Monthly price and period can wrap or stretch as full-width widgets' );
check( strpos( $inline_pricing_json, '"editor":"Для регулярных задач и развития проекта"' ) !== false, 'Inline quoted pricing fallback merged the monthly price and description' );
$price_first_pricing_message = 'Добавь отдельный pricing-блок в конец текущей страницы, не удаляй существующие элементы. Точные тексты: надзаголовок «ТАРИФЫ», заголовок «Выберите формат работы». Три карточки: «Старт» — «от 50 000 ₸» — «Для небольшой задачи с понятным объёмом» — кнопка «Выбрать Старт» со ссылкой #start; «Проект» — «от 150 000 ₸» — «Для комплексной работы от идеи до результата» — кнопка «Обсудить проект» со ссылкой #project; «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач и развития проекта» — кнопка «Подключить поддержку» со ссылкой #support. Используй native Elementor Flexbox и сохрани остальные элементы страницы.';
$price_first_contract = wpae_llm_extract_pricing_content( $price_first_pricing_message );
$compact_live_pricing_message = 'Тарифы: Старт — от 50 000 ₸ — Для регулярных задач и развития проекта; Проект — от 150 000 ₸ — Для небольшой задачи с понятным объёмом; Поддержка — от 80 000 ₸/мес — Для комплексной работы от идеи до результата.';
$compact_live_pricing_contract = wpae_llm_extract_pricing_content( $compact_live_pricing_message );
$compact_live_pricing_brief = wpae_brief_ir_parse( $compact_live_pricing_message );
$compact_live_pricing_plan = wpae_design_plan_from_brief( $compact_live_pricing_brief );
$compact_live_pricing_validation = wpae_design_plan_validate( $compact_live_pricing_plan, $compact_live_pricing_brief );
check( count( $compact_live_pricing_contract['items'] ?? [] ) === 3 && array_column( $compact_live_pricing_contract['items'], 'label' ) === [ 'Старт', 'Проект', 'Поддержка' ], 'Compact live pricing request did not produce all three named tiers' );
check( array_column( $compact_live_pricing_contract['items'], 'price_text' ) === [ 'от 50 000 ₸', 'от 150 000 ₸', 'от 80 000 ₸/мес' ] && ( $compact_live_pricing_contract['items'][2]['description'] ?? '' ) === 'Для комплексной работы от идеи до результата.', 'Compact live pricing parser lost a price, period, or description' );
check( count( $compact_live_pricing_brief['pricing_items'] ?? [] ) === 3, 'Compact live pricing request did not populate typed BriefIR tiers' );
check( ! in_array( 'pricing_tiers_required', $compact_live_pricing_validation['errors'] ?? [], true ) && empty( array_filter( (array) ( $compact_live_pricing_validation['errors'] ?? [] ), static fn( $error ): bool => str_starts_with( (string) $error, 'pricing_tier_' ) ) ), 'Compact live pricing request still fails the DesignPlan pricing-tier gate' );
check( ( $compact_live_pricing_brief['pricing_items'][2]['period_ref'] ?? '' ) !== '', 'Compact live monthly price did not retain its typed period reference' );
$simple_live_pricing_message = 'Блок тарифов: Старт — 50 000 ₸, «Для регулярных задач». Проект — 150 000 ₸, «Для небольшой задачи». Поддержка — 80 000 ₸/мес, «Для комплексной работы».';
$simple_live_pricing_contract = wpae_llm_extract_pricing_content( $simple_live_pricing_message );
check( count( $simple_live_pricing_contract['items'] ?? [] ) === 3 && array_column( $simple_live_pricing_contract['items'], 'label' ) === [ 'Старт', 'Проект', 'Поддержка' ] && ( $simple_live_pricing_contract['items'][2]['price_text'] ?? '' ) === '80 000 ₸/мес' && ( $simple_live_pricing_contract['items'][2]['description'] ?? '' ) === 'Для комплексной работы', 'Simple live pricing brief lost tier, monthly period, or quoted description' );
check( wpae_llm_detect_block_archetype( $simple_live_pricing_message ) === 'pricing', 'Simple live pricing brief was classified as the wrong block family' );
$simple_pricing_adapted_changes = 0;
$simple_pricing_adapted = wpae_llm_apply_library_template( [ container_node( 'simple-pricing-source', [ 'container_type' => 'flex' ], [ widget( 'simple-pricing-placeholder', 'heading', [ 'title' => 'Source pricing' ] ) ] ) ], $simple_live_pricing_message, 'pricing', $simple_pricing_adapted_changes );
$simple_pricing_adapted_copy = wpae_llm_collect_action_content( $simple_pricing_adapted );
check( count( $simple_pricing_adapted ) === 1 && substr_count( wp_json_encode( $simple_pricing_adapted ), 'wpae-pricing-card-' ) >= 3 && strpos( $simple_pricing_adapted_copy, 'Старт' ) !== false && strpos( $simple_pricing_adapted_copy, 'Проект' ) !== false && strpos( $simple_pricing_adapted_copy, 'Поддержка' ) !== false, 'Simple live pricing brief did not adapt into three native pricing cards' );
$price_first_items = (array) ( $price_first_contract['items'] ?? [] );
check( count( $price_first_items ) === 3 && ( $price_first_contract['heading'] ?? '' ) === 'Выберите формат работы' && ( $price_first_contract['badge'] ?? '' ) === 'ТАРИФЫ', 'Price-first recovery prompt did not preserve the explicit pricing heading and eyebrow' );
check( $price_first_items[0]['price_text'] === 'от 50 000 ₸' && $price_first_items[1]['description'] === 'Для комплексной работы от идеи до результата' && array_column( $price_first_items, 'cta_url' ) === [ '#start', '#project', '#support' ], 'Price-first recovery prompt lost a price, description, or paired CTA URL' );
$price_first_action = wpae_llm_build_fallback_action( $price_first_pricing_message, 42 );
$price_first_plan = wpae_llm_content_plan( $price_first_pricing_message, 'pricing' );
$price_first_audit = wpae_llm_content_plan_audit( $price_first_plan, $price_first_action['elements'] );
check( ! empty( $price_first_audit['ok'] ) && ! empty( wpae_llm_content_fidelity( $price_first_pricing_message, $price_first_action['elements'] )['ok'] ), 'Price-first recovery fallback introduced unrelated semantics or lost requested pricing copy' );
$price_first_visual_changed = 0;
$price_first_native_changed = 0;
$price_first_flex_changed = 0;
$price_first_runtime_elements = wpae_llm_apply_generation_visual_grammar( $price_first_action['elements'], 'pricing', $price_first_visual_changed );
$price_first_runtime_elements = wpae_llm_normalize_native_visual_contract( $price_first_runtime_elements, $price_first_pricing_message, 'pricing', $price_first_native_changed );
$price_first_runtime_elements = wpae_llm_enforce_flex_layout_contract( $price_first_runtime_elements, 'pricing', $price_first_flex_changed );
$price_first_runtime_grid = null;
$find_price_first_runtime_grid = static function ( array $nodes ) use ( &$find_price_first_runtime_grid, &$price_first_runtime_grid ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$classes = preg_split( '/\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) );
		$child_cards = array_values( array_filter( (array) ( $node['elements'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' && in_array( 'wpae-pricing-card', preg_split( '/\s+/', trim( (string) ( $child['settings']['_css_classes'] ?? '' ) ) ), true ) ) );
		if ( ( $node['elType'] ?? '' ) === 'container' && is_array( $classes ) && ( in_array( 'wpae-pricing-grid', $classes, true ) || ( in_array( 'wpae-bento-grid', $classes, true ) && count( $child_cards ) === 3 ) ) ) {
			$price_first_runtime_grid = $node;
			return;
		}
		$find_price_first_runtime_grid( (array) ( $node['elements'] ?? [] ) );
		if ( is_array( $price_first_runtime_grid ) ) {
			return;
		}
	}
};
$find_price_first_runtime_grid( $price_first_runtime_elements );
$price_first_runtime_cards = array_values( array_filter( (array) ( $price_first_runtime_grid['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'container' ) );
check( count( $price_first_runtime_cards ) === 3 && ( $price_first_runtime_grid['settings']['flex_wrap'] ?? '' ) === 'nowrap' && ( $price_first_runtime_grid['settings']['flex_wrap_mobile'] ?? '' ) === 'wrap', 'Production pricing contract allowed the three-card grid to wrap on desktop' );
check( count( array_filter( $price_first_runtime_cards, static fn( $card ): bool => ( $card['settings']['background_color'] ?? '' ) === '#ffffff' && (float) ( $card['settings']['width']['size'] ?? 0 ) === 30.0 ) ) === 3, 'Production pricing contract did not preserve neutral 30 percent cards' );
$price_first_runtime_root = (array) ( $price_first_runtime_elements[0] ?? [] );
$price_first_runtime_root_classes = preg_split( '/\s+/', trim( (string) ( $price_first_runtime_root['settings']['_css_classes'] ?? '' ) ) );
check( ( $price_first_runtime_root['settings']['flex_direction'] ?? '' ) === 'column' && ! in_array( 'wpae-bento-grid', $price_first_runtime_root_classes, true ) && ( $price_first_runtime_grid['settings']['flex_direction'] ?? '' ) === 'row', 'Pricing visual normalizer turned the full section into a horizontal grid instead of preserving a vertical shell around the tier row' );
$no_title_pricing_message = 'Тарифы: «Старт» — «Для быстрой задачи» — «от 50 000 ₸»; «Проект» — «Для большого проекта» — «от 150 000 ₸».';
$no_title_pricing_action = wpae_llm_build_fallback_action( $no_title_pricing_message, 42 );
$no_title_pricing_json = (string) wp_json_encode( $no_title_pricing_action['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( wpae_llm_extract_section_title( $no_title_pricing_message ) === '' && str_contains( $no_title_pricing_json, '"title":"Тарифы"' ) && ! str_contains( $no_title_pricing_json, '"title":"Старт","header_size":"h2"' ), 'Pricing without an explicit section heading promoted the first tier to the oversized section title' );

$multiline_pricing_message = "Создай блок: «Выберите формат работы» с бейджем: «ТАРИФЫ».\n«Старт» — «Для небольшой задачи: быстро и понятно.» — «от 50 000 ₸» — кнопка «Выбрать Старт», ссылка #start.\n«Проект» — «Для комплексной работы, от идеи до результата?» — «от 150 000 ₸» — кнопка «Обсудить проект», ссылка #project.\n«Поддержка» — «Для регулярных задач и развития проекта.» — «от 80 000 ₸/мес» — кнопка «Подключить поддержку», ссылка #support.";
$multiline_pricing_contract = wpae_llm_extract_pricing_content( $multiline_pricing_message );
$multiline_pricing_items = $multiline_pricing_contract['items'];
check( count( $multiline_pricing_items ) === 3 && $multiline_pricing_items[0]['description'] === 'Для небольшой задачи: быстро и понятно.' && $multiline_pricing_items[1]['description'] === 'Для комплексной работы, от идеи до результата?' && $multiline_pricing_items[2]['price_text'] === 'от 80 000 ₸/мес', 'Multiline pricing contract lost punctuation or the monthly price period' );
check( array_column( $multiline_pricing_items, 'cta_url' ) === [ '#start', '#project', '#support' ] && array_column( $multiline_pricing_items, 'cta_text' ) === [ 'Выбрать Старт', 'Обсудить проект', 'Подключить поддержку' ], 'Pricing contract did not keep CTA text and URL pairs ordered by card' );
$normalized_pricing_contract = wpae_llm_normalize_pricing_contract( $multiline_pricing_contract );
$normalized_pricing_contract_again = wpae_llm_normalize_pricing_contract( $normalized_pricing_contract );
check( wp_json_encode( $normalized_pricing_contract, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) === wp_json_encode( $normalized_pricing_contract_again, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 'Pricing contract normalization is not idempotent' );
$optional_pricing_contract = wpae_llm_normalize_pricing_contract( [
	'heading' => 'Тарифы',
	'badge' => 'ТАРИФЫ',
	'items' => [
		[ 'label' => 'Мини', 'description' => '', 'price_text' => 'от 10 000 ₸', 'cta_text' => '', 'cta_url' => '' ],
		[ 'label' => 'Стандарт', 'description' => 'Полный объём', 'price_text' => 'от 30 000 ₸', 'cta_text' => 'Выбрать', 'cta_url' => '#standard' ],
	],
] );
check( $optional_pricing_contract['items'][0]['description'] === '' && $optional_pricing_contract['items'][0]['price_text'] === 'от 10 000 ₸', 'Pricing contract rejected an absent optional description or changed price_text' );
$optional_pricing_changed = 0;
$optional_pricing_layout = wpae_llm_build_pricing_pair_layout( [], $optional_pricing_contract, $optional_pricing_changed );
$optional_pricing_json = (string) wp_json_encode( $optional_pricing_layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $optional_pricing_json, '"title":"от 10 000 ₸"' ) !== false && strpos( $optional_pricing_json, '"editor":""' ) === false, 'Pricing builder created empty optional description content' );
$invalid_url_message = 'Создай блок «Тарифы»: «Старт» — «Описание» — «от 50 000 ₸» — кнопка «Выбрать Старт», ссылка javascript:alert(1). «Проект» — «Описание проекта» — «от 150 000 ₸».';
$invalid_url_contract = wpae_llm_extract_pricing_content( $invalid_url_message );
check( ( $invalid_url_contract['items'][0]['cta_text'] ?? '' ) === 'Выбрать Старт' && ( $invalid_url_contract['items'][0]['cta_url'] ?? '' ) === '', 'Invalid CTA URL was not rejected by the pricing contract policy' );

$reference_timeline = wpae_llm_build_process_timeline(
    [
        [ 'label' => 'Замысел', 'content' => 'Формулируем цель, аудиторию и ключевую идею.' ],
        [ 'label' => 'Съёмка', 'content' => 'Записываем материал по утверждённому плану.' ],
        [ 'label' => 'Монтаж', 'content' => 'Собираем материал в цельную историю и проверяем детали.' ],
        [ 'label' => 'Публикация', 'content' => 'Готовим итоговый материал к выбранному каналу публикации.' ],
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
	check( ( $card_settings['width']['unit'] ?? '' ) === '%' && abs( (float) $card_settings['width']['size'] - 25.0 ) < 0.001 && ( $card_settings['_flex_size'] ?? '' ) === 'custom' && (int) ( $card_settings['_flex_grow'] ?? -1 ) === 0 && (float) $card_settings['width_mobile']['size'] === 100.0 && ( $card_settings['_flex_size_mobile'] ?? '' ) === 'custom', 'Reference cards lack equal native desktop widths or full-width mobile stacking' );
	check( ( $card_settings['_element_custom_width']['unit'] ?? '' ) === '%' && abs( (float) $card_settings['_element_custom_width']['size'] - 25.0 ) < 0.001 && (float) $card_settings['_element_custom_width_mobile']['size'] === 100.0, 'Reference process cards do not use Elementor native custom-width controls at both breakpoints' );
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
$mobile_stack_process_message = 'Создай блок процесса «Как мы работаем». На desktop покажи этапы в ряд; на mobile расположи карточки вертикально.';
$mobile_stack_process = wpae_llm_build_process_timeline( wpae_llm_process_timeline_steps( $mobile_stack_process_message ), 'mobile-stack-process', wpae_llm_process_timeline_layout( $mobile_stack_process_message ) );
check( wpae_llm_process_timeline_layout( $mobile_stack_process_message ) === 'horizontal' && ( $mobile_stack_process['elements'][2]['settings']['flex_direction'] ?? '' ) === 'row' && ( $mobile_stack_process['elements'][2]['settings']['flex_direction_mobile'] ?? '' ) === 'column', 'Mobile vertical stacking instruction incorrectly made the desktop process timeline vertical' );
$mobile_stack_final_changed = 0;
$mobile_stack_final = wpae_llm_enforce_process_timeline_contract( [ $mobile_stack_process ], $mobile_stack_process_message, $mobile_stack_final_changed );
check( ( $mobile_stack_final[0]['elements'][2]['settings']['flex_direction'] ?? '' ) === 'row' && ( $mobile_stack_final[0]['elements'][2]['settings']['flex_direction_mobile'] ?? '' ) === 'column', 'Final process write contract reversed the desktop row/mobile stack layout' );
$desktop_vertical_process_message = 'Создай блок процесса «Как мы работаем». На desktop расположи этапы вертикально.';
check( wpae_llm_process_timeline_layout( $desktop_vertical_process_message ) === 'left', 'Explicit desktop vertical process layout was overridden by the horizontal default' );
$generated_process_message = 'Создай блок «Как мы работаем». Над заголовком добавь бейдж «ПРОЦЕСС». Этапы: «Замысел», «Съёмка», «Монтаж», «Публикация». Добавь к каждому этапу короткое описание.';
$generated_process_steps = wpae_llm_process_timeline_steps( $generated_process_message );
check( array_column( $generated_process_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Generated process labels changed order or content' );
$generated_process_copies = array_column( $generated_process_steps, 'content' );
check( count( $generated_process_copies ) === 4 && count( array_filter( $generated_process_copies, static fn( $copy ): bool => (bool) preg_match( '/^Этап\s+\d+\s*:/u', (string) $copy ) ) ) === 0, 'Generated process descriptions fell back to numbered label placeholders' );
check( min( array_map( static fn( $copy ): int => function_exists( 'mb_strlen' ) ? mb_strlen( (string) $copy ) : strlen( (string) $copy ), $generated_process_copies ) ) > 20, 'Generated process descriptions are not meaningful enough for the requested stage brief' );
$generated_process_timeline = wpae_llm_build_process_timeline( $generated_process_steps, 'generated-process', 'horizontal', 'Как мы работаем' );
$generated_process_json = (string) wp_json_encode( $generated_process_timeline, JSON_UNESCAPED_UNICODE );
check( strpos( $generated_process_json, 'Формулируем цель, аудиторию и ключевую идею.' ) !== false && strpos( $generated_process_json, 'Готовим итоговый материал к выбранному каналу публикации.' ) !== false, 'Canonical process builder did not retain generated stage descriptions' );
$explicit_process_message = 'Создай отдельный блок «Как мы работаем» с бейджем «ПРОЦЕСС». Этап «Замысел»: «Определяем задачу, аудиторию и идею ролика». Этап «Съёмка»: «Записываем материал по согласованному сценарию». Этап «Монтаж»: «Собираем историю, обрабатываем звук и цвет». Этап «Публикация»: «Готовим финальные файлы для выбранных площадок».';
$explicit_process_steps = wpae_llm_process_timeline_steps( $explicit_process_message );
check( array_column( $explicit_process_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Explicit process labels were not parsed from colon pairs' );
check( array_column( $explicit_process_steps, 'content' ) === [ 'Определяем задачу, аудиторию и идею ролика', 'Записываем материал по согласованному сценарию', 'Собираем историю, обрабатываем звук и цвет', 'Готовим финальные файлы для выбранных площадок' ], 'Explicit process descriptions were not preserved exactly' );
$explicit_process_timeline = wpae_llm_build_process_timeline( $explicit_process_steps, 'explicit-process', 'horizontal', 'Как мы работаем' );
$explicit_process_json = (string) wp_json_encode( $explicit_process_timeline, JSON_UNESCAPED_UNICODE );
check( strpos( $explicit_process_json, 'Определяем задачу, аудиторию и идею ролика' ) !== false && strpos( $explicit_process_json, 'Готовим финальные файлы для выбранных площадок' ) !== false, 'Native process builder lost explicitly requested descriptions' );
$bare_content_only_process_message = "Как мы работаем\nПРОЦЕСС\nЗамысел\nСъёмка\nМонтаж\nПубликация";
$standalone_cta_with_process_words = 'Самостоятельный CTA. Заголовок: «Обсудите следующий шаг проекта». Кнопка: «Связаться»';
check( ! wpae_llm_is_process_request( $standalone_cta_with_process_words, 'cta' ), 'Explicit CTA archetype was hijacked by the generic “следующий шаг проекта” process heuristic' );
$bare_process_archetype = wpae_llm_detect_block_archetype( $bare_content_only_process_message );
check( wpae_llm_is_content_only_process_brief( $bare_content_only_process_message ), 'Content-only process shell was not recognised before archetype scoring' );
check( wpae_llm_is_process_request( $bare_content_only_process_message ), 'Content-only process shell was not recognised as a process request' );
check( $bare_process_archetype === 'process', 'Content-only process shell was misclassified as ' . $bare_process_archetype );
$bare_content_only_process_steps = wpae_llm_process_timeline_steps( $bare_content_only_process_message );
check( array_column( $bare_content_only_process_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Bare content-only process lines did not stay in order' );
check( wpae_llm_process_timeline_layout( $bare_content_only_process_message ) === 'horizontal', 'Content-only process brief did not infer horizontal layout' );
$standard_timeline_message = 'Сделай стандартный таймлайн.';
check( wpae_llm_detect_block_archetype( $standard_timeline_message ) === 'process' && wpae_llm_process_timeline_layout( $standard_timeline_message ) === 'horizontal', 'Bare standard timeline request did not use the reference desktop row by default' );
$standard_timeline = wpae_llm_build_process_timeline( wpae_llm_process_timeline_steps( $standard_timeline_message ), 'standard-timeline', wpae_llm_process_timeline_layout( $standard_timeline_message ) );
$standard_timeline_errors = [];
$check_timeline_radii = static function ( array $nodes, string $path = 'root' ) use ( &$check_timeline_radii, &$standard_timeline_errors ): void {
	foreach ( $nodes as $node ) {
		$node_path = $path . '.' . (string) ( $node['id'] ?? 'unknown' );
		if ( isset( $node['settings']['border_radius'] ) ) {
			$error = wpae_elementor_native_control_error( 'border_radius', $node['settings']['border_radius'] );
			if ( $error !== '' ) {
				$standard_timeline_errors[] = $node_path . ': ' . $error;
			}
		}
		$check_timeline_radii( (array) ( $node['elements'] ?? [] ), $node_path );
	}
};
$check_timeline_radii( [ $standard_timeline ] );
check( empty( $standard_timeline_errors ), 'Canonical horizontal timeline failed Elementor native-setting validation: ' . implode( '; ', $standard_timeline_errors ) );
check( is_array( wpae_llm_process_timeline_steps( null, false ) ), 'Null retry message crashed the shared process parser' );
$content_only_process_timeline = wpae_llm_build_process_timeline( $content_only_process_steps, 'content-only-process', 'horizontal', 'Как мы работаем' );
$content_only_process_json = wp_json_encode( $content_only_process_timeline );
check( substr_count( (string) $content_only_process_json, '"wpae-process-content"' ) === 4 && substr_count( (string) $content_only_process_json, '"widgetType":"divider"' ) === 3, 'Content-only process prompt did not produce four cards and three native dividers' );
check( wpae_llm_is_targeted_edit_request( 'Обнови выбранный горизонтальный таймлайн: добавь стандартный нативный бейдж «ПРОЦЕСС» и секционный заголовок.' ), 'Selected process shell addition was misclassified as a new block' );
check( ! wpae_llm_is_targeted_edit_request( 'Создай новый горизонтальный таймлайн: Замысел, Съёмка, Монтаж, Публикация.' ), 'New process generation was incorrectly classified as a targeted edit' );
$existing_faq_edit = 'В существующем FAQ замени вопросы и ответы на: «Как заказать проект?» — «Оставьте заявку, и мы свяжемся с вами»; «Сколько длится работа?» — «Срок зависит от состава и объёма проекта».';
check( wpae_llm_is_targeted_edit_request( $existing_faq_edit ), 'An explicit edit to an existing FAQ was misclassified as a new root insert' );
check( ! wpae_llm_is_targeted_edit_request( 'Создай новый блок FAQ с двумя вопросами и ответами.' ), 'A new FAQ request was incorrectly classified as a targeted edit' );
$rebuilt_steps = wpae_llm_process_timeline_steps_from_elements( [ $reference_timeline ] );
check( array_column( $rebuilt_steps, 'label' ) === [ 'Замысел', 'Съёмка', 'Монтаж', 'Публикация' ], 'Process step reader did not ignore the horizontal badge/heading shell' );
$contract_changed = 0;
$contract_timeline = wpae_llm_enforce_process_timeline_contract( [ $reference_timeline ], $detailed_process_message, $contract_changed )[0] ?? [];
$contract_json = wp_json_encode( $contract_timeline );
check( substr_count( (string) $contract_json, '"wpae-generated-badge"' ) === 1 && substr_count( (string) $contract_json, '"wpae-process-heading"' ) === 1, 'Process contract duplicated or dropped the horizontal badge/heading shell' );
$provider_process_message = 'Создай блок процесса «Как мы работаем»: «01. Заявка» — «QA: запрос поступил»; «02. Уточнение» — «QA: детали проверены»; «03. Старт» — «QA: следующий шаг согласован».';
$provider_process_root = container_node( 'provider-process-root', [ 'flex_direction' => 'row' ], [
	widget( 'provider-process-label-1', 'heading', [ 'title' => '01. Заявка' ] ),
	widget( 'provider-process-copy-1', 'text-editor', [ 'editor' => 'QA: запрос поступил' ] ),
	widget( 'provider-process-label-2', 'heading', [ 'title' => '02. Уточнение' ] ),
	widget( 'provider-process-copy-2', 'text-editor', [ 'editor' => 'QA: детали проверены' ] ),
	widget( 'provider-process-label-3', 'heading', [ 'title' => '03. Старт' ] ),
	widget( 'provider-process-copy-3', 'text-editor', [ 'editor' => 'QA: следующий шаг согласован' ] ),
] );
$provider_process_changed = 0;
$provider_process_result = wpae_llm_enforce_process_timeline_contract( [ $provider_process_root ], $provider_process_message, $provider_process_changed, true );
$provider_process_json = (string) wp_json_encode( $provider_process_result, JSON_UNESCAPED_UNICODE );
check( $provider_process_changed === 1 && str_contains( $provider_process_json, 'wpae-process-items' ) && substr_count( $provider_process_json, 'wpae-process-content' ) === 3, 'Markerless provider Process root bypassed the canonical responsive timeline contract' );
check( str_contains( $provider_process_json, 'QA: запрос поступил' ) && str_contains( $provider_process_json, 'QA: детали проверены' ) && str_contains( $provider_process_json, 'QA: следующий шаг согласован' ), 'Canonical Process rebuild lost explicit provider content' );
$provider_process_cards = (array) ( $provider_process_result[0]['elements'][2]['elements'] ?? [] );
check( count( $provider_process_cards ) === 3 && array_reduce( $provider_process_cards, static fn( bool $ok, array $card ): bool => $ok && abs( (float) ( $card['settings']['width']['size'] ?? 0 ) - ( 100 / 3 ) ) < 0.001 && (float) ( $card['settings']['width_mobile']['size'] ?? 0 ) === 100.0, true ), 'Three-step Process repair did not assign equal desktop widths with a mobile stack' );
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
$owned_retry_root = $reference_timeline;
$owned_retry_root['id'] = 'owned-retry-root';
$owned_retry_root['settings']['_css_classes'] = 'wpae-process-timeline wpae-generated-root';
$owned_retry_nested_id = (string) ( $owned_retry_root['elements'][1]['id'] ?? '' );
$owned_retry_target = wpae_llm_find_process_timeline_target( [ $owned_retry_root ], [ $owned_retry_nested_id ] );
check( ! empty( $owned_retry_target['ok'] ) && ( $owned_retry_target['root_id'] ?? '' ) === 'owned-retry-root' && ( $owned_retry_target['selection_relation'] ?? '' ) === 'descendant', 'Nested process selection did not resolve to its top-level root' );
$owned_retry_operation_target = wpae_llm_find_operation_owned_process_target( [ $owned_retry_root ], [ 'owned-retry-root' ] );
check( ! empty( $owned_retry_operation_target['ok'] ) && ! empty( $owned_retry_operation_target['operation_owned'] ), 'Operation-owned process root was not accepted for safe retry' );
$foreign_retry_root = $owned_retry_root;
$foreign_retry_root['settings']['_css_classes'] = 'wpae-process-timeline';
$foreign_retry_target = wpae_llm_find_operation_owned_process_target( [ $foreign_retry_root ], [ 'owned-retry-root' ] );
check( empty( $foreign_retry_target['ok'] ) && ( $foreign_retry_target['reason'] ?? '' ) === 'root_not_operation_owned', 'Foreign process root was accepted as an operation-owned retry target' );
$missing_retry_target = wpae_llm_find_operation_owned_process_target( [ $owned_retry_root ], [ 'missing-retry-root' ] );
check( empty( $missing_retry_target['ok'] ) && ( $missing_retry_target['reason'] ?? '' ) === 'process_root_not_found', 'Deleted process root did not produce a safe retry conflict' );
$GLOBALS['page_data'] = [ $owned_retry_root ];
$GLOBALS['writes'] = [];
$nested_repair_result = wpae_llm_execute_process_timeline_repair( $GLOBALS['page_data'], 42, [ $owned_retry_nested_id ], $detailed_process_message, 'nested-repair-operation' );
check( ! empty( $nested_repair_result['ok'] ) && ( $nested_repair_result['editor_sync']['after_top_level_ids'] ?? [] ) === [ 'owned-retry-root' ] && count( $GLOBALS['page_data'] ) === 1, 'Nested process retry rebuilt a duplicate or lost the operation-owned root' );
check( ( $nested_repair_result['steps'][0]['details']['selection_relation'] ?? '' ) === 'descendant' && ( $nested_repair_result['steps'][0]['details']['selected_element_id'] ?? '' ) === $owned_retry_nested_id, 'Nested retry did not report its actual selection boundary' );
check( ! wpae_llm_is_process_structure_repair_request( 'Измени только текст дочернего заголовка выбранного таймлайна.' ), 'Copy-only nested process edit was routed to structural rebuild' );
check( wpae_llm_is_process_structure_repair_request( 'Добавь к выбранному таймлайну нативные Divider между карточками.' ), 'Structural process repair was not recognised' );
check( wpae_llm_is_independent_insert_request( 'Добавь ещё один горизонтальный блок процесса.' ), 'Independent process insertion was not distinguished from retry' );
check( wpae_llm_is_independent_insert_request( 'Создай отдельный блок «Как мы работаем» с этапами «Замысел», «Съёмка», «Монтаж», «Публикация».' ), 'Separate process block was not distinguished from retry when the editor selection remained active' );
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

$edde_plan = [
	'schema' => WPAE_LLM_DESIGN_ENGINE_SCHEMA,
	'archetype' => 'hero',
	'composition' => 'split_40_60',
	'content_alignment' => 'center',
	'vertical_alignment' => 'center',
	'spacing_rhythm' => 'spacious',
	'surface' => 'outlined',
	'typography' => 'display',
	'cta_hierarchy' => 'primary_secondary',
	'responsive_strategy' => 'copy_first_stack',
];
check( wpae_llm_design_engine_decode_plan( wp_json_encode( $edde_plan ), [ 'composition' => 'split_60_40' ] )['plan']['composition'] === 'split_60_40', 'EDDE explicit composition constraint did not override a provider plan' );
check( ! wpae_llm_design_engine_decode_plan( wp_json_encode( array_merge( $edde_plan, [ 'confidence' => 0.9 ] ) ) )['ok'], 'EDDE accepted an untyped confidence field' );
$edde_constraints = wpae_llm_design_engine_explicit_constraints( $explicit_cta_message );
check( ! array_key_exists( 'content_alignment', $edde_constraints ), 'EDDE mistook a right-side visual panel for right-aligned copy' );
$split_constraint_message = 'Создай hero для архитектурной студии «Тихая форма». Композиция 40/60: текст слева, визуальная зона справа. Выровняй текст по левому краю.';
$split_constraints = wpae_llm_design_engine_explicit_constraints( $split_constraint_message );
check( ( $split_constraints['composition'] ?? '' ) === 'split_40_60' && ( $split_constraints['content_alignment'] ?? '' ) === 'left', 'EDDE treated a right-side visual zone as right-aligned copy' );
$edde_color_action = wpae_llm_design_engine_compile_hero( wpae_llm_build_fallback_action( $message, 42 ), $edde_plan, 'Создай hero с фоном #123456.' );
check( ( $edde_color_action['elements'][0]['settings']['background_color'] ?? '' ) === '#123456', 'EDDE compiler overrode an explicit user background color: ' . wp_json_encode( [ 'id' => $edde_color_action['elements'][0]['id'] ?? '', 'settings' => $edde_color_action['elements'][0]['settings'] ?? [] ] ) );
$vision_regenerate_plan = array_merge(
    [
        'schema' => WPAE_LLM_DESIGN_ENGINE_SCHEMA,
        'archetype' => 'hero',
        'composition' => 'split_60_40',
        'content_alignment' => 'left',
        'vertical_alignment' => 'start',
        'spacing_rhythm' => 'balanced',
        'surface' => 'soft_panel',
        'typography' => 'display',
        'cta_hierarchy' => 'single_primary',
        'responsive_strategy' => 'copy_first_stack',
    ],
    $split_constraints
);
$vision_regenerate_action = wpae_llm_design_engine_compile_hero( wpae_llm_build_fallback_action( $split_constraint_message, 42 ), $vision_regenerate_plan, $split_constraint_message . ' Фон #F6F0E6.' );
$vision_regenerate_shell = $vision_regenerate_action['elements'][0]['elements'][1] ?? [];
$vision_regenerate_copy = $vision_regenerate_shell['elements'][0] ?? [];
$vision_regenerate_visual = $vision_regenerate_shell['elements'][1] ?? [];
check( abs( (float) ( $vision_regenerate_copy['settings']['width']['size'] ?? 0 ) - 40 ) < 0.01 && abs( (float) ( $vision_regenerate_visual['settings']['width']['size'] ?? 0 ) - 60 ) < 0.01, 'Vision regeneration lost explicit 40/60 hero geometry' );
check( ( $vision_regenerate_action['elements'][0]['settings']['background_color'] ?? '' ) === '#f6f0e6' && ( $vision_regenerate_copy['settings']['width_mobile']['size'] ?? 0 ) === 100 && ( $vision_regenerate_visual['settings']['width_mobile']['size'] ?? 0 ) === 100, 'Vision regeneration lost explicit hero background or mobile stack' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_engine_mode' => 'active' ];
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $edde_plan ) ) ];
$edde_request = new WP_REST_Request();
$edde_request->set_param( 'message', $explicit_cta_message );
$edde_request->set_param( 'context', [ 'post_id' => 42 ] );
$edde_response = wpae_llm_chat_request( $edde_request );
check( $edde_response instanceof WP_REST_Response && ! empty( $edde_response->get_data()['ok'] ), 'EDDE active hero did not use the existing write boundary' );
$edde_data = $edde_response->get_data();
check( ( $edde_data['diagnostics']['action_path'] ?? '' ) === 'edde' && ( $edde_data['diagnostics']['design_engine']['status'] ?? '' ) === 'ok', 'EDDE active diagnostics did not report the typed path' );
check( count( $GLOBALS['http_calls'] ) === 1 && count( $GLOBALS['writes'] ) === 1, 'EDDE active exceeded its single bounded decision call or wrote more than once' );
$edde_saved = $GLOBALS['page_data'][2] ?? [];
$edde_shell = $edde_saved['elements'][1] ?? [];
$edde_copy = $edde_shell['elements'][0] ?? [];
$edde_visual = $edde_shell['elements'][1] ?? [];
check( abs( (float) ( $edde_copy['settings']['width']['size'] ?? 0 ) - 38.4 ) < 0.01 && abs( (float) ( $edde_visual['settings']['width']['size'] ?? 0 ) - 57.6 ) < 0.01, 'EDDE compiler did not preserve the typed 40/60 composition at the native 96% layout budget' );
check( ( $edde_shell['settings']['flex_justify_content'] ?? '' ) === 'center' && ( $edde_visual['settings']['border_border'] ?? '' ) === 'solid', 'EDDE compiler lost vertical or outlined surface decisions' );
$sixty_message = 'Создай второй hero для архитектурной студии «Тихая форма». Надзаголовок «АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ», заголовок «Пространство для вашей жизни», описание «Проектируем спокойные, светлые интерьеры с вниманием к каждой детали». Основная кнопка «Обсудить проект» со ссылкой #contact; вторичная «Смотреть проекты» со ссылкой #projects. Сделай композицию 60/40: текст слева, визуальная зона справа, компактные отступы, нейтральный размер заголовка, тонкая рамка визуальной зоны, фон #F6F0E6 и mobile copy-first stack.';
$sixty_plan = [
	'schema' => WPAE_LLM_DESIGN_ENGINE_SCHEMA,
	'archetype' => 'hero',
	'composition' => 'split_60_40',
	'content_alignment' => 'left',
	'vertical_alignment' => 'start',
	'spacing_rhythm' => 'compact',
	'surface' => 'outlined',
	'typography' => 'neutral',
	'cta_hierarchy' => 'primary_secondary',
	'responsive_strategy' => 'copy_first_stack',
];
$sixty_constraints = wpae_llm_design_engine_explicit_constraints( $sixty_message );
check( ( $sixty_constraints['composition'] ?? '' ) === 'split_60_40' && ( $sixty_constraints['spacing_rhythm'] ?? '' ) === 'compact' && ( $sixty_constraints['typography'] ?? '' ) === 'neutral', 'EDDE did not decode the independent 60/40 layout constraints' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION]['design_engine_mode'] = 'active';
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $sixty_plan ) ) ];
$sixty_request = new WP_REST_Request();
$sixty_request->set_param( 'message', $sixty_message );
$sixty_request->set_param( 'context', [ 'post_id' => 42 ] );
$sixty_response = wpae_llm_chat_request( $sixty_request );
check( $sixty_response instanceof WP_REST_Response && ! empty( $sixty_response->get_data()['ok'] ), 'EDDE active 60/40 hero did not use the existing write boundary' );
$sixty_data = $sixty_response->get_data();
check( ( $sixty_data['diagnostics']['action_path'] ?? '' ) === 'edde' && ( $sixty_data['diagnostics']['design_engine']['status'] ?? '' ) === 'ok', 'EDDE active 60/40 diagnostics did not report the typed path' );
check( ( $sixty_data['diagnostics']['design_engine']['plan']['composition'] ?? '' ) === 'split_60_40' && ( $sixty_data['diagnostics']['design_engine']['plan']['spacing_rhythm'] ?? '' ) === 'compact', 'EDDE active 60/40 lost the validated plan before compilation' );
check( count( $GLOBALS['http_calls'] ) === 1 && count( $GLOBALS['writes'] ) === 1, 'EDDE active 60/40 exceeded its bounded decision or write budget' );
$sixty_saved = $GLOBALS['page_data'][2] ?? [];
$sixty_shell = $sixty_saved['elements'][1] ?? [];
$sixty_copy = $sixty_shell['elements'][0] ?? [];
$sixty_visual = $sixty_shell['elements'][1] ?? [];
check( abs( (float) ( $sixty_copy['settings']['width']['size'] ?? 0 ) - 57.6 ) < 0.01 && abs( (float) ( $sixty_visual['settings']['width']['size'] ?? 0 ) - 38.4 ) < 0.01, 'EDDE production path did not preserve the typed 60/40 ratio at the native 96% layout budget' );
check( ( $sixty_shell['settings']['flex_gap']['size'] ?? 0 ) === 1.25 && ( $sixty_copy['elements'][1]['settings']['typography_font_size']['size'] ?? 0 ) === 2.75, 'EDDE production path lost compact spacing or neutral heading typography' );
check( ( $sixty_saved['settings']['background_color'] ?? '' ) === '#f6f0e6' && ( $sixty_visual['settings']['border_border'] ?? '' ) === 'solid', 'EDDE production path lost the explicit background or visual outline' );
check( ( $sixty_copy['settings']['width_mobile']['size'] ?? 0 ) === 100 && ( $sixty_visual['settings']['width_mobile']['size'] ?? 0 ) === 100, 'EDDE production path did not preserve the mobile copy-first stack' );
$sixty_json = (string) wp_json_encode( $sixty_saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( strpos( $sixty_json, 'Пространство для вашей жизни' ) !== false && strpos( $sixty_json, 'Проектируем спокойные, светлые интерьеры с вниманием к каждой детали' ) !== false, 'EDDE production 60/40 lost exact hero copy' );
$sixty_buttons = [];
$collect_sixty_buttons = static function ( array $nodes ) use ( &$collect_sixty_buttons, &$sixty_buttons ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'button' ) {
			$sixty_buttons[] = $node;
		}
		if ( is_array( $node['elements'] ?? null ) ) {
			$collect_sixty_buttons( $node['elements'] );
		}
	}
};
$collect_sixty_buttons( [ $sixty_saved ] );
check( count( $sixty_buttons ) === 2 && ( $sixty_buttons[0]['settings']['link']['url'] ?? '' ) === '#contact' && ( $sixty_buttons[1]['settings']['link']['url'] ?? '' ) === '#projects', 'EDDE production 60/40 lost the two native CTA links' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION]['design_engine_mode'] = 'shadow';
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $edde_plan ) ), provider_reply( wp_json_encode( $action ) ) ];
$shadow_request = new WP_REST_Request();
$shadow_request->set_param( 'message', $message );
$shadow_request->set_param( 'context', [ 'post_id' => 42 ] );
$shadow_response = wpae_llm_chat_request( $shadow_request );
check( $shadow_response instanceof WP_REST_Response && ! empty( $shadow_response->get_data()['ok'] ), 'EDDE shadow mode changed the existing provider path' );
$shadow_data = $shadow_response->get_data();
check( ( $shadow_data['diagnostics']['action_path'] ?? '' ) === 'provider' && ( $shadow_data['diagnostics']['design_engine']['status'] ?? '' ) === 'ok', 'EDDE shadow mode did not preserve provider action diagnostics' );
check( count( $GLOBALS['http_calls'] ) === 2 && count( $GLOBALS['writes'] ) === 1, 'EDDE shadow mode did not stay within decision plus provider call budget' );
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION]['design_engine_mode'] = 'off';

$hero_cta_classifier = 'Создай hero для архитектурной студии. Надзаголовок: «АРХИТЕКТУРА». Заголовок: «Пространство для идей». Описание: «Опишите задачу». Основная кнопка «К тарифам», ссылка #contact.';
check( wpae_llm_detect_block_archetype( $hero_cta_classifier ) === 'hero', 'CTA label "К тарифам" does not override an explicitly requested hero in the primary runtime classifier' );

$library_choice_message = 'Создай карусель партнёров: Партнёры: Альфа, Бета.';
$library_choice_carousel = [
	[ 'id' => 1, 'url' => 'https://example.test/partner-a.jpg' ],
	[ 'id' => 2, 'url' => 'https://example.test/partner-b.jpg' ],
];
$library_choice_candidate = [
	'choice_key' => 'candidate_2',
	'id' => 102,
	'title' => 'Выбранная карусель',
	'category' => 'carousel',
	'source' => 'plugin_template',
	'status' => 'published',
	'score' => 9,
	'matched_terms' => [ 'carousel' ],
	'trusted_bundled' => false,
	'elementor_data' => [ widget( 'library-carousel-choice', 'image-carousel', [ 'carousel' => $library_choice_carousel ] ) ],
];
$GLOBALS['library'] = [
	'status' => 'matched',
	'reason' => 'fixture',
	'available_count' => 2,
	'candidate_count' => 2,
	'candidates' => [
		[ 'choice_key' => 'candidate_1', 'title' => 'Неподходящая карточка', 'category' => 'carousel', 'source' => 'plugin_template' ],
		[ 'choice_key' => 'candidate_2', 'title' => 'Выбранная карусель', 'category' => 'carousel', 'source' => 'plugin_template' ],
	],
	'selection_candidates' => [
		[ 'choice_key' => 'candidate_1', 'id' => 101, 'title' => 'Неподходящая карточка', 'category' => 'carousel', 'source' => 'plugin_template', 'trusted_bundled' => false, 'elementor_data' => [] ],
		$library_choice_candidate,
	],
	'selected' => [ 'choice_key' => 'candidate_1', 'id' => 101, 'title' => 'Неподходящая карточка', 'category' => 'carousel', 'source' => 'plugin_template', 'trusted_bundled' => false, 'elementor_data' => [] ],
];
$library_provider_action = [
	'action' => 'insert_elements',
	'post_id' => 42,
	'position' => 'end',
	'library_choice' => 'candidate_2',
	'elements' => [ container_node( 'provider-root-must-not-win', [ 'container_type' => 'flex' ], [ widget( 'provider-copy', 'text-editor', [ 'editor' => $library_choice_message ] ) ] ) ],
];
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'off', 'design_engine_mode' => 'off' ];
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $library_provider_action ) ) ];
$library_choice_request = new WP_REST_Request();
$library_choice_request->set_param( 'message', $library_choice_message );
$library_choice_request->set_param( 'context', [ 'post_id' => 42 ] );
$library_choice_response = wpae_llm_chat_request( $library_choice_request );
$library_choice_data = $library_choice_response instanceof WP_REST_Response ? $library_choice_response->get_data() : [];
$library_choice_trace = (array) ( $library_choice_data['library'] ?? [] );
$library_choice_written = (array) ( $GLOBALS['page_data'][ count( $legacy_page ) ] ?? [] );
$library_choice_system_prompt = (string) ( $GLOBALS['http_calls'][0]['body']['messages'][0]['content'] ?? '' );
$library_choice_widget_types = [];
$collect_library_choice_widgets = static function ( array $nodes ) use ( &$collect_library_choice_widgets, &$library_choice_widget_types ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( ( $node['elType'] ?? '' ) === 'widget' ) {
			$library_choice_widget_types[] = (string) ( $node['widgetType'] ?? '' );
		}
		$collect_library_choice_widgets( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_library_choice_widgets( [ $library_choice_written ] );
check( ! empty( $library_choice_data['ok'] ) && count( $GLOBALS['http_calls'] ) === 1 && count( $GLOBALS['writes'] ) === 1, 'Agent template decision uses the ordinary provider and single page-write boundary: ' . wp_json_encode( [ 'data' => $library_choice_data, 'trace' => $library_choice_trace, 'error' => $library_choice_response instanceof WP_Error ? $library_choice_response->get_error_code() : '' ], JSON_UNESCAPED_UNICODE ) );
check( strpos( $library_choice_system_prompt, 'candidate_1' ) !== false && strpos( $library_choice_system_prompt, 'candidate_2' ) !== false && strpos( $library_choice_system_prompt, 'library-carousel-choice' ) === false && strpos( $library_choice_system_prompt, '"library_choice":"offered choice_key or null"' ) !== false && strpos( $library_choice_system_prompt, 'Не заменяй отказ собственной native-композицией' ) !== false, 'Agent receives bounded choices and must refuse instead of substituting an unselected native composition' );
check( ( $library_choice_trace['selection_source'] ?? '' ) === 'model_choice' && ( $library_choice_trace['model_choice'] ?? '' ) === 'candidate_2' && ( $library_choice_trace['selected']['title'] ?? '' ) === 'Выбранная карусель', 'Production diagnostics record the model-selected allowlisted library template' );
check( ( $library_choice_trace['status'] ?? '' ) === 'applied' && in_array( 'image-carousel', $library_choice_widget_types, true ), 'The model-selected template passes adaptation and is compiled into the page write: ' . wp_json_encode( [ 'status' => $library_choice_trace['status'] ?? '', 'widgets' => $library_choice_widget_types ] ) );
check( strpos( (string) wp_json_encode( $library_choice_written ), 'provider-root-must-not-win' ) === false && ( $GLOBALS['page_data'][0]['id'] ?? '' ) === ( $legacy_page[0]['id'] ?? '' ), 'The selected library composition wins while existing page roots remain untouched' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$library_provider_decline = $library_provider_action;
$library_provider_decline['library_choice'] = null;
$library_provider_decline['elements'][0]['id'] = 'provider-root-after-decline';
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $library_provider_decline ) ) ];
$library_decline_request = new WP_REST_Request();
$library_decline_request->set_param( 'message', $library_choice_message );
$library_decline_request->set_param( 'context', [ 'post_id' => 42 ] );
$library_decline_response = wpae_llm_chat_request( $library_decline_request );
$library_decline_data = $library_decline_response instanceof WP_REST_Response ? $library_decline_response->get_data() : [];
$library_decline_trace = (array) ( $library_decline_data['library'] ?? [] );
check( ! empty( $library_decline_data['ok'] ) && ( $library_decline_trace['selection_source'] ?? '' ) === 'model_declined' && empty( $library_decline_trace['selected'] ), 'Legacy routes retain their existing behavior when the agent declines library options' );
check( strpos( (string) wp_json_encode( $GLOBALS['page_data'] ), 'provider-root-after-decline' ) !== false && count( $GLOBALS['writes'] ) === 1, 'Non-library routes still allow their validated native composition through the existing writer' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ new WP_Error( 'http_request_failed', 'simulated provider outage' ) ];
$library_failure_request = new WP_REST_Request();
$library_failure_request->set_param( 'message', $library_choice_message );
$library_failure_request->set_param( 'context', [ 'post_id' => 42 ] );
$library_failure_response = wpae_llm_chat_request( $library_failure_request );
$library_failure_data = $library_failure_response instanceof WP_REST_Response ? $library_failure_response->get_data() : [];
$library_failure_trace = (array) ( $library_failure_data['library'] ?? [] );
check( ! empty( $library_failure_data['ok'] ) && ( $library_failure_trace['selection_source'] ?? '' ) === 'no_model_choice' && empty( $library_failure_trace['selected'] ), 'Provider failure does not silently promote the locally ranked library candidate' );
check( ( $library_failure_trace['status'] ?? '' ) === 'not_selected' && stripos( (string) ( $library_failure_trace['reason'] ?? '' ), 'no valid model selection' ) !== false, 'Library diagnostics distinguish provider failure from model selection' );
$GLOBALS['library'] = [];

if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
	eval( 'namespace Elementor; class Plugin { public static $types = [ "heading", "text-editor", "button", "image", "icon-list", "divider" ]; public $widgets_manager; public static function instance() { return new self(); } public function __construct() { $this->widgets_manager = new Widgets_Manager(); } } class Widgets_Manager { public function get_widget_types() { return array_fill_keys( Plugin::$types, new \\stdClass() ); } }' );
}
if ( function_exists( 'did_action' ) ) {
	$GLOBALS['test_actions']['elementor/widgets/register'] = 1;
}
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'active' ];
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [];
$missing_required_image = new WP_REST_Request();
$missing_required_image->set_param( 'message', 'Создай hero. Изображение обязательно. Заголовок: «Комната для идей».' );
$missing_required_image->set_param( 'context', [ 'post_id' => 42 ] );
$missing_required_response = wpae_llm_chat_request( $missing_required_image );
check( is_wp_error( $missing_required_response ) && $missing_required_response->get_error_code() === 'wpae_design_plan_rejected', 'active pipeline rejects a required image without an asset before write' );
check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 0, 'required missing asset makes zero provider calls and zero page writes' );
$unscoped_vision_repair = new WP_REST_Request();
$unscoped_vision_repair->set_param( 'message', 'Создай hero с заголовком «Комната для идей» и описанием «Понятный первый шаг».' );
$unscoped_vision_repair->set_param( 'context', [ 'post_id' => 42, 'vision_repair' => true, 'vision_regenerate' => true ] );
$unscoped_vision_response = wpae_llm_chat_request( $unscoped_vision_repair );
check( is_wp_error( $unscoped_vision_response ) && $unscoped_vision_response->get_error_code() === 'wpae_vision_replacement_scope_required', 'active Vision regeneration without an owned-root snapshot is refused instead of appending' );
check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 0, 'unscoped Vision regeneration performs zero provider calls and writes' );

// An active deterministic request must stop at the capability gate when the
// runtime cannot confirm its widgets; it must not fall through to provider JSON.
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'active' ];
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [];
$capability_request = new WP_REST_Request();
\Elementor\Plugin::$types = [];
$capability_request->set_param( 'message', 'Создай hero. Надзаголовок: «ПРОВЕРКА». Заголовок: «Runtime-компоненты». Описание: «Проверяем доступность виджетов». Кнопка: «К тарифам», ссылка #start.' );
$capability_request->set_param( 'context', [ 'post_id' => 42 ] );
$capability_response = wpae_llm_chat_request( $capability_request );
check( is_wp_error( $capability_response ) && $capability_response->get_error_code() === 'wpae_widget_capability_unavailable', 'unverified runtime fails active pipeline before provider fallback' );
check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 0, 'active capability failure makes zero provider calls and zero writes' );

// FAQ and benefits must use the production deterministic route, even with
// EDDE active, and preserve all pre-existing roots through the one writer.
\Elementor\Plugin::$types = [ 'heading', 'text-editor', 'button', 'image', 'icon', 'icon-list', 'divider', 'accordion' ];
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'active', 'design_engine_mode' => 'active' ];
$production_design_cases = [
	[ 'hero', 'Создай hero. Надзаголовок: «ТИХАЯ ФОРМА». Заголовок: «Пространство для идей». Описание: «Опишите задачу и получите понятный первый шаг». Кнопка: «Начать проект», ссылка #contact.', 'heading' ],
	[ 'hero_image', "Создай hero с изображением. Надзаголовок: «АРХИТЕКТУРА». Заголовок: «Пространство для идей». Описание: «Опишите задачу и получите понятный первый шаг». Кнопка: «Начать проект», ссылка #contact. Изображение: https://images.unsplash.com/photo-1774516534068-77422d9226e6?auto=format&fit=crop&w=1800&q=85\nAlt: «Современный бетонный интерьер с большими окнами на природный ландшафт»\nLicense: «Unsplash License»\nPhoto by: «Neon Wang»", 'image' ],
	[ 'pricing', 'Создай pricing. «Старт» — «от 50 000 ₸» — «Для небольшой задачи». Кнопка: «Выбрать Старт», ссылка #start. «Проект» — «от 150 000 ₸» — «Для комплексной работы». Кнопка: «Обсудить проект», ссылка #project. «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач». Кнопка: «Подключить поддержку», ссылка #support.', 'button' ],
	[ 'faq', "Создай FAQ\nВопрос 1: «Как начать?»\nОтвет 1: «Сначала согласуем задачу.»\nВопрос 2: «Можно ли редактировать?»\nОтвет 2: «Да, тексты остаются native Elementor.»", 'accordion' ],
	[ 'benefits', "Создай блок преимуществ\nПреимущество 1: «Прозрачный план»\nОписание преимущества 1: «Каждый этап согласован заранее.»\nПреимущество 2: «Редактируемый сайт»\nОписание преимущества 2: «Команда меняет тексты внутри Elementor.»", 'icon' ],
	[ 'services', "Блок услуг\nУслуга 1 — название: «Стратегия проекта»\nУслуга 1 — описание: «Формулируем задачу и согласуем план работ.»\nУслуга 2 — название: «Архитектура и дизайн»\nУслуга 2 — описание: «Разрабатываем решение под заданный контекст.»\nУслуга 3 — название: «Сопровождение»\nУслуга 3 — описание: «Проверяем соответствие согласованному проекту.»", 'text-editor' ],
	[ 'team', "Блок команды\nУчастник 1 — имя: «Синтетический участник 1»\nУчастник 1 — должность: «Демо-архитектор»\nУчастник 2 — имя: «Синтетический участник 2»\nУчастник 2 — должность: «Демо-руководитель проекта»", 'heading' ],
	[ 'testimonials', "Блок отзывов — синтетические тестовые данные\nОтзыв 1 — текст: «Синтетический короткий отзыв для проверки карточки.»\nОтзыв 1 — автор: «Тестовый автор 1»\nОтзыв 2 — текст: «Синтетический длинный отзыв для проверки переноса текста и естественной высоты карточки без обрезания.»\nОтзыв 2 — автор: «Тестовый автор 2»", 'text-editor' ],
	[ 'cta', "Самостоятельный CTA\nЗаголовок: «Обсудите следующий шаг проекта»\nОписание: «Опишите задачу, чтобы выбрать подходящий формат разговора.»\nКнопка: «Связаться», ссылка #contact\nВторичная кнопка: «Посмотреть проекты», ссылка #projects", 'button' ],
];
foreach ( $production_design_cases as [ $case_name, $case_prompt, $expected_widget ] ) {
	$GLOBALS['page_data'] = $legacy_page;
	$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
	$GLOBALS['responses'] = [];
	$case_request = new WP_REST_Request();
	$case_request->set_param( 'message', $case_prompt );
	$case_request->set_param( 'context', [ 'post_id' => 42 ] );
	$case_response = wpae_llm_chat_request( $case_request );
	$case_data = $case_response instanceof WP_REST_Response ? $case_response->get_data() : [];
	check( ! empty( $case_data['ok'] ) && ( $case_data['diagnostics']['action_path'] ?? '' ) === 'pipeline', $case_name . ' takes the active production pipeline when EDDE is also active: ' . wp_json_encode( [ 'ok' => $case_data['ok'] ?? false, 'action_path' => $case_data['diagnostics']['action_path'] ?? '', 'error' => $case_response instanceof WP_Error ? $case_response->get_error_code() : '', 'message' => $case_response instanceof WP_Error ? $case_response->get_error_message() : '', 'data' => $case_response instanceof WP_Error ? $case_response->get_error_data() : [] ] ) );
	check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 1, $case_name . ' performs no provider call and exactly one page write' );
	$case_tree = (array) ( $GLOBALS['page_data'] ?? [] );
	$case_widgets = [];
	$case_nodes = [];
	$collect_case_widgets = static function ( array $nodes ) use ( &$collect_case_widgets, &$case_widgets, &$case_nodes ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$case_nodes[] = $node;
			if ( ( $node['elType'] ?? '' ) === 'widget' ) {
				$case_widgets[] = (string) ( $node['widgetType'] ?? '' );
			}
			$collect_case_widgets( (array) ( $node['elements'] ?? [] ) );
		}
	};
	$case_generated_roots = array_slice( $case_tree, count( $legacy_page ) );
	$collect_case_widgets( $case_generated_roots );
	$case_data = $case_response instanceof WP_REST_Response ? $case_response->get_data() : [];
	check( in_array( $expected_widget, $case_widgets, true ) && ( $case_tree[0]['id'] ?? '' ) === ( $legacy_page[0]['id'] ?? '' ) && count( $case_generated_roots ) === 1, $case_name . ' compiles a native widget into exactly one new root while keeping existing roots: ' . wp_json_encode( [ 'expected_widget' => $expected_widget, 'widgets' => $case_widgets, 'generated_roots' => count( $case_generated_roots ), 'first_root' => $case_tree[0]['id'] ?? '', 'ok' => $case_data['ok'] ?? false, 'action_path' => $case_data['diagnostics']['action_path'] ?? '', 'error' => $case_response instanceof WP_Error ? $case_response->get_error_code() : '' ] ) );
	check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 1 && ( $case_data['diagnostics']['provider_calls'] ?? null ) === 0, $case_name . ' uses exactly one production write and no provider calls' );
	if ( $case_name === 'cta' ) {
		$cta_trace = $case_data['diagnostics']['design_pipeline'] ?? [];
		$cta_classes = preg_split( '/\s+/', trim( (string) ( $case_generated_roots[0]['settings']['_css_classes'] ?? '' ) ) ) ?: [];
		check( ( $cta_trace['brief']['archetype'] ?? '' ) === 'cta' && ( $cta_trace['plan']['archetype'] ?? '' ) === 'cta' && in_array( 'wpae-generated-cta', $cta_classes, true ) && ! in_array( 'wpae-generated-hero', $cta_classes, true ), 'production CTA diagnostics and the written root agree on cta archetype' );
	}
	if ( $case_name === 'hero_image' ) {
		$case_image = array_values( array_filter( $case_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) )[0] ?? [];
		check( ( $case_image['settings']['image']['url'] ?? '' ) === 'https://images.unsplash.com/photo-1774516534068-77422d9226e6?auto=format&fit=crop&w=1800&q=85' && ( $case_image['settings']['image']['alt'] ?? '' ) === 'Современный бетонный интерьер с большими окнами на природный ландшафт', 'production hero route writes the exact licensed image URL and alt into a native Image widget' );
	}
}

$team_natural_brief = wpae_brief_ir_parse( 'Команда: участник 1 — имя «Айжан Садыкова», должность «Архитектор»; участник 2 — имя «Тимур Оспанов», должность «Руководитель проекта».' );
$team_natural_plan = wpae_design_plan_from_brief( $team_natural_brief );
$team_natural_validation = wpae_design_plan_validate( $team_natural_plan, $team_natural_brief );
check( empty( $team_natural_validation['errors'] ), 'Team parser lost a position that follows a quoted name in one natural member entry: ' . wp_json_encode( $team_natural_validation['errors'] ?? [] ) );
$team_natural_items = (array) ( $team_natural_plan['sections'][0]['children'][0]['items'] ?? [] );
check( count( $team_natural_items ) === 2 && $team_natural_items[0]['name_ref'] === 'team_1_name' && $team_natural_items[0]['position_ref'] === 'team_1_position' && $team_natural_items[1]['name_ref'] === 'team_2_name' && $team_natural_items[1]['position_ref'] === 'team_2_position', 'Team natural member entries were not grouped into exact name/position pairs' );
$team_compact_brief = wpae_brief_ir_parse( 'Блок команды: Алия — архитектор, Тимур — дизайнер.' );
$team_compact_plan = wpae_design_plan_from_brief( $team_compact_brief );
$team_compact_validation = wpae_design_plan_validate( $team_compact_plan, $team_compact_brief );
$team_compact_items = (array) ( $team_compact_plan['sections'][0]['children'][0]['items'] ?? [] );
check( empty( $team_compact_validation['errors'] ) && count( $team_compact_items ) === 2 && [ $team_compact_items[0]['name_ref'], $team_compact_items[0]['position_ref'], $team_compact_items[1]['name_ref'], $team_compact_items[1]['position_ref'] ] === [ 'team_1_name', 'team_1_position', 'team_2_name', 'team_2_position' ], 'Compact name-role team pairs must enter the typed DesignPlan without out-of-range errors' );
$team_audit_message = "Блок команды. Участник 1 — имя: «Тестовый архитектор». Участник 1 — должность: «Архитектор». Участник 2 — имя: «Тестовый инженер». Участник 2 — должность: «Инженер-конструктор».";
$team_audit_plan = wpae_llm_content_plan( $team_audit_message, 'team' );
$team_audit_cards = container_node( 'team-audit-root', [], [
	container_node( 'team-audit-card-1', [], [ widget( 'team-audit-name-1', 'heading', [ 'title' => 'Тестовый архитектор' ] ), widget( 'team-audit-role-1', 'text-editor', [ 'editor' => 'Архитектор' ] ) ] ),
	container_node( 'team-audit-card-2', [], [ widget( 'team-audit-name-2', 'heading', [ 'title' => 'Тестовый инженер' ] ), widget( 'team-audit-role-2', 'text-editor', [ 'editor' => 'Инженер-конструктор' ] ) ] ),
] );
$team_audit = wpae_llm_content_plan_audit( $team_audit_plan, [ $team_audit_cards ] );
check( $team_audit_plan['repeatable_units'] === 2 && count( $team_audit_plan['content_pairs'] ) === 2 && ! empty( $team_audit['ok'] ), 'Team audit must count each grouped person once rather than counting name and role as separate cards: ' . wp_json_encode( [ 'units' => $team_audit_plan['repeatable_units'], 'pairs' => count( $team_audit_plan['content_pairs'] ), 'audit' => $team_audit ], JSON_UNESCAPED_UNICODE ) );
$team_icon_box_changes = 0;
$team_icon_box_tree = [ container_node( 'team-icon-box-root', [], [ widget( 'team-icon-box', 'icon-box', [ 'title_text' => 'Тестовый архитектор', 'description_text' => 'Архитектор', 'selected_icon' => [ 'value' => 'fas fa-user', 'library' => 'fa-solid' ] ] ) ] ) ];
$team_native_tree = wpae_llm_convert_icon_boxes_to_native_widgets( $team_icon_box_tree, $team_icon_box_changes, false );
$team_native_widgets = [];
$walk_team_native = static function ( array $nodes ) use ( &$walk_team_native, &$team_native_widgets ): void { foreach ( $nodes as $node ) { if ( ! is_array( $node ) ) { continue; } if ( ( $node['elType'] ?? '' ) === 'widget' ) { $team_native_widgets[] = $node['widgetType'] ?? ''; } $walk_team_native( (array) ( $node['elements'] ?? [] ) ); } };
$walk_team_native( $team_native_tree );
check( ! in_array( 'icon', $team_native_widgets, true ) && ! in_array( 'icon-box', $team_native_widgets, true ) && in_array( 'heading', $team_native_widgets, true ) && in_array( 'text-editor', $team_native_widgets, true ), 'Team icon-box normalization must retain native name/role copy without introducing forbidden icon widgets: ' . wp_json_encode( $team_native_widgets ) );
$team_fallback_prompt = "Блок команды\nУчастник 1 — имя: «Синтетический участник 1»\nУчастник 1 — должность: «Демо-архитектор»\nУчастник 2 — имя: «Синтетический участник 2»\nУчастник 2 — должность: «Демо-руководитель проекта»";
$team_requested_content = wpae_llm_extract_requested_content( $team_fallback_prompt );
check( $team_requested_content === [ 'Синтетический участник 1', 'Демо-архитектор', 'Синтетический участник 2', 'Демо-руководитель проекта' ], 'Team content fidelity treats quoted name and position values as content, not their field labels: ' . wp_json_encode( $team_requested_content, JSON_UNESCAPED_UNICODE ) );
$team_fallback = wpae_llm_build_fallback_action( $team_fallback_prompt, 42 );
$team_content_plan = wpae_llm_content_plan( $team_fallback_prompt, 'team' );
$team_star_tree = [ container_node( 'team-root', [ '_css_classes' => 'wpae-generated-root wpae-system-test' ], [
	container_node( 'team-grid', [], [
		container_node( 'team-card-1', [], [ widget( 'team-name-1', 'heading', [ 'title' => 'Синтетический участник 1' ] ), widget( 'team-position-1', 'text-editor', [ 'editor' => 'Демо-архитектор' ] ), widget( 'team-rating-star', 'icon', [ 'icon' => [ 'value' => 'fas fa-star', 'library' => 'fa-solid' ] ] ) ] ),
		container_node( 'team-card-2', [], [ widget( 'team-name-2', 'heading', [ 'title' => 'Синтетический участник 2' ] ), widget( 'team-position-2', 'text-editor', [ 'editor' => 'Демо-руководитель проекта' ] ) ] ),
	] ),
] ) ];
$team_star_audit = wpae_llm_content_plan_audit( $team_content_plan, $team_star_tree );
check( in_array( 'icon', (array) ( $team_content_plan['forbidden_widgets'] ?? [] ), true ) && in_array( 'icon', (array) ( $team_star_audit['forbidden_widgets'] ?? [] ), true ), 'Team semantic contract rejects an unsolicited decorative/rating icon instead of saving it as part of a person card' );
$team_fallback_nodes = [];
$collect_team_fallback_nodes = static function ( array $nodes ) use ( &$collect_team_fallback_nodes, &$team_fallback_nodes ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$team_fallback_nodes[] = $node;
		$collect_team_fallback_nodes( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_team_fallback_nodes( (array) ( $team_fallback['elements'] ?? [] ) );
$team_fallback_grid = array_values( array_filter( $team_fallback_nodes, static fn( array $node ): bool => ( $node['elType'] ?? '' ) === 'container' && ( $node['id'] ?? '' ) === 'llm-team-grid' ) )[0] ?? [];
$team_fallback_cards = (array) ( $team_fallback_grid['elements'] ?? [] );
$team_fallback_rows = array_map( static function ( array $card ): array {
	$heading = array_values( array_filter( (array) ( $card['elements'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'heading' ) )[0] ?? [];
	$body = array_values( array_filter( (array) ( $card['elements'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'text-editor' ) )[0] ?? [];
	return [ 'name' => (string) ( $heading['settings']['title'] ?? '' ), 'position' => (string) ( $body['settings']['editor'] ?? '' ) ];
}, $team_fallback_cards );
check( count( $team_fallback_cards ) === 2, 'Team deterministic fallback groups name and position fields into exactly one card per member' );
check( $team_fallback_rows === [ [ 'name' => 'Синтетический участник 1', 'position' => 'Демо-архитектор' ], [ 'name' => 'Синтетический участник 2', 'position' => 'Демо-руководитель проекта' ] ], 'Team fallback preserves exact member copy in the matching native heading and text widgets: ' . wp_json_encode( $team_fallback_rows, JSON_UNESCAPED_UNICODE ) );

$testimonials_prompt = 'Создай блок отзывов: отзыв 1 — текст «Согласование прошло легко и спокойно»; отзыв 1 — автор «Динара»; отзыв 2 — текст «Получили ясный план действий»; отзыв 2 — автор «Марат».';
$testimonials_compact_prompt = 'Блок отзывов: отзыв 1 — текст «Понятно, как проходит работа», автор «Алия». Отзыв 2 — текст «План быстро согласовали», автор «Тимур».';
$testimonials_compact_brief = wpae_brief_ir_parse( $testimonials_compact_prompt );
$testimonials_compact_plan = wpae_design_plan_from_brief( $testimonials_compact_brief );
$testimonials_compact_validation = wpae_design_plan_validate( $testimonials_compact_plan, $testimonials_compact_brief );
$testimonials_compact_items = (array) ( $testimonials_compact_plan['sections'][0]['children'][0]['items'] ?? [] );
check( empty( $testimonials_compact_validation['errors'] ) && count( $testimonials_compact_items ) === 2 && array_column( $testimonials_compact_items, 'author_ref' ) === [ 'testimonial_1_author', 'testimonial_2_author' ], 'Compact review quote/author pairs must compile into two correctly grouped valid testimonial items: ' . wp_json_encode( [ 'errors' => $testimonials_compact_validation['errors'] ?? [], 'items' => $testimonials_compact_items ] ) );
$testimonials_natural_prompt = 'Отзывы: «Понятно, как проходит работа» — Алия; «План быстро согласовали» — Тимур.';
$testimonials_natural_brief = wpae_brief_ir_parse( $testimonials_natural_prompt );
$testimonials_natural_plan = wpae_design_plan_from_brief( $testimonials_natural_brief );
$testimonials_natural_validation = wpae_design_plan_validate( $testimonials_natural_plan, $testimonials_natural_brief );
$testimonials_natural_items = (array) ( $testimonials_natural_plan['sections'][0]['children'][0]['items'] ?? [] );
check( empty( $testimonials_natural_validation['errors'] ) && count( $testimonials_natural_items ) === 2 && array_column( $testimonials_natural_items, 'author_ref' ) === [ 'testimonial_1_author', 'testimonial_2_author' ] && array_column( $testimonials_natural_items, 'quote_ref' ) === [ 'testimonial_1_quote', 'testimonial_2_quote' ], 'Natural quote-dash-author testimonial briefs must group each quote with its author and pass DesignPlan validation' );
$testimonials_library_template = [ container_node( 'testimonial-library-root', [], [
	container_node( 'testimonial-library-card-1', [], [ widget( 'testimonial-library-widget-1', 'testimonial', [ 'testimonial_content' => 'Source quote one', 'testimonial_name' => 'Source author one' ] ) ] ),
	container_node( 'testimonial-library-card-2', [], [ widget( 'testimonial-library-widget-2', 'testimonial', [ 'testimonial_content' => 'Source quote two', 'testimonial_name' => 'Source author two' ] ) ] ),
] ) ];
$testimonials_library_changes = 0;
$testimonials_library_adapted = wpae_llm_apply_library_template( $testimonials_library_template, $testimonials_compact_prompt, 'testimonials', $testimonials_library_changes );
$testimonials_library_widgets = [];
$collect_testimonials_library_widgets = static function ( array $nodes ) use ( &$collect_testimonials_library_widgets, &$testimonials_library_widgets ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'testimonial' ) { $testimonials_library_widgets[] = (array) ( $node['settings'] ?? [] ); }
		$collect_testimonials_library_widgets( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_testimonials_library_widgets( $testimonials_library_adapted );
check( count( $testimonials_library_widgets ) === 2 && array_column( $testimonials_library_widgets, 'testimonial_name' ) === [ 'Алия', 'Тимур' ] && array_column( $testimonials_library_widgets, 'testimonial_content' ) === [ 'Понятно, как проходит работа', 'План быстро согласовали' ], 'Library adaptation must use typed BriefIR quote-author pairs instead of generic unrelated label pairs' );
$team_library_prompt = 'Блок команды: Алия — архитектор, Тимур — дизайнер.';
$team_library_template = [ container_node( 'team-library-root', [], [
	container_node( 'team-library-card-1', [], [ widget( 'team-library-name-1', 'heading', [ 'title' => 'Source name one' ] ), widget( 'team-library-position-1', 'text-editor', [ 'editor' => 'Source role one' ] ) ] ),
	container_node( 'team-library-card-2', [], [ widget( 'team-library-name-2', 'heading', [ 'title' => 'Source name two' ] ), widget( 'team-library-position-2', 'text-editor', [ 'editor' => 'Source role two' ] ) ] ),
] ) ];
$team_library_changes = 0;
$team_library_adapted = wpae_llm_apply_library_template( $team_library_template, $team_library_prompt, 'team', $team_library_changes );
$team_library_copy = wpae_llm_collect_action_content( $team_library_adapted );
check( count( $team_library_adapted ) === 1 && str_contains( $team_library_copy, 'Алия' ) && str_contains( $team_library_copy, 'архитектор' ) && str_contains( $team_library_copy, 'Тимур' ) && str_contains( $team_library_copy, 'дизайнер' ), 'Library adaptation must use typed BriefIR member pairs for the compact natural Team brief' );
$team_library_preflight = wpae_llm_preflight_library_candidates( [
	'status' => 'matched', 'candidate_count' => 4,
	'preflight_candidates' => [
		[ 'choice_key' => 'candidate_1', 'title' => 'Not adaptable', 'elementor_data' => [ container_node( 'team-invalid-root', [], [ widget( 'team-invalid-text', 'text-editor', [ 'editor' => 'Unrelated' ] ) ] ) ] ],
		[ 'choice_key' => 'candidate_2', 'title' => 'Also not adaptable', 'elementor_data' => [ container_node( 'team-invalid-root-2', [], [ widget( 'team-invalid-text-2', 'text-editor', [ 'editor' => 'Unrelated' ] ) ] ) ] ],
		[ 'choice_key' => 'candidate_3', 'title' => 'Still not adaptable', 'elementor_data' => [ container_node( 'team-invalid-root-3', [], [ widget( 'team-invalid-text-3', 'text-editor', [ 'editor' => 'Unrelated' ] ) ] ) ] ],
		[ 'choice_key' => 'candidate_4', 'title' => 'Adaptable team', 'elementor_data' => $team_library_template ],
	],
], $team_library_prompt, 'team', wpae_llm_content_plan( $team_library_prompt, 'team' ), 42 );
check( count( (array) ( $team_library_preflight['selection_candidates'] ?? [] ) ) === 1 && ( $team_library_preflight['selection_candidates'][0]['choice_key'] ?? '' ) === 'candidate_1' && ( $team_library_preflight['selection_candidates'][0]['title'] ?? '' ) === 'Adaptable team', 'Library agent is offered only candidates that pass the production adapter, native shape, fidelity, and semantic checks' );
$team_preflight_elements = (array) ( $team_library_preflight['selection_candidates'][0]['_wpae_preflight_elements'] ?? [] );
$team_preflight_resolution = wpae_llm_resolve_library_choice( $team_library_preflight, 'candidate_1' );
$team_selected_preflight_elements = (array) ( $team_preflight_resolution['selected']['_wpae_preflight_elements'] ?? [] );
check( ! empty( $team_preflight_elements ) && ! empty( $team_preflight_resolution['ok'] ) && wp_json_encode( $team_selected_preflight_elements ) === wp_json_encode( $team_preflight_elements ) && wpae_llm_content_plan_audit( wpae_llm_content_plan( $team_library_prompt, 'team' ), $team_selected_preflight_elements )['ok'], 'Library selection carries forward the exact server-adapted tree that passed preflight, rather than adapting the chosen candidate again after the provider response' );
$testimonial_requested = wpae_llm_extract_requested_content( $testimonials_prompt );
check( $testimonial_requested === [ 'Согласование прошло легко и спокойно', 'Динара', 'Получили ясный план действий', 'Марат' ], 'Testimonials fidelity extracts only quoted semantic fields, not slot labels: ' . wp_json_encode( $testimonial_requested, JSON_UNESCAPED_UNICODE ) );
$testimonial_plan = wpae_llm_content_plan( $testimonials_prompt, 'testimonials' );
check( count( (array) ( $testimonial_plan['content_pairs'] ?? [] ) ) === 2 && ( $testimonial_plan['content_pairs'][0]['label'] ?? '' ) === 'Динара' && ( $testimonial_plan['content_pairs'][0]['content'] ?? '' ) === 'Согласование прошло легко и спокойно' && ( $testimonial_plan['repeatable_units'] ?? 0 ) === 2, 'Testimonials semantic plan groups one quote and author per review instead of treating field labels as four cards' );
check( empty( $testimonial_plan['explicit_cta'] ) && empty( wpae_llm_extract_requested_ctas( $testimonials_prompt ) ), 'Testimonials author names are not misclassified as CTA labels' );
$testimonial_fallback = wpae_llm_build_fallback_action( $testimonials_prompt, 42 );
$testimonial_nodes = [];
$collect_testimonial_nodes = static function ( array $nodes ) use ( &$collect_testimonial_nodes, &$testimonial_nodes ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$testimonial_nodes[] = $node;
		$collect_testimonial_nodes( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_testimonial_nodes( (array) ( $testimonial_fallback['elements'] ?? [] ) );
$testimonial_grid = array_values( array_filter( $testimonial_nodes, static fn( array $node ): bool => ( $node['elType'] ?? '' ) === 'container' && ( $node['id'] ?? '' ) === 'llm-testimonial-grid' ) )[0] ?? [];
$testimonial_cards = (array) ( $testimonial_grid['elements'] ?? [] );
$testimonial_rows = array_map( static function ( array $card ): array {
	$quote = array_values( array_filter( (array) ( $card['elements'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'text-editor' ) )[0] ?? [];
	$author = array_values( array_filter( (array) ( $card['elements'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'heading' ) )[0] ?? [];
	return [ 'quote' => (string) ( $quote['settings']['editor'] ?? '' ), 'author' => (string) ( $author['settings']['title'] ?? '' ) ];
}, $testimonial_cards );
check( count( $testimonial_cards ) === 2 && $testimonial_rows === [ [ 'quote' => 'Согласование прошло легко и спокойно', 'author' => 'Динара' ], [ 'quote' => 'Получили ясный план действий', 'author' => 'Марат' ] ], 'Testimonials fallback writes only two native quote/author cards with the correct pairing: ' . wp_json_encode( $testimonial_rows, JSON_UNESCAPED_UNICODE ) );
check( ! empty( wpae_llm_content_plan_audit( $testimonial_plan, (array) ( $testimonial_fallback['elements'] ?? [] ) )['ok'] ), 'Testimonials audit accepts exactly the two grouped review cards without an unrequested button' );
check( empty( wpae_llm_content_fidelity( $testimonials_prompt, (array) ( $testimonial_fallback['elements'] ?? [] ) )['missing'] ) && ! str_contains( wp_json_encode( $testimonial_fallback['elements'], JSON_UNESCAPED_UNICODE ), 'отзыв 1' ), 'Testimonials fallback passes fidelity without exposing field labels as visible copy' );
$testimonial_pipeline = (array) $testimonial_fallback['elements'];
$testimonial_pipeline_changed = 0;
$testimonial_pipeline = wpae_llm_normalize_generated_typography( $testimonial_pipeline, 'testimonials', 0, $testimonial_pipeline_changed );
$testimonial_pipeline = wpae_llm_apply_bento_layout( $testimonial_pipeline, 'testimonials', $testimonial_pipeline_changed );
$testimonial_pipeline = wpae_llm_repair_unbalanced_repeatable_layout( $testimonial_pipeline, $testimonials_prompt, 'testimonials', $testimonial_pipeline_changed );
$testimonial_pipeline = wpae_llm_apply_generation_visual_grammar( $testimonial_pipeline, 'testimonials', $testimonial_pipeline_changed );
wpae_llm_normalize_bento_grids_recursive( $testimonial_pipeline, $testimonial_pipeline_changed, 'testimonials' );
$testimonial_pipeline = wpae_llm_normalize_native_visual_contract( $testimonial_pipeline, $testimonials_prompt, 'testimonials', $testimonial_pipeline_changed );
$testimonial_root = (array) ( $testimonial_pipeline[0] ?? [] );
$testimonial_root_children = (array) ( $testimonial_root['elements'] ?? [] );
$testimonial_pipeline_root_classes = preg_split( '/\s+/', trim( (string) ( $testimonial_root['settings']['_css_classes'] ?? '' ) ) );
$testimonial_pipeline_grid_count = 0;
$count_testimonial_grids = static function ( array $nodes ) use ( &$count_testimonial_grids, &$testimonial_pipeline_grid_count ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$classes = preg_split( '/\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) );
		if ( is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ) && count( (array) ( $node['elements'] ?? [] ) ) === 2 ) { $testimonial_pipeline_grid_count++; }
		$count_testimonial_grids( (array) ( $node['elements'] ?? [] ) );
	}
};
$count_testimonial_grids( $testimonial_root_children );
check( count( $testimonial_root_children ) === 2 && ! in_array( 'wpae-bento-grid', $testimonial_pipeline_root_classes, true ), 'Complete Testimonials fallback pipeline keeps the badge and full-width content shell above the repeatable grid' );
check( $testimonial_pipeline_grid_count === 1, 'Complete Testimonials fallback pipeline creates one two-card grid inside the content shell' );

$services_message = "Блок услуг\nЗаголовок: «Наши услуги»\nНадзаголовок: «УСЛУГИ»\nУслуга 1 — название: «Стратегия проекта»\nУслуга 1 — описание: «Формулируем задачу и согласуем план работ.»\nУслуга 2 — название: «Архитектура и дизайн»\nУслуга 2 — описание: «Разрабатываем решение под заданный контекст.»\nУслуга 3 — название: «Сопровождение»\nУслуга 3 — описание: «Проверяем соответствие согласованному проекту.»";
$services_colon_prompt = 'Услуги: стратегия проекта — формулируем задачу и согласуем план работ; архитектура и дизайн — разрабатываем решение под заданный контекст; сопровождение — проверяем соответствие согласованному проекту.';
check( ( wpae_brief_ir_parse( $services_colon_prompt )['intent']['archetype'] ?? '' ) === 'services' && wpae_llm_detect_block_archetype( $services_colon_prompt ) === 'services', 'A natural “Услуги:” lead keeps the production classifier aligned with BriefIR instead of routing service copy to portfolio' );

$services_pairs = wpae_llm_extract_services_content( $services_message );
$services_plan = wpae_llm_content_plan( $services_message, 'services' );
$services_requested = wpae_llm_extract_requested_content( $services_message );
check( count( $services_pairs ) === 3 && ( $services_pairs[2]['label'] ?? '' ) === 'Сопровождение' && ( $services_pairs[2]['content'] ?? '' ) === 'Проверяем соответствие согласованному проекту.', 'Services extraction groups exact labels and descriptions into three semantic cards' );
check( count( (array) ( $services_plan['content_pairs'] ?? [] ) ) === 3 && count( (array) ( $services_plan['content_units'] ?? [] ) ) === 8 && ! str_contains( wp_json_encode( $services_plan, JSON_UNESCAPED_UNICODE ), 'сам выберет подходящий шаблон' ), 'Services content plan keeps required copy and excludes the instruction tail from generated content' );
check( ! in_array( 'сам выберет подходящий шаблон', $services_requested, true ) && in_array( 'Наши услуги', $services_requested, true ), 'Services fidelity compares user copy while ignoring agent instructions' );

$services_layout_prompt = "Создай на этой странице один блок услуг для архитектурной студии. Заголовок секции: «Услуги архитектурной студии». Описание секции: «От первого замысла до авторского сопровождения.»\nУслуга 1 — название: «Стратегия проекта». Описание: «Формулируем задачу и согласуем план работ.»\nУслуга 2 — название: «Архитектура и дизайн». Описание: «Разрабатываем решение под заданный контекст.»\nУслуга 3 — название: «Сопровождение». Описание: «Проверяем соответствие согласованному проекту.»";
$services_layout_brief = wpae_brief_ir_parse( $services_layout_prompt );
$services_layout_roles = [];
foreach ( (array) ( $services_layout_brief['content'] ?? [] ) as $item ) {
	if ( is_array( $item ) ) {
		$services_layout_roles[ (string) ( $item['exact_text'] ?? '' ) ] = [ (string) ( $item['role'] ?? '' ), (string) ( $item['group_id'] ?? '' ) ];
	}
}
check( ( $services_layout_roles['Услуги архитектурной студии'][0] ?? '' ) === 'title' && ( $services_layout_roles['От первого замысла до авторского сопровождения.'][0] ?? '' ) === 'body', 'Services section heading and description keep their semantic BriefIR slots' );
check( ( $services_layout_roles['Формулируем задачу и согласуем план работ.'] ?? [] ) === [ 'service_body', 'service_1' ] && ( $services_layout_roles['Разрабатываем решение под заданный контекст.'] ?? [] ) === [ 'service_body', 'service_2' ] && ( $services_layout_roles['Проверяем соответствие согласованному проекту.'] ?? [] ) === [ 'service_body', 'service_3' ], 'Inline service descriptions remain grouped with the matching service title instead of becoming hero body content' );
$services_layout_fallback = wpae_llm_build_fallback_action( $services_layout_prompt, 42 );
$services_layout_visual_changes = 0;
$services_layout_visual = wpae_llm_apply_generation_visual_grammar( (array) ( $services_layout_fallback['elements'] ?? [] ), 'services', $services_layout_visual_changes );
$services_layout_text = wp_json_encode( $services_layout_visual, JSON_UNESCAPED_UNICODE );
check( str_contains( $services_layout_text, 'Услуги архитектурной студии' ) && str_contains( $services_layout_text, 'От первого замысла до авторского сопровождения.' ) && str_contains( $services_layout_text, 'УСЛУГИ' ) && ! str_contains( $services_layout_text, 'НОВЫЙ БЛОК' ), 'Services fallback renders the exact section copy and a family-specific badge label' );

$services_one_line = "Услуга 1: «Стратегия проекта» — «Формулируем задачу и согласуем план работ.»\nУслуга 2: «Архитектура и дизайн» — «Разрабатываем решение под заданный контекст.»\nУслуга 3: «Сопровождение» — «Проверяем соответствие согласованному проекту.»";
$services_one_line_brief = wpae_brief_ir_parse( $services_one_line );
$services_one_line_pairs = wpae_llm_extract_services_content( $services_one_line );
$services_one_line_spans_ok = true;
foreach ( (array) ( $services_one_line_brief['content'] ?? [] ) as $service_item ) {
	if ( ! is_array( $service_item ) || ! in_array( (string) ( $service_item['role'] ?? '' ), [ 'service_title', 'service_body' ], true ) ) { continue; }
	$span = (array) ( $service_item['source_span'] ?? [] );
	$services_one_line_spans_ok = $services_one_line_spans_ok && substr( $services_one_line, (int) ( $span[0] ?? -1 ), (int) ( ( $span[1] ?? 0 ) - ( $span[0] ?? 0 ) ) ) === (string) ( $service_item['exact_text'] ?? '' );
}
check( ( $services_one_line_brief['intent']['archetype'] ?? '' ) === 'services' && $services_one_line_pairs === $services_pairs, 'Natural one-line service pairs produce the same three exact semantic items as the canonical slot form' );
check( count( array_filter( (array) ( $services_one_line_brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'service_title', 'service_body' ], true ) ) ) === 6 && $services_one_line_spans_ok, 'One-line service fields retain their exact values, stable group slots, and source spans' );
$services_inline_prompt = 'Блок услуг. Надзаголовок «УСЛУГИ», заголовок «Наши услуги». Услуга 1: «Стратегия проекта» — «Формулируем задачу и согласуем план работ». Услуга 2: «Архитектура и дизайн» — «Разрабатываем решение под заданный контекст». Услуга 3: «Сопровождение» — «Проверяем соответствие согласованному проекту».';
$services_inline_brief = wpae_brief_ir_parse( $services_inline_prompt );
$services_inline_plan = wpae_design_plan_from_brief( $services_inline_brief );
$services_inline_validation = wpae_design_plan_validate( $services_inline_plan, $services_inline_brief );
$services_inline_items = [];
foreach ( (array) ( $services_inline_plan['sections'][0]['children'] ?? [] ) as $service_child ) {
	if ( is_array( $service_child ) && ( $service_child['role'] ?? '' ) === 'service_cards' ) { $services_inline_items = (array) ( $service_child['items'] ?? [] ); }
}
check( ( $services_inline_brief['intent']['archetype'] ?? '' ) === 'services' && count( $services_inline_items ) === 3 && ! empty( $services_inline_validation['ok'] ), 'A concise single-paragraph Services prompt builds three complete typed items without forced line breaks' );
$services_inline_copy = [];
foreach ( (array) ( $services_inline_brief['content'] ?? [] ) as $item ) {
	if ( is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'service_title', 'service_body' ], true ) ) { $services_inline_copy[] = (string) ( $item['exact_text'] ?? '' ); }
}
check( $services_inline_copy === [ 'Стратегия проекта', 'Формулируем задачу и согласуем план работ', 'Архитектура и дизайн', 'Разрабатываем решение под заданный контекст', 'Сопровождение', 'Проверяем соответствие согласованному проекту' ], 'Single-paragraph Services extraction preserves the exact requested six values in order' );
$services_semicolon_prompt = 'Блок услуг. Услуга 1: «Архитектурное проектирование» — «Концепция и планировка»; Услуга 2: «Рабочая документация» — «Чертежи и спецификации»; Услуга 3: «Авторский надзор» — «Контроль соответствия проекту».';
$services_semicolon_brief = wpae_brief_ir_parse( $services_semicolon_prompt );
$services_semicolon_pairs = wpae_llm_extract_services_content( $services_semicolon_prompt );
check( ( $services_semicolon_brief['intent']['archetype'] ?? '' ) === 'services' && count( $services_semicolon_pairs ) === 3 && ( $services_semicolon_pairs[2]['label'] ?? '' ) === 'Авторский надзор' && ( $services_semicolon_pairs[2]['content'] ?? '' ) === 'Контроль соответствия проекту', 'Inline Services pairs separated by semicolons preserve all three exact service slots' );
$services_inline_ambiguous = wpae_brief_ir_parse( 'Блок услуг. Услуга 1: «Стратегия проекта» — «Формулируем задачу». Услуга 2: «Архитектура» — описание без кавычек.' );
check( ! empty( array_filter( (array) ( $services_inline_ambiguous['ambiguities'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['kind'] ?? '' ) === 'incomplete_service_pair' ) ), 'An incomplete inline service pair remains an explicit ambiguity instead of disappearing silently' );
$services_ambiguous_brief = wpae_brief_ir_parse( "Услуга 1: «Стратегия проекта» — без кавычек" );
check( ! empty( array_filter( (array) ( $services_ambiguous_brief['ambiguities'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['kind'] ?? '' ) === 'incomplete_service_pair' ) ), 'An incomplete natural service row is reported as ambiguous instead of paired heuristically' );
$services_mixed_ambiguity_brief = wpae_brief_ir_parse( $services_message . "\nУслуга 4: «Дополнение» — описание без закрытой пары" );
$services_mixed_ambiguity_plan = wpae_design_plan_from_brief( $services_mixed_ambiguity_brief );
$services_mixed_ambiguity_validation = wpae_design_plan_validate( $services_mixed_ambiguity_plan, $services_mixed_ambiguity_brief );
check( empty( $services_mixed_ambiguity_validation['ok'] ) && in_array( 'services_ambiguous_input', (array) ( $services_mixed_ambiguity_validation['errors'] ?? [] ), true ), 'Three complete Services pairs do not hide an additional incomplete service line from the typed plan gate' );

$services_fallback_message = str_replace( "Пусть ИИ-агент сам выберет подходящий шаблон из встроенной библиотеки и применит только проверенный вариант.\n", '', $services_message );
$services_fallback = wpae_llm_build_fallback_action( $services_fallback_message, 42 );
$services_fallback_plan = wpae_llm_content_plan( $services_fallback_message, 'services' );
$services_fallback_audit = wpae_llm_content_plan_audit( $services_fallback_plan, (array) ( $services_fallback['elements'] ?? [] ) );
$services_fallback_json = wp_json_encode( $services_fallback['elements'] ?? [], JSON_UNESCAPED_UNICODE );
$expected_services_pairs = [
	[ 'Стратегия проекта', 'Формулируем задачу и согласуем план работ.' ],
	[ 'Архитектура и дизайн', 'Разрабатываем решение под заданный контекст.' ],
	[ 'Сопровождение', 'Проверяем соответствие согласованному проекту.' ],
];
$collect_service_card_pairs = static function ( array $nodes ) use ( $expected_services_pairs ): array {
	$copy = wpae_llm_normalize_content_text( wpae_llm_collect_action_content( $nodes ) );
	return array_values( array_filter( $expected_services_pairs, static function ( array $pair ) use ( $copy ): bool {
		return strpos( $copy, wpae_llm_normalize_content_text( $pair[0] ) ) !== false && strpos( $copy, wpae_llm_normalize_content_text( $pair[1] ) ) !== false;
	} ) );
};
$services_fallback_images = [];
$services_fallback_badges = [];
$collect_services_visuals = static function ( array $nodes ) use ( &$collect_services_visuals, &$services_fallback_images, &$services_fallback_badges ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$settings = (array) ( $node['settings'] ?? [] );
		$classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) ) ?: [];
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'image' ) { $services_fallback_images[] = $settings['image'] ?? []; }
		if ( ( $node['elType'] ?? '' ) === 'container' && in_array( 'wpae-generated-badge', $classes, true ) ) {
			foreach ( (array) ( $node['elements'] ?? [] ) as $badge_child ) {
				if ( is_array( $badge_child ) && ( $badge_child['widgetType'] ?? '' ) === 'heading' ) { $services_fallback_badges[] = (string) ( $badge_child['settings']['title'] ?? '' ); }
			}
		}
		$collect_services_visuals( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_services_visuals( (array) ( $services_fallback['elements'] ?? [] ) );
check( ! empty( $services_fallback_audit['ok'] ) && ( $services_fallback_audit['service_card_count'] ?? 0 ) === 3 && ( $services_fallback_audit['expected_service_card_count'] ?? 0 ) === 3, 'Deterministic Services fallback has three independently verified semantic cards: ' . wp_json_encode( $services_fallback_audit, JSON_UNESCAPED_UNICODE ) );
$services_fallback_pairs = $collect_service_card_pairs( (array) ( $services_fallback['elements'] ?? [] ) );
check( $services_fallback_pairs === $expected_services_pairs && ! str_contains( $services_fallback_json, 'сам выберет подходящий шаблон' ), 'Services fallback keeps all three exact title/body pairs in order and excludes the instruction tail: ' . wp_json_encode( $services_fallback_pairs, JSON_UNESCAPED_UNICODE ) );
check( count( $services_fallback_images ) === 3 && count( array_filter( $services_fallback_images, static fn( $image ): bool => in_array( (string) ( $image['url'] ?? '' ), array_column( wpae_design_plan_default_service_media(), 'source_url' ), true ) && trim( (string) ( $image['alt'] ?? '' ) ) !== '' ) ) === 3 && $services_fallback_badges === [ 'УСЛУГИ' ], 'Services fallback includes one pill and three accessible native Unsplash images in its actual Elementor tree' );
$services_normalized_fallback = wpae_elementor_normalize_data( (array) ( $services_fallback['elements'] ?? [] ) );
$services_normalized_pairs = $collect_service_card_pairs( (array) ( $services_normalized_fallback['data'] ?? [] ) );
$services_normalized_audit = wpae_llm_content_plan_audit( $services_fallback_plan, (array) ( $services_normalized_fallback['data'] ?? [] ) );
check( $services_normalized_pairs === $expected_services_pairs && ! empty( $services_normalized_audit['ok'] ), 'Elementor normalization preserves the same three Services title/body cards before the semantic gate' );
$services_fallback_missing_third = (array) ( $services_fallback['elements'] ?? [] );
$remove_service_card = static function ( array $nodes, string $title ) use ( &$remove_service_card ): array {
	$out = [];
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$direct_title = '';
	foreach ( (array) ( $node['elements'] ?? [] ) as $child ) {
			if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'widget' && ( $child['widgetType'] ?? '' ) === 'heading' ) {
				$direct_title = trim( (string) ( $child['settings']['title'] ?? '' ) );
				break;
			}
		}
		if ( ( $node['elType'] ?? '' ) === 'container' && $direct_title === $title ) { continue; }
		$node['elements'] = $remove_service_card( (array) ( $node['elements'] ?? [] ), $title );
		$out[] = $node;
	}
	return $out;
};
$services_fallback_missing_third = $remove_service_card( $services_fallback_missing_third, 'Сопровождение' );
$services_missing_third_audit = wpae_llm_content_plan_audit( $services_fallback_plan, $services_fallback_missing_third );
check( empty( $services_missing_third_audit['ok'] ) && ( $services_missing_third_audit['service_card_count'] ?? 0 ) === 2 && ( $services_missing_third_audit['expected_service_card_count'] ?? 0 ) === 3, 'Semantic gate still rejects a two-card Services tree for three requested pairs: ' . wp_json_encode( $services_missing_third_audit, JSON_UNESCAPED_UNICODE ) );
$services_fallback_wrong_third = (array) ( $services_fallback['elements'] ?? [] );
$replace_service_body = static function ( array $nodes ) use ( &$replace_service_body ): array {
	foreach ( $nodes as &$node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'text-editor' && trim( (string) ( $node['settings']['editor'] ?? '' ) ) === 'Проверяем соответствие согласованному проекту.' ) {
			$node['settings']['editor'] = 'Описание другой услуги.';
		}
		$node['elements'] = $replace_service_body( (array) ( $node['elements'] ?? [] ) );
	}
	unset( $node );
	return $nodes;
};
$services_fallback_wrong_third = $replace_service_body( $services_fallback_wrong_third );
$services_wrong_third_audit = wpae_llm_content_plan_audit( $services_fallback_plan, $services_fallback_wrong_third );
check( empty( $services_wrong_third_audit['ok'] ) && ( $services_wrong_third_audit['structural_container_count'] ?? 0 ) === 4 && ( $services_wrong_third_audit['service_card_count'] ?? 0 ) === 2, 'Structural containers, including the new pill, cannot mask one incorrect service title/body pair from semantic validation: ' . wp_json_encode( $services_wrong_third_audit, JSON_UNESCAPED_UNICODE ) );
$services_library_only_message = $services_message . "\nПусть ИИ-агент сам выберет подходящий шаблон из встроенной библиотеки и использует только проверенный вариант.";
check( wpae_llm_requires_verified_library_template( $services_library_only_message ) && wpae_llm_forbids_fallback( 'Не используй fallback при отказе провайдера.' ), 'Library-only and explicit no-fallback requests are recognized as write constraints' );
$explicit_library_family_prompts = [
	'mega_menu' => 'Создай отдельный библиотечный блок Мега Меню: пункты «О нас», «Портфолио». В меню есть цены, не меняй тему сайта.',
	'about' => 'Создай отдельный библиотечный блок О нас: заголовок «Наша студия». Цена проекта обсуждается отдельно.',
	'portfolio' => 'Создай отдельный библиотечный блок Портфолио: проект «Дом у озера». Бюджет проекта не публикуй.',
	'carousel' => 'Создай отдельный библиотечный блок Carousel: заголовок секции «С кем мы работаем». Партнёры: «Альфа», «Бета». Описание: «Надёжные партнёры проекта». Глобальную тему сайта не меняй.',
];
foreach ( $explicit_library_family_prompts as $expected_archetype => $family_prompt ) {
	check( wpae_llm_detect_block_archetype( $family_prompt ) === $expected_archetype && ( wpae_brief_ir_parse( $family_prompt )['intent']['archetype'] ?? '' ) === $expected_archetype, 'Explicit library family must outrank incidental pricing copy in both production classifiers: ' . $expected_archetype );
}
check( wpae_llm_requires_library_template( $explicit_library_family_prompts['carousel'] ), 'An explicit library block request is a no-fallback write constraint' );
$carousel_copy_probe = wpae_llm_extract_carousel_content( $explicit_library_family_prompts['carousel'] );
$carousel_requested_content = wpae_llm_extract_requested_content( $explicit_library_family_prompts['carousel'] );
check( $carousel_copy_probe === [ 'title' => 'С кем мы работаем', 'description' => 'Надёжные партнёры проекта', 'partners' => [ 'Альфа', 'Бета' ] ] && $carousel_requested_content === [ 'С кем мы работаем', 'Надёжные партнёры проекта', 'Альфа', 'Бета' ], 'Carousel parser keeps exact labeled copy and partner names while excluding page-wide instructions: ' . wp_json_encode( [ 'copy' => $carousel_copy_probe, 'content' => $carousel_requested_content ], JSON_UNESCAPED_UNICODE ) );
$carousel_source_widget = widget( 'carousel-source', 'image-carousel', [ 'carousel' => [ [ 'id' => 1, 'url' => 'https://example.test/media/partner-one.png' ], [ 'id' => 2, 'url' => 'https://example.test/media/partner-two.png' ] ] ] );
$carousel_adapter_changes = 0;
$carousel_adapted_probe = wpae_llm_apply_library_template( [ $carousel_source_widget ], $explicit_library_family_prompts['carousel'], 'carousel', $carousel_adapter_changes );
$carousel_adapter_json = (string) wp_json_encode( $carousel_adapted_probe, JSON_UNESCAPED_UNICODE );
$carousel_adapter_copy = wpae_llm_collect_action_content( $carousel_adapted_probe );
check( $carousel_adapter_changes > 0 && str_contains( $carousel_adapter_copy, 'С кем мы работаем' ) && str_contains( $carousel_adapter_copy, 'Надёжные партнёры проекта' ) && str_contains( $carousel_adapter_copy, 'Альфа' ) && str_contains( $carousel_adapter_copy, 'Бета' ) && ! str_contains( $carousel_adapter_json, 'Глобальную тему сайта не меняй' ) && ! str_contains( $carousel_adapter_json, 'цены сайта' ), 'Carousel library adapter writes only requested content and never publishes the user instructions: ' . $carousel_adapter_json );

$library_agent_message = '«Пространство для идей». Hero. Описание: «Опишите задачу и получите понятный первый шаг». Кнопка: «Начать проект», ссылка #contact.';
$library_agent_root = container_node( 'imported-hero-library-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-library-agent-fixture-candidate-two wpae-generated-badge' ], [
	widget( 'imported-hero-title', 'heading', [ 'title' => 'Исходный заголовок', 'header_size' => 'h2' ] ),
	widget( 'imported-hero-copy', 'text-editor', [ 'editor' => 'Исходное описание.' ] ),
	widget( 'imported-hero-button', 'button', [ 'text' => 'Исходная кнопка', 'link' => [ 'url' => '#old' ] ] ),
] );
$library_agent_first_root = container_node( 'unselected-hero-library-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-library-agent-candidate-one' ], [
	widget( 'unselected-hero-title', 'heading', [ 'title' => 'Не выбранный вариант', 'header_size' => 'h2' ] ),
	widget( 'unselected-hero-copy', 'text-editor', [ 'editor' => 'Другой вариант.' ] ),
	widget( 'unselected-hero-button', 'button', [ 'text' => 'Смотреть', 'link' => [ 'url' => '#other' ] ] ),
 ] );
$library_agent_ranked_good = [
	'score' => 9,
	'matched_terms' => [ 'hero' ],
	'summary' => [
		'id' => 503,
		'title' => 'Импортированный hero',
		'description' => 'Hero с заголовком, описанием и CTA',
		'category' => 'hero',
		'template_type' => 'section-hero',
		'tags' => [ 'hero' ],
		'source' => 'plugin_template',
		'status' => 'published',
		'compatibility' => [ 'stats' => [ 'widget_types' => [ 'container', 'heading', 'text-editor', 'button' ], 'media_references' => [] ] ],
	],
	'trusted_bundled' => false,
	'elementor_data' => [ $library_agent_root ],
];
$library_agent_unadaptable = static fn( int $id, string $title ): array => [
	'choice_key' => 'candidate_' . $id,
	'id' => $id,
	'title' => $title,
	'category' => 'hero',
	'source' => 'plugin_template',
	'status' => 'published',
	'trusted_bundled' => false,
	'elementor_data' => [],
];
$GLOBALS['library'] = [
	'status' => 'matched', 'available_count' => 4, 'candidate_count' => 4,
	'candidates' => [
		[ 'choice_key' => 'candidate_1', 'title' => 'Неадаптируемый 1', 'category' => 'hero' ],
		[ 'choice_key' => 'candidate_2', 'title' => 'Неадаптируемый 2', 'category' => 'hero' ],
		[ 'choice_key' => 'candidate_3', 'title' => 'Неадаптируемый 3', 'category' => 'hero' ],
	],
	'selection_candidates' => [
		$library_agent_unadaptable( 500, 'Неадаптируемый 1' ),
		$library_agent_unadaptable( 501, 'Неадаптируемый 2' ),
		$library_agent_unadaptable( 502, 'Неадаптируемый 3' ),
	],
	'preflight_candidates' => [
		$library_agent_unadaptable( 500, 'Неадаптируемый 1' ),
		$library_agent_unadaptable( 501, 'Неадаптируемый 2' ),
		$library_agent_unadaptable( 502, 'Неадаптируемый 3' ),
		$library_agent_ranked_good,
	],
];
$library_agent_action = [
	'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'library_choice' => 'candidate_1',
	'elements' => [],
];
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'active', 'design_engine_mode' => 'active' ];
$GLOBALS['options'][WPAE_LLM_RATE_LIMIT_OPTION] = [];
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['library_retrieval_calls'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $library_agent_action ) ) ];
$library_agent_request = new WP_REST_Request();
$library_agent_request->set_param( 'message', $library_agent_message );
$library_agent_request->set_param( 'context', [ 'post_id' => 42 ] );
$library_agent_response = wpae_llm_chat_request( $library_agent_request );
$library_agent_data = $library_agent_response instanceof WP_REST_Response ? $library_agent_response->get_data() : [];
$library_agent_trace = (array) ( $library_agent_data['library'] ?? [] );
$library_agent_written_root = (array) ( $GLOBALS['page_data'][ count( $legacy_page ) ] ?? [] );
$library_agent_system_prompt = (string) ( $GLOBALS['http_calls'][0]['body']['messages'][0]['content'] ?? '' );
$library_agent_root_json = wp_json_encode( $library_agent_written_root );
$library_agent_written_copy = wpae_llm_collect_action_content( [ $library_agent_written_root ] );
$find_library_agent_button_url = static function ( array $nodes ) use ( &$find_library_agent_button_url ): string {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'button' ) {
			return (string) ( $node['settings']['link']['url'] ?? '' );
		}
		$url = $find_library_agent_button_url( (array) ( $node['elements'] ?? [] ) );
		if ( $url !== '' ) { return $url; }
	}
	return '';
};
$library_agent_button_url = $find_library_agent_button_url( [ $library_agent_written_root ] );
check( ! empty( $library_agent_data['ok'] ) && count( $GLOBALS['http_calls'] ) === 1 && count( $GLOBALS['writes'] ) === 1, 'active supported pipeline delegates one library decision, then uses one existing write boundary' );
check( ( $library_agent_data['diagnostics']['action_path'] ?? '' ) === 'library_agent' && ( $library_agent_data['diagnostics']['design_pipeline']['route_decision']['provider_calls'] ?? null ) === 1 && ( $library_agent_data['diagnostics']['design_pipeline']['route_decision']['precedence'] ?? '' ) === 'active_pipeline_library_decision', 'active pipeline diagnostics expose the bounded library-agent route without invoking EDDE in parallel' );
check( ( $GLOBALS['library_retrieval_calls'][0][2] ?? false ) === true && count( (array) ( $library_agent_trace['candidates'] ?? [] ) ) === 1 && strpos( $library_agent_system_prompt, '"library_choice":"точный choice_key или null"' ) !== false && strpos( $library_agent_system_prompt, 'candidate_1' ) !== false && strpos( $library_agent_system_prompt, 'candidate_2' ) === false && strpos( $library_agent_system_prompt, 'Импортированный hero' ) !== false && strpos( $library_agent_system_prompt, 'Семантический план контента' ) === false && strpos( $library_agent_system_prompt, 'Elementor native Flexbox container/widget objects' ) === false, 'the post-plan library route retrieves all ranked candidates, preflights before provider dispatch, and offers only the later compatible template with its metadata' );
check( ( $library_agent_data['diagnostics']['initial_validation']['validation_scope'] ?? '' ) === 'allowlisted_library_choice' && ! empty( $library_agent_data['diagnostics']['initial_validation']['provider_tree_ignored'] ), 'A library-only model choice bypasses duplicate provider-tree validation and defers checks to the selected server template' );
check( ( $library_agent_trace['selection_source'] ?? '' ) === 'model_choice' && ( $library_agent_trace['model_choice'] ?? '' ) === 'candidate_1' && ( $library_agent_trace['selected']['title'] ?? '' ) === 'Импортированный hero' && ( $library_agent_trace['status'] ?? '' ) === 'applied' && strpos( $library_agent_root_json, 'wpae-library-agent-fixture-candidate-two' ) !== false && strpos( $library_agent_root_json, 'wpae-library-agent-candidate-one' ) === false, 'the model selects the compatible fourth ranked template and the exact preflighted tree reaches the write boundary' );
check( strpos( $library_agent_written_copy, 'Пространство для идей' ) !== false && strpos( $library_agent_written_copy, 'Опишите задачу и получите понятный первый шаг' ) !== false && strpos( $library_agent_written_copy, 'Начать проект' ) !== false && $library_agent_button_url === '#contact', 'active library-agent adaptation preserves exact brief copy and CTA URL' );
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$library_agent_decline = $library_agent_action;
$library_agent_decline['library_choice'] = null;
$library_agent_decline['elements'] = [ container_node( 'unselected-native-fallback', [ 'container_type' => 'flex' ], [ widget( 'unselected-copy', 'text-editor', [ 'editor' => 'Не выбранная библиотека' ] ) ] ) ];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $library_agent_decline, JSON_UNESCAPED_UNICODE ) ) ];
$library_agent_decline_request = new WP_REST_Request();
$library_agent_decline_request->set_param( 'message', $library_agent_message );
$library_agent_decline_request->set_param( 'context', [ 'post_id' => 42 ] );
$library_agent_decline_response = wpae_llm_chat_request( $library_agent_decline_request );
$library_agent_decline_error = $library_agent_decline_response instanceof WP_Error ? $library_agent_decline_response : null;
check( $library_agent_decline_error instanceof WP_Error && $library_agent_decline_error->get_error_code() === 'wpae_llm_library_selection_required', 'Active library route rejects a native composition when the model declines offered compatible templates' );
check( ( $library_agent_decline_error->get_error_data()['details']['write_count'] ?? null ) === 0 && $GLOBALS['writes'] === [] && array_column( $GLOBALS['page_data'], 'id' ) === array_column( $legacy_page, 'id' ), 'Declining offered templates cannot append an unselected design or modify neighboring roots' );
$GLOBALS['library'] = [];

// The user-corrected Services section is offered as a bounded library choice;
// the model selects it, and the existing write path adapts that structure.
$services_template_document = json_decode( (string) file_get_contents( __DIR__ . '/../includes/elementor/imported-templates/services-photo-cards.json' ), true );
$services_template_data = wpae_elementor_normalize_data( (array) ( $services_template_document['content'] ?? [] ) )['data'];
$services_provider_root = container_node( 'services-provider-root', [ 'container_type' => 'flex' ], [
	widget( 'services-provider-heading', 'heading', [ 'title' => 'Услуги архитектурной студии', 'header_size' => 'h2' ] ),
	widget( 'services-provider-description', 'text-editor', [ 'editor' => 'От первого замысла до авторского сопровождения.' ] ),
	container_node( 'services-provider-cards', [ 'container_type' => 'flex' ], [
		container_node( 'services-provider-card-1', [ 'container_type' => 'flex' ], [ widget( 'services-provider-title-1', 'heading', [ 'title' => 'Стратегия проекта' ] ), widget( 'services-provider-copy-1', 'text-editor', [ 'editor' => 'Формулируем задачу и согласуем план работ.' ] ) ] ),
		container_node( 'services-provider-card-2', [ 'container_type' => 'flex' ], [ widget( 'services-provider-title-2', 'heading', [ 'title' => 'Архитектура и дизайн' ] ), widget( 'services-provider-copy-2', 'text-editor', [ 'editor' => 'Разрабатываем решение под заданный контекст.' ] ) ] ),
		container_node( 'services-provider-card-3', [ 'container_type' => 'flex' ], [ widget( 'services-provider-title-3', 'heading', [ 'title' => 'Сопровождение' ] ), widget( 'services-provider-copy-3', 'text-editor', [ 'editor' => 'Проверяем соответствие согласованному проекту.' ] ) ] ),
	] ),
 ] );
$services_adaptation_changes = 0;
$services_adapted_probe = wpae_llm_apply_library_template( $services_template_data, $services_message, 'services', $services_adaptation_changes );
$services_probe_action = [ 'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'elements' => $services_adapted_probe ];
$services_probe_shape = wpae_llm_validate_action_shape( $services_probe_action, 42 );
$services_probe_fidelity = wpae_llm_content_fidelity( $services_message, $services_adapted_probe );
$services_probe_audit = wpae_llm_content_plan_audit( $services_plan, $services_adapted_probe );
$services_probe = [ 'changes' => $services_adaptation_changes, 'shape' => $services_probe_shape, 'fidelity' => $services_probe_fidelity, 'audit' => $services_probe_audit ];
check( ! empty( $services_probe_shape['ok'] ) && ! empty( $services_probe_fidelity['ok'] ) && ! empty( $services_probe_audit['ok'] ), 'Imported Services card adapter must satisfy native shape, exact content, and repeatable-plan checks: ' . wp_json_encode( $services_probe, JSON_UNESCAPED_UNICODE ) );
check( $collect_service_card_pairs( $services_adapted_probe ) === $expected_services_pairs, 'The imported Course Boxes adapter maps three service pairs in order without an ID forced by the application' );
$services_split_copy = $services_adapted_probe;
$services_split_body = null;
$extract_services_split_body = static function ( array &$nodes ) use ( &$extract_services_split_body, &$services_split_body, $expected_services_pairs ): bool {
	foreach ( $nodes as $index => &$node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'text-editor' && wpae_llm_normalize_content_text( (string) ( $node['settings']['editor'] ?? '' ) ) === wpae_llm_normalize_content_text( (string) $expected_services_pairs[2][1] ) ) {
			$services_split_body = $node;
			unset( $nodes[ $index ] );
			$nodes = array_values( $nodes );
			return true;
		}
		if ( is_array( $node['elements'] ?? null ) && $extract_services_split_body( $node['elements'] ) ) { return true; }
	}
	return false;
};
$services_split_removed = $extract_services_split_body( $services_split_copy );
if ( is_array( $services_split_body ) ) { $services_split_copy[] = $services_split_body; }
$services_split_audit = wpae_llm_content_plan_audit( $services_plan, $services_split_copy );
check( $services_split_removed && empty( $services_split_audit['ok'] ) && ( $services_split_audit['service_card_count'] ?? 0 ) === 2, 'Nested Services audit rejects a body moved outside its own card instead of pairing it at the section wrapper' );
$services_command_prompt = "Создай отдельную секцию услуг на post=5214\nУслуга 1 — название: «Стратегия проекта»\nУслуга 1 — описание: «Формулируем задачу и согласуем план работ.»\nУслуга 2 — название: «Архитектура и дизайн»\nУслуга 2 — описание: «Разрабатываем решение под заданный контекст.»\nУслуга 3 — название: «Сопровождение»\nУслуга 3 — описание: «Проверяем соответствие согласованному проекту.»";
$services_command_changes = 0;
$services_command_tree = wpae_llm_apply_library_template( $services_template_data, $services_command_prompt, 'services', $services_command_changes );
wpae_llm_clear_unrequested_library_copy( $services_command_tree, $services_command_prompt, $services_command_changes );
$services_command_layout_changes = 0;
$services_command_tree = wpae_llm_normalize_library_layout( $services_command_tree, $services_command_layout_changes, 'services' );
$services_root_padding = (array) ( $services_command_tree[0]['settings']['padding'] ?? [] );
$services_root_padding_tablet = (array) ( $services_command_tree[0]['settings']['padding_tablet'] ?? [] );
$services_root_padding_mobile = (array) ( $services_command_tree[0]['settings']['padding_mobile'] ?? [] );
check( ( $services_root_padding['left'] ?? null ) === '4.5' && ( $services_root_padding['right'] ?? null ) === '4.5' && ( $services_root_padding_tablet['left'] ?? null ) === '0' && ( $services_root_padding_tablet['right'] ?? null ) === '0' && ( $services_root_padding_mobile['left'] ?? null ) === '2' && ( $services_root_padding_mobile['right'] ?? null ) === '2', 'Services outer container preserves reference horizontal padding on desktop, tablet, and mobile' );
$services_command_json = wp_json_encode( $services_command_tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$services_command_headings = [];
$services_command_badges = [];
$services_command_spacers = 0;
$services_command_dividers = 0;
$services_command_globals = 0;

$collect_services_command_nodes = static function ( array $nodes ) use ( &$collect_services_command_nodes, &$services_command_headings, &$services_command_badges, &$services_command_spacers, &$services_command_dividers, &$services_command_globals ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'heading' ) {
			$services_command_headings[] = trim( (string) ( $node['settings']['title'] ?? '' ) );
			$classes = preg_split( '/\\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) ) ?: [];
			if ( in_array( 'wpae-generated-badge-label', $classes, true ) ) { $services_command_badges[] = trim( (string) ( $node['settings']['title'] ?? '' ) ); }
		}
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'spacer' ) { $services_command_spacers++; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'divider' ) { $services_command_dividers++; }
		if ( ! empty( $node['settings']['__globals__'] ) ) { $services_command_globals++; }
		$collect_services_command_nodes( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_services_command_nodes( $services_command_tree );
check( in_array( 'Стратегия проекта', $services_command_headings, true ) && ! in_array( 'Услуги', $services_command_headings, true ) && ! str_contains( $services_command_json, 'Создай отдельную секцию услуг на post=5214' ), 'Services instruction-only first line is removed instead of becoming a fabricated section heading' );
check( $services_command_badges === [ 'УСЛУГИ' ] && ! empty( wpae_llm_template_fingerprint( $services_command_tree )['has_badge'] ), 'Library cleanup preserves the generated Services pill while removing other unrequested copy' );
$services_explicit_title_prompt = "Создай отдельную секцию услуг\nЗаголовок: «Услуги архитектурной студии»\nУслуга 1 — название: «Стратегия проекта»\nУслуга 1 — описание: «Формулируем задачу и согласуем план работ.»\nУслуга 2 — название: «Архитектура и дизайн»\nУслуга 2 — описание: «Разрабатываем решение под заданный контекст.»\nУслуга 3 — название: «Сопровождение»\nУслуга 3 — описание: «Проверяем соответствие согласованному проекту.»";
$services_explicit_title_changes = 0;
$services_explicit_title_tree = wpae_llm_apply_library_template( $services_template_data, $services_explicit_title_prompt, 'services', $services_explicit_title_changes );
wpae_llm_clear_unrequested_library_copy( $services_explicit_title_tree, $services_explicit_title_prompt, $services_explicit_title_changes );
$services_explicit_title_text = wpae_llm_collect_action_content( $services_explicit_title_tree );
check( strpos( $services_explicit_title_text, 'Услуги архитектурной студии' ) !== false, 'An explicitly labeled Services title remains intact during library copy cleanup' );
check( $services_command_spacers === 0 && $services_command_dividers === 0 && $services_command_globals === 0 && str_contains( $services_command_json, '"text_color":"#6b7280"' ) && str_contains( $services_command_json, '"title_color":"#111827"' ), 'Services template adaptation removes source spacers/dividers/global styles and applies semantic project colors' );
$GLOBALS['library'] = [
	'status' => 'matched', 'available_count' => 1, 'candidate_count' => 1,
	'candidates' => [ [ 'choice_key' => 'candidate_1', 'title' => 'Services — Photo Cards (User Reference)', 'category' => 'services', 'template_type' => 'section-services', 'source' => 'plugin_template' ] ],
	'selection_candidates' => [ [ 'choice_key' => 'candidate_1', 'id' => 0, 'bundled_fixture_id' => 'template-services-photo-cards-v1', 'title' => 'Services — Photo Cards (User Reference)', 'category' => 'services', 'template_type' => 'section-services', 'source' => 'plugin_template', 'status' => 'published', 'trusted_bundled' => true, 'elementor_data' => $services_template_data ] ],
];
$services_provider_action = [ 'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'library_choice' => 'candidate_1', 'elements' => [ $services_provider_root ] ];
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'active', 'design_engine_mode' => 'active' ];
$GLOBALS['options'][WPAE_LLM_RATE_LIMIT_OPTION] = [];
$GLOBALS['page_data'] = $legacy_page;
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $services_provider_action ) ) ];
$GLOBALS['test_project_typography_tokens'] = true;
$services_request = new WP_REST_Request();
$services_request->set_param( 'message', $services_message );
$services_request->set_param( 'context', [ 'post_id' => 42 ] );
$services_response = wpae_llm_chat_request( $services_request );
unset( $GLOBALS['test_project_typography_tokens'] );
$services_response_data = $services_response instanceof WP_REST_Response ? $services_response->get_data() : [];
$services_trace = (array) ( $services_response_data['library'] ?? [] );
$services_written = array_slice( (array) $GLOBALS['page_data'], count( $legacy_page ) );
$services_written_json = wp_json_encode( $services_written, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$services_heading_values = [];
$services_text_values = [];
$collect_services_written = static function ( array $nodes ) use ( &$collect_services_written, &$services_heading_values, &$services_text_values ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'heading' ) { $services_heading_values[] = (string) ( $node['settings']['title'] ?? '' ); }
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'text-editor' ) { $services_text_values[] = trim( wp_strip_all_tags( (string) ( $node['settings']['editor'] ?? '' ) ) ); }
		$collect_services_written( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_services_written( $services_written );
$services_card_heading_settings = [];
$services_badge_settings = [];
$collect_services_layout_controls = static function ( array $nodes ) use ( &$collect_services_layout_controls, &$services_card_heading_settings, &$services_badge_settings ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$settings = (array) ( $node['settings'] ?? [] );
	$classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) ) ?: [];
		if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'heading' && in_array( 'wpae-card-heading', $classes, true ) ) { $services_card_heading_settings[] = $settings; }
		if ( ( $node['elType'] ?? '' ) === 'container' && in_array( 'wpae-generated-badge', $classes, true ) ) { $services_badge_settings[] = $settings; }
		$collect_services_layout_controls( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_services_layout_controls( $services_written );
$services_reference_type_matches = count( $services_card_heading_settings ) === 3 && count( array_filter( $services_card_heading_settings, static fn( $settings ): bool => ( $settings['header_size'] ?? '' ) === 'div' && ( $settings['typography_font_size']['unit'] ?? '' ) === 'px' && (float) ( $settings['typography_font_size']['size'] ?? 0 ) === 22.0 && ( $settings['typography_font_size_tablet']['unit'] ?? '' ) === 'rem' && (float) ( $settings['typography_font_size_tablet']['size'] ?? 0 ) === 1.75 && ( $settings['typography_font_size_mobile']['unit'] ?? '' ) === 'rem' && (float) ( $settings['typography_font_size_mobile']['size'] ?? 0 ) === 1.5 ) ) === 3;
check( $services_reference_type_matches, 'Production Services write preserves the reference 22px / 1.75rem / 1.5rem card titles instead of mapping them to the 3.5rem display token: ' . wp_json_encode( $services_card_heading_settings ) );
check( count( $services_badge_settings ) === 1 && ( $services_badge_settings[0]['align_self'] ?? '' ) === 'flex-start' && ( $services_badge_settings[0]['_element_width'] ?? '' ) === 'initial' && (float) ( $services_badge_settings[0]['_flex_grow'] ?? -1 ) === 0.0 && (float) ( $services_badge_settings[0]['width']['size'] ?? 0 ) === 10.0 && ( $services_badge_settings[0]['width']['unit'] ?? '' ) === '%' && (float) ( $services_badge_settings[0]['width_tablet']['size'] ?? 0 ) === 120.0 && ( $services_badge_settings[0]['width_tablet']['unit'] ?? '' ) === 'px' && (float) ( $services_badge_settings[0]['width_mobile']['size'] ?? 0 ) === 120.0 && ( $services_badge_settings[0]['width_mobile']['unit'] ?? '' ) === 'px' && ( $services_badge_settings[0]['_flex_size'] ?? null ) === '' && ( $services_badge_settings[0]['_flex_size_mobile'] ?? null ) === '', 'Production Services write keeps the reference pill narrow with native responsive container widths' );
check( ! empty( $services_response_data['ok'] ) && ( $services_response_data['diagnostics']['action_path'] ?? '' ) === 'library_agent' && count( $GLOBALS['http_calls'] ) === 1 && count( $GLOBALS['writes'] ) === 1, 'Imported Services design uses the agent library decision route and the single existing writer: ' . wp_json_encode( [ 'ok' => $services_response_data['ok'] ?? false, 'path' => $services_response_data['diagnostics']['action_path'] ?? '', 'provider_calls' => count( $GLOBALS['http_calls'] ), 'writes' => count( $GLOBALS['writes'] ), 'library' => $services_response_data['library'] ?? [], 'error' => $services_response instanceof WP_Error ? $services_response->get_error_code() : '', 'message' => $services_response instanceof WP_Error ? $services_response->get_error_message() : '', 'error_data' => $services_response instanceof WP_Error ? $services_response->get_error_data() : [] ] ) );
$services_step_statuses = [];
$services_library_diagnostics = [];
foreach ( (array) ( $services_response_data['steps'] ?? [] ) as $service_step ) {
	$services_step_statuses[] = [ 'id' => $service_step['id'] ?? '', 'status' => $service_step['status'] ?? '', 'message' => $service_step['message'] ?? '' ];
	if ( ( $service_step['id'] ?? '' ) === 'library_retrieval' ) { $services_library_diagnostics = (array) ( $service_step['details'] ?? [] ); }
}
check( ( $services_trace['selection_source'] ?? '' ) === 'model_choice' && ( $services_trace['model_choice'] ?? '' ) === 'candidate_1' && ( $services_trace['selected']['title'] ?? '' ) === 'Services — Photo Cards (User Reference)' && ( $services_trace['status'] ?? '' ) === 'applied', 'The model-selected user Services reference reaches production adaptation: ' . wp_json_encode( [ 'source' => $services_trace['selection_source'] ?? '', 'choice' => $services_trace['model_choice'] ?? '', 'title' => $services_trace['selected']['title'] ?? '', 'status' => $services_trace['status'] ?? '', 'reason' => $services_trace['reason'] ?? '', 'fidelity' => $services_trace['fidelity'] ?? [], 'library_diagnostics' => $services_library_diagnostics, 'root_count' => count( $services_written ), 'steps' => $services_step_statuses ] ) );
check( ! empty( $services_trace['design_preserved'] ) && ( $services_trace['design_preservation_scope'] ?? '' ) === 'authored_layout_after_project_palette_adaptation' && str_contains( $services_written_json, 'wpae-preserve-library-design' ), 'The bundled Services reference keeps its authored composition after safe palette adaptation' );
check( count( array_intersect( [ 'Стратегия проекта', 'Архитектура и дизайн', 'Сопровождение' ], $services_heading_values ) ) === 3 && count( array_intersect( [ 'Формулируем задачу и согласуем план работ.', 'Разрабатываем решение под заданный контекст.', 'Проверяем соответствие согласованному проекту.' ], $services_text_values ) ) === 3 && ! str_contains( $services_written_json, 'сам выберет подходящий шаблон' ) && ! str_contains( $services_written_json, 'временный QA-блок' ), 'Imported Services cards retain exact requested text without exposing the prompt instructions' );
$services_written_widgets = [];
$collect_services_widget_types = static function ( array $nodes ) use ( &$collect_services_widget_types, &$services_written_widgets ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' ) { $services_written_widgets[] = (string) ( $node['widgetType'] ?? '' ); }
		$collect_services_widget_types( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_services_widget_types( $services_written );
check( in_array( 'Наши услуги', $services_heading_values, true ) && in_array( 'УСЛУГИ', $services_heading_values, true ) && count( array_filter( $services_written_widgets, static fn( string $type ): bool => $type === 'image' ) ) === 3 && count( array_filter( $services_written_widgets, static fn( string $type ): bool => $type === 'icon' ) ) === 3, 'Model-selected Services reference keeps its section heading, badge, and three native photo/icon pairs' );
$services_card_surfaces = [];
$collect_service_card_descendants = static function ( array $nodes, array &$types, string &$body_color ) use ( &$collect_service_card_descendants ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$type = (string) ( $node['widgetType'] ?? '' );
		if ( $type !== '' ) { $types[] = $type; }
		if ( $type === 'text-editor' && $body_color === '' ) { $body_color = strtolower( (string) ( $node['settings']['text_color'] ?? '' ) ); }
		$collect_service_card_descendants( (array) ( $node['elements'] ?? [] ), $types, $body_color );
	}
};
$collect_services_card_surfaces = static function ( array $nodes ) use ( &$collect_services_card_surfaces, &$collect_service_card_descendants, &$services_card_surfaces ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'container' ) {
			$settings = (array) ( $node['settings'] ?? [] );
			$types = [];
			$service_text = '';
			$collect_service_card_descendants( (array) ( $node['elements'] ?? [] ), $types, $service_text );
			if ( in_array( 'image', $types, true ) && in_array( 'heading', $types, true ) && in_array( 'text-editor', $types, true ) && ( $settings['border_color'] ?? '' ) === '#d1d5db' ) {
				$services_card_surfaces[] = [ 'background' => $settings['background_background'] ?? '', 'color' => strtolower( (string) ( $settings['background_color'] ?? '' ) ), 'text_color' => $service_text ];
			}
		}
		$collect_services_card_surfaces( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_services_card_surfaces( $services_written );
check( count( $services_card_surfaces ) === 3 && count( array_filter( $services_card_surfaces, static fn( $card ): bool => ( $card['background'] ?? '' ) === 'classic' && ( $card['color'] ?? '' ) === '#ffffff' && ( $card['text_color'] ?? '' ) === '#6b7280' ) ) === 3, 'All imported Services cards reset dark/source-token fills and green source text to one readable surface palette: ' . wp_json_encode( $services_card_surfaces ) );
check( count( $services_written ) === 1 && ( $GLOBALS['page_data'][0]['id'] ?? '' ) === ( $legacy_page[0]['id'] ?? '' ), 'Imported Services generation appends one root and preserves the pre-existing page root' );
$find_services_grid = static function ( array $nodes ) use ( &$find_services_grid ): array {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$classes = preg_split( '/\s+/', trim( (string) ( $node['settings']['_css_classes'] ?? '' ) ) ) ?: [];
		$cards = array_values( array_filter( (array) ( $node['elements'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) );
		if ( in_array( 'wpae-bento-grid', $classes, true ) && count( $cards ) >= 2 ) { return $node; }
		$found = $find_services_grid( (array) ( $node['elements'] ?? [] ) );
		if ( ! empty( $found ) ) { return $found; }
	}
	return [];
};
$services_grid = $find_services_grid( $services_written );
$services_grid_cards = array_values( array_filter( (array) ( $services_grid['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'container' ) );
$services_card_spacers = 0;
foreach ( $services_grid_cards as $service_card ) {
	$services_card_spacers += count( array_filter( (array) ( $service_card['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'spacer' ) );
}
check( count( $services_grid_cards ) === 3 && ( $services_grid['settings']['flex_direction'] ?? '' ) === 'row' && ( $services_grid['settings']['flex_direction_mobile'] ?? '' ) === 'column' && $services_card_spacers === 0, 'Production Services template is normalized into a desktop card row, a mobile stack, and no spacer-driven card gaps: ' . wp_json_encode( [ 'archetype' => $services_response_data['diagnostics']['archetype'] ?? null, 'grid_settings' => $services_grid['settings'] ?? [], 'card_count' => count( $services_grid_cards ), 'card_ids' => array_column( $services_grid_cards, 'id' ), 'card_widgets' => array_map( static fn( $card ): array => array_map( static fn( $node ): string => (string) ( $node['widgetType'] ?? $node['elType'] ?? '' ), (array) ( $card['elements'] ?? [] ) ), $services_grid_cards ), 'spacers' => $services_card_spacers ] ) );
$services_styled_card_count = count( array_filter( $services_grid_cards, static fn( $card ): bool => ( $card['settings']['container_type'] ?? '' ) === 'flex' && ( $card['settings']['flex_direction'] ?? '' ) === 'column' && ( $card['settings']['flex_direction_mobile'] ?? '' ) === 'column' && (float) ( $card['settings']['width']['size'] ?? 0 ) >= 30 && (float) ( $card['settings']['width_tablet']['size'] ?? 0 ) === 48.0 && (float) ( $card['settings']['width_mobile']['size'] ?? 0 ) === 100.0 && ( $card['settings']['background_background'] ?? '' ) === 'classic' && ( $card['settings']['background_color'] ?? '' ) === '#ffffff' && ( $card['settings']['margin']['right'] ?? null ) === '0' && ( $card['settings']['margin_mobile']['left'] ?? null ) === '0' ) );
check( $services_styled_card_count === 3, 'All three Services cards use native vertical Flex content, gap-aware responsive widths, zero imported side margins, and a consistent readable surface' );
$services_layout_changes = 0;
$services_layout_normalized = wpae_llm_normalize_library_layout( (array) ( $services_layout_fallback['elements'] ?? [] ), $services_layout_changes, 'services' );
$services_layout_grid = $find_services_grid( $services_layout_normalized );
$services_layout_cards = array_values( array_filter( (array) ( $services_layout_grid['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'container' ) );
check( count( $services_layout_cards ) === 3 && count( array_filter( $services_layout_cards, static fn( $card ): bool => ( $card['settings']['border_radius']['unit'] ?? '' ) === 'rem' && (float) ( $card['settings']['border_radius']['size'] ?? 0 ) === 1.0 && ( $card['settings']['border_radius']['top'] ?? '' ) === '1' && ( $card['settings']['border_radius']['right'] ?? '' ) === '1' && ( $card['settings']['border_radius']['bottom'] ?? '' ) === '1' && ( $card['settings']['border_radius']['left'] ?? '' ) === '1' && ( $card['settings']['padding']['top'] ?? '' ) === '1.5' && ( $card['settings']['background_color'] ?? '' ) === '#ffffff' ) ) === 3, 'Services layout normalization emits Elementor dimension-shaped rounded, padded card surfaces for every card' );
$services_final_elements = wpae_llm_apply_generation_visual_grammar( (array) ( $services_layout_fallback['elements'] ?? [] ), 'services', $services_layout_visual_changes );
$services_library_changes = 0;
$services_final_elements = wpae_llm_normalize_library_layout( $services_final_elements, $services_library_changes, 'services' );
$services_bento_changes = 0;
wpae_llm_normalize_bento_grids_recursive( $services_final_elements, $services_bento_changes, 'services' );
$services_final_grid = $find_services_grid( $services_final_elements );
$services_final_cards = array_values( array_filter( (array) ( $services_final_grid['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'container' ) );
$services_final_card_checks = array_map( static function ( array $card ): array {
	$image_widgets = array_values( array_filter( (array) ( $card['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'image' ) );
	return [
		'width' => (float) ( $card['settings']['width']['size'] ?? 0 ),
		'tablet_width' => (float) ( $card['settings']['width_tablet']['size'] ?? 0 ),
		'mobile_width' => (float) ( $card['settings']['width_mobile']['size'] ?? 0 ),
		'radius' => (float) ( $card['settings']['border_radius']['size'] ?? 0 ),
		'radius_sides' => array_map( 'strval', array_intersect_key( (array) ( $card['settings']['border_radius'] ?? [] ), array_flip( [ 'top', 'right', 'bottom', 'left' ] ) ) ),
		'image' => $image_widgets[0]['settings']['image'] ?? [],
		'object_fit' => $image_widgets[0]['settings']['object-fit'] ?? '',
	];
}, $services_final_cards );
$services_final_copy = wp_json_encode( $services_final_elements, JSON_UNESCAPED_UNICODE );
check( count( $services_final_cards ) === 3 && ( $services_final_grid['settings']['flex_direction'] ?? '' ) === 'row' && ( $services_final_grid['settings']['flex_direction_mobile'] ?? '' ) === 'column' && count( array_filter( $services_final_card_checks, static fn( $card ): bool => $card['width'] === 31.0 && $card['tablet_width'] === 48.0 && $card['mobile_width'] === 100.0 && $card['radius'] === 1.0 && $card['radius_sides'] === [ 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1' ] && str_starts_with( (string) ( $card['image']['url'] ?? '' ), 'https://images.unsplash.com/' ) && trim( (string) ( $card['image']['alt'] ?? '' ) ) !== '' && $card['object_fit'] === 'cover' ) ) === 3, 'Services final bento normalization enforces Elementor border-radius dimensions, row/tablet/mobile widths and native Unsplash photos' );
check( str_contains( $services_final_copy, 'Услуги архитектурной студии' ) && str_contains( $services_final_copy, 'От первого замысла до авторского сопровождения.' ) && str_contains( $services_final_copy, 'УСЛУГИ' ) && str_contains( $services_final_copy, 'Проверяем соответствие согласованному проекту.' ), 'Services visual fix keeps the exact section copy, badge and final service description' );
$services_explicit_media_grid = $services_final_grid;
$services_explicit_media_grid['elements'][0]['elements'] = array_values( array_filter( (array) ( $services_explicit_media_grid['elements'][0]['elements'] ?? [] ), static fn( $node ): bool => ! ( is_array( $node ) && ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'image' ) ) );
array_unshift( $services_explicit_media_grid['elements'][0]['elements'], [ 'id' => 'user-service-image', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => [ 'image' => [ 'url' => 'https://example.com/owner-photo.jpg', 'id' => 0, 'alt' => 'Фото, указанное пользователем.' ] ], 'elements' => [] ] );
$services_preserve_changes = 0;
wpae_llm_normalize_bento_grid( $services_explicit_media_grid, $services_preserve_changes, 'services' );
$services_first_card_images = array_values( array_filter( (array) ( $services_explicit_media_grid['elements'][0]['elements'] ?? [] ), static fn( $node ): bool => is_array( $node ) && ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'image' ) );
check( count( $services_first_card_images ) === 1 && ( $services_first_card_images[0]['settings']['image']['url'] ?? '' ) === 'https://example.com/owner-photo.jpg', 'Services visual normalization preserves an existing user-supplied image and does not add a duplicate: ' . wp_json_encode( [ 'card_count' => count( (array) ( $services_explicit_media_grid['elements'] ?? [] ) ), 'images' => array_map( static fn( $node ): string => (string) ( $node['settings']['image']['url'] ?? '' ), $services_first_card_images ) ] ) );

$run_services_route = static function ( string $message, array $responses, array $library, string $operation_identity, bool $fail_write = false, string $pipeline_mode = 'active', string $engine_mode = 'active' ) use ( $legacy_page ): array {
	$previous_globals = [];
	foreach ( [ 'library', 'options', 'page_data', 'http_calls', 'writes', 'responses', 'fail_write_boundary' ] as $global_key ) {
		$previous_globals[ $global_key ] = $GLOBALS[ $global_key ] ?? null;
	}
	$GLOBALS['library'] = $library;
	$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => $pipeline_mode, 'design_engine_mode' => $engine_mode ];
	$GLOBALS['options'][WPAE_LLM_RATE_LIMIT_OPTION] = [];
	$GLOBALS['page_data'] = $legacy_page;
	$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
	$GLOBALS['responses'] = $responses;
	$GLOBALS['fail_write_boundary'] = $fail_write;
	$request = new WP_REST_Request();
	$request->set_param( 'message', $message );
	$request->set_param( 'context', [ 'post_id' => 42, 'operation_identity' => $operation_identity ] );
	$response = wpae_llm_chat_request( $request );
	$result = [
		'response' => $response instanceof WP_REST_Response ? $response->get_data() : [],
		'error' => $response instanceof WP_Error ? [ 'code' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data() ] : [],
		'calls' => count( $GLOBALS['http_calls'] ),
		'writes' => count( $GLOBALS['writes'] ),
		'roots' => array_column( (array) $GLOBALS['page_data'], 'id' ),
		'written' => (array) ( $GLOBALS['page_data'][ count( $legacy_page ) ] ?? [] ),
	];
	foreach ( $previous_globals as $global_key => $value ) {
		if ( $value === null ) {
			unset( $GLOBALS[ $global_key ] );
		} else {
			$GLOBALS[ $global_key ] = $value;
		}
	}
	return $result;
};
$services_library_fixture = [
	'status' => 'matched', 'available_count' => 1, 'candidate_count' => 1,
	'candidates' => [ [ 'choice_key' => 'candidate_1', 'title' => 'Services — Photo Cards (User Reference)', 'category' => 'services' ] ],
	'selection_candidates' => [ [ 'choice_key' => 'candidate_1', 'id' => 0, 'bundled_fixture_id' => 'template-services-photo-cards-v1', 'title' => 'Services — Photo Cards (User Reference)', 'category' => 'services', 'template_type' => 'section-services', 'source' => 'plugin_template', 'status' => 'published', 'trusted_bundled' => false, 'elementor_data' => $services_template_data ] ],
];
$carousel_library_candidate = [
	'choice_key' => 'candidate_1', 'id' => 781, 'title' => 'Partner Logo Carousel', 'category' => 'carousel', 'template_type' => 'section-logo-carousel', 'source' => 'plugin_template', 'status' => 'published', 'trusted_bundled' => false,
	'elementor_data' => [ $carousel_source_widget ],
];
$carousel_library_fixture = [
	'status' => 'matched', 'available_count' => 1, 'candidate_count' => 1,
	'candidates' => [ [ 'choice_key' => 'candidate_1', 'title' => 'Partner Logo Carousel', 'category' => 'carousel' ] ],
	'selection_candidates' => [ $carousel_library_candidate ],
	'preflight_candidates' => [ $carousel_library_candidate ],
];
$carousel_choice_action = [ 'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'library_choice' => 'candidate_1', 'elements' => [] ];
$carousel_library_route = $run_services_route( $explicit_library_family_prompts['carousel'], [ provider_reply( wp_json_encode( $carousel_choice_action, JSON_UNESCAPED_UNICODE ) ) ], $carousel_library_fixture, 'carousel-library-route-identity' );
$carousel_library_route_copy = wpae_llm_collect_action_content( [ $carousel_library_route['written'] ] );
$carousel_library_route_json = (string) wp_json_encode( $carousel_library_route['written'], JSON_UNESCAPED_UNICODE );
$carousel_library_widgets = [];
$collect_carousel_widgets = static function ( array $nodes ) use ( &$collect_carousel_widgets, &$carousel_library_widgets ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'widget' ) { $carousel_library_widgets[] = (string) ( $node['widgetType'] ?? '' ); }
		$collect_carousel_widgets( (array) ( $node['elements'] ?? [] ) );
	}
};
if ( ! empty( $carousel_library_route['written'] ) ) { $collect_carousel_widgets( [ $carousel_library_route['written'] ] ); }
check( ! empty( $carousel_library_route['response']['ok'] ) && ( $carousel_library_route['response']['diagnostics']['action_path'] ?? '' ) === 'library_agent' && $carousel_library_route['calls'] === 1 && $carousel_library_route['writes'] === 1 && ! empty( $carousel_library_route['response']['operation_id'] ), 'Carousel runs through the production library-agent route and crosses the existing write boundary once with an operation ID: ' . wp_json_encode( $carousel_library_route, JSON_UNESCAPED_UNICODE ) );
check( str_contains( $carousel_library_route_copy, 'С кем мы работаем' ) && str_contains( $carousel_library_route_copy, 'Надёжные партнёры проекта' ) && str_contains( $carousel_library_route_copy, 'Альфа' ) && str_contains( $carousel_library_route_copy, 'Бета' ) && ! str_contains( $carousel_library_route_json, 'Глобальную тему сайта не меняй' ) && in_array( 'image-carousel', $carousel_library_widgets, true ) && in_array( 'heading', $carousel_library_widgets, true ), 'Production Carousel adapter preserves the native carousel and exact copy without leaking instructions: ' . wp_json_encode( [ 'copy' => $carousel_library_route_copy, 'widgets' => $carousel_library_widgets ], JSON_UNESCAPED_UNICODE ) );
$carousel_without_candidate = $run_services_route( $explicit_library_family_prompts['carousel'], [], [], 'carousel-no-candidate-identity' );
check( ( $carousel_without_candidate['error']['code'] ?? '' ) === 'wpae_llm_no_compatible_library_candidate' && ( $carousel_without_candidate['error']['data']['details']['write_count'] ?? null ) === 0 && $carousel_without_candidate['calls'] === 0 && $carousel_without_candidate['writes'] === 0 && $carousel_without_candidate['roots'] === array_column( $legacy_page, 'id' ), 'Explicit library request with no compatible candidate stops before provider dispatch and preserves existing roots' );
$natural_services_library_message = "Услуги:\nСтратегия проекта — Формулируем задачу и согласуем план работ\nАрхитектура и дизайн — Разрабатываем решение под заданный контекст\nСопровождение — Проверяем соответствие согласованному проекту";
$natural_services_library_action = [ 'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'library_choice' => 'candidate_1', 'elements' => [] ];
$natural_services_library_result = $run_services_route( $natural_services_library_message, [ provider_reply( wp_json_encode( $natural_services_library_action, JSON_UNESCAPED_UNICODE ) ) ], $services_library_fixture, 'services-natural-library-identity' );
$natural_services_library_trace = (array) ( $natural_services_library_result['response']['library'] ?? [] );
$natural_services_library_written = (array) ( $natural_services_library_result['written'] ?? [] );
$natural_services_library_text = wpae_llm_collect_action_content( [ $natural_services_library_written ] );
check( ! empty( $natural_services_library_result['response']['ok'] ) && ( $natural_services_library_trace['selection_source'] ?? '' ) === 'model_choice' && ( $natural_services_library_trace['status'] ?? '' ) === 'applied' && $natural_services_library_result['calls'] === 1 && $natural_services_library_result['writes'] === 1, 'A plain Services brief completes the model-selected library route through one existing writer' );
check( ! empty( $natural_services_library_trace['fidelity']['ok'] ) && ! empty( $natural_services_library_trace['source_fingerprint']['has_badge'] ) && ! empty( wpae_llm_template_fingerprint( [ $natural_services_library_written ] )['has_badge'] ) && str_contains( $natural_services_library_text, 'УСЛУГИ' ), 'The chosen Services template retains its reference pill through copy cleanup and structural fidelity checks' );
check( str_contains( $natural_services_library_text, 'Стратегия проекта' ) && str_contains( $natural_services_library_text, 'Формулируем задачу и согласуем план работ' ) && str_contains( $natural_services_library_text, 'Архитектура и дизайн' ) && str_contains( $natural_services_library_text, 'Разрабатываем решение под заданный контекст' ) && str_contains( $natural_services_library_text, 'Сопровождение' ) && str_contains( $natural_services_library_text, 'Проверяем соответствие согласованному проекту' ), 'The plain Services library route preserves the exact three ordered card pairs' );
$pricing_library_message = 'Тарифы: Старт — от 50 000 ₸ — Для регулярных задач и развития проекта; Проект — от 150 000 ₸ — Для небольшой задачи с понятным объёмом; Поддержка — от 80 000 ₸/мес — Для комплексной работы от идеи до результата.';
$incompatible_pricing_fixture = [
	'status' => 'matched', 'available_count' => 1, 'candidate_count' => 1,
	'candidates' => [ [ 'choice_key' => 'candidate_1', 'title' => 'Incompatible pricing block', 'category' => 'pricing' ] ],
	'selection_candidates' => [ [ 'choice_key' => 'candidate_1', 'id' => 0, 'title' => 'Incompatible pricing block', 'category' => 'pricing', 'template_type' => 'section-pricing', 'source' => 'plugin_template', 'status' => 'published', 'trusted_bundled' => false, 'elementor_data' => [ container_node( 'unrelated-faq-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-faq-library-root' ], [] ), container_node( 'unrelated-hero-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-hero-library-root' ], [] ) ] ] ],
];
$pricing_provider_action = wpae_llm_build_fallback_action( $pricing_library_message, 42 );
$pricing_provider_action['library_choice'] = 'candidate_1';
$pricing_provider_action['elements'][0]['settings']['_css_classes'] = 'wpae-provider-pricing-root';
unset( $pricing_provider_action['fallback_archetype'], $pricing_provider_action['fallback_variant'] );
$pricing_after_incompatible_choice = $run_services_route( $pricing_library_message, [ provider_reply( wp_json_encode( $pricing_provider_action, JSON_UNESCAPED_UNICODE ) ) ], $incompatible_pricing_fixture, 'pricing-incompatible-library-choice' );
check( ( $pricing_after_incompatible_choice['error']['code'] ?? '' ) === 'wpae_llm_no_compatible_library_candidate' && $pricing_after_incompatible_choice['calls'] === 0 && $pricing_after_incompatible_choice['writes'] === 0 && ( $pricing_after_incompatible_choice['error']['data']['details']['write_count'] ?? null ) === 0, 'An incompatible library shortlist is rejected before provider dispatch instead of allowing an unrelated composition: ' . wp_json_encode( $pricing_after_incompatible_choice, JSON_UNESCAPED_UNICODE ) );
check( $pricing_after_incompatible_choice['roots'] === array_column( $legacy_page, 'id' ) && ( $pricing_after_incompatible_choice['error']['data']['details']['write_count'] ?? null ) === 0, 'Incompatible library output leaves all neighboring roots untouched and never crosses the write boundary' );
$leaked_cta_provider_action = [ 'action' => 'insert_elements', 'post_id' => 42, 'position' => 'end', 'elements' => $leaked_cta_provider ];
$leaked_cta_route = $run_services_route( $standalone_cta_message, [ provider_reply( wp_json_encode( $leaked_cta_provider_action, JSON_UNESCAPED_UNICODE ) ) ], [], 'cta-label-leak-identity', false, 'off', 'off' );
$leaked_cta_written_copy = wpae_llm_collect_action_content( [ (array) $leaked_cta_route['written'] ] );
check( ! empty( $leaked_cta_route['response']['ok'] ) && ( $leaked_cta_route['response']['diagnostics']['action_path'] ?? '' ) === 'fallback' && $leaked_cta_route['calls'] === 1 && $leaked_cta_route['writes'] === 1, 'A malformed CTA provider composition did not recover through one existing write boundary' );
check( str_contains( $leaked_cta_written_copy, 'Обсудим проект' ) && str_contains( $leaked_cta_written_copy, 'Связаться' ) && str_contains( $leaked_cta_written_copy, 'Смотреть проекты' ) && ! preg_match( '/(?:Заголовок|Описание) секции:|(?:Основная|Вторичная) кнопка:/u', $leaked_cta_written_copy ), 'Production CTA route wrote visible instruction labels instead of the deterministic semantic fallback' );
$leaked_cta_route_actions = [];
$collect_leaked_cta_actions = static function ( array $nodes ) use ( &$collect_leaked_cta_actions, &$leaked_cta_route_actions ): void {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		if ( ( $node['elType'] ?? '' ) === 'container' && str_contains( (string) ( $node['settings']['_css_classes'] ?? '' ), 'wpae-cta-actions' ) ) { $leaked_cta_route_actions[] = $node; }
		$collect_leaked_cta_actions( (array) ( $node['elements'] ?? [] ) );
	}
};
$collect_leaked_cta_actions( [ (array) $leaked_cta_route['written'] ] );
$leaked_cta_route_buttons = (array) ( $leaked_cta_route_actions[0]['elements'] ?? [] );
check( count( $leaked_cta_route_actions ) === 1 && count( $leaked_cta_route_buttons ) === 2 && ( $leaked_cta_route_buttons[0]['settings']['link']['url'] ?? '' ) === '#contact' && ( $leaked_cta_route_buttons[1]['settings']['link']['url'] ?? '' ) === '#projects', 'Production CTA fallback did not keep both exact links together in one responsive group' );
$no_fallback_cta_route = $run_services_route( $standalone_cta_message . "\nНе используй fallback.", [ provider_reply( wp_json_encode( $leaked_cta_provider_action, JSON_UNESCAPED_UNICODE ) ) ], [], 'cta-no-fallback-identity', false, 'off', 'off' );
check( ( $no_fallback_cta_route['error']['code'] ?? '' ) === 'wpae_llm_composition_rejected' && $no_fallback_cta_route['writes'] === 0 && $no_fallback_cta_route['roots'] === array_column( $legacy_page, 'id' ), 'A rejected CTA composition crossed the write boundary when fallback was forbidden' );
$services_ambiguous_route = $run_services_route( $services_message . "\nУслуга 4: «Дополнение» — описание без закрытой пары", [], $services_library_fixture, 'services-ambiguous-identity' );
check( ( $services_ambiguous_route['error']['code'] ?? '' ) === 'wpae_design_plan_rejected' && str_contains( (string) ( $services_ambiguous_route['error']['message'] ?? '' ), 'Изменения не записаны' ) && str_contains( (string) ( $services_ambiguous_route['error']['message'] ?? '' ), 'services-ambiguous-identity' ), 'Production route explains ambiguous Services input and exposes the request identity before provider/write' );
check( $services_ambiguous_route['calls'] === 0 && $services_ambiguous_route['writes'] === 0 && $services_ambiguous_route['roots'] === array_column( $legacy_page, 'id' ) && ( $services_ambiguous_route['error']['data']['details']['operation_identity'] ?? '' ) === 'services-ambiguous-identity', 'Ambiguous Services input cannot call a provider or cross the write boundary' );
$services_declined_action = $services_provider_action;
$services_declined_action['library_choice'] = null;
$services_declined = $run_services_route( $services_library_only_message, [ provider_reply( wp_json_encode( $services_declined_action ) ) ], $services_library_fixture, 'services-declined-identity' );
check( ( $services_declined['error']['code'] ?? '' ) === 'wpae_llm_library_selection_required' && str_contains( (string) ( $services_declined['error']['message'] ?? '' ), 'Модель отказалась' ) && str_contains( (string) ( $services_declined['error']['message'] ?? '' ), 'services-declined-identity' ), 'Library-only Services request surfaces the model refusal and existing request identity' );
check( ( $services_declined['error']['data']['details']['write_count'] ?? null ) === 0 && $services_declined['writes'] === 0 && $services_declined['roots'] === array_column( $legacy_page, 'id' ), 'Model decline does not locally select a template, invoke fallback, or write the page' );
$services_unknown_action = $services_provider_action;
$services_unknown_action['library_choice'] = 'candidate_not_offered';
$services_unknown = $run_services_route( $services_library_only_message, [ provider_reply( wp_json_encode( $services_unknown_action ) ) ], $services_library_fixture, 'services-unknown-identity' );
check( ( $services_unknown['error']['data']['details']['library_selection_source'] ?? '' ) === 'invalid_model_choice' && str_contains( (string) ( $services_unknown['error']['message'] ?? '' ), 'неизвестный ID шаблона' ) && $services_unknown['writes'] === 0, 'Unknown model template ID is rejected before the existing write boundary' );
$services_timeout = $run_services_route( $services_library_only_message, [ new WP_Error( 'http_request_failed', 'simulated provider timeout' ) ], $services_library_fixture, 'services-timeout-identity' );
check( ( $services_timeout['error']['data']['details']['library_selection_source'] ?? '' ) === 'no_model_choice' && ( $services_timeout['error']['data']['details']['action_path'] ?? '' ) === 'library_agent' && ( $services_timeout['error']['data']['details']['provider_call_count'] ?? 0 ) === 1 && $services_timeout['writes'] === 0, 'Provider timeout cannot trigger a local template selection or unapproved fallback write' );
$services_forbidden_timeout = $run_services_route( $services_fallback_message . "\nНе используй fallback при отказе или timeout.", [ new WP_Error( 'http_request_failed', 'simulated provider timeout' ) ], [], 'services-no-fallback-identity', false, 'off', 'off' );
check( ( $services_forbidden_timeout['error']['code'] ?? '' ) === 'wpae_llm_library_selection_required' && str_contains( (string) ( $services_forbidden_timeout['error']['message'] ?? '' ), 'Изменения не записаны' ) && $services_forbidden_timeout['writes'] === 0, 'Explicit no-fallback instruction stops the deterministic recovery path after timeout' );

$services_invalid_action = [ 'action' => 'unsupported', 'post_id' => 42, 'position' => 'end', 'library_choice' => null, 'elements' => [] ];
$services_fallback_result = $run_services_route( $services_fallback_message, [ provider_reply( wp_json_encode( $services_invalid_action ) ), provider_reply( wp_json_encode( $services_invalid_action ) ), provider_reply( wp_json_encode( $services_invalid_action ) ) ], [], 'services-fallback-allowed-identity', false, 'off', 'off' );
$services_fallback_route_plan = wpae_llm_content_plan( $services_fallback_message, 'services' );
$services_fallback_route_audit = wpae_llm_content_plan_audit( $services_fallback_route_plan, [ $services_fallback_result['written'] ] );
check( ! empty( $services_fallback_result['response']['ok'] ) && ( $services_fallback_result['response']['diagnostics']['action_path'] ?? '' ) === 'fallback' && $services_fallback_result['calls'] === 3 && $services_fallback_result['writes'] === 1, 'Permitted model/repair failure produces one validated Services fallback write' );
check( ! empty( $services_fallback_route_audit['ok'] ) && ( $services_fallback_route_audit['service_card_count'] ?? 0 ) === 3 && ! str_contains( wp_json_encode( $services_fallback_result['written'], JSON_UNESCAPED_UNICODE ), 'сам выберет подходящий шаблон' ), 'Written fallback has three exact cards and does not publish instruction text' );
$services_fallback_images = [];
$services_fallback_badges = [];
$collect_services_visuals( [ $services_fallback_result['written'] ] );
check( count( $services_fallback_images ) === 3 && $services_fallback_badges === [ 'УСЛУГИ' ], 'The production fallback route writes the Services pill and all three photos through the existing Elementor boundary' );
$services_write_failure = $run_services_route( $services_fallback_message, [ provider_reply( wp_json_encode( $services_invalid_action ) ), provider_reply( wp_json_encode( $services_invalid_action ) ), provider_reply( wp_json_encode( $services_invalid_action ) ) ], [], 'services-write-failure-identity', true, 'off', 'off' );
check( empty( $services_write_failure['response']['ok'] ) && $services_write_failure['writes'] === 0 && $services_write_failure['roots'] === array_column( $legacy_page, 'id' ), 'A failing Elementor transaction boundary writes no fallback root and preserves the existing page tree' );
$GLOBALS['library'] = [];

// Negative insertion language stays a selected-root edit; an explicit new
// root request is rejected only when it conflicts with that same edit scope.
$negative_root_prompt = 'Измени выбранный процессный таймлайн: добавь нативные разделители между шагами, не добавляй новый root.';
check( ! wpae_llm_has_explicit_root_insert_intent( $negative_root_prompt ), 'negated "не добавляй новый root" is not classified as an insert command' );
check( wpae_llm_is_targeted_edit_request( $negative_root_prompt ) && wpae_llm_is_process_structure_repair_request( $negative_root_prompt, 'process' ), 'selected-root negative-insert phrase is recognized as a process structural edit' );
check( wpae_llm_is_process_structure_repair_request( $negative_root_prompt, wpae_llm_detect_block_archetype( $negative_root_prompt ) ), 'production archetype classification keeps the selected timeline on the deterministic repair path: ' . wpae_llm_detect_block_archetype( $negative_root_prompt ) );
$process_root = wpae_llm_build_process_timeline( [
	[ 'label' => '01. Заявка', 'content' => 'QA step one.' ],
	[ 'label' => '02. Уточнение', 'content' => 'QA step two.' ],
], 'selected-process-root', 'left', 'Процесс QA' );
$process_root['settings']['_css_classes'] = 'wpae-generated-root wpae-process-timeline wpae-system-test';
$process_neighbor = container_node( 'neighbor-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-system-test' ], [ widget( 'neighbor-title', 'heading', [ 'title' => 'Соседний пользовательский root' ] ) ] );
$GLOBALS['page_data'] = [ $process_neighbor, $process_root ];
$before_route_roots = array_column( $GLOBALS['page_data'], 'id' );
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [];
$targeted_process = new WP_REST_Request();
$targeted_process->set_param( 'message', $negative_root_prompt );
$targeted_process->set_param( 'context', [ 'post_id' => 42, 'selected_elements' => [ [ 'id' => 'selected-process-root' ] ] ] );
$targeted_process_response = wpae_llm_chat_request( $targeted_process );
$after_route_roots = array_column( $GLOBALS['page_data'], 'id' );
check( $targeted_process_response instanceof WP_REST_Response && ( $targeted_process_response->get_data()['action'] ?? '' ) === 'patch_elements', 'targeted process edit with a negated insert phrase uses the selected-root repair route' );
check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 1 && $after_route_roots === $before_route_roots, 'negative insert wording performs one existing-root write, no provider call, and no append/duplicate' );
check( ( $GLOBALS['page_data'][0]['id'] ?? '' ) === 'neighbor-root', 'targeted process repair preserves the unrelated neighboring root' );
$conflicting_insert = new WP_REST_Request();
$conflicting_insert->set_param( 'message', 'Измени выбранный таймлайн и создай отдельный новый root процесса.' );
$conflicting_insert->set_param( 'context', [ 'post_id' => 42, 'selected_elements' => [ [ 'id' => 'selected-process-root' ] ] ] );
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [];
$conflict_response = wpae_llm_chat_request( $conflicting_insert );
check( is_wp_error( $conflict_response ) && $conflict_response->get_error_code() === 'wpae_llm_conflicting_scope', 'contradictory selected edit plus separate root is rejected before generation' );
check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 0 && array_column( $GLOBALS['page_data'], 'id' ) === $before_route_roots, 'conflicting scopes perform no provider call, no write, and preserve both roots' );
check( wpae_llm_has_explicit_root_insert_intent( 'Добавь отдельный новый root процесса.' ), 'explicit independent root insertion remains detectable' );
$independent_insert_prompt = 'Создай новый таймлайн процесса: шаг «Заявка» — описание «Получить вводные»; шаг «Уточнение» — описание «Согласовать детали». Добавь отдельным блоком в конец страницы.';
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [];
$independent_insert = new WP_REST_Request();
$independent_insert->set_param( 'message', $independent_insert_prompt );
$independent_insert->set_param( 'context', [ 'post_id' => 42, 'selected_elements' => [ [ 'id' => 'selected-process-root' ] ] ] );
$independent_insert_response = wpae_llm_chat_request( $independent_insert );
$independent_ids = array_column( $GLOBALS['page_data'], 'id' );
check( $independent_insert_response instanceof WP_REST_Response && ( $independent_insert_response->get_data()['diagnostics']['action_path'] ?? '' ) === 'pipeline', 'a distinct new timeline request remains a valid insert even while an old element is selected' );
check( count( $GLOBALS['http_calls'] ) === 0 && count( $GLOBALS['writes'] ) === 1 && $independent_ids[0] === 'neighbor-root' && $independent_ids[1] === 'selected-process-root' && count( $independent_ids ) === 3, 'independent selected-page insert appends exactly one root without replacing selected or neighboring content' );

$cta_selected_root = container_node( 'selected-cta-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-generated-root wpae-generated-cta' ], [
	widget( 'cta-primary', 'button', [ 'text' => 'Связаться', 'link' => [ 'url' => '#contact' ] ] ),
	widget( 'cta-secondary', 'button', [ 'text' => 'Посмотреть проекты', 'link' => [ 'url' => '#projects' ] ] ),
] );
$cta_selected_neighbor = container_node( 'cta-neighbor-root', [ 'container_type' => 'flex', '_css_classes' => 'wpae-system-test' ], [ widget( 'cta-neighbor-title', 'heading', [ 'title' => 'Соседняя секция' ] ) ] );
$GLOBALS['page_data'] = [ $cta_selected_neighbor, $cta_selected_root ];
$cta_before_undo = $GLOBALS['page_data'];
$cta_before_ids = array_column( $GLOBALS['page_data'], 'id' );
$GLOBALS['options'][ WPAE_DESIGN_OPERATION_OPTION ] = [];
$GLOBALS['options']['wp_ai_executor_rollback_snapshots'] = [];
$GLOBALS['http_calls'] = $GLOBALS['writes'] = [];
$GLOBALS['responses'] = [];
$cta_target_mismatch = wpae_llm_execute_patch_action(
	[ 'action' => 'patch_elements', 'post_id' => 42, 'patches' => [ [ 'element_id' => 'cta-neighbor-title', 'path' => 'settings.title', 'op' => 'set', 'value' => 'Не трогать соседний блок' ] ] ],
	42,
	[ 'selected-cta-root' ],
	'Измени кнопки в выбранном CTA-блоке.',
	[ 'operation_identity' => 'cta-target-mismatch' ]
);
check( empty( $cta_target_mismatch['ok'] ) && count( $GLOBALS['writes'] ) === 0 && ( $GLOBALS['page_data'][0]['elements'][0]['settings']['title'] ?? '' ) === 'Соседняя секция', 'targeted patch rejects a provider target outside the captured selected root before any write' );
$cta_targeted_edit = new WP_REST_Request();
$cta_targeted_edit->set_param( 'message', 'Измени кнопки в выбранном CTA-блоке: основная «Связаться», вторичная «Посмотреть проекты».' );
$cta_targeted_edit->set_param( 'context', [ 'post_id' => 42, 'operation_identity' => 'cta-targeted-patch-identity', 'selected_elements' => [ [ 'id' => 'selected-cta-root' ] ] ] );
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( [ 'action' => 'patch_elements', 'post_id' => 42, 'patches' => [ [ 'element_id' => 'cta-primary', 'path' => 'settings.text', 'op' => 'set', 'value' => 'Обсудить проект' ] ] ] ) ) ];
$cta_wrong_copy_response = wpae_llm_chat_request( $cta_targeted_edit );
check( $cta_wrong_copy_response instanceof WP_Error && $cta_wrong_copy_response->get_error_code() === 'wpae_llm_action_failed' && count( $GLOBALS['writes'] ) === 0 && ( $GLOBALS['page_data'][1]['elements'][0]['settings']['text'] ?? '' ) === 'Связаться', 'targeted patch rejects provider copy that conflicts with exact requested CTA text before write' );
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( [ 'action' => 'patch_elements', 'post_id' => 42, 'patches' => [ [ 'element_id' => 'cta-primary', 'path' => 'settings.text', 'op' => 'set', 'value' => 'Связаться' ] ] ] ) ) ];
$cta_targeted_response = wpae_llm_chat_request( $cta_targeted_edit );
$cta_after_ids = array_column( $GLOBALS['page_data'], 'id' );
check( $cta_targeted_response instanceof WP_REST_Response && ( $cta_targeted_response->get_data()['action'] ?? '' ) === 'patch_elements', 'targeted CTA button edit routes to the selected patch action rather than append: ' . wp_json_encode( [ 'error' => $cta_targeted_response instanceof WP_Error ? $cta_targeted_response->get_error_code() : '', 'message' => $cta_targeted_response instanceof WP_Error ? $cta_targeted_response->get_error_message() : '', 'data' => $cta_targeted_response instanceof WP_Error ? $cta_targeted_response->get_error_data() : [] ] ) );
check( count( $GLOBALS['writes'] ) === 1 && $cta_after_ids === $cta_before_ids && ( $GLOBALS['page_data'][1]['id'] ?? '' ) === 'selected-cta-root', 'targeted CTA button edit writes once in place and preserves both root identities' );
$cta_patch_payload = $cta_targeted_response->get_data();
$cta_operation = wpae_design_operation_find_by_id( (string) ( $cta_patch_payload['operation_id'] ?? '' ) );
$cta_snapshot_id = (string) ( $cta_patch_payload['write']['rollback_snapshot_id'] ?? '' );
$cta_snapshot = wpae_get_rollback_snapshots()[ $cta_snapshot_id ] ?? [];
check( is_array( $cta_operation ) && ( $cta_operation['current_state'] ?? '' ) === 'written' && ( $cta_operation['operation_identity'] ?? '' ) === 'cta-targeted-patch-identity' && ( $cta_operation['root_ids'] ?? [] ) === [ 'selected-cta-root' ], 'successful targeted patch is durably registered as written under its exact request identity and root' );
check( $cta_snapshot_id !== '' && ( $cta_snapshot['operation_id'] ?? '' ) === ( $cta_operation['operation_id'] ?? '' ) && ( $cta_snapshot['operation_identity'] ?? '' ) === 'cta-targeted-patch-identity' && ( $cta_snapshot['root_ids'] ?? [] ) === [ 'selected-cta-root' ] && ! empty( $cta_snapshot['after_hashes'][42] ), 'rollback snapshot is bound to the same durable operation, post state, and owned root' );

$cta_retry = new WP_REST_Request();
$cta_retry->set_param( 'message', $cta_targeted_edit->get_param( 'message' ) );
$cta_retry->set_param( 'context', [ 'post_id' => 42, 'operation_identity' => 'cta-targeted-patch-identity', 'selected_elements' => [ [ 'id' => 'selected-cta-root' ] ] ] );
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( [ 'action' => 'patch_elements', 'post_id' => 42, 'patches' => [ [ 'element_id' => 'cta-primary', 'path' => 'settings.text', 'op' => 'set', 'value' => 'Связаться' ] ] ] ) ) ];
$cta_retry_response = wpae_llm_chat_request( $cta_retry );
check( $cta_retry_response instanceof WP_REST_Response && ( $cta_retry_response->get_data()['operation_id'] ?? '' ) === ( $cta_operation['operation_id'] ?? '' ) && ! empty( $cta_retry_response->get_data()['write']['idempotent'] ) && count( $GLOBALS['writes'] ) === 1, 'same request identity reconciles the saved patch without a second write' );

$cta_undo = new WP_REST_Request();
$cta_undo->set_param( 'post_id', 42 );
$cta_undo->set_param( 'operation_id', $cta_operation['operation_id'] );
$cta_undo->set_param( 'operation_identity', $cta_operation['operation_identity'] );
$cta_undo->set_param( 'revision', $cta_operation['revision'] );
$cta_undo->set_param( 'root_ids', $cta_operation['root_ids'] );
$cta_undo->set_param( 'rollback_snapshot_id', $cta_snapshot_id );
$cta_undo->set_param( 'operation_event', 'user_undo' );
$cta_undo_response = wpae_llm_undo( $cta_undo );
check( $cta_undo_response->get_status() === 200 && ! empty( $cta_undo_response->get_data()['ok'] ) && $GLOBALS['page_data'] === $cta_before_undo, 'operation-bound Undo restores the exact before-document without losing the unchanged neighbor' );
$cta_rolled_back = wpae_design_operation_find_by_id( (string) $cta_operation['operation_id'] );
check( ( $cta_rolled_back['current_state'] ?? '' ) === 'unknown' && ( $cta_rolled_back['rollback_event'] ?? '' ) === 'user_undo' && (int) ( $cta_rolled_back['revision'] ?? 0 ) > (int) $cta_operation['revision'], 'successful Undo advances the same ledger operation instead of losing its operation link' );
$stale_cta_undo = wpae_llm_undo( $cta_undo );
check( $stale_cta_undo->get_status() === 409 && ( $stale_cta_undo->get_data()['code'] ?? '' ) === 'wpae_undo_stale_revision' && $GLOBALS['page_data'] === $cta_before_undo, 'old Undo confirmation is rejected after the operation revision advances' );

$library_placeholder_changes = 0;
$library_placeholder_result = wpae_llm_normalize_library_layout( [ container_node( 'team-library-root', [], [ widget( 'team-library-photo', 'image', [ 'image' => [ 'url' => 'new-container-image-placeholder', 'id' => 0, 'source' => 'url' ] ] ), widget( 'team-library-name', 'heading', [ 'title' => 'Алия Садыкова' ] ) ] ) ], $library_placeholder_changes, 'team' );
check( count( $library_placeholder_result[0]['elements'] ?? [] ) === 1 && ( $library_placeholder_result[0]['elements'][0]['settings']['title'] ?? '' ) === 'Алия Садыкова' && $library_placeholder_changes > 0, 'Library template normalization removes an empty Team image slot instead of filling it with an invented stock portrait' );

$independent_patch = wpae_llm_execute_patch_action(
	[ 'action' => 'patch_elements', 'post_id' => 42, 'patches' => [ [ 'element_id' => 'cta-primary', 'path' => 'settings.text', 'op' => 'set', 'value' => 'Связаться' ] ] ],
	42,
	[ 'selected-cta-root' ],
	'Измени кнопки в выбранном CTA-блоке: основная «Связаться», вторичная «Посмотреть проекты».',
	[ 'operation_identity' => 'cta-same-brief-new-operation' ]
);
check( ! empty( $independent_patch['ok'] ) && $independent_patch['operation_id'] !== $cta_operation['operation_id'] && count( $GLOBALS['writes'] ) === 2, 'same patch content with a new explicit identity creates an independent operation' );
$GLOBALS['page_data'][0]['elements'][0]['settings']['title'] = 'Пользовательская правка после patch';
$independent_operation = wpae_design_operation_find_by_id( (string) $independent_patch['operation_id'] );
$independent_undo = new WP_REST_Request();
$independent_undo->set_param( 'post_id', 42 );
$independent_undo->set_param( 'operation_id', $independent_operation['operation_id'] );
$independent_undo->set_param( 'operation_identity', $independent_operation['operation_identity'] );
$independent_undo->set_param( 'revision', $independent_operation['revision'] );
$independent_undo->set_param( 'root_ids', $independent_operation['root_ids'] );
$independent_undo->set_param( 'rollback_snapshot_id', $independent_patch['rollback_snapshot_id'] );
$independent_undo->set_param( 'operation_event', 'user_undo' );
$conflicted_undo = wpae_llm_undo( $independent_undo );
check( $conflicted_undo->get_status() === 409 && ( $conflicted_undo->get_data()['code'] ?? '' ) === 'wpae_undo_conflict' && ( $GLOBALS['page_data'][0]['elements'][0]['settings']['title'] ?? '' ) === 'Пользовательская правка после patch' && ( $GLOBALS['page_data'][1]['elements'][0]['settings']['text'] ?? '' ) === 'Связаться', 'Undo refuses a stale full-page snapshot and preserves both the later neighbor edit and target patch' );

$permission = new WP_REST_Request();
$permission->set_param( 'post_id', 42 );
$permission->set_param( 'context', [ 'post_id' => 99 ] );
check( is_wp_error( wpae_llm_chat_permission( $permission ) ), 'Nested editor context bypassed post authorization' );
$permission->set_param( 'context', [ 'post_id' => 42 ] );
check( wpae_llm_chat_permission( $permission ) === true, 'Authorized editor blocked' );

echo 'flex generation runtime: ' . $GLOBALS['checks'] . " checks OK\n";
