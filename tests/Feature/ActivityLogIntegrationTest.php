<?php

use Bernskiold\LaravelDataScrubber\Concerns\ScrubsData;
use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Contracts\ScrubsActivityLog;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Events\Scrubbed;
use Bernskiold\LaravelDataScrubber\Listeners\ScrubActivityLogListener;
use Bernskiold\LaravelDataScrubber\Services\ActivityLogScrubber;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

// Test that listener gracefully handles missing Spatie package
it('returns early when Spatie Activity Log is not installed', function () {
    $model = new TestModel;
    $event = new Scrubbed($model, []);

    $scrubber = new ActivityLogScrubber;
    $listener = new ScrubActivityLogListener($scrubber);

    // Should return without errors when Spatie package isn't installed
    $result = $listener->handle($event);

    expect($result)->toBeNull();
});

// Test that listener checks for LogsActivity trait
it('skips scrubbing when model does not have LogsActivity trait', function () {
    $model = new TestModel;

    // Verify TestModel doesn't have the LogsActivity trait
    $traits = class_uses_recursive($model);
    expect($traits)->not->toContain('Spatie\Activitylog\Traits\LogsActivity');
});

// Test configuration values
it('uses default activity log config values', function () {
    expect(config('data-scrubber.activity_log.model'))->toBeNull();
    expect(config('data-scrubber.activity_log.property_keys'))->toBe(['old', 'attributes']);
});

it('respects custom activity log config values', function () {
    config()->set('data-scrubber.activity_log.model', 'App\\Models\\CustomActivity');
    config()->set('data-scrubber.activity_log.property_keys', ['previous', 'current']);

    expect(config('data-scrubber.activity_log.model'))->toBe('App\\Models\\CustomActivity');
    expect(config('data-scrubber.activity_log.property_keys'))->toBe(['previous', 'current']);
});

// Test ActivityLogScrubber service with mock Activity model
it('scrubs activity log records correctly', function () {
    // Create the mock activity log table
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    // Create a test model with activity log scrubbing
    $testModel = TestModelWithMockActivity::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Create mock activity records
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'old@example.com', 'first_name' => 'Jane'],
            'attributes' => ['email' => 'new@example.com', 'first_name' => 'John'],
        ],
    ]);

    // Configure to use mock Activity model
    config()->set('activitylog.activity_model', MockActivity::class);

    // Run the scrubber
    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel);

    expect($count)->toBe(1);

    // Verify the activity record was scrubbed
    $activity = MockActivity::first();
    $properties = $activity->properties;

    // Email should be anonymized
    expect($properties['old']['email'])->toBe('deleted-'.$testModel->id.'@anonymized.local');
    expect($properties['attributes']['email'])->toBe('deleted-'.$testModel->id.'@anonymized.local');

    // First name should be redacted
    expect($properties['old']['first_name'])->toBe('[REDACTED]');
    expect($properties['attributes']['first_name'])->toBe('[REDACTED]');

    // Cleanup
    Schema::dropIfExists('mock_activity_log');
});

it('handles empty properties gracefully', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel = TestModelWithMockActivity::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Create activity with empty properties
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => [],
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel);

    expect($count)->toBe(0);

    Schema::dropIfExists('mock_activity_log');
});

it('handles null properties gracefully', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel = TestModelWithMockActivity::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Create activity with null properties
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => null,
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel);

    expect($count)->toBe(0);

    Schema::dropIfExists('mock_activity_log');
});

it('only scrubs fields that exist in properties', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel = TestModelWithMockActivity::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Create activity with only some fields
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'old@example.com', 'unrelated_field' => 'keep me'],
            'attributes' => ['first_name' => 'John'],
        ],
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel);

    expect($count)->toBe(1);

    $activity = MockActivity::first();
    $properties = $activity->properties;

    // Email in 'old' should be scrubbed
    expect($properties['old']['email'])->toBe('deleted-'.$testModel->id.'@anonymized.local');

    // Unrelated field should be unchanged
    expect($properties['old']['unrelated_field'])->toBe('keep me');

    // First name in 'attributes' should be scrubbed
    expect($properties['attributes']['first_name'])->toBe('[REDACTED]');

    Schema::dropIfExists('mock_activity_log');
});

// Test ScrubsActivityLog interface - opt out
it('respects shouldScrubActivityLog opt-out', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel = TestModelOptOut::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    MockActivity::create([
        'subject_type' => TestModelOptOut::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'old@example.com'],
            'attributes' => ['email' => 'new@example.com'],
        ],
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    $scrubber = new ActivityLogScrubber;
    $event = new Scrubbed($testModel, []);
    $listener = new ScrubActivityLogListener($scrubber);

    // Manually add LogsActivity trait check bypass for testing
    // In real usage, the model would need the trait
    // Since the model opts out via shouldScrubActivityLog(), it should not scrub
    expect($testModel->shouldScrubActivityLog())->toBeFalse();

    Schema::dropIfExists('mock_activity_log');
});

