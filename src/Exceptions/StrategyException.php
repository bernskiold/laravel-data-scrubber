<?php

namespace Bernskiold\LaravelDataScrubber\Exceptions;

use Exception;

class StrategyException extends Exception
{
    /**
     * Create an exception for when a class does not implement the ScrubStrategy interface.
     */
    public static function invalidClass(string $class): self
    {
        return new self(
            "Class '{$class}' does not implement ScrubStrategy interface."
        );
    }

    /**
     * Create an exception for when an invalid strategy type is provided.
     */
    public static function invalidType(): self
    {
        return new self(
            'Strategy must be a ScrubStrategy instance or class-string.'
        );
    }
}
