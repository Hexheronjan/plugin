<?php
/**
 * Admin Audit Logs Viewer.
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Helpers;
use PremiumMembership\Security;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class Logs
{
    /**
     * Render Logs Screen.
     */
    public static function render(): void
    {
        Security::check_admin_permission('manage_options');

        $message = '';

        // Handle Clear Logs Action
        if (isset($_POST['clear_logs'])) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            Logger::clear_logs();
            $message = __('Seluruh catatan log sistem berhasil dibersihkan.', 'premium-membership');
        }

        // Filters and pagination
        $event_type = sanitize_text_field($_GET['event_type'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 25;
        $offset = ($paged - 1) * $per_page;

        $logs_data = Logger::get_logs($per_page, $offset, $search, $event_type);
        $total_items = $logs_data['total'];
        $logs = $logs_data['items'];
        $total_pages = ceil($total_items / $per_page);
        ?>
        <div class="wrap pm-admin-wrap">
            <h1 class="pm-admin-title">
                <span class="dashicons dashicons-media-document"></span>
                <?php esc_html_e('Log Audit Sistem', 'premium-membership'); ?>
            </h1>

            <?php if (!empty($message)): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <!-- Filter and Actions -->
            <div class="tablenav top" style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="pm-logs" />

                    <select name="event_type" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('Semua Tipe Event', 'premium-membership'); ?></option>
                        <option value="transaction_created" <?php selected($event_type, 'transaction_created'); ?>>transaction_created</option>
                        <option value="payment_request" <?php selected($event_type, 'payment_request'); ?>>payment_request</option>
                        <option value="webhook_received" <?php selected($event_type, 'webhook_received'); ?>>webhook_received</option>
                        <option value="payment_success" <?php selected($event_type, 'payment_success'); ?>>payment_success</option>
                        <option value="payment_failed" <?php selected($event_type, 'payment_failed'); ?>>payment_failed</option>
                        <option value="membership_activated" <?php selected($event_type, 'membership_activated'); ?>>membership_activated</option>
                        <option value="membership_renewed" <?php selected($event_type, 'membership_renewed'); ?>>membership_renewed</option>
                        <option value="membership_expired" <?php selected($event_type, 'membership_expired'); ?>>membership_expired</option>
                    </select>

                    <p class="search-box" style="position: static; margin: 0; display: inline-flex;">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Cari pesan log...', 'premium-membership'); ?>" />
                        <input type="submit" class="button" value="<?php esc_attr_e('Cari', 'premium-membership'); ?>" />
                    </p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=pm-logs')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Apakah Anda yakin ingin menghapus semua log audit? Tindakan ini tidak dapat dibatalkan.', 'premium-membership')); ?>');">
                    <?php wp_nonce_field(Security::NONCE_ACTION_ADMIN, '_pm_nonce'); ?>
                    <button type="submit" name="clear_logs" class="button button-secondary" style="color:#d63638; border-color:#d63638;">
                        🗑️ <?php esc_html_e('Bersihkan Semua Log', 'premium-membership'); ?>
                    </button>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="pm-card" style="margin-top: 10px;">
                <div class="pm-card-body" style="padding: 0;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 70px;"><?php esc_html_e('ID', 'premium-membership'); ?></th>
                                <th style="width: 170px;"><?php esc_html_e('Event Type', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Pesan Log', 'premium-membership'); ?></th>
                                <th style="width: 250px;"><?php esc_html_e('Konteks (JSON)', 'premium-membership'); ?></th>
                                <th style="width: 160px;"><?php esc_html_e('Waktu', 'premium-membership'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 25px; color: #777;">
                                        <?php esc_html_e('Belum ada log yang tersimpan.', 'premium-membership'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $item): ?>
                                    <tr>
                                        <td>#<?php echo esc_html($item['id']); ?></td>
                                        <td><span class="pm-tag"><?php echo esc_html($item['event_type']); ?></span></td>
                                        <td><?php echo esc_html($item['message']); ?></td>
                                        <td>
                                            <?php if (!empty($item['context'])): ?>
                                                <pre class="pm-json-preview"><?php echo esc_html($item['context']); ?></pre>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo Helpers::format_date($item['created_at']); ?></td>
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
                        <span class="displaying-num"><?php echo sprintf(esc_html__('%d entri log', 'premium-membership'), $total_items); ?></span>
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
