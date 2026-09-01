# WP AI Executor Context

## Current release

- Plugin: `v02.10.68`
- Guide: `v02.05.94`
- Repository: `DiasMazhenov/wp-ai-executor`
- Canonical API header: `X-AI-Key`
- Elementor writes: native Flexbox Containers only; legacy sections/columns may
  be imported for inspection but must be normalized before structured writes.

Release v02.10.68 fixes the deeper live-preview corruption found in the v67
Browser Use run. The Elementor editor kept roots from earlier generation and
Vision repair requests even after the server rollback, so each new template was
inserted beside stale duplicates and Vision correctly reported overlapping
headings, detached CTAs and broken hierarchy. Insert responses now include the
server snapshot's top-level root IDs; before realtime insertion the chat removes
only editor roots absent from that snapshot, then inserts the current template.
The existing request-scoped generated-root cleanup remains the repair fallback.
Contract coverage protects the root reconciliation and snapshot ID payload.

Release v02.10.67 fixes the live trusted-template failure found in the v66
Browser Use run. The Vocario Hero source has a photo background, but stale
materialized/token-mapped white fills could remain on structural child
containers and cover the photo and white text. Trusted-library preparation now
removes only non-card white surfaces when they have no deliberate card border,
radius or shadow, clears stale entrance-animation settings, and passes the
trusted marker through the token-map stage so source colors are not remapped.
The live request tracker also resets at the start of a new generation and
accumulates every root created in its repair chain, preventing duplicate Hero
roots during Vision repair or rollback. Contract coverage protects both
visual-state cleanup and request-scoped root tracking.

Release v02.10.60 fixes trusted library narrative adaptation: requested copy is
now assigned to one title, one body, one CTA and at most one instance of each
numeric, audience and confidence slot. Duplicate source copy widgets are
pruned instead of being filled by cycling fragments, while the template's
Flex geometry, palette, media and responsive composition remain untouched.

Release v02.10.61 fixes the remaining live Flex geometry failure in trusted
library compositions. A two-column row with one explicit percentage width and
one intrinsic-width child now receives a bounded complementary width, safe
flex shrinking, and responsive wrapping; negative horizontal source offsets are
removed without rewriting the source palette or vertical composition. Compact
icon-list accents are truncated to a short phrase while the complete requested
body remains in the main text widget.

Release v02.10.62 fixes the next live Vision failure: trusted template
decorative slots are no longer populated with a phrase already present in the
requested title or body. Repeated confidence, audience, and duration copy is
pruned instead of being rendered as overlapping duplicate text; unique source
slots remain eligible for adaptation.

Release v02.10.63 fixes the live render mismatch where Vocario templates
referenced source-only Elementor global color IDs. Known Vocario colors are
materialized into explicit native widget settings before insertion, so CTA
labels and template typography keep their intended contrast on the target site.

Release v02.10.64 fixes the next live Vision failure: failed reviews now
surface rollback errors instead of claiming success, and long library
headings receive bounded responsive sizes so the preserved composition remains
readable on the editor canvas. Its first attempt to suppress duplicate roots
by skipping live sync caused a blank canvas and was not retained.

Release v02.10.65 keeps first generation realtime in the open Elementor
editor, while tracking only the roots created by the current chat request.
Before a Vision repair or rollback those tracked roots are deleted through the
Elementor Commands API, then the replacement is inserted. This prevents old
sections from accumulating without hiding a new successful design from the
canvas.

Release v02.10.66 fixes the deeper visual causes found by comparing the
trusted Vocario source JSON with its live Elementor render. Third-party
`jkit_heading` conversion now carries the source focused/title color into the
native heading, so white hero type stays readable on the source image. Trusted
library typography clamps extreme `em` sizes and negative text margins without
rewriting the source composition. Generated bento cards use flexible Flex grow
and shrink behavior and no longer use the narrow `23%` four-column fallback.
The existing Vision repair loop and current-request root cleanup remain
enabled; Vision findings are now actionable against the actual source causes.

Release v02.10.42 explicitly marks all 393 containers in the 13 bundled Vocario
JSON fixtures with `container_type=flex`. Vocario retrieval now recognizes home,
course, event, blog, footer, form and 404 wording through the existing
archetype aliases, while the shared runtime normalizer remains the final guard.

Release v02.10.43 adds a post-grammar CTA pass: an explicitly requested
standalone call-to-action cannot remain a plain text-editor; it is replaced or
inserted as a styled native Elementor button inside the generated content shell.

Release v02.10.44 synchronizes all Vocario fixture SHA-256 values in the
CopyElement manifest with the Flex-converted JSON files. Without this, the
bundled-template seed skipped the changed files and retrieval could select
stale database records. The contract test now verifies every manifest fixture
hash before delivery.

Release v02.10.45 fixes Vocario archetype routing: an ordinary mention of
partners no longer forces a carousel, while explicit carousel/slider/logo
language still does. A public-speaking-school content brief now matches the
Vocario Home Hero root, preserving its real image-led Flex composition instead
of generating a sparse partner block.

Release v02.10.46 fixes the next retrieval failure found in live Browser Use:
the Vocario Hero root was discarded because it contained the unavailable
third-party `jkit_heading` widget, so retrieval fell back to the `Trusted By`
root. The shared Elementor normalizer now converts that known widget to native
`heading`, preserving its composed title, HTML heading level, responsive
typography, alignment, width and animation settings before compatibility
filtering. This keeps the image-led Hero root eligible for retrieval while
remaining native Flexbox-compatible.

Release v02.10.47 fixes the visual mismatch found after live Vision review:
trusted bundled templates no longer pass through the generic generated bento
layout, typography and badge/content-shell grammar after retrieval. Their
source composition, media, spacing and responsive settings are preserved while
natural content is mapped to the first semantic heading, body text and CTA,
leaving decorative template statistics and labels intact. Bundled fixture ids
are included in retrieval diagnostics so live tests can prove which source
template was selected.

Release v02.10.48 fixes the next live Vision mismatch: trusted Vocario narrative
templates no longer retain unrelated source copy such as English labels and
decorative statistics when a content-only prompt is adapted. The adapter keeps
the source containers, images, icons and Flex geometry, maps the requested title,
body and CTA, and removes only non-target text-bearing widgets.

Release v02.10.49 fixes the remaining live Hero geometry failure: when a long
Russian heading replaces the compact English source heading, the adapter now
applies responsive native heading/body sizes and line-heights and removes the
source negative body margin. This preserves the Vocario composition while
preventing heading line overlap and CTA collisions.

Release v02.10.50 fixes the next live fidelity failure: removing all unrequested
Vocario copy left source images, icons and blue-card backgrounds without their
intended textual hierarchy. Trusted narrative adaptation now replaces every
supported source heading, text editor, button, icon-list and icon-box text with
compact fragments from the user content, using numeric/audience fragments for
compact template slots, while preserving the original media, Flex containers and
visual composition.

Release v02.10.51 fixes the actual live Flex rendering mismatch found after the
v02.10.50 browser test. The shared normalizer no longer assigns white to every
container with empty background settings, so transparent source wrappers remain
transparent. Trusted bundled library roots are marked as source-of-truth before
the design-token pass; their palette, CTA styling, typography and media are not
rewritten by the project-wide token map while their requested copy is adapted.

Release v02.10.52 fixes the live transport hang found after the v02.10.51
reload: the Elementor chat aborts an LLM fetch after 55 seconds and routes the
timeout through the existing single provider-unavailable reload/retry path
instead of leaving the UI permanently on “waiting for JSON”.

Release v02.10.53 keeps the shared Flexbox normalizer from adding a legacy
`gap` baseline when a container already has native `flex_gap`. This removes a
second spacing source from trusted Vocario layouts and keeps their source Flex
geometry intact.

Release v02.10.54 adds Gemini as a first-class LLM provider. Its official
OpenAI-compatible Base URL and default model are populated by the provider
selector, while the Gemini API key follows the existing encrypted server-side
storage path.

