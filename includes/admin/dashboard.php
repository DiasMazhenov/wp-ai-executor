<?php

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_options_page(
        'WP AI Executor',
        'AI Executor',
        'manage_options',
        'wp-ai-executor',
        'wpae_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'wpae_settings', 'wp_ai_executor_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    // Обработка регенерации ключа.
    if (
        isset( $_POST['wpae_regenerate'] ) &&
        check_admin_referer( 'wpae_regenerate_key' )
    ) {
        update_option( 'wp_ai_executor_key', bin2hex( random_bytes( 32 ) ) );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&regenerated=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_capabilities'] ) &&
        check_admin_referer( 'wpae_save_capabilities' )
    ) {
        $input = isset( $_POST['wpae_capabilities'] ) && is_array( $_POST['wpae_capabilities'] )
            ? wp_unslash( $_POST['wpae_capabilities'] )
            : [];

        wpae_update_capability_settings( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&capabilities_saved=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_apply_capability_preset'] ) &&
        check_admin_referer( 'wpae_apply_capability_preset' )
    ) {
        $preset_id = sanitize_key( (string) wp_unslash( $_POST['wpae_capability_preset'] ?? '' ) );
        $result = wpae_apply_capability_preset( $preset_id ) ? 'capability_preset_saved' : 'capability_preset_error';
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . $result . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_design_tokens'] ) &&
        check_admin_referer( 'wpae_save_design_tokens' )
    ) {
        $input = isset( $_POST['wpae_design_tokens'] ) && is_array( $_POST['wpae_design_tokens'] )
            ? wp_unslash( $_POST['wpae_design_tokens'] )
            : [];

        wpae_update_project_design_tokens( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&design_tokens_saved=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_skill_ui'] ) &&
        check_admin_referer( 'wpae_save_skill_ui' )
    ) {
        $raw_enforce = isset( $_POST['wpae_skill_enforce'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_enforce'] ) )
            : '';
        $enforce = [];

        if ( $raw_enforce !== '' ) {
            $decoded = json_decode( $raw_enforce, true );
            if ( is_array( $decoded ) ) {
                $enforce = $decoded;
            } else {
                wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_error=1' ) );
                exit;
            }
        }

        $skill = wpae_upsert_skill( [
            'id' => isset( $_POST['wpae_skill_id'] ) ? wp_unslash( $_POST['wpae_skill_id'] ) : '',
            'name' => isset( $_POST['wpae_skill_name'] ) ? wp_unslash( $_POST['wpae_skill_name'] ) : '',
            'description' => isset( $_POST['wpae_skill_description'] ) ? wp_unslash( $_POST['wpae_skill_description'] ) : '',
            'content' => isset( $_POST['wpae_skill_content'] ) ? wp_unslash( $_POST['wpae_skill_content'] ) : '',
            'enforce' => $enforce,
            'enabled' => ! empty( $_POST['wpae_skill_enabled'] ),
            'priority' => isset( $_POST['wpae_skill_priority'] ) ? wp_unslash( $_POST['wpae_skill_priority'] ) : 0,
        ] );

        $result = is_wp_error( $skill ) ? 'skill_error' : 'skill_saved';
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . $result . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_delete_skill_ui'] ) &&
        check_admin_referer( 'wpae_delete_skill_ui' )
    ) {
        $id = wpae_normalize_skill_id( isset( $_POST['wpae_delete_skill_id'] ) ? (string) wp_unslash( $_POST['wpae_delete_skill_id'] ) : '' );
        $skills = wpae_get_skill_store();

        if ( isset( $skills[ $id ] ) ) {
            unset( $skills[ $id ] );
            wpae_update_skill_store( $skills );
        }

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_deleted=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_import_skills_ui'] ) &&
        check_admin_referer( 'wpae_import_skills_ui' )
    ) {
        $bundle = isset( $_POST['wpae_skill_bundle_json'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_bundle_json'] ) )
            : '';
        $mode = isset( $_POST['wpae_skill_import_mode'] )
            ? sanitize_key( (string) wp_unslash( $_POST['wpae_skill_import_mode'] ) )
            : 'merge';
        $items = wpae_extract_skill_import_items( $bundle );
        $result = is_wp_error( $items ) ? $items : wpae_import_skill_items( $items, $mode );

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'skill_import_error' : 'skill_imported' ) . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_import_skill_url_ui'] ) &&
        check_admin_referer( 'wpae_import_skill_url_ui' )
    ) {
        $source_url = isset( $_POST['wpae_skill_source_url'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_source_url'] ) )
            : '';
        $raw_url = wpae_normalize_github_skill_url( $source_url );
        $result = $raw_url;

        if ( ! is_wp_error( $raw_url ) ) {
            $response = wp_remote_get( $raw_url, [
                'timeout' => 20,
                'redirection' => 3,
                'limit_response_size' => 150000,
            ] );

            if ( is_wp_error( $response ) ) {
                $result = $response;
            } elseif ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
                $result = new WP_Error( 'wpae_skill_download_failed', 'Skill download returned non-200 status.' );
            } else {
                $content = (string) wp_remote_retrieve_body( $response );
                $request_data = [
                    'id' => isset( $_POST['wpae_skill_url_id'] ) ? wp_unslash( $_POST['wpae_skill_url_id'] ) : '',
                    'name' => isset( $_POST['wpae_skill_url_name'] ) ? wp_unslash( $_POST['wpae_skill_url_name'] ) : '',
                    'description' => '',
                    'enabled' => ! empty( $_POST['wpae_skill_url_enabled'] ),
                    'priority' => isset( $_POST['wpae_skill_url_priority'] ) ? wp_unslash( $_POST['wpae_skill_url_priority'] ) : 10,
                    'enforce' => [],
                ];
                $trimmed = ltrim( $content );

                if ( $trimmed !== '' && ( $trimmed[0] === '{' || $trimmed[0] === '[' ) ) {
                    $decoded = json_decode( $content, true );
                    $items = wpae_extract_skill_import_items( $decoded );
                    if ( is_wp_error( $items ) ) {
                        $result = $items;
                    } else {
                        foreach ( $items as &$item ) {
                            if ( is_array( $item ) ) {
                                $item['source_url'] = $raw_url;
                                $item['source_type'] = 'github_bundle';
                                $item['source_sha256'] = hash( 'sha256', $content );
                                $item['imported_at'] = gmdate( 'c' );
                            }
                        }
                        unset( $item );
                        $result = wpae_import_skill_items( $items, 'merge' );
                    }
                } else {
                    $result = wpae_upsert_skill( wpae_build_skill_from_markdown( $content, $raw_url, $request_data ) );
                }
            }
        }

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'skill_url_error' : 'skill_url_imported' ) . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_prune_exports_ui'] ) &&
        check_admin_referer( 'wpae_prune_exports_ui' )
    ) {
        $before = wpae_get_export_store();
        $after = wpae_prune_export_store( $before );
        wpae_update_export_store( $after );

        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&exports_pruned=' . max( 0, count( $before ) - count( $after ) ) ) );
        exit;
    }
} );

function wpae_settings_page() {
    $key                = wpae_get_key();
    $site_url           = get_rest_url( null, 'ai-executor/v1/run' );
    $guide_url          = get_rest_url( null, 'ai-executor/v1/guide' );
    $capabilities_url   = get_rest_url( null, 'ai-executor/v1/capabilities' );
    $logs_url           = get_rest_url( null, 'ai-executor/v1/logs' );
    $health_url         = get_rest_url( null, 'ai-executor/v1/health' );
    $regen              = isset( $_GET['regenerated'] );
    $capabilities_saved = isset( $_GET['capabilities_saved'] );
    $capability_preset_saved = isset( $_GET['capability_preset_saved'] );
    $capability_preset_error = isset( $_GET['capability_preset_error'] );
    $design_tokens_saved = isset( $_GET['design_tokens_saved'] );
    $skill_saved        = isset( $_GET['skill_saved'] );
    $skill_deleted      = isset( $_GET['skill_deleted'] );
    $skill_error        = isset( $_GET['skill_error'] );
    $skill_imported     = isset( $_GET['skill_imported'] );
    $skill_import_error = isset( $_GET['skill_import_error'] );
    $skill_url_imported = isset( $_GET['skill_url_imported'] );
    $skill_url_error    = isset( $_GET['skill_url_error'] );
    $exports_pruned     = isset( $_GET['exports_pruned'] ) ? absint( $_GET['exports_pruned'] ) : null;
    $health_checked     = sanitize_key( (string) ( $_GET['health_checked'] ?? '' ) );
    $capabilities       = wpae_get_capability_settings();
    $capability_labels  = wpae_capability_labels();
    $capability_presets = wpae_capability_presets();
    $design_tokens      = wpae_get_project_design_tokens();
    $skills             = wpae_sort_skills( wpae_get_skill_store() );
    $operation_logs     = array_slice( wpae_get_operation_logs_store(), 0, 8 );
    $exports_summary    = wpae_build_exports_summary( wpae_get_export_store() );
    $skill_bundle_json  = wp_json_encode( wpae_build_skill_bundle(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    $enabled_count      = count( array_filter( $capabilities ) );
    $total_count        = count( $capabilities );
    $filesystem_locked  = ! wpae_can_run_filesystem_operations();
    ?>
    <style>
        .wpae-dashboard {
            --wpae-bg: #f6f7f9;
            --wpae-panel: #ffffff;
            --wpae-panel-soft: #f8fafc;
            --wpae-text: #111827;
            --wpae-muted: #64748b;
            --wpae-border: #d9e0ea;
            --wpae-accent: #16a34a;
            --wpae-accent-dark: #15803d;
            --wpae-danger: #b91c1c;
            --wpae-code: #0f172a;
            --wpae-code-text: #dbeafe;
            max-width: 1180px;
            color: var(--wpae-text);
        }
        .wpae-dashboard * { box-sizing: border-box; }
        .wpae-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
            gap: 16px;
            align-items: stretch;
            margin: 18px 0;
        }
        .wpae-hero-main,
        .wpae-card {
            background: var(--wpae-panel);
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .wpae-hero-main {
            padding: 24px;
            border-left: 4px solid var(--wpae-accent);
        }
        .wpae-kicker {
            margin: 0 0 8px;
            color: var(--wpae-accent-dark);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .wpae-title {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 0;
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: 0;
        }
        .wpae-version {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #e8f5ee;
            color: #166534;
            font-size: 13px;
            font-weight: 700;
        }
        .wpae-lead {
            max-width: 760px;
            margin: 10px 0 0;
            color: var(--wpae-muted);
            font-size: 14px;
            line-height: 1.55;
        }
        .wpae-status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            padding: 16px;
        }
        .wpae-stat {
            min-height: 78px;
            padding: 14px;
            background: var(--wpae-panel-soft);
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
        }
        .wpae-stat-label {
            margin: 0 0 7px;
            color: var(--wpae-muted);
            font-size: 12px;
            font-weight: 600;
        }
        .wpae-stat-value {
            margin: 0;
            font-size: 22px;
            line-height: 1.1;
            font-weight: 800;
        }
        .wpae-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .wpae-card {
            padding: 18px;
        }
        .wpae-card-wide {
            grid-column: 1 / -1;
        }
        .wpae-card h2 {
            margin: 0 0 6px;
            font-size: 18px;
            line-height: 1.25;
        }
        .wpae-card h3 {
            margin: 18px 0 8px;
            font-size: 14px;
        }
        .wpae-card p {
            margin: 0 0 12px;
            color: var(--wpae-muted);
            line-height: 1.5;
        }
        .wpae-field-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .wpae-input {
            width: 100%;
            min-height: 38px;
            padding: 8px 11px;
            border: 1px solid var(--wpae-border);
            border-radius: 7px;
            background: #fff;
            color: var(--wpae-text);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
        }
        .wpae-button {
            min-height: 38px;
            padding: 7px 12px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 700;
        }
        .wpae-button:focus-visible,
        .wpae-input:focus-visible,
        .wpae-toggle input:focus-visible {
            outline: 2px solid var(--wpae-accent);
            outline-offset: 2px;
        }
        .wpae-danger-button {
            color: var(--wpae-danger) !important;
            border-color: var(--wpae-danger) !important;
        }
        .wpae-code {
            margin: 0;
            padding: 14px;
            overflow-x: auto;
            border-radius: 8px;
            background: var(--wpae-code);
            color: var(--wpae-code-text);
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .wpae-code-light {
            background: #f8fafc;
            color: #1f2937;
            border: 1px solid var(--wpae-border);
        }
        .wpae-textarea {
            width: 100%;
            min-height: 180px;
            padding: 11px;
            border: 1px solid var(--wpae-border);
            border-radius: 7px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: vertical;
        }
        .wpae-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .wpae-form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 700;
        }
        .wpae-section-note {
            margin: 8px 0 0;
            padding: 10px 12px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 12px;
            line-height: 1.45;
        }
        .wpae-color-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .wpae-color-field {
            padding: 12px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }
        .wpae-color-control {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
        }
        .wpae-color-control input[type="color"] {
            width: 44px;
            height: 38px;
            padding: 2px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
        }
        .wpae-color-token {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }
        .wpae-token-pill {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .wpae-skill-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .wpae-skill-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-skill-item h3 {
            margin: 0 0 4px;
            font-size: 14px;
        }
        .wpae-skill-meta {
            color: var(--wpae-muted);
            font-size: 12px;
        }
        .wpae-cap-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .wpae-preset-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 14px 0 16px;
        }
        .wpae-preset {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            min-height: 154px;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-preset strong {
            display: block;
            margin-bottom: 5px;
            color: var(--wpae-text);
        }
        .wpae-preset span {
            display: block;
            color: var(--wpae-muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .wpae-toggle {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            min-height: 92px;
            padding: 13px;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel-soft);
        }
        .wpae-toggle input {
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }
        .wpae-toggle strong {
            display: block;
            margin-bottom: 4px;
            color: var(--wpae-text);
        }
        .wpae-toggle span {
            display: block;
            color: var(--wpae-muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .wpae-alert {
            margin: 12px 0;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            font-weight: 600;
        }
        .wpae-security {
            border-color: #fde68a;
            background: #fffbeb;
        }
        .wpae-security strong {
            display: block;
            margin-bottom: 8px;
        }
        .wpae-security ul {
            margin: 0 0 0 18px;
            color: #713f12;
        }
        @media (max-width: 960px) {
            .wpae-hero,
            .wpae-grid,
            .wpae-cap-list {
                grid-template-columns: 1fr;
            }
            .wpae-field-row {
                flex-direction: column;
            }
            .wpae-form-grid,
            .wpae-color-grid,
            .wpae-preset-list,
            .wpae-skill-item {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="wrap wpae-dashboard">
        <section class="wpae-hero" aria-labelledby="wpae-title">
            <div class="wpae-hero-main">
                <p class="wpae-kicker">Панель управления агентами</p>
                <h1 id="wpae-title" class="wpae-title">
                    WP AI Executor
                    <span class="wpae-version"><?php echo esc_html( WPAE_VERSION ); ?></span>
                </h1>
                <p class="wpae-lead">
                    REST-мост для Codex, Claude, GPT, Gemini, Qwen и других агентов.
                    Управляйте доступом, проверяйте Elementor-структуру и держите опасные операции под контролем.
                </p>
            </div>

            <div class="wpae-card">
                <div class="wpae-status-grid">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Разрешения</p>
                        <p class="wpae-stat-value"><?php echo esc_html( $enabled_count . '/' . $total_count ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Файловая запись</p>
                        <p class="wpae-stat-value"><?php echo $filesystem_locked ? 'Выкл.' : 'Вкл.'; ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Guide-токен</p>
                        <p class="wpae-stat-value">15 мин</p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Elementor</p>
                        <p class="wpae-stat-value"><?php echo ! empty( $capabilities['elementor_writes'] ) ? 'Вкл.' : 'Выкл.'; ?></p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ( $regen ) : ?>
            <div class="wpae-alert" role="status">Секретный ключ успешно сгенерирован заново.</div>
        <?php endif; ?>

        <?php if ( $capabilities_saved ) : ?>
            <div class="wpae-alert" role="status">Настройки разрешений сохранены.</div>
        <?php endif; ?>

        <?php if ( $capability_preset_saved ) : ?>
            <div class="wpae-alert" role="status">Профиль разрешений применен.</div>
        <?php endif; ?>

        <?php if ( $capability_preset_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось применить профиль разрешений.</div>
        <?php endif; ?>

        <?php if ( $design_tokens_saved ) : ?>
            <div class="wpae-alert" role="status">Дизайн-токены проекта сохранены.</div>
        <?php endif; ?>

        <?php if ( $skill_saved ) : ?>
            <div class="wpae-alert" role="status">Custom skill сохранен.</div>
        <?php endif; ?>

        <?php if ( $skill_deleted ) : ?>
            <div class="wpae-alert" role="status">Custom skill удален.</div>
        <?php endif; ?>

        <?php if ( $skill_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось сохранить skill: проверьте название, содержимое и JSON enforce.</div>
        <?php endif; ?>

        <?php if ( $skill_imported ) : ?>
            <div class="wpae-alert" role="status">Пакет skills импортирован.</div>
        <?php endif; ?>

        <?php if ( $skill_import_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось импортировать пакет: проверьте JSON, поле skills и содержимое каждого skill.</div>
        <?php endif; ?>

        <?php if ( $skill_url_imported ) : ?>
            <div class="wpae-alert" role="status">Skill из GitHub URL импортирован.</div>
        <?php endif; ?>

        <?php if ( $skill_url_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось импортировать skill из GitHub URL: проверьте ссылку, доступность файла и размер.</div>
        <?php endif; ?>

        <?php if ( $exports_pruned !== null ) : ?>
            <div class="wpae-alert" role="status">Очистка exports завершена. Удалено записей: <?php echo esc_html( (string) $exports_pruned ); ?>.</div>
        <?php endif; ?>

        <?php if ( $health_checked !== '' ) : ?>
            <div class="wpae-alert" role="status">Диагностика WordPress завершена. Режим: <?php echo esc_html( $health_checked ); ?>.</div>
        <?php endif; ?>

        <div class="wpae-grid">
            <?php wpae_render_health_dashboard_card( $health_url ); ?>

            <div class="wpae-card">
                <h2>REST endpoint</h2>
                <p>Основной адрес для выполнения PHP через защищенный REST API.</p>
                <label for="wpae-rest-url">REST URL</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-rest-url" type="text" value="<?php echo esc_attr( $site_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $site_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Секретный ключ</h2>
                <p>Передавайте этот ключ в заголовке <code>X-AI-Key</code>. Старый <code>X-WPAE-API-Key</code> принимается только как deprecated alias и возвращает предупреждение. Не публикуйте ключ в frontend-коде.</p>
                <label for="wpae-key">X-AI-Key</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" type="text" id="wpae-key" value="<?php echo esc_attr( $key ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $key ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>

                <form method="post" style="margin-top:12px" onsubmit="return confirm('Сгенерировать новый секретный ключ? Агентам со старым ключом потребуется обновление.')">
                    <?php wp_nonce_field( 'wpae_regenerate_key' ); ?>
                    <input type="hidden" name="wpae_regenerate" value="1" />
                    <button type="submit" class="button wpae-button wpae-danger-button">Сгенерировать новый ключ</button>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Короткоживущие exports</h2>
                <p>
                    JSON-экспорты хранятся в <code>wp_options</code>, не создают публичных файлов и автоматически ограничены по TTL и количеству.
                </p>
                <div class="wpae-status-grid" style="padding:0;margin-top:12px">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Активные exports</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( $exports_summary['active_count'] ?? 0 ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Просроченные</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( $exports_summary['expired_count'] ?? 0 ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Активный размер</p>
                        <p class="wpae-stat-value"><?php echo esc_html( size_format( (int) ( $exports_summary['total_active_bytes'] ?? 0 ) ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">TTL</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( (int) WPAE_EXPORT_TTL_SECONDS / 60 ) ); ?> мин</p>
                    </div>
                </div>
                <form method="post" style="margin-top:12px">
                    <?php wp_nonce_field( 'wpae_prune_exports_ui' ); ?>
                    <input type="hidden" name="wpae_prune_exports_ui" value="1" />
                    <button type="submit" class="button wpae-button">Очистить просроченные exports</button>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Разрешения агента</h2>
                <p>
                    Ключ остается один, но владелец сайта управляет тем, что агенту разрешено делать.
                    Все write endpoints дополнительно требуют свежий guide token.
                </p>

                <div class="wpae-preset-list">
                    <?php foreach ( $capability_presets as $preset_id => $preset ) : ?>
                        <form class="wpae-preset" method="post">
                            <?php wp_nonce_field( 'wpae_apply_capability_preset' ); ?>
                            <input type="hidden" name="wpae_apply_capability_preset" value="1" />
                            <input type="hidden" name="wpae_capability_preset" value="<?php echo esc_attr( $preset_id ); ?>" />
                            <span>
                                <strong><?php echo esc_html( $preset['label'] ); ?></strong>
                                <span><?php echo esc_html( $preset['description'] ); ?></span>
                            </span>
                            <button type="submit" class="button wpae-button">Применить</button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_capabilities' ); ?>
                    <input type="hidden" name="wpae_save_capabilities" value="1" />

                    <div class="wpae-cap-list">
                    <?php foreach ( $capability_labels as $capability => $meta ) : ?>
                        <label class="wpae-toggle">
                            <input type="checkbox"
                                name="wpae_capabilities[<?php echo esc_attr( $capability ); ?>]"
                                value="1"
                                <?php checked( ! empty( $capabilities[ $capability ] ) ); ?> />
                            <span>
                                <strong><?php echo esc_html( $meta['label'] ); ?></strong>
                                <span><?php echo esc_html( $meta['description'] ); ?></span>
                                <?php if ( $capability === 'filesystem_writes' && defined( 'WP_AI_EXECUTOR_ALLOW_FILE_WRITES' ) && WP_AI_EXECUTOR_ALLOW_FILE_WRITES ) : ?>
                                    <span><strong>Переопределение в wp-config.php сейчас включено.</strong></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить разрешения</button>
                    </p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Дизайн-токены проекта</h2>
                <p>
                    Эти настройки попадают в <code>/guide</code>, <code>/capabilities</code>, <code>/elementor/design-system</code> и <code>/elementor/blueprint</code>.
                    Агент обязан использовать их как единую дизайн-систему для новых страниц и новых блоков.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_design_tokens' ); ?>
                    <input type="hidden" name="wpae_save_design_tokens" value="1" />

                    <h3>Палитра</h3>
                    <p class="wpae-section-note">
                        Выберите цвета через picker или введите HEX вручную. Эти значения становятся обязательной дизайн-системой для Elementor-страниц и блоков.
                    </p>
                    <div class="wpae-color-grid">
                        <?php foreach ( (array) ( $design_tokens['palette'] ?? [] ) as $token_key => $token_value ) : ?>
                            <?php
                            $color_value = (string) $token_value;
                            $picker_value = preg_match( '/^#[0-9a-fA-F]{6}$/', $color_value ) ? $color_value : '#111827';
                            ?>
                            <div class="wpae-color-field">
                                <div class="wpae-color-token">
                                    <label for="wpae-token-palette-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                    <span class="wpae-token-pill"><?php echo esc_html( $color_value ); ?></span>
                                </div>
                                <div class="wpae-color-control">
                                    <input type="color"
                                        aria-label="<?php echo esc_attr( $token_key ); ?> color picker"
                                        value="<?php echo esc_attr( $picker_value ); ?>"
                                        data-wpae-color-target="wpae-token-palette-<?php echo esc_attr( $token_key ); ?>" />
                                    <input class="wpae-input"
                                        id="wpae-token-palette-<?php echo esc_attr( $token_key ); ?>"
                                        name="wpae_design_tokens[palette][<?php echo esc_attr( $token_key ); ?>]"
                                        type="text"
                                        pattern="#[0-9a-fA-F]{6,8}"
                                        value="<?php echo esc_attr( $color_value ); ?>" />
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3>Типографика</h3>
                    <div class="wpae-form-grid">
                        <?php foreach ( (array) ( $design_tokens['typography_roles'] ?? [] ) as $token_key => $token_value ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-token-type-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                <input class="wpae-input" id="wpae-token-type-<?php echo esc_attr( $token_key ); ?>" name="wpae_design_tokens[typography_roles][<?php echo esc_attr( $token_key ); ?>]" type="text" value="<?php echo esc_attr( (string) $token_value ); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3>Spacing и radii</h3>
                    <div class="wpae-form-grid">
                        <?php foreach ( (array) ( $design_tokens['spacing_scale'] ?? [] ) as $token_key => $token_value ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-token-spacing-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                <input class="wpae-input" id="wpae-token-spacing-<?php echo esc_attr( $token_key ); ?>" name="wpae_design_tokens[spacing_scale][<?php echo esc_attr( $token_key ); ?>]" type="text" value="<?php echo esc_attr( (string) $token_value ); ?>" />
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ( (array) ( $design_tokens['radii'] ?? [] ) as $token_key => $token_value ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-token-radii-<?php echo esc_attr( $token_key ); ?>"><?php echo esc_html( $token_key ); ?></label>
                                <input class="wpae-input" id="wpae-token-radii-<?php echo esc_attr( $token_key ); ?>" name="wpae_design_tokens[radii][<?php echo esc_attr( $token_key ); ?>]" type="text" value="<?php echo esc_attr( (string) $token_value ); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-token-button-style">Стиль кнопок</label>
                            <input class="wpae-input" id="wpae-token-button-style" name="wpae_design_tokens[button_style]" type="text" value="<?php echo esc_attr( (string) ( $design_tokens['button_style'] ?? '' ) ); ?>" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-token-tone">Тон коммуникации</label>
                            <input class="wpae-input" id="wpae-token-tone" name="wpae_design_tokens[tone_of_voice]" type="text" value="<?php echo esc_attr( (string) ( $design_tokens['tone_of_voice'] ?? '' ) ); ?>" />
                        </div>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-token-prohibitions">Дизайн-запреты</label>
                        <textarea class="wpae-textarea" id="wpae-token-prohibitions" name="wpae_design_tokens[design_prohibitions]" style="min-height:120px"><?php echo esc_textarea( implode( "\n", (array) ( $design_tokens['design_prohibitions'] ?? [] ) ) ); ?></textarea>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить дизайн-токены</button>
                    </p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Пользовательские skills</h2>
                <p>
                    Загружайте собственные инструкции в формате <code>SKILL.md</code>. Они хранятся в базе WordPress,
                    попадают в <code>/guide</code> и не создают файлов на сервере.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_skill_ui' ); ?>
                    <input type="hidden" name="wpae_save_skill_ui" value="1" />

                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-skill-name">Название</label>
                            <input class="wpae-input" id="wpae-skill-name" name="wpae_skill_name" type="text" placeholder="frontend-design" required />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-id">ID</label>
                            <input class="wpae-input" id="wpae-skill-id" name="wpae_skill_id" type="text" placeholder="frontend-design" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-priority">Приоритет</label>
                            <input class="wpae-input" id="wpae-skill-priority" name="wpae_skill_priority" type="number" min="-100" max="100" value="10" />
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-enabled">Статус</label>
                            <label class="wpae-toggle" style="min-height:38px;padding:9px;margin:0">
                                <input id="wpae-skill-enabled" name="wpae_skill_enabled" type="checkbox" value="1" checked />
                                <span><strong>Включить skill</strong></span>
                            </label>
                        </div>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-description">Описание</label>
                        <input class="wpae-input" id="wpae-skill-description" name="wpae_skill_description" type="text" placeholder="Правила дизайна, Elementor или проекта" />
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-content">Содержимое SKILL.md</label>
                        <textarea class="wpae-textarea" id="wpae-skill-content" name="wpae_skill_content" placeholder="# Skill instructions..." required></textarea>
                    </div>

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-enforce">Enforce JSON</label>
                        <textarea class="wpae-textarea" id="wpae-skill-enforce" name="wpae_skill_enforce" style="min-height:92px" placeholder='[{"type":"forbid_elementor_eltype","value":"section"},{"type":"require_widget_key","value":"widgetType"}]'></textarea>
                    </div>

                    <p style="margin-top:14px">
                        <button type="submit" class="button button-primary wpae-button">Сохранить skill</button>
                    </p>
                </form>

                <div class="wpae-grid wpae-grid-two" style="margin-top:18px">
                    <form method="post" style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <?php wp_nonce_field( 'wpae_import_skill_url_ui' ); ?>
                        <input type="hidden" name="wpae_import_skill_url_ui" value="1" />
                        <h3 style="margin-top:0">Импорт из GitHub URL</h3>
                        <p>Вставьте ссылку на <code>SKILL.md</code>, папку skill на GitHub или JSON bundle. Плагин скачает содержимое и сохранит его в базе, без файлов на сервере.</p>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-source-url">GitHub URL</label>
                            <input class="wpae-input" id="wpae-skill-source-url" name="wpae_skill_source_url" type="url" placeholder="https://github.com/owner/repo/blob/main/path/SKILL.md" required />
                        </div>
                        <div class="wpae-form-grid">
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-name">Название, если нужно</label>
                                <input class="wpae-input" id="wpae-skill-url-name" name="wpae_skill_url_name" type="text" placeholder="frontend-design" />
                            </div>
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-id">ID, если нужно</label>
                                <input class="wpae-input" id="wpae-skill-url-id" name="wpae_skill_url_id" type="text" placeholder="frontend-design" />
                            </div>
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-priority">Приоритет</label>
                                <input class="wpae-input" id="wpae-skill-url-priority" name="wpae_skill_url_priority" type="number" min="-100" max="100" value="10" />
                            </div>
                            <div class="wpae-form-field">
                                <label for="wpae-skill-url-enabled">Статус</label>
                                <label class="wpae-toggle" style="min-height:38px;padding:9px;margin:0">
                                    <input id="wpae-skill-url-enabled" name="wpae_skill_url_enabled" type="checkbox" value="1" checked />
                                    <span><strong>Включить skill</strong></span>
                                </label>
                            </div>
                        </div>
                        <p class="wpae-section-note">Поддерживаются только HTTPS-ссылки <code>github.com</code> и <code>raw.githubusercontent.com</code>. При импорте меняется <code>guide_hash</code>, поэтому агенту нужно заново пройти guide ack.</p>
                        <p style="margin-top:14px">
                            <button type="submit" class="button button-primary wpae-button">Импортировать из GitHub</button>
                        </p>
                    </form>

                    <form method="post" style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <?php wp_nonce_field( 'wpae_import_skills_ui' ); ?>
                        <input type="hidden" name="wpae_import_skills_ui" value="1" />
                        <h3 style="margin-top:0">Импорт пакета</h3>
                        <p>Вставьте JSON bundle. Режим merge обновит совпадающие ID, replace полностью заменит текущие skills.</p>
                        <div class="wpae-form-field">
                            <label for="wpae-skill-import-mode">Режим</label>
                            <select class="wpae-input" id="wpae-skill-import-mode" name="wpae_skill_import_mode">
                                <option value="merge">Merge: добавить и обновить</option>
                                <option value="replace">Replace: заменить все</option>
                            </select>
                        </div>
                        <div class="wpae-form-field" style="margin-top:12px">
                            <label for="wpae-skill-bundle-json">JSON bundle</label>
                            <textarea class="wpae-textarea" id="wpae-skill-bundle-json" name="wpae_skill_bundle_json" style="min-height:180px" placeholder='{"schema":"wp-ai-executor.skill-bundle","skills":[]}' required></textarea>
                        </div>
                        <p style="margin-top:14px">
                            <button type="submit" class="button button-primary wpae-button">Импортировать</button>
                        </p>
                    </form>

                    <div style="border:1px solid var(--wpae-border);border-radius:12px;padding:16px;background:#fff">
                        <h3 style="margin-top:0">Экспорт пакета</h3>
                        <p>Этот JSON можно перенести на другой WordPress сайт с WP AI Executor. Файлы на сервере не создаются.</p>
                        <textarea class="wpae-textarea" readonly style="min-height:265px" onclick="this.select()"><?php echo esc_textarea( (string) $skill_bundle_json ); ?></textarea>
                    </div>
                </div>

                <div class="wpae-skill-list" aria-label="Установленные custom skills">
                    <?php if ( empty( $skills ) ) : ?>
                        <div class="wpae-skill-item">
                            <div>
                                <h3>Skills пока не загружены</h3>
                                <div class="wpae-skill-meta">Добавьте SKILL.md через форму выше.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $skills as $skill ) : ?>
                            <div class="wpae-skill-item">
                                <div>
                                    <h3><?php echo esc_html( (string) ( $skill['name'] ?? $skill['id'] ?? 'skill' ) ); ?></h3>
                                    <div class="wpae-skill-meta">
                                        ID: <code><?php echo esc_html( (string) ( $skill['id'] ?? '' ) ); ?></code>
                                        · приоритет: <?php echo esc_html( (string) ( $skill['priority'] ?? 0 ) ); ?>
                                        · <?php echo ! empty( $skill['enabled'] ) ? 'включен' : 'выключен'; ?>
                                        · enforce: <?php echo esc_html( (string) count( is_array( $skill['enforce'] ?? null ) ? $skill['enforce'] : [] ) ); ?>
                                    </div>
                                    <?php if ( ! empty( $skill['description'] ) ) : ?>
                                        <div class="wpae-skill-meta"><?php echo esc_html( (string) $skill['description'] ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $skill['source_url'] ) ) : ?>
                                        <div class="wpae-skill-meta">
                                            source: <code><?php echo esc_html( (string) $skill['source_type'] ); ?></code>
                                            · hash: <code><?php echo esc_html( substr( (string) ( $skill['source_sha256'] ?? '' ), 0, 12 ) ); ?></code>
                                            · <a href="<?php echo esc_url( (string) $skill['source_url'] ); ?>" target="_blank" rel="noreferrer">открыть источник</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <form method="post" onsubmit="return confirm('Удалить custom skill?')">
                                    <?php wp_nonce_field( 'wpae_delete_skill_ui' ); ?>
                                    <input type="hidden" name="wpae_delete_skill_ui" value="1" />
                                    <input type="hidden" name="wpae_delete_skill_id" value="<?php echo esc_attr( (string) ( $skill['id'] ?? '' ) ); ?>" />
                                    <button type="submit" class="button wpae-button wpae-danger-button">Удалить</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Guide и разрешения</h2>
                <p>Агент должен читать эти endpoints перед записью и следовать возвращенным правилам.</p>
                <label for="wpae-guide-url">URL guide</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-guide-url" type="text" value="<?php echo esc_attr( $guide_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $guide_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
                <label for="wpae-capabilities-url" style="display:block;margin-top:12px">URL разрешений</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-capabilities-url" type="text" value="<?php echo esc_attr( $capabilities_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $capabilities_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Журнал операций</h2>
                <p>Последние действия агентов без ключей, токенов и raw payload.</p>
                <label for="wpae-logs-url">URL журнала</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-logs-url" type="text" value="<?php echo esc_attr( $logs_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $logs_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>

                <div class="wpae-skill-list" style="margin-top:14px">
                    <?php if ( empty( $operation_logs ) ) : ?>
                        <div class="wpae-skill-item">
                            <div>
                                <h3>Записей пока нет</h3>
                                <div class="wpae-skill-meta">Журнал появится после write/audit запросов.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $operation_logs as $entry ) : ?>
                            <div class="wpae-skill-item">
                                <div>
                                    <h3><?php echo esc_html( (string) ( $entry['method'] ?? '' ) . ' ' . ( $entry['endpoint'] ?? '' ) ); ?></h3>
                                    <div class="wpae-skill-meta">
                                        <?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?>
                                        · status <?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?>
                                        <?php if ( isset( $entry['duration_ms'] ) ) : ?>
                                            · <?php echo esc_html( (string) $entry['duration_ms'] ); ?> мс
                                        <?php endif; ?>
                                        · actor <?php echo esc_html( (string) ( $entry['actor'] ?? 'agent' ) ); ?>
                                    </div>
                                    <?php if ( ! empty( $entry['target_ids'] ) ) : ?>
                                        <div class="wpae-skill-meta">targets: <code><?php echo esc_html( (string) wp_json_encode( $entry['target_ids'] ) ); ?></code></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $entry['rollback_snapshot_id'] ) ) : ?>
                                        <div class="wpae-skill-meta">rollback: <code><?php echo esc_html( (string) $entry['rollback_snapshot_id'] ); ?></code></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpae-card">
                <h2>Пример curl</h2>
                <p>Минимальный запрос к `/run`. Для write endpoints также нужен guide token.</p>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s -X POST "' . $site_url . '" \\
  -H "Content-Type: application/json" \\
  -H "X-AI-Key: ' . $key . '" \\
  -d \'{"code": "return get_bloginfo(\'name\');"}\''
); ?></pre>
            </div>

            <div class="wpae-card">
                <h2>JavaScript</h2>
                <p>Для локальной разработки или agent runtime с fetch.</p>
                <pre class="wpae-code"><?php echo esc_html(
'const AI_KEY = "' . $key . '";

window.aiPHP = async (code) => {
    const res = await fetch("/wp-json/ai-executor/v1/run", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-AI-Key": AI_KEY },
        body: JSON.stringify({ code })
    });
    const d = await res.json();
    return d.return_value ?? d.error;
};

// Пример:
await aiPHP(`return get_bloginfo("name") . " | PHP " . PHP_VERSION;`);'
); ?></pre>
            </div>

            <div class="wpae-card">
                <h2>Python</h2>
                <p>Пример для любого агента, который умеет делать HTTP-запросы.</p>
                <pre class="wpae-code"><?php echo esc_html(
'import requests

def wp_php(code: str) -> dict:
    return requests.post(
        "' . $site_url . '",
        headers={"X-AI-Key": "' . $key . '"},
        json={"code": code}
    ).json()

result = wp_php("return get_bloginfo(\'name\');")
print(result["return_value"])'
); ?></pre>
            </div>

            <div class="wpae-card wpae-card-wide">
                <h2>Рекомендуемая инструкция для агента</h2>
                <p>Эту инструкцию можно дать Codex, Claude Desktop или другому агенту перед работой с сайтом.</p>
                <h3>Получить guide</h3>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s "' . get_rest_url( null, 'ai-executor/v1/guide' ) . '" \\
  -H "X-AI-Key: ' . $key . '"'
); ?></pre>
                <h3>Инструкция агента</h3>
                <pre class="wpae-code wpae-code-light"><?php echo esc_html( wpae_agent_prompt() ); ?></pre>
            </div>

            <div class="wpae-card wpae-card-wide wpae-security">
                <strong>Безопасность</strong>
                <ul>
                    <li>Плагин может выполнять PHP, поэтому держите ключ в секрете.</li>
                    <li>Для production лучше задать ключ в <code>wp-config.php</code>: <code>define('WP_AI_EXECUTOR_KEY', 'your-key');</code></li>
                    <li>Дополнительно ограничьте доступ по IP на уровне сервера или firewall.</li>
                </ul>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.querySelectorAll('[data-wpae-color-target]').forEach(function (picker) {
            var input = document.getElementById(picker.getAttribute('data-wpae-color-target'));
            var pill = picker.closest('.wpae-color-field') ? picker.closest('.wpae-color-field').querySelector('.wpae-token-pill') : null;
            if (!input) return;
            picker.addEventListener('input', function () {
                input.value = picker.value.toUpperCase();
                if (pill) pill.textContent = input.value;
            });
            input.addEventListener('input', function () {
                if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
                    picker.value = input.value;
                }
                if (pill) pill.textContent = input.value;
            });
        });
    })();
    </script>
    <?php
}
