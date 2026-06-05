<?php

namespace Bernskiold\LaravelDataScrubber;

use Bernskiold\LaravelDataScrubber\Commands\ConfigReportCommand;
use Bernskiold\LaravelDataScrubber\Commands\ScrubDataCommand;
use Bernskiold\LaravelDataScrubber\Services\ConfigurationReportService;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeFirstNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeLastNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\CallbackStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\ConditionalStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\DeleteFileStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\IpAnonymizeStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\JsonFieldStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\MaskStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\TruncateStrategy;
use Composer\InstalledVersions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class DataScrubberServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerDefaultStrategies();
        $this->registerBlueprintMacros();

        AboutCommand::add('Laravel Data Scrubber', fn () => ['Version' => $this->packageVersion()]);

        $this->publishes([
            __DIR__.'/../config/data-scrubber.php' => config_path('data-scrubber.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScrubDataCommand::class,
                ConfigReportCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/data-scrubber.php', 'data-scrubber'
        );

        $this->app->singleton(ConfigurationReportService::class);
    }

    /**
     * Register the default scrubbing strategies.
     */
    protected function registerDefaultStrategies(): void
    {
        ScrubbingStrategies::register([
            NullStrategy::class,
            RedactedStrategy::class,
            AnonymizeFirstNameStrategy::class,
            AnonymizeLastNameStrategy::class,
            AnonymizeEmailStrategy::class,
            AnonymizeEmailWithIdStrategy::class,
            HashStrategy::class,
            DeleteFileStrategy::class,
            MaskStrategy::class,
            TruncateStrategy::class,
            IpAnonymizeStrategy::class,
            JsonFieldStrategy::class,
            ConditionalStrategy::class,
            CallbackStrategy::class,
        ]);
    }

    /**
     * Resolve the installed package version for the about command.
     */
    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) &&
            InstalledVersions::isInstalled('bernskiold/laravel-data-scrubber')) {
            return InstalledVersions::getPrettyVersion('bernskiold/laravel-data-scrubber') ?? 'dev';
        }

        return 'dev';
    }

    /**
     * Register Blueprint macros for schema migrations.
     */
    protected function registerBlueprintMacros(): void
    {
        Blueprint::macro('scrubbedAt', function () {
            /** @var Blueprint $this */
            return $this->timestamp(config('data-scrubber.timestamp_column', 'scrubbed_at'))->nullable();
        });
    }
}
