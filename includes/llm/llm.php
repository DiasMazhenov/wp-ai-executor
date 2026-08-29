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
            'model' => 'openrouter/free',
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
    ];
}

function wpae_llm_is_action_request( string $message ): bool {
    if ( preg_match( '/^\s*(как|что|почему|зачем|объясни|подскажи)\b/ui', $message ) ) {
        return false;
    }
    return (bool) preg_match( '/\b(сделай|создай|добавь|собери|сверстай|измени|поставь|замени|верст|hero|хиро|лендинг)\b/ui', $message );
}

function wpae_llm_is_list( array $value ): bool {
    $index = 0;
    foreach ( array_keys( $value ) as $key ) {
        if ( $key !== $index++ ) {
            return false;
        }
    }
    return true;
}

function wpae_llm_count_widgets( array $elements ): int {
    $count = 0;
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( (string) ( $element['elType'] ?? '' ) === 'widget' ) {
            $count++;
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            $count += wpae_llm_count_widgets( $element['elements'] );
        }
    }
    return $count;
}

function wpae_llm_decode_action( string $reply, int $post_id = 0 ): array {
    $candidate = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $reply ) );
    $decoded = json_decode( $candidate, true );
    if ( ! is_array( $decoded ) ) {
        $start = strpos( $candidate, '{' );
        $end = strrpos( $candidate, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            $decoded = json_decode( substr( $candidate, $start, $end - $start + 1 ), true );
        }
    }
    if ( ! is_array( $decoded ) ) {
        return [
            '_wpae_diagnostics' => [
                'response_type' => 'text',
                'json_decoded' => false,
                'reply_preview' => sanitize_text_field( substr( wp_strip_all_tags( $reply ), 0, 600 ) ),
            ],
        ];
    }
    foreach ( [ 'result', 'data', 'command' ] as $wrapper ) {
        if ( isset( $decoded[ $wrapper ] ) && is_array( $decoded[ $wrapper ] ) ) {
            $decoded = $decoded[ $wrapper ];
            break;
        }
    }
    if ( wpae_llm_is_list( $decoded ) ) {
        $decoded = [ 'action' => 'insert_elements', 'elements' => $decoded ];
    }
    $decoded['_wpae_diagnostics'] = [
        'response_type' => 'json_object',
        'json_decoded' => true,
        'response_keys' => array_values( array_map( 'sanitize_key', array_keys( $decoded ) ) ),
    ];
    if ( ( $decoded['action'] ?? $decoded['type'] ?? '' ) === 'insert_elements' ) {
        $decoded['action'] = 'insert_elements';
    }
    if ( $post_id > 0 && empty( $decoded['post_id'] ) ) {
        $decoded['post_id'] = $post_id;
    }
    return $decoded;
}

