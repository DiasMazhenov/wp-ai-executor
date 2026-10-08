# Общая геометрия повторяемых блоков и live-приёмка v293

Дата: 2026-10-08. Страница: post=5214, публичная страница https://mazhenov.kz/pricing-contract-live-v123/.

## Итог и статусы

Проведены пять новых сценариев A–E через обычный plugin chat. Каждый использовал текущую цепочку BriefIR → composition decision → frozen DesignPlan → ElementorIR/native Flex compiler → validation → transaction/readback. Все пять финальных результатов прошли Publish/reload, независимый native JSON readback, публичную проверку desktop/mobile и guarded Undo. После каждого Undo подтверждён пустой root set; финальный editor/public baseline — [].

Первый A-результат на v292 сохранил контент, но провалил выравнивание centered CTA. Результат сохранён и guarded-Undone. После общей правки v293 тот же exact fixture был запущен один раз повторно и принят. Итого: пять самостоятельных сценариев, шесть transaction writes, один retry после исправления source-дефекта, пять accepted результатов из пяти; repair writes — 0.

| Сценарий | Family / запись | Root | Operation / identity | Publish/readback → Undo revision | Lifecycle | Content/native | Desktop/mobile |
|---|---|---|---|---|---|---|---|
| A | CTA / cta.centered v1 / editorial_light | 438f8e7 | wpae-ee04b47bac4e2cfa / 713c3968-f33b-4d49-965a-9101dd0140fd | 5 → 6 | PASS | PASS | PASS |
| B | Team / team.grid v1 / editorial_light | 94ea563 | wpae-2809fae7a68d52c9 / f5f0b4b2-a258-42de-a2c7-c6a7a88c1d37 | 6 → 7 | PASS | PASS | PASS, SITE_OVERLAY note |
| C | Testimonials / testimonials.grid v1 / editorial_light | f395bb8 | wpae-17d930cd89457555 / 2cdedd6b-99e8-4ff5-ae9d-c77687ad469d | 6 → 7 | PASS | PASS | PASS after native chat collapse; SITE_OVERLAY preserved |
| D | Benefits / benefits.grid v1 / editorial_light | bf5992e | wpae-68abefd3c3529628 / a90f1ace-fc7d-4f50-a1c2-7f6e35ba94a5 | 6 → 7 | PASS | PASS | PASS; 3/2/1 responsive tracks, SITE_OVERLAY preserved |
| E | Pricing / pricing.tiers v1 / family default | 44acb9c | wpae-ec604346c0a45b9b / 49b162cd-e999-485d-b629-906e718916e4 | generation 4, Publish 5 → Undo 6 | PASS | PASS | PASS; desktop/tablet/mobile measured |

Операции выполнялись только на post=5214. Тестовые roots добавлялись по одному; root вручную не удалялся. Guarded Undo после каждого сценария восстановил именно [].

| Acceptance dimension | Result |
|---|---|
| Technical lifecycle | PASS for all five final operations; A’s failed first attempt was guarded-undone before the corrected rerun. |
| Content fidelity | PASS; exact authored text, entity ownership/order, prices, features and hrefs retained. |
| Independent native readback | PASS; A–E exports were captured after editor reload. |
| Responsive geometry | PASS at the measured public desktop/mobile widths; D tablet columns and E tablet stack also measured. |
| Visual composition | ACCEPTED_WITH_SITE_OVERLAY_NOTE; selected composition, hierarchy, native surfaces and responsive flow match each request. |
| Structural variation | PASS; centered CTA, Team entity cards, quote/author cards, icon benefit grid and Pricing hierarchy retain distinct native content topology. |
| Document restoration | PASS; every guarded Undo returned editor/public roots to [] and final root set remains []. |
| External overlay/accessibility | SITE_OVERLAY is separate: expanded greeting covers some B/C/D mobile content; native collapse frames preserve content, and persistent launcher intersects blank surface only. No external site settings were changed. |

## Source, publication и installation

Исходный baseline: commit 37678c964d8e8abe868123f2eaa9960648e7a120, v02.11.291. Во время работы обнаружено, что HEAD уже содержал последующие исходные изменения; новый runtime этап включал:

- e5d7facc78beeb21ceedef6f5faf2338c3bc9491 — v02.11.292, общий контракт геометрии коллекций и regression coverage.
- fd12c9b5fcab2112c58b3e19223c736ea698a63e — v02.11.293, общий native alignment для centered CTA intro, pill и actions.

