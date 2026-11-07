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
