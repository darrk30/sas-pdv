@php
  use App\Models\AppSetting;
  use Illuminate\Support\Facades\Storage;

  $cfg_nombre    = AppSetting::get('nombre',    'TUKIPU');
  $cfg_slogan    = AppSetting::get('slogan');
  $cfg_email     = AppSetting::get('email',     'hola@tukipu.com');
  $cfg_telefono  = AppSetting::get('telefono');
  $cfg_whatsapp  = AppSetting::get('whatsapp',  '51942407799');
  $cfg_web       = AppSetting::get('web');
  $cfg_facebook  = AppSetting::get('facebook');
  $cfg_instagram = AppSetting::get('instagram');
  $cfg_youtube   = AppSetting::get('youtube');
  $cfg_linkedin  = AppSetting::get('linkedin');
  $cfg_tiktok    = AppSetting::get('tiktok');
  $cfg_twitter   = AppSetting::get('twitter');

  $cfg_logoPath  = AppSetting::get('logo');
  $cfg_logoUrl   = $cfg_logoPath ? Storage::disk('public')->url($cfg_logoPath) : asset('img/logotukipu.webp');

  $waNavUrl = 'https://wa.me/' . $cfg_whatsapp . '?text=' . rawurlencode("Hola, quiero solicitar un demo de {$cfg_nombre}");
  $waFabUrl = 'https://wa.me/' . $cfg_whatsapp . '?text=' . rawurlencode("Hola, quiero más información sobre {$cfg_nombre}");
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO básico -->
  <title>TUKIPU — Sistema POS y Tienda Online para negocios en Perú</title>
  <meta name="description" content="TUKIPU es el sistema POS en la nube para tiendas, minimarkets, ferreterías y más. Vende, gestiona inventario, emite comprobantes y ten tu tienda online desde S/50/mes.">
  <meta name="keywords" content="sistema para punto de venta, software para tiendas, tienda virtual, sistema de inventario, sistema POS Peru, punto de venta en la nube, software para tiendas, tienda online Peru, POS minimarket, inventario en la nube, comprobantes electronicos Peru, sistema de ventas para negocios, programa para tienda, caja registradora online, boletas y facturas electronicas, software para bodega, sistema para ferreteria, sistema para cafeteria, sistema para ropa, TUKIPU">
  <meta name="robots" content="index, follow">
  <meta name="author" content="TUKIPU">
  <link rel="canonical" href="{{ url('/') }}">

  <!-- Open Graph (Facebook, WhatsApp, LinkedIn) -->
  <meta property="og:type"        content="website">
  <meta property="og:url"         content="{{ url('/') }}">
  <meta property="og:title"       content="TUKIPU — Sistema POS y Tienda Online para negocios en Perú">
  <meta property="og:description" content="Vende, gestiona inventario, emite comprobantes y ten tu tienda online desde S/50/mes. Prueba gratis.">
  <meta property="og:image"       content="{{ $cfg_logoUrl }}">
  <meta property="og:locale"      content="es_PE">
  <meta property="og:site_name"   content="TUKIPU">

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="TUKIPU — Sistema POS y Tienda Online para negocios en Perú">
  <meta name="twitter:description" content="Vende, gestiona inventario, emite comprobantes y ten tu tienda online desde S/50/mes.">
  <meta name="twitter:image"       content="{{ $cfg_logoUrl }}">

  <!-- Datos estructurados JSON-LD (Google) -->
  @php
    $ldJson = json_encode([
      '@context' => 'https://schema.org',
      '@graph'   => [
        [
          '@type' => 'Organization',
          'name'  => 'TUKIPU',
          'url'   => url('/'),
          'logo'  => $cfg_logoUrl,
          'contactPoint' => [
            '@type'             => 'ContactPoint',
            'telephone'         => '+' . ltrim($cfg_whatsapp, '+'),
            'contactType'       => 'sales',
            'areaServed'        => 'PE',
            'availableLanguage' => 'Spanish',
          ],
        ],
        [
          '@type'               => 'SoftwareApplication',
          'name'                => 'TUKIPU',
          'applicationCategory' => 'BusinessApplication',
          'operatingSystem'     => 'Web',
          'url'                 => url('/'),
          'description'         => 'Sistema POS en la nube con tienda online integrada para comercios minoristas en Perú.',
          'offers'              => $planes->map(fn($p) => [
            '@type'        => 'Offer',
            'name'         => 'Plan ' . $p->nombre,
            'price'        => number_format($p->precio, 2, '.', ''),
            'priceCurrency'=> 'PEN',
            'description'  => $p->descripcion,
          ])->values()->all(),
          'inLanguage'         => 'es-PE',
          'countriesSupported' => 'PE',
        ],
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  @endphp
  <script type="application/ld+json">{!! $ldJson !!}</script>

  <link rel="icon" type="image/x-icon" href="{{ asset('img/iconotukipu.ico') }}">
  <link rel="stylesheet" href="{{ asset('landing/landing.css') }}">
</head>

<body>

  <!-- NAV -->
  <nav id="nav">
    <div class="wrap">
      <div class="nav-i">
        <a href="#inicio">
          <img src="{{ $cfg_logoUrl }}" alt="{{ $cfg_nombre }}" style="height:38px;width:auto;display:block">
        </a>
        <ul class="nav-links">
          <li><a href="#caracteristicas">Características</a></li>
          <li><a href="#industrias">Para quién</a></li>
          <li><a href="#como-funciona">Cómo funciona</a></li>
          <li><a href="#planes">Planes</a></li>
        </ul>
        <div class="nav-r">
          <a href="{{ $waNavUrl }}" target="_blank" rel="noopener" class="btn btn-or">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
              <path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.549 4.099 1.514 5.82L.057 23.455a.5.5 0 0 0 .597.665l5.82-1.514A11.946 11.946 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0h-.001zm.001 21.818a9.818 9.818 0 0 1-5.018-1.374l-.36-.213-3.727.978.978-3.605-.234-.375A9.818 9.818 0 0 1 2.181 12c0-5.422 4.396-9.818 9.818-9.818 5.423 0 9.819 4.396 9.819 9.818 0 5.423-4.396 9.818-9.818 9.818z" />
            </svg>
            Solicita tu demo
          </a>
          <button class="hamburger" onclick="document.getElementById('mn').classList.add('open')" aria-label="Abrir menú">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
              <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </nav>

  <div class="m-nav" id="mn">
    <div class="m-ov" onclick="document.getElementById('mn').classList.remove('open')"></div>
    <nav class="m-draw">
      <button class="m-close" onclick="document.getElementById('mn').classList.remove('open')" aria-label="Cerrar menú"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
          <path d="M18 6 6 18M6 6l12 12" />
        </svg></button>
      <a href="#caracteristicas" onclick="document.getElementById('mn').classList.remove('open')">Características</a>
      <a href="#industrias" onclick="document.getElementById('mn').classList.remove('open')">Para quién</a>
      <a href="#como-funciona" onclick="document.getElementById('mn').classList.remove('open')">Cómo funciona</a>
      <a href="#planes" onclick="document.getElementById('mn').classList.remove('open')">Planes</a>
      <a href="{{ $waNavUrl }}" target="_blank" rel="noopener" class="btn btn-or" style="margin-top:1rem;justify-content:center">Solicita tu demo</a>
    </nav>
  </div>

  <main>

    <!-- HERO -->
    <section class="hero" id="inicio">
      <canvas id="hero-canvas"></canvas>
      <div class="hero-blob-or"></div>
      <div class="hero-blob-tl"></div>
      <div class="wrap">
        <div class="hero-inner">
          <div>
            <div class="hero-pill">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
              </svg>
              Sistema POS SaaS · Multiempresa
            </div>
            <h1 class="hero-h">Vende más, gestiona<br>mejor — <em>todo en uno</em></h1>
            <p class="hero-sub">La plataforma perfecta para</p>
            <p class="typed-line"><span class="typed-word" id="typed"></span><span class="typed-cursor"></span></p>
            <p class="hero-sub" style="margin-top:-.4rem;margin-bottom:1.75rem">Punto de venta, tienda online, inventario y reportes — todo desde un solo sistema en la nube.</p>
            <div class="hero-btns">
              <a href="#cta" class="btn btn-or">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                  <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
                Solicitar demo gratis
              </a>
              <a href="#como-funciona" class="btn-ghost">Ver cómo funciona</a>
            </div>
            <div class="hero-checks">
              <span class="hck"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0DAA6A" stroke-width="2.5">
                  <path d="M20 6 9 17l-5-5" />
                </svg>Sin instalación</span>
              <span class="hck"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0DAA6A" stroke-width="2.5">
                  <path d="M20 6 9 17l-5-5" />
                </svg>Subdominio propio</span>
              <span class="hck"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0DAA6A" stroke-width="2.5">
                  <path d="M20 6 9 17l-5-5" />
                </svg>100% en la nube</span>
              <span class="hck"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0DAA6A" stroke-width="2.5">
                  <path d="M20 6 9 17l-5-5" />
                </svg>Listo en minutos</span>
            </div>
          </div>
          <div class="mockup-out">
            <div class="mockup-shadow"></div>
            <div class="browser">
              <div class="b-chrome">
                <div class="b-dots"><span></span><span></span><span></span></div>
                <div class="b-url">empresa1.tukipu.com/pdv</div>
              </div>
              <div class="b-body">
                <div class="b-pos">
                  <div class="b-pos-head">
                    <span class="b-pos-htitle">Punto de Venta</span>
                    <span class="b-pos-badge">8 productos</span>
                  </div>
                  <div class="b-grid">
                    <div class="b-prod">
                      <div class="b-pc" style="background:linear-gradient(135deg,#FFE4CC,#FFCDA0)">👕</div>
                      <div class="b-pi">
                        <div class="b-pn">Polo Básico</div>
                        <div class="b-pp">S/ 35</div>
                      </div>
                    </div>
                    <div class="b-prod">
                      <div class="b-pc" style="background:linear-gradient(135deg,#CCE9FF,#A0D4FF)">👖</div>
                      <div class="b-pi">
                        <div class="b-pn">Pantalón</div>
                        <div class="b-pp">S/ 89</div>
                      </div>
                    </div>
                    <div class="b-prod">
                      <div class="b-pc" style="background:linear-gradient(135deg,#E0D4FF,#C8B0FF)">👟</div>
                      <div class="b-pi">
                        <div class="b-pn">Zapatillas</div>
                        <div class="b-pp">S/ 120</div>
                      </div>
                    </div>
                    <div class="b-prod">
                      <div class="b-pc" style="background:linear-gradient(135deg,#FFD6CC,#FFAFA0)">🧥</div>
                      <div class="b-pi">
                        <div class="b-pn">Casaca</div>
                        <div class="b-pp">S/ 150</div>
                      </div>
                    </div>
                    <div class="b-prod">
                      <div class="b-pc" style="background:linear-gradient(135deg,#FFF3CC,#FFE68A)">🩳</div>
                      <div class="b-pi">
                        <div class="b-pn">Short</div>
                        <div class="b-pp">S/ 45</div>
                      </div>
                    </div>
                    <div class="b-prod">
                      <div class="b-pc" style="background:linear-gradient(135deg,#CCF7FF,#8AE8FF)">🎒</div>
                      <div class="b-pi">
                        <div class="b-pn">Mochila</div>
                        <div class="b-pp">S/ 75</div>
                      </div>
                    </div>
                  </div>
                  <div class="b-cart">
                    <div class="b-cr"><span>1× Polo Básico</span><b>S/ 35.00</b></div>
                    <div class="b-cr"><span>1× Zapatillas</span><b>S/ 120.00</b></div>
                    <hr class="b-hr">
                    <div class="b-tot"><span class="b-tl">Total a cobrar</span><span class="b-ta">S/ 155.00</span></div>
                    <button class="b-pay">💳 COBRAR AHORA</button>
                  </div>
                </div>
                <div class="b-dash">
                  <div class="b-dt">
                    <div>Ventas de hoy</div>
                    <div class="b-da">S/ 2,840</div>
                    <div class="b-ds">↑ 18% vs. ayer</div>
                  </div>
                  <svg viewBox="0 0 182 52" fill="none" width="100%">
                    <rect x="4" y="29" width="16" height="17" rx="2" fill="#EDF1F5" />
                    <rect x="29" y="33" width="16" height="13" rx="2" fill="#EDF1F5" />
                    <rect x="54" y="22" width="16" height="24" rx="2" fill="#EDF1F5" />
                    <rect x="79" y="25" width="16" height="21" rx="2" fill="#EDF1F5" />
                    <rect x="104" y="14" width="16" height="32" rx="2" fill="#EDF1F5" />
                    <rect x="129" y="7" width="16" height="39" rx="2" fill="#EDF1F5" />
                    <defs>
                      <linearGradient id="bg3" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F07020" />
                        <stop offset="100%" stop-color="#0097B5" />
                      </linearGradient>
                    </defs>
                    <rect x="154" y="6" width="16" height="40" rx="2" fill="url(#bg3)" />
                    <text x="12" y="51" font-size="6" fill="#7A9BB0" text-anchor="middle" font-family="system-ui">L</text>
                    <text x="37" y="51" font-size="6" fill="#7A9BB0" text-anchor="middle" font-family="system-ui">M</text>
                    <text x="62" y="51" font-size="6" fill="#7A9BB0" text-anchor="middle" font-family="system-ui">X</text>
                    <text x="87" y="51" font-size="6" fill="#7A9BB0" text-anchor="middle" font-family="system-ui">J</text>
                    <text x="112" y="51" font-size="6" fill="#7A9BB0" text-anchor="middle" font-family="system-ui">V</text>
                    <text x="137" y="51" font-size="6" fill="#7A9BB0" text-anchor="middle" font-family="system-ui">S</text>
                    <text x="162" y="51" font-size="6" fill="#F07020" text-anchor="middle" font-family="system-ui" font-weight="700">D</text>
                  </svg>
                  <div class="b-kpis">
                    <div class="b-kpi">
                      <div class="b-kl">Órdenes</div>
                      <div class="b-kv">34</div>
                    </div>
                    <div class="b-kpi">
                      <div class="b-kl">Ticket prom.</div>
                      <div class="b-kv">S/ 83.5</div>
                    </div>
                  </div>
                  <div>
                    <div class="b-ol">Ventas recientes</div>
                    <div class="b-ord">
                      <div class="b-od" style="background:#0DAA6A"></div>
                      <div class="b-on">Carlos M.</div>
                      <div class="b-oa">S/ 155</div>
                      <div class="b-bg bg-ok">Pagado</div>
                    </div>
                    <div class="b-ord">
                      <div class="b-od" style="background:#CA8D00"></div>
                      <div class="b-on">Ana R.</div>
                      <div class="b-oa">S/ 89</div>
                      <div class="b-bg bg-p">Pendiente</div>
                    </div>
                    <div class="b-ord">
                      <div class="b-od" style="background:var(--tl)"></div>
                      <div class="b-on">Luis T.</div>
                      <div class="b-oa">S/ 210</div>
                      <div class="b-bg bg-n">Nuevo</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- INDUSTRIES -->
    <section class="industries" id="industrias">
      <div class="wrap">
        <p class="ind-lbl">Ideal para todo tipo de comercio minorista</p>
        <div class="ind-grid">
          <div class="ind-item fade-up">
            <div class="ind-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--or)" stroke-width="1.8">
                <path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.57A1 1 0 0 0 3.85 10H7v10a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V10h3.15a1 1 0 0 0 .99-.84l.58-3.57a2 2 0 0 0-1.34-2.23z" />
              </svg></div>
            <span class="ind-name">Ropa y moda</span>
          </div>
          <div class="ind-item fade-up" style="transition-delay:.05s">
            <div class="ind-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--tl)" stroke-width="1.8">
                <circle cx="8" cy="21" r="1" />
                <circle cx="19" cy="21" r="1" />
                <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
              </svg></div>
            <span class="ind-name">Minimarket</span>
          </div>
          <div class="ind-item fade-up" style="transition-delay:.1s">
            <div class="ind-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#C89000" stroke-width="1.8">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
              </svg></div>
            <span class="ind-name">Ferretería</span>
          </div>
          <div class="ind-item fade-up" style="transition-delay:.15s">
            <div class="ind-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--or)" stroke-width="1.8">
                <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                <line x1="6" y1="1" x2="6" y2="4" />
                <line x1="10" y1="1" x2="10" y2="4" />
                <line x1="14" y1="1" x2="14" y2="4" />
              </svg></div>
            <span class="ind-name">Cafetería</span>
          </div>
          <div class="ind-item fade-up" style="transition-delay:.2s">
            <div class="ind-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--or-d)" stroke-width="1.8">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                <polyline points="9,22 9,12 15,12 15,22" />
              </svg></div>
            <span class="ind-name">Bodega</span>
          </div>
          <div class="ind-item fade-up" style="transition-delay:.3s">
            <div class="ind-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--ink3)" stroke-width="1.8">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="16" />
                <line x1="8" y1="12" x2="16" y2="12" />
              </svg></div>
            <span class="ind-name">Y más…</span>
          </div>
        </div>
      </div>
    </section>

    <!-- FEATURES -->
    <section id="caracteristicas">
      <div class="wrap">
        <div class="sec-head fade-up">
          <span class="sec-tag sec-tag-or">Características</span>
          <h2 class="sec-h">Todo lo que tu negocio necesita,<br>integrado en un solo lugar</h2>
          <p class="sec-p">Del mostrador al carrito online. Sin apps externas, sin integraciones complicadas — todo sincronizado en tiempo real.</p>
        </div>
        <div class="feat-grid">
          <div class="feat-card fade-up">
            <div class="feat-icon feat-icon-or"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" />
                <path d="M8 21h8M12 17v4" />
              </svg></div>
            <h3 class="feat-title">Punto de venta ágil</h3>
            <p class="feat-desc">Interfaz táctil optimizada para mostrador. Cobra en segundos con búsqueda rápida, variantes y descuentos.</p>
          </div>
          <div class="feat-card fade-up" style="transition-delay:.07s">
            <div class="feat-icon feat-icon-tl"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
              </svg></div>
            <h3 class="feat-title">Tienda online propia</h3>
            <p class="feat-desc">Subdominio personalizado con catálogo, carrito y checkout. Tus clientes compran desde cualquier dispositivo.</p>
          </div>
          <div class="feat-card fade-up" style="transition-delay:.14s">
            <div class="feat-icon feat-icon-go"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                <polyline points="3.27,6.96 12,12.01 20.73,6.96" />
                <line x1="12" y1="22.08" x2="12" y2="12" />
              </svg></div>
            <h3 class="feat-title">Inventario en tiempo real</h3>
            <p class="feat-desc">Stock actualizado al instante en cada venta, en PDV y tienda online. Variantes, tallas y colores incluidos.</p>
          </div>
          <div class="feat-card fade-up" style="transition-delay:.21s">
            <div class="feat-icon feat-icon-or"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14,2 14,8 20,8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg></div>
            <h3 class="feat-title">Comprobantes electrónicos</h3>
            <p class="feat-desc">Emite boletas, facturas y tickets. Historial completo con series, correlativos, desglose de IGV y notas de crédito.</p>
          </div>
          <div class="feat-card fade-up" style="transition-delay:.28s">
            <div class="feat-icon feat-icon-tl"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" />
                <line x1="12" y1="20" x2="12" y2="4" />
                <line x1="6" y1="20" x2="6" y2="14" />
              </svg></div>
            <h3 class="feat-title">Reportes y ganancias</h3>
            <p class="feat-desc">Dashboard con ventas, utilidades, costos y rendimiento por producto o período. Decisiones con datos reales.</p>
          </div>
          <div class="feat-card fade-up" style="transition-delay:.35s">
            <div class="feat-icon feat-icon-go"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg></div>
            <h3 class="feat-title">Pedidos y notificaciones</h3>
            <p class="feat-desc">Gestiona pedidos web con estados. Notificaciones push para nuevas órdenes desde tu tienda online.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- SHOWCASE STORE -->
    <section class="sc-store" id="tienda-online">
      <div class="wrap">
        <div class="sc-in">
          <div class="fade-up">
            <span class="sec-tag sec-tag-tl">Tienda online</span>
            <h2 class="sec-h">Tu catálogo en línea,<br>con tu propia marca</h2>
            <p class="sec-p">Cada empresa obtiene una tienda online completa con subdominio propio. Un solo panel para gestionar todo.</p>
            <div class="store-perks">
              <div class="perk">
                <div class="perk-icon perk-icon-or"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--or)" stroke-width="2.2">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="2" y1="12" x2="22" y2="12" />
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10" />
                  </svg></div>
                <div><strong>Subdominio personalizado</strong><span>empresa.tukipu.com activa en minutos, sin conocimientos técnicos.</span></div>
              </div>
              <div class="perk">
                <div class="perk-icon perk-icon-tl"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--tl)" stroke-width="2.2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                  </svg></div>
                <div><strong>Variantes y promociones</strong><span>Vende con tallas, colores, precios especiales y combos.</span></div>
              </div>
              <div class="perk">
                <div class="perk-icon perk-icon-or"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--or)" stroke-width="2.2">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                  </svg></div>
                <div><strong>Stock sincronizado al instante</strong><span>Lo que vendes en PDV se descuenta del inventario online automáticamente.</span></div>
              </div>
            </div>
          </div>
          <div class="phone-wrap fade-up" style="transition-delay:.15s">
            <div class="phone">
              <div class="phone-notch"></div>
              <div class="phone-screen">
                <div class="ph-head">
                  <div class="ph-logo">Mi Tienda</div>
                  <div class="ph-cart-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--tl)" stroke-width="2.2">
                      <circle cx="8" cy="21" r="1" />
                      <circle cx="19" cy="21" r="1" />
                      <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
                    </svg></div>
                </div>
                <div class="ph-products">
                  <div class="ph-prod">
                    <div class="ph-img" style="background:linear-gradient(135deg,#FFE4CC,#FFD0A0)">👕</div>
                    <div class="ph-info">
                      <div class="ph-name">Polo Básico</div>
                      <div class="ph-price">S/ 35.00</div>
                    </div>
                  </div>
                  <div class="ph-prod">
                    <div class="ph-img" style="background:linear-gradient(135deg,#CCE9FF,#A0D4FF)">👖</div>
                    <div class="ph-info">
                      <div class="ph-name">Pantalón Jean</div>
                      <div class="ph-price">S/ 89.00</div>
                    </div>
                  </div>
                  <div class="ph-prod">
                    <div class="ph-img" style="background:linear-gradient(135deg,#FFF3CC,#FFE68A)">🩳</div>
                    <div class="ph-info">
                      <div class="ph-name">Short Sport</div>
                      <div class="ph-price">S/ 45.00</div>
                    </div>
                  </div>
                  <div class="ph-prod">
                    <div class="ph-img" style="background:linear-gradient(135deg,#E0D4FF,#C8B0FF)">👟</div>
                    <div class="ph-info">
                      <div class="ph-name">Zapatillas</div>
                      <div class="ph-price">S/ 120.00</div>
                    </div>
                  </div>
                </div>
                <button class="ph-add">Ver catálogo completo →</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- COMPROBANTES -->
    <section class="sc-comp">
      <div class="wrap">
        <div class="sc-in" style="gap:3.5rem">
          <div class="fade-up">
            <span class="sec-tag sec-tag-or">Comprobantes electrónicos</span>
            <h2 class="sec-h">Boletas, facturas y tickets<br>desde el mismo sistema</h2>
            <p class="sec-p">Elige el comprobante en cada venta. El IGV se calcula automáticamente y el historial queda registrado con series y correlativos.</p>
            <div class="store-perks" style="margin-top:1.75rem">
              <div class="perk">
                <div class="perk-icon perk-icon-tl"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--tl)" stroke-width="2.2">
                    <path d="M12 20h9" />
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                  </svg></div>
                <div><strong>Múltiples series por caja</strong><span>Configura series distintas para cada punto de venta o usuario.</span></div>
              </div>
              <div class="perk">
                <div class="perk-icon perk-icon-or"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--or)" stroke-width="2.2">
                    <rect x="2" y="3" width="20" height="14" rx="2" />
                    <path d="M8 21h8M12 17v4" />
                  </svg></div>
                <div><strong>Historial completo</strong><span>Accede a cada comprobante desde el reporte de ventas por cliente.</span></div>
              </div>
              <div class="perk">
                <div class="perk-icon perk-icon-tl"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--tl)" stroke-width="2.2">
                    <polyline points="3,6 5,6 21,6" />
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                  </svg></div>
                <div><strong>Notas de crédito y débito</strong><span>Gestiona devoluciones y ajustes sin perder la trazabilidad.</span></div>
              </div>
            </div>
          </div>
          <div class="comp-cards fade-up" style="transition-delay:.12s">
            <div class="comp-card">
              <div class="comp-badge cb-b">BOL</div>
              <div class="comp-info"><strong>Boleta de Venta</strong><span>Para consumidores finales. Sin datos adicionales del cliente.</span></div>
            </div>
            <div class="comp-card">
              <div class="comp-badge cb-f">FAC</div>
              <div class="comp-info"><strong>Factura Electrónica</strong><span>Para empresas. Incluye desglose de IGV automático.</span></div>
            </div>
            <div class="comp-card">
              <div class="comp-badge cb-t">TKT</div>
              <div class="comp-info"><strong>Ticket de Venta</strong><span>Para ventas rápidas en mostrador, sin complicaciones.</span></div>
            </div>
            <div class="comp-card">
              <div class="comp-badge cb-nc">N/C</div>
              <div class="comp-info"><strong>Nota de Crédito / Débito</strong><span>Gestión de devoluciones vinculadas al comprobante original.</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- STEPS -->
    <section id="como-funciona">
      <div class="wrap">
        <div class="sec-head fade-up" style="text-align:center">
          <span class="sec-tag sec-tag-tl">Proceso</span>
          <h2 class="sec-h">Empezar es muy sencillo</h2>
          <p class="sec-p" style="margin:0 auto">Sin instalaciones ni configuraciones complejas. Tu negocio listo para vender en tres pasos.</p>
        </div>
        <div class="steps-grid">
          <div class="step-card fade-up">
            <div class="step-illo">
              <svg viewBox="0 0 96 96" fill="none">
                <rect x="18" y="32" width="60" height="50" rx="4" fill="#FEF0E5" stroke="var(--or)" stroke-width="2.5" />
                <rect x="30" y="48" width="14" height="14" rx="2" fill="white" stroke="var(--or)" stroke-width="2" />
                <rect x="52" y="48" width="14" height="14" rx="2" fill="white" stroke="var(--or)" stroke-width="2" />
                <rect x="36" y="68" width="24" height="14" rx="2" fill="var(--or)" opacity=".3" />
                <path d="M14 32 48 14 82 32" stroke="var(--or)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <line x1="38" y1="22" x2="58" y2="22" stroke="var(--or)" stroke-width="2" stroke-linecap="round" opacity=".4" />
                <line x1="40" y1="27" x2="56" y2="27" stroke="var(--or)" stroke-width="1.5" stroke-linecap="round" opacity=".3" />
              </svg>
              <div class="step-num-badge sn1">1</div>
            </div>
            <h3 class="step-title">Crea tu empresa</h3>
            <p class="step-desc">Registra tu negocio, configura tu nombre y series de comprobantes. Tu subdominio propio queda activo al instante.</p>
          </div>
          <div class="step-card fade-up" style="transition-delay:.1s">
            <div class="step-illo">
              <svg viewBox="0 0 96 96" fill="none">
                <rect x="14" y="46" width="32" height="30" rx="3" fill="#E0F4FA" stroke="var(--tl)" stroke-width="2.2" />
                <rect x="50" y="46" width="32" height="30" rx="3" fill="#E0F4FA" stroke="var(--tl)" stroke-width="2.2" />
                <rect x="32" y="26" width="32" height="30" rx="3" fill="#CCF0F8" stroke="var(--tl)" stroke-width="2.2" />
                <line x1="14" y1="56" x2="46" y2="56" stroke="var(--tl)" stroke-width="1.8" stroke-linecap="round" opacity=".5" />
                <line x1="50" y1="56" x2="82" y2="56" stroke="var(--tl)" stroke-width="1.8" stroke-linecap="round" opacity=".5" />
                <line x1="32" y1="36" x2="64" y2="36" stroke="var(--tl)" stroke-width="1.8" stroke-linecap="round" opacity=".5" />
                <rect x="40" y="34" width="16" height="12" rx="2" fill="white" stroke="var(--tl)" stroke-width="1.5" opacity=".7" />
                <circle cx="44" cy="38" r="2" fill="var(--tl)" opacity=".5" />
                <path d="M40 44 44 40 48 43 50 41 56 44" stroke="var(--tl)" stroke-width="1.2" opacity=".5" />
              </svg>
              <div class="step-num-badge sn2">2</div>
            </div>
            <h3 class="step-title">Carga tus productos</h3>
            <p class="step-desc">Agrega productos con fotos, variantes, tallas, colores, precios y stock. Rápido y sin complicaciones técnicas.</p>
          </div>
          <div class="step-card fade-up" style="transition-delay:.2s">
            <div class="step-illo">
              <svg viewBox="0 0 96 96" fill="none">
                <circle cx="38" cy="76" r="6" fill="var(--tl)" opacity=".3" stroke="var(--tl)" stroke-width="2" />
                <circle cx="66" cy="76" r="6" fill="var(--tl)" opacity=".3" stroke="var(--tl)" stroke-width="2" />
                <path d="M16 20h8l10 36h28l8-26H30" stroke="var(--tl)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="72" cy="28" r="14" fill="#FEF0E5" stroke="var(--or)" stroke-width="2" />
                <text x="72" y="33" text-anchor="middle" font-size="14" font-weight="900" fill="var(--or)" font-family="system-ui">S/</text>
                <path d="M20 28 22 24 24 28 22 32z" fill="var(--gold)" opacity=".7" />
                <path d="M28 16 29.5 12 31 16 29.5 20z" fill="var(--or)" opacity=".5" />
              </svg>
              <div class="step-num-badge sn3">3</div>
            </div>
            <h3 class="step-title">¡Empieza a vender!</h3>
            <p class="step-desc">PDV en mostrador y tienda online sincronizados en tiempo real. Cobra, emite comprobantes y revisa tus reportes.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- PRICING -->
    <section id="planes">
      <div class="wrap">
        <div class="sec-head fade-up" style="text-align:center">
          <span class="sec-tag sec-tag-or">Planes y precios</span>
          <h2 class="sec-h">Elige el plan para tu negocio</h2>
          <p class="sec-p" style="margin:0 auto">Todos los planes incluyen PDV, tienda online, inventario y comprobantes. Sin permanencia mínima ni contratos. Cancela cuando quieras.</p>
        </div>
        <div class="plan-grid">
          @foreach($planes as $plan)
          @php
          $n      = $loop->iteration;
          $featured = $n === 2;
          $multiLoc = $plan->maximo_locales > 1;
          $waMsg  = rawurlencode("Hola, me interesa el plan {$plan->nombre} de {$cfg_nombre}");
          $delay  = $loop->index > 0 ? "transition-delay:.{$loop->index}s" : '';
          @endphp
          <div class="plan-card p{{ $n }}{{ $featured ? ' featured' : '' }} fade-up"{{ $delay ? " style=\"{$delay}\"" : '' }}>
            @if($featured)<div class="plan-badge">Más popular</div>@endif

            <div class="plan-eyebrow">Plan {{ $n }}</div>
            <div class="plan-name">{{ $plan->nombre }}</div>
            @if($plan->subtitulo)
            <p class="plan-subtitle">{{ $plan->subtitulo }}</p>
            @endif

            <div class="plan-price-block">
              <span class="plan-currency">S/</span>
              <span class="plan-amount">{{ number_format($plan->precio, 0) }}</span>
              <span class="plan-period">/mes</span>
            </div>
            <div class="plan-price-note">
              {{ $plan->maximo_usuarios }} {{ $plan->maximo_usuarios == 1 ? 'usuario' : 'usuarios' }}
              · {{ $multiLoc ? 'hasta ' . $plan->maximo_locales . ' sucursales' : '1 sucursal' }}
            </div>

            <hr class="plan-hr">

            <div class="plan-feat plan-feat-rich">
              {!! $plan->descripcion !!}
            </div>

            <a href="https://wa.me/{{ $cfg_whatsapp }}?text={{ $waMsg }}" target="_blank" rel="noopener"
              class="plan-cta">Solicitar acceso</a>
          </div>
          @endforeach
        </div>

        <!-- ADDONS -->
        <div class="plan-addons fade-up">
          <div>
            <div class="addon-h">Precio adicional</div>
            <p class="addon-p">Amplía tu plan cuando tu negocio lo necesite. Sin contratos, se activa al instante.</p>
          </div>
          <div class="addons-list">
            <div class="addon-item">
              <div style="width:38px;height:38px;background:var(--or-l);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--or)" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg></div>
              <div class="addon-detail"><strong>Usuario adicional</strong><span>Agrega colaboradores a tu plan actual</span></div>
              <div class="addon-price">S/ 15<small>/mes</small></div>
            </div>
            <div class="addon-item">
              <div style="width:38px;height:38px;background:var(--tl-l);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tl)" stroke-width="2">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                </svg></div>
              <div class="addon-detail"><strong>Sucursal adicional</strong><span>Gestiona más ubicaciones desde el mismo panel</span></div>
              <div class="addon-price">S/ 30<small>/mes</small></div>
            </div>
          </div>
        </div>
        <p class="fade-up" style="text-align:center;margin-top:1.5rem;font-size:.84rem;color:var(--ink3)">Todos los planes incluyen soporte estándar vía WhatsApp · <a href="https://wa.me/{{ $cfg_whatsapp }}" target="_blank" style="color:var(--or);font-weight:600">Contáctanos</a> para consultar la tarifa de implementación</p>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq">
      <div class="wrap">
        <div class="sec-head fade-up" style="text-align:center">
          <span class="sec-tag sec-tag-tl">FAQ</span>
          <h2 class="sec-h">Preguntas frecuentes</h2>
          <p class="sec-p" style="margin:0 auto">Resolvemos las dudas más comunes antes de que empieces.</p>
        </div>
        <div class="faq-list fade-up" style="transition-delay:.1s">
          <div class="faq-item">
            <button class="faq-q" onclick="toggleFaq(this)">
              ¿Qué necesito para empezar a usar TUKIPU?
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M6 9l6 6 6-6" />
              </svg>
            </button>
            <div class="faq-a">
              <p>Solo necesitas un dispositivo con internet (PC, tablet o celular) y 10 minutos para registrar tu empresa. No hay instalaciones, servidores ni configuraciones técnicas. Nosotros nos encargamos de todo.</p>
            </div>
          </div>
          <div class="faq-item">
            <button class="faq-q" onclick="toggleFaq(this)">
              ¿Puedo usar el sistema en varios dispositivos al mismo tiempo?
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M6 9l6 6 6-6" />
              </svg>
            </button>
            <div class="faq-a">
              <p>Sí. TUKIPU es 100% web y puedes acceder desde cualquier dispositivo con navegador. Varios usuarios pueden operar simultáneamente, cada uno con su propia cuenta dentro del plan.</p>
            </div>
          </div>
          <div class="faq-item">
            <button class="faq-q" onclick="toggleFaq(this)">
              ¿Mi tienda online y el PDV están sincronizados?
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M6 9l6 6 6-6" />
              </svg>
            </button>
            <div class="faq-a">
              <p>Sí, en tiempo real. Cuando realizas una venta en el punto de venta físico, el stock se descuenta automáticamente de la tienda online, y viceversa. Un solo inventario para todos tus canales de venta.</p>
            </div>
          </div>
          <div class="faq-item">
            <button class="faq-q" onclick="toggleFaq(this)">
              ¿Puedo cambiar de plan en cualquier momento?
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M6 9l6 6 6-6" />
              </svg>
            </button>
            <div class="faq-a">
              <p>Claro que sí. Puedes subir o bajar de plan cuando lo necesites sin penalidades. El cambio se refleja en tu próximo período de facturación. Contáctanos por WhatsApp y lo gestionamos de inmediato.</p>
            </div>
          </div>
          <div class="faq-item">
            <button class="faq-q" onclick="toggleFaq(this)">
              ¿Qué pasa con mis datos si decido cancelar?
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M6 9l6 6 6-6" />
              </svg>
            </button>
            <div class="faq-a">
              <p>Tus datos son tuyos. Puedes exportar tu información en cualquier momento antes de cancelar. Conservamos los datos durante 30 días posteriores a la cancelación por si necesitas recuperarlos.</p>
            </div>
          </div>
          <div class="faq-item">
            <button class="faq-q" onclick="toggleFaq(this)">
              ¿El sistema funciona sin conexión a internet?
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M6 9l6 6 6-6" />
              </svg>
            </button>
            <div class="faq-a">
              <p>TUKIPU requiere conexión a internet para sincronizar ventas e inventario en tiempo real entre todos tus dispositivos. Recomendamos una conexión estable. Para el PDV, una conexión de datos básica es suficiente.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-sec" id="cta">
      <div class="cta-deco-or"></div>
      <div class="cta-deco-tl"></div>
      <div class="wrap cta-inner">
        <div class="cta-eye">¿Listo para crecer?</div>
        <h2 class="cta-h">Lleva tu negocio<br>al siguiente nivel</h2>
        <p class="cta-p">Únete a los comercios que ya gestionan ventas en físico y online desde una sola plataforma. Solicita tu acceso hoy.</p>
        <div class="cta-btns">
          <a href="mailto:{{ $cfg_email }}" class="btn-grad">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
              <path d="M5 12h14M12 5l7 7-7 7" />
            </svg>
            Solicitar acceso gratis
          </a>
          <a href="#planes" class="btn-ghost">Ver planes</a>
        </div>
      </div>
    </section>

  </main>

  <footer>
    <div class="wrap">
      <div class="foot-grid">
        <div>
          <div class="foot-logo">
            <img src="{{ $cfg_logoUrl }}" alt="{{ $cfg_nombre }}" style="height:36px;width:auto;display:block">
          </div>
          <p class="foot-tag">{{ $cfg_slogan ?: 'Sistema POS y tienda online para comercios minoristas. Conecta lo físico con lo digital.' }}</p>
        </div>
        <div class="foot-col">
          <h3>Producto</h3><a href="#caracteristicas">Características</a><a href="#tienda-online">Tienda online</a><a href="#como-funciona">Cómo funciona</a><a href="#planes">Planes</a>
        </div>
        <div class="foot-col">
          <h3>Para quién</h3><a href="#industrias">Ropa y moda</a><a href="#industrias">Minimarket</a><a href="#industrias">Ferretería</a><a href="#industrias">Cafetería</a>
        </div>
        <div class="foot-col">
          <h3>Contacto</h3>
          @if($cfg_email)<a href="mailto:{{ $cfg_email }}">{{ $cfg_email }}</a>@endif
          @if($cfg_telefono)<a href="tel:{{ $cfg_telefono }}">{{ $cfg_telefono }}</a>@endif

          {{-- Íconos de redes sociales --}}
          @php
            $hasSocial = $cfg_whatsapp || $cfg_facebook || $cfg_instagram || $cfg_tiktok || $cfg_youtube || $cfg_linkedin || $cfg_twitter || $cfg_web;
          @endphp
          @if($hasSocial)
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;align-items:center;">
            @if($cfg_whatsapp)
            <a href="{{ $waFabUrl }}" target="_blank" rel="noopener" title="WhatsApp" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#25D366'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            @endif
            @if($cfg_facebook)
            <a href="https://facebook.com/{{ $cfg_facebook }}" target="_blank" rel="noopener" title="Facebook" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#1877F2'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            @endif
            @if($cfg_instagram)
            <a href="https://instagram.com/{{ ltrim($cfg_instagram,'@') }}" target="_blank" rel="noopener" title="Instagram" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#E4405F'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            @endif
            @if($cfg_tiktok)
            <a href="https://tiktok.com/@{{ ltrim($cfg_tiktok,'@') }}" target="_blank" rel="noopener" title="TikTok" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#69C9D0'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.73a4.85 4.85 0 01-1.01-.04z"/></svg>
            </a>
            @endif
            @if($cfg_youtube)
            <a href="{{ $cfg_youtube }}" target="_blank" rel="noopener" title="YouTube" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#FF0000'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>
            </a>
            @endif
            @if($cfg_linkedin)
            <a href="{{ $cfg_linkedin }}" target="_blank" rel="noopener" title="LinkedIn" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#0A66C2'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
            @endif
            @if($cfg_twitter)
            <a href="https://x.com/{{ ltrim($cfg_twitter,'@') }}" target="_blank" rel="noopener" title="Twitter / X" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.763l7.728-8.835L1.254 2.25H8.08l4.259 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            @endif
            @if($cfg_web)
            <a href="{{ $cfg_web }}" target="_blank" rel="noopener" title="Sitio web" style="color:rgba(255,255,255,.55);transition:color .2s;line-height:0" onmouseover="this.style.color='#0097B5'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
            </a>
            @endif
          </div>
          @endif
        </div>
      </div>
      <div class="foot-btm">
        <span>© {{ date('Y') }} {{ $cfg_nombre }}. Todos los derechos reservados.</span>
        <span>Hecho con ❤ para el comercio peruano</span>
      </div>
    </div>
  </footer>

  <script src="{{ asset('landing/landing.js') }}" defer></script>

  <a href="{{ $waFabUrl }}" target="_blank" rel="noopener" class="wsp-fab" aria-label="Contactar por WhatsApp">
    <div class="wsp-pulse"></div>
    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  </a>
</body>

</html>