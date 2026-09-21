# WP AI Executor — актуальный отчёт стабилизации deterministic design pipeline

Дата фиксации: 2026-09-21 20:52:00 +05:00 (Asia/Almaty)
Репозиторий: /Users/diasmazhenov/vibecode/wp-ai-executor
Ветка: main
Целевая WordPress-страница: post=5214
Новые WordPress pages, posts и drafts не создавались.

## Версии и release evidence

- Исходный HEAD этой сессии: bfdfc90038a48ca9b01c758d154027a99a46c0ac.
- Итоговый source/runtime HEAD до документационного commit: a9d92d67379ae4c2a92b7f005a09694bc6bea478.
- Runtime/source version: v02.11.132.
- Установленная live version: v02.11.132, подтверждена в WP Pusher и в Elementor editor.
- Runtime commits этой серии: 3ee6261, 6ea8e41, 62c10ef, a9d92d6.
- Feature flags до и после live-сессии: Design Decision Engine=active, Deterministic Design Pipeline=active.
- Push runtime: PASS, origin/main содержит source commit.
- Push документации: фиксируется отдельным commit после этого отчёта.
- Install: PASS, WP Pusher установил v02.11.132.

## Фактическая архитектура и routing priority

Текущий write pipeline:

~~~
prompt
  -> BriefIR v1
  -> typed DesignPlan v1
  -> WidgetCapabilityRegistry
  -> LayoutReport
  -> ElementorIR v2
  -> native Elementor compiler
  -> structural/semantic/layout validation
  -> существующий transaction + update/read-back boundary
  -> editor/public render
  -> Vision/design review
  -> bounded reconcile or patch
~~~

В active deterministic режиме модель принимает ограниченные design decisions; произвольный provider-generated Elementor tree не является источником записи. Legacy provider path сохранён для режимов, в которых deterministic pipeline не выбран. Альтернативного write path нет.

| Deterministic pipeline | EDDE | Выбранный action path | Provider calls | Записи страницы | Факт |
|---|---|---|---:|---:|---|
| off | off | provider | 1 | 1 | PASS, contract harness |
| off | active | edde | 0 | 1 | PASS, contract/runtime harness |
| shadow | active | edde; shadow compiler только сравнивает | 0 | 1 | PASS, страница изменяется один раз |
| active | active | pipeline, precedence=pipeline | 0 | 1 | PASS, обе live-вставки |

В live active/active один запрос не запускал EDDE и pipeline последовательно: diagnostics показали action_path=pipeline, route=local_deterministic, provider_calls=0. Двойного compiler и двойной записи не наблюдалось.

## Воспроизведённые дефекты и изменения

### Native responsive width

Причина: compiler применял общий flex_basis к widget nodes. Elementor трактовал это как невалидную для виджета width-настройку, поэтому LayoutReport и native output расходились.

Исправление в includes/elementor/elementor-ir.php:

- responsive width settings назначаются только child containers;
- widget nodes получают native widget settings без generic flex_basis;
- сохранены width, width_tablet, width_mobile и совместимые native aliases для container nodes;
- regression обновлён в tests/design-pipeline-contract.php.

### Operation ledger

В includes/elementor/operation-ledger.php и callers сохранены:

- idempotency key на post_id + brief_hash + selected_scope + operation_type + operation_identity;
- атомарный option-lock через add_option;
- allowlist допустимых state transitions;
- monotonic reconcile, который не деградирует новую операцию старым acknowledgement;
- ownership, post capability, revision, saved hash, target fingerprint и root scope checks.

Обычный read-then-update не используется как атомарная защита.

### Reconcile и evidence

operation-reconcile принимает ограниченный payload и не доверяет произвольному completed:

- проверяются авторизация и конкретный post;
- operation identity и revision связываются с target roots;
- server read-back выполняется до rendered/reviewed/completed;
- browser evidence само по себе не доказывает saved data или Vision;
- повторное подтверждение с тем же evidence идемпотентно;
- stale identity/revision, saved hash, target fingerprint и root mismatch отклоняются.

