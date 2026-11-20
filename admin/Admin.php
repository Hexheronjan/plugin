<?php
/**
 * Admin Menu and Asset Orchestrator.
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Security;

defined('ABSPATH') || exit;

class Admin
{
    /**
     * Initialize Admin hooks.
     */
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /**
     * Register Admin Menus and Submenus.
     */
    public static function register_admin_menus(): void
    {
        // Main Menu
        add_menu_page(
            __('Premium Membership', 'premium-membership'),
            __('Membership', 'premium-membership'),
            'manage_options',
            'premium-membership',
            [Dashboard::class, 'render'],
            'dashicons-awards',
            30
        );

        // Submenus
        add_submenu_page(
            'premium-membership',
            __('Dashboard Membership', 'premium-membership'),
            __('Dashboard', 'premium-membership'),
            'manage_options',
            'premium-membership',
            [Dashboard::class, 'render']
        );

        add_submenu_page(
            'premium-membership',
            __('Kelola Paket Membership', 'premium-membership'),
            __('Plans', 'premium-membership'),
            'manage_options',
            'pm-plans',
            [Plans::class, 'render']
        );

        add_submenu_page(
            'premium-membership',
            __('Daftar Member', 'premium-membership'),
            __('Members', 'premium-membership'),
            'manage_options',
            'pm-members',
            [Members::class, 'render']
        );

        add_submenu_page(
            'premium-membership',
            __('Riwayat Transaksi', 'premium-membership'),
            __('Transactions', 'premium-membership'),
            'manage_options',
            'pm-transactions',
            [Transactions::class, 'render']
        );

        add_submenu_page(
            'premium-membership',
            __('Pengaturan Plugin', 'premium-membership'),
            __('Settings', 'premium-membership'),
            'manage_options',
            'pm-settings',
            [Settings::class, 'render']
        );

        add_submenu_page(
            'premium-membership',
            __('Log Sistem', 'premium-membership'),
            __('Logs', 'premium-membership'),
            'manage_options',
            'pm-logs',
            [Logs::class, 'render']
        );
    }

    /**
     * Enqueue Admin CSS & JS only on plugin screens.
     *
     * @param string $hook
     */
    public static function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, 'premium-membership') === false && strpos($hook, 'pm-') === false) {
            return;
        }

        wp_enqueue_style(
            'pm-admin-css',
            PM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PM_VERSION
        );

        wp_enqueue_script(
            'pm-admin-js',
            PM_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            PM_VERSION,
            true
        );

        wp_localize_script('pm-admin-js', 'pm_admin_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(Security::NONCE_ACTION_ADMIN),
            'confirm_delete' => __('Apakah Anda yakin ingin menghapus data ini?', 'premium-membership'),
            'confirm_action' => __('Apakah Anda yakin ingin melakukan tindakan ini?', 'premium-membership'),
        ]);
    }
}
