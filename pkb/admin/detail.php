<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: keluarga.php");
    exit;
}

$db = get_db();

// Handle Status Update from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = clean($_POST['status_verifikasi'] ?? 'pending');
    $catatan = clean($_POST['catatan_admin'] ?? '');

    $stmtUpdate = $db->prepare("UPDATE families SET status_verifikasi = ?, catatan_admin = ? WHERE id = ?");
    $stmtUpdate->execute([$newStatus, $catatan, $id]);

    set_flash('success', 'Status verifikasi berhasil diperbarui!');
    header("Location: detail.php?id=" . $id);
    exit;
}

// Fetch Family Details with Group
$stmt = $db->prepare("
    SELECT 
        f.*,
        g.nama_kelompok,
        g.nomor_kelompok,
        g.nama_ketua as ketua_kelompok,
        g.no_hp_ketua
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    WHERE f.id = ? 
    LIMIT 1
");
$stmt->execute([$id]);
$family = $stmt->fetch();

if (!$family) {
    set_flash('danger', 'Data keluarga tidak ditemukan.');
    header("Location: keluarga.php");
    exit;
}

// Fetch Members
$stmtMembers = $db->prepare("SELECT * FROM family_members WHERE family_id = ? ORDER BY id ASC");
$stmtMembers->execute([$id]);
$members = $stmtMembers->fetchAll();

$pageTitle = "Detail Data Keluarga - " . htmlspecialchars($family['nama_kepala']);
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <a href="keluarga.php" class="btn btn-outline btn-sm">← Kembali ke Daftar Keluarga</a>
    <div style="display: flex; gap: 0.5rem;">
        <a href="edit.php?id=<?= $family['id'] ?>" class="btn btn-outline btn-sm">✏️ Edit Data</a>
        <a href="cetak.php?id=<?= $family['id'] ?>" target="_blank" class="btn btn-accent btn-sm">🖨️ Cetak / Print</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    
    <!-- Left Column: Data & Table -->
    <div>
        <!-- Main Card -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--secondary);"><?= htmlspecialchars($family['nama_kepala']) ?></h3>
                    <div style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                        No. Kartu Keluarga: <strong style="color: var(--primary); font-family: monospace;"><?= htmlspecialchars($family['no_kk']) ?></strong>
                    </div>
                </div>
                <div>
                    <span class="badge-status badge-<?= $family['status_verifikasi'] ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">
                        <?= ucfirst($family['status_verifikasi']) ?>
                    </span>
                </div>
            </div>

            <div class="form-grid" style="gap: 1rem;">
                <div>
                    <small style="color: var(--text-muted); display: block;">Kelompok Domisili</small>
                    <?php if (!empty($family['nama_kelompok'])): ?>
                        <strong style="color: #7c3aed; font-size: 1rem;">
                            🏷️ <?= htmlspecialchars($family['nama_kelompok']) ?>
                        </strong>
                        <?php if (!empty($family['ketua_kelompok'])): ?>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                Ketua: <?= htmlspecialchars($family['ketua_kelompok']) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: var(--text-muted);">- Belum Ditentukan -</span>
                    <?php endif; ?>
                </div>
                <div>
                    <small style="color: var(--text-muted); display: block;">NIK Kepala Keluarga</small>
                    <strong style="font-family: monospace; font-size: 1rem;"><?= htmlspecialchars($family['nik_kepala']) ?></strong>
                </div>
                <div>
                    <small style="color: var(--text-muted); display: block;">No. WhatsApp / HP</small>
                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $family['no_hp'])) ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                        💬 <?= htmlspecialchars($family['no_hp']) ?>
                    </a>
                </div>
                <div>
                    <small style="color: var(--text-muted); display: block;">Wilayah RT / RW</small>
                    <strong>RT <?= htmlspecialchars($family['rt']) ?> / RW <?= htmlspecialchars($family['rw']) ?></strong>
                </div>
                <div>
                    <small style="color: var(--text-muted); display: block;">Kelurahan & Kecamatan</small>
                    <strong>Kel. <?= htmlspecialchars($family['kelurahan']) ?>, Kec. <?= htmlspecialchars($family['kecamatan']) ?></strong>
                </div>
                <div>
                    <small style="color: var(--text-muted); display: block;">Jumlah Tanggungan</small>
                    <strong><?= $family['jumlah_tanggungan'] ?> Orang</strong>
                </div>
                <div class="col-full">
                    <small style="color: var(--text-muted); display: block;">Alamat Lengkap</small>
                    <span><?= htmlspecialchars($family['alamat_lengkap']) ?></span>
                </div>
                <div>
                    <small style="color: var(--text-muted); display: block;">Terdaftar Sejak</small>
                    <span><?= date('d F Y, H:i', strtotime($family['created_at'])) ?> WITA</span>
                </div>
            </div>
        </div>

        <!-- Family Members Table -->
        <div class="card" style="padding: 1.5rem;">
            <div class="card-title-section" style="margin-bottom: 1rem;">
                <div class="card-icon">👨‍👩‍👧‍👦</div>
                <div>
                    <h3>Anggota Keluarga Terdaftar (<?= count($members) ?> Orang)</h3>
                    <small style="color: var(--text-muted);">Daftar seluruh individu yang tergabung dalam Kartu Keluarga ini</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>NIK</th>
                            <th>Hubungan</th>
                            <th>L/P</th>
                            <th>TTL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Tidak ada detail anggota keluarga.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $idx => $m): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td style="font-weight: 700; color: var(--secondary);"><?= htmlspecialchars($m['nama_lengkap']) ?></td>
                                    <td style="font-family: monospace;"><?= htmlspecialchars($m['nik']) ?></td>
                                    <td><span style="background: #ede9fe; color: #6d28d9; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><?= htmlspecialchars($m['hubungan_keluarga']) ?></span></td>
                                    <td><?= htmlspecialchars($m['jenis_kelamin']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($m['tempat_lahir']) ?><?= !empty($m['tanggal_lahir']) ? ', ' . date('d/m/Y', strtotime($m['tanggal_lahir'])) : '' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Map, Photo & Verification -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Verification Card -->
        <div class="card" style="padding: 1.25rem;">
            <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--secondary);">Verifikasi Status</h4>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_status">
                
                <div class="form-group">
                    <label>Status Data</label>
                    <select name="status_verifikasi" class="form-control">
                        <option value="pending" <?= $family['status_verifikasi'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                        <option value="terverifikasi" <?= $family['status_verifikasi'] === 'terverifikasi' ? 'selected' : '' ?>>✅ Terverifikasi</option>
                        <option value="ditolak" <?= $family['status_verifikasi'] === 'ditolak' ? 'selected' : '' ?>>❌ Ditolak</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top: 0.75rem;">
                    <label>Catatan Petugas (Opsional)</label>
                    <textarea name="catatan_admin" class="form-control" placeholder="Tulis catatan jika ada..."><?= htmlspecialchars($family['catatan_admin'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-sm" style="margin-top: 1rem;">
                    Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- Mini Map Card -->
        <div class="card" style="padding: 1.25rem;">
            <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--secondary);">Titik Lokasi Rumah</h4>
            <div id="detail-map" style="height: 240px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 0.75rem;"></div>
            
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                Koordinat: <strong><?= $family['latitude'] ?>, <?= $family['longitude'] ?></strong>
            </div>

            <a href="https://www.google.com/maps/search/?api=1&query=<?= $family['latitude'] ?>,<?= $family['longitude'] ?>" target="_blank" class="btn btn-primary btn-block btn-sm" style="background: #ea4335;">
                🗺️ Buka Rute di Google Maps
            </a>
        </div>

        <!-- Family Photo Card -->
        <?php if (!empty($family['foto_keluarga'])): ?>
            <div class="card" style="padding: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: #7c3aed;">👨‍👩‍👧‍👦 Foto Keluarga</h4>
                <img src="<?= base_url('uploads/' . $family['foto_keluarga']) ?>" alt="Foto Keluarga" style="width: 100%; border-radius: var(--radius-md); border: 1.5px solid #ddd6fe; object-fit: cover; max-height: 250px;">
            </div>
        <?php endif; ?>

        <!-- House Photo Card -->
        <?php if (!empty($family['foto_rumah'])): ?>
            <div class="card" style="padding: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: #0284c7;">🏠 Foto Rumah</h4>
                <img src="<?= base_url('uploads/' . $family['foto_rumah']) ?>" alt="Foto Rumah" style="width: 100%; border-radius: var(--radius-md); border: 1px solid var(--border-color); object-fit: cover; max-height: 250px;">
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lat = <?= $family['latitude'] ?>;
    const lng = <?= $family['longitude'] ?>;

    const detailMap = L.map('detail-map').setView([lat, lng], 17);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(detailMap);

    const marker = L.marker([lat, lng]).addTo(detailMap);
    marker.bindPopup("<b><?= htmlspecialchars(addslashes($family['nama_kepala'])) ?></b><br><?= htmlspecialchars(addslashes($family['alamat_lengkap'])) ?>").openPopup();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
