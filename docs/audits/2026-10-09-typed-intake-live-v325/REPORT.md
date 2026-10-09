# Typed intake diagnostics and live acceptance — v02.11.325

- **Date:** 2026-10-09
- **Target:** existing Elementor page `post=5214`
- **Final source/runtime:** `c3b41d64a64f2e76f809d7db76c72084653f8943`, plugin `v02.11.325`
- **Final page roots:** `[]`

## Status

The typed intake chain is still `canonical intake → BriefIR → one composition decision → frozen DesignPlan → ElementorIR/native compiler → validation → transaction/readback`. The confirmed v321 Hero failure was an OpenRouter structured-output wire-compatibility defect; the shared v323 adapter removed only the unsupported `uniqueItems` keyword from the OpenRouter wire schema. The canonical schema and server-side semantic validation remain authoritative. The exact Hero fixture then completed successfully on v325.

The historical About 400 is a distinct upstream route capability refusal. The diagnostic says the selected free-routed endpoint/model does not support `json_schema` and supports only `json_object`. Strict structured output, `require_parameters`, and fail-closed handling remain in place. That external incompatibility is not presented as locally fixed.

Five new written generations on the final v325 source reached Publish, reload, independent native readback, public desktop/mobile capture and review, then operation-scoped Undo: Hero A, two-tier Pricing D, Testimonials, Team editorial rows, and an authored-copy About split. Team rows passed lifecycle/content/media and responsive checks with a visible desktop whitespace note. The exact required A–E matrix is only **2/5 accepted**: A and D passed; B, C and E did not write. Therefore the requested exact matrix is **not complete** and the overall task remains open despite five full generation cycles across distinct families. Failed/pre-write scenarios are not counted as accepted generations.

## Source, push, install, and editor version

- Diagnostic runtime commit `92c564d` added allowlisted, secret-filtered, length-limited diagnostics through transport, attempts, intake, REST response and existing chat diagnostics. HTTP 200 provider error envelopes are classified as errors. Unknown fields remain null/unknown; API endpoint and upstream provider name are separate.
- Runtime commit `3b7fb6a` added `openrouter_typed_omit_uniqueItems_v1`. It recursively removes only `uniqueItems` from the strict OpenRouter wire representation. The full canonical contract still rejects duplicate `fact_refs`; `json_schema`, `strict=true`, and `require_parameters=true` are preserved on the first request and bounded retry.
- `66146ff` corrected generic numbered Benefits heading parsing; `c3b41d6` corrected parsing of a quoted two-tier price-only line while preserving exact copy.
- Final source `c3b41d6` was pushed and independently matched `origin/main` before this documentation-only update. No runtime source changed during the live acceptance/reporting pass.
- WP Pusher updated only WP AI Executor. Existing Plugins page showed `v02.11.325`. The reloaded existing Elementor page’s inline `window.WPAELLMChat.pluginVersion` also showed `v02.11.325`; the editor Publish button was disabled after reload. No other plugin or WordPress setting was changed.
- The runtime checks for v325 were already run against this final source: Design Pipeline Contract **992**, Flex Runtime **1610**, Node **27/27**, PHP lint PASS, Elementor patch guard PASS, catalog **158 manifest / 156 tree / 156 preview**, package probe **253 files / 0 hash mismatch**, `git diff --check` PASS. This pass made no runtime edit, so the full runtime suite was not rerun.

## HTTP 400 diagnosis and schema comparison

The v322 diagnostics saved the actual safe request fingerprints and provider error envelopes. They did not retain raw provider request/response bodies. The failures are distinguishable without assuming a schema-shape cause:

