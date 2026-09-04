<?php

/**
 * WP AI Executor LLM transport layer: provider settings storage, request
 * preparation, transport, response diagnostics, and provider-error helpers.
 * Split from llm.php so the generation pipeline stays reviewable separately
 * from provider plumbing (optimization #5, v02.11.45).
 */

defined( 'ABSPATH' ) || exit;

const WPAE_LLM_SETTINGS_OPTION = 'wp_ai_executor_llm_settings';
const WPAE_LLM_RATE_LIMIT_OPTION = 'wp_ai_executor_llm_rate_window';
const WPAE_LLM_CALL_LIMIT = 30;
const WPAE_LLM_CALL_WINDOW = 600;
const WPAE_LLM_MAX_MESSAGE_LENGTH = 4000;
const WPAE_LLM_MAX_HISTORY_ITEMS = 12;
const WPAE_LLM_MAX_RESPONSE_BYTES = 262144;

function wpae_llm_provider_options(): array {
    return [
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4.1-mini',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openrouter/free',
        ],
        'gemini' => [
            'label' => 'Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model' => 'gemini-3.7-flash',
        ],
        'custom' => [
            'label' => 'Другой OpenAI-compatible провайдер',
            'base_url' => '',
            'model' => '',
        ],
    ];
}

function wpae_llm_provider_model_options( string $provider ): array {
    if ( $provider !== 'gemini' ) {
        return [];
    }

    return [
        'gemini-3.7-flash' => 'Gemini 3.7 Flash - агентские задачи',
        'gemini-3.6-flash' => 'Gemini 3.6 Flash - скорость и качество',
        'gemini-3.5-flash' => 'Gemini 3.5 Flash - универсальный режим',
        'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite - экономичный режим',
        'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview - сложные задачи',
        'gemini-2.5-flash' => 'Gemini 2.5 Flash - reasoning и скорость',
        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite - минимальная стоимость',
    ];
}

function wpae_llm_get_stored_settings(): array {
    $stored = get_option( WPAE_LLM_SETTINGS_OPTION, [] );
    return is_array( $stored ) ? $stored : [];
}

function wpae_llm_get_settings(): array {
    $providers = wpae_llm_provider_options();
    $stored = wpae_llm_get_stored_settings();
    $provider = sanitize_key( (string) ( $stored['provider'] ?? 'openai' ) );
    if ( ! isset( $providers[ $provider ] ) ) {
        $provider = 'openai';
    }

    $base_url = $provider === 'custom'
        ? esc_url_raw( (string) ( $stored['base_url'] ?? '' ) )
        : $providers[ $provider ]['base_url'];
    $model = sanitize_text_field( (string) ( $stored['model'] ?? $providers[ $provider ]['model'] ) );
    if ( $base_url === '' && $provider !== 'custom' ) {
        $base_url = $providers[ $provider ]['base_url'];
    }
    if ( $model === '' ) {
        $model = $providers[ $provider ]['model'];
    }
    if ( $provider === 'gemini' && ! isset( wpae_llm_provider_model_options( 'gemini' )[ $model ] ) ) {
        $model = $providers[ $provider ]['model'];
    }

    $api_key = wpae_vision_decrypt_api_key( (string) ( $stored['api_key_encrypted'] ?? '' ) );
    return [
        'provider' => $provider,
        'provider_label' => $providers[ $provider ]['label'],
        'base_url' => $base_url,
        'model' => $model,
        'has_api_key' => $api_key !== '',
        'api_key_hint' => $api_key !== '' ? 'Ключ сохранен' : 'Ключ не задан',
        'updated_at' => sanitize_text_field( (string) ( $stored['updated_at'] ?? '' ) ),
    ];
}

function wpae_llm_get_runtime_settings() {
    $settings = wpae_llm_get_settings();
    $stored = wpae_llm_get_stored_settings();
    $api_key = wpae_vision_decrypt_api_key( (string) ( $stored['api_key_encrypted'] ?? '' ) );
    if ( $api_key === '' ) {
        return new WP_Error( 'wpae_llm_not_configured', 'LLM-провайдер не настроен. Добавьте зашифрованный API-ключ в настройках плагина.' );
    }
    if ( $settings['base_url'] === '' ) {
        return new WP_Error( 'wpae_llm_base_url_required', 'Для custom-провайдера укажите HTTPS base URL.' );
    }

    $settings['api_key'] = $api_key;
    return $settings;
}

function wpae_llm_validate_base_url( string $base_url ) {
    $parts = wp_parse_url( $base_url );
    if ( ! is_array( $parts ) || empty( $parts['host'] ) || ( $parts['scheme'] ?? '' ) !== 'https' || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
        return new WP_Error( 'wpae_llm_invalid_base_url', 'Base URL должен быть HTTPS-адресом без логина, пароля, query и fragment.' );
    }
    return true;
}

