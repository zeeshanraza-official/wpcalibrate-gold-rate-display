/**
 * WPCalibrate Gold Rate Display - Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. Test Connection AJAX Handler
		const $btnTest = $('#btn-test-connection');
		const $statusTest = $('#test-connection-status');
		const $badgeContainer = $('#api-connection-status-container');

		if ($btnTest.length) {
			$btnTest.on('click', function(e) {
				e.preventDefault();

				const apiKey = $('#wpcalibrate_api_key').val();
				const nonce = $btnTest.data('nonce') || (window.wpcalibrateGrdAdmin && window.wpcalibrateGrdAdmin.ajaxNonce);
				const ajaxUrl = (window.wpcalibrateGrdAdmin && window.wpcalibrateGrdAdmin.ajaxUrl) || window.ajaxurl;

				$btnTest.prop('disabled', true);
				$statusTest
					.removeClass('status-success status-error')
					.addClass('status-loading')
					.text((window.wpcalibrateGrdAdmin && window.wpcalibrateGrdAdmin.strings.testing) || 'Testing connection...');

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'wpcalibrate_grd_test_connection',
						api_key: apiKey,
						nonce: nonce
					},
					success: function(response) {
						$btnTest.prop('disabled', false);
						if (response.success) {
							$statusTest
								.removeClass('status-loading status-error')
								.addClass('status-success')
								.text(response.data.message || 'Connected successfully!');

							if ($badgeContainer.length) {
								$badgeContainer.html('<span class="wpcalibrate-badge status-success">Connected & Valid</span>');
							}
						} else {
							$statusTest
								.removeClass('status-loading status-success')
								.addClass('status-error')
								.text(response.data.message || 'Connection failed.');

							if ($badgeContainer.length) {
								$badgeContainer.html('<span class="wpcalibrate-badge status-error">Connection Failed</span>');
							}
						}
					},
					error: function(xhr, status, error) {
						$btnTest.prop('disabled', false);
						$statusTest
							.removeClass('status-loading status-success')
							.addClass('status-error')
							.text('Network error occurred: ' + (error || status));
					}
				});
			});
		}

		// 2. Manual Refresh AJAX Handler
		const $btnRefresh = $('#btn-manual-refresh');
		const $statusRefresh = $('#manual-refresh-status');
		const $livePrice = $('#dashboard-live-price');
		const $lastSync = $('#dashboard-last-sync');
		const $statusBadge = $('#dashboard-status-badge');

		if ($btnRefresh.length) {
			$btnRefresh.on('click', function(e) {
				e.preventDefault();

				const nonce = $btnRefresh.data('nonce') || (window.wpcalibrateGrdAdmin && window.wpcalibrateGrdAdmin.ajaxNonce);
				const ajaxUrl = (window.wpcalibrateGrdAdmin && window.wpcalibrateGrdAdmin.ajaxUrl) || window.ajaxurl;

				$btnRefresh.prop('disabled', true);
				$statusRefresh
					.removeClass('status-success status-error')
					.addClass('status-loading')
					.text((window.wpcalibrateGrdAdmin && window.wpcalibrateGrdAdmin.strings.refreshing) || 'Refreshing rates...');

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'wpcalibrate_grd_manual_refresh',
						nonce: nonce
					},
					success: function(response) {
						$btnRefresh.prop('disabled', false);
						if (response.success) {
							$statusRefresh
								.removeClass('status-loading status-error')
								.addClass('status-success')
								.text(response.data.message || 'Rates refreshed!');

							if (response.data.formatted_price && $livePrice.length) {
								$livePrice.text(response.data.formatted_price);
							}
							if (response.data.last_sync && $lastSync.length) {
								$lastSync.text(response.data.last_sync);
							}
							if (response.data.status && $statusBadge.length) {
								if (response.data.status === 'fresh') {
									$statusBadge.removeClass('status-warning status-error status-neutral').addClass('status-success').text('Fresh (Cached)');
								}
							}
						} else {
							$statusRefresh
								.removeClass('status-loading status-success')
								.addClass('status-error')
								.text(response.data.message || 'Refresh failed.');
						}
					},
					error: function(xhr, status, error) {
						$btnRefresh.prop('disabled', false);
						$statusRefresh
							.removeClass('status-loading status-success')
							.addClass('status-error')
							.text('Network error: ' + (error || status));
					}
				});
			});
		}
	});
})(jQuery);