Release v02.10.55 adds a Gemini model selector with Flash, Flash-Lite and Pro
choices. The dashboard switches between the Gemini select and the existing
free-text model field for other/custom providers without exposing API keys.

Release v02.10.56 fixes Gemini action requests for Google's OpenAI-compatible
endpoint by removing unsupported OpenAI-only `response_format` and
`max_completion_tokens` fields before transport. The `/llm/chat` callback now
converts unexpected PHP `Throwable` failures into a JSON `WP_Error`, logs the
server-side file/line for diagnosis, and keeps network exceptions mapped to the
existing single provider reload/retry path. The chat surfaces the safe exception
message instead of showing WordPress' HTML critical-error page.

Release v02.10.57 fixes the `Value of type null is not callable` failure in
trusted library narrative adaptation. The recursive copy mapper now captures
its compact-copy callback and derived content fragments explicitly, so repeated
headings, body widgets, icon lists and CTAs no longer resolve the callback as
null. The chat and library narrative regression smoke tests cover this path.

Release v02.10.58 fixes content parsing for hyphenated words such as
`Онлайн-школа`. The labeled-content parser now treats only spaced hyphens as
label separators while preserving em/en dash punctuation, so content-only
briefs are not split into false label/description pairs before template
adaptation. The parser contract and runtime smoke checks verify intact
requested content and still accept real `label - description` pairs.

Release v02.10.59 fixes the visual corruption found in the live Browser Use
test: a deterministic fallback action retained `fallback_variant` after a
trusted Vocario library composition replaced its generated elements. The
Elementor executor then applied the generic variation pass to the preserved
library root, overwriting source geometry and creating duplicate, misplaced
content. Trusted library roots now skip fallback variation defensively, and
the chat path removes the stale variant marker before execution. The contract
test covers both guards.

## Architecture

`wp-ai-executor.php` is the bootstrap. Domain code is split under `includes/`:

- `security/` - API key and capability profiles;
- `guide/` - mandatory agent contract and guide-token flow;
- `elementor/` - validation, normalization, recipes, compose, transactions,
  editability, CSS-to-native migration, page writes, native template access,
  and block library;
- `vision/` - optional provider-backed screenshot review, normalized reports,
  and transaction Vision gate;
- `llm/` - encrypted OpenAI-compatible provider settings, rate-limited chat
  proxy, and bounded editor context;
- `rest/` - route registration;
- `admin/` - Russian dashboard;
- `updates/` - single-file and manifest-based package updates;
- `support/`, `health/`, `rollback/`, `media/`, `exports/`, `skills/`.

Release v02.10.27 forbids the Icon Box widget in generated and imported
templates. Card headings remain native heading widgets; existing card icons are
preserved, and cards without one receive a separate native icon widget.

Release v02.10.28 fixes natural testimonial parsing when a prompt writes each
quote before its author, such as `«Отзыв». Имя, компания.`. The parsed pairs
now replace imported template demo content before normalization, preventing
placeholders such as Ashley White / London from surviving a deterministic
fallback.

Release v02.10.29 repairs the package manifest hash for the guide file and
keeps the update package verifiable before WP Pusher delivery.

Release v02.10.30 prevents incidental words inside card descriptions, such as
`команда`, from misclassifying a benefits brief as a team block. Explicit
benefit labels now win before team matching, so library retrieval keeps the
requested composition while preserving the user's content.

Release v02.10.31 parses repeated `label — description` content-only sentences
before fallback adaptation, so benefits and similar blocks preserve each
requested pair instead of failing the content-fidelity gate. The card-heading
normalizer no longer relies on a stale widget-type variable when checking
native heading markers; generated output remains free of `icon-box`.

Release v02.10.32 fixes card-heading insertion during visual normalization. The
normalizer no longer mutates the card widget array while iterating it, so adding
a native icon cannot skip the heading or duplicate the description. Benefits
and other repeatable cards retain exact label/description pairs after the full
visual grammar pipeline.

Release v02.10.33 converts every library/template container to native Flexbox,
including containers nested inside widget payloads such as Mega Menu. Grid-only
settings are mapped to Flexbox gaps/alignment where possible and then removed.
Bundled library records are stored and migrated with the normalized Flexbox
payload, while retrieval normalizes older records before generation.

Release v02.10.34 gives quoted testimonial content priority over process keywords
inside the quote. A review containing a phrase such as «каждый шаг» now remains
a testimonials composition instead of being misclassified as a process block.

Release v02.10.35 rejects a process library candidate when it contains media or
too few real step headings for the requested content. Accepted library blocks
now also pass through the shared bento/Flex layout normalizer, so content
fidelity cannot approve a visually incomplete composition. Structural library
wrappers no longer accumulate left/right padding; card-level padding remains.

Release v02.10.36 splits natural labeled content at sentence boundaries, so
name-role pairs in content-only prompts are preserved instead of falling back to
generic card copy.

Release v02.10.37 prioritizes explicit team semantics before benefits keywords,
so a role such as «стратег» inside a team prompt cannot misclassify the whole
composition as a benefits block.

Release v02.10.38 imports the supplied standalone Image Carousel fixture into
the private CopyElement library. Carousel requests are classified separately;
the native `image-carousel` is validated for at least two real media slides,
wrapped in one transparent Flexbox container, and kept as a carousel instead of
being flattened into generic cards.

Release v02.10.39 fixes carousel retrieval aliases for content-only partner,
logo, and slider briefs. The standalone carousel adapter now preserves the
source carousel while adding native heading and text-editor content from the
brief, so the content-fidelity gate cannot silently replace it with a generic
fallback block.

Release v02.10.40 gives carousel blocks a semantic `ПАРТНЁРЫ` badge instead of
the generic `НОВЫЙ БЛОК` label, keeping the shared outlined-badge rule useful for
this archetype and preventing placeholder text from reaching the live canvas.

Release v02.10.41 keeps the supplied image-carousel media rail and adds a
transparent Flexbox partner-label rail when the content-only brief names
partners. Each name is a native outlined pill, so brands are visually distinct
without replacing the original carousel with generic cards.

Release v02.10.26 makes selected-container JSON export resilient to Elementor
selection focus changes: the chat reads the live selection, falls back to the
active editor model, and retains the last valid selected models while the copy
button receives focus. This keeps recursive `wpae-elementor-selection-v1`
exports available when a selected container loses transient selection state. It
also adds a collapsed JSON spoiler immediately after each successful Elementor
write, before Vision repair can start, containing the exact native elements
returned in `editor_sync` and a local copy button.

The floating Elementor chat can explicitly export selected native Elementor
models as `wpae-elementor-selection-v1` JSON with recursive child elements;
the copy-only export stays local to the browser, while a bounded sanitized copy
of the selected subtree is sent with a selected-edit chat request.

Release v02.10.15 keeps testimonial cards semantic: their author is rendered
with a native heading and their quote with a native text editor, so the shared
card-icon normalizer no longer turns testimonial authors into `icon-box`
widgets. Generic card heading icons use a white icon on a contrasting stacked
background.

Release v02.10.16 removes the remaining contradictory generic `icon-box` rule
from the guide and execution metadata. Testimonial semantics are now explicit
in the guide, action trace, and LLM guide: author/company is a native heading,
quote is a native text-editor, and any icon is decorative only.

Release v02.10.17 makes the testimonial exception the first rule in the
generation hint, so the model cannot interpret a later exception as permission
to use `icon-box` for testimonial authors or quotes.

Release v02.10.18 fixes the full testimonial library path. Natural prompts that
say "отзывы" now win over incidental portfolio words such as "сервис" or
"продукт", the command prefix is removed before extracting the first author,
and bundled testimonial cards inside nested carousels are converted from
`icon-box` into separate native author headings and quote text editors. Legacy
carousel widget names are recognized by both layout and typography normalizers.

Release v02.10.19 removes empty testimonial image widgets during library
normalization, so a template cannot render gray media placeholders when no
author image was supplied. Real image URLs or media IDs remain untouched.

