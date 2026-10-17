<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyRegressionTest extends TestCase
{
    use DatabaseMigrations;

    public static function operations(): array
    {
        return [
            'duplicate field' => ['field', [201, 422]],
            'duplicate form' => ['form', [201, 422]],
            'duplicate tenant' => ['tenant', [201, 422]],
            'duplicate user' => ['user', [201, 422]],
            'next version' => ['version', [201, 400]],
            'publish version' => ['publish', [200, 422]],
            'finalize draft' => ['finalize', [200, 404]],
        ];
    }

    #[DataProvider('operations')]
    public function test_two_competing_requests_keep_invariants_and_return_domain_responses(string $operation, array $expected): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Two real workers require the disposable PostgreSQL profile.');
        }
        $user = User::factory()->create(['role' => 'admin-sistema']);
        $form = Form::create(['tenant_id' => $user->tenant_id, 'name' => 'Concurrency review', 'created_by' => $user->id]);
        $version = FormVersion::create(['form_id' => $form->id, 'version_number' => 1, 'created_by' => $user->id,
            'is_published' => in_array($operation, ['version', 'finalize'], true)]);
        FormField::create(['form_version_id' => $version->id, 'name' => 'base', 'label' => 'Base', 'type' => 'text', 'order' => 1]);
        $path = '/api/forms/'.$form->id.'/versions/'.$version->id.'/fields';
        $payload = ['label' => 'Concurrent', 'name' => 'same_name', 'type' => 'text', 'order' => 2, 'is_required' => false];
        $lockTable = null;
        $rowTable = 'form_versions';
        $rowId = $version->id;
        if ($operation === 'form') {
            $path = '/api/forms';
            $payload = ['name' => 'Concurrent new form'];
            $lockTable = 'forms';
        } elseif ($operation === 'tenant') {
            $path = '/api/admin/tenants';
            $payload = ['name' => 'Concurrent tenant', 'slug' => 'concurrent-tenant'];
            $lockTable = 'tenants';
        } elseif ($operation === 'user') {
            $path = '/api/tenants/'.$user->tenant_id.'/users';
            $payload = ['name' => 'Concurrent user', 'email' => 'concurrent@example.test', 'password' => 'password', 'role' => 'user'];
            $lockTable = 'users';
        } elseif ($operation === 'version') {
            $path = '/api/forms/'.$form->id.'/versions';
            $payload = [];
            $rowTable = 'forms';
            $rowId = $form->id;
        } elseif ($operation === 'publish') {
            $path = '/api/forms/'.$form->id.'/versions/'.$version->id.'/publish';
            $payload = [];
        } elseif ($operation === 'finalize') {
            $draft = FormSubmission::create(['tenant_id' => $user->tenant_id, 'form_version_id' => $version->id,
                'submitted_by' => $user->id, 'status' => 'draft']);
            $path = '/api/forms/'.$form->id.'/drafts/'.$draft->id.'/submit';
            $payload = ['values' => ['base' => 'Valid']];
            $rowTable = 'form_submissions';
            $rowId = $draft->id;
        }
        $token = $user->createToken('review')->plainTextToken;
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'form_engine_testing',
            'DB_USERNAME' => 'form_engine_testing', 'DB_PASSWORD' => 'testing_only', 'DB_HOST' => 'postgres-test',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null',
            'BCRYPT_ROUNDS' => '4',
        ];
        $workers = [];
        DB::beginTransaction();
        try {
            if ($lockTable !== null) {
                // Only fixed test table names above enter this statement.
                DB::statement('LOCK TABLE '.$lockTable.' IN SHARE MODE');
            } else {
                DB::table($rowTable)->where('id', $rowId)->lockForUpdate()->first();
            }
            foreach ([1, 2] as $index) {
                $worker = new Process([PHP_BINARY, __DIR__.'/../Support/concurrent-request.php',
                    $path, $token, base64_encode(json_encode($payload))], base_path(), $environment);
                $worker->setTimeout(90)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 60;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE application_name = 'review-worker' AND wait_event_type = 'Lock'")->total;
                if ((int) $blocked === 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $diagnostics = implode(' | ', array_map(fn ($worker) => $worker->getOutput().$worker->getErrorOutput(), $workers));
            $this->assertSame(2, (int) $blocked, 'Both requests must finish validation and wait on the version lock. '.$diagnostics);
            DB::commit();
            $statuses = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $statuses[] = (int) $worker->getOutput();
            }
            sort($statuses);
            $this->assertSame($expected, $statuses, 'Unexpected concurrent HTTP statuses for '.$operation);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }
}
