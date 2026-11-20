<?php

/**
 * Standby mode while Instagram Feed Pro is active (SMASH-2563).
 *
 * The free plugin and Instagram Feed Pro define the same functions, classes and
 * constants. When Pro is active, the free plugin's main file loads this file,
 * calls sbi_free_standby() and returns before it defines anything Pro also
 * defines. The free plugin stays active but loads no feeds, menus, settings,
 * assets, crons, REST routes or AJAX handlers. It only adds a notice to its own
 * row on the Plugins screen, and restores its cron events when Pro is
 * deactivated.
 *
 * Every name in this file is unique to the free plugin. Pro never loads this
 * file. The main file loads this file before its PHP version check, so keep the
 * syntax compatible with old PHP versions.
 *
 * Keep this path and file name. Pro's activation.php checks that
 * instagram-feed/inc/sbi-pro-standby.php exists to tell a free plugin that can
 * stand by from an older one, which Pro still deactivates
 * (sbi_deactivate_free_without_standby()).
 *
 * @package Instagram Feed
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (!function_exists('sbi_free_should_stand_by')) {
	/**
	 * Whether the free plugin must stand by in this request.
	 *
	 * Pro's activation routine checks that this function exists. When it does,
	 * Pro knows that this free plugin can stay active next to it.
	 *
	 * @return bool
	 */
	function sbi_free_should_stand_by()
	{
		// sbi_builder_pro() exists only in Pro's main file, so Pro has already loaded in this request.
		return function_exists('sbi_builder_pro') || sbi_free_pro_is_active();
	}

	/**
	 * Whether Instagram Feed Pro is installed and active on this site or for the whole network.
	 *
	 * Plugin API helpers such as is_plugin_active() are not loaded on the front
	 * end, so this reads the active plugin options directly, the same way
	 * WordPress does when it loads plugins.
	 *
	 * @return bool
	 */
	function sbi_free_pro_is_active()
	{
		$pro_basename = 'instagram-feed-pro/instagram-feed.php';

		// WordPress skips an active plugin whose file is missing, so the free plugin must run.
		if (!file_exists(WP_PLUGIN_DIR . '/' . $pro_basename)) {
			return false;
		}

		if (in_array($pro_basename, (array) get_option('active_plugins', array()), true)) {
			return true;
		}

		if (!is_multisite()) {
			return false;
		}

		$network_plugins = (array) get_site_option('active_sitewide_plugins', array());

		return isset($network_plugins[$pro_basename]);
	}

	/**
	 * The free plugin's basename, for example instagram-feed/instagram-feed.php.
	 *
	 * @return string
	 */
	function sbi_free_standby_plugin_basename()
	{
		return plugin_basename(dirname(__DIR__) . '/instagram-feed.php');
	}

	/**
	 * Add the only hooks the free plugin registers while it stands by.
	 *
	 * @return void
	 */
	function sbi_free_standby()
	{
		add_action('after_plugin_row_' . sbi_free_standby_plugin_basename(), 'sbi_free_standby_plugin_row', 20);
		add_action('load-plugins.php', 'sbi_free_standby_dismiss_notice');
		add_action('deactivated_plugin', 'sbi_free_standby_pro_deactivated');
	}

	/**
	 * Print the standby notice under the free plugin's row on the Plugins screen.
	 *
	 * @return void
	 */
	function sbi_free_standby_plugin_row()
	{
		if (get_user_meta(get_current_user_id(), 'sbi_free_standby_notice_dismissed', true)) {
			return;
		}

		$free_basename = sbi_free_standby_plugin_basename();

		// On the Network Admin screen, show the notice only when both plugins are network-active.
		// Otherwise the free plugin still runs on the sites where Pro is not active.
		if (is_network_admin()) {
			$network_plugins = (array) get_site_option('active_sitewide_plugins', array());
			if (!isset($network_plugins['instagram-feed-pro/instagram-feed.php'], $network_plugins[$free_basename])) {
				return;
			}
		}

		$colspan = 4;
		if (function_exists('_get_list_table')) {
			$list_table = _get_list_table('WP_Plugins_List_Table', array('screen' => get_current_screen()));
			if ($list_table) {
				$colspan = $list_table->get_column_count();
			}
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg('sbi_free_standby_dismiss', '1', self_admin_url('plugins.php')),
			'sbi_free_standby_dismiss'
		);
		$message     = implode(
			' ',
			array(
				__('Instagram Feed Pro is active, so this free version is on standby.', 'instagram-feed'),
				__('It loads no feeds, menus or settings of its own.', 'instagram-feed'),
				__('If you deactivate Pro, the free version runs your feeds again.', 'instagram-feed'),
				__('You can keep this plugin or delete it.', 'instagram-feed'),
				__('Deleting it does not remove your feeds or settings.', 'instagram-feed'),
			)
		);

		// data-plugin lets the Plugins screen remove this row with the plugin row when the plugin is deleted.
		printf(
			'<tr class="plugin-update-tr active sbi-free-standby-notice" data-plugin="%5$s">'
			. '<td colspan="%1$d" class="plugin-update colspanchange">'
			. '<div class="notice inline notice-info notice-alt"><p>%2$s</p><p><a href="%3$s">%4$s</a></p></div>'
			. '</td></tr>',
			(int) $colspan,
			esc_html($message),
			esc_url($dismiss_url),
			esc_html__('Dismiss this notice', 'instagram-feed'),
			esc_attr($free_basename)
		);
	}

	/**
	 * Save the dismissal of the standby notice for the current user.
	 *
	 * Runs on load-plugins.php, so the free plugin registers no AJAX action.
	 *
	 * @return void
	 */
	function sbi_free_standby_dismiss_notice()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only. check_admin_referer() runs next.
		if (!isset($_GET['sbi_free_standby_dismiss'])) {
			return;
		}

		check_admin_referer('sbi_free_standby_dismiss');

		if (!current_user_can('activate_plugins')) {
			return;
		}

		update_user_meta(get_current_user_id(), 'sbi_free_standby_notice_dismissed', time());

		wp_safe_redirect(remove_query_arg(array('sbi_free_standby_dismiss', '_wpnonce')));
		exit;
	}

	/**
	 * Schedule the free plugin's cron events again after Pro is deactivated.
	 *
	 * @param string $plugin Basename of the plugin that was deactivated.
	 *
	 * @return void
	 */
	function sbi_free_standby_pro_deactivated($plugin)
	{
		if ('instagram-feed-pro/instagram-feed.php' !== $plugin) {
			return;
		}

		// deactivate_plugins() saves the active plugin lists after this action, so check them at shutdown.
		add_action('shutdown', 'sbi_free_standby_restore_cron_events');
	}

	/**
	 * Restore the cron events that Pro's deactivation clears, when the free plugin takes over.
	 *
	 * Pro's deactivation routine clears these shared events. The free plugin's
	 * activation routine schedules them, but it does not run again because the
	 * free plugin stayed active.
	 *
	 * Also drop the setup-wizard redirect. The free plugin's activation routine
	 * saves it, and the free plugin's first wp-admin page load uses it. When the
	 * free plugin was activated outside wp-admin (for example with WP-CLI) and
	 * then stood by, the redirect is still saved. A site that ran Pro does not
	 * need the free plugin's first-run wizard.
	 *
	 * @return void
	 */
	function sbi_free_standby_restore_cron_events()
	{
		$free_basename   = sbi_free_standby_plugin_basename();
		$network_plugins = is_multisite() ? (array) get_site_option('active_sitewide_plugins', array()) : array();
		$free_is_active  = in_array($free_basename, (array) get_option('active_plugins', array()), true)
			|| isset($network_plugins[$free_basename]);

		if (!$free_is_active || sbi_free_pro_is_active()) {
			return;
		}

		delete_option('sbi_plugin_do_activation_redirect');

		// Re-schedule sb_instagram_cron_job to match what the free plugin's activation
		// routine schedules. No callback in either plugin listens to it today.
		if (!wp_next_scheduled('sb_instagram_cron_job')) {
			wp_schedule_event(time(), 'twicedaily', 'sb_instagram_cron_job');
		}
		if (!wp_next_scheduled('sb_instagram_twicedaily')) {
			wp_schedule_event(time(), 'twicedaily', 'sb_instagram_twicedaily');
		}
		// Pro's copy of this shared function is loaded in this request. Both copies are the same.
		if (!wp_next_scheduled('sb_instagram_feed_issue_email') && function_exists('sbi_schedule_report_email')) {
			sbi_schedule_report_email();
		}
	}
}