Release v02.10.20 fixes natural pricing content parsing. Quoted package names
with comma-separated prices and semicolon-separated descriptions are kept as
three correct label/content pairs, so the content-fidelity gate no longer
rejects valid pricing briefs.

Release v02.10.21 generalizes that parser fix to all quoted content labels
followed by a dash, including process steps, while keeping sentence-level CTA
instructions outside the card description.

Release v02.10.22 retries OpenRouter structured action requests once without
`response_format` and `provider` when the route returns a generic provider
error or `finish_reason: error`, before the editor performs its reload retry.

Release v02.10.23 adds the CopyElement Mega Menu c3372 fixture and its local
preview to the private block library. Mega-menu prompts now retrieve the
dedicated template, preserve its native grid header, and can replace menu
labels and CTA text from ordinary user content without converting the layout
into generic cards.

Release v02.10.23 also bundles 13 usable JSON templates from the downloaded
Vocario Public Speaking Class kit with their local JPG/PNG previews. Global kit
styles are intentionally excluded because they are not an insertable content
block. Multi-section kit pages stay as source records, while retrieval filters
unavailable roots and the LLM adapter selects one archetype-matching root so a
full page is never inserted as one generated block.

Release v02.10.14 gives explicit About intent priority over incidental Team words
inside content-only briefs, so phrases such as "о компании ... рядом с командой"
retrieve the About template instead of the Team template.

Release v02.10.13 makes the required post-generation JSON diagnostic reliable
in embedded editors: the chat retries clipboard writes through the native
textarea fallback when `navigator.clipboard` rejects, so a successful selected
JSON export is verified by the chat result rather than a false button click.

Release v02.09.88 keeps selected-container patches realtime in the open editor:
after native Backbone settings are applied, the current preview iframe is
refreshed before the chat reports success or sends the screenshot to Vision.
This rehydrates Elementor CSS such as `border_radius` without reloading the
editor page or closing the active browser tab.

Release v02.09.89 adds read-only native Elementor Template access through
`GET /elementor/templates` and `GET /elementor/templates/{id}`. Both routes
require the canonical `X-AI-Key`, expose `elementor_library` summaries or
decoded template data with normalization/compatibility reports, and never
send the key to browser JavaScript. The existing WPAE block library remains a
separate storage and import path.

Release v02.09.90 accepts an Elementor export envelope with top-level
`type: "elementor"`, `siteurl`, and `elements` through the existing private
block-library POST route. The original envelope is retained as `native_payload`
while its native `elements` array is normalized, compatibility-checked, and
stored for later instantiation through `X-AI-Key`. The Elementor Kit Library
Wireframe tab was inspected in Browser Use: it listed 11 Wireframe previews, but
the official `Download Website ZIP` action returned Elementor's visible
`Something went wrong` state, so no cloud template JSON was bundled or guessed.
Exact Wireframe templates require their official exported JSON (or a successful
local Elementor import) before they can be committed as plugin fixtures.

CopyElement Browser Use inspection on 2026-08-31 found 54 free component cards
and 54 preview image URLs. Thirty unique Elementor JSON payloads were retained
after freshness/deduplication checks; later copy attempts returned the previous
clipboard value and CopyElement displayed an account usage-ban message. Only
the complete `image-box-c2644` payload was recoverable after the Browser Use
kernel reset. It is now bundled under `includes/elementor/copyelement/` with
its preview URL and SHA-256 manifest. The first authenticated read of the
private library imports that fixture as a private `wpae_block` record,
idempotently; it never writes to Elementor's `elementor_library`. The
remaining 29 payloads were not guessed or claimed as saved because their raw
JSON was not recovered.

Release v02.09.91 adds this bundled CopyElement fixture importer. The private
library now accepts the captured `preview_url` metadata and JSON up to 4 MB;
the list response reports bundled import results for diagnostics, and the
editor library details panel renders the stored preview URL.

Release v02.09.92 adds a separate private-library retrieval step to new
natural-language Elementor generation. It ranks only approved/published,
compatible blocks by detected archetype, tags, title, description, and prompt
terms; adapts repeatable native card content from label-description pairs with
no more than four cards; and requires native-shape plus content-fidelity checks
before insertion. Targeted edits and Vision repair remain isolated from
retrieval. The chat exposes safe `library_retrieval` metadata without raw
stored JSON; if no approved match exists, normal generation remains the
fallback.

Release v02.09.93 adds the next two user-supplied CopyElement fixtures to the
same private library package: `Testimonials c1198` and `About c70`. The supplied
`Image Box c2644` was matched to the existing canonical fixture and was not
duplicated. All three bundled fixtures are declared with SHA-256 entries and
are seeded idempotently as draft `wpae_block` records until the Design Review
Gate approves them. The two new exports contain no preview image URL, so no
preview asset was invented or bundled.

Release v02.09.94 adds the user-supplied CopyElement `Team c1429` export as the
fourth bundled fixture. It contains 25 elements and 16 native widgets and is
declared with its SHA-256 in the private-library manifest. It has no supplied
preview asset and remains a draft `wpae_block` record until Design Review Gate
approval.

Release v02.09.95 keeps arbitrary private-library drafts out of automatic
retrieval, but marks the four exact bundled CopyElement fixtures with their
manifest hash and immutable parsed-content hash after seeding. Those trusted
bundled fixtures may be used by new chat generation while direct draft
instantiation and the Design Review approval workflow remain unchanged. The
seed migrates existing fixture records only when their stored content hash
still matches the packaged file.

Release v02.09.95 also recognizes team/about requests before the name-based
testimonial heuristic, parses several natural `label — description` pairs in
one sentence, and adapts non-card two-column layouts such as About through
native heading content when no repeatable card group exists.

The v02.09.95 fixture self-check now passes all four bundled designs: Team,
Testimonials, About, and Image Box retain every requested label and description
after library adaptation. Heading descriptions are written to the next heading
when a source layout has no dedicated body-text widget.

Release v02.09.96 normalizes imported CopyElement geometry before the shared
generation grammar: long source `heading` widgets used as paragraph text become
native `text-editor` widgets with bounded responsive typography, source widget
fixed widths and negative margins are removed, delayed animations are disabled
for stable preview checks, and nested multi-container groups become transparent
native Flexbox bento grids. Direct cards receive bounded responsive widths,
outlined rounded surfaces, and the group remains capped at four items per row.
The helper runs only after a trusted bundled template is selected, so ordinary
LLM/fallback generation keeps its existing behavior.

Release v02.09.97 fixes the remaining bundled-template visual regression: the
library normalizer identifies card groups by semantic native widgets instead of
boxing every multi-container composition, keeps composition shells transparent,
removes empty buttons and CopyElement placeholder text, and localizes remaining
demo CTAs. Card-heading icon conversion is now limited to descendants of a
marked bento grid, so section titles and descriptions do not receive stray
icons. Full Vision regeneration can retrieve the same trusted bundled template
again, while a successfully adapted library block skips the broad generated
bento pass that previously changed its composition.

Release v02.09.98 removes the first service placeholder heading from an imported
CopyElement tree before the shared visual grammar adds its single outlined badge;
this prevents duplicate badge/title hierarchies such as `КОМАНДА` plus `Наша
команда`. A library-applied action is no longer reported as a deterministic
fallback even when the provider response required the local fallback before the
trusted template was selected.

Release v02.09.99 keeps testimonial quote content in the library's direct quote
widget and clears the template author's demo description when the same card has
already received the quote. This prevents imported values such as `London` or a
second copy of the quote from appearing in the author block. The recursive
library adapter now passes the archetype through both traversal closures and the
four bundled fixtures pass the local adaptation/fidelity matrix.

Release v02.10.00 expands trusted library card groups to the number of supplied
content pairs, cloning complete native card subtrees with unique Elementor IDs
when the source has fewer cards. It replaces generic CopyElement headings such
as `New Block Title` with the archetype heading, keeps only `Sample Subtitle` as
an optional removable label, and keeps repeatable fallback blocks bounded while
allowing more than four cards to wrap into later rows. Card widths now choose
balanced two-, three-, or four-column rows for up to twelve cards, preventing a
single narrow orphan in a second row. The local fixture matrix covers these
rules for Team, Testimonials, About, and Image Box.

