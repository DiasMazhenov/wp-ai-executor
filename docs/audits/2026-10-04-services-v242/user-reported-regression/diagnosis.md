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
