<?php

namespace InstagramFeed\Abilities;

use InstagramFeed\Builder\SBI_Feed_Builder;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Pro upsell surfaces for the FREE edition's Abilities API (AgDR-9006).
 *
 * One place that knows three things, so every surface stays consistent and the
 * copy never drifts from the rest of the plugin:
 *   1. the upgrade URL + UTM convention  -> upgrade_url()
 *   2. which Pro features exist, and which feed settings they unlock
 *                                         -> features() / pro_only_settings()
 *   3. the one-line "this is Pro" hint attached to Pro-caused refusals
 *                                         -> hint() / hint_for_setting()
 *
 * Feature names/descriptions are NOT restated here: they are read from the
 * builder's own upsell popup (SBI_Feed_Builder::get_extensions_popup()), so a
 * copy change there flows through automatically. Only the map of "feature ->
 * Pro-only setting keys" lives here, and tests pin it against the free schema
 * (every listed key must be absent from the free writable schema).
 *
 * UTM convention (one rule, so every port matches):
 *   - utm_source = `abilities` for API responses, `settings` for the admin card;
 *   - utm_medium = the feature id, i.e. the popup/upsell key in kebab-case
 *     (`feedLayout` -> `feed-layout`, see feature_medium()), for any link tied to one
 *     feature;
 *   - utm_medium = `ai-agents-card` for the banner, and `get-pro-features` for that
 *     ability's top-level upgrade_url.
 *
 * Free edition only. Every public method is inert in a Pro build.
 *
 * PORTING: swap BASE_URL / UTM_CAMPAIGN, the data source in features(), and
 * PRO_SETTINGS_BY_FEATURE. Nothing else is plugin-specific.
 *
 * @since 6.14.0
 */
class SBI_Pro_Upsell
{
	/** The plugin's existing lite-upgrade landing page (same one the Settings and builder CTAs use). */
	const BASE_URL = 'https://smashballoon.com/instagram-feed/instagram-lite-upgrade/';

	/** Matches the `utm_campaign` every other free-edition upgrade link already carries. */
	const UTM_CAMPAIGN = 'instagram-free';

	/**
	 * Pro-only feed settings, grouped by the builder-popup feature that unlocks them.
	 *
	 * Keys are the SBI_Feed_Builder::get_extensions_popup() array keys. Values are
	 * setting keys that exist in Pro and are deliberately ABSENT from the free
	 * schema (AgDR-9005), or `key=value` for a single Pro-only value of a key that
	 * free does expose (sortby=likes). Features with no per-feed setting
	 * (hashtag/tagged feeds, Social Wall, moderation, shoppable) map to an empty
	 * list: never invent a key for them.
	 *
	 * @var array<string, string[]>
	 */
	const PRO_SETTINGS_BY_FEATURE = array(
		'hashtag'          => array(),
		'tagged'           => array(),
		'socialwall'       => array(),
		'feedLayout'       => array(
			'layout', 'carouselrows', 'carouselarrows', 'carouselautoplay', 'carouselloop',
			'carouselpag', 'carouseltime', 'highlighttype', 'highlightpattern',
			'highlightoffset', 'highlightids', 'highlighthashtag',
		),
		'headerLayout'     => array(
			'headerstyle', 'showfollowers', 'stories', 'storiestime', 'headerprimarycolor',
			'headersecondarycolor', 'headertext', 'headertextsize',
		),
		'postStyling'      => array(
			'showcaption', 'captionlength', 'captioncolor', 'captionsize', 'showlikes',
			'likescolor', 'likessize', 'hovereffect', 'hovercolor', 'poststyle', 'postbgcolor',
			'postcorners', 'boxshadow', 'customtextcolor1', 'customtextcolor2', 'customlinkcolor1',
			'sortby=likes',
		),
		'lightbox'         => array('disablelightbox'),
		'filtermoderation' => array(),
		'shoppablefeed'    => array(),
	);

	/**
	 * Popup keys of the 3 features the AI Agents card banner names. Picked by key
	 * (not array position) so reordering the builder popup cannot change the banner.
	 *
	 * @var string[]
	 */
	const BANNER_FEATURES = array('feedLayout', 'hashtag', 'lightbox');

	/**
	 * Only the free edition promotes Pro. Guarded here so a future shared build
	 * can never surface any of this inside Pro.
	 *
	 * @return bool
	 */
	public static function is_free()
	{
		return !function_exists('sbi_is_pro_version') || !sbi_is_pro_version();
	}

	/**
	 * Upgrade link with the plugin's standard UTM set.
	 *
	 * @param string $medium What was shown (e.g. `get-pro-features`, `feed-layout`, `ai-agents-card`).
	 * @param string $source Surface: `abilities` for API responses, `settings` for the admin card.
	 * @return string
	 */
	public static function upgrade_url($medium, $source = 'abilities')
	{
		return self::BASE_URL
			. '?utm_campaign=' . self::UTM_CAMPAIGN
			. '&utm_source=' . rawurlencode((string) $source)
			. '&utm_medium=' . rawurlencode((string) $medium)
			. '&utm_content=UpgradeToPro';
	}

