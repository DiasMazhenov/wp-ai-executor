# WP AI Executor — актуальный отчёт стабилизации deterministic design pipeline

Дата фиксации: 2026-09-21 18:54:03 +05:00 (Asia/Almaty)
Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Ветка: `main`
Целевая WordPress-страница: `post=5214`
Assignment baseline: `d69d734`
Рабочий source перед этой live-сессией: `df3ac87`
Итоговый runtime source commit: `a55824d32b8815b7d39219e2cd91347dff728281`
Source/live version: `v02.11.128`
Runtime push: PASS, `origin/main`
Runtime install: PASS, WP Pusher и Plugins page показали `WP AI Executor v02.11.128`
Документационный commit: будет указан после фиксации этого файла.

## Итоговый статус

Работа выполнена на одной существующей странице `post=5214`. Новые WordPress pages, posts и drafts не создавались. Соседний pricing-контент на публичной странице сохранился.

Итоговый deterministic pipeline проверен на независимой второй вставке: exact copy, две CTA, native widgets, 40/60 desktop composition, tablet 50/50, mobile 100/100 copy-first, saved root и публичный DOM подтверждены. Ledger для этой операции остался в честном состоянии `written/render_review_pending`, потому что серверный rendered HTML hash и сохранённый screenshot-файл отсутствуют.

Полная файловая screenshot-приёмка не завершена. Browser API возвращает screenshot bytes для inline-показа, но документированного writer/artifact API для этих bytes в текущем Codex In-app Browser нет. Поэтому в этом документе нет выдуманных ссылок на png-файлы; статус галереи оставлен `SCREENSHOT BLOCKED`.

## Фактическая архитектура и приоритет маршрутов

Текущий поток:

```
prompt
  -> BriefIR v1
  -> typed DesignPlan v1
  -> WidgetCapabilityRegistry/LayoutReport
  -> ElementorIR v2
  -> native Elementor compiler
  -> structural/semantic/layout validation
  -> existing transaction + update/read-back boundary
  -> editor/public render
  -> Vision/design review
  -> bounded reconcile or patch
```

Модель не формирует произвольное Elementor tree в active deterministic режиме. Provider-generated tree остаётся legacy adapter-ом для режимов, где deterministic pipeline не выбран. Альтернативного write path нет.

Feature flags на начало и конец live-сессии: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.

| Pipeline | EDDE | Фактический action path | Provider calls | Writes | Проверка |
|---|---|---|---:|---:|---|
| off | off | `provider` | 1 | 1 | PASS, локальный route contract |
| off | active | `edde` | 0 | 1 | PASS, локальный route/runtime contract |
| shadow | active | `edde` с `shadow_only=true` | 0 | 1 | PASS, shadow compiler не пишет |
| active | active | `pipeline`, precedence=pipeline | 0 | 1 | PASS, обе live active вставки |

При `active/active` один запрос не запускал EDDE и pipeline последовательно: routing выбрал pipeline до compiler, diagnostics показали `action_path=pipeline`, `route=local_deterministic`, `provider_calls=0`. Двойной compiler и двойная запись не наблюдались.

## Изменения в source

### v02.11.128

Изменены:

- `includes/llm/brief-ir.php` — распознавание семантических ограничений «текст слева 40%, визуальная часть справа 60%» с provenance; результатом становится `split_40_60`.
- `includes/llm/decision-engine.php` — EDDE применяет те же explicit side-percentage constraints.
- `includes/elementor/elementor-ir.php` — brand сохраняется отдельным native heading; порядок copy group нормализован как brand → eyebrow → title → body → CTA; brand/eyebrow получают h6 и semantic muted token.
- `tests/design-pipeline-contract.php` — exact live brief regression: split 40/60, widget order, native CTA links.
- `tests/llm-chat-contract.test.js` — ожидаемая версия `v02.11.128`.
- `wp-ai-executor.php` — plugin header и runtime version.
- `wpae-package.json` — SHA-256 обновлены после runtime-изменений; проверка ниже дала 90 файлов без mismatches.

### Ранее введённые и проверенные границы

Исторические изменения EDDE, BriefIR/DesignPlan/ElementorIR, LayoutReport, token/reference validation, operation ledger, reconcile endpoint и routing policy были сохранены и проверены. В частности:

