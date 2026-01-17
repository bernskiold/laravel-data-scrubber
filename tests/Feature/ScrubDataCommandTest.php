<?php

use Bernskiold\LaravelDataScrubber\Jobs\ScrubModelJob;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModelWithoutTimestamp;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Set up model paths to point to test fixtures
    config(['data-scrubber.model_paths' => [__DIR__.'/../Fixtures']]);
    // Default to sync mode for tests
    config(['data-scrubber.queue.async' => false]);
});

it('discovers scrubbable models from configured paths', function () {
    $this->artisan('data-scrubbing:scrub', ['--dry-run' => true])
        ->assertSuccessful();
});

it('runs in dry-run mode without making changes', function () {
    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
        'notes' => 'Private notes',
    ]);
    $model->delete();

    $this->artisan('data-scrubbing:scrub', ['--dry-run' => true])
        ->assertSuccessful();

    $model->refresh();

    // Verify data is unchanged
    expect($model->email)->toBe('john@example.com');
    expect($model->first_name)->toBe('John');
    expect($model->last_name)->toBe('Doe');
});

it('scrubs data with force flag', function () {
    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
        'notes' => 'Private notes',
    ]);
    $model->delete();

    $this->artisan('data-scrubbing:scrub', ['--force' => true])
        ->assertSuccessful();

    $model->refresh();

    expect($model->email)->toBe("deleted-{$model->id}@anonymized.local");
    expect($model->first_name)->toBe('Deleted');
    expect($model->last_name)->toBe('User');
    expect($model->phone)->toBeNull();
    expect($model->ssn)->toBe('[REDACTED]');
});

it('filters by model name', function () {
    // Create records for both models
    $testModel = TestModel::create([
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ]);
    $testModel->delete();

    $otherModel = TestModelWithoutTimestamp::create([
        'email' => 'other@example.com',
        'name' => 'Other User',
    ]);

    // Only scrub TestModel
    $this->artisan('data-scrubbing:scrub', [
        '--model' => 'TestModel',
        '--force' => true,
    ])->assertSuccessful();

    $testModel->refresh();
    $otherModel->refresh();

    // TestModel should be scrubbed
    expect($testModel->first_name)->toBe('Deleted');

    // TestModelWithoutTimestamp should be unchanged
    expect($otherModel->name)->toBe('Other User');
});

it('handles no records to scrub gracefully', function () {
    // Don't create any scrubbable records
    TestModel::create([
        'email' => 'active@example.com',
        'first_name' => 'Active',
        'last_name' => 'User',
    ]);
    // Not deleted, so not in scrubbableQuery

    $this->artisan('data-scrubbing:scrub', ['--force' => true])
        ->assertSuccessful();
});

it('handles empty model paths gracefully', function () {
    config(['data-scrubber.model_paths' => ['/nonexistent/path']]);

    $this->artisan('data-scrubbing:scrub', ['--dry-run' => true])
        ->assertSuccessful();
});

it('scrubs multiple models', function () {
    // Create scrubbable records for both model types
    $testModel = TestModel::create([
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ]);
    $testModel->delete();

    $otherModel = TestModelWithoutTimestamp::create([
        'email' => 'other@example.com',
        'name' => 'Other User',
    ]);

    $this->artisan('data-scrubbing:scrub', ['--force' => true])
        ->assertSuccessful();

    $testModel->refresh();
    $otherModel->refresh();

    expect($testModel->first_name)->toBe('Deleted');
    expect($otherModel->name)->toBe('[REDACTED]');
});

it('dispatches jobs when in async mode', function () {
    Queue::fake();

    config(['data-scrubber.queue.async' => true]);

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $model->delete();

    $this->artisan('data-scrubbing:scrub', ['--force' => true])
        ->assertSuccessful();

    Queue::assertPushed(ScrubModelJob::class);
});

it('runs synchronously with --sync flag even when async is configured', function () {
    config(['data-scrubber.queue.async' => true]);

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
    ]);
    $model->delete();

    $this->artisan('data-scrubbing:scrub', ['--force' => true, '--sync' => true])
        ->assertSuccessful();

    $model->refresh();

    // Data should be scrubbed synchronously
    expect($model->first_name)->toBe('Deleted');
    expect($model->scrubbed_at)->not->toBeNull();
});

it('shows queue info in dry-run when async mode is enabled', function () {
    config(['data-scrubber.queue.async' => true]);

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $model->delete();

    $this->artisan('data-scrubbing:scrub', ['--dry-run' => true])
        ->assertSuccessful();

    // Data should remain unchanged
    $model->refresh();
    expect($model->first_name)->toBe('John');
});
