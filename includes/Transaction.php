<?php
/**
 * Transaction entity and order lifecycle management.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Transaction
{
    const STATUS_PENDING   = 'pending';
    const STATUS_PAID      = 'paid';
    const STATUS_FAILED    = 'failed';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED  = 'refunded';

    /**
     * Generate a cryptographically secure, unique Order ID.
     * Format: PM-YYYYMMDD-XXXXXXXX
     *
     * @return string
     */
    public static function generate_order_id(): string
    {
        $date_part = wp_date('Ymd');
        $random_part = strtoupper(wp_generate_password(8, false, false));
        return "PM-{$date_part}-{$random_part}";
    }

    /**
     * Create a new pending transaction securely from server database pricing.
     *
     * @param int $user_id
     * @param int $plan_id
     * @param string $gateway_slug
     * @return object|null Transaction object or null on failure
     */
    public static function create_transaction(int $user_id, int $plan_id, string $gateway_slug = 'mock'): ?object
    {
        global $wpdb;

        if ($user_id <= 0 || !get_userdata($user_id)) {
            return null;
        }

        // Fetch plan from database (never trust client amounts)
        $plans_table = Database::plans_table();
        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$plans_table} WHERE id = %d AND status = 'active' LIMIT 1",
                $plan_id
            )
        );

        if (!$plan) {
            return null;
        }

        $order_id = self::generate_order_id();
        $now = current_time('mysql');
        $expiry = date('Y-m-d H:i:s', strtotime($now) + DAY_IN_SECONDS); // 24 hours expiry for pending orders

        $table = Database::transactions_table();
        $inserted = $wpdb->insert(
            $table,
            [
                'user_id'         => $user_id,
                'plan_id'         => $plan->id,
                'order_id'        => $order_id,
                'payment_gateway' => sanitize_key($gateway_slug),
                'amount'          => (int) $plan->price,
                'status'          => self::STATUS_PENDING,
                'payment_url'     => null,
                'paid_at'         => null,
                'expired_at'      => $expiry,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return null;
        }

        $transaction_id = (int) $wpdb->insert_id;

        Logger::log(
            'transaction_created',
            "Transaksi baru dibuat: Order ID {$order_id}, Paket #{$plan->id} ({$plan->name}), Jumlah: " . Helpers::format_currency($plan->price),
            [
                'transaction_id' => $transaction_id,
                'order_id'       => $order_id,
                'user_id'        => $user_id,
                'plan_id'        => $plan->id,
                'amount'         => $plan->price,
                'gateway'        => $gateway_slug,
            ]
        );

        return self::get_transaction($transaction_id);
    }

    /**
     * Get transaction by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_transaction(int $id): ?object
    {
        global $wpdb;
        $table_tx = Database::transactions_table();
        $table_plans = Database::plans_table();

        $query = $wpdb->prepare(
            "SELECT tx.*, p.name as plan_name, p.duration as plan_duration 
             FROM {$table_tx} tx 
             LEFT JOIN {$table_plans} p ON tx.plan_id = p.id 
             WHERE tx.id = %d LIMIT 1",
            $id
        );

        return $wpdb->get_row($query);
    }

    /**
     * Get transaction by Order ID.
     *
     * @param string $order_id
     * @return object|null
     */
    public static function get_by_order_id(string $order_id): ?object
    {
        global $wpdb;
        $table_tx = Database::transactions_table();
        $table_plans = Database::plans_table();

        $query = $wpdb->prepare(
            "SELECT tx.*, p.name as plan_name, p.duration as plan_duration 
             FROM {$table_tx} tx 
             LEFT JOIN {$table_plans} p ON tx.plan_id = p.id 
             WHERE tx.order_id = %s LIMIT 1",
            Security::sanitize_order_id($order_id)
        );

        return $wpdb->get_row($query);
    }

    /**
     * Update payment URL for transaction.
     *
     * @param int $transaction_id
     * @param string $payment_url
     * @return bool
     */
    public static function update_payment_url(int $transaction_id, string $payment_url): bool
    {
        global $wpdb;
        $table = Database::transactions_table();

        return (bool) $wpdb->update(
            $table,
            [
                'payment_url' => esc_url_raw($payment_url),
                'updated_at'  => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Transition transaction to PAID and activate user membership.
     *
     * @param int $transaction_id
     * @param string|null $gateway_txn_id
     * @return bool
     */
    public static function mark_as_paid(int $transaction_id, ?string $gateway_txn_id = null): bool
    {
        global $wpdb;
        $transaction = self::get_transaction($transaction_id);

        if (!$transaction) {
            return false;
        }

        // Idempotency: Do not reactivate if already paid
        if ($transaction->status === self::STATUS_PAID) {
            return true;
        }

        $table = Database::transactions_table();
        $now = current_time('mysql');

        $update_data = [
            'status'     => self::STATUS_PAID,
            'paid_at'    => $now,
            'updated_at' => $now,
        ];
        $format = ['%s', '%s', '%s'];

        if ($gateway_txn_id !== null) {
            $update_data['gateway_transaction_id'] = sanitize_text_field($gateway_txn_id);
            $format[] = '%s';
        }

        $updated = $wpdb->update($table, $update_data, ['id' => $transaction_id], $format, ['%d']);

        if ($updated !== false) {
            // Activate membership
            Membership::activate_membership((int) $transaction->user_id, (int) $transaction->plan_id, (int) $transaction->plan_duration);

            // Send WhatsApp Notification if enabled
            WhatsApp::send_payment_success_notification($transaction);

            Logger::log(
                'payment_success',
                "Pembayaran sukses untuk Order ID {$transaction->order_id}. Membership diaktifkan.",
                [
                    'transaction_id' => $transaction_id,
                    'order_id'       => $transaction->order_id,
                    'user_id'        => $transaction->user_id,
                    'amount'         => $transaction->amount,
                    'gateway_txn_id' => $gateway_txn_id,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Mark transaction as failed.
     *
     * @param int $transaction_id
     * @param string $reason
     * @return bool
     */
    public static function mark_as_failed(int $transaction_id, string $reason = ''): bool
    {
        global $wpdb;
        $table = Database::transactions_table();

        $updated = $wpdb->update(
            $table,
            [
                'status'     => self::STATUS_FAILED,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            Logger::log('payment_failed', "Transaksi ID #{$transaction_id} gagal. Alasan: " . sanitize_text_field($reason));
            return true;
        }

        return false;
    }

    /**
     * Mark transaction as expired.
     *
     * @param int $transaction_id
     * @return bool
     */
    public static function mark_as_expired(int $transaction_id): bool
    {
        global $wpdb;
        $table = Database::transactions_table();

        return (bool) $wpdb->update(
            $table,
            [
                'status'     => self::STATUS_EXPIRED,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Mark transaction as refunded and immediately deactivate the user's membership.
     *
     * @param int $transaction_id
     * @return bool
     */
    public static function mark_as_refunded(int $transaction_id): bool
    {
        global $wpdb;
        $table = Database::transactions_table();

        // Fetch transaction first so we have user_id
        $transaction = self::get_transaction($transaction_id);
        if (!$transaction) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'     => self::STATUS_REFUNDED,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            // Immediately deactivate the user's active membership
            $mem_table = Database::memberships_table();
            $wpdb->update(
                $mem_table,
                [
                    'status'     => 'cancelled',
                    'updated_at' => current_time('mysql'),
                ],
                [
                    'user_id' => (int) $transaction->user_id,
                    'status'  => 'active',
                ],
                ['%s', '%s'],
                ['%d', '%s']
            );

            Logger::log(
                'payment_refunded',
                "Transaksi ID #{$transaction_id} (Order: {$transaction->order_id}) direfund. Membership user ID #{$transaction->user_id} dinonaktifkan otomatis.",
                [
                    'transaction_id' => $transaction_id,
                    'user_id'        => $transaction->user_id,
                    'order_id'       => $transaction->order_id,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Fetch user transactions with pagination.
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_user_transactions(int $user_id, int $limit = 20, int $offset = 0): array
    {
        global $wpdb;
        $table_tx = Database::transactions_table();
        $table_plans = Database::plans_table();

        $count_query = $wpdb->prepare("SELECT COUNT(*) FROM {$table_tx} WHERE user_id = %d", $user_id);
        $total = (int) $wpdb->get_var($count_query);

        $query = $wpdb->prepare(
            "SELECT tx.*, p.name as plan_name 
             FROM {$table_tx} tx 
             LEFT JOIN {$table_plans} p ON tx.plan_id = p.id 
             WHERE tx.user_id = %d 
             ORDER BY tx.id DESC 
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        );

        $items = $wpdb->get_results($query);

        return [
            'total' => $total,
            'items' => $items ?: [],
        ];
    }
}