- operation ledger использует bounded option store и атомарный `add_option` lock;
- idempotency key включает `post_id + brief_hash + selected_scope + operation_type + operation_identity`;
- допустимые переходы состояния проверяются allowlist-ом;
- reconcile проверяет post capability, operation identity, revision, saved hash, root ownership, target fingerprint, evidence source и Vision report;
- только существующий transaction/update/read-back boundary пишет Elementor data;
- routing diagnostics отличает `null` неизвестной transport-метрики от измеренного нуля.

Новые архетипы, marketplace, новая token system и отдельный write path не добавлялись.

## Idempotency, concurrency и reconcile

Локальный contract harness воспроизвёл:

| Сценарий | Результат |
|---|---|
| Повтор доставки того же idempotency key | PASS: возвращается существующая операция, новый root не создаётся |
| Конкурентный захват | PASS: второй захват получает `lock_conflict/unknown`; option lock не теряет журнал |
| Новая явная вставка с тем же brief | PASS: новый `operation_identity` создаёт новую операцию и новый deterministic root |
| Недопустимый переход состояния | PASS: переход отклонён, журнал помечает `invalid_state_transition` |
| Browser rendered/reviewed без server verification | PASS: reconcile оставляет `written` |
| Старый unknown после более нового written | PASS: monotonic reconcile не деградирует состояние |

Endpoint `POST /wp-json/ai-executor/v1/llm/operation-reconcile` ограничивает JSON payload 32768 bytes. Browser evidence не подтверждает read-back сама по себе. Для `rendered/reviewed/completed` сервер перечитывает Elementor data; для `reviewed/completed` дополнительно требуется Vision report с тем же post. Устаревший `operation_identity` или revision отклоняется с 409. Несовпадение saved hash, target fingerprint или root scope отклоняется с 409. Повторное подтверждение с тем же evidence идемпотентно.

Живой ledger:

| Operation | Root | Состояние | Доказательство |
|---|---|---|---|
| `wpae-20260921110642-50ed0529` | `4979256` | `written/render_review_pending` | состояние, переданное на вход этапа |
| `wpae-c237dc2ec56bf748` | `1d846cc` | `written/render_review_pending` | v127 active generation, до v128 fix |
| `wpae-20260921134608-904e4277` | selected patch | не найден в ledger | UI ответил «Ledger reconcile требует read-back: Операция не найдена» |
| `wpae-32d5d4d8c09fdd16` | `20e991c` | `written/render_review_pending` | v128 независимая active insertion |

Patch operation не была выдана за retry или за независимую вставку. Она не изменила ledger другой операции.

## LayoutReport и фактический DOM

LayoutReport v1 для v128:

| Breakpoint | Container width | Used width | Mobile stack | Violations |
|---|---:|---:|---|---|
| desktop | 1200 | 1168 | n/a | [] |
| laptop | 960 | 928 | n/a | [] |
| tablet | 704 | 672 | n/a | [] |
| mobile | 326 | 326 | `copy_first_stack` | [] |

Compiler native settings для root `20e991c`:

- desktop row: copy basis 40%, media basis 60%;
- tablet: copy 50%, media 50%;
- mobile: both 100%, direction column;
- outer padding 1.5rem;
- desktop gap 1.5rem, mobile gap 1rem;
- media fallback has solid `#d1d5db` border and stable 12rem minimum height;
- no fixed text height, no zero-width child.

Public DOM read-back after reload was captured at the actual browser viewport 891×913 CSS px, DPR 2. Root `20e991c` had background `rgb(246, 240, 230)`, outer rect x=0, y=1002.77, width=876, height=382; inner rect x=24, width=828. At this tablet-width viewport the two direct children were row-aligned: copy width 400.45 and media width 403.55 with a 24px gap. Their widths plus gap equal the available inner width; no horizontal overflow or zero-width visible child was observed. This matches the declared tablet 50/50 policy rather than incorrectly applying desktop 40/60 to the cross-axis.

Elementor responsive mode was explicitly switched to «Мобильный - книжная ориентация (до 767px)». AX read-back showed the v128 order `Тихая форма → АРХИТЕКТУРА → Пространство для идей → описание → Начать проект → Смотреть проекты`, with copy before media. The generated JSON declares mobile 100/100 and column direction.

