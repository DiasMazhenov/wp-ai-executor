# WP AI Executor — Luna handoff report

Дата отчёта: 2026-09-20 19:17, Asia/Almaty

## A. Изменения относительно прошлого отчёта

- Подтверждён общий корень прежнего дефекта: retry при вложенном выборе не
  разрешал выбранный descendant к operation-owned process-root и мог падать в
  provider/fallback append-путь с новым корнем.
- В `v02.11.115` добавлен общий recursive resolver process-root. Он различает
  root и descendant, возвращает scope корня и проверяет marker
  `wpae-generated-root` для явного retry текущей операции.
- Явный retry теперь передаёт `retry_current_operation`, сохраняет до 12
  operation-owned root IDs в session storage с TTL и после отсутствия безопасной
  цели возвращает конфликт HTTP 409 без вставки нового блока.
- Copy-only правка дочернего элемента не переводится в structural rebuild;
  запрос «ещё один блок» остаётся независимой вставкой.
- Vision capture теперь передаёт фактические scroll bounds, размеры захвата,
  page scroll, target rect и `capture_complete`; неполный crop явно помечается
  и не является самостоятельным основанием для destructive repair.
- Добавлены локальные positive/negative regressions для nested target,
  operation-owned target, foreign root, удалённого root, copy-only edit и
  independent insert. Runtime coverage выросла с 262 до 271 checks.
- EJ-130 имеет прежнее live-подтверждение для правильно выбранного root на
  v02.11.114. Новое live-подтверждение именно nested/reload маршрута в этом
  этапе не выполнено.
- Предыдущий отчёт сохранён в
  `/Users/diasmazhenov/vibecode/wp-ai-executor/LUNA_HANDOFF_REPORT-2026-09-20-pre-v115.md`.

## B. Репозиторий и версии

- Рабочая папка: `/Users/diasmazhenov/vibecode/wp-ai-executor`.
- Ветка: `main`.
- HEAD: `f9ec71c4de1d8a4ac4c72830fffa4e66bb45cc0e`.
- Runtime commit: `3bb0b92f8f4f2a3283ed42a0a25b0edbd12cded4` — operation-owned
  process retry, nested selection resolver, session ownership and Vision crop
  bounds.
- Regression commit: `f9ec71c4de1d8a4ac4c72830fffa4e66bb45cc0e` — foreign/deleted
  retry-target assertions.
- `git push origin main` подтверждён: `3bb0b92..f9ec71c main -> main`.
- PR не создавался.
- Tracked working tree чистый после коммита. Сохранены untracked
  `.DS_Store`, `.codex/`, `.openchamber/`, `docs/`, `graphify-out/`, audit
  files, `NEXT_AGENT_PROMPT.md` и старые report backups; они не добавлялись в
  runtime commit.
- Рабочая версия кода и release manifest: `v02.11.115`, guide: `v02.05.99`.
- Последняя фактически подтверждённая установленная версия: `v02.11.114`.
  Установка `v02.11.115` в WordPress/Elementor в этом этапе не подтверждена:
  push выполнен, но callable browser-инструмент для Plugins UI/Elementor
  отсутствовал.
- Только `context.md` является каноническим контекстным журналом проекта.
  `SESSION_CONTEXT.md` не создавался и не используется.

## C. Public preview

- Свежий штатный Preview для v02.11.115 не открывался: browser-инструмент
  недоступен, а произвольный URL не использовался как evidence.
- В предыдущем live этапе для draft `post=5197` открывался public draft-preview
  tab; наблюдался пустой документ с единственным текстом `desktop`.
- Статус записи, редирект, публичный root, Elementor wrapper, computed styles,
  console errors и фактический viewport для нового этапа не прочитаны.
- Причина пустого preview не доказана. Не установлено, было ли это связано с
  авторизацией, draft-preview URL, сохранением draft, скрытым root или runtime
  ошибкой WordPress/Elementor/WPAE.
- Public DOM/computed-style/screenshot acceptance: NOT RUN.
- Новые изменения исправляют ownership/retry и полноту Vision capture, но не
  содержат доказанного исправления public-preview открытия.

## D. Retry matrix

