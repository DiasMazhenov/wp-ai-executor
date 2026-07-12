<?php

defined( 'ABSPATH' ) || exit;

function wpae_clear_elementor_cache( int $post_id ): array {
    $report = [
        'post_id' => $post_id,
        'post_css_meta_deleted' => false,
        'global_css_option_deleted' => false,
        'elementor_files_cache_cleared' => null,
        'wp_rocket_domain_cleaned' => null,
        'errors' => [],
    ];

    delete_post_meta( $post_id, '_elementor_css' );
    $report['post_css_meta_deleted'] = get_post_meta( $post_id, '_elementor_css', true ) === '';

    delete_option( '_elementor_global_css' );
    $report['global_css_option_deleted'] = get_option( '_elementor_global_css', null ) === null;

    if ( class_exists( '\Elementor\Plugin' ) ) {
        try {
            $elementor = \Elementor\Plugin::$instance;
            if ( isset( $elementor->files_manager ) && method_exists( $elementor->files_manager, 'clear_cache' ) ) {
                $elementor->files_manager->clear_cache();
                $report['elementor_files_cache_cleared'] = true;
            } else {
                $report['elementor_files_cache_cleared'] = false;
            }
        } catch ( Throwable $e ) {
            $report['elementor_files_cache_cleared'] = false;
            $report['errors'][] = [
                'scope' => 'elementor_files_cache',
                'message' => $e->getMessage(),
            ];
        }
    }

    if ( function_exists( 'rocket_clean_domain' ) ) {
        try {
            rocket_clean_domain();
            $report['wp_rocket_domain_cleaned'] = true;
        } catch ( Throwable $e ) {
            $report['wp_rocket_domain_cleaned'] = false;
            $report['errors'][] = [
                'scope' => 'wp_rocket',
                'message' => $e->getMessage(),
            ];
        }
    }

    $report['ok'] = $report['post_css_meta_deleted'] && empty( $report['errors'] );

    return $report;
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
    $cache = wpae_clear_elementor_cache( $post_id );
    if ( empty( $cache['ok'] ) ) {
        return new WP_Error( 'wpae_elementor_cache_clear_failed', 'Elementor cache clearing failed after metadata write.', [ 'cache' => $cache ] );
    }

    return true;
}

function wpae_build_elementor_transaction_status( string $operation, int $post_id, ?array $rollback_snapshot, array $checks = [] ): array {
    return [
        'mode' => 'atomic',
        'operation' => $operation,
        'post_id' => $post_id,
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'checks' => $checks,
        'auto_rollback_on' => [
            'metadata_save_error',
            'invalid_saved_elementor_json',
            'post_save_contract_failure',
            'cache_clear_failure',
            'public_verification_failure_when_requested',
            'visual_regression_failure_when_requested',
            'strict_quality_failure_when_requested',
        ],
    ];
}

