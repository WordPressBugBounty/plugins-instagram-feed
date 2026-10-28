<?php

namespace InstagramFeed\Abilities;

use WP_Error;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * WP_Error factories shared by every Instagram Feed ability.
 *
 * Every error carries an HTTP status in `data.status` so the core Abilities
 * REST controller and the MCP adapter surface the right code.
 *
 * @since 6.14.0
 */
class SBI_Ability_Response
{
	public static function forbidden()
	{
		return new WP_Error(
			'sbi_forbidden',
			__('You do not have permission to manage Instagram Feed settings.', 'instagram-feed'),
			array('status' => 403)
		);
	}

	public static function writes_disabled()
	{
		return new WP_Error(
			'sbi_writes_disabled',
			__(
				'AI write access is disabled. Enable "MCP Write Access" under Instagram Feed → Settings → General.', // phpcs:ignore Generic.Files.LineLength.TooLong -- single literal for make-pot
				'instagram-feed'
			),
			array('status' => 403)
		);
	}

	public static function invalid_feed_id()
	{
		return new WP_Error(
			'sbi_invalid_feed_id',
			__('A valid feed_id is required.', 'instagram-feed'),
			array('status' => 400)
		);
	}

	public static function feed_not_found($feed_id)
	{
		return new WP_Error(
			'sbi_feed_not_found',
			sprintf(
				/* translators: %d: feed id */
				__('Feed %d was not found.', 'instagram-feed'),
				(int) $feed_id
			),
			array('status' => 404)
		);
	}

	/**
	 * Render caller-supplied values (account handles) for interpolation into an
	 * error message: tags/control characters stripped, HTML-escaped, each value
	 * length-limited and the list capped, so unknown input is never echoed raw.
	 *
	 * @param array $values Raw caller-supplied strings.
	 * @param int   $max_items Maximum values to show.
	 * @param int   $max_length Maximum characters per value.
	 * @return string Comma-separated, safe to place in a message.
	 */
	public static function safe_list(array $values, $max_items = 10, $max_length = 60)
	{
		$shown = array();
		foreach (array_slice($values, 0, $max_items) as $value) {
			$clean = sanitize_text_field((string) $value);
			if (function_exists('mb_strlen') && mb_strlen($clean) > $max_length) {
				$clean = mb_substr($clean, 0, $max_length) . '...';
			} elseif (!function_exists('mb_strlen') && strlen($clean) > $max_length) {
				$clean = substr($clean, 0, $max_length) . '...';
			}
			$shown[] = esc_html($clean);
		}
		$out   = implode(', ', $shown);
		$extra = count($values) - count($shown);
		if ($extra > 0) {
			/* translators: %d: number of additional values not listed */
			$out .= ' ' . sprintf(__('(and %d more)', 'instagram-feed'), $extra);
		}
		return $out;
	}
}
