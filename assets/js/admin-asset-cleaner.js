/**
 * Asset Cleaner AJAX Handler & Dynamic UI behavior
 *
 * @param {jQuery} $ - jQuery instance.
 * @package YonkaAdminToolkit
 */

/* global yonkatkAssetCleaner */
jQuery(document).ready(function ($) {
	'use strict';

	// Handle background highlighting on checkbox toggle.
	$('.yonkatk-asset-item input[type="checkbox"]').on('change', function () {
		const $item = $(this).closest('.yonkatk-asset-item');
		if ($(this).is(':checked')) {
			$item.addClass('is-disabled');
		} else {
			$item.removeClass('is-disabled');
		}
	});

	// Reset captured assets via AJAX.
	$('#yonkatk-reset-assets-btn').on('click', function (e) {
		e.preventDefault();
		// eslint-disable-next-line no-alert
		if (!window.confirm(yonkatkAssetCleaner.confirmReset)) {
			return;
		}

		const $btn = $(this);
		$btn.prop('disabled', true);

		const formData = new FormData();
		formData.append('action', 'yonkatk__clear_captured_assets');
		formData.append('nonce', yonkatkAssetCleaner.nonce);

		fetch(yonkatkAssetCleaner.ajaxUrl, {
			method: 'POST',
			body: formData,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					// eslint-disable-next-line no-alert
					window.alert(
						res.data && res.data.message
							? res.data.message
							: yonkatkAssetCleaner.genericError
					);
					$btn.prop('disabled', false);
				}
			})
			.catch(function () {
				// eslint-disable-next-line no-alert
				window.alert(yonkatkAssetCleaner.genericError);
				$btn.prop('disabled', false);
			});
	});
});
