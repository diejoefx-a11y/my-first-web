/**
 * FPL-BOT - Frontend Interactive Application Logic
 * Aturan Komposisi Skuad Resmi FPL: 2 GK, 5 DEF, 5 MID, 3 FWD (Total 15 Pemain)
 * Aturan Kuota Klub: Maksimal 3 Pemain per Klub
 * Indikator Sisa Bank: HIJAU jika aman/sisa, MERAH jika defisit/lebih
 */

let appState = {
    gameweek: 1,
    deadlineTime: null,
    squad: [],
    squadValue: 0,
    bank: 0,
    isOverBudget: false,
    settings: {},
    recommendation: null,
    youtubeInsight: null
};

// State untuk Squad Builder
let builderPicks = [];
let targetSlotIndex = null;

document.addEventListener('DOMContentLoaded', () => {
    fetchTeamData();
    setupEventListeners();
    setInterval(updateCountdown, 1000);
});

/**
 * Aturan Posisi Slot Resmi FPL:
 * Slot 0, 1   : 2 GK (Kiper)
 * Slot 2..6   : 5 DEF (Bek)
 * Slot 7..11  : 5 MID (Gelandang)
 * Slot 12..14 : 3 FWD (Penyerang)
 */
function getSlotType(idx) {
    if (idx < 2) return 1;       // Kiper (GK) -> 2 slot (0, 1)
    if (idx < 7) return 2;       // Bek (DEF) -> 5 slot (2, 3, 4, 5, 6)
    if (idx < 12) return 3;      // Gelandang (MID) -> 5 slot (7, 8, 9, 10, 11)
    return 4;                   // Penyerang (FWD) -> 3 slot (12, 13, 14)
}

/**
 * Fetch Main Data from API
 */
async function fetchTeamData() {
    showLoading(true);
    try {
        const res = await fetch('api/get_team.php');
        const data = await res.json();

        if (data.status === 'error') {
            alert('Error memuat data: ' + data.message);
            showLoading(false);
            return;
        }

        appState.gameweek = data.gameweek || 1;
        appState.deadlineTime = data.deadline_time ? new Date(data.deadline_time) : null;
        appState.squad = data.squad || [];
        appState.squadValue = data.squad_value || 0;
        appState.bank = data.bank || 0;
        appState.isOverBudget = data.is_over_budget || false;
        appState.settings = data.settings || {};
        appState.recommendation = data.recommendation || {};
        appState.youtubeInsight = data.youtube_insight || null;

        // Update Header Stats
        document.getElementById('stat-gw').innerText = `GW ${appState.gameweek}`;
        
        // INDIKATOR SISA BANK HEADER (HIJAU jika >= 0, MERAH jika < 0)
        const bankElem = document.getElementById('stat-bank');
        const pillBank = document.getElementById('pill-bank');
        if (appState.bank >= 0) {
            bankElem.innerText = `+£${appState.bank.toFixed(1)}m`;
            bankElem.style.color = '#00ff87';
            if (pillBank) {
                pillBank.style.border = '1px solid rgba(0, 255, 135, 0.4)';
                pillBank.style.background = 'rgba(0, 255, 135, 0.08)';
            }
        } else {
            bankElem.innerText = `-£${Math.abs(appState.bank).toFixed(1)}m (Minus)`;
            bankElem.style.color = '#ef4444';
            if (pillBank) {
                pillBank.style.border = '1px solid rgba(239, 68, 68, 0.5)';
                pillBank.style.background = 'rgba(239, 68, 68, 0.15)';
            }
        }

        document.getElementById('stat-ft').innerText = `${data.free_transfers} FT`;

        renderPitch(appState.squad);
        renderRecommendations(appState.recommendation);
        renderSettings(appState.settings);
        renderYoutubeInsight(appState.youtubeInsight);

    } catch (err) {
        console.error('Fetch error:', err);
    } finally {
        showLoading(false);
    }
}

/**
 * Render Football Pitch (Starting XI + 4 Bench)
 */
