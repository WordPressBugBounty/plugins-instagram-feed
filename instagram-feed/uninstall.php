<?php

/**
 * Runs when the plugin is deleted (SMASH-2563).
 *
 * WordPress runs this file instead of the uninstall callback that the main file
 * registers. It loads the main file and runs sb_instagram_uninstall(). That
 * function keeps the data when Instagram Feed Pro is installed.
 *
 * While Pro is active, the main file stands by and does not define
 * sb_instagram_uninstall(). When Pro is loaded, Pro's copy runs and keeps the
 * shared data. When Pro is active but not loaded, for example while recovery
 * mode pauses it, no copy exists. The stored callback then failed with a PHP 8
 * TypeError. Now nothing runs, and the data stays for Pro.
 *
 * WordPress loads this file only to delete the plugin, so it adds no work to
 * other requests.
 *
 * @package Instagram Feed
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once __DIR__ . '/instagram-feed.php';

if (function_exists('sb_instagram_uninstall')) {
	sb_instagram_uninstall();
}
