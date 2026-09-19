<?php

return [
    'repository' => env('DIATAR_REPOSITORY', 'diatar-eu/diatar-dtxs'),
    'branch' => env('DIATAR_REPOSITORY_BRANCH', 'main'),
    'api_url' => env('DIATAR_REPOSITORY_API_URL', 'https://api.github.com'),
    'raw_url' => env('DIATAR_REPOSITORY_RAW_URL', 'https://raw.githubusercontent.com'),
    'token' => env('DIATAR_REPOSITORY_TOKEN'),
    'connect_timeout' => (int) env('DIATAR_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('DIATAR_HTTP_TIMEOUT', 20),
    'retry_attempts' => (int) env('DIATAR_HTTP_RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => (int) env('DIATAR_HTTP_RETRY_DELAY_MS', 250),
    'schedule_day' => (int) env('DIATAR_SYNC_DAY', 1),
    'schedule_time' => env('DIATAR_SYNC_TIME', '03:15'),
    'schedule_on_one_server' => (bool) env('DIATAR_SYNC_ON_ONE_SERVER', true),
];
