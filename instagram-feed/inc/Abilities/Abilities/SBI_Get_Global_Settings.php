<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\Services\SBI_Global_Settings_Service;
use InstagramFeed\Abilities\Services\SBI_Settings_Schema;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/get-global-settings
 *
 * @since 6.14.0
 */
class SBI_Get_Global_Settings extends SBI_Ability
{
	public function slug()
	{
		return 'get-global-settings';
	}

	public function args()
	{
		return array(
			'label'               => __('Get Instagram Feed global settings', 'instagram-feed'),
			'description'         => __(
				'Returns the plugin-wide Instagram Feed settings (caching, GDPR, image handling, script loading). Never returns connected accounts, tokens or license data.', // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
				'instagram-feed'
			),
			// No input_schema: a bare GET passes null input, which an object schema rejects.
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array_merge(
					SBI_Settings_Schema::to_json_properties_readable(SBI_Settings_Schema::global_settings()),
					array(
						'warnings' => array(
							'type'        => 'array',
							'items'       => array('type' => 'string'),
							'description' => 'One entry per stored setting that sits outside free\'s '
								. 'writable set (e.g. a Pro-only value left behind by a Pro-to-free '
								. 'downgrade). Empty when every stored value is within free\'s set.',
						),
					)
				),
			),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_manage'),
			'meta'                => $this->read_meta(true),
		);
	}

	public function execute()
	{
		return (new SBI_Global_Settings_Service())->get();
	}
}
