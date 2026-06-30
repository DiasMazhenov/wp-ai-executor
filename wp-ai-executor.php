<?php
/**
 * Plugin Name: WP AI Executor
 * Description: Secure REST endpoint for AI automation (Claude, GPT, Gemini, Qwen, etc.). Execute PHP in WordPress context via any AI agent.
 * Version:     1.3.5
 * Author:      DIAS
 * License:     MIT
 */

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

// ── REST routes ────────────────────────────────────────────────────────────────
add_action( 'rest_api_init', function () {

    register_rest_route( 'ai-executor/v1', '/run', [
        'methods'             => 'POST',
        'callback'            => 'wpae_run',
        'permission_callback' => 'wpae_auth',
    ] );

    register_rest_route( 'ai-executor/v1', '/key', [
        'methods'             => 'GET',
        'callback'            => fn() => new WP_REST_Response( [ 'key' => wpae_get_key() ], 200 ),
        'permission_callback' => fn() => in_array( $_SERVER['REMOTE_ADDR'] ?? '', [ '127.0.0.1', '::1', 'localhost' ], true ),
    ] );

    register_rest_route( 'ai-executor/v1', '/guide', [
        'methods'             => 'GET',
        'callback'            => 'wpae_get_guide',
        'permission_callback' => 'wpae_auth',
    ] );

} );

function wpae_auth( WP_REST_Request $r ): bool {
    $provided = $r->get_header( 'X-AI-Key' )
             ?? $r->get_header( 'X-Claude-Key' )
             ?? $r->get_param( 'key' );
    return hash_equals( wpae_get_key(), (string) $provided );
}

function wpae_run( WP_REST_Request $request ) {
    $code = trim( (string) $request->get_param( 'code' ) );
    if ( $code === '' ) {
        return new WP_REST_Response( [ 'error' => 'No code provided' ], 400 );
    }

    $forbidden_file_operation = wpae_detect_forbidden_file_operation( $code );
    if (
        $forbidden_file_operation &&
        ! ( defined( 'WP_AI_EXECUTOR_ALLOW_FILE_WRITES' ) && WP_AI_EXECUTOR_ALLOW_FILE_WRITES )
    ) {
        return new WP_REST_Response( [
            'error' => 'Filesystem writes are disabled by WP AI Executor policy.',
            'blocked_operation' => $forbidden_file_operation,
            'help' => 'Use WordPress APIs for posts, post meta, options, Elementor data, and cache clearing. Do not create temporary loaders, mu-plugins, PHP/JS/CSS/JSON files, or files in /tmp.',
        ], 403 );
    }

    $elementor_before = wpae_capture_elementor_data_snapshot();

    ob_start();
    $result = null;
    try {
        $fn     = eval( 'return function() { ' . $code . ' };' );
        $result = $fn();
    } catch ( Throwable $e ) {
        ob_end_clean();
        return new WP_REST_Response( [ 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() ], 500 );
    }
    $output = ob_get_clean();

    $elementor_validation = wpae_validate_changed_elementor_data( $elementor_before );
    if ( ! $elementor_validation['ok'] ) {
        return new WP_REST_Response( [
            'error' => 'Invalid Elementor data blocked by WP AI Executor policy.',
            'details' => $elementor_validation['errors'],
            'rolled_back_post_ids' => $elementor_validation['rolled_back_post_ids'],
            'output' => $output,
        ], 422 );
    }

    return new WP_REST_Response( [ 'return_value' => $result, 'output' => $output ], 200 );
}

function wpae_detect_forbidden_file_operation( string $code ): ?string {
    $patterns = [
        'file_put_contents',
        'fopen',
        'fwrite',
        'fputs',
        'mkdir',
        'rmdir',
        'unlink',
        'rename',
        'copy',
        'touch',
        'chmod',
        'chown',
        'chgrp',
        'symlink',
        'link',
        'move_uploaded_file',
        'ZipArchive',
        'Phar',
        'WP_Filesystem',
        'wp_mkdir_p',
        'wp_delete_file',
        'wp_tempnam',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
        'popen',
    ];

    foreach ( $patterns as $pattern ) {
        if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $code ) ) {
            return $pattern;
        }
    }

    $path_patterns = [
        '/tmp/',
        'wp-content/mu-plugins',
        'wp-content/plugins',
        'wp-content/themes',
        'landing_data.b64',
        'elem-loader.php',
    ];

    foreach ( $path_patterns as $pattern ) {
        if ( stripos( $code, $pattern ) !== false ) {
            return $pattern;
        }
    }

    return null;
}

