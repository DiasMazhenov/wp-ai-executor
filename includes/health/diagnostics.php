<?php

defined( 'ABSPATH' ) || exit;

const WPAE_HEALTH_HISTORY_MAX_ENTRIES = 20;

function wpae_health_add_check( array &$checks, string $code, string $status, string $label, string $message, array $details = [], string $fix = '' ): void {
    $checks[] = [
        'code' => sanitize_key( $code ),
        'status' => in_array( $status, [ 'good', 'warning', 'critical' ], true ) ? $status : 'warning',
        'label' => sanitize_text_field( $label ),
        'message' => sanitize_text_field( $message ),
        'details' => $details,
        'fix' => sanitize_text_field( $fix ),
    ];
}

function wpae_health_bytes( $value ): int {
    if ( is_int( $value ) || is_float( $value ) ) {
        return max( 0, (int) $value );
    }

    $value = trim( (string) $value );
    if ( $value === '' || $value === '-1' ) {
        return $value === '-1' ? -1 : 0;
    }

    return function_exists( 'wp_convert_hr_to_bytes' )
        ? (int) wp_convert_hr_to_bytes( $value )
        : max( 0, (int) $value );
}

function wpae_health_format_bytes( int $bytes ): string {
    if ( $bytes < 0 ) {
        return 'unlimited';
    }

    return size_format( $bytes, 2 );
}

function wpae_health_collect_cron_stats(): array {
    $cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
    $now = time();
    $events = 0;
    $overdue = 0;
    $oldest_overdue_seconds = 0;
    $overdue_hooks = [];

    foreach ( (array) $cron as $timestamp => $hooks ) {
        foreach ( (array) $hooks as $hook => $instances ) {
            $count = count( (array) $instances );
            $events += $count;
            if ( (int) $timestamp < $now - 10 * MINUTE_IN_SECONDS ) {
                $overdue += $count;
                $oldest_overdue_seconds = max( $oldest_overdue_seconds, $now - (int) $timestamp );
                if ( count( $overdue_hooks ) < 8 ) {
                    $overdue_hooks[] = sanitize_key( (string) $hook );
                }
            }
        }
    }

    return [
        'events' => $events,
        'overdue' => $overdue,
        'oldest_overdue_seconds' => $oldest_overdue_seconds,
        'overdue_hooks' => array_values( array_unique( $overdue_hooks ) ),
        'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
        'site_health_next_run' => (int) wp_next_scheduled( 'wp_site_health_scheduled_check' ),
    ];
}

