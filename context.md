# WP AI Executor Context

## Current release

- Plugin: `v02.08.89`
- Guide: `v02.05.63`
- Repository: `DiasMazhenov/wp-ai-executor`
- Canonical API header: `X-AI-Key`
- Elementor writes: native Flexbox Containers only; legacy sections/columns may
  be imported for inspection but must be normalized before structured writes.

## Architecture

`wp-ai-executor.php` is the bootstrap. Domain code is split under `includes/`:

- `security/` - API key and capability profiles;
- `guide/` - mandatory agent contract and guide-token flow;
- `elementor/` - validation, normalization, recipes, compose, transactions,
  editability, CSS-to-native migration, page writes, and block library;
- `vision/` - optional provider-backed screenshot review, normalized reports,
  and transaction Vision gate;
- `llm/` - encrypted OpenAI-compatible provider settings, rate-limited chat
  proxy, and bounded editor context;
- `rest/` - route registration;
- `admin/` - Russian dashboard;
- `updates/` - single-file and manifest-based package updates;
- `support/`, `health/`, `rollback/`, `media/`, `exports/`, `skills/`.

Administrators have a direct `AI Executor` link in the WordPress Admin Bar.
It opens the existing `Settings -> AI Executor` screen and is hidden from
users without `manage_options`.

Custom skills may carry an optional database-only
`wpae-skill-manifest-v1` sidecar. It describes version, capabilities, inputs,
pipeline, source, license and compatibility while `SKILL.md` remains canonical.
Legacy records without a manifest remain valid. Pipeline steps are checked
against a closed safe-endpoint allowlist and their declared capabilities; the
manifest never executes code or grants filesystem, shell, MCP, WP-CLI,
browser-admin, `/run`, or self-update access.
Skills that need screenshot review may declare the `ai_vision` capability and
the `/vision/analyze`, `/vision/report`, or `/vision/page-review` pipeline
endpoints; the site owner capability toggle and guide-token checks still apply.

The optional `llm_chat` capability powers `POST /llm/chat` and is disabled by
default. The dashboard supports OpenAI, DeepSeek, OpenRouter, and custom HTTPS
OpenAI-compatible providers. Provider keys are encrypted in `wp_options`; the
Elementor editor receives only a WordPress REST nonce and proxy URL. Ordinary
chat remains advisory. Explicit action requests may insert only new Elementor
elements when `llm_chat` and `elementor_writes` are enabled; generated data
passes update, preflight, protected-zone, visual-regression and rollback checks.
Delete and replace actions are forbidden. Only bounded history and sanitized
selected-element metadata are sent to the model.

LLM settings use provider-owned built-in HTTPS base URLs. OpenRouter defaults to
`openrouter/free`; custom base URLs are available only for the custom provider.
An existing provider key is shown in the dashboard as a bullet placeholder while
the submitted value remains empty, so saving the form cannot replace it.
The Elementor floating chat also detects the explicit admin editor URL
`post.php?post=ID&action=elementor` and has a fallback enqueue path when the
Elementor-specific script hook is unavailable.
LLM chat responses include a safe operational `steps` array. Action traces cover
provider response, JSON command decoding, command and permission checks, element
count, native normalization, design-system mapping, page context, ID rekeying,
Elementor update and final status. The chat renders this trace and includes it in
the copied JSON log; it never exposes hidden reasoning, credentials, prompts,
raw page payloads or raw provider responses.
Action requests from the floating Elementor chat also receive the current guide,
enabled custom skills, capabilities, and project design system in the server-side
LLM context. The browser still receives only its WordPress REST nonce; the site
API key is never exposed. The action is executed through the same internal
Elementor update, preflight, transaction, visual-regression, and rollback path
used by structured REST writes.
The chat action gate rejects a technically valid but empty element tree when it
contains no native Elementor widget, preventing a false success that leaves only
an empty container. After a successful write, the chat reloads the current
Elementor preview; progress statuses appear while the synchronous request is
running, followed by confirmed server steps as separate assistant messages.
When the Elementor transaction fails after a write and rollback, the chat now
exposes `update_error`, `failed_checks`, and readable `failure_details` in the
assistant message and copied JSON log instead of reducing the failure to HTTP
422. This identifies the exact post-save check that rejected the operation.
When AI Vision is enabled and configured, the floating chat captures the
refreshed Elementor preview after a successful write and sends it to the
editor-only `/llm/vision-review` endpoint. Critical findings, provider errors,
or screenshot failures restore the write snapshot when possible; the chat does
not claim success after such a rollback.
Vision review failures expose sanitized provider, HTTP status, provider
message, analysis code, and rollback status in the chat log; credentials and
raw image/provider payloads remain excluded.

`POST /skills/validate` validates and normalizes a manifest without writing.
It remains available when `manage_skills` is disabled; mutation endpoints do
not bypass that owner-controlled capability.

Live verification on `v02.08.66` covered legacy-null, valid manifest,
forbidden `/run`, and missing write-capability cases. The endpoint is exposed
at `file_write_policy.allowed_endpoints["/skills/validate"]`; the skill store
count remained unchanged. Live skill mutation tests were intentionally not
forced because the site owner has `manage_skills=false`.

