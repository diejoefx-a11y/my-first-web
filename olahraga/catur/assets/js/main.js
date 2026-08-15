/**
 * assets/js/main.js
 * Logika Dashboard Panitia & Manajemen Meja Catur
 */

document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
});

let currentFilter = 'all';
let searchQuery = '';
let autoRefreshTimer = null;

function initDashboard() {
    setupEventListeners();
    setupPresetButtons();
    loadDashboardData();
    
    // Auto-polling pembaruan status setiap 3 detik
    autoRefreshTimer = setInterval(() => {
        loadDashboardData(true);
    }, 3000);
}

function setupEventListeners() {
    // Tombol Tambah Meja
    const btnTambah = document.getElementById('btnTambahMeja');
    if (btnTambah) {
        btnTambah.addEventListener('click', () => openModalTambah());
    }

    // Filter Buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            e.currentTarget.classList.add('active');
            currentFilter = e.currentTarget.getAttribute('data-filter') || 'all';
            renderMejaList();
        });
    });

    // Search Input
    const searchInput = document.getElementById('searchMeja');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            renderMejaList();
        });
    }

    // Modal Close buttons
    document.querySelectorAll('.modal-close, .btn-cancel-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.currentTarget.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });

    // Form Tambah / Edit Meja Submit
    const formMeja = document.getElementById('formMeja');
    if (formMeja) {
        formMeja.addEventListener('submit', handleSaveMeja);
    }
}

let cachedMejaData = [];
let cachedStats = {};

async function loadDashboardData(isBackground = false) {
    try {
        const response = await fetch('api/get_meja.php');
        const res = await response.json();
        
        if (res.success) {
            cachedMejaData = res.data || [];
            cachedStats = res.stats || {};
            updateStatsUI(cachedStats);
            renderMejaList();
        }
    } catch (err) {
        if (!isBackground) {
            showToast('Gagal memuat data meja', 'danger');
        }
    }
}

function updateStatsUI(stats) {
    const elTotal = document.getElementById('statTotal');
    const elRunning = document.getElementById('statRunning');
    const elStandby = document.getElementById('statStandby');
    const elFinished = document.getElementById('statFinished');

    if (elTotal) elTotal.textContent = stats.total || 0;
    if (elRunning) elRunning.textContent = stats.running || 0;
    if (elStandby) elStandby.textContent = (stats.standby || 0) + (stats.paused || 0);
    if (elFinished) elFinished.textContent = stats.finished || 0;
}

