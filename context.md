# WP AI Executor — context

Последнее обновление: **2026-09-28 07:29 +05:00 (Asia/Almaty)**.

## Текущая версия и состояние Services на post=5214

- Source и `origin/main` исходно совпадали на `be5e3c121405ea9522f026f2b040118a84597ed2`. Runtime release v02.11.178 — commit `de45db889a7fcb52c8951605c6d2b1f48cac3eef`, запушен в `origin/main`; WP Pusher сообщил об успешном обновлении, Plugins UI подтвердил `v02.11.178`.
- Уже открытый editor не перезагружался после установки и его inline config всё ещё `v02.11.177`. Browser Use read-only snapshot от 2026-09-28 07:26 +05: CSS viewport editor 1203×923; preview root `2fc6b48`, pill `eac6890`, group `c0f8cfb`, cards wrapper `4581453`, cards `64c85b0`, `8d98dc9`, `2ce81e5`. Три пары услуг видны; preview DOM не является saved `_elementor_data` readback.
- Предыдущая проблема Services — неодинаковые native overlay/radius/border, бледные/отсутствующие фото и один mobile radius `0`. Новый compiler path строит Flex card с native Image сверху и отдельной светлой непрозрачной текстовой областью; использует текущие разрешённые Unsplash media refs и сохраняет явные пользовательские media overrides.
- Общая native-control validation принимает opacity 0–1 scalar/slider, не преобразует `55` и принимает известную форму пустого unset slider `{unit:px,size:"",sizes:[]}`. Исправлены два импортированных exports, которые ошибочно отсеивались: `block-feature-grid.json`, `page-home.json`; локальный каталог снова содержит 155/155 доступных деревьев.
- Patch boundary сохраняет `expected_before_hash`, operation identity и target guards. Behavioral checks покрывают stale hash 409 без записи, idempotent повтор без второго write, scoped target, before-snapshot/Undo и конфликт соседнего изменения.
- Последний подтверждённый server readback из предыдущего среза: `_elementor_data=[]`, hash `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`; durable ledger для предыдущей generation/patch traces не подтверждён. В этом проходе fresh canonical readback/ownership не получены; live save/patch/reload не выполнялись. Поэтому post-v178 screenshots и desktop/mobile visual acceptance остаются BLOCKED.
- PNG в `docs/audits/2026-09-28-v177-services-repair/` — только прежний v177 baseline, не приёмка v178. Новых pages/drafts/roots не создавали.

## Исторический baseline Services v177 (2026-09-28 04:47 +05:00)

- Source HEAD: `be5e3c121405ea9522f026f2b040118a84597ed2`, branch `main`, исходник v02.11.177. После обычного reload существующий Elementor editor inline config также показывает v02.11.177.
- На существующем post=5214 public DOM после reload содержит единственный root `2fc6b48`; в нём pill container `eac6890` и три карточки `64c85b0`, `8d98dc9`, `2ce81e5`. Pages/drafts/roots не добавлялись/не удалялись.
- До repair свойства карточек были неодинаковыми: пропавшие/слабо видимые фото, повторённая звезда, radius 16/16/0, разные border/overlay. У первой карточки были некорректные Elementor CSS values и непрозрачный overlay. В одном раннем UI target мышь выбрала pill вместо карточки; pill затем восстановлен. Далее target выбирался клавишей Enter в точной строке Navigator с проверкой `data-id`.
- Через WPAE patch path карточки получили разные Unsplash background images и иконки lightbulb / drafting-compass / clipboard-check; у всех border 1px #D7DCE2, radius 16px и белый overlay 0.65. Pill «УСЛУГИ» снова capsule.
- Финальный отдельный mobile patch `wpae-20260927234100-7de3195c` адресовал только карточку `2ce81e5` и исправил mobile radius с 0 на 16px. После reload editor mobile preview: iframe viewport 360×736, карточки сложены колонкой около 313×158.6px, gap около 16px, `scrollWidth=clientWidth=345`; горизонтального overflow нет. Public mobile не проверялся.
- Public desktop после reload: viewport 1280×720, три карточки в row около 397.3×170.4px, gap 20px, `scrollWidth=clientWidth=1280`. Точный copy сохранён. Отдельный `_elementor_data`/durable-ledger readback отсутствует; operation IDs — UI trace IDs, не доказательство ledger completion. UI Vision остаётся advisory, не operation-bound acceptance.
- Проверенные кадры: public desktop PNG 1280×720 и editor mobile PNG 1144×923 (встроенный CSS viewport 360×736). Файлы проверены как PNG и открыты:
  - `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-28-v177-services-repair/services-public-desktop.png`
  - `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-28-v177-services-repair/services-editor-mobile.png`
- Runtime code/package не менялись; новый release/commit/push не выполнялись. PHP/Node/runtime/package tests не повторялись; `git diff --check` выполнен после правки документов. Незакоммиченные и untracked файлы других работ сохранены.
- На текущем public document других семей блоков нет; этот срез принимает только Services. Подробные evidence и ограничения: `LUNA_HANDOFF_REPORT.md`.

