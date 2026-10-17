<?php

return [
    // Empty by default: direct requests cannot select their own client IP.
    'proxies' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
