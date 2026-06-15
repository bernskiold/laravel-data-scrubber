<?php

namespace Bernskiold\LaravelDataScrubber\Tests;

use Bernskiold\LaravelDataScrubber\DataScrubberServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            DataScrubberServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('ssn')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scrubbed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_models_without_timestamp', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('test_models_custom', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('custom_field')->nullable();
            $table->timestamp('scrubbed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('test_models_unfiltered', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scrubbed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_models_class_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('secret')->nullable();
            $table->string('custom')->nullable();
            $table->timestamp('scrubbed_at')->nullable();
            $table->timestamps();
        });
    }
}
