<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class HashStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $algorithm = null,
    ) {
        $this->algorithm ??= config('data-scrubber.strategies.hash.algorithm', 'sha256');
    }

    /**
     * Apply the hash strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return hash($this->algorithm, (string) $value);
    }

    public function label(): string
    {
        return 'Hash the value';
    }

    public function description(): string
    {
        return 'Hashes the value preserving uniqueness while anonymizing.';
    }
}
