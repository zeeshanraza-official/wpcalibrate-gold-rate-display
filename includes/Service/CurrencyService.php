<?php
/**
 * Currency service and validation for WPCalibrate Gold Rate Display.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Service;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Validates and provides metadata for supported ISO currency codes.
 */
final class CurrencyService {

	/**
	 * Curated currency metadata allowlist.
	 *
	 * @var array<string, array{name: string, symbol: string, position: string}>
	 */
	private const CURRENCIES = array(
		'USD' => array(
			'name'     => 'US Dollar',
			'symbol'   => '$',
			'position' => 'before',
		),
		'GBP' => array(
			'name'     => 'British Pound',
			'symbol'   => '£',
			'position' => 'before',
		),
		'EUR' => array(
			'name'     => 'Euro',
			'symbol'   => '€',
			'position' => 'before',
		),
		'PKR' => array(
			'name'     => 'Pakistani Rupee',
			'symbol'   => '₨',
			'position' => 'before',
		),
		'INR' => array(
			'name'     => 'Indian Rupee',
			'symbol'   => '₹',
			'position' => 'before',
		),
		'AED' => array(
			'name'     => 'UAE Dirham',
			'symbol'   => 'AED',
			'position' => 'after',
		),
		'SAR' => array(
			'name'     => 'Saudi Riyal',
			'symbol'   => 'SAR',
			'position' => 'after',
		),
		'QAR' => array(
			'name'     => 'Qatari Riyal',
			'symbol'   => 'QAR',
			'position' => 'after',
		),
		'KWD' => array(
			'name'     => 'Kuwaiti Dinar',
			'symbol'   => 'KWD',
			'position' => 'after',
		),
		'CAD' => array(
			'name'     => 'Canadian Dollar',
			'symbol'   => 'CA$',
			'position' => 'before',
		),
		'AUD' => array(
			'name'     => 'Australian Dollar',
			'symbol'   => 'AU$',
			'position' => 'before',
		),
		'SGD' => array(
			'name'     => 'Singapore Dollar',
			'symbol'   => 'SG$',
			'position' => 'before',
		),
	);

	/**
	 * Get all supported currency codes and metadata.
	 *
	 * @return array<string, array{name: string, symbol: string, position: string}>
	 */
	public static function get_supported_currencies(): array {
		return self::CURRENCIES;
	}

	/**
	 * Check if a currency code is in the supported allowlist.
	 *
	 * @param string $currency ISO-4217 Currency code.
	 * @return bool
	 */
	public static function is_supported( string $currency ): bool {
		$code = strtoupper( trim( $currency ) );
		return isset( self::CURRENCIES[ $code ] );
	}

	/**
	 * Sanitize and validate currency code, defaulting to USD if invalid.
	 *
	 * @param string $currency Given currency.
	 * @param string $default  Fallback currency code.
	 * @return string Validated 3-letter currency code.
	 */
	public static function sanitize_currency( string $currency, string $default = 'USD' ): string {
		$code = strtoupper( trim( $currency ) );
		return self::is_supported( $code ) ? $code : $default;
	}

	/**
	 * Get symbol for a currency.
	 *
	 * @param string $currency ISO Currency code.
	 * @return string Symbol or code if not found.
	 */
	public static function get_symbol( string $currency ): string {
		$code = strtoupper( trim( $currency ) );
		return self::CURRENCIES[ $code ]['symbol'] ?? $code;
	}

	/**
	 * Format a price with its currency symbol and locale-aware separators.
	 *
	 * @param float  $amount   Numeric amount.
	 * @param string $currency ISO Currency code.
	 * @param int    $decimals Decimal places (default 2).
	 * @return string Formatted price string.
	 */
	public static function format_price( float $amount, string $currency, int $decimals = 2 ): string {
		$code     = self::sanitize_currency( $currency );
		$meta     = self::CURRENCIES[ $code ] ?? array( 'symbol' => $code, 'position' => 'before' );
		$symbol   = $meta['symbol'];
		$position = $meta['position'];

		// Locale-aware formatting.
		$thousands_sep = ',';
		$decimal_sep   = '.';

		if ( function_exists( 'localeconv' ) ) {
			$locale_info = localeconv();
			if ( ! empty( $locale_info['decimal_point'] ) ) {
				$decimal_sep = $locale_info['decimal_point'];
			}
			if ( ! empty( $locale_info['thousands_sep'] ) ) {
				$thousands_sep = $locale_info['thousands_sep'];
			}
		}

		$formatted_number = number_format( $amount, $decimals, $decimal_sep, $thousands_sep );

		if ( 'after' === $position ) {
			return $formatted_number . ' ' . $symbol;
		}

		return $symbol . $formatted_number;
	}
}
