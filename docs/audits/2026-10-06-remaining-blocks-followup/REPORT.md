# Remaining-family live follow-up — alternate Team/Testimonials structures, CTA and Partners

Date: 2026-10-06. Target: existing Elementor post `5214`, public page `https://mazhenov.kz/pricing-contract-live-v123/`.

## Scope and first checkpoint

The user excluded FAQ and Services and asked to skip previously accepted family/composition pairs. At the first checkpoint, Pricing (two and three tiers), Hero, About, Benefits, Team grid, and Testimonials editorial rows were skipped; only the standalone CTA compatibility smoke was attempted. This report retains those CTA facts and adds the later Team editorial-rows and Testimonials grid generations below. Those are distinct structural alternatives, not repeats of the previously accepted Team grid and Testimonials editorial rows.

## Version and baseline

- Repository HEAD before this documentation follow-up: `b8e42e9bd9f3e0eb5fa34b09dc63feb930c713d5`.
- Runtime source: `v02.11.271`, runtime commit `c2328d4e1efd5d32acb48ef4cffce017fdbd4503`.
- WordPress Plugins PHP row: `v02.11.271`.
- Existing editor inline config: `v02.11.271`.
- Editor CSS viewport: `1232×923`; Publish control was disabled.
- Before request, editor and public root sets were both `[]`.

No plugin update was needed. No runtime code or WordPress settings were changed.

## Exact request and result

Fixture: [J-cta-exact-request.txt](../2026-10-06-lifecycle-layout-v270/J-cta-exact-request.txt), SHA-256 `2984c86e1cf89e922eebabe8da740b4b45fe8a930a4ae14556338191e66769fc`.

The exact fixture was submitted once through the existing post `5214` plugin-chat UI:

> Создай самостоятельный CTA-блок. Заголовок “Обсудим ваш следующий проект”. Описание “Расскажите о задаче — определим подход и следующий шаг”. Основная кнопка “Обсудить проект”, ссылка #contact. Вторая кнопка “Посмотреть работы”, ссылка #projects. Изображения не добавляй. Сохрани текст и обе ссылки точно.

The chat refused before writing: “Подходящий шаблон библиотеки не прошёл производственную проверку адаптера; изменения не записаны.” Read-only diagnostics in the editor showed:

- `post_id=5214`, `archetype=cta`;
- `candidate_count=22`, `compatible_candidate_count=0`;
- `provider_call_count=0`, `write_count=0`.

There was no operation, identity, accepted contract, root, or revision. Undo was not invoked because no transaction occurred. No retry or alternate prompt was used. CTA remains outside `migrated_create` and has no canonical composition record, per the prior scope.

After reloading the existing public tab, the root set remained `[]`, CSS viewport was `1232×923`, and the title, primary button, and secondary button strings were absent. Thus no generation or content/visual acceptance is claimed. The document baseline remains unchanged because the write count was zero.

## Screenshot evidence

The current editor frame shows the refusal and the unchanged empty canvas. It came from the existing Browser Use tab after the request; Browser Use returned JPEG bytes, which were saved, converted to PNG, signature and dimensions checked, then opened and visually inspected.

Editor source, post `5214`, no root/operation, CSS viewport `1232×923`, PNG `1232×923`:

![CTA library preflight refusal in the Elementor editor](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/cta-v271-preflight-refusal.png)

[Download the refusal screenshot](screenshots/cta-v271-preflight-refusal.png)

No public/mobile CTA screenshot exists because no block was written. The screenshot documents the refusal, not acceptance.

## Status

| Area | Result |
|---|---|
| Technical route | Preflight refusal; 22 candidates, 0 production-compatible |
| Provider calls / writes | `0 / 0` |
| Root / operation / identity / revision | None created |
| Content fidelity | Not run; no block |
| Responsive geometry / visual composition | Not run; no block |
| Public readback | Fresh root set `[]`; CTA copy absent at CSS `1232×923` |
| Document restoration | Baseline `[]` unchanged without Undo |
| Services / FAQ / prior successful families | Skipped per user |
| Local runtime tests / package hashes | Not rerun; runtime unchanged |

The live attempt does not add a new accepted family generation. Its concrete blocker is the production library-adapter preflight, and the earlier instruction not to migrate CTA remains in force.

## Continuation: two alternate structures and Partners refusal

### Current source and final page state

- Source and installed WP AI Executor remained `v02.11.271`, runtime commit `c2328d4e1efd5d32acb48ef4cffce017fdbd4503`; repository HEAD before documentation edits was `b8e42e9bd9f3e0eb5fa34b09dc63feb930c713d5`.
- Plugins PHP and the existing Elementor editor inline config independently showed `v02.11.271`. No update was needed and no runtime or WordPress setting changed.
- The existing editor tab for post `5214` and the existing public page tab were used. After the two test operations were guarded-Undone, a fresh read-only check measured editor canvas roots `[]`, public roots `[]`, public `innerWidth=1232`, `innerHeight=923`, document width/scroll width `1232`, and disabled Publish. Snapshot: [final-baseline-roots.json](evidence/final-baseline-roots.json).
- There were exactly two new live generations, no generation retries, and two successful operation-scoped Undos. No previously accepted family/composition pair was regenerated. The live baseline at the start and finish of this continuation was empty.

