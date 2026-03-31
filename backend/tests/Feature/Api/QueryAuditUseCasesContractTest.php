<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueryAuditUseCasesContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_uc15_manager_can_list_tenant_submissions_with_filters(): void
    {
        [$manager, $form, $submission] = $this->makeSubmittedData('manager');
        $otherTenantManager = User::factory()->create(['role' => 'manager']);
        $this->makeSubmittedData('user', $otherTenantManager->tenant_id);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/submissions?form_id='.$form->id);

        $response->assertOk()->assertJsonPath('success', true);
        $ids = array_column($response->json('data.submissions'), 'id');

        $this->assertContains($submission->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_uc15_user_sees_only_own_submissions(): void
    {
        [$owner] = $this->makeSubmittedData('user');
        [$other] = $this->makeSubmittedData('user', $owner->tenant_id);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/submissions');

        $response->assertOk()->assertJsonPath('success', true);

        $submittedBy = array_map(static fn ($item) => $item['user']['id'], $response->json('data.submissions'));
        $this->assertContains($owner->id, $submittedBy);
        $this->assertNotContains($other->id, $submittedBy);
    }

    public function test_uc16_manager_can_view_submission_details_in_tenant(): void
    {
        [$manager, , $submission] = $this->makeSubmittedData('manager');
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/submissions/'.$submission->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission.id', $submission->id)
            ->assertJsonPath('data.values.0.label', 'Nome Completo');
    }

    public function test_uc16_user_cannot_view_submission_from_another_user(): void
    {
        [$owner, , $submission] = $this->makeSubmittedData('user');
        [$other] = $this->makeSubmittedData('user', $owner->tenant_id);

        Sanctum::actingAs($other);

        $response = $this->getJson('/api/submissions/'.$submission->id);

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Submissão não encontrada');
    }

    public function test_uc17_manager_can_export_csv_for_form_submissions(): void
    {
        [$manager, $form] = $this->makeSubmittedData('manager');
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/submissions/export', [
            'form_id' => $form->id,
        ]);

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $this->assertStringContainsString('ID,Usuário,Email,Data de Submissão,Versão', $response->streamedContent());
    }

    public function test_uc17_user_cannot_export_csv(): void
    {
        [$user, $form] = $this->makeSubmittedData('user');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/submissions/export', [
            'form_id' => $form->id,
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_uc18_admin_can_list_audit_logs_from_own_tenant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AuditLog::query()->create([
            'tenant_id' => $admin->tenant_id,
            'user_id' => $admin->id,
            'action' => 'SUBMIT_FORM',
            'entity_type' => 'form_submission',
            'entity_id' => 1,
            'metadata' => ['form_id' => 1],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/audit-logs?action=SUBMIT_FORM');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.logs.0.action', 'SUBMIT_FORM');
    }

    public function test_uc18_manager_cannot_access_audit_logs(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_uc19_admin_can_view_audit_log_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $log = AuditLog::query()->create([
            'tenant_id' => $admin->tenant_id,
            'user_id' => $admin->id,
            'action' => 'EXPORT_DATA',
            'entity_type' => 'form_submission',
            'entity_id' => 12,
            'metadata' => ['records_count' => 5],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/audit-logs/'.$log->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.log.id', $log->id)
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.entity_exists', false)
            ->assertJsonPath('data.related_actions_count', 0);
    }

    private function makeSubmittedData(string $role = 'manager', ?int $tenantId = null): array
    {
        $user = User::factory()->create([
            'role' => $role,
            'tenant_id' => $tenantId ?? User::factory()->create()->tenant_id,
        ]);

        $form = Form::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Consulta '.uniqid(),
            'description' => 'Consulta',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $version = FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 1,
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $field1 = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Nome Completo',
            'name' => 'nome_completo',
            'type' => 'text',
            'is_required' => true,
            'options' => null,
            'order' => 1,
        ]);

        $field2 = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Estado Civil',
            'name' => 'estado_civil',
            'type' => 'select',
            'is_required' => true,
            'options' => [
                ['value' => 'solteiro', 'label' => 'Solteiro(a)'],
            ],
            'order' => 2,
        ]);

        $submission = FormSubmission::query()->create([
            'tenant_id' => $user->tenant_id,
            'form_version_id' => $version->id,
            'status' => 'submitted',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        FormSubmissionValue::query()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field1->id,
            'value' => 'Maria Santos',
        ]);

        FormSubmissionValue::query()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field2->id,
            'value' => 'solteiro',
        ]);

        return [$user, $form, $submission];
    }
}
