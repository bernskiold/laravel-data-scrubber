<?php

namespace Bernskiold\LaravelDataScrubber\Jobs;

use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScrubModelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The queue connection that should handle the job.
     *
     * @var string|null
     */
    public $connection;

    /**
     * The queue that the job should be sent to.
     *
     * @var string|null
     */
    public $queue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff;

    /**
     * Create a new job instance.
     *
     * Each job scrubs a single bounded chunk of records and, if more remain,
     * chains the next chunk as a fresh job. This keeps individual jobs small
     * (avoiding worker timeouts on large tables) and makes retries safe: a
     * failed job re-processes only its own chunk, starting after $afterId.
     *
     * @param  class-string<Model&Scrubbable>  $modelClass
     * @param  mixed  $afterId  Only process records with a key greater than this
     */
    public function __construct(
        public string $modelClass,
        public mixed $afterId = null,
    ) {
        $this->connection = config('data-scrubber.queue.connection');
        $this->queue = config('data-scrubber.queue.queue', 'data-scrubber');
        $this->tries = config('data-scrubber.queue.tries', 3);
        $this->backoff = config('data-scrubber.queue.backoff', 60);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var Model&Scrubbable $instance */
        $instance = new $this->modelClass;
        $chunkSize = $instance->getScrubOptions()->chunkSize;
        $keyName = $instance->getQualifiedKeyName();

        $query = $instance->pendingScrubbableQuery();

        if ($this->afterId !== null) {
            $query->where($keyName, '>', $this->afterId);
        }

        $records = $query->orderBy($keyName)
            ->limit($chunkSize)
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $lastId = null;
        foreach ($records as $record) {
            /** @var Model&Scrubbable $record */
            $record->scrub();
            $lastId = $record->getKey();
        }

        // A full chunk means there may be more records — chain the next batch.
        if ($records->count() === $chunkSize) {
            self::dispatch($this->modelClass, $lastId);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'data-scrubber',
            'model:'.$this->modelClass,
        ];
    }
}
