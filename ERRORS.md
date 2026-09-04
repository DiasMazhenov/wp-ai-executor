# Error Journal

This file records confirmed failures and their regression status. Read it
before making a new change to the plugin.

- **Live acceptance (2026-09-04, v02.11.48):** A fresh horizontal timeline
  generation wrote successfully through the fallback chain (primary wildcard
  timed out twice; the bounded 20s primary plus 30s fallback window reached
  the fallback pool). The rendered rail now shows numbered markers aligned
  above their own cards with continuous horizontal connector lines between
  them; the vertical connector remnant is gone. Screenshot captured in the
  editor; AI Vision review was unavailable (Gemini 503) and remains an
  advisory gap. The horizontal family is visually accepted in the editor
  canvas.

## EJ-085: The fallback model never received a response window on hanging routes

- **Observed (2026-09-04, live editor, plugin v02.11.46):** With the
  fallback model configured (`google/gemma-4-31b-it:free`), two horizontal
  timeline sends still ended in the client transport timeout. The
  v02.11.44 server-side fallback attempt never produced a usable response.
- **Root cause:** The server spent up to 45 seconds waiting for the hanging
  `openrouter/free` wildcard before starting the 30-second fallback attempt,
  a 75-second worst case that exceeds the editor's 55-second client abort.
  The fallback attempt was therefore still in flight when the client gave
  up, and its result never reached the chat.
- **Fix:** v02.11.47 bounds the primary attempt to 20 seconds whenever a
  fallback model is configured, so 20 + 30 fits inside the 55-second client
  abort. Without a fallback the 45-second primary timeout is unchanged.
- **Regression status:** Local lint and contract tests pass. Live
  acceptance: a hanging primary route must visibly reach the fallback
  attempt and either succeed or return a real provider error within the
  client window.

## EJ-084: Dashboard fallback-model field rendered empty after every save

- **Observed (2026-09-04, live dashboard, plugin v02.11.45):** The site owner
  entered a fallback model id and saved, but after the settings reload the
  field rendered empty again, so the opt-in fallback could not be visibly
  confirmed or switched.
- **Root cause:** `wpae_update_llm_settings()` stored `fallback_model`
  correctly, but `wpae_llm_get_settings()` — the array the dashboard renders
  from — did not include the key, so the input always received an empty
  value attribute.
- **Fix:** v02.11.46 returns `fallback_model` plus a bounded
  `fallback_model_history` (10 entries, current first) from
  `wpae_llm_get_settings()`, maintains the history on each save, and renders
  the field as a combo input with a `datalist`, so previously entered models
  are selectable while new ids remain typeable.
- **Regression status:** Local lint and contract tests pass. Live check: the
  saved value must survive a settings page reload, and the datalist must
  offer previously entered ids.

## EJ-083: Provider returned error persisted after the OpenRouter schema fix; upstream reason was hidden

- **Observed (2026-09-04, live editor `post=4556`, plugin v02.11.39):** After
  EJ-082 deployed, a fresh content-only left-timeline send still failed on
  both the initial attempt and the reload retry with `Provider returned
  error` on `google/gemma-4-31b-it:free`. The schema-valid `max_tokens` body
  was therefore not the only blocker, and the chat could not show why the
  upstream refused the request.
- **Root cause:** OpenRouter reports the upstream failure reason in
  `error.metadata.raw` (plus `provider_name`), but
  `wpae_llm_provider_error_message()` returned only `error.message`, so the
  actionable part of the failure was discarded before the chat displayed it.
- **Fix:** v02.11.40 surfaces a bounded (300-char), sanitized excerpt of
  `error.metadata.raw` with the provider name in the provider error message.
  The extraction feeds the chat error line, the structured-route retry
  detection, and the response diagnostics, so the next failure shows the real
  upstream reason without exposing credentials or raw payloads.
- **Live confirmation (2026-09-04, v02.11.40):** The next editor-chat send
  exposed the concrete upstream reason in the chat:
  `Provider returned error [Google AI Studio: google/gemma-4-31b-it:free is
  temporarily rate-limited upstream. Please retry shortly, or add your own
  key to accumulate your rate limits]`. The root cause of the whole EJ-081
  failure family is shared-free-pool upstream rate limiting, not the plugin
  configuration, the stored keys, or the action pipeline. Both EJ-081/EJ-082
  symptom reports and this diagnostic close with that conclusion. Resolving
  the blocker is an account/model choice: retry when the shared free pool
  recovers, attach an own provider key via OpenRouter integrations, or use a
  paid model id such as `z-ai/glm-5.3-flash`. The timeline/pricing live
  matrix remains blocked on provider availability only.
- **Mitigation (v02.11.41):** Rate limiting is now a distinct chat state:
  `wpae_llm_provider_rate_limited` carries a bounded `retry_after`, and the
  editor chat performs one delayed in-place retry without the full-editor
  reload instead of burning both attempts immediately. Fail-closed behavior
  is unchanged; a second failure stays a visible final error.

## EJ-082: OpenRouter requests carried the OpenAI-only max_completion_tokens field

- **Observed (2026-09-04, live editor `post=4556`, plugin v02.11.38):** After
  configuring OpenRouter with concrete model ids, every editor-chat request —
  including a tiny plain message — failed on both the initial attempt and the
  v02.10.22 retry with the sanitized message `Provider returned error`. The
  same uniform failure appeared on two independent free upstreams
  (`z-ai/glm-5.2:free`, `google/gemma-4-31b-it:free`), while the dashboard
  confirmed the key was stored and decryption worked.
- **Root cause:** The action/chat request body was built with the
  OpenAI-named field `max_completion_tokens` (llm.php request builder). The
  OpenRouter request schema uses `max_tokens`; `max_completion_tokens` is
  outside that schema. The bounded provider retry stripped
  `response_format` and `provider` but kept `max_completion_tokens`, so both
  attempts stayed schema-invalid for OpenRouter routes. Gemini-direct requests
  did not hit this path because the Gemini branch already removed the field.
