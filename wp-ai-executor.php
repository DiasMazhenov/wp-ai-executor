<?php
/**
 * Plugin Name: WP AI Executor
 * Description: Secure REST endpoint for AI automation (Claude, GPT, Gemini, Qwen, etc.). Execute PHP in WordPress context via any AI agent.
 * Version:     1.2.0
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
    ob_start();
    $result = null;
    try {
        $fn     = eval( 'return function() { ' . $code . ' };' );
        $result = $fn();
    } catch ( Throwable $e ) {
        ob_end_clean();
        return new WP_REST_Response( [ 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() ], 500 );
    }
    return new WP_REST_Response( [ 'return_value' => $result, 'output' => ob_get_clean() ], 200 );
}

function wpae_get_guide(): WP_REST_Response {
    return new WP_REST_Response( wpae_agent_guide(), 200 );
}

function wpae_agent_guide(): array {
    return [
        'name' => 'WP AI Executor Agent Guide',
        'version' => '1.0.0',
        'purpose' => 'Use this guide before automating WordPress and Elementor through WP AI Executor.',
        'agent_prompt' => wpae_agent_prompt(),
        'workflow' => [
            '1. Inspect WordPress, PHP, theme, and Elementor status with a small read-only PHP request.',
            '2. For page work, create or update a WordPress page and write Elementor metadata.',
            '3. Prefer Elementor Containers/Flexbox over legacy section-column layouts.',
            '4. Design the page before building: define subject, audience, job, palette, type roles, layout, and one signature element.',
            '5. Verify with HTTP status, permalink, post status, _elementor_edit_mode, _elementor_data, and visible HTML text.',
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
            'planning_template' => [
                'subject' => 'What is being sold or explained?',
                'audience' => 'Who must understand and act?',
                'single_job' => 'What should the page make the visitor do?',
                'palette' => '4-6 named hex colors.',
                'type_roles' => 'Display, body, and utility/caption roles.',
                'layout' => 'Short section map or wireframe.',
                'signature' => 'One distinctive element justified by the brief.',
            ],
        ],
        'wordpress_elementor' => [
            'stack' => [
                'Remote WordPress site',
                'Elementor only',
                'WP AI Executor as the automation bridge',
                'No Oxygen',
                'No Novamira',
                'Avoid browser automation unless absolutely required',
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
                    'widgetType' => 'heading, text-editor, button, html, icon-list, etc.',
                    'isInner' => false,
                    'settings' => 'Widget control values.',
                    'elements' => [],
                ],
            ],
            'php_snippet' => wpae_elementor_page_snippet(),
        ],
        'security' => [
            'Treat X-AI-Key as root access to WordPress.',
            'Never commit, log, or expose real keys in frontend code.',
            'Prefer server/firewall IP restrictions for production.',
            'Run read-only checks before writes and verify after writes.',
        ],
    ];
}

function wpae_agent_prompt(): string {
    return <<<'PROMPT'
You are operating a remote WordPress site through WP AI Executor.
Before writing, inspect the environment. For Elementor pages, design first: define subject, audience, single page job, palette, type roles, layout, and one distinctive signature element. Then create or update a WordPress page by setting Elementor metadata. Use Elementor Containers/Flexbox, avoid Oxygen and Novamira, and verify the published URL over HTTP plus Elementor meta after every write. Do not expose API keys.
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
