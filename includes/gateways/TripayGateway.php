<?php
/**
 * Tripay Payment Gateway Integration (Closed Payment / Checkout).
 * Supports BCA, Mandiri, BRI, BNI, BSI, QRIS, ShopeePay, OVO, DANA, Alfamart, Indomaret.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class TripayGateway implements PaymentGatewayInterface
{
    const SANDBOX_URL    = 'https://tripay.co.id/api-sandbox/transaction/create';
    const PRODUCTION_URL = 'https://tripay.co.id/api/transaction/create';

    public function get_id(): string
    {
        return 'tripay';
    }

    public function get_name(): string
    {
        return 'Tripay (QRIS, VA Bank, E-Wallet, Retail)';
    }

    private function get_settings(): array
    {
        $settings = get_option('pm_settings', []);
        return [
            'api_key'      => trim($settings['tripay_api_key'] ?? ''),
            'private_key'  => trim($settings['tripay_private_key'] ?? ''),
            'merchant_code'=> trim($settings['tripay_merchant_code'] ?? ''),
            'is_prod'      => ($settings['environment'] ?? 'sandbox') === 'production',
        ];
    }

    public function create_payment(object $transaction): array
    {
        $config = $this->get_settings();
        if (empty($config['api_key']) || empty($config['private_key']) || empty($config['merchant_code'])) {
            return [
                'success' => false,
                'message' => 'Kredensial Tripay (API Key, Private Key, Merchant Code) belum lengkap di Settings.',
            ];
        }

        $endpoint = $config['is_prod'] ? self::PRODUCTION_URL : self::SANDBOX_URL;
        $amount   = (int) $transaction->amount;
        $order_id = $transaction->order_id;

        // Tripay Signature: HMAC-SHA256(merchant_code + merchant_ref + amount, private_key)
        $signature = hash_hmac('sha256', $config['merchant_code'] . $order_id . $amount, $config['private_key']);

        $user = get_userdata((int) $transaction->user_id);
        $account_url = get_permalink((int) Helpers::get_setting('account_page_id', 0)) ?: home_url('/');

        $payload = [
            'method'         => '', // Kosongkan agar pembeli bisa pilih metode bayar di halaman checkout Tripay
            'merchant_ref'   => $order_id,
            'amount'         => $amount,
            'customer_name'  => $user ? $user->display_name : 'Pelanggan',
            'customer_email' => $user ? $user->user_email : 'user@example.com',
            'order_items'    => [
                [
                    'name'     => 'Langganan Membership - ' . ($transaction->plan_duration ?? '') . ' Hari',
                    'price'    => $amount,
                    'quantity' => 1,
                ]
            ],
            'callback_url'   => Helpers::get_webhook_url(),
            'return_url'     => add_query_arg('order_id', $order_id, $account_url),
            'expired_time'   => time() + (24 * 3600), // 24 Jam
            'signature'      => $signature,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::log('tripay_error', 'Koneksi ke Tripay gagal: ' . $response->get_error_message());
            return ['success' => false, 'message' => 'Gagal terhubung ke Tripay.'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success']) && !empty($body['data']['checkout_url'])) {
            Logger::log('tripay_session', 'Transaksi Tripay dibuat: ' . $order_id, [
                'reference' => $body['data']['reference'] ?? '',
            ]);
            return [
                'success'        => true,
                'payment_url'    => $body['data']['checkout_url'],
                'gateway_txn_id' => $body['data']['reference'] ?? '',
                'message'        => 'Transaksi Tripay berhasil dibuat.',
            ];
        }

        Logger::log('tripay_error', 'Tripay API Error', ['body' => $body]);
        return [
            'success' => false,
            'message' => 'Tripay: ' . ($body['message'] ?? 'Gagal membuat transaksi.'),
        ];
    }

    public function verify_webhook(\WP_REST_Request $request): array
    {
        $config = $this->get_settings();
        $private_key = $config['private_key'];

        $headers = $request->get_headers();
        $callback_signature = $headers['x_callback_signature'][0] ?? '';

        $json = $request->get_body();
        if (empty($json)) {
            return ['valid' => false, 'message' => 'Payload kosong.'];
        }

        // Verify HMAC-SHA256 signature against raw body
        $expected = hash_hmac('sha256', $json, $private_key);
        if (!hash_equals($expected, $callback_signature)) {
            return ['valid' => false, 'message' => 'Signature Tripay tidak valid.'];
        }

        $data = json_decode($json, true);
        $order_id = sanitize_text_field($data['merchant_ref'] ?? '');
        $status_raw = sanitize_text_field($data['status'] ?? '');
        $amount = (int) ($data['total_amount'] ?? 0);
        $ref = sanitize_text_field($data['reference'] ?? '');

        $status = 'pending';
        if ($status_raw === 'PAID') {
            $status = 'paid';
        } elseif (in_array($status_raw, ['EXPIRED', 'FAILED'], true)) {
            $status = 'failed';
        } elseif ($status_raw === 'REFUND') {
            $status = 'refunded';
        }

        return [
            'valid'          => true,
            'order_id'       => $order_id,
            'amount'         => $amount,
            'status'         => $status,
            'gateway_txn_id' => $ref,
            'message'        => 'Webhook Tripay valid.',
        ];
    }
}