Все live-результаты ниже разделены с локальными regression assertions. Новых
root IDs на v02.11.115 не создавалось.

| № | Сценарий и ожидаемое действие | Фактический результат | IDs/сохранность | Статус |
|---|---|---|---|---|
| 1 | Выбран generated process root; `replace` существующего root | v02.11.114 live root-selected retry на post `5197` прошёл HTTP 200; v02.11.115 локальный resolver сохраняет top-level root | `ad4b9da`; один root после reload; ручной очистки в валидном проходе не было | EXISTING RESULT VERIFIED; fresh v115 NOT RUN |
| 2 | Выбран badge Heading; retry должен подняться к process-root и сделать `replace` | Исторический v02.11.114 nested selection прошёл обычный provider/fallback путь и создал duplicate; duplicate удалён вручную. v115 local resolver даёт `selection_relation=descendant` | Исторически `ad4b9da` сохранился; duplicate был удалён вручную; fresh live nested route отсутствует | HISTORICAL FAIL; LOCAL FIXED; LIVE v115 NOT RUN |
| 3 | Выбран Heading внутри карточки; retry должен использовать тот же operation root | Recursive local resolver находит descendant любого уровня и возвращает root scope; live editor не проверен | Live before/after IDs, DOM и read-back отсутствуют | LOCAL PASS; LIVE NOT RUN |
| 4 | Штатный retry после reload той же операции; `replace` без append | v02.11.114 root-selected reload/retry прошёл; v115 session storage сохраняет bounded root IDs и передаёт explicit retry flag | Исторически один `ad4b9da` после reload | EXISTING PASS; fresh v115 NOT RUN |
| 5 | Нет selection, но operation известна; `replace` по operation-owned root | В v115 JS читает operation roots из session storage, PHP требует marked root; end-to-end REST/editor route не запускался | Нет свежих IDs/read-back | LOCAL CONTRACT; LIVE NOT RUN |
| 6 | Выбран чужой пользовательский root; безопасный отказ, не replace/append | Local negative regression получает `root_not_operation_owned`; клиентский ID без server marker не принимается | User root не изменялся в локальном сценарии; live user root не создавался | LOCAL PASS; LIVE NOT RUN |
| 7 | Root удалён или устарел/изменён до retry; безопасный отказ, не append | Local negative regression получает `process_root_not_found`; существующие editor-root probes также проверяют selected-root change conflict, но не весь новый REST retry | Live deletion/change и read-back не выполнялись | LOCAL PASS; LIVE NOT RUN |
| 8 | Явно добавить ещё один независимый блок; `append` | `wpae_llm_is_independent_insert_request()` локально распознаёт сценарий; retry interception его пропускает | Live append не запускался | LOCAL PASS; LIVE NOT RUN |
| 9 | Изменить только текст дочернего Heading; `scoped edit`, не rebuild всего root | Local classifier возвращает structural=false; targeted path не вызывает canonical process rebuild. End-to-end provider patch на живом nested Heading не запускался | Live content/read-back отсутствуют | LOCAL PASS; LIVE NOT RUN |

Ownership resolver в этой версии покрывает process-root. Отдельная live и
local end-to-end проверка общей retry-границы на pricing root в данном этапе не
проводилась; pricing остаётся историческим результатом, а не доказательством
нового generic retry маршрута.

## E. Пользовательский контрольный блок

- Реальный пользовательский контейнер с уникальными Heading/Text Editor на
  этом этапе не создавался.
- Предыдущая live попытка открыла библиотеку шаблонов; доказательства создания
  контрольного блока, его ID и локального несохранённого текста отсутствуют.
- Локальный `editor-roots-probe.js` проверяет сохранение synthetic unsaved
  user root при reconciliation, но это не реальный browser editor block.
- AI-вставка рядом с реальным пользовательским блоком, repair, штатное
  сохранение, reload и Undo: NOT RUN.
- Одновременное изменение выбранного AI-root пользователем во время ожидания:
  NOT RUN live. Existing local snapshot/conflict assertions не являются этой
  проверкой.

## F. EJ-128 — полнота Vision capture

- До v115 capture использовал `getBoundingClientRect()` как width/height и мог
  отправить Vision только видимую часть длинного target.
