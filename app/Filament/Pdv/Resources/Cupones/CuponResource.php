<?php

namespace App\Filament\Pdv\Resources\Cupones;

use App\Filament\Pdv\Resources\Cupones\Pages\CreateCupon;
use App\Filament\Pdv\Resources\Cupones\Pages\EditCupon;
use App\Filament\Pdv\Resources\Cupones\Pages\ListCupones;
use App\Filament\Pdv\Resources\Cupones\Schemas\CuponForm;
use App\Filament\Pdv\Resources\Cupones\Tables\CuponesTable;
use App\Models\Cupon;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CuponResource extends Resource
{
    protected static ?string $model = Cupon::class;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-ticket';
    protected static ?string $navigationLabel                 = 'Cupones';
    protected static string|UnitEnum|null $navigationGroup    = 'Pedidos Web';
    protected static ?int $navigationSort                     = 40;
    protected static ?string $modelLabel                      = 'Cupón';
    protected static ?string $pluralModelLabel                = 'Cupones';
    protected static ?string $recordTitleAttribute            = 'codigo';

    public static function canAccess(): bool          { return (Filament::getTenant()?->tieneModulo('cupones') ?? false) && (auth()->user()?->can('cupones.ver')      ?? false); }
    public static function canCreate(): bool          { return (Filament::getTenant()?->tieneModulo('cupones') ?? false) && (auth()->user()?->can('cupones.crear')    ?? false); }
    public static function canEdit(Model $r): bool    { return (Filament::getTenant()?->tieneModulo('cupones') ?? false) && (auth()->user()?->can('cupones.editar')   ?? false); }
    public static function canDelete(Model $r): bool  { return (Filament::getTenant()?->tieneModulo('cupones') ?? false) && (auth()->user()?->can('cupones.eliminar') ?? false); }

    public static function form(Schema $schema): Schema
    {
        return CuponForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CuponesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCupones::route('/'),
            'create' => CreateCupon::route('/create'),
            'edit'   => EditCupon::route('/{record}/edit'),
        ];
    }
}
