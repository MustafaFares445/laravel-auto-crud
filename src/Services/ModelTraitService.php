<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelTraitService
{
    public function injectTraits(string $modelPath, array $traits): void
    {
        if (!File::exists($modelPath)) {
            return;
        }

        $content = File::get($modelPath);
        $modified = false;

        foreach ($traits as $trait) {
            if (!$this->hasTrait($content, $trait)) {
                $content = $this->addTrait($content, $trait);
                $modified = true;
            }
        }

        if ($modified) {
            File::put($modelPath, $content);
        }
    }

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

    private function addTrait(string $content, string $trait): string
    {
        $traitName = class_basename($trait);
        
        // 1. Add import if missing
        if (! preg_match('/^use\s+' . preg_quote($trait, '/') . '\s*;/m', $content)) {
            $content = preg_replace('/namespace\s+[^;]+;/', "$0\n\nuse {$trait};", $content, 1);
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
        }

        return $content;
    }
}
