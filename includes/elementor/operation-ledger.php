<?php

/** Durable, bounded operation state used to reconcile browser timeouts safely. */

defined( 'ABSPATH' ) || exit;

const WPAE_DESIGN_OPERATION_SCHEMA = 'wpae-design-operation-v1';
const WPAE_DESIGN_OPERATION_OPTION = 'wp_ai_executor_design_operations';
const WPAE_DESIGN_OPERATION_LOCK_OPTION = 'wp_ai_executor_design_operations_lock';

function wpae_design_operation_states(): array {
	return [ 'planned', 'generated', 'normalized', 'validated', 'written', 'rendered', 'reviewed', 'revised', 'completed', 'failed', 'unknown' ];
}

function wpae_design_operation_state_rank( string $state ): int {
	$rank = [ 'planned' => 10, 'generated' => 20, 'normalized' => 30, 'validated' => 40, 'written' => 50, 'rendered' => 60, 'reviewed' => 70, 'revised' => 45, 'completed' => 80, 'failed' => 0, 'unknown' => 1 ];
	return (int) ( $rank[ sanitize_key( $state ) ] ?? -1 );
}

function wpae_design_operation_transition_map(): array {
	return [
		'planned' => [ 'generated', 'failed', 'unknown' ],
		'generated' => [ 'normalized', 'failed', 'unknown' ],
		'normalized' => [ 'validated', 'failed', 'unknown' ],
		'validated' => [ 'written', 'failed', 'unknown' ],
		'written' => [ 'rendered', 'failed', 'unknown' ],
		'rendered' => [ 'reviewed', 'failed', 'unknown' ],
		'reviewed' => [ 'revised', 'completed', 'failed' ],
		'revised' => [ 'generated', 'normalized', 'validated', 'written', 'rendered', 'reviewed', 'completed', 'failed' ],
		'unknown' => [ 'written', 'rendered', 'reviewed', 'failed' ],
		'failed' => [ 'unknown' ],
		'completed' => [],
	];
}

function wpae_design_operation_transition_allowed( string $from, string $to ): bool {
	$from = sanitize_key( $from );
	$to   = sanitize_key( $to );
	if ( $from === $to ) {
		return true;
	}
	$allowed = wpae_design_operation_transition_map();
	return in_array( $to, $allowed[ $from ] ?? [], true );
}

/**
 * Return the shortest valid state path without weakening the state machine.
 * Reconcile uses this while holding the operation lock so a reviewed ack cannot
 * skip the rendered state through a series of unlocked read/update calls.
 */
function wpae_design_operation_transition_path( string $from, string $to ): array {
	$from = sanitize_key( $from );
	$to   = sanitize_key( $to );
	if ( $from === $to ) {
		return [];
	}
	$queue = [ [ $from, [] ] ];
	$seen  = [ $from => true ];
	$map   = wpae_design_operation_transition_map();
	while ( ! empty( $queue ) ) {
		$current = array_shift( $queue );
		$state   = (string) ( $current[0] ?? '' );
		$path    = (array) ( $current[1] ?? [] );
		foreach ( (array) ( $map[ $state ] ?? [] ) as $next ) {
			$next = sanitize_key( (string) $next );
			if ( $next === '' || isset( $seen[ $next ] ) ) {
				continue;
			}
			$next_path = array_merge( $path, [ $next ] );
			if ( $next === $to ) {
				return $next_path;
			}
			$seen[ $next ] = true;
			$queue[] = [ $next, $next_path ];
		}
	}
	return [];
}

function wpae_design_operation_store(): array {
	$stored = function_exists( 'get_option' ) ? get_option( WPAE_DESIGN_OPERATION_OPTION, [] ) : [];
	return is_array( $stored ) ? array_values( $stored ) : [];
}

function wpae_design_operation_idempotency_key( int $post_id, string $brief_hash, string $selected_scope, string $operation_type = 'design', string $operation_identity = '' ): string {
	$identity = sanitize_text_field( $operation_identity );
	// Retries reuse the client identity; a new explicit insertion gets a new one.
	if ( $identity === '' ) {
		$identity = 'legacy-content-key';
	}
	return hash( 'sha256', implode( '|', [ $post_id, $brief_hash, sanitize_key( $selected_scope ), sanitize_key( $operation_type ), $identity ] ) );
}

