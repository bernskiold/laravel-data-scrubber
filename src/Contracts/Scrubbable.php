<?php

namespace Bernskiold\LaravelDataScrubber\Contracts;

use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Data\ScrubOptions;
use Illuminate\Database\Eloquent\Builder;

interface Scrubbable
{
    /**
     * Get the query for records that should be scrubbed.
     *
     * This method should return a query builder scoped to only the records
     * that are eligible for scrubbing (e.g., soft-deleted records older than X days).
     */
    public function scrubbableQuery(): Builder;

    /**
     * Get the fields and their scrubbing strategies.
     *
     * Returns a ScrubbableFields object containing field names and their strategies.
     * Strategies can be:
     * - A ScrubStrategy instance (configured strategy)
     * - A class-string of a ScrubStrategy implementation
     *
     * Example using array factory:
     * return ScrubbableFields::make([
     *     'email' => AnonymizeEmailWithIdStrategy::class,
     *     'first_name' => AnonymizeFirstNameStrategy::class,
     *     'phone' => NullStrategy::class,
     * ]);
     *
     * Example using fluent builder:
     * return ScrubbableFields::make()
     *     ->add('email', AnonymizeEmailWithIdStrategy::class)
     *     ->add('first_name', new AnonymizeFirstNameStrategy('Anonymous'))
     *     ->when($this->hasAvatar(), fn($c) => $c->add('avatar', DeleteFileStrategy::class));
     */
    public function scrubbableFields(): ScrubbableFields;

    /**
     * Get the scrubbing options for this model.
     *
     * Override this method to customize scrubbing behavior:
     * - Timestamp logging (enabled/disabled)
     * - Timestamp column name
     * - Chunk size for batch processing
     * - Async/sync processing mode
     *
     * Example:
     * return ScrubOptions::defaults()
     *     ->useTimestampColumn('data_cleaned_at')
     *     ->useChunkSize(100)
     *     ->scrubSynchronously();
     */
    public function getScrubOptions(): ScrubOptions;
}
