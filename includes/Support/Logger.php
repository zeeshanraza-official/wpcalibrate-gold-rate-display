<?php
/**
 * Diagnostic logger for WPCalibrate Gold Rate Display.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Support;

use WPCalibrate\GoldRateDisplay\Service\SettingsService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Diagnostic Logger.
 * Strict security rule: Never logs API keys, license keys, or Authorization/X-API-KEY headers.
 */
final class Logger {

	/**
	 * Log a message to debug log if logging is enabled.
	 *
	 * @param string               $level   Log level (debug, info, warning, error).
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional contextual data (secrets will be masked).
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		$settings = SettingsService::get_settings();
		if ( empty( $settings['debug_logging'] ) ) {
			return;
		}

		$safe_context = self::sanitize_context( $context );
		$context_str  = ! empty( $safe_context ) ? ' | Context: ' . wp_json_encode( $safe_context ) : '';
		$log_entry    = sprintf( '[WPCalibrate Gold Rate Display] [%s] %s%s', strtoupper( $level ), $message, $context_str );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			error_log( $log_entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Mask sensitive credentials or secrets in context before writing to logs.
	 *
	 * @param array<string, mixed> $context Context array.
	 * @return array<string, mixed> Sanitized context.
	 */
	private static function sanitize_context( array $context ): array {
		$sanitized = array();
		$sensitive_keys = array( 'api_key', 'key', 'license_key', 'x-api-key', 'authorization', 'token', 'secret' );

		foreach ( $context as $k => $v ) {
			$lower_key = strtolower( (string) $k );
			if ( in_array( $lower_key, $sensitive_keys, true ) ) {
				$sanitized[ $k ] = '***REDACTED***';
				continue;
			}

			if ( is_array( $v ) ) {
				$sanitized[ $k ] = self::sanitize_context( $v );
			} elseif ( is_string( $v ) ) {
				// Prevent secret exposure in URLs or query strings.
				$sanitized[ $k ] = preg_replace( '/([?&](api_key|key|token)=)[^&]+/i', '$1***REDACTED***', $v );
			} else {
				$sanitized[ $k ] = $v;
			}
		}

		return $sanitized;
	}
}