Release v02.10.01 bounds the Elementor editor screenshot capture wait for remote
images: each pending image now settles on load, error, or a 2.5-second timeout.
This prevents one unavailable preview asset from leaving the chat permanently
stuck at "Обновляю preview и проверяю результат через AI Vision".

Release v02.10.02 also bounds the `html2canvas` capture itself to 12 seconds
and passes its image timeout through to the capture library. A slow or broken
preview asset now falls back to a visible Vision error path instead of freezing
the editor chat and leaving the result unverifiable.

Release v02.10.03 fixes natural FAQ fallback parsing: punctuation-separated
question/answer pairs stay together in the native accordion, and an unmatched
FAQ remainder is rejected by content fidelity instead of becoming an orphan
text widget. Provider retries also preserve bounded Vision repair options and
findings across the forced reload.

Release v02.10.04 changes the deterministic FAQ fallback to a visible native
bento card grid. Each requested question gets an icon heading and its answer
in the same card, so Vision and users can inspect the complete content without
opening an accordion item; the old accordion remains supported for imported
templates.

Release v02.10.05 fixes natural process classification: stage/launch/transfer
markers are evaluated before the generic `команда` token, so a brief ending
with «передаем готовый результат команде» remains a process block instead of
falling back to a team composition. This keeps Vision repair aligned with the
user's actual process content.

Release v02.10.06 keeps the shared generated CTA row compact: its transparent
wrapper uses content width instead of stretching across the whole block, while
the label and button remain responsive and editable native Elementor widgets.
This removes the empty horizontal surface that Vision identified beneath
generated bento compositions.

Release v02.10.07 removes the extra generated CTA wrapper entirely. The native
button remains directly in the content shell, so visual grammar cannot create
an empty card-like surface around an otherwise valid CTA.

Release v02.10.08 makes CopyElement testimonial adaptation quote-aware: natural
«name, company - quote» pairs stay together, imported author demo descriptions are
cleared, attribution typography is bounded, and testimonial bento cards keep their
natural height without cross-row stretching. The four bundled templates remain the
live Browser Use test set; each test requires screenshot, AI Vision, and the
design-taste-frontend visual rubric before it is accepted.

Release v02.10.09 fixes natural project briefs being classified as testimonials
only because they contain quoted project names. Named testimonial signals and
explicit review language still select testimonials, while three or more project
label-description pairs select portfolio before the generic quote fallback. This
keeps CopyElement retrieval aligned with the user's content and the matching
Image Box/portfolio fixture.

Release v02.10.10 completes that classifier fix by checking project terms in the
full normalized brief as well as pair labels. Quoted project names such as
«Точка», «Маяк» and «Север» therefore select the portfolio/Image Box fixture,
while named reviews remain testimonials.

Release v02.10.12 extends the trusted-library normalization after the stale
cache fix: natural `name, company - quote` pairs separated by semicolons are
retained, source pixel padding is converted to bounded responsive rem spacing,
library roots and bento shells stay transparent, native headings/body text are
assigned bounded responsive typography by composition role, and all generated
buttons receive content-sized responsive geometry with safe wrapping and
rounded padding. The four bundled fixtures pass a local adaptation,
typography, geometry, and cache matrix; every live generation still requires
Browser Use screenshot, AI Vision, design-taste validation, and selected JSON
export before acceptance.

Release v02.10.11 clears stale `htmlCache`/`html_cache` values recursively from
generated library elements before the Elementor write. This prevents copied
CopyElement preview markup such as `Brand Identity`, `Learn More`, and lorem
placeholders from overriding the adapted native settings in the live canvas.
The four bundled fixtures pass the local adaptation/cache matrix and still
require live Browser Use verification after delivery.

Graphify navigation was refreshed on 2026-09-01 after this change: the local
code graph contains 681 nodes and 1623 edges, with `graphify-out/GRAPH_TREE.html`
regenerated as an untracked analysis artifact.

The editor-chat Vision review is advisory: quality findings trigger a bounded
rollback-and-regenerate workflow instead of silently keeping a weak block. The
failed generated version is rolled back, then the original brief plus sanitized
Vision findings are sent to the LLM for one complete native `insert_elements`
regeneration; up to two repair passes are allowed, with no changes to unrelated
existing page blocks. Atomic rollback remains limited to explicit
`transaction_vision_review` transactions.

Vision feedback in the chat explicitly says that the agent is regenerating the
design. Temporary provider failures preserve the original request, force a
reload, and retry once; embedded browsers that ignore `location.reload()` use
the same pending request for a local bounded retry, without a reload loop.

Action generation classifies natural-language block requests into hero,
benefits, pricing, testimonials, FAQ, process, CTA, or portfolio archetypes.
Content-only prompts with at least two natural label-description pairs are also
classified as generation requests, so users do not need to name an Elementor
widget or add a technical command verb. Briefs made from three or more
sentence/line units with a content signal or CTA are classified the same way,
even when they contain no labels or technical action words. Neutral briefs
with three or more sentence/line units and at least 80 characters are also
treated as generation requests, so ordinary content can be used without
technical keywords, quoted labels, or CTA wording.
Plain sentence/line briefs are also used as requested content when no quoted or
label-description content exists, so deterministic fallback and content
fidelity cannot replace the user's copy with a generic template.
When those prompts do not name a block type, archetype detection uses the
content semantics: question pairs become FAQ, price pairs pricing, testimonial
language becomes testimonials, step language becomes process, case/result
language becomes portfolio, and neutral pairs become benefits cards.
Benefits language is checked before process language, and fallback repeatable
grids are resized to the number of supplied pairs (minimum two, bounded at
twelve) and native Flexbox widths keep no more than four cards per row without
leaving a narrow orphan where a balanced row is possible.
The LLM prompt and bounded repair pass choose matching native Elementor widgets
when available, while retaining the one-root-Flexbox, populated-content,
design-system, and editability gates.
If both provider output and repair are unusable, a typed deterministic fallback
is generated, marked in the execution trace, and sent through the same gates.
Explicit quoted content in the user request is checked against the generated
native widget tree before any write. The fallback grafts those phrases into
matching heading/text widgets when possible and rejects the action with a
missing-content list when fidelity cannot be proven. Editor Vision also receives
the original brief and a bounded visible-text excerpt, so content fidelity is a
separate review concern from visual composition.
Vision reports with major/critical findings or a score below 75 trigger the
bounded editor-chat regeneration workflow; malformed/provider failures remain
visible errors. The chat only reports regeneration success after Elementor
confirms the new write.
The targeted Elementor patch branch keeps balanced nested arrays in its
progress-step merge; PHP syntax is checked before release to prevent a bootstrap
fatal error from taking down WordPress. A selected widget or container sends its
native settings plus the full bounded descendant tree to the provider. The
server derives an authorization scope from the current saved Elementor tree,
allows patches only for that scope, previews them before writing, and returns
changed descendant IDs for editor synchronization.
Pricing fallback content parsing strips request prefixes from labels, keeps
the root heading/description separate from explicit tariff cards, and maps
`Кнопка: ...` text into native card buttons.
Editor chat retries transient provider HTTP failures (408/425/429/5xx) once
after a forced reload; auth, malformed-request, JSON, validation, write, and
Vision errors remain non-retryable.
After the forced reload, the chat opens itself and renders the retry message
and final result so the one-retry path is visible to the editor.
Repeatable fallback archetypes keep explicit label/description pairs inside
their card grids instead of consuming them in the root heading/description.
Testimonial fallback output uses a wrapping Flexbox card grid with one editable
native card container per quote and a single-column mobile layout.
Generated testimonial author headings are guarded to native h5/h6 typography;
quote copy remains in text-editor/testimonial widgets so names cannot inherit a
display-scale role and overflow their cards.
Repeatable archetypes use a backend bento normalizer rather than a prompt rule:
direct native widgets are wrapped into editable Flexbox card containers when
needed, child containers receive responsive custom widths and flex wrapping, and
the layout caps each row at four items with a single-column mobile fallback.
Every generated root block receives one compact outlined rounded native badge
container with a native heading label inside. Its width is content-sized, its
horizontal padding is larger than its vertical padding, and its dark
border/four-sided radius form the pill shape. The badge geometry is protected
from the generic design-token spacing/radius map; its background may use the
active surface token or remain transparent. Headings inside repeatable card containers are normalized to native
`icon-box` widgets with an icon positioned to the left, including provider,
repair, and deterministic fallback output. The container holding repeatable
cards is forced transparent while individual cards retain their own surfaces.
The normalizer preserves existing card content, handles malformed settings
defensively, skips the badge subtree during card-icon conversion, and applies
this grammar before Elementor preflight.

