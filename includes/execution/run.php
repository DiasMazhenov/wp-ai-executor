<?php

defined( 'ABSPATH' ) || exit;

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

    $elementor_validation = wpae_validate_changed_elementor_data( $elementor_before, $request );
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

