<?php

defined( 'ABSPATH' ) || exit;

function wpae_required_ack_schema(): array {
    return [
        'read_agent_prompt' => true,
        'read_custom_skills' => true,
        'read_capabilities' => true,
        'will_follow_skills' => true,
        'will_follow_runtime_rules' => true,
    ];
}

function wpae_get_guide_hash(): string {
    $payload = [
        'guide_version' => 'v02.05.43',
        'plugin_version' => WPAE_VERSION,
        'agent_prompt' => wpae_agent_prompt(),
        'custom_skills' => wpae_get_enabled_skills_for_guide(),
        'capabilities' => wpae_get_capabilities_payload(),
    ];

    return hash( 'sha256', (string) wp_json_encode( $payload ) );
}

function wpae_get_guide_sessions(): array {
    $sessions = get_option( 'wp_ai_executor_guide_sessions', [] );
    return is_array( $sessions ) ? $sessions : [];
}

function wpae_update_guide_sessions( array $sessions ): void {
    update_option( 'wp_ai_executor_guide_sessions', $sessions, false );
}

function wpae_normalize_guide_session_id( string $session_id ): string {
    return preg_match( '/^[a-f0-9]{32}$/', $session_id ) ? $session_id : '';
}

function wpae_guide_session_option_name( string $session_id ): string {
    return 'wp_ai_executor_guide_session_' . wpae_normalize_guide_session_id( $session_id );
}

function wpae_guide_token_option_name( string $token_hash ): string {
    $token_hash = preg_match( '/^[a-f0-9]{64}$/', $token_hash ) ? $token_hash : '';
    return 'wp_ai_executor_guide_token_' . $token_hash;
}

function wpae_prune_guide_token_records(): void {
    global $wpdb;

    $prefix = $wpdb->esc_like( 'wp_ai_executor_guide_token_' ) . '%';
    $option_names = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $prefix
    ) );

    foreach ( (array) $option_names as $option_name ) {
        $record = get_option( $option_name, null );
        if ( ! is_array( $record ) || (int) ( $record['expires_at_unix'] ?? 0 ) < time() ) {
            delete_option( $option_name );
        }
    }
}

function wpae_get_stored_guide_session( string $session_id ) {
    $session_id = wpae_normalize_guide_session_id( $session_id );
    if ( $session_id === '' ) {
        return null;
    }

    $session = get_option( wpae_guide_session_option_name( $session_id ), null );
    if ( is_array( $session ) ) {
        return $session;
    }

    $sessions = wpae_get_guide_sessions();
    return is_array( $sessions[ $session_id ] ?? null ) ? $sessions[ $session_id ] : null;
}

function wpae_save_guide_session( array $session ): bool {
    $session_id = wpae_normalize_guide_session_id( (string) ( $session['id'] ?? '' ) );
    if ( $session_id === '' ) {
        return false;
    }

    $session['id'] = $session_id;
    update_option( wpae_guide_session_option_name( $session_id ), $session, false );

    $sessions = wpae_prune_guide_sessions( wpae_get_guide_sessions() );
    $sessions[ $session_id ] = $session;
    wpae_update_guide_sessions( $sessions );

    return is_array( get_option( wpae_guide_session_option_name( $session_id ), null ) );
}

function wpae_delete_guide_session( string $session_id ): void {
    $session_id = wpae_normalize_guide_session_id( $session_id );
    if ( $session_id === '' ) {
        return;
    }

    $session = wpae_get_stored_guide_session( $session_id );
    if ( is_array( $session ) && ! empty( $session['token_hash'] ) ) {
        delete_option( wpae_guide_token_option_name( (string) $session['token_hash'] ) );
    }

    delete_option( wpae_guide_session_option_name( $session_id ) );
    $sessions = wpae_get_guide_sessions();
    unset( $sessions[ $session_id ] );
    wpae_update_guide_sessions( $sessions );
}

