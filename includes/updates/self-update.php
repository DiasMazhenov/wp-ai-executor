<?php

defined( 'ABSPATH' ) || exit;

function wpae_self_update( WP_REST_Request $request ) {
    $source_url = trim( (string) $request->get_param( 'source_url' ) );
    $dry_run     = (bool) $request->get_param( 'dry_run' );

    if ( $source_url === '' || ! wpae_is_allowed_self_update_url( $source_url ) ) {
        return new WP_REST_Response( [
            'error' => 'Self-update requires an immutable Git commit URL.',
            'allowed' => 'https://raw.githubusercontent.com/DiasMazhenov/wp-ai-executor/<40-char-commit-sha>/wp-ai-executor.php',
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

    $temp = tempnam( dirname( $target ), '.wpae-update-' );
    if ( $temp === false ) {
        return new WP_REST_Response( [
            'error' => 'Failed to create atomic update temp file.',
            'target' => $target,
        ], 500 );
    }

    $written = file_put_contents( $temp, $body, LOCK_EX );
    if ( $written === false ) {
        @unlink( $temp );
        return new WP_REST_Response( [
            'error' => 'Failed to write plugin update temp file.',
            'target' => $target,
        ], 500 );
    }

    $target_mode = @fileperms( $target );
    if ( $target_mode !== false ) {
        @chmod( $temp, $target_mode & 0777 );
    }

    if ( ! @rename( $temp, $target ) ) {
        @unlink( $temp );
        return new WP_REST_Response( [
            'error' => 'Failed to atomically replace plugin file.',
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
    return (bool) preg_match( '#^/DiasMazhenov/wp-ai-executor/[a-f0-9]{40}/wp-ai-executor\.php$#', $path );
}

function wpae_validate_self_update_file( string $contents ): array {
    $errors = [];

    $is_modular_bootstrap = strpos( $contents, "require_once __DIR__ . '/includes/rest/routes.php';" ) !== false
        && strpos( $contents, "require_once __DIR__ . '/includes/updates/package-updater.php';" ) !== false
        && strpos( $contents, "const WPAE_VERSION = 'v" ) !== false;

    if ( strlen( $contents ) < ( $is_modular_bootstrap ? 500 : 5000 ) ) {
        $errors[] = 'File is unexpectedly small.';
    }

    if ( strlen( $contents ) > 500000 ) {
        $errors[] = 'File is unexpectedly large.';
    }

    if ( strncmp( ltrim( $contents ), '<?php', 5 ) !== 0 ) {
        $errors[] = 'File must start with <?php.';
    }

    $required_markers = $is_modular_bootstrap ? [
        'Plugin Name: WP AI Executor',
        "defined( 'ABSPATH' ) || exit;",
        "require_once __DIR__ . '/includes/security/capabilities.php';",
        "require_once __DIR__ . '/includes/elementor/core.php';",
        "require_once __DIR__ . '/includes/rest/routes.php';",
    ] : [
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
