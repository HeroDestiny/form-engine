<?php

namespace Tests\Feature\Api;

use App\Exceptions\BusinessException;
use App\Models\FormVersion;
use App\Models\User;
use App\Services\Forms\FormFieldService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VersionHistoryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_preserves_options_order_and_historical_submissions_after_edit_and_delete_in_new_version(): void
    {
        $actor = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($actor);
        $data = $this->postJson('/api/forms', ['name' => 'Histórico'])->assertCreated()->json('data');
        $form = $data['form']['id'];
        $v1 = $data['version']['id'];
        $payload = ['name' => 'escolha', 'label' => 'Escolha original', 'type' => 'checkbox', 'is_required' => true, 'order' => 5,
            'options' => [['value' => 'a', 'label' => 'Opção A'], ['value' => 'b', 'label' => 'Opção B']]];
        $old = $this->postJson("/api/forms/$form/versions/$v1/fields", $payload)->assertCreated()->json('data');
        $optional = $this->postJson("/api/forms/$form/versions/$v1/fields", [
            'name' => 'obs', 'label' => 'Observações', 'type' => 'text', 'is_required' => false, 'order' => 1,
        ])->assertCreated()->json('data');
        $this->postJson("/api/forms/$form/versions/$v1/publish")->assertOk();
        $submission = $this->postJson("/api/forms/$form/submit", ['values' => ['escolha' => ['a'], 'obs' => 'Histórico preservado']])
            ->assertCreated()->json('data.submission.id');
        $new = $this->postJson("/api/forms/$form/versions")->assertCreated()->json('data');
        $v2 = $new['version']['id'];
        $copied = array_column($new['fields'], null, 'name');
        foreach (['name', 'label', 'type', 'options', 'order', 'is_required'] as $attribute) {
            $this->assertSame($old[$attribute], $copied['escolha'][$attribute]);
        }
        $this->assertNotSame($old['id'], $copied['escolha']['id']);
        $this->assertDatabaseHas('form_fields', ['id' => $copied['obs']['id'], 'order' => 1]);
        $copyId = $copied['escolha']['id'];
        $this->putJson("/api/forms/$form/versions/$v2/fields/$copyId", [...$payload, 'label' => 'Escolha nova'])->assertOk();
        $copyObs = $copied['obs']['id'];
        $this->deleteJson("/api/forms/$form/versions/$v2/fields/$copyObs")->assertOk();
        $this->postJson("/api/forms/$form/versions/$v2/publish")->assertOk();
        $this->getJson('/api/submissions/'.$submission)->assertOk()->assertJsonPath('data.version.id', $v1)
            ->assertJsonPath('data.values.0.value', 'Histórico preservado')->assertJsonPath('data.values.1.label', 'Escolha original');
        $this->assertDatabaseHas('form_fields', ['id' => $optional['id'], 'form_version_id' => $v1]);
        $this->putJson("/api/forms/$form/versions/$v1/fields/".$old['id'], $payload)->assertUnprocessable();
        $this->postJson("/api/forms/$form/versions/$v2/publish")->assertUnprocessable();
        foreach (['CREATE_FORM', 'ADD_FIELD', 'UPDATE_FIELD', 'DELETE_FIELD', 'CREATE_VERSION', 'PUBLISH_VERSION', 'SUBMIT_FORM'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['tenant_id' => $actor->tenant_id, 'user_id' => $actor->id, 'action' => $action]);
        }
    }

    public function test_stale_draft_object_cannot_be_used_to_modify_a_published_version(): void
    {
        $actor = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($actor);
        $data = $this->postJson('/api/forms', ['name' => 'Concorrência'])->assertCreated()->json('data');
        $stale = FormVersion::query()->findOrFail($data['version']['id']);
        FormVersion::query()->whereKey($stale->id)->update(['is_published' => true, 'published_at' => now()]);
        $this->expectException(BusinessException::class);
        app(FormFieldService::class)->create($stale, [
            'label' => 'Proibido', 'name' => 'proibido', 'type' => 'text', 'options' => null, 'is_required' => false, 'order' => 1,
        ]);
    }

    public function test_database_rejects_two_draft_versions_for_the_same_form(): void
    {
        $actor = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($actor);
        $data = $this->postJson('/api/forms', ['name' => 'Unicidade'])->assertCreated()->json('data');
        $this->expectException(QueryException::class);
        FormVersion::query()->create(['form_id' => $data['form']['id'], 'version_number' => 2, 'is_published' => false, 'created_by' => $actor->id]);
    }
}
