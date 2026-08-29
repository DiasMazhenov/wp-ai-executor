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
            'copyLog' => 'Копировать лог',
            'copied' => 'Лог скопирован',
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
