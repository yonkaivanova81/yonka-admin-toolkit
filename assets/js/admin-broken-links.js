/**
 * Broken Links & Redirects Helper Script
 *
 * @package YonkaAdminToolkit
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		'use strict';

		// Copy path from 404 log table directly into the manual redirect source input field.
		document
		.querySelectorAll( '.yonkatk-fix-redirect-btn' )
		.forEach(
			function (btn) {
				btn.addEventListener(
					'click',
					function () {
						const path  = this.getAttribute( 'data-path' );
						const input = document.getElementById(
							'yonkatk_source_url_field'
						);
						if (input) {
							input.value = path;
							window.scrollTo(
								{
									top: 0,
									behavior: 'smooth',
								}
							);
						}
					}
				);
			}
		);
	}
);
