<?php

namespace App\Filament\Resources\Empresas\Tables;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class EmpresasTable
{
    public static function configure(Table $table): Table
    {
        $mes  = now()->month;
        $anio = now()->year;

        return $table
            ->query(
                \App\Models\Empresa::query()
                    ->leftJoin(
                        DB::raw("(SELECT empresa_id,
                                    COUNT(*) AS ventas_mes,
                                    COALESCE(SUM(total),0) AS monto_mes
                                  FROM ventas
                                  WHERE estado='completada'
                                    AND MONTH(created_at)={$mes}
                                    AND YEAR(created_at)={$anio}
                                  GROUP BY empresa_id) AS vm"),
                        'empresas.id', '=', 'vm.empresa_id'
                    )
                    ->leftJoin('suscripciones', 'empresas.id', '=', 'suscripciones.empresa_id')
                    ->leftJoin('plans', 'suscripciones.plan_id', '=', 'plans.id')
                    ->select([
                        'empresas.*',
                        'plans.nombre as plan_nombre',
                        'suscripciones.fecha_fin',
                        'suscripciones.es_prueba_gratuita',
                        DB::raw('COALESCE(vm.ventas_mes,0) AS ventas_mes'),
                        DB::raw('COALESCE(vm.monto_mes,0)  AS monto_mes'),
                    ])
            )
            ->columns([
                ImageColumn::make('logo')
                    ->circular()
                    ->disk('public'),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('ruc')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('plan_nombre')
                    ->label('Plan')
                    ->default('—')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('es_prueba_gratuita')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state ? 'Trial' : 'Pago')
                    ->badge()
                    ->colors(['warning' => true, 'success' => false]),

                TextColumn::make('fecha_fin')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->default('—')
                    ->color(fn ($record) => $record->fecha_fin && $record->fecha_fin < now() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('ventas_mes')
                    ->label('Ventas mes')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('monto_mes')
                    ->label('Monto mes')
                    ->money('PEN')
                    ->sortable()
                    ->color('success'),

                TextColumn::make('estado')
                    ->badge()
                    ->colors(['success' => 'activo', 'danger' => 'inactivo']),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo']),
                SelectFilter::make('es_prueba_gratuita')
                    ->label('Tipo')
                    ->options([true => 'Trial', false => 'Pago'])
                    ->query(fn ($query, $data) =>
                        $data['value'] !== null && $data['value'] !== ''
                            ? $query->where('suscripciones.es_prueba_gratuita', $data['value'])
                            : $query
                    ),
            ])
            ->recordActions([
                Action::make('stats')
                    ->label('Estadísticas')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->url(fn ($record) => EmpresaResource::getUrl('stats', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])
            ->defaultSort('monto_mes', 'desc')
            ->persistSortInSession(false);
    }
}

