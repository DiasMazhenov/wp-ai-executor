# WP AI Executor Plan

## Done

- Embed agent guidance for WordPress, Elementor, frontend design, native Flexbox Containers, and file-write restrictions.
- Block common filesystem writes through `/run`.
- Allow plugin self-update only through the dedicated `/self-update` endpoint.
- Add database-backed custom skills through `/skills`.
- Require `/guide/session` + `/guide/ack` guide tokens before any write endpoint.
- Runtime-validate changed Elementor data for legacy `section`/`column`, missing `widgetType`, and enabled skill `enforce` rules.

## Next

1. Add a post-write audit endpoint.
   - Endpoint: `POST /wp-json/ai-executor/v1/audit`.
   - Check Elementor data, page meta, native widget content placement, HTML widget usage, critical native backgrounds/colors/borders/spacing, and external-file policy.
   - Return machine-readable pass/fail findings so agents cannot hide weak verification behind prose.

2. Add dry-run and rollback snapshots for write actions.
   - Before `/run` mutates posts/meta/options, capture affected values.
   - Let the agent request `dry_run=true` where practical.
   - Store a short-lived rollback snapshot in options, not files.
   - Add rollback endpoint guarded by guide token.

3. Add structured Elementor mutation endpoints.
   - Reduce raw PHP use for common work.
   - Endpoints for create/update page, set Elementor data, patch element settings, clear Elementor cache, and publish/draft page.
   - Apply the same validator before saving.

4. Improve custom skill import.
   - Accept pasted `SKILL.md` content and JSON skill packs.
   - Support import/export of skill bundles through WordPress options.
   - Keep all skill storage in the database; no server-side skill files.
   - Extend `enforce` rules for Elementor widget allowlists, required native style keys, forbidden HTML-widget content patterns, and required verification checks.

5. Add operation logs.
   - Store recent authenticated actions in a capped option.
   - Include endpoint, actor hint, target IDs, guide hash, validation result, and rollback snapshot ID when present.
   - Never log API keys, guide tokens, raw page payloads, or secrets.

6. Add agent conformance scoring.
   - After each write, score whether the agent followed the guide: read guide token flow, no files, native Elementor, Flex Containers, correct `widgetType`, native critical styles, verification done.
   - Return score and blocking errors in write responses.

7. Add site owner controls.
   - Admin UI toggles for strict mode, allowed write endpoints, skill enable/disable, guide-token TTL, and file-write override status.
   - Show active plugin version, guide version, enabled skills, and recent validation failures.
