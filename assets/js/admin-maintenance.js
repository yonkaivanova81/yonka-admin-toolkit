/**
 * Maintenance Mode Admin Scripts
 *
 * @param {jQuery} $ - jQuery instance.
 * @package YonkaAdminToolkit
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			if ($.fn.wpColorPicker) {
				$( '.yonkatk-color-picker-field' ).wpColorPicker();
			}
		}
	);
})( jQuery );
