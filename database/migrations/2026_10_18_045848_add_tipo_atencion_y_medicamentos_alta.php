<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el tipo de atención (hospitalización o emergencia) y los medicamentos
 * indicados para casa al finalizar la atención. Es idempotente porque la base
 * puede haberse actualizado antes con SQL manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_ingreso', 'tipo_atencion')) {
            Schema::table('tbl_ingreso', function (Blueprint $table): void {
                $table->enum('tipo_atencion', ['Hospitalización', 'Emergencia'])
                    ->default('Hospitalización')
                    ->after('id_paciente');
            });
        }

        if (! Schema::hasTable('tbl_alta_medicamento')) {
            Schema::create('tbl_alta_medicamento', function (Blueprint $table): void {
                $table->increments('id_alta_medicamento');
                $table->integer('id_alta');
                $table->string('medicamento', 255);
                $table->string('dosis', 100);
                $table->string('via_administracion', 80)->nullable();
                $table->string('frecuencia', 100);
                $table->string('duracion', 100)->nullable();
                $table->foreign('id_alta', 'fk_alta_medicamento_alta')
                    ->references('id_alta')
                    ->on('tbl_alta_medica')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_alta_medicamento');

        if (Schema::hasColumn('tbl_ingreso', 'tipo_atencion')) {
            Schema::table('tbl_ingreso', fn (Blueprint $table) => $table->dropColumn('tipo_atencion'));
        }
    }
};