function wpae_capture_elementor_data_snapshot(): array {
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id ASC",
            '_elementor_data'
        ),
        ARRAY_A
    );

    $snapshot = [];
    foreach ( $rows as $row ) {
        $snapshot[ (int) $row['post_id'] ] = (string) $row['meta_value'];
    }

    return $snapshot;
}

function wpae_validate_changed_elementor_data( array $before ): array {
    $after = wpae_capture_elementor_data_snapshot();
    $errors = [];
    $rolled_back_post_ids = [];

    foreach ( $after as $post_id => $raw_data ) {
        if ( array_key_exists( $post_id, $before ) && $before[ $post_id ] === $raw_data ) {
            continue;
        }

        $post_errors = wpae_validate_elementor_data_string( $raw_data );
        if ( empty( $post_errors ) ) {
            continue;
        }

        $errors[] = [
            'post_id' => $post_id,
            'errors' => $post_errors,
        ];

        if ( array_key_exists( $post_id, $before ) ) {
            update_post_meta( $post_id, '_elementor_data', wp_slash( $before[ $post_id ] ) );
        } else {
            delete_post_meta( $post_id, '_elementor_data' );
        }
        $rolled_back_post_ids[] = $post_id;
    }

    return [
        'ok' => empty( $errors ),
        'errors' => $errors,
        'rolled_back_post_ids' => $rolled_back_post_ids,
    ];
}

function wpae_validate_elementor_data_string( string $raw_data ): array {
    $data = json_decode( $raw_data, true );
    if ( ! is_array( $data ) ) {
        return [ 'Elementor _elementor_data must be valid JSON array data.' ];
    }

    $errors = [];
    wpae_validate_elementor_elements_recursive( $data, 'root', $errors );
    return $errors;
}

function wpae_validate_elementor_elements_recursive( array $elements, string $path, array &$errors ): void {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            $errors[] = "{$path}.{$index}: element must be an object/array.";
            continue;
        }

        $element_path = $path . '.' . ( $element['id'] ?? $index );
        $el_type = $element['elType'] ?? null;

        if ( $el_type === 'section' || $el_type === 'column' ) {
            $errors[] = "{$element_path}: legacy Elementor elType={$el_type} is forbidden; use elType=container.";
        }

        if ( array_key_exists( 'widget_type', $element ) ) {
            $errors[] = "{$element_path}: widget_type is forbidden; use camelCase widgetType.";
        }

        if ( $el_type === 'widget' && empty( $element['widgetType'] ) ) {
            $errors[] = "{$element_path}: widget element must have non-empty camelCase widgetType.";
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_validate_elementor_elements_recursive( $element['elements'], $element_path, $errors );
        }
    }
}

function wpae_get_guide(): WP_REST_Response {
    return new WP_REST_Response( wpae_agent_guide(), 200 );
}

