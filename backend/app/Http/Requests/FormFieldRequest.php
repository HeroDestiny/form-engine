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
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('form_fields', 'name')->where(fn ($query) => $query->where('form_version_id', $this->version->id))->ignore($this->field?->id),
            ],
            'type' => ['required', Rule::in(['text', 'textarea', 'number', 'email', 'date', 'select', 'radio', 'checkbox', 'file'])],
            'is_required' => ['required', 'boolean'],
            'order' => ['required', 'integer', 'between:1,2147483647'],
            'options' => ['required_if:type,select,radio,checkbox', 'nullable', 'array', 'list', 'max:'.config('limits.options_per_field')],
            'options.*.value' => ['required_with:options', 'string', 'max:255', 'distinct:strict'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'condition' => ['nullable', 'array'],
            'condition.field' => ['required_with:condition', 'string', 'max:100'],
            'condition.operator' => ['required_with:condition', Rule::in(['equals', 'not_equals', 'in', 'filled', 'empty'])],
            'condition.value' => ['nullable'],
            'section' => ['nullable', 'string', 'max:100'],
            'section_description' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', Rule::in(['full', 'half', 'third'])],
            'layout_mode' => ['nullable', Rule::in(['cards', 'wizard'])],
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
        $validated = $this->validated();
        $rawOptions = $validated['options'] ?? null;
        $condition = $validated['condition'] ?? null;
        $section = ! empty($validated['section']) ? trim($validated['section']) : null;
        $sectionDesc = ! empty($validated['section_description']) ? trim($validated['section_description']) : null;
        $width = ! empty($validated['width']) ? $validated['width'] : null;
        $layoutMode = ! empty($validated['layout_mode']) ? $validated['layout_mode'] : null;

        $hasChoices = is_array($rawOptions) && ! empty($rawOptions);
        $hasCustomMetadata = (! empty($condition) && ! empty($condition['field']))
            || ! empty($section)
            || ! empty($sectionDesc)
            || (! empty($width) && $width !== 'full')
            || ! empty($layoutMode);

        if (! $hasCustomMetadata) {
            $options = $hasChoices ? $rawOptions : null;
        } else {
            $options = [];
            if ($hasChoices) {
                $options['choices'] = array_is_list($rawOptions) ? $rawOptions : ($rawOptions['choices'] ?? $rawOptions);
            }
            if (! empty($condition) && ! empty($condition['field'])) {
                $options['condition'] = $condition;
            }
            if ($section) {
                $options['section'] = $section;
            }
            if ($sectionDesc) {
                $options['section_description'] = $sectionDesc;
            }
            if ($width && $width !== 'full') {
                $options['width'] = $width;
            }
            if ($layoutMode) {
                $options['layout_mode'] = $layoutMode;
            }
        }

        return [
            'label' => $validated['label'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_required' => $validated['is_required'],
            'order' => $validated['order'],
            'options' => $options,
        ];
    }
}
