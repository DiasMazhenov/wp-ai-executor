# M3.1 — Team and Testimonials, cross-family live acceptance

## Scope and implementation

The migration uses one pipeline: BriefIR owns exact references/source spans and media ownership; DesignPlan freezes composition/profile, full intro, repeat layout, typography and badge colors; ElementorIR/compiler map those decisions to native controls; the existing normalizer/transaction/readback boundary stays in place. No separate generator/DSL, structured model extraction, manual Elementor JSON insertion, or direct edit was introduced.

Added `team.grid`, `team.editorial_rows`, `testimonials.grid`, `testimonials.editorial_rows`, with `editorial_light` and `soft_cards_light`. Testimonial quotes/authors remain distinct even when quote strings repeat. Photos/ratings aren't invented. Team portrait acceptance is blocked because no authorized employee portraits were supplied. Pricing intro body now binds through its existing builder; historical frozen contracts remain unchanged. Services/Pricing reuse existing records and recipes.

The v266 first Services attempt exposed a strict-freeze defect: accepted `visual_policy` was present but the Services Brief lacked `canonical_create`; normalizer then added the required root `wpae-ds` marker after freeze. First attempt was refused with provider_calls=0/write_count=0. Compiler was fixed to resolve the mandatory design-system marker on roots with an accepted visual policy before freeze. The production regression now uses both actual mandatory classes. v267 Services then passed the owned-model guard, Publish, editor reload, complete native JSON export and field comparison.

The first v267 public render exposed a second real defect that the advisory Vision score 90 missed: the Services pill container was white and its label text was also white. DOM measured both computed colors; the PNG showed an empty pill. A v268 source correction now freezes `pill` text, background and border colors in the existing DesignPlan `eyebrow_colors` and compiles them into the existing Services badge container/label. Historical plans lacking these extra color keys keep their old compiler behavior. Local contracts pass; v268 is committed as `95a3af8a880068026be45fc0a4a44d25f48b4ec9`, pushed and independently matched on origin/main; it has **not** been installed or visually retested yet.

## Source, install, test and visual statuses

| Layer | Evidence/status |
|---|---|
| v266 source | runtime commit `1f2519bb2044942e92a647cdf093e6a8f0ec6ebc`, pushed and remote matched |
| v267 root-marker correction | commit `017c1ae5fdc4f01c7a4207d4eeda7ff26afaf817`, pushed; `git ls-remote` matched |
| v266 install/editor | Plugins PHP and reloaded editor config v266, confirmed |
| v267 install/editor | Plugins PHP v267; clean editor reload inline config v267, confirmed |
| v268 source | local change prepared; local checks listed below; commit/push/install pending |
| Live lifecycle | A–D completed v266. E first attempt refused safely, then v267 published/read back, but final guarded Undo currently stopped on a dirty editor state; page has E root `b48abe1` at revision 6. The user previously authorized duplicate/reopen/close for dirty editor reload; awaiting their explicit answer to the current recovery choice. Do not start F until baseline is independently empty. |
| Visual desktop | A–E screenshots captured/opened; A and E have confirmed visual defects; C has a subjective repetition concern. Per-scenario notes below. |
| Public responsive | BLOCKED: viewport override never changes real `window.innerWidth` from 1232. Requested 320/390/767/768/1024/1025/1280 actual-width measurements were unavailable. No editor mobile preview is claimed as public mobile. |

The persisted Plugins and inline editor evidence are in `install-editor-v266.json`, `install-editor-v267.json`. v267 is installed; current editor still has that loaded source. E pill correction requires a clean v268 editor reload before retry.

## Local checks

On the source containing the v268 color-policy edit:

