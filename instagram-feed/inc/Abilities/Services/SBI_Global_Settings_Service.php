<?php

namespace InstagramFeed\Abilities\Services;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reads plugin-wide settings for the Abilities API, restricted to the
 * SBI_Settings_Schema::global_settings() allowlist.
 *
 * Thin over sbi_get_database_settings(); never touches tokens or licenses.
 *
 * @since 6.14.0
 */
class SBI_Global_Settings_Service
{
	/**
	 * @return array<string, mixed>
	 */
	public function get()
	{
		$all      = sbi_get_database_settings();
		$defaults = sbi_defaults();
		$schema   = SBI_Settings_Schema::global_settings();
		$out      = array();
		$warnings = array();
		foreach ($schema as $key => $def) {
			$raw       = isset($all[$key]) ? $all[$key] : null;
			$out[$key] = SBI_Settings_Schema::cast_out(
				$def,
				$raw,
				isset($defaults[$key]) ? $defaults[$key] : null
			);
			$warning = SBI_Settings_Schema::downgrade_warning($def, $raw, $key);
			if ($warning !== null) {
				$warnings[] = $warning;
			}
		}
		$out['warnings'] = $warnings;
		return $out;
	}

	/**
	 * Apply a partial update to the plugin-wide settings.
	 *
	 * Mirrors what SBI_Global_Settings::sbi_save_settings() persists for the
	 * same keys (the allowlist keys ARE the option keys), then restarts the
	 * background cron the way the Settings page does when cron keys change.
	 *
	 * @param array $partial key => value, already schema-validated by core.
	 * @return array{updated: string[], ignored: object, warnings: string[]}|\WP_Error
	 */
	public function update(array $partial)
	{
		$schema   = SBI_Settings_Schema::global_settings();
		$writable = SBI_Settings_Schema::writable($schema);
		$updated  = array();
		$ignored  = array();
		$clean    = array();

		foreach ($partial as $key => $value) {
			if (!isset($schema[$key])) {
				$ignored[$key] = 'unknown_setting';
				continue;
			}
			if (!isset($writable[$key])) {
				$ignored[$key] = 'read_only';
				continue;
			}
			$v = SBI_Settings_Schema::validate_in($schema[$key], $value, $key);
			if (is_wp_error($v)) {
				return $v;
			}
			$clean[$key] = $v;
		}

		if (empty($clean)) {
			return array('updated' => array(), 'ignored' => (object) $ignored, 'warnings' => array());
		}

		$settings = get_option('sb_instagram_settings', array());
		$settings = is_array($settings) ? $settings : array();
		foreach ($clean as $key => $v) {
			$settings[$key] = $v;
			$updated[]      = $key;
		}
		update_option('sb_instagram_settings', $settings);

		// Same side effects as the Settings page save (reset_caches_and_cron()): drop the
		// stored feed caches so gdpr / image_format changes show immediately.
		if (function_exists('sbi_clear_all_feed_caches')) {
			sbi_clear_all_feed_caches();
		}
		delete_option('sbi_cron_report');

		$cron_keys = array('sbi_cache_cron_interval', 'sbi_cache_cron_time', 'sbi_cache_cron_am_pm');
		if (array_intersect($cron_keys, $updated) && class_exists('SB_Instagram_Cron_Updater')) {
			$all = sbi_get_database_settings();
			\SB_Instagram_Cron_Updater::start_cron_job(
				$all['sbi_cache_cron_interval'],
				$all['sbi_cache_cron_time'],
				$all['sbi_cache_cron_am_pm']
			);
		}

		// Parity with SBI_Global_Settings::reset_caches_and_cron()
		// (admin/SBI_Global_Settings.php): the Settings-page save also resets the
		// per-account API request delay backoff and logs the change, so an agent-driven save behaves
		// identically to a human clicking Save on the configure tab (AgDR-9004
		// req. 4, AgDR-9005 §4).
		global $sb_instagram_posts_manager;
		if (!empty($sb_instagram_posts_manager) && method_exists($sb_instagram_posts_manager, 'clear_api_request_delays')) {
			$sb_instagram_posts_manager->clear_api_request_delays();
		}
		if (!empty($sb_instagram_posts_manager) && method_exists($sb_instagram_posts_manager, 'add_action_log')) {
			$sb_instagram_posts_manager->add_action_log('AI agent updated settings.');
		}

		/**
		 * Fires after an AI agent updated global settings through the Abilities API.
		 *
		 * @param string[] $updated Keys that were written.
		 */
		do_action('sbi_abilities_global_settings_updated', $updated);

		// AgDR-0504 requirement 3: sbi_cache_cron_time / _am_pm only take effect
		// when the interval is "24hours" — writing them under any other interval
		// is accepted (they ARE stored) but has no visible effect until the
		// interval changes too.
		$warnings      = array();
		$cron_time_keys = array('sbi_cache_cron_time', 'sbi_cache_cron_am_pm');
		if (array_intersect($cron_time_keys, $updated)) {
			$all      = sbi_get_database_settings();
			$interval = isset($all['sbi_cache_cron_interval']) ? $all['sbi_cache_cron_interval'] : '';
			if ($interval !== '24hours') {
				$warnings[] = 'sbi_cache_cron_time and sbi_cache_cron_am_pm only apply when '
					. 'sbi_cache_cron_interval is "24hours" — the value is saved but currently unused.';
			}
		}

		if (in_array('gdpr', $updated, true) && $clean['gdpr'] === 'no') {
			$warnings[] = 'GDPR mode is now "no": feeds load Instagram images and scripts before '
				. 'visitors consent. Tell the site owner, since this can affect legal compliance.';
		}

		return array('updated' => $updated, 'ignored' => (object) $ignored, 'warnings' => $warnings);
	}
}
