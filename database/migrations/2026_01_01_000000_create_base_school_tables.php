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
        // 1. Alumnos
        if (!Schema::hasTable('alumnos')) {
            Schema::create('alumnos', function (Blueprint $table) {
                $table->string('matricula')->primary();
                $table->string('nombre');
                $table->string('apellido');
                $table->string('sexo')->default('M');
                $table->string('curp')->nullable();
                $table->string('nivel_educativo')->nullable();
                $table->string('grado')->nullable();
                $table->string('grupo')->nullable();
                $table->string('discapacidad')->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->integer('edad')->nullable();
                $table->text('comentarios')->nullable();
                $table->boolean('activo')->default(true);
            });
        }

        // 2. Tutores
        if (!Schema::hasTable('tutores')) {
            Schema::create('tutores', function (Blueprint $table) {
                $table->id();
                $table->string('matricula_alumno')->nullable();
                $table->string('t1_nombre');
                $table->string('t1_apellido')->nullable();
                $table->string('t1_telefono');
            });
        }

        // 3. Personal
        if (!Schema::hasTable('personal')) {
            Schema::create('personal', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('nombre_completo');
                $table->string('rfc')->nullable();
                $table->string('categoria')->nullable();
                $table->string('funcion')->nullable();
                $table->string('plaza')->nullable();
                $table->string('grado_grupo')->nullable();
                $table->string('telefono')->nullable();
                $table->boolean('activo')->default(true);
            });
        }

        // 4. Registros Asistencia
        if (!Schema::hasTable('registros_asistencia')) {
            Schema::create('registros_asistencia', function (Blueprint $table) {
                $table->id();
                $table->string('id_persona')->nullable();
                $table->string('rol')->nullable();
                $table->date('fecha');
                $table->time('hora');
            });
        }

        // 5. Eventos
        if (!Schema::hasTable('eventos')) {
            Schema::create('eventos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_evento');
                $table->dateTime('fecha_hora');
                $table->string('ubicacion')->nullable();
                $table->text('descripcion')->nullable();
                $table->timestamps();
            });
        }

        // 6. Archivos Sistema (Expedientes)
        if (!Schema::hasTable('archivos_sistema')) {
            Schema::create('archivos_sistema', function (Blueprint $table) {
                $table->id();
                $table->string('matricula');
                $table->string('nombre_original');
                $table->string('nombre_servidor');
                $table->string('tipo_mime')->nullable();
                $table->bigInteger('tamano_bytes')->nullable();
                $table->dateTime('fecha_subida')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archivos_sistema');
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('registros_asistencia');
        Schema::dropIfExists('personal');
        Schema::dropIfExists('tutores');
        Schema::dropIfExists('alumnos');
    }
};