- `php -l includes/llm/design-plan.php` — PASS
- `php -l includes/elementor/elementor-ir.php` — PASS
- `php -l tests/m3-entities-contract.php` — PASS
- `php -l wp-ai-executor.php` — PASS
- `php tests/design-pipeline-contract.php` — 827 PASS
- `php tests/flex-generation-runtime.php` — 1350 PASS
- `node --test tests/*.test.js` — 15/15 PASS (run before the color-only delta)
- `php tests/elementor-patch-guard.php` — PASS (run before the color-only delta)
- `php tests/imported-template-catalog.php` — 158 manifest / 156 retrievable / 156 preview PASS (run before the color-only delta)
- `php docs/audits/2026-09-12/package-probe.php` — 252 hashes, four package cases PASS after v268 manifest refresh
- `git diff --check` — PASS on the staged source/docs/evidence diff

Full package diagnostic JSON still has the historical malformed UTF-8 serialization issue; its compact summary is valid. Local checks and mock writes do not prove browser appearance.

## Per-scenario results and evidence

Every completed root was generated through the ordinary chat path, Published, editor-reloaded, exported as complete native JSON, compared against the saved first native tree, viewed on the public URL and captured at actual CSS viewport 1232×923. For A–D and corrected first E result, the independent native comparison found zero authored-field differences. A–D were guarded-Undone and baseline-empty was confirmed before the next case. E is presently the only persisted root; its Undo hit the ownership guard's dirty-editor stop. The full traces/configs/exports/comparison/measurements are next to each PNG.

### A — Team grid, v266

Fixture: four synthetic people, full eyebrow/title/body, unequal positions and short/long bios; no photos. Root `3178f61`, operation `wpae-df7ea9c2e4a4d350`, operation identity `b73a21e5-19fe-4fd6-ba27-5f8fbf495127`, final Undo revision 7. Provider calls 0, one write, native readback zero authored differences, exact baseline restored.

Public desktop measured 1232×923 CSS px; root height 871.40px, no document or target-node overflow, zero images. Two-column grid, H2 intro and H3 names; biographies remained exact and flowed naturally. Pill displayed. The site's fixed welcome overlay intersects the last line of the fourth biography: `SITE_OVERLAP`; visual access remains REVIEW_REQUIRED. This is an existing site overlay, not hidden by changing site settings.

![A Team grid public after Publish/reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/A-v266-public-desktop.png)

[Download A PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/A-v266-public-desktop.png) — 1232×923 PNG.

### B — Team editorial rows, same Brief, v266

Root `79bebe3`, operation `wpae-bd8413a091d8594c`, identity `6ea7ebab-6667-4414-8ba1-e116613effb4`, final Undo revision 6. Same exact four-person content/assets as A, only record changed. Provider calls 0, one write, native comparison zero authored differences, baseline restored.

Public desktop actual viewport 1232×923; PNG 1217×975; root height 943.35px, no overflow. Distinct editorial topology: four horizontal rows, identity/copy columns, body content aligned left, short/long copy rows grow naturally. No employee portrait was fabricated. Desktop technical/content acceptance PASS; public responsive remains BLOCKED.

![B Team editorial rows public after Publish/reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/B-v266-public-desktop.png)

[Download B PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/B-v266-public-desktop.png) — 1217×975 PNG.

### C — Testimonials grid, v266

Fixture: six exact synthetic quotes/authors/metadata, including repeated quote text assigned to different authors; no ratings/photos. Root `978a738`, operation `wpae-e66b6355ef433f36`, identity `ed40c767-7350-44b6-a450-0a72bf4395bb`, final Undo revision 6. Provider calls 0, one write, native comparison zero authored differences, baseline restored.

Public desktop 1232×923; PNG 1217×1126; root height 1094.59px, no overflow. All six exact quote/author/meta groups render in two columns; short and long cards use natural content heights. Advisory Vision score 60/confidence 95 said “single vertical column”; public DOM and PNG disprove that observation. Its concern about repetitive isolated cards is aesthetic and remains REVIEW_REQUIRED; no automatic replacement or cosmetic repair was applied.

![C Testimonials grid public after Publish/reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/C-v266-public-desktop.png)

[Download C PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/C-v266-public-desktop.png) — 1217×1126 PNG.

