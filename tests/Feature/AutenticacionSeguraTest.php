<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutenticacionSeguraTest extends TestCase
{
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
            'tipo_usuario' => 'Docente',
        ]);

        $response = $this->post('/login', [
            'email' => 'login_test@cam48.edu.mx',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
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
            'tipo_usuario' => 'Docente',
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
            'tipo_usuario' => 'Docente',
        ]);

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'clave_falsa_999',
            'password' => 'clave_nueva_intento',
            'password_confirmation' => 'clave_nueva_intento',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('clave_real_123', $user->fresh()->password));
    }
}
