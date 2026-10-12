# Typed-copy and live acceptance checkpoint — v02.11.348

- **Date:** 2026-10-12
- **Page:** existing Elementor page `post=5214`
- **Source/runtime:** `728e27d2ec719f17ff8a21dd9fbbd11630d7fbb`, `v02.11.348`
- **Requested route:** `openrouter/free` (unchanged)
- **Final page roots:** `[]`
- **New exact A–E cases:** **3 accepted of 5** (B, D, E); A and C refused before write. The task remains open.
- **Additional shared-layout cases:** F Team and G Testimonials not started while A–E is incomplete.

## Source, install, and scope

The current source is v348 at the commit above. This continuation made no runtime or package change and did not bump the version. The existing WP AI Executor Plugins row showed `v02.11.348`; after reloading the existing Elementor editor, the inline chat showed `openrouter/free · v02.11.348`. The editor canvas was empty and Publish disabled after the final Undo/reload. Public DOM after Undo contained zero Elementor roots. No page or draft was created, and no other plugin, model setting, account, or WordPress setting was changed.

The v348 source push had previously been reported successful. An independent `git ls-remote origin refs/heads/main` in this continuation failed because `github.com` could not be resolved; remote HEAD is therefore **not independently confirmed by this check**. The runtime version was already installed; this continuation did not run a new WP Pusher installation. Full runtime suites were not rerun because there was no runtime change. The live inline version was rechecked; the Plugins row independently confirmed the installed version. The installed PHP runtime itself was not measured.

The requested provider remained `openrouter/free`. Its selected model is dynamic per call: successful cases returned Liquid, AtlasCloud, and Nvidia endpoints; timeout A disclosed no serving model. The capability policy remained `unknown`. No route switch, raw-tree fallback, JSON mode downgrade, or relaxation of strict schema/provenance checks was used.

## Failure evidence and source boundary

### A — Hero

The unchanged fixture produced two transport timeouts (30,081 ms and 30,078 ms; 60,160 ms total) on `openrouter/free`. Both calls had `http_status=null`; the sanitized transport message was cURL error 28 with partial response bytes. No returned model or upstream provider was exposed. `json_schema`, `strict=true`, and `require_parameters=true` remained active. There was no Plan acceptance, operation, or root; `write_count=0`. This is a transport failure, not evidence of an incompatible schema or local semantic-validator defect. No exact-fixture retry was made without a source or route change.

Safe telemetry: [A diagnostics](diagnostics/A-hero-refusal-v348.json). Fixture: [A exact request](fixtures/A-hero-generated.txt), SHA-256 `387237de2ea9c9e44b7658776a80a576da3d8c378b6e370fd36c77d54ba679df`.

### C — Benefits

One normal plugin-chat submission made two strict typed calls (49,974 ms total), returned serving model `nvidia/nemotron-3-super-120b-a12b:free` from Nvidia, then failed closed as `explicitly_prohibited_claim` / `semantic_provenance_failure`. No operation/root was created and `write_count=0`. Rejected copy was not retained, so the evidence cannot establish whether the model made an unsupported claim or the validator raised a false positive. It is not evidence of a JSON Schema endpoint rejection. No exact-fixture retry or validator relaxation was made without the missing claim evidence.

Safe telemetry: [C diagnostics](diagnostics/C-benefits-refusal-v348.json). Fixture: [C exact request](fixtures/C-benefits-four-grid.txt), SHA-256 `9b0ba44cf186c15b36593c143dbdce2708b22fbb0b9df15f91b4deb2746c5e72`.

These outcomes do not justify a local source fix: A did not receive a usable response, and C's rejected model copy is unavailable for claim-level review. A source change made on this evidence alone would risk weakening the fail-closed fact contract or merely masking a dynamic-router timeout. No runtime source was changed during this continuation.

## Acceptance matrix

Exact fixtures and hashes are preserved in [`fixtures/manifest.json`](fixtures/manifest.json). Per-scenario evidence and statuses are machine-readable in [`acceptance-matrix.json`](acceptance-matrix.json).

