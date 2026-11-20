# Premium Membership WordPress Plugin

Plugin WordPress profesional untuk portal berita dengan sistem **Premium Membership** berbayar. Dibangun menggunakan PHP Native, WordPress API, `$wpdb`, `dbDelta()`, REST API, dan WP-Cron tanpa ketergantungan framework eksternal.

---

## 🌟 Fitur Utama

### Untuk Pengunjung & Member:
- **Artikel Gratis & Premium**: Pengunjung bebas membaca artikel gratis. Untuk artikel premium, konten utuh dipotong di server dan menampilkan paywall elegan.
- **Integrasi User WordPress**: Mendaftar dan login menggunakan akun WordPress standar (`wp_users`).
- **Pilihan Paket Fleksibel**: Memilih paket membership dengan durasi harian (misal: 7 hari, 30 hari, 1 tahun).
- **Pembayaran Otomatis**: Integrasi payment gateway dengan aktivasi otomatis via webhook tervalidasi HMAC signature.
- **Member Dashboard**: Melihat status membership, masa aktif, sisa hari, dan riwayat transaksi pribadi via shortcode `[premium_account]`.
- **Perpanjangan (Renewal) Cerdas**: Jika memperpanjang saat membership masih aktif, durasi otomatis ditambahkan ke tanggal kedaluwarsa lama (`old_expiry + duration`). Jika sudah expired, dihitung dari waktu sekarang.

### Untuk Admin:
- **Dashboard Statistik**: Memantau Total Member, Member Aktif, Expired, Total Transaksi, dan Total Pendapatan secara real-time.
- **Manajemen Paket (CRUD Plans)**: Menambah, mengubah harga/durasi, mengaktifkan/menonaktifkan paket.
- **Daftar Member**: Pencarian, filter status (Active, Expired, Cancelled), serta perpanjangan manual.
- **Manajemen Transaksi**: Filter status (Paid, Pending, Failed, Expired, Refunded), pencarian Order ID, dan aksi manual override.
- **Metabox Artikel Premium**: Menandai artikel premium langsung dari editor postingan WordPress.
- **Log Audit Sistem Aman**: Mencatat seluruh event penting (order dibuat, webhook diterima, membership aktif) dengan sensor/masking otomatis untuk data rahasia/kunci API.
- **Proteksi Server-Side Lengkap**: Konten artikel premium dipotong di server, aman dari inspeksi HTML, REST API `/wp-json/wp/v2/posts`, dan RSS feeds.
- **Payment Gateway Abstraction**: Dilengkapi Mock Sandbox Simulator untuk pengujian lokal instan tanpa uang sungguhan, serta siap diintegrasikan dengan Midtrans, Xendit, Tripay, atau Duitku.

---

## 📁 Struktur Plugin

```
premium-membership/
├── premium-membership.php            # File utama bootstrap plugin
├── uninstall.php                     # Pembersihan data aman saat uninstall
├── README.md                         # Dokumentasi lengkap
│
├── includes/
│   ├── Autoloader.php                # PSR-4 native autoloader
│   ├── Database.php                  # Skema database & dbDelta()
│   ├── Membership.php                # Logika status & renewal membership
│   ├── Transaction.php               # Lifecycle pesanan & status transaksi
│   ├── Logger.php                    # Audit log aman dengan masking rahasia
│   ├── Helpers.php                   # Format uang, tanggal, dan template loader
│   ├── Security.php                  # Nonce, capability & sanitasi
│   ├── Cron.php                      # WP-Cron pemrosesan status expired
│   ├── PaymentGatewayInterface.php   # Kontrak adapter payment gateway
│   ├── PaymentManager.php            # Registry & factory gateway
│   ├── Webhook.php                   # Endpoint REST API Webhook
│   └── gateways/
│       └── MockGateway.php           # Sandbox payment simulator
│
├── admin/
│   ├── Admin.php                     # Menu & asset admin
│   ├── Dashboard.php                 # Tampilan metrik dashboard
│   ├── Plans.php                     # CRUD paket membership
│   ├── Members.php                   # Daftar & manajemen member
│   ├── Transactions.php              # Daftar transaksi & filter
│   ├── Settings.php                  # Pengaturan gateway & opsi plugin
│   └── Logs.php                      # Tampilan log sistem
│
├── public/
│   ├── PublicController.php          # Frontend asset & simulator routing
│   ├── PlansView.php                 # Shortcode [premium_plans]
│   ├── Checkout.php                  # Shortcode [premium_checkout]
│   ├── Account.php                   # Shortcode [premium_account]
│   └── PremiumContent.php            # Paywall artikel & proteksi REST/RSS
│
├── templates/
│   ├── plans.php                     # Tampilan daftar harga paket
│   ├── checkout.php                  # Tampilan konfirmasi checkout
│   ├── account.php                   # Tampilan dashboard akun member
│   ├── premium-content.php           # Box paywall artikel premium
│   └── payment-status.php            # Tampilan simulasi sandbox mock payment
│
└── assets/
    ├── css/
    │   ├── admin.css                 # Styling halaman admin
    │   └── public.css                # Styling frontend responsif
    └── js/
        ├── admin.js                  # Interaksi & konfirmasi admin
        └── public.js                 # Skrip proteksi checkout frontend
```

