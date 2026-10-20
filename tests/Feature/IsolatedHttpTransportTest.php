<?php

namespace Tests\Feature;

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class IsolatedHttpTransportTest extends TestCase
{
    public function test_las_respuestas_http_simuladas_siguen_disponibles(): void
    {
        Http::fake([
            'https://synthetic.invalid/allowed' => Http::response(['status' => 'synthetic'], 202),
        ]);

        $response = Http::get('https://synthetic.invalid/allowed');

        $this->assertSame(202, $response->status());
        $this->assertSame(['status' => 'synthetic'], $response->json());
    }

    public function test_una_solicitud_sin_simulacion_es_rechazada(): void
    {
        Http::fake([
            'https://synthetic.invalid/allowed' => Http::response(['status' => 'synthetic']),
        ]);

        $this->expectException(StrayRequestException::class);
        $this->expectExceptionMessage('https://synthetic.invalid/unexpected');

        Http::get('https://synthetic.invalid/unexpected');
    }

    public function test_el_transporte_bloquea_solicitudes_aunque_se_permitan_solicitudes_no_simuladas(): void
    {
        Http::allowStrayRequests();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ISOLATION_HTTP_TRANSPORT_FORBIDDEN');

        Http::get('https://synthetic.invalid/forbidden');
    }
}
