<?php

namespace App\Filament\Pdv\Resources\Categorias\Pages;

use App\Filament\Pdv\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategoria extends CreateRecord
{
    protected static string $resource = CategoriaResource::class;
    /**
     * Interceptamos los datos del formulario antes de que se guarden en MySQL
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1. Generar slug si no existe
        if (empty($data['slug']) && !empty($data['nombre'])) {
            $data['slug'] = Str::slug($data['nombre']);
        }

        // 2. Calcular el número de orden automático por empresa
        $empresaActual = Filament::getTenant();

        if ($empresaActual) {
            // Busca el número de orden más alto en esta tienda específica
            $maxOrden = Categoria::where('empresa_id', $empresaActual->id)->max('orden');
            // Si ya hay categorías, suma 1. Si no hay ninguna, empieza en 1.
            $data['orden'] = $maxOrden ? $maxOrden + 1 : 1;
        } else {
            $data['orden'] = 1;
        }

        return $data;
    }
}
