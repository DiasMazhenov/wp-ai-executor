<?php

/** Policy metadata for provider routing; transport remains the single HTTP path. */

defined( 'ABSPATH' ) || exit;

const WPAE_LLM_ROUTE_SCHEMA = 'wpae-llm-route-v1';

function wpae_llm_route_policy( string $operation_type = 'draft', string $provider = '', string $model = '' ): array {
	$operation_type = sanitize_key( $operation_type );
	$critical = in_array( $operation_type, [ 'design_plan', 'elementor_write', 'repair' ], true );
	$route = $critical ? 'reliable_structured' : 'cheap_structured';
	$policy = [
		'schema' => WPAE_LLM_ROUTE_SCHEMA,
		'operation_type' => $operation_type,
		'provider' => sanitize_key( $provider ),
		'model' => sanitize_text_field( $model ),
		'route' => $route,
		'critical_write' => $critical,
		'requires_structured_output' => true,
		'retry_budget' => $critical ? 1 : 0,
		'max_repair_passes' => $operation_type === 'repair' ? 2 : 0,
		'health_check' => $critical,
		'circuit_breaker' => $critical,
		'per_operation_budget_ms' => $critical ? 30000 : 12000,
		'fallback_allowed' => ! $critical,
	];
	if ( function_exists( 'apply_filters' ) ) {
		$policy = apply_filters( 'wpae_llm_route_policy', $policy, $operation_type, $provider, $model );
	}
	return is_array( $policy ) ? $policy : [];
}

function wpae_llm_route_diagnostics( array $policy, array $telemetry = [] ): array {
	return [
		'schema' => WPAE_LLM_ROUTE_SCHEMA,
		'provider' => sanitize_key( (string) ( $telemetry['provider'] ?? $policy['provider'] ?? '' ) ),
		'model' => sanitize_text_field( (string) ( $telemetry['model'] ?? $policy['model'] ?? '' ) ),
		'route' => sanitize_key( (string) ( $policy['route'] ?? 'unknown' ) ),
		'input_tokens' => max( 0, (int) ( $telemetry['input_tokens'] ?? 0 ) ),
		'output_tokens' => max( 0, (int) ( $telemetry['output_tokens'] ?? 0 ) ),
		'latency_ms' => max( 0, (int) ( $telemetry['latency_ms'] ?? 0 ) ),
		'retry_count' => max( 0, (int) ( $telemetry['retry_count'] ?? 0 ) ),
		'fallback_used' => ! empty( $telemetry['fallback_used'] ),
		'estimated_cost' => max( 0, (float) ( $telemetry['estimated_cost'] ?? 0 ) ),
		'success' => ! empty( $telemetry['success'] ),
	];
}