### D — Testimonials editorial rows, same Brief, v266

Root `bcc1d46`, operation `wpae-06bb72bdb0e0881b`, identity `abee04b7-af60-44ec-bc79-b08f885123c2`, final Undo revision 6. Same exact six testimonial entities as C, only record changed. Provider calls 0, one write, complete independent native export and zero authored-field differences, baseline restored.

Public desktop 1232×923; final fresh post-reload PNG 1217×1276; root height 1244.59px, no overflow. Six separated editorial rows; copy left aligned; short and long entries grow by content. Advisory complained of excessive spacing; measured row gaps were 20px and row heights followed content, so its severity was not confirmed by public DOM/pixels.

![D Testimonials editorial rows public after confirmed Publish/reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/D-v266-confirmed-public-desktop.png)

[Download D PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/D-v266-confirmed-public-desktop.png) — 1217×1276 PNG.

### E — Services photo cards

Fixture exact request is `E-services-exact-request.txt`: three services with deliberately unequal descriptions, exact distinct CTA labels and `#strategy`, `#projects`, `#support`, and existing owner-specific catalog assets/alts. v266 first operation `wpae-3377a398502e7e67` refused frozen-normalization mismatch before write (provider 0/write 0). No retry was made on v266.

After the v267 common compiler fix, operation `wpae-008116dc9ff6c108`, identity `fa049ed9-afad-4e15-b885-556b4bd36d34`, root `b48abe1`, revision 6, was generated via the typed path, passed owned-model guard, was Published and reloaded, and exported completely from the live native navigator. Native comparison: zero authored differences. Public DOM has exact text and CTA hrefs; all three assigned catalog images loaded at 1200×900, `object-fit: cover`, centered, with exact service-specific alts. Root height 1060.41px; no page or root overflow. Desktop card layout is three columns, the long description expands the middle card, and exact copy remains uncut.

But the public PNG and computed DOM showed the pill text color `rgb(255,255,255)` on a white background `rgb(255,255,255)`; border is gray. This is a confirmed visual defect. Automatic Vision score 90 missed it. The supplied defect evidence is `E-v267-pill-defect.json` and the original first render is preserved; no manual repair was made. v268 Plan/compiler regression now coordinates these values but is NOT live. E guarded Undo then stopped because the editor reported local unsaved changes and its Publish control became active. The published server root remains E at revision 6. No page data was erased or manually replaced; hold Pricing until the user resolves that dirty-editor guard.

![E Services first v267 public render — pill contrast defect retained as evidence](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/E-v267-public-desktop.png)

[Download E first-render PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-m3-1-entities/E-v267-public-desktop.png) — 1217×1092 PNG.

### F — Pricing tiers

NOT RUN. It is dependent on recovering an independently verified empty baseline after E and installing/reloading v268. No root, operation or screenshot exists for F.

## Baseline, final root set and outstanding acceptance

