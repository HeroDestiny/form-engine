<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormLayoutContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_field_with_section_and_layout_width(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $form = Form::query()->create([
            'tenant_id' => $manager->tenant_id,
            'name' => 'Formulário com Layout',
            'is_active' => true,
            'created_by' => $manager->id,
        ]);
        $version = FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 1,
            'is_published' => false,
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        // Create field with section and half width
        $response = $this->postJson("/api/forms/{$form->id}/versions/{$version->id}/fields", [
            'label' => 'CEP Residencial',
            'name' => 'cep',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
            'section' => 'Endereço Completo',
            'section_description' => 'Dados de localização do solicitante',
            'width' => 'half',
            'layout_mode' => 'wizard',
        ]);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertSame('Endereço Completo', $data['options']['section']);
        $this->assertSame('Dados de localização do solicitante', $data['options']['section_description']);
        $this->assertSame('half', $data['options']['width']);
        $this->assertSame('wizard', $data['options']['layout_mode']);

        // Verify list fields returns options
        $listResponse = $this->getJson("/api/forms/{$form->id}/versions/{$version->id}/fields");
        $listResponse->assertOk();
        $this->assertSame('half', $listResponse->json('data.fields.0.options.width'));
        $this->assertSame('Endereço Completo', $listResponse->json('data.fields.0.options.section'));
    }

    public function test_submission_details_includes_section_and_width_metadata(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Formulário Ativo',
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

        $field = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Logradouro',
            'name' => 'logradouro',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
            'options' => [
                'section' => 'Endereço',
                'section_description' => 'Logradouro e número',
                'width' => 'half',
            ],
        ]);

        $submission = FormSubmission::query()->create([
            'tenant_id' => $user->tenant_id,
            'form_version_id' => $version->id,
            'submitted_by' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => 'Av. Paulista, 1000',
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/submissions/{$submission->id}");

        $response->assertOk();
        $item = $response->json('data.values.0');
        $this->assertSame('Endereço', $item['section']);
        $this->assertSame('Logradouro e número', $item['section_description']);
        $this->assertSame('half', $item['width']);
    }
}
