<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Data;

/**
 * Strategy details for configuration reporting.
 */
final class StrategyInfo
{
    /**
     * @param  string  $class  FQCN of strategy
     * @param  string  $label  Human-readable label from strategy->label()
     * @param  string  $description  Description from strategy->description()
     * @param  array<string, mixed>  $parameters  Extracted configuration parameters
     * @param  bool  $isConfigured  True if strategy was passed as instance vs class-string
     */
    public function __construct(
        public string $class,
        public string $label,
        public string $description,
        public array $parameters = [],
        public bool $isConfigured = false,
    ) {}

    /**
     * Get a short name for the strategy (without namespace).
     */
    public function shortName(): string
    {
        return class_basename($this->class);
    }

    /**
     * Check if the strategy has custom parameters.
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Format parameters as a readable string.
     */
    public function formatParameters(): string
    {
        if (! $this->hasParameters()) {
            return 'defaults';
        }

        $formatted = [];
        foreach ($this->parameters as $key => $value) {
            $formatted[] = $key.'='.$this->formatValue($value);
        }

        return implode(', ', $formatted);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'shortName' => $this->shortName(),
            'label' => $this->label,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'isConfigured' => $this->isConfigured,
        ];
    }

    /**
     * Format a parameter value for display.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return '['.implode(', ', array_map([$this, 'formatValue'], $value)).']';
        }

        if (is_object($value)) {
            if ($value instanceof \Closure) {
                return 'Closure';
            }

            return class_basename($value::class);
        }

        return (string) $value;
    }
}
