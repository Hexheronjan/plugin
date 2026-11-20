<?php
/**
 * Utility and formatting helpers.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Helpers
{
    /**
     * Format currency based on settings.
     *
     * @param int|float $amount
     * @return string
     */
    public static function format_currency($amount): string
    {
        $settings = get_option('pm_settings', []);
        $symbol = $settings['currency_symbol'] ?? 'Rp';
        $amount = (int) $amount;

        return esc_html($symbol . ' ' . number_format($amount, 0, ',', '.'));
    }

    /**
     * Format MySQL datetime to localized human readable date.
     *
     * @param string|null $mysql_datetime
     * @param string $format
     * @return string
     */
    public static function format_date(?string $mysql_datetime, string $format = 'j F Y H:i'): string
    {
        if (empty($mysql_datetime) || $mysql_datetime === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($mysql_datetime);
        if (!$timestamp) {
            return '-';
        }

        return esc_html(wp_date($format, $timestamp));
    }

    /**
     * Calculate remaining active days from expired_at.
     *
     * @param string|null $expired_at
     * @return int
     */
    public static function get_remaining_days(?string $expired_at): int
    {
        if (empty($expired_at)) {
            return 0;
        }

        $now = current_datetime()->getTimestamp();
        $expiry_dt = date_create_from_format('Y-m-d H:i:s', $expired_at, wp_timezone());
        $expiry = $expiry_dt ? $expiry_dt->getTimestamp() : strtotime($expired_at);

        if ($expiry <= $now) {
            return 0;
        }

        return (int) ceil(($expiry - $now) / DAY_IN_SECONDS);
    }

    /**
     * Mask sensitive strings (API keys, secrets, tokens).
     *
     * @param string|null $secret
     * @return string
     */
    public static function mask_secret(?string $secret): string
    {
        if (empty($secret)) {
            return '-';
        }

        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($secret, 0, 4);
        $suffix = substr($secret, -4);
        $masked = str_repeat('*', min(12, $length - 8));

        return esc_html($prefix . $masked . $suffix);
    }

    /**
     * Get a specific plugin setting.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_setting(string $key, $default = null)
    {
        $settings = get_option('pm_settings', []);
        return $settings[$key] ?? $default;
    }

    /**
     * Get Webhook Endpoint URL.
     *
     * @return string
     */
    public static function get_webhook_url(): string
    {
        return rest_url('premium-membership/v1/webhook');
    }

    /**
     * Render a PHP template file with arguments.
     *
     * @param string $template_name Base name without .php
     * @param array $args Variables passed to template
     * @param bool $echo Output or return buffer
     * @return string
     */
    public static function render_template(string $template_name, array $args = [], bool $echo = false): string
    {
        $template_file = PM_PLUGIN_DIR . 'templates/' . $template_name . '.php';

        if (!file_exists($template_file)) {
            return '';
        }

        // Extract variables to local scope
        extract($args, EXTR_SKIP);

        ob_start();
        include $template_file;
        $output = ob_get_clean();

        if ($echo) {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return '';
        }

        return $output;
    }
}
