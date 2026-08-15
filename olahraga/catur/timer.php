<?php
// timer.php - Jam Catur Digital Mobile & Split Screen untuk Meja Catur
require_once __DIR__ . '/config/database.php';

$mejaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = get_db();

if ($mejaId <= 0) {
    // Ambil meja pertama jika ada
    $stmt = $db->query("SELECT id FROM `meja_catur` ORDER BY id ASC LIMIT 1");
    $firstMeja = $stmt->fetch();
    if ($firstMeja) {
        header("Location: timer.php?id=" . $firstMeja['id']);
        exit;
    } else {
        die("Belum ada meja pertandingan catur yang dibuat. Silakan buka halaman <a href='index.php'>index.php</a> untuk menambah meja.");
    }
}

$stmt = $db->prepare("SELECT * FROM `meja_catur` WHERE id = ?");
$stmt->execute([$mejaId]);
$meja = $stmt->fetch();

if (!$meja) {
    die("Meja pertandingan tidak ditemukan. Silakan kembali ke <a href='index.php'>Dashboard</a>.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($meja['nomor_meja']) ?> - Jam Catur Digital</title>
    
    <!-- CSS Style Jam Catur -->
    <link rel="stylesheet" href="assets/css/timer.css">
</head>
<body>

    <div class="chess-clock-app">
        
        <!-- Sisi Pemain Hitam (Black) - Default diputar 180 derajat untuk pemain berhadapan -->
        <div class="clock-side clock-side-black rotated" id="sideBlack">
            <div class="clock-player-header">
                <div class="clock-player-title">
                    <span class="side-indicator-icon black-piece">♟</span>
                    <span class="player-display-name" id="nameBlack"><?= htmlspecialchars($meja['nama_hitam']) ?></span>
                </div>
                <span class="move-count-badge" id="movesBlack">Langkah: 0</span>
            </div>

            <div class="clock-digits" id="digitsBlack">00:00</div>
            <div class="increment-tag" id="incBlack"></div>
        </div>

        <!-- Central Floating Control Bar -->
        <div class="center-control-bar">
            <button class="ctrl-btn" id="btnRotate" title="Putar Jam Hitam (Toggle 180°)">
                🔄
            </button>
            <button class="ctrl-btn" id="btnSound" title="Suara Jam (Mute/Unmute)">
                🔊
            </button>
            <button class="ctrl-btn ctrl-btn-primary" id="btnPlayPause" title="Mulai / Jeda">
                ▶️
            </button>
            <button class="ctrl-btn" id="btnFullscreen" title="Layar Penuh">
                ⛶
            </button>
            <button class="ctrl-btn" id="btnMenu" title="Menu Opsi & Hasil">
                ⚙️
            </button>
            <div class="match-info-center">
                <span class="table-label"><?= htmlspecialchars($meja['nomor_meja']) ?></span>
                <span class="status-indicator">
                    <span class="sync-dot" id="syncDot" title="Status Sinkronisasi Server"></span>
                    <span id="syncText">DB</span>
                </span>
            </div>
        </div>

        <!-- Sisi Pemain Putih (White) -->
        <div class="clock-side clock-side-white" id="sideWhite">
            <div class="increment-tag" id="incWhite"></div>
            <div class="clock-digits" id="digitsWhite">00:00</div>

            <div class="clock-player-header">
                <div class="clock-player-title">
                    <span class="side-indicator-icon white-piece">♙</span>
                    <span class="player-display-name" id="nameWhite"><?= htmlspecialchars($meja['nama_putih']) ?></span>
                </div>
                <span class="move-count-badge" id="movesWhite">Langkah: 0</span>
            </div>
        </div>

    </div>

    <!-- Modal Menu & Pengaturan Cepat -->
    <div class="timer-modal" id="modalMenu">
        <div class="timer-modal-card">
            <h2>⚙️ Menu Jam Catur</h2>
            <p><?= htmlspecialchars($meja['nomor_meja']) ?> • <?= htmlspecialchars($meja['kategori_babak']) ?></p>

            <div class="modal-actions-grid">
                <!-- Penalti Waktu -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <button class="t-btn t-btn-secondary" onclick="chessClockInstance.adjustPenaltyTime('putih', 60)">
                        ⚪ +1 Mnt Putih
                    </button>
                    <button class="t-btn t-btn-secondary" onclick="chessClockInstance.adjustPenaltyTime('hitam', 60)">
                        ⚫ +1 Mnt Hitam
                    </button>
                </div>

                <!-- Akhiri Pertandingan -->
                <button class="t-btn t-btn-primary" onclick="openModalResult()">
                    🏁 Catat Hasil / Pemenang
                </button>

                <!-- Reset Pertandingan -->
                <button class="t-btn t-btn-danger" onclick="chessClockInstance.resetMatch()">
                    🔄 Reset Waktu Meja Ini
                </button>

                <!-- Kembali ke Dashboard -->
                <a href="index.php" class="t-btn t-btn-secondary">
                    📋 Kembali ke Dashboard
                </a>

                <!-- Tutup Modal -->
                <button class="t-btn t-btn-secondary" onclick="chessClockInstance.closeAllModals()" style="margin-top: 0.5rem;">
                    ✖️ Tutup Menu
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Catat Pemenang -->
    <div class="timer-modal" id="modalResult">
        <div class="timer-modal-card">
            <h2>🏁 Tentukan Hasil Pertandingan</h2>
            <p>Pilih pemenang atau hasil remis untuk meja ini:</p>

            <div class="modal-actions-grid">
                <button class="t-btn t-btn-success" onclick="chessClockInstance.setManualResult('putih', 'Skakmat / Menang')">
                    ⚪ Putih (<?= htmlspecialchars($meja['nama_putih']) ?>) Menang
                </button>
                <button class="t-btn t-btn-danger" onclick="chessClockInstance.setManualResult('hitam', 'Skakmat / Menang')">
                    ⚫ Hitam (<?= htmlspecialchars($meja['nama_hitam']) ?>) Menang
                </button>
                <button class="t-btn t-btn-secondary" onclick="chessClockInstance.setManualResult('remis', 'Remis / Draw Sepakat')">
                    🤝 Remis (Draw)
                </button>
                <button class="t-btn t-btn-secondary" onclick="chessClockInstance.closeAllModals()" style="margin-top: 0.5rem;">
                    Batal
                </button>
            </div>
        </div>
    </div>

    <!-- Flag Fall / Game Over Overlay -->
    <div class="flag-overlay" id="flagOverlay" style="display: none;">
        <div class="flag-icon">🚩</div>
        <h1 class="flag-title" id="flagTitle">WAKTU HABIS!</h1>
        <p class="flag-desc" id="flagDesc">Pertandingan telah berakhir.</p>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center;">
            <button class="t-btn t-btn-secondary" onclick="chessClockInstance.resetMatch()">
                🔄 Main Ulang (Reset)
            </button>
            <a href="index.php" class="t-btn t-btn-secondary">
                📋 Ke Dashboard
            </a>
            <button class="t-btn t-btn-secondary" onclick="chessClockInstance.hideFlagOverlay()">
                👁️ Lihat Jam
            </button>
        </div>
    </div>

    <!-- Variabel ID Meja untuk JavaScript -->
    <script>
        window.MEJA_ID = <?= (int)$meja['id'] ?>;
        function openModalResult() {
            document.getElementById('modalMenu').classList.remove('active');
            document.getElementById('modalResult').classList.add('active');
        }
    </script>

    <!-- Script Jam Catur -->
    <script src="assets/js/timer.js"></script>
</body>
</html>
