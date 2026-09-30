## Текущий срез — FAQ live defect и source fix, 2026-09-30

Срез: **2026-09-30 16:05 +05:00 (Asia/Almaty)**. Использовались существующие tabs 13/14 и `post=5214`; новых страниц/drafts/tabs не создавалось. Tab 14 переиспользован для WP Pusher и целевого Plugins readback.

- Source release `v02.11.209` committed as `997d112`; report head `328223b`, pushed to `origin/main`. WP Pusher сообщил `Plugin was successfully updated`; целевая Plugins row показывает header `v02.11.209`. Inline config Elementor всё ещё `v02.11.204` (`wpae_release=193`) после reload и точечного hard refresh. Browser Use `goto()` в этой же вкладке с текущим или новым `fresh` значением завершился `net::ERR_ABORTED`. `editor-chat.php` сериализует `pluginVersion` из `WPAE_VERSION`; причина несоответствия (stale PHP worker/response cache или другое) не установлена. Production generation runtime v209 не подтверждён.
- Live FAQ prompt: `FAQ: «Как заказать проект?» — «Оставьте заявку, и мы свяжемся с вами». «Сколько длится работа?» — «Срок зависит от состава и объёма проекта».` Operation `wpae-20260930103313-01565d9d`, root `4c23da3`. Runtime v204 сохранил невалидный native Accordion с заголовками `Аккордеон #1/#2` и Kafka-placeholder вместо точных пар. Editor preview root ID подтверждён; iframe viewport 1025×860 CSS px, вкладка 1228×923.
- Причина: общий semantic audit разрешал двум generic repeatable containers заместить обязательные FAQ items в native Accordion. Исправление в `includes/llm/llm.php` теперь требует не меньше ожидаемого числа native Accordion items; регрессия в `tests/flex-generation-runtime.php` подтверждает отказ при пустом `tabs`, даже если generic containers присутствуют. `tests/llm-chat-contract.test.js` синхронизирован с новым отказом. Это блокирует дефектный результат до write на исправленном runtime.
- Screenshot дефекта (не PASS): ![FAQ v204 — невалидный Accordion](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-30-live-family-v209/faq-v204-invalid-editor.png) [PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-30-live-family-v209/faq-v204-invalid-editor.png). Browser Use вернул JPEG bytes; PNG после `sips` проверен и открыт.
- Локально: flex runtime 567 checks, design-pipeline 270, patch guard PASS, Node 6/6, PHP lint и 249 package hashes PASS, `git diff --check` PASS. Source push и WP Pusher success notice PASS; active editor config/runtime mismatch BLOCKED. Невалидный FAQ root `4c23da3` остаётся в editor; public/durable readback, repair и остальные live-проверки не подтверждены.

Предыдущий срез v208 ниже — исторический baseline. Его сохранённые Hero/Services факты остаются сведениями предыдущего save/reload; публичное состояние после последнего FAQ write ещё не проверено.

## Исторический baseline — live тестирование на post=5214, 2026-09-30

Срез: **2026-09-30 15:16 +05:00 (Asia/Almaty)**. Использовались только существующие вкладки и post `5214`; новые страницы/drafts и вкладки редактора не создавались.

