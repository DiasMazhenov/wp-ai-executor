# Accepted visual policy v259 — 2026-10-05

## Scope and ownership

Existing BriefIR → DesignPlan → ElementorIR → native compiler → validation → transaction/readback remains the only create chain. New canonical creates freeze optional visual_policy v1. Historical contracts without this policy retain their compiler path and are not rewritten.

Previously composition records, profile resolution, IR role defaults, late compiler overrides and normalizer independently chose geometry or presentation. Accepted DesignPlan now owns intro placement, container alignment separately from text alignment, reading measure, semantic headings and responsive typography, relationship spacing, collection columns/gap/responsive policy, and eyebrow presentation. Intake owns explicit intent with source spans. IR binds the accepted decisions to roles. Compiler translates controls. Normalizer preserves validated policy-tagged native Grid. LayoutReport remains static evidence.

Priority: explicit Brief → selected composition record → selected visual profile → documented default. Selected profiles take priority over inherited reference/page tokens. Section measure affects the section intro/split copy wrapper only; item copy stays full width. Wrapper owns measure and placement; label widgets do not duplicate it. Pricing intro is H2 independently of display size. Item copy and CTA gaps are separate. Grid fr columns own equal widths with native gap, replacing percentage corrections for new contracts. Tablet stack is one column. Badge container alone owns background/border/radius/padding; label owns text/color/type. Plain and negative badge requests retain exact supplied eyebrow. Contradictory plain/pill instructions refuse before write.

Scoped repair is separate from first render: policy contracts change section vertical spacing only for the existing validated compact-spacing repair; historical repair compatibility remains. No live repair has been applied in this audit.

## Starting state

Source HEAD 1097c474bc5cbfd0e2a66e5e671cb73d2ae7f9bd, v258. Source prepared as v259, one version increment. Existing editor post5214 reload and independent public inspection found saved root set [], hash 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945. Historical [37b84f9] is not the current baseline. No roots were removed by this task. Fresh current editor inspection again confirms v258/frontend typed-lifecycle-v6, valid empty baseline, Publish disabled. Existing authorized tab remains in use; no pages/drafts created.

## Local evidence

DesignPlan 365 checks; runtime 1269 checks; Node14/14; patch guard PASS; catalog158 manifests/156 retrievable/156 instantiated previews; PHP lint changed files PASS; package252 hashes/zero mismatches/four scenarios PASS; git diff --check PASS.

Regressions cover selected composition/profile, freeze and readback guards, role measure/axis, Pricing H2, count2/3/4/6 where admitted, equal gap-aware Grid and tablet/mobile agreement, plain/pill/negative/conflicting intent, single badge box owner, exact short/long copy, required media and photo prohibition. Static counts do not establish browser rendering.

Package diagnostic limitation: full validator result contains binary strings and fails full JSON serialization with malformed UTF-8; compact scenario summary serializes and all four real package validation scenarios pass.

## Status layers

Source: prepared, not yet committed/pushed. Install: NOT RUN for v259. Editor: v258 confirmed. Generation/readback/visual acceptance: NOT RUN for v259. B–J exact fixtures copied without changing text or assets. PNGs and browser measurements are pending and no live PASS is claimed.

| Scenario | Composition | Profile | Generation | Readback | Visual |
|---|---|---|---|---|---|
| B | hero.split_60_40.right | editorial_light | NOT RUN | NOT RUN | NOT RUN |
| C | hero.split_60_40.left | editorial_light | NOT RUN | NOT RUN | NOT RUN |
| D | hero.split_60_40.right | soft_cards_light | NOT RUN | NOT RUN | NOT RUN |
| E | about.split_50_50.right | editorial_light | NOT RUN | NOT RUN | NOT RUN |
| F | benefits.grid | editorial_light | NOT RUN | NOT RUN | NOT RUN |
| G | benefits.editorial_list | editorial_light | NOT RUN | NOT RUN | NOT RUN |
| H | benefits.grid | soft_cards_light | NOT RUN | NOT RUN | NOT RUN |
| I | pricing.tiers | automatic | NOT RUN | NOT RUN | NOT RUN |
| J | faq.native | automatic | NOT RUN | NOT RUN | NOT RUN |