- **Fix:** v02.11.39 extends `wpae_llm_prepare_provider_request_body()` with an
  OpenRouter branch that maps `max_completion_tokens` to schema-valid
  `max_tokens` before transport; the conversion applies to the initial request
  and to the structured-parameter retry because preparation runs first. The
  Gemini branch is unchanged, and the contract test now asserts the OpenRouter
  mapping.
- **Regression status:** Local PHP lint and the LLM chat contract test must
  pass before deployment. Live acceptance requires a fresh editor-chat send on
  an OpenRouter model that completes without the provider error, then the full
  left/alternating/horizontal timeline and pricing validation loop.

## EJ-081: Every editor-chat LLM request failed with a uniform provider error

- **Observed (2026-09-04, live editor `post=4556`, plugin v02.11.38):** Three
  separate chat sends — two content-only left-timeline briefs and one simple
  advisory question — each failed twice (initial request plus the forced-reload
  retry) with the sanitized message `LLM-провайдер вернул ошибку.: Провайдер
  вернул ошибку.`. No structured JSON was produced, no Elementor write
  happened, and the fail-closed behavior was correct on every attempt. The
  page reload between attempts was observed (preview `ver` changed), so the
  transport retry path itself worked as designed.
- **Update (2026-09-04, after switching the LLM provider to OpenRouter
  `openrouter/free`):** Two content-only left-timeline sends each failed twice
  again, but with a different failure mode:
  `LLM-провайдер недоступен: превышено время ожидания ответа.` — the bounded
  55-second transport abort (v02.10.52) on both the initial request and the
  reload retry. The dynamic `openrouter/free` wildcard route is too slow for
  the large structured action prompt, consistent with the historical cURL
  error 28 timeouts on OpenRouter routes (v02.09.42) and the route's known
  structured-parameter rejections (v02.09.06). Fail-closed and retry behavior
  stayed correct; no write happened.
- **Root cause (confirmed direction):** The failure follows the provider
  configuration, not the request content. Gemini `gemini-3.5-flash-lite`
  fails fast with a uniform sanitized provider error on every request type,
  while OpenRouter `openrouter/free` fails slow with a transport timeout on
  the large action prompt. The generation/action pipeline itself was never
  reached in either configuration.
- **Fix:** Pending. Configure one concrete valid model id for the LLM
  endpoint — the curated Gemini model selector or a specific OpenRouter
  model, not the dynamic `/free` wildcard — then send one bounded test
  request. Read the server-side provider-failure log (file/line are logged
  since v02.10.56) before changing any transport code. Do not retest
  generation until the provider answers at least one request.
- **Regression status:** Live transport behavior confirmed correct across
  both providers (one reload retry, sanitized errors, no silent success, no
  partial write). The provider/model configuration itself remains the open
  blocker for the live timeline/pricing matrix.

## EJ-079: Timeline rows were empty editor scaffolding instead of a timeline

- **Observed:** The fresh v02.11.36 alternating screenshot showed large empty
  spacer containers on alternating sides, a border around the entire row, and
  short connector pills separated from the content. The chat trace called it
  an alternating timeline, but the rendered editor composition looked like
  unfinished empty columns. The user rejected all timeline variants.
- **Root cause:** The shared builder used three children for alternating rows
  (`spacer -> rail -> content`) while applying the card surface to the parent
  step. The spacer was a real empty Elementor container, so Elementor exposed
  it as an insertion zone and the row border visually disconnected the rail
  from the actual step content. The same parent-card assumption also made the
  horizontal family dependent on a surface that was not semantically owned by
  its content.
- **Fix:** v02.11.37 makes every step row a transparent Flex layout and moves
  the bordered surface to the populated content container. Alternating side
  containers now contain a subtle `ЭТАП NN` metadata heading instead of being
  empty. Horizontal steps explicitly restore their own card surface while
  their inner content stays transparent. This keeps the marker rail and
  content hierarchy consistent across all families without Icon Box widgets.
- **Regression status:** Local lint, contract tests and diff checks pass.
  Fresh deployed left, alternating, and horizontal generations still require
  scoped JSON/DOM, screenshot, Vision/design-taste, Impeccable, prompt
  comparison, responsive check and no rollback.

## EJ-080: Empty connector containers rendered as editor artifacts

- **Observed:** After the v02.11.37 row-surface fix, the fresh left timeline
  screenshot still showed each connector as a rounded pill with an Elementor
  `+` overlay. The timeline content was present, but its connecting line still
  looked like an empty editor drop zone rather than a deliberate rail.
- **Root cause:** The connector remained a container with no child widget. Its
  background was therefore exposed through Elementor's empty-container editing
  chrome, and the same vertical connector geometry was reused by horizontal
  timelines.
- **Fix:** v02.11.38 puts a visible native HTML line inside every connector
  container. The horizontal builder rewrites that child into a 2px horizontal
  rule while vertical families keep the vertical line. No connector container
  is empty, and all families still use native Flex containers.
- **Regression status:** Local lint, contract tests and diff checks are
  required. Fresh deployed left, alternating, and horizontal generations must
  be checked with scoped JSON/DOM, screenshots, Vision/design-taste,
  Impeccable, prompt comparison and no rollback.

## EJ-078: Pricing visual grammar reintroduced placeholder shells

- **Observed:** The fresh v02.11.35 pricing screenshot still showed large
  empty media-style areas and `01`/`02`/`03` number shells above the actual
  tariff content. Vision scored it 85, but the manual composition review
  rejected the result because the cards looked like incomplete media cards.
- **Root cause:** The v02.11.35 pricing builder ran before the shared bento and
  visual-grammar passes. Those generic passes interpreted the new pricing grid
  as a repeatable card group and wrapped its direct cards with the generic
  placeholder/number variation.
- **Fix:** v02.11.36 rebuilds pricing at the final pre-write boundary after all
  shared normalizers. It uses direct native Flex pricing cards and explicit
  responsive typography, so pricing has no generic wrapper or placeholder
  insertion point.
