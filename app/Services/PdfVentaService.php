<?php

namespace App\Services;

use App\Enums\TipoComprobante;
use App\Models\Empresa;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class PdfVentaService
{
    public function generar(Venta $venta, Empresa $empresa): DomPDF
    {
        $venta->loadMissing([
            'serie',
            'detalles.producto.unidadMedida',
            'detalles.variante.producto.unidadMedida',
            'pagos.metodoPago',
            'cliente',
        ]);

        $tipoEnum = $venta->serie?->tipo;
        $esFE     = in_array($tipoEnum, [TipoComprobante::Boleta, TipoComprobante::Factura])
            && ! empty($venta->qr_data);

        $qrBase64 = $esFE ? $this->generarQr($venta->qr_data) : null;

        // Siempre formato ticket 80 mm; el QR aparece al pie si es FE
        return Pdf::loadView('pdv.ticket-venta-pdf', compact('venta', 'empresa', 'qrBase64'))
            ->setPaper([0, 0, 226.77, 1133.86], 'portrait')
            ->setOption('defaultFont', 'Courier')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 150);
    }

    public function nombreArchivo(Venta $venta): string
    {
        $serie       = $venta->serie?->serie ?? 'TKT';
        $correlativo = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);

        return "ticket-{$serie}-{$correlativo}.pdf";
    }

    public function generarQrParaVenta(Venta $venta): ?string
    {
        $tipo = $venta->serie?->tipo;
        if (! in_array($tipo, [\App\Enums\TipoComprobante::Boleta, \App\Enums\TipoComprobante::Factura])) {
            return null;
        }
        return ! empty($venta->qr_data) ? $this->generarQr($venta->qr_data) : null;
    }

    private function generarQr(string $data): string
    {
        $options = new QROptions([
            'outputType'  => 'png',
            'scale'       => 6,
            'imageBase64' => true,
        ]);

        return (new QRCode($options))->render($data);
    }
}
