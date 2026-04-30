# WP AI Executor

Secure REST endpoint that lets any AI agent (Claude, Codex, Gemini, GPT, etc.) execute PHP code in the WordPress context.

## Installation

1. Copy the `wp-ai-executor` folder to `wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. The secret key is auto-generated on first activation and stored in `wp_options`

Optionally, hard-code the key in `wp-config.php`:
```php
define( 'WP_AI_EXECUTOR_KEY', 'your-64-char-hex-key' );
```

---

## Endpoints

### `POST /wp-json/ai-executor/v1/run`

Execute PHP code in the WordPress environment.

**Headers:**
```
X-AI-Key: <your-secret-key>
Content-Type: application/json
```

**Body:**
```json
{ "code": "return get_bloginfo('name');" }
```

**Response:**
```json
{
  "return_value": "My WordPress Site",
  "output": ""
}
```

### `GET /wp-json/ai-executor/v1/key`

Returns the current secret key. **Accessible from localhost only.**

```bash
curl https://yoursite.com/wp-json/ai-executor/v1/key
```

---

## Usage Examples

### Bash / curl (production, no browser needed)
```bash
KEY="your-secret-key"
SITE="https://yoursite.com"

curl -s -X POST "$SITE/wp-json/ai-executor/v1/run" \
  -H "Content-Type: application/json" \
  -H "X-AI-Key: $KEY" \
  -d '{"code": "return get_option(\"blogname\");"}'
```

### JavaScript / Browser (local dev)
```javascript
const KEY = 'your-secret-key';

window.aiPHP = async (code) => {
    const res = await fetch('/wp-json/ai-executor/v1/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-AI-Key': KEY },
        body: JSON.stringify({ code })
    });
    const d = await res.json();
    return d.return_value ?? d.error;
};

// Usage:
await aiPHP(`return get_bloginfo('name') . ' | PHP ' . PHP_VERSION;`);
```

---

## Security

- All requests require the `X-AI-Key` header (or `?key=` query param)
- Key is a 64-char cryptographically random hex string
- Key comparison uses `hash_equals()` to prevent timing attacks
- The `/key` endpoint is restricted to `127.0.0.1` / `::1` only
- For extra security on production, hard-code the key in `wp-config.php` and delete it from `wp_options`

---

## Compatibility

- WordPress 5.9+
- PHP 8.0+
- Works with any AI agent that can make HTTP requests

## License

MIT
