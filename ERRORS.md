# Error Journal

This file records confirmed failures and their regression status. Read it
before making a new change to the plugin.

## EJ-016: Dirty accent and stale preview state survived trusted hero repair

- **Observed:** v02.10.81 used a muted terracotta CTA family, while the live
  screenshot after the Vision chain still showed three identical hero roots
  and low-contrast body text on the photo. Vision scored the attempts `60`,
  `58`, and `60`; Impeccable rejected the low contrast, internal-looking
  badge, and run-on copy.
- **Root cause:** The project default accent was `#C75B3B` and the clean hero
  hardcoded another terracotta color. Photo text markers were absent, so the
  token map changed white text to ink. Rollback refreshed through the embedded
  Elementor editor model, which retained stale iframe roots.
- **Fix:** v02.10.82 migrates the legacy accent to `#4460EC`, removes hero
  color hardcodes, protects photo text and the black overlay during token
  mapping, uses the content badge `ПРЕДЛОЖЕНИЕ`, and reloads the saved iframe
  directly before retry and after rollback.
- **Regression status:** Local contract tests and PHP lint must pass. Fresh
  live Browser Use validation must show one root, readable white photo text,
  a cobalt token CTA, a semantic outlined badge, and a passing
  screenshot -> Vision/design-taste -> prompt comparison before continuing
  the 10-iteration run.

## EJ-015: Trusted hero source preserved decorative geometry after adaptation

- **Observed:** v02.10.80 generated a native Flex hero from the Vocario source,
  but the screenshot still showed the source white panel, blue decorative
  shape, black circle, oversized spacing, and weak hierarchy. AI Vision scored
  the result `62`; Impeccable also rejected the composition.
- **Root cause:** Disabling trusted preservation only skipped the preservation
  marker. The source hero tree still passed through generic wrappers, so its
  descendant geometry, decoration, and third-party widgets survived until the
  final native conversion.
- **Fix:** v02.10.81 adds a trusted-hero adapter that keeps only the root photo,
  rebuilds requested content as native heading/text-editor/button widgets in a
  transparent Flex shell, puts the outlined badge above the heading, and
  applies a black semi-transparent native Background Overlay.
- **Regression status:** Local contract tests and PHP lint pending. Fresh live
  Browser Use validation must pass the screenshot -> Vision/design-taste ->
  prompt comparison loop before the 10-iteration run continues.

## EJ-014: Vision repair could be lost after Elementor reload

- **Observed:** Iteration 1 received Vision score 60 with major layout defects,
  rolled the write back, and reloaded Elementor. The next browser state showed
  only the welcome message and no repair request, while the preview still had
  the sparse hero composition.
- **Root cause:** The retry functions cleared their session state before
  checking `config.ready`; a delayed Elementor boot could therefore discard a
  valid pending repair. The two-minute TTL was also shorter than a real
  generation plus browser-side review and reconnect.
- **Fix:** v02.10.80 keeps pending state until the chat is ready, retries the
  readiness check after one second, and extends both pending retry TTLs to ten
  minutes.
- **Regression status:** Local contract tests and PHP lint pending. Fresh live
  10-iteration Browser Use validation must confirm that every failed Vision
  review either completes its bounded repair or reports a retained retry.

## EJ-013: Content-only briefs could become advisory text and stale previews

- **Observed:** The content-only prompt `Что получают наши клиенты...` was
  returned as an explanatory plan instead of generating a block. Earlier
  retries also left three repeated hero roots visible, with the rendered button
  still green/blue while the generated JSON specified orange/white.
- **Root cause:** The action detector treated any brief beginning with `Что`
  as advisory, even without a question mark. The chat context always declared
  a selected-element scope when no elements were selected. The retry path
  called `window.location.reload()` and immediately submitted again, which can
  be ignored by an embedded Elementor browser and preserve stale preview DOM.
- **Fix:** v02.10.79 limits the advisory opener rule to actual questions or
  short messages, reports page context for an empty selection, refreshes the
  Elementor preview before a repair retry, and blocks overlapping requests.
- **Regression status:** Local JS contract tests and PHP lint pass. Fresh live
  10-iteration Browser Use validation with screenshot, scoped JSON/DOM, Vision,
  and `design-taste-frontend` review is pending after deployment.

## EJ-012: Trusted Team and Image Box briefs lost source composition

- **Observed:** Team content-only generation selected a competing trusted
  fixture and left member information as one paragraph. Image Box generation
  could fall back to ordinary generation because the source had only
  `nested-carousel` and `icon-box` widgets, so the rendered result was sparse
  and unrelated to the supplied template.
- **Root cause:** Retrieval had no source preference when CopyElement and
  Vocario matched the same archetype. The content parser did not understand
  `name, role. description`, while narrative adaptation required ordinary
  headings/text widgets and pruned the Image Box card widgets when that path
  failed.
