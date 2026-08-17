<?php
/**
 * FORMULIR EDIT DATA KELUARGA & TITIK RUMAH JEMAAT (TANPA VERIFIKASI / TANPA SESI)
 * Persekutuan Kaum Bapak (PKB)
 * 
 * Fitur:
 * - Pencarian langsung nomor KK tanpa proteksi sesi
 * - Tidak memerlukan status 'terverifikasi'
 * - Pengeditan data pokok keluarga, koordinat GPS, foto, dan anggota keluarga
 */

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Makassar');

$db = get_db();
$errors = [];
$successMsg = '';
$family = null;
$members = [];

// Step 1: Handle Search / Lookup by No. KK (Langsung tanpa verifikasi)
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

// Step 2: Handle POST Update Data
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
    <title>Edit Data Keluarga (Tanpa Verifikasi) - PKBGT</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Google Fonts & Custom CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">

    <style>
        /* NAVBAR HEADER (MATCHING INDEX.PHP) */
        .navbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(11, 9, 26, 0.82) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border-bottom: 1.5px solid rgba(139, 92, 246, 0.25) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35) !important;
            padding: 0.65rem 0 !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        .navbar-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }
        .brand-logo {
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
            text-decoration: none !important;
            transition: transform 0.2s ease !important;
        }
        .brand-logo:hover {
            transform: scale(1.02);
        }
        .brand-logo-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .brand-logo-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: contain;
            background: #ffffff;
            padding: 2px;
            border: 2px solid rgba(167, 139, 250, 0.55);
            box-shadow: 0 0 16px rgba(167, 139, 250, 0.55), 0 0 8px rgba(16, 185, 129, 0.4);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        }
        .brand-logo:hover .brand-logo-img {
            transform: rotate(6deg) scale(1.06);
            box-shadow: 0 0 24px rgba(167, 139, 250, 0.8), 0 0 12px rgba(16, 185, 129, 0.6);
        }
        .brand-title {
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.28rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: 0.5px !important;
            display: block !important;
            line-height: 1.15 !important;
        }
        .brand-subtitle {
            font-size: 0.76rem !important;
            font-weight: 700 !important;
            color: #c4b5fd !important;
            letter-spacing: 0.8px !important;
            text-transform: uppercase !important;
            display: block !important;
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

        /* Dynamic Leaf Green Gradient Theme for Search Card */
        .card-leaf-green-dynamic {
            background: linear-gradient(135deg, rgba(6, 44, 23, 0.96) 0%, rgba(13, 62, 34, 0.92) 50%, rgba(4, 30, 16, 0.98) 100%) !important;
            border: 1.5px solid rgba(74, 222, 128, 0.45) !important;
            box-shadow: 0 12px 35px rgba(21, 128, 61, 0.28), 0 0 20px rgba(74, 222, 128, 0.15) !important;
            border-radius: 20px !important;
            padding: 1.75rem !important;
            margin-bottom: 2rem !important;
            position: relative;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .card-leaf-green-dynamic:hover {
            border-color: rgba(74, 222, 128, 0.7) !important;
            box-shadow: 0 16px 40px rgba(21, 128, 61, 0.4), 0 0 25px rgba(74, 222, 128, 0.25) !important;
        }
        .card-icon-leaf-green {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.25) 0%, rgba(34, 197, 94, 0.15) 100%);
            border: 1.5px solid rgba(134, 239, 172, 0.5);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.25);
        }
        .input-search-leaf {
            background: rgba(15, 23, 42, 0.75) !important;
            border: 1.5px solid rgba(74, 222, 128, 0.4) !important;
            color: #ffffff !important;
            font-size: 1.05rem !important;
            padding: 0.85rem 1.25rem !important;
            border-radius: 14px !important;
            transition: all 0.3s ease !important;
        }
        .input-search-leaf::placeholder {
            color: rgba(187, 247, 208, 0.6) !important;
        }
        .input-search-leaf:focus {
            background: rgba(15, 23, 42, 0.9) !important;
            border-color: #4ade80 !important;
            box-shadow: 0 0 20px rgba(74, 222, 128, 0.45) !important;
            outline: none !important;
        }
        .btn-search-leaf-green {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #22c55e 70%, #4ade80 100%) !important;
            border: 2px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.05rem !important;
            font-weight: 800 !important;
            padding: 0.85rem 2.2rem !important;
            border-radius: 14px !important;
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.4), 0 0 15px rgba(74, 222, 128, 0.25) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            cursor: pointer !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.25) !important;
            letter-spacing: 0.4px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-search-leaf-green::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
        }
        .btn-search-leaf-green:hover {
            transform: translateY(-2px) scale(1.02) !important;
            box-shadow: 0 14px 35px rgba(34, 197, 94, 0.6), 0 0 25px rgba(74, 222, 128, 0.4) !important;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 40%, #4ade80 100%) !important;
            border-color: #bbf7d0 !important;
        }
        .btn-search-leaf-green:hover::after {
            left: 100%;
        }

        /* Dynamic Purple Gradient Theme for Card 1 (Data Pokok) */
        .card-purple-dynamic {
            background: linear-gradient(135deg, rgba(28, 18, 62, 0.96) 0%, rgba(49, 16, 102, 0.92) 50%, rgba(18, 12, 42, 0.98) 100%) !important;
            border: 1.5px solid rgba(167, 139, 250, 0.45) !important;
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            box-shadow: 0 16px 40px rgba(46, 16, 101, 0.35), 0 0 25px rgba(139, 92, 246, 0.15) !important;
            backdrop-filter: blur(16px);
            color: #ffffff;
            margin-bottom: 1.75rem;
        }
        .card-icon-purple {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.3) 0%, rgba(124, 58, 237, 0.2) 100%);
            border: 1.5px solid rgba(192, 132, 252, 0.55);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }
        .card-purple-dynamic label {
            color: #e9d5ff !important;
            font-weight: 700;
            font-size: 0.88rem;
        }
        .card-purple-dynamic .form-control {
            background: rgba(15, 23, 42, 0.7) !important;
            border: 1.5px solid rgba(167, 139, 250, 0.35) !important;
            color: #ffffff !important;
            border-radius: 10px !important;
        }
        .card-purple-dynamic .form-control:focus {
            background: rgba(15, 23, 42, 0.9) !important;
            border-color: #c084fc !important;
            box-shadow: 0 0 16px rgba(192, 132, 252, 0.45) !important;
        }

        /* Dynamic Yellow / Bumblebee Gradient Theme for Card 2 (Alamat & Peta) */
        .card-yellow-dynamic {
            background: linear-gradient(135deg, rgba(38, 28, 6, 0.96) 0%, rgba(55, 40, 5, 0.92) 50%, rgba(20, 15, 4, 0.98) 100%) !important;
            border: 1.5px solid rgba(250, 204, 21, 0.45) !important;
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            box-shadow: 0 16px 40px rgba(161, 98, 7, 0.3), 0 0 25px rgba(234, 179, 8, 0.15) !important;
            backdrop-filter: blur(16px);
            color: #ffffff;
            margin-bottom: 1.75rem;
        }
        .card-icon-yellow {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(250, 204, 21, 0.3) 0%, rgba(234, 179, 8, 0.2) 100%);
            border: 1.5px solid rgba(253, 224, 71, 0.55);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 15px rgba(234, 179, 8, 0.3);
        }
        .card-yellow-dynamic label {
            color: #fef08a !important;
            font-weight: 700;
            font-size: 0.88rem;
        }
        .card-yellow-dynamic .form-control {
            background: rgba(15, 23, 42, 0.7) !important;
            border: 1.5px solid rgba(250, 204, 21, 0.35) !important;
            color: #ffffff !important;
            border-radius: 10px !important;
        }
        .card-yellow-dynamic .form-control:focus {
            background: rgba(15, 23, 42, 0.9) !important;
            border-color: #facc15 !important;
            box-shadow: 0 0 16px rgba(250, 204, 21, 0.45) !important;
        }

        /* Dynamic Tosca / Cyan Gradient Theme for Card 3 (Tabel Anggota) */
        .card-tosca-dynamic {
            background: linear-gradient(135deg, rgba(8, 38, 48, 0.96) 0%, rgba(12, 58, 72, 0.92) 50%, rgba(6, 26, 34, 0.98) 100%) !important;
            border: 1.5px solid rgba(34, 211, 238, 0.45) !important;
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            box-shadow: 0 16px 40px rgba(8, 145, 178, 0.3), 0 0 25px rgba(34, 211, 238, 0.15) !important;
            backdrop-filter: blur(16px);
            color: #ffffff;
            margin-bottom: 1.75rem;
        }
        .card-icon-tosca {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(34, 211, 238, 0.3) 0%, rgba(6, 182, 212, 0.2) 100%);
            border: 1.5px solid rgba(103, 232, 249, 0.55);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }
        .card-tosca-dynamic label {
            color: #cffafe !important;
            font-weight: 700;
            font-size: 0.88rem;
        }
        .table-tosca-header {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 50%, #155e75 100%) !important;
            color: #ffffff !important;
        }

        /* Dynamic Leaf Green Theme for Add Member Button */
        .btn-add-leaf-green {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 40%, #22c55e 100%) !important;
            border: 1.5px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.88rem !important;
            font-weight: 800 !important;
            padding: 0.6rem 1.4rem !important;
            border-radius: 20px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.35), 0 0 10px rgba(74, 222, 128, 0.2) !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-add-leaf-green::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
        }
        .btn-add-leaf-green:hover {
            color: #ffffff !important;
            transform: translateY(-2px) scale(1.02) !important;
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.55), 0 0 18px rgba(74, 222, 128, 0.35) !important;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 40%, #4ade80 100%) !important;
            border-color: #bbf7d0 !important;
        }
        .btn-add-leaf-green:hover::after {
            left: 100%;
        }

        /* Dynamic Leaf Green Gradient Theme for Submit Button */
        .btn-submit-leaf-green {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #22c55e 70%, #4ade80 100%) !important;
            border: 2px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.15rem !important;
            font-weight: 800 !important;
            padding: 1.05rem 3rem !important;
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
            border-color: #06b6d4;
            outline: none;
            box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.25);
        }
    </style>
