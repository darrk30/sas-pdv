<script defer src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
/* ── Overlay ─────────────────────────────────────────────────────────────── */
.bcs-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}
@media (min-width: 600px) {
    .bcs-overlay {
        align-items: center;
        padding: 1rem;
    }
}
.bcs-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
}
/* ── Modal ───────────────────────────────────────────────────────────────── */
.bcs-modal {
    position: relative;
    width: 100%;
    max-width: 400px;
    background: #ffffff;
    border-radius: 1rem 1rem 0 0;
    box-shadow: 0 25px 50px rgba(0,0,0,0.35);
    overflow: hidden;
}
@media (min-width: 600px) {
    .bcs-modal {
        border-radius: 1rem;
    }
}
/* ── Drag handle (solo mobile) ───────────────────────────────────────────── */
.bcs-drag {
    display: flex;
    justify-content: center;
    padding: 0.75rem 0 0.25rem;
}
@media (min-width: 600px) {
    .bcs-drag { display: none; }
}
.bcs-drag__pill {
    width: 2.5rem;
    height: 0.25rem;
    border-radius: 9999px;
    background: #d1d5db;
}
/* ── Header ──────────────────────────────────────────────────────────────── */
.bcs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f0f0f0;
    padding: 0.75rem 1.25rem;
}
.bcs-header__left {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}
.bcs-header__icon {
    width: 1.25rem;
    height: 1.25rem;
    color: #6366f1;
    flex-shrink: 0;
}
.bcs-header__texts {
    display: flex;
    flex-direction: column;
}
.bcs-header__title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
    line-height: 1.3;
}
.bcs-header__sub {
    font-size: 0.7rem;
    color: #6b7280;
    margin: 0.1rem 0 0;
    line-height: 1.2;
}
.bcs-close {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    min-height: 40px;
    padding: 0.5rem;
    border: none;
    background: none;
    cursor: pointer;
    color: #9ca3af;
    border-radius: 0.625rem;
    transition: background 0.15s, color 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.bcs-close:hover  { background: #f3f4f6; color: #4b5563; }
.bcs-close:active { background: #e5e7eb; }
.bcs-close svg    { width: 1.25rem; height: 1.25rem; pointer-events: none; }
/* ── Body ────────────────────────────────────────────────────────────────── */
.bcs-body {
    padding: 1rem;
}
/* ── Visor de cámara (html5-qrcode se monta aquí) ───────────────────────── */
#barcode-reader-preview {
    position: relative;
    overflow: hidden;
    background: #000;
    border-radius: 0.75rem;
    min-height: 220px;
    width: 100%;
}
#barcode-reader-preview video {
    width: 100% !important;
    height: auto !important;
    display: block !important;
    object-fit: cover;
}
/* Ocultar UI que inyecta html5-qrcode que no necesitamos */
#barcode-reader-preview canvas  { display: none !important; }
#barcode-reader-preview img     { display: none !important; }
#barcode-reader-preview span    { display: none !important; }
#barcode-reader-preview select  { display: none !important; }
#barcode-reader-preview a       { display: none !important; }
#barcode-reader-preview > div > button { display: none !important; }
/* Marco de escaneo — estilizar borde del área de lectura */
#barcode-reader-preview div[style*="border"] {
    border-color: rgba(99,102,241,0.9) !important;
    border-width: 2px !important;
    box-sizing: border-box !important;
}
/* ── Textos de estado ────────────────────────────────────────────────────── */
.bcs-status {
    margin-top: 0.75rem;
    text-align: center;
    font-size: 0.75rem;
    color: #6b7280;
}
/* ── Botón cerrar ────────────────────────────────────────────────────────── */
.bcs-btn-cerrar {
    display: block;
    margin-top: 0.75rem;
    width: 100%;
    border: none;
    border-radius: 0.75rem;
    background: #f3f4f6;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    -webkit-tap-highlight-color: transparent;
}
.bcs-btn-cerrar:hover  { background: #e5e7eb; }
.bcs-btn-cerrar:active { transform: scale(0.98); }
/* ── Transiciones Alpine ─────────────────────────────────────────────────── */
[x-cloak] { display: none !important; }
</style>

<script>
window._barcodeScanner = function () {
    return {
        open: false,
        scanning: false,
        isPdv: false,
        targetPath: null,
        _scanner: null,
        _starting: false,
        _lastCode: null,
        _lastScanTime: 0,

        init() {
            window.addEventListener('open-barcode-scanner', async (e) => {
                if (this.open || this._starting) return;

                this.targetPath = e.detail?.path ?? null;
                this.isPdv      = this.targetPath === '__pdv__';

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    Livewire.dispatch('camera-not-available');
                    return;
                }

                try {
                    var devices = await navigator.mediaDevices.enumerateDevices();
                    if (!devices.some(function(d) { return d.kind === 'videoinput'; })) {
                        Livewire.dispatch('camera-not-available');
                        return;
                    }
                } catch (_) {}

                this._lastCode     = null;
                this._lastScanTime = 0;
                this.open          = true;
                this.$nextTick(function() { this._startCamera(); }.bind(this));
            });
        },

        _playBeep() {
            try {
                var ctx  = new (window.AudioContext || window.webkitAudioContext)();
                var osc  = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'square';
                osc.frequency.value = 1200;
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
            } catch (_) {}
        },

        _startCamera() {
            var id = 'barcode-reader-preview';
            if (!document.getElementById(id) || this._scanner || this._starting) return;

            this._starting = true;

            var FORMATS = [
                window.Html5QrcodeSupportedFormats.EAN_13,
                window.Html5QrcodeSupportedFormats.EAN_8,
                window.Html5QrcodeSupportedFormats.CODE_128,
                window.Html5QrcodeSupportedFormats.CODE_39,
                window.Html5QrcodeSupportedFormats.UPC_A,
                window.Html5QrcodeSupportedFormats.UPC_E,
                window.Html5QrcodeSupportedFormats.QR_CODE,
            ];

            this._scanner = new window.Html5Qrcode(id, { formatsToSupport: FORMATS, verbose: false });

            var self   = this;
            var cfg    = { fps: 10, qrbox: { width: 240, height: 100 } };
            var onOk   = function(code) { self._onSuccess(code); };
            var onErr  = function() {};

            var tryStart = function(constraints) {
                return self._scanner.start(constraints, cfg, onOk, onErr);
            };

            tryStart({ facingMode: { exact: 'environment' } })
                .catch(function() { return tryStart({ facingMode: 'environment' }); })
                .catch(function() { return tryStart({ facingMode: 'user' }); })
                .then(function() {
                    self.scanning  = true;
                    self._starting = false;
                })
                .catch(function(err) {
                    self.open      = false;
                    self._scanner  = null;
                    self._starting = false;
                    Livewire.dispatch('camera-not-available');
                    console.error('[BarcodeScanner]', err);
                });
        },

        _onSuccess(code) {
            var now = Date.now();
            // Ignorar mismo código leído dentro de 2 segundos (lecturas duplicadas del sensor)
            if (code === this._lastCode && now - this._lastScanTime < 2000) return;
            this._lastCode     = code;
            this._lastScanTime = now;

            this._playBeep();

            if (!this.targetPath) return;

            if (this.isPdv) {
                // PDV: mantener modal abierto, despachar al carrito
                Livewire.dispatch('pdv-barcode', { code: code });
            } else {
                // Formularios: cerrar modal y rellenar campo
                var formPath = this.targetPath.replace(/^data\./, '');
                this._stop(true);
                Livewire.dispatch('barcode-result', { path: formPath, code: code });
            }
        },

        _stop(andClose) {
            if (andClose === undefined) andClose = true;
            this._starting = false;
            if (this._scanner && this.scanning) {
                this.scanning = false;
                var self      = this;
                this._scanner.stop()
                    .catch(function() {})
                    .finally(function() {
                        self._scanner = null;
                        if (andClose) self.open = false;
                    });
            } else {
                this._scanner = null;
                if (andClose) this.open = false;
            }
        },

        close() { this._stop(true); },
    };
};
</script>

