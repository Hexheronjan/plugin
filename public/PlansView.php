<?php
/**
 * Frontend Plans Pricing Table View & Shortcode Handler.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Database;
use PremiumMembership\Helpers;
use PremiumMembership\Membership;

defined('ABSPATH') || exit;

class PlansView
{
    /**
     * Render [premium_plans] shortcode.
     *
     * @param array $atts
     * @return string
     */
    public static function render(array $atts = []): string
    {
        global $wpdb;
        $table = Database::plans_table();

        $plans = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' ORDER BY price ASC"
        );

        $user_id = get_current_user_id();
        $is_logged_in = is_user_logged_in();
        $has_active_membership = $user_id ? Membership::has_active_membership($user_id) : false;
        $current_membership = $user_id ? Membership::get_user_membership($user_id) : null;

        $checkout_page_id = (int) Helpers::get_setting('checkout_page_id', 0);
        $checkout_base_url = $checkout_page_id > 0 && get_post($checkout_page_id)
            ? get_permalink($checkout_page_id)
            : add_query_arg('pm_page', 'checkout', home_url('/'));

        return Helpers::render_template('plans', [
            'plans'                 => $plans ?: [],
            'is_logged_in'          => $is_logged_in,
            'has_active_membership' => $has_active_membership,
            'current_membership'    => $current_membership,
            'checkout_base_url'     => $checkout_base_url,
            'login_url'             => wp_login_url(get_permalink()),
        ]);
    }
}
