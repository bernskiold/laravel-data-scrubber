<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Data;

/**
 * Per-field scrubbing configuration.
 */
final class FieldConfiguration
{
    public function __construct(
        public string $fieldName,
        public StrategyInfo $strategy,
    ) {}

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fieldName' => $this->fieldName,
            'strategy' => $this->strategy->toArray(),
        ];
    }
}
