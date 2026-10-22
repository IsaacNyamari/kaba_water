<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign-in for the HS Water Billing Android app.
 *
 * The app has its own native login screen, then shows the normal web app
 * inside a WebView. login() checks the same credentials as the website
 * (staff: email + password; customers: phone + meter + PIN) and returns a
 * single-use handoff token. The WebView opens session() with it, which
 * starts an ordinary web session — so everything after sign-in, including
 * two-factor challenges, behaves exactly like the website.
 */
class MobileLoginController extends Controller
{
    protected const MAX_ATTEMPTS = 5;

    protected const TOKEN_TTL_SECONDS = 120;

    public function login(Request $request, PhoneLoginController $phoneLogin): JsonResponse
    {
        $mode = $request->input('mode') === 'phone' ? 'phone' : 'email';

        $rules = $mode === 'phone'
            ? ['phone' => ['required', 'string', 'max:30'], 'meter' => ['required', 'string', 'max:50'], 'password' => ['required', 'string', 'max:255']]
            : ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']];

        $validator = validator($request->all(), $rules, [], ['meter' => 'meter number', 'password' => $mode === 'phone' ? 'PIN or password' : 'password']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $throttleKey = 'mobile-login:'.Str::transliterate(Str::lower((string) ($mode === 'phone'
            ? $request->input('phone').'|'.$request->input('meter')
            : $request->input('email'))).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json(['message' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)])], 429);
        }

        $user = $mode === 'phone'
            ? $phoneLogin->findClient((string) $request->input('phone'), (string) $request->input('meter'), (string) $request->input('password'))
            : $this->findByEmail((string) $request->input('email'), (string) $request->input('password'));

        if (! $user) {
            RateLimiter::hit($throttleKey);
            event(new Failed('web', null, $mode === 'phone' ? $request->only('phone', 'meter') : $request->only('email')));

            return response()->json(['message' => trans('auth.failed')], 422);
        }

        RateLimiter::clear($throttleKey);

        $token = Str::random(64);
        Cache::put('mobile-handoff:'.hash('sha256', $token), [
            'user_id' => $user->getKey(),
            'two_factor' => $phoneLogin->requiresTwoFactor($user),
        ], self::TOKEN_TTL_SECONDS);

        return response()->json([
            'name' => $user->name,
            'session_url' => route('mobile.session', $token),
        ]);
    }

    /** Opened inside the app's WebView: turns the handoff token into a normal, remembered web session. */
    public function session(Request $request, string $token): RedirectResponse
    {
        $handoff = Cache::pull('mobile-handoff:'.hash('sha256', $token));
        $user = $handoff ? User::find($handoff['user_id']) : null;

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in link has expired. Please sign in again.']);
        }

        if ($handoff['two_factor']) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->to($user->homeRoute());
    }

    protected function findByEmail(string $email, string $password): ?User
    {
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->first();

        return $user && Hash::check($password, $user->password) ? $user : null;
    }
}
