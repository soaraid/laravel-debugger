<?php

namespace Soara\Debugger\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Soara\Debugger\Scheduler\SchedulerRuns;

class PruneCommand extends Command
{
    protected $signature = 'debugger:prune';

    protected $description = 'Delete old scheduler runs and audit entries kept by soara/laravel-debugger';

    public function handle(SchedulerRuns $runs): int
    {
        $runDays = (int) config('debugger.scheduler.retention_days', 7);
        $auditDays = (int) config('debugger.audit.retention_days', 90);

        $deletedRuns = $runs->prune($runDays);
        $deletedAudit = DB::connection(config('debugger.database.connection'))
            ->table((string) config('debugger.database.tables.audit_logs', 'debugger_audit_logs'))
            ->where('created_at', '<', CarbonImmutable::now()->subDays($auditDays))
            ->delete();

        $this->components->info("Deleted {$deletedRuns} scheduler runs older than {$runDays} days and {$deletedAudit} audit entries older than {$auditDays} days.");

        return self::SUCCESS;
    }
}
