<?php

/** Lossless media/style reference metadata shared by BriefIR and the compiler. */

defined( 'ABSPATH' ) || exit;

const WPAE_REFERENCE_SET_SCHEMA = 'wpae-reference-set-v1';

function wpae_reference_set_roles(): array {
	return [ 'about', 'hero', 'background', 'portrait', 'avatar', 'project_image', 'icon', 'logo', 'card_image', 'decorative' ];
}

/** Accept only simple Elementor dimension values from the frozen render contract. */
function wpae_reference_set_dimension_value( $value ): string {
	$value = trim( (string) $value );
	if ( in_array( $value, [ 'auto', '100%' ], true ) ) { return $value; }
	return preg_match( '/^(?:0|[1-9]\d*(?:\.\d+)?)(?:px|rem|em|vw|vh|%)$/', $value ) ? $value : '';
}

function wpae_reference_set_responsive_dimensions( $value ): array {
	$dimensions = [];
	foreach ( (array) $value as $device => $dimension ) {
		$device = sanitize_key( (string) $device );
		$dimension = wpae_reference_set_dimension_value( $dimension );
		if ( in_array( $device, [ 'desktop', 'tablet', 'mobile' ], true ) && $dimension !== '' ) { $dimensions[$device] = $dimension; }
	}
	return $dimensions;
}

