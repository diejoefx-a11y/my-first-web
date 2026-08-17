<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FPL-BOT | Smart Assistant & Automated Decision Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- Header Navigation & Live Stats Bar -->
    <header class="app-header">
        <div class="container header-content">
            <div class="brand-logo">
                <span class="brand-icon">⚽</span>
                <div class="brand-text">
                    <h1>FPL-BOT ASSISTANT</h1>
                    <p>Decision Engine & Smart Fallback H-30m</p>
                </div>
            </div>

            <div class="header-stats">
                <div class="stat-pill">
                    <span class="label">Target:</span>
                    <span class="value" id="stat-gw">GW --</span>
                </div>
                <div class="stat-pill" id="pill-bank">
                    <span class="label">Sisa Saldo Bank:</span>
                    <span class="value" id="stat-bank" style="font-weight:800;">£0.0m</span>
                </div>
                <div class="stat-pill">
                    <span class="label">Free Transfer:</span>
                    <span class="value" id="stat-ft">1 FT</span>
                </div>
                <div class="stat-pill">
                    <span class="label">⏳ Deadline:</span>
                    <span class="value" id="stat-timer">--:--:--</span>
                </div>
                <div class="stat-pill fallback-pill" title="Jaring pengaman otomatis H-30 menit jika tidak dieksekusi manual">
                    <span class="label">⚡ Fallback:</span>
                    <span class="value" id="stat-fallback-time">--:--</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="container">
        <div class="dashboard-grid">
            
            <!-- LEFT COLUMN: Visual Lapangan Taktis & Tombol Eksekusi -->
            <section>
                <div class="card">
                    <div class="card-title">
                        <span>📋 Susunan Skuad Aktif</span>
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="btn btn-primary" style="width:auto; padding:0.35rem 0.75rem; font-size:0.75rem; background:#00ff87; color:#052e16;" onclick="openSquadBuilder()">✏️ Atur Skuad Tim Saya</button>
                            <button class="btn btn-secondary" style="width:auto; padding:0.35rem 0.75rem; font-size:0.75rem;" onclick="fetchTeamData()">🔄 Refresh</button>
                            <button class="btn btn-secondary" style="width:auto; padding:0.35rem 0.75rem; font-size:0.75rem;" onclick="openModal('modal-logs')">📜 Riwayat Log</button>
                        </div>
                    </div>

                    <!-- Pitch Board -->
                    <div class="pitch-container">
                        <div class="pitch-line-center"></div>
                        <div class="pitch-circle-center"></div>
                        <div class="pitch-penalty-top"></div>
                        <div class="pitch-penalty-bottom"></div>

                        <div class="pitch-layout">
                            <!-- Goalkeepers -->
                            <div class="pitch-row" id="row-gkp"></div>
                            <!-- Defenders -->
                            <div class="pitch-row" id="row-def"></div>
                            <!-- Midfielders -->
                            <div class="pitch-row" id="row-mid"></div>
                            <!-- Forwards -->
                            <div class="pitch-row" id="row-fwd"></div>
                        </div>
                    </div>

                    <!-- Bench Section -->
                    <div class="bench-container">
                        <div class="bench-title">Pemain Cadangan (Bench)</div>
                        <div class="bench-row" id="row-bench"></div>
                    </div>

                    <!-- Tombol 1-Click Manual Execution -->
                    <div style="margin-top: 1.25rem;">
                        <button class="btn btn-primary" id="btn-apply-fpl" style="font-size:1rem; padding:0.85rem;">
                            🚀 Terapkan Rekomendasi ke Akun FPL (1-Click Apply)
                        </button>
                    </div>
                </div>
            </section>

            <!-- RIGHT COLUMN: Rekomendasi, Slider Parameter & YouTube Insight -->
            <section>
                <!-- Card 1: Rekomendasi Transfer & C/VC -->
                <div class="card">
                    <div class="card-title">
                        <span>🎯 Rekomendasi Transfer Terhitung</span>
                    </div>
                    <div id="rec-container"></div>

                    <div style="margin-top:1rem;">
                        <div style="font-size:0.75rem; color:#94a3b8; font-weight:700; text-transform:uppercase; margin-bottom:0.4rem;">Pilihan Ban Kapten</div>
                        <div class="captain-picks">
                            <div class="cap-card c">
                                <span class="badge">C</span>
                                <div>
                                    <div style="font-size:0.85rem; font-weight:700;" id="cap-name">--</div>
                                    <div style="font-size:0.65rem; color:#94a3b8;">Kapten Utama</div>
                                </div>
                            </div>
                            <div class="cap-card vc">
                                <span class="badge">VC</span>
                                <div>
                                    <div style="font-size:0.85rem; font-weight:700;" id="vc-name">--</div>
                                    <div style="font-size:0.65rem; color:#94a3b8;">Wakil Kapten</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Pengaturan Bobot Multi-Parameter -->
                <div class="card">
                    <div class="card-title">
                        <span>⚖️ Pembobotan Scoring Engine</span>
                    </div>
                    <div class="param-list" id="param-list"></div>
                </div>

                <!-- Card 3: YouTube Consensus Insight -->
                <div class="card">
                    <div class="card-title">
                        <span>📺 Konsensus Analis YouTube FPL</span>
                    </div>
                    <div id="yt-insight-box"></div>
                </div>
            </section>

        </div>
    </main>

    <!-- Modal Squad Builder (Pilih 15 Pemain Custom) -->
    <div class="modal-backdrop" id="modal-squad-builder">
        <div class="modal-content" style="max-width:850px; max-height:88vh; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #334155; padding-bottom:0.75rem;">
                <div>
                    <h3 style="font-size:1.15rem; color:#00ff87;">Atur 15 Pemain Tim Anda</h3>
                    <p style="font-size:0.75rem; color:#94a3b8;">Komposisi Resmi FPL: 2 Kiper (GK), 5 Bek (DEF), 5 Gelandang (MID), dan 3 Penyerang (FWD).</p>
                </div>
                <button class="btn btn-secondary" style="width:auto; padding:0.25rem 0.5rem;" onclick="closeModal('modal-squad-builder')">✕</button>
            </div>
            
            <div style="overflow-y:auto; flex:1; padding-right:0.5rem;">
                <!-- Pencarian Cepat -->
                <div style="margin-bottom:1rem;">
                    <input type="text" id="builder-search" class="form-control" placeholder="🔍 Cari nama pemain atau klub (contoh: Haaland, Salah, Saka, Palmer, Arsenal)..." oninput="onBuilderSearch(this.value)">
                </div>

                <div id="builder-search-results" style="display:none; max-height:180px; overflow-y:auto; background:#0f172a; border:1px solid #334155; border-radius:8px; margin-bottom:1rem; padding:0.5rem;"></div>

                <!-- Daftar 15 Slot Pemain -->
                <div style="font-size:0.8rem; font-weight:700; color:#cbd5e1; margin-bottom:0.5rem;">15 Slot Skuad Anda:</div>
                <div id="builder-slots-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:0.5rem; margin-bottom:1rem;"></div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #334155; padding-top:1rem; margin-top:0.5rem;">
                <div style="font-size:0.8rem;" id="builder-total-cost">Total Biaya: £0.0m</div>
                <div style="display:flex; gap:0.5rem;">
                    <button class="btn btn-secondary" onclick="closeModal('modal-squad-builder')">Batal</button>
                    <button class="btn btn-primary" id="btn-save-builder" onclick="saveCustomSquad()">💾 Simpan 15 Pemain</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi 1-Click Apply -->
    <div class="modal-backdrop" id="modal-confirm">
        <div class="modal-content">
            <h3 style="font-size:1.15rem; margin-bottom:0.75rem; color:#00ff87;">Konfirmasi Penerapan ke FPL</h3>
            <p style="font-size:0.85rem; color:#cbd5e1; margin-bottom:1rem;">
                Apakah Anda yakin ingin mengeksekusi transfer pemain yang direkomendasikan serta memperbarui susunan Kapten (C) dan Wakil Kapten (VC) langsung ke akun resmi FPL Anda?
            </p>
            <div style="display:flex; gap:0.75rem;">
                <button class="btn btn-secondary" onclick="closeModal('modal-confirm')">Batal</button>
                <button class="btn btn-primary" id="btn-confirm-apply">Ya, Terapkan Sekarang</button>
            </div>
        </div>
    </div>

    <!-- Modal Input YouTube URL -->
    <div class="modal-backdrop" id="modal-yt">
        <div class="modal-content">
            <h3 style="font-size:1.15rem; margin-bottom:0.75rem;">Input Video YouTube Analis FPL</h3>
            <form id="form-yt">
                <div class="form-group">
                    <label for="yt-url">URL Video YouTube:</label>
                    <input type="url" class="form-control" id="yt-url" placeholder="https://www.youtube.com/watch?v=..." required>
                </div>
                <div class="form-group">
                    <label for="yt-buys">Rekomendasi Pemain Masuk (Pisahkan koma):</label>
                    <input type="text" class="form-control" id="yt-buys" placeholder="Contoh: Palmer, Saka, Isak">
                </div>
                <div class="form-group">
                    <label for="yt-sells">Rekomendasi Pemain Keluar (Pisahkan koma):</label>
                    <input type="text" class="form-control" id="yt-sells" placeholder="Contoh: Son, Watkins">
                </div>
                <div class="form-group">
                    <label for="yt-caps">Pilihan Kapten Utama/VC:</label>
                    <input type="text" class="form-control" id="yt-caps" placeholder="Contoh: Haaland, Salah">
                </div>
                <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-yt')">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan Analisis</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Riwayat Log Eksekusi -->
    <div class="modal-backdrop" id="modal-logs">
        <div class="modal-content" style="max-width:700px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="font-size:1.15rem;">Riwayat Eksekusi (Manual & Fallback)</h3>
                <button class="btn btn-secondary" style="width:auto; padding:0.25rem 0.5rem;" onclick="closeModal('modal-logs')">✕</button>
            </div>
            <div style="max-height:350px; overflow-y:auto;">
                <table class="table-logs">
                    <thead>
                        <tr>
                            <th>GW</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Transfer</th>
                            <th>Waktu Eksekusi</th>
                        </tr>
                    </thead>
                    <tbody id="log-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loading Spinner Overlay -->
    <div id="loading-overlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.85); display:none; align-items:center; justify-content:center; z-index:999; flex-direction:column; gap:1rem;">
        <div style="font-size:2rem; animation: pulse 1s infinite;">⚽</div>
        <div style="font-size:0.9rem; color:#00ff87; font-weight:600;">Memproses Data FPL Engine...</div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
