# WP AI Executor Context

Последнее обновление: **2026-09-22 01:33:54 +05:00 (Asia/Almaty)**
Target: **post=5214, Pricing Contract Live v123**. Новые pages, drafts и Elementor roots не создавались.

## Source/live

- Начало этапа: HEAD `438e5d9`, runtime `d34e401`, v02.11.141.
- Итоговый source HEAD: `4ae6f71cbdc0692efc1136f5766b7e0b6e2b1083`.
- Source/live version: **v02.11.142**.
- WP Pusher обновил активный плагин до v02.11.142: PASS.
- Первая ZIP-загрузка оставила одну неактивную копию v02.11.142; она ожидает удаления из списка плагинов.
- Push `438e5d9..4ae6f71 main -> main`: PASS.
- Flags: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.

## Architecture and routing

```text
BriefIR v1 -> DesignPlan v1 -> WidgetCapabilityRegistry/LayoutReport
-> ElementorIR v2 -> native compiler -> validation
-> existing transaction/read-back -> render -> Vision/reconcile
```

В active/active первым выбирается local deterministic pipeline. EDDE не запускается вторым compiler-ом; legacy provider JSON, transaction/rollback, retry/undo и существующий write path сохранены.

## Current saved roots

- Hero: `a9282de`, background `rgb(246,240,230)`.
- FAQ: `13568dc`.
- Pricing: `b898e72`, background `rgb(255,255,255)`.
- Hero CTA: `#contact`, `#projects`.
- Pricing CTA: `#start`, `#project`, `#support`.
- Temporary unsaved-neighbor marker and duplicate pricing root absent.

## v142 stale-operation guard

- `includes/elementor/operation-ledger.php`: `wpae_design_operation_target_status()` checks post, root scope, saved hash and target fingerprint; missing root is `stale_target/root_missing`, rollback is classified separately when proven.
- `includes/elementor/editor-chat.php`: exposes target status and `reviewable` to editor.
- `includes/llm/llm.php`: reconcile rejects stale/mismatched report identity before reviewed/completed.
- `assets/js/elementor-llm-chat.js`: no screenshot/provider fallback for a missing target; stale check button is hidden.
- `wp-ai-executor.php`: v02.11.142; `wpae-package.json`: 90 files, hash mismatches 0.

## Durable pending record

Operation `wpae-89007d964d7436ab`, identity `e12c2e08-2952-4906-8dbb-f66d3ed42eb1`, revision `5`, state `written`, root `1fa90e6`. Current roots do not contain `1fa90e6`; this stale target is the confirmed cause of the whole-operation capture failure, surfaced before v142 as `wpae_vision_capture_failed`. After v142 editor reload showed no `Проверить сохранённый результат` button and no page mutation. Record remains written/pending, not reviewed/completed.

Selected patch evidence is separate: `wpae-20260921192047-21f06cd2`, element `bc09c44`, Vision `100/100`.

## Live evidence

- Public DOM after v142 contains exact hero copy/CTAs, FAQ and pricing. Hero width `1265px`/height `342px`; FAQ `305.203125px`; pricing `429.375px`; no overflow.
- Editor: desktop `1010px`, tablet `753px`, mobile `345px`; mobile copy-first stack; no overflow.
- Fresh inline CUA screenshots were captured after v142 reload for editor desktop/mobile and public hero/pricing.
- Screenshot files/links are blocked: CUA documents inline screenshot bytes only and no PNG writer/artifact export.

## Checks

- PHP lint changed runtime files: PASS.
- `php tests/design-pipeline-contract.php`: PASS, 76 checks.
- `php tests/flex-generation-runtime.php`: PASS, 331 checks.
- `node --test tests/*.test.js`: PASS, 4 suites.
- `php docs/audits/2026-09-12/package-probe.php`: PASS, 90 files, mismatches 0.
- `git diff --check`: PASS.
- Whole-operation Vision: BLOCKED by stale missing target; selected Vision PASS.
