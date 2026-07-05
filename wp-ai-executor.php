<?php
/**
 * Plugin Name: WP AI Executor
 * Description: Secure REST endpoint for AI automation (Claude, GPT, Gemini, Qwen, etc.). Execute PHP in WordPress context via any AI agent.
 * Version:     1.9.0
 * Author:      DIAS
 * License:     MIT
 */

defined( 'ABSPATH' ) || exit;

const WPAE_VERSION = '1.9.0';
const WPAE_ROLLBACK_TTL_SECONDS = 7200;
const WPAE_ROLLBACK_MAX_SNAPSHOTS = 20;
const WPAE_OPERATION_LOG_MAX_ENTRIES = 100;

// ── Key management ─────────────────────────────────────────────────────────────
function wpae_get_key(): string {
    if ( defined( 'WP_AI_EXECUTOR_KEY' ) ) return WP_AI_EXECUTOR_KEY;
    $key = get_option( 'wp_ai_executor_key' );
    if ( ! $key ) {
        $key = bin2hex( random_bytes( 32 ) );
        update_option( 'wp_ai_executor_key', $key );
    }
    return $key;
}

function wpae_capability_defaults(): array {
    return [
        'run' => true,
        'self_update' => true,
        'elementor_writes' => true,
        'media_upload' => true,
        'exports' => true,
        'manage_skills' => true,
        'filesystem_writes' => false,
    ];
}

function wpae_capability_labels(): array {
    return [
        'run' => [
            'label' => 'Разрешить PHP /run',
            'description' => 'Позволяет авторизованным агентам выполнять PHP через /run.',
        ],
        'self_update' => [
            'label' => 'Разрешить самообновление плагина',
            'description' => 'Позволяет /self-update обновлять файл плагина из разрешенного GitHub-источника.',
        ],
        'elementor_writes' => [
            'label' => 'Разрешить запись Elementor',
            'description' => 'Позволяет сохранять _elementor_data через /run и структурированные Elementor endpoints.',
        ],
        'media_upload' => [
            'label' => 'Разрешить загрузку медиа',
            'description' => 'Позволяет /media/upload создавать проверенные вложения WordPress.',
        ],
        'exports' => [
            'label' => 'Разрешить JSON-экспорты',
            'description' => 'Позволяет /exports/create создавать JSON-файлы в uploads/wp-ai-executor/exports.',
        ],
        'manage_skills' => [
            'label' => 'Разрешить управление skills',
            'description' => 'Позволяет агентам создавать, обновлять и удалять custom skills в базе данных.',
        ],
        'filesystem_writes' => [
            'label' => 'Разрешить запись файлов через /run',
            'description' => 'Опасно. Разрешает файловые операции через /run. Держите выключенным без явной необходимости.',
        ],
    ];
}

function wpae_get_capability_settings(): array {
    $stored = get_option( 'wp_ai_executor_capabilities', [] );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $settings = [];
    foreach ( wpae_capability_defaults() as $key => $default ) {
        $settings[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : (bool) $default;
    }

    return $settings;
}

function wpae_update_capability_settings( array $input ): void {
    $settings = [];
    foreach ( wpae_capability_defaults() as $key => $default ) {
        $settings[ $key ] = ! empty( $input[ $key ] );
    }
    update_option( 'wp_ai_executor_capabilities', $settings, false );
}

function wpae_capability_enabled( string $capability ): bool {
    $settings = wpae_get_capability_settings();
    return ! empty( $settings[ $capability ] );
}

function wpae_can_run_filesystem_operations(): bool {
    if ( defined( 'WP_AI_EXECUTOR_ALLOW_FILE_WRITES' ) && WP_AI_EXECUTOR_ALLOW_FILE_WRITES ) {
        return true;
    }

    return wpae_capability_enabled( 'filesystem_writes' );
}

// ── REST routes ────────────────────────────────────────────────────────────────
add_action( 'rest_api_init', function () {

    register_rest_route( 'ai-executor/v1', '/run', [
        'methods'             => 'POST',
        'callback'            => 'wpae_run',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'run' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/key', [
        'methods'             => 'GET',
        'callback'            => fn() => new WP_REST_Response( [ 'key' => wpae_get_key() ], 200 ),
        'permission_callback' => fn() => in_array( $_SERVER['REMOTE_ADDR'] ?? '', [ '127.0.0.1', '::1', 'localhost' ], true ),
    ] );

    register_rest_route( 'ai-executor/v1', '/guide', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_guide',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/guide/session', [
        'methods'             => 'POST',
        'callback'            => 'wpae_create_guide_session',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/guide/ack', [
        'methods'             => 'POST',
        'callback'            => 'wpae_ack_guide_session',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/capabilities', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_capabilities',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/logs', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_logs',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/audit', [
        'methods'             => 'POST',
        'callback'            => 'wpae_audit',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/rollback', [
        'methods'             => 'POST',
        'callback'            => 'wpae_rollback',
        'permission_callback' => 'wpae_auth_with_guide_token',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/validate', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_validate',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/page', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_page',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'elementor_writes' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/update', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_update',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'elementor_writes' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/skills', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_skills',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/skills', [
        'methods'             => 'POST',
        'callback'            => 'wpae_save_skill',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'manage_skills' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/skills/export', [
        'methods'             => 'GET',
        'callback'            => 'wpae_export_skills',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/skills/import', [
        'methods'             => 'POST',
        'callback'            => 'wpae_import_skills',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'manage_skills' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/skills/(?P<id>[a-z0-9_-]+)', [
        'methods'             => 'DELETE',
        'callback'            => 'wpae_delete_skill',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'manage_skills' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/media/upload', [
        'methods'             => 'POST',
        'callback'            => 'wpae_upload_media',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'media_upload' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/exports/create', [
        'methods'             => 'POST',
        'callback'            => 'wpae_create_export',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'exports' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/self-update', [
        'methods'             => 'POST',
        'callback'            => 'wpae_self_update',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'self_update' ),
    ] );

} );

function wpae_auth( WP_REST_Request $r ): bool {
    $provided = $r->get_header( 'X-AI-Key' )
             ?? $r->get_header( 'X-Claude-Key' )
             ?? $r->get_param( 'key' );
    return hash_equals( wpae_get_key(), (string) $provided );
}

function wpae_auth_with_guide_token( WP_REST_Request $request ) {
    if ( ! wpae_auth( $request ) ) {
        return false;
    }

    $token = (string) ( $request->get_header( 'X-WPAE-Guide-Token' ) ?: $request->get_param( 'guide_token' ) );
    $guide_hash = (string) ( $request->get_header( 'X-WPAE-Guide-Hash' ) ?: $request->get_param( 'guide_hash' ) );
    $validation = wpae_validate_guide_token( $token, $guide_hash );

    if ( $validation === true ) {
        return true;
    }

    return new WP_Error(
        'wpae_guide_token_required',
        'Write endpoints require a valid guide token. Call /guide/session, read /guide and /capabilities, then call /guide/ack.',
        [
            'status' => 403,
            'details' => $validation,
        ]
    );
}

function wpae_auth_with_capability( WP_REST_Request $request, string $capability ) {
    $guide_auth = wpae_auth_with_guide_token( $request );
    if ( $guide_auth !== true ) {
        return $guide_auth;
    }

    if ( wpae_capability_enabled( $capability ) ) {
        return true;
    }

    return new WP_Error(
        'wpae_capability_disabled',
        'This WP AI Executor capability is disabled by the site owner.',
        [
            'status' => 403,
            'capability' => $capability,
        ]
    );
}

function wpae_get_operation_logs_store(): array {
    $logs = get_option( 'wp_ai_executor_operation_logs', [] );
    return is_array( $logs ) ? $logs : [];
}

function wpae_update_operation_logs_store( array $logs ): void {
    update_option( 'wp_ai_executor_operation_logs', array_slice( array_values( $logs ), 0, WPAE_OPERATION_LOG_MAX_ENTRIES ), false );
}

function wpae_should_log_operation( WP_REST_Request $request ): bool {
    $route = (string) $request->get_route();
    $method = strtoupper( (string) $request->get_method() );

    if ( strpos( $route, '/ai-executor/v1/' ) !== 0 ) {
        return false;
    }

    if ( in_array( $route, [ '/ai-executor/v1/logs', '/ai-executor/v1/key' ], true ) ) {
        return false;
    }

    if ( in_array( $method, [ 'POST', 'DELETE' ], true ) ) {
        return true;
    }

    return in_array( $route, [ '/ai-executor/v1/audit' ], true );
}

function wpae_get_response_data_for_log( $response ): array {
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        return is_array( $data ) ? $data : [];
    }

    if ( $response instanceof WP_HTTP_Response ) {
        $data = $response->get_data();
        return is_array( $data ) ? $data : [];
    }

    if ( is_wp_error( $response ) ) {
        return [
            'error' => $response->get_error_message(),
            'code' => $response->get_error_code(),
        ];
    }

    return [];
}

function wpae_get_response_status_for_log( $response ): int {
    if ( $response instanceof WP_REST_Response || $response instanceof WP_HTTP_Response ) {
        return (int) $response->get_status();
    }

    if ( is_wp_error( $response ) ) {
        $data = $response->get_error_data();
        return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
    }

    return 200;
}

function wpae_collect_log_target_ids( WP_REST_Request $request, array $response_data ): array {
    $targets = [];
    $post_id = absint( $request->get_param( 'post_id' ) ?: ( $response_data['post_id'] ?? 0 ) );
    $attachment_id = absint( $response_data['attachment_id'] ?? 0 );
    $skill_id = (string) ( $request->get_param( 'id' ) ?: ( $response_data['deleted'] ?? '' ) );

    if ( $skill_id === '' && ! empty( $response_data['skill'] ) && is_array( $response_data['skill'] ) ) {
        $skill_id = (string) ( $response_data['skill']['id'] ?? '' );
    }

    if ( $post_id > 0 ) {
        $targets['post_id'] = $post_id;
    }

    if ( $attachment_id > 0 ) {
        $targets['attachment_id'] = $attachment_id;
    }

    if ( $skill_id !== '' ) {
        $targets['skill_id'] = wpae_normalize_skill_id( $skill_id );
    }

    if ( ! empty( $response_data['imported'] ) && is_array( $response_data['imported'] ) ) {
        $skill_ids = [];
        foreach ( $response_data['imported'] as $skill ) {
            if ( is_array( $skill ) && ! empty( $skill['id'] ) ) {
                $skill_ids[] = wpae_normalize_skill_id( (string) $skill['id'] );
            }
        }
        if ( ! empty( $skill_ids ) ) {
            $targets['skill_ids'] = array_values( array_unique( $skill_ids ) );
        }
    }

    if ( ! empty( $response_data['filename'] ) ) {
        $targets['filename'] = sanitize_file_name( (string) $response_data['filename'] );
    }

    return $targets;
}

function wpae_summarize_response_for_log( array $response_data, int $status ): array {
    $summary = [
        'ok' => $status >= 200 && $status < 300 && empty( $response_data['error'] ),
    ];

    foreach ( [ 'error', 'code', 'dry_run', 'same_file', 'validation_ok', 'imported_count', 'total_count', 'mode' ] as $key ) {
        if ( array_key_exists( $key, $response_data ) && ! is_array( $response_data[ $key ] ) ) {
            $summary[ $key ] = $response_data[ $key ];
        }
    }

    if ( isset( $response_data['ok'] ) && is_bool( $response_data['ok'] ) ) {
        $summary['ok'] = $response_data['ok'];
    }

    if ( isset( $response_data['findings'] ) && is_array( $response_data['findings'] ) ) {
        $summary['findings_count'] = count( $response_data['findings'] );
    }

    if ( isset( $response_data['errors'] ) && is_array( $response_data['errors'] ) ) {
        $summary['errors_count'] = count( $response_data['errors'] );
    }

    return $summary;
}