The testimonial fallback now constrains author icon-boxes with compact native
h6 typography and keeps generated CTA groups content-sized and left-aligned
without a full-width divider, preventing long author names and detached action
rows from lowering the Vision score.

Testimonial classification now runs before portfolio label heuristics, so names
and quoted customer proof cannot be misclassified by words such as branding,
site, or service.

Administrators have a direct `AI Executor` link in the WordPress Admin Bar.
It opens the existing `Settings -> AI Executor` screen and is hidden from
users without `manage_options`.

Custom skills may carry an optional database-only
`wpae-skill-manifest-v1` sidecar. It describes version, capabilities, inputs,
pipeline, source, license and compatibility while `SKILL.md` remains canonical.
Legacy records without a manifest remain valid. Pipeline steps are checked
against a closed safe-endpoint allowlist and their declared capabilities; the
manifest never executes code or grants filesystem, shell, MCP, WP-CLI,
browser-admin, `/run`, or self-update access.
Skills that need screenshot review may declare the `ai_vision` capability and
the `/vision/analyze`, `/vision/report`, or `/vision/page-review` pipeline
endpoints; the site owner capability toggle and guide-token checks still apply.

The optional `llm_chat` capability powers `POST /llm/chat` and is disabled by
default. The dashboard supports OpenAI, DeepSeek, OpenRouter, and custom HTTPS
OpenAI-compatible providers. Provider keys are encrypted in `wp_options`; the
Elementor editor receives only a WordPress REST nonce and proxy URL. Ordinary
chat remains advisory. Explicit action requests may insert only new Elementor
elements when `llm_chat` and `elementor_writes` are enabled; generated data
passes update, preflight, protected-zone, visual-regression and rollback checks.
Delete and replace actions are forbidden. Only bounded history and a sanitized
selected-element subtree are sent to the model; secrets and executable fields
are removed before the snapshot is serialized.

LLM settings use provider-owned built-in HTTPS base URLs. OpenRouter defaults to
`openrouter/free`, Gemini uses Google's OpenAI-compatible endpoint with a
curated model selector, and custom
base URLs are available only for the custom provider.
An existing provider key is shown in the dashboard as a bullet placeholder while
the submitted value remains empty, so saving the form cannot replace it.
The Elementor floating chat also detects the explicit admin editor URL
`post.php?post=ID&action=elementor` and has a fallback enqueue path when the
Elementor-specific script hook is unavailable.
LLM chat responses include a safe operational `steps` array. Action traces cover
provider response, JSON command decoding, command and permission checks, element
count, native normalization, design-system mapping, page context, ID rekeying,
Elementor update and final status. The chat renders this trace and includes it in
the copied JSON log; it never exposes hidden reasoning, credentials, prompts,
raw page payloads or raw provider responses.
Action requests from the floating Elementor chat also receive the current guide,
enabled custom skills, capabilities, and project design system in the server-side
LLM context. The browser still receives only its WordPress REST nonce; the site
API key is never exposed. The action is executed through the same internal
Elementor update, preflight, transaction, visual-regression, and rollback path
used by structured REST writes.
The chat action gate rejects a technically valid but empty element tree when it
contains no native Elementor widget, preventing a false success that leaves only
an empty container. After a successful write, the chat reloads the current
Elementor preview; progress statuses appear while the synchronous request is
running, followed by confirmed server steps as separate assistant messages.
Generated blocks receive a post-parse visual-variation guard. It selects from
60 combinations of ten coherent surface themes and six responsive compositions,
compares the candidate visual signature with every saved top-level block, and
stores `_wpae_visual_variant`/`_wpae_visual_layout` metadata. This prevents a
provider or fallback from passing off the same grid as a new design, including
when older blocks have no WPAE variant class. Repeatable grids keep a maximum of
four cards per row and each composition has a mobile single-column fallback.
The candidate signature is calculated after the variant is applied, before the
write, so selection cannot rely only on an LLM seed or on a class that Elementor
may omit from the editor DOM.

When the Elementor transaction fails after a write and rollback, the chat now
exposes `update_error`, `failed_checks`, and readable `failure_details` in the
assistant message and copied JSON log instead of reducing the failure to HTTP
422. This identifies the exact post-save check that rejected the operation.
When AI Vision is enabled and configured, the floating chat captures the
refreshed Elementor preview after a successful write and sends it to the
editor-only `/llm/vision-review` endpoint. Critical findings, provider errors,
or screenshot failures restore the write snapshot when possible; the chat does
not claim success after such a rollback.
Vision review failures expose sanitized provider, HTTP status, provider
message, analysis code, and rollback status in the chat log; credentials and
raw image/provider payloads remain excluded.
LLM provider responses with HTTP 200 but `finish_reason=error` are treated as
provider failures, and the chat exposes their sanitized provider message/code
instead of reporting only an empty response.
When the LLM transport is unavailable (`wpae_llm_provider_request_failed`), the
editor chat stores only the bounded current request in `sessionStorage`, reloads
the current Elementor page, and retries it once. A second transport failure and
all model, JSON, validation, write, or Vision errors are reported without
another reload loop.
The AI Vision provider key field uses the same bullet mask as the main LLM key;
empty input keeps the encrypted key, while the explicit clear checkbox removes it.
The Elementor chat header displays the configured provider model and plugin
version as non-secret metadata; provider keys remain server-side.
The default Gemini AI Vision model is `gemini-3.5-flash-lite`. LLM action
prompts explicitly require populated native widgets inside containers; the
empty-container gate remains blocking so a failed model response cannot save a
blank hero.
Action responses allow up to 8000 completion tokens and request compact JSON;
failed decoding exposes only safe response length, JSON error, truncation hint,
provider message/code, and finish reason in chat diagnostics.
Action requests now explicitly request JSON mode from OpenAI-compatible
providers; OpenRouter requests additionally require a route that supports the
requested parameters. The action prompt repeats a final JSON-only instruction
after the guided context so provider route text or endpoint instructions cannot
be mistaken for an Elementor command. If the dynamic `openrouter/free` route
rejects those structured-output parameters with its known no-endpoint response,
the same request retries without those optional parameters and remains protected
by the server-side action decoder and Elementor validation gates. Provider
transport errors now expose a sanitized technical detail in the editor chat.
Action prompts now require exactly one top-level Flexbox container with 3-5
populated native widgets, preventing `openrouter/free` from returning a large
flat list that the action gate must reject.
When the provider still returns an invalid action shape (too many top-level
elements, no native widgets, or no insert_elements action), the proxy performs
one bounded repair pass with a minimal JSON-only context, the exact current
post_id, and reruns the same
decoder and Elementor gates.
After a successful chat write, the chat first synchronizes the saved normalized
elements into the open Elementor editor model through the official
`$e.run('document/elements/create')` command, so the current canvas and Structure
tree update without a browser reload. If the editor command API is unavailable,
it falls back to the official `document/save` footer saver API, the legacy
`elementor.reloadPreview()` path, and cache-busted iframe verification.

