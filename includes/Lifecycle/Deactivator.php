<?php
/**
 * Plugin Deactivator.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Lifecycle;

use WPCalibrate\GoldRateDisplay\Cache\CacheManager;
use WPCalibrate\GoldRateDisplay\Scheduler\RateScheduler;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles graceful plugin deactivation.
 */
final class Deactivator {

	/**
	 * Run deactivation logic.
	 */
	public static function deactivate(): void {
		// Clear scheduled cron events.
		RateScheduler::clear_schedule();

		// Clear temporary locks.
		CacheManager::clear_all_rates();
		delete_transient( RateScheduler::MANUAL_LOCK_KEY );

		// Notice: Stored user options and last known rates are preserved by default.
	}
}
