<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole(['admin', 'pelatih']);

$pdo = getPdo();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT e.*, a.nama_lengkap, a.kelompok_usia, a.posisi_utama 
    FROM evaluasi_atlet e 
    JOIN atlet a ON e.atlet_id = a.id 
    WHERE e.id = ?
");
$stmt->execute([$id]);
$evaluasi = $stmt->fetch();

if (!$evaluasi) {
    die("<div style='padding:2rem; color:red; font-family:sans-serif;'>Data evaluasi tidak ditemukan! <a href='index.php'>Kembali</a></div>");
}

$pageTitle = "Edit Raport Evaluasi - " . htmlspecialchars($evaluasi['nama_lengkap']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_evaluasi = $_POST['tanggal_evaluasi'] ?? date('Y-m-d');
    $nilai_passing = (int)($_POST['nilai_passing'] ?? 75);
    $nilai_dribbling = (int)($_POST['nilai_dribbling'] ?? 75);
    $nilai_shooting = (int)($_POST['nilai_shooting'] ?? 75);
    $nilai_tackling = (int)($_POST['nilai_tackling'] ?? 75);
    $nilai_stamina = (int)($_POST['nilai_stamina'] ?? 75);
    $nilai_disiplin = (int)($_POST['nilai_disiplin'] ?? 80);
    $vo2max = (float)($_POST['vo2max'] ?? 45.0);
    $catatan_pelatih = trim($_POST['catatan_pelatih'] ?? '');

    try {
        $stmtUpdate = $pdo->prepare("
            UPDATE evaluasi_atlet 
            SET tanggal_evaluasi = ?, nilai_passing = ?, nilai_dribbling = ?, nilai_shooting = ?, nilai_tackling = ?, nilai_stamina = ?, nilai_disiplin = ?, vo2max = ?, catatan_pelatih = ? 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$tanggal_evaluasi, $nilai_passing, $nilai_dribbling, $nilai_shooting, $nilai_tackling, $nilai_stamina, $nilai_disiplin, $vo2max, $catatan_pelatih, $id]);

        header("Location: index.php?success=eval_updated");
        exit;
    } catch (Exception $e) {
        $error = "Gagal memperbarui data evaluasi: " . $e->getMessage();
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:750px; margin:0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">Edit Raport Evaluasi Atlet</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">
                Atlet: <strong style="color:#fff;"><?= htmlspecialchars($evaluasi['nama_lengkap']) ?></strong> (<?= htmlspecialchars($evaluasi['kelompok_usia']) ?> - <?= htmlspecialchars($evaluasi['posisi_utama']) ?>)
            </p>
        </div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Batal & Kembali</a>
    </div>

    <?php if ($error): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:1rem; border-radius:12px; margin-bottom:1.5rem;">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Tanggal Evaluasi *</label>
                <input type="date" name="tanggal_evaluasi" value="<?= htmlspecialchars($evaluasi['tanggal_evaluasi']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Hasil VO2Max (mL/kg/min)</label>
                <input type="number" step="0.1" name="vo2max" value="<?= htmlspecialchars($evaluasi['vo2max']) ?>" class="form-control">
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem;">PENILAIAN ATRIBUT & TEKNIK (SKALA 0 - 100)</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Passing & Control (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_passing" value="<?= htmlspecialchars($evaluasi['nilai_passing']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Dribbling & Ball Handling (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_dribbling" value="<?= htmlspecialchars($evaluasi['nilai_dribbling']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Shooting & Finishing (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_shooting" value="<?= htmlspecialchars($evaluasi['nilai_shooting']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Tackling & Defending (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_tackling" value="<?= htmlspecialchars($evaluasi['nilai_tackling']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Stamina & Endurance (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_stamina" value="<?= htmlspecialchars($evaluasi['nilai_stamina']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Disiplin & Mental (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_disiplin" value="<?= htmlspecialchars($evaluasi['nilai_disiplin']) ?>" class="form-control" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:2rem;">
            <label>Catatan Evaluasi / Pesan Pelatih</label>
            <textarea name="catatan_pelatih" class="form-control" rows="3" placeholder="Tuliskan evaluasi perkembangan, kelebihan, dan aspek yang perlu ditingkatkan..."><?= htmlspecialchars($evaluasi['catatan_pelatih']) ?></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <a href="index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan Raport</button>
        </div>
    </form>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
