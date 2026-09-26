# WP AI Executor — context

Последнее обновление: **2026-09-27 03:09 +05:00 (Asia/Almaty)**.

## Актуальное состояние

- Source: branch `main`, HEAD `f9e68767a40024c01c120be5d04beddc8e90eff6`, runtime `v02.11.167`; push `origin/main` прошёл. WP Pusher показал успешное обновление. Plugins UI после установки отдельно не прочитан.
- Mini-JEV library decision: локальный retrieval только формирует shortlist (до 3 вариантов); модель возвращает `library_choice`/`null`; сервер проверяет allowlist и адаптирует выбранный native tree. EDDE отдельно выбирает typed hero layout.
- v166 active pipeline обходил библиотечный выбор. v167 добавляет `library_agent` route при наличии валидного shortlist: один provider call, одна текущая write boundary; без параллельного EDDE. Decline/ошибка не превращается в выбор первого local-ranked template.
- Local behavioral evidence: active route выбрал второй кандидат из двух, сохранил его дерево, exact copy и `#contact`; один provider call/write; предыдущие roots сохранены. Node 6/6; PHP route 246, flex runtime 413; imported catalog 157/155; lint, package hashes 248/0 mismatches и `git diff --check` PASS.
- В существующем Elementor tab для `post=5214` inline config пока v166. Там видна предыдущая операция FAQ `wpae-4da1eea881d9e2ce`, root `a88fb3a`, локальный pipeline (`provider_calls=0`). Вкладка не перезагружалась: актуальный dirty/save state не установлен. v167 live generation, save/reload, DOM review, public desktop/mobile и свежие screenshots **NOT RUN**.
- Каталог плагина: 157 bundled JSON, 155 retrievable Elementor trees. Harness покрывает каталог и instantiation; он не подтверждает live generation каждого файла.

## Постоянные workflow rules

Для live Elementor использовать только текущую существующую страницу; новые pages/drafts не создавать. Не сохранять неполную editor model и не обходить ownership/stale guards. Screenshots через Browser Use: сохранить screenshot bytes в абсолютный путь, проверить формат, при JPEG преобразовать через `sips` в PNG, открыть/проверить и вставить inline с абсолютной ссылкой. Всегда указывать CSS viewport отдельно от pixel dimensions. Установку после релиза выполнять существующим WP Pusher.

## Последние ранее снятые screenshots (историческая v02.11.160-проверка)

Снимки сделаны Browser Use из существующих вкладок. JPEG bytes сохранены, сигнатура проверена, PNG созданы через `sips`, итоговые файлы открыты и визуально проверены. Pixel canvas указан отдельно от CSS viewport.

- Services public desktop, post `5214`, root `42d4363`, operation `wpae-ee4bb88fb23e06e3`; CSS viewport `1105×923`, PNG `1050×923`: `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-public-desktop-20260926.png`.
- Services editor mobile, post `5214`, root `42d4363`, operation `wpae-ee4bb88fb23e06e3`; preview iframe CSS width `360px` (outer editor viewport `1100×923`), PNG `1045×923`: `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-editor-mobile-360-20260926.png`.
- Services editor desktop diagnostic, post `5214`, root `42d4363`, operation `wpae-ee4bb88fb23e06e3`; outer CSS viewport `1100×923`, PNG `1045×923`: `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-template-coverage-v160/services-editor-desktop-20260926.png`. The narrow editor canvas clips part of the third card; the public desktop frame shows all three cards.
- CTA screenshots under `docs/audits/2026-09-26-v160-cta/` are historical pre-coverage captures. They do not describe the current post state.

Полная история v02.11.159 и более ранних проверок сохранена в `LUNA_HANDOFF_REPORT.md` под пометкой «Историческое состояние»; она не описывает текущий post state.

## Постоянные workflow rules

Для live Elementor использовать только текущую существующую страницу; новые pages/drafts не создавать. Не сохранять неполную editor model и не обходить ownership/stale guards. Screenshots через Browser Use: сохранить screenshot bytes в абсолютный путь, проверить формат, при JPEG преобразовать через `sips` в PNG, открыть/проверить и вставить inline с абсолютной ссылкой. Всегда указывать CSS viewport отдельно от pixel dimensions. Установку после релиза выполнять существующим WP Pusher.
