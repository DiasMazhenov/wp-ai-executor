<?php

defined( 'ABSPATH' ) || exit;

function wpae_get_skill_store(): array {
    $skills = get_option( 'wp_ai_executor_skills', [] );
    return is_array( $skills ) ? $skills : [];
}

function wpae_update_skill_store( array $skills ): void {
    update_option( 'wp_ai_executor_skills', $skills, false );
}

function wpae_normalize_skill_id( string $id ): string {
    $id = strtolower( trim( $id ) );
    $id = preg_replace( '/[^a-z0-9_-]+/', '-', $id );
    $id = trim( (string) $id, '-' );
    return $id !== '' ? $id : 'skill-' . wp_generate_password( 8, false, false );
}

function wpae_sort_skills( array $skills ): array {
    uasort( $skills, function ( array $a, array $b ): int {
        $priority = (int) ( $b['priority'] ?? 0 ) <=> (int) ( $a['priority'] ?? 0 );
        if ( $priority !== 0 ) {
            return $priority;
        }
        return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
    } );
    return $skills;
}

function wpae_is_list_array( array $items ): bool {
    if ( $items === [] ) {
        return true;
    }

    return array_keys( $items ) === range( 0, count( $items ) - 1 );
}

function wpae_normalize_skill_data( array $data, array $existing_skills = [] ) {
    $raw_id = (string) ( $data['id'] ?? '' );
    $raw_name = (string) ( $data['name'] ?? '' );
    $name = sanitize_text_field( $raw_name !== '' ? $raw_name : $raw_id );
    $content = (string) ( $data['content'] ?? '' );

    if ( $name === '' ) {
        return new WP_Error( 'wpae_skill_name_required', 'Skill name is required.' );
    }

    if ( trim( $content ) === '' ) {
        return new WP_Error( 'wpae_skill_content_required', 'Skill content is required.' );
    }

    if ( strlen( $content ) > 120000 ) {
        return new WP_Error( 'wpae_skill_too_large', 'Skill content exceeds 120 KB limit.' );
    }

    $id = wpae_normalize_skill_id( (string) ( $data['id'] ?? $name ) );
    $now = gmdate( 'c' );
    $enforce = $data['enforce'] ?? [];

    return [
        'id' => $id,
        'name' => $name,
        'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
        'content' => wp_check_invalid_utf8( $content ),
        'enforce' => wpae_sanitize_skill_enforce_rules( is_array( $enforce ) ? $enforce : [] ),
        'enabled' => array_key_exists( 'enabled', $data ) ? (bool) $data['enabled'] : true,
        'priority' => max( -100, min( 100, (int) ( $data['priority'] ?? 0 ) ) ),
        'created_at' => $existing_skills[ $id ]['created_at'] ?? $now,
        'updated_at' => $now,
        'source_url' => esc_url_raw( (string) ( $data['source_url'] ?? ( $existing_skills[ $id ]['source_url'] ?? '' ) ) ),
        'source_type' => sanitize_key( (string) ( $data['source_type'] ?? ( $existing_skills[ $id ]['source_type'] ?? 'manual' ) ) ),
        'source_sha256' => sanitize_text_field( (string) ( $data['source_sha256'] ?? ( $existing_skills[ $id ]['source_sha256'] ?? '' ) ) ),
        'imported_at' => sanitize_text_field( (string) ( $data['imported_at'] ?? ( $existing_skills[ $id ]['imported_at'] ?? '' ) ) ),
    ];
}

function wpae_upsert_skill( array $data ) {
    $skills = wpae_get_skill_store();
    $skill = wpae_normalize_skill_data( $data, $skills );

    if ( is_wp_error( $skill ) ) {
        return $skill;
    }

    $skills[ $skill['id'] ] = $skill;
    wpae_update_skill_store( $skills );

    return $skill;
}

function wpae_build_skill_bundle( bool $include_disabled = true ): array {
    $skills = wpae_sort_skills( wpae_get_skill_store() );

    if ( ! $include_disabled ) {
        $skills = array_filter( $skills, fn( array $skill ): bool => ! empty( $skill['enabled'] ) );
    }

    return [
        'schema' => 'wp-ai-executor.skill-bundle',
        'schema_version' => 1,
        'plugin_version' => WPAE_VERSION,
        'exported_at' => gmdate( 'c' ),
        'storage' => 'wp_options',
        'file_policy' => 'No skill files are created on the server.',
        'skills' => array_values( $skills ),
    ];
}

