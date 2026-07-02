<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Services\ProductoImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

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

                        Placeholder::make('link_plantilla_nuevos')
                            ->label('')
                            ->content(fn () => new HtmlString(
                                '<a href="' . route('productos.plantilla', ['tipo' => 'nuevos']) . '" target="_blank"
                                    style="display:inline-flex;align-items:center;gap:6px;font-size:0.875rem;color:#2563eb;font-weight:500;text-decoration:none;"
                                    onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    Descargar plantilla para nuevos productos
                                </a>'
                            ))
                            ->visible(fn (Get $get) => $get('tipo') === 'nuevos'),

                        Placeholder::make('link_plantilla_actualizar')
                            ->label('')
                            ->content(fn () => new HtmlString(
                                '<a href="' . route('productos.plantilla', ['tipo' => 'actualizar']) . '" target="_blank"
                                    style="display:inline-flex;align-items:center;gap:6px;font-size:0.875rem;color:#2563eb;font-weight:500;text-decoration:none;"
                                    onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    Descargar plantilla para actualizar productos
                                </a>'
                            ))
                            ->visible(fn (Get $get) => $get('tipo') === 'actualizar'),

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
                        Placeholder::make('link_plantilla_precios')
                            ->label('')
                            ->content(fn () => new HtmlString(
                                '<a href="' . route('productos.plantilla', ['tipo' => 'precios']) . '" target="_blank"
                                    style="display:inline-flex;align-items:center;gap:6px;font-size:0.875rem;color:#2563eb;font-weight:500;text-decoration:none;"
                                    onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    Descargar plantilla de actualización de precios
                                </a>'
                            )),

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
