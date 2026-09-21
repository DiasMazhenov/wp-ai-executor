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
