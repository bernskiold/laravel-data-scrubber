<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class AnonymizeEmailWithIdStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $domain = null,
        protected ?string $prefix = null,
    ) {
        $this->domain ??= config('data-scrubber.strategies.anonymize_email_with_id.domain', 'anonymized.local');
        $this->prefix ??= config('data-scrubber.strategies.anonymize_email_with_id.prefix', 'deleted-');
    }

    /**
     * Apply the anonymize email with ID strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return "{$this->prefix}{$model->getKey()}@{$this->domain}";
    }

    public function label(): string
    {
        return 'Replace with deleted-{id}@anonymized.local';
    }

    public function description(): string
    {
        return 'Replaces the value with a unique anonymized email using the model\'s ID.';
    }
}
