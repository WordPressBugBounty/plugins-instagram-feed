<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\SBI_Write_Gate;
use InstagramFeed\Abilities\Services\SBI_Global_Settings_Service;
use InstagramFeed\Abilities\Services\SBI_Settings_Schema;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/update-global-settings
 *
 * Gated by SBI_Write_Gate (off by default), then the same capability as the
 * Settings page. Unknown keys are rejected by core (additionalProperties:false);
 * read-only keys are reported in `ignored`.
 *
 * @since 6.14.0
 */
class SBI_Update_Global_Settings extends SBI_Ability
{
	public function slug()
	{
		return 'update-global-settings';
	}

	public function args()
	{
		$writable = SBI_Settings_Schema::writable(SBI_Settings_Schema::global_settings());

		return array(
			'label'               => __('Update Instagram Feed global settings', 'instagram-feed'),
			'description'         => __('Updates one or more plugin-wide Instagram Feed settings (caching schedule, GDPR mode, image handling, script loading). Requires AI write access to be enabled in Instagram Feed → Settings → General. Returns the keys that were updated and the keys that were ignored.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => SBI_Settings_Schema::to_json_properties($writable),
				'minProperties'        => 1,
				'additionalProperties' => false,
			),
			'output_schema'       => $this->write_output_schema(),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_write'),
			'meta'                => $this->write_meta(false, true),
		);
	}

	public function execute($input = null)
	{
		return (new SBI_Global_Settings_Service())->update($this->normalize_input($input));
	}
}
