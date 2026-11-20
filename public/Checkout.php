<?php
/**
 * Frontend Checkout View & Shortcode Handler.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Database;
use PremiumMembership\Helpers;
use PremiumMembership\Security;
use PremiumMembership\Transaction;
use PremiumMembership\PaymentManager;
use PremiumMembership\Membership;

defined('ABSPATH') || exit;

class Checkout
{
    /**
     * Handle checkout page rendering and form submission.
     *
     * @param array $atts
     * @return string
     */
    public static function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            $current_url = get_permalink() ?: home_url('/');
            if (!empty($_GET['plan_id'])) {
                $current_url = add_query_arg('plan_id', absint($_GET['plan_id']), $current_url);
            }

            $register_url = \PremiumMembership\Frontend\Register::get_register_url($current_url);
            $login_url    = \PremiumMembership\Frontend\Login::get_login_url($current_url);
            return '<div class="pm-container pm-auth-notice">' .
                   '<div class="pm-alert pm-alert-warning">' .
                   '<h3>' . esc_html__('Silakan Login atau Daftar Terlebih Dahulu', 'premium-membership') . '</h3>' .
                   '<p>' . esc_html__('Untuk melanjutkan pembelian membership premium, Anda dapat masuk ke akun atau mendaftar akun baru.', 'premium-membership') . '</p>' .
                   '<div style="display:flex; gap:10px; margin-top:12px;">' .
                   '<a href="' . esc_url($login_url) . '" class="pm-btn pm-btn-primary">' . esc_html__('Login / Masuk', 'premium-membership') . '</a>' .
                   '<a href="' . esc_url($register_url) . '" class="pm-btn pm-btn-outline">' . esc_html__('Daftar Akun Baru (Register)', 'premium-membership') . '</a>' .
                   '</div>' .
                   '</div></div>';
        }

        $user_id = get_current_user_id();
        $error_message = '';
        $success_message = '';

        // Handle Checkout Form Submission (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pm_action']) && $_POST['pm_action'] === 'process_checkout') {
            $nonce = sanitize_text_field(wp_unslash($_POST['pm_checkout_nonce'] ?? ''));
            if (!Security::verify_nonce($nonce, 'pm_checkout_action')) {
                $error_message = esc_html__('Sesi keamanan Anda telah berakhir. Silakan muat ulang halaman.', 'premium-membership');
            } else {
                $plan_id = absint($_POST['plan_id'] ?? 0);
                $gateway_slug = sanitize_key($_POST['gateway'] ?? Helpers::get_setting('active_gateway', 'mock'));

                $gateway = PaymentManager::get_gateway($gateway_slug) ?: PaymentManager::get_active_gateway();

                // 1. Create Transaction in Database (Price retrieved directly from DB)
                $transaction = Transaction::create_transaction($user_id, $plan_id, $gateway->get_id());

                if (!$transaction) {
                    $error_message = esc_html__('Gagal membuat pesanan. Paket membership tidak valid atau sudah tidak aktif.', 'premium-membership');
                } else {
                    // 2. Request Payment URL from Gateway
                    $payment_response = $gateway->create_payment($transaction);

                    if (!empty($payment_response['success']) && !empty($payment_response['payment_url'])) {
                        Transaction::update_payment_url((int) $transaction->id, $payment_response['payment_url']);

                        // 3. Redirect user to payment gateway
                        wp_redirect($payment_response['payment_url']);
                        exit;
                    } else {
                        $error_message = !empty($payment_response['message']) 
                            ? esc_html($payment_response['message']) 
                            : esc_html__('Gagal menghubungkan ke gateway pembayaran.', 'premium-membership');
                    }
                }
            }
        }

        // Get requested plan
        $plan_id = absint($_GET['plan_id'] ?? 0);
        global $wpdb;
        $plans_table = Database::plans_table();

        $plan = null;
        if ($plan_id > 0) {
            $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$plans_table} WHERE id = %d AND status = 'active' LIMIT 1", $plan_id));
        }

        // If no plan selected or not found, show selection prompt or first plan
        if (!$plan) {
            $available_plans = $wpdb->get_results("SELECT * FROM {$plans_table} WHERE status = 'active' ORDER BY price ASC");
            if (empty($available_plans)) {
                return '<div class="pm-container"><div class="pm-alert pm-alert-info">' . esc_html__('Belum ada paket membership aktif yang tersedia saat ini.', 'premium-membership') . '</div></div>';
            }
            $plan = $available_plans[0];
        }

        $active_gateway = PaymentManager::get_active_gateway();
        $current_membership = Membership::get_user_membership($user_id);

        return Helpers::render_template('checkout', [
            'plan'               => $plan,
            'user'               => wp_get_current_user(),
            'current_membership' => $current_membership,
            'gateway'            => $active_gateway,
            'error_message'      => $error_message,
            'success_message'    => $success_message,
            'nonce'              => Security::create_nonce('pm_checkout_action'),
        ]);
    }
}