| Fixture / outcome | Strict request facts | Provider response | Conclusion |
|---|---|---|---|
| A Hero, v322 diagnostic repeat | `json_schema`, strict, `require_parameters`; schema 864 bytes, SHA-256 `428cecc033882e18120505b091ab6db003f11bd4555c372bf5712082e9ad2a48`; token limit 3200; one actual HTTP call | HTTP 200 envelope with error code 400, type `invalid_request`, message “The `uniqueItems` JSON Schema keyword is not supported.” | Confirmed OpenRouter wire keyword incompatibility, not a model answer or semantic refusal. Fixed by the shared wire adapter. |
| B About, v322 diagnostic repeat | `json_schema`, strict, `require_parameters`; schema 602 bytes, SHA-256 `0e92739eea9461b8d5131a4ec8078e7e1101477ef22de2fbae60f8caced00206`; token limit 3200; one actual HTTP call | HTTP 400 envelope; `provider_name=Novita`; message: `Model 'apodex/apodex-1.1-mini' does not support 'json_schema' response format. Supported formats: json_object.` | Confirmed selected upstream route cannot honor strict structured output. `api_endpoint` is OpenRouter’s API URL; it is not the upstream model/provider. No safe local downgrade was made. |
| C Benefits, successful v321 control | `json_schema`, requested `openrouter/free`; schema 1201 bytes, SHA-256 `2587c72d641424cb58ad0d26b5e8d3b242767a0dbd80b197c9503646c3ab75fd`; token limit 3200 | HTTP 200, `finish_reason=stop`, accepted | Control demonstrates that a different schema using the same strict response format can succeed; it does not establish arbitrary route support. |
| D Pricing, successful v321 control | `json_schema`, requested `openrouter/free`; schema 1144 bytes, SHA-256 `f49b0c1d9bb2bead090ec3a44a460e34a4390fb50cf5baae754ae1cc70545f69`; token limit 3200 | HTTP 200, accepted | No provider-reported schema-shape rejection. |
| E FAQ, successful v321 control | `json_schema`, requested `openrouter/free`; schema 848 bytes, SHA-256 `fe6ed8a63548b1c089134fdbe441e0aca04cc6e1fb73c94bb79383c7a9ed9bbe`; token limit 3200 | HTTP 200, accepted | No provider-reported schema-shape rejection. Historical C/D/E remain historical controls, not new acceptances. |

The family schema differs in size and content, but the available exports intentionally preserve hashes/byte counts rather than raw schema bodies. The explicit A error identifies the unsupported keyword. The explicit B error identifies the unsupported response format, so `required`, `additionalProperties`, `anyOf`/`enum`, and array/string constraints do not explain that endpoint refusal. We did not relax semantic validation or structured output.

The v323 adapter regression checks ensure the canonical schema retains its semantic constraints, the wire copy omits only the unsupported keyword, duplicate facts remain rejected server-side, strict parameters remain enabled across bounded retry, and error envelopes at HTTP 200 are never accepted as model copy. Diagnostics are allowlisted, stripped of secrets, UTF-8 safely bounded, and exclude raw body/metadata. Existing tests cover Cyrillic, emoji, and over-limit message truncation.

## New live acceptance matrix A–E

The exact fixtures were saved before submission; hashes appear in `acceptance-matrix.json` and the fixture copies are under `fixtures/`.

