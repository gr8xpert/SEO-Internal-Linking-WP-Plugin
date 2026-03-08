<?php
/**
 * RS Smart Interlinker Uninstall
 *
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete plugin options
delete_option( 'rs_interlinker_options' );
delete_option( 'rs_interlinker_index' );

// Delete all transients with our prefix
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_rs_interlinker_%'
     OR option_name LIKE '_transient_timeout_rs_interlinker_%'"
);

// Delete all post meta
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key = '_rs_interlinker_keywords'"
);
