<?php

namespace App\Services\Forms;

use App\Models\FormField;
use Illuminate\Support\Collection;

class SubmissionCsvService
{
    public function render(int $formId, Collection $submissions, string $separator = ','): array
    {
        $headers = ['ID', 'Usuário', 'Email', 'Data de Submissão', 'Versão'];
        $columns = [];
        foreach ($submissions->sortByDesc(fn ($submission) => $submission->version->version_number) as $submission) {
            foreach ($submission->version->fields->sortBy('order') as $field) {
                // Names identify logical fields across copied versions.
                $columns[$field->name] ??= $field->label;
            }
        }
        $headers = array_merge($headers, array_values($columns));
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, $separator, '"', '', "\r\n");

            foreach ($submissions as $submission) {
                $owner = $submission->submitter;
                $fields = $submission->version->fields->keyBy('name');
                $values = $submission->values->keyBy('form_field_id');
                $row = [
                    $submission->id,
                    (string) ($owner?->name ?? ''),
                    (string) ($owner?->email ?? ''),
                    (string) optional($submission->submitted_at)->format('d/m/Y H:i'),
                    $submission->version->version_number,
                ];

                foreach (array_keys($columns) as $name) {
                    $field = $fields->get($name);
                    $value = $field ? $values->get($field->id)?->value : null;

                    if ($field) {
                        $value = $this->mapValueForField($field, $value);
                    }

                    $row[] = $value ?? '';
                }

                fputcsv($stream, $row, $separator, '"', '', "\r\n");
            }

            rewind($stream);
            $csv = stream_get_contents($stream);
            $filename = 'submissoes-'.$formId.'-'.now()->format('Ymd').'.csv';

            return ['content' => $csv, 'filename' => $filename];
        } finally {
            fclose($stream);
        }
    }

    private function mapValueForField(FormField $field, mixed $value): mixed
    {
        if ($field->type === 'checkbox') {
            $choices = SubmissionFieldValue::decode($field, $value) ?? [];

            return implode('; ', array_map(function ($choice) use ($field) {
                $selected = collect($field->options)->firstWhere('value', $choice);

                return $selected['label'] ?? $choice;
            }, $choices));
        }

        if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
            $selected = collect($field->options)->firstWhere('value', $value);

            return $selected['label'] ?? $value;
        }

        return $value;
    }
}
