<?php

namespace App\Filament\Pdv\Resources\Users;

use App\Filament\Pdv\Resources\Users\Pages\CreateUser;
use App\Filament\Pdv\Resources\Users\Pages\EditUser;
use App\Filament\Pdv\Resources\Users\Pages\ListUsers;
use App\Filament\Pdv\Resources\Users\Schemas\UserForm;
use App\Filament\Pdv\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $recordTitleAttribute = 'User';

    protected static ?string $tenantOwnershipRelationshipName = 'empresas';

    public static function canAccess(): bool              { return Filament::getTenant()->tieneModulo('usuarios_roles') && (auth()->user()?->can('usuarios.ver') ?? false); }
    public static function canCreate(): bool              { return auth()->user()?->can('usuarios.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('usuarios.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('usuarios.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