- **Fix:** v02.10.78 prefers CopyElement for ordinary prompts, adds a Team
  pair parser, and adds a dedicated Image Box nested-carousel adapter that
  preserves populated image cards and removes unused slides.
- **Regression status:** Local lint/contract checks required. Fresh Browser
  Use Team, Image Box, Testimonials and About screenshot, scoped JSON/DOM,
  AI Vision and `design-taste-frontend` review required.

## EJ-011: Explicit Team brief was routed to the Hero template

- **Observed:** The v02.10.76 natural Team brief began with «Наша команда»,
  but the generated block used the Vocario Hero composition, including a
  «ПЕРВЫЙ ЭКРАН» badge, oversized empty hero surface and one flat paragraph.
  Vision scored it 60/62 and reported missing team cards and a floating graphic.
- **Root cause:** The generic Hero detector matched «главное» in the Team
  description before the later Team detector ran.
- **Fix:** v02.10.77 recognizes an explicit Team heading at the start of the
  content brief before generic Hero signals; incidental About mentions remain
  unaffected.
- **Regression status:** Contract coverage added. Fresh Browser Use Team,
  Image Box and Testimonials generation with scoped JSON/DOM, screenshot, AI
  Vision and design-taste review is required.

## EJ-010: Trusted About kept empty placeholder layers and negative offset

- **Observed:** The v02.10.75 About c70 generation loaded the replacement photo,
  but still rendered empty gray placeholder boxes and a detached composition.
  Scoped JSON showed an overlay container with `margin.top = -262` and two
  empty nested containers; the final repair response also ended with HTTP 500.
- **Root cause:** Trusted layout normalization preserved source decorative
  containers even when they contained no widgets or media, and preserved
  geometry only cleared negative horizontal margins. The source kit's empty
  placeholder layer therefore survived into the generated Flex tree.
- **Fix:** v02.10.76 skips nested containers with no widget/background media and
  clears negative top/right/bottom/left margins in all responsive margin maps.
- **Regression status:** Local contract/lint checks pending. Fresh Browser Use
  About screenshot, scoped JSON/DOM, AI Vision and design-taste review required;
  then rerun Team, Image Box and Testimonials.

## EJ-009: Natural About brief selected the Team template and repair duplicated roots

- **Observed:** The v02.10.74 About prompt mentioning a studio and leaving the
  site to the team selected the Team fixture `c1429`. The canvas contained
  duplicate roots with a `КОМАНДА` badge, oversized placeholder heading layers,
  and the repair request ended with HTTP 500.
- **Root cause:** About detection only recognized explicit `о компании`/`о нас`
  phrases; the later Team rule matched `команде`. Retrieval also treated
  `about` as a Team alias, making the wrong fixture easier to select.
- **Fix:** v02.10.75 recognizes `студия` in company/about briefs before Team
  matching and removes the generic Team `about` alias.
- **Regression status:** Contract coverage added. Fresh Browser Use About,
  Team and Image Box generation with scoped JSON, DOM, screenshot, AI Vision,
  and design-taste review is required.

## EJ-008: Release metadata was not bumped with the fix

- **Observed:** WP Pusher reported a successful update after the image fix,
  but the live Elementor chat still displayed `v02.10.73`.
- **Root cause:** The release notes and implementation had been prepared for
  v02.10.74, while the plugin header and `WPAE_VERSION` constant remained at
  v02.10.73.
- **Fix:** Synchronize both plugin metadata locations to `v02.10.74` and
  require a live version check after delivery.
- **Regression status:** Local source check added by inspection; Browser Use
  delivery verification is required before the block test run continues.

## EJ-007: Known library image placeholders survived trusted adaptation

- **Observed:** The v02.10.73 testimonials screenshot still showed a gray
  image placeholder. The scoped JSON used a non-empty external URL ending in
  `new-container-image-1.png` with `id: 0`, so the existing empty-image guard
  did not remove or replace it.
- **Root cause:** CopyElement's placeholder image URL looked like valid remote
  media to the normalizer. The trusted path therefore preserved it even though
  it was not relevant content.
- **Fix:** v02.10.74 detects only known placeholder URL patterns in `image` and
  `image-box` widgets and replaces them with archetype-specific network image
  URLs plus alt text; real source images remain untouched.
- **Regression status:** Contract coverage added. Must be confirmed by fresh
  Browser Use screenshots and scoped JSON for testimonials, About, Team and
  Image Box.

## EJ-006: Trusted multi-pair blocks bypassed placeholder cleanup

- **Observed:** The first live testimonials test on v02.10.72 mapped both
  requested quotes and names, but the rendered block retained `Sample Subtitle`,
  `New Block Title`, lorem ipsum copy, and weak separation between testimonial
  cards. AI Vision scored it `58` and started a repair cycle that still left
  placeholder content.
- **Root cause:** `wpae_llm_apply_library_template()` uses a separate multi-pair
  card adaptation path. That path never called the existing
  `wpae_llm_normalize_library_layout()` placeholder/card normalizer, even when
  the selected source was a trusted bundled template.
