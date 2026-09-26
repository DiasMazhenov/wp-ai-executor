# WP AI Executor — актуальный handoff

Дата фиксации: **2026-09-26, 19:49 Asia/Almaty**.

## Результат

Подтверждена причина большого пустого пространства у text-only CTA: текущая композиция центрировала ограниченную текстовую группу внутри широкой секции. Чтобы CTA мог получить фотографию справа, в v02.11.160 добавлен вариант `split_60_40` для явного media reference. Вариант без фотографии остаётся прежним. Релиз опубликован и установлен, но применить его к текущей странице не удалось: после обычной перезагрузки существующая операция помечена `root_missing`, а редактор и публичный ответ пусты. Никакая запись страницы в этой проверке не выполнялась.

## Версии и выпуск

- Source runtime: `v02.11.160`, commit `7beef71bffa1745427956879c7bcb483ee28ee99` (`Add media composition to CTA replacement`).
- Документация отчёта и context закоммичена и отправлена в `origin/main` после runtime commit `7beef71`.
- Live plugin: `v02.11.160`, обновлён штатно через WP Pusher.
- После обновления обычная перезагрузка существующей editor-вкладки показала inline config `v02.11.160`.
- Runtime commit содержит 8 файлов: CTA routing/UI, DesignPlan, ElementorIR compiler, tests, plugin version и package hashes.
- Страница: существующий `post=5214`; новые страницы, drafts и roots не создавались.

## Изменение композиции

- `includes/llm/design-plan.php`: CTA media plan принимается только при явном валидном изображении, alt и provenance/license; план использует desktop `split_60_40`, image справа.
- `includes/elementor/elementor-ir.php`: компилятор создаёт native copy и image containers; tablet/mobile складывает их вертикально, сначала copy. Изображение использует native Elementor image widget, `object-fit: cover` и существующий `radius.card` token.
- `includes/llm/llm.php`: сохраняет точный media reference и ограничивает CTA-композицию известным планом.
- `assets/js/elementor-llm-chat.js`: targeted replacement передаёт существующую operation identity только при единственном выбранном root, совпадающем с текущей reviewable operation.
- Без media намеренно сохраняется существующий text-only режим. Генератор не подставляет случайное или синтетическое изображение.
- Регрессионный media example использует бесплатную по условиям Unsplash фотографию интерьера Neon Wang: [источник фотографии](https://unsplash.com/photos/modern-interior-with-concrete-walls-and-wooden-accents-JsL6PZU1KRU), [условия Unsplash License](https://unsplash.com/license). Это только локальный пример для pipeline; в страницу фото не записывалось.

## Live state и защита данных

После установки v02.11.160 fresh editor config показал:

- pending operation `wpae-2ca8292fb0a142e8`;
- identity `384c25ad-8506-449f-8cfe-637da1287a4e`;
- revision `5`, state `written`;
- target root `eb0103a`;
- `reviewable=false`, `target_status.reason=root_missing`.

Editor canvas после обычного reload содержит ноль Elementor roots. Public DOM после собственного обычного reload также содержит ноль Elementor roots и ноль headings. Поэтому фактический текущий набор root IDs в этих двух представлениях — пустой. Когда и почему root исчез из сохранённого документа, эта проверка не установила.

До reload public-вкладка показывала старый HTML с четырьмя roots `5a3292b`, `32f16d1`, `5b96df3`, `eb0103a`. Это доказывает, что вкладка показывала устаревшее относительно ответа после reload содержимое; конкретный слой кэширования не установлен. Старый кадр не считается результатом v02.11.160.

Сохранение пустой editor model поверх страницы, append, восстановление старого root и обход ownership/fingerprint/revision guards не выполнялись. Targeted replacement и live visual acceptance остались заблокированы отсутствием reviewable target `eb0103a`.

## Проверки

| Проверка | Результат |
|---|---|
| CTA explicit-media DesignPlan/compiler contract | PASS |
| Replacement ownership/stale-target JS behavior | PASS |
| `php tests/design-pipeline-contract.php` | PASS — 245 checks |
| `php tests/flex-generation-runtime.php` | PASS — 396 checks |
| `node --test tests/*.test.js` | PASS — 4/4 |
| PHP lint изменённых runtime/test файлов | PASS |
| Package probe | PASS — 90 файлов, 0 hash mismatches, 4 scenarios |
| `git diff --check` | PASS до фиксации отчёта |
| Push source runtime | PASS — `7beef71` на `origin/main` |
| WP Pusher install и editor inline version | PASS — `v02.11.160` |
| Live replacement/save/readback | BLOCKED — целевой root отсутствует; operation сообщает `root_missing` |
| Новый desktop/mobile render и operation-bound Vision | NOT RUN — live блок не создавался |

Package probe сообщает отдельное ограничение: полный JSON summary не кодируется из-за malformed UTF-8 в диагностических данных; компактный summary проходит. Hash verification при этом завершилась успешно.

## Скриншоты

PNG получены из свежих Browser Use captures: JPEG-сигнатура проверена, преобразование выполнено через `sips`, итоговые файлы открыты и визуально проверены. Pixel-размер указан отдельно от CSS viewport.

### Старый public DOM до reload — только диагностика устаревшей вкладки

Root `eb0103a`, старый text-only CTA, operation `wpae-2ca8292fb0a142e8`; CSS viewport **1105×923**; PNG **1050×923**; public.

![Public CTA до reload — устаревшая вкладка](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-public-render-after-editor-reload-20260926.png)

[Открыть PNG — public до reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-public-render-after-editor-reload-20260926.png)

### Текущее состояние editor после reload

Post `5214`; operation `wpae-2ca8292fb0a142e8`, rev 5; target `eb0103a`, status `root_missing`; CSS viewport **1100×923**, canvas **1025×860**; PNG **1045×923**; editor.

![Editor после reload — target root отсутствует](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-editor-v160-after-reload-empty-20260926.png)

[Открыть PNG — editor после reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-editor-v160-after-reload-empty-20260926.png)

### Текущий public response после reload

Post `5214`; root IDs отсутствуют; CSS viewport **1105×923**; PNG **1050×923**; public.

![Public после reload — Elementor roots отсутствуют](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-public-after-reload-empty-20260926.png)

[Открыть PNG — public после reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-public-after-reload-empty-20260926.png)

Эти кадры фиксируют диагностику страницы, а не новую композицию. Desktop/mobile screenshots нового CTA с фотографией и Vision review отсутствуют, потому что отсутствовал безопасно заменяемый root.

## Остаточные статусы

- **PASS:** локальная deterministic CTA-композиция с явно заданной фотографией; routing и guards; source push; v02.11.160 установлена.
- **BLOCKED:** live targeted repair существующего CTA — target root `eb0103a` больше не подтверждается серверной конфигурацией операции.
- **NOT RUN:** запись/сохранение нового CTA, desktop/mobile acceptance нового блока, operation-bound Vision.
- **Не установлено:** точное время и механизм удаления/рассинхронизации `eb0103a`; точный кэш-слой старой public-вкладки.

## Историческое состояние

Проверки v02.11.159 и более ранних версий, включая ранее виденные roots и визуальные тесты других семейств, относятся к прошлым snapshot и не подтверждают текущее содержимое `post=5214`.
