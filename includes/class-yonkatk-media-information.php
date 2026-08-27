<?php
/**
 * Media Information Module for Yonka Admin Toolkit
 *
 * @package Yonka_Admin_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

if ( ! class_exists( 'Yonkatk_Media_Information' ) ) {

	/**
	 * Class Yonkatk_Media_Information
	 * Handles media stats analysis and admin interface for Yonka Admin Toolkit.
	 */
	class Yonkatk_Media_Information {

		/**
		 * Constructor to register hooks
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'wp_ajax_yonkatk_get_media_stats', array( $this, 'ajax_get_media_stats' ) );
		}

		/**
		 * Add the page as a submenu in Yonka Admin Toolkit
		 */
		public function add_admin_menu() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Media Inventory', 'yonka-admin-toolkit' ),
				__( '🖼️ Media Inventory', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-media-information',
				array( $this, 'render_admin_page' ),
				20
			);
		}

		/**
		 * Enqueue external admin stylesheet and script assets.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_assets( string $hook ) {
			if ( false === strpos( $hook, 'yonkatk-media-information' ) ) {
				return;
			}

			wp_enqueue_style( 'common' );

			// Register and enqueue external CSS file.
			wp_enqueue_style(
				'yonkatk-mo-admin-style',
				plugin_dir_url( __DIR__ ) . 'assets/css/admin-media-information.css',
				array(),
				'1.0.0'
			);

			// Register and enqueue external JS file.
			wp_enqueue_script(
				'yonkatk-mo-admin-script',
				plugin_dir_url( __DIR__ ) . 'assets/js/admin-media-information.js',
				array(),
				'1.0.0',
				true
			);

			// Pass backend values and localized strings to JavaScript.
			$script_vars = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'yonkatk_mo_nonce' ),
				'i18n'    => array(
					'errorLoading' => __( 'Error loading data.', 'yonka-admin-toolkit' ),
					'ajaxError'    => __( 'AJAX Error: ', 'yonka-admin-toolkit' ),
					'noFiles'      => __( 'No files found for the selected filter.', 'yonka-admin-toolkit' ),
					'noAlt'        => __( 'No ALT', 'yonka-admin-toolkit' ),
					'editLink'     => __( 'Edit ↗', 'yonka-admin-toolkit' ),
				),
			);

			wp_localize_script( 'yonkatk-mo-admin-script', 'yonkatkMoData', $script_vars );
		}

		/**
		 * AJAX handler to retrieve media data and statistics
		 */
		public function ajax_get_media_stats() {
			check_ajax_referer( 'yonkatk_mo_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$query = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
				)
			);

			$items = array(
				'images'    => array(),
				'documents' => array(),
				'media'     => array(),
				'others'    => array(),
			);

			$total_bytes       = 0;
			$total_oversized   = 0;
			$total_missing_alt = 0;

			foreach ( $query->posts as $post ) {
				$file_path = get_attached_file( $post->ID );
				$file_size = ( $file_path && file_exists( $file_path ) ) ? filesize( $file_path ) : 0;
				$mime_type = get_post_mime_type( $post->ID );

				$total_bytes += $file_size;
				$is_oversized = $file_size > ( 1024 * 1024 ); // Flag files larger than 1 MB.
				if ( $is_oversized ) {
					++$total_oversized;
				}

				$has_alt  = false;
				$category = 'others';

				if ( strpos( $mime_type, 'image/' ) === 0 ) {
					$category = 'images';
					$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
					$has_alt  = ! empty( trim( $alt_text ) );
					if ( ! $has_alt ) {
						++$total_missing_alt;
					}
				} elseif ( strpos( $mime_type, 'application/' ) === 0 || strpos( $mime_type, 'text/' ) === 0 ) {
					$category = 'documents';
				} elseif ( strpos( $mime_type, 'video/' ) === 0 || strpos( $mime_type, 'audio/' ) === 0 ) {
					$category = 'media';
				}

				$thumb_url = false;
				if ( 'images' === $category ) {
					$thumb     = wp_get_attachment_image_src( $post->ID, 'thumbnail' );
					$thumb_url = $thumb ? $thumb[0] : false;
				}

				$items[ $category ][] = array(
					'id'             => $post->ID,
					'title'          => get_the_title( $post->ID ) ? get_the_title( $post->ID ) : __( '(No title)', 'yonka-admin-toolkit' ),
					'mime'           => $mime_type,
					'size_bytes'     => $file_size,
					'size_formatted' => size_format( $file_size, 2 ),
					'is_oversized'   => $is_oversized,
					'has_alt'        => $has_alt,
					'date'           => get_the_date( 'Y-m-d', $post->ID ),
					'thumb_url'      => $thumb_url,
					'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
				);
			}

			$data = array(
				'summary' => array(
					'total_count'       => count( $query->posts ),
					'total_size'        => size_format( $total_bytes, 2 ),
					'total_oversized'   => $total_oversized,
					'total_missing_alt' => $total_missing_alt,
				),
				'items'   => $items,
			);

			wp_send_json_success( $data );
		}

		/**
		 * Render the admin page interface
		 */
		public function render_admin_page() {
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( '🖼️ Yonka Admin Toolkit › Media Inventory', 'yonka-admin-toolkit' ); ?></h1>
				<hr class="wp-header-end">

				<!-- Statistics Cards -->
				<div class="yonkatk-mo-stats-grid">
					<div class="yonkatk-mo-stat-card blue">
						<div class="yonkatk-mo-stat-label"><?php esc_html_e( 'Total Files', 'yonka-admin-toolkit' ); ?></div>
						<div id="stat-count" class="yonkatk-mo-stat-value">-</div>
					</div>
					<div class="yonkatk-mo-stat-card green">
						<div class="yonkatk-mo-stat-label"><?php esc_html_e( 'Total Size', 'yonka-admin-toolkit' ); ?></div>
						<div id="stat-size" class="yonkatk-mo-stat-value">-</div>
					</div>
					<div class="yonkatk-mo-stat-card orange">
						<div class="yonkatk-mo-stat-label"><?php esc_html_e( 'Files > 1 MB', 'yonka-admin-toolkit' ); ?></div>
						<div id="stat-oversized" class="yonkatk-mo-stat-value orange">-</div>
					</div>
					<div class="yonkatk-mo-stat-card red">
						<div class="yonkatk-mo-stat-label"><?php esc_html_e( 'Images without ALT', 'yonka-admin-toolkit' ); ?></div>
						<div id="stat-alt" class="yonkatk-mo-stat-value red">-</div>
					</div>
				</div>

				<!-- File Type Tabs -->
				<nav class="nav-tab-wrapper wp-clearfix yonkatk-mo-tabs-wrapper">
					<a href="javascript:void(0)" class="nav-tab yonkatk-mo-tab nav-tab-active" data-tab="images" onclick="yonkatkSwitchTab('images')">
						<?php esc_html_e( 'Images', 'yonka-admin-toolkit' ); ?> (<span id="count-images">0</span>)
					</a>
					<a href="javascript:void(0)" class="nav-tab yonkatk-mo-tab" data-tab="documents" onclick="yonkatkSwitchTab('documents')">
						<?php esc_html_e( 'Documents', 'yonka-admin-toolkit' ); ?> (<span id="count-documents">0</span>)
					</a>
					<a href="javascript:void(0)" class="nav-tab yonkatk-mo-tab" data-tab="media" onclick="yonkatkSwitchTab('media')">
						<?php esc_html_e( 'Video & Audio', 'yonka-admin-toolkit' ); ?> (<span id="count-media">0</span>)
					</a>
					<a href="javascript:void(0)" class="nav-tab yonkatk-mo-tab" data-tab="others" onclick="yonkatkSwitchTab('others')">
						<?php esc_html_e( 'Other', 'yonka-admin-toolkit' ); ?> (<span id="count-others">0</span>)
					</a>
				</nav>

				<!-- Quick Filters -->
				<div class="yonkatk-mo-filters-wrapper">
					<button type="button" class="button" onclick="yonkatkApplyFilter('all')"><?php esc_html_e( 'All', 'yonka-admin-toolkit' ); ?></button>
					<button type="button" class="button" onclick="yonkatkApplyFilter('oversized')"><?php esc_html_e( 'Only > 1MB', 'yonka-admin-toolkit' ); ?></button>
					<button type="button" class="button" id="btn-filter-alt" onclick="yonkatkApplyFilter('missing_alt')"><?php esc_html_e( 'Only missing ALT', 'yonka-admin-toolkit' ); ?></button>
				</div>

				<!-- Loading Indicator -->
				<div id="yonkatk-mo-loading" class="yonkatk-mo-loading-box">
					<span class="spinner is-active yonkatk-mo-spinner"></span>
					<?php esc_html_e( 'Loading and analyzing media library...', 'yonka-admin-toolkit' ); ?>
				</div>

				<!-- Main Table -->
				<table class="wp-list-table widefat fixed striped yonkatk-mo-hidden" id="yonkatk-mo-table">
					<thead>
						<tr>
							<th style="width: 60px;"><?php esc_html_e( 'Preview', 'yonka-admin-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Title / File', 'yonka-admin-toolkit' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Size', 'yonka-admin-toolkit' ); ?></th>
							<th style="width: 160px;"><?php esc_html_e( 'Status', 'yonka-admin-toolkit' ); ?></th>
							<th style="width: 100px; text-align: right;"><?php esc_html_e( 'Action', 'yonka-admin-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody id="yonkatk-mo-tbody">
					</tbody>
				</table>
			</div>
			<?php
		}
	}

	// Initialize module class.
	new Yonkatk_Media_Information();
}
