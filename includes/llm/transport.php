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
const WPAE_LLM_ACTION_TIMEOUT_SECONDS = 120;
const WPAE_LLM_MAX_HISTORY_ITEMS = 12;
const WPAE_LLM_MAX_RESPONSE_BYTES = 262144;
const WPAE_LLM_ACTION_MAX_COMPLETION_TOKENS = 12000;

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
            'model' => 'deepseek-v4-flash',
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
    if ( $provider === 'deepseek' ) {
        return [
            'deepseek-v4-pro' => 'DeepSeek V4 Pro — сложные агентские задачи',
            'deepseek-v4-flash' => 'DeepSeek V4 Flash — быстрый универсальный режим',
            'deepseek-v4-flash-vision-exp' => 'DeepSeek V4 Flash Vision Exp — vision-задачи',
        ];
    }

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
    $model_options = wpae_llm_provider_model_options( $provider );
    if ( ! empty( $model_options ) && ! isset( $model_options[ $model ] ) ) {
        $model = $providers[ $provider ]['model'];
    }

    $fallback_model = sanitize_text_field( (string) ( $stored['fallback_model'] ?? '' ) );
    if ( ! empty( $model_options ) && $fallback_model !== '' && ! isset( $model_options[ $fallback_model ] ) ) {
        $fallback_model = '';
    }
	$design_engine_mode = sanitize_key( (string) ( $stored['design_engine_mode'] ?? 'off' ) );
	if ( ! in_array( $design_engine_mode, [ 'off', 'shadow', 'active' ], true ) ) {
		$design_engine_mode = 'off';
	}
	$design_pipeline_mode = sanitize_key( (string) ( $stored['design_pipeline_mode'] ?? 'off' ) );
	if ( ! in_array( $design_pipeline_mode, [ 'off', 'shadow', 'active' ], true ) ) {
		$design_pipeline_mode = 'off';
	}

    $api_key = wpae_vision_decrypt_api_key( (string) ( $stored['api_key_encrypted'] ?? '' ) );
    return [
        'provider' => $provider,
        'provider_label' => $providers[ $provider ]['label'],
        'base_url' => $base_url,
        'model' => $model,
        'fallback_model' => $fallback_model,
		'fallback_model_history' => wpae_llm_fallback_model_history( $stored ),
		'design_engine_mode' => $design_engine_mode,
		'design_pipeline_mode' => $design_pipeline_mode,
        'has_api_key' => $api_key !== '',
        'api_key_hint' => $api_key !== '' ? 'Ключ сохранен' : 'Ключ не задан',
        'updated_at' => sanitize_text_field( (string) ( $stored['updated_at'] ?? '' ) ),
    ];
}