function renderPitch(squad) {
    const gkRow = document.getElementById('row-gkp');
    const defRow = document.getElementById('row-def');
    const midRow = document.getElementById('row-mid');
    const fwdRow = document.getElementById('row-fwd');
    const benchRow = document.getElementById('row-bench');

    gkRow.innerHTML = '';
    defRow.innerHTML = '';
    midRow.innerHTML = '';
    fwdRow.innerHTML = '';
    benchRow.innerHTML = '';

    const starters = squad.filter(p => p.position <= 11);
    const bench = squad.filter(p => p.position > 11);

    starters.forEach(player => {
        const card = createPlayerChip(player);
        if (player.element_type === 1) gkRow.appendChild(card);
        else if (player.element_type === 2) defRow.appendChild(card);
        else if (player.element_type === 3) midRow.appendChild(card);
        else if (player.element_type === 4) fwdRow.appendChild(card);
    });

    bench.forEach(player => {
        const card = createPlayerChip(player, true);
        benchRow.appendChild(card);
    });
}

function createPlayerChip(player, isBench = false) {
    const chip = document.createElement('div');
    chip.className = `player-chip ${player.is_injured ? 'injured' : ''}`;

    let roleBadge = '';
    if (player.is_captain) roleBadge = `<span class="player-badge-role">C</span>`;
    else if (player.is_vice_captain) roleBadge = `<span class="player-badge-role vc">VC</span>`;

    const fdrClass = `fdr-${Math.min(5, Math.max(1, Math.round(player.avg_fdr || 3)))}`;

    chip.innerHTML = `
        ${roleBadge}
        <div class="player-name" title="${player.full_name}">${player.web_name}</div>
        <div class="player-meta">
            <span class="player-score-badge">★ ${player.total_score}</span>
            <span class="player-fdr-badge ${fdrClass}">FDR ${player.avg_fdr}</span>
        </div>
        <div style="font-size:0.6rem; color:#94a3b8; margin-top:2px;">£${player.now_cost}m · ${player.team_short}</div>
    `;
    return chip;
}

/**
 * Render Recommendations Card
 */
function renderRecommendations(rec) {
    const container = document.getElementById('rec-container');
    if (!rec || !rec.transfer_out || !rec.transfer_in) {
        container.innerHTML = `<div style="color:#94a3b8; font-size:0.85rem; text-align:center; padding:1rem;">Skuad saat ini sudah optimal dan seimbang dengan saldo bank! Tidak ada saran transfer mendesak.</div>`;
    } else {
        const outP = rec.transfer_out;
        const inP = rec.transfer_in;
        const isRemainBankPositive = (rec.remaining_bank_after >= 0);
        container.innerHTML = `
            <div class="transfer-pair">
                <div class="transfer-card out">
                    <div class="transfer-label">🔴 Transfer Out</div>
                    <div class="transfer-player-name">${outP.web_name}</div>
                    <div class="transfer-player-sub">${outP.team_short} · £${outP.now_cost}m · Skor: ${outP.total_score}</div>
                </div>
                <div class="transfer-arrow">➔</div>
                <div class="transfer-card in">
                    <div class="transfer-label">🟢 Transfer In</div>
                    <div class="transfer-player-name">${inP.web_name}</div>
                    <div class="transfer-player-sub">${inP.team_short} · £${inP.now_cost}m · Skor: ${inP.total_score}</div>
                </div>
            </div>
            <div style="font-size:0.75rem; color:#00ff87; margin-top:0.5rem; text-align:center; font-weight:600;">
                ✨ Estimasi Peningkatan Skor: +${rec.gain_score} Poin
            </div>
            <div style="font-size:0.75rem; text-align:center; margin-top:0.25rem; color:${isRemainBankPositive ? '#00ff87' : '#ef4444'}; font-weight:700;">
                💰 Sisa Saldo Bank Setelah Transfer: <span>${isRemainBankPositive ? '+' : ''}£${rec.remaining_bank_after.toFixed(1)}m</span>
            </div>
        `;
    }

    if (rec && rec.captain) {
        document.getElementById('cap-name').innerText = `${rec.captain.web_name} (${rec.captain.team_short})`;
    }
    if (rec && rec.vice_captain) {
        document.getElementById('vc-name').innerText = `${rec.vice_captain.web_name} (${rec.vice_captain.team_short})`;
    }
}

function renderSettings(settings) {
    const list = document.getElementById('param-list');
    list.innerHTML = '';

    Object.keys(settings).forEach(key => {
        const item = settings[key];
        const row = document.createElement('div');
        row.className = 'param-item';

        row.innerHTML = `
            <div class="param-header">
                <span class="param-title">${item.name}</span>
                <label class="switch">
                    <input type="checkbox" id="toggle-${key}" ${item.is_active ? 'checked' : ''} onchange="onSettingChange()">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="param-slider-wrapper">
                <input type="range" class="param-slider" id="slider-${key}" min="0" max="100" value="${item.weight}" oninput="document.getElementById('val-${key}').innerText = this.value + '%'" onchange="onSettingChange()">
                <span class="param-weight-val" id="val-${key}">${item.weight}%</span>
            </div>
            <div style="font-size:0.65rem; color:#64748b; margin-top:0.25rem;">${item.description || ''}</div>
        `;
        list.appendChild(row);
    });
}

