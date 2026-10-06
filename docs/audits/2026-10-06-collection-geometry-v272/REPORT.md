# Collection geometry live reacceptance — v273

Date: 2026-10-06. This report records the four v273 live retests for the common collection geometry change. The folder name retains the earlier v272 audit namespace; all files with the v273 suffix below are the later v273 evidence. Existing v272/v271 artifacts remain untouched. Machine-readable case table: [acceptance-matrix-v273.json](acceptance-matrix-v273.json).

## Scope and release state

Source HEAD at test time: 92e1c769f7967cbb78aa806afb9fa1b33b7f8d0a, plugin source v02.11.273. The runtime change was committed and pushed before these tests. WP Pusher reported a successful update of WP AI Executor only. WordPress Plugins PHP version and the existing reloaded Elementor editor inline version both independently showed v02.11.273.

The source change in includes/elementor/elementor-ir.php prevents the generic card_body gap fallback from replacing accepted responsive entity_layout.tracks. includes/elementor/layout-report.php reports accepted list icon/copy tracks as static plan estimates, without claiming browser-rendered geometry. The same source release had local Design Pipeline 881 checks, Flex Runtime 1414 checks, Node 18/18, patch guard PASS, changed PHP lint PASS, catalog checks 158/156/156, package probe 253 files and 0 hash mismatches, and git diff --check PASS. No runtime changed during the live continuation.

The page was the existing post 5214. Before the first scenario the editor and public page both had roots []. Each scenario was published and independently read back, then removed only by its operation-scoped guarded Undo. After every Undo the page returned to roots []; final editor and public root sets are []. Publish was disabled at the final editor check. Four primary generations ran, one per listed composition; there were zero retries and zero repairs. No other family was generated.

Public browser CSS viewport was window.innerWidth 1232 × innerHeight 923. On pages with a vertical scrollbar, document.clientWidth/scrollWidth was 1217; the Benefits page geometry snapshot reported clientWidth/scrollWidth 1232. Public screenshot PNGs are therefore 1217 or 1232 pixels wide depending on the capture surface; PNG pixels are not reported as CSS viewport.

## Trace retention boundary

All four requests were submitted once through the normal Elementor plugin chat on post 5214. The operation/root IDs, post-Publish descriptors, owned-model checks, native exports, public DOM, screenshots and scoped Undo outcomes were retained below.

The v273 plugin-chat surface did not leave a durable copy of each accepted Brief/Plan trace after editor reload. For those cases, Brief hash, Plan hash, provider-call count, operation identity UUID and accepted-contract ID are marked unavailable rather than inferred. The unversioned diagnostics files in this directory have earlier timestamps and different composition identities (including team.editorial_list, testimonials.editorial_list, and testimonials.three_cards); they are earlier-run evidence and are excluded from v273 claims. The request fixture paths and hashes below establish the unchanged exact prompts.

## Acceptance matrix

