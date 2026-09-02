# План WP AI Executor

## v02.10.77

- Исправить приоритет классификации: явный brief «Наша команда/Команда» должен
  выбирать Team до общих hero-сигналов, не ломая About с фразой «оставляем
  команде».
- Пройти lint/контрактные тесты, доставить через WP Pusher и повторить Team,
  затем Image Box и Testimonials через Browser Use со screenshot, scoped JSON,
  DOM, AI Vision и `design-taste-frontend`.

## v02.10.76

- Удалить из trusted About пустые вложенные контейнеры без видимых виджетов или
  фоновых изображений, не трогая содержательные Flex-узлы.
- Обнулить отрицательные margin по всем сторонам в сохранённой библиотечной
  геометрии, пройти lint/контрактные тесты, доставить через WP Pusher и заново
  проверить About, Team, Image Box и Testimonials через Browser Use со
  screenshot, scoped JSON, DOM, AI Vision и `design-taste-frontend`.

## v02.10.75

- Исправить маршрутизацию естественного brief про студию в About, чтобы слово
  «команде» не выбирало Team, и убрать конфликтующий Team-алиас `about`.
- Пройти lint/контрактные тесты, доставить пакет через WP Pusher и повторить
  About, Team и Image Box через Browser Use со screenshot, scoped JSON, DOM,
  AI Vision и `design-taste-frontend`.

## v02.10.74

- Заменить известные CopyElement image/image-box placeholder-URL на
  релевантные сетевые изображения по archetype, не затрагивая настоящие media.
- Пройти lint/контрактные тесты, доставить пакет через WP Pusher, повторить
  testimonials и затем проверить About, Team и Image Box через Browser Use с
  screenshot, scoped JSON, DOM, AI Vision и design-taste review.

## v02.10.73

- Исправить trusted-ветку с несколькими content-парами: после подстановки
  цитат/имен прогонять существующий library layout normalizer, чтобы убрать
  `Sample Subtitle`, `New Block Title`, lorem ipsum и слабую геометрию карточек,
  сохранив исходную композицию.
- Доставить пакет через WP Pusher и повторить testimonials, затем проверить
  остальные естественные промты блоков со screenshot, scoped JSON, DOM, AI
  Vision и `design-taste-frontend`.

## v02.10.72

- Исправить остаточный live-провал hero: бейдж должен быть внутри
  `wpae-generated-content-shell` непосредственно над первым заголовком, а не
  отдельным корнем в левом верхнем углу.
- При выборе общей оси приоритет отдавать выравниванию текстовых виджетов;
  принудительно сделать текст CTA белым в обычном и hover-состоянии.
- Обновить `context.md` и `ERRORS.md`, пройти lint/контрактные тесты, доставить
  пакет через WP Pusher и подтвердить Browser Use screenshot, scoped JSON, DOM,
  AI Vision и `design-taste-frontend`.

## v02.10.71

- Исправить live-классификацию естественного hero-промта: оффер с signup CTA
  не должен превращаться в benefits/trusted-композицию с чужим бейджем.
- Отключить trusted-preservation для hero, сохранить библиотечную композицию
  только как источник, затем применить native overlay, белую типографику и
  единое выравнивание всех элементов текстовой колонки.
- Повторить Browser Use generation со screenshot, scoped JSON, DOM, AI Vision
  и design-taste review; подтвердить, что Vision больше не видит detached badge,
  low-contrast copy и повторяющиеся корни.

## v02.10.70

- Вернуть обязательный outlined badge в trusted library-композиции и разместить
  его над сохраненным контентом, не урезая исходный дизайн.
- Для фото с несочетаемым светлым текстом включить native Elementor Background
  Overlay: черный цвет и полупрозрачность; не дублировать media в content shell.
- Рассматривать hero как самостоятельную композицию: синхронизировать
  выравнивание badge, заголовка, текста, иконок и CTA внутри текстовой колонки;
  не смешивать left/center/right и не маркировать hero как trusted только из-за
  наличия фото.
- Повторить удаление live Elementor roots через штатный Commands API с проверкой
  фактического исчезновения модели, чтобы stale-дубли не оставались в canvas.
- Пройти контрактные тесты, PHP lint, доставить пакет через WP Pusher и провести
  live generation со scoped JSON, screenshot, AI Vision и design-taste review.

## v02.10.68

- Trace the v67 live Vision failure to stale Elementor editor roots surviving
  rollback and being combined with later trusted-template insertions.
- Return server before/after top-level IDs in the insert diff and reconcile the
  open editor model against the server snapshot before realtime insertion.
- Keep the cleanup scoped to roots absent from the saved snapshot and verify
  PHP syntax, contract tests, delivery and a fresh live Vision run.

## v02.10.67

- Устранить подтвержденный live-провал Vocario Hero: белые структурные слои,
  оставшиеся после materialize/token-map, перекрывали фото и белый контент.
- Сохранять белый фон только у намеренных карточек с border/radius/shadow,
  удалять устаревшие entrance-animation settings и не переназначать цвета
  trusted-композиции через design-token map.
- Сбросить tracking корней в начале нового запроса и накапливать все корни
  текущей repair-цепочки, чтобы Vision не оставлял дубли.
- Пройти lint/контрактные тесты, доставить пакет через WP Pusher и повторить
  live generation со screenshot, JSON, DOM, AI Vision и design-taste review.

## v02.10.66

- Перенести цвет `jkit_heading` из source `st_focused_*` в native heading,
  чтобы hero-заголовок не становился чёрным на тёмном изображении.
- Ограничить экстремальные `em`/`px` размеры и отрицательные текстовые
  отступы только в trusted library typography, сохранив исходную композицию.
- Убрать узкий `23%` bento fallback и включить grow/shrink для Flex-карточек;
  затем пройти lint/контрактные тесты и повторить live generation со
  screenshot, JSON, AI Vision и design-taste review.

## v02.10.65

- Устранить повторное появление старых секций в live Elementor без отключения
  realtime-вставки: отслеживать созданные текущим запросом корни и удалять их
  перед Vision-repair или rollback.
- Оставить первую генерацию видимой сразу в открытом canvas и использовать
  штатный Commands API Elementor для удаления только отслеженных корней.
- Не скрывать ошибки rollback после провальной Vision-проверки; останавливать
  цикл только после подтвержденного отката.
- Ограничить responsive typography длинного заголовка trusted Vocario Hero,
  затем пройти lint/контрактные тесты и повторить browser generation со
  screenshot, JSON, AI Vision и design-taste review.

## v02.10.64

- Устранить повторное появление старых секций в live Elementor: при включенном
  Vision не добавлять каждый repair-root повторно в редакторскую модель, а
  показывать сохраненный preview как источник истины.

## v02.10.62

- Устранить Vision-ошибку v02.10.61: не дублировать фразы из полного title/body
  в декоративных confidence/audience/duration слотах trusted-шаблона.
- Сохранить уникальные смысловые слоты, но удалить повторяющиеся виджеты через
  существующий content-fidelity pipeline; затем повторить live generation,
  scoped screenshot, Vision и design-taste review.

## v02.10.63

- Материализовать известные Vocario `globals/colors` в явные native Elementor
  color settings, чтобы на target site не терялись CTA и контраст текста.
- Повторить live generation на чистой странице и проверить scoped screenshot,
  JSON, DOM, AI Vision и design-taste review.

## v02.10.61

- Устранить подтвержденное live-переполнение trusted Vocario Hero: второй
  прямой Flex-ребенок должен получать явную остаточную ширину вместо intrinsic
  auto, а горизонтальные отрицательные смещения не должны обрезать композицию.
- Сохранить исходные медиа, палитру и вертикальную композицию; добавить только
  безопасное сжатие/перенос Flex и короткий текстовый акцент в icon-list.
- Перед коммитом пройти lint/контрактные тесты, доставить пакет через WPPusher,
  затем повторить Browser Use generation со scoped screenshot, Vision,
  design-taste review и проверкой свежего JSON.

## v02.10.60

- Устранить подтверждённую Vision-проблему trusted Vocario-шаблонов: один
  content-only запрос не должен размножаться по всем текстовым виджетам.
- Оставить только семантические одноразовые слоты для title/body/CTA и
  компактных акцентов, удалить лишние текстовые виджеты, затем проверить
  JSON, screenshot и Vision в live Elementor.

## v02.10.59

- Устранить источник мусорной верстки в live Browser Use: после выбора
  trusted Vocario-шаблона не применять к нему `fallback_variant`, оставшийся от
  промежуточного deterministic fallback.
- Защитить executor по marker сохраненной library-композиции, удалить stale
  variant в chat pipeline, прогнать lint/contract tests, затем доставить пакет
  через WPPusher и повторить браузерную генерацию со screenshot и Vision.

## v02.10.58

- Исправить parser content-only запросов: дефис внутри слов вроде
  `Онлайн-школа` не должен распознаваться как разделитель пары
  «название - описание».
- Сохранить поддержку тире и настоящих пар с дефисом вокруг пробелов, затем
  проверить цельные content units, fallback-путь и live-генерацию через
  Browser Use со screenshot и Vision review.

## v02.10.57

- Исправить `Value of type null is not callable` в адаптации narrative-шаблонов:
  рекурсивный mapper должен явно захватывать callback компактного copy и его
  производные фрагменты.
- Закрепить захват зависимостей контракт-тестом и runtime smoke-тестом для
  trusted library template, затем повторно прогнать полный PHP lint и chat/vision
  contract tests перед push.

## v02.10.56

- Исправить Gemini action-запросы: не отправлять в Google OpenAI-compatible
  endpoint неподдерживаемые `response_format` и `max_completion_tokens`.
- Защитить `/llm/chat` от PHP `Throwable`: возвращать JSON-ошибку с безопасной
  диагностикой и сохранять исходную причину в журнале WordPress.
- Сохранить единичный reload/retry для сетевых ошибок провайдера и проверить
  Gemini payload, PHP lint и LLM chat contract до доставки.

## v02.10.55

- Добавить несколько актуальных Gemini-моделей в отдельный select в разделе
  LLM-агентов; оставить свободное поле для остальных и custom-провайдеров.
- Проверить live-переключение Gemini, автоподстановку Base URL/модели и доставить
  пакет через Browser Use.

## v02.10.54

- Добавить Gemini отдельным LLM-провайдером с официальным OpenAI-compatible
  Base URL и моделью по умолчанию в селекторе настроек.
- Проверить автоподстановку Base URL/модели в WordPress и доставить пакет через
  Browser Use, не передавая API-ключ в браузер.

## v02.10.53

- Не добавлять legacy `gap` к контейнеру, где уже задан native `flex_gap`,
  чтобы после нормализации Flexbox-шаблоны не получали второй источник spacing.
- Доставить пакет, затем повторить live Hero через Browser Use и принять его
  только после screenshot, Vision/design-taste и проверки JSON.

