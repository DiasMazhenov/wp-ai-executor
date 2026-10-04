# User-reported Services regression — 2026-10-04

## Original evidence

The two user-provided PNGs are preserved without conversion:

- `split-editorial.png`: 2412×1502, SHA-256 `482902f637186d5fdca3c9aecb2ff2ac002d9a6dd51e6d8ec20f5fa7c72387e6`.
- `text-icon-list.png`: 1746×1346, SHA-256 `bc3a9be4720a486138e68b8e0d98979b751188fb30cfe80b0e9f7c9737dd7f2a`.

## Live state when reported

- Page: `https://mazhenov.kz/pricing-contract-live-v123/`, Elementor post `5214`.
- Browser Plugin listed the existing Elementor tab (`2`) and public tab (`3`); no WP Pusher tab was open at this check. The editor's inline plugin version was `v02.11.242`.
- CSS viewport at the public tab: `innerWidth=1238`, `innerHeight=923`, `clientWidth=1223`, `clientHeight=923`, `devicePixelRatio=2`.
- Saved test roots remained `[023bd70, e939025, 8b79d6c]`: `services.photo_cards`, `services.split_editorial`, `services.text_icon_list`. The current screenshots show two distinct variants: split lead with image/editorial rows, and native icon/text rows. Those structures differ by recipe on purpose. The three generations are three full sections, so each carries its own `УСЛУГИ` / `Наши услуги` intro.
- Read-only live DOM found no class containing `badge` or `pill`. It found three plain `УСЛУГИ` heading widgets and three `Наши услуги` headings, one intro per generated root. At this viewport the split recipe root measured 1159×260 CSS px; its copy and image columns measured 602.7 px and 510 px. No horizontal overflow was present. The screenshots were not captured at this current viewport, so their CSS viewport is not inferred from raster size.

## Cause

The user-corrected native Services reference is `includes/elementor/imported-templates/services-photo-cards.json`. Its section eyebrow is a white native container with a 2 px `#6b7280` outline, 999 px radius, `0.5rem 1.75rem` padding, and a dark uppercase heading.

The typed Services recipe planner populated eyebrow content but did not set `eyebrow_presentation`. ElementorIR therefore compiled `УСЛУГИ` as a plain H6 instead of a badge container plus heading. This path does not call the old `wpae_llm_badge_widget()` fallback, so the older fallback's badge was not inherited. The visible split/list differences themselves match their two selected recipes; the visual fidelity defect is the missing shared pill. The page also intentionally contains three separate recipe test roots, rather than one production Services section.

## Source correction and local checks

Local source is prepared as `v02.11.243`: typed Services plans default a present eyebrow to `pill`, ElementorIR emits the native outlined Services badge and label from the user reference, and the design-pipeline contract asserts that behavior for all three recipes. The existing legacy default remains unchanged when no Services recipe is explicitly selected.

Local checks after this correction: PHP lint passed; Design Pipeline Contract `309 checks OK`; Flex Generation Runtime `671 checks OK`; Node contracts `6/6`; Elementor patch guard passed; imported template catalog `158 manifests / 156 trees / 156 previews`; package probe `250 files / 0 hash mismatches / 4 scenarios passed`; `git diff --check` passed. These prove source/package behavior only, not installation or live repair.

## Live status

At diagnosis time, no site write, reload, root replacement, or new screenshot was performed. The v242 roots and their original screenshot evidence were left unchanged. Installation, editor reload, guarded replacement, save/reload readback, and post-fix visual acceptance require separate live evidence and are recorded in the continuation report/context after execution.

## Installation and guard follow-up — 2026-10-04

WP Pusher reported that WP AI Executor updated successfully. The active Plugins row showed `v02.11.243`; WordPress Site Health reported PHP `8.3.22`. The first reload of the existing editor still showed v242, so one fresh editor tab was opened for the same post. Its localized editor config and visible LLM chat badge both showed `v02.11.243`; only then was the stale editor tab closed. The existing public tab was restored. No other plugin or WordPress setting was changed.

After installation, public readback still had post `5214` roots `[023bd70, e939025, 8b79d6c]`, three plain `УСЛУГИ` headings and no badge/pill nodes. CSS viewport was `1238×923`, client `1223×923`, DPR 2. The only current editor pending operation is `wpae-patch-6938b90091861944`, revision `4`, root `[3271f43]`; the durable guard says `reviewable=false`, `stale_target`, reason `root_missing`. The supported replacement path requires an exact current operation/root ownership match, so no request was submitted and no roots were replaced or appended (`write_count=0` for this continuation). v243 source, installation and editor config are verified; v243 generation, save/reload readback and post-fix visual acceptance remain **NOT RUN**.

## Stage 5 follow-up — target association and wider visual defects — 2026-10-04

### Fresh baseline

The built-in Browser Plugin is documented through `mcp__node_repl__js`. Current `tabs.list()` showed public page tab `3` and Elementor `post=5214` tab `4`; WP Pusher was not among the open tabs. Public and editor were inspected without creating tabs or submitting writes. Installed/editor config remained v02.11.243 at capture.

