<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireAuth();
$pdo = getPdo();
$user = getAuthUser();
$role = $user['role'] ?? 'admin';

$pageTitle = "Daftar & Rekam Turnamen SSB Tamalanrea";

// ==========================================
// FETCH TOURNAMENT DATA
// ==========================================
$tournaments = $pdo->query("
    SELECT t.*, 
           COUNT(s.id) as total_pemain_tercatat,
           COALESCE(SUM(s.gol), 0) as total_gol_turnamen
    FROM turnamen t
    LEFT JOIN statistik_pertandingan s ON t.id = s.turnamen_id
    GROUP BY t.id
    ORDER BY t.tanggal_mulai DESC, t.id DESC
")->fetchAll();

// Calculations
$totalTournaments = count($tournaments);
$totalJuara1 = 0;
$totalJuara2 = 0;
$totalJuara3 = 0;

foreach ($tournaments as $t) {
    $p = strtolower($t['pencapaian'] ?? '');
    if (strpos($p, 'juara 1') !== false || strpos($p, 'juara i') !== false) $totalJuara1++;
    elseif (strpos($p, 'juara 2') !== false || strpos($p, 'runner') !== false) $totalJuara2++;
    elseif (strpos($p, 'juara 3') !== false || strpos($p, 'semifinal') !== false) $totalJuara3++;
}

// Success Notification Messages
$successMsg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'tourney_added') $successMsg = "Turnamen baru berhasil ditambahkan!";
    elseif ($_GET['msg'] === 'tourney_updated') $successMsg = "Data turnamen berhasil diperbarui!";
    elseif ($_GET['msg'] === 'tourney_deleted') $successMsg = "Turnamen berhasil dihapus!";
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- ALERT NOTIFICATIONS -->
<?php if ($successMsg): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.2rem;">✨</span>
            <span style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#34d399; cursor:pointer; font-size:1.2rem;">&times;</button>
    </div>
<?php endif; ?>

<!-- TAB NAVIGATION ATAS -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.75rem;">
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-primary" style="box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">
            🏆 Daftar & Rekam Turnamen
        </a>
        <a href="pemain.php" class="btn btn-secondary" style="color: var(--text-heading);">
            ⚽ Statistik Pemain dalam Turnamen
        </a>
    </div>

    <?php if ($role === 'admin' || $role === 'pelatih'): ?>
        <a href="tambah.php" class="btn btn-primary" style="box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            + Tambah Turnamen Baru
        </a>
    <?php endif; ?>
</div>

<!-- HERO BANNER -->
<div class="card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid var(--border-glass); margin-bottom: 2rem; position: relative; overflow: hidden; padding: 2rem;">
    <div style="position: absolute; right: -30px; top: -30px; width: 280px; height: 280px; background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; right: 120px; bottom: -40px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(168, 85, 247, 0.2) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; position: relative; z-index: 2;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; color: #818cf8; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem;">
                <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; box-shadow: 0 0 8px #22c55e;"></span>
                Pusat Rekam Kompetisi SSB
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 0.5rem;">
                🏆 Rekam Turnamen & Liga
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 620px;">
                Daftar lengkap kompetisi, liga, dan turnamen yang diikuti oleh SSB Tamalanrea beserta catatan prestasi dan statistik pertandingan.
            </p>
        </div>
    </div>
</div>

<!-- METRICS COUNTER CARDS -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">🏆</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalTournaments ?></div>
            <div class="metric-desc">Total Kompetisi</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">🥇</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalJuara1 ?></div>
            <div class="metric-desc">Gelar Juara 1</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1;">🥈</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalJuara2 ?></div>
            <div class="metric-desc">Runner Up (Juara 2)</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(217, 119, 6, 0.15); color: #f59e0b;">🥉</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalJuara3 ?></div>
            <div class="metric-desc">Juara 3 / Semifinal</div>
        </div>
    </div>
</div>

