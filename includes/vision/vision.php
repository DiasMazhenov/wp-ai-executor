<?php

defined( 'ABSPATH' ) || exit;

const WPAE_VISION_SETTINGS_OPTION = 'wp_ai_executor_vision_settings';
const WPAE_VISION_REPORTS_OPTION = 'wp_ai_executor_vision_reports';
const WPAE_VISION_MAX_IMAGE_BYTES = 4194304;
const WPAE_VISION_MAX_RESPONSE_BYTES = 524288;
const WPAE_VISION_MAX_REPORTS = 30;
const WPAE_VISION_RATE_LIMIT_OPTION = 'wp_ai_executor_vision_rate_window';
const WPAE_VISION_PROVIDER_CALL_LIMIT = 12;
const WPAE_VISION_PROVIDER_CALL_WINDOW = 600;

function wpae_vision_provider_options(): array {
    return [
        'gemini' => [
            'label' => 'Google Gemini',
            'model' => 'gemini-3.5-flash-lite',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'model' => 'gpt-4.1-mini',
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'model' => 'claude-sonnet-4-20250514',
        ],
    ];
}

function wpae_vision_encryption_key(): string {
    return hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|' . home_url(), true );
}

function wpae_vision_encrypt_api_key( string $api_key ) {
    if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'random_bytes' ) ) {
        return new WP_Error( 'wpae_vision_crypto_unavailable', 'OpenSSL is required to store the AI Vision provider key securely.' );
    }

    try {
        $iv = random_bytes( 12 );
    } catch ( Throwable $e ) {
        return new WP_Error( 'wpae_vision_crypto_failed', 'The AI Vision provider key could not be encrypted.' );
    }
    $tag = '';
    $ciphertext = openssl_encrypt( $api_key, 'aes-256-gcm', wpae_vision_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
    if ( $ciphertext === false || $tag === '' ) {
        return new WP_Error( 'wpae_vision_crypto_failed', 'The AI Vision provider key could not be encrypted.' );
    }

    return 'v1:' . base64_encode( $iv . $tag . $ciphertext );
}

function wpae_vision_decrypt_api_key( string $encrypted ): string {
    if ( strpos( $encrypted, 'v1:' ) !== 0 || ! function_exists( 'openssl_decrypt' ) ) {
        return '';
    }

    $payload = base64_decode( substr( $encrypted, 3 ), true );
    if ( ! is_string( $payload ) || strlen( $payload ) <= 28 ) {
        return '';
    }

    $iv = substr( $payload, 0, 12 );
    $tag = substr( $payload, 12, 16 );
    $ciphertext = substr( $payload, 28 );
    $api_key = openssl_decrypt( $ciphertext, 'aes-256-gcm', wpae_vision_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );

    return is_string( $api_key ) ? $api_key : '';
}

function wpae_get_vision_settings(): array {
    $providers = wpae_vision_provider_options();
    $stored = get_option( WPAE_VISION_SETTINGS_OPTION, [] );
    $stored = is_array( $stored ) ? $stored : [];
    $provider = sanitize_key( (string) ( $stored['provider'] ?? 'gemini' ) );
    if ( ! isset( $providers[ $provider ] ) ) {
        $provider = 'gemini';
    }

    $model = sanitize_text_field( (string) ( $stored['model'] ?? '' ) );
    if ( $model === '' || ( $provider === 'gemini' && $model === 'gemini-2.5-flash-lite' ) ) {
        $model = $providers[ $provider ]['model'];
    }

    $encrypted = (string) ( $stored['api_key_encrypted'] ?? '' );
    $api_key = wpae_vision_decrypt_api_key( $encrypted );
    return [
        'provider' => $provider,
        'provider_label' => $providers[ $provider ]['label'],
        'model' => $model,
        'has_api_key' => $api_key !== '',
        'api_key_hint' => $api_key !== '' ? 'Ключ сохранен' : 'Ключ не задан',
        'updated_at' => sanitize_text_field( (string) ( $stored['updated_at'] ?? '' ) ),
    ];
}

function wpae_get_vision_runtime_settings() {
    $settings = wpae_get_vision_settings();
    $stored = get_option( WPAE_VISION_SETTINGS_OPTION, [] );
    $api_key = wpae_vision_decrypt_api_key( (string) ( is_array( $stored ) ? ( $stored['api_key_encrypted'] ?? '' ) : '' ) );
    if ( $api_key === '' ) {
        return new WP_Error( 'wpae_vision_not_configured', 'AI Vision provider is not configured. Set an encrypted provider key in the plugin settings.' );
    }

    $settings['api_key'] = $api_key;
    return $settings;
}

