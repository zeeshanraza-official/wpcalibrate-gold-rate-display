<?php
/**
 * Normalized Gold Rate Data Model.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Model;

use WPCalibrate\GoldRateDisplay\Service\KaratCalculator;
use WPCalibrate\GoldRateDisplay\Service\UnitConverter;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Normalized Gold Rate Data representation.
 * Immutable data structure holding spot prices, karat calculations, and metadata.
 */
final class GoldRateData {

	/**
	 * Status constants.
	 */
	public const STATUS_FRESH    = 'fresh';
	public const STATUS_STALE    = 'stale';
	public const STATUS_FALLBACK = 'fallback';

	/**
	 * Spot price per Troy Ounce in the given currency.
	 */
	private float $spot_price_ounce;

	/**
	 * ISO-4217 Currency code.
	 */
	private string $currency;

	/**
	 * Provider UNIX timestamp.
	 */
	private int $provider_timestamp;

	/**
	 * Last successful sync UNIX timestamp.
	 */
	private int $last_synced_at;

	/**
	 * Data status (fresh, stale, fallback).
	 */
	private string $status;

	/**
	 * Numerical price change (if reliable).
	 */
	private ?float $price_change;

	/**
	 * Percentage change (if reliable).
	 */
	private ?float $percent_change;

	/**
	 * Constructor.
	 *
	 * @param float       $spot_price_ounce   Spot price per troy ounce.
	 * @param string      $currency           ISO Currency code.
	 * @param int         $provider_timestamp Provider reported timestamp.
	 * @param int         $last_synced_at     Local timestamp of successful sync.
	 * @param string      $status             Data status.
	 * @param float|null  $price_change       Numerical price change.
	 * @param float|null  $percent_change     Percentage price change.
	 */
	public function __construct(
		float $spot_price_ounce,
		string $currency,
		int $provider_timestamp,
		int $last_synced_at,
		string $status = self::STATUS_FRESH,
		?float $price_change = null,
		?float $percent_change = null
	) {
		$this->spot_price_ounce   = $spot_price_ounce;
		$this->currency           = strtoupper( $currency );
		$this->provider_timestamp = $provider_timestamp;
		$this->last_synced_at     = $last_synced_at;
		$this->status             = $status;
		$this->price_change       = $price_change;
		$this->percent_change     = $percent_change;
	}

	/**
	 * Spot price per Troy Ounce.
	 *
	 * @return float
	 */
	public function get_spot_price_ounce(): float {
		return $this->spot_price_ounce;
	}

	/**
	 * Currency code.
	 *
	 * @return string
	 */
	public function get_currency(): string {
		return $this->currency;
	}

	/**
	 * Provider timestamp.
	 *
	 * @return int
	 */
	public function get_provider_timestamp(): int {
		return $this->provider_timestamp;
	}

	/**
	 * Last synced timestamp.
	 *
	 * @return int
	 */
	public function get_last_synced_at(): int {
		return $this->last_synced_at;
	}

	/**
	 * Status (fresh, stale, fallback).
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Whether data is marked stale.
	 *
	 * @return bool
	 */
	public function is_stale(): bool {
		return self::STATUS_FRESH !== $this->status;
	}

	/**
	 * Price change.
	 *
	 * @return float|null
	 */
	public function get_price_change(): ?float {
		return $this->price_change;
	}

	/**
	 * Percent change.
	 *
	 * @return float|null
	 */
	public function get_percent_change(): ?float {
		return $this->percent_change;
	}

	/**
	 * Get spot price for a specified weight unit.
	 *
	 * @param string $unit Unit ('ounce', 'gram', 'tola').
	 * @return float
	 */
	public function get_spot_price_for_unit( string $unit ): float {
		return UnitConverter::convert_from_ounce( $this->spot_price_ounce, $unit );
	}

	/**
	 * Get price for a specific karat and unit.
	 *
	 * @param string $karat Karat ('24K', '22K', '21K', '18K').
	 * @param string $unit  Unit ('ounce', 'gram', 'tola').
	 * @return float
	 */
	public function get_karat_price( string $karat, string $unit ): float {
		$base_24k_unit_price = $this->get_spot_price_for_unit( $unit );
		return KaratCalculator::calculate_price( $base_24k_unit_price, $karat );
	}

	/**
	 * Get prices for all supported karats in a given unit.
	 *
	 * @param string $unit Unit ('ounce', 'gram', 'tola').
	 * @return array<string, float>
	 */
	public function get_all_karat_prices( string $unit ): array {
		$base_24k_unit_price = $this->get_spot_price_for_unit( $unit );
		return KaratCalculator::calculate_all( $base_24k_unit_price );
	}

	/**
	 * Convert to array for storage or REST API response.
	 *
	 * @param string $unit Optional unit to precalculate values for.
	 * @return array<string, mixed>
	 */
	public function to_array( string $unit = 'gram' ): array {
		$canonical_unit = UnitConverter::normalize_unit_key( $unit );
		$unit_spot      = $this->get_spot_price_for_unit( $canonical_unit );
		$karats         = $this->get_all_karat_prices( $canonical_unit );

		return array(
			'spot_price_ounce'   => $this->spot_price_ounce,
			'currency'           => $this->currency,
			'unit'               => $canonical_unit,
			'unit_spot_price'    => $unit_spot,
			'karats'             => $karats,
			'price_change'       => $this->price_change,
			'percent_change'     => $this->percent_change,
			'provider_timestamp' => $this->provider_timestamp,
			'last_synced_at'     => $this->last_synced_at,
			'status'             => $this->status,
			'is_stale'           => $this->is_stale(),
		);
	}

	/**
	 * Recreate GoldRateData instance from raw stored array.
	 *
	 * @param array<string, mixed> $data Serialized array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(float) ( $data['spot_price_ounce'] ?? 0.0 ),
			(string) ( $data['currency'] ?? 'USD' ),
			(int) ( $data['provider_timestamp'] ?? time() ),
			(int) ( $data['last_synced_at'] ?? time() ),
			(string) ( $data['status'] ?? self::STATUS_FRESH ),
			isset( $data['price_change'] ) ? (float) $data['price_change'] : null,
			isset( $data['percent_change'] ) ? (float) $data['percent_change'] : null
		);
	}

	/**
	 * Return a clone marked with a new status (e.g., stale).
	 *
	 * @param string $status New status.
	 * @return self
	 */
	public function with_status( string $status ): self {
		return new self(
			$this->spot_price_ounce,
			$this->currency,
			$this->provider_timestamp,
			$this->last_synced_at,
			$status,
			$this->price_change,
			$this->percent_change
		);
	}
}
