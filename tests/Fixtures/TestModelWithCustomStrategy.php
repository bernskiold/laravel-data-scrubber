<?php

namespace Bernskiold\LaravelDataScrubber\Tests\Fixtures;

use Bernskiold\LaravelDataScrubber\Concerns\ScrubsData;
use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\CallbackStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TestModelWithCustomStrategy extends Model implements Scrubbable
{
    use ScrubsData;

    protected $table = 'test_models_custom';

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
            'email' => AnonymizeEmailStrategy::class,
            'custom_field' => new CallbackStrategy(fn ($value, $model, $field) => 'custom-'.$model->getKey().'-scrubbed'),
        ]);
    }
}
