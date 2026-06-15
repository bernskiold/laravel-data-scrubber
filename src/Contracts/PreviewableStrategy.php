<?php

namespace Bernskiold\LaravelDataScrubber\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by strategies whose apply() method performs side effects
 * (e.g. deleting a file) in addition to computing the scrubbed value.
 *
 * During a dry run / preview, preview() is called instead of apply() so the
 * scrubbed value can be shown without performing the side effect.
 */
interface PreviewableStrategy
{
    /**
     * Compute the scrubbed value WITHOUT performing any side effects.
     *
     * @param  mixed  $value  The current value to scrub
     * @param  Model  $model  The model instance being scrubbed
     * @param  string  $field  The field name being scrubbed
     * @return mixed The value that apply() would produce
     */
    public function preview(mixed $value, Model $model, string $field): mixed;
}
