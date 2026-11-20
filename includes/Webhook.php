<?php
/**
 * REST API Webhook Handler for Payment Gateways.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Webhook
{
    /**
     * Register REST API routes.
     */
    public static function register_routes(): void
    {
        register_rest_route(
            'premium-membership/v1',
            '/webhook',
            [
                'methods'             => ['POST', 'GET'],
                'callback'            => [__CLASS__, 'handle_webhook'],
                'permission_callback' => '__return_true', // Gateway authentication via signature validation
            ]
        );
    }

    /**
     * Handle incoming webhook requests.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function handle_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $gateway_slug = sanitize_key($request->get_param('gateway') ?: Helpers::get_setting('active_gateway', 'mock'));
        $gateway = PaymentManager::get_gateway($gateway_slug) ?: PaymentManager::get_active_gateway();

        Logger::log(
            'webhook_received',
            "Webhook diterima dari gateway: {$gateway->get_name()}",
            [
                'gateway' => $gateway->get_id(),
                'params'  => $request->get_params(),
            ]
        );

        // 1. Verify signature and payload
        $verified = $gateway->verify_webhook($request);
        if (!$verified['valid']) {
            Logger::log('webhook_invalid', 'Webhook verifikasi gagal: ' . ($verified['message'] ?? 'Invalid signature'));
            return new \WP_REST_Response(
                [
                    'success' => false,
                    'message' => $verified['message'] ?? 'Verifikasi webhook gagal.',
                ],
                400
            );
        }

        $order_id = Security::sanitize_order_id($verified['order_id']);
        $amount = (int) $verified['amount'];
        $status = sanitize_text_field($verified['status']);
        $gateway_txn_id = sanitize_text_field($verified['gateway_txn_id'] ?? '');

        // 2. Fetch transaction from database
        $transaction = Transaction::get_by_order_id($order_id);
        if (!$transaction) {
            Logger::log('webhook_order_not_found', "Transaksi dengan Order ID {$order_id} tidak ditemukan.");
            return new \WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan.',
                ],
                404
            );
        }

        // 3. Amount validation (Anti-tampering)
        if ((int) $transaction->amount !== $amount) {
            Logger::log(
                'webhook_amount_mismatch',
                "Amount mismatch untuk Order ID {$order_id}. Di DB: {$transaction->amount}, Dari Gateway: {$amount}"
            );
            return new \WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Nominal pembayaran tidak sesuai.',
                ],
                400
            );
        }

        // 4. Idempotency handling
        if ($transaction->status === Transaction::STATUS_PAID && $status === Transaction::STATUS_PAID) {
            Logger::log('webhook_duplicate', "Webhook duplicate untuk Order ID {$order_id} (sudah paid).");
            return new \WP_REST_Response(
                [
                    'success' => true,
                    'message' => 'Transaksi sudah diproses sebelumnya.',
                ],
                200
            );
        }

        // 5. Process status update
        switch ($status) {
            case Transaction::STATUS_PAID:
                Transaction::mark_as_paid((int) $transaction->id, $gateway_txn_id);
                break;

            case Transaction::STATUS_FAILED:
                Transaction::mark_as_failed((int) $transaction->id, $verified['message'] ?? 'Payment failed at gateway');
                break;

            case Transaction::STATUS_EXPIRED:
                Transaction::mark_as_expired((int) $transaction->id);
                break;

            default:
                break;
        }

        return new \WP_REST_Response(
            [
                'success' => true,
                'message' => 'Webhook berhasil diproses.',
            ],
            200
        );
    }
}
