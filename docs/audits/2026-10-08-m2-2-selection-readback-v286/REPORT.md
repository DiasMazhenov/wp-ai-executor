# M2.2 composition readback and live acceptance — 2026-10-08

## Scope update — 2026-10-08

The user has explicitly excluded **Services** and **Process** from any further testing. The D and E results below remain historical evidence; they are not authorization to generate those families again. This update adds no live generations and does not change their recorded statuses: corrected Services is historically accepted with the earlier v289 pill failure retained, while Process remains mobile visual/accessibility PARTIAL because of `SITE_OVERLAP`.

## Decision and count

Five target slots A–E were exercised through the ordinary plugin chat on existing post 5214. There were six generation writes total: A, B, C, E, the initial Services D on v289, and its one allowed rerun after the v290 source fix. D-v289 was a real first-render failure because the accepted pill eyebrow was missing. D-v290 passed the corresponding correction check. No cosmetic repair or manual Elementor control edits were made.

Strict full-acceptance count is **4/5**. A–D passed the generation, content, native, geometry, public desktop/mobile and restoration gates. E passed generation, content, native structure, geometry, and desktop, but public mobile remains **PARTIAL / SITE_OVERLAP**: after the greeting was collapsed through the widget UI, the remaining site-owned chat launcher still covered part of step 3's paragraph in the 390×844 public capture. The block itself has no clipping or overflow; exact text remains in DOM and native JSON. That external visual obstruction means E does not count as a full acceptance under the requested rule. The five-generation completion gate is not closed.

## Source, install, and historical selection finding

- Source/runtime: v02.11.290, commit 7a5c3947bd94df6be334a3fe932900eba70897fb.
- Plugins PHP version and reloaded editor inline version were independently observed as v02.11.290. Only WP AI Executor was updated in the install. The current Site Health PHP engine version was not rechecked in this acceptance run.
- v285's stored Team fixture did not encode a selected record/profile, and its retained evidence reports null values. The v285 audit has no selector-before-submit or accepted-Plan evidence proving a selection was made and then lost. Historical selection is therefore **UNKNOWN**; missing evidence is not proof of lost selection.
- The confirmed gap was durable, readable evidence of the exact request/accepted choice after reload. The existing accepted-contract/lifecycle response now exposes versioned wpae-composition-evidence-v1 with request selection, accepted record/version/hash/profile, decision source/reasons, Brief/Plan hashes, surface provenance, provider and transaction write counts, operation identity/post/root/revision, accepted-contract hash, and saved native fingerprint. UI lifecycle rendering exposes that evidence. The ordinary create path kept one Brief, one pre-freeze composition decision, one accepted Plan and one transaction.
- The existing UI selection snapshot intentionally excludes selected-element and repair requests. Delivery snapshots are used for operation retry; the fresh create path builds a new request context. Local UI/replay/readback tests passed. A/B/C/D/E read-only descriptors after reload all reproduce the exact accepted choice and matching operation identity, roots and hashes. This is evidence that the current path preserves selection, not retroactive evidence about v285.
- The D-v289 first result is retained separately. The accepted Plan said pill, while the first native/public result omitted the pill. v02.11.290's typed-intake fix preserves the eyebrow text; the same exact request was run once again and the pill was present. D-v289 root 6dfd971 / operation wpae-03dd3c9f473cbbf0 was guarded-Undone before the v290 run. D-v290 was then independently read back and guarded-Undone.
- Decorative background behavior was tested across A–E before adding this rule to the shared context/architecture docs: all five accepted Plans froze the resolved system Accent #61ce70 as #61ce7033, computed rgba(97, 206, 112, 0.2), with white opaque underlay. This confirms a decorative tint only; the system Accent is not confirmed as the site's brand color. Explicit Brief → record surface → selected profile → documented Accent tint precedence remains intact.

## Acceptance matrix

