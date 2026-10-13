<?php
/**
 * Rate Provider Interface.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Api;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Interface contract for precious metal rate providers.
 * Allows switching or extending to alternative providers without modifying display services.
 */
interface RateProviderInterface {

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Fetch latest rate for a given currency.
	 *
	 * @param string $currency ISO currency code (e.g. 'USD', 'EUR').
	 * @return ProviderResponse
	 */
	public function get_latest_rate( string $currency ): ProviderResponse;

	/**
	 * Test provider connection and credentials.
	 *
	 * @param string $api_key API key to test.
	 * @return ProviderResponse
	 */
	public function test_connection( string $api_key ): ProviderResponse;
}
