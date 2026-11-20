<?php
/**
 * Template: Member Account Dashboard & Transaction History.
 *
 * @var \WP_User $user
 * @var object|null $membership
 * @var bool $has_active
 * @var int $remaining_days
 * @var array $transactions
 * @var int $total_transactions
 * @var int $paged
 * @var int $per_page
 * @var string $plans_page_url
 *
 * @package PremiumMembership
 */

use PremiumMembership\Helpers;

defined('ABSPATH') || exit;
?>

<div class="pm-container pm-account-wrapper">
    <div class="pm-account-grid">
        <!-- Membership Status Card -->
        <div class="pm-account-card">
            <div class="pm-card-header">
                <h3><?php esc_html_e('Status Membership Anda', 'premium-membership'); ?></h3>
            </div>
            <div class="pm-card-body">
                <div class="pm-user-header">
                    <div class="pm-user-avatar">
                        <?php echo get_avatar($user->ID, 64); ?>
                    </div>
                    <div class="pm-user-details">
                        <h4><?php echo esc_html($user->display_name ?: $user->user_login); ?></h4>
                        <p><?php echo esc_html($user->user_email); ?></p>
                    </div>
                </div>

                <div class="pm-status-box">
                    <?php if ($has_active && $membership): ?>
                        <div class="pm-status-badge-wrap">
                            <span class="pm-badge pm-badge-active"><?php esc_html_e('PREMIUM AKTIF', 'premium-membership'); ?></span>
                        </div>
                        <ul class="pm-status-details">
                            <li>
                                <span><?php esc_html_e('Paket Berlangganan:', 'premium-membership'); ?></span>
                                <strong><?php echo esc_html($membership->plan_name ?: '-'); ?></strong>
                            </li>
                            <li>
                                <span><?php esc_html_e('Tanggal Mulai:', 'premium-membership'); ?></span>
                                <strong><?php echo Helpers::format_date($membership->started_at, 'j F Y'); ?></strong>
                            </li>
                            <li>
                                <span><?php esc_html_e('Berlaku Sampai:', 'premium-membership'); ?></span>
                                <strong><?php echo Helpers::format_date($membership->expired_at, 'j F Y H:i'); ?></strong>
                            </li>
                            <li>
                                <span><?php esc_html_e('Sisa Waktu Aktif:', 'premium-membership'); ?></span>
                                <strong class="pm-days-left"><?php echo esc_html($remaining_days); ?> <?php esc_html_e('Hari', 'premium-membership'); ?></strong>
                            </li>
                        </ul>
                        <div class="pm-status-actions" style="margin-top: 15px;">
                            <a href="<?php echo esc_url($plans_page_url); ?>" class="pm-btn pm-btn-secondary pm-btn-block">
                                <?php esc_html_e('Perpanjang Masa Aktif', 'premium-membership'); ?>
                            </a>
                        </div>
                    <?php elseif ($membership && $membership->status === 'expired'): ?>
                        <div class="pm-status-badge-wrap">
                            <span class="pm-badge pm-badge-expired"><?php esc_html_e('MEMBERSHIP TELAH EXPIRED', 'premium-membership'); ?></span>
                        </div>
                        <p class="pm-expired-note">
                            <?php
                            printf(
                                esc_html__('Membership Anda telah berakhir pada %s. Silakan berlangganan kembali untuk membuka akses artikel premium.', 'premium-membership'),
                                '<strong>' . Helpers::format_date($membership->expired_at, 'j F Y') . '</strong>'
                            );
                            ?>
                        </p>
                        <div class="pm-status-actions" style="margin-top: 15px;">
                            <a href="<?php echo esc_url($plans_page_url); ?>" class="pm-btn pm-btn-primary pm-btn-block">
                                <?php esc_html_e('Berlangganan Kembali', 'premium-membership'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="pm-status-badge-wrap">
                            <span class="pm-badge pm-badge-inactive"><?php esc_html_e('BELUM MEMILIKI MEMBERSHIP', 'premium-membership'); ?></span>
                        </div>
                        <p class="pm-expired-note">
                            <?php esc_html_e('Anda belum memiliki paket membership aktif. Bergabunglah sekarang untuk menikmati artikel premium tanpa batas.', 'premium-membership'); ?>
                        </p>
                        <div class="pm-status-actions" style="margin-top: 15px;">
                            <a href="<?php echo esc_url($plans_page_url); ?>" class="pm-btn pm-btn-primary pm-btn-block">
                                <?php esc_html_e('Lihat Pilihan Paket', 'premium-membership'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Transaction History Card -->
        <div class="pm-account-card">
            <div class="pm-card-header">
                <h3><?php esc_html_e('Riwayat Transaksi Pembayaran', 'premium-membership'); ?></h3>
            </div>
            <div class="pm-card-body" style="padding: 0;">
                <div class="pm-table-responsive">
                    <table class="pm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Order ID', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Paket', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Nominal', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Status', 'premium-membership'); ?></th>
                                <th><?php esc_html_e('Tanggal', 'premium-membership'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="pm-empty-table">
                                        <?php esc_html_e('Belum ada riwayat transaksi.', 'premium-membership'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($tx->order_id); ?></strong></td>
                                        <td><?php echo esc_html($tx->plan_name ?: '-'); ?></td>
                                        <td><?php echo Helpers::format_currency($tx->amount); ?></td>
                                        <td>
                                            <span class="pm-badge pm-badge-<?php echo esc_attr($tx->status); ?>">
                                                <?php echo esc_html(strtoupper($tx->status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo Helpers::format_date($tx->created_at, 'd/m/Y'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $total_pages = ceil($total_transactions / $per_page);
                if ($total_pages > 1):
                ?>
                    <div class="pm-pagination">
                        <?php
                        echo paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo; Sebelumnya',
                            'next_text' => 'Selanjutnya &raquo;',
                            'total'     => $total_pages,
                            'current'   => $paged,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
