<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package PremiumMembership
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Retrieve settings to check whether the admin allowed database cleanup
$settings = get_option('pm_settings', []);
$delete_data = !empty($settings['delete_data_on_uninstall']) && (int) $settings['delete_data_on_uninstall'] === 1;

if ($delete_data) {
    global $wpdb;

    // Drop plugin custom tables
    $table_plans        = $wpdb->prefix . 'premium_plans';
    $table_memberships  = $wpdb->prefix . 'premium_memberships';
    $table_transactions = $wpdb->prefix . 'premium_transactions';
    $table_logs         = $wpdb->prefix . 'premium_logs';

    $wpdb->query("DROP TABLE IF EXISTS {$table_logs}");
    $wpdb->query("DROP TABLE IF EXISTS {$table_transactions}");
    $wpdb->query("DROP TABLE IF EXISTS {$table_memberships}");
    $wpdb->query("DROP TABLE IF EXISTS {$table_plans}");

    // Delete post meta created by plugin
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            '_pm_is_premium'
        )
    );

    // Delete options
    delete_option('pm_settings');
    delete_option('pm_db_version');

    // Remove any cron hooks
    wp_clear_scheduled_hook('pm_hourly_membership_check');
}
