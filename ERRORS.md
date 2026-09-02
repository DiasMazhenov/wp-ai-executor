# Error Journal

This file records confirmed failures and their regression status. Read it
before making a new change to the plugin.

## EJ-040: Preserved Team template lost parent side padding and heading tier

- **Observed:** The live Team block rendered with `padding-left/right: 0px`,
  while `НАША КОМАНДА` was a heading widget configured with `header_size: p`,
  so Elementor displayed it as small body text.
- **Root cause:** The preserved-library badge pass reset the root container's
  padding to zero and the source heading semantics were not promoted after
  template adaptation. The shared badge deduplicator could also treat the
  Team section title as redundant with the `КОМАНДА` badge.
- **Fix:** v02.11.08 adds responsive horizontal padding for Team roots,
  promotes the outside section title to a native `h2`, and preserves that
  title during badge deduplication.
- **Regression status:** Recheck with Browser Use using screenshot ->
  Vision/design-taste -> prompt comparison -> scoped JSON and DOM. Require a
  visible large heading, nonzero parent side padding, preserved card images,
  no overflow and a healthy Elementor iframe.

## EJ-039: Elementor editor emitted external console errors during a healthy write

- **Observed:** After the v02.11.07 deployment, the fresh Elementor editor
  logged `gtag is not defined`, a checklist `parentElement` null error, two
  Elementor MCP 404 responses and `Angie is not available`. The iframe stayed
  alive, the native write returned HTTP 200, and AI Vision completed with score
  `82`, so these messages did not crash the plugin generation flow.
- **Root cause:** The first error originates in the site's preview theme script;
  the remaining errors originate in Elementor 4.1.1 checklist/MCP services.
  They are outside WP AI Executor's source and are independent of the saved
  Elementor JSON.
- **Fix:** No plugin workaround is claimed. The Browser Use health check now
  distinguishes these external editor warnings from a plugin fatal and records
  them for site/Elementor maintenance.
- **Regression status:** Plugin generation, preflight, write, preview refresh,
  scoped JSON copy and editor iframe remain operational. Recheck after Elementor
  or theme updates; do not treat these external messages as a successful visual
  generation.

## EJ-038: Content-only fallback redistributed brief text across the wrong widgets

- **Observed:** The live v02.11.06 benefits generation rendered a reasonable
  three-card grid, but one requested phrase was duplicated between a card
  heading and another card's body, while an unrequested `Обсудить проект` CTA
  appeared detached below the grid. The screenshot therefore did not match
  the content-only brief even though the request was written successfully.
- **Root cause:** When the provider and bounded repair returned no usable tree,
  generic fallback content filling assigned missing units in widget order. It
  treated the section title, card copy, and button as interchangeable slots;
  the fallback's sample card descriptions and default CTA remained in the
  output.
- **Fix:** v02.11.07 maps unlabeled repeatable content deterministically: the
  first unit becomes the section heading, each remaining unit becomes one card
  heading, and fallback-only body/CTA widgets are removed unless requested.
- **Regression status:** Local contract tests, PHP lint and diff checks must
  pass. A fresh Browser Use generation must show exact requested card copy,
  no invented CTA, balanced Flexbox cards, and a passing screenshot,
  Vision/design-taste and Impeccable review.

## EJ-037: Four-card fallback composition overflowed and used giant headings

- **Observed:** The live content-only benefits generation rendered each card as
  a wide row with oversized headings, clipped descriptions and horizontal
  overflow. AI Vision scored the result `42` and identified clipping,
  overlapping borders and broken hierarchy.
- **Root cause:** The fallback selected four `31%` cards even though the Flexbox
  gap made four columns exceed the row width. Marked card headings could also
  bypass the bento-depth check and retain the provider's `3.5rem` typography;
  one card retained `row` direction while the others used `column`.
- **Fix:** v02.11.06 selects `48%` widths for four cards, forces bento card
  containers to a shared vertical Flexbox direction, and treats the
  `wpae-card-heading` class as a card-heading marker for compact typography.
- **Regression status:** Local contract tests, PHP lint and diff checks pass.
  Fresh Browser Use generation must produce a balanced 2x2 grid without
  horizontal overflow, then pass screenshot, Vision/design-taste and
  Impeccable review.

## EJ-036: Native photo overlay settings could remain stale

