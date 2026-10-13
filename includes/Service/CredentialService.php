<?php
/**
 * Credential Service for API keys and quota management.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Service;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles API credential storage, masking, and status metadata.
 */
final class CredentialService {

	/**
	 * Option key for API credentials.
	 */
	public const OPTION_KEY = 'wpcalibrate_grd_api_credentials';

	/**
	 * Status constants.
	 */
	public const STATUS_UNTESTED       = 'untested';
	public const STATUS_VALID          = 'valid';
	public const STATUS_INVALID        = 'invalid';
	public const STATUS_QUOTA_EXCEEDED = 'quota_exceeded';
	public const STATUS_ERROR          = 'error';

	/**
	 * Get stored credential record.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_credentials(): array {
		$defaults = array(
			'api_key'            => '',
			'provider'           => 'metalpriceapi',
			'status'             => self::STATUS_UNTESTED,
			'last_tested'        => 0,
			'last_error'         => '',
			'quota_used'         => null,
			'quota_total'        => null,
			'quota_remaining'    => null,
			'plan_name'          => '',
		);

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * Retrieve raw API key for server-side outbound HTTP requests ONLY.
	 * Never output this method's return value to HTML, REST, or client-side scripts.
	 *
	 * @return string Raw API key.
	 */
	public static function get_api_key(): string {
		$creds = self::get_credentials();
		return trim( (string) ( $creds['api_key'] ?? '' ) );
	}

	/**
	 * Check if an API key is configured.
	 *
	 * @return bool
	 */
	public static function has_api_key(): bool {
		return '' !== self::get_api_key();
	}

	/**
	 * Mask the API key for safe admin UI display.
	 * Returns e.g. "mp_a••••••••••••1234" or empty string if no key.
	 *
	 * @param string|null $key Key to mask. If null, stored key is used.
	 * @return string Masked key or empty string.
	 */
	public static function get_masked_key( ?string $key = null ): string {
		$raw = ( null === $key ) ? self::get_api_key() : trim( $key );
		$len = strlen( $raw );

		if ( 0 === $len ) {
			return '';
		}

		if ( $len <= 6 ) {
			return str_repeat( '•', $len );
		}

		$start = substr( $raw, 0, 4 );
		$end   = substr( $raw, -4 );
		return $start . str_repeat( '•', max( 4, $len - 8 ) ) . $end;
	}

	/**
	 * Save or replace the API key.
	 * If input is empty or matches the masked representation, the existing key is preserved.
	 *
	 * @param string $new_key Raw key input from form.
	 * @return bool
	 */
	public static function save_api_key( string $new_key ): bool {
		$trimmed   = trim( $new_key );
		$existing  = self::get_credentials();
		$old_key   = $existing['api_key'] ?? '';
		$masked    = self::get_masked_key( $old_key );

		// If user didn't enter a new key or submitted the masked placeholder, do not overwrite.
		if ( '' === $trimmed || $trimmed === $masked ) {
			return false;
		}

		$existing['api_key']     = $trimmed;
		$existing['status']      = self::STATUS_UNTESTED;
		$existing['last_tested'] = 0;
		$existing['last_error']  = '';

		return update_option( self::OPTION_KEY, $existing );
	}

	/**
	 * Remove stored API key completely.
	 *
	 * @return bool
	 */
	public static function clear_api_key(): bool {
		$creds = self::get_credentials();
		$creds['api_key']         = '';
		$creds['status']          = self::STATUS_UNTESTED;
		$creds['last_tested']     = 0;
		$creds['last_error']      = '';
		$creds['quota_used']      = null;
		$creds['quota_total']     = null;
		$creds['quota_remaining'] = null;

		return update_option( self::OPTION_KEY, $creds );
	}

	/**
	 * Update connection status and quota metrics.
	 *
	 * @param string      $status          Status constant.
	 * @param string      $error_message   Error message if failed.
	 * @param int|null    $quota_used      Quota used count.
	 * @param int|null    $quota_total     Quota limit.
	 * @param string|null $plan_name       Plan name if available.
	 * @return bool
	 */
	public static function update_status(
		string $status,
		string $error_message = '',
		?int $quota_used = null,
		?int $quota_total = null,
		?string $plan_name = null
	): bool {
		$creds                = self::get_credentials();
		$creds['status']      = $status;
		$creds['last_tested'] = time();
		$creds['last_error']  = $error_message;

		if ( null !== $quota_used ) {
			$creds['quota_used'] = $quota_used;
		}
		if ( null !== $quota_total ) {
			$creds['quota_total'] = $quota_total;
		}
		if ( null !== $quota_used && null !== $quota_total ) {
			$creds['quota_remaining'] = max( 0, $quota_total - $quota_used );
		}
		if ( null !== $plan_name ) {
			$creds['plan_name'] = $plan_name;
		}

		return update_option( self::OPTION_KEY, $creds );
	}
}
