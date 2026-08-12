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

$pageTitle = "Edit Data Atlet - " . htmlspecialchars($atlet['nama_lengkap']);
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

<div class="card" style="max-width:850px; margin:0 auto;">
    <div class="card-header">
        <h2 class="card-title">Edit Profil Atlet</h2>
        <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">&larr; Batal & Kembali</a>
    </div>

    <?php if ($error): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:1rem; border-radius:12px; margin-bottom:1.5rem;">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem;">A. BIODATA ATLET</h3>
        
        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Nama Lengkap Atlet *</label>
                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($atlet['nama_lengkap']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>NISN / NIK</label>
                <input type="text" name="nisn_nik" value="<?= htmlspecialchars($atlet['nisn_nik'] ?? '') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($atlet['tempat_lahir'] ?? '') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Tanggal Lahir *</label>
                <input type="date" name="tanggal_lahir" id="tanggalLahirInput" value="<?= $atlet['tanggal_lahir'] ?>" class="form-control" required onchange="autoSyncKU()">
                <small id="kuHint" style="font-size:0.75rem; color:#818cf8; display:block; margin-top:3px;"></small>
            </div>

            <div class="form-group">
                <label>Kelompok Usia (KU) *</label>
                <select name="kelompok_usia" id="kelompokUsiaSelect" class="form-control" required>
                    <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $ku): ?>
                        <option value="<?= $ku ?>" <?= $atlet['kelompok_usia'] == $ku ? 'selected' : '' ?>><?= $ku ?></option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="form-group">
                <label>Status Keanggotaan</label>
                <select name="status_keanggotaan" class="form-control">
                    <?php foreach (['Aktif','Non-Aktif','Alumni','Mutasi'] as $st): ?>
                        <option value="<?= $st ?>" <?= $atlet['status_keanggotaan'] == $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Foto Profil Atlet</label>
                <div style="display:flex; align-items:center; gap:1.5rem; margin-top:6px;">
                    <?php
                        $photoPath = __DIR__ . '/../assets/img/atlet/' . ($atlet['foto_profil'] ?? '');
                        $hasPhoto = !empty($atlet['foto_profil']) && $atlet['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
                    ?>
                    <div style="width:70px; height:70px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; overflow:hidden; border:2px solid var(--primary); flex-shrink:0;">
                        <?php if ($hasPhoto): ?>
                            <img src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span style="font-size:1.5rem; font-weight:800; color:#fff;"><?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <input type="file" name="foto_profil" class="form-control" accept="image/*">
                        <small style="font-size:0.75rem; color:var(--text-muted);">Pilih foto baru untuk mengganti. Format: JPG, PNG, WEBP (Maks 2MB)</small>
                    </div>
                </div>
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem; margin-top:1.5rem;">B. DOKUMEN & LEGALITAS</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>No. Kartu Keluarga (KK)</label>
                <input type="text" name="no_kk" value="<?= htmlspecialchars($atlet['no_kk'] ?? '') ?>" class="form-control" placeholder="16 Digit No. Kartu Keluarga">
            </div>

            <div class="form-group">
                <label>Berkas Scan Kartu Keluarga (KK)</label>
                <input type="file" name="file_kk" class="form-control" accept="image/*,application/pdf">
                <?php if (!empty($atlet['file_kk'])): ?>
                    <small style="font-size:0.75rem; color:#34d399; display:block; margin-top:4px;">
                        ✓ Berkas KK tersimpan: <a href="../assets/docs/<?= htmlspecialchars($atlet['file_kk']) ?>" target="_blank" style="color:#60a5fa; text-decoration:underline;">Lihat Dokumen</a>
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>No. Registrasi Akta Kelahiran</label>
                <input type="text" name="no_akta" value="<?= htmlspecialchars($atlet['no_akta'] ?? '') ?>" class="form-control" placeholder="Nomor Akta Kelahiran">
            </div>

            <div class="form-group">
                <label>Berkas Scan Akta Kelahiran</label>
                <input type="file" name="file_akta" class="form-control" accept="image/*,application/pdf">
                <?php if (!empty($atlet['file_akta'])): ?>
                    <small style="font-size:0.75rem; color:#34d399; display:block; margin-top:4px;">
                        ✓ Berkas Akta tersimpan: <a href="../assets/docs/<?= htmlspecialchars($atlet['file_akta']) ?>" target="_blank" style="color:#60a5fa; text-decoration:underline;">Lihat Dokumen</a>
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem; margin-top:1.5rem;">C. POSISI & ATRIBUT FISIK</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Posisi Utama *</label>
                <input type="text" name="posisi_utama" value="<?= htmlspecialchars($atlet['posisi_utama']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Posisi Sekunder</label>
                <input type="text" name="posisi_sekunder" value="<?= htmlspecialchars($atlet['posisi_sekunder']) ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Kaki Dominan</label>
                <select name="kaki_dominan" class="form-control">
                    <option value="Kanan" <?= $atlet['kaki_dominan'] == 'Kanan' ? 'selected' : '' ?>>Kanan</option>
                    <option value="Kiri" <?= $atlet['kaki_dominan'] == 'Kiri' ? 'selected' : '' ?>>Kiri</option>
                    <option value="Keduanya" <?= $atlet['kaki_dominan'] == 'Keduanya' ? 'selected' : '' ?>>Keduanya</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tinggi Badan (cm)</label>
                <input type="number" name="tinggi_badan" value="<?= $atlet['tinggi_badan'] ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Berat Badan (kg)</label>
                <input type="number" name="berat_badan" value="<?= $atlet['berat_badan'] ?>" class="form-control">
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem; margin-top:1.5rem;">D. INFORMASI ORANG TUA / WALI</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Nama Ayah</label>
                <input type="text" name="nama_ayah" value="<?= htmlspecialchars($atlet['nama_ayah'] ?? '') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Nama Ibu</label>
                <input type="text" name="nama_ibu" value="<?= htmlspecialchars($atlet['nama_ibu'] ?? '') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>No. WhatsApp</label>
                <input type="text" name="no_whatsapp" value="<?= htmlspecialchars($atlet['no_whatsapp'] ?? '') ?>" class="form-control">
            </div>
        </div>

        <div class="form-group" style="margin-bottom:2rem;">
            <label>Alamat Rumah</label>
            <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($atlet['alamat'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
<script>
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
    if (hint) {
        hint.innerText = `💡 Usia: ${age} tahun -> Kelompok Usia disinkronkan otomatis ke ${ku}`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    autoSyncKU();
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>



