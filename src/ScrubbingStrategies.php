<?php

namespace Bernskiold\LaravelDataScrubber;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Bernskiold\LaravelDataScrubber\Exceptions\StrategyException;

class ScrubbingStrategies
{
    /**
     * The registered strategy classes.
     *
     * @var array<class-string<ScrubStrategy>>
     */
    protected static array $strategies = [];

    /**
     * Register one or more strategy classes.
     *
     * @param  class-string<ScrubStrategy>|array<class-string<ScrubStrategy>>  $strategies
     */
    public static function register(string|array $strategies): void
    {
        $strategies = is_array($strategies) ? $strategies : [$strategies];

        foreach ($strategies as $strategyClass) {
            if (! in_array($strategyClass, static::$strategies, true)) {
                static::$strategies[] = $strategyClass;
            }
        }
    }

    /**
     * Check if a strategy class is registered.
     *
     * @param  class-string<ScrubStrategy>  $strategyClass
     */
    public static function has(string $strategyClass): bool
    {
        return in_array($strategyClass, static::$strategies, true);
    }

    /**
     * Get all registered strategy classes.
     *
     * @return array<class-string<ScrubStrategy>>
     */
    public static function all(): array
    {
        return static::$strategies;
    }

    /**
     * Remove a strategy class from the registry.
     *
     * @param  class-string<ScrubStrategy>  $strategyClass
     */
    public static function forget(string $strategyClass): void
    {
        static::$strategies = array_values(
            array_filter(static::$strategies, fn ($class) => $class !== $strategyClass)
        );
    }

    /**
     * Clear all registered strategies.
     *
     * Primarily useful for testing.
     */
    public static function flush(): void
    {
        static::$strategies = [];
    }

    /**
     * Resolve a strategy from various input types.
     *
     * Supports:
     * - ScrubStrategy instance: returned as-is
     * - Class-string: instantiated and returned
     *
     * @param  ScrubStrategy|class-string<ScrubStrategy>  $strategy
     *
     * @throws StrategyException If the strategy cannot be resolved
     */
    public static function resolve(mixed $strategy): ScrubStrategy
    {
        // Already a strategy instance
        if ($strategy instanceof ScrubStrategy) {
            return $strategy;
        }

        // Class-string - instantiate it
        if (is_string($strategy) && class_exists($strategy)) {
            $instance = new $strategy;

            if (! $instance instanceof ScrubStrategy) {
                throw StrategyException::invalidClass($strategy);
            }

            return $instance;
        }

        throw StrategyException::invalidType();
    }
}
