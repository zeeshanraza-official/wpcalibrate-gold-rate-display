<?php
/**
 * Settings Page Controller.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Admin;

use WPCalibrate\GoldRateDisplay\Api\MetalpriceApiProvider;
use WPCalibrate\GoldRateDisplay\Cache\CacheManager;
use WPCalibrate\GoldRateDisplay\Licensing\PendingLicenseService;
use WPCalibrate\GoldRateDisplay\Model\GoldRateData;
use WPCalibrate\GoldRateDisplay\Scheduler\RateScheduler;
use WPCalibrate\GoldRateDisplay\Service\CredentialService;
use WPCalibrate\GoldRateDisplay\Service\CurrencyService;
use WPCalibrate\GoldRateDisplay\Service\KaratCalculator;
use WPCalibrate\GoldRateDisplay\Service\RateService;
use WPCalibrate\GoldRateDisplay\Service\SettingsService;
use WPCalibrate\GoldRateDisplay\Service\UnitConverter;
use WPCalibrate\GoldRateDisplay\Support\SupportService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Manages admin settings tabs, form handling, notices, and AJAX callbacks.
 */
final class SettingsPage {

	/**
	 * Available tabs.
	 */
	public const TABS = array(
		'overview'  => 'Overview',
		'dashboard' => 'Dashboard',
		'api'       => 'API Settings',
		'display'   => 'Display',
		'advanced'  => 'Advanced',
		'license'   => 'License',
		'support'   => 'Support',
	);

	/**
	 * Register actions and AJAX handlers.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submissions' ) );
		add_action( 'wp_ajax_wpcalibrate_grd_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wpcalibrate_grd_manual_refresh', array( __CLASS__, 'ajax_manual_refresh' ) );
	}

	/**
	 * Handle POST form actions with strict security checks.
	 */
	public static function handle_form_submissions(): void {
		if ( ! isset( $_POST['wpcalibrate_grd_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-gold-rate-display' ) );
		}

		check_admin_referer( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['wpcalibrate_grd_action'] ) );
		$tab    = sanitize_key( wp_unslash( $_POST['current_tab'] ?? 'dashboard' ) );

