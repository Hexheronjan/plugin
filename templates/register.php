<?php
/**
 * Custom Registration Form — Embedded inside WordPress page via [premium_register] shortcode.
 *
 * @package PremiumMembership
 * @var string $redirect_to  URL to redirect after successful registration.
 * @var string $error        Error message if any.
 * @var string $success      Success message if any.
 */

defined('ABSPATH') || exit;
?>
<style>
.pm-reg-wrap *, .pm-reg-wrap *::before, .pm-reg-wrap *::after { box-sizing: border-box; }
.pm-reg-wrap {
    max-width: 460px;
    margin: 30px auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.10);
    padding: 38px 36px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.pm-reg-wrap .pm-reg-logo { text-align: center; margin-bottom: 20px; font-size: 20px; font-weight: 700; color: #1e293b; }
.pm-reg-wrap h2 { margin: 0 0 4px; font-size: 20px; color: #1e293b; text-align: center; }
.pm-reg-wrap .pm-reg-subtitle { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 24px; }
.pm-reg-wrap .pm-form-group { margin-bottom: 16px; }
.pm-reg-wrap .pm-form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.pm-reg-wrap .pm-form-group input {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; color: #1e293b; outline: none; background: #f8fafc;
    transition: border-color 0.2s;
}
.pm-reg-wrap .pm-form-group input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); background: #fff; }
.pm-reg-wrap .pm-hint { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.pm-reg-wrap .pm-btn-register {
    display: block; width: 100%; padding: 13px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff !important; border: none; border-radius: 8px;
    font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 10px;
    transition: opacity 0.2s;
}
.pm-reg-wrap .pm-btn-register:hover { opacity: 0.87; }
.pm-reg-wrap .pm-reg-links { margin-top: 20px; text-align: center; font-size: 13px; color: #64748b; }
.pm-reg-wrap .pm-reg-links a { color: #6366f1; text-decoration: none; font-weight: 600; }
.pm-reg-alert-error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }
.pm-reg-alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }
.pm-pwd-wrap { position: relative; }
.pm-pwd-wrap input { padding-right: 44px !important; }
.pm-toggle-pwd {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    cursor: pointer; color: #94a3b8; font-size: 16px;
    border: none; background: none; padding: 0; line-height: 1;
}
</style>

<div class="pm-reg-wrap">
    <div class="pm-reg-logo">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            echo wp_get_attachment_image($custom_logo_id, 'thumbnail', false, ['style' => 'max-height:48px;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;']);
        }
        echo '<div>📰 ' . esc_html(get_bloginfo('name')) . '</div>';
        ?>
    </div>

    <h2><?php esc_html_e('Daftar Akun Baru', 'premium-membership'); ?></h2>
    <p class="pm-reg-subtitle"><?php esc_html_e('Buat akun untuk mengakses berita premium eksklusif.', 'premium-membership'); ?></p>

    <?php if (!empty($error)): ?>
        <div class="pm-reg-alert-error">⚠️ <?php echo esc_html($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="pm-reg-alert-success">✅ <?php echo esc_html($success); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(get_permalink()); ?>">
        <?php wp_nonce_field('pm_register_action', 'pm_register_nonce'); ?>
        <input type="hidden" name="pm_action" value="register">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

        <div class="pm-form-group">
            <label for="pm_reg_username"><?php esc_html_e('Nama Pengguna (Username)', 'premium-membership'); ?></label>
            <input type="text" id="pm_reg_username" name="reg_username" required autocomplete="username"
                   placeholder="Contoh: budi123"
                   value="<?php echo esc_attr(isset($_GET['reg_username']) ? urldecode($_GET['reg_username']) : ''); ?>">
            <div class="pm-hint"><?php esc_html_e('Hanya huruf, angka, dan garis bawah.', 'premium-membership'); ?></div>
        </div>

        <div class="pm-form-group">
            <label for="pm_reg_email"><?php esc_html_e('Alamat Email', 'premium-membership'); ?></label>
            <input type="email" id="pm_reg_email" name="reg_email" required autocomplete="email"
                   placeholder="email@contoh.com"
                   value="<?php echo esc_attr(isset($_GET['reg_email']) ? urldecode($_GET['reg_email']) : ''); ?>">
        </div>

        <div class="pm-form-group">
            <label for="pm_reg_password"><?php esc_html_e('Kata Sandi', 'premium-membership'); ?></label>
            <div class="pm-pwd-wrap">
                <input type="password" id="pm_reg_password" name="reg_password" required autocomplete="new-password"
                       placeholder="Minimal 8 karakter">
                <button type="button" class="pm-toggle-pwd" onclick="pmTogglePwd('pm_reg_password', this)">👁</button>
            </div>
        </div>

        <div class="pm-form-group">
            <label for="pm_reg_password2"><?php esc_html_e('Konfirmasi Kata Sandi', 'premium-membership'); ?></label>
            <div class="pm-pwd-wrap">
                <input type="password" id="pm_reg_password2" name="reg_password2" required autocomplete="new-password"
                       placeholder="Ulangi kata sandi">
                <button type="button" class="pm-toggle-pwd" onclick="pmTogglePwd('pm_reg_password2', this)">👁</button>
            </div>
        </div>

        <button type="submit" class="pm-btn-register">
            <?php esc_html_e('Buat Akun Sekarang', 'premium-membership'); ?> &rarr;
        </button>
    </form>

    <div class="pm-reg-links">
        <?php esc_html_e('Sudah punya akun?', 'premium-membership'); ?>
        <a href="<?php echo esc_url(wp_login_url($redirect_to)); ?>"><?php esc_html_e('Login di sini', 'premium-membership'); ?></a>
        &nbsp;|&nbsp;
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('← Beranda', 'premium-membership'); ?></a>
    </div>
</div>

<script>
function pmTogglePwd(fieldId, btn) {
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
