# Typed copy contract continuation — v02.11.340

- **Date:** 2026-10-12
- **Target:** existing Elementor page `post=5214`
- **Source/runtime:** `8b3492ac46950440e0709359d05cb90abeca111e`, `v02.11.340`
- **Final page roots:** `[]`
- **Required A–E gate:** **3/5 full live acceptances; task remains open**

## Current state and source/install

The v340 source was already pushed and installed for WP AI Executor only. Plugins and the reloaded Elementor editor had independently shown v02.11.340 in the previous release verification. This continuation changed no runtime source, package file, version, model setting, WordPress setting, or other plugin, so the runtime suites and install were not repeated.

Before stopping, the single existing browser tab was on `post=5214`. The reloaded editor showed an empty canvas, Publish disabled, and no unsaved state; the user has clarified that this is their saved, user-cleared baseline. Public DOM readback at CSS viewport 1232×923 found zero Elementor roots and no horizontal overflow. No page or draft was created. Every written test operation was undone with its guarded operation-scoped Undo. Baseline after each completed write and current editor/public root set are `[]`.

## Failure diagnosis and boundary

The exact A and C failures do not establish a local source defect:

- **A / Hero:** one normal plugin-chat submission made two bounded HTTP calls to requested model `openrouter/free`. Both ended in cURL timeout (`http_status=null`; 30,088 ms and 30,083 ms; 94 and 86 response bytes received). Total latency was 60,171 ms. No model response was available to validate, and there was no operation, root, or write (`write_count=0`). Capability state for the dynamic route was `unknown`.
- **C / Benefits:** one normal plugin-chat submission made two calls; both returned HTTP 200 from `liquid/lfm-2.5-2.6b:free`. Strict `json_schema` and `require_parameters=true` remained set. Calls took 17,244 and 18,589 ms (35,833 ms total); outputs used 2,422 and 3,072 tokens, with 2,157 and 2,703 reasoning tokens. The semantic provenance validator rejected the generated description first for `benefits_1_description`, then for `benefits_2_description`, as `unsupported_generated_claim`. No operation/root was written (`write_count=0`). Rejected copy was not retained, so the evidence proves a fail-closed semantic refusal but cannot distinguish an unsupported model claim from a validator false positive.

The successful B/D requests also used the free dynamic route, resolving to NVIDIA; the corrected E response model was not recorded. This variation and the two failures show that `openrouter/free` does not give a stable model selection. They do not prove a schema incompatibility: C returned a schema-shaped HTTP 200 response, and B/D/E completed typed generation. No exact A/C prompt was repeated, no provider was silently changed, and no weakening of schema/provenance guards was introduced.

Current OpenRouter documentation describes JSON Schema structured output as endpoint-specific and recommends `require_parameters=true`; support can differ across providers for the same model. A candidate for a user-approved pinned route is `openai/gpt-6-luna`, whose current OpenRouter model page advertises JSON Schema output at listed rates of $0.10/M input and $0.50/M output. The exact selected endpoint must still report structured-output capability before a paid generation; the plugin's strict schema and local semantic validator must remain enabled. This candidate was not selected or called. At the observed copy sizes, two remaining scenarios with one bounded retry each would be roughly $0.008 at those listed rates (about 3,000 input and up to 3,200 output tokens per attempt); actual usage and rate can differ. Sources: [OpenRouter structured outputs](https://openrouter.ai/docs/guides/features/structured-outputs), [GPT-6 Luna model and pricing](https://openrouter.ai/openai/gpt-6-luna).

## Acceptance matrix

Exact fixtures and hashes are preserved in [`fixtures/manifest.json`](fixtures/manifest.json). A refusal or wrong selected record is not counted.

