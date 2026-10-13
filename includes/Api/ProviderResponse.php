<?php
/**
 * Provider Response value object.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Api;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Encapsulates the result of a provider API request.
 */
final class ProviderResponse {

	private bool $success;
	private ?float $spot_price_ounce;
	private string $currency;
	private int $timestamp;
	private string $error_code;
	private string $error_message;
	private ?int $quota_used;
	private ?int $quota_total;
	private ?string $plan_name;

	/**
	 * Constructor.
	 */
	public function __construct(
		bool $success,
		?float $spot_price_ounce,
		string $currency,
		int $timestamp = 0,
		string $error_code = '',
		string $error_message = '',
		?int $quota_used = null,
		?int $quota_total = null,
		?string $plan_name = null
	) {
		$this->success           = $success;
		$this->spot_price_ounce  = $spot_price_ounce;
		$this->currency          = strtoupper( $currency );
		$this->timestamp         = $timestamp > 0 ? $timestamp : time();
		$this->error_code        = $error_code;
		$this->error_message     = $error_message;
		$this->quota_used        = $quota_used;
		$this->quota_total       = $quota_total;
		$this->plan_name         = $plan_name;
	}

	public static function success(
		float $spot_price_ounce,
		string $currency,
		int $timestamp,
		?int $quota_used = null,
		?int $quota_total = null,
		?string $plan_name = null
	): self {
		return new self(
			true,
			$spot_price_ounce,
			$currency,
			$timestamp,
			'',
			'',
			$quota_used,
			$quota_total,
			$plan_name
		);
	}

	public static function failure(
		string $error_code,
		string $error_message,
		string $currency = 'USD',
		?int $quota_used = null,
		?int $quota_total = null
	): self {
		return new self(
			false,
			null,
			$currency,
			time(),
			$error_code,
			$error_message,
			$quota_used,
			$quota_total
		);
	}

	public function is_success(): bool {
		return $this->success;
	}

	public function get_spot_price_ounce(): ?float {
		return $this->spot_price_ounce;
	}

	public function get_currency(): string {
		return $this->currency;
	}

	public function get_timestamp(): int {
		return $this->timestamp;
	}

	public function get_error_code(): string {
		return $this->error_code;
	}

	public function get_error_message(): string {
		return $this->error_message;
	}

	public function get_quota_used(): ?int {
		return $this->quota_used;
	}

	public function get_quota_total(): ?int {
		return $this->quota_total;
	}

	public function get_quota_remaining(): ?int {
		if ( null !== $this->quota_used && null !== $this->quota_total ) {
			return max( 0, $this->quota_total - $this->quota_used );
		}
		return null;
	}

	public function get_plan_name(): ?string {
		return $this->plan_name;
	}
}
