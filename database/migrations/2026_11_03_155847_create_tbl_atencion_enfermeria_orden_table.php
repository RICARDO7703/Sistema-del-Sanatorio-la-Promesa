<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Órdenes médicas que enfermería indica como aplicadas o no aplicadas en cada nota.
 * Se guarda el texto de la orden tal como estaba, para que el historial no cambie si el médico la modifica después.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_atencion_enfermeria_orden')) {
            return;
        }
        Schema::create('tbl_atencion_enfermeria_orden', function (Blueprint $table): void {
            $table->bigIncrements('id_orden_aplicada');
            $table->bigInteger('id_atencion_enfermeria');
            $table->integer('id_evolucion');
            $table->string('tipo', 20);
            $table->string('descripcion', 255);
            $table->boolean('aplicada');
            $table->string('motivo', 255)->nullable();
            $table->foreign('id_atencion_enfermeria', 'fk_orden_aplicada_nota')
                ->references('id_atencion_enfermeria')->on('tbl_atencion_enfermeria')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('id_evolucion', 'fk_orden_aplicada_evolucion')
                ->references('id_evolucion')->on('tbl_evolucion_clinica')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_atencion_enfermeria_orden');
    }
};
