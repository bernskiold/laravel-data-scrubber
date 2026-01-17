<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Data;

use Illuminate\Support\Collection;

/**
 * Per-model scrubbing configuration.
 */
final class ModelConfiguration
{
    /**
     * @param  string  $modelClass  Fully qualified class name
     * @param  string  $shortName  Class basename
     * @param  Collection<int, FieldConfiguration>  $fields  Field configurations
     * @param  ScrubOptions  $options  Scrubbing options
     */
    public function __construct(
        public string $modelClass,
        public string $shortName,
        public Collection $fields,
        public ScrubOptions $options,
    ) {}

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function fieldNames(): array
    {
        return $this->fields->pluck('fieldName')->all();
    }

    /**
     * Get the total number of fields.
     */
    public function fieldCount(): int
    {
        return $this->fields->count();
    }

    /**
     * Get a specific field configuration by name.
     */
    public function field(string $fieldName): ?FieldConfiguration
    {
        return $this->fields->first(fn (FieldConfiguration $field) => $field->fieldName === $fieldName);
    }

    /**
     * Get the processing mode label.
     */
    public function processingMode(): string
    {
        return $this->options->scrubAsync ? 'Async' : 'Sync';
    }

    /**
     * Get all unique strategy classes used by this model.
     *
     * @return Collection<int, string>
     */
    public function uniqueStrategyClasses(): Collection
    {
        return $this->fields
            ->pluck('strategy.class')
            ->unique()
            ->values();
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'shortName' => $this->shortName,
            'fields' => $this->fields->map->toArray()->all(),
            'options' => [
                'logTimestamp' => $this->options->logTimestamp,
                'timestampColumn' => $this->options->timestampColumn,
                'chunkSize' => $this->options->chunkSize,
                'scrubAsync' => $this->options->scrubAsync,
            ],
        ];
    }
}