let saveTimeout = null;
function onSettingChange() {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(saveSettings, 500);
}

async function saveSettings() {
    const payload = { settings: {} };
    Object.keys(appState.settings).forEach(key => {
        const toggle = document.getElementById(`toggle-${key}`);
        const slider = document.getElementById(`slider-${key}`);
        if (toggle && slider) {
            payload.settings[key] = {
                is_active: toggle.checked ? 1 : 0,
                weight: parseInt(slider.value)
            };
        }
    });

    try {
        const res = await fetch('api/update_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            fetchTeamData();
        }
    } catch (e) {
        console.error('Save settings error:', e);
    }
}

function renderYoutubeInsight(yt) {
    const box = document.getElementById('yt-insight-box');
    if (!yt) {
        box.innerHTML = `
            <div style="font-size:0.8rem; color:#94a3b8;">Belum ada analisis video tersimpan untuk Gameweek ini.</div>
            <button class="btn btn-secondary" style="margin-top:0.5rem;" onclick="openModal('modal-yt')">➕ Input Link YouTube</button>
        `;
        return;
    }

    let buysHtml = (yt.recommended_buys || []).map(b => `<span class="badge-tag badge-buy">Buy: ${b}</span>`).join('');
    let sellsHtml = (yt.recommended_sells || []).map(s => `<span class="badge-tag badge-sell">Sell: ${s}</span>`).join('');
    let capHtml = (yt.recommended_captains || []).map(c => `<span class="badge-tag badge-cap">Cap: ${c}</span>`).join('');

    box.innerHTML = `
        <div style="font-size:0.85rem; font-weight:700; color:#f8fafc; margin-bottom:0.25rem;">
            📺 <a href="${yt.video_url}" target="_blank" style="color:#38bdf8; text-decoration:none;">${yt.video_title}</a>
        </div>
        <div style="font-size:0.75rem; color:#94a3b8; margin-bottom:0.5rem;">
            Channel: <strong>${yt.channel_name}</strong> ${yt.is_fallback ? '<span style="color:#f59e0b;">(Fallback dari GW sebelumnya)</span>' : ''}
        </div>
        <div style="margin-bottom:0.5rem;">
            ${buysHtml} ${sellsHtml} ${capHtml}
        </div>
        <button class="btn btn-secondary" style="font-size:0.75rem; padding:0.4rem;" onclick="openModal('modal-yt')">🔄 Update / Ganti Link YouTube</button>
    `;
}

/**
 * ==================================================================
 * SQUAD BUILDER (TEPAT 15 SLOT: 2 GK, 5 DEF, 5 MID, 3 FWD)
 * ==================================================================
 */