- Source release тогда был `v02.11.208`, HEAD `88a9c1b`; editor inline config `v02.11.204`.
- WP Pusher ранее завершался ошибкой `Timed out running CDP command "Emulation.setFocusEmulationEnabled" for tab 15`; обновление не было подтверждено.
- После тогдашнего save/reload roots были Hero `b51107f` и Services `bb1e7c1`; после последующего FAQ write набор нужно перечитать.
- Hero prompt: `Hero. Надзаголовок: «АРХИТЕКТУРА». Заголовок: «Пространство для идей». Описание: «Опишите задачу и получите понятный первый шаг». Кнопка: «Начать проект» → #contact. Добавь фотографию с Unsplash.` Operation `wpae-20260930095358-d20cea3a`, root `b51107f`; точный copy, CTA и Unsplash Image присутствуют после reload. Production path сгенерировал fallback после провала provider semantic quality gate. Автоматический Vision screenshot завершился `wpae_vision_capture_failed`; это не operation-bound Vision PASS. Public desktop вручную проверен.
- Services сначала дважды безопасно отклонён до записи из-за формата пар строк. Финальный точный запрос: `Блок услуг.` и далее отдельные строки `Услуга 1: «Архитектурное проектирование» — «Концепция и планировка»`, `Услуга 2: «Рабочая документация» — «Чертежи и спецификации»`, `Услуга 3: «Авторский надзор» — «Контроль соответствия проекту»`. Operation `wpae-20260930100353-46a09c81`, root `bb1e7c1`; после reload сохранены точные три пары и три native Image. Public desktop — три равные карточки; mobile — естественный вертикальный stack. На mobile `window.innerWidth=390`, `innerHeight=844`, `documentElement.scrollWidth=375` (overflow отсутствует). Ветка live использовала deterministic fallback; AI Vision advisory: score 94/confidence98, не operation-bound review.
- Services screenshot artifacts: editor/public desktop CSS viewport `1228×923`; public mobile CSS viewport `390×844`, browser screenshot canvas `375×812`, два кадра покрывают карточки 1–2 и 1–3. Editor кадр показывает панель управления и горизонтально суженный canvas; public кадры показывают render без большой панели чата.
- Ранее в этой серии сообщения отмечали успешные write для Benefits `dca2ada`/`wpae-20260930001144-04ef4a4b`, Process `eb4e94c`/`wpae-20260930003211-7b7c315f`, Pricing `d6ff183`/`wpae-20260930005611-25214a08`, CTA `8da0ce5`/`wpae-20260930010520-035f7118` и Testimonials `578a737`. После итогового reload эти roots в editor/public не найдены; они не входят в финальный root set. CTA имел visual FAIL, Process — замечание к ширине cards. Team и FAQ на v204 были отклонены pre-write; source parser fixes, которых нет в текущем runtime, live не проверены.
- Девять production archetypes остаются Hero, Process, Pricing, FAQ, Benefits, Services, Team, Testimonials, CTA. Это выборочная проверка семейств, не приёмка всех 158 manifest entries или каждого imported JSON.
- Подтверждённый Services parser edge case: compact `Услуга N: «title» — «body»; Услуга N+1: ...` был отвергнут на v204; parser принимал такие пары только без `;` или на отдельных строках. v208 разрешает semicolon delimiter, повышает BriefIR parser provenance до v8 и имеет regression для трёх полных пар. Local tests v208: flex runtime 566 checks, design-pipeline 270, patch guard PASS, Node 6/6, PHP lint PASS, package hashes 249/0 mismatches, `git diff --check` PASS. Commit/push v208 PASS; live install через WP Pusher BLOCKED.
- Screenshot policy: Browser Use → сохранять фактически возвращённые bytes, проверять magic/формат, JPEG конвертировать `sips` в PNG, открывать и осматривать каждый PNG, указывать CSS viewport отдельно от PNG pixel canvas; финальный ответ должен включать inline PNG и абсолютные ссылки.

## Исторический срез — live проверка семейств и исправления parser/audit (v202–v204)

Срез: **2026-09-30 04:46 +05:00 (Asia/Almaty)**. Работа велась в существующем `post=5214` и одной Elementor-вкладке. Новые страницы, drafts и вкладки редактора не создавались.

### Source, runtime и исправления

