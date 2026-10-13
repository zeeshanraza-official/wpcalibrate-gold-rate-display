<?php
/**
 * Uninstall routine for WPCalibrate Gold Rate Display.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

// Prevent direct execution or execution outside WordPress uninstall workflow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wpcalibrate_grd_settings', array() );
$delete_data = ! empty( $settings['delete_on_uninstall'] );

// If administrator configured data retention, preserve options and exit.
if ( ! $delete_data ) {
	return;
}

// 1. Clear scheduled cron event.
$timestamp = wp_next_scheduled( 'wpcalibrate_grd_cron_refresh' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'wpcalibrate_grd_cron_refresh' );
}

// 2. Delete plugin options.
delete_option( 'wpcalibrate_grd_settings' );
delete_option( 'wpcalibrate_grd_last_rates' );
delete_option( 'wpcalibrate_grd_version' );
delete_option( 'wpcalibrate_grd_db_version' );
delete_option( 'wpcalibrate_grd_license' );

// 3. Clear transient caches and locks for all known currencies.
$currencies = array( 'USD', 'GBP', 'EUR', 'PKR', 'INR', 'AED', 'SAR', 'QAR', 'KWD', 'CAD', 'AUD', 'SGD' );
foreach ( $currencies as $currency ) {
	delete_transient( 'wpcalibrate_grd_rate_' . strtolower( $currency ) );
	delete_transient( 'wpcalibrate_grd_lock_' . strtolower( $currency ) );
}
delete_transient( 'wpcalibrate_grd_refresh_lock' );

// Note: WPCalibrate shared parent menu is preserved and not altered.
