<?php

defined( 'ABSPATH' ) || exit;

function wpae_skill_manifest_capabilities(): array {
    return [ 'elementor_read', 'elementor_write', 'design_system', 'block_library', 'media_upload', 'visual_audit', 'rollback', 'ai_vision' ];
}

function wpae_skill_manifest_pipeline_endpoints(): array {
    return [
        '/guide' => [ 'GET' ],
        '/capabilities' => [ 'GET' ],
        '/health' => [ 'GET' ],
        '/audit' => [ 'POST' ],
        '/logs' => [ 'GET' ],
        '/elementor/design-system' => [ 'POST' ],
        '/elementor/design-system/update' => [ 'POST' ],
        '/elementor/blueprint' => [ 'POST' ],
        '/elementor/recipes' => [ 'GET' ],
        '/elementor/recipes/{id}' => [ 'GET' ],
        '/elementor/compose' => [ 'POST' ],
        '/elementor/blocks' => [ 'GET', 'POST' ],
        '/elementor/blocks/{id}' => [ 'GET', 'DELETE' ],
        '/elementor/blocks/{id}/instantiate' => [ 'GET' ],
        '/elementor/normalize' => [ 'POST' ],
        '/elementor/validate' => [ 'POST' ],
        '/elementor/visual-audit' => [ 'POST' ],
        '/elementor/editability-audit' => [ 'POST' ],
        '/elementor/page' => [ 'POST' ],
        '/elementor/update' => [ 'POST' ],
        '/elementor/patch' => [ 'POST' ],
        '/visual-audit' => [ 'POST' ],
        '/vision/analyze' => [ 'POST' ],
        '/vision/report' => [ 'POST' ],
        '/vision/page-review' => [ 'POST' ],
        '/media/upload' => [ 'POST' ],
        '/rollback' => [ 'POST' ],
    ];
}

function wpae_skill_manifest_endpoint_capabilities( string $endpoint, string $method ): array {
    if ( strpos( $endpoint, '/elementor/blocks' ) === 0 ) {
        return $method === 'GET' ? [ 'block_library' ] : [ 'block_library', 'elementor_write' ];
    }
    if ( strpos( $endpoint, '/elementor/design-system' ) === 0 ) {
        return $endpoint === '/elementor/design-system/update' ? [ 'design_system', 'elementor_write' ] : [ 'design_system' ];
    }
    if ( in_array( $endpoint, [ '/audit', '/visual-audit', '/elementor/visual-audit', '/elementor/editability-audit' ], true ) ) {
        return [ 'visual_audit' ];
    }
    if ( $endpoint === '/media/upload' ) {
        return [ 'media_upload' ];
    }
    if ( $endpoint === '/rollback' ) {
        return [ 'rollback' ];
    }
    if ( strpos( $endpoint, '/vision/' ) === 0 ) {
        return [ 'ai_vision' ];
    }
    if ( in_array( $endpoint, [ '/elementor/page', '/elementor/update', '/elementor/patch' ], true ) ) {
        return [ 'elementor_write' ];
    }
    return strpos( $endpoint, '/elementor/' ) === 0 ? [ 'elementor_read' ] : [];
}

function wpae_skill_manifest_string_list( $items, int $limit = 30 ): array {
    $items = is_array( $items ) ? $items : [];
    $clean = [];
    foreach ( array_slice( $items, 0, $limit ) as $item ) {
        $item = sanitize_key( (string) $item );
        if ( $item !== '' ) {
            $clean[] = $item;
        }
    }
    return array_values( array_unique( $clean ) );
}

