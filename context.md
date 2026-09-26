# WP AI Executor — context

Последнее обновление: **2026-09-26 13:52 +05:00 (Asia/Almaty)**.

## Текущее состояние

- Checkout `/Users/diasmazhenov/vibecode/wp-ai-executor`, branch `main`; исходный HEAD до этого этапа `5c976ff8c3b9d5d201172b9efd9305159408c753`.
- Локальная версия исходников подготовлена как **v02.11.158**, изменения runtime и тестов не закоммичены. Read-only GitHub API подтвердил `origin/main=5c976ff8c3b9d5d201172b9efd9305159408c753` на 2026-09-26 13:02 +05. `git add` остановился на запрете создания `.git/index.lock`; GitHub `create_blob` вернул 403 `Resource not accessible by integration`; обычный `git ls-remote` не разрешил `github.com`. Commit/push/install не выполнены.
- WP Pusher открыт для `DiasMazhenov/wp-ai-executor`; в списке указаны branch `main` и `Push-to-Deploy: enabled`. Установленные плагины показывают **v02.11.157**. WP Pusher отобразил общее уведомление `Plugin was successfully updated`, но оно не подтверждает установку v158. Повторное обновление не запускалось: пока v158 не опубликована в `main`, это могло бы установить только старый код.
- Предыдущая успешная публикация v155 зафиксирована в историческом handoff как push в `origin/main`; это подтверждает, что раньше работал обычный Git push, после которого сайт обновлялся отдельно. Нынешний отказ специфичен для этого запуска: sandbox запрещает запись в `.git` (`git add` → `Operation not permitted`), GitHub connector отклонил запись (`403 Resource not accessible by integration`), а DNS не разрешил `github.com`. POSIX write bits сами по себе не отменяют sandbox-ограничение.
- В открытом Browser Use IAB три вкладки: редактор существующей страницы `post=5214`, Plugins и WP Pusher. Plugins подтверждает установленную **v02.11.157**; текущая запись WP Pusher — `main`, Push-to-Deploy включён. В ранее прочитанном editor tab был version marker **v02.11.157** и пустой canvas/dropzone при CSS viewport 928×923. В этом проходе editor не перезагружался, saved document/public render заново не читались; generation/save/root mutation не было.
- Для активного deterministic pipeline локально добавлена поддержка `hero` с media, `services`, `team`, `testimonials` и отдельного `cta`; `pricing`, `benefits`, `faq` оставлены регрессионными archetypes. Production-path harness при EDDE=`active` и pipeline=`active` проверяет `action_path=pipeline`, 0 provider calls и ровно один writer call; это local harness, не live operation.
- Для фото hero в fixtures используется URL снимка Neon Wang на Unsplash с alt, license и attribution. Фото не скачивалось и не копировалось в runtime/package. Условия использования сверены с [Unsplash License](https://unsplash.com/license) и [страницей автора](https://unsplash.com/photos/modern-concrete-interior-with-large-windows-overlooking-landscape-vDubGhodBV8).
- Live-тесты пяти новых композиций, сохранение, readback, DOM/screenshots, public mobile и operation-bound Vision — **NOT RUN**: установленная editor-страница всё ещё сообщает v157, а v158 пока не опубликована/не установлена. Пустой editor canvas — текущее наблюдение editor UI, не доказательство свежего серверного readback.

## Реализация и локальные проверки v158

- `includes/llm/brief-ir.php`: групповые media fields сохраняют Unicode context, точный alt/license/photographer provenance.
- `includes/llm/design-plan.php`: typed archetypes и validation для services/team/testimonials/CTA; item ids и повторяемые группы остаются связанными; synthetic testimonials — только явно помеченные тестовые данные.
- `includes/elementor/elementor-ir.php`: существующий compiler формирует native Flex/card/container/Image/Heading/Text Editor/Button nodes; mobile policy и source image/alt остаются явными.
- `includes/elementor/reference-set.php`, `includes/llm/llm.php`: media provenance и новый выбор существующего deterministic pipeline dispatcher. Отдельный writer или новая библиотека не добавлялись.
- `tests/design-pipeline-contract.php` — **223 checks OK**; `tests/flex-generation-runtime.php` — **393 checks OK**; `node --test tests/*.test.js` — **4 passed, 0 failed**.
- `php -l` прошёл для runtime PHP files; `php docs/audits/2026-09-12/package-probe.php` — **PASS**, 90 package files, 0 hash mismatches, 4 manifest/archive scenarios; `git diff --check` — **PASS**.

## Правило screenshot evidence

Для live Elementor: текущая Browser Use вкладка существующей страницы после save/reload → screenshot bytes → проверить формат → JPEG конвертировать через `sips` в PNG → открыть PNG и визуально проверить → показать inline и дать кликабельную абсолютную ссылку. Записать CSS viewport, post/root/operation IDs и editor/public source. Не создавать новые pages/drafts и не выдавать размер screenshot canvas за viewport. Если новый блок в live не создавался, не использовать пустой кадр как замену приёмке дизайна.

## Выпуск через WP Pusher

После готовности исходников сначала опубликовать проверенный commit в `main`, затем выполнять установку/обновление через зарегистрированный WP Pusher `DiasMazhenov/wp-ai-executor` (branch `main`, Push-to-Deploy включён). Если автоматический deploy не сработал, использовать действие обновления существующей записи WP Pusher. После deploy отдельно подтвердить версию в Plugins и в inline config редактора; уведомление WP Pusher само по себе не доказывает активную версию. Не запускать WP Pusher до появления нужного commit в `main`, чтобы не переустановить старую версию. Если запись репозитория отсутствует, использовать экран добавления WP Pusher с тем же repo/branch и включить Push-to-Deploy и Link installed plugin. Секреты GitHub не читать и не выводить.

## История

Наблюдения v154–v157 и более ранние states страницы в `LUNA_HANDOFF_REPORT.md` исторические. Старые root IDs и screenshots не описывают текущий editor document. `SESSION_CONTEXT.md` не используется.
