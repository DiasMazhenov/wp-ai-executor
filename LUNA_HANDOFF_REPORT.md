# WP AI Executor — актуальный handoff report

Дата и время фиксации: 2026-09-21 22:56:25 +05:00 (Asia/Almaty)
Репозиторий: /Users/diasmazhenov/vibecode/wp-ai-executor
Ветка: main
Целевая страница: WordPress post=5214 (Pricing Contract Live v123)
Новые pages, posts и drafts в этой сессии не создавались.

## Release evidence

- Исходный HEAD текущей серии: aa92c93.
- Runtime/source HEAD после исправления pricing: ad6bc5d326b78bfa74aef2f71212fbe5cdf19f53.
- Runtime version: v02.11.137.
- Live установленная версия: v02.11.137; в WP Plugins отображается активный WP AI Executor, версия подтверждена через WP Pusher после update из origin/main.
- Runtime push: PASS, origin/main содержит ad6bc5d.
- Documentation push: выполняется отдельным commit после фиксации этого документа.
- Исходные live flags: Design Decision Engine=active, Deterministic Design Pipeline=active.
- Итоговые live flags: те же значения; временных переключений, оставленных включёнными, не было.

## Архитектура и приоритет маршрутов

Фактическая active-схема записи:

~~~
prompt
  -> BriefIR v1
  -> typed DesignPlan v1
  -> WidgetCapabilityRegistry
  -> LayoutReport
  -> ElementorIR v2
  -> native Elementor compiler
  -> structural/semantic/layout validation
  -> существующий transaction/update/read-back boundary
  -> editor/public render
  -> Vision/design review
  -> bounded reconcile или patch
~~~

При одновременных active deterministic pipeline имеет приоритет над EDDE. EDDE остаётся typed hero slice и не запускается вторым competing compiler-ом. Legacy provider tree сохранён для совместимых legacy routes; отдельного write path не добавлялось.

| Pipeline | EDDE | Фактический action path | Provider calls | Page writes | Результат |
|---|---|---|---:|---:|---|
| off | off | legacy provider | 1 | 1 | PASS, contract harness |
| off | active | EDDE | 0 | 1 | PASS, contract/runtime harness |
| shadow | active | EDDE + shadow comparison | 0 | 1 | PASS, shadow не пишет второй раз |
| active | active | local deterministic pipeline | 0 | 1 | PASS, live v137 |

Live v137 diagnostics показали BriefIR → DesignPlan → registry → LayoutReport → ElementorIR → native compiler, один insert_elements write, operation_id=wpae-3b645647657433c0; provider JSON не был источником Elementor tree. Двойного запуска EDDE/pipeline и двойной записи не наблюдалось.

## Воспроизведённый дефект pricing

Старый live/editor результат v136 имел три карточки с native basis 33.333%, внутренней шириной группы 962px и двумя gap по 24px. Расчёт занимал 320.66 × 3 + 48 = 1010px, поэтому третья карточка переходила на следующую строку. Именно этот результат показан на пользовательском скриншоте.

Исправление:

- includes/elementor/elementor-ir.php: для роли pricing_cards с тремя детьми compiler резервирует gap-aware basis [31.5, 31.5, 31.5]; mobile policy остаётся 100% и column.
- tests/design-pipeline-contract.php: regression проверяет desktop basis >30 && <32 и mobile 100.
- wp-ai-executor.php: версия поднята до v02.11.137.
- tests/llm-chat-contract.test.js: проверка runtime version обновлена.
- wpae-package.json: SHA-256 manifest обновлён; stale hash не обнаружен.

Предыдущие v134–v136 fixes сохранили полную ширину intro/cards group и native row/wrap policy. Они не были заменены новым слоем.

## Operation ledger, idempotency и reconcile

Контрактные проверки текущего runtime покрывают:

