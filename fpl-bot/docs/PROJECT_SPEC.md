# 📋 SPESIFIKASI TEKNIS & BLUEPRINT ARSITEKTUR: FPL-BOT (NATIVE PHP)

Dokumen ini memuat seluruh keputusan desain teknis, parameter sistem, dan alur integrasi aplikasi `fpl-bot`.

---

## 1. Modul Parameter Keputusan (Multi-Parameter Engine)

Setiap calon pemain dan rencana transfer dihitung menggunakan formula pembobotan:

$$\text{Total Score} = \sum_{i=1}^{n} \left( \text{Score}_i \times \frac{\text{Weight}_i}{100} \right)$$

### Daftar Parameter:
1. **Statistik Kuantitatif (Form & Underlying Data):**
   * Metrik: $xG$, $xA$, $xGI$, $Form$ (rata-rata 3-5 GW terakhir).
   * Status: Toggleable (ON/OFF) + Weight Slider (0 - 100%).
2. **Jadwal & FDR (Fixture Difficulty Rating):**
   * Metrik: Bobot tingkat kesulitan lawan 3-5 gameweek ke depan, penyesuaian Home/Away advantage.
   * Status: Toggleable (ON/OFF) + Weight Slider (0 - 100%).
3. **YouTube Consensus Score (Data Kualitatif):**
   * Metrik: Frekuensi rekomendasi dari video YouTube pilihan per Gameweek.
   * Status: Toggleable (ON/OFF) + Weight Slider (0 - 100%).
   * Mode: Input link YouTube per GW dengan Smart Fallback ke video tersimpan sebelumnya jika belum diisi.
4. **Proteksi Effective Ownership (EO):**
   * Menjaga kepemilikan pemain kunci template agar ranking tidak anjlok saat pemain populer mencetak poin.
   * Status: Toggleable (ON/OFF).
5. **Injury / Suspension Hard-Filter:**
   * Otomatis memblokir transfer pemain yang memiliki status cedera (*chance of playing < 75%*).
   * Status: Selalu aktif (Safety feature).

---

## 2. Spesifikasi Mekanisme Smart Fallback

* **Waktu Trigger:** 30 Menit sebelum *deadline* resmi FPL Gameweek terkait.
* **Status State Machine:**
  * `IDLE` -> Menunggu jadwal Gameweek.
  * `RECOMMENDATION_READY` -> Sistem telah menghitung rencana transfer & C/VC terbaik, menunggu review user di dashboard.
  * `MANUAL_EXECUTED` -> User mengklik tombol "Terapkan ke Akun FPL" di web. Timer fallback dinonaktifkan.
  * `AUTO_EXECUTED` -> User tidak melakukan aksi hingga $T - 30$ menit. Sistem otomatis mengeksekusi rencana rekomendasi terakhir ke API FPL.
* **Proteksi Keamanan Eksekusi:**
  * Maksimal $0$ point hit (Hanya memakai Free Transfer yang tersedia).
  * Tidak mengaktifkan Chip (*Wildcard / Free Hit / Triple Captain / Bench Boost*) secara otomatis.

---

## 3. Integrasi & Alur Autentikasi FPL (Native PHP cURL)

1. **Login Sesi:**
   * POST via cURL ke `https://users.premierleague.com/accounts/login/` dengan payload email dan password.
   * Menyimpan session cookie ke file lokal temporer.
2. **Perubahan Susunan Tim (C / VC / Bench):**
   * POST ke `https://fantasy.premierleague.com/api/my-team/{team_id}/` dengan array `picks`.
3. **Eksekusi Transfer:**
   * POST ke `https://fantasy.premierleague.com/api/transfers/` dengan detail transfer in & out.

---

## 4. Alur Integrasi YouTube FPL

1. User menginput link URL video YouTube di form dashboard web untuk GW aktif.
2. Script PHP mengekstrak *video ID* dan metadata via YouTube oEmbed API / AI summarizer.
3. Parser mengekstrak nama-nama pemain yang direkomendasikan (*Buy, Sell, Captain*).
4. Hasil disimpan ke tabel database MySQL `youtube_consensus`.
5. Jika GW berikutnya user tidak menginput link baru, sistem menggunakan data video tersimpan sebelumnya sebagai referensi default.