`POST /skills/validate` validates and normalizes a manifest without writing.
It remains available when `manage_skills` is disabled; mutation endpoints do
not bypass that owner-controlled capability.

Live verification on `v02.08.66` covered legacy-null, valid manifest,
forbidden `/run`, and missing write-capability cases. The endpoint is exposed
at `file_write_policy.allowed_endpoints["/skills/validate"]`; the skill store
count remained unchanged. Live skill mutation tests were intentionally not
forced because the site owner has `manage_skills=false`.

The dashboard uses an accessible horizontal tab interface with seven focused
sections: connection, Elementor, agents, LLM agents, Vision, monitoring and
examples. The active
section is preserved in the URL hash and local browser storage. Arrow keys,
Home and End switch tabs, while the non-JavaScript fallback keeps every card
visible in the original document order.

Health latency scoring excludes expected long-running plugin update endpoints
from interactive REST thresholds. Their count and maximum duration remain in
the report as maintenance metadata without producing a false critical alert.

The package updater explicitly loads the WordPress temp-file API when REST
requests do not bootstrap `wp_tempnam()` automatically. Atomic package writes
preserve existing file permissions and assign `0644` to new module/asset files
before rename. The plugin root and package-managed subdirectories preserve
owner write bits while receiving the missing read/execute bits required for
nginx traversal.

The visual regression gate is comparative: it blocks a good/acceptable public
baseline regressing to weak/blocked or losing key public signals, but it does
not reject every update merely because the page was already weak/blocked.
It runs only for published posts; draft/editor pages do not have a reliable
public permalink baseline and therefore skip this comparison.
The floating editor chat uses advisory AI Vision review for both draft and
published editor pages; strict rollback remains limited to explicit transaction
Vision gates.
After an insert, preview verification compares the widget count with the
pre-request count plus the inserted count, so stale canvas content cannot be
reported as refreshed.
The preview iframe cache-buster also updates its `ver` query parameter, which
forces the Elementor preview URL to request the newly saved HTML.
The current chat contract now assigns an operation_id to every action write,
returns a compact top-level diff and exposes a one-click editor-only undo
endpoint scoped to the current post. Action decoding rejects anything except
one populated root Flexbox container, so a successful provider response cannot
be reported as a flat or empty insertion.
Editor-chat Vision is advisory and no longer rolls back a successful write when
the editor screenshot or provider review fails. The capture hides editor-only
chrome/dropzones, sends bounded render-context evidence, and the Vision prompt
is instructed to ignore editor placeholders and distinguish uncertainty from a
broken public page. The chat exposes action controls for the latest rollback
snapshot and can undo it without a guide-token round trip.
Realtime editor sync now waits for the official `$e.run` promises for inserts
and applies native settings patches recursively to the selected editor model
tree for selected edits; only an unconfirmed sync falls back to a saved-preview
refresh. Vision capture waits for document fonts and pending images so it
reviews a stable render rather than an intermediate layout.
Targeted native patches are server-scoped to the selected Elementor element
ids and all descendants; prompt instructions alone are not trusted as an
authorization boundary. Generic insert wording keeps the normal insertion
route, while a selected edit prompt returns `patch_elements` and reports the
patch count, changed IDs, and selected scope count in the chat trace.
Action requests allow up to 8000 completion tokens and explicitly constrain
the provider to compact JSON with at most two containers and eight widgets,
without duplicated Elementor defaults or optional settings.

## AI Vision

Runtime module: `includes/vision/vision.php`. AI Vision is an optional
owner-controlled capability and is disabled by default in every preset because
`/vision/analyze` sends an agent-provided screenshot to the selected external
provider. Supported providers are Google Gemini, OpenAI, and Anthropic Claude.
The provider, model, and API key are configured in the dashboard's `AI Vision`
tab. The key is encrypted with AES-256-GCM before it is stored in the
`wp_ai_executor_vision_settings` option; it is never returned by
`/capabilities`, `/guide`, dashboard HTML, or operation logs. Provider payloads
use each provider's native multimodal request format; Gemini receives a
compatibility schema without OpenAI-only strictness fields, and provider
responses over the bounded 512 KB limit fail closed.

Endpoints:

- `POST /vision/analyze` accepts an existing WordPress image `media_id` or
  bounded inline `image_base64`, calls the configured provider, and stores only
  the normalized report.
- `POST /vision/report` accepts a structured report from an external Vision
  client when the image was analyzed outside WordPress.
- `POST /vision/page-review` combines a stored/external report with the
  deterministic Elementor Design Review Gate for a post or supplied data.

The normalized report contains `vision_score`, `findings`, `confidence`,
`must_fix`, `summary`, and `strengths`. Findings use `critical`, `major`,
`minor`, or `info` severity. Provider responses must pass the full structured
report contract before storage. Reports are bound to their declared post when
one is supplied. Critical findings block only when the caller explicitly passes
`transaction_vision_review=true` with a same-post report issued by
`/vision/analyze`; external `/vision/report` results remain advisory. Then the
existing atomic Elementor transaction restores its rollback snapshot. Major
findings remain advisory unless another deterministic gate blocks the write.

The module does not accept arbitrary image URLs, does not create image files,
does not persist base64 or raw provider responses, and does not put image data
in operation logs. It permits at most 12 provider calls per site-wide 10-minute
window to limit accidental spend. AI Vision is additional visual evidence only:
deterministic validation, native Elementor editability, public browser
screenshots, and animation/WebGL checks remain mandatory.

Release state: local `v02.09.75` prepared, including the LLM proxy, Elementor editor chat,
action execution, Enter-to-send behavior, provider-response diagnostics,
JSON chat logs, initial empty-page action support, design-system
normalization, native widget content alias mapping, safe operational step traces,
guided editor context, editor preview synchronization with cache-busted iframe
reload and native-widget load confirmation, separate progress
messages, the non-empty native-widget action gate, and transaction failure
diagnostics, post-write AI Vision review and rollback, and Vision failure
diagnostics, the masked AI Vision key field, chat model/version metadata, the new
Vision default model, the populated-native-widget action rule, and action JSON
 diagnostics and live Elementor preview refresh are published on GitHub `main`; `v02.09.50` keeps testimonial quotes as text and assigns card-heading icons only to short heading/author content, while preserving the compact outlined badge and transparent card-grid container. `v02.09.49` turns the badge into a compact native Flexbox pill container with a heading label, preserves its transparent background and dark outline, and forces card-grid containers to remain transparent. `v02.09.48` adds the enforced outlined badge and card-heading icon grammar after provider decoding, fallback/repair normalization, and before Elementor preflight. `v02.09.25` fixes the native Elementor card sizing mode with custom flex sizing and explicit grow/shrink values. `v02.09.24` adds backend bento normalization for repeatable blocks, native Flexbox card wrapping, a four-items-per-row cap, and single-column mobile sizing without adding the layout requirement to the user prompt. Earlier releases: `v02.08.96` added the stronger realtime canvas refresh, the hero composition guard, and the strict editor Vision quality gate, `v02.08.97` disables visual-regression comparison for first insertion into an empty Elementor post while retaining it for existing content, `v02.08.98` makes regression comparative and raises the compact action-JSON budget, `v02.08.99` skips unreliable public baselines for draft posts, `v02.09.00` skips editor Vision rollback for draft posts, `v02.09.01` verifies that the preview contains the newly inserted widgets, `v02.09.02` refreshes the preview URL version key, `v02.09.03` synchronizes saved action elements into the open Elementor editor model through the official create command, `v02.09.04` accepts double-encoded JSON returned by OpenAI-compatible providers, `v02.09.05` requests provider JSON mode and compatible OpenRouter routing for action commands, `v02.09.06` retries `openrouter/free` without optional structured-output parameters when that route rejects them, `v02.09.07` exposes sanitized provider transport details in editor chat errors, `v02.09.08` constrains action prompts to one compact populated hero container, `v02.09.09` adds one bounded repair pass for invalid action shapes, `v02.09.10` gives that repair pass a minimal JSON-only context, `v02.09.11` passes the exact current post_id into repair prompts, `v02.09.12` requires non-empty heading, text-editor, and button content in the repair-pass contract, `v02.09.13` keeps low Vision score as a warning while reserving rollback for major or critical findings and exposes report details in chat errors, `v02.09.14` gives the bounded action repair-pass a concrete populated native-widget JSON exemplar, `v02.09.15` retries an invalid repair response once and reports the failure details instead of silently stopping, `v02.09.16` keeps major Vision findings advisory so only critical findings roll back a successful editor-chat write, `v02.09.17` forbids placeholder content in repair responses and asks for request-specific copy, `v02.09.18` excludes Elementor editor-only overlays and dropzones from the Vision screenshot to prevent false critical findings, and `v02.09.19` makes editor-chat Vision reports advisory while retaining strict rollback only for transaction Vision gates.
