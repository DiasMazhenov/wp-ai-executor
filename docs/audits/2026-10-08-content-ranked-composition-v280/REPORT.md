# M2.2 content-ranked composition — v02.11.280

## Итог

M2.2 source завершён в commit `535b0c35dc001c9d780233dfa23953986164dea9`, version `v02.11.280`; push завершился успешно, при публикации `git ls-remote origin refs/heads/main` вернул тот же SHA. WP Pusher подтвердил `Plugin was successfully updated`; Plugins показывает PHP version `v02.11.280`, Site Health — PHP `8.3.22`, а после reload существующего editor inline version — `v02.11.280`.

Live приёмка трёх целевых запросов остановлена до provider и transaction из-за неполных подтверждённых цветовых ролей сайта. Каждый Brief прошёл parse (`brief.ok=true`), но DesignPlan вернул один и тот же palette provenance отказ. Итого: 0 provider calls, 0 writes, 0 новых roots. Никакого root не публиковал, не исправлял или не удалял; guarded Undo не применялся, так как mutation не было. Baseline post 5214 до и после запросов — editor/public `[]`.

Статусы разделены:

| Контур | Статус | Основание |
|---|---|---|
| Source / local checks | PASS | Runtime commit `535b0c3`, проверки ниже |
| Push / remote | PASS на момент push | Remote `refs/heads/main` независимо совпал с runtime SHA; более поздний повторный запрос во время документирования завершился DNS `Could not resolve host: github.com` |
| Install / PHP / editor version | PASS | WP Pusher success; Plugins v280; PHP 8.3.22; reloaded inline editor v280 |
| Baseline / preservation | PASS | Свежий descriptor старой undone-operation; editor и public roots `[]`; никаких настроек/плагинов не менял |
| A automatic selection | BLOCKED_BEFORE_WRITE | Brief валиден; DesignPlan не freeze из-за palette roles; provider/write `0/0` |
| B `team.grid` | BLOCKED_BEFORE_WRITE | Brief валиден; та же palette gate; provider/write `0/0` |
| C `team.editorial_rows` | BLOCKED_BEFORE_WRITE | Тот же exact Brief, второй selector; та же palette gate; provider/write `0/0` |
| Content/native readback | NOT RUN | Принятого Plan, operation и root нет |
| Public geometry / desktop/mobile / visual composition | NOT RUN | Ни один блок не был записан; public mobile не запрашивался |
| Document restoration / Undo | PASS — baseline unchanged | Root set оставался пустым; Undo неприменим к pre-write отказам |

## Runtime изменения

Сохранён существующий pipeline `BriefIR → одно composition decision → frozen DesignPlan → ElementorIR → native compiler → validation → transaction/readback`.

- `includes/elementor/recipes.php`: общий `wpae_composition_decide()` фильтрует candidates по жёсткой совместимости, затем ранжирует допустимые записи по content metrics; хранит причины выбора/отказа и record/catalog provenance.
- `includes/llm/design-plan.php` и `includes/llm/llm.php`: решение передаётся в accepted Plan до freeze; Services не решает композицию повторно.
- `includes/elementor/compose.php`: no-write typed preview строит выбранную и ограниченные альтернативы через Plan/IR/compiler validators и сравнивает topology signatures без ID/color/copy.
- `includes/design/token-resolution.php`: bundled WPAE defaults и неизменённые stock Elementor system colors не считаются фирменными подтверждениями; profile не подменяет брендовые цвета; недостающие semantic roles останавливают Plan; происхождение frozen colors проверяется до compiler output.
- `includes/elementor/elementor-ir.php`: компилятор отображает принятые решения, не выбирая композицию после freeze.

Package hashes обновлены штатно для семи включённых packaged files. Runtime commit содержит 12 scoped files: шесть PHP модулей, четыре тестовых файла, plugin header/version и `wpae-package.json`. Пользовательский diff `context.md` и untracked audit/screenshot файлы в runtime commit не включались.

## Сохранённые Global Colors и provenance