- **Regression status:** Local lint, contract tests and diff checks are
  required. Fresh deployed pricing validation must prove the direct
  `root -> grid -> 3 cards` DOM hierarchy with screenshot, Vision/design-taste,
  Impeccable, prompt comparison and no rollback.

## EJ-077: Pricing pairs still entered a fragmented library composition

- **Observed:** The v02.11.34 live pricing probe parsed the three tiers, but the
  rendered result still promoted the first tier into a large heading and placed
  the other tiers in a separate right-side stack. A provider timeout also made
  that route depend on the generic fallback path.
- **Root cause:** Parsing pairs was not enough: the library adapter still chose
  its existing content zones rather than a pricing-specific composition boundary.
  The fallback post-processor could also remap a canonical pricing grid as a
  generic card group.
- **Fix:** v02.11.35 uses one shared `wpae-pricing-composition` native Flex root
  with an explicit `wpae-pricing-grid` and one `wpae-pricing-card` per parsed
  tier. Amounts use `wpae-pricing-price`; the generic fallback mapper skips
  canonical pricing cards, so both provider and fallback routes preserve the
  same card geometry.
- **Regression status:** Local lint, contract tests and diff checks are next.
  Fresh v02.11.35 deployment still requires scoped JSON/DOM, screenshot,
  Vision/design-taste, Impeccable review, prompt comparison and no rollback.

## EJ-076: Single-line pricing briefs collapsed into one heading

- **Observed:** A live pricing request with three tiers was written as a
  fragmented library layout. The first sentence became a long heading, while
  the tier cards were not populated as parallel pricing units. Vision scored
  the result 68 and found a major pricing-composition failure; bounded repair
  then exhausted its retry path.
- **Root cause:** `wpae_llm_extract_pricing_content()` only parsed newline-
  separated `label — amount` records. A normal one-paragraph brief with
  sentence-separated tiers produced fewer than two pairs and incorrectly
  entered the generic narrative library adapter.
- **Fix:** v02.11.34 parses repeated label/amount boundaries across one
  paragraph before the legacy line parser. The existing library adapter can
  therefore bind each tier to its own card instead of assigning the whole
  brief to the first heading.
- **Regression status:** Local lint, contract tests and diff checks pass. A
  fresh deployed pricing generation still requires scoped JSON/DOM, screenshot,
  Vision/Impeccable review, prompt comparison and no rollback.

## EJ-074: Alternating timeline selected marker and content by position

- **Observed:** Live alternating output rendered the first and third cards on
  the right, but the middle step's content column collapsed to about 79px and
  the opposite side showed an empty 408px spacer. The screenshot looked sparse
  and disconnected even after the generic repair pass reported success.
- **Root cause:** `wpae_llm_build_process_timeline()` selected children with
  `content_index`/`rail_index`. The base step order is `[rail, content]`, so
  odd-step reordering selected the rail as content and the content as rail.
- **Fix:** v02.11.32 finds the two children by their semantic CSS classes before
  applying widths and alternating order. Indexes remain only as a defensive
  fallback for malformed provider trees.
- **Regression status:** Local PHP lint, contract tests, and diff checks are
  required. Live alternating, left, horizontal, and non-process generations
  still require scoped JSON/DOM, screenshot, Vision/Impeccable review, and
  prompt comparison after WP Pusher reports v02.11.32.

## EJ-075: Horizontal timeline badge shared the cards row

- **Observed:** Live horizontal generation was rolled back after Vision found
  a large empty region beside the `ПРОЦЕСС` badge and the step cards started
  too far to the right. The generated trace claimed a horizontal Flex timeline,
  but the rendered composition was unbalanced.
- **Root cause:** The horizontal builder changed the timeline root to a row
  while the generated badge was merged as another root child. The badge became
  a narrow first column instead of a full-width heading above the cards.
- **Fix:** v02.11.33 keeps the timeline root as a column and wraps horizontal
  steps in one full-width `wpae-process-track` row. Mobile uses the same track
  as a single column; extraction reads the wrapper instead of treating it as a
  step.
- **Regression status:** Local checks pass after the patch. A fresh deployed
  horizontal generation still requires scoped JSON/DOM, screenshot,
  Vision/Impeccable review, prompt comparison, and no rollback.

## EJ-072: Process intent leaked from content into unrelated blocks

- **Observed:** The live screenshot audit of the generated page found six
  historical roots where formats blocks contained process rows, empty marker
  containers, duplicated shells, and concatenated card copy. The block request
  boundary allowed a body-only word such as «этап» to select the process
  archetype.
- **Root cause:** Archetype scoring treated any process vocabulary in the full
  brief as sufficient, and the execution path repeated a broad process regex.
  There was no shared intent-head boundary and «форматы» was not a catalog
  alias for the pricing/formats fallback.
- **Fix:** v02.11.30 centralizes process intent detection to the command head,
  adds «формат»/«вариант» to the formats catalog, suppresses process body-only
  scores, and reapplies the process contract after the generic Flex contract.
- **Regression status:** Local checks pass. Fresh live process and formats
  generations still require scoped JSON/DOM, screenshot, Impeccable review,
  prompt comparison, and deployment-version confirmation. Existing page roots
  remain historical test contamination and are not deleted by this fix.

## EJ-073: Flex timeline content overflowed its row

- **Observed:** Live Impeccable/Talon inspection found process content extending past the 1010px canvas. The content container had `width: 100%` together with Flex-grow while sharing a row with the fixed marker rail.
- **Root cause:** The process builder encoded a fixed percentage width for a growable sibling, so the width was calculated before the rail and gap were accounted for.
- **Fix:** v02.11.31 removes explicit width fields from stretch/grow timeline shells and gives the parent timeline controlled inner padding. Recheck rendered DOM overflow after every process generation.
- **Regression status:** Source and local contract checks pass; live left, alternating, and horizontal variants still require screenshot plus Impeccable review.

## EJ-070: Horizontal timeline steps wrapped into a second row

- **Observed:** The live horizontal timeline screenshot showed steps 01-03 on
  the first row and step 04 wrapped below. The rail was no longer perceived as
  one continuous horizontal timeline.