function wpae_record_operation_log( WP_REST_Request $request, $response ): void {
    if ( ! wpae_should_log_operation( $request ) || ! wpae_auth( $request ) ) {
        return;
    }

    $status = wpae_get_response_status_for_log( $response );
    $response_data = wpae_get_response_data_for_log( $response );
    $route = (string) $request->get_route();
    $logs = wpae_get_operation_logs_store();
    $guide_hash = (string) ( $request->get_header( 'X-WPAE-Guide-Hash' ) ?: $request->get_param( 'guide_hash' ) );

    array_unshift( $logs, [
        'id' => bin2hex( random_bytes( 8 ) ),
        'time' => gmdate( 'c' ),
        'method' => strtoupper( (string) $request->get_method() ),
        'endpoint' => preg_replace( '#^/ai-executor/v1#', '', $route ),
        'status' => $status,
        'actor' => sanitize_text_field( (string) ( $request->get_header( 'X-WPAE-Actor' ) ?: $request->get_header( 'X-AI-Agent' ) ?: 'agent' ) ),
        'ip_hash' => hash( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        'guide_hash' => $guide_hash !== '' ? $guide_hash : null,
        'target_ids' => wpae_collect_log_target_ids( $request, $response_data ),
        'rollback_snapshot_id' => sanitize_text_field( (string) ( $response_data['rollback_snapshot_id'] ?? $response_data['snapshot_id'] ?? '' ) ) ?: null,
        'validation_result' => wpae_summarize_response_for_log( $response_data, $status ),
    ] );

    wpae_update_operation_logs_store( $logs );
}

add_filter( 'rest_post_dispatch', function ( $response, WP_REST_Server $server, WP_REST_Request $request ) {
    wpae_record_operation_log( $request, $response );
    return $response;
}, 10, 3 );

function wpae_get_logs( WP_REST_Request $request ): WP_REST_Response {
    $limit = max( 1, min( WPAE_OPERATION_LOG_MAX_ENTRIES, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
    $logs = array_slice( wpae_get_operation_logs_store(), 0, $limit );

    return new WP_REST_Response( [
        'logs' => $logs,
        'count' => count( $logs ),
        'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
        'storage' => 'wp_options',
        'redaction' => 'API keys, guide tokens, raw request bodies, and raw page payloads are not logged.',
    ], 200 );
}

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
        'guide_version' => '1.6.0',
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

function wpae_prune_guide_sessions( array $sessions ): array {
    $now = time();
    foreach ( $sessions as $id => $session ) {
        if ( (int) ( $session['expires_at_unix'] ?? 0 ) < $now ) {
            unset( $sessions[ $id ] );
        }
    }
    return $sessions;
}

function wpae_create_guide_session(): WP_REST_Response {
    $sessions = wpae_prune_guide_sessions( wpae_get_guide_sessions() );
    $session_id = bin2hex( random_bytes( 16 ) );
    $expires_at_unix = time() + 15 * MINUTE_IN_SECONDS;

    $sessions[ $session_id ] = [
        'id' => $session_id,
        'guide_hash' => wpae_get_guide_hash(),
        'created_at' => gmdate( 'c' ),
        'expires_at' => gmdate( 'c', $expires_at_unix ),
        'expires_at_unix' => $expires_at_unix,
        'acked' => false,
    ];

    wpae_update_guide_sessions( $sessions );

    return new WP_REST_Response( [
        'guide_session_id' => $session_id,
        'guide_hash' => $sessions[ $session_id ]['guide_hash'],
        'expires_at' => $sessions[ $session_id ]['expires_at'],
        'required_ack_schema' => wpae_required_ack_schema(),
        'next_steps' => [
            'Read /guide and /capabilities.',
            'Call /guide/ack with guide_session_id and all required ack fields set to true.',
            'Pass X-WPAE-Guide-Token and X-WPAE-Guide-Hash to every write endpoint.',
        ],
    ], 200 );
}

function wpae_ack_guide_session( WP_REST_Request $request ) {
    $session_id = sanitize_text_field( (string) $request->get_param( 'guide_session_id' ) );
    $ack = $request->get_param( 'ack' );
    $sessions = wpae_prune_guide_sessions( wpae_get_guide_sessions() );

    if ( $session_id === '' || ! isset( $sessions[ $session_id ] ) ) {
        wpae_update_guide_sessions( $sessions );
        return new WP_REST_Response( [ 'error' => 'Invalid or expired guide_session_id.' ], 404 );
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
    if ( ! hash_equals( (string) $sessions[ $session_id ]['guide_hash'], $current_hash ) ) {
        unset( $sessions[ $session_id ] );
        wpae_update_guide_sessions( $sessions );
        return new WP_REST_Response( [ 'error' => 'Guide changed. Start a new /guide/session.', 'guide_hash' => $current_hash ], 409 );
    }

    $token = bin2hex( random_bytes( 32 ) );
    $token_hash = hash( 'sha256', $token );
    $expires_at_unix = time() + 15 * MINUTE_IN_SECONDS;

    $sessions[ $session_id ]['acked'] = true;
    $sessions[ $session_id ]['ack'] = array_intersect_key( $ack, wpae_required_ack_schema() );
    $sessions[ $session_id ]['token_hash'] = $token_hash;
    $sessions[ $session_id ]['expires_at'] = gmdate( 'c', $expires_at_unix );
    $sessions[ $session_id ]['expires_at_unix'] = $expires_at_unix;
    $sessions[ $session_id ]['acked_at'] = gmdate( 'c' );

    wpae_update_guide_sessions( $sessions );

    return new WP_REST_Response( [
        'ok' => true,
        'guide_token' => $token,
        'guide_hash' => $current_hash,
        'expires_at' => $sessions[ $session_id ]['expires_at'],
        'headers' => [
            'X-WPAE-Guide-Token' => $token,
            'X-WPAE-Guide-Hash' => $current_hash,
        ],
    ], 200 );
}

function wpae_validate_guide_token( string $token, string $guide_hash ) {
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

    wpae_update_guide_sessions( $sessions );

    return $valid ? true : [ 'error' => 'invalid_or_expired_guide_token' ];
}

function wpae_get_rollback_snapshots(): array {
    $snapshots = get_option( 'wp_ai_executor_rollback_snapshots', [] );
    return is_array( $snapshots ) ? $snapshots : [];
}

function wpae_update_rollback_snapshots( array $snapshots ): void {
    update_option( 'wp_ai_executor_rollback_snapshots', $snapshots, false );
}

function wpae_prune_rollback_snapshots( array $snapshots ): array {
    $now = time();
    foreach ( $snapshots as $id => $snapshot ) {
        if ( (int) ( $snapshot['expires_at_unix'] ?? 0 ) < $now ) {
            unset( $snapshots[ $id ] );
        }
    }

    uasort( $snapshots, function ( array $a, array $b ): int {
        return (int) ( $b['created_at_unix'] ?? 0 ) <=> (int) ( $a['created_at_unix'] ?? 0 );
    } );

    return array_slice( $snapshots, 0, WPAE_ROLLBACK_MAX_SNAPSHOTS, true );
}

function wpae_sanitize_rollback_post_ids( $post_ids ): array {
    if ( ! is_array( $post_ids ) ) {
        return [];
    }

    $post_ids = array_map( 'absint', $post_ids );
    $post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

    return array_slice( $post_ids, 0, 50 );
}

function wpae_sanitize_rollback_option_names( $option_names ): array {
    if ( ! is_array( $option_names ) ) {
        return [];
    }

    $clean = [];
    foreach ( $option_names as $name ) {
        $name = sanitize_key( (string) $name );
        if ( $name !== '' ) {
            $clean[] = $name;
        }
    }

    return array_slice( array_values( array_unique( $clean ) ), 0, 50 );
}

function wpae_capture_post_snapshot( int $post_id ): array {
    $post = get_post( $post_id, ARRAY_A );
    if ( ! is_array( $post ) ) {
        return [ 'exists' => false ];
    }

    return [
        'exists' => true,
        'post' => $post,
        'meta' => get_post_meta( $post_id ),
    ];
}

function wpae_capture_option_snapshot( string $option_name ): array {
    $sentinel = '__wpae_missing_option__';
    $value = get_option( $option_name, $sentinel );

    if ( $value === $sentinel ) {
        return [ 'exists' => false ];
    }

    return [
        'exists' => true,
        'value' => $value,
    ];
}

function wpae_create_rollback_snapshot( string $label, array $post_ids = [], array $option_names = [], array $created_post_ids = [] ): ?array {
    $post_ids = wpae_sanitize_rollback_post_ids( $post_ids );
    $option_names = wpae_sanitize_rollback_option_names( $option_names );
    $created_post_ids = wpae_sanitize_rollback_post_ids( $created_post_ids );
    $all_post_ids = array_values( array_unique( array_merge( $post_ids, $created_post_ids ) ) );

    if ( empty( $all_post_ids ) && empty( $option_names ) ) {
        return null;
    }

    $now = time();
    $snapshot_id = bin2hex( random_bytes( 12 ) );
    $snapshot = [
        'id' => $snapshot_id,
        'label' => sanitize_text_field( $label ),
        'created_at' => gmdate( 'c', $now ),
        'created_at_unix' => $now,
        'expires_at' => gmdate( 'c', $now + WPAE_ROLLBACK_TTL_SECONDS ),
        'expires_at_unix' => $now + WPAE_ROLLBACK_TTL_SECONDS,
        'posts' => [],
        'options' => [],
    ];

    foreach ( $all_post_ids as $post_id ) {
        $snapshot['posts'][ (string) $post_id ] = in_array( $post_id, $created_post_ids, true )
            ? [ 'exists' => false ]
            : wpae_capture_post_snapshot( $post_id );
    }

    foreach ( $option_names as $option_name ) {
        $snapshot['options'][ $option_name ] = wpae_capture_option_snapshot( $option_name );
    }

    $snapshots = wpae_prune_rollback_snapshots( wpae_get_rollback_snapshots() );
    $snapshots[ $snapshot_id ] = $snapshot;
    wpae_update_rollback_snapshots( wpae_prune_rollback_snapshots( $snapshots ) );

    return [
        'id' => $snapshot_id,
        'expires_at' => $snapshot['expires_at'],
    ];
}

function wpae_restore_post_snapshot( int $post_id, array $snapshot ): array {
    if ( empty( $snapshot['exists'] ) ) {
        if ( get_post( $post_id ) !== null ) {
            wp_delete_post( $post_id, true );
        }
        return [ 'post_id' => $post_id, 'action' => 'deleted_created_post' ];
    }

    $post = is_array( $snapshot['post'] ?? null ) ? $snapshot['post'] : [];
    if ( empty( $post['ID'] ) ) {
        $post['ID'] = $post_id;
    }

    $result = wp_update_post( $post, true );
    if ( is_wp_error( $result ) ) {
        return [ 'post_id' => $post_id, 'action' => 'failed', 'error' => $result->get_error_message() ];
    }

    $current_meta = get_post_meta( $post_id );
    foreach ( array_keys( $current_meta ) as $meta_key ) {
        delete_post_meta( $post_id, $meta_key );
    }

    $meta = is_array( $snapshot['meta'] ?? null ) ? $snapshot['meta'] : [];
    foreach ( $meta as $meta_key => $values ) {
        if ( ! is_array( $values ) ) {
            $values = [ $values ];
        }
        foreach ( $values as $value ) {
            add_post_meta( $post_id, $meta_key, $value );
        }
    }

    wpae_clear_elementor_cache( $post_id );

    return [ 'post_id' => $post_id, 'action' => 'restored' ];
}

function wpae_restore_option_snapshot( string $option_name, array $snapshot ): array {
    if ( empty( $snapshot['exists'] ) ) {
        delete_option( $option_name );
        return [ 'option' => $option_name, 'action' => 'deleted_created_option' ];
    }

    update_option( $option_name, $snapshot['value'] ?? null, false );
    return [ 'option' => $option_name, 'action' => 'restored' ];
}

function wpae_rollback( WP_REST_Request $request ): WP_REST_Response {
    $snapshot_id = sanitize_text_field( (string) $request->get_param( 'snapshot_id' ) );
    $snapshots = wpae_prune_rollback_snapshots( wpae_get_rollback_snapshots() );

    if ( $snapshot_id === '' || ! isset( $snapshots[ $snapshot_id ] ) ) {
        wpae_update_rollback_snapshots( $snapshots );
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid or expired rollback snapshot.' ], 404 );
    }

    $snapshot = $snapshots[ $snapshot_id ];
    $restored_posts = [];
    $restored_options = [];

    foreach ( (array) ( $snapshot['posts'] ?? [] ) as $post_id => $post_snapshot ) {
        $restored_posts[] = wpae_restore_post_snapshot( absint( $post_id ), (array) $post_snapshot );
    }

    foreach ( (array) ( $snapshot['options'] ?? [] ) as $option_name => $option_snapshot ) {
        $restored_options[] = wpae_restore_option_snapshot( sanitize_key( (string) $option_name ), (array) $option_snapshot );
    }

    unset( $snapshots[ $snapshot_id ] );
    wpae_update_rollback_snapshots( $snapshots );

    return new WP_REST_Response( [
        'ok' => true,
        'snapshot_id' => $snapshot_id,
        'label' => $snapshot['label'] ?? '',
        'restored_posts' => $restored_posts,
        'restored_options' => $restored_options,
    ], 200 );
}

function wpae_get_capabilities_payload(): array {
    $settings = wpae_get_capability_settings();

    return [
        'plugin_version' => WPAE_VERSION,
        'guide_version' => '1.6.0',
        'capability_toggles' => $settings,
        'can_execute_php' => ! empty( $settings['run'] ),
        'can_write_files_via_run' => wpae_can_run_filesystem_operations(),
        'can_self_update_plugin' => ! empty( $settings['self_update'] ),
        'can_write_elementor' => ! empty( $settings['elementor_writes'] ),
        'can_upload_media' => ! empty( $settings['media_upload'] ),
        'can_create_exports' => ! empty( $settings['exports'] ),
        'can_manage_skills' => ! empty( $settings['manage_skills'] ),
        'can_import_export_skills' => ! empty( $settings['manage_skills'] ),
        'can_audit' => true,
        'can_rollback' => true,
        'can_view_operation_logs' => true,
        'requires_guide_token_for_writes' => true,
        'elementor' => [
            'enabled_for_writes' => ! empty( $settings['elementor_writes'] ),
            'safe_endpoints' => [
                'validate' => 'POST /wp-json/ai-executor/v1/elementor/validate',
                'page' => 'POST /wp-json/ai-executor/v1/elementor/page',
                'update' => 'POST /wp-json/ai-executor/v1/elementor/update',
            ],
            'must_use_flex_containers' => true,
            'forbidden_eltypes' => [ 'section', 'column' ],
            'required_widget_key' => 'widgetType',
            'forbidden_widget_keys' => [ 'widget_type' ],
            'runtime_validation' => true,
            'supports_dry_run' => [ '/elementor/page', '/elementor/update' ],
        ],
        'rollback' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/rollback',
            'requires_guide_token' => true,
            'ttl_seconds' => WPAE_ROLLBACK_TTL_SECONDS,
            'max_snapshots' => WPAE_ROLLBACK_MAX_SNAPSHOTS,
            'storage' => 'wp_options',
            'run_snapshot_request' => [
                'rollback_targets' => [
                    'post_ids' => [ 123 ],
                    'option_names' => [ 'some_option_name' ],
                ],
            ],
        ],
        'skills' => [
            'storage' => 'wp_options',
            'safe_endpoints' => [
                'list' => 'GET /wp-json/ai-executor/v1/skills',
                'save_one' => 'POST /wp-json/ai-executor/v1/skills',
                'delete_one' => 'DELETE /wp-json/ai-executor/v1/skills/{id}',
                'export_bundle' => 'GET /wp-json/ai-executor/v1/skills/export',
                'import_bundle' => 'POST /wp-json/ai-executor/v1/skills/import',
            ],
            'import_modes' => [ 'merge', 'replace' ],
            'bundle_schema' => 'wp-ai-executor.skill-bundle',
            'max_skills_per_bundle' => 100,
            'max_content_bytes_per_skill' => 120000,
            'enforce_rule_types' => [
                'forbid_elementor_eltype',
                'require_widget_key',
                'forbid_widget_key',
                'allow_widget_type',
                'forbid_widget_type',
                'require_widget_setting',
                'require_container_setting',
                'forbid_html_pattern',
            ],
        ],
        'operation_logs' => [
            'endpoint' => 'GET /wp-json/ai-executor/v1/logs',
            'storage' => 'wp_options',
            'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
            'logged_fields' => [
                'time',
                'method',
                'endpoint',
                'status',
                'actor',
                'ip_hash',
                'guide_hash',
                'target_ids',
                'rollback_snapshot_id',
                'validation_result',
            ],
            'redaction' => [
                'api_keys',
                'guide_tokens',
                'raw_request_bodies',
                'raw_page_payloads',
                'raw_response_payloads',
                'secrets',
            ],
        ],
        'file_write_policy' => [
            'forbidden_in_run' => [ 'php_files', 'mu_plugins', 'tmp_files', 'arbitrary_paths', 'shell_commands' ],
            'filesystem_override_enabled' => wpae_can_run_filesystem_operations(),
            'allowed_endpoints' => [
                '/self-update' => ! empty( $settings['self_update'] ),
                '/media/upload' => ! empty( $settings['media_upload'] ),
                '/exports/create' => ! empty( $settings['exports'] ),
                '/elementor/validate' => true,
                '/elementor/page' => ! empty( $settings['elementor_writes'] ),
                '/elementor/update' => ! empty( $settings['elementor_writes'] ),
                '/audit' => true,
                '/logs' => true,
                '/rollback' => true,
                '/skills' => ! empty( $settings['manage_skills'] ),
                '/skills/export' => true,
                '/skills/import' => ! empty( $settings['manage_skills'] ),
            ],
        ],
        'guide_token_protocol' => [
            'session_endpoint' => 'POST /wp-json/ai-executor/v1/guide/session',
            'ack_endpoint' => 'POST /wp-json/ai-executor/v1/guide/ack',
            'required_headers_for_writes' => [ 'X-WPAE-Guide-Token', 'X-WPAE-Guide-Hash' ],
            'ttl_minutes' => 15,
        ],
    ];
}

function wpae_get_capabilities(): WP_REST_Response {
    return new WP_REST_Response( wpae_get_capabilities_payload(), 200 );
}

function wpae_get_skill_store(): array {
    $skills = get_option( 'wp_ai_executor_skills', [] );
    return is_array( $skills ) ? $skills : [];
}

function wpae_update_skill_store( array $skills ): void {
    update_option( 'wp_ai_executor_skills', $skills, false );
}

function wpae_normalize_skill_id( string $id ): string {
    $id = strtolower( trim( $id ) );
    $id = preg_replace( '/[^a-z0-9_-]+/', '-', $id );
    $id = trim( (string) $id, '-' );
    return $id !== '' ? $id : 'skill-' . wp_generate_password( 8, false, false );
}

function wpae_sort_skills( array $skills ): array {
    uasort( $skills, function ( array $a, array $b ): int {
        $priority = (int) ( $b['priority'] ?? 0 ) <=> (int) ( $a['priority'] ?? 0 );
        if ( $priority !== 0 ) {
            return $priority;
        }
        return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
    } );
    return $skills;
}

function wpae_is_list_array( array $items ): bool {
    if ( $items === [] ) {
        return true;
    }

    return array_keys( $items ) === range( 0, count( $items ) - 1 );
}

function wpae_normalize_skill_data( array $data, array $existing_skills = [] ) {
    $raw_id = (string) ( $data['id'] ?? '' );
    $raw_name = (string) ( $data['name'] ?? '' );
    $name = sanitize_text_field( $raw_name !== '' ? $raw_name : $raw_id );
    $content = (string) ( $data['content'] ?? '' );

    if ( $name === '' ) {
        return new WP_Error( 'wpae_skill_name_required', 'Skill name is required.' );
    }

    if ( trim( $content ) === '' ) {
        return new WP_Error( 'wpae_skill_content_required', 'Skill content is required.' );
    }

    if ( strlen( $content ) > 120000 ) {
        return new WP_Error( 'wpae_skill_too_large', 'Skill content exceeds 120 KB limit.' );
    }

    $id = wpae_normalize_skill_id( (string) ( $data['id'] ?? $name ) );
    $now = gmdate( 'c' );
    $enforce = $data['enforce'] ?? [];

    return [
        'id' => $id,
        'name' => $name,
        'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
        'content' => wp_check_invalid_utf8( $content ),
        'enforce' => wpae_sanitize_skill_enforce_rules( is_array( $enforce ) ? $enforce : [] ),
        'enabled' => array_key_exists( 'enabled', $data ) ? (bool) $data['enabled'] : true,
        'priority' => max( -100, min( 100, (int) ( $data['priority'] ?? 0 ) ) ),
        'created_at' => $existing_skills[ $id ]['created_at'] ?? $now,
        'updated_at' => $now,
    ];
}

function wpae_upsert_skill( array $data ) {
    $skills = wpae_get_skill_store();
    $skill = wpae_normalize_skill_data( $data, $skills );

    if ( is_wp_error( $skill ) ) {
        return $skill;
    }

    $skills[ $skill['id'] ] = $skill;
    wpae_update_skill_store( $skills );

    return $skill;
}

function wpae_build_skill_bundle( bool $include_disabled = true ): array {
    $skills = wpae_sort_skills( wpae_get_skill_store() );

    if ( ! $include_disabled ) {
        $skills = array_filter( $skills, fn( array $skill ): bool => ! empty( $skill['enabled'] ) );
    }

    return [
        'schema' => 'wp-ai-executor.skill-bundle',
        'schema_version' => 1,
        'plugin_version' => WPAE_VERSION,
        'exported_at' => gmdate( 'c' ),
        'storage' => 'wp_options',
        'file_policy' => 'No skill files are created on the server.',
        'skills' => array_values( $skills ),
    ];
}

function wpae_extract_skill_import_items( $payload ) {
    if ( is_string( $payload ) ) {
        $payload = json_decode( $payload, true );
    }

    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'wpae_invalid_skill_bundle', 'Skill import payload must be a JSON object or array.' );
    }

    if ( isset( $payload['skills'] ) && is_array( $payload['skills'] ) ) {
        return $payload['skills'];
    }

    if ( wpae_is_list_array( $payload ) ) {
        return $payload;
    }

    return new WP_Error( 'wpae_invalid_skill_bundle', 'Skill import payload must contain a skills array.' );
}

function wpae_import_skill_items( array $items, string $mode = 'merge' ) {
    $mode = $mode === 'replace' ? 'replace' : 'merge';
    $existing = $mode === 'replace' ? [] : wpae_get_skill_store();
    $next = $existing;
    $imported = [];
    $errors = [];

    if ( count( $items ) > 100 ) {
        return new WP_Error( 'wpae_skill_bundle_too_large', 'Skill bundle contains more than 100 skills.' );
    }

    foreach ( $items as $index => $item ) {
        if ( ! is_array( $item ) ) {
            $errors[] = [ 'index' => $index, 'error' => 'Skill item must be an object.' ];
            continue;
        }

        $skill = wpae_normalize_skill_data( $item, $next );
        if ( is_wp_error( $skill ) ) {
            $errors[] = [ 'index' => $index, 'error' => $skill->get_error_message() ];
            continue;
        }

        $next[ $skill['id'] ] = $skill;
        $imported[] = $skill;
    }

    if ( ! empty( $errors ) ) {
        return new WP_Error( 'wpae_skill_import_failed', 'Skill import failed validation.', [ 'errors' => $errors ] );
    }

    wpae_update_skill_store( $next );

    return [
        'mode' => $mode,
        'imported' => $imported,
        'imported_count' => count( $imported ),
        'total_count' => count( $next ),
    ];
}

function wpae_get_skills(): WP_REST_Response {
    $skills = wpae_sort_skills( wpae_get_skill_store() );
    return new WP_REST_Response( [
        'skills' => array_values( $skills ),
        'count' => count( $skills ),
    ], 200 );
}

function wpae_export_skills( WP_REST_Request $request ): WP_REST_Response {
    $include_disabled = ! $request->has_param( 'enabled_only' ) || ! (bool) $request->get_param( 'enabled_only' );
    return new WP_REST_Response( wpae_build_skill_bundle( $include_disabled ), 200 );
}

function wpae_import_skills( WP_REST_Request $request ) {
    $payload = $request->get_json_params();
    if ( empty( $payload ) && $request->has_param( 'bundle' ) ) {
        $payload = $request->get_param( 'bundle' );
    }

    $items = wpae_extract_skill_import_items( $payload );
    if ( is_wp_error( $items ) ) {
        return new WP_REST_Response( [ 'error' => $items->get_error_message() ], 400 );
    }

    $result = wpae_import_skill_items( $items, sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'merge' ) ) );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [
            'error' => $result->get_error_message(),
            'details' => $result->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( array_merge( [ 'ok' => true ], $result ), 200 );
}

