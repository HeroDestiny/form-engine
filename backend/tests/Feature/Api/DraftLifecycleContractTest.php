<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class DraftLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    private function publishedForm(): array
    {
        $user = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($user);
        $data = $this->postJson('/api/forms', ['name' => 'Ciclo completo'])->assertCreated()->json('data');
        $formId = $data['form']['id'];
        $versionId = $data['version']['id'];
        $field = $this->postJson("/api/forms/$formId/versions/$versionId/fields", [
            'name' => 'nome', 'label' => 'Nome original', 'type' => 'text', 'is_required' => true, 'order' => 1,
        ])->assertCreated()->json('data');
        $this->postJson("/api/forms/$formId/versions/$versionId/publish")->assertOk();

        return [$user, $formId, $versionId, $field['id']];
    }

    public function test_draft_is_updated_and_finalized_in_place_using_original_version_after_new_publication(): void
    {
        [$user, $formId, $versionId, $fieldId] = $this->publishedForm();
        $draftId = $this->postJson("/api/forms/$formId/drafts", ['values' => ['nome' => 'Inicial']])->assertCreated()->json('data.draft.id');
        $base = "/api/forms/$formId/drafts/$draftId";
        $this->patchJson($base, ['values' => ['nome' => 'Atualizado']])->assertOk();
        $this->getJson('/api/submissions/'.$draftId)->assertOk()->assertJsonPath('data.values.0.value', 'Atualizado');
        $this->getJson('/api/submissions')->assertOk()->assertJsonPath('data.pagination.total', 0);

        $next = $this->postJson("/api/forms/$formId/versions")->assertCreated()->json('data');
        $nextId = $next['version']['id'];
        $nextFieldId = $next['fields'][0]['id'];
        $this->putJson("/api/forms/$formId/versions/$nextId/fields/$nextFieldId", [
            'name' => 'nome_novo', 'label' => 'Nome novo', 'type' => 'text', 'is_required' => true, 'order' => 2,
        ])->assertOk();
        $this->postJson("/api/forms/$formId/versions/$nextId/publish")->assertOk();

        $this->postJson($base.'/submit', ['values' => []])->assertUnprocessable();
        $this->assertDatabaseHas('form_submissions', ['id' => $draftId, 'status' => 'draft', 'submitted_at' => null]);
        $this->postJson($base.'/submit', ['values' => ['nome' => 'Final']])->assertOk()
            ->assertJsonPath('data.submission.id', $draftId)->assertJsonPath('data.submission.form_version_id', $versionId);
        $this->assertDatabaseCount('form_submissions', 1);
        $this->assertDatabaseHas('form_submission_values', ['form_submission_id' => $draftId, 'form_field_id' => $fieldId, 'value' => 'Final']);
        $this->getJson('/api/submissions/'.$draftId)->assertOk()->assertJsonPath('data.values.0.label', 'Nome original');
        $this->getJson('/api/submissions')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson("/api/forms/$formId/drafts")->assertOk()->assertJsonCount(0, 'data.drafts');
        $this->patchJson($base, ['values' => ['nome' => 'Proibido']])->assertNotFound();
        $this->postJson($base.'/submit', ['values' => ['nome' => 'Duplicado']])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'SUBMIT_FORM', 'entity_id' => $draftId, 'user_id' => $user->id]);
    }

    public function test_draft_writes_are_restricted_to_owner_form_and_tenant_before_payload_validation(): void
    {
        [$owner, $formId] = $this->publishedForm();
        $id = $this->postJson("/api/forms/$formId/drafts", ['values' => ['nome' => 'Privado']])->assertCreated()->json('data.draft.id');
        foreach ([User::factory()->create(['tenant_id' => $owner->tenant_id, 'role' => 'admin']), User::factory()->create()] as $other) {
            Sanctum::actingAs($other);
            $this->patchJson("/api/forms/$formId/drafts/$id", ['values' => 'invalid'])->assertNotFound();
            $this->postJson("/api/forms/$formId/drafts/$id/submit", ['values' => 'invalid'])->assertNotFound();
        }
        Sanctum::actingAs($owner);
        $this->patchJson('/api/forms/999999/drafts/'.$id, ['values' => []])->assertNotFound();
        $this->assertDatabaseHas('form_submission_values', ['form_submission_id' => $id, 'value' => 'Privado']);
    }

    public function test_multiple_drafts_can_be_saved_and_values_can_be_cleared(): void
    {
        [, $formId] = $this->publishedForm();
        $id = $this->postJson("/api/forms/$formId/drafts", ['values' => ['nome' => 'Remover']])->assertCreated()->json('data.draft.id');
        $this->postJson("/api/forms/$formId/drafts", ['values' => []])->assertCreated();
        $this->patchJson("/api/forms/$formId/drafts/$id", ['values' => []])->assertOk();
        $this->assertDatabaseCount('form_submission_values', 0);
        $this->getJson("/api/forms/$formId/drafts")->assertOk()->assertJsonCount(2, 'data.drafts');
    }

    public function test_explicit_published_version_is_used_and_draft_versions_are_rejected(): void
    {
        [, $formId, $versionId] = $this->publishedForm();
        $next = $this->postJson("/api/forms/$formId/versions")->assertCreated()->json('data.version.id');
        $this->postJson("/api/forms/$formId/submit", ['version_id' => $next, 'values' => ['nome' => 'Teste']])->assertNotFound();
        $this->postJson("/api/forms/$formId/versions/$next/publish")->assertOk();
        $this->postJson("/api/forms/$formId/submit", ['version_id' => $versionId, 'values' => ['nome' => 'Versão antiga']])->assertCreated()
            ->assertJsonPath('data.submission.form_version_id', $versionId);
        $this->postJson("/api/forms/$formId/submit", ['values' => ['nome' => 'Última versão']])->assertCreated()
            ->assertJsonPath('data.submission.form_version_id', $next);
    }

    public function test_failed_finalization_rolls_back_status_values_and_audit(): void
    {
        [, $formId] = $this->publishedForm();
        $id = $this->postJson("/api/forms/$formId/drafts", ['values' => ['nome' => 'Original']])->assertCreated()->json('data.draft.id');
        $this->mock(AuditRecorder::class, function (MockInterface $mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });
        $this->postJson("/api/forms/$formId/drafts/$id/submit", ['values' => ['nome' => 'Alterado']])->assertStatus(500);
        $this->assertDatabaseHas('form_submissions', ['id' => $id, 'status' => 'draft', 'submitted_at' => null]);
        $this->assertDatabaseHas('form_submission_values', ['form_submission_id' => $id, 'value' => 'Original']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'SUBMIT_FORM', 'entity_id' => $id]);
    }
}
