<?php
/**
 * Cache Manager and Anti-Stampede locking.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Cache;

use WPCalibrate\GoldRateDisplay\Model\GoldRateData;
use WPCalibrate\GoldRateDisplay\Service\CurrencyService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Manages transient caching, stampede locks, and last-known-good persistence.
 */
final class CacheManager {

	/**
	 * Transient prefix for cached rate datasets.
	 */
	public const TRANSIENT_PREFIX = 'wpcalibrate_grd_rate_';

	/**
	 * Transient prefix for stampede refresh locks.
	 */
	public const LOCK_PREFIX = 'wpcalibrate_grd_lock_';

	/**
	 * Option key for permanent last-known-good data storage.
	 */
	public const OPTION_LAST_GOOD_RATES = 'wpcalibrate_grd_last_rates';

	/**
	 * Default lock timeout in seconds.
	 */
	public const DEFAULT_LOCK_TTL = 30;

	/**
	 * Retrieve cached rate data for a currency.
	 *
	 * @param string $currency ISO currency code.
	 * @return GoldRateData|null Cached rate or null if expired/missing.
	 */
	public static function get_cached_rate( string $currency ): ?GoldRateData {
		$code      = strtolower( CurrencyService::sanitize_currency( $currency ) );
		$transient = get_transient( self::TRANSIENT_PREFIX . $code );

		if ( is_array( $transient ) && ! empty( $transient['spot_price_ounce'] ) ) {
			return GoldRateData::from_array( $transient );
		}

		return null;
	}

	/**
	 * Store normalized rate data in transient cache and update permanent fallback storage.
	 *
	 * @param string       $currency    ISO currency code.
	 * @param GoldRateData $rate_data   Normalized rate instance.
	 * @param int          $ttl_seconds Cache expiration in seconds.
	 * @return bool
	 */
	public static function set_cached_rate( string $currency, GoldRateData $rate_data, int $ttl_seconds ): bool {
		$code = strtolower( CurrencyService::sanitize_currency( $currency ) );
		$data = $rate_data->to_array();

		// Update transient cache.
		$saved = set_transient( self::TRANSIENT_PREFIX . $code, $data, max( 60, $ttl_seconds ) );

		// Update persistent last-known-good rate if not fallback.
		if ( GoldRateData::STATUS_FALLBACK !== $rate_data->get_status() ) {
			self::save_last_known_good( $currency, $rate_data );
		}

		return $saved;
	}

	/**
	 * Delete cached rate transient for a currency.
	 *
	 * @param string $currency ISO currency code.
	 * @return bool
	 */
	public static function delete_cached_rate( string $currency ): bool {
		$code = strtolower( CurrencyService::sanitize_currency( $currency ) );
		return delete_transient( self::TRANSIENT_PREFIX . $code );
	}

	/**
	 * Acquire an exclusive anti-stampede refresh lock.
	 *
	 * @param string $currency         ISO currency code.
	 * @param int    $lock_ttl_seconds Lock lifetime in seconds.
	 * @return bool True if lock was successfully acquired, false if another process holds it.
	 */
	public static function acquire_lock( string $currency, int $lock_ttl_seconds = self::DEFAULT_LOCK_TTL ): bool {
		$code     = strtolower( CurrencyService::sanitize_currency( $currency ) );
		$lock_key = self::LOCK_PREFIX . $code;

		// Check if lock already exists.
		if ( false !== get_transient( $lock_key ) ) {
			return false;
		}

		// Set temporary lock.
		return set_transient( $lock_key, time(), $lock_ttl_seconds );
	}

	/**
	 * Release the refresh lock.
	 *
	 * @param string $currency ISO currency code.
	 * @return bool
	 */
	public static function release_lock( string $currency ): bool {
		$code     = strtolower( CurrencyService::sanitize_currency( $currency ) );
		$lock_key = self::LOCK_PREFIX . $code;
		return delete_transient( $lock_key );
	}

	/**
	 * Store last known good rate persistently in options.
	 *
	 * @param string       $currency  ISO currency code.
	 * @param GoldRateData $rate_data Valid rate data.
	 * @return bool
	 */
	public static function save_last_known_good( string $currency, GoldRateData $rate_data ): bool {
		$upper_code = strtoupper( CurrencyService::sanitize_currency( $currency ) );
		$all_rates  = get_option( self::OPTION_LAST_GOOD_RATES, array() );

		if ( ! is_array( $all_rates ) ) {
			$all_rates = array();
		}

		$all_rates[ $upper_code ] = $rate_data->to_array();
		return update_option( self::OPTION_LAST_GOOD_RATES, $all_rates );
	}

	/**
	 * Retrieve last known good rate from persistent options.
	 *
	 * @param string $currency ISO currency code.
	 * @return GoldRateData|null
	 */
	public static function get_last_known_good( string $currency ): ?GoldRateData {
		$upper_code = strtoupper( CurrencyService::sanitize_currency( $currency ) );
		$all_rates  = get_option( self::OPTION_LAST_GOOD_RATES, array() );

		if ( is_array( $all_rates ) && isset( $all_rates[ $upper_code ] ) && is_array( $all_rates[ $upper_code ] ) ) {
			$raw = $all_rates[ $upper_code ];
			if ( ! empty( $raw['spot_price_ounce'] ) ) {
				$data = GoldRateData::from_array( $raw );
				return $data->with_status( GoldRateData::STATUS_FALLBACK );
			}
		}

		return null;
	}

	/**
	 * Clear all cached rates and locks across all supported currencies.
	 *
	 * @return void
	 */
	public static function clear_all_rates(): void {
		$currencies = array_keys( CurrencyService::get_supported_currencies() );
		foreach ( $currencies as $currency ) {
			self::delete_cached_rate( $currency );
			self::release_lock( $currency );
		}
	}
}