function wpae_reference_set_normalize( array $input ): array {
	$role = sanitize_key( (string) ( $input['role'] ?? 'decorative' ) );
	if ( ! in_array( $role, wpae_reference_set_roles(), true ) ) {
		$role = 'decorative';
	}
	$focal = is_array( $input['focal_point'] ?? null ) ? $input['focal_point'] : null;
	$facts = is_array( $input['asset_facts'] ?? null ) ? $input['asset_facts'] : [];
	$width = isset( $facts['width'] ) && is_numeric( $facts['width'] ) ? absint( $facts['width'] ) : 0;
	$height = isset( $facts['height'] ) && is_numeric( $facts['height'] ) ? absint( $facts['height'] ) : 0;
	$render = is_array( $input['render'] ?? null ) ? $input['render'] : [];
	return [
		'asset_id' => sanitize_key( (string) ( $input['asset_id'] ?? '' ) ),
		'group_id' => sanitize_key( (string) ( $input['group_id'] ?? '' ) ),
		'attachment_id' => isset( $input['attachment_id'] ) && is_numeric( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : null,
		'source_url' => esc_url_raw( (string) ( $input['source_url'] ?? '' ) ),
		'role' => $role,
		'purpose' => in_array( sanitize_key( (string) ( $input['purpose'] ?? '' ) ), [ 'section_image', 'project_image', 'portrait', 'avatar', 'logo', 'icon' ], true ) ? sanitize_key( (string) $input['purpose'] ) : ( [ 'hero' => 'section_image', 'about' => 'section_image', 'project_image' => 'project_image', 'portrait' => 'portrait', 'avatar' => 'avatar', 'logo' => 'logo', 'icon' => 'icon' ][ $role ] ?? null ),
		'alt' => sanitize_text_field( (string) ( $input['alt'] ?? '' ) ),
		'focal_point' => $focal === null ? null : [
			'x' => max( 0, min( 1, (float) ( $focal['x'] ?? 0.5 ) ) ),
			'y' => max( 0, min( 1, (float) ( $focal['y'] ?? 0.5 ) ) ),
		],
		'crop' => sanitize_key( (string) ( $input['crop'] ?? '' ) ) ?: null,
		'object_fit' => in_array( sanitize_key( (string) ( $input['object_fit'] ?? 'cover' ) ), [ 'cover', 'contain', 'fill' ], true ) ? sanitize_key( (string) ( $input['object_fit'] ?? 'cover' ) ) : 'cover',
		'license' => sanitize_text_field( (string) ( $input['license'] ?? '' ) ),
		'attribution' => sanitize_text_field( (string) ( $input['attribution'] ?? '' ) ),
		'allowed_reuse' => ! empty( $input['allowed_reuse'] ),
		'allow_shared_asset' => ! empty( $input['allow_shared_asset'] ),
		'provenance' => is_array( $input['provenance'] ?? null ) ? $input['provenance'] : [ 'source' => 'unknown' ],
		// Intrinsic file facts are separate from the per-composition render policy.
		'asset_facts' => $width > 0 && $height > 0 ? [ 'width' => $width, 'height' => $height, 'aspect_ratio' => round( $width / $height, 5 ), 'mime' => sanitize_mime_type( (string) ( $facts['mime'] ?? '' ) ) ] : [],
		'render' => [
			'object_fit' => in_array( sanitize_key( (string) ( $render['object_fit'] ?? '' ) ), [ 'cover', 'contain' ], true ) ? sanitize_key( (string) $render['object_fit'] ) : null,
			'focal_point' => is_array( $render['focal_point'] ?? null ) ? [ 'x' => max( 0, min( 1, (float) ( $render['focal_point']['x'] ?? 0.5 ) ) ), 'y' => max( 0, min( 1, (float) ( $render['focal_point']['y'] ?? 0.5 ) ) ) ] : null,
			'width' => wpae_reference_set_responsive_dimensions( $render['width'] ?? [] ),
			'height' => wpae_reference_set_responsive_dimensions( $render['height'] ?? [] ),
			'shape' => in_array( sanitize_key( (string) ( $render['shape'] ?? '' ) ), [ 'natural', 'rounded', 'circle' ], true ) ? sanitize_key( (string) $render['shape'] ) : 'natural',
			'radius_token' => in_array( sanitize_key( (string) ( $render['radius_token'] ?? '' ) ), [ 'radius.card', 'radius.pill' ], true ) ? sanitize_key( (string) $render['radius_token'] ) : null,
		],
	];
}

/** Resolve explicit same-site media references to owned WordPress image attachments before Brief freeze. */
function wpae_reference_set_resolve_wordpress_asset( array $reference ): array {
	$reference = wpae_reference_set_normalize( $reference );
	$required = [ 'attachment_url_to_postid', 'get_post_type', 'wp_attachment_is_image', 'current_user_can', 'wp_get_attachment_metadata', 'wp_get_attachment_url', 'get_post_meta', 'get_post_mime_type' ];
	foreach ( $required as $function ) { if ( ! function_exists( $function ) ) { return $reference; } }
	$attachment_id = absint( $reference['attachment_id'] ?? 0 );
	$source_host = strtolower( (string) ( parse_url( (string) $reference['source_url'], PHP_URL_HOST ) ?: '' ) );
	$site_host = function_exists( 'home_url' ) ? strtolower( (string) ( parse_url( home_url( '/' ), PHP_URL_HOST ) ?: '' ) ) : '';
	$is_site_asset = $attachment_id > 0 || ( $source_host !== '' && $site_host !== '' && hash_equals( $site_host, $source_host ) );
	if ( ! $is_site_asset ) { return $reference; }
	if ( $attachment_id <= 0 && function_exists( 'attachment_url_to_postid' ) ) {
		$attachment_id = absint( attachment_url_to_postid( (string) $reference['source_url'] ) );
	}
	if ( $attachment_id <= 0 || get_post_type( $attachment_id ) !== 'attachment' || ! wp_attachment_is_image( $attachment_id ) ) {
		$reference['allowed_reuse'] = false;
		$reference['provenance'] = array_merge( (array) $reference['provenance'], [ 'resolution' => 'wordpress_attachment', 'reuse_permission' => 'unresolved_site_attachment' ] );
		return $reference;
	}
	$can_reuse = current_user_can( 'edit_post', $attachment_id );
	if ( ! $can_reuse ) {
		$reference['allowed_reuse'] = false;
		$reference['provenance'] = array_merge( (array) $reference['provenance'], [ 'resolution' => 'wordpress_attachment', 'reuse_permission' => 'denied_current_user_cannot_edit' ] );
		return $reference;
	}
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$width = absint( $metadata['width'] ?? 0 );
	$height = absint( $metadata['height'] ?? 0 );
	$attached_url = wp_get_attachment_url( $attachment_id );
	$attachment_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	$reference['attachment_id'] = $attachment_id;
	$reference['source_url'] = esc_url_raw( (string) ( $attached_url ?: $reference['source_url'] ) );
	$reference['asset_facts'] = [ 'width' => $width, 'height' => $height, 'mime' => get_post_mime_type( $attachment_id ) ];
	if ( trim( (string) $reference['alt'] ) === '' ) { $reference['alt'] = sanitize_text_field( (string) $attachment_alt ); }
	$reference['allowed_reuse'] = $width > 0 && $height > 0 && trim( (string) $reference['alt'] ) !== '';
	$reference['license'] = trim( (string) $reference['license'] ) ?: 'WordPress site-owned media';
	$reference['provenance'] = array_merge( (array) $reference['provenance'], [ 'resolution' => 'wordpress_attachment', 'attachment_id' => $attachment_id, 'reuse_permission' => 'explicit_prompt_reference_and_current_user_can_edit', 'source_page' => 'current_wordpress_site' ] );
	return wpae_reference_set_normalize( $reference );
}

/** Resolve an explicitly licensed Unsplash image to verified intrinsic facts. */
function wpae_reference_set_resolve_remote_image( array $reference, ?callable $resolver = null ): array {
	$url = trim( (string) ( $reference['source_url'] ?? '' ) );
	$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
	if ( ! is_array( $parts ) || strtolower( (string) ( $parts['scheme'] ?? '' ) ) !== 'https' || strtolower( (string) ( $parts['host'] ?? '' ) ) !== 'images.unsplash.com' || ! preg_match( '#^/photo-[A-Za-z0-9_-]+#', (string) ( $parts['path'] ?? '' ) ) ) {
		return [ 'ok' => false, 'reason' => 'remote_host_or_path_not_approved' ];
	}
	if ( ! preg_match( '/^unsplash license$/i', trim( (string) ( $reference['license'] ?? '' ) ) ) || ( ( $reference['provenance']['source'] ?? '' ) !== 'prompt' && empty( $reference['provenance']['approved_catalog'] ) ) ) {
		return [ 'ok' => false, 'reason' => 'remote_license_or_provenance_missing' ];
	}
	$facts = null;
	if ( $resolver !== null ) {
		$facts = $resolver( $url, $reference );
	} elseif ( function_exists( 'wp_safe_remote_get' ) && function_exists( 'wp_remote_retrieve_response_code' ) && function_exists( 'wp_remote_retrieve_header' ) && function_exists( 'wp_remote_retrieve_body' ) && function_exists( 'getimagesizefromstring' ) ) {
		$response = wp_safe_remote_get( $url, [ 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 4194304, 'headers' => [ 'Accept' => 'image/jpeg,image/png,image/webp,image/avif' ] ] );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) { return [ 'ok' => false, 'reason' => 'remote_fetch_failed' ]; }
		$content_type = strtolower( trim( (string) wp_remote_retrieve_header( $response, 'content-type' ) ) );
		$mime = strtok( $content_type, ';' );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ], true ) ) { return [ 'ok' => false, 'reason' => 'remote_not_supported_image_mime' ]; }
		$bytes = wp_remote_retrieve_body( $response );
		if ( ! is_string( $bytes ) || $bytes === '' || strlen( $bytes ) > 4194304 ) { return [ 'ok' => false, 'reason' => 'remote_image_size_invalid' ]; }
		$image = @getimagesizefromstring( $bytes );
		if ( ! is_array( $image ) || empty( $image[0] ) || empty( $image[1] ) || strtolower( (string) ( $image['mime'] ?? '' ) ) !== $mime ) { return [ 'ok' => false, 'reason' => 'remote_image_metadata_invalid' ]; }
		$facts = [ 'width' => (int) $image[0], 'height' => (int) $image[1], 'mime' => (string) $image['mime'] ];
	}
	if ( ! is_array( $facts ) ) { return [ 'ok' => false, 'reason' => 'remote_resolver_unavailable' ]; }
	$width = absint( $facts['width'] ?? 0 );
	$height = absint( $facts['height'] ?? 0 );
	$mime = strtolower( sanitize_mime_type( (string) ( $facts['mime'] ?? '' ) ) );
	if ( $width < 16 || $height < 16 || ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ], true ) ) { return [ 'ok' => false, 'reason' => 'remote_image_facts_invalid' ]; }
	$reference['asset_facts'] = [ 'width' => $width, 'height' => $height, 'mime' => $mime ];
	$reference['allowed_reuse'] = trim( (string) ( $reference['alt'] ?? '' ) ) !== '';
	$reference['provenance'] = array_merge( (array) ( $reference['provenance'] ?? [] ), [ 'resolution' => 'verified_remote_image', 'reuse_permission' => 'explicit_prompt_license', 'resolved_dimensions' => [ $width, $height ] ] );
	return [ 'ok' => true, 'reference' => wpae_reference_set_normalize( $reference ) ];
}

