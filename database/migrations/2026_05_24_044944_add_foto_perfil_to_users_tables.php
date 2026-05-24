<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('foto_perfil')->nullable()->after('curp');
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->string('foto_perfil')->nullable()->after('email');
        });

        Schema::table('tutores', function (Blueprint $table) {
            $table->string('t1_foto_perfil')->nullable()->after('t1_email');
            $table->string('t2_foto_perfil')->nullable()->after('t2_telefono');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropColumn('foto_perfil');
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn('foto_perfil');
        });

        Schema::table('tutores', function (Blueprint $table) {
            $table->dropColumn(['t1_foto_perfil', 't2_foto_perfil']);
        });
    }
};
