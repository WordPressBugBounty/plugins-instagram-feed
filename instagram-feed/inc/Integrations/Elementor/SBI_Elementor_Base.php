<?php

namespace InstagramFeed\Integrations\Elementor;

use InstagramFeed\Vendor\Smashballoon\Framework\Packages\Blocks\RecommendedElementorWidgets;
use InstagramFeed\Vendor\Smashballoon\Framework\Packages\Blocks\SB_Feed_Blocks_Registry;
use InstagramFeed\Vendor\Smashballoon\Framework\Packages\Blocks\SB_Block_Utils;
use InstagramFeed\Vendor\Smashballoon\Framework\Packages\Blocks\SB_Elementor_Editor_Assets;
use InstagramFeed\Builder\SBI_Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SBI_Elementor_Base {

	private static $instance = null;

	public static function register() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Backward-compat alias for register(). Kept because instagram-feed.php
	 * still calls SBI_Elementor_Base::instance() on plugin bootstrap.
	 */
	public static function instance() {
		return self::register();
	}

	private function init() {
		if ( doing_action( 'init' ) || did_action( 'init' ) ) {
			$this->init_elementor_integration();
		} else {
			add_action( 'init', array( $this, 'init_elementor_integration' ), 4 );
		}
	}

	public function init_elementor_integration() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$recommended = new RecommendedElementorWidgets( 'instagram' );
		$recommended->setup();

		$registry = SB_Feed_Blocks_Registry::instance();
		$registry->register_elementor_widget( array(
			'blockId'    => 'instagram',
			'widgetName' => 'sb-instagram-feed',
			'globalVar'  => 'sbiElementorData',
			'feedInitFn' => 'sbi_init',
		) );

		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_smashballoon_categories' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
	}

	public function register_widgets( $widgets_manager ) {
		$widgets_manager->register( new SBI_Modern_Elementor_Widget() );
		$widgets_manager->register( new SBI_Elementor_Widget() );
	}

	public function register_frontend_scripts() {
		sb_instagram_scripts_enqueue();

		$feeds = SBI_Db::elementor_feeds_list();

		$data = array(
			'feeds'         => ! empty( $feeds ) ? $feeds : array(),
			'feed_url'      => admin_url( 'admin.php?page=sbi-feed-builder' ),
			'is_pro_active' => sbi_is_pro_version(),
		);

		wp_localize_script( 'sbi_scripts', 'sbiElementorData', $data );

		SB_Feed_Blocks_Registry::instance()->enqueue_elementor_assets();
	}

	public function add_smashballoon_categories( $elements_manager ) {
		$elements_manager->add_category(
			SB_Block_Utils::CATEGORY_SLUG,
			array(
				'title' => esc_html__( 'Smash Balloon', 'instagram-feed' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	public function enqueue_editor_scripts() {
		SB_Elementor_Editor_Assets::enqueue_shared_elementor_styles( SBIVER );
	}
}
