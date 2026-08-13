<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pdo = getPdo();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT a.*, o.nama_ayah, o.nama_ibu, o.no_whatsapp, o.alamat FROM atlet a LEFT JOIN orang_tua o ON a.id = o.atlet_id WHERE a.id = ?");
$stmt->execute([$id]);
$atlet = $stmt->fetch();

if (!$atlet) {
    die("<div style='padding:2rem; color:red;'>Data atlet tidak ditemukan!</div>");
}

// Fetch latest evaluation for OVR rating calculation
$stmtEval = $pdo->prepare("SELECT * FROM evaluasi_atlet WHERE atlet_id = ? ORDER BY tanggal_evaluasi DESC LIMIT 1");
$stmtEval->execute([$id]);
$evaluasi = $stmtEval->fetch();

// Fetch tournament count
$stmtStats = $pdo->prepare("SELECT COUNT(*) as total FROM statistik_pertandingan WHERE atlet_id = ?");
$stmtStats->execute([$id]);
$tournamentCount = (int)($stmtStats->fetch()['total'] ?? 0);

if ($evaluasi) {
    $passingVal   = (int)($evaluasi['nilai_passing'] ?? 70);
    $dribblingVal = (int)($evaluasi['nilai_dribbling'] ?? 70);
    $shootingVal  = (int)($evaluasi['nilai_shooting'] ?? 70);
    $tacklingVal  = (int)($evaluasi['nilai_tackling'] ?? 70);
    $staminaVal   = (int)($evaluasi['nilai_stamina'] ?? 70);
    $ovr = (int)round(($passingVal + $dribblingVal + $shootingVal + $tacklingVal + $staminaVal) / 5);
} else {
    $ovr = 75;
}

if ($ovr >= 90) {
    $ovrGrade = "ELITE 👑";
    $ovrColor = "#38bdf8";
    $ovrBadgeBg = "rgba(56, 189, 248, 0.2)";
} elseif ($ovr >= 80) {
    $ovrGrade = "GOLD 🏆";
    $ovrColor = "#fbbf24";
    $ovrBadgeBg = "rgba(251, 191, 36, 0.2)";
} elseif ($ovr >= 70) {
    $ovrGrade = "SILVER 🥈";
    $ovrColor = "#cbd5e1";
    $ovrBadgeBg = "rgba(203, 213, 225, 0.2)";
} else {
    $ovrGrade = "BRONZE 🥉";
    $ovrColor = "#f97316";
    $ovrBadgeBg = "rgba(249, 115, 22, 0.2)";
}

