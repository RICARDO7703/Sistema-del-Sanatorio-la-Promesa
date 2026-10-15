<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recuperacion_contrasenas', function (Blueprint $table) {
            $table->unsignedInteger('id_usuario')->primary();
            $table->string('correo');
            $table->string('token', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuperacion_contrasenas');
    }
};
