<?php

return [
    /**
     * Подставлять ссылки на фото при экспорте Liewood (CSV/XLSX).
     */
    'enabled' => env('LIEWOOD_DRIVE_ENABLED', false),

    /**
     * Источник картинок:
     * - gcs   (Google Cloud Storage, рекомендуется)
     * - drive (Google Drive, legacy)
     */
    'source' => env('LIEWOOD_IMAGES_SOURCE', 'gcs'),

    /**
     * GCS settings (используются при source=gcs).
     */
    'gcs_bucket' => env('LIEWOOD_GCS_BUCKET', 'lpb-bucket'),
    // Префикс сезона в бакете (без лишнего /images/ — объекты ищутся рекурсивно под этим путём).
    'gcs_prefix' => env('LIEWOOD_GCS_PREFIX', 'liewoodseason12026/'),
    'gcs_public_base_url' => env('LIEWOOD_GCS_PUBLIC_BASE_URL', 'https://storage.googleapis.com'),

    /**
     * false (по умолчанию) — в CSV как раньше прямые ссылки https://storage.googleapis.com/...
     * true — в CSV прямые public URL GCS, но с параметром
     * `response-content-disposition=attachment` (для режима "download").
     */
    'gcs_use_download_proxy' => filter_var(env('LIEWOOD_GCS_USE_DOWNLOAD_PROXY', false), FILTER_VALIDATE_BOOLEAN),

    /** TTL записи в кэше для токена ссылки (секунды). */
    'gcs_download_proxy_ttl_seconds' => (int) env('LIEWOOD_GCS_DOWNLOAD_PROXY_TTL', 604800),

    /** ID папки на Google Drive (из URL folders/XXXX) */
    'folder_id' => env('LIEWOOD_DRIVE_FOLDER_ID', '1FvQTZT2ecCPDmyGMulaRPGYrILwN04NX'),

    /**
     * OAuth: JSON из Google Cloud (Desktop client).
     * По умолчанию — копия из scripts/ или свой путь.
     */
    'credentials_path' => env(
        'LIEWOOD_DRIVE_CREDENTIALS_PATH',
        base_path('scripts/credentials.json')
    ),

    /**
     * OAuth token (создаётся после первого входа; можно скопировать scripts/token.json).
     */
    'token_path' => env(
        'LIEWOOD_DRIVE_TOKEN_PATH',
        base_path('scripts/token.json')
    ),

    /**
     * Устар.: раньше управляло вызовом permissions на каждый файл (800+ запросов → таймаут 30s).
     * Права «по ссылке» для массива файлов делайте офлайн (Python-скрипт с --make-public) или шарингом папки.
     */
    'make_public' => env('LIEWOOD_DRIVE_MAKE_PUBLIC', false),

    /**
     * Если true — для КАЖДОГО файла вызывается Drive permissions.create (очень медленно, только малые наборы).
     * По умолчанию false, чтобы импорт не упирался в max_execution_time.
     */
    'apply_public_permission_per_file' => env('LIEWOOD_DRIVE_APPLY_PUBLIC_PERMISSION_PER_FILE', false),

    /** Лимит времени PHP (сек.) на шаг подстановки картинок (список файлов + кэш). 0 = без лимита. */
    'max_execution_seconds' => (int) env('LIEWOOD_DRIVE_MAX_EXECUTION_SECONDS', 300),

    /**
     * Кэш списка файлов с Диска (секунды), чтобы не дергать API на каждый экспорт.
     */
    'cache_ttl_seconds' => (int) env('LIEWOOD_DRIVE_CACHE_TTL', 1800),

    /**
     * Колонки шаблона для картинок (без Images 4 — как в master template).
     *
     * @var array<int, string>
     */
    'image_slots' => [
        'Images 1',
        'Images 2',
        'Images 3',
        'Images 5',
        'Images 6',
        'Images 7',
        'Images 8',
        'Images 9',
        'Images 10',
    ],
];
