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
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params;
		public function __construct( array $params = [] ) { $this->params = $params; }
		public function get_param( string $name ) { return $this->params[ $name ] ?? null; }
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
require_once __DIR__ . '/../includes/llm/routing.php';

$checks = 0;
$check = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	$checks++;
};

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

$hero_prompt = "hero\neyebrow: «Запуск без лишних шагов»\ntitle: «Соберите сильную страницу\nза один день»\nbody: «Понятный процесс для команды.»\nCTA: «Начать проект» -> https://example.com/start\nCTA: «Узнать больше» -> #about\nImage: https://example.com/contract.png\nFAQ\nО нас\n7 шагов";
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
$check( ( $forbidden_ru['layout_constraints'][0]['value'] ?? '' ) === 'forbidden' && ( $forbidden_en['layout_constraints'][0]['value'] ?? '' ) === 'forbidden', 'Russian and English image prohibition becomes an explicit BriefIR constraint' );
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
$check( ( $required_missing['layout_constraints'][0]['value'] ?? '' ) === 'required' && ! wpae_design_plan_validate( $required_missing_plan )['ok'] && in_array( 'required_media_asset_missing', wpae_design_plan_validate( $required_missing_plan )['errors'], true ), 'required image without a usable asset is rejected before compilation' );
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
$check( ( $contradictory['layout_constraints'][0]['value'] ?? '' ) === 'conflict' && empty( $contradictory_validation['ok'] ) && in_array( 'media_intent_conflict', $contradictory_validation['errors'], true ), 'conflicting image requirements are visible and rejected' );
$forbidden_with_asset = wpae_brief_ir_parse( 'Create a hero with no image, but keep this supplied image URL: https://example.com/hero.jpg' );
$check( ( $forbidden_with_asset['layout_constraints'][0]['value'] ?? '' ) === 'conflict', 'explicit prohibition and an image asset URL are treated as a visible conflict' );