function wpae_fetch_public_audit_snapshot_for_post( int $post_id, string $source ): array {
    $url = get_permalink( $post_id );
    $snapshot = [
        'ok' => false,
        'source' => $source,
        'post_id' => $post_id,
        'url' => is_string( $url ) ? $url : '',
    ];

    if ( ! is_string( $url ) || $url === '' || ! wpae_is_safe_visual_audit_url( $url ) ) {
        $snapshot['error'] = 'Unsafe or empty permalink.';
        return $snapshot;
    }

    $response = wp_remote_get( $url, [
        'timeout' => 10,
        'redirection' => 3,
        'user-agent' => 'WP AI Executor Visual Regression/' . WPAE_VERSION,
    ] );
    if ( is_wp_error( $response ) ) {
        $snapshot['error'] = $response->get_error_message();
        return $snapshot;
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $html = (string) wp_remote_retrieve_body( $response );
    $audit = wpae_build_public_html_visual_audit( $html, [
        'source' => $source,
        'url' => $url,
        'post_id' => $post_id,
        'status_code' => $status_code,
    ] );

    return [
        'ok' => true,
        'source' => $source,
        'post_id' => $post_id,
        'url' => $url,
        'http_status' => $status_code,
        'html_bytes' => strlen( $html ),
        'audit' => [
            'ok' => (bool) ( $audit['ok'] ?? false ),
            'score' => (int) ( $audit['score'] ?? 0 ),
            'level' => (string) ( $audit['level'] ?? '' ),
            'stats' => (array) ( $audit['stats'] ?? [] ),
            'recommendations' => (array) ( $audit['recommendations'] ?? [] ),
        ],
    ];
}

function wpae_compare_visual_regression_snapshots( array $before, array $after ): array {
    $failures = [];
    $warnings = [];
    $before_stats = (array) ( $before['audit']['stats'] ?? [] );
    $after_stats = (array) ( $after['audit']['stats'] ?? [] );
    $before_status = (int) ( $before['http_status'] ?? 0 );
    $after_status = (int) ( $after['http_status'] ?? 0 );

    if ( $after_status < 200 || $after_status >= 400 ) {
        $failures[] = 'Public page became unreachable after write.';
    }

    $before_text = max( 1, (int) ( $before_stats['visible_text_length'] ?? 0 ) );
    $after_text = (int) ( $after_stats['visible_text_length'] ?? 0 );
    if ( $after_text < (int) floor( $before_text * 0.65 ) ) {
        $failures[] = 'Visible text length dropped by more than 35%.';
    }

    if ( ! empty( $before_stats['has_cta'] ) && empty( $after_stats['has_cta'] ) ) {
        $failures[] = 'Public CTA was present before write and is missing after write.';
    }

    $before_overflow = (int) ( $before_stats['wide_fixed_width_risks'] ?? 0 );
    $after_overflow = (int) ( $after_stats['wide_fixed_width_risks'] ?? 0 );
    if ( $after_overflow > $before_overflow + 2 ) {
        $failures[] = 'Horizontal overflow risk increased after write.';
    }

    $before_empty = (int) ( $before_stats['empty_block_risks'] ?? 0 );
    $after_empty = (int) ( $after_stats['empty_block_risks'] ?? 0 );
    if ( $after_empty > $before_empty ) {
        $warnings[] = 'Empty block risk increased after write.';
    }

    $after_level = (string) ( $after['audit']['level'] ?? '' );
    if ( in_array( $after_level, [ 'weak', 'blocked' ], true ) ) {
        $failures[] = 'Public visual audit level after write is ' . $after_level . '.';
    }

    $score_delta = (int) ( $after['audit']['score'] ?? 0 ) - (int) ( $before['audit']['score'] ?? 0 );
    if ( $score_delta < -20 ) {
        $warnings[] = 'Public audit score dropped by more than 20 points.';
    }

    return [
        'ok' => empty( $failures ),
        'failures' => $failures,
        'warnings' => $warnings,
        'score_delta' => $score_delta,
        'before_summary' => [
            'http_status' => $before_status,
            'score' => (int) ( $before['audit']['score'] ?? 0 ),
            'level' => (string) ( $before['audit']['level'] ?? '' ),
            'visible_text_length' => (int) ( $before_stats['visible_text_length'] ?? 0 ),
            'has_cta' => (bool) ( $before_stats['has_cta'] ?? false ),
            'wide_fixed_width_risks' => $before_overflow,
            'empty_block_risks' => $before_empty,
        ],
        'after_summary' => [
            'http_status' => $after_status,
            'score' => (int) ( $after['audit']['score'] ?? 0 ),
            'level' => $after_level,
            'visible_text_length' => $after_text,
            'has_cta' => (bool) ( $after_stats['has_cta'] ?? false ),
            'wide_fixed_width_risks' => $after_overflow,
            'empty_block_risks' => $after_empty,
        ],
    ];
}

function wpae_verify_saved_elementor_transaction( int $post_id, array $expected_elementor_data, array $preflight, WP_REST_Request $request, ?array $visual_regression_baseline = null ): array {
    $checks = [];
    $raw_data = get_post_meta( $post_id, '_elementor_data', true );
    $decoded = is_string( $raw_data ) ? json_decode( $raw_data, true ) : null;
    $saved_valid = is_array( $decoded ) && empty( wpae_validate_elementor_data_array( $decoded ) );

    $checks['saved_elementor_data'] = [
        'ok' => $saved_valid,
        'message' => $saved_valid ? 'Saved _elementor_data decodes as valid Elementor array.' : 'Saved _elementor_data is missing, invalid JSON, or fails Elementor validation.',
        'json_error' => is_string( $raw_data ) && ! is_array( $decoded ) ? json_last_error_msg() : null,
    ];

    $design_contract = is_array( $decoded ) ? wpae_validate_design_system_contract( $decoded ) : [ 'ok' => false ];
    $checks['design_system_contract'] = [
        'ok' => ! empty( $design_contract['ok'] ),
        'message' => ! empty( $design_contract['ok'] ) ? 'Saved data keeps the active design-system contract.' : 'Saved data violates the active design-system contract.',
        'details' => $design_contract,
    ];

    $cache = wpae_clear_elementor_cache( $post_id );
    $checks['cache_refresh'] = [
        'ok' => ! empty( $cache['ok'] ),
        'message' => ! empty( $cache['ok'] ) ? 'Elementor cache refresh completed.' : 'Elementor cache refresh failed.',
        'details' => $cache,
    ];

    $quality_summary = wpae_build_after_save_quality_summary( $post_id, $expected_elementor_data, $preflight );
    $strict_quality = (bool) $request->get_param( 'transaction_strict_quality' );
    $quality_level = (string) ( $quality_summary['visual_audit']['level'] ?? '' );
    $quality_ok = ! $strict_quality || in_array( $quality_level, [ 'strong', 'acceptable' ], true );
    $checks['quality_gate'] = [
        'ok' => $quality_ok,
        'strict' => $strict_quality,
        'message' => $quality_ok ? 'Static quality gate passed for the requested transaction mode.' : 'Strict quality gate failed.',
        'details' => [
            'level' => $quality_level,
            'score' => (int) ( $quality_summary['visual_audit']['score'] ?? 0 ),
        ],
    ];

    $public_verification = (bool) $request->get_param( 'transaction_verify_public' );
    if ( $public_verification ) {
        $public_target = get_permalink( $post_id );
        $public_ok = false;
        $public_details = [ 'url' => $public_target ];
        if ( is_string( $public_target ) && $public_target !== '' && wpae_is_safe_visual_audit_url( $public_target ) ) {
            $response = wp_remote_get( $public_target, [
                'timeout' => 10,
                'redirection' => 3,
                'user-agent' => 'WP AI Executor Transaction Verify/' . WPAE_VERSION,
            ] );
            if ( is_wp_error( $response ) ) {
                $public_details['error'] = $response->get_error_message();
            } else {
                $code = (int) wp_remote_retrieve_response_code( $response );
                $html = (string) wp_remote_retrieve_body( $response );
                $public_audit = wpae_build_public_html_visual_audit( $html, [
                    'source' => 'transaction_verify_public',
                    'url' => $public_target,
                    'post_id' => $post_id,
                    'status_code' => $code,
                ] );
                $public_ok = $code >= 200 && $code < 400 && ! empty( $public_audit['ok'] );
                $public_details['http_status'] = $code;
                $public_details['audit'] = [
                    'ok' => (bool) ( $public_audit['ok'] ?? false ),
                    'score' => (int) ( $public_audit['score'] ?? 0 ),
                    'level' => (string) ( $public_audit['level'] ?? '' ),
                    'recommendations' => (array) ( $public_audit['recommendations'] ?? [] ),
                ];
            }
        } else {
            $public_details['error'] = 'Unsafe or empty permalink for public verification.';
        }

        $checks['public_verification'] = [
            'ok' => $public_ok,
            'requested' => true,
            'message' => $public_ok ? 'Public verification passed.' : 'Public verification failed.',
            'details' => $public_details,
        ];
    }

    $visual_regression_requested = (bool) $request->get_param( 'transaction_visual_regression' );
    if ( $visual_regression_requested ) {
        $regression_ok = false;
        $regression_details = [
            'requested' => true,
            'baseline_available' => is_array( $visual_regression_baseline ) && ! empty( $visual_regression_baseline['ok'] ),
        ];
        if ( ! empty( $regression_details['baseline_available'] ) ) {
            $after_snapshot = wpae_fetch_public_audit_snapshot_for_post( $post_id, 'visual_regression_after' );
            $comparison = ! empty( $after_snapshot['ok'] )
                ? wpae_compare_visual_regression_snapshots( $visual_regression_baseline, $after_snapshot )
                : [ 'ok' => false, 'failures' => [ 'Failed to capture after-write public snapshot.' ], 'warnings' => [], 'after_snapshot' => $after_snapshot ];
            $regression_ok = ! empty( $comparison['ok'] );
            $regression_details['before'] = $visual_regression_baseline;
            $regression_details['after'] = $after_snapshot;
            $regression_details['comparison'] = $comparison;
        } else {
            $regression_details['message'] = 'No before-write public baseline was available. New pages and unpublished pages cannot run visual regression comparison.';
            $regression_ok = true;
        }

        $checks['visual_regression'] = [
            'ok' => $regression_ok,
            'requested' => true,
            'message' => $regression_ok ? 'Visual regression gate passed or was not applicable.' : 'Visual regression gate failed.',
            'details' => $regression_details,
        ];
    }

    $failed = [];
    foreach ( $checks as $code => $check ) {
        if ( empty( $check['ok'] ) ) {
            $failed[] = $code;
        }
    }

    return [
        'ok' => empty( $failed ),
        'checks' => $checks,
        'failed_checks' => $failed,
        'quality_summary' => $quality_summary,
    ];
}

function wpae_finalize_elementor_transaction( string $operation, int $post_id, ?array $rollback_snapshot, array $elementor_data, array $preflight, WP_REST_Request $request, ?array $visual_regression_baseline = null ) {
    $transaction = wpae_build_elementor_transaction_status( $operation, $post_id, $rollback_snapshot );
    $verification = wpae_verify_saved_elementor_transaction( $post_id, $elementor_data, $preflight, $request, $visual_regression_baseline );
    $transaction['checks'] = $verification['checks'];
    $transaction['failed_checks'] = $verification['failed_checks'];
    $transaction['ok'] = ! empty( $verification['ok'] );

    if ( ! $transaction['ok'] ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : [ 'ok' => false, 'error' => 'No rollback snapshot was available.' ];
        $transaction['auto_rollback'] = $rollback;

        return new WP_Error(
            'wpae_elementor_transaction_failed',
            'Elementor transaction failed after write and was rolled back when possible.',
            [
                'transaction' => $transaction,
                'quality_summary' => $verification['quality_summary'],
            ]
        );
    }

    return [
        'transaction' => $transaction,
        'quality_summary' => $verification['quality_summary'],
    ];
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

function wpae_elementor_normalize( WP_REST_Request $request ): WP_REST_Response {
    $elementor_data = wpae_get_elementor_data_from_request( $request );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
    }

    $before_errors = wpae_validate_elementor_data_array( $elementor_data );
    $normalized = wpae_elementor_normalize_data( $elementor_data );
    $normalized_data = $normalized['data'];
    $after_errors = wpae_validate_elementor_data_array( $normalized_data );
    $stats = wpae_default_elementor_audit_stats();
    wpae_collect_elementor_audit_stats( $normalized_data, $stats );
    wpae_collect_elementor_design_quality_stats( $normalized_data, $stats );
    wpae_finalize_elementor_audit_stats( $stats );

    return new WP_REST_Response( [
        'ok' => empty( $after_errors ),
        'changed' => ! empty( $normalized['report']['changes'] ),
        'change_counts' => $normalized['report']['counts'],
        'changes' => $normalized['report']['changes'],
        'before_errors' => $before_errors,
        'after_errors' => $after_errors,
        'stats' => $stats,
        'normalized_elementor_data' => $normalized_data,
    ], empty( $after_errors ) ? 200 : 422 );
}
