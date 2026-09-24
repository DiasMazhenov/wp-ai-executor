# WP AI Executor Context

Срез: **2026-09-25 01:56 +05:00 (Asia/Almaty)**.

## Source и runtime

- Checkout: `/Users/diasmazhenov/vibecode/wp-ai-executor`, branch `main`.
- HEAD: `8c9a89aef1be430c37e93ad36ef47ea916314031`, runtime version **v02.11.151**.
- Этот runtime commit ранее отправлен в `origin/main`; WP Pusher сообщил об успешном обновлении, а редактор Elementor на `post=5214` показывает v02.11.151.
- Канонический контекст — lowercase `context.md`. На текущей macOS `CONTEXT.md` разрешается в тот же inode; не создавай отдельную копию и не используй `SESSION_CONTEXT.md`.

## Live-проверка `post=5214`

- Существующая страница: `https://mazhenov.kz/pricing-contract-live-v123/`, WordPress post `5214`. Новые pages и drafts не создавались.
- Пользователь сообщил, что очистил прежнее содержимое страницы перед тестами. Сейчас в сохранённом editor preview и public DOM видны два QA-root: FAQ `82b88e5` и процесс `de395b6`. Прежний hero-root `cd4da23` в текущем DOM отсутствует.
- FAQ: operation `wpae-20260924203054-838965f0`; native Accordion widget `7ccf10b`. После save/reload сохранены три заданных синтетических вопроса и ответа. Public interaction раскрывала ответы; Vision оценил результат в 85/95, с minor finding о тесном отступе над badge.
- Процесс: operation `wpae-803141a1fa8a0c3f`; root `de395b6`. После save/reload видны три этапа, собранные из текстовых виджетов и горизонтальных Divider. Vision 68/95: слабая типографическая иерархия и однообразные интервалы; результат не принят как качественный timeline. Автоматическая замена остановлена защитой ownership: сохранённый target не принадлежал этой операции/изменился.
- Старый дизайн доступен только как историческое изображение `wpae-post-5214-public-check-20260925.png`; оно показывает прежний root `cd4da23` с pill badge, двумя CTA и фотографией. Этот кадр не является текущим live-состоянием.

## Проверенные артефакты

- `wpae-faq-qa-v151-public-desktop-20260925.png` — FAQ, public, 1253×933.
- `wpae-faq-qa-v151-editor-mobile-20260925.png` — FAQ, Elementor mobile preview canvas 360×736; внешний editor screenshot 1253×933. Отдельный public mobile viewport не подтверждён.
- `wpae-process-qa-v151-public-desktop-20260925.png` — процесс QA-root, public, 1238×922; визуальная проверка fail.
- Все перечисленные актуальные PNG проверены по сигнатуре и открыты для визуальной проверки. При Browser Use capture сверяй фактический формат bytes; JPEG конвертируй в настоящий PNG до сохранения с расширением `.png`.

## Правило screenshot для live Elementor-приёмки

1. Только существующая страница и существующая вкладка встроенного браузера; новых pages/drafts не создавать.
2. После save/reload используй Browser Use через `node_repl`: импортируй `setupBrowserRuntime` из актуального `browser-client.mjs`, вызови setup, выбери browser с `type === "iab"`, найди нужную вкладку по URL и сними `screenshot({ fullPage: false })`.
3. Сохрани bytes в абсолютный путь внутри workspace. Проверь реальную сигнатуру и, если требуется, конвертируй в PNG штатной системной утилитой.
4. Открой PNG и проверь, что на нём именно нужная страница/root и состояние после reload; выведи screenshot inline через `nodeRepl.emitImage()`.
5. В финале дай inline Markdown-изображение и кликабельную абсолютную ссылку на файл, укажи post/root/operation IDs, viewport и editor/public источник. Не печатай base64 и не выдумывай путь. Если файл сохранить или проверить нельзя — укажи `SCREENSHOT BLOCKED` и конкретный технический блокер.

## Локальные проверки v02.11.152

- `php -l wp-ai-executor.php includes/llm/llm.php tests/flex-generation-runtime.php` — PASS.
- `php tests/flex-generation-runtime.php` — 345 checks OK.
- `php tests/design-pipeline-contract.php` — 151 checks OK.
- `node --test tests/*.test.js` — 4/4 PASS.
- `php docs/audits/2026-09-12/package-probe.php` — 90 files, 0 hash mismatches.
- `git diff --check` — PASS для runtime-релиза до публикации. Текущие локальные изменения этого среза — документация и screenshot artifacts.

## Закреплённый process timeline reference

- Источник: пользовательский Elementor selection JSON, post `4556`, element `5a52297`.
- Эталонный контракт закреплён в `tests/fixtures/process-card-reference-v1.json`; active pipeline должен собирать его как native Elementor: карточка белая, border `1px solid #dbe3f0`, radius `20px`, reference padding; marker `#4460EC` круглый, `3rem` desktop / `2.5rem` mobile; marker row содержит native Divider `1px`, `100%`, gap `15px`.
- Desktop cards: горизонтальный ряд с basis `22%` для четырёх карточек; tablet/mobile: вертикальный stack, каждая карточка `100%`. Явная подпись блока отображается одной компактной pill перед рядом карточек.
- Не менять этот эталон на линейный список текстов/Divider. Regression должна проверять собранный native tree, стили маркера и карточки, точные label/body slots и responsive widths.