## v02.10.52

- Завершать зависший frontend-запрос к LLM по таймауту и передавать его в
  существующий единичный provider reload/retry, чтобы чат не оставался навсегда
  на шаге ожидания JSON.
- Повторно доставить пакет после v02.10.51 и проверить live Hero после чистого
  запуска Elementor.

## v02.10.51

- Исправить фактический live-Flex рендер доверенных Vocario-шаблонов: пустые
  вложенные контейнеры не должны получать белый фон, а source palette и CTA
  не должны заменяться общими токенами проекта.
- Доставить пакет с правильной корневой директорией, затем проверить Hero в
  открытом Elementor через content-only prompt, JSON, screenshot, Vision и
  design-taste review.

## v02.10.50

- Исправить root cause live mismatch доверенных Vocario-блоков: не оставлять
  изображения и пустые декоративные зоны после удаления англоязычного copy.
- Переписать весь текстовый слой выбранного шаблона фрагментами контента запроса,
  сохранить исходные media/Flex-композиции и проверить новый live-рендер через
  screenshot, Vision, design-taste и JSON.

## v02.10.48

- Сохранить композицию и медиа доверенных Vocario-шаблонов, но удалять из
  narrative-блоков несвязанные исходные текстовые виджеты, чтобы английские
  остатки и случайные статистики не смешивались с контентом запроса.
- Проверить live в Elementor через новый content-only prompt, screenshot,
  Vision и JSON свежего объекта.

## v02.10.49

- Адаптировать типографику только заменённых Vocario Hero heading/body под
  длину русскоязычного контента: убрать наследуемые 12em/0.9em и отрицательный
  нижний отступ, чтобы строки и CTA не пересекались.
- Повторить live content-only тест и принять результат только после screenshot,
  Vision и проверки JSON нового объекта.

## v02.10.47

- Preserve trusted bundled library geometry and styling instead of applying the
  generic generated bento/visual-grammar wrapper after retrieval.
- Map natural library content into the first semantic heading, body text and
  CTA widgets so decorative statistics and template labels remain intact.
- Expose the bundled fixture id in retrieval diagnostics for live verification.

## v02.10.46

- Исправить потерю Vocario Hero: известный `jkit_heading` нормализуется в
  native `heading` с сохранением текста, уровня и ключевой типографики.
- Проверить, что retrieval больше не заменяет Hero на `Trusted By`, затем
  повторить живую генерацию с JSON, screenshot, Vision и design-taste review.

## v02.10.45

- Исправить false-positive классификацию: слово «партнеры» само по себе не
  должно выбирать карусель, а контент школы публичных выступлений должен
  выбирать Vocario Hero.
- Обновить пакет и проверить Home, Team, Courses и Event через Browser Use с
  JSON, screenshot, AI Vision и design-taste review.

## v02.10.44

- Синхронизировать SHA-256 Vocario JSON в CopyElement manifest после перевода
  всех 393 контейнеров на Flexbox, чтобы seed/retrieval не использовал старые
  записи из БД.
- Добавить автоматическую проверку целостности fixture manifest и повторно
  протестировать Vocario через Browser Use со screenshot, Vision/design-taste
  и фактическим JSON.

## v02.10.43

- Преобразовывать явно запрошенный standalone CTA из text-editor в native
  button после визуальной нормализации, сохраняя текст пользователя.
- Повторно проверить Vocario Home и другие типы в Browser Use со screenshot,
  AI Vision/design-taste и JSON после доставки обновления.

## v02.10.42

- Явно зафиксировать `container_type=flex` во всех 13 исходных Vocario JSON.
- Усилить retrieval для Home/Courses/Event/Blog/Footer/404 и проверить
  структурный контракт всех Vocario-шаблонов.
- Доставить пакет и повторно проверить Vocario в Browser Use со screenshot,
  AI Vision/design-taste и JSON.

## v02.10.41

- Добавить прозрачную Flexbox-ленту outlined-лейблов партнёров под сохранённой
  каруселью, если имена явно присутствуют в контенте.
- Обновить пакет, доставить через WP Pusher и повторить Browser Use screenshot,
  Vision/design-taste и JSON-проверку.

## v02.10.40

- Заменить generic badge у carousel на semantic `ПАРТНЁРЫ`.
- Обновить пакет, доставить через WP Pusher и повторить Browser Use screenshot,
  Vision/design-taste и JSON-проверку карусели.

## v02.10.39

- Исправить retrieval aliases для content-only запросов о партнерах, логотипах
  и слайдерах.
- Добавить над standalone Image Carousel нативные heading/text-editor с полным
  пользовательским контентом, сохранив исходные реальные слайды.
- Доставить пакет и повторить Browser Use тест после обновления страницы со
  screenshot, Vision/design-taste и JSON спойлером.

## v02.10.38

- Добавить присланный standalone Image Carousel в private CopyElement library.
- Распознавать carousel/slider/logo/partner briefs отдельно от portfolio.
- Оборачивать валидный native `image-carousel` в прозрачный Flexbox-контейнер
  и не превращать его в bento-карточки.
- Доставить пакет и проверить content-only carousel prompt в Browser Use со
  screenshot, Vision/design-taste и JSON.

## v02.10.37

- Исправить приоритет archetype detection: «команда/специалисты» должна
  опережать слова ролей вроде «стратег», чтобы не получать benefits layout.
- Доставить пакет и проверить новый team prompt в Browser Use со screenshot,
  Vision/design-taste и JSON.

## v02.10.36

- Разбирать естественные пары имя — роль после точек, чтобы content-only prompt
  не терял карточки и не подставлял шаблонный fallback-текст.
- Доставить пакет и повторно проверить командный промт в Browser Use со
  screenshot, Vision/design-taste и JSON.

## v02.10.35

- Не принимать process-шаблон только по совпадению текста: отклонять библиотечную
  композицию с media-placeholder или недостаточным числом заголовков шагов.
- Применять общий bento/Flex-нормализатор и к принятым библиотечным блокам.
- Убрать суммирование горизонтальных отступов у структурных обёрток, сохранив
  внутренние отступы самих карточек.
- Доставить пакет и повторно проверить процессный промт в Browser Use со
  screenshot, Vision/design-taste и JSON.

## v02.10.34

- Исправить классификацию отзывов с фразами вроде «каждый шаг»: кавычки должны
  иметь приоритет над ключевыми словами процессного блока.
- Доставить пакет, повторить естественный testimonials-промт в Browser Use,
  проверить screenshot, AI Vision/design-taste и сгенерированный JSON.

## v02.10.33

- Перевести все библиотечные и bundled-шаблоны на native Flexbox, включая
  вложенные контейнеры Mega Menu.
- Перенести совместимые grid gaps/alignment в Flexbox, удалить grid-only
  настройки, мигрировать старые bundled-записи и проверить все 18 fixtures.

## v02.10.32

- Исправить мутацию массива карточки во время добавления native icon, из-за
  которой заголовок пропадал, а описание дублировалось после visual grammar.
- Доставить пакет, проверить benefits-промт в Browser Use, screenshot,
  AI Vision/design-taste и JSON с exact content и нулем `icon-box`.

## v02.10.31

- Разбирать несколько контентных пар `заголовок — описание` без технических
  инструкций и сохранять их в соответствующих карточках.
- Устранить stale-проверку типа виджета в нормализаторе заголовков и подтвердить
  native `heading`/`icon` без `icon-box` в Browser Use.

## v02.10.30

- При классификации content-only brief учитывать заголовки пар раньше
  случайных слов в описаниях, чтобы `команда` внутри copy не выбирала Team.
- Проверить повторный benefits-тест, JSON, Vision и отсутствие `icon-box`.

## v02.10.29

- Исправить устаревший SHA-256 `includes/guide/guide.php` в package manifest.
- Повторно проверить целостность всего пакета перед WP Pusher delivery.

## v02.10.28

- Распознавать естественный формат отзывов `«цитата». Имя, компания.` и
  подставлять пары в импортированный шаблон до visual normalization.
- Проверить fallback, content fidelity, отсутствие `icon-box` и live Elementor
  результат в Browser Use.

## v02.10.27

- Keep generated and imported card composition free of `icon-box`: preserve an
  existing native icon, or emit a separate native `icon` beside a native
  heading/text-editor.

## v02.10.26

- Сделать копирование JSON выбранного контейнера устойчивым к потере фокуса и
  различиям Elementor selection API: использовать activeModel и снимок
  последнего валидного выделения.
- Сразу после записи показывать свернутый JSON каждого сгенерированного
  native-дизайна до запуска Vision repair, с отдельной кнопкой копирования.
- Обновить package hash, прогнать syntax/contract проверки и подтвердить
  копирование выбранного контейнера и JSON генерации в открытом Elementor.

## v02.10.23

- Добавить CopyElement Mega Menu c3372 в private block library вместе с
  локальным preview asset и безопасной package-доставкой PNG.
- Распознавать мега-меню отдельным archetype, подставлять пункты/CTA из
  обычного контентного промта и сохранять исходную native grid-композицию.
- Подключить 13 JSON-шаблонов Vocario и их локальные JPG/PNG-превью в private
  block library; из много-секционных страниц выбирать совместимый root.
- Обновить package hash и прогнать syntax/contract и library-adaptation проверки.

## v02.10.22

- Для OpenRouter повторять structured action request без несовместимых
  `response_format/provider`, если ответ содержит generic provider error или
  `finish_reason: error`.
- Обновить package hash, локальные тесты и повторить Browser Use screenshot,
  Vision/design-taste и selected JSON проверки.

## v02.10.21

- Обобщить разбор quoted-label карточек для этапов и других контентных блоков;
  не смешивать описание последней карточки с отдельной CTA-инструкцией.
- Обновить package hash, локальные тесты и повторить Browser Use screenshot,
  Vision/design-taste и selected JSON проверки.

## v02.10.20

- Исправить разбор естественных ценовых карточек с кавычками, запятыми и
  точками с запятой, чтобы fidelity gate проверял исходный контент, а не
  ошибочные составные пары.
- Обновить package hash, локальные тесты и повторить Browser Use screenshot,
  Vision/design-taste и selected JSON проверки.

## v02.10.19

- Не рендерить пустые image-widget-заглушки в testimonial-карточках; реальные
  URL/ID изображений сохранять.
- Обновить package hash, локальные тесты и повторить Browser Use screenshot,
  Vision/design-taste и selected JSON проверки.

## v02.10.18

- Исправить выбор Testimonials-шаблона для естественных промтов с отзывами и
  не включать командный префикс в имя первого автора.
- Разделять импортированный testimonial `icon-box` на native heading автора и
  native text-editor цитаты, включая карточки внутри nested/n-carousel.
- Обновить package hashes, прогнать локальные проверки и подтвердить live-путь
  через Browser Use: промт, скриншот, Vision/design-taste и selected JSON.

