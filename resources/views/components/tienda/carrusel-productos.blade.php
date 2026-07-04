@if ($productos->isNotEmpty())
<section class="caru">

    <div class="caru__header">
        <h2 class="caru__titulo">{{ $titulo }}</h2>

        {{-- Botones de navegación custom --}}
        <div class="caru__nav">
            <button class="caru__nav-btn caru__nav-btn--prev" type="button" aria-label="Anterior">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </button>
            <button class="caru__nav-btn caru__nav-btn--next" type="button" aria-label="Siguiente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="swiper caru__swiper"
         x-data
         x-init="
             (function(el){
                 new Swiper(el, {
                     slidesPerView: 2.3,
                     spaceBetween: 10,
                     navigation: {
                         nextEl: el.closest('.caru').querySelector('.caru__nav-btn--next'),
                         prevEl: el.closest('.caru').querySelector('.caru__nav-btn--prev'),
                     },
                     breakpoints: {
                         480:  { slidesPerView: 3,   spaceBetween: 12 },
                         640:  { slidesPerView: 4,   spaceBetween: 14 },
                         900:  { slidesPerView: 5,   spaceBetween: 16 },
                         1200: { slidesPerView: 6,   spaceBetween: 16 },
                     }
                 });
             })($el)
         ">
        <div class="swiper-wrapper">
            @foreach ($productos as $producto)
                <div class="swiper-slide caru__slide">
                    <x-tienda.tarjeta :producto="$producto" wire:key="'caru-'.$producto->id" />
                </div>
            @endforeach
        </div>
    </div>

</section>
@endif