- Source checkout: ветка `main`, базовый HEAD `cc3636b`; рабочее дерево содержит незакоммиченный candidate runtime `v02.11.204`. Remote подтверждён только до `cc3636b` (`origin/main`); v204 ещё не опубликован. В открытой вкладке query `wpae_release=193`, inline config сообщает **v02.11.202**. Пользователь сообщил v201, но актуальный UI в этой же вкладке показывает v202.
- `includes/llm/brief-ir.php`: добавлен разбор коротких незаключённых в кавычки пар Benefits формата `название — описание; ...`, без обязательной нумерации. Обе части сохраняются отдельными BriefIR полями с исходными текстами.
- `includes/llm/llm.php`: для Benefits `repeatable_units` теперь считается по полным парным карточкам BriefIR, а не по числу полей title+body. Подтверждённый сбой был в общем pre-write audit: три карточки считались шестью единицами, поэтому правильно сформированный deterministic fallback отклонялся, хотя в дереве были три полные карточки. Также в этом candidate сохраняется FAQ parser fix: допускается естественная связка `вопрос — ответ «...»` с меткой «ответ».
- Regression в `tests/flex-generation-runtime.php` покрывает natural Benefits parser, три DesignPlan cards, количество repeatable units и прохождение audit для трёх карточек, а также естественный FAQ-парсер.
- Проверки после изменений: `php -d memory_limit=512M tests/flex-generation-runtime.php` — **556 checks PASS**; `php tests/design-pipeline-contract.php` — **270 PASS**; `php tests/elementor-patch-guard.php` — PASS; `node --test tests/*.test.js` — **6/6 PASS**; PHP lint для `brief-ir.php`, `llm.php`, test harness — PASS; `wpae-package.json` — 249 hashes, 0 mismatches; `git diff --check` — PASS. Commit/push/install v204 ещё не выполнены.

### Live семейства: post=5214

Существующий сохранённый root — только Testimonials `578a737`, operation `wpae-20260929230626-0e11efff`; операция создана runtime v202 через `deterministic_fallback`. Exact copy пережил Elementor save/reload. Визуальная проверка остаётся **FAIL**: общий bento-нормализатор превратил секционные badge/content shells в две равноправные 48%-колонки; pill оказался слева, заголовок и карточки — сжаты справа. Исправление этой причины входит в предыдущий source v203, но live repair не выполнен, поскольку v203/v204 не установлены.

![Testimonials — editor desktop, дефект после reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-30-live-family-v203/testimonials-editor-desktop.png)

[Открыть PNG — Testimonials editor desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-30-live-family-v203/testimonials-editor-desktop.png)

FAQ live attempt использовал prompt: `Создай блок FAQ: вопрос «Как начать проект?» — ответ «Оставьте заявку, и мы обсудим задачу». Вопрос «Сколько стоит работа?» — ответ «Стоимость зависит от объёма и сроков».` Runtime v202 отклонил контент до записи; FAQ root и успешная operation identity не появились. Запрос не повторялся после локального parser fix.

Для Benefits проверены два точных live запроса. Естественный запрос `Создай блок преимуществ из трёх пунктов: точный расчёт сроков — планируем этапы до начала работ; единая команда — архитекторы и инженеры работают вместе; прозрачный контроль — показываем ход проекта на каждом этапе.` остановлен на DesignPlan: BriefIR не выделил незаключённые пары и вернул `benefits_require_two_to_six_complete_items` (request ID `d19cf07f-ccc5-4ed1-b220-65cb443dba43`). Второй запрос с явными строками `Преимущество N` и `Описание преимущества N` создал три полные native карточки в deterministic fallback, но audit v202 требовал 6 контейнеров вместо 3; модельный library route использовал один provider call и отказался от candidates, replacement не применялся. Pre-write validation остановила запись (request ID `a874c075-7c9f-4572-b8ec-1b0cf66009fb`). Root не появился, число сохранённых roots не увеличилось. Оба отказа предотвращены до write.

![Benefits — editor, отказ pre-write на runtime v202](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-30-live-family-v203/benefits-v202-validation-error.png)

[Открыть PNG — Benefits v202 validation error](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-30-live-family-v203/benefits-v202-validation-error.png)

Для кадра Benefits использован Browser Use screenshot существующей вкладки; фактический editor viewport **1109×923 CSS px**, PNG `1109×923`. JPEG signature проверена, сконвертирован через `sips`, PNG открыт и проверен. Кадр показывает активный root Testimonials и pre-write diagnostics Benefits, не результат дизайна Benefits. Скриншот FAQ аналогично показывает ошибку v202, не сгенерированный блок.

