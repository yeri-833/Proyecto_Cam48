<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventoController extends Controller
{
    /**
     * Muestra el calendario / agenda de eventos
     */
    public function index()
    {
        Gate::authorize('eventos.consultar');

        // Traemos los eventos desde hoy en adelante
        $eventos = Evento::whereDate('fecha_hora', '>=', Carbon::today())
            ->orderBy('fecha_hora', 'asc')
            ->get()
            ->map(function ($evento) {
                $fecha = Carbon::parse($evento->fecha_hora);
                $evento->fecha_formateada = $fecha->translatedFormat('d M Y');
                $evento->hora_formateada = $fecha->format('h:i A');
                $evento->es_hoy = $fecha->isToday();

                return $evento;
            });

        return Inertia::render('Agenda/Index', [
            'eventos' => $eventos,
        ]);
    }

    /**
     * Guarda o actualiza un evento
     */
    public function store(Request $request)
    {
        Gate::authorize('eventos.crear');

        $validated = $request->validate([
            'id' => 'nullable|exists:eventos,id',
            'nombre_evento' => 'required|string|max:255',
            'fecha_hora' => 'required|date',
            'ubicacion' => 'nullable|string|max:255',
        ]);

        if (isset($validated['id']) && $validated['id']) {
            $evento = Evento::findOrFail($validated['id']);
            $evento->update([
                'nombre_evento' => $validated['nombre_evento'],
                'fecha_hora' => $validated['fecha_hora'],
                'ubicacion' => $validated['ubicacion'] ?? '',
            ]);
            $msg = 'Evento actualizado correctamente.';
        } else {
            Evento::create([
                'nombre_evento' => $validated['nombre_evento'],
                'fecha_hora' => $validated['fecha_hora'],
                'ubicacion' => $validated['ubicacion'] ?? '',
            ]);

            // =========== ALERTAS AUTOMÁTICAS (WHATSAPP + TELEGRAM) ===========
            $fechaFormateada = Carbon::parse($validated['fecha_hora'])->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A');
            $ubicacionFinal = $validated['ubicacion'] ?? 'Por confirmar';

            // 1. Envíos por WhatsApp (y Email masivos que integrados anteriormente)
            $wsService = new WhatsAppService;
            $wsService->enviarAvisoEvento(
                $validated['nombre_evento'],
                $fechaFormateada,
                $ubicacionFinal
            );

            // 2. Envíos masivos por Telegram
            $tgService = new TelegramService;
            $tgService->enviarAvisoEvento(
                $validated['nombre_evento'],
                $fechaFormateada,
                $ubicacionFinal
            );
            // ==========================================

            $msg = 'Evento agendado y notificado a los padres.';
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Elimina un evento
     */
    public function destroy($id)
    {
        Gate::authorize('eventos.eliminar');

        $evento = Evento::findOrFail($id);
        $evento->delete();

        return redirect()->back()->with('success', 'Evento cancelado correctamente.');
    }
}