## v02.10.17

- Поставить исключение testimonial перед общим правилом `icon-box` в
  generation hint, чтобы модель не выбирала блок с иконкой для имени или
  цитаты по первой инструкции.
- Обновить package hash и проверить контрактный тест.

## v02.10.16

- Убрать оставшееся противоречие из guide, LLM guide и execution trace: для
  testimonial автор/компания остаются native heading, цитата native
  text-editor, а иконка только декоративная; `icon-box` оставить для обычных
  повторяющихся карточек.
- Обновить package hashes и проверить live guide в открытом Elementor через
  Browser Use после reload, не удаляя текущие тестовые секции.

## v02.10.15

- Убрать `icon-box` из testimonial-карточек: имя и компания должны быть
  обычным native heading, цитата — native text-editor. Для остальных карточек
  сохранить контрастную белую иконку на фоне.

## v02.10.14

- Исправить приоритет классификации content-only запросов: явное «о компании»
  должно побеждать слово «командой» внутри описания, чтобы retrieval выбирал
  About-шаблон, а не Team.

## v02.10.13

- Сделать обязательный post-generation export выбранного JSON надежным в
  embedded Elementor: fallback после отказа `navigator.clipboard`, затем
  Browser Use screenshot, Vision/design-taste и сверка результата.

## v02.10.12

- Закрепить естественный парсер отзывов с разделителем `;` и общий
  нормализатор библиотеки: bounded rem-отступы, прозрачные root/bento shells,
  responsive typography для ролей и защищённая геометрия CTA.
- Перед commit прогнать syntax/contract checks и локальную матрицу всех четырёх
  fixtures; после доставки выполнить Browser Use generation -> screenshot ->
  Vision/design-taste -> selected JSON export и повторять цикл при плохом
  результате.

## v02.10.11

- Очищать устаревший Elementor `htmlCache`/`html_cache` во всех вложенных
  элементах перед записью библиотечного дизайна, чтобы preview показывал
  адаптированный контент, а не демо-разметку CopyElement.
- Обновить пакет и проверить все четыре bundled-шаблона естественными промтами
  в открытом Elementor через Browser Use, screenshot, AI Vision и
  design-taste-frontend.

## v02.10.10

- Исправить оставшийся случай, когда проектные пары с кавычками не выбирали
  portfolio, потому что классификатор смотрел только на labels пар.
- Обновить пакет и повторить live-проверку Image Box, затем Testimonials,
  About и Team через Browser Use, screenshot, AI Vision и design-taste-frontend.

## v02.10.09

- Исправить классификацию проектных промтов с кавычками: три и более проектных
  пары должны выбирать portfolio/Image Box до общего quote-признака отзывов.
- Повторно проверить Image Box и затем остальные три bundled-шаблона через
  Browser Use, screenshot, AI Vision и design-taste-frontend.

## v02.10.08

- Исправить адаптацию CopyElement testimonials: сохранять естественные пары
  «имя, компания — цитата», очищать демо-атрибуции и убрать растяжение карточек.
- Обновить плагин и проверить все четыре bundled-шаблона естественными промтами
  в открытом Elementor через Browser Use, screenshot, AI Vision и design-taste.

## v02.10.07

- Убрать дополнительную CTA-обёртку из общей visual-grammar: последняя native
  button должна оставаться прямым элементом content shell, без пустой карточной
  поверхности и растянутого ряда.
- Обновить плагин и повторить process-промт в открытом Elementor через Browser
  Use, screenshot и AI Vision после исправления структуры CTA.

## v02.10.06

- Убрать растянутую на всю ширину CTA-поверхность из общей visual-grammar:
  ряд подписи и кнопки должен быть компактным по содержимому, без лишнего
  горизонтального пустого пространства.
- Обновить плагин и повторить process-промт в открытом Elementor через Browser
  Use, screenshot и AI Vision после исправления CTA-композиции.

## v02.10.05

- Исправить классификацию естественных промтов процесса: маркеры этапов,
  запуска и передачи результата имеют приоритет над словом «команда» в
  контексте «передаем команде».
- Обновить плагин и повторить process-промт в открытом Elementor через Browser
  Use, screenshot и AI Vision после исправления классификатора.

## v02.10.04

- Заменить deterministic FAQ fallback с accordion на видимую bento-сетку
  native-карточек: вопрос с иконкой и ответ должны быть в одной карточке.
- Обновить плагин через WP Pusher и повторить FAQ-промт в открытом Elementor
  через Browser Use, screenshot и AI Vision после неудачного repair-прохода.

## v02.10.03

- Исправить разбор естественных FAQ-промтов: сохранять каждый вопрос и его
  ответ в native accordion, не добавлять остаток отдельным текстовым виджетом.
- Сохранять bounded Vision repair options и findings при provider retry после
  принудительной перезагрузки.
- Обновить плагин через WP Pusher и проверить FAQ-промт в открытом Elementor
  через Browser Use, screenshot и AI Vision.

## v02.10.02

- Ограничить сам `html2canvas` capture 12 секундами и передать ему bounded
  image timeout, чтобы зависший asset не блокировал чат без финального статуса.
- Повторить естественный контентный промт в открытом Elementor и проверить
  screenshot/Vision после доставки версии.

## v02.10.01

- Устранить зависание editor-chat Vision на незавершившемся remote image во
  время screenshot capture: завершать ожидание по `load`, `error` или bounded
  timeout.
- После публикации повторить естественный контентный промт в свежем Elementor
  и проверить screenshot/Vision до отчета.

## v02.10.00

- Исправить root cause несоответствия библиотечных шаблонов: клонировать
  полную native-карточку с уникальными ID, когда пар контента больше исходных
  зон, чтобы ни одна пара не выпадала за пределы карточной композиции.
- Заменять `New Block Title` и другие служебные заголовки данными типа блока,
  сохраняя только служебный subtitle как необязательный удаляемый label.
- Сбалансировать ширины для 2–12 карточек: максимум четыре в строке, без
  одинокой узкой карточки в следующем ряду.
- Перед публикацией прогнать fixture matrix и естественный Testimonials prompt
  в открытом Elementor через Browser Use, снять screenshot и проверить AI Vision.

## v02.09.99

- Не дублировать текст отзыва в `icon-box` автора, если quote уже записан в
  прямой native text widget; очищать демонстрационные подписи вроде `London`.
- Исправить передачу archetype в рекурсивном адаптере библиотечных карточек.
- Перед публикацией прогнать fixture matrix и четыре естественных промта в
  открытом Elementor через Browser Use, снять screenshots и проверить AI Vision.

## v02.09.98

- Убрать служебный placeholder-заголовок из импортированного CopyElement-шаблона,
  чтобы общий outlined badge оставался единственным и всегда стоял над основным
  заголовком.
- Не помечать успешно примененный библиотечный шаблон как deterministic fallback
  в шаге выполнения.
- Повторить живой тест Team и остальных трех шаблонов в свежей вкладке Elementor
  через Browser Use с обязательным screenshot и AI Vision.

## v02.09.97

- Разделить в CopyElement-нормализаторе семантические группы карточек и
  композиционные контейнеры: только реальные card-группы получают bento-сетку,
  белые поверхности и responsive-ширины; внешняя композиция остается
  прозрачной и сохраняет исходный ритм.
- Удалять пустые кнопки и placeholder-контент импортированных шаблонов,
  заменять демонстрационные подписи на локализованный контент блока и не
  допускать иконки у заголовков вне bento-карточек.
- Разрешить повторный retrieval проверенного bundled-шаблона при полной
  Vision-регенерации и не прогонять библиотечную композицию через общий
  fallback bento-нормализатор.
- Перед публикацией проверить четыре шаблона естественными промтами в
  открытом Elementor через Browser Use, снять screenshots и дождаться AI
  Vision без критических/major findings.

## v02.09.96

- Улучшить нормализацию импортированных CopyElement-шаблонов: длинные
  текстовые heading не должны раздувать страницу, фиксированные размеры и
  отрицательные отступы не должны ломать типографику.
- Привести вложенные группы карточек к native Flexbox bento-сетке с прозрачным
  контейнером, карточками максимум по четыре в ряд и адаптивными размерами.
- После выпуска проверить все четыре шаблона естественными промтами в открытом
  Elementor через Browser Use, снять screenshots и дождаться AI Vision.

## v02.09.95

- Сделать четыре переданных CopyElement-fixture доступными для обычной
  генерации только через проверенный immutable bundled-маркер; произвольные
  draft-блоки по-прежнему не применять автоматически.
- Исправить распознавание `team/about` и естественных нескольких пар
  `название — описание`, чтобы библиотечный адаптер выбирал правильную
  композицию и переносил контент в native widgets.
- Проверить все четыре шаблона естественными промтами в открытом Elementor,
  снять screenshots и дождаться AI Vision результата.

## v02.09.94

- Добавить переданный пользователем CopyElement JSON `Team c1429` в bundled
  private library.
- Зафиксировать SHA-256, native-совместимость и idempotent seeding; оставить
  шаблон draft до Design Review Gate.
- Не придумывать preview URL или preview asset, если они не переданы вместе с
  экспортом.

## v02.09.93

- Добавить в bundled private library два переданных пользователем JSON:
  `Testimonials c1198` и `About c70`.
- Распознать уже существующий `Image Box c2644` и не создавать дубликат.
- Зафиксировать SHA-256, manifest и idempotent seeding для всех трёх
  CopyElement-дизайнов; новые записи оставить draft до Design Review Gate.
- Не придумывать preview URL для двух экспортов без переданного preview asset.

## v02.09.92

- Добавить retrieval-шаг в обычную генерацию Elementor-чата: искать только
  совместимые `approved`/`published` блоки нашей private library по типу блока,
  тегам, названию, описанию и словам запроса.
- Адаптировать найденную повторяющуюся композицию под пары контента, оставить
  максимум четыре карточки и пропускать результат только после native shape и
  content-fidelity проверок.
- Не применять библиотеку к выделенным точечным правкам и Vision repair;
  вернуть безопасную диагностику `library_retrieval` без raw JSON шаблона.

## v02.09.91

- Сохранить восстановленный CopyElement JSON как bundled-шаблон нашего
  плагина: `Image Box c2644` хранится в `includes/elementor/copyelement/`.
- При первом чтении private block library идемпотентно создать запись
  `wpae_block` с JSON, URL превью, provenance и compatibility report; не
  использовать `elementor_library`.
- Показать сохраненное превью в деталях шаблона нашей библиотеки.
- Не выдавать отсутствующие payload как сохраненные: из-за сброса Browser Use
  полностью восстановлен только один JSON.

## v02.09.90

- Принимать официальный Elementor export-envelope с верхнеуровневыми
  `type`, `siteurl` и `elements` в существующей private block library через
  `X-AI-Key`, сохраняя исходный payload и native data.
