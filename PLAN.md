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
- Добавлено защитное правило preserve existing enhancements: native-first не разрешает массово переписывать страницу или удалять рабочие CSS/JS/WebGL/Three.js/GSAP/canvas/animation HTML widgets без явного запроса.
- В guide добавлен подробный CSS-to-native Elementor mapping: typography_*, colors, padding/margin/radius/min_height/flex/gap/wrap, unitless line-height для multi-line и обязательная очистка Elementor/WP Rocket CSS cache после native changes.
- Уточнено по `wordpress-elementor-dev`: обычные и hover gradients через Elementor `Group_Control_Background` являются native settings; `z-index` и fixed/sticky сначала через Elementor advanced/positioning/motion controls, CSS только если native controls недостаточны для overlay/off-canvas/system layers.
- Усилена безопасность: `/run` выключен по умолчанию и однократно отключается при обновлении существующей установки; self-update принимает только immutable Git commit URL и заменяет файл атомарно.
- Усилены пункты безопасности 4–6: `/visual-audit` проверяет DNS/IP и использует safe HTTP fetch, API-ключ принимается только в `X-AI-Key`, просроченные guide-token options очищаются автоматически.
- Усилены оставшиеся пункты безопасности: rollback восстанавливает только управляемые Elementor/WPAE meta, новая страница удаляется при ошибке сохранения Elementor metadata, exports хранятся в `wp_options` без публичных файлов, `/media/upload` проверяет фактическую binary signature, а dashboard получил preset-кнопки single-key capability modes.
- В `/guide` добавлены явные runtime notes для агентов: rollback scope, automatic orphan cleanup для `/elementor/page`, binary signature check в `/media/upload` и database-only `/exports/create`.
- Добавлен `/elementor/typography-unlock`: безопасная миграция страниц, где сторонний агент зашил локальные `typography_*` overrides в каждый виджет и тем самым сломал глобальное управление типографикой через Elementor.
- В guide добавлено правило typography editability: native settings first не означает дублировать `typography_*` на каждом widget; глобально управляемая типографика должна идти через роли/наследование, локальные overrides только для осознанных исключений.
- Уточнено правило Elementor editor editability: все дизайн-свойства, которые Elementor умеет редактировать, должны оставаться в native settings/controls; native settings нельзя удалять ради “редактируемости”, потому что они и есть редактируемый источник. CSS/HTML widget не должны быть единственным источником управляемого дизайна.
- Добавлены endpoints для аварийного восстановления: `/rollback/snapshots`, `/elementor/revisions`, `/elementor/restore-revision`. После любых page writes/rollback/migration/restore теперь требуется реальный browser screenshot публичной страницы; HTML/CSS-аудита недостаточно.
- Добавлены `/exports` и `/exports/prune`, а также карточка в dashboard для просмотра metadata короткоживущих JSON exports и ручной очистки просроченных записей из `wp_options`.
- Проведен review после живого использования новых endpoints: добавлен `/skills/import-url` в conformance guide-token route list и исправлена передача `target` в skill enforce rules.
- Добавлен `repeated_agent_error_audit` в `/audit` и `/elementor/visual-audit`: legacy sections/columns, `widget_type`, HTML widget layout/content, script-injected native CSS, heading typography `!important`, excessive local typography overrides, design-system marker drift и fixed px layout risks.

## Далее

1. Наблюдать новые live-логи и добавлять точечные validators, если внешние агенты найдут новый повторяющийся анти-паттерн.

## Killer Features

1. Transaction write mode для Elementor: каждый `/elementor/page` и `/elementor/update` должен уметь работать как атомарная операция с auto-rollback при failed validation, failed cache refresh или failed public verification.
2. Patch API по `element_id` и native property path: агент должен менять точечные свойства вроде `heading.typography_font_size` или `container.background_color`, не пересобирая всю страницу.
3. Protected zones: маркировать WebGL/Three.js/GSAP/canvas/HTML enhancement blocks как защищенные, чтобы миграции native settings не ломали рабочие анимации и скрипты.
4. Visual regression gate: перед risky write сохранять lightweight baseline публичной страницы, после write сравнивать ключевые признаки layout/copy/CTA/overflow и блокировать явные поломки.
5. Elementor editability tests: отдельная проверка, что свойства из `css_to_native_map` реально управляются через Elementor native settings, а не перебиваются CSS/HTML/script-injected styles.
6. CSS-to-native migrator: endpoint, который находит native-supported CSS declarations, переносит их в settings виджетов/контейнеров и аккуратно удаляет только перенесенные CSS rules.
7. Design system registry: хранить несколько named design systems, фиксировать active system per page и явно мигрировать страницу между системами через dry-run.
8. Pattern library builder: сохранять удачные sections как reusable Flexbox Container patterns с slots, variants, required settings и quality score.
9. Preview -> approve -> publish flow: агент сначала создает draft/preview, отдает audit summary и только после approval публикует или заменяет live page.
10. Agent contract handshake: write endpoints требуют явного подтверждения, что агент прочитал `/guide`, `/capabilities`, enabled skills, design system и текущие ограничения.
11. Recovery assistant actions: при ошибке endpoint должен возвращать не только code/message, но и безопасный следующий endpoint/payload skeleton для исправления.
12. Расширенный Agent Conformance Scoring: учитывать не только нарушения, но и качество процесса: blueprint used, recipe/compose used, native settings coverage, mobile-first coverage, visual verification evidence и number of retries.

## Приоритет внедрения

1. Transaction write mode + более сильный rollback.
2. Patch API по `element_id` и native property path.
3. Protected zones для WebGL/Three.js/GSAP/canvas.
4. Visual regression gate.
5. Elementor editability tests.
6. CSS-to-native migrator.
