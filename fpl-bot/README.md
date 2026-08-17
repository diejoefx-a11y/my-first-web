# ⚽ FPL-BOT: Smart Assistant & Automated Decision Engine

Aplikasi Web Assistant dan Bot Otomatisasi untuk Fantasy Premier League (FPL) berbasis **100% Native PHP + MySQL + Vanilla JS/CSS** yang dioptimalkan untuk **Hosting Niagahoster (cPanel / Cloud / VPS)** dan **XAMPP Lokal**.

---

## 📌 Ringkasan Fitur Utama

1. **Dashboard Manajemen Tim (Web-Based):**
   * Visualisasi susunan pemain di formasi lapangan hijau (*Pitch View*) & bangku cadangan (*Bench*).
   * Informasi live *budget bank*, kuota *Free Transfer (FT)*, dan status hitung mundur *Gameweek Deadline*.
   * Tombol eksekusi manual 1-klik (*1-Click Apply to FPL*).

2. **Smart Fallback Mechanism (Jaring Pengaman Deadline H-30 Menit):**
   * Memberikan kebebasan review manual bagi manajer.
   * **Auto-Execution:** Jika user tidak melakukan eksekusi manual hingga **30 menit sebelum deadline GW ditutup**, bot otomatis mengeksekusi rekomendasi transfer & set Kapten (C) / Wakil Kapten (VC) terbaik.
   * **Proteksi Keamanan:** Hanya menggunakan Free Transfer yang tersedia (*max 0 hit penalty*) dan tidak mengaktifkan chip (*Wildcard, Free Hit, TC, BB*) secara otomatis.

3. **Modular Multi-Parameter Scoring Engine:**
   * **Form & Underlying Stats:** Produktivitas $xG$, $xA$, $xGI$, dan $Form$ pemain.
   * **Jadwal & FDR:** Fixture Difficulty Rating 3-5 GW ke depan dengan penyesuaian *Home/Away advantage*.
   * **Effective Ownership (EO):** Perlindungan rank template vs diferensial.
   * **Hard-Filter Cedera:** Pemain dengan *chance of playing < 75%* otomatis di-filter/blokir.
   * **Saklar & Bobot:** Setiap parameter dapat diaktifkan/dinonaktifkan (ON/OFF) serta diatur bobot persentasenya (0-100%) via slider di dashboard.

4. **Integrasi Analisis YouTube FPL:**
   * Input link YouTube manual per Gameweek (GW).
   * **Smart Fallback:** Jika GW baru belum diisi link baru, otomatis menggunakan analisis dari video sebelumnya yang tersimpan di database.
   * Nilai rekomendasi analis (*Buy, Sell, Captain*) diintegrasikan langsung ke dalam pembobotan skor.

5. **Eksekusi Akun Resmi FPL:**
   * Otomatisasi login aman via kredensial di `.env`.
   * Melakukan transfer pemain dan mengubah susunan Kapten (C) / Wakil Kapten (VC) secara terprogram via API resmi FPL.

---

## 🏗️ Struktur Direktori

```text
fpl-bot/
├── config/
│   ├── .env.example          # Template konfigurasi kredensial & database
│   ├── config.php            # Loader variabel lingkungan (.env)
│   └── database.php          # Koneksi PDO MySQL & Auto-Migration Tabel
├── src/
│   ├── FplService.php        # Handler komunikasi resmi API FPL (cURL native)
│   ├── YoutubeService.php    # Handler ekstraksi & konsensus video YouTube
│   ├── ScoringEngine.php     # Kalkulasi skor multi-parameter & rencana transfer
│   └── CronHandler.php       # Logika otomatisasi Smart Fallback (H-30m)
├── public/
│   ├── css/
│   │   └── style.css         # Styling modern pitch tactical board
│   ├── js/
│   │   └── app.js            # Interaksi UI, slider auto-save & countdown
│   └── index.php             # Dashboard utama
├── api/
│   ├── get_team.php          # Endpoint fetch data skuad & rekomendasi
│   ├── update_settings.php   # Endpoint simpan bobot parameter
│   ├── save_youtube.php      # Endpoint simpan link video YouTube
│   ├── trigger_execute.php   # Endpoint eksekusi manual 1-klik
│   └── get_logs.php          # Endpoint riwayat log eksekusi
├── docs/
│   └── PROJECT_SPEC.md       # Spesifikasi teknis sistem
├── cron.php                  # Background Cron runner untuk cPanel Niagahoster
├── index.php                 # Root entry point
└── README.md
```

---

## 🚀 Panduan Instalasi & Menjalankan

### A. Di Komputer Lokal (XAMPP)

1. Pastikan folder project berada di `E:\xampp\fpl-bot` atau `C:\xampp\htdocs\fpl-bot`.
2. Nyalakan modul **Apache** dan **MySQL** di XAMPP Control Panel.
3. Salin file `.env.example` menjadi `.env` lalu sesuaikan kredensial:
   ```ini
   DB_HOST=127.0.0.1
   DB_NAME=fpl_bot
   DB_USER=root
   DB_PASS=

   FPL_EMAIL=email_fpl_anda@gmail.com
   FPL_PASSWORD=password_fpl_anda
   FPL_TEAM_ID=1234567
   ```
4. Buka browser dan akses: `http://localhost/fpl-bot`
   *(Database `fpl_bot` beserta seluruh tabel akan otomatis dibuat saat halaman pertama kali dibuka!)*

---

### B. Di Hosting Niagahoster (cPanel)

1. **Upload / Clone File:**
   * Masuk ke cPanel Niagahoster -> **File Manager** -> buka folder `public_html/fpl-bot` (atau root domain).
   * Upload semua file project atau gunakan fitur **Git Version Control** di cPanel.

2. **Buat Database MySQL:**
   * Masuk ke cPanel -> **MySQL Database Wizard**.
   * Buat database baru (misal: `u123_fplbot`), user, dan password.
   * Berikan hak akses **ALL PRIVILEGES**.

3. **Konfigurasi `.env`:**
   * Edit file `.env` di File Manager Niagahoster dan masukkan nama database, user, password, serta akun FPL Anda.

4. **Atur Cron Job di cPanel (Untuk Smart Fallback H-30 Menit):**
   * Masuk ke menu **Cron Jobs** di cPanel Niagahoster.
   * Pilih interval: **Once Per 5 Minutes (`*/5 * * * *`)**.
   * Masukkan perintah berikut:
     ```bash
     php -q /home/USER_CPANEL_ANDA/public_html/fpl-bot/cron.php
     ```
     *(Ganti `USER_CPANEL_ANDA` dan sesuaikan path direktori Anda).*
   * Selesai! Sistem akan otomatis memeriksa deadline dan melakukan fallback 30 menit sebelum Gameweek ditutup jika Anda belum mengeksekusi manual.
