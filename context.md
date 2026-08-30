# WP AI Executor Context

## Current release

- Plugin: `v02.09.73`
- Guide: `v02.05.77`
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

The floating Elementor chat can explicitly export selected native Elementor
models as `wpae-elementor-selection-v1` JSON with recursive child elements;
the full export stays local to the browser and is not added to LLM context.

Graphify navigation was refreshed on 2026-08-30 after this change: the local
code graph contains 585 nodes and 1383 edges, with `graphify-out/GRAPH_TREE.html`
regenerated as an untracked analysis artifact.

The editor-chat Vision review is advisory: quality findings trigger a bounded
rollback-and-regenerate workflow instead of silently keeping a weak block. The
failed generated version is rolled back, then the original brief plus sanitized
Vision findings are sent to the LLM for one complete native `insert_elements`
regeneration; up to two repair passes are allowed, with no changes to unrelated
existing page blocks. Atomic rollback remains limited to explicit
`transaction_vision_review` transactions.

Vision feedback in the chat explicitly says that the agent is regenerating the
design. Temporary provider failures preserve the original request, force a
reload, and retry once; embedded browsers that ignore `location.reload()` use
the same pending request for a local bounded retry, without a reload loop.

Action generation classifies natural-language block requests into hero,
benefits, pricing, testimonials, FAQ, process, CTA, or portfolio archetypes.
Content-only prompts with at least two natural label-description pairs are also
classified as generation requests, so users do not need to name an Elementor
widget or add a technical command verb. Briefs made from three or more
sentence/line units with a content signal or CTA are classified the same way,
even when they contain no labels or technical action words.
Plain sentence/line briefs are also used as requested content when no quoted or
label-description content exists, so deterministic fallback and content
fidelity cannot replace the user's copy with a generic template.
When those prompts do not name a block type, archetype detection uses the
content semantics: question pairs become FAQ, price pairs pricing, testimonial
language becomes testimonials, step language becomes process, case/result
language becomes portfolio, and neutral pairs become benefits cards.
Benefits language is checked before process language, and fallback repeatable
grids are resized to the number of supplied pairs (minimum two, maximum four)
so template cards cannot remain as orphaned content.
The LLM prompt and bounded repair pass choose matching native Elementor widgets
when available, while retaining the one-root-Flexbox, populated-content,
design-system, and editability gates.
If both provider output and repair are unusable, a typed deterministic fallback
is generated, marked in the execution trace, and sent through the same gates.
Explicit quoted content in the user request is checked against the generated
native widget tree before any write. The fallback grafts those phrases into
matching heading/text widgets when possible and rejects the action with a
missing-content list when fidelity cannot be proven. Editor Vision also receives
the original brief and a bounded visible-text excerpt, so content fidelity is a
separate review concern from visual composition.
Vision reports with major/critical findings or a score below 75 trigger the
bounded editor-chat regeneration workflow; malformed/provider failures remain
visible errors. The chat only reports regeneration success after Elementor
confirms the new write.
The targeted Elementor patch branch keeps balanced nested arrays in its
progress-step merge; PHP syntax is checked before release to prevent a bootstrap
fatal error from taking down WordPress.
Pricing fallback content parsing strips request prefixes from labels, keeps
the root heading/description separate from explicit tariff cards, and maps
`Кнопка: ...` text into native card buttons.
Editor chat retries transient provider HTTP failures (408/425/429/5xx) once
after a forced reload; auth, malformed-request, JSON, validation, write, and
Vision errors remain non-retryable.
After the forced reload, the chat opens itself and renders the retry message
and final result so the one-retry path is visible to the editor.
Repeatable fallback archetypes keep explicit label/description pairs inside
their card grids instead of consuming them in the root heading/description.
Testimonial fallback output uses a wrapping Flexbox card grid with one editable
native card container per quote and a single-column mobile layout.
Generated testimonial author headings are guarded to native h5/h6 typography;
quote copy remains in text-editor/testimonial widgets so names cannot inherit a
display-scale role and overflow their cards.
Repeatable archetypes use a backend bento normalizer rather than a prompt rule:
direct native widgets are wrapped into editable Flexbox card containers when
needed, child containers receive responsive custom widths and flex wrapping, and
the layout caps each row at four items with a single-column mobile fallback.
Every generated root block receives one compact outlined rounded native badge
container with a native heading label inside. Its width is content-sized, its
horizontal padding is larger than its vertical padding, and its dark
border/four-sided radius form the pill shape. The badge geometry is protected
from the generic design-token spacing/radius map; its background may use the
active surface token or remain transparent. Headings inside repeatable card containers are normalized to native
`icon-box` widgets with an icon positioned to the left, including provider,
repair, and deterministic fallback output. The container holding repeatable
cards is forced transparent while individual cards retain their own surfaces.
The normalizer preserves existing card content, handles malformed settings
defensively, skips the badge subtree during card-icon conversion, and applies
this grammar before Elementor preflight.

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
Generated blocks receive a post-parse visual-variation guard. It selects from
60 combinations of ten coherent surface themes and six responsive compositions,
compares the candidate visual signature with every saved top-level block, and
stores `_wpae_visual_variant`/`_wpae_visual_layout` metadata. This prevents a
provider or fallback from passing off the same grid as a new design, including
when older blocks have no WPAE variant class. Repeatable grids keep a maximum of
four cards per row and each composition has a mobile single-column fallback.
The candidate signature is calculated after the variant is applied, before the
write, so selection cannot rely only on an LLM seed or on a class that Elementor
may omit from the editor DOM.

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
LLM provider responses with HTTP 200 but `finish_reason=error` are treated as
provider failures, and the chat exposes their sanitized provider message/code
instead of reporting only an empty response.
When the LLM transport is unavailable (`wpae_llm_provider_request_failed`), the
editor chat stores only the bounded current request in `sessionStorage`, reloads
the current Elementor page, and retries it once. A second transport failure and
all model, JSON, validation, write, or Vision errors are reported without
another reload loop.
The AI Vision provider key field uses the same bullet mask as the main LLM key;
empty input keeps the encrypted key, while the explicit clear checkbox removes it.
The Elementor chat header displays the configured provider model and plugin
version as non-secret metadata; provider keys remain server-side.
The default Gemini AI Vision model is `gemini-3.5-flash-lite`. LLM action
prompts explicitly require populated native widgets inside containers; the
empty-container gate remains blocking so a failed model response cannot save a
blank hero.
Action responses allow up to 8000 completion tokens and request compact JSON;
failed decoding exposes only safe response length, JSON error, truncation hint,
provider message/code, and finish reason in chat diagnostics.
Action requests now explicitly request JSON mode from OpenAI-compatible
providers; OpenRouter requests additionally require a route that supports the
requested parameters. The action prompt repeats a final JSON-only instruction
after the guided context so provider route text or endpoint instructions cannot
be mistaken for an Elementor command. If the dynamic `openrouter/free` route
rejects those structured-output parameters with its known no-endpoint response,
the same request retries without those optional parameters and remains protected
by the server-side action decoder and Elementor validation gates. Provider
transport errors now expose a sanitized technical detail in the editor chat.
Action prompts now require exactly one top-level Flexbox container with 3-5
populated native widgets, preventing `openrouter/free` from returning a large
flat list that the action gate must reject.
When the provider still returns an invalid action shape (too many top-level
elements, no native widgets, or no insert_elements action), the proxy performs
one bounded repair pass with a minimal JSON-only context, the exact current
post_id, and reruns the same
decoder and Elementor gates.
After a successful chat write, the chat first synchronizes the saved normalized
elements into the open Elementor editor model through the official
`$e.run('document/elements/create')` command, so the current canvas and Structure
tree update without a browser reload. If the editor command API is unavailable,
it falls back to the official `document/save` footer saver API, the legacy
`elementor.reloadPreview()` path, and cache-busted iframe verification.

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