- **Observed:** The reported gray/black dimming appeared around cards in the
  Elementor editor. Live DOM inspection showed it was Elementor's selection
  scrim, not a generated black overlay. Separately, generated containers could
  retain native overlay color/gradient settings after a photo was removed or
  text became dark.
- **Root cause:** The visual normalizers only wrote the black overlay for the
  positive photo case and did not clear stale native overlay color/gradient
  keys on the other branch.
- **Fix:** v02.11.05 routes preserved-library and hero normalization through
  `wpae_llm_sync_native_photo_overlay_settings()`, which keeps a black native
  overlay only for photo containers with light text and removes stale overlay
  color/gradient settings otherwise.
- **Regression status:** Local contract tests, PHP lint and diff checks must
  pass. Fresh Browser Use generation must show no black overlay on a non-photo
  block, keep the editor iframe alive, and provide screenshot plus visual
  review evidence.

## EJ-035: Generated section duplicated its badge label

- **Observed:** The live Team block rendered the `КОМАНДА` badge immediately
  above a second `НАША КОМАНДА` heading, which Vision scored as redundant
  hierarchy and excess whitespace. The editor screenshot also dimmed adjacent
  cards while one container was selected.
- **Root cause:** Visual grammar enforced the badge but did not remove a
  semantically equivalent section heading or paragraph. The dimming was
  Elementor's selection scrim, not `background_overlay_color` written by the
  plugin.
- **Fix:** v02.11.04 removes an equivalent heading or paragraph outside
  repeatable bento cards while preserving the required outlined badge and the
  card headings.
- **Regression status:** PHP lint, contract tests and a fresh Team Browser Use
  generation must pass; the live editor must remain available after the write.

## EJ-032: Approved library adaptation retained source copy

- **Observed:** A fresh benefits generation contained the requested card
  headings and body text, but the last card still rendered source-library copy
  (`Компоненты и адаптивная сетка не рассыпаются на мобильных устройствах.`).
  The screenshot looked structurally valid, yet it did not match the
  content-only brief.
- **Root cause:** `wpae_llm_clear_unrequested_library_copy()` was gated by
  `trusted_bundled`. Approved/imported library records therefore skipped the
  source-copy scrub after pair adaptation; content fidelity only checked for
  requested text and could not reject extra text.
- **Fix:** v02.10.99 applies the existing scrub to every adapted library path,
  including pair, narrative, image-box and mega-menu branches. Imported layout
  geometry remains intact while unrequested copy is removed before the final
  fidelity check.
- **Regression status:** Node contract, PHP lint and diff checks must pass.
  Every supported block type still requires live screenshot, scoped JSON/DOM,
  Vision/design-taste review and Impeccable validation.

## EJ-031: Vision timeout was reported as a failed Elementor generation

- **Observed:** A benefits generation was written to Elementor, but a Gemini
  Vision cURL timeout made the chat display `Ошибка сервера (500 Internal
  Server Error)`, obscuring the saved design and blocking the visual test
  report.
- **Root cause:** The client chained the advisory Vision promise directly into
  the generation success path, so provider failures entered the generic request
  error handler even though the Elementor write had already succeeded.
- **Fix:** v02.10.98 catches Vision review failures separately, reports the
  provider warning, keeps the write and undo control, and leaves screenshot plus
  Impeccable review as the acceptance gate.
- **Regression status:** Node contract check passes. Live Gemini Vision remains
  network-dependent; a timeout is now a review warning rather than a false
  generation failure.

## EJ-030: Courses kit root was used for a benefits request

- **Observed:** The benefits brief was inserted into the Vocario `Courses`
  root. Its source layout kept a 25% text column beside a 75% image column,
  so the requested copy rendered as a compressed, overlapping text block even
  though every container had native Flex classes.
- **Root cause:** Retrieval allowed the `courses` category for benefits and
  root scoring treated `Courses` as a benefits-compatible root. Content
  fidelity passed because the requested words were present, but composition
  type was wrong.
- **Fix:** v02.10.97 limits the canonical benefits category to `benefits` and
  rejects multi-root kits unless a root title matches the requested archetype.
  The type-specific native Flex fallback now handles unmatched benefits
  requests instead of inheriting incompatible source geometry.
