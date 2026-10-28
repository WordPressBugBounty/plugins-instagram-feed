<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\SBI_Ability_Response;
use InstagramFeed\Abilities\Services\SBI_Feed_Settings_Service;
use InstagramFeed\Abilities\Services\SBI_Settings_Schema;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/update-feed-settings
 *
 * Gated by SBI_Write_Gate, then capability. Free has no license-tier concept
 * (AgDR-9005) — every writable key in SBI_Settings_Schema::feed_settings()
 * is available the moment the feed exists; a key not in that schema comes
 * back in `ignored` as `unknown_setting`, a read-only key as `read_only`.
 *
 * @since 6.14.0
 */
class SBI_Update_Feed_Settings extends SBI_Ability
{
	public function slug()
	{
		return 'update-feed-settings';
	}

	public function args()
	{
		$writable = SBI_Settings_Schema::writable(SBI_Settings_Schema::feed_settings());
		$props    = array(
			'feed_id'  => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => 'Feed id from list-feeds.',
			),
			'settings' => array(
				'type'                 => 'object',
				'properties'           => SBI_Settings_Schema::to_json_properties($writable),
				'minProperties'        => 1,
				'additionalProperties' => false,
				'description'          => 'Settings to change. Only the keys you pass are changed; '
					. 'other stored values are re-saved as-is.',
			),
		);

		return array(
			'label'               => __('Update an Instagram Feed', 'instagram-feed'),
			'description'         => __('Changes one or more settings of a feed (columns, post count, header, buttons, follow button, colours). Only the keys you pass are changed. The custom color palette (colorpalette and its custom*color companions) is read-only here — use set-color-palette instead, which validates the coupled set together. Requires AI write access to be enabled in Instagram Feed → Settings → General. Returns updated, ignored and warnings. A setting that is not in this schema may be a Pro-only feature: call describe-settings-schema (pro_only) or get-pro-features.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => $props,
				'required'             => array('feed_id', 'settings'),
				'additionalProperties' => false,
			),
			'output_schema'       => $this->write_output_schema(array('feed_id' => array('type' => 'integer'))),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_write'),
			'meta'                => $this->write_meta(false, true),
		);
	}

	public function execute($input = null)
	{
		$input   = $this->normalize_input($input);
		$feed_id = isset($input['feed_id']) ? (int) $input['feed_id'] : 0;
		if ($feed_id <= 0) {
			return SBI_Ability_Response::invalid_feed_id();
		}
		$settings = isset($input['settings']) ? $this->normalize_input($input['settings']) : array();

		$service = new SBI_Feed_Settings_Service();
		$result  = $service->update_feed($feed_id, $settings);

		return $result === null ? SBI_Ability_Response::feed_not_found($feed_id) : $result;
	}
}
