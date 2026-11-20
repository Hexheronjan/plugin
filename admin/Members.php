<?php
/**
 * Admin Members Listing, Filtering, and Manual Management.
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Database;
use PremiumMembership\Helpers;
use PremiumMembership\Security;
use PremiumMembership\Membership;

defined('ABSPATH') || exit;

class Members
{
    /**
     * Render Members List Screen.
     */
    public static function render(): void
    {
        Security::check_admin_permission('manage_options');

        global $wpdb;
        $table_memberships = Database::memberships_table();
        $table_plans = Database::plans_table();

        $action = sanitize_key($_REQUEST['action'] ?? '');
        $membership_id = absint($_REQUEST['id'] ?? 0);
        $message = '';

        // Handle Admin Manual Actions
        if ($action === 'expire' && $membership_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            Membership::expire_membership($membership_id);
            $message = __('Status membership diubah menjadi Expired.', 'premium-membership');
        } elseif ($action === 'cancel' && $membership_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            Membership::cancel_membership($membership_id);
            $message = __('Status membership dibatalkan.', 'premium-membership');
        } elseif ($action === 'extend' && $membership_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            $m = Membership::get_membership($membership_id);
            if ($m) {
                Membership::activate_membership((int) $m->user_id, (int) $m->plan_id, 30);
                $message = __('Masa aktif membership berhasil diperpanjang 30 hari.', 'premium-membership');
            }
        }

        // Filters & Search
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $where = ['1=1'];
        $params = [];

        if (!empty($status_filter)) {
            $now = current_time('mysql');
            if ($status_filter === 'active') {
                $where[] = "m.status = 'active' AND m.expired_at > %s";
                $params[] = $now;
            } elseif ($status_filter === 'expired') {
                $where[] = "(m.status = 'expired' OR (m.status = 'active' AND m.expired_at <= %s))";
                $params[] = $now;
            } elseif ($status_filter === 'cancelled') {
                $where[] = "m.status = 'cancelled'";
            }
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        $count_query = "SELECT COUNT(*) FROM {$table_memberships} m LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID WHERE {$where_clause}";
        $total_items = !empty($params) 
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, $params))
            : (int) $wpdb->get_var($count_query);

        $query = "SELECT m.*, u.user_login, u.user_email, u.display_name, p.name as plan_name 
                  FROM {$table_memberships} m 
                  LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID 
                  LEFT JOIN {$table_plans} p ON m.plan_id = p.id 
                  WHERE {$where_clause} 
                  ORDER BY m.id DESC 
                  LIMIT %d OFFSET %d";

        $items_params = array_merge($params, [$per_page, $offset]);
        $members = $wpdb->get_results($wpdb->prepare($query, $items_params));
        $total_pages = ceil($total_items / $per_page);
        ?>
        <div class="wrap pm-admin-wrap">
            <h1 class="pm-admin-title">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e('Daftar Member Premium', 'premium-membership'); ?>
            </h1>

            <?php if (!empty($message)): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="tablenav top" style="margin-top: 15px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="pm-members" />

                    <select name="status" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('Semua Status', 'premium-membership'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active (Aktif)', 'premium-membership'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired (Kedaluwarsa)', 'premium-membership'); ?></option>
                        <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php esc_html_e('Cancelled (Dibatalkan)', 'premium-membership'); ?></option>
                    </select>

                    <p class="search-box" style="position: static; margin: 0; display: inline-flex;">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Cari user / email...', 'premium-membership'); ?>" />
                        <input type="submit" class="button" value="<?php esc_attr_e('Cari', 'premium-membership'); ?>" />
                    </p>
                </form>
            </div>

            <!-- Members Table -->
            <div class="pm-card" style="margin-top: 10px;">
                <div class="pm-card-body" style="padding: 0;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 60px;"><?php esc_html_e('ID', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('User', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Email', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Paket', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Status', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Mulai', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Berakhir', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Sisa Hari', 'premium-membership'); ?></th>
                                <th style="width: 200px; text-align: right;"><?php esc_html_e('Aksi Admin', 'premium-membership'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 25px; color: #777;">
                                        <?php esc_html_e('Tidak ada member yang ditemukan.', 'premium-membership'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($members as $m): ?>
                                    <?php
                                    $is_active_time = strtotime($m->expired_at) > current_datetime()->getTimestamp();
                                    $display_status = ($m->status === 'active' && !$is_active_time) ? 'expired' : $m->status;
                                    $remaining = Helpers::get_remaining_days($m->expired_at);
                                    ?>
                                    <tr>
                                        <td>#<?php echo esc_html($m->id); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($m->user_login ?: 'User #' . $m->user_id); ?></strong>
                                            <?php if (!empty($m->display_name) && $m->display_name !== $m->user_login): ?>
                                                <br><small style="color: #666;"><?php echo esc_html($m->display_name); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($m->user_email ?: '-'); ?></td>
                                        <td><?php echo esc_html($m->plan_name ?: '-'); ?></td>
                                        <td>
                                            <span class="pm-badge pm-badge-<?php echo esc_attr($display_status); ?>">
                                                <?php echo esc_html(strtoupper($display_status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo Helpers::format_date($m->started_at, 'j M Y'); ?></td>
                                        <td><?php echo Helpers::format_date($m->expired_at, 'j M Y H:i'); ?></td>
                                        <td>
                                            <?php if ($display_status === 'active'): ?>
                                                <strong style="color: #2e7d32;"><?php echo esc_html($remaining); ?> <?php esc_html_e('Hari', 'premium-membership'); ?></strong>
                                            <?php else: ?>
                                                <span style="color: #999;">0 <?php esc_html_e('Hari', 'premium-membership'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php
                                            $extend_url = wp_nonce_url(admin_url('admin.php?page=pm-members&action=extend&id=' . $m->id), Security::NONCE_ACTION_ADMIN, '_pm_nonce');
                                            $expire_url = wp_nonce_url(admin_url('admin.php?page=pm-members&action=expire&id=' . $m->id), Security::NONCE_ACTION_ADMIN, '_pm_nonce');
                                            ?>
                                            <a href="<?php echo esc_url($extend_url); ?>" class="button button-small" title="<?php esc_attr_e('Tambah 30 Hari', 'premium-membership'); ?>">
                                                +30 Hari
                                            </a>
                                            <?php if ($display_status === 'active'): ?>
                                                <a href="<?php echo esc_url($expire_url); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Set status menjadi Expired?', 'premium-membership')); ?>');">
                                                    Expire
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
                        <span class="displaying-num"><?php echo sprintf(esc_html__('%d item', 'premium-membership'), $total_items); ?></span>
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