function wpae_health_collect_loopback_check( array &$checks ): void {
    $started = microtime( true );
    $response = wp_remote_post( site_url( 'wp-cron.php' ), [
        'body' => [ 'site-health' => 'loopback-test' ],
        'headers' => [ 'Cache-Control' => 'no-cache' ],
        'timeout' => 5,
        'redirection' => 0,
        'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
    ] );
    $duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

    if ( is_wp_error( $response ) ) {
        wpae_health_add_check(
            $checks,
            'loopback',
            'critical',
            'Loopback WordPress',
            'Внутренний запрос WordPress завершился ошибкой или таймаутом.',
            [
                'duration_ms' => $duration_ms,
                'error_code' => sanitize_key( (string) $response->get_error_code() ),
                'error_message' => sanitize_text_field( (string) $response->get_error_message() ),
            ],
            'Проверьте PHP-FPM workers, nginx upstream timeout, DNS/SSL loopback и firewall.'
        );
        return;
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $status = $status_code === 200 && $duration_ms < 3000 ? 'good' : ( $status_code >= 500 || $duration_ms >= 5000 ? 'critical' : 'warning' );
    wpae_health_add_check(
        $checks,
        'loopback',
        $status,
        'Loopback WordPress',
        $status === 'good' ? 'Внутренний запрос WordPress работает.' : 'Внутренний запрос WordPress медленный или вернул неожиданный статус.',
        [ 'http_status' => $status_code, 'duration_ms' => $duration_ms ],
        $status === 'good' ? '' : 'Проверьте загрузку PHP-FPM, WP-Cron и сетевые ограничения loopback.'
    );
}

function wpae_health_collect_rest_loopback_check( array &$checks ): void {
    $started = microtime( true );
    $response = wp_remote_get( rest_url( 'wp/v2/types/post' ), [
        'headers' => [ 'Cache-Control' => 'no-cache' ],
        'timeout' => 5,
        'redirection' => 0,
        'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        'limit_response_size' => 65536,
    ] );
    $duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

    if ( is_wp_error( $response ) ) {
        wpae_health_add_check(
            $checks,
            'rest_loopback',
            'critical',
            'REST API',
            'REST API недоступен из WordPress или завершился таймаутом.',
            [
                'duration_ms' => $duration_ms,
                'error_code' => sanitize_key( (string) $response->get_error_code() ),
                'error_message' => sanitize_text_field( (string) $response->get_error_message() ),
            ],
            'Проверьте PHP-FPM, permalink rules, security plugins и nginx location для /wp-json/.'
        );
        return;
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $status = $status_code === 200 && $duration_ms < 3000 ? 'good' : ( $status_code >= 500 || $duration_ms >= 5000 ? 'critical' : 'warning' );
    wpae_health_add_check(
        $checks,
        'rest_loopback',
        $status,
        'REST API',
        $status === 'good' ? 'REST API отвечает штатно.' : 'REST API отвечает медленно или вернул неожиданный статус.',
        [ 'http_status' => $status_code, 'duration_ms' => $duration_ms ],
        $status === 'good' ? '' : 'Проверьте PHP-FPM workers, плагины безопасности и правила nginx.'
    );
}

function wpae_build_health_report( string $mode = 'quick' ): array {
    global $wpdb, $wp_version;

    $mode = $mode === 'deep' ? 'deep' : 'quick';
    $started = microtime( true );
    $request_started = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $started;
    $bootstrap_ms = max( 0, (int) round( ( $started - $request_started ) * 1000 ) );
    $checks = [];

    $bootstrap_status = $bootstrap_ms >= 5000 ? 'critical' : ( $bootstrap_ms >= 2000 ? 'warning' : 'good' );
    wpae_health_add_check(
        $checks,
        'wordpress_bootstrap',
        $bootstrap_status,
        'Загрузка WordPress',
        $bootstrap_status === 'good' ? 'WordPress загрузился без заметной задержки.' : 'WordPress загружается слишком медленно до запуска диагностики.',
        [ 'duration_ms' => $bootstrap_ms ],
        $bootstrap_status === 'good' ? '' : 'Проверьте PHP-FPM saturation, медленные плагины, object cache и базу данных.'
    );

    $db_started = microtime( true );
    $db_result = $wpdb->get_var( 'SELECT 1' );
    $db_ms = (int) round( ( microtime( true ) - $db_started ) * 1000 );
    $db_ok = (string) $db_result === '1' && $wpdb->last_error === '';
    $db_status = ! $db_ok ? 'critical' : ( $db_ms >= 1000 ? 'warning' : 'good' );
    wpae_health_add_check(
        $checks,
        'database',
        $db_status,
        'База данных',
        $db_ok ? ( $db_status === 'good' ? 'Соединение с базой работает.' : 'База отвечает медленно.' ) : 'Проверочный запрос базы завершился ошибкой.',
        [
            'duration_ms' => $db_ms,
            'server' => sanitize_text_field( (string) $wpdb->db_server_info() ),
            'error' => $db_ok ? '' : sanitize_text_field( (string) $wpdb->last_error ),
        ],
        $db_status === 'good' ? '' : 'Проверьте MySQL processlist, блокировки, slow query log и доступные соединения.'
    );

    $autoload = $wpdb->get_row(
        "SELECT COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_value)), 0) AS total_bytes FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')",
        ARRAY_A
    );
    $autoload_bytes = (int) ( $autoload['total_bytes'] ?? 0 );
    $autoload_status = $autoload_bytes >= 2 * MB_IN_BYTES ? 'critical' : ( $autoload_bytes >= 800 * KB_IN_BYTES ? 'warning' : 'good' );
    wpae_health_add_check(
        $checks,
        'autoloaded_options',
        $autoload_status,
        'Автозагружаемые options',
        $autoload_status === 'good' ? 'Размер autoload options приемлемый.' : 'Autoload options занимают слишком много памяти на каждом запросе.',
        [
            'count' => (int) ( $autoload['option_count'] ?? 0 ),
            'bytes' => $autoload_bytes,
            'formatted' => wpae_health_format_bytes( $autoload_bytes ),
        ],
        $autoload_status === 'good' ? '' : 'Найдите крупные устаревшие autoload options и отключите autoload только после проверки владельца option.'
    );

    $memory_limit = wpae_health_bytes( ini_get( 'memory_limit' ) );
    $memory_peak = memory_get_peak_usage( true );
    $memory_ratio = $memory_limit > 0 ? $memory_peak / $memory_limit : 0;
    $memory_status = $memory_limit > 0 && $memory_ratio >= 0.85 ? 'critical' : ( ( $memory_limit > 0 && $memory_limit < 128 * MB_IN_BYTES ) || $memory_ratio >= 0.65 ? 'warning' : 'good' );
    wpae_health_add_check(
        $checks,
        'php_memory',
        $memory_status,
        'Память PHP',
        $memory_status === 'good' ? 'Запас памяти PHP достаточный.' : 'Лимит или текущее потребление памяти PHP требует внимания.',
        [
            'limit_bytes' => $memory_limit,
            'limit' => wpae_health_format_bytes( $memory_limit ),
            'peak_bytes' => $memory_peak,
            'peak' => wpae_health_format_bytes( $memory_peak ),
            'usage_percent' => $memory_limit > 0 ? round( $memory_ratio * 100, 1 ) : null,
        ],
        $memory_status === 'good' ? '' : 'Проверьте memory_limit, тяжёлые плагины и большие Elementor/REST payload.'
    );

    $cron = wpae_health_collect_cron_stats();
    $cron_status = $cron['overdue'] >= 20 ? 'critical' : ( $cron['overdue'] > 0 || $cron['wp_cron_disabled'] ? 'warning' : 'good' );
    wpae_health_add_check(
        $checks,
        'wp_cron',
        $cron_status,
        'WP-Cron',
        $cron_status === 'good' ? 'Просроченных cron-событий не обнаружено.' : 'Есть просроченные cron-события или WP-Cron отключён.',
        $cron,
        $cron_status === 'good' ? '' : 'Проверьте системный cron, loopback и зависшие задачи плагинов.'
    );

    $php_status = version_compare( PHP_VERSION, '8.1', '>=' ) ? 'good' : ( version_compare( PHP_VERSION, '8.0', '<' ) ? 'critical' : 'warning' );
    wpae_health_add_check(
        $checks,
        'runtime',
        $php_status,
        'Runtime',
        $php_status === 'good' ? 'Версии WordPress и PHP определены.' : 'Версия PHP устарела для современного production WordPress.',
        [
            'wordpress' => sanitize_text_field( (string) $wp_version ),
            'php' => sanitize_text_field( PHP_VERSION ),
            'sapi' => sanitize_text_field( PHP_SAPI ),
            'max_execution_time' => (int) ini_get( 'max_execution_time' ),
            'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
            'opcache_enabled' => (bool) ini_get( 'opcache.enable' ),
            'external_object_cache' => wp_using_ext_object_cache(),
        ],
        $php_status === 'good' ? '' : 'Обновите PHP после проверки совместимости темы и плагинов.'
    );

    $disk_free = @disk_free_space( ABSPATH );
    $disk_total = @disk_total_space( ABSPATH );
    if ( is_float( $disk_free ) || is_int( $disk_free ) ) {
        $disk_free = (int) $disk_free;
        $disk_total = max( 0, (int) $disk_total );
        $disk_percent = $disk_total > 0 ? ( $disk_free / $disk_total ) * 100 : 100;
        $disk_status = $disk_free < 500 * MB_IN_BYTES || $disk_percent < 5 ? 'critical' : ( $disk_free < GB_IN_BYTES || $disk_percent < 10 ? 'warning' : 'good' );
        wpae_health_add_check(
            $checks,
            'disk_space',
            $disk_status,
            'Свободное место',
            $disk_status === 'good' ? 'Свободного места достаточно.' : 'На сервере заканчивается свободное место.',
            [
                'free_bytes' => $disk_free,
                'free' => wpae_health_format_bytes( $disk_free ),
                'free_percent' => round( $disk_percent, 1 ),
            ],
            $disk_status === 'good' ? '' : 'Удалите безопасные старые backup/cache/log файлы или увеличьте диск.'
        );
    }

    $debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    $debug_status = function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'production' && $debug_display ? 'warning' : 'good';
    wpae_health_add_check(
        $checks,
        'debug_configuration',
        $debug_status,
        'Отладка',
        $debug_status === 'good' ? 'Публичный вывод ошибок не создаёт обнаруженного риска.' : 'Публичный вывод PHP-ошибок включён на production.',
        [
            'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && (bool) WP_DEBUG_LOG,
            'wp_debug_display' => $debug_display,
        ],
        $debug_status === 'good' ? '' : 'Отключите WP_DEBUG_DISPLAY, оставив защищённый error log при необходимости.'
    );

    $debug_log = WP_CONTENT_DIR . '/debug.log';
    if ( is_file( $debug_log ) ) {
        $debug_log_size = (int) @filesize( $debug_log );
        $debug_log_status = $debug_log_size >= 50 * MB_IN_BYTES ? 'critical' : ( $debug_log_size >= 5 * MB_IN_BYTES ? 'warning' : 'good' );
        wpae_health_add_check(
            $checks,
            'debug_log_size',
            $debug_log_status,
            'Размер debug.log',
            $debug_log_status === 'good' ? 'debug.log не имеет опасного размера.' : 'debug.log стал слишком большим.',
            [
                'bytes' => $debug_log_size,
                'formatted' => wpae_health_format_bytes( $debug_log_size ),
                'modified_at' => gmdate( 'c', (int) @filemtime( $debug_log ) ),
            ],
            $debug_log_status === 'good' ? '' : 'Сначала изучите ошибки, затем настройте ротацию логов; не удаляйте лог вслепую.'
        );
    }

    $plugin_updates = get_site_transient( 'update_plugins' );
    $theme_updates = get_site_transient( 'update_themes' );
    $core_updates = get_site_transient( 'update_core' );
    $core_update_count = 0;
    foreach ( (array) ( is_object( $core_updates ) ? $core_updates->updates ?? [] : [] ) as $update ) {
        if ( is_object( $update ) && ( $update->response ?? '' ) === 'upgrade' ) {
            $core_update_count++;
        }
    }
    $update_count = count( (array) ( is_object( $plugin_updates ) ? $plugin_updates->response ?? [] : [] ) )
        + count( (array) ( is_object( $theme_updates ) ? $theme_updates->response ?? [] : [] ) )
        + $core_update_count;
    wpae_health_add_check(
        $checks,
        'updates',
        $update_count > 0 ? 'warning' : 'good',
        'Обновления',
        $update_count > 0 ? 'Доступны обновления WordPress, тем или плагинов.' : 'Кеш WordPress не сообщает об ожидающих обновлениях.',
        [
            'plugins' => count( (array) ( is_object( $plugin_updates ) ? $plugin_updates->response ?? [] : [] ) ),
            'themes' => count( (array) ( is_object( $theme_updates ) ? $theme_updates->response ?? [] : [] ) ),
            'core' => $core_update_count,
        ],
        $update_count > 0 ? 'Сделайте backup и установите совместимые security/maintenance updates.' : ''
    );

    $operation_logs = function_exists( 'wpae_get_operation_logs_store' )
        ? array_values( array_filter( array_slice( wpae_get_operation_logs_store(), 0, 30 ), 'is_array' ) )
        : [];
    $logged_durations = array_values( array_filter( array_map(
        static fn( array $entry ): int => isset( $entry['duration_ms'] ) ? max( 0, (int) $entry['duration_ms'] ) : 0,
        $operation_logs
    ) ) );
    if ( ! empty( $logged_durations ) ) {
        $slow_requests = count( array_filter( $logged_durations, static fn( int $duration ): bool => $duration >= 2000 ) );
        $very_slow_requests = count( array_filter( $logged_durations, static fn( int $duration ): bool => $duration >= 5000 ) );
        $max_duration = max( $logged_durations );
        $latency_status = $very_slow_requests >= 2 ? 'critical' : ( $slow_requests > 0 ? 'warning' : 'good' );
        wpae_health_add_check(
            $checks,
            'executor_rest_latency',
            $latency_status,
            'История REST-запросов',
            $latency_status === 'good' ? 'Недавние операции AI Executor выполнялись без заметных задержек.' : 'В журнале AI Executor обнаружены медленные REST-запросы.',
            [
                'sample_size' => count( $logged_durations ),
                'slow_over_2s' => $slow_requests,
                'very_slow_over_5s' => $very_slow_requests,
                'max_duration_ms' => $max_duration,
            ],
            $latency_status === 'good' ? '' : 'Остановите параллельных агентов и проверьте PHP-FPM max_children, slow log и MySQL.'
        );
    }

    if ( $mode === 'deep' ) {
        wpae_health_collect_loopback_check( $checks );
        wpae_health_collect_rest_loopback_check( $checks );
    }

    $summary = [ 'good' => 0, 'warning' => 0, 'critical' => 0 ];
    $recommendations = [];
    foreach ( $checks as $check ) {
        $summary[ $check['status'] ]++;
        if ( $check['status'] !== 'good' && $check['fix'] !== '' ) {
            $recommendations[] = $check['fix'];
        }
    }

    $overall = $summary['critical'] > 0 ? 'critical' : ( $summary['warning'] > 0 ? 'warning' : 'healthy' );
    return [
        'ok' => $summary['critical'] === 0,
        'health_version' => 'v01.00.00',
        'plugin_version' => WPAE_VERSION,
        'mode' => $mode,
        'overall' => $overall,
        'generated_at' => gmdate( 'c' ),
        'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
        'summary' => $summary,
        'checks' => $checks,
        'recommendations' => array_values( array_unique( $recommendations ) ),
        'limitations' => [
            'A plugin cannot answer while every PHP-FPM worker is already blocked.',
            'Use nginx, PHP-FPM, MySQL, and hosting metrics to confirm server-level saturation.',
            'Deep mode performs two same-site loopback requests with a five-second timeout and runs only on explicit request.',
        ],
        'redaction' => 'No API keys, database credentials, absolute paths, server IP addresses, request bodies, or log contents are returned.',
    ];
}

function wpae_get_health_history(): array {
    $history = get_option( 'wp_ai_executor_health_history', [] );
    return is_array( $history ) ? $history : [];
}

function wpae_store_health_report( array $report ): void {
    update_option( 'wp_ai_executor_health_last_report', $report, false );
    $history = wpae_get_health_history();
    array_unshift( $history, [
        'generated_at' => (string) ( $report['generated_at'] ?? '' ),
        'mode' => (string) ( $report['mode'] ?? 'quick' ),
        'overall' => (string) ( $report['overall'] ?? 'warning' ),
        'duration_ms' => (int) ( $report['duration_ms'] ?? 0 ),
        'summary' => (array) ( $report['summary'] ?? [] ),
        'problem_codes' => array_values( array_map(
            static fn( array $check ): string => (string) ( $check['code'] ?? '' ),
            array_filter(
                (array) ( $report['checks'] ?? [] ),
                static fn( $check ): bool => is_array( $check ) && ( $check['status'] ?? 'good' ) !== 'good'
            )
        ) ),
    ] );
    update_option( 'wp_ai_executor_health_history', array_slice( $history, 0, WPAE_HEALTH_HISTORY_MAX_ENTRIES ), false );
}

function wpae_get_health_report( string $mode = 'quick', bool $refresh = false ): array {
    $mode = $mode === 'deep' ? 'deep' : 'quick';
    $cache_key = 'wp_ai_executor_health_' . $mode . '_v1';
    if ( ! $refresh ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            $cached['cached'] = true;
            return $cached;
        }
    }

    $lock_key = 'wp_ai_executor_health_deep_lock_v1';
    if ( $mode === 'deep' && get_transient( $lock_key ) ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            $cached['cached'] = true;
            $cached['deep_check_skipped'] = 'already_running';
            return $cached;
        }

        $fallback = wpae_get_health_report( 'quick', false );
        $fallback['deep_check_skipped'] = 'already_running';
        return $fallback;
    }

    if ( $mode === 'deep' ) {
        set_transient( $lock_key, 1, 15 );
    }

    $report = wpae_build_health_report( $mode );
    $report['cached'] = false;
    set_transient( $cache_key, $report, $mode === 'deep' ? 5 * MINUTE_IN_SECONDS : 30 );
    wpae_store_health_report( $report );
    if ( $mode === 'deep' ) {
        delete_transient( $lock_key );
    }
    return $report;
}

