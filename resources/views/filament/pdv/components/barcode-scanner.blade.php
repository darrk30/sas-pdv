{{--
    html5-qrcode desde CDN con defer: se ejecuta antes que los módulos ES (Alpine),
    por lo que Html5Qrcode estará disponible cuando el usuario abra el scanner.
--}}
<script defer src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

{{--
    Script síncrono (no es módulo): se ejecuta DURANTE el parseo del HTML,
    antes de cualquier script type="module" (incluyendo Alpine y app.js).
    Así window._barcodeScanner existe cuando Alpine evalúa x-data.
--}}
<script>
window._barcodeScanner = function () {
    return {
        open: false,
        scanning: false,
        targetPath: null,
        error: null,
        _scanner: null,

        init() {
            window.addEventListener('open-barcode-scanner', (e) => {
                this.targetPath = e.detail?.path ?? null;
                this.error = null;
                this.open = true;
                this.$nextTick(() => this._startCamera());
            });
        },

        _startCamera() {
            var id = 'barcode-reader-preview';
            if (!document.getElementById(id)) return;

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

            this._scanner
                .start(
                    { facingMode: { exact: 'environment' } },
                    { fps: 10, qrbox: { width: 260, height: 110 } },
                    (code) => this._onSuccess(code),
                    () => {}
                )
                .then(() => { this.scanning = true; })
                .catch(() => {
                    this._scanner
                        .start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: { width: 260, height: 110 } },
                            (code) => this._onSuccess(code),
                            () => {}
                        )
                        .then(() => { this.scanning = true; })
                        .catch((err) => {
                            this.error = 'No se pudo acceder a la cámara. Verifica los permisos del navegador.';
                            console.error('[BarcodeScanner]', err);
                        });
                });
        },

        _onSuccess(code) {
            this._stop(false);
            if (this.targetPath) {
                var formPath = this.targetPath.replace(/^data\./, '');
                Livewire.dispatch('barcode-result', { path: formPath, code });
            }
        },

        _stop(andClose) {
            if (andClose === undefined) andClose = true;
            if (this._scanner && this.scanning) {
                this.scanning = false;
                this._scanner.stop()
                    .catch(function () {})
                    .finally(() => {
                        this._scanner = null;
                        if (andClose) this.open = false;
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
        {{-- Overlay --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            style="display:none"
        >
            {{-- Fondo oscuro --}}
            <div
                class="absolute inset-0 bg-black/70 backdrop-blur-sm"
                @click="close()"
            ></div>

            {{-- Modal --}}
            <div class="relative w-full max-w-sm rounded-2xl bg-white shadow-2xl dark:bg-gray-800 overflow-hidden">

                {{-- Encabezado --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <div class="flex items-center gap-2.5">
                        <svg class="h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                        </svg>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Escanear código de barras
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="close()"
                        class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Visor de cámara --}}
                <div class="px-4 pb-4 pt-3">
                    <div
                        id="barcode-reader-preview"
                        class="w-full overflow-hidden rounded-xl bg-gray-900"
                        style="min-height: 200px"
                        wire:ignore
                    ></div>

                    {{-- Error --}}
                    <div x-show="error" class="mt-3 rounded-lg bg-red-50 px-3 py-2 dark:bg-red-900/20">
                        <p class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>
                    </div>

                    {{-- Instrucción --}}
                    <p
                        x-show="scanning && !error"
                        class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400"
                    >
                        Apunta la cámara trasera al código de barras
                    </p>

                    <button
                        type="button"
                        @click="close()"
                        class="mt-3 w-full rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