function wpae_update_vision_settings( array $input ) {
    $providers = wpae_vision_provider_options();
    $provider = sanitize_key( (string) ( $input['provider'] ?? 'gemini' ) );
    if ( ! isset( $providers[ $provider ] ) ) {
        return new WP_Error( 'wpae_vision_invalid_provider', 'Unknown AI Vision provider.' );
    }

    $model = sanitize_text_field( (string) ( $input['model'] ?? '' ) );
    $model = $model !== '' ? substr( $model, 0, 120 ) : $providers[ $provider ]['model'];
    $stored = get_option( WPAE_VISION_SETTINGS_OPTION, [] );
    $stored = is_array( $stored ) ? $stored : [];
    $api_key = trim( (string) ( $input['api_key'] ?? '' ) );

    if ( ! empty( $input['clear_api_key'] ) ) {
        $stored['api_key_encrypted'] = '';
    } elseif ( $api_key !== '' ) {
        $encrypted = wpae_vision_encrypt_api_key( $api_key );
        if ( is_wp_error( $encrypted ) ) {
            return $encrypted;
        }
        $stored['api_key_encrypted'] = $encrypted;
    }

    $stored['provider'] = $provider;
    $stored['model'] = $model;
    $stored['updated_at'] = gmdate( 'c' );
    update_option( WPAE_VISION_SETTINGS_OPTION, $stored, false );

    return true;
}

function wpae_vision_trim_text( $value, int $limit = 600 ): string {
    $text = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $text, 0, $limit );
    }
    return substr( $text, 0, $limit );
}

function wpae_vision_normalize_report( $raw, array $meta = [] ): array {
    $raw = is_array( $raw ) ? $raw : [];
    $score = $raw['vision_score'] ?? $raw['score'] ?? 0;
    $confidence = $raw['confidence'] ?? 0;
    $findings = [];
    foreach ( array_slice( (array) ( $raw['findings'] ?? [] ), 0, 20 ) as $finding ) {
        if ( ! is_array( $finding ) ) {
            continue;
        }
        $severity = sanitize_key( (string) ( $finding['severity'] ?? 'minor' ) );
        if ( ! in_array( $severity, [ 'critical', 'major', 'minor', 'info' ], true ) ) {
            $severity = 'minor';
        }
        $message = wpae_vision_trim_text( $finding['message'] ?? $finding['issue'] ?? '' );
        if ( $message === '' ) {
            continue;
        }
        $findings[] = [
            'severity' => $severity,
            'category' => wpae_vision_trim_text( $finding['category'] ?? 'visual', 80 ),
            'message' => $message,
            'fix' => wpae_vision_trim_text( $finding['fix'] ?? $finding['recommendation'] ?? '', 600 ),
            'viewport' => wpae_vision_trim_text( $finding['viewport'] ?? ( $meta['viewport'] ?? '' ), 40 ),
        ];
    }

    $must_fix = [];
    foreach ( (array) ( $raw['must_fix'] ?? [] ) as $fix ) {
        $fix = wpae_vision_trim_text( $fix );
        if ( $fix !== '' ) {
            $must_fix[] = $fix;
        }
    }
    foreach ( $findings as $finding ) {
        if ( in_array( $finding['severity'], [ 'critical', 'major' ], true ) ) {
            $fix = $finding['fix'] !== '' ? $finding['fix'] : $finding['message'];
            $must_fix[] = $fix;
        }
    }

    return [
        'source' => sanitize_key( (string) ( $meta['source'] ?? 'agent' ) ),
        'provider' => sanitize_key( (string) ( $meta['provider'] ?? '' ) ) ?: null,
        'model' => wpae_vision_trim_text( $meta['model'] ?? '', 120 ) ?: null,
        'post_id' => absint( $meta['post_id'] ?? $raw['post_id'] ?? 0 ) ?: null,
        'viewport' => wpae_vision_trim_text( $meta['viewport'] ?? $raw['viewport'] ?? '', 40 ) ?: null,
        'vision_score' => max( 0, min( 100, is_numeric( $score ) ? (int) round( (float) $score ) : 0 ) ),
        'confidence' => max( 0, min( 1, is_numeric( $confidence ) ? (float) $confidence : 0 ) ),
        'summary' => wpae_vision_trim_text( $raw['summary'] ?? $raw['overall'] ?? '', 1000 ),
        'findings' => $findings,
        'must_fix' => array_values( array_unique( array_slice( $must_fix, 0, 20 ) ) ),
        'strengths' => array_values( array_filter( array_map( static fn( $item ) => wpae_vision_trim_text( $item, 300 ), array_slice( (array) ( $raw['strengths'] ?? [] ), 0, 10 ) ) ) ),
    ];
}

function wpae_validate_vision_report_payload( $report ): array {
    $errors = [];
    if ( ! is_array( $report ) ) {
        return [ 'report must be an object.' ];
    }
    if ( ! array_key_exists( 'vision_score', $report ) && ! array_key_exists( 'score', $report ) ) {
        $errors[] = 'vision_score is required.';
    }
    if ( ! is_numeric( $report['vision_score'] ?? $report['score'] ?? null ) ) {
        $errors[] = 'vision_score must be a number from 0 to 100.';
    }
    if ( is_numeric( $report['vision_score'] ?? $report['score'] ?? null ) && ( (float) ( $report['vision_score'] ?? $report['score'] ) < 0 || (float) ( $report['vision_score'] ?? $report['score'] ) > 100 ) ) {
        $errors[] = 'vision_score must be a number from 0 to 100.';
    }
    if ( ! is_array( $report['findings'] ?? null ) ) {
        $errors[] = 'findings must be an array.';
    }
    if ( isset( $report['confidence'] ) && ( ! is_numeric( $report['confidence'] ) || (float) $report['confidence'] < 0 || (float) $report['confidence'] > 1 ) ) {
        $errors[] = 'confidence must be a number from 0 to 1.';
    }
    return $errors;
}

