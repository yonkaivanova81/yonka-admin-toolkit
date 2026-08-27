/**
 * Marquee Frontend Fallback Script
 *
 * @package YonkaAdminToolkit
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		function yonkatkUpdateMarqueeHeight() {
			const bar = document.querySelector( '.yonkatk-marquee-announcement-bar' );
			if (bar) {
				const height = bar.offsetHeight;
				document.documentElement.style.setProperty(
					'--yonkatk-marquee-height',
					height + 'px'
				);
			}
		}
		yonkatkUpdateMarqueeHeight();
		window.addEventListener( 'resize', yonkatkUpdateMarqueeHeight );
	}
);
