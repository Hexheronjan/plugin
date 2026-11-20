<?php
/**
 * Member Account Dashboard View & Shortcode Handler.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Helpers;
use PremiumMembership\Membership;
use PremiumMembership\Transaction;

defined('ABSPATH') || exit;

class Account
{
    /**
     * Render [premium_account] shortcode.
     *
     * @param array $atts
     * @return string
     */
    public static function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="pm-container pm-auth-notice">' .
                   '<div class="pm-alert pm-alert-warning">' .
                   '<h3>' . esc_html__('Akses Akun Member', 'premium-membership') . '</h3>' .
                   '<p>' . esc_html__('Silakan login terlebih dahulu untuk melihat dashboard membership dan riwayat transaksi Anda.', 'premium-membership') . '</p>' .
                   '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="pm-btn pm-btn-primary">' . esc_html__('Login Sekarang', 'premium-membership') . '</a>' .
                   '</div></div>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        $membership = Membership::get_user_membership($user_id);
        $has_active = Membership::has_active_membership($user_id);
        $remaining_days = $membership ? Helpers::get_remaining_days($membership->expired_at) : 0;

        // Fetch transactions with pagination
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 10;
        $offset = ($paged - 1) * $per_page;

        $transactions_data = Transaction::get_user_transactions($user_id, $per_page, $offset);

        $plans_page_url = PremiumContent::get_plans_page_url();

        return Helpers::render_template('account', [
            'user'              => $user,
            'membership'        => $membership,
            'has_active'        => $has_active,
            'remaining_days'    => $remaining_days,
            'transactions'      => $transactions_data['items'],
            'total_transactions'=> $transactions_data['total'],
            'paged'             => $paged,
            'per_page'          => $per_page,
            'plans_page_url'    => $plans_page_url,
        ]);
    }
}
