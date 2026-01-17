<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DeleteFileStrategy implements ScrubStrategy
{
    protected ?string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('data-scrubber.strategies.delete_file.disk');
    }

    /**
     * Apply the delete file strategy.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value !== null && is_string($value)) {
            $this->getStorage()->delete($value);
        }

        return null;
    }

    protected function getStorage(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    public function label(): string
    {
        return 'Delete file from storage and set to NULL';
    }

    public function description(): string
    {
        return 'Deletes the file from storage (using the value as the path) and sets the field to NULL.';
    }
}
