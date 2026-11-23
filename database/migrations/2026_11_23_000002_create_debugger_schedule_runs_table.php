<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('debugger.database.connection');
    }

    public function up(): void
    {
        Schema::create(config('debugger.database.tables.schedule_runs', 'debugger_schedule_runs'), function (Blueprint $table) {
            $table->id();
            $table->string('task')->index();
            $table->string('mutex', 64)->nullable()->index();
            $table->string('expression', 100);
            // running (background, not finished yet), ran, failed, skipped
            $table->string('status', 16)->index();
            $table->text('reason')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('debugger.database.tables.schedule_runs', 'debugger_schedule_runs'));
    }
};
