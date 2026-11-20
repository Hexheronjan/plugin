<?php
/**
 * Admin Plugin Settings, Payment Gateway Config, WhatsApp Gateway, and Page Mappings.
 *
 * @package PremiumMembership\Admin
 */

namespace PremiumMembership\Admin;

use PremiumMembership\Helpers;
use PremiumMembership\Security;
use PremiumMembership\PaymentManager;
use PremiumMembership\Logger;

defined('ABSPATH') || exit;

class Settings
{
    /**
     * Render Settings Screen.
     */
    public static function render(): void
    {
        Security::check_admin_permission('manage_options');

        $message = '';
        $settings = get_option('pm_settings', []);

        // Process Settings Form Save
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
            Security::verify_admin_referer(Security::NONCE_ACTION_ADMIN);

            $active_gateway   = sanitize_key($_POST['active_gateway'] ?? 'mock');
            $environment      = in_array($_POST['environment'] ?? '', ['sandbox', 'production'], true) ? $_POST['environment'] : 'sandbox';
            $currency_symbol  = sanitize_text_field($_POST['currency_symbol'] ?? 'Rp');
            $currency_code    = sanitize_text_field($_POST['currency_code'] ?? 'IDR');
            $checkout_page_id = absint($_POST['checkout_page_id'] ?? 0);
            $plans_page_id    = absint($_POST['plans_page_id'] ?? 0);
            $account_page_id  = absint($_POST['account_page_id'] ?? 0);
            $register_page_id = absint($_POST['register_page_id'] ?? 0);
            $login_page_id    = absint($_POST['login_page_id'] ?? 0);
            $delete_data      = !empty($_POST['delete_data_on_uninstall']) ? 1 : 0;
            $mock_secret_key  = sanitize_text_field($_POST['mock_secret_key'] ?? ($settings['mock_secret_key'] ?? ''));

            // WhatsApp Settings
            $enable_wa        = !empty($_POST['enable_wa_notification']) ? 1 : 0;
            $wa_api_token     = sanitize_text_field($_POST['wa_api_token'] ?? '');
            $wa_endpoint_url  = esc_url_raw($_POST['wa_endpoint_url'] ?? 'https://api.fonnte.com/send');
            $admin_wa_phone   = sanitize_text_field($_POST['admin_wa_phone'] ?? '');

            // Midtrans Settings
            $midtrans_server_key = sanitize_text_field($_POST['midtrans_server_key'] ?? ($settings['midtrans_server_key'] ?? ''));
            $midtrans_client_key = sanitize_text_field($_POST['midtrans_client_key'] ?? ($settings['midtrans_client_key'] ?? ''));

            // Xendit Settings
            $xendit_secret_key    = sanitize_text_field($_POST['xendit_secret_key'] ?? ($settings['xendit_secret_key'] ?? ''));
            $xendit_webhook_token = sanitize_text_field($_POST['xendit_webhook_token'] ?? ($settings['xendit_webhook_token'] ?? ''));

            // Tripay Settings
            $tripay_api_key       = sanitize_text_field($_POST['tripay_api_key'] ?? ($settings['tripay_api_key'] ?? ''));
            $tripay_private_key   = sanitize_text_field($_POST['tripay_private_key'] ?? ($settings['tripay_private_key'] ?? ''));
            $tripay_merchant_code = sanitize_text_field($_POST['tripay_merchant_code'] ?? ($settings['tripay_merchant_code'] ?? ''));

            // Duitku Settings
            $duitku_merchant_code = sanitize_text_field($_POST['duitku_merchant_code'] ?? ($settings['duitku_merchant_code'] ?? ''));
            $duitku_api_key       = sanitize_text_field($_POST['duitku_api_key']       ?? ($settings['duitku_api_key']       ?? ''));

            // iPaymu Settings
            $ipaymu_va            = sanitize_text_field($_POST['ipaymu_va'] ?? ($settings['ipaymu_va'] ?? ''));
            $ipaymu_api_key       = sanitize_text_field($_POST['ipaymu_api_key'] ?? ($settings['ipaymu_api_key'] ?? ''));

            // Universal / Custom Gateway Settings
            $universal_checkout_url = esc_url_raw($_POST['universal_checkout_url'] ?? ($settings['universal_checkout_url'] ?? ''));
            $universal_secret_token = sanitize_text_field($_POST['universal_secret_token'] ?? ($settings['universal_secret_token'] ?? ''));

            if (empty($mock_secret_key)) {
                $mock_secret_key = 'pm_mock_secret_' . wp_generate_password(16, false);
            }

            $updated_settings = [
                'active_gateway'           => $active_gateway,
                'environment'              => $environment,
                'currency'                 => $currency_code,
                'currency_symbol'          => $currency_symbol,
                'checkout_page_id'         => $checkout_page_id,
                'plans_page_id'            => $plans_page_id,
                'account_page_id'          => $account_page_id,
                'register_page_id'         => $register_page_id,
                'login_page_id'            => $login_page_id,
                'delete_data_on_uninstall' => $delete_data,
                'mock_secret_key'          => $mock_secret_key,
                'enable_wa_notification'   => $enable_wa,
                'wa_api_token'             => $wa_api_token,
                'wa_endpoint_url'          => $wa_endpoint_url,
                'admin_wa_phone'           => $admin_wa_phone,
                'midtrans_server_key'      => $midtrans_server_key,
                'midtrans_client_key'      => $midtrans_client_key,
                'xendit_secret_key'        => $xendit_secret_key,
                'xendit_webhook_token'     => $xendit_webhook_token,
                'tripay_api_key'           => $tripay_api_key,
                'tripay_private_key'       => $tripay_private_key,
                'tripay_merchant_code'     => $tripay_merchant_code,
                'duitku_merchant_code'     => $duitku_merchant_code,
                'duitku_api_key'           => $duitku_api_key,
                'ipaymu_va'                => $ipaymu_va,
                'ipaymu_api_key'           => $ipaymu_api_key,
                'universal_checkout_url'   => $universal_checkout_url,
                'universal_secret_token'   => $universal_secret_token,
            ];

            update_option('pm_settings', $updated_settings);
            $settings = $updated_settings;

            Logger::log('settings_updated', 'Pengaturan plugin diperbarui oleh Admin.');
            $message = __('Pengaturan berhasil disimpan.', 'premium-membership');
        }