- **Root cause:** Every desktop step used a fixed 24% width while also adding
  card padding and flex gaps; `_flex_shrink` was zero, so the browser wrapped
  the final card instead of resolving the small width overrun.
- **Fix:** v02.11.26 derives a width budget from the number of steps and allows
  horizontal cards to shrink. Mobile remains a single vertical stack.
- **Regression status:** Local checks pending. Re-test horizontal, alternating,
  and left variants with scoped JSON, screenshot, Impeccable review, and prompt
  comparison before acceptance.

## EJ-071: Final variation pass reintroduced bento classes into timelines

- **Observed:** After v02.11.26, the live central timeline still ended as a
  bento-like grid even though the earlier process contract reported an
  alternating timeline.
- **Root cause:** The process contract ran before `wpae_llm_execute_action()`;
  the later visual-variation pass could add `wpae-bento-grid` back to the
  process root and its steps before preview/write.
- **Fix:** v02.11.28 passes the original brief into the shared execution
  boundary, detects process requests from that brief as a second guard, and
  re-applies the process contract after variation immediately before final
  normalization and the Elementor write.
- **Follow-up:** v02.11.29 preserves the current design-system marker classes
  and ID when that final contract rebuilds the timeline root, while removing
  stale bento/system markers that would invalidate the replacement root.
- **Regression status:** Local checks pending. Re-test left, alternating, and
  horizontal variants with scoped JSON, screenshot, Impeccable review, and
  prompt comparison before acceptance.

## EJ-069: Post-processing rebuilt a timeline as a bento grid

- **Observed:** The live central-timeline run reported an alternating timeline,
  but its final scoped JSON contained a left timeline root, a generated shell
  with `wpae-bento-grid`, and process steps carrying the same grid class. AI
  Vision correctly rejected the rendered three-card format grid.
- **Root cause:** Provider repair output could contain mixed process and bento
  trees. The final visual-grammar and bento passes did not enforce one canonical
  process root after those transformations.
- **Fix:** v02.11.25 adds a final process-timeline contract boundary that
  rebuilds the complete process root in the requested layout, preserves the
  generated badge, skips generated wrappers while extracting steps, and stops
  the generic bento pass from mutating process trees.
- **Regression status:** Local PHP lint, contract tests, and diff checks pass.
  Re-run all three timeline families with screenshot -> Impeccable -> prompt
  comparison before acceptance.

## EJ-068: Instruction-only timeline fallback was rejected by semantic audit

- **Observed:** The live `сделай центральный чередующийся таймлайн` request fell
  back to a deterministic process tree, then was rejected as a semantic-plan
  failure before writing. The brief contained a layout instruction but no
  user-supplied content.
- **Root cause:** The final semantic audit compared built-in scaffold copy with
  an empty content plan and treated any matching archetype terms as invented
  user meaning.
- **Fix:** v02.11.24 scopes semantic-conflict checks to explicit content. An
  instruction-only brief still validates native widgets, media, CTA policy,
  and repeatable structure, while its selected fallback scaffold is allowed.
- **Regression status:** Local PHP lint, contract tests, and diff checks pass.
  Live validation is required for left, alternating, and horizontal timeline
  requests with screenshot -> Impeccable -> prompt comparison.

## EJ-067: Timeline layout selector crashed on a null live value

- **Observed:** The live `сделай центральный чередующийся таймлайн` retry
  failed before writing with `wpae_llm_build_process_timeline(): Argument #3
  ($layout) must be of type string, null given`.
- **Root cause:** The shared timeline builder accepted a nullable value from
  the live normalization path but declared a non-nullable parameter, so its
  internal fallback to `left` could never run.
- **Fix:** v02.11.23 makes the builder boundary nullable and keeps the existing
  allow-list coercion to `left` for null or unknown layouts. This protects all
  timeline callers, including stale or partially deployed PHP paths.
- **Regression status:** Local PHP lint, contract tests, and diff checks pass.
  Re-run alternating and horizontal live generations after WP Pusher confirms
  v02.11.23, then perform screenshot -> Vision/Impeccable -> prompt review.

## EJ-066: Process fallback exposed only one timeline composition

- **Observed:** The dedicated v02.11.21 fallback prevented the old bento-grid
  regression, but every process request still rendered the same left-sided
  vertical timeline. The original timeline reference contains multiple layout
  families, so there was no way to test or select the other compositions.
- **Root cause:** `wpae_llm_build_process_timeline()` had no layout contract;
  process normalization always rebuilt a column root, and the visual-variant
  pass hardcoded process children to 100% width and a column direction.
- **Fix:** v02.11.22 adds one layout selector and extends the shared builder for
  left vertical, centered alternating vertical, and horizontal Flex timelines.
  The selector is driven by explicit natural-language layout terms, the
  horizontal root uses responsive wrapping, alternating steps use a protected
  spacer rail, and the final variant pass preserves the selected family.
- **Regression status:** Local checks pass. Live testing is pending deployment
  of v02.11.22 and requires one screenshot plus Vision/Impeccable review for
  each family. A variant is not accepted from JSON or trace alone.

## EJ-065: Process fallback was converted into a bento grid

- **Observed:** The live `сделай таймлайн` result rendered a 2x2 card grid
  instead of a connected vertical timeline. Vision correctly reported that
  the requested process structure was missing; bounded repairs kept the wrong
  composition and the result was rolled back.
- **Root cause:** The `process` archetype reused the generic bento fallback,
  then shared repeatable-layout and visual-variant normalizers were allowed to
  rewrite its children. The pipeline had no native timeline grammar or
  structural invariant for markers and connectors.
- **Fix:** v02.11.21 builds a dedicated native Flex timeline with one column
  root, one row step per item, a numbered marker rail, connector containers,
  and ordinary heading/text-editor content. Process is excluded from bento,
  repeatable-layout repair, and visual-variant conversion. The final trace
  records the timeline rebuild and confirms that Icon Box is not used.
