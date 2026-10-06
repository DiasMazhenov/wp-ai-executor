# Accordion native roundtrip v271 — 2026-10-06

## Итог этапа

Локальная реализация и regressions готовы: compiler заранее переводит plain FAQ answer в точное native представление Elementor Accordion WYSIWYG; accepted projection и operation recovery допускают только этот bounded roundtrip. Source/test commit c2328d4e1efd5d32acb48ef4cffce017fdbd4503 (v02.11.271) уже был отправлен в origin/main. Установка через WP Pusher не состоялась, поэтому live recovery и единственный разрешённый повтор FAQ не запускались.

На этой стадии сделано 0 новых генераций. Приложенное задание ограничивает live scope FAQ и запрещает повторять B–H, Services и остальные семьи.

## Исходный native diff и причина

Сверены все поля файлов [native after reload](../2026-10-06-lifecycle-layout-v270/faq-I-native-after-reload.json) и [после Publish normalization](../2026-10-06-lifecycle-layout-v270/faq-I-native-after-publish-normalization.json). SHA-256 соответственно:

- b8022d5f09b4434dd5f36634e8cabef29ae5e176d22cbb023cc28e3f7b024f11
- 200c4bbd136dfda5f7feb5f432119efd9f31bff16a353db2ca0723a0f40928d0

Полный recursive JSON diff содержит ровно 3 различия:

| Путь | Изменение |
|---|---|
| root.elements[0].elements[0].settings.tabs[0].tab_content | «Обсудим задачу.» → «<p>Обсудим задачу.</p>» |
| root.elements[0].elements[0].settings.tabs[1].tab_content | Длинный точный ответ → та же строка в единственной паре <p>…</p> |
| root.elements[0].elements[0].htmlCache | Render cache отражает те же paragraph wrappers |

Остальные authored controls равны. Сохранились root/widget/node IDs, типы, иерархия, порядок и count tabs, вопросы, exact answer copy и technical repeater IDs. Причина отказа Undo — native WYSIWYG сериализовал plain string в paragraph HTML после Publish, поэтому authored tabs изменили ownership fingerprint. Обновление одной revision не могло вернуть eligibility.

Машиночитаемая сверка: [native-authored-diff.json](native-authored-diff.json).

## Source boundary и локальные проверки

Добавлен versioned adapter wpae-native-roundtrip-v1 на границе compiler → freeze/signature → accepted projection/readback. Для нового Accordion результата исходный exact copy остаётся в Brief/Plan, а compiler до freeze формирует принятую строку <p>exact plain text</p>. Comparator получает widget/control context; он не выкидывает tabs из fingerprint, не сравнивает только textContent и не применяет общий strip_tags.

Разрешение ограничено Accordion tabs[*].tab_content: одна точная paragraph wrapper вокруг plain text. Любые изменения вопроса/ответа, значимого HTML, ссылки/атрибута, порядка/count, иных controls, widget/operation/identity/contract/root, revision/document model, неизвестная версия adapter и expired/missing contract остаются отказами. Дескриптор recovery связан с исходным contract/hash, current payload, полным document model, operation, identity, page, roots и revision; повторная проверка выполняется под существующими transaction guards. Ни guard, ни dirty editor protection не ослаблены.

Проверки после source изменений:

| Проверка | Результат |
|---|---|
| PHP lint изменённых PHP-файлов | PASS, 5 файлов |
| Design Pipeline Contract | PASS, 849 checks |
| Flex Runtime | PASS, 1408 checks |
| Node suites | PASS, 18/18 |
| Elementor patch guard | PASS |
| Package/hash probe | PASS, 253 file hashes, 0 mismatches, 4 probe cases |
| git diff --check | PASS |

Source/test commit: c2328d4e1efd5d32acb48ef4cffce017fdbd4503. Предыдущий push завершился успешно; тогда remote HEAD совпал с commit. Повторный git ls-remote в текущем проходе завершился DNS error: Could not resolve host: github.com.

## Live state, установка и safety gate

Использовались существующие вкладки post=5214, public page, WP Pusher и Plugins. Перед install Elementor показал чистое состояние редактора и inline v02.11.270. Нажималась только кнопка Update plugin в строке WP AI Executor. После первой попытки интерфейс показал ошибку копирования; после проверки Plugins была выполнена одна контролируемая повторная попытка того же единственного target. WP Pusher снова показал An error occured: Не удалось скопировать файл. Скриншот ниже снят после повторной попытки. Независимая Plugins вкладка по-прежнему показывает PHP plugin v02.11.270; Elementor inline config также остался v02.11.270. Editor не перезагружался, другие плагины/настройки не менялись; дальнейшие install повторы остановлены.

