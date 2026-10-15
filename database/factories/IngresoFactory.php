<?php

namespace Database\Factories;

use App\Models\Ingreso;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<Ingreso> */
class IngresoFactory extends Factory
{
    protected $model = Ingreso::class;

    public function definition(): array
    {
        return [
            'id_paciente' => fn () => DB::table('tbl_paciente')->insertGetId([
                'numero_expediente' => fake()->unique()->numerify('TEST-########'),
                'nombres' => 'Paciente', 'apellidos' => fake()->lastName(),
                'telefono' => '5555-5555', 'id_usuario_creacion' => 1,
            ]),
            'id_personal_registra' => 1,
            'fecha_ingreso' => now()->subDay(),
            'motivo_ingreso' => 'Ingreso de prueba',
            'estado' => 'Activo',
            'id_usuario_creacion' => 1,
        ];
    }
}