$semantic_hero = wpae_brief_ir_parse( 'Добавь новую hero-секцию для архитектурной студии «Тихая форма». Надзаголовок «АРХИТЕКТУРА». Заголовок «Пространство для идей». Описание «Опишите задачу и получите понятный первый шаг». Основная кнопка «Начать проект», ссылка #contact. Вторичная кнопка «Смотреть проекты», ссылка #projects. Текст слева занимает 40%, визуальная часть справа — 60%. Изображение: https://example.com/hero.png' );
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
$check( (float) ( $semantic_compiled['elementor_data'][0]['settings']['padding']['size'] ?? 0 ) === 4.5 && (float) ( $semantic_compiled['elementor_data'][0]['settings']['padding_mobile']['size'] ?? 0 ) === 2.0, 'section spacing tokens become native desktop/mobile padding settings' );
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
$check( $plan['sections'][0]['children'][0]['allowed_widgets'] === [ 'heading', 'text-editor', 'button' ], 'hero plan restricts widgets' );
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
$pricing_card_details = (array) ( $pricing_card_nodes[0]['elements'][0] ?? [] );
$pricing_price_group = (array) ( $pricing_card_details['elements'][1] ?? [] );
$check( ( $pricing_cards['settings']['flex_align_items'] ?? '' ) === 'stretch' && ( $pricing_card_settings['flex_justify_content'] ?? '' ) === 'space-between' && ( $pricing_card_settings['flex_justify_content_mobile'] ?? '' ) === 'flex-start' && ! isset( $pricing_card_settings['height'], $pricing_card_settings['min_height'] ), 'pricing uses native flex to equalize row cards while preserving natural mobile card height' );
$check( ( $pricing_price_group['settings']['flex_direction'] ?? '' ) === 'row' && count( $pricing_price_group['elements'] ?? [] ) === 1, 'pricing amount and optional period share a compact native row when period is absent' );
$pricing_live_brief = wpae_brief_ir_parse( 'Надзаголовок: «ТАРИФЫ». Заголовок: «Выберите формат работы». «Старт» — «от 50 000 ₸» — «Для небольшой задачи». Кнопка: «Выбрать тариф», ссылка #start. «Проект» — «от 150 000 ₸/мес» — «Для комплексной работы с несколькими этапами, согласованием материалов и поддержкой команды на протяжении всего проекта». Кнопка: «Выбрать тариф», ссылка #project. «Поддержка» — «от 80 000 ₸/год» — «Для регулярного сопровождения». Кнопка: «Выбрать тариф», ссылка #support.' );
$pricing_live_ir = wpae_elementor_ir_from_design_plan( wpae_design_plan_from_brief( $pricing_live_brief ), $pricing_live_brief );
$pricing_live_compiled = wpae_elementor_ir_compile( $pricing_live_ir, $pricing_live_brief, [], [ 'id_seed' => 'pricing-live-contract' ] );
$pricing_live_root = $pricing_live_compiled['elementor_data'][0] ?? [];
$pricing_live_group = $pricing_live_root['elements'][1] ?? [];
$pricing_live_cards = (array) ( $pricing_live_group['elements'] ?? [] );
$pricing_live_card_settings = (array) ( $pricing_live_cards[0]['settings'] ?? [] );
$check( count( $pricing_live_cards[0]['elements'] ?? [] ) === 2 && ( $pricing_live_cards[0]['elements'][1]['widgetType'] ?? '' ) === 'button' && ( $pricing_live_card_settings['flex_justify_content'] ?? '' ) === 'space-between', 'pricing CTA remains the final native flex child and aligns to the common card baseline on desktop' );
$pricing_live_eyebrow = (array) ( $pricing_live_root['elements'][0]['elements'][0] ?? [] );
$pricing_live_rows = array_map( static function ( array $card ): array {
	$details = (array) ( $card['elements'][0]['elements'] ?? [] );
	$price_widgets = (array) ( $details[1]['elements'] ?? [] );
	$button = (array) ( $card['elements'][1] ?? [] );
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
$check( $faq_brief['parser_version'] === 'wpae-brief-parser-v2', 'BriefIR provenance version tracks the expanded native section roles' );
$check( wpae_design_plan_validate( $faq_plan )['ok'] && ! empty( $faq_compiled['ok'] ) && ( $faq_widget['widgetType'] ?? '' ) === 'accordion', 'FAQ uses the existing typed pipeline and compiles to Elementor Accordion' );
$check( ( $faq_compiled['elementor_data'][0]['elements'][0]['elements'][0]['settings']['title'] ?? '' ) === 'FAQ', 'FAQ preserves the short category label in an editable heading' );
$check( array_column( $faq_tabs, 'tab_title' ) === [ 'Как проходит работа?', 'Можно ли изменить содержание?' ] && array_column( $faq_tabs, 'tab_content' ) === [ 'Сначала согласуем задачу, затем соберём страницу.', 'Да, каждый текст остаётся редактируемым.' ], 'Accordion preserves exact questions and answers in source order' );
$check( count( array_unique( array_column( $faq_tabs, '_id' ) ) ) === 2 && ( $faq_widget['settings']['selected_icon']['value'] ?? '' ) === 'fas fa-angle-down', 'Accordion tabs receive unique stable IDs and native toggle icon settings' );
$check( ( $faq_compiled['elementor_data'][0]['settings']['background_color'] ?? '' ) === '#f6f0e6', 'FAQ white surface stays scoped to the rounded inner surface instead of flattening the entire page section' );
$check( ( $faq_surface['settings']['background_color'] ?? '' ) === '#ffffff' && ( $faq_surface['settings']['border_radius']['unit'] ?? '' ) === 'px' && (float) ( $faq_surface['settings']['border_radius']['size'] ?? 0 ) === 12.0 && ( $faq_surface['settings']['border_border'] ?? '' ) === 'solid' && (float) ( $faq_surface['settings']['border_width']['size'] ?? 0 ) === 1.0, 'explicit FAQ white surface, light border and 12px radius compile onto the native container' );
$check( ( $faq_widget['settings']['title_background'] ?? '' ) === '#ffffff' && ( $faq_widget['settings']['content_background_color'] ?? '' ) === '#ffffff' && (float) ( $faq_widget['settings']['border_width']['size'] ?? -1 ) === 0.0 && ( $faq_widget['settings']['border_color'] ?? '' ) !== '' && str_contains( (string) ( $faq_widget['settings']['custom_css'] ?? '' ), 'border-bottom: 1px solid' ), 'native Accordion drops its duplicate frame but retains token-backed separators inside the outer card' );
$check( ( $faq_widget['settings']['title_color'] ?? '' ) === '#111827' && ( $faq_widget['settings']['title_active_color'] ?? '' ) === '#111827' && str_contains( (string) ( $faq_widget['settings']['custom_css'] ?? '' ), ':hover' ) && str_contains( (string) ( $faq_widget['settings']['custom_css'] ?? '' ), ':focus-visible' ) && str_contains( (string) ( $faq_widget['settings']['custom_css'] ?? '' ), '#2563eb' ), 'Accordion hover, active and keyboard focus colors use semantic text and focus tokens' );
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
$check( ! isset( $benefits_group['settings']['background_color'] ) && ( $benefits_cards[0]['settings']['background_color'] ?? '' ) === '#ffffff', 'benefits gap stays transparent while each card keeps its own white surface' );
$check( ( $benefits_cards[0]['settings']['flex_align_items'] ?? '' ) === 'stretch' && ( $benefits_cards[0]['elements'][0]['settings']['align'] ?? '' ) === 'left' && ( $benefits_cards[0]['elements'][1]['settings']['align'] ?? '' ) === 'left' && ( $benefits_cards[0]['elements'][2]['settings']['align'] ?? '' ) === 'left', 'benefit icon, heading and body align left inside full-width card content' );
$check( ( $benefits_group['settings']['flex_direction_mobile'] ?? '' ) === 'column' && (float) ( $benefits_cards[0]['settings']['width_mobile']['size'] ?? 0 ) === 100.0, 'feature cards stack to full width at mobile breakpoint' );
$two_benefits_prompt = "Features\nFeature 1: \"Clear scope\"\nFeature description 1: \"" . str_repeat( 'The written scope keeps each approval visible. ', 5 ) . "\"\nFeature 2: \"Native editing\"\nFeature description 2: \"Text stays editable in Elementor.\"";
$two_benefits_brief = wpae_brief_ir_parse( $two_benefits_prompt );
$two_benefits_plan = wpae_design_plan_from_brief( $two_benefits_brief );
$two_benefits_tree = wpae_native_elementor_compile( wpae_elementor_ir_from_design_plan( $two_benefits_plan, $two_benefits_brief ), $two_benefits_brief, [], [ 'id_seed' => 'two-benefits-long-copy' ] );
$two_benefits_group = [];
foreach ( (array) ( $two_benefits_tree['elementor_data'][0]['elements'] ?? [] ) as $compiled_child ) {
	if ( ( $compiled_child['settings']['_css_classes'] ?? '' ) === 'wpae-feature-cards' ) {
		$two_benefits_group = $compiled_child;
		break;
	}
}
$check( count( (array) ( $two_benefits_group['elements'] ?? [] ) ) === 2 && ( $two_benefits_group['elements'][0]['elements'][2]['settings']['editor'] ?? '' ) === trim( str_repeat( 'The written scope keeps each approval visible. ', 5 ) ), 'feature compiler adapts to two cards and preserves long exact copy' );
$check( ( $two_benefits_group['settings']['flex_direction'] ?? '' ) === 'row' && (float) ( $two_benefits_group['elements'][0]['settings']['width']['size'] ?? 0 ) === 48.0 && (float) ( $two_benefits_group['elements'][1]['settings']['width']['size'] ?? 0 ) === 48.0, 'two benefits cards keep a desktop row with gap-safe native widths' );
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
$editor_tree = [ [ 'id' => 'cd4da23', 'elType' => 'container' ] ];
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
$check( $stale_editor_candidate['operation_id'] === 'wpae-current-root-stale' && empty( $stale_editor_candidate['reviewable'] ), 'when no operation is current, editor config prefers the stale operation whose owned root still exists for diagnostics' );
$check( $stale_editor_candidate['target_status']['expected_fingerprint'] === 'expected-page-fingerprint' && $stale_editor_candidate['target_status']['current_fingerprint'] === 'current-target-fingerprint', 'stale present-root candidate exposes both fingerprints without becoming reviewable' );
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
	[ 'off', 'off', 'provider', 1, 1 ],
	[ 'off', 'active', 'edde', 0, 1 ],
	[ 'shadow', 'active', 'edde', 0, 1 ],
	[ 'active', 'active', 'pipeline', 0, 1 ],
];
foreach ( $matrix as $entry ) {
	$decision = wpae_design_generation_route( $entry[0], $entry[1], true, true );
	$check( $decision['action_path'] === $entry[2] && $decision['provider_calls'] === $entry[3] && $decision['writes'] === $entry[4], 'feature flag route matrix ' . $entry[0] . '/' . $entry[1] );
}

fwrite( STDOUT, "design pipeline contract: {$checks} checks OK\n" );
