<?php
/**
 * Database Information Module for Yonka Admin Toolkit
 *
 * @package YonkaAdminToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

if ( ! class_exists( 'Yonkatk_Database_Information' ) ) {

	/**
	 * Class Yonkatk_Database_Information
	 * Analyzes database metrics, storage allocation, overhead, and top tables.
	 */
	class Yonkatk_Database_Information {

		/**
		 * Constructor to attach WordPress hooks.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 35 );
			add_action( 'admin_init', array( $this, 'handle_actions' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		/**
		 * Register Submenu Page under Yonka Admin Toolkit.
		 */
		public function add_plugin_page() {
			add_submenu_page(
				'yonka-admin-toolkit',
				__( 'Database Information', 'yonka-admin-toolkit' ),
				__( '🗄️ Database Information', 'yonka-admin-toolkit' ),
				'manage_options',
				'yonkatk-database-information',
				array( $this, 'render_admin_page' ),
				35
			);
		}

		/**
		 * Enqueue Dedicated Styles for Database Information Admin View.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( false === strpos( $hook, 'yonkatk-database-information' ) ) {
				return;
			}

			wp_enqueue_style(
				'yonkatk-database-info-css',
				YONKATK_URL . 'assets/css/admin-database-info.css',
				array(),
				YONKATK_VERSION
			);
		}

		/**
		 * Handle transient cache clearing action.
		 */
		public function handle_actions() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( isset( $_POST['yonkatk_refresh_db_cache'] ) && check_admin_referer( 'yonkatk_refresh_db_cache_nonce' ) ) {
				delete_transient( 'yonkatk_db_status_cache' );
				wp_safe_redirect( admin_url( 'admin.php?page=yonkatk-database-information' ) );
				exit;
			}
		}

		/**
		 * Helper method to format raw bytes into human-readable units.
		 *
		 * @param  float|int $bytes     Raw size in bytes.
		 * @param  int       $precision Decimal places precision.
		 * @return string Formatted byte value with unit.
		 */
		private function format_bytes( $bytes, $precision = 2 ) {
			$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
			$bytes  = max( (float) $bytes, 0 );
			$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow    = min( $pow, count( $units ) - 1 );
			$bytes /= pow( 1024, $pow );

			return round( $bytes, $precision ) . ' ' . $units[ $pow ];
		}

		/**
		 * Render Database Information HTML Page.
		 */
		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'yonka-admin-toolkit' ) );
			}

			global $wpdb;

			// Fetch metrics from transient cache or query DB directly.
			$db_cache = get_transient( 'yonkatk_db_status_cache' );

			if ( false === $db_cache || empty( $db_cache['parsed_tables'] ) ) {

				$tables = array();
				$prefix = $wpdb->esc_like( $wpdb->prefix ) . '%';

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- System DB status fetch, cached via transient.
				$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $prefix ), ARRAY_A );

				if ( empty( $tables ) ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback DB status query.
					$tables = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
				}

				if ( empty( $tables ) ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback information_schema query.
					$tables = $wpdb->get_results(
						'SELECT 
							TABLE_NAME AS name, 
							ENGINE AS engine, 
							TABLE_ROWS AS rows, 
							DATA_LENGTH AS data_length, 
							INDEX_LENGTH AS index_length, 
							DATA_FREE AS data_free 
						 FROM information_schema.TABLES 
						 WHERE TABLE_SCHEMA = DATABASE()',
						ARRAY_A
					);
				}

				$data_size_bytes  = 0;
				$index_size_bytes = 0;
				$overhead_bytes   = 0;
				$parsed_tables    = array();

				if ( ! empty( $tables ) && is_array( $tables ) ) {
					foreach ( $tables as $table ) {
						$table = array_change_key_case( $table, CASE_LOWER );

						$d_size = isset( $table['data_length'] ) ? (float) $table['data_length'] : 0;
						$i_size = isset( $table['index_length'] ) ? (float) $table['index_length'] : 0;
						$free   = isset( $table['data_free'] ) ? (float) $table['data_free'] : 0;

						$data_size_bytes  += $d_size;
						$index_size_bytes += $i_size;
						$overhead_bytes   += $free;

						$table_name = isset( $table['name'] ) ? $table['name'] : ( isset( $table['table_name'] ) ? $table['table_name'] : '—' );

						$parsed_tables[] = array(
							'name'        => $table_name,
							'engine'      => ! empty( $table['engine'] ) ? $table['engine'] : 'InnoDB',
							'rows'        => isset( $table['rows'] ) ? (int) $table['rows'] : ( isset( $table['table_rows'] ) ? (int) $table['table_rows'] : 0 ),
							'total_bytes' => ( $d_size + $i_size ),
							'free_bytes'  => $free,
						);
					}
				}

				// Sort tables by total size descending.
				usort(
					$parsed_tables,
					function ( $a, $b ) {
						return $b['total_bytes'] <=> $a['total_bytes'];
					}
				);

				$db_cache = array(
					'total_tables'     => count( $parsed_tables ),
					'data_size_bytes'  => $data_size_bytes,
					'index_size_bytes' => $index_size_bytes,
					'overhead_bytes'   => $overhead_bytes,
					'parsed_tables'    => $parsed_tables,
					'last_db_error'    => $wpdb->last_error,
				);

				if ( ! empty( $parsed_tables ) ) {
					set_transient( 'yonkatk_db_status_cache', $db_cache, HOUR_IN_SECONDS );
				}
			}

			$total_tables     = $db_cache['total_tables'];
			$data_size_bytes  = $db_cache['data_size_bytes'];
			$index_size_bytes = $db_cache['index_size_bytes'];
			$overhead_bytes   = $db_cache['overhead_bytes'];
			$parsed_tables    = $db_cache['parsed_tables'];

			$total_size_bytes = $data_size_bytes + $index_size_bytes;
			$top_10_tables    = array_slice( $parsed_tables, 0, 10 );
			$index_ratio      = $data_size_bytes > 0 ? ( $index_size_bytes / $data_size_bytes ) * 100 : 0;
			?>
			<div class="wrap yonkatk-db-wrap">
				<div class="yonkatk-db-header">
					<h1><?php esc_html_e( '🗄️ Yonka Admin Toolkit › Database Information', 'yonka-admin-toolkit' ); ?></h1>
					<form method="post" class="yonkatk-db-inline-form">
			<?php wp_nonce_field( 'yonkatk_refresh_db_cache_nonce' ); ?>
			<?php submit_button( __( '🔄 Refresh Metrics', 'yonka-admin-toolkit' ), 'secondary', 'yonkatk_refresh_db_cache', false ); ?>
					</form>
				</div>

			<?php if ( ! empty( $db_cache['last_db_error'] ) ) : ?>
					<div class="notice notice-error yonkatk-db-error-notice">
						<strong><?php esc_html_e( 'SQL Error:', 'yonka-admin-toolkit' ); ?></strong> <code><?php echo esc_html( $db_cache['last_db_error'] ); ?></code>
					</div>
			<?php endif; ?>

				<p><?php esc_html_e( 'Real-time metrics regarding your database size, index efficiency, and server footprint.', 'yonka-admin-toolkit' ); ?></p>

				<!-- Metrics Overview Cards -->
				<div class="yonkatk-db-cards-grid">
					 
					<div class="yonkatk-db-card yonkatk-card-blue">
						<div class="yonkatk-card-title"><?php esc_html_e( 'Total DB Size', 'yonka-admin-toolkit' ); ?></div>
						<div class="yonkatk-card-value"><?php echo esc_html( $this->format_bytes( $total_size_bytes ) ); ?></div>
						<span class="yonkatk-card-subtext">
			<?php
			/* translators: %d: Total number of database tables */
			printf( esc_html__( '%d Total Tables', 'yonka-admin-toolkit' ), absint( $total_tables ) );
			?>
						</span>
					</div>

					<div class="yonkatk-db-card yonkatk-card-green">
						<div class="yonkatk-card-title"><?php esc_html_e( 'Data vs Index', 'yonka-admin-toolkit' ); ?></div>
						<div class="yonkatk-card-value yonkatk-val-compact"><?php echo esc_html( $this->format_bytes( $data_size_bytes ) ); ?> / <?php echo esc_html( $this->format_bytes( $index_size_bytes ) ); ?></div>
						<span class="yonkatk-card-subtext">
			<?php
				/* translators: %s: Search index ratio percentage */
				printf( esc_html__( 'Search index ratio: %s%%', 'yonka-admin-toolkit' ), esc_html( number_format_i18n( $index_ratio, 1 ) ) );
			?>
						</span>
					</div>

					<div class="yonkatk-db-card yonkatk-card-amber">
						<div class="yonkatk-card-title"><?php esc_html_e( 'Unused Space (Overhead)', 'yonka-admin-toolkit' ); ?></div>
						<div class="yonkatk-card-value"><?php echo esc_html( $this->format_bytes( $overhead_bytes ) ); ?></div>
						<span class="yonkatk-card-subtext"><?php echo ( $overhead_bytes > 5 * 1024 * 1024 ) ? esc_html__( '⚠️ Optimization recommended', 'yonka-admin-toolkit' ) : esc_html__( '✅ Optimal storage efficiency', 'yonka-admin-toolkit' ); ?></span>
					</div>

					<div class="yonkatk-db-card yonkatk-card-purple">
						<div class="yonkatk-card-title"><?php esc_html_e( 'Database Engine', 'yonka-admin-toolkit' ); ?></div>
						<div class="yonkatk-card-value"><?php echo isset( $top_10_tables[0]['engine'] ) ? esc_html( $top_10_tables[0]['engine'] ) : 'MySQL'; ?></div>
						<span class="yonkatk-card-subtext"><?php esc_html_e( 'Primary table engine', 'yonka-admin-toolkit' ); ?></span>
					</div>

				</div>

				<!-- Top Tables Breakdown -->
				<div class="yonkatk-db-panel">
					<h2><?php esc_html_e( '🏆 Top 10 Largest Database Tables', 'yonka-admin-toolkit' ); ?></h2>
					<p class="yonkatk-db-panel-desc"><?php esc_html_e( 'Tables taking up the most disk space on your server:', 'yonka-admin-toolkit' ); ?></p>

					<table class="wp-list-table widefat fixed striped table-view-list yonkatk-db-table">
						<thead>
							<tr>
								<th><strong><?php esc_html_e( 'Table Name', 'yonka-admin-toolkit' ); ?></strong></th>
								<th><strong><?php esc_html_e( 'Engine', 'yonka-admin-toolkit' ); ?></strong></th>
								<th><strong><?php esc_html_e( 'Rows Count', 'yonka-admin-toolkit' ); ?></strong></th>
								<th><strong><?php esc_html_e( 'Total Size', 'yonka-admin-toolkit' ); ?></strong></th>
								<th><strong><?php esc_html_e( 'Overhead', 'yonka-admin-toolkit' ); ?></strong></th>
							</tr>
						</thead>
						<tbody>
			<?php if ( ! empty( $top_10_tables ) ) : ?>
					<?php foreach ( $top_10_tables as $t ) : ?>
									<tr>
										<td><code><?php echo esc_html( $t['name'] ); ?></code></td>
										<td><span class="yonkatk-badge"><?php echo esc_html( $t['engine'] ); ?></span></td>
										<td><?php echo esc_html( number_format_i18n( $t['rows'] ) ); ?></td>
										<td><strong><?php echo esc_html( $this->format_bytes( $t['total_bytes'] ) ); ?></strong></td>
										<td><?php echo $t['free_bytes'] > 0 ? '<span class="yonkatk-overhead-warn">' . esc_html( $this->format_bytes( $t['free_bytes'] ) ) . '</span>' : '0 B'; ?></td>
									</tr>
					<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="5"><?php esc_html_e( 'No database tables found.', 'yonka-admin-toolkit' ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}
	}

	// Initialize module class.
	new Yonkatk_Database_Information();
}
