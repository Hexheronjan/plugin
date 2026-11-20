<?php
/**
 * Plugin Name:       Premium Membership
 * Plugin URI:        https://example.com/premium-membership
 * Description:       Sistem membership premium profesional untuk portal berita WordPress dengan sistem berbayar, paywall cerdas, transaksi, dan payment gateway.
 * Version:           1.1.1
 * Author:            Antigravity
 * Author URI:        https://example.com
 * Text Domain:       premium-membership
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package           PremiumMembership
 */

defined('ABSPATH') || exit;

// Define Plugin Constants
define('PM_VERSION', '1.1.1');
define('PM_PLUGIN_FILE', __FILE__);
define('PM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Autoloader
require_once PM_PLUGIN_DIR . 'includes/Autoloader.php';
\PremiumMembership\Autoloader::register();

/**
 * Main Plugin Bootstrap Class
 */
final class PremiumMembershipPlugin
{
    /**
     * Singleton instance
     *
     * @var PremiumMembershipPlugin|null
     */
    private static ?PremiumMembershipPlugin $instance = null;

    /**
     * Get singleton instance
     *
     * @return PremiumMembershipPlugin
     */
    public static function get_instance(): PremiumMembershipPlugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_components();
    }

    /**
     * Initialize plugin components
     */
    private function init_components(): void
    {
        // Auto-check and create tables if version mismatch or tables missing
        if (get_option('pm_db_version') !== \PremiumMembership\Database::DB_VERSION) {
            \PremiumMembership\Database::create_tables();
        }

        // Core Webhook & REST API initialization
        add_action('rest_api_init', [\PremiumMembership\Webhook::class, 'register_routes']);

        // WP-Cron scheduler & task handler
        \PremiumMembership\Cron::init();

        // Admin Module
        if (is_admin()) {
            \PremiumMembership\Admin\Admin::init();
        }

        // Frontend & Public Module (Shortcodes, Paywall, Scripts)
        \PremiumMembership\Frontend\PublicController::init();
        \PremiumMembership\Frontend\PremiumContent::init();
        \PremiumMembership\Frontend\Register::init();
        \PremiumMembership\Frontend\Login::init();

        // Hide WordPress admin bar for non-admin users (regular members)
        add_action('after_setup_theme', [__CLASS__, 'hide_admin_bar_for_members']);

        // Redirect members away from wp-admin to member account page
        add_action('admin_init', [__CLASS__, 'redirect_members_from_admin']);
    }

    /**
     * Hide the WordPress admin bar for regular subscribers/members on frontend.
     */
    public static function hide_admin_bar_for_members(): void
    {
        if (is_user_logged_in() && !current_user_can('edit_posts')) {
            show_admin_bar(false);
        }
    }

    /**
     * Redirect non-admin users away from wp-admin to their member account page.
     */
    public static function redirect_members_from_admin(): void
    {
        if (is_admin() && !current_user_can('edit_posts') && !wp_doing_ajax()) {
            $account_page_id = (int) \PremiumMembership\Helpers::get_setting('account_page_id', 0);
            $redirect_url = $account_page_id > 0 ? get_permalink($account_page_id) : home_url('/');
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Activation Callback
     */
    public static function activate(): void
    {
        // Create or update database tables
        \PremiumMembership\Database::create_tables();

        // Schedule WP-Cron tasks
        \PremiumMembership\Cron::schedule_events();

        // Set default options if not exists
        if (!get_option('pm_settings')) {
            update_option('pm_settings', [
                'active_gateway'          => 'mock',
                'environment'             => 'sandbox',
                'currency'                => 'IDR',
                'currency_symbol'         => 'Rp',
                'checkout_page_id'        => 0,
                'plans_page_id'           => 0,
                'account_page_id'         => 0,
                'delete_data_on_uninstall'=> 0,
                'mock_secret_key'         => 'pm_mock_secret_' . wp_generate_password(16, false),
            ]);
        }

        // Flush rewrite rules for custom endpoints
        flush_rewrite_rules();
    }

    /**
     * Deactivation Callback
     */
    public static function deactivate(): void
    {
        // Clear WP-Cron schedules
        \PremiumMembership\Cron::clear_events();

        flush_rewrite_rules();
    }
}

// Register activation and deactivation hooks in root scope
register_activation_hook(PM_PLUGIN_FILE, ['PremiumMembershipPlugin', 'activate']);
register_deactivation_hook(PM_PLUGIN_FILE, ['PremiumMembershipPlugin', 'deactivate']);

// Instantiate plugin
add_action('plugins_loaded', function () {
    PremiumMembershipPlugin::get_instance();
});
