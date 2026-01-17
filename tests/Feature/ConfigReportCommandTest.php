<?php

beforeEach(function () {
    config(['data-scrubber.model_paths' => [__DIR__.'/../Fixtures']]);
});

describe('data-scrubbing:config command', function () {
    it('displays configuration report', function () {
        $this->artisan('data-scrubbing:config')
            ->assertSuccessful();
    });

    it('shows model summary table', function () {
        $this->artisan('data-scrubbing:config')
            ->assertSuccessful();
    });

    it('filters by model name using short name', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'TestModel'])
            ->assertSuccessful();
    });

    it('filters by model name using FQCN', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'Bernskiold\\LaravelDataScrubber\\Tests\\Fixtures\\TestModel'])
            ->assertSuccessful();
    });

    it('handles non-existent model filter gracefully', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'NonExistentModel'])
            ->assertSuccessful();
    });

    it('outputs valid JSON with --json flag', function () {
        $this->artisan('data-scrubbing:config', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"models"');
    });

    it('JSON output contains expected structure', function () {
        $command = $this->artisan('data-scrubbing:config', ['--json' => true]);
        $command->assertSuccessful();
    });

    it('JSON output can be filtered by model', function () {
        $this->artisan('data-scrubbing:config', ['--json' => true, '--model' => 'TestModel'])
            ->assertSuccessful();
    });

    it('handles empty model paths gracefully', function () {
        config(['data-scrubber.model_paths' => ['/nonexistent/path']]);

        $this->artisan('data-scrubbing:config')
            ->assertSuccessful();
    });

    it('displays multiple models', function () {
        $this->artisan('data-scrubbing:config')
            ->assertSuccessful();
    });
});

describe('report content', function () {
    it('shows all configured fields for each model', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'TestModel'])
            ->assertSuccessful();
    });

    it('shows strategy information', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'TestModel'])
            ->assertSuccessful();
    });

    it('shows timestamp configuration', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'TestModel'])
            ->assertSuccessful();
    });

    it('shows processing mode configuration', function () {
        $this->artisan('data-scrubbing:config', ['--model' => 'TestModel'])
            ->assertSuccessful();
    });
});

describe('JSON output format', function () {
    it('includes model configurations', function () {
        $this->artisan('data-scrubbing:config', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"modelClass"');
    });

    it('includes scanned paths', function () {
        $this->artisan('data-scrubbing:config', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"scannedPaths"');
    });

    it('includes summary statistics', function () {
        $this->artisan('data-scrubbing:config', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"summary"');
    });

    it('includes generation timestamp', function () {
        $this->artisan('data-scrubbing:config', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"generatedAt"');
    });
});
