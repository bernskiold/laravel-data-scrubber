<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class AnonymizeLastNameStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $replacement = null,
    ) {
        $this->replacement ??= config('data-scrubber.strategies.anonymize_last_name.replacement', 'User');
    }

    /**
     * Apply the anonymize last name strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return $this->replacement;
    }

    public function label(): string
    {
        return 'Replace with "User"';
    }

    public function description(): string
    {
        return 'Replaces the value with "User" - useful for last name fields.';
    }
}