function wpae_design_operation_find( string $idempotency_key ): ?array {
	foreach ( wpae_design_operation_store() as $operation ) {
		if ( is_array( $operation ) && (string) ( $operation['idempotency_key'] ?? '' ) === $idempotency_key ) {
			return $operation;
		}
	}
	return null;
}

function wpae_design_operation_find_by_id( string $operation_id ): ?array {
	$operation_id = sanitize_key( $operation_id );
	if ( $operation_id === '' ) {
		return null;
	}
	foreach ( wpae_design_operation_store() as $operation ) {
		if ( is_array( $operation ) && (string) ( $operation['operation_id'] ?? '' ) === $operation_id ) {
			return $operation;
		}
	}
	return null;
}

/**
 * Compare a pending operation with the currently saved Elementor target.
 * This is read-only: stale history stays in the ledger, but cannot be used
 * as capture or review evidence.
 */
function wpae_design_operation_target_status( array $operation, int $post_id, ?array $elementor_data = null ): array {
	if ( absint( $operation['post_id'] ?? 0 ) !== $post_id ) {
		return [ 'reviewable' => false, 'status' => 'scope_mismatch', 'reason' => 'post_mismatch' ];
	}
	$root_ids = array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) ( $operation['root_ids'] ?? [] ), 0, 12 ) ) ) );
	if ( empty( $root_ids ) ) {
		return [ 'reviewable' => false, 'status' => 'unknown_target', 'reason' => 'missing_root_scope' ];
	}
	if ( $elementor_data === null && function_exists( 'wpae_get_elementor_data_for_post' ) ) {
		$elementor_data = wpae_get_elementor_data_for_post( $post_id );
	}
	if ( ! is_array( $elementor_data ) ) {
		return [ 'reviewable' => false, 'status' => 'unknown_target', 'reason' => 'readback_unavailable' ];
	}
	$current_root_ids = array_values( array_filter( array_map( static fn( $item ): string => is_array( $item ) ? sanitize_key( (string) ( $item['id'] ?? '' ) ) : '', $elementor_data ) ) );
	$missing_root_ids = array_values( array_diff( $root_ids, $current_root_ids ) );
	if ( ! empty( $missing_root_ids ) ) {
		$class = 'unknown_target_change';
		$snapshot_id = sanitize_text_field( (string) ( $operation['rollback_snapshot_id'] ?? '' ) );
		if ( $snapshot_id !== '' && function_exists( 'wpae_get_rollback_snapshots' ) && function_exists( 'wpae_rollback_post_fingerprint' ) ) {
			$snapshot = wpae_get_rollback_snapshots()[ $snapshot_id ] ?? null;
			$after_hash = is_array( $snapshot ) ? sanitize_text_field( (string) ( $snapshot['after_hashes'][ $post_id ] ?? '' ) ) : '';
			if ( $after_hash !== '' && hash_equals( $after_hash, wpae_rollback_post_fingerprint( $post_id ) ) ) {
				$class = 'confirmed_rollback';
			}
		}
		return [
			'reviewable' => false,
			'status' => 'stale_target',
			'reason' => 'root_missing',
			'class' => $class,
			'root_ids' => $root_ids,
			'missing_root_ids' => $missing_root_ids,
		];
	}
	$expected_saved_hash = sanitize_text_field( (string) ( $operation['saved_hash'] ?? '' ) );
	$current_saved_hash = hash( 'sha256', (string) wp_json_encode( $elementor_data ) );
	if ( $expected_saved_hash === '' ) {
		return [ 'reviewable' => false, 'status' => 'unknown_target', 'reason' => 'missing_saved_hash', 'root_ids' => $root_ids ];
	}
	if ( ! hash_equals( $expected_saved_hash, $current_saved_hash ) ) {
		return [ 'reviewable' => false, 'status' => 'stale_target', 'reason' => 'saved_hash_mismatch', 'root_ids' => $root_ids, 'current_saved_hash' => $current_saved_hash ];
	}
	$expected_fingerprint = sanitize_text_field( (string) ( $operation['target_fingerprint'] ?? '' ) );
	if ( $expected_fingerprint !== '' ) {
		if ( ! function_exists( 'wpae_rollback_post_fingerprint' ) ) {
			return [ 'reviewable' => false, 'status' => 'unknown_target', 'reason' => 'fingerprint_unavailable', 'root_ids' => $root_ids ];
		}
		if ( ! hash_equals( $expected_fingerprint, (string) wpae_rollback_post_fingerprint( $post_id ) ) ) {
			return [ 'reviewable' => false, 'status' => 'stale_target', 'reason' => 'fingerprint_mismatch', 'root_ids' => $root_ids ];
		}
	}
	return [ 'reviewable' => true, 'status' => 'current', 'reason' => 'target_matches_saved_snapshot', 'root_ids' => $root_ids ];
}