function openSquadBuilder() {
    const gks = appState.squad.filter(p => p.element_type === 1);
    const defs = appState.squad.filter(p => p.element_type === 2);
    const mids = appState.squad.filter(p => p.element_type === 3);
    const fwds = appState.squad.filter(p => p.element_type === 4);

    const exactPicks = [];

    // 1. Slot 0..1 : 2 Kiper (GK)
    for (let i = 0; i < 2; i++) {
        if (gks[i]) {
            exactPicks.push(JSON.parse(JSON.stringify(gks[i])));
        } else {
            exactPicks.push({ id: 0, web_name: 'Pilih Kiper...', full_name: 'Pilih Kiper', team_short: 'PL', now_cost: 4.0, element_type: 1, is_captain: false, is_vice_captain: false });
        }
    }

    // 2. Slot 2..6 : 5 Bek (DEF)
    for (let i = 0; i < 5; i++) {
        if (defs[i]) {
            exactPicks.push(JSON.parse(JSON.stringify(defs[i])));
        } else {
            exactPicks.push({ id: 0, web_name: 'Pilih Bek...', full_name: 'Pilih Bek', team_short: 'PL', now_cost: 4.5, element_type: 2, is_captain: false, is_vice_captain: false });
        }
    }

    // 3. Slot 7..11 : 5 Gelandang (MID)
    for (let i = 0; i < 5; i++) {
        if (mids[i]) {
            exactPicks.push(JSON.parse(JSON.stringify(mids[i])));
        } else {
            exactPicks.push({ id: 0, web_name: 'Pilih Gelandang...', full_name: 'Pilih Gelandang', team_short: 'PL', now_cost: 5.0, element_type: 3, is_captain: false, is_vice_captain: false });
        }
    }

    // 4. Slot 12..14 : 3 Penyerang (FWD)
    for (let i = 0; i < 3; i++) {
        if (fwds[i]) {
            exactPicks.push(JSON.parse(JSON.stringify(fwds[i])));
        } else {
            exactPicks.push({ id: 0, web_name: 'Pilih Penyerang...', full_name: 'Pilih Penyerang', team_short: 'PL', now_cost: 6.0, element_type: 4, is_captain: false, is_vice_captain: false });
        }
    }

    builderPicks = exactPicks;

    // Pastikan status Kapten (C) dan Wakil Kapten (VC) terpasang
    if (!builderPicks.some(p => p.is_captain)) {
        builderPicks[12].is_captain = true;
    }
    if (!builderPicks.some(p => p.is_vice_captain)) {
        builderPicks[7].is_vice_captain = true;
    }

    renderBuilderSlots();
    openModal('modal-squad-builder');
}

