<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('alumno_tutor')) {
            Schema::create('alumno_tutor', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tutor_id');
                $table->string('alumno_matricula');
                $table->string('parentesco')->nullable();
                $table->boolean('es_tutor_principal')->default(true);
                $table->timestamps();

                $table->unique(['tutor_id', 'alumno_matricula'], 'uk_alumno_tutor');
                $table->index('alumno_matricula', 'idx_alumno_tutor_matricula');

                if (Schema::hasTable('tutores')) {
                    $table->foreign('tutor_id', 'fk_alumno_tutor_tutor_id')
                        ->references('id')
                        ->on('tutores')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('alumnos')) {
                    $table->foreign('alumno_matricula', 'fk_alumno_tutor_matricula')
                        ->references('matricula')
                        ->on('alumnos')
                        ->onDelete('cascade');
                }
            });

            // Migrar datos existentes desde tutores.matricula_alumno si existen registros históricos
            if (Schema::hasTable('tutores') && Schema::hasColumn('tutores', 'matricula_alumno')) {
                $columnas = ['id', 'matricula_alumno'];
                if (Schema::hasColumn('tutores', 't1_parentesco')) {
                    $columnas[] = 't1_parentesco';
                }

                $registros = DB::table('tutores')
                    ->whereNotNull('matricula_alumno')
                    ->where('matricula_alumno', '!=', '')
                    ->get($columnas);

                foreach ($registros as $reg) {
                    // Validar si el alumno existe en caso de que existan claves foráneas activas
                    $alumnoExiste = Schema::hasTable('alumnos')
                        ? DB::table('alumnos')->where('matricula', $reg->matricula_alumno)->exists()
                        : true;

                    if ($alumnoExiste) {
                        DB::table('alumno_tutor')->insertOrIgnore([
                            'tutor_id' => $reg->id,
                            'alumno_matricula' => $reg->matricula_alumno,
                            'parentesco' => property_exists($reg, 't1_parentesco') ? $reg->t1_parentesco : null,
                            'es_tutor_principal' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumno_tutor');
    }
};
