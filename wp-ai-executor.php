<?php
/**
 * Plugin Name: WP AI Executor
 * Description: Secure REST endpoint for AI automation (Claude, GPT, Gemini, Qwen, etc.). Execute PHP in WordPress context via any AI agent.
 * Version:     v02.08.48
 * Author:      DIAS
 * License:     MIT
 */

defined( 'ABSPATH' ) || exit;

const WPAE_VERSION = 'v02.08.48';
const WPAE_ROLLBACK_TTL_SECONDS = 7200;
const WPAE_ROLLBACK_MAX_SNAPSHOTS = 20;
const WPAE_OPERATION_LOG_MAX_ENTRIES = 100;
const WPAE_EXPORT_TTL_SECONDS = 3600;
const WPAE_EXPORT_MAX_ENTRIES = 20;

require_once __DIR__ . '/includes/updates/package-updater.php';
require_once __DIR__ . '/includes/updates/self-update.php';
require_once __DIR__ . '/includes/security/capabilities.php';
require_once __DIR__ . '/includes/skills/skills.php';
require_once __DIR__ . '/includes/design/system.php';
require_once __DIR__ . '/includes/support/logging.php';
require_once __DIR__ . '/includes/health/diagnostics.php';
require_once __DIR__ . '/includes/rollback/rollback.php';
require_once __DIR__ . '/includes/elementor/validation-rules.php';
require_once __DIR__ . '/includes/elementor/design-contract.php';
require_once __DIR__ . '/includes/elementor/validation.php';
require_once __DIR__ . '/includes/elementor/data.php';
require_once __DIR__ . '/includes/elementor/normalize.php';
require_once __DIR__ . '/includes/elementor/transactions.php';
require_once __DIR__ . '/includes/elementor/typography.php';
require_once __DIR__ . '/includes/elementor/recipes.php';
require_once __DIR__ . '/includes/elementor/blueprint.php';
require_once __DIR__ . '/includes/elementor/compose.php';
require_once __DIR__ . '/includes/elementor/visual-audit.php';
require_once __DIR__ . '/includes/elementor/editability.php';
require_once __DIR__ . '/includes/elementor/css-native.php';
require_once __DIR__ . '/includes/elementor/page-update.php';
require_once __DIR__ . '/includes/media/media.php';
require_once __DIR__ . '/includes/exports/exports.php';
require_once __DIR__ . '/includes/execution/run.php';
require_once __DIR__ . '/includes/guide/session.php';
require_once __DIR__ . '/includes/guide/guide.php';
require_once __DIR__ . '/includes/rest/routes.php';
require_once __DIR__ . '/includes/admin/dashboard.php';
