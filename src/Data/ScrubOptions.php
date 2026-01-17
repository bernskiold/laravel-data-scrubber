<?php

namespace Bernskiold\LaravelDataScrubber\Data;

use Illuminate\Support\Traits\Conditionable;

class ScrubOptions
{
    use Conditionable;

    public bool $logTimestamp = true;

    public string $timestampColumn = 'scrubbed_at';

    public int $chunkSize = 500;

    public bool $scrubAsync = true;

    /**
     * Create a new ScrubOptions instance with defaults from config.
     */
    public static function defaults(): static
    {
        return (new static)
            ->useTimestampColumn(config('data-scrubber.timestamp_column', 'scrubbed_at'))
            ->useChunkSize(config('data-scrubber.queue.chunk_size', 500))
            ->when(config('data-scrubber.queue.async', true), fn ($options) => $options->scrubAsynchronously())
            ->when(! config('data-scrubber.queue.async', true), fn ($options) => $options->scrubSynchronously());
    }

    /**
     * Enable timestamp logging when records are scrubbed.
     */
    public function logScrubTimestamp(): static
    {
        $this->logTimestamp = true;

        return $this;
    }

    /**
     * Disable timestamp logging when records are scrubbed.
     */
    public function dontLogScrubTimestamp(): static
    {
        $this->logTimestamp = false;

        return $this;
    }

    /**
     * Set the column name used to store the scrub timestamp.
     */
    public function useTimestampColumn(string $column): static
    {
        $this->timestampColumn = $column;

        return $this;
    }

    /**
     * Set the chunk size for processing records.
     */
    public function useChunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Enable asynchronous scrubbing via queued jobs.
     */
    public function scrubAsynchronously(): static
    {
        $this->scrubAsync = true;

        return $this;
    }

    /**
     * Disable asynchronous scrubbing (process synchronously).
     */
    public function scrubSynchronously(): static
    {
        $this->scrubAsync = false;

        return $this;
    }
}