function wpae_llm_fallback_model_history( array $stored ): array {
    $history = [];
    $current = trim( (string) ( $stored['fallback_model'] ?? '' ) );
    if ( $current !== '' ) {
        $history[] = $current;
    }
    foreach ( is_array( $stored['fallback_model_history'] ?? null ) ? $stored['fallback_model_history'] : [] as $entry ) {
        $entry = trim( sanitize_text_field( (string) $entry ) );
        if ( $entry !== '' && ! in_array( $entry, $history, true ) ) {
            $history[] = $entry;
        }
    }
    return array_slice( $history, 0, 10 );
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
    $model_options = wpae_llm_provider_model_options( $provider );
    if ( ! empty( $model_options ) && ! isset( $model_options[ $model ] ) ) {
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
    // block a whole test or content session. Previously entered ids stay
    // available in the dashboard dropdown for quick switching.
    $fallback_model = substr( sanitize_text_field( (string) ( $input['fallback_model'] ?? '' ) ), 0, 120 );
    if ( ! empty( $model_options ) && $fallback_model !== '' && ! isset( $model_options[ $fallback_model ] ) ) {
        $fallback_model = '';
    }
    $stored['fallback_model'] = $fallback_model;
	$design_engine_mode = sanitize_key( (string) ( $input['design_engine_mode'] ?? ( $stored['design_engine_mode'] ?? 'off' ) ) );
	if ( ! in_array( $design_engine_mode, [ 'off', 'shadow', 'active' ], true ) ) {
		$design_engine_mode = 'off';
	}
	$stored['design_engine_mode'] = $design_engine_mode;
	$design_pipeline_mode = sanitize_key( (string) ( $input['design_pipeline_mode'] ?? ( $stored['design_pipeline_mode'] ?? 'off' ) ) );
	if ( ! in_array( $design_pipeline_mode, [ 'off', 'shadow', 'active' ], true ) ) {
		$design_pipeline_mode = 'off';
	}
	$stored['design_pipeline_mode'] = $design_pipeline_mode;
    $history = is_array( $stored['fallback_model_history'] ?? null ) ? $stored['fallback_model_history'] : [];
    if ( $fallback_model !== '' && ! in_array( $fallback_model, $history, true ) ) {
        array_unshift( $history, $fallback_model );
        $stored['fallback_model_history'] = array_slice( $history, 0, 10 );
    }
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
    $fallback_model = trim( (string) ( wpae_llm_get_settings()['fallback_model'] ?? '' ) );
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

function wpae_llm_provider_error_message( $body, array $request_body = [] ): string {
    if ( is_string( $body ) ) {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return wpae_llm_diagnostic_text( $body, 300, $request_body );
        }
        $body = $decoded;
    }
    if ( ! is_array( $body ) ) {
        return '';
    }

    $choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : [];
    $error = is_array( $body['error'] ?? null ) ? $body['error'] : ( is_array( $choice['error'] ?? null ) ? $choice['error'] : [] );
    foreach ( [ 'message', 'detail', 'description' ] as $key ) {
        $candidate = $error[ $key ] ?? null;
        if ( is_scalar( $candidate ) && trim( (string) $candidate ) !== '' ) {
            return wpae_llm_diagnostic_text( $candidate, 300, $request_body );
        }
    }
    return '';
}

function wpae_llm_provider_error_fallback( $body, int $status ): string {
    if ( ! is_array( $body ) ) {
        return 'HTTP ' . $status . '; провайдер не вернул JSON-объект с диагностикой.';
    }
    $keys = [];
    foreach ( array_slice( array_keys( $body ), 0, 8 ) as $key ) {
        $key = sanitize_key( (string) $key );
        if ( $key !== '' ) {
            $keys[] = $key;
        }
    }
    return 'HTTP ' . $status . '; провайдер не передал понятного сообщения' . ( ! empty( $keys ) ? ' (поля: ' . implode( ', ', $keys ) . ')' : '' ) . '.';
}

function wpae_llm_response_diagnostics( $body, array $request_body = [] ): array {
    $choices = is_array( $body['choices'] ?? null ) ? $body['choices'] : [];
    $choice = is_array( $choices[0] ?? null ) ? $choices[0] : [];
    $message = is_array( $choice['message'] ?? null ) ? $choice['message'] : [];
    $content = $message['content'] ?? ( $choice['text'] ?? ( $body['output_text'] ?? null ) );
    $finish_reason = is_scalar( $choice['finish_reason'] ?? null ) && trim( (string) $choice['finish_reason'] ) !== ''
        ? sanitize_key( (string) $choice['finish_reason'] )
        : null;
    $content_text = is_string( $content ) ? $content : '';
    $usage = is_array( $body['usage'] ?? null ) ? $body['usage'] : [];
    $input_tokens = $usage['prompt_tokens'] ?? ( $usage['input_tokens'] ?? null );
    $output_tokens = $usage['completion_tokens'] ?? ( $usage['output_tokens'] ?? null );
    $total_tokens = $usage['total_tokens'] ?? null;
    $completion_details = is_array( $usage['completion_tokens_details'] ?? null ) ? $usage['completion_tokens_details'] : [];
    $reasoning_tokens = $usage['reasoning_tokens'] ?? ( $completion_details['reasoning_tokens'] ?? null );
    $returned_model = $body['model'] ?? null;
    $provider_name = $body['provider_name'] ?? ( $body['provider'] ?? null );
    $choice_error = is_array( $choice['error'] ?? null ) ? $choice['error'] : [];
    $error = is_array( $body['error'] ?? null ) ? $body['error'] : $choice_error;
    $error_metadata = is_array( $error['metadata'] ?? null ) ? $error['metadata'] : [];
    $raw_error = [];
    $raw_error_metadata = [];
    $raw_provider_message = null;
    $raw = $error_metadata['raw'] ?? null;
    if ( is_string( $raw ) && strlen( $raw ) <= 8192 ) {
        $decoded_raw = json_decode( $raw, true );
        if ( is_array( $decoded_raw ) ) {
            $raw_error = is_array( $decoded_raw['error'] ?? null ) ? $decoded_raw['error'] : $decoded_raw;
            $raw_error_metadata = is_array( $raw_error['metadata'] ?? null ) ? $raw_error['metadata'] : [];
            foreach ( [ 'message', 'detail', 'description' ] as $raw_message_key ) {
                if ( is_scalar( $raw_error[ $raw_message_key ] ?? null ) && trim( (string) $raw_error[ $raw_message_key ] ) !== '' ) {
                    $raw_provider_message = $raw_error[ $raw_message_key ];
                    break;
                }
            }
        }
    }
    $has_error_envelope = array_key_exists( 'error', $body ) || array_key_exists( 'error', $choice );
    if ( ! is_scalar( $returned_model ) ) { $returned_model = null; }
    if ( ! is_scalar( $provider_name ) ) { $provider_name = null; }
    if ( $provider_name === null && is_scalar( $error_metadata['provider_name'] ?? null ) ) { $provider_name = $error_metadata['provider_name']; }
    $provider_error_code = $error_metadata['provider_code'] ?? ( $error['provider_code'] ?? ( $raw_error_metadata['provider_code'] ?? ( $raw_error['provider_code'] ?? ( $raw_error['code'] ?? null ) ) ) );
    $provider_error_type = $error_metadata['error_type'] ?? ( $error['error_type'] ?? ( $error['type'] ?? ( $raw_error_metadata['error_type'] ?? ( $raw_error['error_type'] ?? ( $raw_error['type'] ?? null ) ) ) ) );
    $error_code = $error['code'] ?? null;
    $provider_error_param = $error['param'] ?? ( $error_metadata['param'] ?? ( $raw_error['param'] ?? ( $raw_error_metadata['param'] ?? null ) ) );
    $provider_error_path = $error['path'] ?? ( $error_metadata['path'] ?? ( $raw_error['path'] ?? ( $raw_error_metadata['path'] ?? null ) ) );
    if ( is_array( $provider_error_path ) ) {
        $provider_error_path = implode( '.', array_map( static fn( $part ): string => sanitize_key( (string) $part ), $provider_error_path ) );
    }
    $safe_scalar = static function ( $value, int $limit = 120 ) use ( $request_body ) {
        return is_scalar( $value ) && trim( (string) $value ) !== '' ? wpae_llm_diagnostic_text( $value, $limit, $request_body ) : null;
    };
    $provider_message = $has_error_envelope ? wpae_llm_provider_error_message( $body, $request_body ) : '';
    if ( $raw_provider_message !== null && ( $provider_message === '' || stripos( $provider_message, 'provider returned error' ) !== false ) ) {
        $provider_message = wpae_llm_diagnostic_text( $raw_provider_message, 300, $request_body );
    }
    return [
        'choices_count' => count( $choices ),
        'finish_reason' => $finish_reason,
        'content_length' => strlen( $content_text ),
        'likely_truncated' => in_array( strtolower( (string) $finish_reason ), [ 'length', 'max_tokens', 'token_limit' ], true ),
        'content_type' => is_array( $content ) ? 'array' : gettype( $content ),
        'has_reasoning' => ! empty( $message['reasoning'] ?? $choice['reasoning'] ?? false ),
        'returned_model' => $safe_scalar( $returned_model ),
        'provider_name' => $safe_scalar( $provider_name ),
        'has_refusal' => is_string( $message['refusal'] ?? null ) && trim( $message['refusal'] ) !== '',
        'has_error_envelope' => $has_error_envelope,
        'error_code' => $safe_scalar( $error_code, 80 ),
        'provider_error_code' => $safe_scalar( $provider_error_code, 120 ),
        'provider_error_type' => $safe_scalar( $provider_error_type, 120 ),
        'provider_error_param' => $safe_scalar( $provider_error_param, 180 ),
        'provider_error_path' => $safe_scalar( $provider_error_path, 240 ),
        'provider_message' => $provider_message !== '' ? $provider_message : null,
        'usage' => [
            'input_tokens' => is_numeric( $input_tokens ) ? max( 0, (int) $input_tokens ) : null,
            'output_tokens' => is_numeric( $output_tokens ) ? max( 0, (int) $output_tokens ) : null,
            'total_tokens' => is_numeric( $total_tokens ) ? max( 0, (int) $total_tokens ) : null,
            'reasoning_tokens' => is_numeric( $reasoning_tokens ) ? max( 0, (int) $reasoning_tokens ) : null,
            'known' => is_numeric( $input_tokens ) || is_numeric( $output_tokens ) || is_numeric( $total_tokens ) || is_numeric( $reasoning_tokens ),
        ],
    ];
}

function wpae_llm_diagnostic_redact_request_echo( string $text, array $request_body ): string {
    $needles = [];
    $collect = static function ( $value ) use ( &$collect, &$needles ): void {
        if ( is_string( $value ) ) {
            if ( strlen( $value ) >= 20 ) {
                $needles[] = $value;
                foreach ( preg_split( '/(?<=[.!?;])\s+/u', $value ) ?: [] as $sentence ) {
                    if ( strlen( $sentence ) >= 20 ) { $needles[] = $sentence; }
                }
            }
            return;
        }
        if ( is_array( $value ) ) {
            foreach ( $value as $nested ) { $collect( $nested ); }
        }
    };
    foreach ( (array) ( $request_body['messages'] ?? [] ) as $message ) {
        if ( ! is_array( $message ) || ( $message['role'] ?? '' ) !== 'user' ) { continue; }
        $user_content = $message['content'] ?? null;
        if ( is_string( $user_content ) ) {
            $needles[] = $user_content;
            $decoded = json_decode( $user_content, true );
            if ( is_array( $decoded ) ) { $collect( $decoded ); }
        } else {
            $collect( $user_content );
        }
    }
    usort( $needles, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
    foreach ( array_unique( $needles ) as $needle ) {
        if ( $needle !== '' ) { $text = str_replace( $needle, '[request text redacted]', $text ); }
    }
    return $text;
}

function wpae_llm_diagnostic_truncate_utf8( string $text, int $limit ): string {
    $limit = max( 0, $limit );
    if ( function_exists( 'wp_check_invalid_utf8' ) ) { $text = wp_check_invalid_utf8( $text, true ); }
    if ( function_exists( 'mb_strcut' ) ) { return mb_strcut( $text, 0, $limit, 'UTF-8' ); }
    $text = substr( $text, 0, $limit );
    while ( $text !== '' && preg_match( '//u', $text ) !== 1 ) { $text = substr( $text, 0, -1 ); }
    return $text;
}

function wpae_llm_diagnostic_text( $value, int $limit = 300, array $request_body = [] ): string {
    if ( ! is_scalar( $value ) ) { return ''; }
    $text = (string) $value;
    if ( function_exists( 'wp_check_invalid_utf8' ) ) { $text = wp_check_invalid_utf8( $text, true ); }
    $text = sanitize_text_field( $text );
    $text = wpae_llm_diagnostic_redact_request_echo( $text, $request_body );
    $redacted = preg_replace( '/\b(Bearer|Basic)\s+[^\s,;]+/iu', '$1 [redacted]', $text );
    $text = is_string( $redacted ) ? $redacted : $text;
    $redacted = preg_replace( '/\b(?:sk-or-v1-|sk-|or-v1-)[A-Za-z0-9._-]{8,}\b/i', '[redacted-key]', $text );
    $text = is_string( $redacted ) ? $redacted : $text;
    $redacted = preg_replace( '/\b(authorization|cookie|api[_ -]?key|token)\s*[:=]\s*[^\s,;]+/iu', '$1=[redacted]', $text );
    $text = is_string( $redacted ) ? $redacted : $text;
    $redacted = preg_replace( '/https?:\/\/[^\s?]+\?[^\s]*/iu', '[URL query redacted]', $text );
    $text = is_string( $redacted ) ? $redacted : $text;
    return wpae_llm_diagnostic_truncate_utf8( $text, $limit );
}

function wpae_llm_diagnostic_endpoint( string $url ): ?string {
    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
        return null;
    }
    $scheme = sanitize_key( (string) ( $parts['scheme'] ?? '' ) );
    $host = sanitize_text_field( (string) $parts['host'] );
    $path = preg_replace( '/[^A-Za-z0-9._~\/-]/', '', (string) ( $parts['path'] ?? '/' ) );
    return ( $scheme !== '' ? $scheme . '://' : '' ) . $host . ( is_string( $path ) && $path !== '' ? $path : '/' );
}

function wpae_llm_diagnostic_http_status( $status ): ?int {
    if ( ! is_numeric( $status ) ) {
        return null;
    }
    $status = (int) $status;
    return $status > 0 ? $status : null;
}

function wpae_llm_build_request_diagnostics( string $url, array $remote_args, array $request_body, string $provider, string $model, string $attempt, bool $action_request, ?int $status = null, $raw = '', $body = null, $transport_error = null ): array {
    $request_body = wpae_llm_prepare_provider_request_body( $request_body, $action_request, $provider );
    $headers = [];
    foreach ( (array) ( $remote_args['headers'] ?? [] ) as $name => $value ) {
        $headers[ strtolower( (string) $name ) ] = trim( (string) $value );
    }
    $body_keys = [];
    foreach ( array_slice( array_keys( $request_body ), 0, 16 ) as $key ) {
        $key = sanitize_key( (string) $key );
        if ( $key !== '' ) {
            $body_keys[] = $key;
        }
    }

    $raw = (string) $raw;
    if ( ! is_array( $body ) && trim( $raw ) !== '' ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            $body = $decoded;
        }
    }
    $top_level_keys = [];
    $body_type = trim( $raw ) === '' ? 'empty' : 'text';
    if ( is_array( $body ) ) {
        $body_type = count( $body ) === 0 || array_keys( $body ) === range( 0, count( $body ) - 1 ) ? 'json_list' : 'json_object';
        foreach ( array_slice( array_keys( $body ), 0, 16 ) as $key ) {
            $key = sanitize_key( (string) $key );
            if ( $key !== '' ) {
                $top_level_keys[] = $key;
            }
        }
    }

    $response = [
        'http_status' => wpae_llm_diagnostic_http_status( $status ),
        'body_type' => $body_type,
        'body_bytes' => strlen( $raw ),
        'top_level_keys' => $top_level_keys,
    ];
    if ( is_array( $body ) ) {
        $response_details = wpae_llm_response_diagnostics( $body, $request_body );
        $response['choices_count'] = (int) ( $response_details['choices_count'] ?? 0 );
        $response['finish_reason'] = is_string( $response_details['finish_reason'] ?? null ) ? $response_details['finish_reason'] : null;
        $response['provider_error_code'] = isset( $response_details['provider_error_code'] ) ? wpae_llm_diagnostic_text( $response_details['provider_error_code'] ) : null;
        $response['provider_message'] = $response_details['provider_message'] ?? null;
        foreach ( [ 'has_error_envelope', 'error_code', 'provider_error_type', 'provider_error_param', 'provider_error_path', 'provider_name' ] as $diagnostic_key ) { $response[ $diagnostic_key ] = $response_details[ $diagnostic_key ] ?? null; }
    }

    $diagnostics = [
        'schema' => 'wpae-llm-provider-diagnostics-v1',
        'attempt' => sanitize_key( $attempt ),
        'provider' => sanitize_key( $provider ),
        'model' => wpae_llm_diagnostic_text( $model, 120 ),
        'endpoint' => wpae_llm_diagnostic_endpoint( $url ),
        'api_endpoint' => wpae_llm_diagnostic_endpoint( $url ),
        'timeout_seconds' => max( 0, (int) ( $remote_args['timeout'] ?? 0 ) ),
        'request' => [
            'body_keys' => $body_keys,
            'message_count' => is_array( $request_body['messages'] ?? null ) ? count( $request_body['messages'] ) : 0,
            'has_response_format' => array_key_exists( 'response_format', $request_body ),
            'has_provider_parameters' => array_key_exists( 'provider', $request_body ),
            'response_format' => is_array( $request_body['response_format'] ?? null ) ? sanitize_key( (string) ( $request_body['response_format']['type'] ?? '' ) ) : null,
            'response_format_strict' => is_array( $request_body['response_format']['json_schema'] ?? null ) ? ( $request_body['response_format']['json_schema']['strict'] ?? null ) : null,
            'require_parameters' => is_array( $request_body['provider'] ?? null ) ? ( $request_body['provider']['require_parameters'] ?? null ) : null,
            'schema_sha256' => isset( $request_body['response_format']['json_schema']['schema'] ) ? hash( 'sha256', (string) wp_json_encode( $request_body['response_format']['json_schema']['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) : null,
            'schema_bytes' => isset( $request_body['response_format']['json_schema']['schema'] ) ? strlen( (string) wp_json_encode( $request_body['response_format']['json_schema']['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) : null,
            'max_tokens' => isset( $request_body['max_tokens'] ) ? (int) $request_body['max_tokens'] : ( isset( $request_body['max_completion_tokens'] ) ? (int) $request_body['max_completion_tokens'] : 0 ),
            'headers' => [
                'authorization_header_present' => array_key_exists( 'authorization', $headers ),
                'authorization_value_nonempty' => ! empty( $headers['authorization'] ),
                'content_type_present' => array_key_exists( 'content-type', $headers ),
                'http_referer_present' => array_key_exists( 'http-referer', $headers ),
                'x_title_present' => array_key_exists( 'x-title', $headers ),
            ],
            'openrouter_schema_retry_possible' => $provider === 'openrouter' && $action_request,
        ],
        'response' => $response,
    ];
    if ( is_wp_error( $transport_error ) ) {
        $diagnostics['transport'] = [
            'error_code' => sanitize_key( (string) $transport_error->get_error_code() ),
            'error_message' => wpae_llm_diagnostic_text( $transport_error->get_error_message(), 300, $request_body ),
        ];
    }
    return $diagnostics;
}

/** Remove only the unsupported OpenRouter wire keyword; the canonical validator still enforces uniqueness. */
function wpae_llm_openrouter_typed_wire_schema( array $schema ): array {
    foreach ( array_keys( $schema ) as $key ) {
        if ( $key === 'uniqueItems' ) {
            unset( $schema[ $key ] );
            continue;
        }
        if ( is_array( $schema[ $key ] ) ) {
            $schema[ $key ] = wpae_llm_openrouter_typed_wire_schema( $schema[ $key ] );
        }
    }
    return $schema;
}

function wpae_llm_prepare_provider_request_body( array $request_body, bool $action_request, string $provider, array $policy = [] ): array {
	$strict_typed_contract = ( $policy['contract'] ?? '' ) === 'typed_intake_strict';
    if ( $provider === 'openrouter' ) {
        // OpenRouter's schema uses max_tokens; the OpenAI-only max_completion_tokens
        // field is outside that schema and is ignored or rejected by upstream routes.
        if ( isset( $request_body['max_completion_tokens'] ) ) {
            $request_body['max_tokens'] = (int) $request_body['max_completion_tokens'];
            unset( $request_body['max_completion_tokens'] );
        }
        if ( $strict_typed_contract && is_array( $request_body['response_format']['json_schema']['schema'] ?? null ) ) {
            // OpenRouter's compatible endpoint rejected uniqueItems. Duplicate fact_refs remain rejected by the full server validator.
            $request_body['response_format']['json_schema']['schema'] = wpae_llm_openrouter_typed_wire_schema( $request_body['response_format']['json_schema']['schema'] );
        }
        return $request_body;
    }

    if ( $provider !== 'gemini' ) {
        return $request_body;
    }

    // Gemini's OpenAI-compatible endpoint does not accept OpenAI-only action fields.
    unset( $request_body['max_completion_tokens'] );
    if ( $action_request && ! $strict_typed_contract ) {
        unset( $request_body['response_format'] );
    }

    return $request_body;
}

function wpae_llm_provider_request( string $url, array $remote_args, array $request_body, bool $action_request, string $provider, float $deadline = 0.0, ?array &$attempt_meta = null, array $policy = [] ) {
    try {
		$attempt_meta = [ 'provider_calls' => 0, 'retry_count' => 0, 'retry_reason' => '', 'first_finish_reason' => '', 'finish_reason' => '', 'http_status' => null, 'attempts' => [] ];
		$strict_typed_contract = ( $policy['contract'] ?? '' ) === 'typed_intake_strict';
        $deadline = $deadline > 0 ? $deadline : microtime( true ) + (float) ( $remote_args['timeout'] ?? 45 );
        $remaining = $deadline - microtime( true );
        if ( $remaining < 1 ) {
            return new WP_Error( 'wpae_llm_provider_budget_exhausted', 'Общее время ожидания LLM исчерпано.' );
        }
        $remote_args['timeout'] = min( (float) ( $remote_args['timeout'] ?? 45 ), $remaining );
        $canonical_schema = is_array( $request_body['response_format']['json_schema']['schema'] ?? null ) ? $request_body['response_format']['json_schema']['schema'] : null;
        $canonical_schema_json = $canonical_schema === null ? '' : (string) wp_json_encode( $canonical_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $canonical_schema_sha256 = $canonical_schema_json !== '' ? hash( 'sha256', $canonical_schema_json ) : null;
        $canonical_schema_bytes = $canonical_schema_json !== '' ? strlen( $canonical_schema_json ) : null;
        $wire_schema_adapter = $strict_typed_contract && $provider === 'openrouter' ? 'openrouter_typed_omit_uniqueItems_v1' : null;
		$request_body = wpae_llm_prepare_provider_request_body( $request_body, $action_request, $provider, $policy );
		$append_attempt = static function ( $attempt_response, $attempt_error, array $attempt_body, string $retry_reason = '' ) use ( &$attempt_meta, $url, $provider, $deadline, $strict_typed_contract, $canonical_schema_sha256, $canonical_schema_bytes, $wire_schema_adapter ): void {
			$status = ! is_wp_error( $attempt_response ) && ! is_wp_error( $attempt_error ) ? wpae_llm_diagnostic_http_status( wp_remote_retrieve_response_code( $attempt_response ) ) : null;
			$response_body = null;
			if ( ! is_wp_error( $attempt_response ) && ! is_wp_error( $attempt_error ) ) {
				$decoded = json_decode( (string) wp_remote_retrieve_body( $attempt_response ), true );
				$response_body = is_array( $decoded ) ? $decoded : null;
			}
			$details = wpae_llm_response_diagnostics( is_array( $response_body ) ? $response_body : [], $attempt_body );
			$response_format = is_array( $attempt_body['response_format'] ?? null ) ? $attempt_body['response_format'] : [];
			$schema = is_array( $response_format['json_schema']['schema'] ?? null ) ? $response_format['json_schema']['schema'] : null;
			$encoded_schema = $schema === null ? '' : (string) wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$limit = $attempt_body['max_tokens'] ?? ( $attempt_body['max_completion_tokens'] ?? null );
			$usage = is_array( $details['usage'] ?? null ) ? $details['usage'] : [];
			$attempt_meta['attempts'][] = [
				'requested_model' => wpae_llm_diagnostic_text( $attempt_body['model'] ?? '', 120 ),
				'returned_model' => (string) ( $details['returned_model'] ?? '' ) !== '' ? $details['returned_model'] : null,
				'endpoint' => wpae_llm_diagnostic_endpoint( $url ),
				'api_endpoint' => wpae_llm_diagnostic_endpoint( $url ),
				'endpoint_provider' => (string) ( $details['provider_name'] ?? '' ) !== '' ? $details['provider_name'] : null,
				'provider_name' => $details['provider_name'] ?? null,
				'response_format' => (string) ( $response_format['type'] ?? '' ) !== '' ? sanitize_key( (string) $response_format['type'] ) : null,
				'response_format_strict' => array_key_exists( 'strict', (array) ( $response_format['json_schema'] ?? [] ) ) ? (bool) $response_format['json_schema']['strict'] : null,
				'require_parameters' => array_key_exists( 'require_parameters', (array) ( $attempt_body['provider'] ?? [] ) ) ? (bool) $attempt_body['provider']['require_parameters'] : null,
				'schema_sha256' => $encoded_schema !== '' ? hash( 'sha256', $encoded_schema ) : null,
				'schema_bytes' => $encoded_schema !== '' ? strlen( $encoded_schema ) : null,
				'canonical_schema_sha256' => $strict_typed_contract ? $canonical_schema_sha256 : null,
				'canonical_schema_bytes' => $strict_typed_contract ? $canonical_schema_bytes : null,
				'wire_schema_sha256' => $encoded_schema !== '' ? hash( 'sha256', $encoded_schema ) : null,
				'wire_schema_bytes' => $encoded_schema !== '' ? strlen( $encoded_schema ) : null,
				'wire_schema_adapter' => $wire_schema_adapter,
				'http_status' => $status,
				'error_code' => $details['error_code'] ?? null,
				'provider_error_envelope' => ! empty( $details['has_error_envelope'] ),
				'provider_error_code' => $details['provider_error_code'] ?? null,
				'provider_error_type' => $details['provider_error_type'] ?? null,
				'provider_message' => $details['provider_message'] ?? null,
				'provider_error_param' => $details['provider_error_param'] ?? null,
				'provider_error_path' => $details['provider_error_path'] ?? null,
				'transport_error_code' => is_wp_error( $attempt_error ) ? sanitize_key( (string) $attempt_error->get_error_code() ) : null,
				'transport_error_message' => is_wp_error( $attempt_error ) ? wpae_llm_diagnostic_text( $attempt_error->get_error_message(), 200, $attempt_body ) : null,
				'finish_reason' => (string) ( $details['finish_reason'] ?? '' ) !== '' ? sanitize_key( (string) $details['finish_reason'] ) : null,
				'token_limit' => is_numeric( $limit ) ? max( 0, (int) $limit ) : null,
				'usage' => [ 'input_tokens' => $usage['input_tokens'] ?? null, 'output_tokens' => $usage['output_tokens'] ?? null, 'total_tokens' => $usage['total_tokens'] ?? null, 'reasoning_tokens' => $usage['reasoning_tokens'] ?? null ],
				'duration_ms' => isset( $attempt_meta['_attempt_started'] ) ? max( 0, (int) round( ( microtime( true ) - (float) $attempt_meta['_attempt_started'] ) * 1000 ) ) : null,
				'remaining_budget_ms' => max( 0, (int) round( ( $deadline - microtime( true ) ) * 1000 ) ),
				'retry_reason' => $retry_reason !== '' ? sanitize_key( $retry_reason ) : null,
				'schema_validation_result' => 'not_checked',
				'write_count' => 0,
			];
			unset( $attempt_meta['_attempt_started'] );
		};
        $remote_args['body'] = wp_json_encode( $request_body );
		$attempt_meta['_attempt_started'] = microtime( true );
        ++$attempt_meta['provider_calls'];
        $response = wp_safe_remote_post( $url, $remote_args );
		if ( $strict_typed_contract ) {
			$append_attempt( $response, is_wp_error( $response ) ? $response : null, $request_body );
			if ( ! is_wp_error( $response ) ) {
				$final_status = (int) wp_remote_retrieve_response_code( $response );
				$final_body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				$final_diagnostics = wpae_llm_response_diagnostics( is_array( $final_body ) ? $final_body : [] );
				$attempt_meta['finish_reason'] = is_string( $final_diagnostics['finish_reason'] ?? null ) ? $final_diagnostics['finish_reason'] : null;
				$attempt_meta['http_status'] = wpae_llm_diagnostic_http_status( $final_status );
			}
			return $response;
		}
		if ( ! is_wp_error( $response ) && $action_request && $provider === 'openrouter' ) {
            $initial_status = wp_remote_retrieve_response_code( $response );
            $initial_body = json_decode( wp_remote_retrieve_body( $response ), true );
            $initial_error = wpae_llm_provider_error_message( is_array( $initial_body ) ? $initial_body : [], $request_body );
            $initial_diagnostics = wpae_llm_response_diagnostics( is_array( $initial_body ) ? $initial_body : [] );
			$attempt_meta['first_finish_reason'] = is_string( $initial_diagnostics['finish_reason'] ?? null ) ? $initial_diagnostics['finish_reason'] : null;
            $structured_route_rejected = $initial_status >= 400 && ( stripos( $initial_error, 'No endpoints found' ) !== false || stripos( $initial_error, 'requested parameters' ) !== false || stripos( $initial_error, 'Provider returned error' ) !== false );
            $structured_response_failed = $initial_status >= 200 && $initial_status < 300 && in_array( strtolower( (string) ( $initial_diagnostics['finish_reason'] ?? '' ) ), [ 'error', 'length', 'max_tokens', 'token_limit' ], true );
            if ( $structured_route_rejected || $structured_response_failed ) {
                $remaining = $deadline - microtime( true );
                if ( $remaining < 1 ) {
                    return new WP_Error( 'wpae_llm_provider_budget_exhausted', 'Общее время ожидания LLM исчерпано.' );
                }
                ++$attempt_meta['provider_calls'];
                ++$attempt_meta['retry_count'];
                $attempt_meta['retry_reason'] = $structured_route_rejected ? 'structured_response_format_rejected' : 'structured_response_finish_reason_' . sanitize_key( (string) ( $initial_diagnostics['finish_reason'] ?? 'failed' ) );
                $remote_args['timeout'] = min( (float) $remote_args['timeout'], $remaining );
                $append_attempt( $response, null, $request_body, $attempt_meta['retry_reason'] );
                unset( $request_body['response_format'], $request_body['provider'] );
                $remote_args['body'] = wp_json_encode( $request_body );
				$attempt_meta['_attempt_started'] = microtime( true );
                $response = wp_safe_remote_post( $url, $remote_args );
            }
            if ( ! is_wp_error( $response ) ) {
                $final_status = (int) wp_remote_retrieve_response_code( $response );
                $final_body = json_decode( wp_remote_retrieve_body( $response ), true );
                $final_diagnostics = wpae_llm_response_diagnostics( is_array( $final_body ) ? $final_body : [] );
				$attempt_meta['finish_reason'] = is_string( $final_diagnostics['finish_reason'] ?? null ) ? $final_diagnostics['finish_reason'] : null;
				$attempt_meta['http_status'] = wpae_llm_diagnostic_http_status( $final_status );
            }
        }
		if ( empty( $attempt_meta['attempts'] ) || count( $attempt_meta['attempts'] ) < (int) $attempt_meta['provider_calls'] ) {
			$append_attempt( $response, is_wp_error( $response ) ? $response : null, $request_body, (string) ( $attempt_meta['retry_reason'] ?? '' ) );
		}
        return $response;
    } catch ( Throwable $error ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[WP AI Executor] Provider request failed for %s: %s', $provider, wpae_llm_diagnostic_text( $error->getMessage() ) ) );
        }
        return new WP_Error( 'wpae_llm_provider_request_failed', wpae_llm_diagnostic_text( $error->getMessage() ), [
            'provider' => $provider,
            'exception_type' => get_class( $error ),
        ] );
    }
}
