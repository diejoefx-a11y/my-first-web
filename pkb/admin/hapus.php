<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $db = get_db();

    // Check photo to delete file
    $stmtPhoto = $db->prepare("SELECT foto_rumah FROM families WHERE id = ?");
    $stmtPhoto->execute([$id]);
    $family = $stmtPhoto->fetch();

    if ($family && !empty($family['foto_rumah'])) {
        $photoPath = __DIR__ . '/../uploads/' . $family['foto_rumah'];
        if (file_exists($photoPath)) {
            @unlink($photoPath);
        }
    }

    // Delete family (family_members will be deleted automatically via ON DELETE CASCADE)
    $stmt = $db->prepare("DELETE FROM families WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Data keluarga berhasil dihapus.');
}

header("Location: keluarga.php");
exit;