- **Fix:** v02.10.73 runs the existing library layout normalizer in the trusted
  branch after pair adaptation and before preservation-specific visual passes.
- **Regression status:** Contract coverage added. Testimonials and remaining
  block types require fresh Browser Use generation with screenshot, scoped JSON,
  DOM, AI Vision, and design-taste review.

## EJ-005: Hero badge and content alignment remained visually detached

- **Observed:** The v02.10.71 live screenshot still showed the outlined
  «ПРЕИМУЩЕСТВА» badge detached in the top-left, gray body copy over the photo,
  and a green CTA with blue text. The centered heading and surrounding content
  therefore did not form one coherent hero composition.
- **Root cause:** Hero normalization treated the generated badge and the
  content shell as sibling roots, and alignment discovery could return the
  outer container's `flex-start` before inspecting text-widget alignment. The
  button color pass also did not cover Elementor's hover text setting.
- **Fix:** v02.10.72 reparents the badge into the content shell immediately
  before the first heading, prioritizes text-widget alignment, and sets normal
  and hover CTA text to white while retaining the native black photo overlay.
- **Regression status:** Contract coverage added. Fresh Browser Use generation
  with scoped JSON, DOM, screenshot, AI Vision, and design-taste review is
  pending.

## EJ-001: Elementor roots duplicated after a clean generation

- **Observed:** After deleting and publishing 9 stale AI sections, a fresh
  editor reload showed `0` roots. One content-only generation then produced 3
  identical Flexbox Hero roots (`341e253`, `0486d4a`, `0cd2441`) in the same
  request. Each root was `1010x553` and contained the same four widgets.
- **Evidence:** Browser Use DOM query on `#elementor-preview-iframe` reported
  3 top-level roots with identical title, subtitle, body, and CTA. The
  screenshot showed the Hero repeated vertically; AI Vision scored it `40`
  and rolled the request back after two repair passes.
- **Root cause:** The Elementor preview iframe refresh did not reset the parent
  editor model after rollback, so repeated repair inserts could survive even
  though every server write started from `before_top_level_ids: []`. A single
  native delete/retry was insufficient for this boundary.
- **Fix:** v02.10.69 persists Vision repair state and performs a full Elementor
  page reload between repair writes; final rollback also reloads the saved
  state. Native delete/retry remains a guarded fallback for in-session cleanup.
- **Status:** Open pending a fresh live generation proving exactly one root and
  a passing Vision/design review.

## EJ-002: Trusted library badge disappeared

- **Observed:** Trusted library generations could contain the imported design
  but no outlined badge above the heading.
- **Root cause:** The trusted-library branch intentionally skipped the generic
  visual-grammar pass, but the badge rule lived only in that skipped pass.
- **Fix:** v02.10.69 adds a trusted-only badge enforcement pass that preserves
  the source content in a Flex content shell and inserts exactly one outlined
  badge before it.
- **Regression status:** Contract coverage added. Live verification is pending
  a fresh Browser Use generation after delivery.

## EJ-004: Natural hero brief was routed through trusted benefits design

- **Observed:** The content-only brief «Школа переговоров для руководителей...
  Запишитесь на вводную встречу» produced a photo-backed hero, but the action
  log said `Hero-нормализация не требуется`, and the result used a detached
  «ПРЕИМУЩЕСТВА» badge. AI Vision scored the result `55` and reported nearly
  invisible description text, a detached badge, and weak CTA presentation.
- **Root cause:** The natural brief did not contain the literal `hero`/«первый
  экран» marker, so archetype detection returned `benefits`. Retrieval then
  selected a trusted bundled source and `trusted-preservation` bypassed the
  hero alignment and contrast pass.
- **Fix:** v02.10.71 recognizes a single marketing offer with a signup CTA as
  `hero`, disables trusted preservation for hero while retaining the source
  layout, applies a native black overlay for photo-backed text, and forces
  white descendant typography plus one shared alignment.
- **Regression status:** Contract coverage added. Fresh Browser Use generation
  is required before marking the issue resolved.

## EJ-003: Light text over photo lacked native overlay

- **Observed:** In the live Vocario Hero, the badge and white text sat directly
  over a dark photo; the badge was technically present but visually weak.
- **Root cause:** The trusted visual-state normalizer preserved the image but had
  no contrast rule for light descendant text. Badge enforcement also copied the
  root background image into the content shell, duplicating the media layer.
- **Fix:** v02.10.70 adds a native black semi-transparent Elementor Background
  Overlay when a photo-backed container contains white text, and strips root
  media/overlay settings from the structural content shell. Hero composition
  normalization now applies the same contrast rule outside the trusted path and
  synchronizes all text-column alignment; the badge gets a white surface.
- **Regression status:** Contract coverage added. Live verification is pending
  a fresh Browser Use generation after delivery.
