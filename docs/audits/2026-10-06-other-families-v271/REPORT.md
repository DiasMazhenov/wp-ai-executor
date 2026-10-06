# Other non-FAQ families: live continuation v271

Date: 2026-10-06. Target: existing Elementor `post=5214`; public URL `https://mazhenov.kz/pricing-contract-live-v123/`.

## Scope and prior accepted work

The seven typed families in this comparison are Services, Pricing, Hero, About, Benefits, Team, and Testimonials. Six already had successful live scenarios on v270, so they were deliberately skipped as requested: Pricing (two and three tiers), Hero, About, Benefits, Team, and Testimonials. Their exact operations, roots, screenshots, and separate limitations remain in [the v270 acceptance report](../2026-10-06-lifecycle-layout-v270/REPORT.md). Services A from v270 had been deleted before save/reload readback, so that was not a complete acceptance; the same fixture was run once here to close that gap.

FAQ is excluded by the user's latest instruction. A preceding FAQ attempt had been captured, but its eight JPEG/PNG screenshot files were removed at the user's request. The historic v270 FAQ evidence is retained with its own report. CTA is a separate legacy compatibility smoke test, not one of the seven accepted typed families: the `cta.band` route has no canonical typed record and no proven operation-scoped Undo. It was blocked before any request or write. No fake substitute block was generated.

## Source, install, and live target

- Repository HEAD at start of this continuation: `0b5fda10d7a1fc22528feed05f5f1a5128961885`, branch `main`; runtime source `v02.11.271`, runtime commit `c2328d4e1efd5d32acb48ef4cffce017fdbd4503`.
- Existing Plugins screen independently showed PHP plugin version `v02.11.271`; after reload, Elementor's inline editor config also showed `v02.11.271`. Only WP AI Executor was updated in the prior install step; this continuation made no runtime/source change or version bump.
- Existing authorized tabs and post 5214 were reused. Before generation, editor and freshly reloaded public output both had root set `[]`; no dirty state was present. The user had confirmed clearing the page.
- Exact Services fixture: [`E-services-exact-request.txt`](../../2026-10-05-m3-1-entities/E-services-exact-request.txt), SHA-256 `245e0a8451118781bbe27b0724a2d1325a1cbaf78bf7528e987abc6296cc8795`.

## Live generation: Services

One unchanged request was sent once through the normal WP AI Executor plugin chat. No imported JSON or manual Elementor controls were used. Route was `local_deterministic`; one Brief and one accepted DesignPlan were frozen; `provider_calls=0`, `write_count=1`. Selected recipe was `services.photo_cards`, source `explicit_request`; there was no explicitly selected visual profile, so the recorded safe defaults were used. Brief hash: `7dce09d62950eca4b49455b824c3eac0f0ab06b9f46dd8c8d268f56a8a801e92`. Plan hash: `4b318be730da67807ad1d20e7a24fcd5ad445d0848ea2e32aca04b9b94ee1aab`.

Operation `wpae-750dacf4726ed1f9`, identity `35314405-ae70-40f2-8368-05ade426c175`, accepted contract `contract-1596f84fb2515fe15df00ba0` (hash `1596f84fb2515fe15df00ba012f39616e3a81b6c16a763dc44a6161429c43b01`), generation revision 4, root `77b0786`. The first render was saved before Publish in [`services-first-render-trace.json`](evidence/services-first-render-trace.json) and [`services-first-render-native.json`](evidence/services-first-render-native.json). No repair was applied. Vision's score 60 and its claims of missing card surfaces/broken wrapping were contradicted by the measured public DOM and saved pixels; those findings were not used to trigger cosmetic patches.

The editor's native export after Publish/reload independently contained only root `77b0786`. Compared authored fields against the first accepted tree: titles, full exact descriptions, media URLs/alts, button labels, and hrefs all matched. The readback is in [`services-editor-native-after-reload.json`](evidence/services-editor-native-after-reload.json) and [`services-authored-readback-v271.json`](evidence/services-authored-readback-v271.json).

