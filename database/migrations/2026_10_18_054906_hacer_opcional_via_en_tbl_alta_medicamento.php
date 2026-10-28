<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La vía ya no se pide en los medicamentos para casa. Es idempotente porque
 * la columna puede haberse modificado antes con SQL manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_alta_medicamento', 'via_administracion')) {
            return;
        }

        $columna = collect(Schema::getColumns('tbl_alta_medicamento'))->firstWhere('name', 'via_administracion');

        if ($columna && ! $columna['nullable']) {
            Schema::table('tbl_alta_medicamento', fn (Blueprint $table) => $table->string('via_administracion', 80)->nullable()->change());
        }
    }

    /**
     * No se vuelve a exigir la vía: rompería los medicamentos ya guardados sin ella.
     */
    public function down(): void {}
};
