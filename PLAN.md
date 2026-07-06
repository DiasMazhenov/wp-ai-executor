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

1. Add `/elementor/normalize` for common Elementor JSON mistakes.
   - Convert `widget_type` to `widgetType` where safe.
   - Reject or normalize legacy `section`/`column` into Flexbox Containers.
   - Fill safe baseline `settings`, `elements`, spacing, background, and responsive structure.
   - Return a diff-like summary so agents can learn what was fixed.

2. Add `/elementor/recipes` with native Elementor section patterns.
   - Provide reusable Flexbox Container JSON for hero, pricing, testimonials, FAQ, process, CTA, feature grid, cases, and contact blocks.
   - Recipes must use native widgets and settings first; HTML widgets only for CSS/JS enhancements.
   - Include required widget/settings contracts for each recipe.

3. Expand agent conformance scoring into design quality gates.
   - Score typography hierarchy, spacing consistency, CTA visibility, mobile readiness, native backgrounds, HTML-widget layout risk, palette quality, and empty/hidden blocks.
   - Keep scoring advisory at first, but make `weak`/`blocked` a clear "not done" signal in `/guide`.

4. Add `/elementor/blueprint` for page planning before writing.
   - Accept niche, goal, audience, offer, language, and preferred style.
   - Return required sections, native widgets, content roles, design tokens, CTA plan, and allowed enhancement CSS/JS zones.
   - Use it as the preferred first step before `/elementor/page`.

5. Add project design tokens in the dashboard and `/guide`.
   - Store palette, typography roles, spacing scale, radii, button style, tone of voice, and design prohibitions in `wp_options`.
   - Return tokens in `/guide` and `/capabilities` so any agent can follow the site's visual system.

6. Add stronger preflight checks before Elementor writes.
   - Verify no legacy sections/columns, no empty native widgets, no CSS-only critical backgrounds, no horizontal overflow risk markers, and no HTML widget used as main layout.
   - Require CTA presence and native critical visual settings for landing pages.

7. Add after-save quality summary.
   - After `/elementor/page` or `/elementor/update`, return permalink, audit summary, conformance score, warnings, and concrete fixes.
   - Encourage agents to run `/audit` and address warnings before claiming completion.

8. Consider a future `/visual-audit` endpoint.
   - Prefer server-side DOM/render checks only if technically reliable on typical WordPress hosting.
   - Target overflow, contrast, invisible text, suspicious empty blocks, huge spacing, and desktop/mobile screenshot metrics.

9. Optionally add preset buttons for existing single-key capability toggles.
   - The core model is already implemented: one `X-AI-Key`, dashboard capability toggles, and `/capabilities` reflection.
   - Do not add separate `run_key`, `guide_key`, `update_key`, or `readonly_key`.
   - Presets, if needed, should only be UI shortcuts such as `read_only`, `elementor_safe`, `maintenance`, and `full_trusted`.
