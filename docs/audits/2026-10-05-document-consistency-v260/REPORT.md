# Document consistency — v02.11.260 — 2026-10-05

## Source and evidence boundary

Prepared from HEAD 17e1d0014ff2c56bbad0dbc79c0cebb7466db08f. Runtime release v260; install and fresh B–J acceptance pending.

Confirmed source omission: the previous transaction wrote `_elementor_data` without synchronizing `post_content`. Official Elementor 4.1.1 Document `get_elements_data()` converts nonempty HTML when JSON is empty in the editor. The corresponding DB `get_plain_text_from_data($data)` accepts explicit JSON; the conversion-prone `get_plain_text(post_id)` must not be used here. Official tagged sources were inspected, not installed server PHP files. Editor initial_document independently identifies Elementor 4.1.1. Exact historical moment creating root 1a309f40 remains unobserved.

Sources: [Document 4.1.1](https://github.com/elementor/elementor/blob/4.1.1/core/base/document.php), [DB 4.1.1](https://github.com/elementor/elementor/blob/4.1.1/includes/db.php).

The previous C PNGs were opened and reviewed. Unowned HTML root 1a309f40 is visual FAIL (oversized image, plain-link CTA, lost native topology). Native C below it has a separate reading-width overflow defect; its source clamp correction still needs a fresh render.

## Owner and migration

Existing transaction owns explicit JSON and native HTML projection, metadata readback and existing snapshot rollback. Ledger attests the complete owned inverse including unchanged foreign neighbors; ordinary empty create/update remain invalid. Existing accepted-contract guard now compares the whole fresh native document before Publish. Chat sends the complete current native tree on ordinary, selected and retry paths; server refuses stale bootstrap or unsaved neighbor edits before provider/write. Config reports JSON/HTML consistency without exposing HTML or disabling legacy conversion.

Changed runtime: transactions.php, page-update.php, accepted-contract.php, editor-chat.php, llm.php, rollback.php, elementor-llm-chat.js, plugin header/package hashes. Existing visual policy and fixtures retained. No competing generator, new catalog families or automatic historical rewrite.

Projection scope requires empty pre-Publish HTML or exact projection of the current JSON. Arbitrary legacy HTML is preserved. Hook-time newer JSON/HTML and stale rollback fingerprints are preserved. Optimistic guards do not constitute a database-wide serializable transaction; a competing writer that ignores the operation lock can still race between compare and write. This remains a boundary to verify, not an unconditional concurrency PASS.

## Local verification

DesignPlan365; runtime1317; Node15/15; patch guard PASS; catalog158/156/156; PHP lint all changed PHP files PASS; package252 hashes/no mismatches/four scenarios PASS; diff check PASS.

Scoped real transaction probe reproduces stale HTML conversion, then two sequential create/inverse/hydration cycles without resetting between them. It exercises projection failure + actual snapshot rollback and metadata-hook JSON/HTML races. WP/native projection/hydration boundary is a model of tagged source, not a live installed Elementor test. Native Save in this probe is represented by its projection boundary; full native Save remains required live. Full package diagnostic serialization still fails malformed UTF-8; compact scenario diagnostics pass.

## Fresh current baseline

Read-only browser inspection now shows empty Navigator/preview and disabled Publish. Reloaded v259 savedBaseline is valid_array/rootIds[]/hash4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945. Independent public DOM is empty at CSS949×923, saved in baseline-public-before-install.json. No cleanup executed by this agent and no retrospective ownership assigned. Cause of this external state change is unknown. This supersedes the previous observed [1a309f40] state, and does not prove the new v260 fix.

## Acceptance status

| Scenario | v260 generation / Save / geometry / visual / Undo |
|---|---|
| B right editorial | NOT_RUN |
| C left editorial | NOT_RUN |
| D soft cards Hero | NOT_RUN |
| E About split | NOT_RUN |
| F Benefits grid | NOT_RUN |
| G Benefits list | NOT_RUN |
| H Benefits soft cards | NOT_RUN |
| I Pricing | NOT_RUN |
| J native FAQ | NOT_RUN |

No new design screenshots or visual PASS are claimed. Historical first FAIL and old PNGs remain in the visual-policy audit. Release commit/push/install are separate from live behavior and remain pending at this source preparation snapshot. Final observed root set [] before installation. Task incomplete: fresh B/C cycles, widths320/390/767/768/1024/1025/1280, D–J and screenshot/visual review remain required.


## v260 installation and first B refusal; v261 correction

v260 runtime commit01ed420a388a4418db7f5941407fbb8988cf60ed pushed; independent origin/main matched. WP Pusher success notice; Plugins PHPv260 and reloaded editorv260 confirmed. Config documentState shows JSONvalid[], HTMLbytes0/hash e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855, intentional_empty_consistent true. No cleanup executed.

First B exact UI request stopped locally before provider/write. UI guard wrongly expected valid/root_ids from captureEditorRootSnapshot, whose actual return is ids/fingerprints/parents. v261 correction validates API availability and actual ids. Regression now executes the real snapshot/children functions, rather than a mock returning nonexistent fields. Node15/15 (including DesignPlan365/runtime1317), package252 hashes/four probes, header lint and diff PASS. PHP runtime unchanged apart from release header. v261 publication/install and corrected B–J pending at this entry. Root set remains[].

First refusal editor screenshot post5214/root none/operation none/revision none, CSS949×923, PNG949×923. File converted from JPEG, PNG signature verified and opened: empty canvas/Navigator and erroneous refusal visibly agree. It is failure evidence, not design acceptance.

![B v260 first refusal](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-document-consistency-v260/B-v260-first-refusal.png)

[Download screenshot](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-document-consistency-v260/B-v260-first-refusal.png)


## v261 installed; B first generation; scope guard stopped Save

v261 runtime7b639e95bf7ea1986432712c988f71d979e10b4b pushed and remote matched. WP Pusher success, Plugins PHPv261/reloaded editorv261/documentState JSON[] HTMLbytes0 confirmed independently. B exact fixture created through normal UI, provider0/write1, root5a1b94d, operationwpae-1a17d1d28a4633e6, identity71eef9c1-42d4-4792-9826-34aaf478b112, initial response revision4. Brief3aa85d2e19936c14b2829a03bbe8c7095e2bb7476e3a772e638b6ea890a8b8a7; Plan e268f1706d6c16e2c71c637b5f752338f0135a61b21c0b6a8ca1bf69fcf313e5; saved hash3d8bc591a9e559c9c8e0a1d035e8e848377eb62f06abb97afe41355c54246afa. Plan/native diagnostics retained; no repair applied.

Read-only owned-model button refused exact scope/revision. Actual current server revision has not been observed yet; stale UI revision is a hypothesis, not a confirmed cause. Native Save/reload and dependent C–J writes stopped. B native selected JSON recursively matches every authored generated field (0 differences; additional native fields permitted). User reload authorization requested, pending. Current observed root set[5a1b94d].

First preview DOM: actual iframe CSS1025×860 (outer browser949×923), document/root width1025; inner961; copy column564.6; reading wrapper564.6; media376.4. No overflow at this single measured editor width. Remaining widths/public/mobile NOT_RUN. Negative advisory Vision68 retained: reported wide spacing/awkward wrapping. This must be checked against unobscured public pixels; editor Navigator obscures part of first captured frame.

First editor PNG949×923, root5a1b94d/opwpae-1a17d1d28a4633e6/initial revision4, before Native Save, converted/verified/opened. Badge and styled CTA visible; Navigator and browser crop obscure right title/media. Cannot judge full composition or claim screenshot acceptance after Save/reload. Required post-Save/public screenshots remain BLOCKED by model guard and pending reload approval.

![B v261 first editor preview before Save](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-document-consistency-v260/B-v261-first-editor-before-save.png)

[Download first preview](/Users/diasmazhenov/vibecode/wp-ai-executor/docs/audits/2026-10-05-document-consistency-v260/B-v261-first-editor-before-save.png)

## v262 read-only descriptor recovery prepared

Existing lifecycle adds describe_operation: capability/post/identity and current ownership/lineage/fingerprint required; returns bounded current descriptor without document write. Only read-only lookup omits revision check. All check_model/Save/repair/Undo guards still require exact revision. UI checks same operation/identity/contract/root set before updating its acknowledgement and checking actual model. No automatic repair or ownership reassignment.

Local DesignPlan365/runtime1325/Node15of15/patch/catalog158/156/156/lint/package252+four probes/diff PASS. Tests cover read-only current revision, foreign identity refusal and UI check using fresh revision. v262 commit/push planned separately; installation and live model retry pending current dirty-tab reload authorization. B generation PASS; authored content match PASS; Native Save/readback BLOCKED; responsive geometry partial; visual composition REVIEW_REQUIRED; Undo NOT_RUN. C–J NOT_RUN. Full task remains incomplete.


### Publication checkpoint

Runtime v262 commit267bdd72999c8cc4f19e6e1cc9748b7a240cfa09 pushed; independent origin/main matched. Installed PHP/editor remain independently confirmed v261. v262 installation/editor reload are pending the explicit dirty-tab reload question. No dependent write, Native Publish, repair or Undo attempted after scope guard refusal. Tracked working tree clean at publication; foreign/untracked files and raw intermediate JPEGs remain preserved. Current test root5a1b94d remains; baseline[] has not yet been restored after B. Acceptance incomplete.


## Current completed technical matrix

See [LIVE_ACCEPTANCE.md](LIVE_ACCEPTANCE.md) for actual B–J results, installation, failures, guarded Undo, review limitations and inline public PNGs. Earlier pending checkpoints are historical. Overall quality remains REVIEW_REQUIRED for Pricing.
