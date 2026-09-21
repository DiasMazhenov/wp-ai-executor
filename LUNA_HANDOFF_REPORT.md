# WP AI Executor — актуальный handoff report

Дата фиксации: **2026-09-22 00:29:09 +05:00 (Asia/Almaty)**
Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Ветка: `main`
Целевая страница: **WordPress post=5214, Pricing Contract Live v123**
Новые pages, posts и drafts в этом этапе не создавались.

## Фактический release-срез

- Зафиксированный перед этапом в задании baseline: `d69d734`; последняя runtime-версия в приложенном handoff была v02.11.126.
- Фактическое состояние checkout перед этим запуском: `2702845`, source/live v02.11.140.
- Текущий HEAD: `905df45`; runtime source commit: `d34e401e4c8c91581b14a9766a42f9e24faa87c3` (`Harden parser surfaces and vision reconcile`).
- Runtime version в исходниках и установленном WordPress: **v02.11.141**.
- Установка v141 через штатный WP Pusher: **PASS**; Plugins page показала v02.11.141.
- `git push origin main`: **PASS**, runtime push `2702845..d34e401`, documentation push `d34e401..905df45`.
- Повторный `git ls-remote origin` в этом запуске: **BLOCKED** из-за DNS/network; локальная tracking-ref `origin/main` отсутствует.
- Исходные и итоговые flags: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.
- Временные переключения после live-проверок не оставлялись.

## Архитектура и приоритет routing

```text
prompt
  -> BriefIR v1
  -> typed DesignPlan v1
  -> WidgetCapabilityRegistry
  -> LayoutReport
  -> ElementorIR v2
  -> native Elementor compiler
  -> structural/semantic/layout validation
  -> existing transaction/update/read-back boundary
  -> editor/public render
  -> Vision/reconcile
```

При `pipeline=active` и `EDDE=active` первым выбирается local deterministic pipeline. EDDE не запускается вторым конкурирующим compiler-ом. Legacy provider JSON, EDDE hero slice, transaction/rollback, page-update, retry/undo и browser sync сохранены.

| Pipeline | EDDE | Action path | Provider calls | Page writes | Результат |
|---|---|---:|---:|---:|---|
| off | off | legacy provider | 1 | 1 | PASS, contract harness |
| off | active | EDDE | 0 | 1 | PASS, contract harness |
| shadow | active | EDDE + shadow comparison | 0 | 1 | PASS, shadow не пишет |
| active | active | local deterministic pipeline | 0 | 1 | PASS, live pricing operation |

Для v140 pricing operation фактическая diagnostics была `action_path=pipeline`, `route=local_deterministic`, `provider_calls=0`, одна Elementor write. Для v141 selected-scope patch использовался legacy native patch boundary, без нового write path.

## Изменения v141 и исправленные root causes

### Структурный URL parser и explicit background

`includes/llm/brief-ir.php` больше не использует фиксированный look-ahead для связывания URL с quoted value. Граница строится до следующей структурной quoted value; сохраняются UTF-8, переносы, несколько CTA и соседние карточки. URL очищается только от завершающей пунктуации.

Явный цвет из prompt (`фон`, `background`, `surface` + hex) записывается как `surface_color` с provenance/source span. Отсутствие explicit цвета не изменяет глобальную design system.

### Surface precedence

`includes/llm/design-plan.php` валидирует explicit surface override. `includes/elementor/elementor-ir.php` передаёт его в compiler после semantic token resolution. Precedence остаётся:

```text
explicit user input > reference style > page design system > project design system > safe default
```

Pricing root `b898e72` использует `color.surface` и в live DOM имеет `rgb(255,255,255)`. Hero `a9282de` сохраняет свой explicit/default surface `rgb(246,240,230)`; глобальные цвета сайта не менялись.

### Durable report scope для reconcile

`includes/elementor/operation-ledger.php` добавляет общий guard `wpae_design_operation_report_scope_matches()`. Он проверяет post id, operation id/identity, revision, root scope, saved hash и target fingerprint. `includes/llm/llm.php` применяет guard к Vision report перед `reviewed/completed`; источник report должен быть provider. Устаревший browser acknowledgement не может завершить другую операцию или понизить terminal state.

### Сохранение package integrity

`wpae-package.json` пересобран до v141; package probe прошёл без hash mismatch. Runtime changed files были включены в commit; секреты, cookies, tokens и screenshots в commit не включались.

## Критическая live-находка и восстановление hero

После установки v141 read-only проверка показала, что ранее документированный hero `a9282de` отсутствовал и в editor iframe, и в public DOM; pricing `b898e72` и FAQ `13568dc` оставались. Это было реальное сохранённое расхождение, а не stale preview.

WordPress revision page показала:

- revision `5303` удаляет из diff `Тихая форма`, `АРХИТЕКТУРА`, `Пространство для идей`, body и обе CTA;
- revision `5300` содержит hero как неизменённый контент и сохраняет pricing/FAQ.

