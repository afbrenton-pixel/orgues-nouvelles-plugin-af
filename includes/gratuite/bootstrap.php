<?php
/**
 * Free student subscription ("gratuité -26 ans"): module loader.
 *
 * Work is delivered step by step, see docs/gratuite.md.
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/private-proof-storage.php';
require_once __DIR__ . '/request-rules.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/form-action.php';
require_once __DIR__ . '/confirmation.php';
require_once __DIR__ . '/confirmation-admin.php';
require_once __DIR__ . '/emails.php';
require_once __DIR__ . '/subscription-rules.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/decision-rules.php';
require_once __DIR__ . '/decision.php';
require_once __DIR__ . '/subscription-admin.php';
