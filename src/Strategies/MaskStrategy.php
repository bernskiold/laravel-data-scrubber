<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class MaskStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?int $visibleStart = null,
        protected ?int $visibleEnd = null,
        protected ?string $maskChar = null,
    ) {
        $this->visibleStart ??= config('data-scrubber.strategies.mask.visible_start', 2);
        $this->visibleEnd ??= config('data-scrubber.strategies.mask.visible_end', 2);
        $this->maskChar ??= config('data-scrubber.strategies.mask.mask_char', '*');
    }

    /**
     * Apply the mask strategy - shows start and end characters, masks the middle.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $length = mb_strlen($value);

        // If the string is too short to mask, return all mask characters
        if ($length <= $this->visibleStart + $this->visibleEnd) {
            return str_repeat($this->maskChar, $length);
        }

        $start = mb_substr($value, 0, $this->visibleStart);
        $end = mb_substr($value, -$this->visibleEnd);
        $maskLength = $length - $this->visibleStart - $this->visibleEnd;
        $mask = str_repeat($this->maskChar, $maskLength);

        return $start.$mask.$end;
    }

    public function label(): string
    {
        return 'Mask middle characters';
    }

    public function description(): string
    {
        return 'Masks the middle portion of the value, keeping the first and last characters visible.';
    }
}
