<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Paths
    |--------------------------------------------------------------------------
    |
    | These are the paths that will be scanned when auto-discovering models
    | that implement the Scrubbable interface. By default, this is set to
    | the app/Models directory, but you can add additional paths as needed.
    |
    */

    'model_paths' => [
        app_path('Models'),
        // app_path('Domains'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scrub Timestamp Column
    |--------------------------------------------------------------------------
    |
    | This is the default column name used to store when a record was scrubbed.
    | You can override this on a per-model basis by implementing the
    | scrubTimestampColumn() method in your model.
    |
    */

    'timestamp_column' => 'scrubbed_at',

    /*
    |--------------------------------------------------------------------------
    | Strategy Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for the built-in scrubbing strategies. These values are
    | used when a strategy is instantiated without explicit configuration.
    | You can override these globally here or per-usage in your models.
    |
    */

    'strategies' => [

        'redacted' => [
            'replacement' => '[REDACTED]',
        ],

        'anonymize_first_name' => [
            'replacement' => 'Deleted',
        ],

        'anonymize_last_name' => [
            'replacement' => 'User',
        ],

        'anonymize_email' => [
            'replacement' => 'anonymized@deleted.local',
        ],

        'anonymize_email_with_id' => [
            'domain' => 'anonymized.local',
            'prefix' => 'deleted-',
        ],

        'hash' => [
            'algorithm' => 'sha256',
        ],

        'delete_file' => [
            'disk' => null, // Uses default filesystem disk
        ],

        'mask' => [
            'visible_start' => 2,
            'visible_end' => 2,
            'mask_char' => '*',
        ],

        'truncate' => [
            'keep_chars' => 3,
            'suffix' => '***',
        ],

        'ip_anonymize' => [
            'mask_octets' => 2, // 1 = x.x.x.0, 2 = x.x.0.0, 3 = x.0.0.0
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the data scrubber processes records. By default, scrubbing
    | is performed asynchronously using Laravel's queue system for better
    | performance with large datasets. Use the --sync flag to run synchronously.
    |
    */

    'queue' => [
        // Enable async processing by default (models can override via shouldScrubAsynchronously)
        'async' => env('DATA_SCRUBBER_ASYNC', true),

        // Queue connection (null = default connection)
        'connection' => null,

        // Queue name for scrub jobs
        'queue' => 'data-scrubber',

        // Default chunk size for chunkById (models can override via scrubChunkSize)
        'chunk_size' => 500,

        // Job retry attempts
        'tries' => 3,

        // Backoff in seconds between retries
        'backoff' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log Integration
    |--------------------------------------------------------------------------
    |
    | Optional integration with Spatie Activity Log. These settings are only
    | used when you register the LogScrubbedActivity or ScrubActivityLogListener
    | listeners and have spatie/laravel-activitylog installed.
    |
    */

    'activity_log' => [
        // The event name to use in the activity log
        'event' => 'data_scrubbed',

        // The description text logged for each scrub operation
        'description' => 'Record data was scrubbed',

        // Property keys within the JSON 'properties' column that contain model data
        'property_keys' => ['old', 'attributes'],
    ],

];