<!-- TOURNAMENT GRID & SEARCH -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 class="card-title">📋 Daftar Seluruh Turnamen & Liga</h2>
        
        <div style="position: relative; width: 240px;">
            <input type="text" id="tourneySearchInput" class="form-control" placeholder="Cari turnamen / lokasi..." style="padding-left: 32px; font-size: 0.85rem; height: 36px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 10px; top: 11px; color: var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
        <?php if (empty($tournaments)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem; grid-column: 1 / -1; border: 1px dashed var(--border-glass); border-radius: 16px;">
                Belum ada data turnamen terdaftar.
            </div>
        <?php else: ?>
            <?php foreach ($tournaments as $t): 
                $pencapaian = htmlspecialchars($t['pencapaian'] ?: 'Peserta');
                $badgeClass = 'badge-primary';
                if (strripos($pencapaian, 'juara 1') !== false || strripos($pencapaian, 'juara i') !== false) $badgeClass = 'badge-amber';
                elseif (strripos($pencapaian, 'juara 2') !== false || strripos($pencapaian, 'runner') !== false) $badgeClass = 'badge-cyan';
                elseif (strripos($pencapaian, 'juara 3') !== false || strripos($pencapaian, 'semifinal') !== false) $badgeClass = 'badge-rose';
            ?>
                <div class="tournament-card tourney-item" style="padding: 1.5rem;">
                    <div>
                        <div class="tournament-header" style="margin-bottom: 1rem;">
                            <div>
                                <h3 class="tournament-title" style="font-size: 1.2rem;"><?= htmlspecialchars($t['nama_turnamen']) ?></h3>
                                <span class="badge badge-cyan" style="font-size: 0.7rem; margin-top: 4px; display: inline-block;">
                                    🎯 Kategori: <?= htmlspecialchars($t['kelompok_usia'] ?? 'Semua KU') ?>
                                </span>
                            </div>
                            <span class="badge <?= $badgeClass ?>" style="font-size: 0.78rem; padding: 4px 10px;"><?= $pencapaian ?></span>
                        </div>

                        <div class="tournament-meta" style="flex-direction: column; gap: 8px; margin-bottom: 1.25rem;">
                            <div class="tournament-meta-item" style="font-size: 0.88rem; color: var(--text-heading);">
                                📍 <strong style="color: #fff;"><?= htmlspecialchars($t['lokasi'] ?: 'Makassar') ?></strong>
                            </div>
                            <div class="tournament-meta-item" style="font-size: 0.84rem;">
                                🗓️ <?= date('d M Y', strtotime($t['tanggal_mulai'])) ?>
                                <?php if ($t['tanggal_selesai'] && $t['tanggal_selesai'] !== $t['tanggal_mulai']): ?>
                                    s/d <?= date('d M Y', strtotime($t['tanggal_selesai'])) ?>
                                <?php endif; ?>
                            </div>
                            <div class="tournament-meta-item" style="font-size: 0.84rem; color: #818cf8; font-weight: 600;">
                                👥 <?= $t['total_pemain_tercatat'] ?> Pemain Tercatat &bull; ⚽ <?= $t['total_gol_turnamen'] ?> Gol Tim
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-glass); padding-top: 1rem; margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                            <div style="display: flex; gap: 6px;">
                                <a href="kelola.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm" style="font-size: 0.78rem; padding: 5px 12px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                                    ⚙️ Kelola Pemain
                                </a>
                                <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem; padding: 5px 10px;" title="Edit Data Turnamen">
                                    ✏️ Edit
                                </a>
                            </div>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <a href="pemain.php?turnamen_id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8; border-color: rgba(56, 189, 248, 0.4); display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem;">
                            <span>Stat Pemain ⚽</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- SEARCH JAVASCRIPT -->
<script>
document.getElementById('tourneySearchInput')?.addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let cards = document.querySelectorAll('.tourney-item');
    cards.forEach(card => {
        let text = card.textContent.toLowerCase();
        card.style.display = text.includes(filter) ? 'flex' : 'none';
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
