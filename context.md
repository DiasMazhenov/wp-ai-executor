# WP AI Executor Context

Последнее обновление: **2026-09-22 00:29:09 +05:00 (Asia/Almaty)**
Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Ветка: `main`
Target: **post=5214, Pricing Contract Live v123**. Новые pages/drafts не создавались.

## Source/live

- Source HEAD: `d34e401e4c8c91581b14a9766a42f9e24faa87c3`.
- Runtime/live version: **v02.11.141**.
- Live installation: **PASS** through WP Pusher; Plugins page showed v02.11.141.
- Push: **PASS** recorded as `2702845..d34e401 main -> main`; current shell could not re-resolve GitHub DNS for a second remote check.
- Feature flags: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.
- Active/active priority: local deterministic pipeline first; EDDE is not a competing compiler.

## Current saved roots

- Hero: `a9282de`.
- FAQ: `13568dc`.
- Pricing: `b898e72`.
- Pricing operation: `wpae-66b8db408b2372e5`.
- Pricing exact CTA hrefs: `#start`, `#project`, `#support`.
- Hero exact CTA hrefs: `#contact`, `#projects`.
- No temporary `WPAE-UNSAVED-NEIGHBOR-*` marker remains.

## Pipeline boundary

```text
BriefIR v1 -> DesignPlan v1 -> WidgetCapabilityRegistry/LayoutReport
-> ElementorIR v2 -> native compiler -> validation
-> existing transaction/update/read-back -> render -> Vision/reconcile
```

Legacy provider JSON, EDDE hero slice, transaction/rollback, retry/undo and page-update paths remain. No second write path, marketplace, new token system or new WordPress page was added.

## Critical live recovery fact

Revision `5303` removed the hero copy from the saved page representation. Revision `5300` retained hero, pricing and FAQ. Revision `5300` was restored through WordPress, then the restored Elementor model was saved through the existing Elementor UI so `_elementor_data` and public DOM matched. Final editor and public DOM contain all three roots above.

## v141 source changes

- `includes/llm/brief-ir.php`: structural quoted-value URL boundary, punctuation-safe URL preservation and explicit surface provenance.
- `includes/llm/design-plan.php`: explicit surface override validation and plan provenance.
- `includes/elementor/elementor-ir.php`: compiler application of explicit surface override.
- `includes/elementor/operation-ledger.php`: shared operation/report scope guard for revision, saved hash, fingerprint and roots.
- `includes/llm/llm.php`: Vision report identity/scope checks before reviewed/completed.
- Parser/design/vision/routing regression fixtures and v141 asset contract updates.

## Durable state

Localized pending metadata after reload exposed operation `wpae-89007d964d7436ab`, state `written`, revision `5`, root `1fa90e6`, with saved hash and target fingerprint. The root is not in the final current roots, so the record was not promoted. UI reconcile attempted fresh Vision and returned `wpae_vision_capture_failed`; reviewed/completed remains pending.

## Live unsaved-neighbor probe

Temporary native Text Editor baseline was saved, changed without Save, and preserved through one selected-scope AI patch and subsequent Save/reload. The temporary widget was then deleted and cleanup saved. Final page has no test marker; hero, FAQ and pricing remain.

## Live geometry

- Editor desktop: hero `1010px` wide, pricing `1010px` wide, no overflow.
- Editor tablet: `753px`, hero `flex-direction=column`, no overflow.
- Editor mobile: `345px`, hero `flex-direction=column`, copy-first order, no overflow.
- Public desktop: hero width `1265px`, pricing width `1265px`, pricing background `rgb(255,255,255)`, hero background `rgb(246,240,230)`, no overflow.

## Checks

- PHP lint for changed runtime files: PASS.
- `php tests/design-pipeline-contract.php`: PASS, 73 checks.
- `php tests/flex-generation-runtime.php`: PASS, 331 checks.
- `node --test tests/*.test.js`: PASS, 4 suites.
- `php docs/audits/2026-09-12/package-probe.php`: PASS, 90 files, mismatches 0.
- `git diff --check`: PASS before documentation update.
- Fresh editor/public DOM and inline CUA screenshots after save/reload: PASS.
- Whole-operation Vision report: BLOCKED by `wpae_vision_capture_failed`.
- Filesystem PNG links: BLOCKED; CUA exposes inline screenshot bytes but no documented PNG writer/artifact export.
