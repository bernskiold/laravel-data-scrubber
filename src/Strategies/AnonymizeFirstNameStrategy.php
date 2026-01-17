<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class AnonymizeFirstNameStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $replacement = null,
    ) {
        $this->replacement ??= config('data-scrubber.strategies.anonymize_first_name.replacement', 'Deleted');
    }

    /**
     * Apply the anonymize first name strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return $this->replacement;
    }

    public function label(): string
    {
        return 'Replace with "Deleted"';
    }

    public function description(): string
    {
        return 'Replaces the value with "Deleted" - useful for first name fields.';
    }
}
