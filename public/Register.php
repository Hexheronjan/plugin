<?php
/**
 * Custom Registration Handler & Shortcode.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Helpers;

defined('ABSPATH') || exit;

class Register
{
    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        // Shortcode for [premium_register] on any WordPress page
        add_shortcode('premium_register', [__CLASS__, 'render_shortcode']);

        // Override WordPress default registration URL to our custom page
        add_filter('register_url', [__CLASS__, 'override_register_url']);

        // Handle POST from our custom register form via template_redirect
        add_action('template_redirect', [__CLASS__, 'handle_register_post'], 5);
    }

    /**
     * Override the default WordPress registration URL to our custom page.
     *
     * @param string $url
     * @return string
     */
    public static function override_register_url(string $url): string
    {
        $page_id = (int) Helpers::get_setting('register_page_id', 0);
        if ($page_id <= 0) {
            global $wpdb;
            $found = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[premium_register]%' LIMIT 1");
            if ($found) {
                $page_id = (int) $found;
            }
        }
        if ($page_id > 0) {
            return get_permalink($page_id) ?: $url;
        }
        return $url;
    }

    /**
     * Get registration page URL with optional redirect.
     *
     * @param string $redirect_to
     * @return string
     */
    public static function get_register_url(string $redirect_to = ''): string
    {
        $page_id = (int) Helpers::get_setting('register_page_id', 0);
        if ($page_id <= 0) {
            global $wpdb;
            $found = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[premium_register]%' LIMIT 1");
            if ($found) {
                $page_id = (int) $found;
            }
        }
        $base_url = $page_id > 0 ? (get_permalink($page_id) ?: home_url('/daftar-member/')) : home_url('/daftar-member/');
        if (!empty($redirect_to)) {
            return add_query_arg('redirect_to', urlencode($redirect_to), $base_url);
        }
        return $base_url;
    }

    /**
     * Handle POST submission from our custom register form.
     * This fires on template_redirect so it processes on real WordPress pages (not wp-login.php).
     */
    public static function handle_register_post(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            empty($_POST['pm_action']) ||
            $_POST['pm_action'] !== 'register'
        ) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pm_register_nonce'] ?? '')), 'pm_register_action')) {
            wp_die('Permintaan tidak valid. Harap muat ulang halaman dan coba lagi.', 'Error', ['response' => 403]);
        }

        $username    = sanitize_user(wp_unslash($_POST['reg_username'] ?? ''));
        $email       = sanitize_email(wp_unslash($_POST['reg_email'] ?? ''));
        $password    = wp_unslash($_POST['reg_password'] ?? '');
        $password2   = wp_unslash($_POST['reg_password2'] ?? '');
        $redirect_to = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? ''));

        $error = '';

        if (empty($username)) {
            $error = __('Nama pengguna tidak boleh kosong.', 'premium-membership');
        } elseif (!validate_username($username)) {
            $error = __('Nama pengguna mengandung karakter tidak valid. Gunakan huruf, angka, atau garis bawah saja.', 'premium-membership');
        } elseif (username_exists($username)) {
            $error = __('Nama pengguna sudah digunakan. Silakan pilih yang lain.', 'premium-membership');
        } elseif (empty($email) || !is_email($email)) {
            $error = __('Format alamat email tidak valid.', 'premium-membership');
        } elseif (email_exists($email)) {
            $error = __('Alamat email ini sudah terdaftar. Silakan gunakan email lain atau login.', 'premium-membership');
        } elseif (strlen($password) < 8) {
            $error = __('Kata sandi minimal 8 karakter.', 'premium-membership');
        } elseif ($password !== $password2) {
            $error = __('Konfirmasi kata sandi tidak cocok. Periksa kembali.', 'premium-membership');
        }

        if (!empty($error)) {
            // Store error in transient and redirect back to avoid POST re-submit
            set_transient('pm_reg_error_' . session_id(), $error, 60);
            $register_url = self::get_register_url($redirect_to);
            $register_url = add_query_arg([
                'pm_reg_error'   => urlencode($error),
                'redirect_to'    => urlencode($redirect_to),
                'reg_username'   => urlencode($username),
                'reg_email'      => urlencode($email),
            ], $register_url);
            wp_redirect($register_url);
            exit;
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            $register_url = self::get_register_url($redirect_to);
            $register_url = add_query_arg('pm_reg_error', urlencode($user_id->get_error_message()), $register_url);
            wp_redirect($register_url);
            exit;
        }

        // Send welcome email
        wp_new_user_notification($user_id, null, 'both');

        // Auto-login after successful registration
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, false);

        // Redirect to intended page (e.g. checkout) or plans page
        if (!empty($redirect_to)) {
            wp_redirect($redirect_to);
        } else {
            $plans_page_id = (int) Helpers::get_setting('plans_page_id', 0);
            wp_redirect($plans_page_id > 0 ? get_permalink($plans_page_id) : home_url('/'));
        }
        exit;
    }

    /**
     * Shortcode [premium_register] — embed form on any WordPress page.
     *
     * @param array $atts
     * @return string
     */
    public static function render_shortcode(array $atts = []): string
    {
        if (is_user_logged_in()) {
            $account_page_id = (int) Helpers::get_setting('account_page_id', 0);
            $account_url = $account_page_id > 0 ? get_permalink($account_page_id) : home_url('/');
            return '<div class="pm-container"><div class="pm-alert pm-alert-info" style="padding:16px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1d4ed8; margin:20px 0;">' .
                   '✅ ' . esc_html__('Anda sudah login. ', 'premium-membership') .
                   '<a href="' . esc_url($account_url) . '">' . esc_html__('Lihat Akun Member', 'premium-membership') . '</a>' .
                   '</div></div>';
        }

        // Read values from URL params (after redirect back on error)
        $error       = isset($_GET['pm_reg_error']) ? sanitize_text_field(urldecode($_GET['pm_reg_error'])) : '';
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(urldecode($_GET['redirect_to'])) : '';

        // render_template returns HTML string when $echo=false
        return Helpers::render_template('register', [
            'redirect_to' => $redirect_to,
            'error'       => $error,
            'success'     => '',
        ], false);
    }
}