function wpae_agent_guide(): array {
    return [
        'name' => 'WP AI Executor Agent Guide',
        'version' => '1.1.5',
        'purpose' => 'Use this guide before automating WordPress and Elementor through WP AI Executor.',
        'embedded_skill_packs' => [
            'frontend_design' => 'Distilled frontend-design rules for distinctive visual direction, typography, layout, motion, and copy.',
            'wordpress_elementor_dev' => 'Distilled WordPress/Elementor development rules for native Elementor data, REST execution, security, and verification.',
        ],
        'agent_prompt' => wpae_agent_prompt(),
        'workflow' => [
            '1. Inspect WordPress, PHP, theme, and Elementor status with a small read-only PHP request.',
            '2. For page work, create or update a WordPress page and write Elementor metadata.',
            '3. Use native Elementor Flexbox Containers only for layout: elType=container plus native widgets. Never use legacy elType=section or elType=column.',
            '4. Never create external files. Use WordPress APIs and database metadata only; no temp files, loaders, mu-plugins, PHP/JS/CSS/JSON files, or filesystem writes.',
            '5. Design the page before building: define subject, audience, job, palette, type roles, layout, and one signature element.',
            '6. Verify with HTTP status, permalink, post status, _elementor_edit_mode, _elementor_data, visible HTML text, and inspect any html widgets if present.',
        ],
        'frontend_design' => [
            'principles' => [
                'Avoid generic template aesthetics; ground the visual direction in the subject matter.',
                'Open with a hero that states a clear design thesis.',
                'Choose a compact color system, intentional typography roles, and one memorable signature element.',
                'Use structure as information, not decoration. Numbering should mean sequence.',
                'Keep copy specific, active, and useful from the visitor side of the screen.',
                'Spend boldness in one place; keep the rest disciplined and responsive.',
            ],
            'anti_generic_defaults' => [
                'Do not default to a generic hero, generic gradient cards, generic dark SaaS page, or one-note palette.',
                'Do not use decorative numbering unless the content is actually sequential.',
                'Do not use stock-like filler sections; every section must move the visitor toward the page job.',
                'Do not stop at a technically valid layout; evaluate whether the page looks intentionally designed.',
            ],
            'planning_template' => [
                'subject' => 'What is being sold or explained?',
                'audience' => 'Who must understand and act?',
                'single_job' => 'What should the page make the visitor do?',
                'palette' => '4-6 named hex colors.',
                'type_roles' => 'Display, body, and utility/caption roles.',
                'layout' => 'Short section map or wireframe.',
                'signature' => 'One distinctive element justified by the brief.',
            ],
            'design_quality_bar' => [
                'Hero must be the design thesis and should contain the page signature.',
                'Typography must use deliberate roles: display, body, utility/caption.',
                'Layout must be stable at desktop, tablet, and mobile widths.',
                'Motion must support comprehension: reveal, hover, progress, or focused ambient animation.',
                'Copy must be concrete and action-oriented.',
            ],
        ],
        'wordpress_elementor' => [
            'stack' => [
                'Remote WordPress site',
                'Elementor only',
                'WP AI Executor as the automation bridge',
                'No Oxygen',
                'No Novamira',
                'HTML widget only for small JS snippets or complex CSS that cannot reasonably be expressed through Elementor settings',
                'Avoid browser automation unless absolutely required',
            ],
            'filesystem_policy' => [
                'required' => true,
                'rule' => 'Do not create, modify, rename, copy, chmod, or delete files on the WordPress server through WP AI Executor.',
                'forbidden' => [
                    'Temporary loaders such as elem-loader.php.',
                    'Files in /tmp, wp-content/mu-plugins, wp-content/plugins, wp-content/themes, or uploads created as implementation scratch space.',
                    'External PHP, JS, CSS, JSON, base64, cache, or helper files.',
                    'Direct filesystem or shell/process calls such as file_put_contents, fopen, fwrite, mkdir, unlink, rename, copy, chmod, ZipArchive, Phar, WP_Filesystem, wp_mkdir_p, exec, shell_exec, system, passthru, proc_open, and popen.',
                ],
                'allowed_instead' => [
                    'Use wp_insert_post, wp_update_post, update_post_meta, update_option, delete_option, and Elementor metadata.',
                    'Use Elementor cache APIs to clear/regenerate generated CSS; do not write CSS files manually.',
                    'Return data directly from /run instead of writing temporary files.',
                ],
                'runtime_enforcement' => 'By default, /run rejects common filesystem write/delete operations unless WP_AI_EXECUTOR_ALLOW_FILE_WRITES is explicitly defined by the site owner.',
            ],
            'runtime_elementor_validation' => [
                'required' => true,
                'rule' => 'The executor validates changed _elementor_data after each /run call and rejects invalid Elementor JSON even if the agent ignored the guide.',
                'blocked' => [
                    'Legacy elType=section.',
                    'Legacy elType=column.',
                    'Snake-case widget_type.',
                    'Any elType=widget element with missing or empty widgetType.',
                ],
                'rollback' => 'If invalid _elementor_data is detected, the changed _elementor_data meta is rolled back to its pre-run value when possible.',
            ],
            'native_elementor_first' => [
                'required' => true,
                'rule' => 'Build page structure and content from native Elementor Flexbox Containers and widgets so the user can edit content and styling in the Elementor editor panel. Use elType=container for all layout nodes.',
                'layout_system' => [
                    'required' => 'Elementor Flexbox Containers only.',
                    'allowed_layout_eltypes' => [
                        'container',
                    ],
                    'forbidden_legacy_eltypes' => [
                        'section',
                        'column',
                    ],
                    'rule' => 'Do not create, import, or preserve legacy Section/Column layouts. Convert every layout wrapper to nested containers with flex_direction, content_width, width, gap, padding, and responsive settings.',
                ],
                'allowed_widget_types' => [
                    'heading',
                    'text-editor',
                    'button',
                    'icon-list',
                    'image',
                    'image-box',
                    'icon-box',
                    'divider',
                    'spacer',
                    'counter',
                    'progress',
                    'testimonial',
                    'tabs',
                    'accordion',
                    'toggle',
                ],
                'forbidden_widget_types' => [
                    'shortcode',
                ],
                'conditional_widget_types' => [
                    'html' => 'Allowed only for small JavaScript snippets or complex CSS enhancements that cannot reasonably be expressed with native Elementor settings. Never use it as the main page markup/content/layout container.',
                ],
                'content_placement' => [
                    'Headlines must live in heading widget settings.title.',
                    'Body copy must live in text-editor widget settings.editor.',
                    'Calls to action must live in button widget settings.text and settings.link.',
                    'Lists must live in icon-list repeater settings when practical.',
                    'Cards, columns, grids, hero panels, and sections must be Flexbox Containers with settings and child widgets.',
                    'Critical visual state required for readability must live in native Elementor settings first: background_color, text color, border, border_radius, padding, margin, width, min-height, gap, and alignment.',
                ],
                'forbidden_patterns' => [
                    'Do not create temporary loader files, mu-plugins, helper PHP files, external CSS/JS files, JSON/base64 payload files, or scratch files anywhere on the server.',
                    'Do not use legacy Elementor sections or columns: elType=section and elType=column are forbidden.',
                    'Do not put full page markup into an Elementor HTML widget.',
                    'Do not use inline CSS/JS blobs to fake the main page structure.',
                    'Do not replace editable Elementor controls with opaque HTML.',
                    'Do not rely on enhancement CSS as the only source for essential backgrounds, contrast, spacing, or card borders.',
                ],
                'verification' => [
                    'Confirm the solution did not create or require any external files.',
                    'Traverse _elementor_data recursively.',
                    'Confirm all layout elements are elType=container and all content elements are allowed native widgets.',
                    'Confirm there are zero elements with elType=section or elType=column.',
                    'If html widgets exist, confirm each one is limited to JS or complex CSS enhancements, not page content/layout.',
                    'Confirm important text lives in heading/text-editor/button/icon-list settings.',
                    'Confirm any visually critical card, panel, hero, or dark section has native Elementor background and border settings before relying on CSS.',
                ],
            ],
            'html_enhancement_policy' => [
                'allowed' => true,
                'allowed_for' => [
                    'Google Fonts or other font loading when the site has no better typography pipeline.',
                    'Scoped CSS polish for classed Elementor containers/widgets.',
                    'Small JavaScript interactions such as scroll reveal, hover helpers, tabs state, counters, or progressive enhancement.',
                    'Complex responsive CSS that Elementor settings cannot express cleanly.',
                ],
                'requirements' => [
                    'Scope CSS under project-specific classes, e.g. .wpae-* or page-specific .mz-*.',
                    'Do not target .elementor-widget-container as the primary selector.',
                    'Use !important only as an enhancement fallback for scoped selectors when Elementor/theme CSS wins specificity.',
                    'Do not use !important to compensate for missing native Elementor settings on critical backgrounds, colors, borders, spacing, or layout.',
                    'Respect prefers-reduced-motion for animation.',
                    'Use vanilla JavaScript in an IIFE; avoid jQuery unless WordPress/Elementor dependency forces it.',
                    'HTML widget must be enhancement-only and removable without losing the page content.',
                ],
            ],
            'page_meta' => [
                '_elementor_edit_mode' => 'builder',
                '_elementor_template_type' => 'wp-page',
                '_elementor_version' => 'Use ELEMENTOR_VERSION when defined.',
                '_elementor_data' => 'JSON-encoded Elementor element array, stored with wp_slash().',
                '_wp_page_template' => 'elementor_canvas for full landing pages when appropriate.',
            ],
            'element_shape' => [
                'container' => [
                    'id' => 'unique 7-8 character string',
                    'elType' => 'container',
                    'isInner' => false,
                    'settings' => 'Container/Flexbox settings.',
                    'elements' => 'Nested widgets or containers.',
                ],
                'widget' => [
                    'id' => 'unique 7-8 character string',
                    'elType' => 'widget',
                    'widgetType' => 'Required camelCase key. Native editable Elementor widget, e.g. heading, text-editor, button, icon-list, image, divider, spacer. HTML widget is allowed only for JS or complex CSS enhancements, never for main layout/content.',
                    'isInner' => false,
                    'settings' => 'Widget control values.',
                    'elements' => [],
                ],
            ],
            'elementor_data_rules' => [
                'Use recursive arrays of containers and widgets.',
                'Every element needs id, elType, isInner, settings, and elements.',
                'Every widget element must use the exact camelCase key widgetType. Never use widget_type, widget_type_name, type, or name as substitutes.',
                'If elType is widget and widgetType is missing or empty, Elementor will render an empty/broken widget; treat this as a blocker.',
                'Never emit legacy elType=section or elType=column. If source data contains them, convert to nested elType=container before saving.',
                'Use Flexbox Container settings for layout: flex_direction, content_width, width, min_height, gap, padding, margin, justify_content, align_items, flex_wrap, and responsive variants.',
                'Use deterministic short ids when possible so future updates can target stable elements.',
                'Use _css_classes in settings to attach scoped enhancement styles.',
                'For readable sections and cards, duplicate essential styling in settings before adding CSS: background_background, background_color, title/text colors, border_border, border_color, border_width, border_radius, padding, margin, gap, width, min-height, and alignment.',
                'Clear/regenerate Elementor CSS cache after writing page data when Elementor classes are available.',
            ],
            'verification_checklist' => [
                'HTTP status for permalink is 200.',
                'Post status is publish unless the user requested draft.',
                '_wp_page_template is elementor_canvas for full landing pages when appropriate.',
                '_elementor_edit_mode is builder.',
                '_elementor_data decodes as JSON array.',
                '_elementor_data contains no legacy section or column elements.',
                'Every elType=widget element has non-empty widgetType and no widget_type key.',
                'Core text is stored in native widget settings, not opaque HTML.',
                'Any html widget is enhancement-only.',
                'Critical backgrounds, borders, spacing, and contrast are present in native Elementor settings, with CSS only refining or reinforcing them.',
                'No external files, temporary loaders, mu-plugins, scratch files, or filesystem writes were created or required.',
                'Desktop and mobile layout should not have obvious overlap or horizontal overflow.',
            ],
            'php_snippet' => wpae_elementor_page_snippet(),
        ],
        'security' => [
            'Treat X-AI-Key as root access to WordPress.',
            'Never commit, log, or expose real keys in frontend code.',
            'Never create temporary loaders, mu-plugins, PHP/JS/CSS/JSON/base64 files, or scratch files on the server.',
            'Filesystem write/delete operations are blocked by default in /run; do not ask agents to bypass this.',
            'Prefer server/firewall IP restrictions for production.',
            'Run read-only checks before writes and verify after writes.',
        ],
    ];
}

