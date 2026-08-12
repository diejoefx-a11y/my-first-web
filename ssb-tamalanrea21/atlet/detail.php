<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

$pdo = getPdo();
$user = getAuthUser();

$id = (int)($_GET['id'] ?? 0);
if ($user['role'] === 'atlet') {
    $id = $user['atlet_id'];
}

// Fetch Athlete Data
$stmt = $pdo->prepare("SELECT a.*, o.nama_ayah, o.nama_ibu, o.no_whatsapp, o.alamat FROM atlet a LEFT JOIN orang_tua o ON a.id = o.atlet_id WHERE a.id = ?");
$stmt->execute([$id]);
$atlet = $stmt->fetch();

if (!$atlet) {
    die("<div style='padding:2rem; color:red;'>Data atlet tidak ditemukan! <a href='index.php'>Kembali</a></div>");
}


$pageTitle = "Profil Atlet - " . htmlspecialchars($atlet['nama_lengkap']);

// Check photo existence
$photoPath = __DIR__ . '/../assets/img/atlet/' . ($atlet['foto_profil'] ?? '');
$hasPhoto = !empty($atlet['foto_profil']) && $atlet['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);

// Fetch Latest Evaluation / Raport
$stmtEval = $pdo->prepare("SELECT * FROM evaluasi_atlet WHERE atlet_id = ? ORDER BY tanggal_evaluasi DESC LIMIT 1");
$stmtEval->execute([$id]);
$evaluasi = $stmtEval->fetch();

// Fetch SPP History
$stmtSpp = $pdo->prepare("SELECT * FROM iuran_spp WHERE atlet_id = ? ORDER BY tahun DESC, bulan DESC");
$stmtSpp->execute([$id]);
$sppList = $stmtSpp->fetchAll();

// Fetch Tournament Stats
$stmtStats = $pdo->prepare("SELECT s.*, t.nama_turnamen, t.lokasi FROM statistik_pertandingan s JOIN turnamen t ON s.turnamen_id = t.id WHERE s.atlet_id = ?");
$stmtStats->execute([$id]);
$tournamentStats = $stmtStats->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Athlete Profile Header Banner -->
<div class="card" style="position:relative; overflow:hidden;">
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:1.5rem; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:1.5rem;">
            <div style="width:90px; height:90px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; overflow:hidden; border:3px solid var(--primary); box-shadow:0 8px 25px rgba(99,102,241,0.4); flex-shrink:0;">
                <?php if ($hasPhoto): ?>
                    <img src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Foto <?= htmlspecialchars($atlet['nama_lengkap']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <span style="font-size:2rem; font-weight:800; color:#fff;"><?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
                    <h2 style="font-family:'Outfit'; font-size:1.8rem; font-weight:800; color:#fff;"><?= htmlspecialchars($atlet['nama_lengkap']) ?></h2>
                    <span class="badge badge-primary"><?= htmlspecialchars($atlet['kelompok_usia']) ?></span>
                    <span class="badge badge-emerald"><?= htmlspecialchars($atlet['status_keanggotaan']) ?></span>
                </div>
                <div style="font-size:0.9rem; color:var(--text-muted); display:flex; gap:15px; flex-wrap:wrap;">
                    <span>⚽ <strong>Posisi:</strong> <?= htmlspecialchars($atlet['posisi_utama']) ?></span>
                    <span>👟 <strong>Kaki:</strong> <?= htmlspecialchars($atlet['kaki_dominan']) ?></span>
                    <span>📏 <strong>Tinggi/Berat:</strong> <?= $atlet['tinggi_badan'] ?> cm / <?= $atlet['berat_badan'] ?> kg</span>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="../idcard.php?id=<?= $atlet['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">🪪 Cetak ID Card</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="hapus.php?id=<?= $atlet['id'] ?>" class="btn btn-secondary btn-sm" style="color:#f87171;" onclick="return confirm('Apakah Anda yakin ingin menghapus atlet <?= htmlspecialchars(addslashes($atlet['nama_lengkap'])) ?>? Seluruh riwayat raport & SPP atlet ini akan terhapus.');">🗑️ Hapus Atlet</a>
            <?php endif; ?>
        </div>


    </div>
</div>

<div class="grid-2">
    <!-- 1. Raport Perkembangan & Atribut Skill -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📊 Raport Performa Atlet</h2>
            <?php if ($evaluasi): ?>
                <a href="../evaluasi/edit.php?id=<?= $evaluasi['id'] ?>" class="btn btn-secondary btn-sm" style="color:#fbbf24;">✏️ Edit Raport Ini</a>
            <?php endif; ?>
        </div>



        <?php if ($evaluasi): ?>
            <?php
                $stPassing = getScoreStyle($evaluasi['nilai_passing']);
                $stDribbling = getScoreStyle($evaluasi['nilai_dribbling']);
                $stShooting = getScoreStyle($evaluasi['nilai_shooting']);
                $stTackling = getScoreStyle($evaluasi['nilai_tackling']);
                $stStamina = getScoreStyle($evaluasi['nilai_stamina']);
                $stDisiplin = getScoreStyle($evaluasi['nilai_disiplin']);
            ?>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:1.25rem;">
                Tanggal Evaluasi Terakhir: <strong><?= date('d F Y', strtotime($evaluasi['tanggal_evaluasi'])) ?></strong>
            </div>

            <div style="display:grid; gap:1rem;">
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600;">
                        <span>Passing & Control</span>
                        <span style="color:<?= $stPassing['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_passing'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stPassing['barFill'] ?>"></div></div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600;">
                        <span>Dribbling & Ball Handling</span>
                        <span style="color:<?= $stDribbling['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_dribbling'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stDribbling['barFill'] ?>"></div></div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600;">
                        <span>Shooting & Finishing</span>
                        <span style="color:<?= $stShooting['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_shooting'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stShooting['barFill'] ?>"></div></div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600;">
                        <span>Tackling & Defending</span>
                        <span style="color:<?= $stTackling['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_tackling'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stTackling['barFill'] ?>"></div></div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600;">
                        <span>Stamina & Fisik (VO2Max: <?= $evaluasi['vo2max'] ?>)</span>
                        <span style="color:<?= $stStamina['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_stamina'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stStamina['barFill'] ?>"></div></div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600;">
                        <span>Disiplin & Sikap</span>
                        <span style="color:<?= $stDisiplin['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_disiplin'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stDisiplin['barFill'] ?>"></div></div>
                </div>
            </div>


            <div style="margin-top:1.5rem; background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; margin-bottom:4px;">Catatan Pelatih:</div>
                <p style="font-size:0.9rem; color:#f1f5f9; font-style:italic;">"<?= htmlspecialchars($evaluasi['catatan_pelatih']) ?>"</p>
            </div>
        <?php else: ?>
            <p style="color:var(--text-muted);">Belum ada raport evaluasi untuk atlet ini.</p>
        <?php endif; ?>
    </div>

    <!-- 2. Detail Biodata & Kontak Orang Tua -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">👨‍👩‍👦 Informasi Legalitas & Wali</h2>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="edit.php?id=<?= $atlet['id'] ?>" class="btn btn-primary btn-sm">✏️ Edit Profile</a>
            <?php endif; ?>
        </div>

        <div style="display:grid; gap:1rem;">
            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">TEMPAT & TANGGAL LAHIR</div>
                <div style="font-size:0.95rem; color:#fff; font-weight:600; margin-top:2px;">
                    <?= htmlspecialchars($atlet['tempat_lahir'] ?: '-') ?>, <?= !empty($atlet['tanggal_lahir']) ? date('d F Y', strtotime($atlet['tanggal_lahir'])) : '-' ?>
                </div>
            </div>

            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">NISN / NIK</div>
                <div style="font-family:monospace; font-size:1rem; color:#a5b4fc;"><?= htmlspecialchars($atlet['nisn_nik'] ?: '-') ?></div>
            </div>


            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; margin-bottom:2px;">KARTU KELUARGA (KK)</div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-family:monospace; font-size:0.95rem; color:#fff;"><?= htmlspecialchars($atlet['no_kk'] ?: '-') ?></div>
                    <?php if (!empty($atlet['file_kk'])): ?>
                        <a href="../assets/docs/<?= htmlspecialchars($atlet['file_kk']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:0.75rem; color:#60a5fa;">📄 Lihat Berkas KK</a>
                    <?php else: ?>
                        <span style="font-size:0.75rem; color:var(--text-muted);">Belum ada berkas</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; margin-bottom:2px;">AKTA KELAHIRAN</div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-family:monospace; font-size:0.95rem; color:#fff;"><?= htmlspecialchars($atlet['no_akta'] ?: '-') ?></div>
                    <?php if (!empty($atlet['file_akta'])): ?>
                        <a href="../assets/docs/<?= htmlspecialchars($atlet['file_akta']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:0.75rem; color:#60a5fa;">📄 Lihat Berkas Akta</a>
                    <?php else: ?>
                        <span style="font-size:0.75rem; color:var(--text-muted);">Belum ada berkas</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">NAMA AYAH / IBU</div>
                <div style="font-size:0.95rem; color:#fff; font-weight:600;">
                    Ayah: <?= htmlspecialchars($atlet['nama_ayah'] ?: '-') ?><br>
                    Ibu: <?= htmlspecialchars($atlet['nama_ibu'] ?: '-') ?>
                </div>
            </div>

            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">NOMOR WHATSAPP WALI</div>
                <div style="font-size:1.1rem; color:#34d399; font-weight:700; display:flex; align-items:center; justify-content:space-between; margin-top:4px;">
                    <span><?= htmlspecialchars($atlet['no_whatsapp'] ?: '-') ?></span>
                    <?php if ($atlet['no_whatsapp']): ?>
                        <?php 
                            $waNum = preg_replace('/[^0-9]/', '', $atlet['no_whatsapp']);
                            if (strpos($waNum, '0') === 0) $waNum = '62' . substr($waNum, 1);
                        ?>
                        <a href="https://wa.me/<?= $waNum ?>" target="_blank" class="btn btn-secondary btn-sm" style="color:#22c55e;">Chat WA &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">ALAMAT TINGGAL</div>
                <div style="font-size:0.9rem; color:#f1f5f9;"><?= nl2br(htmlspecialchars($atlet['alamat'] ?: '-')) ?></div>
            </div>
        </div>
    </div>

</div>

<!-- 3. Riwayat SPP & Turnamen Stats -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">💳 Riwayat Pembayaran SPP</h2>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="../iuran/index.php?atlet_id=<?= $atlet['id'] ?>" class="btn btn-secondary btn-sm">Kelola SPP</a>
            <?php endif; ?>
        </div>


        <table class="data-table">
            <thead>
                <tr>
                    <th>Bulan / Tahun</th>
                    <th>Nominal</th>
                    <th>Status</th>
                    <th>Tanggal Bayar</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $bulanMap = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
                foreach ($sppList as $spp): 
                ?>
                    <tr>
                        <td><strong><?= $bulanMap[$spp['bulan']] ?> <?= $spp['tahun'] ?></strong></td>
                        <td>Rp <?= number_format($spp['jumlah'], 0, ',', '.') ?></td>
                        <td>
                            <?php if ($spp['status_bayar'] == 'Lunas'): ?>
                                <span class="badge badge-emerald">Lunas</span>
                            <?php else: ?>
                                <span class="badge badge-rose">Belum Bayar</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $spp['tanggal_bayar'] ? date('d/m/Y', strtotime($spp['tanggal_bayar'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Tournament Performance Stats -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏆 Statistik Pertandingan & Turnamen</h2>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Turnamen</th>
                    <th>Main</th>
                    <th>Gol</th>
                    <th>Assist</th>
                    <th>Kartu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tournamentStats) == 0): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Belum ada riwayat turnamen.</td></tr>
                <?php else: ?>
                    <?php foreach ($tournamentStats as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['nama_turnamen']) ?></strong></td>
                            <td><?= $t['main'] ?> match</td>
                            <td><strong style="color:#22c55e;"><?= $t['gol'] ?> ⚽</strong></td>
                            <td><?= $t['assist'] ?> 🎯</td>
                            <td>🟨 <?= $t['kartu_kuning'] ?> | 🟥 <?= $t['kartu_merah'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ID Card Modal Container (Hidden until printed) -->
<div style="display:none;">
    <div id="idCardPlayer" class="id-card">
        <div class="id-card-header">
            <h3 style="font-family:'Outfit'; font-size:1.2rem; font-weight:800; color:#fff; letter-spacing:1px;">SSB TAMALANREA</h3>
            <div style="font-size:0.75rem; color:#818cf8; font-weight:600;">KARTU TANDA ANGGOTA ATLET</div>
        </div>

        <div style="width:90px; height:90px; border-radius:50%; background:#6366f1; color:#fff; font-size:2rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem auto; border:3px solid #818cf8; overflow:hidden;">
            <?php if ($hasPhoto): ?>
                <img src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?>
            <?php endif; ?>
        </div>

        <h2 style="font-family:'Outfit'; font-size:1.3rem; font-weight:700; color:#fff; margin-bottom:4px;"><?= htmlspecialchars($atlet['nama_lengkap']) ?></h2>
        <div style="font-size:0.85rem; color:#38bdf8; font-weight:600; margin-bottom:1rem;"><?= htmlspecialchars($atlet['posisi_utama']) ?> &bull; <?= htmlspecialchars($atlet['kelompok_usia']) ?></div>

        <div style="text-align:left; background:rgba(255,255,255,0.08); padding:0.85rem; border-radius:12px; font-size:0.8rem; line-height:1.6; margin-bottom:1rem;">
            <div><strong>NISN/NIK:</strong> <?= htmlspecialchars($atlet['nisn_nik'] ?: '-') ?></div>
            <div><strong>Tgl Lahir:</strong> <?= date('d/m/Y', strtotime($atlet['tanggal_lahir'])) ?></div>
            <div><strong>Kaki Dominan:</strong> <?= htmlspecialchars($atlet['kaki_dominan']) ?></div>
        </div>

        <div style="font-size:0.65rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">
            Terverifikasi &bull; SSB Tamalanrea Makassar
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
