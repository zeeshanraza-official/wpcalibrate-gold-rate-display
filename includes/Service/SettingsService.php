<?php
/**
 * Settings service for WPCalibrate Gold Rate Display.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Service;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Manages plugin configuration settings.
 */
final class SettingsService {

	/**
	 * Option key for settings.
	 */
	public const OPTION_KEY = 'wpcalibrate_grd_settings';

	/**
	 * Allowed refresh intervals in minutes.
	 */
	public const ALLOWED_REFRESH_INTERVALS = array( 1, 5, 10, 15, 30, 60 );

	/**
	 * Default settings map.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'currency'              => 'USD',
			'unit'                  => 'gram',
			'karats'                => array( '24K', '22K', '21K', '18K' ),
			'layout'                => 'cards',
			'refresh_interval'      => 15,
			'decimals'              => 2,
			'show_spot'             => true,
			'show_change'           => true,
			'show_percent_change'   => true,
			'show_updated'          => true,
			'show_stale_notice'     => true,
			'frontend_auto_refresh' => true,
			'heading'               => '',
			'disclaimer'            => __( 'Market prices are for informational purposes and may differ from local dealer prices.', 'wpcalibrate-gold-rate-display' ),
			'debug_logging'         => false,
			'delete_on_uninstall'   => false,
		);
	}

	/**
	 * Get current saved settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::get_defaults(), $saved );
	}

	/**
	 * Sanitize raw settings input from admin forms.
	 *
	 * @param array<string, mixed> $raw Raw input.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize_settings( array $raw ): array {
		$defaults = self::get_defaults();

		// Currency.
		$currency = isset( $raw['currency'] ) ? sanitize_text_field( (string) $raw['currency'] ) : $defaults['currency'];
		$currency = CurrencyService::sanitize_currency( $currency, $defaults['currency'] );

		// Unit.
		$unit = isset( $raw['unit'] ) ? sanitize_key( (string) $raw['unit'] ) : $defaults['unit'];
		if ( ! UnitConverter::is_valid_unit( $unit ) ) {
			$unit = $defaults['unit'];
		} else {
			$unit = UnitConverter::normalize_unit_key( $unit );
		}

		// Karats.
		$karats = array();
		if ( isset( $raw['karats'] ) && is_array( $raw['karats'] ) ) {
			foreach ( $raw['karats'] as $k ) {
				$norm_k = KaratCalculator::normalize_karat_name( sanitize_text_field( (string) $k ) );
				if ( in_array( $norm_k, KaratCalculator::get_supported_karats(), true ) ) {
					$karats[] = $norm_k;
				}
			}
		}
		if ( empty( $karats ) ) {
			$karats = $defaults['karats'];
		}
		$karats = array_values( array_unique( $karats ) );

		// Layout.
		$layout = isset( $raw['layout'] ) ? sanitize_key( (string) $raw['layout'] ) : $defaults['layout'];
		if ( ! in_array( $layout, array( 'cards', 'table', 'compact' ), true ) ) {
			$layout = $defaults['layout'];
		}

		// Refresh interval (minutes).
		$refresh_interval = isset( $raw['refresh_interval'] ) ? (int) $raw['refresh_interval'] : $defaults['refresh_interval'];
		if ( ! in_array( $refresh_interval, self::ALLOWED_REFRESH_INTERVALS, true ) ) {
			$refresh_interval = $defaults['refresh_interval'];
		}

		// Decimals (0 - 4).
		$decimals = isset( $raw['decimals'] ) ? (int) $raw['decimals'] : $defaults['decimals'];
		if ( $decimals < 0 || $decimals > 4 ) {
			$decimals = $defaults['decimals'];
		}

		// Toggles.
		$show_spot             = ! empty( $raw['show_spot'] );
		$show_change           = ! empty( $raw['show_change'] );
		$show_percent_change   = ! empty( $raw['show_percent_change'] );
		$show_updated          = ! empty( $raw['show_updated'] );
		$show_stale_notice     = ! empty( $raw['show_stale_notice'] );
		$frontend_auto_refresh = ! empty( $raw['frontend_auto_refresh'] );
		$debug_logging         = ! empty( $raw['debug_logging'] );
		$delete_on_uninstall   = ! empty( $raw['delete_on_uninstall'] );

		// Text inputs.
		$heading    = isset( $raw['heading'] ) ? sanitize_text_field( (string) $raw['heading'] ) : '';
		$disclaimer = isset( $raw['disclaimer'] ) ? sanitize_textarea_field( (string) $raw['disclaimer'] ) : $defaults['disclaimer'];

		return array(
			'currency'              => $currency,
			'unit'                  => $unit,
			'karats'                => $karats,
			'layout'                => $layout,
			'refresh_interval'      => $refresh_interval,
			'decimals'              => $decimals,
			'show_spot'             => $show_spot,
			'show_change'           => $show_change,
			'show_percent_change'   => $show_percent_change,
			'show_updated'          => $show_updated,
			'show_stale_notice'     => $show_stale_notice,
			'frontend_auto_refresh' => $frontend_auto_refresh,
			'heading'               => $heading,
			'disclaimer'            => $disclaimer,
			'debug_logging'         => $debug_logging,
			'delete_on_uninstall'   => $delete_on_uninstall,
		);
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $raw Raw input.
	 * @return bool
	 */
	public static function update_settings( array $raw ): bool {
		$sanitized = self::sanitize_settings( $raw );
		return update_option( self::OPTION_KEY, $sanitized );
	}
}
