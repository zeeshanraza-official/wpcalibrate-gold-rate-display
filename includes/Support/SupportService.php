<?php
/**
 * Support metadata service.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Provides official verified support contact channels.
 */
final class SupportService {

	public const EMAIL       = 'support@wpcalibrate.com';
	public const WEBSITE     = 'https://wpcalibrate.com';
	public const MARKETPLACE = 'https://marketplace.wpcalibrate.com/';
	public const PHONE       = '+447474795976';
	public const WHATSAPP_URL = 'https://wa.me/447474795976';

	/**
	 * Get contact details map.
	 *
	 * @return array<string, string>
	 */
	public static function get_details(): array {
		return array(
			'email'        => self::EMAIL,
			'website'      => self::WEBSITE,
			'marketplace'  => self::MARKETPLACE,
			'phone'        => self::PHONE,
			'whatsapp_url' => self::WHATSAPP_URL,
		);
	}
}