### Установка и фактическое покрытие

- WP Pusher на двух ранее открытых административных вкладках не ответил в Browser Use: CDP timeout на чтении/взаимодействии (`Emulation.setFocusEmulationEnabled`); навигация editor → Pusher оборвалась `net::ERR_ABORTED`. Это технические ошибки UI automation, не установленный запрет приложения. Обход Browser Use другим транспортом не применялся. Изменений настроек WordPress или других плагинов не было.
- На текущей установленной v202 после последнего screenshot проверены: Testimonials (save/reload PASS, visual FAIL), FAQ (pre-write FAIL), Benefits (pre-write FAIL). Исправленные Benefits и FAQ должны быть перепроверены после установки candidate v204. Охват по семействам из исторических срезов не означает, что каждый импортированный JSON был отдельно сгенерирован и принят.
- Каталог содержит 158 manifest entries / 13 categories; девять typed archetypes: Hero, Process, Pricing, FAQ, Benefits, Services, Team, Testimonials, CTA. В текущем editor root set: только `578a737`; после двух отказов set не изменился. Public DOM и durable ledger readback в этом срезе не снимались; public desktop/mobile и operation-bound Vision — NOT RUN.

| Область | Статус |
|---|---|
| Benefits natural pair parser + typed plan | PASS local |
| Benefits audit: 3 title/body pairs → 3 cards | PASS local; live v202 reproduced validator defect |
| FAQ optional answer label parser | PASS local; live v202 rejected before source fix |
| Testimonials exact content/save/reload | PASS live v202 |
| Testimonials editor visual | FAIL; fresh editor screenshot |
| FAQ/Benefits live write | NOT RUN after fix; v204 not installed |
| Source package hashes / local checks | PASS |
| Source v204 commit/push | NOT RUN |
| WP Pusher installation/editor version | BLOCKED / unconfirmed; editor reads v202 |
| Public DOM, public desktop/mobile, durable ledger, operation-bound Vision | NOT RUN |

## Исторический live-срез — v02.11.192, 2026-09-30

- Source HEAD/pushed runtime commit `3c170b94199aa8fc351a1415f5bf0503a0a65348`; установленная версия и inline editor config после reload — v02.11.192. Runtime этого продолжения не менялся.
- Пользователь очистил post=5214 перед нынешним циклом тестирования. Services `802dfd4` и предыдущий ошибочный CTA→Process `1f764e8` относятся к прежнему состоянию и были очищены; не считать их текущими.
- Новые успешные live roots этого цикла: Pricing `fb863c6` / `wpae-20260929194740-97229547`; Team `df970cc` / `wpae-20260929194222-9fca7545`; Process `8849f21` / `wpae-20260929195420-d028c181`. На момент чтения editor iframe и public DOM содержат тот же порядок и тот же набор; screenshot/public viewport был 1280×720. Новых pages/drafts не было.
- Pricing: exact three prices/descriptions, three cards on desktop, natural vertical stack on mobile, no Image widgets. Route — deterministic fallback; предыдущая numbered-form попытка остановлена `pricing_tiers_required` до записи.
- Team: one native card, «Айгерим» / «Архитектор», no Image widgets; content/save/reload PASS. Визуально карточка широкая для одного человека и содержит лишнюю star icon; считать visual PASS частичным, не полным.
- Process: три точных QA-шага, no Image widgets, save/reload PASS; desktop row, mobile vertical stack без горизонтального overflow. Desktop выглядит как простой текстовый ряд без выраженных карточек/соединителей; visual PASS частичный. Advisory score 90 не operation-bound review.
- Testimonials: библиотечный кандидат был выбран, но отклонён после адаптации (`после адаптации нарушена его структура`); write/root отсутствует. FAQ (два разных briefs) и Benefits (явный набор из трёх преимуществ) остановлены content-fidelity validation до записи. CTA-запрос остановлен сообщением «Безопасная цель повторной сборки не найдена; новый дубликат не добавлен». Для этих блоков root/screenshot нет.
- Все девять typed archetypes в `includes/llm/design-plan.php` теперь имеют live попытку в текущем или предыдущих циклах; live PASS по каждому не достигнут. Это не означает проверку каждого импортированного JSON-шаблона: для Pricing подтверждён deterministic fallback; для Team не установлен route, Process пришёл структурированным JSON; library-template PASS не подтверждён.
- Fresh public PNG этой проверки в `docs/audits/2026-09-30-live-family-v192/`: Pricing+Team desktop/mobile (mobile — два кадра) и Process desktop/mobile. CSS viewport: desktop 1280×900 (Process desktop 1280×720; scrollY=220), mobile 390×844. Browser screenshot physical canvas: 1280×900; mobile обычно 375×812. Каждый файл открыт и визуально проверен; WordPress toolbar/чат присутствуют, плавающий чат частично перекрывает нижний mobile кадр.
- Итоговые текущие roots: `fb863c6`, `df970cc`, `8849f21`. No other roots detected in editor iframe or public DOM. Durable ledger revision/fingerprint и operation-bound Vision не подтверждены. Остальные фактические статусы и prompts см. в актуальном разделе LUNA_HANDOFF_REPORT.md.

