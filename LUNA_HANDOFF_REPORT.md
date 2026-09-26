# WP AI Executor — актуальный handoff

Дата фиксации: **2026-09-27 03:09 Asia/Almaty**.

## Итог: агент выбирает шаблон из shortlist библиотеки

В WPAE уже есть ограниченный слой решения для шаблонов: локальный retrieval отбирает до трёх кандидатов, а агент получает их summaries через `wpae_llm_library_decision_prompt()` и возвращает `library_choice` либо `null`. `wpae_llm_resolve_library_choice()` разрешает только ключ из переданного allowlist; затем существующий adapter проверяет структуру, fidelity и семантический контракт. Локальный ranking сам по себе не должен назначать победителя.

Отдельный EDDE в `includes/llm/decision-engine.php` принимает typed layout-решения для hero. Это соседний механизм, не выбор импортированного шаблона. На v02.11.166 active Design Pipeline имел приоритет и обходил provider path даже при наличии библиотечных кандидатов. Поэтому конкретный live FAQ на `post=5214` был собран локально и не доказывал выбор шаблона агентом. Предыдущее утверждение, будто локальный ranking выбирает готовый шаблон, было неверным.

В v02.11.167 исправлен этот routing: при active pipeline, допустимом archetype и shortlist кандидатов используется `library_agent` route с одним provider call и одной существующей write boundary. EDDE не запускается параллельно. Targeted edits, Vision repair/regeneration и replacement остаются на прежних маршрутах. При отказе модели или отсутствии валидного выбора локальный top-ranked вариант не подставляется молча; применяется обычная native composition/fallback path.

## Проверенная версия и live state

- Source branch: `main`; HEAD `f9e68767a40024c01c120be5d04beddc8e90eff6`, commit `Route active design through agent library decisions`; runtime version `v02.11.167`.
- Push в `origin/main`: **PASS**, `2ffa39d..f9e6876`.
- WP Pusher: **PASS** — после нажатия Update plugin показал `Plugin was successfully updated.`
- Установленная версия отдельной строкой Plugins UI после обновления не перепроверена. Уже открытая вкладка Elementor всё ещё показывает inline version `v02.11.166`; её не перезагружал, поскольку в текущем editor state виден созданный FAQ, а dirty/save status не удалось независимо установить.
- Последняя показанная операция на этой вкладке: FAQ `wpae-4da1eea881d9e2ce`, root `a88fb3a`, `action_path=pipeline`, `provider_calls=0`, `route=local_deterministic`. Эта live-операция относится к v02.11.166 и не является проверкой нового `library_agent` маршрута.
- В ходе v02.11.167 ни один page/root не создавался и не изменялся. Новая библиотечная генерация в установленном editor **NOT RUN**; публичный render, save/reload и screenshots для v167 **NOT RUN**.

## Behavioral evidence

- Runtime harness активного pipeline передаёт два кандидата. Модель выбирает `candidate_2`, хотя первым локально ранжирован `candidate_1`; именно дерево второго кандидата проходит адаптацию и достигает обычной write boundary.
- Harness проверяет один provider call, одну запись, сохранение существующих корней, точный текст и URL `#contact`; EDDE не запускается вторым компилятором.
- Отдельный regression проверяет отказ/невалидный выбор: ни один ranked candidate не вставляется вместо решения агента.
- Route matrix проверяет active pipeline без кандидатов (локальный pipeline, 0 provider calls) и с кандидатами (library agent, 1 provider call); оба случая имеют одну write boundary.

## Импортированная библиотека

`includes/elementor/imported-templates/` содержит 157 JSON-файлов из `/Users/diasmazhenov/Downloads/templates/1`; 155 имеют извлекаемое Elementor tree. Локальный production-catalog harness читает и инстанцирует все 155. Это подтверждает доступность каталога, но не генерацию всех 155 через агент и не live-визуальную проверку каждого шаблона. Архивы из `elementorpro-temp` не смешиваются с этим отдельным bundle.

