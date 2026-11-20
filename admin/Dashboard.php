<?php
/**
 * Admin Dashboard View and Statistics.
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Database;
use PremiumMembership\Helpers;
use PremiumMembership\Security;

defined('ABSPATH') || exit;

class Dashboard
{
    /**
     * Render the admin dashboard.
     */
    public static function render(): void
    {
        Security::check_admin_permission('manage_options');

        global $wpdb;
        $now = current_time('mysql');
        $table_memberships = Database::memberships_table();
        $table_transactions = Database::transactions_table();
        $table_plans = Database::plans_table();

        // Calculate Membership Stats
        $total_members = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$table_memberships}");
        $active_members = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM {$table_memberships} WHERE status = 'active' AND expired_at > %s", $now)
        );
        $expired_members = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM {$table_memberships} WHERE status = 'expired' OR (status = 'active' AND expired_at <= %s)", $now)
        );

        // Calculate Transaction Stats
        $total_tx = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_transactions}");
        $paid_tx = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_transactions} WHERE status = 'paid'");
        $pending_tx = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_transactions} WHERE status = 'pending'");
        $failed_tx = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_transactions} WHERE status IN ('failed', 'expired')");
        $total_revenue = (int) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$table_transactions} WHERE status = 'paid'");

        // Fetch Recent 6 Transactions
        $recent_tx = $wpdb->get_results(
            "SELECT tx.*, u.user_login, u.user_email, p.name as plan_name 
             FROM {$table_transactions} tx 
             LEFT JOIN {$wpdb->users} u ON tx.user_id = u.ID 
             LEFT JOIN {$table_plans} p ON tx.plan_id = p.id 
             ORDER BY tx.id DESC LIMIT 6"
        );
        ?>
        <div class="wrap pm-admin-wrap">
            <h1 class="pm-admin-title">
                <span class="dashicons dashicons-awards"></span> 
                <?php esc_html_e('Premium Membership Dashboard', 'premium-membership'); ?>
            </h1>

            <!-- Metric Cards -->
            <div class="pm-stats-grid">
                <div class="pm-stat-card pm-stat-primary">
                    <div class="pm-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="pm-stat-data">
                        <span class="pm-stat-label"><?php esc_html_e('Total Pendapatan', 'premium-membership'); ?></span>
                        <span class="pm-stat-value"><?php echo Helpers::format_currency($total_revenue); ?></span>
                    </div>
                </div>

                <div class="pm-stat-card pm-stat-success">
                    <div class="pm-stat-icon"><span class="dashicons dashicons-groups"></span></div>
                    <div class="pm-stat-data">
                        <span class="pm-stat-label"><?php esc_html_e('Member Aktif', 'premium-membership'); ?></span>
                        <span class="pm-stat-value"><?php echo esc_html(number_format_i18n($active_members)); ?></span>
                    </div>
                </div>

                <div class="pm-stat-card pm-stat-warning">
                    <div class="pm-stat-icon"><span class="dashicons dashicons-hourglass"></span></div>
                    <div class="pm-stat-data">
                        <span class="pm-stat-label"><?php esc_html_e('Member Expired', 'premium-membership'); ?></span>
                        <span class="pm-stat-value"><?php echo esc_html(number_format_i18n($expired_members)); ?></span>
                    </div>
                </div>

                <div class="pm-stat-card pm-stat-info">
                    <div class="pm-stat-icon"><span class="dashicons dashicons-cart"></span></div>
                    <div class="pm-stat-data">
                        <span class="pm-stat-label"><?php esc_html_e('Total Transaksi', 'premium-membership'); ?></span>
                        <span class="pm-stat-value"><?php echo esc_html(number_format_i18n($total_tx)); ?> (<?php echo esc_html($paid_tx); ?> Paid)</span>
                    </div>
                </div>
            </div>

            <div class="pm-dashboard-columns">
                <!-- Recent Transactions Table -->
                <div class="pm-column-main">
                    <div class="pm-card">
                        <div class="pm-card-header">
                            <h3><?php esc_html_e('Transaksi Terbaru', 'premium-membership'); ?></h3>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=pm-transactions')); ?>" class="button button-secondary button-small">
                                <?php esc_html_e('Lihat Semua Transaksi', 'premium-membership'); ?>
                            </a>
                        </div>
                        <div class="pm-card-body" style="padding: 0;">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Order ID', 'premium-membership'); ?></th>
                                        <th><?php esc_html_e('User', 'premium-membership'); ?></th>
                                        <th><?php esc_html_e('Paket', 'premium-membership'); ?></th>
                                        <th><?php esc_html_e('Nominal', 'premium-membership'); ?></th>
                                        <th><?php esc_html_e('Status', 'premium-membership'); ?></th>
                                        <th><?php esc_html_e('Tanggal', 'premium-membership'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_tx)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 20px; color: #888;">
                                                <?php esc_html_e('Belum ada transaksi.', 'premium-membership'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_tx as $tx): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($tx->order_id); ?></strong></td>
                                                <td><?php echo esc_html($tx->user_login ?: 'User #' . $tx->user_id); ?></td>
                                                <td><?php echo esc_html($tx->plan_name ?: '-'); ?></td>
                                                <td><?php echo Helpers::format_currency($tx->amount); ?></td>
                                                <td>
                                                    <span class="pm-badge pm-badge-<?php echo esc_attr($tx->status); ?>">
                                                        <?php echo esc_html(strtoupper($tx->status)); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo Helpers::format_date($tx->created_at); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Status -->
                <div class="pm-column-side">
                    <div class="pm-card">
                        <div class="pm-card-header">
                            <h3><?php esc_html_e('Status Sistem', 'premium-membership'); ?></h3>
                        </div>
                        <div class="pm-card-body">
                            <ul class="pm-info-list">
                                <li>
                                    <strong><?php esc_html_e('Active Gateway:', 'premium-membership'); ?></strong>
                                    <span class="pm-tag"><?php echo esc_html(strtoupper(Helpers::get_setting('active_gateway', 'mock'))); ?></span>
                                </li>
                                <li>
                                    <strong><?php esc_html_e('Environment:', 'premium-membership'); ?></strong>
                                    <span class="pm-tag"><?php echo esc_html(ucfirst(Helpers::get_setting('environment', 'sandbox'))); ?></span>
                                </li>
                                <li>
                                    <strong><?php esc_html_e('Next Cron Check:', 'premium-membership'); ?></strong>
                                    <span><?php echo Helpers::format_date(date('Y-m-d H:i:s', (int) wp_next_scheduled('pm_hourly_membership_check'))); ?></span>
                                </li>
                                <li>
                                    <strong><?php esc_html_e('Webhook Endpoint:', 'premium-membership'); ?></strong>
                                    <input type="text" readonly value="<?php echo esc_attr(Helpers::get_webhook_url()); ?>" class="regular-text code" style="width: 100%; font-size: 11px; margin-top: 5px;" onclick="this.select();" />
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="pm-card" style="margin-top: 20px;">
                        <div class="pm-card-header">
                            <h3><?php esc_html_e('Shortcodes Bawaan', 'premium-membership'); ?></h3>
                        </div>
                        <div class="pm-card-body">
                            <p style="font-size: 13px; color: #555;">Gunakan shortcode berikut pada halaman WordPress Anda:</p>
                            <code>[premium_plans]</code> - Menampilkan daftar paket<br><br>
                            <code>[premium_checkout]</code> - Halaman checkout & order<br><br>
                            <code>[premium_account]</code> - Halaman dashboard akun member
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
