<?php
/**
 * Membership core logic and state management.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Membership
{
    /**
     * Check if a user has an active membership in real-time.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_active_membership(int $user_id): bool
    {
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return false;
        }

        // Administrators bypass check if configured or by default
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        global $wpdb;
        $table = Database::memberships_table();
        $now = current_time('mysql');

        $query = $wpdb->prepare(
            "SELECT id FROM {$table} 
             WHERE user_id = %d 
               AND status = 'active' 
               AND expired_at > %s 
             LIMIT 1",
            $user_id,
            $now
        );

        $result = $wpdb->get_var($query);
        return !empty($result);
    }

    /**
     * Get user's current membership record (most recent or active).
     *
     * @param int $user_id
     * @return object|null
     */
    public static function get_user_membership(int $user_id): ?object
    {
        if ($user_id <= 0) {
            return null;
        }

        global $wpdb;
        $table_memberships = Database::memberships_table();
        $table_plans = Database::plans_table();

        $query = $wpdb->prepare(
            "SELECT m.*, p.name as plan_name, p.price as plan_price, p.duration as plan_duration 
             FROM {$table_memberships} m 
             LEFT JOIN {$table_plans} p ON m.plan_id = p.id 
             WHERE m.user_id = %d 
             ORDER BY m.id DESC 
             LIMIT 1",
            $user_id
        );

        $membership = $wpdb->get_row($query);
        if (!$membership) {
            return null;
        }

        // Dynamic status check
        $now = current_time('mysql');
        if ($membership->status === 'active' && $membership->expired_at <= $now) {
            $membership->status = 'expired';
        }

        return $membership;
    }

    /**
     * Get membership record by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_membership(int $id): ?object
    {
        global $wpdb;
        $table = Database::memberships_table();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id)
        );
    }

    /**
     * Activate or renew a membership for a user based on plan duration.
     *
     * @param int $user_id
     * @param int $plan_id
     * @param int|null $custom_duration
     * @return int Membership record ID
     */
    public static function activate_membership(int $user_id, int $plan_id, ?int $custom_duration = null): int
    {
        global $wpdb;

        if ($user_id <= 0 || !get_userdata($user_id)) {
            return 0;
        }

        // Fetch plan duration if not provided
        $duration = $custom_duration;
        if ($duration === null || $duration <= 0) {
            $plan_table = Database::plans_table();
            $plan = $wpdb->get_row($wpdb->prepare("SELECT duration FROM {$plan_table} WHERE id = %d", $plan_id));
            $duration = $plan ? (int) $plan->duration : 30;
        }

        $now = current_time('mysql');
        $now_timestamp = current_datetime()->getTimestamp();

        // Check if user already has an active membership
        $current_membership = self::get_user_membership($user_id);
        $memberships_table = Database::memberships_table();

        $is_renewal_on_active = false;
        $started_at = $now;
        $expired_at = wp_date('Y-m-d H:i:s', $now_timestamp + ($duration * DAY_IN_SECONDS));

        $is_currently_active = false;
        $old_expiry_timestamp = 0;
        if ($current_membership && $current_membership->status === 'active') {
            $old_dt = date_create_from_format('Y-m-d H:i:s', $current_membership->expired_at, wp_timezone());
            $old_expiry_timestamp = $old_dt ? $old_dt->getTimestamp() : strtotime($current_membership->expired_at);
            if ($old_expiry_timestamp > $now_timestamp) {
                $is_currently_active = true;
            }
        }

        if ($is_currently_active) {
            // User extends active membership: new expiry = old expiry + duration
            $is_renewal_on_active = true;
            $expired_at = wp_date('Y-m-d H:i:s', $old_expiry_timestamp + ($duration * DAY_IN_SECONDS));
            $started_at = $current_membership->started_at;

            $wpdb->update(
                $memberships_table,
                [
                    'plan_id'    => $plan_id,
                    'status'     => 'active',
                    'expired_at' => $expired_at,
                    'updated_at' => $now,
                ],
                ['id' => $current_membership->id],
                ['%d', '%s', '%s', '%s'],
                ['%d']
            );

            $membership_id = (int) $current_membership->id;

            Logger::log(
                'membership_renewed',
                "Membership ID #{$membership_id} untuk User ID #{$user_id} diperpanjang sampai {$expired_at}.",
                [
                    'user_id'       => $user_id,
                    'plan_id'       => $plan_id,
                    'duration'      => $duration,
                    'new_expired_at'=> $expired_at,
                ]
            );
        } else {
            // New membership or renewal after expiration
            $wpdb->insert(
                $memberships_table,
                [
                    'user_id'    => $user_id,
                    'plan_id'    => $plan_id,
                    'status'     => 'active',
                    'started_at' => $started_at,
                    'expired_at' => $expired_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );

            $membership_id = (int) $wpdb->insert_id;

            Logger::log(
                'membership_activated',
                "Membership baru ID #{$membership_id} aktif untuk User ID #{$user_id} sampai {$expired_at}.",
                [
                    'user_id'    => $user_id,
                    'plan_id'    => $plan_id,
                    'duration'   => $duration,
                    'expired_at' => $expired_at,
                ]
            );
        }

        return $membership_id;
    }

    /**
     * Expire a membership record.
     *
     * @param int $id
     * @return bool
     */
    public static function expire_membership(int $id): bool
    {
        global $wpdb;
        $table = Database::memberships_table();

        $updated = $wpdb->update(
            $table,
            [
                'status'     => 'expired',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            Logger::log('membership_expired', "Membership ID #{$id} ditandai expired.");
            return true;
        }

        return false;
    }

    /**
     * Cancel a membership record.
     *
     * @param int $id
     * @return bool
     */
    public static function cancel_membership(int $id): bool
    {
        global $wpdb;
        $table = Database::memberships_table();

        $updated = $wpdb->update(
            $table,
            [
                'status'     => 'cancelled',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            Logger::log('membership_cancelled', "Membership ID #{$id} dibatalkan.");
            return true;
        }

        return false;
    }
}
