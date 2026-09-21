# WP AI Executor — полный отчёт о введении архитектурных изменений

Дата фиксации: 2026-09-21, часовой пояс Asia/Almaty
Репозиторий: /Users/diasmazhenov/vibecode/wp-ai-executor
Ветка: main
Целевая WordPress-страница: post=5214
Источник и live: v02.11.124
Итоговый commit: 76829f92c9120bcd634f439dc16bf9910ca381bb (76829f9)
Push: origin/main, 2026-09-21 14:58:04 +05

## 1. Границы работы и итоговый статус

Вся проверка выполнена на одной существующей странице Elementor post=5214. Новые WordPress-страницы, записи и черновики не создавались. Существующие hero и pricing сохранены.

Исходной точкой этой серии был commit 500ec909ec29022dc243bf6991b5f80a5882e5a9 (Clarify 60/40 acceptance boundary). К моменту передачи:

- исходный код собран в v02.11.124;
- WP Pusher сообщил Plugin was successfully updated.;
- страница плагинов показала активный WP AI Executor v02.11.124;
- после перезагрузки Elementor показал Модель: openrouter/free · Версия: v02.11.124;
- live acceptance выполнена на post=5214 для первого hero, pricing и второго hero;
- public saved DOM после перезагрузки содержит текущие roots без raw prompt в опубликованном содержимом;
- старые raw-prompt nodes остаются только в локальном iframe DOM Elementor как исторический overlay.

## 2. Введённая архитектура EDDE

EDDE (Elementor Design Decision Engine) оставлен узким вертикальным срезом только для hero-блоков. Он не заменяет общий pipeline записи Elementor и не получает право писать _elementor_data напрямую.

Фактический поток:

~~~mermaid
flowchart TD
    A[Prompt в Elementor chat] --> B[Archetype и content plan]
    B --> C[EDDE state wpae-edde-state-v1]
    C --> D[LLM plan wpae-edde-plan-v1]
    D --> E[Enum decode и explicit constraints]
    E --> F[Native hero compiler]
    F --> G[Content, shape и design-system validation]
    G --> H[Общий transaction/write/read-back boundary]
    H --> I[Editor refresh и operation diagnostics]
~~~

wpae-edde-plan-v1 ограничен типизированными enum-полями:

| Поле | Разрешённые значения |
|---|---|
| schema | wpae-edde-plan-v1 |
| archetype | hero |
| composition | split_60_40, split_50_50, split_40_60, stacked_left |
| content_alignment | left, center, right |
| vertical_alignment | start, center |
| spacing_rhythm | compact, balanced, spacious |
| surface | minimal, soft_panel, outlined |
| typography | display, neutral |
| cta_hierarchy | single_primary, primary_secondary |
| responsive_strategy | copy_first_stack |

В decision state передаются content slots, CTA-пары, explicit constraints, design-system identifiers, editor context и provider/model. Произвольный Elementor JSON, секреты и прямой meta write в EDDE не входят.

## 3. Исправления, вошедшие в v02.11.124

### Сохранение ограничений при Vision regeneration

Ранее Vision regeneration мог перейти на общий fallback с фиксированным соотношением 58/38. Это стирало пользовательские требования 40/60 или 60/40, выравнивание, surface, типографику, CTA hierarchy, responsive stack и фон.

Теперь regeneration:

1. строит bounded hero plan;
2. применяет wpae_llm_design_engine_explicit_constraints() к исходному сообщению;
3. повторно компилирует fallback через существующий wpae_llm_design_engine_compile_hero();
4. сохраняет vision_regenerate_design_plan в diagnostics;
5. проходит прежние content/design-system/fidelity/transaction/read-back/editor-refresh проверки.

Отдельный write path не создан: запись идёт через существующую транзакционную границу.

### Корректный разбор направления текста

Регулярное выражение alignment теперь останавливается на запятой, точке с запятой и переводе строки. Поэтому фраза «текст слева, визуальная зона справа» даёт content_alignment=left; положение визуальной зоны справа больше не ошибочно превращается в right-aligned copy.

### Защита от превращения подписанных полей в заголовок