function renderBuilderSlots() {
    const grid = document.getElementById('builder-slots-grid');
    grid.innerHTML = '';

    let totalCost = 0;
    const typeNames = { 1: 'GK', 2: 'DEF', 3: 'MID', 4: 'FWD' };
    const sectionHeaders = {
        0: '🧤 KIPER (GK) - 2 Pemain',
        2: '🛡️ BEK (DEF) - 5 Pemain',
        7: '👟 GELANDANG (MID) - 5 Pemain',
        12: '⚽ PENYERANG (FWD) - 3 Pemain'
    };

    const teamCounts = {};
    let hasUnselectedSlot = false;

    builderPicks.forEach((p, idx) => {
        totalCost += (p.now_cost || 0);
        const tShort = p.team_short || 'PL';
        if (p.id > 0) {
            teamCounts[tShort] = (teamCounts[tShort] || 0) + 1;
        } else {
            hasUnselectedSlot = true;
        }

        const slotType = getSlotType(idx);
        p.element_type = slotType;

        if (sectionHeaders[idx]) {
            const headerDiv = document.createElement('div');
            headerDiv.style.gridColumn = '1 / -1';
            headerDiv.style.fontSize = '0.75rem';
            headerDiv.style.fontWeight = '800';
            headerDiv.style.color = '#00ff87';
            headerDiv.style.marginTop = (idx > 0) ? '0.75rem' : '0.25rem';
            headerDiv.style.paddingBottom = '0.2rem';
            headerDiv.style.borderBottom = '1px solid #334155';
            headerDiv.innerText = sectionHeaders[idx];
            grid.appendChild(headerDiv);
        }

        const card = document.createElement('div');
        card.style.background = '#1e293b';
        card.style.border = (teamCounts[tShort] > 3) ? '1px solid #ef4444' : (p.id === 0 ? '1px dashed #f59e0b' : '1px solid #334155');
        card.style.borderRadius = '8px';
        card.style.padding = '0.5rem 0.75rem';
        card.style.display = 'flex';
        card.style.justifyContent = 'space-between';
        card.style.alignItems = 'center';

        const typeBadge = `<span style="font-size:0.65rem; font-weight:700; padding:0.1rem 0.35rem; border-radius:4px; background:#334155; color:#00ff87;">${typeNames[slotType]}</span>`;
        const capChecked = p.is_captain ? 'checked' : '';
        const vcChecked = p.is_vice_captain ? 'checked' : '';

        card.innerHTML = `
            <div>
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    ${typeBadge}
                    <strong style="font-size:0.85rem; color:${p.id === 0 ? '#f59e0b' : '#f8fafc'};">${p.web_name}</strong>
                </div>
                <div style="font-size:0.7rem; color:#94a3b8; margin-top:2px;">
                    ${p.id > 0 ? `${p.team_short} · £${p.now_cost}m <span style="font-size:0.65rem; color:${teamCounts[tShort] >= 3 ? '#ef4444' : '#64748b'};">(${teamCounts[tShort]}/3)</span>` : '<span style="color:#f59e0b;">Klik tombol "Pilih" untuk memilih pemain</span>'}
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:0.4rem;">
                <label style="font-size:0.7rem; cursor:pointer;" title="Jadikan Kapten (C)">
                    <input type="radio" name="builder_cap" value="${idx}" ${capChecked} onchange="setBuilderRole(${idx}, 'C')"> C
                </label>
                <label style="font-size:0.7rem; cursor:pointer;" title="Jadikan Wakil Kapten (VC)">
                    <input type="radio" name="builder_vc" value="${idx}" ${vcChecked} onchange="setBuilderRole(${idx}, 'VC')"> VC
                </label>
                <button class="btn ${p.id === 0 ? 'btn-primary' : 'btn-secondary'}" style="width:auto; padding:0.2rem 0.4rem; font-size:0.7rem;" onclick="prepareReplaceSlot(${idx})">${p.id === 0 ? 'Pilih' : 'Ganti'}</button>
            </div>
        `;
        grid.appendChild(card);
    });

    totalCost = Math.round(totalCost * 10) / 10;
    const remainingBank = Math.round((100.0 - totalCost) * 10) / 10;
    const costContainer = document.getElementById('builder-total-cost');
    const saveBtn = document.getElementById('btn-save-builder');

    // Cek Pelanggaran Batas Tim (>3 pemain dari klub yang sama)
    let teamViolation = null;
    Object.keys(teamCounts).forEach(team => {
        if (teamCounts[team] > 3) teamViolation = team;
    });

    // Render Quota Summary Badge
    const clubQuotaPills = Object.keys(teamCounts).map(t => {
        const cnt = teamCounts[t];
        const isFull = (cnt >= 3);
        return `<span style="font-size:0.65rem; padding:0.1rem 0.35rem; border-radius:4px; margin-right:3px; background:${isFull ? 'rgba(239,68,68,0.2)' : 'rgba(51,65,85,0.5)'}; color:${isFull ? '#ef4444' : '#cbd5e1'}; border:1px solid ${isFull ? 'rgba(239,68,68,0.4)' : '#334155'};">${t}: ${cnt}/3</span>`;
    }).join('');

    // Indikator Hijau (Aman / Sisa) vs Merah (Defisit)
    if (hasUnselectedSlot) {
        costContainer.innerHTML = `
            <div style="background:rgba(245,158,11,0.12); border:1px solid #f59e0b; border-radius:8px; padding:0.65rem 0.85rem;">
                <div style="font-size:0.85rem; font-weight:800; color:#f59e0b;">
                    ⚠️ Masih ada slot pemain yang belum dipilih!
                </div>
                <div style="font-size:0.7rem; color:#cbd5e1; margin-top:0.25rem;">
                    Harap lengkapi semua 15 pemain (2 GK, 5 DEF, 5 MID, 3 FWD) sebelum menyimpan.
                </div>
                <div style="margin-top:0.4rem; font-size:0.7rem; color:#cbd5e1;">Kuota Klub: ${clubQuotaPills}</div>
            </div>
        `;
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.5';
    } else if (remainingBank < 0 || totalCost > 100.0) {
        const deficit = Math.abs(remainingBank).toFixed(1);
        costContainer.innerHTML = `
            <div style="background:rgba(239,68,68,0.12); border:1px solid #ef4444; border-radius:8px; padding:0.65rem 0.85rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <span style="font-size:0.75rem; color:#fca5a5; font-weight:600;">Sisa Saldo Bank:</span>
                        <div style="font-size:1.2rem; font-weight:900; color:#ef4444;">
                            🔴 -£${deficit}m <span style="font-size:0.75rem; font-weight:700; color:#f87171;">(DEFISIT / SALDO KURANG)</span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.75rem; color:#fca5a5;">Total Biaya 15 Pemain:</span>
                        <div style="font-size:0.95rem; font-weight:800; color:#ef4444;">
                            £${totalCost.toFixed(1)}m <span style="font-size:0.75rem; color:#fca5a5;">/ £100.0m</span>
                        </div>
                    </div>
                </div>
                <div style="font-size:0.7rem; color:#fca5a5; margin-top:0.35rem;">
                    ⚠️ Total biaya melebihi anggaran (£100.0m)! Ganti beberapa pemain dengan opsi lebih murah agar saldo menjadi hijau (+).
                </div>
                <div style="margin-top:0.4rem; font-size:0.7rem; color:#cbd5e1;">Kuota Klub: ${clubQuotaPills}</div>
            </div>
        `;
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.5';
    } else if (teamViolation) {
        costContainer.innerHTML = `
            <div style="background:rgba(239,68,68,0.12); border:1px solid #ef4444; border-radius:8px; padding:0.65rem 0.85rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <span style="font-size:0.75rem; color:#fca5a5; font-weight:600;">Sisa Saldo Bank:</span>
                        <div style="font-size:1.2rem; font-weight:900; color:#00ff87;">
                            🟢 +£${remainingBank.toFixed(1)}m
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.75rem; color:#fca5a5;">Total Biaya:</span>
                        <div style="font-size:0.95rem; font-weight:800; color:#f8fafc;">
                            £${totalCost.toFixed(1)}m / £100.0m
                        </div>
                    </div>
                </div>
                <div style="font-size:0.7rem; color:#ef4444; margin-top:0.35rem; font-weight:700;">
                    ⚠️ Kuota Klub Penuh: Lebih dari 3 pemain dari ${teamViolation} (${teamCounts[teamViolation]} pemain). Maksimal 3 pemain per klub!
                </div>
                <div style="margin-top:0.4rem; font-size:0.7rem; color:#cbd5e1;">Kuota Klub: ${clubQuotaPills}</div>
            </div>
        `;
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.5';
    } else {
        costContainer.innerHTML = `
            <div style="background:rgba(0,255,135,0.08); border:1px solid #00ff87; border-radius:8px; padding:0.65rem 0.85rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <span style="font-size:0.75rem; color:#94a3b8; font-weight:600;">Sisa Saldo Bank:</span>
                        <div style="font-size:1.2rem; font-weight:900; color:#00ff87;">
                            🟢 +£${remainingBank.toFixed(1)}m <span style="font-size:0.75rem; font-weight:600; color:#86efac;">(Aman / Sisa Saldo Tersedia)</span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.75rem; color:#94a3b8;">Total Biaya 15 Pemain:</span>
                        <div style="font-size:0.95rem; font-weight:800; color:#f8fafc;">
                            £${totalCost.toFixed(1)}m <span style="font-size:0.75rem; color:#64748b;">/ £100.0m</span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:0.4rem; font-size:0.7rem; color:#cbd5e1;">Kuota Klub: ${clubQuotaPills}</div>
            </div>
        `;
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
    }
}

