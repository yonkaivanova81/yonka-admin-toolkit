/**
 * JavaScript interactions for Quick Notes module
 *
 * @package YonkaAdminToolkit
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		const deleteButtons = document.querySelectorAll( '.yonkatk-note-delete-btn' );

		deleteButtons.forEach(
			function (button) {
				button.addEventListener(
					'click',
					function (e) {
						const confirmMessage = this.getAttribute( 'data-confirm' );
						// eslint-disable-next-line no-alert
						if ( ! window.confirm( confirmMessage )) {
							e.preventDefault();
						}
					}
				);
			}
		);
	}
);
