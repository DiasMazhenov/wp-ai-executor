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

The five rows below preserve the original v269 run evidence. Their public JPEG captures were inspected in Browser Use but were not saved as files. A later screenshot recovery attempt re-ran Services and is documented separately below; it has a new operation and root and does not replace the historical run IDs.

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

## Screenshot recovery and guarded Undo refusal — 2026-10-06

The earlier categorical claim that Browser Use had no supported local-file writer was incorrect. The supported workflow is available in the bundled Browser Use runtime: import `browser-client.mjs`, call `setupBrowserRuntime()`, select the existing browser with `type === "iab"`, list/get the already-open tabs, capture with `tab.screenshot()`, and persist the returned `Uint8Array` with Node `fs.writeFile()`. The actual public capture returned JPEG bytes; `sips` converted them to PNG, `file`/`sips` verified format and dimensions, and the saved PNG was opened and visually inspected. No alternate transport or raw CDP was used.

The exact Services fixture was re-run through the existing plugin chat on `post=5214`, then Published. It produced one Brief, one explicit `services.photo_cards` decision, `provider_calls=0`, `write_count=1`, Plan hash `4e3ce08c40f1034bfa608a2f21815fe75d86208490184ce9771f3936a9b42fa8`, operation `wpae-e387c462a25a14dc`, identity `5746a737-6c38-44e5-bbea-e323dfb8979a`, ledger revision 4, and root `efdd267`. The Brief hash and exact prompt match the earlier Services fixture. The saved pipeline trace is [services-generation-trace.json](screenshots/services-generation-trace.json).

After Publish and a public-page reload, the live DOM contained baseline root `b48abe1` and test root `efdd267`; all three supplied service descriptions and CTA labels/hrefs matched. The three catalog images loaded at `1200×900` with the expected catalog alts. Document width was `1217px`; actual public CSS viewport was `1232×923`. The existing AI-Dana widget overlapped part of the third CTA in the scrolled viewport capture; in the full-page capture its bubble fell over lower-right card whitespace. Visual acceptance therefore remains PARTIAL. The fresh public full-page PNG is `1217×1988`; the scrolled viewport PNG is `1217×912`. The editor refusal PNG is `1232×923`.

The first result was captured before Undo. The operation-specific Undo button was marked available for `wpae-e387c462a25a14dc`, but the normal UI returned `Точная операция/revision не подтверждены.` A subsequent public reload still showed root `efdd267`; no manual removal was attempted. The editor refusal screenshot and public PNGs are preserved below. Since guarded Undo/readback did not restore the exact baseline, the four dependent screenshot re-runs were stopped. Their earlier test runs and historical Undo results above remain separate evidence; fresh PNG artifacts were not obtained for those earlier operation IDs. No public-mobile screenshot was captured: the current public tab's actual CSS viewport stayed `1232×923`, and this Browser Use tab surface exposed no documented viewport setter. Editor mobile preview is not represented as public mobile.

### Fresh screenshot evidence

**Public Services first result** — post `5214`, root `efdd267`, operation `wpae-e387c462a25a14dc`, ledger revision 4, `services.photo_cards`; source: public page after Publish and reload. Actual CSS viewport `1232×923`; full-page PNG `1217×1988`.

![Published Services first result on the public page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-cross-family-v269/screenshots/services-after-publish-public-full.png)

[Download Services public PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-cross-family-v269/screenshots/services-after-publish-public-full.png)

The scrolled viewport frame shows the fixed AI-Dana bubble crossing the third CTA; PNG dimensions `1217×912`, same CSS viewport `1232×923`.

![Services scrolled viewport with site widget overlap](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-cross-family-v269/screenshots/services-after-publish-public-desktop.png)

[Download Services viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-cross-family-v269/screenshots/services-after-publish-public-desktop.png)

**Guarded Undo refusal** — post `5214`, same root/operation, source: Elementor editor; actual CSS viewport and PNG dimensions `1232×923`.

![Elementor editor guarded Undo refusal](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-cross-family-v269/screenshots/services-undo-refusal-editor.png)

[Download Undo refusal editor PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-cross-family-v269/screenshots/services-undo-refusal-editor.png)

## Remaining matrix limits

The original five-family run completed its minimum. The screenshot recovery is incomplete: only the new Services operation has saved/reopened PNGs; fresh screenshots for the other four earlier operations were not captured, and dependent re-runs stopped at the Undo guard. The original B–J variants Hero left/soft-cards, Benefits list/alternate profile, and native FAQ remain outside that five-family run. Services reference JSON was not inserted. Structured model extraction remains inactive. The root set after the original five operations' guarded Undos was `[b48abe1]`; the current public DOM after the new Services Undo refusal shows `[b48abe1, efdd267]`.

