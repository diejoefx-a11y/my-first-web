<?php
/**
 * FORMULIR PENDAFTARAN KARTU KELUARGA & PEMETAAN TITIK RUMAH SPASIAL
 * Persekutuan Jemaat Kristiani & Persekutuan Kaum Bapak (PKB)
 */

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Makassar');

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();

    // 1. Sanitize & Retrieve Input
    $no_kk = preg_replace('/[^0-9]/', '', $_POST['no_kk'] ?? '');
    $nik_kepala = preg_replace('/[^0-9]/', '', $_POST['nik_kepala'] ?? '');
    $nama_kepala = clean($_POST['nama_kepala'] ?? '');
    $kelompok_id = !empty($_POST['kelompok_id']) ? intval($_POST['kelompok_id']) : 1;
    $no_hp = clean($_POST['no_hp'] ?? '');
    $rt = clean($_POST['rt'] ?? '01');
    $rw = clean($_POST['rw'] ?? '01');
    $kelurahan = clean($_POST['kelurahan'] ?? 'Tamalanrea');
    $kecamatan = clean($_POST['kecamatan'] ?? 'Tamalanrea');
    $alamat_lengkap = clean($_POST['alamat_lengkap'] ?? '');
    
    // Coordinates fallback to default Makassar if empty
    $latitude = !empty($_POST['latitude']) && is_numeric($_POST['latitude']) ? clean($_POST['latitude']) : '-5.147665';
    $longitude = !empty($_POST['longitude']) && is_numeric($_POST['longitude']) ? clean($_POST['longitude']) : '119.432731';
    
    $jumlah_tanggungan = intval($_POST['jumlah_tanggungan'] ?? 0);
    $members = $_POST['members'] ?? [];

    $old = $_POST;

    // 2. Validation
    if (empty($no_kk) || strlen($no_kk) < 10) {
        $errors[] = "Nomor Kartu Keluarga (KK) wajib diisi (minimal 10-16 digit angka).";
    }
    if (empty($nik_kepala) || strlen($nik_kepala) < 10) {
        $errors[] = "NIK Kepala Keluarga wajib diisi (minimal 10-16 digit angka).";
    }
    if (empty($nama_kepala)) {
        $errors[] = "Nama Kepala Keluarga wajib diisi.";
    }
    if (empty($no_hp)) {
        $errors[] = "Nomor WhatsApp / HP aktif wajib diisi.";
    }
    if (empty($alamat_lengkap)) {
        $errors[] = "Alamat lengkap / nama jalan wajib diisi (atau klik titik di peta).";
    }

    // Check duplicate KK
    if (empty($errors)) {
        $stmtCheck = $db->prepare("SELECT id, nama_kepala FROM families WHERE no_kk = ? LIMIT 1");
        $stmtCheck->execute([$no_kk]);
        $existing = $stmtCheck->fetch();
        if ($existing) {
            $errors[] = "Nomor KK <strong>$no_kk</strong> sudah pernah didaftarkan atas nama <strong>" . htmlspecialchars($existing['nama_kepala']) . "</strong>. Jika ingin mengubah data, silakan hubungi Majelis Admin.";
        }
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    // 3. Handle File Upload (Foto Keluarga)
    $foto_keluarga_name = null;
    if (isset($_FILES['foto_keluarga']) && $_FILES['foto_keluarga']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_keluarga']['tmp_name'];
        $fileName = $_FILES['foto_keluarga']['name'];
        $fileSize = $_FILES['foto_keluarga']['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExts)) {
            $errors[] = "Format foto keluarga hanya boleh JPG, JPEG, PNG, atau WEBP.";
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "Ukuran file foto keluarga maksimal adalah 5 MB.";
        } else {
            $foto_keluarga_name = 'keluarga_' . time() . '_' . uniqid() . '.' . $fileExt;
            $destination = $uploadDir . $foto_keluarga_name;
            if (!move_uploaded_file($fileTmp, $destination)) {
                $foto_keluarga_name = null;
            }
        }
    }

    // 4. Handle File Upload (Foto Rumah / Tampak Depan)
    $foto_rumah_name = null;
    if (isset($_FILES['foto_rumah']) && $_FILES['foto_rumah']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_rumah']['tmp_name'];
        $fileName = $_FILES['foto_rumah']['name'];
        $fileSize = $_FILES['foto_rumah']['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExts)) {
            $errors[] = "Format foto rumah hanya boleh JPG, JPEG, PNG, atau WEBP.";
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "Ukuran file foto rumah maksimal adalah 5 MB.";
        } else {
            $foto_rumah_name = 'rumah_' . time() . '_' . uniqid() . '.' . $fileExt;
            $destination = $uploadDir . $foto_rumah_name;
            if (!move_uploaded_file($fileTmp, $destination)) {
                $foto_rumah_name = null;
            }
        }
    }

    // 5. Save to Database using Transaction
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmtFamily = $db->prepare("
                INSERT INTO families (
                    no_kk, nik_kepala, nama_kepala, kelompok_id, no_hp, rt, rw, kelurahan, kecamatan,
                    alamat_lengkap, latitude, longitude, jumlah_tanggungan, foto_rumah, foto_keluarga, status_verifikasi
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, 'pending'
                )
            ");

            $stmtFamily->execute([
                $no_kk, $nik_kepala, $nama_kepala, $kelompok_id, $no_hp, $rt, $rw, $kelurahan, $kecamatan,
                $alamat_lengkap, $latitude, $longitude, $jumlah_tanggungan, $foto_rumah_name, $foto_keluarga_name
            ]);

            $familyId = $db->lastInsertId();

            // Insert Family Members
            $stmtMember = $db->prepare("
                INSERT INTO family_members (
                    family_id, nik, nama_lengkap, hubungan_keluarga, jenis_kelamin,
                    tempat_lahir, tanggal_lahir
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?
                )
            ");

            $hasHeadOfFamily = false;

            if (!empty($members) && is_array($members)) {
                foreach ($members as $m) {
                    $m_nama = clean($m['nama_lengkap'] ?? '');
                    $m_nik = preg_replace('/[^0-9]/', '', $m['nik'] ?? '');
                    $m_hubungan = clean($m['hubungan_keluarga'] ?? 'Anggota');
                    $m_jk = clean($m['jenis_kelamin'] ?? 'L');
                    $m_tempat = clean($m['tempat_lahir'] ?? '');
                    $m_tgl = !empty($m['tanggal_lahir']) ? $m['tanggal_lahir'] : null;

                    if (!empty($m_nama)) {
                        if (empty($m_nik)) $m_nik = $nik_kepala;
                        if ($m_hubungan === 'Kepala Keluarga') $hasHeadOfFamily = true;

                        $stmtMember->execute([
                            $familyId, $m_nik, $m_nama, $m_hubungan, $m_jk,
                            $m_tempat, $m_tgl
                        ]);
                    }
                }
            }

            // If Kepala Keluarga member row was not explicitly inserted, insert from main form
            if (!$hasHeadOfFamily) {
                $stmtMember->execute([
                    $familyId, $nik_kepala, $nama_kepala, 'Kepala Keluarga', 'L',
                    'Makassar', null
                ]);
            }

            $db->commit();

            header("Location: ../sukses.php?id=" . $familyId . "&kk=" . urlencode($no_kk));
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Gagal menyimpan ke database: " . $e->getMessage();
        }
    }
}