function renderMejaList() {
    const grid = document.getElementById('mejaGrid');
    if (!grid) return;

    let filtered = cachedMejaData.filter(m => {
        // Status filter
        if (currentFilter === 'running' && m.status !== 'running') return false;
        if (currentFilter === 'standby' && m.status !== 'standby' && m.status !== 'paused') return false;
        if (currentFilter === 'finished' && m.status !== 'finished') return false;

        // Search filter
        if (searchQuery) {
            const matchNomor = (m.nomor_meja || '').toLowerCase().includes(searchQuery);
            const matchPutih = (m.nama_putih || '').toLowerCase().includes(searchQuery);
            const matchHitam = (m.nama_hitam || '').toLowerCase().includes(searchQuery);
            const matchBabak = (m.kategori_babak || '').toLowerCase().includes(searchQuery);
            return matchNomor || matchPutih || matchHitam || matchBabak;
        }
        return true;
    });

    if (filtered.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">♟️</div>
                <h3>Belum Ada Meja Pertandingan</h3>
                <p>Silakan klik tombol <strong>"Tambah Meja"</strong> di atas untuk membuat meja pertandingan catur baru.</p>
                <button class="btn btn-primary" onclick="openModalTambah()">
                    ➕ Tambah Meja Pertama
                </button>
            </div>
        `;
        return;
    }

    grid.innerHTML = filtered.map(m => createMejaCardHTML(m)).join('');
}

function createMejaCardHTML(m) {
    const statusMap = {
        'standby': { label: 'Standby', class: 'badge-standby', icon: '⚪' },
        'running': { label: 'Tanding', class: 'badge-running', icon: '🟢' },
        'paused': { label: 'Pause', class: 'badge-paused', icon: '🟡' },
        'finished': { label: 'Selesai', class: 'badge-finished', icon: '🏆' }
    };
    const st = statusMap[m.status] || statusMap['standby'];

    const waktuPutih = formatTime(m.sisa_waktu_putih_ms);
    const waktuHitam = formatTime(m.sisa_waktu_hitam_ms);

    const isWhiteTurn = m.status === 'running' && m.giliran === 'putih';
    const isBlackTurn = m.status === 'running' && m.giliran === 'hitam';

    let formatWaktuText = `${m.time_base_minutes} mnt`;
    if (m.time_increment_seconds > 0) {
        formatWaktuText += ` + ${m.time_increment_seconds}s`;
    }

    let winnerBanner = '';
    if (m.status === 'finished') {
        let winText = '';
        if (m.pemenang === 'putih') winText = `⚪ ${m.nama_putih} Menang!`;
        else if (m.pemenang === 'hitam') winText = `⚫ ${m.nama_hitam} Menang!`;
        else winText = '🤝 Remis (Draw)';
        winnerBanner = `<div style="background: #ecfdf5; color: #065f46; font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 8px; text-align: center; margin-top: 0.25rem;">${winText} (${m.keterangan_selesai || 'Selesai'})</div>`;
    }

    return `
        <div class="meja-card status-${m.status}">
            <div class="card-header">
                <div class="meja-badge-title">
                    <span class="meja-number">${escapeHTML(m.nomor_meja)}</span>
                    <span class="round-tag">${escapeHTML(m.kategori_babak || 'Babak 1')}</span>
                </div>
                <span class="status-badge ${st.class}">
                    ${st.icon} ${st.label}
                </span>
            </div>

            <div class="match-players">
                <!-- Pemain Hitam -->
                <div class="player-row ${isBlackTurn ? 'turn-active' : ''}">
                    <div class="player-info">
                        <span class="piece-tag black">♟</span>
                        <span class="player-name">${escapeHTML(m.nama_hitam)}</span>
                    </div>
                    <span class="player-clock">${waktuHitam}</span>
                </div>

                <!-- Pemain Putih -->
                <div class="player-row ${isWhiteTurn ? 'turn-active' : ''}">
                    <div class="player-info">
                        <span class="piece-tag white">♙</span>
                        <span class="player-name">${escapeHTML(m.nama_putih)}</span>
                    </div>
                    <span class="player-clock">${waktuPutih}</span>
                </div>

                ${winnerBanner}

                <div class="match-meta">
                    <span>⏱️ Format: <strong>${formatWaktuText}</strong></span>
                    <span>Langkah: <strong>${m.jumlah_langkah || 0}</strong></span>
                </div>
            </div>

            <div class="card-actions">
                <a href="timer.php?id=${m.id}" target="_blank" class="btn btn-primary btn-sm">
                    ⏱️ Buka Jam
                </a>
                <button class="btn btn-secondary btn-sm" onclick="showQRCodeModal(${m.id})" title="Tampilkan Barcode/QR HP">
                    📱 Barcode
                </button>
                <div style="display: flex; gap: 0.25rem;">
                    <button class="btn btn-secondary btn-sm" onclick="openModalEdit(${m.id})" title="Edit Meja">
                        ✏️
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="resetMeja(${m.id})" title="Reset Waktu">
                        🔄
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="hapusMeja(${m.id})" title="Hapus Meja">
                        🗑️
                    </button>
                </div>
            </div>
        </div>
    `;
}

function formatTime(ms) {
    if (ms === null || ms === undefined || ms < 0) ms = 0;
    const totalSec = Math.floor(ms / 1000);
    const hours = Math.floor(totalSec / 3600);
    const minutes = Math.floor((totalSec % 3600) / 60);
    const seconds = totalSec % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}

// Preset button handlers
function setupPresetButtons() {
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            e.currentTarget.classList.add('active');

            const base = e.currentTarget.getAttribute('data-base');
            const inc = e.currentTarget.getAttribute('data-inc');

            const inputBase = document.getElementById('inputTimeBase');
            const inputInc = document.getElementById('inputTimeInc');

            if (inputBase) inputBase.value = base;
            if (inputInc) inputInc.value = inc;
        });
    });
}

function openModalTambah() {
    const modal = document.getElementById('modalMeja');
    const title = document.getElementById('modalMejaTitle');
    const form = document.getElementById('formMeja');
    
    if (!modal || !form) return;

    form.reset();
    document.getElementById('actionType').value = 'tambah';
    document.getElementById('mejaId').value = '';
    
    // Auto increment nama meja
    const nextNum = cachedMejaData.length + 1;
    document.getElementById('inputNomorMeja').value = `Meja ${nextNum}`;
    document.getElementById('inputKategori').value = 'Babak 1';
    document.getElementById('inputNamaPutih').value = 'Pemain Putih';
    document.getElementById('inputNamaHitam').value = 'Pemain Hitam';
    document.getElementById('inputTimeBase').value = '5';
    document.getElementById('inputTimeInc').value = '0';

    // Highlight preset 5+0
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    const defPreset = document.querySelector('.preset-btn[data-base="5"][data-inc="0"]');
    if (defPreset) defPreset.classList.add('active');

    title.textContent = '➕ Tambah Meja Pertandingan Baru';
    modal.classList.add('active');
}

function openModalEdit(id) {
    const meja = cachedMejaData.find(m => m.id == id);
    if (!meja) return;

    const modal = document.getElementById('modalMeja');
    const title = document.getElementById('modalMejaTitle');
    
    if (!modal) return;

    document.getElementById('actionType').value = 'edit';
    document.getElementById('mejaId').value = meja.id;
    document.getElementById('inputNomorMeja').value = meja.nomor_meja;
    document.getElementById('inputKategori').value = meja.kategori_babak || '';
    document.getElementById('inputNamaPutih').value = meja.nama_putih;
    document.getElementById('inputNamaHitam').value = meja.nama_hitam;
    document.getElementById('inputTimeBase').value = meja.time_base_minutes;
    document.getElementById('inputTimeInc').value = meja.time_increment_seconds;

    title.textContent = `✏️ Edit ${meja.nomor_meja}`;
    modal.classList.add('active');
}

async function handleSaveMeja(e) {
    e.preventDefault();
    const action = document.getElementById('actionType').value;
    const id = document.getElementById('mejaId').value;
    const nomor_meja = document.getElementById('inputNomorMeja').value.trim();
    const kategori_babak = document.getElementById('inputKategori').value.trim();
    const nama_putih = document.getElementById('inputNamaPutih').value.trim();
    const nama_hitam = document.getElementById('inputNamaHitam').value.trim();
    const time_base_minutes = parseInt(document.getElementById('inputTimeBase').value, 10) || 5;
    const time_increment_seconds = parseInt(document.getElementById('inputTimeInc').value, 10) || 0;

    const payload = {
        action,
        id,
        nomor_meja,
        kategori_babak,
        nama_putih,
        nama_hitam,
        time_base_minutes,
        time_increment_seconds,
        time_mode: 'fischer'
    };

    try {
        const res = await fetch('api/action_meja.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('modalMeja').classList.remove('active');
            loadDashboardData();
        } else {
            showToast(data.message || 'Gagal menyimpan', 'danger');
        }
    } catch (err) {
        showToast('Terjadi kesalahan jaringan', 'danger');
    }
}

async function resetMeja(id) {
    if (!confirm('Yakin ingin mereset waktu jam catur meja ini ke awal?')) return;
    try {
        const res = await fetch('api/action_meja.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset', id })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadDashboardData();
        }
    } catch (err) {
        showToast('Gagal mereset meja', 'danger');
    }
}

async function hapusMeja(id) {
    if (!confirm('Yakin ingin menghapus meja pertandingan ini?')) return;
    try {
        const res = await fetch('api/action_meja.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'hapus', id })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadDashboardData();
        }
    } catch (err) {
        showToast('Gagal menghapus meja', 'danger');
    }
}

// QR Code Modal Handler
function showQRCodeModal(id) {
    const meja = cachedMejaData.find(m => m.id == id);
    if (!meja) return;

    const modal = document.getElementById('modalQR');
    const title = document.getElementById('qrModalTitle');
    const container = document.getElementById('qrCodeContainer');
    const linkText = document.getElementById('qrUrlText');
    const openBtn = document.getElementById('qrOpenLinkBtn');

    if (!modal) return;

    title.textContent = `📱 Barcode HP: ${meja.nomor_meja}`;
    container.innerHTML = '';
    linkText.textContent = meja.qr_url;
    openBtn.href = meja.local_url || meja.qr_url;

    // Render QR Code
    new QRCode(container, {
        text: meja.qr_url,
        width: 220,
        height: 220,
        colorDark: "#0f172a",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
    });

    modal.classList.add('active');
}

function copyQRUrl() {
    const text = document.getElementById('qrUrlText').textContent;
    navigator.clipboard.writeText(text).then(() => {
        showToast('Link berhasil disalin ke clipboard!', 'success');
    });
}

function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