- **Regression status:** Node contract test, PHP lint and diff check pass.
  Fresh Browser Use generation must show a benefits grid with readable copy,
  then the remaining block types must be checked through screenshot,
  Vision/design-taste and Impeccable review.

## EJ-027: Incidental process wording changed an explicit benefits block

- **Observed:** A content-only benefits brief began with `Преимущества`, but
  also contained `на каждом этапе`. The live generation produced a sparse,
  mismatched composition and the editor still showed the unrelated Hero/КЕЙСЫ
  visual state instead of a benefits card layout.
- **Root cause:** The classifier checked the generic process vocabulary before
  recognizing an explicit benefits section heading, so `этап` could override
  the user's declared block type.
- **Fix:** v02.10.96 recognizes explicit benefits/features headings before the
  generic process branch and matches the `Courses` root inside a multi-section
  Vocario file. The plugin version metadata is synchronized to the release;
  local contracts protect both priorities.
- **Regression status:** Node contract test, PHP lint, and diff check pass.
  Browser Use must confirm the fresh benefits root uses the benefits category,
  contains its requested copy, and passes the screenshot/Impeccable review.

## EJ-028: AI Vision provider timed out after a successful write

- **Observed:** The live Gemini Vision review returned cURL error 28 after 45
  seconds with zero response bytes. The Elementor write remained in the canvas,
  while the chat reported that the preview could not be checked.
- **Root cause:** Provider/network timeout during the external Vision request;
  this is separate from the classifier and does not prove visual quality.
- **Fix:** No code fix is claimed. The write is retained as designed, and the
  screenshot is reviewed manually with the Impeccable rubric when Vision is
  unavailable. Provider health remains an open deployment check.
- **Regression status:** Must be tracked separately from visual acceptance;
  a generation is not accepted without screenshot plus Impeccable review.

## EJ-029: Benefits retrieval selected the first Hero root in a kit file

- **Observed:** The request was classified as `benefits`, but the selected
  Vocario file contained several top-level sections and the generated JSON had
  `_title: Hero` instead of the Courses/benefits composition.
- **Root cause:** Internal root scoring recognized benefits vocabulary but did
  not include `course/courses/обуч`, so the first root won on widget count.
- **Fix:** v02.10.96 expands the benefits root matcher to include course
  vocabulary, allowing the `Courses` section to win inside Vocario files.
- **Regression status:** Local contract checks pass after release bump.
  Browser Use must confirm the generated root title/category and rendered
  benefits cards before this is closed.

## EJ-025: Content fidelity accepted a library block of the wrong type

- **Observed:** A content-only benefits generation selected `Team c1429` and
  rendered the requested copy inside a team/image composition. Vision reported
  duplicate headings, empty space, and no usable benefits grid, while the
  content gate passed because the requested words were present.
- **Root cause:** Library retrieval ranked shared prompt tokens and the
  CopyElement preference without enforcing the requested archetype against the
  stored category. Content fidelity checked text, but not composition type.
- **Fix:** v02.10.94 filters known archetypes to matching library categories;
  only explicitly tagged `custom` records can match without a canonical
  category. A wrong-type library fixture can no longer replace the native
  fallback for a content-only request.
- **Regression status:** Local contract tests, PHP lint, and diff check pass.
  Browser Use must show each supported block type selecting a matching
  template or a type-specific native fallback before this issue is closed.

## EJ-026: Incidental team wording changed a benefits archetype

- **Observed:** The benefits brief mentioned support for a team, but the live
  generated JSON was classified and rendered as `Team c1429`.
- **Root cause:** The generic Team classifier matched any occurrence of
  `команд*`, including ordinary body copy such as `поддержка команды`.
- **Fix:** v02.10.95 limits Team classification to explicit team headings,
  team lists, or labeled member entries.
- **Regression status:** Local contract tests and PHP lint pass. Re-run the
  benefits prompt in Browser Use and confirm its selected library category is
  `benefits`/`courses` or that its native fallback is benefits-shaped.

## EJ-023: Advisory editor Vision still rolled back valid writes

- **Observed:** A live Hero screenshot showed a populated photo, readable
  heading, labeled CTA, and native Flex layout, but the editor chat received a
  low-confidence-looking quality report and started two repair generations.
  The final canvas returned to an older sparse/duplicated section instead of
  keeping the inspected write.
