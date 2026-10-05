# Pricing inline-value owner v265

Confirmed v264 public390 DOM: price-group x41,width308, direction mobile column, align-items center; amount x145.171875 vs item copy x41. Native heading text align left does not position its narrow box. First I evidence is preserved unchanged.

New accepted visual_policy.inline_value defines desktop/tablet/mobile direction row, wrap, main flex-start, cross center, gap0.25rem. IR binds this existing price/period group to those decisions; native compiler only translates them. Price/period can naturally wrap without introducing mobile centering. Typography, exact text, links, record/profile, collection geometry and assets are unchanged. Optional field preserves historical frozen Plan behavior. No live root rewritten.

Changed includes/llm/design-plan.php (optional accepted policy plus validation), includes/elementor/elementor-ir.php (IR binding/native translation; legacy role branch gated), tests/design-pipeline-contract.php (2/3/4/6 native responsive equality, alternate accepted policy, historical compatibility, invalid direction), entrypoint version and package manifest.

Local PASS: DesignPlan380, runtime1329, Node15/15, patch guard, catalog158/156/156, changed PHP lint, package252 hashes/four probe cases, diff check. Full package diagnostic serialization retains historical malformed UTF8 limitation; compact results valid. Sourcev265 prepared; runtime commit/push/install/fresh live Pricing acceptance are separate pending statuses at preparation.
