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
X-WPAE-Guide-Token: <token from /guide/ack>
X-WPAE-Guide-Hash: <hash from /guide/ack>
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

### `POST /wp-json/ai-executor/v1/self-update`

Safely updates only the plugin's own `wp-ai-executor.php` file from the
allowlisted GitHub source. This is the only supported filesystem write path.
General filesystem writes through `/run` remain blocked.
The downloaded file must pass required plugin marker validation before writing.

```bash
curl -s -X POST "$SITE/wp-json/ai-executor/v1/self-update" \
  -H "X-AI-Key: $KEY" \
  -H "X-WPAE-Guide-Token: $GUIDE_TOKEN" \
  -H "X-WPAE-Guide-Hash: $GUIDE_HASH" \
  -H "Content-Type: application/json" \
  -d '{"dry_run": true}'
```

Accepted source URLs must match:

```text
https://raw.githubusercontent.com/DiasMazhenov/wp-ai-executor/*/wp-ai-executor.php
```

### `GET /wp-json/ai-executor/v1/capabilities`

Returns the executor's current safety and write capabilities.
This reflects the site owner's settings from **Settings → AI Executor**.

Important fields include:

```json
{
  "can_execute_php": true,
  "can_write_elementor": true,
  "can_write_files_via_run": false,
  "can_self_update_plugin": true,
  "can_upload_media": true,
  "can_create_exports": true,
  "can_manage_skills": true,
  "can_rollback": true,
  "can_view_operation_logs": true,
  "can_score_agent_conformance": true,
  "elementor": {
    "can_normalize": true,
    "can_use_recipes": true,
    "can_compose_sections": true
  }
}
```

If a capability is disabled, the matching write endpoint returns `403` even
with a valid key and guide token.

### Agent conformance scoring

Mutating and verification endpoints return an `agent_conformance` object. It is
a runtime score for whether the agent followed the guide and site policy.
The score is advisory, not a hard block: agents must treat `weak` or `blocked`
as unfinished work and correct the operation before claiming success.

Scored criteria include:

- guide token flow
- forbidden filesystem activity
- native Elementor validation
- Flexbox Containers only
- camelCase `widgetType`
- native visual settings for critical backgrounds/styles
- native heading hierarchy
- consistent native container padding/gaps
- visible native button CTA
- responsive Elementor settings
- deliberate palette variety
- populated native heading/text content
- explicit verification signal or `/audit`

Example:

```json
{
  "agent_conformance": {
    "score": 92,
    "level": "strong",
    "blocking_errors": [],
    "criteria": {
      "guide_token_flow": { "status": "pass", "points": 15, "max": 15 },
      "file_policy": { "status": "pass", "points": 15, "max": 15 }
    }
  }
}
```

Operation logs also include the redacted `agent_conformance_score` and
`agent_conformance_level` summary fields.

### `POST /wp-json/ai-executor/v1/elementor/validate`

Validates Elementor JSON without writing anything. Requires `X-AI-Key`.

```json
{ "elementor_data": [] }
```

### `POST /wp-json/ai-executor/v1/elementor/normalize`

Normalizes common Elementor JSON mistakes without writing anything. Requires
`X-AI-Key`. Use this before `/elementor/page` or `/elementor/update` when an
agent produced legacy sections/columns, `widget_type`, missing `widgetType`,
missing `settings`, missing `elements`, or incomplete container defaults.

It returns `normalized_elementor_data`, `change_counts`, a capped `changes`
list, `before_errors`, `after_errors`, and audit `stats`.

```json
{
  "elementor_data": [
    {
      "elType": "section",
      "elements": [
        {
          "elType": "widget",
          "widget_type": "heading",
          "settings": { "title": "Hello" }
        }
      ]
    }
  ]
}
```

### `GET /wp-json/ai-executor/v1/elementor/recipes`

Returns reusable native Elementor composition patterns. Recipes are not rigid
templates; they are safe primitives and section patterns with variants and
slots. Agents should use them for complex sections instead of inventing raw
Elementor structure from scratch.

Available section recipes include:

- `hero.editorial`
- `feature.grid`
- `process.steps`
- `pricing.comparison`
- `faq.accordion`
- `cta.band`
- `proof.timeline`
- `contact.block`

```bash
curl -s "$SITE/wp-json/ai-executor/v1/elementor/recipes" \
  -H "X-AI-Key: $KEY"
```

### `GET /wp-json/ai-executor/v1/elementor/recipes/{id}`

Returns one recipe with its variants, slots, and native Elementor JSON pattern.

```bash
curl -s "$SITE/wp-json/ai-executor/v1/elementor/recipes/hero.editorial" \
  -H "X-AI-Key: $KEY"
```

### `POST /wp-json/ai-executor/v1/elementor/compose`

Composes a recipe variant with project-specific slot values and returns ready
Elementor JSON without writing anything. The returned `elementor_data` should
still be passed through `/elementor/normalize`, `/elementor/validate`, and then
saved with `/elementor/page` or `/elementor/update`.

