<?php
/**
 * Quick Notes & To-Do Checklist Module for Yonka Admin Toolkit
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Yonkatk_Quick_Notes' ) ) {

	/**
	 * Class Yonkatk_Quick_Notes
	 */
	class Yonkatk_Quick_Notes {

		/**
		 * Register WordPress hooks.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_submenu' ), 45 );
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
			add_action( 'admin_init', array( $this, 'handle_actions' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Enqueue required CSS styles and JavaScript files for admin area.
		 *
		 * @param string $hook The current admin page hook.
		 */
		public function enqueue_assets( $hook ) {
			if ( 'index.php' === $hook || strpos( $hook, 'yonkatk-quick-notes' ) !== false ) {
				wp_enqueue_style(
					'yonkatk-quick-notes-style',
					plugin_dir_url( __DIR__ ) . 'assets/css/yonkatk-quick-notes.css',
					array(),
					'1.0.0'
				);

				wp_enqueue_script(
					'yonkatk-quick-notes-script',
					plugin_dir_url( __DIR__ ) . 'assets/js/yonkatk-quick-notes.js',
					array(),
					'1.0.0',
					true
				);
			}
		}

		/**
		 * Add submenu item under Yonka Admin Toolkit menu.
		 */
		public function add_submenu() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Quick Notes', 'yonka-admin-toolkit' ),
				__( '📝 Quick Notes', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-quick-notes',
				array( $this, 'render_admin_page' ),
				45
			);
		}

		/**
		 * Process form submissions and GET actions (Create, Delete, Toggle completed state).
		 */
		public function handle_actions() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Handle Add Note request.
			if ( isset( $_POST['add_note'] ) && check_admin_referer( 'add_note_action', 'note_nonce' ) ) {
				$title       = isset( $_POST['note_title'] ) ? sanitize_text_field( wp_unslash( $_POST['note_title'] ) ) : '';
				$content     = isset( $_POST['note_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ) : '';
				$color_class = isset( $_POST['note_color_class'] ) ? sanitize_html_class( wp_unslash( $_POST['note_color_class'] ) ) : 'yellow';
				$is_todo     = isset( $_POST['is_todo'] ) ? 1 : 0;

				if ( ! empty( $title ) || ! empty( $content ) ) {
					$notes = get_option( 'yonkatk_quick_notes_data', array() );
					$user  = wp_get_current_user();

					$notes[] = array(
						'id'          => uniqid( 'note_' ),
						'title'       => $title,
						'content'     => $content,
						'color_class' => $color_class,
						'is_todo'     => $is_todo,
						'completed'   => 0,
						'created_by'  => $user->display_name ? $user->display_name : $user->user_login,
						'date'        => current_time( 'mysql' ),
					);

					update_option( 'yonkatk_quick_notes_data', $notes, false );
				}

				wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-quick-notes' ) );
				exit;
			}

			// Handle Delete Note request.
			if ( isset( $_GET['action'] ) && 'delete_note' === $_GET['action'] && isset( $_GET['note_id'] ) ) {
				$note_id = sanitize_text_field( wp_unslash( $_GET['note_id'] ) );

				if ( check_admin_referer( 'delete_note_' . $note_id ) ) {
					$notes = get_option( 'yonkatk_quick_notes_data', array() );
					$notes = array_filter(
						$notes,
						function ( $n ) use ( $note_id ) {
							return isset( $n['id'] ) && $n['id'] !== $note_id;
						}
					);

					update_option( 'yonkatk_quick_notes_data', array_values( $notes ), false );
				}

				wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-quick-notes' ) );
				exit;
			}

			// Handle Toggle Checklist Status request.
			if ( isset( $_GET['action'] ) && 'toggle_note' === $_GET['action'] && isset( $_GET['note_id'] ) ) {
				$note_id = sanitize_text_field( wp_unslash( $_GET['note_id'] ) );

				if ( check_admin_referer( 'toggle_note_' . $note_id ) ) {
					$notes = get_option( 'yonkatk_quick_notes_data', array() );

					foreach ( $notes as &$n ) {
						if ( isset( $n['id'] ) && $n['id'] === $note_id ) {
							$n['completed'] = empty( $n['completed'] ) ? 1 : 0;
							break;
						}
					}

					update_option( 'yonkatk_quick_notes_data', $notes, false );
				}

				$redirect = isset( $_GET['from_db'] ) ? admin_url( 'index.php' ) : admin_url( 'admin.php?page=yonkatk-quick-notes' );
				wp_safe_redirect( $redirect );
				exit;
			}
		}

		/**
		 * Render main Quick Notes admin administration page.
		 */
		public function render_admin_page() {
			$notes = get_option( 'yonkatk_quick_notes_data', array() );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( '📝 Yonka Admin Toolkit › Quick Notes', 'yonka-admin-toolkit' ); ?></h1>
				<p><?php esc_html_e( 'Manage sticky notes and critical site maintenance tasks shared across all administrators.', 'yonka-admin-toolkit' ); ?></p>

				<div class="yonkatk-notes-wrapper">
					<!-- Create Note Form -->
					<div class="yonkatk-notes-form-card">
						<h2><?php esc_html_e( 'Add New Note / Task', 'yonka-admin-toolkit' ); ?></h2>
						<form method="post" action="">
							<?php wp_nonce_field( 'add_note_action', 'note_nonce' ); ?>
							
							<p>
								<label><strong><?php esc_html_e( 'Title:', 'yonka-admin-toolkit' ); ?></strong></label><br>
								<input type="text" name="note_title" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Update SSL Certificate', 'yonka-admin-toolkit' ); ?>" required />
							</p>

							<p>
								<label><strong><?php esc_html_e( 'Content / Instructions:', 'yonka-admin-toolkit' ); ?></strong></label><br>
								<textarea name="note_content" class="widefat" rows="4" placeholder="<?php esc_attr_e( 'Detailed instructions or reminder...', 'yonka-admin-toolkit' ); ?>"></textarea>
							</p>

							<p>
								<label><strong><?php esc_html_e( 'Sticky Color:', 'yonka-admin-toolkit' ); ?></strong></label><br>
								<select name="note_color_class" class="widefat">
									<option value="yellow"><?php esc_html_e( '🟡 Yellow (General Note)', 'yonka-admin-toolkit' ); ?></option>
									<option value="red"><?php esc_html_e( '🔴 Red (Urgent / Warning)', 'yonka-admin-toolkit' ); ?></option>
									<option value="blue"><?php esc_html_e( '🔵 Blue (Informational)', 'yonka-admin-toolkit' ); ?></option>
									<option value="green"><?php esc_html_e( '🟢 Green (Feature / Completed Ideal)', 'yonka-admin-toolkit' ); ?></option>
								</select>
							</p>

							<p>
								<label>
									<input type="checkbox" name="is_todo" value="1" />
									<strong><?php esc_html_e( 'Treat as Actionable Task (Checklist)', 'yonka-admin-toolkit' ); ?></strong>
								</label>
							</p>

							<p>
								<input type="submit" name="add_note" class="button button-primary" value="<?php esc_attr_e( 'Save Note', 'yonka-admin-toolkit' ); ?>" />
							</p>
						</form>
					</div>

					<!-- Notes Grid -->
					<div class="yonkatk-notes-grid">
						<?php if ( empty( $notes ) ) : ?>
							<p><em><?php esc_html_e( 'No sticky notes found. Create your first note on the left panel!', 'yonka-admin-toolkit' ); ?></em></p>
						<?php else : ?>
							<?php
							foreach ( array_reverse( $notes ) as $note ) :
								$color_class         = ! empty( $note['color_class'] ) ? $note['color_class'] : 'yellow';
								$completed_class     = ! empty( $note['completed'] ) ? ' yonkatk-note-completed' : '';
								$delete_confirm_text = __( 'Delete this note?', 'yonka-admin-toolkit' );
								?>
								<div class="yonkatk-note-card yonkatk-note-color-<?php echo esc_attr( $color_class ); ?>">
									<h3 class="yonkatk-note-title<?php echo esc_attr( $completed_class ); ?>">
										<?php echo esc_html( $note['title'] ); ?>
									</h3>
									
									<p class="yonkatk-note-content<?php echo esc_attr( $completed_class ); ?>">
										<?php echo nl2br( esc_html( $note['content'] ) ); ?>
									</p>

									<div class="yonkatk-note-footer">
										<span>
											<?php
											/* translators: %s: Author name */
											printf( esc_html__( 'By %s', 'yonka-admin-toolkit' ), esc_html( $note['created_by'] ) );
											?>
										</span>

										<div class="yonkatk-note-actions">
											<?php if ( ! empty( $note['is_todo'] ) ) : ?>
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=yonkatk-quick-notes&action=toggle_note&note_id=' . $note['id'] ), 'toggle_note_' . $note['id'] ) ); ?>" class="button button-small">
													<?php echo ! empty( $note['completed'] ) ? esc_html__( '↩️ Undo', 'yonka-admin-toolkit' ) : esc_html__( '✅ Done', 'yonka-admin-toolkit' ); ?>
												</a>
											<?php endif; ?>

											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=yonkatk-quick-notes&action=delete_note&note_id=' . $note['id'] ), 'delete_note_' . $note['id'] ) ); ?>" 
												class="yonkatk-note-delete-btn" 
												data-confirm="<?php echo esc_attr( $delete_confirm_text ); ?>">✕</a>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Register dashboard widget for Quick Notes.
		 */
		public function add_dashboard_widget() {
			wp_add_dashboard_widget(
				'csi_notes_widget',
				__( '🚨 CRITICAL SITE NOTES & TASKS', 'yonka-admin-toolkit' ),
				array( $this, 'render_dashboard_widget' )
			);

			// Safely move widget placement to right column.
			global $wp_meta_boxes;
			if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['csi_notes_widget'] ) ) {
				$widget = $wp_meta_boxes['dashboard']['normal']['core']['csi_notes_widget'];
				unset( $wp_meta_boxes['dashboard']['normal']['core']['csi_notes_widget'] );

				// @codingStandardsIgnoreStart
				$wp_meta_boxes['dashboard']['side']['core']['csi_notes_widget'] = $widget;
				// @codingStandardsIgnoreEnd
			}
		}

		/**
		 * Render content inside dashboard widget.
		 */
		public function render_dashboard_widget() {
			$notes = get_option( 'yonkatk_quick_notes_data', array() );
			?>
			<div class="csi-banner">
				<svg viewBox="0 0 24 24">
					<path d="M12 2L1 21h22L12 2zm0 3.45L20.15 19H3.85L12 5.45zM11 10h2v4h-2zm0 5h2v2h-2z"/>
				</svg>
				<?php esc_html_e( 'RESTRICTED AREA: ADMIN REMINDERS', 'yonka-admin-toolkit' ); ?>
			</div>

			<?php if ( empty( $notes ) ) : ?>
				<p class="csi-empty-state">
					<em><?php esc_html_e( 'No active notes or urgent tasks reported.', 'yonka-admin-toolkit' ); ?></em>
				</p>
			<?php else : ?>
				<div class="csi-notes-scroll-container">
					<?php
					foreach ( array_reverse( $notes ) as $note ) :
						$color_class = ! empty( $note['color_class'] ) ? $note['color_class'] : 'yellow';
						?>
						<div class="csi-item yonkatk-dash-color-<?php echo esc_attr( $color_class ); ?> <?php echo ! empty( $note['completed'] ) ? 'completed' : ''; ?>">
							<div class="csi-item-title">
								<span><?php echo esc_html( $note['title'] ); ?></span>
								<?php if ( ! empty( $note['is_todo'] ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'index.php?action=toggle_note&from_db=1&note_id=' . $note['id'] ), 'toggle_note_' . $note['id'] ) ); ?>" class="csi-toggle">
										<?php echo ! empty( $note['completed'] ) ? esc_html__( '[Undo]', 'yonka-admin-toolkit' ) : esc_html__( '[Check]', 'yonka-admin-toolkit' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $note['content'] ) ) : ?>
								<div class="csi-item-desc">
									<?php echo nl2br( esc_html( $note['content'] ) ); ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="csi-footer-link">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=yonkatk-quick-notes' ) ); ?>">
					<?php esc_html_e( '+ Manage All Notes ›', 'yonka-admin-toolkit' ); ?>
				</a>
			</div>
			<?php
		}
	}

	new Yonkatk_Quick_Notes();
}
