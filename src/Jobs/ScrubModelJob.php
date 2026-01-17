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
     * @param  class-string<Model&Scrubbable>  $modelClass
     */
    public function __construct(
        public string $modelClass,
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

        $instance->scrubbableQuery()
            ->chunkById($chunkSize, function ($records) {
                foreach ($records as $record) {
                    /** @var Model&Scrubbable $record */
                    $record->scrub();
                }
            });
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
