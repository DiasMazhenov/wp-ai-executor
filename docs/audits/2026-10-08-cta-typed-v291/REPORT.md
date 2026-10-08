# Canonical typed CTA — v02.11.291

## Scope and result

This stage moved standalone CTA creation onto the canonical `BriefIR → DesignPlan → ElementorIR → native Flex compiler → validation → transaction/readback` path, added two versioned composition records, corrected the release package diagnostic, and ran one new live CTA generation on the existing post 5214. No Services or Process request was sent. The live CTA was published, independently read after reload, reviewed on public desktop and mobile, and removed only by its operation-scoped guarded Undo.

There was one live generation, one transaction write, zero repeats and zero manual repairs. The historical A–D generations were reused as existing evidence; E remains `PARTIAL / SITE_OVERLAP`. Four historical full acceptances A–D plus new full acceptance F make five full acceptance generations cumulatively. This does not turn historical A–E into 5/5: E is still partial.

## Source, installation and checks

| Status | Evidence |
|---|---|
| Source | v02.11.291, runtime commit `261b74e519151257472fb95cba7fd74516ace331`. |
| Push | Runtime `git push` reported success. The scoped documentation/evidence commit `a68114c08140002abe981e5119e00d2df80b66fe` was also pushed; the subsequent independent `git ls-remote origin refs/heads/main` matched that SHA. |
| Install | WP Pusher reported “Plugin was successfully updated” for WP AI Executor only. |
| Plugins version | v02.11.291. |
| Editor version | v02.11.291 in the reloaded existing Elementor editor. |
| PHP runtime | Not separately collected during this install. |

Before the runtime commit, local checks passed: Design Pipeline Contract 943; Flex Generation Runtime 1473; Node 20/20; Elementor patch guard; PHP lint for changed PHP; imported catalog 158 manifest entries / 156 retrievable trees / 156 previews; package manifest 253 files / zero mismatches; and `git diff --check`. After the package diagnostic was made explicit, the package probe was rerun: all four package scenarios passed, 253 hashes matched, zero mismatches. The actual full validator-result JSON measurement is `false` with `Malformed UTF-8 characters, possibly incorrectly encoded` because it contains opaque archive bytes; this is now explicitly `NOT_APPLICABLE` as a whole-result transport contract. Compact UTF-8 metadata containing Cyrillic and 🚀 encoded successfully, had no JSON error, and round-tripped. This diagnostic result is not evidence of a production runtime serialization failure. PHP lint for the changed probe also passed. The machine-readable output is [evidence-package-probe.json](evidence-package-probe.json); source/install and recorded local checks are in [evidence-source-install-push.json](evidence-source-install-push.json).

## Typed CTA contract

CTA is now included in `migrated_create` as an archetype and uses the existing Brief, composition-decision, accepted DesignPlan, ElementorIR, compiler, validation, transaction, readback and operation-ownership modules. Ordinary library candidates do not redirect the CTA path; typed-route errors do not fall through to a different legacy composition or write.

The registered v1 records are:

| Record | Accepted native topology | Responsive policy |
|---|---|---|
| `cta.centered` | One section column. Copy and actions live together in its centered child; the copy uses a constrained 42rem measure. | Column on desktop/tablet/mobile; action buttons row on desktop, stack on tablet/mobile. |
| `cta.split_actions` | Desktop section row with two sibling groups: copy and separate actions. Copy/actions tracks are resolved before freeze; the generated live tree contains two direct child groups, unlike the centered record. | Section changes to a column on tablet/mobile; both action buttons are vertical. |

Both records disallow media and repeating groups at the record boundary. Explicit image intent is refused unless a compatible typed media record is added. They use native Elementor Flex containers and heading/text/button widgets, not HTML, shortcode or root-specific CSS. The accepted Plan owns topology, reading measure, alignment, responsive axes, spacing and surface values; IR carries them and the compiler translates them without choosing again. Brief intake owns exact copy, URLs, source spans and provenance. The semantic H2/title role remains independent of display type; badge text is retained as the eyebrow.

The existing surface precedence remains explicit Brief → record/profile → documented palette Accent tint default. The live CTA resolved `#61ce7033` (`rgba(97, 206, 112, 0.2)`) over white: 20% opacity / 80% transparency. The Elementor system Accent value `#61ce70` is a stock decorative default, not a confirmed brand color.

