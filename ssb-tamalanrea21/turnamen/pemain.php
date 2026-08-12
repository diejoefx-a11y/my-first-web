<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireAuth();
$pdo = getPdo();
$user = getAuthUser();
$role = $user['role'] ?? 'admin';

$pageTitle = "Statistik Pemain dalam Turnamen";

// Selected tournament filter from URL
$selectedTourneyId = isset($_GET['turnamen_id']) ? (int)$_GET['turnamen_id'] : 0;

// Fetch all tournaments for filter dropdown
$tournaments = $pdo->query("SELECT id, nama_turnamen, pencapaian FROM turnamen ORDER BY tanggal_mulai DESC")->fetchAll();

// Build query for player stats based on selected tournament filter
$whereClause = "";
$params = [];
if ($selectedTourneyId > 0) {
    $whereClause = "WHERE s.turnamen_id = ?";
    $params[] = $selectedTourneyId;
}

$stmtStats = $pdo->prepare("
    SELECT s.*, a.nama_lengkap, a.kelompok_usia, a.posisi_utama, t.nama_turnamen 
    FROM statistik_pertandingan s 
    JOIN atlet a ON s.atlet_id = a.id 
    JOIN turnamen t ON s.turnamen_id = t.id 
    $whereClause
    ORDER BY s.gol DESC, s.assist DESC, s.main ASC
");
$stmtStats->execute($params);
$playerStats = $stmtStats->fetchAll();

// Calculations
$totalGoals = array_sum(array_column($playerStats, 'gol'));
$totalAssists = array_sum(array_column($playerStats, 'assist'));
$topScorer = $playerStats[0] ?? null;

$maxGoalsInList = max(array_merge([1], array_column($playerStats, 'gol')));
$maxAssistsInList = max(array_merge([1], array_column($playerStats, 'assist')));

// Get selected tournament info if filtered
$selectedTourneyName = "Semua Turnamen";
if ($selectedTourneyId > 0) {
    foreach ($tournaments as $t) {
        if ($t['id'] == $selectedTourneyId) {
            $selectedTourneyName = $t['nama_turnamen'];
            break;
        }
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- TAB NAVIGATION ATAS -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.75rem;">
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-secondary" style="color: var(--text-heading);">
            🏆 Daftar & Rekam Turnamen
        </a>
        <a href="pemain.php" class="btn btn-primary" style="box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">
            ⚽ Statistik Pemain dalam Turnamen
        </a>
    </div>

    <!-- FILTER DROPDOWN TURNAMEN -->
    <div style="display: flex; align-items: center; gap: 8px;">
        <form method="GET" style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Filter Turnamen:</label>
            <select name="turnamen_id" onchange="this.form.submit()" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; height: 36px; min-width: 220px;">
                <option value="0">-- Semua Turnamen & Liga --</option>
                <?php foreach ($tournaments as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTourneyId == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nama_turnamen']) ?> (<?= htmlspecialchars($t['pencapaian'] ?: 'Kompetisi') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selectedTourneyId > 0 && ($role === 'admin' || $role === 'pelatih')): ?>
            <a href="kelola.php?id=<?= $selectedTourneyId ?>" class="btn btn-primary btn-sm" style="font-size: 0.8rem; padding: 6px 12px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                ⚙️ Kelola Pemain Turnamen Ini
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- HERO BANNER -->
<div class="card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid var(--border-glass); margin-bottom: 2rem; position: relative; overflow: hidden; padding: 2rem;">
    <div style="position: absolute; right: -30px; top: -30px; width: 280px; height: 280px; background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; position: relative; z-index: 2;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; color: #818cf8; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem;">
                <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; box-shadow: 0 0 8px #22c55e;"></span>
                <?= htmlspecialchars($selectedTourneyName) ?>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 0.5rem;">
                ⚽ Statistik Pemain dalam Turnamen
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 620px;">
                Rangkuman performa individu atlet SSB Tamalanrea. Pantau statistik gol, assist, dan pemeringkatan kartu digital pemain.
            </p>
        </div>
    </div>
</div>

<!-- METRICS COUNTER CARDS -->
<div class="metric-grid">
    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(34, 197, 94, 0.15); color: #4ade80;">⚽</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalGoals ?></div>
            <div class="metric-desc">Total Gol SSB</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">🎯</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalAssists ?></div>
            <div class="metric-desc">Total Assist SSB</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">👑</div>
        <div class="metric-info">
            <div class="metric-num" style="font-size: 1.25rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;">
                <?= htmlspecialchars($topScorer['nama_lengkap'] ?? 'Belum ada') ?>
            </div>
            <div class="metric-desc">Top Scorer (<?= $topScorer['gol'] ?? 0 ?> Gol)</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon-box" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">👥</div>
        <div class="metric-info">
            <div class="metric-num"><?= count($playerStats) ?></div>
            <div class="metric-desc">Pemain Tercatat</div>
        </div>
    </div>
</div>

<!-- TOP 3 PLAYER PODIUM SHOWCASE -->
<?php if (count($playerStats) >= 1): ?>
    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; color: #fff; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                🥇 Top Scorer & Assist Leaderboard
            </h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($selectedTourneyName) ?></span>
        </div>

        <div class="podium-container">
            <?php 
            $ranks = [
                ['rank' => 1, 'crown' => '🥇 Emas', 'class' => 'rank-1', 'bg' => 'linear-gradient(135deg, #f59e0b, #d97706)'],
                ['rank' => 2, 'crown' => '🥈 Perak', 'class' => 'rank-2', 'bg' => 'linear-gradient(135deg, #94a3b8, #64748b)'],
                ['rank' => 3, 'crown' => '🥉 Perunggu', 'class' => 'rank-3', 'bg' => 'linear-gradient(135deg, #d97706, #b45309)']
            ];
            for ($i = 0; $i < min(3, count($playerStats)); $i++): 
                $p = $playerStats[$i];
                $r = $ranks[$i];
                $initials = strtoupper(substr($p['nama_lengkap'], 0, 2));
            ?>
                <div class="podium-card <?= $r['class'] ?>">
                    <div class="podium-crown"><?= $r['crown'] ?></div>
                    <div class="podium-avatar" style="background: <?= $r['bg'] ?>;">
                        <?= $initials ?>
                    </div>
                    <div class="podium-name"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                    <div style="display: flex; justify-content: center; gap: 6px; margin-top: 4px;">
                        <span class="badge badge-primary" style="font-size: 0.68rem;"><?= htmlspecialchars($p['kelompok_usia']) ?></span>
                        <span class="badge badge-amber" style="font-size: 0.68rem;"><?= htmlspecialchars($p['posisi_utama']) ?></span>
                    </div>

                    <div class="podium-stats">
                        <div class="podium-stat-item">
                            <span style="color: var(--text-muted); font-size: 0.72rem;">Gol</span>
                            <strong style="color: #22c55e;"><?= $p['gol'] ?> ⚽</strong>
                        </div>
                        <div class="podium-stat-item">
                            <span style="color: var(--text-muted); font-size: 0.72rem;">Assist</span>
                            <strong style="color: #38bdf8;"><?= $p['assist'] ?> 🎯</strong>
                        </div>
                        <div class="podium-stat-item">
                            <span style="color: var(--text-muted); font-size: 0.72rem;">Main</span>
                            <strong style="color: #cbd5e1;"><?= $p['main'] ?> Laga</strong>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<!-- FIFA FUT / DIGITAL SCOUTING CARDS SECTION -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 class="card-title">⚡ Kartu Performa Pemain (FUT Scouting)</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Statistik individual atlet di <?= htmlspecialchars($selectedTourneyName) ?></span>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Search Input -->
            <div style="position: relative; width: 220px;">
                <input type="text" id="statSearchInput" class="form-control" placeholder="Cari nama atlet..." style="padding-left: 30px; font-size: 0.82rem; height: 34px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 10px; top: 10px; color: var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
        </div>
    </div>

    <!-- FIFA FUT CARDS GRID CONTAINER -->
    <div id="containerFutCards" class="fut-grid" style="max-height: 580px; overflow-y: auto; padding-right: 4px;">
        <?php 
        $futPlayers = array_filter($playerStats, function($ps) {
            return ($ps['gol'] > 0 || $ps['assist'] > 0);
        });
        ?>
        <?php if (empty($futPlayers)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem; grid-column: 1 / -1; border: 1px dashed var(--border-glass); border-radius: 16px;">
                Belum ada pemain yang mencetak gol atau assist pada turnamen ini.
            </div>
        <?php else: ?>
            <?php $idx = 0; foreach ($futPlayers as $ps): 
                $idx++;
                $ovr = min(99, max(70, 75 + ($ps['gol'] * 3) + ($ps['assist'] * 2) + ($ps['main'])));
                $ratio = $ps['main'] > 0 ? number_format($ps['gol'] / $ps['main'], 1) : '0.0';
                $initials = strtoupper(substr($ps['nama_lengkap'], 0, 2));

                $themeClass = 'theme-cyan';
                $tagLabel = 'SSB';
                if ($idx === 1) { $themeClass = 'theme-gold'; $tagLabel = '🥇 MOTM'; }
                elseif ($idx === 2) { $themeClass = 'theme-gold'; $tagLabel = '🥈 STAR'; }
                elseif ($idx === 3) { $themeClass = 'theme-purple'; $tagLabel = '🥉 PRO'; }
                elseif ($ps['gol'] >= 3) { $themeClass = 'theme-purple'; $tagLabel = 'ELITE'; }
            ?>
                <div class="fut-card fut-card-item <?= $themeClass ?>">
                    <div class="fut-card-header">
                        <div class="fut-badge-ovr">
                            <?= $ovr ?>
                            <span>OVR</span>
                        </div>
                        <span class="badge badge-primary" style="font-size: 0.65rem; padding: 2px 8px;"><?= $tagLabel ?></span>
                    </div>

                    <div>
                        <div class="fut-avatar-wrap">
                            <div class="fut-avatar"><?= $initials ?></div>
                        </div>

                        <div class="fut-player-name" title="<?= htmlspecialchars($ps['nama_lengkap']) ?>">
                            <?= htmlspecialchars($ps['nama_lengkap']) ?>
                        </div>

                        <div class="fut-player-sub">
                            <span style="font-weight: 700; color: #fff;"><?= htmlspecialchars($ps['posisi_utama']) ?></span>
                            <span>&bull; <?= htmlspecialchars($ps['kelompok_usia']) ?></span>
                        </div>
                    </div>

                    <div class="fut-stats-grid">
                        <div class="fut-stat-box">
                            <div class="fut-stat-label">GOL</div>
                            <div class="fut-stat-val" style="color: #4ade80;"><?= $ps['gol'] ?> ⚽</div>
                        </div>
                        <div class="fut-stat-box">
                            <div class="fut-stat-label">ASSIST</div>
                            <div class="fut-stat-val" style="color: #38bdf8;"><?= $ps['assist'] ?> 🎯</div>
                        </div>
                        <div class="fut-stat-box">
                            <div class="fut-stat-label">MAIN</div>
                            <div class="fut-stat-val" style="color: #cbd5e1;"><?= $ps['main'] ?></div>
                        </div>
                        <div class="fut-stat-box">
                            <div class="fut-stat-label">RASIO</div>
                            <div class="fut-stat-val" style="color: #fbbf24;"><?= $ratio ?></div>
                        </div>
                    </div>

                    <div class="fut-card-footer">
                        <div style="font-size: 0.72rem; width: 100%; text-align: center;">
                            🏆 <span style="color: #fff; font-weight: 600;"><?= htmlspecialchars($ps['nama_turnamen']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- JAVASCRIPT LIVE SEARCH -->
<script>
document.getElementById('statSearchInput')?.addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let futCards = document.querySelectorAll('.fut-card-item');
    futCards.forEach(card => {
        let text = card.textContent.toLowerCase();
        card.style.display = text.includes(filter) ? 'flex' : 'none';
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

