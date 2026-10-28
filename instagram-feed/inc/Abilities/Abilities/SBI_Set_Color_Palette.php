<?php

namespace InstagramFeed\Abilities\Abilities;

use InstagramFeed\Abilities\SBI_Ability_Response;
use InstagramFeed\Abilities\Services\SBI_Feed_Settings_Service;
use InstagramFeed\Abilities\Services\SBI_Settings_Schema;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * instagram-feed/set-color-palette
 *
 * `colorpalette` and its custom*color companions are read-only in
 * update-feed-settings (AgDR-0504 requirement 2): "custom" with no colour
 * supplied is a state the feed can't complete — it renders with a blank
 * palette (finding A4). This ability validates the whole set together and
 * refuses "custom" with nothing to fill it, mirroring Custom Twitter Feeds'
 * set-feed-type-and-terms. Free's control tree exposes only 3 of Pro's 6
 * custom*color keys (AgDR-9005 §3a) — customtextcolor1, customtextcolor2
 * and customlinkcolor1 have no free UI and are absent here entirely (not
 * declared, and never reported as `ignored`).
 *
 * @since 6.14.0
 */
class SBI_Set_Color_Palette extends SBI_Ability
{
	public function slug()
	{
		return 'set-color-palette';
	}

	public function args()
	{
		$color_props = array();
		foreach (
			array(
				'custombgcolor1',
				'custombuttoncolor1',
				'custombuttoncolor2',
			) as $key
		) {
			$color_props[$key] = array(
				'type'        => 'string',
				'pattern'     => SBI_Settings_Schema::COLOR_PATTERN,
				'description' => 'Only used when colorpalette is "custom".',
			);
		}

		return array(
			'label'               => __('Set an Instagram Feed color palette', 'instagram-feed'),
			'description'         => __('Sets a feed\'s color palette: inherit, light, dark, or custom. When custom, at least one custom*color value is required or the write is rejected — a "custom" palette with no colour renders blank. Requires AI write access.', 'instagram-feed'), // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array_merge(
					array(
						'feed_id'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Feed id from list-feeds.',
						),
						'colorpalette' => array(
							'type' => 'string',
							'enum' => array('inherit', 'light', 'dark', 'custom'),
						),
					),
					$color_props
				),
				'required'             => array('feed_id', 'colorpalette'),
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
		$colorpalette = isset($input['colorpalette']) ? (string) $input['colorpalette'] : '';
		$colors       = array_intersect_key(
			$input,
			array_flip(
				array(
					'custombgcolor1',
					'custombuttoncolor1',
					'custombuttoncolor2',
				)
			)
		);

		$result = (new SBI_Feed_Settings_Service())->set_color_palette($feed_id, $colorpalette, $colors);

		return $result === null ? SBI_Ability_Response::feed_not_found($feed_id) : $result;
	}
}