---

## 🚀 Panduan Instalasi & Penggunaan

### 1. Instalasi Plugin
1. Kompres folder `premium-membership` menjadi `premium-membership.zip` (atau gunakan file ZIP yang telah disediakan).
2. Masuk ke **WordPress Admin** &rarr; **Plugins** &rarr; **Add New** &rarr; **Upload Plugin**.
3. Pilih file `premium-membership.zip` dan klik **Install Now**.
4. Klik **Activate Plugin**.

### 2. Membuat Halaman Frontend
Buat 3 halaman di menu **Pages** &rarr; **Add New**:
1. **Halaman Paket**: Beri judul "Paket Berlangganan" dan masukkan shortcode `[premium_plans]`.
2. **Halaman Checkout**: Beri judul "Checkout Membership" dan masukkan shortcode `[premium_checkout]`.
3. **Halaman Akun**: Beri judul "Akun Member" dan masukkan shortcode `[premium_account]`.

### 3. Konfigurasi Plugin
1. Masuk ke menu **Membership** &rarr; **Settings** di WordPress Admin.
2. Pada bagian **Integrasi Halaman Frontend**, pilih halaman yang baru saja Anda buat untuk masing-masing opsi.
3. Klik **Simpan Semua Pengaturan**.

### 4. Membuat Paket Membership
1. Masuk ke menu **Membership** &rarr; **Plans**.
2. Klik **Tambah Paket Baru**.
3. Masukkan nama paket (misal: "Paket Premium 30 Hari"), harga (misal: `25000`), dan durasi (`30` hari).
4. Klik **Buat Paket**.

### 5. Membuat Artikel Premium
1. Masuk ke **Posts** &rarr; **Add New** (atau edit post yang sudah ada).
2. Di panel sebelah kanan editor, cari metabox **Pengaturan Premium Membership**.
3. Centang opsi **Tandai sebagai Artikel Premium**.
4. Publikasikan postingan.

---

## 🧪 Pengujian Menggunakan Mock Sandbox Simulator

Plugin telah dilengkapi dengan gateway simulasi interaktif tanpa perlu menyambungkan kartu kredit atau akun bank:

1. Buka halaman **Paket Berlangganan** di frontend sebagai user biasa.
2. Klik tombol **Pilih Paket**.
3. Anda akan diarahkan ke halaman **Checkout**. Klik **Lanjut ke Pembayaran**.
4. Anda akan masuk ke layar **🧪 Payment Gateway Simulator**.
5. Klik tombol **Bayar Sekarang (Simulasikan Sukses / PAID)**.
6. Simulator akan otomatis mengirim webhook REST API tervalidasi HMAC ke `/wp-json/premium-membership/v1/webhook`.
7. Transaksi otomatis ditandai `PAID`, membership langsung `ACTIVE`, dan Anda langsung dapat membaca seluruh artikel premium!

---

## 🔒 Keamanan & Praktik Terbaik

- **Server-Side Content Truncation**: Full content artikel tidak pernah dirender ke HTML atau dikirim ke browser jika user belum aktif.
- **REST API & RSS Feed Isolation**: Endpoint `/wp-json/wp/v2/posts` dan RSS Feed difilter secara ketat agar tidak membocorkan konten eksklusif.
- **Anti-Tampering Pricing**: Harga selalu diambil dari database tabel plans server, bukan dari form input frontend.
- **HMAC Signature & Webhook Idempotency**: Webhook diverifikasi dengan signature rahasia dan aman dari request duplikat.
- **Secret Redaction**: API Key, token, dan kata sandi otomatis disensor saat dicatat ke log audit.
- **Prepared Queries**: Semua query database menggunakan `$wpdb->prepare()`.

---

## 📄 Lisensi & Pembuat
- **Plugin Name**: Premium Membership
- **Version**: 1.0.0
- **Author**: Antigravity
