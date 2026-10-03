<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SocialProviders;
use App\Models\SocialIdentity;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider): Response
    {
        return $this->begin($request, $provider, 'login');
    }

    public function connect(Request $request, string $provider): Response
    {
        return $this->begin($request, $provider, 'link');
    }

    public function confirm(Request $request, string $provider): Response
    {
        abort_unless(SocialIdentity::query()->where('user_id', $request->user()->id)->where('provider', $provider)->exists(), 404);

        return $this->begin($request, $provider, 'confirm');
    }

    private function driver(string $provider): AbstractProvider
    {
        $driver = Socialite::driver($provider);
        assert($driver instanceof AbstractProvider);
        $driver->setHttpClient(new Client(['connect_timeout' => 5, 'timeout' => 15]));

        return $driver;
    }

    private function callbackOrigin(string $provider): ?string
    {
        $callback = parse_url((string) config("services.$provider.redirect"));
        if (! is_array($callback) || ! isset($callback['scheme'], $callback['host'])) {
            return null;
        }
        $port = $callback['port'] ?? null;
        $default = $port === null || ($callback['scheme'] === 'http' && $port === 80) || ($callback['scheme'] === 'https' && $port === 443);

        return $callback['scheme'].'://'.$callback['host'].($default ? '' : ':'.$port);
    }

    private function begin(Request $request, string $provider, string $purpose): Response
    {
        abort_unless(in_array($provider, SocialProviders::NAMES, true), 404);
        if (! SocialProviders::enabled($provider)) {
            return back()->withErrors(['social' => __('This sign-in provider is not available yet.')]);
        }
        // The provider returns to the registered callback address, whose session cookie is separate from any other local address.
        $origin = $this->callbackOrigin($provider);
        if ($origin !== null && $origin !== $request->getSchemeAndHttpHost() && ! $request->boolean('canonical')) {
            if ($purpose !== 'login') {
                return back()->withErrors(['social' => __('Open Elancer at :address to use this sign-in provider.', ['address' => $origin])]);
            }
            $target = $origin.$request->getBaseUrl().$request->getPathInfo().'?canonical=1';

            return $request->header('X-Inertia') ? Inertia::location($target) : redirect()->away($target);
        }
        $request->session()->forget(['oauth.pending_signup', 'oauth.flow']);
        if ($purpose === 'login') {
            $request->session()->forget(['login.id', 'login.remember', 'admin.two_factor_proof']);
        }
        $request->session()->put('oauth.flow', ['provider' => $provider, 'purpose' => $purpose, 'user_id' => $request->user()?->id, 'started_at' => time()]);
        $driver = $this->driver($provider);
        if ($provider === 'google') {
            $driver->with(['prompt' => 'select_account']);
        }
        $redirect = $driver->redirect();

        return $request->header('X-Inertia') ? Inertia::location($redirect->getTargetUrl()) : $redirect;
    }

    public function callback(Request $request, string $provider): Response
    {
        abort_unless(in_array($provider, SocialProviders::NAMES, true), 404);
        $flow = $request->session()->pull('oauth.flow');
        // Only a pending link or confirmation belongs in Security; any other returning callback leaves a signed-in member in the workspace.
        $pending = is_array($flow) && in_array($flow['purpose'] ?? null, ['link', 'confirm'], true);
        $destination = $request->user() ? ($pending ? 'security.edit' : 'dashboard') : 'login';
        if (! is_array($flow) || ($flow['provider'] ?? null) !== $provider || ($flow['started_at'] ?? 0) < time() - 600 || ($flow['user_id'] ?? null) !== $request->user()?->id || ! SocialProviders::enabled($provider)) {
            $request->session()->forget(['state', 'code_verifier']);

            return to_route($destination)->withErrors(['social' => __('The sign-in request expired. Please try again.')]);
        }
        if ($request->has('error')) {
            $request->session()->forget(['state', 'code_verifier']);

            return to_route($destination)->withErrors(['social' => __('Provider sign-in was cancelled. You can try again.')]);
        }
        try {
            $remote = $this->driver($provider)->user();
        } catch (Throwable) {
            return to_route($destination)->withErrors(['social' => __('Provider sign-in failed. Please try again.')]);
        }
        assert($remote instanceof \Laravel\Socialite\Two\User);
        $subject = (string) $remote->getId();
        if ($subject === '' || strlen($subject) > 191) {
            return to_route($destination)->withErrors(['social' => __('Provider sign-in failed. Please try again.')]);
        }
        $identity = SocialIdentity::query()->where('provider', $provider)->where('subject', $subject)->first();
        if ($flow['purpose'] === 'confirm') {
            if (! $identity || $identity->user_id !== $request->user()->id) {
                return to_route('password.confirm')->withErrors(['social' => __('Use the provider account already connected to this Elancer account.')]);
            }
            $request->session()->passwordConfirmed();

            return redirect()->intended(route('security.edit'));
        }
        if ($flow['purpose'] === 'link') {
            if (time() - (int) $request->session()->get('auth.password_confirmed_at', 0) > config('auth.password_timeout', 10800)) {
                return to_route('password.confirm');
            }
            try {
                DB::transaction(function () use ($request, $provider, $subject): void {
                    User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                    $identity = SocialIdentity::query()->where('provider', $provider)->where('subject', $subject)->first();
                    if ($identity && $identity->user_id === $request->user()->id) {
                        return;
                    }
                    if ($identity || SocialIdentity::query()->where('user_id', $request->user()->id)->where('provider', $provider)->exists()) {
                        throw ValidationException::withMessages(['social' => __('This provider account is already connected. Disconnect it first if you want to change it.')]);
                    }
                    (new SocialIdentity)->forceFill(['user_id' => $request->user()->id, 'provider' => $provider, 'subject' => $subject])->save();
                }, 3);
            } catch (ValidationException|UniqueConstraintViolationException) {
                return to_route('security.edit')->withErrors(['social' => __('This provider account is already connected. Disconnect it first if you want to change it.')]);
            }

            return to_route('security.edit')->with('status', __('Provider connected.'));
        }
        if ($identity) {
            return $this->login($request, $identity->user);
        }
        $email = is_string($remote->getEmail()) ? Str::lower(trim($remote->getEmail())) : null;
        $raw = $remote->getRaw();
        $verified = $provider === 'github' ? $email !== null : (($raw['email_verified'] ?? false) === true && (str_ends_with($email ?? '', '@gmail.com') || ! empty($raw['hd'])));
        $name = Str::limit(trim((string) ($remote->getName() ?: $remote->getNickname())), 255, '');
        $signup = ['provider' => $provider, 'subject' => $subject, 'name' => $name, 'started_at' => time()];
        if (! $verified || ! $email || Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $request->session()->put('oauth.pending_signup', $signup);

            return to_route('social.complete');
        }

        return $this->create($request, $signup, $name ?: __('New member'), $email, true);
    }

    public function complete(Request $request): \Inertia\Response
    {
        $signup = $this->pending($request);

        return Inertia::render('auth/social-complete', ['name' => $signup['name']]);
    }

    public function store(Request $request): Response
    {
        $signup = $this->pending($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255']]);

        return $this->create($request, $signup, $data['name'], Str::lower(trim($data['email'])), false);
    }

    /** @return array{provider:string, subject:string, name:string, started_at:int} */
    private function pending(Request $request): array
    {
        $pending = $request->session()->get('oauth.pending_signup');
        if (! is_array($pending) || ! is_string($pending['provider'] ?? null) || ! in_array($pending['provider'], SocialProviders::NAMES, true) || ! is_string($pending['subject'] ?? null) || ! is_string($pending['name'] ?? null) || ! is_int($pending['started_at'] ?? null) || $pending['started_at'] < time() - 600) {
            abort(419);
        }

        return ['provider' => $pending['provider'], 'subject' => $pending['subject'], 'name' => $pending['name'], 'started_at' => $pending['started_at']];
    }

    /** @param array{provider:string, subject:string, name:string, started_at:int} $signup */
    private function create(Request $request, array $signup, string $name, string $email, bool $verified): Response
    {
        try {
            $user = DB::transaction(function () use ($signup, $name, $email, $verified): User {
                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw ValidationException::withMessages(['social' => __('An account already uses this email. Sign in to that account, then connect this provider in Security settings.')]);
                }
                $user = new User(['name' => $name, 'email' => $email, 'password' => null]);
                $user->forceFill(['locale' => app()->getLocale(), 'email_verified_at' => $verified ? now() : null])->save();
                (new SocialIdentity)->forceFill(['user_id' => $user->id, 'provider' => $signup['provider'], 'subject' => $signup['subject']])->save();

                return $user;
            }, 3);
        } catch (ValidationException|UniqueConstraintViolationException) {
            $request->session()->forget('oauth.pending_signup');

            return to_route('login')->withErrors(['social' => __('An account already uses this email or provider. Sign in to that account, then connect this provider in Security settings.')]);
        }
        $request->session()->forget('oauth.pending_signup');
        event(new Registered($user));

        return $this->login($request, $user);
    }

    private function login(Request $request, User $user): Response
    {
        // Provider sign-in always opens the workspace; a stored destination could demand a second provider confirmation.
        $request->session()->forget(['admin.two_factor_proof', 'auth.password_confirmed_at', 'url.intended']);
        $request->session()->regenerate();
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);

            return to_route('two-factor.login');
        }
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    public function disconnect(Request $request, string $provider): Response
    {
        abort_unless(in_array($provider, SocialProviders::NAMES, true), 404);
        DB::transaction(function () use ($request, $provider): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(SocialIdentity::query()->where('user_id', $user->id)->where('provider', $provider)->exists(), 404);
            if (! SocialProviders::hasOtherMethod($user, $provider)) {
                throw ValidationException::withMessages(['social' => __('Keep at least one usable sign-in method. Add a password, passkey or another provider first.')]);
            }
            SocialIdentity::query()->where('user_id', $user->id)->where('provider', $provider)->delete();
        }, 3);

        return to_route('security.edit')->with('status', __('Provider disconnected.'));
    }
}
