<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();


$pdo = getPdo();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch evaluation to get atlet_id for redirect
$stmt = $pdo->prepare("SELECT atlet_id FROM evaluasi_atlet WHERE id = ?");
$stmt->execute([$id]);
$eval = $stmt->fetch();

if ($eval) {
    $stmtDelete = $pdo->prepare("DELETE FROM evaluasi_atlet WHERE id = ?");
    $stmtDelete->execute([$id]);
}

header("Location: index.php?success=eval_deleted");
exit;