The IAB viewport capability was documented and invoked for 390px, but this existing tab continued to report 891×913; therefore public computed DOM at exactly 390, 768, 1024 and 1440 was not falsely claimed. The 390 public-DOM comparison is BLOCKED by the browser backend viewport override; the editor mobile mode and compiler policy remain directly observed. LayoutReport is still a preliminary invariant check, not a replacement for computed CSS or screenshots.

## Semantic contrast and routing diagnostics

v128 diagnostics:

- text/page background ratio: 15.649, minimum 4.5;
- muted/page background ratio: 6.667, minimum 4.5;
- CTA surface/primary ratio: 5.085, minimum 3 for UI object;
- small muted text receives the shared `color.muted` contrast-safe fallback; role name alone cannot bypass the size threshold;
- missing project token warnings remain explicit.

For the local deterministic route, provider metadata was not fabricated: `source=not_called`, `provider_calls=0`, `metrics_known.*=false`, token/latency/cost/success fields are `null`, not measured zeros or synthetic success.

## Live generation evidence

Editor: `https://mazhenov.kz/wp-admin/post.php?post=5214&action=elementor`
Public source: `https://mazhenov.kz/pricing-contract-live-v123/`

Exact prompt used for both insert attempts:

```
Добавь новую hero-секцию для архитектурной студии «Тихая форма».
Надзаголовок «АРХИТЕКТУРА».
Заголовок «Пространство для идей».
Описание «Опишите задачу и получите понятный первый шаг».
Основная кнопка «Начать проект», ссылка #contact.
Вторичная кнопка «Смотреть проекты», ссылка #projects.
Текст слева занимает 40%, визуальная часть справа — 60%.
Тонкая рамка визуальной зоны, просторные отступы, фон #F6F0E6.
На мобильном сначала текст, затем визуальная часть.
Существующие блоки не изменяй.
```

### Первый результат до v128

Operation `wpae-c237dc2ec56bf748`, root `1d846cc`, source v02.11.127:

- route diagnostics: `action_path=pipeline`, `route=local_deterministic`, `provider_calls=0`;
- saved operation state: `written/render_review_pending`;
- generated copy placed title before eyebrow and used the opposite 60/40 semantic order;
- Vision score 90, confidence 95%, finding: eyebrow hierarchy;
- selected-root patch `wpae-20260921134608-904e4277` changed only bounded selected elements; it did not create a second independent root and did not repair the node order;
- patch reconcile was rejected because that UI operation was absent from the durable ledger.

Status: FAIL/partial for the requested semantic ordering. The root was retained to avoid deleting user/page content.

### Второй результат после v128

Operation `wpae-32d5d4d8c09fdd16`, operation identity `e74c5a87-43c4-44c3-a080-4529a6808e51`, idempotency key `15d9a13dcbf69af38c6853dbfb5a01cf5922a60aaeb01a0a0ca9b38604cf21d5`, root `20e991c`:

- route: `pipeline`, precedence `pipeline`, `local_deterministic`, provider calls 0, one write;
- BriefIR hash `eced777cf49b3219ecceaeef43b55756e42d0209049888e7360519f4436eb1b`;
- DesignPlan hash `f2722ad3d29e1d10e840a289eaec8da2c2a6b8517d6d61de77ae218afef18d85`;
- compiled hash `5c11246aa98cbe75491363947e81c426bf4cd49af5a4654d1fd06e0f35d8734c`;
- saved hash `64c440a0763c45c773da2f53ac1afffd257a6c7de793dd2de406395175d7ce6a`;
- target fingerprint `468dd0498a4d4a699a69052a8fcb79c9994778ffe439e84dde7c2cd2b73a2a38`;
- revision 4, current state `written`, `rendered_html_hash=""`, `render_review_pending=true`;
- native widgets: container, heading, text-editor, button; no unavailable widget downgrade;
- exact visible copy and links were present in editor and public AX:
  - brand `Тихая форма`;
  - eyebrow `АРХИТЕКТУРА`;
  - title `Пространство для идей`;
  - body `Опишите задачу и получите понятный первый шаг`;
  - `Начать проект` → `#contact`;
  - `Смотреть проекты` → `#projects`;
