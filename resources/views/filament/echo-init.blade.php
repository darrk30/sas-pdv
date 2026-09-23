<script>
(function () {
    if (typeof window.EchoFactory === 'undefined') return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        ?? window.livewire_csrf_token
        ?? '{{ csrf_token() }}';
    window.Echo = new window.EchoFactory({
        broadcaster:       'reverb',
        key:               '{{ config('broadcasting.connections.reverb.key') }}',
        wsHost:            '{{ config('broadcasting.connections.reverb.options.host') }}',
        wsPort:            {{ (int) config('broadcasting.connections.reverb.options.port') }},
        wssPort:           {{ (int) config('broadcasting.connections.reverb.options.port') }},
        forceTLS:          {{ config('broadcasting.connections.reverb.options.scheme') === 'https' ? 'true' : 'false' }},
        enabledTransports: ['ws', 'wss'],
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        },
    });

})();
</script>
