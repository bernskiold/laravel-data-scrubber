<?php

namespace Bernskiold\LaravelDataScrubber\Contracts;

use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;

/**
 * Optional interface for models to customize activity log scrubbing behavior.
 *
 * Implement this interface on your Scrubbable models to:
 * - Opt out of activity log scrubbing entirely
 * - Define custom scrubbing strategies for activity log fields
 */
interface ScrubsActivityLog
{
    /**
     * Determine whether activity log records should be scrubbed for this model.
     *
     * Return false to skip scrubbing activity logs when the model is scrubbed.
     */
    public function shouldScrubActivityLog(): bool;

    /**
     * Get custom scrubbable fields for activity log records.
     *
     * Return null to use the same strategies defined in scrubbableFields().
     * Return a ScrubbableFields instance to use different strategies specifically for activity logs.
     */
    public function activityLogScrubbableFields(): ?ScrubbableFields;
}