The dashboard uses an accessible horizontal tab interface with seven focused
sections: connection, Elementor, agents, LLM agents, Vision, monitoring and
examples. The active
section is preserved in the URL hash and local browser storage. Arrow keys,
Home and End switch tabs, while the non-JavaScript fallback keeps every card
visible in the original document order.

Health latency scoring excludes expected long-running plugin update endpoints
from interactive REST thresholds. Their count and maximum duration remain in
the report as maintenance metadata without producing a false critical alert.

The package updater explicitly loads the WordPress temp-file API when REST
requests do not bootstrap `wp_tempnam()` automatically. Atomic package writes
preserve existing file permissions and assign `0644` to new module/asset files
before rename. The plugin root and package-managed subdirectories preserve
owner write bits while receiving the missing read/execute bits required for
nginx traversal.

## AI Vision

Runtime module: `includes/vision/vision.php`. AI Vision is an optional
owner-controlled capability and is disabled by default in every preset because
`/vision/analyze` sends an agent-provided screenshot to the selected external
provider. Supported providers are Google Gemini, OpenAI, and Anthropic Claude.
The provider, model, and API key are configured in the dashboard's `AI Vision`
tab. The key is encrypted with AES-256-GCM before it is stored in the
`wp_ai_executor_vision_settings` option; it is never returned by
`/capabilities`, `/guide`, dashboard HTML, or operation logs. Provider payloads
use each provider's native multimodal request format; Gemini receives a
compatibility schema without OpenAI-only strictness fields, and provider
responses over the bounded 512 KB limit fail closed.

Endpoints:

- `POST /vision/analyze` accepts an existing WordPress image `media_id` or
  bounded inline `image_base64`, calls the configured provider, and stores only
  the normalized report.
- `POST /vision/report` accepts a structured report from an external Vision
  client when the image was analyzed outside WordPress.
- `POST /vision/page-review` combines a stored/external report with the
  deterministic Elementor Design Review Gate for a post or supplied data.

The normalized report contains `vision_score`, `findings`, `confidence`,
`must_fix`, `summary`, and `strengths`. Findings use `critical`, `major`,
`minor`, or `info` severity. Provider responses must pass the full structured
report contract before storage. Reports are bound to their declared post when
one is supplied. Critical findings block only when the caller explicitly passes
`transaction_vision_review=true` with a same-post report issued by
`/vision/analyze`; external `/vision/report` results remain advisory. Then the
existing atomic Elementor transaction restores its rollback snapshot. Major
findings remain advisory unless another deterministic gate blocks the write.

The module does not accept arbitrary image URLs, does not create image files,
does not persist base64 or raw provider responses, and does not put image data
in operation logs. It permits at most 12 provider calls per site-wide 10-minute
window to limit accidental spend. AI Vision is additional visual evidence only:
deterministic validation, native Elementor editability, public browser
screenshots, and animation/WebGL checks remain mandatory.

Release state: `v02.08.89` including the LLM proxy, Elementor editor chat,
action execution, Enter-to-send behavior, provider-response diagnostics,
JSON chat logs, initial empty-page action support, design-system
normalization, native widget content alias mapping, safe operational step traces,
guided editor context, editor preview synchronization, separate progress
messages, the non-empty native-widget action gate, and transaction failure
diagnostics, post-write AI Vision review and rollback, and Vision failure
diagnostics is published on GitHub `main` in commit `55f7877`.
The last live deployment known here is `v02.08.70` through WP Pusher;
no live deployment has been requested in this task. Public verification confirmed
that the removed `/wp-json/ai-executor/v1/key` endpoint returns `404`. Provider
smoke-test remains pending until the site owner configures a provider and
enables `ai_vision`.

## Design System Package v2

`POST /elementor/design-system` remains backward compatible and now returns a
`package` containing a machine-readable `wpae-design-system-v2` manifest,
agent-facing `DESIGN.md` content and semantic tokens. The manifest includes a
stable system ID, version, provenance, source URL, SHA-256 source hash, license
and optional active-page state when `post_id` is supplied.

Structured Elementor writes persist `_wpae_design_system_id` and
`_wpae_design_system_hash` through the shared save path. These fields are
already included in rollback snapshots, so restored revisions retain their
design-system association. Package data remains in WordPress options/meta and
does not create server files.

Live verification on `v02.08.63` confirmed Package v2 output, guide-session
compatibility, `422` schema rejection and a successful non-writing update
`dry_run`. Existing pages without WPAE design-system meta report `active=false`
until their next structured Elementor write; they are not modified implicitly.

## Elementor block library

Implementation:

- PHP: `includes/elementor/block-library.php`
- Context-menu integration: `assets/js/elementor-block-library.js`
- Visual library UI: `assets/js/elementor-block-library-ui.js`
- Visual library styles: `assets/css/elementor-block-library.css`
- Storage: private `wpae_block` custom posts plus revision-enabled
  `_wpae_block_payload` post meta