| Case | Result | Route / frozen selection | Live result and lifecycle |
|---|---|---|---|
| A — Hero | **BLOCKED, no write** | `fixtures/A-hero-generated.txt`; `openrouter/free`; capability `unknown` | Two transport timeouts, `write_count=0`; no operation/root or screenshot acceptance. See [safe timeout diagnostic](diagnostics/A-hero-v340-provider-timeout.json). |
| B — About | **PASS** | `about.split_50_50.right`, `editorial_light`; Brief `de018ba7051dec5486136bcd406e25f6acbcebc15483d96495bd39b7d762243e`; Plan `0f6fd8af48de970dbab6adf0bb03b389996e14c02390d9eb036f8a53b47222a8` | Root `b6cb47f`; operation `wpae-347f0085318b1140`, identity `7eefb2a2-7326-42eb-a083-3e1055bb685f`; generation revision 4, refreshed revision 5 before Undo. Exact authored H2 `Проект начинается с внимательного разговора`; generated paragraphs `На первой встрече команда обсуждает пожелания и ограничения пространства.` and `Перед разработкой чертежей согласуется планировка.` No CTA. Only requested Unsplash asset, exact alt and center-top focal intent. Saved/reloaded full editor/server comparison and owned-contract check passed; image loaded. Guarded Undo restored `[]`. |
| C — Benefits | **FAIL, no write** | `benefits.grid`; `openrouter/free`, resolved to Liquid `liquid/lfm-2.5-2.6b:free`; capability `unknown` | Two HTTP 200 attempts refused as `unsupported_generated_claim` on two description slots; `write_count=0`; no operation/root or screenshot acceptance. See [semantic refusal diagnostic](diagnostics/C-benefits-v340-semantic-refusal.json). |
| D — Pricing | **PASS** | Legacy identity `pricing.three_cards`, `editorial_light`; Brief `fac41b860b20c4d1c9ce2b17927ed8590fb9bb4a892ecf1b9f49c1f273c7203b`; Plan `219355ebb1bf3b2f0aa6480915955d6a7ee9977b65030b9ea9cd5c16202b6cd2` | Root `6303608`; operation `wpae-d533281aab3b25a8`, identity `e9e108de-f772-4725-89ad-1a073c35ee96`; generation revision 4, refreshed descriptor revision 6. Pill/title/body are `УСЛОВИЯ` / `Небольшая архитектурная студия проектирует жилые и общественные пространства` / `На первой встрече обсуждаются задача, пожелания и ограничения пространства`. Two authored tiers only: `Старт — 50 000 ₸/мес` (Аудит, План; `Выбрать Старт` → `#start`) and `Проект — 150 000 ₸/год` (Дизайн, Разработка; `Выбрать Проект` → `#project`). Independent saved native readback matched the frozen signature. Desktop has two equal 558px cards and 24px gap; mobile stacks two content-sized cards with no horizontal overflow. Guarded Undo restored `[]`. |
| E — CTA | **PASS, readback caveat recorded** | Exact fixture `cta.centered`, `editorial_light`; Brief `b67e87b5d86a8fe69d4b9325a17bf3f49ae84e64bbf66fa7b2976eb5877712b0`; Plan `be6dd1b85ddbeb3accb1c8b604e185133dee513d1dabdc2422579fb7deb6d6c7` | First automatic-selection attempt chose `cta.split_actions` and was not accepted; its scoped Undo restored baseline. One justified corrected submission used the same exact fixture and explicit UI selectors. Root `1a9d5c7`; operation `wpae-3f2e5157313820a2`, identity `a12f93ee-14da-4480-bd59-cece3e556641`; post-Publish revision 5. Generated pill/title/body and both exact buttons/hrefs match the request. After reload, the read-only full editor/server comparison completed with `write_count=0` and owned model matched the accepted contract. The corrected operation's raw JSON dump was not saved; the existing similarly named raw export belongs to the rejected `cta.split_actions` attempt and is explicitly excluded. Guarded Undo restored `[]`. |

### A–E status by acceptance dimension

| Case | Lifecycle | Content / provenance | Media | Responsive geometry | Visual review | Restoration |
|---|---|---|---|---|---|---|
| A | Not reached | Not reached | Not reached | Not reached | Not reached | Not applicable; no write |
| B | PASS | PASS | PASS | PASS | PASS; exact title and badge render; no clipping | PASS, `[]` |
| C | Pre-write refusal | FAIL/UNKNOWN: provenance gate rejected copy; no raw rejected content | Not applicable | Not reached | Not reached | Not applicable; no write |
| D | PASS | PASS | Not applicable | PASS after normal greeting collapse; screenshot raster differs from measured CSS viewport | PASS after collapse; initial open-greeting frame records **SITE_OVERLAP** over second-tier copy | PASS, `[]` |
| E | PASS | PASS | Not requested | PASS, desktop/mobile | PASS; centered pill, H2, copy, distinct primary/secondary actions | PASS, `[]` |

### Public screenshot gallery

All images are public after Publish/reload, saved as PNG, checked by signature and dimensions, and visually opened. CSS viewport comes from DOM measurement; PNG dimensions are separately reported in the manifest.

#### Independent design review

The required `ui-ux-pro-max` workflow was run for an architecture-studio editorial landing page: `--design-system`, followed by UX, style, and typography searches. Its generic newsletter/Bento and broad style recommendations were not treated as the site's design contract; the already accepted `editorial_light` profile and native Elementor Flex composition remain authoritative. Relevant review checks were mobile copy readability, text contrast, image alt text, and separation between adjacent actions. The screenshots and DOM agree: About's pill/H2 and split image-copy balance are clear on desktop and stack copy-first on mobile; Pricing retains a readable intro and two distinct white tiers, then stacks them; the CTA has a centered pill/H2, concise body, and visually distinct primary/secondary buttons with 12px mobile separation. On Pricing, the original open greeting overlaps copy in the second tier; the required collapsed-state captures reveal the full tier and both CTAs, while the remaining avatar sits over unused card area. About and the corrected CTA screenshots show the greeting below authored content. Button boxes in the measured CTA mobile DOM are 39px and 41px high; this is recorded as a small-target advisory against the skill's generic 44pt app guidance, not as a native web acceptance failure. No colors, fonts, profile, or source controls were changed from a search recommendation.