function wpae_validate_vision_provider_report( $report ): array {
    $errors = wpae_validate_vision_report_payload( $report );
    if ( ! is_array( $report ) ) {
        return $errors;
    }

    foreach ( [ 'confidence', 'summary', 'must_fix', 'strengths' ] as $field ) {
        if ( ! array_key_exists( $field, $report ) ) {
            $errors[] = $field . ' is required in a provider report.';
        }
    }
    if ( isset( $report['summary'] ) && ! is_string( $report['summary'] ) ) {
        $errors[] = 'summary must be a string.';
    }
    foreach ( [ 'must_fix', 'strengths' ] as $field ) {
        if ( isset( $report[ $field ] ) && ! is_array( $report[ $field ] ) ) {
            $errors[] = $field . ' must be an array.';
        }
    }
    foreach ( (array) ( $report['findings'] ?? [] ) as $index => $finding ) {
        if ( ! is_array( $finding ) ) {
            $errors[] = 'findings[' . $index . '] must be an object.';
            continue;
        }
        foreach ( [ 'severity', 'category', 'message', 'fix', 'viewport' ] as $field ) {
            if ( ! isset( $finding[ $field ] ) || ! is_string( $finding[ $field ] ) ) {
                $errors[] = 'findings[' . $index . '].' . $field . ' must be a string.';
            }
        }
        if ( isset( $finding['severity'] ) && ! in_array( $finding['severity'], [ 'critical', 'major', 'minor', 'info' ], true ) ) {
            $errors[] = 'findings[' . $index . '].severity is invalid.';
        }
    }

    return array_values( array_unique( $errors ) );
}

function wpae_vision_consume_provider_slot() {
    $now = time();
    $window_start = $now - WPAE_VISION_PROVIDER_CALL_WINDOW;
    $timestamps = get_option( WPAE_VISION_RATE_LIMIT_OPTION, [] );
    $timestamps = is_array( $timestamps ) ? $timestamps : [];
    $timestamps = array_values( array_filter( $timestamps, static fn( $timestamp ) => is_numeric( $timestamp ) && (int) $timestamp >= $window_start ) );

    if ( count( $timestamps ) >= WPAE_VISION_PROVIDER_CALL_LIMIT ) {
        $oldest = (int) min( $timestamps );
        return new WP_Error( 'wpae_vision_rate_limited', 'AI Vision provider call limit reached. Retry after the current review window.', [
            'status' => 429,
            'limit' => WPAE_VISION_PROVIDER_CALL_LIMIT,
            'window_seconds' => WPAE_VISION_PROVIDER_CALL_WINDOW,
            'retry_after_seconds' => max( 1, $oldest + WPAE_VISION_PROVIDER_CALL_WINDOW - $now ),
        ] );
    }

    $timestamps[] = $now;
    update_option( WPAE_VISION_RATE_LIMIT_OPTION, $timestamps, false );
    return true;
}

function wpae_save_vision_report( array $report ): array {
    $report['report_id'] = 'vr_' . str_replace( '-', '', wp_generate_uuid4() );
    $report['created_at'] = gmdate( 'c' );
    $reports = get_option( WPAE_VISION_REPORTS_OPTION, [] );
    $reports = is_array( $reports ) ? $reports : [];
    array_unshift( $reports, $report );
    update_option( WPAE_VISION_REPORTS_OPTION, array_slice( $reports, 0, WPAE_VISION_MAX_REPORTS ), false );
    return $report;
}

function wpae_get_vision_report( string $report_id ): ?array {
    if ( $report_id === '' ) {
        return null;
    }
    foreach ( (array) get_option( WPAE_VISION_REPORTS_OPTION, [] ) as $report ) {
        if ( is_array( $report ) && hash_equals( (string) ( $report['report_id'] ?? '' ), $report_id ) ) {
            return $report;
        }
    }
    return null;
}

function wpae_get_vision_reports( int $limit = 5 ): array {
    $reports = array_values( array_filter( (array) get_option( WPAE_VISION_REPORTS_OPTION, [] ), 'is_array' ) );
    return array_slice( $reports, 0, max( 1, min( 20, $limit ) ) );
}

