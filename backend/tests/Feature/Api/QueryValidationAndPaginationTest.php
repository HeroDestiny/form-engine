<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueryValidationAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidQueries(): iterable
    {
        foreach (['/submissions', '/audit-logs', '/admin/tenants', '/tenants/{tenant}/users'] as $path) {
            foreach (['page' => [0, -1, 'abc', '1.5', [], 2147483648], 'per_page' => [0, -1, 101, 'abc', '1.5', []]] as $field => $values) {
                foreach ($values as $index => $value) {
                    yield "$path $field $index" => [$path, [$field => $value], $field];
                }
            }
        }
        foreach (['/submissions' => ['form_id', 'version_id', 'user_id'], '/audit-logs' => ['user_id', 'entity_id']] as $path => $fields) {
            foreach ($fields as $field) {
                foreach ([-1, 0, 'abc', '1.5', ['x'], 2147483648] as $index => $value) {
                    yield "$path $field $index" => [$path, [$field => $value], $field];
                }
            }
        }
        foreach (['/submissions', '/audit-logs'] as $path) {
            foreach (['date_from' => ['bad', '2026-02-30', ['x']], 'date_to' => ['bad', '2026-02-30', ['x']]] as $field => $values) {
                foreach ($values as $index => $value) {
                    yield "$path $field $index" => [$path, [$field => $value], $field];
                }
            }
            yield "$path reversed dates" => [$path, ['date_from' => '2026-10-02', 'date_to' => '2026-10-01'], 'date_to'];
        }
        foreach (['status' => 'other', 'date_from' => '2026-10-01T00:00:00Z'] as $field => $value) {
            yield "submissions invalid $field" => ['/submissions', [$field => $value], $field];
        }
        foreach (['action' => ['x'], 'entity_type' => ['x'], 'date_from' => 'tomorrow'] as $field => $value) {
            yield "audit invalid $field" => ['/audit-logs', [$field => $value], $field];
        }
        foreach (['search' => ['x'], 'sort' => 'id', 'order' => 'invalid', 'scope' => 'foreign'] as $field => $value) {
            yield "forms invalid $field" => ['/forms', [$field => $value], $field];
        }
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_returns_validation_envelope(string $path, array $query, string $field): void
    {
        $actor = User::factory()->create(['role' => 'admin-sistema']);
        Sanctum::actingAs($actor);
        // Empty arrays need an explicit [] parameter; http_build_query would omit them.
        $encoded = $query[$field] === [] ? "$field%5B%5D=" : http_build_query($query);
        $path = str_replace('{tenant}', (string) $actor->tenant_id, $path);
        $this->getJson('/api'.$path.'?'.$encoded)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonPath('data', null)->assertJsonValidationErrors($field);
    }

    private function version(User $owner, string $name = 'Teste', bool $active = true, bool $published = true): FormVersion
    {
        $form = Form::query()->create(['tenant_id' => $owner->tenant_id, 'created_by' => $owner->id, 'name' => $name, 'is_active' => $active]);

        return FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1, 'is_published' => $published,
            'published_at' => $published ? now() : null, 'created_by' => $owner->id]);
    }

    private function submission(User $owner, FormVersion $version, string $date, string $status = 'submitted'): FormSubmission
    {
        $submission = new FormSubmission(['tenant_id' => $owner->tenant_id, 'submitted_by' => $owner->id,
            'form_version_id' => $version->id, 'status' => $status, 'submitted_at' => $status === 'submitted' ? $date : null]);
        $submission->created_at = $date;
        $submission->save();

        return $submission;
    }

    private function log(User $owner, string $date, array $attributes = []): AuditLog
    {
        $log = new AuditLog(['tenant_id' => $owner->tenant_id, 'user_id' => $owner->id, 'action' => 'CREATE_FORM',
            'entity_type' => 'form', 'entity_id' => 10, 'metadata' => [], ...$attributes]);
        $log->created_at = $date;
        $log->save();

        return $log;
    }

    public function test_submission_filters_combine_and_keep_user_and_tenant_isolation(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['tenant_id' => $manager->tenant_id]);
        $version = $this->version($manager);
        $target = $this->submission($owner, $version, '2026-10-01 23:59:59');
        $this->submission($owner, $version, '2026-09-30 23:59:59');
        $this->submission($owner, $version, '2026-10-02 00:00:00');
        $this->submission($manager, $version, '2026-10-01 12:00:00');
        $this->submission($owner, $this->version($owner, 'Outro'), '2026-10-01 12:00:00');
        $draft = $this->submission($owner, $version, '2026-10-01 12:00:00', 'draft');
        $foreign = User::factory()->create();
        $foreignVersion = $this->version($foreign);
        $this->submission($foreign, $foreignVersion, '2026-10-01 12:00:00');
        Sanctum::actingAs($manager);
        $filters = ['form_id' => $version->form_id, 'version_id' => $version->id, 'user_id' => $owner->id,
            'date_from' => '2026-10-01', 'date_to' => '2026-10-01'];
        $this->getJson('/api/submissions?'.http_build_query($filters))->assertOk()->assertJsonCount(1, 'data.submissions')
            ->assertJsonPath('data.submissions.0.id', $target->id);
        $this->getJson('/api/submissions?'.http_build_query([...$filters, 'status' => 'draft']))->assertOk()
            ->assertJsonCount(1, 'data.submissions')->assertJsonPath('data.submissions.0.id', $draft->id);
        foreach (['form_id' => $foreignVersion->form_id, 'version_id' => $foreignVersion->id, 'user_id' => $foreign->id, 'form_id_missing' => 2147483647] as $key => $id) {
            $key = $key === 'form_id_missing' ? 'form_id' : $key;
            $this->getJson("/api/submissions?$key=$id")->assertOk()->assertJsonCount(0, 'data.submissions')->assertJsonPath('data.pagination.total', 0);
        }
        Sanctum::actingAs($owner);
        $this->getJson('/api/submissions?user_id='.$manager->id)->assertOk()->assertJsonCount(4, 'data.submissions');
        foreach ($this->getJson('/api/submissions')->json('data.submissions') as $item) {
            $this->assertSame($owner->id, $item['user']['id']);
        }
    }

    public function test_submission_pages_have_stable_order_correct_metadata_and_empty_last_pages(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $version = $this->version($owner);
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->submission($owner, $version, '2026-10-01 12:00:00')->id;
        }
        Sanctum::actingAs($owner);
        $seen = [];
        for ($page = 1; $page <= 3; $page++) {
            $response = $this->getJson("/api/submissions?per_page=2&page=$page")->assertOk()
                ->assertJsonPath('data.pagination.total', 5)->assertJsonPath('data.pagination.last_page', 3)
                ->assertJsonPath('data.pagination.current_page', $page)->assertJsonPath('data.pagination.per_page', 2)
                ->assertJsonPath('data.pagination.from', ($page - 1) * 2 + 1)->assertJsonPath('data.pagination.to', min($page * 2, 5));
            array_push($seen, ...array_column($response->json('data.submissions'), 'id'));
        }
        $this->assertSame(array_reverse($ids), $seen);
        $this->getJson('/api/submissions?per_page=2&page=4')->assertOk()->assertJsonCount(0, 'data.submissions')
            ->assertJsonPath('data.pagination.from', null)->assertJsonPath('data.pagination.to', null)->assertJsonPath('data.pagination.total', 5);
        $this->getJson('/api/submissions?date_to=2026-09-30')->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/submissions?date_from=2026-10-01')->assertOk()->assertJsonPath('data.pagination.total', 5);
        $this->getJson('/api/submissions?per_page=100')->assertOk()->assertJsonPath('data.pagination.per_page', 100);
    }

    public function test_audit_filters_include_whole_final_day_and_honor_timestamp_offsets_and_ties(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = $this->log($admin, '2026-10-01 23:59:59');
        $tie = $this->log($admin, '2026-10-01 23:59:59');
        $this->log($admin, '2026-10-02 00:00:00');
        $this->log($admin, '2026-09-30 23:59:59');
        $this->log($admin, '2026-10-01 12:00:00', ['action' => 'UPDATE_FORM']);
        $this->log($admin, '2026-10-01 12:00:00', ['entity_type' => 'user']);
        $this->log($admin, '2026-10-01 12:00:00', ['entity_id' => 11]);
        $this->log(User::factory()->create(), '2026-10-01 12:00:00');
        Sanctum::actingAs($admin);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'action' => 'CREATE_FORM',
            'entity_type' => 'form', 'entity_id' => 10, 'user_id' => $admin->id, 'per_page' => 1];
        $this->getJson('/api/audit-logs?'.http_build_query($filters))->assertOk()->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.logs.0.id', $tie->id);
        $this->getJson('/api/audit-logs?'.http_build_query([...$filters, 'page' => 2]))->assertOk()->assertJsonPath('data.logs.0.id', $target->id);
        $this->getJson('/api/audit-logs?'.http_build_query([...$filters, 'date_from' => '2026-10-01T20:59:59-03:00', 'date_to' => '2026-10-01T23:59:59Z']))
            ->assertOk()->assertJsonPath('data.pagination.total', 2);
        $this->getJson('/api/audit-logs?date_to=2026-09-30')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/audit-logs?'.http_build_query([...$filters, 'date_from' => '2026-10-01T23:59:59Z']))
            ->assertOk()->assertJsonPath('data.pagination.total', 2);
        $this->getJson('/api/audit-logs?'.http_build_query([...$filters, 'date_from' => '2026-10-02T00:00:00Z']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson('/api/audit-logs?action=UNKNOWN')->assertOk()->assertJsonCount(0, 'data.logs');
        $this->getJson('/api/audit-logs?user_id=2147483647')->assertOk()->assertJsonCount(0, 'data.logs');
    }

    public function test_forms_search_order_and_scope_are_consistent_and_tenant_scoped(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $alpha = $this->version($manager, 'Alpha');
        $beta = $this->version($manager, 'Beta');
        $inactive = $this->version($manager, 'Inativo', false);
        $draft = $this->version($manager, 'Rascunho', true, false);
        $this->version(User::factory()->create(), 'Alpha externo');
        Sanctum::actingAs($manager);
        $this->getJson('/api/forms')->assertOk()->assertJsonCount(2, 'data.forms')->assertJsonPath('data.forms.0.id', $alpha->form_id);
        $this->getJson('/api/forms?order=desc')->assertOk()->assertJsonPath('data.forms.0.id', $beta->form_id);
        $this->getJson('/api/forms?search=ALPHA')->assertOk()->assertJsonCount(1, 'data.forms')->assertJsonPath('data.forms.0.id', $alpha->form_id);
        $this->getJson('/api/forms?scope=all&sort=created_at&order=desc')->assertOk()->assertJsonCount(4, 'data.forms');
        $this->getJson('/api/forms?search=missing')->assertOk()->assertJsonPath('data.total', 0)->assertJsonCount(0, 'data.forms');
        $user = User::factory()->create(['tenant_id' => $manager->tenant_id, 'role' => 'user']);
        Sanctum::actingAs($user);
        $this->getJson('/api/forms?scope=all')->assertOk()->assertJsonCount(2, 'data.forms');
    }

    public function test_tenant_and_user_listing_paginate_without_cross_tenant_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin-sistema']);
        $users = User::factory()->count(3)->create(['tenant_id' => $admin->tenant_id]);
        $foreign = User::factory()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/tenants/'.$admin->tenant_id.'/users?per_page=2&page=2')->assertOk()
            ->assertJsonPath('data.pagination.total', 4)->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.users.0.id', $users[1]->id)->assertJsonPath('data.users.1.id', $users[2]->id);
        $this->getJson('/api/admin/tenants?per_page=1&page=2')->assertOk()->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.tenants.0.id', $foreign->tenant_id);
    }

    public function test_version_filter_is_independent_and_draft_order_uses_creation_time(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $first = $this->version($owner);
        $second = FormVersion::query()->create(['form_id' => $first->form_id, 'version_number' => 2,
            'is_published' => true, 'published_at' => now(), 'created_by' => $owner->id]);
        $this->submission($owner, $first, '2026-10-01 12:00:00');
        $target = $this->submission($owner, $second, '2026-10-01 12:00:00');
        $older = $this->submission($owner, $first, '2026-10-01 00:00:00', 'draft');
        $newer = $this->submission($owner, $first, '2026-10-01 23:59:59', 'draft');
        Sanctum::actingAs($owner);
        $this->getJson('/api/submissions?version_id='.$second->id)->assertOk()->assertJsonCount(1, 'data.submissions')
            ->assertJsonPath('data.submissions.0.id', $target->id);
        $this->getJson('/api/submissions?status=draft&per_page=1')->assertOk()->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.submissions.0.id', $newer->id);
        $this->getJson('/api/submissions?status=draft&per_page=1&page=2')->assertOk()->assertJsonPath('data.submissions.0.id', $older->id);
        $this->getJson('/api/submissions?date_from=&date_to=&status=')->assertOk()->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.per_page', 20);
    }
}
