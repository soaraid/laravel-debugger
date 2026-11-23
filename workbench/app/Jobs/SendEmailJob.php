<?php

namespace Workbench\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void {}
}
