<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Médico al que enfermería avisó desde una nota. Es idempotente porque el SQL también se ejecuta a mano en Railway.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tbl_atencion_enfermeria', 'id_personal_avisado')) {
            return;
        }

        Schema::table('tbl_atencion_enfermeria', function (Blueprint $table): void {
            $table->integer('id_personal_avisado')->nullable()->after('aviso_medico');
            $table->foreign('id_personal_avisado', 'fk_atencion_enfermeria_medico_avisado')
                ->references('id_personal')
                ->on('tbl_personal')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tbl_atencion_enfermeria', 'id_personal_avisado')) {
            return;
        }

        Schema::table('tbl_atencion_enfermeria', function (Blueprint $table): void {
            $table->dropForeign('fk_atencion_enfermeria_medico_avisado');
            $table->dropColumn('id_personal_avisado');
        });
    }
};
