<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Exceptions;

use InvalidArgumentException;

class ModelNotFoundException extends InvalidArgumentException
{
    /**
     * Create a new model not found exception instance.
     *
     * @param string $modelName The model class name that was not found
     * @param string|null $suggestedPath Optional suggested path or model name
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        private readonly string $modelName,
        private readonly ?string $suggestedPath = null,
        ?\Throwable $previous = null
    ) {
        $message = "Model class '{$modelName}' not found.";
        
        if ($suggestedPath !== null) {
            $message .= " Did you mean: {$suggestedPath}?";
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the model name that was not found.
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * Get the suggested path or model name.
     *
     * @return string|null
     */
    public function getSuggestedPath(): ?string
    {
        return $this->suggestedPath;
    }
}