| Case | Status | Frozen selection / live result |
|---|---|---|
| A — Hero | **BLOCKED, no write** | `openrouter/free`; two cURL timeouts; no Plan/operation/root; `write_count=0`. |
| B — About | **PASS** | `about.split_50_50.right`, `editorial_light`; root `d99f28c`; operation `wpae-11fe4005ccf31cae`; identity `9836b883-ae21-47c5-b7c8-3e2c592976cd`; generation revision 4, refreshed revision 5 before Undo. One generated slot. The saved frozen/readback signatures match. Exact supplied H2, two fact-grounded paragraphs, the requested photo/alt/focal intent, and no CTA were preserved. Guarded Undo restored `[]`. |
| C — Benefits | **BLOCKED, no write** | `benefits.grid`; two strict calls ended in semantic refusal `explicitly_prohibited_claim`; rejected copy was not retained; no operation/root; `write_count=0`. |
| D — Pricing | **PASS** | `pricing.tiers` (legacy identity `pricing.three_cards`); two actual tiers; root `8748276`; operation `wpae-6e73d2611e5eca2c`; identity `fac3a08c-0c00-4ca6-9d0a-ed26fb53002b`; generation revision 4, refreshed revision 6 before Undo. Frozen/readback signatures match. All authored prices, periods, features, order and CTA hrefs were preserved; no empty third card. Guarded Undo restored `[]`. |
| E — CTA | **PASS; raw JSON export caveat** | Explicit `cta.centered` v1, `editorial_light`; root `2b22d51`; operation `wpae-ac53274ac164d125`; identity `51e27ed2-0423-4575-8c28-b6d20c535e18`; generation revision 4, post-reload descriptor revision 5, Undo revision 6. Post-reload owned-model check and complete read-only editor/server comparison passed with `write_count=0`; saved native fingerprint stayed `a6e2c5bd5790e524a64286a6525c5b6836e2fb694cd54fb458a83dfcb9eab2d7`. The raw native JSON was captured before reload, not exported again after reload; this limitation is recorded in the evidence JSON. Guarded Undo restored `[]`. |

### B — About visual review

The exact fixture is [B](fixtures/B-about-hybrid.txt), SHA-256 `5518b72b6405cc59ae7f97aa6ef7a43bb7d3e10dc303db3153ab14cba91fd06b`. The public desktop DOM measured 1280×900 CSS px with no horizontal overflow. The composition places the two-column copy track and loaded image in a 50/50 split; the supplied heading and pill are aligned on the same left axis, and the 2 paragraphs remain intact. At 390×844 CSS px the copy appears before the image and grows naturally. The site greeting was captured open and then collapsed through its own UI; it did not obscure the text or media. Exact saved-model and lifecycle evidence is in [B summary](diagnostics/B-first-result-summary-v348.json), [post-reload read-only check](native/B-after-reload-readonly-check-v348.json), [measurements](measurements/B-v348-public-desktop.json), and [Undo baseline](native/B-undo-baseline-v348.json).

![B About, public desktop, post Publish/reload; post 5214, root d99f28c, operation wpae-11fe4005ccf31cae, revision 5, CSS viewport 1280×900, PNG 1280×900](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/B-v348-public-desktop-viewport.png)

[Download B desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/B-v348-public-desktop-viewport.png)

![B About, public mobile after native greeting collapse; post 5214, root d99f28c, operation wpae-11fe4005ccf31cae, revision 5, CSS viewport 390×844, PNG 390×844](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/B-v348-public-mobile-greeting-collapsed.png)

[Download B mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/B-v348-public-mobile-greeting-collapsed.png)

### D — Pricing visual review

The exact fixture is [D](fixtures/D-pricing-two-tiers.txt), SHA-256 `6ad53c3ab461f93cfe95c5e36e814e8350838360ba413fe67019a4936319fe2b`. The native/DOM output contains exactly two plans and the full intro. On desktop, the two white cards have matching authored structure and visible actions; no blank third plan is rendered. Public mobile stacks both content-sized plans. The measured `window.innerWidth` was 390 CSS px; the capture raster was 375×812 (full page 375×892), recorded separately in the measurement and screenshot manifest. The original greeting-open and native-collapse frames are retained. All exact text and links were confirmed; Undo restored `[]`.

![D Pricing, public desktop, post 5214, root 8748276, operation wpae-6e73d2611e5eca2c, revision 6, CSS viewport 1280×900, PNG 1280×900](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/D-v348-public-desktop-viewport.png)

[Download D desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/D-v348-public-desktop-viewport.png)

