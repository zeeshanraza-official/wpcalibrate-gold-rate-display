<?php
/**
 * Weight unit converter service for precious metals.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Service;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles precise weight conversions between Troy Ounces, Grams, and Tolas.
 */
final class UnitConverter {

	/**
	 * Exact conversion constant: grams per troy ounce.
	 * 1 troy ounce = 31.1034768 grams.
	 */
	public const TROY_OUNCE_TO_GRAMS = 31.1034768;

	/**
	 * Exact conversion constant: grams per tola.
	 * 1 tola = 11.6638038 grams.
	 */
	public const TOLA_TO_GRAMS = 11.6638038;

	/**
	 * Canonical unit names.
	 */
	public const UNIT_OUNCE = 'ounce';
	public const UNIT_GRAM  = 'gram';
	public const UNIT_TOLA  = 'tola';

	/**
	 * Supported units mapping.
	 *
	 * @var array<string, array{label: string, symbol: string}>
	 */
	private const UNITS = array(
		self::UNIT_OUNCE => array(
			'label'  => 'Troy Ounce',
			'symbol' => 'oz t',
		),
		self::UNIT_GRAM  => array(
			'label'  => 'Gram',
			'symbol' => 'g',
		),
		self::UNIT_TOLA  => array(
			'label'  => 'Tola',
			'symbol' => 'tola',
		),
	);

	/**
	 * Get supported units list.
	 *
	 * @return array<string, array{label: string, symbol: string}>
	 */
	public static function get_supported_units(): array {
		return self::UNITS;
	}

	/**
	 * Check if a unit identifier is valid.
	 *
	 * @param string $unit Unit key.
	 * @return bool
	 */
	public static function is_valid_unit( string $unit ): bool {
		$key = self::normalize_unit_key( $unit );
		return isset( self::UNITS[ $key ] );
	}

	/**
	 * Normalize unit synonyms to canonical key.
	 *
	 * @param string $unit Input unit (e.g., 'oz', 'troy_ounce', 'g', 'gram').
	 * @return string Canonical unit key ('ounce', 'gram', 'tola').
	 */
	public static function normalize_unit_key( string $unit ): string {
		$lower = strtolower( trim( $unit ) );
		return match ( $lower ) {
			'oz', 'ounce', 'troy_ounce', 'troy_oz' => self::UNIT_OUNCE,
			'g', 'gram', 'grams'                   => self::UNIT_GRAM,
			'tola', 'tolas'                        => self::UNIT_TOLA,
			default                                => self::UNIT_GRAM,
		};
	}

	/**
	 * Get unit display label.
	 *
	 * @param string $unit Unit key.
	 * @return string
	 */
	public static function get_unit_label( string $unit ): string {
		$key = self::normalize_unit_key( $unit );
		return self::UNITS[ $key ]['label'] ?? 'Gram';
	}

	/**
	 * Get unit display symbol/abbreviation.
	 *
	 * @param string $unit Unit key.
	 * @return string
	 */
	public static function get_unit_symbol( string $unit ): string {
		$key = self::normalize_unit_key( $unit );
		return self::UNITS[ $key ]['symbol'] ?? 'g';
	}

	/**
	 * Convert price per troy ounce to target unit price.
	 * Internal calculations preserve full floating point precision.
	 *
	 * @param float  $price_per_ounce Spot price per troy ounce.
	 * @param string $target_unit     Target unit ('ounce', 'gram', 'tola').
	 * @return float Price in target unit.
	 */
	public static function convert_from_ounce( float $price_per_ounce, string $target_unit ): float {
		$key = self::normalize_unit_key( $target_unit );

		return match ( $key ) {
			self::UNIT_OUNCE => $price_per_ounce,
			self::UNIT_GRAM  => $price_per_ounce / self::TROY_OUNCE_TO_GRAMS,
			self::UNIT_TOLA  => ( $price_per_ounce / self::TROY_OUNCE_TO_GRAMS ) * self::TOLA_TO_GRAMS,
			default          => $price_per_ounce / self::TROY_OUNCE_TO_GRAMS,
		};
	}

	/**
	 * Convert price from an arbitrary source unit to target unit.
	 *
	 * @param float  $price       Price amount.
	 * @param string $source_unit Source unit.
	 * @param string $target_unit Target unit.
	 * @return float
	 */
	public static function convert( float $price, string $source_unit, string $target_unit ): float {
		$src = self::normalize_unit_key( $source_unit );
		$dst = self::normalize_unit_key( $target_unit );

		if ( $src === $dst ) {
			return $price;
		}

		// First normalize source price to price per troy ounce.
		$price_per_ounce = match ( $src ) {
			self::UNIT_OUNCE => $price,
			self::UNIT_GRAM  => $price * self::TROY_OUNCE_TO_GRAMS,
			self::UNIT_TOLA  => ( $price / self::TOLA_TO_GRAMS ) * self::TROY_OUNCE_TO_GRAMS,
			default          => $price * self::TROY_OUNCE_TO_GRAMS,
		};

		return self::convert_from_ounce( $price_per_ounce, $dst );
	}
}
