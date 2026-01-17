<?php

namespace Bernskiold\LaravelDataScrubber\Listeners;

use Bernskiold\LaravelDataScrubber\Events\Scrubbed;

class LogScrubbedActivity
{
    public function handle(Scrubbed $event): void
    {
        // Check if Spatie Activity Log is installed
        if (! function_exists('activity')) {
            return;
        }

        // Check if the model uses the LogsActivity trait
        if (! in_array('Spatie\Activitylog\Traits\LogsActivity', class_uses_recursive($event->model))) {
            return;
        }

        // Log the activity without exposing scrubbed data
        activity()
            ->performedOn($event->model)
            ->withProperties([
                'scrubbed_fields' => collect($event->scrubbedFields)
                    ->mapWithKeys(fn ($field) => [$field->field => class_basename($field->strategy)])
                    ->all(),
            ])
            ->event(config('data-scrubber.activity_log.event', 'data_scrubbed'))
            ->log(config('data-scrubber.activity_log.description', 'Record data was scrubbed'));
    }
}