The exact three assets and alts came from the permitted Services catalog, in service order:

| Entity | Catalog asset | Alt | Rendered state |
|---|---|---|---|
| Strategy | `photo-1772442198689-af331f8f9617` | `Архитектор изучает чертежи у современного здания.` | Loaded, natural 1200×900, rendered 318.3×260, `object-fit: cover` |
| Architecture and design | `photo-1766230976347-c5badd3f76c9` | `Современный архитектурный интерьер.` | Loaded, natural 1200×900, rendered 318.3×260, `object-fit: cover` |
| Support | `photo-1778074762022-c33cc42f79ae` | `Специалисты обсуждают проектные чертежи.` | Loaded, natural 1200×900, rendered 318.3×260, `object-fit: cover` |

### Public geometry and visual review

Public desktop CSS viewport was `1232×923` (DPR 2). The screenshot PNG is `1217×912`; the full-page PNG is `1217×1003`. The section has H2 typography; item titles are H3. The three equal desktop cards measured 368.3px wide with 24px horizontal collection gaps; the image/content interior is 318.3px. All CTA tops share `y=867.6px` and each button is 39px high. Body text, exact order, and distinct CTA hrefs `#strategy`, `#projects`, and `#support` were present with no document horizontal overflow. Detailed computed styles and element geometry are in [`services-public-geometry-v271.json`](evidence/services-public-geometry-v271.json).

The visible composition is only a partial visual pass: `justify-content: space-between` puts a large flexible interval between the copy and footer in the short first and third cards; the longest body naturally reduces that interval. The site-owned greeting/chat overlay covers part of the third CTA (`SITE_OVERLAP`), so full public accessibility of that CTA is not confirmed. No settings, plugin, injected style, or DOM state were changed to hide the overlay. The requested unified CTA axis is achieved; the remaining spacing and overlay issues are recorded rather than patched on-page.

Public mobile is **BLOCKED**: the documented controls for the existing public tab expose no viewport override, and the verified actual public CSS viewport remained 1232×923. Elementor's separate mobile preview was measured at iframe CSS viewport `360×736`; it stacked the cards, loaded all three images, kept the full copy and actions, and had no horizontal overflow. This is editor-preview evidence only, not public-mobile acceptance. Measurements: [`services-editor-mobile-geometry-v271.json`](evidence/services-editor-mobile-geometry-v271.json).

### Saved screenshots

Each PNG below was captured through the existing Browser Use tab after Publish/reload, saved and format-checked, opened, and visually inspected. The mobile-preview image is an editor view; its actual inner preview viewport is listed in the caption.

**Public desktop viewport — post 5214, root `77b0786`, operation `wpae-750dacf4726ed1f9`, identity `35314405-ae70-40f2-8368-05ade426c175`, descriptor r5, public CSS viewport 1232×923, PNG 1217×912.**

![Services public desktop viewport; site-owned greeting overlays part of the third CTA](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-other-families-v271/screenshots/services-v271-public-viewport.png)

[Download public desktop viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-other-families-v271/screenshots/services-v271-public-viewport.png)

**Public full page — same post/root/operation/identity and descriptor r5, public CSS viewport 1232×923, full-page PNG 1217×1003.**

![Services public full-page screenshot](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-other-families-v271/screenshots/services-v271-public-full.png)

[Download public full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-other-families-v271/screenshots/services-v271-public-full.png)

**Editor mobile preview — same post/root/operation/identity and descriptor r5; inner preview CSS viewport 360×736, outer screenshot PNG 1232×923.**

![Services Elementor mobile preview, not a public-mobile screenshot](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-other-families-v271/screenshots/services-v271-editor-mobile-preview.png)

[Download Elementor mobile-preview PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-other-families-v271/screenshots/services-v271-editor-mobile-preview.png)

