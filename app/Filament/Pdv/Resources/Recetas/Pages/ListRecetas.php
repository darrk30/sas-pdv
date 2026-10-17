<?php

namespace App\Filament\Pdv\Resources\Recetas\Pages;

use App\Filament\Pdv\Resources\Recetas\RecetaResource;
use App\Services\RecetaActualizarService;
use App\Services\RecetaExportService;
use App\Services\RecetaImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions as FormActions;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListRecetas extends ListRecords
{
    protected static string $resource = RecetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ActionGroup::make([

                // ── Exportar ─────────────────────────────────────────────────
                Action::make('exportar_recetas')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (): StreamedResponse {
                        return app(RecetaExportService::class)->exportar(Filament::getTenant());
                    }),

                // ── Importar (crear / reemplazar) ─────────────────────────────
                Action::make('importar_recetas')
                    ->label('Importar (crear nuevas)')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->form([
                        FormActions::make([
                            Action::make('plantilla_importar')
                                ->label('Descargar plantilla')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->action(fn (): StreamedResponse =>
                                    app(RecetaExportService::class)->generarPlantilla()
                                ),
                        ]),
                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required()
                            ->helperText(
                                'Columnas: codigo_producto | nombre_receta | nombre_producto | ' .
                                'codigo_insumo | nombre_insumo | cantidad | unidad. ' .
                                'Si la receta ya existe, sus insumos se reemplazan completamente.'
                            ),
                    ])
                    ->action(function (array $data): void {
                        $ruta = $this->resolverRuta($data['archivo']);
                        if (! $ruta) { $this->errorArchivo(); return; }

                        try {
                            $r = app(RecetaImportService::class)->importar($ruta, Filament::getTenant()->id);
                            $this->notificarResultado($r, 'Importación');
                        } catch (\Throwable $e) {
                            Notification::make()->title('Error al importar')->body($e->getMessage())->danger()->send();
                        } finally {
                            @unlink($ruta);
                        }
                    })
                    ->modalHeading('Importar Recetas')
                    ->modalDescription(
                        'Sube el Excel con las recetas a crear. Si ya existe una receta para el producto, ' .
                        'sus insumos se reemplazarán completamente.'
                    )
                    ->modalSubmitActionLabel('Importar')
                    ->modalWidth('lg'),

                // ── Actualizar (merge) ────────────────────────────────────────
                Action::make('actualizar_recetas')
                    ->label('Actualizar recetas existentes')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        FormActions::make([
                            Action::make('plantilla_actualizar')
                                ->label('Descargar plantilla')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->action(fn (): StreamedResponse =>
                                    app(RecetaExportService::class)->generarPlantilla()
                                ),
                        ]),
                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required()
                            ->helperText(
                                'Mismo formato del export. Por cada receta del Excel: ' .
                                'agrega insumos nuevos, actualiza cantidades/unidades y elimina los que ya no están. ' .
                                'Las recetas que no aparezcan en el Excel no se tocan.'
                            ),
                    ])
                    ->action(function (array $data): void {
                        $ruta = $this->resolverRuta($data['archivo']);
                        if (! $ruta) { $this->errorArchivo(); return; }

                        try {
                            $r = app(RecetaActualizarService::class)->actualizar($ruta, Filament::getTenant()->id);
                            $this->notificarResultado($r, 'Actualización');
                        } catch (\Throwable $e) {
                            Notification::make()->title('Error al actualizar')->body($e->getMessage())->danger()->send();
                        } finally {
                            @unlink($ruta);
                        }
                    })
                    ->modalHeading('Actualizar Recetas')
                    ->modalDescription(
                        'Las recetas del Excel se actualizan mediante merge. ' .
                        'Si una receta no existe, se crea. Las recetas del sistema que no estén en el Excel no se eliminan.'
                    )
                    ->modalSubmitActionLabel('Actualizar')
                    ->modalWidth('lg'),

            ])
            ->label('Acciones')
            ->button()
            ->color('gray'),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function errorArchivo(): void
    {
        Notification::make()
            ->title('No se pudo leer el archivo')
            ->body('Por favor, vuelve a subir el archivo e inténtalo de nuevo.')
            ->danger()->send();
    }

    private function notificarResultado(array $r, string $titulo): void
    {
        $hayErrores = ! empty($r['errores']);

        $partes = array_filter([
            ($r['creadas']      ?? 0) > 0 ? "✔ {$r['creadas']} recetas creadas"   : null,
            ($r['actualizadas'] ?? 0) > 0 ? "✔ {$r['actualizadas']} actualizadas" : null,
            ($r['omitidas']     ?? 0) > 0 ? "⚠ {$r['omitidas']} omitidas"         : null,
        ]);
        $cuerpo = implode(' | ', $partes) ?: 'No se procesó ningún registro.';

        if ($hayErrores) {
            $detalle = implode("\n", array_slice($r['errores'], 0, 6));
            if (count($r['errores']) > 6) {
                $detalle .= "\n... y " . (count($r['errores']) - 6) . ' más.';
            }
            Notification::make()
                ->title("{$titulo} completada — con advertencias")
                ->body($cuerpo . "\n\n" . $detalle)
                ->warning()->persistent()->send();
        } else {
            Notification::make()
                ->title("{$titulo} completada")
                ->body($cuerpo)
                ->success()->send();
        }
    }
}
