<?php

namespace Bernskiold\LaravelDataScrubber\Concerns;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Bernskiold\LaravelDataScrubber\Data\ScrubbedField;
use Bernskiold\LaravelDataScrubber\Events\Scrubbed;
use Bernskiold\LaravelDataScrubber\Data\ScrubOptions;
use Bernskiold\LaravelDataScrubber\ScrubbingStrategies;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ScrubsData
{
    /**
     * Get the scrubbing options for this model.
     *
     * Override this method in your model to customize scrubbing behavior.
     */
    public function getScrubOptions(): ScrubOptions
    {
        return ScrubOptions::defaults();
    }

    /**
     * Scrub the model's PII/sensitive data.
     *
     * Applies the scrubbing strategies defined in scrubbableFields()
     * and optionally updates the scrub timestamp.
     */
    public function scrub(): bool
    {
        $scrubbedFields = [];
        $options = $this->getScrubOptions();

        foreach ($this->scrubbableFields() as $field => $strategyConfig) {
            $strategy = $this->resolveStrategyInstance($strategyConfig);
            $previousValue = $this->{$field};

            $scrubbedValue = $strategy->apply(
                $previousValue,
                $this,
                $field
            );

            $this->{$field} = $scrubbedValue;

            $scrubbedFields[$field] = new ScrubbedField(
                field: $field,
                previous: $previousValue,
                scrubbed: $scrubbedValue,
                strategy: $strategy::class,
            );
        }

        if ($options->logTimestamp) {
            $this->{$options->timestampColumn} = now();
        }

        $saved = $this->save();

        if ($saved) {
            Scrubbed::dispatch($this, $scrubbedFields);
        }

        return $saved;
    }

    /**
     * Check if this record has already been scrubbed.
     *
     * Only works when timestamp logging is enabled.
     */
    public function hasBeenScrubbed(): bool
    {
        $options = $this->getScrubOptions();

        if (! $options->logTimestamp) {
            return false;
        }

        return $this->{$options->timestampColumn} !== null;
    }

    /**
     * Preview the scrubbed values without saving.
     *
     * Returns an array of field names to their scrubbed values.
     *
     * @return array<string, mixed>
     */
    public function previewScrub(): array
    {
        $preview = [];

        foreach ($this->scrubbableFields() as $field => $strategyConfig) {
            $strategy = $this->resolveStrategyInstance($strategyConfig);

            $preview[$field] = [
                'current' => $this->{$field},
                'scrubbed' => $strategy->apply(
                    $this->{$field},
                    $this,
                    $field
                ),
                'strategy' => $strategy::class,
            ];
        }

        return $preview;
    }

    /**
     * Scope a query to only include records that have not been scrubbed.
     *
     * Only works when timestamp logging is enabled.
     */
    public function scopeNotScrubbed(Builder $query): Builder
    {
        $options = $this->getScrubOptions();

        if (! $options->logTimestamp) {
            return $query;
        }

        return $query->whereNull($options->timestampColumn);
    }

    /**
     * Scope a query to only include records that have been scrubbed.
     *
     * Only works when timestamp logging is enabled.
     */
    public function scopeScrubbed(Builder $query): Builder
    {
        $options = $this->getScrubOptions();

        if (! $options->logTimestamp) {
            return $query;
        }

        return $query->whereNotNull($options->timestampColumn);
    }

    /**
     * Resolve a strategy instance from the configuration.
     *
     * Supports:
     * - ScrubStrategy instance: used as-is
     * - Class-string: instantiated
     *
     * @param  ScrubStrategy|class-string<ScrubStrategy>  $config
     */
    protected function resolveStrategyInstance(mixed $config): ScrubStrategy
    {
        return ScrubbingStrategies::resolve($config);
    }
}
