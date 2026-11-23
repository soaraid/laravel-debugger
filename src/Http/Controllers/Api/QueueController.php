<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Queue\FailedJobs;
use Soara\Debugger\Support\Format;
use Throwable;

class QueueController extends Controller
{
    public function __construct(protected FailedJobs $jobs, protected AuditLog $audit) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = 25;
        $result = $this->jobs->page($page, $perPage);
        $from = ($page - 1) * $perPage;

        return response()->json([
            'jobs' => array_map(fn (array $job): array => [
                ...$job,
                'failed_ago' => Format::ago($job['failed_at']),
                'failed_at' => $job['failed_at']?->format('M j, H:i:s'),
            ], $result['jobs']),
            'total' => $result['total'],
            'error' => $result['error'],
            'waiting' => $this->jobs->waiting(),
            'pagination' => $this->pages($page, count($result['jobs']), $perPage, $from + count($result['jobs']) < $result['total'], $result['total']),
        ]);
    }

    public function retry(string $id): JsonResponse
    {
        try {
            $job = $this->jobs->find($id);
            $this->jobs->retry($id);
            $this->audit->record('queue.retry', $id, ['job' => $job['class'] ?? null]);

            return $this->notice(($job['name'] ?? 'Job').' sent back to the '.($job['queue'] ?? 'default').' queue.');
        } catch (Throwable $e) {
            return $this->notice("Couldn't retry the job: {$e->getMessage()}", 'danger');
        }
    }

    public function retryAll(): JsonResponse
    {
        try {
            $count = $this->jobs->retryAll();
            $this->audit->record('queue.retry_all', null, ['count' => $count]);

            return $this->notice($count === 0 ? 'There were no failed jobs to retry.' : "{$count} failed jobs sent back to their queues.");
        } catch (Throwable $e) {
            return $this->notice("Couldn't retry the jobs: {$e->getMessage()}", 'danger');
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $job = $this->jobs->find($id);
            $this->jobs->forget($id);
            $this->audit->record('queue.delete', $id, ['job' => $job['class'] ?? null]);

            return $this->notice(($job['name'] ?? 'Job').' deleted from failed jobs.');
        } catch (Throwable $e) {
            return $this->notice("Couldn't delete the job: {$e->getMessage()}", 'danger');
        }
    }
}