- **Regression status:** Fixed in `v02.11.21`. WP Pusher and the installed
  plugin report the new version. Browser Use verified the process trace,
  native Flex structure, scoped generated JSON, fresh screenshot, and final
  AI Vision score 82 without critical or major findings. Impeccable review
  found only a minor editor-mode multi-border impression; no 2x2 bento grid
  or Icon Box was present in the generated timeline.

## EJ-063: Vision findings were not sent as a separate agent prompt

- **Observed:** Three live `сделай таймлайн` cycles wrote a badge and the
  heading `СДЕЛАЙ ТАЙМЛАЙН` instead of a timeline. AI Vision correctly scored
  the result 45/40/40 and reported the missing timeline and excessive empty
  space, but the next repair request carried the findings only in the editor
  context flag. The targeted repair path discarded the findings entirely.
- **Root cause:** The LLM request contained the original user message but no
  distinct supplemental user message containing the Vision report. The PHP
  handler also read the findings only when `vision_regenerate` was true, while
  the frontend sends them for both full regeneration and targeted repair.
  Short imperative briefs were also treated as content units, so the
  deterministic fallback could use the command as a heading.
- **Fix:** v02.11.20 builds a bounded Vision feedback prompt, sends it as a
  separate LLM user message after the original brief, includes it in bounded
  JSON repair requests, and exposes a `vision_feedback_prompt` execution step.
  It accepts findings for both repair modes, treats `таймлайн`/`timeline` as
  the process archetype, and excludes command-only briefs from content units.
- **Regression status:** Local contract tests and PHP lint pass. Live
  acceptance is pending deployment of v02.11.20; the editor was still
  reporting v02.11.16 during diagnosis. The next live test must confirm the
  separate prompt step, timeline structure in scoped JSON/DOM, and a fresh
  screenshot reviewed through Vision/Impeccable.

## EJ-064: WP Pusher could not add the repository for live deployment

- **Observed:** Because `WP AI Executor` was absent from the WP Pusher plugin
  list, the `Install New Plugin` form was opened with GitHub repository
  `DiasMazhenov/wp-ai-executor`, branch `main`, `Push-to-Deploy`, and `Link
  installed plugin` enabled. WP Pusher returned `Загрузка не удалась` and did
  not add the repository.
- **Likely causes:** The site has previously reported zero free disk space,
  and the configured GitHub token may be invalid or unavailable to WP Pusher.
  The public repository and branch are correct; the UI did not expose a more
  specific error.
- **Regression status:** The failed automated add was completed manually by
  the user; WP Pusher now lists the plugin and live Elementor reports
  v02.11.20. The fresh timeline test still failed visual acceptance: Vision
  scored 82 and then 68, identified a 2x2 card grid instead of a connected
  timeline, received the supplemental prompt twice, and rolled back after
  two bounded repairs. Do not claim the timeline composition is fixed yet.

## EJ-051: Quality-gate additions accepted generic sparse provider trees

- **Observed:** After v02.11.14-v02.11.15, content-only pricing, FAQ, cases,
  process, and contact requests could write a formally valid Elementor tree
  while repeated content was collapsed into one text zone or a sparse shell.
  Flex normalization then made the wrong structure responsive without making
  it semantically correct.
- **Root cause:** `wpae_llm_content_plan_audit()` checked widget count,
  forbidden Icon Box, media, and CTA text, but did not require separate
  populated repeatable units. Library acceptance used the same incomplete
  audit, so a bad adapted composition could be selected before write.
- **Fix:** v02.11.16 records FAQ pairs, counts populated leaf containers or
  accordion tabs, rejects collapsed repeatable structures before normalization,
  checks adapted library blocks with the same contract, and falls back to a
  deterministic archetype builder. Content-only buttons are removed unless
  explicitly requested.
- **Regression status:** Local contracts and PHP lint must pass. Browser Use
  must show distinct requested units, bounded whitespace, native Flex structure,
  no invented CTA, and the screenshot -> AI Vision -> Impeccable/design-taste
  -> prompt comparison loop.

## EJ-052: Temporary Vision failure rolled back a successful generation

- **Observed:** A full-block generation with a successful Elementor write was
  rolled back when the advisory screenshot review returned `Failed to fetch`.
  The user saw no result even though the write path had completed.
- **Root cause:** The new client quality-gate branch treated `vision_unavailable`
  as a blocking full-generation failure instead of distinguishing unavailable
  review evidence from a received critical/major quality finding.
- **Fix:** v02.11.16 preserves the saved generation and reports that Vision
  review is unavailable and requires manual screenshot/Impeccable validation.
  Received quality failures still trigger bounded repair and rollback after
  the configured limit.
- **Regression status:** Contract test covers the client branch. Live Browser
  Use must verify that a successful write remains visible when Vision is
  unavailable, while a real failed Vision report still rolls back.

## EJ-053: Pricing fallback split descriptions after the amount

- **Observed:** A content-only pricing prompt reached deterministic fallback
  with tier names and prices, but descriptions after the first period were
  dropped from the cards.
- **Root cause:** The generic dash parser split each line at sentence-ending
  periods before it could bind the amount and following description to one
  pricing tier.
- **Fix:** v02.11.16 uses a pricing-aware line parser before generic labeled
  content extraction, preserving the full amount-plus-description pair for
  every tier.
- **Regression status:** Re-run the live pricing prompt and verify every tier
  title, price, description, native Flex card, and screenshot review.

## EJ-054: Pricing request routed through team composition

- **Observed:** A pricing brief containing the tier «Команда» produced a root
  that mixed `КОМАНДА`, team copy, and pricing cards. The live page accumulated
  several such roots during repair/testing.
- **Root cause:** The content-only archetype detector checked sentence-start
  matches for `команда` before checking currency and tariff markers. A tier
  label after a period therefore won over the explicit pricing intent.
- **Fix:** v02.11.17 checks pricing markers before the team rule and adds a
  source-order regression assertion.
- **Regression status:** Deploy, generate a clean pricing block, inspect its
  scoped JSON/DOM and screenshot, then run Vision/Impeccable review. Existing
  historical test roots are not evidence about the new root.

