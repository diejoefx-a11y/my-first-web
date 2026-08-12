<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireAuth();

$pdo = getPdo();
$user = getAuthUser();

$id = (int)($_GET['id'] ?? 0);
if ($user['role'] === 'atlet') {
    $id = $user['atlet_id'];
}

$stmt = $pdo->prepare("SELECT * FROM atlet WHERE id = ?");
$stmt->execute([$id]);
$atlet = $stmt->fetch();

if (!$atlet) {
    die("<div style='padding:2rem; color:red; font-family:sans-serif;'>Atlet tidak ditemukan!</div>");
}

$photoPath = __DIR__ . '/assets/img/atlet/' . ($atlet['foto_profil'] ?? '');
$hasPhoto = !empty($atlet['foto_profil']) && $atlet['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak ID Card - <?= htmlspecialchars($atlet['nama_lengkap']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #090d16;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .id-card { box-shadow: none !important; border: 2px solid #6366f1; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 2rem; display:flex; gap:1rem;">
        <a href="atlet/detail.php?id=<?= $atlet['id'] ?>" class="btn btn-secondary">&larr; Kembali ke Profil</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak Kartu Anggota</button>
    </div>

    <div class="id-card" id="idCard">
        <div class="id-card-header">
            <h3 style="font-family:'Outfit'; font-size:1.3rem; font-weight:800; color:#fff; letter-spacing:1px; margin-bottom:2px;">SSB TAMALANREA</h3>
            <div style="font-size:0.75rem; color:#818cf8; font-weight:700; letter-spacing:0.5px;">KARTU TANDA ANGGOTA ATLET</div>
        </div>

        <div style="width:100px; height:100px; border-radius:50%; background:linear-gradient(135deg, #6366f1, #a855f7); color:#fff; font-size:2.2rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem auto; border:4px solid #818cf8; box-shadow:0 8px 20px rgba(99,102,241,0.4); overflow:hidden;">
            <?php if ($hasPhoto): ?>
                <img src="assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?>
            <?php endif; ?>
        </div>

        <h2 style="font-family:'Outfit'; font-size:1.4rem; font-weight:800; color:#fff; margin-bottom:4px;"><?= htmlspecialchars($atlet['nama_lengkap']) ?></h2>
        <div style="font-size:0.9rem; color:#38bdf8; font-weight:700; margin-bottom:1.25rem;">
            <?= htmlspecialchars($atlet['posisi_utama']) ?> &bull; <span style="color:#fbbf24;"><?= htmlspecialchars($atlet['kelompok_usia']) ?></span>
        </div>

        <div style="text-align:left; background:rgba(255,255,255,0.08); padding:1rem; border-radius:14px; font-size:0.85rem; line-height:1.7; margin-bottom:1.25rem; border:1px solid rgba(255,255,255,0.1);">
            <div><strong>NISN/NIK:</strong> <span style="font-family:monospace; color:#a5b4fc;"><?= htmlspecialchars($atlet['nisn_nik'] ?: '-') ?></span></div>
            <?php if (!empty($atlet['no_kk'])): ?>
                <div><strong>No. KK:</strong> <span style="font-family:monospace; color:#a5b4fc;"><?= htmlspecialchars($atlet['no_kk']) ?></span></div>
            <?php endif; ?>
            <div><strong>Tempat/Tgl Lahir:</strong> <?= htmlspecialchars($atlet['tempat_lahir']) ?>, <?= date('d/m/Y', strtotime($atlet['tanggal_lahir'])) ?></div>
            <div><strong>Kaki Dominan:</strong> <?= htmlspecialchars($atlet['kaki_dominan']) ?></div>
            <div><strong>Postur Fisik:</strong> <?= $atlet['tinggi_badan'] ?> cm / <?= $atlet['berat_badan'] ?> kg</div>
        </div>


        <div style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; border-top:1px dashed rgba(255,255,255,0.2); padding-top:0.75rem;">
            RESMI &bull; SSB TAMALANREA MAKASSAR
        </div>
    </div>

</body>
</html>

