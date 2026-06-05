<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class HashStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?string $algorithm = null,
        protected ?string $salt = null,
    ) {
        $this->algorithm ??= config('data-scrubber.strategies.hash.algorithm', 'sha256');
        $this->salt ??= config('data-scrubber.strategies.hash.salt');
    }

    /**
     * Apply the hash strategy.
     *
     * When a salt is configured the value is hashed with HMAC, which prevents
     * low-cardinality PII (emails, phone numbers, etc.) from being recovered via
     * brute-force or rainbow tables. Without a salt a plain hash is used.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->salt !== null && $this->salt !== '') {
            return hash_hmac($this->algorithm, (string) $value, $this->salt);
        }

        return hash($this->algorithm, (string) $value);
    }

    public function label(): string
    {
        return 'Hash the value';
    }

    public function description(): string
    {
        return 'Hashes the value preserving uniqueness while anonymizing.';
    }
}
