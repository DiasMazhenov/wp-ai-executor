<?php

/** Policy metadata for provider routing; transport remains the single HTTP path. */

defined( 'ABSPATH' ) || exit;

const WPAE_LLM_ROUTE_SCHEMA = 'wpae-llm-route-v1';

function wpae_design_generation_route( string $pipeline_mode, string $edde_mode, bool $pipeline_supported = true, bool $edde_eligible = true ): array {
	$pipeline_mode = in_array( sanitize_key( $pipeline_mode ), [ 'off', 'shadow', 'active' ], true ) ? sanitize_key( $pipeline_mode ) : 'off';
	$edde_mode = in_array( sanitize_key( $edde_mode ), [ 'off', 'shadow', 'active' ], true ) ? sanitize_key( $edde_mode ) : 'off';
	if ( $pipeline_mode === 'active' && $pipeline_supported ) {
		return [ 'action_path' => 'pipeline', 'provider_calls' => 0, 'writes' => 1, 'shadow_only' => false, 'precedence' => 'pipeline' ];
	}
	if ( $edde_mode === 'active' && $edde_eligible ) {
		return [ 'action_path' => 'edde', 'provider_calls' => 0, 'writes' => 1, 'shadow_only' => $pipeline_mode === 'shadow', 'precedence' => 'edde' ];
	}
	return [ 'action_path' => 'provider', 'provider_calls' => 1, 'writes' => 1, 'shadow_only' => $pipeline_mode === 'shadow', 'precedence' => 'legacy_provider' ];
}

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
	$known = static function ( string $key ) use ( $telemetry ): bool {
		return array_key_exists( $key, $telemetry ) && $telemetry[ $key ] !== null && $telemetry[ $key ] !== '';
	};
	return [
		'schema' => WPAE_LLM_ROUTE_SCHEMA,
		'provider' => sanitize_key( (string) ( $telemetry['provider'] ?? $policy['provider'] ?? '' ) ),
		'model' => sanitize_text_field( (string) ( $telemetry['model'] ?? $policy['model'] ?? '' ) ),
		'route' => sanitize_key( (string) ( $policy['route'] ?? 'unknown' ) ),
		'input_tokens' => $known( 'input_tokens' ) ? max( 0, (int) $telemetry['input_tokens'] ) : null,
		'output_tokens' => $known( 'output_tokens' ) ? max( 0, (int) $telemetry['output_tokens'] ) : null,
		'latency_ms' => $known( 'latency_ms' ) ? max( 0, (int) $telemetry['latency_ms'] ) : null,
		'retry_count' => $known( 'retry_count' ) ? max( 0, (int) $telemetry['retry_count'] ) : null,
		'fallback_used' => ! empty( $telemetry['fallback_used'] ),
		'estimated_cost' => $known( 'estimated_cost' ) ? max( 0, (float) $telemetry['estimated_cost'] ) : null,
		'success' => $known( 'success' ) ? (bool) $telemetry['success'] : null,
		'provider_calls' => $known( 'provider_calls' ) ? max( 0, (int) $telemetry['provider_calls'] ) : null,
		'source' => sanitize_key( (string) ( $telemetry['source'] ?? 'unavailable' ) ),
		'action_path' => sanitize_key( (string) ( $telemetry['action_path'] ?? '' ) ),
		'metrics_known' => [
			'input_tokens' => $known( 'input_tokens' ),
			'output_tokens' => $known( 'output_tokens' ),
			'latency_ms' => $known( 'latency_ms' ),
			'retry_count' => $known( 'retry_count' ),
			'estimated_cost' => $known( 'estimated_cost' ),
			'success' => $known( 'success' ),
		],
	];
}
