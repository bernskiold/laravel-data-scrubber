<?php

namespace Bernskiold\LaravelDataScrubber\Listeners;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubsActivityLog;
use Bernskiold\LaravelDataScrubber\Events\Scrubbed;
use Bernskiold\LaravelDataScrubber\Services\ActivityLogScrubber;

class ScrubActivityLogListener
{
    public function __construct(
        protected ActivityLogScrubber $scrubber
    ) {}

    public function handle(Scrubbed $event): void
    {
        // Check if Spatie Activity Log is installed
        if (! class_exists('Spatie\Activitylog\Models\Activity')) {
            return;
        }

        // Check if the model uses the LogsActivity trait
        if (! $this->modelLogsActivity($event->model)) {
            return;
        }

        // Check if model opts out of activity log scrubbing
        if ($event->model instanceof ScrubsActivityLog && ! $event->model->shouldScrubActivityLog()) {
            return;
        }

        // Delegate to the scrubber service
        $this->scrubber->scrub($event->model);
    }

    /**
     * Check if the model uses the LogsActivity trait.
     */
    protected function modelLogsActivity(object $model): bool
    {
        return in_array(
            'Spatie\Activitylog\Traits\LogsActivity',
            class_uses_recursive($model)
        );
    }
}
