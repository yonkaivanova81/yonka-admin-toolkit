/**
 * JavaScript interactions for Yonka Admin Toolkit Dashboard
 *
 * @package YonkaAdminToolkit
 */

jQuery( document ).ready(
	function ($) {
		/**
		 * Optional card click action to make whole card clickable
		 */
		$( '.yonkatk-card' ).on(
			'click',
			function (e) {
				// Prevent trigger if clicking directly on an anchor tag.
				if ($( e.target ).is( 'a' )) {
					return;
				}

				const targetUrl = $( this ).find( 'h3 a' ).attr( 'href' );
				if (targetUrl) {
					window.location.href = targetUrl;
				}
			}
		);
	}
);
