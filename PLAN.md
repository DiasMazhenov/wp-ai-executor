# WP AI Executor Plan

## Done

- Embed agent guidance for WordPress, Elementor, frontend design, native Flexbox Containers, and file-write restrictions.
- Block common filesystem writes through `/run`.
- Allow plugin self-update only through the dedicated `/self-update` endpoint.
- Add database-backed custom skills through `/skills`.
- Require `/guide/session` + `/guide/ack` guide tokens before any write endpoint.
- Runtime-validate changed Elementor data for legacy `section`/`column`, missing `widgetType`, and enabled skill `enforce` rules.
- Add site-owner capability toggles for `/run`, self-update, Elementor writes, media upload, exports, skills management, and filesystem write override.
- Expand `/capabilities` into a machine-readable contract aligned with runtime enforcement.
- Add safe Elementor endpoints: `/elementor/validate`, `/elementor/page`, and `/elementor/update`.
- Add `/audit` for machine-readable post-write Elementor/page verification.
- Add dashboard fields for pasting and managing database-backed custom `SKILL.md` instructions.
- Add dry-run support for structured Elementor writes and rollback snapshots stored in `wp_options`.
- Add `/rollback` guarded by guide token and `/run` `rollback_targets` for known posts/options.

## Next

1. Improve custom skill import/export.
   - Support JSON skill packs import/export.
   - Support import/export of skill bundles through WordPress options.
   - Keep all skill storage in the database; no server-side skill files.
   - Extend `enforce` rules for Elementor widget allowlists, required native style keys, forbidden HTML-widget content patterns, and required verification checks.

2. Add operation logs.
   - Store recent authenticated actions in a capped option.
   - Include endpoint, actor hint, target IDs, guide hash, validation result, and rollback snapshot ID when present.
   - Never log API keys, guide tokens, raw page payloads, or secrets.

3. Add agent conformance scoring.
   - After each write, score whether the agent followed the guide: read guide token flow, no files, native Elementor, Flex Containers, correct `widgetType`, native critical styles, verification done.
   - Return score and blocking errors in write responses.

4. Add optional role-based keys.
   - Add only if a site needs different secrets for different agents or clients.
   - Possible roles: `run_key`, `guide_key`, `update_key`, and `readonly_key`.
   - Keep disabled by default to avoid unnecessary setup friction.
