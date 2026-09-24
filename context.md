# WP AI Executor Context

Последний срез: **2026-09-24 23:43 +05:00 (Asia/Almaty)**.

## Repository/runtime

- Checkout: `/Users/diasmazhenov/vibecode/wp-ai-executor`, branch `main`.
- Starting HEAD: `6c0abc8512be4e29bd0dfd22b4225de9c2189c3c`.
- Live/editor runtime: **v02.11.147**. Source now declares **v02.11.148**, not yet confirmed installed.
- A fresh `git fetch` could not write `.git/FETCH_HEAD`; `git ls-remote` could not resolve `github.com`. Remote freshness, push and installation remain unverified.
- Canonical project context is this lowercase `context.md`. Do not create `SESSION_CONTEXT.md` or a casing duplicate.

## Current target and visual evidence

- Work only on existing WordPress page `post=5214`, public `/pricing-contract-live-v123/`. No pages, drafts, or roots were created.
- Fresh public DOM has one `.wpae-generated-root`, ID `cd4da23`. Exact copy, CTA links `#contact` and `#projects`, pill «АРХИТЕКТУРА», and loaded architectural photo are present.
- Pill computed style: background `rgb(255,253,250)`, border `1px solid rgb(22,30,51)`, radius `999px`, padding `6px 12px`. Image widget `e5e26ab` loads 1800×1200 with `object-fit:cover`; `alt` remains empty. Root background is `#f6f0e6`.
- Current public viewport 911×933, DPR 2: copy and image each about 412px; image 411.5×460px; no horizontal overflow. Previous editor checks: desktop inner 946px → 368.8/553.2px (40/60); mobile preview 360×736 → copy-first stack, 313px children, image 313×300px.
- Fresh Browser Use public screenshot, visually checked: `/Users/diasmazhenov/vibecode/wp-ai-executor/wpae-post-5214-cd4da23-public-current-20260924.png` (911×933). Earlier editor screenshots remain in the repository; links and source details are in `LUNA_HANDOFF_REPORT.md`.
- This turn made no WordPress/page write. Visual properties were saved in the earlier Elementor editor session, not through the deterministic operation pipeline.

## Operation review/repair status

- Reloaded editor config selects unrelated operation `wpae-89007d964d7436ab`, identity `e12c2e08-2952-4906-8dbb-f66d3ed42eb1`, revision 5, state `written`, root `1fa90e6`.
- Its target status is `stale_target / root_missing`, `reviewable=false`; current config hides «Проверить сохранённый результат». Its saved hashes and fingerprints belong to `1fa90e6`, not `cd4da23`.
- Current ledger operation identity/revision/saved hash/fingerprint for `cd4da23` remain unverified live. Historical diagnostic `wpae-642778ef2b64e486` is not a server ledger readback. Do not infer owner from the editor's selected candidate.
- Source v148 adds protected GET `/ai-executor/v1/design-operations/target` to read at most 10 records claiming a given root. It uses existing `wpae_llm_chat_permission`, rechecks `current_user_can('edit_post', $post_id)`, returns no prompt text, calls the existing saved-target guard, and does not mutate the ledger. This route is not installed/live-verified yet.
- No targeted repair write or Vision review was performed. Do not bypass saved-hash/fingerprint ownership guards; existing reconcile is POST and can mutate state.

## Checks for source v02.11.148

- PHP lint: `wp-ai-executor.php`, `includes/elementor/operation-ledger.php`, `includes/rest/routes.php`, and `tests/design-pipeline-contract.php` — passed.
- `php tests/design-pipeline-contract.php` — 140 checks, including target scoping, immutable ledger read, and edit capability denial.
- `php tests/flex-generation-runtime.php` — 338 checks.
- `node --test tests/*.test.js` — 4 passed, 0 failed.
- `php docs/audits/2026-09-12/package-probe.php` — passed, 90 files, 0 hash mismatches; invalid hash, missing file, and unsafe path were rejected.
- Repeat `git diff --check` before commit; current report/context edits followed the last check.

## Screenshot procedure

Use only the existing in-app Browser Use tab: import `setupBrowserRuntime` from the current `browser-client.mjs`, choose the `iab` browser and existing tab for post 5214, capture `screenshot({fullPage:false})`, save bytes to an allowed absolute workspace path, inspect actual file type, convert JPEG to PNG with `sips` if needed, open with `view_image`, verify the requested target, then embed the absolute image and give a clickable link. Do not print base64, invent paths, create tabs/pages/drafts, or claim unseen content is present. Record post/root/operation identity only when verified, viewport, and editor/public source.

Полный текущий handoff и evidence: `LUNA_HANDOFF_REPORT.md`.
