<?php
/**
 * Maintenance Mode for Yonka Admin Toolkit
 *
 * Handles maintenance mode toggle, REST API protection,
 * admin settings, and rendering the front-end maintenance template.
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

if ( ! class_exists( 'Yonkatk_Maintenance_Mode' ) ) {

	/**
	 * Class Yonkatk_Maintenance_Mode
	 * Manages site maintenance status and display options.
	 */
	class Yonkatk_Maintenance_Mode {

		/**
		 * Constructor to attach hooks and filters.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_submenu' ), 6 );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

			// Intercept frontend and REST API requests.
			add_action( 'template_redirect', array( $this, 'render_maintenance_page' ) );
			add_action( 'rest_authentication_errors', array( $this, 'block_rest_api_in_maintenance' ) );
		}

		/**
		 * Enqueue Administrative Assets (Color Picker, Custom CSS & JS).
		 *
		 * @param string $hook Current page hook in WordPress admin.
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( false === strpos( $hook, 'yonkatk-maintenance-mode' ) ) {
				return;
			}

			// Core Color Picker.
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			// Custom Admin Styles.
			wp_enqueue_style(
				'yonkatk-maintenance-admin-css',
				YONKATK_URL . 'assets/css/admin-maintenance.css',
				array(),
				defined( 'YONKATK_VERSION' ) ? YONKATK_VERSION : '1.0.0'
			);

			// Custom Admin JavaScript.
			wp_enqueue_script(
				'yonkatk-maintenance-admin-js',
				YONKATK_URL . 'assets/js/admin-maintenance.js',
				array( 'jquery', 'wp-color-picker' ),
				defined( 'YONKATK_VERSION' ) ? YONKATK_VERSION : '1.0.0',
				true
			);
		}

		/**
		 * Add Submenu Page under Yonka Admin Toolkit menu.
		 */
		public function add_submenu() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Maintenance Mode', 'yonka-admin-toolkit' ),
				__( '🛠️ Maintenance Mode', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-maintenance-mode',
				array( $this, 'render_admin_page' ),
				5
			);
		}

		/**
		 * Register Options & Settings in WordPress Database.
		 */
		public function register_settings() {
			register_setting(
				'yonkatk_maintenance_group',
				'yonkatk_maintenance_mode_enabled',
				array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'default'           => 0,
				)
			);

			register_setting(
				'yonkatk_maintenance_group',
				'yonkatk_maintenance_mode_title',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => __( "We'll Be Right Back!", 'yonka-admin-toolkit' ),
				)
			);

			register_setting(
				'yonkatk_maintenance_group',
				'yonkatk_maintenance_mode_message',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'default'           => __( 'We are currently performing scheduled maintenance to improve your experience. Please check back shortly.', 'yonka-admin-toolkit' ),
				)
			);

			register_setting(
				'yonkatk_maintenance_group',
				'yonkatk_maintenance_show_gear',
				array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'default'           => 1,
				)
			);

			register_setting(
				'yonkatk_maintenance_group',
				'yonkatk_maintenance_gear_color',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_hex_color',
					'default'           => '#6366f1',
				)
			);
		}

		/**
		 * Render Settings Page in Admin Dashboard.
		 */
		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'yonka-admin-toolkit' ) );
			}

			$is_enabled     = (int) get_option( 'yonkatk_maintenance_mode_enabled', 0 );
			$custom_title   = get_option( 'yonkatk_maintenance_mode_title', __( "We'll Be Right Back!", 'yonka-admin-toolkit' ) );
			$custom_message = get_option( 'yonkatk_maintenance_mode_message', __( 'We are currently performing scheduled maintenance to improve your experience. Please check back shortly.', 'yonka-admin-toolkit' ) );
			$show_gear      = (int) get_option( 'yonkatk_maintenance_show_gear', 1 );
			$gear_color     = get_option( 'yonkatk_maintenance_gear_color', '#6366f1' );
			?>
			<div class="wrap yonkatk-maint-wrap">
				<h1><?php esc_html_e( '🛠️ Yonka Admin Toolkit › Maintenance Mode', 'yonka-admin-toolkit' ); ?></h1>

				<div class="yonkatk-maint-card">
					<form method="post" action="options.php">
						<?php settings_fields( 'yonkatk_maintenance_group' ); ?>

						<table class="form-table" role="presentation">
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Enable Maintenance Mode', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<label class="yonkatk-switch">
										<input type="checkbox" name="yonkatk_maintenance_mode_enabled" value="1" <?php checked( 1, $is_enabled, true ); ?> />
										<span class="yonkatk-slider round"></span>
									</label>
									<p class="description yonkatk-desc-margin">
										<?php esc_html_e( 'When enabled, non-logged-in visitors will see the animated maintenance screen.', 'yonka-admin-toolkit' ); ?>
									</p>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Custom Title', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<input type="text" name="yonkatk_maintenance_mode_title" value="<?php echo esc_attr( $custom_title ); ?>" class="regular-text" />
								</td>
							</tr>

							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Custom Message', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<textarea name="yonkatk_maintenance_mode_message" rows="5" class="large-text"><?php echo esc_textarea( $custom_message ); ?></textarea>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Show Gear Animation', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="yonkatk_maintenance_show_gear" value="1" <?php checked( 1, $show_gear, true ); ?> />
										<?php esc_html_e( 'Display animated gear on the maintenance page', 'yonka-admin-toolkit' ); ?>
									</label>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Gear Color', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<input type="text" name="yonkatk_maintenance_gear_color" value="<?php echo esc_attr( $gear_color ); ?>" class="yonkatk-color-picker-field" data-default-color="#6366f1" />
								</td>
							</tr>

							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Status Indicator', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<?php if ( 1 === $is_enabled ) : ?>
										<span class="yonkatk-status-badge yonkatk-status-active">
											<?php esc_html_e( '🟢 MAINTENANCE MODE IS ACTIVE', 'yonka-admin-toolkit' ); ?>
										</span>
									<?php else : ?>
										<span class="yonkatk-status-badge yonkatk-status-disabled">
											<?php esc_html_e( '⚪ DISABLED (SITE IS LIVE)', 'yonka-admin-toolkit' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Save Changes', 'yonka-admin-toolkit' ) ); ?>
					</form>
				</div>
			</div>
			<?php
		}

		/**
		 * Restrict REST API endpoints during maintenance mode for non-administrators.
		 *
		 * @param  mixed $access Current authentication error state.
		 * @return WP_Error|mixed WP_Error on maintenance, or unmodified access response.
		 */
		public function block_rest_api_in_maintenance( $access ) {
			if ( get_option( 'yonkatk_maintenance_mode_enabled', 0 ) && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'site_under_maintenance',
					__( 'Site is currently under maintenance.', 'yonka-admin-toolkit' ),
					array( 'status' => 503 )
				);
			}
			return $access;
		}

		/**
		 * Intercept Frontend Page Loading to Serve Maintenance Screen.
		 */
		/**
		 * Render the maintenance mode custom frontend page.
		 */
		public function render_maintenance_page() {
			if ( ! get_option( 'yonkatk_maintenance_mode_enabled', false ) ) {
				return;
			}

			// Allow logged-in administrators to browse normally.
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				return;
			}

			// Prevent blocking standard login page access.
			$pagenow  = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
			$php_self = isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '';

			if ( 'wp-login.php' === $pagenow || ( $php_self && false !== strpos( $php_self, 'wp-login.php' ) ) ) {
				return;
			}

			// Set 503 Service Unavailable HTTP Status Header.
			status_header( 503 );
			header( 'Retry-After: 3600' );
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
			header( 'Content-Type: text/html; charset=utf-8' );

			$site_name  = get_bloginfo( 'name' );
			$title      = get_option( 'yonkatk_maintenance_mode_title', __( "We'll Be Right Back!", 'yonka-admin-toolkit' ) );
			$message    = get_option( 'yonkatk_maintenance_mode_message', __( 'We are currently performing scheduled maintenance to improve your experience. Please check back shortly.', 'yonka-admin-toolkit' ) );
			$show_gear  = (int) get_option( 'yonkatk_maintenance_show_gear', 1 );
			$gear_color = get_option( 'yonkatk_maintenance_gear_color', '#6366f1' );

			if ( empty( trim( $title ) ) ) {
				$title = __( "We'll Be Right Back!", 'yonka-admin-toolkit' );
			}
			if ( empty( trim( $message ) ) ) {
				$message = __( 'We are currently performing scheduled maintenance to improve your experience. Please check back shortly.', 'yonka-admin-toolkit' );
			}

			$css_url = defined( 'YONKATK_URL' ) ? YONKATK_URL : plugin_dir_url( __FILE__ ) . '../../';
			$css_url = trailingslashit( $css_url ) . 'assets/css/maintenance-frontend.css';
			$version = defined( 'YONKATK_VERSION' ) ? YONKATK_VERSION : '1.0.0';

			wp_enqueue_style( 'yonkatk-maintenance-style', $css_url, array(), $version );
			$custom_css = 'body { --yonkatk-gear-color: ' . esc_attr( $gear_color ) . '; }';
			wp_add_inline_style( 'yonkatk-maintenance-style', $custom_css );
			?>
			<!DOCTYPE html>
			<html <?php language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title><?php echo esc_html( $site_name ); ?> - <?php echo esc_html( $title ); ?></title>
				<?php wp_head(); ?>
			</head>
			<body>

				<div class="yonkatk-maintenance-card">
					<?php if ( 1 === $show_gear ) : ?>
						<div class="yonkatk-gear-wrapper">
							<svg class="yonkatk-gear-icon" viewBox="0 0 24 24" fill="none" stroke="var(--yonkatk-gear-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"></path>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
							</svg>
						</div>
					<?php endif; ?>

					<h1 class="yonkatk-maint-title"><?php echo esc_html( $title ); ?></h1>
					<p class="yonkatk-maint-description"><?php echo esc_html( $message ); ?></p>
					<div class="yonkatk-pulse-bar"></div>
				</div>

			</body>
			</html>
			<?php
			exit;
		}
	}

	// Initialize maintenance module class.
	new Yonkatk_Maintenance_Mode();
}