Before/after limitations: historical centered reading-wrapper axis, list copy width leakage, Pricing percent+gap wrap and tablet-row contradiction, double badge decoration and false positive negative intent are addressed at source boundaries. Browser comparison remains pending. Historical negative Vision findings remain in the v247 audit; no replacement hides them.

## Publication, installation and first B

Runtime commit42b88d589da7bffd419b21fc5d2d7f3f5ef4c4e8 pushed to origin/main and independently matched by ls-remote. WP Pusher update of WP AI Executor only completed despite snapshot timeout; independent Plugins row PHPv259 and reloaded editor configv259/frontendtyped-lifecycle-v6 confirmed. Existing single tab was navigated between authorized WP Pusher/Plugins/editor/public; no tabs or pages created.

B first generation: exact B–D fixture, hero.split_60_40.right/editorial_light, rootb861b04, operationwpae-61af2ec3fbd5889b, identityf04a32c3-8646-4805-9b12-f87716b4c1fa, revision5, contract965e273b8f88d2773546a941. Brief3aa85d2e19936c14b2829a03bbe8c7095e2bb7476e3a772e638b6ea890a8b8a7. Provider0/write1; first Plan/native JSON retained. Owned model check PASS, native Publish/reload PASS, authored controls recursively match selected fresh native JSON with zero differences. Saved hash12f51aa0c08007b3a739a05dcb000ec0b2aa41c46c89ffae2d865e42d6099df1/rootset[b861b04].

Publicdesktop actualCSS1280×900/PNG1280×900; publicmobile CSS390×1000/PNG390×1000. Screenshots captured after save/reload, JPEG bytes retained locally, converted to PNG, signature/dimensions checked and PNG files opened for visual inspection. Pill, heading, description, CTA#start and exact supplied licensed photo/alt preserved. Image complete1200×675; desktop448×252, mobile358×201.375, proportional without distortion. Intro starts on its column axis; no historical center shift. Advisory Vision68 claims words separated: fresh public pixels show normal whole heading; allegation not confirmed. First technical fidelity/save PASS; visual request fidelity PASS; composition quality acceptable for short editorial Hero, pending multi-family acceptance. Site chatbot is outside root and does not hide B content.

Actual320/767/768/1024/1025 CSS widths independently measured; document/root widths equal viewport, no horizontal overflow. Computed owned root minHeight0/maxHeightnone; no min-height explanation inferred. Root boxed content owns vertical padding in e-con-inner, so root computed vertical padding0 alone does not describe section rhythm. No manual repair applied.

![B public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-visual-policy/B-public-desktop.png)

[Download desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-visual-policy/B-public-desktop.png)

![B public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-visual-policy/B-public-mobile.png)

[Download mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-visual-policy/B-public-mobile.png)

## First Undo refusal and scoped source correction

Guarded Undo B refused before write: “Elementor data failed design-system contract.” Existing generic validator requires a top-level container and explicit palette even when exact owned inverse legitimately returns empty saved baseline. Current server remained[b861b04]; no manual deletion or bypass. Dependent generations C–J stopped. Original refusal UI/screenshot retained.

Correction adds server-derived verified_empty_inverse context only after exact post/operation identity/revision/eligibility checks, creation contract before_owned[], exact current root ownership and no foreign roots. Generic empty create/update remains refused. Existing expected-before, protected zones, preview, transaction/readback and inverse ownership guards remain. Preflight applies no landing-page content requirements to a proven empty inverse. Added real-validator/preflight and lifecycle regressions: foreign roots/stale revision refuse; actual guarded inverse restores[]. Latest checks runtime1293/DesignPlan365/Node14/14/patchguard/lint/package252 hashes+four probes/diff PASS. Correction remains in v259 to comply with one requested version increment; separate source commit/install evidence is required and version label alone cannot distinguish these source revisions. Correction deployment and real Undo retry pending.

