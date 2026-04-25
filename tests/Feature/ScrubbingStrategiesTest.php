<?php

use Bernskiold\LaravelDataScrubber\Exceptions\StrategyException;
use Bernskiold\LaravelDataScrubber\ScrubbingStrategies;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;

beforeEach(function () {
    ScrubbingStrategies::flush();
});

afterEach(function () {
    ScrubbingStrategies::flush();
});

it('registers a single strategy class', function () {
    ScrubbingStrategies::register(NullStrategy::class);

    expect(ScrubbingStrategies::has(NullStrategy::class))->toBeTrue();
});

it('registers multiple strategy classes at once', function () {
    ScrubbingStrategies::register([
        NullStrategy::class,
        RedactedStrategy::class,
    ]);

    expect(ScrubbingStrategies::has(NullStrategy::class))->toBeTrue();
    expect(ScrubbingStrategies::has(RedactedStrategy::class))->toBeTrue();
});

it('does not register duplicate strategy classes', function () {
    ScrubbingStrategies::register(NullStrategy::class);
    ScrubbingStrategies::register(NullStrategy::class);

    expect(ScrubbingStrategies::all())->toHaveCount(1);
});

it('returns false for unregistered strategy class', function () {
    expect(ScrubbingStrategies::has(NullStrategy::class))->toBeFalse();
});

it('gets all registered strategy classes', function () {
    ScrubbingStrategies::register([
        NullStrategy::class,
        RedactedStrategy::class,
    ]);

    $all = ScrubbingStrategies::all();

    expect($all)->toHaveCount(2);
    expect($all)->toContain(NullStrategy::class);
    expect($all)->toContain(RedactedStrategy::class);
});

it('forgets a strategy class', function () {
    ScrubbingStrategies::register(NullStrategy::class);

    expect(ScrubbingStrategies::has(NullStrategy::class))->toBeTrue();

    ScrubbingStrategies::forget(NullStrategy::class);

    expect(ScrubbingStrategies::has(NullStrategy::class))->toBeFalse();
});

it('flushes all strategies', function () {
    ScrubbingStrategies::register([
        NullStrategy::class,
        RedactedStrategy::class,
    ]);

    expect(ScrubbingStrategies::all())->toHaveCount(2);

    ScrubbingStrategies::flush();

    expect(ScrubbingStrategies::all())->toBeEmpty();
});

it('resolves strategy instance as-is', function () {
    $strategy = new NullStrategy;

    $resolved = ScrubbingStrategies::resolve($strategy);

    expect($resolved)->toBe($strategy);
});

it('resolves strategy from class-string', function () {
    $resolved = ScrubbingStrategies::resolve(NullStrategy::class);

    expect($resolved)->toBeInstanceOf(NullStrategy::class);
});

it('throws exception when resolving invalid class-string', function () {
    expect(fn () => ScrubbingStrategies::resolve(stdClass::class))
        ->toThrow(StrategyException::class, 'does not implement ScrubStrategy interface');
});

it('throws exception when resolving non-existent class', function () {
    expect(fn () => ScrubbingStrategies::resolve('NonExistentClass'))
        ->toThrow(StrategyException::class, 'Strategy must be a ScrubStrategy instance or class-string');
});
