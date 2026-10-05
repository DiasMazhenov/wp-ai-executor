# v269 cross-family live acceptance — 2026-10-06

## Scope and version boundaries

This continuation completed five distinct families on the existing `post=5214`: Services, Hero, About, Benefits, and Pricing. All requests went through the existing WP AI Executor chat and typed production path. No page or draft was created, no template JSON was imported, and no repair or manual control edit was applied.

| Layer | Evidence |
|---|---|
| Source | `v02.11.269`, local HEAD `5f63cc112fffe98724ab464b8d32c0fe9f367539` (`fix: keep accepted collection geometry through normalization`). No source files changed during this acceptance continuation. |
| Push | The source release had already been pushed and its remote state independently confirmed before this continuation. This is separate from installation. |
| Install | WP Pusher/Plugins showed the installed WP AI Executor PHP plugin at `v02.11.269`. No other plugin or WordPress setting was changed. |
| Editor | After reloading the existing editor, the inline editor config/JS reported `v02.11.269`. |
| Generation | Each case used one request, one Brief, one selected deterministic recipe/record, one compiler/write transaction; the observed provider call count was zero and write count one. |
| Readback | Frozen compiler/readback signatures matched; after Publish and editor reload, the target root and exact content were checked in the editor/public DOM. A complete independent post-reload native JSON export was not retained for every case. |
| Undo | The operation-scoped guarded Undo succeeded after each case. Final editor/server/public root set is exactly `[b48abe1]`. |

The prior v269 source checks remain the code evidence; they were not rerun because this continuation changed no runtime or packaged file. Package hashes therefore remain unchanged. `git diff --check` was run for this documentation update.

## Target baseline and supplied reference

Before the five cases, the observed saved baseline on `post=5214` contained only root `b48abe1` (Services). Each generated root was undone through its own guarded operation before the next case. After the final Pricing Undo, the editor and public DOM again showed only `b48abe1`; no Pricing root/text remained.

The user-supplied Elementor export was treated as read-only. Its root ID is `b48abe1`, matching the preserved baseline Services tree. It was not imported and is not evidence of the generated `services.photo_cards` first render. The Services test used the exact request fixture linked below.

## Live matrix

All captures listed here were fresh public-page captures after Publish and were visually inspected in the Browser Use result. CSS viewport is the actual browser `window.innerWidth × innerHeight`; PNG dimensions are unavailable because the capture bytes could not be saved through the documented Browser Use/CUA interface.

