<?php

use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModelWithCustomStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModelWithoutTimestamp;

it('scrubs model data with timestamp logging', function () {
    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
        'notes' => 'Some private notes',
    ]);

    $model->delete(); // Soft delete

    $model->scrub();
    $model->refresh();

    expect($model->email)->toBe("deleted-{$model->id}@anonymized.local");
    expect($model->first_name)->toBe('Deleted');
    expect($model->last_name)->toBe('User');
    expect($model->phone)->toBeNull();
    expect($model->ssn)->toBe('[REDACTED]');
    expect($model->notes)->toBe(hash('sha256', 'Some private notes'));
    expect($model->scrubbed_at)->not->toBeNull();
});

it('scrubs model data without timestamp logging', function () {
    $model = TestModelWithoutTimestamp::create([
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
    ]);

    $model->scrub();
    $model->refresh();

    expect($model->email)->toBe('anonymized@deleted.local');
    expect($model->name)->toBe('[REDACTED]');
});

it('scrubs model with custom strategy', function () {
    $model = TestModelWithCustomStrategy::create([
        'email' => 'test@example.com',
        'custom_field' => 'original value',
    ]);

    $model->scrub();
    $model->refresh();

    expect($model->email)->toBe('anonymized@deleted.local');
    expect($model->custom_field)->toBe("custom-{$model->id}-scrubbed");
    expect($model->scrubbed_at)->not->toBeNull();
});

it('detects if model has been scrubbed', function () {
    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $model->delete();

    expect($model->hasBeenScrubbed())->toBeFalse();

    $model->scrub();

    expect($model->hasBeenScrubbed())->toBeTrue();
});

it('returns false for hasBeenScrubbed when timestamp logging disabled', function () {
    $model = TestModelWithoutTimestamp::create([
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
    ]);

    expect($model->hasBeenScrubbed())->toBeFalse();

    $model->scrub();

    // Still returns false because timestamp logging is disabled
    expect($model->hasBeenScrubbed())->toBeFalse();
});

it('previews scrub without saving', function () {
    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
        'notes' => 'Some notes',
    ]);

    $preview = $model->previewScrub();

    expect($preview)->toHaveKeys(['email', 'first_name', 'last_name', 'phone', 'ssn', 'notes']);
    expect($preview['email']['current'])->toBe('john@example.com');
    expect($preview['email']['scrubbed'])->toBe("deleted-{$model->id}@anonymized.local");
    expect($preview['email']['strategy'])->toBe(AnonymizeEmailWithIdStrategy::class);

    // Verify original data is unchanged
    $model->refresh();
    expect($model->email)->toBe('john@example.com');
});

it('scopes to not scrubbed records', function () {
    $scrubbed = TestModel::create([
        'email' => 'scrubbed@example.com',
        'first_name' => 'Deleted',
        'last_name' => 'User',
        'scrubbed_at' => now(),
    ]);
    $scrubbed->delete();

    $notScrubbed = TestModel::create([
        'email' => 'notscrubbed@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $notScrubbed->delete();

    $results = TestModel::withTrashed()->notScrubbed()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($notScrubbed->id);
});

it('scopes to scrubbed records', function () {
    $scrubbed = TestModel::create([
        'email' => 'scrubbed@example.com',
        'first_name' => 'Deleted',
        'last_name' => 'User',
        'scrubbed_at' => now(),
    ]);
    $scrubbed->delete();

    $notScrubbed = TestModel::create([
        'email' => 'notscrubbed@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $notScrubbed->delete();

    $results = TestModel::withTrashed()->scrubbed()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($scrubbed->id);
});

it('scopes return all when timestamp logging disabled', function () {
    TestModelWithoutTimestamp::create(['email' => 'a@example.com', 'name' => 'A']);
    TestModelWithoutTimestamp::create(['email' => 'b@example.com', 'name' => 'B']);

    // When timestamp logging is disabled, scopes return all records
    expect(TestModelWithoutTimestamp::notScrubbed()->count())->toBe(2);
    expect(TestModelWithoutTimestamp::scrubbed()->count())->toBe(2);
});

it('returns correct scrub options timestamp column', function () {
    $model = new TestModel;
    $options = $model->getScrubOptions();

    expect($options->timestampColumn)->toBe('scrubbed_at');
});

it('returns correct scrub options logTimestamp value', function () {
    $modelWithTimestamp = new TestModel;
    $modelWithoutTimestamp = new TestModelWithoutTimestamp;

    expect($modelWithTimestamp->getScrubOptions()->logTimestamp)->toBeTrue();
    expect($modelWithoutTimestamp->getScrubOptions()->logTimestamp)->toBeFalse();
});

it('scrubbable query returns correct records', function () {
    // Create models (not deleted - should not be in query)
    TestModel::create([
        'email' => 'active@example.com',
        'first_name' => 'Active',
        'last_name' => 'User',
    ]);

    // Create and soft delete model (should be in query)
    $deletedNotScrubbed = TestModel::create([
        'email' => 'deleted@example.com',
        'first_name' => 'Deleted',
        'last_name' => 'User',
    ]);
    $deletedNotScrubbed->delete();

    // Create soft deleted and already scrubbed model (should not be in query)
    $deletedScrubbed = TestModel::create([
        'email' => 'scrubbed@example.com',
        'first_name' => 'Scrubbed',
        'last_name' => 'User',
        'scrubbed_at' => now(),
    ]);
    $deletedScrubbed->delete();

    $results = (new TestModel)->scrubbableQuery()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($deletedNotScrubbed->id);
});