В wpae_llm_extract_hero_copy() добавлен приоритет явных меток «надзаголовок», «заголовок», «описание». Если такие поля уже распознаны, их полная строка не может стать generic brand heading. Это устранило регрессию второго hero, где весь prompt с labels попадал в визуальный заголовок.

### Pricing и native writeback

Для live-приёмки pricing восстановлена одна responsive desktop row из трёх карточек (1d83207), а EDDE/widget writeback и recovery продолжают использовать общую native Elementor структуру (b74baf9). Pricing не включён в EDDE scope и не был удалён при hero-операциях.

## 4. Файлы и история изменений

| Commit | Изменение |
|---|---|
| e549f64 | Feature-flagged EDDE hero decision vertical slice. |
| 511dbd0 | Сохранение EDDE constraints при Vision regeneration. |
| 500ec90 | Явная граница acceptance для 60/40. |
| b74baf9 | Pricing recovery и EDDE widget writeback. |
| 1d83207 | Три pricing cards в одной responsive desktop row. |
| 76829f9 | Исправление labeled hero fallback copy extraction. |

Кодовые файлы текущего релиза:

| Файл | Роль изменения |
|---|---|
| includes/llm/llm.php | Vision regeneration через EDDE compiler; extraction явных hero labels. |
| includes/llm/decision-engine.php | Typed EDDE plan, explicit constraints и точный alignment parsing. |
| tests/flex-generation-runtime.php | Production-path regression для 40/60, 60/40, CTA, surface, mobile stack и второго hero prompt. |
| tests/llm-chat-contract.test.js | Контракт версии v02.11.124. |
| wp-ai-executor.php | Header и WPAE_VERSION = v02.11.124. |
| wpae-package.json | SHA-256 manifest для 80 файлов. |
| context.md | Канонический журнал текущего source/live состояния. |

## 5. Точные live prompts

### Первый hero: 40/60

~~~text
Добавь новую hero-секцию для архитектурной студии «Тихая форма».
Надзаголовок «АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ».
Заголовок «Пространство для вашей жизни».
Описание «Проектируем спокойные, светлые интерьеры с вниманием к каждой детали».
Основная кнопка «Обсудить проект», ссылка #contact.
Вторичная кнопка «Смотреть проекты», ссылка #projects.
Текстовая часть занимает 40%, визуальная — 60%.
Текст выровнен по левому краю, визуальная зона расположена справа
и оформлена тонкой рамкой.
Композиция просторная, заголовок крупный, фон #F6F0E6.
На мобильном сначала текст, затем визуальная часть.
Существующие секции не изменяй.
~~~

### Второй hero: 60/40

~~~text
Создай второй hero-блок в конце текущей страницы, не удаляй и не изменяй существующие hero и pricing. Надзаголовок «АРХИТЕКТУРА ПОВСЕДНЕВНОСТИ», заголовок «Пространство для вашей жизни», описание «Проектируем спокойные, светлые интерьеры с вниманием к каждой детали». Основная кнопка «Обсудить проект» со ссылкой #contact; вторичная «Смотреть проекты» со ссылкой #projects. Композиция 60/40: текст слева, визуальная зона справа. Компактные отступы, нейтральный размер заголовка, outlined surface, фон #F6F0E6, на мобильном сначала текст, затем визуальная зона. Существующие hero и pricing сохрани.
~~~

## 6. Live operations и read-back

### Первый hero — split_40_60

Operation: wpae-20260921090807-8fa12ddb
Saved root: 96b68e5
Режим: EDDE active; UI diagnostics показывали openrouter/free. Точное сохранённое поле action_path для этой операции отдельно не извлекалось, поэтому оно не объявляется как независимое доказательство.

Публичный read-back подтвердил:

- фон #F6F0E6;
- text-left и visual-right;
- desktop content shell в строке;
- copy/visual ширины примерно 38.4/57.6;
- мобильные колонки 100/100, текст перед визуальной частью;
- wpae-hero-visual-panel с тонкой рамкой;
- две CTA-пары: Обсудить проект → #contact и Смотреть проекты → #projects;
- существующие секции ниже hero сохранились.

### Pricing preservation

