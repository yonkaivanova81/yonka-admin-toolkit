<?php
/**
 * Marquee Announcement for Yonka Admin Toolkit
 *
 * @package Yonka_Admin_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Yonkatk_Marquee_Announcement' ) ) {

	/**
	 * Main class for handling the Marquee Announcement Module.
	 */
	class Yonkatk_Marquee_Announcement {

		/**
		 * Option name in wp_options table.
		 *
		 * @var string
		 */
		private $yonkatk_option_name = 'yonkatk_marquee_announcement_settings';

		/**
		 * Flag to prevent duplicate rendering on the front-end.
		 *
		 * @var bool
		 */
		private static $yonkatk_rendered = false;

		/**
		 * Constructor to register hooks.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'yonkatk_add_plugin_page' ), 50 );
			add_action( 'admin_init', array( $this, 'yonkatk_register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'yonkatk_enqueue_admin_assets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'yonkatk_enqueue_frontend_assets' ) );

			add_action( 'wp_body_open', array( $this, 'yonkatk_render_announcement_bar' ), 1 );
			add_action( 'wp_footer', array( $this, 'yonkatk_render_announcement_bar_fallback' ), 9999 );
		}

		/**
		 * Add submenu page under Yonka Admin Toolkit.
		 */
		public function yonkatk_add_plugin_page() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Marquee Announcement', 'yonka-admin-toolkit' ),
				__( '📢 Marquee Announcement', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-marquee-announcement',
				array( $this, 'yonkatk_render_admin_page' ),
				50
			);
		}

		/**
		 * Register plugin settings.
		 */
		public function yonkatk_register_settings() {
			register_setting(
				'yonkatk_marquee_announcement_group',
				$this->yonkatk_option_name,
				array(
					'sanitize_callback' => array( $this, 'yonkatk_sanitize_settings' ),
				)
			);
		}

		/**
		 * Sanitize admin settings input.
		 *
		 * @param  array $yonkatk_input Raw input options.
		 * @return array Sanitized options.
		 */
		public function yonkatk_sanitize_settings( $yonkatk_input ) {
			$yonkatk_output = array();

			$yonkatk_output['enabled']        = ! empty( $yonkatk_input['enabled'] ) ? 1 : 0;
			$yonkatk_output['source']         = isset( $yonkatk_input['source'] ) && in_array( $yonkatk_input['source'], array( 'custom', 'recent_posts', 'selected_posts' ), true ) ? $yonkatk_input['source'] : 'recent_posts';
			$yonkatk_output['custom_text']    = isset( $yonkatk_input['custom_text'] ) ? wp_kses_post( $yonkatk_input['custom_text'] ) : '';
			$yonkatk_output['post_count']     = isset( $yonkatk_input['post_count'] ) ? absint( $yonkatk_input['post_count'] ) : 5;
			$yonkatk_output['post_type']      = isset( $yonkatk_input['post_type'] ) ? sanitize_text_field( $yonkatk_input['post_type'] ) : 'post';
			$yonkatk_output['selected_posts'] = ( isset( $yonkatk_input['selected_posts'] ) && is_array( $yonkatk_input['selected_posts'] ) ) ? array_map( 'absint', $yonkatk_input['selected_posts'] ) : array();

			$yonkatk_output['speed']          = isset( $yonkatk_input['speed'] ) ? absint( $yonkatk_input['speed'] ) : 20;
			$yonkatk_output['bg_color']       = isset( $yonkatk_input['bg_color'] ) ? sanitize_hex_color( $yonkatk_input['bg_color'] ) : '#1d2327';
			$yonkatk_output['text_color']     = isset( $yonkatk_input['text_color'] ) ? sanitize_hex_color( $yonkatk_input['text_color'] ) : '#ffffff';
			$yonkatk_output['pause_on_hover'] = ! empty( $yonkatk_input['pause_on_hover'] ) ? 1 : 0;
			$yonkatk_output['direction']      = isset( $yonkatk_input['direction'] ) && in_array( $yonkatk_input['direction'], array( 'left', 'right' ), true ) ? $yonkatk_input['direction'] : 'left';
			$yonkatk_output['separator']      = isset( $yonkatk_input['separator'] ) ? sanitize_text_field( $yonkatk_input['separator'] ) : '✦';
			$yonkatk_output['sticky']         = ! empty( $yonkatk_input['sticky'] ) ? 1 : 0;

			return $yonkatk_output;
		}

		/**
		 * Get module settings with defaults.
		 *
		 * @return array Saved options merged with defaults.
		 */
		private function yonkatk_get_settings() {
			$yonkatk_defaults = array(
				'enabled'        => 1,
				'source'         => 'recent_posts',
				'custom_text'    => '',
				'post_count'     => 5,
				'post_type'      => 'post',
				'selected_posts' => array(),
				'speed'          => 20,
				'bg_color'       => '#1d2327',
				'text_color'     => '#ffffff',
				'pause_on_hover' => 1,
				'direction'      => 'left',
				'separator'      => '✦',
				'sticky'         => 1,
			);

			return wp_parse_args( get_option( $this->yonkatk_option_name, array() ), $yonkatk_defaults );
		}

		/**
		 * Enqueue administrative CSS assets for options page.
		 *
		 * @param string $yonkatk_hook_suffix Current admin page hook.
		 */
		public function yonkatk_enqueue_admin_assets( $yonkatk_hook_suffix ) {
			if ( false === strpos( $yonkatk_hook_suffix, 'yonkatk-marquee-announcement' ) ) {
				return;
			}

			wp_enqueue_style(
				'yonkatk-marquee-admin-css',
				plugins_url( 'assets/css/marquee-admin.css', __DIR__ ),
				array(),
				'1.0.0'
			);
		}

		/**
		 * Enqueue front-end CSS and JS assets.
		 */
		public function yonkatk_enqueue_frontend_assets() {
			$yonkatk_settings = $this->yonkatk_get_settings();
			if ( empty( $yonkatk_settings['enabled'] ) || is_admin() ) {
				return;
			}

			wp_enqueue_style(
				'yonkatk-marquee-announcement-css',
				plugins_url( 'assets/css/marquee-frontend.css', __DIR__ ),
				array(),
				'1.0.0'
			);

			$yonkatk_is_sticky = ! empty( $yonkatk_settings['sticky'] );
			$yonkatk_position  = $yonkatk_is_sticky ? 'sticky' : 'relative';
			$yonkatk_bg        = sanitize_hex_color( $yonkatk_settings['bg_color'] );
			$yonkatk_color     = sanitize_hex_color( $yonkatk_settings['text_color'] );
			$yonkatk_duration  = absint( $yonkatk_settings['speed'] ) . 's';
			$yonkatk_anim      = ( 'right' === $yonkatk_settings['direction'] ) ? 'yonkatk-single-marquee-scroll-reverse' : 'yonkatk-single-marquee-scroll';

			$yonkatk_inline_css = sprintf(
				'.yonkatk-marquee-announcement-bar { position: %s !important; background-color: %s; color: %s; } .yonkatk-marquee-announcement-content { animation-duration: %s; animation-name: %s; }',
				esc_attr( $yonkatk_position ),
				esc_attr( $yonkatk_bg ),
				esc_attr( $yonkatk_color ),
				esc_attr( $yonkatk_duration ),
				esc_attr( $yonkatk_anim )
			);

			wp_add_inline_style( 'yonkatk-marquee-announcement-css', $yonkatk_inline_css );

			if ( $yonkatk_is_sticky ) {
				wp_enqueue_script(
					'yonkatk-marquee-announcement-js',
					plugins_url( 'assets/js/marquee-frontend.js', __DIR__ ),
					array(),
					'1.0.0',
					true
				);
			}
		}

		/**
		 * Fallback renderer in case wp_body_open is not supported by theme.
		 */
		public function yonkatk_render_announcement_bar_fallback() {
			if ( ! self::$yonkatk_rendered ) {
				ob_start();
				$this->yonkatk_render_announcement_bar();
				$yonkatk_html = ob_get_clean();

				if ( ! empty( $yonkatk_html ) ) {
					wp_enqueue_script(
						'yonkatk-marquee-fallback-js',
						plugins_url( 'assets/js/marquee-fallback.js', __DIR__ ),
						array(),
						'1.0.0',
						true
					);

					wp_localize_script(
						'yonkatk-marquee-fallback-js',
						'yonkatkMarqueeFallbackVars',
						array(
							'html' => $yonkatk_html,
						)
					);
				}
			}
		}

		/**
		 * Render the Marquee Announcement Bar on the front-end.
		 */
		public function yonkatk_render_announcement_bar() {
			if ( self::$yonkatk_rendered || is_admin() ) {
				return;
			}

			$yonkatk_settings = $this->yonkatk_get_settings();
			if ( empty( $yonkatk_settings['enabled'] ) ) {
				return;
			}

			$yonkatk_items = array();

			if ( 'custom' === $yonkatk_settings['source'] ) {
				if ( ! empty( $yonkatk_settings['custom_text'] ) ) {
					$yonkatk_items[] = $yonkatk_settings['custom_text'];
				}
			} elseif ( 'selected_posts' === $yonkatk_settings['source'] && ! empty( $yonkatk_settings['selected_posts'] ) ) {
				$yonkatk_posts = get_posts(
					array(
						'post__in'       => $yonkatk_settings['selected_posts'],
						'posts_per_page' => -1,
						'orderby'        => 'post__in',
						'post_type'      => 'any',
					)
				);

				foreach ( $yonkatk_posts as $yonkatk_post ) {
					$yonkatk_items[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( get_permalink( $yonkatk_post->ID ) ),
						esc_html( get_the_title( $yonkatk_post->ID ) )
					);
				}
			} else {
				$yonkatk_posts = get_posts(
					array(
						'posts_per_page' => absint( $yonkatk_settings['post_count'] ),
						'post_type'      => $yonkatk_settings['post_type'],
						'post_status'    => 'publish',
						'orderby'        => 'date',
						'order'          => 'DESC',
					)
				);

				foreach ( $yonkatk_posts as $yonkatk_post ) {
					$yonkatk_items[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( get_permalink( $yonkatk_post->ID ) ),
						esc_html( get_the_title( $yonkatk_post->ID ) )
					);
				}
			}

			if ( empty( $yonkatk_items ) ) {
				return;
			}

			self::$yonkatk_rendered = true;

			$yonkatk_separator_html = sprintf( '<span class="yonkatk-marquee-announcement-separator">%s</span>', esc_html( $yonkatk_settings['separator'] ) );
			$yonkatk_ticker_text    = implode( $yonkatk_separator_html, $yonkatk_items );
			$yonkatk_pause_class    = ! empty( $yonkatk_settings['pause_on_hover'] ) ? ' yonkatk-pause-on-hover' : '';

			?>
			<div class="yonkatk-marquee-announcement-bar<?php echo esc_attr( $yonkatk_pause_class ); ?>">
				<div class="yonkatk-marquee-announcement-content">
					<span class="yonkatk-marquee-announcement-item"><?php echo $yonkatk_ticker_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
			</div>
			<?php
		}

		/**
		 * Render the plugin options admin page.
		 */
		public function yonkatk_render_admin_page() {
			$yonkatk_settings  = $this->yonkatk_get_settings();
			$yonkatk_all_posts = get_posts(
				array(
					'posts_per_page' => 50,
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => 'publish',
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			?>
			<div class="wrap yonkatk-marquee-admin-wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( '📢 Yonka Admin Toolkit › Marquee Announcement', 'yonka-admin-toolkit' ); ?></h1>

				<p><?php esc_html_e( 'Configure the scrolling announcement bar displayed globally at the top of your site.', 'yonka-admin-toolkit' ); ?></p>

				<form method="post" action="options.php" class="yonkatk-marquee-admin-card">
					<?php settings_fields( 'yonkatk_marquee_announcement_group' ); ?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Announcement Bar', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<label for="yonkatk_enabled">
										<input type="checkbox" id="yonkatk_enabled" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[enabled]" value="1" <?php checked( $yonkatk_settings['enabled'], 1 ); ?> />
										<?php esc_html_e( 'Display top announcement bar on frontend', 'yonka-admin-toolkit' ); ?>
									</label>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Sticky Position', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<label for="yonkatk_sticky">
										<input type="checkbox" id="yonkatk_sticky" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[sticky]" value="1" <?php checked( $yonkatk_settings['sticky'], 1 ); ?> />
										<?php esc_html_e( 'Keep announcement bar fixed at the top when scrolling', 'yonka-admin-toolkit' ); ?>
									</label>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_ticker_source"><?php esc_html_e( 'Content Source', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<select id="yonkatk_ticker_source" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[source]">
										<option value="recent_posts" <?php selected( $yonkatk_settings['source'], 'recent_posts' ); ?>><?php esc_html_e( 'Recent Posts', 'yonka-admin-toolkit' ); ?></option>
										<option value="selected_posts" <?php selected( $yonkatk_settings['source'], 'selected_posts' ); ?>><?php esc_html_e( 'Selected Posts / Pages', 'yonka-admin-toolkit' ); ?></option>
										<option value="custom" <?php selected( $yonkatk_settings['source'], 'custom' ); ?>><?php esc_html_e( 'Custom Text / HTML', 'yonka-admin-toolkit' ); ?></option>
									</select>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_post_count"><?php esc_html_e( 'Number of Recent Posts', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<input type="number" id="yonkatk_post_count" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[post_count]" value="<?php echo esc_attr( $yonkatk_settings['post_count'] ); ?>" min="1" max="20" class="small-text" />
									<p class="description"><?php esc_html_e( 'Applies when Content Source is set to "Recent Posts".', 'yonka-admin-toolkit' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_selected_posts"><?php esc_html_e( 'Select Specific Posts', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<select id="yonkatk_selected_posts" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[selected_posts][]" multiple="multiple" style="height: 140px; min-width: 320px;">
										<?php foreach ( $yonkatk_all_posts as $yonkatk_p ) : ?>
											<option value="<?php echo esc_attr( $yonkatk_p->ID ); ?>" <?php echo in_array( $yonkatk_p->ID, $yonkatk_settings['selected_posts'], true ) ? 'selected' : ''; ?>>
												<?php echo esc_html( $yonkatk_p->post_title ); ?> (<?php echo esc_html( $yonkatk_p->post_type ); ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Hold Ctrl (or Cmd on Mac) to select multiple posts. Applies when Content Source is set to "Selected Posts".', 'yonka-admin-toolkit' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_custom_text"><?php esc_html_e( 'Custom Content', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<textarea id="yonkatk_custom_text" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[custom_text]" rows="4" class="large-text"><?php echo esc_textarea( $yonkatk_settings['custom_text'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Applies when Content Source is set to "Custom Text / HTML". HTML links are allowed.', 'yonka-admin-toolkit' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_ticker_separator"><?php esc_html_e( 'Item Separator Symbol', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<input type="text" id="yonkatk_ticker_separator" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[separator]" value="<?php echo esc_attr( $yonkatk_settings['separator'] ); ?>" class="regular-text" style="width: 80px;" />
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_ticker_speed"><?php esc_html_e( 'Animation Speed (seconds)', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<input type="number" id="yonkatk_ticker_speed" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[speed]" value="<?php echo esc_attr( $yonkatk_settings['speed'] ); ?>" min="1" max="100" class="small-text" />
									<p class="description"><?php esc_html_e( 'Lower numbers scroll faster, higher numbers scroll slower.', 'yonka-admin-toolkit' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_ticker_direction"><?php esc_html_e( 'Scroll Direction', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<select id="yonkatk_ticker_direction" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[direction]">
										<option value="left" <?php selected( $yonkatk_settings['direction'], 'left' ); ?>><?php esc_html_e( 'Left', 'yonka-admin-toolkit' ); ?></option>
										<option value="right" <?php selected( $yonkatk_settings['direction'], 'right' ); ?>><?php esc_html_e( 'Right', 'yonka-admin-toolkit' ); ?></option>
									</select>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Pause on Hover', 'yonka-admin-toolkit' ); ?></th>
								<td>
									<label for="yonkatk_pause_on_hover">
										<input type="checkbox" id="yonkatk_pause_on_hover" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[pause_on_hover]" value="1" <?php checked( $yonkatk_settings['pause_on_hover'], 1 ); ?> />
										<?php esc_html_e( 'Pause the scrolling animation when mouse hovers over the bar', 'yonka-admin-toolkit' ); ?>
									</label>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_bg_color"><?php esc_html_e( 'Background Color', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<input type="color" id="yonkatk_bg_color" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[bg_color]" value="<?php echo esc_attr( $yonkatk_settings['bg_color'] ); ?>" />
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="yonkatk_text_color"><?php esc_html_e( 'Text Color', 'yonka-admin-toolkit' ); ?></label></th>
								<td>
									<input type="color" id="yonkatk_text_color" name="<?php echo esc_attr( $this->yonkatk_option_name ); ?>[text_color]" value="<?php echo esc_attr( $yonkatk_settings['text_color'] ); ?>" />
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( __( 'Save Announcement Settings', 'yonka-admin-toolkit' ) ); ?>
				</form>
			</div>
			<?php
		}
	}

	new Yonkatk_Marquee_Announcement();
}
