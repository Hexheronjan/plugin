<?php
/**
 * Custom / Universal Webhook Payment Gateway.
 * Bisa dipakai untuk payment gateway APAPUN di dunia yang punya API webhook standar.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class UniversalGateway implements PaymentGatewayInterface
{
    public function get_id(): string
    {
        return 'universal';
    }

    public function get_name(): string
    {
        return 'Universal / Custom Gateway (Bebas untuk Gateway Lain)';
    }

    public function create_payment(object $transaction): array
    {
        $settings = get_option('pm_settings', []);
        $custom_checkout_url = trim($settings['universal_checkout_url'] ?? '');

        // Jika ada URL checkout custom dari gateway klien
        if (!empty($custom_checkout_url)) {
            $payment_url = add_query_arg([
                'order_id' => $transaction->order_id,
                'amount'   => $transaction->amount,
            ], $custom_checkout_url);
        } else {
            // Default ke halaman akun menunggu pembayaran/manual instruksi
            $payment_url = add_query_arg(
                ['order_id' => $transaction->order_id, 'pm_status' => 'pending'],
                get_permalink((int) Helpers::get_setting('account_page_id', 0)) ?: home_url('/')
            );
        }

        return [
            'success'        => true,
            'payment_url'    => $payment_url,
            'gateway_txn_id' => 'UNI-' . $transaction->order_id,
            'message'        => 'Universal payment session siap.',
        ];
    }

    public function verify_webhook(\WP_REST_Request $request): array
    {
        $settings = get_option('pm_settings', []);
        $secret   = trim($settings['universal_secret_token'] ?? '');

        // Cek secret token jika dikonfigurasi
        if (!empty($secret)) {
            $headers = $request->get_headers();
            $auth = $headers['authorization'][0] ?? $headers['x_callback_token'][0] ?? $headers['token'][0] ?? '';
            if (strpos($auth, $secret) === false && !hash_equals($secret, $auth)) {
                return ['valid' => false, 'message' => 'Token autentikasi tidak cocok.'];
            }
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $order_id = sanitize_text_field($params['order_id'] ?? $params['external_id'] ?? $params['merchant_ref'] ?? $params['reference_id'] ?? '');
        $status_raw = strtolower(sanitize_text_field($params['status'] ?? $params['transaction_status'] ?? 'paid'));
        $amount = (int) ($params['amount'] ?? $params['total_amount'] ?? $params['gross_amount'] ?? 0);
        $txn_id = sanitize_text_field($params['transaction_id'] ?? $params['id'] ?? $params['reference'] ?? 'UNI-OK');

        if (empty($order_id)) {
            return ['valid' => false, 'message' => 'Order ID tidak ditemukan di payload webhook.'];
        }

        $status = 'pending';
        if (in_array($status_raw, ['paid', 'success', 'settlement', 'capture', 'berhasil', 'completed', '200'], true)) {
            $status = 'paid';
        } elseif (in_array($status_raw, ['failed', 'deny', 'cancel', 'batal', 'expired', 'gagal'], true)) {
            $status = 'failed';
        } elseif (in_array($status_raw, ['refund', 'refunded'], true)) {
            $status = 'refunded';
        }

        return [
            'valid'          => true,
            'order_id'       => $order_id,
            'amount'         => $amount,
            'status'         => $status,
            'gateway_txn_id' => $txn_id,
            'message'        => 'Webhook Universal berhasil diproses.',
        ];
    }
}
