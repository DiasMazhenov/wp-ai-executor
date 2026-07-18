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

function wpae_get_capabilities_payload(): array {
    $settings = wpae_get_capability_settings();

    return [
        'plugin_version' => WPAE_VERSION,
        'guide_version' => 'v02.05.45',
        'auth' => [
            'canonical_header' => 'X-AI-Key',
            'deprecated_aliases' => [
                'X-WPAE-API-Key' => 'Accepted for backwards compatibility only. New agents must use X-AI-Key.',
            ],
            'warning_header_for_alias' => 'X-WPAE-Auth-Warning',
        ],
        'capability_toggles' => $settings,
        'can_execute_php' => ! empty( $settings['run'] ),
        'can_write_files_via_run' => wpae_can_run_filesystem_operations(),
        'can_self_update_plugin' => ! empty( $settings['self_update'] ),
        'can_self_update_plugin_package' => ! empty( $settings['self_update'] ) && class_exists( 'ZipArchive' ),
        'can_write_elementor' => ! empty( $settings['elementor_writes'] ),
        'can_upload_media' => ! empty( $settings['media_upload'] ),
        'can_create_exports' => ! empty( $settings['exports'] ),
        'can_manage_skills' => ! empty( $settings['manage_skills'] ),
        'can_import_export_skills' => ! empty( $settings['manage_skills'] ),
        'can_audit' => true,
        'can_visual_audit_public_page' => true,
        'can_visual_audit_elementor' => true,
        'can_rollback' => true,
        'can_list_rollback_snapshots' => true,
        'can_restore_elementor_revisions' => true,
        'can_view_operation_logs' => true,
        'can_diagnose_wordpress' => true,
        'can_score_agent_conformance' => true,
        'can_create_design_system' => true,
        'can_provide_project_design_tokens' => true,
        'can_run_elementor_preflight' => true,
        'can_return_after_save_quality_summary' => true,
        'can_run_atomic_elementor_transactions' => true,
        'requires_design_system_before_elementor_build' => true,
        'requires_mobile_first_design' => true,
        'requires_native_style_settings_first' => true,
        'requires_preserving_existing_enhancements' => true,
        'can_migrate_design_system_markers' => true,
        'forbids_wp_admin_credentials' => true,
        'forbids_browser_automation_writes' => true,
        'allows_browser_public_verification_only' => true,
        'run_elementor_data_writes_enforce_design_system' => true,
        'requires_guide_token_for_writes' => true,
        'guide_token_storage' => [
            'session_storage' => 'wp_options_per_session_with_legacy_index',
            'token_storage' => 'wp_options_per_token_hash',
            'ack_body_formats' => [ 'application/json', 'raw_json_body_fallback', 'form_fields' ],
        ],
        'wordpress_health' => [
            'endpoint' => 'GET /wp-json/ai-executor/v1/health',
            'authentication' => 'X-AI-Key',
            'modes' => [
                'quick' => 'Local read-only checks with a 30-second cache.',
                'deep' => 'Explicit same-site loopback and REST checks with five-second timeouts; cached for five minutes.',
            ],
            'deep_concurrency' => 'A 15-second lock prevents parallel deep checks from multiplying PHP-FPM load.',
            'checks' => [
                'WordPress bootstrap latency',
                'database connectivity and latency',
                'autoloaded options size',
                'PHP memory and runtime',
                'WP-Cron backlog',
                'disk space',
                'debug configuration and debug.log size',
                'cached update availability',
                'recent AI Executor REST latency',
                'WordPress loopback and REST API in deep mode',
            ],
            'side_effects' => 'Read-only. It does not restart services, clear caches, delete logs, or change WordPress settings.',
            'limitation' => 'No plugin endpoint can answer while all PHP-FPM workers are blocked; use server metrics and logs for that condition.',
        ],
        'project_design_tokens' => wpae_get_project_design_tokens(),
        'project_design_system' => wpae_build_project_design_system(),
        'embedded_jezweb_claude_skills' => wpae_get_jezweb_claude_skills_pack(),
        'visual_audit' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/visual-audit',
            'scope' => 'Read-only public HTML audit for same-site post_id or url.',
            'checks' => [
                'public fetch status',
                'viewport/title/visible copy',
                'fixed width/min-width overflow risks',
                'invisible text patterns',
                'empty Elementor/WPAE blocks',
                'CTA presence',
                'mobile-first CSS signals',
            ],
            'limitations' => [
                'No server-side screenshots.',
                'No true rendered overflow measurement.',
                'No full computed CSS contrast cascade.',
            ],
            'browser_followup_required_for' => [
                'desktop/mobile screenshots',
                'real click/tap checks',
                'rendered contrast',
                'animation behavior',
            ],
        ],
        'elementor' => [
            'enabled_for_writes' => ! empty( $settings['elementor_writes'] ),
            'safe_endpoints' => [
                'validate' => 'POST /wp-json/ai-executor/v1/elementor/validate',
                'normalize' => 'POST /wp-json/ai-executor/v1/elementor/normalize',
                'blueprint' => 'POST /wp-json/ai-executor/v1/elementor/blueprint',
                'design_system' => 'POST /wp-json/ai-executor/v1/elementor/design-system',
                'recipes' => 'GET /wp-json/ai-executor/v1/elementor/recipes',
                'recipe' => 'GET /wp-json/ai-executor/v1/elementor/recipes/{id}',
                'compose' => 'POST /wp-json/ai-executor/v1/elementor/compose',
                'visual_audit' => 'POST /wp-json/ai-executor/v1/elementor/visual-audit',
                'editability_audit' => 'POST /wp-json/ai-executor/v1/elementor/editability-audit',
                'public_visual_audit' => 'POST /wp-json/ai-executor/v1/visual-audit',
                'revisions' => 'GET /wp-json/ai-executor/v1/elementor/revisions',
                'restore_revision' => 'POST /wp-json/ai-executor/v1/elementor/restore-revision',
                'css_to_native' => 'POST /wp-json/ai-executor/v1/elementor/css-to-native',
                'page' => 'POST /wp-json/ai-executor/v1/elementor/page',
                'update' => 'POST /wp-json/ai-executor/v1/elementor/update',
                'patch' => 'POST /wp-json/ai-executor/v1/elementor/patch',
            ],
            'can_normalize' => true,
            'can_blueprint' => true,
            'can_use_recipes' => true,
            'can_compose_sections' => true,
            'must_use_flex_containers' => true,
            'forbidden_eltypes' => [ 'section', 'column' ],
            'required_widget_key' => 'widgetType',
            'forbidden_widget_keys' => [ 'widget_type' ],
            'runtime_validation' => true,
            'static_visual_audit' => true,
            'preflight_before_writes' => true,
            'transaction_write_mode' => [
                'mode' => 'atomic',
                'default_for' => [ '/elementor/page', '/elementor/update', '/elementor/patch' ],
                'auto_rollback_on' => [
                    'metadata_save_error',
                    'invalid_saved_elementor_json',
                    'post_save_contract_failure',
                    'cache_clear_failure',
                    'public_verification_failure_when_requested',
                    'visual_regression_failure_when_requested',
                    'strict_quality_failure_when_requested',
                ],
                'optional_request_flags' => [
                    'transaction_verify_public' => 'When true, fetch and audit the public permalink after save; failure triggers auto-rollback.',
                    'transaction_visual_regression' => 'When true on existing-page writes, capture a public HTML/audit baseline before write and auto-rollback if key public signals regress after save.',
                    'transaction_strict_quality' => 'When true, weak/blocked static quality after save triggers auto-rollback.',
                ],
                'response_fields' => [
                    'transaction',
                    'transaction.checks',
                    'transaction.failed_checks',
                    'transaction.auto_rollback',
                    'rollback_snapshot_id',
                    'rollback_expires_at',
                    'quality_summary',
                ],
            ],
            'protected_enhancement_zones' => [
                'enabled' => true,
                'marker' => 'wpae-protected-zone CSS class or data-wpae-protected attribute.',
                'automatic_detection' => 'HTML widgets containing WebGL, Three.js, GSAP, canvas, shader, or Babylon enhancement code.',
                'guarded_endpoints' => [ '/elementor/page', '/elementor/update', '/elementor/patch', '/elementor/css-to-native' ],
                'default' => 'Block removal or modification of an existing protected zone.',
                'explicit_override' => [
                    'allow_protected_zone_changes' => true,
                    'protected_zone_reason' => 'Concrete reason of at least 10 characters.',
                ],
            ],
            'after_save_quality_summary' => true,
            'visual_regression_gate' => [
                'enabled' => true,
                'request_flag' => 'transaction_visual_regression',
                'scope' => 'Existing post updates and patches. New pages have no previous public baseline.',
                'signals' => [ 'HTTP status', 'visible text length', 'CTA presence', 'overflow risks', 'empty block risks', 'public audit level' ],
                'rollback' => 'A failed requested regression check is part of the atomic transaction and triggers rollback.',
            ],
            'editability_tests' => [
                'enabled' => true,
                'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/editability-audit',
                'purpose' => 'Verify that Elementor-supported design properties are stored in native widget/container settings and are not primarily controlled by HTML widget CSS or script-injected styles.',
                'checks' => [ 'native typography coverage', 'native color coverage', 'native spacing coverage', 'native flex/background settings', 'HTML widget CSS overrides', 'script-injected native CSS' ],
            ],
            'css_to_native_migrator' => [
                'enabled' => true,
                'endpoint' => 'POST /wp-json/ai-executor/v1/elementor/css-to-native',
                'default_safety' => 'Use dry_run=true first. The migrator only moves confidently mapped declarations from HTML widget <style> rules that target Elementor element ids.',
                'supported_properties' => [ 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-transform', 'color', 'background-color', 'border-color', 'padding', 'margin', 'border-radius', 'min-height', 'gap', 'flex-direction', 'justify-content', 'align-items', 'flex-wrap', 'z-index' ],
                'skips' => [ 'protected HTML widgets', 'selectors without Elementor element ids', 'unsupported values', 'script-injected style blocks' ],
            ],
            'design_system_contract_enforced_on_writes' => true,
            'design_system_marker_migration' => true,
            'mobile_first_design_required' => true,
            'native_style_settings_required' => true,
            'preserve_existing_enhancements_required' => true,
            'requires_elementor_editor_editable_design' => true,
            'native_style_policy' => [
                'required' => true,
                'rule' => 'Change element styling through native Elementor settings/style controls first; CSS is allowed only for exceptional enhancements that Elementor cannot express.',
                'principle' => 'Every design property that Elementor supports natively must be represented in widget/container settings so the site owner can edit it in Elementor. Native settings are the editable source of truth; do not remove them unless the user explicitly asks for global inheritance/design-token control.',
                'editor_editability_contract' => [
                    'Editable design means the property is stored in Elementor element settings/controls and saved in _elementor_data, not hidden only in Custom CSS, an HTML widget, an external file, or injected markup.',
                    'Do not strip native settings to make a property editable. Native widget/container settings are what makes the property editable in the Elementor panel.',
                    'Only remove local native overrides when the user explicitly asks that a property inherit from global Elementor styles/design tokens.',
                    'If a property from css_to_native_map appears in <script>-injected CSS, it is not editable in Elementor. Migrate it to native settings and remove the injected CSS declaration.',
                    'For existing pages, migrate only the requested properties; preserve unrelated working styles, classes, IDs, JS, WebGL, Three.js, GSAP, canvas, and animation code.',
                ],
                'custom_html_policy' => [
                    'allowed' => true,
                    'rule' => 'Custom HTML widgets may be edited only when the requested change concerns their enhancement purpose. They are allowed for JavaScript, WebGL/canvas, Three.js, GSAP, embeds, schema/meta, and CSS that has no native Elementor equivalent.',
                    'css_only_rule' => 'For CSS-only enhancement, use the target element settings.custom_css first. Do not create an HTML widget merely to hold CSS.',
                    'separation_rule' => 'Put WebGL, Three.js, GSAP, canvas, shader, and complex animation code in a dedicated HTML widget or protected enhancement zone, separate from page-wide design CSS.',
                    'preserve_rule' => 'Do not modify, delete, merge, or relocate an existing WebGL/Three.js/GSAP/canvas HTML widget unless the user explicitly asks to change that enhancement.',
                    'forbidden_as_bypass' => 'Custom HTML must not be used as a workaround for Elementor-editable typography, colors, backgrounds, spacing, borders, radius, flex layout, sizing, or responsive values.',
                ],
                'css_to_native_map_contract' => 'Properties in css_to_native_map must be set through native Elementor settings. They must not be the design source in Custom CSS, HTML widget CSS, external CSS files, or <script>-injected CSS.',
                'element_custom_css' => [
                    'supported' => true,
                    'settings_key' => 'custom_css',
                    'storage' => 'Store per-widget/per-container Custom CSS in the target element settings.custom_css so it remains editable in Elementor.',
                    'selector_rule' => 'Use Elementor selector to scope rules to the selected element, e.g. selector { ... }, selector::before { ... }, selector:hover { ... }, or selector .child { ... }.',
                    'native_first' => 'If Elementor exposes a visual control for the property, use that control as the source of truth. settings.custom_css is only for unsupported selectors, states, effects, animations, browser fixes, or responsive behavior.',
                    'preferred_over' => [ 'HTML widget CSS', 'page-wide CSS for one element', 'external CSS files', 'JavaScript-injected style elements' ],
                    'preserve_existing' => 'Unrelated settings.custom_css must be preserved byte-for-byte during targeted updates.',
                    'safe_html_migration' => 'Use /elementor/patch op=replace_text with an exact expected_count for surgical changes inside large protected HTML/WebGL widgets; do not resend their complete HTML value.',
                    'forbidden' => 'Do not override an available Elementor control with conflicting settings.custom_css declarations or !important.',
                ],
                'css_to_native_map' => [
                    'typography' => [
                        'font-family' => 'typography_font_family',
                        'font-size' => 'typography_font_size plus typography_font_size_mobile and typography_font_size_tablet when needed',
                        'font-weight' => 'typography_font_weight',
                        'line-height' => 'typography_line_height; use unitless ratio for multi-line headings/text',
                        'letter-spacing' => 'typography_letter_spacing',
                        'text-transform' => 'typography_text_transform',
                    ],
                    'colors' => [
                        'color' => 'title_color or text_color',
                        'background-color' => 'background_background plus background_color',
                        'linear/radial gradient' => 'background_background=gradient plus background_color, background_color_b, background_gradient_type, background_gradient_angle, and background_gradient_position where supported',
                        'background overlay gradient' => 'background_overlay_background=gradient plus background_overlay_color, background_overlay_color_b, and related overlay gradient settings where supported',
                        'border-color' => 'border_border plus border_color',
                    ],
                    'advanced_positioning' => [
                        'z-index' => '_z_index or z_index control/settings when Elementor exposes it for the element',
                        'position fixed/sticky' => 'Elementor Advanced/Positioning or Motion Effects sticky settings such as _position, sticky, sticky_on, sticky_offset, sticky_effects_offset, and sticky_parent when available',
                    ],
                    'spacing_and_size' => [
                        'padding' => 'padding including responsive breakpoints',
                        'margin' => 'margin',
                        'border-radius' => 'border_radius',
                        'min-height' => 'min_height',
                    ],
                    'flex_grid' => [
                        'display:flex' => 'flex_direction, justify_content, align_items',
                        'gap' => 'gap',
                        'flex-wrap' => 'flex_wrap',
                    ],
                ],
                'native_first_settings' => [
                    'background_background',
                    'background_color',
                    'background_color_b',
                    'background_gradient_type',
                    'background_gradient_angle',
                    'background_gradient_position',
                    'background_overlay_background',
                    'background_overlay_color',
                    'background_overlay_color_b',
                    '_background_background',
                    '_background_color',
                    '_z_index',
                    'z_index',
                    '_position',
                    'sticky',
                    'sticky_on',
                    'sticky_offset',
                    'sticky_effects_offset',
                    'sticky_parent',
                    'title_color',
                    'text_color',
                    'button_text_color',
                    'button_background_color',
                    'border_border',
                    'border_color',
                    'border_width',
                    'border_radius',
                    'padding',
                    'margin',
                    'gap',
                    'width',
                    'min_height',
                    'align_items',
                    'justify_content',
                    'flex_direction',
                    'flex_wrap',
                    'typography_font_family',
                    'typography_font_size',
                    'typography_font_weight',
                    'typography_line_height',
                    'typography_letter_spacing',
                    'typography_text_transform',
                    'responsive mobile/tablet variants',
                ],
                'css_exception_rule' => 'Use scoped CSS only for animations, pseudo-elements, WebGL/canvas styling, gradients/patterns that Elementor Group_Control_Background cannot express, fixed/sticky global overlays or off-canvas UI when native positioning controls are insufficient, custom systemic z-index layers when native z-index controls are insufficient, Google Fonts @import, media queries that responsive controls cannot express, complex responsive behavior, hover/focus refinements, browser fixes, or theme specificity conflicts after native settings are set. The script-injected CSS validator inspects only static CSS declarations assigned to an injected style node; it excludes JavaScript object properties, @import, :root custom properties, and documented @media/@keyframes/@supports/@container exceptions.',
                'cache_rule' => 'After changing native Elementor settings, clear Elementor CSS cache: delete_post_meta(post_id, "_elementor_css"), delete_option("_elementor_global_css"), Elementor files cache when available, and rocket_clean_domain() when WP Rocket exists.',
                'forbidden' => [
                    'CSS-only backgrounds, contrast, spacing, borders, typography, or layout when native Elementor settings can express them.',
                    'Full page or block styling hidden inside an HTML widget instead of editable Elementor controls.',
                    'Unscoped global CSS for page-specific styling.',
                    'Injecting CSS via <script>createElement("style") for any property that has a mapping in css_to_native_map.',
                ],
                'validation_checks' => [
                    [
                        'code' => 'html_widget_script_injected_css',
                        'severity' => 'blocking',
                        'check' => 'Detect <script> tags in HTML widgets that inject <style> targeting properties listed in css_to_native_map.',
                        'message' => 'HTML widget contains static <script>-injected CSS declarations for native properties. Migrate only those declarations to widget/container native settings.',
                    ],
                ],
            ],
            'mobile_first_policy' => [
                'required' => true,
                'rule' => 'Design and validate the mobile layout first, then enhance tablet and desktop.',
                'native_settings' => [
                    'Set mobile flex direction, wrapping, order, width, min-height, padding, gap, and alignment explicitly where the section can collapse.',
                    'Keep primary CTA visible without horizontal scrolling.',
                    'Use readable mobile type sizes and comfortable tap targets.',
                    'Avoid desktop-first split/grid compositions that are only patched after saving.',
                ],
                'verification' => 'Run visual audit with mobile readiness in mind before writing and verify the public page at mobile width before desktop polish.',
            ],
            'wp_admin_editing_forbidden' => true,
            'playwright_editing_forbidden' => true,
            'browser_verification_scope' => 'Browser automation may inspect public pages after writes, but must not log in to WP Admin, open Elementor editor for edits, request admin credentials, or rely on WP cookies/nonces.',
            'run_bypass_blocked' => true,
            'run_elementor_data_policy' => 'Direct _elementor_data writes through /run are not a bypass; changed Elementor data is validated with the same design-system and preflight contract and rolled back on failure.',
            'unit_policy' => [
                'spacing_and_type' => 'Prefer rem/em.',
                'height' => 'Prefer vh/svh/min-height for viewport sections.',
                'width' => 'Prefer %, flex basis, max-width, and responsive constraints.',
                'px_exceptions' => 'Hairline borders, tiny controls/icons, shadows, and Elementor compatibility exceptions.',
            ],
            'supports_dry_run' => [ '/elementor/page', '/elementor/update', '/elementor/patch' ],
        ],
        'rollback' => [
            'endpoint' => 'POST /wp-json/ai-executor/v1/rollback',
            'requires_guide_token' => true,
            'ttl_seconds' => WPAE_ROLLBACK_TTL_SECONDS,
            'max_snapshots' => WPAE_ROLLBACK_MAX_SNAPSHOTS,
            'storage' => 'wp_options',
            'run_snapshot_request' => [
                'rollback_targets' => [
                    'post_ids' => [ 123 ],
                    'option_names' => [ 'some_option_name' ],
                ],
            ],
        ],
        'skills' => [
            'storage' => 'wp_options',
            'safe_endpoints' => [
                'list' => 'GET /wp-json/ai-executor/v1/skills',
                'save_one' => 'POST /wp-json/ai-executor/v1/skills',
                'delete_one' => 'DELETE /wp-json/ai-executor/v1/skills/{id}',
                'export_bundle' => 'GET /wp-json/ai-executor/v1/skills/export',
                'import_bundle' => 'POST /wp-json/ai-executor/v1/skills/import',
                'import_from_github_url' => 'POST /wp-json/ai-executor/v1/skills/import-url',
            ],
            'import_modes' => [ 'merge', 'replace' ],
            'github_import' => [
                'storage' => 'Downloaded skills are normalized and stored in wp_options; no server files are created.',
                'accepted_urls' => [
                    'https://github.com/{owner}/{repo}/blob/{ref}/path/SKILL.md',
                    'https://github.com/{owner}/{repo}/tree/{ref}/path/to/skill-folder',
                    'https://raw.githubusercontent.com/{owner}/{repo}/{ref}/path/SKILL.md',
                    'https://raw.githubusercontent.com/{owner}/{repo}/{ref}/bundle.json',
                ],
            ],
            'bundle_schema' => 'wp-ai-executor.skill-bundle',
            'max_skills_per_bundle' => 100,
            'max_content_bytes_per_skill' => 120000,
            'enforce_rule_types' => [
                'forbid_elementor_eltype',
                'require_widget_key',
                'forbid_widget_key',
                'allow_widget_type',
                'forbid_widget_type',
                'require_widget_setting',
                'require_container_setting',
                'forbid_html_pattern',
            ],
        ],
        'exports' => [
            'storage' => 'wp_options',
            'ttl_seconds' => WPAE_EXPORT_TTL_SECONDS,
            'max_entries' => WPAE_EXPORT_MAX_ENTRIES,
            'endpoints' => [
                'summary' => 'GET /wp-json/ai-executor/v1/exports',
                'create' => 'POST /wp-json/ai-executor/v1/exports/create',
                'fetch_one' => 'GET /wp-json/ai-executor/v1/exports/{id}',
                'prune_expired' => 'POST /wp-json/ai-executor/v1/exports/prune',
            ],
            'file_policy' => 'Exports are never public uploads files; prune removes expired wp_options entries only.',
        ],
        'operation_logs' => [
            'endpoint' => 'GET /wp-json/ai-executor/v1/logs',
            'storage' => 'wp_options',
            'max_entries' => WPAE_OPERATION_LOG_MAX_ENTRIES,
            'logged_fields' => [
                'time',
                'method',
                'endpoint',
                'status',
                'duration_ms',
                'actor',
                'ip_hash',
                'guide_hash',
                'target_ids',
                'rollback_snapshot_id',
                'validation_result',
            ],
            'redaction' => [
                'api_keys',
                'guide_tokens',
                'raw_request_bodies',
                'raw_page_payloads',
                'raw_response_payloads',
                'secrets',
            ],
        ],
        'agent_conformance' => [
            'enabled' => true,
            'returned_in_responses' => true,
            'included_in_logs' => true,
            'levels' => [ 'strong', 'acceptable', 'weak', 'blocked' ],
            'criteria' => [
                'guide_token_flow',
                'file_policy',
                'elementor_policy',
                'native_flex_containers',
                'widget_type_integrity',
                'native_visual_settings',
                'static_visual_audit',
                'design_system_consistency',
                'typography_hierarchy',
                'spacing_consistency',
                'cta_visibility',
                'mobile_readiness',
                'palette_quality',
                'content_completeness',
                'responsive_unit_policy',
                'verification_signal',
            ],
            'design_quality_gates' => [
                'typography_hierarchy' => 'Use enough native heading widgets to show page structure.',
                'spacing_consistency' => 'Put padding and gap into native container settings, not only CSS.',
                'cta_visibility' => 'Use a native button widget for the primary action.',
                'mobile_readiness' => 'Design mobile first and include responsive Elementor settings for complex layouts.',
                'palette_quality' => 'Use a deliberate palette with detectable color variety.',
                'content_completeness' => 'Avoid empty native heading/text widgets and sparse placeholder content.',
                'responsive_unit_policy' => 'Prefer rem/em for spacing/type, vh/svh for height, and percentages/flex constraints for width.',
            ],
            'score_meaning' => [
                '90_100' => 'strong',
                '75_89' => 'acceptable',
                '50_74' => 'weak',
                '0_49' => 'blocked',
            ],
        ],
        'repeated_agent_error_audit' => [
            'enabled' => true,
            'returned_on' => [ '/audit', '/elementor/visual-audit' ],
            'checks' => [
                'legacy_sections_columns',
                'snake_case_widget_type',
                'html_widget_layout_or_content',
                'script_injected_native_css',
                'heading_typography_important_override',
                'excessive_local_typography_overrides',
                'design_system_marker_drift',
                'fixed_px_layout_risk',
            ],
            'purpose' => 'Surfaces repeated external-agent mistakes as concrete machine-readable checks with safe next fixes.',
        ],
        'file_write_policy' => [
            'forbidden_in_run' => [ 'php_files', 'mu_plugins', 'tmp_files', 'arbitrary_paths', 'shell_commands' ],
            'filesystem_override_enabled' => wpae_can_run_filesystem_operations(),
            'allowed_endpoints' => [
                '/self-update' => ! empty( $settings['self_update'] ),
                '/self-update-package' => ! empty( $settings['self_update'] ) && class_exists( 'ZipArchive' ),
                '/media/upload' => ! empty( $settings['media_upload'] ),
                '/exports' => true,
                '/exports/create' => ! empty( $settings['exports'] ),
                '/exports/prune' => ! empty( $settings['exports'] ),
                '/elementor/validate' => true,
                '/elementor/normalize' => true,
                '/elementor/blueprint' => true,
                '/elementor/design-system' => true,
                '/elementor/recipes' => true,
                '/elementor/compose' => true,
                '/elementor/visual-audit' => true,
                '/elementor/editability-audit' => true,
                '/elementor/typography-unlock' => ! empty( $settings['elementor_writes'] ),
                '/elementor/resolve-typography-overrides' => ! empty( $settings['elementor_writes'] ),
                '/elementor/css-to-native' => ! empty( $settings['elementor_writes'] ),
                '/elementor/revisions' => true,
                '/elementor/restore-revision' => ! empty( $settings['elementor_writes'] ),
                '/visual-audit' => true,
                '/elementor/page' => ! empty( $settings['elementor_writes'] ),
                '/elementor/update' => ! empty( $settings['elementor_writes'] ),
                '/elementor/patch' => ! empty( $settings['elementor_writes'] ),
                '/audit' => true,
                '/logs' => true,
                '/rollback' => true,
                '/skills' => ! empty( $settings['manage_skills'] ),
                '/skills/export' => true,
                '/skills/import' => ! empty( $settings['manage_skills'] ),
                '/skills/import-url' => ! empty( $settings['manage_skills'] ),
            ],
        ],
        'guide_token_protocol' => [
            'session_endpoint' => 'POST /wp-json/ai-executor/v1/guide/session',
            'ack_endpoint' => 'POST /wp-json/ai-executor/v1/guide/ack',
            'required_headers_for_writes' => [ 'X-WPAE-Guide-Token', 'X-WPAE-Guide-Hash' ],
            'ttl_minutes' => 15,
            'storage' => 'Guide sessions are stored as individual wp_options plus a legacy index; guide tokens are also stored by token hash for write validation.',
            'ack_body_formats' => [
                'JSON body with guide_session_id and ack object.',
                'Raw JSON body fallback even if Content-Type is missing or wrong.',
                'Form fields: guide_session_id plus required ack fields.',
            ],
        ],
    ];
}

function wpae_get_capabilities(): WP_REST_Response {
    return new WP_REST_Response( wpae_get_capabilities_payload(), 200 );
}
