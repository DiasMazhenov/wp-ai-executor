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
- Add JSON skill bundle import/export through REST and dashboard, stored only in `wp_options`.
- Extend skill `enforce` rules for Elementor widget allowlists, forbidden widget types, required widget/container settings, and forbidden HTML widget patterns.
- Add capped operation logs in `wp_options` with endpoint, actor hint, target IDs, guide hash, validation summary, and rollback snapshot ID.
- Keep operation logs redacted: no API keys, guide tokens, raw page payloads, request bodies, response bodies, or secrets.
- Add agent conformance scoring in responses and operation logs for guide-token flow, file policy, Elementor policy, Flex Containers, `widgetType`, native visual settings, and verification signal.

## Next

1. Add single-key access profiles through dashboard toggles.
   - Keep one `X-AI-Key`; do not add separate `run_key`, `guide_key`, `update_key`, or `readonly_key`.
   - Add owner-selectable presets such as `read_only`, `elementor_safe`, `maintenance`, and `full_trusted`.
   - Profiles should only set existing capability toggles and be reflected in `/capabilities`.
