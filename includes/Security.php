<?php
/**
 * Security, sanitization, nonce, and authorization helpers.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Security
{
    const NONCE_ACTION_ADMIN = 'pm_admin_action_nonce';
    const NONCE_ACTION_FRONTEND = 'pm_frontend_action_nonce';

    /**
     * Verify admin capability or die.
     *
     * @param string $cap
     */
    public static function check_admin_permission(string $cap = 'manage_options'): void
    {
        if (!current_user_can($cap)) {
            wp_die(
                esc_html__('Anda tidak memiliki izin untuk mengakses halaman ini.', 'premium-membership'),
                esc_html__('Akses Ditolak', 'premium-membership'),
                ['response' => 403]
            );
        }
    }

    /**
     * Create nonce for forms or AJAX.
     *
     * @param string $action
     * @return string
     */
    public static function create_nonce(string $action = self::NONCE_ACTION_FRONTEND): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify nonce safely.
     *
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public static function verify_nonce(string $nonce, string $action = self::NONCE_ACTION_FRONTEND): bool
    {
        return (bool) wp_verify_nonce($nonce, $action);
    }

    /**
     * Check admin referer with nonce verification.
     *
     * @param string $action
     * @param string $query_arg
     */
    public static function verify_admin_referer(string $action = self::NONCE_ACTION_ADMIN, string $query_arg = '_pm_nonce'): void
    {
        check_admin_referer($action, $query_arg);
    }

    /**
     * Sanitize order ID (alphanumeric, dash, underscore only).
     *
     * @param string $order_id
     * @return string
     */
    public static function sanitize_order_id(string $order_id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', sanitize_text_field($order_id));
    }
}