Operation: wpae-20260921094359-dc8fd3ea
Saved root: 9f3bee7
Nested grid: f02313f
Vision: score 85, confidence 95%, accepted.

В read-back остались три карточки:

| Карточка | Текст | Ссылка |
|---|---|---|
| Старт | от 50 000 ₸ и исходное описание | #start |
| Проект | от 150 000 ₸ и исходное описание | #project |
| Поддержка | от 80 000 ₸/мес и исходное описание | #support |

Кнопки и их labels также сохранились: Выбрать Старт, Обсудить проект, Подключить поддержку.

### Второй hero — split_60_40

Operation: wpae-20260921100000-cc191309
Saved root: 9bdbc05
Vision: score 85, confidence 95%, accepted.

Ход операции:

1. EDDE plan был typed и скомпилирован.
2. Provider response был отклонён quality gate как sparse composition.
3. Запущен deterministic content-complete fallback.
4. Native widgets: 6; existing elements: 2; inserted: 1.
5. Save завершился HTTP 200, затем выполнены read-back и refresh.

Публичная геометрия после reload:

| Узел | Evidence |
|---|---|
| Root 9bdbc05 | #F6F0E6, flex column, 1265×340, y=902 |
| Content shell 4db3319 | flex row, gap 20, 1217×168, x=24, y=1035 |
| Copy 6b0a8c8 | 701×168, примерно 60%, x=24 |
| Visual 8ada197 | wpae-hero-visual-panel, outlined, 467×168, примерно 40%, x=745 |

На мобильном read-back и inline screenshot показали copy-first stack: текст и CTA идут перед visual panel. В текущем editor iframe кроме нового root видны старые raw-prompt nodes; публичный DOM их не содержит.

## 7. Сопоставление plan → compiler → normalization → write

| Сценарий | Plan | Compiler/normalization | Write/read-back |
|---|---|---|---|
| Первый hero | hero, split_40_60, left, spacious, outlined, display, primary_secondary, copy_first_stack | Native copy/visual columns; #F6F0E6; две CTA link settings; mobile stack | Root 96b68e5; public geometry 38.4/57.6; exact copy and links present |
| Pricing | Existing non-EDDE structure | Responsive row normalizer keeps all 3 cards and paired CTA links | Root 9f3bee7; grid f02313f; all 3 cards preserved |
| Второй hero | hero, split_60_40, left, compact, outlined, neutral, primary_secondary, copy_first_stack | Sparse provider response rejected; deterministic fallback recompiled; labeled copy extraction prevents raw prompt heading | Root 9bdbc05; public geometry 60/40; Vision 85/95; HTTP 200 |

Все три сценария проходят общий validation, transaction, _elementor_data write/read-back и editor refresh. EDDE не записывает WordPress meta напрямую.

## 8. Vision, A/B и unsaved-safety matrix

| Проверка | Результат | Граница доказательства |
|---|---|---|
| EDDE active mode | PASS | Live diagnostics в Elementor: mode=active, provider openrouter/free. |
| Первый hero 40/60 | PASS | Live save, public DOM geometry, exact copy/CTA и desktop/mobile inline captures. Числовой Vision score этой операции не был сохранён в итоговом diagnostics extract. |
| Pricing preservation | PASS | Root/grid read-back, три карточки и Vision 85/95. |
| Второй hero 60/40 | PASS | Provider sparse → fallback path, public geometry, exact copy/CTA, Vision 85/95. |
| Vision regeneration constraint fidelity | PASS | v124 compiler повторно применяет composition/alignment/surface/typography/CTA/mobile/background constraints. |
| Vision incomplete-capture scenario A | NOT RUN | Отдельный неполный screenshot capture не запускался. |
| Vision incomplete-capture scenario B | NOT RUN | Отдельный неполный screenshot capture не запускался. |
| Live manual neighboring unsaved pricing edit | NOT RUN | Не создавался рискованный ручной edit; никаких новых страниц и черновиков не добавлялось. |
| Runtime ownership/transaction safety | PASS | editor-roots-probe.js — 15 assertions; transactions-probe.php — autosave owner/fingerprint, conflict, rollback и failure guards. |