| Case | Requested → accepted selection | Operation / root / post-reload revision | Content, native, and geometry | Desktop / public mobile / visual | Undo |
|---|---|---|---|---|---|
| A Testimonials | Automatic → testimonials.editorial_rows v1, content_ranked_catalog; editorial_light; reason long_or_uneven_quotes_favor_editorial_rows | wpae-13fec7681cbd488f / bf61768 / 5214 / r5 | Six exact synthetic quote-author-company triples; no photo/rating; native Flex editorial rows; exact text and ownership; no overflow | Desktop 1232×923 CSS / 1217×912 PNG. Mobile 390×844 CSS / 375×812 PNG; greeting collapsed and no authored text obscured. Visual PASS | Guarded Undo; editor/public roots [] |
| B Team grid | Explicit team.grid v1 + editorial_light → honored_explicit_record | wpae-4165df7e7eb25d93 / c0bfd54 / 5214 / r6 | Same four-person Brief as C; no photos; native 2×2 Flex-wrap grid; natural short/long copy height; no clipping | Desktop 1232×923 / 1232×923 PNG. Mobile 390×844 / 375×812 PNG, one-column stack. Visual PASS after native greeting collapse | Guarded Undo; editor/public roots [] |
| C Team editorial rows | Explicit team.editorial_rows v1 + same editorial_light → honored_explicit_record | wpae-4fc2059529e2c7ff / e98cca1 / 5214 / r5 | Byte-identical Brief to B; native horizontal editorial rows; topology differs from B without relying on IDs, color, or copy; exact member-field ownership | Desktop 1232×923 / 1232×923 PNG. Mobile 390×844 / 375×812 PNG, rows stack naturally. Vision's negative advisory contradicted by checked PNG/pixels; visual PASS after greeting collapse | Guarded Undo; editor/public roots [] |
| D Services | Automatic → services.icon_cards v1, content_ranked_catalog; editorial_light; reason balanced_copy_favors_icon_cards | v289 failure: wpae-03dd3c9f473cbbf0 / 6dfd971 / r6; v290 corrected run: wpae-550f6a8b5bde630d / 21cbb2d / r6 | Three exact services; exact CTA labels and #strategy, #projects, #support; no photos; one-row native icon-card layout; equal desktop card height and aligned CTA; natural mobile heights. v289 pill missing despite accepted Plan; v290 pill present after source fix | Desktop 1232×923 / 1232×923 PNG. Mobile 390×844 / 375×812 PNG, one-column Flex stack. Visual PASS | Each operation separately guarded-Undone; baseline [] before/after rerun |
| E Process | Explicit process.ordered_steps v1; no profile requested → editorial_light accepted by record/default; honored_explicit_record | wpae-a73cf12d77b424d5 / b553009 / 5214 / r6 | Four exact ordered steps; native Flex timeline desktop / column mobile; no photo/button; exact native text; no clipping or horizontal overflow | Desktop 1232×923 / 1232×923 PNG; visual PASS. Mobile 390×844 / 375×812 PNG; topology/readability partly obstructed by site-owned launcher after greeting collapse: SITE_OVERLAP, visual/accessibility PARTIAL | Guarded Undo; editor/public roots [] |

For every generation, the descriptor's write_count=0 describes that read-only request only. It is not the generation's write count. Each successful transaction is separately recorded with provider_calls=0 and transaction_write_count=1. No scenario was inferred from a root name or screenshot appearance.

### A — Testimonials

Exact request: [A-testimonials-automatic.txt](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/fixtures/A-testimonials-automatic.txt). The six synthetic quote groups are intentionally uneven; every quote is paired with its exact synthetic author/company, and the accepted output has no image, rating, or action. The accepted record/profile persisted after reload and the independent native export matched the six content groups. Desktop and mobile full-page evidence is retained in the screenshots directory. Vision's score of 70/95 raised a contrast/layout warning, but the saved PNG and public pixels showed readable white rows and no clipping; no patch was warranted.

- Brief hash: 2a1ed56b7506ca085090d1d36df2ef580543c4d4ad09456052beaf35718d436f
- Plan hash: d0bdccb17ccfc726a2e82b31f9f9cf0812864227cd563677e9b6c46668841d0c
- Accepted record hash: 73e5a072aca859cd78e8e581d08c16f054b57be23e3fda8c26f3ba636f2de56a
- Contract: contract-ef7edbb7922eec266b6be2d4; SHA  ef7edbb7922eec266b6be2d4ba14d8c38e6957308eb885d915b8201dfe28b294
- Native fingerprint: cacaf5c2b727ff57157795dd83c6a2c753dc02a8c0e070c656a2b18b8efda098

### B/C — Team topology comparison

