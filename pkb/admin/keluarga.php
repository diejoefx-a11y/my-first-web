<?php
$pageTitle = "Manajemen Data Keluarga";
require_once __DIR__ . '/header.php';

$db = get_db();

// Filter & Search Parameters
$search = clean($_GET['q'] ?? '');
$filterRt = clean($_GET['rt'] ?? '');
$filterKelompok = !empty($_GET['kelompok_id']) ? intval($_GET['kelompok_id']) : 0;
$filterStatus = clean($_GET['status'] ?? '');

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(f.nama_kepala LIKE ? OR f.no_kk LIKE ? OR f.nik_kepala LIKE ? OR f.alamat_lengkap LIKE ? OR f.no_hp LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterRt)) {
    $conditions[] = "f.rt = ?";
    $params[] = $filterRt;
}

if ($filterKelompok > 0) {
    $conditions[] = "f.kelompok_id = ?";
    $params[] = $filterKelompok;
}

if (!empty($filterStatus)) {
    $conditions[] = "f.status_verifikasi = ?";
    $params[] = $filterStatus;
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Fetch distinct RT for filter
$stmtRT = $db->query("SELECT DISTINCT rt FROM families WHERE rt != '' ORDER BY rt ASC");
$listRT = $stmtRT->fetchAll(PDO::FETCH_COLUMN);

// Fetch all Groups for filter
$groupsList = $db->query("SELECT id, nomor_kelompok, nama_kelompok FROM `groups` ORDER BY nomor_kelompok ASC")->fetchAll();

// Global Stats (for quick metric badges)
$globalKK = $db->query("SELECT COUNT(*) FROM families")->fetchColumn();
$globalJiwa = $db->query("SELECT COUNT(*) FROM family_members")->fetchColumn();
$globalPending = $db->query("SELECT COUNT(*) FROM families WHERE status_verifikasi = 'pending'")->fetchColumn();
$globalVerified = $db->query("SELECT COUNT(*) FROM families WHERE status_verifikasi = 'terverifikasi'")->fetchColumn();

// Count Total Filtered Rows
$countSql = "SELECT COUNT(*) FROM families f $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();

// Pagination
$limit = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$totalPages = ceil($totalRows / $limit);

// Fetch Families Data
$sql = "
    SELECT 
        f.*,
        g.nama_kelompok,
        g.nomor_kelompok,
        (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as total_anggota
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    $whereClause
    ORDER BY f.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$families = $stmt->fetchAll();
?>

<style>
/* Custom Dynamic Styles for admin/keluarga.php */
.page-hero-keluarga {
    background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #7c3aed 100%);
    border-radius: 20px;
    padding: 1.75rem 2rem;
    color: #ffffff;
    margin-bottom: 1.75rem;
    box-shadow: 0 10px 25px -4px rgba(109, 40, 217, 0.35);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.page-hero-keluarga::after {
    content: '';
    position: absolute;
    right: -40px;
    top: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(233, 213, 255, 0.2) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.hero-stats-row {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.hero-stat-pill {
    background: rgba(255, 255, 255, 0.16);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    padding: 0.5rem 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    color: #f3e8ff;
}

.hero-stat-pill strong {
    color: #ffffff;
    font-size: 1.05rem;
    font-weight: 800;
}

/* Dynamic Filter Panel */
.filter-card-admin {
    background: #ffffff;
    border-radius: 18px;
    border: 1.5px solid var(--adm-border);
    box-shadow: var(--adm-shadow-md);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.75rem;
}

.filter-input-wrap {
    display: grid;
    grid-template-columns: 1.8fr 1.2fr 0.9fr 1fr auto;
    gap: 0.75rem;
    align-items: center;
}

.form-control-custom {
    background: #f8f7fc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.65rem 0.9rem;
    font-size: 0.88rem;
    color: #1e1b4b;
    width: 100%;
    outline: none;
    transition: all 0.25s;
    font-family: inherit;
}

.form-control-custom:focus {
    border-color: #7c3aed;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
}

/* Mobile Touch-Swipeable Table Container */
.keluarga-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1.5px solid var(--adm-border);
    box-shadow: var(--adm-shadow-md);
    overflow: hidden;
    margin-bottom: 2rem;
}

.keluarga-table-header {
    padding: 1.25rem 1.75rem;
    background: linear-gradient(180deg, #ffffff 0%, #faf8ff 100%);
    border-bottom: 1.5px solid var(--adm-primary-lightest);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.table-swipe-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    position: relative;
}

.table-swipe-wrapper::-webkit-scrollbar {
    height: 7px;
}
.table-swipe-wrapper::-webkit-scrollbar-track {
    background: #f1f0f9;
    border-radius: 10px;
}
.table-swipe-wrapper::-webkit-scrollbar-thumb {
    background: #c4b5fd;
    border-radius: 10px;
}
.table-swipe-wrapper::-webkit-scrollbar-thumb:hover {
    background: #7c3aed;
}

.table-dynamic-keluarga {
    width: 100%;
    min-width: 960px; /* Memastikan kolom tidak terpotong & leluasa digeser di smartphone */
    border-collapse: collapse;
    text-align: left;
    font-size: 0.88rem;
}

.table-dynamic-keluarga thead th {
    background: #f5f3ff;
    padding: 1rem 1.15rem;
    font-size: 0.75rem;
    font-weight: 800;
    color: #6d28d9;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1.5px solid #ddd6fe;
    white-space: nowrap;
}

.table-dynamic-keluarga tbody td {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid #f1f0f9;
    vertical-align: middle;
    color: #334155;
}

.table-dynamic-keluarga tbody tr {
    transition: background-color 0.2s ease;
}

.table-dynamic-keluarga tbody tr:hover {
    background-color: #faf8ff;
}

/* User Avatar Thumbnail / Initials */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
    color: #6d28d9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
    flex-shrink: 0;
    border: 1.5px solid #c4b5fd;
}

.mobile-swipe-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #6d28d9;
    background: #ede9fe;
    border: 1px solid #ddd6fe;
    padding: 4px 10px;
    border-radius: 9999px;
}

/* Status Badges */
.badge-status-dot {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.35rem 0.8rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: capitalize;
}
.badge-dot-terverifikasi {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.badge-dot-pending {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.badge-dot-ditolak {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.dot-indicator {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.dot-terverifikasi { background: #10b981; box-shadow: 0 0 6px #10b981; }
.dot-pending { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }
.dot-ditolak { background: #ef4444; box-shadow: 0 0 6px #ef4444; }

/* Action Button Group */
.action-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.action-btn-item {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid transparent;
}
.btn-view-act {
    background: #ede9fe;
    color: #6d28d9;
    border-color: #ddd6fe;
}
.btn-view-act:hover {
    background: #7c3aed;
    color: #ffffff;
}
.btn-edit-act {
    background: #e0f2fe;
    color: #0284c7;
    border-color: #bae6fd;
}
.btn-edit-act:hover {
    background: #0284c7;
    color: #ffffff;
}
.btn-del-act {
    background: #fee2e2;
    color: #ef4444;
    border-color: #fecaca;
}
.btn-del-act:hover {
    background: #ef4444;
    color: #ffffff;
}

/* Responsiveness */
@media (max-width: 992px) {
    .filter-input-wrap {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 600px) {
    .page-hero-keluarga {
        padding: 1.25rem;
    }
    .filter-input-wrap {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- 1. HERO HEADER KELUARGA -->
<div class="page-hero-keluarga">
    <div>
        <div style="font-size: 0.75rem; font-weight: 800; color: #ddd6fe; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.35rem;">
            Direktori Administrasi Jemaat
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.7rem; font-weight: 800; margin: 0 0 0.4rem 0;">
            👨‍👩‍👧‍👦 Data Kartu Keluarga (KK)
        </h1>
        <p style="color: #e9d5ff; font-size: 0.9rem; margin: 0; max-width: 540px;">
            Manajemen verifikasi profil kepala keluarga, rincian tanggungan jiwa jemaat, dan sinkronisasi titik peta domisili.
        </p>
    </div>

    <div class="hero-stats-row">
        <div class="hero-stat-pill">
            <span>📋 Total:</span>
            <strong><?= number_format($globalKK) ?> KK</strong>
        </div>
        <div class="hero-stat-pill">
            <span>👥 Jiwa:</span>
            <strong><?= number_format($globalJiwa) ?> Jiwa</strong>
        </div>
        <div class="hero-stat-pill" style="background: rgba(16,185,129,0.25); border-color: rgba(16,185,129,0.4); color: #a7f3d0;">
            <span>✅ Terverifikasi:</span>
            <strong><?= number_format($globalVerified) ?></strong>
        </div>
    </div>
</div>

<!-- 2. ADVANCED FILTER & SEARCH PANEL -->
<div class="filter-card-admin">
    <form action="" method="GET">
        <div class="filter-input-wrap">
            
            <!-- Cari Teks -->
            <div style="position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                <input type="text" 
                       name="q" 
                       class="form-control-custom" 
                       placeholder="Cari No. KK, NIK, Nama, Alamat, WA..." 
                       value="<?= htmlspecialchars($search) ?>" 
                       style="padding-left: 2.35rem;"
                >
            </div>

            <!-- Filter Kelompok -->
            <div>
                <select name="kelompok_id" class="form-control-custom">
                    <option value="">🏷️ Semua Kelompok (1-14)</option>
                    <?php foreach ($groupsList as $grp): ?>
                        <option value="<?= $grp['id'] ?>" <?= $filterKelompok == $grp['id'] ? 'selected' : '' ?>>
                            Kelompok <?= $grp['nomor_kelompok'] ?> - <?= htmlspecialchars($grp['nama_kelompok']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter RT -->
            <div>
                <select name="rt" class="form-control-custom">
                    <option value="">📍 Semua RT</option>
                    <?php foreach ($listRT as $rtVal): ?>
                        <option value="<?= htmlspecialchars($rtVal) ?>" <?= $filterRt === $rtVal ? 'selected' : '' ?>>
                            RT <?= htmlspecialchars($rtVal) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Status -->
            <div>
                <select name="status" class="form-control-custom">
                    <option value="">⚡ Semua Status</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>⏳ Menunggu (Pending)</option>
                    <option value="terverifikasi" <?= $filterStatus === 'terverifikasi' ? 'selected' : '' ?>>✅ Terverifikasi</option>
                    <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>❌ Ditolak</option>
                </select>
            </div>

            <!-- Tombol Aksi Filter -->
            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.65rem 1.15rem; border-radius: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <?php if ($search || $filterRt || $filterKelompok || $filterStatus): ?>
                    <a href="keluarga.php" class="btn btn-outline btn-sm" style="padding: 0.65rem 0.9rem; border-radius: 12px; font-weight: 700;" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
                <a href="export_excel.php" class="btn btn-accent btn-sm" style="padding: 0.65rem 0.95rem; border-radius: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;" title="Unduh Excel">
                    <i class="fa-solid fa-file-excel"></i>
                </a>
            </div>

        </div>
    </form>
</div>

<!-- 3. TABEL DAFTAR KARTU KELUARGA TERDAFTAR (MOBILE HORIZONTAL SWIPE) -->
<div class="keluarga-table-card">
    
    <!-- Table Header Info Bar -->
    <div class="keluarga-table-header">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #1e1b4b; margin: 0 0 2px 0;">
                Daftar Kartu Keluarga Terdaftar
            </h3>
            <div style="font-size: 0.82rem; color: #64748b;">
                Menampilkan <strong><?= count($families) ?> data</strong> (Halaman <?= $page ?> dari <?= max(1, $totalPages) ?>) • Total ditemukan: <strong><?= number_format($totalRows) ?> KK</strong>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span class="mobile-swipe-badge">
                <i class="fa-solid fa-arrows-left-right"></i> Geser tabel ke samping pada layar HP
            </span>
            <a href="../jemaat/pasangtitik.php" target="_blank" class="btn btn-primary btn-sm" style="padding: 0.5rem 1rem; border-radius: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-plus"></i> Tambah KK Baru
            </a>
        </div>
    </div>

    <!-- Responsive Swipe Wrapper for Mobile Devices -->
    <div class="table-swipe-wrapper">
        <table class="table-dynamic-keluarga">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">No</th>
                    <th>Kepala Keluarga</th>
                    <th>Nomor Kartu Keluarga (KK)</th>
                    <th>Kelompok Pelayanan</th>
                    <th>Kontak WhatsApp</th>
                    <th>Domisili / RT-RW</th>
                    <th style="text-align: center;">Tanggungan</th>
                    <th>Titik Koordinat</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 110px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($families)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 3rem 1.5rem; color: #94a3b8;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
                            <div style="font-weight: 700; color: #475569; font-size: 1.05rem;">Tidak Ada Data Keluarga Ditemukan</div>
                            <small>Coba sesuaikan kata kunci pencarian atau reset filter untuk melihat semua data.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($families as $idx => $f): 
                        $waClean = preg_replace('/[^0-9]/', '', $f['no_hp'] ?? '');
                        if (substr($waClean, 0, 1) === '0') $waClean = '62' . substr($waClean, 1);
                        $hasCoords = !empty($f['latitude']) && !empty($f['longitude']) && $f['latitude'] != '0';
                        $initial = mb_substr(trim($f['nama_kepala']), 0, 1);
                    ?>
                        <tr>
                            <!-- No -->
                            <td style="text-align: center; font-weight: 700; color: #94a3b8;">
                                <?= $offset + $idx + 1 ?>
                            </td>

                            <!-- Kepala Keluarga Profil -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="avatar-circle">
                                        <?= strtoupper($initial) ?>
                                    </div>
                                    <div>
                                        <a href="detail.php?id=<?= $f['id'] ?>" style="font-weight: 800; color: #1e1b4b; text-decoration: none; font-size: 0.92rem; display: block;">
                                            <?= htmlspecialchars($f['nama_kepala']) ?>
                                        </a>
                                        <small style="color: #64748b; font-family: monospace; font-size: 0.78rem;">
                                            NIK: <?= htmlspecialchars($f['nik_kepala']) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>

                            <!-- Nomor KK -->
                            <td>
                                <span style="font-family: monospace; font-weight: 800; color: #6d28d9; background: #f5f3ff; padding: 4px 8px; border-radius: 8px; border: 1px solid #ddd6fe; font-size: 0.85rem;">
                                    <?= htmlspecialchars($f['no_kk']) ?>
                                </span>
                            </td>

                            <!-- Kelompok -->
                            <td>
                                <?php if (!empty($f['nama_kelompok'])): ?>
                                    <span style="background: #ede9fe; color: #6d28d9; padding: 0.3rem 0.7rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 800; border: 1px solid #ddd6fe; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-users" style="font-size: 0.7rem;"></i> Klp <?= $f['nomor_kelompok'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.8rem; font-style: italic;">Belum Ditentukan</span>
                                <?php endif; ?>
                            </td>

                            <!-- WhatsApp -->
                            <td>
                                <?php if (!empty($f['no_hp'])): ?>
                                    <a href="https://wa.me/<?= $waClean ?>?text=Syalom%20Bpk/Ibu%20<?= urlencode($f['nama_kepala']) ?>" 
                                       target="_blank" 
                                       style="color: #059669; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; background: #ecfdf5; padding: 3px 8px; border-radius: 6px; border: 1px solid #a7f3d0;"
                                    >
                                        <i class="fa-brands fa-whatsapp" style="color: #25D366; font-size: 0.95rem;"></i> <?= htmlspecialchars($f['no_hp']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Alamat RT / RW -->
                            <td>
                                <div style="font-weight: 700; color: #1e1b4b; font-size: 0.84rem;">
                                    RT <?= htmlspecialchars($f['rt'] ?: '-') ?> / RW <?= htmlspecialchars($f['rw'] ?: '-') ?>
                                </div>
                                <small style="color: #64748b; display: block; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($f['alamat_lengkap']) ?>">
                                    <?= htmlspecialchars($f['alamat_lengkap'] ?: 'Alamat belum diisi') ?>
                                </small>
                            </td>

                            <!-- Tanggungan Jiwa -->
                            <td style="text-align: center;">
                                <span style="background: #e0f2fe; color: #0284c7; font-weight: 800; padding: 3px 10px; border-radius: 9999px; font-size: 0.8rem; border: 1px solid #bae6fd;">
                                    <?= $f['total_anggota'] ?> Jiwa
                                </span>
                            </td>

                            <!-- Titik Koordinat & Google Maps -->
                            <td>
                                <?php if ($hasCoords): ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $f['latitude'] ?>,<?= $f['longitude'] ?>" 
                                       target="_blank" 
                                       style="display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; text-decoration: none; font-weight: 700;"
                                       title="Buka Navigasi Rute di Google Maps"
                                    >
                                        <i class="fa-solid fa-location-dot" style="color: #ef4444;"></i> <?= number_format($f['latitude'], 4) ?>, <?= number_format($f['longitude'], 4) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem;">Belum ada titik</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status Verifikasi -->
                            <td>
                                <?php 
                                    $st = $f['status_verifikasi'];
                                    $badgeClass = ($st === 'terverifikasi') ? 'badge-dot-terverifikasi' : (($st === 'ditolak') ? 'badge-dot-ditolak' : 'badge-dot-pending');
                                    $dotClass = ($st === 'terverifikasi') ? 'dot-terverifikasi' : (($st === 'ditolak') ? 'dot-ditolak' : 'dot-pending');
                                ?>
                                <span class="badge-status-dot <?= $badgeClass ?>">
                                    <span class="dot-indicator <?= $dotClass ?>"></span>
                                    <?= ucfirst($st) ?>
                                </span>
                            </td>

                            <!-- Tombol Aksi -->
                            <td style="text-align: center; white-space: nowrap;">
                                <div class="action-btn-group">
                                    <a href="detail.php?id=<?= $f['id'] ?>" class="action-btn-item btn-view-act" title="Lihat Detail Lengkap">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $f['id'] ?>" class="action-btn-item btn-edit-act" title="Edit Data KK">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="hapus.php?id=<?= $f['id'] ?>" 
                                       class="action-btn-item btn-del-act" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data KK <?= htmlspecialchars(addslashes($f['nama_kepala'])) ?>?\nSemua data anggota keluarga juga akan terhapus secara permanen.');" 
                                       title="Hapus Data"
                                    >
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Modern Bar -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1.25rem 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-top: 1.5px solid #f1f0f9; background: #faf8ff;">
            <div style="font-size: 0.82rem; color: #64748b;">
                Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong>
            </div>
            <div style="display: flex; gap: 5px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&rt=<?= urlencode($filterRt) ?>&kelompok_id=<?= $filterKelompok ?>&status=<?= urlencode($filterStatus) ?>" class="btn btn-outline btn-sm" style="border-radius: 8px;">
                        &laquo; Prev
                    </a>
                <?php endif; ?>

                <?php 
                $startP = max(1, $page - 2);
                $endP = min($totalPages, $page + 2);
                for ($i = $startP; $i <= $endP; $i++): 
                ?>
                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&rt=<?= urlencode($filterRt) ?>&kelompok_id=<?= $filterKelompok ?>&status=<?= urlencode($filterStatus) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="border-radius: 8px; font-weight: <?= $i === $page ? '800' : '600' ?>;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&rt=<?= urlencode($filterRt) ?>&kelompok_id=<?= $filterKelompok ?>&status=<?= urlencode($filterStatus) ?>" class="btn btn-outline btn-sm" style="border-radius: 8px;">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
