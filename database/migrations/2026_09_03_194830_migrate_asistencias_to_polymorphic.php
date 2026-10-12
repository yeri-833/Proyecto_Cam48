<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registros_asistencia', function (Blueprint $table) {
            if (!Schema::hasColumn('registros_asistencia', 'asistible_type')) {
                $table->string('asistible_type')->nullable()->after('id');
            }
            if (!Schema::hasColumn('registros_asistencia', 'asistible_id')) {
                $table->string('asistible_id')->nullable()->after('asistible_type');
            }
            
            $indexes = Schema::getIndexes('registros_asistencia');
            $indexNames = array_column($indexes, 'name');
            
            if (!in_array('registros_asistencia_asistible_type_asistible_id_index', $indexNames)) {
                $table->index(['asistible_type', 'asistible_id']);
            }
            if (in_array('idx_asistencia_fecha_rol', $indexNames)) {
                $table->dropIndex('idx_asistencia_fecha_rol');
            }
            if (!in_array('idx_fecha_type', $indexNames)) {
                $table->index(['fecha', 'asistible_type'], 'idx_fecha_type');
            }
        });

        // Migrar datos existentes si las columnas de origen todavía existen
        if (Schema::hasColumn('registros_asistencia', 'rol') && Schema::hasColumn('registros_asistencia', 'id_persona')) {
            DB::statement("UPDATE registros_asistencia SET asistible_type = 'App\\\\Models\\\\Alumno', asistible_id = id_persona WHERE rol = 'Alumno'");
            DB::statement("UPDATE registros_asistencia SET asistible_type = 'App\\\\Models\\\\Tutor', asistible_id = id_persona WHERE rol = 'Tutor'");
            DB::statement("UPDATE registros_asistencia SET asistible_type = 'App\\\\Models\\\\Personal', asistible_id = id_persona WHERE rol = 'Personal'");

            Schema::table('registros_asistencia', function (Blueprint $table) {
                $table->dropColumn(['id_persona', 'rol']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registros_asistencia', function (Blueprint $table) {
            $table->string('id_persona')->nullable();
            $table->string('rol')->nullable();
        });

        // Desmigrar datos (básico)
        DB::statement("UPDATE registros_asistencia SET id_persona = asistible_id, rol = 'Alumno' WHERE asistible_type = 'App\\\\Models\\\\Alumno'");
        DB::statement("UPDATE registros_asistencia SET id_persona = asistible_id, rol = 'Tutor' WHERE asistible_type = 'App\\\\Models\\\\Tutor'");
        DB::statement("UPDATE registros_asistencia SET id_persona = asistible_id, rol = 'Personal' WHERE asistible_type = 'App\\\\Models\\\\Personal'");

        Schema::table('registros_asistencia', function (Blueprint $table) {
            $table->dropIndex(['asistible_type', 'asistible_id']);
            $table->dropColumn(['asistible_type', 'asistible_id']);
        });
    }
};
