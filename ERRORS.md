# Error Journal

This file records confirmed failures and their regression status. Read it
before making a new change to the plugin.

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