- Public files: none
- Maximum accepted JSON input: 1 MB

Accepted inputs:

- one native Elementor element;
- an array of native Elementor elements;
- an Elementor template object with `content`;
- a `wpae-elementor-block-v1` wrapper.

Each stored wrapper contains:

- exact `native_payload` for lossless foreign template storage;
- extracted `elementor_data` for insertion;
- title, description, category, tags and source metadata;
- Elementor/design-system versions;
- compatibility report, content hash, media references and protected zones.

REST endpoints:

- `GET|POST /wp-json/ai-executor/v1/elementor/blocks`
- `GET|DELETE /wp-json/ai-executor/v1/elementor/blocks/{id}`
- `GET /wp-json/ai-executor/v1/elementor/blocks/{id}/instantiate`
- `POST /elementor/blocks/{id}` updates bounded metadata or replaces native JSON and returns the block to `draft`.
- `POST /elementor/blocks/{id}/duplicate` creates a draft revision with `parent_revision`.
- `POST /elementor/blocks/{id}/publish` moves `draft -> approved -> published`; approval runs Design Review Gate.

The `wpae-elementor-block-manifest-v1` sidecar records status, source skill,
design-system version, provenance/license, parent revision, quality score and
media dependencies. Manifest metadata is limited to 32 KB. Old records without
the sidecar remain readable as published blocks.

Live verification on `v02.08.68` created and deleted a private smoke fixture:
draft creation, manifest/source-skill extraction, metadata update, duplicate
revision with parent ID, and expected `422 wpae_block_review_required` approval
failure all passed. No Elementor page was changed.

Instantiation modes:

- `preserve` - rekey IDs only and keep the original design;
- `compatibility` - normalize, rekey and validate for the Flexbox contract;
- `adapt` - compatibility plus deterministic semantic color, typography,
  spacing and radius mapping through native Elementor settings. The response
  includes mapped/unmatched/collision evidence and protected-zone skips.

Live fixture verification on `v02.08.64` confirmed that `preserve` and
`compatibility` retain source styles, while `adapt` maps native values and
responsive units without changing protected HTML. Separate fixtures confirmed
collision and unmatched review lists. Both private test blocks were deleted;
the live block library returned to its original empty state.

`preserve` reports a warning when protected HTML/JS/WebGL zones exist because
their code may refer to old Elementor `data-id` values after safe ID rekeying.

In the Elementor editor, container/widget context menus expose:

- `Копировать как JSON`
- `Сохранить в библиотеку WP AI Executor`
- `Открыть библиотеку блоков`

## Design Review Gate

- `POST /elementor/design-review` возвращает deterministic review по composition/brief, design-system consistency, accessibility/mobile, copy и Elementor editability.
- Состояния: `review`, `revise`, `approved`; максимум три итерации.
- `transaction_design_review=true` делает approval обязательным и включает auto-rollback при провале.
- Live verification: страница `4172` вернула `approved`, visual level `acceptable`, HTTP `200`.

The visual library provides client-side search, category filtering, structural
details, compatibility status and `preserve|compatibility|adapt` insertion
modes. Insertion uses Elementor's native `document/ui/paste` command and the
currently selected Elementor container instead of writing page metadata.

The browser integration uses the logged-in WordPress REST nonce and never
exposes `X-AI-Key`. The packaged JS source is attached inline to Elementor's
existing `elementor-editor` handle because the current hosting policy returns
403 for direct static files inside the WP AI Executor plugin directory. The
inline source is printed before `elementor-editor`, catches `elementor/init`
before element views calculate their context-menu groups, and exposes the
non-sensitive `window.WPAEBlockLibraryDebug` status for DevTools diagnostics.
It registers Elementor's universal `elements/context-menu/groups` filter for
current and future element types, while retaining typed filters as a fallback.
Callbacks resolve the active models through `elementor.selection.getElements()`.

## Agent workflow

1. Fetch `/guide` and `/capabilities`, then complete guide-token acknowledgement.
2. Search `/elementor/blocks` before generating a common section from scratch.
3. Inspect the full block and compatibility report.
4. Choose `preserve`, `compatibility`, or `adapt` explicitly.
5. Preserve protected HTML/WebGL/Three.js/GSAP/canvas zones.
6. Merge returned `elementor_data`, then run normalize/validate and write
   endpoint `dry_run=true`.
7. Write only after validation succeeds and verify the public page.
8. When `ai_vision` is enabled, capture desktop and mobile screenshots, call
   `/vision/analyze` or `/vision/report`, then call `/vision/page-review`.
9. For risky writes, pass `transaction_vision_review=true` with the report ID
   and inspect `transaction.checks.vision_review` and rollback details.

## Next block-library steps

- visual library browser and insertion action inside Elementor;
- update/duplicate endpoints and richer category/tag management;
- previews and screenshots;
- media dependency transfer between sites;
- richer native token controls in the dashboard;
- live AI Vision provider and transaction-gate smoke tests;
- runtime REST/editor tests on a WordPress + Elementor installation.
