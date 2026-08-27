<?php
/**
 * Fired when the Yonka Admin Toolkit plugin is uninstalled.
 *
 * @package YonkaAdminToolkit
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * 1. DATABASE TABLES CLEANUP
 */

// Drop the custom security & activity logs table.
$yonkatk_table_name = $wpdb->prefix . 'yonkatk_security_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS {$yonkatk_table_name}" );


/**
 * 2. PLUGIN OPTIONS CLEANUP (wp_options)
 */

// Security & Log options.
delete_option( 'yonkatk_security_log_db_version' );

// Asset Cleaner options & captured cache.
delete_option( 'yonkatk__disabled_scripts' );
delete_option( 'yonkatk__disabled_styles' );
delete_option( 'yonkatk__captured_scripts' );
delete_option( 'yonkatk__captured_styles' );

// Broken Links & 404 Redirects options.
delete_option( 'yonkatk_redirect_rules' );
delete_option( 'yonkatk_404_logs' );

// Maintenance Mode options.
delete_option( 'yonkatk_maintenance_mode_enabled' );
delete_option( 'yonkatk_maintenance_mode_title' );
delete_option( 'yonkatk_maintenance_mode_message' );
delete_option( 'yonkatk_maintenance_show_gear' );
delete_option( 'yonkatk_maintenance_gear_color' );

// Marquee Announcement options.
delete_option( 'yonkatk_marquee_announcement_settings' );

// Quick Notes options.
delete_option( 'yonkatk_quick_notes_data' );


/**
 * 3. TRANSIENTS CLEANUP
 */

// Database Information transient cache cleanup.
delete_transient( 'yonkatk_db_status_cache' );

// Yearly Statistics transients cleanup.
delete_transient( 'yonkatk_cys_years_cache' );

// Delete all dynamic yearly statistics cached transients from wp_options.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query(
	"DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_yonkatk_cys_stats_%' 
        OR option_name LIKE '_transient_timeout_yonkatk_cys_stats_%'"
);