## Screenshot workflow documentation update

The exact supported capture-and-save method is now documented canonically in [`context.md`](../../../context.md) and summarized in [`LUNA_HANDOFF_REPORT.md`](../../../LUNA_HANDOFF_REPORT.md): use the bundled Browser Use runtime and an existing `iab` tab, save `tab.screenshot()` bytes using Node `fs/promises.writeFile()`, convert JPEG when needed, verify and open the PNG, and report CSS viewport separately from raster dimensions. Treat generic, unverified `SCREENSHOT BLOCKED` status text as a claim to verify rather than a confirmed capability limit. Actual Browser Use security/tool refusals and observed screenshot or filesystem failures remain blockers and must be recorded exactly; this rule does not authorize alternate-transport workarounds. No missing screenshots were fabricated or retroactively inferred.

## Superseding state check — v270, 2026-10-06

The historical operation screenshots above remain accurate for `efdd267`; this section updates the *current* page state and corrects the interpretation of the older `SCREENSHOT BLOCKED` labels. Screenshot bytes can be persisted through the bundled Browser Use client and Node `fs/promises.writeFile()`. The original five v269 operation IDs still do not have saved PNGs, so their per-operation evidence remains missing; the earlier statement treated that omission as a tool limitation and was incorrect.

Runtime `v02.11.270` is installed and the existing reloaded editor inline config reports v270. Current saved editor JSON and a fresh public navigation both show `rootIds=[]`, JSON SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`, and empty public HTML hash `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`. Before fresh navigation, the existing public tab still displayed stale `[b48abe1, efdd267]`; that stale tab view is retained below as diagnostic evidence, not current server state. The expected `b48abe1` baseline is absent. This continuation issued no post write, generation, or manual root operation.

The fresh v270 bootstrap reports operation `wpae-e387c462a25a14dc`, identity `5746a737-6c38-44e5-bbea-e323dfb8979a`, root `efdd267`, revision 6, `unavailable/contract_expired`; the historical b48 operation is `contract_missing`. The previous read-only verification returned `Owned fingerprint или lineage изменились.` The revision-only Undo hypothesis is not confirmed. Guarded Undo was not attempted against the expired descriptor, and dependent generations remain unrun.

Fresh diagnostic PNGs (all bytes saved, signature/dimensions checked, opened and visually inspected):

**Previously open stale public tab** — source public before fresh navigation, post 5214, old roots `[b48abe1, efdd267]`, actual CSS viewport `1643×1231`, full-page PNG `2163×2615`. This shows the old Services composition, uneven CTA baselines and excess whitespace under short cards. It is not v270 generation evidence and is not a fresh saved-state render.

![Stale public Services page retained for visual diagnosis](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/services-v269-current-public-full.png)

[Download stale public diagnostic PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/services-v269-current-public-full.png)

**Fresh public baseline** — source public after navigation/reload, root set `[]`, CSS viewport `1643×1231`, full-page PNG `2189×1640`.

![Fresh public page with no generated roots](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-public-full.png)

[Download fresh public baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-public-full.png)

**Fresh editor baseline** — source Elementor editor after reload, post 5214, root set `[]`, CSS viewport `1643×1231`, PNG `1642×1231`; inline source version v270 and Publish disabled.

![Fresh v270 editor baseline](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-editor-v270.png)

[Download fresh editor baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-editor-v270.png)

See [the v270 lifecycle/layout audit](../2026-10-06-lifecycle-layout-v270/REPORT.md) for code/test/release evidence and current scenario statuses. No Services/Hero/About/Benefits/Pricing/Team/Testimonials/FAQ/CTA block was generated under v270 in that continuation.


## Separate v270 continuation — 2026-10-06

The historical v269 case table above remains version-specific. A later v270 run generated nine first results on the user-confirmed empty post 5214 baseline, including a fresh Services generation (root `bcfeab4`) which the user deleted before post-Publish readback, two Pricing counts, Hero, About, Benefits, Team, Testimonials and native FAQ. This does not retrofit PNG evidence onto the older v269 operations. Seven v270 guarded Undos restored `[]`; FAQ root `1a1d059` remains after a safe `owned_fingerprint_changed` refusal. See [v270 live report](../2026-10-06-lifecycle-layout-v270/REPORT.md) and its [measurement/screenshot index](../2026-10-06-lifecycle-layout-v270/live-measurements-v270.json).
