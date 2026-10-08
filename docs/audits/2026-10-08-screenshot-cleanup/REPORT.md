# Screenshot cleanup — 2026-10-08

Completed at 2026-10-08T13:47:49+05:00. User authorized removal of obsolete screenshots.

- Removed 624 screenshot files (83.44 MiB): 410 unreferenced JPEG source copies with valid same-size PNG counterparts, and 214 obsolete unreferenced captures.
- Tracked deletions: 164; untracked screenshot deletions: 460.
- Remaining screenshots in this cleanup scope: 427.
- All 61 current M2.2 PNGs were retained byte-for-byte, including A–E, Services v289 first failure, corrected v290 output and Process overlap evidence.
- Screenshots referenced by repository text and additional first-render/refusal/defect evidence were retained. Non-screenshot untracked files, runtime code, WordPress and pages were untouched.
- Before deletion, a repository-wide fixed-string reference scan found no selected filename references. Every deletion was limited to an inventoried path with an unchanged SHA-256.
- The existing user change in context.md is preserved; only a cleanup note was appended.

The machine-readable deletion inventory records each path, previous SHA-256, size, Git tracking status and reason in [manifest.json](manifest.json). Deletion records are historical metadata, not screenshot links.

No live test or screenshot capture ran. Acceptance remains 4/5; Services and Process remain excluded from further testing.
