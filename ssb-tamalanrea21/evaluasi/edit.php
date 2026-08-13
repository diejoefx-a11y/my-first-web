<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole(['admin', 'pelatih']);

$pdo = getPdo();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT e.*, a.nama_lengkap, a.kelompok_usia, a.posisi_utama, a.posisi_sekunder, a.foto_profil 
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

        $kuTarget = urlencode($evaluasi['kelompok_usia']);
        header("Location: index.php?ku={$kuTarget}&success=eval_updated");
        exit;
    } catch (Exception $e) {
        $error = "Gagal memperbarui data evaluasi: " . $e->getMessage();
    }
}

include_once __DIR__ . '/../includes/header.php';

$photoPath = __DIR__ . '/../assets/img/atlet/' . ($evaluasi['foto_profil'] ?? '');
$hasPhoto = !empty($evaluasi['foto_profil']) && $evaluasi['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);

$initOvr = (int)round(($evaluasi['nilai_passing'] + $evaluasi['nilai_dribbling'] + $evaluasi['nilai_shooting'] + $evaluasi['nilai_tackling'] + $evaluasi['nilai_stamina']) / 5);

if ($initOvr >= 90) {
    $ovrGrade = "ELITE 👑";
    $ovrColor = "#38bdf8";
    $ovrBadgeBg = "rgba(56, 189, 248, 0.2)";
} elseif ($initOvr >= 80) {
    $ovrGrade = "GOLD 🏆";
    $ovrColor = "#fbbf24";
    $ovrBadgeBg = "rgba(251, 191, 36, 0.2)";
} elseif ($initOvr >= 70) {
    $ovrGrade = "SILVER 🥈";
    $ovrColor = "#cbd5e1";
    $ovrBadgeBg = "rgba(203, 213, 225, 0.2)";
} else {
    $ovrGrade = "BRONZE 🥉";
    $ovrColor = "#f97316";
    $ovrBadgeBg = "rgba(249, 115, 22, 0.2)";
}
?>

<div style="max-width:950px; margin:0 auto; display:flex; flex-direction:column; gap:1.5rem;">

    <!-- ATHLETE HERO CARD (FUT HERO CARD DESIGN MATCHING DETAIL.PHP) -->
    <div class="card" style="position:relative; overflow:hidden; background: linear-gradient(135deg, rgba(30, 27, 75, 0.95) 0%, rgba(15, 23, 42, 0.98) 50%, rgba(17, 24, 39, 0.95) 100%); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.7); margin-bottom: 0.5rem; padding: 1.5rem 1.75rem;">
        <!-- Glowing Ambient Orbs -->
        <div style="position:absolute; right:-40px; top:-40px; width:280px; height:280px; background:radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
        <div style="position:absolute; left:25%; bottom:-60px; width:220px; height:220px; background:radial-gradient(circle, <?= $ovrColor ?>33 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1.75rem; position:relative; z-index:2;">
            
            <!-- LEFT COLUMN: INFO UTAMA & TOMBOL AKSI -->
            <div style="flex:1; min-width:280px;">
                <!-- BADGE HEADER -->
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                    <span id="heroGradeBadge" style="background:<?= $ovrBadgeBg ?>; border:1px solid <?= $ovrColor ?>; color:<?= $ovrColor ?>; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;">
                        <?= $ovrGrade ?>
                    </span>
                    <span class="badge badge-primary" style="font-weight:700; border:1px solid rgba(99,102,241,0.4);">
                        KATEGORI <?= htmlspecialchars($evaluasi['kelompok_usia']) ?>
                    </span>
                </div>

                <!-- NAMA ATLET -->
                <h1 style="font-family:'Outfit', sans-serif; font-size:1.85rem; font-weight:800; color:#fff; margin-bottom:8px; letter-spacing:-0.5px;">
                    Edit Raport: <?= htmlspecialchars($evaluasi['nama_lengkap']) ?>
                </h1>

                <!-- METRIK STRUKTURAL -->
                <div style="font-size:0.85rem; color:#cbd5e1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem;">
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                        ⚽ <strong>Utama:</strong> <span style="color:#38bdf8; font-weight:700;"><?= htmlspecialchars($evaluasi['posisi_utama'] ?: '-') ?></span>
                    </span>
                    <?php if (!empty($evaluasi['posisi_sekunder']) && $evaluasi['posisi_sekunder'] !== '-'): ?>
                        <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:8px; border:1px solid rgba(56,189,248,0.25);">
                            🔄 <strong>Sekunder:</strong> <span style="color:#7dd3fc; font-weight:700;"><?= htmlspecialchars($evaluasi['posisi_sekunder']) ?></span>
                        </span>
                    <?php endif; ?>
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                        📅 <strong>Evaluasi:</strong> <span style="color:#fbbf24; font-weight:700;"><?= date('d F Y', strtotime($evaluasi['tanggal_evaluasi'])) ?></span>
                    </span>
                </div>

                <!-- GROUP TOMBOL AKSI OPERASIONAL -->
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <a href="index.php?ku=<?= urlencode($evaluasi['kelompok_usia']) ?>" class="btn btn-secondary btn-sm" style="padding:0.6rem 1.1rem; border-color:rgba(148,163,184,0.3);">
                        &larr; Batal & Kembali ke Tabel Evaluasi
                    </a>
                </div>
            </div>

            <!-- RIGHT COLUMN: FOTO PROFILE HERO CARD DENGAN OVR RATING -->
            <div style="position:relative; flex-shrink:0;">
                <div style="width:135px; height:135px; border-radius:24px; background:linear-gradient(135deg, var(--primary), #818cf8); display:flex; align-items:center; justify-content:center; overflow:hidden; border:3px solid <?= $ovrColor ?>; box-shadow:0 0 25px <?= $ovrColor ?>55, 0 10px 25px rgba(0,0,0,0.5);">
                    <?php if ($hasPhoto): ?>
                        <img src="../assets/img/atlet/<?= htmlspecialchars($evaluasi['foto_profil']) ?>" alt="Foto Atlet" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span style="font-size:2.8rem; font-weight:800; color:#fff; font-family:'Outfit', sans-serif;"><?= strtoupper(substr($evaluasi['nama_lengkap'], 0, 2)) ?></span>
                    <?php endif; ?>
                </div>

                <!-- FLOATING OVR RATING PILL OVERLAY -->
                <div style="position:absolute; bottom:-12px; left:50%; transform:translateX(-50%); background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,27,75,0.95)); border:1.5px solid <?= $ovrColor ?>; border-radius:20px; padding:3px 12px; box-shadow:0 4px 12px rgba(0,0,0,0.5); display:flex; align-items:center; gap:5px; white-space:nowrap; z-index:3;">
                    <span id="heroOvrVal" style="font-family:'Outfit', sans-serif; font-size:1rem; font-weight:900; color:<?= $ovrColor ?>;"><?= $initOvr ?></span>
                    <span style="font-size:0.65rem; font-weight:800; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.5px;">OVR</span>
                </div>
            </div>

        </div>
    </div>

    <!-- ERROR ALERT -->
    <?php if ($error): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:1rem; border-radius:14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:1.2rem;">⚠️</span>
            <div style="font-size:0.88rem; font-weight:600;"><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- MAIN FORM EVALUASI -->
    <form method="POST" id="editRaportForm">

        <!-- SECTION 1: PARAMETER GENERAL & VO2MAX -->
        <div class="card" style="border:1px solid rgba(99,102,241,0.3); margin-bottom:1.25rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(99,102,241,0.25), rgba(129,140,248,0.15)); color:#818cf8; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(99,102,241,0.3);">
                        📋
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">1. Parameter Jadwal & Uji Kebugaran Fisik</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Tanggal penilaian resmi dan hasil tes VO2Max stamina</p>
                    </div>
                </div>

                <div id="vo2CategoryBadge" style="background:rgba(34,211,238,0.12); border:1px solid rgba(34,211,238,0.3); padding:0.4rem 0.85rem; border-radius:10px; font-size:0.75rem; color:#22d3ee; font-weight:700;">
                    🫁 Level VO2Max: <span id="vo2CategoryVal" style="color:#fff;">-</span>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Tanggal Evaluasi *</label>
                    <input type="date" name="tanggal_evaluasi" value="<?= htmlspecialchars($evaluasi['tanggal_evaluasi']) ?>" class="form-control" required style="font-weight:700; color:#fff;">
                </div>

                <div class="form-group">
                    <label style="font-size:0.82rem; font-weight:600; color:#cbd5e1;">Hasil Tes VO2Max (mL/kg/min)</label>
                    <div style="position:relative;">
                        <input type="number" step="0.1" name="vo2max" id="vo2maxInput" value="<?= htmlspecialchars($evaluasi['vo2max']) ?>" class="form-control" placeholder="misal: 48.5" style="font-family:'Courier New', monospace; font-weight:800; color:#22d3ee;" oninput="updateVo2Category()">
                        <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.72rem; color:var(--text-muted); pointer-events:none;">mL/kg/min</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: INTERACTIVE SKILL SLIDERS (SKALA 0 - 100) -->
        <div class="card" style="border:1px solid rgba(56,189,248,0.3); margin-bottom:1.25rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(56,189,248,0.25), rgba(14,165,233,0.15)); color:#38bdf8; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(56,189,248,0.3);">
                        ⚡
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">2. Penilaian Atribut Teknis & Fisik (Skala 0 - 100)</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Geser slider nilai untuk mengubah skor dan kalkulasi OVR otomatis</p>
                    </div>
                </div>

                <span style="font-size:0.72rem; color:#38bdf8; background:rgba(56,189,248,0.12); padding:4px 10px; border-radius:20px; border:1px solid rgba(56,189,248,0.25); font-weight:600;">
                    🎛️ Live Interactive Sliders
                </span>
            </div>

            <!-- SKILL SLIDERS GRID -->
            <div style="display:flex; flex-direction:column; gap:1.2rem;">

                <?php
                $skills = [
                    ['key' => 'nilai_passing',  'label' => 'Passing & Control',        'icon' => '🎯', 'desc' => 'Akurasi umpan pendek/jauh & kontrol bola pertama', 'val' => $evaluasi['nilai_passing']],
                    ['key' => 'nilai_dribbling','label' => 'Dribbling & Ball Handling', 'icon' => '⚡', 'desc' => 'Kelincahan menggiring & olah bola saat dikawal', 'val' => $evaluasi['nilai_dribbling']],
                    ['key' => 'nilai_shooting', 'label' => 'Shooting & Finishing',     'icon' => '⚽', 'desc' => 'Kekuatan & akurasi tembakan ke gawang',          'val' => $evaluasi['nilai_shooting']],
                    ['key' => 'nilai_tackling', 'label' => 'Tackling & Defending',     'icon' => '🛡️', 'desc' => 'Kemampuan merebut bola & posisi bertahan',      'val' => $evaluasi['nilai_tackling']],
                    ['key' => 'nilai_stamina',  'label' => 'Stamina & Endurance',     'icon' => '🫁', 'desc' => 'Daya tahan fisik selama 90 menit pertandingan',   'val' => $evaluasi['nilai_stamina']],
                    ['key' => 'nilai_disiplin', 'label' => 'Disiplin & Mental',        'icon' => '🧠', 'desc' => 'Fokus latihan, ketepatan waktu & sikap sportif',   'val' => $evaluasi['nilai_disiplin']]
                ];

                foreach ($skills as $sk):
                ?>
                    <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:1rem 1.1rem; border-radius:14px; transition:all 0.2s ease;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">
                            <div>
                                <span style="font-size:0.88rem; font-weight:700; color:#fff; display:flex; align-items:center; gap:6px;">
                                    <span><?= $sk['icon'] ?></span> <?= $sk['label'] ?>
                                </span>
                                <span style="font-size:0.72rem; color:var(--text-muted);"><?= $sk['desc'] ?></span>
                            </div>

                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="number" id="<?= $sk['key'] ?>_num" min="0" max="100" value="<?= $sk['val'] ?>" class="form-control" style="width:65px; font-weight:800; font-family:'Courier New', monospace; text-align:center; padding:3px 6px; font-size:0.88rem;" oninput="syncFromNum('<?= $sk['key'] ?>')">
                                <span id="<?= $sk['key'] ?>_badge" style="font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:8px;">-</span>
                            </div>
                        </div>

                        <!-- RANGE SLIDER -->
                        <div style="display:flex; align-items:center; gap:12px; margin-top:8px;">
                            <span style="font-size:0.7rem; color:var(--text-muted); font-weight:700; width:20px;">0</span>
                            <input type="range" id="<?= $sk['key'] ?>_range" name="<?= $sk['key'] ?>" min="0" max="100" value="<?= $sk['val'] ?>" style="flex:1; accent-color:#818cf8; cursor:pointer;" oninput="syncFromRange('<?= $sk['key'] ?>')">
                            <span style="font-size:0.7rem; color:var(--text-muted); font-weight:700; width:25px; text-align:right;">100</span>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- SECTION 3: CATATAN PELATIH & REKOMENDASI -->
        <div class="card" style="border:1px solid rgba(251,191,36,0.3); margin-bottom:1.5rem; background:rgba(15,23,42,0.7);">
            <div style="border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(251,191,36,0.25), rgba(245,158,11,0.15)); color:#fbbf24; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(251,191,36,0.3);">
                        ✍️
                    </span>
                    <div>
                        <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">3. Catatan Evaluasi & Rekomendasi Pelatih</h3>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Ulasan mendalam mengenai kelebihan, kekurangan, dan aspek yang perlu ditingkatkan</p>
                    </div>
                </div>
            </div>

            <!-- PRESET REKOMENDASI CHIPS -->
            <div style="background:rgba(15,23,42,0.5); padding:0.85rem 1rem; border-radius:12px; border:1px solid var(--border-glass); margin-bottom:1rem;">
                <div style="font-size:0.73rem; font-weight:700; color:#fbbf24; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.5rem;">
                    💡 Preset Catatan Cepat (Klik untuk menambahkan ke teks):
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php
                    $presets = [
                        "Akurasi passing sangat baik, perlu tingkatkan stamina akhir pertandingan.",
                        "Kontrol bola sudah matang, tingkatkan kekuatan tembakan kaki kiri.",
                        "Visi bermain luar biasa, pertahankan tingkat disiplin di setiap latihan.",
                        "Perlu meningkatkan kelincahan dribbling dan keberanian duel udara.",
                        "Stamina & daya tahan fisik luar biasa tinggi, calon pemain inti."
                    ];
                    foreach ($presets as $p):
                    ?>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:0.3rem 0.6rem; border-radius:8px; background:rgba(251,191,36,0.1); color:#fde047; border:1px solid rgba(251,191,36,0.25);" onclick="addCatatanPreset('<?= htmlspecialchars(addslashes($p)) ?>')">
                            + <?= $p ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <textarea name="catatan_pelatih" id="catatanPelatihInput" class="form-control" rows="4" placeholder="Tuliskan evaluasi perkembangan, kelebihan, dan aspek yang perlu ditingkatkan..."><?= htmlspecialchars($evaluasi['catatan_pelatih']) ?></textarea>
            </div>
        </div>

        <!-- FLOATING / STICKY ACTION BAR -->
        <div style="background:rgba(15,23,42,0.92); border:1px solid rgba(99,102,241,0.35); padding:1rem 1.25rem; border-radius:16px; backdrop-filter:blur(12px); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
            <div style="font-size:0.8rem; color:var(--text-muted);">
                💡 Setelah disimpan, sistem akan kembali ke tabel evaluasi khusus <strong style="color:#38bdf8;">KU <?= htmlspecialchars($evaluasi['kelompok_usia']) ?></strong>.
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem;">
                <a href="index.php?ku=<?= urlencode($evaluasi['kelompok_usia']) ?>" class="btn btn-secondary" style="padding:0.6rem 1.2rem;">
                    Batal & Kembali
                </a>
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem; font-weight:700; box-shadow:0 4px 15px rgba(99,102,241,0.4);">
                    💾 Simpan Perubahan Raport
                </button>
            </div>
        </div>

    </form>

</div>

<script>
const skillKeys = ['nilai_passing', 'nilai_dribbling', 'nilai_shooting', 'nilai_tackling', 'nilai_stamina', 'nilai_disiplin'];

function getScoreBadgeStyle(val) {
    if (val >= 90) return { bg: 'rgba(56,189,248,0.2)', border: '#38bdf8', color: '#38bdf8', label: 'ELITE' };
    if (val >= 80) return { bg: 'rgba(52,211,153,0.2)', border: '#34d399', color: '#34d399', label: 'BAGUS' };
    if (val >= 70) return { bg: 'rgba(251,191,36,0.2)', border: '#fbbf24', color: '#fbbf24', label: 'CUKUP' };
    return { bg: 'rgba(244,63,94,0.2)', border: '#f87171', color: '#f87171', label: 'PERLU OLAH' };
}

function syncFromRange(key) {
    const range = document.getElementById(key + '_range');
    const num = document.getElementById(key + '_num');
    if (range && num) {
        num.value = range.value;
    }
    updateSingleBadge(key);
    calculateOvr();
}

function syncFromNum(key) {
    const range = document.getElementById(key + '_range');
    const num = document.getElementById(key + '_num');
    if (range && num) {
        let v = parseInt(num.value) || 0;
        if (v > 100) v = 100;
        if (v < 0) v = 0;
        range.value = v;
    }
    updateSingleBadge(key);
    calculateOvr();
}

function updateSingleBadge(key) {
    const range = document.getElementById(key + '_range');
    const badge = document.getElementById(key + '_badge');
    if (!range || !badge) return;

    const val = parseInt(range.value) || 0;
    const st = getScoreBadgeStyle(val);

    badge.style.background = st.bg;
    badge.style.border = '1px solid ' + st.border;
    badge.style.color = st.color;
    badge.innerText = st.label;
}

function calculateOvr() {
    let sum = 0;
    let count = 5; // core 5 skills for OVR: passing, dribbling, shooting, tackling, stamina
    const coreKeys = ['nilai_passing', 'nilai_dribbling', 'nilai_shooting', 'nilai_tackling', 'nilai_stamina'];

    coreKeys.forEach(k => {
        const range = document.getElementById(k + '_range');
        if (range) sum += parseInt(range.value) || 0;
    });

    const ovr = Math.round(sum / count);
    const heroOvr = document.getElementById('heroOvrVal');
    const heroGrade = document.getElementById('heroGradeBadge');

    if (heroOvr) heroOvr.innerText = ovr;

    if (heroGrade) {
        if (ovr >= 90) {
            heroGrade.innerHTML = 'ELITE 👑';
            heroGrade.style.color = '#38bdf8';
            heroGrade.style.borderColor = '#38bdf8';
            heroGrade.style.background = 'rgba(56,189,248,0.2)';
        } else if (ovr >= 80) {
            heroGrade.innerHTML = 'GOLD 🏆';
            heroGrade.style.color = '#fbbf24';
            heroGrade.style.borderColor = '#fbbf24';
            heroGrade.style.background = 'rgba(251,191,36,0.2)';
        } else if (ovr >= 70) {
            heroGrade.innerHTML = 'SILVER 🥈';
            heroGrade.style.color = '#cbd5e1';
            heroGrade.style.borderColor = '#cbd5e1';
            heroGrade.style.background = 'rgba(203,213,225,0.2)';
        } else {
            heroGrade.innerHTML = 'BRONZE 🥉';
            heroGrade.style.color = '#f97316';
            heroGrade.style.borderColor = '#f97316';
            heroGrade.style.background = 'rgba(249,115,22,0.2)';
        }
    }
}

function updateVo2Category() {
    const input = document.getElementById('vo2maxInput');
    const badgeVal = document.getElementById('vo2CategoryVal');
    if (!input || !badgeVal) return;

    const val = parseFloat(input.value) || 0;
    let label = 'Belum Ada Data';
    let color = '#a5b4fc';

    if (val >= 55) { label = 'Istimewa 🫁 (Elite Level)'; color = '#38bdf8'; }
    else if (val >= 48) { label = 'Sangat Baik ⚡'; color = '#34d399'; }
    else if (val >= 42) { label = 'Baik 👍'; color = '#a78bfa'; }
    else if (val >= 35) { label = 'Cukup 👌'; color = '#fbbf24'; }
    else if (val > 0) { label = 'Perlu Latihan Stamina ⚠️'; color = '#f87171'; }

    badgeVal.innerHTML = `<strong style="color:${color};">${label}</strong>`;
}

function addCatatanPreset(text) {
    const area = document.getElementById('catatanPelatihInput');
    if (!area) return;

    if (area.value.trim() === '') {
        area.value = text;
    } else {
        area.value = area.value.trim() + " " + text;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    skillKeys.forEach(k => updateSingleBadge(k));
    calculateOvr();
    updateVo2Category();
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
