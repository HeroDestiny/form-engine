<?php

namespace App\Services\Forms;

use App\Exceptions\BusinessException;
use App\Models\FormField;
use Illuminate\Support\Collection;

class SubmissionCsvService
{
    public function render(int $formId, Collection $versions, iterable $submissions, string $separator = ','): array
    {
        $headers = ['ID', 'Usuário', 'Email', 'Data de Submissão', 'Versão'];
        $columns = [];
        foreach ($versions as $version) {
            foreach ($version->fields as $field) {
                // Names identify logical fields across copied versions.
                $columns[$field->name] ??= $field->label;
                if (count($columns) + 5 > config('limits.export_columns')) {
                    throw new BusinessException('Limite de colunas para exportação excedido. Restrinja as versões.');
                }
            }
        }
        $headers = array_merge($headers, array_values($columns));
        $stream = tmpfile();
        if ($stream === false) {
            throw new \RuntimeException('Não foi possível preparar o arquivo de exportação.');
        }
        $count = 0;
        $versions = $versions->keyBy('id');
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, $headers, $separator);

            foreach ($submissions as $submission) {
                $owner = $submission->submitter;
                $version = $versions->get($submission->form_version_id);
                $fields = $submission->version->fields->keyBy('name');
                $values = $submission->values->keyBy('form_field_id');
                $row = [
                    $submission->id,
                    (string) ($owner?->name ?? ''),
                    (string) ($owner?->email ?? ''),
                    (string) optional($submission->submitted_at)->format('d/m/Y H:i'),
                    $version->version_number,
                ];

                foreach (array_keys($columns) as $name) {
                    $field = $fields->get($name);
                    $value = $field ? $values->get($field->id)?->value : null;

                    if ($field) {
                        $value = $this->mapValueForField($field, $value);
                    }

                    $row[] = $value ?? '';
                }

                $this->writeRow($stream, $row, $separator);
                $count++;
            }

            $bytes = ftell($stream);
            rewind($stream);
            $filename = 'submissoes-'.$formId.'-'.now()->format('Ymd').'.csv';

            return ['stream' => $stream, 'filename' => $filename, 'records_count' => $count, 'bytes' => $bytes];
        } catch (\Throwable $exception) {
            fclose($stream);
            throw $exception;
        }
    }

    private function writeRow($stream, array $row, string $separator): void
    {
        $row = array_map(function ($cell) {
            if (is_string($cell) && preg_match('/^(?:[\t\r\n]|[\p{Z}\s\x00]*[=+\-@＝＋－＠])/u', $cell)) {
                // Tab is kept inside a quoted CSV cell by fputcsv (Excel viewing).
                return "\t".$cell;
            }

            return $cell;
        }, $row);
        if (fputcsv($stream, $row, $separator, '"', '', "\r\n") === false) {
            throw new \RuntimeException('Não foi possível escrever o arquivo de exportação.');
        }
        if (ftell($stream) > config('limits.export_bytes')) {
            throw new BusinessException('Limite de tamanho do CSV excedido. Restrinja os filtros.');
        }
    }

    private function mapValueForField(FormField $field, mixed $value): mixed
    {
        if ($field->type === 'checkbox') {
            $choices = SubmissionFieldValue::decode($field, $value) ?? [];

            return implode('; ', array_map(fn ($choice) => SubmissionFieldValue::label($field, $choice), $choices));
        }

        if ($field->type === 'file') {
            $decoded = SubmissionFieldValue::decode($field, $value);

            return is_array($decoded) ? ($decoded['name'] ?? '') : (string) ($value ?? '');
        }

        if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
            return SubmissionFieldValue::label($field, $value);
        }

        return $value;
    }
}
