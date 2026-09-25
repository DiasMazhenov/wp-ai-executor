# WP AI Executor — аудит и адаптация Elementor-композиций

Обновлено: **2026-09-25 19:37 +05:00 (Asia/Almaty)**. Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`. Целевая существующая страница: `post=5214`. Live-приёмка не завершена: сохранённый server document не подтверждён, поэтому запись блокирована защитой от потери roots.

## Результат

В `/Users/diasmazhenov/Downloads/elementorpro-temp` найдено 16 ZIP-файлов, из них 15 уникальных: второй Aquassi ZIP совпадает по SHA-256 с первым (`5a7509519da3caf4ba8438219fe1420ed2c73192a4c7e3be6dafb9c51ad8378a`). Все разобранные архивы содержат реальные Elementor JSON exports. Десять уникальных kit сопоставлены по названию и template manifest с записями в `/Users/diasmazhenov/Downloads/ElemKits-main`; пять оставшихся архивов такой связи в локальном каталоге не имеют.

Для четырёх семейств — hero, преимущества, pricing и FAQ — исходные композиции адаптированы к уже существующим `BriefIR → DesignPlan → ElementorIR → native compiler`, без импорта чужих JSON/медиа, нового write path или новой библиотеки. Четыре exact prompt-fixtures прошли локальный production `wpae_llm_chat_request()` при обоих flags `active`: route `pipeline`, 0 provider calls, 1 write в in-memory WordPress harness на фейковом `post_id=42`. Local write не изменял сайт.

На `post=5214` не запускалась генерация: последнее доступное наблюдение было editor preview `[]` при public DOM с roots `82b88e5`, `de395b6`, `d729d84`; свежий авторитетный `_elementor_data` readback отсутствует. Сохранение пустой/неполной editor model могло бы удалить видимые roots. Поэтому live roots, operation/root IDs новых дизайнов, save/reload, DOM review, Vision и их desktop/mobile screenshots отсутствуют. Live-блоки и страницы этим запуском не создавались.

## Источники и лицензия

Локальный [README ElemKits](</Users/diasmazhenov/Downloads/ElemKits-main/README.md>) утверждает, что Elementor kits, доступные на сайте, распространяются по **CC0 1.0**. У отдельных ZIP `manifest.json` нет поля лицензии; связь архива с сайтом-каталогом подтверждена совпадающими названием kit, template names и каталоговой download metadata, а сама CC0-атрибуция остаётся заявлением README, не отдельной подписью каждого ZIP. См. [CC0 1.0 deed](https://creativecommons.org/publicdomain/zero/1.0/) и [официальный legal code](https://creativecommons.org/publicdomain/zero/1.0/legalcode.en).

Даже при этом исходящие фотографии, шрифты и другие внешние assets не переносились: их отдельные права в этих архивах не установлены. В плагин не добавлены чужие JSON, фотографии, шрифты, tracking, IDs, внешние ссылки или глобальные Elementor references.

| Адаптированное семейство | Источник и найденная композиция | Структура и зависимости исходника | Что использует WPAE |
|---|---|---|---|
| Hero | [18Holes catalog record](https://elemkits.lemonsqueezy.com/checkout/buy/8027ec69-b28d-4b73-9089-b937728a4ef8), [homepage preview](https://templatekits.themewarrior.com/18holes/template-kit/homepage/), `18holes...zip`, `templates/homepage.json`, секция `Section: Hero` | Legacy Sections/Columns; native `divider`, `heading`, `button`; remote golf background photo, parallax/motion effects и global color/typography refs. В manifest homepage помечена Elementor Pro required; responsive values есть для tablet/mobile. | Используется порядок eyebrow/divider → heading → CTA. Фото, motion effects, global refs и legacy IDs удалены; итог компилируется существующим Hero-путём в native Flex и использует только точный prompt и project tokens. Media отсутствует, если пользователь не дал собственный asset. |
| Преимущества | [Akademy catalog record](https://elemkits.lemonsqueezy.com/checkout/buy/3fa78633-b5d5-4a4c-9684-b30f099b97e3), [feature boxes preview](https://a.catand.us/akademy/template-kit/block-feature-boxes/), `akademy...zip`, `templates/block-feature-boxes.json` | Legacy Sections/Columns; `icon`, `heading`, `text-editor`, `button`, `spacer`; manifest `elementor_pro_required=false`, tablet/mobile values; в выбранном блоке нет внешних фото, custom CSS, dynamic tags или global refs. | Новый typed `benefits` plan: 2–6 явных пар «преимущество + описание», опциональные label/title/body и CTA. Native Flex cards с Icon/Heading/Text Editor/Button; мобильная колонка. Незакрытая пара отклоняется, текст/факты из prompt не дополняются. |
| Pricing | [Akademy catalog record](https://elemkits.lemonsqueezy.com/checkout/buy/3fa78633-b5d5-4a4c-9684-b30f099b97e3), [pricing boxes preview](https://a.catand.us/akademy/template-kit/block-pricing-boxes/), `akademy...zip`, `templates/block-pricing-boxes.json` | Legacy Sections/Columns; native `heading`, `divider`, `button`, `spacer`; три карточки, Pro не требуется, tablet/mobile значения есть, внешних медиа/динамики/custom CSS/global refs в блоке не найдено. | Сохранён существующий pricing plan/compiler. Тестируются три повторяемые карточки; суммы, подписи и CTA/URL должны прийти из запроса. Desktop row, mobile stack. Содержимое/цены исходного kit не копируются. |
| FAQ | [18Holes catalog record](https://elemkits.lemonsqueezy.com/checkout/buy/8027ec69-b28d-4b73-9089-b937728a4ef8), [FAQ preview](https://templatekits.themewarrior.com/18holes/template-kit/faq/), `18holes...zip`, `templates/faq.json` | Legacy Sections/Columns; два native `accordion`, headings, dividers и spacers; manifest FAQ `elementor_pro_required=false`, responsive tablet/mobile controls есть. Обнаружены global color/typography refs; внешних URL, custom CSS и dynamic tags не найдено. | Новый typed `faq` plan собирает один native Elementor Accordion только из полных точных Q/A-пар, сохраняет короткий label `FAQ`, optional title и CTA/URL. Global refs не импортируются; недостающий ответ отклоняется, а не генерируется. |

Структура выбранных исходников не переносится как Elementor tree: все четыре используют legacy Section/Column exports; новый результат формирует уже имеющийся local native compiler. В тестовом выходе нет чужих element IDs или library/global references.

## Матрица кандидатов

| Кандидат/семейство | Статус | Подтверждённая причина |
|---|---|---|
| 18Holes hero | Адаптирован локально | Композиция из core widgets полезна; Pro motion и внешний background asset исключены. |
| Akademy feature boxes | Адаптирован локально | Реальный native повторяемый grid; поля переведены в typed content pairs. |
| Akademy pricing boxes | Использован текущим plan/compiler | Реальные карточки подтверждают семейство; существующий pipeline уже поддерживает pricing. |
| 18Holes FAQ | Адаптирован локально | В archive есть native Accordion; содержание и ссылки берутся только из запроса. |
| Akademy testimonial boxes | Отклонён как готовый source | В export четыре внешних портрета и текст отзывов; отдельные права на фото и подтверждённость отзывов отсутствуют. Эти assets/copy не использовались. |
| Alcor Block Hero | Отклонён | JSON содержит пустые legacy section/column nodes с внешними фоновыми фото и motion effects, без native copy widgets. |
| EasyLanding / App Showcase – How It Works | Отклонён | Export есть в папке, но kit не сопоставлен с ElemKits catalog/license; manifest требует Pro, внутри есть external media URLs. |
| AppRaxx blocks | Отклонены | Встречаются `stax-*` widgets; каталоговая/license связь локально не подтверждена. |
| Остальные совпавшие/unmatched kits | Не переносились | Формы/сторонние widgets, внешние фото или неподходящий компонентный scope; полный набор ZIP сохранён без изменений. |

Из десяти каталоговых совпадений: 18Holes, Adopt, Akademy, Alaya, Alcor, Apper, Apprista, Apptom, Aquassi, Aquavist. Без подтверждённой локальной ElemKits-записи: Albion, Aleos, EasyLanding/Applanding, AppRaxx, Aquila. Пять unmatched архивов не объявлялись CC0-источниками.

## Production route и реализованные исправления

`wpae_llm_chat_request()` получает archetype существующим classifier-ом и выбирает route через `wpae_design_generation_route()`. `pipeline=active` имеет приоритет над EDDE: при `pipeline=active / EDDE=active` один запрос не запускает параллельно EDDE и provider; он строится детерминированно локально. В тестах для каждого из четырёх блоков фактические diagnostics были `action_path=pipeline`, provider calls — 0, writes — 1; существующий transaction/write boundary не менялся.

Подтверждённые изменения:

- `includes/llm/brief-ir.php`: новые FAQ/benefits roles; точное извлечение английского `Feature description`; explicit section heading больше не теряется за словом «этапы» из описания; parser provenance version увеличен до `wpae-brief-parser-v2`.
- `includes/llm/design-plan.php`: FAQ и benefits подключены к существующему `DesignPlan v1`; Q/A и feature pairs связываются по source order. Неполные Q/A, непарные features и менее 2/более 6 feature cards отвергаются. Optional CTA refs проходят через существующий button compiler.
- `includes/elementor/elementor-ir.php`: новые планы собираются native Accordion/Icon и существующими Heading/Text Editor/Button/Flex. Короткий label отображается как редактируемый eyebrow; explicit CTA/URL сохраняются.
- `includes/elementor/capability-registry.php`: `accordion` и `icon` допускаются только как явно поддерживаемые compiler widget types и всё равно проходят runtime availability gate.
- `includes/llm/llm.php`: faq/benefits подключены к eligibility существующего active pipeline; единственный write path остался прежним.
- `tests/design-pipeline-contract.php`, `tests/flex-generation-runtime.php`: production path проверяется реальными функциями плагина на in-memory WP/Elementor harness; локальный runtime не равен live WordPress.
- `wp-ai-executor.php`: source version `v02.11.154`; `wpae-package.json` hashes пересчитаны после изменений.

### Exact local harness requests

Это inputs behavioral tests, не live пользовательские генерации:

- Hero: `Создай hero. Надзаголовок: «ТИХАЯ ФОРМА». Заголовок: «Пространство для идей». Описание: «Опишите задачу и получите понятный первый шаг». Кнопка: «Начать проект», ссылка #contact.`
- Pricing: `Создай pricing. «Старт» — «от 50 000 ₸» — «Для небольшой задачи». Кнопка: «Выбрать Старт», ссылка #start. «Проект» — «от 150 000 ₸» — «Для комплексной работы». Кнопка: «Обсудить проект», ссылка #project. «Поддержка» — «от 80 000 ₸/мес» — «Для регулярных задач». Кнопка: «Подключить поддержку», ссылка #support.`
- FAQ: `Создай FAQ`, две полные Q/A-пары, label `FAQ`, кнопка `Задать вопрос` со ссылкой `#contact`.
- Преимущества: `Создай блок преимуществ`, три пары exact title/description и кнопка `Узнать больше` со ссылкой `#details`.

