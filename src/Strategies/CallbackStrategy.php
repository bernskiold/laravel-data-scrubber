<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Closure;
use Illuminate\Database\Eloquent\Model;

class CallbackStrategy implements ScrubStrategy
{
    /**
     * @param  Closure(mixed, Model, string): mixed  $callback
     */
    public function __construct(
        protected Closure $callback,
    ) {}

    /**
     * Apply the callback strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return ($this->callback)($value, $model, $field);
    }

    public function label(): string
    {
        return 'Apply custom callback';
    }

    public function description(): string
    {
        return 'Applies a custom closure to transform the value.';
    }
}
