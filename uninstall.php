<?php
/**
 * Remove every trace of the plugin on uninstall.
 *
 * @package NSW_AI_Visibility
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'nsw_aiv_settings' );
delete_option( 'nsw_aiv_db_version' );
delete_transient( 'nsw_aiv_llms_cache' );
wp_clear_scheduled_hook( 'nsw_aiv_purge' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nsw_aiv_hits" );
