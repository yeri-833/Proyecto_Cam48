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
        if (! Schema::hasTable('notificaciones_procesadas')) {
            Schema::create('notificaciones_procesadas', function (Blueprint $table) {
                $table->id();
                $table->string('idempotency_key', 191)->unique();
                $table->string('destinatario_tipo', 50);
                $table->string('destinatario_id', 100);
                $table->string('canal', 50);
                $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE, EN_PROCESO, ENTREGADO, FALLIDO
                $table->unsignedInteger('intentos')->default(0);
                $table->text('ultimo_error')->nullable();
                $table->timestamp('fecha_entrega')->nullable();
                $table->timestamps();

                $table->index(['destinatario_tipo', 'destinatario_id'], 'idx_notif_destinatario');
                $table->index('estado');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_procesadas');
    }
};