function wpae_save_skill( WP_REST_Request $request ) {
    $skill = wpae_upsert_skill( [
        'id' => $request->get_param( 'id' ),
        'name' => $request->get_param( 'name' ),
        'description' => $request->get_param( 'description' ),
        'content' => $request->get_param( 'content' ),
        'enforce' => $request->get_param( 'enforce' ),
        'enabled' => $request->has_param( 'enabled' ) ? (bool) $request->get_param( 'enabled' ) : true,
        'priority' => $request->get_param( 'priority' ),
    ] );

    if ( is_wp_error( $skill ) ) {
        $status = $skill->get_error_code() === 'wpae_skill_too_large' ? 413 : 400;
        return new WP_REST_Response( [ 'error' => $skill->get_error_message() ], $status );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'skill' => $skill,
    ], 200 );
}

function wpae_delete_skill( WP_REST_Request $request ) {
    $id = wpae_normalize_skill_id( (string) $request['id'] );
    $skills = wpae_get_skill_store();

    if ( ! isset( $skills[ $id ] ) ) {
        return new WP_REST_Response( [ 'error' => 'Skill not found.' ], 404 );
    }

    unset( $skills[ $id ] );
    wpae_update_skill_store( $skills );

    return new WP_REST_Response( [ 'ok' => true, 'deleted' => $id ], 200 );
}

function wpae_get_enabled_skills_for_guide(): array {
    $skills = wpae_sort_skills( wpae_get_skill_store() );
    $enabled = [];

    foreach ( $skills as $skill ) {
        if ( empty( $skill['enabled'] ) ) {
            continue;
        }
        $enabled[] = $skill;
    }

    return $enabled;
}

function wpae_sanitize_skill_enforce_rules( array $rules ): array {
    $allowed_types = [
        'forbid_elementor_eltype',
        'require_widget_key',
        'forbid_widget_key',
        'allow_widget_type',
        'forbid_widget_type',
        'require_widget_setting',
        'require_container_setting',
        'forbid_html_pattern',
    ];
    $clean = [];

    foreach ( $rules as $rule ) {
        if ( ! is_array( $rule ) ) {
            continue;
        }

        $type = sanitize_key( (string) ( $rule['type'] ?? '' ) );
        $value = sanitize_text_field( (string) ( $rule['value'] ?? '' ) );
        $target = sanitize_key( (string) ( $rule['target'] ?? '' ) );

        if ( ! in_array( $type, $allowed_types, true ) || $value === '' ) {
            continue;
        }

        $clean_rule = [
            'type' => $type,
            'value' => $value,
        ];

        if ( $target !== '' ) {
            $clean_rule['target'] = $target;
        }

        $clean[] = $clean_rule;

        if ( count( $clean ) >= 50 ) {
            break;
        }
    }

    return $clean;
}

function wpae_get_enforceable_skill_rules(): array {
    $skills = wpae_get_enabled_skills_for_guide();
    $rules = [];

    foreach ( $skills as $skill ) {
        if ( empty( $skill['enforce'] ) || ! is_array( $skill['enforce'] ) ) {
            continue;
        }

        foreach ( $skill['enforce'] as $rule ) {
            if ( is_array( $rule ) ) {
                $rules[] = [
                    'skill_id' => $skill['id'] ?? 'unknown',
                    'type' => $rule['type'] ?? '',
                    'value' => $rule['value'] ?? '',
                ];
            }
        }
    }

    return $rules;
}

function wpae_get_elementor_data_from_request( WP_REST_Request $request ) {
    $data = $request->get_param( 'elementor_data' );
    if ( $data === null ) {
        $data = $request->get_param( 'data' );
    }

    if ( is_string( $data ) ) {
        $decoded = json_decode( $data, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'wpae_invalid_elementor_json', 'elementor_data must be valid JSON array data.' );
        }
        return $decoded;
    }

    if ( ! is_array( $data ) ) {
        return new WP_Error( 'wpae_missing_elementor_data', 'elementor_data array is required.' );
    }

    return $data;
}

function wpae_validate_elementor_data_array( array $elementor_data ): array {
    return wpae_validate_elementor_data_string( (string) wp_json_encode( $elementor_data ) );
}

function wpae_clear_elementor_cache( int $post_id ): void {
    delete_post_meta( $post_id, '_elementor_css' );

    if ( class_exists( '\Elementor\Plugin' ) ) {
        try {
            $elementor = \Elementor\Plugin::$instance;
            if ( isset( $elementor->files_manager ) && method_exists( $elementor->files_manager, 'clear_cache' ) ) {
                $elementor->files_manager->clear_cache();
            }
        } catch ( Throwable $e ) {
            // Cache clearing is best effort; saving valid metadata is the critical path.
        }
    }
}

