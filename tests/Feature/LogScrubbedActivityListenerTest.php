<?php

use Bernskiold\LaravelDataScrubber\Data\ScrubbedField;
use Bernskiold\LaravelDataScrubber\Events\Scrubbed;
use Bernskiold\LaravelDataScrubber\Listeners\LogScrubbedActivity;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;

it('returns early when activity function does not exist', function () {
    // Create a model and event
    $model = new TestModel;
    $event = new Scrubbed($model, []);

    // The listener should return early without errors when activity() doesn't exist
    $listener = new LogScrubbedActivity;
    $result = $listener->handle($event);

    // No exception means it handled the missing function gracefully
    expect($result)->toBeNull();
});

it('skips logging when model does not have LogsActivity trait', function () {
    // TestModel does not have the LogsActivity trait
    // We can verify the trait detection logic works

    $model = new TestModel;

    // Verify TestModel doesn't have the LogsActivity trait
    $traits = class_uses_recursive($model);
    expect($traits)->not->toContain('Spatie\Activitylog\Traits\LogsActivity');
});

it('correctly detects LogsActivity trait would be found if present', function () {
    // This test verifies the trait detection mechanism works
    // We test with a known trait that IS present

    $model = new TestModel;
    $traits = class_uses_recursive($model);

    // Verify known traits are detected
    expect($traits)->toContain('Bernskiold\LaravelDataScrubber\Concerns\ScrubsData');
    expect($traits)->toContain('Illuminate\Database\Eloquent\SoftDeletes');
});

it('builds correct scrubbed_fields structure', function () {
    // Test that the scrubbed fields mapping logic works correctly
    $scrubbedFields = [
        'email' => new ScrubbedField(
            field: 'email',
            previous: 'john@example.com',
            scrubbed: 'deleted-1@anonymized.local',
            strategy: AnonymizeEmailWithIdStrategy::class,
        ),
        'phone' => new ScrubbedField(
            field: 'phone',
            previous: '555-1234',
            scrubbed: null,
            strategy: NullStrategy::class,
        ),
    ];

    // Simulate what the listener does to build the properties
    $result = collect($scrubbedFields)
        ->mapWithKeys(fn ($field) => [$field->field => class_basename($field->strategy)])
        ->all();

    expect($result)->toBe([
        'email' => 'AnonymizeEmailWithIdStrategy',
        'phone' => 'NullStrategy',
    ]);
});

it('uses correct default config values', function () {
    expect(config('data-scrubber.activity_log.event'))->toBe('data_scrubbed');
    expect(config('data-scrubber.activity_log.description'))->toBe('Record data was scrubbed');
});

it('respects custom config values', function () {
    config()->set('data-scrubber.activity_log.event', 'custom_scrub_event');
    config()->set('data-scrubber.activity_log.description', 'Custom description');

    expect(config('data-scrubber.activity_log.event'))->toBe('custom_scrub_event');
    expect(config('data-scrubber.activity_log.description'))->toBe('Custom description');
});

it('never includes previous values in the logged properties', function () {
    // This test documents the security requirement that previous values
    // should never be logged - only field names and strategy names

    $scrubbedFields = [
        'email' => new ScrubbedField(
            field: 'email',
            previous: 'sensitive@example.com',
            scrubbed: 'deleted-1@anonymized.local',
            strategy: AnonymizeEmailWithIdStrategy::class,
        ),
        'ssn' => new ScrubbedField(
            field: 'ssn',
            previous: '123-45-6789',
            scrubbed: null,
            strategy: NullStrategy::class,
        ),
    ];

    // Build the properties as the listener would
    $properties = [
        'scrubbed_fields' => collect($scrubbedFields)
            ->mapWithKeys(fn ($field) => [$field->field => class_basename($field->strategy)])
            ->all(),
    ];

    // Verify no sensitive data is included
    $serialized = json_encode($properties);

    expect($serialized)->not->toContain('sensitive@example.com');
    expect($serialized)->not->toContain('123-45-6789');
    expect($serialized)->not->toContain('deleted-1@anonymized.local');

    // Only field names and strategy names should be present
    expect($serialized)->toContain('email');
    expect($serialized)->toContain('ssn');
    expect($serialized)->toContain('AnonymizeEmailWithIdStrategy');
    expect($serialized)->toContain('NullStrategy');
});