| Сценарий | Результат |
|---|---|
| Повторная доставка одного запроса | PASS: существующая операция возвращается, duplicate root не создаётся |
| Два конкурентных захвата | PASS: atomic option lock отдаёт lock_conflict/unknown, журнал не теряется |
| Явная новая вставка с тем же brief | PASS: новая operation_identity допускает новую операцию |
| Timeout после фактической записи | PASS в read-back contract: сохранённый root находится до retry |
| Изменение target/page после операции | PASS: conflict/unknown, чужая запись не перезаписывается |
| Stale browser acknowledgement | PASS: identity/revision/root/hash проверки не завершают другую операцию |
| Недопустимый state transition | PASS: allowlist отклоняет переход |
| Provider timeout в live | NOT RUN как искусственный изменяющий сценарий |
| Unsaved user-authored neighbor probe | NOT RUN; соседние user roots не трогались |

operation-ledger.php использует lock-backed create/update; обычный read-then-update не объявляется атомарной защитой. reconcile сначала проверяет capability, post scope, operation identity, saved hash, target fingerprint, revision и root scope. Browser payload сам по себе не доказывает серверный read-back или Vision.

## Live operations на post=5214

### Existing hero A

- Root: a9282de.
- Сохранён после удаления старого pricing root; публичный DOM после последнего reload содержит этот root.
- Exact copy: Тихая форма, АРХИТЕКТУРА, Пространство для идей, Опишите задачу и получите понятный первый шаг.
- CTA: Начать проект → #contact, Смотреть проекты → #projects.
- Public root class: wpae-generated-hero.

### Removed broken pricing v136

- Operation: wpae-c8a717fb6f3eb12c.
- Root: cb8db62.
- Defect: three 33.333% cards wrapped at desktop.
- Reconcile before removal: saved write was retained as written/render-review-pending; Vision capture failed, automatic rollback did not execute.
- Root was removed through the existing Elementor UI only after explicit user confirmation; hero A was preserved and the page was saved before the new independent insert.

### Current independent pricing v137

Exact user request:

~~~
Добавь на текущую страницу новую pricing-секцию. Надзаголовок: «ТАРИФЫ». Заголовок: «Выберите формат работы». «Старт» — «от 50 000 ₸» — «Для небольшой задачи с понятным объёмом». Кнопка: «Выбрать Старт», ссылка #start. «Проект» — «от 150 000 ₸» — «Для комплексной работы от идеи до результата». Кнопка: «Обсудить проект», ссылка #project. «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач и развития проекта». Кнопка: «Подключить поддержку», ссылка #support. Сохрани существующие roots и не добавляй другие тексты.
~~~

- Operation: wpae-3b645647657433c0.
- Root after compiler/write/read-back: 39a8c89.
- Request type: a new independent insert after the v136 root had been removed and saved; this was not a retry of v136.
- Route: pipeline, local deterministic compiler; provider calls: 0; page writes: 1.
- Native widgets observed: container, text-editor, heading, button; no invented widget type.
- Exact CTA hrefs in editor and public DOM: #start, #project, #support.
- Vision UI result: score 85, confidence 95%; finding was minor card padding balance. This is browser evidence, not a server-side proof of completed.
- Durable state is not promoted to reviewed/completed by this report: the server-side rendered verifier/Vision report ID was not exposed after reload, so the honest state remains write/read-back confirmed with review pending.

## LayoutReport against live DOM

### Editor desktop after save/reload

- Viewport frame: 1010px root width; content width 962px.
- Root 39a8c89: x=0, y=406, w=1010, h=359.
- Intro group 38d6155: x=24, w=962, gap=24px.
- Cards group c0259fa: x=24, y=534, w=962, h=207, flex-direction=row, flex-wrap=wrap, gap=24px.
- Cards: 412afab, 987aec5, f0f8be5; each w=303.023px, x positions 24, 351.023, 678.047, same y 534; no wrap occurred.
- 303.023 × 3 + 24 × 2 = 957.07px <= 962px.

### Editor mobile after save/reload

- Browser editor mobile frame: innerWidth=360, scrollWidth=345.
- Root width 345px, direction column.
- Cards group c0259fa: x=24, y=732, w=297, h=581, direction column, gap 16px.
- Card positions: y=732, 931, 1130; each w=297, no horizontal overflow.
- Copy precedes media/remaining content according to mobile policy; CTA text remains usable.

### Public DOM after save/reload

