<?php

use Bernskiold\LaravelDataScrubber\Data\ConfigurationReport;
use Bernskiold\LaravelDataScrubber\Data\FieldConfiguration;
use Bernskiold\LaravelDataScrubber\Data\ModelConfiguration;
use Bernskiold\LaravelDataScrubber\Data\StrategyInfo;
use Bernskiold\LaravelDataScrubber\Services\ConfigurationReportService;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeFirstNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\CallbackStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\MaskStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;

beforeEach(function () {
    config(['data-scrubber.model_paths' => [__DIR__.'/../Fixtures']]);
});

describe('generate()', function () {
    it('generates a complete configuration report', function () {
        $service = app(ConfigurationReportService::class);
        $report = $service->generate();

        expect($report)->toBeInstanceOf(ConfigurationReport::class);
        expect($report->hasModels())->toBeTrue();
        expect($report->generatedAt)->toBeInstanceOf(DateTimeImmutable::class);
        expect($report->scannedPaths)->toContain(__DIR__.'/../Fixtures');
    });

    it('discovers all scrubbable models', function () {
        $service = app(ConfigurationReportService::class);
        $report = $service->generate();

        $modelNames = $report->models->pluck('shortName')->all();

        expect($modelNames)->toContain('TestModel');
        expect($modelNames)->toContain('TestModelWithoutTimestamp');
        expect($modelNames)->toContain('TestModelWithClassStrategies');
        expect($modelNames)->toContain('TestModelWithCustomStrategy');
    });

    it('returns empty report when no models found', function () {
        config(['data-scrubber.model_paths' => ['/nonexistent/path']]);

        $service = app(ConfigurationReportService::class);
        $report = $service->generate();

        expect($report->hasModels())->toBeFalse();
        expect($report->modelCount())->toBe(0);
    });
});

describe('buildModelConfiguration()', function () {
    it('builds complete model configuration', function () {
        $service = app(ConfigurationReportService::class);
        $config = $service->buildModelConfiguration(TestModel::class);

        expect($config)->toBeInstanceOf(ModelConfiguration::class);
        expect($config->modelClass)->toBe(TestModel::class);
        expect($config->shortName)->toBe('TestModel');
        expect($config->fields)->toHaveCount(6);
        expect($config->fieldNames())->toEqual(['email', 'first_name', 'last_name', 'phone', 'ssn', 'notes']);
    });

    it('captures scrub options', function () {
        $service = app(ConfigurationReportService::class);
        $config = $service->buildModelConfiguration(TestModel::class);

        expect($config->options->logTimestamp)->toBeTrue();
        expect($config->options->timestampColumn)->toBe('scrubbed_at');
        expect($config->options->chunkSize)->toBeInt();
    });

    it('builds field configurations with strategy info', function () {
        $service = app(ConfigurationReportService::class);
        $config = $service->buildModelConfiguration(TestModel::class);

        $emailField = $config->field('email');

        expect($emailField)->toBeInstanceOf(FieldConfiguration::class);
        expect($emailField->fieldName)->toBe('email');
        expect($emailField->strategy)->toBeInstanceOf(StrategyInfo::class);
        expect($emailField->strategy->class)->toBe(AnonymizeEmailWithIdStrategy::class);
    });
});

describe('buildStrategyInfo()', function () {
    it('builds strategy info from class string', function () {
        $service = app(ConfigurationReportService::class);
        $info = $service->buildStrategyInfo(NullStrategy::class);

        expect($info->class)->toBe(NullStrategy::class);
        expect($info->shortName())->toBe('NullStrategy');
        expect($info->label)->toBe('Set to NULL');
        expect($info->description)->toBe('Sets the field value to NULL.');
        expect($info->isConfigured)->toBeFalse();
    });

    it('builds strategy info from instance (configured)', function () {
        $strategy = new MaskStrategy(visibleStart: 3, visibleEnd: 4);
        $service = app(ConfigurationReportService::class);
        $info = $service->buildStrategyInfo($strategy);

        expect($info->class)->toBe(MaskStrategy::class);
        expect($info->isConfigured)->toBeTrue();
        expect($info->parameters)->toHaveKey('visibleStart');
        expect($info->parameters['visibleStart'])->toBe(3);
        expect($info->parameters['visibleEnd'])->toBe(4);
    });

    it('extracts parameters from hash strategy', function () {
        $strategy = new HashStrategy('md5');
        $service = app(ConfigurationReportService::class);
        $info = $service->buildStrategyInfo($strategy);

        expect($info->parameters)->toHaveKey('algorithm');
        expect($info->parameters['algorithm'])->toBe('md5');
    });

    it('handles callback strategy with closure', function () {
        $strategy = new CallbackStrategy(fn ($value) => strtoupper($value));
        $service = app(ConfigurationReportService::class);
        $info = $service->buildStrategyInfo($strategy);

        expect($info->class)->toBe(CallbackStrategy::class);
        expect($info->parameters)->toHaveKey('callback');
        expect($info->parameters['callback'])->toBe('Closure');
    });
});