function wpae_llm_execute_action( array $action, int $post_id ): array {
    $received_action = sanitize_key( (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' ) );
    $received_post_id = absint( $action['post_id'] ?? 0 );
    $received_elements = $action['elements'] ?? [];
    $steps = [];
    if ( ( $action['action'] ?? '' ) !== 'insert_elements' || $post_id <= 0 || absint( $action['post_id'] ?? 0 ) !== $post_id ) {
        $steps[] = [
            'id' => 'command_validation',
            'status' => 'failed',
            'message' => 'Команда модели не прошла проверку типа или страницы.',
            'details' => [
                'received_action' => $received_action,
                'received_post_id' => $received_post_id,
                'expected_action' => 'insert_elements',
                'expected_post_id' => $post_id,
                'element_count' => is_array( $received_elements ) ? count( $received_elements ) : 0,
            ],
        ];
        return [
            'ok' => false,
            'error' => 'Модель вернула неподдерживаемую Elementor-команду.',
            'received_action' => $received_action,
            'received_post_id' => $received_post_id,
            'expected_action' => 'insert_elements',
            'expected_post_id' => $post_id,
            'model_response' => $action['_wpae_diagnostics'] ?? [],
            'steps' => $steps,
        ];
    }
    if ( ! wpae_capability_enabled( 'elementor_writes' ) ) {
        $steps[] = [ 'id' => 'permissions', 'status' => 'failed', 'message' => 'Запись заблокирована настройками возможностей сайта.', 'details' => [ 'capability' => 'elementor_writes' ] ];
        return [ 'ok' => false, 'error' => 'Разрешение elementor_writes выключено владельцем сайта.', 'capability' => 'elementor_writes', 'steps' => $steps ];
    }
    if ( function_exists( 'current_user_can' ) && is_user_logged_in() && ! current_user_can( 'edit_post', $post_id ) ) {
        $steps[] = [ 'id' => 'permissions', 'status' => 'failed', 'message' => 'Текущий пользователь не может изменить эту страницу.', 'details' => [ 'post_id' => $post_id ] ];
        return [ 'ok' => false, 'error' => 'Нет разрешения на изменение этой страницы.', 'post_id' => $post_id, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'command_validation', 'status' => 'ok', 'message' => 'Команда insert_elements и целевая страница подтверждены.', 'details' => [ 'action' => $received_action, 'post_id' => $post_id ] ];

    $elements = $action['elements'] ?? [];
    if ( ! is_array( $elements ) || empty( $elements ) || count( $elements ) > 12 ) {
        $steps[] = [ 'id' => 'element_count', 'status' => 'failed', 'message' => 'Количество новых Elementor-элементов должно быть от 1 до 12.', 'details' => [ 'element_count' => is_array( $elements ) ? count( $elements ) : 0 ] ];
        return [ 'ok' => false, 'error' => 'Elementor-команда должна содержать от 1 до 12 новых элементов.', 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'element_count', 'status' => 'ok', 'message' => 'Количество новых элементов прошло проверку.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $widget_count = wpae_llm_count_widgets( $elements );
    if ( $widget_count < 1 ) {
        $steps[] = [ 'id' => 'native_widgets', 'status' => 'failed', 'message' => 'Команда не содержит native Elementor widgets, поэтому результат был бы пустым.', 'details' => [ 'widget_count' => 0 ] ];
        return [ 'ok' => false, 'error' => 'Elementor-команда должна содержать хотя бы один native widget, иначе страница останется пустой.', 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'native_widgets', 'status' => 'ok', 'message' => 'В команде найден хотя бы один native Elementor widget.', 'details' => [ 'widget_count' => $widget_count ] ];
    $native_normalized = function_exists( 'wpae_elementor_normalize_data' );
    if ( $native_normalized ) {
        $elements = wpae_elementor_normalize_data( $elements )['data'];
    }
    $steps[] = [ 'id' => 'native_normalize', 'status' => $native_normalized ? 'ok' : 'skipped', 'message' => $native_normalized ? 'Структура виджетов нормализована под native Elementor и Flexbox.' : 'Нормализация Elementor недоступна и пропущена.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $design_mapped = function_exists( 'wpae_apply_design_token_map' );
    if ( $design_mapped ) {
        $elements = wpae_apply_design_token_map( $elements )['data'];
    }
    $steps[] = [ 'id' => 'design_system', 'status' => $design_mapped ? 'ok' : 'skipped', 'message' => $design_mapped ? 'Применены совместимые native-токены активной дизайн-системы.' : 'Маппинг дизайн-системы недоступен и пропущен.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $existing = wpae_get_elementor_data_for_post( $post_id );
    $initial_page = false;
    if ( is_wp_error( $existing ) ) {
        $existing = [];
        $initial_page = true;
    }
    if ( ! empty( $existing ) && function_exists( 'wpae_elementor_normalize_data' ) ) {
        $existing = wpae_elementor_normalize_data( $existing )['data'];
    }
    $steps[] = [ 'id' => 'page_context', 'status' => 'ok', 'message' => $initial_page ? 'Страница пустая: разрешена безопасная инициализация Elementor.' : 'Текущая структура страницы прочитана.', 'details' => [ 'existing_element_count' => count( $existing ) ] ];
    if ( function_exists( 'wpae_rekey_elementor_ids_recursive' ) ) {
        $elements = wpae_rekey_elementor_ids_recursive( $elements, 'llm-' . wp_generate_password( 10, false, false ) );
    }
    $steps[] = [ 'id' => 'element_ids', 'status' => 'ok', 'message' => 'Для новых элементов созданы уникальные Elementor ID.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $position = sanitize_key( (string) ( $action['position'] ?? 'end' ) );
    $next = $position === 'start' ? array_merge( $elements, $existing ) : array_merge( $existing, $elements );
    $request = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/update' );
    $request->set_param( 'post_id', $post_id );
    $request->set_param( 'elementor_data', $next );
    $request->set_param( 'template', 'elementor_canvas' );
    $request->set_param( '_wpae_allow_initial_data', true );
    $request->set_param( 'transaction_visual_regression', true );
    $result = wpae_elementor_update( $request );
    $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
    $status = $result instanceof WP_REST_Response ? $result->get_status() : 500;
    if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['ok'] ) ) {
        $blocking_errors = [];
        foreach ( [ $data['details']['errors'] ?? [], $data['details']['design_system']['errors'] ?? [], $data['preflight']['blocking_errors'] ?? [] ] as $errors ) {
            foreach ( (array) $errors as $error ) {
                if ( is_scalar( $error ) && trim( (string) $error ) !== '' ) {
                    $blocking_errors[] = sanitize_text_field( (string) $error );
                }
            }
        }
        return [
            'ok' => false,
            'error' => 'Elementor update отклонен существующей проверкой.',
            'status' => $status,
            'blocking_errors' => array_values( array_unique( $blocking_errors ) ),
            'details' => is_array( $data ) ? $data : [],
            'steps' => array_merge( $steps, [ [ 'id' => 'elementor_update', 'status' => 'failed', 'message' => 'Elementor update остановлен проверкой.', 'details' => [ 'http_status' => $status, 'blocking_errors' => array_values( array_unique( $blocking_errors ) ) ] ] ] ),
        ];
    }
    $steps[] = [ 'id' => 'elementor_update', 'status' => 'ok', 'message' => 'Изменения сохранены через Elementor update.', 'details' => [ 'http_status' => $status ] ];
    $steps[] = [ 'id' => 'complete', 'status' => 'ok', 'message' => 'Задача выполнена, Elementor подтвердил запись.' ];
    return [
        'ok' => true,
        'action' => 'insert_elements',
        'post_id' => $post_id,
        'inserted_count' => count( $elements ),
        'quality_summary' => $data['quality_summary'] ?? null,
        'rollback_snapshot_id' => $data['rollback_snapshot_id'] ?? null,
        'steps' => $steps,
    ];
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

    $action_request = wpae_llm_is_action_request( $message );
    $system_prompt = 'Ты помогаешь работать с WordPress и Elementor. Не заявляй, что изменения выполнены, если не получил подтверждение API. Соблюдай native Elementor settings, Flexbox Containers, mobile-first и сохраняй существующие WebGL/GSAP/Three.js enhancement-зоны.';
    $guided_context = [];
    if ( $action_request ) {
        $guided_context = [
            'guide_version' => WPAE_GUIDE_VERSION,
            'plugin_version' => WPAE_VERSION,
            'agent_rules' => function_exists( 'wpae_agent_prompt' ) ? wpae_agent_prompt() : '',
            'custom_skills' => function_exists( 'wpae_get_enabled_skills_for_guide' ) ? wpae_get_enabled_skills_for_guide() : [],
            'capabilities' => function_exists( 'wpae_get_capabilities_payload' ) ? wpae_get_capabilities_payload() : [],
        ];
        $system_prompt .= "\nЭто guided-режим WP AI Executor. Перед выполнением обязательно применяй agent_rules, все custom_skills и capabilities из следующего контекста. Правила WP AI Executor имеют приоритет при конфликте:\n" . wp_json_encode( $guided_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }
    if ( $action_request ) {
        $system_prompt .= ' Это запрос на выполнение работы. Не пиши инструкцию и не объясняй ручные клики. Верни только JSON без markdown по схеме: {"action":"insert_elements","post_id":number,"position":"start|end","elements":[Elementor native Flexbox container/widget objects]}. Разрешена только вставка новых элементов с elType=container/widget, точным camelCase widgetType, native settings и elements arrays. Не удаляй и не заменяй существующие элементы.';
        $system_prompt .= "\nАктивная дизайн-система: " . wp_json_encode( wpae_build_project_design_system(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }
    $messages = [ [
        'role' => 'system',
        'content' => $system_prompt,
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
            'max_completion_tokens' => $action_request ? 3000 : 1200,
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
        return new WP_Error( 'wpae_llm_empty_response', 'LLM-провайдер вернул пустой ответ. Проверьте модель и лимит токенов.', [
            'status' => 502,
            'provider' => $runtime['provider'],
            'details' => wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] ),
        ] );
    }
    if ( $action_request ) {
        $post_id = is_array( $context ?? null ) ? absint( $context['post_id'] ?? 0 ) : 0;
        $action = wpae_llm_decode_action( $reply, $post_id );
        $action_diagnostics = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : [];
        $action_diagnostics['decoded_action'] = sanitize_key( (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' ) );
        $action_diagnostics['decoded_post_id'] = absint( $action['post_id'] ?? 0 );
        $action_diagnostics['decoded_element_count'] = is_array( $action['elements'] ?? null ) ? count( $action['elements'] ) : 0;
        $action_steps = [
            [ 'id' => 'guided_context', 'status' => 'ok', 'message' => 'Загружены актуальные guide, skills и capabilities сайта.', 'details' => [ 'guide_version' => WPAE_GUIDE_VERSION, 'custom_skills_count' => count( $guided_context['custom_skills'] ?? [] ), 'elementor_writes' => ! empty( $guided_context['capabilities']['capability_toggles']['elementor_writes'] ) ] ],
            [ 'id' => 'provider_response', 'status' => 'ok', 'message' => 'Ответ LLM-провайдера получен.', 'details' => wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] ) ],
            [
                'id' => 'command_decode',
                'status' => ! empty( $action_diagnostics['json_decoded'] ) ? 'ok' : 'failed',
                'message' => ! empty( $action_diagnostics['json_decoded'] ) ? 'Ответ разобран как Elementor JSON-команда.' : 'Ответ не разобран как Elementor JSON-команда.',
                'details' => $action_diagnostics,
            ],
        ];
        $execution = wpae_llm_execute_action( $action, $post_id );
        $execution['steps'] = array_merge( $action_steps, is_array( $execution['steps'] ?? null ) ? $execution['steps'] : [] );
        if ( empty( $execution['ok'] ) ) {
            return new WP_Error( 'wpae_llm_action_failed', 'LLM не выполнил задачу в Elementor.', [ 'status' => 422, 'details' => $execution ] );
        }
        return new WP_REST_Response( [ 'ok' => true, 'message' => 'Задача выполнена через Elementor. Вставлено элементов: ' . (int) $execution['inserted_count'] . '.', 'action' => $execution['action'], 'write' => $execution, 'steps' => $execution['steps'], 'provider' => $runtime['provider'], 'model' => $runtime['model'] ], 200 );
    }
    return new WP_REST_Response( [
        'ok' => true,
        'message' => substr( $reply, 0, 12000 ),
        'steps' => [
            [ 'id' => 'guided_context', 'status' => 'ok', 'message' => 'Чат использовал актуальные runtime-настройки сайта.', 'details' => [ 'guide_version' => WPAE_GUIDE_VERSION ] ],
            [ 'id' => 'provider_response', 'status' => 'ok', 'message' => 'Ответ LLM-провайдера получен.', 'details' => wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] ) ],
            [ 'id' => 'answer_ready', 'status' => 'ok', 'message' => 'Ответ подготовлен для чата.' ],
        ],
        'provider' => $runtime['provider'],
        'model' => $runtime['model'],
    ], 200 );
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
        'safety' => 'Normal chat is advisory. Explicit action requests may insert only new Elementor elements when elementor_writes is enabled; the plugin validates generated data and routes it through update, preflight, protected-zone, visual-regression, and rollback checks. Delete and replace actions are not supported. If the provider returns tutorial text instead of the required action JSON, the write is rejected.',
        'guided_editor_mode' => 'The floating Elementor editor chat injects the current guide, enabled custom skills, capabilities, and project design system into action requests. It uses the same internal Elementor validation/update pipeline without exposing the site API key to browser JavaScript.',
        'editor_preview_sync' => 'After a successful action write, the floating chat reloads the current Elementor preview so the open editor reflects the saved server state. If the preview API is unavailable, the chat reports that refresh could not be confirmed.',
        'action_content_gate' => 'An explicit action is rejected when its generated element tree contains no native Elementor widget, even if an empty container is structurally valid JSON.',
        'execution_trace' => 'Action and advisory responses include a safe operational steps array for the chat UI and JSON log: provider response, command decoding, validation, native normalization, design-system mapping, page context, Elementor update and final status. It never contains hidden reasoning, credentials, prompts, raw page payloads or raw provider responses.',
        'provider_rate_limit' => [ 'calls' => WPAE_LLM_CALL_LIMIT, 'window_seconds' => WPAE_LLM_CALL_WINDOW, 'scope' => 'site-wide' ],
        'privacy' => 'Provider keys are encrypted in wp_options. Prompts, histories, and raw provider responses are not stored or logged.',
    ];
}
