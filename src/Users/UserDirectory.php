<?php

namespace Soara\Debugger\Users;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;
use Soara\Debugger\Debugger;
use Throwable;

/**
 * Lists users of the app for impersonation. The model comes from the auth
 * provider of the configured guard, so a custom table (app_users) or UUID
 * primary keys need no extra setup; column names come from config.
 */
class UserDirectory
{
    public function guard(): string
    {
        return (string) config('debugger.users.guard', 'web');
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        $model = config('debugger.users.model');

        if (! $model) {
            $provider = config('auth.guards.'.$this->guard().'.provider');
            $model = config("auth.providers.{$provider}.model");
        }

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('No Eloquent user model found for the "'.$this->guard().'" guard. Set debugger.users.model.');
        }

        return $model;
    }

    public function paginate(?string $search, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        $model = $this->model();
        $columns = (array) config('debugger.users.columns', []);
        $query = $model::query();

        if ($scope = Debugger::userQueryScope()) {
            $query = $scope($query) ?? $query;
        }

        $search = trim((string) $search);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                foreach ((array) config('debugger.users.search', ['name', 'email']) as $column) {
                    $builder->orWhere($column, 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');
                }
            });
        }

        $order = $columns['name'] ?? null;

        return $query
            ->when($order, fn (Builder $q) => $q->orderBy($order), fn (Builder $q) => $q->orderBy((new $model)->getKeyName()))
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $id): ?Authenticatable
    {
        $model = $this->model();
        $user = $model::query()->find($id);

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @return array{id: string, name: string, email: ?string, role: ?string, last_active: ?CarbonImmutable}
     */
    public function present(Authenticatable $user): array
    {
        $columns = (array) config('debugger.users.columns', []);
        $role = Debugger::userRoleResolver();
        $lastActive = null;

        if (! empty($columns['last_active']) && $value = data_get($user, $columns['last_active'])) {
            try {
                $lastActive = CarbonImmutable::parse($value);
            } catch (Throwable) {
                //
            }
        }

        return [
            'id' => (string) $user->getAuthIdentifier(),
            'name' => (string) (data_get($user, $columns['name'] ?? 'name') ?? $user->getAuthIdentifier()),
            'email' => ($email = data_get($user, $columns['email'] ?? 'email')) ? (string) $email : null,
            'role' => $role ? (string) $role($user) : null,
            'last_active' => $lastActive,
        ];
    }

    public function showsRole(): bool
    {
        return Debugger::userRoleResolver() !== null;
    }

    public function showsLastActive(): bool
    {
        return ! empty(config('debugger.users.columns.last_active'));
    }
}