Оба runtime commit были отправлены в origin/main; v293 установлен через WP Pusher. Plugins PHP version и inline config после reload существующего Elementor editor независимо показывали v02.11.293. Новые вкладки, страницы и drafts не создавались. PHP runtime version в этой приёмке отдельно не собирался.

На входе редактор показывал пустой canvas и выключенную кнопку «Опубликовать»; по правилу страницы это user-cleared опубликованный baseline. Read-only/editor/public чтения подтвердили [] до первого теста и после каждого Undo.

## Общая причина и границы исправления

До исправления wpae_design_plan_visual_policy() выводил collection.item_height как equal_row только при наличии authored actions, а для коллекции без действий выбирал content. Семантика внутреннего footer таким образом управляла высотой внешнего ряда. Это расходилось с требуемой геометрией: Team/Testimonials/Benefits должны были выравнивать видимые поверхности в пределах строки независимо от наличия CTA.

В v292 существующая visual_policy.collection теперь до freeze разрешает колонки по одному приоритетному пути: явное ограничение Brief → composition record → visual profile → общий детерминированный default. Default count вычисляется из item count и допустимого record ceiling; частные таблицы для 4/6 элементов удалены. Новые Plan поля фиксируют column_sources, surface_alignment и item_height. Для grid без явного override используется equal_row + stretch независимо от действий; editorial/list сохраняет natural content height. LayoutReport сообщает фактический item_count, columns, gap, track width, источник колонок и surface alignment как static_plan, не как браузерный render.

ElementorIR переносит принятый Plan в native Flex: row/wrap и gap-aware width для карточных grid; explicit align_self/stretch для видимых поверхностей; responsive direction/width остаются у Plan. Footer остаётся optional. В карточках с действиями extra space распределяется перед actions на desktop, mobile возвращается к естественному потоку; фиксированных высот, line-clamp и удаления текста нет. Frozen Plans без новых необязательных значений сохраняют прежний compatibility path. Исторические roots не переписывались.

Вторая проверенная причина была в centered CTA: accepted centered selection задавала центрированное текстовое содержимое, но container alignment не доходил до pill и actions во всех ветках native compiler. В v293 IR сохраняет container_align отдельно от text_align и передаёт его pill/actions. Это изменило общую typed mapping, без CSS по root ID.

Области реализации: includes/llm/design-plan.php, includes/llm/brief-ir.php, includes/elementor/elementor-ir.php, includes/elementor/layout-report.php; regressions — tests/design-pipeline-contract.php и tests/flex-generation-runtime.php. Исторический диагноз до правки — [DIAGNOSIS.md](DIAGNOSIS.md).

## Локальные проверки v293

Проверки уже были выполнены на исходном v293 после runtime-коммитов; в этом документальном закрытии код не менялся.

| Проверка | Результат |
|---|---:|
| Design Pipeline Contract | 952 checks OK |
| Flex Runtime | 1475 checks OK |
| Elementor patch guard | PASS |
| Node suites | 20/20 PASS |
| PHP lint изменённых PHP и тестовых файлов | 7 файлов, PASS |
| Imported template catalog | 158 manifest / 156 retrievable trees / 156 previews |
| Package manifest hash verification | 253/253 совпали; 0 missing, 0 mismatches |
| git diff --check | PASS |

Package verification этого закрытия выполнена read-only сравнением всех SHA-256 entries из wpae-package.json; runtime не менялся и hashes повторно не генерировались.

## Exact fixtures, selection и readback

Для каждого сценария полный submitted prompt сохранён в fixture, а его Brief/Plan hashes и выбор записаны в case evidence. Все пять прошли production plugin-chat route локально детерминированно: provider_calls 0, успешная transaction_write_count 1. A имеет ещё одну отдельную не принятую v292 generation до source correction.

