<?php
/**
 * WP-Cron Scheduler for automated membership expiration and cleanup.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Cron
{
    const HOURLY_HOOK = 'pm_hourly_membership_check';

    /**
     * Initialize Cron hooks.
     */
    public static function init(): void
    {
        add_action(self::HOURLY_HOOK, [__CLASS__, 'process_hourly_tasks']);
    }

    /**
     * Schedule cron events on plugin activation.
     */
    public static function schedule_events(): void
    {
        if (!wp_next_scheduled(self::HOURLY_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOURLY_HOOK);
        }
    }

    /**
     * Clear scheduled events on plugin deactivation.
     */
    public static function clear_events(): void
    {
        $timestamp = wp_next_scheduled(self::HOURLY_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOURLY_HOOK);
        }
    }

    /**
     * Hourly task callback.
     */
    public static function process_hourly_tasks(): void
    {
        global $wpdb;
        $now = current_time('mysql');

        // 1. Expire outdated active memberships
        $table_memberships = Database::memberships_table();
        $expired_memberships = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id FROM {$table_memberships} 
                 WHERE status = 'active' AND expired_at <= %s",
                $now
            )
        );

        if (!empty($expired_memberships)) {
            $ids = wp_list_pluck($expired_memberships, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_memberships} SET status = 'expired', updated_at = %s WHERE id IN ({$placeholders})",
                    array_merge([$now], $ids)
                )
            );

            Logger::log(
                'cron_membership_expired',
                'Cron memproses ' . count($ids) . ' membership yang telah melewati masa aktif.'
            );
        }

        // 2. Expire outdated pending transactions
        $table_transactions = Database::transactions_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_transactions} 
                 SET status = 'expired', updated_at = %s 
                 WHERE status = 'pending' AND expired_at IS NOT NULL AND expired_at <= %s",
                $now,
                $now
            )
        );
    }
}