## Предпочтение к generation prompts

Пиши коротко и естественно: что создать, точный пользовательский контент и только важный видимый результат. Не перегружай запрос внутренними названиями полей, схемами, маршрутизацией, write-boundary, множеством запретов и повторяющимися требованиями. Такие гарантии обеспечивает pipeline плагина, а не текст prompt.

## Правило исправления невалидного визуального результата

Если свежий скриншот показывает дефект или несоответствие эталону, не засчитывай блок как PASS и не подправляй его вручную в Elementor. Найди первопричину в общем production-пути (Brief/DesignPlan, выбор шаблона, нормализация, compiler, responsive/style tokens или write/finalize — по фактическим доказательствам), исправь минимально общий механизм и добавь behavioral regression. Затем исправь только принадлежащий тесту root через штатную transaction boundary; сохрани соседние roots, выполни save/reload и снова проверь содержимое, DOM-геометрию и свежие desktop/mobile screenshots. Не маскируй дефект усложнением prompt или случайным CSS-патчем.

## Services — эталон хранится в плагине

Канонический исполняемый шаблон находится в библиотеке плагина: includes/elementor/imported-templates/services-photo-cards.json; manifest ID — template-services-photo-cards-v1; файл включён в SHA-256 package manifest wpae-package.json. context.md хранит описание и результаты, но не сам шаблон.

Эталон пользователя: pill «УСЛУГИ», отдельный heading «Наши услуги», три равные desktop-карточки; native Image сверху, затем иконка рядом с тёмным названием и приглушённое описание; светлая поверхность, тонкая серая рамка, скругление, мягкая тень. На mobile карточки складываются вертикально.

## Исторический live Services repair — v02.11.185