| ID | Status | Result |
|---|---|---|
| A Hero | **ACCEPTED** | Exact generated-copy fixture, `hero.text_only` v1, automatic decision, root `b0d0fc3`, operation `wpae-14af1036fffe6a37`, identity `1715dfe8-ad6d-4334-a4ad-1d4b0c0828ff`, generation revision 4 / refreshed descriptor revision 5. One Brief and Plan; Brief SHA `0d2e9916d1e7b3330303eb8c2a2ae935e5c0562e20189629c4e75591ac810fe8`, Plan SHA `067131f24d1064a44cce356c90544068ef86e076744f8c808d40ce41d153bf87`. Provider accepted after adapter; one transaction write. Native export after reload and owned/full-document check passed. Exact generated H1/body and CTA `Обсудить проект` → `#contact`; no media. Desktop CSS viewport 1280×720; mobile 390×844. Clean text-only hero, strong hierarchy, no overflow; substantial empty right field follows the chosen text-only composition. Scoped Undo and editor/public baseline `[]` passed. |
| B About generated-copy fixture | **BLOCKED / NO WRITE** | One v322 diagnostic repeat already produced the explicit unsupported-`json_schema` route message shown above. `write_count=0`; no root/operation. Do not drop `strict` or send `json_object` because that would remove the required schema guarantee. The separate authored-copy About test below is supplementary and does not replace B. |
| C Benefits | **FAIL / NO WRITE** | v325 used the exact four-benefit fixture. Provider returned `finish_reason=length` on both bounded attempts (`provider_calls=2`, `actual_http_calls=1`, retry 1; 3200 token limit; requested `openrouter/free`, returned `dots-studio/dots-3-note-preview:free`, provider `AtlasCloud`). Intake diagnostic `response_truncated` / retryable schema failure; no accepted Brief/Plan, no fallback, no operation/root, `write_count=0`. No blind repeat or speculative token-limit change. |
| D Pricing | **ACCEPTED** | Two-tier exact fixture, `pricing.tiers` v1; root `f071f32`, operation `wpae-5ba97f5fb65fd4fc`, identity `fc3b4459-e0d4-4a58-a4cb-cc13127c1c4f`, generation revision 4 / descriptor revision 5. Brief SHA `b95f2a501526994c699d821897027d9de34e5d04fa8d38af8bf237afd16218fb`; Plan SHA `96a4f8160fb4932c1e49387bc4afef3e8ecc09e00fcd868a6b218af357528c52`. One Brief/Plan and one transaction write; bounded provider retry preserved schema. Two cards only; exact tier labels/prices/periods/features/CTA hrefs survive native readback. Desktop CSS viewport 1280×720; mobile 390×844. Equal desktop card widths and CTA bottom axis; mobile natural stacking. Greeting overlap was captured and a second frame after normal widget collapse confirms the lower CTA. Scoped Undo and baseline `[]` passed. |
| E CTA generated-copy fixture | **REFUSED / NO WRITE** | Intake returned a semantically valid CTA-family result after bounded retry (`generated_slot_count=1`, requested `openrouter/free`, returned Nvidia/Nemotron); the frozen Plan refused it with `cta_description_required`. `write_count=0`; no root/operation, no fallback. This is a Plan completeness refusal, not a successful CTA render. No repeat with altered request. |

## Additional full live cycles on v325

These are genuine new generation cycles and contribute to five distinct successful live cases, but they do not replace the exact A–E fixture matrix.

### Testimonials — accepted

Fixture `Testimonials-editorial-rows-supplement.txt`, SHA-256 `a64825f23e316ac775bacd1815e4cfccae3097120002b8ff25317d6a3569bbe9`. Three fictional quote/author/meta records with permitted illustrative images; automatic `testimonials.editorial_rows` v1, profile `editorial_light`; deterministic route, provider 0, one write. Root `34b5567`; operation `wpae-a3fe0270f937ba49`; identity `650a2e4c-3a44-455f-aefc-e853a341e5c4`; refreshed descriptor revision 6. Native readback includes exact quote/author/meta ownership and three image URLs/alts; all images loaded (natural sizes 1100×1046, 1500×1000, 238×400). Desktop CSS viewport 1280×720, mobile 390×844. Editorial rows remain distinct from Services cards; long mobile quote grows without clipping. The site's greeting initially overlaps the third quote in the lower frame; original and after-normal-collapse PNGs are retained. After collapse the text is visible, though the floating launcher remains in whitespace. Scoped Undo passed; baseline `[]` confirmed.

### Team editorial rows — accepted with visual note