function wpae_agent_prompt(): string {
    return <<<'PROMPT'
You are operating a remote WordPress site through WP AI Executor.
Before writing, fetch and follow this guide as the source of truth. Inspect the environment first. Never create external files on the WordPress server: no temporary loaders, mu-plugins, helper PHP files, CSS/JS/JSON/base64 payload files, scratch files, or files in /tmp. Use WordPress APIs and Elementor metadata only; /run blocks common filesystem write/delete operations by default. For Elementor pages, design first: define subject, audience, single page job, palette, type roles, layout, and one distinctive signature element. Apply the embedded frontend_design pack to avoid generic pages, and apply the wordpress_elementor_dev pack to build editable Elementor output. Use only native Elementor Flexbox Containers for layout: elType=container plus editable native widgets. Never use legacy Elementor Sections or Columns; elType=section and elType=column are forbidden and must be converted to containers before saving. Every widget must use the exact camelCase widgetType key; widget_type is forbidden and causes empty widgets. Put critical backgrounds, readable text colors, borders, spacing, dimensions, and alignment into native Elementor settings first; scoped CSS, including selective !important, may reinforce or refine them but must not be the only source of essential contrast or layout. The Elementor HTML widget is allowed only for small JavaScript snippets or complex CSS enhancements when native settings are not enough; never use it as the main page markup/content/layout container. Do not use shortcode widgets, Oxygen, or Novamira for page layout/content. After writing, run the verification checklist: published URL, Elementor meta, decoded _elementor_data, zero section/column elements, no external files, native widget content placement, native critical visual settings, and html widgets enhancement-only. Do not expose API keys.
PROMPT;
}