The visual regression gate is comparative: it blocks a good/acceptable public
baseline regressing to weak/blocked or losing key public signals, but it does
not reject every update merely because the page was already weak/blocked.
It runs only for published posts; draft/editor pages do not have a reliable
public permalink baseline and therefore skip this comparison.
The floating editor chat uses advisory AI Vision review for both draft and
published editor pages; strict rollback remains limited to explicit transaction
Vision gates.
After an insert, preview verification compares the widget count with the
pre-request count plus the inserted count, so stale canvas content cannot be
reported as refreshed.
The preview iframe cache-buster also updates its `ver` query parameter, which
forces the Elementor preview URL to request the newly saved HTML.
The current chat contract now assigns an operation_id to every action write,
returns a compact top-level diff and exposes a one-click editor-only undo
endpoint scoped to the current post. Action decoding rejects anything except
one populated root Flexbox container, so a successful provider response cannot
be reported as a flat or empty insertion.
Editor-chat Vision is advisory and no longer rolls back a successful write when
the editor screenshot or provider review fails. The capture hides editor-only
chrome/dropzones, sends bounded render-context evidence, and the Vision prompt
is instructed to ignore editor placeholders and distinguish uncertainty from a
broken public page. The chat exposes action controls for the latest rollback
snapshot and can undo it without a guide-token round trip.
Realtime editor sync now waits for the official `$e.run` promises and confirms
the expected widget count before reporting success; only an unconfirmed sync
falls back to a saved-preview refresh. Vision capture waits for document fonts
and pending images so it reviews a stable render rather than an intermediate
layout.
Targeted native patches are server-scoped to the selected Elementor element
ids; prompt instructions alone are not trusted as an authorization boundary.
Action requests allow up to 8000 completion tokens and explicitly constrain
the provider to compact JSON with at most two containers and eight widgets,
without duplicated Elementor defaults or optional settings.

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

