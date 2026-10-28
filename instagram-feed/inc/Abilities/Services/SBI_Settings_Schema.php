<?php

namespace InstagramFeed\Abilities\Services;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Typed allowlist of the settings exposed through the Abilities API.
 *
 * This is the single source of truth for what an AI agent may read or write.
 * Anything not listed here is never exposed — connected accounts, access
 * tokens, license data, custom CSS/JS and usage tracking are deliberately
 * absent. JSON Schemas for every ability are derived from these tables so the
 * pattern ports to the other feed plugins by swapping the tables only.
 *
 * Per-feed keys map 1:1 onto SBI_Feed_Saver::settings_defaults(); global keys
 * map onto the sb_instagram_settings option (see sbi_defaults()).
 *
 * @since 6.14.0
 */
class SBI_Settings_Schema
{
	const MAX_STRING_LENGTH = 500;

	/** Hex colour: #rgb, #rgba, #rrggbb, #rrggbbaa — or empty (plugin default). */
	const COLOR_PATTERN = '^(#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8}))?$';

	/**
	 * Per-feed settings. Free's strict, test-pinned subset of Pro's 68 keys
	 * (30 kept) — AgDR-9005. There is no `tier` concept in free: every key
	 * listed here is available the moment the feed is created. Enums and
	 * bounds are literals pinned by a drift test against the free plugin's
	 * own control tree (tests/abilities-schema-drift-test.php) — never a
	 * runtime tree walk. Where free's control declares no maximum, none is
	 * declared here (no invented bounds).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function feed_settings()
	{
		return array(
			'feed_name' => array(
				'type' => 'string',
				'description' => 'Internal feed name shown in the Feed Builder list.',
			),
			'cols' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => 10,
				'description' => 'Columns on desktop.',
			),
			'colstablet' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => 10,
				'description' => 'Columns on tablet.',
			),
			'colsmobile' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => 10,
				'description' => 'Columns on mobile.',
			),
			'num' => array(
				'type' => 'integer',
				'minimum' => 1,
				'description' => 'Number of posts on desktop. The free control declares no maximum.',
			),
			'nummobile' => array(
				'type' => 'integer',
				'minimum' => 1,
				'description' => 'Number of posts on mobile. The free control declares no maximum.',
			),
			'imagepadding' => array(
				'type' => 'integer',
				'minimum' => 0,
				'description' => 'Padding around each image, in px. The free control declares no maximum.',
			),
			'imageaspectratio' => array(
				'type' => 'string',
				'enum' => array('1:1', '3:4', '4:5'),
				'description' => 'Image aspect ratio.',
			),
			'sortby' => array(
				'type' => 'string',
				'enum' => array('none', 'random'),
				'description' => 'Post sort order (none = newest first). "likes" is a Pro-only sort and is '
					. 'not writable here; a feed carrying a stored "likes" value (e.g. after a Pro-to-free '
					. 'downgrade) reads it back as-is.',
			),
			'showheader' => array(
				'type' => 'boolean',
				'description' => 'Show the feed header.',
			),
			'headersize' => array(
				'type' => 'string',
				'enum' => array('small', 'medium', 'large'),
				'description' => 'Header size.',
			),
			'showbio' => array(
				'type' => 'boolean',
				'description' => 'Show the account bio in the header.',
			),
			'custombio' => array(
				'type' => 'string',
				'description' => 'Custom bio text shown in the header.',
			),
			'headercolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Header text colour (hex).',
			),
			'headeroutside' => array(
				'type' => 'boolean',
				'description' => 'Place the header outside the feed container.',
			),
			'imageres' => array(
				'type' => 'string',
				'enum' => array('auto', 'thumb', 'medium', 'full'),
				'description' => 'Image resolution.',
			),
			'showbutton' => array(
				'type' => 'boolean',
				'description' => 'Show the Load More button.',
			),
			'buttontext' => array(
				'type' => 'string',
				'description' => 'Load More button label.',
			),
			'buttoncolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Load More button background (hex).',
			),
			'buttonhovercolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Load More button hover background (hex).',
			),
			'buttontextcolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Load More button text colour (hex).',
			),
			'showfollow' => array(
				'type' => 'boolean',
				'description' => 'Show the Follow button.',
			),
			'followtext' => array(
				'type' => 'string',
				'description' => 'Follow button label.',
			),
			'followcolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Follow button background (hex).',
			),
			'followhovercolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Follow button hover background (hex).',
			),
			'followtextcolor' => array(
				'type' => 'string',
				'format' => 'color',
				'description' => 'Follow button text colour (hex).',
			),
			'colorpalette' => array(
				'type' => 'string',
				'enum' => array('inherit', 'light', 'dark', 'custom'),
				'writable' => false,
				'description' => 'Color palette. Read-only here: "custom" needs its custom*color '
					. 'companions set in the same write or the palette renders blank. Use '
					. 'set-color-palette, which validates the whole set together.',
			),
			// --- Custom color palette companions (colorpalette "custom"). Free's
			// control tree exposes only these three (AgDR-9005 §3a); customtextcolor1,
			// customtextcolor2 and customlinkcolor1 are commented out upstream and are
			// absent here entirely, never reported as `ignored`. Read-only for the same
			// AgDR-0504/AgDR-9004 reason as Pro: set together with colorpalette
			// through set-color-palette. ---
			'custombgcolor1' => array(
				'type' => 'string',
				'format' => 'color',
				'writable' => false,
				'description' => 'Custom palette background colour (colorpalette "custom"). Read-only: set '
					. 'with set-color-palette.',
			),
			'custombuttoncolor1' => array(
				'type' => 'string',
				'format' => 'color',
				'writable' => false,
				'description' => 'Custom palette Load More button background (colorpalette "custom"). '
					. 'Read-only: set with set-color-palette.',
			),
			'custombuttoncolor2' => array(
				'type' => 'string',
				'format' => 'color',
				'writable' => false,
				'description' => 'Custom palette Follow button background (colorpalette "custom"). '
					. 'Read-only: set with set-color-palette.',
			),
		);
	}

	/**
	 * Global (plugin-wide) settings stored in the sb_instagram_settings option.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function global_settings()
	{
		return array(
			'sbi_caching_type' => array(
				'type' => 'string',
				'enum' => array('background', 'page'),
				'description' => 'How feeds are refreshed. Legacy constant: the Settings UI always uses "background"; '
					. 'read-only.',
				'writable' => false,
			),
			'sbi_cache_cron_interval' => array(
				'type' => 'string',
				'enum' => array('30mins', '1hour', '12hours', '24hours'),
				'description' => 'Background cache refresh interval.',
			),
			'sbi_cache_cron_time' => array(
				'type' => 'string',
				'enum' => array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'),
				'description' => 'Hour for the daily (24hours) refresh.',
			),
			'sbi_cache_cron_am_pm' => array(
				'type' => 'string',
				'enum' => array('am', 'pm'),
				'description' => 'AM/PM for the daily refresh hour.',
			),
			'gdpr' => array(
				'type' => 'string',
				'enum' => array('auto', 'yes', 'no'),
				'description' => 'GDPR mode, as set in Settings → Feeds. "auto" turns it on only when '
					. 'a supported consent plugin is active, "yes" always on, "no" always off. '
					. 'While on, feeds skip third-party requests (Instagram images, scripts) until the '
					. 'visitor consents. It affects legal compliance, so confirm the change with the '
					. 'site owner first.',
			),
			'sb_instagram_disable_resize' => array(
				'type' => 'boolean',
				'description' => 'Disable local image resizing/storage.',
			),
			'image_format' => array(
				'type' => 'string',
				'enum' => array('webp', 'jpg'),
				'description' => 'Format for locally resized images.',
			),
			'sb_instagram_ajax_theme' => array(
				'type' => 'boolean',
				'description' => 'Load feeds via AJAX (for themes that break the initial load).',
			),
			'sb_ajax_initial' => array(
				'type' => 'boolean',
				'description' => 'Load the initial posts via AJAX.',
			),
			'enqueue_css_in_shortcode' => array(
				'type' => 'boolean',
				'description' => 'Enqueue CSS only where the shortcode is used.',
			),
			'enqueue_js_in_head' => array(
				'type' => 'boolean',
				'description' => 'Enqueue JS in the head instead of the footer.',
			),
			'disable_js_image_loading' => array(
				'type' => 'boolean',
				'description' => 'Disable JavaScript image loading.',
			),
			'disable_admin_notice' => array(
				'type' => 'boolean',
				'description' => 'Hide feed-issue admin notices. Read-only for AI assistants '
					. '(an agent must not silence error notices).',
				'writable' => false,
			),
		);
	}

	/**
	 * Build a JSON Schema `properties` map from a table (drops private keys).
	 *
	 * @param array $table
	 * @return array
	 */
	public static function to_json_properties(array $table)
	{
		$out = array();
		foreach ($table as $key => $def) {
			$prop = array('type' => $def['type']);
			foreach (array('enum', 'minimum', 'maximum', 'description') as $k) {
				if (isset($def[$k])) {
					$prop[$k] = $def[$k];
				}
			}
			if ($def['type'] === 'string' && !isset($def['enum'])) {
				$prop['maxLength'] = self::MAX_STRING_LENGTH;
			}
			if (isset($def['format']) && $def['format'] === 'color') {
				$prop['pattern']     = self::COLOR_PATTERN;
				$prop['description'] = (isset($prop['description']) ? $prop['description'] . ' ' : '')
					. 'Hex colour (#rgb, #rrggbb or #rrggbbaa) or empty for the default.';
			}
			if (isset($def['writable']) && $def['writable'] === false) {
				$prop['readOnly'] = true;
			}
			// No `tier` / `x-license-feature` branch: free has no license-tier
			// concept (AgDR-9005). Every row in this table is available the
			// moment the feed exists.
			$out[$key] = $prop;
		}
		return $out;
	}

