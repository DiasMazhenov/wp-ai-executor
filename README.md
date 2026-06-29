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

### `GET /wp-json/ai-executor/v1/guide`

Returns authenticated guidance for AI agents before they automate WordPress or Elementor.
This is not a Codex-specific skill runtime. It is a portable prompt and implementation
contract that any HTTP-capable agent can fetch and follow.

It includes:

- frontend design planning principles
- WordPress/Elementor automation workflow
- distilled `frontend-design` and `wordpress-elementor-dev` knowledge packs
- native Elementor-first rules
- HTML widget enhancement policy
- Elementor `_elementor_data` shape
- required page meta keys
- verification checklist
- security rules
- a minimal PHP snippet for creating an Elementor page

The guide intentionally does not embed Codex skills as a runtime dependency. Instead, it
ships portable distilled rules that Claude, Codex, GPT, Gemini, Qwen, scripts, or any
HTTP-capable agent can fetch and apply.

```bash
KEY="your-secret-key"
SITE="https://yoursite.com"

curl -s "$SITE/wp-json/ai-executor/v1/guide" \
  -H "X-AI-Key: $KEY"
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

### Agent bootstrap

Ask any agent to fetch `/guide` first:

```text
Before making WordPress changes, call:
GET /wp-json/ai-executor/v1/guide with X-AI-Key.

Follow the returned agent_prompt, embedded_skill_packs, frontend_design rules,
wordpress_elementor workflow, page_meta contract, html_enhancement_policy, and
verification_checklist. Then use /run for execution and verify the published URL
plus Elementor metadata.

Important: build Elementor page structure and content with native editable
Elementor elements. Use containers and widget settings such as `heading`,
`text-editor`, `button`, `icon-list`, `image`, `divider`, and `spacer`.
Use only Elementor Flexbox Containers for layout. In `_elementor_data`, layout
nodes must be `elType: "container"` only. Legacy `elType: "section"` and
`elType: "column"` are forbidden and must be converted to nested containers
before saving.
Critical visual state must also be set natively: backgrounds, text colors,
borders, border radius, spacing, dimensions, and alignment. Scoped CSS may use
`!important` as a fallback when Elementor/theme CSS wins specificity, but CSS
must not be the only source for essential contrast or layout.

The Elementor `html` widget is allowed only for small JavaScript snippets or
complex CSS enhancements when native settings are not enough. Do not use it as
the main page markup/content/layout container, and do not use shortcode widgets
to fake editable page sections. After writing `_elementor_data`, recursively
inspect any `html` widgets and confirm they are enhancement-only.
```

### Agent quality gates

Before writing a landing page, the agent should produce or internally resolve:

- subject, audience, and the page's single job
- 4-6 color tokens
- display, body, and utility typography roles
- section map / wireframe
- one distinctive signature element
- native Elementor element plan
- CSS/JS enhancement plan, if needed

After writing, the agent should verify:

- permalink returns HTTP 200
- post status and Elementor meta are correct
- `_elementor_data` decodes as a recursive array
- `_elementor_data` contains zero legacy `section` or `column` elements
- main copy lives in native widget settings
- any `html` widget is CSS/JS enhancement-only
- critical backgrounds, borders, spacing, and contrast exist in native Elementor settings
- no obvious desktop/mobile overlap or horizontal overflow

---

## Security

- All requests require the `X-AI-Key` header (or `?key=` query param)
- Key is a 64-char cryptographically random hex string
- Key comparison uses `hash_equals()` to prevent timing attacks
- The `/key` endpoint is restricted to `127.0.0.1` / `::1` only
- The `/guide` endpoint is authenticated because it describes privileged automation workflows
- For extra security on production, hard-code the key in `wp-config.php` and delete it from `wp_options`

---

## Compatibility

- WordPress 5.9+
- PHP 8.0+
- Works with any AI agent that can make HTTP requests

## License

MIT