function wpae_extract_skill_import_items( $payload ) {
    if ( is_string( $payload ) ) {
        $payload = json_decode( $payload, true );
    }

    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'wpae_invalid_skill_bundle', 'Skill import payload must be a JSON object or array.' );
    }

    if ( isset( $payload['skills'] ) && is_array( $payload['skills'] ) ) {
        return $payload['skills'];
    }

    if ( wpae_is_list_array( $payload ) ) {
        return $payload;
    }

    return new WP_Error( 'wpae_invalid_skill_bundle', 'Skill import payload must contain a skills array.' );
}

function wpae_import_skill_items( array $items, string $mode = 'merge' ) {
    $mode = $mode === 'replace' ? 'replace' : 'merge';
    $existing = $mode === 'replace' ? [] : wpae_get_skill_store();
    $next = $existing;
    $imported = [];
    $errors = [];

    if ( count( $items ) > 100 ) {
        return new WP_Error( 'wpae_skill_bundle_too_large', 'Skill bundle contains more than 100 skills.' );
    }

    foreach ( $items as $index => $item ) {
        if ( ! is_array( $item ) ) {
            $errors[] = [ 'index' => $index, 'error' => 'Skill item must be an object.' ];
            continue;
        }

        $skill = wpae_normalize_skill_data( $item, $next );
        if ( is_wp_error( $skill ) ) {
            $errors[] = [ 'index' => $index, 'error' => $skill->get_error_message() ];
            continue;
        }

        $next[ $skill['id'] ] = $skill;
        $imported[] = $skill;
    }

    if ( ! empty( $errors ) ) {
        return new WP_Error( 'wpae_skill_import_failed', 'Skill import failed validation.', [ 'errors' => $errors ] );
    }

    wpae_update_skill_store( $next );

    return [
        'mode' => $mode,
        'imported' => $imported,
        'imported_count' => count( $imported ),
        'total_count' => count( $next ),
    ];
}

function wpae_get_skills(): WP_REST_Response {
    $skills = wpae_sort_skills( wpae_get_skill_store() );
    return new WP_REST_Response( [
        'skills' => array_values( $skills ),
        'count' => count( $skills ),
    ], 200 );
}

function wpae_export_skills( WP_REST_Request $request ): WP_REST_Response {
    $include_disabled = ! $request->has_param( 'enabled_only' ) || ! (bool) $request->get_param( 'enabled_only' );
    return new WP_REST_Response( wpae_build_skill_bundle( $include_disabled ), 200 );
}

function wpae_import_skills( WP_REST_Request $request ) {
    $payload = $request->get_json_params();
    if ( empty( $payload ) && $request->has_param( 'bundle' ) ) {
        $payload = $request->get_param( 'bundle' );
    }

    $items = wpae_extract_skill_import_items( $payload );
    if ( is_wp_error( $items ) ) {
        return new WP_REST_Response( [ 'error' => $items->get_error_message() ], 400 );
    }

    $result = wpae_import_skill_items( $items, sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'merge' ) ) );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [
            'error' => $result->get_error_message(),
            'details' => $result->get_error_data(),
        ], 422 );
    }

    return new WP_REST_Response( array_merge( [ 'ok' => true ], $result ), 200 );
}

function wpae_normalize_github_skill_url( string $source_url ) {
    $source_url = trim( $source_url );
    $parts = wp_parse_url( $source_url );

    if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' ) {
        return new WP_Error( 'wpae_invalid_skill_url', 'Skill URL must be an HTTPS GitHub URL.' );
    }

    $host = strtolower( (string) ( $parts['host'] ?? '' ) );
    $path = (string) ( $parts['path'] ?? '' );

    if ( $host === 'raw.githubusercontent.com' ) {
        if ( ! preg_match( '#^/[^/]+/[^/]+/.+\.(?:md|json)$#i', $path ) ) {
            return new WP_Error( 'wpae_invalid_skill_url', 'Raw GitHub skill URL must point to an .md or .json file.' );
        }

        return $source_url;
    }

    if ( $host !== 'github.com' ) {
        return new WP_Error( 'wpae_invalid_skill_url', 'Only github.com and raw.githubusercontent.com skill URLs are allowed.' );
    }

    $segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
    if ( count( $segments ) < 2 ) {
        return new WP_Error( 'wpae_invalid_skill_url', 'GitHub skill URL must include owner and repository.' );
    }

    $owner = rawurlencode( $segments[0] );
    $repo = rawurlencode( $segments[1] );

    if ( isset( $segments[2] ) && $segments[2] === 'blob' && count( $segments ) >= 5 ) {
        $ref = rawurlencode( $segments[3] );
        $file_path = implode( '/', array_map( 'rawurlencode', array_slice( $segments, 4 ) ) );

        return "https://raw.githubusercontent.com/{$owner}/{$repo}/{$ref}/{$file_path}";
    }

    if ( isset( $segments[2] ) && $segments[2] === 'tree' && count( $segments ) >= 4 ) {
        $ref = rawurlencode( $segments[3] );
        $dir_path = implode( '/', array_map( 'rawurlencode', array_slice( $segments, 4 ) ) );
        $suffix = $dir_path !== '' ? '/' . $dir_path : '';

        return "https://raw.githubusercontent.com/{$owner}/{$repo}/{$ref}{$suffix}/SKILL.md";
    }

    return new WP_Error( 'wpae_invalid_skill_url', 'Use a GitHub blob URL to SKILL.md/.json or a tree URL to a skill folder.' );
}

