<?php

use Bernskiold\LaravelDataScrubber\Jobs\ScrubModelJob;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['data-scrubber.model_paths' => [__DIR__.'/../Fixtures']]);
    // Default to sync mode for tests unless testing queue behavior
    config(['data-scrubber.queue.async' => false]);
});

it('scrubs all records for a model', function () {
    $model1 = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
    ]);
    $model1->delete();

    $model2 = TestModel::create([
        'email' => 'jane@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '555-5678',
        'ssn' => '987-65-4321',
    ]);
    $model2->delete();

    $job = new ScrubModelJob(TestModel::class);
    $job->handle();

    $model1->refresh();
    $model2->refresh();

    expect($model1->email)->toBe("deleted-{$model1->id}@anonymized.local");
    expect($model1->first_name)->toBe('Deleted');
    expect($model1->last_name)->toBe('User');
    expect($model1->phone)->toBeNull();
    expect($model1->ssn)->toBe('[REDACTED]');
    expect($model1->scrubbed_at)->not->toBeNull();

    expect($model2->email)->toBe("deleted-{$model2->id}@anonymized.local");
    expect($model2->first_name)->toBe('Deleted');
    expect($model2->last_name)->toBe('User');
    expect($model2->scrubbed_at)->not->toBeNull();
});

it('only scrubs records matching scrubbableQuery', function () {
    // Create a scrubbable record (soft deleted)
    $deletedModel = TestModel::create([
        'email' => 'deleted@example.com',
        'first_name' => 'Deleted',
        'last_name' => 'User',
    ]);
    $deletedModel->delete();

    // Create a non-scrubbable record (not deleted)
    $activeModel = TestModel::create([
        'email' => 'active@example.com',
        'first_name' => 'Active',
        'last_name' => 'User',
    ]);

    $job = new ScrubModelJob(TestModel::class);
    $job->handle();

    $deletedModel->refresh();
    $activeModel->refresh();

    // Deleted model should be scrubbed
    expect($deletedModel->first_name)->toBe('Deleted');
    expect($deletedModel->scrubbed_at)->not->toBeNull();

    // Active model should be unchanged
    expect($activeModel->email)->toBe('active@example.com');
    expect($activeModel->first_name)->toBe('Active');
});

it('uses configured chunk size', function () {
    // Create multiple records
    for ($i = 0; $i < 5; $i++) {
        $model = TestModel::create([
            'email' => "user{$i}@example.com",
            'first_name' => "User{$i}",
            'last_name' => 'Test',
        ]);
        $model->delete();
    }

    config(['data-scrubber.queue.chunk_size' => 2]);

    $job = new ScrubModelJob(TestModel::class);
    $job->handle();

    // All records should be scrubbed despite chunking
    $scrubbed = TestModel::onlyTrashed()->whereNotNull('scrubbed_at')->count();
    expect($scrubbed)->toBe(5);
});

it('can be dispatched to queue', function () {
    Queue::fake();

    ScrubModelJob::dispatch(TestModel::class);

    Queue::assertPushed(ScrubModelJob::class, function ($job) {
        return $job->modelClass === TestModel::class;
    });
});

it('uses configured queue settings', function () {
    config([
        'data-scrubber.queue.connection' => 'redis',
        'data-scrubber.queue.queue' => 'custom-scrubber',
        'data-scrubber.queue.tries' => 5,
        'data-scrubber.queue.backoff' => 120,
    ]);

    $job = new ScrubModelJob(TestModel::class);

    expect($job->connection)->toBe('redis');
    expect($job->queue)->toBe('custom-scrubber');
    expect($job->tries)->toBe(5);
    expect($job->backoff)->toBe(120);
});

it('has appropriate job tags', function () {
    $job = new ScrubModelJob(TestModel::class);

    expect($job->tags())->toBe([
        'data-scrubber',
        'model:'.TestModel::class,
    ]);
});

it('handles empty result set gracefully', function () {
    // No records to scrub
    $job = new ScrubModelJob(TestModel::class);
    $job->handle();

    // Should not throw
    expect(true)->toBeTrue();
});
