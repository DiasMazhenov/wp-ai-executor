# M2.1 editor integration and cross-family acceptance — v02.11.245

## Source and scope

Baseline `ab4ec235cb84fef7a9e61db2efb42ce448ba0333`, main, tracked tree clean; 776 foreign untracked files preserved. Current source v02.11.245, frontend build `composition-ui-v1`, Brief parser unchanged v11. Source, publication, installation, generation, readback and visual acceptance are separate statuses.

## Implementation

- `recipes.php::wpae_composition_editor_catalog()` projects 17 implemented distinct records from the existing catalog: ID/version/family and human labels/profile availability only. No alias, trees, tokens or credential. Services remains automatic. The existing API-key-only recipes endpoint and authorization are unchanged.
- `editor-chat.php` localizes this projection and pipeline mode via existing WPAELLMChat. `savedBaseline` reports valid-array/unavailable, SHA256 of canonical JSON and saved root IDs; errors remain errors rather than an empty page. `frontendBuild` distinguishes this UI from previous source changes published under v244.
- JS builds grouped native composition/profile selects next to the input. Automatic has no overrides; profiles filtered by selected record. No frontend family classifier and no composer/second write.
- The existing submit captures exact selection and a complete delivery snapshot (message/history/context, including selected elements and operation identity). Replay and automatic provider/rate-limit delivery reuse the snapshot; changing dropdown cannot change delivered operation. Stale/missing snapshots stop. A new submit receives a new identity. Request-in-flight and scheduled retry prevent duplicate submits. Selection excluded with any selected element, targeted/replacement/Vision flags and off/shadow. Selected elements and existing guards remain intact.
- LayoutReport reads accepted Plan tokens, responsive component gaps and supported boxed copy width clamp. Outer column allocation and inner text width remain separate fields. Static breakpoint/font assumptions remain assumptions; `visual_render_verified=false`. No claim of real DOM geometry.

## Local evidence

Runtime: 1072 PASS; DesignPlan: 319 PASS; patch guard PASS; Node: 8/8 PASS (including two executable UI tests). UI tests execute the safe PHP projection, real selection helpers and existing request function with captured fetch payloads. They cover Automatic/profile filtering, no aliases, exact POST, busy suppression, immutable replay, stale replay refusal, new-submit identity, targeted/replacement/Vision/off/shadow exclusions. Existing backend refusal cases cover unknown/stale version/record without write. New PHP checks compare accepted D/T/M gaps and copy width to native controls for both profiles, even when unrelated report options provide conflicting values.

An initial concurrent Node run hit its existing PHP subprocess timeout and the old regex expectation for retryCurrentOperation. The expectation was updated to the new delivery-retry contract (true plus original snapshot), supported by executable replay assertions. Assertions/timeouts were not weakened. The isolated full Node run passed 8/8. PHP lint, JS syntax and git diff --check PASS; imported catalog158/156/156 PASS; manifest250/250 hashes and all4 package-probe scenarios PASS. Six packaged hashes changed. Historical full-result ZIP serialization limitation remains unchanged. Narrow isolated Graphify AST index refreshed for4 affected files (127nodes/338edges); foreign repository graphs untouched.

## Browser baseline before installation

Documented `mcp__node_repl__js` Browser Plugin setup succeeded. Current browser id16: public tab3 (`pricing-contract-live-v123`), editor tab4 post5214. No new tab/page created. Browser viewport capability is available in this session, unlike the previous historical restriction.

At CSS viewport 1238×923, existing inline editor version v02.11.244, pendingOperation=null, root-owner map empty; Elementor initial_document.elements=[]; Publish disabled, old Update control has elementor-disabled. Public DOM has no post5214 roots. This alone does not prove saved `_elementor_data=[]`; source-owned savedBaseline will be read after installation. No generation/write/reload has occurred during this initial capture. Pipeline mode not yet confirmed.

## Acceptance matrix

[Machine-readable matrix](evidence-matrix.json). A–J pending. Media must be verified from an existing authorized asset, not mock example.com URLs. Full requests, root/operation/trace, first-result rubric, readback/native/DOM, D/M screenshots and Undo outcomes are recorded only after actual actions. Missing saved state or a failed cleanup guard stops dependent writes.

Source local checks complete; commit/push/install/editor/generation/save-readback/public desktop/public mobile/visual acceptance: pending. No live PASS or screenshot claim yet.

## Live v245 and marker repair v246

v245 source commit bd03882dc749476c5ecf5d7077750bbdc9d209ed independently matched origin/main. WP Pusher reported successful update; Plugins and reloaded editor confirmed v02.11.245, composition-ui-v1, 17 records, active mode. Actual PHP 8.3.22 (Site Health). Saved baseline valid_array, roots [], SHA256 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945; no unsaved changes before reload.

A first result: FAIL before write, operation wpae-c1d5962e969af28c, hero.text_only/editorial_light, provider_calls=0, write_count=0, no root. frozen_decisions_changed_by_normalization: production required wpae-ds was added after compiler freeze. Diagnostic preserved in A-first-diagnostic.json; first-failure editor PNG 1238x923 visually inspected. No visual acceptance.

v246 emits required root design-system classes before freeze in native compiler. Signature exclusions unchanged; author class mutation remains detected. Regression uses actual required classes and empty saved baseline matching live target; historical neighbor fixtures remain unchanged. Runtime 1075, DesignPlan 319, patch guard and package250/250 passed. Installation and affected live retry pending.