### Team — `team.editorial_rows`

Exact unchanged request fixture: [A-B-team-exact-request.txt](../2026-10-05-m3-1-entities/A-B-team-exact-request.txt). It specifies a synthetic four-person team, exact badge/title/body, exact name/role/bio groups, and no portraits. The user-facing request was submitted through the normal plugin chat with `Редакционные строки` selected; the deterministic pipeline made one Brief, one DesignPlan and one write, with `provider_calls=0`.

- Brief SHA-256 `8335a524db685d6cc755baab2b545f415672c1cc58c48834c516b42bc73b78dd`; Plan SHA-256 `4b224dd2c29d3a2a6037e329df0bdefc7ea46aedd94cba405f3c377f5cdd44da`; record `team.editorial_rows`, hash `ffaea52b36377285954bf3a4766500d300ae7ef9bbbaace7d4d18fcae8450da8`, profile `default`.
- Operation `wpae-1ae82f92fec16468`; identity `869a0aca-0789-45d0-942a-973f434f4060`; accepted contract `contract-1e0495616ff06c03731a693d`, hash `1e0495616ff06c03731a693d7d18a99f4a69f55321d5f6162157d435172cce77`; root `18789cc`. Generation revision 4, fresh post-Publish descriptor revision 6 (`available`), and fresh post-Undo descriptor revision 7 (`already_undone`). `write_count=1` for create; Undo restored the user-confirmed empty model.
- Native editor readback contained 36 nodes with all four exact name/role/bio pairings, no portrait or CTA widgets. Public root/readback after reload matched. Desktop CSS viewport was `1232×923`; the content container was 1140px; four rows measured 111/127/111/127px, with visible copy intact and no horizontal overflow. Public mobile and editor mobile evidence were not captured.
- First result was not repaired. Composition and text passed; visual acceptance is **PARTIAL / SITE_OVERLAP** because the site-owned greeting obscures the end of the fourth biography in the public frame. No site setting or overlay CSS was changed.

Post `5214`, root `18789cc`, operation `wpae-1ae82f92fec16468`, identity `869a0aca-0789-45d0-942a-973f434f4060`, final descriptor revision 7, public CSS viewport `1232×923`, PNG `1232×923`:

![Team editorial rows after Publish and public reload; site greeting overlaps the fourth biography](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/team-editorial-rows-public-full.png)

[Download Team public viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/team-editorial-rows-public-viewport.png) · [Download Team full PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/team-editorial-rows-public-full.png)

### Testimonials — `testimonials.grid`

Exact unchanged request fixture: [C-D-testimonials-exact-request.txt](../2026-10-05-m3-1-entities/C-D-testimonials-exact-request.txt). It specifies six synthetic quote/author/company groups, two quote lengths, and no photos or ratings. The user-facing request went through the normal plugin chat with the `Карточки отзывов` composition selected; one deterministic Brief, one Plan and one write, with `provider_calls=0`.

- Brief SHA-256 `53eedbedf3b4fd549bf506b17f614e4cc604e469a989ebd2d1809034c4e9978b`; Plan SHA-256 `92e5c4b36d2aad589ee2a385b477531927b37453274c6d56ba9c87786381e538`; record `testimonials.grid`, hash `77a8235bead676e5cb9d2ec3ffe16d299d7e43cf7e711a7e841b0d2c5c0f6382`, six actual items, default profile.
- Operation `wpae-7338a43ebdcd7d8b`; identity `d2f9c2ba-655a-46f8-b3f0-9c7e68660018`; accepted contract `contract-096876cf222f2dd25508cc62`, hash `096876cf222f2dd25508cc628cf69476252a525da294f1cb647fd4a173e8b620`; root `4c9e82b`. Generation revision 4, post-Publish descriptor revision 6 (`available`), post-Undo descriptor revision 7 (`already_undone`).
- The 38-node native tree and editor readback after reload retained all six exact quote/author/meta groups, with zero image, rating, or link widgets. Frozen compiled and readback signatures match. Public DOM text/entity ownership matched after reload. No patch or second generation was applied.
- The legacy composition identity remains `testimonials.three_cards`, while the selected record is `testimonials.grid`. The actual six-item public native grid measured two desktop columns at 558px each with a 24px gap inside a 1140px container; Plan states tablet/mobile `stack`, consistent with the measured editor mobile preview's one column. Treat the legacy `three_cards` name as an identifier, not proof of three rendered columns. Actual DOM geometry is saved in [testimonials-grid-public-geometry.json](evidence/testimonials-grid-public-geometry.json).
- At public desktop CSS viewport `1232×923`, short and long cards both stretched to 225.4px within each two-card row. Short quote content occupied 98.6px, leaving about 126.8px of open space; the longer text remained complete with `overflow: visible`. This is a visible density issue, not clipping. Page `scrollWidth` equalled `clientWidth` at 1217px (scrollbar-adjusted), so no horizontal overflow was measured.
- Public mobile was **BLOCKED**: the documented Browser Use page controls did not expose a public viewport override, and the actual public tab remained `1232×923`. Separately, Elementor mobile preview measured a real inner frame of `360×736`: one 313px column, 16px gap, short cards 148.6px, long cards 327.8px, full text and no horizontal overflow. This is editor mobile evidence, not public-mobile PASS.
- Visual composition is **PARTIAL / SITE_OVERLAP**. Two columns are readable and visibly distinct from editorial rows; the shorter quotes leave excess empty space under their text. On the public full page, the site's own greeting/avatar overlaps a portion of the sixth quote. The greeting was collapsed using its visible site control for an additional capture; the launcher still overlaps content. No external plugin or setting was changed.

