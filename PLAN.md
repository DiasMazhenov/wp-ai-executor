# Roadmap: заимствования из EMCP Tools (msrbuilds/elementor-mcp)

Исследование 2026-09-05, вечерний разбор репозитория. Сначала реализовано в
v02.11.52: пост-записной сброс кэша CSS/assets, `GET /elementor/rendered-html`
(серверная DOM-верификация приёмки), правила промптов «constraints, not
layout» / «sections communicate» / «never scaffold empty containers».

Оставшиеся заимствования по приоритету:

## Ближний круг (S)

1. **Conflict guard для undo (S)** — штамп `after_hash = sha1(_elementor_data)`
   в rollback-снапшотах; one-click undo (`POST /llm/undo`) отказывается
   работать, если пост менялся после операции (`hash_equals`). Закрывает
   «undo перетёр свежие правки». Источник:
   `class-change-log.php::detect_conflict`.
2. **Elementor-гочаи в нормализатор (S)** — классические грид-контейнеры
   Elementor по умолчанию рендерят две строки: при grid→flex маппинге ставить
   `grid_rows_grid = {"unit":"fr","size":1}` для однорядной сетки; требовать
   все четыре стороны dimension-объектов (у нас уже частично есть в
   патч-пайплайне v02.09.87 — закрепить в нормализаторе).

## Средний круг (M)

3. **Page Snapshot digest (M)** — one-call разбор страницы: компактное дерево,
   аудит реально используемых глобальных токенов, per-device override-ключи,
   контент-статы, warnings (пустые контейнеры, вложенность ≥6, несколько H1,
   отсутствующие alt). Лежит в preflight и Vision render-context. Источник:
   `class-page-snapshot.php::build()`.
4. **Stock-image провайдеры (M + API-ключи)** — Pexels/Unsplash/Pixabay клиенты
   для релевантных фото в photo-контентных блоках вместо только bundled
   Vocario-фикстур (боль EJ-059). Источник: `class-pexels-client.php` и соседи.
5. **Blob offload для rollback-снапшотов (M)** — лёгкая строка в журнале +
   before-image >4КБ в отдельный blob-стор вместо тяжёлых ревизий. Источник:
   `class-change-blobs.php`.

## Стратегия (L, отдельное решение)

6. **Schema-driven coercion (M-L)** — валидация/coerce настроек через
   `get_props_schema()` + `Prop_Type::validate()` вместо хардкод-списков
   ключей; гейт по **зарегистрированным** типам элементов (Elementor молча
   дропает незарегистрированное при save). Целит и Elementor 4 atomic
   widgets, и самолечение битых деревьев. Источник: `class-atomic-props.php`.
7. **MCP-экспозиция (L)** — бандл WordPress MCP Adapter
   (github.com/WordPress/mcp-adapter), выставить существующий REST
   (`/elementor/*`, `/vision/*`, `/llm/*`) как MCP-тулзы: Claude Desktop /
   Cursor управляют сайтом без редакторского чата. Источник:
   `class-mcp-adapter-bootstrap.php`.

## Не заимствуем

- PHP snippet store/sandbox, SQL guard, user/file инструменты — против
  безопасностной модели плагина (нет файловой системы/shell).
- Pro-функции Freemius/облако.