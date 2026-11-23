<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Soara\Debugger\Connections\Pinger;
use Soara\Debugger\Http\Controllers\Controller;

class ConnectionsController extends Controller
{
    public function __construct(protected Pinger $pinger) {}

    public function index(): JsonResponse
    {
        return response()->json($this->state());
    }

    public function ping(string $key): JsonResponse
    {
        abort_unless(isset($this->pinger->services()[$key]), 404);

        $result = $this->pinger->ping($key);
        $name = $this->pinger->services()[$key]['name'];

        return $this->notice("{$name}: {$result['label']}.", $result['tone'] === 'ok' ? 'success' : 'danger', $this->state());
    }

    public function pingAll(): JsonResponse
    {
        $failed = 0;

        foreach (array_keys($this->pinger->services()) as $key) {
            $failed += $this->pinger->ping($key)['tone'] === 'ok' ? 0 : 1;
        }

        $total = count($this->pinger->services());

        return $this->notice(
            $failed === 0 ? "All {$total} services answered." : "{$failed} of {$total} services had a problem.",
            $failed === 0 ? 'success' : 'danger',
            $this->state(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(): array
    {
        $results = $this->pinger->results();
        $checked = collect($results)->pluck('checked_at')->filter()->max();
        $services = [];

        foreach ($this->pinger->services() as $key => $service) {
            $services[] = [
                'key' => $key,
                'name' => $service['name'],
                'endpoint' => $service['method'].' '.preg_replace('#^https?://#', '', $service['url']),
                'result' => $results[$key] ?? null,
            ];
        }

        return [
            'services' => $services,
            'last_checked' => $checked ? CarbonImmutable::parse($checked)->format('H:i') : null,
        ];
    }
}