	/**
	 * Build a JSON Schema `properties` map for a READ-side output schema.
	 *
	 * Unlike to_json_properties() (used for input/write schemas, and for the
	 * describe-settings-schema discovery ability), this never declares
	 * `enum`, `minimum` or `maximum` as hard constraints. A stored value can
	 * legally sit outside free's writable set — most commonly a Pro-only
	 * value left behind by a Pro-to-free downgrade (AgDR-9005 §3's
	 * downgrade-read rule) — and core validates ability OUTPUT against this
	 * schema, failing the WHOLE ability with a 500 on a single value that
	 * doesn't satisfy a declared enum/range. cast_out() below returns such a
	 * value unchanged, so the output schema must not contradict that by
	 * rejecting it. The free-only writable set is still declared precisely
	 * in the WRITE schema (to_json_properties($writable)); this is read-only.
	 *
	 * @param array $table
	 * @return array
	 */
	public static function to_json_properties_readable(array $table)
	{
		$out = array();
		foreach ($table as $key => $def) {
			$prop = array('type' => $def['type']);
			if (isset($def['description'])) {
				$prop['description'] = $def['description'];
			}
			// Deliberately no maxLength here either: cast_out() no longer
			// truncates on read (truncation is a WRITE-time concern, enforced
			// by validate_in()), so declaring one would let core reject a
			// legitimately long legacy/Pro value the same way an enum would.
			if (isset($def['format']) && $def['format'] === 'color') {
				$prop['pattern']     = self::COLOR_PATTERN;
				$prop['description'] = (isset($prop['description']) ? $prop['description'] . ' ' : '')
					. 'Hex colour (#rgb, #rrggbb or #rrggbbaa) or empty for the default.';
			}
			if (isset($def['writable']) && $def['writable'] === false) {
				$prop['readOnly'] = true;
			}
			$out[$key] = $prop;
		}
		return $out;
	}

