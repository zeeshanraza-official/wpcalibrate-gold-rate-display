<?php
/**
 * Public REST API Rates Controller.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Rest;

use WPCalibrate\GoldRateDisplay\Service\CurrencyService;
use WPCalibrate\GoldRateDisplay\Service\RateService;
use WPCalibrate\GoldRateDisplay\Service\SettingsService;
use WPCalibrate\GoldRateDisplay\Service\UnitConverter;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Serves public cached precious metal rate data for frontend components and auto-refresh.
 */
final class RatesController {

	/**
	 * REST route namespace.
	 */
	public const REST_NAMESPACE = 'wpcalibrate-gold-rate-display/v1';

	/**
	 * REST route path.
	 */
	public const REST_ROUTE = '/rates';

	/**
	 * Register REST routes on rest_api_init.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the rates endpoint.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rates' ),
				'permission_callback' => array( $this, 'get_rates_permissions_check' ),
				'args'                => array(
					'currency' => array(
						'description'       => __( 'ISO-4217 Currency code.', 'wpcalibrate-gold-rate-display' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $param ): bool {
							return is_string( $param ) && CurrencyService::is_supported( $param );
						},
					),
					'unit'     => array(
						'description'       => __( 'Weight unit (ounce, gram, tola).', 'wpcalibrate-gold-rate-display' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $param ): bool {
							return is_string( $param ) && UnitConverter::is_valid_unit( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Permission callback for public rates endpoint.
	 *
	 * Intentionally public access (returns true) because this endpoint delivers strictly
	 * sanitized, normalized market rate data for public frontend widgets, shortcodes, and blocks.
	 * Absolutely no administrative configuration, API keys, credentials, or sensitive metadata
	 * are returned or accessible via this endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool Always true for public read-only rates.
	 */
	public function get_rates_permissions_check( \WP_REST_Request $request ): bool {
		return true;
	}

	/**
	 * Retrieve normalized rate data.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_rates( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$settings = SettingsService::get_settings();

		// Read and validate query parameters, falling back to global defaults.
		$currency_param = $request->get_param( 'currency' );
		$currency       = ( ! empty( $currency_param ) && CurrencyService::is_supported( (string) $currency_param ) )
			? strtoupper( (string) $currency_param )
			: ( $settings['currency'] ?? 'USD' );

		$unit_param = $request->get_param( 'unit' );
		$unit       = ( ! empty( $unit_param ) && UnitConverter::is_valid_unit( (string) $unit_param ) )
			? UnitConverter::normalize_unit_key( (string) $unit_param )
			: ( $settings['unit'] ?? 'gram' );

		$rate_service = new RateService();
		$rate_data    = $rate_service->get_rate( $currency );

		if ( is_wp_error( $rate_data ) ) {
			return new \WP_Error(
				'rate_unavailable',
				__( 'Precious metal rates are temporarily unavailable.', 'wpcalibrate-gold-rate-display' ),
				array( 'status' => 503 )
			);
		}

		$decimals    = (int) ( $settings['decimals'] ?? 2 );
		$unit_spot   = $rate_data->get_spot_price_for_unit( $unit );
		$karats_all  = $rate_data->get_all_karat_prices( $unit );
		$last_sync   = $rate_data->get_last_synced_at();
		$formatted_t = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync );

		$formatted_karats = array();
		foreach ( $karats_all as $k => $val ) {
			$formatted_karats[ $k ] = CurrencyService::format_price( $val, $currency, $decimals );
		}

		$response_data = array(
			'currency'             => $currency,
			'currency_symbol'      => CurrencyService::get_symbol( $currency ),
			'unit'                 => $unit,
			'unit_label'           => UnitConverter::get_unit_label( $unit ),
			'unit_symbol'          => UnitConverter::get_unit_symbol( $unit ),
			'spot_price'           => $unit_spot,
			'formatted_spot_price' => CurrencyService::format_price( $unit_spot, $currency, $decimals ),
			'karats'               => $karats_all,
			'formatted_karats'     => $formatted_karats,
			'price_change'         => $rate_data->get_price_change(),
			'percent_change'       => $rate_data->get_percent_change(),
			'last_synced_at'       => $last_sync,
			'formatted_last_sync'  => $formatted_t,
			'status'               => $rate_data->get_status(),
			'is_stale'             => $rate_data->is_stale(),
		);

		return new \WP_REST_Response( $response_data, 200 );
	}
}
