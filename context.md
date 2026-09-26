# WP AI Executor — context

Последнее обновление: **2026-09-26 19:49 +05:00 (Asia/Almaty)**.

## Актуальное состояние

- Checkout `/Users/diasmazhenov/vibecode/wp-ai-executor`, branch `main`, final HEAD `7030db0` (handoff/context documentation); runtime source commit `7beef71bffa1745427956879c7bcb483ee28ee99`. Runtime `v02.11.160` опубликован в `origin/main` и установлен через WP Pusher. Plugins UI и свежий editor inline config подтвердили `v02.11.160`.
- Runtime update добавляет CTA с явным изображением как deterministic `split_60_40`: native copy/image containers на desktop, stack copy-first на tablet/mobile. Вариант CTA без media сохраняет прежний text-only path. Targeted replacement разрешается только при совпадении выбранного root с единственным актуальным operation-owned root; ownership, revision, saved fingerprint и transaction guards остаются включены.
- Behavioral checks: `php tests/design-pipeline-contract.php` — 245 checks; `php tests/flex-generation-runtime.php` — 396; `node --test tests/*.test.js` — 4/4; PHP syntax, `git diff --check` — PASS. Package probe: 90 файлов, 0 hash mismatches, 4 package scenarios PASS; полный probe JSON по-прежнему содержит malformed UTF-8, компактный результат валиден.
- На текущем `post=5214` live replacement не выполнялся. Свежий editor config показывает CTA repair `wpae-2ca8292fb0a142e8`, identity `384c25ad-8506-449f-8cfe-637da1287a4e`, revision 5, state `written`, root `eb0103a`, `reviewable=false`, `target_status.reason=root_missing`. Editor canvas после обычного reload содержит 0 roots. Текущая public страница после обычного reload также содержит 0 Elementor roots и 0 headings.
- До обычного reload существующая public-вкладка показывала старый HTML с четырьмя roots `5a3292b`, `32f16d1`, `5b96df3`, `eb0103a`; после reload они исчезли. Это подтверждает устаревшее содержимое прежней public-вкладки, но точный слой cache/response не установлен. Сохранённый CTA отсутствует; страницу не записывали и новые roots/pages/drafts не создавали.
- В regression prompt использовано фото современного интерьера из Unsplash, автор Neon Wang; оригинальная страница помечает его как бесплатное по Unsplash License. Фото и его CDN URL в live document не записывались. Источники: https://unsplash.com/photos/modern-interior-with-concrete-walls-and-wooden-accents-JsL6PZU1KRU и https://unsplash.com/license.

## Свежие screenshots

Снимки сделаны Browser Use из существующих вкладок. JPEG bytes сохранены, сигнатура проверена, PNG созданы через `sips`, все файлы открыты и визуально проверены. Pixel canvas указан отдельно от CSS viewport.

- Старая public-вкладка **до её reload**: root `eb0103a` с прежним CTA без картинки, operation из исторического состояния `wpae-2ca8292fb0a142e8`; CSS viewport `1105×923`, PNG `1050×923`: `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-public-render-after-editor-reload-20260926.png`. Это старый public DOM, не v160 acceptance.
- Editor **после reload**, post `5214`, operation `wpae-2ca8292fb0a142e8` rev 5, root status `root_missing`; CSS viewport `1100×923`, canvas `1025×860`, PNG `1045×923`: `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-editor-v160-after-reload-empty-20260926.png`.
- Public **после reload**, post `5214`, Elementor roots отсутствуют; CSS viewport `1105×923`, PNG `1050×923`: `/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-09-26-v160-cta/cta-public-after-reload-empty-20260926.png`.

Полная история v02.11.159 и более ранних проверок сохранена в `LUNA_HANDOFF_REPORT.md` под пометкой «Историческое состояние»; она не описывает текущий post state.

## Постоянные workflow rules

Для live Elementor использовать только текущую существующую страницу; новые pages/drafts не создавать. Не сохранять неполную editor model и не обходить ownership/stale guards. Screenshots через Browser Use: сохранить screenshot bytes в абсолютный путь, проверить формат, при JPEG преобразовать через `sips` в PNG, открыть/проверить и вставить inline с абсолютной ссылкой. Всегда указывать CSS viewport отдельно от pixel dimensions. Установку после релиза выполнять существующим WP Pusher.