/**
 * Verify that a persisted Vision report belongs to this exact operation
 * snapshot. Browser evidence may point at a report, but cannot establish this
 * relationship without the server-side ledger values below.
 */
function wpae_design_operation_report_scope_matches( array $operation, array $report, int $post_id, int $revision, array $root_ids = [] ): bool {
	$context = is_array( $report['render_context'] ?? null ) ? $report['render_context'] : [];
	$stored_identity = sanitize_text_field( (string) ( $operation['operation_identity'] ?? '' ) );
	$report_identity = sanitize_text_field( (string) ( $context['operation_identity'] ?? '' ) );
	$operation_roots = array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) ( $operation['root_ids'] ?? [] ), 0, 12 ) ) ) );
	$report_roots = array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) ( $context['operation_root_ids'] ?? [] ), 0, 12 ) ) ) );
	$reported_roots = array_values( array_filter( array_map( 'sanitize_key', array_slice( $root_ids, 0, 12 ) ) ) );
	$report_operation_id = sanitize_key( (string) ( $context['operation_id'] ?? '' ) );
	$report_revision = absint( $context['operation_revision'] ?? 0 );
	$report_saved_hash = sanitize_text_field( (string) ( $context['operation_saved_hash'] ?? '' ) );
	$report_fingerprint = sanitize_text_field( (string) ( $context['operation_target_fingerprint'] ?? '' ) );
	if ( ! is_array( $report ) || (int) ( $report['post_id'] ?? 0 ) !== $post_id
		|| $report_operation_id === '' || ! hash_equals( sanitize_key( (string) ( $operation['operation_id'] ?? '' ) ), $report_operation_id )
		|| $stored_identity === '' || $report_identity === '' || ! hash_equals( $stored_identity, $report_identity )
		|| $report_revision !== max( 1, $revision )
		|| empty( $operation_roots ) || array_diff( $operation_roots, $report_roots )
		|| ( ! empty( $reported_roots ) && array_diff( $reported_roots, $operation_roots ) )
		|| $report_saved_hash === '' || ! hash_equals( (string) ( $operation['saved_hash'] ?? '' ), $report_saved_hash )
		|| $report_fingerprint === '' || ! hash_equals( (string) ( $operation['target_fingerprint'] ?? '' ), $report_fingerprint ) ) {
		return false;
	}
	return true;
}

function wpae_design_operation_lock_token(): string {
	$payload = [ microtime( true ), function_exists( 'getmypid' ) ? getmypid() : 0, mt_rand() ];
	return substr( hash( 'sha256', function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : serialize( $payload ) ), 0, 32 );
}

function wpae_design_operation_acquire_lock( int $ttl = 20 ): ?string {
	$token = wpae_design_operation_lock_token();
	$lock  = [ 'token' => $token, 'expires' => time() + max( 5, $ttl ) ];
	if ( function_exists( 'add_option' ) ) {
		if ( add_option( WPAE_DESIGN_OPERATION_LOCK_OPTION, $lock, '', 'no' ) ) {
			return $token;
		}
		$existing = function_exists( 'get_option' ) ? get_option( WPAE_DESIGN_OPERATION_LOCK_OPTION, [] ) : [];
		if ( is_array( $existing ) && (int) ( $existing['expires'] ?? 0 ) < time() ) {
			if ( function_exists( 'delete_option' ) ) {
				delete_option( WPAE_DESIGN_OPERATION_LOCK_OPTION );
			}
			if ( add_option( WPAE_DESIGN_OPERATION_LOCK_OPTION, $lock, '', 'no' ) ) {
				return $token;
			}
		}
		return null;
	}
	// ponytail: single-process fallback only for the PHP contract harness; the
	// WordPress path above uses the database's unique option insert.
	global $wpae_design_operation_fallback_lock;
	if ( ! empty( $wpae_design_operation_fallback_lock ) ) {
		return null;
	}
	$wpae_design_operation_fallback_lock = true;
	return $token;
}

