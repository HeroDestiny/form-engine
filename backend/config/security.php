<?php

return [
    'login_attempts_per_minute' => max(1, (int) env('LOGIN_ATTEMPTS_PER_MINUTE', 5)),
];
