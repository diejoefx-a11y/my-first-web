<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$db = get_db();

$sql = "
    SELECT 
        f.no_kk,
        f.nik_kepala,
        f.nama_kepala,
        g.nama_kelompok,
        f.no_hp,
        f.rt,
        f.rw,
        f.kelurahan,
        f.kecamatan,
        f.alamat_lengkap,
        f.latitude,
        f.longitude,
        f.jumlah_tanggungan,
        f.status_verifikasi,
        f.created_at,
        m.nik as nik_anggota,
        m.nama_lengkap as nama_anggota,
        m.hubungan_keluarga,
        m.jenis_kelamin,
        m.tempat_lahir,
        m.tanggal_lahir,
        m.agama,
        m.pendidikan_terakhir,
        m.pekerjaan
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    LEFT JOIN family_members m ON f.id = m.family_id
    ORDER BY g.nomor_kelompok ASC, f.no_kk ASC, m.id ASC
";

$stmt = $db->query($sql);
$rows = $stmt->fetchAll();

$filename = "Export_Data_Keluarga_Kelompok_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Column
fputcsv($output, [
    'No. KK',
    'NIK Kepala Keluarga',
    'Nama Kepala Keluarga',
    'Kelompok Domisili',
    'No. WhatsApp / HP',
    'RT',
    'RW',
    'Kelurahan',
    'Kecamatan',
    'Alamat Lengkap',
    'Latitude',
    'Longitude',
    'Jumlah Tanggungan',
    'Status Verifikasi',
    'Tanggal Daftar',
    'NIK Anggota',
    'Nama Anggota',
    'Hubungan Keluarga',
    'Jenis Kelamin',
    'Tempat Lahir',
    'Tanggal Lahir',
    'Agama',
    'Pendidikan Terakhir',
    'Pekerjaan'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        "'" . $row['no_kk'],
        "'" . $row['nik_kepala'],
        $row['nama_kepala'],
        $row['nama_kelompok'] ?: 'Belum Ditentukan',
        "'" . $row['no_hp'],
        $row['rt'],
        $row['rw'],
        $row['kelurahan'],
        $row['kecamatan'],
        $row['alamat_lengkap'],
        $row['latitude'],
        $row['longitude'],
        $row['jumlah_tanggungan'],
        ucfirst($row['status_verifikasi']),
        $row['created_at'],
        !empty($row['nik_anggota']) ? "'" . $row['nik_anggota'] : '',
        $row['nama_anggota'],
        $row['hubungan_keluarga'],
        $row['jenis_kelamin'],
        $row['tempat_lahir'],
        $row['tanggal_lahir'],
        $row['agama'],
        $row['pendidikan_terakhir'],
        $row['pekerjaan']
    ]);
}

fclose($output);
exit;