The independently observed initial target was `post=5214`, no Elementor roots, JSON SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`, public HTML hash for empty root `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`. A–D guarded Undo verified the exact empty baseline before each succeeding case. Current saved post baseline contains only the single test root `b48abe1` (Services E). No pre-existing user content was present at the start. Site chat overlay stayed enabled and was documented above.

Remaining: resolve editor unsaved-state guard; finish E undo, install v268, rerun E and compare first/fixed result, verify exact baseline; run F Pricing with exact fixture, independent native readback, public DOM/desktop screenshot and guarded Undo; update final roots; retain mobile as BLOCKED unless actual CSS viewport can be changed via the documented browser control. Until then overall visual acceptance is incomplete and no full M3.1 PASS is claimed.

### Later state reconciliation — v270 check, 2026-10-06

This continuation supersedes the E-root checkpoint above for *current saved page state only*: after v270 was installed and the existing editor/public page freshly loaded, both sources reported an empty root set `[]` (JSON hash `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`). The former E operation for `b48abe1` now reports `contract_missing`; the M3.1 report's older “E root remains” statement is historical. No JSON was imported or restored. This does not retroactively prove when or how the page became empty.

M3.1 scenarios and v270 generation are separate: the current continuation did not run Team, Testimonials, or Pricing again and does not upgrade historical evidence to v270. Team grid and Testimonials editorial rows remain historical v266 results. Pricing's earlier exact two-tier fixture remains historical v264/v265 evidence; M3.1 Pricing scenario F (three tiers) remains unrun. Public mobile status in historical runs remains as recorded in each evidence package; no new mobile result is inferred. Current operation ownership and baseline reconciliation are recorded in [the v270 lifecycle/layout audit](../2026-10-06-lifecycle-layout-v270/REPORT.md).

## 2026-10-06 — v269 five-family live continuation

This continuation supersedes the older “E remains / F NOT RUN” checkpoint above for the live work below. Runtime source stayed at HEAD 5f63cc112fffe98724ab464b8d32c0fe9f367539, plugin v02.11.269. WP Pusher/Plugins v269 had been confirmed; this continuation reloaded the existing editor and observed inline v02.11.269. The user-supplied Elementor export was read-only and matched the preserved Services baseline root b48abe1; it was not imported. Before testing, editor/public contained only [b48abe1].

All five cases used the ordinary plugin chat path on post 5214. Each generated one root with write_count=1 and provider_calls=0, was Published, checked in the public DOM, then removed only by that operation’s guarded Undo. No manual JSON import, duplicate append, repair, new page/draft, or deletion of a user root occurred. Revisions below are the operation ledger revisions.

| Family | Root / operation / identity | Recipe or record; Brief and Plan | Public result and responsive measurements | Status |
|---|---|---|---|---|
| Services | 2c663fd / wpae-87e49a31a93a5752 / f3995921-56dd-41a7-abb4-d98ef30a6d33, r4 | services.photo_cards, explicit_request; Brief 7dce09d62950eca4b49455b824c3eac0f0ab06b9f46dd8c8d268f56a8a801e92. Three exact services and separate CTA hrefs (#strategy, #projects, #support). Existing permitted catalog images were assigned by service with the observed service-specific alt text. | Public desktop capture at CSS 1232×923. All three cards and CTAs appeared; the site-owned greeting widget intersected the third card CTA. The pill and full content were visible. | Generation, public content, and guarded Undo PASS; visual review PARTIAL because of site overlay. |
| Hero | 4c5d508 / wpae-41612dd2fb2d5967 / 8920787b-380b-4f4a-812a-b0be34ecb763, r4 | hero.split_60_40.right; Brief 3aa85d2e19936c14b2829a03bbe8c7095e2bb7476e3a772e638b6ea890a8b8a7; Plan fd0c890d18df2d3008e9e79879b14520e673419272f5bc2eacc3af83757d0add. Exact fixture B-C-D; Unsplash photo-1766230976347-c5badd3f76c9, alt “Современный архитектурный интерьер.” | Public CSS 1232×923, document width 1217, no horizontal overflow. Root 1217×395.1; copy 669.6px; media 446.4px; gap 24px. Image loaded 1200×675. H1 56px/700/58.8px. Chat widget covered part of the image, not copy or CTA. | Technical/content and desktop composition PASS; public mobile NOT RUN; guarded Undo PASS. |
| About | b4825de / wpae-3a0b99add34d4807 / e59ebcc2-094e-4b5a-8a93-6507a36fea9b, r4 | about.split_60_40.right; Brief f7ead05920b683b0c44911f1cc22a507ef8b8e9088b617df1cde17ec39cf5ebe; Plan 07ff57fe9295a81b6c81b1709d1b635260dcc394ed57f5bdf3477d604d57bd99. Exact fixture E; same allowed Unsplash asset and alt as Hero; CTA #about. | Desktop CSS 1232×923, document width 1217, no overflow. Root 1217×405.8; copy 669.6px; media 446.4px; gap 24px. H2 56px/700/58.8px and naturally wraps to two lines. Public mobile CSS 390×844, document width 375, no overflow; copy-first, H2 36px/37.8px. Existing chat overlay obscured part of the mobile body/media. | Technical/content PASS; mobile geometry PASS; visual review PARTIAL due overlay. Advisory Vision’s “extreme wrapping” claim was contradicted by measured DOM and pixels. Guarded Undo PASS. |
| Benefits | 206f35b / wpae-0d749e5381b1b802 / 5d1b29d5-ca42-4c56-87ab-879f90083702, r4 | benefits.grid; Brief 89aa68ade81152988f2a7468191283d7d7f0a301b3fe25ba0b11d9622c8c1f92; Plan 6360ea60677b961d8dc13cc1909b76dbb6d5289865d765ce273c67edbbba2cf1. Exact fixture F-G-H; photo prohibition honored. | Desktop CSS 1232×923, document width 1217, no overflow. Native grid 1140px wide, 2×558px columns, gap 24px; cards 185px high. Mobile CSS 390×844, document width 375, no overflow; cards stack at 343px with 16px gap and no images. Chat overlay covered the first card heading and part of its icon on mobile. | Technical/content and grid geometry PASS; mobile visual review PARTIAL due overlay; guarded Undo PASS. |
| Pricing | 8859f30 / wpae-e67a76fd2b2199d1 / 5d9f88d1-4266-49ca-b28c-0c2c9ae0f65d, r4 | pricing.tiers; Brief 1029d67d34917aeb74a70dea85e48735ae88446d0b53da61e82c4d4177a44136; Plan 61c22fc0b165f5e353a11ce0f4be0cb4af439c98b0518906e602f61782501de7. Exact fixture I; two tiers, exact feature strings, distinct links #start and #project, no media. | Desktop CSS 1232×923, document width 1217, no overflow. Grid 1140px wide with 2×558px cards and 24px gap; both cards 295.6px high, 24px padding; CTAs align. Mobile CSS 390×844, document width 375, no overflow; one 343px column, 16px gap; cards 287.9px high. The site chat greeting overlapped the first mobile card’s feature area. Plan names composition pricing.three_cards although the exact request has two items; native layout correctly rendered two columns without an empty card. | Technical/content and desktop composition PASS; mobile geometry PASS; mobile visual review PARTIAL due overlay; record/count mismatch noted; guarded Undo PASS. |

For every case the frozen compiler/readback signatures matched and a reloaded editor plus public DOM showed the test root/content before Undo. Full native JSON export after reload was not retained for every case, so this report does not claim that additional proof. After Pricing Undo, public DOM contained Services root b48abe1 and no 8859f30/Pricing content; the reloaded editor also showed only the baseline. Final live root set is [b48abe1].

### Screenshot and acceptance boundary

Fresh public desktop captures were made after Publish; About, Benefits, and Pricing also had captures at actual public CSS viewport 390×844. Captures returned as JPEG bytes and were visually reviewed inline in the Browser Use result. The current documented CUA/Browser Use surface exposes capture but no local-file writer for those returned bytes. Therefore none of these captures was persisted, converted to PNG, reopened as a file, dimension-verified, or linked. Screenshot artifact acceptance is SCREENSHOT BLOCKED for all five cases; no PNG path or dimensions are asserted. The site chat widget is existing page content and was left unchanged.

The requested threshold of five distinct block families was met. This continuation did not rerun every B-J variant: Hero left/soft-cards, Benefits list/alternate profile, and native FAQ remain outside this five-family matrix. No source code changed in this continuation; v269’s earlier local checks remain the available source evidence and were not rerun. Structured model extraction remains inactive.

The later user-supplied Elementor export is a read-only reference whose root is the preserved Services baseline `b48abe1`. It was not inserted and is not evidence of the generated `services.photo_cards` output. The separate Services generation and its current measurements are recorded above; this distinction preserves the actual production-pipeline evidence.
