<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\Services\SBI_Feed_Settings_Service;
use InstagramFeed\Abilities\Services\SBI_Settings_Schema;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/create-feed
 *
 * @since 6.14.0
 */
class SBI_Create_Feed extends SBI_Ability
{
	public function slug()
	{
		return 'create-feed';
	}

	public function args()
	{
		$writable = SBI_Settings_Schema::writable(SBI_Settings_Schema::feed_settings());
		// feed_name has a dedicated top-level property; a nested copy would be
		// silently written into the settings blob instead of the feeds row.
		unset($writable['feed_name']);

		return array(
			'label'               => __('Create an Instagram Feed', 'instagram-feed'),
			'description'         => __('Creates a new USER-type feed (the connected accounts\' own posts) like the Feed Builder does: optional quick settings, and optional already-connected accounts as sources. Free has no feed-template picker. Returns the new feed id and its shortcode. Requires AI write access. A setting that is not in this schema may be a Pro-only feature: call describe-settings-schema (pro_only) or get-pro-features.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'feed_name' => array(
						'type'        => 'string',
						'maxLength'   => 200,
						'description' => 'Optional name; defaults to the first source username.',
					),
					'sources'   => array(
						'type'        => 'array',
						'items'       => array('type' => 'string', 'maxLength' => 100),
						'maxItems'    => 10,
						'description' => 'Usernames or account ids of ALREADY-CONNECTED accounts (see list-sources).',
					),
					'settings'  => array(
						'type'                 => 'object',
						'properties'           => SBI_Settings_Schema::to_json_properties($writable),
						'additionalProperties' => false,
						'description'          => 'Quick settings applied on top of the defaults.',
					),
				),
				'additionalProperties' => false,
			),
			// write_output_schema() declares `warnings` alongside `updated` and
			// `ignored` (AgDR-9005 §6 item 10 — Pro's create-feed omits `warnings`
			// from ITS output schema, a backport bug flagged there, not repeated
			// here).
			'output_schema'       => $this->write_output_schema(
				array(
					'feed_id'   => array('type' => 'integer'),
					'feed_name' => array('type' => 'string'),
					'shortcode' => array('type' => 'string'),
				)
			),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_write'),
			'meta'                => $this->write_meta(false, false),
		);
	}

	public function execute($input = null)
	{
		return (new SBI_Feed_Settings_Service())->create_feed($this->normalize_input($input));
	}
}
