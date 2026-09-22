<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'admin' => Tab::make('Usuarios Admin')
                ->icon('heroicon-o-shield-check')
                ->badge(User::whereExists(fn($q) => $q->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereNull('roles.empresa_id')
                )->count())
                ->modifyQueryUsing(fn($query) => $query->whereExists(fn($q) => $q->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereNull('roles.empresa_id')
                )),

            'pdv' => Tab::make('Usuarios PDV')
                ->icon('heroicon-o-building-storefront')
                ->badge(User::whereExists(fn($q) => $q->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereNotNull('roles.empresa_id')
                )->count())
                ->modifyQueryUsing(fn($query) => $query->whereExists(fn($q) => $q->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereNotNull('roles.empresa_id')
                )),
        ];
    }
}