- **Root cause:** `wpae_vision_editor_review()` returned an advisory gate, but
  the JavaScript request handler treated every `gate.quality_failed` as a
  blocking rollback condition.
- **Fix:** v02.10.92 gates automatic repair/rollback on
  `quality_failed && !gate.advisory`. Advisory Vision findings are still
  displayed for screenshot/design review and do not erase a successful write.
- **Regression status:** Local contract test, PHP lint, and diff check pending;
  fresh Browser Use generation must show a written non-Hero block remaining in
  the canvas after its advisory Vision review.

## EJ-024: Trusted pair adaptation left source copy visible

- **Observed:** A live pricing generation mapped the requested prices into
  cards, but retained English source headings, stale counters, and an
  oversized source CTA, so the rendered block did not match the prompt.
- **Root cause:** The trusted pair path adapted matching widgets but skipped
  the source-copy cleanup used by narrative templates; the CTA normalizer also
  copied a full request sentence into a button.
- **Fix:** v02.10.93 runs trusted cleanup after pair adaptation and compacts
  long CTA sentences in both extraction and normalization paths.
- **Regression status:** Local contract and PHP checks pending; Browser Use
  must confirm pricing and the remaining non-Hero archetypes contain only
  requested copy with no clipping or stale source widgets.

## EJ-022: New trusted heroes could repeat photos already used on the page

- **Observed:** The current page contained several saved hero roots. New
  generations rotated across the trusted image pool, but a page with existing
  duplicates could still receive a photo already used elsewhere, making the
  whole page look repetitive.
- **Root cause:** v02.10.84-v02.10.87 compared the candidate only with the
  source root being normalized. v02.10.90 added saved `_elementor_data`
  collection, but the active Elementor editor can retain newer in-memory
  roots that are not present in that server snapshot; the trusted pool also
  contained only five photos.
- **Attempted fix:** v02.10.88 collected existing background URLs before hero
  normalization and skipped them while an unused relevant photo remained. A
  fresh Browser Use content-only generation then returned HTTP 500, so this
  implementation was not accepted as a live fix.
- **Fix:** v02.10.90 moves saved collection behind the existing
  `wpae_get_elementor_data_for_post()` boundary, and v02.10.91 adds bounded,
  sanitized background URLs from the live preview to the same exclusion set
  while expanding the trusted Vocario pool with relevant photos. The v02.10.88
  failure was caused by referencing `$existing` from `wpae_llm_chat_request()`,
  where it was undefined.
- **Regression status:** Contract tests, PHP lint, diff check, and a runtime
  nested-tree rotation self-check pass. Fresh Browser Use generation must show
  the new hero avoiding all currently visible trusted background URLs while an
  unused pool candidate remains.

## EJ-021: Booking CTA stayed as text in trusted hero generations

- **Observed:** A content-only presentation brief generated a relevant photo
  hero, but `Забронируйте диагностическую встречу` was not rendered as a native
  button. The page also retained older duplicate test roots, three of which
  reused the original `side-view` photo and made the image rotation look broken.
- **Root cause:** The hero content-unit parser and requested-CTA normalizer did
  not recognize `забронируйте`/`забронировать`. Trusted preservation also
  refused to append a CTA when the source template had no button. Historical
  duplicate roots were already saved page content, not the current rotation
  candidate; the current generated roots used three different photo URLs.
- **Fix:** v02.10.87 adds booking verbs to all shared CTA detection paths,
  captures `preserve_style` correctly in the recursive walker, and always
  materializes a missing CTA button inside the content shell. Existing trusted
  button styling remains unchanged.
- **Regression status:** Local contract tests and PHP lint must pass. Fresh
  Browser Use must show a labeled native CTA in the new hero and a background
  URL distinct from the immediately preceding generated root where the five
  image pool allows it.

## EJ-020: Content-only hero offer fell into the shared Vocario Hero root

- **Observed:** A presentation-school brief with a booking CTA was classified
  as benefits, so the library path could select a Vocario Hero root with the
  same default speaker photo and skip hero normalization.
- **Root cause:** Hero intent required an explicit hero phrase or a narrow CTA
  phrase list; `забронируйте` and presentation/leadership context were missing.
- **Fix:** v02.10.86 recognizes content-only course/presentation offers with
  booking CTAs as hero and makes benefits root matching select `Why Choose Us`.
