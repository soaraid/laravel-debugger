<?php

namespace Workbench\Database\Seeders;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Soara\Debugger\Scheduler\Heartbeat;
use Workbench\App\Jobs\GenerateInvoicePdf;
use Workbench\App\Jobs\SendEmailJob;
use Workbench\App\Jobs\SyncStockJob;
use Workbench\App\Models\User;

/**
 * Sample data so every debugger page has something to show in the workbench.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Testbench may seed more than once per build, so stay idempotent.
        if (User::query()->exists()) {
            return;
        }

        $people = [
            // Example people only: the repo and its screenshots are public.
            ['Dev Admin', 'dev@example.com', 'developer', 0],
            ['Rizal Hakim', 'rizal@example.com', 'developer', 2880],
            ['Budi Santoso', 'budi@example.com', 'admin', 5],
            ['Siti Rahmawati', 'siti@example.com', 'owner', 60],
            ['Andi Pratama', 'andi@example.com', 'client', 1440],
            ['Dewi Lestari', 'dewi@example.com', 'client', 7200],
        ];

        foreach ($people as [$name, $email, $role, $minutesAgo]) {
            User::factory()->create(['name' => $name, 'email' => $email, 'role' => $role, 'last_login_at' => now()->subMinutes($minutesAgo)]);
        }

        $this->failedJobs();
        $this->logs();
        $this->schedulerRuns();

        Storage::disk('local')->put('debugger/invoice-sample.txt', "Invoice INV-2611-0057\nTotal: Rp 245.000\n");
    }

    protected function failedJobs(): void
    {
        $jobs = [
            [SendEmailJob::class, 'default', 'Symfony\Component\Mailer\Exception\TransportException: Connection could not be established with host "smtp.mailgun.org:587": Connection timed out', 18],
            [SyncStockJob::class, 'default', 'Illuminate\Http\Client\ConnectionException: cURL error 28: Operation timed out after 30001 milliseconds (api.example.com)', 120],
            [GenerateInvoicePdf::class, 'pdf', 'Symfony\Component\ErrorHandler\Error\FatalError: Allowed memory size of 268435456 bytes exhausted (tried to allocate 20480 bytes)', 900],
        ];

        foreach ($jobs as [$class, $queue, $error, $minutesAgo]) {
            $uuid = (string) Str::uuid();

            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => $queue,
                'payload' => json_encode([
                    'uuid' => $uuid,
                    'displayName' => $class,
                    'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                    'attempts' => 3,
                    'data' => ['commandName' => $class, 'command' => serialize(new $class)],
                ]),
                'exception' => $error."\n#0 /var/www/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php(154): start()\n#1 /var/www/app/Services/MailService.php(12): App\Jobs\SendEmailJob->handle()\n#2 /var/www/app/Services/OrderService.php(188): App\Services\MailService->send()\n#3 {main}",
                'failed_at' => now()->subMinutes($minutesAgo),
            ]);
        }
    }

    protected function schedulerRuns(): void
    {
        $rows = [];
        $start = now()->subMinutes(40)->startOfMinute();

        for ($i = 0; $i <= 40; $i++) {
            $at = $start->copy()->addMinutes($i);
            $skipped = $i === 18;
            $rows[] = [
                'task' => 'task:schedule-run',
                'expression' => '* * * * *',
                'status' => $skipped ? 'skipped' : 'ran',
                'reason' => $skipped ? 'previous run still going' : null,
                'exit_code' => $skipped ? null : 0,
                'started_at' => $at,
                'finished_at' => $at->copy()->addSecond(),
                'duration_ms' => $skipped ? null : random_int(700, 1400),
            ];

            if ($i % 15 === 0) {
                $failed = $i === 30;
                $rows[] = [
                    'task' => 'orders:expire-unpaid',
                    'expression' => '*/15 * * * *',
                    'status' => $failed ? 'failed' : 'ran',
                    'reason' => $failed ? 'exit code 1' : null,
                    'exit_code' => $failed ? 1 : 0,
                    'started_at' => $at,
                    'finished_at' => $at->copy()->addSeconds(3),
                    'duration_ms' => random_int(2400, 3600),
                ];
            }
        }

        DB::table('debugger_schedule_runs')->insert($rows);

        app(Heartbeat::class)->beat(app(Schedule::class));
    }

    protected function logs(): void
    {
        $dir = storage_path('logs');
        File::ensureDirectoryExists($dir);

        $now = now()->subMinutes(30);
        $lines = [];
        $samples = [
            ['INFO', 'Processing jobs from the [default] queue'],
            ['WARNING', 'Slow query (2.418 ms): select * from `orders` where `status` = ? order by `created_at` desc'],
            ['INFO', 'Invoice INV-2611-0057 generated for order #18342'],
            ['DEBUG', 'Cache miss: settings.general'],
            ['INFO', 'User 214 signed in'],
            ['WARNING', 'Rate limit hit for api:203.0.113.9 (60 per minute)'],
            ['ERROR', 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'INV-2611-0042\' for key \'invoices_number_unique\''],
            ['INFO', 'Payment callback received for order #18331'],
            ['ERROR', 'Call to a member function format() on null at app/Http/Resources/OrderResource.php:41'],
        ];

        for ($i = 0; $i < 60; $i++) {
            [$level, $message] = $samples[$i % count($samples)];
            $time = $now->copy()->addSeconds($i * 25)->format('Y-m-d H:i:s');
            $lines[] = "[{$time}] local.{$level}: {$message}";

            if ($level === 'ERROR') {
                $lines[] = '[stacktrace]';
                $lines[] = '#0 /var/www/app/Http/Controllers/InvoiceController.php(54): App\Services\InvoiceService->create()';
                $lines[] = '#1 /var/www/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): App\Http\Controllers\InvoiceController->store()';
                $lines[] = '#2 /var/www/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(43): Illuminate\Routing\Controller->callAction()';
                $lines[] = '#3 {main}';
            }
        }

        $time = now()->subMinute()->format('Y-m-d H:i:s');
        $lines[] = "[{$time}] local.ERROR: Connection could not be established with host \"smtp.mailgun.org:587\": Connection timed out {\"exception\":\"[object] (Symfony\\\\Component\\\\Mailer\\\\Exception\\\\TransportException(code: 0): Connection timed out at /var/www/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php:154)";
        $lines[] = '[stacktrace]';
        $lines[] = '#0 /var/www/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(275): Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream->initialize()';
        $lines[] = '#1 /var/www/app/Services/MailService.php(12): App\Jobs\SendEmailJob->handle()';
        $lines[] = '#2 /var/www/app/Services/OrderService.php(188): App\Services\MailService->send()';
        $lines[] = '#3 {main}';
        $lines[] = '"}';

        File::put($dir.'/laravel.log', implode("\n", $lines)."\n");
        File::put($dir.'/worker.log', '['.now()->format('Y-m-d H:i:s')."] local.INFO: Worker started on database/default\n");
    }
}