function setBuilderRole(idx, role) {
    if (role === 'C') {
        builderPicks.forEach((p, i) => p.is_captain = (i === idx));
    } else if (role === 'VC') {
        builderPicks.forEach((p, i) => p.is_vice_captain = (i === idx));
    }
}

function prepareReplaceSlot(idx) {
    targetSlotIndex = idx;
    const requiredType = getSlotType(idx);
    const typeNames = { 1: 'Kiper (GK)', 2: 'Bek (DEF)', 3: 'Gelandang (MID)', 4: 'Penyerang (FWD)' };
    const currentName = (builderPicks[idx] && builderPicks[idx].web_name && builderPicks[idx].id > 0) ? builderPicks[idx].web_name : `Slot #${idx + 1}`;
    
    let otherCost = 0;
    builderPicks.forEach((p, i) => {
        if (i !== idx) otherCost += (p.now_cost || 0);
    });
    const maxBudgetForSlot = Math.max(4.0, Math.round((100.0 - otherCost) * 10) / 10);

    const searchInput = document.getElementById('builder-search');
    searchInput.value = '';
    searchInput.placeholder = `🔍 Pilih ${typeNames[requiredType]} (Maks £${maxBudgetForSlot}m - Pengganti ${currentName})...`;
    searchInput.focus();
    onBuilderSearch('');
}