![D Pricing, public mobile after native greeting collapse, post 5214, root 8748276, operation wpae-6e73d2611e5eca2c, revision 6, CSS viewport 390×844, PNG 375×812](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/D-v348-public-mobile-chat-collapsed-390x844.png)

[Download D mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/D-v348-public-mobile-chat-collapsed-390x844.png)

### E — Centered CTA visual review

The unchanged exact fixture is [E](fixtures/E-cta-centered-generated.txt), SHA-256 `79ffb9ee6827dddae431838454e38e62721d6c02dbd00368c54c1210801d515a`. Plan freeze honored explicit `cta.centered` with `editorial_light`; the accepted record hash and Brief/Plan hashes are in [post-reload read-only evidence](native/E-after-reload-readonly-check-v348.json). Generated copy is “Обсуждение пожеланий и ограничений пространства” and “Согласование планировки перед созданием чертежей”; these remain grounded in the approved meeting/planning facts. The pill is exactly `ПЕРВЫЙ ШАГ`; actions are exactly `Обсудить задачу` → `#contact` and `Посмотреть подход` → `#about`. No image was requested or rendered.

At public desktop CSS viewport 1280×900, root `2b22d51` starts at y=32 and is 1280×372.63 px. The H2 is 40/46 px in a 672 px text measure; body is 17/28.05 px in a 441 px measure; the two centered buttons are 167.5×39 and 190.3×41 px, with 1 px vertical offset. At mobile CSS viewport 390×844, root height is 378.39 px; H2 is 28/32.2 px, body 16/26.4 px, and actions stack centered with both links visible. Client and scroll widths match at both sizes; overflow is visible without clipping. The pale-green 20%-opacity accent surface and dark type remain legible. The initial site greeting was captured open; a second screenshot after its normal collapse shows the full CTA unobstructed. The layout is balanced and content-driven; no fixed-height or hidden text is present.

![E centered CTA, public desktop after Publish/reload, post 5214, root 2b22d51, operation wpae-ac53274ac164d125, revision 5, CSS viewport 1280×900, PNG 1280×900](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/E-v348-public-desktop-viewport.png)

[Download E desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/E-v348-public-desktop-viewport.png)

![E centered CTA, public mobile after native greeting collapse, post 5214, root 2b22d51, operation wpae-ac53274ac164d125, revision 5, CSS viewport 390×844, PNG 390×844](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/E-v348-public-mobile-viewport-chat-collapsed.png)

[Download E mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/E-v348-public-mobile-viewport-chat-collapsed.png)

![Post-Undo public baseline, post 5214, no root, operation wpae-ac53274ac164d125, revision 6, CSS viewport 1280×900, PNG 1280×900](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/E-undo-public-baseline-v348.png)

[Download post-Undo baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-12-copy-grounded-v348/screenshots/E-undo-public-baseline-v348.png)

The generated-design tree `native/E-first-result-generated-design-v348.json` is the pre-reload export. After reload, the plugin's read-only owned-model check and full editor/server comparison both passed, and the server native fingerprint remained unchanged. A second raw JSON export after reload was not saved; see `native/E-after-reload-readonly-check-v348.json`. This is an evidence caveat, not a failed readback or a reason to repeat the generation.

## Restoration and remaining criteria

Each written operation B/D/E was undone through the operation-scoped guard. For E the post-Undo descriptor returned `already_undone`, revision 6; the editor reloaded to an empty canvas with Publish disabled, and public `post=5214` readback contained no Elementor roots. Current editor/public baseline is `[]`. The post-Undo public DOM and inspected PNG are preserved in `native/E-undo-public-baseline-v348.json` and the screenshot manifest.

The exact A–E gate remains open at 3/5. No additional family was used to replace A or C. F Team and G Testimonials remain unstarted because the ordered A–E gate is incomplete. Services and Process remain excluded. There were five primary fixture submissions, three successful transaction writes, two pre-write refusals, no source-fix retry and no repair write. The task is not complete.

## Evidence integrity follow-up

After publishing this report, a JSON parse audit found six evidence files with a literal `\n` suffix after the closing object. The suffixes were removed without changing evidence fields; every JSON file in this audit directory now parses successfully. The correction is recorded in commit `2cddc0156cfb29420e6ec6528d773a867e47fcb4`.
