<?php
/**
 * Upgrade and Migration Manager.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Upgrade;

use WPCalibrate\GoldRateDisplay\Scheduler\RateScheduler;
use WPCalibrate\GoldRateDisplay\Service\SettingsService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles version migrations and upgrade procedures.
 */
final class UpgradeManager {

	public const OPTION_VERSION    = 'wpcalibrate_grd_version';
	public const OPTION_DB_VERSION = 'wpcalibrate_grd_db_version';

	/**
	 * Run upgrade checks.
	 */
	public static function check_and_run_upgrades(): void {
		$installed_version = get_option( self::OPTION_VERSION, '' );

		if ( WPCALIBRATE_GRD_VERSION === $installed_version ) {
			return;
		}

		if ( empty( $installed_version ) ) {
			// First installation.
			self::install();
		} else {
			// Upgrades across versions.
			self::upgrade( $installed_version );
		}

		update_option( self::OPTION_VERSION, WPCALIBRATE_GRD_VERSION );
		update_option( self::OPTION_DB_VERSION, '1.0.0' );
	}

	/**
	 * Fresh installation routine.
	 */
	private static function install(): void {
		if ( false === get_option( SettingsService::OPTION_KEY ) ) {
			update_option( SettingsService::OPTION_KEY, SettingsService::get_defaults() );
		}

		$settings = SettingsService::get_settings();
		$interval = (int) ( $settings['refresh_interval'] ?? 15 );
		RateScheduler::reschedule( $interval );
	}

	/**
	 * Versioned upgrade migration logic.
	 *
	 * @param string $from_version Previous installed version.
	 */
	private static function upgrade( string $from_version ): void {
		// Future version migrations will be sequenced here:
		// if ( version_compare( $from_version, '1.1.0', '<' ) ) { ... }
	}
}
