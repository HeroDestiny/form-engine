<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Repositories\Contracts\FormFieldRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FormVersionService
{
    public function __construct(
        private readonly FormVersionRepositoryInterface $formVersionRepository,
        private readonly FormFieldRepositoryInterface $formFieldRepository,
    ) {}

    public function listForFormInTenant(int $tenantId, int $formId): Collection
    {
        return $this->formVersionRepository->listForFormInTenant($tenantId, $formId);
    }

    public function createNextVersionFromPublished(Form $form, FormVersion $publishedVersion, int $createdBy): array
    {
        return DB::transaction(function () use ($form, $publishedVersion, $createdBy) {
            $newVersion = $this->formVersionRepository->create([
                'form_id' => $form->id,
                'version_number' => $publishedVersion->version_number + 1,
                'is_published' => false,
                'published_at' => null,
                'created_by' => $createdBy,
            ]);

            $fields = $this->formFieldRepository->listForVersion($publishedVersion->id);
            foreach ($fields as $field) {
                $this->formFieldRepository->create([
                    'form_version_id' => $newVersion->id,
                    'label' => $field->label,
                    'name' => $field->name,
                    'type' => $field->type,
                    'is_required' => $field->is_required,
                    'options' => $field->options,
                    'order' => $field->order,
                ]);
            }

            return [
                'version' => $newVersion,
                'fields_copied' => $fields->count(),
                'from_version' => $publishedVersion->version_number,
                'fields' => $this->formFieldRepository->listForVersion($newVersion->id),
            ];
        });
    }
}
