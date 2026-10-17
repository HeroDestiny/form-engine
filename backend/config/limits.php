<?php

return [
    'text_length' => 1000,
    'textarea_length' => 10000,
    'fields_per_version' => 100,
    'options_per_field' => 100,
    'export_rows' => max(1, min(10000, (int) env('EXPORT_MAX_ROWS', 10000))),
    'export_bytes' => max(1, min(26214400, (int) env('EXPORT_MAX_BYTES', 26214400))),
    'export_columns' => 500,
    'export_versions' => 100,
    'export_chunk_size' => 25,
];
