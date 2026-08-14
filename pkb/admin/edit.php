<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: keluarga.php");
    exit;
}

$db = get_db();
$errors = [];

// Fetch current data
$stmt = $db->prepare("SELECT * FROM families WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$family = $stmt->fetch();

if (!$family) {
    header("Location: keluarga.php");
    exit;
}

// Fetch groups list for dropdown
$groupsList = $db->query("SELECT id, nomor_kelompok, nama_kelompok FROM `groups` ORDER BY nomor_kelompok ASC")->fetchAll();

// Handle POST Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF Token if present
    $token = $_POST['csrf_token'] ?? '';
    if (!empty($token) && !verify_csrf_token($token)) {
        $errors[] = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    }

    $no_kk = preg_replace('/[^0-9]/', '', $_POST['no_kk'] ?? '');
    $nik_kepala = preg_replace('/[^0-9]/', '', $_POST['nik_kepala'] ?? '');
    $nama_kepala = clean($_POST['nama_kepala'] ?? '');
    $kelompok_id = !empty($_POST['kelompok_id']) ? intval($_POST['kelompok_id']) : null;
    $no_hp = clean($_POST['no_hp'] ?? '');
    $rt = clean($_POST['rt'] ?? '');
    $rw = clean($_POST['rw'] ?? '');
    $kelurahan = clean($_POST['kelurahan'] ?? '');
    $kecamatan = clean($_POST['kecamatan'] ?? '');
    $alamat_lengkap = clean($_POST['alamat_lengkap'] ?? '');
    $latitude = clean($_POST['latitude'] ?? '');
    $longitude = clean($_POST['longitude'] ?? '');
    $jumlah_tanggungan = intval($_POST['jumlah_tanggungan'] ?? 0);
    $status_verifikasi = clean($_POST['status_verifikasi'] ?? 'pending');
    $members = $_POST['members'] ?? [];

    if (empty($no_kk) || strlen($no_kk) < 10) {
        $errors[] = "Nomor KK wajib diisi (minimal 10 s/d 16 digit angka).";
    }
    if (empty($nik_kepala) || strlen($nik_kepala) < 10) {
        $errors[] = "NIK Kepala Keluarga wajib diisi (minimal 10 s/d 16 digit angka).";
    }
    if (empty($nama_kepala)) {
        $errors[] = "Nama Kepala Keluarga wajib diisi.";
    }
    if (empty($latitude) || empty($longitude)) {
        $errors[] = "Koordinat peta wajib ditentukan.";
    }

    // Handle Foto Keluarga
    $foto_keluarga_name = $family['foto_keluarga'] ?? null;
    if (isset($_FILES['foto_keluarga']) && $_FILES['foto_keluarga']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_keluarga']['tmp_name'];
        $fileName = $_FILES['foto_keluarga']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExt, $allowedExts) && $_FILES['foto_keluarga']['size'] <= 5 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $foto_keluarga_name = 'keluarga_' . time() . '_' . uniqid() . '.' . $fileExt;
            move_uploaded_file($fileTmp, $uploadDir . $foto_keluarga_name);
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmtUpdate = $db->prepare("
                UPDATE families SET
                    no_kk = ?, nik_kepala = ?, nama_kepala = ?, kelompok_id = ?, no_hp = ?,
                    rt = ?, rw = ?, kelurahan = ?, kecamatan = ?,
                    alamat_lengkap = ?, latitude = ?, longitude = ?,
                    jumlah_tanggungan = ?, foto_keluarga = ?, status_verifikasi = ?
                WHERE id = ?
            ");

            $stmtUpdate->execute([
                $no_kk, $nik_kepala, $nama_kepala, $kelompok_id, $no_hp,
                $rt, $rw, $kelurahan, $kecamatan,
                $alamat_lengkap, $latitude, $longitude,
                $jumlah_tanggungan, $foto_keluarga_name, $status_verifikasi, $id
            ]);

            // Replace members
            $db->prepare("DELETE FROM family_members WHERE family_id = ?")->execute([$id]);

            if (!empty($members) && is_array($members)) {
                $stmtMember = $db->prepare("
                    INSERT INTO family_members (
                        family_id, nik, nama_lengkap, hubungan_keluarga, jenis_kelamin,
                        tempat_lahir, tanggal_lahir
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?
                    )
                ");

                foreach ($members as $m) {
                    $m_nama = clean($m['nama_lengkap'] ?? '');
                    $m_nik = preg_replace('/[^0-9]/', '', $m['nik'] ?? '');
                    if (empty($m_nik)) {
                        $m_nik = $nik_kepala;
                    }
                    $m_hubungan = clean($m['hubungan_keluarga'] ?? 'Anggota');
                    $m_jk = clean($m['jenis_kelamin'] ?? 'L');
                    $m_tempat = clean($m['tempat_lahir'] ?? '');
                    $m_tgl = !empty($m['tanggal_lahir']) ? $m['tanggal_lahir'] : null;

                    if (!empty($m_nama)) {
                        $stmtMember->execute([
                            $id, $m_nik, $m_nama, $m_hubungan, $m_jk,
                            $m_tempat, $m_tgl
                        ]);
                    }
                }
            }

            $db->commit();
            set_flash('success', 'Data keluarga berhasil diperbarui!');
            header("Location: detail.php?id=" . $id);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

$stmtMembers = $db->prepare("SELECT * FROM family_members WHERE family_id = ? ORDER BY id ASC");
$stmtMembers->execute([$id]);
$currentMembers = $stmtMembers->fetchAll();

$pageTitle = "Edit Data Keluarga - " . htmlspecialchars($family['nama_kepala']);
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="detail.php?id=<?= $family['id'] ?>" class="btn btn-outline btn-sm">← Batal & Kembali ke Detail</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= $err ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data" id="form-keluarga">
    <?= csrf_field() ?>
    <!-- Pokok -->
    <div class="card">
        <div class="card-title-section">
            <div class="card-icon">✏️</div>
            <div>
                <h3>Edit Data Pokok Kepala Keluarga</h3>
                <small style="color: var(--text-muted);">Ubah data identitas KK, kelompok, dan status verifikasi</small>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Nomor KK <span class="required">*</span></label>
                <input type="text" name="no_kk" class="form-control" maxlength="20" placeholder="16 digit angka" value="<?= htmlspecialchars($family['no_kk']) ?>" required>
            </div>
            <div class="form-group">
                <label>NIK Kepala Keluarga <span class="required">*</span></label>
                <input type="text" name="nik_kepala" class="form-control" maxlength="20" placeholder="16 digit angka" value="<?= htmlspecialchars($family['nik_kepala']) ?>" required>
            </div>
            <div class="form-group">
                <label>Nama Kepala Keluarga <span class="required">*</span></label>
                <input type="text" name="nama_kepala" class="form-control" value="<?= htmlspecialchars($family['nama_kepala']) ?>" required>
            </div>
            <div class="form-group">
                <label>Kelompok Domisili (1 - 14) <span class="required">*</span></label>
                <select name="kelompok_id" class="form-control" required>
                    <option value="">-- Pilih Kelompok --</option>
                    <?php foreach ($groupsList as $grp): ?>
                        <option value="<?= $grp['id'] ?>" <?= $family['kelompok_id'] == $grp['id'] ? 'selected' : '' ?>>
                            Kelompok <?= $grp['nomor_kelompok'] ?> - <?= htmlspecialchars($grp['nama_kelompok']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>No. WhatsApp <span class="required">*</span></label>
                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($family['no_hp']) ?>" required>
            </div>
            <div class="form-group">
                <label>Jumlah Tanggungan</label>
                <input type="number" name="jumlah_tanggungan" class="form-control" value="<?= $family['jumlah_tanggungan'] ?>">
            </div>
            <div class="form-group">
                <label>Status Verifikasi</label>
                <select name="status_verifikasi" class="form-control">
                    <option value="pending" <?= $family['status_verifikasi'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="terverifikasi" <?= $family['status_verifikasi'] === 'terverifikasi' ? 'selected' : '' ?>>Terverifikasi</option>
                    <option value="ditolak" <?= $family['status_verifikasi'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                </select>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Ganti / Unggah Foto Keluarga (Opsional)</label>
                <input type="file" name="foto_keluarga" class="form-control" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($family['foto_keluarga'])): ?>
                    <small style="color: #7c3aed; display: block; margin-top: 4px;">Foto saat ini: <a href="<?= base_url('uploads/' . $family['foto_keluarga']) ?>" target="_blank">Lihat Foto Keluarga</a></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alamat & Map -->
    <div class="card">
        <div class="card-title-section">
            <div class="card-icon">📍</div>
            <div>
                <h3>Edit Alamat & Titik Lokasi Rumah</h3>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label>RT</label>
                <input type="text" name="rt" class="form-control" value="<?= htmlspecialchars($family['rt']) ?>">
            </div>
            <div class="form-group">
                <label>RW</label>
                <input type="text" name="rw" class="form-control" value="<?= htmlspecialchars($family['rw']) ?>">
            </div>
            <div class="form-group">
                <label>Kelurahan</label>
                <input type="text" name="kelurahan" class="form-control" value="<?= htmlspecialchars($family['kelurahan']) ?>">
            </div>
            <div class="form-group">
                <label>Kecamatan</label>
                <input type="text" name="kecamatan" class="form-control" value="<?= htmlspecialchars($family['kecamatan']) ?>">
            </div>
        </div>

        <div class="form-group" style="margin-top: 0.5rem;">
            <label>Alamat Lengkap <span class="required">*</span></label>
            <textarea name="alamat_lengkap" class="form-control" required><?= htmlspecialchars($family['alamat_lengkap']) ?></textarea>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
            <label>Geser Titik Marker di Peta jika ingin memindahkan:</label>
            <div class="map-wrapper">
                <div id="map"></div>
            </div>
            <input type="hidden" id="latitude" name="latitude" value="<?= $family['latitude'] ?>">
            <input type="hidden" id="longitude" name="longitude" value="<?= $family['longitude'] ?>">
            <div class="map-coords-card">
                <span>Koordinat:</span>
                <span class="coords-badge" id="display-coords"><?= $family['latitude'] ?>, <?= $family['longitude'] ?></span>
            </div>
        </div>
    </div>

    <!-- Repeater Anggota -->
    <div class="card">
        <div class="card-title-section" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="card-icon">👨‍👩‍👧‍👦</div>
                <div>
                    <h3>Edit Anggota Keluarga</h3>
                </div>
            </div>
            <button type="button" id="btn-add-member" class="btn btn-outline btn-sm">➕ Tambah Anggota</button>
        </div>

        <div id="members-container">
            <?php foreach ($currentMembers as $idx => $m): ?>
                <div class="member-card" id="member-row-<?= $idx ?>" data-index="<?= $idx ?>">
                    <div class="member-header">
                        <span class="member-badge">Anggota #<?= $idx + 1 ?></span>
                        <button type="button" class="btn-danger-outline btn-sm btn-remove-member" data-index="<?= $idx ?>">✕ Hapus</button>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="members[<?= $idx ?>][nama_lengkap]" class="form-control input-nama" value="<?= htmlspecialchars($m['nama_lengkap']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>NIK (Opsional)</label>
                            <input type="text" name="members[<?= $idx ?>][nik]" class="form-control" maxlength="20" placeholder="16 digit / otomatis KK" value="<?= htmlspecialchars($m['nik']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Hubungan Keluarga <span class="required">*</span></label>
                            <select name="members[<?= $idx ?>][hubungan_keluarga]" class="form-control" required>
                                <option value="Kepala Keluarga" <?= $m['hubungan_keluarga'] === 'Kepala Keluarga' ? 'selected' : '' ?>>Kepala Keluarga</option>
                                <option value="Istri" <?= $m['hubungan_keluarga'] === 'Istri' ? 'selected' : '' ?>>Istri</option>
                                <option value="Anak" <?= $m['hubungan_keluarga'] === 'Anak' ? 'selected' : '' ?>>Anak</option>
                                <option value="Orang Tua" <?= $m['hubungan_keluarga'] === 'Orang Tua' ? 'selected' : '' ?>>Orang Tua</option>
                                <option value="Famili Lain" <?= $m['hubungan_keluarga'] === 'Famili Lain' ? 'selected' : '' ?>>Famili Lain</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jenis Kelamin <span class="required">*</span></label>
                            <select name="members[<?= $idx ?>][jenis_kelamin]" class="form-control" required>
                                <option value="L" <?= $m['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-Laki</option>
                                <option value="P" <?= $m['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid" style="margin-top: 0.5rem;">
                        <div class="form-group">
                            <label>Tempat Lahir</label>
                            <input type="text" name="members[<?= $idx ?>][tempat_lahir]" class="form-control" value="<?= htmlspecialchars($m['tempat_lahir']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Lahir</label>
                            <input type="date" name="members[<?= $idx ?>][tanggal_lahir]" class="form-control" value="<?= $m['tanggal_lahir'] ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="text-align: right; margin-bottom: 3rem;">
        <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2rem;">💾 Simpan Perubahan Data</button>
    </div>
</form>

<script src="../assets/js/map-picker.js"></script>
<script src="../assets/js/family-repeater.js"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
