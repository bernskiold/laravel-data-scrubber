<?php

namespace Bernskiold\LaravelDataScrubber\Tests\Fixtures;

use Bernskiold\LaravelDataScrubber\Concerns\ScrubsData;
use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Bernskiold\LaravelDataScrubber\Data\ScrubbableFields;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A model whose scrubbableQuery() deliberately does NOT exclude already-scrubbed
 * records. Used to verify the idempotency safety net (pendingScrubbableQuery)
 * prevents non-idempotent strategies from running twice.
 */
class TestModelUnfilteredQuery extends Model implements Scrubbable
{
    use ScrubsData;
    use SoftDeletes;

    protected $table = 'test_models_unfiltered';

    protected $guarded = [];

    protected $casts = [
        'scrubbed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scrubbableQuery(): Builder
    {
        // Intentionally omits a whereNull('scrubbed_at') filter.
        return static::query()->onlyTrashed();
    }

    public function scrubbableFields(): ScrubbableFields
    {
        return ScrubbableFields::make([
            'notes' => HashStrategy::class,
        ]);
    }
}