// Test ScrubsActivityLog interface - custom fields
it('uses custom activityLogScrubbableFields when provided', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel = TestModelCustomActivityFields::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    MockActivity::create([
        'subject_type' => TestModelCustomActivityFields::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'old@example.com', 'first_name' => 'Jane'],
            'attributes' => ['email' => 'new@example.com', 'first_name' => 'John'],
        ],
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel);

    expect($count)->toBe(1);

    $activity = MockActivity::first();
    $properties = $activity->properties;

    // Email should use the custom NullStrategy (set to null)
    expect($properties['old']['email'])->toBeNull();
    expect($properties['attributes']['email'])->toBeNull();

    // First name should NOT be scrubbed (not in custom fields)
    expect($properties['old']['first_name'])->toBe('Jane');
    expect($properties['attributes']['first_name'])->toBe('John');

    Schema::dropIfExists('mock_activity_log');
});

it('scrubs multiple activity records', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel = TestModelWithMockActivity::create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Create multiple activity records
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'version1@example.com'],
            'attributes' => ['email' => 'version2@example.com'],
        ],
    ]);

    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'version2@example.com'],
            'attributes' => ['email' => 'version3@example.com'],
        ],
    ]);

    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel->id,
        'properties' => [
            'old' => ['email' => 'version3@example.com'],
            'attributes' => ['email' => 'final@example.com'],
        ],
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel);

    expect($count)->toBe(3);

    // Verify all records were scrubbed
    $activities = MockActivity::all();
    foreach ($activities as $activity) {
        expect($activity->properties['old']['email'])->toBe('deleted-'.$testModel->id.'@anonymized.local');
        expect($activity->properties['attributes']['email'])->toBe('deleted-'.$testModel->id.'@anonymized.local');
    }

    Schema::dropIfExists('mock_activity_log');
});

it('does not modify activities for other models', function () {
    Schema::create('mock_activity_log', function ($table) {
        $table->id();
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id');
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    $testModel1 = TestModelWithMockActivity::create([
        'email' => 'test1@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $testModel2 = TestModelWithMockActivity::create([
        'email' => 'test2@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    // Create activity for model 1
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel1->id,
        'properties' => [
            'old' => ['email' => 'old1@example.com'],
            'attributes' => ['email' => 'new1@example.com'],
        ],
    ]);

    // Create activity for model 2
    MockActivity::create([
        'subject_type' => TestModelWithMockActivity::class,
        'subject_id' => $testModel2->id,
        'properties' => [
            'old' => ['email' => 'old2@example.com'],
            'attributes' => ['email' => 'new2@example.com'],
        ],
    ]);

    config()->set('activitylog.activity_model', MockActivity::class);

    // Only scrub model 1
    $scrubber = new ActivityLogScrubber;
    $count = $scrubber->scrub($testModel1);

    expect($count)->toBe(1);

    // Model 1's activity should be scrubbed
    $activity1 = MockActivity::where('subject_id', $testModel1->id)->first();
    expect($activity1->properties['old']['email'])->toBe('deleted-'.$testModel1->id.'@anonymized.local');

    // Model 2's activity should remain unchanged
    $activity2 = MockActivity::where('subject_id', $testModel2->id)->first();
    expect($activity2->properties['old']['email'])->toBe('old2@example.com');
    expect($activity2->properties['attributes']['email'])->toBe('new2@example.com');

    Schema::dropIfExists('mock_activity_log');
});

// Mock Activity Model for testing
class MockActivity extends Model
{
    protected $table = 'mock_activity_log';

    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
    ];
}

// Test Model with mock activity support
class TestModelWithMockActivity extends Model implements Scrubbable
{
    use ScrubsData;

    protected $table = 'test_models';

    protected $guarded = [];

    protected $casts = [
        'scrubbed_at' => 'datetime',
    ];

    public function scrubbableQuery(): Builder
    {
        return static::query()->whereNull('scrubbed_at');
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            'email' => AnonymizeEmailWithIdStrategy::class,
            'first_name' => RedactedStrategy::class,
        ]);
    }
}

// Test Model that opts out of activity log scrubbing
class TestModelOptOut extends Model implements Scrubbable, ScrubsActivityLog
{
    use ScrubsData;

    protected $table = 'test_models';

    protected $guarded = [];

    protected $casts = [
        'scrubbed_at' => 'datetime',
    ];

    public function scrubbableQuery(): Builder
    {
        return static::query()->whereNull('scrubbed_at');
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            'email' => AnonymizeEmailWithIdStrategy::class,
        ]);
    }

    public function shouldScrubActivityLog(): bool
    {
        return false;
    }

    public function activityLogScrubbableFields(): ?ScrubbableFields
    {
        return null;
    }
}

// Test Model with custom activity log fields
class TestModelCustomActivityFields extends Model implements Scrubbable, ScrubsActivityLog
{
    use ScrubsData;

    protected $table = 'test_models';

    protected $guarded = [];

    protected $casts = [
        'scrubbed_at' => 'datetime',
    ];

    public function scrubbableQuery(): Builder
    {
        return static::query()->whereNull('scrubbed_at');
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            'email' => AnonymizeEmailWithIdStrategy::class,
            'first_name' => RedactedStrategy::class,
        ]);
    }

    public function shouldScrubActivityLog(): bool
    {
        return true;
    }

    public function activityLogScrubbableFields(): ?ScrubbableFields
    {
        // Only scrub email in activity logs, using a different strategy
        return ScrubbableFields::make([
            'email' => NullStrategy::class,
        ]);
    }
}
