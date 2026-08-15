<?php

return [

    'enabled' => env('SPOKE_ENABLED', false),

    'version' => '1.1',

    'path' => env('SPOKE_PATH', 'spoke'),

    'auth' => [
        'enabled' => env('SPOKE_AUTH_ENABLED', true),
        'username' => env('SPOKE_AUTH_USER', 'spoke'),
        'password' => env('SPOKE_AUTH_PASS', 'spoke'),
    ],

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        preg_split('/[\s,]+/', (string) env('SPOKE_ALLOWED_IPS', '0.0.0.0/0'), -1, PREG_SPLIT_NO_EMPTY) ?: []
    ))),

    'middleware' => [
        'web',
        \Konekt\Spoke\Http\Middleware\AuthorizeSpoke::class,
    ],

    'storage_path' => storage_path('logs/spoke'),

    'logs_path' => storage_path('logs'),

    'log_reader' => [
        'chunk_bytes' => env('SPOKE_LOG_CHUNK_BYTES', 256 * 1024),
        'max_scan_bytes' => env('SPOKE_LOG_MAX_SCAN_BYTES', 64 * 1024 * 1024),
        'max_entry_bytes' => env('SPOKE_LOG_MAX_ENTRY_BYTES', 256 * 1024),
        'max_output_bytes' => env('SPOKE_LOG_MAX_OUTPUT_BYTES', 20 * 1024),
    ],

    'retention_days' => env('SPOKE_RETENTION_DAYS', 7),

    'redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'client_secret',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'authorization',
        'auth',
        'priv_key',
        'private_key',
        'card_number',
        'cvv',
        'cvc',
        'pin',
    ],

    'ignore_paths' => [
        'spoke*',
        '_debugbar*',
        'telescope*',
    ],

    'recorders' => [
        'queries' => [
            'enabled' => env('SPOKE_RECORD_QUERIES', true),
            'slow_only_ms' => env('SPOKE_QUERIES_SLOW_ONLY_MS', 50),
            'record_cli' => env('SPOKE_RECORD_CLI', false),
            'n_plus_one_threshold' => env('SPOKE_N1_THRESHOLD', 10),
        ],
        'requests' => [
            'enabled' => env('SPOKE_RECORD_REQUESTS', true),
            'sample_rate' => env('SPOKE_REQUESTS_SAMPLE_RATE', 1.0),
            'record_body' => env('SPOKE_RECORD_REQUEST_BODY', false),
            'record_body_max_bytes' => env('SPOKE_REQUEST_BODY_MAX_BYTES', 8192),
        ],
        'mails' => [
            'enabled' => env('SPOKE_RECORD_MAILS', true),
        ],
        'redis' => [
            'enabled' => env('SPOKE_RECORD_REDIS', true),
            'slow_only_ms' => env('SPOKE_REDIS_SLOW_ONLY_MS', 5),
        ],
        'http_client' => [
            'enabled' => env('SPOKE_RECORD_HTTP_CLIENT', true),
            'slow_only_ms' => env('SPOKE_HTTP_SLOW_ONLY_MS', 200),
            'redact_headers' => [
                'Authorization',
                'Cookie',
                'Set-Cookie',
                'X-Api-Key',
                'X-Auth-Token',
                'Api-Key',
                'Token',
                'Proxy-Authorization',
            ],
            'max_body_bytes' => env('SPOKE_HTTP_MAX_BODY_BYTES', 4096),
        ],
        'jobs' => [
            'enabled' => env('SPOKE_RECORD_JOBS', true),
        ],
        'exceptions' => [
            'enabled' => env('SPOKE_RECORD_EXCEPTIONS', true),
        ],
        'scheduler' => [
            'enabled' => env('SPOKE_RECORD_SCHEDULER', true),
        ],
        'commands' => [
            'enabled' => env('SPOKE_RECORD_COMMANDS', false),
            'ignore' => [
                'schedule:run',
                'schedule:finish',
                'schedule:work',
                'queue:work',
                'queue:listen',
                'queue:restart',
                'horizon',
                'horizon:*',
            ],
        ],
    ],

    'explain' => [
        'timeout_ms' => env('SPOKE_EXPLAIN_TIMEOUT_MS', 5000),
    ],

    'query_stats' => [
        'regression_factor' => env('SPOKE_QUERY_REGRESSION_FACTOR', 2.0),
        'min_samples' => env('SPOKE_QUERY_REGRESSION_MIN_SAMPLES', 5),
    ],

    'health' => [
        'exception_warn' => env('SPOKE_HEALTH_EX_WARN', 10),
        'exception_crit' => env('SPOKE_HEALTH_EX_CRIT', 50),
        'exception_spike_factor' => env('SPOKE_HEALTH_EX_SPIKE', 2.0),
    ],

    'deploy' => [
        'detect' => env('SPOKE_DETECT_DEPLOY', true),
    ],

    'capture' => [
        'ttl_minutes' => env('SPOKE_CAPTURE_TTL_MINUTES', 60),
        'max_body_bytes' => env('SPOKE_CAPTURE_MAX_BODY_BYTES', 262144),
    ],

    'mail_body_dir' => storage_path('logs/spoke/mails'),

    'max_read_bytes' => 20 * 1024 * 1024,

    'per_page' => 50,

    'redis_value_max_bytes' => 8192,
];
