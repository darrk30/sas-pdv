<?php

namespace Database\Seeders;

use App\Enums\EstadoGeneral;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Database\Seeder;

class PlanBasicoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el plan Básico si aún no existe
        $plan = Plan::firstOrCreate(
            ['nombre' => 'Básico'],
            [
                'descripcion'       => 'Plan de inicio para pequeños negocios.',
                'precio'            => 50.00,
                'ciclo_facturacion' => 'mensual',
                'maximo_usuarios'   => 5,
                'maximo_locales'    => 1,
                'estado'            => EstadoGeneral::Activo,
            ]
        );

        // 2. Asignar a la primera empresa registrada
        $empresa = Empresa::first();

        if (! $empresa) {
            $this->command->warn('No hay empresas registradas. Se omite la suscripción.');
            return;
        }

        // Evitar duplicados: solo crear si no tiene suscripción activa
        $yaActiva = Suscripcion::where('empresa_id', $empresa->id)
            ->where('estado', EstadoGeneral::Activo)
            ->exists();

        if ($yaActiva) {
            $this->command->info("La empresa [{$empresa->name}] ya tiene una suscripción activa. Se omite.");
            return;
        }

        $fin = now()->addMonth()->toDateString();

        Suscripcion::create([
            'empresa_id'    => $empresa->id,
            'plan_id'       => $plan->id,
            'precio_pagado' => $plan->precio,
            'fecha_inicio'  => now()->toDateString(),
            'fecha_fin'     => $fin,
            'estado'        => EstadoGeneral::Activo,
        ]);

        $this->command->info("Plan [{$plan->nombre}] → [{$empresa->name}] activo hasta {$fin}.");
    }
}
