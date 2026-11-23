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
        Schema::create(config('debugger.database.tables.audit_logs', 'debugger_audit_logs'), function (Blueprint $table) {
            $table->id();
            $table->string('action', 64)->index();
            $table->string('target')->nullable();
            // String, so integer and UUID user keys both fit.
            $table->string('user_id', 64)->nullable()->index();
            $table->string('user_name')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('debugger.database.tables.audit_logs', 'debugger_audit_logs'));
    }
};
