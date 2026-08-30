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

function wpae_llm_provider_error_message( $body ): string {
    $choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : [];
    $message = is_array( $choice['error'] ?? null ) ? ( $choice['error']['message'] ?? '' ) : '';
    $message = $message ?: ( $body['error']['message'] ?? $body['message'] ?? '' );
    return is_scalar( $message ) ? sanitize_text_field( (string) $message ) : '';
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

function wpae_llm_provider_request( string $url, array $remote_args, array $request_body, bool $action_request, string $provider ) {
    $remote_args['body'] = wp_json_encode( $request_body );
    $response = wp_safe_remote_post( $url, $remote_args );
    if ( ! is_wp_error( $response ) && $action_request && $provider === 'openrouter' ) {
        $initial_status = wp_remote_retrieve_response_code( $response );
        $initial_body = json_decode( wp_remote_retrieve_body( $response ), true );
        $initial_error = wpae_llm_provider_error_message( is_array( $initial_body ) ? $initial_body : [] );
        $structured_route_rejected = $initial_status >= 400 && $initial_status < 500 && ( stripos( $initial_error, 'No endpoints found' ) !== false || stripos( $initial_error, 'requested parameters' ) !== false );
        if ( $structured_route_rejected ) {
            unset( $request_body['response_format'], $request_body['provider'] );
            $remote_args['body'] = wp_json_encode( $request_body );
            $response = wp_safe_remote_post( $url, $remote_args );
        }
    }
    return $response;
}

function wpae_llm_is_content_composition_request( string $message ): bool {
    $message = trim( $message );
    if ( strlen( $message ) < 40 || preg_match( '/[?؟]\s*$/u', $message ) ) {
        return false;
    }

    $segments = preg_split( '/(?:\r?\n+|[.!?]\s+)/u', $message, -1, PREG_SPLIT_NO_EMPTY );
    $pairs = 0;
    foreach ( $segments as $segment ) {
        $segment = trim( $segment, " \t\n\r\0\x0B\"«»" );
        if ( preg_match( '/^.{2,80}\s*(?:—|–|-|:)\s*\S.{4,180}$/u', $segment ) ) {
            $pairs++;
        }
    }

    return $pairs >= 2;
}

function wpae_llm_is_action_request( string $message ): bool {
    if ( preg_match( '/^\s*(как|что|почему|зачем|объясни|подскажи)\b/ui', $message ) ) {
        return false;
    }
    return (bool) preg_match( '/\b(сделай|создай|добавь|собери|сверстай|измени|исправь|поставь|замени|верст|hero|хиро|лендинг)\b/ui', $message ) || wpae_llm_is_content_composition_request( $message );
}

function wpae_llm_is_targeted_edit_request( string $message ): bool {
    return (bool) preg_match( '/\b(измени|поменяй|поставь|сделай|увеличь|уменьши|замени|настрой).*(шрифт|типограф|размер|кегл|цвет|отступ|padding|margin|радиус|высот|ширин|выравнив|интервал)/iu', $message );
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

function wpae_llm_new_operation_id(): string {
    return 'wpae-' . gmdate( 'YmdHis' ) . '-' . substr( wp_generate_uuid4(), 0, 8 );
}

function wpae_llm_validate_action_shape( array $action, int $post_id ): array {
    $elements = $action['elements'] ?? null;
    $errors = [];
    if ( (string) ( $action['action'] ?? '' ) !== 'insert_elements' ) {
        $errors[] = 'action must be insert_elements.';
    }
    if ( absint( $action['post_id'] ?? 0 ) !== $post_id ) {
        $errors[] = 'post_id must match the current Elementor page.';
    }
    if ( ! is_array( $elements ) || count( $elements ) !== 1 ) {
        $errors[] = 'elements must contain exactly one root container.';
    } elseif ( (string) ( $elements[0]['elType'] ?? '' ) !== 'container' ) {
        $errors[] = 'the root element must be an Elementor container.';
    } elseif ( empty( $elements[0]['elements'] ) || ! is_array( $elements[0]['elements'] ) ) {
        $errors[] = 'the root container must contain native widgets.';
    }
    return [ 'ok' => empty( $errors ), 'errors' => $errors ];
}

function wpae_llm_action_diff( array $before, array $inserted, array $after ): array {
    $ids = [];
    foreach ( $inserted as $element ) {
        if ( is_array( $element ) && ! empty( $element['id'] ) ) {
            $ids[] = sanitize_key( (string) $element['id'] );
        }
    }
    return [
        'before_top_level_count' => count( $before ),
        'inserted_top_level_count' => count( $inserted ),
        'after_top_level_count' => count( $after ),
        'inserted_ids' => array_values( array_unique( $ids ) ),
        'changed' => count( $after ) !== count( $before ),
    ];
}

function wpae_llm_execute_patch_action( array $action, int $post_id, array $selected_ids = [] ): array {
    $operation_id = wpae_llm_new_operation_id();
    $patches = is_array( $action['patches'] ?? null ) ? array_slice( $action['patches'], 0, 12 ) : [];
    $selected_ids = array_values( array_filter( array_map( 'sanitize_key', $selected_ids ) ) );
    $patch_ids = array_values( array_filter( array_map( static fn( $patch ) => is_array( $patch ) ? sanitize_key( (string) ( $patch['element_id'] ?? $patch['id'] ?? '' ) ) : '', $patches ) ) );
    if ( (string) ( $action['action'] ?? '' ) !== 'patch_elements' || absint( $action['post_id'] ?? 0 ) !== $post_id || empty( $patches ) || empty( $selected_ids ) || count( $patch_ids ) !== count( $patches ) || ! empty( array_diff( $patch_ids, $selected_ids ) ) ) {
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Patch-команда не соответствует текущему Elementor элементу или странице.' ];
    }
    $request = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/patch' );
    $request->set_param( 'post_id', $post_id );
    $request->set_param( 'patches', $patches );
    $request->set_param( 'dry_run', true );
    $preview = wpae_elementor_patch( $request );
    $preview_data = $preview instanceof WP_REST_Response ? $preview->get_data() : [];
    $preview_status = $preview instanceof WP_REST_Response ? $preview->get_status() : 500;
    $steps = [ [ 'id' => 'preview', 'status' => $preview_status >= 200 && $preview_status < 300 && ! empty( $preview_data['ok'] ) ? 'ok' : 'failed', 'message' => 'Patch preview и preflight проверены до записи.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $preview_status, 'patch_count' => count( $patches ) ] ] ];
    if ( $preview_status < 200 || $preview_status >= 300 || empty( $preview_data['ok'] ) ) {
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Patch preview отклонён до записи.', 'status' => $preview_status, 'details' => $preview_data, 'steps' => $steps ];
    }
    $request->set_param( 'dry_run', false );
    $result = wpae_elementor_patch( $request );
    $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
    $status = $result instanceof WP_REST_Response ? $result->get_status() : 500;
    if ( $status < 200 || $status >= 300 || empty( $data['ok'] ) ) {
        $steps[] = [ 'id' => 'elementor_patch', 'status' => 'failed', 'message' => 'Patch не сохранён и был остановлен проверками.', 'details' => [ 'http_status' => $status, 'error' => $data['error'] ?? '' ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor patch отклонён проверкой.', 'status' => $status, 'details' => $data, 'steps' => $steps ];
    }
    $changes = (array) ( $data['patch_report']['changes'] ?? [] );
    $steps[] = [ 'id' => 'elementor_patch', 'status' => 'ok', 'message' => 'Точечные native-свойства изменены через Elementor patch.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $status, 'changes' => $changes ] ];
    return [
        'ok' => true,
        'operation_id' => $operation_id,
        'action' => 'patch_elements',
        'post_id' => $post_id,
        'changed_count' => count( $changes ),
        'patch_report' => $data['patch_report'] ?? [],
        'rollback_snapshot_id' => $data['rollback_snapshot_id'] ?? null,
        'rollback_expires_at' => $data['rollback_expires_at'] ?? null,
        'editor_sync' => [ 'mode' => 'refresh', 'changed_ids' => array_values( array_unique( array_map( static fn( $item ) => sanitize_key( (string) ( $item['element_id'] ?? '' ) ), $changes ) ) ) ],
        'steps' => $steps,
    ];
}

function wpae_llm_detect_block_archetype( string $message ): string {
    $patterns = [
        'hero' => '/\b(hero|хиро|первый экран|обложк)/iu',
        'benefits' => '/\b(преимуществ|benefit|features?|выгод|почему мы)/iu',
        'pricing' => '/\b(тариф|цен|пакет|pricing|стоимост)/iu',
        'testimonials' => '/\b(отзыв|testimonial|клиентск|рекомендац)/iu',
        'faq' => '/\b(faq|вопрос|ответ|аккордеон)/iu',
        'process' => '/\b(процесс|этап|шаг|process|steps?)/iu',
        'cta' => '/\b(cta|заявк|связ|контакт|призыв)/iu',
        'portfolio' => '/\b(портфолио|кейс|работ|portfolio|project)/iu',
    ];
    foreach ( $patterns as $archetype => $pattern ) {
        if ( preg_match( $pattern, $message ) ) {
            return $archetype;
        }
    }
    if ( wpae_llm_is_content_composition_request( $message ) ) {
        $normalized = wpae_llm_normalize_content_text( $message );
        if ( preg_match( '/\?|؟/u', $message ) ) {
            return 'faq';
        }
        if ( preg_match( '/₸|\$|€|₽|\b(цена|стоимост|тариф|пакет|от\s+\d+)/iu', $message ) ) {
            return 'pricing';
        }
        if ( preg_match( '/\b(анна|мария|дмитрий|руслан|марат|отзыв|рекомендац|понравилось|получили)\b/iu', $normalized ) || preg_match( '/[«"]/u', $message ) ) {
            return 'testimonials';
        }
        if ( preg_match( '/\b(быстрый старт|понятная структура|поддержка после запуска|преимуществ|выгод)\b/iu', $normalized ) ) {
            return 'benefits';
        }
        if ( preg_match( '/\b(шаг|этап|сначала|затем|после этого|проверяем|запускаем)\b/iu', $normalized ) ) {
            return 'process';
        }
        if ( preg_match( '/\b(кейс|проект|рост|увелич|сократ|результат|клиент)\b/iu', $normalized ) ) {
            return 'portfolio';
        }
        return 'benefits';
    }
    return 'custom';
}

function wpae_llm_block_archetype_hint( string $message ): string {
    $archetype = wpae_llm_detect_block_archetype( $message );
    $labels = [
        'hero' => [ 'hero/первый экран', 'heading, text-editor, button и image при необходимости' ],
        'benefits' => [ 'преимущества/features', 'heading, icon-list и text-editor или button' ],
        'pricing' => [ 'тарифы/pricing', 'heading, price-list или заполненные native heading/text-editor/button' ],
        'testimonials' => [ 'отзывы/testimonials', 'heading, quote/proof widgets и повторяющиеся native items' ],
        'faq' => [ 'FAQ', 'heading и accordion с заполненными вопросами и ответами' ],
        'process' => [ 'процесс/этапы', 'heading, icon-list или text-editor и divider' ],
        'cta' => [ 'CTA/контакт', 'heading, text-editor и button' ],
        'portfolio' => [ 'портфолио/кейсы', 'heading, image и text-editor или button' ],
    ];
    if ( isset( $labels[ $archetype ] ) ) {
        return ' Сначала классифицируй запрос как блок «' . $labels[ $archetype ][0] . '» и собери соответствующую композицию. Предпочтительные native widgets: ' . $labels[ $archetype ][1] . '. Не повторяй hero/benefits-шаблон, если запрос относится к другому типу.';
    }
    return ' Сначала определи тип блока по смыслу запроса и выбери подходящие native widgets из доступных Elementor. Не своди каждый блок к одному и тому же hero/benefits-шаблону; содержание и композиция должны соответствовать задаче пользователя.';
}

function wpae_llm_normalize_content_text( $value ): string {
    $value = wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    $value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    return trim( preg_replace( '/\s+/u', ' ', $value ) );
}

function wpae_llm_extract_requested_content( string $message ): array {
    $matches = [];
    foreach ( [ '/«([^»]{2,240})»/u', '/"([^"\\n]{2,240})"/u' ] as $pattern ) {
        if ( preg_match_all( $pattern, $message, $found ) ) {
            $matches = array_merge( $matches, $found[1] );
        }
    }
    foreach ( wpae_llm_extract_labeled_content( $message ) as $pair ) {
        $matches[] = $pair['label'];
        $matches[] = $pair['content'];
    }
    $content = [];
    foreach ( $matches as $value ) {
        $value = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $value ) ) );
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
        if ( $value !== '' && $length >= 3 ) {
            $content[ wpae_llm_normalize_content_text( $value ) ] = $value;
        }
    }
    return array_values( $content );
}

function wpae_llm_extract_labeled_content( string $message ): array {
    $pairs = [];
    $segments = preg_split( '/(?:\r?\n|(?<=[.!?])\s+)/u', $message ) ?: [];
    foreach ( $segments as $segment ) {
        if ( ! preg_match( '/^\s*([^—–-]{2,80}?)\s*[—–-]\s*(.{3,240})\s*$/u', trim( (string) $segment ), $match ) ) {
            continue;
        }
        $label = trim( sanitize_text_field( (string) $match[1] ) );
        if ( strpos( $label, ':' ) !== false ) {
            $parts = preg_split( '/:\s*/u', $label );
            $label = trim( (string) end( $parts ) );
        }
        $content = trim( sanitize_text_field( (string) $match[2] ) );
        if ( $label !== '' && $content !== '' ) {
            $pairs[] = [ 'label' => $label, 'content' => $content ];
        }
    }
    return $pairs;
}

function wpae_llm_collect_action_content( array $elements ): string {
    $content = [];
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        foreach ( [ 'title', 'editor', 'text', 'tab_title', 'tab_content' ] as $key ) {
            if ( is_scalar( $settings[ $key ] ?? null ) ) {
                $content[] = (string) $settings[ $key ];
            }
            if ( is_scalar( $element[ $key ] ?? null ) ) {
                $content[] = (string) $element[ $key ];
            }
        }
        foreach ( [ 'tabs', 'elements' ] as $child_key ) {
            if ( is_array( $element[ $child_key ] ?? null ) ) {
                $content[] = wpae_llm_collect_action_content( $element[ $child_key ] );
            }
        }
    }
    return implode( ' ', $content );
}

function wpae_llm_content_fidelity( string $message, array $elements ): array {
    $requested = wpae_llm_extract_requested_content( $message );
    $haystack = wpae_llm_normalize_content_text( wpae_llm_collect_action_content( $elements ) );
    $missing = [];
    foreach ( $requested as $value ) {
        if ( strpos( $haystack, wpae_llm_normalize_content_text( $value ) ) === false ) {
            $missing[] = $value;
        }
    }
    return [
        'requested_count' => count( $requested ),
        'matched_count' => count( $requested ) - count( $missing ),
        'missing' => array_slice( $missing, 0, 12 ),
        'ok' => empty( $missing ),
    ];
}

function wpae_llm_apply_fallback_content( array &$elements, array &$missing, string $archetype, int &$changed, int $depth = 0 ): void {
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $repeatable_archetypes = [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ];
        $is_repeatable_shell = in_array( $archetype, $repeatable_archetypes, true ) && $depth === 1;
        if ( ! $is_repeatable_shell && ! empty( $missing ) && in_array( $widget_type, [ 'heading', 'text-editor' ], true ) ) {
            $value_index = 0;
            if ( $archetype === 'pricing' ) {
                $is_numeric = static fn( $value ): bool => (bool) preg_match( '/\d|₸|\$|€|₽/u', (string) $value );
                $wanted_numeric = $widget_type === 'text-editor';
                foreach ( $missing as $index => $value ) {
                    if ( $is_numeric( $value ) === $wanted_numeric ) {
                        $value_index = $index;
                        break;
                    }
                }
            } elseif ( $archetype === 'testimonials' && $widget_type === 'heading' ) {
                $value_index = count( $missing ) - 1;
            }
            $key = $widget_type === 'heading' ? 'title' : 'editor';
            $settings[ $key ] = $missing[ $value_index ];
            array_splice( $missing, $value_index, 1 );
            $changed++;
        }
        $element['settings'] = $settings;
        foreach ( [ 'elements' ] as $child_key ) {
            if ( is_array( $element[ $child_key ] ?? null ) ) {
                wpae_llm_apply_fallback_content( $element[ $child_key ], $missing, $archetype, $changed, $depth + 1 );
            }
        }
    }
    unset( $element );
}

function wpae_llm_apply_fallback_archetype_content( array &$elements, string $message, string $archetype, int &$changed ): void {
    if ( ! in_array( $archetype, [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ], true ) ) {
        return;
    }
    $pairs = wpae_llm_extract_labeled_content( $message );
    if ( count( $pairs ) < 2 ) {
        return;
    }
    $heading = '';
    if ( preg_match( '/(?:заголовок|название)\s*:\s*[«"]([^»"\n]{2,240})[»"]/iu', $message, $match ) ) {
        $heading = trim( sanitize_text_field( (string) $match[1] ) );
    }
    $cta = '';
    if ( preg_match( '/(?:кнопка|cta)\s*:\s*([^\.\n]{3,120})/iu', $message, $match ) ) {
        $cta = trim( sanitize_text_field( (string) $match[1] ) );
    }
    foreach ( wpae_llm_extract_requested_content( $message ) as $value ) {
        if ( $cta === '' && preg_match( '/получить|расч[её]т|обсудить|задать|узнать|смотреть/iu', $value ) ) {
            $cta = $value;
            break;
        }
    }
    foreach ( $elements as &$root ) {
        if ( ! is_array( $root ) || ! is_array( $root['elements'] ?? null ) ) {
            continue;
        }
        foreach ( $root['elements'] as &$child ) {
            if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'container' || ! is_array( $child['elements'] ?? null ) ) {
                continue;
            }
            $cards = array_values( array_filter( $child['elements'], static fn( $item ) => is_array( $item ) && ( $item['elType'] ?? '' ) === 'container' ) );
            if ( count( $cards ) < 2 ) {
                continue;
            }
            $target_card_count = min( 4, max( 2, count( $pairs ) ) );
            if ( count( $cards ) > $target_card_count ) {
                $trimmed = [];
                $card_count = 0;
                foreach ( $child['elements'] as $item ) {
                    if ( is_array( $item ) && ( $item['elType'] ?? '' ) === 'container' ) {
                        if ( $card_count >= $target_card_count ) {
                            continue;
                        }
                        $card_count++;
                    }
                    $trimmed[] = $item;
                }
                $child['elements'] = $trimmed;
            }
            foreach ( $child['elements'] as &$card ) {
                if ( ! is_array( $card ) || ( $card['elType'] ?? '' ) !== 'container' || ! isset( $pairs[0] ) ) {
                    continue;
                }
                $pair = array_shift( $pairs );
                $title_set = false;
                $copy_set = false;
                foreach ( $card['elements'] ?? [] as &$widget ) {
                    if ( ! is_array( $widget ) ) {
                        continue;
                    }
                    $widget_type = (string) ( $widget['widgetType'] ?? '' );
                    $widget['settings'] = is_array( $widget['settings'] ?? null ) ? $widget['settings'] : [];
                    if ( $widget_type === 'heading' && ! $title_set ) {
                        $widget['settings']['title'] = $pair['label'];
                        $title_set = true;
                        $changed++;
                    } elseif ( in_array( $widget_type, [ 'text-editor', 'testimonial' ], true ) && ! $copy_set ) {
                        $widget['settings'][ $widget_type === 'testimonial' ? 'testimonial_content' : 'editor' ] = $pair['content'];
                        $copy_set = true;
                        $changed++;
                    } elseif ( $widget_type === 'button' && $cta !== '' ) {
                        $widget['settings']['text'] = $cta;
                        $changed++;
                    }
                }
                unset( $widget );
            }
            unset( $card );
        }
        unset( $child );
        if ( $heading !== '' && isset( $root['elements'][0]['widgetType'] ) && $root['elements'][0]['widgetType'] === 'heading' ) {
            $root['elements'][0]['settings'] = is_array( $root['elements'][0]['settings'] ?? null ) ? $root['elements'][0]['settings'] : [];
            $root['elements'][0]['settings']['title'] = $heading;
            $changed++;
        }
    }
    unset( $root );
}

function wpae_llm_bento_card( string $id, array $elements ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => [
            'content_width' => 'full',
            'flex_direction' => 'column',
            'background_background' => 'classic',
            'background_color' => '#ffffff',
            'border_border' => 'solid',
            'border_color' => '#e5e7eb',
            'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
            'border_radius' => [ 'unit' => 'rem', 'size' => 0.75, 'isLinked' => true ],
            'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
            'padding' => [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ],
            'padding_mobile' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ],
        ],
        'elements' => $elements,
    ];
}

