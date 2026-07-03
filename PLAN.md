# WP AI Executor Plan

## Done

- Embed agent guidance for WordPress, Elementor, frontend design, native Flexbox Containers, and file-write restrictions.
- Block common filesystem writes through `/run`.
- Allow plugin self-update only through the dedicated `/self-update` endpoint.
- Add database-backed custom skills through `/skills`.
- Require `/guide/session` + `/guide/ack` guide tokens before any write endpoint.
- Runtime-validate changed Elementor data for legacy `section`/`column`, missing `widgetType`, and enabled skill `enforce` rules.

## Next

1. Split API keys by role.
   - Current `X-AI-Key` is almost root-level access.
   - Add `run_key` for PHP execution and high-risk writes.
   - Add `guide_key` for read-only `/guide`, `/capabilities`, and `/skills` reads.
   - Add `update_key` for `/self-update` only.
   - Optionally add `readonly_key` for diagnostics.
   - Keep backward compatibility during migration, but expose warnings when the single root key is still used.

2. Expand the capabilities contract.
   - Make `/capabilities` the explicit machine-readable contract agents must inspect before acting.
   - Include booleans such as `can_write_files`, `can_update_plugin`, `can_write_elementor`, and `must_use_flex_containers`.
   - Include allowed endpoints, forbidden operations, Elementor constraints, file-write policy, and guide-token requirements.
   - Keep this aligned with runtime enforcement, not just documentation.

3. Add safe Elementor endpoints.
   - Reduce raw PHP use through `/run`.
   - Add `POST /wp-json/ai-executor/v1/elementor/page` for create/update page flows.
   - Add `POST /wp-json/ai-executor/v1/elementor/validate` for JSON validation before saving.
   - Add `POST /wp-json/ai-executor/v1/elementor/update` for saving `_elementor_data`, page meta, template, and cache clearing.
   - The plugin should accept JSON, validate `container`/`widgetType`, write meta, clear Elementor cache, and reject legacy sections/columns.

4. Add a post-write audit endpoint.
   - Endpoint: `POST /wp-json/ai-executor/v1/audit`.
   - Check Elementor data, page meta, native widget content placement, HTML widget usage, critical native backgrounds/colors/borders/spacing, and external-file policy.
   - Return machine-readable pass/fail findings so agents cannot hide weak verification behind prose.

5. Add dry-run and rollback snapshots for write actions.
   - Before `/run` mutates posts/meta/options, capture affected values.
   - Let the agent request `dry_run=true` where practical.
   - Store a short-lived rollback snapshot in options, not files.
   - Add rollback endpoint guarded by guide token.

6. Improve custom skill import.
   - Accept pasted `SKILL.md` content and JSON skill packs.
   - Support import/export of skill bundles through WordPress options.
   - Keep all skill storage in the database; no server-side skill files.
   - Extend `enforce` rules for Elementor widget allowlists, required native style keys, forbidden HTML-widget content patterns, and required verification checks.

7. Add operation logs.
   - Store recent authenticated actions in a capped option.
   - Include endpoint, actor hint, target IDs, guide hash, validation result, and rollback snapshot ID when present.
   - Never log API keys, guide tokens, raw page payloads, or secrets.

8. Add agent conformance scoring.
   - After each write, score whether the agent followed the guide: read guide token flow, no files, native Elementor, Flex Containers, correct `widgetType`, native critical styles, verification done.
   - Return score and blocking errors in write responses.

9. Add site owner controls.
   - Admin UI toggles for strict mode, allowed write endpoints, skill enable/disable, guide-token TTL, and file-write override status.
   - Show active plugin version, guide version, enabled skills, and recent validation failures.