	/**
	 * Pro features as `[name, description, settings[]]`.
	 *
	 * Name + description come from the builder's upsell popup; `settings` from
	 * PRO_SETTINGS_BY_FEATURE. Empty outside the free edition.
	 *
	 * @return array<int, array{name: string, description: string, settings: string[]}>
	 */
	public static function features()
	{
		if (!self::is_free() || !class_exists(SBI_Feed_Builder::class)) {
			return array();
		}
		$out = array();
		foreach (SBI_Feed_Builder::get_extensions_popup() as $id => $item) {
			$out[] = array(
				'name'        => (string) $item['featureName'],
				'description' => (string) $item['description'],
				'settings'    => isset(self::PRO_SETTINGS_BY_FEATURE[$id]) ? self::PRO_SETTINGS_BY_FEATURE[$id] : array(),
			);
		}
		return $out;
	}

	/**
	 * Every Pro-only setting key / `key=value`, flattened. Drives the `pro_only`
	 * block of describe-settings-schema.
	 *
	 * @return string[]
	 */
	public static function pro_only_settings()
	{
		if (!self::is_free()) {
			return array();
		}
		$all = array();
		foreach (self::PRO_SETTINGS_BY_FEATURE as $settings) {
			$all = array_merge($all, $settings);
		}
		return array_values(array_unique($all));
	}

	/**
	 * utm_medium for a feature: its popup key in kebab-case (`feedLayout` -> `feed-layout`).
	 *
	 * @param string $feature_id Popup/upsell key.
	 * @return string
	 */
	public static function feature_medium($feature_id)
	{
		return strtolower(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', (string) $feature_id));
	}

	/**
	 * Names of the BANNER_FEATURES, in banner order, from the builder's popup copy.
	 * Keys missing from the popup are skipped.
	 *
	 * @return string[]
	 */
	public static function banner_feature_names()
	{
		if (!self::is_free() || !class_exists(SBI_Feed_Builder::class)) {
			return array();
		}
		$popup = SBI_Feed_Builder::get_extensions_popup();
		$names = array();
		foreach (self::BANNER_FEATURES as $id) {
			if (isset($popup[$id]['featureName'])) {
				$names[] = (string) $popup[$id]['featureName'];
			}
		}
		return $names;
	}

	/**
	 * One-line hint for a refusal caused by a Pro-only feature.
	 *
	 * @internal Not wired into any ability in this edition. Core JSON-schema
	 *           validation (additionalProperties:false, narrower enums) rejects
	 *           Pro-only keys/values with a 400 before plugin code runs, so a hint
	 *           there is unreachable. Use hint() / hint_for_setting() only where
	 *           plugin code itself refuses a Pro-caused request (feed/source caps,
	 *           single-source rules, tier checks, a dropped template) in editions
	 *           that have those refusals.
	 *
	 * @param string $feature_label Human name of the Pro feature.
	 * @param string $medium        UTM medium: the feature id via feature_medium().
	 * @return string Empty outside the free edition.
	 */
	public static function hint($feature_label, $medium)
	{
		if (!self::is_free()) {
			return '';
		}
		return sprintf(
			/* translators: 1: Pro feature name, 2: upgrade URL */
			__('%1$s is a Pro feature. Upgrade: %2$s', 'instagram-feed'),
			$feature_label,
			self::upgrade_url($medium)
		);
	}

	/**
	 * Hint for a refused setting key (and optionally value), or '' when the
	 * refusal is not Pro-caused (a typo'd key gets no upsell).
	 *
	 * @internal See hint(): for plugin-side refusals only; unreachable through the
	 *           free schema-validated abilities.
	 *
	 * @param string $key   Setting key the caller sent.
	 * @param mixed  $value Value the caller sent (only used for `key=value` Pro values).
	 * @return string
	 */
	public static function hint_for_setting($key, $value = null)
	{
		$pro = self::pro_only_settings();
		$hit = in_array($key, $pro, true)
			|| (is_scalar($value) && in_array($key . '=' . $value, $pro, true));
		if (!$hit) {
			return '';
		}
		$token = in_array($key, $pro, true) ? $key : $key . '=' . $value;
		if (!class_exists(SBI_Feed_Builder::class)) {
			return '';
		}
		$popup = SBI_Feed_Builder::get_extensions_popup();
		foreach (self::PRO_SETTINGS_BY_FEATURE as $id => $settings) {
			if (in_array($token, $settings, true) && isset($popup[$id]['featureName'])) {
				return self::hint((string) $popup[$id]['featureName'], self::feature_medium($id));
			}
		}
		return '';
	}
}
