<?php

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
            'model' => 'openai/gpt-4o-mini',
        ],
        'custom' => [
            'label' => 'Другой OpenAI-compatible провайдер',
            'base_url' => '',
            'model' => '',
        ],
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

    $base_url = esc_url_raw( (string) ( $stored['base_url'] ?? $providers[ $provider ]['base_url'] ) );
    $model = sanitize_text_field( (string) ( $stored['model'] ?? $providers[ $provider ]['model'] ) );
    if ( $base_url === '' && $provider !== 'custom' ) {
        $base_url = $providers[ $provider ]['base_url'];
    }
    if ( $model === '' ) {
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

    $base_url = trim( (string) ( $input['base_url'] ?? $providers[ $provider ]['base_url'] ) );
    if ( $base_url === '' && $provider !== 'custom' ) {
        $base_url = $providers[ $provider ]['base_url'];
    }
    $base_url = untrailingslashit( esc_url_raw( $base_url ) );
    $valid_url = wpae_llm_validate_base_url( $base_url );
    if ( is_wp_error( $valid_url ) ) {
        return $valid_url;
    }

    $model = sanitize_text_field( (string) ( $input['model'] ?? '' ) );
    $model = substr( $model !== '' ? $model : $providers[ $provider ]['model'], 0, 120 );
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
    $content = $body['choices'][0]['message']['content'] ?? '';
    if ( is_string( $content ) ) {
        return trim( $content );
    }
    if ( ! is_array( $content ) ) {
        return '';
    }
    $parts = [];
    foreach ( $content as $part ) {
        if ( is_array( $part ) && ( $part['type'] ?? '' ) === 'text' && is_string( $part['text'] ?? null ) ) {
            $parts[] = $part['text'];
        }
    }
    return trim( implode( "\n", $parts ) );
}

function wpae_llm_chat( WP_REST_Request $request ) {
    if ( ! wpae_llm_rate_limit_check() ) {
        return new WP_Error( 'wpae_llm_rate_limited', 'Лимит LLM-запросов исчерпан. Повторите позже.', [ 'status' => 429, 'window_seconds' => WPAE_LLM_CALL_WINDOW ] );
    }

    $runtime = wpae_llm_get_runtime_settings();
    if ( is_wp_error( $runtime ) ) {
        return $runtime;
    }
    $message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
    if ( $message === '' ) {
        return new WP_Error( 'wpae_llm_message_required', 'Поле message обязательно.', [ 'status' => 400 ] );
    }
    if ( strlen( $message ) > WPAE_LLM_MAX_MESSAGE_LENGTH ) {
        return new WP_Error( 'wpae_llm_message_too_long', 'Сообщение слишком длинное.', [ 'status' => 400, 'max_length' => WPAE_LLM_MAX_MESSAGE_LENGTH ] );
    }

    $messages = [ [
        'role' => 'system',
        'content' => 'Ты помогаешь работать с WordPress и Elementor. Не заявляй, что изменения выполнены, если не получил подтверждение API. Соблюдай native Elementor settings, Flexbox Containers, mobile-first и сохраняй существующие WebGL/GSAP/Three.js enhancement-зоны.',
    ] ];
    foreach ( wpae_llm_clean_history( $request->get_param( 'history' ) ) as $item ) {
        $messages[] = $item;
    }
    $messages[] = [ 'role' => 'user', 'content' => $message ];

    $context = $request->get_param( 'context' );
    if ( is_array( $context ) ) {
        $selected_elements = [];
        foreach ( array_slice( is_array( $context['selected_elements'] ?? null ) ? $context['selected_elements'] : [], 0, 8 ) as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $selected_elements[] = [
                'id' => sanitize_key( substr( (string) ( $element['id'] ?? '' ), 0, 64 ) ),
                'elType' => sanitize_key( substr( (string) ( $element['elType'] ?? '' ), 0, 32 ) ),
                'widgetType' => sanitize_key( substr( (string) ( $element['widgetType'] ?? '' ), 0, 64 ) ),
            ];
        }
        $context = [
            'post_id' => absint( $context['post_id'] ?? 0 ),
            'selected_elements' => $selected_elements,
        ];
        $messages[0]['content'] .= "\nКонтекст редактора: " . wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    $url = untrailingslashit( $runtime['base_url'] ) . '/chat/completions';
    $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $runtime['api_key'] ];
    if ( $runtime['provider'] === 'openrouter' ) {
        $headers['HTTP-Referer'] = home_url( '/' );
        $headers['X-Title'] = get_bloginfo( 'name' );
    }
    $response = wp_safe_remote_post( $url, [
        'timeout' => 45,
        'redirection' => 2,
        'limit_response_size' => WPAE_LLM_MAX_RESPONSE_BYTES,
        'headers' => $headers,
        'body' => wp_json_encode( [
            'model' => $runtime['model'],
            'messages' => $messages,
            'temperature' => 0.2,
            'max_completion_tokens' => 1200,
        ] ),
    ] );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'wpae_llm_provider_request_failed', 'LLM-провайдер недоступен.', [ 'status' => 502, 'details' => $response->get_error_message(), 'provider' => $runtime['provider'] ] );
    }

    $status = wp_remote_retrieve_response_code( $response );
    $raw = wp_remote_retrieve_body( $response );
    $body = json_decode( $raw, true );
    if ( $status < 200 || $status >= 300 ) {
        $provider_error = is_array( $body ) ? ( $body['error']['message'] ?? $body['message'] ?? 'Провайдер вернул ошибку.' ) : 'Провайдер вернул некорректный ответ.';
        if ( ! is_scalar( $provider_error ) ) {
            $provider_error = 'Провайдер вернул ошибку.';
        }
        return new WP_Error( 'wpae_llm_provider_error', 'LLM-провайдер вернул ошибку.', [ 'status' => 502, 'provider_status' => $status, 'provider_message' => sanitize_text_field( (string) $provider_error ), 'provider' => $runtime['provider'] ] );
    }

    $reply = wpae_llm_extract_response_text( is_array( $body ) ? $body : [] );
    if ( $reply === '' ) {
        return new WP_Error( 'wpae_llm_empty_response', 'LLM-провайдер вернул пустой ответ.', [ 'status' => 502, 'provider' => $runtime['provider'] ] );
    }
    return new WP_REST_Response( [ 'ok' => true, 'message' => substr( $reply, 0, 12000 ), 'provider' => $runtime['provider'], 'model' => $runtime['model'] ], 200 );
}