| ID / fixture | Fixture SHA-256; exact request и accepted selection | Brief SHA-256 | Plan SHA-256 | Native export после reload |
|---|---|---|---|---|
| [A exact request](fixtures/A-cta-centered.txt) | aa5dbf0ec38d2b986d8378ee3eaa3792a232b2688788d15040b006a86bd834f2; centered CTA, pill, title/body, one #contact button; cta.centered v1, editorial_light | 26107ef18b35182559fa1102ca96d7b6305e39daebf2ef156cca109463499ce8 | 78d0183459162c57fc7327e87a4e6d66b9c8dd5c1f3f2583c36c0abd65171b0d | 9 nodes: 5 containers, 4 widgets; 0 images, 1 button |
| [B exact request](fixtures/B-team-grid.txt) | 90c530ee7c6acffa360cf3326b91bc1ce473c095aac315292b39f25ee913ff77; 4 synthetic team entities, 2 short/2 long bios, no media/actions; team.grid v1, editorial_light | 475645a2b1e37cd5f54245d54f9e380e32c82259ea6612f94809040f222c729e | 4316fc716fa0b7a3e6cb51147d016d6864a39b18d1bcb7afeb167990ddd85d9e | 28 nodes: 13 containers, 15 widgets; 0 images/buttons |
| [C exact request](fixtures/C-testimonials-grid.txt) | 0eac80465a2621212b5438f3ce5b5bc7beaf42b66784a23dc401fce2e739cee3; 4 synthetic quote/author/meta tuples, short and long, no media/ratings/actions; testimonials.grid v1, editorial_light | f11d27e2f1f6acd93c8d8f30829c5a61031445c99ae54835dfbd463cd9ada3a8 | eb5e057008e5cb9f25db99d019800231f459c39bec84abf7072c03147045af7c | 28 nodes: 13 containers, 15 widgets; 0 images/buttons |
| [D exact request](fixtures/D-benefits-grid.txt) | 1e5f654de789cf4c70d146c80a0c13cfc68358887403395b5dab168d34110259; 6 exact title/body pairs; desktop 3, tablet 2, mobile 1; no media/actions; benefits.grid v1, editorial_light | f6edeb6e5ba40173dffed662b4dca6fa45a0cee35f6edefaa50ddd5615139313 | 2a1a8c4dfc30730419a34c346dbdf5aac70b40d0b7bc1e1f3a3d369586e57e75 | 32 nodes: 11 containers, 21 widgets; 0 images/buttons |
| [E exact request](fixtures/E-pricing-three-tiers.txt) | b9f8e02d1f791f5a2be5c00b4c4100d44c68f65a180da61c8e0f4708bb0614e8; exactly three fixed-price tiers, long/short features, exact CTA/hrefs, pill + full intro, no media; pricing.tiers v1. Record has no profile selector, family default retained | 6f7cf9d8aea193cb3b2c62c8781a34fec39a06ae7a244d08a8b74636b4641f31 | 95296729bbd3342cbf26bbe35a1b8ccaad1103f5341449ecd6c0a26a6c321729 | 45 nodes: 20 containers, 25 widgets; 0 images, 3 buttons |

A accepted record hash 6fa883376cef247cf2f926821046fb5d83746e43200d974737fae72aa386eb8a; B cd48749f8ef014ab115f0c8341ce7ef82e88af013ea34c2477f703556e54a535; C 77a8235bead676e5cb9d2ec3ffe16d299d7e43cf7e711a7e841b0d2c5c0f6382; D 6b3f121c7e7902b143e254507fb809c3c186b17e79c4aa551951197fd9fa0389; E 4ac6ff61078721ec658f1fd38bb0ac1155ec9b519747c06095b874fbcd1967ce. Full contracts, saved document hashes and fingerprints are in the per-case evidence/native JSON.

Content check: A preserved exact pill/title/description/button/href; B kept each name, position and synthetic bio attached to the correct entity; C kept quote, author and company associations/order; D kept all six title/body pairs and requested 3/2/1 tracks; E kept exact intro, each price+period, every feature and three separate CTA hrefs. No unrequested images, ratings or actions were added.

## Responsive geometry and visual review

Screenshots below are the saved PNG files from the Browser Use screenshot-byte workflow. Each selected PNG was signature-checked, dimension/hash recorded in [screenshot-manifest.json](screenshot-manifest.json), opened and visually inspected. Main viewport frames and full mobile frames are separate. Public mobile was requested at windowInnerWidth=390. In B DOM reports visualViewport/client width 390 while its screenshot raster is 375×812; in C–E DOM reports windowInnerWidth 390 with visualViewport/client width 375 and 375px raster. E tablet reports windowInnerWidth 900 but visualViewport/client/raster width 885. The distinctions are recorded instead of treating PNG dimensions alone as CSS viewport.

The ui-ux-pro-max design-system query returned generic red Neo Brutalism and React Native recommendations, which conflict with the user-selected Elementor editorial_light profile and existing palette. Those recommendations were not imported. The relevant review checks were readable hierarchy, sufficient contrast, natural line wraps, mobile content width and overflow.

### A — centered CTA

The v292 first public result failed its selected centered composition: desktop action button x=304px while the centered content wrapper began x=304px, and mobile pill remained left-aligned while heading/body/action were centered. Source-level v293 correction moved the desktop button to x=555.96px within the 1280px page and the mobile pill into the centered column. Final CTA has one button only and no image or empty media box. The initial saved frame and the corrected same-fixture result are both preserved.