function wpae_elementor_page_snippet(): string {
    return <<<'PHP'
$page_id = wp_insert_post([
    'post_title'  => 'Landing Page',
    'post_name'   => 'landing-page',
    'post_status' => 'publish',
    'post_type'   => 'page',
], true);

if ( is_wp_error( $page_id ) ) {
    return [ 'ok' => false, 'error' => $page_id->get_error_message() ];
}

$elementor_data = [
    [
        'id'       => 'hero01',
        'elType'   => 'container',
        'isInner'  => false,
        'settings' => [
            'content_width'  => 'boxed',
            'flex_direction' => 'column',
        ],
        'elements' => [
            [
                'id'         => 'title01',
                'elType'     => 'widget',
                'widgetType' => 'heading',
                'isInner'    => false,
                'settings'   => [
                    'title'       => 'Landing headline',
                    'header_size' => 'h1',
                ],
                'elements'   => [],
            ],
        ],
    ],
];

update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );

return [ 'ok' => true, 'id' => $page_id, 'url' => get_permalink( $page_id ) ];
PHP;
}

// ── Settings page ──────────────────────────────────────────────────────────────
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

    // Handle key regeneration
    if (
        isset( $_POST['wpae_regenerate'] ) &&
        check_admin_referer( 'wpae_regenerate_key' )
    ) {
        update_option( 'wp_ai_executor_key', bin2hex( random_bytes( 32 ) ) );
        wp_redirect( admin_url( 'options-general.php?page=wp-ai-executor&regenerated=1' ) );
        exit;
    }
} );