Post `5214`, root `4c9e82b`, operation `wpae-7338a43ebdcd7d8b`, identity `d2f9c2ba-655a-46f8-b3f0-9c7e68660018`, final descriptor revision 7, public CSS viewport `1232×923`, viewport PNG `1217×912`, full-page PNG `1217×1077`:

![Testimonials grid after Publish and public reload; the site chat launcher overlaps the last card](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/testimonials-grid-public-full.png)

[Download Testimonials public viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/testimonials-grid-public-viewport.png) · [Download full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/testimonials-grid-public-full.png) · [Download chat-collapsed PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/testimonials-grid-public-chat-collapsed.png)

The following capture is the **Elementor editor mobile preview**, not the public page at mobile width. Its nested preview CSS viewport was `360×736`; the outer PNG is `1232×923`:

![Testimonials in Elementor editor mobile preview, nested CSS viewport 360 by 736](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/testimonials-grid-editor-mobile-preview-clear.png)

[Download editor mobile-preview PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/testimonials-grid-editor-mobile-preview-clear.png)

### Partners carousel — refusal before write

The supplied exact five-image native Elementor Image Carousel request is retained in [partners-carousel-exact-request.txt](fixtures/partners-carousel-exact-request.txt). The first request, which also mentioned preserving the global header/menu, was classified as `mega_menu` and refused preflight (7 candidates, 0 compatible, 0 provider calls, 0 writes). One explicit “Карусель партнёров” clarification was then submitted through the same ordinary plugin chat. It reached `library_agent` with one candidate, made two provider calls, and returned `model_declined`; `write_count=0`, no operation or root. This is one compatibility retry, not a generation or accepted family. No further retry, native JSON import, or hand-built carousel was used.

Editor refusal evidence, post `5214`, no root/operation, CSS viewport and PNG `1232×923`:

![Partner carousel request refused by library agent before write](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/partners-v271-refusal-editor.png)

[Download Partners refusal PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-remaining-blocks-followup/screenshots/partners-v271-refusal-editor.png)

### Consolidated status

| Scenario | Generation / transaction | Content and native readback | Geometry / visual review | Undo / final document |
|---|---|---|---|---|
| Team editorial rows | One write; operation `wpae-1ae82f92fec16468`; root `18789cc`; r4 → r6 after Publish → r7 after Undo | Exact 4 name/role/bio groups; 36-node native tree | Desktop measured; no public mobile evidence; **PARTIAL / SITE_OVERLAP** | Guarded Undo PASS; roots `[]` |
| Testimonials grid | One write; operation `wpae-7338a43ebdcd7d8b`; root `4c9e82b`; r4 → r6 after Publish → r7 after Undo | Exact 6 quote/author/meta groups; 38-node tree and frozen readback signature match | Desktop and editor mobile preview measured; public mobile blocked; **PARTIAL** for short-card whitespace and site overlay | Guarded Undo PASS; roots `[]` |
| Standalone CTA | Production adapter preflight refused: 22 candidates, 0 compatible, provider 0, write 0 | Not generated; legacy `cta.band` has no canonical typed record | Not run | No transaction; baseline unchanged |
| Partners carousel | Misclassified/refused, then one explicit family clarification declined by model; total writes 0 | No native carousel generated | Not run | No transaction; baseline unchanged |
| Services / FAQ | Excluded by user | Not run in this continuation | Not run | Unchanged |
| Previously accepted Hero, About, Benefits, Pricing, Team grid, Testimonials editorial rows | Skipped; no successful composition repeated | Historical acceptance evidence remains in its original audits | Historical status not promoted or changed here | Unchanged |

All nine PNG screenshots in this audit were checked with `file`/`sips` for PNG signature and dimensions and opened for visual review; the original JPEG bytes are retained beside conversions. Raw originals, geometry, generation traces, native exports and fixtures are in this audit folder. Runtime was unchanged, so runtime suites and package/hash checks were not rerun.