Production-path regressions cover both records through the real `wpae_llm_chat_request()` mock boundary, distinct topology signatures, record/profile preservation, automatic compatible selection, one/two actions, exact long Russian copy and URLs, pill provenance, missing/unknown fields and media refusal, library-route isolation, frozen-design normalization, native/readback/Undo guard preservation and neighboring-root protection.

## Live scenario F

**Fixture:** [`fixtures/F-cta-exact-request.txt`](fixtures/F-cta-exact-request.txt), SHA-256 `1851910e5875a3c3d54b676317f97e79d88a5f99d8280f4bcc22f9002dd15e16`.

The existing post 5214 began from the user-cleared saved baseline: editor and public root sets `[]`, Publish disabled. In the normal plugin chat UI, the user-visible selections before submit were `cta.split_actions` (“Текст и отдельные действия”) and `editorial_light` (“Светлое редакционное”). The exact fixture was submitted once. The accepted descriptor retained the explicit record and UI profile.

| Evidence | Value |
|---|---|
| Route / calls / writes | Ordinary plugin chat → local deterministic typed pipeline; provider calls `0`; transaction writes `1`. |
| Record / profile | `cta.split_actions@1`, hash `e71681d425d30140c3576cc420bd9b9d04108aeb21b2c113d0f278c3c7c74c74`; `editorial_light`, source `ui_selector`. |
| Brief / Plan | Brief SHA `4f7306df32fae5a020e48c98e2645e8f00d5c3d47a2169889624277e92bb3881`; accepted Plan SHA `6354c21fc7a7ec02ebb9b3d42185f250fc578e572183d66ad043206a5c21a195`. |
| Operation | `wpae-f64ba26b8ce79dfd`; identity `64a44801-5140-4753-96be-d77cf318755f`; root `d6020a0`; revision after Publish/reload `5`. |
| Guard contract | `contract-85ddb2c86ddb27ec0484e098`; contract SHA `85ddb2c86ddb27ec0484e098931f091358a002f9e9c0af2000ed701c3b7b7ea2`; saved-document SHA `45fff04d66f1f423fa795c167a61f8503335c86c97714a7807eafe6f2f33607f`; native fingerprint `f106f6882e21e904033496b3b8308939030e0f6478788aa4ffb27d8655c55178`. |
| Native readback | Full root exported from reloaded `ElementorConfig.initial_document`; 5 Flex containers + 5 widgets, no image widget; exact authored text and `#contact`/`#projects` URLs preserved. |
| Publish/reload | Publish completed; after reload the editor's saved model and native tree agreed. No repair or repeat. |

The exact badge “ОБСУДИМ ПРОЕКТ”, H2 title “Превратим идею в понятный план”, body “Расскажите о задаче — обсудим цели, ограничения и следующий шаг”, primary “Обсудить проект” → `#contact`, and secondary “Посмотреть работы” → `#projects` were retained. The primary is filled black and the secondary outlined; no button, image or empty media group was invented. The complete export is [native-root-after-reload.json](native-root-after-reload.json); its source and widget counts are in [evidence-native-readback-meta.json](evidence-native-readback-meta.json). The operation descriptor and generation trace are saved alongside it.

The pre-submit UI selection is preserved separately in [the selector screenshot](screenshots/cta-selection-before-submit.png). It documents the selected `cta.split_actions` record and `editorial_light` profile before the one ordinary-chat request.

## Rendered geometry and visual review

The captured PNGs are public-page screenshots after Publish/reload. Browser Use screenshot bytes were JPEG; signatures were checked, files converted to PNG, and the PNG dimensions/signatures verified. Both PNGs were opened and visually reviewed. Desktop CSS viewport and client width were both 1280×900 / 1280; mobile was a real public CSS viewport 390×844 with client width 390 (not Elementor mobile preview). Document width matched the viewport at both sizes.

At desktop the CTA root is `x=0,y=32,w=1280,h=331.63`, with 32px horizontal padding and computed background `rgba(97,206,112,.2)`. Copy begins at `(70,112)` and spans 706.8px; the separate actions group begins at `(808.8,149.8)` and spans 364.8px. The title wraps to two lines and the body remains one line. Buttons share the action column's left axis at x=808.8; their tops are y=149.8 and y=204.8 with 16px spacing. The section has no media. No horizontal overflow or clipping was measured.

