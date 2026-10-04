# M1: общий typed create — 2026-10-04 (Asia/Almaty)

## Статус и граница доказательств

Baseline `1f7baa508ff123eb0da1edf4e90a64e9c90b2378`, source header/constant `v02.11.244`; номер plugin release сохранён: это source-only milestone без install/deploy. Parser contract теперь `wpae-brief-parser-v11`. На входе tracked tree чистый, 776 посторонних untracked-файлов сохранены. WordPress, post=5214, браузер, WP Pusher и live generation не использовались. Installed/editor version заново не проверены. Render/visual acceptance **NOT RUN**.

## Реализованный маршрут

`wpae_llm_chat_request()` в active mode для обычного migrated create формирует один canonical Brief или принимает локальный Brief с совпадающим source_text и проверенной explicit-copy provenance. Family, content expectations и bindings выводятся из него. Единственный DesignPlan хранит composition identity/source, slot bindings, responsive policy и resolved visual values. Existing ElementorIR/native compiler формирует native controls; existing execute/preview/update boundary выполняет запись и readback с existing ledger.

Hero: split 60/40, 50/50, 40/60 либо законченный stack без stock-photo fallback. About переиспользует split builders, сохраняя family/role `about`; без разрешённого media отказывает. Benefits: grid card и editorial row с вложенным copy group — разная topology. Pricing: tier name/amount/period/description/features/CTA refs с владельцем pricing_N. FAQ: native Accordion, отсутствие capability даёт отказ. Services сохраняет существующие recipe/group/library-map контракты; не получает новую generic pricing/FAQ семантику.

Обычные library candidates больше не включают library-agent для migrated create. Явный library-only Hero/About/Benefits/Pricing/FAQ отказывает без verified slot map. Services сохраняет verified recipe maps. На новом create не вызываются provider-generated tree, EDDE Hero owner, legacy fallback, Process semantic rebuild, Bento/CTA/template semantic normalization. Общий normalizer допустим только при неизменной frozen signature.

`wpae_llm_decision_signature()` исключает IDs и operation metadata, сохраняя topology, copy, media, native visual/responsive controls и visual classes. Сравнивается compiled tree до/после technical normalization и с owned saved roots. Plan hash фиксируется в trace; execute принимает данные по значению. Ошибка после записи не скрывается как write_count=0: диагностический mismatch оставляет совершённую запись учтённой, ledger failed и saved hash; не запускает append/fallback или вторую rollback boundary. Existing transaction rollback/readback guards сохранены.

## Visual owner и lifecycle

Порядок token resolution: safe defaults → project → подтверждённый page context → подтверждённый reference context → explicit Brief. Не подтверждённые page/reference values не используются; реальный site kit из браузера здесь не извлекался. Muted contrast repair выполняется в Plan; compiler для resolved Plan не меняет контраст скрыто. Native container width/padding/transparent defaults явно компилируются до normalizer. Compatibility callers без resolved Plan сохраняют старый contrast fallback.

Saved helper различает реальный `[]` и missing/malformed/object JSON. Ошибку чтения новый create не превращает в пустую страницу. Existing post/expected-before/protected-zone/ownership/revision/fingerprint/save/readback/rollback boundary не заменена. Frozen create передаёт expected-before document; прямых planner/compiler meta writes не добавлено.

## Сквозные fixtures

Mocks расположены на внешних WordPress/transport boundaries; entrypoint, Brief/Plan/IR/compiler/execute/ledger — production PHP. Все семь fixtures имеют существующий несовместимый library candidate и проходят typed route: ноль provider calls, одна успешная mock-запись, одна final-write попытка, exact neighbors, owned root, ledger written, compiled/readback signature match.

| Fixture | Composition | Native widgets | Результат |
|---|---|---|---|
| hero_stack | hero.stacked_left | heading×2, text-editor×1, button×1 | LOCAL PASS |
| hero_split | hero.split_60_40 | heading×2, text-editor×1, button×1, image×1 | LOCAL PASS |
| about_split | about.split_50_50 | heading×2, text-editor×1, button×1, image×1 | LOCAL PASS |
| benefits_grid | benefits.three_cards | icon×2, heading×2, text-editor×2 | LOCAL PASS |
| benefits_list | benefits.editorial_list | icon×2, heading×2, text-editor×2 | LOCAL PASS |
| pricing | pricing.three_cards | heading×4, text-editor×8, button×2 | LOCAL PASS |
| faq | faq.linear | accordion×1 | LOCAL PASS |

Exact requests и Brief hashes сохранены в [demo.jsonl](demo.jsonl). Asset `m1-asset`, URL `https://example.com/approved-studio.jpg`, alt `Студия`, allowed_reuse=true, role hero/about — локальный mock fixture, не проверенный live asset. Никакого remote media fetch нет.

