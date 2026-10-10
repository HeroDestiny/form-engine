<?php

namespace App\Services\Forms;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\Form;
use App\Models\FormVersion;
use App\Repositories\Contracts\FormFieldRepositoryInterface;
use App\Repositories\Contracts\FormRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FormVersionService
{
    public function __construct(
        private readonly FormVersionRepositoryInterface $formVersionRepository,
        private readonly FormFieldRepositoryInterface $formFieldRepository,
        private readonly FormRepositoryInterface $formRepository,
    ) {}

    public function listForFormInTenant(int $tenantId, int $formId): Collection
    {
        if (! $this->formRepository->findInTenant($tenantId, $formId)) {
            throw new NotFoundException;
        }

        return $this->formVersionRepository->listForFormInTenant($tenantId, $formId);
    }

    private function createNextVersionFromPublished(Form $form, FormVersion $publishedVersion, int $createdBy): array
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

    public function createNextVersion(int $tenantId, int $formId, int $createdBy): array
    {
        $form = $this->formRepository->findInTenant($tenantId, $formId);
        if (! $form) {
            throw new NotFoundException;
        }
        $versions = $this->formVersionRepository->listForFormInTenant($tenantId, $formId);
        if ($versions->contains(fn (FormVersion $version) => ! $version->is_published)) {
            throw new BusinessException('Já existe uma versão em edição', 'draft_exists');
        }
        $published = $versions->first(fn (FormVersion $version) => $version->is_published);
        if (! $published) {
            throw new BusinessException('Publique a versão atual antes de criar nova versão', 'unpublished_version');
        }

        return $this->createNextVersionFromPublished($form, $published, $createdBy);
    }

    public function publish(int $tenantId, int $formId, int $versionId): FormVersion
    {
        $version = $this->formVersionRepository->findInTenant($tenantId, $formId, $versionId);
        if (! $version) {
            throw new NotFoundException;
        }
        if ($version->is_published || $this->formFieldRepository->listForVersion($versionId)->isEmpty()) {
            throw new BusinessException('Versão não pode ser publicada');
        }

        return $this->formVersionRepository->publish($version);
    }
}