function wpae_llm_bento_grid( string $id, array $elements ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => [
            'content_width' => 'full',
            'flex_direction' => 'row',
            'flex_wrap' => 'wrap',
            'flex_justify_content' => 'space-between',
            'flex_align_items' => 'stretch',
            'flex_gap' => [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ],
            'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ],
            'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
            'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
        ],
        'elements' => $elements,
    ];
}

function wpae_llm_build_fallback_action( string $message, int $post_id ): array {
    $archetype = wpae_llm_detect_block_archetype( $message );
    $widget = static function ( string $id, string $type, array $settings = [] ): array {
        return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
    };
    $gap = [ 'column' => '1.5', 'row' => '1.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.5' ];
    $padding = [ 'unit' => 'rem', 'top' => '2.5', 'right' => '1.5', 'bottom' => '2.5', 'left' => '1.5', 'isLinked' => false ];
    $card = static function ( string $id, array $children ) use ( $widget ): array {
        return wpae_llm_bento_card( $id, $children );
    };
    $grid = static function ( string $id, array $cards ): array {
        return wpae_llm_bento_grid( $id, $cards );
    };
    $elements = [
        $widget( 'llm-heading', 'heading', [ 'title' => 'Новый блок', 'header_size' => 'h2' ] ),
        $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Содержательный блок под вашу задачу.' ] ),
        $widget( 'llm-button', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
    ];
    if ( $archetype === 'benefits' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Преимущества для вашего проекта', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Три опоры, которые делают страницу понятной, убедительной и готовой к заявке.' ] ),
            $grid( 'llm-benefits-grid', [
                $card( 'llm-benefit-1', [ $widget( 'llm-benefit-1-title', 'heading', [ 'title' => 'Понятная структура', 'header_size' => 'h4' ] ), $widget( 'llm-benefit-1-copy', 'text-editor', [ 'editor' => 'Посетитель быстро понимает предложение и следующий шаг.' ] ) ] ),
                $card( 'llm-benefit-2', [ $widget( 'llm-benefit-2-title', 'heading', [ 'title' => 'Native Elementor', 'header_size' => 'h4' ] ), $widget( 'llm-benefit-2-copy', 'text-editor', [ 'editor' => 'Контент и стили остаются редактируемыми в визуальном редакторе.' ] ) ] ),
                $card( 'llm-benefit-3', [ $widget( 'llm-benefit-3-title', 'heading', [ 'title' => 'Готово к росту', 'header_size' => 'h4' ] ), $widget( 'llm-benefit-3-copy', 'text-editor', [ 'editor' => 'Компоненты и адаптивная сетка не рассыпаются на мобильных устройствах.' ] ) ] ),
            ] ),
            $widget( 'llm-button', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'faq' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Частые вопросы', 'header_size' => 'h2' ] ),
            $widget( 'llm-accordion', 'accordion', [ 'tabs' => [ [ 'tab_title' => 'Можно ли изменить блок позже?', 'tab_content' => 'Да, все основные настройки остаются доступными в Elementor.' ], [ 'tab_title' => 'Будет ли версия для мобильных?', 'tab_content' => 'Да, композиция и spacing задаются с учетом mobile-first.' ] ] ] ),
            $widget( 'llm-button', 'button', [ 'text' => 'Задать вопрос', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'process' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Как проходит работа', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Понятный маршрут от первой задачи до готовой страницы.' ] ),
            $grid( 'llm-process-grid', [
                $card( 'llm-process-1', [ $widget( 'llm-process-1-title', 'heading', [ 'title' => '01 / Бриф', 'header_size' => 'h4' ] ), $widget( 'llm-process-1-copy', 'text-editor', [ 'editor' => 'Фиксируем цель, аудиторию и главное действие страницы.' ] ) ] ),
                $card( 'llm-process-2', [ $widget( 'llm-process-2-title', 'heading', [ 'title' => '02 / Структура', 'header_size' => 'h4' ] ), $widget( 'llm-process-2-copy', 'text-editor', [ 'editor' => 'Собираем смысловой маршрут и расставляем доказательства.' ] ) ] ),
                $card( 'llm-process-3', [ $widget( 'llm-process-3-title', 'heading', [ 'title' => '03 / Сборка', 'header_size' => 'h4' ] ), $widget( 'llm-process-3-copy', 'text-editor', [ 'editor' => 'Создаем блоки из native Elementor-элементов и задаем адаптив.' ] ) ] ),
                $card( 'llm-process-4', [ $widget( 'llm-process-4-title', 'heading', [ 'title' => '04 / Проверка', 'header_size' => 'h4' ] ), $widget( 'llm-process-4-copy', 'text-editor', [ 'editor' => 'Проверяем визуальный результат, редактируемость и CTA.' ] ) ] ),
            ] ),
            $widget( 'llm-button', 'button', [ 'text' => 'Начать проект', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'pricing' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Выберите формат работы', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Соберите нужный объем работ и начните с понятного следующего шага.' ] ),
            $grid( 'llm-pricing-grid', [
                $card( 'llm-pricing-1', [ $widget( 'llm-pricing-1-title', 'heading', [ 'title' => 'Лендинг', 'header_size' => 'h4' ] ), $widget( 'llm-pricing-1-copy', 'text-editor', [ 'editor' => '<strong>Быстрый запуск</strong><br>Один экран или страница услуги с ясным оффером, структурой и CTA.' ] ), $widget( 'llm-pricing-1-button', 'button', [ 'text' => 'Обсудить формат', 'link' => [ 'url' => '#contact' ] ] ) ] ),
                $card( 'llm-pricing-2', [ $widget( 'llm-pricing-2-title', 'heading', [ 'title' => 'Система страниц', 'header_size' => 'h4' ] ), $widget( 'llm-pricing-2-copy', 'text-editor', [ 'editor' => '<strong>Масштабируемая структура</strong><br>Несколько связанных страниц, единая дизайн-система и путь клиента.' ] ), $widget( 'llm-pricing-2-button', 'button', [ 'text' => 'Получить расчет', 'link' => [ 'url' => '#contact' ] ] ) ] ),
                $card( 'llm-pricing-3', [ $widget( 'llm-pricing-3-title', 'heading', [ 'title' => 'Поддержка', 'header_size' => 'h4' ] ), $widget( 'llm-pricing-3-copy', 'text-editor', [ 'editor' => '<strong>Развитие проекта</strong><br>Точечные улучшения, новые блоки и контроль качества после запуска.' ] ), $widget( 'llm-pricing-3-button', 'button', [ 'text' => 'Задать вопрос', 'link' => [ 'url' => '#contact' ] ] ) ] ),
            ] ),
        ];
    } elseif ( $archetype === 'testimonials' ) {
        $card = static function ( string $id, string $quote, string $author ) use ( $widget ): array {
            return [
                'id' => $id,
                'elType' => 'container',
                'settings' => [
                    'content_width' => 'full',
                    'flex_direction' => 'column',
                    'background_background' => 'classic',
                    'background_color' => '#f7f7f5',
                    'border_border' => 'solid',
                    'border_color' => '#e5e7eb',
                    'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
                    'border_radius' => [ 'unit' => 'rem', 'size' => 1, 'isLinked' => true ],
                    'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                    'padding' => [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ],
                    'padding_mobile' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ],
                    'width' => [ 'unit' => '%', 'size' => 31 ],
                    'width_mobile' => [ 'unit' => '%', 'size' => 100 ],
                    '_element_width' => 'initial',
                    '_element_custom_width' => [ 'unit' => '%', 'size' => 31, 'sizes' => [] ],
                    '_element_width_mobile' => 'initial',
                    '_element_custom_width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                    '_flex_size' => 'custom',
                    '_flex_grow' => 0,
                    '_flex_shrink' => 0,
                    '_flex_size_mobile' => 'custom',
                    '_flex_grow_mobile' => 0,
                    '_flex_shrink_mobile' => 0,
                ],
                'elements' => [
                    $widget( $id . '-quote', 'text-editor', [ 'editor' => $quote ] ),
                    $widget( $id . '-author', 'heading', [ 'title' => $author, 'header_size' => 'h5' ] ),
                ],
            ];
        };
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Что говорят клиенты', 'header_size' => 'h2' ] ),
            [ 'id' => 'llm-testimonial-grid', 'elType' => 'container', 'settings' => [ 'content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'stretch', 'flex_gap' => [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ], 'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ], 'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ], 'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ] ], 'elements' => [
                $card( 'llm-card-1', '«Стало сразу понятно, что мы продаем и куда вести клиента.»', 'Анна, студия брендинга' ),
                $card( 'llm-card-2', '«Получили аккуратную страницу, которую можем менять сами.»', 'Руслан, сервисный бизнес' ),
                $card( 'llm-card-3', '«Новая структура заметно упростила путь клиента к заявке.»', 'Марат, консалтинговая компания' ),
            ] ],
            $widget( 'llm-button', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'portfolio' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Избранные проекты', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Показываем задачу, решение и результат каждого проекта без лишнего шума.' ] ),
            $grid( 'llm-portfolio-grid', [
                $card( 'llm-portfolio-1', [ $widget( 'llm-portfolio-1-title', 'heading', [ 'title' => 'Сервисный бизнес', 'header_size' => 'h4' ] ), $widget( 'llm-portfolio-1-copy', 'text-editor', [ 'editor' => 'Страница услуги с понятным оффером и маршрутом к заявке.' ] ) ] ),
                $card( 'llm-portfolio-2', [ $widget( 'llm-portfolio-2-title', 'heading', [ 'title' => 'Запуск продукта', 'header_size' => 'h4' ] ), $widget( 'llm-portfolio-2-copy', 'text-editor', [ 'editor' => 'Собрали структуру, которая объясняет ценность продукта с первого экрана.' ] ) ] ),
                $card( 'llm-portfolio-3', [ $widget( 'llm-portfolio-3-title', 'heading', [ 'title' => 'Редизайн', 'header_size' => 'h4' ] ), $widget( 'llm-portfolio-3-copy', 'text-editor', [ 'editor' => 'Убрали лишнее, усилили доказательства и сделали систему редактируемой.' ] ) ] ),
            ] ),
            $widget( 'llm-button', 'button', [ 'text' => 'Смотреть кейсы', 'link' => [ 'url' => '#cases' ] ] ),
        ];
    }
    return [
        'action' => 'insert_elements',
        'post_id' => $post_id,
        'position' => 'end',
        'elements' => [ [ 'id' => 'llm-fallback', 'elType' => 'container', 'settings' => [ 'content_width' => 'boxed', 'flex_direction' => 'column', 'background_background' => 'classic', 'background_color' => '#f7f7f5', 'flex_gap' => $gap, 'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ], 'padding' => $padding, 'padding_mobile' => [ 'unit' => 'rem', 'top' => '2', 'right' => '1', 'bottom' => '2', 'left' => '1', 'isLinked' => true ] ], 'elements' => $elements ] ],
    ];
}

function wpae_llm_normalize_generated_typography( array $elements, string $archetype, int $depth = 0, int &$changed = 0 ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        if ( $archetype === 'testimonials' && $widget_type === 'heading' && $depth >= 2 ) {
            $header_size = strtolower( (string) ( $settings['header_size'] ?? '' ) );
            if ( ! in_array( $header_size, [ 'h5', 'h6' ], true ) ) {
                $settings['header_size'] = 'h5';
                $changed++;
            }
        }

        $element['settings'] = $settings;
        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_llm_normalize_generated_typography( $element['elements'], $archetype, $depth + 1, $changed );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_llm_apply_bento_layout( array $elements, string $archetype, int &$changed ): array {
    $repeatable = [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ];
    if ( ! in_array( $archetype, $repeatable, true ) ) {
        return $elements;
    }

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
        $child_containers = [];
        foreach ( $children as $child_index => $child ) {
            if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                $child_containers[] = $child_index;
            }
        }

        if ( count( $children ) >= 3 && empty( $child_containers ) ) {
            $leading = [];
            $trailing = [];
            $repeatable_children = $children;
            if ( is_array( $repeatable_children[0] ?? null ) && ( $repeatable_children[0]['elType'] ?? '' ) === 'widget' && ( $repeatable_children[0]['widgetType'] ?? '' ) === 'heading' ) {
                $leading[] = array_shift( $repeatable_children );
            }
            if ( is_array( end( $repeatable_children ) ) && ( end( $repeatable_children )['elType'] ?? '' ) === 'widget' && ( end( $repeatable_children )['widgetType'] ?? '' ) === 'button' ) {
                $trailing[] = array_pop( $repeatable_children );
            }
            if ( count( $repeatable_children ) >= 2 && ( ! empty( $leading ) || ! empty( $trailing ) ) ) {
                $cards = [];
                foreach ( $repeatable_children as $child_index => $child ) {
                    $cards[] = wpae_llm_bento_card( 'wpae-bento-' . (string) $child_index, [ $child ] );
                    $changed++;
                }
                $children = array_merge( $leading, [ wpae_llm_bento_grid( 'wpae-bento-grid-' . (string) $index, $cards ) ], $trailing );
                $element['elements'] = $children;
            } elseif ( empty( $leading ) && empty( $trailing ) ) {
                $wrapped = [];
                foreach ( $children as $child_index => $child ) {
                    if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' ) {
                        $wrapped[] = $child;
                        continue;
                    }
                    $wrapped[] = wpae_llm_bento_card( 'wpae-bento-' . (string) $child_index, [ $child ] );
                    $changed++;
                }
                $children = $wrapped;
                $element['elements'] = $children;
            }
            $child_containers = [];
            foreach ( $children as $child_index => $child ) {
                if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                    $child_containers[] = $child_index;
                }
            }
            $element['elements'] = $children;
        }

        $has_semantic_shell = ( is_array( $children[0] ?? null ) && ( $children[0]['elType'] ?? '' ) === 'widget' && ( $children[0]['widgetType'] ?? '' ) === 'heading' )
            || ( is_array( end( $children ) ) && ( end( $children )['elType'] ?? '' ) === 'widget' && ( end( $children )['widgetType'] ?? '' ) === 'button' );
        if ( count( $child_containers ) >= 2 && $has_semantic_shell ) {
            $first_card = (int) $child_containers[0];
            $last_card = (int) end( $child_containers );
            $leading = array_slice( $children, 0, $first_card );
            foreach ( array_slice( $children, $first_card + 1, max( 0, $last_card - $first_card - 1 ) ) as $between_index => $between ) {
                if ( ! in_array( $first_card + 1 + $between_index, $child_containers, true ) ) {
                    $leading[] = $between;
                }
            }
            $cards = [];
            foreach ( $child_containers as $child_index ) {
                $cards[] = wpae_llm_bento_card( 'wpae-bento-' . (string) $child_index, [ $children[ $child_index ] ] );
            }
            $trailing = array_slice( $children, $last_card + 1 );
            $children = array_merge( $leading, [ wpae_llm_bento_grid( 'wpae-bento-grid-' . (string) $index, $cards ) ], $trailing );
            $element['elements'] = $children;
            $child_containers = [ count( $leading ) ];
            $changed++;
        }
        if ( count( $child_containers ) >= 2 && ! $has_semantic_shell ) {
            $settings['flex_direction'] = 'row';
            $settings['flex_wrap'] = 'wrap';
            $settings['flex_justify_content'] = 'space-between';
            $settings['flex_align_items'] = 'stretch';
            $settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
            $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
            $changed++;

            $count = count( $child_containers );
            $width = $count >= 4 ? 23 : ( $count === 3 ? 31 : 48 );
            foreach ( $child_containers as $child_index ) {
                $child_settings = is_array( $children[ $child_index ]['settings'] ?? null ) ? $children[ $child_index ]['settings'] : [];
                $child_settings['width'] = [ 'unit' => '%', 'size' => $width, 'sizes' => [] ];
                $child_settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
                $child_settings['_element_width'] = 'initial';
                $child_settings['_element_custom_width'] = [ 'unit' => '%', 'size' => $width, 'sizes' => [] ];
                $child_settings['_element_width_mobile'] = 'initial';
                $child_settings['_element_custom_width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
                $child_settings['_flex_size'] = 'custom';
                $child_settings['_flex_grow'] = 0;
                $child_settings['_flex_shrink'] = 0;
                $child_settings['_flex_size_mobile'] = 'custom';
                $child_settings['_flex_grow_mobile'] = 0;
                $child_settings['_flex_shrink_mobile'] = 0;
                $children[ $child_index ]['settings'] = $child_settings;
            }
            $element['elements'] = $children;
        }

        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_llm_apply_bento_layout( $element['elements'], $archetype, $changed );
        }
        $element['settings'] = $settings;
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_llm_decode_action( string $reply, int $post_id = 0 ): array {
    $candidate = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $reply ) );
    $decoded = json_decode( $candidate, true );
    if ( is_string( $decoded ) ) {
        $nested = json_decode( trim( $decoded ), true );
        if ( is_array( $nested ) ) {
            $decoded = $nested;
        }
    }
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
                'reply_length' => strlen( $reply ),
                'json_error' => sanitize_text_field( json_last_error_msg() ),
                'likely_truncated' => substr( rtrim( $candidate ), -1 ) !== '}',
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
    $operation_id = wpae_llm_new_operation_id();
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
            'operation_id' => $operation_id,
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
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Разрешение elementor_writes выключено владельцем сайта.', 'capability' => 'elementor_writes', 'steps' => $steps ];
    }
    if ( function_exists( 'current_user_can' ) && is_user_logged_in() && ! current_user_can( 'edit_post', $post_id ) ) {
        $steps[] = [ 'id' => 'permissions', 'status' => 'failed', 'message' => 'Текущий пользователь не может изменить эту страницу.', 'details' => [ 'post_id' => $post_id ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Нет разрешения на изменение этой страницы.', 'post_id' => $post_id, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'command_validation', 'status' => 'ok', 'message' => 'Команда insert_elements и целевая страница подтверждены.', 'details' => [ 'action' => $received_action, 'post_id' => $post_id ] ];

    $elements = $action['elements'] ?? [];
    if ( ! is_array( $elements ) || empty( $elements ) || count( $elements ) > 12 ) {
        $steps[] = [ 'id' => 'element_count', 'status' => 'failed', 'message' => 'Количество новых Elementor-элементов должно быть от 1 до 12.', 'details' => [ 'element_count' => is_array( $elements ) ? count( $elements ) : 0 ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor-команда должна содержать от 1 до 12 новых элементов.', 'steps' => $steps ];
    }
    $shape = wpae_llm_validate_action_shape( $action, $post_id );
    if ( empty( $shape['ok'] ) ) {
        $steps[] = [ 'id' => 'command_shape', 'status' => 'failed', 'message' => 'Структура команды не соответствует безопасному Elementor-контракту.', 'details' => [ 'errors' => $shape['errors'] ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor-команда должна содержать ровно один заполненный корневой Flexbox-контейнер.', 'details' => $shape, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'element_count', 'status' => 'ok', 'message' => 'Количество новых элементов прошло проверку.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $widget_count = wpae_llm_count_widgets( $elements );
    if ( $widget_count < 1 ) {
        $steps[] = [ 'id' => 'native_widgets', 'status' => 'failed', 'message' => 'Команда не содержит native Elementor widgets, поэтому результат был бы пустым.', 'details' => [ 'widget_count' => 0 ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor-команда должна содержать хотя бы один native widget, иначе страница останется пустой.', 'steps' => $steps ];
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
    $request->set_param( 'transaction_visual_regression', ! $initial_page );
    $preview_request = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/update' );
    $preview_request->set_param( 'post_id', $post_id );
    $preview_request->set_param( 'elementor_data', $next );
    $preview_request->set_param( 'template', 'elementor_canvas' );
    $preview_request->set_param( '_wpae_allow_initial_data', true );
    $preview_request->set_param( 'dry_run', true );
    $preview = wpae_elementor_update( $preview_request );
    $preview_data = $preview instanceof WP_REST_Response ? $preview->get_data() : [];
    $preview_status = $preview instanceof WP_REST_Response ? $preview->get_status() : 500;
    if ( $preview_status < 200 || $preview_status >= 300 || ! is_array( $preview_data ) || empty( $preview_data['ok'] ) ) {
        $steps[] = [ 'id' => 'preview', 'status' => 'failed', 'message' => 'Предпросмотр команды отклонён до записи.', 'details' => [ 'http_status' => $preview_status, 'error' => sanitize_text_field( (string) ( $preview_data['error'] ?? 'Preview failed.' ) ), 'details' => $preview_data['details'] ?? $preview_data['preflight'] ?? [] ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Предпросмотр Elementor отклонён до записи.', 'update_error' => sanitize_text_field( (string) ( $preview_data['error'] ?? '' ) ), 'status' => $preview_status, 'details' => $preview_data, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'preview', 'status' => 'ok', 'message' => 'Предпросмотр команды и preflight прошли до записи.', 'details' => [ 'http_status' => $preview_status, 'post_id' => $post_id ] ];
    $result = wpae_elementor_update( $request );
    $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
    $status = $result instanceof WP_REST_Response ? $result->get_status() : 500;
    if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['ok'] ) ) {
        $blocking_errors = [];
        $failed_checks = [];
        $failure_details = [];
        foreach ( [ $data['details']['errors'] ?? [], $data['details']['design_system']['errors'] ?? [], $data['preflight']['blocking_errors'] ?? [] ] as $errors ) {
            foreach ( (array) $errors as $error ) {
                if ( is_scalar( $error ) && trim( (string) $error ) !== '' ) {
                    $blocking_errors[] = sanitize_text_field( (string) $error );
                }
            }
        }
        foreach ( [ $data['details']['transaction']['checks'] ?? [], $data['transaction']['checks'] ?? [], $data['details']['checks'] ?? [] ] as $checks ) {
            foreach ( (array) $checks as $code => $check ) {
                if ( is_array( $check ) && empty( $check['ok'] ) ) {
                    $failed_checks[] = sanitize_key( (string) $code );
                    $failure_details[] = [
                        'code' => sanitize_key( (string) $code ),
                        'message' => sanitize_text_field( (string) ( $check['message'] ?? 'Проверка не пройдена.' ) ),
                    ];
                }
            }
        }
        $failed_checks = array_values( array_unique( $failed_checks ) );
        return [
            'ok' => false,
            'operation_id' => $operation_id,
            'error' => 'Elementor update отклонен существующей проверкой.',
            'update_error' => sanitize_text_field( (string) ( $data['error'] ?? '' ) ),
            'status' => $status,
            'blocking_errors' => array_values( array_unique( $blocking_errors ) ),
            'failed_checks' => $failed_checks,
            'failure_details' => $failure_details,
            'details' => is_array( $data ) ? $data : [],
            'steps' => array_merge( $steps, [ [ 'id' => 'elementor_update', 'status' => 'failed', 'message' => 'Elementor update остановлен проверкой; изменения откатились, если rollback был доступен.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $status, 'blocking_errors' => array_values( array_unique( $blocking_errors ) ), 'failed_checks' => $failed_checks, 'failure_details' => $failure_details ] ] ] ),
        ];
    }
    $diff = wpae_llm_action_diff( $existing, $elements, $next );
    $steps[] = [ 'id' => 'elementor_update', 'status' => 'ok', 'message' => 'Изменения сохранены через Elementor update.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $status, 'diff' => $diff ] ];
    $steps[] = [ 'id' => 'complete', 'status' => 'ok', 'message' => 'Задача выполнена, Elementor подтвердил запись.' ];
    return [
        'ok' => true,
        'operation_id' => $operation_id,
        'action' => 'insert_elements',
        'post_id' => $post_id,
        'inserted_count' => count( $elements ),
        'inserted_widget_count' => wpae_llm_count_widgets( $elements ),
        'action_summary' => 'Добавлен блок ' . ( $action['position'] ?? 'end' ) . ': ' . count( $elements ) . ' контейнер.',
        'diff' => $diff,
        'editor_sync' => [
            'position' => $position,
            'elements' => $elements,
        ],
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
    $editor_context_input = $request->get_param( 'context' );
    $selected_element_count = is_array( $editor_context_input ) && is_array( $editor_context_input['selected_elements'] ?? null ) ? count( $editor_context_input['selected_elements'] ) : 0;
    $vision_repair = is_array( $editor_context_input ) && ! empty( $editor_context_input['vision_repair'] );
    $vision_regenerate = is_array( $editor_context_input ) && ! empty( $editor_context_input['vision_regenerate'] );
    $vision_findings = $vision_regenerate && is_array( $editor_context_input ) ? sanitize_textarea_field( substr( (string) ( $editor_context_input['vision_findings'] ?? '' ), 0, 3600 ) ) : '';
    $targeted_edit = $action_request && $selected_element_count > 0 && ! $vision_regenerate && ( $vision_repair || wpae_llm_is_targeted_edit_request( $message ) );
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
        $system_prompt .= $targeted_edit
            ? ' Это точечное изменение выбранного Elementor элемента. Верни только JSON по схеме: {"action":"patch_elements","post_id":number,"patches":[{"element_id":"selected-id","path":"settings.native_property","op":"set","value":...}]}. Меняй только явно запрошенные native properties, не трогай HTML/CSS/WebGL и не пересобирай страницу. Используй только выбранные element_id из контекста.'
            : ' Ограничения компактности action-JSON: ровно 1 корневой контейнер и 3–5 вложенных виджетов; не дублируй значения Elementor по умолчанию и не добавляй необязательные настройки.';
        if ( $vision_repair ) {
            $system_prompt .= $vision_regenerate
                ? ' Это автоматический repair-проход по замечаниям AI Vision. Перегенерируй один полноценный Elementor-блок заново по исходному запросу пользователя; не урезай композицию, не оставляй placeholder-тексты, исправь все findings и верни insert_elements. Не удаляй и не заменяй существующие блоки страницы: предыдущая неудачная версия уже откатена перед этой генерацией.'
                : ' Это автоматический repair-проход по замечаниям AI Vision. Исправь только существующие выбранные элементы по findings; сохрани корректный контент, но замени placeholder или контент, который Vision отметил как несоответствующий исходному запросу пользователя. Не добавляй, не удаляй и не заменяй блоки, не меняй HTML/CSS/WebGL. Используй только native settings properties, необходимые для устранения замечаний, и верни patch_elements.';
            if ( $vision_findings !== '' ) {
                $system_prompt .= ' Замечания Vision: ' . $vision_findings;
            }
        }
        $system_prompt .= wpae_llm_block_archetype_hint( $message );
        $system_prompt .= $targeted_edit ? ' Это запрос на выполнение точечной правки. Не пиши инструкцию и не объясняй ручные клики.' : ' Это запрос на выполнение работы. Не пиши инструкцию и не объясняй ручные клики. Верни только компактный JSON без markdown по схеме: {"action":"insert_elements","post_id":number,"position":"start|end","elements":[Elementor native Flexbox container/widget objects]}. Для этой задачи массив elements обязан содержать ровно один объект elType=container, все widget-объекты должны находиться только внутри его elements, а верхний уровень не должен содержать widget-объекты или дополнительные контейнеры. Используй 3–5 заполненных native widgets, выбранных по типу блока; heading, text-editor и button разрешены, но не обязательны, если более подходящий native widget поддерживается Elementor. Разрешена только вставка новых элементов с elType=container/widget, точным camelCase widgetType, native settings и elements arrays. Каждый container обязан содержать заполненные native widgets в своем дереве; не возвращай контейнеры без widgets. Для hero обязательно добавь полезный контент через native heading/text-editor/button widgets, а не только пустую структуру layout. Любой тип блока должен иметь сбалансированную композицию без пустых или чрезмерно широких зон и чрезмерно широких колонок: на desktop используй понятную композицию, на mobile собери ее в вертикальный stack; задай явный фон корневого контейнера, контрастный текст, видимый CTA там, где он нужен, разумные min-height/spacing и responsive units rem/em/vh/% вместо огромных px-значений. Не допускай слитого текста, гигантских пустых промежутков и элементов, которые визуально существуют только как placeholder. Не удаляй и не заменяй существующие элементы.';
        $system_prompt .= "\nАктивная дизайн-система: " . wp_json_encode( wpae_build_project_design_system(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $system_prompt .= $targeted_edit ? ' КРИТИЧЕСКОЕ ПРАВИЛО: ответом должен быть только JSON-объект patch_elements. Не возвращай URL, endpoint, пояснения или markdown.' : ' КРИТИЧЕСКОЕ ПРАВИЛО: ответом должен быть только сам JSON-объект команды insert_elements. Не возвращай URL, HTTP-запросы, названия endpoint, пояснения, markdown или текст вроде POST /wp-json/... .';
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
            $visible_text = sanitize_text_field( substr( (string) ( $element['visible_text'] ?? '' ), 0, 240 ) );
            if ( $visible_text !== '' ) {
                $selected_elements[ count( $selected_elements ) - 1 ]['visible_text'] = $visible_text;
            }
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
    $request_body = [
        'model' => $runtime['model'],
        'messages' => $messages,
        'temperature' => 0.2,
        'max_completion_tokens' => $action_request ? 8000 : 1200,
    ];
    if ( $action_request ) {
        $request_body['response_format'] = [ 'type' => 'json_object' ];
        if ( $runtime['provider'] === 'openrouter' ) {
            $request_body['provider'] = [ 'require_parameters' => true ];
        }
    }
    $remote_args = [
        'timeout' => 45,
        'redirection' => 2,
        'limit_response_size' => WPAE_LLM_MAX_RESPONSE_BYTES,
        'headers' => $headers,
        'body' => wp_json_encode( $request_body ),
    ];
    $response = wpae_llm_provider_request( $url, $remote_args, $request_body, $action_request, $runtime['provider'] );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'wpae_llm_provider_request_failed', 'LLM-провайдер недоступен.', [ 'status' => 502, 'details' => sanitize_text_field( $response->get_error_message() ), 'provider' => $runtime['provider'] ] );
    }

    $status = wp_remote_retrieve_response_code( $response );
    $raw = wp_remote_retrieve_body( $response );
    $body = json_decode( $raw, true );
    if ( $status < 200 || $status >= 300 ) {
        $provider_error = wpae_llm_provider_error_message( is_array( $body ) ? $body : [] ) ?: ( is_array( $body ) ? 'Провайдер вернул ошибку.' : 'Провайдер вернул некорректный ответ.' );
        return new WP_Error( 'wpae_llm_provider_error', 'LLM-провайдер вернул ошибку.', [ 'status' => 502, 'provider_status' => $status, 'provider_message' => sanitize_text_field( (string) $provider_error ), 'provider' => $runtime['provider'] ] );
    }

    $reply = wpae_llm_extract_response_text( is_array( $body ) ? $body : [] );
    if ( $reply === '' ) {
        $diagnostics = wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] );
        $provider_message = $diagnostics['provider_message'] ?: ( $diagnostics['finish_reason'] === 'error' ? 'Провайдер завершил генерацию с ошибкой без дополнительного сообщения.' : '' );
        if ( $provider_message !== '' || $diagnostics['finish_reason'] === 'error' ) {
            return new WP_Error( 'wpae_llm_provider_error', 'LLM-провайдер вернул ошибку.', [
                'status' => 502,
                'provider_status' => $status,
                'provider_message' => $provider_message,
                'provider_error_code' => $diagnostics['provider_error_code'],
                'finish_reason' => $diagnostics['finish_reason'],
                'provider' => $runtime['provider'],
                'details' => $diagnostics,
            ] );
        }
        return new WP_Error( 'wpae_llm_empty_response', 'LLM-провайдер вернул пустой ответ. Проверьте модель и лимит токенов.', [
            'status' => 502,
            'provider' => $runtime['provider'],
            'details' => $diagnostics,
        ] );
    }
    if ( $action_request ) {
        $post_id = is_array( $context ?? null ) ? absint( $context['post_id'] ?? 0 ) : 0;
        $action = wpae_llm_decode_action( $reply, $post_id );
        $action_diagnostics = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : [];
        $action_diagnostics['decoded_action'] = sanitize_key( (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' ) );
        $action_diagnostics['decoded_post_id'] = absint( $action['post_id'] ?? 0 );
        $action_diagnostics['decoded_element_count'] = is_array( $action['elements'] ?? null ) ? count( $action['elements'] ) : 0;
        if ( $targeted_edit ) {
            $selected_element_ids = array_values( array_filter( array_map( static fn( $item ) => is_array( $item ) ? sanitize_key( (string) ( $item['id'] ?? $item['element_id'] ?? '' ) ) : sanitize_key( (string) $item ), (array) ( $editor_context_input['selected_elements'] ?? [] ) ) ) );
            $patch_execution = wpae_llm_execute_patch_action( $action, $post_id, $selected_element_ids );
            $patch_execution['steps'] = array_merge(
                [ [ 'id' => 'guided_context', 'status' => 'ok', 'message' => 'Загружены guide, skills и контекст выбранного Elementor элемента.', 'details' => [ 'guide_version' => WPAE_GUIDE_VERSION, 'custom_skills_count' => count( $guided_context['custom_skills'] ?? [] ), 'selected_element_count' => $selected_element_count ] ] ],
                [ [ 'id' => 'command_decode', 'status' => ! empty( $action_diagnostics['json_decoded'] ) ? 'ok' : 'failed', 'message' => ! empty( $action_diagnostics['json_decoded'] ) ? 'Ответ разобран как patch-команда.' : 'Ответ не разобран как patch-команда.', 'details' => $action_diagnostics ] ],
                (array) ( $patch_execution['steps'] ?? [] )
            );
            if ( empty( $patch_execution['ok'] ) ) {
                return new WP_Error( 'wpae_llm_action_failed', 'LLM не выполнил точечную правку в Elementor.', [ 'status' => 422, 'details' => $patch_execution ] );
            }
            return new WP_REST_Response( [ 'ok' => true, 'message' => 'Точечная правка выполнена через Elementor. Изменено свойств: ' . (int) $patch_execution['changed_count'] . '.', 'operation_id' => $patch_execution['operation_id'] ?? null, 'action' => $patch_execution['action'], 'write' => $patch_execution, 'steps' => $patch_execution['steps'], 'provider' => $runtime['provider'], 'model' => $runtime['model'] ], 200 );
        }
        $action_repair = false;
        $action_fallback = false;
        $decoded_action = (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' );
        $decoded_elements = is_array( $action['elements'] ?? null ) ? $action['elements'] : [];
        $decoded_widget_count = wpae_llm_count_widgets( $decoded_elements );
        $decoded_shape = wpae_llm_validate_action_shape( $action, $post_id );
        $decoded_content_fidelity = wpae_llm_content_fidelity( $message, $decoded_elements );
        if ( empty( $decoded_shape['ok'] ) || count( $decoded_elements ) > 12 || $decoded_widget_count < 1 || empty( $decoded_content_fidelity['ok'] ) ) {
            $repair_error = '';
            $repair_messages = [
                [ 'role' => 'system', 'content' => 'Исправь Elementor action JSON. Верни только JSON без markdown и текста. Нужен ровно один верхнеуровневый elType=container с 3–5 заполненными native widget descendants. Используй именно post_id ' . (string) $post_id . '. ' . wpae_llm_block_archetype_hint( $message ) . ' Сгенерируй осмысленный русский контент под запрос пользователя «' . sanitize_text_field( $message ) . '», а не служебные заглушки. Используй минимум три подходящих заполненных native widgets; для специального типа предпочти соответствующий widget (icon-list, accordion, price-list, testimonial, image или divider), а если он недоступен или требует неподдерживаемой структуры, используй заполненные heading/text-editor/button с содержанием именно этого типа, а не общий текст о преимуществах. Не используй тексты «Заголовок блока», «Короткое описание результата для клиента», «Текст заголовка» или другие placeholder-фразы. У heading не может быть пустым settings.title, у text-editor settings.editor, у button settings.text или settings.link.url; для общего CTA fallback допустим текст «Обсудить проект», но специальный блок должен сохранить содержание своего типа. Не возвращай пустые контейнеры, плоские виджеты, дополнительные верхнеуровневые элементы, REST-маршруты или пояснения. Схема: {"action":"insert_elements","post_id":' . (string) $post_id . ',"position":"end","elements":[container]}.' ],
                [ 'role' => 'user', 'content' => $message ],
            ];
            for ( $repair_attempt = 1; $repair_attempt <= 2 && ! $action_repair; $repair_attempt++ ) {
                $repair_body = $request_body;
                $repair_body['messages'] = $repair_messages;
                $repair_response = wpae_llm_provider_request( $url, $remote_args, $repair_body, true, $runtime['provider'] );
                if ( is_wp_error( $repair_response ) ) {
                    $repair_error = sanitize_text_field( $repair_response->get_error_message() );
                    continue;
                }
                $repair_status = wp_remote_retrieve_response_code( $repair_response );
                $repair_payload = json_decode( wp_remote_retrieve_body( $repair_response ), true );
                if ( $repair_status < 200 || $repair_status >= 300 ) {
                    $repair_error = 'Repair HTTP ' . $repair_status . '.';
                    continue;
                }
                $repair_reply = wpae_llm_extract_response_text( is_array( $repair_payload ) ? $repair_payload : [] );
                if ( $repair_reply === '' ) {
                    $repair_error = 'Repair-проход вернул пустой ответ.';
                    continue;
                }
                $candidate = wpae_llm_decode_action( $repair_reply, $post_id );
                $candidate_elements = is_array( $candidate['elements'] ?? null ) ? $candidate['elements'] : [];
                $candidate_action = (string) ( $candidate['action'] ?? $candidate['type'] ?? $candidate['command'] ?? '' );
                $candidate_post_id = absint( $candidate['post_id'] ?? 0 );
                $candidate_widget_count = wpae_llm_count_widgets( $candidate_elements );
                $candidate_shape = wpae_llm_validate_action_shape( $candidate, $post_id );
                $candidate_content_fidelity = wpae_llm_content_fidelity( $message, $candidate_elements );
                if ( empty( $candidate_shape['ok'] ) || $candidate_action !== 'insert_elements' || $candidate_post_id !== $post_id || count( $candidate_elements ) > 12 || $candidate_widget_count < 1 || empty( $candidate_content_fidelity['ok'] ) ) {
                    $repair_error = 'Repair-проход вернул неподдерживаемую или пустую Elementor-команду.';
                    continue;
                }
                $reply = $repair_reply;
                $body = $repair_payload;
                $action = $candidate;
                $action_diagnostics = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : [];
                $action_diagnostics['decoded_action'] = sanitize_key( $candidate_action );
                $action_diagnostics['decoded_post_id'] = $candidate_post_id;
                $action_diagnostics['decoded_element_count'] = count( $candidate_elements );
                $action_repair = true;
            }
        }
        if ( ! $action_repair ) {
            $action = wpae_llm_build_fallback_action( $message, $post_id );
            $action_diagnostics = [
                'response_type' => 'deterministic_fallback',
                'json_decoded' => true,
                'response_keys' => [ 'action', 'post_id', 'position', 'elements' ],
                'fallback_archetype' => wpae_llm_detect_block_archetype( $message ),
                'fallback_reason' => 'Provider and bounded repair response did not contain a usable native widget tree.',
            ];
            $action_fallback = true;
        }
        $typography_changed = 0;
        $bento_changed = 0;
        $action_archetype = wpae_llm_detect_block_archetype( $message );
        $fallback_content_changed = 0;
        if ( $action_fallback ) {
            $fallback_fidelity = wpae_llm_content_fidelity( $message, (array) $action['elements'] );
            $missing_content = (array) ( $fallback_fidelity['missing'] ?? [] );
            if ( ! empty( $missing_content ) ) {
                wpae_llm_apply_fallback_content( $action['elements'], $missing_content, $action_archetype, $fallback_content_changed );
            }
            wpae_llm_apply_fallback_archetype_content( $action['elements'], $message, $action_archetype, $fallback_content_changed );
        }
        if ( is_array( $action['elements'] ?? null ) ) {
            $action['elements'] = wpae_llm_normalize_generated_typography( $action['elements'], $action_archetype, 0, $typography_changed );
            $action['elements'] = wpae_llm_apply_bento_layout( $action['elements'], $action_archetype, $bento_changed );
        }
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
        $content_fidelity = wpae_llm_content_fidelity( $message, (array) ( $action['elements'] ?? [] ) );
        $action_steps[] = [
            'id' => 'content_fidelity',
            'status' => ! empty( $content_fidelity['ok'] ) ? 'ok' : 'failed',
            'message' => ! empty( $content_fidelity['ok'] ) ? 'Явно заданный контент пользователя сохранен в native Elementor widgets.' : 'Команда не содержит весь явно заданный контент пользователя.',
            'details' => $content_fidelity,
        ];
        if ( empty( $content_fidelity['ok'] ) ) {
            return new WP_Error( 'wpae_llm_content_mismatch', 'LLM-команда отклонена: сгенерированный контент не соответствует запросу.', [ 'status' => 422, 'details' => [ 'content_fidelity' => $content_fidelity, 'action' => $action_diagnostics ] ] );
        }
        if ( $action_fallback ) {
            $action_steps[] = [ 'id' => 'action_fallback', 'status' => 'ok', 'message' => 'Провайдер не вернул пригодное дерево; создан тематический native Elementor fallback с контентом запроса.', 'details' => array_merge( $action_diagnostics, [ 'content_changed' => $fallback_content_changed ] ) ];
        } elseif ( $action_repair ) {
            $action_steps[] = [ 'id' => 'action_repair', 'status' => 'ok', 'message' => 'Нарушенная JSON-команда была повторно запрошена и разобрана после repair-прохода.' ];
        } elseif ( isset( $repair_error ) && $repair_error !== '' ) {
            $action_steps[] = [ 'id' => 'action_repair', 'status' => 'failed', 'message' => 'Repair-проход не вернул пригодную Elementor-команду.', 'details' => [ 'attempts' => 2, 'error' => $repair_error ] ];
        }
        if ( $typography_changed > 0 ) {
            $action_steps[] = [ 'id' => 'typography_guard', 'status' => 'ok', 'message' => 'Семантическая типографика повторяющегося блока нормализована через native Elementor settings.', 'details' => [ 'archetype' => $action_archetype, 'author_headings_to_h5' => $typography_changed ] ];
        }
        if ( $bento_changed > 0 ) {
            $action_steps[] = [ 'id' => 'bento_layout', 'status' => 'ok', 'message' => 'Повторяющиеся native-контейнеры приведены к bento-сетке с переносом и responsive-размерами.', 'details' => [ 'archetype' => $action_archetype, 'max_items_per_row' => 4, 'containers_updated' => $bento_changed ] ];
        }
        $execution = wpae_llm_execute_action( $action, $post_id );
        $execution['steps'] = array_merge( $action_steps, is_array( $execution['steps'] ?? null ) ? $execution['steps'] : [] );
        if ( empty( $execution['ok'] ) ) {
            return new WP_Error( 'wpae_llm_action_failed', 'LLM не выполнил задачу в Elementor.', [ 'status' => 422, 'details' => $execution ] );
        }
        return new WP_REST_Response( [ 'ok' => true, 'message' => 'Задача выполнена через Elementor. Вставлено элементов: ' . (int) $execution['inserted_count'] . '.', 'operation_id' => $execution['operation_id'] ?? null, 'action' => $execution['action'], 'write' => $execution, 'steps' => $execution['steps'], 'provider' => $runtime['provider'], 'model' => $runtime['model'] ], 200 );
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

function wpae_llm_undo( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $snapshot_id = sanitize_text_field( (string) $request->get_param( 'rollback_snapshot_id' ) );
    if ( $post_id <= 0 || $snapshot_id === '' || ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Недостаточно прав или параметров для отмены операции.' ], 403 );
    }
    $snapshots = function_exists( 'wpae_get_rollback_snapshots' ) ? wpae_get_rollback_snapshots() : [];
    $snapshot = is_array( $snapshots[ $snapshot_id ] ?? null ) ? $snapshots[ $snapshot_id ] : null;
    $snapshot_posts = array_map( 'absint', array_keys( (array) ( $snapshot['posts'] ?? [] ) ) );
    if ( $snapshot === null || ! in_array( $post_id, $snapshot_posts, true ) || count( $snapshot_posts ) !== 1 ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Снимок отмены не найден или не относится к этой странице.', 'code' => 'wpae_undo_scope_mismatch' ], 404 );
    }
    $rollback = wpae_restore_rollback_snapshot_by_id( $snapshot_id, true );
    return new WP_REST_Response( [ 'ok' => ! empty( $rollback['ok'] ), 'operation_id' => wpae_llm_new_operation_id(), 'rollback' => $rollback ], (int) ( $rollback['status'] ?? 422 ) );
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
            'context' => 'Optional bounded editor context with post_id and selected element ids/types. When a selected element and a property-edit request are present, the chat returns patch_elements instead of rebuilding the page.',
        ],
        'safety' => 'Normal chat is advisory. Explicit action requests may insert only one populated root Flexbox container or apply a bounded native patch to selected elements when elementor_writes is enabled. The plugin runs a dry-run preview before writing and routes the write through update, preflight, protected-zone, visual-regression, and rollback checks. Delete and replace actions are not supported. If the provider returns tutorial text instead of the required action JSON, the write is rejected.',
        'guided_editor_mode' => 'The floating Elementor editor chat injects the current guide, enabled custom skills, capabilities, and project design system into action requests. It uses the same internal Elementor validation/update pipeline without exposing the site API key to browser JavaScript.',
        'editor_preview_sync' => 'After a successful action write, the floating chat first synchronizes new models through the open Elementor editor API and then refreshes the preview when needed. The response reports the sync mode and changed ids; it does not claim realtime success without a canvas check.',
        'action_content_gate' => 'An explicit insert action is rejected unless it contains exactly one populated root container with at least one native Elementor widget. A targeted patch is rejected unless it has a current post_id, bounded patches, existing selected element ids, and native editable paths.',
        'preview_and_undo' => 'Every successful write returns operation_id, compact diff, rollback_snapshot_id, and rollback expiry. The editor chat exposes one-click undo through POST /wp-json/ai-executor/v1/llm/undo, scoped to the current post and authenticated editor.',
        'execution_trace' => 'Action and advisory responses include a safe operational steps array for the chat UI and JSON log: provider response, command decoding, validation, preview, native normalization, design-system mapping, page context, Elementor update, sync, Vision review, and final status. It never contains hidden reasoning, credentials, prompts, raw page payloads or raw provider responses.',
        'editor_vision_review' => 'When ai_vision is enabled and configured, the floating Elementor chat captures the refreshed preview and sends it to /llm/vision-review together with the original user brief and a bounded visible-text excerpt. Vision must check content_fidelity as well as visual quality. The editor-chat review is advisory and never rolls back a successful write from subjective screenshot findings; strict rollback remains available through transaction_vision_review. Screenshot or provider failures are reported without undoing the editor write.',
        'content_fidelity' => 'Explicit content from the user request must survive into native Elementor widgets. The server extracts explicit quoted phrases, checks the action tree before write, repairs deterministic fallback content when possible, and rejects the write with a missing-content list when fidelity cannot be proven.',
        'provider_rate_limit' => [ 'calls' => WPAE_LLM_CALL_LIMIT, 'window_seconds' => WPAE_LLM_CALL_WINDOW, 'scope' => 'site-wide' ],
        'privacy' => 'Provider keys are encrypted in wp_options. Prompts, histories, and raw provider responses are not stored or logged.',
    ];
}
