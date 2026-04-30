<?php
/**
 * Plugin Name: WP AI Executor
 * Description: Secure REST endpoint for AI automation (Claude, GPT, Gemini, Qwen, etc.). Execute PHP in WordPress context via any AI agent.
 * Version:     1.1.0
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
