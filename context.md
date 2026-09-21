# WP AI Executor Context

## Current state

- Timestamp: 2026-09-21 22:56:25 +05:00 (Asia/Almaty).
- Repository: /Users/diasmazhenov/vibecode/wp-ai-executor, branch main.
- Runtime source commit: ad6bc5d326b78bfa74aef2f71212fbe5cdf19f53.
- Runtime/live version: v02.11.137.
- Target page: post=5214 only; no new WordPress pages, posts or drafts.
- Live install: PASS through WP Pusher from DiasMazhenov/wp-ai-executor main.
- Feature flags: Design Decision Engine=active, Deterministic Design Pipeline=active; active/active precedence is local deterministic pipeline.
- Current pipeline: BriefIR → DesignPlan → WidgetCapabilityRegistry/LayoutReport → ElementorIR → native compiler → validation → existing transaction/read-back → render → Vision/reconcile.

## Current live evidence

- Existing hero A root: a9282de. Exact copy and #contact/#projects links survived the pricing repair.
- Removed broken v136 pricing root: cb8db62, operation wpae-c8a717fb6f3eb12c. It used 33.333% × 3 with two 24px gaps and wrapped the third card.
- Current independent v137 pricing operation: wpae-3b645647657433c0, root 39a8c89.
- v137 request was a new insert with the exact pricing brief recorded in LUNA_HANDOFF_REPORT.md; it was not a retry of v136.
- v137 route: pipeline/local deterministic, provider calls 0, one page write.
- Exact pricing CTA links: #start, #project, #support.
- Editor desktop: cards 303.023px wide at x=24, 351.023, 678.047, same y, gap 24, no wrap.
- Editor mobile: innerWidth 360, scrollWidth 345, cards width 297, column y=732/931/1130, no overflow.
- Public DOM: viewport 1233×913, pricing root width 1233, cards width 359.09375 at x=46.5/429.59375/812.6875, one generated pricing root.
- v137 Vision UI: score 85, confidence 95%; minor padding finding. Server-side reviewed/completed was not claimed after reload; review remains pending without durable verifier/report evidence.
- Screenshots were captured fresh through embedded CUA and displayed inline for editor desktop, editor mobile and public desktop. CUA provides no documented writer/artifact export for screenshot bytes, so filesystem PNG links remain blocked; no fake path is recorded.

## Architecture constraints

- Keep the existing transaction/update/read-back boundary; no direct post-meta write path.
- EDDE remains a typed hero slice, not a universal DSL.
- Do not create SESSION_CONTEXT.md, CONTEXT.md duplicate, new pages/drafts, marketplace, embeddings, or a second token/write system.
- Preserve foreign/autosave/user-authored roots and existing links; operation-owned failed roots may be removed only through ownership/read-back-safe flow.
- Provider telemetry uses null plus metrics_known when a provider was not called; no fabricated cost or success.
- Pricing compiler reserves gap-aware 31.5% desktop basis for three cards and 100% mobile stack.

## Validation

- php -l wp-ai-executor.php: PASS.
- php -l includes/elementor/elementor-ir.php: PASS.
- php tests/design-pipeline-contract.php: PASS, 63 checks.
- node --test tests/*.test.js: PASS, 4 suites including 331 flex runtime checks.
- php docs/audits/2026-09-12/package-probe.php: PASS, 90 files, mismatches 0.
- git diff --check: PASS.
- Runtime push: PASS, origin/main at ad6bc5d.
- Documentation commit follows this context update.
