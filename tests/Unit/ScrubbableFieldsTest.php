<?php

use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;

it('creates an empty instance with make()', function () {
    $fields = ScrubbableFields::make();

    expect($fields)->toBeInstanceOf(ScrubbableFields::class);
    expect($fields->isEmpty())->toBeTrue();
    expect($fields->count())->toBe(0);
});

it('creates instance with array using make()', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
    ]);

    expect($fields->count())->toBe(2);
    expect($fields->has('email'))->toBeTrue();
    expect($fields->has('phone'))->toBeTrue();
    expect($fields->get('email'))->toBe(AnonymizeEmailStrategy::class);
});

it('adds single field with fluent method', function () {
    $fields = ScrubbableFields::make()
        ->add('email', AnonymizeEmailStrategy::class);

    expect($fields->count())->toBe(1);
    expect($fields->has('email'))->toBeTrue();
    expect($fields->get('email'))->toBe(AnonymizeEmailStrategy::class);
});

it('adds multiple fields with array using add()', function () {
    $fields = ScrubbableFields::make()
        ->add([
            'email' => AnonymizeEmailStrategy::class,
            'phone' => NullStrategy::class,
        ]);

    expect($fields->count())->toBe(2);
    expect($fields->has('email'))->toBeTrue();
    expect($fields->has('phone'))->toBeTrue();
});

it('merges fields when adding array', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
    ])->add([
        'phone' => NullStrategy::class,
        'ssn' => RedactedStrategy::class,
    ]);

    expect($fields->count())->toBe(3);
    expect($fields->fields())->toBe(['email', 'phone', 'ssn']);
});

it('overwrites existing field when adding with same key', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
    ])->add('email', NullStrategy::class);

    expect($fields->count())->toBe(1);
    expect($fields->get('email'))->toBe(NullStrategy::class);
});

it('removes a field', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
    ])->remove('email');

    expect($fields->count())->toBe(1);
    expect($fields->has('email'))->toBeFalse();
    expect($fields->has('phone'))->toBeTrue();
});

it('returns all field names with fields()', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
    ]);

    expect($fields->fields())->toBe(['email', 'phone']);
});

it('returns all strategies with strategies()', function () {
    $strategies = [
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
    ];

    $fields = ScrubbableFields::make($strategies);

    expect($fields->strategies())->toBe($strategies);
});

it('checks if field exists with has()', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
    ]);

    expect($fields->has('email'))->toBeTrue();
    expect($fields->has('phone'))->toBeFalse();
});

it('returns null for non-existent field with get()', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
    ]);

    expect($fields->get('phone'))->toBeNull();
});

it('checks isEmpty correctly', function () {
    $empty = ScrubbableFields::make();
    $notEmpty = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
    ]);

    expect($empty->isEmpty())->toBeTrue();
    expect($notEmpty->isEmpty())->toBeFalse();
});

it('checks isNotEmpty correctly', function () {
    $empty = ScrubbableFields::make();
    $notEmpty = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
    ]);

    expect($empty->isNotEmpty())->toBeFalse();
    expect($notEmpty->isNotEmpty())->toBeTrue();
});

it('converts to array with toArray()', function () {
    $strategies = [
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
    ];

    $fields = ScrubbableFields::make($strategies);

    expect($fields->toArray())->toBe($strategies);
});

it('is iterable with foreach', function () {
    $strategies = [
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
    ];

    $fields = ScrubbableFields::make($strategies);
    $result = [];

    foreach ($fields as $field => $strategy) {
        $result[$field] = $strategy;
    }

    expect($result)->toBe($strategies);
});

it('is countable with count()', function () {
    $fields = ScrubbableFields::make([
        'email' => AnonymizeEmailStrategy::class,
        'phone' => NullStrategy::class,
        'ssn' => RedactedStrategy::class,
    ]);

    expect(count($fields))->toBe(3);
});

it('supports Conditionable when() method', function () {
    $fields = ScrubbableFields::make()
        ->add('email', AnonymizeEmailStrategy::class)
        ->when(true, fn ($c) => $c->add('phone', NullStrategy::class))
        ->when(false, fn ($c) => $c->add('ssn', RedactedStrategy::class));

    expect($fields->count())->toBe(2);
    expect($fields->has('email'))->toBeTrue();
    expect($fields->has('phone'))->toBeTrue();
    expect($fields->has('ssn'))->toBeFalse();
});

it('supports Conditionable unless() method', function () {
    $fields = ScrubbableFields::make()
        ->add('email', AnonymizeEmailStrategy::class)
        ->unless(false, fn ($c) => $c->add('phone', NullStrategy::class))
        ->unless(true, fn ($c) => $c->add('ssn', RedactedStrategy::class));

    expect($fields->count())->toBe(2);
    expect($fields->has('email'))->toBeTrue();
    expect($fields->has('phone'))->toBeTrue();
    expect($fields->has('ssn'))->toBeFalse();
});

it('supports fluent chaining', function () {
    $fields = ScrubbableFields::make()
        ->add('email', AnonymizeEmailStrategy::class)
        ->add('phone', NullStrategy::class)
        ->remove('phone')
        ->add('ssn', RedactedStrategy::class);

    expect($fields->count())->toBe(2);
    expect($fields->fields())->toBe(['email', 'ssn']);
});

it('accepts strategy instances', function () {
    $strategy = new NullStrategy;

    $fields = ScrubbableFields::make()
        ->add('phone', $strategy);

    expect($fields->get('phone'))->toBe($strategy);
});
