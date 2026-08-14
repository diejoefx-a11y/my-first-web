<?php
$pageTitle = "Manajemen Data Master Kelompok";
require_once __DIR__ . '/header.php';

$db = get_db();

// Handle Quick Add POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_kelompok') {
    $id = intval($_POST['id'] ?? 0);
    $nomor = intval($_POST['nomor_kelompok'] ?? 0);
    $nama = clean($_POST['nama_kelompok'] ?? '');
    $ketua = clean($_POST['nama_ketua'] ?? '');
    $no_hp = clean($_POST['no_hp_ketua'] ?? '');
    $sekretaris = clean($_POST['nama_sekretaris'] ?? '');
    $no_hp_sekretaris = clean($_POST['no_hp_sekretaris'] ?? '');
    $wilayah = clean($_POST['wilayah_cakupan'] ?? '');
    $keterangan = clean($_POST['keterangan'] ?? '');

    if ($nomor <= 0 || empty($nama)) {
        set_flash('danger', 'Nomor kelompok dan nama kelompok wajib diisi.');
    } else {
        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE `groups` SET 
                    nomor_kelompok = ?, nama_kelompok = ?, nama_ketua = ?, 
                    no_hp_ketua = ?, nama_sekretaris = ?, no_hp_sekretaris = ?,
                    wilayah_cakupan = ?, keterangan = ?
                WHERE id = ?
            ");
            $stmt->execute([$nomor, $nama, $ketua, $no_hp, $sekretaris, $no_hp_sekretaris, $wilayah, $keterangan, $id]);
            set_flash('success', 'Data ' . htmlspecialchars($nama) . ' berhasil diperbarui!');
        } else {
            $stmt = $db->prepare("
                INSERT INTO `groups` (nomor_kelompok, nama_kelompok, nama_ketua, no_hp_ketua, nama_sekretaris, no_hp_sekretaris, wilayah_cakupan, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nomor, $nama, $ketua, $no_hp, $sekretaris, $no_hp_sekretaris, $wilayah, $keterangan]);
            set_flash('success', 'Kelompok baru berhasil ditambahkan!');
        }
    }
    header("Location: kelompok.php");
    exit;
}

// Fetch all groups with total family count, total articles, total galleries, and admin account info
$sql = "
    SELECT 
        g.*,
        (SELECT COUNT(*) FROM families WHERE kelompok_id = g.id) as total_kk,
        (SELECT COUNT(*) FROM family_members fm JOIN families f ON fm.family_id = f.id WHERE f.kelompok_id = g.id) as total_jiwa,
        (SELECT COUNT(*) FROM articles WHERE group_id = g.id) as total_berita,
        (SELECT COUNT(*) FROM group_galleries WHERE group_id = g.id) as total_foto,
        (SELECT username FROM users WHERE group_id = g.id LIMIT 1) as admin_username
    FROM `groups` g
    ORDER BY g.nomor_kelompok ASC
";
$groups = $db->query($sql)->fetchAll();
?>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--adm-secondary);">Data Master Kelompok (1 - 14)</h3>
        <small style="color: var(--adm-text-muted);">Kelola pengurus (Ketua & Sekretaris), Berita Portal, Galeri Foto, serta Akun Login Admin Kelompok</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()" style="border-radius: var(--adm-radius-full); font-weight: 700;">
        ➕ Tambah Kelompok Baru
    </button>
</div>