| Scenario | Exact fixture and selected composition | Root / operation / descriptor | Native readback and public geometry | Status |
|---|---|---|---|---|
| Benefits | [F-G-H-exact-request.txt](../2026-10-05-visual-policy/F-G-H-exact-request.txt), SHA-256 76c4e7077891d99eb2346d39dd5a298e1d77e28513794ab68d67a3933c709405. UI selection: benefits.editorial_list, editorial_light. Two exact title/body pairs; no photos or actions. | post 5214; root 8738daf; operation wpae-0e3ca208e817d55b; after Publish descriptor revision 5; before/after Undo revisions 5/6, final status already_undone. Identity and accepted contract IDs were not durably retained. | Reloaded independent export [benefits-F-native-after-reload-v273.json](benefits-F-native-after-reload-v273.json) has 19 native nodes: 11 containers, 4 headings, 2 icons, 2 text editors. Public root 1217×515.7 at x0/y32; H2 is 40px/46px; list x46, width 864, two rows each 864×109 at y229.6 and y358.6, 20px row gap, icon/copy gap 16px. Full copy is present; no photo, clipping, or horizontal overflow. | Lifecycle/content/desktop geometry PASS. Overall visual PARTIAL: the short two-row list leaves a broad unanchored area at right and a large blank region below. Editor mobile preview measured 360×736; public mobile BLOCKED because the documented public viewport override had already failed and was not retried. |
| Team | [A-B-team-exact-request.txt](../2026-10-05-m3-1-entities/A-B-team-exact-request.txt), SHA-256 13ed2c29b3c7e83b18a90413fa418d1dc0fc489ba491fc8c10c85fba2f82239b. UI composition: team.editorial_rows. Four synthetic member records, exact name/position/bio ownership, no portraits and no actions. The v273 profile and accepted Plan hash were not retained. | post 5214; root 714a368; operation wpae-73f20e7c0fac6e87; fresh post-Publish descriptor revision 5 and owned-model check passed; scoped Undo restored []. Identity/contract IDs and post-Undo revision were not retained in the audit file. | Reloaded independent export [team-F-native-after-reload-v273.json](team-F-native-after-reload-v273.json) has 40 native nodes: 25 containers, 6 headings, 9 text editors. Public collection x38.5/y281.2, width 864, column direction, 20px row gap; each row keeps its two identity/copy regions with a 32px gap. Long and short bios remain complete. Editor mobile preview width 360 CSS px; cards grow naturally without clipping or horizontal overflow. | Lifecycle/content/desktop geometry PASS. Visual PARTIAL because the site-owned avatar touches the far edge of the last row and public mobile was unavailable; no authored text was covered. |
| Testimonials editorial rows | [C-D-testimonials-exact-request.txt](../2026-10-05-m3-1-entities/C-D-testimonials-exact-request.txt), SHA-256 9e754a6801ddab082faa49082bab44cb93919232c980d12e221f01c96987040d. UI composition: testimonials.editorial_rows. Same unchanged six-quote fixture; exact quote/author/meta pairs, no photos, ratings, or actions. Profile and accepted Plan hash were not retained. | post 5214; root 9d41294; operation wpae-5ccd1164cd1af497; refreshed pre-Undo descriptor revision 6 was available and owned-model check passed; scoped Undo restored []. Identity/contract IDs and final descriptor revision were not retained. | Reloaded independent export [testimonials-H-native-after-reload-v273.json](testimonials-H-native-after-reload-v273.json) has 56 native nodes: 35 containers, 8 headings, 13 text editors. Public collection width 864 with 20px row gap; six authored quote/author/meta groups remain attached and complete, including long quotes. Editor mobile preview CSS viewport 360×736 stacks the rows in one column without horizontal overflow. | Lifecycle/content/desktop geometry PASS. Visual PARTIAL because only editor mobile evidence is available; the site launcher/avatar occupies blank edge space and does not cover quote text. Public mobile BLOCKED. |
| Testimonials grid | [C-D-testimonials-exact-request.txt](../2026-10-05-m3-1-entities/C-D-testimonials-exact-request.txt), SHA-256 9e754a6801ddab082faa49082bab44cb93919232c980d12e221f01c96987040d. UI composition: testimonials.grid; profile default. Same six exact quote/author/meta pairs, no media, ratings, links, or actions. | post 5214; root fc73813; operation wpae-5be2e92def4ff778; before Undo descriptor revision 5; post-Undo descriptor revision 6, already_undone. Identity and accepted contract IDs were not durably retained. | Reloaded independent export [testimonials-grid-native-after-reload-v273.json](testimonials-grid-native-after-reload-v273.json) has 38 native nodes: 17 containers, 8 headings, 13 text editors. Public root is 1217×1045.4. Collection x38.5/y281.2, 1140×724.2; two measured 558px columns with 24px gaps. Short cards are 148.6px high and long cards 225.4px, with natural content and no clipping. Public document has no horizontal overflow. | Lifecycle/content/desktop geometry and structural variation PASS. Visual PARTIAL: the site avatar/launcher reaches an empty lower-right area of the final card but not its text; public mobile BLOCKED. The blue badge is a strong accent on beige; pixel review did not confirm the advisory Vision claim of a conflicting color, so no repair was made. |

