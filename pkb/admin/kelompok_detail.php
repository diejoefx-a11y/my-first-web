<?php
$pageTitle = "Kelola Lengkap Kelompok";
require_once __DIR__ . '/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: kelompok.php");
    exit;
}

$db = get_db();

// 1. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    $type = $_POST['form_type'];

    // A. Update Profil Kelompok (Nama, Ketua, Sekretaris, Wilayah)
    if ($type === 'update_profile') {
        $nomor = intval($_POST['nomor_kelompok'] ?? 0);
        $nama = clean($_POST['nama_kelompok'] ?? '');
        $ketua = clean($_POST['nama_ketua'] ?? '');
        $no_hp_ketua = clean($_POST['no_hp_ketua'] ?? '');
        $sekretaris = clean($_POST['nama_sekretaris'] ?? '');
        $no_hp_sekretaris = clean($_POST['no_hp_sekretaris'] ?? '');
        $wilayah = clean($_POST['wilayah_cakupan'] ?? '');
        $deskripsi = clean($_POST['deskripsi_profil'] ?? '');

        $stmt = $db->prepare("
            UPDATE `groups` SET
                nomor_kelompok = ?, nama_kelompok = ?, nama_ketua = ?, no_hp_ketua = ?,
                nama_sekretaris = ?, no_hp_sekretaris = ?, wilayah_cakupan = ?, deskripsi_profil = ?
            WHERE id = ?
        ");
        $stmt->execute([$nomor, $nama, $ketua, $no_hp_ketua, $sekretaris, $no_hp_sekretaris, $wilayah, $deskripsi, $id]);
        set_flash('success', 'Profil kelompok dan data pengurus berhasil diperbarui!');
        header("Location: kelompok_detail.php?id=" . $id . "&tab=profil");
        exit;
    }

    // B. Simpan / Buat Akun Admin Kelompok
    if ($type === 'save_admin_account') {
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $namaAdmin = clean($_POST['nama_admin'] ?? 'Admin ' . $nama);

        if (empty($username)) {
            set_flash('danger', 'Username wajib diisi.');
        } else {
            // Check if user already exists for this group
            $stmtUser = $db->prepare("SELECT id FROM users WHERE group_id = ? LIMIT 1");
            $stmtUser->execute([$id]);
            $existingUser = $stmtUser->fetch();

            if ($existingUser) {
                // Update
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmtUp = $db->prepare("UPDATE users SET username = ?, password = ?, nama = ? WHERE id = ?");
                    $stmtUp->execute([$username, $hash, $namaAdmin, $existingUser['id']]);
                } else {
                    $stmtUp = $db->prepare("UPDATE users SET username = ?, nama = ? WHERE id = ?");
                    $stmtUp->execute([$username, $namaAdmin, $existingUser['id']]);
                }
                set_flash('success', 'Akun admin kelompok berhasil diperbarui!');
            } else {
                // Insert New
                if (empty($password)) $password = 'admin123';
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtIn = $db->prepare("INSERT INTO users (username, password, nama, role, group_id) VALUES (?, ?, ?, 'admin_kelompok', ?)");
                $stmtIn->execute([$username, $hash, $namaAdmin, $id]);
                set_flash('success', 'Akun login admin kelompok baru berhasil dibuat!');
            }
        }
        header("Location: kelompok_detail.php?id=" . $id . "&tab=akun");
        exit;
    }

    // C. Upload Foto Galeri Kelompok
    if ($type === 'upload_gallery') {
        $judul = clean($_POST['judul_foto'] ?? 'Kegiatan Kelompok');
        $deskripsiFoto = clean($_POST['deskripsi'] ?? '');
        $foto_url = clean($_POST['foto_url'] ?? '');

        // File upload handling
        if (isset($_FILES['file_foto']) && $_FILES['file_foto']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['file_foto']['tmp_name'];
            $fileName = $_FILES['file_foto']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExt, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/gallery/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $newFileName = 'gallery_' . $id . '_' . time() . '.' . $fileExt;
                if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                    $foto_url = base_url('uploads/gallery/' . $newFileName);
                }
            }
        }

        if (!empty($foto_url)) {
            $stmt = $db->prepare("INSERT INTO group_galleries (group_id, judul_foto, file_foto, deskripsi) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $judul, $foto_url, $deskripsiFoto]);
            set_flash('success', 'Foto kegiatan kelompok berhasil ditambahkan ke galeri!');
        } else {
            set_flash('danger', 'Gagal mengunggah foto. Pastikan format file atau URL gambar valid.');
        }
        header("Location: kelompok_detail.php?id=" . $id . "&tab=galeri");
        exit;
    }

    // D. Tambah Berita Kelompok (Tayang di Landing Portal)
    if ($type === 'create_article') {
        $title = clean($_POST['title'] ?? '');
        $category = clean($_POST['category'] ?? 'Kegiatan Warga');
        $author = clean($_POST['author'] ?? 'Admin Kelompok');
        $excerpt = clean($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? '';
        $image = clean($_POST['image'] ?? 'https://images.unsplash.com/photo-1577495508048-b635879837f1?auto=format&fit=crop&w=800&q=80');

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();

        if (!empty($title) && !empty($content)) {
            $stmt = $db->prepare("
                INSERT INTO articles (title, slug, category, group_id, excerpt, content, image, author, is_featured, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$title, $slug, $category, $id, $excerpt, $content, $image, $author]);
            set_flash('success', 'Berita kelompok berhasil dipublikasikan ke Portal Landing Page!');
        } else {
            set_flash('danger', 'Judul dan isi berita wajib diisi.');
        }
        header("Location: kelompok_detail.php?id=" . $id . "&tab=berita");
        exit;
    }
}