function wpae_llm_chat_permission( WP_REST_Request $request ) {
    if ( wpae_get_request_api_key( $request ) !== '' ) {
        return wpae_auth_with_capability( $request, 'llm_chat' );
    }
    if ( ! wpae_capability_enabled( 'llm_chat' ) || ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'wpae_llm_editor_forbidden', 'LLM-чат доступен только авторизованному редактору при включенном разрешении.', [ 'status' => 403, 'capability' => 'llm_chat' ] );
    }
    $post_id = absint( $request->get_param( 'post_id' ) );
    if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_Error( 'wpae_llm_post_forbidden', 'Нет разрешения на редактирование этой страницы.', [ 'status' => 403, 'post_id' => $post_id ] );
    }
    return true;
}

function wpae_get_llm_guide(): array {
    $settings = wpae_llm_get_settings();
    return [
        'optional' => true,
        'capability' => 'llm_chat',
        'enabled' => function_exists( 'wpae_capability_enabled' ) && wpae_capability_enabled( 'llm_chat' ),
        'configured' => ! empty( $settings['has_api_key'] ) && $settings['base_url'] !== '',
        'endpoint' => 'POST /wp-json/ai-executor/v1/llm/chat',
        'protocol' => 'OpenAI-compatible Chat Completions; supports OpenAI, DeepSeek, OpenRouter, and custom HTTPS-compatible gateways.',
        'request' => [
            'message' => 'Required string, maximum 4000 characters.',
            'history' => 'Optional last 12 user/assistant messages; not stored by the plugin.',
            'context' => 'Optional bounded editor context with post_id and selected element ids/types.',
        ],
        'safety' => 'The chat proxy does not execute model output or mutate Elementor. Page writes still require the existing structured Elementor endpoints, guide token, preflight, and rollback contract.',
        'provider_rate_limit' => [ 'calls' => WPAE_LLM_CALL_LIMIT, 'window_seconds' => WPAE_LLM_CALL_WINDOW, 'scope' => 'site-wide' ],
        'privacy' => 'Provider keys are encrypted in wp_options. Prompts, histories, and raw provider responses are not stored or logged.',
    ];
}
