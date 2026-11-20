<?php
/**
 * Template: Checkout & Order Confirmation.
 *
 * @var object $plan
 * @var \WP_User $user
 * @var object|null $current_membership
 * @var \PremiumMembership\PaymentGatewayInterface $gateway
 * @var string $error_message
 * @var string $success_message
 * @var string $nonce
 *
 * @package PremiumMembership
 */

use PremiumMembership\Helpers;

defined('ABSPATH') || exit;
?>

<div class="pm-container pm-checkout-wrapper">
    <div class="pm-checkout-card">
        <div class="pm-checkout-header">
            <h2><?php esc_html_e('Konfirmasi Pembelian Membership', 'premium-membership'); ?></h2>
            <p><?php esc_html_e('Periksa rincian pesanan Anda sebelum melanjutkan ke pembayaran.', 'premium-membership'); ?></p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="pm-alert pm-alert-error">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="pm-alert pm-alert-success">
                <p><?php echo esc_html($success_message); ?></p>
            </div>
        <?php endif; ?>

        <div class="pm-checkout-body">
            <!-- Order Details -->
            <div class="pm-order-summary">
                <h3><?php esc_html_e('Rincian Paket', 'premium-membership'); ?></h3>
                
                <div class="pm-summary-row">
                    <span><?php esc_html_e('Nama Paket:', 'premium-membership'); ?></span>
                    <strong><?php echo esc_html($plan->name); ?></strong>
                </div>

                <div class="pm-summary-row">
                    <span><?php esc_html_e('Durasi Masa Aktif:', 'premium-membership'); ?></span>
                    <strong><?php echo esc_html($plan->duration); ?> <?php esc_html_e('Hari', 'premium-membership'); ?></strong>
                </div>

                <?php if ($current_membership && $current_membership->status === 'active'): ?>
                    <div class="pm-summary-row pm-renewal-notice">
                        <span><?php esc_html_e('Tipe Pembelian:', 'premium-membership'); ?></span>
                        <strong style="color: #2e7d32;"><?php esc_html_e('Perpanjangan (Renewal)', 'premium-membership'); ?></strong>
                    </div>
                <?php endif; ?>

                <div class="pm-summary-row pm-total-row">
                    <span><?php esc_html_e('Total Pembayaran:', 'premium-membership'); ?></span>
                    <strong class="pm-total-price"><?php echo Helpers::format_currency($plan->price); ?></strong>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="pm-customer-info">
                <h3><?php esc_html_e('Informasi Akun Member', 'premium-membership'); ?></h3>
                <div class="pm-info-box">
                    <p><strong><?php esc_html_e('Username:', 'premium-membership'); ?></strong> <?php echo esc_html($user->user_login); ?></p>
                    <p><strong><?php esc_html_e('Email:', 'premium-membership'); ?></strong> <?php echo esc_html($user->user_email); ?></p>
                </div>
            </div>

            <!-- Payment Action Form -->
            <form method="post" action="<?php echo esc_url(get_permalink()); ?>" class="pm-checkout-form">
                <input type="hidden" name="pm_action" value="process_checkout" />
                <input type="hidden" name="pm_checkout_nonce" value="<?php echo esc_attr($nonce); ?>" />
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan->id); ?>" />
                <input type="hidden" name="gateway" value="<?php echo esc_attr($gateway->get_id()); ?>" />

                <div class="pm-gateway-info">
                    <p class="pm-gateway-label" style="margin-bottom: 8px;">
                        <strong><?php esc_html_e('Metode Pembayaran Digital:', 'premium-membership'); ?></strong>
                    </p>
                    <div class="pm-payment-channels" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px;">
                        <span style="background: #e0f2fe; color: #0369a1; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid #bae6fd;">🏦 1. m-BCA</span>
                        <span style="background: #eff6ff; color: #1d4ed8; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid #bfdbfe;">📱 2. DANA</span>
                        <span style="background: #faf5ff; color: #6b21a8; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid #e9d5ff;">🟣 3. OVO</span>
                        <span style="background: #fef2f2; color: #b91c1c; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid #fecaca;">📷 4. QRIS</span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin-top: -5px; margin-bottom: 15px;">
                        💡 <em><?php esc_html_e('*Pembayaran instan digital langsung dari smartphone Anda (Tanpa bayar ke konter/minimarket).', 'premium-membership'); ?></em>
                    </p>
                </div>

                <div class="pm-checkout-actions">
                    <button type="submit" class="pm-btn pm-btn-primary pm-btn-large pm-btn-block">
                        <?php esc_html_e('Lanjut Bayar Sekarang', 'premium-membership'); ?> &rarr;
                    </button>
                </div>
            </form>

            <div class="pm-security-badges">
                <span class="pm-sec-item">🔒 <?php esc_html_e('Pembayaran Aman & Terenkripsi', 'premium-membership'); ?></span>
                <span class="pm-sec-item">⚡ <?php esc_html_e('Aktivasi Otomatis Real-time', 'premium-membership'); ?></span>
            </div>
        </div>
    </div>
</div>
