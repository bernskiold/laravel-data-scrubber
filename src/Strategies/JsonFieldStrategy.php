<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class JsonFieldStrategy implements ScrubStrategy
{
    /**
     * @param  array<string, ScrubStrategy|class-string<ScrubStrategy>>  $fieldStrategies
     */
    public function __construct(
        protected array $fieldStrategies = [],
    ) {}

    /**
     * Apply the JSON field strategy - scrubs specific keys in a JSON/array value.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle JSON string input
        $isString = is_string($value);
        if ($isString) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $value; // Return original if not valid JSON
            }
            $value = $decoded;
        }

        // Handle array input
        if (! is_array($value)) {
            return $value;
        }

        $result = $this->scrubArray($value, $model, $field);

        // Return in the same format as input
        return $isString ? json_encode($result) : $result;
    }

    /**
     * Recursively scrub array keys based on configured strategies.
     */
    protected function scrubArray(array $data, Model $model, string $field): array
    {
        foreach ($this->fieldStrategies as $key => $strategy) {
            if (array_key_exists($key, $data)) {
                $strategyInstance = $this->resolveStrategy($strategy);
                $data[$key] = $strategyInstance->apply($data[$key], $model, "{$field}.{$key}");
            }
        }

        return $data;
    }

    /**
     * Resolve a strategy from a class string or return the instance.
     */
    protected function resolveStrategy(ScrubStrategy|string $strategy): ScrubStrategy
    {
        if ($strategy instanceof ScrubStrategy) {
            return $strategy;
        }

        return new $strategy;
    }

    public function label(): string
    {
        return 'Scrub JSON fields';
    }

    public function description(): string
    {
        return 'Applies different scrubbing strategies to specific keys within a JSON or array value.';
    }
}
