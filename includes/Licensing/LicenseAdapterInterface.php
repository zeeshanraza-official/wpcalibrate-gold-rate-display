<?php
/**
 * License Adapter Interface.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Licensing;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Contract for WPCalibrate commercial license validation.
 */
interface LicenseAdapterInterface {

	/**
	 * Get current license status.
	 *
	 * @return string ('unconfigured', 'active', 'expired', 'invalid').
	 */
	public function get_status(): string;

	/**
	 * Activate license key.
	 *
	 * @param string $license_key License key.
	 * @return array{success: bool, message: string}
	 */
	public function activate( string $license_key ): array;

	/**
	 * Deactivate current license key.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array;

	/**
	 * Get masked license key.
	 *
	 * @return string
	 */
	public function get_masked_license_key(): string;

	/**
	 * Get license expiry string.
	 *
	 * @return string
	 */
	public function get_expiry_date(): string;
}