- Принимать для private library данные CopyElement с `source: copyelement` и
  `preview_url`; хранить URL превью рядом с JSON без встраивания чужих image
  bytes в plugin package.
- Поднять лимит private library до 4 MB для крупных реально скопированных
  компонентов.
- Не встраивать облачные Wireframe-превью без их JSON: получить точные
  шаблоны только через официальный export/import и затем прогнать общий
  normalize/compatibility/review pipeline.

## v02.09.89

- Добавить read-only доступ к нативным Elementor Templates через `X-AI-Key`:
  список `elementor_library` и получение исходного/нормализованного
  `elementor_data` с compatibility report.
- Синхронизировать `/guide`, `/capabilities` и skill endpoint allowlist; не
  передавать ключ в браузерный Elementor JS.

## v02.09.88

- После realtime patch выбранного контейнера обновлять текущий preview-iframe до сообщения об успехе и AI Vision, чтобы CSS native-свойств (включая `border_radius`) реально применялся в canvas без перезагрузки редактора.
- Доставить новую версию и повторить естественный тест скругления выделенного контейнера в текущей Browser Use вкладке со screenshot и Vision.

## v02.09.87

- Распознавать естественные запросы «скругли/закругли углы» для выделенного Elementor-контейнера и гарантировать patch выбранного корня.
- Нормализовать scalar/partial `border_radius` в полный native Elementor dimension object до preview и записи.
- После доставки повторить естественный тест выделенного контейнера в текущей Browser Use вкладке, снять screenshot и проверить Vision.

## v02.09.86

- Вернуть автоматически добавляемому CTA-ряду ширину всей сетки без растягивания самой кнопки, чтобы действие оставалось визуально связано с testimonial-композицией.
- Повторить естественный тест отзывов в новой версии и проверить Vision после полного рендера.

## v02.09.85

- Исправить приоритет классификации: явные отзывы, имена и кавычки должны распознаваться как testimonials раньше эвристики portfolio по словам «бренд», «сайт» и «сервис».
- Повторить тот же естественный запрос отзывов в новой версии и проверить, что заголовок/CTA соответствуют смыслу контента.

## v02.09.84

- Для testimonial-карточек ограничить авторский icon-box компактной native-типографикой, чтобы имена не наследовали display-размер и не ломали карточку.
- Сделать автоматически добавляемую CTA-группу компактной: убрать растягивание `space-between`, верхнюю линию и лишнее пустое пространство.
- Доставить версию и повторить браузерный тест отзывов с Vision перед продолжением серии.

## v02.09.83

- Исправить process-нормализатор для provider-generated карточек, вложенных в дополнительный bento-контейнер: нумеровать первый заголовок рекурсивно и не терять icon-box archetype после visual grammar.
- Доставить новую версию и повторить браузерный тест процесса перед следующими естественными контентными промтами.

## v02.09.82

- Выровнять нумерацию provider-generated process-карточек в нормализаторе: сохранять текст шага, но гарантировать последовательные `01 / ...`, `02 / ...` и далее до числа реально найденных карточек.
- Повторить тест процесса после доставки версии и проверить Vision до продолжения серии контентных промтов.

## v02.09.81

- Расширить классификацию контентных брифов: три и более содержательных предложения длиной от 80 символов должны запускать генерацию даже без технических слов, CTA, кавычек и пар «название — описание».
- Повторить тест нейтрального контента в открытом Elementor и проверить, что агент вставляет блок, а не возвращает обычный текст.

## v02.09.80

- Для выбранной точечной правки ограничить Vision проверкой выбранного дерева, а замечания направлять в повторный `patch_elements`, не в полную регенерацию страницы.
- Передавать корневые ID выбранной области в editor sync и показывать patch count, размер области и измененные ID в логе чата.
- Повторить естественный тест заголовка и проверить Vision после realtime-правки в открытом Elementor.

## v02.09.79

- Исправить классификацию естественного промта «поменяй ...», чтобы точечная правка выбранного Elementor элемента доходила до `patch_elements`, а не возвращалась как обычный текстовый ответ.
- Повторить тест выбранного заголовка в открытом Elementor через Browser Use и проверить realtime-изменение без перезагрузки.

## v02.09.78

- Добавить точечную правку выбранного Elementor widget/container через естественный промт: передавать bounded recursive settings/content snapshot, разрешать backend patch только выбранному дереву и применять измененные native settings в открытом canvas без перезагрузки.
- Не маршрутизировать обычные запросы генерации вроде «сделай хиро блок» в patch-ветку из-за широких слов `блок` или `дизайн`; в чате показывать patch count, changed IDs и размер выбранной области.
- Проверить PHP/JS/contract tests, затем выполнить естественный тест выбранного widget и контейнера через Browser Use в текущей вкладке, снять screenshot и проверить AI Vision.

## v02.09.77

- CTA после bento-сетки больше не выглядит как пустая белая карточка: строка действия остается прозрачной, получает компактную подпись и адаптивно складывается на mobile; token-map защищает ее прозрачный фон.
- Повторить natural portfolio prompt в открытом Elementor и проверить равные карточки, отсутствие отдельной пустой CTA-карточки, realtime-вставку и Vision.

## v02.09.76

- Финальный bento-нормализатор теперь выполняется после fallback, native-нормализации и token-map: он удаляет конфликтующие фиксированные размеры, выравнивает карточки и не дает старому root-маркеру принять badge/content-shell за карточки.
- После исправления проверить в открытом Elementor естественный контентный prompt: равные карточки в одной строке, прозрачная сетка, отдельный CTA и приемлемый Vision score.

## v02.09.75

- Provider errors returned as REST 502 or `finish_reason: error` now enter the same single reload-and-retry path, so a transient failure does not stop a natural browser test before generation.
- Re-run the portfolio prompt in the fresh Elementor tab and verify realtime insertion, equal cards, connected CTA, screenshot, and Vision.

## v02.09.74

- После визуальной грамматики CTA помещается в отдельную прозрачную нижнюю строку с разделителем, а все уже созданные bento-сетки проходят финальную идемпотентную нормализацию. Это убирает оторванные кнопки и композиции с одной oversized-карточкой.
- Повторно проверить естественный portfolio prompt в открытом Elementor: равные карточки, связанный CTA, screenshot целевого блока и Vision без detached CTA finding.

## v02.09.73

- Провайдерские и созданные после оборачивания bento-сетки проходят общий нормализатор: максимум четыре равных карточки в строке, без oversized lead-карточки и с 100% шириной на mobile.
- Vision отбрасывает только ложные finding о пропавшем CTA, если точная фраза подтверждена `text_excerpt`; реальные проблемы положения, отступов и композиции остаются blocking feedback для полной регенерации.
- Повторно проверить natural portfolio prompt в открытом Elementor: равные карточки, сохраненный CTA, screenshot целевого блока и Vision без ложного content-fidelity finding.

## v02.09.72

- Уже существующие `wpae-bento-grid` теперь принудительно нормализуются после ответа провайдера: контейнер остается прозрачным, карточки получают сбалансированные ширины по количеству элементов и 100% на mobile.
- Повторно проверить естественные portfolio, process и pricing промты в открытом Elementor и убедиться, что провайдерские lead-ширины не сохраняются.

## v02.09.71

- Content-only набор из трёх подписанных кейсов теперь распознается как `portfolio`, даже если CTA в конце содержит слово «заявка».
- Повторно проверить естественный portfolio prompt: три карточки должны сохранить пары контента, а `Обсудить проект` должен остаться отдельной кнопкой.

## v02.09.70

- Content-only fallback теперь применяет извлеченный CTA рекурсивно ко всем native-кнопкам блока, включая кнопку после bento-сетки.
- Повторно проверить естественный portfolio prompt и убедиться, что CTA `Обсудить проект` сохраняется без content-fidelity ошибки.

## v02.09.69

- Vision ждёт устойчивую отрисовку после фокуса на новом блоке перед screenshot, чтобы не отправлять модели промежуточный canvas.
- В Vision prompt объективный `text_excerpt` объявлен источником истины для наличия контента; отсутствующий в screenshot текст не считается потерянным, если он есть в target DOM.
- Проверить тот же content-only process prompt в открытом Elementor и не запускать repair из-за ложной потери видимых описаний или CTA.

## v02.09.68

- Три карточки больше не переводятся в двухколоночную раскладку с одинокой карточкой во второй строке: для них всегда выбирается ровный ряд из трех колонок.
- Token-map больше не перекрашивает transparent generated content-shell и transparent root в surface/paper; фон остается только там, где он задан карточке.
- Проверить в открытом Elementor process и portfolio prompts, включая CTA и прозрачную оболочку.

## v02.09.67

- Content-only prompts with labeled cards and a standalone CTA now preserve that CTA in fidelity checks and fallback content mapping.
- Проверить в открытом Elementor, что трехкарточная и двухколоночная бенто-композиции сохраняют весь контент и проходят Vision без переполнения.

## v02.09.66

- Нормализатор очищает фон уже существующей content-shell, если провайдер вернул собственную белую оболочку вокруг bento-сетки.
- Убраны асимметричные варианты `48/23` и `31/31/31/100`, из-за которых при трех карточках появлялись узкие колонки и большие пустые зоны.
- Разные бенто-композиции теперь используют только сбалансированную трех-/четырехколоночную сетку или ровную двухколоночную сетку; карточки в строке растягиваются по высоте.
- Проверить в открытом Elementor несколько естественных запросов с разными раскладками: равномерная сетка и двухколоночный бенто-ритм.

## v02.09.65

- Нормализатор очищает фон уже существующей content-shell, если провайдер вернул собственную белую оболочку вокруг bento-сетки.

## v02.09.64

- Корневой контейнер каждого нового блока и контейнер bento-сетки принудительно остаются прозрачными; заливка сохраняется только у карточек.
- Внутреннее правило генерации синхронизировано с прозрачной внешней композицией, чтобы провайдер не возвращал белую оболочку вокруг карточек.
- После публикации проверить несколько естественных контентных запросов с разными bento-композициями через открытую Elementor-вкладку и AI Vision.

## v02.09.63

- Outlined badge is always the first child of a vertical root composition, above the generated heading and content.
- Original horizontal composition is preserved inside a transparent full-width content shell; fallback visual variants cannot move the badge beside it.

## v02.09.62

- Контентные briefs без технических слов корректно распознаются как генерация: добавлены естественные сигналы коллекции, доставки, заказа, стоимости, этапов и CTA.
- Если провайдер вместо команды присылает инструкцию, bounded repair/fallback остается рабочим путем генерации, а не переводит запрос в обычный ответный чат.

## v02.09.61

