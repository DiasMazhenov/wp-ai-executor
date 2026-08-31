<?php

defined( 'ABSPATH' ) || exit;

const WPAE_ELEMENTOR_TEMPLATE_POST_TYPE = 'elementor_library';

function wpae_elementor_template_post( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post
        || $post->post_type !== WPAE_ELEMENTOR_TEMPLATE_POST_TYPE
        || $post->post_status === 'trash'
    ) {
        return new WP_Error(
            'wpae_elementor_template_not_found',
            'Native Elementor template was not found.',
            [ 'status' => 404 ]
        );
    }

    return $post;
}

function wpae_elementor_template_decode_meta( $value ): array {
    if ( is_array( $value ) ) {
        return $value;
    }

    if ( ! is_string( $value ) || trim( $value ) === '' ) {
        return [];
    }

    $decoded = json_decode( $value, true );
    return is_array( $decoded ) ? $decoded : [];
}

function wpae_elementor_template_summary( WP_Post $post ): array {
    $raw_data = get_post_meta( $post->ID, '_elementor_data', true );

    return [
        'id' => (int) $post->ID,
        'title' => get_the_title( $post ),
        'status' => (string) $post->post_status,
        'template_type' => sanitize_key( (string) get_post_meta( $post->ID, '_elementor_template_type', true ) ),
        'modified_gmt' => (string) get_post_modified_time( 'c', true, $post ),
        'has_elementor_data' => is_string( $raw_data ) && trim( $raw_data ) !== '',
        'detail_endpoint' => get_rest_url( null, 'ai-executor/v1/elementor/templates/' . $post->ID ),
    ];
}

function wpae_elementor_template_list( WP_REST_Request $request ): WP_REST_Response {
    if ( ! post_type_exists( WPAE_ELEMENTOR_TEMPLATE_POST_TYPE ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor saved templates are unavailable because Elementor is inactive.',
            'code' => 'wpae_elementor_templates_unavailable',
        ], 404 );
    }

    $requested_status = sanitize_key( (string) $request->get_param( 'status' ) );
    $allowed_statuses = [ 'publish', 'draft', 'private' ];
    if ( $requested_status !== '' && ! in_array( $requested_status, $allowed_statuses, true ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'status must be publish, draft, or private.',
            'code' => 'wpae_invalid_elementor_template_status',
        ], 400 );
    }

    $limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
    $search = sanitize_text_field( (string) $request->get_param( 'q' ) );
    $template_type = sanitize_key( (string) $request->get_param( 'type' ) );
    $posts = get_posts( [
        'post_type' => WPAE_ELEMENTOR_TEMPLATE_POST_TYPE,
        'post_status' => $requested_status !== '' ? [ $requested_status ] : $allowed_statuses,
        'posts_per_page' => $limit,
        'orderby' => 'modified',
        'order' => 'DESC',
        's' => $search,
        'meta_query' => $template_type === '' ? [] : [
            [
                'key' => '_elementor_template_type',
                'value' => $template_type,
                'compare' => '=',
            ],
        ],
    ] );

    $items = array_map( 'wpae_elementor_template_summary', $posts );

    return new WP_REST_Response( [
        'ok' => true,
        'source' => WPAE_ELEMENTOR_TEMPLATE_POST_TYPE,
        'count' => count( $items ),
        'items' => $items,
    ], 200 );
}

function wpae_elementor_template_get( WP_REST_Request $request ): WP_REST_Response {
    $post = wpae_elementor_template_post( absint( $request['id'] ) );
    if ( is_wp_error( $post ) ) {
        $details = $post->get_error_data();
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $post->get_error_message(),
            'code' => $post->get_error_code(),
            'details' => $details,
        ], (int) ( is_array( $details ) ? ( $details['status'] ?? 404 ) : 404 ) );
    }

    $elementor_data = wpae_get_elementor_data_for_post( $post->ID );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $elementor_data->get_error_message(),
            'code' => $elementor_data->get_error_code(),
            'details' => $elementor_data->get_error_data(),
        ], 422 );
    }

    $normalized = wpae_elementor_normalize_data( $elementor_data );
    $compatibility = function_exists( 'wpae_block_library_compatibility_report' )
        ? wpae_block_library_compatibility_report( $elementor_data )
        : [];
    $template = wpae_elementor_template_summary( $post );
    $template['elementor_data'] = $elementor_data;
    $template['normalized_elementor_data'] = $normalized['data'];
    $template['normalization_report'] = $normalized['report'];
    $template['page_settings'] = wpae_elementor_template_decode_meta(
        get_post_meta( $post->ID, '_elementor_page_settings', true )
    );
    $template['compatibility'] = $compatibility;

    return new WP_REST_Response( [
        'ok' => true,
        'source' => WPAE_ELEMENTOR_TEMPLATE_POST_TYPE,
        'template' => $template,
        'read_only' => true,
        'next_step' => 'Use normalized_elementor_data with /elementor/validate before an X-AI-Key-authenticated structured write.',
    ], 200 );
}
