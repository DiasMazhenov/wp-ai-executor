# Generator M2.1 — source/mock implementation, 2026-10-04

## Состояние и границы

Baseline/initial HEAD: `4e1e0eacd0f67a89dfa594a58b8b80fc52707102`, branch `main`, tracked tree clean, 776 чужих untracked files. Source plugin **v02.11.244**, Brief parser **v11**: версии не повышены. M2.1 реализован в source; structured extraction остаётся opt-in и этим этапом не активирован.

Install/deploy/editor version/live/visual: **NOT RUN**. WordPress, браузер и post=5214 не изменялись. Assets ниже — разрешённые **mock fixtures**, не проверенные изображения сайта. Нет live screenshot, DOM measurements или template fidelity PASS. Исторический empty render post=5214 не исследован.

## Где реализовано

- `includes/elementor/recipes.php`: versioned typed catalog, derived family_compositions, одноразовый resolver, два профиля, честные legacy variant records. Records содержат только Plan composition, slot/binding restrictions, media/group bounds, scope, responsive policy, capabilities, profile availability и provenance/hash. Произвольных Elementor trees/кода/новой DSL в records нет.
- `includes/llm/design-plan.php`: resolution до freeze; decision record ID/version/hash/source/policy/kind/distinct; validation record integrity, policy, cardinality, family/capabilities; profile precedence и value/contrast validation. About сохраняет about.
- `includes/llm/llm.php`: явный selection context, skip ненужного retrieval для ordinary active canonical create, trace reason, resolved values в LayoutReport/trace, передача accepted compiled signature в existing execute. Execute отвергает policy substitution до нормализации/записи; native/readback signature исключает только node IDs, Accordion repeater `_id` и прежнюю operation metadata.
- `includes/elementor/elementor-ir.php`: те же builders; добавлены только native profile controls (responsive typography line-height/gaps/padding/copy boxed width/cards). Compiler получает принятое решение и не выбирает record/profile заново.
- `includes/elementor/compose.php`: старый contract сохраняется; новые typed previews используют тот же resolver/Plan/IR/compiler и guarded normalization, возвращают diagnostics/native tree, `write_count=0`. Mixed legacy/typed payload отвергается, чтобы не терять metrics/proof.
- `tests/m2-generation-contract.php` подключён к реальному runtime harness; package обновляет ровно пять изменённых runtime hashes. Новый runtime module не создавался.

### Каталог v1

18 records, из них 17 distinct results и один typed compatibility alias. `GET /elementor/recipes` возвращает `typed_records`; у каждой записи есть source implementation status и ограничения. Доступность Plan family_compositions выводится из этого каталога, отдельного поддерживаемого family списка нет. Global legacy Plan composition enum по-прежнему покрывает немигрированные paths.

| Record ID | Family / существующий Plan | Ограничения и тип |
|---|---|---|
| `hero.split_60_40.left/right`, `hero.split_50_50.left/right`, `hero.split_40_60.left/right` | hero / соответствующий split | шесть layout alternatives, page, одна copy group, ровно один разрешённый hero asset; tablet 50/50, mobile copy-first |
| `about.split_60_40.left/right`, `about.split_50_50.left/right`, `about.split_40_60.left/right` | about / соответствующий split | шесть layout alternatives, те же bounds, asset role about; about semantic family и copy-first mobile сохраняются |
| `hero.text_only` | hero / stacked_left | page, одна group, ноль assets; **не** альтернатива обязательному image Hero |
| `benefits.grid` | benefits / three_cards | structural alternative, 2–6 ordered complete groups, ноль assets; все явные поля/ссылки сохраняются |
| `benefits.editorial_list` | benefits / editorial_list | те же groups/order/media bounds; icon + nested copy rows вместо cards |
| `benefits.linear` | benefits / linear | legacy_alias, distinct=false, alias_of benefits.grid; оставлен для explicit M1 compatibility |
| `pricing.tiers` | pricing / three_cards | regression control, минимум два tiers, ноль assets; прежняя exact ownership/features/CTA contract |
| `faq.native` | faq / linear | regression control, минимум одна exact Q/A group, ноль assets, native Accordion required |

У Pricing/FAQ record не вводит нового upper group limit; действуют существующие Brief/Plan bounds. У каждого record — необходимые native capabilities. Profiles доступны только Hero/About/Benefits. Default без profile воспроизводит M1 native settings; Services остаётся на прежнем verified recipe/map path.