	/**
	 * Cast a raw stored value to the declared type for output.
	 *
	 * Stored data is not guaranteed to satisfy free's writable enum/range —
	 * most commonly a Pro-only value left behind by a Pro-to-free downgrade
	 * (AgDR-9005 §3's downgrade-read rule), which is legally stored, not
	 * corrupt. Such a value is returned UNCHANGED: falling back to $default
	 * only happens when the stored value is genuinely invalid for its
	 * declared TYPE (missing, wrong PHP type, unparseable) — never merely
	 * because it sits outside the free enum/range. The one exception is a
	 * colour that doesn't match the hex pattern at all, which is real
	 * corruption (a value that predates this schema), not a legal Pro value.
	 * Range/enum-outside cases are surfaced separately via
	 * downgrade_warning() so the caller can populate `warnings[]`.
	 *
	 * @param array $def     Schema entry for the key.
	 * @param mixed $value   Raw stored value (null when missing).
	 * @param mixed $default Plugin default for the key.
	 * @return mixed
	 */
	public static function cast_out(array $def, $value, $default = null)
	{
		switch ($def['type']) {
			case 'boolean':
				// '' is a legitimate "off" in this plugin (legacy read path writes it for
				// showheader/showbutton/...), so only a MISSING value takes the default.
				if ($value === null) {
					$value = $default;
				}
				return $value === true || $value === 'true' || $value === 1 || $value === '1' || $value === 'on';

			case 'integer':
				if ($value === null || $value === '' || !is_numeric($value)) {
					$value = is_numeric($default) ? $default : 0;
				}
				// No min/max clamp: a legally-stored integer outside free's
				// declared range (e.g. left behind by a Pro-to-free downgrade)
				// is returned as-is. Clamping only ever happens at WRITE time,
				// via validate_in().
				return (int) $value;

			default:
				// A value of the wrong PHP type (array, object) is genuinely
				// invalid for a string-typed setting — that's the only type
				// failure this case can hit, so it falls back to the default.
				if (!is_scalar($value)) {
					$value = is_scalar($default) ? $default : '';
				}
				$str = (string) $value;

				// Clamp to the declared hex-colour pattern: a stored value can
				// predate this schema and not be hex at all — that IS real
				// corruption, unlike an out-of-enum string. '' always satisfies
				// COLOR_PATTERN (it is optional-empty), so it is always a safe
				// last resort even when $default itself does not match.
				$is_color = isset($def['format']) && $def['format'] === 'color';
				if ($is_color && !preg_match('/' . self::COLOR_PATTERN . '/', $str)) {
					$fallback_str = is_scalar($default) ? (string) $default : '';
					$str          = preg_match('/' . self::COLOR_PATTERN . '/', $fallback_str) ? $fallback_str : '';
				}

				// No enum fallback and no length truncation: a valid string
				// that sits outside free's writable enum, or exceeds the
				// write-time max length, is still a legally-stored value
				// (e.g. sortby=likes after a Pro downgrade, or a custombio
				// written before the 500-char write cap existed) and is
				// returned unchanged. Both enum membership and max length are
				// enforced at WRITE time only, via validate_in().
				return $str;
		}
	}

