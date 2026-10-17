<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ComprehensiveAcceptanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function published(string $type, ?array $options = null): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $data = $this->postJson('/api/forms', ['name' => 'Review '.uniqid()])->assertCreated()->json('data');
        $path = '/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'];
        $payload = ['name' => 'answer', 'label' => 'Answer', 'type' => $type, 'order' => 1, 'is_required' => true];
        if ($options !== null) {
            $payload['options'] = $options;
        }
        $this->postJson($path.'/fields', $payload)->assertCreated();
        $this->postJson($path.'/publish')->assertOk();

        return $data;
    }

    public function test_numeric_choice_codes_keep_the_selected_label_in_details(): void
    {
        $data = $this->published('select', [['value' => '01', 'label' => 'First'], ['value' => '1', 'label' => 'Second']]);
        $id = $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => '1']])
            ->assertCreated()->json('data.submission.id');
        $this->getJson('/api/submissions/'.$id)->assertOk()->assertJsonPath('data.values.0.value_label', 'Second');
    }

    public static function choiceTypes(): array
    {
        return [['select'], ['radio'], ['checkbox']];
    }

    #[DataProvider('choiceTypes')]
    public function test_unlisted_numeric_choice_code_is_rejected(string $type): void
    {
        $data = $this->published($type, [['value' => '1', 'label' => 'One']]);
        $response = $this->postJson('/api/forms/'.$data['form']['id'].'/submit', [
            'values' => ['answer' => $type === 'checkbox' ? ['001'] : '001'],
        ]);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_export_date_outside_database_calendar_is_validated(): void
    {
        $data = $this->published('text');
        $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => 'valid']])->assertCreated();
        $response = $this->postJson('/api/submissions/export', ['form_id' => $data['form']['id'], 'date_from' => '0000-01-01']);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_numeric_choice_codes_keep_the_selected_label_in_csv(): void
    {
        $data = $this->published('checkbox', [['value' => '01', 'label' => 'First'], ['value' => '1', 'label' => 'Second']]);
        $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => ['1']]])->assertCreated();
        $csv = $this->postJson('/api/submissions/export', ['form_id' => $data['form']['id']])->assertOk()->streamedContent();
        $this->assertStringContainsString('Second', $csv);
    }

    public function test_checkbox_details_include_human_readable_selected_labels(): void
    {
        $data = $this->published('checkbox', [['value' => 'a', 'label' => 'Human readable choice']]);
        $id = $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => ['a']]])
            ->assertCreated()->json('data.submission.id');
        $values = $this->getJson('/api/submissions/'.$id)->assertOk()->json('data.values');
        $this->assertStringContainsString('Human readable choice', json_encode($values));
    }

    public function test_date_details_include_the_documented_formatted_value(): void
    {
        $data = $this->published('date');
        $id = $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => '2026-10-17']])
            ->assertCreated()->json('data.submission.id');
        $this->getJson('/api/submissions/'.$id)->assertOk()->assertJsonPath('data.values.0.value_formatted', '17/10/2026');
    }

    public static function queryBoundaries(): iterable
    {
        foreach (['/api/submissions', '/api/audit-logs'] as $path) {
            foreach (['date_from', 'date_to'] as $key) {
                yield "$path $key year zero" => [$path, [$key => '0000-01-01']];
            }
        }
    }

    #[DataProvider('queryBoundaries')]
    public function test_query_dates_outside_database_calendar_are_validated(string $path, array $query): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->getJson($path.'?'.http_build_query($query));
        $this->assertSame(422, $response->status(), $path.' '.json_encode($query));
    }

    public function test_nul_in_business_text_does_not_silently_change_persisted_value(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->postJson('/api/forms', ['name' => "Review\0suffix"]);
        $stored = DB::table('forms')->value('name');
        $this->assertSame(422, $response->status(), 'Stored name hex: '.bin2hex((string) $stored));
    }

    public function test_nul_in_submission_text_is_validated(): void
    {
        $data = $this->published('text');
        $response = $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => "prefix\0suffix"]]);
        $stored = DB::table('form_submission_values')->value('value');
        $this->assertSame(422, $response->status(), 'Stored answer hex: '.bin2hex((string) $stored));
    }

    public function test_unknown_route_uses_the_documented_error_envelope(): void
    {
        $this->getJson('/api/review-nonexistent')->assertNotFound()->assertJsonPath('success', false)
            ->assertJsonPath('data', null)->assertJsonPath('errors', null);
    }

    public function test_denied_identifier_with_real_token_is_audited(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('review')->plainTextToken;
        $this->withToken($token)->getJson('/api/forms/abc')->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'ACCESS_DENIED', 'user_id' => $user->id]);
    }

    public function test_logout_revocation_is_audited_as_documented(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('review')->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGOUT', 'user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
    }

    public function test_wrong_http_method_uses_the_documented_error_envelope(): void
    {
        $this->deleteJson('/api/auth/login')->assertStatus(405)->assertJsonPath('success', false)->assertHeader('Allow', 'POST');
    }

    public static function invalidJsonBodies(): array
    {
        return [['{"values":'], ['null'], ['true'], ['42'], ['"text"'], ['[{"values":{}}]']];
    }

    #[DataProvider('invalidJsonBodies')]
    public function test_invalid_json_does_not_create_an_empty_draft(string $body): void
    {
        $data = $this->published('text');
        $response = $this->call('POST', '/api/forms/'.$data['form']['id'].'/drafts', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $body);
        $this->assertSame(400, $response->status(), 'Persisted submissions: '.DB::table('form_submissions')->count());
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_logout_rolls_back_token_revocation_when_audit_fails(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('review');
        $this->mock(AuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->withToken($token->plainTextToken)->postJson('/api/auth/logout')->assertStatus(500)
            ->assertJsonPath('success', false)->assertJsonPath('errors', null)->assertDontSee('Audit unavailable');
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_unrelated_unique_constraint_is_not_misreported_as_business_validation(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        DB::statement('CREATE UNIQUE INDEX test_description_unique ON forms (description)');
        $description = '"users_tenant_id_email_unique"';
        $this->postJson('/api/forms', ['name' => 'First form', 'description' => $description])->assertCreated();
        $this->postJson('/api/forms', ['name' => 'Second form', 'description' => $description])->assertStatus(500)
            ->assertJsonPath('success', false)->assertJsonPath('errors', null);
        $this->assertDatabaseCount('forms', 1);
    }

    public function test_nul_in_nested_options_keys_and_filters_is_rejected_without_mutation(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $data = $this->postJson('/api/forms', ['name' => 'Nested NUL'])->assertCreated()->json('data');
        $path = '/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'].'/fields';
        $this->postJson($path, ['name' => 'choice', 'label' => 'Choice', 'type' => 'select', 'is_required' => false,
            'order' => 1, 'options' => [['value' => 'a', 'label' => "Bad\0label"]]])->assertUnprocessable()
            ->assertJsonValidationErrors('options.0.label');
        $this->postJson('/api/forms', ["bad\0key" => 'value'])->assertUnprocessable()->assertJsonValidationErrors('payload');
        $this->getJson('/api/forms?'.http_build_query(['search' => "Bad\0filter"]))->assertUnprocessable();
        $this->assertDatabaseCount('forms', 1);
        $this->assertDatabaseCount('form_fields', 0);
    }

    public static function calendarBoundaries(): array
    {
        return [['0000-01-01', 422], ['0001-01-01', 201], ['9999-12-31', 201],
            ['2026-02-29', 422], ['2024-02-29', 201], ['10000-01-01', 422]];
    }

    #[DataProvider('calendarBoundaries')]
    public function test_dynamic_dates_validate_the_calendar_and_preserve_valid_values(string $date, int $status): void
    {
        $data = $this->published('date');
        $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['answer' => $date]])->assertStatus($status);
        if ($status === 201) {
            $this->assertDatabaseHas('form_submission_values', ['value' => $date]);
        } else {
            $this->assertDatabaseCount('form_submissions', 0);
        }
    }

    public function test_audit_dates_cannot_overflow_the_utc_calendar_via_timezone_offset(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['0001-01-01T00:00:00+03:00', '9999-12-31T23:30:00-03:00'] as $date) {
            $this->getJson('/api/audit-logs?'.http_build_query(['date_from' => $date]))
                ->assertUnprocessable()->assertJsonValidationErrors('date_from');
        }
    }
}
