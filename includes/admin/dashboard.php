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

add_action( 'admin_bar_menu', function ( WP_Admin_Bar $admin_bar ): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $admin_bar->add_node( [
        'id' => 'wpae-settings',
        'title' => 'AI Executor',
        'href' => admin_url( 'options-general.php?page=wp-ai-executor' ),
        'meta' => [
            'title' => 'Настройки WP AI Executor',
        ],
    ] );
}, 80 );

add_action( 'admin_init', function () {
    register_setting( 'wpae_settings', 'wp_ai_executor_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    // Every mutation below is owner-only, including legacy dashboard forms.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

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
        isset( $_POST['wpae_save_vision_settings'] ) &&
        check_admin_referer( 'wpae_save_vision_settings' )
    ) {
        $input = isset( $_POST['wpae_vision'] ) && is_array( $_POST['wpae_vision'] )
            ? wp_unslash( $_POST['wpae_vision'] )
            : [];
        $result = wpae_update_vision_settings( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'vision_error' : 'vision_saved' ) . '=1' ) );
        exit;
    }

    if (
        isset( $_POST['wpae_save_llm_settings'] ) &&
        check_admin_referer( 'wpae_save_llm_settings' )
    ) {
        $input = isset( $_POST['wpae_llm'] ) && is_array( $_POST['wpae_llm'] )
            ? wp_unslash( $_POST['wpae_llm'] )
            : [];
        $result = wpae_update_llm_settings( $input );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&' . ( is_wp_error( $result ) ? 'llm_error' : 'llm_saved' ) . '=1' ) );
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
        $manifest = isset( $_POST['wpae_design_manifest'] ) && is_array( $_POST['wpae_design_manifest'] )
            ? wp_unslash( $_POST['wpae_design_manifest'] )
            : [];

        $tokens = wpae_sanitize_design_tokens( $input );
        $manifest = wpae_sanitize_design_system_manifest( $manifest );
        if ( ! wpae_validate_design_system_package( $manifest, $tokens )['ok'] ) {
            wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&design_system_error=1' ) );
            exit;
        }

        wpae_update_project_design_tokens( $tokens );
        wpae_update_design_system_manifest( $manifest );
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
        $manifest = [];

        if ( $raw_enforce !== '' ) {
            $decoded = json_decode( $raw_enforce, true );
            if ( is_array( $decoded ) ) {
                $enforce = $decoded;
            } else {
                wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&skill_error=1' ) );
                exit;
            }
        }

        $raw_manifest = isset( $_POST['wpae_skill_manifest'] )
            ? trim( (string) wp_unslash( $_POST['wpae_skill_manifest'] ) )
            : '';
        if ( $raw_manifest !== '' ) {
            $manifest = json_decode( $raw_manifest, true );
            if ( ! is_array( $manifest ) ) {
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
            'manifest' => $manifest,
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
    $design_system_error = isset( $_GET['design_system_error'] );
    $skill_saved        = isset( $_GET['skill_saved'] );
    $skill_deleted      = isset( $_GET['skill_deleted'] );
    $skill_error        = isset( $_GET['skill_error'] );
    $skill_imported     = isset( $_GET['skill_imported'] );
    $skill_import_error = isset( $_GET['skill_import_error'] );
    $skill_url_imported = isset( $_GET['skill_url_imported'] );
    $skill_url_error    = isset( $_GET['skill_url_error'] );
    $exports_pruned     = isset( $_GET['exports_pruned'] ) ? absint( $_GET['exports_pruned'] ) : null;
    $health_checked     = sanitize_key( (string) ( $_GET['health_checked'] ?? '' ) );
    $vision_saved       = isset( $_GET['vision_saved'] );
    $vision_error       = isset( $_GET['vision_error'] );
    $llm_saved           = isset( $_GET['llm_saved'] );
    $llm_error           = isset( $_GET['llm_error'] );
    $capabilities       = wpae_get_capability_settings();
    $capability_labels  = wpae_capability_labels();
    $capability_presets = wpae_capability_presets();
    $design_tokens      = wpae_get_project_design_tokens();
    $design_manifest    = wpae_get_design_system_manifest();
    $skills             = wpae_sort_skills( wpae_get_skill_store() );
    $operation_logs     = array_slice( wpae_get_operation_logs_store(), 0, 8 );
    $exports_summary    = wpae_build_exports_summary( wpae_get_export_store() );
    $block_library      = wpae_block_library_dashboard_summary();
    $block_library_url  = get_rest_url( null, 'ai-executor/v1/elementor/blocks' );
    $skill_bundle_json  = wp_json_encode( wpae_build_skill_bundle(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    $enabled_count      = count( array_filter( $capabilities ) );
    $total_count        = count( $capabilities );
    $filesystem_locked  = ! wpae_can_run_filesystem_operations();
    $vision_settings    = wpae_get_vision_settings();
    $vision_status      = wpae_get_vision_status();
    $vision_reports     = wpae_get_vision_reports( 5 );
    $vision_providers    = wpae_vision_provider_options();
    $llm_settings        = wpae_llm_get_settings();
    $llm_providers        = wpae_llm_provider_options();
    $llm_gemini_models    = wpae_llm_provider_model_options( 'gemini' );
    $llm_is_gemini        = $llm_settings['provider'] === 'gemini';
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
        .wpae-tabs {
            margin-top: 16px;
            overflow: hidden;
            border: 1px solid var(--wpae-border);
            border-radius: 8px;
            background: var(--wpae-panel);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .wpae-tabs__list {
            display: flex;
            width: 100%;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        .wpae-tab {
            position: relative;
            flex: 0 0 auto;
            min-height: 46px;
            padding: 0 17px;
            border: 0;
            border-right: 1px solid var(--wpae-border);
            background: transparent;
            color: var(--wpae-muted);
            cursor: pointer;
            font-size: 13px;
            font-weight: 650;
            line-height: 1;
        }
        .wpae-tab:last-child {
            border-right: 0;
        }
        .wpae-tab:hover {
            background: var(--wpae-panel-soft);
            color: var(--wpae-text);
        }
        .wpae-tab[aria-selected="true"] {
            background: #fff;
            color: var(--wpae-text);
        }
        .wpae-tab[aria-selected="true"]::after {
            position: absolute;
            right: 12px;
            bottom: 0;
            left: 12px;
            height: 3px;
            background: var(--wpae-accent);
            content: "";
        }
        .wpae-tab:focus-visible {
            z-index: 1;
            outline: 2px solid var(--wpae-accent);
            outline-offset: -3px;
        }
        .wpae-tab-panels:not(.is-ready) {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .wpae-tab-panel[hidden] {
            display: none;
        }
        .wpae-tab-panel.wpae-grid {
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
        .wpae-form-field { min-width: 0; }
        .wpae-form-field .wpae-input { font-family: inherit; font-size: 14px; }
        .wpae-section-note {
            display: block;
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
            .wpae-tab-panels:not(.is-ready),
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
            <div class="wpae-alert" role="status">Дизайн-система проекта сохранена.</div>
        <?php endif; ?>

        <?php if ( $design_system_error ) : ?>
            <div class="wpae-alert" role="alert" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Проверьте паспорт и HEX-цвета дизайн-системы.</div>
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

        <?php if ( $vision_saved ) : ?>
            <div class="wpae-alert" role="status">Настройки AI Vision сохранены.</div>
        <?php endif; ?>

        <?php if ( $vision_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось сохранить настройки AI Vision. Проверьте провайдера, модель и наличие OpenSSL.</div>
        <?php endif; ?>

        <?php if ( $llm_saved ) : ?>
            <div class="wpae-alert" role="status">Настройки LLM-провайдера сохранены.</div>
        <?php endif; ?>

        <?php if ( $llm_error ) : ?>
            <div class="wpae-alert" role="status" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">Не удалось сохранить LLM-провайдера. Проверьте HTTPS base URL, модель и наличие OpenSSL.</div>
        <?php endif; ?>

        <nav class="wpae-tabs" aria-label="Разделы настроек WP AI Executor">
            <div class="wpae-tabs__list" role="tablist" aria-orientation="horizontal">
                <button class="wpae-tab" id="wpae-tab-connection" type="button" role="tab" aria-selected="true" aria-controls="wpae-panel-connection" tabindex="0" data-wpae-tab-target="connection">Подключение</button>
                <button class="wpae-tab" id="wpae-tab-elementor" type="button" role="tab" aria-selected="false" aria-controls="wpae-panel-elementor" tabindex="-1" data-wpae-tab-target="elementor">Elementor</button>
                <button class="wpae-tab" id="wpae-tab-agents" type="button" role="tab" aria-selected="false" aria-controls="wpae-panel-agents" tabindex="-1" data-wpae-tab-target="agents">Агенты</button>
                <button class="wpae-tab" id="wpae-tab-llm" type="button" role="tab" aria-selected="false" aria-controls="wpae-panel-llm" tabindex="-1" data-wpae-tab-target="llm">LLM-агенты</button>
                <button class="wpae-tab" id="wpae-tab-vision" type="button" role="tab" aria-selected="false" aria-controls="wpae-panel-vision" tabindex="-1" data-wpae-tab-target="vision">AI Vision</button>
                <button class="wpae-tab" id="wpae-tab-monitoring" type="button" role="tab" aria-selected="false" aria-controls="wpae-panel-monitoring" tabindex="-1" data-wpae-tab-target="monitoring">Мониторинг</button>
                <button class="wpae-tab" id="wpae-tab-examples" type="button" role="tab" aria-selected="false" aria-controls="wpae-panel-examples" tabindex="-1" data-wpae-tab-target="examples">Примеры</button>
            </div>
        </nav>

        <div class="wpae-tab-panels" id="wpae-dashboard-sections">
            <?php wpae_render_health_dashboard_card( $health_url ); ?>

            <div class="wpae-card" data-wpae-tab="connection">
                <h2>REST endpoint</h2>
                <p>Основной адрес для выполнения PHP через защищенный REST API.</p>
                <label for="wpae-rest-url">REST URL</label>
                <div class="wpae-field-row" style="margin-top:6px">
                    <input class="wpae-input" id="wpae-rest-url" type="text" value="<?php echo esc_attr( $site_url ); ?>" readonly onclick="this.select()" />
                    <button type="button" class="button wpae-button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $site_url ); ?>');this.textContent='Скопировано';setTimeout(()=>this.textContent='Копировать',2000)">Копировать</button>
                </div>
            </div>

            <div class="wpae-card" data-wpae-tab="connection">
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

            <div class="wpae-card wpae-card-wide" data-wpae-tab="llm">
                <h2>Подключение LLM-агента</h2>
                <p>Единый OpenAI-compatible proxy поддерживает OpenAI, DeepSeek, OpenRouter, Gemini и другие сервисы с совместимым Chat Completions API. Ключ не передается в Elementor или браузер.</p>
                <div class="wpae-status-grid" style="padding:0;margin-top:12px">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Провайдер</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) $llm_settings['provider_label'] ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Модель</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) $llm_settings['model'] ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Ключ</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) $llm_settings['api_key_hint'] ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Лимит</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) WPAE_LLM_CALL_LIMIT ); ?> / <?php echo esc_html( (string) ( WPAE_LLM_CALL_WINDOW / 60 ) ); ?> мин</p>
                    </div>
                </div>

                <form method="post" style="margin-top:16px">
                    <?php wp_nonce_field( 'wpae_save_llm_settings' ); ?>
                    <input type="hidden" name="wpae_save_llm_settings" value="1" />
                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-llm-provider">Провайдер</label>
                            <select class="wpae-input" id="wpae-llm-provider" name="wpae_llm[provider]"><?php foreach ( $llm_providers as $provider_id => $provider ) : ?><option value="<?php echo esc_attr( $provider_id ); ?>" data-base-url="<?php echo esc_attr( (string) $provider['base_url'] ); ?>" data-model="<?php echo esc_attr( (string) $provider['model'] ); ?>" <?php selected( $llm_settings['provider'], $provider_id ); ?>><?php echo esc_html( $provider['label'] ); ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-llm-model">Модель</label>
                            <select class="wpae-input" id="wpae-llm-gemini-model" name="wpae_llm[model]" <?php disabled( ! $llm_is_gemini ); ?> <?php echo $llm_is_gemini ? '' : 'hidden'; ?>><?php foreach ( $llm_gemini_models as $model_id => $model_label ) : ?><option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $llm_settings['model'], $model_id ); ?>><?php echo esc_html( $model_label ); ?></option><?php endforeach; ?></select>
                            <input class="wpae-input" id="wpae-llm-model" name="wpae_llm[model]" type="text" value="<?php echo esc_attr( (string) $llm_settings['model'] ); ?>" autocomplete="off" <?php disabled( $llm_is_gemini ); ?> <?php echo $llm_is_gemini ? 'hidden' : ''; ?> />
                        </div>
                    </div>
                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-llm-base-url">HTTPS base URL</label>
                        <input class="wpae-input" id="wpae-llm-base-url" name="wpae_llm[base_url]" type="url" value="<?php echo esc_attr( (string) $llm_settings['base_url'] ); ?>" placeholder="https://api.example.com/v1" autocomplete="url" />
                        <span class="wpae-section-note">Для собственного шлюза укажите URL до версии API, например <code>https://provider.example/v1</code>. Endpoint <code>/chat/completions</code> добавляется автоматически.</span>
                    </div>
                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-llm-fallback-model">Резервная модель (fallback)</label>
                        <input class="wpae-input" id="wpae-llm-fallback-model" name="wpae_llm[fallback_model]" type="text" value="<?php echo esc_attr( (string) ( $llm_settings['fallback_model'] ?? '' ) ); ?>" placeholder="например, inclusionai/ling-3.0-flash-fin:free" autocomplete="off" />
                        <span class="wpae-section-note">Необязательно. Используется один раз, если основной пул вернёт rate limit. Оставьте пустым, чтобы отключить.</span>
                    </div>
                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-llm-api-key">API-ключ провайдера</label>
                        <input class="wpae-input" id="wpae-llm-api-key" name="wpae_llm[api_key]" type="password" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $llm_settings['has_api_key'] ) ? '••••••••••••' : 'Введите API-ключ провайдера' ); ?>" />
                        <span class="wpae-section-note">Ключ шифруется перед сохранением в <code>wp_options</code>. Промпты и ответы в плагине не сохраняются.</span>
                    </div>
                    <label class="wpae-toggle" style="margin-top:12px">
                        <input name="wpae_llm[clear_api_key]" type="checkbox" value="1" />
                        <span><strong>Удалить сохраненный API-ключ</strong><span>После этого чат вернет понятную ошибку конфигурации.</span></span>
                    </label>
                    <p style="margin-top:14px"><button type="submit" class="button button-primary wpae-button">Сохранить LLM</button></p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide" data-wpae-tab="vision">
                <h2>AI Vision</h2>
                <p>Дополнительная проверка desktop/mobile скриншотов. Vision не заменяет deterministic audits, Elementor editability и проверку публичной страницы.</p>
                <div class="wpae-status-grid" style="padding:0;margin-top:12px">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Доступ агента</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo ! empty( $vision_status['enabled'] ) ? 'Включен' : 'Выключен'; ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Провайдер</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) $vision_settings['provider_label'] ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Ключ</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) $vision_settings['api_key_hint'] ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Отчеты</p>
                        <p class="wpae-stat-value" style="font-size:16px"><?php echo esc_html( (string) $vision_status['report_count'] ); ?></p>
                    </div>
                </div>

                <form method="post" style="margin-top:16px">
                    <?php wp_nonce_field( 'wpae_save_vision_settings' ); ?>
                    <input type="hidden" name="wpae_save_vision_settings" value="1" />
                    <div class="wpae-form-grid">
                        <div class="wpae-form-field">
                            <label for="wpae-vision-provider">Провайдер</label>
                            <select class="wpae-input" id="wpae-vision-provider" name="wpae_vision[provider]">
                                <?php foreach ( $vision_providers as $provider_id => $provider ) : ?>
                                    <option value="<?php echo esc_attr( $provider_id ); ?>" <?php selected( $vision_settings['provider'], $provider_id ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="wpae-form-field">
                            <label for="wpae-vision-model">Модель</label>
                            <input class="wpae-input" id="wpae-vision-model" name="wpae_vision[model]" type="text" value="<?php echo esc_attr( (string) $vision_settings['model'] ); ?>" autocomplete="off" />
                        </div>
                    </div>
                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-vision-api-key">API-ключ провайдера</label>
                        <input class="wpae-input" id="wpae-vision-api-key" name="wpae_vision[api_key]" type="password" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $vision_settings['has_api_key'] ) ? '••••••••••••' : 'Введите API-ключ провайдера' ); ?>" />
                        <span class="wpae-section-note">Ключ шифруется перед сохранением в <code>wp_options</code>. Изображения и сырые ответы провайдера не сохраняются. Для полного отключения ключа отметьте очистку ниже.</span>
                    </div>
                    <label class="wpae-toggle" style="margin-top:12px">
                        <input name="wpae_vision[clear_api_key]" type="checkbox" value="1" />
                        <span><strong>Удалить сохраненный API-ключ</strong><span>После этого /vision/analyze будет возвращать понятную ошибку конфигурации.</span></span>
                    </label>
                    <p style="margin-top:14px"><button type="submit" class="button button-primary wpae-button">Сохранить AI Vision</button></p>
                </form>

                <?php if ( ! empty( $vision_reports ) ) : ?>
                    <h3>Последние отчеты</h3>
                    <?php foreach ( $vision_reports as $vision_report ) : ?>
                        <p style="margin:6px 0">
                            <strong><?php echo esc_html( (string) ( $vision_report['vision_score'] ?? 0 ) ); ?>/100</strong>
                            <span class="wpae-token-pill"><?php echo esc_html( (string) ( $vision_report['viewport'] ?? 'viewport не указан' ) ); ?></span>
                            <span><?php echo esc_html( (string) ( $vision_report['created_at'] ?? '' ) ); ?></span>
                            <?php if ( ! empty( $vision_report['report_id'] ) ) : ?><code><?php echo esc_html( (string) $vision_report['report_id'] ); ?></code><?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="wpae-card wpae-card-wide" data-wpae-tab="elementor">
                <h2>Библиотека Elementor-блоков</h2>
                <p>
                    Постоянное хранилище любых native Elementor JSON-блоков и шаблонов, включая импортированные с других сайтов.
                    В редакторе Elementor используйте контекстное меню контейнера: «Копировать как JSON» или «Сохранить в библиотеку WP AI Executor».
                </p>
                <div class="wpae-status-grid" style="padding:0;margin-top:12px">
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Сохранено блоков</p>
                        <p class="wpae-stat-value"><?php echo esc_html( (string) ( $block_library['count'] ?? 0 ) ); ?></p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Форматы</p>
                        <p class="wpae-stat-value" style="font-size:16px">Native + WPAE</p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Режимы</p>
                        <p class="wpae-stat-value" style="font-size:16px">3</p>
                    </div>
                    <div class="wpae-stat">
                        <p class="wpae-stat-label">Хранилище</p>
                        <p class="wpae-stat-value" style="font-size:16px">Private CPT</p>
                    </div>
                </div>
                <label for="wpae-block-library-url" style="display:block;margin-top:14px">REST API библиотеки</label>
                <input class="wpae-input" id="wpae-block-library-url" type="text" value="<?php echo esc_attr( $block_library_url ); ?>" readonly onclick="this.select()" />
                <?php if ( ! empty( $block_library['items'] ) ) : ?>
                    <div style="margin-top:14px">
                        <h3>Последние блоки</h3>
                        <?php foreach ( $block_library['items'] as $library_item ) : ?>
                            <p style="margin:6px 0">
                                <strong>#<?php echo esc_html( (string) $library_item['id'] ); ?> <?php echo esc_html( $library_item['title'] ); ?></strong>
                                <span class="wpae-token-pill"><?php echo esc_html( $library_item['category'] ); ?></span>
                                <?php if ( empty( $library_item['compatibility']['raw_valid'] ) ) : ?>
                                    <span class="wpae-token-pill">нужна совместимость</span>
                                <?php endif; ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wpae-card wpae-card-wide" data-wpae-tab="monitoring">
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

            <div class="wpae-card wpae-card-wide" data-wpae-tab="agents">
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

            <div class="wpae-card wpae-card-wide" data-wpae-tab="elementor">
                <h2>Дизайн-токены проекта</h2>
                <p>
                    Эти настройки попадают в <code>/guide</code>, <code>/capabilities</code>, <code>/elementor/design-system</code> и <code>/elementor/blueprint</code>.
                    Агент обязан использовать их как единую дизайн-систему для новых страниц и новых блоков.
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wpae_save_design_tokens' ); ?>
                    <input type="hidden" name="wpae_save_design_tokens" value="1" />

                    <h3>Паспорт дизайн-системы</h3>
                    <div class="wpae-form-grid">
                        <?php foreach ( [
                            'name' => 'Название',
                            'version' => 'Версия',
                            'provenance' => 'Происхождение',
                            'source_url' => 'URL источника',
                            'license' => 'Лицензия',
                        ] as $field => $label ) : ?>
                            <div class="wpae-form-field">
                                <label for="wpae-design-manifest-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
                                <input class="wpae-input"
                                    id="wpae-design-manifest-<?php echo esc_attr( $field ); ?>"
                                    name="wpae_design_manifest[<?php echo esc_attr( $field ); ?>]"
                                    type="<?php echo $field === 'source_url' ? 'url' : 'text'; ?>"
                                    required
                                    value="<?php echo esc_attr( (string) ( $design_manifest[ $field ] ?? '' ) ); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>

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
                        <button type="submit" class="button button-primary wpae-button">Сохранить дизайн-систему</button>
                    </p>
                </form>
            </div>

            <div class="wpae-card wpae-card-wide" data-wpae-tab="agents">
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

                    <div class="wpae-form-field" style="margin-top:12px">
                        <label for="wpae-skill-manifest">Skill Manifest JSON (необязательно)</label>
                        <textarea class="wpae-textarea" id="wpae-skill-manifest" name="wpae_skill_manifest" style="min-height:150px" placeholder='{"format":"wpae-skill-manifest-v1","version":"1.0.0","capabilities":["elementor_read"],"inputs":["subject"],"pipeline":[{"method":"POST","endpoint":"/elementor/blueprint"}],"license":"MIT"}'></textarea>
                        <span class="wpae-section-note">Pipeline принимает только разрешённые WPAE endpoints. Shell, /run, self-update, MCP, WP-CLI и browser-admin запрещены.</span>
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
                                        <?php if ( is_array( $skill['manifest'] ?? null ) ) : ?>
                                            · manifest: <?php echo esc_html( (string) ( $skill['manifest']['version'] ?? '1.0.0' ) ); ?>
                                            · capabilities: <?php echo esc_html( (string) count( (array) ( $skill['manifest']['capabilities'] ?? [] ) ) ); ?>
                                            · pipeline: <?php echo esc_html( (string) count( (array) ( $skill['manifest']['pipeline'] ?? [] ) ) ); ?>
                                        <?php endif; ?>
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

            <div class="wpae-card" data-wpae-tab="connection">
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

            <div class="wpae-card" data-wpae-tab="monitoring">
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

            <div class="wpae-card" data-wpae-tab="examples">
                <h2>Пример curl</h2>
                <p>Минимальный запрос к `/run`. Для write endpoints также нужен guide token.</p>
                <pre class="wpae-code"><?php echo esc_html(
'curl -s -X POST "' . $site_url . '" \\
  -H "Content-Type: application/json" \\
  -H "X-AI-Key: ' . $key . '" \\
  -d \'{"code": "return get_bloginfo(\'name\');"}\''
); ?></pre>
            </div>

            <div class="wpae-card" data-wpae-tab="examples">
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

            <div class="wpae-card" data-wpae-tab="examples">
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

            <div class="wpae-card wpae-card-wide" data-wpae-tab="examples">
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

            <div class="wpae-card wpae-card-wide wpae-security" data-wpae-tab="examples">
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
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-wpae-tab-target]'));
        var panelHost = document.getElementById('wpae-dashboard-sections');
        var tabKeys = tabs.map(function (tab) {
            return tab.getAttribute('data-wpae-tab-target');
        });
        var panels = {};

        if (panelHost && tabs.length) {
            tabKeys.forEach(function (key) {
                var panel = document.createElement('section');
                panel.className = 'wpae-tab-panel wpae-grid';
                panel.id = 'wpae-panel-' + key;
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', 'wpae-tab-' + key);
                panels[key] = panel;
                panelHost.appendChild(panel);
            });

            Array.prototype.slice.call(panelHost.querySelectorAll(':scope > [data-wpae-tab]')).forEach(function (card) {
                var key = card.getAttribute('data-wpae-tab');
                if (panels[key]) {
                    panels[key].appendChild(card);
                }
            });

            panelHost.classList.add('is-ready');

            var activateTab = function (key, moveFocus) {
                if (!panels[key]) return;

                tabs.forEach(function (tab) {
                    var selected = tab.getAttribute('data-wpae-tab-target') === key;
                    tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                    tab.setAttribute('tabindex', selected ? '0' : '-1');
                    if (selected && moveFocus) tab.focus();
                });

                tabKeys.forEach(function (panelKey) {
                    panels[panelKey].hidden = panelKey !== key;
                });

                try {
                    window.localStorage.setItem('wpae-dashboard-tab', key);
                } catch (error) {
                    // Storage is optional; tab navigation still works without it.
                }

                if (window.location.hash !== '#wpae-panel-' + key) {
                    window.history.replaceState(null, '', '#wpae-panel-' + key);
                }
            };

            var hashKey = window.location.hash.indexOf('#wpae-panel-') === 0
                ? window.location.hash.replace('#wpae-panel-', '')
                : '';
            var savedKey = '';
            try {
                savedKey = window.localStorage.getItem('wpae-dashboard-tab') || '';
            } catch (error) {
                savedKey = '';
            }

            activateTab(tabKeys.indexOf(hashKey) !== -1 ? hashKey : (tabKeys.indexOf(savedKey) !== -1 ? savedKey : tabKeys[0]), false);

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    activateTab(tab.getAttribute('data-wpae-tab-target'), false);
                });
                tab.addEventListener('keydown', function (event) {
                    var nextIndex = index;
                    if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
                    if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
                    if (event.key === 'Home') nextIndex = 0;
                    if (event.key === 'End') nextIndex = tabs.length - 1;
                    if (nextIndex === index && ['ArrowRight', 'ArrowLeft', 'Home', 'End'].indexOf(event.key) === -1) return;
                    event.preventDefault();
                    activateTab(tabs[nextIndex].getAttribute('data-wpae-tab-target'), true);
                });
            });
        }

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

        var llmProvider = document.getElementById('wpae-llm-provider');
        var llmBaseUrl = document.getElementById('wpae-llm-base-url');
        var llmModel = document.getElementById('wpae-llm-model');
        var llmGeminiModel = document.getElementById('wpae-llm-gemini-model');
        if (llmProvider && llmBaseUrl && llmModel) {
            var syncLlmProvider = function (resetModel) {
                var option = llmProvider.options[llmProvider.selectedIndex];
                var custom = llmProvider.value === 'custom';
                var gemini = llmProvider.value === 'gemini';
                llmBaseUrl.value = option.getAttribute('data-base-url') || '';
                llmBaseUrl.readOnly = !custom;
                llmBaseUrl.setAttribute('aria-readonly', custom ? 'false' : 'true');
                if (llmGeminiModel) {
                    if (gemini) {
                        var hasGeminiModel = Array.prototype.some.call(llmGeminiModel.options, function (item) { return item.value === llmModel.value; });
                        if (resetModel || !hasGeminiModel) llmGeminiModel.value = option.getAttribute('data-model') || llmGeminiModel.options[0].value;
                        llmGeminiModel.disabled = false;
                        llmGeminiModel.hidden = false;
                        llmModel.disabled = true;
                        llmModel.hidden = true;
                    } else {
                        llmGeminiModel.disabled = true;
                        llmGeminiModel.hidden = true;
                        llmModel.disabled = false;
                        llmModel.hidden = false;
                        if (resetModel) llmModel.value = option.getAttribute('data-model') || '';
                    }
                } else if (resetModel) {
                    llmModel.value = option.getAttribute('data-model') || '';
                }
            };
            syncLlmProvider(false);
            llmProvider.addEventListener('change', function () { syncLlmProvider(true); });
        }
    })();
    </script>
    <?php
}