- Селектор fallback-композиции сначала выбирает неиспользованную структурную схему, пока на странице не покрыты все 6 layout-вариантов.
- Для существующих блоков без служебных меток layout восстанавливается по ширинам дочерних контейнеров, поэтому визуальные повторы не скрываются новой палитрой.

## v02.09.60

- Введен post-parse guard неповторяемости: 60 комбинаций палитры и композиции, сравнение визуального отпечатка с существующими top-level блоками и сохранение `_wpae_visual_variant`/`_wpae_visual_layout`.
- Разные варианты меняют не только поверхность, но и сетку карточек: 2/3/4 колонки, feature-card и split-композиции с mobile stack.

## v02.09.59

- Провайдерские и fallback-блоки получают общий guard неповторяющихся визуальных вариантов; внутри одной повторяющейся сетки фон карточек остается единым.
- После загрузки виджетов скрипт чата скрывает stale Elementor preview loader, чтобы realtime canvas и AI Vision видели фактически отрисованный блок.

## v02.09.58

- Генерации получают новый внутренний seed композиции, а deterministic fallback выбирает неиспользованный визуальный вариант из десяти вариантов на текущей странице.
- Вариант применяется после design-token map, поэтому разные фон, обводка, ритм и скругления не затираются нормализатором, а пользовательский контент остается неизменным.

## v02.09.57

- Обычные предложения и строки контентного brief теперь считаются запрошенным контентом, если нет кавычек и пар «название — описание».
- Fallback и content-fidelity сохраняют такой контент вместо шаблонного текста.

## v02.09.56

- Контентные промты без технических глаголов распознаются как генерация, если содержат минимум три смысловые фразы/строки и CTA или явный контентный сигнал.
- Повторная проверка должна использовать тот же естественный brief, а не добавлять в пользовательский промт названия Elementor-виджетов.

## v02.09.55

- Поле ввода floating Elementor-чата получило ту же фиксированную высоту 42px, что и кнопка отправки; переполнение текста прокручивается внутри поля.

## v02.09.54

- Управляющие кнопки floating Elementor-чата переведены с текста на иконки: копирование лога, копирование выделения, отправка и отмена.
- Для каждой иконки сохранены `aria-label` и native tooltip; размер кнопок стабилен на desktop и mobile.

## v02.09.53

- В шапку floating Elementor-чата добавлена отдельная кнопка копирования полного JSON выделенных объектов.
- Скопированный `wpae-elementor-selection-v1` содержит post ID, время, native Elementor settings и рекурсивное дерево дочерних элементов; полное дерево не отправляется провайдеру автоматически.

## v02.09.52

- Исправлена геометрия generated badge: горизонтальные внутренние отступы больше вертикальных, радиус задан по четырем сторонам, а native heading-label не получает внешние отступы.
- Нормализатор принудительно пересобирает любой badge, который вернула модель, в канонический outlined pill; design-token map больше не перезаписывает padding/radius бейджа и прозрачность контейнера bento-сетки.

## v02.09.51

- Vision теперь получает screenshot и visible text только нового корневого блока, переданного через `data-id`, поэтому старые секции страницы не искажают проверку текущего результата.
- Если новый блок не найден или имеет нулевой размер, Vision получает явную ошибку вместо проверки устаревшего viewport.

## v02.09.50

- Исправлена семантика иконок карточек: длинные testimonial-цитаты не превращаются в заголовки и не получают иконку; иконка ставится рядом с коротким именем или заголовком карточки.
- Ошибочные testimonial `icon-box` из ответа модели нормализуются обратно в текст цитаты, а контейнер карточной сетки сохраняет прозрачный фон.

## v02.09.49

- Бейдж генерации переведен из растянутого `heading` в native Flexbox-контейнер с heading-label внутри: прозрачный фон, темная 2px обводка, pill-радиус и компактная ширина.
- Нормализатор сохраняет этот badge-контейнер от преобразования в карточный `icon-box`.
- Контейнер, который содержит повторяющиеся карточки, получает прозрачный фон; фон остается только у самих карточек.

## v02.09.48

- Добавлена обязательная визуальная грамматика генерации: каждый новый блок получает компактный outlined-бейдж с закруглением, а заголовки повторяющихся карточек нормализуются в native `icon-box` с иконкой слева.
- Правило применяется после ответа/repair/fallback модели и до Elementor preflight, поэтому его не нужно писать в пользовательском промте.
- Добавлен regression-контракт для бейджей и карточечных иконок; нормализатор безопасно обрабатывает неполные settings и пустые icon values.

## v02.09.47

- После realtime-вставки canvas прокручивается к новым элементам, а AI Vision получает их `data-id` и проверяет именно сгенерированный блок, а не старый viewport страницы. Если target не найден, старый viewport не отправляется: preview обновляется один раз, затем операция получает явную ошибку.

## v02.09.46

- Content-only пары разбираются одним общим parser path, включая формат «вопрос? — ответ».
- Исправлена запись контента fallback-карточек: native widgets теперь меняются в реальном массиве, а не во временном выражении `?? []`.

## v02.09.45

- Контентный список преимуществ больше не ошибочно классифицируется как процесс из-за слова «запускаем».
- Fallback repeatable-блока удаляет лишние шаблонные карточки и сохраняет ровно 2–4 карточки по числу контентных пар пользователя.

## v02.09.44

- Контентные промты без названия типа блока теперь получают archetype по смысловым признакам; нейтральный список пар по умолчанию собирается как benefits-карточки, а вопросы, тарифы, отзывы, этапы и кейсы распознаются отдельно.

## v02.09.43

- Контентный запрос из нескольких естественных пар «название — описание» теперь распознается как задача на генерацию блока даже без слов «сделай» или «добавь».

## v02.09.42

- В editor chat Vision-отчет явно сообщает о полной регенерации, а не о точечной правке.
- Provider retry сохраняет принудительный reload и получает bounded fallback: если встроенный браузер не выполнил reload, запрос повторяется локально ровно один раз без нового цикла.

## v02.09.41

- AI Vision repair больше не урезает слабый блок точечным patch: неудачная версия откатывается и полностью перегенерируется по исходному brief и findings Vision.
- Полная regeneration использует native `insert_elements`, не затрагивает существующие блоки страницы и повторяет Vision-проверку максимум два раза.

## v02.09.40

- AI Vision findings теперь передаются обратно в editor-chat LLM как один bounded repair-проход.
- Repair получает безопасную карту существующих Elementor IDs и короткий visible text excerpt, возвращает только `patch_elements` по native settings и не может добавлять, удалять или дублировать блоки.
- После repair Vision не запускается рекурсивно; результат считается успешным только после подтвержденной Elementor-записи.

## v02.09.39

- Исправлен конфликт editor-chat Vision: advisory-проверка больше не откатывает успешную Elementor-запись из-за замечаний к существующему контенту страницы; предупреждение и кнопка отмены остаются в чате.

## v02.09.38

- Fallback-контент повторяемых блоков больше не расходуется на корневой heading/description: явные пары пользователя сохраняются внутри карточек benefits, pricing, testimonials, process и portfolio до content-fidelity gate.

## v02.09.37

- После автоматического provider retry чат снова раскрывается после принудительного reload и показывает отдельное сообщение о повторе и его итог, вместо скрытого результата в свернутом pill.

## v02.09.36

- В editor chat временные ошибки LLM-провайдера (`408`, `425`, `429`, `5xx`) теперь считаются недоступностью: запрос сохраняется, вкладка принудительно перезагружается и выполняется ровно один retry.
- Ошибки авторизации, неверного запроса и JSON не ретраятся автоматически.

## v02.09.35

- Исправлен pricing fallback: служебный префикс запроса больше не попадает в карточный label или корневой заголовок; явно заданные тарифы раскладываются по отдельным native-карточкам.
- CTA в формате `Кнопка: ...` теперь переносится в кнопки fallback-блока.

## v02.09.34

- Исправлена PHP parse error в `includes/llm/llm.php`: в ветке targeted Elementor patch были незакрытые вложенные массивы progress steps, из-за чего WordPress мог завершаться с HTTP 500 при загрузке плагина.
- Версия повышена до `v02.09.34`; перед релизом добавлена обязательная проверка синтаксиса PHP-файлов без выполнения WordPress.

## v02.09.33

- AI Vision больше не пропускает низкокачественную композицию как успешную: score ниже 75 и findings уровня major/critical блокируют результат.
- Elementor-чат автоматически пытается откатить запись после проваленной Vision-проверки и показывает причины вместе с результатом отката.
- Исправлен дефект, из-за которого `quality_failed` всегда возвращался как `false`.

## v02.09.32

- Fallback генерации извлекает естественные пары «название — описание» без обязательных кавычек и переносит их в соответствующие native-карточки.
- Explicit heading, CTA, названия и описания теперь проверяются content-fidelity gate до записи; шаблонный текст не может пройти как успешный результат.

## v02.09.31

- При точной ошибке `wpae_llm_provider_request_failed` чат сохраняет текущий запрос в `sessionStorage`, принудительно перезагружает открытый Elementor и повторяет запрос ровно один раз.
- Повтор не применяется к ошибкам модели, JSON, валидации, сохранения или AI Vision; после второй недоступности провайдера чат показывает подробности без нового reload-цикла.

## v02.09.30

- Добавлен content-fidelity gate для LLM action: явно заданные в пользовательском запросе фразы, цены, CTA и названия обязаны присутствовать в native Elementor content до записи.
- Deterministic fallback переносит распознанный пользовательский контент в подходящие heading/text-editor widgets, а при невозможности возвращает подробную ошибку вместо ложного успеха.
- AI Vision editor review получает brief и bounded `text_excerpt` из preview и обязан отдельно проверять соответствие видимого контента запросу (`content_fidelity`), а не только композицию.

## v02.09.29

- Укрепить LLM-chat contract: operation ID, строгий один root container, diff и editor-only undo.
- Сделать редакторский Vision advisory: анализировать очищенный preview body с bounded render-context, не откатывать запись из-за screenshot/provider failure.
- Добавить в guide единые требования realtime-sync, preview/undo, archetype composition, skills evidence и targeted editing.

## Готово