#### B — About

Post 5214, root `b6cb47f`, operation `wpae-347f0085318b1140`, revision 5 before Undo. Desktop CSS viewport 1232×923; PNG 1232×923.

![B About, public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/B-about-v340-public-desktop-viewport.png)

[B About desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/B-about-v340-public-desktop-viewport.png)

Mobile CSS viewport 390×844; PNG 390×844. Copy is first, then loaded media; the site greeting sits below the authored block and does not cover it.

![B About, public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/B-about-v340-public-mobile-390x844-viewport.png)

[B About mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/B-about-v340-public-mobile-390x844-viewport.png)

#### D — Pricing

Post 5214, root `6303608`, operation `wpae-d533281aab3b25a8`, revision 6 after readback. Desktop CSS viewport 1232×923; PNG 1232×923. Two plans, prices and aligned actions are visible.

![D Pricing, public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/D-pricing-v340-public-desktop-viewport.png)

[D Pricing desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/D-pricing-v340-public-desktop-viewport.png)

The original public mobile frame (CSS viewport measured in DOM 390×844; screenshot raster 375×812) has the site's open greeting covering part of the second tier's copy. This is recorded as **SITE_OVERLAP** and is not attributed to the generated block. After collapsing the greeting through its normal UI, the second viewport PNG is 375×812 and the full-page PNG is 375×894; both show the full stacked two-tier layout and both actions. The raster/CSS difference is retained explicitly rather than treating raster dimensions as CSS measurements.

![D Pricing, public mobile with site greeting open — SITE_OVERLAP](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/D-pricing-v340-public-mobile-390x844-viewport.png)

[D Pricing mobile greeting-open PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/D-pricing-v340-public-mobile-390x844-viewport.png)

![D Pricing, public mobile full page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/D-pricing-v340-public-mobile-collapsed-full.png)

[D Pricing mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/D-pricing-v340-public-mobile-collapsed-full.png)

#### E — CTA

The first unchanged-fixture result selected `cta.split_actions` automatically although the request asked for `cta.centered`; this operation was not accepted and was guarded-Undone. These unique PNGs are retained as failure evidence, not counted as an acceptance. Post 5214, root `8963f38`, operation `wpae-0992dc2fe57f0482`, identity `52b8c801-ee92-4afe-8602-4c320bdccb69`, revision 5; desktop CSS viewport/PNG 1232×923 and mobile CSS viewport/PNG 390×844.

![E first result, public desktop — wrong split_actions selection](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-v340-public-desktop-viewport.png)

[E first-result desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-v340-public-desktop-viewport.png)

![E first result, public mobile — wrong split_actions selection](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-v340-public-mobile-390x844-greeting-open.png)

[E first-result mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-v340-public-mobile-390x844-greeting-open.png)

The corrected `cta.centered` result follows below.

Post 5214, root `1a9d5c7`, operation `wpae-3f2e5157313820a2`, revision 5. Public desktop CSS viewport and PNG are 1232×923.

![E centered CTA, public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-centered-v340-public-desktop-viewport.png)

[E centered CTA desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-centered-v340-public-desktop-viewport.png)

Public mobile CSS viewport and PNG are 390×844. Greeting remains open in this capture but does not overlap the block or either CTA.

![E centered CTA, public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-centered-v340-public-mobile-390x844-greeting-open.png)

[E centered CTA mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-contract-v340/screenshots/E-cta-centered-v340-public-mobile-390x844-greeting-open.png)

## Final checkpoint

- Fresh full acceptances on v340: **B, D, E = 3/5**. A and C remain unaccepted; no refusal was counted.
- One corrected E selection was made because the first operation chose a different registered record than the fixture. The fixture text/hash stayed unchanged. The only raw readback file currently in `exports/` for E belongs to the rejected operation and is not evidence for the corrected root; the read-only full comparison for the corrected operation is recorded in `diagnostics/E-cta-centered-v340-readonly-descriptor-before-undo.json`.
- F Team and G Testimonials were not started; the requested order places them after the mandatory A–E gate.
- Final editor, saved page and public root set: `[]`; existing user content was preserved.
- The screenshot manifest now includes the initial D greeting-overlap frame and both unique E first-result failure frames. Nine PNG paths were signature/hash-checked earlier; all current manifest PNG paths and exact fixture hashes were rechecked, and 22 `.jpg`/`.raw` conversion intermediates were removed only where a verified same-stem PNG existed.
- Interim documentation/evidence checkpoint `d7138706940dc8a556a30187de9166459e4fd947` was pushed to `origin/main`; independent `git ls-remote origin refs/heads/main` returned the same SHA. It records the open 3/5 matrix, not task completion.
- Runtime unchanged; no version bump, package hash change, new install or runtime test run in this continuation. Prior v340 checks remain the last source-check evidence. This is an open checkpoint; its documentation/evidence publication status is reported separately from source/install/live status.
