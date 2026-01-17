<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Commands;

use Bernskiold\LaravelDataScrubber\Data\ConfigurationReport;
use Bernskiold\LaravelDataScrubber\Data\FieldConfiguration;
use Bernskiold\LaravelDataScrubber\Data\ModelConfiguration;
use Bernskiold\LaravelDataScrubber\Services\ConfigurationReportService;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class ConfigReportCommand extends Command
{
    protected $signature = 'data-scrubbing:config
                            {--model= : Filter to a specific model (short name or FQCN)}
                            {--json : Output as JSON instead of formatted tables}';

    protected $description = 'Display the configuration of all Scrubbable models';

    public function __construct(
        protected ConfigurationReportService $reportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->reportService->generate();

        // Apply model filter if provided
        if ($modelFilter = $this->option('model')) {
            $filteredModels = $report->filterModels($modelFilter);

            if ($filteredModels->isEmpty()) {
                warning("No models found matching '{$modelFilter}'.");

                return self::SUCCESS;
            }

            $report = new ConfigurationReport(
                models: $filteredModels->values(),
                scannedPaths: $report->scannedPaths,
                generatedAt: $report->generatedAt,
            );
        }

        // Output as JSON if requested
        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Display formatted output
        $this->displayReport($report);

        return self::SUCCESS;
    }

    /**
     * Display the formatted report.
     */
    protected function displayReport(ConfigurationReport $report): void
    {
        $this->displayHeader($report);

        if (! $report->hasModels()) {
            warning('No Scrubbable models found.');
            $this->newLine();
            info('Scanned paths:');
            foreach ($report->scannedPaths as $path) {
                $this->line("  - {$path}");
            }

            return;
        }

        $this->displaySummaryTable($report);
        $this->displayModelDetails($report);
        $this->displayFooter($report);
    }

    /**
     * Display the report header.
     */
    protected function displayHeader(ConfigurationReport $report): void
    {
        $this->newLine();
        info('Data Scrubber Configuration Report');
        $this->line('Generated: '.$report->generatedAt->format('Y-m-d H:i:s'));
        $this->newLine();
    }

    /**
     * Display the summary table.
     */
    protected function displaySummaryTable(ConfigurationReport $report): void
    {
        info('Summary');

        $rows = $report->models->map(fn (ModelConfiguration $model) => [
            $model->shortName,
            (string) $model->fieldCount(),
            $model->processingMode(),
            (string) $model->options->chunkSize,
            $model->options->logTimestamp ? $model->options->timestampColumn : '-',
        ])->all();

        table(
            ['Model', 'Fields', 'Mode', 'Chunk Size', 'Timestamp Col'],
            $rows
        );

        $this->newLine();
    }

    /**
     * Display detailed configuration for each model.
     */
    protected function displayModelDetails(ConfigurationReport $report): void
    {
        foreach ($report->models as $model) {
            $this->displayModelConfiguration($model);
        }
    }

    /**
     * Display configuration for a single model.
     */
    protected function displayModelConfiguration(ModelConfiguration $model): void
    {
        // Model header
        $this->line("<fg=white;options=bold>{$model->shortName}</>");
        $this->line("<fg=gray>{$model->modelClass}</>");

        // Options
        $this->displayOptionLine('Timestamp Logging', $model->options->logTimestamp ? 'Enabled' : 'Disabled');
        if ($model->options->logTimestamp) {
            $this->displayOptionLine('Timestamp Column', $model->options->timestampColumn);
        }
        $this->displayOptionLine('Chunk Size', (string) $model->options->chunkSize);
        $this->displayOptionLine('Processing Mode', $model->options->scrubAsync ? 'Asynchronous' : 'Synchronous');

        $this->newLine();

        // Fields table
        info("Fields ({$model->fieldCount()})");

        $rows = $model->fields->map(fn (FieldConfiguration $field) => [
            $field->fieldName,
            $field->strategy->shortName(),
            $this->truncate($field->strategy->label, 25),
            $this->truncate($field->strategy->formatParameters(), 25),
        ])->all();

        table(
            ['Field', 'Strategy', 'Label', 'Parameters'],
            $rows
        );

        $this->newLine();
    }

    /**
     * Display an option line with dots.
     */
    protected function displayOptionLine(string $label, string $value): void
    {
        $labelLength = strlen($label);
        $valueLength = strlen($value);
        $totalWidth = 40;
        $dots = str_repeat('.', max(1, $totalWidth - $labelLength - $valueLength - 2));

        $this->line("  <fg=gray>{$label}</> <fg=gray>{$dots}</> {$value}");
    }

    /**
     * Display the report footer.
     */
    protected function displayFooter(ConfigurationReport $report): void
    {
        $this->displayOptionLine('Total Models', (string) $report->modelCount());
        $this->displayOptionLine('Total Fields', (string) $report->totalFieldCount());
        $this->displayOptionLine('Unique Strategies', (string) $report->uniqueStrategyCount());
        $this->newLine();
    }

    /**
     * Truncate a string to a maximum length.
     */
    protected function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength - 3).'...';
    }
}
