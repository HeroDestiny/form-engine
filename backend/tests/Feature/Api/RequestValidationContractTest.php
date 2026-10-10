<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestValidationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_from_another_tenant_is_hidden_before_validating_values(): void
    {
        $owner = User::factory()->create();
        $form = $this->makeForm($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => 'invalid'])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors', null);

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_field_from_another_tenant_is_hidden_before_validating_payload(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $form = $this->makeForm($owner);
        $version = $this->makeVersion($form, $owner);
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));

        $this->postJson('/api/forms/'.$form->id.'/versions/'.$version->id.'/fields', [])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', null);

        $this->assertDatabaseCount('form_fields', 0);
    }

    public function test_draft_without_a_published_version_returns_a_domain_error(): void
    {
        $user = User::factory()->create();
        $form = $this->makeForm($user);
        $this->makeVersion($form, $user);
        Sanctum::actingAs($user);

        $this->postJson('/api/forms/'.$form->id.'/drafts', ['values' => []])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Versão não pode ser publicada')
            ->assertJsonPath('errors', null);

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_submission_values_must_be_an_object_or_array(): void
    {
        $user = User::factory()->create();
        $form = $this->makeForm($user);
        $this->makeVersion($form, $user, true);
        Sanctum::actingAs($user);

        $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Erro de validação')
            ->assertJsonValidationErrors('values');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    private function makeForm(User $user): Form
    {
        return Form::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Formulário de validação',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }

    private function makeVersion(Form $form, User $user, bool $published = false): FormVersion
    {
        return FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 1,
            'is_published' => $published,
            'published_at' => $published ? now() : null,
            'created_by' => $user->id,
        ]);
    }
}