An editor desktop screenshot after reload is also preserved at `screenshots/services-v271-editor-after-reload.png` (post 5214, root `77b0786`, operation/identity above, editor CSS viewport 1232×923, PNG 1232×923).

## Operation-scoped Undo and restoration

The fresh read-only descriptor after Publish/reload confirmed the same operation, identity, accepted contract, root set, and page at revision 5 with status `available` and `write_count=0`. Guarded Undo then succeeded for only operation `wpae-750dacf4726ed1f9` / root `77b0786`. After editor and public reload, both root sets were exactly `[]`, the Publish control was disabled, and a fresh descriptor reported revision 6 / `already_undone`. No user-owned roots were changed, no manual delete was used, and no whole-document replacement occurred. Baseline restoration is PASS.

## Seven-family matrix and separate CTA route

| Family | Current continuation result | Separate evidence/status |
|---|---|---|
| Services | One live generation, full native/readback and guarded Undo; public desktop exact and aligned CTA axis; visual PARTIAL, public mobile BLOCKED | This report and `evidence/` / `screenshots/` above |
| Pricing | Skipped per user; two- and three-tier cases previously succeeded and were undone | v270 B/C rows and gallery in [v270 report](../2026-10-06-lifecycle-layout-v270/REPORT.md) |
| Hero | Skipped per user; previous successful split generation | v270 D row/gallery |
| About | Skipped per user; previous successful image-left editorial split | v270 E row/gallery |
| Benefits | Skipped per user; previous successful `benefits.editorial_list` generation; right-side whitespace remains a quality caveat | v270 F row/gallery |
| Team | Skipped per user; previous successful four-entity grid; site greeting overlap recorded | v270 G row/gallery |
| Testimonials | Skipped per user; previous successful editorial rows; site greeting overlap recorded | v270 H row/gallery |
| FAQ | Excluded by user; no FAQ generation in this continuation | Eight screenshots from the immediately preceding FAQ attempt were deleted; historic v270 screenshots remain attached to their old report |
| CTA | Blocked before request/write | Legacy `cta.band`, no canonical typed composition and no proof of safe operation-scoped Undo |

The exact machine-readable matrix is [`acceptance-matrix.json`](acceptance-matrix.json). This continuation contains one primary live generation and zero repeats. The final post 5214 root set is `[]`.

## Screenshot cleanup

Removed only the eight JPEG/PNG files from the immediately preceding v271 FAQ attempt: the pre-Publish editor frame, pre-expand public frame, expanded-answer full-page frame, and empty-editor frame. The progress record [`faq-live-progress.json`](../2026-10-06-native-roundtrip-v271/evidence/faq-live-progress.json) now records the deletion and the later user-confirmed empty baseline. Historic v270 FAQ frames and successful-family screenshots were kept with their existing reports because they remain valid historical evidence.

## Separate statuses

- **Source:** v02.11.271, runtime source commit `c2328d4e1efd5d32acb48ef4cffce017fdbd4503`; no code change here.
- **Install/editor:** Plugins PHP and reloaded inline editor independently showed v02.11.271.
- **Generation:** Services one Brief / one Plan / one write; route local deterministic; provider calls 0.
- **Readback/content:** exact native authored fields after Publish/reload; PASS.
- **Desktop geometry:** no horizontal overflow, equal cards and common CTA baseline; site overlap remains.
- **Visual composition:** PARTIAL because flexible content/footer whitespace and `SITE_OVERLAP`; not an overall design PASS.
- **Mobile:** public mobile BLOCKED by unavailable documented viewport control; Elementor 360×736 preview checked separately.
- **Restoration:** operation-scoped Undo PASS; final editor/public roots `[]`.
- **Local tests:** not rerun; runtime did not change. JSON syntax, screenshot signatures/dimensions, and documentation diff checks are run for this evidence update.
- **Commit/push:** report-only changes are prepared separately from runtime. Their commit/push status is recorded after the staged-diff review.
