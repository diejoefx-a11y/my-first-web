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

    // Template for new member row
    function createMemberHtml(index, isFirst = false) {
        const defaultRole = isFirst ? 'Kepala Keluarga' : 'Istri';
        return `
        <div class="member-card" id="member-row-${index}" data-index="${index}" style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; margin-bottom: 0.75rem;">
            <div class="member-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                <span class="member-badge" style="font-weight: 800; color: #7c3aed; font-size: 0.9rem;">
                    ${isFirst ? '👤 Kepala Keluarga (Data Pokok)' : `👨‍👩‍👧‍👦 Anggota #${index + 1}`}
                </span>
                ${!isFirst ? `<button type="button" class="btn btn-sm btn-remove-member" data-index="${index}" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; border-radius:8px; padding:2px 10px; font-weight:700; font-size:0.75rem; cursor:pointer;">✕ Hapus</button>` : `<span style="font-size:0.75rem; color:#64748b; background:#ede9fe; padding:2px 8px; border-radius:6px; font-weight:700;">Kepala Keluarga</span>`}
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label style="font-size:0.85rem; font-weight:600;">Nama Lengkap <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="members[${index}][nama_lengkap]" class="form-control input-nama" placeholder="Sesuai KTP/KK" ${isFirst ? 'required' : ''}>
                </div>
                <div class="form-group">
                    <label style="font-size:0.85rem; font-weight:600;">NIK</label>
                    <input type="text" name="members[${index}][nik]" class="form-control input-nik" maxlength="16" placeholder="16 digit NIK">
                </div>
                <div class="form-group">
                    <label style="font-size:0.85rem; font-weight:600;">Hubungan Keluarga <span style="color:#ef4444;">*</span></label>
                    <select name="members[${index}][hubungan_keluarga]" class="form-control">
                        <option value="Kepala Keluarga" ${defaultRole === 'Kepala Keluarga' ? 'selected' : ''}>Kepala Keluarga</option>
                        <option value="Istri" ${defaultRole === 'Istri' ? 'selected' : ''}>Istri</option>
                        <option value="Anak">Anak</option>
                        <option value="Orang Tua">Orang Tua</option>
                        <option value="Famili Lain">Famili Lain</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-size:0.85rem; font-weight:600;">Jenis Kelamin</label>
                    <select name="members[${index}][jenis_kelamin]" class="form-control">
                        <option value="L">Laki-Laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-size:0.85rem; font-weight:600;">Tempat Lahir</label>
                    <input type="text" name="members[${index}][tempat_lahir]" class="form-control" placeholder="Kota/Kabupaten">
                </div>
                <div class="form-group">
                    <label style="font-size:0.85rem; font-weight:600;">Tanggal Lahir</label>
                    <input type="date" name="members[${index}][tanggal_lahir]" class="form-control">
                </div>
            </div>
        </div>
        `;
    }

    function updateMemberNumbers() {
        const rows = container.querySelectorAll('.member-card');
        rows.forEach((row, idx) => {
            if (idx > 0) {
                const badge = row.querySelector('.member-badge');
                if (badge) badge.textContent = `👨‍👩‍👧‍👦 Anggota #${idx + 1}`;
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
