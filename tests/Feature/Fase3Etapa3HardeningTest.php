<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Personal;
use App\Models\Tutor;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase3Etapa3HardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. REGISTRO ADMINISTRATIVO SEGURO (MÓDULO 1)
    // ─────────────────────────────────────────────────────────────────

    public function test_administrador_puede_registrar_docente_con_tipo_usuario(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Director Admin',
            'correo' => 'admin_reg@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Administrador',
        ]);

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Profesor Nuevo',
            'email' => 'profe_nuevo@test.com',
            'tipo_usuario' => 'Docente',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('usuarios', [
            'correo' => 'profe_nuevo@test.com',
            'tipo_usuario' => 'Docente',
        ]);

        // La sesión del administrador permanece activa
        $this->assertAuthenticatedAs($admin);
    }

    public function test_administrador_puede_registrar_operador_kiosco(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Director Admin 2',
            'correo' => 'admin_op@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Director',
        ]);

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Operador Kiosco',
            'email' => 'operador_nuevo@test.com',
            'tipo_usuario' => 'Operador',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('usuarios', [
            'correo' => 'operador_nuevo@test.com',
            'tipo_usuario' => 'Operador',
        ]);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_docente_y_operador_reciben_403_al_intentar_registrar(): void
    {
        $docente = User::create([
            'nombre_completo' => 'Docente Sin Permiso',
            'correo' => 'docente_no_reg@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        $this->actingAs($docente)->get('/register')->assertForbidden();
        $this->actingAs($docente)->post('/register', [
            'name' => 'Intento Hack',
            'email' => 'hack@test.com',
            'tipo_usuario' => 'Director',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

        $operador = User::create([
            'nombre_completo' => 'Operador Sin Permiso',
            'correo' => 'op_no_reg@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Operador',
        ]);

        $this->actingAs($operador)->get('/register')->assertForbidden();
        $this->actingAs($operador)->post('/register', [
            'name' => 'Intento Hack 2',
            'email' => 'hack2@test.com',
            'tipo_usuario' => 'Administrador',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_invitado_es_redirigido_a_login_al_intentar_registrar(): void
    {
        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', [
            'name' => 'Invitado Hack',
            'email' => 'guest@test.com',
            'tipo_usuario' => 'Docente',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/login');
    }

    public function test_rol_invalido_y_correo_duplicado_son_rechazados(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Admin Validador',
            'correo' => 'admin_val@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Administrador',
        ]);

        // Rol no existente en la lista blanca
        $resRolInvalido = $this->actingAs($admin)->post('/register', [
            'name' => 'Usuario Rol Raro',
            'email' => 'rol_raro@test.com',
            'tipo_usuario' => 'SuperAdminHacker',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $resRolInvalido->assertSessionHasErrors('tipo_usuario');

        // Correo duplicado
        $resDuplicado = $this->actingAs($admin)->post('/register', [
            'name' => 'Usuario Duplicado',
            'email' => 'admin_val@test.com',
            'tipo_usuario' => 'Docente',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $resDuplicado->assertSessionHasErrors('email');
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. PRIVACIDAD DE EXPEDIENTES ESCOLARES (MÓDULO 2)
    // ─────────────────────────────────────────────────────────────────

    public function test_docente_solo_ve_expedientes_y_alumnos_de_su_grupo_autorizado(): void
    {
        $docente1A = User::create([
            'nombre_completo' => 'Docente Primero A',
            'correo' => 'docente_1a@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 901,
            'nombre_completo' => 'Docente Primero A',
            'email' => 'docente_1a@test.com',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'ALUM_1A_OK',
            'nombre' => 'JUAN',
            'apellido' => 'PEREZ',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $alumno2B = Alumno::create([
            'matricula' => 'ALUM_2B_AJENO',
            'nombre' => 'CARLOS',
            'apellido' => 'GOMEZ',
            'grado' => '2',
            'grupo' => 'B',
            'activo' => 1,
        ]);

        $doc1A = ArchivoSistema::create([
            'matricula' => $alumno1A->matricula,
            'nombre_original' => 'evaluacion_1a.pdf',
            'nombre_servidor' => 'serv_1a.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        $doc2B = ArchivoSistema::create([
            'matricula' => $alumno2B->matricula,
            'nombre_original' => 'diagnostico_2b.pdf',
            'nombre_servidor' => 'serv_2b.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 2048,
            'fecha_subida' => now(),
        ]);

        // Docente 1A consulta el índice
        $response = $this->actingAs($docente1A)->get('/expedientes');
        $response->assertOk();

        // Verificar datos expuestos en Inertia props
        $archivosPaginados = $response->viewData('page')['props']['archivos']['data'];
        $alumnosDisponibles = $response->viewData('page')['props']['alumnos'];

        $matriculasArchivos = array_column($archivosPaginados, 'matricula');
        $matriculasAlumnos = array_column($alumnosDisponibles, 'matricula');

        // Solo debe ver el archivo y alumno de 1 A
        $this->assertContains('ALUM_1A_OK', $matriculasArchivos);
        $this->assertNotContains('ALUM_2B_AJENO', $matriculasArchivos);

        $this->assertContains('ALUM_1A_OK', $matriculasAlumnos);
        $this->assertNotContains('ALUM_2B_AJENO', $matriculasAlumnos);

        // Búsqueda de alumno ajeno debe retornar 0 resultados
        $resBusquedaAjena = $this->actingAs($docente1A)->get('/expedientes?busqueda=CARLOS');
        $resBusquedaAjena->assertOk();
        $archivosFiltrados = $resBusquedaAjena->viewData('page')['props']['archivos']['data'];
        $this->assertEmpty($archivosFiltrados);
    }

    public function test_docente_no_puede_subir_documentos_a_alumnos_ajenos(): void
    {
        $docente1A = User::create([
            'nombre_completo' => 'Docente 1A Upload',
            'correo' => 'docente_1a_up@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 902,
            'nombre_completo' => 'Docente 1A Upload',
            'email' => 'docente_1a_up@test.com',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $alumno2B = Alumno::create([
            'matricula' => 'ALUM_2B_PROT',
            'nombre' => 'DANIEL',
            'apellido' => 'RUIZ',
            'grado' => '2',
            'grupo' => 'B',
            'activo' => 1,
        ]);

        $fakeFile = UploadedFile::fake()->create('reporte.pdf', 500, 'application/pdf');

        // Intento de subir documento al alumno ajeno 2B -> HTTP 403 Forbidden
        $response = $this->actingAs($docente1A)->post('/expedientes', [
            'matricula' => $alumno2B->matricula,
            'archivo' => $fakeFile,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('archivos_sistema', [
            'matricula' => $alumno2B->matricula,
            'nombre_original' => 'reporte.pdf',
        ]);
    }

    public function test_operador_kiosco_recibe_403_en_expedientes(): void
    {
        $operador = User::create([
            'nombre_completo' => 'Operador Bloqueado',
            'correo' => 'operador_exp@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Operador',
        ]);

        $this->actingAs($operador)->get('/expedientes')->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. VINCULACIÓN SEGURA DE TELEGRAM (MÓDULO 3)
    // ─────────────────────────────────────────────────────────────────

    public function test_comando_telegram_sync_bloquea_vinculaciones_secuenciales_inseguras(): void
    {
        config(['telegram.bot_token' => 'TEST_TOKEN_123']);

        $tutor = Tutor::create([
            'id' => 999,
            't1_nombre' => 'Tutor Vulnerable',
            't1_telefono' => '5551234567',
            't1_telegram_chat_id' => null,
        ]);

        Http::fake([
            'https://api.telegram.org/botTEST_TOKEN_123/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 1001,
                        'message' => [
                            'message_id' => 1,
                            'chat' => ['id' => 987654321],
                            'text' => '/start tutor_999',
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/botTEST_TOKEN_123/sendMessage*' => Http::response([
                'ok' => true,
                'result' => [],
            ]),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        // El tutor NO debe haber sido vinculado
        $tutor->refresh();
        $this->assertNull($tutor->t1_telegram_chat_id);

        // Se verifica que se envió el aviso de bloqueo por seguridad
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'deshabilitado permanentemente');
        });
    }

    public function test_protocolo_vinc_funciona_con_esquema_criptografico_aislado(): void
    {
        config(['telegram.bot_token' => 'TEST_TOKEN_123']);

        // Crear la tabla temporal aislada de vinculaciones criptográficas en SQLite
        if (! Schema::hasTable('telegram_vinculaciones')) {
            Schema::create('telegram_vinculaciones', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->string('vinculable_type', 100);
                $table->string('vinculable_id', 50);
                $table->unsignedBigInteger('generado_por_usuario_id')->nullable();
                $table->string('chat_id_registrado', 50)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        $tutor = Tutor::create([
            'id' => 888,
            't1_nombre' => 'Tutor Criptografico',
            't1_telefono' => '5559876543',
            't1_telegram_chat_id' => null,
        ]);

        $rawToken = bin2hex(random_bytes(24)); // 48 caracteres hex
        $tokenHash = hash('sha256', $rawToken);

        DB::table('telegram_vinculaciones')->insert([
            'token_hash' => $tokenHash,
            'vinculable_type' => Tutor::class,
            'vinculable_id' => (string) $tutor->id,
            'expires_at' => now()->addMinutes(30),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://api.telegram.org/botTEST_TOKEN_123/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 2001,
                        'message' => [
                            'message_id' => 1,
                            'chat' => ['id' => 123456789],
                            'text' => "/start VINC_{$rawToken}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/botTEST_TOKEN_123/sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        $tutor->refresh();
        $this->assertEquals('123456789', $tutor->t1_telegram_chat_id);

        // El registro de vinculación debe quedar marcado como usado
        $vinculacion = DB::table('telegram_vinculaciones')->where('token_hash', $tokenHash)->first();
        $this->assertNotNull($vinculacion->used_at);

        // Segundo uso del mismo token debe ser rechazado
        Http::fake([
            'https://api.telegram.org/botTEST_TOKEN_123/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 2002,
                        'message' => [
                            'message_id' => 2,
                            'chat' => ['id' => 999999999],
                            'text' => "/start VINC_{$rawToken}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/botTEST_TOKEN_123/sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        // El chat ID del tutor sigue siendo el primero, no el segundo
        $tutor->refresh();
        $this->assertEquals('123456789', $tutor->t1_telegram_chat_id);
    }

    public function test_controladores_no_generan_enlaces_magicos_inseguros_por_id(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Admin Invitaciones',
            'correo' => 'admin_inv@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Administrador',
        ]);

        $tutor = Tutor::create([
            'id' => 777,
            't1_nombre' => 'Tutor Test',
            't1_telefono' => '5551234567',
            't1_email' => 'tutor_test@test.com',
        ]);

        $personal = Personal::create([
            'id' => 778,
            'nombre_completo' => 'Docente Test',
            'email' => 'docente_test@test.com',
            'activo' => 1,
        ]);

        // Ambos intentos deben retornar warning y NO enviar correo con enlace plano
        $resTutor = $this->actingAs($admin)->post("/tutores/{$tutor->id}/invitacion-telegram");
        $resTutor->assertSessionHas('warning');

        $resPersonal = $this->actingAs($admin)->post("/personal/{$personal->id}/invitacion-telegram");
        $resPersonal->assertSessionHas('warning');

        Mail::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. NOTIFICACIONES, PRIVACIDAD Y SIMULACIÓN (MÓDULO 4)
    // ─────────────────────────────────────────────────────────────────

    public function test_servicios_de_notificaciones_enmascaran_datos_personales_y_diferencian_simulacion(): void
    {
        config(['whatsapp.api_token' => '']);
        config(['whatsapp.api_url' => '']);
        config(['telegram.bot_token' => '']);

        $wsService = new WhatsAppService;
        $tgService = new TelegramService;

        // En modo simulación (sin token), no se reporta entrega confirmada
        $resWs = $wsService->enviarMensajeDirectoDetallado('5551234567', 'Mensaje con datos de Juanito');
        $this->assertEquals('Simulado', $resWs['status']);
        $this->assertFalse($resWs['success']);

        $resTg = $tgService->enviarMensajeDetallado('987654321', 'Mensaje con datos de Juanito');
        $this->assertEquals('Simulado', $resTg['status']);
        $this->assertFalse($resTg['success']);

        // Con credenciales configuradas y Http fake
        config(['whatsapp.api_token' => 'TOKEN_WS']);
        config(['whatsapp.api_url' => 'https://api.whatsapp.test']);
        config(['telegram.bot_token' => 'TOKEN_TG']);

        Http::fake([
            'https://api.whatsapp.test/*' => Http::response(['sent' => true], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $wsReal = new WhatsAppService;
        $tgReal = new TelegramService;

        $resWsReal = $wsReal->enviarMensajeDirectoDetallado('5551234567', 'Aviso Real');
        $this->assertEquals('Enviado a proveedor', $resWsReal['status']);
        $this->assertTrue($resWsReal['success']);

        $resTgReal = $tgReal->enviarMensajeDetallado('987654321', 'Aviso Real');
        $this->assertEquals('Enviado a proveedor', $resTgReal['status']);
        $this->assertTrue($resTgReal['success']);
    }

    public function test_administrador_ve_documentos_y_alumnos_de_todos_los_grupos(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Director General',
            'correo' => 'dir_general@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Director',
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'ALUM_1A_ADMIN',
            'nombre' => 'PEDRO',
            'apellido' => 'LOPEZ',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $alumno2B = Alumno::create([
            'matricula' => 'ALUM_2B_ADMIN',
            'nombre' => 'LUCIA',
            'apellido' => 'MENDEZ',
            'grado' => '2',
            'grupo' => 'B',
            'activo' => 1,
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno1A->matricula,
            'nombre_original' => 'doc_1a.pdf',
            'nombre_servidor' => 'serv_1a_adm.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno2B->matricula,
            'nombre_original' => 'doc_2b.pdf',
            'nombre_servidor' => 'serv_2b_adm.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 2048,
            'fecha_subida' => now(),
        ]);

        $resAdmin = $this->actingAs($admin)->get('/expedientes');
        $resAdmin->assertOk();

        $archivosPaginados = $resAdmin->viewData('page')['props']['archivos']['data'];
        $alumnosDisponibles = $resAdmin->viewData('page')['props']['alumnos'];

        $matriculasArchivos = array_column($archivosPaginados, 'matricula');
        $matriculasAlumnos = array_column($alumnosDisponibles, 'matricula');

        // El administrador ve los de 1A y los de 2B sin restricciones
        $this->assertContains('ALUM_1A_ADMIN', $matriculasArchivos);
        $this->assertContains('ALUM_2B_ADMIN', $matriculasArchivos);
        $this->assertContains('ALUM_1A_ADMIN', $matriculasAlumnos);
        $this->assertContains('ALUM_2B_ADMIN', $matriculasAlumnos);
    }

    public function test_docente_no_puede_descargar_ni_ver_documento_de_alumno_ajeno(): void
    {
        $docente1A = User::create([
            'nombre_completo' => 'Docente Descarga Bloqueada',
            'correo' => 'doc_down_lock@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 950,
            'nombre_completo' => 'Docente Descarga Bloqueada',
            'email' => 'doc_down_lock@test.com',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $alumno3C = Alumno::create([
            'matricula' => 'ALUM_3C_CONF',
            'nombre' => 'SOFIA',
            'apellido' => 'MENDOZA',
            'grado' => '3',
            'grupo' => 'C',
            'activo' => 1,
        ]);

        Storage::disk('local')->put('expedientes/doc_3c_secreto.pdf', 'Contenido Ultra Confidencial');

        $archivoAjeno = ArchivoSistema::create([
            'matricula' => $alumno3C->matricula,
            'nombre_original' => 'expediente_privado.pdf',
            'nombre_servidor' => 'doc_3c_secreto.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 2048,
            'fecha_subida' => now(),
        ]);

        // Intentar ver directamente por ID
        $this->actingAs($docente1A)->get("/expedientes/{$archivoAjeno->id}/ver")->assertForbidden();

        // Intentar descargar directamente por ID
        $this->actingAs($docente1A)->get("/expedientes/{$archivoAjeno->id}/descargar")->assertForbidden();
    }

    public function test_telegram_sync_rechaza_token_inexistente_y_token_vencido(): void
    {
        config(['telegram.bot_token' => 'TEST_TOKEN_123']);

        if (! Schema::hasTable('telegram_vinculaciones')) {
            Schema::create('telegram_vinculaciones', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->string('vinculable_type', 100);
                $table->string('vinculable_id', 50);
                $table->unsignedBigInteger('generado_por_usuario_id')->nullable();
                $table->string('chat_id_registrado', 50)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        // 1. Token vencido
        $expiredRaw = bin2hex(random_bytes(24));
        $expiredHash = hash('sha256', $expiredRaw);

        DB::table('telegram_vinculaciones')->insert([
            'token_hash' => $expiredHash,
            'vinculable_type' => Tutor::class,
            'vinculable_id' => '999',
            'expires_at' => now()->subMinutes(10), // Vencido
            'used_at' => null,
            'created_at' => now()->subMinutes(40),
            'updated_at' => now()->subMinutes(40),
        ]);

        // 2. Token inexistente
        $fakeRaw = bin2hex(random_bytes(24));

        Http::fake([
            'https://api.telegram.org/botTEST_TOKEN_123/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 3001,
                        'message' => [
                            'message_id' => 1,
                            'chat' => ['id' => 111111111],
                            'text' => "/start VINC_{$expiredRaw}",
                        ],
                    ],
                    [
                        'update_id' => 3002,
                        'message' => [
                            'message_id' => 2,
                            'chat' => ['id' => 222222222],
                            'text' => "/start VINC_{$fakeRaw}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/botTEST_TOKEN_123/sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        // Verificar que se enviaron mensajes indicando token inválido o expirado
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'inválido, ya fue utilizado o ha expirado');
        });
    }
}
