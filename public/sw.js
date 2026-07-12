self.addEventListener('push', function (event) {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (_) {}

    event.waitUntil(
        self.registration.showNotification(data.title || 'Nueva notificación', {
            body:               data.body || '',
            icon:               '/favicon.ico',
            badge:              '/favicon.ico',
            data:               { url: data.url || '/' },
            vibrate:            [200, 100, 200],
            requireInteraction: true,
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (const client of list) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            return clients.openWindow(url);
        })
    );
});
