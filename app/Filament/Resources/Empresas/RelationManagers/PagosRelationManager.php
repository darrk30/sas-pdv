<?php

namespace App\Filament\Resources\Empresas\RelationManagers;

use App\Enums\MetodoPago;
use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';
    protected static ?string $title = 'Historial de Pagos';

    protected static ?string $relatedResource = EmpresaResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_pago')
                    ->label('Fecha')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN') // Cambia a 'USD' si fuera necesario
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('metodo_pago')
                    ->label('Método')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)), // Capitaliza la primera letra

                TextColumn::make('referencia')
                    ->label('N° Operación')
                    ->searchable()
                    ->placeholder('Sin referencia'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'aprobado'  => 'success',
                        'rechazado' => 'danger',
                        default     => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'aprobado'  => 'Aprobado',
                        'rechazado' => 'Rechazado',
                        default     => 'Pendiente',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalHeading('Registrar Nuevo Pago')
                    ->label('Registrar Pago')->before(function (CreateAction $action, $livewire) {
                        if (! $livewire->ownerRecord->suscripcion) {
                            Notification::make()
                                ->warning()
                                ->title('Falta Suscripción')
                                ->body('Esta empresa aún no tiene un plan asignado. Ve a la pestaña "Suscripción y Plan" y asígnale uno antes de cobrar.')
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->estado === 'pendiente')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar pago y renovar suscripción')
                    ->modalDescription(fn ($record) => "Se aprobará el pago de S/ {$record->monto} y se extenderá la suscripción según el ciclo del plan activo.")
                    ->action(function ($record, $livewire) {
                        $empresa     = $livewire->ownerRecord;
                        $suscripcion = $empresa->suscripcion;

                        if (! $suscripcion) {
                            Notification::make()->warning()->title('Sin suscripción')->send();
                            return;
                        }

                        // Aprobar este pago
                        $record->update(['estado' => 'aprobado']);

                        // Rechazar otros pagos pendientes de la misma suscripción
                        $suscripcion->pagos()
                            ->where('estado', 'pendiente')
                            ->where('id', '!=', $record->id)
                            ->update(['estado' => 'rechazado']);

                        // Extender fecha_fin según ciclo del plan
                        $ciclo    = $suscripcion->plan?->ciclo_facturacion ?? 'mensual';
                        $base     = $suscripcion->fecha_fin && $suscripcion->fecha_fin->isFuture()
                                    ? $suscripcion->fecha_fin
                                    : now();
                        $nuevaFin = match ($ciclo) {
                            'anual'      => $base->copy()->addYear(),
                            'trimestral' => $base->copy()->addMonths(3),
                            default      => $base->copy()->addMonth(),
                        };

                        $suscripcion->update([
                            'estado'       => 'activo',
                            'fecha_inicio' => $base->copy()->startOfDay(),
                            'fecha_fin'    => $nuevaFin,
                        ]);

                        $empresa->update([
                            'estado'                       => 'activo',
                            'suscripcion_proxima_a_vencer' => false,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Pago aprobado')
                            ->body("Suscripción renovada hasta {$nuevaFin->format('d/m/Y')}.")
                            ->send();
                    }),

                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->estado === 'pendiente')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar pago')
                    ->modalDescription('El cliente deberá registrar un nuevo comprobante.')
                    ->action(fn ($record) => $record->update(['estado' => 'rechazado'])),

                EditAction::make()->modalHeading('Editar Pago'),
                DeleteAction::make()->modalHeading('Eliminar Pago'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Confirmar Eliminación Masiva')
                        ->modalDescription('Estás a punto de eliminar múltiples registros. Esta acción no se puede deshacer. Por seguridad, ingresa tu contraseña para continuar.')
                        ->modalSubmitActionLabel('Sí, eliminar todo')
                        ->schema([
                            TextInput::make('password')
                                ->password()
                                ->label('Tu contraseña de administrador')
                                ->required()
                                ->revealable()
                                ->rule('current_password')
                                ->validationMessages([
                                    'current_password' => 'La contraseña ingresada es incorrecta.',
                                ]),
                        ]),
                ]),
            ])
            ->defaultSort('fecha_pago', 'desc');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('suscripcion_id')->default(fn($livewire) => $livewire->ownerRecord->suscripcion?->id),

                TextInput::make('monto')
                    ->required()
                    ->numeric()
                    ->prefix('S/'),

                Select::make('metodo_pago')
                    ->label('Método de Pago')
                    ->native(false)
                    ->options(MetodoPago::class)
                    ->default(MetodoPago::Efectivo)
                    ->required(),

                DateTimePicker::make('fecha_pago')
                    ->default(now())
                    ->required(),

                TextInput::make('referencia')
                    ->maxLength(255),

                FileUpload::make('path_url')->label('Comprobante')->image()->directory('comprobantes')->columnSpanFull(),
            ]);
    }
}
