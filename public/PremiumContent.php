<?php
/**
 * Premium Article Protection and Metabox Management.
 *
 * @package PremiumMembership\Frontend
 */

namespace PremiumMembership\Frontend;

use PremiumMembership\Membership;
use PremiumMembership\Helpers;

defined('ABSPATH') || exit;

class PremiumContent
{
    const META_KEY_PREMIUM = '_pm_is_premium';
    const NONCE_METABOX = 'pm_premium_metabox_nonce';

    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        // Admin Metabox in Post Editor
        add_action('add_meta_boxes', [__CLASS__, 'add_premium_metabox']);
        add_action('save_post', [__CLASS__, 'save_premium_metabox'], 10, 2);

        // Content & Feed Filtering
        add_filter('the_content', [__CLASS__, 'filter_post_content'], 99);
        add_filter('the_content_feed', [__CLASS__, 'filter_feed_content'], 99);
        add_filter('the_excerpt_rss', [__CLASS__, 'filter_feed_excerpt'], 99);

        // REST API Protection
        add_filter('rest_prepare_post', [__CLASS__, 'filter_rest_post_content'], 10, 3);
    }

    /**
     * Add Premium Article Metabox in WordPress Editor.
     */
    public static function add_premium_metabox(): void
    {
        add_meta_box(
            'pm_premium_post_meta',
            __('Pengaturan Premium Membership', 'premium-membership'),
            [__CLASS__, 'render_metabox_content'],
            'post',
            'side',
            'high'
        );
    }

    /**
     * Render Metabox Content.
     *
     * @param \WP_Post $post
     */
    public static function render_metabox_content(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_METABOX, self::NONCE_METABOX);
        $is_premium = get_post_meta($post->ID, self::META_KEY_PREMIUM, true);
        ?>
        <div class="pm-metabox-wrapper" style="padding: 6px 0;">
            <label for="pm_is_premium_checkbox" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                <input 
                    type="checkbox" 
                    id="pm_is_premium_checkbox" 
                    name="pm_is_premium" 
                    value="1" 
                    <?php checked($is_premium, '1'); ?>
                    style="margin: 0;"
                />
                <span><?php esc_html_e('Tandai sebagai Artikel Premium', 'premium-membership'); ?></span>
            </label>
            <p class="description" style="margin-top: 8px; font-size: 12px; color: #646970;">
                <?php esc_html_e('Jika dicentang, artikel lengkap hanya dapat dibaca oleh pengguna dengan status membership aktif.', 'premium-membership'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save Metabox value securely.
     *
     * @param int $post_id
     * @param \WP_Post $post
     */
    public static function save_premium_metabox(int $post_id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST[self::NONCE_METABOX]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_METABOX])), self::NONCE_METABOX)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $is_premium = !empty($_POST['pm_is_premium']) ? '1' : '0';
        update_post_meta($post_id, self::META_KEY_PREMIUM, $is_premium);
    }

    /**
     * Check if a specific post is marked as premium.
     *
     * @param int $post_id
     * @return bool
     */
    public static function is_premium_post(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_KEY_PREMIUM, true) === '1';
    }

    /**
     * Filter frontend post content and apply server-side paywall truncation.
     *
     * @param string $content
     * @return string
     */
    public static function filter_post_content(string $content): string
    {
        $post_id = get_the_ID();
        if (!$post_id || !self::is_premium_post($post_id)) {
            return $content; // Free article
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && Membership::has_active_membership($user_id)) {
            return $content; // User has active membership, display full content
        }

        $post = get_post($post_id);
        $excerpt = !empty($post->post_excerpt) 
            ? $post->post_excerpt 
            : wp_trim_words(wp_strip_all_tags($content), 30, '...');

        // If on archive / homepage list (non-singular)
        if (!is_singular('post')) {
            return '<p>' . esc_html($excerpt) . '</p><p><a href="' . esc_url(get_permalink($post_id)) . '" class="pm-badge pm-badge-active" style="text-decoration:none; padding: 6px 12px; font-size:12px;">🔒 ' . esc_html__('Lanjutkan Membaca (Artikel Premium) &rarr;', 'premium-membership') . '</a></p>';
        }

        // On single post view: show excerpt + paywall banner
        $preview_html = '<p class="pm-article-preview-text">' . esc_html($excerpt) . '</p>';

        $paywall_html = Helpers::render_template('premium-content', [
            'is_logged_in' => is_user_logged_in(),
            'login_url'    => \PremiumMembership\Frontend\Login::get_login_url(get_permalink($post_id)),
            'plans_url'    => self::get_plans_page_url(),
        ]);

        return $preview_html . $paywall_html;
    }

    /**
     * Protect RSS Feed content from leaking premium text.
     *
     * @param string $content
     * @return string
     */
    public static function filter_feed_content(string $content): string
    {
        $post_id = get_the_ID();
        if ($post_id && self::is_premium_post($post_id)) {
            $post = get_post($post_id);
            return '<p>' . esc_html(wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...')) . '</p><p><em>[' . esc_html__('Artikel Khusus Premium Membership', 'premium-membership') . ']</em></p>';
        }
        return $content;
    }

    /**
     * Protect RSS Feed excerpt.
     *
     * @param string $excerpt
     * @return string
     */
    public static function filter_feed_excerpt(string $excerpt): string
    {
        $post_id = get_the_ID();
        if ($post_id && self::is_premium_post($post_id)) {
            return esc_html($excerpt) . ' [' . esc_html__('Artikel Premium', 'premium-membership') . ']';
        }
        return $excerpt;
    }

    /**
     * Protect REST API (/wp-json/wp/v2/posts) from leaking full content.
     *
     * @param \WP_REST_Response $response
     * @param \WP_Post $post
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function filter_rest_post_content(\WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request): \WP_REST_Response
    {
        if (!self::is_premium_post($post->ID)) {
            return $response;
        }

        $user_id = get_current_user_id();
        $has_access = ($user_id > 0 && Membership::has_active_membership($user_id));

        $data = $response->get_data();

        // Add metadata to API response
        $data['is_premium'] = true;
        $data['has_access'] = $has_access;

        if (!$has_access) {
            $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 35, '...');
            
            $paywall_html = '<p>' . esc_html($excerpt) . '</p><div class="pm-rest-paywall-notice"><p>' . esc_html__('Artikel ini khusus untuk member Premium.', 'premium-membership') . '</p></div>';

            if (isset($data['content']['rendered'])) {
                $data['content']['rendered'] = $paywall_html;
            }
            if (isset($data['content']['raw'])) {
                unset($data['content']['raw']);
            }
        }

        $response->set_data($data);
        return $response;
    }

    /**
     * Helper to get plans page URL.
     *
     * @return string
     */
    public static function get_plans_page_url(): string
    {
        $page_id = (int) Helpers::get_setting('plans_page_id', 0);
        if ($page_id > 0 && get_post($page_id)) {
            return get_permalink($page_id);
        }

        // Fallback to home url with plans query
        return add_query_arg('pm_page', 'plans', home_url('/'));
    }
}
