# Typed provider contract and final live gate — v02.11.321

Date: 2026-10-09 (Asia/Almaty)
Post: `5214` (`https://mazhenov.kz/pricing-contract-live-v123/`)
Runtime source: `40c686bc95fbcfc552bd10d5055f6f59277c60a4` (`v02.11.321`)
Installed WP AI Executor PHP version and reloaded editor inline version: `v02.11.321`.

## Gate status

The new final-source matrix has **3 of 5 full accepted cases**. Benefits C, Pricing D and FAQ E each completed ordinary plugin chat generation, Publish, editor reload, independent native readback, public desktop/mobile review and guarded operation-scoped Undo. Hero A and About B each made one ordinary chat submission, received HTTP 400 from the typed provider intake, and wrote nothing. Those refusals do not count. No failed fixture was repeated because no source change or other permitted changed precondition addressed the unknown HTTP 400 cause. The gate remains open with **2 full acceptances remaining**.

Five submissions were made in the required order C, A, B, D, E. There were three transaction writes (one per accepted case), no repair writes, two no-write provider refusals, and no same-fixture retries. The audit matrix is [acceptance-matrix.json](acceptance-matrix.json).

## Source and contract

The v321 source fix is in the existing pipeline, not a parallel generator:

`BriefIR → DesignPlan → ElementorIR → native compiler → validation → transaction/readback`.

The typed intake now carries an explicit `typed_intake_strict` transport policy. Provider normalization maps the token limit to OpenRouter's `max_tokens` while preserving the supplied `response_format=json_schema` and `provider.require_parameters=true`. The legacy structured-response downgrade remains limited to the older non-typed action caller. Typed intake performs one primary request and at most one bounded schema-preserving syntax/schema retry; it does not retry transport/HTTP failures, relax the schema, or fall back to raw-tree generation. The retry uses the frozen family, exact copy, slots and fact set. Hero generated title/body constraints and overlap checks are validated server-side before any transaction.

The changed v321 runtime also records the requested/returned model, endpoint when available, schema hash/size, HTTP/finish status, token limits and usage, duration, remaining budget, retry reason, validation result and write count in successful typed response telemetry. For A/B's HTTP 400, the user-visible failure evidence retained only the generic `provider_http_failure`, status, model route, one call and zero writes; the provider response body, returned model and endpoint were not exposed. This audit therefore does **not** claim that either JSON Schema was invalid or that the provider lacked support.

