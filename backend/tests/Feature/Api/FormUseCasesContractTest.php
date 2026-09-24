<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormUseCasesContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_uc05_manager_can_create_form_and_initial_draft_version(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/tenants/'.$manager->tenant_id.'/forms', [
            'name' => 'Cadastro de Beneficiarios',
            'description' => 'Descricao de teste',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.form.tenant_id', $manager->tenant_id)
            ->assertJsonPath('data.form.is_active', true)
            ->assertJsonPath('data.version.version_number', 1)
            ->assertJsonPath('data.version.is_published', false);
    }

    public function test_uc05_rejects_duplicate_form_name_in_same_tenant(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Form::query()->create([
            'tenant_id' => $manager->tenant_id,
            'name' => 'Cadastro Unico',
            'description' => null,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/tenants/'.$manager->tenant_id.'/forms', [
            'name' => 'Cadastro Unico',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_uc05_allows_same_form_name_in_different_tenants(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $otherTenant = Tenant::factory()->create();

        Form::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cadastro Repetido',
            'description' => null,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/tenants/'.$manager->tenant_id.'/forms', [
            'name' => 'Cadastro Repetido',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_uc05_user_role_cannot_create_form(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenants/'.$user->tenant_id.'/forms', [
            'name' => 'Nao Deve Criar',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_uc06_manager_can_add_text_field_to_draft_version(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields', [
            'label' => 'Nome Completo',
            'name' => 'nome_completo',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.form_version_id', $version->id)
            ->assertJsonPath('data.name', 'nome_completo')
            ->assertJsonPath('data.type', 'text');
    }

    public function test_uc06_rejects_select_field_without_options(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields', [
            'label' => 'Estado Civil',
            'name' => 'estado_civil',
            'type' => 'select',
            'is_required' => true,
            'order' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['options']);
    }

    public function test_uc06_rejects_add_field_to_published_version(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        $version->update(['is_published' => true, 'published_at' => now()]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields', [
            'label' => 'Nome',
            'name' => 'nome',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Versão não pode ser editada');
    }

    public function test_uc07_manager_can_edit_field_in_draft_version(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        $field = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Nome',
            'name' => 'nome',
            'type' => 'text',
            'is_required' => true,
            'options' => null,
            'order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields/'.$field->id, [
            'label' => 'Nome Completo',
            'name' => 'nome_completo',
            'type' => 'text',
            'is_required' => true,
            'order' => 2,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'Nome Completo')
            ->assertJsonPath('data.name', 'nome_completo')
            ->assertJsonPath('data.order', 2);
    }

    public function test_uc08_manager_can_remove_field_in_draft_version(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        $field = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Remover',
            'name' => 'remover',
            'type' => 'text',
            'is_required' => false,
            'options' => null,
            'order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->deleteJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields/'.$field->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Campo removido com sucesso');

        $this->assertDatabaseMissing('form_fields', ['id' => $field->id]);
    }

    public function test_uc08_rejects_remove_field_in_published_version(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        $version->update(['is_published' => true, 'published_at' => now()]);
        $field = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Nao Remove',
            'name' => 'nao_remove',
            'type' => 'text',
            'is_required' => false,
            'options' => null,
            'order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->deleteJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields/'.$field->id);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Campos de versões publicadas não podem ser removidos');
    }

    public function test_uc09_manager_can_publish_version_with_fields(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Nome',
            'name' => 'nome',
            'type' => 'text',
            'is_required' => true,
            'options' => null,
            'order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/publish');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $version->id)
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.fields.0.name', 'nome');
    }

    public function test_uc09_rejects_publish_without_fields(): void
    {
        [$manager, $form, $version] = $this->makeDraftVersion();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/publish');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Versão não pode ser publicada');
    }

    private function makeDraftVersion(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $form = Form::query()->create([
            'tenant_id' => $manager->tenant_id,
            'name' => 'Form '.uniqid(),
            'description' => null,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        $version = FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 1,
            'is_published' => false,
            'published_at' => null,
            'created_by' => $manager->id,
        ]);

        return [$manager, $form, $version];
    }
}