function wpae_update_llm_settings( array $input ) {
    $providers = wpae_llm_provider_options();
    $provider = sanitize_key( (string) ( $input['provider'] ?? 'openai' ) );
    if ( ! isset( $providers[ $provider ] ) ) {
        return new WP_Error( 'wpae_llm_invalid_provider', 'Неизвестный LLM-провайдер.' );
    }

    $base_url = $provider === 'custom'
        ? trim( (string) ( $input['base_url'] ?? '' ) )
        : $providers[ $provider ]['base_url'];
    $base_url = untrailingslashit( esc_url_raw( $base_url ) );
    $valid_url = wpae_llm_validate_base_url( $base_url );
    if ( is_wp_error( $valid_url ) ) {
        return $valid_url;
    }

    $model = sanitize_text_field( (string) ( $input['model'] ?? '' ) );
    $model = substr( $model !== '' ? $model : $providers[ $provider ]['model'], 0, 120 );
    if ( $provider === 'gemini' && ! isset( wpae_llm_provider_model_options( 'gemini' )[ $model ] ) ) {
        $model = $providers[ $provider ]['model'];
    }
    $stored = wpae_llm_get_stored_settings();
    $api_key = trim( (string) ( $input['api_key'] ?? '' ) );
    if ( ! empty( $input['clear_api_key'] ) ) {
        $stored['api_key_encrypted'] = '';
    } elseif ( $api_key !== '' ) {
        $encrypted = wpae_vision_encrypt_api_key( $api_key );
        if ( is_wp_error( $encrypted ) ) {
            return new WP_Error( 'wpae_llm_crypto_failed', $encrypted->get_error_message() );
        }
        $stored['api_key_encrypted'] = $encrypted;
    }

    $stored['provider'] = $provider;
    $stored['base_url'] = $base_url;
    $stored['model'] = $model;
    // Optional opt-in fallback model: used once when the primary model's pool
    // answers with a rate-limit refusal, so a crowded shared free pool cannot
    // block a whole test or content session.
    $stored['fallback_model'] = substr( sanitize_text_field( (string) ( $input['fallback_model'] ?? '' ) ), 0, 120 );
    $stored['updated_at'] = gmdate( 'c' );
    update_option( WPAE_LLM_SETTINGS_OPTION, $stored, false );
    return true;
}

function wpae_llm_rate_limit_check(): bool {
    $now = time();
    $events = get_option( WPAE_LLM_RATE_LIMIT_OPTION, [] );
    $events = is_array( $events ) ? array_values( array_filter( $events, static fn( $time ): bool => is_numeric( $time ) && ( $now - (int) $time ) < WPAE_LLM_CALL_WINDOW ) ) : [];
    if ( count( $events ) >= WPAE_LLM_CALL_LIMIT ) {
        return false;
    }
    $events[] = $now;
    update_option( WPAE_LLM_RATE_LIMIT_OPTION, array_slice( $events, -WPAE_LLM_CALL_LIMIT ), false );
    return true;
}

function wpae_llm_clean_history( $history ): array {
    $clean = [];
    foreach ( array_slice( is_array( $history ) ? $history : [], -WPAE_LLM_MAX_HISTORY_ITEMS ) as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $role = sanitize_key( (string) ( $item['role'] ?? '' ) );
        if ( ! in_array( $role, [ 'user', 'assistant' ], true ) ) {
            continue;
        }
        $content = sanitize_textarea_field( (string) ( $item['content'] ?? '' ) );
        if ( $content !== '' ) {
            $clean[] = [ 'role' => $role, 'content' => substr( $content, 0, WPAE_LLM_MAX_MESSAGE_LENGTH ) ];
        }
    }
    return $clean;
}

function wpae_llm_extract_response_text( $body ): string {
    $choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : [];
    $message = is_array( $choice['message'] ?? null ) ? $choice['message'] : [];
    $content = $message['content'] ?? ( $choice['text'] ?? ( $body['output_text'] ?? '' ) );
    if ( is_string( $content ) ) {
        return trim( $content );
    }
    if ( ! is_array( $content ) ) {
        return is_string( $message['refusal'] ?? null ) ? trim( $message['refusal'] ) : '';
    }
    $parts = [];
    foreach ( $content as $part ) {
        if ( is_string( $part ) ) {
            $parts[] = $part;
        } elseif ( is_array( $part ) && is_string( $part['text'] ?? null ) ) {
            $parts[] = $part['text'];
        }
    }
    return trim( implode( "\n", $parts ) );
}

function wpae_llm_fallback_model( string $current_model ): string {
    $fallback_model = trim( (string) ( wpae_llm_get_stored_settings()['fallback_model'] ?? '' ) );
    return $fallback_model !== '' && $fallback_model !== $current_model ? $fallback_model : '';
}

function wpae_llm_provider_is_rate_limited( $body ): bool {
    if ( ! is_array( $body ) ) {
        return false;
    }
    if ( (int) ( $body['error']['code'] ?? 0 ) === 429 ) {
        return true;
    }
    $choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : [];
    $haystack = strtolower( (string) ( $body['error']['message'] ?? '' ) . ' ' . (string) ( $body['error']['metadata']['raw'] ?? '' ) . ' ' . (string) ( $choice['error']['message'] ?? '' ) );

    return strpos( $haystack, 'rate-limited' ) !== false || strpos( $haystack, 'rate limit' ) !== false;
}