Состояния остаются различными: written, rendered, reviewed, completed, failed, unknown.

### Contrast и diagnostics

Общий validator применяет пороги к фактическому размеру текста и UI-роли. Маленькая muted-подпись не проходит только по имени роли: включается small_text_contrast fallback.

Routing trace больше не превращает неизвестные transport values в измеренный ноль или синтетический success:

~~~
provider_calls=0
source=not_called
metrics_known.input_tokens=false
metrics_known.output_tokens=false
metrics_known.latency_ms=false
metrics_known.retry_count=false
metrics_known.estimated_cost=false
metrics_known.success=false
input_tokens=null
output_tokens=null
latency_ms=null
retry_count=null
estimated_cost=null
success=null
~~~

## Idempotency, concurrency и reconcile checks

| Сценарий | Результат |
|---|---|
| Повторная доставка того же запроса | PASS: возвращается существующая операция, новый root не создаётся |
| Два конкурентных захвата | PASS: второй получает lock_conflict/unknown, журнал не теряется |
| Явная новая вставка с тем же brief | PASS: новый operation_identity допускает новую операцию и root |
| Недопустимый переход состояния | PASS: отклоняется allowlist-ом |
| Timeout после фактической записи | PASS в contract/read-back checks: saved result находится без duplicate retry |
| Stale rendered/reviewed acknowledgement | PASS в contract checks: старое сообщение не завершает новую операцию |
| Target/page conflict | PASS в contract checks: conflict/unknown без перезаписи чужого состояния |
| Forced live provider timeout | NOT RUN как искусственный destructive live сценарий |
| Controlled live unsaved-neighbor edit | NOT RUN |

## LayoutReport против DOM

Проверенная native policy:

- desktop: copy 40%, media 60%, row;
- tablet: copy 50%, media 50%, row;
- mobile: оба child 100%, column, copy first;
- desktop gap 24px, mobile gap 16px;
- text blocks без fixed height;
- media fallback с border и стабильным aspect/height;
- zero-width visible child не допускается.

LayoutReport остаётся предварительной проверкой и не заменяет computed CSS или screenshot.

### Live A geometry

Editor root: a9282de.

- Desktop editor frame: copy 2b2dc24 — x=24, width=375.140625; media b01a77c — x=423.140625, width=562.859375; gap=24.
- Tablet editor frame: copy width=340.3984375; media width=340.6015625; x media=388.3984375; gap=24.
- Mobile editor frame: innerWidth=360, scrollWidth=345, direction=column, root width=345; copy x=24, y=24, width=297, height=318; media x=24, y=358, width=297, height=198. Horizontal overflow не наблюдался.
- Public DOM: viewport 1233×913 CSS px, document scrollWidth=1233; root a9282de x=0, y=32, width=1233, height=342; public copy/media widths 446.3515625/669.6484375 с 24px gap, что даёт 40/60.
- Public exact links: Начать проект -> #contact, Смотреть проекты -> #projects.

## Live generation A

Точный prompt для A:

~~~
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
~~~

Идентичность:

- operation: wpae-c8d8403f5579e2a3;
- operation identity: b7932c34-b562-4f2d-8c6c-7e57ac146731;
- idempotency key: 844aca06fa8488399c64d293b55e92b9fed2a22418bed5c7ddf6a08d3d08c523;
- root: a9282de;
- brief hash: 61ebd8529914fa0d802fefd6330f8b16be3bd0452de957c32314734b35c3ace7;
- plan hash: 685a0f67bca3f9daabd8842ff8044e338b92a350b417bfcf9ac9c45ea748c572;
- compiled hash: 5a1e3905cb128b086981038e1a19bfa68d13141b579f96da4dd1fde2fde0e490;
- saved hash: fd0071f56e25dc102b7774177307c261e4a4f91cb9c66925ab8b661f9d61ace5;
- target fingerprint: b4e2df1146f7938cf0378eb632e0cbea36dad27de01ad80c5d21b12a80217b04;
- route: pipeline, local_deterministic;
- provider calls: 0;
- writes: 1;
- current durable state: written, render_review_pending=true;
- rendered_html_hash и vision_report_id в ledger пусты.

