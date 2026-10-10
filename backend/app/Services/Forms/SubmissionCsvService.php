<?php

namespace App\Services\Forms;

use App\Models\FormField;
use Illuminate\Support\Collection;

class SubmissionCsvService
{
    public function render(int $formId, Collection $submissions): array
    {
        $headers = ['ID', 'Usuário', 'Email', 'Data de Submissão', 'Versão'];
        $allFieldNames = [];
        foreach ($submissions as $submission) {
            foreach ($submission->version->fields as $field) {
                $allFieldNames[$field->id] = $field->label;
            }
        }
        $headers = array_merge($headers, array_values($allFieldNames));

        $lines = [];
        $lines[] = implode(',', $headers);

        foreach ($submissions as $submission) {
            $owner = $submission->submitter;
            $row = [
                $submission->id,
                '"'.str_replace('"', '""', (string) ($owner?->name ?? '')).'"',
                (string) ($owner?->email ?? ''),
                (string) optional($submission->submitted_at)->format('d/m/Y H:i'),
                $submission->version->version_number,
            ];

            foreach (array_keys($allFieldNames) as $fieldId) {
                $field = $submission->version->fields->firstWhere('id', $fieldId);
                $value = $submission->values->firstWhere('form_field_id', $fieldId)?->value;

                if ($field) {
                    $value = $this->mapValueForField($field, $value);
                }

                $row[] = '"'.str_replace('"', '""', (string) $value).'"';
            }

            $lines[] = implode(',', $row);
        }

        $csv = "\xEF\xBB\xBF".implode("\n", $lines);
        $filename = 'submissoes-'.$formId.'-'.now()->format('Ymd').'.csv';

        return ['content' => $csv, 'filename' => $filename];
    }

    private function mapValueForField(FormField $field, mixed $value): mixed
    {
        if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
            $selected = collect($field->options)->firstWhere('value', $value);

            return $selected['label'] ?? $value;
        }

        return $value;
    }
}
