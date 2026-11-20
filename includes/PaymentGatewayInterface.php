<?php
/**
 * Contract for Payment Gateways.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

interface PaymentGatewayInterface
{
    /**
     * Get unique identifier slug for the gateway.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Get human-readable title.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Create payment request and return checkout URL or token.
     *
     * @param object $transaction
     * @return array ['success' => bool, 'payment_url' => string, 'gateway_txn_id' => string, 'message' => string]
     */
    public function create_payment(object $transaction): array;

    /**
     * Verify incoming webhook request from gateway.
     *
     * @param \WP_REST_Request $request
     * @return array ['valid' => bool, 'order_id' => string, 'amount' => int, 'status' => string, 'gateway_txn_id' => string, 'message' => string]
     */
    public function verify_webhook(\WP_REST_Request $request): array;
}
