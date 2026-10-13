<?php
/**
 * MetalpriceAPI Rate Provider Implementation.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Api;

use WPCalibrate\GoldRateDisplay\Service\CredentialService;
use WPCalibrate\GoldRateDisplay\Service\CurrencyService;
use WPCalibrate\GoldRateDisplay\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * MetalpriceAPI integration client.
 * Connects securely to https://api.metalpriceapi.com/v1/ using WordPress HTTP API.
 */
class MetalpriceApiProvider implements RateProviderInterface {

	/**
	 * API Base URL (Strict HTTPS).
	 */
	public const API_BASE_URL = 'https://api.metalpriceapi.com/v1/';

	/**
	 * HTTP Request timeout in seconds.
	 */
	public const REQUEST_TIMEOUT = 12;

	/**
	 * Provider display name.
	 */
	public function get_name(): string {
		return 'MetalpriceAPI';
	}

	/**
	 * Fetch latest rate for a given currency.
	 *
	 * @param string $currency ISO currency code.
	 * @return ProviderResponse
	 */
	public function get_latest_rate( string $currency ): ProviderResponse {
		$target_currency = CurrencyService::sanitize_currency( $currency );
		$api_key         = CredentialService::get_api_key();

		if ( '' === $api_key ) {
			return ProviderResponse::failure(
				'missing_api_key',
				__( 'MetalpriceAPI key is not configured. Please enter your API key in settings.', 'wpcalibrate-gold-rate-display' ),
				$target_currency
			);
		}

		// Build currencies parameter: XAU plus target fiat currency if not USD.
		$currencies_param = 'XAU';
		if ( 'USD' !== $target_currency ) {
			$currencies_param .= ',' . $target_currency;
		}

		$endpoint = add_query_arg(
			array(
				'currencies' => $currencies_param,
			),
			self::API_BASE_URL . 'latest'
		);

		$response = $this->send_request( $endpoint, $api_key );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'error', 'HTTP error connecting to MetalpriceAPI', array( 'error' => $response->get_error_message() ) );
			return ProviderResponse::failure(
				'http_request_failed',
				$response->get_error_message(),
				$target_currency
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Extract quota headers when present.
		$quota_used  = isset( $headers['x-api-current'] ) ? (int) $headers['x-api-current'] : null;
		$quota_total = isset( $headers['x-api-quota'] ) ? (int) $headers['x-api-quota'] : null;

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			Logger::log( 'error', 'Malformed JSON response from MetalpriceAPI', array( 'status_code' => $status_code ) );
			return ProviderResponse::failure(
				'malformed_json',
				__( 'Received invalid JSON data from the rate provider.', 'wpcalibrate-gold-rate-display' ),
				$target_currency,
				$quota_used,
				$quota_total
			);
		}

		// Check for provider-level failure response.
		if ( empty( $decoded['success'] ) ) {
			$err_code = (string) ( $decoded['error']['code'] ?? $status_code );
			$err_msg  = (string) ( $decoded['error']['info'] ?? $decoded['error']['message'] ?? __( 'Unknown API error returned.', 'wpcalibrate-gold-rate-display' ) );

			// Map common provider error codes.
			if ( 401 === $status_code || str_contains( strtolower( $err_msg ), 'invalid api key' ) ) {
				$err_code = 'invalid_api_key';
			} elseif ( 429 === $status_code || str_contains( strtolower( $err_msg ), 'quota' ) || str_contains( strtolower( $err_msg ), 'usage limit' ) ) {
				$err_code = 'quota_exceeded';
			}

			Logger::log( 'error', 'Provider returned error', array( 'code' => $err_code, 'message' => $err_msg ) );

			return ProviderResponse::failure(
				$err_code,
				$err_msg,
				$target_currency,
				$quota_used,
				$quota_total
			);
		}

		// Validate rates payload.
		$rates = $decoded['rates'] ?? array();
		if ( ! is_array( $rates ) || empty( $rates ) ) {
			return ProviderResponse::failure(
				'missing_rates',
				__( 'API response did not contain required rates object.', 'wpcalibrate-gold-rate-display' ),
				$target_currency,
				$quota_used,
				$quota_total
			);
		}

		$timestamp = (int) ( $decoded['timestamp'] ?? time() );
		$base      = strtoupper( (string) ( $decoded['base'] ?? 'USD' ) );

		// Calculate spot price per Troy Ounce in requested currency.
		$spot_price_ounce = $this->calculate_spot_price( $rates, $base, $target_currency );

		if ( null === $spot_price_ounce || $spot_price_ounce <= 0.0 ) {
			return ProviderResponse::failure(
				'invalid_rate_value',
				__( 'Could not determine a valid positive gold spot price from API rates.', 'wpcalibrate-gold-rate-display' ),
				$target_currency,
				$quota_used,
				$quota_total
			);
		}

		// Update credential quota cache.
		if ( null !== $quota_used || null !== $quota_total ) {
			CredentialService::update_status( CredentialService::STATUS_VALID, '', $quota_used, $quota_total );
		}

