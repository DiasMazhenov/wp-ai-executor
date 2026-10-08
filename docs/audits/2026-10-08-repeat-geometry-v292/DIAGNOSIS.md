# Initial diagnosis — repeated collection geometry

Source baseline: `main` at `37678c964d8e8abe868123f2eaa9960648e7a120`, plugin source `v02.11.291`. No runtime change had been made when this diagnosis was written.

## Confirmed policy-to-DOM mismatch

1. In `includes/llm/design-plan.php`, `wpae_design_plan_visual_policy()` chose `collection.item_height` based on authored actions (`equal_row` with actions, `content` without them; source baseline around line 1028). Thus two instances of the same grid composition could get different row geometry solely because one Brief had a CTA.
2. `includes/elementor/elementor-ir.php` translated `content` into native Flex `flex_align_items:flex-start` on the repeated collection; with no-action Team/Testimonials grids, the resulting row surfaces therefore kept their natural heights. The retained v290 Team grid evidence confirms the mismatch: the collection DOM used `alignItems:flex-start`; its first row cards measured 147.09px and 259.28px despite equal 560px widths, with no clipping or overflow. The exported card children had no explicit `align_self` values. Parent-only `stretch` must be carried to the visible repeated surface owner, not inferred from a test of the parent control alone.
3. The surface owner for Team and Testimonials grid cards is the outer repeated `team_card` / `testimonial_card` node. Its existing surface contract already owns background, border, radius and padding; no inner copy wrapper needs a family-specific background or height rule. Card bodies and optional action groups already use native Flex column flow. The compiler creates no footer when there is no `card_actions` child.
4. The generic column calculation had a hard-coded count branch that forced 4 and 6 items to two columns, and the legacy section-level tablet `stack` check forced nested collections to one column even when an explicit collection breakpoint map was present. The nested collection therefore did not consistently own its responsive tracks.

## Visual evidence inspected

- The v290 `team.grid` desktop screenshot shows short cards ending above the long neighbor in the same row. Its white surface is owned by the card; the mismatch is not caused by replacing the card background.
- The v290 `team.editorial_rows` screenshot shows a different native topology: identity remains in the left track and copy begins in the right track. Its natural row heights must remain independent from grid equal-row behavior.
- The v291 CTA desktop/mobile screenshots show the separate split-action composition, which is outside this regression; the new live CTA case will use the distinct centered record.

## Intended bounded correction

Keep the existing `BriefIR → composition decision → frozen DesignPlan → ElementorIR/native compiler → validation → transaction/readback` path. Resolve grid row height independently of authored actions; carry the accepted optional surface alignment to the visible repeated item owner as native `align_self`; preserve the old collection-position/alignment fallback for frozen Plans without the new field. Resolve breakpoint columns from explicit Brief intent, record policy, visual profile and then the generic balanced default. Keep list/editorial rows at natural height, preserve exact content and action ownership, and retain compatibility where older frozen Plans lack these fields.
