<?php
/**
 * SPM Interlinker Uninstall
 *
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete plugin options
delete_option( 'spm_interlinker_options' );
delete_option( 'spm_interlinker_index' );
delete_option( 'spm_interlinker_queue' );
delete_option( 'spm_interlinker_queue_status' );

// Delete all transients with our prefix
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_spm_interlinker_%'
     OR option_name LIKE '_transient_timeout_spm_interlinker_%'"
);

// Delete all post meta
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key LIKE '_spm_interlinker_%'"
);
