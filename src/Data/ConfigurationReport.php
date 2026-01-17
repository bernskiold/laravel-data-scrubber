<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Data;

use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Top-level container for the configuration report.
 */
final class ConfigurationReport
{
    /**
     * @param  Collection<int, ModelConfiguration>  $models  Model configurations
     * @param  array<int, string>  $scannedPaths  Paths that were scanned for models
     * @param  DateTimeImmutable  $generatedAt  When the report was generated
     */
    public function __construct(
        public Collection $models,
        public array $scannedPaths,
        public DateTimeImmutable $generatedAt,
    ) {}

    /**
     * Get the total number of models.
     */
    public function modelCount(): int
    {
        return $this->models->count();
    }

    /**
     * Get the total number of fields across all models.
     */
    public function totalFieldCount(): int
    {
        return $this->models->sum(fn (ModelConfiguration $model) => $model->fieldCount());
    }

    /**
     * Check if any models were found.
     */
    public function hasModels(): bool
    {
        return $this->models->isNotEmpty();
    }

    /**
     * Get a specific model configuration by class name or short name.
     */
    public function model(string $name): ?ModelConfiguration
    {
        return $this->models->first(
            fn (ModelConfiguration $model) => $model->modelClass === $name || $model->shortName === $name
        );
    }

    /**
     * Get all unique strategies used across all models.
     *
     * @return Collection<int, StrategyInfo>
     */
    public function uniqueStrategies(): Collection
    {
        return $this->models
            ->flatMap(fn (ModelConfiguration $model) => $model->fields->pluck('strategy'))
            ->unique(fn (StrategyInfo $strategy) => $strategy->class)
            ->values();
    }

    /**
     * Get the count of unique strategies.
     */
    public function uniqueStrategyCount(): int
    {
        return $this->uniqueStrategies()->count();
    }

    /**
     * Filter models by name (supports partial matching).
     *
     * @return Collection<int, ModelConfiguration>
     */
    public function filterModels(string $filter): Collection
    {
        $filter = strtolower($filter);

        return $this->models->filter(
            fn (ModelConfiguration $model) => str_contains(strtolower($model->modelClass), $filter) ||
                str_contains(strtolower($model->shortName), $filter)
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'models' => $this->models->map->toArray()->all(),
            'scannedPaths' => $this->scannedPaths,
            'generatedAt' => $this->generatedAt->format('Y-m-d H:i:s'),
            'summary' => [
                'modelCount' => $this->modelCount(),
                'totalFieldCount' => $this->totalFieldCount(),
                'uniqueStrategyCount' => $this->uniqueStrategyCount(),
            ],
        ];
    }
}
