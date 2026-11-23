<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Cache\CacheActions;
use Soara\Debugger\Http\Controllers\Controller;
use Throwable;

class CacheController extends Controller
{
    public function __construct(protected CacheActions $actions) {}

    public function index(): JsonResponse
    {
        $status = $this->actions->status();
        $store = (string) config('cache.default');

        return response()->json([
            'status' => $status,
            'actions' => [
                ['key' => 'cache', 'title' => 'Application cache', 'description' => "Runs cache:clear on the {$store} store."],
                ['key' => 'config', 'title' => 'Config cache', 'description' => 'Runs config:clear. Do this after changing .env.'],
                ['key' => 'route', 'title' => 'Route cache', 'description' => 'Runs route:clear.'],
                ['key' => 'view', 'title' => 'Compiled views', 'description' => "Runs view:clear and removes {$status['Views']} Blade files."],
                ['key' => 'clear', 'title' => 'Everything', 'description' => 'Runs optimize:clear, which does all of the above plus events.'],
            ],
        ]);
    }

    public function run(string $action, AuditLog $audit): JsonResponse
    {
        try {
            $command = $this->actions->run($action);
            $audit->record('cache.'.$action, $command);

            return $this->notice("Ran {$command}.", 'success', ['status' => $this->actions->status()]);
        } catch (Throwable $e) {
            return $this->notice("Couldn't run it: {$e->getMessage()}", 'danger');
        }
    }
}