Exact shared request: [B-C-team-shared-brief.txt](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/fixtures/B-C-team-shared-brief.txt), SHA 13ed2c29b3c7e83b18a90413fa418d1dc0fc489ba491fc8c10c85fba2f82239b. Both operations selected editorial_light explicitly. B's native repeated-group controls form a 2×2 Flex-wrap grid; C's form four horizontal editorial rows with member identity/role separated from biography. Thus the same Brief and visual profile produce a real topology change based on the explicit record. Both preserve the same four exact synthetic people and long biographies, without invented portraits or footers.

- B Brief/Plan hashes: 89dd72a4327ed6ef3e625aad6364b1eda9c6d4c2b3fdcedd4a36bb9fc05c9907 / fa0d0917e9c3a9c45badf2b16ca30c52b549eb670792c84475b73de2784e0a98.
- B accepted record hash / native fingerprint: cd48749f8ef014ab115f0c8341ce7ef82e88af013ea34c2477f703556e54a535 / bfe0802c04f52477753e9aa62675f224264d56465e67d3a6f249604823571108.
- C Brief is byte-identical to B; Plan hash 9544246f16e9ea16f3b11d3b6892c6f1333cd3235f84e12df75f34f22b166447.
- C accepted record hash / native fingerprint: ffaea52b36377285954bf3a4766500d300ae7ef9bbbaace7d4d18fcae8450da8 / 43cc91168a716c414059e0ba1aa1eded3e7f17e0453db370f197f74354167c11.

### D — Services first failure and source-fixed result

Exact request: [D-services-automatic.txt](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/fixtures/D-services-automatic.txt). The v289 first render is retained as a failure: operation wpae-03dd3c9f473cbbf0, root 6dfd971, post-reload revision 6, accepted services.icon_cards/editorial_light, Brief hash 78b7ce951fc91164fe728d4dbc366be9659df3dd3da224eec89fa128d854d2ca, Plan hash ee54f8ee2baf53ebeedff43fecb757c2b206375580aa642bc95b8068bf958734. Exact title/body, three items, and CTA hrefs survived, but the explicit pill eyebrow was absent from the native export and first screenshot. That operation was guarded-Undone.

Source commit 7a5c394 fixed preservation of the pill eyebrow in typed intake and added a regression. The same fixture was submitted once on v290, with no request/profile change or post-generation repair. The new first result contained the pill, exact H2 intro, three H3 service titles, descriptions, and three exact links. The three desktop cards measured equal at 256.09px, with their buttons aligned at y=476.54px. On 390px public mobile, the three cards stack naturally at 211.9/238.3/211.9px with 14px gaps and no overflow/clipping. The site's greeting initially covered the second CTA; after collapsing the greeting using the chat's own UI, only the launcher remained in blank area and no service text was hidden.

- Corrected Brief/Plan hashes: 4c7e704150de5253a4ef9eea4bd3159e32d18f6e0b663bfbffefb2c952ed614b / 2ece0327a71da97bfa327dc7880cf0ee10fb78a38a21e30423e8e3e368a5b0a6.
- Accepted record hash: abe677b31da25949378f4bcec02308152078e1e0ca78b6cbc809b9b1a028efc4. The record is services.icon_cards v1, source content_ranked_catalog, editorial_light, reason balanced_copy_favors_icon_cards.
- Contract SHA: 4187ffe1716cd0bf795c45abaeca8a0c362c497ced8b94d32d20bc3d52a2d248.
- Native fingerprint: cdbcc4e8191157fc07854e9a2f461d60253e6f163b46fdb7668fd0dd2b6b02aa.

### E — Process

Exact request: [E-process-ordered-steps.txt](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/fixtures/E-process-ordered-steps.txt). The UI explicitly selected process.ordered_steps v1; no profile was requested, and the accepted profile is editorial_light. Native readback after reload contains 17 containers, 10 headings, three dividers and four text widgets, no images or buttons. It preserves all four labels/bodies in order. Desktop uses a horizontal Flex timeline; mobile uses a natural-height one-column stack. Desktop layout is balanced and legible. Mobile root width is 375px at actual CSS 390px; root natural height is 1046.97px and there is no overflow/clipping.

The site's greeting was collapsed through its normal control. The launcher still overlays the end of the step 3 paragraph in the mobile capture, so this scenario is recorded as SITE_OVERLAP and mobile visual/accessibility PARTIAL. The plugin/widget was not modified. The operation was guarded-Undone after fresh descriptor and owned-model checks.

