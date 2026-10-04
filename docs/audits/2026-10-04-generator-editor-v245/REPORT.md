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

## Verified installation, A readback and blocked continuation

Source v246 commit `7a8b62449bba636613d6acb30f45da26273755e9`; push succeeded and independent ls-remote matched. WP Pusher successful-update notice, Plugins v02.11.246, same editor tab4 after reload v02.11.246/build composition-ui-v1/active/17 records. Actual PHP 8.3.22 previously read from Site Health. Two existing tabs only.

A request (unchanged for v245 refusal and v246 retry):
```text
Создай Hero text-only без фото
Надзаголовок: «СТУДИЯ»
Заголовок: «Работа со смыслом»
Описание: «Согласуем задачу и доведём проект до результата.»
Кнопка: «Начать», ссылка #start
```
No assets requested or inserted. First v245 result refused before write; runtime fix v246 described above. First v246 operation `wpae-486ca1951e866c95`, root `37b84f9`, Brief hash `1bc00c81f7fb753224a0ae227333c3557662bd251bc9a393563f32fa6ae87fb9`, provider_calls=0/write_count=1/no library retrieval; trace explicit record hero.text_only/version1/profile editorial_light. Native four widgets: heading/heading/text-editor/button.

Existing automatic Vision evaluated first render score60/confidence95 and initiated guarded replacement before manual first-result PNG capture. It reported sparse layout/weak hierarchy. This advisory score does not establish the rubric; operation-bound report ID is not confirmed (ledger vision_report_id empty). Original first-render screenshot unavailable. `A-v246-first-editor.png` filename is historical: it actually shows the replacement transaction and MUST NOT be called a first-render screenshot. Both original and replacement diagnostic traces preserved in A-v246-diagnostics.json.

Automatic replacement operation `wpae-6a8fc1faa4a4e95b`, same root37b84f9, provider_calls=0/write_count=1, Brief hash343c1c272e52116f8fb06ac878b222235d66932f7e9a3b93267511348c5c0843. Trace composition source explicit_brief; resolved_visual=[]; selected editorial_light not retained. A therefore FAIL for selection fidelity even though exact copy/CTA survives. No manual cosmetic patches used. Preview sync subsequently reported no widgets; after normal Publish/save and same-tab reload editor and public have root and all four native widgets.

Saved readback baseline hash `4f391944c856d1a024be825d77bb4275ad863eab616d534c12e0531e8fb46d4d`, root set [37b84f9]. Public exact eyebrow/title/body/button and href #start match saved native JSON. Desktop public measured1280x900, scrollWidth1280; mobile390x844/scrollWidth390; narrow320x844/scrollWidth320. At320 title32px/35.2px line height wraps into two lines; all content within horizontal bounds. Desktop title52px/57.2px, body17px/28.05px, component gap20px. Mobile measured widget y positions86/126.398/175.602/242.398, no overlap; title at320 y126.398 h70.406. Final screenshot visual inspection: copy readable and CTA distinct, sparse white presentation, not requested profile. Root copy boxed width38rem in saved native tree. Screenshot before Publish differs (beige background/left alignment), so only after-reload PNGs below describe final state. Static LayoutReport remains visual_render_verified=false.

Final-result rubric: hierarchy2, spacing1 (sparse), typography2, media N/A (text-only), contrast1 (readable by visual inspection; full computed ancestor-background contrast not measured), responsive2 for measured1280/390/320 only, brief fit0 (profile lost). First-result rubric NOT VERIFIED: auto replacement preempted manual evidence. Tablet/boundary and operation-bound Vision acceptance not complete; no blanket design PASS.

**Cleanup BLOCKED:** After required save/reload, chat is re-created without action history or Undo button. Current normal UI provides no guarded Undo for saved operation. No guard rejection is claimed: Undo request was not sent. Manual delete, endpoint fetch and whole-document restore were not used. B–J dependent generations stopped according to task instruction. Their requests/assets/operations remain not run; no fictitious media permissions or tests. Final root set [37b84f9]; no root removed; initial confirmed user root set[] preserved (there were no user roots). No new pages/drafts/tabs, other plugins/settings unchanged. Public viewport override reset.

Source/UI and installation confirmed; A generation/write/save/native/public readback verified; first visual/profile acceptance FAIL; B,C,D,E,F,G,H,I,J BLOCKED by safe cleanup unavailable. UI lifecycle/automatic Vision profile retention remain unresolved. Structured model extraction remains disabled.

## Verified PNG evidence

### A-v246-public-desktop-after-reload.png

post5214/root37b84f9/operation wpae-6a8fc1faa4a4e95b; source public; CSS viewport 1280×900; PNG 1280×900, 136182 bytes. Requested hero.text_only/editorial_light; final repair has no resolved profile. Fresh after save/reload, JPEG bytes converted to PNG, signature checked, opened and visually inspected. SHA256 `d4b42d7044a70fd483b558b7eb7c629828ee7ac8fa363c74014355ef457ce31b`.

![A-v246-public-desktop-after-reload.png](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-04-generator-editor-v245/A-v246-public-desktop-after-reload.png)

[Скачать screenshot](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-04-generator-editor-v245/A-v246-public-desktop-after-reload.png)

### A-v246-public-mobile.png

post5214/root37b84f9/operation wpae-6a8fc1faa4a4e95b; source public; CSS viewport 390×844; PNG 390×844, 76880 bytes. Requested hero.text_only/editorial_light; final repair has no resolved profile. Fresh after save/reload, JPEG bytes converted to PNG, signature checked, opened and visually inspected. SHA256 `e4e8115d996ef20383817dd3eb252c8c8ecc90677dba9f27ecf02f88d0d4690e`.

![A-v246-public-mobile.png](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-04-generator-editor-v245/A-v246-public-mobile.png)

[Скачать screenshot](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-04-generator-editor-v245/A-v246-public-mobile.png)

### A-v246-editor-after-reload.png

post5214/root37b84f9/operation wpae-6a8fc1faa4a4e95b; source editor; CSS viewport 1238×923; PNG 1238×923, 294660 bytes. Requested hero.text_only/editorial_light; final repair has no resolved profile. Fresh after save/reload, JPEG bytes converted to PNG, signature checked, opened and visually inspected. SHA256 `723b6fcef00e84e3ef67e2c6a3c8ca96d6eaee53015af28776b763cbe6e49eb8`.

![A-v246-editor-after-reload.png](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-04-generator-editor-v245/A-v246-editor-after-reload.png)

[Скачать screenshot](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-04-generator-editor-v245/A-v246-editor-after-reload.png)

Final v246 validation: runtime1075/DesignPlan319/patch guard/package250 and four probe scenarios PASS; full Node suite8/8 PASS with --test-concurrency=1. Concurrent full Node run hit the existing subprocess timeout; no assertion/timeout weakened. PHP lint and git diff --check PASS.
