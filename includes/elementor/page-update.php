<?php

defined( 'ABSPATH' ) || exit;

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

    $existing_data = wpae_get_elementor_data_for_post( $post_id );
    if ( is_wp_error( $existing_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $existing_data->get_error_message(),
            'details' => $existing_data->get_error_data(),
        ], 422 );
    }

    $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $elementor_data, $request );
    if ( ! $protected_zone_guard['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $protected_zone_guard['message'],
            'code' => $protected_zone_guard['error_code'],
            'protected_zone_guard' => $protected_zone_guard,
        ], 422 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
        ], 422 );
    }

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    if ( ! $design_system_contract['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed design-system contract.',
            'details' => $design_system_contract,
        ], 422 );
    }

    $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
        'post_id' => $post_id,
        'template' => $template,
        'operation' => 'update',
    ] );
    if ( ! $preflight['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor preflight failed.',
            'preflight' => $preflight,
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
            'preflight' => $preflight,
            'protected_zone_guard' => $protected_zone_guard,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_update:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : null;
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'transaction' => wpae_build_elementor_transaction_status( 'elementor_update', $post_id, $rollback_snapshot, [
                'metadata_save' => [
                    'ok' => false,
                    'message' => $saved->get_error_message(),
                    'details' => $saved->get_error_data(),
                ],
            ] ),
            'auto_rollback' => $rollback,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    $finalized = wpae_finalize_elementor_transaction( 'elementor_update', $post_id, $rollback_snapshot, $elementor_data, $preflight, $request );
    if ( is_wp_error( $finalized ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $finalized->get_error_message(),
            'details' => $finalized->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'preflight' => $preflight,
        'protected_zone_guard' => $protected_zone_guard,
        'transaction' => $finalized['transaction'],
        'quality_summary' => $finalized['quality_summary'],
    ], 200 );
}

function wpae_elementor_patch( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $patches = $request->get_param( 'patches' );
    if ( is_array( $patches ) && ( isset( $patches['element_id'] ) || isset( $patches['id'] ) ) ) {
        $patches = [ $patches ];
    }
    if ( ! is_array( $patches ) ) {
        $single_patch = $request->get_param( 'patch' );
        $patches = is_array( $single_patch ) ? [ $single_patch ] : [];
    }

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    if ( empty( $patches ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'patches array is required.' ], 400 );
    }

    $existing_data = wpae_get_elementor_data_for_post( $post_id );
    if ( is_wp_error( $existing_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $existing_data->get_error_message(),
            'details' => $existing_data->get_error_data(),
        ], 422 );
    }

    $patched = wpae_apply_elementor_patches( $existing_data, $patches );
    $elementor_data = $patched['data'];
    $patch_report = $patched['report'];

    if ( ! empty( $patch_report['errors'] ) || ! empty( $patch_report['missing_element_ids'] ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor patch failed before validation.',
            'patch_report' => $patch_report,
        ], 422 );
    }

    $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $elementor_data, $request );
    if ( ! $protected_zone_guard['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $protected_zone_guard['message'],
            'code' => $protected_zone_guard['error_code'],
            'protected_zone_guard' => $protected_zone_guard,
            'patch_report' => $patch_report,
        ], 422 );
    }

    $validation_errors = wpae_validate_elementor_data_array( $elementor_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Patched Elementor data failed validation.',
            'details' => [ 'errors' => $validation_errors ],
            'patch_report' => $patch_report,
        ], 422 );
    }

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    if ( ! $design_system_contract['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Patched Elementor data failed design-system contract.',
            'details' => $design_system_contract,
            'patch_report' => $patch_report,
        ], 422 );
    }

    $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
        'post_id' => $post_id,
        'template' => $template,
        'operation' => 'patch',
        'patch_count' => count( $patch_report['changes'] ),
    ] );
    if ( ! $preflight['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Patched Elementor data failed preflight.',
            'preflight' => $preflight,
            'patch_report' => $patch_report,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'patch_report' => $patch_report,
            'preflight' => $preflight,
            'protected_zone_guard' => $protected_zone_guard,
            'elementor_data' => $elementor_data,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_patch:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $elementor_data, $template );
    if ( is_wp_error( $saved ) ) {
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : null;
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'post_id' => $post_id,
            'patch_report' => $patch_report,
            'transaction' => wpae_build_elementor_transaction_status( 'elementor_patch', $post_id, $rollback_snapshot, [
                'metadata_save' => [
                    'ok' => false,
                    'message' => $saved->get_error_message(),
                    'details' => $saved->get_error_data(),
                ],
            ] ),
            'auto_rollback' => $rollback,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    $finalized = wpae_finalize_elementor_transaction( 'elementor_patch', $post_id, $rollback_snapshot, $elementor_data, $preflight, $request );
    if ( is_wp_error( $finalized ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $finalized->get_error_message(),
            'details' => $finalized->get_error_data(),
            'patch_report' => $patch_report,
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'patch_report' => $patch_report,
        'preflight' => $preflight,
        'protected_zone_guard' => $protected_zone_guard,
        'transaction' => $finalized['transaction'],
        'quality_summary' => $finalized['quality_summary'],
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

    $design_system_contract = wpae_validate_design_system_contract( $elementor_data );
    if ( ! $design_system_contract['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor data failed design-system contract.',
            'details' => $design_system_contract,
        ], 422 );
    }

    $preflight = wpae_build_elementor_preflight( $elementor_data, $request, [
        'post_id' => $post_id ?: null,
        'template' => $template,
        'operation' => $post_id > 0 ? 'page_update' : 'page_create',
        'title' => $title,
        'slug' => $slug,
    ] );
    if ( ! $preflight['ok'] ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Elementor preflight failed.',
            'preflight' => $preflight,
        ], 422 );
    }

    if ( $post_id > 0 && get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Target post_id does not exist.' ], 404 );
    }

    $protected_zone_guard = [ 'ok' => true, 'protected_zones' => [] ];
    if ( $post_id > 0 ) {
        $existing_data = wpae_get_elementor_data_for_post( $post_id );
        if ( is_wp_error( $existing_data ) ) {
            return new WP_REST_Response( [
                'ok' => false,
                'error' => $existing_data->get_error_message(),
                'details' => $existing_data->get_error_data(),
            ], 422 );
        }
        $protected_zone_guard = wpae_validate_elementor_protected_zones( $existing_data, $elementor_data, $request );
        if ( ! $protected_zone_guard['ok'] ) {
            return new WP_REST_Response( [
                'ok' => false,
                'error' => $protected_zone_guard['message'],
                'code' => $protected_zone_guard['error_code'],
                'protected_zone_guard' => $protected_zone_guard,
            ], 422 );
        }
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
            'preflight' => $preflight,
            'protected_zone_guard' => $protected_zone_guard,
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
        $rollback = ! empty( $rollback_snapshot['id'] )
            ? wpae_restore_rollback_snapshot_by_id( (string) $rollback_snapshot['id'], false )
            : null;

        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'post_id' => $post_id,
            'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
            'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
            'transaction' => wpae_build_elementor_transaction_status( $is_new_post ? 'elementor_page_create' : 'elementor_page_update', $post_id, $rollback_snapshot, [
                'metadata_save' => [
                    'ok' => false,
                    'message' => $saved->get_error_message(),
                    'details' => $saved->get_error_data(),
                ],
            ] ),
            'auto_rollback' => $rollback,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    $finalized = wpae_finalize_elementor_transaction( $is_new_post ? 'elementor_page_create' : 'elementor_page_update', $post_id, $rollback_snapshot, $elementor_data, $preflight, $request );
    if ( is_wp_error( $finalized ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $finalized->get_error_message(),
            'details' => $finalized->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'status' => get_post_status( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'preflight' => $preflight,
        'protected_zone_guard' => $protected_zone_guard,
        'transaction' => $finalized['transaction'],
        'quality_summary' => $finalized['quality_summary'],
    ], 200 );
}