- Brief/Plan hashes: 04112bb38ef8b8f49449af2c30b311e94f483910689ebee0533affbe13b13fe5 / a1313245f7e9d9b9e13ca062e0b3f9efc4b5df41c0cdb0a122cca874c09adf0c.
- Accepted record hash: 1dc1b883cc211cd70dcfe859c0ce773796c76eb129cf2c3dbbb351198ba8f5db.
- Contract SHA: 2dc2e1243641bea773da0181d29909dcb6d05e58b831979527f988bad230737c.
- Native fingerprint: 014a843c80c686424807b7218784601f662e7318b1fdce554785a2be7d77a45c.

## Geometry, tint, and restoration

- A–E retained actual public viewport measurements and no horizontal page/root overflow. Desktop CSS was 1232×923; actual public mobile CSS was 390×844. PNG raster sizes are recorded per screenshot below and differ from CSS viewport because the embedded browser capture excludes browser/scrollbar pixels.
- A uses a 1232×923 CSS desktop and PNG raster 1217×912; public mobile CSS 390×844, viewport PNG 375×812 and full-page PNG 375×1606.
- B/C/D/E use desktop CSS/raster 1232×923 and mobile CSS 390×844, viewport raster 375×812. Their full-page PNGs are retained alongside viewport captures.
- Accent alpha and computed style matched across A–E: #61ce7033 / rgba(97, 206, 112, 0.2); source was the unconfirmed Elementor stock system Accent, not a verified brand token; white opaque underlay remained in the accepted surface contract.
- Every generated operation was guarded-Undone after a fresh descriptor and owned-model check. D-v289 was restored before D-v290. Each next operation began with an empty editor/public baseline. Final editor and public root sets are both [] on post 5214. No user root was removed or edited.
- Revision values above are the fresh post-Publish/reload descriptor revisions used for acceptance; generation revisions are retained in each evidence record. For example D-v289 was generation revision 4 and readback revision 6.
- The first Publish click for D-v290 and E left the button enabled; a second ordinary UI click completed save, after which Publish disabled and reload/readback confirmed the result. This was a save UI quirk, not a second generation or write. All other saves had one successful Publish action.
- No editor mobile preview is used as public-mobile evidence. No CSS/DOM injection or external plugin/settings change was made.

## Local checks

Ran against the unchanged v290 source at HEAD 7a5c3947bd94df6be334a3fe932900eba70897fb:

- Design Pipeline Contract: 940 checks PASS.
- Flex Runtime: 1469 checks PASS.
- Node suites: 20/20 PASS.
- Elementor patch guard: PASS.
- Imported template catalog: 158 manifest entries, 156 retrievable trees, 156 instantiated previews.
- PHP lint: brief-ir.php, design-plan.php, llm.php, accepted-contract.php, recipes.php all PASS.
- Package probe: four scenarios PASS, 253 manifest files, zero file/hash mismatches. Its full diagnostic serialization still reports malformed UTF-8; compact summary serialization succeeds. No package file changed in this documentation-only continuation, so package hashes were not regenerated.
- Runtime source was not modified in this documentation closeout.

## Commits, push, and remaining limitation

Runtime source commit is 7a5c3947bd94df6be334a3fe932900eba70897fb. Documentation/evidence commit d7ed1327157f6d65f5c1aac89936fdccbd685fd0 was pushed; the immediate post-push `git ls-remote origin refs/heads/main` returned the same SHA. This closeout did not create a new runtime version. The later status-record commit and its remote verification are given in the final status.

Overall lifecycle, native readback, content fidelity, desktop geometry, and document restoration passed for all five target slots. Four are full visual acceptances. E remains partial because the site's own floating chat launcher occludes a small part of one mobile paragraph. The 5/5 full-acceptance gate therefore remains open.


## Checked screenshot gallery

Every PNG below was freshly captured from the existing public page after Publish/reload, saved from Browser Use screenshot bytes, format/dimensions checked, opened, and visually inspected. CSS viewport is reported separately from raster size. The site-owned chat greeting was collapsed through its native UI where noted; no injected CSS/DOM changes were used.

### A — Testimonials

