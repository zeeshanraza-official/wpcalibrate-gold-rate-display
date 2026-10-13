<?php
/**
 * PSR-4 Autoloader for WPCalibrate Gold Rate Display.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Autoloader class.
 */
final class Autoloader {

	/**
	 * Namespace prefix to match.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'WPCalibrate\\GoldRateDisplay\\';

	/**
	 * Base directory for namespace classes.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Base directory path (points to includes/).
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload a class by mapping its FQCN to file path.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		$file           = self::$base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
