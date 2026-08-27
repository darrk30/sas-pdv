<?php

namespace App\Filament\Pdv\Resources\Clientes\Pages;

use App\Filament\Pdv\Resources\Clientes\ClienteResource;
use App\Services\ClienteExportService;
use App\Services\ClienteImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('exportar_clientes')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $empresa = Filament::getTenant();
                    return app(ClienteExportService::class)->exportar($empresa);
                }),

            Action::make('importar_clientes')
                ->label('Importar Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Placeholder::make('link_plantilla')
                        ->label('')
                        ->content(fn () => new HtmlString(
                            '<a href="' . route('clientes.plantilla') . '" target="_blank"
                                style="display:inline-flex;align-items:center;gap:6px;font-size:0.875rem;color:#2563eb;font-weight:500;text-decoration:none;"
                                onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Descargar plantilla de clientes
                            </a>'
                        )),

                    FileUpload::make('archivo')
                        ->label('Archivo Excel (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $ruta = $this->resolverRuta($data['archivo']);

                    if (! $ruta) {
                        Notification::make()
                            ->title('No se pudo leer el archivo')
                            ->body('Por favor, vuelve a subir el archivo e inténtalo de nuevo.')
                            ->danger()->send();
                        return;
                    }

                    $empresaId = Filament::getTenant()->id;

                    try {
                        $resultado = app(ClienteImportService::class)->importar($ruta, $empresaId);
                        $this->notificarResultado($resultado);
                    } finally {
                        @unlink($ruta);
                    }
                })
                ->modalHeading('Importar Clientes')
                ->modalDescription('Descarga la plantilla, complétala y súbela aquí. Los clientes se crearán o actualizarán según el NUMERO_DOCUMENTO.')
                ->modalSubmitActionLabel('Importar')
                ->modalWidth('lg'),
        ];
    }

    private function resolverRuta(mixed $archivo): ?string
    {
        $nombre = is_array($archivo) ? (string) reset($archivo) : (string) $archivo;
        $base   = basename($nombre);

        foreach ([
            storage_path('app/public/' . $base),
            storage_path('app/public/livewire-tmp/' . $base),
            storage_path('app/' . $nombre),
            storage_path('app/livewire-tmp/' . $base),
            storage_path('app/private/livewire-tmp/' . $base),
        ] as $candidato) {
            if (is_file($candidato)) return $candidato;
        }

        return null;
    }

    private function notificarResultado(array $r): void
    {
        $hayErrores     = ! empty($r['errores']);
        $filasConDatos  = $r['filasConDatos'] ?? 0;
        $total          = ($r['creados'] ?? 0) + ($r['actualizados'] ?? 0) + ($r['omitidos'] ?? 0);

        // Sin datos detectados en el archivo
        if ($filasConDatos === 0) {
            Notification::make()
                ->title('Archivo sin datos')
                ->body('No se encontraron filas con datos. Asegúrate de que el archivo tiene NUMERO_DOCUMENTO en la columna B y NOMBRE en la columna C, con datos desde la primera fila después del encabezado.')
                ->warning()
                ->persistent()
                ->send();
            return;
        }

        $cuerpo = implode(' | ', array_filter([
            ($r['creados']      ?? 0) > 0 ? "✔ {$r['creados']} creados"           : null,
            ($r['actualizados'] ?? 0) > 0 ? "✔ {$r['actualizados']} actualizados" : null,
            ($r['omitidos']     ?? 0) > 0 ? "⚠ {$r['omitidos']} omitidos"         : null,
        ]));

        if ($hayErrores) {
            $detalleErrores = implode("\n", array_slice($r['errores'], 0, 5));
            if (count($r['errores']) > 5) {
                $detalleErrores .= "\n... y " . (count($r['errores']) - 5) . ' errores más.';
            }

            Notification::make()
                ->title('Importación de clientes — con advertencias')
                ->body($cuerpo . "\n\n" . $detalleErrores)
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Importación de clientes completada')
                ->body($cuerpo ?: 'No se procesó ningún registro.')
                ->success()
                ->send();
        }
    }
}