function wpae_save_guide_token_record( string $token_hash, array $session ): void {
    if ( ! preg_match( '/^[a-f0-9]{64}$/', $token_hash ) ) {
        return;
    }

    $option_name = wpae_guide_token_option_name( $token_hash );
    update_option( $option_name, [
        'token_hash' => $token_hash,
        'session_id' => (string) ( $session['id'] ?? '' ),
        'guide_hash' => (string) ( $session['guide_hash'] ?? '' ),
        'expires_at' => (string) ( $session['expires_at'] ?? '' ),
        'expires_at_unix' => (int) ( $session['expires_at_unix'] ?? 0 ),
    ], false );
}

function wpae_get_guide_token_record( string $token_hash ) {
    $record = get_option( wpae_guide_token_option_name( $token_hash ), null );
    return is_array( $record ) ? $record : null;
}

function wpae_prune_guide_sessions( array $sessions ): array {
    $now = time();
    foreach ( $sessions as $id => $session ) {
        if ( (int) ( $session['expires_at_unix'] ?? 0 ) < $now ) {
            unset( $sessions[ $id ] );
            delete_option( wpae_guide_session_option_name( (string) $id ) );
        }
    }
    return $sessions;
}

function wpae_get_request_json_body_array( WP_REST_Request $request ): array {
    $body = trim( (string) $request->get_body() );
    if ( $body === '' || $body[0] !== '{' ) {
        return [];
    }

    $decoded = json_decode( $body, true );
    return is_array( $decoded ) ? $decoded : [];
}