        $available_gateways = PaymentManager::get_available_gateways();
        ?>
        <div class="wrap pm-admin-wrap">
            <h1 class="pm-admin-title">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('Pengaturan Premium Membership', 'premium-membership'); ?>
            </h1>

            <?php if (!empty($message)): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=pm-settings')); ?>">
                <?php wp_nonce_field(Security::NONCE_ACTION_ADMIN, '_pm_nonce'); ?>

                <!-- Payment Gateway & Environment -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3><?php esc_html_e('Payment Gateway & Lingkungan', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="active_gateway"><?php esc_html_e('Gateway Aktif', 'premium-membership'); ?></label></th>
                                <td>
                                    <select name="active_gateway" id="active_gateway">
                                        <?php foreach ($available_gateways as $slug => $name): ?>
                                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($settings['active_gateway'] ?? 'mock', $slug); ?>>
                                                <?php echo esc_html($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Pilih payment gateway yang digunakan untuk memproses transaksi.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="environment"><?php esc_html_e('Mode Environment', 'premium-membership'); ?></label></th>
                                <td>
                                    <select name="environment" id="environment">
                                        <option value="sandbox" <?php selected($settings['environment'] ?? 'sandbox', 'sandbox'); ?>><?php esc_html_e('Sandbox / Testing (Aman untuk simulasi)', 'premium-membership'); ?></option>
                                        <option value="production" <?php selected($settings['environment'] ?? '', 'production'); ?>><?php esc_html_e('Production / Live (Pembayaran Nyata)', 'premium-membership'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mock_secret_key"><?php esc_html_e('Mock Secret Key / Webhook Secret', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="mock_secret_key" id="mock_secret_key" type="text" class="regular-text code" value="<?php echo esc_attr($settings['mock_secret_key'] ?? ''); ?>" />
                                    <p class="description"><?php esc_html_e('Kunci rahasia untuk memvalidasi HMAC signature webhook pada sistem simulator.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><?php esc_html_e('Webhook URL (Callback)', 'premium-membership'); ?></label></th>
                                <td>
                                    <input type="text" readonly class="large-text code" value="<?php echo esc_attr(Helpers::get_webhook_url()); ?>" onclick="this.select();" />
                                    <p class="description"><?php esc_html_e('Salin URL ini ke webhook / notification settings di dashboard Payment Gateway Anda.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <!-- Midtrans Credentials -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>🏦 <?php esc_html_e('Midtrans API Credentials', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <p style="color:#646970; margin-top:0;"><?php esc_html_e('Isi jika Anda menggunakan Midtrans sebagai gateway aktif. Dapatkan key dari dashboard.midtrans.com → Settings → Access Keys.', 'premium-membership'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="midtrans_server_key"><?php esc_html_e('Server Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="midtrans_server_key" id="midtrans_server_key" type="password" class="regular-text code"
                                           value="<?php echo esc_attr($settings['midtrans_server_key'] ?? ''); ?>"
                                           placeholder="SB-Mid-server-xxxxxxxxxxxx atau Mid-server-xxxx" />
                                    <p class="description"><?php esc_html_e('Digunakan untuk otorisasi API dan verifikasi webhook. JANGAN dibagikan ke siapapun.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="midtrans_client_key"><?php esc_html_e('Client Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="midtrans_client_key" id="midtrans_client_key" type="text" class="regular-text code"
                                           value="<?php echo esc_attr($settings['midtrans_client_key'] ?? ''); ?>"
                                           placeholder="SB-Mid-client-xxxxxxxxxxxx atau Mid-client-xxxx" />
                                    <p class="description"><?php esc_html_e('Digunakan untuk inisialisasi SNAP.js di frontend.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Duitku Credentials -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>💳 <?php esc_html_e('Duitku API Credentials', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <p style="color:#646970; margin-top:0;"><?php esc_html_e('Isi jika Anda menggunakan Duitku sebagai gateway aktif. Dapatkan key dari dashboard.duitku.com → Project.', 'premium-membership'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="duitku_merchant_code"><?php esc_html_e('Merchant Code', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="duitku_merchant_code" id="duitku_merchant_code" type="text" class="regular-text code"
                                           value="<?php echo esc_attr($settings['duitku_merchant_code'] ?? ''); ?>"
                                           placeholder="Contoh: DS12345" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="duitku_api_key"><?php esc_html_e('API Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="duitku_api_key" id="duitku_api_key" type="password" class="regular-text code"
                                           value="<?php echo esc_attr($settings['duitku_api_key'] ?? ''); ?>"
                                           placeholder="API Key dari akun Duitku Anda" />
                                    <p class="description"><?php esc_html_e('JANGAN dibagikan ke siapapun.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                <!-- Xendit Credentials -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>⚡ <?php esc_html_e('Xendit API Credentials', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <p style="color:#646970; margin-top:0;"><?php esc_html_e('Isi jika Anda menggunakan Xendit. Ambil dari dashboard.xendit.co → Settings → API Keys & Webhooks.', 'premium-membership'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="xendit_secret_key"><?php esc_html_e('Secret API Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="xendit_secret_key" id="xendit_secret_key" type="password" class="regular-text code"
                                           value="<?php echo esc_attr($settings['xendit_secret_key'] ?? ''); ?>"
                                           placeholder="xnd_development_... atau xnd_production_..." />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="xendit_webhook_token"><?php esc_html_e('Verification Token / Callback Token', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="xendit_webhook_token" id="xendit_webhook_token" type="text" class="regular-text code"
                                           value="<?php echo esc_attr($settings['xendit_webhook_token'] ?? ''); ?>"
                                           placeholder="Token verifikasi webhook Xendit" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Tripay Credentials -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>🪙 <?php esc_html_e('Tripay API Credentials', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <p style="color:#646970; margin-top:0;"><?php esc_html_e('Isi jika Anda menggunakan Tripay. Ambil dari tripay.co.id → Merchant → Pengaturan API.', 'premium-membership'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="tripay_merchant_code"><?php esc_html_e('Merchant Code / Kode Merchant', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="tripay_merchant_code" id="tripay_merchant_code" type="text" class="regular-text code"
                                           value="<?php echo esc_attr($settings['tripay_merchant_code'] ?? ''); ?>"
                                           placeholder="Contoh: T12345" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tripay_api_key"><?php esc_html_e('API Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="tripay_api_key" id="tripay_api_key" type="password" class="regular-text code"
                                           value="<?php echo esc_attr($settings['tripay_api_key'] ?? ''); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tripay_private_key"><?php esc_html_e('Private Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="tripay_private_key" id="tripay_private_key" type="password" class="regular-text code"
                                           value="<?php echo esc_attr($settings['tripay_private_key'] ?? ''); ?>" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- iPaymu Credentials -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>🇮🇩 <?php esc_html_e('iPaymu API Credentials', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <p style="color:#646970; margin-top:0;"><?php esc_html_e('Isi jika menggunakan iPaymu. Ambil dari dashboard iPaymu → Integrasi / API Key.', 'premium-membership'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="ipaymu_va"><?php esc_html_e('Virtual Account (VA) Number', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="ipaymu_va" id="ipaymu_va" type="text" class="regular-text code"
                                           value="<?php echo esc_attr($settings['ipaymu_va'] ?? ''); ?>"
                                           placeholder="Nomor VA Akun iPaymu" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ipaymu_api_key"><?php esc_html_e('API Key', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="ipaymu_api_key" id="ipaymu_api_key" type="password" class="regular-text code"
                                           value="<?php echo esc_attr($settings['ipaymu_api_key'] ?? ''); ?>" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Universal / Custom Gateway -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>🌐 <?php esc_html_e('Universal / Custom Payment Gateway (Gateway Lain)', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <p style="color:#646970; margin-top:0;"><?php esc_html_e('Gunakan opsi ini jika klien Anda memakai gateway lain selain yang di atas (misal: Mayar, Faspay, Oy!, dll). Cukup arahkan webhook/callback mereka ke Webhook URL di atas.', 'premium-membership'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="universal_checkout_url"><?php esc_html_e('Custom Checkout URL (Opsional)', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="universal_checkout_url" id="universal_checkout_url" type="url" class="large-text code"
                                           value="<?php echo esc_attr($settings['universal_checkout_url'] ?? ''); ?>"
                                           placeholder="https://payment-provider.com/pay/" />
                                    <p class="description"><?php esc_html_e('Jika diisi, pembeli akan diarahkan ke URL ini beserta parameter ?order_id={id}&amount={nominal}', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="universal_secret_token"><?php esc_html_e('Secret / Auth Token Webhook (Opsional)', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="universal_secret_token" id="universal_secret_token" type="text" class="regular-text code"
                                           value="<?php echo esc_attr($settings['universal_secret_token'] ?? ''); ?>"
                                           placeholder="Token rahasia verifikasi webhook" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- WhatsApp Gateway Notification -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3>📱 <?php esc_html_e('WhatsApp Gateway Notification', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Aktifkan Notifikasi WA', 'premium-membership'); ?></th>
                                <td>
                                    <label for="enable_wa_notification">
                                        <input type="checkbox" name="enable_wa_notification" id="enable_wa_notification" value="1" <?php checked(!empty($settings['enable_wa_notification']), 1); ?> />
                                        <?php esc_html_e('Kirim pesan WhatsApp otomatis ketika ada transaksi baru dan konfirmasi pembayaran sukses.', 'premium-membership'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wa_endpoint_url"><?php esc_html_e('API Endpoint Gateway', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="wa_endpoint_url" id="wa_endpoint_url" type="url" class="regular-text code" value="<?php echo esc_attr($settings['wa_endpoint_url'] ?? 'https://api.fonnte.com/send'); ?>" />
                                    <p class="description"><?php esc_html_e('Endpoint API penyedia WA Gateway (misal: Fonnte, Wablas, Woowa, Starsender). Default: https://api.fonnte.com/send', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wa_api_token"><?php esc_html_e('API Token / Secret', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="wa_api_token" id="wa_api_token" type="password" class="regular-text code" value="<?php echo esc_attr($settings['wa_api_token'] ?? ''); ?>" placeholder="Masukkan API token..." />
                                    <p class="description"><?php esc_html_e('Token otorisasi dari akun WA Gateway Anda.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="admin_wa_phone"><?php esc_html_e('Nomor WhatsApp Admin', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="admin_wa_phone" id="admin_wa_phone" type="text" class="regular-text" value="<?php echo esc_attr($settings['admin_wa_phone'] ?? ''); ?>" placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx" />
                                    <p class="description"><?php esc_html_e('Nomor WA admin untuk menerima notifikasi rangkuman pesanan masuk.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Currency & Format -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3><?php esc_html_e('Mata Uang & Format', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="currency_symbol"><?php esc_html_e('Simbol Mata Uang', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="currency_symbol" id="currency_symbol" type="text" class="small-text" value="<?php echo esc_attr($settings['currency_symbol'] ?? 'Rp'); ?>" />
                                    <p class="description"><?php esc_html_e('Contoh: Rp atau $', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="currency_code"><?php esc_html_e('Kode Mata Uang', 'premium-membership'); ?></label></th>
                                <td>
                                    <input name="currency_code" id="currency_code" type="text" class="small-text" value="<?php echo esc_attr($settings['currency'] ?? 'IDR'); ?>" />
                                    <p class="description"><?php esc_html_e('Contoh: IDR atau USD', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Page Integration -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3><?php esc_html_e('Integrasi Halaman Frontend', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="plans_page_id"><?php esc_html_e('Halaman Daftar Paket', 'premium-membership'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'plans_page_id',
                                        'id'                => 'plans_page_id',
                                        'show_option_none'  => esc_html__('&mdash; Pilih Halaman (Berisi shortcode [premium_plans]) &mdash;', 'premium-membership'),
                                        'option_none_value' => '0',
                                        'selected'          => $settings['plans_page_id'] ?? 0,
                                    ]);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="checkout_page_id"><?php esc_html_e('Halaman Checkout', 'premium-membership'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'checkout_page_id',
                                        'id'                => 'checkout_page_id',
                                        'show_option_none'  => esc_html__('&mdash; Pilih Halaman (Berisi shortcode [premium_checkout]) &mdash;', 'premium-membership'),
                                        'option_none_value' => '0',
                                        'selected'          => $settings['checkout_page_id'] ?? 0,
                                    ]);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="account_page_id"><?php esc_html_e('Halaman Akun Member', 'premium-membership'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'account_page_id',
                                        'id'                => 'account_page_id',
                                        'show_option_none'  => esc_html__('&mdash; Pilih Halaman (Berisi shortcode [premium_account]) &mdash;', 'premium-membership'),
                                        'option_none_value' => '0',
                                        'selected'          => $settings['account_page_id'] ?? 0,
                                    ]);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="register_page_id"><?php esc_html_e('Halaman Pendaftaran (Register)', 'premium-membership'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'register_page_id',
                                        'id'                => 'register_page_id',
                                        'show_option_none'  => esc_html__('&mdash; Pilih Halaman (Berisi shortcode [premium_register]) &mdash;', 'premium-membership'),
                                        'option_none_value' => '0',
                                        'selected'          => $settings['register_page_id'] ?? 0,
                                    ]);
                                    ?>
                                    <p class="description"><?php esc_html_e('Halaman pendaftaran modern di frontend (menggunakan shortcode [premium_register]). Pengunjung tidak akan diarahkan ke wp-login default.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="login_page_id"><?php esc_html_e('Halaman Login', 'premium-membership'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'login_page_id',
                                        'id'                => 'login_page_id',
                                        'show_option_none'  => esc_html__('&mdash; Pilih Halaman (Berisi shortcode [premium_login]) &mdash;', 'premium-membership'),
                                        'option_none_value' => '0',
                                        'selected'          => $settings['login_page_id'] ?? 0,
                                    ]);
                                    ?>
                                    <p class="description"><?php esc_html_e('Halaman login khusus frontend (menggunakan shortcode [premium_login]). Pengunjung tidak akan diarahkan ke wp-login default saat klik login di artikel / paket.', 'premium-membership'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Security & Uninstall Data -->
                <div class="pm-card" style="margin-top: 20px; max-width: 850px;">
                    <div class="pm-card-header">
                        <h3><?php esc_html_e('Keamanan & Data Uninstall', 'premium-membership'); ?></h3>
                    </div>
                    <div class="pm-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Hapus Data saat Uninstall', 'premium-membership'); ?></th>
                                <td>
                                    <label for="delete_data_on_uninstall">
                                        <input type="checkbox" name="delete_data_on_uninstall" id="delete_data_on_uninstall" value="1" <?php checked(!empty($settings['delete_data_on_uninstall']), 1); ?> />
                                        <?php esc_html_e('Hapus semua tabel database plugin, post meta, dan options ketika plugin di-uninstall.', 'premium-membership'); ?>
                                    </label>
                                    <p class="description" style="color: #d63638;">
                                        <?php esc_html_e('Peringatan: Jika dicentang, seluruh riwayat transaksi dan status member akan dihapus permanen saat plugin dihapus.', 'premium-membership'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit" style="margin-top: 20px;">
                    <button type="submit" name="save_settings" class="button button-primary button-large">
                        <?php esc_html_e('Simpan Semua Pengaturan', 'premium-membership'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
}