function wpae_normalize_skill_manifest( $raw_manifest, array $source_fallback = [] ) {
    if ( $raw_manifest === null || $raw_manifest === [] || $raw_manifest === '' ) {
        return null;
    }
    if ( is_string( $raw_manifest ) ) {
        $raw_manifest = json_decode( $raw_manifest, true );
    }
    if ( ! is_array( $raw_manifest ) ) {
        return new WP_Error( 'wpae_invalid_skill_manifest', 'Skill manifest must be a JSON object.' );
    }

    $errors = [];
    if ( isset( $raw_manifest['format'] ) && (string) $raw_manifest['format'] !== 'wpae-skill-manifest-v1' ) {
        $errors[] = [ 'field' => 'format', 'error' => 'Manifest format must be wpae-skill-manifest-v1.' ];
    }
    $version = sanitize_text_field( (string) ( $raw_manifest['version'] ?? '1.0.0' ) );
    if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
        $errors[] = [ 'field' => 'version', 'error' => 'Manifest version must use x.y.z.' ];
    }
    $capabilities = wpae_skill_manifest_string_list( $raw_manifest['capabilities'] ?? [] );
    $invalid_capabilities = array_values( array_diff( $capabilities, wpae_skill_manifest_capabilities() ) );
    if ( ! empty( $invalid_capabilities ) ) {
        $errors[] = [ 'field' => 'capabilities', 'invalid' => $invalid_capabilities ];
    }

    $pipeline = [];
    $allowed_endpoints = wpae_skill_manifest_pipeline_endpoints();
    foreach ( array_slice( is_array( $raw_manifest['pipeline'] ?? null ) ? $raw_manifest['pipeline'] : [], 0, 30 ) as $index => $step ) {
        if ( is_string( $step ) && preg_match( '/^(GET|POST|DELETE)\s+(.+)$/i', trim( $step ), $matches ) ) {
            $step = [ 'method' => $matches[1], 'endpoint' => $matches[2] ];
        }
        if ( ! is_array( $step ) ) {
            $errors[] = [ 'field' => 'pipeline.' . $index, 'error' => 'Pipeline step must contain method and endpoint.' ];
            continue;
        }
        $method = strtoupper( sanitize_key( (string) ( $step['method'] ?? '' ) ) );
        $endpoint = '/' . ltrim( sanitize_text_field( (string) ( $step['endpoint'] ?? '' ) ), '/' );
        $endpoint = preg_replace( '#^wp-json/ai-executor/v1/#', '', ltrim( $endpoint, '/' ) );
        $endpoint = '/' . ltrim( (string) $endpoint, '/' );
        if ( ! isset( $allowed_endpoints[ $endpoint ] ) || ! in_array( $method, $allowed_endpoints[ $endpoint ], true ) ) {
            $errors[] = [ 'field' => 'pipeline.' . $index, 'method' => $method, 'endpoint' => $endpoint, 'error' => 'Endpoint or method is not allowed for skill workflows.' ];
            continue;
        }
        $required_capabilities = wpae_skill_manifest_endpoint_capabilities( $endpoint, $method );
        $missing_capabilities = array_values( array_diff( $required_capabilities, $capabilities ) );
        if ( ! empty( $missing_capabilities ) ) {
            $errors[] = [ 'field' => 'pipeline.' . $index, 'endpoint' => $endpoint, 'required_capabilities' => $required_capabilities, 'missing_capabilities' => $missing_capabilities, 'error' => 'Pipeline endpoint is not declared in manifest capabilities.' ];
            continue;
        }
        $pipeline[] = [ 'method' => $method, 'endpoint' => $endpoint ];
    }

    if ( ! empty( $errors ) ) {
        return new WP_Error( 'wpae_invalid_skill_manifest', 'Skill manifest failed validation.', [ 'errors' => $errors ] );
    }

    $source = is_array( $raw_manifest['source'] ?? null ) ? $raw_manifest['source'] : [];
    $compatibility = is_array( $raw_manifest['compatibility'] ?? null ) ? $raw_manifest['compatibility'] : [];

    return [
        'format' => 'wpae-skill-manifest-v1',
        'version' => $version,
        'capabilities' => $capabilities,
        'inputs' => wpae_skill_manifest_string_list( $raw_manifest['inputs'] ?? [] ),
        'pipeline' => $pipeline,
        'source' => [
            'type' => sanitize_key( (string) ( $source['type'] ?? $source_fallback['source_type'] ?? 'manual' ) ),
            'url' => esc_url_raw( (string) ( $source['url'] ?? $source_fallback['source_url'] ?? '' ) ),
            'sha256' => preg_match( '/^[a-f0-9]{64}$/i', (string) ( $source['sha256'] ?? $source_fallback['source_sha256'] ?? '' ) )
                ? strtolower( (string) ( $source['sha256'] ?? $source_fallback['source_sha256'] ?? '' ) )
                : '',
        ],
        'license' => sanitize_text_field( (string) ( $raw_manifest['license'] ?? 'unspecified' ) ),
        'compatibility' => [
            'plugin_min_version' => sanitize_text_field( (string) ( $compatibility['plugin_min_version'] ?? WPAE_VERSION ) ),
            'elementor_min_version' => sanitize_text_field( (string) ( $compatibility['elementor_min_version'] ?? '' ) ),
            'requires_flexbox' => array_key_exists( 'requires_flexbox', $compatibility ) ? (bool) $compatibility['requires_flexbox'] : true,
            'storage' => 'wp_options',
        ],
    ];
}

function wpae_validate_skill_manifest_request( WP_REST_Request $request ): WP_REST_Response {
    $manifest = wpae_normalize_skill_manifest( $request->get_param( 'manifest' ) );
    if ( is_wp_error( $manifest ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $manifest->get_error_message(),
            'code' => $manifest->get_error_code(),
            'details' => $manifest->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'writes' => false,
        'legacy_compatible' => $manifest === null,
        'manifest' => $manifest,
    ], 200 );
}
