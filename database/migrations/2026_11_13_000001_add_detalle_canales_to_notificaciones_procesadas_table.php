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
        if (Schema::hasTable('notificaciones_procesadas')) {
            Schema::table('notificaciones_procesadas', function (Blueprint $table) {
                if (! Schema::hasColumn('notificaciones_procesadas', 'detalle_canales')) {
                    $table->text('detalle_canales')->nullable()->after('canal');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notificaciones_procesadas')) {
            Schema::table('notificaciones_procesadas', function (Blueprint $table) {
                if (Schema::hasColumn('notificaciones_procesadas', 'detalle_canales')) {
                    $table->dropColumn('detalle_canales');
                }
            });
        }
    }
};
