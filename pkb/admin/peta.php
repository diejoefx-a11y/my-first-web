<?php
$pageTitle = "Peta Sebaran Titik Rumah Keluarga (Master Map)";
require_once __DIR__ . '/header.php';

$db = get_db();

// Fetch distinct RT and RW for filter dropdowns
$stmtRT = $db->query("SELECT DISTINCT rt FROM families WHERE rt != '' ORDER BY rt ASC");
$listRT = $stmtRT->fetchAll(PDO::FETCH_COLUMN);

$stmtRW = $db->query("SELECT DISTINCT rw FROM families WHERE rw != '' ORDER BY rw ASC");
$listRW = $stmtRW->fetchAll(PDO::FETCH_COLUMN);

// Fetch all Groups for filter
$groupsList = $db->query("SELECT id, nomor_kelompok, nama_kelompok FROM `groups` ORDER BY nomor_kelompok ASC")->fetchAll();

// Fetch all families with their group and member counts
$sql = "
    SELECT 
        f.*,
        g.nama_kelompok,
        g.nomor_kelompok,
        g.nama_ketua,
        (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as total_anggota
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    ORDER BY f.nama_kepala ASC
";
$stmt = $db->query($sql);
$families = $stmt->fetchAll();
?>

<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--adm-secondary);">Filter Peta Persebaran</h3>
            <small style="color: var(--adm-text-muted);">Saring tampilan titik lokasi berdasarkan kriteria berikut</small>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
            <div>
                <select id="filter-kelompok" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.85rem; min-width: 150px;">
                    <option value="">Semua Kelompok (1-14)</option>
                    <?php foreach ($groupsList as $grp): ?>
                        <option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['nama_kelompok']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select id="filter-rt" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">
                    <option value="">Semua RT</option>
                    <?php foreach ($listRT as $rtVal): ?>
                        <option value="<?= htmlspecialchars($rtVal) ?>">RT <?= htmlspecialchars($rtVal) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select id="filter-rw" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">
                    <option value="">Semua RW</option>
                    <?php foreach ($listRW as $rwVal): ?>
                        <option value="<?= htmlspecialchars($rwVal) ?>">RW <?= htmlspecialchars($rwVal) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select id="filter-status" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">
                    <option value="">Semua Status</option>
                    <option value="terverifikasi">✅ Terverifikasi (Hijau Emerald)</option>
                    <option value="pending">🔴 Belum Terverifikasi (Merah Metalik)</option>
                    <option value="ditolak">❌ Ditolak (Abu-abu)</option>
                </select>
            </div>

            <div>
                <input type="text" id="filter-search" class="form-control" placeholder="Cari Nama / KK..." style="padding: 0.45rem 0.75rem; font-size: 0.85rem; min-width: 170px;">
            </div>

            <button type="button" id="btn-reset-filter" class="btn btn-outline btn-sm">Reset</button>
        </div>
    </div>
</div>

<!-- Keterangan Legend Pembeda Warna Peta -->
<div style="background: #ffffff; border-radius: 14px; border: 1.5px solid var(--adm-border); padding: 0.85rem 1.35rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; box-shadow: var(--adm-shadow-sm);">
    <div style="font-size: 0.85rem; font-weight: 800; color: var(--adm-secondary); display: flex; align-items: center; gap: 6px;">
        <i class="fa-solid fa-map-location-dot" style="color: #7c3aed;"></i>
        <span>Pembeda Warna Status di Peta:</span>
    </div>
    <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap; font-size: 0.82rem; font-weight: 700;">
        <span style="display: inline-flex; align-items: center; gap: 7px; color: #065f46;">
            <span style="width: 15px; height: 15px; border-radius: 50%; background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%); border: 2px solid #fff; box-shadow: 0 0 8px rgba(16,185,129,0.85); display: inline-block;"></span>
            Terverifikasi (Hijau Emerald)
        </span>
        <span style="display: inline-flex; align-items: center; gap: 7px; color: #991b1b;">
            <span style="width: 15px; height: 15px; border-radius: 50%; background: linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #991b1b 100%); border: 2px solid #fff; box-shadow: 0 0 10px rgba(220,38,38,0.95), inset 0 1px 3px rgba(255,255,255,0.7); display: inline-block;"></span>
            Belum Terverifikasi (Merah Metalik)
        </span>
        <span style="display: inline-flex; align-items: center; gap: 7px; color: #475569;">
            <span style="width: 15px; height: 15px; border-radius: 50%; background: #64748b; border: 2px solid #fff; display: inline-block;"></span>
            Ditolak (Abu-abu)
        </span>
    </div>
