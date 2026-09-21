# WP AI Executor Context

Последнее обновление: **2026-09-21 23:40:12 +05:00 (Asia/Almaty)**
Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Ветка: `main`
Target: **post=5214, Pricing Contract Live v123**. Новые pages/drafts не создавались.

## Source/live

- Source HEAD: `5457b84c084505a6c6008c0c768921e415eda35d`.
- Runtime/live version: **v02.11.140**.
- Live installation: PASS through WP Pusher from `DiasMazhenov/wp-ai-executor` `main`.
- Feature flags: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.
- Active/active priority: local deterministic pipeline first; EDDE is not run as a competing compiler.

## Current live operation

- Prompt archetype: pricing; one new independent pricing section.
- Operation: `wpae-66b8db408b2372e5`.
- Root: `b898e72`.
- Child roots: intro `132d92f`, pill `f36e38b`, group `dd8141d`, cards `132d1dc`, `2dd156c`, `fbbd57a`.
- Existing preserved roots: hero `a9282de`, FAQ `13568dc`.
- Route: `pipeline`; local deterministic; provider calls `0`; one Elementor write.
- Durable status evidence: `written`; reviewed/completed remains pending without a durable verifier/report ID after reload.

## Visual contract verified live

- Pricing root background: `#FFFFFF` / `rgb(255,255,255)`.
- Pill: `#4460EC`, white text, radius `999px`.
- Cards: white background, `1px solid #D1D5DB`, radius `8px`, padding `24px`.
- Desktop editor cards: `303.023px` each, one row.
- Mobile editor frame: `345px`; cards `297px`, column stack, no overflow.
- Public desktop cards: `359.09375px` each, one row.
- Exact links: `#start`, `#project`, `#support`.
- Beige `#F6F0E6` is intentional on existing hero `a9282de`; pricing no longer inherits it.

## Pipeline boundary

```text
BriefIR v1 -> DesignPlan v1 -> WidgetCapabilityRegistry/LayoutReport
-> ElementorIR v2 -> native compiler -> validation
-> existing transaction/update/read-back -> render -> Vision/reconcile
```

Legacy provider JSON, EDDE hero slice, transaction/rollback, retry/undo and page-update paths remain. No second write path, marketplace, new token system or new WordPress page was added.

## Source changes in v140

- `includes/llm/brief-ir.php`: longer quoted-value look-ahead preserves `#support`.
- `includes/llm/design-plan.php`: pricing section uses `color.surface` instead of `color.page_bg`.
- `tests/design-pipeline-contract.php`: exact CTA and white pricing surface regressions.
- `tests/llm-chat-contract.test.js`: v02.11.140 contract update.
- `wp-ai-executor.php`: runtime version v02.11.140.
- `wpae-package.json`: regenerated hashes.

## Checks

- `php -l` changed PHP files: PASS.
- `php tests/design-pipeline-contract.php`: PASS, 68 checks.
- `node --test tests/*.test.js`: PASS, 4 suites; 331 flex runtime checks.
- `php docs/audits/2026-09-12/package-probe.php`: PASS, 90 files, mismatches 0.
- `git diff --check`: PASS.
- Fresh editor/public DOM and inline CUA screenshots after save/reload: PASS.
- Filesystem PNG links: BLOCKED; CUA exposes inline screenshot bytes but no documented PNG writer/artifact export.
