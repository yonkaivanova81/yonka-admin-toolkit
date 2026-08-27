<?php
/**
 * Class Yonkatk_Yearly_Statistics
 *
 * Generates an administrative interface displaying annual content statistics
 * (posts by category, pages created, and media assets uploaded) for maintenance teams.
 *
 * @package  YonkaAdminToolkit
 * @internal For site maintenance and support team reference only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Yonkatk_Yearly_Statistics' ) ) {
	/**
	 * Yonkatk_Yearly_Statistics.
	 *
	 * @since 1.0.0
	 */
	class Yonkatk_Yearly_Statistics {

		/**
		 * Initialize hooks for menu registration, AJAX endpoints, and assets enqueueing.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 40 );
			add_action( 'wp_ajax_yonkatk_cys_get_years', array( $this, 'handle_get_years' ) );
			add_action( 'wp_ajax_yonkatk_cys_get_stats_by_year', array( $this, 'handle_get_stats_by_year' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}

		/**
		 * Register the admin submenu page under custom parent slug or fallback to Tools menu.
		 */
		public function add_plugin_page() {
			global $menu;
			$parent_slug = 'yonka-admin-toolkit';

			$menu_exists = false;
			if ( is_array( $menu ) ) {
				foreach ( $menu as $item ) {
					if ( isset( $item[2] ) && $item[2] === $parent_slug ) {
						$menu_exists = true;
						break;
					}
				}
			}

			// Fallback to standard tools.php if custom top-level admin menu is missing.
			if ( ! $menu_exists ) {
				$parent_slug = 'tools.php';
			}

			add_submenu_page(
				$parent_slug,
				'Yearly Statistics',
				'📊 Yearly Statistics',
				'manage_options',
				'yonkatk-yearly-statistics',
				array( $this, 'render_admin_page' )
			);
		}

		/**
		 * Render the container HTML markup targeted by the React application.
		 */
		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'yonka-admin-toolkit' ) );
			}
			?>
			<div class="wrap">
				<h1 class="text-2xl font-bold mb-4"><?php esc_html_e( '📊 Yonka Admin Toolkit › Yearly Statistics', 'yonka-admin-toolkit' ); ?></h1>
				<div id="yonkatk-cys-app"></div>
			</div>
			<?php
		}

		/**
		 * Enqueue required JavaScript dependencies, scripts, and pass secure AJAX variables.
		 *
		 * @param string $hook The current admin page screen hook.
		 */
		public function enqueue_admin_scripts( $hook ) {
			// Restrict script execution exclusively to this plugin's screen.
			if ( strpos( $hook, 'yonkatk-yearly-statistics' ) === false ) {
				return;
			}

			// Determine correct plugin base URL safely using double dirname to escape 'includes' folder.
			$base_url = defined( 'YONKATK_URL' ) ? YONKATK_URL : plugin_dir_url( dirname( __DIR__ ) );
			$js_url   = $base_url . 'assets/js/yonkatk-cys-app.js';
			$css_url  = $base_url . 'assets/css/yonkatk-cys-admin.css';

			// 1. Enqueue CSS stylesheet.
			wp_enqueue_style(
				'yonkatk-cys-style',
				$css_url,
				array(),
				'1.0.5'
			);

			// 2. Enqueue external JavaScript file (Vanilla JS).
			wp_enqueue_script(
				'yonkatk-cys-script',
				$js_url,
				array(),
				'1.0.5', // Incremented version to bust browser cache.
				true // Load in footer.
			);

			// Expose AJAX endpoint URL and cryptographic nonce to global JS context.
			wp_localize_script(
				'yonkatk-cys-script',
				'yonkatkCysVars',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'yonkatk_cys_nonce' ),
				)
			);
		}

		/**
		 * AJAX Handler: Retrieve distinct list of years containing published content.
		 */
		public function handle_get_years() {
			check_ajax_referer( 'yonkatk_cys_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			// Retrieve cached database response if available.
			$years = get_transient( 'yonkatk_cys_years_cache' );

			if ( false === $years ) {
				global $wpdb;

				// Query database for unique years from published post dates.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$years = $wpdb->get_col(
					"SELECT DISTINCT YEAR(post_date) 
                    FROM {$wpdb->posts} 
                    WHERE post_status IN ('publish', 'inherit') 
                    ORDER BY post_date DESC"
				);

				if ( empty( $years ) ) {
					$years = array( (int) gmdate( 'Y' ) );
				} else {
					$years = array_map( 'intval', $years );
				}

				// Cache query output for 1 hour.
				set_transient( 'yonkatk_cys_years_cache', $years, HOUR_IN_SECONDS );
			}

			wp_send_json_success( $years );
		}

		/**
		 * AJAX Handler: Retrieve aggregated site statistics filtered by selected year.
		 */
		public function handle_get_stats_by_year() {
			check_ajax_referer( 'yonkatk_cys_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$year       = isset( $_POST['year'] ) ? intval( $_POST['year'] ) : intval( gmdate( 'Y' ) );
			$cache_key  = 'yonkatk_cys_stats_' . $year;
			$stats_data = get_transient( $cache_key );

			if ( false === $stats_data ) {
				global $wpdb;

				// Establish date range constraints to optimize MySQL index execution.
				$start_date = "{$year}-01-01 00:00:00";
				$end_date   = "{$year}-12-31 23:59:59";

				// 1. Fetch published posts count grouped by category.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$posts_by_cat_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT t.name AS category_name, COUNT(p.ID) AS count
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
                        INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                        INNER JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
                        WHERE tt.taxonomy = 'category'
                          AND p.post_type = 'post'
                          AND p.post_status = 'publish'
                          AND p.post_date >= %s AND p.post_date <= %s
                        GROUP BY t.term_id
                        ORDER BY count DESC",
						$start_date,
						$end_date
					)
				);

				// Fetch total aggregate published posts count.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$total_posts = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(ID) FROM {$wpdb->posts} 
                        WHERE post_type = 'post' AND post_status = 'publish' 
                        AND post_date >= %s AND post_date <= %s",
						$start_date,
						$end_date
					)
				);

				// 2. Fetch pages created within the selected year.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$pages = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title, post_date 
                        FROM {$wpdb->posts} 
                        WHERE post_type = 'page' AND post_status = 'publish' 
                        AND post_date >= %s AND post_date <= %s
                        ORDER BY post_date DESC",
						$start_date,
						$end_date
					)
				);

				// 3. Fetch media attachments grouped by MIME type.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$media_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_mime_type, COUNT(ID) as count
                        FROM {$wpdb->posts}
                        WHERE post_type = 'attachment' 
                        AND post_date >= %s AND post_date <= %s
                        GROUP BY post_mime_type
                        ORDER BY count DESC",
						$start_date,
						$end_date
					)
				);

				$media_summary = array(
					'images'    => 0,
					'documents' => 0,
					'audio'     => 0,
					'video'     => 0,
					'others'    => 0,
					'total'     => 0,
				);

				// Categorize raw MIME types into high-level asset categories.
				foreach ( $media_raw as $item ) {
					$mime                    = $item->post_mime_type;
					$cnt                     = (int) $item->count;
					$media_summary['total'] += $cnt;

					if ( strpos( $mime, 'image/' ) === 0 ) {
						$media_summary['images'] += $cnt;
					} elseif ( strpos( $mime, 'application/pdf' ) !== false
						|| strpos( $mime, 'application/msword' ) !== false
						|| strpos( $mime, 'application/vnd.openxmlformats-officedocument' ) !== false
						|| strpos( $mime, 'application/vnd.ms-excel' ) !== false
						|| strpos( $mime, 'application/vnd.ms-powerpoint' ) !== false
						|| strpos( $mime, 'text/' ) === 0
					) {
						$media_summary['documents'] += $cnt;
					} elseif ( strpos( $mime, 'audio/' ) === 0 ) {
						$media_summary['audio'] += $cnt;
					} elseif ( strpos( $mime, 'video/' ) === 0 ) {
						$media_summary['video'] += $cnt;
					} else {
						$media_summary['others'] += $cnt;
					}
				}

				$stats_data = array(
					'year'          => $year,
					'total_posts'   => $total_posts,
					'posts_by_cat'  => $posts_by_cat_raw,
					'pages'         => $pages,
					'total_pages'   => count( $pages ),
					'media_summary' => $media_summary,
					'media_raw'     => $media_raw,
				);

				// Cache final compiled dataset for 1 hour.
				set_transient( $cache_key, $stats_data, HOUR_IN_SECONDS );
			}

			wp_send_json_success( $stats_data );
		}
	}

	new Yonkatk_Yearly_Statistics();
}
