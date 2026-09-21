<?php

/** Lossless media/style reference metadata shared by BriefIR and the compiler. */

defined( 'ABSPATH' ) || exit;

const WPAE_REFERENCE_SET_SCHEMA = 'wpae-reference-set-v1';

function wpae_reference_set_roles(): array {
	return [ 'hero', 'background', 'portrait', 'icon', 'logo', 'card_image', 'decorative' ];
}

function wpae_reference_set_normalize( array $input ): array {
	$role = sanitize_key( (string) ( $input['role'] ?? 'decorative' ) );
	if ( ! in_array( $role, wpae_reference_set_roles(), true ) ) {
		$role = 'decorative';
	}
	$focal = is_array( $input['focal_point'] ?? null ) ? $input['focal_point'] : null;
	return [
		'asset_id' => sanitize_key( (string) ( $input['asset_id'] ?? '' ) ),
		'attachment_id' => isset( $input['attachment_id'] ) && is_numeric( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : null,
		'source_url' => esc_url_raw( (string) ( $input['source_url'] ?? '' ) ),
		'role' => $role,
		'alt' => sanitize_text_field( (string) ( $input['alt'] ?? '' ) ),
		'focal_point' => $focal === null ? null : [
			'x' => max( 0, min( 1, (float) ( $focal['x'] ?? 0.5 ) ) ),
			'y' => max( 0, min( 1, (float) ( $focal['y'] ?? 0.5 ) ) ),
		],
		'crop' => sanitize_key( (string) ( $input['crop'] ?? '' ) ) ?: null,
		'object_fit' => in_array( sanitize_key( (string) ( $input['object_fit'] ?? 'cover' ) ), [ 'cover', 'contain', 'fill' ], true ) ? sanitize_key( (string) ( $input['object_fit'] ?? 'cover' ) ) : 'cover',
		'license' => sanitize_text_field( (string) ( $input['license'] ?? '' ) ),
		'allowed_reuse' => ! empty( $input['allowed_reuse'] ),
		'provenance' => is_array( $input['provenance'] ?? null ) ? $input['provenance'] : [ 'source' => 'unknown' ],
	];
}

function wpae_reference_set_validate( array $references ): array {
	$errors = [];
	foreach ( $references as $index => $reference ) {
		if ( ! is_array( $reference ) ) {
			$errors[] = 'reference_' . (int) $index . '_not_array';
			continue;
		}
		if ( trim( (string) ( $reference['asset_id'] ?? '' ) ) === '' ) {
			$errors[] = 'reference_' . (int) $index . '_asset_id';
		}
		if ( trim( (string) ( $reference['source_url'] ?? '' ) ) === '' && absint( $reference['attachment_id'] ?? 0 ) <= 0 ) {
			$errors[] = 'reference_' . (int) $index . '_source';
		}
		if ( ! in_array( $reference['role'] ?? '', wpae_reference_set_roles(), true ) ) {
			$errors[] = 'reference_' . (int) $index . '_role';
		}
		if ( ! empty( $reference['source_url'] ) && ! filter_var( $reference['source_url'], FILTER_VALIDATE_URL ) ) {
			$errors[] = 'reference_' . (int) $index . '_url';
		}
	}
	return [ 'ok' => empty( $errors ), 'errors' => $errors ];
}
