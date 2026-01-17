<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class TruncateStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?int $keepChars = null,
        protected ?string $suffix = null,
    ) {
        $this->keepChars ??= config('data-scrubber.strategies.truncate.keep_chars', 3);
        $this->suffix ??= config('data-scrubber.strategies.truncate.suffix', '***');
    }

    /**
     * Apply the truncate strategy - keeps the first N characters and appends a suffix.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $length = mb_strlen($value);

        // If the string is shorter than or equal to keepChars, mask the entire string
        if ($length <= $this->keepChars) {
            return $this->suffix;
        }

        return mb_substr($value, 0, $this->keepChars).$this->suffix;
    }

    public function label(): string
    {
        return 'Truncate and suffix';
    }

    public function description(): string
    {
        return 'Keeps the first few characters and replaces the rest with a suffix.';
    }
}