	/**
	 * Human-readable reason a stored value sits outside free's writable
	 * enum/range, or null when the value is within it (or missing/empty).
	 *
	 * cast_out() above always returns the value unchanged in this case; this
	 * is the sibling that lets a caller populate `warnings[]` explaining WHY,
	 * per AgDR-9005 §3's downgrade-read rule.
	 *
	 * @param array  $def
	 * @param mixed  $value Raw stored value (null when missing).
	 * @param string $key   Setting key, for the message.
	 * @return string|null
	 */
	public static function downgrade_warning(array $def, $value, $key)
	{
		if ($value === null || $value === '' || !is_scalar($value)) {
			return null;
		}

		if ($def['type'] === 'string' && isset($def['enum'])) {
			$str = (string) $value;
			if (!in_array($str, $def['enum'], true)) {
				// Clip the quoted value: cast_out() may legitimately return a
				// pathologically long stored string verbatim (no read-time
				// truncation — see cast_out()'s own comment), and this
				// message must stay short regardless of how long the stored
				// value is.
				$quoted = mb_strlen($str) > 100 ? mb_substr($str, 0, 100) . '…' : $str;
				return sprintf(
					/* translators: 1: setting key, 2: stored value (clipped to ~100 chars) */
					__(
						'%1$s: "%2$s" is a Pro-only value not writable in free; it is kept as stored.',
						'instagram-feed'
					),
					$key,
					$quoted
				);
			}
			return null;
		}

		if ($def['type'] === 'integer' && is_numeric($value)) {
			$int = (int) $value;
			if (isset($def['minimum']) && $int < $def['minimum']) {
				return sprintf(
					/* translators: 1: setting key, 2: stored value, 3: free minimum */
					__('%1$s: %2$d is below the free minimum of %3$d; it is kept as stored.', 'instagram-feed'),
					$key,
					$int,
					$def['minimum']
				);
			}
			if (isset($def['maximum']) && $int > $def['maximum']) {
				return sprintf(
					/* translators: 1: setting key, 2: stored value, 3: free maximum */
					__('%1$s: %2$d is above the free maximum of %3$d; it is kept as stored.', 'instagram-feed'),
					$key,
					$int,
					$def['maximum']
				);
			}
		}

		return null;
	}

