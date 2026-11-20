<?php
/**
 * Template: Premium Article Paywall Box (Truncated Server-side).
 *
 * @var bool $is_logged_in
 * @var string $login_url
 * @var string $plans_url
 *
 * @package PremiumMembership
 */

defined('ABSPATH') || exit;
?>

<div class="pm-paywall-box">
    <div class="pm-paywall-content">
        <div class="pm-paywall-icon">
            <span class="dashicons dashicons-lock">🔒</span>
        </div>
        <h3 class="pm-paywall-title"><?php esc_html_e('Lanjutkan Membaca...?', 'premium-membership'); ?></h3>
        <p class="pm-paywall-desc">
            <?php esc_html_e('Paragraf di atas adalah cuplikan gratis. Untuk membaca ulasan lengkap, tips mendalam, dan berita eksklusif bermutu ini, silakan pilih paket berlangganan Anda.', 'premium-membership'); ?>
        </p>

        <div class="pm-paywall-actions">
            <?php if ($is_logged_in): ?>
                <a href="<?php echo esc_url($plans_url); ?>" class="pm-btn pm-btn-primary pm-btn-large">
                    <?php esc_html_e('Pilih Paket Berlangganan', 'premium-membership'); ?> &rarr;
                </a>
            <?php else: ?>
                <a href="<?php echo esc_url($plans_url); ?>" class="pm-btn pm-btn-primary pm-btn-large">
                    <?php esc_html_e('Berlangganan Sekarang', 'premium-membership'); ?> &rarr;
                </a>
                <a href="<?php echo esc_url($login_url); ?>" class="pm-btn pm-btn-outline pm-btn-large">
                    <?php esc_html_e('Sudah Punya Akun? Login', 'premium-membership'); ?>
                </a>
            <?php endif; ?>
        </div>

        <p class="pm-paywall-note">
            <?php esc_html_e('Tersedia pembayaran instan digital: QRIS, m-BCA, DANA, OVO.', 'premium-membership'); ?>
        </p>
    </div>
</div>