- `v02.09.27` keeps section headings, intro copy, and CTA outside repeatable bento card grids; deterministic benefits, pricing, process, and portfolio fallbacks now create populated native card containers.
- `v02.09.46` centralizes content-only pair parsing, supports `вопрос? — ответ`, writes fallback card widgets through real arrays, fills FAQ tabs, and expands repeatable fallback cards up to four items. The local fallback matrix covers benefits, testimonials, pricing, process, FAQ, portfolio, hero, and four-card content without fidelity mismatches.
- `v02.09.47` focuses the editor preview on the newly inserted root `data-id` before screenshot capture, sends those target IDs through the server Vision normalizer, and refuses to review a stale viewport when the new block cannot be found after one bounded preview refresh.
- `v02.09.48` enforces one outlined rounded badge per generated root block and native `icon-box` headings with left-side icons inside repeatable cards, independent of provider prompt compliance.
- `v02.09.49` renders that badge as a compact native pill container with a heading label and keeps the container around repeatable cards transparent.
- `v02.09.50` prevents testimonial quotes from being misclassified as card headings: quote-like heading/icon-box widgets are demoted to text, while short author/heading content receives the native icon-box treatment.
- `v02.09.63` keeps the generated outlined badge above the heading in a vertical root shell while preserving the block's horizontal composition inside a transparent full-width child; fallback layout variants cannot restore a horizontal root around that shell.
- `v02.09.64` keeps generated root containers and bento-grid containers transparent after fallback/provider variation mapping; card surfaces remain available, and the action rule no longer asks providers to paint the outer root.
- `v02.09.65` also resets the background on a provider-supplied existing content-shell, preventing a white wrapper from surrounding otherwise transparent bento cards.
- `v02.09.66` removes asymmetric fallback card-width variants that produced oversized lead cards, 23% orphan cards, and uneven row heights; bento variants now use balanced two-column or three-/four-column rows with stretched card heights.
- `v02.09.67` keeps a standalone CTA from a labeled content-only brief in requested-content fidelity and fallback button mapping, preventing Vision from accepting a visually complete but content-incomplete block.
- `v02.09.68` forces three-card bento rows to stay on one balanced three-column line, protects generated transparent shells/root backgrounds from token-map surface/paper replacement, and keeps the two-column variation for two- and four-card compositions.
- `v02.09.69` waits for two animation frames plus a short paint settle before the editor Vision screenshot, and tells Vision to trust the captured target's objective `text_excerpt` for content presence instead of treating an ambiguous crop as missing copy.
- `v02.09.70` applies a content-only brief's extracted CTA recursively to every native button in the fallback tree, including block-level buttons after bento grids.
- `v02.09.71` classifies three labeled content-only case entries as a portfolio card set before the CTA keyword can misclassify the whole brief as a generic CTA block.
- `v02.09.72` normalizes every provider-supplied `wpae-bento-grid` after decoding: the grid stays transparent and its direct cards receive balanced widths by count, with a 100% mobile width, even when the provider returned an oversized lead card.
- `v02.09.73` centralizes that bento normalization for grids created after provider-card wrapping, keeping every row at two, three, or four balanced cards instead of preserving a 48/23/23 lead-card distortion. Vision now filters only verified missing-content false positives when the requested phrase exists in the target `text_excerpt`; layout findings remain active.
- `v02.09.74` adds a final recursive normalization pass after visual grammar and wraps a block-level CTA in a transparent, separated action row so generated bento cards stay balanced and the CTA is visually connected to the composition.
- `v02.09.75` treats REST 502 responses and provider `finish_reason: error` responses as transient provider-unavailable states, enabling the existing single force-reload retry path.
- `v02.09.76` applies the final bento pass after fallback, native normalization, and token mapping; shared card sizing clears conflicting fixed dimensions, forces equal responsive widths/stretching, and moves a stale root bento marker onto the actual card shell.
- `v02.09.77` keeps generated CTA rows transparent after token mapping and adds a compact native CTA label beside the button, preventing a lone button from rendering as an empty white card.
- `v02.09.78` adds selected-element/container prompt editing with bounded recursive context, server-side descendant scope authorization, native patch preview/write, and realtime settings synchronization in the open Elementor canvas. Insert wording has priority over broad block words so generation prompts do not get misrouted to the patch branch.
- `v02.09.79` recognizes the natural edit verb `поменяй` as an action request, allowing selected-element prompts such as `Поменяй этот заголовок...` to reach the patch pipeline instead of the ordinary chat response path.
- `v02.09.80` scopes Vision review to the selected patch subtree, routes failed targeted reviews back through bounded `patch_elements` repair instead of full-page regeneration, returns selected scope IDs for focused screenshots, and exposes patch diagnostics in the chat log.
- `v02.09.81` classifies neutral three-sentence content briefs as generation requests when they contain enough copy, preventing them from falling back to an ordinary explanatory chat response.
- `v02.09.82` normalizes provider-generated process-card headings to one consistent sequential numbering scheme before visual grammar and Vision review, preserving the step wording.
- `v02.09.83` applies that process numbering recursively through provider-created wrapper containers and passes the archetype into card-heading icon normalization, so bento wrapping cannot drop step labels or card icons.
- `v02.09.51` captures only the new Elementor root for editor Vision and reports a missing or zero-size target instead of sending the old full-page viewport for review.
- `v02.09.52` protects generated badge padding/radius from design-token remapping, enforces explicit pill geometry, resets the label margin, and replaces model-supplied badge variants with the canonical generated badge.
- `v02.09.53` adds explicit selected-Elementor JSON export to the floating chat, including native settings and recursive child elements without adding the full payload to LLM requests.
- `v02.09.54` replaces chat control text with Elementor icon buttons while retaining accessible labels and tooltips.
- `v02.09.55` fixes the chat input height to match the 42px send icon button and disables outer textarea resizing.
- `v02.09.56` recognizes natural content-only briefs made from sentence or line units with a CTA/content signal, so the editor chat requests generation instead of returning advisory instructions.
- `v02.09.57` carries plain sentence/line content into fidelity checks and fallback widgets when a content-only brief has no labels or quotes.
- `v02.09.58` gives provider generations a per-run composition seed and makes deterministic fallback blocks choose an unused visual variant from a ten-variant palette, preserving the content and applying the variation after design-token mapping.
- `v02.09.59` applies that unused visual-variant guard to provider-generated blocks as well as fallback blocks, keeps each repeated-card grid on one coherent card surface, and hides the stale Elementor preview loader after the iframe has populated so realtime insertion and Vision capture see the rendered block.

`v02.09.87` routes natural selected-container corner-radius requests into a
deterministic `patch_elements` change, replacing partial or scalar radius
values with a complete native Elementor dimension object before preview and
write. The latest local release `v02.09.87` is prepared for publication on
GitHub `main` after browser verification.

