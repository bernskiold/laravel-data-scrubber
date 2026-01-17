<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class NullStrategy implements ScrubStrategy
{
    /**
     * Apply the null strategy - sets the value to null.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return null;
    }

    /**
     * Get a human-readable label for the strategy.
     */
    public function label(): string
    {
        return 'Set to NULL';
    }

    /**
     * Get a description of what the strategy does.
     */
    public function description(): string
    {
        return 'Sets the field value to NULL.';
    }
}