Resolver получает ограничения canonical Brief первыми. Совместимый explicit record применяется один раз; конфликт с composition/media_side/media/группами/scope — объяснимый отказ. Без record выводится прежний default либо точное Brief composition/side. Sources: explicit_record / explicit_brief / documented_default. Required copy/media не удаляются для совместимости.

Structural alternative отличается semantic node order/topology. Layout alternative отличается side/width/responsive controls (left/right также меняет native order media/copy). Visual alternative отличается реальными settings при том же content/topology. Alias не объявляется новым результатом. Same Brief with required photo никогда не допускает text-only downgrade.

## Visual profiles и precedence

`safe_default → project → selected_visual_profile → confirmed_page → confirmed_reference → explicit_brief`. Выбор profile явный, без него профильные controls не активируются. Unconfirmed page/reference не применяются. Plan хранит конечное значение и source каждого токена; contrast adjustment старого default отдельно маркирован `plan_contrast_adjustment`. Profile path не ремонтирует плохой контраст скрыто: цвет/размер/type shape/line-height и text-on-page/text-on-surface проверяются до freeze.

| Native setting на одном Hero Brief/layout | editorial_light | soft_cards_light |
|---|---|---|
| root background | #ffffff | #f1f5f9 |
| heading size D/T/M, rem | 3.25 / 2.5 / 2 | 2.75 / 2.25 / 1.875 |
| heading weight / line-height em | 700 / 1.1 | 600 / 1.2 |
| vertical section padding D/T/M, rem | 5 / 3.5 / 2.5 | 4 / 3 / 2 |
| component gap D/T/M, rem | 1.25 / 1 / 0.875 | 1.75 / 1.25 / 1 |
| copy boxed width, rem; mobile | 38; 100% | 32; 100% |
| Benefits card radius, rem | 0.25 | 1.25 |
| Benefits feature title D/T/M, rem | 1.25 / 1.1875 / 1.125 | 1.1875 / 1.125 / 1.0625 |

Body D/T/M and line-height также заданы профилями через type.body: editorial 1.0625/1/1 rem, 1.65; soft 1/1/0.9375 rem, 1.6. Белые surfaces, 1px solid borders, разные border colors/card padding/radii компилируются там, где есть cards/rows. Font family inherit, внешние fonts/libraries не подключались. Это source/native-control proof; реальная типографика/kit/breakpoints/geometry ещё не измерены.

## REST contracts

Existing endpoint: `POST /wp-json/ai-executor/v1/elementor/compose`, прежний permission callback. Legacy inputs `recipe_id` (или `id`), `variant`, `slots`, `instance_id` сохраняются. Existing response fields сохраняются; добавлены requested_variant/effective_variant/variant_kind/distinct/implementation_status/write_count. Eight recipes имеют одно реальное tree each:

| Recipe | Effective/default | Compatibility aliases |
|---|---|---|
| hero.editorial | split-proof | minimal, metric-led |
| feature.grid | three-cards | dense, proof-led |
| process.steps | linear | split, timeline |
| pricing.comparison | two-packages | three-packages |
| faq.accordion | simple | compact |
| cta.band | dark | light, accent |
| proof.timeline | three-points | case-led |
| contact.block | direct | split |

Все 20 label requests проверены: одинаковые native trees при одном instance/slots, aliases `legacy_alias / distinct=false`; recipe summaries/get-one показывают variant_records и `alternatives=[]`. Default distinct=true обозначает единственный базовый tree, не дополнительный вариант. `hero.editorial` не адаптируется в новый Hero: metric/proof rail и пользовательские slots сохранены и проверены.

Typed contract:

- Required `composition_record`: точный ID; `canonical_brief`: объект существующей BriefIR schema с source_text, exact content/source spans, role/group ownership, links, media_references, layout constraints. `canonical_create` принудительно true. Plain text/string вместо Brief отвергается; composer **не** парсит message.
- Optional `composition_version`: JSON integer 1 (при отсутствии current version); `visual_profile`: editorial_light/soft_cards_light; `instance_id`: непустой sanitize_key-compatible string. Invalid/unknown version/profile/record — no-write refusal.
- Optional `page_tokens`+`page_tokens_confirmed`, `reference_tokens`+`reference_tokens_confirmed`: те же подтверждённые flat token layers, что chat. Explicit visual_token constraints могут задавать и новые известные profile tokens. Полный type token требует D/T/M, weight, font_family, line_height; невалидные shapes отказывают.
- Media уже находятся в canonical Brief, с valid refs/source, allowed_reuse, alt, family role/group; composer не берёт assets из отдельного free-text parser или legacy slots.
- Legacy recipe_id/id/variant/slots вместе с typed inputs запрещены (`mixed_legacy_typed_contract`). Это предотвращает silent drop старых metric/proof fields.
- Response: прежние общие fields, native elementor_data, diagnostics Brief/Plan/record/resolved_visual/LayoutReport, stats/normalization, provider_calls=0/write_count=0/render_status=NOT_RUN. instance_id меняет node/repeater IDs; copy/media/geometry/visual decisions эквивалентны.

Chat active create принимает selection в `context.composition_record`, `composition_version`, `visual_profile`; canonical_brief optional по M1 local contract (source_text должен совпасть с нормализованным message). Обычная детерминированная intake остаётся доступной. UI dropdown/new model extraction в M2.1 не добавлялись. Off/shadow/targeted/unmigrated compatibility сохранена.

## Evidence

[demo.jsonl](demo.jsonl): 24 compact fixture records через настоящий `wpae_llm_chat_request()`. Main matrix покрывает все 18 records, ещё шесть fixtures — два профиля для Hero, Benefits grid и list. Полные requests, mock assets/alt/permissions, Brief/record hashes, accounting/structural/layout-visual signatures, IDs и краткие native controls сохранены. Нет полных Elementor trees и выдуманных screenshot links.

Mocks: WordPress options/data/widget registry/provider transport и dry-run/final update endpoint boundary. Production chat, Brief/Plan/IR/native compiler, execute и ledger используются. One mock transaction/readback подтверждает caller contract; настоящая WordPress storage/concurrency/render этим harness не проверяются. Patch guard проверен отдельным suite.

Каждый successful submit: один Brief/record decision, provider=0, ordinary retrieval=0, одна final-write попытка/одна успешная mock transaction, один выбранный root, соседние roots byte-equivalent; owned root ledger/readback matches. Composer того же record выдаёт тот же content/settings без IDs и делает ноль записей. Смена instance_id сохраняет семантику/геометрию/visual settings. Никакие остальные alternatives не создаются.

На одном Brief: Hero/About left/right и ratios сохраняют copy order/CTA href/asset ownership; side/order/mobile controls и native widths различаются. Benefits grid/list сохраняют те же ordered groups, descriptions, links и media accounting; без добавленных CTA/photo. Profiles сохраняют topology и content при разных native controls. Default native results сравниваются со всеми семью M1 controls и совпадают. Services photo/split/text-icon и explicit verified library map, Pricing exact tier fields и FAQ exact native pairs остаются regression controls.

| Same-Brief comparison | Brief hash (prefix) | Accounting | Structure / native settings |
|---|---|---|---|
| hero.split_60_40.left / hero.split_60_40.right | `cd996757ef51ba03` | identical | structure different; settings different |
| about.split_60_40.left / about.split_60_40.right | `c1795414611b4d76` | identical | structure different; settings different |
| benefits.grid / benefits.editorial_list | `513d2ff422e62767` | identical | structure different; settings different |
| hero.editorial_light / hero.soft_cards_light | `cd996757ef51ba03` | identical | structure same; settings different |
| benefits.editorial_light / benefits.soft_cards_light | `513d2ff422e62767` | identical | structure same; settings different |
| benefits.grid / benefits.linear | `513d2ff422e62767` | identical | structure same; settings same |

Отказы: unknown record/version, cross-family, explicit side/ratio conflict, mandatory unresolved/multiple media, group cardinality, missing image/Accordion capability, unknown profile, bad dimensions/type fields/contrast on page or surface, unbound/cross-group refs (M1 controls), accepted policy mutation, unreadable saved JSON, mixed/raw-text composer input и library-only Brief без verified slot map. Preview/final storage failures имеют 0/1 write attempts соответственно и ноль successful writes, без retry/provider/legacy fallback. Execute substitution test меняет принятое mobile direction и получает `frozen_accepted_policy_mismatch` до записи.

### Imported references