describe('extractStrategyParameters()', function () {
    it('extracts protected properties', function () {
        $strategy = new MaskStrategy(visibleStart: 2, visibleEnd: 3, maskChar: '#');
        $service = app(ConfigurationReportService::class);
        $params = $service->extractStrategyParameters($strategy);

        expect($params)->toBe([
            'visibleStart' => 2,
            'visibleEnd' => 3,
            'maskChar' => '#',
        ]);
    });

    it('handles strategies with no configurable parameters', function () {
        $strategy = new NullStrategy;
        $service = app(ConfigurationReportService::class);
        $params = $service->extractStrategyParameters($strategy);

        expect($params)->toBeEmpty();
    });

    it('formats null values correctly', function () {
        $strategy = new AnonymizeFirstNameStrategy(null);
        $service = app(ConfigurationReportService::class);
        $info = $service->buildStrategyInfo($strategy);

        // The replacement defaults from config if null passed
        expect($info->parameters)->toHaveKey('replacement');
    });
});

describe('ConfigurationReport DTO', function () {
    it('calculates total field count', function () {
        $service = app(ConfigurationReportService::class);
        $report = $service->generate();

        $expectedCount = $report->models->sum(fn ($m) => $m->fieldCount());

        expect($report->totalFieldCount())->toBe($expectedCount);
        expect($report->totalFieldCount())->toBeGreaterThan(0);
    });

    it('calculates unique strategies', function () {
        $service = app(ConfigurationReportService::class);
        $report = $service->generate();

        $uniqueStrategies = $report->uniqueStrategies();

        expect($uniqueStrategies)->not->toBeEmpty();
        expect($uniqueStrategies->first())->toBeInstanceOf(StrategyInfo::class);
    });

    it('filters models by name', function () {
        $service = app(ConfigurationReportService::class);
        $report = $service->generate();

        $filtered = $report->filterModels('TestModel');

        expect($filtered)->not->toBeEmpty();
        expect($filtered->every(fn ($m) => str_contains($m->shortName, 'TestModel')))->toBeTrue();
    });

    it('converts to array', function () {
        $service = app(ConfigurationReportService::class);
        $report = $service->generate();
        $array = $report->toArray();

        expect($array)->toHaveKeys(['models', 'scannedPaths', 'generatedAt', 'summary']);
        expect($array['summary'])->toHaveKeys(['modelCount', 'totalFieldCount', 'uniqueStrategyCount']);
    });
});

describe('StrategyInfo DTO', function () {
    it('formats parameters for display', function () {
        $info = new StrategyInfo(
            class: MaskStrategy::class,
            label: 'Mask',
            description: 'Masks values',
            parameters: ['visibleStart' => 2, 'visibleEnd' => 3],
            isConfigured: true,
        );

        expect($info->formatParameters())->toBe('visibleStart=2, visibleEnd=3');
    });

    it('returns defaults when no parameters', function () {
        $info = new StrategyInfo(
            class: NullStrategy::class,
            label: 'Set to NULL',
            description: 'Sets to null',
            parameters: [],
            isConfigured: false,
        );

        expect($info->formatParameters())->toBe('defaults');
        expect($info->hasParameters())->toBeFalse();
    });

    it('handles complex parameter values', function () {
        $info = new StrategyInfo(
            class: 'TestStrategy',
            label: 'Test',
            description: 'Test strategy',
            parameters: [
                'closure' => fn () => null,
                'array' => ['a', 'b'],
                'null' => null,
                'bool' => true,
            ],
            isConfigured: true,
        );

        $formatted = $info->formatParameters();

        expect($formatted)->toContain('closure=Closure');
        expect($formatted)->toContain('null=null');
        expect($formatted)->toContain('bool=true');
    });
});

describe('ModelConfiguration DTO', function () {
    it('provides processing mode label', function () {
        $service = app(ConfigurationReportService::class);
        $config = $service->buildModelConfiguration(TestModel::class);

        expect($config->processingMode())->toBeIn(['Async', 'Sync']);
    });

    it('provides unique strategy classes', function () {
        $service = app(ConfigurationReportService::class);
        $config = $service->buildModelConfiguration(TestModel::class);

        $strategies = $config->uniqueStrategyClasses();

        expect($strategies)->toContain(AnonymizeEmailWithIdStrategy::class);
        expect($strategies)->toContain(NullStrategy::class);
        expect($strategies->unique()->count())->toBe($strategies->count());
    });
});
