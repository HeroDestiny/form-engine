<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseResetRegressionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_fresh_migrations_can_repeat_and_preserve_audit_immutability(): void
    {
        // DatabaseMigrations already ran migrate:fresh once, without a transaction.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $log = AuditLog::create(['action' => 'RESET_PROBE']);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'RESET_PROBE']);

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'MUTATED']);
    }
}
