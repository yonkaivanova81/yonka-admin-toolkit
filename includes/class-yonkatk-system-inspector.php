<?php
/**
 * System Inspector Module (Plugins & Widgets Tracker) for Yonka Admin Toolkit
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

if ( ! class_exists( 'Yonkatk_System_Inspector' ) ) {

	/**
	 * Class Yonkatk_System_Inspector
	 * Inspects installed plugins, active widgets, and theme sidebars.
	 */
	class Yonkatk_System_Inspector {

		/**
		 * Constructor to register hooks.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 30 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		/**
		 * Register Submenu Page under Yonka Admin Toolkit.
		 */
		public function add_plugin_page() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'System Inspector', 'yonka-admin-toolkit' ),
				__( '🔍 System Inspector', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-system-inspector',
				array( $this, 'render_admin_page' ),
				30
			);
		}

		/**
		 * Enqueue Dedicated Styles for System Inspector Admin View.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( false === strpos( $hook, 'yonkatk-system-inspector' ) ) {
				return;
			}

			wp_enqueue_style(
				'yonkatk-system-inspector-css',
				YONKATK_URL . 'assets/css/admin-system-inspector.css',
				array(),
				YONKATK_VERSION
			);
		}

		/**
		 * Render System Inspector HTML Page.
		 */
		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation via GET parameter.
			$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'plugins';
			?>
			<div class="wrap yonkatk-sys-wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( '🔍 Yonka Admin Toolkit › System Inspector', 'yonka-admin-toolkit' ); ?></h1>
				<p><?php esc_html_e( 'Overview of all installed plugins, active widget areas, and placed widgets on this site.', 'yonka-admin-toolkit' ); ?></p>

				<!-- Navigation Tabs -->
				<h2 class="nav-tab-wrapper">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-system-inspector&tab=plugins' ) ); ?>" class="nav-tab <?php echo 'plugins' === $active_tab ? 'nav-tab-active' : ''; ?>">
						🔌 <?php esc_html_e( 'Plugins Overview', 'yonka-admin-toolkit' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-system-inspector&tab=widgets' ) ); ?>" class="nav-tab <?php echo 'widgets' === $active_tab ? 'nav-tab-active' : ''; ?>">
						🧩 <?php esc_html_e( 'Widgets & Sidebars', 'yonka-admin-toolkit' ); ?>
					</a>
				</h2>

				<div class="yonkatk-tab-content">
			<?php
			if ( 'widgets' === $active_tab ) {
				$this->render_widgets_tab();
			} else {
				$this->render_plugins_tab();
			}
			?>
				</div>
			</div>
			<?php
		}

		/**
		 * Render Plugins Overview Tab
		 */
		private function render_plugins_tab() {
			if ( ! function_exists( 'get_plugins' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', array() );

			$total_count    = count( $all_plugins );
			$active_count   = count( $active_plugins );
			$inactive_count = $total_count - $active_count;
			?>
			<div class="welcome-panel yonkatk-summary-panel">
				<h3><?php esc_html_e( 'Plugins Summary', 'yonka-admin-toolkit' ); ?></h3>
				<p class="yonkatk-summary-stats">
					<strong><?php esc_html_e( 'Total Installed:', 'yonka-admin-toolkit' ); ?></strong> <?php echo esc_html( $total_count ); ?> | 
					<span class="yonkatk-stat-active"><strong><?php esc_html_e( 'Active:', 'yonka-admin-toolkit' ); ?></strong> <?php echo esc_html( $active_count ); ?></span> | 
					<span class="yonkatk-stat-inactive"><strong><?php esc_html_e( 'Inactive:', 'yonka-admin-toolkit' ); ?></strong> <?php echo esc_html( $inactive_count ); ?></span>
				</p>
			</div>

			<table class="wp-list-table widefat fixed striped table-view-list yonkatk-plugins-table">
				<thead>
					<tr>
						<th class="yonkatk-col-name"><?php esc_html_e( 'Plugin Name', 'yonka-admin-toolkit' ); ?></th>
						<th class="yonkatk-col-status"><?php esc_html_e( 'Status', 'yonka-admin-toolkit' ); ?></th>
						<th class="yonkatk-col-version"><?php esc_html_e( 'Version', 'yonka-admin-toolkit' ); ?></th>
						<th class="yonkatk-col-author"><?php esc_html_e( 'Author', 'yonka-admin-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Plugin File Path', 'yonka-admin-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
			<?php
			foreach ( $all_plugins as $plugin_path => $plugin_data ) {
				$is_active    = in_array( $plugin_path, $active_plugins, true ) || is_plugin_active_for_network( $plugin_path );
				$status_badge = $is_active
				? '<span class="yonkatk-badge yonkatk-badge-active">' . esc_html__( 'ACTIVE', 'yonka-admin-toolkit' ) . '</span>'
				: '<span class="yonkatk-badge yonkatk-badge-inactive">' . esc_html__( 'INACTIVE', 'yonka-admin-toolkit' ) . '</span>';
				?>
						<tr>
							<td>
								<strong><?php echo esc_html( $plugin_data['Name'] ); ?></strong>
							</td>
							<td><?php echo wp_kses_post( $status_badge ); ?></td>
							<td><code><?php echo esc_html( $plugin_data['Version'] ); ?></code></td>
							<td><?php echo wp_kses_post( $plugin_data['Author'] ); ?></td>
							<td><code><?php echo esc_html( $plugin_path ); ?></code></td>
						</tr>
				<?php
			}
			?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Render Widgets & Sidebars Tab
		 */
		private function render_widgets_tab() {
			global $wp_registered_sidebars, $wp_registered_widgets;

			$sidebars_widgets = get_option( 'sidebars_widgets', array() );
			?>
			<div class="welcome-panel yonkatk-summary-panel">
				<h3><?php esc_html_e( 'Widget Areas & Sidebars Overview', 'yonka-admin-toolkit' ); ?></h3>
				<p><?php esc_html_e( 'Below is a list of all registered widget areas/sidebars in your active theme and the widgets assigned to them.', 'yonka-admin-toolkit' ); ?></p>
			</div>

			<?php if ( empty( $wp_registered_sidebars ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'No registered widget areas found in this theme.', 'yonka-admin-toolkit' ); ?></p></div>
			<?php else : ?>
				<div class="yonkatk-sidebars-grid">
				<?php
				foreach ( $wp_registered_sidebars as $sidebar_id => $sidebar ) {
					$widgets      = isset( $sidebars_widgets[ $sidebar_id ] ) ? $sidebars_widgets[ $sidebar_id ] : array();
					$widget_count = is_array( $widgets ) ? count( $widgets ) : 0;
					?>
						<div class="card yonkatk-sidebar-card">
							<h2 class="title yonkatk-sidebar-title">
					<?php echo esc_html( $sidebar['name'] ); ?>
								<span class="yonkatk-widget-count-badge">
					<?php echo esc_html( $widget_count ); ?> <?php esc_html_e( 'widgets', 'yonka-admin-toolkit' ); ?>
								</span>
							</h2>
							<p class="description"><em>ID: <code><?php echo esc_html( $sidebar_id ); ?></code></em></p>
					<?php if ( ! empty( $sidebar['description'] ) ) : ?>
								<p class="yonkatk-sidebar-desc"><?php echo esc_html( $sidebar['description'] ); ?></p>
					<?php endif; ?>

							<h4 class="yonkatk-widgets-list-title"><?php esc_html_e( 'Assigned Widgets:', 'yonka-admin-toolkit' ); ?></h4>
					<?php if ( ! empty( $widgets ) && is_array( $widgets ) ) : ?>
								<ul class="yonkatk-widgets-list">
						<?php foreach ( $widgets as $widget_id ) : ?>
							<?php
							$widget_name = isset( $wp_registered_widgets[ $widget_id ]['name'] )
							? $wp_registered_widgets[ $widget_id ]['name']
							: $widget_id;
							?>
										<li class="yonkatk-widget-item">
											<strong><?php echo esc_html( $widget_name ); ?></strong> 
											<span class="yonkatk-widget-id">(<?php echo esc_html( $widget_id ); ?>)</span>
										</li>
						<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="yonkatk-no-widgets-msg"><?php esc_html_e( 'No active widgets in this sidebar.', 'yonka-admin-toolkit' ); ?></p>
							<?php endif; ?>
						</div>
				<?php } ?>
				</div>
				<?php
			endif;
		}
	}

	// Initialize module class.
	new Yonkatk_System_Inspector();
}