Topology hashes вычислены по type/widget/children без IDs и settings:

- `hero_stack`: `f6adcdfd9356fc845c6813e00562510243a60165433edaee49eb47bb780c9e16`
- `hero_split`: `e71cdb5834e08d773ef45cae4ea99b5709b83caf7e89b67aa75c3790ae8bf057`
- `about_split`: `e71cdb5834e08d773ef45cae4ea99b5709b83caf7e89b67aa75c3790ae8bf057`
- `benefits_grid`: `5d6cc9141edf43a47b95365e28b4354bedfa1f5038eb3f18ded97a4f9b95d58d`
- `benefits_list`: `8a4bb28090cc45b68168e7cb8ae3969b0520a251aca28371631b835e935115e4`
- `pricing`: `2a699c7f1671bbd993e11b1a1262429ca290fd8379ad42ae6496abcd7491a6c2`
- `faq`: `873457ab7b54a4aee92c6ef86bdf08deb575f1bc1501fbfc87cfc5b499d32186`

Hero stack/split и Benefits grid/list отличаются этим структурным представлением; одинаковая About/Hero split topology — намеренное переиспользование, семантика about проверяется отдельно. Pricing проверяет поля внутри собственного tier и отсутствие чужого CTA href. FAQ проверяет упорядоченные пары и Accordion. Длинная copy (>500 символов, обе quote syntax) сохраняется без fixed clipping height; instructions не становятся copy.

Отказы без успешной записи/provider/fallback: forbidden/unresolved media, отсутствующий About asset, library-only без map, wrong post, unknown composition/ref, cross-group bindings (Benefits, Pricing, FAQ), отсутствующая Accordion capability, unreadable saved JSON. Есть локальный validated-Brief success. Preview failure и final storage failure завершаются без retry; final failure имеет одну attempted write и ноль успешных записей. Stale-revision/protected-zone errors инжектируются mock boundary: доказан no-retry/no-fallback caller contract, а не новая реальная проверка WordPress concurrency/security. Существующий patch guard проверен отдельно.

## Проверки

- `php tests/flex-generation-runtime.php`: **773 checks OK** (включает Services regressions и M1).
- `php tests/design-pipeline-contract.php`: **319 checks OK**.
- `node --test tests/*.test.js`: **6/6 PASS**.
- `php tests/elementor-patch-guard.php`: PASS.
- `php tests/imported-template-catalog.php`: PASS, manifest 158, trees/previews 156/156, FAQ 5, Services 2.
- PHP lint: семь изменённых runtime PHP и три PHP test files — PASS.
- `php docs/audits/2026-09-12/package-probe.php`: 250 files, 0 mismatches, 4 scenarios PASS. Full-result JSON всё ещё имеет историческую malformed UTF-8 limitation для zip bytes; compact probe summary сериализуется.
- Package manifest: обновлены семь runtime SHA256; plugin version не изменена.
- `git diff --check`: PASS; staged diff просмотрен перед commit.

Demo command из repository root:

```sh
WPAE_M1_CHAT_DEMO=1 php tests/flex-generation-runtime.php
```

Вывод: семь compact JSONL records плюс итог suite, без полных деревьев. Сохранённый JSONL содержит только эти семь records.

Graphify navigation был перепроверен по source: обновлён изолированный граф пяти затронутых PHP файлов в `/tmp/wpae-m1-graph-4kkfa2pv` (242 nodes/775 edges). Посторонние curated graphs репозитория не перезаписаны и не включены в commit.

## Совместимость и пробелы

Off/shadow, targeted edits, Vision repair и unmigrated families остаются на compatibility branches; shadow может писать через legacy execution. Existing Services recipe selector может повториться при media enrichment — прежний совместимый путь не объявлен новой единственной generic decision реализацией. Shared compiler defaults теперь явные native controls; прямые legacy compiler assertions скорректированы на эквивалентное transparent значение, existing suites сохранены.

Deterministic grammar требует explicit quoted/labeled copy и typed refs. Pricing Features/Возможности/Функции задаются quoted values на строке своего tier; нет произвольного language understanding или generated-copy calibration. Structured model adapter не включён автоматически. Carousel/forms/Theme Builder/popup и произвольные imported behaviors остаются вне M1. Real asset permission/load/crop, enabled site breakpoints, actual kit context, Elementor native render и визуальная пригодность требуют отдельной live-приёмки. Причина исторического empty render post=5214 этим milestone не исследована и не установлена.

Commit/push относятся только к source milestone. Install/editor/live/visual остаются отдельными неизменёнными статусами; SHA указан в Git и финальном отчёте.