Для всех строковых fixtures local compiler сохранил native widget content; новый WordPress root ID и постоянный operation ID не выдавались. In-memory вызовы используют фиктивную страницу harness (`post_id=42`) и не являются live page acceptance.

## Проверки и release evidence

- `php tests/design-pipeline-contract.php` — **181 checks OK**.
- `php tests/flex-generation-runtime.php` — **357 checks OK**.
- `node --test tests/*.test.js` — **4 passed, 0 failed** после финальных изменений.
- `php -l` для всех 8 изменённых PHP-файлов — **без syntax errors**.
- `git diff --check` — **PASS**.
- `wpae-package.json` пересобран; проверка SHA-256 — **90/90 файлов совпали**.

Исходный checkout был branch `main`, base HEAD `898d20b6a6ac8a465298d2a0709d709bd43ba592`, source/live version `v02.11.153`. После локальных runtime-изменений source version — `v02.11.154`. `git ls-remote origin refs/heads/main` завершился `Could not resolve host: github.com`; актуальный remote HEAD поэтому неизвестен. Commit/push/install ещё не подтверждены; Plugins UI ранее показывал установленный `v02.11.153`, а текущая editor version не перепроверялась. Source, remote и live считаются отдельными слоями доказательств.

## Live safety, roots и screenshots