| Family | Root / operation / identity / revision | Recipe and exact source | First render, readback, and measured public layout | Acceptance |
|---|---|---|---|---|
| Services | `2c663fd` / `wpae-87e49a31a93a5752` / `f3995921-56dd-41a7-abb4-d98ef30a6d33` / ledger r4 | `services.photo_cards`, explicit request. Brief `7dce09d62950eca4b49455b824c3eac0f0ab06b9f46dd8c8d268f56a8a801e92`. [Exact Services request](../2026-10-05-m3-1-entities/E-services-exact-request.txt). Three services, full copy, distinct `#strategy`, `#projects`, `#support` links. Existing catalog assets in order: `photo-1772442198689-af331f8f9617` (alt `Архитектор изучает чертежи у современного здания.`), `photo-1766230976347-c5badd3f76c9` (alt `Современный архитектурный интерьер.`), and `photo-1778074762022-c33cc42f79ae` (alt `Специалисты обсуждают проектные чертежи.`). | Public desktop CSS `1232×923`. All three cards, pill eyebrow, copy, images, and CTAs rendered. Site-owned greeting widget intersected the third CTA. Mobile was not captured for this case. | Generation/content and guarded Undo PASS; visual review PARTIAL due to the site overlay and missing mobile view. |
| Hero | `4c5d508` / `wpae-41612dd2fb2d5967` / `8920787b-380b-4f4a-812a-b0be34ecb763` / ledger r4 | `hero.split_60_40.right`; Brief `3aa85d2e19936c14b2829a03bbe8c7095e2bb7476e3a772e638b6ea890a8b8a7`; Plan `fd0c890d18df2d3008e9e79879b14520e673419272f5bc2eacc3af83757d0add`. Exact [B–D fixture](../2026-10-05-visual-policy/B-C-D-exact-request.txt). | Desktop CSS `1232×923`, document width `1217`, no horizontal overflow. Root `x=0,y=527.7,w=1217,h=395.1`; copy `w=669.6`, media `w=446.4`, gap `24`. H1 `56px/700/58.8px`. Allowed Unsplash image loaded at `1200×675`, expected alt. Greeting widget overlapped some image area, not copy/CTA. | Technical/content and desktop composition PASS; mobile NOT RUN; guarded Undo PASS. |
| About | `b4825de` / `wpae-3a0b99add34d4807` / `e59ebcc2-094e-4b5a-8a93-6507a36fea9b` / ledger r4 | `about.split_60_40.right`; Brief `f7ead05920b683b0c44911f1cc22a507ef8b8e9088b617df1cde17ec39cf5ebe`; Plan `07ff57fe9295a81b6c81b1709d1b635260dcc394ed57f5bdf3477d604d57bd99`. Exact [E fixture](../2026-10-05-visual-policy/E-exact-request.txt). Same allowed photo/alt as Hero, CTA `#about`. | Desktop CSS `1232×923`, document width `1217`, no overflow. Root `x=0,y=517.2,w=1217,h=405.8`; copy `w=669.6`, media `w=446.4`, gap `24`. H2 `56px/700/58.8px`, two natural lines. Public mobile CSS `390×844`, document width `375`, no overflow; copy-first, image below; H2 `36px/37.8px`. Greeting widget covered some mobile body/media. | Technical/content PASS; mobile geometry PASS; visual review PARTIAL due overlay. Advisory Vision's “extreme wrapping” warning was contradicted by the measured DOM and pixels; no repair was applied. Guarded Undo PASS. |
| Benefits | `206f35b` / `wpae-0d749e5381b1b802` / `5d1b29d5-ca42-4c56-87ab-879f90083702` / ledger r4 | `benefits.grid`; Brief `89aa68ade81152988f2a7468191283d7d7f0a301b3fe25ba0b11d9622c8c1f92`; Plan `6360ea60677b961d8dc13cc1909b76dbb6d5289865d765ce273c67edbbba2cf1`. Exact [F–H fixture](../2026-10-05-visual-policy/F-G-H-exact-request.txt). Two items, no photo requested and no image emitted. | Desktop CSS `1232×923`, document width `1217`, no overflow. Grid `x=38.5,w=1140`, two `558px` columns with `24px` gap; cards `185px` high, inner padding `24px`. Public mobile CSS `390×844`, document width `375`, one `343px` column, `16px` gap; no images. Greeting widget covered the first mobile heading and part of its icon. | Technical/content and grid geometry PASS; mobile visual review PARTIAL due overlay; guarded Undo PASS. |
| Pricing | `8859f30` / `wpae-e67a76fd2b2199d1` / `5d9f88d1-4266-49ca-b28c-0c2c9ae0f65d` / ledger r4 | `pricing.tiers`; Brief `1029d67d34917aeb74a70dea85e48735ae88446d0b53da61e82c4d4177a44136`; Plan `61c22fc0b165f5e353a11ce0f4be0cb4af439c98b0518906e602f61782501de7`. Exact [I fixture](../2026-10-05-visual-policy/I-exact-request.txt). Two tiers; exact prices/features; CTAs `#start` and `#project`; no media. | Desktop CSS `1232×923`, document width `1217`, no overflow. Root `x=0,y=953.2,w=1217,h=439.6`; grid `x=38.5,w=1140`, two `558px` columns, `24px` gap; cards `295.6px` high with `24px` padding; CTAs fit. Mobile CSS `390×844`, document width `375`, one `343px` column, `16px` gap; cards `287.9px` high. Greeting widget overlapped part of the first card's feature area. | Technical/content and desktop composition PASS; mobile geometry PASS, visual review PARTIAL due overlay. Plan record says `pricing.three_cards` although request has two tiers; native output correctly rendered two columns without an empty card. Guarded Undo PASS. |

## First-render defects, repairs, and quality

These five v269 results are the first public renders for this continuation. No post-write visual patch or repair was applied; the only subsequent mutation was the guarded Undo. Services, About, Benefits, and Pricing were partially obscured by the site's existing greeting widget in at least one tested viewport. The widget was left unchanged. Hero public mobile was not measured, and Services has desktop-only evidence here.

Across the measured viewports, document widths matched the site's content width and no horizontal overflow was measured. Split image loading, copy order, heading semantics, card counts, copy, and CTA destinations matched the selected request. The Pricing record/count mismatch remains a plan-level discrepancy even though the compiler rendered the actual two requested tiers correctly. Therefore technical lifecycle PASS is distinct from design-quality PASS: the latter is PARTIAL for this matrix, not a blanket PASS.

## Screenshot artifact status

**SCREENSHOT BLOCKED — local PNG artifacts were not produced for these five cases.** Browser Use/CUA supplied the fresh captures as JPEG image bytes in its result, and the images were visually inspected there. The documented CUA surface exposes screenshot capture and image emission but no supported local-file writer or shared binary handoff into the repository. Therefore the captures could not be persisted to an approved absolute path, converted to PNG, reopened from disk, dimension-verified, or linked. No PNG path/dimensions are claimed. The screenshots were taken from the public page after Publish, not from Elementor's mobile preview. The public-page mobile viewport was CSS `390×844` for About, Benefits, and Pricing; no equivalent public-mobile capture was made for Services/Hero.

## Remaining matrix limits

This completes the user's minimum of five different families. It does not complete every originally listed B–J variant: Hero left/soft-cards, Benefits list/alternate profile, and native FAQ were not part of this five-case continuation. Services reference JSON was not inserted. Structured model extraction remains inactive. The final post root set is `[b48abe1]`.