function wpae_settings_page() {
    $key      = wpae_get_key();
    $site_url = get_rest_url( null, 'ai-executor/v1/run' );
    $regen    = isset( $_GET['regenerated'] );
    ?>
    <div class="wrap">
        <h1>⚡ WP AI Executor</h1>
        <p style="color:#666">Universal REST endpoint for AI automation. Works with Claude, GPT, Gemini, Qwen, and any AI agent that can make HTTP requests.</p>

        <?php if ( $regen ) : ?>
            <div class="notice notice-success is-dismissible"><p>✅ Secret key regenerated successfully.</p></div>
        <?php endif; ?>

        <!-- Endpoint -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:20px 0">
            <h2 style="margin-top:0">📡 Endpoint</h2>
            <label style="display:block;font-weight:600;margin-bottom:6px">REST URL</label>
            <div style="display:flex;gap:8px">
                <input type="text" value="<?php echo esc_attr( $site_url ); ?>" readonly
                    style="width:100%;font-family:monospace;background:#f6f7f7;padding:8px 12px;border:1px solid #ccc;border-radius:4px"
                    onclick="this.select()" />
                <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $site_url ); ?>');this.textContent='✅ Copied!';setTimeout(()=>this.textContent='Copy',2000)"
                    class="button">Copy</button>
            </div>
        </div>

        <!-- Secret Key -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:20px 0">
            <h2 style="margin-top:0">🔑 Secret Key</h2>
            <p style="color:#666;margin-top:0">Send this key in the <code>X-AI-Key</code> header with every request.</p>

            <div style="display:flex;gap:8px;align-items:center">
                <input type="text" id="wpae-key" value="<?php echo esc_attr( $key ); ?>" readonly
                    style="width:100%;font-family:monospace;background:#f6f7f7;padding:8px 12px;border:1px solid #ccc;border-radius:4px"
                    onclick="this.select()" />
                <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $key ); ?>');this.textContent='✅ Copied!';setTimeout(()=>this.textContent='Copy',2000)"
                    class="button">Copy</button>
            </div>

            <form method="post" style="margin-top:12px" onsubmit="return confirm('Regenerate secret key? All AI agents using the old key will need to be updated.')">
                <?php wp_nonce_field( 'wpae_regenerate_key' ); ?>
                <input type="hidden" name="wpae_regenerate" value="1" />
                <button type="submit" class="button button-secondary" style="color:#b32d2e;border-color:#b32d2e">
                    🔄 Regenerate Key
                </button>
            </form>
        </div>

        <!-- Usage examples -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:20px 0">
            <h2 style="margin-top:0">📖 Usage Examples</h2>

            <h3>curl (production / no browser)</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px"><?php echo esc_html(