// 2. Handle Action Deletes
if (isset($_GET['delete_gallery'])) {
    $galId = intval($_GET['delete_gallery']);
    $db->prepare("DELETE FROM group_galleries WHERE id = ? AND group_id = ?")->execute([$galId, $id]);
    set_flash('success', 'Foto kegiatan berhasil dihapus dari galeri.');
    header("Location: kelompok_detail.php?id=" . $id . "&tab=galeri");
    exit;
}

if (isset($_GET['delete_article'])) {
    $artId = intval($_GET['delete_article']);
    $db->prepare("DELETE FROM articles WHERE id = ? AND group_id = ?")->execute([$artId, $id]);
    set_flash('success', 'Berita kelompok berhasil dihapus.');
    header("Location: kelompok_detail.php?id=" . $id . "&tab=berita");
    exit;
}

// 3. Fetch Group Data
$stmt = $db->prepare("
    SELECT 
        g.*,
        (SELECT COUNT(*) FROM families WHERE kelompok_id = g.id) as total_kk,
        (SELECT COUNT(*) FROM family_members fm JOIN families f ON fm.family_id = f.id WHERE f.kelompok_id = g.id) as total_jiwa
    FROM `groups` g
    WHERE g.id = ? LIMIT 1
");
$stmt->execute([$id]);
$group = $stmt->fetch();

if (!$group) {
    header("Location: kelompok.php");
    exit;
}

// Fetch Admin User for this Group
$stmtUser = $db->prepare("SELECT * FROM users WHERE group_id = ? LIMIT 1");
$stmtUser->execute([$id]);
$groupAdmin = $stmtUser->fetch();

// Fetch Galleries for this Group
$stmtGal = $db->prepare("SELECT * FROM group_galleries WHERE group_id = ? ORDER BY created_at DESC");
$stmtGal->execute([$id]);
$galleries = $stmtGal->fetchAll();

// Fetch Articles for this Group
$stmtArt = $db->prepare("SELECT * FROM articles WHERE group_id = ? ORDER BY published_at DESC");
$stmtArt->execute([$id]);
$articles = $stmtArt->fetchAll();

// Fetch Families in this Group
$stmtFam = $db->prepare("
    SELECT f.*, (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as total_anggota
    FROM families f
    WHERE f.kelompok_id = ?
    ORDER BY f.nama_kepala ASC
");
$stmtFam->execute([$id]);
$families = $stmtFam->fetchAll();

$activeTab = $_GET['tab'] ?? 'profil';
?>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <a href="kelompok.php" class="btn btn-outline btn-sm">← Kembali</a>
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--adm-secondary);">
                🏷️ <?= htmlspecialchars($group['nama_kelompok']) ?>
            </h2>
            <small style="color: var(--adm-text-muted);">
                Wilayah: <strong><?= htmlspecialchars($group['wilayah_cakupan']) ?: '-' ?></strong> • <?= $group['total_kk'] ?> KK Terdata (<?= $group['total_jiwa'] ?> Jiwa)
            </small>
        </div>
    </div>

    <div style="display: flex; gap: 0.5rem;">
        <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">🌐 Lihat di Portal Web</a>
        <a href="keluarga.php?kelompok_id=<?= $group['id'] ?>" class="btn btn-primary btn-sm">📋 Kelola Data KK</a>
    </div>
</div>

<!-- Tab Navigation Pills -->
<div class="category-tabs" style="margin-bottom: 1.5rem;">
    <a href="?id=<?= $id ?>&tab=profil" class="cat-pill <?= $activeTab === 'profil' ? 'active' : '' ?>">
        👤 Profil & Pengurus (Ketua & Sekertaris)
    </a>
    <a href="?id=<?= $id ?>&tab=berita" class="cat-pill <?= $activeTab === 'berita' ? 'active' : '' ?>">
        📰 Berita Portal Kelompok (<?= count($articles) ?>)
    </a>
    <a href="?id=<?= $id ?>&tab=galeri" class="cat-pill <?= $activeTab === 'galeri' ? 'active' : '' ?>">
        📸 Foto & Galeri Kegiatan (<?= count($galleries) ?>)
    </a>
    <a href="?id=<?= $id ?>&tab=akun" class="cat-pill <?= $activeTab === 'akun' ? 'active' : '' ?>">
        🔐 Akun Login Admin Kelompok
    </a>
    <a href="?id=<?= $id ?>&tab=warga" class="cat-pill <?= $activeTab === 'warga' ? 'active' : '' ?>">
        👨‍👩‍👧‍👦 Daftar KK Terdaftar (<?= count($families) ?>)
    </a>
</div>

<!-- TAB 1: PROFIL & PENGURUS (KETUA & SEKRETARIS) -->
<?php if ($activeTab === 'profil'): ?>
    <div class="card-purple">
        <div class="card-title-header">
            <h3><span>👤</span> Data Pokok Kelompok, Ketua & Sekretaris</h3>
            <small style="color: var(--adm-text-muted);">Informasi struktur penanggung jawab kelompok</small>
        </div>

        <form action="" method="POST">
            <input type="hidden" name="form_type" value="update_profile">

            <div class="form-grid" style="gap: 1.25rem;">
                <div class="form-group">
                    <label>Nomor Kelompok <span class="required">*</span></label>
                    <input type="number" name="nomor_kelompok" class="form-control" value="<?= $group['nomor_kelompok'] ?>" required>
                </div>

                <div class="form-group">
                    <label>Nama Kelompok <span class="required">*</span></label>
                    <input type="text" name="nama_kelompok" class="form-control" value="<?= htmlspecialchars($group['nama_kelompok']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Nama Ketua Kelompok <span class="required">*</span></label>
                    <input type="text" name="nama_ketua" class="form-control" value="<?= htmlspecialchars($group['nama_ketua']) ?>" placeholder="Nama lengkap ketua" required>
                </div>

                <div class="form-group">
                    <label>No. WhatsApp Ketua Kelompok</label>
                    <input type="text" name="no_hp_ketua" class="form-control" value="<?= htmlspecialchars($group['no_hp_ketua']) ?>" placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group">
                    <label>Nama Sekretaris Kelompok <span class="required">*</span></label>
                    <input type="text" name="nama_sekretaris" class="form-control" value="<?= htmlspecialchars($group['nama_sekretaris']) ?>" placeholder="Nama lengkap sekretaris" required>
                </div>

                <div class="form-group">
                    <label>No. WhatsApp Sekretaris Kelompok</label>
                    <input type="text" name="no_hp_sekretaris" class="form-control" value="<?= htmlspecialchars($group['no_hp_sekretaris']) ?>" placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group col-full">
                    <label>Wilayah Cakupan (RT / RW / Blok)</label>
                    <input type="text" name="wilayah_cakupan" class="form-control" value="<?= htmlspecialchars($group['wilayah_cakupan']) ?>" placeholder="Contoh: RT 01 & RT 02 / RW 01">
                </div>

                <div class="form-group col-full">
                    <label>Deskripsi & Program Kerja Kelompok</label>
                    <textarea name="deskripsi_profil" class="form-control" rows="3" placeholder="Tuliskan program atau fokus kegiatan kelompok ini..."><?= htmlspecialchars($group['deskripsi_profil'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem;">
                    💾 Simpan Perubahan Profil
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- TAB 2: BERITA / ARTIKEL PORTAL KELOMPOK -->
<?php if ($activeTab === 'berita'): ?>
    <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 1.5rem;">
        <!-- Form Tambah Berita -->
        <div class="card-purple">
            <div class="card-title-header">
                <h3><span>✍️</span> Tulis Berita / Info Kelompok</h3>
            </div>
            <p style="font-size: 0.85rem; color: var(--adm-text-muted); margin-bottom: 1rem;">
                Berita yang ditulis di sini akan langsung ditampilkan di <strong>Portal Berita Landing Page (index.php)</strong> dengan label <strong><?= htmlspecialchars($group['nama_kelompok']) ?></strong>.
            </p>

            <form action="" method="POST">
                <input type="hidden" name="form_type" value="create_article">

                <div class="form-group">
                    <label>Judul Berita <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Kegiatan Kerja Bakti Warga Kelompok 1" required>
                </div>

                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category" class="form-control">
                        <option value="Kegiatan Warga">Kegiatan Warga</option>
                        <option value="Pengumuman">Pengumuman</option>
                        <option value="Kesehatan">Kesehatan & Posyandu</option>
                        <option value="Bansos">Bantuan Sosial</option>
                        <option value="Pemberdayaan">Pemberdayaan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Penulis / Humas</label>
                    <input type="text" name="author" class="form-control" value="Humas <?= htmlspecialchars($group['nama_kelompok']) ?>" required>
                </div>

                <div class="form-group">
                    <label>URL Gambar / Foto Berita</label>
                    <input type="text" name="image" class="form-control" placeholder="https://..." value="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80">
                </div>

                <div class="form-group">
                    <label>Ringkasan Singkat (Excerpt)</label>
                    <textarea name="excerpt" class="form-control" rows="2" placeholder="Ringkasan 1-2 kalimat..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Isi Lengkap Berita <span class="required">*</span></label>
                    <textarea name="content" class="form-control" rows="5" placeholder="Tulis rincian berita/kegiatan di sini..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    🚀 Terbitkan ke Portal Berita
                </button>
            </form>
        </div>

        <!-- Daftar Berita Kelompok yang Sudah Terbit -->
        <div class="card-purple">
            <div class="card-title-header">
                <h3><span>📰</span> Berita Terbit Kelompok Ini (<?= count($articles) ?>)</h3>
            </div>

            <?php if (empty($articles)): ?>
                <div style="text-align: center; padding: 2.5rem; color: var(--adm-text-muted);">
                    Belum ada berita yang diterbitkan oleh <?= htmlspecialchars($group['nama_kelompok']) ?>. Silakan gunakan formulir di samping untuk membuat berita baru.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($articles as $art): ?>
                        <div style="background: var(--adm-primary-lightest); border: 1px solid var(--adm-border); border-radius: 12px; padding: 1rem; display: flex; gap: 1rem;">
                            <img src="<?= htmlspecialchars($art['image']) ?>" style="width: 100px; height: 80px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
                            <div style="flex-grow: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <span style="font-size: 0.725rem; font-weight: 800; color: #7c3aed; text-transform: uppercase;">
                                        <?= htmlspecialchars($art['category']) ?>
                                    </span>
                                    <a href="?id=<?= $id ?>&tab=berita&delete_article=<?= $art['id'] ?>" class="btn-danger-outline btn-sm" onclick="return confirm('Hapus berita ini dari portal?');" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                        ✕ Hapus
                                    </a>
                                </div>
                                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--adm-secondary); margin: 0.25rem 0;">
                                    <?= htmlspecialchars($art['title']) ?>
                                </h4>
                                <small style="color: var(--adm-text-muted);">
                                    ✍️ <?= htmlspecialchars($art['author']) ?> • 📅 <?= date('d M Y', strtotime($art['published_at'])) ?> • 👁️ <?= $art['views'] ?>x
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- TAB 3: FOTO & GALERI KEGIATAN KELOMPOK -->
<?php if ($activeTab === 'galeri'): ?>
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
        <!-- Upload Foto Form -->
        <div class="card-purple">
            <div class="card-title-header">
                <h3><span>📷</span> Tambah Foto Kegiatan</h3>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="upload_gallery">

                <div class="form-group">
                    <label>Judul Kegiatan / Foto <span class="required">*</span></label>
                    <input type="text" name="judul_foto" class="form-control" placeholder="Contoh: Kerja Bakti RT 01" required>
                </div>

                <div class="form-group">
                    <label>Upload File Foto (JPG/PNG/WEBP)</label>
                    <input type="file" name="file_foto" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Atau Gunakan Link / URL Gambar</label>
                    <input type="text" name="foto_url" class="form-control" placeholder="https://...">
                </div>

                <div class="form-group">
                    <label>Deskripsi Foto (Opsional)</label>
                    <textarea name="deskripsi" class="form-control" rows="2" placeholder="Keterangan singkat kegiatan..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    ➕ Upload Foto ke Galeri
                </button>
            </form>
        </div>

        <!-- Grid Galeri Foto -->
        <div class="card-purple">
            <div class="card-title-header">
                <h3><span>🖼️</span> Galeri Kegiatan <?= htmlspecialchars($group['nama_kelompok']) ?> (<?= count($galleries) ?> Foto)</h3>
            </div>

            <?php if (empty($galleries)): ?>
                <div style="text-align: center; padding: 2.5rem; color: var(--adm-text-muted);">
                    Belum ada foto kegiatan di galeri kelompok ini. Silakan upload foto menggunakan formulir di samping.
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                    <?php foreach ($galleries as $gal): ?>
                        <div style="background: #f8fafc; border: 1px solid var(--adm-border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;">
                            <img src="<?= htmlspecialchars($gal['file_foto']) ?>" style="width: 100%; height: 140px; object-fit: cover;">
                            <div style="padding: 0.75rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <h5 style="font-size: 0.85rem; font-weight: 700; color: var(--adm-secondary); margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($gal['judul_foto']) ?>
                                    </h5>
                                    <p style="font-size: 0.75rem; color: var(--adm-text-muted); line-height: 1.3;">
                                        <?= htmlspecialchars($gal['deskripsi']) ?>
                                    </p>
                                </div>
                                <div style="margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 0.4rem;">
                                    <small style="font-size: 0.7rem; color: #94a3b8;"><?= date('d M Y', strtotime($gal['created_at'])) ?></small>
                                    <a href="?id=<?= $id ?>&tab=galeri&delete_gallery=<?= $gal['id'] ?>" class="btn-danger-outline btn-sm" onclick="return confirm('Hapus foto ini dari galeri?');" style="padding: 0.15rem 0.45rem; font-size: 0.7rem;">
                                        Hapus
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- TAB 4: AKUN LOGIN ADMIN KELOMPOK -->
<?php if ($activeTab === 'akun'): ?>
    <div class="card-purple" style="max-width: 600px; margin: 0 auto;">
        <div class="card-title-header">
            <h3><span>🔐</span> Akun Login Admin / Petugas <?= htmlspecialchars($group['nama_kelompok']) ?></h3>
        </div>
        <p style="font-size: 0.875rem; color: var(--adm-text-muted); margin-bottom: 1.5rem;">
            Akun ini dapat digunakan oleh Ketua atau Sekretaris Kelompok untuk login ke sistem, mengelola data keluarga warga binaan, serta mempublikasikan berita & foto kelompoknya sendiri.
        </p>

        <form action="" method="POST">
            <input type="hidden" name="form_type" value="save_admin_account">

            <div class="form-group">
                <label>Nama Pengguna / Petugas Admin</label>
                <input type="text" name="nama_admin" class="form-control" value="<?= htmlspecialchars($groupAdmin['nama'] ?? 'Admin ' . $group['nama_kelompok']) ?>" required>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label>Username Login <span class="required">*</span></label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($groupAdmin['username'] ?? 'kelompok' . $group['nomor_kelompok']) ?>" required>
                <small style="color: var(--adm-text-muted);">Contoh: <code>kelompok<?= $group['nomor_kelompok'] ?></code></small>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label>Password Baru</label>
                <input type="password" name="password" class="form-control" placeholder="<?= $groupAdmin ? 'Kosongkan jika tidak ingin mengubah password' : 'Masukkan password baru (default: admin123)' ?>">
            </div>

            <div style="margin-top: 1.5rem; background: var(--adm-primary-lightest); padding: 1rem; border-radius: 12px; border: 1px solid var(--adm-border); font-size: 0.85rem;">
                <strong>Status Akun:</strong> 
                <?php if ($groupAdmin): ?>
                    <span style="color: #059669; font-weight: 700;">✓ Aktif (Username: <?= htmlspecialchars($groupAdmin['username']) ?>)</span>
                <?php else: ?>
                    <span style="color: #d97706; font-weight: 700;">Belum Dibuat</span>
                <?php endif; ?>
            </div>

            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem;">
                    💾 Simpan Akun Admin Kelompok
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- TAB 5: DAFTAR WARGA KK -->
<?php if ($activeTab === 'warga'): ?>
    <div class="data-table-container" style="margin-top: 0;">
        <div class="table-header-filter">
            <div>
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--adm-secondary);">
                    Daftar Kartu Keluarga Terdaftar di <?= htmlspecialchars($group['nama_kelompok']) ?>
                </h3>
                <small style="color: var(--adm-text-muted);">Total <?= count($families) ?> KK Terdaftar</small>
            </div>
            <a href="export_excel.php" class="btn btn-accent btn-sm">📥 Unduh Excel</a>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No. KK</th>
                        <th>Nama Kepala Keluarga</th>
                        <th>WhatsApp</th>
                        <th>RT / RW</th>
                        <th>Anggota</th>
                        <th>Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($families)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--adm-text-muted);">
                                Belum ada data keluarga yang didaftarkan ke kelompok ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($families as $idx => $f): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <a href="detail.php?id=<?= $f['id'] ?>" style="font-family: monospace; font-weight: 800; color: #7c3aed; text-decoration: none;">
                                        <?= htmlspecialchars($f['no_kk']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--adm-secondary);"><?= htmlspecialchars($f['nama_kepala']) ?></div>
                                    <small style="color: var(--adm-text-muted);">NIK: <?= htmlspecialchars($f['nik_kepala']) ?></small>
                                </td>
                                <td>
                                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $f['no_hp'])) ?>" target="_blank" style="color: #059669; text-decoration: none; font-weight: 700;">
                                        💬 <?= htmlspecialchars($f['no_hp']) ?>
                                    </a>
                                </td>
                                <td>RT <?= htmlspecialchars($f['rt']) ?> / RW <?= htmlspecialchars($f['rw']) ?></td>
                                <td><strong><?= $f['total_anggota'] ?></strong> Jiwa</td>
                                <td>
                                    <span class="badge-status badge-<?= $f['status_verifikasi'] ?>">
                                        <?= ucfirst($f['status_verifikasi']) ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="detail.php?id=<?= $f['id'] ?>" class="btn btn-outline btn-sm" style="padding: 0.25rem 0.6rem;">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
