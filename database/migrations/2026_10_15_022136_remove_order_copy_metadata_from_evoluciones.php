<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('tbl_evolucion_clinica')->whereNotNull('ordenes_medicas')->lockForUpdate()
                ->chunkById(100, function (Collection $registros): void {
                    foreach ($registros as $registro) {
                        $ordenes = json_decode($registro->ordenes_medicas, true, 512, JSON_THROW_ON_ERROR);
                        if (! is_array($ordenes) || (! array_key_exists('modo', $ordenes) && ! array_key_exists('origen_evolucion', $ordenes))) {
                            continue;
                        }
                        unset($ordenes['modo'], $ordenes['origen_evolucion']);
                        DB::table('tbl_evolucion_clinica')->where('id_evolucion', $registro->id_evolucion)
                            ->update(['ordenes_medicas' => json_encode($ordenes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
                    }
                }, 'id_evolucion');
        });
    }

    public function down(): void
    {
        // Los metadatos eliminados no se reconstruyen; las indicaciones clínicas se conservan.
    }
};
