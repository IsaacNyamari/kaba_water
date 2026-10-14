<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client sign-in without an email address: phone number + meter number +
 * PIN/password. The phone and meter only identify the account (they are not
 * secret); the PIN is what proves it is the client.
 */
class PhoneLoginController extends Controller
{
    /** Attempts allowed per minute for one phone + meter + IP. */
    protected const MAX_ATTEMPTS = 5;

    public function store(Request $request): Response
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'meter' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ], [], [
            'phone' => 'phone number',
            'meter' => 'meter number',
            'password' => 'PIN or password',
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => trans('auth.throttle', [
                    'seconds' => $seconds = RateLimiter::availableIn($throttleKey),
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        $user = $this->findClient($credentials['phone'], $credentials['meter'], $credentials['password']);

        if (! $user) {
            RateLimiter::hit($throttleKey);
            event(new Failed('web', null, ['phone' => $credentials['phone'], 'meter' => $credentials['meter']]));

            // Same message whatever was wrong, so it reveals nothing about which part matched.
            throw ValidationException::withMessages(['phone' => trans('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);

        if ($this->requiresTwoFactor($user)) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }

    /**
     * The client user whose phone and meter match and whose PIN is correct.
     * Meters are matched by serial (case-insensitive) across all vendors,
     * then narrowed by the client's phone number.
     */
    protected function findClient(string $phone, string $meterNumber, string $password): ?User
    {
        $candidates = Meter::withoutGlobalScope('vendor')
            ->whereRaw('LOWER(serial) = ?', [Str::lower(trim($meterNumber))])
            ->with(['client' => fn ($q) => $q->withoutGlobalScope('vendor')->whereNotNull('user_id')])
            ->get()
            ->pluck('client')
            ->filter(fn ($client) => $client && PhoneNumber::matches($client->phone, $phone))
            ->unique('user_id');

        foreach ($candidates as $client) {
            $user = User::find($client->user_id);

            if ($user && $user->hasRole('client') && Hash::check($password, $user->password)) {
                return $user;
            }
        }

        return null;
    }

    protected function requiresTwoFactor(User $user): bool
    {
        if (! $user->two_factor_secret || ! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return false;
        }

        return ! Fortify::confirmsTwoFactorAuthentication() || $user->two_factor_confirmed_at !== null;
    }

    protected function throttleKey(Request $request): string
    {
        return 'phone-login:'.Str::transliterate(
            PhoneNumber::normalize($request->input('phone')).'|'.Str::lower(trim((string) $request->input('meter'))).'|'.$request->ip()
        );
    }
}
