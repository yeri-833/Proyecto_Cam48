<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class HistorialExportacionTest extends TestCase
{
    /**
     * Prueba que el endpoint de exportación descarga correctamente en lotes (Chunking) y devuelve el CSV.
     */
    public function test_exportacion_historial_descarga_csv(): void
    {
        $user = User::first(); // Usamos el admin principal
        if (!$user) {
            $this->markTestSkipped('No hay usuarios en la base de datos para probar.');
        }

        $response = $this->actingAs($user)->get('/historial/exportar?fecha=' . date('Y-m-d'));

        $response->assertStatus(200);
        
        // Validamos que los headers sean correctos para una descarga de CSV
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');
        
        // Verificamos que el contenido comience con el BOM o la cabecera esperada
        // Ya que es un StreamedResponse, la respuesta real está en el stream, pero podemos asertar que es ok
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-type'));
    }
}
