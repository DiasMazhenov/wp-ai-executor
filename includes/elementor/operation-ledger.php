<?php

/** Durable, bounded operation state used to reconcile browser timeouts safely. */

defined( 'ABSPATH' ) || exit;

const WPAE_DESIGN_OPERATION_SCHEMA = 'wpae-design-operation-v1';
const WPAE_DESIGN_OPERATION_OPTION = 'wp_ai_executor_design_operations';

function wpae_design_operation_states(): array {
	return [ 'planned', 'generated', 'normalized', 'validated', 'written', 'rendered', 'reviewed', 'revised', 'completed', 'failed', 'unknown' ];
}

function wpae_design_operation_store(): array {
	$stored = function_exists( 'get_option' ) ? get_option( WPAE_DESIGN_OPERATION_OPTION, [] ) : [];
	return is_array( $stored ) ? array_values( $stored ) : [];
}

function wpae_design_operation_idempotency_key( int $post_id, string $brief_hash, string $selected_scope, string $operation_type = 'design' ): string {
	return hash( 'sha256', implode( '|', [ $post_id, $brief_hash, sanitize_key( $selected_scope ), sanitize_key( $operation_type ) ] ) );
}

function wpae_design_operation_find( string $idempotency_key ): ?array {
	foreach ( wpae_design_operation_store() as $operation ) {
		if ( is_array( $operation ) && (string) ( $operation['idempotency_key'] ?? '' ) === $idempotency_key ) {
			return $operation;
		}
	}
	return null;
}

function wpae_design_operation_save( array $operations ): void {
	if ( function_exists( 'update_option' ) ) {
		update_option( WPAE_DESIGN_OPERATION_OPTION, array_slice( array_values( $operations ), -100 ), false );
	}
}

function wpae_design_operation_create( array $input ): array {
	$idempotency_key = sanitize_text_field( (string) ( $input['idempotency_key'] ?? '' ) );
	$existing = $idempotency_key !== '' ? wpae_design_operation_find( $idempotency_key ) : null;
	if ( is_array( $existing ) ) {
		$existing['reconciled'] = true;
		return $existing;
	}
	$operation_id = sanitize_key( (string) ( $input['operation_id'] ?? '' ) );
	if ( $operation_id === '' ) {
		$operation_id = 'wpae-op-' . substr( hash( 'sha256', $idempotency_key . '|' . microtime( true ) ), 0, 16 );
	}
	$now = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
	$operation = [
		'schema' => WPAE_DESIGN_OPERATION_SCHEMA,
		'operation_id' => $operation_id,
		'idempotency_key' => $idempotency_key,
		'post_id' => absint( $input['post_id'] ?? 0 ),
		'selected_scope' => sanitize_text_field( (string) ( $input['selected_scope'] ?? 'page' ) ),
		'brief_hash' => sanitize_text_field( (string) ( $input['brief_hash'] ?? '' ) ),
		'plan_hash' => sanitize_text_field( (string) ( $input['plan_hash'] ?? '' ) ),
		'compiled_hash' => sanitize_text_field( (string) ( $input['compiled_hash'] ?? '' ) ),
		'saved_hash' => sanitize_text_field( (string) ( $input['saved_hash'] ?? '' ) ),
		'rendered_html_hash' => sanitize_text_field( (string) ( $input['rendered_html_hash'] ?? '' ) ),
		'provider' => sanitize_key( (string) ( $input['provider'] ?? '' ) ),
		'model' => sanitize_text_field( (string) ( $input['model'] ?? '' ) ),
		'latency_ms' => max( 0, (int) ( $input['latency_ms'] ?? 0 ) ),
		'retry_count' => max( 0, (int) ( $input['retry_count'] ?? 0 ) ),
		'fallback_used' => ! empty( $input['fallback_used'] ),
		'current_state' => in_array( $input['current_state'] ?? 'planned', wpae_design_operation_states(), true ) ? (string) $input['current_state'] : 'planned',
		'created_at' => $now,
		'updated_at' => $now,
	];
	$operations = wpae_design_operation_store();
	$operations[] = $operation;
	wpae_design_operation_save( $operations );
	return $operation;
}

function wpae_design_operation_update( string $operation_id, array $patch ): ?array {
	$operations = wpae_design_operation_store();
	$updated = null;
	foreach ( $operations as &$operation ) {
		if ( ! is_array( $operation ) || (string) ( $operation['operation_id'] ?? '' ) !== $operation_id ) {
			continue;
		}
		foreach ( $patch as $key => $value ) {
			if ( in_array( $key, [ 'current_state', 'post_id', 'latency_ms', 'retry_count', 'fallback_used', 'brief_hash', 'plan_hash', 'compiled_hash', 'saved_hash', 'rendered_html_hash', 'provider', 'model', 'selected_scope' ], true ) ) {
				$operation[ $key ] = $key === 'current_state' && ! in_array( $value, wpae_design_operation_states(), true ) ? 'unknown' : $value;
			}
		}
		$operation['updated_at'] = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
		$updated = $operation;
		break;
	}
	unset( $operation );
	if ( $updated !== null ) {
		wpae_design_operation_save( $operations );
	}
	return $updated;
}

function wpae_design_operation_reconcile( string $operation_id, array $readback = [] ): ?array {
	$state = ! empty( $readback['ok'] ) ? 'written' : 'unknown';
	return wpae_design_operation_update( $operation_id, [
		'current_state' => $state,
		'saved_hash' => sanitize_text_field( (string) ( $readback['saved_hash'] ?? '' ) ),
		'rendered_html_hash' => sanitize_text_field( (string) ( $readback['rendered_html_hash'] ?? '' ) ),
	] );
}
