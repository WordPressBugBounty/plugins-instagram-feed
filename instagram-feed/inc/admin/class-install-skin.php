<?php

use Sbi\Helpers\PluginSilentUpgraderSkin;

/**
 * Skin for on-the-fly addon installations.
 *
 * @since 1.0.0
 * @since 1.5.6.1 Extend PluginSilentUpgraderSkin and clean up the class.
 */
class Sbi_Install_Skin extends PluginSilentUpgraderSkin
{
	/**
	 * Instead of outputting HTML for errors, json_encode the errors and send them
	 * back to the Ajax script for processing.
	 *
	 * @param array $errors Array of errors with the install process.
	 * @since 1.0.0
	 */
	public function error($errors)
	{

		if (!empty($errors)) {
			wp_send_json_error($errors);
		}
	}
}
