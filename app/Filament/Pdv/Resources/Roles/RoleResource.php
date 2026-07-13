<?php

namespace App\Filament\Pdv\Resources\Roles;

use App\Filament\Pdv\Resources\Roles\Pages\CreateRole;
use App\Filament\Pdv\Resources\Roles\Pages\EditRole;
use App\Filament\Pdv\Resources\Roles\Pages\ListRoles;
use App\Filament\Pdv\Resources\Roles\Schemas\RoleForm;
use App\Filament\Pdv\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool              { return auth()->user()?->can('roles.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('roles.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('roles.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('roles.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit'   => EditRole::route('/{record}/edit'),
        ];
    }
}
