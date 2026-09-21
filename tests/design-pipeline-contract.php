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
	function apply_filters( $tag, $value ) {
		return $value;
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

require_once __DIR__ . '/../includes/design/token-resolution.php';
require_once __DIR__ . '/../includes/elementor/capability-registry.php';
require_once __DIR__ . '/../includes/elementor/reference-set.php';
require_once __DIR__ . '/../includes/llm/brief-ir.php';
require_once __DIR__ . '/../includes/llm/design-plan.php';
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

$hero_prompt = "hero\neyebrow: «Запуск без лишних шагов»\ntitle: «Соберите сильную страницу\nза один день»\nbody: «Понятный процесс для команды.»\nCTA: «Начать проект» -> https://example.com/start\nCTA: «Узнать больше» -> #about\nFAQ\nО нас\n7 шагов";
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
$check( $compiled['elementor_data'][0]['elements'][0]['settings']['flex_basis']['size'] === 60.0 && $compiled['elementor_data'][0]['elements'][1]['settings']['flex_basis_mobile']['size'] === 100, 'compiler applies split and mobile basis' );

$process = wpae_brief_ir_parse( "process\n«Step one»\n«Step two»\n«Step three»" );
$process_plan = wpae_design_plan_from_brief( $process );
$process_ir = wpae_elementor_ir_from_design_plan( $process_plan, $process );
$process_children = $process_ir['nodes'][0]['children'][0]['children'] ?? [];
$check( count( $process_children ) === 5, 'process connector count is n-1' );
$pricing = wpae_brief_ir_parse( 'pricing: «Basic» «Pro» «Team»' );
$pricing_plan = wpae_design_plan_from_brief( $pricing );
$check( $pricing_plan['archetype'] === 'pricing' && wpae_design_plan_validate( $pricing_plan )['ok'], 'pricing typed plan validates' );

$unknown = wpae_widget_capability_resolve( 'imaginary-widget' );
$check( $unknown['downgraded'] && $unknown['widget_type'] === 'text-editor', 'unavailable widget has native fallback' );
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
$held_lock = wpae_design_operation_acquire_lock();
$contended = wpae_design_operation_create( [ 'operation_id' => 'op-contended', 'idempotency_key' => 'contended-key', 'post_id' => 5214 ] );
$check( $held_lock !== null && ! empty( $contended['lock_conflict'] ), 'concurrent operation capture is rejected by the atomic option lock' );
wpae_design_operation_release_lock( $held_lock );
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
