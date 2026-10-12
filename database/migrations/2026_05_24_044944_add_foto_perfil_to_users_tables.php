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
            if (!Schema::hasColumn('alumnos', 'foto_perfil')) {
                $table->string('foto_perfil')->nullable()->after('curp');
            }
        });

        Schema::table('personal', function (Blueprint $table) {
            if (!Schema::hasColumn('personal', 'foto_perfil')) {
                $table->string('foto_perfil')->nullable();
            }
        });

        Schema::table('tutores', function (Blueprint $table) {
            if (!Schema::hasColumn('tutores', 't1_foto_perfil')) {
                $table->string('t1_foto_perfil')->nullable();
            }
            if (!Schema::hasColumn('tutores', 't2_foto_perfil')) {
                $table->string('t2_foto_perfil')->nullable();
            }
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