Я открыл Site Settings → Global Colors в существующей вкладке editor в режиме чтения. Сохранённые WPAE palette значения совпадают с bundled defaults и поэтому v280 не считает их подтверждением владельца сайта.

У Elementor system colors: `Первый #000000` отличается от stock Primary и подтверждён как primary; `Второй #54595F`, `Текст #7A7A7A` и `Акцент #61CE70` совпадают с stock Elementor и исключены как не подтверждённые. Пользовательские saved colors видны как `Желтый #FFD618`, `Новый глобальный цвет #0BF4F3`, `Синий #0D6EFF`, `bg-gray #121212`, `autopapa #EC4040` и `Белый #FFFFFF`. Они сохранены, однако не назначают все необходимые semantic roles в существующем mapping.

Для canonical Plan не подтверждены `color.page_bg`, `color.surface`, `color.text`, `color.muted`; также проверка отмечает provenance `color.border`. `color.focus` и `color.hover` могли бы наследоваться от подтверждённого primary только после успешной проверки полного плана. Код не вывел значения из профиля, template, скриншота либо внешнего вида пустой страницы. Настройки Global Colors/WPAE не редактировались. Полная безсекретная запись наблюдения: [palette-observation.json](palette-observation.json).

Это безопасный отказ, а не runtime install failure: source v280 активен, typed requests достигают DesignPlan, и fallback в legacy путь не запускается. До принятия palette roles composition selection не доходит до accepted Plan; поэтому реальный automatic `record_id`, Plan hash и compiler topology получить нельзя.

## Live baseline

Работал только на существующем `post=5214` и двух уже открытых вкладках: Elementor и переиспользуемой вкладке для read-only Plugins/Site Health/public. Новые tabs/pages/drafts не создавались.

Перед запросами editor после reload показывал пустой canvas и disabled Publish; согласно пользовательскому правилу это сохранённое user-cleared baseline. Свежий read-only descriptor последней undone operation `wpae-ab76671d039660df` показал revision `7`, `already_undone`, `write_count=0`. Public URL `/pricing-contract-live-v123/` вернул roots `[]`, CSS viewport `1232×923`, `scrollWidth=clientWidth=1232`. После всех запросов editor/public остаются `[]`, кнопка Publish остаётся disabled.

## A–C и первичные попытки

Сценарий A использовал byte-identical Testimonials fixture из ранее принятой матрицы, теперь с selector `Автоматически`; новый этап проверяет именно auto decision, а не заново принятую структуру. B/C используют один и тот же byte-identical Team Brief и два разных существующих selector values. Hashes запросов приведены в [acceptance-matrix.json](acceptance-matrix.json).

| Case | UI choice / Brief | Request ID | Operation/root/revision | Result |
|---|---|---|---|---|
| A | Testimonials, Automatic, profile default; `brief.ok=true` | `72c87a3b-d008-457f-9879-d43eaa604604` | Не создан / `—` / `—` | `confirmed_palette_role_missing` для page_bg/surface/text/muted; `resolved_visual_contrast`; unresolved provenance для page_bg/surface/text/muted/border. Provider 0, write 0. Record decision не frozen. |
| B | Team, `team.grid`, profile default; `brief.ok=true` | `e02c02cc-7ebf-4aa7-9482-c8cacfb17491` | Не создан / `—` / `—` | Те же missing roles и provenance errors. Provider 0, write 0. |
| C | Team, `team.editorial_rows`, тот же fixture/hash и profile; `brief.ok=true` | `838ed32c-a917-44b1-927c-8ffdb6b0ee54` | Не создан / `—` / `—` | Те же missing roles и provenance errors. Provider 0, write 0. |

Для A были два дополнительных невошедших в матрицу генерации вводных запроса, оба до записи. A-input-0 был написан свободным текстом с тремя testimonial items; parser оставил unbound content fields и вернул `testimonials_items_out_of_range`, `entity_layout_policy_invalid` и `composition_no_compatible_candidate`, плюс palette errors; provider/write `0/0`. Для изоляции composition от language parsing вместо повторения этого brief использован известный валидный exact fixture. A-input-1 затем оказался 4,464 UTF-8 bytes; plugin chat ответил `Сообщение слишком длинное.` до typed diagnostic, без request/operation ID. Оба точных запроса и JSON отказа сохранены в `fixtures/` и `A-attempt-0-diagnostic.json`.

