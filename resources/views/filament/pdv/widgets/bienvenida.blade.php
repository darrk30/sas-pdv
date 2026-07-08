<x-filament-widgets::widget>
<style>
/* Tarjeta: solo borde suave, sin fondo blanco */
.wi-bv-card {
    border: 1px solid #e5e7eb;
    border-radius: .75rem;
    padding: 1.5rem;
    overflow: hidden;
    position: relative;
}
.dark .wi-bv-card { border-color: #2d3748; }

/* Degradado de fondo interior */
.wi-bv-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(70,68,158,.08) 0%, transparent 60%);
    pointer-events: none;
    z-index: 0;
}
.dark .wi-bv-bg {
    background: linear-gradient(120deg, rgba(70,68,158,.2) 0%, transparent 60%);
}

/* Barra lateral izquierda */
.wi-bv-bar {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: .25rem;
    background: linear-gradient(to bottom, #46449e, #8b89d0);
    z-index: 2;
}

/* Layout interior */
.wi-bv-layout {
    position: relative;
    display: flex;
    align-items: stretch;
    min-height: 11rem;
    z-index: 1;
}

/* Contenido de texto */
.wi-bv-content {
    flex: 1;
    min-width: 0;
    padding-left: .75rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Pill */
.wi-bv-pill {
    display: inline-flex;
    align-items: center;
    gap: .375rem;
    background: rgba(70,68,158,.1);
    color: #46449e;
    font-size: .6875rem;
    font-weight: 700;
    padding: .2rem .7rem;
    border-radius: 9999px;
    letter-spacing: .05em;
    text-transform: uppercase;
    margin-bottom: .75rem;
    width: fit-content;
}
.dark .wi-bv-pill { background: rgba(165,163,217,.18); color: #a5a3d9; }

/* Título */
.wi-bv-title { font-size: 1.5rem; font-weight: 800; line-height: 1.25; color: #111827; margin: 0 0 .5rem; }
.dark .wi-bv-title { color: #f9fafb; }
.wi-bv-name  { color: #46449e; font-style: italic; }
.dark .wi-bv-name { color: #a5a3d9; }

/* Descripción */
.wi-bv-desc { font-size: .875rem; line-height: 1.6; color: #6b7280; margin: 0; max-width: 28rem; }
.dark .wi-bv-desc { color: #9ca3af; }

/* Imagen — desktop: borde a borde del card */
.wi-bv-img-wrap {
    flex-shrink: 0;
    width: 17rem;
    /* Cancela el padding del card (1.5rem) para tocar los bordes */
    margin-top: -1.5rem;
    margin-bottom: -1.5rem;
    margin-right: -1.5rem;
    overflow: hidden;
}
.wi-bv-img {
    /* height 100% escala la imagen a la altura total del card, sin recortar */
    height: 100%;
    width: auto;
    display: block;
    /* Degradado izquierdo: la imagen se integra al card */
    -webkit-mask-image: linear-gradient(to right, transparent 0%, black 42%);
    mask-image: linear-gradient(to right, transparent 0%, black 42%);
}

/* Mobile: imagen debajo del texto */
@media (max-width: 600px) {
    .wi-bv-layout { flex-wrap: wrap; min-height: auto; }
    .wi-bv-img-wrap {
        width: calc(100% + 3rem);
        height: auto;        /* altura natural, no fija */
        margin-top: .5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        margin-bottom: -1.5rem;
    }
    .wi-bv-img {
        /* En móvil: ancho completo, altura natural */
        width: 100%;
        height: auto;
        -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 20%);
        mask-image: linear-gradient(to bottom, transparent 0%, black 20%);
    }
}
</style>

    <div class="wi-bv-card">
        <div class="wi-bv-bg"></div>
        <div class="wi-bv-bar"></div>

        <div class="wi-bv-layout">

            {{-- Texto --}}
            <div class="wi-bv-content">
                <div class="wi-bv-pill">
                    <svg style="width:.75rem;height:.75rem;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                    </svg>
                    Panel de gestión
                </div>

                <p class="wi-bv-title">
                    ¡Bienvenido/a de nuevo,<br>
                    <span class="wi-bv-name">{{ $nombreUsuario }}</span>!
                </p>

                <p class="wi-bv-desc">
                    Cada día es una nueva oportunidad para hacer crecer tu negocio.
                    Gestiona con inteligencia y alcanza tus metas.
                </p>
            </div>

            {{-- Imagen completa, borde a borde --}}
            <div class="wi-bv-img-wrap">
                <img src="{{ asset('img/alumnoenpc.png') }}" alt="" class="wi-bv-img" />
            </div>

        </div>
    </div>

</x-filament-widgets::widget>
