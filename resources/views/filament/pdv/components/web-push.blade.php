<script>
(function () {
    'use strict';

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    const VAPID_KEY     = '{{ config("services.vapid.public_key") }}';
    const SUBSCRIBE_URL = '{{ route("push.subscribe") }}';
    const CSRF          = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    function urlBase64ToUint8Array(base64) {
        const pad = '='.repeat((4 - base64.length % 4) % 4);
        const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    async function guardarSuscripcion(sub) {
        await fetch(SUBSCRIBE_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify(sub),
        });
    }

    async function suscribir(reg) {
        try {
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_KEY),
            });
            await guardarSuscripcion(sub);
        } catch (e) {
            console.warn('[WebPush] No se pudo suscribir:', e);
        }
    }

    async function init() {
        const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

        // Si ya tiene permiso, asegura que haya suscripción activa
        if (Notification.permission === 'granted') {
            const existing = await reg.pushManager.getSubscription();
            if (!existing) await suscribir(reg);
            return;
        }

        if (Notification.permission === 'denied') return;

        // Pedir permiso en el primer click del usuario (requerido por los navegadores)
        document.addEventListener('click', async function pedirPermiso() {
            document.removeEventListener('click', pedirPermiso);
            const resultado = await Notification.requestPermission();
            if (resultado === 'granted') await suscribir(reg);
        }, { once: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
