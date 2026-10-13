<?php
/**
 * Plugin Activator.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Lifecycle;

use WPCalibrate\GoldRateDisplay\Scheduler\RateScheduler;
use WPCalibrate\GoldRateDisplay\Service\SettingsService;
use WPCalibrate\GoldRateDisplay\Upgrade\UpgradeManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Executes plugin activation tasks safely.
 */
final class Activator {

	/**
	 * Run activation logic.
	 */
	public static function activate(): void {
		// Verify minimum PHP requirement.
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			deactivate_plugins( plugin_basename( WPCALIBRATE_GRD_FILE ) );
			wp_die(
				esc_html__( 'WPCalibrate Gold Rate Display requires PHP 8.2 or greater.', 'wpcalibrate-gold-rate-display' )
			);
		}

		// Initialize default settings if not yet existing.
		if ( false === get_option( SettingsService::OPTION_KEY ) ) {
			update_option( SettingsService::OPTION_KEY, SettingsService::get_defaults() );
		}

		// Set version flags.
		UpgradeManager::check_and_run_upgrades();

		// Register cron event.
		$settings = SettingsService::get_settings();
		$interval = (int) ( $settings['refresh_interval'] ?? 15 );
		RateScheduler::reschedule( $interval );
	}
}
