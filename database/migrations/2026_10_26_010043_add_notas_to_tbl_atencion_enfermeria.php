<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de enfermería: estado del paciente, signos vitales enlazados, aviso al médico
 * y varias actividades por nota. Es idempotente porque el SQL también se ejecuta a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $actividad = collect(Schema::getColumns('tbl_atencion_enfermeria'))->firstWhere('name', 'actividad');

        if ($actividad && ! $actividad['nullable']) {
            Schema::table('tbl_atencion_enfermeria', fn (Blueprint $table) => $table->string('actividad', 180)->nullable()->change());
        }

        if (! Schema::hasColumn('tbl_atencion_enfermeria', 'estado_paciente')) {
            Schema::table('tbl_atencion_enfermeria', function (Blueprint $table): void {
                $table->enum('estado_paciente', ['Mejoró', 'Sin cambios', 'Empeoró'])->nullable()->after('fecha_hora');
            });
        }

        if (! Schema::hasColumn('tbl_atencion_enfermeria', 'id_signos_vitales')) {
            Schema::table('tbl_atencion_enfermeria', function (Blueprint $table): void {
                $table->bigInteger('id_signos_vitales')->nullable()->after('estado_paciente');
                $table->foreign('id_signos_vitales', 'fk_atencion_enfermeria_signos')
                    ->references('id_signos_vitales')
                    ->on('tbl_signos_vitales')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasColumn('tbl_atencion_enfermeria', 'aviso_medico')) {
            Schema::table('tbl_atencion_enfermeria', function (Blueprint $table): void {
                $table->boolean('aviso_medico')->default(false)->after('observaciones');
            });
        }

        if (! Schema::hasTable('tbl_atencion_enfermeria_actividad')) {
            Schema::create('tbl_atencion_enfermeria_actividad', function (Blueprint $table): void {
                $table->bigIncrements('id_actividad');
                $table->bigInteger('id_atencion_enfermeria');
                $table->string('actividad', 100);
                $table->foreign('id_atencion_enfermeria', 'fk_actividad_atencion_enfermeria')
                    ->references('id_atencion_enfermeria')
                    ->on('tbl_atencion_enfermeria')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_atencion_enfermeria_actividad');

        Schema::table('tbl_atencion_enfermeria', function (Blueprint $table): void {
            $table->dropForeign('fk_atencion_enfermeria_signos');
            $table->dropColumn(['estado_paciente', 'id_signos_vitales', 'aviso_medico']);
        });
    }
};