<div x-data="window._barcodeScanner()" wire:ignore x-cloak>

    <template x-teleport="body">
        <div
            class="bcs-overlay"
            x-show="open"
            x-transition:enter="bcs-fade-enter"
            x-transition:enter-start="bcs-fade-start"
            x-transition:enter-end="bcs-fade-end"
            x-transition:leave="bcs-fade-leave"
            x-transition:leave-start="bcs-fade-end"
            x-transition:leave-end="bcs-fade-start"
            style="display:none"
        >
            {{-- Backdrop --}}
            <div class="bcs-backdrop" @click="close()"></div>

            {{-- Modal --}}
            <div class="bcs-modal">

                {{-- Drag handle visible solo en mobile --}}
                <div class="bcs-drag">
                    <div class="bcs-drag__pill"></div>
                </div>

                {{-- Header --}}
                <div class="bcs-header">
                    <div class="bcs-header__left">
                        <svg class="bcs-header__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                        </svg>
                        <div class="bcs-header__texts">
                            <p class="bcs-header__title">Escanear código de barras</p>
                            <p class="bcs-header__sub" x-show="isPdv">Modo PDV — escanea varios productos seguidos</p>
                        </div>
                    </div>
                    <button type="button" class="bcs-close" @click="close()" aria-label="Cerrar">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Visor --}}
                <div class="bcs-body">
                    <div id="barcode-reader-preview" wire:ignore></div>

                    <p class="bcs-status" x-show="scanning">Apunta la cámara al código de barras</p>
                    <p class="bcs-status" x-show="!scanning && open" style="color:#9ca3af">Iniciando cámara...</p>

                    <button type="button" class="bcs-btn-cerrar" @click="close()">Cerrar</button>
                </div>

            </div>
        </div>
    </template>

</div>

<style>
/* Transiciones del overlay */
.bcs-fade-enter { transition: opacity 0.2s ease; }
.bcs-fade-leave { transition: opacity 0.15s ease; }
.bcs-fade-start { opacity: 0; }
.bcs-fade-end   { opacity: 1; }
</style>