/**
 * Resolve exact URLs from the plugin's reviewed image catalog without relying
 * on outbound HTTP from the WordPress host. The catalog stores intrinsic facts
 * that were checked against the CDN response; this never substitutes a URL.
 */
function wpae_reference_set_reviewed_catalog_facts( string $url ): array {
	if ( ! function_exists( 'wpae_design_plan_default_service_media' ) ) { return []; }
	foreach ( wpae_design_plan_default_service_media() as $asset ) {
		if ( ! is_array( $asset ) || ! hash_equals( trim( (string) ( $asset['source_url'] ?? '' ) ), trim( $url ) ) ) { continue; }
		return [
			'asset_facts' => [ 'width' => 1200, 'height' => 675, 'mime' => 'image/jpeg' ],
			'license' => 'Unsplash License',
			'catalog_id' => 'wpae-reviewed-unsplash-16x9-v1',
		];
	}
	return [];
}

function wpae_reference_set_asset_identity( array $reference ): string {
	$attachment_id = absint( $reference['attachment_id'] ?? 0 );
	if ( $attachment_id > 0 ) { return 'wp:' . $attachment_id; }
	$url = trim( (string) ( $reference['source_url'] ?? '' ) );
	return $url !== '' ? 'url:' . hash( 'sha256', $url ) : 'asset:' . sanitize_key( (string) ( $reference['asset_id'] ?? '' ) );
}

