# Lifecycle synchronization and shared card layout — v270 live continuation

Date: 2026-10-06 (Asia/Almaty)
Target: existing post `5214`; public page [pricing-contract-live-v123](https://mazhenov.kz/pricing-contract-live-v123/)
Source/runtime: v02.11.270, runtime commit `5c35b5cac6bee711231b18b6c6dd2859c2bcb777`; report started at HEAD `864019713a4a34e6a86e3ce01358e32a8b870686`.

> **Correction:** The earlier v270 checkpoint reported no live generation because it observed an empty canvas after the user had deleted the prior Services test root. The user later explicitly confirmed that deletion and authorized continuing from the cleared page. The empty canvas was the intended test baseline, not evidence of a failed Publish. This section is authoritative for the continuation; the historical checkpoint is preserved below.

## Execution and release state

The installed source was already v02.11.270; Plugins PHP and the reloaded editor inline version both independently reported v270. No runtime source changed in this continuation, so no version bump or repeat of the full local suite was needed. No alternate page, draft, imported template, manual Elementor controls, or direct generation path was used. Each generation below used the regular plugin chat and native Publish.

Initial user-confirmed test baseline: `[]`. Nine first generations were attempted across eight distinct families; no repeat generation was made. The Services root `bcfeab4` was removed by the user after its first editor preview, as the user clarified. That case has no post-Publish readback or guarded Undo acceptance and was not repeated. B–I used the regular plugin chat path and native Publish. Seven operation-scoped Undos succeeded for B–H and restored `[]`. FAQ I remains because the ownership/fingerprint guard refused its Undo. CTA J was not generated: `cta.band` is legacy, has no canonical typed record, and a safe scoped Undo has not been proven; after the FAQ guard refusal, no further writes were safe.

## A–J live matrix

Status dimensions are separate: lifecycle, content fidelity, responsive geometry, visual composition, structural variation, and document restoration. Fixture SHA-256 values are recorded as exact-request fixture hashes; Brief hashes were not captured for these runs and are not fabricated. The per-case operation/root/revision, native evidence, measurements and screenshot index are in [acceptance-matrix-A-J.json](acceptance-matrix-A-J.json) and [live-measurements-v270.json](live-measurements-v270.json).

| Case | Family / accepted route | Root / operation / identity / generation revision | Result and restoration |
|---|---|---|---|
| A | Services `services.photo_cards`, `editorial_light` | `bcfeab4` / `wpae-2f5a88d2554ef83c` / identity not retained / revision not retained | One generation/write. User deleted the root before post-Publish acceptance; first editor evidence only. Not rerun. |
| B | Pricing `pricing.tiers` (legacy composition ID `pricing.three_cards`), two tiers | `f19f307` / `wpae-d100b2dc1c76cbd8` / `d56e1e02-f770-4d27-9eff-c0cb7d0ec797` / r5 | Exact two tiers and distinct `#start`, `#project` links; 2 native columns, aligned actions; guarded Undo r6 restored `[]`. Full native export was not retained; native readback summary and public DOM were. |
| C | Pricing `pricing.tiers`, three tiers | `99ff7f7` / `wpae-6c366b0ac547b7a5` / `9e5a923a-b6a1-4fa0-bd32-e99196353961` / r6 | Exact three tiers, pill, section title/intro, prices/features/CTAs; guarded Undo r7 restored `[]`. |
| D | Hero `hero.split_60_40.right`, `editorial_light` | `cd4d98d` / `wpae-d8ab197c302660bd` / `b43085c3-3115-4e40-91c5-3a04d7615018` / r5 | Exact Hero copy, H1, approved loaded image/alt and `#start`; distinct right-media split; Undo r6 restored `[]`. |
| E | About `about.split_60_40.left`, `editorial_light` | `6ee89b6` / `wpae-517673afeecdaed6` / `4f481f6c-0e2d-44e4-aea1-c19dcd1d1082` / r6 | Full exact body, H2, image-left editorial split, `#about`; mobile preview copy-first; Undo r7 restored `[]`. |
| F | Benefits `benefits.editorial_list`, `editorial_light` | `1126b32` / `wpae-d30a078ae23ba42a` / `4b67bc43-fc7a-49e2-a657-5359a9047e5d` / r5 | Two exact title/body pairs and icons; no photos/actions; genuinely one-column list; Undo r6 restored `[]`. Large unused right side remains a visual limitation. |
| G | Team `team.grid`, four synthetic people | `d861368` / `wpae-d35757c357effecc` / `94be84ec-403e-46f9-a755-4926b597c8f6` / r6 | Four exact name/position/bio groups, no portraits or CTA/footer; two-column native grid; greeting overlaps lower edge of final card; Undo r7 restored `[]`. |
| H | Testimonials `testimonials.editorial_rows`, six records | `7bb2750` / `wpae-a7a3bf847b5e4c10` / `55506376-7ca2-4d57-81ce-f316a1c944c7` / r5 | Six exact quote/author/meta groups; no ratings, avatars or logos; editorial rows; greeting overlaps part of final quote; Undo r6 restored `[]`. |
| I | FAQ `faq.native`, `default` | `1a1d059` / `wpae-c616e5dbb2a2a125` / `5e6a84de-849f-4012-a012-284fbcbfb2af` / r5 | Native Accordion; exact questions and answers verified open on public page. Subsequent native `tabs` serialization changed owned fingerprint; guarded Undo refused. Root remains. |
| J | CTA legacy `cta.band`, no canonical typed record | — | Not run, blocked before request/write for lack of canonical ownership plus operation-scoped Undo proof; no CTA substitute used. |

## Rendering, screenshot and design review

Public `window.innerWidth/innerHeight` was `1232×923`; public mobile could not be established because the documented viewport override did not change the actual window. Therefore public mobile is **BLOCKED** for all cases. Elementor mobile preview was inspected separately at `360×736` for C, E, F, G, H and I. Its screenshot files are desktop-app rasters `1232×923` with the editor preview frame showing the 360×736 device; they are not public-mobile screenshots. Pricing's native tablet grid control is one fractional track; desktop has the actual item count (two or three). Public tablet/mobile breakpoints were not measured.

Fresh public screenshots were captured after Publish/reload for B–I, saved as JPEG-returned bytes then converted/validated as PNG, opened and visually inspected. The exact paths, PNG sizes, source type and viewport are in `live-measurements-v270.json`. Visual findings:

- **Pricing two tiers:** two equally sized outlined cards, complete exact prices/features, buttons share the lower edge. No accidental third card.
- **Pricing three tiers:** pill and intro are present; three equal columns and aligned CTAs; longer middle copy expands naturally. The two-tier and three-tier output differ structurally by real native item count.
- **Hero:** H1 and right-side image have clear 60/40 reading order; loaded image crop is balanced. A Vision spacing warning was not supported by measured DOM or pixels.
- **About:** image-left, H2/copy/CTA-right; composition is editorial rather than another Hero with renamed heading. Full copy remained visible.
- **Benefits:** explicit editorial list is preserved, icons and copy read clearly. The sparse two-item list leaves substantial unused space on the right; this is a visual-quality limitation, not a reason to silently turn it into a card grid.
- **Team:** two-column card topology and natural long bios; no fabricated portraits/footer. Site greeting overlaps the bottom edge of the last card (`SITE_OVERLAP`).
- **Testimonials:** six two-part editorial rows with correct author/quote ownership and no added commercial adornments. Site greeting covers part of the last long quote (`SITE_OVERLAP`), so accessibility of that fragment is incomplete.
- **FAQ:** native Accordion, exact answers; each public answer opened using the native control. The long answer is fully visible after animation. The long-answer editor-mobile visual is not verified.

No desktop overflow or clipping was observed in the measured target roots. Mobile geometry is only partly covered by Elementor preview; public mobile, 320px/390px breakpoints and narrow desktop transitions remain unverified. No Vision score substitutes for those missing measurements.

### Gallery

All public screenshots below are post-Publish/reload; public CSS viewport is `1232×923`. Each PNG is `1232×923` except the full Testimonials capture (`1217×1265`). Operation and identity are in the matrix above.

**B — Pricing, two tiers**
![Pricing two-tier public desktop, post 5214, root f19f307, operation wpae-d100b2dc1c76cbd8, revision 5, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/pricing-2-public-desktop.png)
[Download Pricing two-tier PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/pricing-2-public-desktop.png)

**C — Pricing, three tiers**
![Pricing three-tier public desktop, post 5214, root 99ff7f7, operation wpae-6c366b0ac547b7a5, revision 6, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/pricing-3-public-desktop.png)
[Download Pricing three-tier PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/pricing-3-public-desktop.png)

**D — Hero**
![Hero public desktop, post 5214, root cd4d98d, operation wpae-d8ab197c302660bd, revision 5, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/hero-D-public-desktop.png)
[Download Hero PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/hero-D-public-desktop.png)

**E — About**
![About public desktop, post 5214, root 6ee89b6, operation wpae-517673afeecdaed6, revision 6, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/about-E-public-desktop.png)
[Download About PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/about-E-public-desktop.png)

**F — Benefits**
![Benefits editorial list public desktop, post 5214, root 1126b32, operation wpae-d30a078ae23ba42a, revision 5, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/benefits-F-public-desktop.png)
[Download Benefits PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/benefits-F-public-desktop.png)

**G — Team**
![Team grid public desktop, post 5214, root d861368, operation wpae-d35757c357effecc, revision 6, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/team-G-public-desktop.png)
[Download Team PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/team-G-public-desktop.png)

**H — Testimonials, full view**
![Testimonials editorial rows public full, post 5214, root 7bb2750, operation wpae-a7a3bf847b5e4c10, revision 5, CSS viewport 1232x923, PNG 1217x1265; site greeting overlaps last quote](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/testimonials-H-public-full.png)
[Download Testimonials full PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/testimonials-H-public-full.png)

**I — FAQ, long answer opened**
![FAQ native Accordion long answer open public, post 5214, root 1a1d059, operation wpae-c616e5dbb2a2a125, revision 5, CSS viewport 1232x923, PNG 1232x923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-final-public-long-answer-open.png)
[Download FAQ PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-final-public-long-answer-open.png)

Editor preview captures at the 360×736 device setting (not public-mobile evidence): [Pricing C](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/pricing-3-editor-mobile-preview.png), [About E](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/about-E-editor-mobile-preview.png), [Benefits F](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/benefits-F-editor-mobile-preview.png), [Team G](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/team-G-editor-mobile-preview.png), [Testimonials H](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/testimonials-H-editor-mobile-preview.png), [FAQ I](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/faq-I-editor-mobile-preview.png).

## FAQ lifecycle refusal and final target state

FAQ generation had one Brief/Plan/record decision and one insertion write. The native public Q/A strings survived Publish/reload. To inspect the Accordion, an editor-side interaction changed the native `tabs` authored control representation (plain text became paragraph-wrapped serialization), leaving the editor dirty. The safe Undo UI first stopped with `Undo остановлен: несохранённые изменения редактора сохранены локально.` The read-only model check then returned `typed_editor_model_mismatch`, `control=tabs`, `reason=authored_control_changed`. After the normal Publish serialized that editor representation, the operation descriptor reported `changed_target`, reason `owned_fingerprint_changed` at revision 5. The ownership guard was correct to refuse Undo. No manual root deletion, guard bypass or whole-document replacement was attempted.

Fresh editor and public reads both contain only root `[1a1d059]`, saved JSON hash `8e0451b15dc2730dda0a916010ed69a667d93379aeae9ed6835e7d75ada7726f`. The original requested historic root `b48abe1` was absent before these tests and was never imported or recreated; the user-confirmed actual test baseline was empty. Seven successful operation-scoped Undos restored that empty baseline. FAQ is the sole remaining test root.

## Checks and status boundary

The v270 runtime had already passed before this docs/evidence continuation: Design Pipeline Contract `848`; Flex Runtime `1380`; Node suites `18/18`; Elementor patch guard PASS; catalog `158 manifest / 156 retrievable / 156 previews`; PHP lint of 10 changed PHP files PASS; package probe `252` hashes, zero mismatches, four scenarios PASS; `git diff --check` PASS at runtime release. No source changed during these live runs; package hashes and version were not changed. A docs-only diff check is rerun before commit.

| Layer | Current result |
|---|---|
| Source / push / install / editor version | v270; independently confirmed before the live matrix |
| Generations | 9 primary attempts, 0 repeats; 8 distinct families; CTA not run |
| Content/native readback | B–I read back after Publish/reload; Services A was user-deleted before readback |
| Desktop geometry | Measurements and public screenshots for B–I; PASS with noted overlays/spacing limitation |
| Public mobile | BLOCKED; viewport remained 1232px |
| Elementor mobile preview | Captured for C/E/F/G/H/I; distinct from public mobile |
| Guarded Undo/restoration | B–H PASS to `[]`; A user-deleted; I refused safely and remains |
| Final root set | `[1a1d059]` |
| Overall | INCOMPLETE; do not label full acceptance PASS |

## Historical v270 source-only checkpoint (superseded)

The earlier report body below is retained as a timestamped record of the source-only observation before the user clarified deletion of the Services root. Its “0 generations” matrix, empty final root set and “live NOT RUN” conclusions are superseded by the current matrix above.

---

# Lifecycle synchronization and shared card layout — v270 checkpoint

Date: 2026-10-06 (Asia/Almaty)
Target: existing WordPress post `5214`; public URL `https://mazhenov.kz/pricing-contract-live-v123/`
Scope: refresh of owned operation descriptors, common repeated-card geometry, and requested live family matrix A–J.

## Outcome

The common lifecycle and card-layout source changes were implemented, locally checked, released once as v02.11.270, pushed, installed, and independently observed in the PHP plugin and reloaded editor config. The current accepted source path remains BriefIR → DesignPlan → ElementorIR → native compiler → validation → transaction/readback.

Live acceptance did not start. The fresh editor/server and fresh public page now show `rootIds=[]`; the task’s required preserved baseline `b48abe1` is absent. The old Services test operation `efdd267` has an expired contract, and the historical `b48abe1` operation descriptor is missing. No current baseline can be safely restored through those operations. No JSON was imported, no root was manually changed, and no generation/Undo write was issued in this continuation. Thus v270 source, release, installation, local tests and screenshot workflow are confirmed; v270 live generation and visual acceptance remain NOT RUN.

The earlier generic `SCREENSHOT BLOCKED` explanation was incorrect as a capability statement. The documented Browser Use route was exercised successfully: bundled `browser-client.mjs` → `setupBrowserRuntime()` → existing iab tab → `tab.screenshot()` → Node `fs/promises.writeFile()` → JPEG-to-PNG conversion → `file`/`sips` verification → `view_image` inspection. This produced five diagnostic PNGs. None is a v270 generated-block acceptance screenshot.

## Source, commits, push, install

| Layer | Evidence | Status |
|---|---|---|
| Source version | `v02.11.270`, local HEAD `5c35b5cac6bee711231b18b6c6dd2859c2bcb777` | Confirmed |
| Lifecycle commit | `349a618cba41c7614f5064c4198512edb55f0b89` | Committed |
| Scoped repair race guard | `9fd5c8415f612b3a255ff4ffb4bc066462895344` | Committed |
| Layout/compiler release | `5c35b5cac6bee711231b18b6c6dd2859c2bcb777` | Committed |
| Push | `origin/main` independently returned `5c35b5cac6bee711231b18b6c6dd2859c2bcb777` | Confirmed |
| WP Pusher | Updated only WP AI Executor in its existing tab | Confirmed success notice |
| Installed PHP plugin | Plugins page displayed `v02.11.270` | Confirmed |
| Editor JS/config | Reloaded existing post=5214 editor; inline config displayed `v02.11.270` | Confirmed |
| Post writes | None in this v270 continuation | `write_count=0` |

No WordPress settings or other plugin were changed. The existing editor was reloaded only after the Publish control was disabled. Existing authorized tabs were reused; no extra editor tab was created.

## Lifecycle descriptor finding

### Source change

`assets/js/elementor-llm-chat.js` now has one descriptor refresh helper for the existing typed lifecycle controls. The helper calls the read-only operation-description path and accepts refreshed state only when post, operation ID, identity, accepted contract, and the complete owned-root set still match the operation in the UI. It updates the acknowledged revision only after that scope check. Verify, Resync, Undo, and Repair use the helper where the stale descriptor mechanism applies. Scoped Repair also snapshots the full editor model before refresh and compares it after refresh.

Server-side guards in `includes/elementor/accepted-contract.php` and the existing ledger/transaction path still require exact revision, identity/contract, owned roots, fingerprint/lineage and full document model. Dirty-editor and model-race protections remain active. There is no blind write retry.

### Live evidence and limit

Before the v270 reload, the existing v269 read-only “Проверить owned модель перед Save” path returned `Owned fingerprint или lineage изменились.` The fresh v270 bootstrap contains:

- `wpae-e387c462a25a14dc`, identity `5746a737-6c38-44e5-bbea-e323dfb8979a`, root `efdd267`, revision `6`, status `unavailable`, reason `contract_expired`;
- historical operation for root `b48abe1`, revision `6`, status `unavailable`, reason `contract_missing`.

The fresh descriptor is unavailable, so the current editor does not offer a usable read-only Verify control for this operation. The prior response therefore does not isolate a stale-revision-only cause; it specifically reports fingerprint/lineage mismatch. The hypothesis that native Publish only made the UI revision stale remains unproven. A guarded Undo against the expired descriptor would not be safe and was not attempted. No root was deleted manually.

### Regression coverage

Lifecycle regressions cover native Save advancing revision, read-only refresh of the same operation scope, guarded Undo using refreshed revision, identity/contract/root-set mismatch refusal, document/revision race refusal after refresh, preservation of a neighboring baseline root, and the same full-model race protection for scoped Repair. UI suite: 18/18 Node tests pass; PHP typed lifecycle/runtime tests are included in the checks below.

## Shared card and typography policy

No new generator, family catalog, or planning DSL was introduced. The existing accepted Plan, IR and compiler own these decisions:

- `type.section_title` separates ordinary section-intro scale from Hero display scale. Heading semantics remain independent: Hero H1, ordinary section intro H2, item title appropriate H3. Historical Plans keep their prior defaults.
- Repeated groups use native Grid fractional tracks and explicit gap, so tracks consume available container width after gaps. Fixed percentage compensation is not the source of truth.
- Typed card IR separates `card_body` from optional `card_actions`. On desktop, remaining vertical space sits between body and action footer, aligning CTAs at the row bottom while copy intervals stay natural. Mobile cards stack and grow with exact content; no fixed height, clamp, or content hiding was introduced.
- Footer/actions are absent when a Brief has no actions; Team and Testimonials do not acquire invented CTA controls.
- Existing Pricing composition IDs remain compatible. A Plan stores actual `item_count`; native output emits two or three actual tiers and no empty third placeholder. Responsive columns follow Plan.
- Existing token, profile, composition-record and explicit Brief priority remain in the existing resolver/compiler chain.

Local evidence supports these contracts, not browser pixels. No live comparison using identical prompt/profile before and after v270 was possible in the current post state.

## Current target state and prior Services visual evidence

Fresh editor bootstrap for `post=5214`:

- valid Elementor array, `rootIds=[]`;
- saved JSON SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`;
- empty saved HTML SHA-256 `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`;
- `targetOperationsByRoot=[]`, `pendingOperation=null`.

Fresh navigation to the public page also has `rootIds=[]`. Before that navigation, the already-open public tab showed old DOM roots `[b48abe1, efdd267]`. Those visible sections were stale-tab content; they must not be presented as current saved output.

The historical public Services screenshot does show the shared visual issue under investigation: the cards preserve their images, pill, and full descriptions, but their CTA buttons sit at visibly different heights, and short cards leave substantial unused space under the button. The AI-Dana/site chat overlay is page-owned and intersects the third CTA in the viewport image. This screenshot is retained as v269 diagnostic evidence only.

### Stale-tab Services diagnostic

Post 5214, visible stale public roots `[b48abe1, efdd267]`; source public tab before fresh navigation; CSS viewport `1643×1231`; PNG `2163×2615`.

![Previously open public Services tab retained as stale visual evidence](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/services-v269-current-public-full.png)

[Download stale public diagnostic PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/services-v269-current-public-full.png)

### Fresh public baseline

Post 5214; public source after fresh navigation; root set `[]`; CSS viewport `1643×1231`; full-page PNG `2189×1640`.

![Fresh public baseline with no generated roots](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-public-full.png)

[Download fresh public baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-public-full.png)

### Fresh editor baseline

Post 5214; Elementor editor after reload; source inline config v270; root set `[]`; CSS viewport `1643×1231`; PNG `1642×1231`; Publish disabled.

![Fresh Elementor editor baseline on v270](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-editor-v270.png)

[Download fresh editor baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-editor-v270.png)

Additional PNGs preserve the stale editor refusal and the stale public viewport framing. All five current files are valid PNGs with the sizes recorded in `current-state.json`; their JPEG source bytes are preserved beside them. They were opened and visually reviewed. Editor/public screenshots are distinct evidence types.

## Acceptance matrix A–J

The full machine-readable matrix is [`acceptance-matrix-A-J.json`](acceptance-matrix-A-J.json). It distinguishes prior-version evidence from the requested v270 acceptance. **Every v270 generation scenario is NOT RUN.** The historical A Services operation is especially relevant: its old operation contract expired; the historical baseline operation is missing; current saved roots are empty. The historical root list is not authority to recreate any root.

| Scenario | Requested composition | Historical evidence (not v270 acceptance) | v270 status |
|---|---|---|---|
| A | Services `services.photo_cards`, three exact items/assets/CTA | v269 Services recovery: `efdd267`, `wpae-e387c462a25a14dc`, identity `5746a737-6c38-44e5-bbea-e323dfb8979a`, initial revision 4; later descriptor revision 6 expired. Historic output had exact copy/assets, but CTA y positions differed and site overlay covered CTA 3. | `BLOCKED_BASELINE_MISMATCH`; no retry |
| B | Pricing, exact two-tier request I | v269 `8859f30` / `wpae-e67a76fd2b2199d1`; exact two cards rendered, historical guarded Undo passed; legacy record name `pricing.three_cards`. | `NOT_RUN` |
| C | Pricing, exact three-tier M3.1 F | No historical live run found. | `NOT_RUN` |
| D | Hero split, image right, editorial-light | v269 `4c5d508` / `wpae-41612dd2fb2d5967`; historical desktop evidence only, Undo passed. | `NOT_RUN` |
| E | About editorial split, image left | v269 `b4825de` / `wpae-3a0b99add34d4807` used a different right-image record; historical Undo passed. | `NOT_RUN` |
| F | Benefits `editorial_list`, no photos | v269 Benefits grid is not this composition; no matching editorial-list run. | `NOT_RUN` |
| G | Team grid, four people, no fabricated portraits | v266 root `3178f61` / `wpae-df7ea9c2e4a4d350`; historical review required overlay/readability follow-up. | `NOT_RUN` |
| H | Testimonials `editorial_rows`, no ratings/photos | v266 root `bcc1d46` / `wpae-06bb72bdb0e0881b`; historical desktop row result only. | `NOT_RUN` |
| I | Native FAQ Accordion, every item opened | v264 root `59a770e` / `wpae-0198aa58bfe9eaaf`; historical settled mobile evidence and guarded Undo exist. | `NOT_RUN` |
| J | Standalone CTA compatibility smoke, two exact links | No run. The route is legacy `cta.band`; there is no canonical typed CTA record, and safe scoped lifecycle was not proven for it. | `NOT_RUN`; no request/write |

Primary generations in this v270 continuation: `0`. Repeats: `0`. No prompt was changed to hide a defect. No first-result repair was needed because no v270 result exists.

## Local checks and package status

These results were run for the source release before docs-only updates:

| Check | Result |
|---|---|
| `php -d memory_limit=512M tests/design-pipeline-contract.php` | `848 checks OK` |
| `php -d memory_limit=512M tests/flex-generation-runtime.php` | `1380 checks OK` |
| `node --test tests/*.test.js` | 18/18 pass |
| `php tests/elementor-patch-guard.php` | PASS |
| `php tests/imported-template-catalog.php` | 158 manifest / 156 retrievable / 156 previews / 5 FAQ candidates / 2 Services candidates |
| PHP lint of changed PHP files | 10 files; all syntax checks pass |
| `php docs/audits/2026-09-12/package-probe.php` | 252 manifest files; 0 hash mismatches; 4 probe scenarios pass |
| `git diff --check` | PASS on the runtime release before report update; rerun after docs changes |

An early package probe after the follow-up JS edit detected a stale package hash; `wpae-package.json` was updated and the final probe passed. The full diagnostic JSON still has the pre-existing malformed UTF-8 serialization finding; its compact summary succeeds. No test result proves live DOM or visual quality.

## Separate status summary

| Acceptance layer | Status |
|---|---|
| Source | v270 committed |
| Push | origin/main independently confirmed at v270 HEAD |
| Install | WP AI Executor PHP v270 confirmed |
| Editor JavaScript | Inline config v270 after reload |
| Current saved/public root state | Both empty `[]`; required historical baseline absent |
| Lifecycle fix | Source regressions pass; live stale-revision cause not proven because old accepted contract expired |
| Live generation | 0 cases under v270 |
| Native readback | Current empty-state bootstrap confirmed; no new generated native tree to compare |
| Responsive geometry | No v270 generated root; NOT RUN |
| Visual composition | Historical Services defect retained; v270 quality NOT RUN |
| Screenshot workflow | Browser Use bytes saved and PNGs checked/opened; generated-block screenshots NOT RUN |
| Undo/document restoration | No v270 mutation to undo; `b48abe1` restoration unresolved |

Historical and current status differences are retained in `current-state.json` and the exact matrix. No full M3.1, cross-family, or v270 live visual acceptance is claimed.
