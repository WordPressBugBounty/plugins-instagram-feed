<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\Services\SBI_Source_Service;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/get-connect-url
 *
 * The OAuth handoff: the agent hands the returned wp-admin link to the human;
 * that page (SBI_Connect_Bootstrap) consumes the single-use state token and
 * auto-submits the Feed Builder's own connect POST with a fresh session
 * nonce, so the relay round-trip and the account picker on return run
 * through the plugin's native path. The agent never sees Meta credentials.
 *
 * @since 6.14.0
 */
class SBI_Get_Connect_Url extends SBI_Ability
{
	public function slug()
	{
		return 'get-connect-url';
	}

	public function args()
	{
		return array(
			'label'               => __('Get an Instagram account-connect link', 'instagram-feed'),
			'description'         => __('Returns a single-use link (valid 15 minutes) the site admin opens in their browser to connect an Instagram account via Facebook/Instagram OAuth. The assistant never touches Meta credentials. After connecting, use list-sources to see the new account.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			// Null-tolerant: POST abilities must define an input schema when any
			// input arrives; a bodyless POST passes null.
			'input_schema'        => array(
				'type'                 => array('object', 'null'),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'connect_url'  => array('type' => 'string'),
					'expires_in'   => array('type' => 'integer'),
					'instructions' => array('type' => 'string'),
				),
			),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_write'),
			'meta'                => $this->write_meta(false, false),
		);
	}

	public function execute()
	{
		return (new SBI_Source_Service())->get_connect_url();
	}
}
