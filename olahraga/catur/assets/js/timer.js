/**
 * assets/js/timer.js
 * High-Precision Chess Clock Engine dengan Web Audio API & MySQL Auto-Recovery
 */

class ChessClock {
    constructor() {
        this.mejaId = window.MEJA_ID || 0;
        this.data = null;

        // State Timer
        this.whiteTimeMs = 300000;
        this.blackTimeMs = 300000;
        this.incrementMs = 0;
        this.status = 'standby'; // 'standby', 'running', 'paused', 'finished'
        this.turn = 'putih';     // 'putih', 'hitam'
        this.moves = 0;
        this.winner = 'belum';
        this.endReason = '';

        // Internal Animation & Timing
        this.lastFrameTime = null;
        this.animationFrameId = null;
        this.lastSyncTime = 0;
        this.isSyncing = false;
        this.soundEnabled = true;

        // Audio Context
        this.audioCtx = null;

        // DOM Elements
        this.initDOMElements();
        this.initAudioContext();
        this.bindEvents();
        this.loadInitialState();
    }

    initDOMElements() {
        this.sideWhite = document.getElementById('sideWhite');
        this.sideBlack = document.getElementById('sideBlack');
        this.digitsWhite = document.getElementById('digitsWhite');
        this.digitsBlack = document.getElementById('digitsBlack');
        this.movesWhite = document.getElementById('movesWhite');
        this.movesBlack = document.getElementById('movesBlack');
        this.nameWhite = document.getElementById('nameWhite');
        this.nameBlack = document.getElementById('nameBlack');
        this.incWhite = document.getElementById('incWhite');
        this.incBlack = document.getElementById('incBlack');

        this.btnPlayPause = document.getElementById('btnPlayPause');
        this.btnReset = document.getElementById('btnReset');
        this.btnRotate = document.getElementById('btnRotate');
        this.btnSound = document.getElementById('btnSound');
        this.btnFullscreen = document.getElementById('btnFullscreen');
        this.btnMenu = document.getElementById('btnMenu');
        this.syncDot = document.getElementById('syncDot');

        // Modal Elements
        this.modalMenu = document.getElementById('modalMenu');
        this.modalResult = document.getElementById('modalResult');
        this.flagOverlay = document.getElementById('flagOverlay');
        this.flagTitle = document.getElementById('flagTitle');
        this.flagDesc = document.getElementById('flagDesc');
    }

