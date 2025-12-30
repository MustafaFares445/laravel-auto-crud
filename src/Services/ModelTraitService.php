<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelTraitService
{
    /**
     * Inject traits into a model file.
     *
     * @param string $modelPath Full path to the model file
     * @param array<string> $traits Array of fully qualified trait class names
     * @return void
     * @throws \RuntimeException If file cannot be read or written
     */
    public function injectTraits(string $modelPath, array $traits): void
    {
        if (!File::exists($modelPath)) {
            return;
        }

        if (!is_readable($modelPath)) {
            throw new \RuntimeException("Model file is not readable: {$modelPath}");
        }

        try {
            $content = File::get($modelPath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read model file: {$modelPath}");
            }

            // Validate that the file contains valid PHP class structure
            if (!preg_match('/^\s*<\?php/i', $content)) {
                throw new \RuntimeException("File does not appear to be a valid PHP file: {$modelPath}");
            }

            if (!preg_match('/class\s+\w+/', $content)) {
                throw new \RuntimeException("File does not contain a valid class definition: {$modelPath}");
            }

            $modified = false;

            foreach ($traits as $trait) {
                if (!class_exists($trait) && !trait_exists($trait)) {
                    throw new \InvalidArgumentException("Trait or class does not exist: {$trait}");
                }

                if (!$this->hasTrait($content, $trait)) {
                    $content = $this->addTrait($content, $trait);
                    $modified = true;
                }
            }

            if ($modified) {
                if (!is_writable($modelPath)) {
                    throw new \RuntimeException("Model file is not writable: {$modelPath}");
                }
                File::put($modelPath, $content);
            }
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            throw new \RuntimeException("Model file not found: {$modelPath}", 0, $e);
        }
    }

    /**
     * Check if the model already has the trait.
     *
     * @param string $content Model file content
     * @param string $trait Fully qualified trait name
     * @return bool True if trait is already present
     */
    private function hasTrait(string $content, string $trait): bool
    {
        $traitName = class_basename($trait);

        if (! preg_match('/class\s+[^{]+{(.*)}/s', $content, $matches)) {
            return false;
        }

        $classBody = $matches[1] ?? '';

        // Support multiple traits per statement: use A, B;
        if (preg_match_all('/use\s+([^;]+);/', $classBody, $useMatches)) {
            foreach ($useMatches[1] as $list) {
                $traitsInUse = array_map('trim', explode(',', $list));
                if (in_array($traitName, $traitsInUse, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a trait to the model class.
     *
     * @param string $content Model file content
     * @param string $trait Fully qualified trait name
     * @return string Modified content with trait added
     * @throws \RuntimeException If content structure is invalid
     */
    private function addTrait(string $content, string $trait): string
    {
        $traitName = class_basename($trait);
        
        // 1. Add import if missing
        $escapedTrait = preg_quote($trait, '/');
        if (! preg_match('/^use\s+' . $escapedTrait . '\s*;/m', $content)) {
            $replaced = preg_replace('/namespace\s+[^;]+;/', "$0\n\nuse {$trait};", $content, 1);
            if ($replaced === null) {
                throw new \RuntimeException("Failed to add trait import: {$trait}");
            }
            $content = $replaced;
        }

        // 2. Add trait usage inside the class, respecting existing lists
        if (preg_match('/class\s+\w+(?:\s+extends\s+[^{]+)?(?:\s+implements\s+[^{]+)?\s*{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1] + strlen($matches[0][0]);
            $classBody = substr($content, $offset);

            if (preg_match('/use\s+([^;]+);/m', $classBody, $useMatches, PREG_OFFSET_CAPTURE)) {
                $existingList = $useMatches[1][0];
                $existingTraits = array_map('trim', explode(',', $existingList));

                if (! in_array($traitName, $existingTraits, true)) {
                    $existingTraits[] = $traitName;
                    $newUse = 'use ' . implode(', ', array_unique($existingTraits)) . ';';

                    $content = substr_replace(
                        $content,
                        $newUse,
                        $offset + $useMatches[0][1],
                        strlen($useMatches[0][0])
                    );
                }
            } else {
                $content = substr_replace($content, "\n    use {$traitName};\n", $offset, 0);
            }
        } else {
            throw new \RuntimeException("Could not find class definition in content");
        }

        return $content;
    }
}
