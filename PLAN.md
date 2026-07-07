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

## Далее

1. Добавить project design tokens в dashboard и `/guide`.
   - Хранить palette, typography roles, spacing scale, radii, button style, tone of voice и design prohibitions в `wp_options`.
   - Возвращать tokens в `/guide` и `/capabilities`, чтобы любой агент мог следовать визуальной системе сайта.

2. Добавить более строгие preflight checks перед Elementor writes.
   - Проверять отсутствие legacy sections/columns, пустых native widgets, CSS-only critical backgrounds, признаков horizontal overflow и HTML widget как main layout.
   - Требовать CTA presence и native critical visual settings для landing pages.

3. Добавить after-save quality summary.
   - После `/elementor/page` или `/elementor/update` возвращать permalink, audit summary, conformance score, warnings и конкретные fixes.
   - Подталкивать агентов запускать `/audit` и исправлять warnings до сообщения о завершении.

4. Рассмотреть будущий endpoint `/visual-audit`.
   - Использовать server-side DOM/render checks только если это будет надежно работать на типичном WordPress hosting.
   - Цели проверки: overflow, contrast, invisible text, подозрительные empty blocks, слишком большие отступы и desktop/mobile screenshot metrics.

5. Опционально добавить preset buttons для существующих single-key capability toggles.
   - Базовая модель уже реализована: один `X-AI-Key`, capability toggles в dashboard и отражение в `/capabilities`.
   - Не добавлять отдельные `run_key`, `guide_key`, `update_key` или `readonly_key`.
   - Presets, если понадобятся, должны быть только UI shortcuts: `read_only`, `elementor_safe`, `maintenance`, `full_trusted`.
