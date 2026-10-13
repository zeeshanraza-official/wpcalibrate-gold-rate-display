/**
 * WPCalibrate Gold Rate Display - Frontend Auto-Refresh Script
 * Queries local REST route only; pauses when page is hidden.
 */

(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		const widgets = document.querySelectorAll('.wpcalibrate-gold-rate-display[data-auto-refresh="true"]');
		if (!widgets.length) {
			return;
		}

		const config = window.wpcalibrateGrdConfig || {};
		const restUrl = config.restUrl;
		const refreshIntervalMs = Math.max(60000, (parseInt(config.refreshInterval, 10) || 900) * 1000);

		if (!restUrl) {
			return;
		}

		function refreshWidget(widget) {
			// Skip refresh if tab or browser window is inactive/hidden.
			if (document.visibilityState === 'hidden') {
				return;
			}

			const currency = widget.getAttribute('data-currency') || 'USD';
			const unit = widget.getAttribute('data-unit') || 'gram';
			const decimals = widget.getAttribute('data-decimals') || '2';

			const fetchUrl = restUrl + '?currency=' + encodeURIComponent(currency) + '&unit=' + encodeURIComponent(unit);

			fetch(fetchUrl, {
				method: 'GET',
				headers: {
					'Accept': 'application/json'
				}
			})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('Network response not ok: ' + response.status);
				}
				return response.json();
			})
			.then(function(data) {
				if (!data || !data.spot_price) {
					return;
				}

				// 1. Update spot price if present
				const spotEl = widget.querySelector('[data-role="spot-price"]');
				if (spotEl && data.formatted_spot_price) {
					spotEl.textContent = data.formatted_spot_price;
				}

				// 2. Update each karat element
				if (data.formatted_karats) {
					const karatElements = widget.querySelectorAll('[data-karat]');
					karatElements.forEach(function(el) {
						const karatKey = el.getAttribute('data-karat');
						if (karatKey && data.formatted_karats[karatKey]) {
							const priceEl = el.querySelector('[data-role="karat-price"]');
							if (priceEl) {
								priceEl.textContent = data.formatted_karats[karatKey];
							}
						}
					});
				}

				// 3. Update timestamp
				const timeEl = widget.querySelector('[data-role="updated-time"]');
				if (timeEl && data.formatted_last_sync) {
					timeEl.textContent = data.formatted_last_sync;
				}
			})
			.catch(function(err) {
				// Silently fail without clearing good cached data.
			});
		}

		// Schedule periodic refresh for each widget.
		setInterval(function() {
			widgets.forEach(function(widget) {
				refreshWidget(widget);
			});
		}, refreshIntervalMs);
	});
})();