- Теперь capture измеряет `scrollWidth/scrollHeight`, ограничивает изображение
  защитным максимумом 4000 px и передаёт фактические `capture_width`,
  `capture_height`, target scroll bounds, page scroll и bounded target rect.
- `capture_complete=false` выставляется, если защитный предел или иное
  ограничение не покрывает измеренный target. PHP сохраняет false, а Vision
  prompt запрещает уверенный destructive вывод по неполному crop и переводит
  неопределённость в minor/info.
- Объективный `text_excerpt` остаётся authoritative: содержание, отсутствующее
  и на изображении, и в objective DOM context, по-прежнему может быть
  отмечено как content-fidelity defect. Quality gate целиком не отключён.
- Сценарий «элемент есть, но за пределами неполного изображения»: новый
  browser capture/Vision response не получен, NOT RUN.
- Сценарий «элемент действительно отсутствует»: новый browser
  capture/Vision response не получен, NOT RUN.
- EJ-128: MITIGATED LOCALLY / OPEN LIVE. Runtime contract и prompt boundary
  изменены; фактическая картинка, Vision findings и repair decision этого
  этапа отсутствуют.

## G. Изменённый код

- `includes/llm/llm.php`: symptom — nested retry мог стать append; cause —
  selection IDs проверялись только среди top-level roots и ownership не
  переносился через explicit retry; change — recursive process target resolver,
  operation marker check, 409 conflict, copy-only/independent-insert split;
  regression — `tests/flex-generation-runtime.php` и chat contract; live
  result v115 — NOT RUN.
- `assets/js/elementor-llm-chat.js`: symptom — regenerate/reload очищал
  operation root IDs; cause — `liveGeneratedRootIds` обнулялся для каждого
  primary request; change — bounded session ownership, explicit retry flag and
  root IDs captured from editor sync; regression — JS contract and syntax; live
  result v115 — NOT RUN.
- `assets/js/elementor-llm-chat.js` и `includes/vision/vision.php`: symptom —
  Vision мог оценивать неполный crop как полный; change — measured scroll
  bounds, capture completeness metadata and incomplete-crop prompt guard;
  regression — Vision security contract and syntax; opposite live cases — NOT
  RUN.
- `tests/flex-generation-runtime.php`: added 9 checks for nested/foreign/
  deleted operation targets and intent split; final runtime result 271 checks.
- `tests/llm-chat-contract.test.js`, `tests/vision-security-contract.test.js`:
  added source contracts for retry ownership and capture completeness.
- `wp-ai-executor.php`: version/header `v02.11.115`.
- `wpae-package.json`: SHA256 refreshed for the four changed runtime files;
  manifest contains 79 runtime files.
- `context.md`, `ERRORS.md`: factual v115 entry and new retry/crop error entry;
  no `SESSION_CONTEXT.md` was created.

## H. Проверки и evidence

| Команда / evidence | Result | Граница |
|---|---|---|
| `php tests/flex-generation-runtime.php` | PASS, `271 checks OK` | In-memory WP/Elementor boundary; not a production server or browser. |
| `node --test tests/*.test.js` | PASS, 3 suites | Source contracts/runtime contracts; not public DOM. |
| `php docs/audits/2026-09-12/transactions-probe.php` | PASS | A01/A03/A04 mocked lifecycle assertions. |
| `node docs/audits/2026-09-12/editor-roots-probe.js` | PASS, 11 assertions | User-root preservation/reconciliation model; not live concurrency. |
| `php docs/audits/2026-09-12/cta-probe.php` | PASS | CTA pair/unsafe URL/fallback checks. |
| `php docs/audits/2026-09-12/package-probe.php` | PASS, 4 scenarios, 79 files | Valid ZIP accepted; corrupt, missing and unsafe packages rejected. Full diagnostic serialization also reports the existing malformed-UTF-8 probe field; compact summary passed. |
| PHP lint: `find . -type f -name '*.php' -not -path './.git/*' -not -path './docs/audits/*' -print0 \| xargs -0 -n1 php -l` | PASS | Runtime PHP files parse. |
| `node --check assets/js/elementor-llm-chat.js` | PASS | Syntax only. |
| `git diff --check` | PASS | No whitespace errors. |
| Release ZIP `/private/tmp/wp-ai-executor-v02.11.115.zip` | PASS, validator `ok=true`, 79 files, SHA256 `112ef129eaac816bcf36123fb2eba0ec7dbc0d82521269e2ba24d7e7ae12bba7` | Temporary local artifact; not site installation evidence. |
| `git push origin main` | PASS, `3bb0b92..f9ec71c main -> main` | Remote push only; not WordPress installation evidence. |
| Plugins UI, fresh Elementor, public preview and screenshots for v115 | NOT RUN | No callable browser tool in this session. |

