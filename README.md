# WPCalibrate Gold Rate Display

<p align="center">
  <img src="branding/icon-dark.png" alt="WPCalibrate Logo" width="80" height="80">
</p>

<p align="center">
  <strong>Live Spot Gold Prices & Multi-Karat Rates for WordPress</strong><br>
  Real-time market ingestion via MetalpriceAPI, intelligent anti-stampede caching, and accessible frontend widgets.
</p>

<p align="center">
  <a href="#about">About</a> •
  <a href="#features">Features</a> •
  <a href="#docs">Docs</a> •
  <a href="#rest-api">REST API</a> •
  <a href="#changelog">Changelog</a> •
  <a href="#support">Support</a>
</p>

---

## About

**WPCalibrate Gold Rate Display** is an enterprise-grade, standalone WordPress plugin designed specifically for jewellery retailers, bullion merchants, currency exchange operators, and financial portals.

The plugin connects seamlessly with **MetalpriceAPI** to stream live spot gold market prices (XAU) and compute physical karat valuations (**24K, 22K, 21K, and 18K**) across international units (**Troy Ounce, Gram, and Tola**) in real time.

Built from the ground up for speed, reliability, and security, it includes a multi-tiered transient caching shield that protects your external API quota while ensuring that visitors and search engines always experience instant page loads with zero layout shifts or price drops.

---

## Features

### 🪙 Real-Time Market Ingestion
- **MetalpriceAPI Integration:** Secure HTTPS requests using standard `X-API-KEY` authorization.
- **Cross-Currency Support:** Convert gold prices to USD, EUR, GBP, PKR, INR, AED, SAR, CAD, AUD, QAR, KWD, and SGD.
- **Quota Safeguards:** Built-in connection tester and quota monitoring via the `/v1/usage` API endpoint.

### ⚖️ Exact Physical & Purity Calculations
- **True International Physical Constants:**
  - 1 Troy Ounce = `31.1034768` grams
  - 1 Tola = `11.6638038` grams
- **Standard Karat Ratios:** 24K (24/24), 22K (22/24), 21K (21/24), and 18K (18/24).
- **Full Mathematical Precision:** Calculations maintain floating-point accuracy end-to-end, rounding only at final output.

### 🛡️ Intelligent Anti-Stampede Caching
- **Quota Shield:** Visitor page requests never make direct outbound HTTP calls to third-party APIs.
- **Atomic Transient Locks:** 60-second mutex locks prevent redundant simultaneous requests during cache refresh cycles.
- **Last-Known-Good Fallback:** If upstream API rate limits are hit or network interruptions occur, the plugin serves the most recent verified rate with a clear stale badge—preventing zero-price errors.

### 🎨 Modular Presentation & Accessibility
- **Three Presentation Layouts:**
  - **Cards:** Modern elevated grid layout showcasing spot rates and karat breakdowns.
  - **Table:** Clean, tabular display ideal for financial and daily price tables.
  - **Compact:** Minimal inline ticker suited for headers, sticky bars, or footers.
- **WCAG 2.2 AA Compliant:** Accessible semantic markup, high-contrast ratios, visible focus indicators, and screen reader announcements.
- **Gutenberg & Elementor Compatible:** Includes a native server-rendered Gutenberg block (`wpcalibrate/gold-rate`) and standard shortcode support.

### 🔒 Enterprise WordPress Engineering
- **PSR-4 Namespaced:** Zero global namespace pollution (`WPCalibrate\GoldRateDisplay`).
- **PHP 8.2 & 8.3 Ready:** Strict type declarations (`declare(strict_types=1);`) across all files.
- **WPCalibrate Suite Isolation:** Seamlessly integrates into the shared `WPCalibrate` parent admin menu without conflicts.
- **Security First:** Nonces on all forms and AJAX endpoints, `current_user_can('manage_options')` capability enforcement, and masked credential storage (`meta••••••••5678`).

---

## Docs

### System Requirements
- **WordPress:** 6.0 or higher (Tested through WordPress 7.x)
- **PHP:** 8.2 or 8.3 (Strict types enforced)
- **Dependencies:** None. Zero external composer or npm dependencies required.

---

### Installation

