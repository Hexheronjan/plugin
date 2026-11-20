<?php
/**
 * Database schema and table management.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Database
{
    const DB_VERSION = '1.0.0';

    /**
     * Get plans table name
     */
    public static function plans_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'premium_plans';
    }

    /**
     * Get memberships table name
     */
    public static function memberships_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'premium_memberships';
    }

    /**
     * Get transactions table name
     */
    public static function transactions_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'premium_transactions';
    }

    /**
     * Get logs table name
     */
    public static function logs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'premium_logs';
    }

    /**
     * Create or update database tables using dbDelta()
     */
    public static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $plans_table = self::plans_table();
        $memberships_table = self::memberships_table();
        $transactions_table = self::transactions_table();
        $logs_table = self::logs_table();

        // SQL table definitions formatted strictly for dbDelta compatibility
        $sql_plans = "CREATE TABLE {$plans_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            price BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            duration INT(10) UNSIGNED NOT NULL DEFAULT 30,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_memberships = "CREATE TABLE {$memberships_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            plan_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            started_at DATETIME NOT NULL,
            expired_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY plan_id (plan_id),
            KEY status (status),
            KEY expired_at (expired_at)
        ) {$charset_collate};";

        $sql_transactions = "CREATE TABLE {$transactions_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            plan_id BIGINT(20) UNSIGNED NOT NULL,
            order_id VARCHAR(64) NOT NULL,
            payment_gateway VARCHAR(50) NOT NULL DEFAULT 'mock',
            gateway_transaction_id VARCHAR(100) NULL,
            amount BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_url TEXT NULL,
            paid_at DATETIME NULL,
            expired_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id),
            KEY user_id (user_id),
            KEY plan_id (plan_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_plans);
        dbDelta($sql_memberships);
        dbDelta($sql_transactions);
        dbDelta($sql_logs);

        update_option('pm_db_version', self::DB_VERSION);
    }
}