- Добавлены инструкции для агентов по WordPress, Elementor, frontend-дизайну, native Flexbox Containers и запрету файловых записей.
- Заблокированы типовые файловые операции через `/run`.
- Самообновление плагина разрешено только через отдельный endpoint `/self-update`.
- Добавлены кастомные skills, которые хранятся в базе данных через `/skills`.
- Перед любым write endpoint требуется связка `/guide/session` + `/guide/ack` и guide token.
- Добавлена runtime-валидация измененного Elementor JSON: legacy `section`/`column`, отсутствующий `widgetType`, а также включенные skill `enforce` rules.
- Добавлены переключатели возможностей для владельца сайта: `/run`, self-update, Elementor writes, media upload, exports, skills management и filesystem write override.
- `/capabilities` расширен до machine-readable контракта, совпадающего с runtime enforcement.
- Добавлены безопасные Elementor endpoints: `/elementor/validate`, `/elementor/page`, `/elementor/update`.
- Добавлен `/audit` для machine-readable проверки Elementor/page после записи.
- В dashboard добавлены поля для вставки и управления кастомными `SKILL.md`, которые хранятся в базе данных.
- Добавлены dry-run для структурированных Elementor writes и rollback snapshots в `wp_options`.
- Добавлен `/rollback`, защищенный guide token, и `/run` `rollback_targets` для известных posts/options.
- Добавлены import/export JSON skill bundles через REST и dashboard; хранение только в `wp_options`.
- Расширены skill `enforce` rules: Elementor widget allowlists, запрещенные widget types, обязательные widget/container settings и запрещенные HTML widget patterns.
- Добавлены ограниченные operation logs в `wp_options`: endpoint, actor hint, target IDs, guide hash, validation summary и rollback snapshot ID.
- Operation logs остаются redacted: без API keys, guide tokens, raw page payloads, request bodies, response bodies и secrets.
- Добавлен agent conformance scoring в responses и operation logs: guide-token flow, file policy, Elementor policy, Flex Containers, `widgetType`, native visual settings и verification signal.
- Добавлен `/elementor/normalize` для частых ошибок Elementor JSON: `widget_type`, legacy `section`/`column`, отсутствующие `settings`, `elements`, IDs и baseline container settings.
- Добавлены `/elementor/recipes`, `/elementor/recipes/{id}` и `/elementor/compose` для переиспользуемых native Flexbox Container composition patterns с variants и slots.
- Agent conformance scoring расширен до design quality gates: typography hierarchy, spacing consistency, CTA visibility, mobile readiness, palette quality и native content completeness.
- Добавлен `/elementor/blueprint` для read-only планирования страницы перед записью: subject, goal, audience, offer, language, style, section map, design tokens, CTA plan, recipes и enhancement zones.
- Добавлены project design tokens в dashboard, `/guide`, `/capabilities` и `/elementor/blueprint`: palette, typography roles, spacing scale, radii, button style, tone of voice и design prohibitions.
- Добавлено обязательное правило responsive units: `rem/em` для отступов/типографики, `vh/svh` для высоты экранных секций, `%`/flex/max-width для ширины; `px` только для малых исключений.
- Добавлены строгие preflight checks перед `/elementor/page` и `/elementor/update`: блокируют invalid contract, пустой native content, HTML widget как layout, отсутствие CTA и native critical visuals для landing pages; предупреждают про фиксированные `px` width/height.
- Добавлен after-save `quality_summary` для `/elementor/page` и `/elementor/update`: permalink, status, visual audit score/level, warnings и конкретные fixes.
- Усилен guide token flow: sessions хранятся отдельными `wp_options` плюс legacy index, tokens хранятся по hash, `/guide/ack` принимает JSON, raw JSON fallback и form-fields.
- Запрещен обход Elementor validation через `/run`: прямые изменения `_elementor_data` после `/run` проходят design-system/preflight contract и откатываются при ошибке.
- Добавлено обязательное правило error reporting: агент должен указывать endpoint/action, HTTP status или exception, plugin error details/preflight/blocking_errors и следующий безопасный шаг.
- Добавлена миграция design-system markers: `/elementor/normalize` заменяет старые `wpae-system-*` на текущую основную дизайн-систему, сохраняя остальные CSS classes.
- Запрещены запросы WP Admin логина/пароля, admin cookies, nonces и browser sessions; Playwright/WP Admin нельзя использовать для правок, только для публичной проверки после REST API writes.
- Добавлено обязательное правило mobile-first: агент должен сначала проектировать мобильную композицию, типографику, CTA, tap targets и responsive Elementor settings, а уже потом расширять tablet/desktop.
- Добавлен read-only endpoint `/visual-audit` для публичного HTML-аудита same-site страниц: fetch/status, viewport/title/copy, overflow risks, invisible text, empty blocks, CTA и mobile-first CSS signals. Screenshot/render metrics оставлены для публичной browser-проверки.
- Добавлено обязательное правило native style settings first: стили элемента меняются через нативные Elementor settings/style controls; CSS допускается только как scoped exception для сложных случаев, которых нет в настройках.
- Добавлено защитное правило preserve existing enhancements: native-first не разрешает массово переписывать страницу или удалять рабочие CSS/JS/WebGL/Three.js/GSAP/canvas/animation HTML widgets без явного запроса.
- В guide добавлен подробный CSS-to-native Elementor mapping: typography_*, colors, padding/margin/radius/min_height/flex/gap/wrap, unitless line-height для multi-line и обязательная очистка Elementor/WP Rocket CSS cache после native changes.
- Уточнено по `wordpress-elementor-dev`: обычные и hover gradients через Elementor `Group_Control_Background` являются native settings; `z-index` и fixed/sticky сначала через Elementor advanced/positioning/motion controls, CSS только если native controls недостаточны для overlay/off-canvas/system layers.
- Усилена безопасность: `/run` выключен по умолчанию и однократно отключается при обновлении существующей установки; self-update принимает только immutable Git commit URL и заменяет файл атомарно.
- Усилены пункты безопасности 4–6: `/visual-audit` проверяет DNS/IP и использует safe HTTP fetch, канонический API-key header — только `X-AI-Key`, `X-WPAE-API-Key` принимается как deprecated alias с warning, просроченные guide-token options очищаются автоматически.
- Усилены оставшиеся пункты безопасности: rollback восстанавливает только управляемые Elementor/WPAE meta, новая страница удаляется при ошибке сохранения Elementor metadata, exports хранятся в `wp_options` без публичных файлов, `/media/upload` проверяет фактическую binary signature, а dashboard получил preset-кнопки single-key capability modes.
- В `/guide` добавлены явные runtime notes для агентов: rollback scope, automatic orphan cleanup для `/elementor/page`, binary signature check в `/media/upload` и database-only `/exports/create`.
- Добавлен `/elementor/typography-unlock`: безопасная миграция страниц, где сторонний агент зашил локальные `typography_*` overrides в каждый виджет и тем самым сломал глобальное управление типографикой через Elementor.
- В guide добавлено правило typography editability: native settings first не означает дублировать `typography_*` на каждом widget; глобально управляемая типографика должна идти через роли/наследование, локальные overrides только для осознанных исключений.
- Уточнено правило Elementor editor editability: все дизайн-свойства, которые Elementor умеет редактировать, должны оставаться в native settings/controls; native settings нельзя удалять ради “редактируемости”, потому что они и есть редактируемый источник. CSS/HTML widget не должны быть единственным источником управляемого дизайна.
- Добавлены endpoints для аварийного восстановления: `/rollback/snapshots`, `/elementor/revisions`, `/elementor/restore-revision`. После любых page writes/rollback/migration/restore теперь требуется реальный browser screenshot публичной страницы; HTML/CSS-аудита недостаточно.
- Добавлены `/exports` и `/exports/prune`, а также карточка в dashboard для просмотра metadata короткоживущих JSON exports и ручной очистки просроченных записей из `wp_options`.
- Проведен review после живого использования новых endpoints: добавлен `/skills/import-url` в conformance guide-token route list и исправлена передача `target` в skill enforce rules.
- Добавлен `repeated_agent_error_audit` в `/audit` и `/elementor/visual-audit`: legacy sections/columns, `widget_type`, HTML widget layout/content, script-injected native CSS, heading typography `!important`, excessive local typography overrides, design-system marker drift и fixed px layout risks.
- Добавлен transaction write mode для `/elementor/page` и `/elementor/update`: atomic write, post-save metadata verification, cache refresh verification, optional public verification, optional strict quality gate и auto-rollback через rollback snapshot при провале.
- Добавлен `/elementor/patch` для точечных native Elementor правок по `element_id` и property path без пересборки всей страницы; patch проходит validation, design-system contract, preflight, atomic transaction, cache refresh и rollback.
- Добавлен visual regression gate: `transaction_visual_regression=true` сохраняет public HTML/audit baseline перед правкой существующей страницы, сравнивает HTTP status, visible text, CTA, overflow, empty blocks и audit level после записи, и запускает auto-rollback при явной деградации.
- Добавлен read-only WordPress Health: `/health` проверяет bootstrap latency, базу, autoload options, PHP memory/runtime, WP-Cron, диск, debug-конфигурацию и обновления; ручной deep mode дополнительно проверяет loopback и REST API с короткими таймаутами. Dashboard показывает последний отчёт и не выполняет автоматические исправления.
- Добавлен `/elementor/editability-audit`: проверяет native Elementor editability coverage для типографики, цветов, фонов, spacing/flex settings и ловит HTML/script CSS overrides для свойств, которые должны редактироваться через Elementor.
- Добавлен `/elementor/css-to-native`: переносит уверенно распознанные CSS declarations из HTML widget `<style>` в native Elementor settings по `data-id`/`elementor-element-*` selectors, с обязательным `dry_run`-workflow и пропуском protected enhancement zones.
- Продолжено дробление Elementor-модулей: editability audit вынесен из `validation.php` в отдельный `includes/elementor/editability.php`, CSS migrator добавлен как отдельный `includes/elementor/css-native.php`.
- Продолжено постепенное дробление `validation.php`: базовые Elementor validation rules вынесены в `includes/elementor/validation-rules.php`, design-system contract вынесен в `includes/elementor/design-contract.php`.
- Dashboard разделен на доступные горизонтальные табы: «Подключение», «Elementor», «Агенты», «Мониторинг» и «Примеры». Активная вкладка сохраняется, поддерживаются клавиатурная навигация и fallback без JavaScript.
- Исправлен false positive диагностики REST latency: ожидаемо долгие `/self-update` и `/self-update-package` исключены из порогов интерактивных запросов, но сохраняются в отчёте как maintenance metadata.
- Добавлен опциональный AI Vision review: провайдеры Gemini/OpenAI/Claude, зашифрованный ключ в `wp_options`, `/vision/analyze`, `/vision/report`, `/vision/page-review`, нормализованные отчёты и атомарный rollback при критических findings.
- Custom skill manifests получили capability `ai_vision` и закрытый allowlist Vision endpoints; включенная capability владельца и guide token остаются обязательными.
- Добавлен LLM proxy и floating Elementor chat: OpenAI, DeepSeek, OpenRouter и custom OpenAI-compatible HTTPS providers, зашифрованные ключи в `wp_options`, capability `llm_chat`, rate limit и bounded editor context.
- Для LLM-настроек исправлена UX-логика: OpenRouter по умолчанию использует `openrouter/free`, built-in provider всегда получает свой HTTPS base URL, а сохраненный API-ключ отображается безопасным placeholder из bullet-символов без передачи значения в форму.
- В `v02.08.73` добавлен явный fallback обнаружения Elementor по `post.php?post=ID&action=elementor`, чтобы floating chat подключался даже при пропуске Elementor editor hook.
- В `v02.08.74` добавлен execution mode для явных action-запросов из Elementor chat: только вставка новых элементов через штатный update/preflight/visual-regression/rollback pipeline; tutorial-ответы для таких запросов отклоняются.
- В `v02.08.75` execution mode стал рабочим: плагин сам объединяет `insert_elements` с текущей страницей, нормализует существующую структуру и отклоняет неподдерживаемые/не-JSON ответы.
- В `v02.08.76` добавлена отправка LLM-запроса по `Enter`; `Shift+Enter` сохраняет перенос строки, кнопка остается доступной на мобильных устройствах.
- В `v02.08.77` LLM proxy принимает распространенные варианты OpenAI-compatible ответа, сообщает безопасную диагностику пустого ответа и увеличивает лимит для action-JSON Elementor.
- В `v02.08.79` Elementor-чат показывает детали отказа action-пайплайна и позволяет скопировать текущий диалог как лог; промпты по-прежнему не сохраняются на сервере.
- В `v02.08.80` копируемый лог переведен в JSON-формат, а action-вставка может штатно инициализировать новую Elementor-страницу без сохраненного `_elementor_data`.
- В `v02.08.81` action-элементы перед сохранением проходят normalize и native token map активной дизайн-системы; ошибки возвращают структурированный `blocking_errors`.
- В `v02.08.82` normalize переносит совместимые поля `text/content/title` в нативные `title/editor/text`, чтобы модель не создавала пустые Heading, Text Editor или Button widgets.
- В `v02.08.83` LLM-чат возвращает безопасную трассировку рабочих шагов: разбор ответа модели, проверка action, native normalize, design-system map, Elementor update и итог; шаги отображаются в чате и копируемом JSON-логе.
- В `v02.08.84` action-запросы floating Elementor-чата получают тот же guided-контекст: актуальные guide, custom skills, capabilities и дизайн-систему; запись остается в общем серверном Elementor pipeline без передачи API-ключа в браузер.
- В `v02.08.85` floating chat показывает отдельные progress-сообщения, выводит подтвержденные серверные шаги отдельными сообщениями и после успешной записи перезагружает текущий Elementor preview, чтобы открытый редактор отобразил сохраненные данные.
- В `v02.08.86` action-gate отклоняет технически валидные, но пустые команды без native Elementor widgets, чтобы чат не сообщал об успешном создании пустого блока.
- В `v02.08.87` LLM-чат пробрасывает `failed_checks`, сообщения проверок и причину transaction rollback, чтобы ошибка Elementor не сводилась к общему HTTP 422.
- В `v02.08.88` floating Elementor-чат после записи анализирует обновленный preview через AI Vision и откатывает snapshot при критических находках или ошибке screenshot/provider review.
- В `v02.08.89` ошибки AI Vision в Elementor-чате раскрывают безопасные детали provider/HTTP-ответа и причину rollback вместо общего сообщения.
- В `v02.08.90` ответы LLM с `finish_reason=error` и HTTP 200 классифицируются как ошибки провайдера и показывают безопасный код/текст причины вместо «пустого ответа».
- В `v02.08.91` поле API-ключа AI Vision маскирует сохраненный ключ буллетами, как основное поле LLM, и сохраняет поведение «пустой ввод = оставить текущий ключ».
- В `v02.08.92` Elementor-чат показывает в шапке выбранную модель и текущую версию плагина без передачи API-ключа в браузер.
- В `v02.08.93` модель AI Vision по умолчанию изменена на `gemini-3.5-flash-lite`, а action-промпт LLM явно запрещает контейнеры без заполненных native widgets.
- В `v02.08.94` action-ответы LLM получили увеличенный лимит и компактный JSON-промпт; при обрыве или невалидном JSON чат показывает безопасные diagnostics ответа и `finish_reason`.
- В `v02.08.95` обновление открытого Elementor preview после REST-записи использует официальный `document/save.footerSaver.refreshWpPreview()` с fallback на `elementor.reloadPreview()`.
- В `v02.08.96` floating chat принудительно обновляет текущий Elementor preview-iframe с cache-busting и ждёт загрузки native widgets; editor Vision gate откатывает результат при `major` findings или score ниже 75, чтобы не подтверждать кривой/пустой canvas.
- В `v02.08.97` initial insert в пустую Elementor-запись не сравнивается с пустой публичной baseline-страницей; visual regression остаётся включённым для уже существующего контента.
- В `v02.08.98` visual regression сравнивает ухудшение относительно исходного уровня и не блокирует изменения на странице, которая уже была weak/blocked; action-JSON получил больший лимит и ограничения компактности, чтобы не обрываться на сложных блоках.
- В `v02.08.99` visual regression пропускает черновики и сравнивает только опубликованные страницы, где доступен достоверный public baseline; это устраняет ложные rollback при работе в редакторе с draft-страницами.
- В `v02.09.00` floating chat не запускает AI Vision rollback для draft-страниц: у них нет стабильного публичного preview; опубликованные страницы по-прежнему проходят Vision-проверку.
- В `v02.09.01` проверка realtime preview ждёт ожидаемое увеличение числа native widgets после вставки, а не принимает старый widget за подтверждение обновления canvas.
- В `v02.09.02` realtime preview получает новый `ver` вместе с cache-busting параметром, чтобы iframe загружал свежий Elementor HTML, а не устаревший ответ.
- В `v02.09.03` после успешной REST-записи чат синхронизирует сохраненные элементы в текущую модель открытого Elementor через официальный `$e.run('document/elements/create')`; при недоступности API сохраняется fallback обновления preview.
- В `v02.09.04` action-декодер принимает JSON, который OpenAI-compatible провайдер вернул дополнительной строкой-оберткой, и только затем запускает стандартную валидацию команды.
- В `v02.09.05` action-запросы используют JSON mode, а OpenRouter требует совместимый маршрут параметров; финальная инструкция запрещает модели возвращать REST-маршруты вместо Elementor JSON.
- В `v02.09.06` для `openrouter/free` добавлен узкий fallback без optional structured-output параметров при ответе OpenRouter «No endpoints found»; серверная декодировка и Elementor-гейты остаются обязательными.
- В `v02.09.07` чат показывает безопасную техническую деталь транспортной ошибки LLM-провайдера вместо общего «провайдер недоступен».
- В `v02.09.08` action-промпт требует один корневой Flexbox-контейнер и 3–5 вложенных native widgets, чтобы `openrouter/free` не возвращал плоские раздутые команды.
- В `v02.09.09` добавлен один bounded repair-pass для команд без native widgets, с лишними элементами или неверным action; итог всё равно проходит общий Elementor pipeline.
- В `v02.09.10` repair-pass переведен на минимальный JSON-only контекст, чтобы не повторять длинный guided prompt при исправлении ответа `openrouter/free`.
- В `v02.09.11` repair-pass получает точный `post_id` текущего Elementor редактора и не может случайно адресовать другую страницу.
- В `v02.09.12` repair-pass требует непустые native `title`, `editor`, `text` и URL кнопки, чтобы валидная оболочка не превращалась в пустой Elementor canvas.
- В `v02.09.13` низкий AI Vision score больше не откатывает валидную запись сам по себе; rollback остаётся для `major/critical`, а чат показывает детали отчёта при отклонении.
- В `v02.09.14` repair-pass получает короткий рабочий JSON-пример с заполненными heading, text-editor и button, чтобы провайдер не возвращал пустой контейнер.
- В `v02.09.15` добавлена одна повторная попытка для пустого или неподдерживаемого repair-ответа, с явной диагностикой в trace.
- В `v02.09.16` major Vision findings переведены в предупреждения: rollback редакторского чата выполняется только для critical findings.
- В `v02.09.17` repair-pass запрещает служебные placeholder-тексты и требует содержательный контент под запрос пользователя.
- В `v02.09.18` Vision screenshot временно исключает editor-only overlays и dropzones, чтобы они не создавали ложные critical findings.
- В `v02.09.19` editor-chat Vision переведен в advisory-режим: субъективные critical/major findings не откатывают realtime-вставку; строгий rollback остается у transaction Vision gate.
- В `v02.09.20` action-промпт классифицирует обычные запросы по типу блока и выбирает соответствующие native Elementor widgets, чтобы skills не сводились к одному hero/benefits-шаблону; repair-pass сохраняет эту специализацию.
- В `v02.09.21` добавлен тематический deterministic fallback после невалидного ответа провайдера и bounded repair-pass; fallback явно попадает в trace и проходит тот же Elementor/design-system pipeline.
- В `v02.09.22` fallback отзывов переведен на отдельные native Flexbox card containers с editable background, border, radius, padding и responsive width; правило карточек закреплено в guide для повторяющегося контента.
- В `v02.09.23` добавлена semantic typography guard для отзывов: авторы внутри карточек получают native h5/h6 вместо display-scale h1/h2, а правило закреплено в guide и repair prompt.
- В `v02.09.24` повторяющиеся блоки по умолчанию приводятся к native bento-сетке: виджеты оборачиваются в редактируемые Flexbox-карточки, включается перенос, максимум четыре элемента в ряд и 100% ширина на mobile. Технические требования bento не добавляются в пользовательский prompt.
- В `v02.09.25` исправлен native sizing карточек: режим `_flex_size=custom` и нулевые grow/shrink теперь действительно включают заданную процентную ширину Elementor, включая mobile override.
- В `v02.09.26` исправлена совместимость bento-сетки с актуальными Elementor controls: карточки используют `content_width=full`, а flex alignment и gaps записываются через `flex_*` ключи.
- В `v02.09.27` bento-нормализатор больше не превращает заголовок, описание или CTA в карточки; fallback-композиции benefits, pricing, process и portfolio используют отдельные повторяемые card containers внутри grid.
- В `v02.09.28` улучшен общий Elementor-нормализатор: legacy alignment/gap aliases мигрируются в актуальные Flexbox controls и responsive variants, контейнеры получают `container_type=flex`, а native-кнопки всегда получают явные цвета активной дизайн-системы, чтобы тема не подменяла контраст.

