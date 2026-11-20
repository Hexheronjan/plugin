<?php
/**
 * Custom Reset Password Form — Embedded inside WordPress page when user clicks reset link from email.
 *
 * @package PremiumMembership
 * @var string $key       Reset key from email link.
 * @var string $login     User login from email link.
 * @var string $error     Error message if any.
 * @var bool   $key_valid Whether the reset key is valid.
 */

use PremiumMembership\Frontend\Login;

defined('ABSPATH') || exit;

$login_url = Login::get_login_url();
$lost_url  = Login::get_lostpassword_url();
?>
<style>
.pm-reset-wrap *, .pm-reset-wrap *::before, .pm-reset-wrap *::after { box-sizing: border-box; }
.pm-reset-wrap {
    max-width: 440px;
    margin: 30px auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    padding: 38px 34px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    border: 1px solid #f1f5f9;
}
.pm-reset-wrap .pm-reset-logo { text-align: center; margin-bottom: 20px; font-size: 20px; font-weight: 700; color: #1e293b; }
.pm-reset-wrap h2 { margin: 0 0 6px; font-size: 22px; color: #0f172a; text-align: center; font-weight: 700; }
.pm-reset-wrap .pm-reset-subtitle { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.5; }
.pm-reset-wrap .pm-form-group { margin-bottom: 18px; }
.pm-reset-wrap .pm-form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
.pm-reset-wrap .pm-form-group input[type="password"] {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; color: #0f172a; outline: none; background: #f8fafc;
    transition: all 0.2s ease;
}
.pm-reset-wrap .pm-form-group input:focus { border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,0.12); background: #fff; }
.pm-reset-wrap .pm-pwd-wrap { position: relative; }
.pm-reset-wrap .pm-pwd-wrap input { padding-right: 44px !important; }
.pm-reset-wrap .pm-toggle-pwd {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    cursor: pointer; color: #94a3b8; font-size: 16px;
    border: none; background: none; padding: 0; line-height: 1;
}
.pm-reset-wrap .pm-btn-submit {
    display: block; width: 100%; padding: 13px;
    background: #0284c7;
    color: #fff !important; border: none; border-radius: 8px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: background 0.2s ease;
}
.pm-reset-wrap .pm-btn-submit:hover { background: #0369a1; }
.pm-reset-wrap .pm-reset-links { margin-top: 22px; text-align: center; font-size: 13.5px; color: #64748b; }
.pm-reset-wrap .pm-reset-links a { color: #0284c7; text-decoration: none; font-weight: 600; }
.pm-reset-wrap .pm-reset-links a:hover { text-decoration: underline; }
.pm-reset-alert-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 14px; border-radius:8px; font-size:13.5px; margin-bottom:18px; line-height: 1.4; }
</style>

<div class="pm-reset-wrap">
    <div class="pm-reset-logo">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            echo wp_get_attachment_image($custom_logo_id, 'thumbnail', false, ['style' => 'max-height:48px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;']);
        }
        echo '<div>📰 ' . esc_html(get_bloginfo('name')) . '</div>';
        ?>
    </div>

    <h2><?php esc_html_e('Atur Sandi Baru', 'premium-membership'); ?></h2>
    <p class="pm-reset-subtitle">
        <?php esc_html_e('Silakan buat kata sandi baru untuk akun Anda.', 'premium-membership'); ?>
    </p>

    <?php if (!empty($error)): ?>
        <div class="pm-reset-alert-error">⚠️ <?php echo wp_kses_post($error); ?></div>
    <?php endif; ?>

    <?php if (!$key_valid): ?>
        <div class="pm-reset-alert-error">
            <strong><?php esc_html_e('Tautan Tidak Valid atau Kedaluwarsa', 'premium-membership'); ?></strong><br>
            <?php esc_html_e('Tautan pengaturan ulang sandi ini sudah tidak berlaku atau pernah digunakan sebelumnya.', 'premium-membership'); ?>
        </div>
        <div class="pm-reset-links">
            <a href="<?php echo esc_url($lost_url); ?>" class="pm-btn-submit" style="text-align:center; display:inline-block; text-decoration:none;">
                <?php esc_html_e('Minta Tautan Baru', 'premium-membership'); ?> &rarr;
            </a>
            <br><br>
            <a href="<?php echo esc_url($login_url); ?>">← <?php esc_html_e('Kembali ke Halaman Masuk', 'premium-membership'); ?></a>
        </div>
    <?php else: ?>
        <form method="post" action="<?php echo esc_url(Login::get_login_url()); ?>">
            <?php wp_nonce_field('pm_resetpass_action', 'pm_resetpass_nonce'); ?>
            <input type="hidden" name="pm_action" value="resetpass">
            <input type="hidden" name="rp_key" value="<?php echo esc_attr($key); ?>">
            <input type="hidden" name="rp_login" value="<?php echo esc_attr($login); ?>">

            <div class="pm-form-group">
                <label for="pm_pass1"><?php esc_html_e('Kata Sandi Baru (Minimal 6 karakter)', 'premium-membership'); ?></label>
                <div class="pm-pwd-wrap">
                    <input type="password" id="pm_pass1" name="pass1" required autocomplete="new-password"
                           placeholder="<?php esc_attr_e('Masukkan kata sandi baru...', 'premium-membership'); ?>">
                    <button type="button" class="pm-toggle-pwd" onclick="pmToggleResetPwd('pm_pass1', this)">👁</button>
                </div>
            </div>

            <div class="pm-form-group">
                <label for="pm_pass2"><?php esc_html_e('Ulangi Kata Sandi Baru', 'premium-membership'); ?></label>
                <div class="pm-pwd-wrap">
                    <input type="password" id="pm_pass2" name="pass2" required autocomplete="new-password"
                           placeholder="<?php esc_attr_e('Ulangi kata sandi baru...', 'premium-membership'); ?>">
                    <button type="button" class="pm-toggle-pwd" onclick="pmToggleResetPwd('pm_pass2', this)">👁</button>
                </div>
            </div>

            <button type="submit" class="pm-btn-submit">
                <?php esc_html_e('Simpan Sandi Baru', 'premium-membership'); ?> &rarr;
            </button>
        </form>

        <div class="pm-reset-links">
            <a href="<?php echo esc_url($login_url); ?>">← <?php esc_html_e('Kembali ke Halaman Masuk', 'premium-membership'); ?></a>
        </div>
    <?php endif; ?>
</div>

<script>
function pmToggleResetPwd(fieldId, btn) {
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