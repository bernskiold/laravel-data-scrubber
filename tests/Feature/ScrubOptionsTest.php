<?php

use Bernskiold\LaravelDataScrubber\Data\ScrubOptions;

it('has correct default property values', function () {
    $options = new ScrubOptions;

    expect($options->logTimestamp)->toBeTrue();
    expect($options->timestampColumn)->toBe('scrubbed_at');
    expect($options->chunkSize)->toBe(500);
    expect($options->scrubAsync)->toBeTrue();
});

it('creates instance with defaults from config', function () {
    config()->set('data-scrubber.timestamp_column', 'custom_column');
    config()->set('data-scrubber.queue.chunk_size', 250);
    config()->set('data-scrubber.queue.async', true);

    $options = ScrubOptions::defaults();

    expect($options)->toBeInstanceOf(ScrubOptions::class);
    expect($options->timestampColumn)->toBe('custom_column');
    expect($options->chunkSize)->toBe(250);
    expect($options->scrubAsync)->toBeTrue();
});

it('creates instance with async disabled from config', function () {
    config()->set('data-scrubber.queue.async', false);

    $options = ScrubOptions::defaults();

    expect($options->scrubAsync)->toBeFalse();
});

it('uses fallback values when config is not set', function () {
    config()->set('data-scrubber', []);

    $options = ScrubOptions::defaults();

    expect($options->timestampColumn)->toBe('scrubbed_at');
    expect($options->chunkSize)->toBe(500);
    expect($options->scrubAsync)->toBeTrue();
});

it('enables timestamp logging with logScrubTimestamp()', function () {
    $options = (new ScrubOptions)
        ->dontLogScrubTimestamp()
        ->logScrubTimestamp();

    expect($options->logTimestamp)->toBeTrue();
});

it('disables timestamp logging with dontLogScrubTimestamp()', function () {
    $options = (new ScrubOptions)->dontLogScrubTimestamp();

    expect($options->logTimestamp)->toBeFalse();
});

it('sets timestamp column with useTimestampColumn()', function () {
    $options = (new ScrubOptions)->useTimestampColumn('anonymized_at');

    expect($options->timestampColumn)->toBe('anonymized_at');
});

it('sets chunk size with useChunkSize()', function () {
    $options = (new ScrubOptions)->useChunkSize(1000);

    expect($options->chunkSize)->toBe(1000);
});

it('enables async scrubbing with scrubAsynchronously()', function () {
    $options = (new ScrubOptions)
        ->scrubSynchronously()
        ->scrubAsynchronously();

    expect($options->scrubAsync)->toBeTrue();
});

it('disables async scrubbing with scrubSynchronously()', function () {
    $options = (new ScrubOptions)->scrubSynchronously();

    expect($options->scrubAsync)->toBeFalse();
});

it('supports fluent chaining', function () {
    $options = (new ScrubOptions)
        ->dontLogScrubTimestamp()
        ->useTimestampColumn('custom_column')
        ->useChunkSize(100)
        ->scrubSynchronously();

    expect($options->logTimestamp)->toBeFalse();
    expect($options->timestampColumn)->toBe('custom_column');
    expect($options->chunkSize)->toBe(100);
    expect($options->scrubAsync)->toBeFalse();
});

it('returns same instance for fluent methods', function () {
    $options = new ScrubOptions;

    expect($options->logScrubTimestamp())->toBe($options);
    expect($options->dontLogScrubTimestamp())->toBe($options);
    expect($options->useTimestampColumn('test'))->toBe($options);
    expect($options->useChunkSize(100))->toBe($options);
    expect($options->scrubAsynchronously())->toBe($options);
    expect($options->scrubSynchronously())->toBe($options);
});

it('supports Conditionable when() method', function () {
    $options = (new ScrubOptions)
        ->when(true, fn ($o) => $o->scrubSynchronously())
        ->when(false, fn ($o) => $o->useChunkSize(9999));

    expect($options->scrubAsync)->toBeFalse();
    expect($options->chunkSize)->toBe(500);
});

it('supports Conditionable unless() method', function () {
    $options = (new ScrubOptions)
        ->unless(false, fn ($o) => $o->dontLogScrubTimestamp())
        ->unless(true, fn ($o) => $o->useChunkSize(9999));

    expect($options->logTimestamp)->toBeFalse();
    expect($options->chunkSize)->toBe(500);
});

it('supports when() with closure condition', function () {
    $shouldDisableAsync = fn () => true;

    $options = (new ScrubOptions)
        ->when($shouldDisableAsync, fn ($o) => $o->scrubSynchronously());

    expect($options->scrubAsync)->toBeFalse();
});