## EJ-055: Vision accepted a mixed root after seeing valid pricing cards

- **Observed:** The latest pricing root contained `КОМАНДА`, `Наша команда`,
  and team-specialist copy around valid pricing cards. Vision scored the
  scoped pricing area 85 even though the full generated root was semantically
  wrong.
- **Root cause:** Content fidelity required requested phrases to be present,
  but did not reject unrelated template copy. The rendered review therefore
  had valid prices as evidence while the wrong team composition remained.
- **Fix:** v02.11.18 replaces order-dependent archetype priority with one
  weighted catalog of headline/body signals and removes repeatable labels from
  those signals. The same catalog provides strong semantic markers to both
  preflight and final audits, which reject cross-archetype copy before Flex
  normalization and Elementor write. The plan now exposes bounded archetype
  scores for diagnostics.
- **Regression status:** Local contract, lint, classification, and semantic
  audit smoke checks pass. Live acceptance is blocked until v02.11.18 is
  actually deployed; the current editor is still v02.11.16.

## EJ-056: WP Pusher did not deploy the architecture fix

- **Observed:** After pushing the repository, WP Pusher showed no `WP AI
  Executor` package row and displayed `Could not find plugin.` The supplied
  push hook returned HTTP 400 with an empty body; Elementor still reported
  v02.11.16.
- **Root cause:** The live WP Pusher package/connection is not registered or
  is pointing at a missing plugin package, so repository `main` and the live
  WordPress plugin are different releases.
- **Fix:** No blind browser or filesystem replacement was performed. The
  release is committed and pushed, while deployment remains explicitly
  pending re-registering the WP Pusher package.
- **Regression status:** Do not call live generation, screenshot, Vision, or
  Impeccable acceptance successful until the editor reports v02.11.18 and a
  fresh generation is inspected in its scoped JSON/DOM and screenshot.

## EJ-057: New block types still collapse or misroute on stale live runtime

- **Observed:** Browser Use tests on live `v02.11.16` for FAQ, testimonials,
  process, portfolio, CTA, carousel, about, and mega-menu produced no clean
  acceptance. FAQ/portfolio/CTA collapsed content into sparse shells; process
  was rejected after library adaptation; carousel became a service grid; about
  repeated copy across cards; mega-menu became a services grid. Testimonials
  were written, but author headings dominated the quotes and overflowed the
  editor viewport.
- **Root cause:** The live site does not contain the catalog and semantic audit
  from `v02.11.18`. The old runtime falls back to generic compositions, and
  its Vision report can score a valid crop instead of the requested block type.
  Existing historical roots also contaminate the page during retries.
- **Evidence:** Every test used content-only prompts, DOM inspection, a
  post-generation screenshot, and Impeccable visual review. Vision scores
  ranged from 60 to 85 and contradicted visible clipping, sparse whitespace,
  missing structure, or wrong archetypes.
- **Regression status:** Keep open until WP Pusher deploys `v02.11.18`, then
  rerun the same non-Hero matrix with scoped JSON/DOM, screenshots, and the
  screenshot -> Vision/Impeccable -> prompt comparison loop.

## EJ-058: Elementor editor emits unrelated console errors during live tests

- **Observed:** Live console contains `gtag is not defined`, Elementor
  `ToggleIcon` reading `parentElement` from null, REST `404 rest_no_route`, and
  `Angie is not available` while the editor is open.
- **Root cause:** These errors originate in the site/Elementor editor shell,
  not in the plugin's chat request. They remain relevant because they can
  interfere with preview refresh and make editor crashes look like generation
  failures.
- **Regression status:** Track separately. Before calling a future live test
  successful, verify that these errors do not appear during generation,
  refresh, screenshot, and rollback; fix them at their owning integration if
  they affect chat or canvas.

## EJ-059: Photo-content requests fail before media generation on stale live runtime

- **Observed:** A long portfolio brief with three distinct photo descriptions
  was sent twice through Browser Use. Both attempts displayed the prompt but
  returned `Поле message обязательно` and a `500 Internal Server Error` toast;
  no new image widget or block was written. A short «О студии» brief with one
  photo description triggered the provider-unavailable retry and then ended in
  `HTTP 500`. A third testimonials brief with three photo-avatar descriptions
  behaved identically after the retry. Screenshots and DOM checks confirmed
  that the canvas stayed on the previous section.
- **Root cause:** The live editor still runs `v02.11.16`, while the repository
  had already moved to `v02.11.18`; the live deployment therefore does not
  contain the current media/content contract. The chat handler also relied on
  `WP_REST_Request::get_param()` for a JSON request body, which is fragile on
  this hosting path and can produce the exact false `message required` result.
- **Fix:** `v02.11.19` reads `message`, `history`, and `context` from
  `get_json_params()` with a backward-compatible parameter fallback. This is
  an input-contract fix, not a visual workaround.
- **Regression status:** Open until the live editor reports `v02.11.19` and
  the photo matrix is rerun. Each block must produce a new image-bearing
  Elementor tree, a screenshot, and the screenshot -> Vision/design-taste /
  Impeccable -> prompt comparison loop. Do not accept `HTTP 500`, stale-canvas
  screenshots, or provider-only success as generation success.

## EJ-060: WordPress admin returns Internal Server Error during deployment check

- **Observed:** On 2026-09-03 a fresh Browser Use tab opened the WP Pusher
  plugins admin page and received `Internal Server Error` from the server.
  The existing hook-error tab remains failed as well. Fresh requests to the
  `wp-ai-executor` settings page and the ordinary WordPress
  `/wp-admin/plugins.php` control page returned the same error. The Elementor
  tab was kept open and was not used as a deployment workaround.
- **Root cause status:** Current evidence points to a site-wide
  WordPress/PHP/hosting or server-configuration failure, not specifically the
  WP Pusher screen or our plugin. The source has no WP Pusher-specific hooks.
  The generic 500 response cannot identify the exact fatal without server
  PHP/web-server logs or WordPress Recovery Mode details.
