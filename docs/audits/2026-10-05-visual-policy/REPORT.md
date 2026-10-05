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