function wpae_design_operation_release_lock( ?string $token ): void {
	if ( $token === null ) {
		return;
	}
	if ( function_exists( 'get_option' ) && function_exists( 'delete_option' ) && function_exists( 'add_option' ) ) {
		$lock = get_option( WPAE_DESIGN_OPERATION_LOCK_OPTION, [] );
		if ( is_array( $lock ) && hash_equals( (string) ( $lock['token'] ?? '' ), $token ) ) {
			delete_option( WPAE_DESIGN_OPERATION_LOCK_OPTION );
		}
		return;
	}
	// fallback_lock is intentionally process-local and only used by tests.
	global $wpae_design_operation_fallback_lock;
	$wpae_design_operation_fallback_lock = false;
}

function wpae_design_operation_with_lock( callable $callback ) {
	$token = wpae_design_operation_acquire_lock();
	if ( $token === null ) {
		return null;
	}
	try {
		return $callback();
	} finally {
		wpae_design_operation_release_lock( $token );
	}
}

function wpae_design_operation_save( array $operations ): void {
	if ( function_exists( 'update_option' ) ) {
		update_option( WPAE_DESIGN_OPERATION_OPTION, array_slice( array_values( $operations ), -100 ), false );
	}
}

function wpae_design_operation_create( array $input ): array {
	$idempotency_key = sanitize_text_field( (string) ( $input['idempotency_key'] ?? '' ) );
	$result = wpae_design_operation_with_lock( static function () use ( $input, $idempotency_key ): array {
		$existing = $idempotency_key !== '' ? wpae_design_operation_find( $idempotency_key ) : null;
		if ( is_array( $existing ) ) {
			$existing['reconciled'] = true;
			return $existing;
		}
		$identity = sanitize_text_field( (string) ( $input['operation_identity'] ?? '' ) );
		$operation_id = sanitize_key( (string) ( $input['operation_id'] ?? '' ) );
		if ( $operation_id === '' ) {
			$operation_id = 'wpae-op-' . substr( hash( 'sha256', $idempotency_key . '|' . $identity . '|' . microtime( true ) ), 0, 16 );
		}
		$now = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
		$operation = [
			'schema' => WPAE_DESIGN_OPERATION_SCHEMA,
			'operation_id' => $operation_id,
			'operation_identity' => $identity,
			'idempotency_key' => $idempotency_key,
			'post_id' => absint( $input['post_id'] ?? 0 ),
			'selected_scope' => sanitize_text_field( (string) ( $input['selected_scope'] ?? 'page' ) ),
			'operation_type' => sanitize_key( (string) ( $input['operation_type'] ?? 'design' ) ),
			'brief_hash' => sanitize_text_field( (string) ( $input['brief_hash'] ?? '' ) ),
			'plan_hash' => sanitize_text_field( (string) ( $input['plan_hash'] ?? '' ) ),
			'compiled_hash' => sanitize_text_field( (string) ( $input['compiled_hash'] ?? '' ) ),
			'saved_hash' => sanitize_text_field( (string) ( $input['saved_hash'] ?? '' ) ),
			'rendered_html_hash' => sanitize_text_field( (string) ( $input['rendered_html_hash'] ?? '' ) ),
			'vision_report_id' => sanitize_text_field( (string) ( $input['vision_report_id'] ?? '' ) ),
			'root_ids' => array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) ( $input['root_ids'] ?? [] ), 0, 12 ) ) ) ),
			'target_fingerprint' => sanitize_text_field( (string) ( $input['target_fingerprint'] ?? '' ) ),
			'provider' => sanitize_key( (string) ( $input['provider'] ?? '' ) ),
			'model' => sanitize_text_field( (string) ( $input['model'] ?? '' ) ),
			'latency_ms' => max( 0, (int) ( $input['latency_ms'] ?? 0 ) ),
			'retry_count' => max( 0, (int) ( $input['retry_count'] ?? 0 ) ),
			'fallback_used' => ! empty( $input['fallback_used'] ),
			'current_state' => in_array( $input['current_state'] ?? 'planned', wpae_design_operation_states(), true ) ? (string) $input['current_state'] : 'planned',
			'revision' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		];
		$operations = wpae_design_operation_store();
		$operations[] = $operation;
		wpae_design_operation_save( $operations );
		return $operation;
	} );
	if ( is_array( $result ) ) {
		return $result;
	}
	return [ 'schema' => WPAE_DESIGN_OPERATION_SCHEMA, 'idempotency_key' => $idempotency_key, 'current_state' => 'unknown', 'reconciled' => true, 'lock_conflict' => true, 'conflict_reason' => 'operation_lock_busy' ];
}