## Проверки v02.11.167

- `node --test tests/*.test.js` — **PASS, 6/6**.
- `php tests/design-pipeline-contract.php` — **PASS, 246 checks**.
- `php tests/flex-generation-runtime.php` — **PASS, 413 checks**.
- `php -d error_reporting=E_ALL tests/imported-template-catalog.php` — **PASS: 157 manifest entries, 155 retrievable trees, 155 previews**.
- PHP lint изменённых PHP и harness-файлов — **PASS**.
- `php docs/audits/2026-09-12/package-probe.php` — **PASS: 248 files, 0 hash mismatches, 4 scenarios**. Известная полная JSON-сериализация diagnostic payload всё ещё возвращает malformed UTF-8; compact probe проходит.
- `git diff --check` — **PASS**.

## Изменённые файлы релиза

`includes/llm/llm.php`, `includes/llm/routing.php`, `tests/design-pipeline-contract.php`, `tests/flex-generation-runtime.php`, `wp-ai-executor.php`, `wpae-package.json`.

## Исторические проверки до v02.11.164

Ниже сохранены прежние QA-записи, включая v160. Они относятся к указанным версиям и snapshots и не заменяют текущие факты выше; старые утверждения о не включённых source JSON superseded commit `f8ee9a9`.

## Историческое изменение композиции v02.11.160

