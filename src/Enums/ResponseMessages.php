<?php

namespace Mrmarchone\LaravelAutoCrud\Enums;

enum ResponseMessages: string
{
    case RETRIEVED = 'data retrieved successfully.';

    case CREATED = 'data created successfully.';

    case UPDATED = 'data updated successfully.';

    case DELETED = 'data deleted successfully.';

    /**
     * Get the translated message for this response type.
     * 
     * Translation priority:
     * 1. Package translation file (laravel-auto-crud::response_messages.{key})
     * 2. Config value (laravel_auto_crud.response_messages.{key})
     * 3. Enum default value
     *
     * @return string
     */
    public function message(): string
    {
        $key = strtolower($this->name);
        $translationKey = "laravel-auto-crud::response_messages.{$key}";
        
        // Try package translation first
        $translated = trans($translationKey);
        if ($translated !== $translationKey) {
            return $translated;
        }
        
        // Fall back to config
        $configValue = config("laravel_auto_crud.response_messages.{$key}");
        if ($configValue !== null) {
            return __($configValue);
        }
        
        // Fall back to enum default value
        return __($this->value);
    }
}
