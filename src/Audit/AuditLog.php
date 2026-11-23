<?php

namespace Soara\Debugger\Audit;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Who did what in the debugger. Writing never breaks the action itself:
 * if the table is missing (migrations not run yet), the entry is skipped.
 */
class AuditLog
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(string $action, ?string $target = null, array $meta = []): void
    {
        $user = Auth::user();

        try {
            $this->connection()->table($this->table())->insert([
                'action' => $action,
                'target' => $target,
                'user_id' => $user?->getAuthIdentifier() !== null ? (string) $user->getAuthIdentifier() : null,
                'user_name' => $user ? (string) ($user->name ?? $user->email ?? $user->getAuthIdentifier()) : null,
                'meta' => $meta === [] ? null : json_encode($meta),
                'ip' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<string>  $actions
     * @return list<object>
     */
    public function latest(array $actions = [], int $limit = 20): array
    {
        try {
            return $this->connection()->table($this->table())
                ->when($actions !== [], fn ($query) => $query->whereIn('action', $actions))
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function connection(): Connection
    {
        return DB::connection(config('debugger.database.connection'));
    }

    protected function table(): string
    {
        return (string) config('debugger.database.tables.audit_logs', 'debugger_audit_logs');
    }
}