WPCS, PHPStan и ESLint в checkout не настроены; результаты этих инструментов
отсутствуют. Новые screenshots/evidence files этого этапа не создавались.
Исторические screenshots v02.11.114 были показаны inline в предыдущей сессии;
доступного абсолютного файла или artifact ID для них нет.

## I. Общая матрица

| Сценарий | Последняя версия и источник | Fresh generation сейчас | Актуальность после v115 |
|---|---|---|---|
| Benefits «Почему нас выбирают» | v02.11.93, live post 4556, pair fidelity | NOT RUN | Классификация/пар не затронуты; свежего v115 PASS нет |
| Hero «Тихая форма» | v02.11.100, live read-back; локальный fallback покрыт runtime | NOT RUN | CTA/hero pipeline напрямую не менялся; v115 live не подтверждён |
| Pricing | v02.11.105, post 5171, root `b11af7a`, Vision/reload evidence | NOT RUN | Capture/retry code изменён; historical PASS не повышен до v115 |
| FAQ | v02.11.112, post 5193, deterministic fallback/read-back | NOT RUN | Generic content path не переиспытан на v115 |
| Portfolio | v02.11.112, post 5189, EJ-129 boundary repair | NOT RUN | Portfolio fix сохранён; generic pricing/portfolio retry не проверен |
| Horizontal process | v02.11.114, post 5197, root `ad4b9da`, 4 cards/3 Dividers, Vision 95/100 | NOT RUN fresh v115 | Existing root-selected evidence сохранено; nested v115 live gap открыт |
| Vertical left/alternating preservation | Local runtime only | NOT RUN live | Horizontal code path не меняет vertical builder; live proof отсутствует |
| Existing + unsaved user content | Local synthetic editor-root probe | NOT RUN live | Safety assertions сохранены; real control block отсутствует |
| Vision repair/reload/retry | v02.11.114 valid root-selected retry; nested invalid selection duplicate | NOT RUN fresh v115 | Ownership/crop logic изменена; live reacceptance отсутствует |
| Booking CTA `#booking` | Local CTA probe and prior fallback checks | NOT RUN live | URL safety code не менялся; fresh live proof отсутствует |

## J. Фактическое незавершённое состояние

- Подтверждённые открытые дефекты: public draft preview остаётся без
  доказанной причины; EJ-128 остаётся OPEN LIVE; v115 не подтверждён
  установленным на сайте; generic retry на pricing не проверен.
- Непроверенные гипотезы: пустой public preview может быть связан с URL,
  авторизацией, draft status, сохранением или runtime, но текущих данных для
  выбора причины нет.
- Не выполнены: свежая browser retry matrix 1–9, реальный user control block,
  live unsaved edit/Undo/concurrency, две противоположные Vision capture cases,
  public DOM/computed styles, screenshots v115, live install verification.
- Внешний блокер: в текущем сеансе нет callable browser/editor automation
  tool; shell tests не дают права объявлять live Elementor/public acceptance.
- Недоступные evidence: новые screenshots, public HTML, computed styles,
  console/network trace и установленная v115. Секреты, cookies, nonce и
  private payload в отчёт не включались.

Глобально относительно цели проекта мы топчемся на месте: локальная
архитектурная граница retry улучшена и проверена воспроизводимыми тестами, но
это ещё не реальный глобальный прогресс всей native Elementor-системы без
новой live/public приёмки. Нужны реальные предложения по реальному глобальному
прогрессу — изменения, которые расширяют проверенное покрытие и устраняют
общие причины, а не очередные точечные правки.
