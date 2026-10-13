<?php
/**
 * Admin Menu Handler.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Admin;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles shared WPCalibrate menu and isolated Gold Rate Display submenu.
 */
final class AdminMenu {

	public const PARENT_SLUG = 'wpcalibrate';
	public const PAGE_SLUG   = 'wpcalibrate-gold-rate-display';

	/**
	 * Register menu hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );
	}

	/**
	 * Register or reuse shared WPCalibrate top-level menu and attach isolated submenu.
	 */
	public function register_admin_menu(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $admin_page_hooks;

		// 1. Shared top-level WPCalibrate parent menu.
		if ( ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			add_menu_page(
				__( 'WPCalibrate', 'wpcalibrate-gold-rate-display' ),
				'WPCalibrate',
				'manage_options',
				self::PARENT_SLUG,
				array( SettingsPage::class, 'render' ),
				'none',
				58.6
			);

			// Rename default auto-created first submenu item.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'WPCalibrate Gold Rate Display', 'wpcalibrate-gold-rate-display' ),
				__( 'Gold Rate Display', 'wpcalibrate-gold-rate-display' ),
				'manage_options',
				self::PAGE_SLUG,
				array( SettingsPage::class, 'render' )
			);

			// Remove duplicate top-level submenu entry.
			remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
		} else {
			// Parent menu was already registered by another WPCalibrate plugin; attach our isolated submenu.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'WPCalibrate Gold Rate Display', 'wpcalibrate-gold-rate-display' ),
				__( 'Gold Rate Display', 'wpcalibrate-gold-rate-display' ),
				'manage_options',
				self::PAGE_SLUG,
				array( SettingsPage::class, 'render' )
			);
		}
	}
}