function wpae_health_endpoint( WP_REST_Request $request ): WP_REST_Response {
    $mode = sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'quick' ) );
    $refresh = filter_var( $request->get_param( 'refresh' ), FILTER_VALIDATE_BOOLEAN );
    $report = wpae_get_health_report( $mode, $refresh );
    $report['history'] = array_slice( wpae_get_health_history(), 0, 10 );
    return new WP_REST_Response( $report, 200 );
}

add_action( 'admin_post_wpae_run_health_check', function (): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Недостаточно прав.', 'wp-ai-executor' ) );
    }

    check_admin_referer( 'wpae_run_health_check' );
    $mode = sanitize_key( (string) wp_unslash( $_POST['wpae_health_mode'] ?? 'quick' ) );
    wpae_get_health_report( $mode, true );
    wp_safe_redirect( admin_url( 'options-general.php?page=wp-ai-executor&health_checked=' . ( $mode === 'deep' ? 'deep' : 'quick' ) ) );
    exit;
} );

function wpae_render_health_dashboard_card( string $health_url ): void {
    $report = get_option( 'wp_ai_executor_health_last_report', [] );
    if ( ! is_array( $report ) || empty( $report['checks'] ) ) {
        $report = wpae_get_health_report( 'quick', false );
    }

    $overall = (string) ( $report['overall'] ?? 'warning' );
    $overall_label = $overall === 'healthy' ? 'Исправно' : ( $overall === 'critical' ? 'Критично' : 'Есть предупреждения' );
    $status_labels = [ 'good' => 'Норма', 'warning' => 'Предупреждение', 'critical' => 'Критично' ];
    $checks = array_slice( array_values( array_filter( (array) ( $report['checks'] ?? [] ), 'is_array' ) ), 0, 12 );
    ?>
    <style>
        .wpae-health-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
        .wpae-health-badge { display:inline-flex; align-items:center; min-height:30px; padding:4px 10px; border-radius:6px; font-weight:700; }
        .wpae-health-badge--healthy,.wpae-health-status--good { color:#166534; background:#dcfce7; }
        .wpae-health-badge--warning,.wpae-health-status--warning { color:#92400e; background:#fef3c7; }
        .wpae-health-badge--critical,.wpae-health-status--critical { color:#991b1b; background:#fee2e2; }
        .wpae-health-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin:16px 0; }
        .wpae-health-item { border:1px solid var(--wpae-border); border-radius:8px; padding:12px; background:#fff; }
        .wpae-health-item strong { display:block; margin-bottom:5px; }
        .wpae-health-status { display:inline-block; margin-top:8px; padding:2px 7px; border-radius:4px; font-size:12px; font-weight:700; }
        .wpae-health-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        @media (max-width:782px) { .wpae-health-list { grid-template-columns:1fr; } }
    </style>
    <div class="wpae-card wpae-card-wide">
        <div class="wpae-health-head">
            <div>
                <h2>Диагностика WordPress</h2>
                <p>Проверяет PHP, базу, WP-Cron, память, диск, autoload options и конфигурацию. Глубокий режим дополнительно тестирует loopback и REST API.</p>
            </div>
            <span class="wpae-health-badge wpae-health-badge--<?php echo esc_attr( $overall ); ?>"><?php echo esc_html( $overall_label ); ?></span>
        </div>

        <div class="wpae-health-list">
            <?php foreach ( $checks as $check ) : ?>
                <div class="wpae-health-item">
                    <strong><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?></strong>
                    <div><?php echo esc_html( (string) ( $check['message'] ?? '' ) ); ?></div>
                    <?php if ( ! empty( $check['fix'] ) && ( $check['status'] ?? 'good' ) !== 'good' ) : ?>
                        <div class="wpae-skill-meta" style="margin-top:6px"><?php echo esc_html( (string) $check['fix'] ); ?></div>
                    <?php endif; ?>
                    <span class="wpae-health-status wpae-health-status--<?php echo esc_attr( (string) ( $check['status'] ?? 'warning' ) ); ?>">
                        <?php echo esc_html( (string) ( $status_labels[ $check['status'] ?? 'warning' ] ?? 'Предупреждение' ) ); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wpae-health-actions">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wpae_run_health_check' ); ?>
                <input type="hidden" name="action" value="wpae_run_health_check" />
                <input type="hidden" name="wpae_health_mode" value="quick" />
                <button type="submit" class="button wpae-button">Быстрая проверка</button>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Глубокая проверка выполнит два loopback-запроса с таймаутом до 5 секунд каждый. Продолжить?')">
                <?php wp_nonce_field( 'wpae_run_health_check' ); ?>
                <input type="hidden" name="action" value="wpae_run_health_check" />
                <input type="hidden" name="wpae_health_mode" value="deep" />
                <button type="submit" class="button button-primary wpae-button">Глубокая проверка</button>
            </form>
            <span class="wpae-skill-meta">
                Последняя: <?php echo esc_html( (string) ( $report['generated_at'] ?? 'нет данных' ) ); ?>
                · режим <?php echo esc_html( (string) ( $report['mode'] ?? 'quick' ) ); ?>
                · <?php echo esc_html( (string) ( $report['duration_ms'] ?? 0 ) ); ?> мс
            </span>
        </div>

        <label for="wpae-health-url" style="display:block;margin-top:16px">REST URL диагностики</label>
        <div class="wpae-field-row" style="margin-top:6px">
            <input class="wpae-input" id="wpae-health-url" type="text" value="<?php echo esc_attr( $health_url ); ?>" readonly onclick="this.select()" />
            <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $health_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
        </div>
        <p class="wpae-skill-meta" style="margin-top:10px">Endpoint защищён <code>X-AI-Key</code>. Глубокий режим: <code>?mode=deep&amp;refresh=1</code>. Никакие исправления автоматически не выполняются.</p>
    </div>
    <?php
}
