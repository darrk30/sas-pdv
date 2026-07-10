<?php

namespace App\Filament\Pdv\Resources\Clientes\Tables;

use App\Enums\TipoDocumento;
use App\Enums\TipoPago;
use App\Filament\Pdv\Pages\CuentasPorCobrarPage;
use App\Filament\Pdv\Pages\ReporteClienteComprasPage;
use App\Models\Cliente;
use App\Models\Venta;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->addSelect([
                // Subquery: total facturado (ventas completadas)
                'total_facturado' => Venta::selectRaw('COALESCE(SUM(total), 0)')
                    ->whereColumn('cliente_id', 'clientes.id')
                    ->whereColumn('empresa_id', 'clientes.empresa_id')
                    ->where('estado', 'completada'),

                // Subquery: crédito pendiente (crédito completo + pagos parciales)
                'credito_pendiente' => Venta::selectRaw('COALESCE(SUM(saldo_pendiente), 0)')
                    ->whereColumn('cliente_id', 'clientes.id')
                    ->whereColumn('empresa_id', 'clientes.empresa_id')
                    ->where('estado', 'completada')
                    ->whereIn('estado_pago', ['pendiente', 'parcial']),

                // Subquery: total de ventas a crédito (pagadas + pendientes)
                'total_creditos' => Venta::selectRaw('COUNT(*)')
                    ->whereColumn('cliente_id', 'clientes.id')
                    ->whereColumn('empresa_id', 'clientes.empresa_id')
                    ->where('estado', 'completada')
                    ->where('tipo_pago', TipoPago::Credito),
            ]))
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('apellidos')
                    ->label('Apellidos')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('tipo_documento')
                    ->label('Tipo doc.')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('numero_documento')
                    ->label('N° Documento')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('correo')
                    ->label('Correo')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('total_facturado')
                    ->label('Facturado')
                    ->money('PEN')
                    ->sortable()
                    ->alignRight()
                    ->color('gray')
                    ->placeholder('S/ 0.00'),

                TextColumn::make('credito_pendiente')
                    ->label('Crédito')
                    ->money('PEN')
                    ->sortable()
                    ->alignRight()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->options(TipoDocumento::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('compras')
                        ->label('Compras')
                        ->icon('heroicon-o-shopping-bag')
                        ->color('info')
                        ->url(fn (Cliente $record) =>
                            ReporteClienteComprasPage::getUrl() . '?' . http_build_query([
                                'clienteNombre' => $record->nombre_completo,
                                'clienteNumDoc' => $record->numero_documento,
                            ])
                        ),

                    Action::make('creditos')
                        ->label('Créditos')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn (Cliente $record) =>
                            (int) ($record->total_creditos ?? 0) > 0 ||
                            (float) ($record->credito_pendiente ?? 0) > 0
                        )
                        ->url(fn (Cliente $record) =>
                            CuentasPorCobrarPage::getUrl() . '?' . http_build_query([
                                'filtroClienteId'     => $record->id,
                                'filtroClienteNombre' => $record->nombre_completo,
                            ])
                        ),

                    EditAction::make()
                        ->hidden(fn (Cliente $record) => $record->numero_documento === '99999999'),

                    DeleteAction::make()
                        ->hidden(fn (Cliente $record) => $record->numero_documento === '99999999'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
