# План WP AI Executor

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

## Далее

1. Опционально добавить preset buttons для существующих single-key capability toggles.
   - Базовая модель уже реализована: один `X-AI-Key`, capability toggles в dashboard и отражение в `/capabilities`.
   - Не добавлять отдельные `run_key`, `guide_key`, `update_key` или `readonly_key`.
   - Presets, если понадобятся, должны быть только UI shortcuts: `read_only`, `elementor_safe`, `maintenance`, `full_trusted`.