- **Regression status:** Open. Restore a baseline WordPress admin page first,
  confirm the live plugin reports `v02.11.19` (live currently reports
  `v02.11.16`), then rerun photo generation and its screenshot ->
  Vision/design-taste/Impeccable validation loop.

## EJ-061: Supplied server log shows duplicate debug constants and REST warnings

- **Observed:** Two supplied `mod_fcgid` excerpts now cover 2026-09-03
  02:20:37-02:21:09. The larger excerpt has 999 lines and repeats the same
  warning set across roughly 100 sequential request cycles, with new PHP
  processes and client ports. It reports `WP_DEBUG`, `WP_DEBUG_DISPLAY`,
  `WP_DEBUG_LOG`, and `SCRIPT_DEBUG` already defined in the site's `wp-config.php`
  (lines 93, 96, 99, 103, 109-111). It also repeats `Undefined array key 2`
  and `Trying to access array offset on null` from WordPress core
  `wp-includes/rest-api/class-wp-rest-server.php` lines 1841 and 1853.
- **Root cause status:** The duplicate-constant issue is a site configuration
  defect, not a definition in this plugin. The REST messages indicate a
  malformed or incompatible REST request and the repetition is consistent with
  a client retry loop, but the excerpts contain no request route, response
  status, stack trace, `Fatal error`, `Uncaught`, or `Parse error`; the larger
  excerpt also ends mid-line. They therefore cannot identify the 500 or
  attribute it to our plugin.
  `WP_DEBUG_DISPLAY` being enabled may additionally corrupt REST/AJAX output,
  but does not by itself explain a generic admin 500.
- **Next action:** On the server, keep one guarded definition of each debug
  constant, set `WP_DEBUG_DISPLAY` false, and collect the complete PHP/Apache
  error entry for the same 500 request plus its request URL/stack. Then compare
  the REST route with the caller before changing plugin code.

## EJ-062: Hosting quota reports zero free disk space

- **Observed:** The supplied hosting-panel screenshot reports `Свободное
  место, MB: 0`; it also shows 76 MB of logs, 111 MB of databases, and 965 MB
  of sites.
- **Root cause status:** Full disk is a high-confidence infrastructure cause
  for generic WordPress/PHP 500 responses because PHP and WordPress may be
  unable to write temporary files, sessions, cache, uploads, database data, or
  logs. It can also make the preceding 500 evidence incomplete. It does not by
  itself explain the malformed REST warnings or duplicate debug definitions.
- **Next action:** Download the current logs first, free several hundred MB or
  increase the hosting quota, then retest `/wp-admin/` and Elementor before
  changing plugin code. Remove only identified old logs/cache/backups; do not
  delete `wp-content/uploads` or plugin files blindly.

## EJ-047: Live runtime was behind the paste-ready JSON release

- **Observed:** On an earlier live check the Elementor editor showed
  `Выбрано: 1 объект`. The existing `Копировать JSON выделенного` action
  copied a valid `wpae-elementor-selection-v1` envelope of 1,130,957 bytes for
  post 4556. Browser Use clipboard reading works when this payload path runs.
  The new `Копировать JSON для вставки в Elementor` action was absent from the
  live DOM.
- **Live mismatch:** The chat DOM reported `v02.11.13`; the repository was
  `v02.11.15`. The live site was therefore running the previous release, not the
  paste-ready implementation.
- **Root cause:** The current evidence points to a stale live deployment. The
  earlier no-selection result was a transient empty-editor state, not proof
  that the old Elementor selection bridge is broken.
- **Fix:** Closed on 2026-09-03. Browser Use now reports live version `v02.11.15`
  and the new paste-ready button is present in the chat DOM.
- **Regression status:** Require a real selected Elementor element, the
  `Копировать JSON для вставки в Elementor` button in the DOM, parsed clipboard
  JSON with at least one element, and live version `v02.11.15`. Treat the
  existing `gtag`, Elementor checklist, MCP REST 404, and Angie console errors
  as separate external editor/site noise unless they change chat behavior.

## EJ-048: Content-only pricing brief fell back to an empty shell

- **Observed:** On 2026-09-03 a Browser Use generation for three pricing tiers
  (`Старт`, `Про`, `Команда`) wrote successfully, but AI Vision scored the
  result 65 because the tier cards and their prices/descriptions were missing or
  clipped. Two bounded repairs scored 45 and 60 and showed the same failure;
  the final version was rolled back.
- **Root cause:** The current fallback/template route can preserve the badge and
  category heading while losing the repeated pricing-card content and usable
  vertical composition. A successful Elementor update is therefore not enough.
- **Fix:** Open. Make the pricing semantic plan a hard pre-write contract and
  reject any fallback that does not contain all tier names, prices, descriptions,
  and visible repeated card roots before writing.
- **Regression status:** A live test must prove all three tiers in the rendered
  screenshot and scoped DOM, with native Flex containers and no clipping, then
  pass the screenshot -> Vision/design-taste -> prompt comparison loop.

## EJ-049: Content-only FAQ brief collapsed into one text block

- **Observed:** On 2026-09-03 a Browser Use FAQ generation preserved the text but
  rendered the questions and answers as one unstructured block. AI Vision scored
  68; the repair scored 62 with an empty/clipped CTA and sparse composition.
  A subsequent Vision request failed with `Failed to fetch`, so the repair was
  rolled back.
- **Root cause:** The fallback content path preserves FAQ copy without reliably
  creating repeated question/answer units and without a stable compact layout.
- **Fix:** Open. Add a FAQ semantic contract that requires one visible unit per
  question, answer text in each unit, and bounded spacing before Elementor write
  and Vision acceptance.
- **Regression status:** A live test must prove four distinct FAQ units, no empty
  CTA shell, native Flex structure, and a passing screenshot -> Vision/design-taste
  -> prompt comparison loop.

## EJ-050: Unsupported content-only block types reject or use a sparse shell