function wpae_guess_skill_name_from_url( string $source_url ): string {
    $path = (string) ( wp_parse_url( $source_url, PHP_URL_PATH ) ?: '' );
    $segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
    $filename = end( $segments );
    $parent = count( $segments ) >= 2 ? $segments[ count( $segments ) - 2 ] : '';

    if ( is_string( $filename ) && strtolower( $filename ) !== 'skill.md' && $filename !== '' ) {
        $guessed = preg_replace( '/\.(md|json)$/i', '', sanitize_title( $filename ) );
        return is_string( $guessed ) && $guessed !== '' ? $guessed : 'github-skill';
    }

    return $parent !== '' ? sanitize_title( $parent ) : 'github-skill';
}

function wpae_build_skill_from_markdown( string $content, string $source_url, array $request_data = [] ): array {
    $name = sanitize_text_field( (string) ( $request_data['name'] ?? '' ) );
    if ( $name === '' && preg_match( '/^\s*#\s+(.+)$/m', $content, $matches ) ) {
        $name = sanitize_text_field( trim( (string) $matches[1] ) );
    }
    if ( $name === '' ) {
        $name = wpae_guess_skill_name_from_url( $source_url );
    }

    $description = sanitize_textarea_field( (string) ( $request_data['description'] ?? '' ) );
    if ( $description === '' && preg_match( '/^\s*(?:description|desc)\s*:\s*(.+)$/mi', $content, $matches ) ) {
        $description = sanitize_textarea_field( trim( (string) $matches[1] ) );
    }

    return [
        'id' => (string) ( $request_data['id'] ?? wpae_normalize_skill_id( $name ) ),
        'name' => $name,
        'description' => $description,
        'content' => $content,
        'enabled' => array_key_exists( 'enabled', $request_data ) ? (bool) $request_data['enabled'] : true,
        'priority' => (int) ( $request_data['priority'] ?? 10 ),
        'enforce' => is_array( $request_data['enforce'] ?? null ) ? $request_data['enforce'] : [],
        'source_url' => $source_url,
        'source_type' => 'github_url',
        'source_sha256' => hash( 'sha256', $content ),
        'imported_at' => gmdate( 'c' ),
    ];
}

function wpae_import_skill_from_url( WP_REST_Request $request ) {
    $source_url = trim( (string) ( $request->get_param( 'source_url' ) ?: $request->get_param( 'url' ) ) );
    $raw_url = wpae_normalize_github_skill_url( $source_url );

    if ( is_wp_error( $raw_url ) ) {
        return new WP_REST_Response( [ 'error' => $raw_url->get_error_message() ], 400 );
    }

    $response = wp_remote_get( $raw_url, [
        'timeout' => 20,
        'redirection' => 3,
        'limit_response_size' => 150000,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [
            'error' => 'Failed to download skill.',
            'message' => $response->get_error_message(),
        ], 502 );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $content = (string) wp_remote_retrieve_body( $response );

    if ( $status !== 200 ) {
        return new WP_REST_Response( [
            'error' => 'Skill download returned non-200 status.',
            'status' => $status,
            'raw_url' => $raw_url,
        ], 502 );
    }

    if ( trim( $content ) === '' ) {
        return new WP_REST_Response( [ 'error' => 'Downloaded skill is empty.' ], 422 );
    }

    $request_data = [
        'id' => $request->get_param( 'id' ),
        'name' => $request->get_param( 'name' ),
        'description' => $request->get_param( 'description' ),
        'enabled' => $request->has_param( 'enabled' ) ? (bool) $request->get_param( 'enabled' ) : true,
        'priority' => $request->get_param( 'priority' ),
        'enforce' => $request->get_param( 'enforce' ),
    ];

    $trimmed = ltrim( $content );
    if ( $trimmed !== '' && ( $trimmed[0] === '{' || $trimmed[0] === '[' ) ) {
        $decoded = json_decode( $content, true );
        $items = wpae_extract_skill_import_items( $decoded );
        if ( is_wp_error( $items ) ) {
            return new WP_REST_Response( [ 'error' => $items->get_error_message() ], 422 );
        }

        foreach ( $items as &$item ) {
            if ( is_array( $item ) ) {
                $item['source_url'] = $raw_url;
                $item['source_type'] = 'github_bundle';
                $item['source_sha256'] = hash( 'sha256', $content );
                $item['imported_at'] = gmdate( 'c' );
            }
        }
        unset( $item );

        $result = wpae_import_skill_items( $items, sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'merge' ) ) );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [
                'error' => $result->get_error_message(),
                'details' => $result->get_error_data(),
            ], 422 );
        }

        return new WP_REST_Response( array_merge( [
            'ok' => true,
            'source_url' => $source_url,
            'raw_url' => $raw_url,
            'source_sha256' => hash( 'sha256', $content ),
        ], $result ), 200 );
    }

    $skill_data = wpae_build_skill_from_markdown( $content, $raw_url, $request_data );
    $skill = wpae_upsert_skill( $skill_data );
    if ( is_wp_error( $skill ) ) {
        $status_code = $skill->get_error_code() === 'wpae_skill_too_large' ? 413 : 422;
        return new WP_REST_Response( [ 'error' => $skill->get_error_message() ], $status_code );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'source_url' => $source_url,
        'raw_url' => $raw_url,
        'source_sha256' => $skill['source_sha256'] ?? hash( 'sha256', $content ),
        'imported_count' => 1,
        'skill' => $skill,
    ], 200 );
}