<!-- Groups Table Card -->
<div class="data-table-container">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 70px; text-align: center;">No.</th>
                    <th>Nama Kelompok</th>
                    <th>Ketua Kelompok</th>
                    <th>Sekretaris Kelompok</th>
                    <th>Wilayah Cakupan</th>
                    <th style="text-align: center;">Berita</th>
                    <th style="text-align: center;">Foto</th>
                    <th style="text-align: center;">Total KK</th>
                    <th>Akun Admin</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2rem; color: var(--adm-text-muted);">
                            Belum ada data master kelompok.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($groups as $g): ?>
                        <tr>
                            <td style="text-align: center;">
                                <span class="badge-status badge-pending" style="font-size: 0.85rem; padding: 0.25rem 0.6rem; background: #ede9fe; color: #6d28d9; border-color: #ddd6fe;">
                                    #<?= $g['nomor_kelompok'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="kelompok_detail.php?id=<?= $g['id'] ?>" style="font-weight: 800; color: var(--adm-secondary); font-size: 0.95rem; text-decoration: none;">
                                    <?= htmlspecialchars($g['nama_kelompok']) ?> &rarr;
                                </a>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?= htmlspecialchars($g['nama_ketua']) ?: '-' ?></div>
                                <?php if (!empty($g['no_hp_ketua'])): ?>
                                    <small style="color: #059669; font-weight: 600;">📱 <?= htmlspecialchars($g['no_hp_ketua']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?= htmlspecialchars($g['nama_sekretaris']) ?: '-' ?></div>
                                <?php if (!empty($g['no_hp_sekretaris'])): ?>
                                    <small style="color: #059669; font-weight: 600;">📱 <?= htmlspecialchars($g['no_hp_sekretaris']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">
                                    <?= htmlspecialchars($g['wilayah_cakupan']) ?: '-' ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 9999px; font-weight: 800; font-size: 0.8rem;">
                                    <?= $g['total_berita'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #fdf2f8; color: #db2777; padding: 2px 8px; border-radius: 9999px; font-weight: 800; font-size: 0.8rem;">
                                    <?= $g['total_foto'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="keluarga.php?kelompok_id=<?= $g['id'] ?>" style="font-weight: 800; color: #7c3aed; text-decoration: none; background: #f5f3ff; padding: 0.25rem 0.65rem; border-radius: 9999px; border: 1px solid #ddd6fe;">
                                    <?= number_format($g['total_kk']) ?> KK
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($g['admin_username'])): ?>
                                    <span style="font-family: monospace; font-size: 0.75rem; background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                        👤 <?= htmlspecialchars($g['admin_username']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem;">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="kelompok_detail.php?id=<?= $g['id'] ?>" class="btn btn-primary btn-sm" style="padding: 0.3rem 0.75rem; font-size: 0.8rem;">
                                    ⚙️ Kelola Lengkap
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Quick Add / Edit -->
<div class="modal-backdrop" id="kelompok-modal">
    <div class="modal-card" style="max-width: 540px;">
        <button type="button" class="modal-close" onclick="closeKelompokModal()">✕</button>
        <div class="modal-body" style="padding: 2rem;">
            <h3 id="modal-title" style="font-size: 1.3rem; font-weight: 800; color: var(--adm-secondary); margin-bottom: 1.25rem;">
                Tambah Kelompok Baru
            </h3>

            <form action="" method="POST">
                <input type="hidden" name="action" value="save_kelompok">
                <input type="hidden" name="id" id="form-id" value="0">

                <div class="form-grid" style="gap: 1rem;">
                    <div class="form-group">
                        <label>Nomor Kelompok (1 - 14) <span class="required">*</span></label>
                        <input type="number" name="nomor_kelompok" id="form-nomor" class="form-control" min="1" max="99" required>
                    </div>

                    <div class="form-group">
                        <label>Nama Kelompok <span class="required">*</span></label>
                        <input type="text" name="nama_kelompok" id="form-nama" class="form-control" placeholder="Contoh: Kelompok 1" required>
                    </div>

                    <div class="form-group">
                        <label>Nama Ketua Kelompok</label>
                        <input type="text" name="nama_ketua" id="form-ketua" class="form-control" placeholder="Nama lengkap ketua">
                    </div>

                    <div class="form-group">
                        <label>No. WhatsApp / HP Ketua</label>
                        <input type="text" name="no_hp_ketua" id="form-no-hp" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="form-group">
                        <label>Nama Sekretaris Kelompok</label>
                        <input type="text" name="nama_sekretaris" id="form-sekretaris" class="form-control" placeholder="Nama lengkap sekretaris">
                    </div>

                    <div class="form-group">
                        <label>No. WhatsApp Sekretaris</label>
                        <input type="text" name="no_hp_sekretaris" id="form-no-hp-sekretaris" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="form-group col-full">
                        <label>Wilayah Cakupan</label>
                        <input type="text" name="wilayah_cakupan" id="form-wilayah" class="form-control" placeholder="Contoh: RT 01 / RW 01 atau Blok A - B">
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-outline" onclick="closeKelompokModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">💾 Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('kelompok-modal');

function openAddModal() {
    document.getElementById('modal-title').textContent = 'Tambah Kelompok Baru';
    document.getElementById('form-id').value = '0';
    document.getElementById('form-nomor').value = '';
    document.getElementById('form-nama').value = '';
    document.getElementById('form-ketua').value = '';
    document.getElementById('form-no-hp').value = '';
    document.getElementById('form-sekretaris').value = '';
    document.getElementById('form-no-hp-sekretaris').value = '';
    document.getElementById('form-wilayah').value = '';
    modal.classList.add('active');
}

function closeKelompokModal() {
    modal.classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
