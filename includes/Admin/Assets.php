<?php
/**
 * Admin Assets Enqueue Manager.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Admin;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles scoped CSS and JS enqueueing for admin screens.
 */
final class Assets {

	/**
	 * Register asset hooks.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( $this, 'inject_menu_icon_styles' ) );
	}

	/**
	 * Enqueue admin styles and scripts strictly on this plugin's settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, AdminMenu::PAGE_SLUG ) && ! str_contains( $hook_suffix, AdminMenu::PARENT_SLUG ) ) {
			return;
		}

		// Admin CSS.
		wp_enqueue_style(
			'wpcalibrate-grd-admin',
			WPCALIBRATE_GRD_URL . 'assets/css/admin.css',
			array(),
			WPCALIBRATE_GRD_VERSION
		);

		// Admin JS.
		wp_enqueue_script(
			'wpcalibrate-grd-admin',
			WPCALIBRATE_GRD_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WPCALIBRATE_GRD_VERSION,
			true
		);

		wp_localize_script(
			'wpcalibrate-grd-admin',
			'wpcalibrateGrdAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'wpcalibrate_grd_ajax_nonce' ),
				'strings'   => array(
					'testing'   => __( 'Testing connection...', 'wpcalibrate-gold-rate-display' ),
					'refreshing' => __( 'Refreshing rates...', 'wpcalibrate-gold-rate-display' ),
				),
			)
		);
	}

	/**
	 * Inject menu icon styles for the shared WPCalibrate top-level menu.
	 * Uses CSS mask on icon-white.png so icon inherits native WordPress dashboard theme colors.
	 */
	public function inject_menu_icon_styles(): void {
		$icon_url = WPCALIBRATE_GRD_URL . 'branding/icon-white.png';
		?>
		<style type="text/css">
			#adminmenu #toplevel_page_wpcalibrate div.wp-menu-image:before {
				content: '' !important;
				display: inline-block !important;
				width: 20px !important;
				height: 20px !important;
				background-color: currentColor !important;
				-webkit-mask-image: url('<?php echo esc_url( $icon_url ); ?>') !important;
				-webkit-mask-size: 18px 18px !important;
				-webkit-mask-repeat: no-repeat !important;
				-webkit-mask-position: center center !important;
				mask-image: url('<?php echo esc_url( $icon_url ); ?>') !important;
				mask-size: 18px 18px !important;
				mask-repeat: no-repeat !important;
				mask-position: center center !important;
				vertical-align: middle !important;
			}
		</style>
		<?php
	}
}
