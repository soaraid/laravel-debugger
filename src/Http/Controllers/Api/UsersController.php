<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Impersonation\Impersonator;
use Soara\Debugger\Support\Format;
use Soara\Debugger\Users\UserDirectory;
use Throwable;

class UsersController extends Controller
{
    public function __construct(protected UserDirectory $users, protected Impersonator $impersonator) {}

    public function index(Request $request, AuditLog $audit): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $page = max(1, $request->integer('page', 1));
        $rows = [];
        $error = null;
        $pagination = $this->pages($page, 0, 25, false);

        try {
            $paginator = $this->users->paginate($search, $page);
            $actor = $request->user($this->users->guard());

            foreach ($paginator->items() as $user) {
                $info = $this->users->present($user);
                $rows[] = [
                    ...$info,
                    'last_active' => $info['last_active'] ? Format::ago($info['last_active']) : null,
                    'refusal' => $this->impersonator->refusal($actor, $user),
                ];
            }

            $pagination = $this->pages($page, count($rows), 25, $paginator->hasMorePages(), $paginator->total());
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return response()->json([
            'users' => $rows,
            'error' => $error,
            'enabled' => $this->impersonator->enabled(),
            'show_role' => $this->users->showsRole(),
            'show_last_active' => $this->users->showsLastActive(),
            'sessions' => array_map(function (object $entry): array {
                $meta = json_decode((string) $entry->meta, true) ?: [];
                $actor = $meta['actor'] ?? 'Someone';
                $target = $meta['target'] ?? 'a user';

                return [
                    'id' => $entry->id,
                    'time' => Format::time(CarbonImmutable::parse($entry->created_at)),
                    'text' => $entry->action === 'impersonation.start'
                        ? "{$actor} logged in as {$target}"
                        : "{$actor} switched back from {$target}",
                    'after' => $entry->action === 'impersonation.stop'
                        ? 'after '.CarbonInterval::seconds((int) ($meta['seconds'] ?? 0))->cascade()->forHumans(['short' => true, 'parts' => 2])
                        : null,
                ];
            }, $audit->latest(['impersonation.start', 'impersonation.stop'], 10)),
            'pagination' => $pagination,
        ]);
    }

    public function impersonate(Request $request, string $id): JsonResponse
    {
        try {
            $this->impersonator->start($request, $id);
        } catch (Throwable $e) {
            return $this->notice($e->getMessage(), 'danger');
        }

        // A full page load into the app, which now runs as the other user.
        return response()->json(['redirect' => url((string) config('debugger.impersonation.redirect', '/'))]);
    }
}
