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
           COUNT(DISTINCT s.atlet_id) as total_pemain_tercatat,
           COALESCE(SUM(s.gol), 0) as total_gol_turnamen,
           COUNT(DISTINCT s.id) as total_laga
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
$totalGolKeseluruhan = 0;

foreach ($tournaments as $t) {
    $p = strtolower($t['pencapaian'] ?? '');
    if (strpos($p, 'juara 1') !== false || strpos($p, 'juara i') !== false) $totalJuara1++;
    elseif (strpos($p, 'juara 2') !== false || strpos($p, 'runner') !== false) $totalJuara2++;
    elseif (strpos($p, 'juara 3') !== false || strpos($p, 'semifinal') !== false) $totalJuara3++;
    
    $totalGolKeseluruhan += $t['total_gol_turnamen'];
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
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
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
<div class="card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.95) 0%, rgba(15, 23, 42, 0.98) 50%, rgba(17, 24, 39, 0.95) 100%); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.7); margin-bottom: 2rem; position: relative; overflow: hidden; padding: 2rem;">
    <div style="position: absolute; right: -30px; top: -30px; width: 280px; height: 280px; background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
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
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
    <div class="metric-card" style="border: 1px solid rgba(99,102,241,0.25);">
        <div class="metric-icon-box" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">🏆</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalTournaments ?></div>
            <div class="metric-desc">Total Turnamen</div>
        </div>
    </div>

    <div class="metric-card" style="border: 1px solid rgba(251,191,36,0.25);">
        <div class="metric-icon-box" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">🥇</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalJuara1 ?></div>
            <div class="metric-desc">Gelar Juara 1</div>
        </div>
    </div>

    <div class="metric-card" style="border: 1px solid rgba(203,213,225,0.25);">
        <div class="metric-icon-box" style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1;">🥈</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalJuara2 ?></div>
            <div class="metric-desc">Runner Up (Juara 2)</div>
        </div>
    </div>

    <div class="metric-card" style="border: 1px solid rgba(56,189,248,0.25);">
        <div class="metric-icon-box" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">⚽</div>
        <div class="metric-info">
            <div class="metric-num"><?= $totalGolKeseluruhan ?></div>
            <div class="metric-desc">Total Gol Tim</div>
        </div>
    </div>
</div>

<!-- COMPONENT UI: DAFTAR SELURUH TURNAMEN & LIGA DENGAN FILTER DINAMIS -->
<div class="card" style="padding: 1.5rem;">
    <!-- HEADER BAR WITH LIVE SEARCH AND CATEGORY CHIPS -->
    <div style="border-bottom: 1px solid var(--border-glass); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <h2 class="card-title" style="font-size: 1.25rem; display: flex; align-items: center; gap: 8px;">
                    📋 Daftar Seluruh Turnamen & Liga
                </h2>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">Daftar kompetisi resmi yang telah dan sedang diikuti SSB Tamalanrea</p>
            </div>
            
            <!-- LIVE SEARCH BAR -->
            <div style="position: relative; width: 280px;">
                <input type="text" id="tourneySearchInput" class="form-control" placeholder="Cari nama, lokasi, atau KU..." style="padding-left: 36px; font-size: 0.85rem; height: 40px; border-radius: 12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="position: absolute; left: 12px; top: 12px; color: var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
        </div>

        <!-- QUICK FILTER CHIPS -->
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px;">
            <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-right: 4px;">FILTER KU:</span>
                <button type="button" class="tourney-filter-btn active" data-filter="all" onclick="filterTournaments('all', this)" style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1px solid rgba(99,102,241,0.4); background: rgba(99,102,241,0.25); color: #fff;">
                    Semua
                </button>
                <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $kuOpt): ?>
                    <button type="button" class="tourney-filter-btn" data-filter="ku-<?= strtolower($kuOpt) ?>" onclick="filterTournaments('ku-<?= strtolower($kuOpt) ?>', this)" style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1px solid var(--border-glass); background: rgba(15,23,42,0.6); color: var(--text-muted);">
                        <?= $kuOpt ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="tourneyCountBadge" style="font-size: 0.78rem; font-weight: 700; color: #38bdf8; background: rgba(56,189,248,0.12); padding: 4px 12px; border-radius: 10px; border: 1px solid rgba(56,189,248,0.25);">
                Menampilkan <strong><?= count($tournaments) ?></strong> Turnamen
            </div>
        </div>
    </div>

    <!-- DYNAMIC TOURNAMENT GRID CARDS -->
    <div id="tourneyGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 1.25rem;">
        <?php if (empty($tournaments)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem; grid-column: 1 / -1; border: 1px dashed var(--border-glass); border-radius: 16px;">
                Belum ada data turnamen terdaftar.
            </div>
        <?php else: ?>
            <?php foreach ($tournaments as $t): 
                $pencapaian = htmlspecialchars($t['pencapaian'] ?: 'Peserta');
                $kuTag = strtolower($t['kelompok_usia'] ?? 'semua ku');
                
                // Trophy badge styling
                $badgeStyle = "background:rgba(99,102,241,0.15); border:1px solid rgba(99,102,241,0.3); color:#818cf8;";
                if (strripos($pencapaian, 'juara 1') !== false || strripos($pencapaian, 'juara i') !== false) {
                    $badgeStyle = "background:rgba(251,191,36,0.2); border:1px solid #fbbf24; color:#fbbf24;";
                } elseif (strripos($pencapaian, 'juara 2') !== false || strripos($pencapaian, 'runner') !== false) {
                    $badgeStyle = "background:rgba(203,213,225,0.2); border:1px solid #cbd5e1; color:#cbd5e1;";
                } elseif (strripos($pencapaian, 'juara 3') !== false || strripos($pencapaian, 'semifinal') !== false) {
                    $badgeStyle = "background:rgba(249,115,22,0.2); border:1px solid #f97316; color:#f97316;";
                }

                // Status Badge (Berlangsung / Selesai / Akan Datang)
                $today = date('Y-m-d');
                $tMulai = $t['tanggal_mulai'];
                $tSelesai = $t['tanggal_selesai'] ?: $t['tanggal_mulai'];
                
                if ($today < $tMulai) {
                    $statusLabel = "Akan Datang ⏳";
                    $statusStyle = "background:rgba(56,189,248,0.15); border:1px solid #38bdf8; color:#38bdf8;";
                } elseif ($today >= $tMulai && $today <= $tSelesai) {
                    $statusLabel = "Berlangsung 🟢";
                    $statusStyle = "background:rgba(34,197,94,0.2); border:1px solid #22c55e; color:#4ade80;";
                } else {
                    $statusLabel = "Selesai 🏁";
                    $statusStyle = "background:rgba(148,163,184,0.15); border:1px solid rgba(148,163,184,0.3); color:#cbd5e1;";
                }
            ?>
                <div class="tournament-card tourney-item" data-ku="ku-<?= $kuTag ?>" style="padding: 1.4rem; border-radius: 16px; background: linear-gradient(135deg, rgba(30, 27, 75, 0.4) 0%, rgba(15, 23, 42, 0.7) 100%); border: 1px solid rgba(99, 102, 241, 0.25); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='#818cf8'; this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 30px rgba(0,0,0,0.5)';" onmouseout="this.style.borderColor='rgba(99, 102, 241, 0.25)'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    
                    <!-- Ambient Subtle Glow -->
                    <div style="position: absolute; right: -20px; top: -20px; width: 120px; height: 120px; background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

                    <div>
                        <!-- CARD HEADER BADGES -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 0.85rem; flex-wrap: wrap;">
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <span style="<?= $statusStyle ?> padding: 3px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase;">
                                    <?= $statusLabel ?>
                                </span>
                                <span class="badge badge-primary" style="font-size: 0.68rem; font-weight: 700; border: 1px solid rgba(99,102,241,0.3);">
                                    <?= htmlspecialchars($t['kelompok_usia'] ?? 'Semua KU') ?>
                                </span>
                            </div>
                            <span style="<?= $badgeStyle ?> padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;">
                                <?= $pencapaian ?>
                            </span>
                        </div>

                        <!-- TITLE -->
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: #fff; margin-bottom: 0.75rem; line-height: 1.3;">
                            <?= htmlspecialchars($t['nama_turnamen']) ?>
                        </h3>

                        <!-- META METRICS GRID -->
                        <div style="background: rgba(15, 23, 42, 0.6); padding: 0.75rem 0.9rem; border-radius: 12px; border: 1px solid var(--border-glass); margin-bottom: 1.1rem; display: flex; flex-direction: column; gap: 6px;">
                            <div style="font-size: 0.8rem; color: #cbd5e1; display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 0.9rem;">📍</span> <strong style="color: #fff;"><?= htmlspecialchars($t['lokasi'] ?: 'Makassar') ?></strong>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 0.9rem;">🗓️</span> 
                                <?= date('d M Y', strtotime($t['tanggal_mulai'])) ?>
                                <?php if ($t['tanggal_selesai'] && $t['tanggal_selesai'] !== $t['tanggal_mulai']): ?>
                                    s/d <?= date('d M Y', strtotime($t['tanggal_selesai'])) ?>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; padding-top: 4px; border-top: 1px dashed rgba(255,255,255,0.1); font-size: 0.75rem; font-weight: 700;">
                                <span style="color: #38bdf8;">👥 <?= $t['total_pemain_tercatat'] ?> Pemain Fit</span>
                                <span style="color: #34d399;">⚽ <?= $t['total_gol_turnamen'] ?> Gol Tim</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARD FOOTER ACTIONS -->
                    <div style="border-top: 1px solid var(--border-glass); padding-top: 0.85rem; margin-top: 0.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                            <div style="display: flex; gap: 5px;">
                                <a href="kelola.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm" style="font-size: 0.75rem; padding: 4px 10px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); font-weight: 700;">
                                    ⚙️ Kelola Pemain
                                </a>
                                <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 4px 8px;" title="Edit Data Turnamen">
                                    ✏️ Edit
                                </a>
                            </div>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <a href="pemain.php?turnamen_id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8; border-color: rgba(56, 189, 248, 0.4); display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 700;">
                            <span>Stat Pemain ⚽</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- DYNAMIC FILTER & SEARCH JAVASCRIPT -->
<script>
let currentKuFilter = 'all';

function filterTournaments(ku, btn) {
    currentKuFilter = ku;
    
    // Update active button styling
    document.querySelectorAll('.tourney-filter-btn').forEach(b => {
        b.style.background = 'rgba(15,23,42,0.6)';
        b.style.color = 'var(--text-muted)';
        b.style.borderColor = 'var(--border-glass)';
    });

    btn.style.background = 'rgba(99,102,241,0.25)';
    btn.style.color = '#fff';
    btn.style.borderColor = '#818cf8';

    applyTourneyFilter();
}

function applyTourneyFilter() {
    let searchVal = document.getElementById('tourneySearchInput').value.toLowerCase().trim();
    let cards = document.querySelectorAll('.tourney-item');
    let visibleCount = 0;

    cards.forEach(card => {
        let cardKu = card.getAttribute('data-ku');
        let cardText = card.textContent.toLowerCase();

        let matchesKu = (currentKuFilter === 'all') || (cardKu === currentKuFilter);
        let matchesSearch = (searchVal === '') || cardText.includes(searchVal);

        if (matchesKu && matchesSearch) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update Counter Badge
    const countBadge = document.getElementById('tourneyCountBadge');
    if (countBadge) {
        countBadge.innerHTML = `Menampilkan <strong>${visibleCount}</strong> Turnamen`;
    }
}

document.getElementById('tourneySearchInput')?.addEventListener('keyup', applyTourneyFilter);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
