import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const FORMATS = [
    Html5QrcodeSupportedFormats.EAN_13,
    Html5QrcodeSupportedFormats.EAN_8,
    Html5QrcodeSupportedFormats.CODE_128,
    Html5QrcodeSupportedFormats.CODE_39,
    Html5QrcodeSupportedFormats.UPC_A,
    Html5QrcodeSupportedFormats.UPC_E,
    Html5QrcodeSupportedFormats.QR_CODE,
];

document.addEventListener('alpine:init', () => {
    Alpine.data('barcodeScanner', () => ({
        open: false,
        scanning: false,
        scanner: null,
        targetPath: null,
        error: null,

        init() {
            window.addEventListener('open-barcode-scanner', (e) => {
                this.targetPath = e.detail?.path ?? null;
                this.error = null;
                this.openScanner();
            });
        },

        openScanner() {
            this.open = true;
            this.$nextTick(() => this.startCamera());
        },

        startCamera() {
            const id = 'barcode-reader-preview';
            if (!document.getElementById(id)) return;

            this.scanner = new Html5Qrcode(id, {
                formatsToSupport: FORMATS,
                verbose: false,
            });

            // Intentar cámara trasera primero (móvil)
            this.scanner
                .start(
                    { facingMode: { exact: 'environment' } },
                    { fps: 10, qrbox: { width: 260, height: 110 } },
                    (code) => this.onSuccess(code),
                    () => {}
                )
                .then(() => { this.scanning = true; })
                .catch(() => {
                    // Fallback: cualquier cámara disponible
                    this.scanner
                        .start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: { width: 260, height: 110 } },
                            (code) => this.onSuccess(code),
                            () => {}
                        )
                        .then(() => { this.scanning = true; })
                        .catch((err) => {
                            this.error = 'No se pudo acceder a la cámara. Verifica los permisos del navegador.';
                            console.error('[BarcodeScanner]', err);
                        });
                });
        },

        onSuccess(code) {
            this.stop(false);
            if (this.targetPath) {
                // Quitar prefijo 'data.' que Filament incluye en getStatePath()
                const formPath = this.targetPath.replace(/^data\./, '');
                Livewire.dispatch('barcode-result', { path: formPath, code });
            }
        },

        stop(andClose = true) {
            if (this.scanner && this.scanning) {
                this.scanning = false;
                this.scanner.stop()
                    .catch(() => {})
                    .finally(() => {
                        this.scanner = null;
                        if (andClose) this.open = false;
                    });
            } else {
                this.scanner = null;
                if (andClose) this.open = false;
            }
        },

        close() {
            this.stop(true);
        },
    }));
});
