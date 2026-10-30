<?php

namespace App\Filament\Pdv\Pages;

use App\Models\ReporteDescarga;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use BackedEnum;
use UnitEnum;

class ReporteDescargasPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pdv.pages.reporte-descargas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Mis Descargas';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 99;
    protected static ?string $title = 'Mis Descargas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ventas.ver') ?? false;
    }

    public function mount(): void
    {
        // Limpiar registros expirados del usuario actual
        ReporteDescarga::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', auth()->id())
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (ReporteDescarga $r) {
                if ($r->path && Storage::disk('local')->exists($r->path)) {
                    Storage::disk('local')->delete($r->path);
                }
                $r->delete();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ReporteDescarga::query()
                    ->where('empresa_id', Filament::getTenant()->id)
                    ->where('user_id', auth()->id())
                    ->latest()
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('reporte')
                    ->label('Reporte')
                    ->formatStateUsing(fn($state) => match($state) {
                        'ventas'          => 'Ventas',
                        'compras'         => 'Compras',
                        'ganancias'       => 'Ganancias',
                        'vendedores'      => 'Vendedores',
                        'vendedor_ventas' => 'Ventas vendedor',
                        'kardex'          => 'Kardex',
                        'inventario'      => 'Inventario',
                        'gastos'          => 'Gastos',
                        'productos'       => 'Productos',
                        default           => ucfirst($state),
                    }),

                TextColumn::make('tipo')
                    ->label('Formato')
                    ->formatStateUsing(fn($state) => strtoupper($state))
                    ->badge()
                    ->color(fn($state) => $state === 'pdf' ? 'danger' : 'success'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'listo'      => 'success',
                        'procesando' => 'info',
                        'pendiente'  => 'warning',
                        'error'      => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'listo'      => 'Listo',
                        'procesando' => 'Procesando…',
                        'pendiente'  => 'En cola',
                        'error'      => 'Error',
                        default      => ucfirst($state),
                    }),

                TextColumn::make('nombre_archivo')
                    ->label('Archivo')
                    ->default('—'),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->formatStateUsing(fn($state) => $state ? $state->format('d/m/Y H:i') : '—')
                    ->color(fn($record) => $record->expires_at && $record->expires_at->diffInHours(now()) < 2 ? 'warning' : null),
            ])
            ->actions([
                TableAction::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn(ReporteDescarga $record) => $record->isListo() && $record->path)
                    ->action(fn(ReporteDescarga $record) =>
                        Storage::disk('local')->exists($record->path)
                            ? response()->download(
                                Storage::disk('local')->path($record->path),
                                $record->nombre_archivo
                            )
                            : \Filament\Notifications\Notification::make()
                                ->title('Archivo no encontrado')
                                ->body('El archivo ya no está disponible. Genera un nuevo reporte.')
                                ->warning()
                                ->send()
                    ),

                TableAction::make('eliminar')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ReporteDescarga $record) {
                        if ($record->path && Storage::disk('local')->exists($record->path)) {
                            Storage::disk('local')->delete($record->path);
                        }
                        $record->delete();
                        \Filament\Notifications\Notification::make()
                            ->title('Reporte eliminado')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Sin reportes generados')
            ->emptyStateDescription('Cuando generes un reporte desde cualquier página de reportes, aparecerá aquí listo para descargar.')
            ->emptyStateIcon('heroicon-o-arrow-down-tray')
            ->poll('5s');
    }
}
