<?php
/**
 * Broken Links & 404 Redirects Tracker Module for Yonka Admin Toolkit
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

if ( ! class_exists( 'Yonkatk_Broken_Links' ) ) {

	/**
	 * Class Yonkatk_Broken_Links
	 * Tracks 404 errors on the site and manages 301 redirects.
	 */
	class Yonkatk_Broken_Links {

		/**
		 * Constructor to attach WordPress hooks.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 25 );
			add_action( 'template_redirect', array( $this, 'track_and_redirect_404' ), 1 );
			add_action( 'admin_init', array( $this, 'handle_actions' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		/**
		 * Register Submenu Page under Yonka Admin Toolkit menu.
		 */
		public function add_plugin_page() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Broken Links Repair', 'yonka-admin-toolkit' ),
				__( '🔗 Broken Links Repair', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-broken-links',
				array( $this, 'render_admin_page' ),
				25
			);
		}

		/**
		 * Enqueue Dedicated Styles and Scripts for Broken Links Admin View.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( false === strpos( $hook, 'yonkatk-broken-links-repair' ) ) {
				return;
			}

			wp_enqueue_style(
				'yonkatk-broken-links-css',
				YONKATK_URL . 'assets/css/admin-broken-links.css',
				array(),
				YONKATK_VERSION
			);

			wp_enqueue_script(
				'yonkatk-broken-links-js',
				YONKATK_URL . 'assets/js/admin-broken-links.js',
				array(),
				YONKATK_VERSION,
				true
			);
		}

		/**
		 * Normalize and sanitize path string.
		 *
		 * @param  string $path Raw URL path.
		 * @return string Clean relative path with leading slash.
		 */
		private function sanitize_path( $path ) {
			$path = rawurldecode( (string) $path );
			$path = sanitize_text_field( $path );
			return '/' . ltrim( $path, '/' );
		}

		/**
		 * Intercept 404 requests, execute redirects if configured, or log missing URLs.
		 */
		public function track_and_redirect_404() {
			if ( ! is_404() ) {
				return;
			}

			$requested_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( empty( $requested_uri ) ) {
				return;
			}

			$parsed_path = wp_parse_url( $requested_uri, PHP_URL_PATH );
			if ( empty( $parsed_path ) ) {
				return;
			}

			$requested_url = $this->sanitize_path( $parsed_path );

			$redirects = get_option( 'yonkatk_redirect_rules', array() );
			if ( ! is_array( $redirects ) ) {
				$redirects = array();
			}

			// 1. Check if a redirect rule exists (Using wp_safe_redirect for security).
			if ( isset( $redirects[ $requested_url ] ) && ! empty( $redirects[ $requested_url ] ) ) {
				wp_safe_redirect( esc_url_raw( $redirects[ $requested_url ] ), 301 );
				exit;
			}

			// 2. Log 404 hit.
			$logs = get_option( 'yonkatk_404_logs', array() );
			if ( ! is_array( $logs ) ) {
				$logs = array();
			}

			// Sanitize Remote IP properly.
			$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'Unknown';
			$ip     = filter_var( $raw_ip, FILTER_VALIDATE_IP ) ? $raw_ip : 'Unknown';

			if ( isset( $logs[ $requested_url ] ) ) {
				$logs[ $requested_url ]['hits']       = isset( $logs[ $requested_url ]['hits'] ) ? (int) $logs[ $requested_url ]['hits'] + 1 : 1;
				$logs[ $requested_url ]['last_visit'] = current_time( 'mysql' );
				$logs[ $requested_url ]['last_ip']    = $ip;
			} else {
				$logs[ $requested_url ] = array(
					'hits'       => 1,
					'last_visit' => current_time( 'mysql' ),
					'last_ip'    => $ip,
				);
			}

			// Enforce log storage ceiling (limit max entries).
			if ( count( $logs ) > 200 ) {
				$logs = array_slice( $logs, -200, null, true );
			}

			update_option( 'yonkatk_404_logs', $logs, false ); // autoload = false for better performance.
		}

		/**
		 * Process POST/GET submissions for adding, deleting redirects, and clearing logs.
		 */
		public function handle_actions() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Add or Update Redirect Rule.
			if ( isset( $_POST['yonkatk_add_redirect'] ) && check_admin_referer( 'yonkatk_redirect_nonce' ) ) {
				$raw_source = isset( $_POST['yonkatk_source_url'] ) ? sanitize_text_field( wp_unslash( $_POST['yonkatk_source_url'] ) ) : '';
				$source     = ! empty( $raw_source ) ? $this->sanitize_path( $raw_source ) : '';
				$target     = isset( $_POST['yonkatk_target_url'] ) ? esc_url_raw( wp_unslash( $_POST['yonkatk_target_url'] ) ) : '';

				if ( ! empty( $source ) && ! empty( $target ) ) {
					// Prevent redirect loops (Self-Redirects).
					$target_path = $this->sanitize_path( wp_parse_url( $target, PHP_URL_PATH ) );
					if ( $source !== $target_path ) {
						$redirects = get_option( 'yonkatk_redirect_rules', array() );
						if ( ! is_array( $redirects ) ) {
							$redirects = array();
						}

						$redirects[ $source ] = $target;
						update_option( 'yonkatk_redirect_rules', $redirects );

						$logs = get_option( 'yonkatk_404_logs', array() );
						if ( is_array( $logs ) && isset( $logs[ $source ] ) ) {
							unset( $logs[ $source ] );
							update_option( 'yonkatk_404_logs', $logs, false );
						}
					}
				}

				wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-broken-links-repair' ) );
				exit;
			}

			// Delete single redirect rule.
			if ( isset( $_GET['action'] ) && 'delete_redirect' === $_GET['action'] && isset( $_GET['yonkatk_rule'] ) ) {
				$raw_rule = sanitize_text_field( wp_unslash( $_GET['yonkatk_rule'] ) );
				$rule     = $this->sanitize_path( $raw_rule );
				check_admin_referer( 'yonkatk_delete_redirect_' . $rule );

				$redirects = get_option( 'yonkatk_redirect_rules', array() );
				if ( is_array( $redirects ) && isset( $redirects[ $rule ] ) ) {
					unset( $redirects[ $rule ] );
					update_option( 'yonkatk_redirect_rules', $redirects );
				}
				wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-broken-links-repair' ) );
				exit;
			}

			// Clear 404 Logs.
			if ( isset( $_POST['yonkatk_clear_404_logs'] ) && check_admin_referer( 'yonkatk_clear_logs_nonce' ) ) {
				delete_option( 'yonkatk_404_logs' );
				wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-broken-links-repair' ) );
				exit;
			}
		}

		/**
		 * Render Admin View HTML.
		 */
		public function render_admin_page() {
			$logs      = get_option( 'yonkatk_404_logs', array() );
			$redirects = get_option( 'yonkatk_redirect_rules', array() );

			if ( ! is_array( $logs ) ) {
				$logs = array();
			}
			if ( ! is_array( $redirects ) ) {
				$redirects = array();
			}
			?>
			<div class="wrap yonkatk-bl-wrap">
				<h1><?php esc_html_e( '🔗 Yonka Admin Toolkit › Broken Links Repair', 'yonka-admin-toolkit' ); ?></h1>
				<p><?php esc_html_e( 'Monitor 404 Not Found errors on your site and create 301 permanent redirects to protect SEO authority.', 'yonka-admin-toolkit' ); ?></p>

				<div class="yonkatk-bl-grid">

					<!-- Add Manual Redirect Form -->
					<div class="yonkatk-bl-card">
						<h2><?php esc_html_e( '➕ Add 301 Redirect', 'yonka-admin-toolkit' ); ?></h2>
						<form method="post">
			<?php wp_nonce_field( 'yonkatk_redirect_nonce' ); ?>
							<p>
								<label for="yonkatk_source_url_field" class="yonkatk-bl-label"><?php esc_html_e( 'Old / Broken Path (Source):', 'yonka-admin-toolkit' ); ?></label>
								<input type="text" name="yonkatk_source_url" id="yonkatk_source_url_field" placeholder="/old-broken-page" required class="large-text" />
								<span class="yonkatk-bl-help-text"><?php esc_html_e( 'Relative path starting with slash (e.g., /old-page)', 'yonka-admin-toolkit' ); ?></span>
							</p>
							<p>
								<label for="yonkatk_target_url_field" class="yonkatk-bl-label"><?php esc_html_e( 'Redirect To (Target URL):', 'yonka-admin-toolkit' ); ?></label>
								<input type="url" name="yonkatk_target_url" id="yonkatk_target_url_field" placeholder="https://example.com/new-page" required class="large-text" />
							</p>
			<?php submit_button( __( 'Create 301 Redirect', 'yonka-admin-toolkit' ), 'primary', 'yonkatk_add_redirect' ); ?>
						</form>
					</div>

					<!-- Active Redirect Rules Panel -->
					<div class="yonkatk-bl-card">
						<h2>
			<?php
			printf(
				/* translators: %d: Number of active redirect rules. */
				esc_html__( '🔁 Active Redirect Rules (%d)', 'yonka-admin-toolkit' ),
				count( $redirects )
			);
			?>
						</h2>
			<?php if ( empty( $redirects ) ) : ?>
							<p class="yonkatk-bl-empty"><?php esc_html_e( 'No active redirects defined yet.', 'yonka-admin-toolkit' ); ?></p>
						<?php else : ?>
							<div class="yonkatk-bl-table-container">
								<table class="widefat fixed striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'From', 'yonka-admin-toolkit' ); ?></th>
											<th><?php esc_html_e( 'To', 'yonka-admin-toolkit' ); ?></th>
											<th class="yonkatk-col-action"><?php esc_html_e( 'Action', 'yonka-admin-toolkit' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $redirects as $source => $target ) : ?>
											<tr>
												<td><code><?php echo esc_html( $source ); ?></code></td>
												<td><a href="<?php echo esc_url( $target ); ?>" target="_blank" rel="noopener noreferrer" class="yonkatk-bl-link"><?php echo esc_html( $target ); ?></a></td>
												<td>
											<?php $delete_url = wp_nonce_url( admin_url( 'admin.php?page=yonkatk-broken-links-repair&action=delete_redirect&yonkatk_rule=' . rawurlencode( $source ) ), 'yonkatk_delete_redirect_' . $source ); ?>
													<a href="<?php echo esc_url( $delete_url ); ?>" class="yonkatk-bl-delete"><?php esc_html_e( 'Delete', 'yonka-admin-toolkit' ); ?></a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

				</div>

				<!-- 404 Hits Log Table -->
				<div class="yonkatk-bl-card yonkatk-bl-log-panel">
					<div class="yonkatk-bl-header-bar">
						<h2><?php esc_html_e( '🚨 Recent 404 Error Log', 'yonka-admin-toolkit' ); ?></h2>
						<form method="post" class="yonkatk-bl-inline-form">
			<?php wp_nonce_field( 'yonkatk_clear_logs_nonce' ); ?>
			<?php submit_button( __( 'Clear Log History', 'yonka-admin-toolkit' ), 'delete', 'yonkatk_clear_404_logs', false ); ?>
						</form>
					</div>

			<?php if ( empty( $logs ) ) : ?>
						<p class="yonkatk-bl-empty"><?php esc_html_e( 'No 404 errors recorded yet. All good!', 'yonka-admin-toolkit' ); ?></p>
					<?php else : ?>
						<table class="widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Requested Broken Path', 'yonka-admin-toolkit' ); ?></th>
									<th class="yonkatk-col-hits"><?php esc_html_e( 'Hits', 'yonka-admin-toolkit' ); ?></th>
									<th><?php esc_html_e( 'Last Visit', 'yonka-admin-toolkit' ); ?></th>
									<th><?php esc_html_e( 'Last IP', 'yonka-admin-toolkit' ); ?></th>
									<th class="yonkatk-col-quick-action"><?php esc_html_e( 'Quick Action', 'yonka-admin-toolkit' ); ?></th>
								</tr>
							</thead>
							<tbody>
						<?php foreach ( $logs as $path => $data ) : ?>
									<tr>
										<td><strong class="yonkatk-bl-path"><?php echo esc_html( $path ); ?></strong></td>
										<td><span class="yonkatk-bl-badge"><?php echo isset( $data['hits'] ) ? (int) $data['hits'] : 1; ?></span></td>
										<td><?php echo isset( $data['last_visit'] ) ? esc_html( $data['last_visit'] ) : ''; ?></td>
										<td><code><?php echo isset( $data['last_ip'] ) ? esc_html( $data['last_ip'] ) : 'Unknown'; ?></code></td>
										<td>
											<button type="button" class="button button-small yonkatk-fix-redirect-btn" data-path="<?php echo esc_attr( $path ); ?>">
							<?php esc_html_e( 'Fix Redirect', 'yonka-admin-toolkit' ); ?>
											</button>
										</td>
									</tr>
						<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

			</div>
			<?php
		}
	}

	// Initialize module class.
	new Yonkatk_Broken_Links();
}
