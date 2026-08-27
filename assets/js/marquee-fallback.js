/**
 * Marquee Module Fallback Script
 *
 * @package YonkaAdminToolkit
 */

/* global yonkatkMarqueeFallbackVars */
(function () {
	if (
		typeof yonkatkMarqueeFallbackVars !== 'undefined' &&
		yonkatkMarqueeFallbackVars.html
	) {
		const container       = document.createElement( 'div' );
		container.innerHTML   = yonkatkMarqueeFallbackVars.html;
		const bar             = container.firstElementChild;
		const targetContainer =
			document.querySelector( '.wp-site-blocks' ) || document.body;
		if (bar && targetContainer) {
			targetContainer.insertBefore( bar, targetContainer.firstChild );
		}
	}
})();
