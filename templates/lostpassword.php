<?php
/**
 * Custom Lost Password Form — Embedded inside WordPress page via [premium_login] or [premium_lostpassword].
 *
 * @package PremiumMembership
 * @var string $error    Error message if any.
 * @var string $success  Success message if any.
 */

use PremiumMembership\Frontend\Login;

defined('ABSPATH') || exit;

$login_url = Login::get_login_url();
?>
<style>
.pm-lost-wrap *, .pm-lost-wrap *::before, .pm-lost-wrap *::after { box-sizing: border-box; }
.pm-lost-wrap {
    max-width: 440px;
    margin: 30px auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    padding: 38px 34px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    border: 1px solid #f1f5f9;
}
.pm-lost-wrap .pm-lost-logo { text-align: center; margin-bottom: 20px; font-size: 20px; font-weight: 700; color: #1e293b; }
.pm-lost-wrap h2 { margin: 0 0 6px; font-size: 22px; color: #0f172a; text-align: center; font-weight: 700; }
.pm-lost-wrap .pm-lost-subtitle { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.5; }
.pm-lost-wrap .pm-form-group { margin-bottom: 20px; }
.pm-lost-wrap .pm-form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
.pm-lost-wrap .pm-form-group input[type="text"] {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; color: #0f172a; outline: none; background: #f8fafc;
    transition: all 0.2s ease;
}
.pm-lost-wrap .pm-form-group input:focus { border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,0.12); background: #fff; }
.pm-lost-wrap .pm-btn-submit {
    display: block; width: 100%; padding: 13px;
    background: #0284c7;
    color: #fff !important; border: none; border-radius: 8px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: background 0.2s ease;
}
.pm-lost-wrap .pm-btn-submit:hover { background: #0369a1; }
.pm-lost-wrap .pm-lost-links { margin-top: 22px; text-align: center; font-size: 13.5px; color: #64748b; }
.pm-lost-wrap .pm-lost-links a { color: #0284c7; text-decoration: none; font-weight: 600; }
.pm-lost-wrap .pm-lost-links a:hover { text-decoration: underline; }
.pm-lost-alert-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 14px; border-radius:8px; font-size:13.5px; margin-bottom:18px; line-height: 1.4; }
.pm-lost-alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:14px 16px; border-radius:8px; font-size:13.5px; margin-bottom:18px; line-height: 1.5; }
</style>

<div class="pm-lost-wrap">
    <div class="pm-lost-logo">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            echo wp_get_attachment_image($custom_logo_id, 'thumbnail', false, ['style' => 'max-height:48px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;']);
        }
        echo '<div>📰 ' . esc_html(get_bloginfo('name')) . '</div>';
        ?>
    </div>

    <h2><?php esc_html_e('Lupa Kata Sandi?', 'premium-membership'); ?></h2>
    <p class="pm-lost-subtitle">
        <?php esc_html_e('Masukkan nama pengguna atau alamat email Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi ke email Anda.', 'premium-membership'); ?>
    </p>

    <?php if (!empty($error)): ?>
        <div class="pm-lost-alert-error">⚠️ <?php echo wp_kses_post($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="pm-lost-alert-success">
            <strong>✅ <?php esc_html_e('Tautan Berhasil Dikirim!', 'premium-membership'); ?></strong><br>
            <?php echo esc_html($success); ?>
            <p style="margin:8px 0 0; font-size:12.5px; color:#166534;">
                <?php esc_html_e('Catatan: Jika email tidak ditemukan di Kotak Masuk, mohon periksa folder Spam atau Promosi.', 'premium-membership'); ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(Login::get_lostpassword_url()); ?>">
        <?php wp_nonce_field('pm_lostpassword_action', 'pm_lostpassword_nonce'); ?>
        <input type="hidden" name="pm_action" value="lostpassword">

        <div class="pm-form-group">
            <label for="pm_user_login"><?php esc_html_e('Nama Pengguna atau Alamat Email', 'premium-membership'); ?></label>
            <input type="text" id="pm_user_login" name="user_login" required autocomplete="username"
                   placeholder="<?php esc_attr_e('Contoh: budi123 atau budi@gmail.com', 'premium-membership'); ?>"
                   value="<?php echo esc_attr(isset($_GET['user_login']) ? urldecode($_GET['user_login']) : ''); ?>">
        </div>

        <button type="submit" class="pm-btn-submit">
            <?php esc_html_e('Dapatkan Sandi Baru', 'premium-membership'); ?> &rarr;
        </button>
    </form>

    <div class="pm-lost-links">
        <a href="<?php echo esc_url($login_url); ?>">← <?php esc_html_e('Kembali ke Halaman Masuk', 'premium-membership'); ?></a>
        <br><br>
        <a href="<?php echo esc_url(home_url('/')); ?>" style="color:#64748b; font-size:12.5px;"><?php esc_html_e('Kembali ke Beranda', 'premium-membership'); ?></a>
    </div>
</div>