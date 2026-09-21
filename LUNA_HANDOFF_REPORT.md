# WP AI Executor — актуальный handoff report

Дата фиксации: **2026-09-21 23:40:12 +05:00 (Asia/Almaty)**
Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Ветка: `main`
Целевая страница: **WordPress post=5214, Pricing Contract Live v123**
Новые pages, posts и drafts в этой работе не создавались.

## Итог текущего среза

Предыдущий результат v139 был устаревшим для визуальной приёмки: на странице оставался старый pricing-root с тёплым `color.page_bg` и укороченной ссылкой `#sup`. Это не было корректным результатом. Старый root `11c9734` удалён через Elementor на той же странице и сохранён. После установки v140 создан один новый независимый pricing-root `b898e72`.

На текущем live результате:

- pricing-секция имеет белый фон `rgb(255, 255, 255)`, а не бежевый `#F6F0E6`;
- надзаголовок — native pill с фоном `#4460EC`, белым текстом и radius `999px`;
- три native-карточки стоят в одну строку на desktop;
- каждая карточка имеет белый фон, border `1px solid #D1D5DB`, radius `8px` и padding `24px`;
- на mobile карточки идут одной колонкой без горизонтального overflow;
- CTA сохранены точно: `#start`, `#project`, `#support`;
- hero `a9282de` и FAQ `13568dc` сохранены.

Бежевый фон `#F6F0E6` теперь виден только у существующего hero `a9282de`, где он является его исходной поверхностью. Pricing-root больше его не наследует.

## Source/live release evidence

- Предыдущий runtime HEAD: `0fd1235a3f654eebeafae6fd0ed695dd527248c5` (v02.11.139).
- Текущий source HEAD: `5457b84c084505a6c6008c0c768921e415eda35d` — `Restore pricing surface and CTA fidelity`.
- Runtime version в исходниках: **v02.11.140**.
- Live version: **v02.11.140**, подтверждена в WordPress Plugins после WP Pusher update из `origin/main`.
- Runtime push: **PASS** по результату установки v140 через WP Pusher.
- Установочный источник: `DiasMazhenov/wp-ai-executor`, branch `main`.
- Исходные и итоговые flags: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.
- Временные переключения после проверки не оставлялись.

## Фактическая архитектура и routing

```text
prompt
  -> BriefIR v1
  -> typed DesignPlan v1
  -> WidgetCapabilityRegistry
  -> LayoutReport
  -> ElementorIR v2
  -> local native Elementor compiler
  -> structural/semantic/layout validation
  -> existing transaction/update/read-back boundary
  -> editor/public render
  -> Vision/design review
```

При `pipeline=active` и `EDDE=active` первым выбирается deterministic pipeline. EDDE остаётся typed hero slice и вторым конкурирующим compiler-ом не запускается. В v140 live diagnostics:

```json
{
  "action_path": "pipeline",
  "provider_calls": 0,
  "route": "local_deterministic",
  "design_pipeline": {"mode": "active", "status": "written"}
}
```

Contract/runtime matrix:

| Pipeline | EDDE | Action path | Provider calls | Page writes | Status |
|---|---|---|---:|---:|---|
| off | off | legacy provider | 1 | 1 | PASS, harness |
| off | active | EDDE | 0 | 1 | PASS, harness |
| shadow | active | EDDE + shadow comparison | 0 | 1 | PASS, no second write |
| active | active | local deterministic pipeline | 0 | 1 | PASS, live v140 |

The routing payload preserves unknown transport metrics as `null` with `metrics_known=false`; no zero cost, latency or success value was fabricated for a provider that was not called.

## Root causes and source fixes

### 1. Pricing inherited the warm page surface

The pricing plan used `color.page_bg` for the section surface, so the live result inherited `#F6F0E6`. `includes/llm/design-plan.php` now assigns `color.surface` to the pricing section. The compiler resolves that token to `#FFFFFF`; the live computed background is white.

### 2. Three cards could wrap and the old visual was mistaken for acceptance

The previous v138/v139 compiler path was corrected for gap-aware card basis and native border/radius/padding. The current v140 live root has three cards at `303.023px` in the editor frame and `359.09375px` in the public frame; all share one row at desktop.

### 3. CTA URL was truncated

`includes/llm/brief-ir.php` previously inspected only a 160-byte look-ahead after a quoted value, turning `#support` into `#sup`. The look-ahead is now 512 bytes. A regression asserts the exact third link.

### 4. Release integrity

