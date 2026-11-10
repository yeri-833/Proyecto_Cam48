<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventoMigrationTest extends TestCase
{
    public function test_migracion_evento_id_en_instalacion_nueva_y_actualizacion_existente(): void
    {
        $migration = new class extends \Illuminate\Database\Migrations\Migration {
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

        // 1. Simulación de base de datos v1.4.7 sin evento_id pero con tabla eventos
        Schema::dropIfExists('registros_asistencia');
        Schema::dropIfExists('eventos');

        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('registros_asistencia', function (Blueprint $table) {
            $table->id();
            $table->string('asistible_type')->nullable();
            $table->string('asistible_id')->nullable();
            $table->date('fecha');
            $table->time('hora');
            $table->timestamps();
        });

        // Insertar datos existentes estilo v1.4.7
        DB::table('registros_asistencia')->insert([
            'asistible_type' => 'App\\Models\\Tutor',
            'asistible_id' => '10',
            'fecha' => '2026-11-07',
            'hora' => '08:00:00',
        ]);

        $this->assertTrue(Schema::hasTable('registros_asistencia'));
        $this->assertFalse(Schema::hasColumn('registros_asistencia', 'evento_id'));

        // 2. Ejecutar up() sobre base de datos existente v1.4.7
        $migration->up();

        // Verificar que la columna fue agregada y los datos existentes persisten
        $this->assertTrue(Schema::hasColumn('registros_asistencia', 'evento_id'));
        $registro = DB::table('registros_asistencia')->where('asistible_id', '10')->first();
        $this->assertNotNull($registro);
        $this->assertNull($registro->evento_id);

        // 3. Simular idempotencia (re-ejecutar sin duplicar o fallar)
        $migration->up();
        $this->assertTrue(Schema::hasColumn('registros_asistencia', 'evento_id'));

        // 4. Probar reversión segura (down)
        $migration->down();
        $this->assertFalse(Schema::hasColumn('registros_asistencia', 'evento_id'));

        // 5. Probar re-ejecución tras rollback (nueva instalación limpia)
        $migration->up();
        $this->assertTrue(Schema::hasColumn('registros_asistencia', 'evento_id'));

        // Limpieza de tablas temporales de la prueba
        Schema::dropIfExists('registros_asistencia');
        Schema::dropIfExists('eventos');
    }
}
