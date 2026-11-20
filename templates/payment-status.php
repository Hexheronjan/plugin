<?php
/**
 * Template: Sandbox Mock Payment Gateway Simulator Screen.
 *
 * @var object $transaction
 * @var bool $is_mock
 * @var string $token
 *
 * @package PremiumMembership
 */

use PremiumMembership\Helpers;

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Pilih Metode Pembayaran Digital', 'premium-membership'); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(PM_PLUGIN_URL . 'assets/css/public.css?ver=' . PM_VERSION); ?>" />
    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin: 0;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .pm-sim-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .pm-sim-header {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .pm-sim-header h2 {
            margin: 0 0 6px 0;
            font-size: 20px;
            font-weight: 700;
        }
        .pm-sim-header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .pm-sim-body {
            padding: 24px;
        }
        .pm-sim-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .pm-sim-total {
            font-size: 20px;
            font-weight: 800;
            color: #0284c7;
        }
        .pm-channel-title {
            font-size: 14px;
            font-weight: 700;
            margin: 20px 0 12px 0;
            color: #0f172a;
        }
        .pm-channel-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .pm-channel-item {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }
        .pm-channel-item:hover, .pm-channel-item.active {
            border-color: #0284c7;
            background: #f0f9ff;
        }
        .pm-channel-item input {
            margin: 0;
        }
        .pm-channel-item label {
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #1e293b;
        }
        .pm-sim-btn {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .pm-sim-btn-success {
            background-color: #16a34a;
            color: #ffffff;
        }
        .pm-sim-btn-success:hover {
            background-color: #15803d;
        }
        .pm-sim-footer {
            background: #f8fafc;
            padding: 14px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="pm-sim-card">
        <div class="pm-sim-header">
            <h2>💳 Pembayaran Digital</h2>
            <p>Pilih metode pembayaran untuk membuka akses berita eksklusif</p>
        </div>

        <div class="pm-sim-body">
            <div class="pm-sim-info-row">
                <span>Order ID:</span>
                <strong><?php echo esc_html($transaction->order_id); ?></strong>
            </div>
            <div class="pm-sim-info-row">
                <span>Paket Langganan:</span>
                <strong><?php echo esc_html($transaction->plan_name ?: 'Membership'); ?></strong>
            </div>
            <div class="pm-sim-info-row">
                <span>Total Tagihan:</span>
                <strong class="pm-sim-total"><?php echo Helpers::format_currency($transaction->amount); ?></strong>
            </div>

            <form method="post" action="" id="pm-pay-form">
                <div class="pm-channel-title">Pilih Saluran Pembayaran:</div>
                <div class="pm-channel-list">
                    <div class="pm-channel-item active" onclick="selectChannel('qris', this)">
                        <input type="radio" name="payment_channel" id="c_qris" value="qris" checked />
                        <label for="c_qris">📱 QRIS (Semua E-Wallet/Bank)</label>
                    </div>
                    <div class="pm-channel-item" onclick="selectChannel('bca', this)">
                        <input type="radio" name="payment_channel" id="c_bca" value="bca" />
                        <label for="c_bca">🏦 m-BCA / Virtual Account</label>
                    </div>
                    <div class="pm-channel-item" onclick="selectChannel('dana', this)">
                        <input type="radio" name="payment_channel" id="c_dana" value="dana" />
                        <label for="c_dana">💙 DANA</label>
                    </div>
                    <div class="pm-channel-item" onclick="selectChannel('ovo', this)">
                        <input type="radio" name="payment_channel" id="c_ovo" value="ovo" />
                        <label for="c_ovo">💜 OVO</label>
                    </div>
                </div>

                <button type="submit" name="simulate_status" value="paid" class="pm-sim-btn pm-sim-btn-success">
                    ✓ Bayar Sekarang & Aktifkan Langganan
                </button>
            </form>
        </div>

        <div class="pm-sim-footer">
            🔒 Pembayaran digital aman tervalidasi otomatis via Webhook REST API.
        </div>
    </div>

    <script>
        function selectChannel(val, elem) {
            document.querySelectorAll('.pm-channel-item').forEach(function(el) {
                el.classList.remove('active');
            });
            elem.classList.add('active');
            elem.querySelector('input').checked = true;
        }
    </script>
</body>
</html>
