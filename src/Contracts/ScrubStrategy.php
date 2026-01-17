<?php

namespace Bernskiold\LaravelDataScrubber\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ScrubStrategy
{
    /**
     * Apply the scrubbing strategy to a value.
     *
     * @param  mixed  $value  The current value to scrub
     * @param  Model  $model  The model instance being scrubbed
     * @param  string  $field  The field name being scrubbed
     * @return mixed The scrubbed value
     */
    public function apply(mixed $value, Model $model, string $field): mixed;

    /**
     * Get a human-readable label for the strategy.
     */
    public function label(): string;

    /**
     * Get a description of what the strategy does.
     */
    public function description(): string;
}
