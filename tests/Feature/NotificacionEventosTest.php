<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tutor;
use App\Jobs\NotificarEventoTutorJob;

class NotificacionEventosTest extends TestCase
{
    /**
     * Prueba que al crear un evento escolar, los mensajes masivos se envían a la cola (Background Jobs).
     */
    public function test_creacion_evento_despacha_notificaciones_a_cola(): void
    {
        // Engañamos al sistema para que no envíe nada de verdad y solo capture los Jobs
        Queue::fake();

        $user = User::first() ?? User::factory()->create();

        // Ejecutar petición para crear un evento
        $response = $this->actingAs($user)->postJson('/eventos', [
            'nombre_evento' => 'Test Automatizado',
            'fecha_hora' => date('Y-m-d\T10:00'),
            'ubicacion' => 'Auditorio',
            'descripcion' => 'Evento de prueba',
            'avisar_whatsapp' => true // Esto desencadena el Job
        ]);

        $response->assertStatus(302);

        // Contar cuántos tutores hay en BD
        $totalTutores = Tutor::count();

        if ($totalTutores > 0) {
            // Verificar que se despachó el Job NotificarEventoTutorJob exactamente N veces
            Queue::assertPushed(NotificarEventoTutorJob::class, $totalTutores);
        } else {
            Queue::assertNothingPushed();
        }
    }
}