- Source HEAD: 0368a0e74281dec4482b527adad33c35b436a5f8, branch main. Runtime commit запушен в origin/main; WP Pusher установил v02.11.185; после reload существующий editor подтвердил inline-конфигурацию v02.11.185. Повторный git ls-remote в текущем срезе не завершился: DNS не разрешил github.com.
- Подтверждённая причина скриншота пользователя: старая editor-вкладка с URL wpae_release=180 всё ещё загружала root 672fbb9 с нулевыми боковыми padding. Ширина preview root — 1010 CSS px, pill — 1010 px (100% root). Эта вкладка не перезагружалась и не изменялась.
- В includes/llm/llm.php исправлено общее правило wpae_llm_normalize_library_layout(): структурные контейнеры по умолчанию теряли horizontal padding; верхний Services root теперь сохраняет эталонные значения. Regression находится в tests/flex-generation-runtime.php. Plugin template остаётся источником композиции.
- Через existing selected-root WPAE patch path обновлён только root 672fbb9; operation ID wpae-patch-f5248df45d986835, распознаны 3 patch-операции, HTTP 200. Новых roots не добавлено. Card roots: 3c2fc2e, b12f025, c01b6c0; pill: f39a08e.
- После reload editor и public DOM показывают прежний root, три заданные пары текста и три native images с alt. Publish в редакторе неактивен. Точный текст: «Стратегия проекта» / «Формулируем задачу и согласуем план работ»; «Архитектура и дизайн» / «Разрабатываем решение под заданный контекст»; «Сопровождение» / «Проверяем соответствие согласованному проекту».
- Public desktop CSS viewport 1203×923: root 1203×645 CSS px, padding 72 px; карточки примерно 339.7×338.6 px, gap 20 px, белые, border #d1d5db, radius 16 px, headings 22 px; все фото загружены и имеют alt; document scrollWidth равен viewport. Отдельное переполнение heading в текущем DOM не воспроизведено.
- Editor mobile CSS viewport внутри preview — 360×632. Root шириной 345 px с боковыми полями 32 px; карточки width 281 px, последовательно стоят на y=147, 506.7 и 866.5 CSS px; scrollWidth=clientWidth=345. Это editor mobile, не public mobile.
- Public mobile BLOCKED: документированный Browser Use viewport override 390×844 не изменил фактический window.innerWidth=1203 после reload; временный override сброшен. Не считать editor preview доказательством public mobile.
- Vision показал advisory score 95/confidence 98% в чате. Operation-bound Vision review не подтверждён.

### Свежие screenshots после patch и reload

Public desktop: post 5214, root 672fbb9, operation wpae-patch-f5248df45d986835; CSS viewport 1203×923; PNG 1144×923; public source. В кадре WordPress admin bar и чат, сам блок не перекрыт.

![Services v185 — public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v185/services-public-desktop.png)

[Открыть public desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v185/services-public-desktop.png)

Editor mobile, кадр 1: post 5214, root 672fbb9, operation wpae-patch-f5248df45d986835; preview CSS viewport 360×632; PNG canvas 1280×720; editor source; scrollY=0.

![Services v185 — editor mobile, верх блока](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v185/services-editor-mobile-top.png)

[Открыть editor mobile PNG — кадр 1](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v185/services-editor-mobile-top.png)

Editor mobile, кадр 2: тот же post/root/operation и CSS viewport; scrollY=632 показывает карточки 2 и 3 целиком.

![Services v185 — editor mobile, карточки 2 и 3](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v185/services-editor-mobile-middle.png)

[Открыть editor mobile PNG — кадр 2](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v185/services-editor-mobile-middle.png)

Runtime проверки после release: PHP lint на includes/llm/llm.php, tests/flex-generation-runtime.php и wp-ai-executor.php — PASS; flex runtime 483 checks, design pipeline contract 261 checks, Elementor patch guard PASS, Node 6/6, package hashes 249 файлов / 0 mismatches, git diff --check PASS.

## Исторический live Services на post=5214 — snapshot v02.11.180