1. Download the latest release `wpcalibrate-gold-rate-display.zip`.
2. In your WordPress Admin, navigate to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**, then **Activate Plugin**.
4. Navigate to **WPCalibrate > Gold Rate Display** in your WordPress sidebar.
5. In the **API Settings** tab, paste your **MetalpriceAPI Key** and click **Test Connection**.
6. Customize your default currency, units, and layout in the **Display** tab.

---

### Shortcode Reference

Embed gold prices anywhere on your site using the `[wpcalibrate_gold_rate]` shortcode:

```text
[wpcalibrate_gold_rate]
```

#### Shortcode Attributes

| Attribute | Default | Options | Description |
|---|---|---|---|
| `currency` | `USD` | `USD`, `EUR`, `GBP`, `PKR`, `INR`, `AED`, `SAR`, `CAD`, `AUD`, etc. | Target display currency. |
| `unit` | `gram` | `gram`, `ounce`, `tola` | Physical weight unit for calculation. |
| `karats` | `24,22,21,18` | Comma-separated list (e.g. `24,22`) | Karats to display. |
| `layout` | `cards` | `cards`, `table`, `compact` | Presentation format. |
| `show_spot` | `1` | `1`, `0` | Display the 24K pure spot price header. |
| `show_change` | `1` | `1`, `0` | Display 24h percentage and net change indicators. |
| `show_updated` | `1` | `1`, `0` | Show last synchronization timestamp. |
| `decimals` | `2` | `0`, `2`, `3`, `4` | Decimal precision for price amounts. |

#### Shortcode Examples

**Jewellery Price Board (Grams in EUR):**
```text
[wpcalibrate_gold_rate currency="EUR" unit="gram" karats="24,22,18" layout="table"]
```

**Subcontinent / Middle East Gold Rates (Tola in AED):**
```text
[wpcalibrate_gold_rate currency="AED" unit="tola" karats="24,22,21" layout="cards"]
```

**Header Ticker / Compact Bar (USD Spot):**
```text
[wpcalibrate_gold_rate layout="compact" show_change="1"]
```

---

## REST API

The plugin registers a public, cached read-only REST API endpoint:

```http
GET /wp-json/wpcalibrate-gold-rate-display/v1/rates
```

#### Query Parameters
- `currency` *(optional, default: plugin default)*: 3-letter currency code (e.g. `USD`, `GBP`).
- `unit` *(optional, default: plugin default)*: `gram`, `ounce`, or `tola`.

#### Sample JSON Response
```json
{
  "success": true,
  "data": {
    "currency": "USD",
    "unit": "gram",
    "timestamp": 1728828000,
    "last_synced_at": 1728828000,
    "status": "fresh",
    "spot_price": 84.52,
    "rates": {
      "24k": { "karat": "24K", "price": 84.52, "purity": 1.0 },
      "22k": { "karat": "22K", "price": 77.48, "purity": 0.9167 },
      "21k": { "karat": "21K", "price": 73.96, "purity": 0.875 },
      "18k": { "karat": "18K", "price": 63.39, "purity": 0.75 }
    }
  }
}
```

---

## Changelog

### [1.0.1] - 2026-10-13
- **Fixed:** Resolved critical fatal error on the Support tab caused by undefined constant `MARKPLACE` in `SupportService.php`.
- **Added:** New **Overview Tab** featuring plugin methodology, architecture pillars, a 4-step guided setup workflow, and copyable shortcodes.
- **Added:** Automated deployment pipeline and expanded automated test assertions (151 assertions across 10 suites).
- **Added:** Comprehensive GitHub documentation and packaging workflow.

### [1.0.0] - 2026-10-13
- **Initial Release:** Production-ready live gold rate display plugin with MetalpriceAPI integration.
- Full unit conversion engine for Troy Ounce, Gram, and Tola.
- Purity calculations for 24K, 22K, 21K, and 18K gold.
- Anti-stampede transient cache with last-known-good fallback.
- Native Gutenberg block, shortcode engine, and responsive CSS.
- Public read-only REST API endpoint.

---

## Support & Inquiries

For technical support, custom precious metals integration, or questions:
- **Website:** [https://wpcalibrate.com](https://wpcalibrate.com)
- **Marketplace:** [https://marketplace.wpcalibrate.com](https://marketplace.wpcalibrate.com)
- **Support Email:** [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **WhatsApp:** [+44 7474 795976](https://wa.me/447474795976)

---

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](LICENSE).