$db = get_db();
$groupsList = $db->query("SELECT id, nomor_kelompok, nama_kelompok, nama_ketua, nama_sekretaris FROM `groups` ORDER BY nomor_kelompok ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pasang Titik Rumah & Sensus KK Jemaat (PKB)</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Google Fonts & Custom CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .app-header {
            background: linear-gradient(135deg, #2e1065 0%, #4c1d95 50%, #6d28d9 100%);
            color: #ffffff;
            padding: 2.5rem 0 2rem 0;
            border-bottom: 2px solid rgba(167, 139, 250, 0.3);
            box-shadow: 0 10px 25px rgba(46, 16, 101, 0.3);
        }
        .badge-pill-church {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #e9d5ff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-back-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #c4b5fd;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .btn-back-home:hover {
            color: #ffffff;
            transform: translateX(-3px);
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header class="app-header">
        <div class="container">
            <a href="../index.php" class="btn-back-home">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Portal Warta Jemaat
            </a>
            <div>
                <span class="badge-pill-church">
                    <i class="fa-solid fa-church"></i> Persekutuan Kaum Bapak (PKB)
                </span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; font-weight: 800; margin-bottom: 0.35rem;">
                📍 Formulir Pasang Titik Rumah & Sensus KK Jemaat
            </h1>
            <p style="color: #ddd6fe; font-size: 0.95rem; max-width: 800px; line-height: 1.5;">
                Silakan lengkapi identitas Kepala Keluarga, pilih <strong>Kelompok Domisili (1 s/d 14)</strong>, susunan anggota keluarga, serta geser titik koordinat atap rumah pada peta interaktif di bawah ini.
            </p>
        </div>
    </header>

    <!-- Main Form Wrapper -->
    <div class="container main-wrapper" style="margin-top: 1.75rem;">

        <!-- Edit Data Shortcut Banner -->
        <div style="background: #f5f3ff; border: 1.5px solid #ddd6fe; border-radius: 14px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 0.9rem; color: #5b21b6;">
                <span>💡 Sudah pernah mendaftarkan Kartu Keluarga sebelumnya?</span>
            </div>
            <a href="edit_data.php" style="background: #7c3aed; color: #ffffff; padding: 6px 16px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-user-pen"></i> Perbarui / Edit Data KK &rarr;
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: #fee2e2; border: 1.5px solid #ef4444; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.15);">
                <div style="color: #991b1b;">
                    <strong style="font-size: 1.05rem;"><i class="fa-solid fa-triangle-exclamation"></i> Data Belum Tersimpan. Mohon Lengkapi:</strong>
                    <ul style="margin-left: 1.5rem; margin-top: 0.5rem; line-height: 1.6; font-size: 0.92rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <script>window.scrollTo({ top: 150, behavior: 'smooth' });</script>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" id="form-keluarga">
            
            <!-- 1. DATA POKOK KEPALA KELUARGA -->
            <div class="card" style="box-shadow: 0 4px 18px rgba(0,0,0,0.06); border-radius: 16px; border: 1.5px solid #e9d5ff;">
                <div class="card-title-section">
                    <div class="card-icon" style="background: #ede9fe; color: #6d28d9; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        👤
                    </div>
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">Data Pokok Kepala Keluarga Jemaat</h3>
                        <small style="color: #6b7280;">Informasi Kartu Keluarga, pemilihan kelompok binaan, kontak WhatsApp, dan foto keluarga</small>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="no_kk">Nomor Kartu Keluarga (KK) <span class="required" style="color:#ef4444;">*</span></label>
                        <input type="text" id="no_kk" name="no_kk" class="form-control" maxlength="16" placeholder="16 digit nomor KK (contoh: 7371012345678901)" value="<?= htmlspecialchars($old['no_kk'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nik_kepala">NIK Kepala Keluarga <span class="required" style="color:#ef4444;">*</span></label>
                        <input type="text" id="nik_kepala" name="nik_kepala" class="form-control" maxlength="16" placeholder="16 digit NIK KTP (contoh: 7371012345678901)" value="<?= htmlspecialchars($old['nik_kepala'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nama_kepala">Nama Lengkap Kepala Keluarga <span class="required" style="color:#ef4444;">*</span></label>
                        <input type="text" id="nama_kepala" name="nama_kepala" class="form-control" placeholder="Nama lengkap sesuai KTP" value="<?= htmlspecialchars($old['nama_kepala'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="kelompok_id">Kelompok Pelayanan Domisili (Kelompok 1 - 14) <span class="required" style="color:#ef4444;">*</span></label>
                        <select id="kelompok_id" name="kelompok_id" class="form-control" required style="font-weight: 600; color: #5b21b6;">
                            <option value="">-- Pilih Kelompok Binaan --</option>
                            <?php foreach ($groupsList as $grp): ?>
                                <option value="<?= $grp['id'] ?>" <?= (isset($old['kelompok_id']) && $old['kelompok_id'] == $grp['id']) ? 'selected' : ($grp['nomor_kelompok'] == 1 ? 'selected' : '') ?>>
                                    🏷️ <?= htmlspecialchars($grp['nama_kelompok']) ?><?= !empty($grp['nama_ketua']) ? ' (Ketua: ' . htmlspecialchars($grp['nama_ketua']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="no_hp">No. WhatsApp / HP Aktif <span class="required" style="color:#ef4444;">*</span></label>
                        <input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($old['no_hp'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="jumlah_tanggungan">Jumlah Tanggungan Keluarga</label>
                        <input type="number" id="jumlah_tanggungan" name="jumlah_tanggungan" class="form-control" min="0" max="20" placeholder="Contoh: 3" value="<?= htmlspecialchars($old['jumlah_tanggungan'] ?? '0') ?>">
                    </div>

                    <!-- FOTO KELUARGA (OPSIONAL) -->
                    <div class="form-group" style="grid-column: 1 / -1; background: #faf5ff; border: 1.5px dashed #c084fc; border-radius: 12px; padding: 1rem 1.25rem;">
                        <label for="foto_keluarga" style="color: #6b21a8; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <span>👨‍👩‍👧‍👦</span> Foto Bersama Keluarga Jemaat <span style="font-weight: 400; color: #9333ea; font-size: 0.8rem;">(Opsional)</span>
                        </label>
                        <input type="file" id="foto_keluarga" name="foto_keluarga" class="form-control" accept="image/jpeg,image/png,image/webp" style="background: #ffffff; margin-top: 0.35rem;">
                        <small style="color: #7e22ce; font-size: 0.775rem; display: block; margin-top: 0.35rem;">
                            Unggah foto bersama anggota keluarga tercinta. Format: JPG, PNG, atau WEBP (Maksimal 5 MB).
                        </small>
                        <div id="foto-keluarga-preview" style="margin-top: 0.75rem; display: none;">
                            <img src="" alt="Pratinjau Foto Keluarga" style="max-height: 200px; border-radius: 10px; border: 2px solid #a855f7; object-fit: cover; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.2);">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. ALAMAT DOMISILI & TITIK LOKASI PETA -->
            <div class="card" style="box-shadow: 0 4px 18px rgba(0,0,0,0.06); border-radius: 16px; border: 1.5px solid #e9d5ff;">
                <div class="card-title-section">
                    <div class="card-icon" style="background: #e0f2fe; color: #0284c7; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        📍
                    </div>
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">Alamat & Pasang Titik Rumah di Peta</h3>
                        <small style="color: #6b7280;">Gunakan tombol GPS otomatis atau geser pin merah tepat di atas atap rumah Anda (data jalan akan otomatis terisi)</small>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="rt">RT</label>
                        <input type="text" id="rt" name="rt" class="form-control" placeholder="Contoh: 01" value="<?= htmlspecialchars($old['rt'] ?? '01') ?>">
                    </div>

                    <div class="form-group">
                        <label for="rw">RW</label>
                        <input type="text" id="rw" name="rw" class="form-control" placeholder="Contoh: 02" value="<?= htmlspecialchars($old['rw'] ?? '01') ?>">
                    </div>

                    <div class="form-group">
                        <label for="kelurahan">Kelurahan / Desa</label>
                        <input type="text" id="kelurahan" name="kelurahan" class="form-control" placeholder="Kelurahan" value="<?= htmlspecialchars($old['kelurahan'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="kecamatan">Kecamatan</label>
                        <input type="text" id="kecamatan" name="kecamatan" class="form-control" placeholder="Kecamatan" value="<?= htmlspecialchars($old['kecamatan'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.5rem;">
                    <label for="alamat_lengkap">Alamat Lengkap (Nama Jalan / Nomor Rumah / Blok) <span class="required" style="color:#ef4444;">*</span></label>
                    <textarea id="alamat_lengkap" name="alamat_lengkap" class="form-control" placeholder="Contoh: Jl. Perintis Kemerdekaan No. 21, Blok C..." required><?= htmlspecialchars($old['alamat_lengkap'] ?? '') ?></textarea>
                </div>

                <!-- PETA INTERAKTIF LEAFLET -->
                <div class="form-group" style="margin-top: 1.25rem;">
                    
                    <!-- Tips Android GPS -->
                    <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 0.85rem; display: flex; align-items: flex-start; gap: 0.75rem;">
                        <span style="font-size: 1.3rem; line-height: 1;">📱</span>
                        <div style="font-size: 0.825rem; color: #065f46; line-height: 1.45;">
                            <strong>Tips Penentuan Titik di HP Android:</strong><br>
                            Nyalakan tombol <strong>Lokasi/GPS</strong> di HP Anda, lalu ketuk tombol hijau <strong>"📍 Gunakan Lokasi GPS Saya"</strong> di bawah. Anda juga bisa langsung menggeser pin merah di peta.
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap; gap: 0.5rem;">
                        <label style="font-weight: 700; color: #1e1b4b; font-size: 0.95rem;">
                            Peta Penentuan Titik Koordinat Rumah <span class="required" style="color:#ef4444;">*</span>
                        </label>
                        <button type="button" id="btn-gps" class="btn btn-sm" style="font-size: 0.85rem; font-weight: 800; background: #10b981; color: #ffffff; border-radius: 20px; padding: 0.5rem 1.2rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border: none;">
                            <span>📍</span> <span>Gunakan Lokasi GPS Saya</span>
                        </button>
                    </div>

                    <div class="map-search-bar" style="margin-bottom: 0.75rem; display: flex; gap: 0.5rem;">
                        <input type="text" id="map-search-input" class="form-control" placeholder="Atau cari nama jalan / kelurahan..." style="font-size: 0.85rem;">
                        <button type="button" id="btn-search-map" class="btn btn-primary btn-sm" style="padding: 0 1.25rem;">Cari</button>
                    </div>

                    <div class="map-wrapper" style="border-radius: 14px; overflow: hidden; border: 1.5px solid #cbd5e1;">
                        <div id="map" style="height: 380px; width: 100%;"></div>
                    </div>

                    <!-- Hidden Inputs for Coordinates (Pre-filled with Makassar default) -->
                    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($old['latitude'] ?? '-5.147665') ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($old['longitude'] ?? '119.432731') ?>">

                    <div class="map-coords-card" style="margin-top: 0.75rem; background: #f5f3ff; border: 1px solid #ddd6fe; padding: 0.65rem 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span style="font-size: 0.85rem; color: #5b21b6; font-weight: 600;">Koordinat Terpilih:</span>
                        <span class="coords-badge" id="display-coords" style="font-family: monospace; font-weight: 800; background: #7c3aed; color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 0.85rem;">
                            <?= htmlspecialchars($old['latitude'] ?? '-5.147665') ?>, <?= htmlspecialchars($old['longitude'] ?? '119.432731') ?>
                        </span>
                    </div>
                    <div class="map-hint" id="gps-status" style="margin-top: 0.5rem; font-size: 0.8rem; line-height: 1.4;">
                        <span style="color: #6b7280;">* Ketuk tombol hijau GPS di atas atau sentuh titik di peta untuk menentukan koordinat rumah (nama jalan & wilayah otomatis terisi).</span>
                    </div>
                </div>

                <!-- Foto Rumah -->
                <div class="form-group" style="margin-top: 1.25rem;">
                    <label for="foto_rumah">Foto Rumah / Tempat Tinggal Jemaat (Opsional)</label>
                    <input type="file" id="foto_rumah" name="foto_rumah" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small style="color: #6b7280; font-size: 0.775rem; display: block; margin-top: 0.25rem;">Maksimal ukuran file: 5 MB. Format: JPG, PNG, atau WEBP.</small>
                    <div id="foto-preview" style="margin-top: 0.75rem; display: none;">
                        <img src="" alt="Pratinjau Foto Rumah" style="max-height: 180px; border-radius: 10px; border: 1px solid #cbd5e1; object-fit: cover;">
                    </div>
                </div>
            </div>

            <!-- 3. DETAIL ANGGOTA KELUARGA (REPEATER) -->
            <div class="card" style="box-shadow: 0 4px 18px rgba(0,0,0,0.06); border-radius: 16px; border: 1.5px solid #e9d5ff;">
                <div class="card-title-section" style="justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div class="card-icon" style="background: #fdf2f8; color: #db2777; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            👨‍👩‍👧‍👦
                        </div>
                        <div>
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">Susunan Anggota Keluarga Jemaat</h3>
                            <small style="color: #6b7280;">Daftar seluruh individu yang tergabung dalam Kartu Keluarga ini</small>
                        </div>
                    </div>
                    <button type="button" id="btn-add-member" class="btn btn-outline btn-sm" style="border-radius: 20px; font-weight: 700; border-color: #7c3aed; color: #7c3aed; cursor: pointer;">
                        ➕ Tambah Anggota
                    </button>
                </div>

                <div id="members-container" style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                    <!-- Rows dynamically generated by family-repeater.js -->
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div style="text-align: right; margin-bottom: 3.5rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 1.1rem 2.8rem; font-size: 1.1rem; font-weight: 800; border-radius: 30px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.45); display: inline-flex; align-items: center; gap: 10px; cursor: pointer; border: none;">
                    <i class="fa-solid fa-floppy-disk"></i> <span>💾 Simpan & Daftarkan Data Keluarga</span>
                </button>
            </div>

        </form>
    </div>

    <!-- MODAL BANTUAN BUKA IZIN LOKASI GPS -->
    <div class="modal-backdrop" id="modal-gps-guide" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: #ffffff; border-radius: 20px; max-width: 520px; width: 100%; padding: 1.75rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3); border: 1.5px solid #e9d5ff; position: relative;">
            <button type="button" onclick="closeGpsModal()" style="position: absolute; top: 1rem; right: 1rem; background: #f1f5f9; border: none; width: 34px; height: 34px; border-radius: 50%; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #64748b;">✕</button>

            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="width: 44px; height: 44px; background: #fee2e2; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    🔒
                </div>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: #1e1b4b; margin: 0;">
                        Cara Mengaktifkan Izin Lokasi GPS
                    </h3>
                    <small style="color: #64748b;">Panduan membuka blokir izin lokasi di HP Android</small>
                </div>
            </div>

            <div style="font-size: 0.875rem; color: #334155; line-height: 1.55; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
                <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 12px; border-left: 4px solid #7c3aed;">
                    <strong>Langkah 1:</strong> Ketuk ikon <strong>Gembok (🔒)</strong> atau ikon <strong>Setelan Situs (ⓘ)</strong> di sebelah kiri alamat URL browser (paling atas layar HP).
                </div>
                <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 12px; border-left: 4px solid #7c3aed;">
                    <strong>Langkah 2:</strong> Pilih menu <strong>"Izin" (Permissions)</strong> &rarr; pilih <strong>"Lokasi" (Location)</strong>.
                </div>
                <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 12px; border-left: 4px solid #7c3aed;">
                    <strong>Langkah 3:</strong> Ubah pilihan menjadi <strong>"Izinkan" (Allow)</strong>, lalu muat ulang (refresh) halaman formulir ini.
                </div>

                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.75rem 1rem; border-radius: 12px; color: #065f46;">
                    💡 <strong>Solusi Cepat Tanpa GPS:</strong><br>
                    Anda <strong>TIDAK WAJIB</strong> menggunakan GPS otomatis! Anda cukup <strong>menggeser pin merah di peta</strong> tepat di atas atap rumah Anda atau ketik nama jalan di kotak pencarian. Koordinat & alamat akan langsung tersimpan secara akurat.
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" onclick="closeGpsModal()" class="btn btn-primary" style="border-radius: 12px; padding: 0.6rem 1.5rem; font-weight: 700; width: 100%;">
                    ✓ Saya Mengerti, Lanjutkan Pilih di Peta
                </button>
            </div>
        </div>
    </div>

    <!-- Leaflet JS & Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../assets/js/map-picker.js"></script>
    <script src="../assets/js/family-repeater.js"></script>
</body>
</html>