function wpae_save_elementor_page_data( int $post_id, array $elementor_data, string $template = 'elementor_canvas' ) {
    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_Error( 'wpae_invalid_post_id', 'A valid post_id is required.' );
    }

    $errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $errors ) ) {
        return new WP_Error( 'wpae_invalid_elementor_data', 'Elementor data failed validation.', [ 'errors' => $errors ] );
    }

    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
    update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
    update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
    update_post_meta( $post_id, '_wp_page_template', $template ?: 'elementor_canvas' );
    wpae_clear_elementor_cache( $post_id );

    return true;
}

function wpae_elementor_validate( WP_REST_Request $request ): WP_REST_Response {
    $elementor_data = wpae_get_elementor_data_from_request( $request );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    $errors = wpae_validate_elementor_data_array( $elementor_data );

    return new WP_REST_Response( [
        'ok' => empty( $errors ),
        'errors' => $errors,
    ], empty( $errors ) ? 200 : 422 );
}

function wpae_elementor_update( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $elementor_data = wpae_get_elementor_data_from_request( $request );

    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'would_write' => [
                '_elementor_data',
                '_elementor_edit_mode',
                '_elementor_template_type',
                '_elementor_version',
                '_wp_page_template',
            ],
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_update:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
    ], 200 );
}

function wpae_elementor_page( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: 'Elementor Page' ) );
    $slug = sanitize_title( (string) $request->get_param( 'slug' ) );
    $status = sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'publish' ) );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $allowed_statuses = [ 'publish', 'draft', 'private', 'pending' ];
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $elementor_data = wpae_get_elementor_data_from_request( $request );

    if ( ! in_array( $status, $allowed_statuses, true ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid page status.' ], 400 );
    }

    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
        ], 422 );
    }

    if ( $post_id > 0 && get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Target post_id does not exist.' ], 404 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id ?: null,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'template' => $template,
        ], 200 );
    }

    $post_args = [
        'post_title' => $title,
        'post_status' => $status,
        'post_type' => 'page',
    ];

    if ( $slug !== '' ) {
        $post_args['post_name'] = $slug;
    }

    $rollback_snapshot = null;
    $is_new_post = $post_id <= 0;

    if ( $post_id > 0 ) {
        $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_page:update:' . $post_id, [ $post_id ] );
        $post_args['ID'] = $post_id;
        $result = wp_update_post( $post_args, true );
    } else {
        $result = wp_insert_post( $post_args, true );
    }

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 500 );
    }

    $post_id = (int) $result;
    if ( $is_new_post ) {
        $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_page:create:' . $post_id, [], [], [ $post_id ] );
    }

    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'post_id' => $post_id,
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'status' => get_post_status( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
    ], 200 );
}

function wpae_upload_media( WP_REST_Request $request ) {
    $filename = sanitize_file_name( (string) $request->get_param( 'filename' ) );
    $mime_type = sanitize_text_field( (string) $request->get_param( 'mime_type' ) );
    $content_base64 = (string) $request->get_param( 'content_base64' );
    $post_parent = absint( $request->get_param( 'post_parent' ) );

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    if ( $filename === '' || $content_base64 === '' ) {
        return new WP_REST_Response( [ 'error' => 'filename and content_base64 are required.' ], 400 );
    }

    if ( ! isset( $allowed_mimes[ $mime_type ] ) ) {
        return new WP_REST_Response( [ 'error' => 'mime_type is not allowed.', 'allowed' => array_keys( $allowed_mimes ) ], 400 );
    }

    $bytes = base64_decode( $content_base64, true );
    if ( $bytes === false ) {
        return new WP_REST_Response( [ 'error' => 'Invalid base64 content.' ], 400 );
    }

    if ( strlen( $bytes ) > 8 * 1024 * 1024 ) {
        return new WP_REST_Response( [ 'error' => 'Media file exceeds 8 MB limit.' ], 413 );
    }

    $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    if ( $extension !== $allowed_mimes[ $mime_type ] ) {
        $filename .= '.' . $allowed_mimes[ $mime_type ];
    }

    $upload = wp_upload_bits( $filename, null, $bytes );
    if ( ! empty( $upload['error'] ) ) {
        return new WP_REST_Response( [ 'error' => $upload['error'] ], 500 );
    }

    $attachment_id = wp_insert_attachment( [
        'post_mime_type' => $mime_type,
        'post_title' => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
        'post_content' => '',
        'post_status' => 'inherit',
    ], $upload['file'], $post_parent );

    if ( is_wp_error( $attachment_id ) ) {
        return new WP_REST_Response( [ 'error' => $attachment_id->get_error_message() ], 500 );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    if ( is_array( $metadata ) ) {
        wp_update_attachment_metadata( $attachment_id, $metadata );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'id' => $attachment_id,
        'url' => wp_get_attachment_url( $attachment_id ),
        'file' => basename( $upload['file'] ),
        'mime_type' => $mime_type,
    ], 200 );
}

function wpae_create_export( WP_REST_Request $request ) {
    $filename = sanitize_file_name( (string) ( $request->get_param( 'filename' ) ?: 'wp-ai-export-' . gmdate( 'Ymd-His' ) . '.json' ) );
    $payload = $request->get_param( 'data' );

    if ( $payload === null ) {
        return new WP_REST_Response( [ 'error' => 'data is required.' ], 400 );
    }

    if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'json' ) {
        $filename .= '.json';
    }

    $json = is_string( $payload )
        ? $payload
        : wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

    if ( ! is_string( $json ) || strlen( $json ) > 1024 * 1024 ) {
        return new WP_REST_Response( [ 'error' => 'Export JSON exceeds 1 MB limit or could not be encoded.' ], 413 );
    }

    $decoded = json_decode( $json, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_REST_Response( [ 'error' => 'Export data must be valid JSON.' ], 400 );
    }

    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['error'] ) ) {
        return new WP_REST_Response( [ 'error' => $upload_dir['error'] ], 500 );
    }

    $dir = trailingslashit( $upload_dir['basedir'] ) . 'wp-ai-executor/exports';
    if ( ! wp_mkdir_p( $dir ) ) {
        return new WP_REST_Response( [ 'error' => 'Could not create exports directory.' ], 500 );
    }

    $path = trailingslashit( $dir ) . $filename;
    $written = file_put_contents( $path, $json, LOCK_EX );
    if ( $written === false ) {
        return new WP_REST_Response( [ 'error' => 'Could not write export file.' ], 500 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'filename' => $filename,
        'bytes' => $written,
        'url' => trailingslashit( $upload_dir['baseurl'] ) . 'wp-ai-executor/exports/' . rawurlencode( $filename ),
    ], 200 );
}

function wpae_self_update( WP_REST_Request $request ) {
    $default_url = 'https://raw.githubusercontent.com/DiasMazhenov/wp-ai-executor/main/wp-ai-executor.php';
    $source_url  = trim( (string) ( $request->get_param( 'source_url' ) ?: $default_url ) );
    $dry_run     = (bool) $request->get_param( 'dry_run' );

    if ( ! wpae_is_allowed_self_update_url( $source_url ) ) {
        return new WP_REST_Response( [
            'error' => 'Self-update source_url is not allowed.',
            'allowed' => 'https://raw.githubusercontent.com/DiasMazhenov/wp-ai-executor/*/wp-ai-executor.php',
        ], 400 );
    }

    $response = wp_remote_get( $source_url, [
        'timeout' => 20,
        'redirection' => 3,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [
            'error' => 'Failed to download update.',
            'message' => $response->get_error_message(),
        ], 502 );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = (string) wp_remote_retrieve_body( $response );

    if ( $status !== 200 ) {
        return new WP_REST_Response( [
            'error' => 'Update download returned non-200 status.',
            'status' => $status,
        ], 502 );
    }

    $validation_errors = wpae_validate_self_update_file( $body );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'error' => 'Downloaded plugin file failed validation.',
            'details' => $validation_errors,
        ], 422 );
    }

    $target = __FILE__;
    $current_hash = hash_file( 'sha256', $target );
    $new_hash = hash( 'sha256', $body );

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'target' => $target,
            'source_url' => $source_url,
            'current_sha256' => $current_hash,
            'new_sha256' => $new_hash,
            'same_file' => hash_equals( $current_hash, $new_hash ),
        ], 200 );
    }

    $written = file_put_contents( $target, $body, LOCK_EX );
    if ( $written === false ) {
        return new WP_REST_Response( [
            'error' => 'Failed to write plugin file.',
            'target' => $target,
        ], 500 );
    }

    clearstatcache( true, $target );

    return new WP_REST_Response( [
        'ok' => true,
        'target' => $target,
        'source_url' => $source_url,
        'bytes' => $written,
        'previous_sha256' => $current_hash,
        'new_sha256' => hash_file( 'sha256', $target ),
    ], 200 );
}

function wpae_is_allowed_self_update_url( string $source_url ): bool {
    $parts = wp_parse_url( $source_url );
    if ( ! is_array( $parts ) ) {
        return false;
    }

    if ( ( $parts['scheme'] ?? '' ) !== 'https' ) {
        return false;
    }

    if ( ( $parts['host'] ?? '' ) !== 'raw.githubusercontent.com' ) {
        return false;
    }

    $path = $parts['path'] ?? '';
    return (bool) preg_match( '#^/DiasMazhenov/wp-ai-executor/[^/]+/wp-ai-executor\.php$#', $path );
}

function wpae_validate_self_update_file( string $contents ): array {
    $errors = [];

    if ( strlen( $contents ) < 5000 ) {
        $errors[] = 'File is unexpectedly small.';
    }

    if ( strlen( $contents ) > 500000 ) {
        $errors[] = 'File is unexpectedly large.';
    }

    if ( strncmp( ltrim( $contents ), '<?php', 5 ) !== 0 ) {
        $errors[] = 'File must start with <?php.';
    }

    $required_markers = [
        'Plugin Name: WP AI Executor',
        'function wpae_run',
        'function wpae_self_update',
        "register_rest_route( 'ai-executor/v1'",
        'Filesystem writes are disabled by WP AI Executor policy.',
    ];

    foreach ( $required_markers as $marker ) {
        if ( strpos( $contents, $marker ) === false ) {
            $errors[] = 'Missing marker: ' . $marker;
        }
    }

    return $errors;
}

function wpae_run( WP_REST_Request $request ) {
    $code = trim( (string) $request->get_param( 'code' ) );
    if ( $code === '' ) {
        return new WP_REST_Response( [ 'error' => 'No code provided' ], 400 );
    }

    if ( (bool) $request->get_param( 'dry_run' ) ) {
        return new WP_REST_Response( [
            'error' => 'dry_run is not supported for arbitrary /run PHP.',
            'help' => 'Use /elementor/validate, /elementor/page dry_run, /elementor/update dry_run, or pass rollback_targets for a real rollback snapshot.',
        ], 400 );
    }

    $forbidden_file_operation = wpae_detect_forbidden_file_operation( $code );
    if (
        $forbidden_file_operation &&
        ! wpae_can_run_filesystem_operations()
    ) {
        return new WP_REST_Response( [
            'error' => 'Filesystem writes are disabled by WP AI Executor policy.',
            'blocked_operation' => $forbidden_file_operation,
            'help' => 'Use WordPress APIs for posts, post meta, options, Elementor data, and cache clearing. Do not create temporary loaders, mu-plugins, PHP/JS/CSS/JSON files, or files in /tmp.',
        ], 403 );
    }

    $rollback_snapshot = null;
    $rollback_targets = $request->get_param( 'rollback_targets' );
    if ( is_array( $rollback_targets ) ) {
        $rollback_snapshot = wpae_create_rollback_snapshot(
            'run',
            is_array( $rollback_targets['post_ids'] ?? null ) ? $rollback_targets['post_ids'] : [],
            is_array( $rollback_targets['option_names'] ?? null ) ? $rollback_targets['option_names'] : []
        );
    }

    $elementor_before = wpae_capture_elementor_data_snapshot();

    ob_start();
    $result = null;
    try {
        $fn     = eval( 'return function() { ' . $code . ' };' );
        $result = $fn();
    } catch ( Throwable $e ) {
        ob_end_clean();
        return new WP_REST_Response( [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        ], 500 );
    }
    $output = ob_get_clean();

    $elementor_validation = wpae_validate_changed_elementor_data( $elementor_before );
    if ( ! $elementor_validation['ok'] ) {
        return new WP_REST_Response( [
            'error' => 'Invalid Elementor data blocked by WP AI Executor policy.',
            'details' => $elementor_validation['errors'],
            'rolled_back_post_ids' => $elementor_validation['rolled_back_post_ids'],
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
            'output' => $output,
        ], 422 );
    }

    return new WP_REST_Response( [
        'return_value' => $result,
        'output' => $output,
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
    ], 200 );
}

function wpae_detect_forbidden_file_operation( string $code ): ?string {
    $patterns = [
        'file_put_contents',
        'fopen',
        'fwrite',
        'fputs',
        'mkdir',
        'rmdir',
        'unlink',
        'rename',
        'copy',
        'touch',
        'chmod',
        'chown',
        'chgrp',
        'symlink',
        'link',
        'move_uploaded_file',
        'ZipArchive',
        'Phar',
        'WP_Filesystem',
        'wp_mkdir_p',
        'wp_delete_file',
        'wp_tempnam',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
        'popen',
    ];

    foreach ( $patterns as $pattern ) {
        if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $code ) ) {
            return $pattern;
        }
    }

    $path_patterns = [
        '/tmp/',
        'wp-content/mu-plugins',
        'wp-content/plugins',
        'wp-content/themes',
        'landing_data.b64',
        'elem-loader.php',
    ];

    foreach ( $path_patterns as $pattern ) {
        if ( stripos( $code, $pattern ) !== false ) {
            return $pattern;
        }
    }

    return null;
}

function wpae_capture_elementor_data_snapshot(): array {
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id ASC",
            '_elementor_data'
        ),
        ARRAY_A
    );

    $snapshot = [];
    foreach ( $rows as $row ) {
        $snapshot[ (int) $row['post_id'] ] = (string) $row['meta_value'];
    }

    return $snapshot;
}

