<?php
/**
 * Admin Membership Plans Management (CRUD).
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Database;
use PremiumMembership\Helpers;
use PremiumMembership\Security;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class Plans
{
    /**
     * Render Plans Management Screen.
     */
    public static function render(): void
    {
        Security::check_admin_permission('manage_options');

        global $wpdb;
        $table = Database::plans_table();

        $action = sanitize_key($_REQUEST['action'] ?? 'list');
        $plan_id = absint($_REQUEST['id'] ?? 0);
        $message = '';
        $error = '';

        // 1. Process Plan Deletion
        if ($action === 'delete' && $plan_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            $deleted = $wpdb->delete($table, ['id' => $plan_id], ['%d']);
            if ($deleted) {
                Logger::log('plan_deleted', "Paket membership ID #{$plan_id} dihapus oleh Admin.");
                $message = __('Paket membership berhasil dihapus.', 'premium-membership');
            } else {
                $error = __('Gagal menghapus paket membership.', 'premium-membership');
            }
            $action = 'list';
        }

        // 2. Process Status Toggle
        if ($action === 'toggle' && $plan_id > 0) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);
            $current_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $plan_id));
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
            $wpdb->update($table, ['status' => $new_status, 'updated_at' => current_time('mysql')], ['id' => $plan_id], ['%s', '%s'], ['%d']);
            $message = sprintf(__('Status paket diubah menjadi %s.', 'premium-membership'), strtoupper($new_status));
            $action = 'list';
        }

        // 3. Process Save/Update Form POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);

            $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
            $price = absint($_POST['price'] ?? 0);
            $duration = absint($_POST['duration'] ?? 30);
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
            $edit_id = absint($_POST['plan_id'] ?? 0);

            // Validations
            if (empty($name)) {
                $error = __('Nama paket tidak boleh kosong.', 'premium-membership');
            } elseif ($duration <= 0) {
                $error = __('Durasi paket harus lebih dari 0 hari.', 'premium-membership');
            } elseif ($price < 0) {
                $error = __('Harga paket tidak valid.', 'premium-membership');
            } else {
                $now = current_time('mysql');

                if ($edit_id > 0) {
                    // Update
                    $wpdb->update(
                        $table,
                        [
                            'name'       => $name,
                            'price'      => $price,
                            'duration'   => $duration,
                            'status'     => $status,
                            'updated_at' => $now,
                        ],
                        ['id' => $edit_id],
                        ['%s', '%d', '%d', '%s', '%s'],
                        ['%d']
                    );
                    Logger::log('plan_updated', "Paket membership ID #{$edit_id} diperbarui: {$name}.");
                    $message = __('Paket membership berhasil diperbarui.', 'premium-membership');
                } else {
                    // Insert
                    $wpdb->insert(
                        $table,
                        [
                            'name'       => $name,
                            'price'      => $price,
                            'duration'   => $duration,
                            'status'     => $status,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        ['%s', '%d', '%d', '%s', '%s', '%s']
                    );
                    $new_id = (int) $wpdb->insert_id;
                    Logger::log('plan_created', "Paket membership baru ID #{$new_id} dibuat: {$name}.");
                    $message = __('Paket membership baru berhasil ditambahkan.', 'premium-membership');
                }

                $action = 'list';
            }
        }

        // Render Views
        ?>
        <div class="wrap pm-admin-wrap">
            <h1 class="pm-admin-title">
                <span class="dashicons dashicons-tag"></span>
                <?php esc_html_e('Kelola Paket Membership', 'premium-membership'); ?>
                <?php if ($action === 'list'): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pm-plans&action=new')); ?>" class="page-title-action">
                        <?php esc_html_e('Tambah Paket Baru', 'premium-membership'); ?>
                    </a>
                <?php endif; ?>
            </h1>

            <?php if (!empty($message)): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php if ($action === 'new' || $action === 'edit'): ?>
                <?php
                $edit_plan = null;
                if ($action === 'edit' && $plan_id > 0) {
                    $edit_plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $plan_id));
                }
                ?>
                <div class="pm-card" style="max-width: 650px; margin-top: 20px;">
                    <div class="pm-card-header">
                        <h3><?php echo $edit_plan ? esc_html__('Edit Paket Membership', 'premium-membership') : esc_html__('Tambah Paket Baru', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=pm-plans')); ?>">
                            <?php wp_nonce_field(Security::NONCE_ACTION_ADMIN, '_pm_nonce'); ?>
                            <input type="hidden" name="plan_id" value="<?php echo $edit_plan ? esc_attr($edit_plan->id) : 0; ?>" />

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="name"><?php esc_html_e('Nama Paket', 'premium-membership'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <input name="name" id="name" type="text" class="regular-text" required value="<?php echo $edit_plan ? esc_attr($edit_plan->name) : ''; ?>" placeholder="Contoh: Paket 30 Hari" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="price"><?php esc_html_e('Harga (IDR)', 'premium-membership'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <input name="price" id="price" type="number" min="0" step="1000" class="regular-text" required value="<?php echo $edit_plan ? esc_attr($edit_plan->price) : '25000'; ?>" />
                                        <p class="description"><?php esc_html_e('Nominal harga dalam angka tanpa titik/koma (misal: 25000).', 'premium-membership'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="duration"><?php esc_html_e('Durasi Masa Aktif (Hari)', 'premium-membership'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <input name="duration" id="duration" type="number" min="1" class="small-text" required value="<?php echo $edit_plan ? esc_attr($edit_plan->duration) : '30'; ?>" />
                                        <span><?php esc_html_e('Hari', 'premium-membership'); ?></span>
                                        <p class="description"><?php esc_html_e('Durasi masa aktif membership (Contoh: 7 = 7 hari, 30 = 30 hari, 365 = 1 tahun).', 'premium-membership'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="status"><?php esc_html_e('Status', 'premium-membership'); ?></label></th>
                                    <td>
                                        <select name="status" id="status">
                                            <option value="active" <?php selected($edit_plan ? $edit_plan->status : 'active', 'active'); ?>><?php esc_html_e('Active (Tersedia)', 'premium-membership'); ?></option>
                                            <option value="inactive" <?php selected($edit_plan ? $edit_plan->status : '', 'inactive'); ?>><?php esc_html_e('Inactive (Disembunyikan)', 'premium-membership'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" name="save_plan" class="button button-primary">
                                    <?php echo $edit_plan ? esc_html__('Simpan Perubahan', 'premium-membership') : esc_html__('Buat Paket', 'premium-membership'); ?>
                                </button>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=pm-plans')); ?>" class="button button-secondary">
                                    <?php esc_html_e('Batal', 'premium-membership'); ?>
                                </a>
                            </p>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <?php
                $plans = $wpdb->get_results("SELECT * FROM {$table} ORDER BY price ASC");
                ?>
                <div class="pm-card" style="margin-top: 20px;">
                    <div class="pm-card-body" style="padding: 0;">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><?php esc_html_e('ID', 'premium-membership'); ?></th>
                                    <th><?php esc_html_e('Nama Paket', 'premium-membership'); ?></th>
                                    <th><?php esc_html_e('Harga', 'premium-membership'); ?></th>
                                    <th><?php esc_html_e('Durasi', 'premium-membership'); ?></th>
                                    <th><?php esc_html_e('Status', 'premium-membership'); ?></th>
                                    <th><?php esc_html_e('Dibuat Pada', 'premium-membership'); ?></th>
                                    <th style="width: 180px; text-align: right;"><?php esc_html_e('Aksi', 'premium-membership'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($plans)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 25px; color: #777;">
                                            <?php esc_html_e('Belum ada paket membership. Klik "Tambah Paket Baru" untuk membuat paket pertama.', 'premium-membership'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($plans as $p): ?>
                                        <tr>
                                            <td><?php echo esc_html($p->id); ?></td>
                                            <td><strong><?php echo esc_html($p->name); ?></strong></td>
                                            <td><?php echo Helpers::format_currency($p->price); ?></td>
                                            <td><?php echo esc_html($p->duration); ?> <?php esc_html_e('Hari', 'premium-membership'); ?></td>
                                            <td>
                                                <span class="pm-badge pm-badge-<?php echo esc_attr($p->status); ?>">
                                                    <?php echo esc_html(strtoupper($p->status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo Helpers::format_date($p->created_at); ?></td>
                                            <td style="text-align: right;">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=pm-plans&action=edit&id=' . $p->id)); ?>" class="button button-small">
                                                    <?php esc_html_e('Edit', 'premium-membership'); ?>
                                                </a>

                                                <?php
                                                $toggle_url = wp_nonce_url(
                                                    admin_url('admin.php?page=pm-plans&action=toggle&id=' . $p->id),
                                                    Security::NONCE_ACTION_ADMIN,
                                                    '_pm_nonce'
                                                );
                                                ?>
                                                <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">
                                                    <?php echo ($p->status === 'active') ? esc_html__('Deactivate', 'premium-membership') : esc_html__('Activate', 'premium-membership'); ?>
                                                </a>

                                                <?php
                                                $delete_url = wp_nonce_url(
                                                    admin_url('admin.php?page=pm-plans&action=delete&id=' . $p->id),
                                                    Security::NONCE_ACTION_ADMIN,
                                                    '_pm_nonce'
                                                );
                                                ?>
                                                <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-link-delete pm-btn-delete" onclick="return confirm('<?php echo esc_js(__('Apakah Anda yakin ingin menghapus paket ini?', 'premium-membership')); ?>');">
                                                    <?php esc_html_e('Hapus', 'premium-membership'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