		return ProviderResponse::success(
			$spot_price_ounce,
			$target_currency,
			$timestamp,
			$quota_used,
			$quota_total
		);
	}

	/**
	 * Test provider connection and credentials without consuming billable quote where possible.
	 *
	 * @param string $api_key API key to test.
	 * @return ProviderResponse
	 */
	public function test_connection( string $api_key ): ProviderResponse {
		$trimmed_key = trim( $api_key );
		if ( '' === $trimmed_key ) {
			return ProviderResponse::failure(
				'empty_key',
				__( 'Please provide an API key to test.', 'wpcalibrate-gold-rate-display' )
			);
		}

		// Step 1: Attempt the /usage endpoint (free of billable quota).
		$usage_url = self::API_BASE_URL . 'usage';
		$response  = $this->send_request( $usage_url, $trimmed_key );

		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );
			$headers     = wp_remote_retrieve_headers( $response );
			$decoded     = json_decode( $body, true );

			$quota_used  = isset( $headers['x-api-current'] ) ? (int) $headers['x-api-current'] : null;
			$quota_total = isset( $headers['x-api-quota'] ) ? (int) $headers['x-api-quota'] : null;

			if ( 200 === $status_code && is_array( $decoded ) && ! empty( $decoded['success'] ) ) {
				$plan_name = (string) ( $decoded['plan'] ?? $decoded['user']['plan'] ?? '' );
				if ( null === $quota_used && isset( $decoded['usage']['requests_month'] ) ) {
					$quota_used = (int) $decoded['usage']['requests_month'];
				}
				if ( null === $quota_total && isset( $decoded['usage']['monthly_limit'] ) ) {
					$quota_total = (int) $decoded['usage']['monthly_limit'];
				}

				return ProviderResponse::success( 0.0, 'USD', time(), $quota_used, $quota_total, $plan_name );
			}

			// If usage returned explicit auth error, fail immediately.
			if ( 401 === $status_code || ( is_array( $decoded ) && ! empty( $decoded['error'] ) && 401 === ( $decoded['error']['code'] ?? 0 ) ) ) {
				return ProviderResponse::failure(
					'invalid_api_key',
					__( 'Invalid API Key. Please verify your MetalpriceAPI key.', 'wpcalibrate-gold-rate-display' )
				);
			}
		}

		// Step 2: Fallback to lightweight /latest query for XAU to verify key.
		$latest_url = add_query_arg( array( 'currencies' => 'XAU' ), self::API_BASE_URL . 'latest' );
		$fallback   = $this->send_request( $latest_url, $trimmed_key );

		if ( is_wp_error( $fallback ) ) {
			return ProviderResponse::failure( 'http_error', $fallback->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $fallback );
		$body        = wp_remote_retrieve_body( $fallback );
		$headers     = wp_remote_retrieve_headers( $fallback );
		$decoded     = json_decode( $body, true );

		$quota_used  = isset( $headers['x-api-current'] ) ? (int) $headers['x-api-current'] : null;
		$quota_total = isset( $headers['x-api-quota'] ) ? (int) $headers['x-api-quota'] : null;

		if ( 200 === $status_code && is_array( $decoded ) && ! empty( $decoded['success'] ) ) {
			return ProviderResponse::success( 0.0, 'USD', time(), $quota_used, $quota_total );
		}

		$err_msg = (string) ( $decoded['error']['info'] ?? $decoded['error']['message'] ?? __( 'Connection test failed.', 'wpcalibrate-gold-rate-display' ) );
		return ProviderResponse::failure( 'api_error_' . $status_code, $err_msg, 'USD', $quota_used, $quota_total );
	}

	/**
	 * Send HTTP request with X-API-KEY header and security guards.
	 *
	 * @param string $url     Endpoint URL.
	 * @param string $api_key API key.
	 * @return array|\WP_Error
	 */
	protected function send_request( string $url, string $api_key ) {
		$args = array(
			'timeout'     => self::REQUEST_TIMEOUT,
			'redirection' => 2,
			'sslverify'   => true,
			'headers'     => array(
				'Accept'    => 'application/json',
				'x-api-key' => $api_key,
			),
		);

		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Calculate spot price per Troy Ounce from rates payload.
	 *
	 * @param array<string, mixed> $rates           Rates map from response.
	 * @param string               $base            Response base currency (e.g. 'USD' or 'XAU').
	 * @param string               $target_currency Requested fiat currency (e.g. 'USD', 'EUR', 'PKR').
	 * @return float|null
	 */
	private function calculate_spot_price( array $rates, string $base, string $target_currency ): ?float {
		// Case A: Response base is XAU, direct currency quote.
		if ( 'XAU' === $base && isset( $rates[ $target_currency ] ) ) {
			return (float) $rates[ $target_currency ];
		}

		// Case B: Response contains inverted XAU rate (how many ounces per 1 unit of base currency).
		if ( isset( $rates['XAU'] ) ) {
			$xau_rate = (float) $rates['XAU'];
			if ( $xau_rate <= 0.0 ) {
				return null;
			}

			// Price of 1 Troy Ounce in base currency.
			$ounce_in_base = ( $xau_rate < 1.0 ) ? ( 1.0 / $xau_rate ) : $xau_rate;

			// If target currency matches base currency.
			if ( $base === $target_currency ) {
				return $ounce_in_base;
			}

			// If target currency is another fiat currency, convert via target exchange rate.
			if ( isset( $rates[ $target_currency ] ) ) {
				$target_rate = (float) $rates[ $target_currency ];
				return $ounce_in_base * $target_rate;
			}
		}

		// Case C: Response contains target currency directly when base is target.
		if ( isset( $rates[ $target_currency ] ) && is_numeric( $rates[ $target_currency ] ) ) {
			return (float) $rates[ $target_currency ];
		}

		return null;
	}
}
