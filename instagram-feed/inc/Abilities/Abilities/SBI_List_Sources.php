<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\Services\SBI_Source_Service;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/list-sources
 *
 * @since 6.14.0
 */
class SBI_List_Sources extends SBI_Ability
{
	public function slug()
	{
		return 'list-sources';
	}

	public function args()
	{
		return array(
			'label'               => __('List connected Instagram accounts', 'instagram-feed'),
			'description'         => __('Lists the Instagram accounts connected to this site (username, type, token expiry date, error flag, how many feeds use each). Never returns access tokens or account credentials.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			// No input_schema: a bare GET passes null input, which an object schema rejects.
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'sources' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'username'      => array('type' => 'string'),
								'account_type'  => array('type' => 'string'),
								'connect_type'  => array('type' => 'string'),
								'expires'       => array('type' => 'string'),
								'has_error'     => array('type' => 'boolean'),
								'used_in_feeds' => array('type' => 'integer'),
							),
						),
					),
				),
			),
			'execute_callback'    => array($this, 'execute'),
			'permission_callback' => array($this, 'can_manage'),
			'meta'                => $this->read_meta(true),
		);
	}

	public function execute()
	{
		return array('sources' => (new SBI_Source_Service())->list_sources());
	}
}
