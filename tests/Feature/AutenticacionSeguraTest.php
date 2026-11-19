<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutenticacionSeguraTest extends TestCase
{
    public static function rolesInstitucionales(): array
    {
        return [
            'director' => ['Director', true],
            'directivo' => ['Directivo', true],
            'administrador' => ['Administrador', false],
            'admin' => ['Admin', false],
            'normalizado' => ['  dIrEcTiVo  ', true],
            'docente' => ['Docente', false],
            'personal' => ['Personal', false],
            'secretaria' => ['Secretaria', false],
            'secretario' => [' Secretario ', false],
            'operador' => ['Operador', false],
            'desconocido' => ['Desconocido', false],
        ];
    }

    #[DataProvider('rolesInstitucionales')]
    public function test_roles_conservan_acceso_escolar_sin_elevar_privilegios(string $rol, bool $administrador): void
    {
        $user = User::create([
            'nombre_completo' => 'Usuario Sintetico',
            'correo' => 'roles@example.test',
            'password' => Hash::make('synthetic-password-123'),
            'tipo_usuario' => $rol,
        ]);

        $this->assertSame($administrador, $user->isAdmin());
        $escolar = in_array($rol, ['Director', 'Directivo', '  dIrEcTiVo  ', 'Secretaria', ' Secretario '], true);
        $this->assertSame($escolar, $user->isPersonal());
        $this->actingAs($user)->get('/dashboard')->assertStatus($escolar ? 200 : 403);
        if ($escolar || $rol === 'Operador') {
            $this->get('/login')->assertRedirect(route($rol === 'Operador' ? 'asistencia.escaner' : 'dashboard'));
        } else {
            $this->get('/login')->assertForbidden();
        }
        $this->get('/register')->assertStatus($administrador ? 200 : 403);

        if (! $administrador) {
            $before = User::count();
            $this->post('/register', [
                'name' => 'Cuenta No Autorizada',
                'email' => 'unauthorized@example.test',
                'password' => 'synthetic-password-123',
                'password_confirmation' => 'synthetic-password-123',
                'tipo_usuario' => 'Director',
            ])->assertForbidden();
            $this->assertSame($before, User::count());
            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_invitado_no_accede_dashboard_ni_crea_directivo(): void
    {
        $before = User::count();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->post('/register', [
            'name' => 'Cuenta Publica Sintetica',
            'email' => 'public@example.test',
            'password' => 'synthetic-password-123',
            'password_confirmation' => 'synthetic-password-123',
            'tipo_usuario' => 'Directivo',
        ])->assertRedirect(route('login'));
        $this->assertSame($before, User::count());
        $this->assertGuest();
    }

    public function test_perfil_no_permite_asignar_roles_administrativos(): void
    {
        $user = User::create([
            'nombre_completo' => 'Docente Sintetico',
            'correo' => 'profile@example.test',
            'password' => Hash::make('synthetic-password-123'),
            'tipo_usuario' => 'Docente',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Docente Sintetico Actualizado',
            'email' => 'profile@example.test',
            'role' => 'Admin',
            'tipo_usuario' => 'Directivo',
        ])->assertForbidden();

        $this->assertSame('Docente', $user->refresh()->tipo_usuario);
        $this->assertSame('Docente Sintetico', $user->nombre_completo);
        $this->assertFalse($user->isAdmin());
    }

    /**
     * Verifica que la pantalla de inicio de sesión se renderiza correctamente.
     */
    public function test_pantalla_login_se_renderiza_correctamente(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    /**
     * Verifica que un usuario con credenciales correctas puede autenticarse.
     */
    public function test_usuario_valido_puede_iniciar_sesion(): void
    {
        $user = User::create([
            'nombre_completo' => 'Usuario Login Test',
            'correo' => 'login_test@cam48.edu.mx',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Secretario',
        ]);

        $response = $this->post('/login', [
            'email' => 'login_test@cam48.edu.mx',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Verifica que el intento con contraseña incorrecta es rechazado.
     */
    public function test_usuario_con_contrasena_incorrecta_es_rechazado(): void
    {
        User::create([
            'nombre_completo' => 'Usuario Password Invalido',
            'correo' => 'bad_pass@cam48.edu.mx',
            'password' => Hash::make('password_correcta'),
            'tipo_usuario' => 'Docente',
        ]);

        $response = $this->post('/login', [
            'email' => 'bad_pass@cam48.edu.mx',
            'password' => 'password_incorrecta',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Verifica que un usuario autenticado puede cerrar sesión de forma segura.
     */
    public function test_usuario_autenticado_puede_cerrar_sesion(): void
    {
        $user = User::create([
            'nombre_completo' => 'Usuario Logout Test',
            'correo' => 'logout_test@cam48.edu.mx',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    /**
     * Verifica que el autoregistro público está estrictamente bloqueado para invitados (OWASP A01).
     */
    public function test_registro_publico_esta_estrictamente_bloqueado_para_invitados(): void
    {
        // Petición GET a /register debe redirigir al login
        $responseGet = $this->get('/register');
        $responseGet->assertRedirect('/login');

        // Petición POST a /register por un usuario no autenticado debe redirigir al login
        $responsePost = $this->post('/register', [
            'name' => 'Atacante Externo',
            'email' => 'hacker@externo.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Administrador',
        ]);
        $responsePost->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Verifica que un usuario autenticado con rol Docente/Personal no tiene permiso para registrar usuarios.
     */
    public function test_usuario_docente_no_puede_acceder_al_formulario_de_registro(): void
    {
        $docente = User::create([
            'nombre_completo' => 'Profesor Sin Permiso Admin',
            'correo' => 'profesor_noadmin@cam48.edu.mx',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        $response = $this->actingAs($docente)->get('/register');
        $response->assertStatus(403);
    }

    /**
     * Verifica que un Administrador autenticado sí puede acceder al formulario de registro institucional.
     */
    public function test_administrador_autorizado_puede_acceder_al_formulario_de_registro(): void
    {
        $admin = User::create([
            'nombre_completo' => 'Director Admin Autorizado',
            'correo' => 'director_autorizado@cam48.edu.mx',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Director',
        ]);

        $response = $this->actingAs($admin)->get('/register');
        $response->assertStatus(200);
    }

    /**
     * Verifica la actualización segura de contraseña desde el perfil con credenciales válidas.
     */
    public function test_actualizacion_de_contrasena_con_credenciales_validas(): void
    {
        $user = User::create([
            'nombre_completo' => 'Usuario Cambio Pass',
            'correo' => 'cambio_pass@cam48.edu.mx',
            'password' => Hash::make('clave_anterior_123'),
            'tipo_usuario' => 'Secretaria',
        ]);

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'clave_anterior_123',
            'password' => 'clave_nueva_segura_456',
            'password_confirmation' => 'clave_nueva_segura_456',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('clave_nueva_segura_456', $user->fresh()->password));
    }

    /**
     * Verifica que la actualización de contraseña es rechazada si la contraseña actual es errónea.
     */
    public function test_actualizacion_de_contrasena_rechazada_con_contrasena_actual_invalida(): void
    {
        $user = User::create([
            'nombre_completo' => 'Usuario Error Pass',
            'correo' => 'error_pass@cam48.edu.mx',
            'password' => Hash::make('clave_real_123'),
            'tipo_usuario' => 'Secretaria',
        ]);

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'clave_falsa_999',
            'password' => 'clave_nueva_intento',
            'password_confirmation' => 'clave_nueva_intento',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('clave_real_123', $user->fresh()->password));
    }

    /**
     * Verifica que el rol histórico "Directivo" conserve sus permisos administrativos.
     */
    public function test_rol_directivo_se_reconoce_como_administrador_y_personal(): void
    {
        $directivo = User::create([
            'nombre_completo' => 'Directivo Compatibilidad',
            'correo' => 'directivo_compatibilidad@cam48.edu.mx',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Directivo',
        ]);

        $this->assertTrue($directivo->isAdmin());
        $this->assertTrue($directivo->isPersonal());
    }

    /**
     * Verifica que un Directivo autenticado pueda abrir el Dashboard y que /login
     * lo redirija correctamente en lugar de terminar en una respuesta 403.
     */
    public function test_directivo_autenticado_puede_abrir_dashboard_y_login_no_produce_403(): void
    {
        $directivo = User::create([
            'nombre_completo' => 'Directivo Dashboard',
            'correo' => 'directivo_dashboard@cam48.edu.mx',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Directivo',
            'email_verified_at' => now(),
        ]);

        $loginResponse = $this->actingAs($directivo)->get('/login');
        $loginResponse->assertRedirect();
        $this->assertSame(
            '/dashboard',
            parse_url((string) $loginResponse->headers->get('Location'), PHP_URL_PATH)
        );

        $this->get('/dashboard')->assertOk();
    }
}
