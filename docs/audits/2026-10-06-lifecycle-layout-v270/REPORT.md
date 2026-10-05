# Lifecycle synchronization and shared card layout — v270 checkpoint

Date: 2026-10-06 (Asia/Almaty)
Target: existing WordPress post `5214`; public URL `https://mazhenov.kz/pricing-contract-live-v123/`
Scope: refresh of owned operation descriptors, common repeated-card geometry, and requested live family matrix A–J.

## Outcome

The common lifecycle and card-layout source changes were implemented, locally checked, released once as v02.11.270, pushed, installed, and independently observed in the PHP plugin and reloaded editor config. The current accepted source path remains BriefIR → DesignPlan → ElementorIR → native compiler → validation → transaction/readback.

Live acceptance did not start. The fresh editor/server and fresh public page now show `rootIds=[]`; the task’s required preserved baseline `b48abe1` is absent. The old Services test operation `efdd267` has an expired contract, and the historical `b48abe1` operation descriptor is missing. No current baseline can be safely restored through those operations. No JSON was imported, no root was manually changed, and no generation/Undo write was issued in this continuation. Thus v270 source, release, installation, local tests and screenshot workflow are confirmed; v270 live generation and visual acceptance remain NOT RUN.

The earlier generic `SCREENSHOT BLOCKED` explanation was incorrect as a capability statement. The documented Browser Use route was exercised successfully: bundled `browser-client.mjs` → `setupBrowserRuntime()` → existing iab tab → `tab.screenshot()` → Node `fs/promises.writeFile()` → JPEG-to-PNG conversion → `file`/`sips` verification → `view_image` inspection. This produced five diagnostic PNGs. None is a v270 generated-block acceptance screenshot.

## Source, commits, push, install

| Layer | Evidence | Status |
|---|---|---|
| Source version | `v02.11.270`, local HEAD `5c35b5cac6bee711231b18b6c6dd2859c2bcb777` | Confirmed |
| Lifecycle commit | `349a618cba41c7614f5064c4198512edb55f0b89` | Committed |
| Scoped repair race guard | `9fd5c8415f612b3a255ff4ffb4bc066462895344` | Committed |
| Layout/compiler release | `5c35b5cac6bee711231b18b6c6dd2859c2bcb777` | Committed |
| Push | `origin/main` independently returned `5c35b5cac6bee711231b18b6c6dd2859c2bcb777` | Confirmed |
| WP Pusher | Updated only WP AI Executor in its existing tab | Confirmed success notice |
| Installed PHP plugin | Plugins page displayed `v02.11.270` | Confirmed |
| Editor JS/config | Reloaded existing post=5214 editor; inline config displayed `v02.11.270` | Confirmed |
| Post writes | None in this v270 continuation | `write_count=0` |

No WordPress settings or other plugin were changed. The existing editor was reloaded only after the Publish control was disabled. Existing authorized tabs were reused; no extra editor tab was created.

## Lifecycle descriptor finding

### Source change

`assets/js/elementor-llm-chat.js` now has one descriptor refresh helper for the existing typed lifecycle controls. The helper calls the read-only operation-description path and accepts refreshed state only when post, operation ID, identity, accepted contract, and the complete owned-root set still match the operation in the UI. It updates the acknowledged revision only after that scope check. Verify, Resync, Undo, and Repair use the helper where the stale descriptor mechanism applies. Scoped Repair also snapshots the full editor model before refresh and compares it after refresh.

Server-side guards in `includes/elementor/accepted-contract.php` and the existing ledger/transaction path still require exact revision, identity/contract, owned roots, fingerprint/lineage and full document model. Dirty-editor and model-race protections remain active. There is no blind write retry.

### Live evidence and limit

Before the v270 reload, the existing v269 read-only “Проверить owned модель перед Save” path returned `Owned fingerprint или lineage изменились.` The fresh v270 bootstrap contains:

- `wpae-e387c462a25a14dc`, identity `5746a737-6c38-44e5-bbea-e323dfb8979a`, root `efdd267`, revision `6`, status `unavailable`, reason `contract_expired`;
- historical operation for root `b48abe1`, revision `6`, status `unavailable`, reason `contract_missing`.

The fresh descriptor is unavailable, so the current editor does not offer a usable read-only Verify control for this operation. The prior response therefore does not isolate a stale-revision-only cause; it specifically reports fingerprint/lineage mismatch. The hypothesis that native Publish only made the UI revision stale remains unproven. A guarded Undo against the expired descriptor would not be safe and was not attempted. No root was deleted manually.