function wpae_validate_changed_elementor_data( array $before ): array {
    $after = wpae_capture_elementor_data_snapshot();
    $errors = [];
    $rolled_back_post_ids = [];
    $elementor_writes_enabled = wpae_capability_enabled( 'elementor_writes' );

    foreach ( $after as $post_id => $raw_data ) {
        if ( array_key_exists( $post_id, $before ) && $before[ $post_id ] === $raw_data ) {
            continue;
        }

        if ( ! $elementor_writes_enabled ) {
            $errors[] = [
                'post_id' => $post_id,
                'errors' => [ 'Elementor writes are disabled by the site owner.' ],
            ];

            if ( array_key_exists( $post_id, $before ) ) {
                update_post_meta( $post_id, '_elementor_data', wp_slash( $before[ $post_id ] ) );
            } else {
                delete_post_meta( $post_id, '_elementor_data' );
            }
            $rolled_back_post_ids[] = $post_id;
            continue;
        }

        $post_errors = wpae_validate_elementor_data_string( $raw_data );
        if ( empty( $post_errors ) ) {
            continue;
        }

        $errors[] = [
            'post_id' => $post_id,
            'errors' => $post_errors,
        ];

        if ( array_key_exists( $post_id, $before ) ) {
            update_post_meta( $post_id, '_elementor_data', wp_slash( $before[ $post_id ] ) );
        } else {
            delete_post_meta( $post_id, '_elementor_data' );
        }
        $rolled_back_post_ids[] = $post_id;
    }

    return [
        'ok' => empty( $errors ),
        'errors' => $errors,
        'rolled_back_post_ids' => $rolled_back_post_ids,
    ];
}

function wpae_validate_elementor_data_string( string $raw_data ): array {
    $data = json_decode( $raw_data, true );
    if ( ! is_array( $data ) ) {
        return [ 'Elementor _elementor_data must be valid JSON array data.' ];
    }

    $errors = [];
    wpae_validate_elementor_elements_recursive( $data, 'root', $errors, wpae_get_enforceable_skill_rules() );
    return $errors;
}

function wpae_validate_elementor_elements_recursive( array $elements, string $path, array &$errors, array $skill_rules = [] ): void {
    $allowed_widget_types = [];
    foreach ( $skill_rules as $rule ) {
        if ( ( $rule['type'] ?? '' ) === 'allow_widget_type' && ! empty( $rule['value'] ) ) {
            $allowed_widget_types[] = (string) $rule['value'];
        }
    }
    $allowed_widget_types = array_values( array_unique( $allowed_widget_types ) );

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            $errors[] = "{$path}.{$index}: element must be an object/array.";
            continue;
        }

        $element_path = $path . '.' . ( $element['id'] ?? $index );
        $el_type = $element['elType'] ?? null;

        if ( $el_type === 'section' || $el_type === 'column' ) {
            $errors[] = "{$element_path}: legacy Elementor elType={$el_type} is forbidden; use elType=container.";
        }

        if ( array_key_exists( 'widget_type', $element ) ) {
            $errors[] = "{$element_path}: widget_type is forbidden; use camelCase widgetType.";
        }

        if ( $el_type === 'widget' && empty( $element['widgetType'] ) ) {
            $errors[] = "{$element_path}: widget element must have non-empty camelCase widgetType.";
        }

        foreach ( $skill_rules as $rule ) {
            $rule_type = $rule['type'] ?? '';
            $rule_value = $rule['value'] ?? '';
            $rule_target = $rule['target'] ?? '';
            $skill_id = $rule['skill_id'] ?? 'unknown';
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

            if ( $rule_type === 'forbid_elementor_eltype' && $el_type === $rule_value ) {
                $errors[] = "{$element_path}: elType={$el_type} is forbidden by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_widget_key' && $el_type === 'widget' && array_key_exists( $rule_value, $element ) ) {
                $errors[] = "{$element_path}: widget key {$rule_value} is forbidden by skill {$skill_id}.";
            }

            if ( $rule_type === 'require_widget_key' && $el_type === 'widget' && empty( $element[ $rule_value ] ) ) {
                $errors[] = "{$element_path}: widget key {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_widget_type' && $el_type === 'widget' && $widget_type === $rule_value ) {
                $errors[] = "{$element_path}: widgetType={$widget_type} is forbidden by skill {$skill_id}.";
            }

            if (
                $rule_type === 'require_widget_setting' &&
                $el_type === 'widget' &&
                ( $rule_target === '' || $rule_target === $widget_type ) &&
                empty( $settings[ $rule_value ] )
            ) {
                $errors[] = "{$element_path}: widget setting {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'require_container_setting' && $el_type === 'container' && empty( $settings[ $rule_value ] ) ) {
                $errors[] = "{$element_path}: container setting {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_html_pattern' && $el_type === 'widget' && $widget_type === 'html' ) {
                $html = (string) ( $settings['html'] ?? $settings['editor'] ?? '' );

                if ( stripos( $html, $rule_value ) !== false ) {
                    $errors[] = "{$element_path}: HTML widget content matches forbidden pattern from skill {$skill_id}.";
                }
            }
        }

        if ( $el_type === 'widget' && ! empty( $allowed_widget_types ) && ! in_array( (string) ( $element['widgetType'] ?? '' ), $allowed_widget_types, true ) ) {
            $errors[] = "{$element_path}: widgetType=" . (string) ( $element['widgetType'] ?? '' ) . ' is not in the enabled skills allowlist.';
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_validate_elementor_elements_recursive( $element['elements'], $element_path, $errors, $skill_rules );
        }
    }
}

function wpae_audit_add_finding( array &$findings, string $code, string $status, string $message, array $details = [] ): void {
    $findings[] = [
        'code' => $code,
        'status' => $status,
        'message' => $message,
        'details' => $details,
    ];
}

function wpae_collect_elementor_audit_stats( array $elements, array &$stats ): void {
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $el_type = (string) ( $element['elType'] ?? '' );
        if ( $el_type === 'container' ) {
            $stats['containers']++;
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $has_background = ! empty( $settings['background_color'] )
                || ! empty( $settings['background_background'] )
                || ! empty( $settings['_background_color'] )
                || ! empty( $settings['_background_background'] );

            if ( $has_background ) {
                $stats['containers_with_native_background']++;
            }
        }

        if ( $el_type === 'widget' ) {
            $stats['widgets']++;
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

            if ( $widget_type === 'html' ) {
                $stats['html_widgets']++;
                $html = (string) ( $settings['html'] ?? $settings['editor'] ?? '' );
                if ( preg_match( '/<(main|section|article|header|footer|nav)\b/i', $html ) || stripos( $html, 'elementor-section' ) !== false ) {
                    $stats['html_widget_layout_risks']++;
                }
            }

            if ( $widget_type === 'heading' && trim( (string) ( $settings['title'] ?? '' ) ) === '' ) {
                $stats['empty_heading_widgets']++;
            }

            if ( $widget_type === 'text-editor' && trim( wp_strip_all_tags( (string) ( $settings['editor'] ?? '' ) ) ) === '' ) {
                $stats['empty_text_widgets']++;
            }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_collect_elementor_audit_stats( $element['elements'], $stats );
        }
    }
}

function wpae_audit( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $findings = [];

    if ( $post_id <= 0 ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'post_id is required.' ], 400 );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Post not found.' ], 404 );
    }

    wpae_audit_add_finding(
        $findings,
        'post_exists',
        'pass',
        'Post exists.',
        [ 'post_id' => $post_id, 'post_type' => $post->post_type, 'post_status' => $post->post_status ]
    );

    $edit_mode = (string) get_post_meta( $post_id, '_elementor_edit_mode', true );
    wpae_audit_add_finding(
        $findings,
        'elementor_edit_mode',
        $edit_mode === 'builder' ? 'pass' : 'fail',
        $edit_mode === 'builder' ? 'Elementor edit mode is builder.' : 'Elementor edit mode is not builder.',
        [ 'value' => $edit_mode ]
    );

    $template = (string) get_post_meta( $post_id, '_wp_page_template', true );
    wpae_audit_add_finding(
        $findings,
        'page_template',
        $template !== '' ? 'pass' : 'warn',
        $template !== '' ? 'Page template is set.' : 'Page template is empty.',
        [ 'value' => $template ]
    );

    $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
    if ( $raw_data === '' ) {
        wpae_audit_add_finding( $findings, 'elementor_data_present', 'fail', '_elementor_data is empty.' );
        return new WP_REST_Response( [
            'ok' => false,
            'post_id' => $post_id,
            'findings' => $findings,
        ], 422 );
    }

    $elementor_data = json_decode( $raw_data, true );
    if ( ! is_array( $elementor_data ) ) {
        wpae_audit_add_finding( $findings, 'elementor_data_json', 'fail', '_elementor_data is not valid JSON array data.' );
        return new WP_REST_Response( [
            'ok' => false,
            'post_id' => $post_id,
            'findings' => $findings,
        ], 422 );
    }

    wpae_audit_add_finding( $findings, 'elementor_data_json', 'pass', '_elementor_data decodes as JSON array data.' );

    $validation_errors = wpae_validate_elementor_data_string( $raw_data );
    wpae_audit_add_finding(
        $findings,
        'elementor_policy_validation',
        empty( $validation_errors ) ? 'pass' : 'fail',
        empty( $validation_errors ) ? 'Elementor data passes runtime policy validation.' : 'Elementor data failed runtime policy validation.',
        [ 'errors' => $validation_errors ]
    );

    $stats = [
        'containers' => 0,
        'containers_with_native_background' => 0,
        'widgets' => 0,
        'html_widgets' => 0,
        'html_widget_layout_risks' => 0,
        'empty_heading_widgets' => 0,
        'empty_text_widgets' => 0,
    ];
    wpae_collect_elementor_audit_stats( $elementor_data, $stats );

    wpae_audit_add_finding(
        $findings,
        'native_containers',
        $stats['containers'] > 0 ? 'pass' : 'fail',
        $stats['containers'] > 0 ? 'Elementor layout uses containers.' : 'No Elementor containers found.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'native_backgrounds',
        $stats['containers_with_native_background'] > 0 ? 'pass' : 'warn',
        $stats['containers_with_native_background'] > 0 ? 'At least one container has native background settings.' : 'No native container background settings found; verify contrast is not CSS-only.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'html_widget_scope',
        $stats['html_widget_layout_risks'] === 0 ? 'pass' : 'warn',
        $stats['html_widget_layout_risks'] === 0 ? 'No HTML widget layout risks detected.' : 'Some HTML widgets may contain layout/content markup instead of enhancement-only CSS/JS.',
        $stats
    );

    wpae_audit_add_finding(
        $findings,
        'native_widget_content',
        ( $stats['empty_heading_widgets'] === 0 && $stats['empty_text_widgets'] === 0 ) ? 'pass' : 'warn',
        ( $stats['empty_heading_widgets'] === 0 && $stats['empty_text_widgets'] === 0 ) ? 'No empty heading/text widgets detected.' : 'Some native content widgets appear empty.',
        $stats
    );

    $has_failures = false;
    foreach ( $findings as $finding ) {
        if ( ( $finding['status'] ?? '' ) === 'fail' ) {
            $has_failures = true;
            break;
        }
    }

    return new WP_REST_Response( [
        'ok' => ! $has_failures,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'stats' => $stats,
        'findings' => $findings,
    ], $has_failures ? 422 : 200 );
}

function wpae_get_guide(): WP_REST_Response {
    return new WP_REST_Response( wpae_agent_guide(), 200 );
}