## Библиотека произвольных Elementor-блоков

Цель: копировать и переиспользовать любой выбранный Elementor Flexbox Container или widget, включая чужие блоки и шаблоны, а не только встроенные WPAE recipes.

Статус: визуальная библиотека первого этапа реализована в `v02.08.58`;
в `v02.08.59` добавлена ссылка на настройки в WordPress Admin Bar;
в `v02.08.60` основная панель настроек разделена по функциональным вкладкам;
в `v02.08.61` исправлена классификация долгих maintenance REST-операций.

1. Поддержать два формата:
   - native Elementor JSON для точного копирования исходного элемента без обязательной WPAE-обёртки;
   - `wpae-elementor-block-v1` для постоянной библиотеки, метаданных, зависимостей, совместимости и использования агентами.
2. При сохранении чужого блока не менять его исходный Elementor JSON и дизайн автоматически. `design_system_id` может быть пустым.
3. При получении блока из библиотеки поддержать режимы:
   - `preserve` — только новые Elementor IDs, исходный дизайн сохраняется;
   - `compatibility` — новые IDs плюс normalize/validate для текущего Flexbox-контракта;
   - `adapt` — compatibility плюс детерминированное применение semantic tokens через native Elementor settings.
4. Показывать compatibility report: legacy section/column, `widget_type`, неизвестные widgets, protected enhancement zones, design-system drift и необходимые normalize changes.
5. Добавить в Elementor context menu:
   - «Копировать как JSON»;
   - «Сохранить в библиотеку WP AI Executor».
