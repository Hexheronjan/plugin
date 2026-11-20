<?php
/**
 * iPaymu Payment Gateway Integration (API v2).
 * Supports Virtual Account, QRIS, Indomaret/Alfamart, Paylater, COD.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class IPaymuGateway implements PaymentGatewayInterface
{
    const SANDBOX_URL    = 'https://sandbox.ipaymu.com/api/v2/payment';
    const PRODUCTION_URL = 'https://my.ipaymu.com/api/v2/payment';

    public function get_id(): string
    {
        return 'ipaymu';
    }

    public function get_name(): string
    {
        return 'iPaymu (VA Bank, QRIS, E-Wallet, Indomaret/Alfamart)';
    }

    private function get_settings(): array
    {
        $settings = get_option('pm_settings', []);
        return [
            'va'      => trim($settings['ipaymu_va'] ?? ''),
            'api_key' => trim($settings['ipaymu_api_key'] ?? ''),
            'is_prod' => ($settings['environment'] ?? 'sandbox') === 'production',
        ];
    }

    public function create_payment(object $transaction): array
    {
        $config = $this->get_settings();
        if (empty($config['va']) || empty($config['api_key'])) {
            return [
                'success' => false,
                'message' => 'Virtual Account (VA) dan API Key iPaymu belum diisi di Settings.',
            ];
        }

        $endpoint = $config['is_prod'] ? self::PRODUCTION_URL : self::SANDBOX_URL;
        $amount   = (int) $transaction->amount;
        $order_id = $transaction->order_id;
        $user     = get_userdata((int) $transaction->user_id);
        $account_url = get_permalink((int) Helpers::get_setting('account_page_id', 0)) ?: home_url('/');

        $body = [
            'name'        => $user ? $user->display_name : 'Pelanggan',
            'phone'       => '081234567890',
            'email'       => $user ? $user->user_email : 'member@example.com',
            'amount'      => $amount,
            'notifyUrl'   => Helpers::get_webhook_url(),
            'ureturn'     => add_query_arg('order_id', $order_id, $account_url),
            'unotify'     => Helpers::get_webhook_url(),
            'ucancel'     => $account_url,
            'referenceId' => $order_id,
            'product'     => ['Langganan Membership'],
            'qty'         => [1],
            'price'       => [$amount],
        ];

        $json_body = json_encode($body);
        $string_to_sign = "POST:" . $config['va'] . ":" . strtolower(hash('sha256', $json_body)) . ":" . $config['api_key'];
        $signature = hash_hmac('sha256', $string_to_sign, $config['api_key']);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'va'           => $config['va'],
                'signature'    => $signature,
                'timestamp'    => date('YmdHis'),
            ],
            'body'    => $json_body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::log('ipaymu_error', 'Koneksi ke iPaymu gagal: ' . $response->get_error_message());
            return ['success' => false, 'message' => 'Gagal terhubung ke iPaymu.'];
        }

        $res_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($res_data['Data']['Url'])) {
            Logger::log('ipaymu_session', 'Transaksi iPaymu dibuat: ' . $order_id, [
                'session_id' => $res_data['Data']['SessionId'] ?? '',
            ]);
            return [
                'success'        => true,
                'payment_url'    => $res_data['Data']['Url'],
                'gateway_txn_id' => (string)($res_data['Data']['SessionId'] ?? ''),
                'message'        => 'Payment link iPaymu berhasil dibuat.',
            ];
        }

        Logger::log('ipaymu_error', 'iPaymu API Error', ['body' => $res_data]);
        return [
            'success' => false,
            'message' => 'iPaymu: ' . ($res_data['Message'] ?? 'Gagal membuat sesi pembayaran.'),
        ];
    }

    public function verify_webhook(\WP_REST_Request $request): array
    {
        $params = $request->get_params();

        $order_id   = sanitize_text_field($params['reference_id'] ?? $params['sid'] ?? '');
        $status_raw = sanitize_text_field($params['status'] ?? '');
        $amount     = (int) ($params['total'] ?? 0);
        $trx_id     = sanitize_text_field($params['trx_id'] ?? '');

        if (empty($order_id)) {
            return ['valid' => false, 'message' => 'Order reference_id tidak ditemukan.'];
        }

        $status = 'pending';
        if (strtolower($status_raw) === 'berhasil') {
            $status = 'paid';
        } elseif (in_array(strtolower($status_raw), ['expired', 'gagal', 'batal'], true)) {
            $status = 'failed';
        }

        return [
            'valid'          => true,
            'order_id'       => $order_id,
            'amount'         => $amount,
            'status'         => $status,
            'gateway_txn_id' => $trx_id,
            'message'        => 'Webhook iPaymu diproses.',
        ];
    }
}