let searchTimeout = null;
function onBuilderSearch(q) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        const container = document.getElementById('builder-search-results');
        if (targetSlotIndex === null) {
            container.style.display = 'none';
            return;
        }

        const targetType = getSlotType(targetSlotIndex);
        const typeNames = { 1: 'Kiper (GK)', 2: 'Bek (DEF)', 3: 'Gelandang (MID)', 4: 'Penyerang (FWD)' };
        
        let otherCost = 0;
        const otherTeamCounts = {};
        builderPicks.forEach((p, i) => {
            if (i !== targetSlotIndex && p.id > 0) {
                otherCost += (p.now_cost || 0);
                const t = p.team_short || 'PL';
                otherTeamCounts[t] = (otherTeamCounts[t] || 0) + 1;
            }
        });
        const maxBudgetForSlot = Math.max(4.0, Math.round((100.0 - otherCost) * 10) / 10);

        const fullTeams = Object.keys(otherTeamCounts).filter(t => otherTeamCounts[t] >= 3);
        const excludeQuery = fullTeams.join(',');

        try {
            const res = await fetch(`api/search_players.php?q=${encodeURIComponent(q)}&type=${targetType}&max_cost=${maxBudgetForSlot}&exclude_teams=${encodeURIComponent(excludeQuery)}`);
            const data = await res.json();

            if (data.status === 'success' && data.players.length > 0) {
                container.style.display = 'block';
                container.innerHTML = `
                    <div style="font-size:0.75rem; color:#00ff87; font-weight:700; margin-bottom:0.4rem; padding-bottom:0.25rem; border-bottom:1px solid #334155; display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem;">
                        <span>Pilihan ${typeNames[targetType]} Terjangkau (Maks £${maxBudgetForSlot.toFixed(1)}m):</span>
                        <span style="color:#94a3b8;">
                            ${data.players.length} Pemain 
                            ${fullTeams.length > 0 ? `<span style="color:#ef4444; margin-left:4px;">(🚫 Kuota Penuh 3/3: ${fullTeams.join(', ')})</span>` : ''}
                        </span>
                    </div>
                ` + data.players.map(p => {
                    const remainingBankAfterPick = (maxBudgetForSlot - p.now_cost).toFixed(1);
                    const isPos = (remainingBankAfterPick >= 0);
                    return `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0.5rem; border-bottom:1px solid #1e293b; cursor:pointer;" onclick="selectPlayerForSlot(${p.id}, '${p.web_name}', '${p.team}', ${p.now_cost}, ${p.element_type})">
                            <div>
                                <strong style="font-size:0.8rem; color:#f8fafc;">${p.full_name} (${p.web_name})</strong>
                                <div style="font-size:0.65rem; color:#94a3b8;">
                                    ${p.team} · <span style="color:#f8fafc; font-weight:700;">£${p.now_cost}m</span> (Sisa Saldo Bank: <strong style="color:${isPos ? '#00ff87' : '#ef4444'};">${isPos ? '+' : ''}£${remainingBankAfterPick}m</strong>) ${p.news ? `· <span style="color:#ef4444;">${p.news}</span>` : ''}
                                </div>
                            </div>
                            <button class="btn btn-primary" style="width:auto; padding:0.2rem 0.5rem; font-size:0.7rem;">Pilih</button>
                        </div>
                    `;
                }).join('');
            } else {
                container.style.display = 'block';
                container.innerHTML = `<div style="font-size:0.75rem; color:#f87171; text-align:center; padding:0.5rem;">Tidak ada pemain ${typeNames[targetType]} yang memenuhi kriteria budget & kuota klub.</div>`;
            }
        } catch (e) {
            console.error(e);
        }
    }, 200);
}

function selectPlayerForSlot(id, webName, teamShort, cost, type) {
    if (targetSlotIndex === null) {
        alert('Silakan klik tombol "Ganti" pada salah satu slot pemain terlebih dahulu.');
        return;
    }

    builderPicks[targetSlotIndex].id = id;
    builderPicks[targetSlotIndex].web_name = webName;
    builderPicks[targetSlotIndex].full_name = webName;
    builderPicks[targetSlotIndex].team_short = teamShort;
    builderPicks[targetSlotIndex].now_cost = cost;
    builderPicks[targetSlotIndex].element_type = getSlotType(targetSlotIndex);

    document.getElementById('builder-search-results').style.display = 'none';
    document.getElementById('builder-search').value = '';
    targetSlotIndex = null;
    renderBuilderSlots();
}

async function saveCustomSquad() {
    const btn = document.getElementById('btn-save-builder');
    btn.disabled = true;
    btn.innerText = 'Menyimpan...';

    // Cek apakah ada slot kosong
    if (builderPicks.some(p => p.id === 0)) {
        alert('Masih ada slot pemain yang belum dipilih. Harap pilih semua 15 pemain!');
        btn.disabled = false;
        btn.innerText = '💾 Simpan 15 Pemain';
        return;
    }

    const picksPayload = builderPicks.map(p => ({
        element_id: p.id,
        is_captain: p.is_captain ? 1 : 0,
        is_vice_captain: p.is_vice_captain ? 1 : 0
    }));

    try {
        const res = await fetch('api/save_squad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ picks: picksPayload })
        });
        const data = await res.json();
        if (data.status === 'success') {
            closeModal('modal-squad-builder');
            alert(`✅ ${data.message}`);
            fetchTeamData();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = '💾 Simpan 15 Pemain';
    }
}

