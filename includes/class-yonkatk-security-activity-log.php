<?php
/**
 * Security & Login Activity Log Module for Yonka Admin Toolkit.
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Yonkatk_Security_Activity_Log' ) ) {

	/**
	 * Class Yonkatk_Security_Activity_Log
	 *
	 * Handles security logging and brute-force protection.
	 */
	class Yonkatk_Security_Activity_Log {

		/**
		 * Database table name.
		 *
		 * @var string
		 */
		private string $table_name;

		/**
		 * Database schema version.
		 *
		 * @var string
		 */
		private string $db_version = '1.0';

		/**
		 * Constructor. Initializes the hooks and database setup.
		 */
		public function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'yonkatk_security_logs';

			// Auto-check table existence.
			if ( get_option( 'yonkatk_security_log_db_version' ) !== $this->db_version ) {
				self::create_table();
				update_option( 'yonkatk_security_log_db_version', $this->db_version );
			}

			// WordPress standard hooks.
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
			add_action( 'admin_init', array( $this, 'handle_log_actions' ) );

			// Event Hooks.
			add_action( 'wp_login', array( $this, 'log_successful_login' ), 10, 2 );
			add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
			add_action( 'clear_auth_cookie', array( $this, 'log_logout' ) );
			add_action( 'activated_plugin', array( $this, 'log_plugin_activation' ) );
			add_action( 'deactivated_plugin', array( $this, 'log_plugin_deactivation' ) );

			// Brute Force Lockout.
			add_filter( 'authenticate', array( $this, 'check_brute_force_lockout' ), 30, 2 );
		}

		/**
		 * Enqueue Admin Styles and Assets.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_assets( string $hook ): void {
			// Define allowed hooks.
			$allowed_hooks = array(
				'yonka-admin-toolkit_page_yonkatk-security-activity-log',
				'index.php', // WordPress Dashboard for the widget.
			);

			// Check if current hook matches our plugin page, dashboard, or contains our unique slug part.
			if ( ! in_array( $hook, $allowed_hooks, true ) && false === strpos( $hook, 'yonkatk-security-activity-log' ) ) {
				return;
			}

			// Use the main plugin URL constant safely with a fallback.
			$plugin_url = defined( 'YONKATK_URL' ) ? YONKATK_URL : plugin_dir_url( dirname( __DIR__ ) );
			$css_url    = trailingslashit( $plugin_url ) . 'assets/css/admin-security.css';
			$version    = defined( 'YONKATK_VERSION' ) ? YONKATK_VERSION : '1.0.0';

			wp_enqueue_style(
				'yonkatk-security-admin-css',
				$css_url,
				array(),
				$version
			);
		}

		/**
		 * Create the database table for security logs.
		 */
		public static function create_table(): void {
			global $wpdb;
			$table_name      = $wpdb->prefix . 'yonkatk_security_logs';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT 0,
                username varchar(100) DEFAULT '',
                event_type varchar(50) NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text DEFAULT '',
                details text DEFAULT '',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY created_at (created_at),
                KEY lookup_brute_force (ip_address, event_type, created_at)
            ) {$charset_collate};";

			include_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Add submenu page for activity log.
		 */
		public function add_plugin_page(): void {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Activity Log', 'yonka-admin-toolkit' ),
				__( '🛡️ Activity Log', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-security-activity-log',
				array( $this, 'render_admin_page' ),
				10
			);
		}

		/**
		 * Insert a new log record into the database.
		 *
		 * @param string $user_id    User ID.
		 * @param string $username   Username.
		 * @param string $event_type Event type key.
		 * @param string $details    Additional details.
		 */
		private function insert_log( string $user_id, string $username, string $event_type, string $details = '' ): void {
			global $wpdb;
			$ip = $this->get_user_ip();
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$this->table_name,
				array(
					'user_id'    => (int) $user_id,
					'username'   => sanitize_user( $username ),
					'event_type' => sanitize_key( $event_type ),
					'ip_address' => $ip,
					'user_agent' => $ua,
					'details'    => sanitize_text_field( $details ),
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			// Auto-clean logs older than 30 days (1% chance per log insert).
			if ( wp_rand( 1, 100 ) === 1 ) {
				$this->purge_old_logs( 30 );
			}
		}

		/**
		 * Log successful login.
		 *
		 * @param string  $user_login Username.
		 * @param WP_User $user       WP_User object.
		 */
		public function log_successful_login( string $user_login, $user ): void {
			$this->insert_log( (string) $user->ID, $user_login, 'login_success', 'User logged in successfully.' );
		}

		/**
		 * Log failed login attempt.
		 *
		 * @param string $username Username.
		 */
		public function log_failed_login( string $username ): void {
			$this->insert_log( '0', $username, 'login_failed', 'Failed login attempt.' );
		}

		/**
		 * Log user logout.
		 */
		public function log_logout(): void {
			$user = wp_get_current_user();
			if ( $user->exists() ) {
				$this->insert_log( (string) $user->ID, $user->user_login, 'logout', 'User logged out.' );
			}
		}

		/**
		 * Log plugin activation.
		 *
		 * @param string $plugin Plugin file path.
		 */
		public function log_plugin_activation( string $plugin ): void {
			$user     = wp_get_current_user();
			$username = $user->exists() ? $user->user_login : 'System/CLI';
			$user_id  = $user->exists() ? (string) $user->ID : '0';
			$this->insert_log( $user_id, $username, 'plugin_activated', 'Activated plugin: ' . $plugin );
		}

		/**
		 * Log plugin deactivation.
		 *
		 * @param string $plugin Plugin file path.
		 */
		public function log_plugin_deactivation( string $plugin ): void {
			$user     = wp_get_current_user();
			$username = $user->exists() ? $user->user_login : 'System/CLI';
			$user_id  = $user->exists() ? (string) $user->ID : '0';
			$this->insert_log( $user_id, $username, 'plugin_deactivated', 'Deactivated plugin: ' . $plugin );
		}

		/**
		 * Check brute force lockout.
		 *
		 * @param mixed  $user     WP_User, WP_Error, or null.
		 * @param string $username Username.
		 * @return mixed
		 */
		public function check_brute_force_lockout( $user, string $username ) {
			if ( empty( $username ) || is_wp_error( $user ) ) {
				return $user;
			}

			global $wpdb;
			$ip = $this->get_user_ip();

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE ip_address = %s AND event_type = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$failed_attempts = $wpdb->get_var(
				$wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$ip,
					'login_failed'
				)
			);

			if ( (int) $failed_attempts >= 5 ) {
				return new WP_Error( 'too_many_attempts', __( '⚠️ <strong>ERROR</strong>: Too many failed login attempts. Please try again in 15 minutes.', 'yonka-admin-toolkit' ) );
			}

			return $user;
		}

		/**
		 * Retrieve the user's IP address safely.
		 *
		 * @return string
		 */
		private function get_user_ip(): string {
			$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
			return filter_var( $raw_ip, FILTER_VALIDATE_IP ) ? $raw_ip : '0.0.0.0';
		}

		/**
		 * Purge old logs from the database.
		 *
		 * @param int $days Number of days to keep.
		 */
		private function purge_old_logs( int $days = 30 ): void {
			global $wpdb;

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)";
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$days
				)
			);
		}

		/**
		 * Handle log actions such as clearing all logs.
		 */
		public function handle_log_actions(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( isset( $_GET['action'] ) && 'clear_all_logs' === $_GET['action'] ) {
				if ( check_admin_referer( 'yonkatk_clear_security_logs' ) ) {
					global $wpdb;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
					wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-security-activity-log&cleared=1' ) );
					exit;
				}
			}
		}

		/**
		 * Add dashboard widget for recent security activity.
		 */
		public function add_dashboard_widget(): void {
			wp_add_dashboard_widget(
				'yonkatk_security_log_widget',
				__( '🛡️ Recent Security Activity', 'yonka-admin-toolkit' ),
				array( $this, 'render_dashboard_widget' )
			);
		}

		/**
		 * Render the dashboard widget content.
		 */
		public function render_dashboard_widget(): void {
			global $wpdb;

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d";
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$logs = $wpdb->get_results(
				$wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					5
				)
			);

			if ( empty( $logs ) ) {
				echo '<p>' . esc_html__( 'No activity logged yet.', 'yonka-admin-toolkit' ) . '</p>';
				return;
			}

			echo '<ul class="yonkatk-dashboard-list">';
			foreach ( $logs as $log ) {
				$badge = $this->get_event_badge( $log->event_type );
				$time  = human_time_diff( strtotime( $log->created_at ), time() ) . ' ago';
				echo '<li class="yonkatk-dashboard-item">';
				echo '<strong>' . esc_html( $log->username ? $log->username : 'Guest' ) . '</strong> ';
				echo '<span>' . wp_kses_post( $badge ) . '</span> ';
				echo "<br><small class='yonkatk-dashboard-meta'>IP: " . esc_html( $log->ip_address ) . ' | ' . esc_html( $time ) . '</small>';
				echo '</li>';
			}
			echo '</ul>';
			echo '<p class="yonkatk-dashboard-footer"><a href="' . esc_url( admin_url( 'admin.php?page=yonkatk-security-activity-log' ) ) . '" class="button button-small">' . esc_html__( 'View Full Log →', 'yonka-admin-toolkit' ) . '</a></p>';
		}

		/**
		 * Get badge HTML for a specific event type.
		 *
		 * @param string $event Event type key.
		 * @return string
		 */
		private function get_event_badge( string $event ): string {
			switch ( $event ) {
				case 'login_success':
					return '<span class="yonkatk-badge-success">[' . esc_html__( 'Login Success', 'yonka-admin-toolkit' ) . ']</span>';
				case 'login_failed':
					return '<span class="yonkatk-badge-failed">[' . esc_html__( 'Login Failed', 'yonka-admin-toolkit' ) . ']</span>';
				case 'logout':
					return '<span class="yonkatk-badge-logout">[' . esc_html__( 'Logout', 'yonka-admin-toolkit' ) . ']</span>';
				case 'plugin_activated':
					return '<span class="yonkatk-badge-plugin-active">[' . esc_html__( 'Plugin Activated', 'yonka-admin-toolkit' ) . ']</span>';
				case 'plugin_deactivated':
					return '<span class="yonkatk-badge-plugin-deactive">[' . esc_html__( 'Plugin Deactivated', 'yonka-admin-toolkit' ) . ']</span>';
				default:
					return '<span class="yonkatk-badge-default">[' . esc_html( $event ) . ']</span>';
			}
		}

		/**
		 * Render the main admin page with activity logs.
		 */
		public function render_admin_page(): void {
			global $wpdb;

			// Nonce Check for Filter Form (if submitted).
			if ( isset( $_GET['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'yonkatk_filter_logs_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'yonka-admin-toolkit' ) );
			}

			// Strict Date Format Validation (YYYY-MM-DD).
			$raw_from  = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
			$raw_to    = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';
			$from_date = ( ! empty( $raw_from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_from ) ) ? $raw_from : '';
			$to_date   = ( ! empty( $raw_to ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_to ) ) ? $raw_to : '';

			$event_filter = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '';

			$where_sql  = '1=1';
			$where_args = array();

			if ( ! empty( $from_date ) ) {
				$where_sql   .= ' AND created_at >= %s';
				$where_args[] = $from_date . ' 00:00:00';
			}
			if ( ! empty( $to_date ) ) {
				$where_sql   .= ' AND created_at <= %s';
				$where_args[] = $to_date . ' 23:59:59';
			}
			if ( ! empty( $event_filter ) ) {
				$where_sql   .= ' AND event_type = %s';
				$where_args[] = $event_filter;
			}

			// Pagination Setup.
			$raw_paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
			$paged     = max( 1, $raw_paged );
			$per_page  = 30;
			$offset    = ( $paged - 1 ) * $per_page;

			// Count total items safely.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";
			if ( ! empty( $where_args ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
				$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_args ) );
			} else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
				$total_items = (int) $wpdb->get_var( $count_sql );
			}

			$total_pages = ceil( $total_items / $per_page );

			// Fetch logs safely.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fetch_sql  = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
			$fetch_args = array_merge( $where_args, array( $per_page, $offset ) );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
			$logs = $wpdb->get_results( $wpdb->prepare( $fetch_sql, $fetch_args ) );
			?>
			<div class="wrap">
				<h1>
					<span><?php esc_html_e( '🛡️ Yonka Admin Toolkit › Activity Log', 'yonka-admin-toolkit' ); ?></span>
					<?php if ( ! empty( $logs ) ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=yonkatk-security-activity-log&action=clear_all_logs' ), 'yonkatk_clear_security_logs' ) ); ?>" 
							onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete all security logs?', 'yonka-admin-toolkit' ) ); ?>');" 
							class="button button-link-delete yonkatk-clear-logs-btn">
							⚠️ <?php esc_html_e( 'Clear All Logs', 'yonka-admin-toolkit' ); ?>
						</a>
					<?php endif; ?>
				</h1>
				
				<?php if ( isset( $_GET['cleared'] ) ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All security logs have been cleared.', 'yonka-admin-toolkit' ); ?></p></div>
				<?php endif; ?>

				<p><?php esc_html_e( 'Track administrator access, login failures, brute-force attempts, and plugin activities.', 'yonka-admin-toolkit' ); ?></p>

				<!-- Date & Event Filter Form -->
				<form method="get" action="" class="yonkatk-filter-form">
					<input type="hidden" name="page" value="yonkatk-security-activity-log">
					<?php wp_nonce_field( 'yonkatk_filter_logs_nonce' ); ?>

					<div class="yonkatk-filter-field">
						<label><?php esc_html_e( 'From Date:', 'yonka-admin-toolkit' ); ?></label>
						<input type="date" name="from_date" value="<?php echo esc_attr( $from_date ); ?>" class="regular-text">
					</div>

					<div class="yonkatk-filter-field">
						<label><?php esc_html_e( 'To Date:', 'yonka-admin-toolkit' ); ?></label>
						<input type="date" name="to_date" value="<?php echo esc_attr( $to_date ); ?>" class="regular-text">
					</div>

					<div class="yonkatk-filter-field">
						<label><?php esc_html_e( 'Event Type:', 'yonka-admin-toolkit' ); ?></label>
						<select name="event_type">
							<option value=""><?php esc_html_e( 'All Events', 'yonka-admin-toolkit' ); ?></option>
							<option value="login_success" <?php selected( $event_filter, 'login_success' ); ?>><?php esc_html_e( 'Login Success', 'yonka-admin-toolkit' ); ?></option>
							<option value="login_failed" <?php selected( $event_filter, 'login_failed' ); ?>><?php esc_html_e( 'Login Failed', 'yonka-admin-toolkit' ); ?></option>
							<option value="logout" <?php selected( $event_filter, 'logout' ); ?>><?php esc_html_e( 'Logout', 'yonka-admin-toolkit' ); ?></option>
							<option value="plugin_activated" <?php selected( $event_filter, 'plugin_activated' ); ?>><?php esc_html_e( 'Plugin Activated', 'yonka-admin-toolkit' ); ?></option>
							<option value="plugin_deactivated" <?php selected( $event_filter, 'plugin_deactivated' ); ?>><?php esc_html_e( 'Plugin Deactivated', 'yonka-admin-toolkit' ); ?></option>
						</select>
					</div>

					<div>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter Logs', 'yonka-admin-toolkit' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-security-activity-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'yonka-admin-toolkit' ); ?></a>
					</div>
				</form>

				<!-- Activity Log Table -->
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th style="width: 160px;"><?php esc_html_e( 'Date & Time', 'yonka-admin-toolkit' ); ?></th>
							<th><?php esc_html_e( 'User / Input', 'yonka-admin-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Event Type', 'yonka-admin-toolkit' ); ?></th>
							<th><?php esc_html_e( 'IP Address', 'yonka-admin-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Details', 'yonka-admin-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $logs ) ) : ?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'No security activity records found for the selected criteria.', 'yonka-admin-toolkit' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><code><?php echo esc_html( $log->created_at ); ?></code></td>
									<td><strong><?php echo esc_html( $log->username ? $log->username : 'Unknown' ); ?></strong></td>
									<td><?php echo wp_kses_post( $this->get_event_badge( $log->event_type ) ); ?></td>
									<td><code><?php echo esc_html( $log->ip_address ); ?></code></td>
									<td><?php echo esc_html( $log->details ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- Pagination Links -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'prev_text' => __( '&laquo;', 'yonka-admin-toolkit' ),
										'next_text' => __( '&raquo;', 'yonka-admin-toolkit' ),
										'total'     => $total_pages,
										'current'   => $paged,
									)
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	new Yonkatk_Security_Activity_Log();
}