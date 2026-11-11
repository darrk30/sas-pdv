<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use App\Services\AjusteImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListAjustes extends ListRecords
{
    protected static string $resource = AjusteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ActionGroup::make([
                Action::make('importar_ajuste')
                    ->label('Importar desde Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->form([
                        ToggleButtons::make('tipo')
                            ->label('Tipo de ajuste')
                            ->options(['entrada' => 'Entrada', 'salida' => 'Salida'])
                            ->icons(['entrada' => 'heroicon-o-arrow-down-tray', 'salida' => 'heroicon-o-arrow-up-tray'])
                            ->colors(['entrada' => 'success', 'salida' => 'danger'])
                            ->inline()
                            ->required(),

                        Textarea::make('motivo')
                            ->label('Motivo del ajuste')
                            ->placeholder('Ej: Corrección de inventario tras conteo físico...')
                            ->rows(2)
                            ->required(),

                        Placeholder::make('link_plantilla')
                            ->label('')
                            ->content(fn () => new HtmlString(
                                '<a href="' . route('ajustes.plantilla') . '" target="_blank"
                                    style="display:inline-flex;align-items:center;gap:6px;font-size:0.875rem;color:#2563eb;font-weight:500;text-decoration:none;"
                                    onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    Descargar plantilla de ajuste de stock
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
                                ->danger()
                                ->send();
                            return;
                        }

                        $empresaId = Filament::getTenant()->id;

                        try {
                            $resultado = app(AjusteImportService::class)->importar(
                                $ruta,
                                $empresaId,
                                $data['tipo'],
                                $data['motivo'],
                                auth()->id(),
                            );
                            $this->notificarResultado($resultado);
                        } finally {
                            @unlink($ruta);
                        }
                    })
                    ->modalHeading('Importar Ajuste de Stock')
                    ->modalDescription('Descarga la plantilla, complétala con los productos a ajustar y súbela aquí. Se creará un ajuste en estado Borrador para que lo revises antes de confirmar.')
                    ->modalSubmitActionLabel('Importar')
                    ->modalWidth('lg'),
            ])
                ->label('Acciones')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button(),
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
        $ajuste        = $r['ajuste'] ?? null;
        $importados    = $r['importados'] ?? 0;
        $omitidos      = $r['omitidos'] ?? 0;
        $errores       = $r['errores'] ?? [];
        $filasConDatos = $r['filasConDatos'] ?? 0;

        // Sin datos en el archivo
        if ($filasConDatos === 0) {
            Notification::make()
                ->title('Archivo sin datos')
                ->body('No se encontraron filas con datos. Asegúrate de que el CODIGO_INTERNO está en la columna A.')
                ->warning()
                ->persistent()
                ->send();
            return;
        }

        // Sin ítems válidos (todos omitidos)
        if ($importados === 0) {
            $detalle = implode("\n", array_slice($errores, 0, 5));
            if (count($errores) > 5) {
                $detalle .= "\n... y " . (count($errores) - 5) . ' errores más.';
            }
            Notification::make()
                ->title('No se importó ningún ítem')
                ->body($detalle ?: 'Revisa que los códigos internos existan y que las unidades sean válidas.')
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $cuerpo = "✔ {$importados} ítem(s) importado(s)";
        if ($omitidos > 0) {
            $cuerpo .= " | ⚠ {$omitidos} omitido(s)";
        }
        if ($ajuste) {
            $cuerpo .= "\nAjuste creado: {$ajuste->codigo} (Borrador — revísalo y confírmalo desde la tabla).";
        }

        if (! empty($errores)) {
            $detalleErrores = implode("\n", array_slice($errores, 0, 5));
            if (count($errores) > 5) {
                $detalleErrores .= "\n... y " . (count($errores) - 5) . ' errores más.';
            }

            Notification::make()
                ->title('Importación completada con advertencias')
                ->body($cuerpo . "\n\n" . $detalleErrores)
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Importación completada')
                ->body($cuerpo)
                ->success()
                ->send();
        }
    }
}
