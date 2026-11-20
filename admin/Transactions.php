<?php
/**
 * Admin Transactions Management, Search, Filters, and Status Actions.
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Database;
use PremiumMembership\Helpers;
use PremiumMembership\Security;
use PremiumMembership\Transaction;

defined('ABSPATH') || exit;

class Transactions
{
    /**
     * Render Transactions Screen.
     */
    public static function render(): void
    {
        Security::check_admin_permission('manage_options');

        global $wpdb;
        $table_tx = Database::transactions_table();
        $table_plans = Database::plans_table();

        $action = sanitize_key($_REQUEST['action'] ?? '');
        $tx_id = absint($_REQUEST['id'] ?? 0);
        $message = '';

        // Manual Transaction Status Management by Admin
        if ($action === 'mark_paid' && $tx_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            $success = Transaction::mark_as_paid($tx_id, 'MANUAL-ADMIN-' . time());
            if ($success) {
                $message = __('Transaksi berhasil ditandai Paid dan membership member telah diaktifkan.', 'premium-membership');
            }
        } elseif ($action === 'refund' && $tx_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            $success = Transaction::mark_as_refunded($tx_id);
            if ($success) {
                $message = __('Transaksi berhasil ditandai Refunded.', 'premium-membership');
            }
        }

        // Filters and Search
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $where = ['1=1'];
        $params = [];

        if (!empty($status_filter)) {
            $where[] = "tx.status = %s";
            $params[] = $status_filter;
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(tx.order_id LIKE %s OR tx.gateway_transaction_id LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        $count_query = "SELECT COUNT(*) FROM {$table_tx} tx LEFT JOIN {$wpdb->users} u ON tx.user_id = u.ID WHERE {$where_clause}";
        $total_items = !empty($params) 
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, $params))
            : (int) $wpdb->get_var($count_query);

        $query = "SELECT tx.*, u.user_login, u.user_email, p.name as plan_name 
                  FROM {$table_tx} tx 
                  LEFT JOIN {$wpdb->users} u ON tx.user_id = u.ID 
                  LEFT JOIN {$table_plans} p ON tx.plan_id = p.id 
                  WHERE {$where_clause} 
                  ORDER BY tx.id DESC 
                  LIMIT %d OFFSET %d";

        $items_params = array_merge($params, [$per_page, $offset]);
        $transactions = $wpdb->get_results($wpdb->prepare($query, $items_params));
        $total_pages = ceil($total_items / $per_page);
        ?>
        <div class="wrap pm-admin-wrap">
            <h1 class="pm-admin-title">
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e('Daftar Transaksi Pembayaran', 'premium-membership'); ?>
            </h1>

            <?php if (!empty($message)): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="tablenav top" style="margin-top: 15px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="pm-transactions" />

                    <select name="status" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('Semua Status', 'premium-membership'); ?></option>
                        <option value="paid" <?php selected($status_filter, 'paid'); ?>><?php esc_html_e('Paid (Lunas)', 'premium-membership'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending (Menunggu)', 'premium-membership'); ?></option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed (Gagal)', 'premium-membership'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired (Kedaluwarsa)', 'premium-membership'); ?></option>
                        <option value="refunded" <?php selected($status_filter, 'refunded'); ?>><?php esc_html_e('Refunded (Dikembalikan)', 'premium-membership'); ?></option>
                    </select>

                    <p class="search-box" style="position: static; margin: 0; display: inline-flex;">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Order ID / User...', 'premium-membership'); ?>" />
                        <input type="submit" class="button" value="<?php esc_attr_e('Cari', 'premium-membership'); ?>" />
                    </p>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="pm-card" style="margin-top: 10px;">
                <div class="pm-card-body" style="padding: 0;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Order ID', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('User', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Paket', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Gateway', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Nominal', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Status', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Dibuat', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Dibayar', 'premium-membership'); ?></th>
                                <th style="width: 170px; text-align: right;"><?php esc_html_e('Aksi Admin', 'premium-membership'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 25px; color: #777;">
                                        <?php esc_html_e('Tidak ada transaksi yang ditemukan.', 'premium-membership'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($tx->order_id); ?></strong>
                                            <?php if (!empty($tx->gateway_transaction_id)): ?>
                                                <br><small style="color: #666;">Ref: <?php echo esc_html($tx->gateway_transaction_id); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($tx->user_login ?: 'User #' . $tx->user_id); ?>
                                            <br><small style="color: #888;"><?php echo esc_html($tx->user_email ?: ''); ?></small>
                                        </td>
                                        <td><?php echo esc_html($tx->plan_name ?: '-'); ?></td>
                                        <td><span class="pm-tag"><?php echo esc_html(strtoupper($tx->payment_gateway)); ?></span></td>
                                        <td><strong><?php echo Helpers::format_currency($tx->amount); ?></strong></td>
                                        <td>
                                            <span class="pm-badge pm-badge-<?php echo esc_attr($tx->status); ?>">
                                                <?php echo esc_html(strtoupper($tx->status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo Helpers::format_date($tx->created_at, 'd/m/Y H:i'); ?></td>
                                        <td><?php echo ($tx->status === 'paid' && !empty($tx->paid_at)) ? Helpers::format_date($tx->paid_at, 'd/m/Y H:i') : '-'; ?></td>
                                        <td style="text-align: right;">
                                            <?php if ($tx->status === 'pending'): ?>
                                                <?php
                                                $paid_url = wp_nonce_url(admin_url('admin.php?page=pm-transactions&action=mark_paid&id=' . $tx->id), Security::NONCE_ACTION_ADMIN, '_pm_nonce');
                                                ?>
                                                <a href="<?php echo esc_url($paid_url); ?>" class="button button-small button-primary" onclick="return confirm('<?php echo esc_js(__('Tandai transaksi ini sebagai Paid dan aktifkan membership member?', 'premium-membership')); ?>');">
                                                    Set Paid
                                                </a>
                                            <?php elseif ($tx->status === 'paid'): ?>
                                                <?php
                                                $refund_url = wp_nonce_url(admin_url('admin.php?page=pm-transactions&action=refund&id=' . $tx->id), Security::NONCE_ACTION_ADMIN, '_pm_nonce');
                                                ?>
                                                <a href="<?php echo esc_url($refund_url); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Tandai transaksi ini sebagai Refunded?', 'premium-membership')); ?>');">
                                                    Refund
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo sprintf(esc_html__('%d transaksi', 'premium-membership'), $total_items); ?></span>
                        <?php
                        echo paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $paged,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
