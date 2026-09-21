# WP AI Executor Context

## Текущее состояние

- Зафиксировано: 2026-09-21 20:52:00 +05:00 (Asia/Almaty).
- Репозиторий: /Users/diasmazhenov/vibecode/wp-ai-executor, branch main.
- Исходный HEAD live-сессии: bfdfc90038a48ca9b01c758d154027a99a46c0ac.
- Итоговый runtime/source HEAD: a9d92d67379ae4c2a92b7f005a09694bc6bea478.
- Source/live/install version: v02.11.132.
- Целевая страница: post=5214 только; новые WordPress pages/posts/drafts не создавались.
- Feature flags: Design Decision Engine=active, Deterministic Design Pipeline=active; при active/active precedence у pipeline.
- Runtime pipeline: BriefIR -> DesignPlan -> capabilities/LayoutReport -> ElementorIR -> native compiler -> validation -> существующий transaction/read-back -> render -> Vision/reconcile.
- Runtime install через WP Pusher: PASS, v02.11.132.
- Runtime push: PASS; документационный commit/push выполняется отдельно.

## Текущая live evidence

- Accepted A operation: wpae-c8d8403f5579e2a3, identity b7932c34-b562-4f2d-8c6c-7e57ac146731, root a9282de, route pipeline/local_deterministic, provider calls 0, one write.
- A exact content: Тихая форма, АРХИТЕКТУРА, Пространство для идей, Опишите задачу и получите понятный первый шаг, CTA #contact and #projects.
- A durable state: written/render_review_pending; rendered HTML hash and durable Vision report ID are empty. Editor Vision UI showed score 85/confidence 95%.
- A editor geometry: desktop copy/media 375.140625/562.859375 with 24px gap; tablet 340.3984375/340.6015625; mobile root width 345, column, copy x24 y24 w297 h318, media x24 y358 w297 h198, scrollWidth 345, innerWidth 360.
- A public geometry: viewport 1233x913, root a9282de width 1233 height 342, public child widths 446.3515625/669.6484375 with 24px gap, no horizontal overflow.
- B was a distinct request: operation wpae-dff2acb7714d94ff, identity 6ba18eb7-1c1a-47f9-9246-191ee48c5fc0, root bb838fb. Vision found empty visual placeholder; provider repair timed out; deterministic fallback failed exact content fidelity. B root was removed from the editor autosave model and A was saved with native Elementor Update. After reload, editor/public show A only.
- Final public readback has no bb838fb; specific pricing preservation and an unsaved-neighbor edit probe are not proven.
- Screenshot bytes were shown inline from the existing editor after save/reload (A desktop and mobile), but no filesystem screenshot paths exist. Public capture failed; report status is SCREENSHOT BLOCKED. Never invent screenshot links.

## Architecture constraints

- Keep the existing transaction/update/read-back boundary; no direct post-meta write path.
- EDDE remains a typed hero slice, not a universal DSL.
- Do not create SESSION_CONTEXT.md, CONTEXT.md duplicate, new pages/drafts, marketplace, embeddings, or a second token/write system.
- Preserve foreign/autosave/user-authored roots and existing links; operation-owned failed roots may be removed only through ownership/read-back-safe flow.
- Provider telemetry uses null plus metrics_known when a provider was not called; no fabricated cost or success.

## Historical record

Older release history is intentionally abbreviated here. Detailed historical changes remain in git history and prior report snapshots; current facts above override them.
