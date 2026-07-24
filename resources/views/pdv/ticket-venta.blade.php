<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket {{ ($venta->serie?->serie ?? '') . '-' . $venta->correlativo }}</title>
<style>
    /* ── Reset y base ───────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        width: 80mm;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        color: #000;
        background: #fff;
        line-height: 1.4;
    }

    /* ── Contenedor principal ───────────────────────────────── */
    .tk {
        width: 80mm;
        padding: 5mm 4mm;
    }

    /* ── Cabecera empresa ───────────────────────────────────── */
    .tk-empresa {
        text-align: center;
        margin-bottom: 3mm;
    }
    .tk-logo {
        max-width: 45mm;
        max-height: 18mm;
        object-fit: contain;
        display: block;
        margin: 0 auto 2mm;
    }
    .tk-empresa-nombre {
        font-size: 13px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .5px;
    }
    .tk-empresa-info {
        font-size: 10px;
        margin-top: 1mm;
        line-height: 1.5;
    }

    /* ── Separadores ────────────────────────────────────────── */
    .tk-sep { border: none; border-top: 1px dashed #000; margin: 2.5mm 0; }
    .tk-sep-solid { border: none; border-top: 1px solid #000; margin: 2mm 0; }

    /* ── Comprobante ────────────────────────────────────────── */
    .tk-comprobante {
        text-align: center;
        margin-bottom: 1mm;
    }
    .tk-comprobante-tipo {
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .tk-comprobante-num {
        font-size: 11px;
        font-weight: bold;
    }
    .tk-comprobante-fecha {
        font-size: 10px;
        color: #333;
    }

    /* ── Cliente ────────────────────────────────────────────── */
    .tk-cliente {
        font-size: 10px;
        margin-bottom: 1mm;
    }
    .tk-cliente-titulo {
        font-weight: bold;
        font-size: 10px;
        margin-bottom: .5mm;
        text-transform: uppercase;
    }
    .tk-cliente-fila {
        display: flex;
        gap: 2mm;
    }
    .tk-cliente-label {
        min-width: 14mm;
        color: #444;
    }

    /* ── Tabla ítems ────────────────────────────────────────── */
    .tk-tabla {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
        margin: 1mm 0;
    }
    .tk-tabla th {
        text-align: left;
        font-weight: bold;
        font-size: 10px;
        padding: 0 1mm 1mm;
        border-bottom: 1px solid #000;
    }
    .tk-tabla th.r, .tk-tabla td.r { text-align: right; }
    .tk-tabla td {
        padding: 1mm 1mm;
        vertical-align: top;
    }
    .tk-tabla .td-desc {
        word-break: break-word;
        max-width: 28mm;
    }
    .tk-tabla .td-und {
        font-size: 9px;
        color: #444;
        white-space: nowrap;
    }
    .tk-tabla tr:last-child td { padding-bottom: 2mm; }

    /* ── Subtotales ─────────────────────────────────────────── */
    .tk-totales {
        font-size: 10px;
        margin-top: 1mm;
    }
    .tk-totales-fila {
        display: flex;
        justify-content: space-between;
        padding: .3mm 0;
    }
    .tk-totales-fila--grande {
        font-size: 13px;
        font-weight: bold;
        margin-top: 1mm;
        border-top: 1px solid #000;
        padding-top: 1.5mm;
    }

    /* ── Pagos ──────────────────────────────────────────────── */
    .tk-pagos-titulo {
        font-weight: bold;
        font-size: 10px;
        text-transform: uppercase;
        margin-bottom: .5mm;
    }
    .tk-pago-fila {
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        padding: .3mm 0;
    }
    .tk-pago-ref {
        font-size: 9px;
        color: #555;
    }

    /* ── Pie ────────────────────────────────────────────────── */
    .tk-gracias {
        text-align: center;
        font-size: 11px;
        font-weight: bold;
        margin: 2mm 0 1mm;
    }
    .tk-no-comprobante {
        text-align: center;
        font-size: 9px;
        font-style: italic;
        line-height: 1.5;
        margin-top: 2mm;
        padding-top: 1mm;
        border-top: 1px dashed #000;
    }

    /* ── QR y datos FE ──────────────────────────────────────── */
    .tk-letras {
        font-size: 9px;
        font-style: italic;
        margin: 2mm 0 1mm;
        line-height: 1.4;
    }
    .tk-qr-wrap { text-align: center; margin: 2mm 0; }
    .tk-qr-wrap img { width: 38mm; height: 38mm; }
    .tk-qr-label { text-align: center; font-size: 9px; color: #444; margin-top: 1mm; }
    .tk-hash-fe {
        font-size: 7px; color: #555;
        font-family: 'Courier New', monospace;
        word-break: break-all; margin-top: 1mm;
    }
    .tk-nota-fe {
        text-align: center; font-size: 9px;
        font-style: italic; margin-top: 1mm;
        line-height: 1.4; color: #333;
    }

    /* ── Print ──────────────────────────────────────────────── */
    @media print {
        @page {
            size: 80mm auto;
            margin: 3mm 0;
        }
        html, body { width: 80mm; }
        .no-print { display: none !important; }
    }

    /* ── Botón imprimir (solo pantalla) ─────────────────────── */
    .btn-imprimir {
        display: block;
        width: 100%;
        padding: 6px;
        margin-top: 4mm;
        background: #1e293b;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        font-family: sans-serif;
    }
</style>
</head>
<body>
@php
    use App\Enums\TipoComprobante;
    $serie      = $venta->serie;
    $tipoEnum   = $serie?->tipo;
    $comprobante = ($serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
    $esFactura  = $tipoEnum === TipoComprobante::Factura;
    $esBoleta   = $tipoEnum === TipoComprobante::Boleta;
    $esTicket   = $tipoEnum === TipoComprobante::Ticket;
    $esSin      = $tipoEnum === TipoComprobante::SinComprobante || $tipoEnum === null;
    $tieneIgv   = ($esFactura || $esBoleta) && (float) $venta->igv > 0;

    $clienteTel  = $venta->cliente?->telefono;
    $clienteDir  = $venta->cliente?->direccion ?? null;
@endphp

<div class="tk">

    {{-- ══ CABECERA EMPRESA ══ --}}
    <div class="tk-empresa">
        @if ($empresa->logo)
            <img src="{{ asset('storage/' . $empresa->logo) }}" class="tk-logo" alt="Logo">
        @endif
        <div class="tk-empresa-nombre">{{ $empresa->name }}</div>
        <div class="tk-empresa-info">
            @if ($empresa->ruc) RUC: {{ $empresa->ruc }}<br>@endif
            @if ($empresa->direccion) {{ $empresa->direccion }}<br>@endif
            @if ($empresa->provincia || $empresa->departamento)
                {{ implode(', ', array_filter([$empresa->provincia ?? null, $empresa->departamento ?? null])) }}<br>
            @endif
            @if ($empresa->telefono) Tel: {{ $empresa->telefono }}@endif
        </div>
    </div>

    <hr class="tk-sep-solid">

    {{-- ══ COMPROBANTE ══ --}}
    <div class="tk-comprobante">
        <div class="tk-comprobante-tipo">
            @if ($esFactura) FACTURA ELECTRÓNICA
            @elseif ($esBoleta) BOLETA DE VENTA ELECTRÓNICA
            @elseif ($esTicket) TICKET DE VENTA
            @else COMPROBANTE DE VENTA
            @endif
        </div>
        <div class="tk-comprobante-num">{{ $comprobante }}</div>
        <div class="tk-comprobante-fecha">
            {{ $venta->fecha_emision?->format('d/m/Y H:i') ?? $venta->created_at->format('d/m/Y H:i') }}
        </div>
    </div>

    <hr class="tk-sep">

    {{-- ══ CLIENTE ══ --}}
    @php
        $tieneCliente = $venta->cliente_nombre || $venta->cliente_num_doc;
    @endphp
    @if ($tieneCliente)
    <div class="tk-cliente">
        <div class="tk-cliente-titulo">Cliente</div>
        @if ($venta->cliente_nombre)
        <div class="tk-cliente-fila">
            <span class="tk-cliente-label">Nombre:</span>
            <span>{{ $venta->cliente_nombre }}</span>
        </div>
        @endif
        @if ($venta->cliente_num_doc)
        <div class="tk-cliente-fila">
            <span class="tk-cliente-label">{{ strtoupper($venta->cliente_tipo_doc ?? 'Doc') }}:</span>
            <span>{{ $venta->cliente_num_doc }}</span>
        </div>
        @endif
        @if ($clienteTel)
        <div class="tk-cliente-fila">
            <span class="tk-cliente-label">Tel:</span>
            <span>{{ $clienteTel }}</span>
        </div>
        @endif
        @if ($clienteDir)
        <div class="tk-cliente-fila">
            <span class="tk-cliente-label">Dir:</span>
            <span>{{ $clienteDir }}</span>
        </div>
        @endif
    </div>
    <hr class="tk-sep">
    @endif

    {{-- ══ ÍTEMS ══ --}}
    <table class="tk-tabla">
        <thead>
            <tr>
                <th>Cant</th>
                <th>Und</th>
                <th>Descripción</th>
                <th class="r">P.U.</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $d)
            @php
                $cant    = (float) $d->cantidad;
                $cantFmt = $cant == floor($cant)
                    ? number_format($cant, 0)
                    : rtrim(rtrim(number_format($cant, 3, '.', ''), '0'), '.');
                $simbolo = $d->producto?->unidadMedida?->simbolo
                    ?? $d->variante?->producto?->unidadMedida?->simbolo
                    ?? '';
            @endphp
            <tr>
                <td>{{ $cantFmt }}</td>
                <td class="td-und">{{ $simbolo }}</td>
                <td class="td-desc">{{ $d->descripcion }}</td>
                <td class="r">{{ number_format($d->precio_unitario, 2) }}</td>
                <td class="r">{{ number_format($d->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <hr class="tk-sep-solid">

    {{-- ══ TOTALES ══ --}}
    <div class="tk-totales">
        @if ((float)$venta->descuento_total > 0)
        <div class="tk-totales-fila">
            <span>Descuento</span>
            <span>- S/ {{ number_format($venta->descuento_total, 2) }}</span>
        </div>
        @endif

        @if ($tieneIgv)
        <div class="tk-totales-fila">
            <span>Op. Gravada</span>
            <span>S/ {{ number_format($venta->op_gravadas, 2) }}</span>
        </div>
        <div class="tk-totales-fila">
            <span>IGV (18%)</span>
            <span>S/ {{ number_format($venta->igv, 2) }}</span>
        </div>
        @endif

        <div class="tk-totales-fila tk-totales-fila--grande">
            <span>TOTAL</span>
            <span>S/ {{ number_format($venta->total, 2) }}</span>
        </div>
    </div>

    <hr class="tk-sep">

    {{-- ══ MÉTODOS DE PAGO ══ --}}
    @php
        $pagos = $venta->pagos->filter(fn($p) => $p->monto > 0);
    @endphp
    @if ($pagos->isNotEmpty())
    <div>
        <div class="tk-pagos-titulo">Forma de pago</div>
        @foreach ($pagos as $pago)
        <div class="tk-pago-fila">
            <div>
                <div>{{ $pago->metodoPago?->nombre ?? '—' }}</div>
                @if ($pago->referencia)
                    <div class="tk-pago-ref">Ref: {{ $pago->referencia }}</div>
                @endif
            </div>
            <span>S/ {{ number_format($pago->monto, 2) }}</span>
        </div>
        @endforeach

        @if ((float)$venta->saldo_pendiente > 0)
        <div class="tk-pago-fila" style="font-style:italic">
            <span>Saldo pendiente</span>
            <span>S/ {{ number_format($venta->saldo_pendiente, 2) }}</span>
        </div>
        @endif
    </div>
    <hr class="tk-sep">
    @endif

    {{-- ══ PIE ══ --}}
    <div class="tk-gracias">¡Gracias por su preferencia!</div>
    <div style="text-align:center;font-size:9px">Vuelva pronto.</div>

    @if ($esTicket || $esSin)
    <div class="tk-no-comprobante">
        Este documento NO constituye<br>
        un comprobante de pago electrónico.
    </div>
    @endif

    {{-- ══ QR + LETRAS (solo boleta/factura electrónica) ══ --}}
    @php $qrBase64 = $qrBase64 ?? null; @endphp
    @if (($esFactura || $esBoleta) && $qrBase64)
    <hr class="tk-sep">
    @if ($venta->total_letras)
    <div class="tk-letras">Son: {{ $venta->total_letras }}</div>
    @endif
    <div class="tk-qr-wrap">
        <img src="{{ $qrBase64 }}" alt="QR SUNAT">
    </div>
    <div class="tk-qr-label">Consulte en sunat.gob.pe</div>
    @if ($venta->hash)
    <div class="tk-hash-fe">Hash: {{ $venta->hash }}</div>
    @endif
    <div class="tk-nota-fe">
        Representación impresa de<br>
        {{ $esFactura ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA' }}
    </div>
    @endif

    {{-- Botón imprimir (no aparece al imprimir) --}}
    <button class="btn-imprimir no-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>

</div>

<script>

    // Auto-imprimir solo si se pasa el parámetro ?print=1
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    }
</script>
</body>
</html>
