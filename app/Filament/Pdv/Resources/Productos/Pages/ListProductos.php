<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Services\ProductoExcelTemplateService;
use App\Services\ProductoExportService;
use App\Services\ProductoImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Attributes\On;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    public function mount(): void
    {
        $this->js(<<<'JS'
            if (!window.__scannerProductos) {
                window.__scannerProductos = true;
                var buf = '', last = 0;
                document.addEventListener('keydown', function (e) {
                    var a = document.activeElement;
                    if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable)) {
                        buf = ''; return;
                    }
                    if (e.key === 'Enter') {
                        if (buf.length >= 4) {
                            var code = buf; buf = '';
                            Livewire.dispatch('barcode-result', { path: 'productos_barcode_filter', code: code });
                        } else { buf = ''; }
                        return;
                    }
                    var now = Date.now();
                    if (now - last > 60 && buf.length > 0) buf = '';
                    last = now;
                    if (e.key.length === 1) buf += e.key;
                });
            }
        JS);
    }

    #[On('barcode-result')]
    public function filtrarPorBarcode(string $path, string $code): void
    {
        if ($path === 'productos_barcode_filter') {
            $this->tableSearch = $code;
        }
    }

    #[On('camera-not-available')]
    public function handleCameraNotAvailable(): void
    {
        Notification::make()
            ->title('Cámara no disponible')
            ->body('Activa los permisos de cámara en el navegador o usa un escáner USB conectado al equipo.')
            ->warning()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('exportar_productos')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    return app(ProductoExportService::class)->exportar(Filament::getTenant());
                }),

            ActionGroup::make([

                // ── Productos: Nuevo / Actualizar ─────────────────────────────
                Action::make('importar_productos')
                    ->label('Productos')
                    ->icon('heroicon-o-cube')
                    ->form([
                        Radio::make('tipo')
                            ->label('¿Qué deseas hacer?')
                            ->options([
                                'nuevos'     => 'Crear nuevos productos',
                                'actualizar' => 'Actualizar productos existentes',
                            ])
                            ->default('nuevos')
                            ->live()
                            ->required(),

                        FormActions::make([
                            Action::make('descargar_plantilla_nuevos')
                                ->label('Descargar plantilla — nuevos productos')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->action(function (): StreamedResponse {
                                    $s = app(ProductoExcelTemplateService::class)->generarPlantillaNuevos();
                                    $n = 'plantilla-productos-nuevos.xlsx';
                                    return response()->streamDownload(fn () => (new Xlsx($s))->save('php://output'), $n, [
                                        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'Content-Disposition' => "attachment; filename=\"{$n}\"",
                                    ]);
                                })
                                ->visible(fn (Get $get) => $get('tipo') === 'nuevos'),

                            Action::make('descargar_plantilla_actualizar')
                                ->label('Descargar plantilla — actualizar productos')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->action(function (): StreamedResponse {
                                    $s = app(ProductoExcelTemplateService::class)->generarPlantillaActualizar();
                                    $n = 'plantilla-productos-actualizar.xlsx';
                                    return response()->streamDownload(fn () => (new Xlsx($s))->save('php://output'), $n, [
                                        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'Content-Disposition' => "attachment; filename=\"{$n}\"",
                                    ]);
                                })
                                ->visible(fn (Get $get) => $get('tipo') === 'actualizar'),
                        ]),

                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $ruta = $this->resolverRuta($data['archivo']);

                        if (! $ruta) {
                            Notification::make()
                                ->title('No se pudo leer el archivo')
                                ->body('Por favor, vuelve a subir el archivo e inténtalo de nuevo.')
                                ->danger()->send();
                            return;
                        }

                        $empresaId = Filament::getTenant()->id;
                        $servicio  = app(ProductoImportService::class);

                        try {
                            $resultado = match ($data['tipo']) {
                                'nuevos'     => $servicio->importarNuevos($ruta, $empresaId),
                                'actualizar' => $servicio->importarActualizar($ruta, $empresaId),
                            };
                            $this->notificarResultado($resultado, $data['tipo']);
                        } finally {
                            @unlink($ruta);
                        }
                    })
                    ->modalHeading('Importar Productos')
                    ->modalDescription('Descarga la plantilla correspondiente, complétala y súbela aquí.')
                    ->modalSubmitActionLabel('Importar')
                    ->modalWidth('lg'),

                // ── Actualizar Precios ────────────────────────────────────────
                Action::make('importar_precios')
                    ->label('Actualizar Precios')
                    ->icon('heroicon-o-currency-dollar')
                    ->form([
                        FormActions::make([
                            Action::make('descargar_plantilla_precios')
                                ->label('Descargar plantilla de precios')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->action(function (): StreamedResponse {
                                    $s = app(ProductoExcelTemplateService::class)->generarPlantillaPrecios();
                                    $n = 'plantilla-productos-precios.xlsx';
                                    return response()->streamDownload(fn () => (new Xlsx($s))->save('php://output'), $n, [
                                        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'Content-Disposition' => "attachment; filename=\"{$n}\"",
                                    ]);
                                }),
                        ]),

                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $ruta = $this->resolverRuta($data['archivo']);

                        if (! $ruta) {
                            Notification::make()
                                ->title('No se pudo leer el archivo')
                                ->body('Por favor, vuelve a subir el archivo e inténtalo de nuevo.')
                                ->danger()->send();
                            return;
                        }

                        $empresaId = Filament::getTenant()->id;
                        $servicio  = app(ProductoImportService::class);

                        try {
                            $resultado = $servicio->importarPrecios($ruta, $empresaId);
                            $this->notificarResultado($resultado, 'precios');
                        } finally {
                            @unlink($ruta);
                        }
                    })
                    ->modalHeading('Actualizar Precios')
                    ->modalDescription('Descarga la plantilla, completa los precios con su CODIGO_INTERNO y súbela.')
                    ->modalSubmitActionLabel('Actualizar precios')
                    ->modalWidth('lg'),

            ])
            ->label('Importar')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->button(),
        ];
    }

    /**
     * Filament v5 Actions no garantiza mover el archivo de livewire-tmp al disco configurado.
     * Buscamos en todos los lugares posibles.
     */
    private function resolverRuta(mixed $archivo): ?string
    {
        $nombre = is_array($archivo) ? (string) reset($archivo) : (string) $archivo;
        $base   = basename($nombre);

        foreach ([
            storage_path('app/public/' . $base),          // disco public (default en este proyecto)
            storage_path('app/public/livewire-tmp/' . $base),
            storage_path('app/' . $nombre),
            storage_path('app/livewire-tmp/' . $base),
            storage_path('app/private/livewire-tmp/' . $base),
        ] as $candidato) {
            if (is_file($candidato)) return $candidato;
        }

        return null;
    }

    private function notificarResultado(array $r, string $tipo): void
    {
        $tipoLabel = match ($tipo) {
            'nuevos'     => 'Importación de nuevos productos',
            'actualizar' => 'Actualización de productos',
            'precios'    => 'Actualización de precios',
            default      => 'Importación',
        };

        $hayErrores = ! empty($r['errores']);

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
                ->title("{$tipoLabel} — con advertencias")
                ->body($cuerpo . "\n\n" . $detalleErrores)
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title("{$tipoLabel} completada")
                ->body($cuerpo ?: 'No se procesó ningún registro.')
                ->success()
                ->send();
        }
    }
}
