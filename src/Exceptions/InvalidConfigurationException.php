<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Exceptions;

use InvalidArgumentException;

class InvalidConfigurationException extends InvalidArgumentException
{
    /**
     * Create a new invalid configuration exception instance.
     *
     * @param string $configKey The configuration key that is invalid
     * @param mixed $providedValue The value that was provided (will be converted to string)
     * @param string $expectedFormat Description of the expected format
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        private readonly string $configKey,
        mixed $providedValue = null,
        private readonly string $expectedFormat = '',
        ?\Throwable $previous = null
    ) {
        $message = "Invalid configuration for '{$configKey}'.";
        
        if ($providedValue !== null) {
            $valueString = is_scalar($providedValue) 
                ? var_export($providedValue, true)
                : gettype($providedValue);
            $message .= " Provided: {$valueString}";
        }
        
        if ($expectedFormat !== '') {
            $message .= " Expected: {$expectedFormat}";
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the configuration key that is invalid.
     *
     * @return string
     */
    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    /**
     * Get the expected format description.
     *
     * @return string
     */
    public function getExpectedFormat(): string
    {
        return $this->expectedFormat;
    }
}

