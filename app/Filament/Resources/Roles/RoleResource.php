<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel               = 'Roles Admin';
    protected static ?string $modelLabel                    = 'Rol';
    protected static ?string $pluralModelLabel              = 'Roles';
    protected static string|UnitEnum|null $navigationGroup  = 'Usuarios';
    protected static ?int    $navigationSort                = 2;
    protected static ?string $recordTitleAttribute          = 'name';

    // Roles del panel admin: empresa_id = null (sistema)
    public static function canAccess(): bool              { return auth()->user()?->can('admin.usuarios.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('admin.usuarios.crear') ?? false; }
    public static function canEdit(Model $record): bool   {
        return (auth()->user()?->can('admin.usuarios.editar') ?? false)
            && $record->name !== 'Super Administrador';
    }
    public static function canDelete(Model $record): bool {
        return (auth()->user()?->can('admin.usuarios.eliminar') ?? false)
            && $record->name !== 'Super Administrador';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('empresa_id');
    }

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