6. Добавить постоянный REST CRUD:
   - `GET|POST /elementor/blocks`;
   - `GET|DELETE /elementor/blocks/{id}`;
   - `GET /elementor/blocks/{id}/instantiate?mode=preserve|compatibility|adapt`.
7. Агент должен сначала искать подходящий блок в библиотеке, получать безопасный instance с новыми IDs, затем использовать существующие normalize/validate/dry-run/write endpoints.
8. Визуальная библиотека внутри Elementor:
   - реализованы поиск, фильтр категорий, структурный просмотр и режимы вставки;
   - вставка выполняется через native `document/ui/paste`;
   - следующие этапы: редактирование metadata, автоматические screenshots и
     перенос media dependencies.

## Далее

### Roadmap на основе Open Design

Цель: перенять переносимые контракты Open Design, сохранив WordPress-native
архитектуру, Elementor editability и запрет произвольных серверных файлов.

1. **Design System Package v2 — первый приоритет.**
   - Статус: реализовано и проверено на live в `v02.08.63`; следующий этап — Token Map.
   - Разделить текущую систему на machine-readable manifest, agent-facing `DESIGN.md` content и semantic tokens.
   - Хранить пакет в `wp_options`, без создания файлов на сервере.
   - Добавить ID, версию, provenance, source URL/hash, license и active system per page.
   - Компилировать semantic roles в native Elementor settings mapping; `rem/em/%/vh` остаются политикой WPAE.
   - Сохранить совместимость с текущим `/elementor/design-system` и существующими токенами.
   - Готово, когда manifest/tokens проходят schema validation, а смена системы доступна через `dry_run` и rollback.

2. **Детерминированный Token Map для `adapt` — второй приоритет.**
   - Статус: реализован и проверен на live в `v02.08.64`; следующий этап — Skill Manifest v1.
   - Извлекать из чужого блока цвета, typography, spacing, radii и другие native-supported значения.
   - Сопоставлять их с semantic roles активной дизайн-системы по использованию, а не только по близости значений.
   - Возвращать `mapped`, `unmatched`, `collisions`, evidence и предполагаемые native Elementor property paths.
   - Не добавлять новые токены и не менять protected HTML/WebGL zones без явного подтверждения.
   - Применять mapping только через `dry_run -> validate -> approve/write`; `preserve` и `compatibility` не менять.
   - Готово, когда `adapt` выполняет реальный token-level remap и неоднозначные значения остаются в review list.

3. **Skill Manifest v1.**
   - Статус: реализован и проверен на live в `v02.08.66`; следующий этап — Design Review Gate.
   - Оставить `SKILL.md` каноническим переносимым содержимым.
   - Добавить необязательный sidecar в database record: version, capabilities, inputs, pipeline, source, license и compatibility.
   - Проверять минимальные capabilities и разрешать только существующие безопасные WPAE endpoints.
   - Не разрешать skill запускать shell, MCP server, WP-CLI, browser-admin writes или создавать серверные файлы.
   - Готово, когда старые skills работают без manifest, а новые получают schema validation и machine-readable workflow.

4. **Design Review Gate поверх существующего conformance scoring.**
   - Статус: реализован и проверен на live в `v02.08.67`; следующий этап — версионированный manifest Elementor-блока.
   - Переиспользовать `/audit`, visual regression, editability audit и conformance scoring вместо пяти отдельных LLM-панелистов.
   - Оценивать composition/brief, design-system consistency, accessibility/mobile, copy и Elementor editability.
   - Ввести состояния `draft -> review -> revise -> approved`, максимум три итерации с явной причиной остановки.
   - Не публиковать результат ниже blocking threshold; fallback `ship_best` не использовать для live Elementor page.
   - Готово, когда write response содержит review verdict, must-fix list и безопасный следующий шаг.

5. **Версионированный manifest Elementor-блока и publish flow.**
   - Статус: реализован и проверен на live в `v02.08.68`; следующий этап — user feedback -> rule proposals.
   - Расширить `wpae-elementor-block-v1`: status, source skill, design system, provenance/license, parent revision, quality score и media dependencies.
   - Ограничить metadata size, валидировать JSON и запрещать абсолютные/traversal paths.
   - Добавить редактирование metadata, duplicate/revision и `draft -> approved -> published` в визуальной библиотеке.
   - Добавить previews/screenshots только после стабильного manifest и approval workflow.
   - Готово, когда агент может выбрать только совместимый approved block и получить полный provenance report.

6. **User feedback -> rule proposals — после первых пяти этапов.**
   - Собирать повторяющиеся пользовательские исправления из review/annotations в предложения правил.
   - Использовать сначала детерминированную дедупликацию; LLM-обобщение оставить опциональным.
   - Никогда не менять guide/design system автоматически: каждое правило требует явного `accept/reject` владельца.
   - Готово, когда принятое правило получает source evidence, version и enforce mapping либо остаётся advisory.

7. **AI Vision visual QA.**
   - Статус: `v02.08.70` опубликован и выкачен на `mazhenov.kz`; публичная проверка удаления `/key` вернула `404`. Provider smoke-test ожидает настройки владельца сайта.
   - Использовать Vision как дополнительную проверку desktop/mobile screenshots, не заменяя deterministic audits, browser verification и Elementor editability checks.
   - Хранить только нормализованные отчёты в `wp_options`; изображения и raw provider responses не сохранять и не логировать.
   - Поддерживать явный `transaction_vision_review=true`: критические findings блокируют транзакцию и запускают существующий atomic rollback.
   - Готово, когда capability toggle, provider configuration, все три endpoints, report schema, transaction gate и live negative/positive smoke checks подтверждены.

### Что намеренно не переносим

- Electron/Next.js daemon, SQLite и отдельный desktop runtime.
- Запуск локальных CLI, MCP servers и shell-команд из WordPress.
- Произвольные React/iframe plugin surfaces и полный marketplace.
- Файловую artifact-систему: skills, manifests и design systems остаются в WordPress database.
- Пять дорогостоящих LLM-рецензентов; сначала используем уже существующие validators.
- Чужие брендовые assets/design systems без отдельной проверки license и provenance.

После каждого этапа: обновить guide/capabilities/dashboard, добавить один минимальный regression check, выполнить package dry-run, live rollout и проверить реальный ответ endpoint.

### Постоянная эксплуатация

1. Наблюдать новые live-логи и добавлять точечные validators только для подтверждённых повторяющихся анти-паттернов.

## Killer Features

1. Transaction write mode для Elementor: каждый `/elementor/page` и `/elementor/update` должен уметь работать как атомарная операция с auto-rollback при failed validation, failed cache refresh или failed public verification.
2. Patch API по `element_id` и native property path: агент должен менять точечные свойства вроде `heading.typography_font_size` или `container.background_color`, не пересобирая всю страницу.
3. Protected zones: маркировать WebGL/Three.js/GSAP/canvas/HTML enhancement blocks как защищенные, чтобы миграции native settings не ломали рабочие анимации и скрипты.
4. Visual regression gate: перед risky write сохранять lightweight baseline публичной страницы, после write сравнивать ключевые признаки layout/copy/CTA/overflow и блокировать явные поломки.
5. Elementor editability tests: отдельная проверка, что свойства из `css_to_native_map` реально управляются через Elementor native settings, а не перебиваются CSS/HTML/script-injected styles.
6. CSS-to-native migrator: endpoint, который находит native-supported CSS declarations, переносит их в settings виджетов/контейнеров и аккуратно удаляет только перенесенные CSS rules.
7. Design system registry: хранить несколько named design systems, фиксировать active system per page и явно мигрировать страницу между системами через dry-run.
8. Pattern library builder: сохранять любые native Elementor blocks и шаблоны в постоянную библиотеку; поддерживать raw native JSON и WPAE metadata wrapper, slots, variants, dependencies, compatibility и quality score.
9. Preview -> approve -> publish flow: агент сначала создает draft/preview, отдает audit summary и только после approval публикует или заменяет live page.
10. Agent contract handshake: write endpoints требуют явного подтверждения, что агент прочитал `/guide`, `/capabilities`, enabled skills, design system и текущие ограничения.
11. Recovery assistant actions: при ошибке endpoint должен возвращать не только code/message, но и безопасный следующий endpoint/payload skeleton для исправления.
12. Расширенный Agent Conformance Scoring: учитывать не только нарушения, но и качество процесса: blueprint used, recipe/compose used, native settings coverage, mobile-first coverage, visual verification evidence и number of retries.
13. AI Vision visual QA: анализировать desktop/mobile screenshots через настроенный provider, возвращать структурированные findings и использовать критические findings как дополнительный rollback gate.

## Приоритет внедрения

1. Модульная архитектура плагина. Готово в первом крупном разрезе: root стал bootstrap, домены вынесены в `includes/` без изменения REST API.
2. Protected zones для WebGL/Three.js/GSAP/canvas. Готово: existing protected blocks нельзя изменить или удалить без явного override с причиной.
3. Visual regression gate. Готово: доступен через `transaction_visual_regression=true` на existing-page writes.
4. Elementor editability tests. Готово: `/elementor/editability-audit` и summary внутри `/audit`.
5. CSS-to-native migrator. Готово: `/elementor/css-to-native` с dry-run, protected-zone guard и editability audit.
6. AI Vision visual QA. Реализован; `v02.08.70` добавляет лимит provider-вызовов, строгую проверку provider-отчёта и same-post/server-issued contract для atomic gate. После настройки провайдера и capability проверить provider request и transaction gate на live.

### Миграция на модули

1. Package updater: ZIP из immutable Git commit, manifest с SHA-256, staging и замена bootstrap последней. Готово.
2. Минимальный `wp-ai-executor.php`: header, constants и require модулей. Готово.
3. Elementor validation layer: базовые rules и design-system contract вынесены в отдельные файлы. Готово.
3. Перенос по доменам: security, REST, Elementor, guide, skills, admin, support. Готово в первом крупном разрезе.
4. Live dry-run package update, затем реальный rollout с возможностью отката. Выполняется для каждого пакетного релиза.
