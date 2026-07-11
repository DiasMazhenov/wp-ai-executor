<?php

defined( 'ABSPATH' ) || exit;

// ── Key management ─────────────────────────────────────────────────────────────
function wpae_get_key(): string {
    if ( defined( 'WP_AI_EXECUTOR_KEY' ) ) return WP_AI_EXECUTOR_KEY;
    $key = get_option( 'wp_ai_executor_key' );
    if ( ! $key ) {
        $key = bin2hex( random_bytes( 32 ) );
        update_option( 'wp_ai_executor_key', $key );
    }
    return $key;
}

function wpae_capability_defaults(): array {
    return [
        'run' => false,
        'self_update' => true,
        'elementor_writes' => true,
        'media_upload' => true,
        'exports' => true,
        'manage_skills' => true,
        'filesystem_writes' => false,
    ];
}

// Disable the historically enabled arbitrary-PHP capability once on upgrade.
// Site owners can explicitly re-enable it from the dashboard when required.
add_action( 'init', function (): void {
    if ( get_option( 'wp_ai_executor_security_hardening_v1', false ) ) {
        return;
    }

    $stored = get_option( 'wp_ai_executor_capabilities', [] );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $stored['run'] = false;
    $stored['filesystem_writes'] = false;
    update_option( 'wp_ai_executor_capabilities', $stored, false );
    update_option( 'wp_ai_executor_security_hardening_v1', gmdate( 'c' ), false );
}, 1 );

function wpae_capability_labels(): array {
    return [
        'run' => [
            'label' => 'Разрешить PHP /run',
            'description' => 'Позволяет авторизованным агентам выполнять PHP через /run.',
        ],
        'self_update' => [
            'label' => 'Разрешить самообновление плагина',
            'description' => 'Позволяет /self-update обновлять файл плагина из разрешенного GitHub-источника.',
        ],
        'elementor_writes' => [
            'label' => 'Разрешить запись Elementor',
            'description' => 'Позволяет сохранять _elementor_data через /run и структурированные Elementor endpoints.',
        ],
        'media_upload' => [
            'label' => 'Разрешить загрузку медиа',
            'description' => 'Позволяет /media/upload создавать проверенные вложения WordPress.',
        ],
        'exports' => [
            'label' => 'Разрешить JSON-экспорты',
            'description' => 'Позволяет /exports/create создавать короткоживущие JSON-экспорты в wp_options без публичных файлов.',
        ],
        'manage_skills' => [
            'label' => 'Разрешить управление skills',
            'description' => 'Позволяет агентам создавать, обновлять и удалять custom skills в базе данных.',
        ],
        'filesystem_writes' => [
            'label' => 'Разрешить запись файлов через /run',
            'description' => 'Опасно. Разрешает файловые операции через /run. Держите выключенным без явной необходимости.',
        ],
    ];
}

function wpae_get_capability_settings(): array {
    $stored = get_option( 'wp_ai_executor_capabilities', [] );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $settings = [];
    foreach ( wpae_capability_defaults() as $key => $default ) {
        $settings[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : (bool) $default;
    }

    return $settings;
}

function wpae_update_capability_settings( array $input ): void {
    $settings = [];
    foreach ( wpae_capability_defaults() as $key => $default ) {
        $settings[ $key ] = ! empty( $input[ $key ] );
    }
    update_option( 'wp_ai_executor_capabilities', $settings, false );
}

function wpae_capability_presets(): array {
    return [
        'read_only' => [
            'label' => 'Только чтение',
            'description' => 'Отключает все write-возможности. Подходит для диагностики, guide, capabilities, audits и logs.',
            'capabilities' => [
                'run' => false,
                'self_update' => false,
                'elementor_writes' => false,
                'media_upload' => false,
                'exports' => false,
                'manage_skills' => false,
                'filesystem_writes' => false,
            ],
        ],
        'elementor_safe' => [
            'label' => 'Elementor safe',
            'description' => 'Разрешает структурированные Elementor-правки, медиа и exports без PHP /run, self-update и файловых операций.',
            'capabilities' => [
                'run' => false,
                'self_update' => false,
                'elementor_writes' => true,
                'media_upload' => true,
                'exports' => true,
                'manage_skills' => false,
                'filesystem_writes' => false,
            ],
        ],
        'maintenance' => [
            'label' => 'Обслуживание',
            'description' => 'Разрешает Elementor, media, exports, skills и self-update. PHP /run и файловые операции остаются выключены.',
            'capabilities' => [
                'run' => false,
                'self_update' => true,
                'elementor_writes' => true,
                'media_upload' => true,
                'exports' => true,
                'manage_skills' => true,
                'filesystem_writes' => false,
            ],
        ],
        'full_trusted' => [
            'label' => 'Полный доверенный',
            'description' => 'Включает все возможности, включая PHP /run и файловые операции. Используйте только для полностью доверенного агента.',
            'capabilities' => [
                'run' => true,
                'self_update' => true,
                'elementor_writes' => true,
                'media_upload' => true,
                'exports' => true,
                'manage_skills' => true,
                'filesystem_writes' => true,
            ],
        ],
    ];
}

function wpae_apply_capability_preset( string $preset_id ): bool {
    $presets = wpae_capability_presets();
    if ( ! isset( $presets[ $preset_id ] ) ) {
        return false;
    }

    wpae_update_capability_settings( (array) $presets[ $preset_id ]['capabilities'] );
    return true;
}

function wpae_capability_enabled( string $capability ): bool {
    $settings = wpae_get_capability_settings();
    return ! empty( $settings[ $capability ] );
}

function wpae_can_run_filesystem_operations(): bool {
    if ( defined( 'WP_AI_EXECUTOR_ALLOW_FILE_WRITES' ) && WP_AI_EXECUTOR_ALLOW_FILE_WRITES ) {
        return true;
    }

    return wpae_capability_enabled( 'filesystem_writes' );
}