- Source HEAD: `e185415b1948bf9a0157a571439ff7b85d51ee01` (`fix: normalize imported services sections`), branch `main`; `wp-ai-executor.php` — v02.11.180. В существующем Elementor editor чат также показывает v02.11.180 после reload. Установка через WP Pusher подтверждалась в ходе этого запуска.
- Дефект на пользовательском снимке был реальным: в v179 служебная фраза запроса стала заголовком, импортированный Services-набор содержал чёрную поверхность первой карточки, зелёный текст, source-site global styles и лишние spacer/divider. Это не приемлемый render.
- Исправление в `includes/llm/llm.php`: `wpae_llm_clear_unrequested_library_copy()` оставляет Services-заголовок только при явно помеченном title; `wpae_llm_normalize_library_layout()` удаляет spacer/divider и чужие global references, а цвета/поверхности задаёт через активные project tokens и единый card style. Regression checks находятся в `tests/flex-generation-runtime.php` и `tests/llm-chat-contract.test.js`.
- Через production UI на существующем post=5214 выполнена одна новая Services generation: operation ID `wpae-20260928203645-d4f94ae5`, новый root `3dc3822`; карточки `5386bb7`, `f57b434`, `576d53e`, pill `f7ccd22`. Diagnostics показывают `action_path=library_agent`, archetype `services`, `library_applied=true`, один provider call. Ручной JSON-import не применялся; точный catalog candidate ID в ответе не surfaced. UI сообщил Elementor update HTTP 200.
- После обычного reload существующих editor и public tabs в обоих отображается root `3dc3822`; старый root `882b453` не найден. На public DOM нет утёкшей инструкции, spacer/divider и горизонтального overflow. При CSS viewport 1203×923 три карточки стоят в row, каждая примерно 324×442 CSS px, gap 20px; фон всех карточек `#fff`, border `1px solid #d1d5db`, radius `16px`, заголовки тёмные, описания серые; все три native image widgets имеют alt. Page background не менялся.
- Screenshot evidence — public PNG 1143×923 при CSS viewport 1203×923; editor PNG 1144×923 при outer CSS viewport 1203×923. На public кадре остаются WordPress admin bar и плавающий чат, блок не перекрыт. В editor кадре панель WPAE перекрывает часть двух карточек, поэтому чистым visual proof служит public кадр. Оба PNG открыты и визуально осмотрены.

![Services v180 — public desktop after save/reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v180/public-services-desktop.png)

[Открыть PNG — Services public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-29-services-v180/public-services-desktop.png)

- Mobile визуальная приёмка не подтверждена: текущий Elementor preview остался в desktop mode 1025×860 CSS px; доступное переключение на Mobile не изменило selected device. Public mobile не запускался. Vision в чате выдал advisory score 96/confidence 100%; operation-bound Vision review не подтверждён.
- Runtime checks, выполненные перед live на commit v180: `tests/flex-generation-runtime.php` — 475 checks; `tests/design-pipeline-contract.php` — 261; imported catalog — 157 manifests / 155 retrievable / 155 instantiated previews; patch guard PASS; Node 6/6; PHP lint PASS; package 248 files / 0 hash mismatch. В текущем продолжении runtime не менялся, после обновления документов повторён `git diff --check`.
- Локальная tracking ref `origin/main` в этом checkout указывает на `50c2e97` (v178), тогда как рабочий HEAD — `e185415`. `git ls-remote origin refs/heads/main` завершился DNS-ошибкой GitHub; поэтому актуальный remote HEAD/push в этом срезе отдельно не подтверждён. Live editor v180 подтверждён.

## Исторический live smoke test Services на post=5214 — v02.11.179

