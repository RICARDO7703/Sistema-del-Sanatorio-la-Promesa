<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_ingreso', 'id_ingreso_anterior')) {
            Schema::table('tbl_ingreso', function (Blueprint $table): void {
                $table->integer('id_ingreso_anterior')->nullable();
                $table->foreign('id_ingreso_anterior', 'fk_ingreso_anterior')->references('id_ingreso')->on('tbl_ingreso')->restrictOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::table('tbl_ingreso', function (Blueprint $table): void {
            $table->dropForeign('fk_ingreso_anterior');
            $table->dropColumn('id_ingreso_anterior');
        });
    }
};
