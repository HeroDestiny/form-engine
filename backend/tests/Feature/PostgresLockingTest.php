<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormVersion;
use App\Models\User;
use App\Repositories\Contracts\FormRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresLockingTest extends TestCase
{
    public function test_form_and_version_locks_block_competing_writes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Row locking is verified in the isolated PostgreSQL profile.');
        }
        $user = User::factory()->create();
        $form = Form::query()->create(['tenant_id' => $user->tenant_id, 'name' => 'Lock test', 'created_by' => $user->id]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1, 'created_by' => $user->id]);
        config(['database.connections.competitor' => config('database.connections.pgsql')]);
        $competitor = DB::connection('competitor');
        $competitor->statement("SET lock_timeout = '100ms'");
        DB::beginTransaction();
        try {
            app(FormRepositoryInterface::class)->findInTenant($user->tenant_id, $form->id, true);
            app(FormVersionRepositoryInterface::class)->findInTenant($user->tenant_id, $form->id, $version->id, true);
            foreach (['forms' => $form->id, 'form_versions' => $version->id] as $table => $id) {
                try {
                    $competitor->table($table)->where('id', $id)->update(['updated_at' => now()]);
                    $this->fail('Competing write bypassed the lock.');
                } catch (QueryException $exception) {
                    $this->assertSame('55P03', $exception->errorInfo[0]);
                }
            }
        } finally {
            DB::rollBack();
            DB::purge('competitor');
            $user->tenant()->delete();
        }
    }
}