- **Observed:** On 2026-09-03 Browser Use tests for `Кейсы клиентов` and
  `Как мы работаем` were rejected before Elementor update because the fallback
  violated the semantic plan. `Контакты` was written, but AI Vision scored the
  first version 45 and the repaired version 68: the details were clumped at the
  bottom of a mostly empty canvas. Two bounded repairs ended with rollback.
- **Root cause:** Content-only briefs outside the tested Hero, benefits, Team,
  reviews, pricing, and FAQ paths are not mapped to stable semantic archetypes.
  The current behavior alternates between rejecting the command and emitting a
  generic sparse shell instead of failing before layout generation.
- **Fix:** Open. Register explicit semantic plans and native fallback builders
  for case-list, process-step, and contact-information blocks. If no plan is
  available, reject before provider write and do not emit a generic visual
  shell.
- **Regression status:** Live tests must prove the requested repeated units,
  content grouping, bounded whitespace, native Flex structure, and the full
  screenshot -> AI Vision -> Impeccable/design-taste -> prompt comparison loop.

## EJ-046: Generation quality checks were disconnected

- **Observed:** Provider output could drift from a selected library composition,
  use a forbidden Icon Box, or be written while editor Vision was still only
  advisory. A valid Elementor JSON response was therefore not enough to prove
  a usable design.
- **Root cause:** Retrieval, semantic content constraints, Flex normalization,
  and screenshot review did not share a single pre-write/final quality contract;
  the editor client explicitly ignored advisory Vision failures.
- **Fix:** v02.11.14 adds a semantic plan audit for explicit and invented CTAs,
  selected/adapted template fingerprints, a final native Flexbox contract,
  JSON/DOM context in Vision, and bounded rollback/repair for full-block
  generations.
- **Regression status:** Local contract, PHP lint and diff checks are required.
  Fresh Browser Use generation must prove one scoped root, preserved template
  anchors, native Flex containers, no Icon Box/overflow, and a passing
  screenshot -> Vision/design-taste -> prompt comparison review.

## EJ-045: Content-only Team brief inherited a detached CTA

- **Observed:** The live v02.11.12 Team result showed an extra black button
  containing the first member description beside the section H2, although the
  brief requested only member names and descriptions.
- **Root cause:** Shared CTA detection searched for action verbs anywhere in a
  sentence, so `Помогает выбрать ясный курс развития` was treated as a CTA.
  Library cleanup then preserved the source button because its text matched the
  requested content.
- **Fix:** v02.11.13 centralizes CTA-copy detection on explicit imperative
  starts and removes preserved-library buttons when no explicit CTA exists.
- **Regression status:** Contract test, PHP lint and diff checks pass. Fresh
  Browser Use generation must show no button, a large native H2, Team side
  padding, preserved portraits, no Icon Box widgets and a healthy iframe.

## EJ-041: Preserved Team cards leaked the forbidden Icon Box widget

- **Observed:** The fresh Team generation had the requested parent padding and
  an `H2` section heading, but all three live cards still rendered as
  `elementor-widget-icon-box` widgets.
- **Root cause:** Generated branches already converted Icon Box widgets, while
  the trusted-library badge pass rebuilt the root without passing its content
  through the shared native-widget converter.
- **Fix:** v02.11.09 runs the existing converter on preserved-library content
  before Team heading promotion and final root assembly. Icon Box content is
  retained as native heading/body widgets, and image siblings remain intact.
- **Regression status:** Recheck with Browser Use using screenshot ->
  Vision/design-taste -> prompt comparison -> scoped JSON and DOM. Require
  Team parent side padding, visible `H2`, no `icon-box` widgets, preserved
  portraits, no overflow and a healthy Elementor iframe.

## EJ-042: Team source spacing weakened the promoted heading

- **Observed:** The v02.11.09 live Team JSON had the correct side padding,
  `H2`, native card widgets and images, but the content shell retained
  `padding-top: 3rem` and the H2 inherited source gray `#9F9F9F`. Vision
  scored the result `70` and flagged excessive whitespace above the heading.
- **Root cause:** The source template's vertical spacing and title color were
  preserved after the badge pass promoted the heading, so the new semantic
  heading still looked visually disconnected and weak.
- **Fix:** v02.11.10 clears only the Team shell's top padding on desktop/mobile
  and assigns the promoted section H2 the dark text token.
- **Regression status:** Recheck with Browser Use using screenshot ->
  Vision/design-taste -> prompt comparison -> scoped JSON and DOM. Require
  badge above H2, compact heading flow, Team side padding, no `icon-box`,
  preserved images, no overflow and a healthy Elementor iframe.

## EJ-043: Team heading row kept excessive space before cards

- **Observed:** v02.11.10 fixed the gap above the heading, but Vision still
  scored the fresh Team result `68` and flagged the large gap between the H2
  and the member cards.
- **Root cause:** The trusted source heading row retained `padding-bottom: 70px`
  on desktop and `50px` on mobile after heading promotion.
- **Fix:** v02.11.11 detects the Team heading row and normalizes only its bottom
  padding to `24px` desktop and `20px` mobile.
- **Regression status:** Recheck with Browser Use using screenshot ->
  Vision/design-taste -> prompt comparison -> scoped JSON and DOM. Require
  compact badge/H2/card flow, parent side padding, no `icon-box`, preserved
  images, no overflow and a healthy Elementor iframe.

## EJ-044: Removing all Team shell top padding cramped the badge

- **Observed:** v02.11.11 removed the source's `3rem` top padding, but Vision
  scored the fresh Team result `68` and reported the badge and H2 were too close
  to the upper viewport edge.
- **Root cause:** The previous normalization treated the source's excessive
  top padding as an all-or-nothing value and left no intentional top rhythm.
- **Fix:** v02.11.12 uses compact Team shell top padding of `1.5rem` desktop and
  `1.25rem` mobile while retaining the reduced heading-to-card spacing.
- **Regression status:** Recheck with Browser Use using screenshot ->
  Vision/design-taste -> prompt comparison -> scoped JSON and DOM. Require
  balanced top rhythm, badge above H2, parent side padding, no `icon-box`,
  preserved images, no overflow and a healthy Elementor iframe.

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
