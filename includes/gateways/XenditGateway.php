<?php
/**
 * Xendit Payment Gateway Integration (Invoice API).
 * Supports BCA, Mandiri, BRI, BNI, QRIS, OVO, DANA, ShopeePay, Alfamart/Indomaret.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class XenditGateway implements PaymentGatewayInterface
{
    const INVOICE_URL = 'https://api.xendit.co/v2/invoices';

    public function get_id(): string
    {
        return 'xendit';
    }

    public function get_name(): string
    {
        return 'Xendit (Invoice - BCA, Mandiri, QRIS, E-Wallet)';
    }

    private function get_secret_key(): string
    {
        $settings = get_option('pm_settings', []);
        return trim($settings['xendit_secret_key'] ?? '');
    }

    private function get_webhook_token(): string
    {
        $settings = get_option('pm_settings', []);
        return trim($settings['xendit_webhook_token'] ?? '');
    }

    public function create_payment(object $transaction): array
    {
        $secret_key = $this->get_secret_key();
        if (empty($secret_key)) {
            return [
                'success' => false,
                'message' => 'Xendit Secret API Key belum diisi di Membership → Settings.',
            ];
        }

        $user = get_userdata((int) $transaction->user_id);
        $account_url = get_permalink((int) Helpers::get_setting('account_page_id', 0)) ?: home_url('/');

        $payload = [
            'external_id'      => $transaction->order_id,
            'amount'           => (int) $transaction->amount,
            'description'      => 'Langganan Premium Membership - ' . ($transaction->plan_duration ?? '') . ' Hari',
            'invoice_duration' => 86400, // 24 jam
            'customer'         => [
                'given_names' => $user ? $user->display_name : 'Pelanggan',
                'email'       => $user ? $user->user_email : '',
            ],
            'success_redirect_url' => add_query_arg(['pm_status' => 'success', 'order_id' => $transaction->order_id], $account_url),
            'failure_redirect_url' => add_query_arg(['pm_status' => 'failed', 'order_id' => $transaction->order_id], $account_url),
        ];

        $response = wp_remote_post(self::INVOICE_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::log('xendit_error', 'Koneksi ke Xendit gagal: ' . $response->get_error_message());
            return ['success' => false, 'message' => 'Gagal terhubung ke Xendit.'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['invoice_url'])) {
            Logger::log('xendit_session', 'Invoice Xendit dibuat: ' . $transaction->order_id, [
                'invoice_id' => $body['id'] ?? '',
            ]);
            return [
                'success'        => true,
                'payment_url'    => $body['invoice_url'],
                'gateway_txn_id' => $body['id'] ?? '',
                'message'        => 'Invoice Xendit berhasil dibuat.',
            ];
        }

        Logger::log('xendit_error', 'Xendit API error', ['body' => $body]);
        return [
            'success' => false,
            'message' => 'Xendit: ' . ($body['message'] ?? 'Unknown error'),
        ];
    }

    public function verify_webhook(\WP_REST_Request $request): array
    {
        $headers = $request->get_headers();
        $token_sent = $headers['x_callback_token'][0] ?? '';
        $expected_token = $this->get_webhook_token();

        if (!empty($expected_token) && !hash_equals($expected_token, $token_sent)) {
            return ['valid' => false, 'message' => 'Xendit Callback Token tidak valid.'];
        }

        $body = $request->get_json_params();
        if (empty($body)) {
            $body = $request->get_params();
        }

        $order_id   = sanitize_text_field($body['external_id'] ?? '');
        $status_raw = sanitize_text_field($body['status'] ?? '');
        $amount     = (int) ($body['amount'] ?? 0);
        $invoice_id = sanitize_text_field($body['id'] ?? '');

        if (empty($order_id)) {
            return ['valid' => false, 'message' => 'External ID tidak ditemukan.'];
        }

        $status = 'pending';
        if ($status_raw === 'PAID' || $status_raw === 'SETTLED') {
            $status = 'paid';
        } elseif ($status_raw === 'EXPIRED') {
            $status = 'expired';
        }

        return [
            'valid'          => true,
            'order_id'       => $order_id,
            'amount'         => $amount,
            'status'         => $status,
            'gateway_txn_id' => $invoice_id,
            'message'        => 'Webhook Xendit valid.',
        ];
    }
}