Fixture `Team-editorial-rows-supplement.txt`, SHA-256 `6ffaaffbca86f7ce923e4ca9bfc1b3368459410fb09eda663cad61a3ea5b8db3`. Three synthetic profiles, different bio lengths, permitted site image assets; automatic `team.editorial_rows` v1, `editorial_light`, selection reason long/uneven biographies; local deterministic route, provider 0, one write. Root `b441243`; operation `wpae-be7ecdc2342ad7a9`; identity `bb216c65-c8ef-40c8-b861-1d1e1b1385f9`; descriptor revision 6. Brief SHA `8395a224919a98c7981c88ec4e4dfff67051c42eec5a917c109828eeee3628fe`; Plan SHA `fc00860bd0f1e2befbb3d02033a99c397a52bc87754ff93b1d6cfafa213624c4`. Exact names/roles/bios and asset bindings survive independent native JSON readback; public images load. Desktop CSS viewport 1280×720 and mobile 390×844; no horizontal overflow/clipping, natural mobile row growth. The desktop screenshot shows generous empty white space to the right/below short copy and places identity under the portrait while biography sits in the adjacent column. This is a composition-quality weakness, retained as a non-blocking visual note rather than hidden. The first separate Team grid request selected editorial rows, not the requested grid; that root was captured as a negative variation result and operation-scoped Undo restored `[]`. Team grid failure is not counted. Team rows scoped Undo passed; baseline `[]` confirmed.

### About authored-copy split — accepted supplementary case

Fixture `About-authored-split-supplement.txt`, SHA-256 `399d25ab10b36ad2c8468eab6b11d81e02ee55dc7ccaf1d683801c11edd6a06a`. This fixture contains authored long copy; it is **not** the required generated-copy B request. `about.split_40_60.left` v1, explicit left media constraint, `editorial_light`, deterministic route, provider 0, one write. Root `37f832a`; operation `wpae-9d1688c9836fa0bb`; identity `9b7f8ea7-05b8-4b92-8a7d-94dbda0cdc54`; refreshed descriptor revision 5. Brief SHA `4acb0ea1ec1e61269ed9d76a2112db6891c3de251218b6a215382696ccd7feb1`; Plan SHA `0fcfb1e511208c6be6b71b1d1d3cd6a0c3b3313f653ebaf3c34053286af67f8a`. Exact eyebrow/title/782-character body, media URL, and alt survive native readback. The image loaded at 1200×675 with `cover`; desktop H2 and image balance are coherent. Mobile stacks copy before media; text is complete with no horizontal overflow. The unchanged greeting overlaps only the lower image area. Desktop CSS viewport 1280×720, mobile 390×844. Scoped Undo operation `wpae-9d1688c9836fa0bb` reached `already_undone`; post-Undo editor/public reload shows no generated text and root set `[]`.

## Screenshot and image review

Every accepted cycle has fresh public screenshot bytes, verified PNG signatures/dimensions, and visual inspection. Pixel dimensions are intentionally reported separately from the measured CSS viewport because Browser Use captures can omit browser/page scrollbar pixels. The full-frame and overlay/collapsed variants are indexed in `screenshot-manifest.json`; PNG SHA-256 values are listed there.

`ui-ux-pro-max` was read and run first with an architecture/editorial design-system query, then UX long-form readability and typography searches. The design-system output is advisory: its sample palette was not substituted for the page's resolved accent tint. The captured DOM confirms the active accent-derived `rgba(97,206,112,0.2)` section surface, not the dark green reported by Vision in some earlier summaries. Images and pixels were reviewed directly; advisory Vision errors are not used as a PASS substitute.

## Lifecycle and final page state

- Five accepted live cycles: Hero A (`b0d0fc3`), Pricing D (`f071f32`), Testimonials (`34b5567`), Team editorial rows (`b441243`, visual note), About authored split (`37f832a`).
- One additional written Team grid request did not meet its requested composition; its exact root was undone and not counted.
- Core A–E status: **2/5 accepted**. B/C/E are no-write refusals with different causes; no fake roots and no legacy/raw-tree fallback.
- Each written test root was removed only through its operation-scoped guarded Undo. After the final About Undo and reload, editor and public root sets are both `[]`; public contains none of the five generated sample texts. No baseline/user root was deleted or overwritten.
- No stale screenshot cleanup was performed in unrelated historical audits. Unique first FAIL/overlay frames are preserved.

The task remains open because the exact B, C, and E acceptance criteria have no successful saved render on the final source. The About-authored, Testimonials, and Team-row cases are additional evidence, not substitutions for the missing matrix rows.

## Accepted public screenshot gallery