## 9. Локальные проверки

| Команда | Результат |
|---|---|
| php -l includes/llm/llm.php | PASS |
| php -l includes/llm/decision-engine.php | PASS |
| php -l wp-ai-executor.php | PASS |
| php tests/flex-generation-runtime.php | PASS — 331 checks OK |
| node --test tests/flex-generation-runtime.test.js | PASS; PHP runtime 331 |
| node --test tests/llm-chat-contract.test.js | PASS |
| node --test tests/vision-security-contract.test.js | PASS |
| php docs/audits/2026-09-12/package-probe.php | PASS: 4 scenarios, 80 files, valid_zip=true, unsafe_path=false, corrupted_hash=false, missing_required_file=false; full-result UTF-8 serialization probe отдельно предупредил Malformed UTF-8 |
| node docs/audits/2026-09-12/editor-roots-probe.js | PASS — 15 assertions, unsaved roots preserved, owned AI root removed, idempotency preserved |
| php docs/audits/2026-09-12/transactions-probe.php | PASS — autosave, conflict, rollback и delete-failure guards |
| git diff --check | PASS |
| Direct SHA-256 manifest check | PASS — 80 files |

## 10. Deployment evidence

Source deployment:

- git commit выполнен для runtime изменений в 76829f9;
- git push origin main выполнен;
- изменённые файлы пакета покрыты wpae-package.json.

WordPress deployment:

- открыт существующий WP Pusher экран;
- обновлена строка WP AI Executor с ветки main;
- UI показал Plugin was successfully updated.;
- после reload Elementor показал v02.11.124;
- активность подтверждена экраном плагинов (Деактивировать).

Эти два слоя не смешиваются: commit/push подтверждает исходник, WP Pusher и header подтверждают установленный live release.

## 11. Screenshot evidence и ограничение ссылок

В CUA были получены свежие inline captures:

- public desktop: первый hero и pricing;
- Elementor mobile: первый hero;
- public desktop: второй hero и pricing;
- Elementor mobile: второй hero.

Файловых screenshot paths нет. tab.screenshot() возвращает Uint8Array без имени файла, а tab.content.export() возвращает точную ошибку:

~~~text
Codex in-app browser does not support command "tab_content_export"
~~~

Поэтому в отчёте намеренно не указаны выдуманные absolute links. Inline captures были показаны в ходе этой сессии; экспортируемая файловая gallery через доступный CUA API заблокирована.

## 12. Известные ограничения и наблюдаемые дефекты

- В Elementor iframe остаются исторические raw-prompt nodes от предыдущих операций. Они не входят в public saved DOM и не являются текущим сохранённым root.
- Vision зафиксировал только один minor: badge typography была задана крупнее ожидаемого (2.75rem); операция при этом принята с 85/95.
- Два отдельных A/B сценария с неполным capture не запускались.
- Ручной live unsaved-neighbor сценарий не запускался; вместо него зафиксированы проходящие runtime ownership/transaction probes.
- CUA не выдаёт локальные screenshot paths, поэтому прямые ссылки на файлы отсутствуют.

## 13. Финальное состояние

На момент передачи source и live синхронизированы на v02.11.124; текущая страница post=5214 содержит исходный pricing, первый hero 40/60 и второй hero 60/40. EDDE остаётся hero-only, а запись проходит существующие native Elementor transaction/read-back boundaries. Итоговый исходник находится в 76829f9 и отправлен в origin/main.

---

# Архитектурное продолжение: deterministic design pipeline v1

Дата фиксации: 2026-09-21, Asia/Almaty
Рабочий clone: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Целевая страница: только существующая Elementor page `post=5214`
Source release: `v02.11.125`
Live release на момент отчета: `v02.11.124`
Implementation commit: `2f97c8f` (`Introduce deterministic design pipeline contracts`)

## 14. Что изменено

Генератор получил versioned границу между пользовательским prompt, typed
design decisions и native Elementor data:

```text
prompt
  -> BriefIR v1
  -> DesignPlan v1
  -> deterministic LayoutReport / capability checks
  -> ElementorIR v2
  -> native compiler
  -> existing Elementor preflight/transaction/readback
```

