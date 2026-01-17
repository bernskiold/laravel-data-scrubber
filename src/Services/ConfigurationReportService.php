<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Services;

use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Bernskiold\LaravelDataScrubber\Data\ConfigurationReport;
use Bernskiold\LaravelDataScrubber\Data\FieldConfiguration;
use Bernskiold\LaravelDataScrubber\Data\ModelConfiguration;
use Bernskiold\LaravelDataScrubber\Data\StrategyInfo;
use Bernskiold\LaravelDataScrubber\ScrubbingStrategies;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionProperty;

class ConfigurationReportService
{
    public function __construct(
        protected ModelDiscoveryService $modelDiscovery,
    ) {}

    /**
     * Generate a complete configuration report.
     */
    public function generate(): ConfigurationReport
    {
        $paths = $this->modelDiscovery->getConfiguredPaths();
        $modelClasses = $this->modelDiscovery->discoverInPaths($paths);

        $models = collect($modelClasses)
            ->map(fn (string $modelClass) => $this->buildModelConfiguration($modelClass))
            ->values();

        return new ConfigurationReport(
            models: $models,
            scannedPaths: $paths,
            generatedAt: new DateTimeImmutable,
        );
    }

    /**
     * Build configuration for a single model.
     *
     * @param  class-string<Model&Scrubbable>  $modelClass
     */
    public function buildModelConfiguration(string $modelClass): ModelConfiguration
    {
        /** @var Model&Scrubbable $instance */
        $instance = new $modelClass;

        $scrubbableFields = $instance->scrubbableFields();
        $options = $instance->getScrubOptions();

        $fields = collect($scrubbableFields->strategies())
            ->map(fn ($strategyConfig, string $fieldName) => new FieldConfiguration(
                fieldName: $fieldName,
                strategy: $this->buildStrategyInfo($strategyConfig),
            ))
            ->values();

        return new ModelConfiguration(
            modelClass: $modelClass,
            shortName: class_basename($modelClass),
            fields: $fields,
            options: $options,
        );
    }

    /**
     * Build strategy info from a strategy configuration.
     *
     * @param  ScrubStrategy|class-string<ScrubStrategy>  $strategyConfig
     */
    public function buildStrategyInfo(mixed $strategyConfig): StrategyInfo
    {
        $isConfigured = $strategyConfig instanceof ScrubStrategy;

        // Resolve to an instance
        $strategy = ScrubbingStrategies::resolve($strategyConfig);

        return new StrategyInfo(
            class: $strategy::class,
            label: $strategy->label(),
            description: $strategy->description(),
            parameters: $this->extractStrategyParameters($strategy),
            isConfigured: $isConfigured,
        );
    }

    /**
     * Extract parameters from a strategy instance using reflection.
     *
     * @return array<string, mixed>
     */
    public function extractStrategyParameters(ScrubStrategy $strategy): array
    {
        $parameters = [];
        $reflection = new ReflectionClass($strategy);

        $properties = $reflection->getProperties(
            ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC
        );

        foreach ($properties as $property) {
            // Skip properties that are other strategy instances (already resolved)
            if ($this->shouldSkipProperty($property)) {
                continue;
            }

            $property->setAccessible(true);
            $value = $property->getValue($strategy);

            // Handle special types
            $parameters[$property->getName()] = $this->formatParameterValue($value);
        }

        return $parameters;
    }

    /**
     * Check if a property should be skipped during parameter extraction.
     */
    protected function shouldSkipProperty(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if ($type === null) {
            return false;
        }

        // Get the type name
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        // Skip ScrubStrategy properties (like resolvedThenStrategy in ConditionalStrategy)
        if ($typeName && (is_a($typeName, ScrubStrategy::class, true) || $typeName === ScrubStrategy::class)) {
            return true;
        }

        return false;
    }

    /**
     * Format a parameter value for storage/display.
     */
    protected function formatParameterValue(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return 'Closure';
        }

        if ($value instanceof ScrubStrategy) {
            return class_basename($value::class);
        }

        if (is_array($value)) {
            return $this->formatArrayValue($value);
        }

        return $value;
    }

    /**
     * Format an array value, handling nested strategies.
     *
     * @return array<string, mixed>
     */
    protected function formatArrayValue(array $value): array
    {
        $formatted = [];

        foreach ($value as $key => $item) {
            if ($item instanceof ScrubStrategy) {
                $formatted[$key] = class_basename($item::class);
            } elseif ($item instanceof Closure) {
                $formatted[$key] = 'Closure';
            } elseif (is_string($item) && class_exists($item) && is_a($item, ScrubStrategy::class, true)) {
                $formatted[$key] = class_basename($item);
            } elseif (is_array($item)) {
                $formatted[$key] = $this->formatArrayValue($item);
            } else {
                $formatted[$key] = $item;
            }
        }

        return $formatted;
    }
}
