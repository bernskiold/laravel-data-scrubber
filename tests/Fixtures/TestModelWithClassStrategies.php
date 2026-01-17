<?php

namespace Bernskiold\LaravelDataScrubber\Tests\Fixtures;

use Bernskiold\LaravelDataScrubber\Concerns\ScrubsData;
use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeFirstNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\CallbackStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\DeleteFileStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Test model demonstrating all strategy configuration styles.
 */
class TestModelWithClassStrategies extends Model implements Scrubbable
{
    use ScrubsData;

    protected $table = 'test_models_class_strategies';

    protected $guarded = [];

    protected $casts = [
        'scrubbed_at' => 'datetime',
    ];

    public function scrubbableQuery(): Builder
    {
        return static::query()->whereNull('scrubbed_at');
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            // Class-string syntax
            'email' => AnonymizeEmailWithIdStrategy::class,

            // Class-string syntax (alternative)
            'first_name' => AnonymizeFirstNameStrategy::class,

            // Configured instance syntax
            'last_name' => new AnonymizeFirstNameStrategy('Anonymous'),

            // Configured instance with parameters
            'avatar' => new DeleteFileStrategy('public'),

            // Configured hash with custom algorithm
            'secret' => new HashStrategy('md5'),

            // CallbackStrategy syntax
            'custom' => new CallbackStrategy(fn ($value, $model, $field) => "processed-{$model->getKey()}"),
        ]);
    }
}