### Hero A — post 5214, root `b0d0fc3`, operation `wpae-14af1036fffe6a37`, identity `1715dfe8-ad6d-4334-a4ad-1d4b0c0828ff`, descriptor revision 5

Public desktop, CSS viewport 1280×720, PNG 1280×720:

![Hero A public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/A-hero-v325-public-desktop.png)

[Open Hero A desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/A-hero-v325-public-desktop.png)

Public mobile, CSS viewport 390×844, PNG 390×844:

![Hero A public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/A-hero-v325-public-mobile-390x844.png)

[Open Hero A mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/A-hero-v325-public-mobile-390x844.png)

### Pricing D — post 5214, root `f071f32`, operation `wpae-5ba97f5fb65fd4fc`, identity `fc3b4459-e0d4-4a58-a4cb-cc13127c1c4f`, descriptor revision 5

Public desktop, CSS viewport 1280×720, PNG 1280×720:

![Pricing D public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/D-pricing-v325-public-desktop.png)

[Open Pricing D desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/D-pricing-v325-public-desktop.png)

Public mobile after normal greeting collapse, CSS viewport 390×844, PNG 375×812:

![Pricing D public mobile](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/D-pricing-v325-public-mobile-collapsed-top-390x844.png)

[Open Pricing D mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/D-pricing-v325-public-mobile-collapsed-top-390x844.png) · [Open mobile CTA frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/D-pricing-v325-public-mobile-actions-390x844.png)

### Testimonials — post 5214, root `34b5567`, operation `wpae-a3fe0270f937ba49`, identity `650a2e4c-3a44-455f-aefc-e853a341e5c4`, descriptor revision 6

Public desktop, CSS viewport 1280×720, PNG 1265×712:

![Testimonials public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/H-testimonials-v325-public-desktop.png)

[Open Testimonials desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/H-testimonials-v325-public-desktop.png)

Public mobile after normal greeting collapse, CSS viewport 390×844, PNG 375×812:

![Testimonials public mobile lower frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/H-testimonials-v325-public-mobile-lower-greeting-collapsed-390x844.png)

[Open Testimonials mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/H-testimonials-v325-public-mobile-lower-greeting-collapsed-390x844.png) · [Open original overlay frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/H-testimonials-v325-public-mobile-lower-390x844.png)

### Team editorial rows — post 5214, root `b441243`, operation `wpae-be7ecdc2342ad7a9`, identity `bb216c65-c8ef-40c8-b861-1d1e1b1385f9`, descriptor revision 6

Public desktop, CSS viewport 1280×720, PNG 1265×712:

![Team rows public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/I-team-rows-v325-public-desktop.png)

[Open Team rows desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/I-team-rows-v325-public-desktop.png) · [Open desktop lower frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/I-team-rows-v325-public-desktop-lower.png)

Public mobile after normal greeting collapse, CSS viewport 390×844, PNG 375×812:

![Team rows public mobile lower frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/I-team-rows-v325-public-mobile-lower-greeting-collapsed-390x844.png)

[Open Team rows mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/I-team-rows-v325-public-mobile-lower-greeting-collapsed-390x844.png) · [Open initial overlay frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/I-team-rows-v325-public-mobile-lower-390x844.png)

### About authored split — post 5214, root `37f832a`, operation `wpae-9d1688c9836fa0bb`, identity `9b7f8ea7-05b8-4b92-8a7d-94dbda0cdc54`, descriptor revision 5

Public desktop, CSS viewport 1280×720, PNG 1265×712:

![About authored split public desktop](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/J-about-v325-public-desktop.png)

[Open About desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/J-about-v325-public-desktop.png)

Public mobile lower frame, CSS viewport 390×844, PNG 375×812:

![About authored split public mobile lower frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/J-about-v325-public-mobile-lower-390x844.png)

[Open About mobile lower PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/J-about-v325-public-mobile-lower-390x844.png) · [Open mobile top frame](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-09-typed-intake-live-v325/screenshots/J-about-v325-public-mobile-390x844.png)
