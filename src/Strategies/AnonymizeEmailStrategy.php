<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class AnonymizeEmailStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $replacement = null,
    ) {
        $this->replacement ??= config('data-scrubber.strategies.anonymize_email.replacement', 'anonymized@deleted.local');
    }

    /**
     * Apply the anonymize email strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return $this->replacement;
    }

    public function label(): string
    {
        return 'Replace with anonymized@deleted.local';
    }

    public function description(): string
    {
        return 'Replaces the value with a static anonymized email address.';
    }
}