function wpae_evaluate_vision_report( array $report ): array {
    $validation_errors = wpae_validate_vision_report_payload( $report );
    if ( ! empty( $validation_errors ) ) {
        return [
            'ok' => false,
            'blocking' => true,
            'critical_count' => 0,
            'major_count' => 0,
            'must_fix' => [ 'AI Vision report is malformed and cannot satisfy a transaction gate.' ],
            'message' => 'AI Vision report is invalid.',
            'validation_errors' => $validation_errors,
        ];
    }

    $critical = [];
    $major = [];
    foreach ( (array) ( $report['findings'] ?? [] ) as $finding ) {
        if ( ( $finding['severity'] ?? '' ) === 'critical' ) {
            $critical[] = $finding;
        } elseif ( ( $finding['severity'] ?? '' ) === 'major' ) {
            $major[] = $finding;
        }
    }
    return [
        'ok' => empty( $critical ),
        'blocking' => ! empty( $critical ),
        'critical_count' => count( $critical ),
        'major_count' => count( $major ),
        'must_fix' => (array) ( $report['must_fix'] ?? [] ),
        'message' => empty( $critical ) ? 'AI Vision found no critical visual defects.' : 'AI Vision found critical visual defects.',
    ];
}

function wpae_vision_report_from_request( WP_REST_Request $request, bool $save_inline = false ) {
    $report_id = sanitize_text_field( (string) ( $request->get_param( 'vision_report_id' ) ?: '' ) );
    if ( $report_id !== '' ) {
        $report = wpae_get_vision_report( $report_id );
        return $report ?: new WP_Error( 'wpae_vision_report_not_found', 'The requested AI Vision report was not found or has expired.' );
    }

    $raw = $request->get_param( 'vision_report' );
    if ( ! is_array( $raw ) ) {
        $raw = $request->get_param( 'report' );
    }
    if ( ! is_array( $raw ) ) {
        return null;
    }

    $errors = wpae_validate_vision_report_payload( $raw );
    if ( ! empty( $errors ) ) {
        return new WP_Error( 'wpae_invalid_vision_report', 'AI Vision report failed validation.', [ 'errors' => $errors ] );
    }
    $report = wpae_vision_normalize_report( $raw, [
        'source' => 'agent',
        'post_id' => $request->get_param( 'post_id' ),
        'viewport' => $request->get_param( 'viewport' ),
    ] );
    return $save_inline ? wpae_save_vision_report( $report ) : $report;
}

function wpae_validate_vision_report_scope( array $report, int $post_id ) {
    $report_post_id = absint( $report['post_id'] ?? 0 );
    if ( $post_id > 0 && $report_post_id > 0 && $report_post_id !== $post_id ) {
        return new WP_Error( 'wpae_vision_report_post_mismatch', 'AI Vision report belongs to a different WordPress post.', [
            'report_post_id' => $report_post_id,
            'requested_post_id' => $post_id,
        ] );
    }
    return true;
}

function wpae_vision_prepare_image( WP_REST_Request $request ) {
    $media_id = absint( $request->get_param( 'media_id' ) );
    $encoded = trim( (string) $request->get_param( 'image_base64' ) );
    $mime_type = strtolower( sanitize_mime_type( (string) $request->get_param( 'mime_type' ) ) );

    if ( $media_id > 0 && $encoded !== '' ) {
        return new WP_Error( 'wpae_vision_multiple_images', 'Send either media_id or image_base64, not both.' );
    }

    if ( $media_id > 0 ) {
        if ( get_post_type( $media_id ) !== 'attachment' ) {
            return new WP_Error( 'wpae_vision_invalid_media', 'media_id must reference an existing WordPress image attachment.' );
        }
        $mime_type = strtolower( (string) get_post_mime_type( $media_id ) );
        $path = get_attached_file( $media_id );
        if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ], true ) || ! is_string( $path ) || ! is_readable( $path ) ) {
            return new WP_Error( 'wpae_vision_invalid_media', 'The media attachment must be a readable JPEG, PNG, WebP, or GIF.' );
        }
        $bytes = file_get_contents( $path, false, null, 0, WPAE_VISION_MAX_IMAGE_BYTES + 1 );
        if ( ! is_string( $bytes ) || strlen( $bytes ) > WPAE_VISION_MAX_IMAGE_BYTES ) {
            return new WP_Error( 'wpae_vision_image_too_large', 'The image must be no larger than 4 MB.' );
        }
        return [ 'bytes' => $bytes, 'mime_type' => $mime_type, 'source' => 'media_id' ];
    }

    if ( $encoded === '' ) {
        return new WP_Error( 'wpae_vision_image_required', 'media_id or image_base64 is required.' );
    }

    if ( preg_match( '#^data:(image/(?:jpeg|png|webp|gif));base64,(.*)$#is', $encoded, $matches ) ) {
        $mime_type = strtolower( $matches[1] );
        $encoded = $matches[2];
    }
    $allowed_mimes = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
    if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
        return new WP_Error( 'wpae_vision_invalid_mime', 'mime_type must be image/jpeg, image/png, image/webp, or image/gif.' );
    }
    $encoded = preg_replace( '/\s+/', '', $encoded );
    if ( ! is_string( $encoded ) || strlen( $encoded ) > 6000000 ) {
        return new WP_Error( 'wpae_vision_image_too_large', 'The base64 image payload is too large.' );
    }
    $bytes = base64_decode( $encoded, true );
    if ( ! is_string( $bytes ) || $bytes === '' || strlen( $bytes ) > WPAE_VISION_MAX_IMAGE_BYTES ) {
        return new WP_Error( 'wpae_vision_invalid_image', 'image_base64 is invalid or larger than 4 MB.' );
    }
    if ( function_exists( 'getimagesizefromstring' ) && @getimagesizefromstring( $bytes ) === false ) {
        return new WP_Error( 'wpae_vision_invalid_image', 'The decoded payload is not a valid image.' );
    }
    return [ 'bytes' => $bytes, 'mime_type' => $mime_type, 'source' => 'inline' ];
}

