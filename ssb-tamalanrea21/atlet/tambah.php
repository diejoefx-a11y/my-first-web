<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pdo = getPdo();

$pageTitle = "Tambah Atlet Baru";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn_nik_raw = trim($_POST['nisn_nik'] ?? '');
    $nisn_nik = !empty($nisn_nik_raw) ? $nisn_nik_raw : null;
    $no_kk = trim($_POST['no_kk'] ?? '');
    $no_akta = trim($_POST['no_akta'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'Laki-laki';
    $posisi_utama = $_POST['posisi_utama'] ?? 'Gelandang Tengah (CM)';
    $posisi_sekunder = trim($_POST['posisi_sekunder'] ?? '-');
    $kaki_dominan = $_POST['kaki_dominan'] ?? 'Kanan';
    $tinggi_badan = (int)($_POST['tinggi_badan'] ?? 0);
    $berat_badan = (int)($_POST['berat_badan'] ?? 0);
    $kelompok_usia = $_POST['kelompok_usia'] ?? 'U-12';
    $status_keanggotaan = $_POST['status_keanggotaan'] ?? 'Aktif';

    // Ortu fields
    $nama_ayah = trim($_POST['nama_ayah'] ?? '');
    $nama_ibu = trim($_POST['nama_ibu'] ?? '');
    $no_whatsapp = trim($_POST['no_whatsapp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (empty($nama_lengkap) || empty($tanggal_lahir) || empty($no_whatsapp)) {
        $error = "Mohon isi Nama Lengkap, Tanggal Lahir, dan Nomor WhatsApp Orang Tua!";
    } else {
        try {
            // Check if NISN / NIK already registered
            if ($nisn_nik !== null) {
                $checkStmt = $pdo->prepare("SELECT id FROM atlet WHERE nisn_nik = ?");
                $checkStmt->execute([$nisn_nik]);
                if ($checkStmt->fetch()) {
                    throw new Exception("NISN / NIK '$nisn_nik' sudah terdaftar pada sistem untuk atlet lain!");
                }
            }

            // Handle Foto Profil upload
            $foto_profil = 'default_avatar.png';
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
            $file_kk = null;
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
            $file_akta = null;
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

            $defaultPass = password_hash('atlet123', PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            // Insert Atlet
            $stmt = $pdo->prepare("INSERT INTO atlet (nisn_nik, no_kk, no_akta, password, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, posisi_utama, posisi_sekunder, kaki_dominan, tinggi_badan, berat_badan, kelompok_usia, foto_profil, file_kk, file_akta, status_keanggotaan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nisn_nik, $no_kk, $no_akta, $defaultPass, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $posisi_utama, $posisi_sekunder, $kaki_dominan, $tinggi_badan, $berat_badan, $kelompok_usia, $foto_profil, $file_kk, $file_akta, $status_keanggotaan]);
            $atletId = $pdo->lastInsertId();

            // Insert Ortu
            $stmtOrtu = $pdo->prepare("INSERT INTO orang_tua (atlet_id, nama_ayah, nama_ibu, no_whatsapp, alamat) VALUES (?, ?, ?, ?, ?)");
            $stmtOrtu->execute([$atletId, $nama_ayah, $nama_ibu, $no_whatsapp, $alamat]);

            // Insert initial default evaluation (all attributes set to 10)
            $stmtEval = $pdo->prepare("INSERT INTO evaluasi_atlet (atlet_id, tanggal_evaluasi, nilai_passing, nilai_dribbling, nilai_shooting, nilai_tackling, nilai_stamina, nilai_disiplin, vo2max, catatan_pelatih) VALUES (?, ?, 10, 10, 10, 10, 10, 10, 10.0, 'Pendaftaran atlet baru di SSB Tamalanrea.')");
            $stmtEval->execute([$atletId, date('Y-m-d')]);


            // Insert initial monthly SPP
            $stmtSpp = $pdo->prepare("INSERT INTO iuran_spp (atlet_id, bulan, tahun, jumlah, status_bayar, keterangan) VALUES (?, ?, ?, 150000, 'Belum Bayar', 'SPP Pendaftaran Bulanan')");
            $stmtSpp->execute([$atletId, (int)date('n'), (int)date('Y')]);

            $pdo->commit();

            header("Location: detail.php?id=$atletId&success=added");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:850px; margin:0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">Formulir Pendaftaran Atlet Baru</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Isi data lengkap atlet untuk pendaftaran di SSB Tamalanrea</p>
        </div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Kembali ke Daftar Atlet</a>
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
                <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Muhammad Fikri" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>NISN / NIK (Opsional)</label>
                <input type="text" name="nisn_nik" class="form-control" placeholder="Nomor NISN atau NIK" value="<?= htmlspecialchars($_POST['nisn_nik'] ?? '') ?>">
                <small style="font-size:0.75rem; color:var(--text-muted);">Biarkan kosong jika belum memiliki NISN/NIK</small>
            </div>

            <div class="form-group">
                <label>Tempat Lahir</label>
                <input type="text" name="tempat_lahir" class="form-control" placeholder="Kota Lahir" value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Tanggal Lahir *</label>
                <input type="date" name="tanggal_lahir" id="tanggalLahirInput" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>" required onchange="autoSyncKU()">
                <small id="kuHint" style="font-size:0.75rem; color:#818cf8; display:block; margin-top:3px;"></small>
            </div>

            <div class="form-group">
                <label>Kelompok Usia (KU) *</label>
                <select name="kelompok_usia" id="kelompokUsiaSelect" class="form-control" required>
                    <?php $selectedKu = $_POST['kelompok_usia'] ?? 'U-12'; ?>
                    <option value="U-8" <?= $selectedKu == 'U-8' ? 'selected' : '' ?>>U-8 (Usia Dini)</option>
                    <option value="U-10" <?= $selectedKu == 'U-10' ? 'selected' : '' ?>>U-10 (Pratama)</option>
                    <option value="U-12" <?= $selectedKu == 'U-12' ? 'selected' : '' ?>>U-12 (Muda)</option>
                    <option value="U-14" <?= $selectedKu == 'U-14' ? 'selected' : '' ?>>U-14 (Madya)</option>
                    <option value="U-16" <?= $selectedKu == 'U-16' ? 'selected' : '' ?>>U-16 (Utama)</option>
                    <option value="U-18" <?= $selectedKu == 'U-18' ? 'selected' : '' ?>>U-18 (Taruna)</option>
                    <option value="Senior" <?= $selectedKu == 'Senior' ? 'selected' : '' ?>>Senior</option>
                </select>
            </div>


            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin" class="form-control">
                    <?php $selectedJk = $_POST['jenis_kelamin'] ?? 'Laki-laki'; ?>
                    <option value="Laki-laki" <?= $selectedJk == 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="Perempuan" <?= $selectedJk == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>

            <div class="form-group">
                <label>Foto Profil Atlet (Opsional)</label>
                <input type="file" name="foto_profil" class="form-control" accept="image/*">
                <small style="font-size:0.75rem; color:var(--text-muted);">Format: JPG, PNG, WEBP</small>
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem; margin-top:1.5rem;">B. DOKUMEN & LEGALITAS</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>No. Kartu Keluarga (KK)</label>
                <input type="text" name="no_kk" class="form-control" placeholder="16 Digit No. Kartu Keluarga" value="<?= htmlspecialchars($_POST['no_kk'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Berkas Scan Kartu Keluarga (KK)</label>
                <input type="file" name="file_kk" class="form-control" accept="image/*,application/pdf">
                <small style="font-size:0.75rem; color:var(--text-muted);">Format: JPG, PNG, PDF (Maks 3MB)</small>
            </div>

            <div class="form-group">
                <label>No. Registrasi Akta Kelahiran</label>
                <input type="text" name="no_akta" class="form-control" placeholder="Nomor Akta Kelahiran" value="<?= htmlspecialchars($_POST['no_akta'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Berkas Scan Akta Kelahiran</label>
                <input type="file" name="file_akta" class="form-control" accept="image/*,application/pdf">
                <small style="font-size:0.75rem; color:var(--text-muted);">Format: JPG, PNG, PDF (Maks 3MB)</small>
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem; margin-top:1.5rem;">C. POSISI & ATRIBUT FISIK</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Posisi Utama *</label>
                <select name="posisi_utama" class="form-control" required>
                    <?php $selectedPos = $_POST['posisi_utama'] ?? 'Gelandang Tengah (CM)'; ?>
                    <option value="Kiper (GK)" <?= $selectedPos == 'Kiper (GK)' ? 'selected' : '' ?>>Kiper (GK)</option>
                    <option value="Bek Tengah (CB)" <?= $selectedPos == 'Bek Tengah (CB)' ? 'selected' : '' ?>>Bek Tengah (CB)</option>
                    <option value="Bek Kanan (RB)" <?= $selectedPos == 'Bek Kanan (RB)' ? 'selected' : '' ?>>Bek Kanan (RB)</option>
                    <option value="Bek Kiri (LB)" <?= $selectedPos == 'Bek Kiri (LB)' ? 'selected' : '' ?>>Bek Kiri (LB)</option>
                    <option value="Gelandang Bertahan (DM)" <?= $selectedPos == 'Gelandang Bertahan (DM)' ? 'selected' : '' ?>>Gelandang Bertahan (DM)</option>
                    <option value="Gelandang Tengah (CM)" <?= $selectedPos == 'Gelandang Tengah (CM)' ? 'selected' : '' ?>>Gelandang Tengah (CM)</option>
                    <option value="Gelandang Serang (CAM)" <?= $selectedPos == 'Gelandang Serang (CAM)' ? 'selected' : '' ?>>Gelandang Serang (CAM)</option>
                    <option value="Penyerang Sayap (Winger)" <?= $selectedPos == 'Penyerang Sayap (Winger)' ? 'selected' : '' ?>>Penyerang Sayap (Winger)</option>
                    <option value="Penyerang (FW)" <?= $selectedPos == 'Penyerang (FW)' ? 'selected' : '' ?>>Penyerang (FW)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Posisi Sekunder</label>
                <input type="text" name="posisi_sekunder" class="form-control" placeholder="Contoh: Bek Kanan, Sayap" value="<?= htmlspecialchars($_POST['posisi_sekunder'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Kaki Dominan</label>
                <select name="kaki_dominan" class="form-control">
                    <?php $selectedKaki = $_POST['kaki_dominan'] ?? 'Kanan'; ?>
                    <option value="Kanan" <?= $selectedKaki == 'Kanan' ? 'selected' : '' ?>>Kanan</option>
                    <option value="Kiri" <?= $selectedKaki == 'Kiri' ? 'selected' : '' ?>>Kiri</option>
                    <option value="Keduanya" <?= $selectedKaki == 'Keduanya' ? 'selected' : '' ?>>Keduanya (Ambidextrous)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tinggi Badan (cm)</label>
                <input type="number" name="tinggi_badan" class="form-control" placeholder="145" value="<?= htmlspecialchars($_POST['tinggi_badan'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Berat Badan (kg)</label>
                <input type="number" name="berat_badan" class="form-control" placeholder="40" value="<?= htmlspecialchars($_POST['berat_badan'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Status Keanggotaan</label>
                <select name="status_keanggotaan" class="form-control">
                    <?php $selectedSt = $_POST['status_keanggotaan'] ?? 'Aktif'; ?>
                    <option value="Aktif" <?= $selectedSt == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Non-Aktif" <?= $selectedSt == 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
                </select>
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem; margin-top:1.5rem;">D. INFORMASI ORANG TUA / WALI</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Nama Ayah</label>
                <input type="text" name="nama_ayah" class="form-control" placeholder="Nama Ayah Kandung" value="<?= htmlspecialchars($_POST['nama_ayah'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Nama Ibu</label>
                <input type="text" name="nama_ibu" class="form-control" placeholder="Nama Ibu Kandung" value="<?= htmlspecialchars($_POST['nama_ibu'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>No. WhatsApp Aktif *</label>
                <input type="text" name="no_whatsapp" class="form-control" placeholder="081234567890" value="<?= htmlspecialchars($_POST['no_whatsapp'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:2rem;">
            <label>Alamat Rumah Lengkap</label>
            <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat tinggal atlet..."><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <a href="index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Data Atlet</button>
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