- **Regression status:** Local contract tests and PHP lint pass. Fresh Browser
  Use must show the new prompt using hero normalization and a background URL
  different from the prior hero where the trusted photo set allows it.

## EJ-018: Trusted hero reused the same background image across generations

- **Observed:** v02.10.83 produced a visually coherent trusted Vocario hero,
  but repeated content-only generations reused the same speaker photo.
- **Root cause:** The trusted hero normalizer preserved the library root
  `background_image` while retrieval selected the same Vocario source template.
- **Fix:** v02.10.84 rotates a relevant photo from the bundled Vocario image
  set using the per-generation variation seed, while preserving arbitrary
  user-owned media.
- **Regression status:** Local tests and PHP lint must pass. Fresh Browser Use
  must show distinct trusted hero background URLs/screenshots across different
  prompts and one relevant background per hero.

## EJ-019: Vision rejected a populated photo hero on false missing-media findings

- **Observed:** v02.10.84 showed a populated hero with a relevant photo and a
  labeled CTA, but Gemini Vision reported a flat gray background and an empty
  button, starting unnecessary bounded repair passes.
- **Root cause:** The Vision prompt had only text length/widget count and could
  not distinguish a screenshot-capture limitation from absent media or CTA.
- **Fix:** v02.10.85 sends scoped factual counts for visible media, headings,
  and labeled CTAs; contradictory missing-media/empty-CTA findings are
  discarded, while genuine major/critical layout findings remain blocking.
- **Regression status:** Local tests and PHP lint must pass. Live Browser Use
  must show a populated hero, distinct relevant background media, and no repair
  loop caused solely by a contradicted missing-media or CTA finding.

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

## EJ-017: Editor runtime was not ready for repair root reconciliation

- **Observed:** v02.10.82 produced a visually coherent photo hero with a
  cobalt CTA, but after Vision rejected the run and rollback completed, the
  Elementor canvas still showed two repeated hero roots. Preview iframe
  inspection was also intermittently unavailable while the editor was
  reloading.
- **Root cause:** The retry gate used the chat readiness flag, which can turn
  true before Elementor exposes `$e` and its preview container. Root cleanup
  then returned early, and iframe reload alone retained the stale editor
  model.
- **Fix:** v02.10.83 waits for the native Elementor runtime before repair
  insertion, explicitly reconciles all current roots to the saved empty state
  before retry and final rollback, then refreshes the iframe.
- **Regression status:** Local contract tests and PHP lint must pass. Fresh
  Browser Use must prove one root after a successful generation and zero roots
  after a failed bounded Vision rollback.

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

## EJ-033: Repeatable provider layout split into an empty side card

- **Observed:** Fresh v02.10.99 benefits generation produced one large
  heading-only card in the left branch and three dense cards in the right
  branch. AI Vision scored it 68 and reported unbalanced whitespace; the
  editor screenshot also showed the selected Elementor canvas dimming the
  unselected cards.
- **Root cause:** The bento normalizer only sized direct child containers. It
  did not flatten a nested two-branch response when one branch contained a
  single heading card and the other contained the actual repeatable cards.
- **Fix:** v02.11.00 detects that sparse/dense branch shape for repeatable
  archetypes, collects leaf card containers, removes a heading-only card that
  duplicates the requested title, and rewrites the grid as one transparent
  Flexbox container with responsive card widths.
- **Regression status:** Local contract, PHP lint, and diff checks are
  required. Fresh live benefits generation must prove the repair step and
  pass screenshot, scoped JSON/DOM, Vision/design-taste, and Impeccable
  review before the remaining block matrix is accepted.

## EJ-034: Fallback card repeated the section heading

- **Observed:** Fresh v02.11.00 content-only benefits generation kept the
  section title in the first card heading while the first requested benefit
  remained in that card body.
- **Root cause:** Fallback content fidelity treated the section title as the
  first missing unit and then assigned it to the first repeatable card heading
  without checking the already populated section heading.
- **Fix:** v02.11.01 adds a shared repeatable-card repair pass that detects the
  duplicate, promotes the card's requested body copy to its heading, and moves
  the next requested unit into the body.
- **Regression status:** Local contract and PHP syntax checks pass; a fresh
  Browser Use benefits generation must confirm the duplicate is gone before
  the remaining block matrix is accepted.