function wpae_vision_schema( bool $strict = true ): array {
    $finding_schema = [
        'type' => 'object',
        'properties' => [
            'severity' => [ 'type' => 'string', 'enum' => [ 'critical', 'major', 'minor', 'info' ] ],
            'category' => [ 'type' => 'string' ],
            'message' => [ 'type' => 'string' ],
            'fix' => [ 'type' => 'string' ],
            'viewport' => [ 'type' => 'string' ],
        ],
        'required' => [ 'severity', 'category', 'message', 'fix', 'viewport' ],
    ];
    if ( $strict ) {
        $finding_schema['additionalProperties'] = false;
    }

    $schema = [
        'type' => 'object',
        'properties' => [
            'vision_score' => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 100 ],
            'confidence' => [ 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ],
            'summary' => [ 'type' => 'string' ],
            'findings' => [ 'type' => 'array', 'items' => $finding_schema ],
            'must_fix' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
            'strengths' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
        ],
        'required' => [ 'vision_score', 'confidence', 'summary', 'findings', 'must_fix', 'strengths' ],
    ];
    if ( $strict ) {
        $schema['additionalProperties'] = false;
    }

    return $schema;
}

function wpae_vision_uppercase_schema( array $schema ): array {
    foreach ( $schema as $key => $value ) {
        if ( $key === 'type' && is_string( $value ) ) {
            $schema[ $key ] = strtoupper( $value );
        } elseif ( is_array( $value ) ) {
            $schema[ $key ] = wpae_vision_is_list( $value )
                ? array_map( static fn( $item ) => is_array( $item ) ? wpae_vision_uppercase_schema( $item ) : $item, $value )
                : wpae_vision_uppercase_schema( $value );
        }
    }
    return $schema;
}

function wpae_vision_is_list( array $value ): bool {
    $expected = 0;
    foreach ( array_keys( $value ) as $key ) {
        if ( $key !== $expected++ ) {
            return false;
        }
    }
    return true;
}

function wpae_vision_prompt( WP_REST_Request $request ): string {
    $viewport = wpae_vision_trim_text( $request->get_param( 'viewport' ), 40 ) ?: 'unknown';
    $brief = wpae_vision_trim_text( $request->get_param( 'brief' ), 1200 );
    $context = $request->get_param( 'context' );
    $context_text = is_array( $context ) ? wpae_vision_trim_text( wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 2500 ) : '';

    return "Review this WordPress/Elementor page screenshot as a senior UI/UX and accessibility reviewer. Viewport: {$viewport}.\n"
        . "Check hierarchy, spacing, alignment, contrast, responsive overflow, CTA visibility, density, legibility, and whether the result looks intentional rather than generic. Do not infer hidden DOM facts from the screenshot.\n"
        . ( $brief !== '' ? "Project brief: {$brief}\n" : '' )
        . ( $context_text !== '' ? "Additional non-secret context: {$context_text}\n" : '' )
        . "Return ONLY one JSON object matching the requested schema. Use critical only for a defect that makes the page unusable or materially broken; major for an important visible defect; minor for polish; info for a strength or observation. Every finding needs a concrete fix. Do not include markdown fences.";
}

