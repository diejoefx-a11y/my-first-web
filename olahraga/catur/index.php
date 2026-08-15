<?php
// index.php - Dashboard Manajemen Pertandingan & Timer Catur Multi-Meja
require_once __DIR__ . '/config/database.php';

$db = get_db();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Jam Catur Multi-Meja & Barcode</title>
    <meta name="description" content="Aplikasi Jam Catur Digital Multi-Meja dengan sinkronisasi database MySQL dan scan Barcode QR Code untuk HP pemain.">
    
    <!-- CSS Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Header Dashboard -->
    <header class="dashboard-header">
        <div class="container">
            <div class="header-content">
                <div class="brand-wrapper">
                    <div class="brand-icon">♟️</div>
                    <div class="brand-title">
                        <h1>Arena Catur Multi-Meja</h1>
                        <p>Sistem Jam Catur Digital & Monitoring Turnamen Real-Time</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="print_qr.php" target="_blank" class="btn btn-outline-white" id="btnPrintAllQR" title="Cetak Semua Barcode Meja">
                        🖨️ Cetak Barcode Meja
                    </a>
                    <button class="btn btn-primary" id="btnTambahMeja">
                        ➕ Tambah Meja Baru
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        
        <!-- Statistik Ringkas -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📋</div>
                <div class="stat-info">
                    <h3 id="statTotal">0</h3>
                    <p>Total Meja Terdaftar</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">⚔️</div>
                <div class="stat-info">
                    <h3 id="statRunning">0</h3>
                    <p>Sedang Bertanding</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber">⏳</div>
                <div class="stat-info">
                    <h3 id="statStandby">0</h3>
                    <p>Standby / Pause</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon slate">🏆</div>
                <div class="stat-info">
                    <h3 id="statFinished">0</h3>
                    <p>Pertandingan Selesai</p>
                </div>
            </div>
        </section>

        <!-- Kontrol Filter & Pencarian -->
        <section class="dashboard-controls">
            <div class="filter-group">
                <button class="filter-btn active" data-filter="all">Semua Meja</button>
                <button class="filter-btn" data-filter="running">🟢 Sedang Tanding</button>
                <button class="filter-btn" data-filter="standby">🟡 Standby / Pause</button>
                <button class="filter-btn" data-filter="finished">🏆 Selesai</button>
            </div>
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchMeja" placeholder="Cari nama pemain / nomor meja...">
            </div>
        </section>

        <!-- Grid Meja Pertandingan -->
        <section class="meja-grid" id="mejaGrid">
            <!-- Rendered dynamically by main.js -->
        </section>

    </main>

    <!-- Modal Tambah / Edit Meja -->
    <div class="modal-overlay" id="modalMeja">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalMejaTitle">➕ Tambah Meja Pertandingan</h3>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <form id="formMeja">
                <div class="modal-body">
                    <input type="hidden" id="actionType" value="tambah">
                    <input type="hidden" id="mejaId" value="">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="inputNomorMeja">Nomor / Nama Meja</label>
                            <input type="text" id="inputNomorMeja" class="form-control" placeholder="Contoh: Meja 1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inputKategori">Kategori / Babak</label>
                            <input type="text" id="inputKategori" class="form-control" placeholder="Contoh: Babak 1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="inputNamaPutih">⚪ Pemain Putih (White)</label>
                            <input type="text" id="inputNamaPutih" class="form-control" placeholder="Nama Pemain Putih" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inputNamaHitam">⚫ Pemain Hitam (Black)</label>
                            <input type="text" id="inputNamaHitam" class="form-control" placeholder="Nama Pemain Hitam" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">⚡ Preset Format Waktu Populer</label>
                        <div class="preset-buttons">
                            <button type="button" class="preset-btn" data-base="3" data-inc="2">⚡ Blitz 3+2s</button>
                            <button type="button" class="preset-btn active" data-base="5" data-inc="0">⚡ Blitz 5+0</button>
                            <button type="button" class="preset-btn" data-base="5" data-inc="3">⚡ Blitz 5+3s</button>
                            <button type="button" class="preset-btn" data-base="10" data-inc="0">⏱️ Rapid 10m</button>
                            <button type="button" class="preset-btn" data-base="15" data-inc="10">⏱️ Rapid 15+10s</button>
                            <button type="button" class="preset-btn" data-base="30" data-inc="0">🏆 Klasik 30m</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="inputTimeBase">Waktu Dasar (Menit)</label>
                            <input type="number" id="inputTimeBase" class="form-control" min="1" max="180" value="5" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inputTimeInc">Increment (+Detik per Langkah)</label>
                            <input type="number" id="inputTimeInc" class="form-control" min="0" max="60" value="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-cancel-modal">Batal</button>
                    <button type="submit" class="btn btn-primary">💾 Simpan Meja</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Barcode / QR Code HP -->
    <div class="modal-overlay" id="modalQR">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="qrModalTitle">📱 Barcode Jam Catur HP</h3>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body qr-container">
                <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                    Buka kamera HP / Scanner QR di meja ini untuk langsung memuat jam catur.
                </p>
                <div class="qr-box" id="qrCodeContainer">
                    <!-- Rendered by qrcode.min.js -->
                </div>
                <div class="qr-url-text" id="qrUrlText"></div>
                <div style="display: flex; gap: 0.5rem; width: 100%;">
                    <button type="button" class="btn btn-secondary btn-sm btn-block" onclick="copyQRUrl()">
                        📋 Salin Link
                    </button>
                    <a href="#" target="_blank" id="qrOpenLinkBtn" class="btn btn-primary btn-sm btn-block">
                        ↗️ Buka di Tab Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Alert Container -->
    <div class="toast-container"></div>

    <!-- Scripts -->
    <script src="assets/js/qrcode.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