Release state: local `v02.09.73` prepared, including the LLM proxy, Elementor editor chat,
action execution, Enter-to-send behavior, provider-response diagnostics,
JSON chat logs, initial empty-page action support, design-system
normalization, native widget content alias mapping, safe operational step traces,
guided editor context, editor preview synchronization with cache-busted iframe
reload and native-widget load confirmation, separate progress
messages, the non-empty native-widget action gate, and transaction failure
diagnostics, post-write AI Vision review and rollback, and Vision failure
diagnostics, the masked AI Vision key field, chat model/version metadata, the new
Vision default model, the populated-native-widget action rule, and action JSON
 diagnostics and live Elementor preview refresh are published on GitHub `main`; `v02.09.50` keeps testimonial quotes as text and assigns card-heading icons only to short heading/author content, while preserving the compact outlined badge and transparent card-grid container. `v02.09.49` turns the badge into a compact native Flexbox pill container with a heading label, preserves its transparent background and dark outline, and forces card-grid containers to remain transparent. `v02.09.48` adds the enforced outlined badge and card-heading icon grammar after provider decoding, fallback/repair normalization, and before Elementor preflight. `v02.09.25` fixes the native Elementor card sizing mode with custom flex sizing and explicit grow/shrink values. `v02.09.24` adds backend bento normalization for repeatable blocks, native Flexbox card wrapping, a four-items-per-row cap, and single-column mobile sizing without adding the layout requirement to the user prompt. Earlier releases: `v02.08.96` added the stronger realtime canvas refresh, the hero composition guard, and the strict editor Vision quality gate, `v02.08.97` disables visual-regression comparison for first insertion into an empty Elementor post while retaining it for existing content, `v02.08.98` makes regression comparative and raises the compact action-JSON budget, `v02.08.99` skips unreliable public baselines for draft posts, `v02.09.00` skips editor Vision rollback for draft posts, `v02.09.01` verifies that the preview contains the newly inserted widgets, `v02.09.02` refreshes the preview URL version key, `v02.09.03` synchronizes saved action elements into the open Elementor editor model through the official create command, `v02.09.04` accepts double-encoded JSON returned by OpenAI-compatible providers, `v02.09.05` requests provider JSON mode and compatible OpenRouter routing for action commands, `v02.09.06` retries `openrouter/free` without optional structured-output parameters when that route rejects them, `v02.09.07` exposes sanitized provider transport details in editor chat errors, `v02.09.08` constrains action prompts to one compact populated hero container, `v02.09.09` adds one bounded repair pass for invalid action shapes, `v02.09.10` gives that repair pass a minimal JSON-only context, `v02.09.11` passes the exact current post_id into repair prompts, `v02.09.12` requires non-empty heading, text-editor, and button content in the repair-pass contract, `v02.09.13` keeps low Vision score as a warning while reserving rollback for major or critical findings and exposes report details in chat errors, `v02.09.14` gives the bounded action repair-pass a concrete populated native-widget JSON exemplar, `v02.09.15` retries an invalid repair response once and reports the failure details instead of silently stopping, `v02.09.16` keeps major Vision findings advisory so only critical findings roll back a successful editor-chat write, `v02.09.17` forbids placeholder content in repair responses and asks for request-specific copy, `v02.09.18` excludes Elementor editor-only overlays and dropzones from the Vision screenshot to prevent false critical findings, and `v02.09.19` makes editor-chat Vision reports advisory while retaining strict rollback only for transaction Vision gates.
