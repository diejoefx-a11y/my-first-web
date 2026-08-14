<?php
/**
 * FORMULIR PEMBARUAN / EDIT DATA KELUARGA & TITIK RUMAH JEMAAT
 * Persekutuan Kaum Bapak (PKB)
 */

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Makassar');

$db = get_db();
$errors = [];
$successMsg = '';
$family = null;
$members = [];

// Step 1: Handle Search / Lookup by No. KK
$lookup_kk = preg_replace('/[^0-9]/', '', $_GET['kk'] ?? ($_POST['lookup_kk'] ?? ''));

if (!empty($lookup_kk)) {
    $stmt = $db->prepare("SELECT * FROM families WHERE no_kk = ? LIMIT 1");
    $stmt->execute([$lookup_kk]);
    $found = $stmt->fetch();

    if ($found) {
        $family = $found;
        $stmtM = $db->prepare("SELECT * FROM family_members WHERE family_id = ? ORDER BY id ASC");
        $stmtM->execute([$family['id']]);
        $members = $stmtM->fetchAll();
    } else {
        $errors[] = "Data keluarga dengan Nomor KK <strong>$lookup_kk</strong> tidak ditemukan di database. Pastikan nomor KK sudah tepat atau daftarkan baru.";
    }
}

// Step 2: Handle POST Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_data') {
    $family_id = intval($_POST['family_id'] ?? 0);
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
    $latitude = clean($_POST['latitude'] ?? '-5.147665');
    $longitude = clean($_POST['longitude'] ?? '119.432731');
    $jumlah_tanggungan = intval($_POST['jumlah_tanggungan'] ?? 0);
    $postMembers = $_POST['members'] ?? [];

    if (empty($no_kk) || strlen($no_kk) < 10) {
        $errors[] = "Nomor Kartu Keluarga wajib diisi (minimal 10-16 digit).";
    }
    if (empty($nama_kepala)) {
        $errors[] = "Nama Kepala Keluarga wajib diisi.";
    }
    if (empty($no_hp)) {
        $errors[] = "Nomor WhatsApp aktif wajib diisi.";
    }
    if (empty($alamat_lengkap)) {
        $errors[] = "Alamat lengkap wajib diisi.";
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    // Handle Foto Keluarga
    $foto_keluarga_name = $family['foto_keluarga'] ?? null;
    if (isset($_FILES['foto_keluarga']) && $_FILES['foto_keluarga']['error'] === UPLOAD_ERR_OK) {
        $fileExt = strtolower(pathinfo($_FILES['foto_keluarga']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedExts) && $_FILES['foto_keluarga']['size'] <= 5 * 1024 * 1024) {
            $foto_keluarga_name = 'keluarga_' . time() . '_' . uniqid() . '.' . $fileExt;
            move_uploaded_file($_FILES['foto_keluarga']['tmp_name'], $uploadDir . $foto_keluarga_name);
        }
    }

    // Handle Foto Rumah
    $foto_rumah_name = $family['foto_rumah'] ?? null;
    if (isset($_FILES['foto_rumah']) && $_FILES['foto_rumah']['error'] === UPLOAD_ERR_OK) {
        $fileExt = strtolower(pathinfo($_FILES['foto_rumah']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedExts) && $_FILES['foto_rumah']['size'] <= 5 * 1024 * 1024) {
            $foto_rumah_name = 'rumah_' . time() . '_' . uniqid() . '.' . $fileExt;
            move_uploaded_file($_FILES['foto_rumah']['tmp_name'], $uploadDir . $foto_rumah_name);
        }
    }

    if (empty($errors) && $family_id > 0) {
        try {
            $db->beginTransaction();

            $stmtUpdate = $db->prepare("
                UPDATE families SET
                    no_kk = ?, nik_kepala = ?, nama_kepala = ?, kelompok_id = ?, no_hp = ?,
                    rt = ?, rw = ?, kelurahan = ?, kecamatan = ?,
                    alamat_lengkap = ?, latitude = ?, longitude = ?,
                    jumlah_tanggungan = ?, foto_rumah = ?, foto_keluarga = ?
                WHERE id = ?
            ");

            $stmtUpdate->execute([
                $no_kk, $nik_kepala, $nama_kepala, $kelompok_id, $no_hp,
                $rt, $rw, $kelurahan, $kecamatan,
                $alamat_lengkap, $latitude, $longitude,
                $jumlah_tanggungan, $foto_rumah_name, $foto_keluarga_name, $family_id
            ]);

            // Re-sync family members
            $db->prepare("DELETE FROM family_members WHERE family_id = ?")->execute([$family_id]);

            $stmtMember = $db->prepare("
                INSERT INTO family_members (
                    family_id, nik, nama_lengkap, hubungan_keluarga, jenis_kelamin,
                    tempat_lahir, tanggal_lahir
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?
                )
            ");

            $hasHead = false;
            if (!empty($postMembers) && is_array($postMembers)) {
                foreach ($postMembers as $m) {
                    $m_nama = clean($m['nama_lengkap'] ?? '');
                    $m_nik = preg_replace('/[^0-9]/', '', $m['nik'] ?? '');
                    $m_hubungan = clean($m['hubungan_keluarga'] ?? 'Anggota');
                    $m_jk = clean($m['jenis_kelamin'] ?? 'L');
                    $m_tempat = clean($m['tempat_lahir'] ?? '');
                    $m_tgl = !empty($m['tanggal_lahir']) ? $m['tanggal_lahir'] : null;

                    if (!empty($m_nama)) {
                        if (empty($m_nik)) $m_nik = $nik_kepala;
                        if ($m_hubungan === 'Kepala Keluarga') $hasHead = true;

                        $stmtMember->execute([
                            $family_id, $m_nik, $m_nama, $m_hubungan, $m_jk,
                            $m_tempat, $m_tgl
                        ]);
                    }
                }
            }

            if (!$hasHead) {
                $stmtMember->execute([
                    $family_id, $nik_kepala, $nama_kepala, 'Kepala Keluarga', 'L',
                    'Makassar', null
                ]);
            }

            $db->commit();

            header("Location: ../sukses.php?id=" . $family_id . "&kk=" . urlencode($no_kk));
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

$groupsList = $db->query("SELECT id, nomor_kelompok, nama_kelompok, nama_ketua FROM `groups` ORDER BY nomor_kelompok ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perbarui / Edit Data KK & Titik Rumah - Persekutuan Kaum Bapak (PKB)</title>
    
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
            background: linear-gradient(135deg, #1e1b4b 0%, #311042 50%, #4c1d95 100%);
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

        /* Interactive Members Table Styling */
        .table-custom-members {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .table-custom-members th {
            padding: 0.75rem 0.65rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .table-custom-members td {
            padding: 0.65rem 0.65rem;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .table-custom-members tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .table-custom-members .form-control-sm {
            padding: 0.45rem 0.6rem;
            font-size: 0.825rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            width: 100%;
            box-sizing: border-box;
        }
        .table-custom-members .form-control-sm:focus {
            border-color: #7c3aed;
            outline: none;
            box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.15);
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header class="app-header">
        <div class="container">
            <a href="../index.php" class="btn-back-home">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Portal Warta
            </a>
            <div>
                <span class="badge-pill-church">
                    <i class="fa-solid fa-church"></i> Persekutuan Kaum Bapak (PKB)
                </span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; font-weight: 800; margin-bottom: 0.35rem;">
                ✏️ Perbarui / Edit Data Keluarga & Titik Rumah Jemaat
            </h1>
            <p style="color: #ddd6fe; font-size: 0.95rem; max-width: 800px; line-height: 1.5;">
                Masukkan <strong>Nomor Kartu Keluarga (KK)</strong> yang telah terdaftar untuk memuat dan memperbarui identitas anggota keluarga, foto, atau memindahkan titik koordinat rumah Anda.
            </p>
        </div>
    </header>

    <div class="container main-wrapper" style="margin-top: 1.75rem;">

        <!-- SEARCH / LOOKUP CARD -->
        <div class="card" style="box-shadow: 0 6px 20px rgba(0,0,0,0.07); border-radius: 18px; border: 1.5px solid #e9d5ff; margin-bottom: 1.75rem; background: #ffffff;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                <div style="width: 44px; height: 44px; background: #ede9fe; color: #6d28d9; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    🔍
                </div>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b; margin: 0;">Cari Data KK Terdaftar</h3>
                    <small style="color: #6b7280;">Ketik nomor Kartu Keluarga Anda untuk membuka formulir pembaruan data</small>
                </div>
            </div>

            <form action="" method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 260px;">
                    <input type="text" name="kk" class="form-control" maxlength="16" placeholder="Masukkan 16 digit Nomor KK..." value="<?= htmlspecialchars($lookup_kk) ?>" required style="font-size: 1rem; padding: 0.75rem 1rem;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem; font-weight: 800; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #6d28d9); display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari Data KK
                </button>
            </form>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: #fee2e2; border: 1.5px solid #ef4444; border-radius: 14px; padding: 1.25rem;">
                <div style="color: #991b1b;">
                    <strong><i class="fa-solid fa-triangle-exclamation"></i> Perhatian:</strong>
                    <ul style="margin-left: 1.5rem; margin-top: 0.5rem; line-height: 1.6;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- FORM EDIT DATA (HANYA MUNCUL JIKA DATA KELUARGA DITEMUKAN) -->
        <?php if ($family): ?>

            <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.5rem;">✅</span>
                    <div>
                        <strong style="color: #065f46; font-size: 1rem;">Data Ditemukan: <?= htmlspecialchars($family['nama_kepala']) ?> (KK: <?= htmlspecialchars($family['no_kk']) ?>)</strong>
                        <div style="color: #047857; font-size: 0.85rem;">Status saat ini: <span style="font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($family['status_verifikasi']) ?></span></div>
                    </div>
                </div>
                <a href="edit_data.php" style="color: #059669; font-weight: 700; font-size: 0.85rem; text-decoration: underline;">Cari KK Lain</a>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" id="form-keluarga">
                <input type="hidden" name="action" value="update_data">
                <input type="hidden" name="family_id" value="<?= $family['id'] ?>">

                <!-- 1. DATA POKOK KEPALA KELUARGA -->
                <div class="card" style="box-shadow: 0 4px 18px rgba(0,0,0,0.06); border-radius: 16px; border: 1.5px solid #e9d5ff;">
                    <div class="card-title-section">
                        <div class="card-icon" style="background: #ede9fe; color: #6d28d9; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            👤
                        </div>
                        <div>
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">1. Data Pokok Kepala Keluarga</h3>
                            <small style="color: #6b7280;">Informasi identitas kepala keluarga, kontak, dan foto keluarga</small>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="no_kk">Nomor Kartu Keluarga (KK) <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="no_kk" name="no_kk" class="form-control" maxlength="16" value="<?= htmlspecialchars($family['no_kk']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="nik_kepala">NIK Kepala Keluarga <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="nik_kepala" name="nik_kepala" class="form-control" maxlength="16" value="<?= htmlspecialchars($family['nik_kepala']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="nama_kepala">Nama Lengkap Kepala Keluarga <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="nama_kepala" name="nama_kepala" class="form-control" value="<?= htmlspecialchars($family['nama_kepala']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="kelompok_id">Kelompok Pelayanan Domisili (1 - 14) <span class="required" style="color:#ef4444;">*</span></label>
                            <select id="kelompok_id" name="kelompok_id" class="form-control" required style="font-weight: 600; color: #5b21b6;">
                                <option value="">-- Pilih Kelompok Binaan --</option>
                                <?php foreach ($groupsList as $grp): ?>
                                    <option value="<?= $grp['id'] ?>" <?= $family['kelompok_id'] == $grp['id'] ? 'selected' : '' ?>>
                                        🏷️ <?= htmlspecialchars($grp['nama_kelompok']) ?><?= !empty($grp['nama_ketua']) ? ' (Ketua: ' . htmlspecialchars($grp['nama_ketua']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="no_hp">No. WhatsApp / HP Aktif <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="no_hp" name="no_hp" class="form-control" value="<?= htmlspecialchars($family['no_hp']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="jumlah_tanggungan">Jumlah Tanggungan Keluarga</label>
                            <input type="number" id="jumlah_tanggungan" name="jumlah_tanggungan" class="form-control" min="0" max="20" value="<?= htmlspecialchars($family['jumlah_tanggungan']) ?>">
                        </div>

                        <!-- FOTO KELUARGA (OPSIONAL) -->
                        <div class="form-group" style="grid-column: 1 / -1; background: #faf5ff; border: 1.5px dashed #c084fc; border-radius: 12px; padding: 1rem 1.25rem;">
                            <label for="foto_keluarga" style="color: #6b21a8; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <span>👨‍👩‍👧‍👦</span> Ganti / Unggah Foto Bersama Keluarga <span style="font-weight: 400; color: #9333ea; font-size: 0.8rem;">(Opsional)</span>
                            </label>
                            <input type="file" id="foto_keluarga" name="foto_keluarga" class="form-control" accept="image/jpeg,image/png,image/webp" style="background: #ffffff; margin-top: 0.35rem;">
                            
                            <?php if (!empty($family['foto_keluarga'])): ?>
                                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #6b21a8;">
                                    Foto saat ini terpasang. <a href="../uploads/<?= htmlspecialchars($family['foto_keluarga']) ?>" target="_blank" style="color: #7c3aed; font-weight: 700; text-decoration: underline;">Lihat Foto Keluarga</a>
                                </div>
                            <?php endif; ?>

                            <div id="foto-keluarga-preview" style="margin-top: 0.75rem; display: none;">
                                <img src="" alt="Pratinjau Foto Keluarga" style="max-height: 200px; border-radius: 10px; border: 2px solid #a855f7; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. ALAMAT & PETA LOKASI RUMAH -->
                <div class="card" style="box-shadow: 0 4px 18px rgba(0,0,0,0.06); border-radius: 16px; border: 1.5px solid #e9d5ff;">
                    <div class="card-title-section">
                        <div class="card-icon" style="background: #e0f2fe; color: #0284c7; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            📍
                        </div>
                        <div>
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">2. Alamat & Titik Koordinat Rumah</h3>
                            <small style="color: #6b7280;">Geser pin merah jika ingin memindahkan atau menyesuaikan posisi atap rumah</small>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="rt">RT</label>
                            <input type="text" id="rt" name="rt" class="form-control" value="<?= htmlspecialchars($family['rt']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="rw">RW</label>
                            <input type="text" id="rw" name="rw" class="form-control" value="<?= htmlspecialchars($family['rw']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="kelurahan">Kelurahan / Desa</label>
                            <input type="text" id="kelurahan" name="kelurahan" class="form-control" value="<?= htmlspecialchars($family['kelurahan']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="kecamatan">Kecamatan</label>
                            <input type="text" id="kecamatan" name="kecamatan" class="form-control" value="<?= htmlspecialchars($family['kecamatan']) ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 0.5rem;">
                        <label for="alamat_lengkap">Alamat Lengkap <span class="required" style="color:#ef4444;">*</span></label>
                        <textarea id="alamat_lengkap" name="alamat_lengkap" class="form-control" required><?= htmlspecialchars($family['alamat_lengkap']) ?></textarea>
                    </div>

                    <!-- LEAFLET MAP -->
                    <div class="form-group" style="margin-top: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap; gap: 0.5rem;">
                            <label style="font-weight: 700; color: #1e1b4b;">Peta Titik Koordinat Rumah</label>
                            <button type="button" id="btn-gps" class="btn btn-sm" style="font-size: 0.85rem; font-weight: 800; background: #10b981; color: #ffffff; border-radius: 20px; padding: 0.5rem 1.2rem; border: none; cursor: pointer;">
                                <span>📍</span> <span>Gunakan Lokasi GPS Saya</span>
                            </button>
                        </div>

                        <div class="map-search-bar" style="margin-bottom: 0.75rem; display: flex; gap: 0.5rem;">
                            <input type="text" id="map-search-input" class="form-control" placeholder="Cari nama jalan / kelurahan terdekat..." style="font-size: 0.85rem;">
                            <button type="button" id="btn-search-map" class="btn btn-primary btn-sm" style="padding: 0 1.25rem;">Cari</button>
                        </div>

                        <div class="map-wrapper" style="border-radius: 14px; overflow: hidden; border: 1.5px solid #cbd5e1;">
                            <div id="map" style="height: 380px; width: 100%;"></div>
                        </div>

                        <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($family['latitude']) ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($family['longitude']) ?>">

                        <div class="map-coords-card" style="margin-top: 0.75rem; background: #f5f3ff; border: 1px solid #ddd6fe; padding: 0.65rem 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.85rem; color: #5b21b6; font-weight: 600;">Koordinat Terpilih:</span>
                            <span class="coords-badge" id="display-coords" style="font-family: monospace; font-weight: 800; background: #7c3aed; color: #fff; padding: 4px 12px; border-radius: 6px;">
                                <?= htmlspecialchars($family['latitude']) ?>, <?= htmlspecialchars($family['longitude']) ?>
                            </span>
                        </div>
                        <div class="map-hint" id="gps-status" style="margin-top: 0.5rem; font-size: 0.8rem; color: #6b7280;">
                            * Geser pin merah untuk memindahkan titik atap rumah Anda.
                        </div>
                    </div>

                    <!-- Foto Rumah -->
                    <div class="form-group" style="margin-top: 1.25rem;">
                        <label for="foto_rumah">Ganti / Unggah Foto Rumah (Opsional)</label>
                        <input type="file" id="foto_rumah" name="foto_rumah" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($family['foto_rumah'])): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.85rem;">
                                Foto rumah saat ini: <a href="../uploads/<?= htmlspecialchars($family['foto_rumah']) ?>" target="_blank" style="color: #0284c7; font-weight: 700; text-decoration: underline;">Lihat Foto Rumah</a>
                            </div>
                        <?php endif; ?>
                        <div id="foto-preview" style="margin-top: 0.75rem; display: none;">
                            <img src="" alt="Pratinjau Foto Rumah" style="max-height: 180px; border-radius: 10px; border: 1px solid #cbd5e1; object-fit: cover;">
                        </div>
                    </div>
                </div>

                <!-- 3. DETAIL ANGGOTA KELUARGA (TABEL ANGGOTA KELUARGA INTERAKTIF) -->
                <div class="card" style="box-shadow: 0 4px 18px rgba(0,0,0,0.06); border-radius: 16px; border: 1.5px solid #e9d5ff;">
                    <div class="card-title-section" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="card-icon" style="background: #fdf2f8; color: #db2777; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                                👨‍👩‍👧‍👦
                            </div>
                            <div>
                                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">3. Tabel Susunan Anggota Keluarga Terdaftar</h3>
                                <small style="color: #6b7280;">Daftar seluruh individu yang tercatat dalam Kartu Keluarga ini (<span id="count-members-badge" style="font-weight:700; color:#7c3aed;"><?= count($members) ?></span> Orang)</small>
                            </div>
                        </div>
                        <button type="button" id="btn-add-member-table" class="btn btn-outline btn-sm" style="border-radius: 20px; font-weight: 700; border-color: #7c3aed; color: #7c3aed; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            ➕ Tambah Baris Anggota
                        </button>
                    </div>

                    <!-- TABEL RESPONSIVE ANGGOTA KELUARGA -->
                    <div style="overflow-x: auto; margin-top: 1.25rem; border-radius: 14px; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                        <table class="table-custom-members" id="table-members" style="min-width: 820px;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); color: #ffffff;">
                                    <th style="width: 45px; text-align: center; border-top-left-radius: 12px;">No</th>
                                    <th style="min-width: 200px;">Nama Lengkap <span style="color:#f87171;">*</span></th>
                                    <th style="width: 170px;">NIK (16 Digit)</th>
                                    <th style="width: 160px;">Hubungan <span style="color:#f87171;">*</span></th>
                                    <th style="width: 90px;">L/P</th>
                                    <th style="min-width: 200px;">Tempat & Tanggal Lahir</th>
                                    <th style="width: 65px; text-align: center; border-top-right-radius: 12px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="members-table-body">
                                <?php 
                                $rowIdx = 0;
                                foreach ($members as $m): 
                                    $isFirst = ($rowIdx === 0);
                                ?>
                                    <tr class="member-table-row" id="table-row-<?= $rowIdx ?>" data-index="<?= $rowIdx ?>">
                                        <td style="text-align: center; font-weight: 800; color: #7c3aed;" class="row-number">
                                            <?= $rowIdx + 1 ?>
                                        </td>
                                        <td>
                                            <input type="text" name="members[<?= $rowIdx ?>][nama_lengkap]" class="form-control-sm input-nama" value="<?= htmlspecialchars($m['nama_lengkap']) ?>" placeholder="Nama lengkap..." required>
                                            <?php if ($isFirst): ?>
                                                <small style="color: #6d28d9; font-weight: 700; font-size: 0.72rem; display: block; margin-top: 2px;">(Kepala Keluarga)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="text" name="members[<?= $rowIdx ?>][nik]" class="form-control-sm input-nik" maxlength="16" value="<?= htmlspecialchars($m['nik']) ?>" placeholder="16 digit NIK...">
                                        </td>
                                        <td>
                                            <select name="members[<?= $rowIdx ?>][hubungan_keluarga]" class="form-control-sm" required style="font-weight: 600;">
                                                <option value="Kepala Keluarga" <?= $m['hubungan_keluarga'] === 'Kepala Keluarga' ? 'selected' : '' ?>>Kepala Keluarga</option>
                                                <option value="Istri" <?= $m['hubungan_keluarga'] === 'Istri' ? 'selected' : '' ?>>Istri</option>
                                                <option value="Anak" <?= $m['hubungan_keluarga'] === 'Anak' ? 'selected' : '' ?>>Anak</option>
                                                <option value="Orang Tua" <?= $m['hubungan_keluarga'] === 'Orang Tua' ? 'selected' : '' ?>>Orang Tua</option>
                                                <option value="Famili Lain" <?= $m['hubungan_keluarga'] === 'Famili Lain' ? 'selected' : '' ?>>Famili Lain</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="members[<?= $rowIdx ?>][jenis_kelamin]" class="form-control-sm">
                                                <option value="L" <?= $m['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>L</option>
                                                <option value="P" <?= $m['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>P</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="members[<?= $rowIdx ?>][tempat_lahir]" class="form-control-sm" value="<?= htmlspecialchars($m['tempat_lahir'] ?? '') ?>" placeholder="Kota lahir..." style="margin-bottom: 4px;">
                                            <input type="date" name="members[<?= $rowIdx ?>][tanggal_lahir]" class="form-control-sm" value="<?= htmlspecialchars($m['tanggal_lahir'] ?? '') ?>">
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if (!$isFirst): ?>
                                                <button type="button" class="btn-remove-table-row" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; border-radius:8px; padding:4px 8px; font-weight:700; font-size:0.75rem; cursor:pointer;" title="Hapus baris">✕</button>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-size:0.75rem;">Utama</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    $rowIdx++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SUBMIT BUTTON -->
                <div style="text-align: right; margin-bottom: 3.5rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 1.1rem 2.8rem; font-size: 1.1rem; font-weight: 800; border-radius: 30px; background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); box-shadow: 0 8px 25px rgba(124, 58, 237, 0.45); display: inline-flex; align-items: center; gap: 10px; cursor: pointer; border: none;">
                        <i class="fa-solid fa-floppy-disk"></i> <span>💾 Simpan Pembaruan Data Jemaat</span>
                    </button>
                </div>

            </form>

        <?php endif; ?>

    </div>

    <!-- Leaflet JS & Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../assets/js/map-picker.js"></script>

    <!-- Table Dynamic Row Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tableBody = document.getElementById('members-table-body');
        const btnAddRow = document.getElementById('btn-add-member-table');
        const countBadge = document.getElementById('count-members-badge');
        let nextIndex = <?= count($members) ?>;

        function updateTableNumbers() {
            if (!tableBody) return;
            const rows = tableBody.querySelectorAll('.member-table-row');
            rows.forEach((row, idx) => {
                const numEl = row.querySelector('.row-number');
                if (numEl) numEl.textContent = idx + 1;
            });
            if (countBadge) countBadge.textContent = rows.length;
        }

        if (btnAddRow && tableBody) {
            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'member-table-row';
                tr.id = 'table-row-' + nextIndex;
                tr.dataset.index = nextIndex;

                tr.innerHTML = `
                    <td style="text-align: center; font-weight: 800; color: #7c3aed;" class="row-number">${tableBody.children.length + 1}</td>
                    <td>
                        <input type="text" name="members[${nextIndex}][nama_lengkap]" class="form-control-sm input-nama" placeholder="Nama lengkap..." required>
                    </td>
                    <td>
                        <input type="text" name="members[${nextIndex}][nik]" class="form-control-sm input-nik" maxlength="16" placeholder="16 digit NIK...">
                    </td>
                    <td>
                        <select name="members[${nextIndex}][hubungan_keluarga]" class="form-control-sm" required style="font-weight: 600;">
                            <option value="Istri">Istri</option>
                            <option value="Anak" selected>Anak</option>
                            <option value="Orang Tua">Orang Tua</option>
                            <option value="Famili Lain">Famili Lain</option>
                            <option value="Kepala Keluarga">Kepala Keluarga</option>
                        </select>
                    </td>
                    <td>
                        <select name="members[${nextIndex}][jenis_kelamin]" class="form-control-sm">
                            <option value="L">L</option>
                            <option value="P">P</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="members[${nextIndex}][tempat_lahir]" class="form-control-sm" placeholder="Kota lahir..." style="margin-bottom: 4px;">
                        <input type="date" name="members[${nextIndex}][tanggal_lahir]" class="form-control-sm">
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn-remove-table-row" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; border-radius:8px; padding:4px 8px; font-weight:700; font-size:0.75rem; cursor:pointer;" title="Hapus baris">✕</button>
                    </td>
                `;

                tableBody.appendChild(tr);
                nextIndex++;
                updateTableNumbers();
            });
        }

        // Delegate remove row click
        if (tableBody) {
            tableBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-remove-table-row')) {
                    const tr = e.target.closest('tr');
                    if (tr) {
                        tr.remove();
                        updateTableNumbers();
                    }
                }
            });
        }

        // Photo preview handling
        const fotoRumahIn = document.getElementById('foto_rumah');
        const fotoRumahPrev = document.getElementById('foto-preview');
        if (fotoRumahIn && fotoRumahPrev) {
            fotoRumahIn.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = fotoRumahPrev.querySelector('img');
                        if (img) img.src = e.target.result;
                        fotoRumahPrev.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    fotoRumahPrev.style.display = 'none';
                }
            });
        }

        const fotoKelIn = document.getElementById('foto_keluarga');
        const fotoKelPrev = document.getElementById('foto-keluarga-preview');
        if (fotoKelIn && fotoKelPrev) {
            fotoKelIn.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = fotoKelPrev.querySelector('img');
                        if (img) img.src = e.target.result;
                        fotoKelPrev.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    fotoKelPrev.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>
