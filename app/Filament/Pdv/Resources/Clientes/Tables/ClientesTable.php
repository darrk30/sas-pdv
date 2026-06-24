<?php

namespace App\Filament\Pdv\Resources\Clientes\Tables;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
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
            ])
            ->filters([
                SelectFilter::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->options(TipoDocumento::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn(Cliente $record) => $record->numero_documento === '99999999'),
                DeleteAction::make()
                    ->hidden(fn(Cliente $record) => $record->numero_documento === '99999999'),
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