`wp-ai-executor.php` and the version contract were bumped to v02.11.140. `wpae-package.json` hashes were regenerated and package verification reports no mismatches.

Changed in source commit `5457b84`:

- `includes/llm/brief-ir.php`
- `includes/llm/design-plan.php`
- `tests/design-pipeline-contract.php`
- `tests/llm-chat-contract.test.js`
- `wp-ai-executor.php`
- `wpae-package.json`

Legacy provider parsing, EDDE, transactions, page-update, read-back and retry/undo paths remain in place. No second write path or new token system was introduced.

## Exact live operation

Prompt used on post=5214:

```text
Добавь на текущую страницу только одну новую pricing-секцию. Не меняй существующие hero и FAQ и не добавляй другие roots. Надзаголовок: «ТАРИФЫ». Заголовок: «Выберите формат работы». «Старт» — «от 50 000 ₸» — «Для небольшой задачи с понятным объёмом». Кнопка: «Выбрать Старт», ссылка #start. «Проект» — «от 150 000 ₸» — «Для комплексной работы от идеи до результата». Кнопка: «Обсудить проект», ссылка #project. «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач и развития проекта». Кнопка: «Подключить поддержку», ссылка #support. На desktop три отдельные native карточки в одну строку, каждая с белым фоном, заметной обводкой color.border 1px, radius.card и component padding; надзаголовок — компактный pill-бейдж color.primary с белым текстом; фон всей pricing-секции белый, без бежевого page_bg; на mobile карточки stack.
```

- Operation: `wpae-66b8db408b2372e5`.
- Request type: new independent insert after the old pricing root was removed and saved; this was not a retry.
- New pricing root: `b898e72`.
- Intro: `132d92f`; pill badge: `f36e38b`; cards group: `dd8141d`.
- Cards: `132d1dc`, `2dd156c`, `fbbd57a`.
- Existing hero: `a9282de`; existing FAQ: `13568dc`.
- Native widgets: `container`, `heading`, `text-editor`, `button`; 14 native widgets reported in the operation diagnostics.
- Exact links in editor and public DOM: `#start`, `#project`, `#support`.

## Operation ledger and reconcile evidence

The operation UI and read-back confirmed the v140 write and current saved root. The durable server state is reported as `written`; a durable server-side `reviewed`/`completed` verifier ID was not exposed after reload, so this report does not promote the operation beyond `written/render_review_pending`.

Existing ledger contract checks remain green for:

| Scenario | Status |
|---|---|
| Same request delivered again | PASS: same operation identity, no duplicate root |
| Concurrent capture | PASS: lock conflict/unknown, journal is retained |
| Explicit new insert with same brief | PASS: separate operation identity is allowed |
| Timeout after a completed write | PASS in read-back contract; retry does not duplicate |
| Target changed after operation | PASS: conflict/unknown, no overwrite of foreign changes |
| Stale browser acknowledgement | PASS: operation/root/hash identity prevents cross-completion |
| Invalid state transition | PASS: allowlist rejects it |
| Artificial provider timeout live mutation | NOT RUN |
| Unsaved user-authored neighbor probe | NOT RUN |

Browser evidence is not treated as proof of server read-back or Vision success. `written`, `rendered`, `reviewed` and `completed` remain separate states.

## LayoutReport versus live DOM

LayoutReport remains a preflight prediction; computed DOM is the acceptance evidence.

### Desktop editor after save/reload

- CUA frame viewport: `1280×720`; Elementor preview inner width: `1010px`.
- Pricing root `b898e72`: width `1010px`, padding `0 24px`, background `rgb(255,255,255)`.
- Cards: width `303.023px`, x positions `24`, `351.023`, `678.047`; same y; border `1px solid rgb(209,213,219)`; radius `8px`; padding `24px`.
- Pill: `rgb(68,96,236)`, border `1px solid rgb(68,96,236)`, radius `999px`, padding `5.6px 12px`.
- No desktop wrap or horizontal overflow.

### Mobile editor after save/reload

- Actual Elementor emulation frame: `innerWidth=345`, `scrollWidth=345`.
- Pricing root: width `345px`, direction `column`, background white.
- Cards: each width `297px`, height `233px`, x=`24px`, y=`557.34375`, `806.34375`, `1055.34375`; direction `column`.
- Overflow query: no nodes outside the viewport.
- Mobile card order is copy-first and CTA text remains usable.

### Public page after save/reload

