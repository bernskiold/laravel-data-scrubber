<?php

namespace Bernskiold\LaravelDataScrubber\Events;

use Bernskiold\LaravelDataScrubber\Data\ScrubbedField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Scrubbed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Model  $model  The model that was scrubbed.
     * @param  array<string, ScrubbedField>  $scrubbedFields  Details about each scrubbed field, keyed by field name.
     */
    public function __construct(
        public Model $model,
        public array $scrubbedFields,
    ) {}

    /**
     * Get the field names that were scrubbed.
     *
     * @return array<int, string>
     */
    public function fieldNames(): array
    {
        return array_keys($this->scrubbedFields);
    }

    /**
     * Get the details for a specific field.
     */
    public function field(string $name): ?ScrubbedField
    {
        return $this->scrubbedFields[$name] ?? null;
    }
}
