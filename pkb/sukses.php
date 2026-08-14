<?php
require_once __DIR__ . '/config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$no_kk = isset($_GET['kk']) ? clean($_GET['kk']) : '';

if (!$id && !$no_kk) {
    header("Location: index.php");
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM families WHERE id = ? OR no_kk = ? LIMIT 1");
$stmt->execute([$id, $no_kk]);
$family = $stmt->fetch();

if (!$family) {
    header("Location: index.php");
    exit;
}

// Fetch family members
$stmtMembers = $db->prepare("SELECT * FROM family_members WHERE family_id = ? ORDER BY id ASC");
$stmtMembers->execute([$family['id']]);
$members = $stmtMembers->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Berhasil - Data Keluarga</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        #preview-map {
            height: 260px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>

    <header class="app-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
        <div class="container">
            <span class="badge-pill" style="background: rgba(255, 255, 255, 0.25);">✓ Berhasil Tersimpan</span>
            <h1>Pendaftaran Data Keluarga Berhasil!</h1>
            <p>Terima kasih, data keluarga dan titik lokasi rumah Anda telah berhasil dicatat ke dalam sistem.</p>
        </div>
    </header>

    <div class="container main-wrapper">
        <div class="card" style="max-width: 800px; margin: 0 auto 2rem auto;">
            
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--secondary);">Data Keluarga Terdaftar</h2>
                <p style="color: var(--text-muted);">Nomor KK: <strong style="color: var(--primary);"><?= htmlspecialchars($family['no_kk']) ?></strong></p>
                <div style="margin-top: 0.5rem;">
                    <span class="badge-status badge-pending">Status: Menunggu Verifikasi Admin</span>
                </div>
            </div>

            <!-- Summary Table -->
            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 1.25rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <div class="form-grid" style="gap: 0.75rem;">
                    <div>
                        <small style="color: var(--text-muted); display: block;">Nama Kepala Keluarga</small>
                        <strong style="font-size: 1.05rem;"><?= htmlspecialchars($family['nama_kepala']) ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--text-muted); display: block;">NIK Kepala Keluarga</small>
                        <strong><?= htmlspecialchars($family['nik_kepala']) ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--text-muted); display: block;">No. WhatsApp</small>
                        <strong><?= htmlspecialchars($family['no_hp']) ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--text-muted); display: block;">Jumlah Anggota Terdaftar</small>
                        <strong><?= count($members) ?> Orang</strong>
                    </div>
                    <div class="col-full">
                        <small style="color: var(--text-muted); display: block;">Alamat Domisili</small>
                        <span><?= htmlspecialchars($family['alamat_lengkap']) ?> (RT <?= htmlspecialchars($family['rt']) ?> / RW <?= htmlspecialchars($family['rw']) ?>, Kel. <?= htmlspecialchars($family['kelurahan']) ?>, Kec. <?= htmlspecialchars($family['kecamatan']) ?>)</span>
                    </div>
                </div>

                <?php if (!empty($family['foto_keluarga']) || !empty($family['foto_rumah'])): ?>
                    <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                        <?php if (!empty($family['foto_keluarga'])): ?>
                            <div style="flex: 1; min-width: 200px;">
                                <small style="color: #7c3aed; font-weight: 700; display: block; margin-bottom: 4px;">👨‍👩‍👧‍👦 Foto Keluarga:</small>
                                <img src="<?= base_url('uploads/' . $family['foto_keluarga']) ?>" alt="Foto Keluarga" style="max-height: 160px; width: 100%; object-fit: cover; border-radius: 10px; border: 1.5px solid #ddd6fe;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($family['foto_rumah'])): ?>
                            <div style="flex: 1; min-width: 200px;">
                                <small style="color: #0284c7; font-weight: 700; display: block; margin-bottom: 4px;">🏠 Foto Rumah:</small>
                                <img src="<?= base_url('uploads/' . $family['foto_rumah']) ?>" alt="Foto Rumah" style="max-height: 160px; width: 100%; object-fit: cover; border-radius: 10px; border: 1.5px solid #bae6fd;">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Family Members List -->
            <?php if (!empty($members)): ?>
                <div style="margin-bottom: 1.75rem;">
                    <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--secondary);">Anggota Keluarga Terdaftar:</h4>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead>
                                <tr style="background: #e2e8f0; text-align: left;">
                                    <th style="padding: 0.5rem 0.75rem;">No</th>
                                    <th style="padding: 0.5rem 0.75rem;">Nama Lengkap</th>
                                    <th style="padding: 0.5rem 0.75rem;">NIK</th>
                                    <th style="padding: 0.5rem 0.75rem;">Hubungan</th>
                                    <th style="padding: 0.5rem 0.75rem;">L/P</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $idx => $m): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.5rem 0.75rem;"><?= $idx + 1 ?></td>
                                        <td style="padding: 0.5rem 0.75rem; font-weight: 600;"><?= htmlspecialchars($m['nama_lengkap']) ?></td>
                                        <td style="padding: 0.5rem 0.75rem;"><?= htmlspecialchars($m['nik']) ?></td>
                                        <td style="padding: 0.5rem 0.75rem;"><?= htmlspecialchars($m['hubungan_keluarga']) ?></td>
                                        <td style="padding: 0.5rem 0.75rem;"><?= htmlspecialchars($m['jenis_kelamin']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Coordinates & Google Maps Link -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--secondary);">Titik Lokasi Rumah:</h4>
                <div id="preview-map"></div>

                <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center;">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $family['latitude'] ?>,<?= $family['longitude'] ?>" target="_blank" class="btn btn-primary" style="background: #ea4335;">
                        🗺️ Buka Rute di Google Maps
                    </a>
                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $family['no_hp'])) ?>" target="_blank" class="btn btn-accent">
                        💬 Hubungi via WhatsApp
                    </a>
                </div>
            </div>

            <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--border-color);">

            <div style="text-align: center; display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="index.php" class="btn btn-outline">
                    🏠 Kembali ke Portal Berita
                </a>
                <a href="jemaat/pasangtitik.php" class="btn btn-primary">
                    ➕ Daftarkan Keluarga Lain
                </a>
            </div>

        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const lat = <?= $family['latitude'] ?>;
        const lng = <?= $family['longitude'] ?>;

        const previewMap = L.map('preview-map', {
            center: [lat, lng],
            zoom: 17,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(previewMap);

        const marker = L.marker([lat, lng]).addTo(previewMap);
        marker.bindPopup("<b>Rumah <?= htmlspecialchars(addslashes($family['nama_kepala'])) ?></b><br><?= htmlspecialchars(addslashes($family['alamat_lengkap'])) ?>").openPopup();
    </script>
</body>
</html>
