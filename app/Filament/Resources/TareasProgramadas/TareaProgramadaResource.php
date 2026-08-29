<?php

namespace App\Filament\Resources\TareasProgramadas;

use App\Filament\Resources\TareasProgramadas\Pages\CreateTareaProgramada;
use App\Filament\Resources\TareasProgramadas\Pages\EditTareaProgramada;
use App\Filament\Resources\TareasProgramadas\Pages\ListTareasProgramadas;
use App\Models\TareaProgramada;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TareaProgramadaResource extends Resource
{
    protected static ?string $model                         = TareaProgramada::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel               = 'Tareas Programadas';
    protected static ?string $modelLabel                    = 'Tarea Programada';
    protected static ?string $pluralModelLabel              = 'Tareas Programadas';
    protected static string|UnitEnum|null $navigationGroup  = 'Sistema';
    protected static ?int    $navigationSort                = 10;

    public static function canAccess(): bool              { return auth()->user()?->can('admin.configuracion') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('admin.configuracion') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('admin.configuracion') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('admin.configuracion') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return TareaProgramadaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TareaProgramadaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTareasProgramadas::route('/'),
            'create' => CreateTareaProgramada::route('/create'),
            'edit'   => EditTareaProgramada::route('/{record}/edit'),
        ];
    }
}
