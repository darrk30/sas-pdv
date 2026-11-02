<?php

namespace App\Filament\Resources\Empresas\RelationManagers;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SucursalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sucursales';
    protected static ?string $title = 'Sucursales';

    public function table(Table $table): Table
    {
        $mes  = now()->month;
        $anio = now()->year;

        return $table
            ->query(
                fn () => $this->getOwnerRecord()
                    ->sucursales()
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
                    ->select([
                        'empresas.*',
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
                    ->weight('bold')
                    ->url(fn ($record) => "https://{$record->slug}.tukipu.cloud/pdv/login")
                    ->openUrlInNewTab(),

                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('ventas_mes')
                    ->label('Ventas mes')
                    ->numeric()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('monto_mes')
                    ->label('Monto mes')
                    ->money('PEN')
                    ->color('success'),

                TextColumn::make('estado')
                    ->badge()
                    ->colors(['success' => 'activo', 'danger' => 'inactivo']),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn ($record) => EmpresaResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Sin sucursales')
            ->emptyStateDescription('Esta empresa no tiene sucursales registradas.')
            ->emptyStateIcon('heroicon-o-building-office');
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->esMatriz();
    }
}