## Geometry and first-result review

The key v273 correction is visible in the two editorial-row native exports and the public geometry: accepted identity/copy tracks and their responsive gaps are no longer replaced by a generic card-body gap. Benefits remains a vertical native list, not a grid. Its 864px width is consistent with the accepted collection measure, but the screenshot shows that two short entries do not visually fill the available section width/height. That is a remaining composition issue, not a content or overflow defect.

For Testimonials grid, the prior v272 measurement separated 126.8px between the 225.4px row-aligned short card and its 98.6px body; 50px was required padding/border and approximately 76.8px was cross-axis stretch surplus. In v273 the short native card is itself 148.6px and the long card 225.4px. This removes the excess stretch inside the short card. Ordinary CSS grid still places both items in a row-height track governed by the taller item; the report does not promise masonry or a shorter whole section.

The four first results were reviewed and none was repaired. The after-Publish screenshots therefore show the same first-generated roots without intervening content or control edits. Separate pre-Publish editor PNGs are retained for Benefits and Testimonials grid. No separately saved first-result PNG for Team or Testimonials editorial rows is claimed; older files with a first-result name predate the v273 source commit and are excluded. For the grid case, the first attempted native clipboard export was stale; it was discarded and overwritten only after selecting root fc73813 in the editor Structure panel and verifying the clipboard root ID, class, six authors, and copy confirmation. The linked v273 native export is the corrected fresh export, not the stale attempt.

Vision remained advisory. Benefits' sparseness finding is corroborated by the public pixels. The earlier Team claim that a biography was missing is contradicted by the exact native export and public text. The Testimonials grid badge-color comment is not corroborated as a conflict by public pixels. No Vision score overrides the evidence or changes the final partial statuses.

## Screenshots and visual inspection

All captions identify post, root, operation, post-Publish revision, source, CSS viewport and PNG dimensions. Operation identity UUIDs were not retained in the v273 audit evidence. The two pre-Publish editor captures below are supplemental first-result evidence; their PNG dimensions are known, but an independent CSS viewport measurement was not saved for those editor screenshots.

**Benefits first editor result** — post 5214, root 8738daf, operation wpae-0e3ca208e817d55b, editor source, pre-Publish; PNG 1232×923, CSS viewport not independently recorded.

[Open the Benefits first-result editor PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/benefits-editor-first-v273.png)

**Testimonials grid first editor result** — post 5214, root fc73813, operation wpae-5be2e92def4ff778, editor source, pre-Publish; PNG 1232×923, CSS viewport not independently recorded.

[Open the Testimonials grid first-result editor PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-grid-editor-first-v273.png)

All listed PNGs were captured after Publish and reload from the existing public page, or from the separately labeled Elementor mobile preview. Returned Browser Use bytes were retained as JPEG source files beside the converted PNGs; each PNG signature and dimensions were checked, and each listed PNG was opened and visually inspected. Public mobile means are not inferred from the editor preview. Public mobile is BLOCKED for all four cases because the previously tested public viewport control did not set a mobile CSS viewport; the control was not retried.

### Benefits — post 5214, root 8738daf, operation wpae-0e3ca208e817d55b, revision 5, public, CSS viewport 1232×923, PNG 1232×923; operation identity was not retained

![Benefits public desktop after Publish and reload: post 5214, root 8738daf, operation wpae-0e3ca208e817d55b, revision 5, CSS viewport 1232×923, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/benefits-public-desktop-v273.png)

[Download Benefits public desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/benefits-public-desktop-v273.png) · [full-page PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/benefits-public-full-v273.png)

### Team editorial rows — post 5214, root 714a368, operation wpae-73f20e7c0fac6e87, revision 5, public, CSS viewport 1232×923, PNG 1217×912; operation identity was not retained

![Team editorial rows public desktop after Publish and reload: post 5214, root 714a368, operation wpae-73f20e7c0fac6e87, revision 5, CSS viewport 1232×923, PNG 1217×912](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/team-editorial-rows-public-desktop-v273.png)

