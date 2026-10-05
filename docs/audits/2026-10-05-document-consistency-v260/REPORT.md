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
