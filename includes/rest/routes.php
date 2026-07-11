<?php

defined( 'ABSPATH' ) || exit;

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

    register_rest_route( 'ai-executor/v1', '/visual-audit', [
        'methods'             => 'POST',
        'callback'            => 'wpae_visual_audit_page',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/rollback', [
        'methods'             => 'POST',
        'callback'            => 'wpae_rollback',
        'permission_callback' => 'wpae_auth_with_guide_token',
    ] );

    register_rest_route( 'ai-executor/v1', '/rollback/snapshots', [
        'methods'             => 'GET',
        'callback'            => 'wpae_list_rollback_snapshots',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/revisions', [
        'methods'             => 'GET',
        'callback'            => 'wpae_elementor_revisions',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/restore-revision', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_restore_revision',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'elementor_writes' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/validate', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_validate',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/normalize', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_normalize',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/blueprint', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_blueprint',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/design-system', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_design_system',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/recipes', [
        'methods'             => 'GET',
        'callback'            => 'wpae_elementor_recipes',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/recipes/(?P<id>[a-z0-9_.-]+)', [
        'methods'             => 'GET',
        'callback'            => 'wpae_elementor_recipe',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/compose', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_compose',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/visual-audit', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_visual_audit',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/typography-unlock', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_typography_unlock',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'elementor_writes' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/elementor/resolve-typography-overrides', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_resolve_typography_overrides',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'elementor_writes' ),
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

    register_rest_route( 'ai-executor/v1', '/elementor/patch', [
        'methods'             => 'POST',
        'callback'            => 'wpae_elementor_patch',
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

    register_rest_route( 'ai-executor/v1', '/skills/import-url', [
        'methods'             => 'POST',
        'callback'            => 'wpae_import_skill_from_url',
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

    register_rest_route( 'ai-executor/v1', '/exports', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_exports_summary',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/exports/prune', [
        'methods'             => 'POST',
        'callback'            => 'wpae_prune_exports',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'exports' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/exports/(?P<id>[a-f0-9]{24})', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_export',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/self-update', [
        'methods'             => 'POST',
        'callback'            => 'wpae_self_update',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'self_update' ),
    ] );

    register_rest_route( 'ai-executor/v1', '/self-update-package', [
        'methods'             => 'POST',
        'callback'            => 'wpae_self_update_package',
        'permission_callback' => fn( WP_REST_Request $request ) => wpae_auth_with_capability( $request, 'self_update' ),
    ] );

} );

function wpae_auth( WP_REST_Request $r ): bool {
    $provided = $r->get_header( 'X-AI-Key' );
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