function wpae_design_operation_update( string $operation_id, array $patch ): ?array {
	$result = wpae_design_operation_with_lock( static function () use ( $operation_id, $patch ): ?array {
		$operations = wpae_design_operation_store();
		$updated = null;
		foreach ( $operations as &$operation ) {
			if ( ! is_array( $operation ) || (string) ( $operation['operation_id'] ?? '' ) !== $operation_id ) {
				continue;
			}
			$current_state = sanitize_key( (string) ( $operation['current_state'] ?? 'planned' ) );
			$transition_rejected = false;
			foreach ( $patch as $key => $value ) {
				$allowed_keys = [ 'current_state', 'post_id', 'latency_ms', 'retry_count', 'fallback_used', 'brief_hash', 'plan_hash', 'compiled_hash', 'saved_hash', 'rendered_html_hash', 'vision_report_id', 'provider', 'model', 'selected_scope', 'operation_identity', 'operation_type', 'root_ids', 'target_fingerprint', 'last_error', 'evidence_source', 'evidence_hash', 'rollback_snapshot_id', 'rollback_event' ];
				if ( ! in_array( $key, $allowed_keys, true ) ) {
					continue;
				}
				if ( $key === 'current_state' ) {
					$next_state = sanitize_key( (string) $value );
					if ( ! in_array( $next_state, wpae_design_operation_states(), true ) || ! wpae_design_operation_transition_allowed( $current_state, $next_state ) ) {
						$transition_rejected = true;
						continue;
					}
					$operation[ $key ] = $next_state;
					$current_state = $next_state;
					continue;
				}
				if ( $key === 'root_ids' ) {
					$operation[ $key ] = array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) $value, 0, 12 ) ) ) );
				} elseif ( in_array( $key, [ 'post_id', 'latency_ms', 'retry_count' ], true ) ) {
					$operation[ $key ] = max( 0, (int) $value );
				} elseif ( $key === 'fallback_used' ) {
					$operation[ $key ] = ! empty( $value );
				} else {
					$operation[ $key ] = sanitize_text_field( (string) $value );
				}
			}
			if ( $transition_rejected ) {
				$operation['last_error'] = 'invalid_state_transition';
				$operation['transition_rejected'] = true;
			}
			$operation['revision'] = max( 1, (int) ( $operation['revision'] ?? 1 ) + 1 );
			$operation['updated_at'] = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
			$updated = $operation;
			break;
		}
		unset( $operation );
		if ( $updated !== null ) {
			wpae_design_operation_save( $operations );
		}
		return $updated;
	} );
	return is_array( $result ) ? $result : null;
}

/**
 * Record an operation-scoped rollback while holding the same lock as create/update.
 * Browser retries must prove identity and revision before changing the ledger.
 */
function wpae_design_operation_mark_rollback( string $operation_id, string $operation_identity, int $revision, string $snapshot_id, string $event = 'rollback', string $evidence_hash = '' ): ?array {
	$result = wpae_design_operation_with_lock( static function () use ( $operation_id, $operation_identity, $revision, $snapshot_id, $event, $evidence_hash ): ?array {
		$operations = wpae_design_operation_store();
		foreach ( $operations as $index => $operation ) {
			if ( ! is_array( $operation ) || (string) ( $operation['operation_id'] ?? '' ) !== $operation_id ) {
				continue;
			}
			$stored_identity = sanitize_text_field( (string) ( $operation['operation_identity'] ?? '' ) );
			if ( $stored_identity !== '' && ! hash_equals( $stored_identity, sanitize_text_field( $operation_identity ) ) ) {
				return null;
			}
			$current_revision = max( 1, (int) ( $operation['revision'] ?? 1 ) );
			if ( $revision > 0 && $revision !== $current_revision ) {
				return null;
			}
			$event = sanitize_key( $event ) ?: 'rollback';
			$snapshot_id = sanitize_text_field( $snapshot_id );
			if ( (string) ( $operation['current_state'] ?? '' ) === 'failed'
				&& (string) ( $operation['rollback_snapshot_id'] ?? '' ) === $snapshot_id
				&& (string) ( $operation['rollback_event'] ?? '' ) === $event ) {
				return $operation;
			}
			$current_state = sanitize_key( (string) ( $operation['current_state'] ?? 'planned' ) );
			$next_state = $event === 'user_undo' ? 'unknown' : 'failed';
			if ( $current_state !== $next_state && ! wpae_design_operation_transition_allowed( $current_state, $next_state ) ) {
				return null;
			}
			$operation['current_state'] = $next_state;
			$operation['rollback_snapshot_id'] = $snapshot_id;
			$operation['rollback_event'] = $event;
			$operation['last_error'] = 'rollback_' . $event;
			if ( $evidence_hash !== '' ) {
				$operation['evidence_hash'] = sanitize_text_field( $evidence_hash );
			}
			$operation['revision'] = $current_revision + 1;
			$operation['updated_at'] = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
			$operations[ $index ] = $operation;
			wpae_design_operation_save( $operations );
			return $operation;
		}
		return null;
	} );
	return is_array( $result ) ? $result : null;
}

