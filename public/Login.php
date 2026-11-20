<?php
/**
 * Custom Login, Lost Password, and Password Reset Handler & Shortcodes.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Helpers;

defined('ABSPATH') || exit;

class Login
{
    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        // Shortcodes
        add_shortcode('premium_login', [__CLASS__, 'render_shortcode']);
        add_shortcode('premium_lostpassword', [__CLASS__, 'render_lostpassword_shortcode']);
        add_shortcode('premium_lost_password', [__CLASS__, 'render_lostpassword_shortcode']);

        // Override WordPress login & lostpassword URLs
        add_filter('login_url', [__CLASS__, 'override_login_url'], 10, 3);
        add_filter('lostpassword_url', [__CLASS__, 'override_lostpassword_url'], 10, 2);

        // Replace default wp-login reset link in emails with our custom frontend URL
        add_filter('retrieve_password_message', [__CLASS__, 'filter_reset_password_message'], 10, 4);

        // Intercept default wp-login lostpassword requests and redirect to custom page
        add_action('login_init', [__CLASS__, 'intercept_wp_login']);

        // Handle POST submissions (login, lostpassword, resetpass)
        add_action('template_redirect', [__CLASS__, 'handle_auth_posts'], 5);
    }

    /**
     * Intercept access to wp-login.php?action=lostpassword or action=retrievepassword
     */
    public static function intercept_wp_login(): void
    {
        if (isset($_GET['action']) && in_array($_GET['action'], ['lostpassword', 'retrievepassword'], true)) {
            $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
            $custom_url  = self::get_lostpassword_url($redirect_to);
            if ($custom_url && !str_contains($custom_url, 'wp-login.php')) {
                wp_redirect($custom_url);
                exit;
            }
        }
    }

    /**
     * Override WordPress default login URL to custom page.
     *
     * @param string $login_url
     * @param string $redirect
     * @param bool   $force_reauth
     * @return string
     */
    public static function override_login_url(string $login_url, string $redirect = '', bool $force_reauth = false): string
    {
        // Do not redirect wp-login if user is accessing logout, rp, resetpass, register, etc.
        if (isset($_GET['action']) && in_array($_GET['action'], ['logout', 'rp', 'resetpass', 'register'], true)) {
            return $login_url;
        }

        $page_id = self::get_login_page_id();
        if ($page_id > 0) {
            $custom_url = get_permalink($page_id);
            if ($custom_url) {
                if (!empty($redirect)) {
                    $custom_url = add_query_arg('redirect_to', urlencode($redirect), $custom_url);
                }
                return $custom_url;
            }
        }

        return $login_url;
    }

    /**
     * Override WordPress lostpassword URL to custom page.
     *
     * @param string $lostpassword_url
     * @param string $redirect
     * @return string
     */
    public static function override_lostpassword_url(string $lostpassword_url, string $redirect = ''): string
    {
        $custom_url = self::get_lostpassword_url($redirect);
        return $custom_url ?: $lostpassword_url;
    }

    /**
     * Helper to find the login page ID.
     *
     * @return int
     */
    public static function get_login_page_id(): int
    {
        $page_id = (int) Helpers::get_setting('login_page_id', 0);
        if ($page_id <= 0) {
            global $wpdb;
            $found = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[premium_login]%' LIMIT 1");
            if ($found) {
                $page_id = (int) $found;
            }
        }
        return $page_id;
    }

    /**
     * Get custom login page URL.
     *
     * @param string $redirect_to
     * @return string
     */
    public static function get_login_url(string $redirect_to = ''): string
    {
        $page_id = self::get_login_page_id();
        $base_url = $page_id > 0 ? (get_permalink($page_id) ?: home_url('/login/')) : home_url('/login/');
        if (!empty($redirect_to)) {
            return add_query_arg('redirect_to', urlencode($redirect_to), $base_url);
        }
        return $base_url;
    }

    /**
     * Get custom lostpassword page URL.
     *
     * @param string $redirect_to
     * @return string
     */
    public static function get_lostpassword_url(string $redirect_to = ''): string
    {
        $login_url = self::get_login_url($redirect_to);
        return add_query_arg('action', 'lostpassword', $login_url);
    }

    /**
     * Replace the reset password URL in WordPress core notification email.
     *
     * @param string   $message
     * @param string   $key
     * @param string   $user_login
     * @param \WP_User $user_data
     * @return string
     */
    public static function filter_reset_password_message(string $message, string $key, string $user_login, $user_data): string
    {
        $custom_reset_url = add_query_arg([
            'action' => 'rp',
            'key'    => $key,
            'login'  => rawurlencode($user_login),
        ], self::get_login_url());

        // Replace default wp-login reset link in email with custom link
        $pattern = '/<([^>]*wp-login\.php\?action=rp[^>]*)>/i';
        if (preg_match($pattern, $message)) {
            $message = preg_replace($pattern, '<' . esc_url_raw($custom_reset_url) . '>', $message);
        } else {
            // Fallback: replace raw url without angle brackets
            $pattern_raw = '/https?:\/\/[^\s]+wp-login\.php\?action=rp[^\s]+/i';
            $message = preg_replace($pattern_raw, esc_url_raw($custom_reset_url), $message);
        }

        return $message;
    }

    /**
     * Handle POST submissions (login, lostpassword, resetpass).
     */
    public static function handle_auth_posts(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['pm_action'])) {
            return;
        }

        $action = sanitize_key($_POST['pm_action']);

        if ($action === 'login') {
            self::process_login();
        } elseif ($action === 'lostpassword') {
            self::process_lostpassword();
        } elseif ($action === 'resetpass') {
            self::process_resetpass();
        }
    }

    /**
     * Process user login POST.
     */
    private static function process_login(): void
    {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pm_login_nonce'] ?? '')), 'pm_login_action')) {
            wp_die('Permintaan tidak valid. Harap muat ulang halaman dan coba lagi.', 'Error', ['response' => 403]);
        }

        $username    = sanitize_text_field(wp_unslash($_POST['log_username'] ?? ''));
        $password    = wp_unslash($_POST['log_password'] ?? '');
        $remember    = !empty($_POST['log_remember']);
        $redirect_to = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? ''));

        if (empty($username) || empty($password)) {
            $error = __('Nama pengguna dan kata sandi wajib diisi.', 'premium-membership');
            $login_url = self::get_login_url($redirect_to);
            $login_url = add_query_arg([
                'pm_login_error' => urlencode($error),
                'log_username'   => urlencode($username),
                'redirect_to'    => urlencode($redirect_to),
            ], $login_url);
            wp_redirect($login_url);
            exit;
        }

        $credentials = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            $error_message = $user->get_error_message();
            $error_message = wp_strip_all_tags($error_message);

            $login_url = self::get_login_url($redirect_to);
            $login_url = add_query_arg([
                'pm_login_error' => urlencode($error_message),
                'log_username'   => urlencode($username),
                'redirect_to'    => urlencode($redirect_to),
            ], $login_url);
            wp_redirect($login_url);
            exit;
        }

        // Login successful
        if (!empty($redirect_to)) {
            wp_redirect($redirect_to);
        } else {
            $account_page_id = (int) Helpers::get_setting('account_page_id', 0);
            wp_redirect($account_page_id > 0 ? get_permalink($account_page_id) : home_url('/'));
        }
        exit;
    }

    /**
     * Process lost password POST.
     */
    private static function process_lostpassword(): void
    {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pm_lostpassword_nonce'] ?? '')), 'pm_lostpassword_action')) {
            wp_die('Permintaan tidak valid. Harap muat ulang halaman dan coba lagi.', 'Error', ['response' => 403]);
        }

        $user_login = sanitize_text_field(wp_unslash($_POST['user_login'] ?? ''));
        $lost_url   = self::get_lostpassword_url();

        if (empty($user_login)) {
            $error = __('Silakan masukkan nama pengguna atau alamat email.', 'premium-membership');
            wp_redirect(add_query_arg(['pm_lost_error' => urlencode($error)], $lost_url));
            exit;
        }

        // Call WordPress core function to generate key and send email
        $result = retrieve_password($user_login);

        if (is_wp_error($result)) {
            $error_message = wp_strip_all_tags($result->get_error_message());
            wp_redirect(add_query_arg([
                'pm_lost_error' => urlencode($error_message),
                'user_login'    => urlencode($user_login),
            ], $lost_url));
            exit;
        }

        // Success — redirect with confirmation
        wp_redirect(add_query_arg(['pm_lost_success' => '1'], $lost_url));
        exit;
    }

    /**
     * Process reset password POST.
     */
    private static function process_resetpass(): void
    {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pm_resetpass_nonce'] ?? '')), 'pm_resetpass_action')) {
            wp_die('Permintaan tidak valid. Harap muat ulang halaman dan coba lagi.', 'Error', ['response' => 403]);
        }

        $key    = sanitize_text_field(wp_unslash($_POST['rp_key'] ?? ''));
        $login  = sanitize_text_field(wp_unslash($_POST['rp_login'] ?? ''));
        $pass1  = wp_unslash($_POST['pass1'] ?? '');
        $pass2  = wp_unslash($_POST['pass2'] ?? '');

        $reset_url = add_query_arg([
            'action' => 'rp',
            'key'    => $key,
            'login'  => urlencode($login),
        ], self::get_login_url());

        if (empty($pass1) || empty($pass2)) {
            $error = __('Silakan isi kata sandi baru dan konfirmasi.', 'premium-membership');
            wp_redirect(add_query_arg(['pm_rp_error' => urlencode($error)], $reset_url));
            exit;
        }

        if ($pass1 !== $pass2) {
            $error = __('Kata sandi baru dan konfirmasi tidak cocok. Silakan coba lagi.', 'premium-membership');
            wp_redirect(add_query_arg(['pm_rp_error' => urlencode($error)], $reset_url));
            exit;
        }

        if (strlen($pass1) < 6) {
            $error = __('Kata sandi minimal 6 karakter demi keamanan akun Anda.', 'premium-membership');
            wp_redirect(add_query_arg(['pm_rp_error' => urlencode($error)], $reset_url));
            exit;
        }

        // Verify key
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            $error = __('Tautan pengaturan ulang sandi sudah kedaluwarsa atau tidak valid.', 'premium-membership');
            wp_redirect(add_query_arg(['pm_rp_error' => urlencode($error)], $reset_url));
            exit;
        }

        // Update password
        reset_password($user, $pass1);

        // Redirect to login page with success notification
        $success_msg = __('Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.', 'premium-membership');
        wp_redirect(add_query_arg([
            'pm_login_success' => urlencode($success_msg),
            'log_username'     => urlencode($login),
        ], self::get_login_url()));
        exit;
    }

    /**
     * Render [premium_lostpassword] shortcode directly.
     *
     * @param array $atts
     * @return string
     */
    public static function render_lostpassword_shortcode(array $atts = []): string
    {
        $error   = isset($_GET['pm_lost_error']) ? sanitize_text_field(urldecode($_GET['pm_lost_error'])) : '';
        $success = '';
        if (isset($_GET['pm_lost_success'])) {
            $success = __('Tautan untuk mengatur ulang kata sandi telah dikirimkan ke alamat email Anda.', 'premium-membership');
        }

        return Helpers::render_template('lostpassword', [
            'error'   => $error,
            'success' => $success,
        ], false);
    }

    /**
     * Render [premium_login] shortcode.
     *
     * @param array $atts
     * @return string
     */
    public static function render_shortcode(array $atts = []): string
    {
        $action = sanitize_key($_GET['action'] ?? '');

        // 1. If user requested lostpassword
        if ($action === 'lostpassword') {
            return self::render_lostpassword_shortcode($atts);
        }

        // 2. If user clicked reset password link from email (action=rp or action=resetpass)
        if ($action === 'rp' || $action === 'resetpass') {
            $key   = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
            $login = sanitize_text_field(wp_unslash($_GET['login'] ?? ''));
            $error = isset($_GET['pm_rp_error']) ? sanitize_text_field(urldecode($_GET['pm_rp_error'])) : '';

            $user = check_password_reset_key($key, $login);
            $key_valid = !is_wp_error($user);

            return Helpers::render_template('resetpass', [
                'key'       => $key,
                'login'     => $login,
                'error'     => $error,
                'key_valid' => $key_valid,
            ], false);
        }

        // 3. Normal Login Form
        if (is_user_logged_in()) {
            $account_page_id = (int) Helpers::get_setting('account_page_id', 0);
            $account_url = $account_page_id > 0 ? get_permalink($account_page_id) : home_url('/');
            $logout_url  = wp_logout_url(get_permalink());

            return '<div class="pm-container"><div class="pm-alert pm-alert-info" style="padding:16px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1d4ed8; margin:20px 0;">' .
                   '👋 ' . esc_html__('Anda saat ini sudah masuk (login). ', 'premium-membership') .
                   '<a href="' . esc_url($account_url) . '" style="font-weight:600; text-decoration:underline;">' . esc_html__('Buka Akun Member', 'premium-membership') . '</a>' .
                   ' | <a href="' . esc_url($logout_url) . '" style="color:#ef4444; text-decoration:underline;">' . esc_html__('Keluar (Logout)', 'premium-membership') . '</a>' .
                   '</div></div>';
        }

        $error       = isset($_GET['pm_login_error']) ? sanitize_text_field(urldecode($_GET['pm_login_error'])) : '';
        $success     = isset($_GET['pm_login_success']) ? sanitize_text_field(urldecode($_GET['pm_login_success'])) : '';
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(urldecode($_GET['redirect_to'])) : '';

        return Helpers::render_template('login', [
            'redirect_to' => $redirect_to,
            'error'       => $error,
            'success'     => $success,
        ], false);
    }
}