- Source HEAD/runtime commit: `f1a09e7845fc7e5d29ac78d23b4127e081e53954` (`fix: inform service library selection about photo adaptation`), v02.11.179. Push `origin/main`, WP Pusher installation, and inline editor v179 were verified earlier in this run. Runtime checks passed before live: 470 flex checks, 261 design-pipeline checks, imported catalog 157/155/155, patch guard PASS, Node 6/6, package 248 files/0 mismatches, PHP lint; no runtime changes since.
- Existing editor/public page post=5214 was empty before this test. First unstructured Services request failed before write (`8cedbe92-a289-4b22-810d-4e52104a7ac3`, `services_items_out_of_range`); no root was created. A fresh explicit three-item request then produced one operation `wpae-20260928182647-9f11f829`, one root `882b453`, and card roots `31672f2`, `54398cf`, `603b28a`.
- Successful diagnostic route: `action_path=library_agent`, archetype `services`, provider/model `openrouter/free`, `reliable_structured`, one provider call. `library_applied=true`, UI/Navigator title `Courses Boxes`; exact catalog candidate ID/path was not returned. Final native tree came through `deterministic_fallback` after unusable provider/repair tree and a repair timeout, so the output is not verified as a direct native-template compilation.
- All three requested title/body pairs and three native Unsplash Image widgets are present; structural checks and 6 content fields passed. An unintended heading, `Создай отдельную секцию услуг на post=5214`, leaked into the generated block. First card has black background; descriptions are green/low contrast. Desktop/mobile visual result FAIL; exact source of these styles has not been isolated.
- After public reload the existing public tab displayed exactly one new Services root. Public desktop CSS viewport 1203×923, no horizontal overflow; cards are about 361px wide with 20px gaps. Canonical `_elementor_data` and durable operation-ledger readback were not separately confirmed.
- Editor Mobile mode works: actual preview document viewport 345×736 CSS px; root about 345×1566; cards stack at y≈313/720/1127; `scrollWidth=clientWidth=345`, no horizontal overflow. Public mobile remains BLOCKED because the documented IAB viewport override did not change public `innerWidth` from 1203px. Do not label editor mobile as public mobile.
- Advisory Vision score 68/confidence 95% flagged the black first card and leaked heading. It is not operation-bound review. Automatic and explicit targeted repair were refused by the existing root ownership/saved-state guard; no second write occurred. New test root `882b453` remains; no prior/user root was deleted or edited.
- Fresh Browser Use PNG files are under `docs/audits/2026-09-28-services-v179/`: public desktop, editor desktop, and three editor-mobile frames covering cards 1–3. Browser bytes were JPEG (`FF D8 FF E0`), converted with `sips`; PNG signature/dimensions checked and each opened. Public desktop CSS viewport 1203×923; editor outer viewport 1203×923; mobile preview CSS viewport 345×736; PNG canvas 1143×923 public and 1144×923 editor.
- No direct durable ledger or operation-bound Vision evidence. Detailed statuses, exact operation/roots, failures, and inline screenshots are in `LUNA_HANDOFF_REPORT.md`.

## Исторический baseline Services v177 (2026-09-28 04:47 +05:00)

В этом проходе public desktop и editor mobile PNG открыты и визуально осмотрены: фотографии были фоном за текстом и сильно выбелены; отдельной светлой текстовой панели не было. Это подтверждает исходный дефект v177, не является визуальной приёмкой v178.

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

**Live-проверки с актуальными скриншотами обязательны.** Не считать live-проверку завершённой без свежего Browser Use capture после соответствующего save/reload, сохранённого PNG, проверки формата, открытия и визуального осмотра файла, inline-вставки и кликабельной абсолютной ссылки. Указывать фактический CSS viewport отдельно от размеров PNG, post/root/operation IDs и editor/public source; editor mobile не называть public mobile.

Если версия плагина в открытом Elementor editor отстаёт от исходной версии, обновить плагин через WP Pusher по штатному release-процессу. Затем обновить существующую editor-вкладку безопасным reload и подтвердить актуальную версию в самом editor (inline config/чат и доступные загруженные assets); одного Plugins UI недостаточно. Не объявлять live-проверку успешной, пока не подтверждены текущая версия и требуемые screenshots.

Работать на существующей странице и вкладках; новые WordPress pages/drafts для проверки не создавать.

## Исторические наблюдения до 2026-09-28

Разделы ниже сохранены как исторические и не описывают текущую версию/root.

### Исходники и активный editor — исторический срез v171

- Репозиторий /Users/diasmazhenov/vibecode/wp-ai-executor, branch main, source HEAD 5ff2f1c55af72cae47887271ff537d35a5ebbb26, source/runtime v02.11.171.
- WP Pusher installation подтверждена ранее; текущая editor inline config и видимый chat показывают pluginVersion=v02.11.171, postId=5214, model openrouter/free, ready=true. Встроенный script добавлен inline через elementor-editor; отдельного JS URL нет. Elementor editor assets показывают 4.1.1.
- Код во время текущего live прохода не менялся. Runtime tests из предыдущего v171 release были успешны; сейчас повторно запускались только документные проверки.

## Исторический Services live на post=5214 — срез v171, 2026-09-27

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
