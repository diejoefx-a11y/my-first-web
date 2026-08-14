-- Sample Data Keluarga untuk Simulasi Peta
USE `db_data_keluarga`;

INSERT INTO `families` (
    `id`, `no_kk`, `nik_kepala`, `nama_kepala`, `no_hp`, `rt`, `rw`, `kelurahan`, `kecamatan`,
    `alamat_lengkap`, `latitude`, `longitude`, `jumlah_tanggungan`, `status_verifikasi`, `catatan_admin`
) VALUES 
(1, '7371110101900001', '7371110101900001', 'Budi Santoso', '081234567891', '01', '02', 'Tamalanrea', 'Tamalanrea', 'Jl. Perintis Kemerdekaan KM 10 No. 12', -5.13824000, 119.49234000, 3, 'terverifikasi', 'Data sudah dicek oleh RT 01'),
(2, '7371110101900002', '7371110101900002', 'Ahmad Fauzi', '081345678902', '01', '02', 'Tamalanrea', 'Tamalanrea', 'Jl. Perintis Kemerdekaan KM 11 Lorong 3', -5.13210000, 119.49850000, 4, 'terverifikasi', 'Lengkap'),
(3, '7371110101900003', '7371110101900003', 'Rahmat Hidayat', '081987654321', '02', '02', 'Tamalanrea Jaya', 'Tamalanrea', 'Kompleks BTP Blok M No. 45', -5.12780000, 119.50520000, 2, 'pending', 'Menunggu foto rumah yang lebih jelas'),
(4, '7371110101900004', '7371110101900004', 'Hendrawan Kusuma', '082112345678', '03', '01', 'Kapasa', 'Tamalanrea', 'Jl. Kapasa Raya No. 88', -5.11540000, 119.51230000, 1, 'terverifikasi', 'Valid');

-- Anggota Keluarga Budi Santoso
INSERT INTO `family_members` (`family_id`, `nik`, `nama_lengkap`, `hubungan_keluarga`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `pendidikan_terakhir`, `pekerjaan`) VALUES
(1, '7371110101900001', 'Budi Santoso', 'Kepala Keluarga', 'L', 'Makassar', '1985-05-12', 'Islam', 'S1/Sarjana', 'Karyawan Swasta'),
(1, '7371110101900005', 'Siti Rahmawati', 'Istri', 'P', 'Gowa', '1988-08-20', 'Islam', 'SMA/SMK/Sederajat', 'Ibu Rumah Tangga'),
(1, '7371110101900006', 'Muhammad Rizky', 'Anak', 'L', 'Makassar', '2012-02-14', 'Islam', 'SD/Sederajat', 'Pelajar');

-- Anggota Keluarga Ahmad Fauzi
INSERT INTO `family_members` (`family_id`, `nik`, `nama_lengkap`, `hubungan_keluarga`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `pendidikan_terakhir`, `pekerjaan`) VALUES
(2, '7371110101900002', 'Ahmad Fauzi', 'Kepala Keluarga', 'L', 'Maros', '1979-11-03', 'Islam', 'SMA/SMK/Sederajat', 'Wiraswasta'),
(2, '7371110101900007', 'Nurul Aini', 'Istri', 'P', 'Makassar', '1982-04-18', 'Islam', 'SMA/SMK/Sederajat', 'Pedagang'),
(2, '7371110101900008', 'Fikri Haikal', 'Anak', 'L', 'Makassar', '2008-09-10', 'Islam', 'SMP/Sederajat', 'Pelajar'),
(2, '7371110101900009', 'Nabila Zahra', 'Anak', 'P', 'Makassar', '2015-12-25', 'Islam', 'Belum/Tidak Sekolah', '-');
