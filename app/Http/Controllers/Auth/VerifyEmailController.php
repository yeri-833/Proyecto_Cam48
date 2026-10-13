<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            $user = $request->user();

            // Solo dispara el evento si el modelo implementa MustVerifyEmail
            // User no extiende MustVerifyEmail explícitamente, pero Authenticated User
            // puede disparar Verified cuando markEmailAsVerified() devuelve true.
            if ($user instanceof MustVerifyEmail) {
                event(new Verified($user));
            }
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
