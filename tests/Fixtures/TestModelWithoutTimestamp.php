<?php

namespace Bernskiold\LaravelDataScrubber\Tests\Fixtures;

use Bernskiold\LaravelDataScrubber\Concerns\ScrubsData;
use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Data\ScrubOptions;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TestModelWithoutTimestamp extends Model implements Scrubbable
{
    use ScrubsData;

    protected $table = 'test_models_without_timestamp';

    protected $guarded = [];

    public function scrubbableQuery(): Builder
    {
        return static::query();
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            'email' => AnonymizeEmailStrategy::class,
            'name' => RedactedStrategy::class,
        ]);
    }

    public function getScrubOptions(): ScrubOptions
    {
        return ScrubOptions::defaults()
            ->dontLogScrubTimestamp();
    }
}
