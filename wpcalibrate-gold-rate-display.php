<?php
/**
 * Plugin Name:       WPCalibrate Gold Rate Display
 * Plugin URI:        https://wpcalibrate.com/
 * Description:       Display live spot gold rates and 24K, 22K, 21K, and 18K karat prices with MetalpriceAPI integration, smart caching, and responsive frontend displays.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Tested up to:      7.1.3
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-gold-rate-display
 * Domain Path:       /languages
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin identity constants.
define( 'WPCALIBRATE_GRD_VERSION', '1.0.0' );
define( 'WPCALIBRATE_GRD_MIN_PHP_VERSION', '8.2' );
define( 'WPCALIBRATE_GRD_FILE', __FILE__ );
define( 'WPCALIBRATE_GRD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_GRD_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_GRD_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check minimum PHP version compatibility before bootstrap.
 */
if ( version_compare( PHP_VERSION, WPCALIBRATE_GRD_MIN_PHP_VERSION, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Current PHP version, 2: Required PHP version */
							__( 'WPCalibrate Gold Rate Display requires PHP version %2$s or higher. Your server is currently running PHP %1$s.', 'wpcalibrate-gold-rate-display' ),
							PHP_VERSION,
							WPCALIBRATE_GRD_MIN_PHP_VERSION
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

// Register autoloader.
require_once WPCALIBRATE_GRD_PATH . 'includes/Autoloader.php';
\WPCalibrate\GoldRateDisplay\Autoloader::register( WPCALIBRATE_GRD_PATH . 'includes' );

// Activation, deactivation, and bootstrap hooks.
register_activation_hook( __FILE__, array( \WPCalibrate\GoldRateDisplay\Lifecycle\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPCalibrate\GoldRateDisplay\Lifecycle\Deactivator::class, 'deactivate' ) );

/**
 * Bootstrap the plugin.
 */
function wpcalibrate_gold_rate_display_init(): void {
	\WPCalibrate\GoldRateDisplay\Plugin::get_instance()->run();
}
add_action( 'plugins_loaded', 'wpcalibrate_gold_rate_display_init' );