Последнее доступное read-only наблюдение `post=5214` от **2026-09-25 около 11:26 +05** было вкладочным, не свежим server readback:

| Источник | Последний известный набор | Ограничение |
|---|---|---|
| Editor preview DOM | `[]` | Это preview snapshot, не экспорт editor model и не saved document. |
| Public DOM | `82b88e5`, `de395b6`, `d729d84` | HTML/DOM не доказывает, что public отдаёт текущий `_elementor_data`. |
| Saved server document | Не подтверждён | Прямой актуальный readback не получен. |
| Ledger operation `wpae-66bff35d6058d8fc` / new process root `d729d84` | Ранее known state `written`, revision 4; current state не перечитан | Не использовать старый state как текущий. |

Существующий GET `/wp-json/ai-executor/v1/design-operations/target` ранее один раз завершился `net::ERR_BLOCKED_BY_CLIENT` до HTTP; status/body неизвестны. Запрос не повторялся другим транспортом. Это не доказывает HTTP endpoint rejection. На момент этой реализации новый свежий server snapshot не получен, и пустой editor source нельзя безопасно сохранить поверх документа с тремя видимыми public roots. Поэтому `post=5214` не сохранялся, не перезагружался и не менялся; FAQ, оба process roots и pricing не трогались. Новые pages/drafts/roots отсутствуют.

| Приёмка | Статус |
|---|---|
| Реальный kit JSON/metadata и структура исходных exports | PASS — выбранные ZIP разобраны локально |
| Четыре typed native compositions проходят production pipeline harness | PASS — local, in-memory |
| Сохранение в live Elementor, saved readback, операция/root IDs | BLOCKED — page source roots/revision не подтверждены |
| Editor/public save-reload, DOM geometry и соседний контент | NOT RUN — live write не происходила |
| Desktop/mobile screenshot, operation-bound Vision/design review | NOT RUN — принятых live designs не создано |
| Сохранность страницы агентом | PASS — этот запуск не выполнял WordPress mutations |

Для каждого дизайна screenshots являются **NOT RUN**, поскольку live generation остановлена до записи. Ни один baseline screenshot и source template preview не предъявляется как acceptance. Нет PNG-ссылок на созданные дизайны, так как их нет. Screenshots для реальной live-приёмки следует получать только после сохранённого результата, через текущую вкладку Browser Use, сохраняя реальные bytes в PNG, открывая каждый файл и указывая CSS viewport, source, post/root/operation IDs.

## Историческая запись

Снимки roots, подписи editor version и состояние ledger из более ранних запусков остаются историческими. В этом актуальном отчёте нет утверждения, что public/editor snapshots являются текущим серверным документом, и нет утверждения о завершённой визуальной приёмке.
