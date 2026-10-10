<?php

namespace App\Http\Requests;

use App\Models\FormField;
use App\Models\FormVersion;
use App\Services\Forms\FormFieldService;
use Illuminate\Validation\Rule;

class FormFieldRequest extends ApiRequest
{
    private FormVersion $version;

    private ?FormField $field = null;

    protected function prepareForValidation(): void
    {
        $service = app(FormFieldService::class);
        $tenantId = (int) $this->user()->tenant_id;
        $formId = (int) $this->route('formId');
        $versionId = (int) $this->route('versionId');

        if ($this->route('fieldId') !== null) {
            $this->field = $service->requireField($tenantId, $formId, $versionId, (int) $this->route('fieldId'));
            $this->version = $this->field->version;
        } else {
            $this->version = $service->requireVersion($tenantId, $formId, $versionId);
        }
        $service->ensureEditable($this->version);
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:255'],
            'name' => [
                'required', 'string', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('form_fields', 'name')->where(fn ($query) => $query->where('form_version_id', $this->version->id))->ignore($this->field?->id),
            ],
            'type' => ['required', Rule::in(['text', 'textarea', 'number', 'email', 'date', 'select', 'radio', 'checkbox'])],
            'is_required' => ['required', 'boolean'],
            'order' => ['required', 'integer', 'min:1'],
            'options' => ['required_if:type,select,radio,checkbox', 'array'],
            'options.*.value' => ['required_with:options', 'string'],
            'options.*.label' => ['required_with:options', 'string'],
        ];
    }

    public function formVersion(): FormVersion
    {
        return $this->version;
    }

    public function formField(): FormField
    {
        return $this->field;
    }

    public function fieldPayload(): array
    {
        return [...$this->validated(), 'options' => $this->validated('options')];
    }
}
