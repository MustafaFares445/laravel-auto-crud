<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Exceptions;

use RuntimeException;

class GenerationException extends RuntimeException
{
    /**
     * Create a new generation exception instance.
     *
     * @param string $operation The operation that failed (e.g., 'controller generation', 'stub generation')
     * @param string $modelName The model name for which generation failed
     * @param string $details Additional details about the failure
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        private readonly string $operation,
        private readonly string $modelName,
        private readonly string $details = '',
        ?\Throwable $previous = null
    ) {
        $message = "Failed to generate {$operation} for model '{$modelName}'.";
        
        if ($details !== '') {
            $message .= " {$details}";
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the operation that failed.
     *
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Get the model name for which generation failed.
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * Get the additional details about the failure.
     *
     * @return string
     */
    public function getDetails(): string
    {
        return $this->details;
    }
}

