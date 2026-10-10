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

class CsvExportContractTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $user = User::factory()->create(['role' => 'manager', 'name' => 'Maria, "Silva"']);
        $form = Form::query()->create(['tenant_id' => $user->tenant_id, 'name' => 'Exportação', 'created_by' => $user->id, 'is_active' => true]);
        $ids = [];
        foreach ([1, 2] as $number) {
            $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => $number, 'is_published' => true, 'published_at' => now(), 'created_by' => $user->id]);
            $field = FormField::query()->create(['form_version_id' => $version->id, 'name' => 'obs', 'label' => 'Notas, "livres"', 'type' => 'textarea', 'is_required' => false, 'order' => 1]);
            $submission = FormSubmission::query()->create(['tenant_id' => $user->tenant_id, 'form_version_id' => $version->id, 'status' => 'submitted', 'submitted_by' => $user->id, 'submitted_at' => $number === 1 ? '2026-10-01 09:15:00' : '2026-10-10 17:45:00']);
            FormSubmissionValue::query()->create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => $number === 1 ? "Olá, \"mundo\"\nOutra linha" : 'Versão dois']);
            $ids[] = $version->id;
        }
        // Drafts and another tenant must never contribute rows or columns.
        FormSubmission::query()->create(['tenant_id' => $user->tenant_id, 'form_version_id' => $version->id, 'status' => 'draft', 'submitted_by' => $user->id]);
        Sanctum::actingAs($user);

        return [$user, $form, $ids];
    }

    private function rows(string $csv, string $separator = ','): array
    {
        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));
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

    public function test_csv_round_trip_preserves_quotes_newlines_dates_and_combines_copied_fields(): void
    {
        [, $form] = $this->data();
        $csv = $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertOk()->streamedContent();
        $rows = $this->rows($csv);
        $this->assertCount(3, $rows);
        $this->assertCount(6, $rows[0]);
        $this->assertSame('Notas, "livres"', $rows[0][5]);
        $this->assertSame('Maria, "Silva"', $rows[1][1]);
        $this->assertSame('10/10/2026 17:45', $rows[1][3]);
        $this->assertSame('2', $rows[1][4]);
        $this->assertSame('Versão dois', $rows[1][5]);
        $this->assertSame("Olá, \"mundo\"\nOutra linha", $rows[2][5]);
    }

    public function test_csv_filters_versions_and_dates_and_supports_semicolon(): void
    {
        [$user, $form, $versions] = $this->data();
        $csv = $this->postJson('/api/submissions/export', [
            'form_id' => $form->id, 'version_ids' => [$versions[1]], 'date_from' => '2026-10-10', 'date_to' => '2026-10-10', 'separator' => ';',
        ])->assertOk()->streamedContent();
        $rows = $this->rows($csv, ';');
        $this->assertCount(2, $rows);
        $this->assertSame('Versão dois', $rows[1][5]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'EXPORT_DATA', 'tenant_id' => $user->tenant_id, 'entity_id' => $form->id]);
        $this->postJson('/api/submissions/export', ['form_id' => $form->id, 'date_from' => '2027-01-01'])->assertUnprocessable();
        $this->postJson('/api/submissions/export', ['form_id' => $form->id, 'separator' => '|'])->assertUnprocessable();
    }

    public function test_csv_cannot_export_another_tenants_form(): void
    {
        [, $form] = $this->data();
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $this->postJson('/api/submissions/export', ['form_id' => $form->id])->assertUnprocessable();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'EXPORT_DATA', 'entity_id' => $form->id]);
    }
}
