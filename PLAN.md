# WP AI Executor Plan

## Done

- Embed agent guidance for WordPress, Elementor, frontend design, native Flexbox Containers, and file-write restrictions.
- Block common filesystem writes through `/run`.
- Allow plugin self-update only through the dedicated `/self-update` endpoint.
- Add database-backed custom skills through `/skills`.
- Require `/guide/session` + `/guide/ack` guide tokens before any write endpoint.
- Runtime-validate changed Elementor data for legacy `section`/`column`, missing `widgetType`, and enabled skill `enforce` rules.

## Next

1. Add site-owner capability toggles.
   - Keep one `X-AI-Key` for simpler agent setup.
   - Add admin toggles for `/run`, plugin self-update, Elementor writes, media upload, exports, skills management, and filesystem write override.
   - Keep dangerous capabilities off by default where practical, especially filesystem writes.
   - Permission callbacks must check the key, guide token, and the relevant enabled capability.
   - Show active plugin version, guide version, enabled skills, current capability state, and recent validation failures in the admin UI.
   - Leave role-based keys as optional future hardening for multi-agent or client sites.

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

9. Add optional role-based keys.
   - Add only if a site needs different secrets for different agents or clients.
   - Possible roles: `run_key`, `guide_key`, `update_key`, and `readonly_key`.
   - Keep disabled by default to avoid unnecessary setup friction.