## Скриншоты через Browser Use

Для live-проверки используй Browser Use на уже открытых вкладках существующей страницы: capture bytes → проверить MIME/signature → JPEG конвертировать в PNG через `sips` → открыть и проверить → вставить inline и абсолютную ссылку. Указывать реальный CSS viewport отдельно от размеров внешнего PNG, post/root/operation IDs и editor/public source. Не считать размер editor shell viewport-ом iframe и не называть editor mobile public mobile.

## Исторические наблюдения до 2026-09-28

Разделы ниже сохранены как исторические и не описывают текущую версию/root.

### Исходники и активный editor — исторический срез v171

- Репозиторий /Users/diasmazhenov/vibecode/wp-ai-executor, branch main, source HEAD 5ff2f1c55af72cae47887271ff537d35a5ebbb26, source/runtime v02.11.171.
- WP Pusher installation подтверждена ранее; текущая editor inline config и видимый chat показывают pluginVersion=v02.11.171, postId=5214, model openrouter/free, ready=true. Встроенный script добавлен inline через elementor-editor; отдельного JS URL нет. Elementor editor assets показывают 4.1.1.
- Код во время текущего live прохода не менялся. Runtime tests из предыдущего v171 release были успешны; сейчас повторно запускались только документные проверки.

## Services live на существующем post=5214

- Запрос: library-only Services с точными заголовком/вводным текстом и тремя title/body парами. UI route library_agent, archetype services. Диагностика одновременно сообщает library_applied=true и конечную команду deterministic_fallback variant 29; два repair-ответа были отклонены. Live template ID отсутствует. Поэтому библиотечный выбор не принят как доказанный.
- UI сообщил write HTTP 200 для operation wpae-20260927161915-412fe84f, root ab47082, и три пары текста 8/8 проходят content fidelity. В public DOM после reload виден один top-level root ab47082.
- В editor inline config серверный helper wpae_get_elementor_data_for_post() прочитал _elementor_data как [] (hash 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945). Поэтому сохранение root в каноническом post meta не подтверждено, хотя editor canvas/public DOM его показывают. Причина расхождения не установлена; не считать HTML cache или autosave доказанной причиной.
- В config находится другой written operation wpae-2ca8292fb0a142e8, revision 5, root eb0103a; его target stale/root_missing, не reviewable. Чат сообщает Выделение: нет; безопасный targeted repair root ab47082 недоступен. Никакого повторного insert, replace, reconcile или ручного Elementor edit не было.
- Public viewport при DOM-проверке 1201×923, DPR2; один root, 0 image elements, 32 Elementor nodes всего. Карточки идут в колонку, первая с чёрно-серым gradient и низким контрастом; изображения и финальная композиция FAIL.
- Внутри Browser Use evaluator fetch и XMLHttpRequest отсутствуют; GET ledger lookup для новой операции не отправлен. Не использовался альтернативный транспорт.
- Public mobile BLOCKED на доступной viewport API; editor mobile не подтверждён — device toolbar оставался Computer. Чат показывает advisory Vision 88/95 без operation-bound evidence.

## Unsplash

По прямому указанию пользователя картинки берутся из Unsplash, без WP Media Library. Проверены официальные источники, страницы указывают бесплатное использование по Unsplash License:

- Planning/blueprints: https://unsplash.com/photos/man-in-blue-jacket-holding-blueprints-near-modern-building-eLmmiLBMkv0
- Architecture/interior: https://unsplash.com/photos/modern-architectural-interior-with-curved-white-walls-wDgzO5XLZT8
- Project supervision: https://unsplash.com/photos/two-construction-workers-review-plans-on-a-tablet-gyrKtgqMChY

Кадры подходят как иллюстративные стоковые фото, не как подтверждение проектов студии. Они ещё не применены к root и не скачивались/не загружались.

## Evidence и release

- Screenshots после generation/reload находятся в docs/audits/2026-09-27-v171-services-live/. Все три открыты и проверены; public top+cards охватывают блок, editor frame показывает native canvas и UI overlay. PNG: public 1142×923, editor 1141×923; CSS viewport отдельно указан в LUNA report. Это доказательства текущего render, не успешной визуальной приёмки.
- Runtime release остаётся v171, source HEAD выше. Push/install нового релиза в этом проходе не требовались и не выполнялись.
- Предыдущие v171 verification: 447 runtime checks, 246 design-contract checks, 157 manifest/155 retrievable/155 instantiated, Node 6/6, package 248 files/0 hash mismatch, PHP lint; в этом проходе они не повторялись.
- В git status изменены только LUNA_HANDOFF_REPORT.md и context.md; текущие PNG и ранее существовавшие untracked artifacts остаются незастейдженными. Commit не создавался.
- Процедура screenshots остаётся: Browser Use текущего существующего post → capture bytes → формат → JPEG→PNG через sips при необходимости → открыть/проверить PNG → inline + абсолютная ссылка; указать post/root/operation, actual CSS viewport, editor/public.