function wpae_vision_provider_request( string $provider, string $model, string $api_key, array $image, string $prompt ) {
    $image64 = base64_encode( $image['bytes'] );
    $schema = wpae_vision_schema();
    $args = [
        'timeout' => 45,
        'redirection' => 2,
        'limit_response_size' => WPAE_VISION_MAX_RESPONSE_BYTES,
        'headers' => [ 'Content-Type' => 'application/json' ],
    ];

    if ( $provider === 'gemini' ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
        $args['headers']['x-goog-api-key'] = $api_key;
        $args['body'] = wp_json_encode( [
            'contents' => [ [ 'parts' => [
                [ 'inlineData' => [ 'mimeType' => $image['mime_type'], 'data' => $image64 ] ],
                [ 'text' => $prompt ],
            ] ] ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema' => wpae_vision_uppercase_schema( wpae_vision_schema( false ) ),
            ],
        ] );
    } elseif ( $provider === 'openai' ) {
        $url = 'https://api.openai.com/v1/chat/completions';
        $args['headers']['Authorization'] = 'Bearer ' . $api_key;
        $args['body'] = wp_json_encode( [
            'model' => $model,
            'temperature' => 0.1,
            'max_tokens' => 1400,
            'messages' => [ [ 'role' => 'user', 'content' => [
                [ 'type' => 'text', 'text' => $prompt ],
                [ 'type' => 'image_url', 'image_url' => [ 'url' => 'data:' . $image['mime_type'] . ';base64,' . $image64, 'detail' => 'high' ] ],
            ] ] ],
            'response_format' => [ 'type' => 'json_schema', 'json_schema' => [ 'name' => 'vision_review', 'strict' => true, 'schema' => $schema ] ],
        ] );
    } else {
        $url = 'https://api.anthropic.com/v1/messages';
        $args['headers']['x-api-key'] = $api_key;
        $args['headers']['anthropic-version'] = '2023-06-01';
        $args['body'] = wp_json_encode( [
            'model' => $model,
            'max_tokens' => 1400,
            'temperature' => 0.1,
            'system' => 'Return only valid JSON matching the requested schema. No markdown fences.',
            'messages' => [ [ 'role' => 'user', 'content' => [
                [ 'type' => 'image', 'source' => [ 'type' => 'base64', 'media_type' => $image['mime_type'], 'data' => $image64 ] ],
                [ 'type' => 'text', 'text' => $prompt ],
            ] ] ],
        ] );
    }

    if ( ! is_string( $args['body'] ) ) {
        return new WP_Error( 'wpae_vision_encode_failed', 'The AI Vision request could not be encoded.' );
    }
    $response = wp_safe_remote_post( $url, $args );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'wpae_vision_provider_request_failed', 'AI Vision provider request failed.', [ 'provider' => $provider, 'message' => $response->get_error_message() ] );
    }
    $status = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );
    if ( strlen( $body ) > WPAE_VISION_MAX_RESPONSE_BYTES ) {
        return new WP_Error( 'wpae_vision_provider_response_too_large', 'AI Vision provider response exceeded the configured size limit.', [ 'provider' => $provider, 'http_status' => $status ] );
    }
    $decoded = json_decode( $body, true );
    if ( $status < 200 || $status >= 300 ) {
        $message = is_array( $decoded ) ? ( $decoded['error']['message'] ?? $decoded['message'] ?? '' ) : '';
        return new WP_Error( 'wpae_vision_provider_http_error', 'AI Vision provider returned an error.', [
            'provider' => $provider,
            'http_status' => $status,
            'provider_message' => wpae_vision_trim_text( $message ?: 'No provider error message returned.', 500 ),
        ] );
    }
    if ( ! is_array( $decoded ) ) {
        return new WP_Error( 'wpae_vision_provider_invalid_json', 'AI Vision provider returned invalid JSON.', [ 'provider' => $provider, 'http_status' => $status ] );
    }
    return [ 'provider' => $provider, 'model' => $model, 'response' => $decoded ];
}

function wpae_vision_provider_text( string $provider, array $response ): string {
    if ( $provider === 'gemini' ) {
        return (string) ( $response['candidates'][0]['content']['parts'][0]['text'] ?? '' );
    }
    if ( $provider === 'openai' ) {
        $content = $response['choices'][0]['message']['content'] ?? '';
        if ( is_array( $content ) ) {
            return implode( "\n", array_map( static fn( $part ) => (string) ( $part['text'] ?? '' ), $content ) );
        }
        return (string) $content;
    }
    foreach ( (array) ( $response['content'] ?? [] ) as $part ) {
        if ( is_array( $part ) && ( $part['type'] ?? '' ) === 'text' ) {
            return (string) ( $part['text'] ?? '' );
        }
    }
    return '';
}

function wpae_vision_decode_report_text( string $text ) {
    $text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text ) );
    $decoded = json_decode( $text, true );
    if ( is_array( $decoded ) ) {
        return $decoded;
    }
    $start = strpos( $text, '{' );
    $end = strrpos( $text, '}' );
    if ( $start !== false && $end !== false && $end > $start ) {
        $decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
    }
    return is_array( $decoded ) ? $decoded : new WP_Error( 'wpae_vision_invalid_report', 'AI Vision returned no valid report JSON.' );
}

function wpae_vision_analyze( WP_REST_Request $request ): WP_REST_Response {
    $image = wpae_vision_prepare_image( $request );
    if ( is_wp_error( $image ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $image->get_error_message(), 'code' => $image->get_error_code(), 'details' => $image->get_error_data() ], 400 );
    }
    $settings = wpae_get_vision_runtime_settings();
    if ( is_wp_error( $settings ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $settings->get_error_message(), 'code' => $settings->get_error_code() ], 503 );
    }
    $rate_limit = wpae_vision_consume_provider_slot();
    if ( is_wp_error( $rate_limit ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $rate_limit->get_error_message(), 'code' => $rate_limit->get_error_code(), 'details' => $rate_limit->get_error_data() ], 429 );
    }
    $provider_result = wpae_vision_provider_request( $settings['provider'], $settings['model'], $settings['api_key'], $image, wpae_vision_prompt( $request ) );
    if ( is_wp_error( $provider_result ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $provider_result->get_error_message(), 'code' => $provider_result->get_error_code(), 'details' => $provider_result->get_error_data() ], 502 );
    }
    $raw_report = wpae_vision_decode_report_text( wpae_vision_provider_text( $provider_result['provider'], $provider_result['response'] ) );
    if ( is_wp_error( $raw_report ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $raw_report->get_error_message(), 'code' => $raw_report->get_error_code() ], 502 );
    }
    $report_errors = wpae_validate_vision_provider_report( $raw_report );
    if ( ! empty( $report_errors ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'AI Vision provider returned a malformed report.', 'code' => 'wpae_vision_provider_invalid_report', 'details' => [ 'errors' => $report_errors ] ], 502 );
    }
    $report = wpae_save_vision_report( wpae_vision_normalize_report( $raw_report, [
        'source' => 'provider',
        'provider' => $settings['provider'],
        'model' => $settings['model'],
        'post_id' => $request->get_param( 'post_id' ),
        'viewport' => $request->get_param( 'viewport' ),
    ] ) );
    return new WP_REST_Response( [ 'ok' => true, 'report' => $report, 'storage' => 'wp_options_only', 'image_stored' => false ], 200 );
}

