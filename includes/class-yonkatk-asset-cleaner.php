<?php
/**
 * Asset & Script Cleaner Module for Yonka Admin Toolkit
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Yonkatk_Asset_Cleaner' ) ) {

	/**
	 * Class Yonkatk_Asset_Cleaner
	 * Handles capturing, dequeuing, and managing scripts/styles on the frontend.
	 */
	class Yonkatk_Asset_Cleaner {

		/**
		 * List of core handles that should never be dequeued to avoid breaking site functionality.
		 *
		 * @var array
		 */
		private $protected_handles = array( 'jquery', 'jquery-core', 'jquery-migrate' );

		/**
		 * Constructor to register hooks.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 15 );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

			// Capture assets only when logged-in admins view the frontend.
			add_action( 'wp_enqueue_scripts', array( $this, 'capture_frontend_assets' ), 9998 );

			// Dequeue disabled scripts and styles late in the execution flow.
			add_action( 'wp_print_scripts', array( $this, 'unload_disabled_scripts' ), 1000 );
			add_action( 'wp_print_styles', array( $this, 'unload_disabled_styles' ), 1000 );

			// AJAX endpoint for clearing captured assets cache.
			add_action( 'wp_ajax_yonkatk__clear_captured_assets', array( $this, 'handle_clear_captured_assets' ) );
		}

		/**
		 * Register Submenu Page under Yonka Admin Toolkit menu.
		 */
		public function add_plugin_page() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Asset Cleaner', 'yonka-admin-toolkit' ),
				__( '⚡ Asset Cleaner', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-asset-cleaner',
				array( $this, 'render_admin_page' ),
				15
			);
		}

		/**
		 * Enqueue Dedicated CSS & JS for Asset Cleaner Admin View.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( false === strpos( $hook, 'yonkatk-asset-cleaner' ) ) {
				return; }

			wp_enqueue_style(
				'yonkatk-asset-cleaner-css',
				YONKATK_URL . 'assets/css/admin-asset-cleaner.css',
				array(),
				defined( 'YONKATK_VERSION' ) ? YONKATK_VERSION : '1.0.0'
			);

			wp_enqueue_script(
				'yonkatk-asset-cleaner-js',
				YONKATK_URL . 'assets/js/admin-asset-cleaner.js',
				array( 'jquery' ),
				defined( 'YONKATK_VERSION' ) ? YONKATK_VERSION : '1.0.0',
				true
			);

			wp_localize_script(
				'yonkatk-asset-cleaner-js',
				'yonkatkAssetCleaner',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'yonkatk__asset_cleaner_nonce' ),
					'confirmReset' => __( 'Are you sure you want to reset the captured assets list?', 'yonka-admin-toolkit' ),
					'genericError' => __( 'An error occurred while resetting the list.', 'yonka-admin-toolkit' ),
				)
			);
		}

		/**
		 * Register plugin settings and sanitization callbacks.
		 */
		public function register_settings() {
			register_setting(
				'yonkatk__asset_cleaner_group',
				'yonkatk__disabled_scripts',
				array(
					'sanitize_callback' => array( $this, 'sanitize_handle_array' ),
				)
			);

			register_setting(
				'yonkatk__asset_cleaner_group',
				'yonkatk__disabled_styles',
				array(
					'sanitize_callback' => array( $this, 'sanitize_handle_array' ),
				)
			);
		}

		/**
		 * Sanitize handle array input.
		 *
		 * @param  mixed $input Submitted raw data.
		 * @return array Clean array of sanitized string keys.
		 */
		public function sanitize_handle_array( $input ) {
			if ( ! is_array( $input ) ) {
				return array();
			}
			return array_values( array_unique( array_map( 'sanitize_key', $input ) ) );
		}

		/**
		 * Captures active scripts and styles when an administrator visits the frontend.
		 */
		public function capture_frontend_assets() {
			if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			global $wp_scripts, $wp_styles;

			// Capture JavaScript assets.
			if ( $wp_scripts instanceof WP_Scripts && ! empty( $wp_scripts->queue ) ) {
				$captured_scripts = get_option( 'yonkatk__captured_scripts', array() );
				$updated          = false;

				foreach ( $wp_scripts->queue as $handle ) {
					if ( isset( $wp_scripts->registered[ $handle ] ) ) {
						$src = $wp_scripts->registered[ $handle ]->src;
						if ( ! isset( $captured_scripts[ $handle ] ) || $captured_scripts[ $handle ] !== $src ) {
							$captured_scripts[ $handle ] = $src;
							$updated                     = true;
						}
					}
				}

				if ( $updated ) {
					update_option( 'yonkatk__captured_scripts', $captured_scripts, false );
				}
			}

			// Capture CSS stylesheets.
			if ( $wp_styles instanceof WP_Styles && ! empty( $wp_styles->queue ) ) {
				$captured_styles = get_option( 'yonkatk__captured_styles', array() );
				$updated         = false;

				foreach ( $wp_styles->queue as $handle ) {
					if ( isset( $wp_styles->registered[ $handle ] ) ) {
						$src = $wp_styles->registered[ $handle ]->src;
						if ( ! isset( $captured_styles[ $handle ] ) || $captured_styles[ $handle ] !== $src ) {
							$captured_styles[ $handle ] = $src;
							$updated                    = true;
						}
					}
				}

				if ( $updated ) {
					update_option( 'yonkatk__captured_styles', $captured_styles, false );
				}
			}
		}

		/**
		 * Unloads (dequeues) user-selected JS scripts on frontend pages.
		 */
		public function unload_disabled_scripts() {
			if ( is_admin() ) {
				return;
			}

			$disabled_scripts = get_option( 'yonkatk__disabled_scripts', array() );
			if ( empty( $disabled_scripts ) || ! is_array( $disabled_scripts ) ) {
				return;
			}

			foreach ( $disabled_scripts as $script_handle ) {
				if ( in_array( $script_handle, $this->protected_handles, true ) ) {
					continue;
				}
				wp_dequeue_script( $script_handle );
				wp_deregister_script( $script_handle );
			}
		}

		/**
		 * Unloads (dequeues) user-selected CSS styles on frontend pages.
		 */
		public function unload_disabled_styles() {
			if ( is_admin() ) {
				return;
			}

			$disabled_styles = get_option( 'yonkatk__disabled_styles', array() );
			if ( empty( $disabled_styles ) || ! is_array( $disabled_styles ) ) {
				return;
			}

			foreach ( $disabled_styles as $style_handle ) {
				wp_dequeue_style( $style_handle );
				wp_deregister_style( $style_handle );
			}
		}

		/**
		 * AJAX Handler to reset captured assets cache.
		 */
		public function handle_clear_captured_assets() {
			check_ajax_referer( 'yonkatk__asset_cleaner_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'yonka-admin-toolkit' ) ) );
			}

			delete_option( 'yonkatk__captured_scripts' );
			delete_option( 'yonkatk__captured_styles' );

			wp_send_json_success( array( 'message' => __( 'Captured list reset successfully.', 'yonka-admin-toolkit' ) ) );
		}

		/**
		 * Render Admin Settings Interface.
		 */
		public function render_admin_page() {
			$captured_scripts = get_option( 'yonkatk__captured_scripts', array() );
			$captured_styles  = get_option( 'yonkatk__captured_styles', array() );

			$disabled_scripts = get_option( 'yonkatk__disabled_scripts', array() );
			$disabled_styles  = get_option( 'yonkatk__disabled_styles', array() );

			if ( ! is_array( $disabled_scripts ) ) {
				$disabled_scripts = array();
			}
			if ( ! is_array( $disabled_styles ) ) {
				$disabled_styles = array();
			}
			?>
			<div class="wrap yonkatk-asset-cleaner-wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( '⚡ Yonka Admin Toolkit › Asset Cleaner', 'yonka-admin-toolkit' ); ?></h1>
				<button id="yonkatk-reset-assets-btn" class="page-title-action"><?php esc_html_e( 'Reset Captured Assets List', 'yonka-admin-toolkit' ); ?></button>

				<p><?php esc_html_e( 'Disable unnecessary JS scripts and CSS stylesheets on the frontend to improve page speed and performance.', 'yonka-admin-toolkit' ); ?></p>

				<div class="notice notice-info inline yonkatk-info-banner">
					<strong><?php esc_html_e( '💡 How it works:', 'yonka-admin-toolkit' ); ?></strong>
			<?php esc_html_e( 'Log in as Administrator and visit your site pages in another tab. The system will automatically record all loaded CSS and JS files here.', 'yonka-admin-toolkit' ); ?>
				</div>

				<form method="post" action="options.php">
			<?php settings_fields( 'yonkatk__asset_cleaner_group' ); ?>

					<div class="yonkatk-asset-grid">

						<!-- JavaScript Files Panel -->
						<div class="yonkatk-asset-panel">
							<h2><?php esc_html_e( '📜 JavaScript Files (.js)', 'yonka-admin-toolkit' ); ?></h2>
			<?php if ( empty( $captured_scripts ) ) : ?>
								<p class="yonkatk-empty-text"><?php esc_html_e( 'No JS files captured yet. Open your website in a new tab while logged in.', 'yonka-admin-toolkit' ); ?></p>
							<?php else : ?>
								<div class="yonkatk-asset-list">
								<?php
								foreach ( $captured_scripts as $handle => $src ) :
									$is_disabled  = in_array( $handle, $disabled_scripts, true );
									$is_protected = in_array( $handle, $this->protected_handles, true );
									?>
										<div class="yonkatk-asset-item <?php echo $is_disabled ? 'is-disabled' : ''; ?>">
											<label class="yonkatk-asset-label <?php echo $is_protected ? 'is-protected' : ''; ?>">
												<input type="checkbox" name="yonkatk__disabled_scripts[]" value="<?php echo esc_attr( $handle ); ?>" <?php checked( $is_disabled ); ?> <?php disabled( $is_protected ); ?> />
												<span class="yonkatk-asset-name">
									<?php echo esc_html( $handle ); ?>
									<?php if ( $is_protected ) : ?>
														<small>(<?php esc_html_e( 'Protected Core', 'yonka-admin-toolkit' ); ?>)</small>
									<?php endif; ?>
												</span>
											</label>
											<div class="yonkatk-asset-src">
									<?php echo esc_html( is_string( $src ) ? $src : __( 'Inline / Core Script', 'yonka-admin-toolkit' ) ); ?>
											</div>
										</div>
								<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<!-- CSS Stylesheets Panel -->
						<div class="yonkatk-asset-panel">
							<h2><?php esc_html_e( '🎨 CSS Stylesheets (.css)', 'yonka-admin-toolkit' ); ?></h2>
			<?php if ( empty( $captured_styles ) ) : ?>
								<p class="yonkatk-empty-text"><?php esc_html_e( 'No CSS files captured yet. Open your website in a new tab while logged in.', 'yonka-admin-toolkit' ); ?></p>
							<?php else : ?>
								<div class="yonkatk-asset-list">
								<?php
								foreach ( $captured_styles as $handle => $src ) :
									$is_disabled = in_array( $handle, $disabled_styles, true );
									?>
										<div class="yonkatk-asset-item <?php echo $is_disabled ? 'is-disabled' : ''; ?>">
											<label class="yonkatk-asset-label">
												<input type="checkbox" name="yonkatk__disabled_styles[]" value="<?php echo esc_attr( $handle ); ?>" <?php checked( $is_disabled ); ?> />
												<span class="yonkatk-asset-name">
									<?php echo esc_html( $handle ); ?>
												</span>
											</label>
											<div class="yonkatk-asset-src">
									<?php echo esc_html( is_string( $src ) ? $src : __( 'Inline / Core Style', 'yonka-admin-toolkit' ) ); ?>
											</div>
										</div>
								<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

					</div>

			<?php submit_button( __( 'Save Disabled Assets', 'yonka-admin-toolkit' ) ); ?>
				</form>
			</div>
			<?php
		}
	}

	new Yonkatk_Asset_Cleaner();
}
