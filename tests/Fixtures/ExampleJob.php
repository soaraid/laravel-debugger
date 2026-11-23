<?php

namespace Soara\Debugger\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExampleJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void {}
}
