<?php

use Bernskiold\LaravelDataScrubber\Data\ScrubbedField;
use Bernskiold\LaravelDataScrubber\Events\Scrubbed;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeFirstNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeLastNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;
use Illuminate\Support\Facades\Event;

it('dispatches Scrubbed event when a model is scrubbed', function () {
    Event::fake([Scrubbed::class]);

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
        'notes' => 'Some private notes',
    ]);
    $model->delete();

    $model->scrub();

    Event::assertDispatched(Scrubbed::class);
});

it('includes the correct model in the event', function () {
    Event::fake([Scrubbed::class]);

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $model->delete();

    $model->scrub();

    Event::assertDispatched(Scrubbed::class, function (Scrubbed $event) use ($model) {
        return $event->model->is($model);
    });
});

it('includes ScrubbedField DTOs for each field', function () {
    Event::fake([Scrubbed::class]);

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'ssn' => '123-45-6789',
        'notes' => 'Some private notes',
    ]);
    $model->delete();

    $model->scrub();

    Event::assertDispatched(Scrubbed::class, function (Scrubbed $event) use ($model) {
        $fields = $event->scrubbedFields;

        // Check that all fields are ScrubbedField instances
        foreach ($fields as $field) {
            expect($field)->toBeInstanceOf(ScrubbedField::class);
        }

        // Check email field
        expect($fields['email']->field)->toBe('email');
        expect($fields['email']->previous)->toBe('john@example.com');
        expect($fields['email']->scrubbed)->toBe("deleted-{$model->id}@anonymized.local");
        expect($fields['email']->strategy)->toBe(AnonymizeEmailWithIdStrategy::class);

        // Check first_name field
        expect($fields['first_name']->field)->toBe('first_name');
        expect($fields['first_name']->previous)->toBe('John');
        expect($fields['first_name']->scrubbed)->toBe('Deleted');
        expect($fields['first_name']->strategy)->toBe(AnonymizeFirstNameStrategy::class);

        // Check last_name field
        expect($fields['last_name']->field)->toBe('last_name');
        expect($fields['last_name']->previous)->toBe('Doe');
        expect($fields['last_name']->scrubbed)->toBe('User');
        expect($fields['last_name']->strategy)->toBe(AnonymizeLastNameStrategy::class);

        // Check phone field
        expect($fields['phone']->field)->toBe('phone');
        expect($fields['phone']->previous)->toBe('555-1234');
        expect($fields['phone']->scrubbed)->toBeNull();
        expect($fields['phone']->strategy)->toBe(NullStrategy::class);

        // Check ssn field
        expect($fields['ssn']->field)->toBe('ssn');
        expect($fields['ssn']->previous)->toBe('123-45-6789');
        expect($fields['ssn']->scrubbed)->toBe('[REDACTED]');
        expect($fields['ssn']->strategy)->toBe(RedactedStrategy::class);

        // Check notes field
        expect($fields['notes']->field)->toBe('notes');
        expect($fields['notes']->previous)->toBe('Some private notes');
        expect($fields['notes']->scrubbed)->toBe(hash('sha256', 'Some private notes'));
        expect($fields['notes']->strategy)->toBe(HashStrategy::class);

        return true;
    });
});

it('provides helper methods on the event', function () {
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

    $model = new TestModel;
    $event = new Scrubbed($model, $scrubbedFields);

    // Test fieldNames
    expect($event->fieldNames())->toBe(['email', 'phone']);

    // Test field
    expect($event->field('email'))->toBeInstanceOf(ScrubbedField::class);
    expect($event->field('email')->previous)->toBe('john@example.com');
    expect($event->field('email')->scrubbed)->toBe('deleted-1@anonymized.local');
    expect($event->field('email')->strategy)->toBe(AnonymizeEmailWithIdStrategy::class);
    expect($event->field('nonexistent'))->toBeNull();
});

it('allows listening to the event', function () {
    $receivedEvent = null;

    Event::listen(Scrubbed::class, function (Scrubbed $event) use (&$receivedEvent) {
        $receivedEvent = $event;
    });

    $model = TestModel::create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $model->delete();

    $model->scrub();

    expect($receivedEvent)->not->toBeNull();
    expect($receivedEvent->model->id)->toBe($model->id);
    expect($receivedEvent->scrubbedFields)->toHaveKeys(['email', 'first_name', 'last_name']);
    expect($receivedEvent->field('email'))->toBeInstanceOf(ScrubbedField::class);
});