</div>

<!-- Master Map Layout -->
<div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Large Map Container -->
    <div class="master-map-wrapper">
        <div id="admin-master-map" style="height: 620px;"></div>
    </div>

    <!-- Sidebar List of Selected Families -->
    <div class="card" style="padding: 1.25rem; height: 620px; display: flex; flex-direction: column; margin-bottom: 0;">
        <div style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center;">
            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--adm-secondary);">Daftar Lokasi (<span id="total-visible"><?= count($families) ?></span>)</h4>
        </div>

        <div id="family-list-panel" style="overflow-y: auto; flex-grow: 1; display: flex; flex-direction: column; gap: 0.5rem; padding-right: 0.25rem;">
            <!-- Items injected via JavaScript -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rawData = <?= json_encode($families) ?>;
    const uploadBaseUrl = "<?= base_url('uploads/') ?>";
    const detailBaseUrl = "detail.php?id=";

    const filterKelompok = document.getElementById('filter-kelompok');
    const filterRt = document.getElementById('filter-rt');
    const filterRw = document.getElementById('filter-rw');
    const filterStatus = document.getElementById('filter-status');
    const filterSearch = document.getElementById('filter-search');
    const btnReset = document.getElementById('btn-reset-filter');
    const listPanel = document.getElementById('family-list-panel');
    const countSpan = document.getElementById('total-visible');

    // Default center
    let initialLat = -5.147665;
    let initialLng = 119.432731;

    if (rawData.length > 0) {
        initialLat = parseFloat(rawData[0].latitude);
        initialLng = parseFloat(rawData[0].longitude);
    }

    const map = L.map('admin-master-map').setView([initialLat, initialLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    let markersLayer = L.layerGroup().addTo(map);
    let markersMap = {};

    function renderMap(filteredData) {
        markersLayer.clearLayers();
        listPanel.innerHTML = '';
        countSpan.textContent = filteredData.length;

        const bounds = [];

        filteredData.forEach(function(item) {
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);

            if (isNaN(lat) || isNaN(lng)) return;

            bounds.push([lat, lng]);

            // Marker differentiation: Terverifikasi (Hijau Emerald) vs Belum Terverifikasi (Merah Metalik)
            const isVerified = (item.status_verifikasi === 'terverifikasi');
            const isPending = (item.status_verifikasi === 'pending' || !item.status_verifikasi);
            const isRejected = (item.status_verifikasi === 'ditolak');

            let markerGrad = 'linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #991b1b 100%)'; // Merah Metalik (Belum Terverifikasi)
            let markerBorderGlow = 'rgba(220, 38, 38, 0.95)';
            let statusText = 'Belum Terverifikasi';
            let statusBadge = '<span style="background: linear-gradient(135deg, #ef4444, #991b1b); color: #ffffff; padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid #fca5a5; display: inline-block;">⏳ BELUM TERVERIFIKASI</span>';
            let headerBg = '#dc2626';

            if (isVerified) {
                markerGrad = 'linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%)'; // Hijau Emerald
                markerBorderGlow = 'rgba(16, 185, 129, 0.85)';
                statusText = 'Terverifikasi';
                statusBadge = '<span style="background: linear-gradient(135deg, #10b981, #047857); color: #ffffff; padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid #a7f3d0; display: inline-block;">✅ TERVERIFIKASI</span>';
                headerBg = '#059669';
            } else if (isRejected) {
                markerGrad = '#64748b';
                markerBorderGlow = 'rgba(100, 116, 139, 0.6)';
                statusText = 'Ditolak';
                statusBadge = '<span style="background: #475569; color: #ffffff; padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; display: inline-block;">❌ DITOLAK</span>';
                headerBg = '#475569';
            }

            const customIcon = L.divIcon({
                html: `<div style="background: ${markerGrad}; border: 2.5px solid #ffffff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 11px; font-weight: 800; box-shadow: 0 0 10px ${markerBorderGlow}, inset 0 1px 3px rgba(255,255,255,0.7); cursor: pointer;">${item.nomor_kelompok || '●'}</div>`,
                className: 'custom-pin-marker-metal',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14]
            });

            const marker = L.marker([lat, lng], { icon: customIcon }).addTo(markersLayer);
            markersMap[item.id] = marker;

            // Formatted Phone for WA
            let waPhone = (item.no_hp || '').replace(/^0/, '62').replace(/[^0-9]/g, '');
            let namaKelompok = item.nama_kelompok ? item.nama_kelompok : 'Belum Ditentukan';

            const popupContent = `
                <div class="map-popup-card">
                    <div class="map-popup-header" style="background: ${headerBg};">
                        ${item.nama_kepala}
                    </div>
                    <div class="map-popup-body">
                        ${item.foto_rumah ? `<img src="${uploadBaseUrl}${item.foto_rumah}" style="width:100%; height:120px; object-fit:cover; border-radius:6px; margin-bottom:8px;">` : ''}
                        <div><strong>Kelompok:</strong> <span style="color:#7c3aed; font-weight:700;">${namaKelompok}</span></div>
                        <div><strong>No. KK:</strong> ${item.no_kk}</div>
                        <div><strong>RT/RW:</strong> RT ${item.rt} / RW ${item.rw}</div>
                        <div><strong>Alamat:</strong> ${item.alamat_lengkap}</div>
                        <div><strong>Anggota:</strong> ${item.total_anggota} Jiwa (Tanggungan: ${item.jumlah_tanggungan})</div>
                        <div style="margin-top:6px;">${statusBadge}</div>
                    </div>
                    <div class="map-popup-actions">
                        <a href="${detailBaseUrl}${item.id}" class="btn btn-primary btn-sm" style="flex:1; font-size:0.75rem; padding:0.35rem 0.5rem;">Lihat Detail</a>
                        <a href="https://www.google.com/maps/search/?api=1&query=${lat},${lng}" target="_blank" class="btn btn-outline btn-sm" style="padding:0.35rem 0.5rem; font-size:0.75rem;" title="Rute Google Maps">🗺️</a>
                        ${waPhone ? `<a href="https://wa.me/${waPhone}" target="_blank" class="btn btn-accent btn-sm" style="padding:0.35rem 0.5rem; font-size:0.75rem;" title="Chat WhatsApp">💬</a>` : ''}
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);

            // Populate Sidebar List
            const listItem = document.createElement('div');
            listItem.style.cssText = 'padding: 0.6rem 0.75rem; background: #f8fafc; border: 1px solid var(--adm-border); border-radius: 8px; cursor: pointer; transition: all 0.2s;';
            listItem.innerHTML = `
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--adm-secondary); display: flex; justify-content: space-between;">
                    <span>${item.nama_kepala}</span>
                    <span style="font-size:0.7rem; font-weight:800; color:${headerBg};">● ${statusText}</span>
                </div>
                <div style="font-size: 0.75rem; color: #7c3aed; font-weight: 600; margin-top: 2px;">
                    ${namaKelompok}
                </div>
                <div style="font-size: 0.75rem; color: var(--adm-text-muted);">
                    RT ${item.rt}/RW ${item.rw} • KK: ${item.no_kk}
                </div>
            `;

            listItem.addEventListener('mouseenter', () => listItem.style.backgroundColor = '#ede9fe');
            listItem.addEventListener('mouseleave', () => listItem.style.backgroundColor = '#f8fafc');
            listItem.addEventListener('click', () => {
                map.setView([lat, lng], 17);
                marker.openPopup();
            });

            listPanel.appendChild(listItem);
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40] });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 16);
        }
    }

    function applyFilters() {
        const kelId = filterKelompok.value;
        const rt = filterRt.value.toLowerCase();
        const rw = filterRw.value.toLowerCase();
        const status = filterStatus.value.toLowerCase();
        const search = filterSearch.value.trim().toLowerCase();

        const filtered = rawData.filter(function(item) {
            if (kelId && String(item.kelompok_id) !== String(kelId)) return false;
            if (rt && (item.rt || '').toLowerCase() !== rt) return false;
            if (rw && (item.rw || '').toLowerCase() !== rw) return false;
            if (status && (item.status_verifikasi || '').toLowerCase() !== status) return false;
            if (search) {
                const matchName = (item.nama_kepala || '').toLowerCase().includes(search);
                const matchKk = (item.no_kk || '').toLowerCase().includes(search);
                const matchAlamat = (item.alamat_lengkap || '').toLowerCase().includes(search);
                if (!matchName && !matchKk && !matchAlamat) return false;
            }
            return true;
        });

        renderMap(filtered);
    }

    filterKelompok.addEventListener('change', applyFilters);
    filterRt.addEventListener('change', applyFilters);
    filterRw.addEventListener('change', applyFilters);
    filterStatus.addEventListener('change', applyFilters);
    filterSearch.addEventListener('input', applyFilters);

    btnReset.addEventListener('click', function() {
        filterKelompok.value = '';
        filterRt.value = '';
        filterRw.value = '';
        filterStatus.value = '';
        filterSearch.value = '';
        renderMap(rawData);
    });

    // Initial render
    renderMap(rawData);
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