function wpae_design_operation_reconcile( string $operation_id, array $readback = [] ): ?array {
	$result = wpae_design_operation_with_lock( static function () use ( $operation_id, $readback ): ?array {
		$operations = wpae_design_operation_store();
		$index      = null;
		foreach ( $operations as $candidate_index => $candidate ) {
			if ( is_array( $candidate ) && (string) ( $candidate['operation_id'] ?? '' ) === $operation_id ) {
				$index = $candidate_index;
				break;
			}
		}
		if ( $index === null ) {
			return null;
		}
		$operation = is_array( $operations[ $index ] ) ? $operations[ $index ] : [];
		$requested = sanitize_key( (string) ( $readback['state'] ?? ( ! empty( $readback['ok'] ) ? 'written' : 'unknown' ) ) );
		if ( ! in_array( $requested, wpae_design_operation_states(), true ) ) {
			$requested = 'unknown';
		}
		if ( in_array( $requested, [ 'rendered', 'reviewed', 'completed' ], true ) && empty( $readback['server_verified'] ) ) {
			$requested = 'written';
		}
		$current = sanitize_key( (string) ( $operation['current_state'] ?? 'planned' ) );
		if ( wpae_design_operation_state_rank( $current ) > wpae_design_operation_state_rank( $requested ) ) {
			$requested = $current;
		}
		$path = wpae_design_operation_transition_path( $current, $requested );
		if ( $current !== $requested && empty( $path ) ) {
			$operation['last_error']          = 'invalid_state_transition';
			$operation['transition_rejected'] = true;
			$operation['updated_at']          = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
			$operations[ $index ]             = $operation;
			wpae_design_operation_save( $operations );
			return $operation;
		}
		foreach ( $path as $state ) {
			$operation['current_state'] = $state;
		}
		$changed = ! empty( $path );
		$metadata = [
			'saved_hash' => sanitize_text_field( (string) ( $readback['saved_hash'] ?? '' ) ),
			'rendered_html_hash' => sanitize_text_field( (string) ( $readback['rendered_html_hash'] ?? '' ) ),
			'vision_report_id' => sanitize_text_field( (string) ( $readback['vision_report_id'] ?? '' ) ),
			'evidence_source' => sanitize_text_field( (string) ( $readback['evidence_source'] ?? '' ) ),
			'evidence_hash' => sanitize_text_field( (string) ( $readback['evidence_hash'] ?? '' ) ),
		];
		foreach ( $metadata as $key => $value ) {
			if ( $value !== '' && (string) ( $operation[ $key ] ?? '' ) !== $value ) {
				$operation[ $key ] = $value;
				$changed = true;
			}
		}
		if ( array_key_exists( 'root_ids', $readback ) ) {
			$root_ids = array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) $readback['root_ids'], 0, 12 ) ) ) );
			if ( $root_ids !== (array) ( $operation['root_ids'] ?? [] ) ) {
				$operation['root_ids'] = $root_ids;
				$changed = true;
			}
		}
		if ( ! empty( $readback['conflict_reason'] ) ) {
			$error = sanitize_text_field( (string) $readback['conflict_reason'] );
			if ( (string) ( $operation['last_error'] ?? '' ) !== $error ) {
				$operation['last_error'] = $error;
				$changed = true;
			}
		}
		if ( ! $changed ) {
			return $operation;
		}
		$operation['revision']   = max( 1, (int) ( $operation['revision'] ?? 1 ) + 1 );
		$operation['updated_at'] = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'c' );
		$operations[ $index ]     = $operation;
		wpae_design_operation_save( $operations );
		return $operation;
	} );
	return is_array( $result ) ? $result : null;
}
