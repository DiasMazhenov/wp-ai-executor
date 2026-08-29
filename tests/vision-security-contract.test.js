const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const routes = read('includes/rest/routes.php');
const dashboard = read('includes/admin/dashboard.php');
const vision = read('includes/vision/vision.php');
const transactions = read('includes/elementor/transactions.php');

assert.doesNotMatch(routes, /register_rest_route\( 'ai-executor\/v1', '\/key'/);
assert.match(dashboard, /if \( ! current_user_can\( 'manage_options' \) \) \{\s*return;/);
assert.match(vision, /function wpae_vision_consume_provider_slot/);
assert.match(vision, /function wpae_validate_vision_provider_report/);
assert.match(vision, /wpae_vision_provider_invalid_report/);
assert.match(vision, /function wpae_validate_vision_report_scope/);
assert.match(vision, /function wpae_vision_editor_review/);
assert.match(vision, /function wpae_vision_render_context/);
assert.match(vision, /editor_review_is_advisory/);
assert.match(vision, /Ignore Elementor editor chrome/);
assert.match(vision, /render_context/);
assert.match(vision, /'render_context' => \$request->get_param\( 'render_context' \)/);
assert.match(vision, /'rolled_back' => false/);
assert.match(vision, /wpae_vision_capture_failed/);
assert.match(vision, /provider_http_status/);
assert.match(vision, /provider_message/);
assert.match(vision, /\$quality_failed = ! empty\( \$gate\['major_count'\] \)/);
assert.match(vision, /'blocking_advisory' => ! empty\( \$gate\['blocking'\] \)/);
assert.match(vision, /'quality_warning' => \$quality_failed/);
assert.match(vision, /score_below_floor/);
assert.match(transactions, /wpae_vision_unverified_report/);

console.log('vision security contract: OK');