function setupEventListeners() {
    const ytForm = document.getElementById('form-yt');
    if (ytForm) {
        ytForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const url = document.getElementById('yt-url').value;
            const buys = document.getElementById('yt-buys').value.split(',').map(s => s.trim()).filter(Boolean);
            const sells = document.getElementById('yt-sells').value.split(',').map(s => s.trim()).filter(Boolean);
            const captains = document.getElementById('yt-caps').value.split(',').map(s => s.trim()).filter(Boolean);

            try {
                const res = await fetch('api/save_youtube.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        gameweek: appState.gameweek,
                        video_url: url,
                        buys: buys,
                        sells: sells,
                        captains: captains
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeModal('modal-yt');
                    fetchTeamData();
                } else {
                    alert('Gagal: ' + data.message);
                }
            } catch (err) {
                alert('Error submitting: ' + err.message);
            }
        });
    }

    const btnExecute = document.getElementById('btn-apply-fpl');
    if (btnExecute) {
        btnExecute.addEventListener('click', () => {
            openModal('modal-confirm');
        });
    }

    const btnConfirmExecute = document.getElementById('btn-confirm-apply');
    if (btnConfirmExecute) {
        btnConfirmExecute.addEventListener('click', async () => {
            btnConfirmExecute.disabled = true;
            btnConfirmExecute.innerText = 'Mengeksekusi...';
            try {
                const res = await fetch('api/trigger_execute.php', { method: 'POST' });
                const data = await res.json();
                closeModal('modal-confirm');
                alert(`Eksekusi Selesai! Status: ${data.status}\nPesan: ${data.details || data.message || 'Berhasil'}`);
                fetchTeamData();
            } catch (err) {
                alert('Eksekusi gagal: ' + err.message);
            } finally {
                btnConfirmExecute.disabled = false;
                btnConfirmExecute.innerText = 'Ya, Terapkan Sekarang';
            }
        });
    }
}

function updateCountdown() {
    if (!appState.deadlineTime) return;

    const now = new Date();
    const diffMs = appState.deadlineTime - now;

    if (diffMs <= 0) {
        document.getElementById('stat-timer').innerText = 'Deadline Closed';
        document.getElementById('stat-fallback-time').innerText = 'Expired';
        return;
    }

    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const mins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    const secs = Math.floor((diffMs % (1000 * 60)) / 1000);

    document.getElementById('stat-timer').innerText = `${hours}j ${mins}m ${secs}s`;

    const fallbackDiffMs = diffMs - (30 * 60 * 1000);
    if (fallbackDiffMs > 0) {
        const fbHours = Math.floor(fallbackDiffMs / (1000 * 60 * 60));
        const fbMins = Math.floor((fallbackDiffMs % (1000 * 60 * 60)) / (1000 * 60));
        document.getElementById('stat-fallback-time').innerText = `Auto-Run in: ${fbHours}j ${fbMins}m`;
    } else {
        document.getElementById('stat-fallback-time').innerText = `⚡ Fallback Active!`;
    }
}

function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
    if (id === 'modal-logs') fetchLogs();
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}

async function fetchLogs() {
    const tbody = document.getElementById('log-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Memuat riwayat...</td></tr>';
    try {
        const res = await fetch('api/get_logs.php');
        const data = await res.json();
        if (data.logs && data.logs.length > 0) {
            tbody.innerHTML = data.logs.map(log => `
                <tr>
                    <td>GW${log.gameweek}</td>
                    <td><strong>${log.execution_type}</strong></td>
                    <td><span style="color:${log.status === 'SUCCESS' ? '#00ff87' : '#ef4444'}; font-weight:700;">${log.status}</span></td>
                    <td>${log.transfer_out_name ? `Out: ${log.transfer_out_name} ➔ In: ${log.transfer_in_name}` : 'No transfer'}</td>
                    <td>${log.executed_at}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#94a3b8;">Belum ada riwayat eksekusi.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#ef4444;">Gagal memuat riwayat.</td></tr>';
    }
}

function showLoading(show) {
    const el = document.getElementById('loading-overlay');
    if (el) el.style.display = show ? 'flex' : 'none';
}
