<?php

defined( 'ABSPATH' ) || exit;

function wpae_build_elementor_design_review( array $elementor_data, array $context = [] ): array {
    $iteration = max( 1, min( 3, absint( $context['iteration'] ?? 1 ) ) );
    $visual = wpae_build_elementor_visual_audit( $elementor_data, $context );
    $editability = wpae_build_elementor_editability_audit( $elementor_data, $context );
    $visual_checks = array_column( (array) ( $visual['checks'] ?? [] ), null, 'code' );
    $vision_report = is_array( $context['vision_report'] ?? null ) ? $context['vision_report'] : null;

    $dimensions = [
        'composition_brief' => [ 'typography_hierarchy', 'native_cta', 'native_content_complete' ],
        'design_system_consistency' => [ 'design_system_contract', 'palette_variety', 'native_spacing_coverage' ],
        'accessibility_mobile' => [ 'responsive_settings', 'responsive_unit_policy', 'native_text_color_coverage' ],
        'copy' => [ 'native_content_complete', 'native_cta', 'typography_hierarchy' ],
        'elementor_editability' => [ 'runtime_elementor_contract', 'repeated_agent_errors', 'html_widget_scope' ],
    ];
    $reviews = [];
    $must_fix = [];

    foreach ( $dimensions as $key => $check_codes ) {
        $statuses = [];
        $messages = [];
        foreach ( $check_codes as $code ) {
            $check = $visual_checks[ $code ] ?? null;
            if ( ! is_array( $check ) ) {
                continue;
            }
            $statuses[] = (string) ( $check['status'] ?? 'warn' );
            if ( ( $check['status'] ?? '' ) !== 'pass' && ! empty( $check['recommendation'] ) ) {
                $messages[] = (string) $check['recommendation'];
            }
        }
        if ( $key === 'elementor_editability' && empty( $editability['ok'] ) ) {
            $statuses[] = 'fail';
            $messages = array_merge( $messages, (array) ( $editability['next_fixes'] ?? [] ) );
        } elseif ( $key === 'elementor_editability' && ( $editability['level'] ?? '' ) === 'weak' ) {
            $statuses[] = 'warn';
            $messages = array_merge( $messages, (array) ( $editability['next_fixes'] ?? [] ) );
        }

        $status = in_array( 'fail', $statuses, true ) ? 'fail' : ( in_array( 'warn', $statuses, true ) ? 'warn' : 'pass' );
        $messages = array_values( array_unique( array_filter( $messages ) ) );
        $reviews[ $key ] = [ 'status' => $status, 'must_fix' => $messages ];
        if ( $status === 'fail' ) {
            $must_fix = array_merge( $must_fix, $messages );
        }
    }

    $vision_gate = null;
    if ( $vision_report !== null ) {
        $vision_gate = wpae_evaluate_vision_report( $vision_report );
        $vision_status = ! empty( $vision_gate['blocking'] ) ? 'fail' : ( ! empty( $vision_gate['major_count'] ) ? 'warn' : 'pass' );
        $reviews['vision_visual_review'] = [
            'status' => $vision_status,
            'must_fix' => (array) ( $vision_gate['must_fix'] ?? [] ),
        ];
        if ( $vision_status === 'fail' ) {
            $must_fix = array_merge( $must_fix, (array) ( $vision_gate['must_fix'] ?? [] ) );
        }
    }

    $blocking = ( $visual['level'] ?? '' ) === 'blocked' || ( $editability['level'] ?? '' ) === 'blocked' || ! empty( $must_fix );
    $acceptable = in_array( (string) ( $visual['level'] ?? '' ), [ 'strong', 'acceptable' ], true )
        && in_array( (string) ( $editability['level'] ?? '' ), [ 'strong', 'acceptable' ], true );
    $verdict = $blocking ? 'revise' : ( $acceptable ? 'approved' : 'review' );

    return [
        'ok' => $verdict === 'approved',
        'design_review_version' => 'v01.00.00',
        'state' => $verdict,
        'verdict' => $verdict,
        'iteration' => $iteration,
        'max_iterations' => 3,
        'must_fix' => array_values( array_unique( array_filter( $must_fix ) ) ),
        'dimensions' => $reviews,
        'evidence' => [
            'visual_audit' => [ 'score' => $visual['score'] ?? 0, 'level' => $visual['level'] ?? 'blocked' ],
            'editability_audit' => [ 'score' => $editability['score'] ?? 0, 'level' => $editability['level'] ?? 'blocked' ],
            'vision' => $vision_report === null ? null : [
                'report_id' => $vision_report['report_id'] ?? null,
                'vision_score' => $vision_report['vision_score'] ?? 0,
                'confidence' => $vision_report['confidence'] ?? 0,
                'critical_count' => $vision_gate['critical_count'] ?? 0,
                'major_count' => $vision_gate['major_count'] ?? 0,
            ],
        ],
        'next_safe_step' => $verdict === 'approved'
            ? 'The page may proceed to write or publication verification.'
            : ( $iteration >= 3 ? 'Stop after this iteration and report unresolved must_fix items.' : 'Fix must_fix items and submit the next review iteration.' ),
        'policy' => 'Live writes must not use ship_best. Enable transaction_design_review=true to make approval an atomic write requirement.',
    ];
}

function wpae_elementor_design_review( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $elementor_data = $post_id > 0
        ? wpae_get_elementor_data_for_post( $post_id )
        : wpae_get_elementor_data_from_request( $request );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $elementor_data->get_error_message(),
            'details' => $elementor_data->get_error_data(),
        ], $post_id > 0 ? 422 : 400 );
    }

    $review = wpae_build_elementor_design_review( $elementor_data, [
        'source' => $post_id > 0 ? 'post_meta' : 'request',
        'post_id' => $post_id ?: null,
        'iteration' => $request->get_param( 'iteration' ),
    ] );
    return new WP_REST_Response( $review, $review['state'] === 'revise' ? 422 : 200 );
}