[Download Team public desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/team-editorial-rows-public-desktop-v273.png) · [full-page PNG 1217×950](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/team-editorial-rows-public-full-v273.png)

### Testimonials editorial rows — post 5214, root 9d41294, operation wpae-5ccd1164cd1af497, revision 6, public, CSS viewport 1232×923, PNG 1217×912; operation identity was not retained

![Testimonials editorial rows public desktop after Publish and reload: post 5214, root 9d41294, operation wpae-5ccd1164cd1af497, revision 6, CSS viewport 1232×923, PNG 1217×912](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-editorial-rows-public-desktop-v273.png)

[Download Testimonials editorial rows public PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-editorial-rows-public-desktop-v273.png) · [full-page PNG 1217×1166](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-editorial-rows-public-full-v273.png)

### Testimonials grid — post 5214, root fc73813, operation wpae-5be2e92def4ff778, revision 5, public, CSS viewport 1232×923, PNG 1217×912; operation identity was not retained

![Testimonials grid public desktop after Publish and reload: post 5214, root fc73813, operation wpae-5be2e92def4ff778, revision 5, CSS viewport 1232×923, PNG 1217×912](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-grid-public-desktop-v273.png)

[Download Testimonials grid public desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-grid-public-desktop-v273.png) · [full-page PNG 1217×1077](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-grid-public-full-v273.png)

### Editor mobile preview — not public mobile

Each capture is from the Elementor editor mobile preview at nested CSS viewport 360×736; the saved outer app PNG is 1232×923. These images show the editor preview only. The operation ID and post-Publish revision are the same as in that scenario's desktop caption; operation identity was not retained.

![Benefits editor mobile preview, post 5214 root 8738daf operation wpae-0e3ca208e817d55b revision 5, CSS viewport 360×736, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/benefits-editor-mobile-preview-clear-v273.png)

[Download Benefits editor mobile preview](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/benefits-editor-mobile-preview-clear-v273.png)

![Team editorial rows editor mobile preview, post 5214 root 714a368 operation wpae-73f20e7c0fac6e87 revision 5, CSS viewport 360×736, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/team-editorial-rows-editor-mobile-clear-v273.png)

[Download Team editor mobile preview](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/team-editorial-rows-editor-mobile-clear-v273.png)

![Testimonials editorial rows editor mobile preview, post 5214 root 9d41294 operation wpae-5ccd1164cd1af497 revision 6, CSS viewport 360×736, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-editorial-rows-editor-mobile-clear-v273.png)

[Download Testimonials editor mobile preview](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-editorial-rows-editor-mobile-clear-v273.png)

![Testimonials grid editor mobile preview, post 5214 root fc73813 operation wpae-5be2e92def4ff778 revision 5, CSS viewport 360×736, PNG 1232×923](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-grid-editor-mobile-preview-v273.png)

[Download Testimonials grid editor mobile preview](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-collection-geometry-v272/screenshots/testimonials-grid-editor-mobile-preview-v273.png)

## Final statuses and limits

- Technical lifecycle: PASS for the four operation-scoped create → publish → reload/readback → owned-model check → guarded Undo flows.
- Content fidelity: PASS for the exact fixture text and entity ownership in all four independent native exports and public DOM checks.
- Responsive geometry: PARTIAL. Editor mobile previews are evidenced at 360×736; public mobile is BLOCKED. Desktop measured layout and overflow are documented above.
- Visual composition: PARTIAL. Benefits remains sparse with two short list entries. The other three have coherent desktop topology and full text, but this is not a public-mobile visual PASS; site-owned avatar/launcher edge overlap is recorded separately.
- Structural variation: PASS for the distinct vertical Benefits list, Team editorial rows, Testimonials editorial rows, and Testimonials two-column grid native topology.
- Document restoration: PASS. Final editor/public root set on post 5214 is [].

No historical root was restored, imported, manually deleted, repaired, or replaced. Existing v272/v271 screenshots and audit files were not removed.
