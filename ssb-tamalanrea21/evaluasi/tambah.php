<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole(['admin', 'pelatih']);

$pdo = getPdo();

$pageTitle = "Input Raport & Evaluasi Atlet";
$presetAtletId = (int)($_GET['atlet_id'] ?? 0);

$atletList = $pdo->query("SELECT id, nama_lengkap, kelompok_usia, posisi_utama FROM atlet ORDER BY nama_lengkap ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atlet_id = (int)($_POST['atlet_id'] ?? 0);
    $tanggal_evaluasi = $_POST['tanggal_evaluasi'] ?? date('Y-m-d');
    $nilai_passing = (int)($_POST['nilai_passing'] ?? 10);
    $nilai_dribbling = (int)($_POST['nilai_dribbling'] ?? 10);
    $nilai_shooting = (int)($_POST['nilai_shooting'] ?? 10);
    $nilai_tackling = (int)($_POST['nilai_tackling'] ?? 10);
    $nilai_stamina = (int)($_POST['nilai_stamina'] ?? 10);
    $nilai_disiplin = (int)($_POST['nilai_disiplin'] ?? 10);
    $vo2max = (float)($_POST['vo2max'] ?? 10.0);
    $catatan_pelatih = trim($_POST['catatan_pelatih'] ?? '');

    if ($atlet_id <= 0) {
        $error = "Pilih atlet terlebih dahulu!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO evaluasi_atlet (atlet_id, tanggal_evaluasi, nilai_passing, nilai_dribbling, nilai_shooting, nilai_tackling, nilai_stamina, nilai_disiplin, vo2max, catatan_pelatih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$atlet_id, $tanggal_evaluasi, $nilai_passing, $nilai_dribbling, $nilai_shooting, $nilai_tackling, $nilai_stamina, $nilai_disiplin, $vo2max, $catatan_pelatih]);

        header("Location: ../atlet/detail.php?id=$atlet_id&success=eval_added");
        exit;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:750px; margin:0 auto;">
    <div class="card-header">
        <h2 class="card-title">Form Input Raport Perkembangan Atlet</h2>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Batal & Kembali</a>
    </div>

    <?php if ($error): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:1rem; border-radius:12px; margin-bottom:1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group" style="grid-column: span 2;">
                <label>Pilih Atlet *</label>
                <select name="atlet_id" class="form-control" required>
                    <option value="">-- Pilih Atlet --</option>
                    <?php foreach ($atletList as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $presetAtletId == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nama_lengkap']) ?> (<?= $a['kelompok_usia'] ?> - <?= $a['posisi_utama'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tanggal Evaluasi *</label>
                <input type="date" name="tanggal_evaluasi" value="<?= date('Y-m-d') ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Hasil VO2Max (mL/kg/min)</label>
                <input type="number" step="0.1" name="vo2max" value="10.0" class="form-control">
            </div>
        </div>

        <h3 style="font-size:1rem; color:var(--primary); margin-bottom:1rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.5rem;">PENILAIAN ATRIBUT & TEKNIK (SKALA 0 - 100)</h3>

        <div class="form-grid" style="margin-bottom:1.5rem;">
            <div class="form-group">
                <label>Passing & Control (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_passing" value="10" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Dribbling & Ball Handling (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_dribbling" value="10" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Shooting & Finishing (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_shooting" value="10" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Tackling & Defending (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_tackling" value="10" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Stamina & Endurance (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_stamina" value="10" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Disiplin & Mental (0-100)</label>
                <input type="number" min="0" max="100" name="nilai_disiplin" value="10" class="form-control" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:2rem;">
            <label>Catatan Evaluation / Pesan Pelatih</label>
            <textarea name="catatan_pelatih" class="form-control" rows="3" placeholder="Tuliskan evaluasi perkembangan, kelebihan, dan aspek yang perlu ditingkatkan..."></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <a href="index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Raport Evaluasi</button>
        </div>
    </form>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
