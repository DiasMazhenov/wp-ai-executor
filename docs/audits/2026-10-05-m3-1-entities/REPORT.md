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