Проверены exact copy и native widgets: container, heading, text-editor, button. В editor и public DOM присутствуют brand Тихая форма, eyebrow АРХИТЕКТУРА, title, body и обе CTA. Explicit image отсутствует, поэтому diagnostics честно сообщает media fallback и missing-media warning.

Свежий Vision в editor UI: score 85, confidence 95%. Vision подтвердил copy, 40/60, #F6F0E6 и border; единственное minor замечание — обе кнопки используют одинаковый bright-blue fill. Это UI evidence, но не заменяет server-side reviewed/completed transition: durable ledger остался written/render_review_pending.

## Live generation B

B запускал тот же prompt как новый пользовательский запрос, не retry A.

Идентичность:

- operation: wpae-dff2acb7714d94ff;
- operation identity: 6ba18eb7-1c1a-47f9-9246-191ee48c5fc0;
- idempotency key: bcb5fe65d39e75f585decdcc9be35638d9251cd3c7e346a317136942139c5bee;
- emitted root: bb838fb;
- brief hash совпал с A, operation identity отличался;
- route: pipeline, local_deterministic, provider calls 0;
- initial UI trace показывал one write и state written/render_review_pending.

Vision B дал score 85/confidence 95%, но нашёл major defect: right-hand visual container был пустым border placeholder. Repair loop получил provider timeout cURL error 28; deterministic fallback затем не прошёл content fidelity (Тихая форма отсутствовала), поэтому bounded write остановился с ошибкой.

После full editor reload и public reload:

- public DOM не содержит bb838fb;
- public содержит только accepted A root a9282de;
- свежий editor сначала показывал B только из текущей Elementor autosave/editor model;
- B был удалён штатным Elementor action из editor model;
- A сохранён через существующий Elementor Update;
- после следующего save/reload editor и public содержат только a9282de.

B не является принятым дизайном и не получил screenshot gallery entry. Его старый UI ledger payload наблюдался как written/render_review_pending при отсутствии B в saved public read-back; это остаётся зафиксированным reconcile discrepancy, а не успешным completed state.

## Сохранность соседнего контента

Новые roots добавлялись только на существующий post=5214; новых страниц и drafts не было. После финального save/reload public DOM содержит A и существующие элементы формы; bb838fb отсутствует.

Контролируемая проверка с несохранённой пользовательской правкой соседнего блока до AI-операции и после save/reload не выполнялась. Pricing preservation в этой финальной live-сессии не объявляется PASS: в итоговом public readback не были обнаружены прежние pricing labels, поэтому доказательство сохранности конкретного pricing блока отсутствует.

## Screenshot evidence

Проверенный штатный путь:

- editorTab.getScreenshot() возвращает screenshot bytes и позволяет показать их inline;
- desktop A snapshot показан после save/reload в Elementor editor;
- mobile A snapshot показан в Elementor responsive mode, innerWidth=360;
- public getScreenshot() завершился Unable to capture screenshot;
- tab.content.export() возвращает Codex in-app browser does not support command "tab_content_export";
- pageAssets содержит только наблюдаемые page assets, не screenshot bytes;
- документированного writer/artifact API для записи этих bytes в файл в текущей browser session нет;
- OS-level capture снимал foreground Chrome, а не embedded IAB; data-URL workaround запрещён средой.

Галерея: SCREENSHOT BLOCKED. Inline desktop/mobile evidence было визуально проверено, но абсолютные filesystem links не создавались и не выдумывались.