```json
{
  "recipe_id": "hero.editorial",
  "variant": "split-proof",
  "slots": {
    "headline": "A service page that makes the offer clear",
    "subheadline": "Native Elementor structure with proof, process, and action.",
    "cta_primary": "Discuss the page"
  }
}
```

### `POST /wp-json/ai-executor/v1/elementor/page`

Creates or updates a WordPress page and saves validated Elementor metadata.
Requires `X-AI-Key`, `X-WPAE-Guide-Token`, `X-WPAE-Guide-Hash`, and the
`elementor_writes` capability.

```json
{
  "title": "Landing Page",
  "slug": "landing-page",
  "status": "publish",
  "template": "elementor_canvas",
  "dry_run": true,
  "elementor_data": []
}
```

Set `dry_run=true` to validate without writing. Real writes return
`rollback_snapshot_id` and `rollback_expires_at`; pass the snapshot ID to
`/rollback` if the page must be reverted.

### `POST /wp-json/ai-executor/v1/elementor/update`

Updates Elementor metadata for an existing page after validation.

```json
{
  "post_id": 123,
  "template": "elementor_canvas",
  "dry_run": true,
  "elementor_data": []
}
```

### `POST /wp-json/ai-executor/v1/rollback`

Restores a short-lived rollback snapshot stored in `wp_options`.
Requires `X-AI-Key`, `X-WPAE-Guide-Token`, and `X-WPAE-Guide-Hash`.

```json
{ "snapshot_id": "abc123" }
```

For arbitrary `/run` PHP, `dry_run` is intentionally unsupported. If a `/run`
mutation is risky, pass known targets before execution:

```json
{
  "code": "return update_option('example_option', 'new-value');",
  "rollback_targets": {
    "post_ids": [123],
    "option_names": ["example_option"]
  }
}
```

### `POST /wp-json/ai-executor/v1/audit`

Audits a page after writing. Requires `X-AI-Key`; does not write anything.
Returns machine-readable findings for Elementor meta, JSON validity, Flexbox
Containers, `widgetType`, HTML widget layout risk, and basic native style checks.

```json
{ "post_id": 123 }
```

### `POST /wp-json/ai-executor/v1/guide/session`

Starts a short-lived guide session and returns `guide_session_id`,
`guide_hash`, expiration, and the required acknowledgement schema.

### `POST /wp-json/ai-executor/v1/guide/ack`

Acknowledges that the agent read `/guide`, `custom_skills`, and
`/capabilities`. Returns:

```http
X-WPAE-Guide-Token: <token>
X-WPAE-Guide-Hash: <hash>
```

All write endpoints require these headers.

### `GET|POST /wp-json/ai-executor/v1/skills`

Stores custom agent skills in the WordPress database. Skills are returned inside
`/guide` as `custom_skills`; no skill files are created on disk.
The same storage can be managed from **Settings → AI Executor → Custom skills**
by pasting `SKILL.md` content into the dashboard.

**Body:**
```json
{
  "id": "frontend-design",
  "name": "frontend-design",
  "description": "Project visual and UX rules",
  "content": "Skill instructions as Markdown or plain text",
  "enforce": [
    { "type": "forbid_elementor_eltype", "value": "section" },
    { "type": "require_widget_key", "value": "widgetType" },
    { "type": "allow_widget_type", "value": "heading" },
    { "type": "require_container_setting", "value": "background_color" },
    { "type": "forbid_html_pattern", "value": "<section" }
  ],
  "enabled": true,
  "priority": 10
}
```

Supported `enforce` rule types:

- `forbid_elementor_eltype`
- `require_widget_key`
- `forbid_widget_key`
- `allow_widget_type`
- `forbid_widget_type`
- `require_widget_setting` with optional `target`
- `require_container_setting`
- `forbid_html_pattern`

### `GET /wp-json/ai-executor/v1/skills/export`

Returns a database-only JSON skill bundle. No files are created.

```json
{
  "schema": "wp-ai-executor.skill-bundle",
  "schema_version": 1,
  "skills": []
}
```

### `POST /wp-json/ai-executor/v1/skills/import`

Imports a JSON skill bundle into `wp_options`. Requires the `manage_skills`
capability and guide-token headers. Use `mode=merge` to add/update, or
`mode=replace` to replace all current skills.

```json
{
  "mode": "merge",
  "skills": [
    {
      "id": "project-rules",
      "name": "Project rules",
      "content": "# Instructions"
    }
  ]
}
```

### `DELETE /wp-json/ai-executor/v1/skills/{id}`

Deletes a custom skill from the database.

### `POST /wp-json/ai-executor/v1/media/upload`

Uploads validated media through the WordPress uploads API. Allowed MIME types:
JPEG, PNG, WebP, GIF, PDF. Max size: 8 MB.

### `POST /wp-json/ai-executor/v1/exports/create`

Creates a JSON export under `wp-content/uploads/wp-ai-executor/exports/`.
Max size: 1 MB. PHP and arbitrary paths are not allowed.

### `GET /wp-json/ai-executor/v1/logs`

