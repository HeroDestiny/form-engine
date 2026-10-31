<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConditionalFieldContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_field_with_conditional_rule(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $form = Form::query()->create([
            'tenant_id' => $manager->tenant_id,
            'name' => 'Formulário Condicional',
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

        // 1. Campo Gatilho (Parent)
        $this->postJson("/api/forms/{$form->id}/versions/{$version->id}/fields", [
            'label' => 'Possui Veículo?',
            'name' => 'possui_veiculo',
            'type' => 'radio',
            'is_required' => true,
            'order' => 1,
            'options' => [
                ['value' => 'sim', 'label' => 'Sim'],
                ['value' => 'nao', 'label' => 'Não'],
            ],
        ])->assertCreated();

        // 2. Campo Dependente com Regra Condicional
        $response = $this->postJson("/api/forms/{$form->id}/versions/{$version->id}/fields", [
            'label' => 'Placa do Veículo',
            'name' => 'placa_veiculo',
            'type' => 'text',
            'is_required' => true,
            'order' => 2,
            'condition' => [
                'field' => 'possui_veiculo',
                'operator' => 'equals',
                'value' => 'sim',
            ],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $field = FormField::query()->where('name', 'placa_veiculo')->first();
        $this->assertNotNull($field);
        $this->assertSame('possui_veiculo', $field->options['condition']['field']);
        $this->assertSame('equals', $field->options['condition']['operator']);
        $this->assertSame('sim', $field->options['condition']['value']);
    }

    public function test_conditional_field_is_ignored_when_condition_is_not_met(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Formulário Transporte',
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
            'label' => 'Possui Veículo?',
            'name' => 'possui_veiculo',
            'type' => 'radio',
            'is_required' => true,
            'order' => 1,
            'options' => [
                ['value' => 'sim', 'label' => 'Sim'],
                ['value' => 'nao', 'label' => 'Não'],
            ],
        ]);

        FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Placa do Veículo',
            'name' => 'placa_veiculo',
            'type' => 'text',
            'is_required' => true, // Mesmo sendo obrigatório, só deve ser cobrado se possui_veiculo == sim
            'order' => 2,
            'options' => [
                'condition' => [
                    'field' => 'possui_veiculo',
                    'operator' => 'equals',
                    'value' => 'sim',
                ],
            ],
        ]);

        Sanctum::actingAs($user);

        // Cenário A: Usuário responde "nao" e não envia "placa_veiculo" -> DEVE PASSAR!
        $resA = $this->postJson("/api/forms/{$form->id}/submit", [
            'values' => [
                'possui_veiculo' => 'nao',
            ],
        ])->assertCreated();

        $submissionAId = $resA->json('data.submission.id');
        $this->assertDatabaseMissing('form_submission_values', [
            'form_submission_id' => $submissionAId,
            'value' => 'placa_veiculo',
        ]);

        // Cenário B: Usuário responde "nao", mas envia "placa_veiculo" -> Não deve persistir a placa órfã
        $resB = $this->postJson("/api/forms/{$form->id}/submit", [
            'values' => [
                'possui_veiculo' => 'nao',
                'placa_veiculo' => 'ABC-1234',
            ],
        ])->assertCreated();

        $submissionBId = $resB->json('data.submission.id');
        $placaField = FormField::query()->where('name', 'placa_veiculo')->first();
        $this->assertDatabaseMissing('form_submission_values', [
            'form_submission_id' => $submissionBId,
            'form_field_id' => $placaField->id,
        ]);

        // Cenário C: Usuário responde "sim", mas NÃO envia "placa_veiculo" -> DEVE FALHAR (422)
        $this->postJson("/api/forms/{$form->id}/submit", [
            'values' => [
                'possui_veiculo' => 'sim',
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('values.placa_veiculo');

        // Cenário D: Usuário responde "sim" e envia a "placa_veiculo" -> DEVE PASSAR E PERSISTIR
        $resD = $this->postJson("/api/forms/{$form->id}/submit", [
            'values' => [
                'possui_veiculo' => 'sim',
                'placa_veiculo' => 'XYZ-9876',
            ],
        ])->assertCreated();

        $submissionDId = $resD->json('data.submission.id');
        $this->assertDatabaseHas('form_submission_values', [
            'form_submission_id' => $submissionDId,
            'form_field_id' => $placaField->id,
            'value' => 'XYZ-9876',
        ]);

        // Detalhes da submissão incluem a condição
        $details = $this->getJson("/api/submissions/{$submissionDId}")->assertOk()->json('data.values');
        $fieldDetails = collect($details)->firstWhere('name', 'placa_veiculo');
        $this->assertSame('XYZ-9876', $fieldDetails['value']);
        $this->assertSame('possui_veiculo', $fieldDetails['condition']['field']);
    }
}
