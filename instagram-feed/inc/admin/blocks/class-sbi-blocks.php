<?php // phpcs:ignore Squiz.Commenting.FileComment.SpacingAfterOpen -- PSR12 requires a blank line between <?php and the file docblock.

/**
 * Instagram Feed block with live preview.
 *
 * @package InstagramFeed
 * @since 2.3
 */

use InstagramFeed\Helpers\Util;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class SB_Instagram_Blocks
{
	/**
	 * Checking if is Gutenberg REST API call.
	 *
	 * @return bool True if is Gutenberg REST API call.
	 * @since 2.3
	 */
	public static function is_gb_editor()
	{
        return defined('REST_REQUEST') && REST_REQUEST && !empty($_REQUEST['context']) && 'edit' === $_REQUEST['context']; // phpcs:ignore
	}

	/**
	 * Indicates if current integration is allowed to load.
	 *
	 * @return bool
	 * @since 1.8
	 */
	public function allow_load()
	{
		return function_exists('register_block_type');
	}

	/**
	 * Loads an integration.
	 *
	 * @since 2.3
	 */
	public function load()
	{
		$this->hooks();

		require_once trailingslashit(SBI_PLUGIN_DIR) . 'inc/admin/blocks/SBI_Modern_Feed_Block.php';
		$modern_block = new \InstagramFeed\Admin\Blocks\SBI_Modern_Feed_Block();
		$modern_block->register_hooks();
	}

	/**
	 * Integration hooks.
	 *
	 * @since 2.3
	 */
	protected function hooks()
	{
		add_action('init', array($this, 'register_block'), 99);
		// Priority 25 mirrors the pre-existing behavior so our localized data lands
		// after WP core / common editor assets but before theme/plugin hooks at 30+.
		add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'), 25);
		add_filter('block_editor_settings_all', array($this, 'inject_iframe_styles'));
	}

	/**
	 * Inject block UI and feed CSS into the WP 7.0+ iframed editor canvas.
	 *
	 * `block_editor_settings_all` exposes a `styles` array that WordPress renders
	 * inline inside the iframe `<head>`. wp_enqueue_style on the outer admin page
	 * does not propagate to the iframe for api_version 3 blocks, so we have to
	 * push the CSS contents through this filter for it to be visible inside the
	 * iframe (e.g. the license-expired notice rendered by get_feed_html()).
	 *
	 * @param array $settings Block editor settings.
	 * @return array
	 */
	public function inject_iframe_styles($settings)
	{
		// Cache the CSS payload across the request lifecycle. block_editor_settings_all
		// fires on every block-editor request (post editor, site editor, widget editor)
		// and the CSS bytes on disk don't change between calls, so re-reading them is
		// wasteful disk I/O on the hottest path of the editor.
		// TODO: also scope this by screen so we only inject when the editor could host
		// this plugin's blocks. Scoping is intentionally skipped for now because
		// block_editor_settings_all fires in REST contexts where get_current_screen()
		// is unreliable, and over-scoping would re-break the iframe styling fix.
		static $cached = null;

		if ($cached === null) {
			$files = array(
				trailingslashit(SBI_PLUGIN_DIR) . 'css/sb-instagram-admin.css',
				trailingslashit(SBI_PLUGIN_DIR) . 'css/sbi-styles.min.css',
			);

			$cached = array();
			foreach ($files as $file) {
				if (! file_exists($file)) {
					continue;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading local plugin CSS, WP_Filesystem would be overkill.
				$css = file_get_contents($file);
				if ($css === false) {
					continue;
				}
				$cached[] = array( 'css' => $css );
			}
		}

		if (! isset($settings['styles']) || ! is_array($settings['styles'])) {
			$settings['styles'] = array();
		}

		foreach ($cached as $entry) {
			$settings['styles'][] = $entry;
		}

		return $settings;
	}

	/**
	 * Register Instagram Feed Gutenberg block on the backend.
	 *
	 * @since 2.3
	 */
	public function register_block()
	{

		wp_register_style(
			'sbi-blocks-styles',
			trailingslashit(SBI_PLUGIN_URL) . 'css/sb-blocks.css',
			array('wp-edit-blocks'),
			SBIVER
		);

		$attributes = array(
			'shortcodeSettings' => array(
				'type' => 'string',
			),
			'noNewChanges' => array(
				'type' => 'boolean',
			)
		);

		register_block_type(
			'sbi/sbi-feed-block',
			array(
				'api_version' => 3,
				'attributes' => $attributes,
				'render_callback' => array($this, 'get_feed_html'),
				'supports' => array( 'inserter' => false ),
			)
		);
	}

	/**
	 * Load Instagram Feed Gutenberg block scripts.
	 *
	 * @since 2.3
	 */
	public function enqueue_block_editor_assets()
	{
		$db = sbi_get_database_settings();

		sb_instagram_scripts_enqueue(true);

		wp_enqueue_style('sbi-blocks-styles');
		wp_enqueue_script(
			'sbi-feed-block',
			trailingslashit(SBI_PLUGIN_URL) . 'js/sb-blocks.js',
			array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor'),
			SBIVER,
			true
		);

		$shortcodeSettings = '';

		$i18n = array(
			'addSettings' => esc_html__('Add Settings', 'instagram-feed'),
			'shortcodeSettings' => esc_html__('Shortcode Settings', 'instagram-feed'),
			'example' => esc_html__('Example', 'instagram-feed'),
			'preview' => esc_html__('Apply Changes', 'instagram-feed'),

		);

		// Mirror the data that sb_instagram_scripts_enqueue() localizes onto
		// the sbi_scripts handle. The editor JS injects sbi-scripts.min.js
		// into the WP 7.0 iframe head and needs these globals defined inside
		// the iframe before that script runs.
		$br_adjust = !( isset($db['sbi_br_adjust']) && ( $db['sbi_br_adjust'] == 'false' || $db['sbi_br_adjust'] == '0' || ! $db['sbi_br_adjust'] ) );
		$sb_instagram_js_options = array(
			'font_method' => 'svg',
			'resized_url' => sbi_get_resized_uploads_url(),
			'placeholder' => trailingslashit(SBI_PLUGIN_URL) . 'img/placeholder.png',
			'br_adjust'   => $br_adjust,
		);
		if (isset($db['sb_instagram_disable_mob_swipe']) && $db['sb_instagram_disable_mob_swipe']) {
			$sb_instagram_js_options['no_mob_swipe'] = true;
		}

		$is_script_debug = Util::isDebugging() || Util::is_script_debug();

		$sbi_js_file = $is_script_debug
			? 'js/sbi-scripts.js'
			: 'js/sbi-scripts.min.js';

		$jquery_file = 'js/jquery/jquery' . ( $is_script_debug ? '' : '.min' ) . '.js';

		wp_localize_script(
			'sbi-feed-block',
			'sbi_block_editor',
			array(
				'wpnonce' => wp_create_nonce('sb-instagram-blocks'),
				'canShowFeed' => !empty($db['connected_accounts']),
				'configureLink' => admin_url('admin.php?page=sbi-settings'),
				'shortcodeSettings' => $shortcodeSettings,
				'i18n' => $i18n,
				'iframeScriptUrl' => trailingslashit(SBI_PLUGIN_URL) . $sbi_js_file,
				'jqueryUrl' => includes_url($jquery_file),
				'sbInstagramJsOptions' => $sb_instagram_js_options,
				'sbiTranslations' => array( 'share' => __('Share', 'instagram-feed') ),
			)
		);
	}

	/**
	 * Get form HTML to display in a Instagram Feed Gutenberg block.
	 *
	 * @param array $attr Attributes passed by Instagram Feed Gutenberg block.
	 *
	 * @return string
	 * @since 2.3
	 */
	public function get_feed_html($attr)
	{

		$return = '';

		$shortcode_settings = isset($attr['shortcodeSettings']) ? $attr['shortcodeSettings'] : '';

		$shortcode_settings = str_replace(array('[instagram-feed', ']'), '', $shortcode_settings);

		$return .= do_shortcode('[instagram-feed ' . $shortcode_settings . ']');

		return $return;
	}
}
