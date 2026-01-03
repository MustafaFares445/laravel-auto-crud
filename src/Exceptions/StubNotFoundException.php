<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Exceptions;

use RuntimeException;

class StubNotFoundException extends RuntimeException
{
    /**
     * Create a new stub not found exception instance.
     *
     * @param string $stubType The stub file name (without .stub extension)
     * @param array<string> $checkedPaths Array of paths that were checked
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        private readonly string $stubType,
        private readonly array $checkedPaths = [],
        ?\Throwable $previous = null
    ) {
        $message = "Stub file '{$stubType}.stub' not found.";
        
        if (!empty($checkedPaths)) {
            $paths = implode(', ', $checkedPaths);
            $message .= " Checked paths: {$paths}";
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the stub type that was not found.
     *
     * @return string
     */
    public function getStubType(): string
    {
        return $this->stubType;
    }

    /**
     * Get the paths that were checked.
     *
     * @return array<string>
     */
    public function getCheckedPaths(): array
    {
        return $this->checkedPaths;
    }
}