	/**
	 * Writable subset of a table (keys not flagged `writable => false`).
	 *
	 * @param array $table
	 * @return array
	 */
	public static function writable(array $table)
	{
		return array_filter(
			$table,
			function ($def) {
				return !isset($def['writable']) || $def['writable'] !== false;
			}
		);
	}

	/**
	 * Validate + normalise ONE incoming value for storage.
	 *
	 * Core has already validated the request against the JSON Schema, so this is
	 * the second, PHP-side line: it never trusts the transport. Returns the
	 * value to store, or a WP_Error (code sbi_invalid_value, status 400).
	 *
	 * @param array  $def
	 * @param mixed  $value
	 * @param string $key
	 * @return mixed|\WP_Error
	 */
	public static function validate_in(array $def, $value, $key)
	{
		$bad = function ($reason) use ($key) {
			return new \WP_Error(
				'sbi_invalid_value',
				sprintf(
					/* translators: 1: setting key, 2: reason */
					__('Invalid value for %1$s: %2$s', 'instagram-feed'),
					$key,
					$reason
				),
				array('status' => 400)
			);
		};

		switch ($def['type']) {
			case 'boolean':
				if (is_bool($value)) {
					return $value;
				}
				if (in_array($value, array('true', '1', 1, 'on'), true)) {
					return true;
				}
				if (in_array($value, array('false', '0', 0, 'off', ''), true)) {
					return false;
				}
				return $bad(__('expected a boolean', 'instagram-feed'));

			case 'integer':
				if (!is_numeric($value) || (float) $value !== (float) (int) $value) {
					return $bad(__('expected an integer', 'instagram-feed'));
				}
				$int = (int) $value;
				if (isset($def['minimum']) && $int < $def['minimum']) {
					return $bad(sprintf(__('minimum is %d', 'instagram-feed'), $def['minimum']));
				}
				if (isset($def['maximum']) && $int > $def['maximum']) {
					return $bad(sprintf(__('maximum is %d', 'instagram-feed'), $def['maximum']));
				}
				return $int;

			default:
				if (!is_scalar($value)) {
					return $bad(__('expected a string', 'instagram-feed'));
				}
				$str = sanitize_text_field((string) $value);
				if (isset($def['enum']) && !in_array($str, $def['enum'], true)) {
					return $bad(sprintf(__('allowed values: %s', 'instagram-feed'), implode(', ', $def['enum'])));
				}
				$is_color = isset($def['format']) && $def['format'] === 'color';
				if ($is_color && !preg_match('/' . self::COLOR_PATTERN . '/', $str)) {
					return $bad(__('expected a hex colour such as #408bd1', 'instagram-feed'));
				}
				if (mb_strlen($str) > self::MAX_STRING_LENGTH) {
					return $bad(
						sprintf(__('maximum length is %d characters', 'instagram-feed'), self::MAX_STRING_LENGTH)
					);
				}
				return $str;
		}
	}
}
