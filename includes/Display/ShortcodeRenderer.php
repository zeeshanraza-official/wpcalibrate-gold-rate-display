<?php
/**
 * Shortcode and Frontend Renderer.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Display;

use WPCalibrate\GoldRateDisplay\Model\GoldRateData;
use WPCalibrate\GoldRateDisplay\Service\CurrencyService;
use WPCalibrate\GoldRateDisplay\Service\KaratCalculator;
use WPCalibrate\GoldRateDisplay\Service\RateService;
use WPCalibrate\GoldRateDisplay\Service\SettingsService;
use WPCalibrate\GoldRateDisplay\Service\UnitConverter;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles [wpcalibrate_gold_rate] shortcode and accessible frontend rendering.
 */
final class ShortcodeRenderer {

	/**
	 * Unique counter for generating unique DOM IDs on multiple widgets per page.
	 */
	private static int $instance_count = 0;

	/**
	 * Register shortcode hook.
	 */
	public function init(): void {
		add_shortcode( 'wpcalibrate_gold_rate', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( array|string $atts = array() ): string {
		self::$instance_count++;

		$settings = SettingsService::get_settings();
		$raw_atts = is_array( $atts ) ? $atts : array();

		// Attribute validation against allowlists.
		$currency = isset( $raw_atts['currency'] )
			? CurrencyService::sanitize_currency( (string) $raw_atts['currency'], $settings['currency'] )
			: $settings['currency'];

		$unit = isset( $raw_atts['unit'] )
			? UnitConverter::normalize_unit_key( (string) $raw_atts['unit'] )
			: $settings['unit'];

		$layout = isset( $raw_atts['layout'] ) ? sanitize_key( (string) $raw_atts['layout'] ) : $settings['layout'];
		if ( ! in_array( $layout, array( 'cards', 'table', 'compact' ), true ) ) {
			$layout = $settings['layout'];
		}

		$karats = $settings['karats'] ?? array( '24K', '22K', '21K', '18K' );
		if ( ! empty( $raw_atts['karats'] ) ) {
			$raw_karats_arr = explode( ',', (string) $raw_atts['karats'] );
			$parsed_karats  = array();
			foreach ( $raw_karats_arr as $k ) {
				$norm_k = KaratCalculator::normalize_karat_name( $k );
				if ( in_array( $norm_k, KaratCalculator::get_supported_karats(), true ) ) {
					$parsed_karats[] = $norm_k;
				}
			}
			if ( ! empty( $parsed_karats ) ) {
				$karats = array_values( array_unique( $parsed_karats ) );
			}
		}

		$show_spot           = isset( $raw_atts['show_spot'] ) ? filter_var( $raw_atts['show_spot'], FILTER_VALIDATE_BOOLEAN ) : ! empty( $settings['show_spot'] );
		$show_change         = isset( $raw_atts['show_change'] ) ? filter_var( $raw_atts['show_change'], FILTER_VALIDATE_BOOLEAN ) : ! empty( $settings['show_change'] );
		$show_percent_change = isset( $raw_atts['show_percent_change'] ) ? filter_var( $raw_atts['show_percent_change'], FILTER_VALIDATE_BOOLEAN ) : ! empty( $settings['show_percent_change'] );
		$show_updated        = isset( $raw_atts['show_updated'] ) ? filter_var( $raw_atts['show_updated'], FILTER_VALIDATE_BOOLEAN ) : ! empty( $settings['show_updated'] );
		$show_stale_notice   = isset( $raw_atts['show_stale_notice'] ) ? filter_var( $raw_atts['show_stale_notice'], FILTER_VALIDATE_BOOLEAN ) : ! empty( $settings['show_stale_notice'] );
		$heading             = isset( $raw_atts['heading'] ) ? sanitize_text_field( (string) $raw_atts['heading'] ) : ( $settings['heading'] ?? '' );
		$disclaimer          = isset( $raw_atts['disclaimer'] ) ? sanitize_textarea_field( (string) $raw_atts['disclaimer'] ) : ( $settings['disclaimer'] ?? '' );
		$decimals            = (int) ( $settings['decimals'] ?? 2 );

		// Enqueue frontend scripts & styles conditionally.
		$this->enqueue_frontend_assets( $settings );

		// Fetch rate data.
		$rate_service = new RateService();
		$rate_data    = $rate_service->get_rate( $currency );

		$widget_id = 'wpcalibrate-grd-widget-' . self::$instance_count;

		return self::render_html(
			$widget_id,
			$rate_data,
			$currency,
			$unit,
			$layout,
			$karats,
			$show_spot,
			$show_change,
			$show_percent_change,
			$show_updated,
			$show_stale_notice,
			$heading,
			$disclaimer,
			$decimals,
			! empty( $settings['frontend_auto_refresh'] )
		);
	}

	/**
	 * Enqueue frontend CSS and JS only when widget is rendered.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 */
	private function enqueue_frontend_assets( array $settings ): void {
		if ( ! is_admin() ) {
			wp_enqueue_style(
				'wpcalibrate-grd-frontend',
				WPCALIBRATE_GRD_URL . 'assets/css/frontend.css',
				array(),
				WPCALIBRATE_GRD_VERSION
			);

			if ( ! empty( $settings['frontend_auto_refresh'] ) ) {
				wp_enqueue_script(
					'wpcalibrate-grd-frontend',
					WPCALIBRATE_GRD_URL . 'assets/js/frontend.js',
					array(),
					WPCALIBRATE_GRD_VERSION,
					true
				);

				wp_localize_script(
					'wpcalibrate-grd-frontend',
					'wpcalibrateGrdConfig',
					array(
						'restUrl'         => esc_url_raw( rest_url( 'wpcalibrate-gold-rate-display/v1/rates' ) ),
						'refreshInterval' => max( 60, (int) ( $settings['refresh_interval'] ?? 15 ) * 60 ),
					)
				);
			}
		}
	}

	/**
	 * Render HTML output for widget.
	 */
	public static function render_html(
		string $widget_id,
		GoldRateData|\WP_Error $rate_data,
		string $currency,
		string $unit,
		string $layout,
		array $karats,
		bool $show_spot,
		bool $show_change,
		bool $show_percent_change,
		bool $show_updated,
		bool $show_stale_notice,
		string $heading,
		string $disclaimer,
		int $decimals,
		bool $auto_refresh
	): string {
		ob_start();

		$unit_label = UnitConverter::get_unit_label( $unit );
		$is_error   = is_wp_error( $rate_data );

		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>"
			 class="wpcalibrate-gold-rate-display wpcalibrate-layout-<?php echo esc_attr( $layout ); ?>"
			 data-currency="<?php echo esc_attr( $currency ); ?>"
			 data-unit="<?php echo esc_attr( $unit ); ?>"
			 data-decimals="<?php echo esc_attr( (string) $decimals ); ?>"
			 data-auto-refresh="<?php echo $auto_refresh ? 'true' : 'false'; ?>">

			<?php if ( '' !== $heading ) : ?>
				<h3 class="wpcalibrate-grd-title"><?php echo esc_html( $heading ); ?></h3>
			<?php endif; ?>

			<?php if ( $is_error ) : ?>
				<div class="wpcalibrate-grd-notice wpcalibrate-grd-notice-warning" role="alert">
					<p><?php esc_html_e( 'Market price data is currently unavailable. Please check back shortly.', 'wpcalibrate-gold-rate-display' ); ?></p>
				</div>
			<?php else : ?>
				<?php
				$is_stale        = $rate_data->is_stale();
				$spot_unit_price = $rate_data->get_spot_price_for_unit( $unit );
				$price_change    = $rate_data->get_price_change();
				$percent_change  = $rate_data->get_percent_change();
				$last_synced     = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rate_data->get_last_synced_at() );
				?>

				<?php if ( $is_stale && $show_stale_notice ) : ?>
					<div class="wpcalibrate-grd-notice wpcalibrate-grd-notice-stale" role="status">
						<span class="wpcalibrate-stale-icon" aria-hidden="true">⏱</span>
						<span><?php esc_html_e( 'Displaying latest recorded rate. Live update in progress.', 'wpcalibrate-gold-rate-display' ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $show_spot ) : ?>
					<div class="wpcalibrate-grd-spot-header">
						<div class="wpcalibrate-grd-spot-main">
							<span class="wpcalibrate-grd-spot-label"><?php echo esc_html( sprintf( __( 'Spot Gold (%1$s/%2$s)', 'wpcalibrate-gold-rate-display' ), $currency, $unit_label ) ); ?></span>
							<span class="wpcalibrate-grd-spot-price" data-role="spot-price"><?php echo esc_html( CurrencyService::format_price( $spot_unit_price, $currency, $decimals ) ); ?></span>
						</div>

						<?php if ( ( $show_change || $show_percent_change ) && null !== $price_change ) : ?>
							<?php
							$is_positive = $price_change >= 0.0;
							$change_cls  = $is_positive ? 'wpcalibrate-change-up' : 'wpcalibrate-change-down';
							$sign        = $is_positive ? '+' : '';
							?>
							<div class="wpcalibrate-grd-change-wrap <?php echo esc_attr( $change_cls ); ?>" data-role="change-indicator">
								<span class="screen-reader-text">
									<?php echo $is_positive ? esc_html__( 'Price increased by:', 'wpcalibrate-gold-rate-display' ) : esc_html__( 'Price decreased by:', 'wpcalibrate-gold-rate-display' ); ?>
								</span>
								<?php if ( $show_change ) : ?>
									<span class="wpcalibrate-grd-change-amount"><?php echo esc_html( $sign . CurrencyService::format_price( $price_change, $currency, $decimals ) ); ?></span>
								<?php endif; ?>
								<?php if ( $show_percent_change && null !== $percent_change ) : ?>
									<span class="wpcalibrate-grd-change-percent">(<?php echo esc_html( $sign . number_format( $percent_change, 2 ) ); ?>%)</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Layout Specific Renderers -->
				<?php
				match ( $layout ) {
					'table'   => self::render_table_layout( $rate_data, $karats, $currency, $unit, $decimals ),
					'compact' => self::render_compact_layout( $rate_data, $karats, $currency, $unit, $decimals ),
					default   => self::render_cards_layout( $rate_data, $karats, $currency, $unit, $decimals ),
				};
				?>

				<?php if ( $show_updated ) : ?>
					<footer class="wpcalibrate-grd-meta">
						<span class="wpcalibrate-grd-updated">
							<?php echo sprintf( esc_html__( 'Last updated: %s', 'wpcalibrate-gold-rate-display' ), '<time datetime="' . esc_attr( gmdate( 'c', $rate_data->get_last_synced_at() ) ) . '" data-role="updated-time">' . esc_html( $last_synced ) . '</time>' ); ?>
						</span>
					</footer>
				<?php endif; ?>

			<?php endif; ?>

			<?php if ( '' !== $disclaimer ) : ?>
				<div class="wpcalibrate-grd-disclaimer">
					<small><?php echo esc_html( $disclaimer ); ?></small>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render Cards Layout.
	 */
	private static function render_cards_layout( GoldRateData $rate_data, array $karats, string $currency, string $unit, int $decimals ): void {
		?>
		<div class="wpcalibrate-grd-cards-grid" role="region" aria-label="<?php esc_attr_e( 'Gold Rates by Karat', 'wpcalibrate-gold-rate-display' ); ?>">
			<?php foreach ( $karats as $karat ) : ?>
				<?php
				$price    = $rate_data->get_karat_price( $karat, $unit );
				$fineness = isset( KaratCalculator::PURITY_FINENESS[ $karat ] ) ? (string) KaratCalculator::PURITY_FINENESS[ $karat ] : '';
				?>
				<div class="wpcalibrate-grd-card" data-karat="<?php echo esc_attr( $karat ); ?>">
					<div class="wpcalibrate-grd-card-header">
						<span class="wpcalibrate-grd-karat-title"><?php echo esc_html( $karat ); ?> Gold</span>
						<?php if ( '' !== $fineness ) : ?>
							<span class="wpcalibrate-grd-karat-purity"><?php echo esc_html( $fineness ); ?> / 1000</span>
						<?php endif; ?>
					</div>
					<div class="wpcalibrate-grd-card-body">
						<span class="wpcalibrate-grd-karat-price" data-role="karat-price"><?php echo esc_html( CurrencyService::format_price( $price, $currency, $decimals ) ); ?></span>
						<span class="wpcalibrate-grd-karat-unit"><?php echo esc_html( sprintf( __( 'per %s', 'wpcalibrate-gold-rate-display' ), UnitConverter::get_unit_label( $unit ) ) ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render Table Layout.
	 */
	private static function render_table_layout( GoldRateData $rate_data, array $karats, string $currency, string $unit, int $decimals ): void {
		$unit_label = UnitConverter::get_unit_label( $unit );
		?>
		<div class="wpcalibrate-grd-table-responsive">
			<table class="wpcalibrate-grd-table">
				<caption class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Gold rates per %s in %s', 'wpcalibrate-gold-rate-display' ), $unit_label, $currency ) ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Karat', 'wpcalibrate-gold-rate-display' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Purity', 'wpcalibrate-gold-rate-display' ); ?></th>
						<th scope="col"><?php echo esc_html( sprintf( __( 'Rate per %s', 'wpcalibrate-gold-rate-display' ), $unit_label ) ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $karats as $karat ) : ?>
						<?php
						$price    = $rate_data->get_karat_price( $karat, $unit );
						$fineness = isset( KaratCalculator::PURITY_FINENESS[ $karat ] ) ? (string) KaratCalculator::PURITY_FINENESS[ $karat ] : '';
						?>
						<tr data-karat="<?php echo esc_attr( $karat ); ?>">
							<th scope="row"><strong><?php echo esc_html( $karat ); ?></strong></th>
							<td><?php echo esc_html( '' !== $fineness ? $fineness . ' / 1000' : '—' ); ?></td>
							<td class="wpcalibrate-grd-table-price" data-role="karat-price"><?php echo esc_html( CurrencyService::format_price( $price, $currency, $decimals ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Compact Ribbon Layout.
	 */
	private static function render_compact_layout( GoldRateData $rate_data, array $karats, string $currency, string $unit, int $decimals ): void {
		$unit_symbol = UnitConverter::get_unit_symbol( $unit );
		?>
		<div class="wpcalibrate-grd-compact-strip" role="list">
			<?php foreach ( $karats as $karat ) : ?>
				<?php
				$price = $rate_data->get_karat_price( $karat, $unit );
				?>
				<div class="wpcalibrate-grd-compact-item" role="listitem" data-karat="<?php echo esc_attr( $karat ); ?>">
					<span class="wpcalibrate-grd-compact-tag"><?php echo esc_html( $karat ); ?></span>
					<span class="wpcalibrate-grd-compact-price" data-role="karat-price"><?php echo esc_html( CurrencyService::format_price( $price, $currency, $decimals ) ); ?></span>
					<span class="wpcalibrate-grd-compact-unit">/<?php echo esc_html( $unit_symbol ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
