<?php

namespace Bernskiold\LaravelDataScrubber\Data;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;

final class ScrubbedField
{
    /**
     * @param  string  $field  The name of the field that was scrubbed.
     * @param  mixed  $previous  The value before scrubbing.
     * @param  mixed  $scrubbed  The value after scrubbing.
     * @param  class-string<ScrubStrategy>  $strategy  The strategy class that was used.
     */
    public function __construct(
        public string $field,
        public mixed $previous,
        public mixed $scrubbed,
        public string $strategy,
    ) {}
}
