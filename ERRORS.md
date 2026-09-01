# Error Journal

This file records confirmed failures and their regression status. Read it
before making a new change to the plugin.

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