Публичная проверка DOM/цветов созданных блоков не проводилась: native controls и public blocks отсутствуют. Ни один screenshot этого отчёта не объявлен генерацией или public acceptance.

## Screenshots

Browser Use на существующей editor вкладке вернул JPEG bytes; файлы сохранены через `Node fs/promises.writeFile`, сигнатуры проверены, JPEG преобразован `sips`, PNG проверены через `file` и `sips`, затем открыты и визуально осмотрены. Эти кадры показывают отказ до записи; источник — **editor**, CSS viewport `1232×923`, PNG raster `1232×923`. Public screenshots для block acceptance отсутствуют, поскольку root не возник.

### A — accepted Brief, palette refusal

Post `5214`; request ID `72c87a3b-d008-457f-9879-d43eaa604604`; operation/root/revision — отсутствуют; editor CSS viewport `1232×923`; PNG `1232×923`.

![Editor A: v280 palette refusal before transaction, post 5214, editor CSS 1232×923, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-content-ranked-composition-v280/screenshots/A-paletterefusal-editor-v280.png)

[Скачать PNG A — отказ до записи](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-content-ranked-composition-v280/screenshots/A-paletterefusal-editor-v280.png)

PNG SHA-256: `1116eb898e847071a73140b923e9fd7ca3db9d9fe1ae307e26ecb480cce205cf`.

### B/C — same Brief, Team selectors, palette refusal

Post `5214`; C request ID `838ed32c-a917-44b1-927c-8ffdb6b0ee54`; B request ID `e02c02cc-7ebf-4aa7-9482-c8cacfb17491`; operation/root/revision — absent for both; editor CSS viewport `1232×923`; PNG `1232×923`. The visible selector is `team.editorial_rows`; full B/C diagnostics are separate JSON artifacts.

![Editor B/C: Team alternative request refused before transaction, post 5214, editor CSS 1232×923, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-content-ranked-composition-v280/screenshots/B-C-palette-refusal-editor-v280.png)

[Скачать PNG B/C — отказ до записи](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-content-ranked-composition-v280/screenshots/B-C-palette-refusal-editor-v280.png)

PNG SHA-256: `dd2667563a029fd6d6c72ea609728d0d20e9bba46bdb7246c87ca88148663d04`.

## Локальные проверки и публикация

Перед runtime commit прошли:

- `php tests/design-pipeline-contract.php` — 923 checks PASS;
- `php tests/flex-generation-runtime.php` — 1449 checks PASS;
- `php tests/elementor-patch-guard.php` — PASS;
- `node --test tests/*.test.js` — 18/18 PASS;
- PHP lint всех 10 изменённых PHP/test files — PASS;
- imported-template catalog — 158 manifest / 156 available trees / 156 previews;
- `php docs/audits/2026-09-12/package-probe.php` — 253 hashes, 0 mismatch;
- `git diff --check` — PASS.

Runtime version raised once to v280 and packaged hashes updated. Commit `535b0c35dc001c9d780233dfa23953986164dea9` был pushed; независимое remote `refs/heads/main` совпало с ним в release step. Повторный `git ls-remote` при документировании позже завершился DNS error `Could not resolve host: github.com`; это не отменяет первоначальную проверку release SHA, но не подтверждает текущий remote после ещё не выполненного документационного push.

## Root set и лимиты

Финальное состояние post 5214: editor/public roots `[]`; никакого test root нет. Document baseline не восстанавливался Undo, потому что он не менялся. Приёмка content ranking, accepted Plan, topology alternatives, native signatures, render geometry, screenshots public desktop/mobile и visual quality остаётся **BLOCKED / NOT RUN** до подтверждения требуемых semantic palette roles. Сама installation и zero-write guard принимаются; три композиции не принимаются как generated blocks.
