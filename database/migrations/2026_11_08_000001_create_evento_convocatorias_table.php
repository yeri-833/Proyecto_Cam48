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
        if (! Schema::hasTable('evento_convocatorias')) {
            Schema::create('evento_convocatorias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('evento_id');
                $table->unsignedBigInteger('tutor_id');
                $table->string('alumno_matricula')->nullable();
                $table->string('canal_notificacion')->nullable();
                $table->boolean('notificado')->default(false);
                $table->boolean('asistio')->default(false);
                $table->timestamp('fecha_asistencia')->nullable();
                $table->timestamps();

                $table->unique(['evento_id', 'tutor_id'], 'uk_evento_tutor_convocatoria');
                $table->index('evento_id', 'idx_evento_convocatorias_evento');
                $table->index('tutor_id', 'idx_evento_convocatorias_tutor');

                if (Schema::hasTable('eventos')) {
                    $table->foreign('evento_id', 'fk_evento_convocatorias_evento_id')
                        ->references('id')
                        ->on('eventos')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('tutores')) {
                    $table->foreign('tutor_id', 'fk_evento_convocatorias_tutor_id')
                        ->references('id')
                        ->on('tutores')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('alumnos')) {
                    $table->foreign('alumno_matricula', 'fk_evento_convocatorias_matricula')
                        ->references('matricula')
                        ->on('alumnos')
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
        Schema::dropIfExists('evento_convocatorias');
    }
};
