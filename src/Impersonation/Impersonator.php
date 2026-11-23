<?php

namespace Soara\Debugger\Impersonation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Users\UserDirectory;

class Impersonator
{
    public const SESSION_KEY = 'debugger.impersonation';

    public function __construct(protected UserDirectory $users, protected AuditLog $audit) {}

    public function enabled(): bool
    {
        return (bool) config('debugger.impersonation.enabled', true);
    }

    public function active(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::SESSION_KEY);
    }

    /**
     * @return array{id: ?string, name: ?string, started_at: int}|null
     */
    public function current(Request $request): ?array
    {
        return $this->active($request) ? (array) $request->session()->get(self::SESSION_KEY) : null;
    }

    /**
     * Why this user can't be impersonated by the current one, or null if they can.
     */
    public function refusal(?Authenticatable $actor, Authenticatable $target): ?string
    {
        if ($actor !== null && (string) $actor->getAuthIdentifier() === (string) $target->getAuthIdentifier()) {
            return "That's you";
        }

        if (Gate::has('beImpersonated') && ! Gate::forUser($actor)->check('beImpersonated', [$target])) {
            return 'Protected';
        }

        return null;
    }

    public function start(Request $request, string $targetId): Authenticatable
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Impersonation is turned off.');
        }

        if ($this->active($request)) {
            throw new RuntimeException('Switch back to your own account before logging in as someone else.');
        }

        $guard = $this->users->guard();
        $actor = $request->user($guard);

        if (Gate::has('impersonate') && ! Gate::forUser($actor)->check('impersonate')) {
            throw new RuntimeException("You aren't allowed to log in as other users.");
        }

        $target = $this->users->find($targetId) ?? throw new RuntimeException("That user doesn't exist any more.");

        if ($reason = $this->refusal($actor, $target)) {
            throw new RuntimeException($reason === 'Protected' ? 'This account is protected and cannot be impersonated.' : "You can't log in as yourself.");
        }

        $actorInfo = $actor ? $this->users->present($actor) : null;
        $targetInfo = $this->users->present($target);

        Auth::guard($guard)->login($target);
        $request->session()->regenerate();
        // Let AuthenticateSession store the new user's password hash instead
        // of logging the session out over the actor's hash.
        $request->session()->forget('password_hash_'.$guard);

        $request->session()->put(self::SESSION_KEY, [
            'id' => $actorInfo['id'] ?? null,
            'name' => $actorInfo['name'] ?? null,
            'target' => $targetInfo['name'],
            'started_at' => time(),
        ]);

        $this->audit->record('impersonation.start', $targetInfo['id'], ['target' => $targetInfo['name'], 'actor' => $actorInfo['name'] ?? null]);

        return $target;
    }

    /**
     * Back to the original account. Returns true when someone is signed in
     * again, false when the original account is gone and we logged out.
     */
    public function stop(Request $request): bool
    {
        $current = $this->current($request) ?? throw new RuntimeException('Nobody is being impersonated.');
        $guard = $this->users->guard();
        $actor = $current['id'] !== null ? $this->users->find((string) $current['id']) : null;
        $target = $request->user($guard);

        $request->session()->forget(self::SESSION_KEY);

        $this->audit->record('impersonation.stop', $target ? (string) $target->getAuthIdentifier() : null, [
            'target' => $current['target'] ?? null,
            'actor' => $current['name'] ?? null,
            'seconds' => max(0, time() - (int) ($current['started_at'] ?? time())),
        ]);

        if ($actor === null) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return false;
        }

        Auth::guard($guard)->login($actor);
        $request->session()->regenerate();
        $request->session()->forget('password_hash_'.$guard);

        return true;
    }
}
