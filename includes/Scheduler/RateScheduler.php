<?php
/**
 * WP-Cron Refresh Scheduler.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Scheduler;

use WPCalibrate\GoldRateDisplay\Service\RateService;
use WPCalibrate\GoldRateDisplay\Service\SettingsService;
use WPCalibrate\GoldRateDisplay\Service\CredentialService;
use WPCalibrate\GoldRateDisplay\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Manages WP-Cron scheduling and manual admin refresh triggers.
 */
final class RateScheduler {

	/**
	 * WP-Cron event hook.
	 */
	public const CRON_HOOK = 'wpcalibrate_grd_cron_refresh';

	/**
	 * Custom recurrence schedule identifier.
	 */
	public const SCHEDULE_KEY = 'wpcalibrate_grd_recurrence';

	/**
	 * Manual refresh anti-spam lock transient.
	 */
	public const MANUAL_LOCK_KEY = 'wpcalibrate_grd_manual_refresh_lock';

	/**
	 * Initialize cron hooks.
	 */
	public function init(): void {
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		add_action( self::CRON_HOOK, array( $this, 'handle_cron_event' ) );
	}

	/**
	 * Register dynamic cron interval according to plugin settings.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Existing schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function add_cron_intervals( array $schedules ): array {
		$settings = SettingsService::get_settings();
		$minutes  = (int) ( $settings['refresh_interval'] ?? 15 );
		$seconds  = max( 60, $minutes * MINUTE_IN_SECONDS );

		$schedules[ self::SCHEDULE_KEY ] = array(
			'interval' => $seconds,
			'display'  => sprintf(
				/* translators: %d: interval in minutes */
				__( 'Every %d Minutes (WPCalibrate Gold Rate)', 'wpcalibrate-gold-rate-display' ),
				$minutes
			),
		);

		return $schedules;
	}

	/**
	 * Re-register scheduled cron event, preventing duplicate schedules.
	 *
	 * @param int $minutes Refresh interval in minutes.
	 * @return void
	 */
	public static function reschedule( int $minutes ): void {
		self::clear_schedule();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( $minutes * MINUTE_IN_SECONDS ), self::SCHEDULE_KEY, self::CRON_HOOK );
		}
	}

	/**
	 * Clear all instances of our scheduled cron event.
	 *
	 * @return void
	 */
	public static function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Cron execution callback.
	 *
	 * @return void
	 */
	public function handle_cron_event(): void {
		if ( ! CredentialService::has_api_key() ) {
			return;
		}

		$settings = SettingsService::get_settings();
		$currency = $settings['currency'] ?? 'USD';

		Logger::log( 'info', 'WP-Cron refresh triggered', array( 'currency' => $currency ) );

		$rate_service = new RateService();
		$rate_service->refresh_rate( $currency );
	}

	/**
	 * Execute a manual refresh initiated by administrator with anti-spam check.
	 *
	 * @param string $currency Currency to refresh.
	 * @return array{success: bool, message: string}
	 */
	public static function execute_manual_refresh( string $currency ): array {
		if ( false !== get_transient( self::MANUAL_LOCK_KEY ) ) {
			return array(
				'success' => false,
				'message' => __( 'A refresh was requested very recently. Please wait a few seconds before refreshing again.', 'wpcalibrate-gold-rate-display' ),
			);
		}

		if ( ! CredentialService::has_api_key() ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot refresh: API key is not configured. Please save your API key first.', 'wpcalibrate-gold-rate-display' ),
			);
		}

		// Set anti-spam lock for 5 seconds.
		set_transient( self::MANUAL_LOCK_KEY, 1, 5 );

		$rate_service = new RateService();
		$result       = $rate_service->refresh_rate( $currency );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Refresh failed: %s', 'wpcalibrate-gold-rate-display' ),
					$result->get_error_message()
				),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Gold rates refreshed successfully from MetalpriceAPI.', 'wpcalibrate-gold-rate-display' ),
		);
	}
}
