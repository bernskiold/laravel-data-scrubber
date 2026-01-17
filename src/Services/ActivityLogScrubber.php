<?php

namespace Bernskiold\LaravelDataScrubber\Services;

use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Contracts\ScrubsActivityLog;
use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Exceptions\StrategyException;
use Bernskiold\LaravelDataScrubber\ScrubbingStrategies;
use Illuminate\Database\Eloquent\Model;

class ActivityLogScrubber
{
    /**
     * The Activity model class to use.
     *
     * @var class-string
     */
    protected string $activityModel;

    /**
     * The property keys to scrub within the properties JSON column.
     *
     * @var array<string>
     */
    protected array $propertyKeys;

    public function __construct()
    {
        $this->activityModel = config('activitylog.activity_model', 'Spatie\Activitylog\Models\Activity');
        $this->propertyKeys = config('data-scrubber.activity_log.property_keys', ['old', 'attributes']);
    }

    /**
     * Scrub activity log records for the given model.
     *
     * @param Model&Scrubbable $model
     * @return int Number of activity records scrubbed
     * @throws StrategyException
     */
    public function scrub(Model $model): int
    {
        if (! class_exists($this->activityModel)) {
            return 0;
        }

        $scrubbableFields = $this->getScrubbableFields($model);
        if ($scrubbableFields->isEmpty()) {
            return 0;
        }

        $activities = $this->getActivityRecords($model);
        $count = 0;

        foreach ($activities as $activity) {
            if ($this->scrubActivity($activity, $scrubbableFields, $model)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the scrubbable fields configuration for the model.
     *
     * @param  Model&Scrubbable  $model
     */
    protected function getScrubbableFields(Model $model): ScrubbableFields
    {
        // Check if model has custom activity log fields
        if ($model instanceof ScrubsActivityLog) {
            $customFields = $model->activityLogScrubbableFields();
            if ($customFields !== null) {
                return $customFields;
            }
        }

        // Fall back to the model's standard scrubbable fields
        return $model->scrubbableFields();
    }

    /**
     * Get activity records for the given model.
     */
    protected function getActivityRecords(Model $model): \Illuminate\Support\Collection
    {
        $activityClass = $this->activityModel;

        return $activityClass::query()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->get();
    }

    /**
     * Scrub a single activity record.
     *
     * @param  Model  $activity  The activity record to scrub
     * @param  ScrubbableFields  $scrubbableFields
     * @param  Model&Scrubbable  $model  The original model being scrubbed
     * @return bool Whether the activity was modified
     *
     * @throws StrategyException
     */
    protected function scrubActivity(Model $activity, ScrubbableFields $scrubbableFields, Model $model): bool
    {
        $properties = $activity->properties;

        // Handle null or empty properties
        if ($properties === null || (is_countable($properties) && count($properties) === 0)) {
            return false;
        }

        // Convert to array if it's a Collection
        $propertiesArray = $properties instanceof \Illuminate\Support\Collection
            ? $properties->toArray()
            : (array) $properties;

        $modified = false;

        foreach ($this->propertyKeys as $key) {
            if (! array_key_exists($key, $propertiesArray)) {
                continue;
            }

            $keyData = $propertiesArray[$key];
            if (! is_array($keyData)) {
                continue;
            }

            $scrubbedData = $this->scrubPropertyData($keyData, $scrubbableFields, $model);

            if ($scrubbedData !== $keyData) {
                $propertiesArray[$key] = $scrubbedData;
                $modified = true;
            }
        }

        if ($modified) {
            $activity->properties = $propertiesArray;
            $activity->save();
        }

        return $modified;
    }

    /**
     * Scrub field values within a property data array.
     *
     * @param  array<string, mixed>  $data
     * @param  ScrubbableFields  $scrubbableFields
     * @param  Model&Scrubbable  $model
     * @return array<string, mixed>
     *
     * @throws StrategyException
     */
    protected function scrubPropertyData(array $data, ScrubbableFields $scrubbableFields, Model $model): array
    {
        foreach ($scrubbableFields as $field => $strategyConfig) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $strategy = $this->resolveStrategyInstance($strategyConfig);
            $data[$field] = $strategy->apply($data[$field], $model, $field);
        }

        return $data;
    }

    /**
     * Resolve a strategy instance from the configuration.
     *
     * @param ScrubStrategy|class-string<ScrubStrategy> $config
     * @throws StrategyException
     */
    protected function resolveStrategyInstance(mixed $config): ScrubStrategy
    {
        return ScrubbingStrategies::resolve($config);
    }
}
