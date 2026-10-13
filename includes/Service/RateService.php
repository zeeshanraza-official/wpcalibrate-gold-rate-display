<?php
/**
 * Central Rate Service.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Service;

use WPCalibrate\GoldRateDisplay\Api\MetalpriceApiProvider;
use WPCalibrate\GoldRateDisplay\Api\RateProviderInterface;
use WPCalibrate\GoldRateDisplay\Cache\CacheManager;
use WPCalibrate\GoldRateDisplay\Model\GoldRateData;
use WPCalibrate\GoldRateDisplay\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Coordinates provider requests, caching, stampede locks, and fallback handling.
 */
final class RateService {

	/**
	 * Rate provider instance.
	 */
	private RateProviderInterface $provider;

	/**
	 * Constructor.
	 *
	 * @param RateProviderInterface|null $provider Optional provider adapter.
	 */
	public function __construct( ?RateProviderInterface $provider = null ) {
		$this->provider = $provider ?? new MetalpriceApiProvider();
	}

	/**
	 * Get the active rate provider.
	 *
	 * @return RateProviderInterface
	 */
	public function get_provider(): RateProviderInterface {
		return $this->provider;
	}

	/**
	 * Get gold rate data for a currency, serving from cache when available.
	 *
	 * @param string $currency ISO currency code.
	 * @param bool   $force    Force refresh bypassing cache.
	 * @return GoldRateData|\WP_Error
	 */
	public function get_rate( string $currency, bool $force = false ): GoldRateData|\WP_Error {
		$code = CurrencyService::sanitize_currency( $currency );

		// 1. Fast path: return fresh cached rate if not forcing.
		if ( ! $force ) {
			$cached = CacheManager::get_cached_rate( $code );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		// 2. Refresh rate from provider with stampede prevention.
		return $this->refresh_rate( $code, $force );
	}

	/**
	 * Fetch fresh rate from provider with concurrency lock and safe fallback.
	 *
	 * @param string $currency ISO currency code.
	 * @param bool   $force    Whether to bypass double-check cache.
	 * @return GoldRateData|\WP_Error
	 */
	public function refresh_rate( string $currency, bool $force = false ): GoldRateData|\WP_Error {
		$code     = CurrencyService::sanitize_currency( $currency );
		$settings = SettingsService::get_settings();
		$ttl      = (int) ( $settings['refresh_interval'] ?? 15 ) * MINUTE_IN_SECONDS;

		// Acquire anti-stampede lock.
		$lock_acquired = CacheManager::acquire_lock( $code, 20 );

		if ( ! $lock_acquired ) {
			// Another process is refreshing right now.
			// Recheck cache after tiny delay or return last known good.
			$cached = CacheManager::get_cached_rate( $code );
			if ( null !== $cached ) {
				return $cached;
			}

			$fallback = CacheManager::get_last_known_good( $code );
			if ( null !== $fallback ) {
				return $fallback;
			}
		}

		try {
			// Double check cache in case another process populated it just before lock.
			if ( ! $force ) {
				$recheck = CacheManager::get_cached_rate( $code );
				if ( null !== $recheck ) {
					return $recheck;
				}
			}

			// Call external provider.
			$response = $this->provider->get_latest_rate( $code );

			if ( ! $response->is_success() ) {
				Logger::log(
					'warning',
					'Rate fetch failed from provider',
					array(
						'currency' => $code,
						'code'     => $response->get_error_code(),
						'error'    => $response->get_error_message(),
					)
				);

				// Fallback to last known good if enabled.
				if ( ! empty( $settings['show_stale_notice'] ) || true ) {
					$fallback = CacheManager::get_last_known_good( $code );
					if ( null !== $fallback ) {
						// Store fallback in cache with shorter grace period (60s) to prevent spamming provider.
						CacheManager::set_cached_rate( $code, $fallback, 60 );
						return $fallback;
					}
				}

				return new \WP_Error( $response->get_error_code(), $response->get_error_message() );
			}

			$spot_price = (float) $response->get_spot_price_ounce();

			// Calculate price change and percent change if last known price exists.
			$price_change   = null;
			$percent_change = null;
			$last_good      = CacheManager::get_last_known_good( $code );

			if ( null !== $last_good ) {
				$old_spot = $last_good->get_spot_price_ounce();
				if ( $old_spot > 0.0 ) {
					$price_change   = $spot_price - $old_spot;
					$percent_change = ( $price_change / $old_spot ) * 100.0;
				}
			}

			$rate_data = new GoldRateData(
				$spot_price,
				$code,
				$response->get_timestamp(),
				time(),
				GoldRateData::STATUS_FRESH,
				$price_change,
				$percent_change
			);

			// Cache normalized rate data.
			CacheManager::set_cached_rate( $code, $rate_data, $ttl );

			Logger::log(
				'info',
				'Successfully refreshed gold rates',
				array(
					'currency'   => $code,
					'spot_price' => $spot_price,
				)
			);

			return $rate_data;

		} finally {
			CacheManager::release_lock( $code );
		}
	}
}
