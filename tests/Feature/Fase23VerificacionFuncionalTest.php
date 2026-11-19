<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase23VerificacionFuncionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Evitar envíos externos o encolamientos de red en todas las pruebas
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. REQUISITO 3: VERIFICAR LOGIN Y REDIRECCIÓN SEGÚN EL ROL
    // ─────────────────────────────────────────────────────────────────

    public function test_login_redirecciona_correctamente_segun_el_rol(): void
    {
        // Los perfiles retirados no pueden iniciar sesión ni acceder al dashboard.
        $admin = User::create([
            'nombre_completo' => 'Admin Test',
            'correo' => 'admin_login@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Administrador',
        ]);

        $resAdmin = $this->post('/login', [
            'email' => 'admin_login@test.com',
            'password' => 'password123',
        ]);
        $resAdmin->assertForbidden();
        $this->assertGuest();
        $this->actingAs($admin)->get('/dashboard')->assertForbidden();
        $this->post('/logout');

        // B. Director -> Redirige a /dashboard
        $director = User::create([
            'nombre_completo' => 'Director Test',
            'correo' => 'director_login@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Director',
        ]);

        $resDir = $this->post('/login', [
            'email' => 'director_login@test.com',
            'password' => 'password123',
        ]);
        $resDir->assertRedirect('/dashboard');
        $this->actingAs($director)->get('/dashboard')->assertOk();
        $this->post('/logout');

        $docente = User::create([
            'nombre_completo' => 'Docente Test',
            'correo' => 'docente_login@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        $resDoc = $this->post('/login', [
            'email' => 'docente_login@test.com',
            'password' => 'password123',
        ]);
        $resDoc->assertForbidden();
        $this->assertGuest();
        $this->actingAs($docente)->get('/dashboard')->assertForbidden();
        $this->post('/logout');

        $secretaria = User::factory()->create(['tipo_usuario' => 'Secretaria']);
        $this->post('/login', [
            'email' => $secretaria->correo,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
        $this->actingAs($secretaria)->get('/dashboard')->assertOk();
        $this->post('/logout');

        // D. OPERADOR DE KIOSCO -> REDIRIGE DIRECTAMENTE A /escaner (SIN 403 EN DASHBOARD)
        $operador = User::create([
            'nombre_completo' => 'Operador Kiosco Test',
            'correo' => 'operador_login@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Operador',
        ]);

        $resOp = $this->post('/login', [
            'email' => 'operador_login@test.com',
            'password' => 'password123',
        ]);
        $resOp->assertRedirect('/escaner');

        // Verificar que el operador accede al escáner con 200 OK y está bloqueado de /dashboard con 403
        $this->actingAs($operador)->get('/escaner')->assertOk();
        $this->actingAs($operador)->get('/dashboard')->assertStatus(403);
        $this->post('/logout');

        // E. Invitado no autenticado -> Bloqueado y redirigido a login
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/escaner')->assertRedirect('/login');
    }

    public function test_operador_esta_bloqueado_de_modulos_administrativos(): void
    {
        $operador = User::create([
            'nombre_completo' => 'Operador Restringido',
            'correo' => 'op_restrict@test.com',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Operador',
        ]);

        $this->actingAs($operador);

        $this->get('/dashboard')->assertStatus(403);
        $this->get('/personal')->assertStatus(403);
        $this->get('/tutores')->assertStatus(403);
        $this->get('/historial')->assertStatus(403);
        $this->get('/estadisticas')->assertStatus(403);
        $this->get('/credenciales')->assertStatus(403);
        $this->get('/expedientes')->assertStatus(403);
        $this->get('/register')->assertStatus(403);
        $this->get('/telegram/sync')->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. REQUISITO 4: VERIFICAR REGISTRO ADMINISTRATIVO Y PRESERVACIÓN
    // ─────────────────────────────────────────────────────────────────

    public function test_registro_administrativo_es_estricto_y_preserva_sesion(): void
    {
        // 1. Invitado no puede registrarse
        $resGuest = $this->post('/register', [
            'name' => 'Invitado Intruso',
            'email' => 'hacker@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tipo_usuario' => 'Director',
        ]);
        $resGuest->assertRedirect('/login');
        $this->assertDatabaseMissing('usuarios', ['correo' => 'hacker@test.com']);

        // 2. Docente no puede crear usuarios
        $docente = User::create([
            'nombre_completo' => 'Docente Sin Permiso',
            'correo' => 'doc_reg@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Docente',
        ]);
        $resDoc = $this->actingAs($docente)->post('/register', [
            'name' => 'Nuevo Alumno',
            'email' => 'nuevo@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tipo_usuario' => 'Docente',
        ]);
        $resDoc->assertStatus(403);

        // Director registra un perfil vigente y conserva su sesión.
        $admin = User::create([
            'nombre_completo' => 'Admin Registrador',
            'correo' => 'admin_reg@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Director',
        ]);

        $nuevoCorreo = 'nuevo_profesor_'.time().'@test.com';
        $resAdmin = $this->actingAs($admin)->post('/register', [
            'name' => 'Profesor Nuevo',
            'email' => $nuevoCorreo,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tipo_usuario' => 'Secretaria',
        ]);
        $resAdmin->assertRedirect(route('dashboard'));

        // Comprobar que se guardó correctamente en el esquema 'usuarios'
        $creado = User::where('correo', $nuevoCorreo)->first();
        $this->assertNotNull($creado);
        $this->assertEquals('Profesor Nuevo', $creado->nombre_completo);
        $this->assertEquals($nuevoCorreo, $creado->correo);
        $this->assertEquals('Secretaria', $creado->tipo_usuario);

        // Comprobar que la sesión del administrador PERMANECE ACTIVA (no se sustituyó por el nuevo usuario)
        $this->assertEquals($admin->id, auth()->user()->id);
        $this->assertEquals($admin->correo, auth()->user()->correo);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. REQUISITO 5: VERIFICAR ESCÁNER Y ASISTENCIAS
    // ─────────────────────────────────────────────────────────────────

    public function test_escaner_rechaza_solicitudes_de_usuarios_no_autorizados(): void
    {
        $docente = User::create([
            'nombre_completo' => 'Docente No Escaner',
            'correo' => 'doc_no_scan@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Docente',
        ]);

        // Intentos directos por POST deben retornar 403
        $this->actingAs($docente)
            ->postJson('/escaner/procesar', ['qr_data' => 'dummy'])
            ->assertStatus(403);

        $this->actingAs($docente)
            ->postJson('/escaner/desbloquear', ['password' => 'cualquiera'])
            ->assertStatus(403);
    }

    public function test_procesamiento_qr_con_firmas_cooldown_y_estados(): void
    {
        $operador = User::create([
            'nombre_completo' => 'Operador Escaner QR',
            'correo' => 'op_scan_flow@test.com',
            'password' => Hash::make('kiosco123'),
            'tipo_usuario' => 'Operador',
        ]);

        $alumnoActivo = Alumno::create([
            'matricula' => 'ALUM_TEST_ACT',
            'nombre' => 'CARLOS',
            'apellido' => 'SANTANA',
            'activo' => 1,
        ]);

        $alumnoInactivo = Alumno::create([
            'matricula' => 'ALUM_TEST_INACT',
            'nombre' => 'PEDRO',
            'apellido' => 'INFANTE',
            'activo' => 0,
        ]);

        $this->actingAs($operador);

        // A. QR con firma INVÁLIDA (Falsificación detectada)
        $qrInvalido = [
            'id' => $alumnoActivo->matricula,
            'rol' => 'Alumno',
            'signature' => 'firma_falsa_maliciosa',
        ];
        $resInvalido = $this->postJson('/escaner/procesar', ['qr_data' => $qrInvalido]);
        $resInvalido->assertJson([
            'status' => 'error',
            'msg' => 'Registro no realizado. Revise el código o solicite apoyo.',
        ]);

        // B. Alumno INACTIVO o dado de baja
        $sigInactivo = hash_hmac('sha256', $alumnoInactivo->matricula.'Alumno', config('app.key'));
        $qrInactivo = [
            'id' => $alumnoInactivo->matricula,
            'rol' => 'Alumno',
            'signature' => $sigInactivo,
        ];
        $resInactivo = $this->postJson('/escaner/procesar', ['qr_data' => $qrInactivo]);
        $resInactivo->assertJson([
            'status' => 'error',
            'msg' => 'Registro no realizado. Revise el código o solicite apoyo.',
        ]);

        // C. Identificador inexistente
        $sigInexistente = hash_hmac('sha256', 'NO_EXISTE_999'.'Alumno', config('app.key'));
        $qrInexistente = [
            'id' => 'NO_EXISTE_999',
            'rol' => 'Alumno',
            'signature' => $sigInexistente,
        ];
        $resInexistente = $this->postJson('/escaner/procesar', ['qr_data' => $qrInexistente]);
        $resInexistente->assertJson([
            'status' => 'error',
            'msg' => 'Registro no realizado. Revise el código o solicite apoyo.',
        ]);

        // D. QR con firma VÁLIDA -> Registro exitoso
        $sigValida = hash_hmac('sha256', $alumnoActivo->matricula.'Alumno', config('app.key'));
        $qrValido = [
            'id' => $alumnoActivo->matricula,
            'rol' => 'Alumno',
            'signature' => $sigValida,
        ];
        $resValido = $this->postJson('/escaner/procesar', ['qr_data' => $qrValido]);
        $resValido->assertJson([
            'status' => 'success',
            'msg' => 'Asistencia registrada',
        ]);
        $this->assertEqualsCanonicalizing(['status', 'msg', 'hora'], array_keys($resValido->json()));
        $this->assertDatabaseHas('registros_asistencia', [
            'asistible_type' => Alumno::class,
            'asistible_id' => $alumnoActivo->matricula,
        ]);

        // E. Escaneo repetido inmediato (Cooldown de 5 minutos activo)
        $resRepetido = $this->postJson('/escaner/procesar', ['qr_data' => $qrValido]);
        $resRepetido->assertJson([
            'status' => 'warning',
            'msg' => 'Registro no realizado. Revise el código o solicite apoyo.',
        ]);
        foreach ([$resInvalido, $resInactivo, $resInexistente, $resRepetido] as $respuesta) {
            $this->assertEqualsCanonicalizing(['status', 'msg', 'hora'], array_keys($respuesta->json()));
        }

        // F. Desbloqueo de Kiosco
        $resUnlockErr = $this->postJson('/escaner/desbloquear', ['password' => 'wrong_password']);
        $resUnlockErr->assertJson(['status' => 'error', 'msg' => 'Contraseña incorrecta']);

        $resUnlockOk = $this->postJson('/escaner/desbloquear', ['password' => 'kiosco123']);
        $resUnlockOk->assertJson(['status' => 'success']);
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. REQUISITO 6: VERIFICAR EXPEDIENTES Y POLICY FAIL-SECURE
    // ─────────────────────────────────────────────────────────────────

    public function test_expedientes_policy_cumple_fail_secure(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Director Expedientes',
            'correo' => 'dir_exp@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Director',
        ]);

        $docente1A = User::create([
            'nombre_completo' => 'Docente 1A',
            'correo' => 'doc1a@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Docente',
        ]);
        Personal::create([
            'id' => 'PERS_1A',
            'nombre_completo' => 'Docente 1A',
            'email' => 'doc1a@test.com',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $docente2B = User::create([
            'nombre_completo' => 'Docente 2B',
            'correo' => 'doc2b@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Docente',
        ]);
        Personal::create([
            'id' => 'PERS_2B',
            'nombre_completo' => 'Docente 2B',
            'email' => 'doc2b@test.com',
            'grado_grupo' => '2 B',
            'activo' => 1,
        ]);

        $operador = User::create([
            'nombre_completo' => 'Operador Kiosco',
            'correo' => 'op_exp@test.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'Operador',
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'ALUM_1A_01',
            'nombre' => 'MARIA',
            'apellido' => 'LOPEZ',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        // Crear archivo físico seguro en disco local
        $nombreServidor = 'doc_test_'.uniqid().'.pdf';
        Storage::disk('local')->put('expedientes/'.$nombreServidor, 'Contenido Confidencial');

        $archivo = ArchivoSistema::create([
            'matricula' => $alumno1A->matricula,
            'nombre_original' => 'expediente_medico.pdf',
            'nombre_servidor' => $nombreServidor,
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 100,
            'fecha_subida' => Carbon::now(),
        ]);

        // A. Administrador -> Acceso permitido a ver y descargar
        $this->actingAs($admin)->get("/expedientes/{$archivo->id}/ver")->assertOk();
        $this->actingAs($admin)->get("/expedientes/{$archivo->id}/descargar")->assertOk();

        // La vinculación con el grupo no habilita un perfil retirado.
        $this->actingAs($docente1A)->get("/expedientes/{$archivo->id}/ver")->assertForbidden();
        $this->actingAs($docente1A)->get("/expedientes/{$archivo->id}/descargar")->assertForbidden();

        // C. Docente de otro grado y grupo (2 B) -> 403 Forbidden
        $this->actingAs($docente2B)->get("/expedientes/{$archivo->id}/ver")->assertStatus(403);
        $this->actingAs($docente2B)->get("/expedientes/{$archivo->id}/descargar")->assertStatus(403);

        // D. Operador de Kiosco -> 403 Forbidden
        $this->actingAs($operador)->get("/expedientes/{$archivo->id}/ver")->assertStatus(403);
        $this->actingAs($operador)->get("/expedientes/{$archivo->id}/descargar")->assertStatus(403);

        // E. Usuario Invitado -> Redirige a /login
        auth()->logout();
        $this->get("/expedientes/{$archivo->id}/ver")->assertRedirect('/login');
        $this->get("/expedientes/{$archivo->id}/descargar")->assertRedirect('/login');

        // F. Documento inexistente -> 404 Not Found
        $this->actingAs($admin)->get('/expedientes/999999/ver')->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────
    // 5. REQUISITO 7: VERIFICAR MATRIZ GLOBAL DE PERMISOS
    // ─────────────────────────────────────────────────────────────────

    public function test_matriz_global_de_permisos_por_rol(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Super Admin',
            'correo' => 'superadmin@test.com',
            'password' => Hash::make('pass'),
            'tipo_usuario' => 'Director',
        ]);

        $secretaria = User::create([
            'nombre_completo' => 'Secretaria Escolar',
            'correo' => 'profe@test.com',
            'password' => Hash::make('pass'),
            'tipo_usuario' => 'Secretaria',
        ]);
        $docente = User::factory()->create(['tipo_usuario' => 'Docente']);

        // Telegram Sync requiere Director.
        $this->actingAs($secretaria)->get('/telegram/sync')->assertStatus(403);
        $this->actingAs($docente)->get('/telegram/sync')->assertForbidden();
        $this->actingAs($admin)->get('/telegram/sync')->assertOk();

        // B. Estadísticas y Credenciales
        $this->actingAs($secretaria)->get('/estadisticas')->assertOk();
        $this->actingAs($secretaria)->get('/credenciales')->assertOk();

        // C. Historial
        $this->actingAs($secretaria)->get('/historial')->assertOk();
        $this->actingAs($secretaria)->get('/historial/exportar')->assertOk();

        // Director conserva el permiso, pero la eliminación espera auditoría durable.
        $asistencia = RegistroAsistencia::create([
            'asistible_type' => Alumno::class,
            'asistible_id' => 'TEST_ASIST_DEL',
            'fecha' => Carbon::today()->toDateString(),
            'hora' => Carbon::now()->toTimeString(),
        ]);
        $antes = $asistencia->refresh()->getAttributes();
        $this->actingAs($secretaria)->delete("/historial/{$asistencia->id}")->assertStatus(403);
        $this->actingAs($docente)->delete("/historial/{$asistencia->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/historial/{$asistencia->id}")->assertStatus(409);
        $this->assertDatabaseHas('registros_asistencia', ['id' => $asistencia->id]);
        $this->assertSame($antes, $asistencia->refresh()->getAttributes());

        // La gestión de personal requiere Director.
        $personal = Personal::create([
            'id' => 'P_TEST_PERM',
            'nombre_completo' => 'Personal Prueba',
            'activo' => 1,
        ]);
        $this->actingAs($secretaria)->post('/personal', ['id' => 'P_NEW', 'nombre_completo' => 'X'])->assertStatus(403);
        $this->actingAs($secretaria)->put("/personal/{$personal->id}", ['nombre_completo' => 'X'])->assertStatus(403);
        $this->actingAs($secretaria)->delete("/personal/{$personal->id}")->assertStatus(403);
        $this->actingAs($docente)->post('/personal', ['id' => 'P_NEW', 'nombre_completo' => 'X'])->assertForbidden();
        $this->actingAs($docente)->put("/personal/{$personal->id}", ['nombre_completo' => 'X'])->assertForbidden();
        $this->actingAs($docente)->delete("/personal/{$personal->id}")->assertForbidden();

        $this->actingAs($secretaria)->get('/personal')->assertForbidden();
        $this->actingAs($secretaria)->get('/expedientes')->assertForbidden();
        $this->actingAs($secretaria)->get('/register')->assertForbidden();

        $this->actingAs($secretaria)->get('/tutores')->assertOk();
        $alumno = Alumno::create([
            'matricula' => 'ALUM_TUTOR_01',
            'nombre' => 'PEDRITO',
            'apellido' => 'LOPEZ',
            'activo' => 1,
        ]);
        $resTutor = $this->actingAs($secretaria)->post('/tutores', [
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'TUTOR DE PEDRITO',
            't1_telefono' => '5551234567',
        ]);
        $resTutor->assertRedirect(route('tutores.index'));
        $this->assertDatabaseHas('tutores', ['matricula_alumno' => $alumno->matricula]);
    }
}
