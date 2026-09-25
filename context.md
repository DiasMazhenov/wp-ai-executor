# WP AI Executor — context

Последнее обновление: **2026-09-25 19:43 +05:00 (Asia/Almaty)**.

## Текущее состояние

- Checkout: `/Users/diasmazhenov/vibecode/wp-ai-executor`, branch `main`, base HEAD `898d20b6a6ac8a465298d2a0709d709bd43ba592`.
- Runtime release commit: `4fd7839` (`Add native FAQ and benefits design plans`), source version `v02.11.154`; push в `origin/main` выполнен успешно с fast-forward от `898d20b`.
- Installed Plugins UI показывает активный WP AI Executor `v02.11.154`. Live editor вкладка после установки не перезагружалась; её inline config/current JS version в этом срезе не подтверждены.
- Runtime изменения этого запуска: `BriefIR` FAQ/benefits roles, FAQ/benefits typed plans в существующем `DesignPlan v1`, native Accordion/Icon compilation и widget capability, active pipeline eligibility. Новый write path, плагины и библиотеки не добавлялись.
- Четыре вида — hero, pricing, FAQ, преимущества — проходят production functions в локальном in-memory harness. Это не live acceptance.

## Kit sources

`/Users/diasmazhenov/Downloads/elementorpro-temp` содержит 16 ZIP и 15 уникальных архивов (Aquassi ZIP повторён). Десять kit совпадают с ElemKits catalog entries: 18Holes, Adopt, Akademy, Alaya, Alcor, Apper, Apprista, Apptom, Aquassi, Aquavist. Пять архивов не сопоставлены с локальными записями: Albion, Aleos, EasyLanding/Applanding, AppRaxx, Aquila.

Использованные только как source patterns: 18Holes Homepage hero и FAQ; Akademy Feature Boxes и Pricing Boxes. Локальный ElemKits README заявляет CC0 1.0 для kits на сайте, но сами ZIP не содержат отдельного license field; сторонние фото/шрифты/внешние assets не переносились. Не включай source ZIP/JSON в plugin package.

Подробная матрица источников, адаптации, полей и live safety — в [LUNA_HANDOFF_REPORT.md](/Users/diasmazhenov/vibecode/wp-ai-executor/LUNA_HANDOFF_REPORT.md).

## Live safety и screenshot rule

Работать только с существующим `post=5214`; новые WordPress pages/drafts не создавать. Последний известный editor preview был пуст, а public DOM показывал `82b88e5`, `de395b6`, `d729d84`; это прежние DOM snapshots, не saved server document. Актуальный `_elementor_data` readback не подтверждён. До получения разрешённого read-only saved document и согласования roots не сохранять editor model и не делать live generation: это может потерять содержимое соседних/старых roots.

Для каждой фактически созданной и принятой live секции после save/reload:
1. Снимок текущей вкладки существующей страницы через документированный Browser Use/CUA.
2. Сохранить именно возвращённые bytes в абсолютный workspace path; не печатать base64 и не выдумывать API/path.
3. Проверить signature/format; при JPEG преобразовать в PNG через `sips`.
4. Открыть файл и проверить, что на нём нужный post/block и выбранный viewport.
5. Вставить inline `![описание](/absolute/path/file.png)` и кликабельную ссылку на тот же абсолютный путь; указывать CSS viewport, source editor/public, post/root/operation IDs.

Не показывать baseline, source preview или реконструированный HTML как screenshot принятого live-дизайна. Если live block не был сохранён/принят, screenshot status — `NOT RUN`; если захват нужного результата фактически невозможен — `SCREENSHOT BLOCKED` с конкретной технической причиной.

## Проверки последнего прохода

- `php tests/design-pipeline-contract.php` — 181 checks OK.
- `php tests/flex-generation-runtime.php` — 357 checks OK.
- В production-path harness при `pipeline=active` и EDDE `active`: четыре archetypes выбрали `diagnostics.action_path=pipeline`, 0 provider calls, 1 write каждый. Записи выполнялись только в in-memory harness `post_id=42`.
- `node --test tests/*.test.js` — 4 passed, 0 failed.
- `php -l` для 8 изменённых PHP-файлов — без syntax errors.
- Package integrity — 90/90 SHA-256 hashes valid после пересборки `wpae-package.json`.
- `php docs/audits/2026-09-12/package-probe.php` — PASS, 4 scenarios, 90 packaged files; invalid/corrupt/missing/unsafe cases отклонены. В дополнительной диагностике полного JSON результата найден malformed UTF-8; компактный summary сериализуется.
- `git diff --check` — PASS.
- `git push origin main` — PASS; установленный Plugins UI row — active `v02.11.154`. Editor runtime после установки — NOT RECHECKED, чтобы не перезагружать вкладку с неподтверждённым editor state.
