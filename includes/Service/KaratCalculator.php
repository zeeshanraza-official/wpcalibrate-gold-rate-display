<?php
/**
 * Karat purity calculations for Gold.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Service;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Isolated karat purity calculation service.
 */
final class KaratCalculator {

	/**
	 * Exact karat purity ratios (relative to pure 24K gold).
	 *
	 * 24K = 24 / 24 = 1.00000000
	 * 22K = 22 / 24 = 0.91666667
	 * 21K = 21 / 24 = 0.87500000
	 * 18K = 18 / 24 = 0.75000000
	 */
	public const PURITY_RATIOS = array(
		'24K' => 24.0 / 24.0,
		'22K' => 22.0 / 24.0,
		'21K' => 21.0 / 24.0,
		'18K' => 18.0 / 24.0,
	);

	/**
	 * Karat fineness representations (parts per thousand).
	 */
	public const PURITY_FINENESS = array(
		'24K' => 999,
		'22K' => 916,
		'21K' => 875,
		'18K' => 750,
	);

	/**
	 * Supported karats list.
	 *
	 * @return array<string>
	 */
	public static function get_supported_karats(): array {
		return array_keys( self::PURITY_RATIOS );
	}

	/**
	 * Get purity ratio for a karat.
	 *
	 * @param string $karat Karat string (e.g. '24K', '22k', '21').
	 * @return float Purity ratio between 0.0 and 1.0.
	 */
	public static function get_purity_ratio( string $karat ): float {
		$normalized = self::normalize_karat_name( $karat );
		return self::PURITY_RATIOS[ $normalized ] ?? 1.0;
	}

	/**
	 * Normalize karat label string.
	 *
	 * @param string $karat Input karat.
	 * @return string Normalized karat (e.g. '24K').
	 */
	public static function normalize_karat_name( string $karat ): string {
		$upper = strtoupper( trim( $karat ) );
		if ( ! str_ends_with( $upper, 'K' ) && is_numeric( $upper ) ) {
			$upper .= 'K';
		}
		return isset( self::PURITY_RATIOS[ $upper ] ) ? $upper : '24K';
	}

	/**
	 * Calculate karat price from base 24K price.
	 * Calculations keep full precision without premature rounding.
	 *
	 * @param float  $price_24k Pure 24K price in requested unit and currency.
	 * @param string $karat     Target karat ('24K', '22K', '21K', '18K').
	 * @return float Calculated karat price.
	 */
	public static function calculate_price( float $price_24k, string $karat ): float {
		$ratio = self::get_purity_ratio( $karat );
		return $price_24k * $ratio;
	}

	/**
	 * Calculate all standard karat prices from 24K price.
	 *
	 * @param float $price_24k Pure 24K price.
	 * @return array<string, float> Map of karat => price.
	 */
	public static function calculate_all( float $price_24k ): array {
		$prices = array();
		foreach ( self::PURITY_RATIOS as $karat => $ratio ) {
			$prices[ $karat ] = $price_24k * $ratio;
		}
		return $prices;
	}
}
