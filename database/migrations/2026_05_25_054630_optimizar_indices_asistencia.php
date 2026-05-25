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
        $indexesFound = Schema::getIndexes('registros_asistencia');
        $indexNames = array_column($indexesFound, 'name');

        Schema::table('registros_asistencia', function (Blueprint $table) use ($indexNames) {
            // Eliminar el índice duplicado si existe
            if (in_array('idx_asistencia_persona_fecha', $indexNames)) {
                $table->dropIndex('idx_asistencia_persona_fecha');
            }

            // Crear nuevo índice enfocado en las consultas de fecha (Historial/Reportes)
            if (!in_array('idx_fecha_rol', $indexNames)) {
                $table->index(['fecha', 'rol'], 'idx_fecha_rol');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexesFound = Schema::getIndexes('registros_asistencia');
        $indexNames = array_column($indexesFound, 'name');

        Schema::table('registros_asistencia', function (Blueprint $table) use ($indexNames) {
            // Revertir los cambios
            if (in_array('idx_fecha_rol', $indexNames)) {
                $table->dropIndex('idx_fecha_rol');
            }
            if (!in_array('idx_asistencia_persona_fecha', $indexNames)) {
                $table->index(['id_persona', 'fecha'], 'idx_asistencia_persona_fecha');
            }
        });
    }
};
