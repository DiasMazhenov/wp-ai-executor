# Repeatable item surface contract and typed Process — 2026-10-07

## Result

The runtime work is on source `v02.11.279` (`554569b73b16a9e3753d2adef9a8ea1d176922e1`). It adds a versioned semantic owner for repeated-item surfaces, registers `process.ordered_steps` in the typed composition catalog, and fixes Services planning context so an explicitly selected record and visual profile survive into the accepted DesignPlan. v279 was installed through WP Pusher; Plugins PHP and the reloaded editor inline config both reported v279. The final public and editor root sets on existing post 5214 are `[]`.

The fixed Services run, Process, and Benefits all completed Publish, reload, read-only owned-model confirmation, public DOM review, screenshot capture and operation-scoped guarded Undo. Desktop visual/composition checks pass. Mobile topology and content remain visible in DOM with no horizontal overflow, but overall mobile visual status is **PARTIAL** because the site-owned greeting overlaps generated content in each mobile capture. The external chat was not modified.

## Confirmed surface failure

The v277 Services `services.text_icon_list` public screenshot shows the defect: a wide white rectangle begins at the copy column while the blue icon sits outside that rectangle. The row is semantically one item, but its surface is split between sibling containers.

The v277 native builder creates `services_text_icon_row` with an icon sibling and a `services_text_icon_copy` container holding body and actions. Before the new contract, compiler controls treated `color.surface` as an instruction to paint containers. The list collection role was explicitly made transparent, while the nested copy role was not; there was no accepted Plan field naming one semantic surface owner. As a result, the copy wrapper could receive the white background/padding/radius while the icon-bearing row remained transparent. Similar role-based styling touched `entity_copy`, `pricing_details`, `card_body`, and family card wrappers, so the ownership ambiguity was cross-family rather than specific to Services.

Evidence and implementation pointers:

