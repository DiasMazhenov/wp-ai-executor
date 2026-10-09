# Universal section pill and heading — v02.11.326 checkpoint

Date: 2026-10-09. Page: existing WordPress post `5214` only.

## Source change and release

The user clarified that every generated section must carry both a section-level overline rendered as a pill badge and a main heading. This is now a plugin contract, not a context-only note.

- `wpae_design_plan_apply_section_header_contract()` freezes `section_header` version 1 into accepted DesignPlan for the migrated families. Exact supplied `eyebrow`/`label` and top-level `title` retain their Brief refs, spans, and provenance. If absent, a neutral family-language fallback is used. Hero uses H1; other section families use H2. The Plan rejects a contradictory explicit plain-only eyebrow instead of silently dropping it.
- The native Elementor compiler emits an editable pill container and label plus the frozen section heading. Pill-box styling belongs to the container; the label carries text/typography. Compiler does not choose a new profile.
- Tests cover all eleven family defaults, supplied heading provenance, native pill output, semantic levels, and grouped item titles not being promoted to section headings.
- Runtime source: `v02.11.326`, commit `223c9d03723eeb20ceb4b15f6b1d7721640a43bf`. Package hashes were refreshed. Scoped runtime commit contains the source, tests, main plugin version, and package manifest.
- Push succeeded; an independent `git ls-remote origin refs/heads/main` matched the same SHA.
- WP Pusher reported success updating only WP AI Executor. Plugins showed `v02.11.326`; after reload the existing Elementor editor inline config also showed `v02.11.326`.

## Checks

- PHP lint: changed PHP files PASS.
- Design Pipeline Contract: 999 checks PASS.
- Flex Runtime: 1601 checks PASS.
- Elementor patch guard PASS.
- Node suites: 27/27 PASS.
- Imported template catalog: 158 manifests, 156 retrievable, 156 previews instantiated.
- Package probe: 253 files, zero hash mismatches, four scenarios PASS.
- `git diff --check` PASS.

## Live state and results

The existing editor was confirmed on `post=5214`, version v326, with Publish disabled (the user-defined saved state) and no roots. Public page `https://mazhenov.kz/pricing-contract-live-v123/` was checked at CSS viewport 1280×720 and had no Elementor roots. Every attempt below was a unique request through the ordinary plugin chat. All five stopped before the transaction; none produced an operation or root. The public/editor root sets remain `[]`. Since there is no generated result to review, no design screenshot was captured or counted.

| Case | Fixture | Result | Safe evidence | Write |
|---|---|---|---|---:|
| A Hero | [v321 A fixture](../2026-10-09-typed-provider-contract-v321/fixtures/A-hero-generated.txt), SHA-256 `387237de2ea9c9e44b7658776a80a576da3d8c378b6e370fd36c77d54ba679df` | `PREWRITE_REFUSAL` | HTTP 200 carried an upstream error envelope with embedded code 502, type `provider_unavailable`, message `JSON error injected into SSE stream`; one provider/HTTP call, provider/model identity unknown | 0 |
| B About | [v321 B fixture](../2026-10-09-typed-provider-contract-v321/fixtures/B-about-hybrid.txt), SHA-256 `5518b72b6405cc59ae7f97aa6ef7a43bb7d3e10dc303db3153ab14cba91fd06b` | `PREWRITE_REFUSAL` | HTTP 200, no provider error envelope; Nvidia model returned; semantic refusal `unreferenced_labeled_fact`; one provider/HTTP call, 13,656 ms | 0 |
| C Benefits | [C fixture](fixtures/C-benefits-generated.txt), SHA-256 `e315216c010f5b3c6e541c38777ed1d444aa7c897cfa87af36097d1b70f51885` | `PREWRITE_REFUSAL` | Requested `benefits.grid`, `editorial_light`; HTTP 200, Nvidia model, semantic refusal `unreferenced_labeled_fact`; one provider/HTTP call, 7,310 ms | 0 |
| D Pricing | [D fixture](fixtures/D-pricing-two-tiers-generated-intro.txt), SHA-256 `6075f78e522f4d1090c9b1c64496c32f49ce46cfe9f736354da1f3f944368a38` | `PREWRITE_REFUSAL` | Two tiers requested. One HTTP call returned `finish_reason=length` after 76,488 ms; bounded retry ended with `cURL error 28` timeout after 13,511 ms. Total provider calls 2, reported latency 90,083 ms. Strict `json_schema` and `require_parameters=true` remained enabled. | 0 |
| E CTA | [E fixture](fixtures/E-cta-centered-generated-copy.txt), SHA-256 `6b0f0557c268c2f3cf7a8c428d4279ec4bd55181064a9247c627ef8412c841b2` | `PREWRITE_REFUSAL` | Requested `cta.centered`, `editorial_light`. Deterministic Brief passed, but Plan rejected `unbound_explicit_content:text`; provider calls 0, write 0. Diagnostic request correlation ID `5d7b9d83-3301-4aa4-935c-6d9647835238` is not an operation ID. | 0 |

**New full live acceptances: 0/5.** No scenario reached frozen DesignPlan/native compilation and transaction/readback as a complete generated result. No operation-scoped Undo was needed because there were no writes. Baseline restoration is confirmed by unchanged empty roots, not by an Undo operation.

For A/B, the historical v321 HTTP 400 causes are documented by the later v325 audit: unsupported `uniqueItems` in the OpenRouter strict schema for A, and an upstream free model that did not support `json_schema` for B. The v323 adapter removed only `uniqueItems` from the wire schema while retaining canonical server validation and strict provider parameters. The v326 repetitions did not reproduce those HTTP 400s: A returned a 502 error envelope inside HTTP 200; B refused during semantic provenance validation. Therefore the historical errors remain diagnosed by the v325 evidence, and the current A/B attempts do not count as successful generation.

E exposed a separate Plan binding defect: the Brief parser treated a fact phrase as a `text` slot, while CTA Plan construction did not bind that slot, so Plan validation failed closed. The refusal preserved the source content and prevented legacy fallback and writes. It does not show that the mandatory header compiler failed; the compiler was not reached.

## Status

- Mandatory pill + section heading in plugin source: **implemented, locally checked, pushed, installed**.
- A–E live generation attempts on v326: **5 distinct requests, 0 writes, 0 full acceptances**.
- Content, structure, public DOM, responsive visual review, PNG, and screenshot gallery for these new scenarios: **NOT RUN**, because no root was generated.
- Final root set on post 5214: `[]` in editor and public page.
- The five-acceptance gate remains open. The output is not a live visual acceptance of the new rule.

The existing v321 and v325 reports remain historical and are not rewritten as v326 evidence.