/** Resolve explicit asset references before Brief freeze; never search or substitute media. */
function wpae_reference_set_resolve_explicit_assets( array $references, ?callable $remote_resolver = null ): array {
	$resolved = [];
	$errors = [];
	$seen_assets = [];
	foreach ( $references as $index => $input ) {
		if ( ! is_array( $input ) ) { $errors[] = 'media_reference_' . (int) $index . '_not_array'; continue; }
		$reference = wpae_reference_set_normalize( $input );
		$reference = wpae_reference_set_resolve_wordpress_asset( $reference );
		$asset_id = sanitize_key( (string) ( $reference['asset_id'] ?? '' ) );
		$group_id = sanitize_key( (string) ( $reference['group_id'] ?? '' ) );
		$role = sanitize_key( (string) ( $reference['role'] ?? '' ) );
		$source = (string) ( $reference['source_url'] ?? '' );
		$host = strtolower( (string) ( parse_url( $source, PHP_URL_HOST ) ?: '' ) );
		$external_license_ok = $host === 'images.unsplash.com'
			&& preg_match( '/^unsplash license$/i', trim( (string) ( $reference['license'] ?? '' ) ) )
			&& ( ( $reference['provenance']['source'] ?? '' ) === 'prompt' || ! empty( $reference['provenance']['approved_catalog'] ) );
		if ( $external_license_ok ) {
			$catalog = wpae_reference_set_reviewed_catalog_facts( $source );
			if ( $catalog ) {
				$reference['asset_facts'] = $catalog['asset_facts'];
				$reference['license'] = $catalog['license'];
				$reference['allowed_reuse'] = trim( (string) ( $reference['alt'] ?? '' ) ) !== '';
				$reference['provenance'] = array_merge( (array) $reference['provenance'], [ 'resolution' => 'verified_reviewed_catalog', 'approved_catalog' => true, 'catalog' => $catalog['catalog_id'], 'reuse_permission' => 'explicit_prompt_license_and_exact_reviewed_catalog_match', 'resolved_dimensions' => [ 1200, 675 ] ] );
				$reference = wpae_reference_set_normalize( $reference );
			} else {
				$remote = wpae_reference_set_resolve_remote_image( $reference, $remote_resolver );
				if ( ! empty( $remote['ok'] ) ) { $reference = $remote['reference']; }
				else { $reference['allowed_reuse'] = false; $reference['provenance']['resolution_error'] = sanitize_key( (string) ( $remote['reason'] ?? 'remote_resolution_failed' ) ); }
			}
		}
		$resolved_site_asset = ( $reference['provenance']['reuse_permission'] ?? '' ) === 'explicit_prompt_reference_and_current_user_can_edit';
		$facts = (array) ( $reference['asset_facts'] ?? [] );
		if ( $asset_id === '' || $group_id === '' || ! in_array( $role, wpae_reference_set_roles(), true ) || empty( $reference['allowed_reuse'] ) || trim( (string) ( $reference['alt'] ?? '' ) ) === '' || absint( $facts['width'] ?? 0 ) < 16 || absint( $facts['height'] ?? 0 ) < 16 || ( ! $resolved_site_asset && ! $external_license_ok ) ) {
			$errors[] = 'media_reference_' . (int) $index . '_permission_or_owner_unresolved';
		}
		$identity = wpae_reference_set_asset_identity( $reference );
		if ( isset( $seen_assets[$identity] ) ) {
			$previous = $seen_assets[$identity];
			$explicit_reuse = ! empty( $reference['allow_shared_asset'] ) && ! empty( $previous['allow_shared_asset'] ) && is_array( $reference['provenance']['reuse_authorization_source_span'] ?? null ) && is_array( $previous['reuse_authorization_source_span'] ?? null );
			if ( ! $explicit_reuse || $previous['group_id'] === $group_id ) { $errors[] = 'media_reference_' . (int) $index . '_asset_reused_without_explicit_permission'; }
		}
		$seen_assets[$identity] = [ 'group_id' => $group_id, 'source_url' => $source, 'allow_shared_asset' => ! empty( $reference['allow_shared_asset'] ), 'reuse_authorization_source_span' => $reference['provenance']['reuse_authorization_source_span'] ?? null ];
		$resolved[] = $reference;
	}
	return [ 'references' => $resolved, 'errors' => array_values( array_unique( $errors ) ) ];
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
		$facts = (array) ( $reference['asset_facts'] ?? [] );
		if ( $facts && ( absint( $facts['width'] ?? 0 ) <= 0 || absint( $facts['height'] ?? 0 ) <= 0 || ! is_numeric( $facts['aspect_ratio'] ?? null ) || (float) $facts['aspect_ratio'] <= 0 ) ) {
			$errors[] = 'reference_' . (int) $index . '_asset_facts';
		}
	}
	return [ 'ok' => empty( $errors ), 'errors' => $errors ];
}
