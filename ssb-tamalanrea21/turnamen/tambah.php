<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireRole(['admin', 'pelatih']);

$pdo = getPdo();
$pageTitle = "Tambah Turnamen Baru";
$error = '';

// List of standard age groups
$ageGroups = ['Semua KU', 'U-8', 'U-10', 'U-12', 'U-14', 'U-16', 'U-18', 'Senior'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_turnamen = trim($_POST['nama_turnamen'] ?? '');
    $kelompok_usia = trim($_POST['kelompok_usia'] ?? 'Semua KU');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $pencapaian = trim($_POST['pencapaian'] ?? '');
    $tanggal_mulai = $_POST['tanggal_mulai'] ?: date('Y-m-d');
    $tanggal_selesai = $_POST['tanggal_selesai'] ?: $tanggal_mulai;

    if (empty($nama_turnamen)) {
        $error = "Nama turnamen / liga tidak boleh kosong!";
    } else {
        // 1. Insert New Tournament
        $stmt = $pdo->prepare("INSERT INTO turnamen (nama_turnamen, kelompok_usia, lokasi, pencapaian, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama_turnamen, $kelompok_usia, $lokasi, $pencapaian, $tanggal_mulai, $tanggal_selesai]);
        $newTourneyId = $pdo->lastInsertId();

        // 2. Auto-Enroll Active Athletes of the Selected Age Group
        if ($kelompok_usia !== 'Semua KU' && !empty($kelompok_usia)) {
            $stmtA = $pdo->prepare("SELECT id FROM atlet WHERE kelompok_usia = ? AND status_keanggotaan = 'Aktif'");
            $stmtA->execute([$kelompok_usia]);
        } else {
            $stmtA = $pdo->query("SELECT id FROM atlet WHERE status_keanggotaan = 'Aktif'");
        }
        $athletes = $stmtA->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($athletes)) {
            $stmtInsertStat = $pdo->prepare("INSERT INTO statistik_pertandingan (atlet_id, turnamen_id, main, gol, assist, kartu_kuning, kartu_merah) VALUES (?, ?, 0, 0, 0, 0, 0)");
            foreach ($athletes as $atletId) {
                $stmtInsertStat->execute([$atletId, $newTourneyId]);
            }
        }

        // Redirect directly to the management page for this tournament
        header("Location: kelola.php?id=$newTourneyId&msg=auto_enrolled");
        exit;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title">🏆 Tambah Turnamen / Liga Baru</h2>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Batal & Kembali</a>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(244, 63, 94, 0.15); border: 1px solid var(--rose); color: #f87171; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid" style="margin-bottom: 1.25rem;">
            <div class="form-group" style="grid-column: span 2;">
                <label>Nama Turnamen / Liga *</label>
                <input type="text" name="nama_turnamen" class="form-control" placeholder="Contoh: Piala Danone U-12 2026" required>
            </div>
        </div>

        <div class="form-grid" style="margin-bottom: 1.25rem;">
            <div class="form-group">
                <label>Kelompok Usia (Kategori) *</label>
                <select name="kelompok_usia" class="form-control" required>
                    <?php foreach ($ageGroups as $ku): ?>
                        <option value="<?= $ku ?>" <?= ($ku === 'U-12') ? 'selected' : '' ?>>
                            <?= $ku ?> <?= ($ku !== 'Semua KU') ? '(Otomatis daftarkan atlet ' . $ku . ')' : '(Daftarkan seluruh atlet)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size: 0.75rem; color: var(--primary); margin-top: 4px;">
                    ✨ Seluruh atlet aktif kategori ini akan otomatis terdaftar ke turnamen.
                </span>
            </div>

            <div class="form-group">
                <label>Lokasi Pertandingan</label>
                <input type="text" name="lokasi" class="form-control" placeholder="Contoh: Lapangan Karebosi">
            </div>
        </div>

        <div class="form-grid" style="margin-bottom: 1.25rem;">
            <div class="form-group" style="grid-column: span 2;">
                <label>Pencapaian / Prestasi</label>
                <input type="text" name="pencapaian" class="form-control" placeholder="Contoh: Juara 1 / Semifinal / Peserta">
            </div>
        </div>

        <div class="form-grid" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label>Tanggal Mulai *</label>
                <input type="date" name="tanggal_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label>Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" style="box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);">
                Simpan & Daftarkan Atlet Otomatis
            </button>
        </div>
    </form>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