- Prior implementation at `d874b3f^:includes/elementor/elementor-ir.php`, where generic container control assignment reads `color.surface` independently of a surface-owner decision; the transparent-role list includes the Services list collection, not `services_text_icon_copy`.
- Current [DesignPlan owner resolver](../../../includes/llm/design-plan.php#L1669) resolves `mode`, `owner_role`, background, border, radius and responsive padding before freeze.
- Current [IR binding](../../../includes/elementor/elementor-ir.php#L506) marks each node `owner`, `interior` or `outside` from that frozen owner role.
- Current [native visual compiler](../../../includes/elementor/elementor-ir.php#L738) paints the declared owner, clears unintended native surface controls on interior wrappers, and preserves only explicit panel/override intent. `color.surface` remains a value; it no longer paints every nested container.
- [Composition records](../../../includes/elementor/recipes.php#L420) declare the owners for Services icon cards and Process steps. The same owner contract is mapped for Benefits, Pricing, Team and Testimonials. Legacy frozen Plans without contract v2 retain the prior compatibility path.

The v277 [Services screenshot](../2026-10-07-flex-remaining-v277/screenshots/services-public-desktop.png) and [Benefits screenshot](../2026-10-07-flex-remaining-v277/screenshots/benefits-public-desktop.png) were opened and inspected for diagnosis. The v277 FAQ capture shows its own native accordion surface and was not treated as a repeatable card. No FAQ generation was run in this stage.

## Architecture and scope

The pipeline remains `BriefIR → DesignPlan → ElementorIR → native Flex compiler → validation → transaction/readback`.

- **BriefIR** retains exact text, item ownership, source spans and explicit composition/profile intent.
- **Composition record** owns topology, responsive column limits and the semantic surface owner.
- **Accepted DesignPlan** resolves the visual profile and copies the v2 surface contract before freeze. It names card or transparent-divider mode and responsive padding. Explicit user overrides retain precedence.
- **ElementorIR** binds owner/interior/outside scope to semantic roles. The compiler does not choose the composition or visual profile.
- **Native compiler** maps only the owner surface to background, border, radius, padding and optional shadow. Body/copy/actions/layout descendants are transparent with zero duplicated box styling unless the accepted Plan explicitly declares a panel.
- **Validation and transaction/readback** keep the existing content, identity, target, ownership, dirty-state and revision guards.

The new Process record is `process.ordered_steps` v1, linked to legacy recipe `process.steps` for catalog compatibility. The typed create route uses the registered record, not the legacy fallback. It accepts 3–6 ordered label/body pairs, no image and no CTA unless explicit user content supplies an action; `process_card` owns each card surface. Historical saved roots and frozen contracts are not rewritten.

The cross-family case C was selected as `benefits.grid`: it shares the `feature_card` repeated-surface owner and directly checks that the implementation is not a Services-only special case. Pricing/Team/Testimonials receive the same mapping and regression coverage; they were not re-generated in this live stage.

## Source and release history

| State | Source | Result |
|---|---|---|
| v278 | `d874b3f2885fb31cfc0be81609c795ea00f25b25` | Added the shared v2 surface contract and typed Process route. First A run revealed that the selected Services profile did not survive the plugin-chat planning context. |
| v279 | `554569b73b16a9e3753d2adef9a8ea1d176922e1` | Preserved `composition_record`, `composition_version` and `visual_profile` through Services planning context and accepted-plan decision. The same A fixture was repeated once to verify this confirmed source defect. |

Both source commits were pushed before live completion. WP Pusher updated only WP AI Executor to v279. Plugins PHP and the reloaded existing Elementor editor independently showed v279. No other plugin or WordPress setting was changed. The current editor still shows v279 and the expected user-cleared empty canvas; public DOM was read at CSS viewport 1232×923 after clearing the temporary responsive viewport override.

## Live matrix

Every request used the exact fixture saved in this audit directory and ordinary plugin chat. All runs used the deterministic local route, one accepted Brief and Plan, zero provider calls and one write. The v278 Services attempt is a superseded diagnostic attempt, not a second accepted scenario. Its one repeat was justified by the v278-to-v279 source fix described above.

| Case | Record/profile | Root / operation / identity | Brief / Plan hashes | Revision | Separate results |
|---|---|---|---|---|---|
| A — Services icon cards, v278 first result | `services.icon_cards` / UI-selected `soft_cards_light` was not preserved | `8073233` / `wpae-386f04d56c4648bd` / `978ca412-8887-4403-afee-5e4b8098aec1` | Brief `b82a93210cc1aae8a26724e3217490add971cf6ab7aa6d70e6c8437522395f0d`; accepted Plan hash not retained | generation 4 → refresh 5 → Undo 6 | Topology/content/links appeared in one desktop row, but selected profile was lost: first output measured 8px radius and 16px padding instead of the selected soft profile. Guarded Undo restored `[]`. |
| A — Services icon cards, v279 corrected result | `services.icon_cards` v1, `soft_cards_light`; record hash `abe677b31da25949378f4bcec02308152078e1e0ca78b6cbc809b9b1a028efc4` | `8c1774b` / `wpae-bf1d39a2187e5491` / `e97b0b7c-4dac-457c-a631-e0592f71b725` | Brief `b82a93210cc1aae8a26724e3217490add971cf6ab7aa6d70e6c8437522395f0d`; Plan `1ae8b834d0b78828f814750eb72dd435cbec1d0a04a7d3ad60a7ce61fe96424d` | generation 4 → owned readback 6 → Undo 7 | Technical/content/desktop geometry PASS; mobile visual PARTIAL for site-owned greeting overlap; Undo restored `[]`. Contract `contract-9bfa0232853ba7d82ca7d653`, hash `9bfa0232853ba7d82ca7d653feb97f6568e1ba4b7ab1965a0059064f80f21546`. |
| B — Process ordered steps | `process.ordered_steps` v1, `soft_cards_light`; record hash `1dc1b883cc211cd70dcfe859c0ce773796f76eb129cf2c3dbbb351198ba8f5db`; legacy link `process.steps` | `ccf3999` / `wpae-750bb7bf15a7f863` / `e2ba9dc9-4224-400b-aa53-17e5eb24796d` | Brief `c6538dbca261919fed312c8644218b1d115a2a5cf567d39bbcf6838f80f96c5a`; Plan `6866460ddbc2bb28ce0610a15a3fc518147b69f030e99d0513c2eefddb921897` | generation 4 → owned readback 6 → Undo 7 | Technical/content/desktop and tablet geometry PASS; mobile visual PARTIAL for site greeting over step marker 03; Undo restored `[]`. Contract `contract-131467eb3cf66179e7438e12`, hash `131467eb3cf66179e7438e12902ae7497615be03f5b84a98191c35feaa69ced5`. |
| C — Benefits grid cross-family surface | `benefits.grid` v1, `soft_cards_light`; record hash `6b3f121c7e7902b143e254507fb809c3c186b17e79c4aa551951197fd9fa0389` | `7921b1f` / `wpae-ab76671d039660df` / `aed61155-e07d-48e7-885e-b68e42da4421` | Brief `64bf9844fbfa17506b7ccf10c395430ee4c930089d306d960049b4e829473c76`; Plan `32e7f99dac1bab5fa012f0bcb7f85329d9ef93fdef17517a8dc9f6e9d156cc63` | generation 4 → owned readback 6 → Undo 7 | Technical/content/desktop and mobile topology PASS; mobile visual PARTIAL for greeting overlap with third item; Undo restored `[]`. Contract `contract-5e110278941481fbadd0e873`, hash `5e110278941481fbadd0e873ffa33a670847faf2cf2f08f18cca18a045fd5550`. |

Exact fixture SHA-256 values: Services `ed80c2af4627f0d3be421cc08fb751dbec8a41d117beead269652af2640caa9c`; Process `b06d30faeaef5759f9ceed390c53277600cbdf44071fc9e1f3fece6a38f2c783`; Benefits `319ed0ed7284e53ec57992626ef4895048d2dd2177bb59eecea2aa9ef1ab0257`. Fixture contents are retained verbatim in [`fixtures/`](fixtures/).

### A — Services

The exact request asks for three services, no photos, one icon/title/body/CTA per shared card surface, profile `soft_cards_light`, and exact `#strategy`, `#projects`, `#support` links. The v278 first screenshot already had the selected `icon_cards` topology, but it used default-looking 8px radius/16px padding because the UI-selected profile never reached the accepted Plan. v279 fixed the planning context, then repeated the same fixture once. The accepted Plan now contains `services.icon_cards`, `soft_cards_light`, item surface v2 owned by `services_icon_card`, 3/2/1 columns, equal item height and `space_between` body/actions distribution.

Public desktop CSS viewport is 1232×923. The collection is 1168px wide at x=32, three equal 370.7px cards, 28px gap. Each card is 421.46px high with white background, 1px border, 20px radius and 28px padding. Body/copy descendants are transparent with zero padding/border/radius. The three CTA buttons share the same bottom axis. Tablet CSS 768×1024 uses two columns with the third wrapping; mobile CSS 390×844 uses one column. All three exact titles, descriptions and hrefs are present; the long second description is complete and no horizontal overflow/clipping was measured. The public mobile greeting overlaps part of the second service's body, so authored-content visibility on mobile remains PARTIAL.

The browser's read-only owned-model check and editor/public content readback matched the accepted operation after reload. A standalone independent native JSON dump for corrected v279 A was not retained; `services-v279-first-generated-tree.json` is the captured compiler tree, not a post-reload native export. The file `services-v278-chat-export-after-reload-not-native.json` is a plugin chat export, not a native JSON tree; its filename states that limit. This limits artifact reproducibility but does not change the observed owned-model/public readback result.

### B — Process

The exact fixture has four ordered label/body pairs: Бриф, Структура, Сборка, Проверка. It forbids photos and buttons; the longer second description remains fully visible. The accepted record declares `ordered_timeline`, `process_card` surface ownership and 4/2/1 desktop/tablet/mobile capacity. Native output uses Flex containers and native heading/text/divider widgets, with 34 native elements in the captured first tree. No content was added.

Public desktop CSS viewport is 1232×923: four horizontal cards, each 264px wide with 28px gaps; card height grows naturally from 206.85px to 283.66px for the longer text. Tablet CSS 768×1024 wraps to two columns of 342px with 20px gap. Mobile CSS 390×844 stacks the four cards with natural height and no horizontal overflow. The site greeting covers step marker `03` and part of its horizontal connector; the third step body starts below the overlay. This is a site overlap, not lost Process text, and leaves mobile visual status PARTIAL.

Editor hierarchy after reload and the operation's read-only owned-model check matched the four ordered steps. A separate editor JSON export was not retained; the captured first generated tree is not represented as a post-reload native dump.

### C — Benefits

The exact fixture has four ordered title/body pairs, no image and no CTA. Benefits was chosen because its `feature_card` role shares the surface-owner implementation but exercises a family other than Services or the newly added Process. Native output has four card owners with transparent inner icon/title/body wrappers.

Public desktop CSS viewport is 1232×923: 2×2 layout, 556px cards with 28px gap; card boxes measure 220.85px high. Accepted Plan's concrete `collection.columns` is desktop 2, tablet 1, mobile 1 for four items; public desktop and mobile DOM match that plan, with no horizontal overflow. Mobile CSS viewport is 390×844 and cards stack naturally. The greeting overlaps title/body in the third item, leaving visual status PARTIAL. Exact text and order remain in readback.

Editor hierarchy after reload and the operation-owned-model check matched the accepted tree. The captured first generated tree is evidence of compiler output and is not labelled a post-reload native export.

## Screenshots and inspection

All listed PNGs were captured from the existing Browser Use tabs after Publish/reload, saved from returned screenshot bytes, signature/dimensions checked, and opened for visual inspection. The mobile screenshots are **public** at CSS viewport 390×844; their PNG viewport captures are 375×812 pixels, while full-page PNG dimensions vary with content height. Editor mobile preview is not used as public mobile evidence.

### A — Services, post 5214, root `8c1774b`, operation `wpae-bf1d39a2187e5491`, identity `e97b0b7c-4dac-457c-a631-e0592f71b725`, revision 6 before Undo

Desktop public, CSS viewport 1232×923, PNG 1232×923:

![Services v279 public desktop full-page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/services-v279-public-desktop-full.png)

[Desktop viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/services-v279-public-desktop-viewport.png) · [Desktop full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/services-v279-public-desktop-full.png)

Mobile public, CSS viewport 390×844, PNG 375×1202 full-page:

![Services v279 public mobile full-page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/services-v279-public-mobile-full.png)

[Mobile viewport PNG, 375×812](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/services-v279-public-mobile-viewport.png) · [Mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/services-v279-public-mobile-full.png)

### B — Process, post 5214, root `ccf3999`, operation `wpae-750bb7bf15a7f863`, identity `e2ba9dc9-4224-400b-aa53-17e5eb24796d`, revision 6 before Undo

Desktop public, CSS viewport 1232×923, PNG 1232×923:

![Process v279 public desktop full-page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/process-v279-public-desktop-full.png)

[Desktop viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/process-v279-public-desktop-viewport.png) · [Desktop full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/process-v279-public-desktop-full.png)

Mobile public, CSS viewport 390×844, PNG 375×1077 full-page:

![Process v279 public mobile full-page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/process-v279-public-mobile-full.png)

[Mobile viewport PNG, 375×812](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/process-v279-public-mobile-viewport.png) · [Mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/process-v279-public-mobile-full.png)

### C — Benefits, post 5214, root `7921b1f`, operation `wpae-ab76671d039660df`, identity `aed61155-e07d-48e7-885e-b68e42da4421`, revision 6 before Undo

Desktop public, CSS viewport 1232×923, PNG 1232×923:

![Benefits v279 public desktop full-page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/benefits-v279-public-desktop-full.png)

[Desktop viewport PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/benefits-v279-public-desktop-viewport.png) · [Desktop full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/benefits-v279-public-desktop-full.png)

Mobile public, CSS viewport 390×844, PNG 375×1053 full-page:

![Benefits v279 public mobile full-page](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/benefits-v279-public-mobile-full.png)

[Mobile viewport PNG, 375×812](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/benefits-v279-public-mobile-viewport.png) · [Mobile full-page PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-07-surface-process-v278/screenshots/benefits-v279-public-mobile-full.png)

## Checks and remaining limits

Recorded for the final runtime source v279: Design Pipeline Contract 919; Flex Runtime 1434; Elementor patch guard PASS; Node suites 18/18; PHP lint for changed PHP PASS; imported-template catalog 158 manifest entries / 156 available trees / 156 previews; package probe 253 files / 0 hash mismatches; `git diff --check` PASS. The runtime did not change during report closeout, so the full suite was not repeated.

Status separation:

| Dimension | A Services | B Process | C Benefits |
|---|---|---|---|
| Technical lifecycle | PASS | PASS | PASS |
| Content fidelity | PASS | PASS | PASS |
| Native structure / owner contract | PASS | PASS | PASS |
| Desktop geometry | PASS | PASS | PASS |
| Responsive topology / overflow | PASS; no overflow | PASS; no overflow | PASS; no overflow |
| Visual composition | Desktop PASS; overall PARTIAL | Desktop PASS; overall PARTIAL | Desktop PASS; overall PARTIAL |
| Public mobile visual | PARTIAL: greeting covers second service copy | PARTIAL: greeting covers marker 03/connector | PARTIAL: greeting covers third item copy |
| Document restoration | PASS, root set `[]` | PASS, root set `[]` | PASS, root set `[]` |

The final root set was read in the existing editor and public tab as `[]`; the temporary responsive viewport override was reset. The task completed 4 live writes total: 3 target compositions plus one justified Services repeat after the v278 profile-forwarding bug was fixed. There were no random retries, manual Elementor edits, root deletions, added pages/drafts, altered header/menu, or changes to other plugins/settings. Remaining evidence limitation: a stand-alone post-reload native JSON export was not retained for corrected A or B/C; read-only owned-model confirmation, editor hierarchy/readback, public DOM, source traces and screenshots are preserved, with those evidence types kept distinct.