`BriefIR` сохраняет `exact_text`, нормализованный текст, URL CTA отдельно,
source span, confidence и provenance. Парсер отдельно покрывает русские и
английские prompts, короткие labels `FAQ`, `О нас`, `7 шагов`, несколько CTA,
style-only и ambiguous prompts, длинный заголовок с переносом строки и явную
медиа-ссылку. Он не создает content, metrics, claims или URL, которых нет в
источнике.

`DesignPlan v1` ограничен archetypes `hero`, `process` и `pricing`. Для каждого
плана фиксируются composition, allowed native widgets, content refs, semantic
token refs, responsive policy, media refs, quality gates и provenance. Остальные
archetypes продолжают legacy compatibility path.

`ElementorIR v2` не является provider JSON. Он содержит `node_id`, role,
widget type, refs, constraints, responsive policy, media refs, editable fields
и provenance. `native-compiler.php` предоставляет единственную стабильную
границу компиляции в native Elementor data; compiler сам создает детерминированные
IDs, разрешает semantic tokens, задает Flex basis/grow/shrink, mobile stack,
responsive settings, exact copy, links и image metadata.

`LayoutReport` проверяет 1440, 1024, 768 и 390 px: container/used/available
width, gaps, basis, min/max, zero-width children, overflow, fixed text height,
mobile stack и suggested patches. Добавлен contrast gate для text/muted/CTA
semantic roles.

## 15. Какие root causes исправлены

1. Парсинг и layout decisions больше не смешаны с provider-generated tree.
2. Второй CTA больше не теряет label, URL или provenance; UTF-8 prefix не
   ломает распознавание `title`, `eyebrow` и CTA.
3. Явный `hero` имеет приоритет над словом `шагов` внутри copy и не ошибочно
   становится `process`.
4. Многострочный quoted text сохраняется без потери переноса.
5. Недоступный widget проходит через общий capability registry и получает
   известный native fallback с downgrade diagnostic.
6. Missing token и низкий contrast видны до записи; palette choices нового
   слоя больше не зависят от произвольных цветов модели.
7. Layout basis и mobile stack вычисляются локально, поэтому provider не может
   самовольно создать zero-width child или fixed-height text block.
8. Повторный active-запрос защищен idempotency key и operation ledger; новый
   root не добавляется поверх pending/unknown операции без reconcile.

## 16. Измененные файлы

Новые runtime-модули:

- `includes/llm/brief-ir.php`
- `includes/llm/design-plan.php`
- `includes/llm/routing.php`
- `includes/design/token-resolution.php`
- `includes/elementor/capability-registry.php`
- `includes/elementor/reference-set.php`
- `includes/elementor/layout-report.php`
- `includes/elementor/elementor-ir.php`
- `includes/elementor/native-compiler.php`
- `includes/elementor/operation-ledger.php`

Изменены existing boundaries и UX:

- `includes/llm/llm.php` — feature-flagged shadow/active integration,
  diagnostics, preflight summary, deterministic ID handoff и operation state.
- `includes/llm/transport.php` — persisted `design_pipeline_mode`.
- `includes/admin/dashboard.php` — настройки `off/shadow/active`.
- `assets/js/elementor-llm-chat.js` и `assets/css/elementor-llm-chat.css` —
  видимые фазы разбора, планирования, components, responsive, compile, write,
  render и visual review; существующие retry/undo/session recovery сохранены.
- `wp-ai-executor.php` — source version `v02.11.125`.
- `wpae-package.json` — SHA-256 manifest обновлен с 80 до 90 runtime files.
- `README.md`, `ERRORS.md` — описаны режимы pipeline и EJ-131.
- `tests/design-pipeline-contract.php` и Node wrapper — новый contract harness.

## 17. Сохраненные legacy paths

- `includes/llm/decision-engine.php` остается узким EDDE hero slice и не
  превращен в универсальный DSL.
- Legacy provider action decode, fallback, recipes, blueprint, block library,
  `includes/llm/design.php`, normalizers и content-fidelity guards не удалены.