$pageTitle = "Edit Profil - " . htmlspecialchars($atlet['nama_lengkap']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn_nik_raw = trim($_POST['nisn_nik'] ?? '');
    $nisn_nik = !empty($nisn_nik_raw) ? $nisn_nik_raw : null;
    $no_kk = trim($_POST['no_kk'] ?? '');
    $no_akta = trim($_POST['no_akta'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'Laki-laki';
    $posisi_utama = $_POST['posisi_utama'] ?? 'Gelandang (MF)';
    $posisi_sekunder = trim($_POST['posisi_sekunder'] ?? '-');
    $kaki_dominan = $_POST['kaki_dominan'] ?? 'Kanan';
    $tinggi_badan = (int)($_POST['tinggi_badan'] ?? 0);
    $berat_badan = (int)($_POST['berat_badan'] ?? 0);
    $kelompok_usia = $_POST['kelompok_usia'] ?? 'U-12';
    $status_keanggotaan = $_POST['status_keanggotaan'] ?? 'Aktif';

    $nama_ayah = trim($_POST['nama_ayah'] ?? '');
    $nama_ibu = trim($_POST['nama_ibu'] ?? '');
    $no_whatsapp = trim($_POST['no_whatsapp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (empty($nama_lengkap) || empty($tanggal_lahir)) {
        $error = "Nama Lengkap dan Tanggal Lahir wajib diisi!";
    } else {
        try {
            // Check if NISN/NIK is already taken by another athlete
            if ($nisn_nik !== null) {
                $checkStmt = $pdo->prepare("SELECT id FROM atlet WHERE nisn_nik = ? AND id != ?");
                $checkStmt->execute([$nisn_nik, $id]);
                if ($checkStmt->fetch()) {
                    throw new Exception("NISN / NIK '$nisn_nik' sudah digunakan oleh atlet lain!");
                }
            }

            // Handle Foto Profil upload
            $foto_profil = $atlet['foto_profil'] ?? 'default_avatar.png';
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['foto_profil']['tmp_name'];
                $fileName = $_FILES['foto_profil']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowedExts)) {
                    $uploadDir = __DIR__ . '/../assets/img/atlet/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $newFileName = 'atlet_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                        $foto_profil = $newFileName;
                    }
                }
            }

            // Handle Berkas KK upload
            $file_kk = $atlet['file_kk'] ?? null;
            if (isset($_FILES['file_kk']) && $_FILES['file_kk']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['file_kk']['tmp_name'];
                $fileName = $_FILES['file_kk']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedDocs = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                if (in_array($ext, $allowedDocs)) {
                    $uploadDirDocs = __DIR__ . '/../assets/docs/';
                    if (!is_dir($uploadDirDocs)) {
                        mkdir($uploadDirDocs, 0777, true);
                    }
                    $newFileName = 'kk_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDirDocs . $newFileName)) {
                        $file_kk = $newFileName;
                    }
                }
            }

            // Handle Berkas Akta Kelahiran upload
            $file_akta = $atlet['file_akta'] ?? null;
            if (isset($_FILES['file_akta']) && $_FILES['file_akta']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['file_akta']['tmp_name'];
                $fileName = $_FILES['file_akta']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedDocs = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                if (in_array($ext, $allowedDocs)) {
                    $uploadDirDocs = __DIR__ . '/../assets/docs/';
                    if (!is_dir($uploadDirDocs)) {
                        mkdir($uploadDirDocs, 0777, true);
                    }
                    $newFileName = 'akta_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDirDocs . $newFileName)) {
                        $file_akta = $newFileName;
                    }
                }
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE atlet SET nisn_nik=?, no_kk=?, no_akta=?, nama_lengkap=?, tempat_lahir=?, tanggal_lahir=?, jenis_kelamin=?, posisi_utama=?, posisi_sekunder=?, kaki_dominan=?, tinggi_badan=?, berat_badan=?, kelompok_usia=?, foto_profil=?, file_kk=?, file_akta=?, status_keanggotaan=? WHERE id=?");
            $stmt->execute([$nisn_nik, $no_kk, $no_akta, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $posisi_utama, $posisi_sekunder, $kaki_dominan, $tinggi_badan, $berat_badan, $kelompok_usia, $foto_profil, $file_kk, $file_akta, $status_keanggotaan, $id]);

            // Update or Insert Ortu
            $checkOrtu = $pdo->prepare("SELECT id FROM orang_tua WHERE atlet_id=?");
            $checkOrtu->execute([$id]);
            if ($checkOrtu->fetch()) {
                $stmtOrtu = $pdo->prepare("UPDATE orang_tua SET nama_ayah=?, nama_ibu=?, no_whatsapp=?, alamat=? WHERE atlet_id=?");
                $stmtOrtu->execute([$nama_ayah, $nama_ibu, $no_whatsapp, $alamat, $id]);
            } else {
                $stmtOrtu = $pdo->prepare("INSERT INTO orang_tua (atlet_id, nama_ayah, nama_ibu, no_whatsapp, alamat) VALUES (?, ?, ?, ?, ?)");
                $stmtOrtu->execute([$id, $nama_ayah, $nama_ibu, $no_whatsapp, $alamat]);
            }

            $pdo->commit();
            header("Location: detail.php?id=$id&success=updated");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<?php
$photoPath = __DIR__ . '/../assets/img/atlet/' . ($atlet['foto_profil'] ?? '');
$hasPhoto = !empty($atlet['foto_profil']) && $atlet['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
?>

<div style="max-width:950px; margin:0 auto; display:flex; flex-direction:column; gap:1.5rem;">

    <!-- ATHLETE HERO CARD (FUT HERO CARD DESIGN MATCHING DETAIL.PHP) -->
    <div class="card" style="position:relative; overflow:hidden; background: linear-gradient(135deg, rgba(30, 27, 75, 0.95) 0%, rgba(15, 23, 42, 0.98) 50%, rgba(17, 24, 39, 0.95) 100%); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.7); margin-bottom: 0.5rem; padding: 1.5rem 1.75rem;">
        <!-- Glowing Ambient Orbs -->
        <div style="position:absolute; right:-40px; top:-40px; width:280px; height:280px; background:radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
        <div style="position:absolute; left:25%; bottom:-60px; width:220px; height:220px; background:radial-gradient(circle, <?= $ovrColor ?>33 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1.75rem; position:relative; z-index:2;">
            
            <!-- LEFT COLUMN: INFO UTAMA & TOMBOL AKSI -->
            <div style="flex:1; min-width:280px;">
                <!-- BADGE HEADER -->
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                    <span style="background:<?= $ovrBadgeBg ?>; border:1px solid <?= $ovrColor ?>; color:<?= $ovrColor ?>; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;">
                        <?= $ovrGrade ?>
                    </span>
                    <span id="heroKuBadge" class="badge badge-primary" style="font-weight:700; border:1px solid rgba(99,102,241,0.4);">
                        KATEGORI <?= htmlspecialchars($atlet['kelompok_usia']) ?>
                    </span>
                    <span class="badge badge-emerald" style="font-weight:700; border:1px solid rgba(52,211,153,0.4);">
                        <span style="width:6px; height:6px; background:#34d399; border-radius:50%; display:inline-block; margin-right:4px;"></span>
                        <?= htmlspecialchars($atlet['status_keanggotaan']) ?>
                    </span>
                </div>

                <!-- NAMA ATLET -->
                <h1 id="heroNamaTitle" style="font-family:'Outfit', sans-serif; font-size:1.85rem; font-weight:800; color:#fff; margin-bottom:8px; letter-spacing:-0.5px;">
                    Edit Profil: <?= htmlspecialchars($atlet['nama_lengkap']) ?>
                </h1>

                <!-- METRIK STRUKTURAL -->
                <div style="font-size:0.85rem; color:#cbd5e1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem;">
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                        ⚽ <strong>Utama:</strong> <span id="heroPosisiChip" style="color:#38bdf8; font-weight:700;"><?= htmlspecialchars($atlet['posisi_utama'] ?: '-') ?></span>
                    </span>
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:8px; border:1px solid rgba(56,189,248,0.25);">
                        🔄 <strong>Sekunder:</strong> <span id="heroPosisiSekunderChip" style="color:#7dd3fc; font-weight:700;"><?= htmlspecialchars(($atlet['posisi_sekunder'] && $atlet['posisi_sekunder'] !== '-') ? $atlet['posisi_sekunder'] : '-') ?></span>
                    </span>
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                        ⚡ <strong>Kaki Dominan:</strong> <span style="color:#a78bfa; font-weight:700;"><?= htmlspecialchars($atlet['kaki_dominan'] ?: 'Kanan') ?></span>
                    </span>
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                        📏 <strong>Fisik:</strong> <span style="color:#fbbf24; font-weight:700;"><?= $atlet['tinggi_badan'] ?> cm / <?= $atlet['berat_badan'] ?> kg</span>
                    </span>
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(251,191,36,0.12); padding:4px 10px; border-radius:8px; border:1px solid rgba(251,191,36,0.3);">
                        🏆 <strong>Turnamen:</strong> <span style="color:#fbbf24; font-weight:700;"><?= $tournamentCount ?> Event</span>
                    </span>
                </div>

                <!-- GROUP TOMBOL AKSI OPERASIONAL -->
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <a href="detail.php?id=<?= $atlet['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.6rem 1.1rem; border-color:rgba(148,163,184,0.3);">
                        &larr; Batal & Kembali ke Detail
                    </a>
                </div>
            </div>

            <!-- RIGHT COLUMN: FOTO PROFILE HERO CARD DENGAN OVR RATING -->
            <div style="position:relative; flex-shrink:0;">
                <div style="width:135px; height:135px; border-radius:24px; background:linear-gradient(135deg, var(--primary), #818cf8); display:flex; align-items:center; justify-content:center; overflow:hidden; border:3px solid <?= $ovrColor ?>; box-shadow:0 0 25px <?= $ovrColor ?>55, 0 10px 25px rgba(0,0,0,0.5);">
                    <?php if ($hasPhoto): ?>
                        <img id="heroAvatarImage" src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Foto Atlet" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span id="heroAvatarInitials" style="font-size:2.8rem; font-weight:800; color:#fff; font-family:'Outfit', sans-serif;"><?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?></span>
                        <img id="heroAvatarImage" src="" alt="Foto Atlet" style="width:100%; height:100%; object-fit:cover; display:none;">
                    <?php endif; ?>
                </div>

                <!-- FLOATING OVR RATING PILL OVERLAY -->
                <div style="position:absolute; bottom:-12px; left:50%; transform:translateX(-50%); background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,27,75,0.95)); border:1.5px solid <?= $ovrColor ?>; border-radius:20px; padding:3px 12px; box-shadow:0 4px 12px rgba(0,0,0,0.5); display:flex; align-items:center; gap:5px; white-space:nowrap; z-index:3;">
                    <span style="font-family:'Outfit', sans-serif; font-size:1rem; font-weight:900; color:<?= $ovrColor ?>;"><?= $ovr ?></span>
                    <span style="font-size:0.65rem; font-weight:800; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.5px;">OVR</span>
                </div>
            </div>

        </div>
    </div>

    <!-- ERROR ALERT -->
    <?php if ($error): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:1rem; border-radius:14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:1.2rem;">⚠️</span>
            <div style="font-size:0.88rem; font-weight:600;"><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- MAIN FORM -->
    <form method="POST" enctype="multipart/form-data" id="editAtletForm">

        <!-- SECTION A: BIODATA & FOTO PROFIL ATLET (HIGH TECH INTERACTIVE) -->
        <div class="card" style="border:1px solid rgba(99,102,241,0.3); margin-bottom:1.25rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(99,102,241,0.25), rgba(129,140,248,0.15)); color:#818cf8; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(99,102,241,0.3);">
                        👤
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">A. Biodata & Foto Profil Atlet</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Identitas pokok, tempat/tanggal lahir, dan kelompok usia</p>
                    </div>
                </div>

                <span style="font-size:0.72rem; color:#818cf8; background:rgba(99,102,241,0.12); padding:4px 10px; border-radius:20px; border:1px solid rgba(99,102,241,0.25); font-weight:600;">
                    ⚡ Live Interactive Input
                </span>
            </div>

            <!-- PHOTO UPLOAD DROPZONE HERO CARD MODULE -->
            <div style="background:linear-gradient(135deg, rgba(30,27,75,0.4), rgba(15,23,42,0.6)); border:1.5px dashed rgba(129,140,248,0.4); padding:1.2rem; border-radius:16px; margin-bottom:1.25rem; transition:all 0.3s ease;">
                <div style="display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap;">
                    
                    <div style="position:relative;">
                        <div id="sectionAvatarContainer" style="width:80px; height:80px; border-radius:20px; background:linear-gradient(135deg, var(--primary), #818cf8); display:flex; align-items:center; justify-content:center; overflow:hidden; border:2.5px solid #818cf8; box-shadow:0 0 15px rgba(99,102,241,0.4); flex-shrink:0;">
                            <?php if ($hasPhoto): ?>
                                <img id="sectionAvatarImage" src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <span id="sectionAvatarInitials" style="font-size:1.8rem; font-weight:800; color:#fff;"><?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?></span>
                                <img id="sectionAvatarImage" src="" alt="Avatar" style="width:100%; height:100%; object-fit:cover; display:none;">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="flex:1; min-width:240px;">
                        <label style="font-size:0.85rem; font-weight:700; color:#fff; margin-bottom:4px; display:block;">
                            📸 Upload Pas Foto Atlet Terbaru
                        </label>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.75rem;">
                            Pilih file foto berformat <strong>JPG, PNG, atau WEBP</strong> (Maksimum 2MB). Foto akan otomatis muncul pada Hero Card & Kartu ID Atlet.
                        </p>
                        <input type="file" name="foto_profil" id="fotoProfilInput" class="form-control" accept="image/*" onchange="previewAvatar(this)">
                    </div>

                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Nama Lengkap Atlet *</label>
                    <input type="text" name="nama_lengkap" id="namaLengkapInput" value="<?= htmlspecialchars($atlet['nama_lengkap']) ?>" class="form-control" required placeholder="Nama lengkap sesuai KK/Akta" oninput="syncNamaHero(this.value)">
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">NISN / NIK (Nomor Induk Siswa/KTP)</label>
                    <input type="text" name="nisn_nik" value="<?= htmlspecialchars($atlet['nisn_nik'] ?? '') ?>" class="form-control" placeholder="10 Digit NISN / 16 Digit NIK">
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($atlet['tempat_lahir'] ?? '') ?>" class="form-control" placeholder="Kota / Kabupaten Lahir">
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Tanggal Lahir *</label>
                    <input type="date" name="tanggal_lahir" id="tanggalLahirInput" value="<?= $atlet['tanggal_lahir'] ?>" class="form-control" required onchange="autoSyncKU()">
                    <div id="kuHint" style="font-size:0.75rem; color:#818cf8; font-weight:600; margin-top:4px; min-height:18px;"></div>
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Jenis Kelamin *</label>
                    <select name="jenis_kelamin" class="form-control" required>
                        <option value="Laki-laki" <?= ($atlet['jenis_kelamin'] ?? 'Laki-laki') == 'Laki-laki' ? 'selected' : '' ?>>👦 Laki-laki</option>
                        <option value="Perempuan" <?= ($atlet['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : '' ?>>👧 Perempuan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Kelompok Usia (KU) *</label>
                    <select name="kelompok_usia" id="kelompokUsiaSelect" class="form-control" required style="font-weight:700; color:#38bdf8;" onchange="syncKuHero(this.value)">
                        <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $ku): ?>
                            <option value="<?= $ku ?>" <?= $atlet['kelompok_usia'] == $ku ? 'selected' : '' ?>><?= $ku ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Status Keanggotaan *</label>
                    <select name="status_keanggotaan" class="form-control" required style="font-weight:700;">
                        <?php foreach (['Aktif','Non-Aktif','Alumni','Mutasi'] as $st): ?>
                            <option value="<?= $st ?>" <?= $atlet['status_keanggotaan'] == $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- SECTION B: DOKUMEN & LEGALITAS FISIK (MODULAR GLASS GRID) -->
        <div class="card" style="border:1px solid rgba(56,189,248,0.3); margin-bottom:1.25rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(56,189,248,0.25), rgba(14,165,233,0.15)); color:#38bdf8; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(56,189,248,0.3);">
                        📜
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">B. Dokumen & Legalitas Fisik Atlet</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Nomor registrasi KK/Akta dan berkas fisik terverifikasi</p>
                    </div>
                </div>

                <div style="display:flex; gap:6px;">
                    <span class="badge" style="background:rgba(56,189,248,0.15); color:#38bdf8; border:1px solid rgba(56,189,248,0.3); font-size:0.7rem;">PDF / PNG / JPG</span>
                    <span class="badge" style="background:rgba(52,211,153,0.15); color:#34d399; border:1px solid rgba(52,211,153,0.3); font-size:0.7rem;">Maks 5MB</span>
                </div>
            </div>

            <!-- MODULAR CARDS GRID: KK & AKTA -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.1rem;">
                
                <!-- CARD 1: KARTU KELUARGA (KK) -->
                <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1.1rem; border-radius:16px; display:flex; flex-direction:column; gap:0.85rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">
                            📜 Kartu Keluarga (KK)
                        </span>
                        <?php if (!empty($atlet['file_kk'])): ?>
                            <span class="badge badge-emerald" style="font-size:0.68rem; font-weight:800; border:1px solid rgba(52,211,153,0.4);">
                                VERIFIED ✓
                            </span>
                        <?php else: ?>
                            <span class="badge badge-rose" style="font-size:0.68rem; font-weight:800; border:1px solid rgba(244,63,94,0.4);">
                                BELUM ADA ⚠️
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem; font-weight:600; color:#cbd5e1;">Nomor Kartu Keluarga (16 Digit)</label>
                        <input type="text" name="no_kk" value="<?= htmlspecialchars($atlet['no_kk'] ?? '') ?>" class="form-control" placeholder="16 Digit Nomor KK" style="font-family:'Courier New', monospace; font-weight:700; letter-spacing:0.5px;" oninput="checkDigitLength(this, 'kkDigitHint', 16)">
                        <div id="kkDigitHint" style="font-size:0.72rem; margin-top:3px; min-height:16px;"></div>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem; font-weight:600; color:#cbd5e1;">File Scan Berkas KK</label>
                        <input type="file" name="file_kk" id="fileKkInput" class="form-control" accept="image/*,application/pdf" onchange="handleFileSelect(this, 'kkFileFeedback')">
                        <div id="kkFileFeedback" style="margin-top:6px;"></div>

                        <?php if (!empty($atlet['file_kk'])): ?>
                            <div style="margin-top:8px; background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.25); padding:0.6rem 0.8rem; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:0.75rem; color:#34d399; font-weight:600;">✓ Berkas Aktif Tersimpan</span>
                                <a href="../assets/docs/<?= htmlspecialchars($atlet['file_kk']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:0.3rem 0.6rem; color:#38bdf8; border-color:rgba(56,189,248,0.3);">
                                    📂 Lihat Berkas
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CARD 2: AKTA KELAHIRAN -->
                <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1.1rem; border-radius:16px; display:flex; flex-direction:column; gap:0.85rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; font-weight:700; color:#a78bfa; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">
                            📑 Akta Kelahiran
                        </span>
                        <?php if (!empty($atlet['file_akta'])): ?>
                            <span class="badge badge-emerald" style="font-size:0.68rem; font-weight:800; border:1px solid rgba(52,211,153,0.4);">
                                VERIFIED ✓
                            </span>
                        <?php else: ?>
                            <span class="badge badge-rose" style="font-size:0.68rem; font-weight:800; border:1px solid rgba(244,63,94,0.4);">
                                BELUM ADA ⚠️
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem; font-weight:600; color:#cbd5e1;">Nomor Registrasi Akta Kelahiran</label>
                        <input type="text" name="no_akta" value="<?= htmlspecialchars($atlet['no_akta'] ?? '') ?>" class="form-control" placeholder="Nomor Registrasi Akta" style="font-family:'Courier New', monospace; font-weight:700; letter-spacing:0.5px;">
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem; font-weight:600; color:#cbd5e1;">File Scan Berkas Akta</label>
                        <input type="file" name="file_akta" id="fileAktaInput" class="form-control" accept="image/*,application/pdf" onchange="handleFileSelect(this, 'aktaFileFeedback')">
                        <div id="aktaFileFeedback" style="margin-top:6px;"></div>

                        <?php if (!empty($atlet['file_akta'])): ?>
                            <div style="margin-top:8px; background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.25); padding:0.6rem 0.8rem; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:0.75rem; color:#34d399; font-weight:600;">✓ Berkas Aktif Tersimpan</span>
                                <a href="../assets/docs/<?= htmlspecialchars($atlet['file_akta']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:0.3rem 0.6rem; color:#38bdf8; border-color:rgba(56,189,248,0.3);">
                                    📂 Lihat Berkas
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- SECTION C: POSISI BERMAIN & ATRIBUT FISIK (INTERACTIVE MODULE) -->
        <div class="card" style="border:1px solid rgba(52,211,153,0.3); margin-bottom:1.25rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(52,211,153,0.25), rgba(16,185,129,0.15)); color:#34d399; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(52,211,153,0.3);">
                        ⚽
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">C. Posisi Bermain & Atribut Fisik Atlet</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Posisi utama, sekunder, kaki dominan, serta analisis postur tubuh</p>
                    </div>
                </div>

                <!-- LIVE BMI CALCULATOR BADGE CARD -->
                <div id="bmiBadge" style="background:rgba(16,185,129,0.12); border:1px solid rgba(52,211,153,0.3); padding:0.45rem 0.95rem; border-radius:12px; font-size:0.78rem; color:#34d399; font-weight:700; display:flex; align-items:center; gap:6px;">
                    ⚖️ Postur Atlet: <span id="bmiVal" style="color:#fff;">-</span>
                </div>
            </div>

            <?php
            $positions = [
                'GK (Kiper)', 'CB (Bek Tengah)', 'LB (Bek Kiri)', 'RB (Bek Kanan)',
                'DMF (Gelandang Bertahan)', 'CMF (Gelandang Tengah)', 'AMF (Gelandang Serang)',
                'LWF (Sayap Kiri)', 'RWF (Sayap Kanan)', 'CF (Penyerang Utama)'
            ];
            ?>

            <!-- QUICK POSITION PRESET CHIPS (POSISI UTAMA) -->
            <div style="background:rgba(15,23,42,0.5); padding:0.85rem 1rem; border-radius:14px; border:1px solid rgba(99,102,241,0.3); margin-bottom:0.85rem;">
                <div style="font-size:0.75rem; font-weight:700; color:#818cf8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.5rem;">
                    💡 Preset Cepat Posisi Utama :
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach ($positions as $pos): ?>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:0.35rem 0.65rem; border-radius:8px; background:rgba(99,102,241,0.12); color:#a5b4fc; border:1px solid rgba(99,102,241,0.25);" onclick="selectPosisiUtamaPreset('<?= htmlspecialchars($pos) ?>')">
                            ⚽ <?= $pos ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- QUICK POSITION PRESET CHIPS (POSISI SEKUNDER) -->
            <div style="background:rgba(15,23,42,0.5); padding:0.85rem 1rem; border-radius:14px; border:1px solid rgba(56,189,248,0.3); margin-bottom:1.25rem;">
                <div style="font-size:0.75rem; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.5rem;">
                    💡 Preset Cepat Posisi Sekunder (Alternatif) :
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach ($positions as $pos): ?>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:0.35rem 0.65rem; border-radius:8px; background:rgba(56,189,248,0.12); color:#7dd3fc; border:1px solid rgba(56,189,248,0.25);" onclick="selectPosisiSekunderPreset('<?= htmlspecialchars($pos) ?>')">
                            ⚽ <?= $pos ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Posisi Utama *</label>
                    <input type="text" name="posisi_utama" id="posisiUtamaInput" value="<?= htmlspecialchars($atlet['posisi_utama']) ?>" class="form-control" required placeholder="Contoh: Gelandang Serang (AMF)" oninput="syncPosisiHero(this.value)">
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Posisi Sekunder (Alternatif)</label>
                    <input type="text" name="posisi_sekunder" id="posisiSekunderInput" value="<?= htmlspecialchars($atlet['posisi_sekunder']) ?>" class="form-control" placeholder="Contoh: Sayap Kanan (RWF)" oninput="syncPosisiSekunderHero(this.value)">
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Kaki Dominan *</label>
                    <select name="kaki_dominan" class="form-control" required style="font-weight:700; color:#a78bfa;">
                        <option value="Kanan" <?= $atlet['kaki_dominan'] == 'Kanan' ? 'selected' : '' ?>>👞 Kanan</option>
                        <option value="Kiri" <?= $atlet['kaki_dominan'] == 'Kiri' ? 'selected' : '' ?>>👟 Kiri</option>
                        <option value="Keduanya" <?= $atlet['kaki_dominan'] == 'Keduanya' ? 'selected' : '' ?>>⚡ Keduanya (Ambidextrous)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Tinggi Badan (cm)</label>
                    <div style="position:relative;">
                        <input type="number" name="tinggi_badan" id="tinggiBadanInput" value="<?= $atlet['tinggi_badan'] ?>" class="form-control" placeholder="misal: 165" oninput="calculateBMI()">
                        <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.75rem; color:var(--text-muted); pointer-events:none;">cm</span>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Berat Badan (kg)</label>
                    <div style="position:relative;">
                        <input type="number" name="berat_badan" id="beratBadanInput" value="<?= $atlet['berat_badan'] ?>" class="form-control" placeholder="misal: 55" oninput="calculateBMI()">
                        <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.75rem; color:var(--text-muted); pointer-events:none;">kg</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION D: INFORMASI ORANG TUA / WALI & DOMISILI -->
        <div class="card" style="border:1px solid rgba(251,191,36,0.3); margin-bottom:1.5rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(251,191,36,0.25), rgba(245,158,11,0.15)); color:#fbbf24; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(251,191,36,0.3);">
                        👨‍👩‍👦
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">D. Informasi Orang Tua / Wali & Domisili</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Kontak WhatsApp darurat dan alamat rumah tempat tinggal</p>
                    </div>
                </div>

                <span style="font-size:0.72rem; color:#fbbf24; background:rgba(251,191,36,0.12); padding:4px 10px; border-radius:20px; border:1px solid rgba(251,191,36,0.25); font-weight:600;">
                    💬 Direct WhatsApp Integration
                </span>
            </div>

            <!-- MODULAR PARENT & CONTACT GRID -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; margin-bottom:1.25rem;">
                
                <!-- AYAH KANDUNG -->
                <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1rem; border-radius:14px;">
                    <label style="font-size:0.82rem; font-weight:700; color:#38bdf8; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                        👨 Nama Ayah Kandung
                    </label>
                    <input type="text" name="nama_ayah" value="<?= htmlspecialchars($atlet['nama_ayah'] ?? '') ?>" class="form-control" placeholder="Nama lengkap Ayah Kandung">
                </div>

                <!-- IBU KANDUNG -->
                <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1rem; border-radius:14px;">
                    <label style="font-size:0.82rem; font-weight:700; color:#a78bfa; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                        👩 Nama Ibu Kandung
                    </label>
                    <input type="text" name="nama_ibu" value="<?= htmlspecialchars($atlet['nama_ibu'] ?? '') ?>" class="form-control" placeholder="Nama lengkap Ibu Kandung">
                </div>

                <!-- WHATSAPP WALI WITH LIVE TEST BUTTON -->
                <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1rem; border-radius:14px; grid-column:1 / -1;">
                    <label style="font-size:0.82rem; font-weight:700; color:#34d399; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                        💬 No. WhatsApp Wali Siswa (Aktif / Darurat) *
                    </label>
                    <input type="text" name="no_whatsapp" id="noWhatsappInput" value="<?= htmlspecialchars($atlet['no_whatsapp'] ?? '') ?>" class="form-control" placeholder="Contoh: 081234567890" style="font-family:'Courier New', monospace; font-weight:700; letter-spacing:0.5px;" oninput="updateWaTestButton(this.value)">
                    <div id="waTestContainer" style="margin-top:4px;"></div>
                </div>

            </div>

            <!-- ALAMAT DOMISILI -->
            <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1rem; border-radius:14px;">
                <label style="font-size:0.82rem; font-weight:700; color:#fbbf24; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                    📍 Alamat Domisili Tempat Tinggal
                </label>
                <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat lengkap (Jalan, RT/RW, Kelurahan, Kecamatan, Kota)"><?= htmlspecialchars($atlet['alamat'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- FLOATING / STICKY ACTION BAR -->
        <div style="background:rgba(15,23,42,0.9); border:1px solid rgba(99,102,241,0.3); padding:1rem 1.25rem; border-radius:16px; backdrop-filter:blur(10px); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
            <div style="font-size:0.8rem; color:var(--text-muted);">
                💡 Pastikan seluruh data bertanda bintang (<span style="color:#f87171;">*</span>) telah terisi dengan benar sebelum menyimpan.
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem;">
                <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary" style="padding:0.6rem 1.2rem;">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem; font-weight:700; box-shadow:0 4px 15px rgba(99,102,241,0.4);">
                    💾 Simpan Perubahan
                </button>
            </div>
        </div>

    </form>

</div>

<script>
function updateWaTestButton(val) {
    const waContainer = document.getElementById('waTestContainer');
    if (!waContainer) return;

    const rawNum = val.replace(/[^0-9]/g, '');
    if (rawNum.length >= 9) {
        let formatted = rawNum;
        if (formatted.startsWith('0')) {
            formatted = '62' + formatted.substring(1);
        } else if (!formatted.startsWith('62')) {
            formatted = '62' + formatted;
        }
        const text = encodeURIComponent("Halo Wali Atlet SSB Tamalanrea, ");
        waContainer.innerHTML = `
            <a href="https://wa.me/${formatted}?text=${text}" target="_blank" class="btn" style="background:#22c55e; color:#fff; font-weight:700; font-size:0.75rem; padding:0.4rem 0.85rem; border-radius:10px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(34,197,94,0.3); text-decoration:none; margin-top:6px;">
                💬 Tes Chat WA: +${formatted}
            </a>
        `;
    } else {
        waContainer.innerHTML = '';
    }
}

function selectPosisiUtamaPreset(pos) {
    const utamaInput = document.getElementById('posisiUtamaInput');
    if (utamaInput) {
        utamaInput.value = pos;
        syncPosisiHero(pos);
    }
}

function selectPosisiSekunderPreset(pos) {
    const sekunderInput = document.getElementById('posisiSekunderInput');
    if (sekunderInput) {
        sekunderInput.value = pos;
        syncPosisiSekunderHero(pos);
    }
}

function syncPosisiSekunderHero(val) {
    const chip = document.getElementById('heroPosisiSekunderChip');
    if (chip) {
        chip.innerText = (val && val !== '-') ? val : '-';
    }
}

function handleFileSelect(input, feedbackId) {
    const feedback = document.getElementById(feedbackId);
    if (!feedback) return;

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        feedback.innerHTML = `<span style="background:rgba(56,189,248,0.15); border:1px solid rgba(56,189,248,0.4); color:#38bdf8; padding:4px 10px; border-radius:8px; font-size:0.75rem; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            📄 Berkas Baru Terpilih: <strong>${file.name}</strong> (${fileSizeMB} MB)
        </span>`;
    } else {
        feedback.innerHTML = '';
    }
}

function checkDigitLength(input, hintId, targetLength) {
    const hint = document.getElementById(hintId);
    if (!hint) return;

    const val = input.value.replace(/\D/g, '');
    if (val.length === 0) {
        hint.innerHTML = '';
    } else if (val.length === targetLength) {
        hint.innerHTML = `<span style="color:#34d399; font-weight:700;">✓ Sesuai (${val.length}/${targetLength} Digit)</span>`;
    } else {
        hint.innerHTML = `<span style="color:#fbbf24; font-weight:600;">⚠️ ${val.length} Digit (Standar: ${targetLength} Digit)</span>`;
    }
}

function syncNamaHero(val) {
    const heroTitle = document.getElementById('heroNamaTitle');
    if (heroTitle) {
        heroTitle.innerText = val ? 'Edit Profil: ' + val : 'Edit Profil Atlet';
    }
}

function syncPosisiHero(val) {
    const chip = document.getElementById('heroPosisiChip');
    if (chip) {
        chip.innerText = val ? val : '-';
    }
}

function syncKuHero(val) {
    const badge = document.getElementById('heroKuBadge');
    if (badge) {
        badge.innerText = 'KATEGORI ' + val;
    }
}

function autoSyncKU() {
    const tglInput = document.getElementById('tanggalLahirInput');
    const kuSelect = document.getElementById('kelompokUsiaSelect');
    const hint = document.getElementById('kuHint');

    if (!tglInput || !tglInput.value) return;

    const birthDate = new Date(tglInput.value);
    const today = new Date();
    
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    let ku = 'Senior';
    if (age <= 8) ku = 'U-8';
    else if (age <= 10) ku = 'U-10';
    else if (age <= 12) ku = 'U-12';
    else if (age <= 14) ku = 'U-14';
    else if (age <= 16) ku = 'U-16';
    else if (age <= 18) ku = 'U-18';

    kuSelect.value = ku;
    syncKuHero(ku);
    if (hint) {
        hint.innerHTML = `💡 Usia Atlet: <strong style="color:#38bdf8;">${age} Tahun</strong> &bull; Disinkronkan otomatis ke <strong style="color:#38bdf8;">${ku}</strong>`;
    }
}

function calculateBMI() {
    const tbInput = document.getElementById('tinggiBadanInput');
    const bbInput = document.getElementById('beratBadanInput');
    const bmiVal = document.getElementById('bmiVal');

    if (!tbInput || !bbInput || !bmiVal) return;

    const tb = parseFloat(tbInput.value);
    const bb = parseFloat(bbInput.value);

    if (tb > 0 && bb > 0) {
        const tbMeter = tb / 100;
        const bmi = (bb / (tbMeter * tbMeter)).toFixed(1);
        let category = 'Ideal';
        let color = '#34d399';

        if (bmi < 18.5) { category = 'Kurus'; color = '#fbbf24'; }
        else if (bmi <= 24.9) { category = 'Ideal ⚽'; color = '#34d399'; }
        else if (bmi <= 29.9) { category = 'Berisi'; color = '#fbbf24'; }
        else { category = 'Overweight'; color = '#f87171'; }

        bmiVal.innerHTML = `<strong style="color:${color};">${bmi} (${category})</strong>`;
    } else {
        bmiVal.innerText = '-';
    }
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const heroImg = document.getElementById('heroAvatarImage');
            const heroInitials = document.getElementById('heroAvatarInitials');
            const sectionImg = document.getElementById('sectionAvatarImage');
            const sectionInitials = document.getElementById('sectionAvatarInitials');
            
            if (heroImg) {
                heroImg.src = e.target.result;
                heroImg.style.display = 'block';
            }
            if (heroInitials) {
                heroInitials.style.display = 'none';
            }
            if (sectionImg) {
                sectionImg.src = e.target.result;
                sectionImg.style.display = 'block';
            }
            if (sectionInitials) {
                sectionInitials.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    autoSyncKU();
    calculateBMI();
    const kkInput = document.querySelector('input[name="no_kk"]');
    if (kkInput) checkDigitLength(kkInput, 'kkDigitHint', 16);
    const waInput = document.getElementById('noWhatsappInput');
    if (waInput && waInput.value) updateWaTestButton(waInput.value);
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
