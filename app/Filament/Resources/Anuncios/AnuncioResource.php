<?php

namespace App\Filament\Resources\Anuncios;

use App\Filament\Resources\Anuncios\Pages\CreateAnuncio;
use App\Filament\Resources\Anuncios\Pages\EditAnuncio;
use App\Filament\Resources\Anuncios\Pages\ListAnuncios;
use App\Models\Anuncio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AnuncioResource extends Resource
{
    protected static ?string $model                        = Anuncio::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel              = 'Anuncios';
    protected static ?string $modelLabel                   = 'Anuncio';
    protected static ?string $pluralModelLabel             = 'Anuncios';
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int    $navigationSort               = 9;

    public static function canAccess(): bool              { return auth()->user()?->can('admin.configuracion') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('admin.configuracion') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('admin.configuracion') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('admin.configuracion') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return AnuncioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnuncioTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAnuncios::route('/'),
            'create' => CreateAnuncio::route('/create'),
            'edit'   => EditAnuncio::route('/{record}/edit'),
        ];
    }
}
