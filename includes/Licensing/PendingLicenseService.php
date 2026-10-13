<?php
/**
 * Pending License Service.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Licensing;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Clean placeholder license service.
 * Explicitly displays integration as not configured without simulating false activation.
 */
final class PendingLicenseService implements LicenseAdapterInterface {

	public const OPTION_KEY = 'wpcalibrate_grd_license';

	/**
	 * Get stored license record.
	 *
	 * @return array{key: string, status: string, expiry: string}
	 */
	public static function get_stored_license(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return array(
			'key'    => (string) ( $saved['key'] ?? '' ),
			'status' => (string) ( $saved['status'] ?? 'unconfigured' ),
			'expiry' => (string) ( $saved['expiry'] ?? '' ),
		);
	}

	public function get_status(): string {
		return 'unconfigured';
	}

	public function activate( string $license_key ): array {
		// Strictly avoid simulating network validation.
		return array(
			'success' => false,
			'message' => __( 'WPCalibrate license activation backend is not configured in this release. The core gold rate display features function without requiring license activation.', 'wpcalibrate-gold-rate-display' ),
		);
	}

	public function deactivate(): array {
		delete_option( self::OPTION_KEY );
		return array(
			'success' => true,
			'message' => __( 'License record cleared.', 'wpcalibrate-gold-rate-display' ),
		);
	}

	public function get_masked_license_key(): string {
		$data = self::get_stored_license();
		$raw  = trim( $data['key'] );
		$len  = strlen( $raw );

		if ( 0 === $len ) {
			return '';
		}

		if ( $len <= 6 ) {
			return str_repeat( '•', $len );
		}

		return substr( $raw, 0, 4 ) . str_repeat( '•', max( 4, $len - 8 ) ) . substr( $raw, -4 );
	}

	public function get_expiry_date(): string {
		return __( 'N/A', 'wpcalibrate-gold-rate-display' );
	}
}
