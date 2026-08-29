<?php

defined( 'ABSPATH' ) || exit;

function wpae_enqueue_elementor_llm_chat(): void {
    if ( ! current_user_can( 'edit_posts' ) || ! function_exists( 'wpae_block_library_asset_source' ) ) {
        return;
    }

    $script = wpae_block_library_asset_source( 'assets/js/elementor-llm-chat.js' );
    $style = wpae_block_library_asset_source( 'assets/css/elementor-llm-chat.css' );
    if ( $script === '' || $style === '' ) {
        return;
    }

    $post_id = absint( $_GET['post'] ?? $_GET['post_id'] ?? 0 );
    $settings = wpae_llm_get_settings();
    $config = wp_json_encode( [
        'endpoint' => get_rest_url( null, 'ai-executor/v1/llm/chat' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'postId' => $post_id,
        'ready' => wpae_capability_enabled( 'llm_chat' ) && ! empty( $settings['has_api_key'] ) && $settings['base_url'] !== '',
        'strings' => [
            'placeholder' => 'Спросите, что угодно…',
            'open' => 'Открыть чат LLM',
            'title' => 'LLM-помощник Elementor',
            'subtitle' => 'Ответы проходят через настроенный proxy',
            'send' => 'Отправить',
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

    wp_add_inline_style( 'elementor-editor', $style );
    wp_add_inline_script( 'elementor-editor', 'window.WPAELLMChat = ' . $config . ';' . "\n" . $script, 'before' );
}
add_action( 'elementor/editor/after_enqueue_scripts', 'wpae_enqueue_elementor_llm_chat', 30 );