function wpae_agent_guide(): array {
    return [
        'name' => 'WP AI Executor Agent Guide',
        'version' => '1.6.0',
        'plugin_version' => WPAE_VERSION,
        'purpose' => 'Use this guide before automating WordPress and Elementor through WP AI Executor.',
        'embedded_skill_packs' => [
            'frontend_design' => 'Distilled frontend-design rules for distinctive visual direction, typography, layout, motion, and copy.',
            'wordpress_elementor_dev' => 'Distilled WordPress/Elementor development rules for native Elementor data, REST execution, security, and verification.',
        ],
        'custom_skills' => wpae_get_enabled_skills_for_guide(),
        'capabilities' => wpae_get_capabilities_payload(),
        'guide_token_protocol' => [
            'required_for_write_endpoints' => true,
            'session_endpoint' => 'POST /wp-json/ai-executor/v1/guide/session',
            'ack_endpoint' => 'POST /wp-json/ai-executor/v1/guide/ack',
            'required_write_headers' => [
                'X-WPAE-Guide-Token',
                'X-WPAE-Guide-Hash',
            ],
            'ttl_minutes' => 15,
            'rule' => 'Before any write endpoint, create a guide session, read /guide and /capabilities, acknowledge all required fields, then send the returned guide token and hash with the write request.',
        ],
        'agent_prompt' => wpae_agent_prompt(),
        'workflow' => [
            '1. Inspect WordPress, PHP, theme, and Elementor status with a small read-only PHP request.',
            '2. For page work, prefer /elementor/validate, /elementor/page, and /elementor/update over raw PHP through /run.',
            '3. Use /elementor/validate or dry_run=true on /elementor/page and /elementor/update before a real write when building complex pages.',
            '4. Use native Elementor Flexbox Containers only for layout: elType=container plus native widgets. Never use legacy elType=section or elType=column.',
            '5. Never create external files. Use WordPress APIs and database metadata only; no temp files, loaders, mu-plugins, PHP/JS/CSS/JSON files, or filesystem writes.',
            '6. Design the page before building: define subject, audience, job, palette, type roles, layout, and one signature element.',
            '7. Save the returned rollback_snapshot_id after writes and use /rollback if the result is wrong.',
            '8. Verify with /audit, HTTP status, permalink, post status, _elementor_edit_mode, _elementor_data, visible HTML text, and inspect any html widgets if present.',
            '9. Use /logs for recent operation metadata when debugging, but never expect raw payloads or secrets there.',
        ],
        'frontend_design' => [
            'principles' => [
                'Avoid generic template aesthetics; ground the visual direction in the subject matter.',
                'Open with a hero that states a clear design thesis.',
                'Choose a compact color system, intentional typography roles, and one memorable signature element.',
                'Use structure as information, not decoration. Numbering should mean sequence.',
                'Keep copy specific, active, and useful from the visitor side of the screen.',
                'Spend boldness in one place; keep the rest disciplined and responsive.',
            ],
            'anti_generic_defaults' => [
                'Do not default to a generic hero, generic gradient cards, generic dark SaaS page, or one-note palette.',
                'Do not use decorative numbering unless the content is actually sequential.',
                'Do not use stock-like filler sections; every section must move the visitor toward the page job.',
                'Do not stop at a technically valid layout; evaluate whether the page looks intentionally designed.',
            ],
            'planning_template' => [
                'subject' => 'What is being sold or explained?',
                'audience' => 'Who must understand and act?',
                'single_job' => 'What should the page make the visitor do?',
                'palette' => '4-6 named hex colors.',
                'type_roles' => 'Display, body, and utility/caption roles.',
                'layout' => 'Short section map or wireframe.',
                'signature' => 'One distinctive element justified by the brief.',
            ],
            'design_quality_bar' => [
                'Hero must be the design thesis and should contain the page signature.',
                'Typography must use deliberate roles: display, body, utility/caption.',
                'Layout must be stable at desktop, tablet, and mobile widths.',
                'Motion must support comprehension: reveal, hover, progress, or focused ambient animation.',
                'Copy must be concrete and action-oriented.',
            ],
        ],
        'wordpress_elementor' => [
            'stack' => [
                'Remote WordPress site',
                'Elementor only',
                'WP AI Executor as the automation bridge',
                'No Oxygen',
                'No Novamira',
                'HTML widget only for small JS snippets or complex CSS that cannot reasonably be expressed through Elementor settings',
                'Avoid browser automation unless absolutely required',
            ],
            'filesystem_policy' => [
                'required' => true,
                'rule' => 'Do not create, modify, rename, copy, chmod, or delete files on the WordPress server through WP AI Executor.',
                'forbidden' => [
                    'Temporary loaders such as elem-loader.php.',
                    'Files in /tmp, wp-content/mu-plugins, wp-content/plugins, wp-content/themes, or uploads created as implementation scratch space.',
                    'External PHP, JS, CSS, JSON, base64, cache, or helper files.',
                    'Direct filesystem or shell/process calls such as file_put_contents, fopen, fwrite, mkdir, unlink, rename, copy, chmod, ZipArchive, Phar, WP_Filesystem, wp_mkdir_p, exec, shell_exec, system, passthru, proc_open, and popen.',
                ],
                'allowed_instead' => [
                    'Use wp_insert_post, wp_update_post, update_post_meta, update_option, delete_option, and Elementor metadata.',
                    'Use Elementor cache APIs to clear/regenerate generated CSS; do not write CSS files manually.',
                    'Return data directly from /run instead of writing temporary files.',
                    'Use /media/upload for validated media library files.',
                    'Use /exports/create for JSON export files under uploads/wp-ai-executor/exports.',
                ],
                'runtime_enforcement' => 'By default, /run rejects common filesystem write/delete operations unless the site owner enables the filesystem_writes capability or WP_AI_EXECUTOR_ALLOW_FILE_WRITES is explicitly defined.',
                'self_update_policy' => [
                    'endpoint' => 'POST /wp-json/ai-executor/v1/self-update',
                    'rule' => 'Plugin self-update is allowed only through the dedicated self-update endpoint, never through arbitrary /run filesystem writes.',
                    'source' => 'Only allowlisted raw GitHub URLs from DiasMazhenov/wp-ai-executor ending in wp-ai-executor.php are accepted.',
                    'target' => 'The endpoint writes only to the current plugin file (__FILE__) after validating required plugin markers.',
                    'dry_run' => 'Pass dry_run=true to compare hashes without writing.',
                ],
            ],
            'custom_skills_policy' => [
                'endpoint' => 'GET/POST/DELETE /wp-json/ai-executor/v1/skills plus GET /skills/export and POST /skills/import',
                'storage' => 'Skills are stored in wp_options as text/JSON, not as files.',
                'rule' => 'Agents must read custom_skills in this guide and apply enabled skills by priority.',
                'limits' => 'Each skill content is limited to 120 KB and is never executed as code.',
                'bundle_import_export' => 'Skill bundles are JSON objects with schema=wp-ai-executor.skill-bundle and a skills array. Import mode can be merge or replace. Bundles are stored in the database only.',
                'enforceable_rules' => [
                    'forbid_elementor_eltype',
                    'require_widget_key',
                    'forbid_widget_key',
                    'allow_widget_type',
                    'forbid_widget_type',
                    'require_widget_setting',
                    'require_container_setting',
                    'forbid_html_pattern',
                ],
            ],
            'runtime_elementor_validation' => [
                'required' => true,
                'rule' => 'The executor validates changed _elementor_data after each /run call and rejects invalid Elementor JSON even if the agent ignored the guide.',
                'blocked' => [
                    'Legacy elType=section.',
                    'Legacy elType=column.',
                    'Snake-case widget_type.',
                    'Any elType=widget element with missing or empty widgetType.',
                ],
                'rollback' => 'If invalid _elementor_data is detected, the changed _elementor_data meta is rolled back to its pre-run value when possible.',
            ],
            'rollback_policy' => [
                'endpoint' => 'POST /wp-json/ai-executor/v1/rollback',
                'storage' => 'Short-lived snapshots are stored in wp_options, never in files.',
                'ttl_seconds' => WPAE_ROLLBACK_TTL_SECONDS,
                'rule' => 'For structured Elementor writes, read rollback_snapshot_id and rollback_expires_at from the response. To revert, call /rollback with the snapshot_id and valid guide-token headers.',
                'dry_run' => [
                    '/elementor/page' => 'Pass dry_run=true to validate the requested create/update without writing.',
                    '/elementor/update' => 'Pass dry_run=true to validate the requested metadata update without writing.',
                    '/run' => 'Arbitrary PHP dry_run is not supported because the plugin cannot reliably simulate unknown mutations. Use rollback_targets instead.',
                ],
                'run_rollback_targets' => [
                    'body' => [
                        'code' => 'return update_option("example_option", "new-value");',
                        'rollback_targets' => [
                            'post_ids' => [ 123 ],
                            'option_names' => [ 'example_option' ],
                        ],
                    ],
                    'rule' => 'Before risky /run mutations, pass known post_ids and option_names so the plugin captures a rollback snapshot first.',
                ],
            ],
            'operation_logs_policy' => [
                'endpoint' => 'GET /wp-json/ai-executor/v1/logs',
                'storage' => 'Recent operation metadata is stored in wp_options with a capped entry count.',
                'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
                'logged' => [
                    'endpoint',
                    'method',
                    'status',
                    'actor hint',
                    'guide hash',
                    'target IDs',
                    'rollback snapshot ID',
                    'validation summary',
                ],
                'redacted' => [
                    'API keys',
                    'guide tokens',
                    'raw request bodies',
                    'raw page payloads',
                    'raw response payloads',
                    'secrets',
                ],
            ],
            'native_elementor_first' => [
                'required' => true,
                'rule' => 'Build page structure and content from native Elementor Flexbox Containers and widgets so the user can edit content and styling in the Elementor editor panel. Use elType=container for all layout nodes.',
                'layout_system' => [
                    'required' => 'Elementor Flexbox Containers only.',
                    'allowed_layout_eltypes' => [
                        'container',
                    ],
                    'forbidden_legacy_eltypes' => [
                        'section',
                        'column',
                    ],
                    'rule' => 'Do not create, import, or preserve legacy Section/Column layouts. Convert every layout wrapper to nested containers with flex_direction, content_width, width, gap, padding, and responsive settings.',
                ],
                'allowed_widget_types' => [
                    'heading',
                    'text-editor',
                    'button',
                    'icon-list',
                    'image',
                    'image-box',
                    'icon-box',
                    'divider',
                    'spacer',
                    'counter',
                    'progress',
                    'testimonial',
                    'tabs',
                    'accordion',
                    'toggle',
                ],
                'forbidden_widget_types' => [
                    'shortcode',
                ],
                'conditional_widget_types' => [
                    'html' => 'Allowed only for small JavaScript snippets or complex CSS enhancements that cannot reasonably be expressed with native Elementor settings. Never use it as the main page markup/content/layout container.',
                ],
                'content_placement' => [
                    'Headlines must live in heading widget settings.title.',
                    'Body copy must live in text-editor widget settings.editor.',
                    'Calls to action must live in button widget settings.text and settings.link.',
                    'Lists must live in icon-list repeater settings when practical.',
                    'Cards, columns, grids, hero panels, and sections must be Flexbox Containers with settings and child widgets.',
                    'Critical visual state required for readability must live in native Elementor settings first: background_color, text color, border, border_radius, padding, margin, width, min-height, gap, and alignment.',
                ],
                'forbidden_patterns' => [
                    'Do not create temporary loader files, mu-plugins, helper PHP files, external CSS/JS files, JSON/base64 payload files, or scratch files anywhere on the server.',
                    'Do not use legacy Elementor sections or columns: elType=section and elType=column are forbidden.',
                    'Do not put full page markup into an Elementor HTML widget.',
                    'Do not use inline CSS/JS blobs to fake the main page structure.',
                    'Do not replace editable Elementor controls with opaque HTML.',
                    'Do not rely on enhancement CSS as the only source for essential backgrounds, contrast, spacing, or card borders.',
                ],
                'verification' => [
                    'Confirm the solution did not create or require any external files.',
                    'Traverse _elementor_data recursively.',
                    'Confirm all layout elements are elType=container and all content elements are allowed native widgets.',
                    'Confirm there are zero elements with elType=section or elType=column.',
                    'If html widgets exist, confirm each one is limited to JS or complex CSS enhancements, not page content/layout.',
                    'Confirm important text lives in heading/text-editor/button/icon-list settings.',
                    'Confirm any visually critical card, panel, hero, or dark section has native Elementor background and border settings before relying on CSS.',
                ],
            ],
            'html_enhancement_policy' => [
                'allowed' => true,
                'allowed_for' => [
                    'Google Fonts or other font loading when the site has no better typography pipeline.',
                    'Scoped CSS polish for classed Elementor containers/widgets.',
                    'Small JavaScript interactions such as scroll reveal, hover helpers, tabs state, counters, or progressive enhancement.',
                    'Complex responsive CSS that Elementor settings cannot express cleanly.',
                ],
                'requirements' => [
                    'Scope CSS under project-specific classes, e.g. .wpae-* or page-specific .mz-*.',
                    'Do not target .elementor-widget-container as the primary selector.',
                    'Use !important only as an enhancement fallback for scoped selectors when Elementor/theme CSS wins specificity.',
                    'Do not use !important to compensate for missing native Elementor settings on critical backgrounds, colors, borders, spacing, or layout.',
                    'Respect prefers-reduced-motion for animation.',
                    'Use vanilla JavaScript in an IIFE; avoid jQuery unless WordPress/Elementor dependency forces it.',
                    'HTML widget must be enhancement-only and removable without losing the page content.',
                ],
            ],
            'page_meta' => [
                '_elementor_edit_mode' => 'builder',
                '_elementor_template_type' => 'wp-page',
                '_elementor_version' => 'Use ELEMENTOR_VERSION when defined.',
                '_elementor_data' => 'JSON-encoded Elementor element array, stored with wp_slash().',
                '_wp_page_template' => 'elementor_canvas for full landing pages when appropriate.',
            ],
            'element_shape' => [
                'container' => [
                    'id' => 'unique 7-8 character string',
                    'elType' => 'container',
                    'isInner' => false,
                    'settings' => 'Container/Flexbox settings.',
                    'elements' => 'Nested widgets or containers.',
                ],
                'widget' => [
                    'id' => 'unique 7-8 character string',
                    'elType' => 'widget',
                    'widgetType' => 'Required camelCase key. Native editable Elementor widget, e.g. heading, text-editor, button, icon-list, image, divider, spacer. HTML widget is allowed only for JS or complex CSS enhancements, never for main layout/content.',
                    'isInner' => false,
                    'settings' => 'Widget control values.',
                    'elements' => [],
                ],
            ],
            'elementor_data_rules' => [
                'Use recursive arrays of containers and widgets.',
                'Every element needs id, elType, isInner, settings, and elements.',
                'Every widget element must use the exact camelCase key widgetType. Never use widget_type, widget_type_name, type, or name as substitutes.',
                'If elType is widget and widgetType is missing or empty, Elementor will render an empty/broken widget; treat this as a blocker.',
                'Never emit legacy elType=section or elType=column. If source data contains them, convert to nested elType=container before saving.',
                'Use Flexbox Container settings for layout: flex_direction, content_width, width, min_height, gap, padding, margin, justify_content, align_items, flex_wrap, and responsive variants.',
                'Use deterministic short ids when possible so future updates can target stable elements.',
                'Use _css_classes in settings to attach scoped enhancement styles.',
                'For readable sections and cards, duplicate essential styling in settings before adding CSS: background_background, background_color, title/text colors, border_border, border_color, border_width, border_radius, padding, margin, gap, width, min-height, and alignment.',
                'Clear/regenerate Elementor CSS cache after writing page data when Elementor classes are available.',
            ],
            'verification_checklist' => [
                'HTTP status for permalink is 200.',
                'Post status is publish unless the user requested draft.',
                '_wp_page_template is elementor_canvas for full landing pages when appropriate.',
                '_elementor_edit_mode is builder.',
                '_elementor_data decodes as JSON array.',
                '_elementor_data contains no legacy section or column elements.',
                'Every elType=widget element has non-empty widgetType and no widget_type key.',
                'Core text is stored in native widget settings, not opaque HTML.',
                'Any html widget is enhancement-only.',
                'Critical backgrounds, borders, spacing, and contrast are present in native Elementor settings, with CSS only refining or reinforcing them.',
                'No external files, temporary loaders, mu-plugins, scratch files, or filesystem writes were created or required.',
                'Desktop and mobile layout should not have obvious overlap or horizontal overflow.',
            ],
            'php_snippet' => wpae_elementor_page_snippet(),
        ],
        'security' => [
            'Treat X-AI-Key as sensitive WordPress automation access, restricted by site-owner capability toggles and guide-token flow.',
            'Never commit, log, or expose real keys in frontend code.',
            'Never create temporary loaders, mu-plugins, PHP/JS/CSS/JSON/base64 files, or scratch files on the server.',
            'Filesystem write/delete operations are blocked by default in /run; do not ask agents to bypass this.',
            'Prefer server/firewall IP restrictions for production.',
            'Run read-only checks before writes and verify after writes.',
        ],
    ];
}