`v02.09.86` keeps the generated CTA row at the full width of its transparent
content shell while keeping the button group compact and left-aligned. This
prevents a detached `fit-content` action from looking like an orphaned card in
Vision review. On
2026-08-30, when the original Elementor tab continued serving v02.09.40, a
same-URL duplicate tab loaded v02.09.41 and the stale tab was closed. After the
v02.09.42 delivery, a second same-URL duplicate tab loaded v02.09.42 and the
previous tab was closed. The v02.09.42 browser test confirmed the provider
retry message and one retry after reload; the configured OpenRouter route then
timed out twice with cURL error 28, so no design success was claimed. The
v02.09.34 browser test reproduced a pricing fallback defect:
the first tariff label was promoted into the root heading and its card was
missing; AI Vision correctly rejected the result and rolled it back.
`/wp-json/ai-executor/v1/key` endpoint returns `404`.

## Design System Package v2

`POST /elementor/design-system` remains backward compatible and now returns a
`package` containing a machine-readable `wpae-design-system-v2` manifest,
agent-facing `DESIGN.md` content and semantic tokens. The manifest includes a
stable system ID, version, provenance, source URL, SHA-256 source hash, license
and optional active-page state when `post_id` is supplied.

Structured Elementor writes persist `_wpae_design_system_id` and
`_wpae_design_system_hash` through the shared save path. These fields are
already included in rollback snapshots, so restored revisions retain their
design-system association. Package data remains in WordPress options/meta and
does not create server files.

Live verification on `v02.08.63` confirmed Package v2 output, guide-session
compatibility, `422` schema rejection and a successful non-writing update
`dry_run`. Existing pages without WPAE design-system meta report `active=false`
until their next structured Elementor write; they are not modified implicitly.

## Elementor block library

Implementation:

- PHP: `includes/elementor/block-library.php`
- Context-menu integration: `assets/js/elementor-block-library.js`
- Visual library UI: `assets/js/elementor-block-library-ui.js`
- Visual library styles: `assets/css/elementor-block-library.css`
- Storage: private `wpae_block` custom posts plus revision-enabled
  `_wpae_block_payload` post meta
- Public files: none
- Maximum accepted JSON input: 4 MB

Accepted inputs:

- one native Elementor element;
- an array of native Elementor elements;
- an Elementor template object with `content`;
- a `wpae-elementor-block-v1` wrapper.

Each stored wrapper contains:

- exact `native_payload` for lossless foreign template storage;
- extracted `elementor_data` for insertion;
- title, description, category, tags and source metadata;
- optional sanitized `preview_url` for an external component thumbnail;
- Elementor/design-system versions;
- compatibility report, content hash, media references and protected zones.

REST endpoints:

- `GET|POST /wp-json/ai-executor/v1/elementor/blocks`
- `GET|DELETE /wp-json/ai-executor/v1/elementor/blocks/{id}`
- `GET /wp-json/ai-executor/v1/elementor/blocks/{id}/instantiate`
- `POST /elementor/blocks/{id}` updates bounded metadata or replaces native JSON and returns the block to `draft`.
- `POST /elementor/blocks/{id}/duplicate` creates a draft revision with `parent_revision`.
- `POST /elementor/blocks/{id}/publish` moves `draft -> approved -> published`; approval runs Design Review Gate.

The `wpae-elementor-block-manifest-v1` sidecar records status, source skill,
design-system version, provenance/license, parent revision, quality score and
media dependencies. Manifest metadata is limited to 32 KB. Old records without
the sidecar remain readable as published blocks.

Live verification on `v02.08.68` created and deleted a private smoke fixture:
draft creation, manifest/source-skill extraction, metadata update, duplicate
revision with parent ID, and expected `422 wpae_block_review_required` approval
failure all passed. No Elementor page was changed.

Instantiation modes:

- `preserve` - rekey IDs only and keep the original design;
- `compatibility` - normalize, rekey and validate for the Flexbox contract;
- `adapt` - compatibility plus deterministic semantic color, typography,
  spacing and radius mapping through native Elementor settings. The response
  includes mapped/unmatched/collision evidence and protected-zone skips.

Live fixture verification on `v02.08.64` confirmed that `preserve` and
`compatibility` retain source styles, while `adapt` maps native values and
responsive units without changing protected HTML. Separate fixtures confirmed
collision and unmatched review lists. Both private test blocks were deleted;
the live block library returned to its original empty state.

`preserve` reports a warning when protected HTML/JS/WebGL zones exist because
their code may refer to old Elementor `data-id` values after safe ID rekeying.

In the Elementor editor, container/widget context menus expose:

- `Копировать как JSON`
- `Сохранить в библиотеку WP AI Executor`
- `Открыть библиотеку блоков`

## Design Review Gate

- `POST /elementor/design-review` возвращает deterministic review по composition/brief, design-system consistency, accessibility/mobile, copy и Elementor editability.
- Состояния: `review`, `revise`, `approved`; максимум три итерации.
- `transaction_design_review=true` делает approval обязательным и включает auto-rollback при провале.
- Live verification: страница `4172` вернула `approved`, visual level `acceptable`, HTTP `200`.

The visual library provides client-side search, category filtering, structural
details, compatibility status and `preserve|compatibility|adapt` insertion
modes. Insertion uses Elementor's native `document/ui/paste` command and the
currently selected Elementor container instead of writing page metadata.

The browser integration uses the logged-in WordPress REST nonce and never
exposes `X-AI-Key`. The packaged JS source is attached inline to Elementor's
existing `elementor-editor` handle because the current hosting policy returns
403 for direct static files inside the WP AI Executor plugin directory. The
inline source is printed before `elementor-editor`, catches `elementor/init`
before element views calculate their context-menu groups, and exposes the
non-sensitive `window.WPAEBlockLibraryDebug` status for DevTools diagnostics.
It registers Elementor's universal `elements/context-menu/groups` filter for
current and future element types, while retaining typed filters as a fallback.
Callbacks resolve the active models through `elementor.selection.getElements()`.

## Agent workflow

1. Fetch `/guide` and `/capabilities`, then complete guide-token acknowledgement.
2. Search `/elementor/blocks` before generating a common section from scratch.
3. Inspect the full block and compatibility report.
4. Choose `preserve`, `compatibility`, or `adapt` explicitly.
5. Preserve protected HTML/WebGL/Three.js/GSAP/canvas zones.
6. Merge returned `elementor_data`, then run normalize/validate and write
   endpoint `dry_run=true`.
7. Write only after validation succeeds and verify the public page.
8. When `ai_vision` is enabled, capture desktop and mobile screenshots, call
   `/vision/analyze` or `/vision/report`, then call `/vision/page-review`.
9. For risky writes, pass `transaction_vision_review=true` with the report ID
   and inspect `transaction.checks.vision_review` and rollback details.

## Normalizer update

`v02.09.28` improves the shared Elementor normalizer: legacy alignment/gap aliases migrate to current Flexbox controls and responsive variants, containers receive `container_type=flex`, and native buttons receive explicit design-token background/text colors so theme defaults cannot change the generated contrast. `v02.09.29` extends the chat contract with strict root-shape validation, operation IDs, compact diffs, targeted native patches, one-click undo, stable realtime sync confirmation, and advisory Vision render context. `v02.09.30` adds deterministic content-fidelity validation, content-aware fallback repair, and passes the user brief plus a bounded preview text excerpt to the Vision review. `v02.09.32` also extracts unquoted natural-language label/description pairs and maps them into fallback cards before the fidelity gate. `v02.09.33` makes Vision score below 75 and major/critical findings blocking, returns the actual `quality_failed` state, and makes the editor chat attempt rollback after a failed Vision review. `v02.09.34` fixes a missing nested-array bracket in the targeted Elementor patch progress merge that caused a PHP parse fatal during bootstrap. `v02.09.35` fixes pricing fallback label parsing, keeps explicit tariff content inside separate cards, and maps `Кнопка: ...` into native card buttons.

## Next block-library steps

- visual library browser and insertion action inside Elementor;
- update/duplicate endpoints and richer category/tag management;
- previews and screenshots;
- media dependency transfer between sites;
- richer native token controls in the dashboard;
- live AI Vision provider and transaction-gate smoke tests;
- runtime REST/editor tests on a WordPress + Elementor installation.
