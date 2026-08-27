<?php
/**
 * Plugin Name:        Yonka Admin Toolkit
 * Description:        All-in-one administration toolkit: Maintenance Mode, Activity Log, Asset Cleaner, Media Information, Broken Links Repair, System Inspector, Database Information, Yearly Stats, Quick Notes and Announcement Marquee.
 * Version:            1.0.0
 * Author:             yonkaivanova
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        yonka-admin-toolkit
 * Requires at least:  6.0
 * Requires PHP:       7.4
 *
 * @package YonkaAdminToolkit
 *
 * @phpcs:disable WordPress.Files.FileName.InvalidClassFileName
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defining Plugin Constants.
define( 'YONKATK_VERSION', filemtime( __FILE__ ) );
define( 'YONKATK_PATH', plugin_dir_path( __FILE__ ) );
define( 'YONKATK_URL', plugin_dir_url( __FILE__ ) );

if ( ! class_exists( 'Yonka_Admin_Toolkit' ) ) {

	/**
	 * Main plugin class.
	 *
	 * @since 1.0.0
	 */
	class Yonka_Admin_Toolkit {

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Admin Menu Setup.
			add_action( 'admin_menu', array( $this, 'create_main_menu' ), 5 );

			// Enqueue Admin Dashboard Assets.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );

			// Load Sub-modules.
			$this->load_modules();
		}

		/**
		 * Create Parent Admin Menu and Dashboard Submenu.
		 */
		public function create_main_menu() {
			// Main Parent Menu Page.
			add_menu_page(
				__( 'Yonka Admin Toolkit', 'yonka-admin-toolkit' ),
				__( 'Yonka Admin Toolkit', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonka-admin-toolkit',
				array( $this, 'render_dashboard_landing' ),
				'dashicons-editor-kitchensink',
				75
			);

			// Submenu Item: Dashboard.
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Dashboard', 'yonka-admin-toolkit' ),
				__( '🏠 Dashboard', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonka-admin-toolkit',
				array( $this, 'render_dashboard_landing' ),
				1
			);
		}

		/**
		 * Enqueue CSS and JS assets for the Toolkit Dashboard landing page.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_dashboard_assets( $hook ) {
			// Load assets only on the main toolkit landing dashboard.
			if ( 'toplevel_page_yonka-admin-toolkit' !== $hook ) {
				return;
			}

			wp_enqueue_style(
				'yonkatk-dashboard-css',
				YONKATK_URL . 'assets/css/admin-dashboard.css',
				array(),
				YONKATK_VERSION
			);

			wp_enqueue_script(
				'yonkatk-dashboard-js',
				YONKATK_URL . 'assets/js/admin-dashboard.js',
				array( 'jquery' ),
				YONKATK_VERSION,
				true
			);
		}

		/**
		 * Render Dashboard Landing Page HTML
		 */
		public function render_dashboard_landing() {
			?>
			<div class="wrap yonkatk-dashboard-wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( '🧰 Yonka Admin Toolkit', 'yonka-admin-toolkit' ); ?></h1>
				<p class="yonkatk-dashboard-subtitle"><?php esc_html_e( 'Welcome to your all-in-one administration and frontend toolkit.', 'yonka-admin-toolkit' ); ?></p>
				
				<hr class="yonkatk-divider">

				<div class="yonkatk-cards-grid">
					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-maintenance-mode' ) ); ?>"><?php esc_html_e( '🛠️ Maintenance Mode', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Toggle Maintenance Mode and animated screen for visitors.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-security-activity-log' ) ); ?>"><?php esc_html_e( '🛡️ Activity Log', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Track administrator logins, failed attempts, and brute-force protection.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-asset-cleaner' ) ); ?>"><?php esc_html_e( '⚡ Asset Cleaner', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Scan and dequeue unneeded JS/CSS scripts to boost website speed and PageSpeed score.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-media-information' ) ); ?>"><?php esc_html_e( '🖼️ Media Inventory', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Scan for unoptimized images and missing ALT attributes.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-broken-links' ) ); ?>"><?php esc_html_e( '🔗 Broken Links Repair', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Track broken 404 URLs and setup 301 permanent redirects for SEO.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-system-inspector' ) ); ?>"><?php esc_html_e( '🔍 System Inspector', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Scan active plugins, widgets, and sidebars to help locate unused elements.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-database-information' ) ); ?>"><?php esc_html_e( '🗄️ Database Information', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'View total size, index health, table overhead, and top storage consumers.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-yearly-statistics' ) ); ?>"><?php esc_html_e( '📊 Yearly Statistics', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Annual publishing statistics for posts, pages, and media assets.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-quick-notes' ) ); ?>"><?php esc_html_e( '📝 Quick Notes', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'CSI-styled admin sticky notes and task management checklists.', 'yonka-admin-toolkit' ); ?></p>
					</div>

					<div class="yonkatk-card">
						<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-marquee-announcement' ) ); ?>"><?php esc_html_e( '📢 Marquee Announcement', 'yonka-admin-toolkit' ); ?></a></h3>
						<p><?php esc_html_e( 'Horizontal scrolling top banner with custom styling and links.', 'yonka-admin-toolkit' ); ?></p>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Require and load all includes module files
		 */
		private function load_modules() {
			$dir   = YONKATK_PATH . 'includes/';
			$files = array(
				'class-yonkatk-maintenance-mode.php',
				'class-yonkatk-security-activity-log.php',
				'class-yonkatk-asset-cleaner.php',
				'class-yonkatk-media-information.php',
				'class-yonkatk-broken-links.php',
				'class-yonkatk-system-inspector.php',
				'class-yonkatk-database-information.php',
				'class-yonkatk-yearly-statistics.php',
				'class-yonkatk-quick-notes.php',
				'class-yonkatk-marquee-announcement.php',
			);

			foreach ( $files as $file ) {
				if ( file_exists( $dir . $file ) ) {
					include_once $dir . $file;
				}
			}
		}
	}

	// Initialize Plugin.
	add_action(
		'plugins_loaded',
		function () {
			new Yonka_Admin_Toolkit();
		}
	);

	// Activation Hook for DB tables setup.
	register_activation_hook(
		__FILE__,
		function () {
			$security_class_file = YONKATK_PATH . 'includes/class-yonkatk-security-log.php';

			if ( file_exists( $security_class_file ) ) {
				include_once $security_class_file;

				if ( class_exists( 'Yonkatk_Security_Log' ) && method_exists( 'Yonkatk_Security_Log', 'create_table' ) ) {
					Yonkatk_Security_Log::create_table();
				}
			}
		}
	);
}
