<?php

declare(strict_types=1);

namespace Bernskiold\LaravelDataScrubber\Services;

use Bernskiold\LaravelDataScrubber\Contracts\Scrubbable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ModelDiscoveryService
{
    /**
     * Discover all models implementing Scrubbable from configured paths.
     *
     * @return array<int, class-string<Model&Scrubbable>>
     */
    public function discover(): array
    {
        return $this->discoverInPaths($this->getConfiguredPaths());
    }

    /**
     * Discover all models implementing Scrubbable in the given paths.
     *
     * @param  array<int, string>  $paths
     * @return array<int, class-string<Model&Scrubbable>>
     */
    public function discoverInPaths(array $paths): array
    {
        $models = [];

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            $finder = new Finder;
            $finder->files()->in($path)->name('*.php');

            foreach ($finder as $file) {
                $class = $this->getClassFromFile($file->getRealPath());

                if ($class && $this->isScrubbableModel($class)) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }

    /**
     * Get the configured model paths from config.
     *
     * @return array<int, string>
     */
    public function getConfiguredPaths(): array
    {
        return config('data-scrubber.model_paths', [app_path('Models')]);
    }

    /**
     * Get the fully qualified class name from a file.
     */
    protected function getClassFromFile(string $path): ?string
    {
        $contents = File::get($path);

        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatches) &&
            preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
            return $namespaceMatches[1].'\\'.$classMatches[1];
        }

        return null;
    }

    /**
     * Check if a class is a Model implementing Scrubbable.
     */
    protected function isScrubbableModel(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return $reflection->isSubclassOf(Model::class)
            && $reflection->implementsInterface(Scrubbable::class)
            && ! $reflection->isAbstract();
    }
}
