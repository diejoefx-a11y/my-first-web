<?php
// print_qr.php - Halaman Cetak Barcode / QR Code untuk Ditempel di Meja Pertandingan Catur
require_once __DIR__ . '/config/database.php';

$db = get_db();
$stmt = $db->query("SELECT * FROM `meja_catur` ORDER BY id ASC");
$daftarMeja = $stmt->fetchAll();

$baseUrlMobile = get_base_url(true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barcode Meja Catur</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f1f5f9;
            padding: 2rem 1rem;
        }
        .print-header {
            max-width: 900px;
            margin: 0 auto 2rem auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .qr-sheet-grid {
            max-width: 900px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        .qr-card-print {
            background: #ffffff;
            border: 2px dashed #0f172a;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            page-break-inside: avoid;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .qr-card-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .qr-card-subtitle {
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 1rem;
        }
        .qr-render-box {
            background: #ffffff;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .qr-render-box canvas {
            display: block;
            margin: 0 auto;
        }
        .qr-match-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            width: 100%;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
            text-align: left;
        }
        .qr-instructions {
            font-size: 0.8rem;
            color: #475569;
            font-weight: 600;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .qr-sheet-grid {
                max-width: 100%;
                gap: 1rem;
            }
            .qr-card-print {
                border: 2px solid #000000;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

    <div class="print-header no-print">
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 800;">🖨️ Cetak Kartu Barcode Meja</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Gunting dan letakkan kartu ini di atas meja catur untuk di-scan oleh pemain.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php" class="btn btn-secondary btn-sm">⬅️ Kembali ke Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Cetak / Print Sekarang</button>
        </div>
    </div>

    <div class="qr-sheet-grid">
        <?php if (empty($daftarMeja)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: #fff; border-radius: 12px;">
                <p>Belum ada meja. Silakan <a href="index.php">tambah meja</a> terlebih dahulu.</p>
            </div>
        <?php else: ?>
            <?php foreach ($daftarMeja as $meja): 
                $qrUrl = $baseUrlMobile . '/timer.php?id=' . $meja['id'];
                $timeText = $meja['time_base_minutes'] . ' mnt';
                if ($meja['time_increment_seconds'] > 0) $timeText .= ' +' . $meja['time_increment_seconds'] . 's';
            ?>
                <div class="qr-card-print">
                    <div class="qr-card-title">♟️ <?= htmlspecialchars($meja['nomor_meja']) ?></div>
                    <div class="qr-card-subtitle"><?= htmlspecialchars($meja['kategori_babak']) ?> • Format: <?= htmlspecialchars($timeText) ?></div>
                    
                    <div class="qr-render-box" id="qr-table-<?= $meja['id'] ?>" data-url="<?= htmlspecialchars($qrUrl) ?>"></div>

                    <div class="qr-match-info">
                        <div>⚪ <strong>Putih:</strong> <?= htmlspecialchars($meja['nama_putih']) ?></div>
                        <div style="margin-top: 0.25rem;">⚫ <strong>Hitam:</strong> <?= htmlspecialchars($meja['nama_hitam']) ?></div>
                    </div>

                    <div class="qr-instructions">
                        📱 Scan Barcode dengan Kamera HP untuk membuka Jam Catur
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="assets/js/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.qr-render-box').forEach(box => {
                const url = box.getAttribute('data-url');
                new QRCode(box, {
                    text: url,
                    width: 180,
                    height: 180,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            });
        });
    </script>
</body>
</html>
