<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormFlowUseCasesContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_uc10_manager_can_create_new_version_from_published_copying_fields(): void
    {
        [$manager, $form, $publishedVersion] = $this->makePublishedFormWithFields();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions');

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version.version_number', 2)
            ->assertJsonPath('data.version.is_published', false)
            ->assertJsonPath('data.fields_copied', 2);

        $this->assertDatabaseHas('form_versions', [
            'form_id' => $form->id,
            'version_number' => 2,
            'is_published' => false,
        ]);
    }

    public function test_uc10_rejects_when_draft_already_exists(): void
    {
        [$manager, $form] = $this->makePublishedFormWithFields();

        FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 2,
            'is_published' => false,
            'published_at' => null,
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions');

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Já existe uma versão em edição');
    }

    public function test_uc11_manager_can_toggle_form_status(): void
    {
        [$manager, $form] = $this->makePublishedFormWithFields();
        Sanctum::actingAs($manager);

        $response = $this->patchJson('/api/forms/'.$form->id.'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $form->id)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Formulário desativado com sucesso');
    }

    public function test_uc12_lists_only_active_forms_with_published_versions_from_same_tenant(): void
    {
        [$user, $activePublishedForm] = $this->makePublishedFormWithFields('user');
        [$manager, $inactiveForm] = $this->makePublishedFormWithFields('manager', $user->tenant_id);
        $inactiveForm->update(['is_active' => false]);

        [, $otherTenantForm] = $this->makePublishedFormWithFields('user');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/forms');

        $response->assertOk()->assertJsonPath('success', true);

        $forms = $response->json('data.forms');
        $formIds = array_column($forms, 'id');

        $this->assertContains($activePublishedForm->id, $formIds);
        $this->assertNotContains($inactiveForm->id, $formIds);
        $this->assertNotContains($otherTenantForm->id, $formIds);
    }

    public function test_uc13_can_submit_form_with_required_fields(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms/'.$form->id.'/submit', [
            'values' => [
                'nome_completo' => 'Maria Santos',
                'estado_civil' => 'solteiro',
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission.form_version_id', $version->id)
            ->assertJsonPath('message', 'Formulário enviado com sucesso!');

        $this->assertDatabaseCount('form_submissions', 1);
        $this->assertDatabaseCount('form_submission_values', 2);
    }

    public function test_uc13_rejects_submission_missing_required_field(): void
    {
        [$user, $form] = $this->makePublishedFormWithFields('user');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms/'.$form->id.'/submit', [
            'values' => [
                'estado_civil' => 'solteiro',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['values.nome_completo']);
    }

    public function test_uc13_rejects_when_form_is_inactive(): void
    {
        [$user, $form] = $this->makePublishedFormWithFields('user');
        $form->update(['is_active' => false]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms/'.$form->id.'/submit', [
            'values' => [
                'nome_completo' => 'Maria Santos',
                'estado_civil' => 'solteiro',
            ],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Este formulário não está mais disponível');
    }

    public function test_uc14_can_save_draft_with_partial_values(): void
    {
        [$user, $form, $version] = $this->makePublishedFormWithFields('user');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms/'.$form->id.'/drafts', [
            'values' => [
                'nome_completo' => 'Maria Santos',
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.draft.form_version_id', $version->id)
            ->assertJsonPath('data.draft.status', 'draft');

        $this->assertDatabaseHas('form_submissions', [
            'form_version_id' => $version->id,
            'submitted_by' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_uc14_validates_type_even_on_draft(): void
    {
        [$user, $form] = $this->makePublishedFormWithFields('user');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms/'.$form->id.'/drafts', [
            'values' => [
                'nome_completo' => 'Maria Santos',
                'estado_civil' => 123,
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['values.estado_civil']);
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
}