Штатно восстановлена revision `5300` на том же post=5214. Revision restore обновил editor preview, но public `_elementor_data` стал актуальным только после штатного Elementor save; после этого public DOM снова содержит hero root `a9282de`. Pricing и FAQ не пересобирались и не удалялись.

## Exact live operations

### Сохранённый pricing operation

Prompt v140 на post=5214 был независимой вставкой pricing, не retry:

```text
Добавь на текущую страницу только одну новую pricing-секцию. Не меняй существующие hero и FAQ и не добавляй другие roots. Надзаголовок: «ТАРИФЫ». Заголовок: «Выберите формат работы». «Старт» — «от 50 000 ₸» — «Для небольшой задачи с понятным объёмом». Кнопка: «Выбрать Старт», ссылка #start. «Проект» — «от 150 000 ₸» — «Для комплексной работы от идеи до результата». Кнопка: «Обсудить проект», ссылка #project. «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач и развития проекта». Кнопка: «Подключить поддержку», ссылка #support. На desktop три отдельные native карточки в одну строку, каждая с белым фоном, заметной обводкой color.border 1px, radius.card и component padding; надзаголовок — компактный pill-бейдж color.primary с белым текстом; фон всей pricing-секции белый, без бежевого page_bg; на mobile карточки stack.
```

- Operation: `wpae-66b8db408b2372e5`.
- Root: `b898e72`.
- Intro: `132d92f`; pill: `f36e38b`; cards group: `dd8141d`.
- Existing roots: hero `a9282de`; FAQ `13568dc`.
- Native widgets: container, heading, text-editor, button.
- Exact CTA hrefs: `#start`, `#project`, `#support`.

### Selected-scope patch и unsaved-neighbor probe

Первая проба с неявной формулировкой была отклонена без записи: `Новый deterministic design pipeline отклонил запись.` Двойного root не появилось.

Для обязательного probe на той же странице был добавлен native Text Editor внутри hero и сохранён baseline `WPAE-UNSAVED-NEIGHBOR-BASELINE`. Затем без Save текст изменён на `WPAE-UNSAVED-NEIGHBOR-MODIFIED`. Выбранным AI target был отдельный hero eyebrow `АРХИТЕКТУРА` (`bc09c44`), а не соседний Text Editor.

Точный successful request:

```text
Измени только выбранный элемент. Установи его заголовок ровно «АРХИТЕКТУРА» и оставь HTML-тег H6. Это точечная правка выбранного элемента: не добавляй root, не пересобирай страницу и не меняй hero title, body, CTA, FAQ, pricing или несохранённый Text Editor.
```

- Operation id из UI: `wpae-20260921192047-21f06cd2`.
- Changed element: `bc09c44`; selected scope count: `1`.
- Preview сразу после patch сохранил `WPAE-UNSAVED-NEIGHBOR-MODIFIED`.
- После Save/reload тот же unsaved-neighbor text был прочитан в editor preview.
- Временный Text Editor удалён через его context menu и сохранён cleanup.
- После cleanup marker отсутствует в editor и public DOM; hero, FAQ, pricing и CTA остались.

## Durable ledger и reconcile

После reload Elementor localized state показывает pending record:

```json
{
  "operation_id": "wpae-89007d964d7436ab",
  "operation_identity": "e12c2e08-2952-4906-8dbb-f66d3ed42eb1",
  "revision": 5,
  "root_ids": ["1fa90e6"],
  "current_state": "written",
  "saved_hash": "present",
  "target_fingerprint": "present"
}
```

Этот pending record не соответствует текущим live roots `a9282de`, `13568dc`, `b898e72`; он оставлен сервером в честном `written`, а не promoted в `reviewed/completed`.

Штатная кнопка `Проверить сохранённый результат` выполнила актуальный preview/reconcile flow. Результат:

```text
wpae_vision_capture_failed
Не удалось снять screenshot сохраненного preview для AI Vision.
rollback: не выполнен
```

Поэтому browser evidence не объявлена доказательством server read-back или Vision. `written`, `rendered`, `reviewed` и `completed` разделены; текущий durable result остаётся `written`/review pending.

Local contract/runtime checks для повторной доставки, конкурентного захвата, независимой вставки с тем же brief, timeout-after-write read-back, stale acknowledgement и допустимых state transitions — **PASS**. Прямой artificial provider-timeout live mutation — **NOT RUN**.

## LayoutReport против live DOM

LayoutReport остаётся предварительным прогнозом; computed DOM — acceptance evidence.

| Срез | Viewport | Root | Geometry | Overflow |
|---|---:|---|---|---|
| Editor desktop | `1010px` inner width | hero `a9282de` | width `1010px`, height `406px` | false |
| Editor desktop | `1010px` inner width | pricing `b898e72` | width `1010px`, height `432.375px` | false |
| Editor tablet | `753px` | hero `a9282de` | width `753px`, height `406px`, `flex-direction=column` | false |
| Editor mobile | `345px` | hero `a9282de` | width/clientWidth `345px`, height `580px`, `flex-direction=column` | false |
| Public desktop | `1265px` content width | hero `a9282de` | width `1265px`, height `342px`, background `rgb(246,240,230)` | false |
| Public desktop | `1265px` content width | FAQ `13568dc` | width `1265px`, height `305.203125px` | false |
| Public desktop | `1265px` content width | pricing `b898e72` | width `1265px`, height `429.375px`, background `rgb(255,255,255)` | false |