Public CSS viewport was `1238×923`, document client `1223×923`, DPR 2; the saved roots were exactly `[023bd70, e939025, 8b79d6c]` and document `scrollWidth=1223`. The exact CTA labels/targets and all three service-matched images remain present; images load at natural `1200×900` with correct alt and cover crop. Fresh Browser Plugin captures, original JPEG bytes and checked/visually inspected PNGs are stored at `../../2026-10-04-services-v243-target-compiler/`:

- `public-baseline-viewport.png`: 1223×912 raster; actual CSS viewport 1238×923.
- `public-baseline-fullpage.png`: 1223×2120 raster; actual CSS viewport 1238×923.
- `editor-baseline-viewport.png`: 1238×923 raster; actual CSS viewport 1238×923.

These are **pre-correction diagnostic** frames, not final save/reload acceptance screenshots. The editor capture shows an empty canvas; Publish and Update are disabled, and the iframe document was not readable. No editor reload or write was performed during this baseline pass.

### Root cause split

The eyebrow defect was fixed in v243: typed recipes now carry the outlined native pill. The public v243 baseline still contains v242-saved roots, so installation alone did not rewrite the page and there are still no pill nodes in the DOM.

The additional split/text-icon defects came from compiler output: split lead and text/editorial rows had zero padding; the secondary rows were white unpadded bars; copy groups used a 24px flex gap; headings inherited `16px/400`. Text/icon set the Elementor widget wrapper to 36px while its native stacked icon emitted a 28px glyph and 14px half-em padding, creating a 56px circle that overlapped copy by 4px. Computed element margins were 0px, so these are generated settings/compiler defaults rather than a global margin rule.

Local v244 source now sets a native 22px glyph/44px circle and wrapper, transparent editorial rows with 16px vertical padding, 8px copy rhythm, a white split lead panel with 24px padding/16px radius, and a distinct 18px/600 service title. Body copy retains the system `type.body` token or uses 16px/400/1.6. Valid explicit surface overrides remain supported, and v243 pill coverage remains in the three-recipe regression. These are source assertions only until the updated runtime is installed and the roots are safely replaced.

### Operation distinction and limitation

The observed stale bootstrap operation `wpae-patch-6938b90091861944` does not own the three current roots: it points to `3271f43`, revision 4, with `root_missing`. The old editor selection path could associate that one global pending candidate with an explicitly selected different root. v244 replaces that behavior with a current root→operation map computed by the existing server ledger and the strict replacement guard; the client blocks before provider/write if the selected generated root has no unique current owner. It does not relax `root_missing`, revision, saved hash, fingerprint, or ownership checks.

The authenticated first-party diagnostics for historical generation IDs `wpae-27aafa3c2441a362`, `wpae-c6b6305322c4b71e`, and `wpae-62307e09f09d9216` were not read during this baseline capture. Therefore it is established that the bootstrap is stale, but **not** established that those ledger entries are absent or that any one has a current fingerprint conflict. No manual owner inference is allowed. There were zero submitted page writes in this continuation; root set is unchanged. v244 source and local tests are complete (Design Pipeline `319`, Flex Runtime `671`, Node `6/6`, patch guard, package hashes 250/250, catalog, PHP lint and diff-check PASS). Commit/push, install, selected-root map, guarded repair, save/reload and final visual screenshots remain separate and **NOT YET VERIFIED**.

## Stage 5 live install result — 2026-10-04

Source commit `ae4f175` / v02.11.244 was pushed to `origin/main`. WP Pusher targeted only WP AI Executor; Plugins confirmed `v02.11.244`, Site Health confirmed PHP `8.3.22`, and the existing Elementor `post=5214` tab showed inline v244 after reload. Before reload, Publish was disabled, Update had the Elementor `elementor-disabled` class, and no unsaved marker was found.

After plugin install, the public URL at CSS viewport `1238×923` contained zero `.elementor-element` nodes and none of roots `[023bd70, e939025, 8b79d6c]`; the reloaded editor canvas also showed no roots. The editor's localized operation fields were `pendingOperation=null` and `targetOperationsByRoot=[]`. No Elementor save or chat generation request was sent; no page write, root append, or manual restore occurred (`write_count=0`). The previous pre-install screenshot/DOM with all three roots remains preserved. This change in rendered state is observed after the installation sequence, but its cause is **UNKNOWN**; saved Elementor data was not read.

Browser Plugin blocked its documented navigation to `/wp-json/ai-executor/v1/design-operations/target` with `net::ERR_BLOCKED_BY_CLIENT` before the server received the read-only request. I did not switch to another transport. The three named historical operations' ledger presence, current state/revision, saved hash/fingerprint comparison and later patches remain **UNVERIFIED**; an empty active owner map cannot distinguish record absence from guard conflict.

Fresh post-reload screenshots were saved from public tab `3` and Elementor tab `4`, CSS viewport/raster `1238×923`, converted from captured JPEG bytes to PNG, format/signature verified and visually inspected. They are [public-after-reload-v244.png](../../2026-10-04-services-v243-target-compiler/public-after-reload-v244.png) (76,238 bytes; blank page plus chatbot greeting) and [editor-after-reload-v244.png](../../2026-10-04-services-v243-target-compiler/editor-after-reload-v244.png) (211,110 bytes; empty canvas and Elementor navigation tutorial). Public mobile is **BLOCKED** because `viewport` is absent from the current Browser Plugin capability list. No composition has post-fix visual acceptance; all three repairs remain blocked because there is no current root in editor/public render and no guarded owner map.
