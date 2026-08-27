<?php

namespace App\Services;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClienteImportService
{
    // Valores que indican fila de cabecera — se saltan (nunca datos reales)
    private const SKIP_TIPO   = ['tipo_documento', 'tipo documento', 'tipo_doc', 'tipo doc'];
    private const SKIP_NUMERO = ['numero_documento', 'numero documento', 'nro_doc', 'nrodoc'];

    public function importar(string $ruta, int $empresaId): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestDataRow();

        $creados        = 0;
        $actualizados   = 0;
        $omitidos       = 0;
        $errores        = [];
        $filasConDatos  = 0; // cuántas filas tenían algo (no vacías, no cabecera)

        for ($row = 1; $row <= $maxRow; $row++) {
            // Leer los tres campos clave
            $tipoDoc = trim((string) $sheet->getCell("A{$row}")->getValue());
            $numDoc  = trim((string) $sheet->getCell("B{$row}")->getValue());
            $nombre  = trim((string) $sheet->getCell("C{$row}")->getValue());

            // Saltar filas completamente vacías
            if ($tipoDoc === '' && $numDoc === '' && $nombre === '') {
                continue;
            }

            // Saltar fila de instrucción (celda A muy larga = texto de descripción)
            if (mb_strlen($tipoDoc) > 30) {
                continue;
            }

            // Saltar fila de cabecera (detectada por columna A o B)
            $tipoDocNorm = strtolower(preg_replace('/[\s*()]+/', '', $tipoDoc));
            if (in_array($tipoDocNorm, self::SKIP_TIPO, true)) {
                continue;
            }
            $numDocLower = strtolower(preg_replace('/[\s*()]+/', '', $numDoc));
            if (in_array($numDocLower, self::SKIP_NUMERO, true)) {
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

            // Validar tipo_documento (opcional)
            $tipoEnum = null;
            if ($tipoDoc !== '') {
                $tipoEnum = TipoDocumento::tryFrom(strtolower($tipoDoc));
                if ($tipoEnum === null) {
                    $errores[] = "Fila {$row}: TIPO_DOCUMENTO '{$tipoDoc}' inválido (usa dni o ruc), se omite.";
                    $omitidos++;
                    continue;
                }
            }

            $apellidos = trim((string) $sheet->getCell("D{$row}")->getValue());
            $correo    = trim((string) $sheet->getCell("E{$row}")->getValue());
            $telefono  = trim((string) $sheet->getCell("F{$row}")->getValue());
            $direccion = trim((string) $sheet->getCell("G{$row}")->getValue());

            $datos = array_filter([
                'nombre'    => $nombre,
                'apellidos' => $apellidos ?: null,
                'correo'    => $correo    ?: null,
                'telefono'  => $telefono  ?: null,
                'direccion' => $direccion ?: null,
            ], fn ($v) => $v !== null);

            if ($tipoEnum !== null) {
                $datos['tipo_documento'] = $tipoEnum;
            }

            $existente = Cliente::where('empresa_id', $empresaId)
                ->where('numero_documento', $numDoc)
                ->first();

            if ($existente) {
                $existente->update($datos);
                $actualizados++;
            } else {
                Cliente::create(array_merge($datos, [
                    'empresa_id'       => $empresaId,
                    'numero_documento' => $numDoc,
                ]));
                $creados++;
            }
        }

        return compact('creados', 'actualizados', 'omitidos', 'errores', 'filasConDatos');
    }
}
