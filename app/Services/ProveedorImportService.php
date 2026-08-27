<?php

namespace App\Services;

use App\Enums\EstadoGeneral;
use App\Enums\TipoDocumento;
use App\Models\Proveedor;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProveedorImportService
{
    private const SKIP_TIPO   = ['tipo_documento', 'tipo documento', 'tipo_doc', 'tipo doc'];
    private const SKIP_NUMERO = ['numero_documento', 'numero documento', 'nro_doc', 'nrodoc'];

    public function importar(string $ruta, int $empresaId): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestDataRow();

        $creados       = 0;
        $actualizados  = 0;
        $omitidos      = 0;
        $errores       = [];
        $filasConDatos = 0;

        for ($row = 1; $row <= $maxRow; $row++) {
            $tipoDoc = trim((string) $sheet->getCell("A{$row}")->getValue());
            $numDoc  = trim((string) $sheet->getCell("B{$row}")->getValue());
            $nombre  = trim((string) $sheet->getCell("C{$row}")->getValue());

            // Fila vacía
            if ($tipoDoc === '' && $numDoc === '' && $nombre === '') {
                continue;
            }

            // Fila instrucción larga (texto del banner)
            if (mb_strlen($tipoDoc) > 30) {
                continue;
            }

            // Fila cabecera
            $tipoNorm = strtolower(preg_replace('/[\s*()]+/', '', $tipoDoc));
            if (in_array($tipoNorm, self::SKIP_TIPO, true)) {
                continue;
            }
            $numNorm = strtolower(preg_replace('/[\s*()]+/', '', $numDoc));
            if (in_array($numNorm, self::SKIP_NUMERO, true)) {
                continue;
            }
            if (in_array(strtolower(trim($nombre)), ['nombre', 'nombres', 'nombre (*)'], true)) {
                continue;
            }

            $filasConDatos++;

            if ($numDoc === '') {
                $errores[] = "Fila {$row}: NUMERO_DOCUMENTO vacío, se omite.";
                $omitidos++;
                continue;
            }

            if ($nombre === '') {
                $errores[] = "Fila {$row}: NOMBRE vacío, se omite.";
                $omitidos++;
                continue;
            }

            $tipoEnum = null;
            if ($tipoDoc !== '') {
                $tipoEnum = TipoDocumento::tryFrom(strtolower($tipoDoc));
                if ($tipoEnum === null) {
                    $errores[] = "Fila {$row}: TIPO_DOCUMENTO '{$tipoDoc}' inválido (usa dni o ruc), se omite.";
                    $omitidos++;
                    continue;
                }
            }

            $correo      = trim((string) $sheet->getCell("D{$row}")->getValue());
            $telefono    = trim((string) $sheet->getCell("E{$row}")->getValue());
            $direccion   = trim((string) $sheet->getCell("F{$row}")->getValue());
            $departamento = trim((string) $sheet->getCell("G{$row}")->getValue());

            $datos = array_filter([
                'nombre'       => $nombre,
                'correo'       => $correo      ?: null,
                'telefono'     => $telefono    ?: null,
                'direccion'    => $direccion   ?: null,
                'departamento' => $departamento ?: null,
            ], fn ($v) => $v !== null);

            if ($tipoEnum !== null) {
                $datos['tipo_documento'] = $tipoEnum;
            }

            $existente = Proveedor::where('empresa_id', $empresaId)
                ->where('numero_documento', $numDoc)
                ->first();

            if ($existente) {
                $existente->update($datos);
                $actualizados++;
            } else {
                Proveedor::create(array_merge($datos, [
                    'empresa_id'       => $empresaId,
                    'numero_documento' => $numDoc,
                    'estado'           => EstadoGeneral::Activo,
                    'user_id'          => auth()->id(),
                ]));
                $creados++;
            }
        }

        return compact('creados', 'actualizados', 'omitidos', 'errores', 'filasConDatos');
    }
}
