<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talla (cm) y peso (lb) como datos del paciente, junto al tipo de sangre, en lugar de pedirlos con los signos vitales.
 * Es idempotente porque el SQL también se ejecuta a mano en Railway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_paciente', function (Blueprint $table): void {
            if (! Schema::hasColumn('tbl_paciente', 'talla')) {
                $table->decimal('talla', 5, 2)->nullable()->after('tipo_sangre');
            }
            if (! Schema::hasColumn('tbl_paciente', 'peso')) {
                $table->decimal('peso', 6, 2)->nullable()->after('talla');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_paciente', function (Blueprint $table): void {
            foreach (['peso', 'talla'] as $columna) {
                if (Schema::hasColumn('tbl_paciente', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