- `v02.09.27` keeps section headings, intro copy, and CTA outside repeatable bento card grids; deterministic benefits, pricing, process, and portfolio fallbacks now create populated native card containers.
- `v02.09.46` centralizes content-only pair parsing, supports `вопрос? — ответ`, writes fallback card widgets through real arrays, fills FAQ tabs, and expands repeatable fallback cards up to four items. The local fallback matrix covers benefits, testimonials, pricing, process, FAQ, portfolio, hero, and four-card content without fidelity mismatches.
- `v02.09.47` focuses the editor preview on the newly inserted root `data-id` before screenshot capture, sends those target IDs through the server Vision normalizer, and refuses to review a stale viewport when the new block cannot be found after one bounded preview refresh.
- `v02.09.48` enforces one outlined rounded badge per generated root block and native `icon-box` headings with left-side icons inside repeatable cards, independent of provider prompt compliance.
- `v02.09.49` renders that badge as a compact native pill container with a heading label and keeps the container around repeatable cards transparent.
- `v02.09.50` prevents testimonial quotes from being misclassified as card headings: quote-like heading/icon-box widgets are demoted to text, while short author/heading content receives the native icon-box treatment.
- `v02.09.63` keeps the generated outlined badge above the heading in a vertical root shell while preserving the block's horizontal composition inside a transparent full-width child; fallback layout variants cannot restore a horizontal root around that shell.
- `v02.09.64` keeps generated root containers and bento-grid containers transparent after fallback/provider variation mapping; card surfaces remain available, and the action rule no longer asks providers to paint the outer root.
- `v02.09.65` also resets the background on a provider-supplied existing content-shell, preventing a white wrapper from surrounding otherwise transparent bento cards.
- `v02.09.66` removes asymmetric fallback card-width variants that produced oversized lead cards, 23% orphan cards, and uneven row heights; bento variants now use balanced two-column or three-/four-column rows with stretched card heights.
- `v02.09.67` keeps a standalone CTA from a labeled content-only brief in requested-content fidelity and fallback button mapping, preventing Vision from accepting a visually complete but content-incomplete block.
- `v02.09.68` forces three-card bento rows to stay on one balanced three-column line, protects generated transparent shells/root backgrounds from token-map surface/paper replacement, and keeps the two-column variation for two- and four-card compositions.
- `v02.09.69` waits for two animation frames plus a short paint settle before the editor Vision screenshot, and tells Vision to trust the captured target's objective `text_excerpt` for content presence instead of treating an ambiguous crop as missing copy.
- `v02.09.70` applies a content-only brief's extracted CTA recursively to every native button in the fallback tree, including block-level buttons after bento grids.
- `v02.09.71` classifies three labeled content-only case entries as a portfolio card set before the CTA keyword can misclassify the whole brief as a generic CTA block.
- `v02.09.72` normalizes every provider-supplied `wpae-bento-grid` after decoding: the grid stays transparent and its direct cards receive balanced widths by count, with a 100% mobile width, even when the provider returned an oversized lead card.
- `v02.09.73` centralizes that bento normalization for grids created after provider-card wrapping, keeping every row at two, three, or four balanced cards instead of preserving a 48/23/23 lead-card distortion. Vision now filters only verified missing-content false positives when the requested phrase exists in the target `text_excerpt`; layout findings remain active.
- `v02.09.51` captures only the new Elementor root for editor Vision and reports a missing or zero-size target instead of sending the old full-page viewport for review.
- `v02.09.52` protects generated badge padding/radius from design-token remapping, enforces explicit pill geometry, resets the label margin, and replaces model-supplied badge variants with the canonical generated badge.
- `v02.09.53` adds explicit selected-Elementor JSON export to the floating chat, including native settings and recursive child elements without adding the full payload to LLM requests.
- `v02.09.54` replaces chat control text with Elementor icon buttons while retaining accessible labels and tooltips.
- `v02.09.55` fixes the chat input height to match the 42px send icon button and disables outer textarea resizing.
- `v02.09.56` recognizes natural content-only briefs made from sentence or line units with a CTA/content signal, so the editor chat requests generation instead of returning advisory instructions.
- `v02.09.57` carries plain sentence/line content into fidelity checks and fallback widgets when a content-only brief has no labels or quotes.
- `v02.09.58` gives provider generations a per-run composition seed and makes deterministic fallback blocks choose an unused visual variant from a ten-variant palette, preserving the content and applying the variation after design-token mapping.
- `v02.09.59` applies that unused visual-variant guard to provider-generated blocks as well as fallback blocks, keeps each repeated-card grid on one coherent card surface, and hides the stale Elementor preview loader after the iframe has populated so realtime insertion and Vision capture see the rendered block.