- Vision: score 90, confidence 95%; minor finding was additional vertical padding, not a content or layout failure.

Status: PASS for deterministic routing, content fidelity, native structure, saved root and observed DOM; BLOCKED for file-based screenshot gallery and server-completed rendered/reviewed state.

Pricing preservation: public AX after reload still contained `ТАРИФЫ`, `Выберите формат работы`, `Старт`, `от 50 000 ₸`, `Проект`, `от 150 000 ₸`, `Поддержка`, `от 80 000 ₸/мес` and the existing `#start`, `#project`, `#support` links. No neighboring root was removed. A separate manual unsaved-edit-before-AI-operation scenario was not run; no loss of existing pricing content was observed.

## Screenshot gallery

The documented screenshot path was checked before the long live test:

- `tab.getScreenshot()` returns screenshot bytes and supports inline display;
- `tab.content.export()` returns exactly: `Codex in-app browser does not support command "tab_content_export"`;
- `pageAssets` exports observed page assets only (image/font/style/video), not screenshot bytes;
- no documented screenshot bytes-to-file writer or screenshot artifact method is available in this browser session.

Inline public desktop and Elementor mobile frames were captured and visually inspected during this run. They cannot be addressed by an absolute filesystem path. Required gallery status:

- Hero A (v127 failed semantic result) — desktop: **SCREENSHOT BLOCKED**; mobile: **SCREENSHOT BLOCKED**.
- Hero B (v128 root `20e991c`) — desktop, public preview, actual viewport 891×913 CSS px: **SCREENSHOT BLOCKED**; mobile, Elementor responsive mode ≤767px: **SCREENSHOT BLOCKED**.

No generated image, alternate renderer, stale inline capture, or single tab ID is being presented as a saved screenshot artifact.

## Validation and release evidence

Commands and results:

```
php -l includes/llm/brief-ir.php                         PASS
php -l includes/llm/decision-engine.php                 PASS
php -l includes/elementor/elementor-ir.php              PASS
php -l wp-ai-executor.php                                PASS
php tests/design-pipeline-contract.php                   PASS — 52 checks OK
php tests/flex-generation-runtime.php                    PASS — 331 checks OK
node --test tests/*.test.js                              PASS — 4 suites, 0 failures
manifest SHA-256 verification                            PASS — files=90 mismatches=0
git diff --check                                          PASS
```

Runtime release `a55824d` was pushed to `origin/main` and installed on the existing site through WP Pusher. The plugin page and the same Elementor editor reported `v02.11.128`. Source release, push, install and live evidence are separate facts; screenshot-file and completed-render gates remain blocked as stated above.

## Matrix of acceptance

| Area | Status | Evidence |
|---|---|---|
| BriefIR exact text/provenance/URLs | PASS | 52 contract checks and live v128 diagnostics |
| DesignPlan/ElementorIR/native compiler | PASS | v128 root, native widget list and saved JSON |
| Feature flag precedence | PASS | four-route matrix and live active/active route |
| Idempotency/concurrency/state transitions | PASS | operation ledger contract checks |
| Timeout/read-back duplicate protection | PASS (contract) / NOT RUN (forced live timeout) | reconcile guards and monotonic state tests |
| Target conflict/stale acknowledgement | PASS (contract) / NOT RUN (forced live conflict) | endpoint checks and stale revision tests |
| Contrast/unknown telemetry handling | PASS | ratios and `metrics_known` diagnostics |
| LayoutReport mobile-axis semantics | PASS | regression plus v128 40/50/100 native settings |
| Live v127 hero semantic result | FAIL/partial | root `1d846cc`, order/ratio mismatch |
| Live v128 independent hero insert | PASS for source/DOM; BLOCKED for screenshot/completed gate | root `20e991c`, public AX, Vision 90 |
| Public pricing preservation | PASS | public AX after reload |
| Desktop/mobile filesystem screenshots | BLOCKED | documented browser limitation |
| Manual unsaved neighboring edit scenario | NOT RUN | no controlled unsaved edit was introduced |
| New page/draft creation | PASS | none created |
