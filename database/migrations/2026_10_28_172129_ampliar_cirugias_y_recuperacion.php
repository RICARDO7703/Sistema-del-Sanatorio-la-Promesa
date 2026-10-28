<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tbl_cirugia MODIFY id_personal_indica INT NULL, MODIFY fecha_programada DATETIME NULL, MODIFY estado ENUM('Indicada','Programada','Evaluación preoperatoria','Aprobada','En cirugía','Realizada','Cancelada') NOT NULL DEFAULT 'Programada'");
            DB::statement("ALTER TABLE tbl_cirugia_equipo MODIFY rol_quirurgico ENUM('Cirujano principal','Cirujano externo','Anestesiólogo','Enfermera instrumentista','Enfermera circulante','Especialista externo','Otro') NULL DEFAULT NULL");
        }
        foreach (['fecha_fin_programada', 'fecha_inicio_real', 'fecha_fin_real'] as $campo) {
            if (! Schema::hasColumn('tbl_cirugia', $campo)) {
                Schema::table('tbl_cirugia', fn (Blueprint $t) => $t->dateTime($campo)->nullable());
            }
        }
        if (! Schema::hasColumn('tbl_cirugia', 'motivo_cancelacion')) {
            Schema::table('tbl_cirugia', fn (Blueprint $t) => $t->string('motivo_cancelacion', 255)->nullable());
        }
        if (! Schema::hasColumn('tbl_signos_vitales', 'id_cirugia')) {
            Schema::table('tbl_signos_vitales', fn (Blueprint $t) => $t->integer('id_cirugia')->nullable());
        }
        if (DB::getDriverName() === 'mysql') {
            $existe = fn (string $tabla, string $nombre) => DB::table('information_schema.statistics')->where('table_schema', DB::connection()->getDatabaseName())->where('table_name', $tabla)->where('index_name', $nombre)->exists();
            if (! $existe('tbl_cirugia', 'uq_cirugia_ingreso')) {
                DB::statement('ALTER TABLE tbl_cirugia ADD UNIQUE KEY uq_cirugia_ingreso (id_cirugia,id_ingreso)');
            }
            if (! $existe('tbl_cirugia', 'idx_cirugia_agenda')) {
                DB::statement('ALTER TABLE tbl_cirugia ADD INDEX idx_cirugia_agenda (estado,fecha_programada)');
            }
            if (! $existe('tbl_signos_vitales', 'idx_signos_cirugia_fecha')) {
                DB::statement('ALTER TABLE tbl_signos_vitales ADD INDEX idx_signos_cirugia_fecha (id_cirugia,fecha_hora)');
            }
            $fk = DB::table('information_schema.table_constraints')->where('constraint_schema', DB::connection()->getDatabaseName())->where('table_name', 'tbl_signos_vitales')->where('constraint_name', 'fk_signos_cirugia_ingreso')->exists();
            if (! $fk) {
                DB::statement('ALTER TABLE tbl_signos_vitales ADD CONSTRAINT fk_signos_cirugia_ingreso FOREIGN KEY (id_cirugia,id_ingreso) REFERENCES tbl_cirugia(id_cirugia,id_ingreso) ON DELETE RESTRICT ON UPDATE CASCADE');
            }
        }
    }

    public function down(): void
    {
        // Conservar las cirugías y los controles registrados al revertir código.
    }
};
