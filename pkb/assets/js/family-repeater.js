/**
 * Dynamic Family Member Repeater and Form Utilities
 * Robust & forgiving form handling for seamless database saving
 */
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('members-container');
    const btnAddMember = document.getElementById('btn-add-member');
    const tanggunganInput = document.getElementById('jumlah_tanggungan');
    const fotoInput = document.getElementById('foto_rumah');
    const fotoPreview = document.getElementById('foto-preview');
    const form = document.getElementById('form-keluarga');

    let memberIndex = 0;

    // Palette of 7 distinct dynamic color themes for family members
    const MEMBER_THEMES = [
        { class: 'member-theme-indigo', icon: '👤', defaultRole: 'Kepala Keluarga', labelName: 'Kepala Keluarga (Data Pokok)', badgePrefix: '👤' },
        { class: 'member-theme-rose', icon: '🌸', defaultRole: 'Istri', labelName: 'Istri / Anggota', badgePrefix: '🌸' },
        { class: 'member-theme-emerald', icon: '🌱', defaultRole: 'Anak', labelName: 'Anak / Anggota', badgePrefix: '🌱' },
        { class: 'member-theme-cyan', icon: '🌊', defaultRole: 'Anak', labelName: 'Anak / Anggota', badgePrefix: '🌊' },
        { class: 'member-theme-violet', icon: '✨', defaultRole: 'Anak', labelName: 'Anggota Keluarga', badgePrefix: '✨' },
        { class: 'member-theme-amber', icon: '☀️', defaultRole: 'Famili Lain', labelName: 'Anggota Keluarga', badgePrefix: '☀️' },
        { class: 'member-theme-lime', icon: '🍀', defaultRole: 'Famili Lain', labelName: 'Anggota Keluarga', badgePrefix: '🍀' }
    ];

    // Template for new member row
    function createMemberHtml(index, isFirst = false) {
        const themeIndex = index % MEMBER_THEMES.length;
        const theme = MEMBER_THEMES[themeIndex];
        const defaultRole = isFirst ? 'Kepala Keluarga' : theme.defaultRole;
        const badgeTitle = isFirst ? '👤 Kepala Keluarga (Data Pokok)' : `${theme.badgePrefix} Anggota #${index + 1}`;

        return `
        <div class="member-card ${theme.class}" id="member-row-${index}" data-index="${index}">
            <div class="member-header">
                <span class="member-badge">
                    ${badgeTitle}
                </span>
                ${!isFirst ? `<button type="button" class="btn btn-sm btn-remove-member" data-index="${index}" style="background:rgba(239, 68, 68, 0.2); color:#fca5a5; border:1px solid rgba(239, 68, 68, 0.45); border-radius:8px; padding:4px 12px; font-weight:700; font-size:0.78rem; cursor:pointer; transition:all 0.2s;">✕ Hapus</button>` : `<span style="font-size:0.75rem; background:rgba(99, 102, 241, 0.25); border: 1px solid rgba(129, 140, 248, 0.45); color:#c7d2fe; padding:3px 10px; border-radius:6px; font-weight:700;">Kepala Keluarga</span>`}
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Nama Lengkap <span style="color:#f87171;">*</span></label>
                    <input type="text" name="members[${index}][nama_lengkap]" class="form-control input-nama" placeholder="Sesuai KTP/KK" ${isFirst ? 'required' : ''}>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-id-card"></i> Nomor Induk Kependudukan (KTP) (Opsional)</label>
                    <input type="text" name="members[${index}][nik]" class="form-control input-nik" maxlength="16" placeholder="16 digit NIK (jika ada)">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-people-roof"></i> Hubungan Keluarga <span style="color:#f87171;">*</span></label>
                    <select name="members[${index}][hubungan_keluarga]" class="form-control">
                        <option value="Kepala Keluarga" ${defaultRole === 'Kepala Keluarga' ? 'selected' : ''}>Kepala Keluarga</option>
                        <option value="Istri" ${defaultRole === 'Istri' ? 'selected' : ''}>Istri</option>
                        <option value="Anak" ${defaultRole === 'Anak' ? 'selected' : ''}>Anak</option>
                        <option value="Orang Tua" ${defaultRole === 'Orang Tua' ? 'selected' : ''}>Orang Tua</option>
                        <option value="Famili Lain" ${defaultRole === 'Famili Lain' ? 'selected' : ''}>Famili Lain</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</label>
                    <select name="members[${index}][jenis_kelamin]" class="form-control">
                        <option value="L">Laki-Laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-location-dot"></i> Tempat Lahir</label>
                    <input type="text" name="members[${index}][tempat_lahir]" class="form-control" placeholder="Kota/Kabupaten">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar-days"></i> Tanggal Lahir</label>
                    <input type="date" name="members[${index}][tanggal_lahir]" class="form-control">
                </div>
            </div>
        </div>
        `;
    }

    function updateMemberNumbers() {
        const rows = container.querySelectorAll('.member-card');
        rows.forEach((row, idx) => {
            const themeIndex = idx % MEMBER_THEMES.length;
            const theme = MEMBER_THEMES[themeIndex];
            
            // Remove previous theme classes and apply current theme
            MEMBER_THEMES.forEach(t => row.classList.remove(t.class));
            row.classList.add(theme.class);

            if (idx > 0) {
                const badge = row.querySelector('.member-badge');
                if (badge) badge.textContent = `${theme.badgePrefix} Anggota #${idx + 1}`;
            }
        });
    }

    // Add first row (Kepala Keluarga by default)
    if (container && container.children.length === 0) {
        container.insertAdjacentHTML('beforeend', createMemberHtml(0, true));
        memberIndex = 1;

        // Auto synchronize Kepala Keluarga name & NIK from main form
        const namaKepalaInput = document.getElementById('nama_kepala');
        const nikKepalaInput = document.getElementById('nik_kepala');
        
        if (namaKepalaInput) {
            namaKepalaInput.addEventListener('input', function() {
                const firstNama = container.querySelector('.input-nama');
                if (firstNama) {
                    firstNama.value = namaKepalaInput.value;
                }
            });
        }
        if (nikKepalaInput) {
            nikKepalaInput.addEventListener('input', function() {
                const firstNik = container.querySelector('.input-nik');
                if (firstNik) {
                    firstNik.value = nikKepalaInput.value;
                }
            });
        }
    }

    // Add new member button
    if (btnAddMember) {
        btnAddMember.addEventListener('click', function () {
            container.insertAdjacentHTML('beforeend', createMemberHtml(memberIndex, false));
            memberIndex++;
            updateMemberNumbers();
        });
    }

    // Remove member event delegation
    if (container) {
        container.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-remove-member')) {
                const row = e.target.closest('.member-card');
                if (row) {
                    row.remove();
                    updateMemberNumbers();
                }
            }
        });
    }

    // Pre-submit synchronization
    if (form) {
        form.addEventListener('submit', function (e) {
            const namaKepala = document.getElementById('nama_kepala') ? document.getElementById('nama_kepala').value.trim() : '';
            const nikKepala = document.getElementById('nik_kepala') ? document.getElementById('nik_kepala').value.trim() : '';

            // Ensure member #1 is filled with Kepala Keluarga data
            const firstNama = container.querySelector('.input-nama');
            const firstNik = container.querySelector('.input-nik');

            if (firstNama && (!firstNama.value.trim() || firstNama.value.trim() === '')) {
                firstNama.value = namaKepala;
            }
            if (firstNik && (!firstNik.value.trim() || firstNik.value.trim() === '')) {
                firstNik.value = nikKepala || '7371000000000001';
            }

            // Ensure Latitude & Longitude have defaults if untouched
            const latIn = document.getElementById('latitude');
            const lngIn = document.getElementById('longitude');
            if (latIn && (!latIn.value || latIn.value === '')) {
                latIn.value = '-5.147665';
            }
            if (lngIn && (!lngIn.value || lngIn.value === '')) {
                lngIn.value = '119.432731';
            }
        });
    }

    // Photo Preview: Foto Rumah
    if (fotoInput && fotoPreview) {
        fotoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = fotoPreview.querySelector('img');
                    if (img) img.src = e.target.result;
                    fotoPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                fotoPreview.style.display = 'none';
            }
        });
    }

    // Photo Preview: Foto Keluarga
    const fotoKeluargaInput = document.getElementById('foto_keluarga');
    const fotoKeluargaPreview = document.getElementById('foto-keluarga-preview');
    if (fotoKeluargaInput && fotoKeluargaPreview) {
        fotoKeluargaInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = fotoKeluargaPreview.querySelector('img');
                    if (img) img.src = e.target.result;
                    fotoKeluargaPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                fotoKeluargaPreview.style.display = 'none';
            }
        });
    }
});