function wpae_vision_report( WP_REST_Request $request ): WP_REST_Response {
    $report = wpae_vision_report_from_request( $request, true );
    if ( is_wp_error( $report ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $report->get_error_message(), 'code' => $report->get_error_code(), 'details' => $report->get_error_data() ], 422 );
    }
    if ( ! is_array( $report ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'vision_report is required.',
            'code' => 'wpae_vision_report_required',
            'details' => [ 'expected' => 'vision_report object or vision_report_id' ],
        ], 400 );
    }
    return new WP_REST_Response( [ 'ok' => true, 'report' => $report, 'storage' => 'wp_options_only', 'image_stored' => false ], 200 );
}

function wpae_vision_page_review( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $elementor_data = $post_id > 0 ? wpae_get_elementor_data_for_post( $post_id ) : wpae_get_elementor_data_from_request( $request );
    if ( is_wp_error( $elementor_data ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $elementor_data->get_error_message(),
            'code' => $elementor_data->get_error_code(),
            'details' => $elementor_data->get_error_data(),
        ], 422 );
    }

    $report = wpae_vision_report_from_request( $request, true );
    if ( $report === null ) {
        $analysis = wpae_vision_analyze( $request );
        $data = $analysis->get_data();
        if ( empty( $data['ok'] ) ) {
            return $analysis;
        }
        $report = $data['report'] ?? null;
    }
    if ( is_wp_error( $report ) || ! is_array( $report ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => is_wp_error( $report ) ? $report->get_error_message() : 'A valid AI Vision report is required.',
            'code' => is_wp_error( $report ) ? $report->get_error_code() : 'wpae_invalid_vision_report',
            'details' => is_wp_error( $report ) ? $report->get_error_data() : null,
        ], 422 );
    }

    $report_scope = wpae_validate_vision_report_scope( $report, $post_id );
    if ( is_wp_error( $report_scope ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => $report_scope->get_error_message(), 'code' => $report_scope->get_error_code(), 'details' => $report_scope->get_error_data() ], 422 );
    }

    $review = wpae_build_elementor_design_review( $elementor_data, [
        'source' => $post_id > 0 ? 'post_meta_with_vision' : 'request_with_vision',
        'post_id' => $post_id ?: null,
        'iteration' => $request->get_param( 'iteration' ),
        'vision_report' => $report,
    ] );
    return new WP_REST_Response( [
        'ok' => $review['state'] !== 'revise',
        'vision_report_id' => $report['report_id'] ?? null,
        'vision' => $report,
        'design_review' => $review,
        'next_safe_step' => $review['next_safe_step'],
    ], $review['state'] === 'revise' ? 422 : 200 );
}

function wpae_vision_report_for_transaction( WP_REST_Request $request ) {
    return wpae_vision_report_from_request( $request, false );
}

