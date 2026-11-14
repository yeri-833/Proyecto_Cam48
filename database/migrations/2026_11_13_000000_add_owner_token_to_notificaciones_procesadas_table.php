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
                if (! Schema::hasColumn('notificaciones_procesadas', 'owner_token')) {
                    $table->string('owner_token', 64)->nullable()->after('estado');
                }
                if (! Schema::hasColumn('notificaciones_procesadas', 'lease_expires_at')) {
                    $table->timestamp('lease_expires_at')->nullable()->after('owner_token');
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
                if (Schema::hasColumn('notificaciones_procesadas', 'lease_expires_at')) {
                    $table->dropColumn('lease_expires_at');
                }
                if (Schema::hasColumn('notificaciones_procesadas', 'owner_token')) {
                    $table->dropColumn('owner_token');
                }
            });
        }
    }
};
