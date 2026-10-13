=== WPCalibrate Gold Rate Display ===
Contributors: wpcalibrate
Tags: gold price, live gold rate, metalpriceapi, karat gold, precious metals
Requires at least: 6.4
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display live spot gold rates and 24K, 22K, 21K, and 18K karat prices with MetalpriceAPI integration, smart caching, and responsive frontend widgets.

== Description ==

**WPCalibrate Gold Rate Display** enables WordPress website owners, jewellery merchants, bullion dealers, and financial blogs to display current spot gold rates and computed karat prices (24K, 22K, 21K, 18K) anywhere on the frontend.

The plugin integrates seamlessly with [MetalpriceAPI](https://metalpriceapi.com/) using secure server-side requests, robust transient caching to protect your API quota from visitor traffic, and an isolated administration dashboard under the unified WPCalibrate parent menu.

### Core Features
* **Spot Gold Rates**: Live spot gold prices in multiple international currencies (USD, GBP, EUR, PKR, INR, AED, SAR, QAR, KWD, CAD, AUD, SGD).
* **Multi-Unit Conversions**: Supports Troy Ounce (oz t), Gram (g), and Tola units with exact physical conversion constants.
* **Karat Purity Calculations**: Automatically derives 24K, 22K, 21K, and 18K gold rates based on pure market spot prices.
* **Quota Protection & Anti-Stampede Caching**: Smart transient caching and concurrency locking prevent duplicate simultaneous provider requests.
* **Three Flexible Layouts**: Responsive Cards grid, accessible structured Table, or compact ticker ribbon.
* **Gutenberg Block & Shortcode**: Embed easily via the `[wpcalibrate_gold_rate]` shortcode or visual Gutenberg block.
* **Elementor Compatible**: Works in Elementor pages via the Shortcode widget with zero bloat or hard dependencies.
* **Client-Side Auto-Refresh**: Optional non-intrusive auto-refresh that queries your server's cached REST endpoint (never exposing your API key or contacting MetalpriceAPI directly from visitors' browsers).
* **Stale-Data Resilience**: Stores last-known-good rate datasets. If the external provider experiences temporary downtime, valid cached prices remain visible with an optional status indicator.
* **Privacy & Security Focused**: API credentials are encrypted/masked in the WordPress admin and never exposed in frontend HTML or public REST routes.

### Third-Party Service Disclosure
This plugin relies on **MetalpriceAPI** to retrieve precious metal rates.

* **Service Provider**: MetalpriceAPI (https://metalpriceapi.com/)
* **Terms of Service**: https://metalpriceapi.com/terms
* **Privacy Policy**: https://metalpriceapi.com/privacy
* **Data Transmitted**: When the plugin refreshes rates, your WordPress web server transmits your configured API key, the requested metal code (`XAU`), and requested currency code (`USD`, etc.) via HTTPS headers.
* **Visitor Data**: No personal data, visitor IP addresses, or browser cookies belonging to your website visitors are transmitted to MetalpriceAPI.

== Installation ==

1. Upload the `wpcalibrate-gold-rate-display` folder to the `/wp-content/plugins/` directory, or upload the ZIP archive via **Plugins > Add New > Upload Plugin** in your WordPress dashboard.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WPCalibrate > Gold Rate Display > API Settings**.
4. Enter your MetalpriceAPI key and click **Test Connection** to verify your account.
5. Save your settings and add the `[wpcalibrate_gold_rate]` shortcode or the **Gold Rate Display** Gutenberg block to any page or post.

== Frequently Asked Questions ==

= Do I need a MetalpriceAPI account? =
Yes. You must supply your own MetalpriceAPI key. Both free and paid subscription plans are supported. Free plans default to USD base rates, and the plugin automatically performs mathematical conversions for international currencies.

= Does page traffic consume my MetalpriceAPI quota? =
No. The plugin utilizes server-side transient caching and WP-Cron scheduling. Visitors viewing your site read from the server cache, protecting your monthly API allowance.

= Can this plugin change WooCommerce product prices? =
No. Version 1.0.0 is a standalone frontend display plugin. It does not alter WooCommerce product catalog prices.

= What units of weight are supported? =
Troy ounce (31.1034768 grams), Gram, and Tola (11.6638038 grams).

= What happens if the API provider is down? =
The plugin stores your last successful normalized dataset. It continues to display the last known valid prices and marks them as fallback/stale rather than displaying zero or broken placeholders.

= What happens on plugin uninstall? =
By default, all plugin settings and historical rates are safely preserved. If you check "Delete WPCalibrate Gold Rate Display data on uninstall" in the Advanced tab, all options, caches, and stored credentials belonging specifically to this plugin will be completely deleted upon removal.

== Shortcode Reference ==

The default shortcode displays the configured global settings:
`[wpcalibrate_gold_rate]`

Available shortcode attributes:
* `currency`: ISO code (e.g. `USD`, `GBP`, `EUR`, `PKR`, `INR`, `AED`, `SAR`, `CAD`, `AUD`)
* `unit`: Weight unit (`gram`, `ounce`, `tola`)
* `layout`: Display format (`cards`, `table`, `compact`)
* `karats`: Comma-separated list (e.g. `24K,22K,21K,18K`)
* `show_spot`: Display spot price header (`true` or `false`)
* `show_change`: Display price change (`true` or `false`)
* `show_updated`: Display last sync time (`true` or `false`)
* `heading`: Custom title string

Example:
`[wpcalibrate_gold_rate layout="table" currency="EUR" unit="gram"]`

== Screenshots ==

1. Admin Dashboard with live spot price, sync status, and quota usage.
2. API Settings tab with masked credential storage and Test Connection.
3. Display Settings tab for currencies, units, and layouts.
4. Frontend Responsive Cards layout.
5. Frontend Structured Table layout.
6. Frontend Compact Ribbon ticker.

== Changelog ==

= 1.0.0 =
* Initial standalone production release.
* MetalpriceAPI integration with X-API-KEY header authentication.
* Unit conversions for Troy Ounce, Gram, and Tola.
* Isolated Karat calculation service for 24K, 22K, 21K, and 18K.
* Transient caching and anti-stampede concurrency locks.
* Public REST API endpoint `/wpcalibrate-gold-rate-display/v1/rates`.
* Responsive Cards, Table, and Compact layouts with WCAG 2.2 AA accessibility.
* Native Gutenberg block and shortcode support.
* Shared WPCalibrate admin menu integration.