- `includes/llm/design-plan.php`: CTA media plan принимается только при явном валидном изображении, alt и provenance/license; план использует desktop `split_60_40`, image справа.
- `includes/elementor/elementor-ir.php`: компилятор создаёт native copy и image containers; tablet/mobile складывает их вертикально, сначала copy. Изображение использует native Elementor image widget, `object-fit: cover` и существующий `radius.card` token.
- `includes/llm/llm.php`: сохраняет точный media reference и ограничивает CTA-композицию известным планом.
- `assets/js/elementor-llm-chat.js`: targeted replacement передаёт существующую operation identity только при единственном выбранном root, совпадающем с текущей reviewable operation.
- Без media намеренно сохраняется существующий text-only режим. Генератор не подставляет случайное или синтетическое изображение.
- Регрессионный media example использует бесплатную по условиям Unsplash фотографию интерьера Neon Wang: [источник фотографии](https://unsplash.com/photos/modern-interior-with-concrete-walls-and-wooden-accents-JsL6PZU1KRU), [условия Unsplash License](https://unsplash.com/license). Это только локальный пример для pipeline; в страницу фото не записывалось.

## Исторический аудит охвата `elementorpro-temp`

Папка `/Users/diasmazhenov/Downloads/elementorpro-temp` содержит 16 ZIP, из них 15 уникальных: один Aquassi архив продублирован. В уникальных архивах найдено 267 JSON-файлов: 238 с Elementor tree и 29 JSON без дерева. Tree-файлы включают полные страницы, header/footer и отдельные секции; число JSON не равно числу независимых блоков.

Эти архивы использовались как reference source; отдельные JSON/ZIP-блоки из них не импортировались в production library или напрямую в generator. Поэтому формулировка «проверить все импортированные шаблоны» здесь неприменима буквально: импортированных kit-блоков в production нет. Проверяемая production-поверхность — девять archetypes ниже; их поведение проверено локальным production harness. `includes/elementor/copyelement/manifest.php` содержит отдельные CopyElement/Vocario fixtures и не является импортом этих 15 ZIP.

| Семейство | Локальный production harness | Live на `post=5214` в этом прогоне | Примечание |
|---|---|---|---|
| Hero | PASS | NOT RUN | Также отдельно проходит hero с explicit image |
| Process/timeline | PASS | NOT RUN | Typed pipeline family |
| Pricing | PASS | NOT RUN | Typed pipeline family |
| FAQ | PASS | NOT RUN | Typed pipeline family |
| Benefits | PASS | NOT RUN | Typed pipeline family |
| Services | PASS | PASS: editor/public save-reload | Единственный live тест этого прогона; cleanup blocked |
| Team | PASS | NOT RUN | Typed pipeline family |
| Testimonials | PASS | NOT RUN | Typed pipeline family |
| CTA | PASS | NOT RUN | Typed pipeline family |

Галереи/carousel, формы, header/footer/navigation, blog/archive, course/event/product/shop, popup/off-canvas и прочие addon-specific patterns из архивов не входят в девять typed archetypes. Их наличие в исходных ZIP или списка Elementor widgets не доказывает поддержку generator-ом; они отмечены **NOT RUN / не поддерживаются текущим deterministic DesignPlan contract**. Полный live-прогон каждого из 267 JSON exports не выполнялся и не означал бы тестирование 267 отдельных production-блоков.

## Историческое live state и защита данных

До текущего Services теста на `post=5214` наблюдалась операция CTA `wpae-2ca8292fb0a142e8` с identity `384c25ad-8506-449f-8cfe-637da1287a4e`, revision 5, state `written`, target `eb0103a`, `reviewable=false`, `target_status.reason=root_missing`. Этот старый target остаётся stale и не использовался для текущего теста.

Точный QA-запрос Services:

> Блок услуг для QA, только тестовые данные. Услуга 1 — название: «QA-услуга: архитектура». Услуга 1 — описание: «Проверяем перенос текста и native-композицию.» Услуга 2 — название: «QA-услуга: дизайн». Услуга 2 — описание: «Тестовый текст второй карточки.» Услуга 3 — название: «QA-услуга: сопровождение». Услуга 3 — описание: «Синтетическая запись для временной проверки.»

Editor чата подтвердил этапы BriefIR → DesignPlan → capabilities → LayoutReport → ElementorIR → запись, написал operation `wpae-ee4bb88fb23e06e3` и сообщил `HTTP 200` для Elementor update, одну новую root и шесть native widgets. UI сообщил, что блок собран deterministic pipeline без provider-generated tree. Поле `diagnostics.action_path`, provider call count и durable operation identity не удалось отдельно считать; эти значения не выдаются за независимо подтверждённые.

После reload public DOM содержит все три QA карточки и один top-level Elementor root `42d4363`; public viewport `1105×923`. В editor mobile toolbar был штатно выбран mobile, preview iframe имел ширину **360 CSS px**; все три карточки расположены вертикально. Editor inline `initial_document`, считанный после reload, всё ещё сообщил 0 roots, хотя canvas показывал тестовый блок. Это расхождение не объяснено и authoritative ledger readback отсутствует.

После фиксации кадров была нажата штатная кнопка Undo для текущего тестового сообщения. Сервер ответил `wpae_undo_stale_revision` / «Состояние операции уже изменилось; старый rollback отклонён». Затем существующий read-only GET `/design-operations/target?post_id=5214&operation_id=wpae-ee4bb88fb23e06e3` через Browser Use вернул `net::ERR_BLOCKED_BY_CLIENT`. Альтернативный транспорт, повтор запроса и обход stale/ownership guards не использовались. На последней public-проверке root `42d4363` оставался; дальнейших live writes не было.

## Исторические проверки v02.11.160

| Проверка | Результат |
|---|---|
| CTA explicit-media DesignPlan/compiler contract | PASS |
| Replacement ownership/stale-target JS behavior | PASS |
| `php tests/design-pipeline-contract.php` | PASS — 245 checks (текущий прогон) |
| `php tests/flex-generation-runtime.php` | PASS — 396 checks; в production cases покрыты все девять archetypes и hero с изображением |
| `node --test tests/*.test.js` | PASS — 4/4 |
| PHP lint изменённых runtime/test файлов | PASS |
| Package probe | PASS — 90 файлов, 0 hash mismatches, 4 scenarios |
| `git diff --check` | PASS — после обновления отчёта и context |
| Push source runtime | PASS — `7beef71` на `origin/main` |
| WP Pusher install и editor inline version | PASS — `v02.11.160` |
| Services live route/write | PASS по UI: deterministic pipeline, Elementor update HTTP 200, один root; точное поле `diagnostics.action_path` не считано |
| Services public save/reload and desktop | PASS — три карточки видны на public после reload |
| Services editor mobile | PASS — preview iframe CSS width 360px, три карточки stacked |
| Services public mobile | NOT RUN |
| Services operation-bound Vision | NOT CONFIRMED — UI показал advisory score 85/confidence 95, attachment/source binding не верифицирован |
| Live cleanup Undo | BLOCKED — `wpae_undo_stale_revision` |
| Services durable ledger readback | BLOCKED — Browser Use GET вернул `net::ERR_BLOCKED_BY_CLIENT` |
| Остальные восемь live семейств | NOT RUN в этом прогоне; локальные production tests PASS |
| Текущий QA root | `42d4363` остаётся на `post=5214`; других новых roots/pages/drafts не создавалось |

Package probe из предыдущего runtime релиза сообщает отдельное ограничение: полный JSON summary не кодируется из-за malformed UTF-8 в диагностических данных; компактный summary проходит. Runtime в этом прогоне не менялся, поэтому package hashes не пересобирались.

## Исторические скриншоты v02.11.160

PNG получены из свежих Browser Use captures: JPEG-сигнатура проверена, преобразование выполнено через `sips`, итоговые файлы открыты и визуально проверены. Pixel-размер указан отдельно от CSS viewport.

### Services — public desktop после save/reload

Post `5214`; root `42d4363`; operation `wpae-ee4bb88fb23e06e3`; CSS viewport **1105×923**; PNG **1050×923**; public. Видны три карточки. WordPress admin bar находится сверху, плавающий chat bubble — внизу справа, содержимое карточек он не закрывает.

![Services QA — public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-public-desktop-20260926.png)

[Открыть PNG — Services public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-public-desktop-20260926.png)

### Services — editor mobile

Post `5214`; root `42d4363`; operation `wpae-ee4bb88fb23e06e3`; editor outer viewport **1100×923**, preview iframe **360 CSS px**; PNG **1045×923**. Это Elementor mobile preview, не public mobile.

![Services QA — editor mobile 360 CSS px](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-editor-mobile-360-20260926.png)

[Открыть PNG — Services editor mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-editor-mobile-360-20260926.png)

### Services — editor desktop, native structure evidence

Post `5214`; root `42d4363`; operation `wpae-ee4bb88fb23e06e3`; editor viewport **1100×923**, PNG **1045×923**. Встроенное canvas узкое из-за Elementor sidebar, поэтому часть третьей карточки видна не полностью; это не desktop visual acceptance.

![Services QA — editor desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-editor-desktop-20260926.png)

[Открыть PNG — Services editor desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-editor-desktop-20260926.png)

Снимки CTA из предыдущего прогонa находятся в `docs/audits/2026-09-26-v160-cta/` и относятся к состоянию до Services QA-теста. Они не подтверждают current page roots и в эту галерею текущего результата не включены.

## Исторические остаточные статусы v02.11.160

- **PASS:** все девять typed archetypes и hero-image вариант в локальном production harness; Services QA-root прошёл live save/reload, public desktop и editor mobile.
- **BLOCKED:** безопасное удаление только нашего QA-root после `wpae_undo_stale_revision`; current operation GET blocked by Browser Use. Root `42d4363` остаётся на странице.
- **NOT RUN:** live проверки восьми остальных typed families на v02.11.160, Services public mobile, operation-bound Vision confirmation, live tests семейств вне typed schema.
- **Не подтверждено:** current Services operation identity/revision и причина расхождения editor `initial_document` (0 roots) с видимым editor canvas/public root.

## Историческое состояние

Проверки v02.11.159 и более ранних версий, включая ранее виденные roots и визуальные тесты других семейств, относятся к прошлым snapshot и не подтверждают текущее содержимое `post=5214`. В частности, исторические screenshots не засчитаны как live PASS этого прогона.