OpenRouter documents structured-output support per endpoint, says support can vary even for the same model, and requires the caller to specify `response_format=json_schema` and use `require_parameters` for compatible routing. Its `/free` route selects among free models, so the route name does not identify one fixed serving model. Those docs support keeping the A/B cause unknown; the local failure evidence is not enough to identify a specific endpoint or schema incompatibility ([Structured Outputs](https://openrouter.ai/docs/guides/features/structured-outputs), [Free Models Router](https://openrouter.ai/openrouter/free)).

## Local checks and release state

On v321, before publication, the project checks completed successfully: Design Pipeline Contract **986**, Flex Runtime **1596**, Node suites **27/27**, changed PHP lint, Elementor patch guard, catalog check and package probe (**253 hashes, zero mismatches; four scenarios**), and `git diff --check`. The scoped runtime commit is `40c686bc95fbcfc552bd10d5055f6f59277c60a4`.

The runtime `git push` reported success. An independent `git ls-remote origin refs/heads/main` failed because `github.com` could not be resolved by DNS, so remote HEAD remains unverified. WP Pusher installed only WP AI Executor; the Plugins page showed v321 and the reloaded Elementor editor's inline configuration showed v321. No other plugin or site setting was changed. Source, push report, remote verification, install and editor version are separate statuses.

## Case results

| Case | Typed selection and lifecycle | Content/native | Public geometry and visual | Restoration | Counted |
|---|---|---|---|---|---:|
| C Benefits | `benefits.editorial_list` / `editorial_light`; root `a90c6ae`; operation `wpae-f11d9d469dbabb84`; identity `cfe6d993-b70c-4209-8359-21699667d7f0`; revision 5 | Three exact ordered titles, generated bodies fact-checked, no images/CTA. Independent native JSON after reload matches the accepted 20-node tree; one provider call, one transaction write. | Public desktop 1280×720 and mobile 390×844; complete text, no overflow/clipping. Pale mint section and restrained single-column mobile layout. Vision's dark-on-dark warning conflicts with computed surface and opened PNG; no repair applied. | Scoped Undo, editor/server/public `[]` | Yes |
| A Hero | One ordinary typed chat request; one provider call; HTTP 400; retry 0; no root/operation | `provider_http_failure`; no provider response body or endpoint-specific reason. No raw-tree fallback; `write_count=0`. | First editor failure screenshot only; there was no generated result to visually accept. Editor/public stayed at `[]`. | Not applicable; no write | No |
| B About | One ordinary typed chat request; one provider call; HTTP 400; retry 0; no root/operation | `provider_http_failure`; no provider response body or endpoint-specific reason. No raw-tree fallback; `write_count=0`. | First editor failure screenshot only; there was no generated result to visually accept. Editor/public stayed at `[]`. | Not applicable; no write | No |
| D Pricing | `pricing.tiers` / documented default (profile not requested); root `1b719d9`; operation `wpae-535444eed4e31760`; identity `174c0455-43c9-43fc-9b6f-75e64f27c9f6`; revision 6 | Exactly three requested tiers, prices, feature groups and CTA hrefs `#start`, `#project`, `#support`; no media. Independent selected native JSON after reload equals the pre-Publish accepted tree; refreshed descriptor and owned check passed. | Desktop CSS 1280×720: three equal 363.992px cards, 24px gap, all CTA top edges at y=455.203px. Mobile CSS 390×844: one-column natural stack, no horizontal overflow. The first mobile frame preserved the site greeting overlapping CTA #project. After collapsing the existing chat through the visible AI-Dana toggle, an additional fresh frame shows the CTA unobstructed. Vision's dark-header finding conflicts with the pale mint surface in PNG/DOM. | Scoped Undo, editor/server/public `[]` | Yes |
| E FAQ | `faq.native` / documented default (profile not requested); root `12950c0`; operation `wpae-93f70e4672dc53e7`; identity `6bd622d3-c25c-47da-8cd7-8f606d9ae7be`; revision 6 | Native Elementor Accordion; exact question order and factual answers retained. Pre-Publish and independent post-reload native trees are equal; full editor/server comparison and owned-model check pass. One provider call, one write. | Desktop CSS 1280×720; mobile CSS 390×844. First answer and longer second answer were opened through the native control after animation. Height grows with content, with no clipping or horizontal overflow. Vision's “dark background” description conflicts with the computed `rgba(97,206,112,.2)` and PNG; no repair applied. | Scoped Undo, editor/server/public `[]` | Yes |

Prompts are preserved byte-for-byte in [fixtures](fixtures/). Provider diagnostics, full chat logs, accepted native JSON, after-reload comparisons, descriptors, measurements and post-Undo baseline reads are stored under [exports](exports/) and [measurements](measurements/). A/B's failure screenshots are kept as negative evidence. No generation was manually assembled or repaired in Elementor.

## Screenshot gallery

All public images below were captured after Publish and editor reload, saved from the existing Browser Use tab bytes, format-checked, converted from JPEG to PNG where needed, dimension-checked and opened for visual inspection. Public mobile means the public page at measured `window.innerWidth=390` and `window.innerHeight=844`; image pixels are listed separately.

### C — Benefits

Post 5214, root `a90c6ae`, operation `wpae-f11d9d469dbabb84`, revision 5; public desktop CSS 1280×720 / PNG 1280×720.

![Benefits public desktop after reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/C-benefits-public-desktop-after-reload-v321.png)

[Скачать PNG — Benefits desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/C-benefits-public-desktop-after-reload-v321.png)

Post 5214, root `a90c6ae`, operation `wpae-f11d9d469dbabb84`, revision 5; public mobile CSS 390×844 / PNG 390×844.

![Benefits public mobile after reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/C-benefits-public-mobile-after-reload-390x844-v321.png)

[Скачать PNG — Benefits mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/C-benefits-public-mobile-after-reload-390x844-v321.png)

### D — Pricing

Post 5214, root `1b719d9`, operation `wpae-535444eed4e31760`, revision 6; public desktop CSS 1280×720 / PNG 1280×720. All three CTA edges align; all tiers and links are visible.

![Pricing public desktop after reload](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/D-pricing-public-desktop-v321.png)

[Скачать PNG — Pricing desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/D-pricing-public-desktop-v321.png)

Post 5214, root `1b719d9`, operation `wpae-535444eed4e31760`, revision 6; public mobile CSS 390×844 / full-page PNG 375×1089. The original frame records the site greeting overlapping CTA `#project`; it remains an accessibility limitation for the initial state. The next image was captured after collapsing the greeting through the site's visible UI.

![Pricing public mobile, original greeting overlap](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/D-pricing-public-mobile-full-390x844-v321.png)

[Скачать PNG — Pricing mobile with original overlap](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/D-pricing-public-mobile-full-390x844-v321.png)

![Pricing public mobile after chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/D-pricing-public-mobile-chat-collapsed-390x844-v321.png)

[Скачать PNG — Pricing mobile after chat collapse](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/D-pricing-public-mobile-chat-collapsed-390x844-v321.png)

### E — FAQ

Post 5214, root `12950c0`, operation `wpae-93f70e4672dc53e7`, revision 6; public desktop CSS 1280×720 / PNG 1280×720. The first answer is open. The second answer is in a separate fresh image below.

![FAQ public desktop, first answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-desktop-first-open-v321.png)

[Скачать PNG — FAQ desktop, first answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-desktop-first-open-v321.png)

![FAQ public desktop, second answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-desktop-second-open-v321.png)

[Скачать PNG — FAQ desktop, second answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-desktop-second-open-v321.png)

Post 5214, root `12950c0`, operation `wpae-93f70e4672dc53e7`, revision 6; public mobile CSS 390×844 / PNG 390×844.

![FAQ public mobile, first answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-mobile-first-open-390x844-v321.png)

[Скачать PNG — FAQ mobile, first answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-mobile-first-open-390x844-v321.png)

![FAQ public mobile, second answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-mobile-second-open-390x844-v321.png)

[Скачать PNG — FAQ mobile, second answer open](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-provider-contract-v321/screenshots/E-faq-public-mobile-second-open-390x844-v321.png)

## Final page and remaining status

The final saved editor, public DOM and native root sets are all `[]`. Each of C, D and E returned to that baseline through its own operation-scoped Undo. A and B never wrote. User-owned roots were not removed or replaced.

The technical pipeline and three case lifecycles passed. The five-case gate remains **3/5** because Hero A and About B returned one HTTP 400 each with `provider_http_failure` and zero writes. The documented `/free` route is dynamic, but this evidence does not show whether the cause was an endpoint, request schema or another upstream condition. No blind retry, paid model, other account, settings change or alternate transport was used. This audit is a progress report; it does not mark the requested five-case task complete.
