<?php
/**
 * Frontend Controller for managing shortcodes, scripts, and sandbox simulation actions.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Helpers;
use PremiumMembership\Transaction;
use PremiumMembership\PaymentManager;
use PremiumMembership\Gateways\MockGateway;

defined('ABSPATH') || exit;

class PublicController
{
    /**
     * Initialize Frontend hooks and shortcodes.
     */
    public static function init(): void
    {
        // Enqueue Assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Register Shortcodes
        add_shortcode('premium_plans', [PlansView::class, 'render']);
        add_shortcode('premium_checkout', [Checkout::class, 'render']);
        add_shortcode('premium_account', [Account::class, 'render']);

        // Handle Sandbox Mock Payment Simulation UI
        add_action('template_redirect', [__CLASS__, 'handle_mock_simulator']);
    }

    /**
     * Enqueue styles and scripts on frontend.
     */
    public static function enqueue_assets(): void
    {
        wp_enqueue_style(
            'pm-public-css',
            PM_PLUGIN_URL . 'assets/css/public.css',
            [],
            PM_VERSION
        );

        wp_enqueue_script(
            'pm-public-js',
            PM_PLUGIN_URL . 'assets/js/public.js',
            ['jquery'],
            PM_VERSION,
            true
        );

        wp_localize_script('pm-public-js', 'pm_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pm_frontend_action_nonce'),
        ]);
    }

    /**
     * Handle Mock Sandbox Simulator Interactive Payment Screen.
     */
    public static function handle_mock_simulator(): void
    {
        if (!isset($_GET['pm_action']) || $_GET['pm_action'] !== 'mock_pay') {
            return;
        }

        $order_id = sanitize_text_field($_GET['order_id'] ?? '');
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (empty($order_id)) {
            wp_die('Order ID tidak ditemukan.', 'Error', ['response' => 400]);
        }

        $transaction = Transaction::get_by_order_id($order_id);
        if (!$transaction) {
            wp_die('Transaksi tidak ditemukan.', 'Error', ['response' => 404]);
        }

        /** @var MockGateway $mock_gateway */
        $mock_gateway = PaymentManager::get_gateway('mock');
        $expected_token = $mock_gateway->generate_signature($transaction->order_id, (int) $transaction->amount, 'paid');

        // Handle Simulator Action POST (Simulate Pay, Simulate Fail, Simulate Expire)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_status'])) {
            $sim_status = sanitize_text_field($_POST['simulate_status']);
            $sim_signature = $mock_gateway->generate_signature($transaction->order_id, (int) $transaction->amount, $sim_status);

            // Execute local internal webhook call
            $webhook_url = Helpers::get_webhook_url();
            $body = [
                'order_id'       => $transaction->order_id,
                'amount'         => (int) $transaction->amount,
                'status'         => $sim_status,
                'signature'      => $sim_signature,
                'gateway_txn_id' => 'MOCK-' . time(),
            ];

            $response = wp_remote_post($webhook_url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]);

            // Redirect back to status confirmation page
            $account_page_id = (int) Helpers::get_setting('account_page_id', 0);
            $redirect_url = $account_page_id > 0 ? get_permalink($account_page_id) : home_url('/');
            $redirect_url = add_query_arg([
                'pm_status' => $sim_status,
                'order_id'  => $transaction->order_id,
            ], $redirect_url);

            wp_redirect($redirect_url);
            exit;
        }

        // Render Sandbox Simulator Screen
        Helpers::render_template('payment-status', [
            'transaction' => $transaction,
            'is_mock'     => true,
            'token'       => $token,
        ], true);
        exit;
    }
}