Returns recent authenticated operation metadata from `wp_options`.
Logs are capped and do not include API keys, guide tokens, raw request bodies,
raw page payloads, raw response payloads, or secrets.

```json
{
  "logs": [
    {
      "time": "2026-07-05T20:00:00+00:00",
      "method": "POST",
      "endpoint": "/elementor/page",
      "status": 200,
      "target_ids": { "post_id": 123 },
      "rollback_snapshot_id": "abc123"
    }
  ]
}
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
Then call /guide/session, read /guide and /capabilities, call /guide/ack,
and pass X-WPAE-Guide-Token plus X-WPAE-Guide-Hash to every write endpoint.

Follow the returned agent_prompt, embedded_skill_packs, frontend_design rules,
custom_skills, wordpress_elementor workflow, page_meta contract,
html_enhancement_policy, and verification_checklist. Then use /run for execution
and verify the published URL plus Elementor metadata.
Use /elementor/normalize before saving any Elementor JSON that contains legacy
sections/columns, snake-case widget_type, missing widgetType, missing settings,
missing elements arrays, or incomplete container defaults.
For complex or non-standard sections, call /elementor/recipes, inspect the
recipe slots/variants, call /elementor/compose, then normalize and validate the
returned elementor_data before saving.

Important: build Elementor page structure and content with native editable
Elementor elements. Use containers and widget settings such as `heading`,
`text-editor`, `button`, `icon-list`, `image`, `divider`, and `spacer`.
Use only Elementor Flexbox Containers for layout. In `_elementor_data`, layout
nodes must be `elType: "container"` only. Legacy `elType: "section"` and
`elType: "column"` are forbidden and must be converted to nested containers
before saving.
Every widget must use Elementor's exact camelCase `widgetType` key. Never use
`widget_type`; Elementor will treat that as missing widget identity and can
render empty widgets.
The executor validates changed `_elementor_data` after each `/run` call and
blocks legacy `section`/`column`, snake-case `widget_type`, and widgets missing
`widgetType`, rolling changed Elementor data back when possible.
Critical visual state must also be set natively: backgrounds, text colors,
borders, border radius, spacing, dimensions, and alignment. Scoped CSS may use
`!important` as a fallback when Elementor/theme CSS wins specificity, but CSS
must not be the only source for essential contrast or layout.

The Elementor `html` widget is allowed only for small JavaScript snippets or
complex CSS enhancements when native settings are not enough. Do not use it as
the main page markup/content/layout container, and do not use shortcode widgets
to fake editable page sections. After writing `_elementor_data`, recursively
inspect any `html` widgets and confirm they are enhancement-only.

Never create external files through this plugin. Do not create temporary
loaders, mu-plugins, helper PHP files, CSS/JS/JSON/base64 payload files, scratch
files, or files in `/tmp`. Use WordPress APIs and Elementor metadata instead.
By default `/run` rejects common filesystem write/delete operations and
shell/process execution.
Use only dedicated endpoints for allowed writes: `/self-update`,
`/elementor/page`, `/elementor/update`, `/media/upload`, `/exports/create`,
and `/skills`. Use `/audit` after page writes. Read `agent_conformance` in
write and audit responses; `weak` or `blocked` means the task is not complete.
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
- every `elType: "widget"` element has non-empty `widgetType` and no `widget_type`
- main copy lives in native widget settings
- any `html` widget is CSS/JS enhancement-only
- critical backgrounds, borders, spacing, and contrast exist in native Elementor settings
- no external files, temporary loaders, mu-plugins, or scratch files are created
- no obvious desktop/mobile overlap or horizontal overflow
- `agent_conformance.level` is `strong` or at least `acceptable`

---

## Security

- All requests require the `X-AI-Key` header (or `?key=` query param)
- Key is a 64-char cryptographically random hex string
- Key comparison uses `hash_equals()` to prevent timing attacks
- The `/key` endpoint is restricted to `127.0.0.1` / `::1` only
- The `/guide` endpoint is authenticated because it describes privileged automation workflows
- Site-owner capability toggles in **Settings → AI Executor** can disable `/run`, self-update, Elementor writes, media upload, exports, skills management, and filesystem writes
- `/run` blocks common filesystem write/delete functions and shell/process execution by default
- `/run` validates changed Elementor data and blocks legacy sections/columns or missing `widgetType`
- `/elementor/validate`, `/elementor/page`, and `/elementor/update` provide structured Elementor JSON validation and saving
- `/audit` returns machine-readable page verification findings
- write endpoints require a fresh guide token from `/guide/session` + `/guide/ack`
- `/self-update` is the only allowed plugin file write path and only writes the current plugin file from the allowlisted GitHub source
- `/skills` stores custom skills in the database, not as files
- skills can include limited `enforce` rules that runtime validators apply
- `/media/upload` and `/exports/create` are the only non-plugin file write endpoints
- For extra security on production, hard-code the key in `wp-config.php` and delete it from `wp_options`

---

## Compatibility

- WordPress 5.9+
- PHP 8.0+
- Works with any AI agent that can make HTTP requests

## License

MIT
