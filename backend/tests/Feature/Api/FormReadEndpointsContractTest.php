<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormReadEndpointsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_form_details_with_versions_and_fields(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/forms/'.$form->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.form.id', $form->id)
            ->assertJsonPath('data.form.latest_version.id', $version->id);
    }

    public function test_show_form_returns_404_for_other_tenant(): void
    {
        [$user,, $version] = $this->makePublishedFormWithFields('user');
        $other = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($other);

        $response = $this->getJson('/api/forms/'.$version->form_id);

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Recurso não encontrado');
    }

    public function test_can_list_form_versions_for_tenant(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');
        // create an extra draft version for the same form
        FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 2,
            'is_published' => false,
            'published_at' => null,
            'created_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/forms/'.$form->id.'/versions');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $versions = $response->json('data.versions');
        $versionNumbers = array_column($versions, 'version_number');

        $this->assertContains(1, $versionNumbers);
        $this->assertContains(2, $versionNumbers);
    }

    public function test_list_form_versions_returns_404_for_form_from_other_tenant(): void
    {
        [$user, $form] = $this->makePublishedFormWithFields('user');
        $other = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($other);

        $response = $this->getJson('/api/forms/'.$form->id.'/versions');

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Recurso não encontrado');
    }

    public function test_can_list_fields_for_version_in_tenant(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $fields = $response->json('data.fields');
        $this->assertGreaterThanOrEqual(2, count($fields));
        $this->assertEquals($version->id, $fields[0]['form_version_id']);
    }

    public function test_list_fields_returns_404_for_version_from_other_tenant(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');
        $other = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($other);

        $response = $this->getJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields');

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Recurso não encontrado');
    }

    public function test_can_list_drafts_for_current_user_and_form(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');

        // create one draft for the user and one for another user (same tenant)
        $otherUser = User::factory()->create(['tenant_id' => $user->tenant_id, 'role' => 'user']);

        $draftForUser = FormSubmission::query()->create([
            'tenant_id' => $user->tenant_id,
            'form_version_id' => $version->id,
            'status' => 'draft',
            'submitted_by' => $user->id,
            'submitted_at' => null,
        ]);

        FormSubmission::query()->create([
            'tenant_id' => $user->tenant_id,
            'form_version_id' => $version->id,
            'status' => 'draft',
            'submitted_by' => $otherUser->id,
            'submitted_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/forms/'.$form->id.'/drafts');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $draftIds = array_column($response->json('data.drafts'), 'id');
        $this->assertContains($draftForUser->id, $draftIds);
        $this->assertCount(1, $draftIds);
    }

    public function test_submissions_listing_applies_filters_and_pagination(): void
    {
        [$manager, $form] = $this->makeSubmittedData('manager');
        // second submission older
        [$manager2, $form2, $submission2] = $this->makeSubmittedData('manager', $manager->tenant_id);
        $submission2->update(['submitted_at' => now()->subDays(10)]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/submissions?form_id='.$form->id.'&status=submitted&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.per_page', 1);

        $this->assertEquals(1, count($response->json('data.submissions')));
    }

    private function makePublishedFormWithFields(string $role = 'manager', ?int $tenantId = null): array
    {
        $user = User::factory()->create([
            'role' => $role,
            'tenant_id' => $tenantId ?? User::factory()->create()->tenant_id,
        ]);

        $form = Form::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Formulario '.uniqid(),
            'description' => 'Descricao',
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

        FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Nome Completo',
            'name' => 'nome_completo',
            'type' => 'text',
            'is_required' => true,
            'options' => null,
            'order' => 1,
        ]);

        FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Estado Civil',
            'name' => 'estado_civil',
            'type' => 'select',
            'is_required' => true,
            'options' => [
                ['value' => 'solteiro', 'label' => 'Solteiro'],
                ['value' => 'casado', 'label' => 'Casado'],
            ],
            'order' => 2,
        ]);

        return [$user, $form, $version];
    }

    private function makeSubmittedData(string $role = 'manager', ?int $tenantId = null): array
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields($role, $tenantId);

        $submission = FormSubmission::query()->create([
            'tenant_id' => $user->tenant_id,
            'form_version_id' => $version->id,
            'status' => 'submitted',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        FormSubmissionValue::query()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $version->fields[0]->id,
            'value' => 'Maria Santos',
        ]);

        return [$user, $form, $submission];
    }
}
