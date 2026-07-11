<?php

defined( 'ABSPATH' ) || exit;

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

    $detected_mime = wpae_detect_media_mime_from_bytes( $bytes );
    if ( $detected_mime !== $mime_type ) {
        return new WP_REST_Response( [
            'error' => 'Media binary signature does not match mime_type.',
            'mime_type' => $mime_type,
            'detected_mime_type' => $detected_mime,
        ], 400 );
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

function wpae_detect_media_mime_from_bytes( string $bytes ): ?string {
    if ( str_starts_with( $bytes, "\xFF\xD8\xFF" ) ) {
        return 'image/jpeg';
    }

    if ( str_starts_with( $bytes, "\x89PNG\r\n\x1A\n" ) ) {
        return 'image/png';
    }

    if ( str_starts_with( $bytes, 'GIF87a' ) || str_starts_with( $bytes, 'GIF89a' ) ) {
        return 'image/gif';
    }

    if ( strlen( $bytes ) >= 12 && substr( $bytes, 0, 4 ) === 'RIFF' && substr( $bytes, 8, 4 ) === 'WEBP' ) {
        return 'image/webp';
    }

    if ( str_starts_with( $bytes, '%PDF-' ) ) {
        return 'application/pdf';
    }

    return null;
}

