<?php
/**
 * Main Plugin Coordinator.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay;

use WPCalibrate\GoldRateDisplay\Admin\AdminMenu;
use WPCalibrate\GoldRateDisplay\Admin\Assets;
use WPCalibrate\GoldRateDisplay\Admin\SettingsPage;
use WPCalibrate\GoldRateDisplay\Display\BlockRenderer;
use WPCalibrate\GoldRateDisplay\Display\ShortcodeRenderer;
use WPCalibrate\GoldRateDisplay\Rest\RatesController;
use WPCalibrate\GoldRateDisplay\Scheduler\RateScheduler;
use WPCalibrate\GoldRateDisplay\Service\RateService;
use WPCalibrate\GoldRateDisplay\Upgrade\UpgradeManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Main Plugin singleton coordinator.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * Rate Service instance for internal and third-party extension access.
	 */
	private ?RateService $rate_service = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {}

	/**
	 * Run and bootstrap all plugin modules.
	 */
	public function run(): void {
		// 1. Internationalization.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// 2. Upgrades and schema verification.
		UpgradeManager::check_and_run_upgrades();

		// 3. Scheduler & Cron.
		$scheduler = new RateScheduler();
		$scheduler->init();

		// 4. Admin Subsystem.
		if ( is_admin() ) {
			$admin_menu = new AdminMenu();
			$admin_menu->init();

			$admin_assets = new Assets();
			$admin_assets->init();

			SettingsPage::init();
		}

		// 5. REST API Subsystem.
		$rest_controller = new RatesController();
		$rest_controller->init();

		// 6. Presentation Layer: Shortcode & Gutenberg Block.
		$shortcode_renderer = new ShortcodeRenderer();
		$shortcode_renderer->init();

		$block_renderer = new BlockRenderer();
		$block_renderer->init();
	}

	/**
	 * Load plugin localization textdomain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-gold-rate-display',
			false,
			dirname( plugin_basename( WPCALIBRATE_GRD_FILE ) ) . '/languages'
		);
	}

	/**
	 * Get centralized Rate Service.
	 * Public accessor allowing future WPCalibrate extensions (e.g. WooCommerce jewellery pricing)
	 * to consume normalized precious metal data without duplicating API clients.
	 *
	 * @return RateService
	 */
	public function get_rate_service(): RateService {
		if ( null === $this->rate_service ) {
			$this->rate_service = new RateService();
		}
		return $this->rate_service;
	}
}
