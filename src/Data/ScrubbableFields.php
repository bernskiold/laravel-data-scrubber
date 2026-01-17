<?php

namespace Bernskiold\LaravelDataScrubber\Data;

use ArrayIterator;
use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Countable;
use Illuminate\Support\Traits\Conditionable;
use IteratorAggregate;
use Traversable;

/**
 * A data object representing the scrubbable fields configuration for a model.
 *
 * Provides a fluent API for building field configurations and implements
 * IteratorAggregate and Countable for easy iteration and counting.
 *
 * @implements IteratorAggregate<string, ScrubStrategy|class-string<ScrubStrategy>|string>
 */
final class ScrubbableFields implements IteratorAggregate, Countable
{
    use Conditionable;

    /**
     * The field configurations.
     *
     * @var array<string, ScrubStrategy|class-string<ScrubStrategy>|string>
     */
    private array $fields;

    /**
     * @param  array<string, ScrubStrategy|class-string<ScrubStrategy>|string>  $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    /**
     * Create a new ScrubbableFields instance.
     *
     * @param  array<string, ScrubStrategy|class-string<ScrubStrategy>|string>  $fields
     */
    public static function make(array $fields = []): static
    {
        return new static($fields);
    }

    /**
     * Add one or more fields with their scrubbing strategies.
     *
     * Single field:
     *     ->add('email', AnonymizeEmailStrategy::class)
     *
     * Multiple fields:
     *     ->add(['email' => AnonymizeEmailStrategy::class, 'phone' => NullStrategy::class])
     *
     * @param  string|array<string, ScrubStrategy|class-string<ScrubStrategy>|string>  $field
     * @param  ScrubStrategy|class-string<ScrubStrategy>|string|null  $strategy
     */
    public function add(string|array $field, ScrubStrategy|string|null $strategy = null): static
    {
        if (is_array($field)) {
            $this->fields = array_merge($this->fields, $field);

            return $this;
        }

        $this->fields[$field] = $strategy;

        return $this;
    }

    /**
     * Remove a field from the scrubbable fields.
     */
    public function remove(string $field): static
    {
        unset($this->fields[$field]);

        return $this;
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Get all strategy configurations.
     *
     * @return array<string, ScrubStrategy|class-string<ScrubStrategy>|string>
     */
    public function strategies(): array
    {
        return $this->fields;
    }

    /**
     * Check if a field exists.
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * Get the strategy configuration for a specific field.
     *
     * @return ScrubStrategy|class-string<ScrubStrategy>|string|null
     */
    public function get(string $field): ScrubStrategy|string|null
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->fields) === 0;
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the underlying array representation.
     *
     * @return array<string, ScrubStrategy|class-string<ScrubStrategy>|string>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    /**
     * Get an iterator for the fields.
     *
     * @return Traversable<string, ScrubStrategy|class-string<ScrubStrategy>|string>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->fields);
    }

    /**
     * Get the count of fields.
     */
    public function count(): int
    {
        return count($this->fields);
    }
}