		if ( 'save_settings' === $action ) {
			$raw_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
			SettingsService::update_settings( $raw_settings );

			// Update cron schedule if refresh interval changed.
			$interval = (int) ( $raw_settings['refresh_interval'] ?? 15 );
			RateScheduler::reschedule( $interval );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => AdminMenu::PAGE_SLUG,
						'tab'     => $tab,
						'updated' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( 'save_api_key' === $action ) {
			$raw_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
			CredentialService::save_api_key( $raw_key );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => AdminMenu::PAGE_SLUG,
						'tab'     => 'api',
						'updated' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( 'clear_api_key' === $action ) {
			CredentialService::clear_api_key();

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => AdminMenu::PAGE_SLUG,
						'tab'     => 'api',
						'cleared' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( 'clear_cache' === $action ) {
			CacheManager::clear_all_rates();

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'          => AdminMenu::PAGE_SLUG,
						'tab'           => 'advanced',
						'cache_cleared' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( 'manual_refresh' === $action ) {
			$settings = SettingsService::get_settings();
			$currency = $settings['currency'] ?? 'USD';
			$result   = RateScheduler::execute_manual_refresh( $currency );

			$args = array(
				'page' => AdminMenu::PAGE_SLUG,
				'tab'  => 'dashboard',
			);

			if ( $result['success'] ) {
				$args['refreshed'] = '1';
			} else {
				$args['error'] = rawurlencode( $result['message'] );
			}

			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * AJAX endpoint for testing connection with MetalpriceAPI.
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'wpcalibrate_grd_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpcalibrate-gold-rate-display' ) ) );
		}

		$submitted_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$key_to_test   = ( '' !== $submitted_key && $submitted_key !== CredentialService::get_masked_key() )
			? $submitted_key
			: CredentialService::get_api_key();

		if ( '' === $key_to_test ) {
			wp_send_json_error( array( 'message' => __( 'Please enter an API key first.', 'wpcalibrate-gold-rate-display' ) ) );
		}

		$provider = new MetalpriceApiProvider();
		$result   = $provider->test_connection( $key_to_test );

		if ( $result->is_success() ) {
			CredentialService::update_status(
				CredentialService::STATUS_VALID,
				'',
				$result->get_quota_used(),
				$result->get_quota_total(),
				$result->get_plan_name()
			);

			$quota_info = '';
			if ( null !== $result->get_quota_used() && null !== $result->get_quota_total() ) {
				$quota_info = sprintf(
					/* translators: 1: Quota used, 2: Quota total */
					__( ' (Usage: %1$d / %2$d calls)', 'wpcalibrate-gold-rate-display' ),
					$result->get_quota_used(),
					$result->get_quota_total()
				);
			}

			wp_send_json_success(
				array(
					'message'         => __( 'Connection successful! MetalpriceAPI verified.', 'wpcalibrate-gold-rate-display' ) . $quota_info,
					'quota_used'      => $result->get_quota_used(),
					'quota_total'     => $result->get_quota_total(),
					'quota_remaining' => $result->get_quota_remaining(),
					'plan_name'       => $result->get_plan_name(),
				)
			);
		} else {
			CredentialService::update_status(
				CredentialService::STATUS_INVALID,
				$result->get_error_message()
			);

			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Connection failed: %s', 'wpcalibrate-gold-rate-display' ),
						$result->get_error_message()
					),
				)
			);
		}
	}

	/**
	 * AJAX endpoint for live manual refresh from dashboard.
	 */
	public static function ajax_manual_refresh(): void {
		check_ajax_referer( 'wpcalibrate_grd_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpcalibrate-gold-rate-display' ) ) );
		}

		$settings = SettingsService::get_settings();
		$currency = $settings['currency'] ?? 'USD';
		$result   = RateScheduler::execute_manual_refresh( $currency );

		if ( $result['success'] ) {
			$rate_service = new RateService();
			$rate_data    = $rate_service->get_rate( $currency );

			if ( ! is_wp_error( $rate_data ) ) {
				$unit       = $settings['unit'] ?? 'gram';
				$spot_unit  = $rate_data->get_spot_price_for_unit( $unit );
				$formatted  = CurrencyService::format_price( $spot_unit, $currency, (int) $settings['decimals'] );
				$last_sync  = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rate_data->get_last_synced_at() );

				wp_send_json_success(
					array(
						'message'         => $result['message'],
						'formatted_price' => $formatted,
						'last_sync'       => $last_sync,
						'status'          => $rate_data->get_status(),
					)
				);
			}

			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Render the Settings Page HTML.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpcalibrate-gold-rate-display' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! array_key_exists( $current_tab, self::TABS ) ) {
			$current_tab = 'overview';
		}

		$settings    = SettingsService::get_settings();
		$credentials = CredentialService::get_credentials();
		$ajax_nonce  = wp_create_nonce( 'wpcalibrate_grd_ajax_nonce' );

		?>
		<div class="wrap wpcalibrate-grd-admin-wrap">
			<!-- Header with Brand Icon -->
			<header class="wpcalibrate-grd-header">
				<div class="wpcalibrate-grd-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_GRD_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-grd-logo wpcalibrate-logo-light-bg" width="36" height="36" />
					<div>
						<h1><?php esc_html_e( 'WPCalibrate Gold Rate Display', 'wpcalibrate-gold-rate-display' ); ?> <span class="wpcalibrate-grd-version">v<?php echo esc_html( WPCALIBRATE_GRD_VERSION ); ?></span></h1>
						<p class="wpcalibrate-grd-subtitle"><?php esc_html_e( 'Live Gold Prices with MetalpriceAPI, intelligent caching, and customizable frontend widgets.', 'wpcalibrate-gold-rate-display' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Admin Notices -->
			<?php self::render_notices(); ?>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper wpcalibrate-grd-tabs" role="tablist">
				<?php foreach ( self::TABS as $tab_key => $tab_label ) : ?>
					<?php
					$tab_url = add_query_arg(
						array(
							'page' => AdminMenu::PAGE_SLUG,
							'tab'  => $tab_key,
						),
						admin_url( 'admin.php' )
					);
					$is_active = ( $current_tab === $tab_key );
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>"
					   class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>"
					   role="tab"
					   aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Tab Content Container -->
			<main class="wpcalibrate-grd-tab-body" id="wpcalibrate-grd-tab-content">
				<?php
				match ( $current_tab ) {
					'overview'  => self::render_tab_overview(),
					'dashboard' => self::render_tab_dashboard( $settings, $credentials, $ajax_nonce ),
					'api'       => self::render_tab_api( $credentials, $ajax_nonce ),
					'display'   => self::render_tab_display( $settings ),
					'advanced'  => self::render_tab_advanced( $settings ),
					'license'   => self::render_tab_license(),
					'support'   => self::render_tab_support(),
					default     => self::render_tab_overview(),
				};
				?>
			</main>
		</div>
		<?php
	}

	/**
	 * Render system feedback notices.
	 */
	private static function render_notices(): void {
		if ( isset( $_GET['updated'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved successfully.', 'wpcalibrate-gold-rate-display' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['cleared'] ) ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p><?php esc_html_e( 'API key removed.', 'wpcalibrate-gold-rate-display' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['cache_cleared'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Rate cache cleared successfully.', 'wpcalibrate-gold-rate-display' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['refreshed'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Gold rates refreshed successfully.', 'wpcalibrate-gold-rate-display' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['error'] ) ) {
			$error_message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) );
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $error_message ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Overview Tab: Purpose, Architecture, Methodology & Guided Workflow.
	 */
	private static function render_tab_overview(): void {
		$api_tab_url = add_query_arg(
			array(
				'page' => AdminMenu::PAGE_SLUG,
				'tab'  => 'api',
			),
			admin_url( 'admin.php' )
		);
		$dashboard_tab_url = add_query_arg(
			array(
				'page' => AdminMenu::PAGE_SLUG,
				'tab'  => 'dashboard',
			),
			admin_url( 'admin.php' )
		);
		$display_tab_url = add_query_arg(
			array(
				'page' => AdminMenu::PAGE_SLUG,
				'tab'  => 'display',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wpcalibrate-overview-container">
			<!-- Hero Banner -->
			<div class="wpcalibrate-card wpcalibrate-overview-hero">
				<div class="wpcalibrate-overview-hero-content">
					<div class="wpcalibrate-overview-hero-text">
						<h2><?php esc_html_e( 'Welcome to WPCalibrate Gold Rate Display', 'wpcalibrate-gold-rate-display' ); ?></h2>
						<p class="wpcalibrate-overview-hero-lead">
							<?php esc_html_e( 'A high-performance, standalone WordPress plugin engineered to fetch live spot gold market prices via MetalpriceAPI and calculate 24K, 22K, 21K, and 18K purity rates with zero premature rounding and full quota protection.', 'wpcalibrate-gold-rate-display' ); ?>
						</p>
						<div class="wpcalibrate-hero-actions">
							<a href="<?php echo esc_url( $api_tab_url ); ?>" class="button button-primary button-hero">
								<?php esc_html_e( 'Configure API Key', 'wpcalibrate-gold-rate-display' ); ?>
							</a>
							<a href="<?php echo esc_url( $dashboard_tab_url ); ?>" class="button button-secondary button-hero">
								<?php esc_html_e( 'View Live Dashboard', 'wpcalibrate-gold-rate-display' ); ?>
							</a>
							<a href="<?php echo esc_url( $display_tab_url ); ?>" class="button button-secondary button-hero">
								<?php esc_html_e( 'Customize Display', 'wpcalibrate-gold-rate-display' ); ?>
							</a>
						</div>
					</div>
					<div class="wpcalibrate-overview-hero-badge">
						<span class="wpcalibrate-hero-pill"><?php esc_html_e( 'Gold / XAU Engine', 'wpcalibrate-gold-rate-display' ); ?></span>
						<span class="wpcalibrate-hero-pill"><?php esc_html_e( 'Troy Oz • Gram • Tola', 'wpcalibrate-gold-rate-display' ); ?></span>
						<span class="wpcalibrate-hero-pill"><?php esc_html_e( 'WCAG 2.2 AA Accessible', 'wpcalibrate-gold-rate-display' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Core Architecture & Methodology -->
			<div class="wpcalibrate-card">
				<div class="wpcalibrate-card-header">
					<h2><?php esc_html_e( 'Plugin Purpose & Operational Method', 'wpcalibrate-gold-rate-display' ); ?></h2>
				</div>
				<div class="wpcalibrate-card-body">
					<p class="wpcalibrate-card-intro">
						<?php esc_html_e( 'Designed for jewellery businesses, bullion traders, currency exchanges, and financial portals, the plugin bridges external financial data with lightweight, accessible frontend components:', 'wpcalibrate-gold-rate-display' ); ?>
					</p>

					<div class="wpcalibrate-overview-pillars">
						<div class="wpcalibrate-pillar-box">
							<div class="wpcalibrate-pillar-icon" aria-hidden="true">🪙</div>
							<h3><?php esc_html_e( 'Live Market Ingestion', 'wpcalibrate-gold-rate-display' ); ?></h3>
							<p><?php esc_html_e( 'Securely connects to MetalpriceAPI using HTTPS and X-API-KEY headers. Supports USD as well as international currencies (EUR, GBP, PKR, INR, AED, SAR, CAD, AUD, etc.) through intelligent cross-currency conversion.', 'wpcalibrate-gold-rate-display' ); ?></p>
						</div>

						<div class="wpcalibrate-pillar-box">
							<div class="wpcalibrate-pillar-icon" aria-hidden="true">⚖️</div>
							<h3><?php esc_html_e( 'Precise Physical Conversions', 'wpcalibrate-gold-rate-display' ); ?></h3>
							<p><?php esc_html_e( 'Calculates Karats using standard purity fractions (24/24, 22/24, 21/24, 18/24) and exact international constants: 1 Troy Ounce = 31.1034768 grams, and 1 Tola = 11.6638038 grams without premature rounding.', 'wpcalibrate-gold-rate-display' ); ?></p>
						</div>

						<div class="wpcalibrate-pillar-box">
							<div class="wpcalibrate-pillar-icon" aria-hidden="true">🛡️</div>
							<h3><?php esc_html_e( 'Anti-Stampede Quota Shield', 'wpcalibrate-gold-rate-display' ); ?></h3>
							<p><?php esc_html_e( 'Site visitors never trigger direct external API calls. Transients cache rates for your configured interval (1–60 mins) and locking mechanisms prevent simultaneous requests. During downtime, last-known-good rates display seamlessly.', 'wpcalibrate-gold-rate-display' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<!-- 4-Step Guided Workflow -->
			<div class="wpcalibrate-card">
				<div class="wpcalibrate-card-header">
					<h2><?php esc_html_e( 'Guided Setup Workflow (How to Use)', 'wpcalibrate-gold-rate-display' ); ?></h2>
				</div>
				<div class="wpcalibrate-card-body">
					<div class="wpcalibrate-workflow-steps">
						<div class="wpcalibrate-step-item">
							<div class="wpcalibrate-step-number">1</div>
							<div class="wpcalibrate-step-content">
								<h4><?php esc_html_e( 'Obtain Your MetalpriceAPI Key', 'wpcalibrate-gold-rate-display' ); ?></h4>
								<p><?php esc_html_e( 'Sign up at metalpriceapi.com to receive your unique API access token. Both free and commercial plans are fully compatible.', 'wpcalibrate-gold-rate-display' ); ?></p>
							</div>
						</div>

						<div class="wpcalibrate-step-item">
							<div class="wpcalibrate-step-number">2</div>
							<div class="wpcalibrate-step-content">
								<h4><?php esc_html_e( 'Connect & Verify Connection', 'wpcalibrate-gold-rate-display' ); ?></h4>
								<p><?php esc_html_e( 'Go to API Settings, paste your key, and click "Test Connection". The plugin verifies your key via the zero-cost usage endpoint and displays your monthly quota.', 'wpcalibrate-gold-rate-display' ); ?></p>
							</div>
						</div>

						<div class="wpcalibrate-step-item">
							<div class="wpcalibrate-step-number">3</div>
							<div class="wpcalibrate-step-content">
								<h4><?php esc_html_e( 'Select Defaults & Units', 'wpcalibrate-gold-rate-display' ); ?></h4>
								<p><?php esc_html_e( 'Under Display, pick your default currency (e.g. USD, EUR, AED, PKR), weight unit (Gram, Troy Ounce, or Tola), and preferred presentation layout.', 'wpcalibrate-gold-rate-display' ); ?></p>
							</div>
						</div>

						<div class="wpcalibrate-step-item">
							<div class="wpcalibrate-step-number">4</div>
							<div class="wpcalibrate-step-content">
								<h4><?php esc_html_e( 'Embed on Frontend', 'wpcalibrate-gold-rate-display' ); ?></h4>
								<p><?php esc_html_e( 'Add the [wpcalibrate_gold_rate] shortcode or the visual Gutenberg block to any page, post, widget area, or Elementor shortcode block.', 'wpcalibrate-gold-rate-display' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Presentation Layouts & Shortcodes -->
			<div class="wpcalibrate-card">
				<div class="wpcalibrate-card-header">
					<h2><?php esc_html_e( 'Supported Display Layouts', 'wpcalibrate-gold-rate-display' ); ?></h2>
				</div>
				<div class="wpcalibrate-card-body">
					<div class="wpcalibrate-layouts-grid">
						<div class="wpcalibrate-layout-card">
							<span class="wpcalibrate-layout-badge"><?php esc_html_e( 'Default Layout', 'wpcalibrate-gold-rate-display' ); ?></span>
							<h3><?php esc_html_e( 'Cards Grid (layout="cards")', 'wpcalibrate-gold-rate-display' ); ?></h3>
							<p><?php esc_html_e( 'Responsive modern cards showing spot rate hero, karat title, purity (parts per 1000), and rate with hover elevation. Adapts cleanly from desktop down to 320px mobile screens.', 'wpcalibrate-gold-rate-display' ); ?></p>
							<code>[wpcalibrate_gold_rate layout="cards"]</code>
						</div>

						<div class="wpcalibrate-layout-card">
							<span class="wpcalibrate-layout-badge"><?php esc_html_e( 'Financial Data', 'wpcalibrate-gold-rate-display' ); ?></span>
							<h3><?php esc_html_e( 'Structured Table (layout="table")', 'wpcalibrate-gold-rate-display' ); ?></h3>
							<p><?php esc_html_e( 'Semantic HTML table featuring accessible caption, thead, tbody, and column scopes. Perfect for gold dealer rate boards and formal financial comparison pages.', 'wpcalibrate-gold-rate-display' ); ?></p>
							<code>[wpcalibrate_gold_rate layout="table" currency="AED" unit="gram"]</code>
						</div>

						<div class="wpcalibrate-layout-card">
							<span class="wpcalibrate-layout-badge"><?php esc_html_e( 'Header / Ribbon', 'wpcalibrate-gold-rate-display' ); ?></span>
							<h3><?php esc_html_e( 'Compact Ribbon (layout="compact")', 'wpcalibrate-gold-rate-display' ); ?></h3>
							<p><?php esc_html_e( 'Fluid horizontal pill ribbon displaying karat badges and inline prices. Ideal for top notification bars, announcement headers, or sidebar sidebars.', 'wpcalibrate-gold-rate-display' ); ?></p>
							<code>[wpcalibrate_gold_rate layout="compact" currency="PKR" unit="tola"]</code>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Dashboard Tab.
	 */
	private static function render_tab_dashboard( array $settings, array $credentials, string $ajax_nonce ): void {
		$currency     = $settings['currency'] ?? 'USD';
		$unit         = $settings['unit'] ?? 'gram';
		$has_key      = CredentialService::has_api_key();
		$rate_service = new RateService();
		$rate_data    = $rate_service->get_rate( $currency );

		$price_formatted = '—';
		$last_sync_text  = __( 'Never', 'wpcalibrate-gold-rate-display' );
		$status_label    = __( 'Not Synced', 'wpcalibrate-gold-rate-display' );
		$status_class    = 'status-neutral';

		if ( ! is_wp_error( $rate_data ) ) {
			$unit_price      = $rate_data->get_spot_price_for_unit( $unit );
			$price_formatted = CurrencyService::format_price( $unit_price, $currency, (int) ( $settings['decimals'] ?? 2 ) );
			$last_sync_text  = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rate_data->get_last_synced_at() );

			if ( GoldRateData::STATUS_FRESH === $rate_data->get_status() ) {
				$status_label = __( 'Fresh (Cached)', 'wpcalibrate-gold-rate-display' );
				$status_class = 'status-success';
			} else {
				$status_label = __( 'Stale / Fallback Data', 'wpcalibrate-gold-rate-display' );
				$status_class = 'status-warning';
			}
		} elseif ( $has_key ) {
			$status_label = sprintf(
				/* translators: %s: error message */
				__( 'Sync Error: %s', 'wpcalibrate-gold-rate-display' ),
				$rate_data->get_error_message()
			);
			$status_class = 'status-error';
		}

		?>
		<div class="wpcalibrate-grd-dashboard-grid">
			<!-- Live Stats Card -->
			<div class="wpcalibrate-card">
				<div class="wpcalibrate-card-header">
					<h2><?php esc_html_e( 'Current Spot Rate Overview', 'wpcalibrate-gold-rate-display' ); ?></h2>
					<span class="wpcalibrate-badge <?php echo esc_attr( $status_class ); ?>" id="dashboard-status-badge">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</div>
				<div class="wpcalibrate-card-body">
					<div class="wpcalibrate-stat-hero">
						<span class="wpcalibrate-stat-hero-label"><?php echo esc_html( sprintf( __( 'Gold Price (%1$s per %2$s)', 'wpcalibrate-gold-rate-display' ), $currency, UnitConverter::get_unit_label( $unit ) ) ); ?></span>
						<span class="wpcalibrate-stat-hero-value" id="dashboard-live-price"><?php echo esc_html( $price_formatted ); ?></span>
					</div>

					<dl class="wpcalibrate-details-list">
						<div class="wpcalibrate-details-item">
							<dt><?php esc_html_e( 'Selected Currency', 'wpcalibrate-gold-rate-display' ); ?></dt>
							<dd><?php echo esc_html( $currency ); ?> (<?php echo esc_html( CurrencyService::get_symbol( $currency ) ); ?>)</dd>
						</div>
						<div class="wpcalibrate-details-item">
							<dt><?php esc_html_e( 'Weight Unit', 'wpcalibrate-gold-rate-display' ); ?></dt>
							<dd><?php echo esc_html( UnitConverter::get_unit_label( $unit ) ); ?></dd>
						</div>
						<div class="wpcalibrate-details-item">
							<dt><?php esc_html_e( 'Last Successful API Sync', 'wpcalibrate-gold-rate-display' ); ?></dt>
							<dd id="dashboard-last-sync"><?php echo esc_html( $last_sync_text ); ?></dd>
						</div>
						<div class="wpcalibrate-details-item">
							<dt><?php esc_html_e( 'Configured Refresh Interval', 'wpcalibrate-gold-rate-display' ); ?></dt>
							<dd><?php echo esc_html( sprintf( __( '%d Minutes', 'wpcalibrate-gold-rate-display' ), (int) ( $settings['refresh_interval'] ?? 15 ) ) ); ?></dd>
						</div>
						<?php if ( null !== $credentials['quota_used'] && null !== $credentials['quota_total'] ) : ?>
							<div class="wpcalibrate-details-item">
								<dt><?php esc_html_e( 'API Quota Consumed', 'wpcalibrate-gold-rate-display' ); ?></dt>
								<dd><?php echo esc_html( sprintf( '%d / %d calls', $credentials['quota_used'], $credentials['quota_total'] ) ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>

					<div class="wpcalibrate-card-actions">
						<form method="post" action="" style="display:inline-block;">
							<?php wp_nonce_field( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' ); ?>
							<input type="hidden" name="wpcalibrate_grd_action" value="manual_refresh" />
							<input type="hidden" name="current_tab" value="dashboard" />
							<button type="button" class="button button-primary" id="btn-manual-refresh" data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>">
								<?php esc_html_e( 'Refresh Rates Now', 'wpcalibrate-gold-rate-display' ); ?>
							</button>
						</form>
						<span id="manual-refresh-status" class="wpcalibrate-inline-status" aria-live="polite"></span>
					</div>
				</div>
			</div>

			<!-- Quick Usage & Shortcodes Card -->
			<div class="wpcalibrate-card">
				<div class="wpcalibrate-card-header">
					<h2><?php esc_html_e( 'Display Anywhere On Your Site', 'wpcalibrate-gold-rate-display' ); ?></h2>
				</div>
				<div class="wpcalibrate-card-body">
					<p><?php esc_html_e( 'You can embed live gold rate widgets in any page, post, widget area, or Elementor shortcode block.', 'wpcalibrate-gold-rate-display' ); ?></p>

					<div class="wpcalibrate-code-snippet">
						<label for="sc-default"><strong><?php esc_html_e( 'Default Shortcode (Uses Global Settings):', 'wpcalibrate-gold-rate-display' ); ?></strong></label>
						<code>[wpcalibrate_gold_rate]</code>
					</div>

					<div class="wpcalibrate-code-snippet">
						<label><strong><?php esc_html_e( 'Table Layout Example:', 'wpcalibrate-gold-rate-display' ); ?></strong></label>
						<code>[wpcalibrate_gold_rate layout="table" currency="USD" unit="gram"]</code>
					</div>

					<div class="wpcalibrate-code-snippet">
						<label><strong><?php esc_html_e( 'Compact Ticker Example:', 'wpcalibrate-gold-rate-display' ); ?></strong></label>
						<code>[wpcalibrate_gold_rate layout="compact" currency="EUR" unit="tola"]</code>
					</div>

					<div class="wpcalibrate-notice-tip">
						<p><strong><?php esc_html_e( 'Gutenberg Block:', 'wpcalibrate-gold-rate-display' ); ?></strong> <?php esc_html_e( 'In the block editor, search for "Gold Rate" to insert the visual block with live sidebar inspector controls.', 'wpcalibrate-gold-rate-display' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * API Tab.
	 */
	private static function render_tab_api( array $credentials, string $ajax_nonce ): void {
		$masked_key = CredentialService::get_masked_key();
		$status     = $credentials['status'] ?? 'untested';
		$last_error = $credentials['last_error'] ?? '';

		?>
		<div class="wpcalibrate-card wpcalibrate-form-card">
			<div class="wpcalibrate-card-header">
				<h2><?php esc_html_e( 'MetalpriceAPI Connection Settings', 'wpcalibrate-gold-rate-display' ); ?></h2>
			</div>
			<div class="wpcalibrate-card-body">
				<p>
					<?php
					echo sprintf(
						/* translators: %s: external link */
						esc_html__( 'This plugin requires an official MetalpriceAPI key to retrieve spot precious metal exchange rates. If you do not have an account, obtain your API key at %s.', 'wpcalibrate-gold-rate-display' ),
						'<a href="https://metalpriceapi.com/" target="_blank" rel="noopener noreferrer">https://metalpriceapi.com/</a>'
					);
					?>
				</p>

				<form method="post" action="">
					<?php wp_nonce_field( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' ); ?>
					<input type="hidden" name="wpcalibrate_grd_action" value="save_api_key" />
					<input type="hidden" name="current_tab" value="api" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wpcalibrate_api_key"><?php esc_html_e( 'MetalpriceAPI Key', 'wpcalibrate-gold-rate-display' ); ?></label>
							</th>
							<td>
								<input type="password"
									   id="wpcalibrate_api_key"
									   name="api_key"
									   value=""
									   placeholder="<?php echo esc_attr( '' !== $masked_key ? $masked_key : __( 'Enter your API key here', 'wpcalibrate-gold-rate-display' ) ); ?>"
									   class="regular-text"
									   autocomplete="off" />
								<p class="description">
									<?php
									if ( '' !== $masked_key ) {
										echo sprintf(
											/* translators: %s: masked key */
											esc_html__( 'Active stored key: %s. To update, enter a new key above.', 'wpcalibrate-gold-rate-display' ),
											'<code>' . esc_html( $masked_key ) . '</code>'
										);
									} else {
										esc_html_e( 'Your API key is never exposed on frontend pages, REST responses, or public source code.', 'wpcalibrate-gold-rate-display' );
									}
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Connection Status', 'wpcalibrate-gold-rate-display' ); ?></th>
							<td>
								<div id="api-connection-status-container">
									<?php if ( 'valid' === $status ) : ?>
										<span class="wpcalibrate-badge status-success"><?php esc_html_e( 'Connected & Valid', 'wpcalibrate-gold-rate-display' ); ?></span>
									<?php elseif ( 'invalid' === $status ) : ?>
										<span class="wpcalibrate-badge status-error"><?php esc_html_e( 'Invalid API Key', 'wpcalibrate-gold-rate-display' ); ?></span>
									<?php elseif ( 'quota_exceeded' === $status ) : ?>
										<span class="wpcalibrate-badge status-warning"><?php esc_html_e( 'Quota Limit Reached', 'wpcalibrate-gold-rate-display' ); ?></span>
									<?php else : ?>
										<span class="wpcalibrate-badge status-neutral"><?php esc_html_e( 'Untested', 'wpcalibrate-gold-rate-display' ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( '' !== $last_error ) : ?>
									<p class="wpcalibrate-field-error"><?php echo esc_html( $last_error ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( null !== $credentials['quota_used'] && null !== $credentials['quota_total'] ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Monthly Quota', 'wpcalibrate-gold-rate-display' ); ?></th>
								<td>
									<p>
										<strong><?php echo esc_html( (string) $credentials['quota_used'] ); ?></strong> / <strong><?php echo esc_html( (string) $credentials['quota_total'] ); ?></strong> <?php esc_html_e( 'requests consumed this cycle.', 'wpcalibrate-gold-rate-display' ); ?>
										(<?php echo esc_html( (string) $credentials['quota_remaining'] ); ?> <?php esc_html_e( 'remaining', 'wpcalibrate-gold-rate-display' ); ?>)
									</p>
								</td>
							</tr>
						<?php endif; ?>
					</table>

					<div class="wpcalibrate-form-actions">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save API Key', 'wpcalibrate-gold-rate-display' ); ?>
						</button>

						<button type="button" class="button button-secondary" id="btn-test-connection" data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>">
							<?php esc_html_e( 'Test Connection', 'wpcalibrate-gold-rate-display' ); ?>
						</button>

						<span id="test-connection-status" class="wpcalibrate-inline-status" aria-live="polite"></span>
					</div>
				</form>

				<?php if ( '' !== $masked_key ) : ?>
					<hr style="margin: 24px 0 16px 0;" />
					<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove your stored API key?', 'wpcalibrate-gold-rate-display' ) ); ?>');">
						<?php wp_nonce_field( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' ); ?>
						<input type="hidden" name="wpcalibrate_grd_action" value="clear_api_key" />
						<input type="hidden" name="current_tab" value="api" />
						<button type="submit" class="button button-link-delete">
							<?php esc_html_e( 'Remove Stored API Key', 'wpcalibrate-gold-rate-display' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Display Tab.
	 */
	private static function render_tab_display( array $settings ): void {
		$supported_currencies = CurrencyService::get_supported_currencies();
		$supported_units      = UnitConverter::get_supported_units();
		$supported_karats     = KaratCalculator::get_supported_karats();
		$configured_karats    = $settings['karats'] ?? array( '24K', '22K', '21K', '18K' );

		?>
		<div class="wpcalibrate-card wpcalibrate-form-card">
			<div class="wpcalibrate-card-header">
				<h2><?php esc_html_e( 'Default Presentation Settings', 'wpcalibrate-gold-rate-display' ); ?></h2>
			</div>
			<div class="wpcalibrate-card-body">
				<form method="post" action="">
					<?php wp_nonce_field( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' ); ?>
					<input type="hidden" name="wpcalibrate_grd_action" value="save_settings" />
					<input type="hidden" name="current_tab" value="display" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="setting_currency"><?php esc_html_e( 'Default Currency', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<select id="setting_currency" name="settings[currency]">
									<?php foreach ( $supported_currencies as $code => $meta ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['currency'], $code ); ?>>
											<?php echo esc_html( sprintf( '%s (%s - %s)', $code, $meta['symbol'], $meta['name'] ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="setting_unit"><?php esc_html_e( 'Default Weight Unit', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<select id="setting_unit" name="settings[unit]">
									<?php foreach ( $supported_units as $key => $meta ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['unit'], $key ); ?>>
											<?php echo esc_html( sprintf( '%s (%s)', $meta['label'], $meta['symbol'] ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="setting_layout"><?php esc_html_e( 'Default Display Layout', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<select id="setting_layout" name="settings[layout]">
									<option value="cards" <?php selected( $settings['layout'], 'cards' ); ?>><?php esc_html_e( 'Cards (Visual Grid)', 'wpcalibrate-gold-rate-display' ); ?></option>
									<option value="table" <?php selected( $settings['layout'], 'table' ); ?>><?php esc_html_e( 'Table (Structured Data)', 'wpcalibrate-gold-rate-display' ); ?></option>
									<option value="compact" <?php selected( $settings['layout'], 'compact' ); ?>><?php esc_html_e( 'Compact (Inline Strip)', 'wpcalibrate-gold-rate-display' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Enabled Karat Columns', 'wpcalibrate-gold-rate-display' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Select Karats to Display', 'wpcalibrate-gold-rate-display' ); ?></legend>
									<?php foreach ( $supported_karats as $karat ) : ?>
										<label style="margin-right: 16px;">
											<input type="checkbox"
												   name="settings[karats][]"
												   value="<?php echo esc_attr( $karat ); ?>"
												   <?php checked( in_array( $karat, $configured_karats, true ) ); ?> />
											<strong><?php echo esc_html( $karat ); ?></strong> (<?php echo esc_html( KaratCalculator::PURITY_FINENESS[ $karat ] ?? '' ); ?>/1000)
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="setting_refresh_interval"><?php esc_html_e( 'Refresh Interval', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<select id="setting_refresh_interval" name="settings[refresh_interval]">
									<?php foreach ( SettingsService::ALLOWED_REFRESH_INTERVALS as $mins ) : ?>
										<option value="<?php echo esc_attr( (string) $mins ); ?>" <?php selected( (int) $settings['refresh_interval'], $mins ); ?>>
											<?php echo esc_html( sprintf( _n( '%d Minute', '%d Minutes', $mins, 'wpcalibrate-gold-rate-display' ), $mins ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'How frequently server-side cache refreshes. Note: Actual freshness is governed by your MetalpriceAPI plan limits and WordPress cron traffic.', 'wpcalibrate-gold-rate-display' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="setting_decimals"><?php esc_html_e( 'Decimal Places', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<input type="number" id="setting_decimals" name="settings[decimals]" min="0" max="4" value="<?php echo esc_attr( (string) $settings['decimals'] ); ?>" class="small-text" />
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Display Elements', 'wpcalibrate-gold-rate-display' ); ?></th>
							<td>
								<fieldset>
									<label><input type="checkbox" name="settings[show_spot]" value="1" <?php checked( ! empty( $settings['show_spot'] ) ); ?> /> <?php esc_html_e( 'Show Spot Price Header', 'wpcalibrate-gold-rate-display' ); ?></label><br/>
									<label><input type="checkbox" name="settings[show_change]" value="1" <?php checked( ! empty( $settings['show_change'] ) ); ?> /> <?php esc_html_e( 'Show Price Change Amount', 'wpcalibrate-gold-rate-display' ); ?></label><br/>
									<label><input type="checkbox" name="settings[show_percent_change]" value="1" <?php checked( ! empty( $settings['show_percent_change'] ) ); ?> /> <?php esc_html_e( 'Show Percentage Change', 'wpcalibrate-gold-rate-display' ); ?></label><br/>
									<label><input type="checkbox" name="settings[show_updated]" value="1" <?php checked( ! empty( $settings['show_updated'] ) ); ?> /> <?php esc_html_e( 'Show Last Updated Timestamp', 'wpcalibrate-gold-rate-display' ); ?></label><br/>
									<label><input type="checkbox" name="settings[show_stale_notice]" value="1" <?php checked( ! empty( $settings['show_stale_notice'] ) ); ?> /> <?php esc_html_e( 'Show Fallback / Stale Data Indicator when provider is unavailable', 'wpcalibrate-gold-rate-display' ); ?></label><br/>
									<label><input type="checkbox" name="settings[frontend_auto_refresh]" value="1" <?php checked( ! empty( $settings['frontend_auto_refresh'] ) ); ?> /> <?php esc_html_e( 'Enable Client-Side Auto-Refresh (queries local cached REST endpoint only)', 'wpcalibrate-gold-rate-display' ); ?></label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="setting_heading"><?php esc_html_e( 'Custom Heading (Optional)', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<input type="text" id="setting_heading" name="settings[heading]" value="<?php echo esc_attr( $settings['heading'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Live Gold Rates', 'wpcalibrate-gold-rate-display' ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="setting_disclaimer"><?php esc_html_e( 'Frontend Disclaimer', 'wpcalibrate-gold-rate-display' ); ?></label></th>
							<td>
								<textarea id="setting_disclaimer" name="settings[disclaimer]" rows="3" class="large-text"><?php echo esc_textarea( $settings['disclaimer'] ?? '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Displays in compact font below the rates. Recommended to clarify that retail jewellery prices may differ.', 'wpcalibrate-gold-rate-display' ); ?></p>
							</td>
						</tr>
					</table>

					<div class="wpcalibrate-form-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Display Settings', 'wpcalibrate-gold-rate-display' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Advanced Tab.
	 */
	private static function render_tab_advanced( array $settings ): void {
		?>
		<div class="wpcalibrate-card wpcalibrate-form-card">
			<div class="wpcalibrate-card-header">
				<h2><?php esc_html_e( 'Advanced & Cache Controls', 'wpcalibrate-gold-rate-display' ); ?></h2>
			</div>
			<div class="wpcalibrate-card-body">
				<form method="post" action="">
					<?php wp_nonce_field( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' ); ?>
					<input type="hidden" name="wpcalibrate_grd_action" value="save_settings" />
					<input type="hidden" name="current_tab" value="advanced" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Debug Logging', 'wpcalibrate-gold-rate-display' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="settings[debug_logging]" value="1" <?php checked( ! empty( $settings['debug_logging'] ) ); ?> />
									<?php esc_html_e( 'Enable Diagnostic Debug Logging', 'wpcalibrate-gold-rate-display' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Writes API error codes, cache misses, and sync events to error_log when WP_DEBUG is enabled. API keys and secrets are strictly redacted.', 'wpcalibrate-gold-rate-display' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Data Retention on Uninstall', 'wpcalibrate-gold-rate-display' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="settings[delete_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
									<?php esc_html_e( 'Delete WPCalibrate Gold Rate Display data on plugin uninstall', 'wpcalibrate-gold-rate-display' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When checked, completely purges all settings, rate caches, and credentials upon uninstall. By default, configuration is preserved.', 'wpcalibrate-gold-rate-display' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="wpcalibrate-form-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Advanced Settings', 'wpcalibrate-gold-rate-display' ); ?></button>
					</div>
				</form>

				<hr style="margin: 24px 0;" />

				<h3><?php esc_html_e( 'Rate Cache Management', 'wpcalibrate-gold-rate-display' ); ?></h3>
				<p><?php esc_html_e( 'Manually flush all cached rates and temporary sync locks across all supported currencies.', 'wpcalibrate-gold-rate-display' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'wpcalibrate_grd_settings_nonce', 'wpcalibrate_grd_nonce' ); ?>
					<input type="hidden" name="wpcalibrate_grd_action" value="clear_cache" />
					<input type="hidden" name="current_tab" value="advanced" />
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear All Cached Rates', 'wpcalibrate-gold-rate-display' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * License Tab.
	 */
	private static function render_tab_license(): void {
		$license_service = new PendingLicenseService();
		$masked_key      = $license_service->get_masked_license_key();

		?>
		<div class="wpcalibrate-card wpcalibrate-form-card">
			<div class="wpcalibrate-card-header">
				<h2><?php esc_html_e( 'Plugin License Management', 'wpcalibrate-gold-rate-display' ); ?></h2>
				<span class="wpcalibrate-badge status-neutral"><?php esc_html_e( 'Not Configured', 'wpcalibrate-gold-rate-display' ); ?></span>
			</div>
			<div class="wpcalibrate-card-body">
				<p>
					<?php esc_html_e( 'Commercial WPCalibrate license activation is maintained separately from your MetalpriceAPI data subscription key. All core display features in version 1.0.0 operate without requiring commercial license activation.', 'wpcalibrate-gold-rate-display' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpcalibrate_license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-gold-rate-display' ); ?></label></th>
						<td>
							<input type="password" id="wpcalibrate_license_key" class="regular-text" placeholder="<?php echo esc_attr( '' !== $masked_key ? $masked_key : 'XXXX-XXXX-XXXX-XXXX' ); ?>" disabled />
							<p class="description"><?php esc_html_e( 'Licensing server adapter is pending future release. No external license activation calls are made.', 'wpcalibrate-gold-rate-display' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'License Status', 'wpcalibrate-gold-rate-display' ); ?></th>
						<td>
							<span class="wpcalibrate-badge status-neutral"><?php esc_html_e( 'Core Standalone (No Active License Required)', 'wpcalibrate-gold-rate-display' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Support & Renewals', 'wpcalibrate-gold-rate-display' ); ?></th>
						<td>
							<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary">
								<?php esc_html_e( 'Visit WPCalibrate Marketplace', 'wpcalibrate-gold-rate-display' ); ?>
							</a>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Support Tab.
	 */
	private static function render_tab_support(): void {
		$details = SupportService::get_details();

		?>
		<div class="wpcalibrate-card">
			<div class="wpcalibrate-card-header">
				<h2><?php esc_html_e( 'WPCalibrate Product Support & Contact', 'wpcalibrate-gold-rate-display' ); ?></h2>
			</div>
			<div class="wpcalibrate-card-body">
				<p><?php esc_html_e( 'Need help configuring your MetalpriceAPI key, shortcode layouts, or have a feature request? Contact the official WPCalibrate engineering team:', 'wpcalibrate-gold-rate-display' ); ?></p>

				<div class="wpcalibrate-support-grid">
					<div class="wpcalibrate-support-box">
						<h3><?php esc_html_e( 'Direct Email Support', 'wpcalibrate-gold-rate-display' ); ?></h3>
						<p><a href="<?php echo esc_url( 'mailto:' . $details['email'] ); ?>"><?php echo esc_html( $details['email'] ); ?></a></p>
					</div>

					<div class="wpcalibrate-support-box">
						<h3><?php esc_html_e( 'WhatsApp & Phone', 'wpcalibrate-gold-rate-display' ); ?></h3>
						<p><a href="<?php echo esc_url( $details['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $details['phone'] ); ?></a></p>
					</div>

					<div class="wpcalibrate-support-box">
						<h3><?php esc_html_e( 'Official Website', 'wpcalibrate-gold-rate-display' ); ?></h3>
						<p><a href="<?php echo esc_url( $details['website'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $details['website'] ); ?></a></p>
					</div>

					<div class="wpcalibrate-support-box">
						<h3><?php esc_html_e( 'Add-ons Marketplace', 'wpcalibrate-gold-rate-display' ); ?></h3>
						<p><a href="<?php echo esc_url( $details['marketplace'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $details['marketplace'] ); ?></a></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
