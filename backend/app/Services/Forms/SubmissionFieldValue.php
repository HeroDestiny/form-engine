<?php

namespace App\Services\Forms;

use App\Models\FormField;

class SubmissionFieldValue
{
    public static function encode(FormField $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $field->type === 'checkbox'
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : (string) $value;
    }

    public static function decode(FormField $field, ?string $value): mixed
    {
        if ($field->type !== 'checkbox' || $value === null) {
            return $value;
        }

        $decoded = json_decode($value, true);

        // Earlier frontend versions sent checkbox choices as comma-separated text.
        return is_array($decoded) ? $decoded : ($value === '' ? [] : explode(',', $value));
    }
}