- Public viewport: `1280×720`; document content width `1265px` because of the scrollbar.
- Pricing root `b898e72`: x=`0`, y=`679.203125` before scroll; width `1265px`, height `429.375px`, background white.
- Public cards: each width `359.09375px`, x=`62.5`, `445.59375`, `828.6875`; same y=`827.578125`; border/radius/padding match editor.
- `.wpae-generated-pricing` count: `1`, id `b898e72`.
- Public CTA hrefs: `#start`, `#project`, `#support`.

The LayoutReport breakpoints still cover desktop/laptop/tablet/mobile and returned `mobile_stack_result=stack` with no violations. Direct live captures above are the authoritative geometry for the tested viewport sizes; no claim is made that LayoutReport replaces computed CSS or screenshots.

## Neighbor preservation

- No new WordPress page, post or draft was created.
- Hero `a9282de` and FAQ `13568dc` remained in editor and public DOM.
- The old pricing root was operation-owned and removed through the existing Elementor UI; no foreign/autosave root was removed.
- Current public DOM contains one generated pricing root only.
- The requested artificial unsaved-edit probe on a separate user-authored neighbor was not run; no neighboring user root was changed in this operation.

## Vision and render evidence

Fresh UI Vision after the v140 operation reported **score 85, confidence 95%**. Its finding was minor: card spacing/wrapping could be polished for mid-range viewports. The finding does not contradict the live desktop/mobile computed geometry above. Because a durable server-side Vision report ID was not available after reload, the operation remains `written/render_review_pending` in this report.

Fresh inline CUA captures were made after v140 save/reload on the existing page:

1. **Editor desktop** — post `5214`, root `b898e72`, operation `wpae-66b8db408b2372e5`, CUA viewport `1280×720`; white pricing surface, pill and three bordered cards visible.
2. **Editor mobile** — same post/root/operation, Elementor mobile mode, actual preview width `345px`; stacked cards and no overflow visible.
3. **Public desktop** — same post/root/operation, URL `https://mazhenov.kz/pricing-contract-live-v123/?wpae_check=pricing-v140-20260922`, public viewport `1280×720`; white pricing surface and three cards visible.

Screenshot file status: **SCREENSHOT FILE BLOCKED**. The documented CUA surface exposes screenshot bytes through `getScreenshot()` and inline `emitImage()`, but this session has no documented filesystem writer or screenshot artifact export. `tab.content.export()` is content export, not PNG capture. The three real screenshots were emitted inline; no fake absolute PNG links are recorded.

## Validation

All checks below passed after source v140 changes:

```text
php -l includes/llm/brief-ir.php
php -l includes/llm/design-plan.php
php -l wp-ai-executor.php
php tests/design-pipeline-contract.php            PASS (68 checks)
node --test tests/*.test.js                       PASS (4 suites; 331 flex runtime checks)
php docs/audits/2026-09-12/package-probe.php     PASS (90 files; mismatches=0)
git diff --check                                  PASS
```

Additional live checks passed:

- v02.11.140 visible in WordPress Plugins after WP Pusher installation;
- save/reload retained exact copy, root IDs, borders, radius, pill and CTA hrefs;
- public DOM contained one pricing root and no horizontal overflow in the tested editor mobile frame;
- existing hero and FAQ content remained unchanged.

## Status matrix

| Evidence | Status |
|---|---|
| Root cause identified (beige pricing surface) | PASS |
| White pricing surface in saved public DOM | PASS |
| Pill badge, card border and radius | PASS |
| Exact copy and CTA URLs | PASS |
| Active/active route without double write | PASS |
| Saved Elementor read-back root `b898e72` | PASS |
| Editor desktop geometry | PASS |
| Editor mobile stack/overflow | PASS |
| Public desktop DOM geometry | PASS |
| Fresh inline screenshots | PASS |
| Filesystem PNG links | BLOCKED by CUA capability |
| Durable reviewed/completed promotion | BLOCKED/PENDING |
| Unsaved neighbor mutation probe | NOT RUN |
| Artificial provider timeout mutation | NOT RUN |

## Commit, push and installation

- Source/runtime commit: `5457b84c084505a6c6008c0c768921e415eda35d`.
- Runtime push: **PASS** according to the v140 WP Pusher installation result.
- Live installation: **PASS**, active version v02.11.140.
- Documentation files are updated in this worktree after the runtime commit; they do not change the package hash manifest.

Исторические v137/v139 snapshots были заменены этим актуальным срезом. Они не являются доказательством текущего live результата.
