<?php

namespace InstagramFeed\Abilities\Services;

use InstagramFeed\Builder\SBI_Db;
use InstagramFeed\Builder\SBI_Feed_Saver;
use SB_Instagram_Cache;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reads Feed Builder feeds for the Abilities API.
 *
 * Wraps SBI_Db / SBI_Feed_Saver and REDACTS everything an agent must not see:
 * SBI_Feed_Saver::get_feed_settings() hydrates connected sources with the
 * decrypted access token, so this service never returns raw source rows.
 *
 * @since 6.14.0
 */
class SBI_Feed_Settings_Service
{
	const MAX_PER_PAGE = 50;

	/**
	 * Per-feed keys whose change alters which posts are fetched from Instagram.
	 *
	 * @var string[]
	 */
	const RETRIEVAL_KEYS = array('num', 'nummobile', 'sortby', 'imageres');

	/**
	 * @param int $page 1-based.
	 * @param int $per_page
	 * @param string $status publish|draft|trash|all (default publish, matching WPForms list-forms).
	 * @return array{feeds: array, total: int, page: int, per_page: int, total_pages: int}
	 */
	public function list_feeds($page = 1, $per_page = 20, $status = 'publish')
	{
		global $wpdb;

		$page     = max(1, (int) $page);
		$per_page = min(self::MAX_PER_PAGE, max(1, (int) $per_page));
		$offset   = ($page - 1) * $per_page;
		$table    = $wpdb->prefix . 'sbi_feeds';

		$status = in_array($status, array('publish', 'draft', 'trash', 'all'), true) ? $status : 'publish';
		if ($status === 'all') {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is $wpdb->prefix + constant.
			$sql = $wpdb->prepare(
				"SELECT id, feed_name, feed_title, settings, status, last_modified
				FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is $wpdb->prefix + constant.
			$sql = $wpdb->prepare(
				"SELECT id, feed_name, feed_title, settings, status, last_modified
				FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d OFFSET %d",
				$status,
				$per_page,
				$offset
			);
		}
		$rows = $wpdb->get_results($sql, ARRAY_A);

		$feeds = array();
		foreach ((array) $rows as $row) {
			$settings = json_decode($row['settings'], true);
			$settings = is_array($settings) ? $settings : array();
			$feeds[]  = array(
				'id'            => (int) $row['id'],
				'feed_name'     => (string) $row['feed_name'],
				'feed_title'    => (string) $row['feed_title'],
				'type'          => isset($settings['type']) ? (string) $settings['type'] : 'user',
				'status'        => (string) $row['status'],
				'last_modified' => (string) $row['last_modified'],
				'shortcode'     => '[instagram-feed feed=' . (int) $row['id'] . ']',
			);
		}

		if ($status === 'all') {
			$total = SBI_Db::feeds_count();
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is $wpdb->prefix + constant.
			$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
		}

		return array(
			'feeds'       => $feeds,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil($total / $per_page),
		);
	}

	/**
	 * @param int $feed_id
	 * @return array|null Null when the feed does not exist.
	 */
	public function get_feed($feed_id)
	{
		$feed_id = (int) $feed_id;
		if ($feed_id <= 0) {
			return null;
		}

		$saver    = new SBI_Feed_Saver($feed_id);
		$settings = $saver->get_feed_settings();
		if (!is_array($settings)) {
			return null;
		}
		$db = $saver->get_feed_db_data();

		// Free drops storiestime, captionlength and captionsize entirely — they
		// have no free control (AgDR-9005 §3a) — so the Pro default-resolution
		// blocks for those keys (storiestime's empty-string-means-5000ms
		// sentinel; captionlength/captionsize's feed-template defaults) do not
		// apply here. Defaults stay runtime-derived straight from
		// settings_defaults() for every key free actually keeps.
		$defaults = SBI_Feed_Saver::settings_defaults();
		$schema   = SBI_Settings_Schema::feed_settings();
		$out      = array();
		$warnings = array();
		foreach ($schema as $key => $def) {
			$raw       = isset($settings[$key]) ? $settings[$key] : null;
			$out[$key] = SBI_Settings_Schema::cast_out(
				$def,
				$raw,
				isset($defaults[$key]) ? $defaults[$key] : null
			);
			$warning = SBI_Settings_Schema::downgrade_warning($def, $raw, $key);
			if ($warning !== null) {
				$warnings[] = $warning;
			}
		}
		$out['feed_name'] = isset($db['feed_name']) ? (string) $db['feed_name'] : $out['feed_name'];

		return array(
			'id'            => $feed_id,
			'feed_title'    => isset($db['feed_title']) ? (string) $db['feed_title'] : '',
			'type'          => isset($settings['type']) ? (string) $settings['type'] : 'user',
			'status'        => isset($db['status']) ? (string) $db['status'] : '',
			'last_modified' => isset($db['last_modified']) ? (string) $db['last_modified'] : '',
			'shortcode'     => '[instagram-feed feed=' . $feed_id . ']',
			'sources'       => $this->redact_sources(isset($settings['sources']) ? $settings['sources'] : array()),
			'settings'      => $out,
			'warnings'      => $warnings,
		);
	}

	/**
	 * Keep only what identifies the account. Never tokens, `info`, or errors.
	 *
	 * @param array $sources
	 * @return array<int, array{username: string, account_type: string}>
	 */
	private function redact_sources($sources)
	{
		$out = array();
		foreach ((array) $sources as $source) {
			if (!is_array($source)) {
				continue;
			}
			$out[] = array(
				'username'     => isset($source['username']) ? (string) $source['username'] : '',
				'account_type' => isset($source['type']) ? (string) $source['type'] : '',
			);
		}
		return $out;
	}

	/**
	 * Re-wrap `source_details` before every set_data() call that merges into a
	 * feed's raw stored settings.
	 *
	 * SBI_Feed_Saver::format_settings() collapses a one-element list to its
	 * single element on save, so a single-source feed stores `source_details`
	 * as a flat {id, username} map. Replaying that flat map through
	 * sanitize_and_sort_data() would turn it into a positional [id, username]
	 * list and break the username-based source-recovery fallback in
	 * get_feed_settings(). Re-wrap it so the round-trip is lossless.
	 *
	 * Shared by every write path that merges into raw stored settings
	 * (update_feed, set_color_palette) — AgDR-0504
	 * requirement 4 (side-effect parity): a partial write must not corrupt
	 * state the plugin's own save path leaves intact.
	 *
	 * @param array $merged Stored settings merged with a partial write.
	 * @return array
	 */
	private static function rewrap_single_source_details(array $merged)
	{
		if (
			isset($merged['source_details']) && is_array($merged['source_details'])
			&& isset($merged['source_details']['id']) && isset($merged['source_details']['username'])
		) {
			$merged['source_details'] = array($merged['source_details']);
		}
		return $merged;
	}

	/**
	 * Apply a partial update to one feed's settings.
	 *
	 * SBI_Feed_Saver::update() REPLACES the stored settings JSON with whatever
	 * was passed to set_data(), so we merge into the RAW stored row (never the
	 * token-hydrated get_feed_settings() array), run the same saver the Feed
	 * Builder uses, and clear the feed cache exactly like builder_update().
	 *
	 * Free has no license-tier concept (AgDR-9005), so unlike Pro this never
	 * takes a $tier_available callable — every writable key in the free
	 * schema is available the moment the feed exists.
	 *
	 * @param int   $feed_id
	 * @param array $partial key => value, already schema-validated by core.
	 * @return array{feed_id: int, updated: string[], ignored: object, warnings: string[]}|\WP_Error|null
	 *         Null when the feed does not exist.
	 */
	public function update_feed($feed_id, array $partial)
	{
		$feed_id = (int) $feed_id;
		if ($feed_id <= 0) {
			return null;
		}
		$rows = SBI_Db::feeds_query(array('id' => $feed_id));
		if (empty($rows)) {
			return null;
		}
		$row    = $rows[0];
		$stored = json_decode($row['settings'], true);
		$stored = is_array($stored) ? $stored : array();

		$schema   = SBI_Settings_Schema::feed_settings();
		$writable = SBI_Settings_Schema::writable($schema);
		$updated  = array();
		$ignored  = array();
		$clean    = array();
		$new_name = null;

		foreach ($partial as $key => $value) {
			if (!isset($schema[$key])) {
				$ignored[$key] = 'unknown_setting';
				continue;
			}
			if (!isset($writable[$key])) {
				$ignored[$key] = 'read_only';
				continue;
			}
			$v = SBI_Settings_Schema::validate_in($schema[$key], $value, $key);
			if (is_wp_error($v)) {
				return $v;
			}
			if ($key === 'feed_name') {
				$new_name = $v;
				$updated[] = $key;
				continue;
			}
			// The builder stores scalars as strings and booleans as booleans.
			$clean[$key] = is_bool($v) ? $v : (string) $v;
		}

		if (empty($clean) && $new_name === null) {
			return array(
				'feed_id'  => $feed_id,
				'updated'  => array(),
				'ignored'  => (object) $ignored,
				'warnings' => array(),
			);
		}

		$merged = array_merge($stored, $clean);
		unset($merged['sources']); // never persisted; builder_update() unsets it too.
		$merged = self::rewrap_single_source_details($merged);

		$saver = new SBI_Feed_Saver($feed_id);
		$saver->set_feed_name($new_name !== null ? $new_name : (string) $row['feed_name']);
		$saver->set_data($merged);
		// SBI_Db::feeds_update() returns $wpdb->update(): rows changed, or false on
		// error. An idempotent replay can legitimately change 0 rows.
		if ($saver->update_or_insert() === false) {
			return new \WP_Error(
				'sbi_feed_save_failed',
				__('The feed could not be saved.', 'instagram-feed'),
				array('status' => 500)
			);
		}
		$updated = array_merge($updated, array_keys($clean));

		if (class_exists('SB_Instagram_Cache')) {
			$cache = new SB_Instagram_Cache($feed_id);
			// 'all' only empties the rendered/header cache rows (local, cheap) so the
			// change shows immediately. 'posts' forces an Instagram API refetch, so it
			// is limited to keys that change WHAT is fetched — otherwise an agent
			// looping over cosmetic keys would burn Graph API quota.
			$cache->clear('all');
			if (array_intersect(self::RETRIEVAL_KEYS, array_keys($clean))) {
				$cache->clear('posts');
			}
		}

		/**
		 * Fires after an AI agent updated a feed through the Abilities API.
		 *
		 * @param int      $feed_id
		 * @param string[] $updated Keys that were written.
		 */
		do_action('sbi_abilities_feed_settings_updated', $feed_id, $updated);

		return array(
			'feed_id'  => $feed_id,
			'updated'  => $updated,
			'ignored'  => (object) $ignored,
			'warnings' => array(),
		);
	}

	/**
	 * Create a feed the way the builder's new_insert path does: quick
	 * defaults + validated quick settings + resolved sources.
	 *
	 * Free has no SBI_Feed_Template_Settings / FEED_TEMPLATES and no
	 * `feedtemplate` input — the plugin ships no feed-template picker
	 * (AgDR-9005 §6 item 10). A feed created here seeds the same bare
	 * `type=user, order=recent` shape the free builder's own new-insert path
	 * uses before any template expansion would apply.
	 *
	 * @param array $input {feed_name?, sources?, settings?}
	 * @return array|\WP_Error
	 */
	public function create_feed(array $input)
	{
		$settings_data = array(
			'type'  => 'user',
			'order' => 'recent',
		);

		$ignored = array();
		$updated = array();
		if (!empty($input['settings']) && is_array($input['settings'])) {
			$schema   = SBI_Settings_Schema::feed_settings();
			$writable = SBI_Settings_Schema::writable($schema);
			foreach ($input['settings'] as $key => $value) {
				if ($key === 'feed_name' || !isset($writable[$key])) {
					$ignored[$key] = isset($schema[$key]) ? 'read_only' : 'unknown_setting';
					continue;
				}
				$v = SBI_Settings_Schema::validate_in($schema[$key], $value, $key);
				if (is_wp_error($v)) {
					return $v;
				}
				$settings_data[$key] = is_bool($v) ? $v : (string) $v;
				$updated[]           = $key;
			}
		}

		$source_usernames = array();
		if (!empty($input['sources']) && is_array($input['sources'])) {
			$resolved = (new SBI_Source_Service())->resolve_sources($input['sources']);
			if (!empty($resolved['missing'])) {
				return new \WP_Error(
					'sbi_source_not_found',
					sprintf(
						/* translators: %s: comma-separated account names */
						__(
							'These accounts are not connected to this site: %s. '
							. 'Use get-connect-url to connect one first.',
							'instagram-feed'
						),
						\InstagramFeed\Abilities\SBI_Ability_Response::safe_list($resolved['missing'])
					),
					array('status' => 404)
				);
			}
			$settings_data['id']             = $resolved['ids'];
			$settings_data['source_details'] = $resolved['details'];
			foreach ($resolved['details'] as $d) {
				$source_usernames[] = $d['username'];
			}
		}

		$feed_name = !empty($input['feed_name'])
			? sanitize_text_field((string) $input['feed_name'])
			: SBI_Db::feeds_query_name(
				!empty($source_usernames) ? $source_usernames[0] : __('Instagram Feed', 'instagram-feed')
			);

		$saver = new SBI_Feed_Saver(false);
		$saver->set_feed_name($feed_name);
		$saver->set_data($settings_data);
		if (!$saver->update_or_insert()) {
			return new \WP_Error(
				'sbi_feed_save_failed',
				__('The feed could not be created.', 'instagram-feed'),
				array('status' => 500)
			);
		}
		$feed_id = (int) $saver->get_feed_id();

		/** This action is documented in SBI_Feed_Settings_Service::update_feed(). */
		do_action('sbi_abilities_feed_settings_updated', $feed_id, $updated);

		return array(
			'feed_id'   => $feed_id,
			'feed_name' => $feed_name,
			'shortcode' => '[instagram-feed feed=' . $feed_id . ']',
			'updated'   => $updated,
			'ignored'   => (object) $ignored,
			'warnings'  => array(),
		);
	}

	/**
	 * Attach already-connected accounts to a feed (replaces the source list).
	 *
	 * @param int      $feed_id
	 * @param string[] $handles usernames or account ids.
	 * @return array|\WP_Error|null Null when the feed does not exist.
	 */
	public function set_feed_sources($feed_id, array $handles)
	{
		$feed_id = (int) $feed_id;
		$rows    = SBI_Db::feeds_query(array('id' => $feed_id));
		if ($feed_id <= 0 || empty($rows)) {
			return null;
		}
		$resolved = (new SBI_Source_Service())->resolve_sources($handles);
		if (!empty($resolved['missing'])) {
			return new \WP_Error(
				'sbi_source_not_found',
				sprintf(
					/* translators: %s: comma-separated account names */
					__(
						'These accounts are not connected to this site: %s. '
						. 'Use get-connect-url to connect one first.',
						'instagram-feed'
					),
					\InstagramFeed\Abilities\SBI_Ability_Response::safe_list($resolved['missing'])
				),
				array('status' => 404)
			);
		}
		if (empty($resolved['ids'])) {
			return new \WP_Error(
				'sbi_invalid_value',
				__('At least one connected account is required.', 'instagram-feed'),
				array('status' => 400)
			);
		}

		$stored = json_decode($rows[0]['settings'], true);
		$stored = is_array($stored) ? $stored : array();
		$stored['id']             = $resolved['ids'];
		$stored['source_details'] = $resolved['details'];
		unset($stored['sources']);

		$saver = new SBI_Feed_Saver($feed_id);
		$saver->set_feed_name((string) $rows[0]['feed_name']);
		$saver->set_data($stored);
		if ($saver->update_or_insert() === false) {
			return new \WP_Error(
				'sbi_feed_save_failed',
				__('The feed could not be saved.', 'instagram-feed'),
				array('status' => 500)
			);
		}

		if (class_exists('SB_Instagram_Cache')) {
			$cache = new SB_Instagram_Cache($feed_id);
			$cache->clear('all');
			$cache->clear('posts'); // source change always alters what is fetched.
		}

		/** This action is documented in SBI_Feed_Settings_Service::update_feed(). */
		do_action('sbi_abilities_feed_settings_updated', $feed_id, array('sources'));

		// resolve_sources() silently de-dupes a handle that resolves to an
		// account already present in the set (two requested handles, one
		// account) — surface that as a warning rather than a bare, misleading
		// success (AgDR-0504 requirement 3, same pattern as set-color-palette).
		$found_count     = count($handles) - count($resolved['missing']);
		$duplicate_count = max(0, $found_count - count($resolved['ids']));
		$warnings        = array();
		if ($duplicate_count > 0) {
			$warnings[] = sprintf(
				/* translators: %d: number of duplicate handles */
				_n(
					'%d requested account resolved to one already present in the set and was not duplicated.',
					'%d requested accounts resolved to ones already present in the set and were not duplicated.',
					$duplicate_count,
					'instagram-feed'
				),
				$duplicate_count
			);
		}

		return array(
			'feed_id'  => $feed_id,
			'sources'  => array_map(
				function ($d) {
					return $d['username'];
				},
				$resolved['details']
			),
			'updated'  => array('sources'),
			'ignored'  => (object) array(),
			'warnings' => $warnings,
		);
	}

	/**
	 * Set a feed's color palette — the validating ability for the custom*color
	 * companions AgDR-0504 requirement 2 made read-only in the generic setter
	 * (finding A4). "custom" with no colour supplied is refused rather than
	 * saved as a palette that renders blank.
	 *
	 * Free's control tree exposes only 3 of Pro's 6 custom*color keys
	 * (AgDR-9005 §3a) — customtextcolor1, customtextcolor2 and
	 * customlinkcolor1 are commented out in
	 * SBI_Customize_Tab::get_customize_colorscheme_controls() and have no free
	 * UI, so they are absent from this set, not silently accepted.
	 *
	 * @param int    $feed_id      Feed id.
	 * @param string $colorpalette inherit|light|dark|custom.
	 * @param array  $colors       key => hex value, any of the three free custom*color keys.
	 * @return array{feed_id: int, updated: string[], ignored: object, warnings: string[]}|\WP_Error|null
	 *         Null when the feed does not exist.
	 */
	public function set_color_palette($feed_id, $colorpalette, array $colors)
	{
		$feed_id = (int) $feed_id;
		$rows    = SBI_Db::feeds_query(array('id' => $feed_id));
		if ($feed_id <= 0 || empty($rows)) {
			return null;
		}

		$schema  = SBI_Settings_Schema::feed_settings();
		$palette = SBI_Settings_Schema::validate_in($schema['colorpalette'], $colorpalette, 'colorpalette');
		if (is_wp_error($palette)) {
			return $palette;
		}

		$custom_keys = array(
			'custombgcolor1',
			'custombuttoncolor1',
			'custombuttoncolor2',
		);

		$clean    = array('colorpalette' => $palette);
		$ignored  = array();
		$warnings = array();

		if ($palette === 'custom') {
			$any = false;
			foreach ($custom_keys as $key) {
				if (!isset($colors[$key]) || $colors[$key] === '') {
					continue;
				}
				$v = SBI_Settings_Schema::validate_in($schema[$key], $colors[$key], $key);
				if (is_wp_error($v)) {
					return $v;
				}
				$clean[$key] = $v;
				$any         = true;
			}
			if (!$any) {
				return new \WP_Error(
					'sbi_invalid_value',
					__(
						'At least one custom*color value is required when colorpalette is "custom".',
						'instagram-feed'
					),
					array('status' => 400)
				);
			}
		} elseif (!empty($colors)) {
			// Accepted-but-inert (AgDR-0504 requirement 3): the values are valid
			// hex colours, just unused while the palette isn't "custom". `inert`,
			// not `feature_unavailable` — free has no license tiers, so that
			// reason would wrongly imply a licensed-but-locked feature.
			foreach (array_keys($colors) as $key) {
				$ignored[$key] = 'inert';
			}
			$warnings[] = 'colorpalette is "' . $palette . '", not "custom" — the supplied custom*color '
				. 'values were not saved.';
		}

		$row    = $rows[0];
		$stored = json_decode($row['settings'], true);
		$stored = is_array($stored) ? $stored : array();
		$merged = array_merge($stored, $clean);
		unset($merged['sources']);
		$merged = self::rewrap_single_source_details($merged);

		$saver = new SBI_Feed_Saver($feed_id);
		$saver->set_feed_name((string) $row['feed_name']);
		$saver->set_data($merged);
		if ($saver->update_or_insert() === false) {
			return new \WP_Error(
				'sbi_feed_save_failed',
				__('The feed could not be saved.', 'instagram-feed'),
				array('status' => 500)
			);
		}

		if (class_exists('SB_Instagram_Cache')) {
			$cache = new SB_Instagram_Cache($feed_id);
			$cache->clear('all'); // cosmetic only — never touches what is fetched.
		}

		/** This action is documented in SBI_Feed_Settings_Service::update_feed(). */
		do_action('sbi_abilities_feed_settings_updated', $feed_id, array_keys($clean));

		return array(
			'feed_id'  => $feed_id,
			'updated'  => array_keys($clean),
			'ignored'  => (object) $ignored,
			'warnings' => $warnings,
		);
	}
}
