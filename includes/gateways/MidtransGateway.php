<?php
/**
 * Midtrans SNAP Payment Gateway Integration.
 * Supports m-BCA, QRIS, DANA, OVO, GoPay, ShopeePay, etc.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class MidtransGateway implements PaymentGatewayInterface
{
    const SNAP_SANDBOX_URL    = 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    const SNAP_PRODUCTION_URL = 'https://app.midtrans.com/snap/v1/transactions';

    public function get_id(): string
    {
        return 'midtrans';
    }

    public function get_name(): string
    {
        return 'Midtrans (m-BCA, QRIS, DANA, OVO, GoPay)';
    }

    private function get_server_key(): string
    {
        $settings = get_option('pm_settings', []);
        return trim($settings['midtrans_server_key'] ?? '');
    }

    private function is_production(): bool
    {
        $settings = get_option('pm_settings', []);
        return ($settings['environment'] ?? 'sandbox') === 'production';
    }

    /**
     * Create Midtrans SNAP payment session.
     *
     * @param object $transaction
     * @return array
     */
    public function create_payment(object $transaction): array
    {
        $server_key = $this->get_server_key();
        $endpoint   = $this->is_production() ? self::SNAP_PRODUCTION_URL : self::SNAP_SANDBOX_URL;

        if (empty($server_key)) {
            return [
                'success' => false,
                'message' => 'Midtrans Server Key belum diisi di Membership → Settings.',
            ];
        }

        $user = get_userdata((int) $transaction->user_id);

        $payload = [
            'transaction_details' => [
                'order_id'     => $transaction->order_id,
                'gross_amount' => (int) $transaction->amount,
            ],
            'customer_details' => [
                'first_name' => $user ? $user->display_name : 'Pelanggan',
                'email'      => $user ? $user->user_email  : '',
            ],
            'item_details' => [[
                'id'       => 'plan_' . $transaction->plan_id,
                'price'    => (int) $transaction->amount,
                'quantity' => 1,
                'name'     => 'Premium Membership - ' . ($transaction->plan_duration ?? '') . ' Hari',
            ]],
            'callbacks' => [
                'finish' => add_query_arg(
                    ['pm_status' => 'finish', 'order_id' => $transaction->order_id],
                    get_permalink((int) Helpers::get_setting('account_page_id', 0)) ?: home_url('/')
                ),
            ],
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($server_key . ':'),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::log('midtrans_error', 'Koneksi ke Midtrans gagal: ' . $response->get_error_message());
            return ['success' => false, 'message' => 'Gagal terhubung ke Midtrans.'];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status === 201 && !empty($body['redirect_url'])) {
            Logger::log('midtrans_session', 'SNAP session dibuat: ' . $transaction->order_id, [
                'snap_token' => $body['token'] ?? '',
            ]);
            return [
                'success'        => true,
                'payment_url'    => $body['redirect_url'],
                'gateway_txn_id' => $body['token'] ?? '',
                'message'        => 'Midtrans SNAP session berhasil dibuat.',
            ];
        }

        Logger::log('midtrans_error', 'Midtrans API error', ['status' => $status, 'body' => $body]);
        return [
            'success' => false,
            'message' => 'Midtrans: ' . ($body['error_messages'][0] ?? 'Unknown error'),
        ];
    }

    /**
     * Verify Midtrans webhook notification.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function verify_webhook(\WP_REST_Request $request): array
    {
        $body = $request->get_json_params();
        if (empty($body)) {
            $body = $request->get_params();
        }

        $order_id     = sanitize_text_field($body['order_id']         ?? '');
        $status_code  = sanitize_text_field($body['status_code']      ?? '');
        $gross_amount = sanitize_text_field($body['gross_amount']     ?? '');
        $sig_key      = sanitize_text_field($body['signature_key']    ?? '');
        $txn_status   = sanitize_text_field($body['transaction_status'] ?? '');
        $fraud_status = sanitize_text_field($body['fraud_status']     ?? 'accept');
        $gateway_txn  = sanitize_text_field($body['transaction_id']   ?? '');

        if (empty($order_id) || empty($sig_key)) {
            return ['valid' => false, 'message' => 'Parameter tidak lengkap.'];
        }

        // Midtrans signature: SHA512(order_id + status_code + gross_amount + server_key)
        $server_key = $this->get_server_key();
        $expected   = hash('sha512', $order_id . $status_code . $gross_amount . $server_key);

        if (!hash_equals($expected, $sig_key)) {
            return ['valid' => false, 'message' => 'Signature Midtrans tidak valid.'];
        }

        // Map Midtrans status to internal status
        $status = 'pending';
        if (in_array($txn_status, ['capture', 'settlement'], true) && $fraud_status !== 'deny') {
            $status = 'paid';
        } elseif (in_array($txn_status, ['cancel', 'deny', 'expire'], true)) {
            $status = 'failed';
        } elseif ($txn_status === 'refund') {
            $status = 'refunded';
        }

        return [
            'valid'          => true,
            'order_id'       => $order_id,
            'amount'         => (int) str_replace('.00', '', $gross_amount),
            'status'         => $status,
            'gateway_txn_id' => $gateway_txn,
            'message'        => 'Webhook Midtrans valid.',
        ];
    }
}
