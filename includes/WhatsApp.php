<?php
/**
 * Universal WhatsApp Gateway Integration.
 * Supports Fonnte, Wablas, Starsender, Woowa, UltraMsg, WPPConnect, Whacenter, and custom WA APIs.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class WhatsApp
{
    /**
     * Send WhatsApp message via any configured gateway provider.
     *
     * @param string $phone Target phone number (e.g. 08123456789 or 628123456789)
     * @param string $message Text content
     * @return bool
     */
    public static function send_message(string $phone, string $message): bool
    {
        $settings = get_option('pm_settings', []);
        $enabled = !empty($settings['enable_wa_notification']);
        $api_token = trim($settings['wa_api_token'] ?? '');
        $endpoint = trim($settings['wa_endpoint_url'] ?? 'https://api.fonnte.com/send');

        if (!$enabled || empty($api_token) || empty($phone) || empty($message)) {
            return false;
        }

        // Format phone number to international standard (628xxx)
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean_phone, '0')) {
            $clean_phone = '62' . substr($clean_phone, 1);
        }

        // Universal payload to be compatible with multiple gateway parameter names
        $body = [
            'target'      => $clean_phone,      // Fonnte
            'phone'       => $clean_phone,      // Wablas / Woowa
            'to'          => $clean_phone,      // Starsender / UltraMsg / WPPConnect
            'receiver'    => $clean_phone,      // Whacenter
            'number'      => $clean_phone,      // MPWA
            'message'     => $message,          // Standard message
            'body'        => $message,          // UltraMsg body
            'msg'         => $message,          // Custom
            'countryCode' => '62',
            'api_key'     => $api_token,        // Some gateways expect token in body
            'token'       => $api_token,
            'secret'      => $api_token,
        ];

        $headers = [
            'Authorization' => $api_token,
            'token'         => $api_token,
            'api-key'       => $api_token,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            Logger::log('wa_send_failed', 'Gagal mengirim pesan WhatsApp: ' . $response->get_error_message(), ['phone' => $clean_phone]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            Logger::log('wa_sent_success', 'Notifikasi WhatsApp berhasil dikirim ke: ' . $clean_phone);
            return true;
        }

        Logger::log('wa_send_response', 'Respon server WA Gateway: ' . wp_remote_retrieve_body($response), ['status' => $status_code]);
        return false;
    }

    /**
     * Send Payment Success & Active Membership notification.
     *
     * @param object $transaction
     * @param object|null $plan
     */
    public static function send_payment_success_notification(object $transaction, ?object $plan = null): void
    {
        $user = get_userdata($transaction->user_id);
        if (!$user) {
            return;
        }

        // Retrieve phone from user meta or billing
        $phone = get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, 'phone', true);
        if (empty($phone)) {
            $phone = Helpers::get_setting('admin_wa_phone', '');
        }

        if (empty($phone)) {
            return;
        }

        $plan_name = $plan ? $plan->name : 'Premium Membership';
        $amount_fmt = Helpers::format_currency($transaction->amount);

        $msg  = "🎉 *PEMBAYARAN MEMBERSHIP BERHASIL!*\n\n";
        $msg .= "Halo *{$user->display_name}*,\n";
        $msg .= "Terima kasih, pembayaran untuk pesanan Anda telah berhasil dikonfirmasi.\n\n";
        $msg .= "📋 *Rincian Pesanan:*\n";
        $msg .= "• Order ID: *{$transaction->order_id}*\n";
        $msg .= "• Paket: *{$plan_name}*\n";
        $msg .= "• Total: *{$amount_fmt}*\n";
        $msg .= "• Status: *AKTIF*\n\n";
        $msg .= "Sekarang Anda dapat membaca seluruh artikel berita eksklusif tanpa batasan.\n";
        $msg .= "Akses akun Anda di: " . home_url('/index.php/akun-member/') . "\n\n";
        $msg .= "_Pesan otomatis dari portal berita._";

        self::send_message($phone, $msg);
    }

    /**
     * Send Pending Order Tagihan Notification.
     *
     * @param object $transaction
     * @param string $payment_url
     */
    public static function send_order_created_notification(object $transaction, string $payment_url): void
    {
        $user = get_userdata($transaction->user_id);
        if (!$user) {
            return;
        }

        $phone = get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, 'phone', true);
        if (empty($phone)) {
            $phone = Helpers::get_setting('admin_wa_phone', '');
        }

        if (empty($phone)) {
            return;
        }

        $amount_fmt = Helpers::format_currency($transaction->amount);

        $msg  = "🔔 *TAGIHAN PEMBAYARAN MEMBERSHIP*\n\n";
        $msg .= "Halo *{$user->display_name}*,\n";
        $msg .= "Pesanan paket membership Anda telah dibuat.\n\n";
        $msg .= "📋 *Rincian Tagihan:*\n";
        $msg .= "• Order ID: *{$transaction->order_id}*\n";
        $msg .= "• Total Pembayaran: *{$amount_fmt}*\n";
        $msg .= "• Metode: m-BCA / QRIS / DANA / OVO\n\n";
        $msg .= "Silakan selesaikan pembayaran melalui tautan berikut:\n";
        $msg .= "👉 {$payment_url}\n\n";
        $msg .= "Membership akan aktif otomatis setelah pembayaran selesai.";

        self::send_message($phone, $msg);
    }
}
