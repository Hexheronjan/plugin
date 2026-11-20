<?php
/**
 * Duitku Payment Gateway Integration.
 * Supports VA Bank, QRIS, DANA, OVO, ShopeePay, Alfamart, etc.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class DuitkuGateway implements PaymentGatewayInterface
{
    const SANDBOX_URL    = 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry';
    const PRODUCTION_URL = 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry';

    public function get_id(): string
    {
        return 'duitku';
    }

    public function get_name(): string
    {
        return 'Duitku (VA Bank, QRIS, DANA, OVO, ShopeePay)';
    }

    private function get_credentials(): array
    {
        $settings = get_option('pm_settings', []);
        return [
            'merchant_code' => trim($settings['duitku_merchant_code'] ?? ''),
            'api_key'       => trim($settings['duitku_api_key']       ?? ''),
        ];
    }

    private function is_production(): bool
    {
        $settings = get_option('pm_settings', []);
        return ($settings['environment'] ?? 'sandbox') === 'production';
    }

    /**
     * Create Duitku payment session.
     *
     * @param object $transaction
     * @return array
     */
    public function create_payment(object $transaction): array
    {
        $creds         = $this->get_credentials();
        $merchant_code = $creds['merchant_code'];
        $api_key       = $creds['api_key'];
        $endpoint      = $this->is_production() ? self::PRODUCTION_URL : self::SANDBOX_URL;

        if (empty($merchant_code) || empty($api_key)) {
            return [
                'success' => false,
                'message' => 'Duitku Merchant Code / API Key belum diisi di Membership → Settings.',
            ];
        }

        $amount    = (int) $transaction->amount;
        $order_id  = $transaction->order_id;
        $signature = md5($merchant_code . $amount . $order_id . $api_key);
        $user      = get_userdata((int) $transaction->user_id);

        $account_url = get_permalink((int) Helpers::get_setting('account_page_id', 0)) ?: home_url('/');

        $payload = [
            'merchantCode'    => $merchant_code,
            'paymentAmount'   => $amount,
            'paymentMethod'   => 'VC',
            'merchantOrderId' => $order_id,
            'productDetails'  => 'Premium Membership - ' . ($transaction->plan_duration ?? '') . ' Hari',
            'customerVaName'  => $user ? $user->display_name : 'Pelanggan',
            'email'           => $user ? $user->user_email : '',
            'phoneNumber'     => '',
            'callbackUrl'     => Helpers::get_webhook_url(),
            'returnUrl'       => add_query_arg('order_id', $order_id, $account_url),
            'signature'       => $signature,
            'expiryPeriod'    => 1440,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::log('duitku_error', 'Koneksi ke Duitku gagal: ' . $response->get_error_message());
            return ['success' => false, 'message' => 'Gagal terhubung ke Duitku.'];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['paymentUrl'])) {
            Logger::log('duitku_session', 'Duitku session dibuat: ' . $order_id, [
                'reference' => $body['reference'] ?? '',
            ]);
            return [
                'success'        => true,
                'payment_url'    => $body['paymentUrl'],
                'gateway_txn_id' => $body['reference'] ?? '',
                'message'        => 'Duitku session berhasil dibuat.',
            ];
        }

        Logger::log('duitku_error', 'Duitku API error', ['status' => $status, 'body' => $body]);
        return [
            'success' => false,
            'message' => 'Duitku: ' . ($body['Message'] ?? 'Unknown error'),
        ];
    }

    /**
     * Verify Duitku webhook callback.
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

        $creds         = $this->get_credentials();
        $merchant_code = $creds['merchant_code'];
        $api_key       = $creds['api_key'];

        $merchant_order = sanitize_text_field($body['merchantOrderId'] ?? '');
        $amount         = sanitize_text_field($body['amount']          ?? '');
        $result_code    = sanitize_text_field($body['resultCode']      ?? '');
        $sig_param      = sanitize_text_field($body['signature']       ?? '');
        $reference      = sanitize_text_field($body['reference']       ?? '');

        if (empty($merchant_order) || empty($sig_param)) {
            return ['valid' => false, 'message' => 'Parameter tidak lengkap.'];
        }

        // Duitku signature: MD5(merchantCode + amount + merchantOrderId + resultCode + apiKey)
        $expected = md5($merchant_code . $amount . $merchant_order . $result_code . $api_key);

        if (!hash_equals($expected, $sig_param)) {
            return ['valid' => false, 'message' => 'Signature Duitku tidak valid.'];
        }

        // Map Duitku status
        $status = 'pending';
        if ($result_code === '00') {
            $status = 'paid';
        } elseif (in_array($result_code, ['01', '02'], true)) {
            $status = 'failed';
        }

        return [
            'valid'          => true,
            'order_id'       => $merchant_order,
            'amount'         => (int) $amount,
            'status'         => $status,
            'gateway_txn_id' => $reference,
            'message'        => 'Webhook Duitku valid.',
        ];
    }
}
