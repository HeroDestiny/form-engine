<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_manager_can_create_file_field_on_form_version(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $form = Form::query()->create([
            'tenant_id' => $manager->tenant_id,
            'name' => 'Formulário com Anexo',
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

        $response = $this->postJson("/api/forms/{$form->id}/versions/{$version->id}/fields", [
            'label' => 'Comprovante de Residência',
            'name' => 'comprovante_residencia',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
            'options' => [
                ['value' => 'pdf', 'label' => 'Documento PDF'],
                ['value' => 'png', 'label' => 'Imagem PNG'],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('form_fields', [
            'form_version_id' => $version->id,
            'name' => 'comprovante_residencia',
            'type' => 'file',
            'is_required' => true,
        ]);
    }

    public function test_user_can_upload_attachment_and_submit_form(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $tenantId = $manager->tenant_id;
        $user = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'user']);

        $form = Form::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Formulário de Cadastro',
            'is_active' => true,
            'created_by' => $manager->id,
        ]);
        $version = FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => 1,
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $manager->id,
        ]);
        $field = FormField::query()->create([
            'form_version_id' => $version->id,
            'label' => 'Anexo de Documento',
            'name' => 'documento',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
            'options' => [
                ['value' => 'pdf', 'label' => 'PDF'],
                ['value' => 'jpg', 'label' => 'JPG'],
            ],
        ]);

        Sanctum::actingAs($user);

        // 1. Rejeita extensão não permitida
        $txtFile = UploadedFile::fake()->create('documento.txt', 100, 'text/plain');
        $this->postJson("/api/forms/{$form->id}/attachments", [
            'field_name' => 'documento',
            'file' => $txtFile,
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        // 2. Rejeita extensão proibida / perigosa
        $phpFile = UploadedFile::fake()->create('script.php', 100, 'application/x-php');
        $this->postJson("/api/forms/{$form->id}/attachments", [
            'field_name' => 'documento',
            'file' => $phpFile,
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        // 3. Upload com sucesso de arquivo permitido
        $pdfFile = UploadedFile::fake()->create('relatorio.pdf', 350, 'application/pdf');
        $uploadRes = $this->postJson("/api/forms/{$form->id}/attachments", [
            'field_name' => 'documento',
            'file' => $pdfFile,
        ])->assertCreated()->assertJsonPath('success', true);

        $attachmentData = $uploadRes->json('data');
        $this->assertNotEmpty($attachmentData['path']);
        $this->assertSame('relatorio.pdf', $attachmentData['name']);
        Storage::disk('local')->assertExists($attachmentData['path']);

        // 4. Submete o formulário com o anexo
        $submitRes = $this->postJson("/api/forms/{$form->id}/submit", [
            'values' => [
                'documento' => $attachmentData,
            ],
        ])->assertCreated()->assertJsonPath('success', true);

        $submissionId = $submitRes->json('data.submission.id');

        // 5. Verifica detalhes da submissão
        $detailsRes = $this->getJson("/api/submissions/{$submissionId}")->assertOk();
        $fieldDetails = collect($detailsRes->json('data.values'))->firstWhere('field_id', $field->id);
        $this->assertSame('relatorio.pdf', $fieldDetails['file_name']);
        $this->assertStringContainsString("/api/submissions/{$submissionId}/attachments/{$field->id}", $fieldDetails['download_url']);

        // 6. Download pelo próprio autor (role user)
        $downloadRes = $this->get("/api/submissions/{$submissionId}/attachments/{$field->id}");
        $downloadRes->assertOk();
        $this->assertStringContainsString('filename=relatorio.pdf', $downloadRes->headers->get('content-disposition'));

        // 7. Download por gestor do mesmo tenant
        Sanctum::actingAs($manager);
        $this->get("/api/submissions/{$submissionId}/attachments/{$field->id}")->assertOk();

        // 8. Tentativa de download por usuário comum de outro registro no mesmo tenant
        $otherUserInSameTenant = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'user']);
        Sanctum::actingAs($otherUserInSameTenant);
        $this->get("/api/submissions/{$submissionId}/attachments/{$field->id}")->assertNotFound();

        // 9. Tentativa de acesso por usuário de outro tenant
        $otherTenant = Tenant::factory()->create();
        $userOtherTenant = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'manager']);
        Sanctum::actingAs($userOtherTenant);
        $this->get("/api/submissions/{$submissionId}/attachments/{$field->id}")->assertNotFound();

        // 10. CSV export inclui o nome do anexo
        Sanctum::actingAs($manager);
        $csvRes = $this->postJson('/api/submissions/export', [
            'form_id' => $form->id,
        ])->assertOk()->streamedContent();
        $this->assertStringContainsString('relatorio.pdf', $csvRes);
    }

    public function test_attachment_works_with_draft_lifecycle(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Formulário Rascunho Anexo',
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
            'label' => 'Foto Documento',
            'name' => 'foto',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
        ]);

        Sanctum::actingAs($user);

        // Upload de imagem
        $png = UploadedFile::fake()->create('foto.png', 150, 'image/png');
        $upload = $this->postJson("/api/forms/{$form->id}/attachments", [
            'field_name' => 'foto',
            'file' => $png,
        ])->assertCreated()->json('data');

        // Salva rascunho
        $draftRes = $this->postJson("/api/forms/{$form->id}/drafts", [
            'values' => ['foto' => $upload],
        ])->assertCreated();

        $draftId = $draftRes->json('data.draft.id');

        // Atualiza rascunho
        $this->patchJson("/api/forms/{$form->id}/drafts/{$draftId}", [
            'values' => ['foto' => $upload],
        ])->assertOk();

        // Finaliza rascunho
        $this->postJson("/api/forms/{$form->id}/drafts/{$draftId}/submit", [
            'values' => ['foto' => $upload],
        ])->assertOk();

        // Verifica que a submissão finalizada possui o anexo
        $details = $this->getJson("/api/submissions/{$draftId}")->assertOk()->json('data.values');
        $this->assertSame('foto.png', $details[0]['file_name']);
    }
}
