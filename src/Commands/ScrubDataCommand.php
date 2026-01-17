<?php

namespace Bernskiold\LaravelDataScrubber\Commands;

use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Jobs\ScrubModelJob;
use Bernskiold\LaravelDataScrubber\Services\ModelDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class ScrubDataCommand extends Command
{
    protected $signature = 'data-scrubbing:scrub
                            {--model= : Specific model class to scrub (short name or FQCN)}
                            {--dry-run : Preview what would be scrubbed without making changes}
                            {--force : Skip confirmation prompts}
                            {--sync : Run synchronously instead of queuing jobs}';

    protected $description = 'Scrub PII and sensitive data from models implementing Scrubbable';

    public function handle(): int
    {
        $models = $this->discoverScrubbableModels();

        if ($this->option('model')) {
            $models = $this->filterModels($models, $this->option('model'));
        }

        if (empty($models)) {
            warning('No scrubbable models found.');

            return self::SUCCESS;
        }

        $this->displayModels($models);

        if ($this->option('dry-run')) {
            return $this->performDryRun($models);
        }

        if (! $this->option('force') && ! confirm('Do you want to proceed with scrubbing?', false)) {
            info('Operation cancelled.');

            return self::SUCCESS;
        }

        return $this->performScrub($models);
    }

    /**
     * Discover all models implementing Scrubbable.
     *
     * @return array<class-string<Model&Scrubbable>>
     */
    protected function discoverScrubbableModels(): array
    {
        return app(ModelDiscoveryService::class)->discover();
    }

    /**
     * Filter models by name or FQCN.
     *
     * @param  array<class-string<Model&Scrubbable>>  $models
     * @return array<class-string<Model&Scrubbable>>
     */
    protected function filterModels(array $models, string $filter): array
    {
        return array_filter($models, function (string $model) use ($filter) {
            return $model === $filter
                || class_basename($model) === $filter;
        });
    }

    /**
     * Display the models that will be scrubbed.
     *
     * @param  array<class-string<Model&Scrubbable>>  $models
     */
    protected function displayModels(array $models): void
    {
        info('Found '.count($models).' scrubbable model(s):');

        $tableData = [];
        foreach ($models as $modelClass) {
            /** @var Model&Scrubbable $instance */
            $instance = new $modelClass;
            $count = $instance->scrubbableQuery()->count();
            $fields = implode(', ', $instance->scrubbableFields()->fields());

            $tableData[] = [
                class_basename($modelClass),
                $count,
                $fields,
            ];
        }

        table(['Model', 'Records', 'Fields'], $tableData);
    }

    /**
     * Perform a dry run.
     *
     * @param  array<class-string<Model&Scrubbable>>  $models
     */
    protected function performDryRun(array $models): int
    {
        info('Dry run mode - no changes will be made.');

        $isSyncMode = $this->shouldRunSync();

        if (! $isSyncMode) {
            $this->newLine();
            info('Queue mode would dispatch jobs for each model:');

            $tableData = [];
            foreach ($models as $modelClass) {
                /** @var Model&Scrubbable $instance */
                $instance = new $modelClass;
                $count = $instance->scrubbableQuery()->count();
                $options = $instance->getScrubOptions();
                $chunkSize = $options->chunkSize;
                $async = $options->scrubAsync;

                $tableData[] = [
                    class_basename($modelClass),
                    $count,
                    $chunkSize,
                    $async ? 'async (job)' : 'sync (forced)',
                ];
            }

            table(['Model', 'Records', 'Chunk Size', 'Mode'], $tableData);
            $this->newLine();
        }

        foreach ($models as $modelClass) {
            $this->newLine();
            info("Preview for {$modelClass}:");

            /** @var Model&Scrubbable $instance */
            $instance = new $modelClass;
            $records = $instance->scrubbableQuery()->limit(3)->get();

            if ($records->isEmpty()) {
                warning('  No records to scrub.');

                continue;
            }

            foreach ($records as $record) {
                $preview = $record->previewScrub();
                $this->line("  Record ID: {$record->getKey()}");

                foreach ($preview as $field => $data) {
                    $current = is_null($data['current']) ? 'NULL' : "'{$data['current']}'";
                    $scrubbed = is_null($data['scrubbed']) ? 'NULL' : "'{$data['scrubbed']}'";
                    $this->line("    {$field}: {$current} -> {$scrubbed} ({$data['strategy']})");
                }
            }

            $total = $instance->scrubbableQuery()->count();
            if ($total > 3) {
                info('  ... and '.($total - 3).' more record(s).');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Perform the actual scrubbing.
     *
     * @param  array<class-string<Model&Scrubbable>>  $models
     */
    protected function performScrub(array $models): int
    {
        $isSyncMode = $this->shouldRunSync();
        $jobsDispatched = 0;
        $syncScrubbed = 0;
        $errors = [];

        foreach ($models as $modelClass) {
            /** @var Model&Scrubbable $instance */
            $instance = new $modelClass;
            $count = $instance->scrubbableQuery()->count();

            if ($count === 0) {
                info('No records to scrub for '.class_basename($modelClass).'.');

                continue;
            }

            // Determine if this model should run sync
            $runSync = $isSyncMode || ! $instance->getScrubOptions()->scrubAsync;

            if ($runSync) {
                // Run synchronously with progress bar
                $result = $this->scrubModelSync($modelClass, $count);
                $syncScrubbed += $result['scrubbed'];
                $errors = array_merge($errors, $result['errors']);
            } else {
                // Dispatch job for async processing
                ScrubModelJob::dispatch($modelClass);
                $jobsDispatched++;
                info('Dispatched job for '.class_basename($modelClass)." ({$count} records).");
            }
        }

        $this->newLine();

        if ($jobsDispatched > 0) {
            info("Dispatched {$jobsDispatched} job(s) to the queue.");
            info("Run 'php artisan queue:work --queue=".config('data-scrubber.queue.queue', 'data-scrubber')."' to process.");
        }

        if ($syncScrubbed > 0) {
            info("Scrubbed {$syncScrubbed} record(s) synchronously.");
        }

        if (! empty($errors)) {
            warning('Encountered '.count($errors).' error(s):');
            foreach ($errors as $error) {
                $this->error("  {$error['model']} #{$error['id']}: {$error['error']}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Scrub a model synchronously with progress bar.
     *
     * @param  class-string<Model&Scrubbable>  $modelClass
     * @return array{scrubbed: int, errors: array<array{model: string, id: mixed, error: string}>}
     */
    protected function scrubModelSync(string $modelClass, int $count): array
    {
        $scrubbed = 0;
        $errors = [];

        /** @var Model&Scrubbable $instance */
        $instance = new $modelClass;
        $chunkSize = $instance->getScrubOptions()->chunkSize;

        $progress = progress(
            label: 'Scrubbing '.class_basename($modelClass),
            steps: $count
        );

        $progress->start();

        $instance->scrubbableQuery()
            ->chunkById($chunkSize, function ($records) use ($progress, &$scrubbed, &$errors) {
                foreach ($records as $record) {
                    /** @var Model&Scrubbable $record */
                    try {
                        $record->scrub();
                        $scrubbed++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'model' => get_class($record),
                            'id' => $record->getKey(),
                            'error' => $e->getMessage(),
                        ];
                    }
                    $progress->advance();
                }
            });

        $progress->finish();

        return ['scrubbed' => $scrubbed, 'errors' => $errors];
    }

    /**
     * Determine if we should run in sync mode.
     */
    protected function shouldRunSync(): bool
    {
        // --sync flag forces sync mode
        if ($this->option('sync')) {
            return true;
        }

        // Check global config (defaults to async)
        return ! config('data-scrubber.queue.async', true);
    }
}