- `includes/elementor/transactions.php`, `page-update.php`, validation,
  protected-zone checks, autosave ownership, rollback и saved `_elementor_data`
  readback остаются единственной write boundary.
- `design_pipeline_mode=off` сохраняет текущий provider path без изменения
  поведения; `shadow` строит новый результат только в памяти и показывает
  comparison diagnostics, а запись выполняет legacy path.
- Existing user-authored roots, foreign/autosave roots, protected zones,
  links и untouched content не удаляются новым слоем.

## 18. Feature flags и operation states

`design_pipeline_mode` принимает только:

```text
off     -> legacy path
shadow  -> BriefIR/Plan/IR/compiler diagnostics, без нового write
active  -> bounded native compiler для hero/process/pricing
```

Durable record использует `wpae-design-operation-v1` и states:
`planned -> generated -> normalized -> validated -> written -> rendered ->
reviewed -> revised -> completed`, плюс `failed` и `unknown`. Idempotency key
строится из `post_id + brief_hash + selected_scope + operation_type`.

## 19. Проверки

Пройдены:

- `php tests/design-pipeline-contract.php` — **32 checks OK**;
- `php tests/flex-generation-runtime.php` — **331 checks OK**;
- `node --test tests/design-pipeline-contract.test.js`;
- `node --test tests/flex-generation-runtime.test.js tests/llm-chat-contract.test.js tests/vision-security-contract.test.js tests/design-pipeline-contract.test.js` — все **4 suites PASS**;
- `node --check assets/js/elementor-llm-chat.js`;
- PHP lint всех измененных PHP-файлов;
- `git diff --check`;
- package SHA-256 verification — **90/90 PASS**.

Новый self-check отдельно проверяет short labels, RU/EN, hero/process/pricing,
несколько CTA, URL fidelity, multiline title, style-only, ambiguity,
capability downgrade, ReferenceSet focal point, deterministic IDs, native
Flex basis, mobile stack, zero-width violation, contrast, idempotency и route
policy.

## 20. Browser/render evidence

Новая версия пока не устанавливалась в WordPress и `design_pipeline_mode` не
переключался в `active`. Поэтому для v02.11.125 нет свежих current generation,
saved `_elementor_data` readback, rendered HTML, DOM geometry, desktop/mobile
screenshots или Vision review. Это source-only architecture release; прежнее
live evidence v02.11.124 на post=5214 остается действительным для уже
установленного EDDE/legacy path и не является acceptance нового active compiler.

В результате визуальная приемка v02.11.125 сознательно имеет статус
**pending**, а не completed. Новые страницы и черновики для проверки не
создавались.

## 21. Остаточные предупреждения и limitations

- Live `active` install/revision check еще не выполнены; source `v02.11.125`
  и live `v02.11.124` намеренно разделены.
- Operation ledger уже durable и idempotent, но отдельный REST reconcile UI
  endpoint для unknown browser timeout остается следующим минимальным шагом;
  существующий editor retry/undo path не удален.
- Provider telemetry policy schema присутствует, однако точные input/output
  tokens, latency и cost требуют подключения к фактическому transport response
  metadata; в локальном compiler-only пути стоимость равна нулю.
- Server-side LayoutReport не доказывает computed CSS, keyboard behavior,
  animation timing или screenshot-level fidelity; это по-прежнему требуют
  свежие browser screenshots и Vision review.
- Legacy hardcoded colors в EDDE/fallback сохранены ради backward-compatible
  path; новый compiler использует semantic refs и safe defaults.

## 22. Commit, push и следующий шаг

- Implementation commit: `2f97c8f`.
- Documentation/context update: будет отдельным docs commit после этой записи.
- Push: выполнить в разрешенный `origin/main` после финальной проверки staged
  files; live WP Pusher deployment в этом этапе не выполняется.
- Следующий минимальный шаг: push `v02.11.125`, установить его через WP
  Pusher на уже открытую страницу `post=5214`, выполнить одну controlled
  active hero generation и собрать saved JSON, rendered HTML, DOM geometry,
  desktop/mobile screenshots и Vision review. Только после этого можно
  переводить active acceptance из pending в completed.