### Regression coverage

Lifecycle regressions cover native Save advancing revision, read-only refresh of the same operation scope, guarded Undo using refreshed revision, identity/contract/root-set mismatch refusal, document/revision race refusal after refresh, preservation of a neighboring baseline root, and the same full-model race protection for scoped Repair. UI suite: 18/18 Node tests pass; PHP typed lifecycle/runtime tests are included in the checks below.

## Shared card and typography policy

No new generator, family catalog, or planning DSL was introduced. The existing accepted Plan, IR and compiler own these decisions:

- `type.section_title` separates ordinary section-intro scale from Hero display scale. Heading semantics remain independent: Hero H1, ordinary section intro H2, item title appropriate H3. Historical Plans keep their prior defaults.
- Repeated groups use native Grid fractional tracks and explicit gap, so tracks consume available container width after gaps. Fixed percentage compensation is not the source of truth.
- Typed card IR separates `card_body` from optional `card_actions`. On desktop, remaining vertical space sits between body and action footer, aligning CTAs at the row bottom while copy intervals stay natural. Mobile cards stack and grow with exact content; no fixed height, clamp, or content hiding was introduced.
- Footer/actions are absent when a Brief has no actions; Team and Testimonials do not acquire invented CTA controls.
- Existing Pricing composition IDs remain compatible. A Plan stores actual `item_count`; native output emits two or three actual tiers and no empty third placeholder. Responsive columns follow Plan.
- Existing token, profile, composition-record and explicit Brief priority remain in the existing resolver/compiler chain.

Local evidence supports these contracts, not browser pixels. No live comparison using identical prompt/profile before and after v270 was possible in the current post state.

## Current target state and prior Services visual evidence

Fresh editor bootstrap for `post=5214`:

- valid Elementor array, `rootIds=[]`;
- saved JSON SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`;
- empty saved HTML SHA-256 `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`;
- `targetOperationsByRoot=[]`, `pendingOperation=null`.

Fresh navigation to the public page also has `rootIds=[]`. Before that navigation, the already-open public tab showed old DOM roots `[b48abe1, efdd267]`. Those visible sections were stale-tab content; they must not be presented as current saved output.

The historical public Services screenshot does show the shared visual issue under investigation: the cards preserve their images, pill, and full descriptions, but their CTA buttons sit at visibly different heights, and short cards leave substantial unused space under the button. The AI-Dana/site chat overlay is page-owned and intersects the third CTA in the viewport image. This screenshot is retained as v269 diagnostic evidence only.

### Stale-tab Services diagnostic

Post 5214, visible stale public roots `[b48abe1, efdd267]`; source public tab before fresh navigation; CSS viewport `1643×1231`; PNG `2163×2615`.

![Previously open public Services tab retained as stale visual evidence](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/services-v269-current-public-full.png)

[Download stale public diagnostic PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/services-v269-current-public-full.png)

### Fresh public baseline

Post 5214; public source after fresh navigation; root set `[]`; CSS viewport `1643×1231`; full-page PNG `2189×1640`.

![Fresh public baseline with no generated roots](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-public-full.png)

[Download fresh public baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-public-full.png)

### Fresh editor baseline

Post 5214; Elementor editor after reload; source inline config v270; root set `[]`; CSS viewport `1643×1231`; PNG `1642×1231`; Publish disabled.

![Fresh Elementor editor baseline on v270](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-editor-v270.png)

[Download fresh editor baseline PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-06-lifecycle-layout-v270/screenshots/baseline-empty-editor-v270.png)

Additional PNGs preserve the stale editor refusal and the stale public viewport framing. All five current files are valid PNGs with the sizes recorded in `current-state.json`; their JPEG source bytes are preserved beside them. They were opened and visually reviewed. Editor/public screenshots are distinct evidence types.

## Acceptance matrix A–J

The full machine-readable matrix is [`acceptance-matrix-A-J.json`](acceptance-matrix-A-J.json). It distinguishes prior-version evidence from the requested v270 acceptance. **Every v270 generation scenario is NOT RUN.** The historical A Services operation is especially relevant: its old operation contract expired; the historical baseline operation is missing; current saved roots are empty. The historical root list is not authority to recreate any root.

| Scenario | Requested composition | Historical evidence (not v270 acceptance) | v270 status |
|---|---|---|---|
| A | Services `services.photo_cards`, three exact items/assets/CTA | v269 Services recovery: `efdd267`, `wpae-e387c462a25a14dc`, identity `5746a737-6c38-44e5-bbea-e323dfb8979a`, initial revision 4; later descriptor revision 6 expired. Historic output had exact copy/assets, but CTA y positions differed and site overlay covered CTA 3. | `BLOCKED_BASELINE_MISMATCH`; no retry |
| B | Pricing, exact two-tier request I | v269 `8859f30` / `wpae-e67a76fd2b2199d1`; exact two cards rendered, historical guarded Undo passed; legacy record name `pricing.three_cards`. | `NOT_RUN` |
| C | Pricing, exact three-tier M3.1 F | No historical live run found. | `NOT_RUN` |
| D | Hero split, image right, editorial-light | v269 `4c5d508` / `wpae-41612dd2fb2d5967`; historical desktop evidence only, Undo passed. | `NOT_RUN` |
| E | About editorial split, image left | v269 `b4825de` / `wpae-3a0b99add34d4807` used a different right-image record; historical Undo passed. | `NOT_RUN` |
| F | Benefits `editorial_list`, no photos | v269 Benefits grid is not this composition; no matching editorial-list run. | `NOT_RUN` |
| G | Team grid, four people, no fabricated portraits | v266 root `3178f61` / `wpae-df7ea9c2e4a4d350`; historical review required overlay/readability follow-up. | `NOT_RUN` |
| H | Testimonials `editorial_rows`, no ratings/photos | v266 root `bcc1d46` / `wpae-06bb72bdb0e0881b`; historical desktop row result only. | `NOT_RUN` |
| I | Native FAQ Accordion, every item opened | v264 root `59a770e` / `wpae-0198aa58bfe9eaaf`; historical settled mobile evidence and guarded Undo exist. | `NOT_RUN` |
| J | Standalone CTA compatibility smoke, two exact links | No run. The route is legacy `cta.band`; there is no canonical typed CTA record, and safe scoped lifecycle was not proven for it. | `NOT_RUN`; no request/write |

Primary generations in this v270 continuation: `0`. Repeats: `0`. No prompt was changed to hide a defect. No first-result repair was needed because no v270 result exists.

## Local checks and package status

These results were run for the source release before docs-only updates:

| Check | Result |
|---|---|
| `php -d memory_limit=512M tests/design-pipeline-contract.php` | `848 checks OK` |
| `php -d memory_limit=512M tests/flex-generation-runtime.php` | `1380 checks OK` |
| `node --test tests/*.test.js` | 18/18 pass |
| `php tests/elementor-patch-guard.php` | PASS |
| `php tests/imported-template-catalog.php` | 158 manifest / 156 retrievable / 156 previews / 5 FAQ candidates / 2 Services candidates |
| PHP lint of changed PHP files | 10 files; all syntax checks pass |
| `php docs/audits/2026-09-12/package-probe.php` | 252 manifest files; 0 hash mismatches; 4 probe scenarios pass |
| `git diff --check` | PASS on the runtime release before report update; rerun after docs changes |

An early package probe after the follow-up JS edit detected a stale package hash; `wpae-package.json` was updated and the final probe passed. The full diagnostic JSON still has the pre-existing malformed UTF-8 serialization finding; its compact summary succeeds. No test result proves live DOM or visual quality.

## Separate status summary

| Acceptance layer | Status |
|---|---|
| Source | v270 committed |
| Push | origin/main independently confirmed at v270 HEAD |
| Install | WP AI Executor PHP v270 confirmed |
| Editor JavaScript | Inline config v270 after reload |
| Current saved/public root state | Both empty `[]`; required historical baseline absent |
| Lifecycle fix | Source regressions pass; live stale-revision cause not proven because old accepted contract expired |
| Live generation | 0 cases under v270 |
| Native readback | Current empty-state bootstrap confirmed; no new generated native tree to compare |
| Responsive geometry | No v270 generated root; NOT RUN |
| Visual composition | Historical Services defect retained; v270 quality NOT RUN |
| Screenshot workflow | Browser Use bytes saved and PNGs checked/opened; generated-block screenshots NOT RUN |
| Undo/document restoration | No v270 mutation to undo; `b48abe1` restoration unresolved |

Historical and current status differences are retained in `current-state.json` and the exact matrix. No full M3.1, cross-family, or v270 live visual acceptance is claimed.