Post 5214, root bf61768, operation wpae-13fec7681cbd488f, identity 4b07291e-f6f1-41d6-984c-f4306525f466, revision 5. Public desktop CSS 1232×923; PNG 1217×912. Public mobile CSS 390×844; viewport PNG 375×812.

![A Testimonials public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/A-v290-public-desktop-viewport.png)

[Download A desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/A-v290-public-desktop-viewport.png)

![A Testimonials public mobile, greeting collapsed](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/A-v290-public-mobile-390-greeting-collapsed.png)

[Download A mobile viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/A-v290-public-mobile-390-greeting-collapsed.png) · [Download A mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/A-v290-public-mobile-full-390-greeting-collapsed.png)

### B — Team grid

Post 5214, root c0bfd54, operation wpae-4165df7e7eb25d93, identity 2f86edd7-893a-4965-8aae-10cd4806beac, revision 6. Public desktop CSS/PNG 1232×923. Public mobile CSS 390×844; viewport PNG 375×812.

![B Team grid public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/B-v290-public-desktop-viewport.png)

[Download B desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/B-v290-public-desktop-viewport.png)

![B Team grid public mobile, greeting collapsed](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/B-v290-public-mobile-390-greeting-collapsed.png)

[Download B mobile viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/B-v290-public-mobile-390-greeting-collapsed.png) · [Download B mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/B-v290-public-mobile-390-full-greeting-collapsed.png)

### C — Team editorial rows

Post 5214, root e98cca1, operation wpae-4fc2059529e2c7ff, identity 601879c8-59e8-4dab-9e75-60e70631135b, revision 5. Public desktop CSS/PNG 1232×923. Public mobile CSS 390×844; viewport PNG 375×812.

![C Team editorial rows public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/C-v290-public-desktop-viewport.png)

[Download C desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/C-v290-public-desktop-viewport.png)

![C Team editorial rows public mobile, greeting collapsed](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/C-v290-public-mobile-390-greeting-collapsed.png)

[Download C mobile viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/C-v290-public-mobile-390-greeting-collapsed.png) · [Download C mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/C-v290-public-mobile-390-full-greeting-collapsed.png)

### D — Services, corrected v290 first result

Post 5214, root 21cbb2d, operation wpae-550f6a8b5bde630d, identity 3b0f3b59-a8ea-42c3-b399-1d44cbae8df3, revision 6. Public desktop CSS/PNG 1232×923. Public mobile CSS 390×844; viewport PNG 375×812. The separate v289 failure screenshots and native export are linked in the D first-failure section above and remain in the same audit folder.

![D Services v290 public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/D-v290-public-desktop-viewport.png)

[Download D desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/D-v290-public-desktop-viewport.png)

![D Services v290 public mobile, greeting collapsed](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/D-v290-public-mobile-390-greeting-collapsed.png)

[Download D mobile viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/D-v290-public-mobile-390-greeting-collapsed.png) · [Download D mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/D-v290-public-mobile-390-full-greeting-collapsed.png)

### E — Process, mobile overlay limitation

Post 5214, root b553009, operation wpae-a73cf12d77b424d5, identity 1cab2083-cd88-4f06-bbed-c6dd6d4bc36f, revision 6. Public desktop CSS/PNG 1232×923. Public mobile CSS 390×844; viewport PNG 375×812. The site-owned launcher covers part of step 3 after the greeting is collapsed; this is retained and reported as SITE_OVERLAP, not hidden.

![E Process public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/E-v290-public-desktop-viewport.png)

[Download E desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/E-v290-public-desktop-viewport.png)

![E Process public mobile, launcher overlap retained](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/E-v290-public-mobile-390-greeting-collapsed.png)

[Download E mobile viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/E-v290-public-mobile-390-greeting-collapsed.png) · [Download E mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-m2-2-selection-readback-v286/screenshots/E-v290-public-mobile-390-full-greeting-collapsed.png)


## Screenshot housekeeping — 2026-10-08

User-authorized cleanup removed 624 obsolete/unreferenced screenshot files (83.44 MiB), including 410 JPEG originals with PNG counterparts. Referenced screenshots, all current M2.2 PNGs and unique first-defect evidence are retained. Inventory: `docs/audits/2026-10-08-screenshot-cleanup/manifest.json`. No runtime or live-page change; acceptance statuses and Services/Process exclusions remain unchanged.