| Статус | Факт |
|---|---|
| Source | v02.11.271, commit c2328d4 |
| Push | Успех был подтверждён ранее; текущая DNS перепроверка недоступна |
| Install | FAIL: WP Pusher file-copy error |
| Installed PHP | v02.11.270 |
| Editor inline JS | v02.11.270 |
| Existing FAQ readback | root set [1a1d059], unchanged |
| Existing descriptor | Последнее наблюдение: operation wpae-c616e5dbb2a2a125, identity 5e6a84de-849f-4012-a012-284fbcbfb2af, contract contract-93cf3469f9d3e1e759c2d774, revision 5, changed_target / owned_fingerprint_changed. Revision не выдаётся за свежую read-only refresh. |
| Recovery / guarded Undo | NOT RUN: proof-gated v271 lifecycle не установлен |
| New FAQ generation | NOT RUN: existing changed target ещё не восстановлен через operation-scoped Undo |
| Other families | NOT RUN, согласно границе приложенного задания |

Root 1a1d059 не удалялся и не reseal-ился. Исторический b48abe1 был удалён пользователем; Services root bcfeab4 пользователь удалил до Publish/readback. Ничего из этого JSON не импортировалось. Текущий editor/public root set — [1a1d059], а безопасно подтверждённый baseline до серии был [].

![WP Pusher: точная ошибка установки WP AI Executor; CSS viewport не замерен, PNG 1217×912](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-native-roundtrip-v271/screenshots/wppusher-install-attempt.png)

[Скачать PNG ошибки WP Pusher](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-native-roundtrip-v271/screenshots/wppusher-install-attempt.png)

Screenshot bytes Browser Use сохранены как JPEG, signature проверена; PNG получен через sips, формат и 1217×912 pixels проверены, PNG открыт и визуально осмотрен. Это evidence ошибки установки, а не screenshot acceptance блока.

## Визуальное сравнение сохранённого v270 FAQ

Сохранённый public desktop screenshot показывает native Accordion на светло-бежевой секции, белую карточку, оба заголовка и полностью открытый длинный ответ. Site-owned chat пузырь находится внизу страницы вне FAQ карточки. Screenshot historical: post 5214, root 1a1d059, public CSS viewport 1232×923, PNG 1232×923.

![Предыдущий FAQ v270 — public desktop, long answer открыт; это исторический кадр, не v271 генерация](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-final-public-long-answer-open-full.png)

[Открыть historical FAQ public PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-final-public-long-answer-open-full.png)

Свежий read-only DOM на текущей существующей public вкладке: CSS viewport 1232×923; открытый вопрос «Как согласовать?»; answer panel 1090×138.4 px; inner paragraph 629.45×96 px, font 16 px, line-height 24 px, margin-bottom 14.4 px, цвет rgb(75, 85, 99). Активный и закрытый question имеют computed color rgb(51, 51, 51), white background, 16 px bold. Публичный светло-зелёный цвет не подтвердился: он виден на старом editor mobile preview как редакторская selection/style state. Paragraph wrapper действительно создаёт измеримый нижний margin 14.4 px; его нельзя считать визуально нейтральным.

Доступный mobile кадр — Elementor mobile preview 360×736 CSS, PNG canvas 1232×923; открыт только короткий первый ответ, длинный закрыт. Это не public-mobile evidence. Документированный public viewport override в v270 не менял реальный CSS viewport 1232 px; тот же неудачный override не повторялся. Поэтому public mobile для v271 результата отсутствует: новый результат не генерировался.

![Предыдущий FAQ v270 — Elementor mobile preview; открыт короткий ответ, длинный закрыт, не public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-editor-mobile-preview.png)

[Открыть historical Elementor mobile-preview PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-editor-mobile-preview.png)

## Статус завершения

Локальная задача source/contract/tests завершена и закоммичена. Live acceptance заблокирована подтверждённой ошибкой копирования WP Pusher: PHP/editor v271 не установлены, операция существующего FAQ остаётся changed_target, baseline [] не подтверждён, поэтому generation count этого этапа равен 0. Нового FAQ screenshot после Publish/reload нет; исторические PNG выше явно маркированы v270.

## Push-to-Deploy follow-up — 2026-10-06

The user supplied WP AI Executor's plugin-specific Push-to-Deploy endpoint. WP Pusher documents this as a secret endpoint where an HTTP request triggers an update; its token is intentionally omitted from this audit. The first attempt navigated the existing WP Pusher Browser Use tab to the endpoint. `Page.navigate` timed out, and Chromium displayed `ERR_HTTP_RESPONSE_CODE_FAILURE`. This replaced the visible Pusher page and did not provide a successful deployment response.

A subsequent non-navigation Node REPL `fetch` against the validated WP AI Executor package endpoint failed before connection with `ENOTFOUND`. Existing Plugins and editor tabs were separately screenshot-checked after the navigation attempt and both still displayed v02.11.270. No editor reload, live page write, recovery/Undo, or generation occurred. The v271 installation and Accordion recovery remain blocked.

Sources: [WP Pusher Push-to-Deploy](https://docs.wppusher.com/article/24-automatic-updates-with-push-to-deploy); [WP Pusher plugin management](https://docs.wppusher.com/article/13-working-with-plugins-and-themes).