[imported-reference-audit.json](imported-reference-audit.json): 24 реальных trees (20 Benefits references + block-hero + три Appraxx Heroes), SHA256/widgets/empty columns/buttons/ekiticons. `block-hero.json` содержит **2 empty columns**. Все три Appraxx Hero содержат `stax-el-heading`/`stax-el-button`. 11 Benefits trees содержат native buttons, например block-feature-grid/button1, our-services/button10, career/button6. Три Appraxx feature trees используют ekiticons (3/5/5 occurrences): наличие этих значений не подтверждает загрузку glyphs на сайте.

Typed records отделены provenance typed_plan. Эти imported references не подключены к records по названию/category: каждый требует отдельного verified slot map и проверки dependencies/assets/icons/runtime/control version. Их typed_slot_map=NOT_IMPLEMENTED, template_fidelity=NOT_ACCEPTED. Existing verified Services map сохранён; library-only без соответствующей проверенной карты отказывает. General imported slot-map validator и новые карты не реализованы в M2.1.

### Ошибки во время implementation

Новый integrity check сначала сравнивал отсутствующий media_side record text-only с section default right; исправлен для records с media slot. Изменён только один прежний expectation: ordinary Services больше не должен вызывать unused retrieval (при том же Plan/fidelity/write/readback).

Сравнение FAQ chat/composer выявило независимые repeater IDs. Signature теперь исключает Accordion `_id`, сохраняя весь tab_title/tab_content/order; rekey задаёт repeater IDs по instance. Отдельная regression меняет реальный FAQ answer и подтверждает signature mismatch. Fidelity/frozen checks не ослаблены. Composer harness теперь загружает production validation/logging/skill functions вместо старого статистического stub; WP add_filter registration остаётся внешним no-op mock. Trace mocked retrieval с отсутствующими status/reason обработан без PHP warnings. Final runtime/demo не содержит warnings/notices/fatals. Один параллельно нагруженный Node run превысил прежний 20s PHP subprocess timeout (SIGTERM); повторный отдельный run завершился 6/6 PASS за 5.35s. Timeout/assertions не менялись.

## Проверки

- Runtime: **1053 checks OK** (M1 + M2.1 + прежние Services/compatibility suites).
- Design pipeline: **319 checks OK**.
- Node: **6/6 PASS**.
- Production patch guard: PASS.
- Imported catalog: **158 manifest / 156 trees / 156 previews**, FAQ5/Services2, PASS; WP records не создаются.
- PHP lint: пять изменённых runtime files + runtime harness + M2 test — PASS.
- Package: **250/250 hashes**, пять обновлены; probe **4 scenarios PASS / 0 mismatches**. Исторический full-result JSON malformed UTF-8 для zip bytes остаётся; compact summary успешно сериализуется.
- `git diff --check`: PASS. Scoped staged diff проверяется перед commit.
- Graphify: affected five-source layer обновлён изолированно в `/var/folders/vm/sv575ch161s9nqwq97dnv6kw0000gn/T/wpae-m2-graph-5_f_l05l`, **232 nodes / 743 edges**. Curated repo graphs и прочие foreign artifacts не изменены/не коммитятся.

Demo command:

```sh
WPAE_M2_CHAT_DEMO=1 php tests/flex-generation-runtime.php
```

24 compact JSONL records + suite summary, одинаковые fixture assertions независимо от demo flag.

## Оставшиеся границы и Git status

Imported adaptation/slot-map validator, arbitrary generated copy/model calibration, real kit/fonts/breakpoints/render/overflow, actual asset authorization/load/crop и browser visual acceptance не выполнены и не заявлены готовыми. Source flags off/shadow/unmigrated и историческая Services redecision при media enrichment остаются compatibility paths. API selection реализован; frontend chooser не входит в этот срез. Install/deploy/live/visual — **NOT RUN**.

Implementation commit `13315c0d5b0fce322eacda3110371fd74483b0e9` опубликован в `origin/main`. Независимый `git ls-remote origin refs/heads/main` подтвердил этот SHA 2026-10-04 18:47:05 Asia/Almaty. Перед commit просмотрен scoped staged diff всех 14 файлов; `git diff --cached --check` PASS. Эта публикационная запись добавлена после подтверждения remote. Install/deploy/live/visual остаются NOT RUN; 776 посторонних untracked-файлов сохранены.
