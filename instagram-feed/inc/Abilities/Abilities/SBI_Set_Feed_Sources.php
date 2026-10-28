<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\SBI_Ability_Response;
use InstagramFeed\Abilities\Services\SBI_Feed_Settings_Service;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/set-feed-sources
 *
 * Destructive by annotation: changing sources changes what a feed shows, so
 * MCP clients that honour annotations (WPVibe) show their approval panel.
 *
 * @since 6.14.0
 */
class SBI_Set_Feed_Sources extends SBI_Ability
{
	public function slug()
	{
		return 'set-feed-sources';
	}

	public function args()
	{
		return array(
			'label'               => __('Set the accounts an Instagram Feed shows', 'instagram-feed'),
			'description'         => __('Replaces a feed\'s source list with already-connected accounts (see list-sources; use get-connect-url to connect a new one first). Changes what the feed displays. Requires AI write access.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'feed_id' => array('type' => 'integer', 'minimum' => 1),
					'sources' => array(
						'type'        => 'array',
						'items'       => array('type' => 'string', 'maxLength' => 100),
						'minItems'    => 1,
						'maxItems'    => 10,
						'description' => 'Usernames or account ids of connected accounts.',
					),
				),
				'required'             => array('feed_id', 'sources'),
				'additionalProperties' => false,
			),
			'output_schema'       => $this->write_output_schema(
				array(
					'feed_id' => array('type' => 'integer'),
					'sources' => array(
						'type'        => 'array',
						'items'       => array('type' => 'string'),
						'description' => 'The feed\'s resulting connected-account username list.',
					),
				)
			),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_write'),
			'meta'                => $this->write_meta(true, true),
		);
	}

	public function execute($input = null)
	{
		$input   = $this->normalize_input($input);
		$feed_id = isset($input['feed_id']) ? (int) $input['feed_id'] : 0;
		if ($feed_id <= 0) {
			return SBI_Ability_Response::invalid_feed_id();
		}
		$handles = isset($input['sources']) && is_array($input['sources'])
			? array_map('strval', $input['sources'])
			: array();
		$result  = (new SBI_Feed_Settings_Service())->set_feed_sources($feed_id, $handles);

		return $result === null ? SBI_Ability_Response::feed_not_found($feed_id) : $result;
	}
}
