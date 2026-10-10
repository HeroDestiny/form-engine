<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmissionValue;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DynamicFieldValidationContractTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidValues(): array
    {
        return [
            'email format' => ['email', 'invalido', 'submit'],
            'number type' => ['number', 'texto', 'submit'],
            'date format' => ['date', 'invalido', 'submit'],
            'text type' => ['text', ['texto'], 'submit'],
            'select option' => ['select', 'fora', 'submit'],
            'radio option' => ['radio', 'fora', 'submit'],
            'checkbox scalar' => ['checkbox', 'a,b', 'submit'],
            'checkbox option' => ['checkbox', ['a', 'fora'], 'submit'],
            'checkbox nested' => ['checkbox', [['a']], 'submit'],
            'checkbox object' => ['checkbox', ['choice' => 'a'], 'submit'],
            'required checkbox empty' => ['checkbox', [], 'submit'],
            'draft email format' => ['email', 'invalido', 'drafts'],
            'draft checkbox option' => ['checkbox', ['fora'], 'drafts'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_dynamic_value_is_rejected_without_persisting(string $type, mixed $value, string $operation): void
    {
        [$user, $form] = $this->makeForm([$type => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms/'.$form->id.'/'.$operation, ['values' => [$type => $value]]);
        $response->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertNotEmpty($response->json('errors'));
        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('form_submission_values', 0);
    }

    public function test_all_field_types_are_persisted_and_checkbox_choices_are_recovered_and_exported(): void
    {
        $values = [
            'text' => 'Maria', 'textarea' => 'Observações', 'email' => 'maria@example.com',
            'number' => 42, 'date' => '2026-10-10', 'select' => 'a', 'radio' => 'b',
            'checkbox' => ['a', 'b'],
        ];
        [$user, $form] = $this->makeForm(array_fill_keys(array_keys($values), true));
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => $values])
            ->assertCreated()->json('data.submission.id');

        $details = $this->getJson('/api/submissions/'.$id)->assertOk()->json('data.values');
        $byName = array_column($details, null, 'name');
        foreach ($values as $name => $value) {
            $this->assertSame(is_array($value) ? $value : (string) $value, $byName[$name]['value']);
        }
        $this->assertDatabaseHas('form_submission_values', [
            'form_submission_id' => $id, 'form_field_id' => $byName['checkbox']['field_id'],
            'value' => '["a","b"]',
        ]);

        $csv = $this->postJson('/api/submissions/export', ['form_id' => $form->id])
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Opção A; Opção B', $csv);
    }

    public function test_optional_empty_values_are_accepted_and_required_fields_can_be_omitted_in_draft(): void
    {
        [$user, $form] = $this->makeForm(['email' => false, 'number' => false, 'checkbox' => false]);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/forms/'.$form->id.'/submit', [
            'values' => ['email' => '', 'number' => null, 'checkbox' => []],
        ])->assertCreated()->json('data.submission.id');
        $details = $this->getJson('/api/submissions/'.$id)->assertOk()->json('data.values');
        $byName = array_column($details, null, 'name');
        $this->assertNull($byName['email']['value']);
        $this->assertNull($byName['number']['value']);
        $this->assertSame([], $byName['checkbox']['value']);

        [$draftUser, $draftForm] = $this->makeForm(['email' => true, 'checkbox' => true]);
        Sanctum::actingAs($draftUser);
        $this->postJson('/api/forms/'.$draftForm->id.'/drafts', ['values' => []])->assertCreated();
    }

    public function test_legacy_checkbox_values_remain_readable(): void
    {
        [$user, $form] = $this->makeForm(['checkbox' => true]);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => ['checkbox' => ['a', 'b']]])
            ->assertCreated()->json('data.submission.id');
        FormSubmissionValue::query()->where('form_submission_id', $id)->update(['value' => 'a,b']);

        $this->getJson('/api/submissions/'.$id)->assertOk()->assertJsonPath('data.values.0.value', ['a', 'b']);
    }

    private function makeForm(array $fields): array
    {
        $user = User::factory()->create(['role' => 'manager']);
        $form = Form::query()->create([
            'tenant_id' => $user->tenant_id, 'name' => 'Validação dinâmica',
            'is_active' => true, 'created_by' => $user->id,
        ]);
        $version = FormVersion::query()->create([
            'form_id' => $form->id, 'version_number' => 1, 'is_published' => true,
            'published_at' => now(), 'created_by' => $user->id,
        ]);
        $order = 0;
        foreach ($fields as $type => $required) {
            FormField::query()->create([
                'form_version_id' => $version->id, 'name' => $type, 'label' => $type,
                'type' => $type, 'is_required' => $required, 'order' => ++$order,
                'options' => in_array($type, ['select', 'radio', 'checkbox'], true) ? [
                    ['value' => 'a', 'label' => 'Opção A'], ['value' => 'b', 'label' => 'Opção B'],
                ] : null,
            ]);
        }

        return [$user, $form];
    }
}