Editor mobile text order is copy first, then visual zone; both CTA labels remain visible. Live DOM contains no horizontal overflow in the tested desktop/tablet/mobile states.

## Live content and neighbor preservation

Final public DOM at `https://mazhenov.kz/pricing-contract-live-v123/?wpae_check=final-v141-20260922` contains:

- hero exact copy: `Тихая форма`, `АРХИТЕКТУРА`, `Пространство для идей`, `Опишите задачу и получите понятный первый шаг`;
- hero CTA: `Начать проект` → `#contact`, `Смотреть проекты` → `#projects`;
- FAQ exact existing copy and root `13568dc`;
- pricing exact copy, three cards, root `b898e72`;
- no temporary `WPAE-UNSAVED-NEIGHBOR-*` marker;
- one generated pricing root, no duplicate pricing root.

No new WordPress page, post or draft was created. The accepted pricing root was not deleted or recreated during v141; the only deleted element was the temporary native Text Editor created for the explicit unsaved-neighbor probe.

## Vision and screenshot evidence

- Successful selected-element Vision during the patch: **score 100, confidence 100%**; source text and H6 tag matched.
- Whole pending-operation Vision/reconcile: **BLOCKED** by `wpae_vision_capture_failed`; no durable Vision report id was created/accepted for that pending record.
- Fresh inline CUA screenshots were emitted after v141 save/reload on the existing post:
  - editor desktop: hero, FAQ top and native layout visible;
  - public desktop: hero `a9282de` with exact copy and both CTA visible;
  - editor mobile: Elementor mobile mode, copy-first stack, visual zone below CTA;
  - editor tablet geometry was read-only checked at `753px`.

Screenshot file status: **SCREENSHOT FILE BLOCKED**. The documented CUA surface returns screenshot bytes for inline `getScreenshot()`/`emitImage()`, but this environment exposes no documented PNG filesystem writer or screenshot artifact export. `tab.content.export()` exports page content, not PNG bytes. No fake absolute screenshot links are recorded.

## Validation commands

```text
php -l includes/llm/brief-ir.php                         PASS
php -l includes/llm/design-plan.php                     PASS
php -l includes/elementor/elementor-ir.php              PASS
php -l includes/elementor/operation-ledger.php          PASS
php -l includes/llm/llm.php                              PASS
php -l wp-ai-executor.php                                PASS
php tests/design-pipeline-contract.php                   PASS (73 checks)
php tests/flex-generation-runtime.php                     PASS (331 checks)
node --test tests/*.test.js                              PASS (4 suites)
php docs/audits/2026-09-12/package-probe.php             PASS (90 files; mismatches=0)
git diff --check                                         PASS before commit
```

New/updated contract coverage includes structural URL boundaries, UTF-8/newline CTA association, explicit surface precedence, report scope/revision guard and v141 asset version. Live browser coverage includes revision restore, Elementor save/reload, selected patch, unsaved-neighbor preservation, cleanup, DOM copy/roots, mobile stack and overflow.

## Status matrix

| Evidence | Status |
|---|---|
| v141 source installed in WordPress | PASS |
| active/active routing without double write | PASS, harness + v140 live trace |
| exact hero copy and two CTA | PASS |
| hero root `a9282de` restored and saved | PASS |
| FAQ root `13568dc` preserved | PASS |
| pricing root `b898e72` preserved | PASS |
| explicit white pricing surface / beige hero surface | PASS |
| selected-scope native patch | PASS |
| unsaved-neighbor immediate preservation | PASS |
| unsaved-neighbor after Save/reload | PASS |
| cleanup leaves no test marker | PASS |
| DOM geometry desktop/tablet/mobile | PASS |
| whole-operation durable Vision report | BLOCKED (`wpae_vision_capture_failed`) |
| durable reviewed/completed promotion | PENDING/BLOCKED |
| inline CUA screenshots | PASS |
| filesystem PNG links | BLOCKED by documented CUA capability |
| provider timeout live mutation | NOT RUN |
| remote ref re-verification in this shell | BLOCKED by DNS |

## Commit, push and installation

- Runtime source commit: `d34e401e4c8c91581b14a9766a42f9e24faa87c3`; documentation commit: `905df45`.
- Push status: **PASS** recorded for `2702845..d34e401 main -> main`.
- Installation status: **PASS**, WordPress Plugins showed v02.11.141.
- Package/hash status: **PASS**, 90 files and no mismatches.
- После documentation commit tracked worktree clean; pre-existing untracked audit/history files не staged.

Исторические v126/v140 snapshots и старые handoff addenda заменены этим актуальным срезом; они не являются доказательством текущего live состояния.
