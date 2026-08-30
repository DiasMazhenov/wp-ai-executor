# План WP AI Executor

## v02.09.65

- Нормализатор очищает фон уже существующей content-shell, если провайдер вернул собственную белую оболочку вокруг bento-сетки.
- Проверить в открытом Elementor несколько естественных запросов с разными раскладками: равномерная сетка, split-композиция и feature-first.

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
