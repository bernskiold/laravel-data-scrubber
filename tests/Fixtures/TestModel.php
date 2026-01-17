<?php

namespace Bernskiold\LaravelDataScrubber\Tests\Fixtures;

use Bernskiold\LaravelDataScrubber\Concerns\ScrubsData;
use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeFirstNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeLastNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModel extends Model implements Scrubbable
{
    use ScrubsData;
    use SoftDeletes;

    protected $table = 'test_models';

    protected $guarded = [];

    protected $casts = [
        'scrubbed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scrubbableQuery(): Builder
    {
        return static::query()
            ->onlyTrashed()
            ->whereNull('scrubbed_at');
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            'email' => AnonymizeEmailWithIdStrategy::class,
            'first_name' => AnonymizeFirstNameStrategy::class,
            'last_name' => AnonymizeLastNameStrategy::class,
            'phone' => NullStrategy::class,
            'ssn' => RedactedStrategy::class,
            'notes' => HashStrategy::class,
        ]);
    }
}