'curl -s -X POST "' . $site_url . '" \\
  -H "Content-Type: application/json" \\
  -H "X-AI-Key: ' . $key . '" \\
  -d \'{"code": "return get_bloginfo(\'name\');"}\''
); ?></pre>

            <h3>JavaScript / Browser (local dev)</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px"><?php echo esc_html(
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

// Example:
await aiPHP(`return get_bloginfo("name") . " | PHP " . PHP_VERSION;`);'
); ?></pre>

            <h3>Python (any AI agent)</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px"><?php echo esc_html(
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

        <!-- Agent guide -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:20px 0">
            <h2 style="margin-top:0">🧭 Agent Guide</h2>
            <p style="color:#666;margin-top:0">Authenticated guidance endpoint for Codex, Claude, GPT, Gemini, Qwen, and other agents before they automate Elementor pages.</p>

            <label style="display:block;font-weight:600;margin-bottom:6px">Guide URL</label>
            <div style="display:flex;gap:8px">
                <input type="text" value="<?php echo esc_attr( get_rest_url( null, 'ai-executor/v1/guide' ) ); ?>" readonly
                    style="width:100%;font-family:monospace;background:#f6f7f7;padding:8px 12px;border:1px solid #ccc;border-radius:4px"
                    onclick="this.select()" />
                <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( get_rest_url( null, 'ai-executor/v1/guide' ) ); ?>');this.textContent='✅ Copied!';setTimeout(()=>this.textContent='Copy',2000)"
                    class="button">Copy</button>
            </div>

            <h3>curl</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px"><?php echo esc_html(
'curl -s "' . get_rest_url( null, 'ai-executor/v1/guide' ) . '" \\
  -H "X-AI-Key: ' . $key . '"'
); ?></pre>

            <h3>Recommended agent instruction</h3>
            <pre style="background:#f6f7f7;color:#1d2327;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px;white-space:pre-wrap"><?php echo esc_html( wpae_agent_prompt() ); ?></pre>
        </div>

        <!-- Security notes -->
        <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:16px;margin:20px 0">
            <strong>⚠️ Security notes</strong>
            <ul style="margin:8px 0 0 16px">
                <li>This plugin executes arbitrary PHP — keep the key secret.</li>
                <li>For extra security, hard-code the key in <code>wp-config.php</code>: <code>define('WP_AI_EXECUTOR_KEY', 'your-key');</code></li>
                <li>Consider restricting access by IP at the server/firewall level on production.</li>
            </ul>
        </div>
    </div>
    <?php
}
