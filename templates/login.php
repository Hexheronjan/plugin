<?php
/**
 * Custom Login Form — Embedded inside WordPress page via [premium_login] shortcode.
 *
 * @package PremiumMembership
 * @var string $redirect_to  URL to redirect after successful login.
 * @var string $error        Error message if any.
 * @var string $success      Success message if any.
 */

use PremiumMembership\Frontend\Register;
use PremiumMembership\Frontend\Login;

defined('ABSPATH') || exit;

$register_url = Register::get_register_url($redirect_to);
$lost_url     = Login::get_lostpassword_url($redirect_to);
?>
<style>
.pm-login-wrap *, .pm-login-wrap *::before, .pm-login-wrap *::after { box-sizing: border-box; }
.pm-login-wrap {
    max-width: 440px;
    margin: 30px auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    padding: 38px 34px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    border: 1px solid #f1f5f9;
}
.pm-login-wrap .pm-login-logo { text-align: center; margin-bottom: 20px; font-size: 20px; font-weight: 700; color: #1e293b; }
.pm-login-wrap h2 { margin: 0 0 6px; font-size: 22px; color: #0f172a; text-align: center; font-weight: 700; }
.pm-login-wrap .pm-login-subtitle { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.5; }
.pm-login-wrap .pm-form-group { margin-bottom: 18px; }
.pm-login-wrap .pm-form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
.pm-login-wrap .pm-form-group input[type="text"],
.pm-login-wrap .pm-form-group input[type="password"] {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; color: #0f172a; outline: none; background: #f8fafc;
    transition: all 0.2s ease;
}
.pm-login-wrap .pm-form-group input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); background: #fff; }
.pm-login-wrap .pm-pwd-wrap { position: relative; }
.pm-login-wrap .pm-pwd-wrap input { padding-right: 44px !important; }
.pm-login-wrap .pm-toggle-pwd {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    cursor: pointer; color: #94a3b8; font-size: 16px;
    border: none; background: none; padding: 0; line-height: 1;
}
.pm-login-wrap .pm-login-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; font-size: 13px;
}
.pm-login-wrap .pm-remember { display: flex; align-items: center; gap: 6px; color: #475569; cursor: pointer; }
.pm-login-wrap .pm-remember input { margin: 0; }
.pm-login-wrap .pm-lost-link { color: #2563eb; text-decoration: none; font-weight: 500; }
.pm-login-wrap .pm-lost-link:hover { text-decoration: underline; }
.pm-login-wrap .pm-btn-login {
    display: block; width: 100%; padding: 13px;
    background: #0284c7;
    color: #fff !important; border: none; border-radius: 8px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: background 0.2s ease;
}
.pm-login-wrap .pm-btn-login:hover { background: #0369a1; }
.pm-login-wrap .pm-login-links { margin-top: 22px; text-align: center; font-size: 13.5px; color: #64748b; }
.pm-login-wrap .pm-login-links a { color: #0284c7; text-decoration: none; font-weight: 600; }
.pm-login-wrap .pm-login-links a:hover { text-decoration: underline; }
.pm-login-alert-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:11px 14px; border-radius:8px; font-size:13.5px; margin-bottom:18px; }
</style>

<div class="pm-login-wrap">
    <div class="pm-login-logo">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            echo wp_get_attachment_image($custom_logo_id, 'thumbnail', false, ['style' => 'max-height:48px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;']);
        }
        echo '<div>📰 ' . esc_html(get_bloginfo('name')) . '</div>';
        ?>
    </div>

    <h2><?php esc_html_e('Masuk ke Akun Anda', 'premium-membership'); ?></h2>
    <p class="pm-login-subtitle"><?php esc_html_e('Silakan login untuk membaca artikel premium dan mengelola langganan.', 'premium-membership'); ?></p>

    <?php if (!empty($error)): ?>
        <div class="pm-login-alert-error">⚠️ <?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="pm-login-alert-success" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 14px; border-radius:8px; font-size:13.5px; margin-bottom:18px;">✅ <?php echo esc_html($success); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(get_permalink()); ?>">
        <?php wp_nonce_field('pm_login_action', 'pm_login_nonce'); ?>
        <input type="hidden" name="pm_action" value="login">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

        <div class="pm-form-group">
            <label for="pm_log_username"><?php esc_html_e('Nama Pengguna atau Email', 'premium-membership'); ?></label>
            <input type="text" id="pm_log_username" name="log_username" required autocomplete="username"
                   placeholder="<?php esc_attr_e('Masukkan username atau email...', 'premium-membership'); ?>"
                   value="<?php echo esc_attr(isset($_GET['log_username']) ? urldecode($_GET['log_username']) : ''); ?>">
        </div>

        <div class="pm-form-group">
            <label for="pm_log_password"><?php esc_html_e('Kata Sandi', 'premium-membership'); ?></label>
            <div class="pm-pwd-wrap">
                <input type="password" id="pm_log_password" name="log_password" required autocomplete="current-password"
                       placeholder="<?php esc_attr_e('Masukkan kata sandi...', 'premium-membership'); ?>">
                <button type="button" class="pm-toggle-pwd" onclick="pmToggleLoginPwd('pm_log_password', this)">👁</button>
            </div>
        </div>

        <div class="pm-login-row">
            <label class="pm-remember">
                <input type="checkbox" name="log_remember" value="1" checked>
                <span><?php esc_html_e('Ingat Saya', 'premium-membership'); ?></span>
            </label>
            <a href="<?php echo esc_url($lost_url); ?>" class="pm-lost-link"><?php esc_html_e('Lupa Sandi?', 'premium-membership'); ?></a>
        </div>

        <button type="submit" class="pm-btn-login">
            <?php esc_html_e('Masuk Sekarang', 'premium-membership'); ?> &rarr;
        </button>
    </form>

    <div class="pm-login-links">
        <?php esc_html_e('Belum punya akun?', 'premium-membership'); ?>
        <a href="<?php echo esc_url($register_url); ?>"><?php esc_html_e('Daftar di sini', 'premium-membership'); ?></a>
        <br><br>
        <a href="<?php echo esc_url(home_url('/')); ?>" style="color:#64748b; font-size:12.5px;"><?php esc_html_e('← Kembali ke Beranda', 'premium-membership'); ?></a>
    </div>
</div>

<script>
function pmToggleLoginPwd(fieldId, btn) {
    var input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁';
    }
}
</script>