At public mobile the root is `x=0,y=46,w=390,h=378.19`, with 16px horizontal padding and the same accepted tint. Copy/title/body occupy x=16..374; the 28px/32.2px title wraps to two lines and the body uses 16px/26.4px. Actions stack at x=16, y=292.2 and y=343.2 with a 12px gap. Buttons and copy stay within the 358px content width. No horizontal overflow, clipping, or overlap was measured. The site-owned greeting is below the CTA in the captured viewport and does not cover either action.

The design reads as a true split CTA on desktop: compact pill, strong but section-scale title, supporting copy, and visibly distinct primary/secondary actions. On mobile the same hierarchy collapses into a natural vertical flow. The screenshot contains the logged-in WordPress admin bar and a large white area below the section; neither is part of the CTA root. No repair was needed. The advisory Vision score of 45/100 claimed a dark surface and low contrast; this is a false positive for this render, contradicted by the saved screenshot and computed mint background / dark text / black-and-white action controls. Vision did not modify the generated tree.

### Fresh public screenshots

Post 5214 · root `d6020a0` · operation `wpae-f64ba26b8ce79dfd` · revision 5 · public desktop · CSS viewport 1280×900 · PNG 1280×900.

![CTA public desktop after Publish/reload, post 5214, root d6020a0, operation wpae-f64ba26b8ce79dfd, revision 5, CSS viewport 1280×900, PNG 1280×900](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-cta-typed-v291/screenshots/cta-public-desktop-1280x900.png)

[Download CTA public desktop PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-cta-typed-v291/screenshots/cta-public-desktop-1280x900.png)

Post 5214 · root `d6020a0` · operation `wpae-f64ba26b8ce79dfd` · revision 5 · public mobile · CSS viewport 390×844 · PNG 390×844.

![CTA public mobile after Publish/reload, post 5214, root d6020a0, operation wpae-f64ba26b8ce79dfd, revision 5, CSS viewport 390×844, PNG 390×844](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-cta-typed-v291/screenshots/cta-public-mobile-390x844.png)

[Download CTA public mobile PNG](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-08-cta-typed-v291/screenshots/cta-public-mobile-390x844.png)

## Guarded Undo and restoration

After the public evidence was collected, the operation-scoped Undo for `wpae-f64ba26b8ce79dfd` was invoked once. Its button is now disabled with status `already_undone`. A fresh editor read shows the deliberate empty canvas, Publish disabled, and root set `[]`; an independent public reload reports root IDs `[]` and no CTA text. The saved evidence is [editor after Undo](evidence-editor-after-undo.json) and [public after Undo](evidence-public-after-undo.json). Final post 5214 root set is `[]`; no root was manually removed or unrelated page content changed.

## Historical Team screenshots and visual debt

The existing v290 public B/C PNGs were reopened without rerunning either generation. B `team.grid` is a two-column Flex-wrap grid; variable card content leaves large unused blank space under the short first/third cards while the taller neighbor sets each row height. C `team.editorial_rows` is a true separate horizontal row composition: names/positions remain in the left column and body copy starts in the right column, unlike B's equal-card grid. C's short rows also leave broad unused horizontal/vertical space. These are genuine content-fit/layout debt observations; neither screenshot is a same-topology recolor or text swap. Radius near 4px remains compatible with `editorial_light` and is not labeled a defect by itself. The historical A–D/E statuses remain as recorded in the [v290 matrix](../2026-10-08-m2-2-selection-readback-v286/acceptance-matrix.json).

## Final statuses and limits

| Area | Status |
|---|---|
| Canonical CTA routing and accepted selection | PASS for this explicit `cta.split_actions` request; `cta.centered` is regression-tested locally, not live-tested. |
| Exact content, pill and links | PASS. |
| Native Flex structure and post-reload readback | PASS. |
| Publish and guarded Undo lifecycle | PASS; final editor/public roots `[]`. |
| Public desktop/mobile geometry | PASS at measured 1280×900 and 390×844. |
| Visual composition | PASS for this one split CTA; no image path or one-button CTA was live-tested. |
| Color provenance | Correct documented Accent tint applied; stock Accent remains unconfirmed as brand. |
| Cumulative full live generations | Five: historical A–D plus new F; E remains partial. |
| Other live coverage | `cta.centered` not live-tested. No Services/Process rerun. |

The screenshot/evidence and machine matrix are part of this audit. Runtime source and documentation/evidence are separate commits. The first documentation/evidence push was independently verified at `a68114c08140002abe981e5119e00d2df80b66fe`; the brief follow-up recording that verification is a separate documentation-only commit.
