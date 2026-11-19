<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthorizedProfile
{
    public static function redirectAuthenticated(Request $request): string
    {
        $user = $request->user();
        abort_unless($user && $user->canonicalRole() !== null, 403);

        return $user->isOperador() ? route('asistencia.escaner') : route('dashboard');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $request->routeIs('logout')) {
            abort_unless($user->canonicalRole() !== null, 403);
            if ($user->isOperador()) {
                abort_unless($request->routeIs(
                    'login', 'asistencia.escaner', 'asistencia.procesarQr', 'asistencia.desbloquear'
                ) || $request->path() === '/', 403);
            }
        }

        $response = $next($request);
        if ($user?->isOperador() && $request->routeIs('asistencia.procesarQr') && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if ($response->getStatusCode() === 422) {
                $errors = $data['errors'] ?? [];
                $response->setData([
                    'message' => 'Los datos del registro no son válidos.',
                    'errors' => is_array($errors) && array_key_exists('evento_id', $errors)
                        ? ['evento_id' => ['El evento seleccionado no es válido o no está disponible.']]
                        : (object) [],
                ]);

                return $response;
            }

            $response->setData([
                'status' => $data['status'] ?? 'error',
                'msg' => match ($data['status'] ?? '') {
                    'success' => 'Asistencia registrada',
                    default => 'Registro no realizado. Revise el código o solicite apoyo.',
                },
                'hora' => $data['hora'] ?? null,
            ]);
        }

        return $response;
    }
}