function wpae_agent_prompt(): string {
    return <<<'PROMPT'
You are operating a remote WordPress site through WP AI Executor.
Before writing, fetch and follow this guide as the source of truth. Inspect the environment first. Read /capabilities and respect site-owner capability toggles; a disabled capability is a hard stop even with a valid key. Read and apply any enabled custom_skills by priority. Write endpoints require a guide token: call /guide/session, read /guide and /capabilities, call /guide/ack, then send X-WPAE-Guide-Token and X-WPAE-Guide-Hash with every write request. Never create external files on the WordPress server: no temporary loaders, mu-plugins, helper PHP files, CSS/JS/JSON/base64 payload files, scratch files, or files in /tmp. Use WordPress APIs and Elementor metadata only; /run blocks common filesystem write/delete operations by default. Prefer /elementor/validate, /elementor/page, and /elementor/update over raw PHP for Elementor pages. Use dry_run=true on /elementor/page or /elementor/update before complex writes; arbitrary /run dry_run is not supported, so pass rollback_targets.post_ids and rollback_targets.option_names before risky /run mutations. Save rollback_snapshot_id from write responses and call /rollback with snapshot_id if the result must be reverted. For Elementor pages, design first: define subject, audience, single page job, palette, type roles, layout, and one distinctive signature element. Apply the embedded frontend_design pack to avoid generic pages, and apply the wordpress_elementor_dev pack to build editable Elementor output. Use only native Elementor Flexbox Containers for layout: elType=container plus editable native widgets. Never use legacy Elementor Sections or Columns; elType=section and elType=column are forbidden and must be converted to containers before saving. Every widget must use the exact camelCase widgetType key; widget_type is forbidden and causes empty widgets. Put critical backgrounds, readable text colors, borders, spacing, dimensions, and alignment into native Elementor settings first; scoped CSS, including selective !important, may reinforce or refine them but must not be the only source of essential contrast or layout. The Elementor HTML widget is allowed only for small JavaScript snippets or complex CSS enhancements when native settings are not enough; never use it as the main page markup/content/layout container. Do not use shortcode widgets, Oxygen, or Novamira for page layout/content. After writing, run /audit and the verification checklist: published URL, Elementor meta, decoded _elementor_data, zero section/column elements, no external files, native widget content placement, native critical visual settings, and html widgets enhancement-only. Use /logs for recent operation metadata when debugging; logs never include API keys, guide tokens, raw request bodies, raw page payloads, or secrets. Do not expose API keys.
PROMPT;
}

function wpae_elementor_page_snippet(): string {
    return <<<'PHP'
$page_id = wp_insert_post([
    'post_title'  => 'Landing Page',
    'post_name'   => 'landing-page',
    'post_status' => 'publish',
    'post_type'   => 'page',
], true);

if ( is_wp_error( $page_id ) ) {
    return [ 'ok' => false, 'error' => $page_id->get_error_message() ];
}

$elementor_data = [
    [
        'id'       => 'hero01',
        'elType'   => 'container',
        'isInner'  => false,
        'settings' => [
            'content_width'  => 'boxed',
            'flex_direction' => 'column',
        ],
        'elements' => [
            [
                'id'         => 'title01',
                'elType'     => 'widget',
                'widgetType' => 'heading',
                'isInner'    => false,
                'settings'   => [
                    'title'       => 'Landing headline',
                    'header_size' => 'h1',
                ],
                'elements'   => [],
            ],
        ],
    ],
];

update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );

return [ 'ok' => true, 'id' => $page_id, 'url' => get_permalink( $page_id ) ];
PHP;
}

// ── Страница настроек ──────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_options_page(
        'WP AI Executor',
        'AI Executor',
        'manage_options',
        'wp-ai-executor',
        'wpae_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'wpae_settings', 'wp_ai_executor_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    // Обработка регенерации ключа.
    if (
        isset( $_POST['wpae_regenerate'] ) &&
        check_admin_referer( 'wpae_regenerate_key' )
    ) {
        update_option( 'wp_ai_executor_key', bin2hex( random_bytes( 32 ) ) );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&regenerated=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_capabilities'] ) &&
        check_admin_referer( 'wpae_save_capabilities' )
    ) {
        $input = isset( $_POST['wpae_capabilities'] ) && is_array( $_POST['wpae_capabilities'] )
            ? wp_unslash( $_POST['wpae_capabilities'] )
            : [];

        wpae_update_capability_settings( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&capabilities_saved=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_skill_ui'] ) &&
        check_admin_referer( 'wpae_save_skill_ui' )
    ) {
        $raw_enforce = isset( $_POST['wpae_skill_enforce'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_enforce'] ) )
            : '';
        $enforce = [];

        if ( $raw_enforce !== '' ) {
            $decoded = json_decode( $raw_enforce, true );
            if ( is_array( $decoded ) ) {
                $enforce = $decoded;
            } else {
                wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_error=1' ) );
                exit;
            }
        }

        $skill = wpae_upsert_skill( [
            'id' => isset( $_POST['wpae_skill_id'] ) ? wp_unslash( $_POST['wpae_skill_id'] ) : '',
            'name' => isset( $_POST['wpae_skill_name'] ) ? wp_unslash( $_POST['wpae_skill_name'] ) : '',
            'description' => isset( $_POST['wpae_skill_description'] ) ? wp_unslash( $_POST['wpae_skill_description'] ) : '',
            'content' => isset( $_POST['wpae_skill_content'] ) ? wp_unslash( $_POST['wpae_skill_content'] ) : '',
            'enforce' => $enforce,
            'enabled' => ! empty( $_POST['wpae_skill_enabled'] ),
            'priority' => isset( $_POST['wpae_skill_priority'] ) ? wp_unslash( $_POST['wpae_skill_priority'] ) : 0,
        ] );

        $result = is_wp_error( $skill ) ? 'skill_error' : 'skill_saved';
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . $result . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_delete_skill_ui'] ) &&
        check_admin_referer( 'wpae_delete_skill_ui' )
    ) {
        $id = wpae_normalize_skill_id( isset( $_POST['wpae_delete_skill_id'] ) ? (string) wp_unslash( $_POST['wpae_delete_skill_id'] ) : '' );
        $skills = wpae_get_skill_store();

        if ( isset( $skills[ $id ] ) ) {
            unset( $skills[ $id ] );
            wpae_update_skill_store( $skills );
        }

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_deleted=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_import_skills_ui'] ) &&
        check_admin_referer( 'wpae_import_skills_ui' )
    ) {
        $bundle = isset( $_POST['wpae_skill_bundle_json'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_bundle_json'] ) )
            : '';
        $mode = isset( $_POST['wpae_skill_import_mode'] )
            ? sanitize_key( (string) wp_unslash( $_POST['wpae_skill_import_mode'] ) )
            : 'merge';
        $items = wpae_extract_skill_import_items( $bundle );
        $result = is_wp_error( $items ) ? $items : wpae_import_skill_items( $items, $mode );

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'skill_import_error' : 'skill_imported' ) . '=1' ) );
        exit;
    }
} );

