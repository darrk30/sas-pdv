<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Services\ProductoExcelTemplateService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoExcelController extends Controller
{
    public function __construct(private ProductoExcelTemplateService $plantillas) {}

    public function descargar(string $tipo): StreamedResponse
    {
        $spreadsheet = match ($tipo) {
            'nuevos'     => $this->plantillas->generarPlantillaNuevos(),
            'actualizar' => $this->plantillas->generarPlantillaActualizar(),
            'precios'    => $this->plantillas->generarPlantillaPrecios(),
            default      => abort(404, 'Tipo de plantilla no válido.'),
        };

        $nombre = "plantilla-productos-{$tipo}.xlsx";

        return response()->streamDownload(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            $nombre,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control'       => 'max-age=0',
                'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
            ]
        );
    }
}