    initAudioContext() {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            this.audioCtx = new AudioContextClass();
        }
    }

    playClickSound() {
        if (!this.soundEnabled || !this.audioCtx) return;
        try {
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            
            // Suara klik jam mekanik renyah
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(800, this.audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(120, this.audioCtx.currentTime + 0.04);

            gain.gain.setValueAtTime(0.4, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + 0.04);

            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.04);
        } catch (e) {
            // Abaikan jika browser memblokir audio sebelum interaksi
        }
    }

    playAlarmSound() {
        if (!this.soundEnabled || !this.audioCtx) return;
        try {
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            const now = this.audioCtx.currentTime;
            
            // Bunyi alarm 3 beep cepat saat waktu habis
            for (let i = 0; i < 3; i++) {
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();
                const startTime = now + (i * 0.15);

                osc.type = 'square';
                osc.frequency.setValueAtTime(880, startTime);

                gain.gain.setValueAtTime(0.3, startTime);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.1);

                osc.connect(gain);
                gain.connect(this.audioCtx.destination);

                osc.start(startTime);
                osc.stop(startTime + 0.1);
            }
        } catch (e) {}
    }

    triggerHaptic(duration = 25) {
        if (navigator.vibrate) {
            try {
                navigator.vibrate(duration);
            } catch (e) {}
        }
    }

    bindEvents() {
        // Klik sisi Putih
        this.sideWhite.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            this.handlePlayerAction('putih');
        });

        // Klik sisi Hitam
        this.sideBlack.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            this.handlePlayerAction('hitam');
        });

        // Tombol Play / Pause
        this.btnPlayPause.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePlayPause();
        });

        // Tombol Rotate Sisi Hitam
        this.btnRotate.addEventListener('click', (e) => {
            e.stopPropagation();
            this.sideBlack.classList.toggle('rotated');
            this.triggerHaptic(15);
        });

        // Tombol Toggle Sound
        this.btnSound.addEventListener('click', (e) => {
            e.stopPropagation();
            this.soundEnabled = !this.soundEnabled;
            this.btnSound.textContent = this.soundEnabled ? '🔊' : '🔇';
            this.triggerHaptic(15);
        });

        // Tombol Fullscreen
        this.btnFullscreen.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleFullscreen();
        });

        // Tombol Menu / Modal
        this.btnMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            if (this.status === 'running') {
                this.pauseClock();
            }
            this.modalMenu.classList.add('active');
        });

        // Keyboard Shortcut untuk laptop / PC
        window.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            if (e.code === 'Space') {
                e.preventDefault();
                if (this.status === 'standby' || this.status === 'paused') {
                    this.startClock();
                } else if (this.status === 'running') {
                    // Pindah giliran pemain saat ini
                    this.handlePlayerAction(this.turn);
                }
            } else if (e.code === 'ArrowDown' || e.code === 'KeyZ') {
                e.preventDefault();
                this.handlePlayerAction('putih');
            } else if (e.code === 'ArrowUp' || e.code === 'KeyM') {
                e.preventDefault();
                this.handlePlayerAction('hitam');
            } else if (e.code === 'KeyP') {
                e.preventDefault();
                this.togglePlayPause();
            }
        });
    }

    async loadInitialState() {
        try {
            const res = await fetch(`api/get_meja.php?id=${this.mejaId}`);
            const json = await res.json();

            if (json.success && json.data) {
                this.data = json.data;
                this.whiteTimeMs = parseInt(this.data.sisa_waktu_putih_ms, 10);
                this.blackTimeMs = parseInt(this.data.sisa_waktu_hitam_ms, 10);
                this.incrementMs = (parseInt(this.data.time_increment_seconds, 10) || 0) * 1000;
                this.status = this.data.status;
                this.turn = this.data.giliran || 'putih';
                this.moves = parseInt(this.data.jumlah_langkah, 10) || 0;
                this.winner = this.data.pemenang || 'belum';
                this.endReason = this.data.keterangan_selesai || '';

                this.updateUIPlayerMeta();
                this.updateClockDisplay();
                this.updateTurnClasses();
                this.updatePlayPauseButton();

                // Auto-Recovery: Jika status di database sedang 'running', langsung lanjutkan
                if (this.status === 'running') {
                    this.startTimingLoop();
                } else if (this.status === 'finished') {
                    this.showFlagFallOverlay(this.winner, this.endReason);
                }
            }
        } catch (e) {
            console.error("Gagal memuat state awal meja:", e);
        }
    }

    updateUIPlayerMeta() {
        if (!this.data) return;
        this.nameWhite.textContent = this.data.nama_putih;
        this.nameBlack.textContent = this.data.nama_hitam;

        const incText = this.incrementMs > 0 ? `+${this.incrementMs / 1000}s` : '';
        this.incWhite.textContent = incText;
        this.incBlack.textContent = incText;

        this.movesWhite.textContent = `Langkah: ${Math.floor((this.moves + 1) / 2)}`;
        this.movesBlack.textContent = `Langkah: ${Math.floor(this.moves / 2)}`;
    }

    handlePlayerAction(player) {
        if (this.status === 'finished') return;

        // Jika dalam kondisi standby / paused, klik pertama akan memulai pertandingan
        if (this.status === 'standby' || this.status === 'paused') {
            if (this.status === 'standby' && player === 'hitam') {
                // Putih harus melangkah pertama di awal game catur
                this.turn = 'putih';
            } else {
                this.turn = player === 'putih' ? 'hitam' : 'putih';
            }
            this.startClock();
            this.playClickSound();
            this.triggerHaptic(40);
            return;
        }

        // Hanya pemain yang gilirannya sedang aktif yang bisa menekan jamnya
        if (this.status === 'running' && this.turn === player) {
            // Tambahkan Fischer Increment ke pemain yang baru saja selesai melangkah
            if (player === 'putih') {
                this.whiteTimeMs += this.incrementMs;
                this.turn = 'hitam';
            } else {
                this.blackTimeMs += this.incrementMs;
                this.turn = 'putih';
            }

            this.moves++;
            this.playClickSound();
            this.triggerHaptic(30);

            this.updateTurnClasses();
            this.updateClockDisplay();
            this.updateUIPlayerMeta();
            this.syncStateToServer();
        }
    }

    startClock() {
        if (this.status === 'finished') return;
        this.status = 'running';
        this.lastFrameTime = performance.now();
        this.updatePlayPauseButton();
        this.updateTurnClasses();
        this.startTimingLoop();
        this.syncStateToServer();
    }

    pauseClock() {
        if (this.status !== 'running') return;
        this.status = 'paused';
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
        this.updatePlayPauseButton();
        this.updateTurnClasses();
        this.syncStateToServer();
    }

    togglePlayPause() {
        if (this.status === 'running') {
            this.pauseClock();
            this.triggerHaptic(20);
        } else if (this.status === 'standby' || this.status === 'paused') {
            this.startClock();
            this.triggerHaptic(30);
        }
    }

    startTimingLoop() {
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
        }

        const loop = (now) => {
            if (this.status !== 'running') return;

            if (this.lastFrameTime) {
                const delta = now - this.lastFrameTime;

                if (this.turn === 'putih') {
                    this.whiteTimeMs -= delta;
                    if (this.whiteTimeMs <= 0) {
                        this.whiteTimeMs = 0;
                        this.handleTimeOut('putih');
                        return;
                    }
                } else {
                    this.blackTimeMs -= delta;
                    if (this.blackTimeMs <= 0) {
                        this.blackTimeMs = 0;
                        this.handleTimeOut('hitam');
                        return;
                    }
                }
            }

            this.lastFrameTime = now;
            this.updateClockDisplay();

            // Periodic Heartbeat Sync setiap 3 detik
            if (Date.now() - this.lastSyncTime > 3000) {
                this.syncStateToServer();
            }

            this.animationFrameId = requestAnimationFrame(loop);
        };

        this.lastFrameTime = performance.now();
        this.animationFrameId = requestAnimationFrame(loop);
    }

    handleTimeOut(playerRunOutOfTime) {
        this.status = 'finished';
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }

        this.winner = playerRunOutOfTime === 'putih' ? 'hitam' : 'putih';
        this.endReason = 'Waktu Habis';

        this.playAlarmSound();
        this.triggerHaptic([100, 50, 100, 50, 200]);

        this.updateClockDisplay();
        this.updateTurnClasses();
        this.updatePlayPauseButton();
        this.showFlagFallOverlay(this.winner, this.endReason);
        this.syncStateToServer();
    }

    showFlagFallOverlay(winner, reason) {
        const winnerName = winner === 'putih' ? (this.data ? this.data.nama_putih : 'Pemain Putih') : 
                           winner === 'hitam' ? (this.data ? this.data.nama_hitam : 'Pemain Hitam') : 'Remis';
        
        if (winner === 'remis') {
            this.flagTitle.textContent = '🤝 Pertandingan Remis';
            this.flagDesc.textContent = reason || 'Hasil seri disepakati';
        } else {
            this.flagTitle.textContent = `🏆 ${winnerName} Menang!`;
            this.flagDesc.textContent = `Pemenang: ${winner.toUpperCase()} (${reason || 'Selesai'})`;
        }

        this.flagOverlay.style.display = 'flex';
    }

    hideFlagOverlay() {
        this.flagOverlay.style.display = 'none';
    }

    updateClockDisplay() {
        this.digitsWhite.textContent = this.formatDigitTime(this.whiteTimeMs);
        this.digitsBlack.textContent = this.formatDigitTime(this.blackTimeMs);

        // Low time indicators (< 30s)
        this.sideWhite.classList.toggle('low-time', this.whiteTimeMs < 30000 && this.whiteTimeMs > 10000);
        this.sideWhite.classList.toggle('critical-time', this.whiteTimeMs <= 10000);

        this.sideBlack.classList.toggle('low-time', this.blackTimeMs < 30000 && this.blackTimeMs > 10000);
        this.sideBlack.classList.toggle('critical-time', this.blackTimeMs <= 10000);
    }

    formatDigitTime(ms) {
        if (ms < 0) ms = 0;
        const totalSec = Math.floor(ms / 1000);
        const hours = Math.floor(totalSec / 3600);
        const minutes = Math.floor((totalSec % 3600) / 60);
        const seconds = totalSec % 60;
        const tenths = Math.floor((ms % 1000) / 100);

        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
        
        // Jika sisa waktu di bawah 10 detik, tampilkan digit desimal (misal 00:08.4)
        if (totalSec < 10) {
            return `00:0${seconds}.${tenths}`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    updateTurnClasses() {
        if (this.status === 'running') {
            this.sideWhite.classList.toggle('active', this.turn === 'putih');
            this.sideBlack.classList.toggle('active', this.turn === 'hitam');
        } else {
            this.sideWhite.classList.remove('active');
            this.sideBlack.classList.remove('active');
        }
    }

    updatePlayPauseButton() {
        if (this.status === 'running') {
            this.btnPlayPause.textContent = '⏸️';
            this.btnPlayPause.title = 'Jeda Pertandingan (Pause)';
        } else {
            this.btnPlayPause.textContent = '▶️';
            this.btnPlayPause.title = 'Mulai Pertandingan (Play)';
        }
    }

    async syncStateToServer() {
        if (this.isSyncing) return;
        this.isSyncing = true;
        this.lastSyncTime = Date.now();
        this.setSyncDotState('saving');

        const payload = {
            id: this.mejaId,
            sisa_waktu_putih_ms: Math.round(this.whiteTimeMs),
            sisa_waktu_hitam_ms: Math.round(this.blackTimeMs),
            status: this.status,
            giliran: this.turn,
            jumlah_langkah: this.moves,
            pemenang: this.winner,
            keterangan_selesai: this.endReason
        };

        try {
            const res = await fetch('api/sync_timer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                this.setSyncDotState('synced');
            } else {
                this.setSyncDotState('offline');
            }
        } catch (e) {
            this.setSyncDotState('offline');
        } finally {
            this.isSyncing = false;
        }
    }

    setSyncDotState(state) {
        if (!this.syncDot) return;
        this.syncDot.className = 'sync-dot';
        if (state === 'saving') this.syncDot.classList.add('saving');
        else if (state === 'offline') this.syncDot.classList.add('offline');
    }

    // Modal & Control Helpers
    adjustPenaltyTime(player, secondsToAdd) {
        const msToAdd = secondsToAdd * 1000;
        if (player === 'putih') {
            this.whiteTimeMs = Math.max(1000, this.whiteTimeMs + msToAdd);
        } else {
            this.blackTimeMs = Math.max(1000, this.blackTimeMs + msToAdd);
        }
        this.updateClockDisplay();
        this.syncStateToServer();
        this.closeAllModals();
    }

    setManualResult(winner, reason) {
        this.status = 'finished';
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }

        this.winner = winner;
        this.endReason = reason;

        this.updatePlayPauseButton();
        this.updateTurnClasses();
        this.showFlagFallOverlay(this.winner, this.endReason);
        this.syncStateToServer();
        this.closeAllModals();
    }

    async resetMatch() {
        if (!confirm('Yakin ingin mereset waktu jam catur meja ini ke awal?')) return;
        
        try {
            const res = await fetch('api/action_meja.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset', id: this.mejaId })
            });
            const data = await res.json();
            if (data.success) {
                this.hideFlagOverlay();
                this.closeAllModals();
                await this.loadInitialState();
            }
        } catch (e) {
            alert('Gagal mereset pertandingan');
        }
    }

    closeAllModals() {
        document.querySelectorAll('.timer-modal').forEach(m => m.classList.remove('active'));
    }

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
        }
    }
}

// Inisialisasi Jam Catur saat halaman siap
let chessClockInstance = null;
document.addEventListener('DOMContentLoaded', () => {
    chessClockInstance = new ChessClock();
});
