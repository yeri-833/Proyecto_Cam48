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

            // Si la columna asistible_type ya existe, indexar (fecha, asistible_type).
            // Si la columna rol existe (orden cronológico previo a la polimórfica), indexar (fecha, rol).
            if (Schema::hasColumn('registros_asistencia', 'asistible_type')) {
                if (!in_array('idx_fecha_type', $indexNames)) {
                    $table->index(['fecha', 'asistible_type'], 'idx_fecha_type');
                }
            } elseif (Schema::hasColumn('registros_asistencia', 'rol')) {
                if (!in_array('idx_asistencia_fecha_rol', $indexNames)) {
                    $table->index(['fecha', 'rol'], 'idx_asistencia_fecha_rol');
                }
            } else {
                if (!in_array('idx_asistencia_fecha', $indexNames)) {
                    $table->index('fecha', 'idx_asistencia_fecha');
                }
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
            if (in_array('idx_fecha_type', $indexNames)) {
                $table->dropIndex('idx_fecha_type');
            }
        });
    }
};
