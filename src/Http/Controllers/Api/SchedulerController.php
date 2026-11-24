<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Scheduler\Heartbeat;
use Soara\Debugger\Scheduler\SchedulerRuns;
use Soara\Debugger\Support\Format;

class SchedulerController extends Controller
{
    public function index(Request $request, SchedulerRuns $runs, Heartbeat $heartbeat): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $recent = $runs->recent($page, 25);
        $last = $heartbeat->lastBeat();

        return response()->json([
            'tasks' => array_map(fn (array $task): array => [
                'task' => $task['task'],
                'expression' => $task['expression'],
                'every' => $task['every'],
                'next' => $task['next']?->diffForHumans(['parts' => 1]),
                'last' => $task['last_at']?->diffForHumans(['parts' => 1]),
                'last_status' => $this->status($task['last_status']),
            ], $runs->tasks()),
            'runs' => array_map(fn (object $run): array => [
                'id' => $run->id,
                'time' => Format::time(CarbonImmutable::parse($run->started_at)),
                'task' => $run->task,
                'status' => $this->status($run->status),
                'reason' => $run->reason,
                'took' => $run->duration_ms !== null ? Format::milliseconds((float) $run->duration_ms) : null,
            ], $recent['runs']),
            'error' => $recent['error'],
            'last_beat' => $last?->diffForHumans(),
            'late_minutes' => $heartbeat->isLate() ? (int) $last->diffInMinutes(now()) : null,
            'never_ran' => $last === null,
            'cron' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            'retention' => (int) config('debugger.scheduler.retention_days', 7),
            'pagination' => $this->pages($page, count($recent['runs']), 25, $recent['has_more'], $recent['total']),
        ]);
    }

    /**
     * v0.1.0 to v0.1.5 recorded a successful run as "ran".
     */
    protected function status(?string $status): ?string
    {
        return $status === 'ran' ? 'success' : $status;
    }
}
