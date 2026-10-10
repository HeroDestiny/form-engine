<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormVersion;
use App\Models\User;
use App\Repositories\Contracts\FormSubmissionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InputAndExportLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function exportData(int $count = 3, string $value = 'Texto'): array
    {
        $actor = User::factory()->create(['role' => 'manager']);
        $form = Form::query()->create(['tenant_id' => $actor->tenant_id, 'name' => 'Limites', 'created_by' => $actor->id, 'is_active' => true]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1, 'is_published' => true, 'published_at' => now(), 'created_by' => $actor->id]);
        $field = FormField::query()->create(['form_version_id' => $version->id, 'name' => 'texto', 'label' => 'Texto', 'type' => 'text', 'is_required' => false, 'order' => 1]);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $submission = FormSubmission::query()->create(['tenant_id' => $actor->tenant_id, 'form_version_id' => $version->id,
                'status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => '2026-10-01 12:00:00']);
            FormSubmissionValue::query()->create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => $value]);
            $ids[] = $submission->id;
        }
        Sanctum::actingAs($actor);

        return [$actor, $form, $version, $field, $ids];
    }

    private function rows(string $csv, string $separator = ','): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, $separator, '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public static function formulaPrefixes(): iterable
    {
        foreach (['=1+1', '+1+1', '-1+1', '@SUM(1)', "\t=1+1", "\r=1+1", "\n=1+1", '  =1+1', '＝1+1', "=1+1\";,x\nlinha"] as $index => $value) {
            yield "prefix $index" => [$value];
        }
    }

    #[DataProvider('formulaPrefixes')]
    public function test_formula_protection_applies_to_values_headers_users_and_choice_labels_without_changing_storage(string $value): void
    {
        [$actor, $form, , $field] = $this->exportData(1, $value);
        // Published rows are set up directly to represent existing historical data.
        $field->update(['label' => $value]);
        $actor->update(['name' => $value, 'email' => $value]);
        $csv = $this->postJson('/api/submissions/export', ['form_id' => $form->id, 'separator' => ';'])->assertOk()->streamedContent();
        $rows = $this->rows($csv, ';');
        $this->assertCount(2, $rows);
        $this->assertCount(6, $rows[1]);
        foreach ([$rows[0][5], $rows[1][1], $rows[1][2], $rows[1][5]] as $cell) {
            $this->assertSame("\t".$value, $cell);
        }
        $this->assertDatabaseHas('form_submission_values', ['form_field_id' => $field->id, 'value' => $value]);
        $field->update(['type' => 'select', 'options' => [['value' => $value, 'label' => $value]]]);
        $mapped = $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertOk()->streamedContent();
        $this->assertSame("\t".$value, $this->rows($mapped)[1][5]);
    }

    public function test_export_limits_reject_before_download_and_audit_and_accept_boundary(): void
    {
        [$actor, $form] = $this->exportData();
        config(['limits.export_rows' => 2]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertUnprocessable()->assertJsonPath('data', null);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'EXPORT_DATA']);
        config(['limits.export_rows' => 3]);
        $response = $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertOk();
        $csv = $response->streamedContent();
        $bytes = strlen($csv);
        $this->assertSame((string) $bytes, $response->headers->get('Content-Length'));
        $this->assertCount(4, $this->rows($csv));
        $log = AuditLog::query()->where('action', 'EXPORT_DATA')->first();
        $this->assertSame(3, $log->metadata['records_count']);
        $this->assertSame($bytes, $log->metadata['bytes']);
        config(['limits.export_bytes' => $bytes - 1]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertUnprocessable();
        $this->assertSame(1, AuditLog::query()->where('action', 'EXPORT_DATA')->count());
        config(['limits.export_bytes' => $bytes, 'limits.export_columns' => 5]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertUnprocessable();
        config(['limits.export_columns' => 6]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertOk()->streamedContent();
    }

    public function test_export_reads_in_bounded_batches_and_selection_excludes_later_submissions(): void
    {
        [$actor, $form, $version, , $ids] = $this->exportData(7);
        config(['limits.export_chunk_size' => 2]);
        $batches = [];
        DB::listen(function ($query) use (&$batches) {
            if (str_contains($query->sql, 'from "form_submission_values"')) {
                preg_match('/\bin\s*\(([^)]*)\)/i', $query->sql, $matches);
                $batches[] = substr_count($matches[1], ',') + 1;
            }
        });
        $csv = $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertOk()->streamedContent();
        $this->assertSame(array_map('strval', array_reverse($ids)), array_column(array_slice($this->rows($csv), 1), 0));
        $this->assertSame([2, 2, 2, 1], $batches);
        $repo = app(FormSubmissionRepositoryInterface::class);
        $selection = $repo->selectForExport($actor->tenant_id, $form->id, [], 8);
        FormSubmission::query()->create(['tenant_id' => $actor->tenant_id, 'form_version_id' => $version->id,
            'submitted_by' => $actor->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $selected = iterator_to_array($repo->iterateForExport($actor->tenant_id, $form->id, $selection['ids'], 2));
        $this->assertSame(array_reverse($ids), array_map(fn ($row) => $row->id, $selected));
    }

    public function test_text_limits_apply_to_submit_save_update_and_finalize_draft(): void
    {
        [, $form] = $this->exportData(0);
        $atLimit = str_repeat('á', 1000);
        $tooLong = $atLimit.'á';
        $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => ['texto' => $atLimit]])->assertCreated();
        foreach (['submit', 'drafts'] as $action) {
            $this->postJson('/api/forms/'.$form->id.'/'.$action, ['values' => ['texto' => $tooLong]])
                ->assertUnprocessable()->assertJsonValidationErrors('values.texto');
        }
        $draft = $this->postJson('/api/forms/'.$form->id.'/drafts', ['values' => ['texto' => $atLimit]])->assertCreated()->json('data.draft.id');
        $this->patchJson('/api/forms/'.$form->id.'/drafts/'.$draft, ['values' => ['texto' => $tooLong]])->assertUnprocessable();
        $this->postJson('/api/forms/'.$form->id.'/drafts/'.$draft.'/submit', ['values' => ['texto' => $tooLong]])->assertUnprocessable();
        $this->assertDatabaseHas('form_submissions', ['id' => $draft, 'status' => 'draft']);
        $this->assertDatabaseHas('form_submission_values', ['form_submission_id' => $draft, 'value' => $atLimit]);
    }

    public function test_structure_limits_prevent_extra_fields_but_allow_editing_existing_field(): void
    {
        $actor = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($actor);
        $data = $this->postJson('/api/forms', ['name' => 'Estrutura'])->assertCreated()->json('data');
        $path = '/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'].'/fields';
        $payload = ['name' => 'campo', 'label' => 'Campo', 'type' => 'checkbox', 'order' => 1, 'is_required' => false,
            'options' => [['value' => 'a', 'label' => 'A']]];
        config(['limits.fields_per_version' => 1, 'limits.options_per_field' => 1]);
        $field = $this->postJson($path, $payload)->assertCreated()->json('data.id');
        $this->postJson($path, [...$payload, 'name' => 'outro'])->assertUnprocessable();
        $this->putJson($path.'/'.$field, [...$payload, 'label' => 'Editado'])->assertOk();
        $this->putJson($path.'/'.$field, [...$payload, 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]])->assertUnprocessable();
        config(['limits.options_per_field' => 100]);
        $this->putJson($path.'/'.$field, [...$payload, 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'Duplicado']]])->assertUnprocessable();
        $this->postJson('/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'].'/publish')->assertOk();
        $this->postJson('/api/forms/'.$data['form']['id'].'/submit', ['values' => ['campo' => ['a', 'a']]])->assertUnprocessable();
    }

    public function test_large_request_body_is_rejected_before_business_mutation(): void
    {
        $actor = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($actor);
        $this->postJson('/api/forms', ['name' => 'Grande', 'description' => str_repeat('x', 2097153)])
            ->assertStatus(413)->assertJsonPath('success', false)->assertJsonPath('data', null);
        $this->assertDatabaseCount('forms', 0);
    }

    public function test_export_filter_validation_and_end_only_date(): void
    {
        [, $form, $version] = $this->exportData(1);
        foreach ([['version_ids' => [$version->id, $version->id]], ['form_id' => -1], ['version_ids' => [-1]],
            ['date_from' => 'tomorrow'], ['date_from' => '2026-10-02', 'date_to' => '2026-10-01']] as $payload) {
            $this->postJson('/api/submissions/export', ['form_id' => $form->id, ...$payload])->assertUnprocessable();
        }
        $this->postJson('/api/submissions/export', ['form_id' => $form->id, 'date_to' => '2026-10-01'])->assertOk()->streamedContent();
    }

    public function test_other_field_types_and_value_count_have_bounds(): void
    {
        [, $form, , $field] = $this->exportData(0);
        foreach ([['textarea', str_repeat('x', 10000), str_repeat('x', 10001)],
            ['number', '1000000000000000', '1000000000000001'],
            ['date', '2026-10-10', 'tomorrow']] as [$type, $valid, $invalid]) {
            $field->update(['type' => $type]);
            $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => ['texto' => $valid]])->assertCreated();
            $this->postJson('/api/forms/'.$form->id.'/submit', ['values' => ['texto' => $invalid]])->assertUnprocessable()->assertJsonValidationErrors('values.texto');
        }
        $field->update(['type' => 'text']);
        $values = array_fill_keys(array_map(fn ($i) => 'campo'.$i, range(1, 101)), 'x');
        $this->postJson('/api/forms/'.$form->id.'/drafts', ['values' => $values])->assertUnprocessable()->assertJsonValidationErrors('values');
    }

    public function test_export_version_limit_and_header_byte_limit_do_not_record_success(): void
    {
        [, $form, $version] = $this->exportData(1);
        $second = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 2, 'is_published' => true,
            'published_at' => now(), 'created_by' => $version->created_by]);
        FormSubmission::query()->create(['tenant_id' => $form->tenant_id, 'form_version_id' => $second->id,
            'submitted_by' => $version->created_by, 'status' => 'submitted', 'submitted_at' => now()]);
        config(['limits.export_versions' => 1]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertUnprocessable();
        config(['limits.export_versions' => 100, 'limits.export_bytes' => 1]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertUnprocessable();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'EXPORT_DATA']);
    }
}
