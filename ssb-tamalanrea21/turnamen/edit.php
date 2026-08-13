<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireRole(['admin', 'pelatih']);

$pdo = getPdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch tournament data
$stmt = $pdo->prepare("SELECT * FROM turnamen WHERE id = ?");
$stmt->execute([$id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    header("Location: index.php");
    exit;
}

$pageTitle = "Edit Turnamen: " . $tournament['nama_turnamen'];
$error = '';
$ageGroups = ['Semua KU', 'U-8', 'U-10', 'U-12', 'U-14', 'U-16', 'U-18', 'Senior'];

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tournament'])) {
    $stmtDel = $pdo->prepare("DELETE FROM turnamen WHERE id = ?");
    $stmtDel->execute([$id]);
    header("Location: index.php?msg=tourney_deleted");
    exit;
}

// Handle Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tournament'])) {
    $nama_turnamen = trim($_POST['nama_turnamen'] ?? '');
    $kelompok_usia = trim($_POST['kelompok_usia'] ?? 'Semua KU');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $pencapaian = trim($_POST['pencapaian'] ?? '');
    $kebobolan = (int)($_POST['kebobolan'] ?? 0);
    $tanggal_mulai = $_POST['tanggal_mulai'] ?: date('Y-m-d');
    $tanggal_selesai = $_POST['tanggal_selesai'] ?: $tanggal_mulai;

    if (empty($nama_turnamen)) {
        $error = "Nama turnamen / liga tidak boleh kosong!";
    } else {
        $stmtUpd = $pdo->prepare("UPDATE turnamen SET nama_turnamen = ?, kelompok_usia = ?, lokasi = ?, pencapaian = ?, kebobolan = ?, tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
        $stmtUpd->execute([$nama_turnamen, $kelompok_usia, $lokasi, $pencapaian, $kebobolan, $tanggal_mulai, $tanggal_selesai, $id]);

        header("Location: index.php?msg=tourney_updated");
        exit;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title">✏️ Edit Turnamen: <?= htmlspecialchars($tournament['nama_turnamen']) ?></h2>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Batal & Kembali</a>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(244, 63, 94, 0.15); border: 1px solid var(--rose); color: #f87171; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="update_tournament" value="1">

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label>Nama Turnamen / Liga *</label>
            <input type="text" name="nama_turnamen" class="form-control" value="<?= htmlspecialchars($tournament['nama_turnamen']) ?>" required>
        </div>

        <div class="form-grid" style="margin-bottom: 1.25rem;">
            <div class="form-group">
                <label>Kelompok Usia (Kategori)</label>
                <select name="kelompok_usia" class="form-control" required>
                    <?php foreach ($ageGroups as $ku): ?>
                        <option value="<?= $ku ?>" <?= ($tournament['kelompok_usia'] ?? 'Semua KU') === $ku ? 'selected' : '' ?>>
                            <?= $ku ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Lokasi Pertandingan</label>
                <input type="text" name="lokasi" class="form-control" value="<?= htmlspecialchars($tournament['lokasi']) ?>">
            </div>
        </div>

        <div class="form-grid" style="margin-bottom: 1.25rem;">
            <div class="form-group">
                <label>Pencapaian / Prestasi</label>
                <input type="text" name="pencapaian" class="form-control" value="<?= htmlspecialchars($tournament['pencapaian']) ?>">
            </div>
            <div class="form-group">
                <label>🥅 Total Kebobolan Tim</label>
                <input type="number" name="kebobolan" class="form-control" min="0" value="<?= (int)($tournament['kebobolan'] ?? 0) ?>" style="font-weight: 700;">
            </div>
        </div>

        <div class="form-grid" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label>Tanggal Mulai *</label>
                <input type="date" name="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($tournament['tanggal_mulai']) ?>" required>
            </div>

            <div class="form-group">
                <label>Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" class="form-control" value="<?= htmlspecialchars($tournament['tanggal_selesai']) ?>">
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
            <button type="submit" form="formDelete" onclick="return confirm('Hapus turnamen ini beserta seluruh reakstatnya?')" class="btn btn-secondary" style="color: #f87171; border-color: rgba(244,63,94,0.3);">
                🗑️ Hapus Turnamen
            </button>

            <div style="display: flex; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary" style="box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);">
                    Update Turnamen
                </button>
            </div>
        </div>
    </form>

    <!-- Hidden Separate Form for Delete -->
    <form method="POST" id="formDelete">
        <input type="hidden" name="delete_tournament" value="1">
    </form>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