- Public viewport observed: 1233×913; document scrollWidth=1233.
- Pricing root 39a8c89: x=0, y=374, w=1233, h=359, class wpae-generated-pricing.
- Cards group c0259fa: x=46.5, y=502, w=1140, h=207.
- Public cards: 412afab, 987aec5, f0f8be5; each w=359.09375, x=46.5, 429.59375, 812.6875, same y 502; no public overflow.

LayoutReport remains a preflight report. These DOM measurements are the separate live computed-geometry evidence; they do not claim that a PHP report alone proves visual acceptance.

## Neighboring content preservation

- No new WordPress page, post or draft was created.
- Hero A root a9282de and its exact links remained after removing cb8db62 and inserting v137 pricing.
- Public read-back contains exactly one generated hero root and one generated pricing root; .wpae-generated-pricing count is 1 with id 39a8c89.
- The requested unsaved-edit probe on a separate user-authored neighbor was not run. No evidence of a neighboring user root being modified was observed.

## Screenshot and render evidence

The current CUA screenshot documentation guarantees inline delivery, not a filesystem writer or artifact export for returned screenshot bytes. tab.content.export() is not a screenshot export in this environment, and no documented screenshot-file writer/artifact API is available. Therefore no fake filesystem links are recorded.

Fresh inline captures were made after v137 save/reload:

1. Editor desktop — post 5214, root 39a8c89, operation wpae-3b645647657433c0, viewport 1280×720, embedded Elementor editor. The image shows all three pricing cards in one row.
2. Editor mobile — post 5214, root 39a8c89, operation wpae-3b645647657433c0, Elementor mobile mode (innerWidth=360, frame 1280×720), two inline frames cover the title and lower stacked cards.
3. Public desktop — post 5214, root 39a8c89, operation wpae-3b645647657433c0, public URL https://mazhenov.kz/pricing-contract-live-v123/?wpae_check=hero-b-132-2039, viewport 1233×913; fresh CUA screenshot shows the corrected one-row pricing block and hero A.

Screenshot file gallery status: SCREENSHOT FILE BLOCKED. Inline screenshots were captured and displayed; an absolute PNG path cannot be truthfully supplied because this CUA session exposes no documented writer/artifact export for the returned bytes. Public inline capture itself succeeded.

## Validation commands

All commands below passed against runtime commit ad6bc5d:

~~~
php -l wp-ai-executor.php                         PASS
php -l includes/elementor/elementor-ir.php       PASS
php tests/design-pipeline-contract.php            PASS (63 checks)
node --test tests/*.test.js                       PASS (4 suites; 331 flex runtime checks)
php docs/audits/2026-09-12/package-probe.php     PASS (90 files; mismatches=0)
git diff --check                                  PASS
~~~

## Status matrix

| Evidence | Status |
|---|---|
| Source fix and regression | PASS |
| Package/hash validation | PASS |
| Push runtime ad6bc5d | PASS |
| WP Pusher install/activation v137 | PASS |
| Active/active routing without double write | PASS |
| Exact copy and CTA links | PASS |
| Saved Elementor read-back root 39a8c89 | PASS |
| Editor desktop geometry | PASS |
| Editor mobile stack/overflow | PASS |
| Public DOM geometry | PASS |
| Fresh inline screenshots | PASS |
| PNG filesystem links | BLOCKED by CUA capability |
| Server-side Vision reviewed/completed promotion | BLOCKED/PENDING; Vision UI score exists, verifier/report ID not confirmed after reload |
| Unsaved user-authored neighbor probe | NOT RUN |
| Artificial provider timeout live scenario | NOT RUN |

## Commit and installation status

- Runtime commit: ad6bc5d326b78bfa74aef2f71212fbe5cdf19f53 (Keep pricing cards on one desktop row).
- Runtime push: PASS to origin/main.
- Live install: PASS, WP Pusher update from DiasMazhenov/wp-ai-executor, branch main; active plugin version v02.11.137.
- Report and canonical context.md: updated in the documentation commit that follows the runtime release.

История предыдущих handoff-срезов сокращена; факты этого документа относятся к текущей source/live проверке и имеют приоритет над историческими snapshots.
