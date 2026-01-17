<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Closure;
use Illuminate\Database\Eloquent\Model;

class ConditionalStrategy implements ScrubStrategy
{
    protected ScrubStrategy $resolvedThenStrategy;

    protected ?ScrubStrategy $resolvedElseStrategy;

    /**
     * @param  Closure(mixed $value, Model $model, string $field): bool  $condition
     * @param  ScrubStrategy|class-string<ScrubStrategy>  $thenStrategy
     * @param  ScrubStrategy|class-string<ScrubStrategy>|null  $elseStrategy
     */
    public function __construct(
        protected Closure $condition,
        ScrubStrategy|string $thenStrategy,
        ScrubStrategy|string|null $elseStrategy = null,
    ) {
        $this->resolvedThenStrategy = $this->resolveStrategy($thenStrategy);
        $this->resolvedElseStrategy = $elseStrategy !== null ? $this->resolveStrategy($elseStrategy) : null;
    }

    /**
     * Apply the conditional strategy - applies different strategies based on a condition.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        $conditionMet = ($this->condition)($value, $model, $field);

        if ($conditionMet) {
            return $this->resolvedThenStrategy->apply($value, $model, $field);
        }

        if ($this->resolvedElseStrategy !== null) {
            return $this->resolvedElseStrategy->apply($value, $model, $field);
        }

        // If no else strategy and condition not met, return value unchanged
        return $value;
    }

    /**
     * Resolve a strategy from a class string or return the instance.
     */
    protected function resolveStrategy(ScrubStrategy|string $strategy): ScrubStrategy
    {
        if ($strategy instanceof ScrubStrategy) {
            return $strategy;
        }

        return new $strategy;
    }

    public function label(): string
    {
        return 'Conditional scrubbing';
    }

    public function description(): string
    {
        return 'Applies different scrubbing strategies based on a condition.';
    }
}
