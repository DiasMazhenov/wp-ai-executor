<?php

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ): string {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ): string {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ?? '' );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ): string {
		return trim( preg_replace( '/\r\n?/', "\n", strip_tags( (string) $value ) ) ?? '' );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ): string {
		return trim( (string) $value );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ): string {
		return (string) json_encode( $value, $flags );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		global $wpae_test_filters;
		return isset( $wpae_test_filters[ $tag ] ) && is_callable( $wpae_test_filters[ $tag ] )
			? $wpae_test_filters[ $tag ]( $value, ...$args )
			: $value;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool { return true; }
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( string $class ): string { return preg_replace( '/[^A-Za-z0-9_\-]/', '', $class ) ?? ''; }
}
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $tag ): int {
		global $wpae_test_actions;
		return (int) ( $wpae_test_actions[ $tag ] ?? 0 );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $wpae_test_options;
		return $wpae_test_options[ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ): bool {
		global $wpae_test_options;
		$wpae_test_options[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ): bool {
		global $wpae_test_options;
		if ( ! is_array( $wpae_test_options ?? null ) ) {
			$wpae_test_options = [];
		}
		if ( array_key_exists( $key, $wpae_test_options ) ) {
			return false;
		}
		$wpae_test_options[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ): bool {
		global $wpae_test_options;
		if ( ! is_array( $wpae_test_options ?? null ) ) {
			$wpae_test_options = [];
		}
		unset( $wpae_test_options[ $key ] );
		return true;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = false ): string {
		return gmdate( 'c' );
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $data;
		public function __construct( string $code, string $message = '', array $data = [] ) { $this->code = $code; $this->data = $data; }
		public function get_error_code(): string { return $this->code; }
		public function get_error_data(): array { return $this->data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params;
		public function __construct( $method_or_params = [], string $route = '' ) { $this->params = is_array( $method_or_params ) ? $method_or_params : []; }
		public function get_param( string $name ) { return $this->params[ $name ] ?? null; }
		public function set_param( string $name, $value ): void { $this->params[ $name ] = $value; }
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;
		public function __construct( $data = null, int $status = 200 ) { $this->data = $data; $this->status = $status; }
		public function get_data() { return $this->data; }
		public function get_status(): int { return $this->status; }
	}
}
if ( ! function_exists( 'wpae_capability_enabled' ) ) {
	function wpae_capability_enabled( string $capability ): bool { return $capability === 'elementor_writes'; }
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool { return false; }
}
if ( ! function_exists( 'wpae_elementor_update' ) ) {
	function wpae_elementor_update( WP_REST_Request $request ): WP_REST_Response {
		global $wpae_recipe_execute_mock;
		$dry_run = (bool) $request->get_param( 'dry_run' );
		$wpae_recipe_execute_mock['calls'][] = [ 'dry_run' => $dry_run, 'post_id' => (int) $request->get_param( 'post_id' ), 'elementor_data' => (array) $request->get_param( 'elementor_data' ) ];
		if ( $dry_run ) {
			return new WP_REST_Response( [ 'ok' => true, 'preflight' => [ 'mock' => true ] ], 200 );
		}
		return new WP_REST_Response( [ 'ok' => false, 'error' => 'contract_harness_write_block' ], 409 );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, ...$args ): bool {
		global $wpae_test_can_edit_post;
		return $capability === 'edit_post' && ! empty( $wpae_test_can_edit_post );
	}
}
if ( ! function_exists( 'wpae_get_elementor_data_for_post' ) ) {
	function wpae_get_elementor_data_for_post( int $post_id ): array {
		global $wpae_test_readback;
		return (array) ( $wpae_test_readback[ $post_id ] ?? [] );
	}
}
if ( ! function_exists( 'wpae_rollback_post_fingerprint' ) ) {
	function wpae_rollback_post_fingerprint( int $post_id ): string {
		global $wpae_test_fingerprints;
		return (string) ( $wpae_test_fingerprints[ $post_id ] ?? '' );
	}
}

require_once __DIR__ . '/../includes/design/token-resolution.php';
require_once __DIR__ . '/../includes/elementor/capability-registry.php';
$wpae_no_elementor_probe = wpae_widget_runtime_probe();
require_once __DIR__ . '/../includes/elementor/reference-set.php';
require_once __DIR__ . '/../includes/llm/llm.php';
require_once __DIR__ . '/../includes/elementor/layout-report.php';
require_once __DIR__ . '/../includes/elementor/elementor-ir.php';
require_once __DIR__ . '/../includes/elementor/native-compiler.php';
require_once __DIR__ . '/../includes/elementor/operation-ledger.php';
require_once __DIR__ . '/../includes/elementor/css-native.php';
require_once __DIR__ . '/../includes/elementor/token-map.php';
require_once __DIR__ . '/../includes/llm/routing.php';
require_once __DIR__ . '/../includes/design/system.php';
require_once __DIR__ . '/../includes/elementor/validation-rules.php';
require_once __DIR__ . '/../includes/elementor/normalize.php';

$checks = 0;
$check = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	$checks++;
};

$token_override_settings = [ 'text_color' => '#6b7280', '__globals__' => [ 'text_color' => 'globals/colors?id=bffb171', 'border_color' => 'globals/colors?id=secondary' ] ];
$token_override_report = [ 'mapped' => [], 'native_paths' => [], 'evidence' => [], 'source_roles' => [] ];
wpae_token_map_set( $token_override_settings, 'text_color', '#6b7280', 'service-copy', 'palette.muted', $token_override_report );
$check( ! isset( $token_override_settings['__globals__']['text_color'] ) && isset( $token_override_settings['__globals__']['border_color'] ) && $token_override_settings['text_color'] === '#6b7280', 'A semantic token override clears only its stale Elementor global color reference' );

$wpae_test_actions = [ 'elementor/widgets/register' => 1 ];
eval( 'namespace Elementor;
class Plugin {
	public static $mode = "ready";
	public static $types = [];
	public $widgets_manager;
	public static function instance() { return self::$mode === "missing_instance" ? null : new self(); }
	public function __construct() { $this->widgets_manager = new Widgets_Manager(); }
}
class Widgets_Manager {
	public function get_widget_types() {
		if ( Plugin::$mode === "throw" ) { throw new \\RuntimeException( "private test detail" ); }
		if ( Plugin::$mode === "manager_unavailable" ) { return null; }
		return array_fill_keys( Plugin::$types, new \\stdClass() );
	}
}' );
\Elementor\Plugin::$types = [ 'heading', 'text-editor', 'button', 'image', 'icon', 'icon-list', 'divider', 'accordion' ];
$check( $wpae_no_elementor_probe['state'] === 'unavailable' && $wpae_no_elementor_probe['reason'] === 'elementor_runtime_missing', 'absent Elementor runtime is reported as unavailable before test double registration' );

$hero_prompt = "hero\nНадзаголовок обычным текстом.\neyebrow: «Запуск без лишних шагов»\ntitle: «Соберите сильную страницу\nза один день»\nbody: «Понятный процесс для команды.»\nCTA: «Начать проект» -> https://example.com/start\nCTA: «Узнать больше» -> #about\nImage: https://example.com/contract.png\nFAQ\nО нас\n7 шагов";
$hero = wpae_brief_ir_parse( $hero_prompt );
$check( $hero['schema'] === 'wpae-brief-v1', 'BriefIR schema' );
$check( wpae_brief_ir_validate( $hero )['ok'], 'BriefIR validates' );
$check( $hero['locale'] === 'ru' && $hero['intent']['archetype'] === 'hero', 'Russian hero classification' );
$check( count( $hero['content'] ) >= 8, 'quoted copy and short labels retained' );
$titles = array_values( array_filter( $hero['content'], static fn( array $item ): bool => $item['role'] === 'title' ) );
$check( $titles[0]['exact_text'] === "Соберите сильную страницу\nза один день", 'multiline exact text preserved' );
$ctas = array_values( array_filter( $hero['content'], static fn( array $item ): bool => str_starts_with( (string) $item['role'], 'cta' ) ) );
$check( count( $ctas ) === 2 && $ctas[0]['url'] === 'https://example.com/start' && $ctas[1]['url'] === '#about', 'CTA URLs preserved separately' );
$check( in_array( 'FAQ', array_column( $hero['content'], 'exact_text' ), true ), 'FAQ label retained' );
$check( in_array( 'О нас', array_column( $hero['content'], 'exact_text' ), true ), 'О нас label retained' );
$check( in_array( '7 шагов', array_column( $hero['content'], 'exact_text' ), true ), '7 шагов label retained' );
$check( ! empty( $hero['content'][0]['provenance']['source_span'] ), 'content provenance present' );

$forbidden_ru = wpae_brief_ir_parse( 'Создай hero без изображения. Заголовок: «Комната для идей».' );
$forbidden_en = wpae_brief_ir_parse( 'Create a hero with no image. Title: "Room for ideas".' );
$forbidden_plan = wpae_design_plan_from_brief( $forbidden_ru );
$forbidden_ir = wpae_elementor_ir_from_design_plan( $forbidden_plan, $forbidden_ru );
$forbidden_compiled = wpae_elementor_ir_compile( $forbidden_ir, $forbidden_ru, [], [ 'id_seed' => 'no-media-contract' ] );
$check( wpae_design_plan_constraint_value( $forbidden_ru, 'media_intent' ) === 'forbidden' && wpae_design_plan_constraint_value( $forbidden_en, 'media_intent' ) === 'forbidden', 'Russian and English image prohibition becomes an explicit BriefIR constraint' );
$check( empty( array_filter( $forbidden_plan['sections'][0]['children'], static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'media' ) ) && $forbidden_plan['sections'][0]['composition'] === 'stacked_left', 'image prohibition compiles as a full-width copy composition' );
$check( $forbidden_compiled['ok'] && ! str_contains( wp_json_encode( $forbidden_compiled['elementor_data'] ), 'media_fallback' ) && ! str_contains( wp_json_encode( $forbidden_compiled['elementor_data'] ), 'min_height' ), 'forbidden media emits no empty placeholder or reserved column' );
$check( ( $forbidden_compiled['elementor_data'][0]['settings']['flex_direction'] ?? '' ) === 'column' && (float) ( $forbidden_compiled['elementor_data'][0]['elements'][0]['settings']['width']['size'] ?? 0 ) === 100.0, 'no-media hero has a full-width desktop text column' );
$forbidden_layout = wpae_layout_report_for_plan( $forbidden_plan );
$check( ( $forbidden_layout['evidence'] ?? '' ) === 'static_plan' && empty( $forbidden_layout['visual_render_verified'] ), 'static LayoutReport does not claim browser visual acceptance' );
$check( array_column( $forbidden_layout['breakpoints'], 'layout_axis' ) === [ 'column', 'column', 'column', 'column' ] && array_reduce( $forbidden_layout['breakpoints'], static fn( bool $ok, array $row ): bool => $ok && (float) ( $row['basis_percent']['copy_group'] ?? 0 ) === 100.0, true ), 'no-media LayoutReport agrees with the single full-width column at 1440/1024/768/390 assumptions' );
$unspecified_media = wpae_brief_ir_parse( 'Create a hero with title: "Room for ideas".' );
$unspecified_plan = wpae_design_plan_from_brief( $unspecified_media );
$check( ( $unspecified_plan['media_intent'] ?? '' ) === 'unspecified' && wpae_design_plan_validate( $unspecified_plan )['ok'] && empty( array_filter( $unspecified_plan['sections'][0]['children'], static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'media' ) ), 'missing URL stays unspecified and does not become an explicit prohibition or an empty image column' );
$text_hero_prompt = 'Создай hero. Надзаголовок: «ТИХАЯ ФОРМА». Заголовок: «Пространство для идей». Описание: «Опишите задачу и получите понятный первый шаг». Кнопка: «Начать проект», ссылка #contact.';
$text_hero_brief = wpae_brief_ir_parse( $text_hero_prompt );
$text_hero_plan = wpae_design_plan_from_brief( $text_hero_brief );
$text_hero_compiled = wpae_elementor_ir_compile( wpae_elementor_ir_from_design_plan( $text_hero_plan, $text_hero_brief ), $text_hero_brief, [], [ 'id_seed' => 'text-hero-contract' ] );
$check( ( $text_hero_plan['media_intent'] ?? '' ) === 'unspecified' && empty( array_filter( $text_hero_plan['sections'][0]['children'] ?? [], static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'media' ) ), 'historical text-only hero brief does not invent an image requirement or placeholder' );
$check( ! empty( $text_hero_compiled['ok'] ) && str_contains( wp_json_encode( $text_hero_compiled['elementor_data'], JSON_UNESCAPED_UNICODE ), 'Пространство для идей' ) && str_contains( wp_json_encode( $text_hero_compiled['elementor_data'], JSON_UNESCAPED_UNICODE ), '#contact' ), 'historical text-only hero keeps its exact title and CTA through native compilation' );
$required_missing = wpae_brief_ir_parse( 'Create a hero with an image required. Title: "Room for ideas".' );
$required_missing_plan = wpae_design_plan_from_brief( $required_missing );
$check( wpae_design_plan_constraint_value( $required_missing, 'media_intent' ) === 'required' && ! wpae_design_plan_validate( $required_missing_plan )['ok'] && in_array( 'required_media_asset_missing', wpae_design_plan_validate( $required_missing_plan )['errors'], true ), 'required image without a usable asset is rejected before compilation' );
$required_with_asset = wpae_brief_ir_parse( 'Create a hero with an image required. Title: "Room for ideas". Image: https://example.com/required.jpg' );
$required_with_asset_plan = wpae_design_plan_from_brief( $required_with_asset );
$check( wpae_design_plan_validate( $required_with_asset_plan )['ok'] && count( array_filter( $required_with_asset_plan['sections'][0]['children'], static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'media' ) ) === 1, 'required image with a valid asset continues through the hero plan' );
$invalid_asset = $required_with_asset;
$invalid_asset['media_references'][0]['source_url'] = 'javascript:alert(1)';
$invalid_asset_plan = wpae_design_plan_from_brief( $invalid_asset );
$check( ! wpae_design_plan_validate( $invalid_asset_plan )['ok'] && in_array( 'required_media_asset_missing', wpae_design_plan_validate( $invalid_asset_plan )['errors'], true ), 'required image with an invalid source is rejected before write' );
$contradictory = wpae_brief_ir_parse( 'Hero без изображения, но сделай split 60/40 с изображением.' );
$contradictory_plan = wpae_design_plan_from_brief( $contradictory );
$contradictory_validation = wpae_design_plan_validate( $contradictory_plan );
$check( wpae_design_plan_constraint_value( $contradictory, 'media_intent' ) === 'conflict' && empty( $contradictory_validation['ok'] ) && in_array( 'media_intent_conflict', $contradictory_validation['errors'], true ), 'conflicting image requirements are visible and rejected' );
$forbidden_with_asset = wpae_brief_ir_parse( 'Create a hero with no image, but keep this supplied image URL: https://example.com/hero.jpg' );
$check( wpae_design_plan_constraint_value( $forbidden_with_asset, 'media_intent' ) === 'conflict', 'explicit prohibition and an image asset URL are treated as a visible conflict' );

$semantic_hero = wpae_brief_ir_parse( 'Добавь новую hero-секцию для архитектурной студии «Тихая форма». Надзаголовок «АРХИТЕКТУРА». Заголовок «Пространство для идей». Описание «Опишите задачу и получите понятный первый шаг». Основная кнопка «Начать проект», ссылка #contact. Вторичная кнопка «Смотреть проекты», ссылка #projects. Надзаголовок обычным текстом. Текст слева занимает 40%, визуальная часть справа — 60%. Изображение: https://example.com/hero.png' );
$semantic_plan = wpae_design_plan_from_brief( $semantic_hero );
$check( ( $semantic_plan['sections'][0]['composition'] ?? '' ) === 'split_40_60' && ( $semantic_plan['media_intent'] ?? '' ) === 'required', 'explicit image URL is treated as a required media reference and preserves copy/media composition' );
$semantic_layout = wpae_layout_report_for_plan( $semantic_plan );
$check( (float) ( $semantic_layout['breakpoints'][0]['basis_percent']['copy_group'] ?? 0 ) === 40.0 && (float) ( $semantic_layout['breakpoints'][1]['basis_percent']['copy_group'] ?? 0 ) === 50.0 && (float) ( $semantic_layout['breakpoints'][3]['basis_percent']['media'] ?? 0 ) === 100.0, '40/60 LayoutReport mirrors compiled desktop/tablet/mobile widths' );
$semantic_ir = wpae_elementor_ir_from_design_plan( $semantic_plan, $semantic_hero );
$semantic_compiled = wpae_elementor_ir_compile( $semantic_ir, $semantic_hero, [ 'palette' => [ 'page_bg' => '#f6f0e6', 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#4b5563', 'primary' => '#4460ec', 'border' => '#d1d5db' ] ], [ 'id_seed' => 'semantic-hero' ] );
$semantic_root = $semantic_compiled['elementor_data'][0] ?? [];
$semantic_copy = $semantic_root['elements'][0] ?? [];
$semantic_media = $semantic_root['elements'][1] ?? [];
$semantic_copy_roles = array_column( (array) ( $semantic_copy['elements'] ?? [] ), 'widgetType' );
$check( (float) ( $semantic_copy['settings']['width']['size'] ?? 0 ) === 40.0 && (float) ( $semantic_media['settings']['width']['size'] ?? 0 ) === 60.0 && ! isset( $semantic_copy['settings']['flex_basis'] ) && ! isset( $semantic_media['settings']['flex_basis'] ), 'semantic hero compiler preserves native 40/60 width contract' );
$check( $semantic_copy_roles === [ 'heading', 'heading', 'heading', 'text-editor', 'button', 'button' ], 'copy widgets keep brand, eyebrow, title order and both CTAs: ' . wp_json_encode( $semantic_copy_roles ) );
$semantic_title = $semantic_copy['elements'][2]['settings'] ?? [];
$semantic_body = $semantic_copy['elements'][3]['settings'] ?? [];
$semantic_primary = $semantic_copy['elements'][4]['settings'] ?? [];
$semantic_secondary = $semantic_copy['elements'][5]['settings'] ?? [];
$check( ( $semantic_media['elements'][0]['widgetType'] ?? '' ) === 'image' && ( $semantic_media['elements'][0]['settings']['image']['url'] ?? '' ) === 'https://example.com/hero.png', 'explicit media asset is preserved as an editable native image widget inside a sized native container' );
$check( ( $semantic_title['header_size'] ?? '' ) === 'h1' && ( $semantic_title['typography_font_size_mobile']['size'] ?? 0 ) > 0 && ( $semantic_title['typography_line_height']['size'] ?? 0 ) > 0 && ( $semantic_body['typography_font_size']['size'] ?? 0 ) > 0, 'display and body typography tokens become native responsive settings' );
$semantic_desktop_padding = (array) ( $semantic_root['settings']['padding'] ?? [] );
$semantic_mobile_padding = (array) ( $semantic_root['settings']['padding_mobile'] ?? [] );
$check( (float) ( $semantic_desktop_padding['top'] ?? 0 ) === 4.5 && (float) ( $semantic_desktop_padding['left'] ?? 0 ) === 2.0 && (float) ( $semantic_mobile_padding['top'] ?? 0 ) === 2.0 && (float) ( $semantic_mobile_padding['left'] ?? 0 ) === 1.0 && ! array_key_exists( 'size', $semantic_desktop_padding ) && ! array_key_exists( 'size', $semantic_mobile_padding ) && wpae_elementor_native_control_error( 'padding', $semantic_desktop_padding ) === '' && wpae_elementor_native_control_error( 'padding_mobile', $semantic_mobile_padding ) === '', 'section spacing tokens compile as unambiguous native desktop/mobile padding dimensions' );
$check( ( $semantic_primary['background_color'] ?? '' ) !== ( $semantic_secondary['background_color'] ?? '' ) && ( $semantic_secondary['border_border'] ?? '' ) === 'solid' && ( $semantic_primary['text'] ?? '' ) === 'Начать проект' && ( $semantic_primary['link']['url'] ?? '' ) === '#contact' && ( $semantic_secondary['text'] ?? '' ) === 'Смотреть проекты' && ( $semantic_secondary['link']['url'] ?? '' ) === '#projects', 'two CTA widgets preserve exact order/URLs and compile primary/secondary native visual hierarchy' );

$live_hero_prompt = 'Создай ОДНУ новую hero-секцию на текущей странице. Для архитектурной студии «Тихая форма». Используй строго этот текст: надзаголовок «АРХИТЕКТУРА»; заголовок «Пространство для идей»; описание «Опишите задачу и получите понятный первый шаг»; основная кнопка «Начать проект» → #contact; вторичная кнопка «Смотреть проекты» → #projects. Явно не используй изображение: без image widget и без пустой визуальной/media-колонки или плейсхолдера. Текстовая композиция должна выглядеть законченной. Добавь аккуратный pill-бейдж над заголовком, карточную/визуальную обводку только если она не создаёт пустую media-зону. Не меняй ничего кроме добавления этой одной секции.';
$live_hero = wpae_brief_ir_parse( $live_hero_prompt );
$live_ctas = array_values( array_filter( $live_hero['content'], static fn( array $item ): bool => str_starts_with( (string) $item['role'], 'cta' ) ) );
$check( count( $live_ctas ) === 2 && $live_ctas[0]['url'] === '#contact' && $live_ctas[1]['url'] === '#projects', 'live prompt Unicode arrows preserve both CTA URL pairs in BriefIR' );
$check( count( array_filter( $live_hero['layout_constraints'], static fn( array $item ): bool => ( $item['kind'] ?? '' ) === 'eyebrow_presentation' && ( $item['value'] ?? '' ) === 'pill' ) ) === 1, 'explicit pill badge request is represented as a typed BriefIR constraint' );
$live_plan = wpae_design_plan_from_brief( $live_hero );
$live_ir = wpae_elementor_ir_from_design_plan( $live_plan, $live_hero );
$live_compiled = wpae_elementor_ir_compile( $live_ir, $live_hero, [], [ 'id_seed' => 'live-hero-regression' ] );
$live_root = $live_compiled['elementor_data'][0] ?? [];
$live_copy = $live_root['elements'][0] ?? [];
$live_nodes = (array) ( $live_copy['elements'] ?? [] );
$live_json = wp_json_encode( $live_root, JSON_UNESCAPED_UNICODE );
$live_buttons = array_values( array_filter( $live_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) );
$check( ! empty( $live_compiled['ok'] ) && count( $live_buttons ) === 2 && ( $live_buttons[0]['settings']['link']['url'] ?? '' ) === '#contact' && ( $live_buttons[1]['settings']['link']['url'] ?? '' ) === '#projects', 'live BriefIR CTA links survive DesignPlan, ElementorIR, and native compilation' );
$check( str_contains( $live_json, 'wpae-generated-badge' ) && str_contains( $live_json, 'АРХИТЕКТУРА' ) && str_contains( $live_json, 'widgetType":"heading' ), 'explicit hero pill compiles as a native editable badge containing exact eyebrow copy' );
$check( ( $live_root['settings']['background_color'] ?? '' ) === '#f6f0e6' && ( $live_compiled['report']['tokens']['resolved'][0]['source'] ?? '' ) === 'safe_default', 'built-in paper fallback remains allowed and its provenance is not mislabeled as project input' );
$separated_ctas = wpae_brief_ir_parse( 'hero primary button «One» → #one; secondary button «Two» → https://example.com/two' );
$separated_links = array_values( array_filter( $separated_ctas['content'], static fn( array $item ): bool => str_starts_with( (string) $item['role'], 'cta' ) ) );
$multiline_ctas = wpae_brief_ir_parse( "hero\nbutton: «First» → #first\nsecondary button: «Second» → https://example.com/second" );
$multiline_links = array_values( array_filter( $multiline_ctas['content'], static fn( array $item ): bool => str_starts_with( (string) $item['role'], 'cta' ) ) );
$check( count( $separated_links ) === 2 && $separated_links[0]['url'] === '#one' && $separated_links[1]['url'] === 'https://example.com/two', 'CTA URL association stays within each quoted CTA when separated by punctuation' );
$check( count( $multiline_links ) === 2 && $multiline_links[0]['url'] === '#first' && $multiline_links[1]['url'] === 'https://example.com/second', 'CTA URL association preserves pairs across line breaks' );
$invalid_cta = wpae_brief_ir_parse( 'hero button «Unsafe» → javascript:alert(1)' );
$check( ! wpae_brief_ir_validate( $invalid_cta )['ok'], 'explicit but unsafe CTA URL fails BriefIR validation instead of becoming a fake link' );
$pill_widgets = [];
$pill_walk = static function ( array $nodes ) use ( &$pill_walk, &$pill_widgets ): void {
	foreach ( $nodes as $node ) {
		if ( is_array( $node ) ) {
			$pill_widgets[] = $node;
			$pill_walk( (array) ( $node['elements'] ?? [] ) );
		}
	}
};
$pill_walk( [ $live_root ] );
$pill_label = array_values( array_filter( $pill_widgets, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'heading' && ( $node['settings']['title'] ?? '' ) === 'АРХИТЕКТУРА' ) )[0] ?? [];
$check( ! empty( $pill_label ) && ( $pill_label['settings']['title_color'] ?? '' ) === '#ffffff' && ( $pill_label['settings']['border_radius']['size'] ?? 0 ) >= 999 && ( $pill_label['settings']['_element_width'] ?? '' ) === 'initial' && ( $pill_label['settings']['_css_classes'] ?? '' ) === 'wpae-generated-badge-label', 'hero pill label stays editable, content-width and high-contrast in native Elementor settings' );
$default_tokens = [ 'design_prohibitions' => [], 'button_style' => [], 'palette' => [ 'paper' => '#f6f0e6' ] ];
$default_report = [];
wpae_design_token_value( 'color.page_bg', $default_tokens, $default_report );
$check( ( $default_report['resolved'][0]['source'] ?? '' ) === 'safe_default' && ( $default_report['resolved'][0]['value'] ?? '' ) === '#f6f0e6', 'sanitized but unstored project defaults keep honest safe-default provenance' );
$wpae_test_options['wp_ai_executor_design_tokens'] = [ 'palette' => [ 'paper' => '#eeeeee' ] ];
$project_report = [];
$project_tokens = [ 'design_prohibitions' => [], 'button_style' => [], 'palette' => [ 'paper' => '#eeeeee' ] ];
wpae_design_token_value( 'color.page_bg', $project_tokens, $project_report );
$check( ( $project_report['resolved'][0]['source'] ?? '' ) === 'project' && ( $project_report['resolved'][0]['value'] ?? '' ) === '#eeeeee', 'explicitly stored project color keeps project provenance and value' );
$wpae_test_options['wp_ai_executor_design_tokens'] = [];

$english = wpae_brief_ir_parse( 'hero title: "Launch faster" body: "A clear path." CTA: "Start now" -> https://example.com/go' );
$check( $english['locale'] === 'en' && $english['intent']['archetype'] === 'hero', 'English hero classification' );
$check( in_array( 'Launch faster', array_column( $english['content'], 'exact_text' ), true ), 'English exact copy retained' );
$style_only = wpae_brief_ir_parse( 'style: editorial premium, warm paper background' );
$check( empty( $style_only['content'] ) && ! empty( $style_only['style_references'] ), 'style-only prompt does not invent content' );
$ambiguous = wpae_brief_ir_parse( 'hero: «One phrase»' );
$check( ! empty( $ambiguous['ambiguities'] ), 'ambiguous quote is visible' );

$plan = wpae_design_plan_from_brief( $hero );
$plan_validation = wpae_design_plan_validate( $plan );
$check( $plan['schema'] === 'wpae-design-plan-v1' && $plan_validation['ok'], 'hero DesignPlan validates' );
$check( $plan['sections'][0]['children'][0]['allowed_widgets'] === [ 'heading', 'text-editor', 'button' ], 'plain hero plan restricts widgets' );
$layout = wpae_layout_report_for_plan( $plan );
$check( $layout['schema'] === 'wpae-layout-report-v1' && count( $layout['breakpoints'] ) === 4 && $layout['ok'], 'hero LayoutReport covers four breakpoints' );
$check( array_column( $layout['breakpoints'], 'layout_axis' ) === [ 'row', 'row', 'row', 'column' ] && (float) ( $layout['breakpoints'][3]['basis_percent']['copy_group'] ?? 0 ) === 100.0 && (float) ( $layout['breakpoints'][3]['basis_percent']['media'] ?? 0 ) === 100.0, 'split hero report agrees with compiled desktop/tablet row and mobile 100% stack assumptions' );
$check( (float) ( $layout['breakpoints'][0]['basis_percent']['copy_group'] ?? 0 ) === 60.0 && (float) ( $layout['breakpoints'][1]['basis_percent']['copy_group'] ?? 0 ) === 50.0 && (float) ( $layout['breakpoints'][2]['basis_percent']['media'] ?? 0 ) === 50.0, 'LayoutReport uses desktop 60/40 and compiler tablet 50/50 compositions at 1440/1024/768' );
$bad_layout = wpae_layout_report_for_plan( $plan, [ 'basis_overrides' => [ 'copy_group' => 0 ] ] );
$check( ! $bad_layout['ok'] && ! empty( $bad_layout['breakpoints'][0]['zero_width_nodes'] ), 'zero-width regression is rejected' );

$ir = wpae_elementor_ir_from_design_plan( $plan, $hero );
$ir_validation = wpae_elementor_ir_validate( $ir );
$check( $ir['schema'] === 'wpae-elementor-ir-v2' && $ir_validation['ok'], 'ElementorIR validates' );
$compiled = wpae_native_elementor_compile( $ir, $hero, [] , [ 'id_seed' => 'contract-test' ] );
$check( $compiled['ok'] && ! empty( $compiled['elementor_data'] ), 'native compiler emits Elementor data' );
$compiled_again = wpae_native_elementor_compile( $ir, $hero, [], [ 'id_seed' => 'contract-test' ] );
$check( wp_json_encode( $compiled['elementor_data'] ) === wp_json_encode( $compiled_again['elementor_data'] ), 'compiler IDs are deterministic' );
$compiled_independent = wpae_native_elementor_compile( $ir, $hero, [], [ 'id_seed' => 'contract-test-independent' ] );
$check( $compiled['elementor_data'][0]['id'] !== $compiled_independent['elementor_data'][0]['id'], 'independent insertions receive distinct root IDs' );
$check( ! empty( $compiled['report']['tokens']['resolved'] ), 'semantic tokens resolved' );
$check( ! empty( $compiled['report']['contrast']['ok'] ), 'token contrast gate passes safe defaults' );
$check( ! wpae_design_token_validate_contrast( [ 'palette' => [ 'paper' => '#f6f0e6', 'muted' => '#6b7280' ] ] )['ok'], 'small muted text below 4.5 contrast is rejected' );
$check( wpae_design_token_validate_contrast( [ 'palette' => [ 'paper' => '#f6f0e6', 'muted' => '#6b7280' ] ], [ 'color.muted.font_size_px' => 24 ] )['ok'], 'large muted text uses the large-text threshold' );
$check( $compiled['elementor_data'][0]['elType'] === 'container', 'compiler owns native root shape' );
$check( $compiled['elementor_data'][0]['elements'][0]['settings']['width']['size'] === 60.0 && $compiled['elementor_data'][0]['elements'][1]['settings']['width_mobile']['size'] === 100 && ! isset( $compiled['elementor_data'][0]['elements'][0]['settings']['flex_basis'] ), 'compiler applies native split and mobile width settings' );
$compiled_copy = $compiled['elementor_data'][0]['elements'][0]['elements'] ?? [];
$check( ( $compiled_copy[1]['settings']['title'] ?? '' ) === "Соберите сильную страницу\nза один день" && ( $compiled_copy[1]['settings']['header_size'] ?? '' ) === 'h1' && ( $compiled_copy[1]['settings']['typography_font_size_mobile']['size'] ?? 0 ) > 0 && ! isset( $compiled_copy[1]['settings']['min_height'] ), 'multiline long title keeps exact copy, responsive native type and intrinsic text height' );

$process = wpae_brief_ir_parse( "process\n«Step one»\n«Step two»\n«Step three»" );
$process_plan = wpae_design_plan_from_brief( $process );
$process_ir = wpae_elementor_ir_from_design_plan( $process_plan, $process );
$process_children = $process_ir['nodes'][0]['children'][0]['children'] ?? [];
$check( count( $process_children ) === 3 && array_reduce( $process_children, static fn( bool $valid, array $card ): bool => $valid && ( $card['role'] ?? '' ) === 'process_card', true ), 'unpaired process labels stay as separate reference cards without invented copy' );
$process_compiled = wpae_elementor_ir_compile( $process_ir, $process, [], [ 'id_seed' => 'process-contract' ] );
$process_root = $process_compiled['elementor_data'][0] ?? [];
$process_group = $process_root['elements'][0] ?? [];
$process_card_nodes = (array) ( $process_group['elements'] ?? [] );
$check( ! empty( $process_compiled['ok'] ) && ( $process_group['settings']['flex_direction'] ?? '' ) === 'row' && ( $process_group['settings']['flex_direction_mobile'] ?? '' ) === 'column' && ( $process_group['settings']['flex_direction_tablet'] ?? '' ) === 'column', 'active process compiler emits a horizontal desktop row and stacked tablet/mobile cards' );
$check( count( $process_card_nodes ) === 3 && ( $process_card_nodes[0]['elements'][0]['elements'][0]['settings']['_css_classes'] ?? '' ) === 'wpae-process-marker' && ( $process_card_nodes[0]['elements'][0]['elements'][1]['widgetType'] ?? '' ) === 'divider', 'process reference divider remains inside the marker row after its marker' );

$empty_process_brief = wpae_brief_ir_parse( 'сделай стандартный таймлайн' );
$empty_process_plan = wpae_design_plan_from_brief( $empty_process_brief );
$empty_process_validation = wpae_design_plan_validate( $empty_process_plan );
$empty_process_ir = wpae_elementor_ir_from_design_plan( $empty_process_plan, $empty_process_brief );
$empty_process_compiled = wpae_elementor_ir_compile( $empty_process_ir, $empty_process_brief, [], [ 'id_seed' => 'empty-process-contract' ] );
$check( empty( $empty_process_validation['ok'] ) && in_array( 'process_steps_missing_source_content', $empty_process_validation['errors'], true ), 'empty process request is rejected before it can produce a write plan' );
$check( empty( $empty_process_compiled['ok'] ) && in_array( 'process_steps_empty', $empty_process_compiled['errors'], true ), 'ElementorIR compiler refuses a process section with no native step widgets' );

$plain_process_brief = wpae_brief_ir_parse( "Процесс:\nЗаявка — запрос поступил\nУточнение — детали проверены\nСтарт — следующий шаг согласован" );
$plain_process_plan = wpae_design_plan_from_brief( $plain_process_brief );
$plain_process_validation = wpae_design_plan_validate( $plain_process_plan, $plain_process_brief );
$plain_process_steps = $plain_process_plan['sections'][0]['children'][0]['steps'] ?? [];
$check( ! empty( $plain_process_validation['ok'] ) && count( $plain_process_steps ) === 3, 'plain line-based process pairs become source-backed DesignPlan steps' );
$check( array_map( static fn( array $step ): array => [ $plain_process_brief['content'][ array_search( $step['label_ref'], array_column( $plain_process_brief['content'], 'id' ), true ) ]['exact_text'], $plain_process_brief['content'][ array_search( $step['text_ref'], array_column( $plain_process_brief['content'], 'id' ), true ) ]['exact_text'] ], $plain_process_steps ) === [ [ 'Заявка', 'запрос поступил' ], [ 'Уточнение', 'детали проверены' ], [ 'Старт', 'следующий шаг согласован' ] ], 'plain process parser preserves the exact ordered label/copy pairs' );

$process_reference = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/process-card-reference-v1.json' ), true );
$qa_process_prompt = 'Добавь отдельным новым root блок процесса «Процесс · QA» с тремя шагами: «01. Заявка» — «QA: запрос поступил»; «02. Уточнение» — «QA: детали проверены»; «03. Старт» — «QA: следующий шаг согласован». Свяжи шаги последовательными connector линиями; на mobile stack вертикально.';
$qa_process_brief = wpae_brief_ir_parse( $qa_process_prompt );
$qa_process_plan = wpae_design_plan_from_brief( $qa_process_brief );
$qa_process_ir = wpae_elementor_ir_from_design_plan( $qa_process_plan, $qa_process_brief );
$qa_steps = $qa_process_plan['sections'][0]['children'][0]['steps'] ?? [];
$check( ( $qa_process_plan['sections'][0]['badge_content_ref'] ?? '' ) === 'text' && count( $qa_steps ) === 3, 'process brief preserves its explicit section label and pairs only source-linked step copy' );
$check( array_map( static fn( array $step ): string => $qa_process_brief['content'][ array_search( $step['label_ref'], array_column( $qa_process_brief['content'], 'id' ), true ) ]['exact_text'], $qa_steps ) === [ '01. Заявка', '02. Уточнение', '03. Старт' ], 'process step headings remain exact source text' );
$qa_process_compiled = wpae_elementor_ir_compile( $qa_process_ir, $qa_process_brief, [], [ 'id_seed' => 'process-reference-contract' ] );
$qa_process_root = $qa_process_compiled['elementor_data'][0] ?? [];
$qa_badge = $qa_process_root['elements'][0] ?? [];
$qa_items = $qa_process_root['elements'][1] ?? [];
$qa_cards = (array) ( $qa_items['elements'] ?? [] );
$reference_card = $process_reference['card'] ?? [];
$first_card = $qa_cards[0] ?? [];
$first_card_settings = (array) ( $first_card['settings'] ?? [] );
$first_marker_row = $first_card['elements'][0] ?? [];
$first_marker = $first_marker_row['elements'][0] ?? [];
$first_divider = $first_marker_row['elements'][1] ?? [];
$check( ! empty( $qa_process_compiled['ok'] ) && ( $qa_badge['settings']['_css_classes'] ?? '' ) === 'wpae-generated-badge' && ( $qa_badge['settings']['background_color'] ?? '' ) === '#4460EC' && ! isset( $qa_badge['settings']['width'] ) && ( $qa_badge['elements'][0]['settings']['title'] ?? '' ) === 'Процесс · QA' && ! isset( $qa_badge['elements'][0]['settings']['background_color'] ), 'explicit process label compiles as one content-sized pill with exact text' );
$check( count( $qa_cards ) === 3 && array_column( array_map( static fn( array $card ): array => [ 'title' => $card['elements'][1]['settings']['title'] ?? '', 'copy' => $card['elements'][2]['settings']['editor'] ?? '' ], $qa_cards ), 'title' ) === [ '01. Заявка', '02. Уточнение', '03. Старт' ], 'active pipeline emits three reference cards with exact headings' );
$check( array_column( array_map( static fn( array $card ): array => [ 'copy' => $card['elements'][2]['settings']['editor'] ?? '' ], $qa_cards ), 'copy' ) === [ 'QA: запрос поступил', 'QA: детали проверены', 'QA: следующий шаг согласован' ], 'active pipeline preserves the exact paired descriptions' );
$first_card_child_roles = array_map( static fn( array $node ): string => ( $node['elType'] ?? '' ) === 'container' ? 'marker_row' : (string) ( $node['widgetType'] ?? '' ), (array) ( $first_card['elements'] ?? [] ) );
$check( $first_card_child_roles === $reference_card['widget_structure'], 'reference card retains its native marker-row, heading, and text-editor hierarchy' );
$check( ( $first_card_settings['background_color'] ?? '' ) === $reference_card['background_color'] && ( $first_card_settings['border_color'] ?? '' ) === $reference_card['border_color'] && ( $first_card_settings['border_width']['top'] ?? '' ) === '1' && ( $first_card_settings['border_radius']['top'] ?? '' ) === '20', 'reference card preserves its white surface, 1px border, and 20px radius' );
$check( ( $first_card_settings['padding']['top'] ?? '' ) === '1' && ( $first_card_settings['padding']['right'] ?? '' ) === '0.75' && ( $first_card_settings['padding']['bottom'] ?? '' ) === '1.25' && ( $first_card_settings['padding']['left'] ?? '' ) === '0.75', 'reference card preserves the supplied desktop padding' );
$marker_label = $first_marker['elements'][0] ?? [];
$check( ( $first_marker['settings']['background_color'] ?? '' ) === $reference_card['marker_background_color'] && ( $first_marker['settings']['border_radius']['top'] ?? '' ) === '999' && (float) ( $first_marker['settings']['width']['size'] ?? 0 ) === (float) $reference_card['marker_width_rem'] && (float) ( $first_marker['settings']['width_mobile']['size'] ?? 0 ) === (float) $reference_card['marker_width_mobile_rem'] && ( $marker_label['settings']['_css_classes'] ?? '' ) === $reference_card['marker_label_css_class'], 'reference card preserves the blue circular numbered marker size and heading at desktop and mobile' );
$check( ( $first_divider['widgetType'] ?? '' ) === 'divider' && (float) ( $first_divider['settings']['weight']['size'] ?? 0 ) === (float) $reference_card['divider']['weight_px'] && (float) ( $first_divider['settings']['gap']['size'] ?? 0 ) === (float) $reference_card['divider']['gap_px'] && (float) ( $first_divider['settings']['width']['size'] ?? 0 ) === (float) $reference_card['divider']['width_percent'], 'reference card uses its native Divider connector geometry' );
$check( (float) ( $qa_cards[0]['settings']['width']['size'] ?? 0 ) > 30 && (float) ( $qa_cards[0]['settings']['width_mobile']['size'] ?? 0 ) === (float) $reference_card['mobile_width_percent'], 'three process cards expand evenly while mobile cards use the reference full width' );
$pricing = wpae_brief_ir_parse( 'pricing. «Basic» — «10 000 ₸» — «Base description». «Pro» — «20 000 ₸» — «Team description». «Team» — «30 000 ₸» — «Team description».' );
$pricing_plan = wpae_design_plan_from_brief( $pricing );
$check( $pricing_plan['archetype'] === 'pricing' && wpae_design_plan_validate( $pricing_plan )['ok'], 'pricing typed plan validates' );
$pricing_brief = wpae_brief_ir_parse( 'pricing. «Старт» — «от 50 000 ₸» — «Для небольшой задачи». «Проект» — «от 150 000 ₸» — «Для комплексной работы». «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач».' );
$pricing_ir = wpae_elementor_ir_from_design_plan( wpae_design_plan_from_brief( $pricing_brief ), $pricing_brief );
$pricing_compiled = wpae_elementor_ir_compile( $pricing_ir, $pricing_brief, [], [ 'id_seed' => 'pricing-contract' ] );
$pricing_cards = $pricing_compiled['elementor_data'][0]['elements'][0] ?? [];
$pricing_card_nodes = (array) ( $pricing_cards['elements'] ?? [] );
$check( (float) ( $pricing_compiled['elementor_data'][0]['elements'][0]['settings']['width']['size'] ?? 0 ) === 100.0, 'pricing group is full-width when section has one child' );
$check( ( $pricing_cards['settings']['flex_direction'] ?? '' ) === 'row' && count( $pricing_card_nodes ) === 3, 'pricing compiler emits a desktop card row' );
$check( (float) ( $pricing_card_nodes[0]['settings']['width']['size'] ?? 0 ) > 30 && (float) ( $pricing_card_nodes[0]['settings']['width']['size'] ?? 0 ) < 32 && (float) ( $pricing_card_nodes[0]['settings']['width_mobile']['size'] ?? 0 ) === 100.0, 'pricing cards reserve native gaps on desktop and stack on mobile' );
$pricing_card_settings = (array) ( $pricing_card_nodes[0]['settings'] ?? [] );
$check( ( $pricing_card_settings['border_border'] ?? '' ) === 'solid' && ( $pricing_card_settings['border_color'] ?? '' ) !== '', 'pricing cards keep a native semantic border' );
$check( ( $pricing_card_settings['border_radius']['unit'] ?? '' ) === 'rem' && (float) ( $pricing_card_settings['border_radius']['size'] ?? 0 ) > 0 && (float) ( $pricing_card_settings['padding']['size'] ?? 0 ) > 0, 'pricing cards compile token radius and component padding' );
$pricing_card_body = (array) ( $pricing_card_nodes[0]['elements'][0] ?? [] );
$pricing_card_details = (array) ( $pricing_card_body['elements'][0] ?? [] );
$pricing_price_group = (array) ( $pricing_card_details['elements'][1] ?? [] );
$check( ( $pricing_cards['settings']['flex_align_items'] ?? '' ) === 'stretch' && ( $pricing_card_settings['flex_justify_content'] ?? '' ) === 'space-between' && ( $pricing_card_settings['flex_justify_content_mobile'] ?? '' ) === 'flex-start' && ! isset( $pricing_card_settings['height'], $pricing_card_settings['min_height'] ), 'pricing uses native flex to equalize row cards while preserving natural mobile card height' );
$check( ( $pricing_price_group['settings']['flex_direction'] ?? '' ) === 'row' && count( $pricing_price_group['elements'] ?? [] ) === 1, 'pricing amount and optional period share a compact native row when period is absent' );
$check( ( $pricing_card_body['elType'] ?? '' ) === 'container' && count( $pricing_card_nodes[0]['elements'] ?? [] ) === 1, 'pricing cards keep copy in a body group and omit an empty action footer' );
$pricing_live_brief = wpae_brief_ir_parse( 'Надзаголовок: «ТАРИФЫ». Заголовок: «Выберите формат работы». «Старт» — «от 50 000 ₸» — «Для небольшой задачи». Кнопка: «Выбрать тариф», ссылка #start. «Проект» — «от 150 000 ₸/мес» — «Для комплексной работы с несколькими этапами, согласованием материалов и поддержкой команды на протяжении всего проекта». Кнопка: «Выбрать тариф», ссылка #project. «Поддержка» — «от 80 000 ₸/год» — «Для регулярного сопровождения». Кнопка: «Выбрать тариф», ссылка #support.' );
$pricing_live_ir = wpae_elementor_ir_from_design_plan( wpae_design_plan_from_brief( $pricing_live_brief ), $pricing_live_brief );
$pricing_live_compiled = wpae_elementor_ir_compile( $pricing_live_ir, $pricing_live_brief, [], [ 'id_seed' => 'pricing-live-contract' ] );
$pricing_live_root = $pricing_live_compiled['elementor_data'][0] ?? [];
$pricing_live_group = $pricing_live_root['elements'][1] ?? [];
$pricing_live_cards = (array) ( $pricing_live_group['elements'] ?? [] );
$pricing_live_card_settings = (array) ( $pricing_live_cards[0]['settings'] ?? [] );
$check( count( $pricing_live_cards[0]['elements'] ?? [] ) === 2 && ( $pricing_live_cards[0]['elements'][0]['elType'] ?? '' ) === 'container' && ( $pricing_live_cards[0]['elements'][1]['elType'] ?? '' ) === 'container' && ( $pricing_live_cards[0]['elements'][1]['elements'][0]['widgetType'] ?? '' ) === 'button' && ( $pricing_live_card_settings['flex_justify_content'] ?? '' ) === 'space-between', 'pricing separates card body/actions and uses native flex to align action groups to the common desktop baseline' );
$pricing_live_eyebrow = (array) ( $pricing_live_root['elements'][0]['elements'][0] ?? [] );
$pricing_live_rows = array_map( static function ( array $card ): array {
	$body = (array) ( $card['elements'][0] ?? [] );
	$details = (array) ( $body['elements'][0]['elements'] ?? [] );
	$price_widgets = (array) ( $details[1]['elements'] ?? [] );
	$actions = (array) ( $card['elements'][1] ?? [] );
	$button = (array) ( $actions['elements'][0] ?? [] );
	return [
		'name' => (string) ( $details[0]['settings']['title'] ?? '' ),
		'price' => (string) ( $price_widgets[0]['settings']['title'] ?? '' ),
		'period' => (string) ( $price_widgets[1]['settings']['editor'] ?? '' ),
		'description' => (string) ( $details[2]['settings']['editor'] ?? '' ),
		'cta' => (string) ( $button['settings']['text'] ?? '' ),
		'url' => (string) ( $button['settings']['link']['url'] ?? '' ),
	];
}, $pricing_live_cards );
$pricing_live_urls = array_column( $pricing_live_rows, 'url' );
$check( count( $pricing_live_root['elements'] ?? [] ) === 2 && (float) ( $pricing_live_root['elements'][0]['settings']['width']['size'] ?? 0 ) === 100.0 && (float) ( $pricing_live_root['elements'][1]['settings']['width']['size'] ?? 0 ) === 100.0, 'pricing intro and card group stay full-width in a stacked section' );
$pricing_live_cta = array_values( array_filter( (array) ( $pricing_live_brief['content'] ?? [] ), static fn( array $item ): bool => ( $item['id'] ?? '' ) === 'pricing_3_cta' ) )[0] ?? [];
$check( ( $pricing_live_cta['url'] ?? '' ) === '#support', 'long quoted pricing values preserve the complete CTA URL on the CTA slot' );
$check( ( $pricing_live_root['settings']['background_color'] ?? '' ) === '#ffffff', 'pricing section uses the surface token instead of the warm page background' );
$check( count( $pricing_live_cards ) === 3 && $pricing_live_urls === [ '#start', '#project', '#support' ], 'pricing parser/compiler preserves three card CTA URLs' );
$check( $pricing_live_rows === [
   [ 'name' => 'Старт', 'price' => 'от 50 000 ₸', 'period' => '', 'description' => 'Для небольшой задачи', 'cta' => 'Выбрать тариф', 'url' => '#start' ],
	[ 'name' => 'Проект', 'price' => 'от 150 000 ₸', 'period' => '/мес', 'description' => 'Для комплексной работы с несколькими этапами, согласованием материалов и поддержкой команды на протяжении всего проекта', 'cta' => 'Выбрать тариф', 'url' => '#project' ],
	[ 'name' => 'Поддержка', 'price' => 'от 80 000 ₸', 'period' => '/год', 'description' => 'Для регулярного сопровождения', 'cta' => 'Выбрать тариф', 'url' => '#support' ],
], 'pricing compiler preserves different exact descriptions and the optional period without changing CTA bindings' );
$check( ( $pricing_live_eyebrow['settings']['background_color'] ?? '' ) === '#4460EC' && ( $pricing_live_eyebrow['settings']['border_radius']['unit'] ?? '' ) === 'px' && (float) ( $pricing_live_eyebrow['settings']['border_radius']['size'] ?? 0 ) >= 999, 'pricing eyebrow compiles as a native pill badge' );

$faq_prompt = "FAQ\nЗаголовок: «Ответы на вопросы»\nВопрос 1: «Как проходит работа?»\nОтвет 1: «Сначала согласуем задачу, затем соберём страницу.»\nВопрос 2: «Можно ли изменить содержание?»\nОтвет 2: «Да, каждый текст остаётся редактируемым.»\nКнопка: «Задать вопрос», ссылка #contact. Дизайн: чистая белая поверхность, тонкая светло-серая обводка и скругление 12px.";
$faq_brief = wpae_brief_ir_parse( $faq_prompt );
$faq_plan = wpae_design_plan_from_brief( $faq_brief );
$faq_ir = wpae_elementor_ir_from_design_plan( $faq_plan, $faq_brief );
$faq_compiled = wpae_native_elementor_compile( $faq_ir, $faq_brief, [ 'palette' => [ 'paper' => '#f6f0e6', 'surface' => '#ffffff', 'ink' => '#111827', 'muted' => '#4b5563', 'accent' => '#4460ec', 'border' => '#d1d5db' ] ], [ 'id_seed' => 'faq-native-accordion' ] );
$faq_surface = $faq_compiled['elementor_data'][0]['elements'][1] ?? [];
$faq_widget = $faq_surface['elements'][0] ?? [];
$faq_tabs = (array) ( $faq_widget['settings']['tabs'] ?? [] );
$check( $faq_brief['intent']['archetype'] === 'faq' && count( array_filter( $faq_brief['content'], static fn( array $item ): bool => in_array( $item['role'], [ 'faq_question', 'faq_answer' ], true ) ) ) === 4, 'FAQ BriefIR retains question and answer slots separately' );
$check( $faq_brief['parser_version'] === 'wpae-brief-parser-v12', 'BriefIR provenance version tracks the current prompt parser contract' );
$check( wpae_design_plan_validate( $faq_plan )['ok'] && ! empty( $faq_compiled['ok'] ) && ( $faq_widget['widgetType'] ?? '' ) === 'accordion', 'FAQ uses the existing typed pipeline and compiles to Elementor Accordion' );
$check( ( $faq_compiled['elementor_data'][0]['elements'][0]['elements'][0]['settings']['title'] ?? '' ) === 'FAQ', 'FAQ preserves the short category label in an editable heading' );
$check( array_column( $faq_tabs, 'tab_title' ) === [ 'Как проходит работа?', 'Можно ли изменить содержание?' ] && array_column( $faq_tabs, 'tab_content' ) === [ '<p>Сначала согласуем задачу, затем соберём страницу.</p>', '<p>Да, каждый текст остаётся редактируемым.</p>' ], 'Accordion freezes exact plain answers in the Elementor WYSIWYG native paragraph representation' );
$faq_roundtrip = $faq_compiled['report']['native_roundtrip'] ?? [];
$faq_roundtrip_twice = wpae_native_roundtrip_compile_tree( $faq_compiled['elementor_data'] );
$check( ( $faq_roundtrip['version'] ?? '' ) === WPAE_NATIVE_ROUNDTRIP_VERSION && count( $faq_roundtrip['decisions'] ?? [] ) === 2 && empty( $faq_roundtrip_twice['diagnostics']['decisions'] ) && $faq_roundtrip_twice['elements'] === $faq_compiled['elementor_data'], 'Accordion native representation is versioned, diagnostic and idempotent before accepted freeze' );
$check( count( array_unique( array_column( $faq_tabs, '_id' ) ) ) === 2 && ( $faq_widget['settings']['selected_icon']['value'] ?? '' ) === 'fas fa-angle-down', 'Accordion tabs receive unique stable IDs and native toggle icon settings' );
$check( ( $faq_compiled['elementor_data'][0]['settings']['background_color'] ?? '' ) === '#f6f0e6', 'FAQ white surface stays scoped to the rounded inner surface instead of flattening the entire page section' );
$check( ( $faq_surface['settings']['background_color'] ?? '' ) === '#ffffff' && ( $faq_surface['settings']['border_radius']['unit'] ?? '' ) === 'px' && (float) ( $faq_surface['settings']['border_radius']['size'] ?? 0 ) === 12.0 && ( $faq_surface['settings']['border_border'] ?? '' ) === 'solid' && (float) ( $faq_surface['settings']['border_width']['size'] ?? 0 ) === 1.0, 'explicit FAQ white surface, light border and 12px radius compile onto the native container' );
$check( ( $faq_widget['settings']['title_background'] ?? '' ) === '#ffffff' && ( $faq_widget['settings']['content_background_color'] ?? '' ) === '#ffffff' && (float) ( $faq_widget['settings']['border_width']['size'] ?? -1 ) === 0.0 && ( $faq_widget['settings']['border_color'] ?? '' ) !== '' && str_contains( (string) ( $faq_widget['settings']['custom_css'] ?? '' ), 'border-bottom: 1px solid' ), 'native Accordion drops its duplicate frame but retains token-backed separators inside the outer card' );
$faq_css = (string) ( $faq_widget['settings']['custom_css'] ?? '' );
$check( ( $faq_widget['settings']['title_color'] ?? '' ) === '#111827' && ( $faq_widget['settings']['title_active_color'] ?? '' ) === '#111827' && ( $faq_widget['settings']['title_hover_color'] ?? '' ) === '#111827' && ( $faq_widget['settings']['icon_hover_color'] ?? '' ) === '#111827' && str_contains( $faq_css, 'selector .elementor-tab-title:focus-visible' ) && str_contains( $faq_css, 'max-width: 70ch' ) && str_contains( $faq_css, '#2563eb' ) && ! str_contains( $faq_css, '!important' ), 'Accordion active/hover use native semantic controls while scoped answer width and keyboard focus use shared tokens' );
$check( ( $faq_widget['settings']['title_padding_mobile']['right'] ?? '' ) === '0.75' && ( $faq_widget['settings']['content_padding_mobile']['left'] ?? '' ) === '0.25', 'mobile Accordion reduces nested padding without reducing font size or answer height' );
$faq_action = $faq_compiled['elementor_data'][0]['elements'][2]['elements'][0] ?? [];
$check( ( $faq_action['widgetType'] ?? '' ) === 'button' && ( $faq_action['settings']['text'] ?? '' ) === 'Задать вопрос' && ( $faq_action['settings']['link']['url'] ?? '' ) === '#contact', 'FAQ keeps an optional explicit CTA after the native Accordion' );
$incomplete_faq = wpae_design_plan_from_brief( wpae_brief_ir_parse( 'FAQ\nВопрос: «Есть ли поддержка?»' ) );
$check( ! wpae_design_plan_validate( $incomplete_faq )['ok'] && in_array( 'faq_questions_and_answers_required', wpae_design_plan_validate( $incomplete_faq )['errors'], true ), 'FAQ without an explicit answer is rejected before compilation' );

$benefits_prompt = "Преимущества\nЗаголовок: «Понятный процесс»\nПреимущество 1: «Прозрачные этапы»\nОписание преимущества 1: «Каждый шаг согласован до начала работы.»\nПреимущество 2: «Удобное редактирование»\nОписание преимущества 2: «Содержание доступно в native Elementor widgets.»\nПреимущество 3: «Адаптация под экран»\nОписание преимущества 3: «Карточки складываются в одну колонку на телефоне.»\nКнопка: «Узнать больше», ссылка #details.";
$benefits_brief = wpae_brief_ir_parse( $benefits_prompt );
$benefits_plan = wpae_design_plan_from_brief( $benefits_brief );
$benefits_ir = wpae_elementor_ir_from_design_plan( $benefits_plan, $benefits_brief );
$benefits_compiled = wpae_native_elementor_compile( $benefits_ir, $benefits_brief, [], [ 'id_seed' => 'benefits-native-cards' ] );
$benefits_group = $benefits_compiled['elementor_data'][0]['elements'][1] ?? [];
$benefits_cards = (array) ( $benefits_group['elements'] ?? [] );
$check( $benefits_brief['intent']['archetype'] === 'benefits' && wpae_design_plan_validate( $benefits_plan )['ok'] && ! empty( $benefits_compiled['ok'] ), 'feature-card brief passes typed plan and native compiler validation' );
$check( count( $benefits_cards ) === 3 && array_column( array_map( static fn( array $card ): array => [ 'title' => $card['elements'][1]['settings']['title'] ?? '' ], $benefits_cards ), 'title' ) === [ 'Прозрачные этапы', 'Удобное редактирование', 'Адаптация под экран' ], 'feature grid keeps explicit content pairs in order' );
$benefits_cta = $benefits_compiled['elementor_data'][0]['elements'][0]['elements'][1] ?? [];
$check( ( $benefits_cta['widgetType'] ?? '' ) === 'button' && ( $benefits_cta['settings']['text'] ?? '' ) === 'Узнать больше' && ( $benefits_cta['settings']['link']['url'] ?? '' ) === '#details', 'feature grid keeps an optional explicit CTA and URL' );
$check( ( $benefits_cards[0]['elements'][0]['widgetType'] ?? '' ) === 'icon' && ( $benefits_cards[0]['elements'][0]['settings']['selected_icon']['value'] ?? '' ) === 'fas fa-check-circle' && ( $benefits_cards[0]['settings']['border_border'] ?? '' ) === 'solid', 'feature cards use native Icon widgets and token-backed bordered surfaces' );
$check( ( $benefits_group['settings']['background_color'] ?? 'transparent' ) === 'transparent' && ( $benefits_cards[0]['settings']['background_color'] ?? '' ) === '#ffffff', 'benefits gap stays transparent while each card keeps its own white surface' );
$check( ( $benefits_cards[0]['settings']['flex_align_items'] ?? '' ) === 'stretch' && ( $benefits_cards[0]['elements'][0]['settings']['align'] ?? '' ) === 'left' && ( $benefits_cards[0]['elements'][1]['settings']['align'] ?? '' ) === 'left' && ( $benefits_cards[0]['elements'][2]['settings']['align'] ?? '' ) === 'left', 'benefit icon, heading and body align left inside full-width card content' );
$check( ( $benefits_group['settings']['flex_direction_mobile'] ?? '' ) === 'column' && (float) ( $benefits_cards[0]['settings']['width_mobile']['size'] ?? 0 ) === 100.0, 'feature cards stack to full width at mobile breakpoint' );
$two_benefits_prompt = "Features\nFeature 1: \"Clear scope\"\nFeature description 1: \"" . str_repeat( 'The written scope keeps each approval visible. ', 5 ) . "\"\nFeature 2: \"Native editing\"\nFeature description 2: \"Text stays editable in Elementor.\"";
$two_benefits_brief = wpae_brief_ir_parse( $two_benefits_prompt );
$two_benefits_plan = wpae_design_plan_from_brief( $two_benefits_brief, [ 'canonical_create' => true ] );
$two_benefits_tree = wpae_native_elementor_compile( wpae_elementor_ir_from_design_plan( $two_benefits_plan, $two_benefits_brief ), $two_benefits_brief, [], [ 'id_seed' => 'two-benefits-long-copy' ] );
$two_benefits_group = [];
foreach ( (array) ( $two_benefits_tree['elementor_data'][0]['elements'] ?? [] ) as $compiled_child ) {
	if ( ( $compiled_child['settings']['_css_classes'] ?? '' ) === 'wpae-feature-cards' ) {
		$two_benefits_group = $compiled_child;
		break;
	}
}
$check( count( (array) ( $two_benefits_group['elements'] ?? [] ) ) === 2 && ( $two_benefits_group['elements'][0]['elements'][2]['settings']['editor'] ?? '' ) === trim( str_repeat( 'The written scope keeps each approval visible. ', 5 ) ), 'feature compiler adapts to two cards and preserves long exact copy' );
$check( ( $two_benefits_group['settings']['container_type'] ?? '' ) === 'grid' && ( $two_benefits_group['settings']['grid_columns_grid']['unit'] ?? '' ) === 'fr' && ( $two_benefits_group['settings']['grid_columns_grid']['size'] ?? 0 ) === 2 && (float) ( $two_benefits_group['settings']['grid_gaps']['column'] ?? 0 ) > 0 && ( $two_benefits_plan['visual_policy']['collection']['item_count'] ?? 0 ) === 2, 'two Benefits items use accepted equal native Grid tracks with a real inter-column gap' );
$check( ( $two_benefits_group['elements'][0]['elements'][1]['settings']['header_size'] ?? '' ) === 'h3' && (float) ( $two_benefits_group['elements'][0]['elements'][1]['settings']['typography_font_size']['size'] ?? 0 ) === 1.125, 'benefit card title uses component typography rather than hero display type' );
$unpaired_benefits = wpae_design_plan_from_brief( wpae_brief_ir_parse( "Features\nFeature: «Structured pages»\nFeature: «Editable content»\nFeature description: «Only the first item has an explicit description.»" ) );
$unpaired_validation = wpae_design_plan_validate( $unpaired_benefits );
$check( ! $unpaired_validation['ok'] && in_array( 'benefits_unpaired_feature_title', $unpaired_validation['errors'], true ), 'unpaired feature content is rejected instead of assigned to a different card' );

$boundary_url_a = 'https://example.com/cta/' . str_repeat( 'длинный-сегмент-', 18 ) . 'финал';
$boundary_prompt = "pricing\nКнопка: «Начать проект», описание: " . str_repeat( 'Длинное описание с переносом строки. ', 14 ) . "\nссылка {$boundary_url_a}.\nКнопка: «Вторая кнопка», ссылка #second.";
$boundary_brief = wpae_brief_ir_parse( $boundary_prompt );
$boundary_ctas = array_values( array_filter( (array) ( $boundary_brief['content'] ?? [] ), static fn( array $item ): bool => str_starts_with( (string) ( $item['role'] ?? '' ), 'cta' ) ) );
$check( count( $boundary_ctas ) === 2 && ( $boundary_ctas[0]['url'] ?? '' ) === $boundary_url_a && ( $boundary_ctas[1]['url'] ?? '' ) === '#second', 'URL extraction crosses long UTF-8/newline segments without stealing the adjacent CTA target' );
$explicit_surface_brief = wpae_brief_ir_parse( 'pricing: white cards on background #123456' );
$explicit_surface_plan = wpae_design_plan_from_brief( $explicit_surface_brief );
$explicit_surface_ir = wpae_elementor_ir_from_design_plan( $explicit_surface_plan, $explicit_surface_brief );
$explicit_surface_compiled = wpae_elementor_ir_compile( $explicit_surface_ir, $explicit_surface_brief, [ 'palette' => [ 'page_bg' => '#f6f0e6', 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#4b5563', 'primary' => '#4460ec', 'border' => '#d1d5db' ] ], [ 'id_seed' => 'explicit-surface' ] );
$check( ( $explicit_surface_plan['sections'][0]['surface_override'] ?? '' ) === '#123456', 'explicit background is retained in the typed plan' );
$check( ( $explicit_surface_compiled['elementor_data'][0]['settings']['background_color'] ?? '' ) === '#123456', 'explicit background overrides the semantic surface token only at the compiled section' );

$unknown = wpae_widget_capability_resolve( 'imaginary-widget' );
$check( empty( $unknown['ok'] ) && $unknown['reason'] === 'not_in_capability_registry', 'unknown widget is rejected instead of guessed into a fallback' );
$heading_capability = wpae_widget_capability( 'heading' );
$check( $heading_capability['available'] && $heading_capability['runtime_result'] === 'present', 'runtime confirms heading availability' );
$supported_native_widgets = wpae_widget_capability_report( [ 'accordion', 'icon' ] );
$check( ! empty( $supported_native_widgets['ok'] ) && $supported_native_widgets['available'] === [ 'accordion', 'icon' ], 'native compiler and runtime registry both confirm Accordion and Icon support' );
$wpae_test_filters['wpae_widget_capability_registry'] = static function ( array $registry ): array {
	$registry['heading']['available'] = false;
	return $registry;
};
$restricted_heading = wpae_widget_capability( 'heading' );
$check( ! $restricted_heading['available'] && $restricted_heading['static_policy'] === 'denied', 'runtime presence cannot override an explicit registry restriction' );
$wpae_test_filters['wpae_widget_capability_registry'] = static function ( array $registry ): array {
	$registry['heading']['available'] = true;
	return $registry;
};
\Elementor\Plugin::$types = array_values( array_diff( \Elementor\Plugin::$types, [ 'heading' ] ) );
$missing_heading = wpae_widget_capability( 'heading' );
$check( empty( $missing_heading['available'] ) && $missing_heading['runtime_result'] === 'missing', 'runtime omission defeats static available=true' );
$wpae_test_filters = [];
\Elementor\Plugin::$types = [];
$container_capability = wpae_widget_capability( 'container' );
$check( $container_capability['available'] && $container_capability['runtime_result'] === 'structural', 'container is resolved as a structural element outside the widget list' );
$wpae_test_actions['elementor/widgets/register'] = 0;
$not_ready = wpae_widget_capability( 'heading' );
$check( ! $not_ready['available'] && $not_ready['runtime_result'] === 'unknown' && $not_ready['reason'] === 'widget_registration_not_ready', 'unregistered Elementor runtime remains unknown and unavailable' );
$wpae_test_actions['elementor/widgets/register'] = 1;
\Elementor\Plugin::$mode = 'manager_unavailable';
$no_manager = wpae_widget_capability_report( [ 'heading' ] );
$check( empty( $no_manager['ok'] ) && $no_manager['unknown'] === [ 'heading' ], 'missing runtime manager is reported as unknown, not available' );
\Elementor\Plugin::$mode = 'throw';
$probe_error = wpae_widget_capability( 'heading' );
$check( ! $probe_error['available'] && $probe_error['runtime_result'] === 'unknown' && $probe_error['reason'] === 'widget_probe_failed', 'runtime exception is redacted and fails closed' );
\Elementor\Plugin::$mode = 'ready';
\Elementor\Plugin::$types = [ 'text-editor', 'button', 'image', 'icon', 'icon-list', 'divider', 'accordion' ];
$safe_heading_fallback = wpae_widget_capability_resolve( 'heading', [ 'role' => 'title', 'content_refs' => [ 'hero_title' ] ] );
$check( ! empty( $safe_heading_fallback['ok'] ) && $safe_heading_fallback['widget_type'] === 'text-editor' && $safe_heading_fallback['downgraded'], 'available compiler-supported heading fallback is selected with probe trace' );
$fallback_ir = wpae_elementor_ir_from_design_plan( $plan, $hero );
$fallback_compiled = wpae_native_elementor_compile( $fallback_ir, $hero, [], [ 'id_seed' => 'heading-fallback' ] );
$fallback_widgets = [];
$fallback_walk = static function ( array $nodes ) use ( &$fallback_walk, &$fallback_widgets ): void {
	foreach ( $nodes as $node ) {
		if ( is_array( $node ) ) {
			$fallback_widgets[] = $node;
			$fallback_walk( (array) ( $node['elements'] ?? [] ) );
		}
	}
};
$fallback_walk( (array) ( $fallback_compiled['elementor_data'] ?? [] ) );
$fallback_heading_node = array_values( array_filter( $fallback_widgets, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'text-editor' && str_contains( (string) ( $node['settings']['editor'] ?? '' ), '<h1>' ) ) );
$check( ! empty( $fallback_compiled['ok'] ) && ! empty( $fallback_heading_node ) && str_contains( $fallback_heading_node[0]['settings']['editor'], 'Соберите сильную страницу' ), 'heading fallback keeps semantic heading markup and exact copy in the native text editor' );
$check( ( $fallback_compiled['report']['downgrades'][0]['from'] ?? '' ) === 'heading' && ( $fallback_compiled['report']['downgrades'][0]['widget_type'] ?? '' ) === 'text-editor' && ( $fallback_compiled['report']['downgrades'][0]['trace'][0]['runtime_result'] ?? '' ) === 'missing', 'compile diagnostics identify source, runtime result and selected fallback' );
\Elementor\Plugin::$types = [ 'button', 'image' ];
$missing_fallback = wpae_widget_capability_resolve( 'heading' );
$check( empty( $missing_fallback['ok'] ) && $missing_fallback['reason'] === 'fallback_unavailable', 'heading fallback is rejected when text-editor is not registered' );
$wpae_test_filters['wpae_widget_capability_registry'] = static function ( array $registry ): array {
	$registry['heading']['fallback_widget'] = 'text-editor';
	$registry['text-editor']['fallback_widget'] = 'heading';
	return $registry;
};
\Elementor\Plugin::$types = [ 'button', 'image' ];
$fallback_cycle = wpae_widget_capability_resolve( 'heading' );
$check( empty( $fallback_cycle['ok'] ) && $fallback_cycle['reason'] === 'fallback_cycle', 'fallback cycle is stopped with a bounded diagnostic' );
$wpae_test_filters = [ 'wpae_widget_capability_registry' => static function ( array $registry ): array {
	$registry['button']['fallback_widget'] = 'text-editor';
	$registry['image']['fallback_widget'] = 'container';
	return $registry;
} ];
\Elementor\Plugin::$types = [ 'text-editor', 'container' ];
$cta_fallback = wpae_widget_capability_resolve( 'button', [ 'role' => 'cta', 'content_refs' => [ 'hero_cta_1' ], 'url' => '#contact' ] );
$media_fallback = wpae_widget_capability_resolve( 'image', [ 'role' => 'media', 'media_refs' => [ 'hero_image' ] ] );
$check( empty( $cta_fallback['ok'] ) && $cta_fallback['reason'] === 'fallback_would_drop_cta_behavior', 'button fallback cannot silently discard CTA URL/behavior' );
$check( empty( $media_fallback['ok'] ) && $media_fallback['reason'] === 'fallback_would_drop_media', 'image fallback cannot silently discard explicit media' );
$wpae_test_filters = [];
\Elementor\Plugin::$types = [ 'text-editor', 'image', 'icon', 'icon-list', 'divider', 'accordion' ];
$blocked_button_ir = [ 'schema' => WPAE_ELEMENTOR_IR_SCHEMA, 'archetype' => 'hero', 'nodes' => [ wpae_elementor_ir_node( 'blocked-cta', 'cta', 'button', [ 'hero_cta_1' ] ) ] ];
$blocked_button_compile = wpae_native_elementor_compile( $blocked_button_ir, $hero, [], [ 'id_seed' => 'blocked-cta' ] );
$check( empty( $blocked_button_compile['ok'] ) && empty( $blocked_button_compile['elementor_data'] ), 'unresolved component stops compilation before the caller can write' );
$check( ( $blocked_button_compile['validation']['capabilities']['failures'][0]['from'] ?? '' ) === 'button' && ( $blocked_button_compile['validation']['capabilities']['failures'][0]['runtime_result'] ?? '' ) === 'missing' && ( $blocked_button_compile['validation']['capabilities']['failures'][0]['reason'] ?? '' ) === 'no_cta_preserving_fallback', 'pre-write validation explains the refused widget, runtime probe and CTA-preserving reason' );
\Elementor\Plugin::$types = [ 'heading', 'text-editor', 'button', 'image', 'icon', 'icon-list', 'divider', 'accordion' ];
$reference = wpae_reference_set_normalize( [ 'asset_id' => 'hero-image', 'source_url' => 'https://example.com/hero.jpg', 'role' => 'hero', 'focal_point' => [ 'x' => 2, 'y' => -1 ], 'alt' => 'Hero' ] );
$check( wpae_reference_set_validate( [ $reference ] )['ok'] && (float) $reference['focal_point']['x'] === 1.0 && (float) $reference['focal_point']['y'] === 0.0, 'ReferenceSet metadata validates and clamps focal point' );

$operation_a = wpae_design_operation_create( [ 'operation_id' => 'op-contract', 'idempotency_key' => 'same-key', 'post_id' => 5214, 'current_state' => 'planned' ] );
$operation_b = wpae_design_operation_create( [ 'operation_id' => 'op-other', 'idempotency_key' => 'same-key', 'post_id' => 5214, 'current_state' => 'planned' ] );
$check( $operation_a['operation_id'] === $operation_b['operation_id'] && ! empty( $operation_b['reconciled'] ), 'operation idempotency prevents duplicate records' );
$check( wpae_design_operation_update( 'op-contract', [ 'current_state' => 'validated' ] )['current_state'] === 'planned', 'invalid state transition is rejected' );
$check( wpae_design_operation_update( 'op-contract', [ 'current_state' => 'generated' ] )['current_state'] === 'generated', 'operation state transition planned to generated works' );
$check( wpae_design_operation_update( 'op-contract', [ 'current_state' => 'normalized' ] )['current_state'] === 'normalized', 'operation state transition generated to normalized works' );
$check( wpae_design_operation_update( 'op-contract', [ 'current_state' => 'validated' ] )['current_state'] === 'validated', 'operation state transition normalized to validated works' );
$mobile_layout = $layout['breakpoints'][3];
$check( $mobile_layout['layout_axis'] === 'column', 'mobile axis is column' );
$check( (float) $mobile_layout['basis_percent']['copy_group'] === 100.0, 'mobile basis percentage is 100' );
$check( (float) $mobile_layout['used_width'] === (float) $mobile_layout['container_width'], 'mobile stack uses cross-axis width instead of desktop percentage basis' );
$independent_key = wpae_design_operation_idempotency_key( 5214, 'same-brief', 'page', 'design', 'new-user-insert' );
$retry_key = wpae_design_operation_idempotency_key( 5214, 'same-brief', 'page', 'design', 'retry-of-insert' );
$independent_a = wpae_design_operation_create( [ 'operation_id' => 'op-independent-a', 'idempotency_key' => $independent_key, 'operation_identity' => 'new-user-insert', 'post_id' => 5214, 'current_state' => 'validated' ] );
$independent_b = wpae_design_operation_create( [ 'operation_id' => 'op-independent-b', 'idempotency_key' => $retry_key, 'operation_identity' => 'retry-of-insert', 'post_id' => 5214, 'current_state' => 'validated' ] );
$check( $independent_a['operation_id'] !== $independent_b['operation_id'], 'same brief with a new operation identity creates an independent insertion' );
$check( wpae_design_operation_reconcile( 'op-independent-a', [ 'ok' => true, 'state' => 'rendered', 'server_verified' => false ] )['current_state'] === 'written', 'browser rendered claim cannot bypass server verification' );
$check( wpae_design_operation_reconcile( 'op-independent-a', [ 'ok' => true, 'state' => 'unknown' ] )['current_state'] === 'written', 'stale unknown readback cannot degrade written operation' );
$review_path = wpae_design_operation_transition_path( 'written', 'reviewed' );
$check( $review_path === [ 'rendered', 'reviewed' ], 'reconcile traverses written to reviewed through rendered' );
$review_operation = wpae_design_operation_create( [ 'operation_id' => 'op-review-path', 'idempotency_key' => 'review-path-key', 'post_id' => 5214, 'operation_identity' => 'review-path', 'current_state' => 'written', 'saved_hash' => 'saved-before' ] );
$reviewed_operation = wpae_design_operation_reconcile( 'op-review-path', [ 'ok' => true, 'state' => 'reviewed', 'server_verified' => true, 'saved_hash' => 'saved-before', 'rendered_html_hash' => 'rendered-hash', 'vision_report_id' => 'vr-review-path', 'evidence_source' => 'preview', 'evidence_hash' => 'evidence-review-path' ] );
$check( $reviewed_operation['current_state'] === 'reviewed' && $reviewed_operation['rendered_html_hash'] === 'rendered-hash' && $reviewed_operation['vision_report_id'] === 'vr-review-path', 'review reconcile applies the complete durable path atomically' );
$reviewed_revision = (int) $reviewed_operation['revision'];
$reviewed_again = wpae_design_operation_reconcile( 'op-review-path', [ 'ok' => true, 'state' => 'reviewed', 'server_verified' => true, 'saved_hash' => 'saved-before', 'rendered_html_hash' => 'rendered-hash', 'vision_report_id' => 'vr-review-path', 'evidence_source' => 'preview', 'evidence_hash' => 'evidence-review-path' ] );
$check( (int) $reviewed_again['revision'] === $reviewed_revision && $reviewed_again['current_state'] === 'reviewed', 'repeated reconcile acknowledgement is idempotent' );
$scope_operation = [ 'operation_id' => 'op-scope', 'operation_identity' => 'scope-identity', 'post_id' => 5214, 'revision' => 3, 'saved_hash' => 'scope-saved', 'target_fingerprint' => 'scope-fingerprint', 'root_ids' => [ 'scope-root' ] ];
$scope_report = [ 'post_id' => 5214, 'source' => 'provider', 'render_context' => [ 'operation_id' => 'op-scope', 'operation_identity' => 'scope-identity', 'operation_revision' => 3, 'operation_saved_hash' => 'scope-saved', 'operation_target_fingerprint' => 'scope-fingerprint', 'operation_root_ids' => [ 'scope-root' ] ] ];
$check( wpae_design_operation_report_scope_matches( $scope_operation, $scope_report, 5214, 3, [ 'scope-root' ] ), 'Vision report scope binds to operation, revision, root, saved hash and fingerprint' );
$scope_report['render_context']['operation_revision'] = 2;
$check( ! wpae_design_operation_report_scope_matches( $scope_operation, $scope_report, 5214, 3, [ 'scope-root' ] ), 'stale Vision report revision is rejected by the shared scope guard' );
$rollback_operation = wpae_design_operation_create( [ 'operation_id' => 'op-rollback', 'idempotency_key' => 'rollback-key', 'post_id' => 5214, 'operation_identity' => 'rollback-identity', 'current_state' => 'written', 'revision' => 1, 'root_ids' => [ 'root-rollback' ] ] );
$rollback_revision = (int) $rollback_operation['revision'];
$rolled_back = wpae_design_operation_mark_rollback( 'op-rollback', 'rollback-identity', $rollback_revision, 'snapshot-rollback', 'vision_rejected', 'vision-evidence' );
$check( is_array( $rolled_back ) && $rolled_back['current_state'] === 'failed' && $rolled_back['last_error'] === 'rollback_vision_rejected', 'vision rollback records a scoped failed operation' );
$check( wpae_design_operation_mark_rollback( 'op-rollback', 'wrong-identity', $rollback_revision, 'snapshot-other', 'vision_rejected' ) === null, 'stale rollback identity cannot change the ledger' );
$rollback_repeat = wpae_design_operation_mark_rollback( 'op-rollback', 'rollback-identity', (int) $rolled_back['revision'], 'snapshot-rollback', 'vision_rejected', 'vision-evidence' );
$check( is_array( $rollback_repeat ) && (int) $rollback_repeat['revision'] === (int) $rolled_back['revision'], 'repeated rollback acknowledgement is idempotent' );
$held_lock = wpae_design_operation_acquire_lock();
$contended = wpae_design_operation_create( [ 'operation_id' => 'op-contended', 'idempotency_key' => 'contended-key', 'post_id' => 5214 ] );
$check( $held_lock !== null && ! empty( $contended['lock_conflict'] ), 'concurrent operation capture is rejected by the atomic option lock' );
wpae_design_operation_release_lock( $held_lock );
$saved_tree = [ [ 'id' => 'kept-root', 'elType' => 'container' ] ];
$current_target = [ 'post_id' => 5214, 'root_ids' => [ 'kept-root' ], 'saved_hash' => hash( 'sha256', wp_json_encode( $saved_tree ) ) ];
$target_status = wpae_design_operation_target_status( $current_target, 5214, $saved_tree );
$check( ! empty( $target_status['reviewable'] ) && $target_status['status'] === 'current', 'current saved target is eligible for capture' );
$check( $target_status['expected_saved_hash'] === $target_status['current_saved_hash'], 'current target status reports matching expected and readback hashes' );
$stale_status = wpae_design_operation_target_status( [ 'post_id' => 5214, 'root_ids' => [ 'missing-root' ], 'saved_hash' => 'old-hash' ], 5214, $saved_tree );
$check( empty( $stale_status['reviewable'] ) && $stale_status['reason'] === 'root_missing' && $stale_status['class'] === 'unknown_target_change', 'missing pending root is rejected before capture without claiming rollback' );
$changed_status = wpae_design_operation_target_status( [ 'post_id' => 5214, 'root_ids' => [ 'kept-root' ], 'saved_hash' => 'old-hash' ], 5214, $saved_tree );
$check( empty( $changed_status['reviewable'] ) && $changed_status['reason'] === 'saved_hash_mismatch', 'changed saved target is rejected before capture' );
$diagnostic_tree = [ [ 'id' => 'diagnostic-root', 'elType' => 'container', 'settings' => [ '_css_classes' => 'wpae-generated-root' ] ] ];
$diagnostic_hash = hash( 'sha256', wp_json_encode( $diagnostic_tree ) );
$diagnostic_operation = wpae_design_operation_create( [
	'operation_id' => 'op-target-diagnostic',
	'operation_identity' => 'target-diagnostic-identity',
	'idempotency_key' => 'target-diagnostic-key',
	'post_id' => 5214,
	'root_ids' => [ 'diagnostic-root' ],
	'saved_hash' => $diagnostic_hash,
	'current_state' => 'written',
] );
$diagnostic_store_before = wpae_design_operation_store();
$wpae_test_readback[5214] = $diagnostic_tree;
$target_diagnostic = wpae_design_operation_target_diagnostics( 5214, 'diagnostic-root', $diagnostic_tree );
$check( count( $target_diagnostic['operations'] ) === 1 && $target_diagnostic['operations'][0]['operation_id'] === 'op-target-diagnostic' && $target_diagnostic['operations'][0]['operation_identity'] === 'target-diagnostic-identity' && $target_diagnostic['operations'][0]['revision'] === $diagnostic_operation['revision'] && ! empty( $target_diagnostic['operations'][0]['target_status']['reviewable'] ), 'read-only target diagnostic binds operation identity, revision, root and saved readback' );
$check( $diagnostic_store_before === wpae_design_operation_store(), 'read-only target diagnostic leaves the durable ledger unchanged' );
$operation_diagnostic = wpae_design_operation_lookup_diagnostics( 5214, 'op-target-diagnostic', $diagnostic_tree );
$check( ! empty( $operation_diagnostic['found'] ) && $operation_diagnostic['current_state'] === 'written' && $operation_diagnostic['revision'] === $diagnostic_operation['revision'] && $operation_diagnostic['root_ids'] === [ 'diagnostic-root' ] && ! empty( $operation_diagnostic['target_status']['reviewable'] ), 'operation diagnostic reads one exact post-scoped operation against saved Elementor data' );
$post_operation_count = count( array_filter( $diagnostic_store_before, static fn( $item ): bool => is_array( $item ) && (int) ( $item['post_id'] ?? 0 ) === 5214 ) );
$check( $operation_diagnostic['store_retention_limit'] === 100 && $operation_diagnostic['post_operation_count'] === $post_operation_count, 'operation diagnostic reports post-scoped bounded ledger counts without implying why older records are absent' );
$check( $diagnostic_store_before === wpae_design_operation_store(), 'operation-ID lookup leaves every durable ledger record unchanged' );
$unknown_operation = wpae_design_operation_lookup_diagnostics( 5214, 'op-unknown', $diagnostic_tree );
$check( empty( $unknown_operation['found'] ) && $unknown_operation['reason'] === 'operation_not_found_for_post', 'unknown operation returns an explicit post-scoped absence result' );
$changed_diagnostic_tree = $diagnostic_tree;
$changed_diagnostic_tree[0]['settings']['title'] = 'manual edit';
$changed_operation_diagnostic = wpae_design_operation_lookup_diagnostics( 5214, 'op-target-diagnostic', $changed_diagnostic_tree );
$check( ! empty( $changed_operation_diagnostic['found'] ) && $changed_operation_diagnostic['target_status']['reason'] === 'saved_hash_mismatch', 'operation lookup distinguishes a manually changed root from a missing operation' );
$missing_operation_root = wpae_design_operation_lookup_diagnostics( 5214, 'op-target-diagnostic', [ [ 'id' => 'another-root' ] ] );
$check( $missing_operation_root['target_status']['reason'] === 'root_missing', 'operation lookup distinguishes a missing owned root from a changed saved root' );
$check( wpae_design_operation_target_diagnostics( 5214, 'foreign-root', $diagnostic_tree )['operations'] === [], 'target diagnostic never returns operations for a different root' );
$wpae_test_can_edit_post = false;
$diagnostic_forbidden = wpae_design_operation_target_diagnostics_endpoint( new WP_REST_Request( [ 'post_id' => 5214, 'root_id' => 'diagnostic-root' ] ) );
$check( $diagnostic_forbidden instanceof WP_Error && $diagnostic_forbidden->get_error_code() === 'wpae_operation_target_forbidden' && ( $diagnostic_forbidden->get_error_data()['status'] ?? 0 ) === 403, 'target diagnostic denies users without edit_post capability' );
$operation_diagnostic_forbidden = wpae_design_operation_target_diagnostics_endpoint( new WP_REST_Request( [ 'post_id' => 5214, 'operation_id' => 'op-target-diagnostic' ] ) );
$check( $operation_diagnostic_forbidden instanceof WP_Error && $operation_diagnostic_forbidden->get_error_code() === 'wpae_operation_target_forbidden', 'operation-ID diagnostics retain the edit_post guard' );
$diagnostic_foreign_operation = wpae_design_operation_create( [ 'operation_id' => 'op-foreign-post', 'idempotency_key' => 'foreign-post-key', 'post_id' => 5215, 'root_ids' => [ 'diagnostic-root' ], 'current_state' => 'planned' ] );
$check( empty( wpae_design_operation_lookup_diagnostics( 5214, $diagnostic_foreign_operation['operation_id'], $diagnostic_tree )['found'] ), 'operation lookup does not disclose an operation owned by another post' );
$wpae_test_can_edit_post = true;
$diagnostic_by_id_endpoint = wpae_design_operation_target_diagnostics_endpoint( new WP_REST_Request( [ 'post_id' => 5214, 'operation_id' => 'op-target-diagnostic' ] ) );
$check( ! empty( $diagnostic_by_id_endpoint['found'] ), 'protected read-only endpoint accepts a concrete operation ID without requiring a root guess' );
$unknown_by_id_endpoint = wpae_design_operation_target_diagnostics_endpoint( new WP_REST_Request( [ 'post_id' => 5214, 'operation_id' => 'op-not-retained' ] ) );
$check( empty( $unknown_by_id_endpoint['found'] ) && $unknown_by_id_endpoint['reason'] === 'operation_not_found_for_post', 'protected endpoint returns an explicit scoped result for a missing operation ID' );
$invalid_diagnostic_scope = wpae_design_operation_target_diagnostics_endpoint( new WP_REST_Request( [ 'post_id' => 5214, 'operation_id' => 'op-target-diagnostic', 'root_id' => 'diagnostic-root' ] ) );
$check( $invalid_diagnostic_scope instanceof WP_Error && $invalid_diagnostic_scope->get_error_data()['status'] === 400, 'diagnostic endpoint rejects ambiguous operation and root scope' );
$wpae_test_fingerprints[5214] = 'current-target-fingerprint';
$changed_hashes = wpae_design_operation_target_status( [ 'post_id' => 5214, 'root_ids' => [ 'kept-root' ], 'saved_hash' => 'old-hash', 'target_fingerprint' => 'expected-target-fingerprint' ], 5214, $saved_tree );
$check( $changed_hashes['reason'] === 'saved_hash_mismatch' && $changed_hashes['expected_fingerprint'] === 'expected-target-fingerprint' && $changed_hashes['current_fingerprint'] === 'current-target-fingerprint', 'stale target diagnostics preserve expected and current page fingerprints alongside saved hashes' );
$editor_tree = [ [ 'id' => 'cd4da23', 'elType' => 'container', 'settings' => [ '_css_classes' => 'wpae-generated-root' ] ] ];
$editor_hash = hash( 'sha256', wp_json_encode( $editor_tree ) );
$wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ] = [
	[ 'operation_id' => 'wpae-current-root', 'operation_identity' => 'current-root-identity', 'post_id' => 5214, 'revision' => 4, 'current_state' => 'written', 'root_ids' => [ 'cd4da23' ], 'saved_hash' => $editor_hash ],
	[ 'operation_id' => 'wpae-newer-stale', 'operation_identity' => 'stale-identity', 'post_id' => 5214, 'revision' => 5, 'current_state' => 'written', 'root_ids' => [ '1fa90e6' ], 'saved_hash' => 'stale-hash' ],
];
$editor_candidate = wpae_design_operation_editor_candidate( 5214, $editor_tree );
$check( $editor_candidate['operation_id'] === 'wpae-current-root' && ! empty( $editor_candidate['reviewable'] ), 'newer stale operation does not hide an older operation whose exact root and saved hash remain current' );
$check( $editor_candidate['target_status']['expected_saved_hash'] === $editor_candidate['target_status']['current_saved_hash'], 'editor candidate exposes equal saved/readback hashes for review evidence' );
$wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ] = [
	[ 'operation_id' => 'wpae-current-root-stale', 'operation_identity' => 'current-root-identity', 'post_id' => 5214, 'revision' => 6, 'current_state' => 'written', 'root_ids' => [ 'cd4da23' ], 'saved_hash' => 'old-page-hash', 'target_fingerprint' => 'expected-page-fingerprint' ],
	end( $wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ] ),
];
$stale_editor_candidate = wpae_design_operation_editor_candidate( 5214, $editor_tree );
$check( $stale_editor_candidate === null, 'stale-only ledger entries never become the active editor pending operation' );
$stale_status = wpae_design_operation_target_status( $wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ][0], 5214, $editor_tree );
$check( $stale_status['reason'] === 'saved_hash_mismatch' && $stale_status['expected_fingerprint'] === 'expected-page-fingerprint' && $stale_status['current_fingerprint'] === 'current-target-fingerprint', 'stale present-root diagnostics still expose both fingerprints without exposing the operation as reviewable' );
$mapped_target = [
	'operation_id' => 'wpae-root-operation',
	'operation_identity' => 'root-operation-identity',
	'post_id' => 5214,
	'revision' => 7,
	'current_state' => 'written',
	'root_ids' => [ 'cd4da23' ],
	'saved_hash' => $editor_hash,
	'target_fingerprint' => 'current-target-fingerprint',
];
$wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ] = [ array_merge( $mapped_target, [ 'current_state' => 'revised' ] ) ];
$check( wpae_design_operation_editor_candidate( 5214, $editor_tree ) === null, 'a current-looking revised history record cannot be surfaced as the active pending editor operation' );
$wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ] = [
	$mapped_target,
	[ 'operation_id' => 'wpae-stale-bootstrap', 'operation_identity' => 'stale-bootstrap-identity', 'post_id' => 5214, 'revision' => 4, 'current_state' => 'written', 'root_ids' => [ '3271f43' ], 'saved_hash' => 'stale-bootstrap-hash' ],
	[ 'operation_id' => 'wpae-foreign-post-root', 'operation_identity' => 'foreign-post-identity', 'post_id' => 5215, 'revision' => 8, 'current_state' => 'written', 'root_ids' => [ 'cd4da23' ], 'saved_hash' => $editor_hash, 'target_fingerprint' => 'current-target-fingerprint' ],
];
$target_operations = wpae_design_operation_editor_targets( 5214, $editor_tree );
$check( array_keys( $target_operations ) === [ 'cd4da23' ] && $target_operations['cd4da23']['operation_id'] === 'wpae-root-operation' && $target_operations['cd4da23']['revision'] === 7 && ! empty( $target_operations['cd4da23']['reviewable'] ), 'selected generated root resolves only its exact post-scoped owner after current saved hash and fingerprint checks' );
$wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ][] = array_merge( $mapped_target, [ 'operation_id' => 'wpae-ambiguous-root-owner', 'operation_identity' => 'ambiguous-root-identity' ] );
$check( wpae_design_operation_editor_targets( 5214, $editor_tree ) === [], 'multiple current ledger records claiming one root are rejected instead of choosing by recency' );
$stale_mapped_target = array_merge( $mapped_target, [ 'operation_id' => 'wpae-stale-fingerprint-root', 'target_fingerprint' => 'older-target-fingerprint' ] );
$wpae_test_options[ WPAE_DESIGN_OPERATION_OPTION ] = [ $stale_mapped_target ];
$check( wpae_design_operation_editor_targets( 5214, $editor_tree ) === [], 'root ownership alone cannot resolve an operation with a stale page fingerprint' );
$owned_tree = [ [ 'id' => 'owned-root', 'elType' => 'container', 'settings' => [ '_css_classes' => 'wpae-generated-root wpae-generated-hero' ], 'elements' => [] ], [ 'id' => 'neighbor-root', 'elType' => 'container', 'settings' => [], 'elements' => [] ] ];
$owned_operation = [ 'operation_id' => 'op-owned', 'operation_identity' => 'owned-identity', 'post_id' => 5214, 'revision' => 4, 'current_state' => 'written', 'root_ids' => [ 'owned-root' ], 'saved_hash' => hash( 'sha256', wp_json_encode( $owned_tree ) ) ];
$owned_guard = wpae_design_operation_replacement_target( $owned_operation, 5214, 'owned-identity', 4, [ 'owned-root' ], $owned_tree );
$check( ! empty( $owned_guard['ok'] ) && $owned_guard['root_ids'] === [ 'owned-root' ], 'replacement accepts the exact current plugin-owned root and saved snapshot' );
$check( empty( wpae_design_operation_replacement_target( $owned_operation, 5214, 'owned-identity', 3, [ 'owned-root' ], $owned_tree )['ok'] ), 'replacement rejects a stale operation revision' );
$check( empty( wpae_design_operation_replacement_target( $owned_operation, 5214, 'owned-identity', 4, [ 'neighbor-root' ], $owned_tree )['ok'] ), 'replacement rejects a neighboring non-owned root' );
$changed_owned_tree = $owned_tree;
$changed_owned_tree[1]['settings']['title'] = 'user edit';
$check( empty( wpae_design_operation_replacement_target( $owned_operation, 5214, 'owned-identity', 4, [ 'owned-root' ], $changed_owned_tree )['ok'] ), 'replacement blocks when any saved page content changed after generation' );
$repair_operation = wpae_design_operation_create( [ 'operation_id' => 'op-repair-transition', 'idempotency_key' => 'repair-transition-key', 'post_id' => 5214, 'current_state' => 'written' ] );
$check( wpae_design_operation_update( 'op-repair-transition', [ 'current_state' => 'revised' ] )['current_state'] === 'revised', 'successful owned-root replacement can mark its former operation revised' );
$route = wpae_llm_route_policy( 'elementor_write', 'openrouter', 'openrouter/free' );
$check( $route['critical_write'] && $route['requires_structured_output'] && $route['retry_budget'] === 1 && ! $route['fallback_allowed'], 'critical route policy is bounded' );
$matrix = [
	[ 'off', 'off', 'provider', 1, 1, false ],
	[ 'off', 'active', 'edde', 0, 1, false ],
	[ 'shadow', 'active', 'edde', 0, 1, false ],
	[ 'active', 'active', 'pipeline', 0, 1, false ],
	[ 'active', 'active', 'library_agent', 1, 1, true ],
];
foreach ( $matrix as $entry ) {
	$decision = wpae_design_generation_route( $entry[0], $entry[1], true, true, $entry[5] );
	$check( $decision['action_path'] === $entry[2] && $decision['provider_calls'] === $entry[3] && $decision['writes'] === $entry[4], 'feature flag route matrix ' . $entry[0] . '/' . $entry[1] );
}

$walk_elements = static function ( array $nodes ) use ( &$walk_elements ): array {
	$all = [];
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$all[] = $node;
		$all = array_merge( $all, $walk_elements( (array) ( $node['elements'] ?? [] ) ) );
	}
	return $all;
};
$compile_prompt = static function ( string $prompt, string $seed ) use ( $walk_elements ): array {
	$brief = wpae_brief_ir_parse( $prompt );
	$plan = wpae_design_plan_from_brief( $brief );
	$validation = wpae_design_plan_validate( $plan, $brief );
	$ir = wpae_elementor_ir_from_design_plan( $plan, $brief );
	$compiled = wpae_native_elementor_compile( $ir, $brief, [ 'palette' => [ 'paper' => '#f6f0e6', 'surface' => '#ffffff', 'ink' => '#111827', 'muted' => '#4b5563', 'accent' => '#4460ec', 'border' => '#d1d5db' ] ], [ 'id_seed' => $seed ] );
	return [ $brief, $plan, $validation, $compiled, $walk_elements( (array) ( $compiled['elementor_data'] ?? [] ) ) ];
};
$hero_image_url = 'https://images.unsplash.com/photo-1774516534068-77422d9226e6?auto=format&fit=crop&w=1800&q=85';
$hero_image_prompt = "Hero\nEyebrow: «АРХИТЕКТУРА»\nЗаголовок: «Пространство для идей, длинный заголовок для проверки переноса»\nОписание: «Опишите задачу и получите понятный первый шаг.»\nКнопка: «Начать проект» ссылка #contact\nКнопка 2: «Смотреть проекты» ссылка #projects\nТекст 40%, визуальная часть 60%. Фото слева. Изображение: {$hero_image_url}\nAlt: «Современный интерьер студии с панорамным окном и видом на природу»\nLicense: «Unsplash License»\nPhoto by: «Neon Wang»";
[ $hero_photo_brief, $hero_photo_plan, $hero_photo_validation, $hero_photo_compiled, $hero_photo_nodes ] = $compile_prompt( $hero_image_prompt, 'hero-unsplash-regression' );
$hero_photo_ref = $hero_photo_brief['media_references'][0] ?? [];
$hero_photo_widget = array_values( array_filter( $hero_photo_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) )[0] ?? [];
$hero_photo_root = $hero_photo_compiled['elementor_data'][0] ?? [];
$hero_photo_buttons = array_values( array_filter( $hero_photo_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) );
$check( $hero_photo_brief['intent']['archetype'] === 'hero' && count( $hero_photo_brief['media_references'] ) === 1 && $hero_photo_ref['alt'] === 'Современный интерьер студии с панорамным окном и видом на природу' && $hero_photo_ref['license'] === 'Unsplash License' && $hero_photo_ref['attribution'] === 'Neon Wang' && ! empty( $hero_photo_ref['allowed_reuse'] ), 'hero BriefIR keeps the real free-license image, meaningful alt, attribution and provenance' );
$check( $hero_photo_validation['ok'] && ! empty( $hero_photo_compiled['ok'] ) && $hero_photo_widget['settings']['image']['url'] === $hero_image_url && $hero_photo_widget['settings']['image']['alt'] === $hero_photo_ref['alt'], 'licensed hero photo compiles as an editable native Image with exact source and alt text' );
$check( ( $hero_photo_plan['sections'][0]['composition'] ?? '' ) === 'split_40_60' && ( $hero_photo_plan['sections'][0]['media_side'] ?? '' ) === 'left' && ( $hero_photo_root['elements'][0]['settings']['width']['size'] ?? 0 ) === 60.0 && ( $hero_photo_root['elements'][1]['settings']['width']['size'] ?? 0 ) === 40.0 && ( $hero_photo_root['settings']['flex_direction_mobile'] ?? '' ) === 'column-reverse', 'hero photo left uses 60% media and 40% copy widths and keeps copy first on mobile' );
$check( array_column( array_map( static fn( array $node ): array => [ 'text' => $node['settings']['text'] ?? '', 'url' => $node['settings']['link']['url'] ?? '' ], $hero_photo_buttons ), 'text' ) === [ 'Начать проект', 'Смотреть проекты' ] && array_column( array_map( static fn( array $node ): array => [ 'text' => $node['settings']['text'] ?? '', 'url' => $node['settings']['link']['url'] ?? '' ], $hero_photo_buttons ), 'url' ) === [ '#contact', '#projects' ], 'photo hero keeps two exact CTA labels and their separate destinations' );
$hero_ru_metadata_prompt = str_replace( [ 'Alt:', 'License:', 'Photo by:' ], [ 'Альт текст:', 'Лицензия:', 'Автор фото:' ], $hero_image_prompt );
[ $hero_ru_brief, $hero_ru_plan, $hero_ru_validation, $hero_ru_compiled ] = $compile_prompt( $hero_ru_metadata_prompt, 'hero-russian-media-metadata' );
$hero_ru_ref = $hero_ru_brief['media_references'][0] ?? [];
$check( $hero_ru_validation['ok'] && ! empty( $hero_ru_compiled['ok'] ) && count( $hero_ru_brief['content'] ?? [] ) === count( $hero_photo_brief['content'] ?? [] ), 'Russian quoted media metadata is excluded from block copy and compiles without unbound text' );
$check( ( $hero_ru_ref['alt'] ?? '' ) === $hero_photo_ref['alt'] && ( $hero_ru_ref['license'] ?? '' ) === $hero_photo_ref['license'] && ( $hero_ru_ref['attribution'] ?? '' ) === $hero_photo_ref['attribution'] && ! empty( $hero_ru_ref['allowed_reuse'] ), 'Russian metadata aliases retain exact alt license attribution and reuse policy' );
$architecture_hero_prompt = 'Hero архитектурной студии. Заголовок: «Пространство для идей». Описание: «Проектируем интерьеры для жизни.»';
[ $architecture_hero_brief, $architecture_hero_plan, $architecture_hero_validation, $architecture_hero_compiled, $architecture_hero_nodes ] = $compile_prompt( $architecture_hero_prompt, 'architecture-default-hero-media' );
$architecture_hero_image = array_values( array_filter( $architecture_hero_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) )[0] ?? [];
$architecture_hero_ref = array_values( array_filter( (array) ( $architecture_hero_plan['media_references'] ?? [] ), static fn( array $media ): bool => ( $media['provenance']['source'] ?? '' ) === 'plugin_default' ) )[0] ?? [];
$check( $architecture_hero_brief['intent']['archetype'] === 'hero' && $architecture_hero_plan['media_intent'] === 'unspecified' && $architecture_hero_validation['ok'] && ! empty( $architecture_hero_compiled['ok'] ), 'contextual architecture hero selects an optional plugin photo without changing user media intent' );
$check( ( $architecture_hero_plan['sections'][0]['composition'] ?? '' ) === 'split_60_40' && ( $architecture_hero_image['widgetType'] ?? '' ) === 'image' && ( $architecture_hero_image['settings']['image']['url'] ?? '' ) === ( $architecture_hero_ref['source_url'] ?? '' ) && ( $architecture_hero_image['settings']['image']['alt'] ?? '' ) !== '' && ! empty( $architecture_hero_ref['allowed_reuse'] ) && ( $architecture_hero_ref['license'] ?? '' ) === 'Unsplash License', 'contextual hero uses a real, licensed Unsplash asset in a native Image widget with alt text' );
$architecture_no_photo_brief = wpae_brief_ir_parse( 'Hero архитектурной студии без фото. Заголовок: «Пространство для идей».' );
$architecture_no_photo_plan = wpae_design_plan_from_brief( $architecture_no_photo_brief );
$architecture_no_photo_ir = wpae_elementor_ir_from_design_plan( $architecture_no_photo_plan, $architecture_no_photo_brief );
$architecture_no_photo_compiled = wpae_elementor_ir_compile( $architecture_no_photo_ir, $architecture_no_photo_brief, [], [ 'id_seed' => 'architecture-no-photo' ] );
$check( $architecture_no_photo_plan['media_intent'] === 'forbidden' && empty( array_filter( $architecture_no_photo_plan['sections'][0]['children'] ?? [], static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'media' ) ) && empty( array_filter( $walk_elements( $architecture_no_photo_compiled['elementor_data'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) ), 'explicit no-photo instruction suppresses even a contextually relevant hero default' );

 $service_content_for = static function ( array $card ) use ( &$service_content_for ): array {
	foreach ( (array) ( $card['elements'] ?? [] ) as $child ) {
		if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'container' ) { continue; }
		$children = (array) ( $child['elements'] ?? [] );
		if ( ( $children[0]['widgetType'] ?? '' ) === 'heading' && ( $children[1]['widgetType'] ?? '' ) === 'text-editor' ) { return $child; }
		$nested = $service_content_for( $child );
		if ( $nested ) { return $nested; }
	}
	return [];
};
$natural_services_prompt = "Услуги:\nСтратегия проекта — Формулируем задачу и согласуем план работ\nАрхитектура и дизайн — Разрабатываем решение под заданный контекст\nСопровождение — Проверяем соответствие согласованному проекту";
[ $natural_services_brief, $natural_services_plan, $natural_services_validation, $natural_services_compiled, $natural_services_nodes ] = $compile_prompt( $natural_services_prompt, 'services-natural-pairs' );
$natural_services_cards = (array) ( array_values( array_filter( $natural_services_compiled['elementor_data'][0]['elements'] ?? [], static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-service_cards' ) )[0]['elements'] ?? [] );
$natural_services_copy = array_map( $service_content_for, $natural_services_cards );
$check( $natural_services_brief['intent']['archetype'] === 'services' && count( $natural_services_plan['sections'][0]['children'][0]['items'] ?? [] ) === 3 && $natural_services_validation['ok'] && ! empty( $natural_services_compiled['ok'] ), 'plain multiline Services title/body pairs pass BriefIR, DesignPlan and native compiler without numbered field labels' );
$check( array_map( static fn( array $panel ): array => [ (string) ( $panel['elements'][0]['settings']['title'] ?? '' ), trim( (string) ( $panel['elements'][1]['settings']['editor'] ?? '' ) ) ], $natural_services_copy ) === [ [ 'Стратегия проекта', 'Формулируем задачу и согласуем план работ' ], [ 'Архитектура и дизайн', 'Разрабатываем решение под заданный контекст' ], [ 'Сопровождение', 'Проверяем соответствие согласованному проекту' ] ], 'plain Services pairs preserve exact labels and descriptions in order' );
foreach ( [ 2, 3, 4 ] as $service_count ) {
	$service_prompt = "Блок услуг\nЗаголовок: «Услуги студии»\n";
	for ( $service_index = 1; $service_index <= $service_count; $service_index++ ) {
		$service_description = $service_index === 2 ? str_repeat( 'Длинное описание услуги сохраняется целиком. ', 5 ) : 'Краткое описание услуги.';
		$service_prompt .= "Услуга {$service_index} — название: «Услуга {$service_index}»\nУслуга {$service_index} — описание: «{$service_description}»\n";
	}
	$service_prompt .= "Услуга 1 — ссылка: «Подробнее» ссылка #service-1";
	[ $service_brief, $service_plan, $service_validation, $service_compiled, $service_nodes ] = $compile_prompt( $service_prompt, 'services-' . $service_count . '-regression' );
	$service_group = array_values( array_filter( $service_compiled['elementor_data'][0]['elements'] ?? [], static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-service_cards' ) )[0] ?? [];
	$service_cards = (array) ( $service_group['elements'] ?? [] );
	$service_copy_panels = array_map( $service_content_for, $service_cards );
	$service_ids = array_column( $service_nodes, 'id' );
	$service_image_cards = array_values( array_filter( $service_cards, static fn( array $card ): bool => ( $card['elements'][0]['elements'][0]['widgetType'] ?? '' ) === 'image' ) );
	$service_default_refs = array_values( array_filter( (array) ( $service_plan['media_references'] ?? [] ), static fn( array $media ): bool => ( $media['provenance']['source'] ?? '' ) === 'plugin_default' ) );
	$service_cta = array_values( array_filter( $service_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) )[0] ?? [];
	$check( $service_brief['intent']['archetype'] === 'services' && $service_validation['ok'] && ! empty( $service_compiled['ok'] ) && count( $service_cards ) === $service_count, 'services production functions accept ' . $service_count . ' explicitly grouped cards' );
	$check( count( array_unique( $service_ids ) ) === count( $service_ids ) && ( $service_copy_panels[0]['elements'][0]['settings']['title'] ?? '' ) === 'Услуга 1' && trim( (string) ( $service_copy_panels[1]['elements'][1]['settings']['editor'] ?? '' ) ) === trim( $service_count > 1 ? str_repeat( 'Длинное описание услуги сохраняется целиком. ', 5 ) : '' ), 'services bind exact item copy inside a distinct text panel for count ' . $service_count );
	$service_actions = (array) ( $service_cards[0]['elements'][1] ?? [] );
	$service_card_settings = (array) ( $service_cards[0]['settings'] ?? [] );
	$check( ( $service_group['settings']['background_color'] ?? 'transparent' ) === 'transparent' && ( $service_cards[0]['settings']['background_color'] ?? '' ) === '#ffffff' && ( $service_actions['elType'] ?? '' ) === 'container' && ( $service_actions['elements'][0]['widgetType'] ?? '' ) === 'button' && ( $service_copy_panels[0]['settings']['background_color'] ?? '' ) === '#ffffff', 'legacy Services keeps its existing surface while separating item copy from the optional CTA group' );
	$check( count( $service_image_cards ) === min( $service_count, 3 ) && count( $service_default_refs ) === min( $service_count, 3 ) && array_reduce( $service_default_refs, static fn( bool $ok, array $media ): bool => $ok && ! empty( $media['allowed_reuse'] ) && ( $media['role'] ?? '' ) === 'card_image' && trim( (string) ( $media['alt'] ?? '' ) ) !== '', true ), 'Services use the existing licensed photo set as native image nodes with role, alt and provenance for count ' . $service_count );
	$check( count( array_unique( array_column( $service_default_refs, 'asset_id' ) ) ) === count( $service_default_refs ), 'default service photos have stable distinct asset identities independent of generated Elementor IDs' );
	$check( array_reduce( $service_image_cards, static fn( bool $ok, array $card ): bool => $ok && ( $card['settings']['border_radius']['top'] ?? '' ) === '16' && ( $card['settings']['border_radius_mobile']['top'] ?? '' ) === '16' && ( $card['elements'][0]['elements'][0]['settings']['image_border_radius_mobile']['top'] ?? '' ) === '16' && ( $card['elements'][0]['elements'][0]['settings']['image_border_radius_mobile']['bottom'] ?? '' ) === '0', true ), 'service image/card corners share the 16px default at mobile without forcing a missing override to zero' );
	$check( ( $service_copy_panels[0]['elements'][0]['settings']['header_size'] ?? '' ) === 'h3' && ( $service_copy_panels[0]['elements'][0]['settings']['typography_font_size']['size'] ?? 0 ) === 1.125 && ( $service_copy_panels[0]['elements'][0]['settings']['typography_font_weight'] ?? '' ) === '600' && ( $service_copy_panels[0]['settings']['flex_gap']['size'] ?? 0 ) === 8, 'service title has a stronger semantic level and a compact native heading/body relationship' );
	if ( $service_count >= 3 ) {
		$service_bodies = array_map( static fn( array $panel ): string => trim( (string) ( $panel['elements'][1]['settings']['editor'] ?? '' ) ), $service_copy_panels );
		$check( $service_bodies[0] === $service_bodies[2] && $service_bodies[0] === 'Краткое описание услуги.', 'services retain equal copy as separate item-bound values for count ' . $service_count );
	}
	if ( $service_count === 3 ) {
		[ , , , , $service_reseeded_nodes ] = $compile_prompt( $service_prompt, 'services-distinct-id-seed' );
		$check( empty( array_intersect( $service_ids, array_column( $service_reseeded_nodes, 'id' ) ) ) && ! in_array( '2fc6b48', $service_ids, true ), 'Services composition compiles fresh IDs from the seed and does not depend on historical live root 2fc6b48' );
	}
	if ( $service_count === 4 ) {
		$service_widths = array_map( static fn( array $card ): float => (float) ( $card['settings']['width']['size'] ?? 0 ), $service_cards );
		$check( ( $service_group['settings']['flex_direction'] ?? '' ) === 'row' && count( $service_widths ) === 4 && min( $service_widths ) > 0 && count( array_unique( $service_widths ) ) === 1 && (float) ( $service_cards[0]['settings']['width_mobile']['size'] ?? 0 ) === 100.0 && ( $service_cta['settings']['link']['url'] ?? '' ) === '#service-1', 'four service cards keep equal usable desktop columns, mobile stack and the exact optional CTA URL' );
	}
}

$team_prompt = "Блок команды\nУчастник 1 — имя: «Ай»\nУчастник 1 — должность: «Архитектор»\nУчастник 2 — имя: «Алия Нурланова, руководитель проектного направления»\nУчастник 2 — должность: «Старший архитектор по устойчивому проектированию»\nУчастник 2 — описание: «Ведёт проекты от первого обсуждения до согласованных чертежей.»";
[ $team_brief, $team_plan, $team_validation, $team_compiled, $team_nodes ] = $compile_prompt( $team_prompt, 'team-without-photo-regression' );
$team_group = array_values( array_filter( $team_compiled['elementor_data'][0]['elements'] ?? [], static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-team_cards' ) )[0] ?? [];
$team_cards = (array) ( $team_group['elements'] ?? [] );
$check( $team_brief['intent']['archetype'] === 'team' && $team_validation['ok'] && ! empty( $team_compiled['ok'] ) && count( $team_cards ) === 2 && count( array_filter( $team_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) ) === 0, 'team compiles short/long names and roles without creating optional photo placeholders' );
$check( ( $team_group['settings']['background_color'] ?? 'transparent' ) === 'transparent' && ( $team_cards[1]['settings']['background_color'] ?? '' ) === '#ffffff' && ( $team_cards[1]['settings']['flex_gap']['size'] ?? 0 ) === 0.5, 'team wrapper stays transparent, card surface remains, and content uses a compact native Flex gap' );
$check( ( $team_cards[1]['elements'][0]['elements'][0]['settings']['typography_font_size']['size'] ?? 0 ) === 1.125 && ( $team_cards[1]['elements'][0]['elements'][0]['settings']['typography_font_weight'] ?? '' ) === '600' && trim( (string) ( $team_cards[1]['elements'][0]['elements'][1]['settings']['editor'] ?? '' ) ) === 'Старший архитектор по устойчивому проектированию', 'team name is primary, role secondary, and long text is preserved without a photo reserve' );
$check( ( $team_cards[1]['elements'][0]['elements'][0]['settings']['title'] ?? '' ) === 'Алия Нурланова, руководитель проектного направления' && ( $team_cards[1]['elements'][0]['elements'][1]['settings']['editor'] ?? '' ) === 'Старший архитектор по устойчивому проектированию' && ! empty( $team_cards[1]['elements'][0]['elements'][2]['settings']['editor'] ), 'team fields remain attached to their own source item with long exact copy' );
$check( array_reduce( $team_cards, static fn( bool $ok, array $card ): bool => $ok && count( (array) ( $card['elements'] ?? [] ) ) === 1, true ), 'team cards without Brief actions have a body only and no placeholder footer' );
$team_portrait_url = 'https://images.unsplash.com/photo-1638727295415-286409421143?auto=format&fit=crop&w=800&q=80';
$team_photo_prompt = "Блок команды\nУчастник 1 — имя: «Синтетический персонаж для теста»\nУчастник 1 — должность: «Не реальный сотрудник»\nУчастник 1 — фото: {$team_portrait_url}\nAlt: «Тестовый портрет, не изображающий конкретного сотрудника»\nLicense: «Unsplash License»\nPhoto by: «Brianna Geoghegan»";
[ $team_photo_brief, $team_photo_plan, $team_photo_validation, $team_photo_compiled, $team_photo_nodes ] = $compile_prompt( $team_photo_prompt, 'team-photo-regression' );
$team_photo_ref = $team_photo_brief['media_references'][0] ?? [];
$team_photo_widget = array_values( array_filter( $team_photo_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) )[0] ?? [];
$check( ( $team_photo_ref['role'] ?? '' ) === 'portrait' && ( $team_photo_ref['group_id'] ?? '' ) === 'team_1' && ! empty( $team_photo_ref['allowed_reuse'] ) && $team_photo_ref['attribution'] === 'Brianna Geoghegan' && $team_photo_validation['ok'] && ! empty( $team_photo_compiled['ok'] ), 'team optional portrait keeps its item ID, free-use license and photographer provenance' );
$check( $team_photo_widget['settings']['image']['url'] === $team_portrait_url && $team_photo_widget['settings']['image']['alt'] === 'Тестовый портрет, не изображающий конкретного сотрудника', 'team photo compiles as a native image only for the explicitly photo-bearing test member' );
$unlicensed_team_brief = wpae_brief_ir_parse( "Блок команды\nУчастник 1 — имя: «Тестовый персонаж»\nУчастник 1 — должность: «Не реальный сотрудник»\nУчастник 1 — фото: {$team_portrait_url}\nAlt: «Тестовый портрет»" );
$unlicensed_team_plan = wpae_design_plan_from_brief( $unlicensed_team_brief );
$unlicensed_team_validation = wpae_design_plan_validate( $unlicensed_team_plan, $unlicensed_team_brief );
$check( ! $unlicensed_team_validation['ok'] && in_array( 'media_unsplash_license_unconfirmed_prompt_media_0', $unlicensed_team_validation['errors'], true ), 'new-card media rejects Unsplash assets unless the free-use license is explicitly recorded' );
$service_image_prompt = "Блок услуг\nУслуга 1 — название: «Проектирование интерьеров»\nУслуга 1 — описание: «Планировка и архитектурная концепция пространства.»\nУслуга 1 — изображение: {$hero_image_url}\nAlt: «Современный интерьер с панорамным окном»\nLicense: «Unsplash License»\nPhoto by: «Neon Wang»\nУслуга 2 — название: «Авторский надзор»\nУслуга 2 — описание: «Контроль реализации проекта.»";
[ $service_image_brief, $service_image_plan, $service_image_validation, $service_image_compiled, $service_image_nodes ] = $compile_prompt( $service_image_prompt, 'service-image-regression' );
$service_image_ref = $service_image_brief['media_references'][0] ?? [];
$service_image_widget = array_values( array_filter( $service_image_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) )[0] ?? [];
$service_image_url = (string) ( $service_image_widget['settings']['image']['url'] ?? '' );
$service_image_url_parts = parse_url( $service_image_url );
$service_image_url_query = [];
parse_str( (string) ( $service_image_url_parts['query'] ?? '' ), $service_image_url_query );
$check( ( $service_image_ref['role'] ?? '' ) === 'card_image' && ( $service_image_ref['group_id'] ?? '' ) === 'service_1' && $service_image_validation['ok'] && ! empty( $service_image_compiled['ok'] ), 'service-card media is grouped with its own source item and passes the license gate' );
$check( ( $service_image_url_parts['host'] ?? '' ) === ( parse_url( $hero_image_url, PHP_URL_HOST ) ?: '' ) && ( $service_image_url_parts['path'] ?? '' ) === ( parse_url( $hero_image_url, PHP_URL_PATH ) ?: '' ) && ( $service_image_url_query['fit'] ?? '' ) === 'crop' && (int) ( $service_image_url_query['w'] ?? 0 ) === 1200 && (int) ( $service_image_url_query['h'] ?? 0 ) === 900 && $service_image_widget['settings']['image']['alt'] === 'Современный интерьер с панорамным окном', 'optional service image remains the requested asset, uses a stable 4:3 crop, and retains its alt' );
$service_image_group = array_values( array_filter( $service_image_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-service_cards' ) )[0] ?? [];
$service_image_card = $service_image_group['elements'][0] ?? [];
$service_image_panel = $service_content_for( $service_image_card );
$check( ( $service_image_card['elements'][0]['elements'][0]['widgetType'] ?? '' ) === 'image' && ( $service_image_card['elements'][0]['elements'][1]['elType'] ?? '' ) === 'container' && ( $service_image_panel['elements'][0]['settings']['title'] ?? '' ) === 'Проектирование интерьеров' && ! isset( $service_image_card['settings']['background_image'] ) && ! isset( $service_image_card['settings']['background_overlay_opacity'] ), 'service composition puts the native image above a separate text panel and removes photo overlays' );
$service_explicit_and_default_urls = array_values( array_map( static fn( array $node ): string => (string) ( $node['settings']['image']['url'] ?? '' ), array_filter( $service_image_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) ) );
$check( count( $service_explicit_and_default_urls ) === 2 && ( parse_url( $service_explicit_and_default_urls[0], PHP_URL_PATH ) ?: '' ) === ( parse_url( $hero_image_url, PHP_URL_PATH ) ?: '' ) && ( parse_url( $service_explicit_and_default_urls[1], PHP_URL_HOST ) ?: '' ) === 'images.unsplash.com', 'explicit service media remains first while the next unfilled card receives a separate built-in photo' );
$service_explicit_radius_plan = $service_image_plan;
foreach ( $service_explicit_radius_plan['sections'][0]['children'] as &$service_plan_child ) {
	if ( ( $service_plan_child['role'] ?? '' ) === 'service_cards' ) {
		$service_plan_child['items'][0]['layout_constraints'] = [ 'border_radius' => '12px', 'border_radius_mobile' => '22px' ];
	}
}
unset( $service_plan_child );
$service_explicit_radius_ir = wpae_elementor_ir_from_design_plan( $service_explicit_radius_plan, $service_image_brief );
$service_explicit_radius_result = wpae_elementor_ir_compile( $service_explicit_radius_ir, $service_image_brief, [], [ 'id_seed' => 'services-radius-override' ] );
$service_explicit_radius_card = array_values( array_filter( $walk_elements( $service_explicit_radius_result['elementor_data'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === '' && ( $node['settings']['_css_classes'] ?? '' ) === '' && ( $node['settings']['border_radius']['top'] ?? '' ) === '12' ) )[0] ?? [];
$check( ! empty( $service_explicit_radius_result['ok'] ) && ( $service_explicit_radius_card['settings']['border_radius_mobile']['top'] ?? '' ) === '22' && ( $service_explicit_radius_card['elements'][0]['elements'][0]['settings']['image_border_radius']['top'] ?? '' ) === '12' && ( $service_explicit_radius_card['elements'][0]['elements'][0]['settings']['image_border_radius_mobile']['top'] ?? '' ) === '22', 'an explicit per-card mobile radius override survives the plan, card surface and native image compiler' );
$service_no_photo_brief = wpae_brief_ir_parse( "Блок услуг без изображений\nУслуга 1 — название: «Стратегия»\nУслуга 1 — описание: «План проекта.»\nУслуга 2 — название: «Дизайн»\nУслуга 2 — описание: «Архитектурное решение.»" );
$service_no_photo_plan = wpae_design_plan_from_brief( $service_no_photo_brief );
$check( ( $service_no_photo_plan['media_intent'] ?? '' ) === 'forbidden' && empty( array_filter( (array) ( $service_no_photo_plan['media_references'] ?? [] ), static fn( array $media ): bool => ( $media['provenance']['source'] ?? '' ) === 'plugin_default' ) ), 'explicit no-image service request suppresses plugin defaults' );

$testimonial_long_quote = str_repeat( 'Синтетический тестовый текст отзыва для проверки длинного содержимого. ', 6 );
$testimonial_prompt = "Блок отзывов — синтетические тестовые данные\nОтзыв 1 — текст: «Короткий синтетический отзыв.»\nОтзыв 1 — автор: «Тестовый автор»\nОтзыв 2 — текст: «{$testimonial_long_quote}»\nОтзыв 2 — автор: «Второй тестовый автор»\nОтзыв 2 — компания: «Тестовая компания»";
[ $testimonial_brief, $testimonial_plan, $testimonial_validation, $testimonial_compiled, $testimonial_nodes ] = $compile_prompt( $testimonial_prompt, 'synthetic-testimonials-regression' );
$testimonial_group = array_values( array_filter( $testimonial_compiled['elementor_data'][0]['elements'] ?? [], static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-testimonial_cards' ) )[0] ?? [];
$testimonial_cards = (array) ( $testimonial_group['elements'] ?? [] );
$check( $testimonial_brief['intent']['archetype'] === 'testimonials' && $testimonial_validation['ok'] && ! empty( $testimonial_compiled['ok'] ) && count( $testimonial_cards ) === 2 && count( array_filter( $testimonial_nodes, static fn( array $node ): bool => str_contains( (string) ( $node['widgetType'] ?? '' ), 'star' ) ) ) === 0, 'testimonials compile as labeled synthetic native cards and do not invent a rating widget' );
$check( ( $testimonial_cards[0]['elements'][0]['elements'][0]['settings']['editor'] ?? '' ) === 'Короткий синтетический отзыв.' && trim( (string) ( $testimonial_cards[1]['elements'][0]['elements'][0]['settings']['editor'] ?? '' ) ) === trim( $testimonial_long_quote ) && ( $testimonial_cards[1]['elements'][0]['elements'][2]['settings']['editor'] ?? '' ) === 'Тестовая компания', 'testimonials preserve short/long text and optional company on the matching author card' );
$check( ( $testimonial_group['settings']['background_color'] ?? 'transparent' ) === 'transparent' && ( $testimonial_cards[0]['settings']['background_color'] ?? '' ) === '#ffffff', 'testimonial card group is transparent and the card retains its white surface' );
$check( empty( $testimonial_cards[1]['elements'][1] ) && ( $testimonial_cards[1]['elements'][0]['elements'][0]['settings']['text_color'] ?? '' ) === '#111827' && ( $testimonial_cards[1]['elements'][0]['elements'][1]['settings']['typography_font_weight'] ?? '' ) === '700', 'testimonial without a CTA has no empty footer and keeps quote/author hierarchy' );
$check( empty( array_filter( (array) ( $team_plan['media_references'] ?? [] ), static fn( array $media ): bool => ( $media['provenance']['source'] ?? '' ) === 'plugin_default' ) ) && empty( array_filter( (array) ( $testimonial_plan['media_references'] ?? [] ), static fn( array $media ): bool => ( $media['provenance']['source'] ?? '' ) === 'plugin_default' ) ), 'team and testimonial plans do not invent stock portraits for real people' );
$visual_family_briefs = [
	'process' => wpae_brief_ir_parse( "Процесс\nЭтап 1 — «Заявка»\nЭтап 2 — «Уточнение»" ),
	'pricing' => wpae_brief_ir_parse( "Тарифы\nТариф 1 — название: «Старт»\nТариф 1 — цена: «50 000 ₸»\nТариф 2 — название: «Проект»\nТариф 2 — цена: «150 000 ₸»" ),
	'faq' => wpae_brief_ir_parse( "FAQ\nВопрос: «Как начать?»\nОтвет: «Напишите нам.»" ),
	'cta' => wpae_brief_ir_parse( "CTA\nЗаголовок: «Обсудим проект»\nКнопка: «Связаться» ссылка #contact" ),
];
$check( array_reduce( $visual_family_briefs, static fn( bool $ok, array $brief ): bool => $ok && empty( array_filter( (array) ( wpae_design_plan_from_brief( $brief )['media_references'] ?? [] ), static fn( array $media ): bool => ( $media['provenance']['source'] ?? '' ) === 'plugin_default' ) ), true ), 'process, pricing, FAQ and CTA stay photo-free unless the user provides or requests a meaningful image' );
$check( str_contains( wpae_llm_block_archetype_hint( 'Блок команды' ), 'Не выдумывай портреты' ), 'production team guidance avoids invented portraits' );
$check( str_contains( wpae_llm_block_archetype_hint( 'FAQ' ), 'Не добавляй фото по умолчанию' ), 'production FAQ guidance does not add decorative photos by default' );
$check( str_contains( wpae_llm_block_archetype_hint( "Блок услуг\nУслуга 1 — название: «Проектирование»\nУслуга 1 — описание: «Архитектурные решения.»" ), 'изображение Unsplash на карточку' ), 'production Services guidance retains relevant Unsplash media per card' );

foreach ( [ 1, 2 ] as $cta_count ) {
	$cta_prompt = "Самостоятельный CTA\nЗаголовок: «Начните разговор о проекте с нашей архитектурной студией»\nОписание: «Расскажите о задаче, и команда подскажет следующий шаг.»\nКнопка: «Связаться» ссылка #contact";
	if ( $cta_count === 2 ) {
		$cta_prompt .= "\nВторичная кнопка: «Посмотреть проекты» ссылка #projects";
	}
	[ $cta_brief, $cta_plan, $cta_validation, $cta_compiled, $cta_nodes ] = $compile_prompt( $cta_prompt, 'standalone-cta-' . $cta_count . '-regression' );
	$cta_buttons = array_values( array_filter( $cta_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) );
	$cta_ids = array_column( $cta_nodes, 'id' );
	$check( $cta_brief['intent']['archetype'] === 'cta' && $cta_validation['ok'] && ! empty( $cta_compiled['ok'] ) && count( $cta_buttons ) === $cta_count && count( array_unique( $cta_ids ) ) === count( $cta_ids ), 'standalone CTA uses one exact compiled native scope with ' . $cta_count . ' button(s)' );
	$check( array_column( array_map( static fn( array $node ): array => [ 'text' => $node['settings']['text'] ?? '', 'url' => $node['settings']['link']['url'] ?? '' ], $cta_buttons ), 'url' ) === ( $cta_count === 1 ? [ '#contact' ] : [ '#contact', '#projects' ] ), 'standalone CTA preserves every explicit URL with ' . $cta_count . ' button(s)' );
	if ( $cta_count === 2 ) {
		$cta_root = $cta_compiled['elementor_data'][0] ?? [];
		$cta_copy = array_values( array_filter( $cta_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-cta-copy' ) )[0] ?? [];
		$cta_actions = array_values( array_filter( $cta_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-cta-actions' ) )[0] ?? [];
		$cta_title = array_values( array_filter( $cta_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'heading' && ( $node['settings']['title'] ?? '' ) === 'Начните разговор о проекте с нашей архитектурной студией' ) )[0] ?? [];
		$check( ( $cta_root['settings']['_css_classes'] ?? '' ) === 'wpae-cta-section' && ( $cta_title['settings']['header_size'] ?? '' ) === 'h2' && ( $cta_title['settings']['typography_font_size']['size'] ?? 0 ) === 1.75, 'standalone CTA compiles as a section-level heading, not a hero display heading' );
		$check( (float) ( $cta_copy['settings']['width']['size'] ?? 0 ) === 72.0 && (float) ( $cta_copy['settings']['width_mobile']['size'] ?? 0 ) === 100.0 && ( $cta_actions['settings']['flex_direction'] ?? '' ) === 'row' && ( $cta_actions['settings']['flex_direction_mobile'] ?? '' ) === 'column', 'standalone CTA has constrained copy width and responsive native button grouping' );
		$check( ( $cta_copy['settings']['background_color'] ?? 'transparent' ) === 'transparent' && ( $cta_actions['settings']['background_color'] ?? 'transparent' ) === 'transparent' && (float) ( $cta_actions['settings']['flex_gap']['size'] ?? 0 ) === 0.875, 'CTA copy/action groups remain transparent and button gap is native Flex' );
	}
}
$cta_photo_url = 'https://images.unsplash.com/photo-1774516534141-fd68a4713366?auto=format&fit=crop&fm=jpg&q=80&w=1400';
$cta_photo_prompt = "Самостоятельный CTA\nЗаголовок: «Обсудите следующий шаг проекта»\nОписание: «Опишите задачу, чтобы выбрать подходящий формат разговора.»\nОсновная кнопка: «Связаться», ссылка #contact\nВторичная кнопка: «Посмотреть проекты», ссылка #projects\nИзображение справа: {$cta_photo_url}\nAlt: «Иллюстративное фото современного интерьера с бетоном и деревом»\nЛицензия: Unsplash License\nPhoto by: «Neon Wang»";
[ $cta_photo_brief, $cta_photo_plan, $cta_photo_validation, $cta_photo_compiled, $cta_photo_nodes ] = $compile_prompt( $cta_photo_prompt, 'standalone-cta-photo-regression' );
$cta_photo_root = $cta_photo_compiled['elementor_data'][0] ?? [];
$cta_photo_children = (array) ( $cta_photo_root['elements'] ?? [] );
$cta_photo_copy = $cta_photo_children[0] ?? [];
$cta_photo_media = $cta_photo_children[1] ?? [];
$cta_photo_image = $cta_photo_media['elements'][0] ?? [];
$cta_photo_refs = (array) ( $cta_photo_brief['media_references'] ?? [] );
$check( $cta_photo_brief['intent']['archetype'] === 'cta' && ( $cta_photo_plan['sections'][0]['composition'] ?? '' ) === 'split_60_40' && ( $cta_photo_plan['sections'][0]['media_side'] ?? '' ) === 'right', 'CTA image selects a deterministic right-side 60/40 composition' );
$check( $cta_photo_validation['ok'] && ! empty( $cta_photo_compiled['ok'] ) && ( $cta_photo_root['settings']['flex_direction'] ?? '' ) === 'row' && ( $cta_photo_root['settings']['flex_direction_tablet'] ?? '' ) === 'column' && ( $cta_photo_root['settings']['flex_direction_mobile'] ?? '' ) === 'column', 'photo CTA compiles to desktop columns and stacks at tablet/mobile breakpoints' );
$check( (float) ( $cta_photo_copy['settings']['width']['size'] ?? 0 ) === 60.0 && (float) ( $cta_photo_media['settings']['width']['size'] ?? 0 ) === 40.0 && (float) ( $cta_photo_copy['settings']['width_mobile']['size'] ?? 0 ) === 100.0 && (float) ( $cta_photo_media['settings']['width_mobile']['size'] ?? 0 ) === 100.0, 'photo CTA uses 60/40 native container widths and full-width mobile stack' );
$check( ( $cta_photo_image['widgetType'] ?? '' ) === 'image' && ( $cta_photo_image['settings']['image']['url'] ?? '' ) === $cta_photo_url && ( $cta_photo_image['settings']['image']['alt'] ?? '' ) === 'Иллюстративное фото современного интерьера с бетоном и деревом' && ! empty( $cta_photo_refs[0]['allowed_reuse'] ) && ( $cta_photo_refs[0]['attribution'] ?? '' ) === 'Neon Wang', 'photo CTA keeps the native image URL, alt text and confirmed Unsplash license provenance' );
$cta_replacement_context = [ 'targeted_design_repair' => true, 'operation_owned_root_ids' => [ 'owned-root' ], 'replaces_operation' => [ 'operation_id' => 'op-owned', 'operation_identity' => 'owned-identity', 'revision' => 4, 'root_ids' => [ 'owned-root' ] ] ];
$cta_replacement_prompt = 'Самостоятельный CTA — обнови выбранный блок и сохрани его содержание';
$check( wpae_llm_targeted_design_replacement_shape_valid( $cta_replacement_prompt, true, $cta_replacement_context, [ 'owned-root' ] ), 'explicit selected-root design repair can enter the guarded deterministic replacement path' );
$check( ! wpae_llm_targeted_design_replacement_shape_valid( $cta_replacement_prompt, true, $cta_replacement_context, [ 'neighbor-root' ] ) && ! wpae_llm_targeted_design_replacement_shape_valid( $cta_replacement_prompt, true, array_merge( $cta_replacement_context, [ 'operation_owned_root_ids' => [ 'neighbor-root' ] ] ), [ 'owned-root' ] ), 'targeted design replacement rejects a selected neighbor or mismatched operation ownership before any write' );
$check( ! wpae_llm_targeted_design_replacement_shape_valid( 'Добавь новый CTA-блок', true, $cta_replacement_context, [ 'owned-root' ] ), 'explicit append intent cannot be reinterpreted as a targeted replacement' );
$architecture_cta_prompt = "Добавь отдельный CTA-блок\nЗаголовок: «Обсудите следующий шаг архитектурного проекта»\nОписание: «Опишите задачу и получите понятный первый шаг.»\nКнопка: «Связаться», ссылка #contact\nВторая кнопка: «Посмотреть проекты», ссылка #projects";
$architecture_cta_brief = wpae_brief_ir_parse( $architecture_cta_prompt );
$check( wpae_llm_detect_block_archetype( $architecture_cta_prompt ) === 'cta' && $architecture_cta_brief['intent']['archetype'] === 'cta', 'explicit standalone CTA intent wins over architecture/studio content-only hero heuristics' );
$hero_two_ctas = "Создай Hero\nНадзаголовок: «АРХИТЕКТУРА»\nЗаголовок: «Пространство для идей»\nОписание: «Опишите задачу и получите понятный первый шаг.»\nКнопка: «Начать проект», ссылка #contact\nВторая кнопка: «Смотреть проекты», ссылка #projects";
$check( wpae_llm_detect_block_archetype( $hero_two_ctas ) === 'hero' && wpae_brief_ir_parse( $hero_two_ctas )['intent']['archetype'] === 'hero', 'explicit hero remains hero when it contains two CTA buttons' );
$natural_cta_prompt = 'Секция призыва к действию: «Обсудим проект». Текст: «Опишите задачу». Кнопки: «Связаться» → #contact; «Проекты» → #projects.';
$natural_cta_brief = wpae_brief_ir_parse( $natural_cta_prompt );
$natural_cta_plan = wpae_design_plan_from_brief( $natural_cta_brief );
$natural_cta_validation = wpae_design_plan_validate( $natural_cta_plan, $natural_cta_brief );
$natural_cta_items = array_values( array_filter( (array) ( $natural_cta_brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && in_array( (string) ( $item['role'] ?? '' ), [ 'title', 'body', 'cta', 'cta_2' ], true ) ) );
$check( $natural_cta_brief['intent']['archetype'] === 'cta' && $natural_cta_validation['ok'] && array_column( $natural_cta_items, 'role' ) === [ 'title', 'body', 'cta', 'cta_2' ] && array_column( $natural_cta_items, 'url' ) === [ null, null, '#contact', '#projects' ], 'natural standalone CTA wording extracts title, copy and both explicit button destinations' );
$invalid_cta_brief = wpae_brief_ir_parse( "Самостоятельный CTA\nЗаголовок: «Оставить заявку»\nКнопка: «Написать»" );
$invalid_cta_plan = wpae_design_plan_from_brief( $invalid_cta_brief );
$invalid_cta_validation = wpae_design_plan_validate( $invalid_cta_plan, $invalid_cta_brief );
$check( ! $invalid_cta_validation['ok'] && in_array( 'cta_button_1_explicit_url_required', $invalid_cta_validation['errors'], true ), 'standalone CTA rejects a missing explicit destination before compilation' );

$services_recipe_prompt = "Блок услуг\nНадзаголовок: «КАК МЫ ПОМОГАЕМ»\nЗаголовок: «Услуги для вашего проекта»\nОписание: «Подробное вступление к списку услуг.»\nУслуга 1 — название: «Стратегия»\nУслуга 1 — описание: «Длинное описание стратегии с несколькими важными этапами и результатами для проекта. Оно должно оставаться рядом только со своей карточкой.»\nУслуга 1 — кнопка: «Подробнее», ссылка #strategy\nУслуга 2 — название: «Дизайн»\nУслуга 2 — описание: «Создаем ясное визуальное решение, сохраняя детали и удобство.»\nУслуга 2 — кнопка: «Подробнее», ссылка #design\nУслуга 3 — название: «Сопровождение»\nУслуга 3 — описание: «Проверяем проектные решения и координируем ключевые этапы.»\nУслуга 3 — кнопка: «Подробнее», ссылка #support";
$services_recipe_brief = wpae_brief_ir_parse( $services_recipe_prompt );
$services_recipe_alias_cases = [
	[ 'phrase' => 'photo_cards', 'recipe_id' => 'services.photo_cards' ],
	[ 'phrase' => 'services.split_editorial', 'recipe_id' => 'services.split_editorial' ],
	[ 'phrase' => 'Список услуг с иконками', 'recipe_id' => 'services.text_icon_list' ],
];
$services_recipe_alias_ok = true;
foreach ( $services_recipe_alias_cases as $recipe_alias_case ) {
	$alias_source = $services_recipe_prompt . "\nКомпозиция: " . $recipe_alias_case['phrase'];
	$alias_brief = wpae_brief_ir_parse( $alias_source );
	$recipe_constraints = array_values( array_filter( (array) ( $alias_brief['layout_constraints'] ?? [] ), static fn( array $constraint ): bool => ( $constraint['kind'] ?? '' ) === 'services_recipe' ) );
	$recipe_constraint = (array) ( $recipe_constraints[0] ?? [] );
	$source_span = array_values( (array) ( $recipe_constraint['source_span'] ?? [] ) );
	$span_text = count( $source_span ) === 2 ? substr( $alias_source, (int) $source_span[0], (int) $source_span[1] - (int) $source_span[0] ) : '';
	$services_recipe_alias_ok = $services_recipe_alias_ok && count( $recipe_constraints ) === 1 && ( $recipe_constraint['value'] ?? '' ) === $recipe_alias_case['recipe_id'] && $span_text === $recipe_alias_case['phrase'] && ( $recipe_constraint['provenance']['source_span'] ?? [] ) === $source_span && ( $recipe_constraint['provenance']['parser'] ?? '' ) === WPAE_BRIEF_IR_PARSER_VERSION;
}
$services_recipe_conflict_brief = wpae_brief_ir_parse( $services_recipe_prompt . "\nВыбери photo_cards и services.text_icon_list." );
$services_recipe_conflict_decision = wpae_design_plan_services_recipe_decision( $services_recipe_conflict_brief );
$check( $services_recipe_alias_ok && count( array_filter( (array) ( $services_recipe_conflict_brief['layout_constraints'] ?? [] ), static fn( array $constraint ): bool => ( $constraint['kind'] ?? '' ) === 'services_recipe' ) ) === 2 && empty( $services_recipe_conflict_decision['ok'] ) && ( $services_recipe_conflict_decision['reason'] ?? '' ) === 'services_recipe_selection_conflict', 'canonical Brief captures each explicit recipe alias once with byte-accurate source span and refuses conflicting recipe choices' );
$services_recipe_assets = [];
for ( $asset_index = 1; $asset_index <= 6; $asset_index++ ) {
	$services_recipe_assets[] = [
		'asset_id' => 'services_recipe_photo_' . $asset_index,
		'attachment_id' => null,
		'source_url' => 'https://example.com/fixture/service-' . $asset_index . '.jpg',
		'role' => 'card_image',
		'group_id' => 'service_' . $asset_index,
		'alt' => 'Synthetic service image ' . $asset_index,
		'license' => 'Contract fixture',
		'attribution' => 'Contract fixture',
		'allowed_reuse' => true,
		'provenance' => [ 'source' => 'synthetic_test_fixture' ],
	];
}
$services_recipe_tokens = [ 'palette' => [ 'paper' => '#f6f0e6', 'surface' => '#ffffff', 'ink' => '#111827', 'muted' => '#4b5563', 'accent' => '#4460ec', 'border' => '#d1d5db' ] ];
$services_recipe_compile = static function ( array $brief, string $recipe_id, array $assets, string $seed, string $lead_ref = '' ) use ( $services_recipe_tokens ): array {
	$context = [ 'services_recipe_id' => $recipe_id, 'media_references' => $assets, 'canonical_create' => true ];
	if ( $lead_ref !== '' ) {
		$context['services_lead_service_ref'] = $lead_ref;
	}
	$plan = wpae_design_plan_from_brief( $brief, $context );
	$plan_validation = wpae_design_plan_validate( $plan, $brief );
	$ir = wpae_elementor_ir_from_design_plan( $plan, $brief, $context );
	$ir_validation = wpae_elementor_ir_validate( $ir, $brief );
	$compiled = wpae_native_elementor_compile( $ir, $brief, $services_recipe_tokens, [ 'id_seed' => $seed ] );
	return [ 'plan' => $plan, 'plan_validation' => $plan_validation, 'ir' => $ir, 'ir_validation' => $ir_validation, 'compiled' => $compiled ];
};
$walk_ir_nodes = static function ( array $nodes ) use ( &$walk_ir_nodes ): array {
	$all = [];
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$all[] = $node;
		$all = array_merge( $all, $walk_ir_nodes( (array) ( $node['children'] ?? [] ) ) );
	}
	return $all;
};
$strip_elementor_ids = static function ( array $nodes ) use ( &$strip_elementor_ids ): array {
	foreach ( $nodes as &$node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		unset( $node['id'] );
		if ( is_array( $node['elements'] ?? null ) ) {
			$node['elements'] = $strip_elementor_ids( $node['elements'] );
		}
	}
	unset( $node );
	return $nodes;
};
$services_recipe_ids = [ 'services.photo_cards', 'services.split_editorial', 'services.text_icon_list' ];
$services_recipe_results = [];
foreach ( $services_recipe_ids as $recipe_index => $recipe_id ) {
	$services_recipe_results[ $recipe_id ] = $services_recipe_compile( $services_recipe_brief, $recipe_id, array_slice( $services_recipe_assets, 0, 3 ), 'services-recipe-' . ( $recipe_index + 1 ), 'service_2' );
}
$photo_recipe_result = $services_recipe_results['services.photo_cards'];
$split_recipe_result = $services_recipe_results['services.split_editorial'];
$text_recipe_result = $services_recipe_results['services.text_icon_list'];
$legacy_services_plan = wpae_design_plan_from_brief( $services_recipe_brief );
$legacy_services_roles = array_column( (array) ( $legacy_services_plan['sections'][0]['children'] ?? [] ), 'role' );
$check( empty( $legacy_services_plan['recipe_id'] ) && in_array( 'service_cards', $legacy_services_roles, true ), 'existing Services planning calls keep the legacy default when no explicit recipe is selected' );
$services_badge_contract_ok = true;
$services_badge_ir_contract_ok = true;
$services_badge_text = (string) ( array_values( array_filter( (array) ( $services_recipe_brief['content'] ?? [] ), static fn( array $item ): bool => ( $item['role'] ?? '' ) === 'eyebrow' ) )[0]['exact_text'] ?? '' );
foreach ( $services_recipe_results as $recipe_result ) {
	$intro_group = (array) ( $recipe_result['plan']['sections'][0]['children'][0] ?? [] );
	$native_nodes = $walk_elements( (array) ( $recipe_result['compiled']['elementor_data'] ?? [] ) );
	$native_badges = array_values( array_filter( $native_nodes, static fn( array $node ): bool => ( $node['elType'] ?? '' ) === 'container' && ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-generated-badge' ) );
	$native_labels = array_values( array_filter( $native_nodes, static fn( array $node ): bool => ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'heading' && ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-generated-badge-label' ) );
	$badge = $native_badges[0] ?? [];
	$label = $native_labels[0] ?? [];
	$services_badge_contract_ok = $services_badge_contract_ok
		&& ( $intro_group['layout_constraints']['eyebrow_presentation'] ?? '' ) === 'pill'
		&& in_array( 'container', (array) ( $intro_group['allowed_widgets'] ?? [] ), true )
		&& count( $native_badges ) === 1
		&& count( $native_labels ) === 1
		&& ( $label['settings']['title'] ?? '' ) === $services_badge_text
		&& ( $label['settings']['title_color'] ?? '' ) === ( $recipe_result['plan']['visual_policy']['eyebrow_colors']['pill'] ?? '' )
		&& ( $label['settings']['typography_font_size']['unit'] ?? '' ) === 'rem'
		&& (float) ( $label['settings']['typography_font_size']['size'] ?? 0 ) === 0.75
		&& ( $label['settings']['typography_text_transform'] ?? '' ) === 'uppercase'
		&& ( $badge['settings']['background_color'] ?? '' ) === ( $recipe_result['plan']['visual_policy']['eyebrow_colors']['pill_background'] ?? '' )
		&& ( $badge['settings']['border_color'] ?? '' ) === ( $recipe_result['plan']['visual_policy']['eyebrow_colors']['pill_border'] ?? '' )
		&& ( $badge['settings']['border_width']['top'] ?? '' ) === '2'
		&& (float) ( $badge['settings']['border_radius']['size'] ?? 0 ) >= 999
		&& ( $badge['settings']['padding']['top'] ?? '' ) === '0.5'
		&& ( $badge['settings']['padding']['right'] ?? '' ) === '1.75'
		&& ( $badge['settings']['_element_width'] ?? '' ) === 'initial';
	$ir_nodes = $walk_ir_nodes( (array) ( $recipe_result['ir']['nodes'] ?? [] ) );
	$services_badge_ir_contract_ok = $services_badge_ir_contract_ok
		&& count( array_filter( $ir_nodes, static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_badge' ) ) === 1
		&& count( array_filter( $ir_nodes, static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_badge_label' ) ) === 1;
}
$check( $services_badge_contract_ok && $services_badge_ir_contract_ok, 'all typed Services recipes keep a single-owner high-contrast outlined eyebrow pill in native settings' );
$services_brief_media_intent = (array) ( array_values( array_filter( (array) ( $services_recipe_brief['layout_constraints'] ?? [] ), static fn( array $constraint ): bool => ( $constraint['kind'] ?? '' ) === 'media_intent' ) )[0] ?? [] );
$check( $services_recipe_brief['intent']['archetype'] === 'services' && ( $services_brief_media_intent['value'] ?? '' ) === 'unspecified' && array_reduce( $services_recipe_results, static fn( bool $ok, array $result ): bool => $ok && ! empty( $result['plan_validation']['ok'] ) && ! empty( $result['ir_validation']['ok'] ) && ! empty( $result['compiled']['ok'] ), true ), 'the same canonical Services Brief validates through all three explicitly selected recipes without changing production defaults' );
$check( array_column( array_map( static fn( array $result ): array => [ 'recipe_id' => $result['plan']['recipe_id'], 'role' => $result['plan']['sections'][0]['children'][1]['role'] ?? '' ], array_values( $services_recipe_results ) ), 'recipe_id' ) === $services_recipe_ids && array_column( array_map( static fn( array $result ): array => [ 'recipe_id' => $result['plan']['recipe_id'], 'role' => $result['plan']['sections'][0]['children'][1]['role'] ?? '' ], array_values( $services_recipe_results ) ), 'role' ) === [ 'services_photo_grid', 'services_split_editorial', 'services_text_icon_list' ], 'opt-in recipe selection produces three different typed DesignPlan compositions' );
$photo_root = $photo_recipe_result['compiled']['elementor_data'][0] ?? [];
$photo_grid = array_values( array_filter( (array) ( $photo_root['elements'] ?? [] ), static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-photo-grid' ) )[0] ?? [];
$photo_cards = (array) ( $photo_grid['elements'] ?? [] );
$photo_nodes = $walk_elements( (array) ( $photo_recipe_result['compiled']['elementor_data'] ?? [] ) );
$photo_text_values = array_values( array_filter( array_map( static fn( array $node ): string => (string) ( $node['settings']['title'] ?? $node['settings']['editor'] ?? '' ), $photo_nodes ), static fn( string $text ): bool => $text !== '' ) );
$check( in_array( 'КАК МЫ ПОМОГАЕМ', $photo_text_values, true ) && in_array( 'Услуги для вашего проекта', $photo_text_values, true ) && in_array( 'Подробное вступление к списку услуг.', $photo_text_values, true ), 'photo composition retains exact Brief-backed section eyebrow, heading and description' );
$photo_ir_images = array_values( array_filter( $walk_ir_nodes( (array) ( $photo_recipe_result['ir']['nodes'] ?? [] ) ), static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_photo_image' ) );
$check( count( $photo_ir_images ) === 3 && ! array_filter( $photo_ir_images, static fn( array $node ): bool => ( $node['layout_constraints']['aspect_ratio'] ?? '' ) !== '4:3' ), 'photo Image nodes carry the shared 4:3 aspect-ratio composition target' );
$photo_images = array_values( array_filter( $photo_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) );
$photo_buttons = array_values( array_filter( $photo_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) );
$photo_item_fidelity = count( $photo_cards ) === 3;
foreach ( $photo_cards as $index => $card ) {
	$direct = (array) ( $card['elements'] ?? [] );
	$body_children = (array) ( $direct[0]['elements'] ?? [] );
	$panel_widgets = (array) ( $body_children[1]['elements'] ?? [] );
	$expected_group = $services_recipe_brief['groups'][ $index ] ?? [];
	$expected_content = [ $expected_group['title_ref'] ?? '', $expected_group['body_ref'] ?? '' ];
	$actual_content = array_values( array_map( static fn( array $node ): string => (string) ( $node['settings']['title'] ?? $node['settings']['editor'] ?? '' ), array_filter( $panel_widgets, static fn( array $node ): bool => in_array( ( $node['widgetType'] ?? '' ), [ 'heading', 'text-editor' ], true ) ) ) );
	$expected_text = array_map( static fn( string $ref ): string => (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === $ref ) )[0]['exact_text'] ?? '' ), $expected_content );
	$expected_url = (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === ( $expected_group['cta_ref'] ?? '' ) ) )[0]['url'] ?? '' );
	$card_button = array_values( array_filter( (array) ( $direct[1]['elements'] ?? [] ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) )[0] ?? [];
	$expected_media_ref = (string) ( $photo_recipe_result['plan']['sections'][0]['children'][1]['items'][ $index ]['media_ref'] ?? '' );
	$expected_asset = array_values( array_filter( $services_recipe_assets, static fn( array $asset ): bool => ( $asset['asset_id'] ?? '' ) === $expected_media_ref ) )[0] ?? [];
	$actual_image = (array) ( $body_children[0] ?? [] );
	$expected_source = (string) ( $expected_asset['source_url'] ?? '' );
	$actual_source = (string) ( $actual_image['settings']['image']['url'] ?? '' );
	$source_matches = $expected_source !== '' && parse_url( $actual_source, PHP_URL_HOST ) === parse_url( $expected_source, PHP_URL_HOST ) && parse_url( $actual_source, PHP_URL_PATH ) === parse_url( $expected_source, PHP_URL_PATH );
	if ( parse_url( $expected_source, PHP_URL_HOST ) === 'images.unsplash.com' ) {
		parse_str( (string) parse_url( $actual_source, PHP_URL_QUERY ), $source_query );
		$source_matches = $source_matches && ( $source_query['fit'] ?? '' ) === 'crop' && (int) ( $source_query['w'] ?? 0 ) === 1200 && (int) ( $source_query['h'] ?? 0 ) === 900;
	}
	$photo_item_fidelity = $photo_item_fidelity && ( $actual_image['widgetType'] ?? '' ) === 'image' && $source_matches && ( $actual_image['settings']['image']['alt'] ?? '' ) === ( $expected_asset['alt'] ?? null ) && $actual_content === $expected_text && ( $card_button['settings']['link']['url'] ?? '' ) === $expected_url;
}
$photo_id_values = array_column( $photo_nodes, 'id' );
$check( $photo_item_fidelity && count( $photo_images ) === 3 && count( $photo_buttons ) === 3 && count( array_unique( $photo_id_values ) ) === count( $photo_id_values ), 'photo cards keep image first, exact matching title/body/CTA URLs, and unique native Elementor IDs per source service' );
$photo_panel_settings = (array) ( $photo_cards[0]['elements'][0]['elements'][1]['settings'] ?? [] );
$check( ( $photo_grid['settings']['container_type'] ?? '' ) === 'grid' && ( $photo_grid['settings']['grid_columns_grid']['unit'] ?? '' ) === 'fr' && (int) ( $photo_grid['settings']['grid_columns_grid']['size'] ?? 0 ) === 3 && ( $photo_grid['settings']['grid_columns_grid_tablet']['unit'] ?? '' ) === 'fr' && (int) ( $photo_grid['settings']['grid_columns_grid_tablet']['size'] ?? 0 ) === 1 && (int) ( $photo_grid['settings']['grid_columns_grid_mobile']['size'] ?? 0 ) === 1 && ( $photo_grid['settings']['grid_gaps']['unit'] ?? '' ) === 'rem' && (float) ( $photo_grid['settings']['grid_gaps']['column'] ?? 0 ) > 0 && ( $photo_panel_settings['padding']['right'] ?? '' ) === '0' && ( $photo_panel_settings['padding']['left'] ?? '' ) === '0' && ( $photo_panel_settings['padding_mobile']['top'] ?? '' ) === '20', 'photo recipe compiles equal native Grid tracks with gap-aware desktop geometry, tablet/mobile stack and reference copy padding' );
$photo_panel = (array) ( $photo_cards[0]['elements'][0]['elements'][1] ?? [] );
$check( ( $photo_grid['settings']['_element_custom_width_tablet']['unit'] ?? '' ) === '%' && (float) ( $photo_grid['settings']['_element_custom_width_tablet']['size'] ?? 0 ) === 100.0 && (float) ( $photo_cards[0]['settings']['_element_custom_width_tablet']['size'] ?? 0 ) === 100.0 && (float) ( $photo_panel['settings']['_element_custom_width_tablet']['size'] ?? 0 ) === 100.0, 'photo recipe stacks the full-width collection, cards and copy panels on tablet' );
$check( ( $photo_images[0]['settings']['object-fit'] ?? '' ) === 'cover' && ( $photo_images[0]['settings']['height']['unit'] ?? '' ) === 'px' && (float) ( $photo_images[0]['settings']['height']['size'] ?? 0 ) > 0 && ( $photo_images[0]['settings']['height_mobile']['unit'] ?? '' ) === 'px' && (float) ( $photo_images[0]['settings']['height_mobile']['size'] ?? 0 ) > 0 && ( $photo_cards[0]['settings']['border_radius_mobile']['unit'] ?? '' ) === 'px' && (float) ( $photo_cards[0]['settings']['border_radius_mobile']['top'] ?? 0 ) === 16.0 && ( $photo_recipe_result['plan']['media_compatibility']['consumed_asset_refs'] ?? [] ) === [ 'services_recipe_photo_1', 'services_recipe_photo_2', 'services_recipe_photo_3' ], 'photo recipe consumes explicit assets with native pixel crop heights and responsive rounded-card controls' );
$repeat_compile_checks = [];
foreach ( $services_recipe_results as $recipe_index => $recipe_result ) {
	$repeat_result = $services_recipe_compile( $services_recipe_brief, $recipe_index, array_slice( $services_recipe_assets, 0, 3 ), 'services-recipe-repeat-' . count( $repeat_compile_checks ), 'service_2' );
	$repeat_compile_checks[] = ! empty( $repeat_result['compiled']['ok'] )
		&& $strip_elementor_ids( (array) ( $repeat_result['compiled']['elementor_data'] ?? [] ) ) === $strip_elementor_ids( (array) ( $recipe_result['compiled']['elementor_data'] ?? [] ) )
		&& ( $repeat_result['plan']['slot_bindings'] ?? [] ) === ( $recipe_result['plan']['slot_bindings'] ?? [] )
		&& ( $repeat_result['plan']['recipe_selection'] ?? [] ) === ( $recipe_result['plan']['recipe_selection'] ?? [] );
}
$check( ! in_array( false, $repeat_compile_checks, true ), 'repeated compilation preserves each recipe topology, content, decisions and slot bindings when generated Elementor IDs are ignored' );
$split_root = $split_recipe_result['compiled']['elementor_data'][0] ?? [];
$split_nodes = $walk_ir_nodes( (array) ( $split_recipe_result['ir']['nodes'] ?? [] ) );
$split_lead_ir = array_values( array_filter( $split_nodes, static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_split_lead' ) )[0] ?? [];
$split_rows_ir = array_values( array_filter( $split_nodes, static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_editorial_rows' ) )[0] ?? [];
$split_row_order = array_values( array_map( static fn( array $node ): string => (string) ( $node['layout_constraints']['item_id'] ?? '' ), array_filter( (array) ( $split_rows_ir['children'] ?? [] ), static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_editorial_row' ) ) );
$split_lead = array_values( array_filter( $walk_elements( (array) ( $split_recipe_result['compiled']['elementor_data'] ?? [] ) ), static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-split-lead' ) )[0] ?? [];
$split_lead_nodes = $walk_elements( [ $split_lead ] );
$split_lead_group = array_values( array_filter( $services_recipe_brief['groups'], static fn( array $group ): bool => ( $group['group_id'] ?? '' ) === 'service_2' ) )[0] ?? [];
$split_lead_text = array_values( array_map( static fn( array $node ): string => (string) ( $node['settings']['title'] ?? $node['settings']['editor'] ?? '' ), array_filter( $split_lead_nodes, static fn( array $node ): bool => in_array( ( $node['widgetType'] ?? '' ), [ 'heading', 'text-editor' ], true ) ) ) );
$split_lead_content = array_map( static fn( string $ref ): string => (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === $ref ) )[0]['exact_text'] ?? '' ), array_filter( [ $split_lead_group['title_ref'] ?? '', $split_lead_group['body_ref'] ?? '' ] ) );
$split_lead_image = array_values( array_filter( $split_lead_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) )[0] ?? [];
$split_lead_button = array_values( array_filter( $split_lead_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) )[0] ?? [];
$split_buttons = array_values( array_filter( $walk_elements( (array) ( $split_recipe_result['compiled']['elementor_data'] ?? [] ) ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) );
$split_editorial_rows = array_values( array_filter( $walk_elements( (array) ( $split_recipe_result['compiled']['elementor_data'] ?? [] ) ), static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-editorial-row' ) );
$split_editorial_fidelity = count( $split_editorial_rows ) === count( $split_row_order );
foreach ( $split_editorial_rows as $index => $row ) {
	$group_id = $split_row_order[ $index ] ?? '';
	$expected_group = array_values( array_filter( $services_recipe_brief['groups'], static fn( array $group ): bool => ( $group['group_id'] ?? '' ) === $group_id ) )[0] ?? [];
	$expected_refs = array_filter( [ $expected_group['title_ref'] ?? '', $expected_group['body_ref'] ?? '' ] );
	$expected_item_text = array_map( static fn( string $ref ): string => (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === $ref ) )[0]['exact_text'] ?? '' ), $expected_refs );
	$row_nodes = $walk_elements( [ $row ] );
	$actual_item_text = array_values( array_map( static fn( array $node ): string => (string) ( $node['settings']['title'] ?? $node['settings']['editor'] ?? '' ), array_filter( $row_nodes, static fn( array $node ): bool => in_array( ( $node['widgetType'] ?? '' ), [ 'heading', 'text-editor' ], true ) ) ) );
	$expected_cta = (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === ( $expected_group['cta_ref'] ?? '' ) ) )[0]['url'] ?? '' );
	$row_button = array_values( array_filter( $row_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) )[0] ?? [];
	$split_editorial_fidelity = $split_editorial_fidelity && $actual_item_text === $expected_item_text && ( $row_button['settings']['link']['url'] ?? '' ) === $expected_cta;
}
$split_photo_count = count( array_filter( $walk_elements( (array) ( $split_recipe_result['compiled']['elementor_data'] ?? [] ) ), static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) );
$check( ( $split_lead_ir['layout_constraints']['item_id'] ?? '' ) === 'service_2' && $split_row_order === [ 'service_1', 'service_3' ] && count( array_filter( $split_buttons, static fn( array $node ): bool => in_array( (string) ( $node['settings']['link']['url'] ?? '' ), [ '#strategy', '#design', '#support' ], true ) ) ) === 3 && $split_editorial_fidelity, 'split editorial honors a selected non-first lead and keeps each secondary row content and CTA paired in source order' );
$check( ( $split_lead['settings']['flex_direction'] ?? '' ) === 'row' && ( $split_lead['settings']['flex_direction_tablet'] ?? '' ) === 'column' && ( $split_lead['settings']['flex_direction_mobile'] ?? '' ) === 'column' && (float) ( $split_lead['elements'][0]['settings']['_element_custom_width']['size'] ?? 0 ) === 52.0 && (float) ( $split_lead['elements'][1]['settings']['_element_custom_width']['size'] ?? 0 ) === 44.0 && ( $split_lead['elements'][1]['elements'][0]['widgetType'] ?? '' ) === 'image' && $split_photo_count === 1 && $split_lead_text === $split_lead_content && ( $split_lead_button['settings']['link']['url'] ?? '' ) === '#design' && ( $split_lead_image['settings']['image']['url'] ?? '' ) === ( $services_recipe_assets[1]['source_url'] ?? '' ), 'split lead binds service_2 exact title/body/link and matching image to a native 52/44 desktop row that stacks at tablet/mobile' );
$check( ( $split_recipe_result['plan']['media_compatibility']['consumed_asset_refs'] ?? [] ) === [ 'services_recipe_photo_2' ] && ( $split_recipe_result['plan']['media_compatibility']['unconsumed_asset_refs'] ?? [] ) === [ 'services_recipe_photo_1', 'services_recipe_photo_3' ], 'split recipe declares its lead image consumed and reports other supplied images as unconsumed' );
$text_nodes = $walk_elements( (array) ( $text_recipe_result['compiled']['elementor_data'] ?? [] ) );
$text_rows = array_values( array_filter( $text_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-text-icon-row' ) );
$text_item_fidelity = count( $text_rows ) === count( $services_recipe_brief['groups'] );
foreach ( $text_rows as $index => $row ) {
	$expected_group = $services_recipe_brief['groups'][ $index ] ?? [];
	$expected_refs = array_filter( [ $expected_group['title_ref'] ?? '', $expected_group['body_ref'] ?? '' ] );
	$expected_item_text = array_map( static fn( string $ref ): string => (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === $ref ) )[0]['exact_text'] ?? '' ), $expected_refs );
	$row_nodes = $walk_elements( [ $row ] );
	$actual_item_text = array_values( array_map( static fn( array $node ): string => (string) ( $node['settings']['title'] ?? $node['settings']['editor'] ?? '' ), array_filter( $row_nodes, static fn( array $node ): bool => in_array( ( $node['widgetType'] ?? '' ), [ 'heading', 'text-editor' ], true ) ) ) );
	$expected_cta = (string) ( array_values( array_filter( $services_recipe_brief['content'], static fn( array $item ): bool => ( $item['id'] ?? '' ) === ( $expected_group['cta_ref'] ?? '' ) ) )[0]['url'] ?? '' );
	$row_button = array_values( array_filter( $row_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) )[0] ?? [];
	$text_item_fidelity = $text_item_fidelity && $actual_item_text === $expected_item_text && ( $row_button['settings']['link']['url'] ?? '' ) === $expected_cta;
}
$text_rows_ir = array_values( array_filter( $walk_ir_nodes( (array) ( $text_recipe_result['ir']['nodes'] ?? [] ) ), static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_text_icon_list' ) )[0] ?? [];
$text_order = array_values( array_map( static fn( array $node ): string => (string) ( $node['layout_constraints']['item_id'] ?? '' ), array_filter( (array) ( $text_rows_ir['children'] ?? [] ), static fn( array $node ): bool => ( $node['role'] ?? '' ) === 'services_text_icon_row' ) ) );
$check( $text_order === [ 'service_1', 'service_2', 'service_3' ] && count( array_filter( $text_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'icon' ) ) === 3 && count( array_filter( $text_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'divider' ) ) === 2 && count( array_filter( $text_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'image' ) ) === 0 && count( array_filter( $text_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'button' ) ) === 3, 'text/icon list is ordered native rows with separate icons and dividers, exact actions and no image widgets' );
$check( $text_item_fidelity, 'compiled text/icon rows keep each service title, description and CTA URL inside its matching row' );
$text_icons = array_values( array_filter( $text_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'icon' ) );
$first_text_row = $text_rows[0] ?? [];
$first_text_row_nodes = $walk_elements( [ $first_text_row ] );
$first_text_title = array_values( array_filter( $first_text_row_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'heading' ) )[0] ?? [];
$first_text_body = array_values( array_filter( $first_text_row_nodes, static fn( array $node ): bool => ( $node['widgetType'] ?? '' ) === 'text-editor' ) )[0] ?? [];
$check( count( $text_icons ) === 3 && (float) ( $text_icons[0]['settings']['size']['size'] ?? 0 ) === 22.0 && (float) ( $text_icons[0]['settings']['_element_custom_width']['size'] ?? 0 ) === 44.0 && ! isset( $text_icons[0]['settings']['icon_padding'] ) && ( $first_text_row['settings']['flex_gap']['unit'] ?? '' ) === 'rem' && (float) ( $first_text_row['settings']['flex_gap']['size'] ?? 0 ) === 1.0, 'text/icon marker uses accepted native 22px glyph sizing with a 44px icon track and a separate 1rem copy gap' );
$check( ( $first_text_title['settings']['header_size'] ?? '' ) === 'h3' && ( $first_text_title['settings']['typography_font_size']['unit'] ?? '' ) === 'rem' && (float) ( $first_text_title['settings']['typography_font_size']['size'] ?? 0 ) === 1.125 && ( $first_text_title['settings']['typography_font_weight'] ?? '' ) === '600' && (float) ( $first_text_body['settings']['typography_font_size']['size'] ?? 0 ) === 1.0 && ( $first_text_body['settings']['typography_font_weight'] ?? '' ) === '400' && (float) ( $first_text_body['settings']['typography_line_height']['size'] ?? 0 ) === 1.6, 'Services item title and body compile with readable heading hierarchy and untruncated natural-height copy' );
$check( ( $first_text_row['settings']['background_background'] ?? '' ) === 'classic' && ( $first_text_row['settings']['background_color'] ?? '' ) === 'transparent' && ( $first_text_row['settings']['padding']['top'] ?? '' ) === '16' && ( $first_text_row['settings']['padding']['bottom'] ?? '' ) === '16' && ( $first_text_row['settings']['padding']['left'] ?? '' ) === '0' && ! isset( $first_text_row['settings']['height'] ) && ! isset( $first_text_row['settings']['min_height'] ), 'default text/icon rows use transparent editorial surfaces with vertical rhythm and no unpadded white bars' );
$check( in_array( 'type.display', (array) ( $text_recipe_result['plan']['sections'][0]['children'][1]['token_refs'] ?? [] ), true ) && in_array( 'type.body', (array) ( $text_recipe_result['plan']['sections'][0]['children'][1]['token_refs'] ?? [] ), true ), 'Services recipe plan retains the selected design system display and body typography token references' );
$split_secondary_row = $split_editorial_rows[0] ?? [];
$check( ( $split_lead['settings']['background_background'] ?? '' ) === 'classic' && ( $split_lead['settings']['background_color'] ?? '' ) === '#ffffff' && ( $split_lead['settings']['padding']['top'] ?? '' ) === '24' && (float) ( $split_lead['settings']['border_radius']['size'] ?? 0 ) === 16.0 && ( $split_secondary_row['settings']['background_color'] ?? '' ) === 'transparent' && ( $split_secondary_row['settings']['padding']['top'] ?? '' ) === '16' && ( $split_secondary_row['settings']['padding']['bottom'] ?? '' ) === '16', 'split recipe emphasizes the lead surface and keeps secondary editorial rows transparent, padded and aligned' );
$override_services_ir = $split_recipe_result['ir'];
$apply_services_surface_override = static function ( array &$nodes ) use ( &$apply_services_surface_override ): void {
	foreach ( $nodes as &$node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( in_array( $node['role'] ?? '', [ 'services_split_lead', 'services_editorial_row' ], true ) ) {
			$node['layout_constraints']['surface_override'] = '#123456';
		}
		if ( is_array( $node['children'] ?? null ) ) {
			$apply_services_surface_override( $node['children'] );
		}
	}
	unset( $node );
};
$apply_services_surface_override( $override_services_ir['nodes'] );
$override_services_compiled = wpae_native_elementor_compile( $override_services_ir, $services_recipe_brief, $services_recipe_tokens, [ 'id_seed' => 'services-explicit-surface' ] );
$override_services_nodes = $walk_elements( (array) ( $override_services_compiled['elementor_data'] ?? [] ) );
$override_lead = array_values( array_filter( $override_services_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-split-lead' ) )[0] ?? [];
$override_editorial_row = array_values( array_filter( $override_services_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-editorial-row' ) )[0] ?? [];
$check( ! empty( $override_services_compiled['ok'] ) && ( $override_lead['settings']['background_color'] ?? '' ) === '#123456' && ( $override_lead['settings']['padding']['top'] ?? '' ) === '24' && ( $override_editorial_row['settings']['background_color'] ?? '' ) === '#123456' && ( $override_editorial_row['settings']['padding']['left'] ?? '' ) === '20' && (float) ( $override_editorial_row['settings']['border_radius']['size'] ?? 0 ) === 12.0, 'explicit Services surface overrides remain intact and compile as full padded surfaces' );
$forbidden_media_services = $services_recipe_brief;
foreach ( $forbidden_media_services['layout_constraints'] as &$constraint ) {
	if ( ( $constraint['kind'] ?? '' ) === 'media_intent' ) {
		$constraint['value'] = 'forbidden';
	}
}
unset( $constraint );
$forbidden_text_recipe = $services_recipe_compile( $forbidden_media_services, 'services.text_icon_list', [], 'services-recipe-forbidden-media' );
$forbidden_photo_plan = wpae_design_plan_from_brief( $forbidden_media_services, [ 'services_recipe_id' => 'services.photo_cards', 'media_references' => array_slice( $services_recipe_assets, 0, 3 ) ] );
$forbidden_split_plan = wpae_design_plan_from_brief( $forbidden_media_services, [ 'services_recipe_id' => 'services.split_editorial', 'media_references' => array_slice( $services_recipe_assets, 0, 3 ), 'services_lead_service_ref' => 'service_2' ] );
$forbidden_text_nodes = $walk_elements( (array) ( $forbidden_text_recipe['compiled']['elementor_data'] ?? [] ) );
$check( ! empty( $forbidden_text_recipe['plan_validation']['ok'] ) && ! empty( $forbidden_text_recipe['ir_validation']['ok'] ) && ! empty( $forbidden_text_recipe['compiled']['ok'] ) && empty( $forbidden_text_recipe['plan']['media_compatibility']['consumed_asset_refs'] ) && count( array_filter( $forbidden_text_nodes, static fn( array $node ): bool => in_array( ( $node['widgetType'] ?? '' ), [ 'image', 'video', 'image-carousel' ], true ) ) ) === 0, 'explicitly forbidden media remains absent from the valid text/icon recipe with no media placeholders' );
$check( ( $text_recipe_result['plan']['media_compatibility']['unconsumed_asset_refs'] ?? [] ) === [ 'services_recipe_photo_1', 'services_recipe_photo_2', 'services_recipe_photo_3' ] && empty( $text_recipe_result['plan']['media_compatibility']['consumed_asset_refs'] ), 'optional supplied assets are explicitly declared unconsumed by the text-only recipe' );
$services_recipe_layout = wpae_layout_report_for_plan( $photo_recipe_result['plan'] );
$check( ( $services_recipe_layout['evidence'] ?? '' ) === 'static_plan' && empty( $services_recipe_layout['visual_render_verified'] ) && ( $services_recipe_layout['recipe_layout']['collection_policy']['implementation'] ?? '' ) === 'native_grid' && ( $services_recipe_layout['recipe_layout']['breakpoints'][0]['columns'] ?? 0 ) === 3 && ( $services_recipe_layout['recipe_layout']['breakpoints'][2]['columns'] ?? 0 ) === 1 && ( $services_recipe_layout['recipe_layout']['breakpoints'][3]['columns'] ?? 0 ) === 1 && ( $services_recipe_layout['recipe_layout']['breakpoints'][0]['axis'] ?? '' ) === 'equal_grid_tracks', 'recipe LayoutReport follows frozen native equal Grid tracks and tablet/mobile stack without claiming a rendered visual result' );
$intro_token_visual = [ 'profile' => 'section-test', 'values' => array_merge( wpae_design_token_defaults(), [ 'layout.intro_title_token' => 'type.section_title' ] ) ];
$intro_token_record = [ 'id' => 'services.visual_title_test', 'policy' => [ 'intro_title_token' => 'type.display' ] ];
$record_title_policy = wpae_design_plan_visual_policy( $services_recipe_brief, $intro_token_record, $intro_token_visual, 'linear', [ 'tablet' => 'stack' ], 3, 'services.photo_cards' );
$explicit_title_brief = $services_recipe_brief;
$explicit_title_brief['layout_constraints'][] = [ 'kind' => 'visual_token', 'token' => 'type.section_title' ];
$explicit_title_policy = wpae_design_plan_visual_policy( $explicit_title_brief, $intro_token_record, $intro_token_visual, 'linear', [ 'tablet' => 'stack' ], 3, 'services.photo_cards' );
$profile_title_policy = wpae_design_plan_visual_policy( $services_recipe_brief, [], $intro_token_visual, 'linear', [ 'tablet' => 'stack' ], 3, 'services.photo_cards' );
$check( $record_title_policy['intro']['title_token'] === 'type.display' && ( $record_title_policy['provenance']['field_sources']['intro_title_token'] ?? '' ) === 'composition_record' && $explicit_title_policy['intro']['title_token'] === 'type.section_title' && ( $explicit_title_policy['provenance']['field_sources']['intro_title_token'] ?? '' ) === 'explicit_brief' && $profile_title_policy['intro']['title_token'] === 'type.section_title' && ( $profile_title_policy['provenance']['field_sources']['intro_title_token'] ?? '' ) === 'resolved_visual', 'intro title size owner follows explicit Brief, composition record, selected visual profile, then documented family default while semantic level stays independent' );
$split_recipe_layout = wpae_layout_report_for_plan( $split_recipe_result['plan'] );
$text_recipe_layout = wpae_layout_report_for_plan( $text_recipe_result['plan'] );
$check( ( $split_recipe_layout['recipe_layout']['breakpoints'][0]['axis'] ?? '' ) === 'lead_row_then_editorial_stack' && ( $split_recipe_layout['recipe_layout']['breakpoints'][3]['axis'] ?? '' ) === 'vertical_stack' && ( $split_recipe_layout['recipe_layout']['breakpoints'][0]['column_basis_percent'] ?? [] ) === [ 'copy' => 52, 'image' => 44 ] && ( $text_recipe_layout['recipe_layout']['breakpoints'][0]['axis'] ?? '' ) === 'vertical_list_with_horizontal_item_rows' && ( $text_recipe_layout['recipe_layout']['breakpoints'][0]['column_basis_percent'] ?? null ) === null && ( $text_recipe_layout['recipe_layout']['native_breakpoint_controls']['row']['copy_width_owner'] ?? '' ) === 'remaining_native_flex_width_after_icon_and_gap', 'static LayoutReport distinguishes split stack and text-list rows without inventing a percentage copy basis' );
$services_geometry_cases = [];
foreach ( [ 'services.photo_cards' => $photo_recipe_result['plan'], 'services.split_editorial' => $split_recipe_result['plan'], 'services.text_icon_list' => $text_recipe_result['plan'] ] as $geometry_recipe_id => $geometry_plan ) {
	$geometry_node_index = null;
	foreach ( (array) ( $geometry_plan['sections'][0]['children'] ?? [] ) as $child_index => $child ) {
		if ( is_array( $child ) && ( $child['role'] ?? '' ) === ( $geometry_recipe_id === 'services.photo_cards' ? 'services_photo_grid' : ( $geometry_recipe_id === 'services.split_editorial' ? 'services_split_editorial' : 'services_text_icon_list' ) ) ) {
			$geometry_node_index = $child_index;
			break;
		}
	}
	foreach ( [ 2, 3, 4, 6 ] as $geometry_item_count ) {
		$geometry_case_plan = $geometry_plan;
		if ( $geometry_node_index !== null ) {
			$source_item = (array) ( $geometry_case_plan['sections'][0]['children'][ $geometry_node_index ]['items'][0] ?? [] );
			$geometry_case_plan['sections'][0]['children'][ $geometry_node_index ]['items'] = array_fill( 0, $geometry_item_count, $source_item );
		}
		$geometry_case_plan['visual_policy'] = wpae_design_plan_visual_policy( $services_recipe_brief, [], (array) ( $geometry_case_plan['resolved_visual'] ?? [] ), 'linear', (array) ( $geometry_case_plan['responsive'] ?? [] ), $geometry_item_count, $geometry_recipe_id );
		$geometry_case_report = wpae_layout_report_for_plan( $geometry_case_plan );
		$geometry_samples = (array) ( $geometry_case_report['recipe_layout']['geometry_samples'] ?? [] );
		$services_geometry_cases[] = [ 'recipe' => $geometry_recipe_id, 'items' => $geometry_item_count, 'report' => $geometry_case_report, 'sample_count' => count( $geometry_samples ) ];
	}
}
$services_geometry_ok = count( $services_geometry_cases ) === 12;
foreach ( $services_geometry_cases as $geometry_case ) {
	$geometry_report = (array) ( $geometry_case['report'] ?? [] );
	$geometry_samples = (array) ( $geometry_report['recipe_layout']['geometry_samples'] ?? [] );
	$services_geometry_ok = $services_geometry_ok && ! empty( $geometry_report['ok'] ) && empty( $geometry_report['visual_render_verified'] ) && ( $geometry_report['recipe_layout']['visual_render_verified'] ?? true ) === false && (int) ( $geometry_report['recipe_layout']['item_count'] ?? 0 ) === (int) $geometry_case['items'] && count( $geometry_samples ) === 6 && ! in_array( true, array_column( $geometry_samples, 'overflow' ), true );
}
$photo_1200_sample = array_values( array_filter( (array) ( $services_recipe_layout['recipe_layout']['geometry_samples'] ?? [] ), static fn( array $sample ): bool => ( $sample['container_width_px'] ?? 0 ) === 1200 ) )[0] ?? [];
$split_1200_sample = array_values( array_filter( (array) ( $split_recipe_layout['recipe_layout']['geometry_samples'] ?? [] ), static fn( array $sample ): bool => ( $sample['container_width_px'] ?? 0 ) === 1200 ) )[0] ?? [];
$split_768_sample = array_values( array_filter( (array) ( $split_recipe_layout['recipe_layout']['geometry_samples'] ?? [] ), static fn( array $sample ): bool => ( $sample['container_width_px'] ?? 0 ) === 768 ) )[0] ?? [];
$expected_photo_cell_width = ( (float) ( $photo_1200_sample['available_content_width_px'] ?? 0 ) - 2 * (float) ( $photo_1200_sample['gap_px'] ?? 0 ) ) / 3;
$check( $services_geometry_ok && abs( (float) ( $photo_1200_sample['cell_width_px'] ?? 0 ) - $expected_photo_cell_width ) < 0.01 && ! array_key_exists( 'card_basis_percent', $photo_1200_sample ) && (float) ( $photo_1200_sample['gap_px'] ?? 0 ) > 0 && (float) ( $photo_1200_sample['outer_horizontal_padding_px'] ?? 0 ) === 32.0 && ( $photo_1200_sample['overflow'] ?? true ) === false && (float) ( $split_1200_sample['raw_copy_basis_width_px'] ?? 0 ) === 590.72 && (float) ( $split_1200_sample['raw_image_basis_width_px'] ?? 0 ) === 499.84 && (float) ( $split_1200_sample['gap_px'] ?? 0 ) === 32.0 && ( $split_1200_sample['overflow'] ?? true ) === false && ( $split_768_sample['axis'] ?? '' ) === 'column' && ( $split_768_sample['overflow'] ?? true ) === false, 'recipe geometry subtracts real collection gaps before dividing equal cells at 2/3/4/6 counts across 320–1200px containers; split proportions and stacked tablet remain separate' );

$missing_photo_recipe = $services_recipe_compile( $services_recipe_brief, 'services.photo_cards', [], 'services-recipe-missing-photo' );
$missing_split_plan = wpae_design_plan_from_brief( $services_recipe_brief, [ 'services_recipe_id' => 'services.split_editorial', 'services_lead_service_ref' => 'service_2' ] );
$unknown_recipe_plan = wpae_design_plan_from_brief( $services_recipe_brief, [ 'services_recipe_id' => 'services.unknown' ] );
$bad_reference_plan = $photo_recipe_result['plan'];
$bad_reference_plan['sections'][0]['children'][1]['items'][0]['title_ref'] = 'service_999_title';
$mismatched_asset = $services_recipe_assets[0];
$mismatched_asset['group_id'] = 'service_99';
$mismatched_media_plan = wpae_design_plan_from_brief( $services_recipe_brief, [ 'services_recipe_id' => 'services.photo_cards', 'media_references' => [ $mismatched_asset ] ] );
$bad_alt_asset = $services_recipe_assets[0];
$bad_alt_asset['alt'] = '';
$bad_alt_plan = wpae_design_plan_from_brief( $services_recipe_brief, [ 'services_recipe_id' => 'services.photo_cards', 'media_references' => [ $bad_alt_asset ] ] );
$duplicate_services_brief = $services_recipe_brief;
$duplicate_services_brief['groups'][1]['group_id'] = $duplicate_services_brief['groups'][0]['group_id'];
$duplicate_services_plan = wpae_design_plan_from_brief( $duplicate_services_brief, [ 'services_recipe_id' => 'services.text_icon_list' ] );
$required_media_services = $services_recipe_brief;
foreach ( $required_media_services['layout_constraints'] as &$constraint ) {
	if ( ( $constraint['kind'] ?? '' ) === 'media_intent' ) {
		$constraint['value'] = 'required';
	}
}
unset( $constraint );
$required_text_recipe = wpae_design_plan_from_brief( $required_media_services, [ 'services_recipe_id' => 'services.text_icon_list' ] );
$required_split_recipe = wpae_design_plan_from_brief( $required_media_services, [ 'services_recipe_id' => 'services.split_editorial', 'media_references' => array_slice( $services_recipe_assets, 0, 3 ) ] );
$missing_photo_validation = $missing_photo_recipe['plan_validation'];
$missing_split_validation = wpae_design_plan_validate( $missing_split_plan, $services_recipe_brief );
$unknown_recipe_validation = wpae_design_plan_validate( $unknown_recipe_plan, $services_recipe_brief );
$bad_reference_validation = wpae_design_plan_validate( $bad_reference_plan, $services_recipe_brief );
$mismatched_media_validation = wpae_design_plan_validate( $mismatched_media_plan, $services_recipe_brief );
$bad_alt_validation = wpae_design_plan_validate( $bad_alt_plan, $services_recipe_brief );
$duplicate_services_validation = wpae_design_plan_validate( $duplicate_services_plan, $duplicate_services_brief );
$required_text_validation = wpae_design_plan_validate( $required_text_recipe, $required_media_services );
$required_split_validation = wpae_design_plan_validate( $required_split_recipe, $required_media_services );
$forbidden_photo_validation = wpae_design_plan_validate( $forbidden_photo_plan, $forbidden_media_services );
$forbidden_split_validation = wpae_design_plan_validate( $forbidden_split_plan, $forbidden_media_services );
$check( empty( $missing_photo_validation['ok'] ) && in_array( 'services_recipe_service_1_photo_asset_required', $missing_photo_validation['errors'], true ), 'explicit photo recipe without required assets refuses before compile' );
$check( empty( $missing_split_validation['ok'] ) && in_array( 'services_recipe_split_editorial_lead_photo_required', $missing_split_validation['errors'], true ), 'split recipe without its required lead image refuses before compile' );
$check( empty( $unknown_recipe_validation['ok'] ) && in_array( 'services_recipe_unknown', $unknown_recipe_validation['errors'], true ), 'unknown recipe_id refuses validation' );
$check( empty( $bad_reference_validation['ok'] ), 'recipe refuses an unknown content slot reference' );
$check( empty( $mismatched_media_validation['ok'] ) && in_array( 'services_recipe_media_asset_group_unknown_service_99', $mismatched_media_validation['errors'], true ), 'recipe refuses an image bound to an unknown service group' );
$check( empty( $bad_alt_validation['ok'] ), 'photo recipe refuses an image without alt text' );
$check( empty( $duplicate_services_validation['ok'] ), 'recipe refuses duplicate service identities' );
$check( empty( $required_text_validation['ok'] ) && in_array( 'text_icon_list_incompatible_with_required_media', $required_text_validation['errors'], true ), 'text/icon recipe refuses when images are explicitly required' );
$check( empty( $required_split_validation['ok'] ) && in_array( 'required_media_assets_unconsumed', $required_split_validation['errors'], true ), 'split recipe refuses required supplied images left unconsumed' );
$check( empty( $forbidden_photo_validation['ok'] ) && in_array( 'photo_cards_media_intent_incompatible', $forbidden_photo_validation['errors'], true ), 'photo recipe refuses when images are explicitly forbidden' );
$check( empty( $forbidden_split_validation['ok'] ) && in_array( 'services_recipe_split_editorial_media_forbidden_or_conflicted', $forbidden_split_validation['errors'], true ), 'split recipe refuses when images are explicitly forbidden' );

$count_recipe_results = [];
foreach ( [ 1, 2, 3, 4, 6, 7 ] as $service_count ) {
	$count_prompt = "Блок услуг\n";
	for ( $service_index = 1; $service_index <= $service_count; $service_index++ ) {
		$count_prompt .= 'Услуга ' . $service_index . ' — название: «Услуга ' . $service_index . '»' . "\n";
		$count_prompt .= 'Услуга ' . $service_index . ' — описание: «Синтетическое описание услуги ' . $service_index . '»' . "\n";
	}
	$count_brief = wpae_brief_ir_parse( $count_prompt );
	$count_recipe_results[ $service_count ] = $services_recipe_compile( $count_brief, 'services.photo_cards', array_slice( $services_recipe_assets, 0, $service_count ), 'services-recipe-count-' . $service_count );
}
$count_recipe_checks = [];
foreach ( $count_recipe_results as $count => $result ) {
	$recipe_child = array_values( array_filter( (array) ( $result['plan']['sections'][0]['children'] ?? [] ), static fn( array $child ): bool => ( $child['role'] ?? '' ) === 'services_photo_grid' ) )[0] ?? [];
	$count_ok = in_array( (int) $count, [ 2, 3, 4, 6 ], true )
		? ( ! empty( $result['plan_validation']['ok'] ) && ! empty( $result['compiled']['ok'] ) && count( (array) ( $recipe_child['items'] ?? [] ) ) === (int) $count )
		: ( empty( $result['plan_validation']['ok'] ) && in_array( 'services_recipe_items_out_of_range_or_incomplete', $result['plan_validation']['errors'] ?? [], true ) );
	if ( in_array( (int) $count, [ 2, 3, 4, 6 ], true ) ) {
		$count_nodes = $walk_elements( (array) ( $result['compiled']['elementor_data'] ?? [] ) );
		$count_grid = array_values( array_filter( $count_nodes, static fn( array $node ): bool => ( $node['settings']['_css_classes'] ?? '' ) === 'wpae-services-photo-grid' ) )[0] ?? [];
		$count_cards = array_values( array_filter( (array) ( $count_grid['elements'] ?? [] ), static fn( array $node ): bool => ( $node['elType'] ?? '' ) === 'container' ) );
		$expected_implementation = 'native_grid';
		$count_ok = $count_ok && ( $result['plan']['visual_policy']['collection']['implementation'] ?? '' ) === $expected_implementation
			&& count( $count_cards ) === (int) $count
			&& ( $count_grid['settings']['container_type'] ?? '' ) === 'grid'
			&& (float) ( $count_cards[0]['settings']['_element_custom_width']['size'] ?? 0 ) === 100.0
			&& (float) ( $count_cards[0]['settings']['_element_custom_width_mobile']['size'] ?? 0 ) === 100.0
			&& ( $result['plan']['visual_policy']['collection']['columns']['desktop'] ?? 0 ) === ( (int) $count >= 4 ? 2 : (int) $count )
			&& ( $result['plan']['visual_policy']['collection']['columns']['tablet'] ?? 0 ) === 1
			&& ( $count_grid['settings']['grid_columns_grid']['unit'] ?? '' ) === 'fr'
			&& ( $count_grid['settings']['grid_columns_grid']['size'] ?? 0 ) === ( $result['plan']['visual_policy']['collection']['columns']['desktop'] ?? -1 )
			&& ( $count_grid['settings']['grid_columns_grid_tablet']['size'] ?? 0 ) === 1
			&& ( $count_grid['settings']['grid_columns_grid_mobile']['size'] ?? 0 ) === 1
			&& (float) ( $count_grid['settings']['grid_gaps']['column'] ?? 0 ) > 0;
		$count_layout = wpae_layout_report_for_plan( $result['plan'] );
		$count_ok = $count_ok && ( $count_layout['recipe_layout']['breakpoints'][0]['columns'] ?? 0 ) === ( (int) $count >= 4 ? 2 : (int) $count )
			&& ( $count_layout['recipe_layout']['breakpoints'][2]['columns'] ?? 0 ) === 1
			&& ( $count_layout['recipe_layout']['breakpoints'][3]['columns'] ?? 0 ) === 1
			&& ( $count_layout['recipe_layout']['card_content_padding_px']['desktop']['left'] ?? -1 ) === 0
			&& ( $count_layout['recipe_layout']['card_content_padding_px']['mobile']['top'] ?? 0 ) === 20;
	}
	$count_recipe_checks[] = $count_ok;
}
$check( ! in_array( false, $count_recipe_checks, true ), 'photo recipes accept two and six services and reject counts outside the supported 2–6 range' );

$wpae_test_readback_previous = $wpae_test_readback[ 5214 ] ?? null;
$wpae_test_readback_had_5214 = array_key_exists( 5214, $wpae_test_readback );
$wpae_test_readback[ 5214 ] = [];
$wpae_recipe_execute_mock = [ 'calls' => [], 'persistent_write_count' => 0 ];
$execute_recipe_checks = true;
foreach ( $services_recipe_results as $recipe_id => $result ) {
	$root = $result['compiled']['elementor_data'][0] ?? [];
	$execute_result = wpae_llm_execute_action( [ 'action' => 'insert_elements', 'post_id' => 5214, 'elements' => [ $root ] ], 5214, 'services', -1, '', false, [ 'operation_id' => 'services-recipe-contract', 'operation_identity' => 'services-recipe-contract', 'deterministic_ids' => true ] );
	$calls = (array) ( $wpae_recipe_execute_mock['calls'] ?? [] );
	$normalized_root = (array) ( $calls[0]['elementor_data'][0] ?? [] );
	$normalized_json = wp_json_encode( $normalized_root, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$has_recipe_topology = $recipe_id === 'services.photo_cards'
		? str_contains( $normalized_json, 'wpae-services-photo-grid' )
		: ( $recipe_id === 'services.split_editorial' ? str_contains( $normalized_json, 'wpae-services-split-lead' ) : str_contains( $normalized_json, 'wpae-services-text-icon-list' ) );
	$has_generic_service_cards = str_contains( $normalized_json, 'llm-services-grid' ) || str_contains( $normalized_json, 'wpae-service-cards' );
	$preview_step = array_values( array_filter( (array) ( $execute_result['steps'] ?? [] ), static fn( array $step ): bool => ( $step['id'] ?? '' ) === 'preview' ) )[0] ?? [];
	$execute_recipe_checks = $execute_recipe_checks && empty( $execute_result['ok'] ) && (int) ( $execute_result['status'] ?? 0 ) === 409 && ( $preview_step['status'] ?? '' ) === 'ok' && count( $calls ) === 2 && ! empty( $calls[0]['dry_run'] ) && empty( $calls[1]['dry_run'] ) && (int) ( $calls[0]['post_id'] ?? 0 ) === 5214 && (int) ( $calls[1]['post_id'] ?? 0 ) === 5214 && $has_recipe_topology && ! $has_generic_service_cards && (int) ( $wpae_recipe_execute_mock['persistent_write_count'] ?? -1 ) === 0;
	$wpae_recipe_execute_mock['calls'] = [];
}
if ( $wpae_test_readback_had_5214 ) {
	$wpae_test_readback[ 5214 ] = $wpae_test_readback_previous;
} else {
	unset( $wpae_test_readback[ 5214 ] );
}
$check( $execute_recipe_checks, 'all three recipe trees survive standard native/token/Bento execute normalization and dry-run preflight without collapsing into generic service cards; mocked final update refuses with write_count=0' );

if ( getenv( 'WPAE_SERVICES_RECIPE_DEMO' ) === '1' ) {
	$demo = [ 'brief' => $services_recipe_brief, 'results' => [] ];
	foreach ( $services_recipe_ids as $recipe_id ) {
		$result = $services_recipe_results[ $recipe_id ];
		$demo['results'][ $recipe_id ] = [ 'plan' => $result['plan'], 'plan_validation' => $result['plan_validation'], 'ir' => $result['ir'], 'ir_validation' => $result['ir_validation'], 'compiled_native_tree' => $result['compiled']['elementor_data'] ?? [], 'compile_ok' => ! empty( $result['compiled']['ok'] ) ];
	}
	fwrite( STDOUT, "services recipe demo (synthetic; no provider/WP calls):\n" . wp_json_encode( $demo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" );
}

// Canonical repeat geometry across typed families; historical recipe checks above remain compatibility evidence.
foreach ( [ 'pricing', 'team', 'services' ] as $family ) {
 foreach ( [ 2, 3, 4, 6 ] as $count ) {
  $prompt = ($family==='team' ? 'Блок команды' : ($family==='services' ? 'Секция услуг' : 'Создай ' . ucfirst($family))) . "\n";
  for($i=1;$i<=$count;$i++) {
   $prompt .= $family==='pricing' ? "«Тариф {$i}» — «100 ₸/мес» — «Точное описание {$i}»\nКнопка: «Выбрать {$i}», ссылка #tier-{$i}\n" : ($family==='team' ? "Участник {$i} — имя: «Имя {$i}»\nУчастник {$i} — должность: «Специалист {$i}»\n" : "Услуга {$i} — название: «Услуга {$i}»\nУслуга {$i} — описание: «Точное описание {$i}»\n");
  }
  $brief=wpae_brief_ir_parse($prompt);
  $ctx=['canonical_create'=>true];
  if($family==='services'){ $ctx['services_recipe_id']='services.text_icon_list'; }
  $plan=wpae_design_plan_from_brief($brief,$ctx);
  $valid=wpae_design_plan_validate($plan,$brief);
  $ir=wpae_elementor_ir_from_design_plan($plan,$brief);
  $compiled=wpae_elementor_ir_compile($ir,$brief,[],['resolved_visual'=>$plan['resolved_visual']??[]]);
  $check($valid['ok'] && !empty($compiled['ok']), 'Canonical count preserves complete typed family '.$family.' '.$count.': '.wp_json_encode($valid['errors']));
  $check(($plan['visual_policy']['collection']['columns']['tablet']??0)===1 && ($plan['visual_policy']['collection']['columns']['mobile']??0)===1,'Collection Plan responsive policy '.$family.' '.$count);
  if($family==='services'){
   $assets=[];
   for($i=1;$i<=$count;$i++){ $assets[]=array_replace($services_recipe_assets[($i-1)%3],['asset_id'=>'canonical-service-asset-'.$i,'group_id'=>'service_'.$i]); }
   $photo_plan=wpae_design_plan_from_brief($brief,['canonical_create'=>true,'services_recipe_id'=>'services.photo_cards','media_references'=>$assets]);
   $photo_ir=wpae_elementor_ir_from_design_plan($photo_plan,$brief);
   $photo_native=wpae_elementor_ir_compile($photo_ir,$brief,[],['resolved_visual'=>$photo_plan['resolved_visual']]);
   $photo_collections=array_values(array_filter($walk_elements($photo_native['elementor_data']??[]),static fn(array $n):bool=>($n['settings']['_css_classes']??'')==='wpae-services-photo-grid'));
	$photo_implementation='native_grid';
	$check(wpae_design_plan_validate($photo_plan,$brief)['ok'] && !empty($photo_native['ok']) && count($photo_collections)===1 && count($photo_collections[0]['elements'])===$count && ($photo_collections[0]['settings']['container_type']??'')==='grid' && ($photo_collections[0]['settings']['grid_columns_grid']['unit']??'')==='fr' && ($photo_collections[0]['settings']['grid_columns_grid']['size']??0)===($photo_plan['visual_policy']['collection']['columns']['desktop']??-1) && ($photo_plan['visual_policy']['collection']['implementation']??'')===$photo_implementation,'Canonical Services photo count uses accepted native equal Grid tracks with owned assets '.$count);
  }
  if($family!=='services'){
   $grids=array_values(array_filter($walk_elements($compiled['elementor_data']),static fn(array $n):bool=>($n['settings']['container_type']??'')==='grid'));
   $check(count($grids)===1 && count($grids[0]['elements'])===$count,'Native Grid cardinality '.$family.' '.$count);
   $collection=$plan['visual_policy']['collection'];$grid_settings=$grids[0]['settings'];
   $check(($collection['implementation']??'')==='native_grid' && ($grid_settings['grid_columns_grid']['size']??0)===($collection['columns']['desktop']??-1) && ($grid_settings['grid_columns_grid_tablet']['size']??0)===($collection['columns']['tablet']??-1) && ($grid_settings['grid_columns_grid_mobile']['size']??0)===($collection['columns']['mobile']??-1) && !isset($grid_settings['flex_direction']) && !isset($grid_settings['flex_direction_tablet']),'Repeated '.$family.' native tracks and responsive controls match the accepted Plan '.$count);
   $normalized_grid=(array)(wpae_elementor_normalize_data([$grids[0]])['data'][0]??[]);$normalized_grid_settings=(array)($normalized_grid['settings']??[]);
   $check(($normalized_grid_settings['container_type']??'')==='grid' && ($normalized_grid_settings['grid_columns_grid']['size']??0)===($collection['columns']['desktop']??-1) && !isset($normalized_grid_settings['flex_direction']) && !isset($normalized_grid_settings['flex_gap']),'Accepted native Grid normalization preserves its frozen geometry without injecting Flex defaults '.$family.' '.$count);
   if($family==='pricing'){
	    $price_group=$grids[0]['elements'][0]['elements'][0]['elements'][0]['elements'][1]??[];
	    $inline=$plan['visual_policy']['inline_value'];
    foreach(['desktop'=>'','tablet'=>'_tablet','mobile'=>'_mobile'] as $device=>$suffix){
     $s=$price_group['settings']??[];
     $check(($s['flex_direction'.$suffix]??'')===$inline['direction'][$device] && ($s['flex_justify_content'.$suffix]??'')===$inline['main_align'] && ($s['flex_align_items'.$suffix]??'')===$inline['cross_align'] && ($s['flex_wrap'.$suffix]??'')===$inline['wrap'],'Price/period native responsive controls follow accepted inline Plan '.$count.' '.$device);
    }
    if($count===2){
     $changed=$plan;$changed['visual_policy']['inline_value']['direction']['mobile']='column';$changed['visual_policy']['inline_value']['cross_align']='flex-start';
     $changed_ir=wpae_elementor_ir_from_design_plan($changed,$brief);
     $changed_native=wpae_elementor_ir_compile($changed_ir,$brief,[],['resolved_visual'=>$changed['resolved_visual']]);
     $changed_grids=array_values(array_filter($walk_elements($changed_native['elementor_data']),static fn(array $n):bool=>($n['settings']['container_type']??'')==='grid'));
	    $changed_group=$changed_grids[0]['elements'][0]['elements'][0]['elements'][0]['elements'][1]['settings'];
     $check($changed_group['flex_direction_mobile']==='column' && $changed_group['flex_align_items_mobile']==='flex-start','Compiler translates accepted alternate inline policy without reselecting it');
     $old=$plan;unset($old['visual_policy']['inline_value']);
     $old_ir=wpae_elementor_ir_from_design_plan($old,$brief);$old_native=wpae_elementor_ir_compile($old_ir,$brief,[],['resolved_visual'=>$old['resolved_visual']]);
     $old_grids=array_values(array_filter($walk_elements($old_native['elementor_data']),static fn(array $n):bool=>($n['settings']['container_type']??'')==='grid'));
	    $old_group=$old_grids[0]['elements'][0]['elements'][0]['elements'][0]['elements'][1]['settings'];
     $check(wpae_design_plan_validate($old,$brief)['ok'] && $old_group['flex_direction_mobile']==='column','Historical frozen Plan without inline policy retains its existing compiler behavior');
     $bad=$plan;$bad['visual_policy']['inline_value']['direction']['mobile']='unexpected';
     $check(in_array('visual_policy_inline_direction_invalid',wpae_design_plan_validate($bad,$brief)['errors'],true),'Invalid inline responsive decision refuses before write');
    }
   }
   $report=wpae_layout_report_for_plan($plan);
   $check(count($report['collections'])===1 && !$report['visual_render_verified'],'Nested group static report '.$family.' '.$count);
  }
 }
}

// M3.1: canonical entity groups and real grid/row topology over identical exact copy.
foreach ( [ 'team', 'testimonials' ] as $family ) {
 foreach ( [ 2, 3, 4, 6 ] as $count ) {
  $prompt = 'Создай блок ' . ( $family === 'team' ? 'команды' : 'отзывов' ) . '. Без фото. Надзаголовок «ПРОВЕРКА». Заголовок «Тестовая секция». Описание «Точное описание всей секции». ';
  for ( $i=1; $i<=$count; $i++ ) {
   $prompt .= $family === 'team' ? 'Участник '.$i.' имя «Имя '.$i.'». Участник '.$i.' должность «Роль '.$i.'». Участник '.$i.' биография «'.($i%2?'Коротко.':rtrim(str_repeat('Длинное точное описание. ',8))).'». ' : 'Отзыв '.$i.' текст «'.($i%2?'Точная цитата.':rtrim(str_repeat('Длинная точная цитата. ',8))).'». Отзыв '.$i.' автор «Автор '.$i.'». Отзыв '.$i.' компания «Компания '.$i.'». ';
  }
  $brief=wpae_brief_ir_parse($prompt); $brief['canonical_create']=true;
  if (!wpae_brief_ir_validate($brief)['ok'] || count($brief['groups'])!==$count) { fwrite(STDERR,wp_json_encode(['intent'=>$brief['intent'],'groups'=>$brief['groups'],'validation'=>wpae_brief_ir_validate($brief)],JSON_UNESCAPED_UNICODE).'\n'); }
  $check(wpae_brief_ir_validate($brief)['ok'] && count($brief['groups'])===$count,'Canonical entity intake '.$family.' '.$count);
  $native_variants=[];
  foreach(['grid','editorial_rows'] as $variant) {
   $plan=wpae_design_plan_from_brief($brief,['canonical_create'=>true,'composition_record'=>$family.'.'.$variant,'visual_profile'=>'editorial_light']);
   $validation=wpae_design_plan_validate($plan,$brief);
   $check($validation['ok'],'Entity Plan '.$family.' '.$count.' '.$variant.' '.implode(',',$validation['errors']));
   $check($plan['sections'][0]['children'][1]['items']===$brief['groups'],'Frozen groups consumed unchanged');
   $ir=wpae_elementor_ir_from_design_plan($plan,$brief);
   $native=wpae_elementor_ir_compile($ir,$brief,[],['resolved_visual'=>$plan['resolved_visual']]);
   $check($native['ok'],'Entity native compiler '.$family.' '.$variant);
   $flat=$walk_elements($native['elementor_data']);
   $titles=array_values(array_filter($flat,static fn(array $n):bool=>($n['settings']['title']??'')==='Тестовая секция'));
   $check(($titles[0]['settings']['header_size']??'')==='h2','Entity intro H2');
   if($variant==='editorial_rows'){
    $expected_copy_measure=(string)$plan['visual_policy']['entity_layout']['tracks']['copy_measure'];
    $copy_measures=array_values(array_filter($flat,static fn(array $n):bool=>($n['settings']['width']['unit']??'')==='custom'&&str_contains((string)($n['settings']['width']['size']??''),$expected_copy_measure)));
    $copy_measure_gaps=array_values(array_filter($copy_measures,static fn(array $n):bool=>($n['settings']['flex_gap']['unit']??'')==='rem'&&(float)($n['settings']['flex_gap']['size']??0)===0.75));
    $expected_copy_percent=(int)$plan['visual_policy']['entity_layout']['tracks']['copy_percent'];
    $copy_tracks=array_values(array_filter($flat,static fn(array $n):bool=>($n['settings']['width']['unit']??'')==='%'&&($n['settings']['width']['size']??0)===$expected_copy_percent));
    $check(count($copy_measures)>0&&count($copy_measure_gaps)>0&&count($copy_tracks)>0,'Editorial entity rows preserve copy track, reading measure, and authored item rhythm '.$family);
   }
   $encoded=wp_json_encode($native['elementor_data'],JSON_UNESCAPED_UNICODE);
   foreach($brief['content'] as $slot) {$check(str_contains($encoded,$slot['exact_text']),'Exact entity slot '.$slot['id']);}
   $bad=$plan; $bad['sections'][0]['children'][1]['items'][0][$family==='team'?'name_ref':'quote_ref']=$brief['groups'][1][$family==='team'?'name_ref':'quote_ref'];
   $check(!wpae_design_plan_validate($bad,$brief)['ok'],'Cross-owner/frozen binding refuses');
   $required=$brief;$required['layout_constraints']=array_values(array_filter($required['layout_constraints'],static fn(array $c):bool=>($c['kind']??'')!=='media_intent'));$required['layout_constraints'][]=['kind'=>'media_intent','value'=>'required'];
   $required_plan=wpae_design_plan_from_brief($required,['canonical_create'=>true,'composition_record'=>$family.'.'.$variant]);
   $check(!wpae_design_plan_validate($required_plan,$required)['ok'],'Required entity portrait missing refuses');
   $bad=$plan;$bad['visual_policy']['entity_layout']['columns']['mobile']=2;
   $check(!wpae_design_plan_validate($bad,$brief)['ok'],'Entity responsive policy cannot diverge from record');
   $unknown=wpae_design_plan_from_brief($brief,['canonical_create'=>true,'composition_record'=>'unknown.entity.record']);
   $check(!wpae_design_plan_validate($unknown,$brief)['ok'],'Unknown entity record refuses');
   $unknown_profile=wpae_design_plan_from_brief($brief,['canonical_create'=>true,'composition_record'=>$family.'.'.$variant,'visual_profile'=>'unknown']);
   $check(!wpae_design_plan_validate($unknown_profile,$brief)['ok'],'Unknown entity profile refuses');
   $images=array_values(array_filter($flat,static fn(array $n):bool=>($n['widgetType']??'')==='image'));
   $check(!$images,'Forbidden entity photos are not invented');
   $native_variants[$variant]=$native['elementor_data'];
  }
  $check($native_variants['grid']!==$native_variants['editorial_rows'],'Distinct entity structures');
 }
}

// Authorized media is validated by entity slot, never globally enabled for all families.
foreach ( [ 'team', 'testimonials' ] as $family ) {
 $prompt=$family==='team'?'Блок команды. Участник 1 имя «Имя». Участник 1 должность «Роль». Участник 1 биография «Био». Участник 1 ссылка «Связаться», ссылка #person.':'Блок отзывов. Отзыв 1 текст «Цитата». Отзыв 1 автор «Автор». Отзыв 1 компания «Компания». Отзыв 1 рейтинг «4 из 5». Отзыв 1 ссылка «Источник», ссылка #quote.';
 $brief=wpae_brief_ir_parse($prompt);$brief['canonical_create']=true;
 $asset=['asset_id'=>'entity_fixture_asset','source_url'=>'https://example.com/fixture/portrait.jpg','attachment_id'=>null,'role'=>'portrait','group_id'=>$brief['groups'][0]['group_id'],'alt'=>'Synthetic portrait fixture','license'=>'Contract fixture','attribution'=>'Contract fixture','allowed_reuse'=>true,'provenance'=>['source'=>'synthetic_test_fixture']];
 $brief['media_references']=[$asset];$brief['groups']=wpae_brief_ir_entity_groups($family,$brief['content'],$brief['media_references']);
 foreach(['grid','editorial_rows'] as $variant){
  $ctx=['canonical_create'=>true,'composition_record'=>$family.'.'.$variant];$plan=wpae_design_plan_from_brief($brief,$ctx);
  $v=wpae_design_plan_validate($plan,$brief);$check($v['ok'],'Owned authorized media and action '.$family.' '.implode(',',$v['errors']));
  $native=wpae_elementor_ir_compile(wpae_elementor_ir_from_design_plan($plan,$brief),$brief,[],['resolved_visual'=>$plan['resolved_visual']]);
  $nodes=$walk_elements($native['elementor_data']);$images=array_values(array_filter($nodes,static fn(array $n):bool=>($n['widgetType']??'')==='image'));
  $check(count($images)===1 && $images[0]['settings']['image']['url']===$asset['source_url'],'Owned portrait survives native serialization');
  $buttons=array_values(array_filter($nodes,static fn(array $n):bool=>($n['widgetType']??'')==='button'));
  $check(count($buttons)===1 && $buttons[0]['settings']['link']['url']===($family==='team'?'#person':'#quote'),'Owned entity action survives');
  foreach(['owner','role','forbidden','required','license'] as $fault){
   $bad=$brief;
   if($fault==='owner'){$bad['media_references'][0]['group_id']='other_1';}
   elseif($fault==='role'){$bad['media_references'][0]['role']='decorative';}
   elseif($fault==='license'){$bad['media_references'][0]['allowed_reuse']=false;}
   elseif($fault==='required'){$bad['media_references']=[];unset($bad['groups'][0]['media_ref']);$bad['layout_constraints'][]=['kind'=>'media_intent','value'=>'required'];}
   else{$bad['layout_constraints'][]=['kind'=>'media_intent','value'=>'forbidden'];}
   // Replace unspecified policy, so the explicit test intent is the sole decision.
   if(in_array($fault,['required','forbidden'],true)){$bad['layout_constraints']=array_values(array_filter($bad['layout_constraints'],static fn(array $c):bool=>($c['kind']??'')!=='media_intent'||($c['value']??'')===$fault));}
   $badplan=wpae_design_plan_from_brief($bad,$ctx);$check(!wpae_design_plan_validate($badplan,$bad)['ok'],'Entity media refusal '.$family.' '.$fault);
  }
 }
}

// Saved live fixtures use the same deterministic grammar before any production write.
foreach ( [ ['A-B-team-exact-request.txt','team.grid'], ['C-D-testimonials-exact-request.txt','testimonials.grid'], ['E-services-exact-request.txt','services.photo_cards'], ['F-pricing-exact-request.txt','pricing.tiers'] ] as [$fixture,$record] ) {
 $prompt=file_get_contents(dirname(__DIR__).'/docs/audits/2026-10-05-m3-1-entities/'.$fixture);
 $brief=wpae_brief_ir_parse($prompt);if($brief['intent']['archetype']!=='services'){$brief['canonical_create']=true;}
 $ctx=['canonical_create'=>true]; if(str_starts_with($record,'services.')){$ctx['services_recipe_id']=$record;}else{$ctx['composition_record']=$record;}
 $plan=wpae_design_plan_from_brief($brief,$ctx);$v=wpae_design_plan_validate($plan,$brief);
 $check(wpae_brief_ir_validate($brief)['ok'] && $v['ok'],'Saved fixture validates '.$fixture.' '.implode(',',$v['errors']));
 $ir=wpae_elementor_ir_from_design_plan($plan,$brief);$native=wpae_elementor_ir_compile($ir,$brief,[],['resolved_visual'=>$plan['resolved_visual']]);
 $check($native['ok'],'Saved fixture native compiler '.$fixture);
	if($record==='services.photo_cards'){
	  $svc_flat=$walk_elements($native['elementor_data']);
	  $service_card_nodes=array_values(array_filter($svc_flat,static fn(array $n):bool=>($n['settings']['_css_classes']??'')==='wpae-services-photo-card'));
  $check(count($service_card_nodes)===3 && array_reduce($service_card_nodes,static fn(bool $ok,array $n):bool=>$ok && ($n['settings']['flex_justify_content']??'')==='space-between' && ($n['settings']['flex_justify_content_mobile']??'')==='flex-start',true),'Canonical Services cards distribute free space before actions on desktop and return to content flow on mobile');
  $check(array_reduce($service_card_nodes,static fn(bool $ok,array $n):bool=>$ok && count((array)($n['elements'][1]['elements']??[]))>0,true),'Canonical Services actions remain in a separate nonempty native footer');
  $svc_flat=$walk_elements($native['elementor_data']);$badge=array_values(array_filter($svc_flat,static fn(array $n):bool=>($n['settings']['_css_classes']??'')==='wpae-generated-badge'))[0]??[];
  $label=array_values(array_filter($svc_flat,static fn(array $n):bool=>($n['settings']['_css_classes']??'')==='wpae-generated-badge-label'))[0]??[];
  $check(($plan['visual_policy']['eyebrow_colors']['pill']??'')===$plan['resolved_visual']['values']['color.text'] && ($badge['settings']['background_color']??'')===$plan['visual_policy']['eyebrow_colors']['pill_background'] && ($badge['settings']['border_color']??'')===$plan['visual_policy']['eyebrow_colors']['pill_border'] && ($label['settings']['title_color']??'')===$plan['visual_policy']['eyebrow_colors']['pill'],'Services pill uses coordinated accepted text, background and border colours');
 }
 if($record==='pricing.tiers'){
  $check(count($brief['pricing_items'])===3 && array_map(static fn(array $g):int=>count($g['feature_refs']),$brief['pricing_items'])===[1,4,2],'Pricing unequal features retained');
  $flat=$walk_elements($native['elementor_data']);
  $check(count(array_filter($flat,static fn(array $n):bool=>($n['settings']['editor']??'')==='Три синтетических пакета для проверки точного содержания и разной длины списка возможностей'))===1,'Pricing full intro body retained');
 }
}
fwrite( STDOUT, "design pipeline contract: {$checks} checks OK\n" );
