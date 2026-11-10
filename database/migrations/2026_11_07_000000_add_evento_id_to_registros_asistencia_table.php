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
        if (Schema::hasTable('registros_asistencia') && ! Schema::hasColumn('registros_asistencia', 'evento_id')) {
            Schema::table('registros_asistencia', function (Blueprint $table) {
                $table->unsignedBigInteger('evento_id')->nullable()->after('hora');
                $table->index('evento_id', 'idx_registros_asistencia_evento_id');

                if (Schema::hasTable('eventos')) {
                    $table->foreign('evento_id', 'fk_registros_asistencia_evento_id')
                        ->references('id')
                        ->on('eventos')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('registros_asistencia') && Schema::hasColumn('registros_asistencia', 'evento_id')) {
            Schema::table('registros_asistencia', function (Blueprint $table) {
                if (Schema::hasTable('eventos')) {
                    $table->dropForeign(['evento_id']);
                }
                $table->dropIndex('idx_registros_asistencia_evento_id');
                $table->dropColumn('evento_id');
            });
        }
    }
};
