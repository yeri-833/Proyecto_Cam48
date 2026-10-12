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
            // Nota: la columna 'rol' fue eliminada en la migración polimórfica.
            // El índice ahora cubre (fecha, asistible_type) para los filtros del Historial.
            if (!in_array('idx_fecha_type', $indexNames)) {
                $table->index(['fecha', 'asistible_type'], 'idx_fecha_type');
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
