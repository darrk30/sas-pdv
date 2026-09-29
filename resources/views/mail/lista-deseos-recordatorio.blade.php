<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tu lista de deseos</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#111827;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;padding:32px 16px;">
  <tr>
    <td align="center">
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

        {{-- ── Header ── --}}
        <tr>
          <td align="center" style="padding-bottom:24px;">
            @if($empresa->logo)
              @php
                $logoUrl = \Illuminate\Support\Facades\Storage::url($empresa->logo);
                if (!str_starts_with($logoUrl, 'http')) {
                    $logoUrl = config('app.url') . $logoUrl;
                }
              @endphp
              <img src="{{ $logoUrl }}" alt="{{ $empresa->name }}"
                   style="max-height:60px;max-width:200px;object-fit:contain;display:block;margin:0 auto;">
            @else
              <p style="margin:0;font-size:22px;font-weight:700;letter-spacing:-.3px;color:#111827;">
                {{ $empresa->name }}
              </p>
            @endif
          </td>
        </tr>

        {{-- ── Card principal ── --}}
        <tr>
          <td style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">

            {{-- Banner superior --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="background:linear-gradient(135deg,#fbbf24 0%,#f59e0b 100%);padding:32px 32px 28px;text-align:center;">
                  <p style="margin:0 0 6px;font-size:32px;">💛</p>
                  <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;line-height:1.3;">
                    ¡Hola, {{ $clienteNombre }}!
                  </p>
                  <p style="margin:8px 0 0;font-size:15px;color:rgba(255,255,255,.9);line-height:1.5;">
                    Tienes {{ $items->count() }} {{ $items->count() === 1 ? 'producto guardado' : 'productos guardados' }} en tu lista de deseos.
                  </p>
                </td>
              </tr>
            </table>

            {{-- Cuerpo --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding:28px 32px;">

                  @if ($mensaje)
                  <div style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.7;">
                    {!! $mensaje !!}
                  </div>
                  @else
                  <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.6;">
                    No queremos que te quedes sin ellos. Aquí te recordamos lo que tenías en mente:
                  </p>
                  @endif

                  {{-- Lista de productos --}}
                  @foreach ($items as $item)
                  @php
                      $varianteTexto = $item->variante
                          ? $item->variante->valores->map(fn($pav) => $pav->valor?->nombre)->filter()->implode(' / ')
                          : null;
                      $precio = $item->variante?->precio_final ?? $item->producto?->precio_con_descuento ?? $item->producto?->precio_venta;
                      $imgUrl = null;
                      if ($item->variante?->imagen) {
                          $imgUrl = \Illuminate\Support\Facades\Storage::url($item->variante->imagen);
                      } elseif ($item->producto?->logo) {
                          $imgUrl = \Illuminate\Support\Facades\Storage::url($item->producto->logo);
                      } elseif ($item->producto?->galeriaProductos?->first()?->imagen_path) {
                          $imgUrl = \Illuminate\Support\Facades\Storage::url($item->producto->galeriaProductos->first()->imagen_path);
                      }
                      if ($imgUrl && !str_starts_with($imgUrl, 'http')) {
                          $imgUrl = config('app.url') . $imgUrl;
                      }
                  @endphp
                  <table width="100%" cellpadding="0" cellspacing="0" border="0"
                         style="margin-bottom:10px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <tr>
                      {{-- Imagen --}}
                      @if ($imgUrl)
                      <td width="64" style="padding:12px 0 12px 12px;vertical-align:middle;">
                        <img src="{{ $imgUrl }}" width="52" height="52"
                             style="border-radius:8px;object-fit:cover;display:block;" alt="">
                      </td>
                      @endif
                      {{-- Nombre + variante --}}
                      <td style="padding:12px 12px;vertical-align:middle;">
                        <p style="margin:0;font-size:14px;font-weight:600;color:#111827;line-height:1.3;">
                          {{ $item->producto?->nombre ?? '—' }}
                        </p>
                        @if ($varianteTexto)
                        <p style="margin:3px 0 0;font-size:12px;color:#6b7280;">{{ $varianteTexto }}</p>
                        @endif
                      </td>
                      {{-- Cantidad + precio --}}
                      <td width="80" style="padding:12px 14px 12px 0;vertical-align:middle;text-align:right;white-space:nowrap;">
                        <p style="margin:0;font-size:13px;color:#6b7280;">× {{ $item->cantidad }}</p>
                        @if ($precio)
                        <p style="margin:4px 0 0;font-size:14px;font-weight:700;color:#111827;">
                          S/ {{ number_format($precio, 2) }}
                        </p>
                        @endif
                      </td>
                    </tr>
                  </table>
                  @endforeach

                  {{-- CTA --}}
                  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
                    <tr>
                      <td align="center">
                        @php
                            $appUrl    = config('app.url');
                            $scheme    = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';
                            $host      = parse_url($appUrl, PHP_URL_HOST) ?? request()->getHost();
                            $tiendaUrl = $scheme . '://' . ($empresa->slug ?? 'tienda') . '.' . $host . '/lista-deseos';
                        @endphp
                        <a href="{{ $tiendaUrl }}"
                           style="display:inline-block;background:#f59e0b;color:#ffffff;font-size:15px;font-weight:700;
                                  text-decoration:none;padding:14px 36px;border-radius:10px;letter-spacing:.2px;">
                          Ver mi lista de deseos →
                        </a>
                      </td>
                    </tr>
                  </table>

                  <p style="margin:24px 0 0;font-size:13px;color:#9ca3af;text-align:center;line-height:1.6;">
                    Si tienes alguna duda, responde este correo o escríbenos por WhatsApp.<br>
                    Con cariño, el equipo de <strong>{{ $empresa->name }}</strong> 🛍️
                  </p>

                </td>
              </tr>
            </table>

          </td>
        </tr>

        {{-- ── Footer ── --}}
        <tr>
          <td style="padding:20px 0 0;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.7;">
              Recibiste este correo porque tienes una cuenta en <strong>{{ $empresa->name }}</strong>.<br>
              Si no quieres recibir estos recordatorios, puedes ignorarlo.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
