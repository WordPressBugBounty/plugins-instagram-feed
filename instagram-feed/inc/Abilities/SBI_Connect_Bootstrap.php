<?php

namespace InstagramFeed\Abilities;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Browser-side start of an ability-initiated account connect.
 *
 * The Feed Builder never *links* to the OAuth relay: its JS auto-submits a
 * POST form (add-source.js, processIFConnect()) carrying a browser-session
 * `sbi_con` nonce and a raw state field. This page reproduces that form
 * exactly. `get-connect-url` hands the admin a wp-admin URL carrying only our
 * single-use state token; landing here (capability-checked, token consumed)
 * mints a FRESH session nonce and auto-submits the same POST the Connect
 * button would — so the relay round-trip and the account-picker popup on
 * return run through the plugin's native path, completely unchanged.
 *
 * The state token never leaves this site: the relay only ever sees the same
 * fields the builder sends it.
 *
 * @since 6.14.0
 */
class SBI_Connect_Bootstrap
{
	public function __construct()
	{
		add_action('admin_init', array($this, 'maybe_render'), 1);
	}

	public function maybe_render()
	{
		if (
			empty($_GET['page']) || $_GET['page'] !== 'sbi-feed-builder'
			|| empty($_GET['sbi_abilities_connect']) || !is_string($_GET['sbi_abilities_connect'])
		) {
			return;
		}
		if (!sbi_current_user_can('manage_instagram_feed_options')) {
			return;
		}
		// Revocation parity with SBI_Delete_Confirmation::handle_delete(): if AI
		// write access was turned off after this link was minted, honour the
		// revocation rather than completing a connect an agent is no longer
		// allowed to have requested.
		$token = sanitize_key($_GET['sbi_abilities_connect']);
		// The token is consumed (burned) when the gate refuses, so turning write
		// access back on later cannot revive a link the admin was told is dead.
		// Capability was checked above and tokens are user-bound, so this can
		// only burn the current user's own link.
		if (!SBI_Write_Gate::is_enabled()) {
			SBI_State_Token::consume($token, 'connect');
			wp_die(
				esc_html__('AI write access has been disabled since this link was created.', 'instagram-feed'),
				esc_html__('Instagram Feed', 'instagram-feed'),
				array('response' => 403, 'back_link' => true)
			);
		}
		if (!SBI_State_Token::consume($token, 'connect')) {
			wp_die(
				esc_html__(
					'This connect link is invalid or has expired. Ask your AI assistant for a new one.',
					'instagram-feed'
				),
				esc_html__('Instagram Feed', 'instagram-feed'),
				array('response' => 403, 'back_link' => true)
			);
		}

		// Never restate free's own connect fields (AgDR-9004 req. 1): derive them
		// from SBI_Source::get_connection_urls(true)['business'], built HERE in
		// the admin's browser session — never in the REST call — so `v`,
		// `wordpress_user` and the nonce can never drift from what the plugin's
		// own Connect button sends (AgDR-9005 §5). This also mints a FRESH
		// session nonce, same as processIFConnect() in
		// admin/builder/assets/js/add-source.js.
		$connection_urls = \InstagramFeed\Builder\SBI_Source::get_connection_urls(true);
		$business        = $connection_urls['business'];
		$fields          = array(
			'wordpress_user' => $business['wordpress_user'],
			'v'              => $business['v'],
			'vn'             => $business['vn'],
			'sbi_con'        => $business['sbi_con'],
			// Return to the SETTINGS page (its own maybe_source_connection_data()
			// pass + sources list), so the admin watches the account get added
			// where accounts are managed — not in the feed list. Same behaviour
			// as the Settings page's own Connect button (get_connection_urls(true)).
			'state'          => "{'{url=" . $connection_urls['stateURL'] . "}'}",
		);

		header('Content-Type: text/html; charset=' . get_option('blog_charset'));
		header('X-Frame-Options: SAMEORIGIN');
		header('Referrer-Policy: no-referrer');
		// phpcs:disable Generic.Files.LineLength.TooLong -- inline template/CSS.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo('charset'); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e('Connecting to Instagram…', 'instagram-feed'); ?></title>
			<style>
				body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f0f1;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
				.sbi-connect{background:#fff;border:1px solid #c3c4c7;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);max-width:440px;padding:32px;text-align:center}
				.sbi-connect h1{font-size:18px;margin:0 0 12px}
				.sbi-connect p{color:#50575e;margin:0 0 24px}
				.sbi-connect button{background:#0068A0;border:none;border-radius:4px;color:#fff;cursor:pointer;font-size:14px;padding:10px 18px}
			</style>
		</head>
		<body>
			<div class="sbi-connect">
				<h1><?php esc_html_e('Connecting to Instagram…', 'instagram-feed'); ?></h1>
				<p><?php esc_html_e('Redirecting you to log in with Facebook/Instagram. Afterwards you will return here to pick the account(s) to connect. Your AI assistant never sees your credentials.', 'instagram-feed'); ?></p>
				<form id="sbi-abilities-connect" method="post" action="<?php echo esc_url(SBI_CONNECT_URL); ?>">
					<?php foreach ($fields as $name => $value) : ?>
						<input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
					<?php endforeach; ?>
					<noscript><button type="submit"><?php esc_html_e('Continue to Instagram login', 'instagram-feed'); ?></button></noscript>
				</form>
				<script>document.getElementById('sbi-abilities-connect').submit();</script>
			</div>
		</body>
		</html>
		<?php
		// phpcs:enable Generic.Files.LineLength.TooLong
		exit;
	}
}