function wpae_save_skill( WP_REST_Request $request ) {
    $skill = wpae_upsert_skill( [
        'id' => $request->get_param( 'id' ),
        'name' => $request->get_param( 'name' ),
        'description' => $request->get_param( 'description' ),
        'content' => $request->get_param( 'content' ),
        'enforce' => $request->get_param( 'enforce' ),
        'enabled' => $request->has_param( 'enabled' ) ? (bool) $request->get_param( 'enabled' ) : true,
        'priority' => $request->get_param( 'priority' ),
        'source_type' => 'manual',
    ] );

    if ( is_wp_error( $skill ) ) {
        $status = $skill->get_error_code() === 'wpae_skill_too_large' ? 413 : 400;
        return new WP_REST_Response( [ 'error' => $skill->get_error_message() ], $status );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'skill' => $skill,
    ], 200 );
}

function wpae_delete_skill( WP_REST_Request $request ) {
    $id = wpae_normalize_skill_id( (string) $request['id'] );
    $skills = wpae_get_skill_store();

    if ( ! isset( $skills[ $id ] ) ) {
        return new WP_REST_Response( [ 'error' => 'Skill not found.' ], 404 );
    }

    unset( $skills[ $id ] );
    wpae_update_skill_store( $skills );

    return new WP_REST_Response( [ 'ok' => true, 'deleted' => $id ], 200 );
}

function wpae_get_enabled_skills_for_guide(): array {
    $skills = wpae_sort_skills( wpae_get_skill_store() );
    $enabled = [];

    foreach ( $skills as $skill ) {
        if ( empty( $skill['enabled'] ) ) {
            continue;
        }
        $enabled[] = $skill;
    }

    return $enabled;
}

function wpae_sanitize_skill_enforce_rules( array $rules ): array {
    $allowed_types = [
        'forbid_elementor_eltype',
        'require_widget_key',
        'forbid_widget_key',
        'allow_widget_type',
        'forbid_widget_type',
        'require_widget_setting',
        'require_container_setting',
        'forbid_html_pattern',
    ];
    $clean = [];

    foreach ( $rules as $rule ) {
        if ( ! is_array( $rule ) ) {
            continue;
        }

        $type = sanitize_key( (string) ( $rule['type'] ?? '' ) );
        $value = sanitize_text_field( (string) ( $rule['value'] ?? '' ) );
        $target = sanitize_key( (string) ( $rule['target'] ?? '' ) );

        if ( ! in_array( $type, $allowed_types, true ) || $value === '' ) {
            continue;
        }

        $clean_rule = [
            'type' => $type,
            'value' => $value,
        ];

        if ( $target !== '' ) {
            $clean_rule['target'] = $target;
        }

        $clean[] = $clean_rule;

        if ( count( $clean ) >= 50 ) {
            break;
        }
    }

    return $clean;
}

function wpae_get_enforceable_skill_rules(): array {
    $skills = wpae_get_enabled_skills_for_guide();
    $rules = [];

    foreach ( $skills as $skill ) {
        if ( empty( $skill['enforce'] ) || ! is_array( $skill['enforce'] ) ) {
            continue;
        }

        foreach ( $skill['enforce'] as $rule ) {
            if ( is_array( $rule ) ) {
                $rules[] = [
                    'skill_id' => $skill['id'] ?? 'unknown',
                    'type' => $rule['type'] ?? '',
                    'value' => $rule['value'] ?? '',
                    'target' => $rule['target'] ?? '',
                ];
            }
        }
    }

    return $rules;
}

