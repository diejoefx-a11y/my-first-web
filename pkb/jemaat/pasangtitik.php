<?php
/**
 * FORMULIR PENDAFTARAN KARTU KELUARGA & PEMETAAN TITIK RUMAH SPASIAL
 * Persekutuan Jemaat Kristiani & Persekutuan Kaum Bapak (PKB)
 */

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Makassar');

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
    $rt = clean($_POST['rt'] ?? '');
    $rw = clean($_POST['rw'] ?? '');
    $kelurahan = clean($_POST['kelurahan'] ?? '');
    $kecamatan = clean($_POST['kecamatan'] ?? '');
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

    if (empty($errors)) {
        try {
            // Check for duplicate No. KK
            $stmtCheck = $db->prepare("SELECT id FROM families WHERE no_kk = ? LIMIT 1");
            $stmtCheck->execute([$no_kk]);
            if ($stmtCheck->fetch()) {
                throw new Exception("Nomor KK <strong>$no_kk</strong> sudah terdaftar di sistem. Jika ingin memperbarui data, silakan gunakan menu <a href='edit_data.php?kk=" . urlencode($no_kk) . "' style='color:#7c3aed; font-weight:700; text-decoration:underline;'>Edit Data Keluarga</a>.");
            }

            // Handle Foto Rumah Upload
            $foto_rumah_name = null;
            if (isset($_FILES['foto_rumah']) && $_FILES['foto_rumah']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileTmp = $_FILES['foto_rumah']['tmp_name'];
                $fileExt = strtolower(pathinfo($_FILES['foto_rumah']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($fileExt, $allowed)) {
                    $foto_rumah_name = 'rumah_' . time() . '_' . uniqid() . '.' . $fileExt;
                    move_uploaded_file($fileTmp, $uploadDir . $foto_rumah_name);
                }
            }

            // Handle Foto Keluarga Upload
            $foto_keluarga_name = null;
            if (isset($_FILES['foto_keluarga']) && $_FILES['foto_keluarga']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileTmp = $_FILES['foto_keluarga']['tmp_name'];
                $fileExt = strtolower(pathinfo($_FILES['foto_keluarga']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($fileExt, $allowed)) {
                    $foto_keluarga_name = 'keluarga_' . time() . '_' . uniqid() . '.' . $fileExt;
                    move_uploaded_file($fileTmp, $uploadDir . $foto_keluarga_name);
                }
            }

            $db->beginTransaction();

            // 3. Insert into families table
            $stmt = $db->prepare("
                INSERT INTO families (
                    no_kk, nik_kepala, nama_kepala, kelompok_id, no_hp, 
                    rt, rw, kelurahan, kecamatan, alamat_lengkap, 
                    latitude, longitude, jumlah_tanggungan, foto_rumah, foto_keluarga, status_verifikasi
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 'Terverifikasi'
                )
            ");
            
            $stmt->execute([
                $no_kk, $nik_kepala, $nama_kepala, $kelompok_id, $no_hp,
                $rt, $rw, $kelurahan, $kecamatan, $alamat_lengkap,
                $latitude, $longitude, $jumlah_tanggungan, $foto_rumah_name, $foto_keluarga_name
            ]);

            $familyId = $db->lastInsertId();

            // 4. Insert dynamic family members
            if (!empty($members) && is_array($members)) {
                $stmtMember = $db->prepare("
                    INSERT INTO family_members (
                        family_id, nik, nama_lengkap, hubungan_keluarga, 
                        jenis_kelamin, tempat_lahir, tanggal_lahir
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, ?
                    )
                ");

                foreach ($members as $m) {
                    $m_nama = clean($m['nama_lengkap'] ?? '');
                    if (empty($m_nama)) continue; // Skip blank member rows

                    $m_nik = preg_replace('/[^0-9]/', '', $m['nik'] ?? '');
                    $m_hubungan = clean($m['hubungan_keluarga'] ?? 'Anggota');
                    $m_gender = clean($m['jenis_kelamin'] ?? 'L');
                    $m_tempat = clean($m['tempat_lahir'] ?? '');
                    $m_tgl = !empty($m['tanggal_lahir']) ? clean($m['tanggal_lahir']) : null;

                    $stmtMember->execute([
                        $familyId, $m_nik, $m_nama, $m_hubungan,
                        $m_gender, $m_tempat, $m_tgl
                    ]);
                }
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
    <title>Formulir Aplikasi Sensus Data PKBGT</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Google Fonts & Custom CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">

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
        /* Dynamic Leaf Green Theme for Back Button */
        .btn-back-leaf-green {
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            background: linear-gradient(135deg, #15803d 0%, #16a34a 40%, #22c55e 100%) !important;
            border: 1.5px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.88rem !important;
            font-weight: 700 !important;
            padding: 0.55rem 1.35rem !important;
            border-radius: 25px !important;
            text-decoration: none !important;
            margin-bottom: 1.15rem !important;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.35), 0 0 12px rgba(74, 222, 128, 0.2) !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-back-leaf-green::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
        }
        .btn-back-leaf-green:hover {
            color: #ffffff !important;
            transform: translateX(-3px) scale(1.02) !important;
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.55), 0 0 20px rgba(74, 222, 128, 0.35) !important;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 40%, #4ade80 100%) !important;
            border-color: #bbf7d0 !important;
        }
        .btn-back-leaf-green:hover::after {
            left: 100%;
        }

        /* Dynamic Leaf Green Theme for Edit Shortcut Button */
        .btn-edit-shortcut-leaf {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 40%, #22c55e 100%) !important;
            border: 1.5px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.88rem !important;
            font-weight: 800 !important;
            padding: 8px 18px !important;
            border-radius: 20px !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.35), 0 0 10px rgba(74, 222, 128, 0.2) !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-edit-shortcut-leaf::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
        }
        .btn-edit-shortcut-leaf:hover {
            color: #ffffff !important;
            transform: translateY(-2px) scale(1.02) !important;
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.55), 0 0 18px rgba(74, 222, 128, 0.35) !important;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 40%, #4ade80 100%) !important;
            border-color: #bbf7d0 !important;
        }
        .btn-edit-shortcut-leaf:hover::after {
            left: 100%;
        }

        /* Dynamic Purple Gradient Theme for Data Pokok Card */
        .card-purple-dynamic {
            background: linear-gradient(135deg, rgba(28, 18, 62, 0.96) 0%, rgba(49, 16, 102, 0.92) 50%, rgba(18, 12, 42, 0.98) 100%) !important;
            border: 1.5px solid rgba(167, 139, 250, 0.4) !important;
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            box-shadow: 0 16px 40px rgba(46, 16, 101, 0.35), 0 0 25px rgba(139, 92, 246, 0.15) !important;
            backdrop-filter: blur(16px);
            color: #ffffff;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-purple-dynamic::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.22) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .card-purple-dynamic .card-title-section {
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            padding-bottom: 1.15rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .card-purple-dynamic .card-icon-purple {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.4), rgba(124, 58, 237, 0.35));
            border: 1.5px solid rgba(196, 181, 253, 0.5);
            border-radius: 14px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #f3e8ff;
            box-shadow: 0 4px 15px rgba(168, 85, 247, 0.35);
        }
        .card-purple-dynamic .card-title-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.3px;
        }
        .card-purple-dynamic .card-subtitle-text {
            color: #ddd6fe;
            font-size: 0.85rem;
            margin-top: 3px;
            display: block;
        }
        .card-purple-dynamic label {
            color: #ede9fe !important;
            font-weight: 700 !important;
            font-size: 0.88rem !important;
            margin-bottom: 0.45rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .card-purple-dynamic .form-control {
            background: rgba(15, 12, 35, 0.65) !important;
            border: 1.5px solid rgba(167, 139, 250, 0.35) !important;
            color: #ffffff !important;
            border-radius: 12px !important;
            padding: 11px 15px !important;
            font-size: 0.92rem !important;
            font-family: 'Inter', sans-serif !important;
            transition: all 0.25s ease !important;
        }
        .card-purple-dynamic .form-control::placeholder {
            color: #94a3b8 !important;
        }
        .card-purple-dynamic .form-control:focus {
            border-color: #c084fc !important;
            box-shadow: 0 0 16px rgba(192, 132, 252, 0.45) !important;
            outline: none !important;
            background: rgba(15, 12, 35, 0.88) !important;
        }
        .card-purple-dynamic select.form-control option {
            background: #1e123d !important;
            color: #ffffff !important;
            padding: 8px !important;
        }
        .card-purple-dynamic .foto-keluarga-box {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.16) 0%, rgba(192, 132, 252, 0.08) 100%) !important;
            border: 1.5px dashed rgba(196, 181, 253, 0.5) !important;
            border-radius: 14px !important;
            padding: 1.15rem 1.35rem !important;
            transition: border-color 0.25s ease;
        }
        .card-purple-dynamic .foto-keluarga-box:hover {
            border-color: #c084fc !important;
        }

        /* Dynamic Orange Gradient Theme for Alamat & Peta Card */
        .card-orange-dynamic {
            background: linear-gradient(135deg, rgba(45, 18, 8, 0.96) 0%, rgba(67, 24, 6, 0.92) 50%, rgba(26, 12, 6, 0.98) 100%) !important;
            border: 1.5px solid rgba(251, 146, 60, 0.45) !important;
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            box-shadow: 0 16px 40px rgba(194, 65, 12, 0.35), 0 0 25px rgba(249, 115, 22, 0.15) !important;
            backdrop-filter: blur(16px);
            color: #ffffff;
            position: relative;
            overflow: hidden;
            margin-top: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-orange-dynamic::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.22) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .card-orange-dynamic .card-title-section {
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            padding-bottom: 1.15rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .card-orange-dynamic .card-icon-orange {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.45), rgba(234, 88, 12, 0.35));
            border: 1.5px solid rgba(253, 186, 116, 0.5);
            border-radius: 14px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #ffedd5;
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.35);
        }
        .card-orange-dynamic .card-title-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.3px;
        }
        .card-orange-dynamic .card-subtitle-text {
            color: #fed7aa;
            font-size: 0.85rem;
            margin-top: 3px;
            display: block;
        }
        .card-orange-dynamic label {
            color: #ffedd5 !important;
            font-weight: 700 !important;
            font-size: 0.88rem !important;
            margin-bottom: 0.45rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .card-orange-dynamic .form-control {
            background: rgba(22, 10, 4, 0.65) !important;
            border: 1.5px solid rgba(251, 146, 60, 0.35) !important;
            color: #ffffff !important;
            border-radius: 12px !important;
            padding: 11px 15px !important;
            font-size: 0.92rem !important;
            font-family: 'Inter', sans-serif !important;
            transition: all 0.25s ease !important;
        }
        .card-orange-dynamic .form-control::placeholder {
            color: #94a3b8 !important;
        }
        .card-orange-dynamic .form-control:focus {
            border-color: #fb923c !important;
            box-shadow: 0 0 16px rgba(251, 146, 60, 0.45) !important;
            outline: none !important;
            background: rgba(22, 10, 4, 0.88) !important;
        }
        .card-orange-dynamic .foto-rumah-box {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.14) 0%, rgba(251, 146, 60, 0.08) 100%) !important;
            border: 1.5px dashed rgba(253, 186, 116, 0.5) !important;
            border-radius: 14px !important;
            padding: 1.15rem 1.35rem !important;
            transition: border-color 0.25s ease;
        }
        .card-orange-dynamic .foto-rumah-box:hover {
            border-color: #fb923c !important;
        }
        .card-orange-dynamic .gps-tips-orange {
            background: linear-gradient(135deg, rgba(234, 88, 12, 0.18) 0%, rgba(180, 83, 9, 0.15) 100%) !important;
            border: 1.5px solid rgba(251, 146, 60, 0.4) !important;
            border-radius: 14px !important;
            padding: 0.85rem 1rem !important;
            margin-bottom: 0.85rem !important;
            display: flex !important;
            align-items: flex-start !important;
            gap: 0.75rem !important;
            color: #fed7aa !important;
        }
        .card-orange-dynamic .coords-card-orange {
            background: rgba(25, 12, 6, 0.75) !important;
            border: 1px solid rgba(251, 146, 60, 0.35) !important;
            padding: 0.65rem 1rem !important;
            border-radius: 12px !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 0.5rem !important;
        }
        .card-orange-dynamic .coords-badge-orange {
            font-family: monospace !important;
            font-weight: 800 !important;
            background: linear-gradient(135deg, #f97316, #c2410c) !important;
            color: #fff !important;
            padding: 4px 12px !important;
            border-radius: 6px !important;
            font-size: 0.85rem !important;
            box-shadow: 0 2px 10px rgba(249, 115, 22, 0.4) !important;
        }

        /* Dynamic Yellow Bumblebee Gradient Theme for Susunan Anggota Keluarga Card */
        .card-bumblebee-dynamic {
            background: linear-gradient(135deg, rgba(38, 28, 6, 0.96) 0%, rgba(55, 40, 5, 0.92) 50%, rgba(20, 15, 4, 0.98) 100%) !important;
            border: 1.5px solid rgba(250, 204, 21, 0.45) !important;
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            box-shadow: 0 16px 40px rgba(161, 98, 7, 0.35), 0 0 25px rgba(234, 179, 8, 0.15) !important;
            backdrop-filter: blur(16px);
            color: #ffffff;
            position: relative;
            overflow: hidden;
            margin-top: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-bumblebee-dynamic::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(234, 179, 8, 0.22) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .card-bumblebee-dynamic .card-title-section {
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            padding-bottom: 1.15rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }
        .card-bumblebee-dynamic .card-icon-bumblebee {
            background: linear-gradient(135deg, rgba(250, 204, 21, 0.45), rgba(202, 138, 4, 0.35));
            border: 1.5px solid rgba(254, 240, 138, 0.55);
            border-radius: 14px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #fef08a;
            box-shadow: 0 4px 15px rgba(234, 179, 8, 0.35);
        }
        .card-bumblebee-dynamic .card-title-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.3px;
        }
        .card-bumblebee-dynamic .card-subtitle-text {
            color: #fef08a;
            font-size: 0.85rem;
            margin-top: 3px;
            display: block;
        }
        .card-bumblebee-dynamic .btn-add-member-bumblebee {
            background: linear-gradient(135deg, #facc15 0%, #eab308 50%, #ca8a04 100%) !important;
            color: #1e1b4b !important;
            border: 1.5px solid #fef08a !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 800 !important;
            font-size: 0.88rem !important;
            padding: 8px 18px !important;
            border-radius: 20px !important;
            box-shadow: 0 4px 15px rgba(234, 179, 8, 0.45) !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.25s ease !important;
        }
        .card-bumblebee-dynamic .btn-add-member-bumblebee:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 20px rgba(234, 179, 8, 0.65) !important;
            background: linear-gradient(135deg, #fde047 0%, #facc15 100%) !important;
        }
        .card-bumblebee-dynamic .member-card {
            background: rgba(26, 20, 5, 0.78) !important;
            border: 1.5px solid rgba(250, 204, 21, 0.35) !important;
            border-radius: 14px !important;
            padding: 1.25rem !important;
            margin-bottom: 0.75rem !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25) !important;
            transition: border-color 0.25s ease;
        }
        .card-bumblebee-dynamic .member-card:hover {
            border-color: #facc15 !important;
        }
        .card-bumblebee-dynamic .member-header {
            border-bottom: 1px solid rgba(250, 204, 21, 0.2) !important;
            padding-bottom: 0.6rem !important;
            margin-bottom: 0.85rem !important;
        }
        .card-bumblebee-dynamic .member-badge {
            font-weight: 800 !important;
            color: #fef08a !important;
            font-size: 0.9rem !important;
            background: rgba(234, 179, 8, 0.18) !important;
            border: 1px solid rgba(250, 204, 21, 0.4) !important;
            padding: 3px 10px !important;
            border-radius: 8px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .card-bumblebee-dynamic label {
            color: #fef9c3 !important;
            font-weight: 700 !important;
            font-size: 0.88rem !important;
            margin-bottom: 0.45rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .card-bumblebee-dynamic .form-control {
            background: rgba(16, 12, 4, 0.72) !important;
            border: 1.5px solid rgba(250, 204, 21, 0.35) !important;
            color: #ffffff !important;
            border-radius: 12px !important;
            padding: 11px 15px !important;
            font-size: 0.92rem !important;
            font-family: 'Inter', sans-serif !important;
            transition: all 0.25s ease !important;
        }
        .card-bumblebee-dynamic .form-control::placeholder {
            color: #94a3b8 !important;
        }
        .card-bumblebee-dynamic .form-control:focus {
            border-color: #facc15 !important;
            box-shadow: 0 0 16px rgba(250, 204, 21, 0.45) !important;
            outline: none !important;
            background: rgba(16, 12, 4, 0.92) !important;
        }
        .card-bumblebee-dynamic select.form-control option {
            background: #231b05 !important;
            color: #ffffff !important;
            padding: 8px !important;
        }

        /* =======================================================
           DYNAMIC MULTI-COLOR THEMES FOR EACH FAMILY MEMBER ROW
           ======================================================= */
        .member-card {
            border-radius: 16px !important;
            padding: 1.25rem !important;
            margin-bottom: 0.85rem !important;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.3s ease !important;
            position: relative;
        }
        .member-card .member-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.85rem;
            padding-bottom: 0.6rem;
        }
        .member-card .member-badge {
            font-weight: 800 !important;
            font-size: 0.88rem !important;
            padding: 4px 12px !important;
            border-radius: 8px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        /* 1. Theme Indigo / Sapphire (Member 1 - Kepala Keluarga) */
        .member-theme-indigo {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.92) 0%, rgba(30, 27, 75, 0.88) 100%) !important;
            border: 1.5px solid rgba(99, 102, 241, 0.45) !important;
        }
        .member-theme-indigo:hover { border-color: #818cf8 !important; box-shadow: 0 8px 25px rgba(99, 102, 241, 0.25) !important; }
        .member-theme-indigo .member-header { border-bottom: 1px solid rgba(99, 102, 241, 0.25) !important; }
        .member-theme-indigo .member-badge { background: rgba(99, 102, 241, 0.2) !important; border: 1px solid rgba(129, 140, 248, 0.45) !important; color: #c7d2fe !important; }
        .member-theme-indigo label { color: #e0e7ff !important; }
        .member-theme-indigo label i { color: #818cf8 !important; }
        .member-theme-indigo .form-control:focus { border-color: #818cf8 !important; box-shadow: 0 0 16px rgba(129, 140, 248, 0.45) !important; }

        /* 2. Theme Rose / Ruby (Member 2 - Istri / Pink) */
        .member-theme-rose {
            background: linear-gradient(135deg, rgba(30, 10, 20, 0.92) 0%, rgba(76, 5, 25, 0.88) 100%) !important;
            border: 1.5px solid rgba(244, 63, 94, 0.45) !important;
        }
        .member-theme-rose:hover { border-color: #fb7185 !important; box-shadow: 0 8px 25px rgba(244, 63, 94, 0.25) !important; }
        .member-theme-rose .member-header { border-bottom: 1px solid rgba(244, 63, 94, 0.25) !important; }
        .member-theme-rose .member-badge { background: rgba(244, 63, 94, 0.2) !important; border: 1px solid rgba(251, 113, 133, 0.45) !important; color: #fecdd3 !important; }
        .member-theme-rose label { color: #ffe4e6 !important; }
        .member-theme-rose label i { color: #fb7185 !important; }
        .member-theme-rose .form-control:focus { border-color: #fb7185 !important; box-shadow: 0 0 16px rgba(251, 113, 133, 0.45) !important; }

        /* 3. Theme Emerald / Mint (Member 3 - Zamrud) */
        .member-theme-emerald {
            background: linear-gradient(135deg, rgba(6, 30, 20, 0.92) 0%, rgba(6, 78, 59, 0.88) 100%) !important;
            border: 1.5px solid rgba(52, 211, 153, 0.45) !important;
        }
        .member-theme-emerald:hover { border-color: #34d399 !important; box-shadow: 0 8px 25px rgba(52, 211, 153, 0.25) !important; }
        .member-theme-emerald .member-header { border-bottom: 1px solid rgba(52, 211, 153, 0.25) !important; }
        .member-theme-emerald .member-badge { background: rgba(16, 185, 129, 0.2) !important; border: 1px solid rgba(52, 211, 153, 0.45) !important; color: #a7f3d0 !important; }
        .member-theme-emerald label { color: #d1fae5 !important; }
        .member-theme-emerald label i { color: #34d399 !important; }
        .member-theme-emerald .form-control:focus { border-color: #34d399 !important; box-shadow: 0 0 16px rgba(52, 211, 153, 0.45) !important; }

        /* 4. Theme Cyan / Ocean Teal (Member 4 - Samudra) */
        .member-theme-cyan {
            background: linear-gradient(135deg, rgba(8, 28, 36, 0.92) 0%, rgba(14, 116, 144, 0.88) 100%) !important;
            border: 1.5px solid rgba(34, 211, 238, 0.45) !important;
        }
        .member-theme-cyan:hover { border-color: #38bdf8 !important; box-shadow: 0 8px 25px rgba(34, 211, 238, 0.25) !important; }
        .member-theme-cyan .member-header { border-bottom: 1px solid rgba(34, 211, 238, 0.25) !important; }
        .member-theme-cyan .member-badge { background: rgba(6, 182, 212, 0.2) !important; border: 1px solid rgba(34, 211, 238, 0.45) !important; color: #bae6fd !important; }
        .member-theme-cyan label { color: #e0f2fe !important; }
        .member-theme-cyan label i { color: #38bdf8 !important; }
        .member-theme-cyan .form-control:focus { border-color: #38bdf8 !important; box-shadow: 0 0 16px rgba(56, 189, 248, 0.45) !important; }

        /* 5. Theme Violet / Amethyst (Member 5 - Lavender Ungu) */
        .member-theme-violet {
            background: linear-gradient(135deg, rgba(28, 10, 42, 0.92) 0%, rgba(88, 28, 135, 0.88) 100%) !important;
            border: 1.5px solid rgba(192, 132, 252, 0.45) !important;
        }
        .member-theme-violet:hover { border-color: #c084fc !important; box-shadow: 0 8px 25px rgba(192, 132, 252, 0.25) !important; }
        .member-theme-violet .member-header { border-bottom: 1px solid rgba(192, 132, 252, 0.25) !important; }
        .member-theme-violet .member-badge { background: rgba(168, 85, 247, 0.2) !important; border: 1px solid rgba(192, 132, 252, 0.45) !important; color: #e9d5ff !important; }
        .member-theme-violet label { color: #f3e8ff !important; }
        .member-theme-violet label i { color: #c084fc !important; }
        .member-theme-violet .form-control:focus { border-color: #c084fc !important; box-shadow: 0 0 16px rgba(192, 132, 252, 0.45) !important; }

        /* 6. Theme Amber / Sunset Orange (Member 6 - Emas Jingga) */
        .member-theme-amber {
            background: linear-gradient(135deg, rgba(38, 20, 6, 0.92) 0%, rgba(120, 53, 15, 0.88) 100%) !important;
            border: 1.5px solid rgba(251, 146, 60, 0.45) !important;
        }
        .member-theme-amber:hover { border-color: #fb923c !important; box-shadow: 0 8px 25px rgba(251, 146, 60, 0.25) !important; }
        .member-theme-amber .member-header { border-bottom: 1px solid rgba(251, 146, 60, 0.25) !important; }
        .member-theme-amber .member-badge { background: rgba(249, 115, 22, 0.2) !important; border: 1px solid rgba(251, 146, 60, 0.45) !important; color: #fed7aa !important; }
        .member-theme-amber label { color: #ffedd5 !important; }
        .member-theme-amber label i { color: #fb923c !important; }
        .member-theme-amber .form-control:focus { border-color: #fb923c !important; box-shadow: 0 0 16px rgba(251, 146, 60, 0.45) !important; }

        /* 7. Theme Lime / Citrus Green (Member 7 - Hijau Lemon) */
        .member-theme-lime {
            background: linear-gradient(135deg, rgba(20, 30, 8, 0.92) 0%, rgba(54, 83, 20, 0.88) 100%) !important;
            border: 1.5px solid rgba(163, 230, 53, 0.45) !important;
        }
        .member-theme-lime:hover { border-color: #a3e635 !important; box-shadow: 0 8px 25px rgba(163, 230, 53, 0.25) !important; }
        .member-theme-lime .member-header { border-bottom: 1px solid rgba(163, 230, 53, 0.25) !important; }
        .member-theme-lime .member-badge { background: rgba(132, 204, 22, 0.2) !important; border: 1px solid rgba(163, 230, 53, 0.45) !important; color: #d9f99d !important; }
        .member-theme-lime label { color: #ecfccb !important; }
        .member-theme-lime label i { color: #a3e635 !important; }
        .member-theme-lime .form-control:focus { border-color: #a3e635 !important; box-shadow: 0 0 16px rgba(163, 230, 53, 0.45) !important; }

        /* Dynamic Leaf Green Gradient Theme for Submit Button */
        .btn-submit-leaf-green {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #22c55e 70%, #4ade80 100%) !important;
            border: 2px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.2rem !important;
            font-weight: 800 !important;
            padding: 1.15rem 3.5rem !important;
            border-radius: 35px !important;
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.45), 0 0 20px rgba(74, 222, 128, 0.3) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 12px !important;
            cursor: pointer !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.25) !important;
            letter-spacing: 0.5px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-submit-leaf-green::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
        }
        .btn-submit-leaf-green:hover {
            transform: translateY(-3px) scale(1.02) !important;
            box-shadow: 0 16px 40px rgba(34, 197, 94, 0.65), 0 0 30px rgba(74, 222, 128, 0.5) !important;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 40%, #4ade80 100%) !important;
            border-color: #bbf7d0 !important;
        }
        .btn-submit-leaf-green:hover::after {
            left: 100%;
        }
        .btn-submit-leaf-green:active {
            transform: translateY(1px) scale(0.99) !important;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header class="app-header">
        <div class="container">
            <a href="../index.php" class="btn-back-leaf-green">
                <i class="fa-solid fa-arrow-left"></i> <span>Kembali Ke Aplikasi Sensus Data PKBGT</span>
            </a>
            <div>
                <span class="badge-pill-church">
                    <i class="fa-solid fa-church"></i> PKBGT
                </span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; font-weight: 800; margin-bottom: 0.35rem;">
                📍 Formulir Aplikasi Sensus Data PKBGT
            </h1>
            <p style="color: #ddd6fe; font-size: 0.95rem; max-width: 800px; line-height: 1.5;">
                Silakan lengkapi identitas Kepala Keluarga, pilih <strong>Kelompok Domisili (1 s/d 17)</strong>, susunan anggota keluarga, serta geser titik koordinat atap rumah pada peta interaktif di bawah ini.
            </p>
        </div>
    </header>

    <!-- Main Form Wrapper -->
    <div class="container main-wrapper" style="margin-top: 1.75rem;">

        <!-- Edit Data Shortcut Banner -->
        <div style="background: #fff1f2; border: 1.5px solid #fecdd3; border-radius: 14px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 0.95rem; color: #881337; font-weight: 700;">
                <span>💡 Sudah pernah mendaftarkan Kartu Keluarga sebelumnya?</span>
            </div>
            <a href="edit_data.php" class="btn-edit-shortcut-leaf">
                <i class="fa-solid fa-user-pen"></i> <span>Perbarui / Edit Data PKBGT &rarr;</span>
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
            
            <!-- 1. DATA POKOK KEPALA KELUARGA (DYNAMIC PURPLE GRADIENT THEME) -->
            <div class="card card-purple-dynamic">
                <div class="card-title-section">
                    <div class="card-icon-purple">
                        👤
                    </div>
                    <div>
                        <h3 class="card-title-text">Data Pokok Kepala Keluarga Jemaat</h3>
                        <span class="card-subtitle-text">Informasi Kartu Keluarga, pemilihan kelompok domisili, nomor WhatsApp, dan foto keluarga</span>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="no_kk"><i class="fa-solid fa-address-card" style="color: #c084fc;"></i> Nomor Kartu Keluarga (KK) <span class="required" style="color:#f87171;">*</span></label>
                        <input type="text" id="no_kk" name="no_kk" class="form-control" maxlength="16" placeholder="16 digit nomor KK (contoh: 7371012345678901)" value="<?= htmlspecialchars($old['no_kk'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nik_kepala"><i class="fa-solid fa-id-badge" style="color: #c084fc;"></i> Nomor Induk Kependudukan (KTP) <span class="required" style="color:#f87171;">*</span></label>
                        <input type="text" id="nik_kepala" name="nik_kepala" class="form-control" maxlength="16" placeholder="16 digit NIK KTP (contoh: 7371012345678901)" value="<?= htmlspecialchars($old['nik_kepala'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nama_kepala"><i class="fa-solid fa-user-check" style="color: #c084fc;"></i> Nama Lengkap Kepala Keluarga <span class="required" style="color:#f87171;">*</span></label>
                        <input type="text" id="nama_kepala" name="nama_kepala" class="form-control" placeholder="Nama lengkap sesuai KTP" value="<?= htmlspecialchars($old['nama_kepala'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="kelompok_id"><i class="fa-solid fa-church" style="color: #c084fc;"></i> Kelompok Domisili (Kelompok 1 - 17) <span class="required" style="color:#f87171;">*</span></label>
                        <select id="kelompok_id" name="kelompok_id" class="form-control" required style="font-weight: 700; color: #ede9fe;">
                            <option value="">-- Pilih Kelompok Binaan --</option>
                            <?php foreach ($groupsList as $grp): ?>
                                <option value="<?= $grp['id'] ?>" <?= (isset($old['kelompok_id']) && $old['kelompok_id'] == $grp['id']) ? 'selected' : ($grp['nomor_kelompok'] == 1 ? 'selected' : '') ?>>
                                    🏷️ <?= htmlspecialchars($grp['nama_kelompok']) ?><?= !empty($grp['nama_ketua']) ? ' (Ketua: ' . htmlspecialchars($grp['nama_ketua']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="no_hp"><i class="fa-brands fa-whatsapp" style="color: #4ade80;"></i> No. WhatsApp / HP Aktif <span class="required" style="color:#f87171;">*</span></label>
                        <input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($old['no_hp'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="jumlah_tanggungan"><i class="fa-solid fa-users" style="color: #c084fc;"></i> Jumlah Tanggungan Keluarga</label>
                        <input type="number" id="jumlah_tanggungan" name="jumlah_tanggungan" class="form-control" min="0" max="20" placeholder="Contoh: 3" value="<?= htmlspecialchars($old['jumlah_tanggungan'] ?? '0') ?>">
                    </div>

                    <!-- FOTO KELUARGA (OPSIONAL) -->
                    <div class="form-group foto-keluarga-box">
                        <label for="foto_keluarga" style="color: #f3e8ff; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <span>👨‍👩‍👧‍👦</span> Foto Bersama Keluarga Jemaat <span style="font-weight: 400; color: #c4b5fd; font-size: 0.8rem;">(Opsional)</span>
                        </label>
                        <input type="file" id="foto_keluarga" name="foto_keluarga" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-top: 0.35rem;">
                        <small style="color: #ddd6fe; font-size: 0.775rem; display: block; margin-top: 0.35rem;">
                            Unggah foto bersama anggota keluarga tercinta. Format: JPG, PNG, atau WEBP (Maksimal 5 MB).
                        </small>
                        <div id="foto-keluarga-preview" style="margin-top: 0.75rem; display: none;">
                            <img src="" alt="Pratinjau Foto Keluarga" style="max-height: 200px; border-radius: 10px; border: 2px solid #a855f7; object-fit: cover; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.4);">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. ALAMAT DOMISILI & TITIK LOKASI PETA (DYNAMIC ORANGE GRADIENT THEME) -->
            <div class="card card-orange-dynamic">
                <div class="card-title-section">
                    <div class="card-icon-orange">
                        📍
                    </div>
                    <div>
                        <h3 class="card-title-text">Alamat & Pasang Titik Rumah di Peta</h3>
                        <span class="card-subtitle-text">Gunakan tombol GPS otomatis atau geser pin merah tepat di atas atap rumah Anda (data jalan akan otomatis terisi)</span>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="rt"><i class="fa-solid fa-map-pin" style="color: #fb923c;"></i> RT</label>
                        <input type="text" id="rt" name="rt" class="form-control" placeholder="Contoh: 01" value="<?= htmlspecialchars($old['rt'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="rw"><i class="fa-solid fa-map-pin" style="color: #fb923c;"></i> RW</label>
                        <input type="text" id="rw" name="rw" class="form-control" placeholder="Contoh: 02" value="<?= htmlspecialchars($old['rw'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="kelurahan"><i class="fa-solid fa-city" style="color: #fb923c;"></i> Kelurahan / Desa</label>
                        <input type="text" id="kelurahan" name="kelurahan" class="form-control" placeholder="Kelurahan" value="<?= htmlspecialchars($old['kelurahan'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="kecamatan"><i class="fa-solid fa-building" style="color: #fb923c;"></i> Kecamatan</label>
                        <input type="text" id="kecamatan" name="kecamatan" class="form-control" placeholder="Kecamatan" value="<?= htmlspecialchars($old['kecamatan'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.75rem;">
                    <label for="alamat_lengkap"><i class="fa-solid fa-road" style="color: #fb923c;"></i> Alamat Lengkap (Nama Jalan / Nomor Rumah / Blok) <span class="required" style="color:#f87171;">*</span></label>
                    <textarea id="alamat_lengkap" name="alamat_lengkap" class="form-control" rows="2" placeholder="Contoh: Jl. Perintis Kemerdekaan No. 21, Blok C..." required><?= htmlspecialchars($old['alamat_lengkap'] ?? '') ?></textarea>
                </div>

                <!-- PETA INTERAKTIF LEAFLET -->
                <div class="form-group" style="margin-top: 1.5rem;">
                    
                    <!-- Tips Android GPS -->
                    <div class="gps-tips-orange">
                        <span style="font-size: 1.3rem; line-height: 1;">📱</span>
                        <div style="font-size: 0.825rem; color: #fed7aa; line-height: 1.45;">
                            <strong style="color: #ffedd5;">Tips Penentuan Titik di HP Android:</strong><br>
                            Nyalakan tombol <strong>Lokasi/GPS</strong> di HP Anda, lalu ketuk tombol hijau <strong>"📍 Gunakan Lokasi GPS Saya"</strong> di bawah. Anda juga bisa langsung menggeser pin merah di peta.
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                        <label style="font-weight: 700; color: #ffedd5; font-size: 0.95rem; margin: 0;">
                            <i class="fa-solid fa-map-location-dot" style="color: #fb923c;"></i> Peta Penentuan Titik Koordinat Rumah <span class="required" style="color:#f87171;">*</span>
                        </label>
                        <button type="button" id="btn-gps" class="btn btn-sm" style="font-size: 0.85rem; font-weight: 800; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border-radius: 20px; padding: 0.5rem 1.2rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border: none;">
                            <span>📍</span> <span>Gunakan Lokasi GPS Saya</span>
                        </button>
                    </div>

                    <div class="map-search-bar" style="margin-bottom: 0.75rem; display: flex; gap: 0.5rem;">
                        <input type="text" id="map-search-input" class="form-control" placeholder="Atau cari nama jalan / kelurahan..." style="font-size: 0.85rem;">
                        <button type="button" id="btn-search-map" class="btn btn-sm" style="padding: 0 1.25rem; font-weight: 800; background: linear-gradient(135deg, #f97316, #ea580c); color:#fff; border-radius: 10px; border:none; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35); cursor:pointer;">Cari</button>
                    </div>

                    <div class="map-wrapper" style="border-radius: 14px; overflow: hidden; border: 2px solid rgba(251, 146, 60, 0.45); box-shadow: 0 8px 24px rgba(0,0,0,0.35);">
                        <div id="map" style="height: 380px; width: 100%;"></div>
                    </div>

                    <!-- Hidden Inputs for Coordinates (Pre-filled with Makassar default) -->
                    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($old['latitude'] ?? '-5.147665') ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($old['longitude'] ?? '119.432731') ?>">

                    <div class="coords-card-orange" style="margin-top: 0.85rem;">
                        <span style="font-size: 0.85rem; color: #fed7aa; font-weight: 700; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-crosshairs" style="color: #fb923c;"></i> Koordinat Terpilih:
                        </span>
                        <span class="coords-badge-orange" id="display-coords">
                            <?= htmlspecialchars($old['latitude'] ?? '-5.147665') ?>, <?= htmlspecialchars($old['longitude'] ?? '119.432731') ?>
                        </span>
                    </div>
                    <div class="map-hint" id="gps-status" style="margin-top: 0.5rem; font-size: 0.8rem; line-height: 1.4;">
                        <span style="color: #fed7aa;">* Ketuk tombol hijau GPS di atas atau sentuh titik di peta untuk menentukan koordinat rumah (nama jalan & wilayah otomatis terisi).</span>
                    </div>
                </div>

                <!-- Foto Rumah -->
                <div class="form-group foto-rumah-box" style="margin-top: 1.5rem;">
                    <label for="foto_rumah" style="color: #ffedd5; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <span>🏠</span> Foto Rumah / Tampak Depan Jemaat <span style="font-weight: 400; color: #fed7aa; font-size: 0.8rem;">(Opsional)</span>
                    </label>
                    <input type="file" id="foto_rumah" name="foto_rumah" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-top: 0.35rem;">
                    <small style="color: #fed7aa; font-size: 0.775rem; display: block; margin-top: 0.35rem;">Maksimal ukuran file: 5 MB. Format: JPG, PNG, atau WEBP.</small>
                    <div id="foto-preview" style="margin-top: 0.75rem; display: none;">
                        <img src="" alt="Pratinjau Foto Rumah" style="max-height: 180px; border-radius: 10px; border: 2px solid #f97316; object-fit: cover; box-shadow: 0 4px 15px rgba(249, 115, 22, 0.35);">
                    </div>
                </div>
            </div>

            <!-- 3. DETAIL ANGGOTA KELUARGA (DYNAMIC BUMBLEBEE YELLOW GRADIENT THEME) -->
            <div class="card card-bumblebee-dynamic">
                <div class="card-title-section">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div class="card-icon-bumblebee">
                            👨‍👩‍👧‍👦
                        </div>
                        <div>
                            <h3 class="card-title-text">Susunan Anggota Keluarga Jemaat</h3>
                            <span class="card-subtitle-text">Daftar seluruh individu yang tergabung dalam Kartu Keluarga ini</span>
                        </div>
                    </div>
                    <button type="button" id="btn-add-member" class="btn-add-member-bumblebee">
                        <i class="fa-solid fa-user-plus"></i> <span>Tambah Anggota Jemaat</span>
                    </button>
                </div>

                <div id="members-container" style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                    <!-- Rows dynamically generated by family-repeater.js -->
                </div>
            </div>

            <!-- SUBMIT BUTTON (DYNAMIC LEAF GREEN GRADIENT THEME) -->
            <div style="text-align: right; margin-bottom: 3.5rem; margin-top: 1.5rem;">
                <button type="submit" class="btn-submit-leaf-green">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Simpan</span>
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
    <script src="../assets/js/map-picker.js?v=<?= time() ?>"></script>
    <script src="../assets/js/family-repeater.js?v=<?= time() ?>"></script>
</body>
</html>
