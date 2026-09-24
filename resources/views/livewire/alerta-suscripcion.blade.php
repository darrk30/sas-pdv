<div>
@if($enPrueba)
{{-- ── Banner prueba gratuita (azul) ─────────────────────────────────────── --}}
<div
    x-data="{ open: false }"
    style="position: relative; display: inline-flex; align-items: center; margin-right: 8px;"
>
    <button
        @click="open = !open"
        @mouseenter="open = true"
        @mouseleave="open = false"
        style="
            display: inline-flex; align-items: center; gap: 6px;
            background: #dbeafe; border: 1px solid #3b82f6; border-radius: 20px;
            padding: 5px 12px; cursor: pointer;
            color: #1e40af; font-size: 0.8rem; font-weight: 600;
            white-space: nowrap; line-height: 1;
            transition: background .15s, box-shadow .15s;
            box-shadow: 0 1px 3px rgba(59,130,246,.2);
        "
        onmouseover="this.style.background='#bfdbfe'; this.style.boxShadow='0 2px 8px rgba(59,130,246,.35)'"
        onmouseout="this.style.background='#dbeafe'; this.style.boxShadow='0 1px 3px rgba(59,130,246,.2)'"
    >
        <span style="font-size: 0.95rem;">🎁</span>
        <span class="sas-alert-label">
            Prueba gratuita · {{ $diasPrueba }} {{ $diasPrueba === 1 ? 'día' : 'días' }}
        </span>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @mouseenter="open = true"
        @mouseleave="open = false"
        style="
            position: absolute; top: calc(100% + 10px); left: 0;
            background: #fff; border: 1px solid #3b82f6; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            padding: 14px 16px; min-width: 260px; z-index: 9999;
            color: #1f2937; font-size: 0.855rem; line-height: 1.55;
        "
    >
        <div style="position: absolute; top: -7px; left: 20px;
            width: 12px; height: 12px;
            background: #fff; border-left: 1px solid #3b82f6; border-top: 1px solid #3b82f6;
            transform: rotate(45deg);"></div>

        <div style="font-weight: 700; color: #1e40af; margin-bottom: 6px;">
            🎁 Período de prueba gratuita
        </div>
        <div style="color: #4b5563; margin-bottom: 12px;">
            @if($diasPrueba > 0)
                Te {{ $diasPrueba === 1 ? 'queda' : 'quedan' }}
                <strong style="color: #1e40af;">{{ $diasPrueba }} {{ $diasPrueba === 1 ? 'día' : 'días' }}</strong>
                de prueba. Activa tu plan antes de que termine para no interrumpir el servicio.
            @else
                Tu prueba gratuita <strong style="color: #dc2626;">vence hoy</strong>.
                Activa tu plan ahora para continuar usando el sistema.
            @endif
        </div>
        <a
            href="{{ $url }}"
            style="
                display: flex; align-items: center; justify-content: center; gap: 6px;
                background: #3b82f6; color: #fff; border-radius: 7px;
                padding: 8px 14px; font-weight: 600; text-decoration: none;
                font-size: 0.82rem; transition: background .15s;
            "
            onmouseover="this.style.background='#2563eb'"
            onmouseout="this.style.background='#3b82f6'"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
            </svg>
            Ver mi suscripción
        </a>
    </div>
</div>

@elseif($proxima)
{{-- ── Banner suscripción por vencer (amarillo) ───────────────────────────── --}}
<div
    x-data="{ open: false }"
    style="position: relative; display: inline-flex; align-items: center; margin-right: 8px;"
>
    <button
        @click="open = !open"
        @mouseenter="open = true"
        @mouseleave="open = false"
        style="
            display: inline-flex; align-items: center; gap: 6px;
            background: #fef3c7; border: 1px solid #f59e0b; border-radius: 20px;
            padding: 5px 12px; cursor: pointer;
            color: #92400e; font-size: 0.8rem; font-weight: 600;
            white-space: nowrap; line-height: 1;
            transition: background .15s, box-shadow .15s;
            box-shadow: 0 1px 3px rgba(245,158,11,.2);
        "
        onmouseover="this.style.background='#fde68a'; this.style.boxShadow='0 2px 8px rgba(245,158,11,.35)'"
        onmouseout="this.style.background='#fef3c7'; this.style.boxShadow='0 1px 3px rgba(245,158,11,.2)'"
    >
        <span style="font-size: 0.95rem; animation: sas-pulse 1.5s infinite;">⚠️</span>
        <span class="sas-alert-label">Suscripción por vencer</span>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @mouseenter="open = true"
        @mouseleave="open = false"
        style="
            position: absolute; top: calc(100% + 10px); left: 0;
            background: #fff; border: 1px solid #f59e0b; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            padding: 14px 16px; min-width: 260px; z-index: 9999;
            color: #1f2937; font-size: 0.855rem; line-height: 1.55;
        "
    >
        <div style="position: absolute; top: -7px; left: 20px;
            width: 12px; height: 12px;
            background: #fff; border-left: 1px solid #f59e0b; border-top: 1px solid #f59e0b;
            transform: rotate(45deg);"></div>

        <div style="font-weight: 700; color: #92400e; margin-bottom: 6px;">
            ⚠️ Suscripción por vencer
        </div>
        <div style="color: #4b5563; margin-bottom: 12px;">
            Tu suscripción vence
            <strong style="color: #92400e;">{{ $diasTexto }}</strong>.
            Realiza tu pago para evitar la suspensión del servicio.
        </div>
        <a
            href="{{ $url }}"
            style="
                display: flex; align-items: center; justify-content: center; gap: 6px;
                background: #f59e0b; color: #fff; border-radius: 7px;
                padding: 8px 14px; font-weight: 600; text-decoration: none;
                font-size: 0.82rem; transition: background .15s;
            "
            onmouseover="this.style.background='#d97706'"
            onmouseout="this.style.background='#f59e0b'"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
            </svg>
            Registrar pago ahora
        </a>
    </div>
</div>
@endif

<style>
@keyframes sas-pulse {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.2); }
}
@media (max-width: 640px) {
    .sas-alert-label { display: none; }
}
</style>
</div>
