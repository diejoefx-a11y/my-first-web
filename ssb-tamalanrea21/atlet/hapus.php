<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pdo = getPdo();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch Athlete Data to delete associated uploaded files
$stmt = $pdo->prepare("SELECT * FROM atlet WHERE id = ?");
$stmt->execute([$id]);
$atlet = $stmt->fetch();

if ($atlet) {
    // Delete profile photo if not default
    if (!empty($atlet['foto_profil']) && $atlet['foto_profil'] !== 'default_avatar.png') {
        $photoFile = __DIR__ . '/../assets/img/atlet/' . $atlet['foto_profil'];
        if (file_exists($photoFile)) {
            @unlink($photoFile);
        }
    }

    // Delete KK file if exists
    if (!empty($atlet['file_kk'])) {
        $kkFile = __DIR__ . '/../assets/docs/' . $atlet['file_kk'];
        if (file_exists($kkFile)) {
            @unlink($kkFile);
        }
    }

    // Delete Akta file if exists
    if (!empty($atlet['file_akta'])) {
        $aktaFile = __DIR__ . '/../assets/docs/' . $atlet['file_akta'];
        if (file_exists($aktaFile)) {
            @unlink($aktaFile);
        }
    }

    // Delete Athlete record (ON DELETE CASCADE handles orang_tua, evaluasi_atlet, iuran_spp, etc.)
    $stmtDelete = $pdo->prepare("DELETE FROM atlet WHERE id = ?");
    $stmtDelete->execute([$id]);
}

header("Location: index.php?success=deleted");
exit;
