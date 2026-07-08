<?php

namespace App\Filament\Pdv\Resources\Clientes;

use App\Filament\Pdv\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Pdv\Resources\Clientes\Pages\EditCliente;
use App\Filament\Pdv\Resources\Clientes\Pages\ListClientes;
use App\Filament\Pdv\Resources\Clientes\Schemas\ClienteForm;
use App\Filament\Pdv\Resources\Clientes\Tables\ClientesTable;
use App\Models\Cliente;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clientes';

    protected static string|UnitEnum|null $navigationGroup = 'Pedidos Web';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return ClienteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListClientes::route('/'),
            'create' => CreateCliente::route('/create'),
            'edit'   => EditCliente::route('/{record}/edit'),
        ];
    }
}
