<?php
/**
 * System Logger for tracking events safely without exposing secrets.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Logger
{
    /**
     * Sensitive keys that must be redacted from logs.
     */
    private static array $sensitive_keys = [
        'password',
        'secret',
        'api_key',
        'server_key',
        'private_key',
        'token',
        'auth',
        'card_number',
        'cvv',
        'otp',
    ];

    /**
     * Log an event into database.
     *
     * @param string $event_type (e.g. 'transaction_created', 'webhook_received', 'membership_activated')
     * @param string $message
     * @param array $context
     */
    public static function log(string $event_type, string $message, array $context = []): void
    {
        global $wpdb;

        $safe_context = self::mask_sensitive_data($context);
        $context_json = !empty($safe_context) ? wp_json_encode($safe_context) : null;

        $table = Database::logs_table();
        $wpdb->insert(
            $table,
            [
                'event_type' => sanitize_key($event_type),
                'message'    => sanitize_textarea_field($message),
                'context'    => $context_json,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Recursively mask sensitive keys in context data.
     *
     * @param array $data
     * @return array
     */
    public static function mask_sensitive_data(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $key_str = strtolower((string) $key);
            $is_sensitive = false;

            foreach (self::$sensitive_keys as $sens) {
                if (strpos($key_str, $sens) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::mask_sensitive_data($value);
            } else {
                $sanitized[$key] = is_scalar($value) ? sanitize_text_field((string) $value) : '';
            }
        }

        return $sanitized;
    }

    /**
     * Get logs with pagination and search.
     *
     * @param int $limit
     * @param int $offset
     * @param string $search
     * @param string $event_type
     * @return array
     */
    public static function get_logs(int $limit = 20, int $offset = 0, string $search = '', string $event_type = ''): array
    {
        global $wpdb;
        $table = Database::logs_table();

        $where = ['1=1'];
        $params = [];

        if (!empty($event_type)) {
            $where[] = 'event_type = %s';
            $params[] = $event_type;
        }

        if (!empty($search)) {
            $where[] = '(message LIKE %s OR event_type LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        $total = !empty($params) 
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, $params))
            : (int) $wpdb->get_var($count_query);

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params_with_limit = array_merge($params, [$limit, $offset]);

        $items = $wpdb->get_results($wpdb->prepare($query, $params_with_limit), ARRAY_A);

        return [
            'total' => $total,
            'items' => $items ?: [],
        ];
    }

    /**
     * Clear all logs.
     */
    public static function clear_logs(): void
    {
        global $wpdb;
        $table = Database::logs_table();
        $wpdb->query("TRUNCATE TABLE {$table}");
    }
}
