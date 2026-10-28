<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\SBI_Ability_Response;
use InstagramFeed\Abilities\Services\SBI_Feed_Settings_Service;
use InstagramFeed\Abilities\Services\SBI_Settings_Schema;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/get-feed
 *
 * @since 6.14.0
 */
class SBI_Get_Feed extends SBI_Ability
{
	public function slug()
	{
		return 'get-feed';
	}

	public function args()
	{
		return array(
			'label'               => __('Get an Instagram Feed', 'instagram-feed'),
			'description'         => __(
				'Returns one feed\'s customer-configurable settings (layout, columns, post count, header, captions, buttons, colors). Connected accounts are reduced to username and type; tokens are never returned.', // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
				'instagram-feed'
			),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'feed_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Feed id from list-feeds.',
					),
				),
				'required'             => array('feed_id'),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'            => array('type' => 'integer'),
					'feed_title'    => array('type' => 'string'),
					'type'          => array('type' => 'string'),
					'status'        => array('type' => 'string'),
					'last_modified' => array('type' => 'string'),
					'shortcode'     => array('type' => 'string'),
					'sources'       => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'username'     => array('type' => 'string'),
								'account_type' => array('type' => 'string'),
							),
						),
					),
					'settings'      => array(
						'type'       => 'object',
						'properties' => SBI_Settings_Schema::to_json_properties_readable(
							SBI_Settings_Schema::feed_settings()
						),
					),
					'warnings'      => array(
						'type'        => 'array',
						'items'       => array('type' => 'string'),
						'description' => 'One entry per stored setting that sits outside free\'s writable '
							. 'set (e.g. a Pro-only value left behind by a Pro-to-free downgrade). Empty '
							. 'when every stored value is within free\'s set.',
					),
				),
			),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_manage'),
			'meta'                => $this->read_meta(true),
		);
	}

	public function execute($input = null)
	{
		$input   = $this->normalize_input($input);
		$feed_id = isset($input['feed_id']) ? (int) $input['feed_id'] : 0;
		if ($feed_id <= 0) {
			return SBI_Ability_Response::invalid_feed_id();
		}
		$feed = (new SBI_Feed_Settings_Service())->get_feed($feed_id);
		return $feed === null ? SBI_Ability_Response::feed_not_found($feed_id) : $feed;
	}
}