function wpae_vision_editor_review( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $snapshot_id = sanitize_text_field( (string) $request->get_param( 'rollback_snapshot_id' ) );

    if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Нет разрешения на проверку этой страницы.',
            'code' => 'wpae_vision_editor_post_forbidden',
        ], 403 );
    }
    if ( ! wpae_capability_enabled( 'ai_vision' ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'AI Vision отключен владельцем сайта.',
            'code' => 'wpae_vision_disabled',
        ], 403 );
    }

    $capture_error = wpae_vision_trim_text( $request->get_param( 'vision_capture_error' ), 500 );
    if ( $capture_error !== '' ) {
        $rollback = $snapshot_id !== '' ? wpae_restore_rollback_snapshot_by_id( $snapshot_id, true ) : [ 'ok' => false, 'error' => 'Rollback snapshot is missing.' ];
        return new WP_REST_Response( [
            'ok' => false,
            'rolled_back' => ! empty( $rollback['ok'] ),
            'error' => 'Не удалось снять screenshot сохраненного preview для AI Vision.',
            'code' => 'wpae_vision_capture_failed',
            'details' => [ 'capture_error' => $capture_error, 'rollback' => $rollback ],
        ], 422 );
    }

    $analysis = wpae_vision_analyze( $request );
    $analysis_data = $analysis->get_data();
    if ( empty( $analysis_data['ok'] ) || empty( $analysis_data['report'] ) ) {
        $analysis_details = is_array( $analysis_data['details'] ?? null ) ? $analysis_data['details'] : [];
        $rollback = $snapshot_id !== '' ? wpae_restore_rollback_snapshot_by_id( $snapshot_id, true ) : [ 'ok' => false, 'error' => 'Rollback snapshot is missing.' ];
        return new WP_REST_Response( [
            'ok' => false,
            'rolled_back' => ! empty( $rollback['ok'] ),
            'error' => 'AI Vision не смог проверить сохраненный preview.',
            'code' => 'wpae_vision_review_failed',
            'details' => [
                'analysis' => $analysis_data,
                'analysis_code' => sanitize_key( (string) ( $analysis_data['code'] ?? '' ) ),
                'analysis_error' => sanitize_text_field( (string) ( $analysis_data['error'] ?? '' ) ),
                'provider' => sanitize_key( (string) ( $analysis_details['provider'] ?? '' ) ),
                'provider_http_status' => absint( $analysis_details['http_status'] ?? $analysis_details['provider_status'] ?? 0 ),
                'provider_message' => sanitize_text_field( (string) ( $analysis_details['provider_message'] ?? $analysis_details['message'] ?? '' ) ),
                'rollback' => $rollback,
            ],
        ], 502 );
    }

    $report = (array) $analysis_data['report'];
    $gate = wpae_evaluate_vision_report( $report );
    $vision_score = absint( $report['vision_score'] ?? 0 );
    $quality_floor = 75;
    $quality_failed = ! empty( $gate['major_count'] );
    return new WP_REST_Response( [
        'ok' => true,
        'rolled_back' => false,
        'report' => $report,
        'gate' => array_merge( $gate, [
            'quality_floor' => $quality_floor,
            'score_below_floor' => $vision_score < $quality_floor,
            'quality_failed' => false,
            'blocking_advisory' => ! empty( $gate['blocking'] ),
            'quality_warning' => $quality_failed,
        ] ),
    ], 200 );
}

function wpae_get_vision_status(): array {
    $settings = wpae_get_vision_settings();
    return [
        'enabled' => function_exists( 'wpae_capability_enabled' ) && wpae_capability_enabled( 'ai_vision' ),
        'configured' => ! empty( $settings['has_api_key'] ),
        'provider' => $settings['provider_label'],
        'model' => $settings['model'],
        'report_count' => count( (array) get_option( WPAE_VISION_REPORTS_OPTION, [] ) ),
    ];
}

function wpae_get_vision_guide(): array {
    return [
        'optional' => true,
        'capability' => 'ai_vision',
        'enabled' => function_exists( 'wpae_capability_enabled' ) && wpae_capability_enabled( 'ai_vision' ),
        'configured' => wpae_get_vision_status()['configured'],
        'endpoints' => [
            'analyze' => 'POST /wp-json/ai-executor/v1/vision/analyze',
            'report' => 'POST /wp-json/ai-executor/v1/vision/report',
            'page_review' => 'POST /wp-json/ai-executor/v1/vision/page-review',
            'editor_review' => 'POST /wp-json/ai-executor/v1/llm/vision-review',
        ],
        'workflow' => [
            '1. Render the public page or candidate screenshot in the agent environment at desktop and mobile widths.',
            '2. Use /vision/analyze with image_base64 or an existing WordPress media_id, or send an external result to /vision/report.',
            '3. Use /vision/page-review to combine the report with the deterministic Elementor Design Review Gate.',
            '4. Fix findings through native Elementor settings first; keep WebGL/Three.js/GSAP/canvas zones protected.',
            '5. For risky writes, pass transaction_vision_review=true with a same-post provider report from /vision/analyze; critical findings trigger atomic rollback.',
        ],
        'provider_rate_limit' => [ 'calls' => WPAE_VISION_PROVIDER_CALL_LIMIT, 'window_seconds' => WPAE_VISION_PROVIDER_CALL_WINDOW, 'scope' => 'site-wide' ],
        'report_contract' => [
            'fields' => [ 'vision_score' => '0..100', 'findings' => 'array', 'confidence' => '0..1', 'must_fix' => 'array' ],
            'severity' => [ 'critical' => 'Blocks transaction and triggers rollback when requested.', 'major' => 'Important correction; advisory unless the deterministic review also blocks.', 'minor' => 'Polish item.', 'info' => 'Observation or strength.' ],
        ],
        'privacy' => 'Images are not stored by the plugin. Provider keys are encrypted in wp_options. Only normalized reports are retained in wp_options; raw provider responses and image base64 are not logged or stored.',
        'limitations' => 'AI Vision is additional visual evidence, not proof of DOM, computed CSS, keyboard behavior, or animation correctness. External reports remain advisory; only same-post reports created by /vision/analyze may satisfy transaction_vision_review. Deterministic audits and public browser verification remain required.',
        'editor_chat_workflow' => 'The floating Elementor chat reviews the refreshed preview after a successful write in advisory mode. Screenshot findings do not roll back the editor write; strict critical rollback is reserved for transaction_vision_review.',
    ];
}
