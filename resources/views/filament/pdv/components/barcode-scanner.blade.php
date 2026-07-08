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
    .bcs-overlay { align-items: center; padding: 1rem; }
}
.bcs-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.75);
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
    .bcs-modal { border-radius: 1rem; }
}
/* ── Drag handle ─────────────────────────────────────────────────────────── */
.bcs-drag {
    display: flex;
    justify-content: center;
    padding: 0.75rem 0 0.25rem;
}
@media (min-width: 600px) { .bcs-drag { display: none; } }
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
.bcs-body { padding: 1rem; }
/* ── Visor de cámara ─────────────────────────────────────────────────────── */
#barcode-reader-preview {
    position: relative;
    overflow: hidden;
    background: #000;
    border-radius: 0.75rem;
    min-height: 180px;
    width: 100%;
}
#barcode-reader-preview video {
    width: 100% !important;
    height: auto !important;
    display: block !important;
    object-fit: cover;
}
/* Ocultar UI propia de html5-qrcode que no necesitamos */
#barcode-reader-preview canvas        { display: none !important; }
#barcode-reader-preview img           { display: none !important; }
#barcode-reader-preview span          { display: none !important; }
#barcode-reader-preview select        { display: none !important; }
#barcode-reader-preview a             { display: none !important; }
#barcode-reader-preview > div > button { display: none !important; }
/* Marco del área de lectura */
#barcode-reader-preview div[style*="border"] {
    border-color: rgba(99,102,241,0.9) !important;
    border-width: 2px !important;
    box-sizing: border-box !important;
}
/* ── Estado: iniciando / apuntando ──────────────────────────────────────── */
.bcs-status {
    margin-top: 0.6rem;
    text-align: center;
    font-size: 0.75rem;
    color: #6b7280;
}
.bcs-status--muted { color: #9ca3af; }
/* ── Área de código detectado (PDV) ─────────────────────────────────────── */
.bcs-detected {
    margin-top: 0.75rem;
    border: 1.5px solid #6366f1;
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f5f3ff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.bcs-detected__label {
    font-size: 0.7rem;
    color: #6366f1;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0 0 0.2rem;
}
.bcs-detected__code {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e1b4b;
    font-family: monospace;
    letter-spacing: 0.05em;
    word-break: break-all;
}
.bcs-detected__info { flex: 1; min-width: 0; }
.bcs-detected__btn {
    flex-shrink: 0;
    border: none;
    border-radius: 0.625rem;
    background: #6366f1;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.625rem 0.875rem;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    -webkit-tap-highlight-color: transparent;
    white-space: nowrap;
}
.bcs-detected__btn:hover  { background: #4f46e5; }
.bcs-detected__btn:active { transform: scale(0.96); }
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
.bcs-fade-enter { transition: opacity 0.2s ease; }
.bcs-fade-leave { transition: opacity 0.15s ease; }
.bcs-fade-start { opacity: 0; }
.bcs-fade-end   { opacity: 1; }
</style>

<script>
window._barcodeScanner = function () {
    return {
        open: false,
        scanning: false,
        isPdv: false,
        targetPath: null,
        pendingCode: null,      // Código detectado esperando confirmación (PDV)
        _scanner: null,
        _starting: false,
        _lastDetected: null,    // Último código detectado (para debounce visual)

        init() {
            window.addEventListener('open-barcode-scanner', function(e) {
                if (this.open || this._starting) return;

                this.targetPath  = e.detail && e.detail.path ? e.detail.path : null;
                this.isPdv       = this.targetPath === '__pdv__';
                this.pendingCode = null;
                this._lastDetected = null;

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    Livewire.dispatch('camera-not-available');
                    return;
                }

                var self = this;
                navigator.mediaDevices.enumerateDevices()
                    .then(function(devices) {
                        if (!devices.some(function(d) { return d.kind === 'videoinput'; })) {
                            Livewire.dispatch('camera-not-available');
                            return;
                        }
                        self.open = true;
                        self.$nextTick(function() { self._startCamera(); });
                    })
                    .catch(function() {
                        // Si falla la enumeración, intentamos igual
                        self.open = true;
                        self.$nextTick(function() { self._startCamera(); });
                    });
            }.bind(this));
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

        _clearPreview() {
            // Forzar limpieza del contenedor para evitar que queden
            // elementos residuales de la sesión anterior de html5-qrcode
            var el = document.getElementById('barcode-reader-preview');
            if (el) el.innerHTML = '';
        },

        _startCamera() {
            var id = 'barcode-reader-preview';
            if (!document.getElementById(id) || this._scanner || this._starting) return;

            // Limpiar residuos de la sesión anterior ANTES de crear la nueva instancia
            this._clearPreview();
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

            // Cuadro rectangular ancho (ideal para códigos de barras lineales)
            var container = document.getElementById(id);
            var w = container ? Math.min(container.offsetWidth - 40, 280) : 240;
            var cfg = { fps: 10, qrbox: { width: w, height: 70 } };

            var self  = this;
            var onOk  = function(code) { self._onDetect(code); };
            var onErr = function() {};

            var tryStart = function(constraints) {
                return self._scanner.start(constraints, cfg, onOk, onErr);
            };

            // Resolution hint (ideal = soft) nudges browser to pick main camera over ultra-wide
            tryStart({ facingMode: { exact: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } })
                .catch(function() { return tryStart({ facingMode: { exact: 'environment' } }); })
                .catch(function() { return tryStart({ facingMode: 'environment' }); })
                .catch(function() { return tryStart({ facingMode: 'user' }); })
                .then(function() {
                    self.scanning  = true;
                    self._starting = false;
                })
                .catch(function(err) {
                    self._clearPreview();
                    self.open      = false;
                    self._scanner  = null;
                    self._starting = false;
                    Livewire.dispatch('camera-not-available');
                    console.error('[BarcodeScanner]', err);
                });
        },

        _onDetect(code) {
            if (this.isPdv) {
                // PDV: mostrar código detectado, esperar confirmación manual
                if (code !== this._lastDetected) {
                    this._lastDetected = code;
                    this.pendingCode   = code;
                }
            } else {
                // Formularios: cerrar modal y rellenar el campo automáticamente
                var formPath = this.targetPath.replace(/^data\./, '');
                this._stop(true);
                Livewire.dispatch('barcode-result', { path: formPath, code: code });
            }
        },

        confirmarCodigo() {
            if (!this.pendingCode) return;
            var code = this.pendingCode;
            this.pendingCode   = null;
            this._lastDetected = null;
            this._playBeep();
            Livewire.dispatch('pdv-barcode', { code: code });
        },

        _stop(andClose) {
            if (andClose === undefined) andClose = true;
            this._starting   = false;
            this.pendingCode = null;
            this._lastDetected = null;
            var self = this;

            var cleanup = function() {
                self._clearPreview();
                self._scanner = null;
                if (andClose) self.open = false;
            };

            if (this._scanner && this.scanning) {
                this.scanning = false;
                this._scanner.stop()
                    .then(function() {
                        // Llamar clear() de la librería para que limpie sus listeners internos
                        try { if (self._scanner) self._scanner.clear(); } catch (_) {}
                    })
                    .catch(function() {})
                    .finally(cleanup);
            } else {
                try { if (this._scanner) this._scanner.clear(); } catch (_) {}
                cleanup();
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

                {{-- Drag handle (mobile) --}}
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
                        <div>
                            <p class="bcs-header__title">Escanear código de barras</p>
                            <p class="bcs-header__sub" x-show="isPdv">Modo PDV — confirma cada producto antes de agregar</p>
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

                    {{-- Mensaje de estado: iniciando --}}
                    <p class="bcs-status bcs-status--muted" x-show="!scanning && open">Iniciando cámara...</p>

                    {{-- PDV: código detectado + botón confirmar --}}
                    <div class="bcs-detected" x-show="isPdv && pendingCode">
                        <div class="bcs-detected__info">
                            <p class="bcs-detected__label">Código detectado</p>
                            <p class="bcs-detected__code" x-text="pendingCode"></p>
                        </div>
                        <button type="button" class="bcs-detected__btn" @click="confirmarCodigo()">
                            + Agregar
                        </button>
                    </div>

                    {{-- PDV: instrucción cuando no hay código --}}
                    <p class="bcs-status" x-show="isPdv && scanning && !pendingCode">
                        Apunta la cámara al código de barras
                    </p>

                    {{-- No PDV: instrucción normal --}}
                    <p class="bcs-status" x-show="!isPdv && scanning">
                        Apunta la cámara al código de barras
                    </p>

                    <button type="button" class="bcs-btn-cerrar" @click="close()">Cerrar</button>
                </div>

            </div>
        </div>
    </template>

</div>