![A accepted-record selection in the existing editor before submission](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-v293-selection-before-submit.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-v293-selection-before-submit.png) — post 5214, existing editor, v293; pre-submit selection cta.centered / editorial_light, no operation/root/revision existed yet, editor raster 1232×923 (CSS viewport not separately measured).

![A first editor result before Publish](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-first-editor-before-publish.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-first-editor-before-publish.png) — post 5214, editor source, v292 first attempt, operation wpae-1e6934eba28c89d7/root 9da0cbc; frame is pre-Publish and the exact editor revision was not separately recorded; corresponding public Publish/readback revision 5; raster 1232×923.

![A first v292 public desktop failure](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-public-desktop-1280x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-public-desktop-1280x900.png) — post 5214, failed root 9da0cbc, operation wpae-1e6934eba28c89d7, identity 255961f8-2d7a-4c79-8cf1-e356bea06e8b, revision 5, public CSS viewport 1280×900, PNG 1280×900.

![A first v292 public mobile failure](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-public-mobile-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-public-mobile-390x844.png) — post 5214, failed root 9da0cbc, operation wpae-1e6934eba28c89d7, identity 255961f8-2d7a-4c79-8cf1-e356bea06e8b, revision 5, public CSS viewport 390×844, PNG 390×844.

![A accepted v293 public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-v293-public-desktop-1280x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-v293-public-desktop-1280x900.png) — post 5214, root 438f8e7, operation wpae-ee04b47bac4e2cfa, identity 713c3968-f33b-4d49-965a-9101dd0140fd, revision 5, public CSS viewport 1280×900, PNG 1280×900.

![A accepted v293 public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-v293-public-mobile-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/A-v293-public-mobile-390x844.png) — same post/root/operation/revision, public CSS viewport 390×844, PNG 390×844. Pill, H2, body and CTA are centered; the authored block ends naturally without clipping.

### B — Team grid

Desktop 1280×900 has two Flex columns of 560px with a 20px gap; all four visible surfaces are 231.234px high across two equal rows. Public mobile windowInnerWidth=390, root/card width 358px, and natural card heights 129.297/234.891/129.297/261.289px. All bios remain full and associated with the correct participant; no empty action footer, images or horizontal overflow. The initial mobile greeting covers part of Gamma's card; this original SITE_OVERLAY frame is retained. After native chat collapse, the greeting disappears; the persistent launcher intersects only blank card space in the full-page review.

![B public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-desktop-1280x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-desktop-1280x900.png) — post 5214, root 94ea563, operation wpae-2809fae7a68d52c9, identity f5f0b4b2-a258-42de-a2c7-c6a7a88c1d37, revision 6, public CSS viewport 1280×900, PNG 1280×900.