function wpae_get_guide_ack_payload( WP_REST_Request $request ): array {
    $raw_body = wpae_get_request_json_body_array( $request );
    $session_id = (string) ( $request->get_param( 'guide_session_id' ) ?: ( $raw_body['guide_session_id'] ?? '' ) );
    $ack = $request->get_param( 'ack' );
    if ( ! is_array( $ack ) && isset( $raw_body['ack'] ) && is_array( $raw_body['ack'] ) ) {
        $ack = $raw_body['ack'];
    }

    if ( ! is_array( $ack ) ) {
        $ack = [];
        foreach ( wpae_required_ack_schema() as $field => $required_value ) {
            $value = $request->get_param( $field );
            if ( $value === null && array_key_exists( $field, $raw_body ) ) {
                $value = $raw_body[ $field ];
            }
            if ( $value !== null ) {
                $ack[ $field ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            }
        }
    }

    return [
        'guide_session_id' => wpae_normalize_guide_session_id( sanitize_text_field( $session_id ) ),
        'ack' => $ack,
        'raw_json_body_detected' => ! empty( $raw_body ),
    ];
}

function wpae_create_guide_session(): WP_REST_Response {
    wpae_prune_guide_token_records();
    wpae_update_guide_sessions( wpae_prune_guide_sessions( wpae_get_guide_sessions() ) );
    $session_id = bin2hex( random_bytes( 16 ) );
    $expires_at_unix = time() + 15 * MINUTE_IN_SECONDS;

    $session = [
        'id' => $session_id,
        'guide_hash' => wpae_get_guide_hash(),
        'created_at' => gmdate( 'c' ),
        'expires_at' => gmdate( 'c', $expires_at_unix ),
        'expires_at_unix' => $expires_at_unix,
        'acked' => false,
    ];

    $stored = wpae_save_guide_session( $session );

    return new WP_REST_Response( [
        'guide_session_id' => $session_id,
        'guide_hash' => $session['guide_hash'],
        'expires_at' => $session['expires_at'],
        'storage' => [
            'type' => 'wp_options_per_session',
            'stored' => $stored,
        ],
        'required_ack_schema' => wpae_required_ack_schema(),
        'next_steps' => [
            'Read /guide and /capabilities.',
            'Call /guide/ack with guide_session_id and all required ack fields set to true.',
            'Pass X-WPAE-Guide-Token and X-WPAE-Guide-Hash to every write endpoint.',
        ],
    ], 200 );
}

function wpae_ack_guide_session( WP_REST_Request $request ) {
    wpae_prune_guide_token_records();
    $payload = wpae_get_guide_ack_payload( $request );
    $session_id = $payload['guide_session_id'];
    $ack = $payload['ack'];
    wpae_update_guide_sessions( wpae_prune_guide_sessions( wpae_get_guide_sessions() ) );
    $session = $session_id !== '' ? wpae_get_stored_guide_session( $session_id ) : null;

    if ( ! is_array( $session ) ) {
        return new WP_REST_Response( [
            'error' => 'Invalid or expired guide_session_id.',
            'diagnostics' => [
                'received_guide_session_id' => $session_id !== '',
                'raw_json_body_detected' => (bool) $payload['raw_json_body_detected'],
                'legacy_session_count' => count( wpae_get_guide_sessions() ),
                'storage' => 'wp_options_per_session',
                'hint' => 'Send Content-Type: application/json or put guide_session_id and ack fields in form data.',
            ],
        ], 404 );
    }

    if ( ! is_array( $ack ) ) {
        return new WP_REST_Response( [ 'error' => 'ack object is required.', 'required_ack_schema' => wpae_required_ack_schema() ], 400 );
    }

    $missing = [];
    foreach ( wpae_required_ack_schema() as $field => $required_value ) {
        if ( empty( $ack[ $field ] ) ) {
            $missing[] = $field;
        }
    }

    if ( ! empty( $missing ) ) {
        return new WP_REST_Response( [
            'error' => 'Guide acknowledgement is incomplete.',
            'missing' => $missing,
            'required_ack_schema' => wpae_required_ack_schema(),
        ], 400 );
    }

    $current_hash = wpae_get_guide_hash();
    if ( ! hash_equals( (string) $session['guide_hash'], $current_hash ) ) {
        wpae_delete_guide_session( $session_id );
        return new WP_REST_Response( [ 'error' => 'Guide changed. Start a new /guide/session.', 'guide_hash' => $current_hash ], 409 );
    }

    $token = bin2hex( random_bytes( 32 ) );
    $token_hash = hash( 'sha256', $token );
    $expires_at_unix = time() + 15 * MINUTE_IN_SECONDS;

    $session['acked'] = true;
    $session['ack'] = array_intersect_key( $ack, wpae_required_ack_schema() );
    $session['token_hash'] = $token_hash;
    $session['expires_at'] = gmdate( 'c', $expires_at_unix );
    $session['expires_at_unix'] = $expires_at_unix;
    $session['acked_at'] = gmdate( 'c' );

    wpae_save_guide_session( $session );
    wpae_save_guide_token_record( $token_hash, $session );

    return new WP_REST_Response( [
        'ok' => true,
        'guide_token' => $token,
        'guide_hash' => $current_hash,
        'expires_at' => $session['expires_at'],
        'headers' => [
            'X-WPAE-Guide-Token' => $token,
            'X-WPAE-Guide-Hash' => $current_hash,
        ],
    ], 200 );
}

function wpae_validate_guide_token( string $token, string $guide_hash ) {
    wpae_prune_guide_token_records();
    if ( $token === '' || $guide_hash === '' ) {
        return [ 'error' => 'missing_guide_token_or_hash' ];
    }

    $current_hash = wpae_get_guide_hash();
    if ( ! hash_equals( $current_hash, $guide_hash ) ) {
        return [ 'error' => 'stale_guide_hash', 'current_guide_hash' => $current_hash ];
    }

    $sessions = wpae_prune_guide_sessions( wpae_get_guide_sessions() );
    $token_hash = hash( 'sha256', $token );
    $valid = false;

    $token_record = wpae_get_guide_token_record( $token_hash );
    if (
        is_array( $token_record ) &&
        (int) ( $token_record['expires_at_unix'] ?? 0 ) >= time() &&
        hash_equals( (string) ( $token_record['guide_hash'] ?? '' ), $current_hash ) &&
        hash_equals( (string) ( $token_record['token_hash'] ?? '' ), $token_hash )
    ) {
        $valid = true;
    }

    if ( ! $valid ) {
        foreach ( $sessions as $session ) {
            if (
                ! empty( $session['acked'] ) &&
                hash_equals( (string) ( $session['guide_hash'] ?? '' ), $current_hash ) &&
                hash_equals( (string) ( $session['token_hash'] ?? '' ), $token_hash )
            ) {
                $valid = true;
                break;
            }
        }
    }

    wpae_update_guide_sessions( $sessions );

    return $valid ? true : [ 'error' => 'invalid_or_expired_guide_token' ];
}
