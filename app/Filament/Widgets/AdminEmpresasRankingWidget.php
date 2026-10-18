<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class AdminEmpresasRankingWidget extends BaseWidget
{
    protected static bool $isDiscovered = true;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Empresas — actividad del mes';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $mes = now()->month;
        $anio = now()->year;

        return $table
            ->query(
                Empresa::query()
                    ->leftJoin(
                        DB::raw("(SELECT empresa_id,
                                    COUNT(*) AS ventas_mes,
                                    COALESCE(SUM(total), 0) AS monto_mes,
                                    MAX(created_at) AS ultima_venta
                                 FROM ventas
                                 WHERE estado = 'completada'
                                   AND MONTH(created_at) = {$mes}
                                   AND YEAR(created_at)  = {$anio}
                                 GROUP BY empresa_id) AS v_mes"),
                        'empresas.id', '=', 'v_mes.empresa_id'
                    )
                    ->leftJoin('suscripciones', 'empresas.id', '=', 'suscripciones.empresa_id')
                    ->leftJoin('plans', 'suscripciones.plan_id', '=', 'plans.id')
                    ->select([
                        'empresas.id',
                        'empresas.name',
                        'empresas.estado',
                        'plans.nombre as plan_nombre',
                        'suscripciones.fecha_fin',
                        'suscripciones.es_prueba_gratuita',
                        DB::raw('COALESCE(v_mes.ventas_mes, 0) AS ventas_mes'),
                        DB::raw('COALESCE(v_mes.monto_mes, 0) AS monto_mes'),
                        DB::raw('v_mes.ultima_venta'),
                    ])
                    ->orderByDesc('monto_mes')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->colors(['success' => 'activo', 'danger' => 'inactivo']),

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
                    ->placeholder('—')
                    ->color(fn ($record) => $record->fecha_fin && $record->fecha_fin < now() ? 'danger' : null),

                TextColumn::make('ventas_mes')
                    ->label('Ventas mes')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('monto_mes')
                    ->label('Monto mes')
                    ->money('PEN')
                    ->sortable()
                    ->color('success'),

                TextColumn::make('ultima_venta')
                    ->label('Última venta')
                    ->since()
                    ->placeholder('—'),
            ])
            ->paginated([10, 25, 50]);
    }
}
