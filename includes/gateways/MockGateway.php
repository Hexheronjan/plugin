<?php
/**
 * Mock / Sandbox Payment Gateway for local testing without real money.
 *
 * @package PremiumMembership\Gateways
 */

namespace PremiumMembership\Gateways;

use PremiumMembership\PaymentGatewayInterface;
use PremiumMembership\Helpers;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class MockGateway implements PaymentGatewayInterface
{
    /**
     * Get Gateway Identifier.
     */
    public function get_id(): string
    {
        return 'mock';
    }

    /**
     * Get Gateway Name.
     */
    public function get_name(): string
    {
        return 'Mock Sandbox Gateway (Simulator)';
    }

    /**
     * Get Secret Key used for HMAC hashing.
     */
    private function get_secret_key(): string
    {
        $settings = get_option('pm_settings', []);
        return $settings['mock_secret_key'] ?? 'pm_mock_secret_default_key';
    }

    /**
     * Generate HMAC Signature.
     *
     * @param string $order_id
     * @param int $amount
     * @param string $status
     * @return string
     */
    public function generate_signature(string $order_id, int $amount, string $status): string
    {
        $payload = "{$order_id}:{$amount}:{$status}";
        return hash_hmac('sha256', $payload, $this->get_secret_key());
    }

    /**
     * Create payment session.
     *
     * @param object $transaction
     * @return array
     */
    public function create_payment(object $transaction): array
    {
        $mock_txn_id = 'MOCK-' . strtoupper(wp_generate_password(10, false, false));
        $signature = $this->generate_signature($transaction->order_id, (int) $transaction->amount, 'paid');

        // Build interactive sandbox simulator payment URL
        $payment_url = add_query_arg(
            [
                'pm_action'  => 'mock_pay',
                'order_id'   => $transaction->order_id,
                'amount'     => $transaction->amount,
                'token'      => $signature,
                'gateway_id' => $this->get_id(),
            ],
            home_url('/')
        );

        Logger::log(
            'payment_request',
            "Payment session dibuat melalui Mock Gateway untuk Order ID {$transaction->order_id}",
            [
                'order_id'       => $transaction->order_id,
                'amount'         => $transaction->amount,
                'mock_txn_id'    => $mock_txn_id,
            ]
        );

        return [
            'success'        => true,
            'payment_url'    => $payment_url,
            'gateway_txn_id' => $mock_txn_id,
            'message'        => 'Mock payment session created.',
        ];
    }

    /**
     * Verify incoming Webhook request.
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

        $order_id       = sanitize_text_field($body['order_id'] ?? '');
        $amount         = (int) ($body['amount'] ?? 0);
        $status         = sanitize_text_field($body['status'] ?? 'paid');
        $signature      = sanitize_text_field($body['signature'] ?? '');
        $gateway_txn_id = sanitize_text_field($body['gateway_txn_id'] ?? ('MOCK-WH-' . wp_generate_password(8, false, false)));

        if (empty($order_id) || empty($signature)) {
            return [
                'valid'   => false,
                'message' => 'Parameter order_id atau signature tidak lengkap.',
            ];
        }

        // Validate HMAC Signature
        $expected_signature = $this->generate_signature($order_id, $amount, $status);
        if (!hash_equals($expected_signature, $signature)) {
            return [
                'valid'   => false,
                'message' => 'Signature webhook tidak valid.',
            ];
        }

        // Map status
        $mapped_status = 'pending';
        if ($status === 'paid' || $status === 'success' || $status === 'settlement' || $status === 'capture') {
            $mapped_status = 'paid';
        } elseif ($status === 'failed' || $status === 'deny') {
            $mapped_status = 'failed';
        } elseif ($status === 'expired') {
            $mapped_status = 'expired';
        }

        return [
            'valid'          => true,
            'order_id'       => $order_id,
            'amount'         => $amount,
            'status'         => $mapped_status,
            'gateway_txn_id' => $gateway_txn_id,
            'message'        => 'Webhook valid.',
        ];
    }
}