function wpae_llm_provider_error_message( $body ): string {
    $choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : [];
    $message = is_array( $choice['error'] ?? null ) ? ( $choice['error']['message'] ?? '' ) : '';
    $message = $message ?: ( $body['error']['message'] ?? $body['message'] ?? '' );
    $message = is_scalar( $message ) ? sanitize_text_field( (string) $message ) : '';

    // OpenRouter hides the upstream reason inside error.metadata; surface a
    // bounded sanitized excerpt so provider failures are diagnosable in the
    // editor chat without exposing credentials or raw payloads.
    $metadata = is_array( $body['error']['metadata'] ?? null ) ? $body['error']['metadata'] : [];
    $raw = trim( (string) ( $metadata['raw'] ?? '' ) );
    if ( $raw !== '' ) {
        $provider_name = sanitize_text_field( (string) ( $metadata['provider_name'] ?? '' ) );
        $excerpt = substr( sanitize_text_field( $raw ), 0, 300 );
        $message = trim( $message . ' [' . ( $provider_name !== '' ? $provider_name . ': ' : '' ) . $excerpt . ']' );
    }

    return $message;
}

function wpae_llm_response_diagnostics( $body ): array {
    $choices = is_array( $body['choices'] ?? null ) ? $body['choices'] : [];
    $choice = is_array( $choices[0] ?? null ) ? $choices[0] : [];
    $message = is_array( $choice['message'] ?? null ) ? $choice['message'] : [];
    $content = $message['content'] ?? ( $choice['text'] ?? ( $body['output_text'] ?? null ) );
    return [
        'choices_count' => count( $choices ),
        'finish_reason' => sanitize_text_field( (string) ( $choice['finish_reason'] ?? '' ) ),
        'content_type' => is_array( $content ) ? 'array' : gettype( $content ),
        'has_reasoning' => ! empty( $message['reasoning'] ?? $choice['reasoning'] ?? false ),
        'has_refusal' => is_string( $message['refusal'] ?? null ) && trim( $message['refusal'] ) !== '',
        'provider_error_code' => sanitize_text_field( (string) ( $body['error']['code'] ?? $choice['error']['code'] ?? '' ) ),
        'provider_message' => wpae_llm_provider_error_message( $body ),
    ];
}

function wpae_llm_prepare_provider_request_body( array $request_body, bool $action_request, string $provider ): array {
    if ( $provider === 'openrouter' ) {
        // OpenRouter's schema uses max_tokens; the OpenAI-only max_completion_tokens
        // field is outside that schema and is ignored or rejected by upstream routes.
        if ( isset( $request_body['max_completion_tokens'] ) ) {
            $request_body['max_tokens'] = (int) $request_body['max_completion_tokens'];
            unset( $request_body['max_completion_tokens'] );
        }
        return $request_body;
    }

    if ( $provider !== 'gemini' ) {
        return $request_body;
    }

    // Gemini's OpenAI-compatible endpoint does not accept OpenAI-only action fields.
    unset( $request_body['max_completion_tokens'] );
    if ( $action_request ) {
        unset( $request_body['response_format'] );
    }

    return $request_body;
}

function wpae_llm_provider_request( string $url, array $remote_args, array $request_body, bool $action_request, string $provider ) {
    try {
        $request_body = wpae_llm_prepare_provider_request_body( $request_body, $action_request, $provider );
        $remote_args['body'] = wp_json_encode( $request_body );
        $response = wp_safe_remote_post( $url, $remote_args );
        if ( ! is_wp_error( $response ) && $action_request && $provider === 'openrouter' ) {
            $initial_status = wp_remote_retrieve_response_code( $response );
            $initial_body = json_decode( wp_remote_retrieve_body( $response ), true );
            $initial_error = wpae_llm_provider_error_message( is_array( $initial_body ) ? $initial_body : [] );
            $initial_diagnostics = wpae_llm_response_diagnostics( is_array( $initial_body ) ? $initial_body : [] );
            $structured_route_rejected = $initial_status >= 400 && ( stripos( $initial_error, 'No endpoints found' ) !== false || stripos( $initial_error, 'requested parameters' ) !== false || stripos( $initial_error, 'Provider returned error' ) !== false );
            $structured_response_failed = $initial_status >= 200 && $initial_status < 300 && ( $initial_diagnostics['finish_reason'] ?? '' ) === 'error';
            if ( $structured_route_rejected || $structured_response_failed ) {
                unset( $request_body['response_format'], $request_body['provider'] );
                $remote_args['body'] = wp_json_encode( $request_body );
                $response = wp_safe_remote_post( $url, $remote_args );
            }
        }
        return $response;
    } catch ( Throwable $error ) {
        error_log( sprintf( '[WP AI Executor] Provider request failed for %s: %s in %s:%d', $provider, $error->getMessage(), $error->getFile(), $error->getLine() ) );
        return new WP_Error( 'wpae_llm_provider_request_failed', $error->getMessage(), [
            'provider' => $provider,
            'exception_type' => get_class( $error ),
        ] );
    }
}