## Долговечный ledger после live-операций

| Operation | Root | Наблюдаемое состояние | Факт |
|---|---|---|---|
| wpae-c8d8403f5579e2a3 | a9282de | written/render_review_pending | saved hash и public DOM подтверждены; rendered/vision fields пусты |
| wpae-dff2acb7714d94ff | bb838fb | UI payload written/render_review_pending | public read-back отсутствует после repair rejection; stale ledger discrepancy |
| wpae-89007d964d7436ab | 1fa90e6 | rolled back | rollback подтверждён public read-back |
| wpae-32d5d4d8c09fdd16 | 20e991c | historical pre-v132 | не используется как evidence текущего A/B |

## Acceptance matrix

| Проверка | Status | Evidence |
|---|---|---|
| BriefIR exact text/provenance/URLs | PASS | contract harness и live A diagnostics |
| DesignPlan/ElementorIR/native compiler | PASS | root A, native widgets, saved hash |
| Feature flag precedence | PASS | four-state routing matrix |
| Idempotency/concurrency/state transitions | PASS | operation-ledger runtime checks |
| Timeout/read-back duplicate protection | PASS (contract) / NOT RUN forced live timeout | reconcile tests |
| Stale acknowledgement | PASS (contract) | identity/revision monotonic checks |
| Target conflict protection | PASS (contract) / NOT RUN forced live conflict | fingerprint/read-back checks |
| LayoutReport mobile-axis semantics | PASS | regression + editor/public geometry |
| Contrast and unknown telemetry | PASS | ratios and null/metrics_known trace |
| Live A exact copy/native widgets/40-60 | PASS | operation A, editor/public DOM |
| Live A durable completed/reviewed state | BLOCKED | ledger remains written/render_review_pending |
| Live B independent identity | PASS | distinct operation/identity/idempotency |
| Live B accepted design | FAIL | Vision major + repair content-fidelity rejection |
| B duplicate after save/reload | PASS | public/editor no bb838fb |
| Pricing preservation | NOT RUN/UNPROVEN | required neighboring edit probe not executed |
| Desktop/mobile filesystem screenshots | BLOCKED | browser writer/artifact limitation |
| New page/draft creation | PASS | none created |

## Verification commands

~~~
php -l includes/llm/brief-ir.php
php -l includes/llm/decision-engine.php
php -l includes/elementor/elementor-ir.php
php -l includes/elementor/layout-report.php
php -l includes/elementor/operation-ledger.php
php -l wp-ai-executor.php
php tests/design-pipeline-contract.php
php tests/flex-generation-runtime.php
node --test tests/*.test.js
php docs/audits/2026-09-12/package-probe.php
git diff --check
git status --short --untracked-files=no
~~~

Итог локальных проверок:

- PHP syntax: PASS для изменённых runtime files;
- design-pipeline contract: PASS, 55 checks;
- flex-generation runtime: PASS, 331 checks;
- Node: PASS, 4 suites, 0 failures;
- package/hash probe: PASS, 90 files, 0 mismatches, valid ZIP;
- git diff --check: PASS;
- untracked files не добавлялись в commit.

## Остаточные наблюдаемые ограничения

- Server-side rendered HTML hash и durable Vision report для A не записаны, поэтому completed не утверждается.
- Public screenshot capture и filesystem persistence для inline bytes недоступны в текущем IAB; статус screenshot gate BLOCKED.
- B stale ledger payload показал, что после repair rejection операция может остаться written/render_review_pending, хотя saved public read-back уже не содержит root.
- Public viewport backend не позволил отдельно получить computed DOM именно на 390, 768, 1024 и 1440 CSS px; editor desktop/tablet/mobile режимы и actual public viewport 1233×913 были проверены.
- gtag is not defined и Angie is not available наблюдались как внешние editor/browser warnings.
- Pricing unsaved-neighbor probe не проводился.