</head>
<body>

    <!-- NAVBAR HEADER (CLEAN BRAND ONLY & DYNAMIC STICKY - MATCHING INDEX.PHP) -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-container">
            <a href="../index.php" class="brand-logo" id="brand-logo">
                <div class="brand-logo-wrap">
                    <img src="../assets/img/logo_pkbgt.png" alt="Logo PKBGT" class="brand-logo-img">
                </div>
                <div class="brand-text">
                    <span class="brand-title">PKB GEREJA TORAJA</span>
                    <span class="brand-subtitle">PERSEKUTUAN KAUM BAPAK (PKBGT)</span>
                </div>
            </a>
        </div>
    </nav>

    <div class="container main-wrapper" style="margin-top: 1.75rem;">

        <!-- SEARCH / LOOKUP CARD (DIRECT LOOKUP) -->
        <div class="card card-leaf-green-dynamic">
            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 1.25rem;">
                <div class="card-icon-leaf-green">
                    🔍
                </div>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: #f0fdf4; margin: 0; letter-spacing: -0.3px;">Cari Data Kartu Keluarga</h3>
                    <small style="color: #bbf7d0; font-size: 0.88rem;">Ketik 16 digit nomor Kartu Keluarga Anda untuk membuka formulir edit data keluarga</small>
                </div>
            </div>

            <form action="edit_data_noverifikasi.php" method="GET" style="display: flex; gap: 0.85rem; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 260px;">
                    <input type="text" name="kk" class="form-control input-search-leaf" maxlength="16" placeholder="Masukkan 16 digit Nomor Kartu Keluarga Anda..." value="<?= htmlspecialchars($lookup_kk) ?>" required>
                </div>
                <button type="submit" class="btn-search-leaf-green">
                    <i class="fa-solid fa-magnifying-glass"></i> <span>Buka Data</span>
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

            <?php 
                $isVerifiedStatus = (strtolower(trim((string)($family['status_verifikasi'] ?? ''))) === 'terverifikasi');
            ?>

            <?php if ($isVerifiedStatus): ?>
                <!-- UI STATUS HIJAU (TERVERIFIKASI) -->
                <div style="background: linear-gradient(135deg, rgba(6, 78, 59, 0.5) 0%, rgba(4, 47, 46, 0.65) 100%); border: 1.5px solid #10b981; border-radius: 16px; padding: 1.15rem 1.35rem; margin-bottom: 1.75rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25);">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; background: rgba(16, 185, 129, 0.25); border: 1.5px solid #34d399; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #34d399; box-shadow: 0 0 15px rgba(16, 185, 129, 0.35);">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div style="color: #ecfdf5; font-weight: 800; font-size: 1.08rem; font-family: 'Outfit', sans-serif;">
                                Data Ditemukan: <?= htmlspecialchars($family['nama_kepala']) ?> (KK: <?= htmlspecialchars($family['no_kk']) ?>)
                            </div>
                            <div style="color: #a7f3d0; font-size: 0.86rem; margin-top: 2px;">
                                Status Verifikasi: <strong style="color: #34d399; text-transform: uppercase; letter-spacing: 0.5px;">TERVERIFIKASI RESMI</strong>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #6ee7b7; box-shadow: 0 2px 10px rgba(16,185,129,0.3); display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-circle-check"></i> TERVERIFIKASI
                        </span>
                        <!-- TOMBOL WHATSAPP TANYA ADMIN -->
                        <a href="https://wa.me/628114188796?text=Hai%20Admin%20Aplikasi%20Sensus%20Data,%20saya%20<?= urlencode($family['nama_kepala']) ?>%20(KK:%20<?= urlencode($family['no_kk']) ?>)" target="_blank" style="background: rgba(37, 211, 102, 0.15); border: 1.5px solid rgba(37, 211, 102, 0.45); color: #86efac; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.15);">
                            <i class="fa-brands fa-whatsapp" style="color:#25D366; font-size: 1rem;"></i>
                            <span>💬 Tanya Admin</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- UI STATUS MERAH (BELUM TERVERIFIKASI / PENDING) -->
                <div style="background: linear-gradient(135deg, rgba(127, 29, 29, 0.55) 0%, rgba(69, 10, 10, 0.7) 100%); border: 1.5px solid #ef4444; border-radius: 16px; padding: 1.15rem 1.35rem; margin-bottom: 1.75rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 8px 24px rgba(239, 68, 68, 0.25);">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; background: rgba(239, 68, 68, 0.25); border: 1.5px solid #f87171; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #f87171; box-shadow: 0 0 15px rgba(239, 68, 68, 0.35);">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                        <div>
                            <div style="color: #fee2e2; font-weight: 800; font-size: 1.08rem; font-family: 'Outfit', sans-serif;">
                                Data Ditemukan: <?= htmlspecialchars($family['nama_kepala']) ?> (KK: <?= htmlspecialchars($family['no_kk']) ?>)
                            </div>
                            <div style="color: #fca5a5; font-size: 0.86rem; margin-top: 2px;">
                                Status Verifikasi: <strong style="color: #f87171; text-transform: uppercase; letter-spacing: 0.5px;">BELUM TERVERIFIKASI (<?= htmlspecialchars($family['status_verifikasi'] ?: 'PENDING') ?>)</strong>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="background: linear-gradient(135deg, #ef4444, #b91c1c); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #fca5a5; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.3); display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> BELUM TERVERIFIKASI
                        </span>
                        <!-- TOMBOL WHATSAPP TANYA ADMIN -->
                        <a href="https://wa.me/628114188796?text=Hai%20Admin%20Aplikasi%20Sensus%20Data,%20saya%20<?= urlencode($family['nama_kepala']) ?>%20(KK:%20<?= urlencode($family['no_kk']) ?>)" target="_blank" style="background: rgba(37, 211, 102, 0.15); border: 1.5px solid rgba(37, 211, 102, 0.45); color: #86efac; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.15);">
                            <i class="fa-brands fa-whatsapp" style="color:#25D366; font-size: 1rem;"></i>
                            <span>💬 Tanya Admin</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" id="form-keluarga">
                <input type="hidden" name="action" value="update_data">
                <input type="hidden" name="family_id" value="<?= $family['id'] ?>">

                <!-- 1. DATA POKOK KEPALA KELUARGA (DYNAMIC PURPLE GRADIENT THEME) -->
                <div class="card card-purple-dynamic">
                    <div class="card-title-section">
                        <div class="card-icon-purple">
                            👤
                        </div>
                        <div>
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: #ffffff;">1. Data Pokok Kepala Keluarga</h3>
                            <small style="color: #ddd6fe;">Informasi identitas kepala keluarga, kontak, dan foto keluarga</small>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="no_kk">Nomor Kartu Keluarga (KK) <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="no_kk" name="no_kk" class="form-control" maxlength="16" value="<?= htmlspecialchars($family['no_kk']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="nik_kepala">Nomor Induk Kependudukan (KTP) <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="nik_kepala" name="nik_kepala" class="form-control" maxlength="16" value="<?= htmlspecialchars($family['nik_kepala']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="nama_kepala">Nama Lengkap Kepala Keluarga <span class="required" style="color:#ef4444;">*</span></label>
                            <input type="text" id="nama_kepala" name="nama_kepala" class="form-control" value="<?= htmlspecialchars($family['nama_kepala']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="kelompok_id">Kelompok Pelayanan Domisili (Kelompok 1 - 17) <span class="required" style="color:#ef4444;">*</span></label>
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
                        <div class="form-group" style="grid-column: 1 / -1; background: rgba(15, 23, 42, 0.7); border: 1.5px dashed #c084fc; border-radius: 14px; padding: 1.15rem 1.25rem;">
                            <label for="foto_keluarga" style="color: #e9d5ff; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <span>👨‍👩‍👧‍👦</span> Ganti / Unggah Foto Bersama Keluarga <span style="font-weight: 400; color: #c084fc; font-size: 0.8rem;">(Opsional)</span>
                            </label>
                            <input type="file" id="foto_keluarga" name="foto_keluarga" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-top: 0.35rem;">
                            
                            <?php if (!empty($family['foto_keluarga'])): ?>
                                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #ddd6fe;">
                                    Foto saat ini terpasang. <a href="../uploads/<?= htmlspecialchars($family['foto_keluarga']) ?>" target="_blank" style="color: #c084fc; font-weight: 700; text-decoration: underline;">Lihat Foto Keluarga</a>
                                </div>
                            <?php endif; ?>

                            <div id="foto-keluarga-preview" style="margin-top: 0.75rem; display: none;">
                                <img src="" alt="Pratinjau Foto Keluarga" style="max-height: 200px; border-radius: 10px; border: 2px solid #a855f7; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. ALAMAT & PETA LOKASI RUMAH (DYNAMIC YELLOW GRADIENT THEME) -->
                <div class="card card-yellow-dynamic">
                    <div class="card-title-section">
                        <div class="card-icon-yellow">
                            📍
                        </div>
                        <div>
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: #fef08a;">2. Alamat & Titik Koordinat Rumah</h3>
                            <small style="color: #fde68a;">Geser pin merah jika ingin memindahkan atau menyesuaikan posisi atap rumah</small>
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
                            <label style="font-weight: 700; color: #fef08a;">Peta Titik Koordinat Rumah</label>
                            <button type="button" id="btn-gps" class="btn btn-sm" style="font-size: 0.85rem; font-weight: 800; background: linear-gradient(135deg, #15803d, #22c55e); color: #ffffff; border-radius: 20px; padding: 0.5rem 1.2rem; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);">
                                <span>📍</span> <span>Gunakan Lokasi GPS Saya</span>
                            </button>
                        </div>

                        <div class="map-search-bar" style="margin-bottom: 0.75rem; display: flex; gap: 0.5rem;">
                            <input type="text" id="map-search-input" class="form-control" placeholder="Cari nama jalan / kelurahan terdekat..." style="font-size: 0.85rem; background: rgba(15, 23, 42, 0.7); color:#fff; border-color: rgba(250, 204, 21, 0.4);">
                            <button type="button" id="btn-search-map" class="btn btn-sm" style="padding: 0 1.25rem; background: #eab308; color:#1e1b4b; font-weight:800; border-radius:10px; border:none; cursor:pointer;">Cari</button>
                        </div>

                        <div class="map-wrapper" style="border-radius: 14px; overflow: hidden; border: 2px solid rgba(250, 204, 21, 0.45); box-shadow: 0 6px 20px rgba(0,0,0,0.3);">
                            <div id="map" style="height: 380px; width: 100%;"></div>
                        </div>

                        <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($family['latitude']) ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($family['longitude']) ?>">

                        <div class="map-coords-card" style="margin-top: 0.75rem; background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(250, 204, 21, 0.4); padding: 0.65rem 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.85rem; color: #fef08a; font-weight: 700;">Koordinat Terpilih:</span>
                            <span class="coords-badge" id="display-coords" style="font-family: monospace; font-weight: 800; background: #ca8a04; color: #fff; padding: 4px 12px; border-radius: 6px;">
                                <?= htmlspecialchars($family['latitude']) ?>, <?= htmlspecialchars($family['longitude']) ?>
                            </span>
                        </div>
                        <div class="map-hint" id="gps-status" style="margin-top: 0.5rem; font-size: 0.8rem; color: #fde68a;">
                            * Geser pin merah untuk memindahkan titik atap rumah Anda.
                        </div>
                    </div>

                    <!-- Foto Rumah -->
                    <div class="form-group" style="margin-top: 1.25rem;">
                        <label for="foto_rumah">Ganti / Unggah Foto Rumah (Opsional)</label>
                        <input type="file" id="foto_rumah" name="foto_rumah" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($family['foto_rumah'])): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #fde68a;">
                                Foto rumah saat ini: <a href="../uploads/<?= htmlspecialchars($family['foto_rumah']) ?>" target="_blank" style="color: #facc15; font-weight: 700; text-decoration: underline;">Lihat Foto Rumah</a>
                            </div>
                        <?php endif; ?>
                        <div id="foto-preview" style="margin-top: 0.75rem; display: none;">
                            <img src="" alt="Pratinjau Foto Rumah" style="max-height: 180px; border-radius: 10px; border: 1px solid #cbd5e1; object-fit: cover;">
                        </div>
                    </div>
                </div>

                <!-- 3. DETAIL ANGGOTA KELUARGA (DYNAMIC TOSCA GRADIENT THEME) -->
                <div class="card card-tosca-dynamic">
                    <div class="card-title-section" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="card-icon-tosca">
                                👨‍👩‍👧‍👦
                            </div>
                            <div>
                                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: #cffafe;">3. Tabel Susunan Anggota Keluarga Terdaftar</h3>
                                <small style="color: #a5f3fc;">Daftar seluruh individu yang tercatat dalam Kartu Keluarga ini (<span id="count-members-badge" style="font-weight:800; color:#22d3ee;"><?= count($members) ?></span> Orang)</small>
                            </div>
                        </div>
                        <button type="button" id="btn-add-member-table" class="btn-add-leaf-green">
                            <i class="fa-solid fa-user-plus"></i> <span>Tambah Anggota Jemaat</span>
                        </button>
                    </div>

                    <!-- TABEL RESPONSIVE ANGGOTA KELUARGA -->
                    <div style="overflow-x: auto; margin-top: 1.25rem; border-radius: 14px; border: 1.5px solid rgba(34, 211, 238, 0.35); box-shadow: 0 4px 16px rgba(0,0,0,0.25);">
                        <table class="table-custom-members" id="table-members" style="min-width: 820px;">
                            <thead>
                                <tr class="table-tosca-header">
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
                                        <td style="text-align: center; font-weight: 800; color: #0891b2;" class="row-number">
                                             <?= $rowIdx + 1 ?>
                                        </td>
                                        <td>
                                             <input type="text" name="members[<?= $rowIdx ?>][nama_lengkap]" class="form-control-sm input-nama" value="<?= htmlspecialchars($m['nama_lengkap']) ?>" placeholder="Nama lengkap..." required>
                                            <?php if ($isFirst): ?>
                                                <small style="color: #0891b2; font-weight: 700; font-size: 0.72rem; display: block; margin-top: 2px;">(Kepala Keluarga)</small>
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
                    <button type="submit" class="btn-submit-leaf-green">
                        <i class="fa-solid fa-floppy-disk"></i> <span>Simpan Pembaruan Data</span>
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
                    <td style="text-align: center; font-weight: 800; color: #0891b2;" class="row-number">${tableBody.children.length + 1}</td>
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
