# WP AI Executor Context

## Current release

- Plugin: `v02.08.65`
- Guide: `v02.05.49`
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

The dashboard uses an accessible horizontal tab interface with five focused
sections: connection, Elementor, agents, monitoring and examples. The active
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

## Next block-library steps

- visual library browser and insertion action inside Elementor;
- update/duplicate endpoints and richer category/tag management;
- previews and screenshots;
- media dependency transfer between sites;
- richer native token controls in the dashboard;
- runtime REST/editor tests on a WordPress + Elementor installation.