## Empty inverse readback propagation correction

Source correction b8a57e4c2380980ef3f4f1eb7ade3a367c096b49 pushed/remote confirmed. WP Pusher update attempted; editor remains v259. Retry demonstrates new preflight policy loaded, but actual finalize/readback validator lacked verified inverse context: transaction refused after write and rolled back. Reload confirmed exact B root/hash/revision5, so baseline restoration remains pending; dependent C–J writes stopped. Original second refusal retained. Server-attested context now propagates into transaction verification only when expected empty data exactly equals readback; ordinary empty write and mismatched readback remain refused. Real transaction probe verifies these three cases along with prior autosave/concurrent rollback checks. No owner guard disabled and no manual root removal.

## B baseline restored after installed correction

Source b826ff1ce1c904c789ee1a13c174867ea45ecdd1 pushed/remote matched. WP Pusher first returned download failure, retained as evidence; one ordinary retry returned “Plugin was successfully updated.” Plugins PHPv259/reloaded editorv259 confirmed independently. Same B guarded Undo succeeded, revision6/already_undone. Saved hash exactly4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945/rootset[], independently publicroots[]. C–J dependent writes resumed only after this proof. First B native/authored controls remain unchanged; no cosmetic repair/re-generation.

Probe hygiene: the old untracked transactions-probe source remains local/untracked; scoped new readback regression is empty-inverse-transaction-probe.php in this audit directory. It exercises real transaction verification with external WP stubs, without bundling the foreign original probe.


## 2026-10-05 — C first-render failures and baseline discrepancy

Correction to earlier B restoration wording: B Undo restored server/public empty roots, but native editor hydration reconstructed previous published HTML as an unowned text-editor container. Native baseline was not verified adequately before C. C native Publish persisted that neighbor; this is a lifecycle defect, not an intentional second test root.

C: hero.split_60_40.left/editorial_light; root `9e0af51`, operation `wpae-b07ea89c8ceb8880`, identity `4d34863b-d2d4-499c-a387-6bed0b4d95e2`, revision5. Exact B–D fixture retained, provider0/write1. First result, owned model check, selected native JSON, public DOM and opened PNG retained. Server root set after save/reload `[1a309f40,9e0af51]`; neighbor `1a309f40` contains previous B HTML in a text-editor and has no typed ownership marker. No root was manually removed.

C first render is FAIL: at CSS768/1024/1025 the 608px reading wrapper exceeds its copy column (344/472/564.6px); document widths1000/1128/1036px. Desktop/public CSS1280×1000, full-page PNG1265×1243; mobile/public CSS390×1400, PNG390×1400. Images loaded and copy/CTA retained, but overflow and neighbor duplication prevent acceptance. Advisory Vision negative findings remain in first UI evidence. Guarded C Undo refused before write: design-system contract rejected the remaining palette-free legacy neighbor. Fresh refusal screenshot editor CSS1232×923/PNG1232×923, opened and visually checked. D–J stopped; final baseline is NOT restored.

Source correction (v259 retained, not yet installed): native custom width `min(100%, accepted measure)` translates the Plan ceiling inside the selected column; exact unchanged legacy fingerprints retain their existing palette without permitting modified/new unmarked roots; fresh create refuses native HTML fallback over an empty saved baseline before provider/write. Historical contracts are unchanged. Native custom unit support was verified against official Elementor container/base-units source. Cleanup of the unowned neighbor awaits explicit user authorization; no protection disabled. C Undo retry and corrected visual acceptance remain pending.

Local correction checks: runtime1302; DesignPlan365; Node15/15; patch guard/catalog158/156/156; PHP lint four changed PHP files; package252 hashes/zero mismatch/four real probe scenarios; scoped empty-inverse transaction probe; diff check PASS. Full package diagnostic JSON limitation unchanged. Commit/push/install/retry are separate pending statuses.