function wpae_settings_page() {
    $key                = wpae_get_key();
    $site_url           = get_rest_url( null, 'ai-executor/v1/run' );
    $guide_url          = get_rest_url( null, 'ai-executor/v1/guide' );
    $capabilities_url   = get_rest_url( null, 'ai-executor/v1/capabilities' );
    $logs_url           = get_rest_url( null, 'ai-executor/v1/logs' );
    $regen              = isset( $_GET['regenerated'] );
    $capabilities_saved = isset( $_GET['capabilities_saved'] );
    $skill_saved        = isset( $_GET['skill_saved'] );
    $skill_deleted      = isset( $_GET['skill_deleted'] );
    $skill_error        = isset( $_GET['skill_error'] );
    $skill_imported     = isset( $_GET['skill_imported'] );
    $skill_import_error = isset( $_GET['skill_import_error'] );
    $capabilities       = wpae_get_capability_settings();
    $capability_labels  = wpae_capability_labels();
    $skills             = wpae_sort_skills( wpae_get_skill_store() );
    $operation_logs     = array_slice( wpae_get_operation_logs_store(), 0, 8 );
    $skill_bundle_json  = wp_json_encode( wpae_build_skill_bundle(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    $enabled_count      = count( array_filter( $capabilities ) );
    $total_count        = count( $capabilities );
    $filesystem_locked  = ! wpae_can_run_filesystem_operations();
    ?>
    <style>
        .wpae-dashboard {
            --wpae-bg: #f6f7f9;
            --wpae-panel: #ffffff;
            --wpae-panel-soft: #f8fafc;
            --wpae-text: #111827;
            --wpae-muted: #64748b;
            --wpae-border: #d9e0ea;
            --wpae-accent: #16a34a;
            --wpae-accent-dark: #15803d;
            --wpae-danger: #b91c1c;
            --wpae-code: #0f172a;
            --wpae-code-text: #dbeafe;
            max-width: 1180px;
            color: var(--wpae-text);
        }
        .wpae-dashboard * { box-sizing: border-box; }
        .wpae-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
            gap: 16px;
            align-items: stretch;
            margin: 18px 0;
        }
        .wpae-hero-main,
        .wpae-card {
            background: var(--wpae-panel);
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .wpae-hero-main {
            padding: 24px;
            border-left: 4px solid var(--wpae-accent);
        }
        .wpae-kicker {
            margin: 0 0 8px;
            color: var(--wpae-accent-dark);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .wpae-title {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 0;
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: 0;
        }
        .wpae-version {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #e8f5ee;
            color: #166534;
            font-size: 13px;
            font-weight: 700;
        }
        .wpae-lead {
            max-width: 760px;
            margin: 10px 0 0;
            color: var(--wpae-muted);
            font-size: 14px;
            line-height: 1.55;
        }
        .wpae-status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            padding: 16px;
        }
        .wpae-stat {
            min-height: 78px;
            padding: 14px;
            background: var(--wpae-panel-soft);
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
        }
        .wpae-stat-label {
            margin: 0 0 7px;
            color: var(--wpae-muted);
            font-size: 12px;
            font-weight: 600;
        }
        .wpae-stat-value {
            margin: 0;
            font-size: 22px;
            line-height: 1.1;
            font-weight: 800;
        }
        .wpae-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .wpae-card {
            padding: 18px;
        }
        .wpae-card-wide {
            grid-column: 1 / -1;
        }
        .wpae-card h2 {
            margin: 0 0 6px;
            font-size: 18px;
            line-height: 1.25;
        }
        .wpae-card h3 {
            margin: 18px 0 8px;
            font-size: 14px;
        }
        .wpae-card p {
            margin: 0 0 12px;
            color: var(--wpae-muted);
            line-height: 1.5;
        }
        .wpae-field-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .wpae-input {
            width: 100%;
            min-height: 38px;
            padding: 8px 11px;
            border: 1px solid var(--wpae-border);
            border-radius: 7px;
            background: #fff;
            color: var(--wpae-text);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
        }
        .wpae-button {
            min-height: 38px;
            padding: 7px 12px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 700;
        }
        .wpae-button:focus-visible,
        .wpae-input:focus-visible,
        .wpae-toggle input:focus-visible {
            outline: 2px solid var(--wpae-accent);
            outline-offset: 2px;
        }
        .wpae-danger-button {
            color: var(--wpae-danger) !important;
            border-color: var(--wpae-danger) !important;
        }
        .wpae-code {
            margin: 0;
            padding: 14px;
            overflow-x: auto;
            border-radius: 8px;
            background: var(--wpae-code);
            color: var(--wpae-code-text);
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .wpae-code-light {
            background: #f8fafc;
            color: #1f2937;
            border: 1px solid var(--wpae-border);
        }
        .wpae-textarea {
            width: 100%;
            min-height: 180px;
            padding: 11px;
            border: 1px solid var(--wpae-border);
            border-radius: 7px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: vertical;
        }
        .wpae-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .wpae-form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 700;
        }
        .wpae-skill-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .wpae-skill-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-skill-item h3 {
            margin: 0 0 4px;
            font-size: 14px;
        }
        .wpae-skill-meta {
            color: var(--wpae-muted);
            font-size: 12px;
        }
        .wpae-cap-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .wpae-toggle {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            min-height: 92px;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-toggle input {
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }
        .wpae-toggle strong {
            display: block;
            margin-bottom: 4px;
            color: var(--wpae-text);
        }
        .wpae-toggle span {
            display: block;
            color: var(--wpae-muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .wpae-alert {
            margin: 12px 0;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            font-weight: 600;
        }
        .wpae-security {
            border-color: #fde68a;
            background: #fffbeb;
        }
        .wpae-security strong {
            display: block;
            margin-bottom: 8px;
        }
        .wpae-security ul {
            margin: 0 0 0 18px;
            color: #713f12;
        }
        @media (max-width: 960px) {
            .wpae-hero,
            .wpae-grid,
            .wpae-cap-list {
                grid-template-columns: 1fr;
            }
            .wpae-field-row {
                flex-direction: column;
            }
            .wpae-form-grid,
            .wpae-skill-item {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="wrap wpae-dashboard">
        <section class="wpae-hero" aria-labelledby="wpae-title">
            <div class="wpae-hero-main">
                <p class="wpae-kicker">Панель управления агентами</p>
                <h1 id="wpae-title" class="wpae-title">
                    WP AI Executor
                    <span class="wpae-version">v<?php echo esc_html( WPAE_VERSION ); ?></span>
                </h1>
                <p class="wpae-lead">
                    REST-мост для Codex, Claude, GPT, Gemini, Qwen и других агентов.
                    Управляйте доступом, проверяйте Elementor-структуру и держите опасные операции под контролем.
                </p>
            </div>

            <div class="wpae-card">
                <div class="wpae-status-grid">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Разрешения</p>
                        <p class="wpae-stat-value"><?php echo esc_html( $enabled_count . '/' . $total_count ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Файловая запись</p>
                        <p class="wpae-stat-value"><?php echo $filesystem_locked ? 'Выкл.' : 'Вкл.'; ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Guide-токен</p>
                        <p class="wpae-stat-value">15 мин</p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Elementor</p>
                        <p class="wpae-stat-value"><?php echo ! empty( $capabilities['elementor_writes'] ) ? 'Вкл.' : 'Выкл.'; ?></p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ( $regen ) : ?>
            <div class="wpae-alert" role="status">Секретный ключ успешно сгенерирован заново.</div>
        <?php endif; ?>

        <?php if ( $capabilities_saved ) : ?>
            <div class="wpae-alert" role="status">Настройки разрешений сохранены.</div>
        <?php endif; ?>

        <?php if ( $skill_saved ) : ?>
            <div class="wpae-alert" role="status">Custom skill сохранен.</div>
        <?php endif; ?>

        <?php if ( $skill_deleted ) : ?>
            <div class="wpae-alert" role="status">Custom skill удален.</div>
        <?php endif; ?>

        <?php if ( $skill_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось сохранить skill: проверьте название, содержимое и JSON enforce.</div>
        <?php endif; ?>

        <?php if ( $skill_imported ) : ?>
            <div class="wpae-alert" role="status">Пакет skills импортирован.</div>
        <?php endif; ?>

        <?php if ( $skill_import_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось импортировать пакет: проверьте JSON, поле skills и содержимое каждого skill.</div>
        <?php endif; ?>

        <div class="wpae-grid">
            <div class="wpae-card">
                <h2>REST endpoint</h2>
                <p>Основной адрес для выполнения PHP через защищенный REST API.</p>
                <label for="wpae-rest-url">REST URL</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-rest-url" type="text" value="<?php echo esc_attr( $site_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $site_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Секретный ключ</h2>
                <p>Передавайте этот ключ в заголовке <code>X-AI-Key</code>. Не публикуйте его в frontend-коде.</p>
                <label for="wpae-key">X-AI-Key</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" type="text" id="wpae-key" value="<?php echo esc_attr( $key ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $key ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>

                <form method="post" style="margin-top:12px" onsubmit="return confirm('Сгенерировать новый секретный ключ? Агентам со старым ключом потребуется обновление.')">
                    <?php wp_nonce_field( 'wpae_regenerate_key' ); ?>
                    <input type="hidden" name="wpae_regenerate" value="1" />
                    <button type="submit" class="button wpae-button wpae-danger-button">Сгенерировать новый ключ</button>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Разрешения агента</h2>
                <p>
                    Ключ остается один, но владелец сайта управляет тем, что агенту разрешено делать.
                    Все write endpoints дополнительно требуют свежий guide token.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_capabilities' ); ?>
                    <input type="hidden" name="wpae_save_capabilities" value="1" />

                    <div class="wpae-cap-list">
                    <?php foreach ( $capability_labels as $capability => $meta ) : ?>
                        <label class="wpae-toggle">
                            <input type="checkbox"
                                name="wpae_capabilities[<?php echo esc_attr( $capability ); ?>]"
                                value="1"
                                <?php checked( ! empty( $capabilities[ $capability ] ) ); ?> />
                            <span>
                                <strong><?php echo esc_html( $meta['label'] ); ?></strong>
                                <span><?php echo esc_html( $meta['description'] ); ?></span>
                                <?php if ( $capability === 'filesystem_writes' && defined( 'WP_AI_EXECUTOR_ALLOW_FILE_WRITES' ) && WP_AI_EXECUTOR_ALLOW_FILE_WRITES ) : ?>
                                    <span><strong>Переопределение в wp-config.php сейчас включено.</strong></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить разрешения</button>
                    </p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Пользовательские skills</h2>
                <p>
                    Загружайте собственные инструкции в формате <code>SKILL.md</code>. Они хранятся в базе WordPress,
                    попадают в <code>/guide</code> и не создают файлов на сервере.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_skill_ui' ); ?>
                    <input type="hidden" name="wpae_save_skill_ui" value="1" />

                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-skill-name">Название</label>
                            <input class="wpae-input" id="wpae-skill-name" name="wpae_skill_name" type="text" placeholder="frontend-design" required />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-id">ID</label>
                            <input class="wpae-input" id="wpae-skill-id" name="wpae_skill_id" type="text" placeholder="frontend-design" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-priority">Приоритет</label>
                            <input class="wpae-input" id="wpae-skill-priority" name="wpae_skill_priority" type="number" min="-100" max="100" value="10" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-enabled">Статус</label>
                            <label class="wpae-toggle" style="min-height:38px;padding:9px;margin:0">
                                <input id="wpae-skill-enabled" name="wpae_skill_enabled" type="checkbox" value="1" checked />
                                <span><strong>Включить skill</strong></span>
                            </label>
                        </div>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-description">Описание</label>
                        <input class="wpae-input" id="wpae-skill-description" name="wpae_skill_description" type="text" placeholder="Правила дизайна, Elementor или проекта" />
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-content">Содержимое SKILL.md</label>
                        <textarea class="wpae-textarea" id="wpae-skill-content" name="wpae_skill_content" placeholder="# Skill instructions..." required></textarea>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-enforce">Enforce JSON</label>
                        <textarea class="wpae-textarea" id="wpae-skill-enforce" name="wpae_skill_enforce" style="min-height:92px" placeholder='[{"type":"forbid_elementor_eltype","value":"section"},{"type":"require_widget_key","value":"widgetType"}]'></textarea>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить skill</button>
                    </p>
                </form>

                <div class="wpae-grid wpae-grid-two" style="margin-top:18px">
                    <form method="post" style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <?php wp_nonce_field( 'wpae_import_skills_ui' ); ?>
                        <input type="hidden" name="wpae_import_skills_ui" value="1" />
                        <h3 style="margin-top:0">Импорт пакета</h3>
                        <p>Вставьте JSON bundle. Режим merge обновит совпадающие ID, replace полностью заменит текущие skills.</p>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-import-mode">Режим</label>
                            <select class="wpae-input" id="wpae-skill-import-mode" name="wpae_skill_import_mode">
                                <option value="merge">Merge: добавить и обновить</option>
                                <option value="replace">Replace: заменить все</option>
                            </select>
                        </div>
                        <div class="wpae-form-field" style="margin-top:12px">
                            <label for="wpae-skill-bundle-json">JSON bundle</label>
                            <textarea class="wpae-textarea" id="wpae-skill-bundle-json" name="wpae_skill_bundle_json" style="min-height:180px" placeholder='{"schema":"wp-ai-executor.skill-bundle","skills":[]}' required></textarea>
                        </div>
                        <p style="margin-top:14px">
                            <button type="submit" class="button button-primary wpae-button">Импортировать</button>
                        </p>
                    </form>

                    <div style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <h3 style="margin-top:0">Экспорт пакета</h3>
                        <p>Этот JSON можно перенести на другой WordPress сайт с WP AI Executor. Файлы на сервере не создаются.</p>
                        <textarea class="wpae-textarea" readonly style="min-height:265px" onclick="this.select()"><?php echo esc_textarea( (string) $skill_bundle_json ); ?></textarea>
                    </div>
                </div>

                <div class="wpae-skill-list" aria-label="Установленные custom skills">
                    <?php if ( empty( $skills ) ) : ?>
                        <div class="wpae-skill-item">
                            <div>
                                <h3>Skills пока не загружены</h3>
                                <div class="wpae-skill-meta">Добавьте SKILL.md через форму выше.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $skills as $skill ) : ?>
                            <div class="wpae-skill-item">
                                <div>
                                    <h3><?php echo esc_html( (string) ( $skill['name'] ?? $skill['id'] ?? 'skill' ) ); ?></h3>
                                    <div class="wpae-skill-meta">
                                        ID: <code><?php echo esc_html( (string) ( $skill['id'] ?? '' ) ); ?></code>
                                        · приоритет: <?php echo esc_html( (string) ( $skill['priority'] ?? 0 ) ); ?>
                                        · <?php echo ! empty( $skill['enabled'] ) ? 'включен' : 'выключен'; ?>
                                        · enforce: <?php echo esc_html( (string) count( is_array( $skill['enforce'] ?? null ) ? $skill['enforce'] : [] ) ); ?>
                                    </div>
                                    <?php if ( ! empty( $skill['description'] ) ) : ?>
                                        <div class="wpae-skill-meta"><?php echo esc_html( (string) $skill['description'] ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <form method="post" onsubmit="return confirm('Удалить custom skill?')">
                                    <?php wp_nonce_field( 'wpae_delete_skill_ui' ); ?>
                                    <input type="hidden" name="wpae_delete_skill_ui" value="1" />
                                    <input type="hidden" name="wpae_delete_skill_id" value="<?php echo esc_attr( (string) ( $skill['id'] ?? '' ) ); ?>" />
                                    <button type="submit" class="button wpae-button wpae-danger-button">Удалить</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Guide и разрешения</h2>
                <p>Агент должен читать эти endpoints перед записью и следовать возвращенным правилам.</p>
                <label for="wpae-guide-url">URL guide</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-guide-url" type="text" value="<?php echo esc_attr( $guide_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $guide_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
                <label for="wpae-capabilities-url" style="display:block;margin-top:12px">URL разрешений</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-capabilities-url" type="text" value="<?php echo esc_attr( $capabilities_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $capabilities_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Журнал операций</h2>
                <p>Последние действия агентов без ключей, токенов и raw payload.</p>
                <label for="wpae-logs-url">URL журнала</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-logs-url" type="text" value="<?php echo esc_attr( $logs_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $logs_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>

                <div class="wpae-skill-list" style="margin-top:14px">
                    <?php if ( empty( $operation_logs ) ) : ?>
                        <div class="wpae-skill-item">
                            <div>
                                <h3>Записей пока нет</h3>
                                <div class="wpae-skill-meta">Журнал появится после write/audit запросов.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $operation_logs as $entry ) : ?>
                            <div class="wpae-skill-item">
                                <div>
                                    <h3><?php echo esc_html( (string) ( $entry['method'] ?? '' ) . ' ' . ( $entry['endpoint'] ?? '' ) ); ?></h3>
                                    <div class="wpae-skill-meta">
                                        <?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?>
                                        · status <?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?>
                                        · actor <?php echo esc_html( (string) ( $entry['actor'] ?? 'agent' ) ); ?>
                                    </div>
                                    <?php if ( ! empty( $entry['target_ids'] ) ) : ?>
                                        <div class="wpae-skill-meta">targets: <code><?php echo esc_html( (string) wp_json_encode( $entry['target_ids'] ) ); ?></code></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $entry['rollback_snapshot_id'] ) ) : ?>
                                        <div class="wpae-skill-meta">rollback: <code><?php echo esc_html( (string) $entry['rollback_snapshot_id'] ); ?></code></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Пример curl</h2>
                <p>Минимальный запрос к `/run`. Для write endpoints также нужен guide token.</p>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s -X POST "' . $site_url . '" \\
  -H "Content-Type: application/json" \\
  -H "X-AI-Key: ' . $key . '" \\
  -d \'{"code": "return get_bloginfo(\'name\');"}\''
); ?></pre>
            </div>

            <div class="wpae-card">
                <h2>JavaScript</h2>
                <p>Для локальной разработки или agent runtime с fetch.</p>
                <pre class="wpae-code"><?php echo esc_html(
'const AI_KEY = "' . $key . '";

window.aiPHP = async (code) => {
    const res = await fetch("/wp-json/ai-executor/v1/run", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-AI-Key": AI_KEY },
        body: JSON.stringify({ code })
    });
    const d = await res.json();
    return d.return_value ?? d.error;
};

// Пример:
await aiPHP(`return get_bloginfo("name") . " | PHP " . PHP_VERSION;`);'
); ?></pre>
            </div>

            <div class="wpae-card">
                <h2>Python</h2>
                <p>Пример для любого агента, который умеет делать HTTP-запросы.</p>
                <pre class="wpae-code"><?php echo esc_html(
'import requests

def wp_php(code: str) -> dict:
    return requests.post(
        "' . $site_url . '",
        headers={"X-AI-Key": "' . $key . '"},
        json={"code": code}
    ).json()

result = wp_php("return get_bloginfo(\'name\');")
print(result["return_value"])'
); ?></pre>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Рекомендуемая инструкция для агента</h2>
                <p>Эту инструкцию можно дать Codex, Claude Desktop или другому агенту перед работой с сайтом.</p>
                <h3>Получить guide</h3>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s "' . get_rest_url( null, 'ai-executor/v1/guide' ) . '" \\
  -H "X-AI-Key: ' . $key . '"'
); ?></pre>
                <h3>Инструкция агента</h3>
                <pre class="wpae-code wpae-code-light"><?php echo esc_html( wpae_agent_prompt() ); ?></pre>
            </div>

            <div class="wpae-card wpae-card-wide wpae-security">
                <strong>Безопасность</strong>
                <ul>
                    <li>Плагин может выполнять PHP, поэтому держите ключ в секрете.</li>
                    <li>Для production лучше задать ключ в <code>wp-config.php</code>: <code>define('WP_AI_EXECUTOR_KEY', 'your-key');</code></li>
                    <li>Дополнительно ограничьте доступ по IP на уровне сервера или firewall.</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
