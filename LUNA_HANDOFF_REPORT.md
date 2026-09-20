# WP AI Executor — Luna handoff report

Дата отчёта: 2026-09-21 01:42, Asia/Almaty

## 1. Состояние релиза

- Рабочая папка: `/Users/diasmazhenov/vibecode/wp-ai-executor`.
- Ветка: `main`.
- Исходный HEAD этапа: `6626d6e`.
- Итоговый HEAD: `5524fc3` (`origin/main` обновлён).
- Установленная через WP Pusher версия: `v02.11.122`; редактор Elementor показал эту версию.
- Guide: `v02.05.99`.
- Тестовая запись: draft `post=5197`, «Live Process v113».
- Существующие process/pricing roots не удалялись. Marker `USER_BLOCK_KEEP_5197_UNSAVED` остался после save/reload.

## 2. Исправления

- v02.11.118 (`d90f10a`): canonical process builder сохраняет содержательные описания; явные описания проходят parsing → normalization → validation → write.
- v02.11.119 (`c81b705`): запрос «Создай отдельный блок…» распознаётся как независимая вставка, а не retry выбранного root.
- v02.11.120 (`1bfb9b5`): inline pricing parser сохраняет три quoted-пары, суммы с префиксом `от` и CTA вида «кнопка …», «ссылка …».
- v02.11.121 (`77e9300`): pricing builder отделяет месячную цену `от 80 000 ₸/мес` от описания и сохраняет заголовок блока `Выберите формат работы`.
- v02.11.122 (`5524fc3`): pricing builder сохраняет явный badge `ТАРИФЫ`; добавлены regression checks.

Кодовые изменения находятся в `includes/llm/llm.php`, тестовом harness, версии плагина и `wpae-package.json`. Ownership/unsaved protections из `0c7b474` и `be8a4b7` не ослаблялись.

## 3. Live matrix

| Сценарий | Маршрут | Operation / root | Save/reload | Vision | Статус |
|---|---|---|---|---|---|
| «Создай блок “Как мы работаем”… Добавь к каждому этапу короткое описание» | canonical, без provider-вызова | `wpae-20260920200050-e0f5e885`, root `4294478` | HTTP 200; exact labels/order and meaningful descriptions visible in editor | 95 / 98% | PASS |
| «Создай отдельный блок…» с четырьмя заданными описаниями | canonical independent insert, без provider-вызова | `wpae-20260920201239-a97301d7`, rendered root `926ca35` | HTTP 200; exact four pairs visible in editor | 95 / 98% | PASS |
| Pricing brief с `ТАРИФЫ`, 3 tiers, prices, CTAs and anchors | deterministic fallback after provider result `stop` | `wpae-20260920203934-746c61a0`, rendered root `08b8ce2` | HTTP 200; 1 element, 14 native widgets; exact block survived editor reload | 90 / 95% | PASS |

Final pricing public DOM contains exactly three `.wpae-pricing-card` cards and native links `#start`, `#project`, `#support`. The latest root text is:

- `ТАРИФЫ` → `Выберите формат работы`;
- `Старт` → `от 50 000 ₸` → `Для небольшой задачи с понятным объёмом` → `Выбрать Старт`;
- `Проект` → `от 150 000 ₸` → `Для комплексной работы от идеи до результата` → `Обсудить проект`;
- `Поддержка` → `от 80 000 ₸/мес` → `Для регулярных задач и развития проекта` → `Подключить поддержку`.

Public preview/read-back: [post 5197 public preview](https://mazhenov.kz/?page_id=5197). Старые исторические pricing roots остаются рядом с новым root по условию задачи; latest root определяется `data-id=08b8ce2` и badge `ТАРИФЫ`.

## 4. Визуальная проверка

- Process desktop: native Flex horizontal cards, badge → heading → four stages, visible Divider connectors, no clipping in the reviewed editor frame.
- Process mobile: cards stack vertically; marker rail remains readable and no horizontal overflow наблюдался у AI-generated process roots. Long marker `USER_BLOCK_KEEP_5197_UNSAVED` относится к контрольному пользовательскому root и отдельно не засчитывался как defect AI block.
- Pricing desktop: three cards are visible with aligned price/description/CTA rows; final clean editor frame showed the requested heading, badge and all three cards.
- Pricing mobile: cards stack one column; separate captures covered the first/second and third cards, including `Поддержка`; no horizontal overflow or clipping observed in the reviewed frames.
- Public DOM/read-back after reload confirms the same native content and CTA anchors.

**SCREENSHOT BLOCKED.** CUA emitted desktop/mobile screenshots for review in-session, but the available API did not provide a writable local screenshot path. Поэтому реальных файлов gallery для Markdown-ссылок нет; CUA tab ID не выдаётся за screenshot artifact.

## 5. Vision security scenarios

The required opposing capture scenarios were not run as separate live A/B experiments:

- A (text present in DOM but outside an incomplete capture): NOT RUN.
- B (text actually absent from the inspected block): NOT RUN.

The live AI Vision scores above are generation-attached visual checks, not proof of those two independent incomplete-capture security scenarios. Existing local vision-security contract checks remain passing.

## 6. Data safety and ownership

- v117 owner-retry live proof remains valid: operation `wpae-20260920194049-0ba44b51` completed for the owned root; selecting a foreign generated root returned the safe refusal «Безопасная цель повторной сборки не найдена; новый дубликат не добавлен.».
- The unsaved marker `USER_BLOCK_KEEP_5197_UNSAVED` was present before the v122 pricing generation and remained after save/reload.
- No old process/pricing root was deleted. Pricing inserts increased the saved page element count; this is intentional historical test data, not cleanup.
- No publish action was used.

## 7. Checks

- `php tests/flex-generation-runtime.php` → `flex generation runtime: 287 checks OK`.
- `node --test tests/*.test.js` → 3 suites passed: flex generation runtime, LLM chat contract, vision security contract.
- `php -l includes/llm/llm.php` → no syntax errors.
- `php -l wp-ai-executor.php` → no syntax errors.
- `git diff --check` → pass.
- Manifest hashes match current `includes/llm/llm.php` and `wp-ai-executor.php`.

## 8. Push and installation

- `d90f10a` pushed: meaningful process descriptions.
- `c81b705` pushed: independent process insertion classifier.
- `1bfb9b5` pushed: inline pricing parser and CTA preservation.
- `77e9300` pushed: requested pricing title and monthly-description split.
- `5524fc3` pushed: requested pricing badge preservation.
- WP Pusher reported `Plugin was successfully updated.` for v02.11.122.
- Final tracked tree is clean; unrelated untracked audit/report/cache artifacts remain un-staged.

## 9. Observable limitations

- Screenshot files are unavailable, so the requested screenshot gallery is blocked despite in-session visual review.
- Separate Vision A/B incomplete-capture experiments remain NOT RUN.
- The test draft contains historical generated blocks and the ownership marker by design; this report does not claim it is a clean production page.