The latest local release `v02.09.73` is prepared for publication on GitHub `main`. On
2026-08-30, when the original Elementor tab continued serving v02.09.40, a
same-URL duplicate tab loaded v02.09.41 and the stale tab was closed. After the
v02.09.42 delivery, a second same-URL duplicate tab loaded v02.09.42 and the
previous tab was closed. The v02.09.42 browser test confirmed the provider
retry message and one retry after reload; the configured OpenRouter route then
timed out twice with cURL error 28, so no design success was claimed. The
v02.09.34 browser test reproduced a pricing fallback defect:
the first tariff label was promoted into the root heading and its card was
missing; AI Vision correctly rejected the result and rolled it back.
`/wp-json/ai-executor/v1/key` endpoint returns `404`.

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

## Normalizer update

`v02.09.28` improves the shared Elementor normalizer: legacy alignment/gap aliases migrate to current Flexbox controls and responsive variants, containers receive `container_type=flex`, and native buttons receive explicit design-token background/text colors so theme defaults cannot change the generated contrast. `v02.09.29` extends the chat contract with strict root-shape validation, operation IDs, compact diffs, targeted native patches, one-click undo, stable realtime sync confirmation, and advisory Vision render context. `v02.09.30` adds deterministic content-fidelity validation, content-aware fallback repair, and passes the user brief plus a bounded preview text excerpt to the Vision review. `v02.09.32` also extracts unquoted natural-language label/description pairs and maps them into fallback cards before the fidelity gate. `v02.09.33` makes Vision score below 75 and major/critical findings blocking, returns the actual `quality_failed` state, and makes the editor chat attempt rollback after a failed Vision review. `v02.09.34` fixes a missing nested-array bracket in the targeted Elementor patch progress merge that caused a PHP parse fatal during bootstrap. `v02.09.35` fixes pricing fallback label parsing, keeps explicit tariff content inside separate cards, and maps `Кнопка: ...` into native card buttons.

## Next block-library steps

- visual library browser and insertion action inside Elementor;
- update/duplicate endpoints and richer category/tag management;
- previews and screenshots;
- media dependency transfer between sites;
- richer native token controls in the dashboard;
- live AI Vision provider and transaction-gate smoke tests;
- runtime REST/editor tests on a WordPress + Elementor installation.
