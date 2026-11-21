<?php

namespace App\Services\Forms;

use App\Models\FormField;

class SubmissionFieldValue
{
    public static function choices(FormField $field): array
    {
        $opts = $field->options;
        if (! is_array($opts)) {
            return [];
        }
        if (isset($opts['choices']) && is_array($opts['choices'])) {
            return $opts['choices'];
        }
        if (array_is_list($opts)) {
            return $opts;
        }

        return [];
    }

    public static function condition(FormField $field): ?array
    {
        $opts = $field->options;
        if (! is_array($opts)) {
            return null;
        }
        if (isset($opts['condition']) && is_array($opts['condition']) && ! empty($opts['condition']['field'])) {
            return $opts['condition'];
        }

        return null;
    }

    public static function section(FormField $field): ?string
    {
        $opts = $field->options;
        if (! is_array($opts)) {
            return null;
        }
        $sec = $opts['section'] ?? null;

        return is_string($sec) && trim($sec) !== '' ? trim($sec) : null;
    }

    public static function sectionDescription(FormField $field): ?string
    {
        $opts = $field->options;
        if (! is_array($opts)) {
            return null;
        }
        $desc = $opts['section_description'] ?? null;

        return is_string($desc) && trim($desc) !== '' ? trim($desc) : null;
    }

    public static function width(FormField $field): string
    {
        $opts = $field->options;
        if (! is_array($opts)) {
            return 'full';
        }
        $w = $opts['width'] ?? 'full';

        return in_array($w, ['full', 'half', 'third'], true) ? $w : 'full';
    }

    public static function layoutMode(FormField $field): string
    {
        $opts = $field->options;
        if (! is_array($opts)) {
            return 'cards';
        }
        $mode = $opts['layout_mode'] ?? 'cards';

        return in_array($mode, ['cards', 'wizard'], true) ? $mode : 'cards';
    }

    public static function label(FormField $field, mixed $value): ?string
    {
        if ($field->type === 'file') {
            if (is_array($value)) {
                return $value['name'] ?? null;
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? ($decoded['name'] ?? basename($value)) : basename($value);
            }
        }

        foreach (self::choices($field) as $option) {
            if (($option['value'] ?? null) === $value) {
                return $option['label'] ?? $value;
            }
        }

        return is_string($value) ? $value : null;
    }

    public static function encode(FormField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field->type === 'checkbox') {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if ($field->type === 'file') {
            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return $value;
                }

                return json_encode(['path' => $value, 'name' => basename($value)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        return (string) $value;
    }

    public static function decode(FormField $field, ?string $value): mixed
    {
        if ($value === null || $value === '') {
            return $field->type === 'checkbox' ? [] : null;
        }

        if ($field->type === 'checkbox') {
            $decoded = json_decode($value, true);

            // Earlier frontend versions sent checkbox choices as comma-separated text.
            return is_array($decoded) ? $decoded : explode(',', $value);
        }

        if ($field->type === 'file') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : ['path' => $value, 'name' => basename($value)];
        }

        return $value;
    }
}