![B initial public mobile with site greeting](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-mobile-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-mobile-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812; greeting visibly crosses Gamma copy.

![B public mobile after native chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-mobile-collapsed-viewport.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-mobile-collapsed-viewport.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812.

![B full public mobile after native chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-mobile-fullpage-collapsed.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/B-v293-public-mobile-fullpage-collapsed.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, full-page PNG 375×1127; all four entities visible.

### C — Testimonials grid

Desktop shows true native Flex 2×2 rows: 20px gap, equal 259.281px surfaces in row one and equal 231.234px surfaces in row two. Quote text precedes bold author and company; the long quotes grow without clipping. Mobile stacks four 343px cards at 14px gap; heights 129.297/287.688/129.297/287.688px, no horizontal overflow. Initial greeting obscured Gamma's short quote; the original screenshot is kept and the greeting was collapsed through native UI. The remaining round launcher sits over blank area, not quote/author text.

![C public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-desktop-1280x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-desktop-1280x900.png) — post 5214, root f395bb8, operation wpae-17d930cd89457555, identity 2cdedd6b-99e8-4ff5-ae9d-c77687ad469d, revision 6, public CSS viewport 1280×900, PNG 1280×900.

![C initial public mobile with greeting](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-mobile-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-mobile-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812; greeting overlaps Gamma quote in the initial frame.

![C public mobile after native chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-mobile-collapsed-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-mobile-collapsed-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812.

![C full public mobile after native chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-mobile-collapsed-fullpage.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/C-v293-public-mobile-collapsed-fullpage.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, full-page PNG 375×1180.

### D — Benefits grid

The exact 3/2/1 response was confirmed in Plan, native Flex controls and public DOM. Desktop: three columns, 366.664px cards, 20px gap, two rows. At tablet windowInnerWidth=900, two columns with 410px cards and 16px gap. Mobile: one 343px column, 14px gap, natural 164.898px item height and all six pairs present. No photos or CTA. Icons repeat consistently and are visually prominent but do not compete with the title. The initial full mobile frame has a site greeting over item 3 heading/body; after native collapse, the launcher intersects blank card edge only. This site-owned overlay is not edited.

![D public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-desktop-1280x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-desktop-1280x900.png) — post 5214, root bf5992e, operation wpae-68abefd3c3529628, identity a90f1ace-fc7d-4f50-a1c2-7f6e35ba94a5, revision 6, public CSS viewport 1280×900, PNG 1280×900.

![D initial public mobile viewport](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812; greeting was not visible in this viewport frame, while it appears over benefit 3 in the preserved full-page frame below.

![D initial full public mobile with greeting](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-fullpage.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-fullpage.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, full-page PNG 375×1363; preserves greeting overlap on the third item.

![D public mobile after native chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-collapsed-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-collapsed-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812.

![D full public mobile after native chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-collapsed-fullpage.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/D-v293-public-mobile-collapsed-fullpage.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, full-page PNG 375×1363; all six benefits visible.

### E — Pricing

Three cards only; exact synthetic prices/periods, complete feature lists and separate CTA hrefs survived independent export and public DOM. Desktop 1280×900: 1140px collection, three cards 363.992×422.008px, 24px gap; all CTA share y=646.797px baseline. Tablet requested 900×900 has windowInnerWidth 900 but visualViewport/clientWidth and PNG width 885 due scrollbar; accepted Plan renders one pricing card per row. Mobile requested windowInnerWidth=390×844; client/visual/raster width is 375px; cards are 343px wide with natural heights 234.320/398.328/271.922px and 16px collection gap. No overflow, missing text, or clipping. The short tiers leave more open space before their actions because all desktop CTA share the lower axis; content is not stretched between every line and no filler is inserted. A site-owned launcher geometrically intersects part of the long second tier's box but reviewed pixels do not cover authored glyphs or buttons. The tablet capture also shows the greeting over empty card surface. Site chat settings remain unchanged.

![E public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-desktop-1280x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-desktop-1280x900.png) — post 5214, root 44acb9c, operation wpae-ec604346c0a45b9b, identity 49b162cd-e999-485d-b629-906e718916e4, revision 5 after Publish/reload, public CSS viewport 1280×900, PNG 1280×900.

![E initial public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-mobile-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-mobile-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812.

![E public mobile after chat review](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-mobile-collapsed-390x844.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-mobile-collapsed-390x844.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, PNG 375×812.

![E full public mobile with all three tiers](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-mobile-collapsed-fullpage.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-mobile-collapsed-fullpage.png) — same post/root/operation/revision, public CSS windowInnerWidth=390 × 844, full-page PNG 375×1243.

![E public tablet showing one tier per row](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-tablet-900x900.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-tablet-900x900.png) — same post/root/operation/revision, public CSS windowInnerWidth=900×900; visualViewport/client and PNG 885×885.

![E full public tablet](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-tablet-fullpage.png)

[Download PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-repeat-geometry-v292/screenshots/E-v293-public-tablet-fullpage.png) — same post/root/operation/revision, public CSS windowInnerWidth=900×900; full-page PNG 885×1288. The site greeting crosses empty right-side card area; CTA and authored text remain visible.

## Document restoration and remaining limits

After A through E, guarded Undo refreshed the descriptor for the exact operation/identity/contract/root and returned no owned roots. Editor was reloaded only after server/native state matched. Independent public reads showed no roots and no test content. No user root existed at initial baseline; no manual page/root deletion or global/plugin setting change occurred. Final state: editor roots []; public roots [].

Residual observation: the site-owned chat greeting can obscure content before native collapse in B/C/D; the small persistent launcher overlays blank card regions in some collapsed captures. E tablet also retains its site greeting over blank card area. This is recorded as SITE_OVERLAY rather than attributed to generated layout. The public generation itself has no horizontal overflow or clipping in the measured cases.

Machine-readable summary: [acceptance matrix](acceptance-matrix.json), [screenshot manifest](screenshot-manifest.json), and [consolidated B case evidence](B-v293-evidence.json). Per-case exact request, selection hashes, operation descriptors, native exports, DOM measurements and Undo readbacks are retained beside this report.

Housekeeping: after checking the PNG replacements and report links, 28 intermediate/outdated JPEG files (including the obsolete v292 WP Pusher frame) were removed from this task's own audit folder. The current 25 PNG evidence frames, first-defect images and native screenshot-byte artifacts remain. No files outside this audit folder were deleted.
