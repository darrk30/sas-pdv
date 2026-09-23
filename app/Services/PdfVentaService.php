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
            'sesionCaja.cajero',
            'orden.vendedor.roles',
        ]);

        $tipoEnum = $venta->serie?->tipo;
        $esFE     = in_array($tipoEnum, [TipoComprobante::Boleta, TipoComprobante::Factura])
            && ! empty($venta->qr_data);

        $qrBase64 = $esFE ? $this->generarQr($venta->qr_data) : null;

        $logoBase64 = $this->prepararLogo($empresa);

        // Siempre formato ticket 80 mm; el QR aparece al pie si es FE
        return Pdf::loadView('pdv.ticket-venta-pdf', compact('venta', 'empresa', 'qrBase64', 'logoBase64'))
            ->setPaper([0, 0, 226.77, 1133.86], 'portrait')
            ->setOption('defaultFont', 'Courier')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 96)
;
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

    private function prepararLogo(Empresa $empresa): ?string
    {
        if (! $empresa->logo) {
            return null;
        }

        $path = public_path('storage/' . $empresa->logo);
        if (! file_exists($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }

        [$origW, $origH, $type] = $info;
        $maxW = 300;

        // Si ya es pequeño, solo codifica
        if ($origW <= $maxW) {
            return 'data:' . $info['mime'] . ';base64,' . base64_encode(file_get_contents($path));
        }

        $src = match ($type) {
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => null,
        };

        if (! $src) {
            return null;
        }

        $newW = $maxW;
        $newH = (int) round($origH * ($maxW / $origW));
        $dst  = imagecreatetruecolor($newW, $newH);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        ob_start();
        imagepng($dst, null, 7);
        $imgData = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return 'data:image/png;base64,' . base64_encode($imgData);
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
