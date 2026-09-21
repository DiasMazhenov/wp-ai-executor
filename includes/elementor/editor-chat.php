<?php

defined( 'ABSPATH' ) || exit;

function wpae_is_elementor_editor_screen(): bool {
    return is_admin()
        && sanitize_key( (string) ( $_GET['action'] ?? '' ) ) === 'elementor'
        && absint( $_GET['post'] ?? $_GET['post_id'] ?? 0 ) > 0;
}

function wpae_enqueue_elementor_llm_chat(): void {
    static $enqueued = false;
    if ( $enqueued ) {
        return;
    }
    if ( ! current_user_can( 'edit_posts' ) || ! function_exists( 'wpae_block_library_asset_source' ) ) {
        return;
    }

    $enqueued = true;

    $script = wpae_block_library_asset_source( 'assets/js/elementor-llm-chat.js' );
    $style = wpae_block_library_asset_source( 'assets/css/elementor-llm-chat.css' );
    if ( $script === '' || $style === '' ) {
        return;
    }

    $post_id = absint( $_GET['post'] ?? $_GET['post_id'] ?? 0 );
    $settings = wpae_llm_get_settings();
    $vision_status = wpae_get_vision_status();
    $pending_operation = null;
    if ( function_exists( 'wpae_design_operation_store' ) ) {
        foreach ( array_reverse( wpae_design_operation_store() ) as $candidate ) {
            if ( ! is_array( $candidate ) || absint( $candidate['post_id'] ?? 0 ) !== $post_id ) {
                continue;
            }
            $state = sanitize_key( (string) ( $candidate['current_state'] ?? '' ) );
            if ( ! in_array( $state, [ 'planned', 'generated', 'normalized', 'validated', 'written', 'rendered', 'reviewed', 'revised', 'unknown' ], true ) ) {
                continue;
            }
            $pending_operation = [
                'operation_id' => sanitize_key( (string) ( $candidate['operation_id'] ?? '' ) ),
                'operation_identity' => sanitize_text_field( (string) ( $candidate['operation_identity'] ?? '' ) ),
                'revision' => absint( $candidate['revision'] ?? 1 ),
                'saved_hash' => sanitize_text_field( (string) ( $candidate['saved_hash'] ?? '' ) ),
                'target_fingerprint' => sanitize_text_field( (string) ( $candidate['target_fingerprint'] ?? '' ) ),
                'root_ids' => array_values( array_filter( array_map( 'sanitize_key', array_slice( (array) ( $candidate['root_ids'] ?? [] ), 0, 12 ) ) ) ),
				'rollback_snapshot_id' => sanitize_text_field( (string) ( $candidate['rollback_snapshot_id'] ?? '' ) ),
                'current_state' => $state,
            ];
			if ( $pending_operation['rollback_snapshot_id'] === '' && function_exists( 'wpae_get_rollback_snapshots' ) ) {
				foreach ( wpae_get_rollback_snapshots() as $snapshot_id => $snapshot ) {
					if ( sanitize_key( (string) ( $snapshot['operation_id'] ?? '' ) ) !== $pending_operation['operation_id'] ) {
						continue;
					}
					if ( in_array( $post_id, array_map( 'absint', array_keys( (array) ( $snapshot['posts'] ?? [] ) ) ), true ) ) {
						$pending_operation['rollback_snapshot_id'] = sanitize_text_field( (string) $snapshot_id );
						break;
					}
				}
			}
            break;
        }
    }
    $config = wp_json_encode( [
        'endpoint' => get_rest_url( null, 'ai-executor/v1/llm/chat' ),
        'undoEndpoint' => get_rest_url( null, 'ai-executor/v1/llm/undo' ),
		'reconcileEndpoint' => get_rest_url( null, 'ai-executor/v1/design-operations/reconcile' ),
        'pluginVersion' => defined( 'WPAE_VERSION' ) ? WPAE_VERSION : '',
        'requestTimeoutMs' => ( WPAE_LLM_ACTION_TIMEOUT_SECONDS + 60 ) * 1000,
        'providerLabel' => (string) ( $settings['provider_label'] ?? '' ),
        'model' => (string) ( $settings['model'] ?? '' ),
        'vision' => [
            'ready' => ! empty( $vision_status['enabled'] ) && ! empty( $vision_status['configured'] ),
            'reviewEndpoint' => get_rest_url( null, 'ai-executor/v1/llm/vision-review' ),
            'captureScript' => 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
        ],
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'postId' => $post_id,
        'pendingOperation' => $pending_operation,
        'postStatus' => (string) get_post_status( $post_id ),
        'ready' => wpae_capability_enabled( 'llm_chat' ) && ! empty( $settings['has_api_key'] ) && $settings['base_url'] !== '',
        'strings' => [
            'placeholder' => 'Спросите, что угодно…',
            'open' => 'Открыть чат LLM',
            'title' => 'LLM-помощник Elementor',
            'subtitle' => 'Ответы проходят через настроенный proxy',
            'meta' => 'Модель: {model} · Версия: {version}',
            'send' => 'Отправить',
            'copyLog' => 'Копировать JSON',
            'copyPrompt' => 'Копировать промт',
            'promptCopied' => 'Промт скопирован',
            'copied' => 'JSON-лог скопирован',
            'copySelection' => 'Копировать JSON выделенного',
            'selectionCopied' => 'JSON выделенного скопирован',
            'copyPasteReady' => 'Копировать JSON для вставки в Elementor',
            'pasteReadyCopied' => 'JSON для вставки в Elementor скопирован',
            'regenerate' => 'Перегенерировать последний запрос',
            'regenerateEmpty' => 'Нет предыдущего запроса для перегенерации.',
            'regenerateBusy' => 'Дождитесь завершения текущего запроса.',
            'reviewPending' => 'Проверить сохранённый результат',
            'selectionEmpty' => 'Выделите элемент в Elementor и повторите.',
            'selectionCopyError' => 'Не удалось скопировать JSON выделенного.',
            'copyError' => 'Не удалось скопировать текст.',
            'close' => 'Свернуть чат',
            'empty' => 'Введите запрос.',
            'starting' => 'Подготовка запроса…',
            'sending' => 'LLM обрабатывает запрос…',
            'done' => 'Ответ получен',
            'disabled' => 'Настройте LLM и включите разрешение в AI Executor',
            'error' => 'Ошибка LLM-запроса',
            'welcome' => 'Опишите задачу по текущей странице или выбранному элементу.',
        ],
    ] );
    if ( ! is_string( $config ) ) {
        return;
    }

    $style_handle = wp_style_is( 'elementor-editor', 'registered' ) ? 'elementor-editor' : 'wpae-elementor-llm-chat';
    $script_handle = wp_script_is( 'elementor-editor', 'registered' ) ? 'elementor-editor' : 'wpae-elementor-llm-chat';
    if ( $style_handle === 'wpae-elementor-llm-chat' ) {
        wp_register_style( $style_handle, false, [], WPAE_VERSION );
        wp_enqueue_style( $style_handle );
    }
    if ( $script_handle === 'wpae-elementor-llm-chat' ) {
        wp_register_script( $script_handle, false, [], WPAE_VERSION, true );
        wp_enqueue_script( $script_handle );
    }
    wp_add_inline_style( $style_handle, $style );
    wp_add_inline_script( $script_handle, 'window.WPAELLMChat = ' . $config . ';' . "\n" . $script, 'before' );
}
add_action( 'admin_enqueue_scripts', function (): void {
    if ( wpae_is_elementor_editor_screen() ) {
        wpae_enqueue_elementor_llm_chat();
    }
}, 100 );
add_action( 'elementor/editor/after_enqueue_scripts', 'wpae_enqueue_elementor_llm_chat', 30 );
