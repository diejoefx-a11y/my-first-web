# Context & Status Proyek: PKB Portal (Persekutuan Kaum Bapak / Jemaat)

## 📌 Ringkasan Proyek
Aplikasi Web **Portal Informasi & Pemetaan Spasial Jemaat PKB** berbasis PHP Native + MySQL + Leaflet.js / OpenStreetMap + Bootstrap/Tailwind/Custom CSS.

- **Lokasi Project**: `e:\xampp\pkb\`
- **Database**: `db_data_keluarga` (MySQL / XAMPP)
- **Timezone**: `Asia/Makassar` (WITA)
- **Koneksi DB**: `config/database.php` menggunakan PDO (Strict Security & Session Management).

---

## 📂 Struktur Direktori & Modul Utama

```text
e:\xampp\pkb\
├── admin/                     # Modul Panel Admin
│   ├── index.php              # Dashboard admin, ringkasan statistik & menu utama
│   ├── login.php              # Autentikasi admin (aman, session timeout 30 mnt)
│   ├── logout.php             # Logout handler
│   ├── header.php             # Layout header admin
│   ├── footer.php             # Layout footer admin
│   ├── keluarga.php           # Manajemen data Kepala Keluarga & anggota keluarga
│   ├── kelompok.php           # Manajemen Kelompok (1 s/d 17) & statistik
│   ├── kelompok_detail.php     # Detail kelompok, daftar KK, filter & rekapitulasi
│   ├── detail.php             # Detail keluarga & anggota keluarga
│   ├── edit.php               # Form edit data keluarga / anggota
│   ├── hapus.php              # Penghapusan data dengan konfirmasi & cascade
│   ├── peta.php               # Peta sebaran spasial keluarga/jemaat di admin
│   ├── cetak.php              # Fitur cetak laporan / kartu keluarga jemaat
│   └── export_excel.php       # Ekspor data ke format Excel / Spreadsheet
│
├── config/
│   └── database.php           # Konfigurasi PDO, Session Security, CSRF helper, Helper function
│
├── database/
│   ├── schema.sql             # Skema dasar tabel
│   ├── portal_schema.sql      # Skema portal berita / konten tambahan
│   ├── kelompok_schema.sql    # Skema tabel kelompok (`groups`)
│   ├── kelompok_detail_schema.sql # Skema relasi & detail anggota per kelompok
│   └── seed_data.sql          # Data inisialisasi / dummy awal
│
├── jemaat/                    # Modul Interaktif Jemaat / Publik
│   ├── cek_kk.php             # Validasi status verifikasi KK & router ke data lengkap / koordinator
│   ├── data_lengkap.php       # Direktori data lengkap jemaat (Diproteksi Session KK Terverifikasi)
│   ├── edit_data.php          # Pembaruan mandiri data jemaat (via cek verifikasi)
│   ├── edit_data_noverifikasi.php # Pembaruan data keluarga jemaat langsung (tanpa sesi & tanpa verifikasi)
│   └── pasangtitik.php        # Penentuan titik koordinat rumah jemaat pada peta (GPS/Pinpoint)
│
├── uploads/                   # Folder upload foto rumah, dokumen, dan berkas jemaat
├── assets/                    # File CSS, JS, Icon, Gambar
├── index.php                  # Halaman Utama Portal Publik (Statistik, Peta Publik, Info Jemaat)
└── sukses.php                 # Halaman konfirmasi setelah berhasil submit data
```

---

## 🗄️ Struktur Database Utama (`db_data_keluarga`)
1. **`groups`**: Daftar kelompok jemaat (`nomor_kelompok`, `nama_kelompok`, dll).
2. **`families`**: Data Kepala Keluarga (`no_kk`, `nama_kk`, `alamat`, `nomor_kelompok`, `latitude`, `longitude`, `foto_rumah`, dll).
3. **`family_members`**: Anggota keluarga (`family_id`, `nama`, `nik`, `hubungan_keluarga`, `jenis_kelamin`, `tanggal_lahir`, `pendidikan`, `pekerjaan`, dll).
4. **`users` / `admins`**: Data akun administrator (`username`, `password_hash`, `role`).
5. **Portal/Berita/Kegiatan** (Opsional/Dapat diaktifkan melalui sakelar di `index.php` `$SHOW_PORTAL_BERITA`).

---

## 🚀 Panduan untuk Sesi / Chat Baru
Jika membuka sesi obrolan baru di Antigravity / AI:
1. Cukup sebutkan: *"Lanjutkan pengembangan PKB Portal di e:\xampp\pkb (baca PROJECT_CONTEXT.md)"*.
2. AI dapat langsung membaca file ini dan kode program di direktori tersebut secara otomatis.
