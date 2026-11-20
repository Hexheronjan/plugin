<?php
/**
 * Template: Membership Plans Pricing Table.
 *
 * @var array $plans
 * @var bool $is_logged_in
 * @var bool $has_active_membership
 * @var object|null $current_membership
 * @var string $checkout_base_url
 * @var string $login_url
 *
 * @package PremiumMembership
 */

use PremiumMembership\Helpers;

defined('ABSPATH') || exit;
?>

<div class="pm-container pm-plans-wrapper">
    <div class="pm-plans-header">
        <h2 class="pm-title"><?php esc_html_e('Pilihan Paket Premium Membership', 'premium-membership'); ?></h2>
        <p class="pm-subtitle">
            <?php esc_html_e('Dapatkan akses tanpa batas ke seluruh artikel berita premium, analisis mendalam, dan liputan eksklusif kami.', 'premium-membership'); ?>
        </p>

        <?php if ($has_active_membership && $current_membership): ?>
            <div class="pm-alert pm-alert-success" style="margin-top: 15px;">
                <span class="pm-badge pm-badge-active"><?php esc_html_e('STATUS AKTIF', 'premium-membership'); ?></span>
                <p style="margin: 6px 0 0 0;">
                    <?php
                    printf(
                        esc_html__('Anda saat ini memiliki membership aktif (%s) sampai %s. Anda dapat membeli paket di bawah untuk memperpanjang masa aktif.', 'premium-membership'),
                        '<strong>' . esc_html($current_membership->plan_name) . '</strong>',
                        '<strong>' . Helpers::format_date($current_membership->expired_at, 'j F Y') . '</strong>'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div class="pm-plans-grid">
        <?php if (empty($plans)): ?>
            <div class="pm-empty-notice">
                <p><?php esc_html_e('Belum ada paket membership yang tersedia saat ini.', 'premium-membership'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($plans as $plan): ?>
                <?php
                $checkout_url = add_query_arg('plan_id', $plan->id, $checkout_base_url);
                $login_url    = \PremiumMembership\Frontend\Login::get_login_url($checkout_url);
                $register_url = \PremiumMembership\Frontend\Register::get_register_url($checkout_url);
                ?>
                <div class="pm-plan-card">
                    <div class="pm-plan-badge"><?php echo esc_html($plan->duration); ?> <?php esc_html_e('Hari', 'premium-membership'); ?></div>
                    <h3 class="pm-plan-name"><?php echo esc_html($plan->name); ?></h3>
                    
                    <div class="pm-plan-price-wrap">
                        <span class="pm-plan-price"><?php echo Helpers::format_currency($plan->price); ?></span>
                        <span class="pm-plan-period">/ <?php echo esc_html($plan->duration); ?> <?php esc_html_e('hari', 'premium-membership'); ?></span>
                    </div>

                    <ul class="pm-plan-features">
                        <li><span class="pm-icon-check">✓</span> <?php esc_html_e('Akses penuh semua artikel premium', 'premium-membership'); ?></li>
                        <li><span class="pm-icon-check">✓</span> <?php esc_html_e('Analisis & laporan mendalam', 'premium-membership'); ?></li>
                        <li><span class="pm-icon-check">✓</span> <?php esc_html_e('Bebas iklan banner yang mengganggu', 'premium-membership'); ?></li>
                        <li><span class="pm-icon-check">✓</span> <?php esc_html_e('Masa aktif', 'premium-membership'); ?> <?php echo esc_html($plan->duration); ?> <?php esc_html_e('hari penuh', 'premium-membership'); ?></li>
                    </ul>

                    <div class="pm-plan-action">
                        <?php if ($has_active_membership): ?>
                            <a href="<?php echo esc_url($checkout_url); ?>" class="pm-btn pm-btn-primary pm-btn-block">
                                <?php esc_html_e('Perpanjang Paket Ini', 'premium-membership'); ?>
                            </a>
                        <?php elseif ($is_logged_in): ?>
                            <a href="<?php echo esc_url($checkout_url); ?>" class="pm-btn pm-btn-primary pm-btn-block">
                                <?php esc_html_e('Pilih Paket', 'premium-membership'); ?>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo esc_url($register_url); ?>" class="pm-btn pm-btn-primary pm-btn-block">
                                <?php esc_html_e('Daftar & Berlangganan', 'premium-membership'); ?>
                            </a>
                            <a href="<?php echo esc_url($login_url); ?>" class="pm-btn pm-btn-outline pm-btn-block" style="margin-top:8px; font-size:13px;">
                                <?php esc_html_e('Sudah punya akun? Login', 'premium-membership'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
