<?php

namespace App\Filament\Resources\Empresas\RelationManagers;

use App\Enums\EstadoGeneral;
use App\Enums\MetodoPago;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Plan;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('ciclo')
                    ->label('Ciclo')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'anual'  => 'Anual',
                        'prueba' => 'Prueba',
                        default  => 'Mensual',
                    }),

                TextColumn::make('periodo_desde')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('periodo_hasta')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('metodo_pago')
                    ->label('Método')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('concepto')
                    ->label('Concepto')
                    ->getStateUsing(fn ($record) => $record->concepto ?? $record->referencia)
                    ->placeholder('—'),

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
                    ->modalHeading('Registrar Pago Manualmente')
                    ->label('Registrar Pago')
                    ->before(function (CreateAction $action, $livewire) {
                        if (! $livewire->ownerRecord->suscripcion) {
                            Notification::make()
                                ->warning()
                                ->title('Falta Suscripción')
                                ->body('Esta empresa no tiene un plan asignado. Asígnale uno en la pestaña "Suscripción y Plan".')
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
                    ->modalDescription(function ($record, $livewire) {
                        $empresa     = $livewire->ownerRecord;
                        $suscripcion = $empresa->suscripcion;
                        $ciclo       = $record->ciclo ?? $suscripcion?->ciclo ?? 'mensual';

                        $finActual    = $suscripcion?->fecha_fin;
                        $periodoDesde = ($finActual && $finActual->isFuture())
                            ? $finActual->copy()->addDay()->startOfDay()
                            : now()->startOfDay();
                        $periodoHasta = $ciclo === 'anual'
                            ? $periodoDesde->copy()->addYear()->subDay()
                            : $periodoDesde->copy()->addMonth()->subDay();

                        return "Pago de S/ {$record->monto} — ciclo {$ciclo}.\n"
                            . "Nuevo período: {$periodoDesde->format('d/m/Y')} → {$periodoHasta->format('d/m/Y')}.";
                    })
                    ->action(function ($record, $livewire) {
                        $empresa     = $livewire->ownerRecord;
                        $suscripcion = $empresa->suscripcion;

                        if (! $suscripcion) {
                            Notification::make()->warning()->title('Sin suscripción')->send();
                            return;
                        }

                        // Ciclo y plan a aplicar (del pago o del plan activo actual)
                        $ciclo       = $record->ciclo ?? $suscripcion->ciclo ?? 'mensual';
                        $nuevoPlanId = $record->plan_id ?? $suscripcion->plan_id;
                        $nuevoPlan   = Plan::find($nuevoPlanId);

                        // periodo_desde: si hay días vigentes → acumular; si venció → desde hoy
                        $finActual    = $suscripcion->fecha_fin;
                        $periodoDesde = ($finActual && $finActual->isFuture())
                            ? $finActual->copy()->addDay()->startOfDay()
                            : now()->startOfDay();

                        // periodo_hasta según ciclo
                        $periodoHasta = $ciclo === 'anual'
                            ? $periodoDesde->copy()->addYear()->subDay()
                            : $periodoDesde->copy()->addMonth()->subDay();

                        // Actualizar el registro de pago con el período calculado
                        $record->updateQuietly([
                            'estado'        => 'aprobado',
                            'plan_id'       => $nuevoPlanId,
                            'ciclo'         => $ciclo,
                            'periodo_desde' => $periodoDesde,
                            'periodo_hasta' => $periodoHasta,
                        ]);

                        // Rechazar otros pagos pendientes de la misma suscripción
                        $suscripcion->pagos()
                            ->where('estado', 'pendiente')
                            ->where('id', '!=', $record->id)
                            ->update(['estado' => 'rechazado']);

                        // Actualizar la suscripción
                        $suscripcion->update([
                            'plan_id'            => $nuevoPlanId,
                            'estado'             => EstadoGeneral::Activo,
                            'fecha_inicio'       => $periodoDesde,
                            'fecha_fin'          => $periodoHasta,
                            'ciclo'              => $ciclo,
                            'precio_pagado'      => $record->monto,
                            'es_prueba_gratuita' => false,
                        ]);

                        // Reactivar empresa si estaba suspendida
                        $empresa->update([
                            'estado'                       => 'activo',
                            'suscripcion_proxima_a_vencer' => false,
                        ]);

                        // Sincronizar módulos si cambió el plan
                        if ($nuevoPlan?->modulos_activos) {
                            $empresa->update(['modulos_activos' => $nuevoPlan->modulos_activos]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Pago aprobado')
                            ->body("Suscripción renovada hasta {$periodoHasta->format('d/m/Y')}.")
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
                Hidden::make('suscripcion_id')
                    ->default(fn ($livewire) => $livewire->ownerRecord->suscripcion?->id),

                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => Plan::orderBy('nombre')->pluck('nombre', 'id'))
                    ->native(false)
                    ->default(fn ($livewire) => $livewire->ownerRecord->suscripcion?->plan_id)
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $plan  = Plan::find($state);
                        $ciclo = $get('ciclo') ?? 'mensual';
                        if ($plan) {
                            $set('monto', ($ciclo === 'anual' && $plan->precio_anual)
                                ? $plan->precio_anual
                                : $plan->precio);
                        }
                    }),

                Select::make('ciclo')
                    ->label('Ciclo')
                    ->options([
                        'mensual' => 'Mensual',
                        'anual'   => 'Anual',
                        'prueba'  => 'Prueba gratuita',
                    ])
                    ->default('mensual')
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $plan = Plan::find($get('plan_id'));
                        if ($plan) {
                            $set('monto', ($state === 'anual' && $plan->precio_anual)
                                ? $plan->precio_anual
                                : $plan->precio);
                        }
                    }),

                TextInput::make('monto')
                    ->required()
                    ->numeric()
                    ->prefix('S/'),

                Select::make('metodo_pago')
                    ->label('Método de Pago')
                    ->native(false)
                    ->options([
                        'transferencia' => 'Transferencia Bancaria',
                        'yape'          => 'Yape',
                        'plin'          => 'Plin',
                        'tarjeta'       => 'Tarjeta',
                        'efectivo'      => 'Efectivo',
                        'gratuito'      => 'Gratuito',
                    ])
                    ->default('transferencia')
                    ->required(),

                DateTimePicker::make('fecha_pago')
                    ->default(now())
                    ->required(),

                TextInput::make('referencia')
                    ->maxLength(255),

                Select::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente'  => 'Pendiente',
                        'aprobado'   => 'Aprobado',
                        'rechazado'  => 'Rechazado',
                    ])
                    ->default('pendiente')
                    ->required(),

                FileUpload::make('path_url')
                    ->label('Comprobante')
                    ->image()
                    ->directory('comprobantes')
                    ->required(fn (Get $get): bool => $get('metodo_pago') !== 'gratuito')
                    ->helperText(fn (Get $get): string => $get('metodo_pago') === 'gratuito'
                        ? 'No requerido para pagos gratuitos.'
                        : 'Requerido: adjunta la captura del comprobante de pago.')
                    ->columnSpanFull(),
            ]);
    }
}
