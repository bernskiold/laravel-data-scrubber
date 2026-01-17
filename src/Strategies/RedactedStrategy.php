<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class RedactedStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $replacement = null,
    ) {
        $this->replacement ??= config('data-scrubber.strategies.redacted.replacement', '[REDACTED]');
    }

    /**
     * Apply the redacted strategy - replaces the value with the redaction text.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return $this->replacement;
    }

    public function label(): string
    {
        return 'Replace with [REDACTED]';
    }

    public function description(): string
    {
        return 'Replaces the value with the string "[REDACTED]".';
    }
}
