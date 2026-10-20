<?php

namespace Tests\Isolation;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

final class SyntheticSchema
{
    public static function create(Connection $connection): void
    {
        IsolationGuard::assertRuntime();
        if (getenv('FAMILYLOCK_ISOLATION_MODE') !== 'phpunit' || $connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('ISOLATION_SYNTHETIC_SCHEMA_REJECTED');
        }

        $schema = $connection->getSchemaBuilder();
        $schema->create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_completo')->nullable();
            $table->string('correo')->nullable()->unique();
            $table->string('telefono')->nullable();
            $table->string('password')->nullable();
            $table->string('tipo_usuario')->nullable();
            $table->timestamp('fecha_registro')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
        });

        $schema->create('alumnos', function (Blueprint $table): void {
            $table->string('matricula')->primary();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('sexo')->default('M');
            foreach (['curp', 'nivel_educativo', 'grado', 'grupo', 'discapacidad', 'comentarios', 'rol', 'foto_perfil'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('fecha_nacimiento')->nullable();
            $table->integer('edad')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_registro')->nullable();
            $table->timestamps();
        });

        $schema->create('personal', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('nombre_completo');
            foreach (['rfc', 'categoria', 'funcion', 'plaza', 'grado_grupo', 'telefono', 'email', 'foto_perfil', 'telegram_chat_id', 'creado_por'] as $column) {
                $table->string($column)->nullable();
            }
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_registro')->nullable();
        });

        $schema->create('tutores', function (Blueprint $table): void {
            $table->id();
            $table->string('matricula_alumno')->nullable();
            foreach (['t1_nombre', 't1_apellido', 't1_parentesco', 't1_telefono', 't1_direccion', 't1_profesion', 't1_email', 't1_foto_perfil', 't1_telegram_chat_id', 't2_nombre', 't2_apellido', 't2_parentesco', 't2_telefono', 't2_email', 't2_foto_perfil'] as $column) {
                $table->string($column)->nullable();
            }
            $table->softDeletes();
        });

        $schema->create('registros_asistencia', function (Blueprint $table): void {
            $table->id();
            $table->string('asistible_type')->nullable();
            $table->string('asistible_id')->nullable();
            $table->string('id_persona')->nullable();
            $table->string('rol')->nullable();
            $table->string('modo_registro')->nullable();
            $table->date('fecha');
            $table->time('hora');
            $table->timestamps();
        });

        $schema->create('archivos_sistema', function (Blueprint $table): void {
            $table->id();
            $table->string('matricula');
            $table->string('nombre_original');
            $table->string('nombre_servidor');
            $table->string('tipo_mime')->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->timestamp('fecha_subida')->nullable();
            $table->timestamps();
        });

        $schema->create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->dateTime('fecha_hora');
            $table->string('ubicacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}
